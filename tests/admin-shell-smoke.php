<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-shell-smoke.php	Dependency-free smoke test for the shell of the workbench
 *											(_admin/Admin.php): the panel registry - what a module's panel may bring
 *											and what the shell makes of it, every word a panel asks for -, the one
 *											reaction point '/nino/admin/action', the scoped permissions inside a
 *											panel, and the failure codes with their words in both interface
 *											languages. Runs against an isolated sandbox directory, never touches the
 *											real project data.
 *
 *	Usage: php tests/admin-shell-smoke.php
 */

require __DIR__. '/harness.php';

/**
 *	Dispatch one action directly against a Dev\* module class
 *
 *	A copy of the helper in admin-system-smoke.php, which the other admin suites
 *	carry as well: it is no helper the harness should hold
 *
 *	@param		array 		&$appData
 *	@param		string		$class				eg. "\Nino\Modules\Config\Admin"
 *	@param		string		$method				eg. "apiList"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body, request ]
 */
function callDev( array &$appData, string $class, string $method, array $data = [] ): array {
	return ninoCall( $appData, [ $class, $method ], $data );
}

$appData = ninoSandbox( 'admin-shell' );
$sandbox = ninoSandboxDir( $appData );
mkdir( $sandbox. '/private/elements', 0777, true );

// A hand-authored-shaped type file (top-level 'title' key, real locale content) -
// apiSave must only ever touch 'title'/'model', never this real content
file_put_contents( $sandbox. '/private/elements/testtype.php', '<?php return [
	\'title\'	=> \'Test Type\',
	\'model\'	=> [ \'name\' => [ \'type\' => \'string\', \'locale\' => true ] ],
	\'*\'			=> [ \'*\' => [] ],
	\'de_DE\'	=> [ \'item1\' => [ \'name\' => \'Hallo\' ] ],
];' );

$appData['/nino/dir']		= '';

// A set-up project with one developer account, logged in - the shell serves
// the workbench, every panel action runs against this session
$appData['/nino/install/completed'] = true;
ninoUser( $appData, 'dev@example.com' );

echo "Sandbox: $sandbox\n\n";


// --- Panel registry - a module brings its own /_admin screen ----------------

echo "\nAdmin::panels - a module's panel joins the shell without a shell change\n";

/** A minimal /_admin panel: actions() and nav() required, the rest optional (see \Nino\Panels) */
class AdminSmokeDummyPanel {
	public static function actions(): array { return [ 'dummy/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'dummy', 'Dummy', 55 ]; }
	public static function assets(): array { return [ '/app/Dummy/assets/admin.js', '/app/Dummy/assets/admin.css' ]; }
	public static function apiList( array &$appData, array &$request ): void {
		if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
			return;
		\Nino\Http::ok( $request, [ 'dummies' => 3 ] );
	}
}

/** A second panel trying to take over the core Config screen by reusing its uri */
class AdminSmokeShadowPanel {
	public static function actions(): array { return [ 'config/get' => [ self::class, 'apiHijack' ] ]; }
	public static function nav(): array { return [ 'config', 'Shadow' ]; }
	public static function apiHijack( array &$appData, array &$request ): void { \Nino\Http::ok( $request, [ 'hijacked' => true ] ); }
}

/** The runtime module contributing both, answering adminPanels() */
class AdminSmokeDummyModule {
	/** Once per registry build - the counter the caching check below reads */
	public static int $asked = 0;
	public static function adminPanels( array &$appData ): array { self::$asked++; return [ 'AdminSmokeDummyPanel', 'AdminSmokeShadowPanel' ]; }
}

/** A module that brings no panel at all - a second entry in the module list, and nothing else */
class AdminSmokeSilentModule {
	public static function adminPanels( array &$appData ): array { return []; }
}

/** A panel naming a script that is not on disk - the registry has to say so */
class AdminSmokeGhostPanel {
	public static function actions(): array { return [ 'ghost/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'ghost', 'Ghost', 56 ]; }
	public static function assets(): array { return [ '/app/Ghost/assets/ghost.js' ]; }
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request ); }
}

class AdminSmokeGhostAssetModule {
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeGhostPanel' ]; }
}

/** A kernel-side panel naming the 'features' group - not below \Nino\Features::dir(), so this is refused */
class AdminSmokeFeatureNamerPanel {
	public static function actions(): array { return [ 'featurenamer/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'featurenamer', 'Feature namer', 57, 'features' ]; }
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request ); }
}

class AdminSmokeFeatureNamerModule {
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeFeatureNamerPanel' ]; }
}

/** A panel answering head() false: its pane opens on its screen, with no row naming it - what the Dashboard does */
class AdminSmokeHeadlessPanel {
	public static function actions(): array { return [ 'headless/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'headless', 'Headless', 59 ]; }
	public static function head(): bool { return false; }
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request ); }
}

class AdminSmokeHeadlessModule {
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeHeadlessPanel' ]; }
}

/** A panel whose icon() answers whatever the icon check below hands it */
class AdminSmokeIconPanel {
	public static string $icon = '';
	public static function actions(): array { return [ 'iconpanel/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'iconpanel', 'Icon panel', 58 ]; }
	public static function icon(): string { return self::$icon; }
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request ); }
}

class AdminSmokeIconModule {
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeIconPanel' ]; }
}

/** A panel a project brings: every optional method answered, a few of them badly - which is what the registry has to cope with */
class AdminSmokeProjectPanel {
	public const string PERM = '/_admin/project/manage';
	public static function actions(): array { return [ 'project/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'project', 'Project <Panel>', 62 ]; }
	public static function perm(): string { return self::PERM; }
	public static function panes(): array { return [ 'project-list', 'project-form', 'Not A Pane' ]; }
	public static function assets(): array { return [ '/app/Project/assets/project.js', '/app/Project/assets/project.css', '/../etc/passwd.js', 'project.js' ]; }
	public static function summary( array &$appData ): array { return [ 'value' => 7, 'label' => 'Projects' ]; }
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request, [ 'projects' => 7 ] ); }
}

/** The runtime module contributing it, and a class that is none */
class AdminSmokeProjectModule {
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeProjectPanel', 'NoSuchClassAtAll' ]; }
}

$withModule = $appData;
$withModule['/nino/modules'] = [ 'AdminSmokeDummyModule', '\\Nino\\Modules\\Navigation' ];

$registry = \Nino\Admin\Admin::panels( $withModule );
$order 		= array_keys( $registry );
check( 'the tool\'s own panels are all there, content first, then structure, then system, from nav() alone', array_values( array_diff( $order, [ 'dummy', 'navs' ] ) ) === [ 'dashboard', 'elements', 'text', 'images', 'logs', 'routes', 'users', 'language', 'backups', 'features', 'config' ] );
check( 'the module panel sits where its weight puts it - in the content group, after images (40), before logs (90)', array_search( 'dummy', $order, true ) === array_search( 'images', $order, true ) + 1 && array_search( 'logs', $order, true ) === array_search( 'dummy', $order, true ) + 1
	&& $registry['dummy']['class'] === 'AdminSmokeDummyPanel' );
check( 'the tool\'s own tabs sit on their panes', array_map( static fn( array $p ): array => array_keys( $p['tabs'] ), array_intersect_key( $registry, array_flip( [ 'elements', 'text', 'images', 'users', 'language' ] ) ) ) === [ 'elements' => [ 'types' ], 'text' => [ 'keys' ], 'images' => [ 'slots' ], 'users' => [ 'roles', 'lockout', 'recoverypw' ], 'language' => [ 'translations' ] ]
	&& $registry['elements']['tabs']['types']['parent'] === 'elements' && $registry['elements']['tabs']['types']['perm'] === \Nino\Modules\Elements\Types::MANAGE_PERM );
check( 'a tab is not a rail entry, but allPanels() lists it right after its panel', isset( $registry['types'] ) === false && array_slice( array_keys( \Nino\Admin\Admin::allPanels( $withModule ) ), 1, 2 ) === [ 'elements', 'types' ] );
check( 'a core panel has no perm() and is open to every account', $registry['users']['perm'] === '' && $registry['dashboard']['perm'] === '' );
check( 'the Navigations panel names the structure group and sits after routes (20)', array_search( 'navs', $order, true ) === array_search( 'routes', $order, true ) + 1 );
check( 'the Navigations panel is the Navigation module\'s and comes with it', $registry['navs']['class'] === \Nino\Modules\Navigation\Admin::class );
check( 'a panel reusing a core uri is dropped, the core panel keeps it', $registry['config']['class'] === \Nino\Modules\Config\Admin::class
	&& str_contains( \Nino\Admin\Panels::navHtml( $registry ), 'Shadow' ) === false );

$navHtml = \Nino\Admin\Panels::navHtml( $registry );
check( 'nav() is rendered - every panel has its link, the module\'s included, an icon or its initial beside the label', str_contains( $navHtml, 'id="admin-nav-dummy" data-panel="dummy" data-layout="page"><span class="nino-admin-nav-icon" aria-hidden="true"><b>D</b></span><span class="nino-admin-nav-label">Dummy</span></a>' ) === true && str_contains( $navHtml, 'id="admin-nav-navs" data-panel="navs" data-layout="page">' ) === true && str_contains( $navHtml, '<span class="nino-admin-nav-label">[[/_admin/nav/navs]]</span></a>' ) === true );
check( 'every label is a fill - structure and system panels the same as the content ones, so the rail speaks one language', str_contains( $navHtml, '>[[/_admin/nav/routes]]</span></a>' ) === true && str_contains( $navHtml, '>[[/_admin/nav/backups]]</span></a>' ) === true && str_contains( $navHtml, '>[[/_admin/nav/text]]</span></a>' ) === true && str_contains( $navHtml, '>[[/_admin/nav/user]]</span></a>' ) === true );
check( 'the three groups actually on screen get their headings, in the GROUPS order - features carries no heading while nothing sits in it', preg_match( '/nav-group" data-group="content".*data-group="structure".*data-group="system"/s', $navHtml ) === 1
	&& str_contains( $navHtml, 'data-group="features"' ) === false );
check( 'every rail link is a real link to its panel - a new tab or a copied link opens it, the script only answers the plain click', preg_match_all( '/<a href="#([a-z][a-z0-9-]*)" id="admin-nav-\1" data-panel="\1"/', $navHtml ) === substr_count( $navHtml, 'data-panel=' )
	&& str_contains( $navHtml, '<a href="#" ' ) === false );
check( 'a group heading is a button that folds its links, open as rendered - a button, not a span: Enter and Space need no handler of their own', preg_match_all( '/<button type="button" class="nino-admin-nav-group" data-group="[a-z]+" aria-expanded="true">\[\[\/_admin\/nav\/group\/[a-z]+\]\]<\/button>/', $navHtml ) === substr_count( $navHtml, 'nino-admin-nav-group' )
	&& str_contains( $navHtml, '<span class="nino-admin-nav-group"' ) === false );
// The script reads a link's group from the order: a heading, then its links
$railGroups = [];
$railOpen 	= null;
preg_match_all( '/data-group="([a-z]+)"|id="admin-nav-([a-z0-9-]+)"/', $navHtml, $railParts, PREG_SET_ORDER );
foreach( $railParts as $railPart ) {
	if( ( $railPart[1] ?? '' ) !== '' )
		$railOpen = $railPart[1];
	else
		$railGroups[$railPart[2]] = $railOpen;
}
$railOk = $railGroups !== [];
foreach( $railGroups as $railUri => $railGroup )
	$railOk = $railOk && $railGroup === $registry[$railUri]['group'];
check( 'every link stands under the heading of its own group, which is how the script knows what a heading folds', $railOk === true && count( $railGroups ) === count( $registry ) );
check( 'GROUPS lists all four, features between structure and system', \Nino\Admin\Panels::GROUPS === [ 'content', 'structure', 'features', 'system' ] );

/*	The registry is built once per request and reused. Building it is a glob
	over the module directories, a ReflectionClass per panel class to tell a
	feature's from the tool's own, and a nav()/actions() call each - and a
	single GET of the workbench asked for it four times over: the asset
	bundles, the text fills, the rail and the panes. Every panel action asked
	once more	*/
// A fresh request: $withModule has been through panels() above, and the
// registry it built is in it (see Panels::collect()'s own cache)
$cachedRegistry = $withModule;
unset( $cachedRegistry['./_admin/panels'] );
AdminSmokeDummyModule::$asked = 0;
\Nino\Admin\Admin::panels( $cachedRegistry );
\Nino\Admin\Admin::allPanels( $cachedRegistry );
\Nino\Admin\Admin::visiblePanels( $cachedRegistry );
\Nino\Admin\Admin::actions( $cachedRegistry );
check( 'four askings of the registry build it once', AdminSmokeDummyModule::$asked === 1 );

/*	...and a module list that changed is a different registry rather than the
	stale one. The Features panel switches a feature on and builds the
	workbench again inside the same request - that is the whole reason it does
	so - and the panel the feature brought has to be in what comes back	*/
$cachedRegistry['/nino/modules'] = array_merge( $cachedRegistry['/nino/modules'], [ 'AdminSmokeSilentModule' ] );
$rebuilt = \Nino\Admin\Admin::panels( $cachedRegistry );
check( '...and a module switched on inside the request gets a fresh one', AdminSmokeDummyModule::$asked === 2 && isset( $rebuilt['dummy'] ) === true );
check( 'which is then reused in its turn', ( static function() use ( $cachedRegistry ): bool {
	$again = $cachedRegistry;
	\Nino\Admin\Admin::panels( $again );
	return AdminSmokeDummyModule::$asked === 2;
} )() );

check( 'a panel naming the features group from outside features/ is refused and falls back to content, with a warning', ( static function() use ( $withModule ): bool {
	$withFeatureNamer = $withModule;
	$withFeatureNamer['/nino/modules'] = array_merge( $withFeatureNamer['/nino/modules'], [ 'AdminSmokeFeatureNamerModule' ] );
	ninoWarnings();
	$registry = \Nino\Admin\Admin::panels( $withFeatureNamer );
	$warnings = ninoWarnings();
	return ( $registry['featurenamer']['group'] ?? null ) === 'content'
		&& count( array_filter( $warnings, static fn( string $m ): bool => str_contains( $m, 'AdminSmokeFeatureNamerPanel' ) && str_contains( $m, 'features' ) ) ) === 1;
} )() );

/*	The head is the default and the Dashboard the exception, not the other
	way round: a panel that says nothing opens with the row that names it,
	one that answers head() false opens on its screen alone - and the
	registry carries the answer, so the rail and the panes read one value	*/
check( 'a panel answering head() false opens on its screen alone; one that says nothing gets the head, the Dashboard is the one shipped panel without', ( static function() use ( $withModule ): bool {
	$withHeadless = $withModule;
	$withHeadless['/nino/modules'] = array_merge( $withHeadless['/nino/modules'], [ 'AdminSmokeHeadlessModule' ] );
	$registry = \Nino\Admin\Admin::panels( $withHeadless );
	$html = \Nino\Admin\Panels::panesHtml( $registry );
	return ( $registry['headless']['head'] ?? null ) === false && ( $registry['dummy']['head'] ?? null ) === true && ( $registry['dashboard']['head'] ?? null ) === false
		&& str_contains( $html, '<div id="admin-content-headless" data-panel="headless" data-layout="page" hidden><div id="headless-list"></div></div>' ) === true
		&& str_contains( $html, 'admin-content-headless' ) === true && substr_count( $html, 'admin-panel-head' ) === count( $registry ) - 2
		&& str_contains( $html, '<div id="admin-content-dashboard" data-panel="dashboard" data-layout="page" hidden></div>' ) === true
		&& substr_count( $html, '<h2 class="admin-panel-title">' ) === substr_count( $html, '<div class="admin-panel-head">' );
} )() );

$panesHtml = \Nino\Admin\Panels::panesHtml( $registry );
check( 'every pane carries the mount points its script renders into, under the head that names the panel', str_contains( $panesHtml, '<div id="admin-content-navs" data-panel="navs" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">[[/_admin/nav/navs]]</h2><div class="admin-panel-actions"></div></div><div id="navs-list"></div><div id="navs-form"></div></div>' ) === true );
check( 'a panel without panes() gets the conventional <uri>-list mount', str_contains( $panesHtml, '<div id="admin-content-dummy" data-panel="dummy" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">Dummy</h2><div class="admin-panel-actions"></div></div><div id="dummy-list"></div></div>' ) === true );
check( 'a pane with tabs carries the shared tab bar in its head, beside the name, its own screen first, and a tab pane per tab', str_contains( $panesHtml, '<div id="admin-content-language" data-panel="language" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">[[/_admin/nav/language]]</h2><div class="nino-admin-tabs nino-admin-tabs--bar nino-admin-tabs--panel admin-panel-tabs" role="tablist"><button type="button" role="tab" id="admin-tabbutton-language" class="nino-admin-tab" data-tab="language" aria-controls="admin-tab-language" aria-selected="false">[[/_admin/nav/languages]]</button><button type="button" role="tab" id="admin-tabbutton-translations" class="nino-admin-tab" data-tab="translations" aria-controls="admin-tab-translations" aria-selected="false">[[/_admin/nav/translations]]</button></div><div class="admin-panel-actions"></div></div><div id="admin-tab-language" role="tabpanel" aria-labelledby="admin-tabbutton-language" data-tab="language" hidden><div id="language-form"></div></div><div id="admin-tab-translations" role="tabpanel" aria-labelledby="admin-tabbutton-translations" data-tab="translations" hidden><div id="translations-content"></div></div></div>' ) === true );


/*	Every piece of that markup is an entry of Panels::$html, not a string the
	method builds - which is what lets somebody change how the rail looks
	without reading the class that decides what is in it (AGENTS.md, "Markup
	belongs in a template"). Proven by replacing an entry: what comes back has
	to follow it	*/
$shippedLink = \Nino\Admin\Panels::$html['nav-link'];
\Nino\Admin\Panels::$html['nav-link'] = '<a class="own-rail" href="#[[uri]]" data-panel="[[uri]]">[[label]]</a>';
$replacedNav = \Nino\Admin\Panels::navHtml( $registry );
\Nino\Admin\Panels::$html['nav-link'] = $shippedLink;
check( 'the rail is rendered through the property, so a replaced link fragment is what comes back', str_contains( $replacedNav, '<a class="own-rail" href="#navs" data-panel="navs">[[/_admin/nav/navs]]</a>' ) === true
	&& str_contains( $replacedNav, 'nino-admin-nav-icon' ) === false );
check( '...and the shipped fragment is back afterwards', str_contains( \Nino\Admin\Panels::navHtml( $registry ), '<span class="nino-admin-nav-label">[[/_admin/nav/navs]]</span></a>' ) === true );

$shippedMount = \Nino\Admin\Panels::$html['mount'];
\Nino\Admin\Panels::$html['mount'] = '<section id="[[id]]" class="own-mount"></section>';
$replacedPanes = \Nino\Admin\Panels::panesHtml( $registry );
\Nino\Admin\Panels::$html['mount'] = $shippedMount;
check( 'the mount points are rendered through theirs too', str_contains( $replacedPanes, '<section id="dummy-list" class="own-mount"></section>' ) === true );

$shippedTitle = \Nino\Admin\Panels::$html['head-title'];
\Nino\Admin\Panels::$html['head-title'] = '<p class="own-title">[[label]]</p>';
$replacedHeads = \Nino\Admin\Panels::panesHtml( $registry );
\Nino\Admin\Panels::$html['head-title'] = $shippedTitle;
check( 'and the name in a pane\'s head through its own fragment', str_contains( $replacedHeads, '<div class="admin-panel-head"><p class="own-title">Dummy</p><div class="admin-panel-actions"></div></div><div id="dummy-list">' ) === true
	&& str_contains( \Nino\Admin\Panels::panesHtml( $registry ), '<h2 class="admin-panel-title">Dummy</h2>' ) === true );

// The language switcher is Admin's own fragment rather than Panels', since
// it is the one piece of the shell that class still renders itself
$shippedOption = \Nino\Admin\Admin::$html['locale-option'];
\Nino\Admin\Admin::$html['locale-option'] = '<option value="[[locale]]"[[selected]]>[[label]] ([[locale]])</option>';
$pickerFragment = new ReflectionMethod( '\Nino\Admin\Admin', '_localePickerHtml' );
$pickerFragment->setAccessible( true );
$replacedPicker = (string) $pickerFragment->invokeArgs( null, [ &$appData, 'en_US' ] );
\Nino\Admin\Admin::$html['locale-option'] = $shippedOption;
check( 'and so is the language switcher', str_contains( $replacedPicker, '<option value="en_US" selected>' ) === true
	&& str_contains( $replacedPicker, '(en_US)</option>' ) === true );

// Every file a shipped panel names has to be there, and every mount id it
// answers has to be one its script renders into - a renamed script or a
// pane spelled differently on the two sides is a panel whose tab opens on
// nothing, with no error anywhere (the bundler skips a missing file)
$shipped = $appData;
$shipped['/nino/modules'] = array_merge( \Nino\AppData::DEFAULTS['/nino/modules'], [ '\\Nino\\Modules\\Form', '\\Nino\\Modules\\Navigation', '\\Nino\\Modules\\Maintenance' ] );
$missingAssets = [];
$missingPanes = [];
foreach( \Nino\Admin\Admin::allPanels( $shipped ) as $uri => $panel ) {
	$script = '';
	foreach( $panel['assets'] as $asset ) {
		if( is_file( dirname( __DIR__ ). $asset ) === false )
			$missingAssets[] = $uri. ': '. $asset;
		elseif( str_ends_with( $asset, '.js' ) === true )
			$script .= (string) file_get_contents( dirname( __DIR__ ). $asset );
	}
	if( $panel['template'] !== '' && is_file( dirname( __DIR__ ). $panel['template']. '.tpl' ) === false )
		$missingAssets[] = $uri. ': '. $panel['template']. '.tpl';
	if( $panel['template'] === '' && $script !== '' )
		foreach( $panel['panes'] as $pane )
			if( str_contains( $script, "'". $pane. "'" ) === false )
				$missingPanes[] = $uri. ': '. $pane;
}
check( 'every file a shipped panel names exists'. ( $missingAssets === [] ? '' : ' - missing: '. implode( ', ', $missingAssets ) ), $missingAssets === [] );
check( 'every pane a shipped panel answers is one its script renders into'. ( $missingPanes === [] ? '' : ' - unrendered: '. implode( ', ', $missingPanes ) ), $missingPanes === [] );

// A label that is a fill key needs the fill, in every interface language -
// from the workbench's own text/<locale>.php or the panel's text() directory.
// A key without one renders as itself in the rail, with no error anywhere
$unlabelled = [];
foreach( \Nino\Admin\Admin::allPanels( $shipped ) as $uri => $panel ) {
	$labels = [ 'label' => $panel['label'], 'tab' => $panel['tab'] ];
	$tile 	= method_exists( $panel['class'], 'summary' ) === true ? $panel['class']::summary( $shipped ) : null;
	if( is_array( $tile ) === true && isset( $tile['label'] ) === true )
		$labels['tile'] = (string) $tile['label'];
	foreach( $labels as $what => $label ) {
		if( str_starts_with( $label, '/' ) === false )
			continue;
		foreach( [ 'en_US', 'de_DE' ] as $locale ) {
			$fills = (array) include dirname( __DIR__ ). '/_admin/text/'. $locale. '.php';
			if( $panel['text'] !== '' && is_file( dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php' ) === true )
				$fills += (array) include dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php';
			if( isset( $fills['[['. $label. ']]'] ) === false )
				$unlabelled[] = $uri. ' '. $what. ' '. $locale;
		}
	}
}
check( 'every panel label, tab name and dashboard tile is a fill both interface languages define'. ( $unlabelled === [] ? '' : ' - missing: '. implode( ', ', $unlabelled ) ), $unlabelled === [] );

// And the same for every word a panel's script asks for. A missing fill is
// silent - Nino.content.getText() answers '' and the button renders empty -
// so the only place it shows up is the screen nobody opened yet. The script
// says which keys it needs; the panel's text() directory and the workbench's
// own file are where they may live
/** Every [[/_admin/...]] a template asks for */
function self_fillKeys( string $source ): array {
	preg_match_all( '#\[\[(/_admin/[^\]]+)\]\]#', $source, $matches );
	return $matches[1];
}

$missingFills = [];
$askedByPanels = [];
foreach( \Nino\Admin\Admin::allPanels( $shipped ) as $uri => $panel ) {

	$keys = [];
	foreach( $panel['assets'] as $asset ) {

		if( str_ends_with( $asset, '.js' ) === false )
			continue;

		$source = (string) file_get_contents( dirname( __DIR__ ). $asset );
		// Only where the literal is the whole argument: a key built from a
		// type or a field name ( getText('/_admin/elements/field/'+ type+ ... ) )
		// is not a key this can look up
		preg_match_all( "#getText\(\s*'(/_admin/[^']+)'\s*\)#", $source, $matches );
		$keys = array_merge( $keys, $matches[1] );
		// ...and the words a failed request falls back to when the server named
		// none: handed to showError() and errorText() as a literal
		preg_match_all( "#'(/_admin/common/error/[^']+)'#", $source, $matches );
		$keys = array_merge( $keys, $matches[1] );
	}

	// A workspace panel renders a whole template into its pane rather than
	// building everything from script, and that markup asks for fills the same
	// way any template does - [[/key]]. It is the half that reads as English
	// on screen while every script check passes, which is exactly how a batch
	// of them once went missing
	if( method_exists( $panel['class'], 'template' ) === true ) {

		// template() answers a template *name* for the [template ...] shortcode,
		// so the extension belongs to the reader rather than the panel
		$template = dirname( __DIR__ ). $panel['class']::template(). '.tpl';

		if( is_file( $template ) === true )
			$keys = array_merge( $keys, self_fillKeys( (string) file_get_contents( $template ) ) );
	}

	$askedByPanels = array_merge( $askedByPanels, $keys );

	foreach( [ 'en_US', 'de_DE' ] as $locale ) {

		$fills = (array) include dirname( __DIR__ ). '/_admin/text/'. $locale. '.php';

		if( $panel['text'] !== '' && is_file( dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php' ) === true )
			$fills += (array) include dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php';

		foreach( array_unique( $keys ) as $key )
			if( isset( $fills['[['. $key. ']]'] ) === false )
				$missingFills[] = $uri. ' '. $key. ' '. $locale;
	}
}

// Not every word is asked for by a panel's own files. The shell's scripts are
// no panel's asset - the login card, the question before unsaved input is
// lost, the html editor - and a panel's class hands the screen its labels,
// hints and the dashboard's tiles as literal keys of its own. Any panel's
// text() directory may hold them, so the whole of the workbench's words is
// what they are looked up in. A feature the checkout carries is read the same
// way (the registry above is the kernel's own), and a checkout may carry none.
// The recovery page is English by design: it has no session to take a
// language from
$askedOutside = [];
foreach( array_merge( glob( dirname( __DIR__ ). '/_admin/assets/*.js' ) ?: [], glob( dirname( __DIR__ ). '/features/*/assets/*.js' ) ?: [] ) as $script ) {

	if( basename( $script ) === 'recovery.js' )
		continue;

	preg_match_all( "#getText\(\s*'(/_admin/[^']+)'\s*\)#", (string) file_get_contents( $script ), $matches );
	$askedOutside = array_merge( $askedOutside, $matches[1] );
	preg_match_all( "#'(/_admin/common/error/[^']+)'#", (string) file_get_contents( $script ), $matches );
	$askedOutside = array_merge( $askedOutside, $matches[1] );
}

$classFiles = array_merge( [ dirname( __DIR__ ). '/_nino/Nino/Modules/Navigation/Admin/Admin.php' ], glob( dirname( __DIR__ ). '/features/*/Admin/Admin.php' ) ?: [] );
foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ). '/_admin/Nino/Modules', FilesystemIterator::SKIP_DOTS ) ) as $classFile )
	if( $classFile->getExtension() === 'php' )
		$classFiles[] = $classFile->getPathname();

foreach( $classFiles as $classFile ) {

	preg_match_all( "#'(/_admin/(?:nav|dashboard|config|lockout|language|users)/[^'\s]+)'#", (string) file_get_contents( $classFile ), $matches );

	// A permission ends in /manage and is no word
	$askedOutside = array_merge( $askedOutside, array_filter( $matches[1], static fn( string $key ): bool => str_ends_with( $key, '/manage' ) === false ) );
}

foreach( [ 'en_US', 'de_DE' ] as $locale ) {

	$fills = (array) include dirname( __DIR__ ). '/_admin/text/'. $locale. '.php';

	foreach( \Nino\Admin\Admin::allPanels( $shipped ) as $panel )
		if( $panel['text'] !== '' && is_file( dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php' ) === true )
			$fills += (array) include dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php';

	foreach( glob( dirname( __DIR__ ). '/features/*/text/'. $locale. '.php' ) ?: [] as $featureText )
		$fills += (array) include $featureText;

	foreach( array_unique( $askedOutside ) as $key )
		if( isset( $fills['[['. $key. ']]'] ) === false )
			$missingFills[] = 'outside the panes '. $key. ' '. $locale;
}

// ...and the scan has to have seen the vocabulary, or "nothing missing" says nothing
check( 'every word a panel asks for, in its scripts and in its markup, is a fill both interface languages define'. ( $missingFills === [] ? '' : ' - missing: '. implode( ', ', array_slice( $missingFills, 0, 8 ) ) ), $missingFills === []
	&& count( array_unique( array_merge( $askedByPanels, $askedOutside ) ) ) > 200 );

// The two languages of a text family declare the same keys. A key in one file
// and not in the other renders as an empty string in the second language -
// a blank where the reason should be, with no error anywhere. A family is the
// workbench's own file and the text() directory of a panel
$families = [ '/_admin/text' ];
foreach( \Nino\Admin\Admin::allPanels( $shipped ) as $panel )
	if( $panel['text'] !== '' )
		$families[] = $panel['text'];

$unequal = [];
foreach( array_unique( $families ) as $family ) {

	$declared = [];
	$missing = [];
	foreach( [ 'en_US', 'de_DE' ] as $locale ) {

		$path = dirname( __DIR__ ). $family. '/'. $locale. '.php';

		if( is_file( $path ) === true )
			$declared[$locale] = array_keys( (array) include $path );
		else
			$missing[] = $locale;
	}

	// A file that is not there declares nothing, which is not "the same keys": a family that lost a language is named
	if( $missing !== [] )
		$unequal[] = $family. ' ('. implode( ' and ', $missing ). ' missing)';
	elseif( array_diff( $declared['en_US'], $declared['de_DE'] ) !== [] || array_diff( $declared['de_DE'], $declared['en_US'] ) !== [] )
		$unequal[] = $family;
}
check( 'the two interface languages of every text family declare the same keys'. ( $unequal === [] ? '' : ' - differ: '. implode( ', ', $unequal ) ), $unequal === [] );

// And the same thing end to end, which is the only version that cannot be
// fooled by a key this file's regexes do not recognise: render the whole
// workbench - the rail, every pane, and every panel that answers template() -
// in each interface language and look for a [[fill]] that came out the other
// side unresolved. A missing fill renders as its own key on screen, and that is
// what a person actually reports.
//
// The filesystem path is the real project root here rather than the sandbox,
// exactly as \Nino\init() sets it: the text files a panel names with
// \Nino\Admin\Panels::relative() are resolved against it, while config,
// content and uploads keep pointing at the sandbox.
$unresolvedFills = [];
foreach( [ 'en_US', 'de_DE', 'fr_FR' ] as $locale ) {

	$render = $shipped;
	$render['./nino/filesystem/path'] = dirname( __DIR__ );

	// A site language the workbench has no words in: the interface language
	// follows the site's, and the panels fall back to English rather than
	// rendering every label as its own key (see Admin::textFills())
	$render['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];

	// Every runtime module that brings a panel, not just the workbench's own:
	// the optional kernel modules are where a whole .tpl is rendered into a
	// pane, and the features ship panels of their own - a registry without
	// them would leave exactly those files unchecked
	$render['/nino/modules'] = array_values( array_filter( array_map(
		static function( string $dir ): string {
			$class = '\\Nino\\Modules\\'. basename( $dir );
			return class_exists( $class ) === true && method_exists( $class, 'adminPanels' ) === true ? $class : '';
		},
		array_merge( glob( dirname( __DIR__ ). '/_nino/Nino/Modules/*', GLOB_ONLYDIR ) ?: [], glob( dirname( __DIR__ ). '/features/*', GLOB_ONLYDIR ) ?: [] )
	) ) );

	// The two fills \Nino::request() registers before any template is rendered
	// (see its first Html::addFills() call). This file never goes through a
	// request, so they are seeded here rather than reported as missing
	\Nino\Html::addFills( $render, [
		'[[/nino/dir]]' 		=> \Nino\Filesystem::getDir( $render ),
		'[[/nino/public]]' 	=> \Nino\Filesystem::getPublicDir( $render ),
	], '*' );

	\Nino\Runtime::setSessionValue( $render, './admin/locale', $locale );
	\Nino\Admin\Admin::init( $render );

	$markup = \Nino\Admin\Admin::panesHtml( $render ). \Nino\Admin\Admin::navHtml( $render );

	foreach( \Nino\Admin\Admin::allPanels( $render ) as $panel ) {

		if( method_exists( $panel['class'], 'template' ) === false )
			continue;

		$file = dirname( __DIR__ ). $panel['class']::template(). '.tpl';

		if( is_file( $file ) === true )
			$markup .= (string) file_get_contents( $file );
	}

	// The language switcher names each locale from a fill the Localepicker
	// module's install step writes - an optional unit, and one that cannot know
	// about a locale added later through the Language panel. An option with no
	// name is a switcher nobody can use, so the code stands in for the word
	if( $locale === 'en_US' ) {
		$noNames = $render;
		$noNames['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
		$picker = \Nino\Html::renderHtml( $noNames, \Nino\Html::renderTextfill( $noNames, '/_admin/localepicker' ) );
		check( 'the language switcher never renders an option with no name', str_contains( $picker, '></option>' ) === false && substr_count( $picker, '<option' ) === 3 );
		/*	And the switcher itself is called something. It stands alone in the
			rail's settings popover and on the login card, with no visible word
			beside it, so without a name of its own it is announced as "combo
			box" - a control whose whole purpose is invisible to whoever cannot
			see the flag of options inside it	*/
		check( '...and the switcher itself carries a name, resolved in the interface language',
			str_contains( \Nino\Admin\Admin::$html['localepicker'], 'aria-label="[[/_admin/label/language]]"' ) === true
			&& preg_match( '/aria-label="\[\[/', $picker ) === 0
			&& preg_match( '/<select id="admin-localepicker" aria-label="[^"\[]+"/', $picker ) === 1 );

		// The name of a language is a text fill, which is editor content: the
		// Text panel writes it, and it went into the shell's markup as it
		// stood. An account holding only the Text permission could put a
		// script into the page every other account is served, its own session
		// included
		// Built here rather than read back from the fill the shell stored at
		// boot: that one was composed before this test wrote the name
		$named = $noNames;
		\Nino\Html::addFills( $named, [ '[[/_nino/locale/fr_FR/name]]' => '<img src=x onerror="alert(1)"> [[/nino/auth/user]]' ], '*' );
		$pickerMethod = new ReflectionMethod( '\Nino\Admin\Admin', '_localePickerHtml' );
		$pickerMethod->setAccessible( true );
		$namedPicker = \Nino\Html::renderHtml( $named, (string) $pickerMethod->invokeArgs( null, [ &$named, 'en_US' ] ) );
		check( 'a language name is drawn as text, markup and fill syntax and all', str_contains( $namedPicker, '<img src=x' ) === false
			&& str_contains( $namedPicker, '&lt;img src=x' ) === true
			&& str_contains( $namedPicker, '&#91;&#91;/nino/auth/user&#93;&#93;' ) === true );
	}

	// The registry has to actually contain the app panels, or this whole check
	// silently proves nothing about the files it was written for
	if( $locale === 'en_US' )
		check( 'the render check covers the runtime modules\' panels too', isset( \Nino\Admin\Admin::allPanels( $render )['maintenance'] ) === true && isset( \Nino\Admin\Admin::allPanels( $render )['submissions'] ) === true );

	preg_match_all( '/\[\[([^\]\[]+)\]\]/', \Nino\Html::renderHtml( $render, $markup ), $left );

	foreach( array_unique( $left[1] ) as $key )
		$unresolvedFills[] = $key. ' '. $locale;
}
check( 'the whole workbench renders with no fill left unresolved, in both interface languages and in a site language it has no words in'. ( $unresolvedFills === [] ? '' : ' - '. implode( ', ', array_slice( $unresolvedFills, 0, 8 ) ) ), $unresolvedFills === [] );
check( 'a panel naming a file that is not there is reported, and the file stays in the list for the bundler to skip', ( static function() use ( $appData ): bool {
	$ghost = $appData;
	$ghost['/nino/modules'] = [ 'AdminSmokeGhostAssetModule' ];
	ninoWarnings();
	$registry = \Nino\Admin\Admin::panels( $ghost );
	$warnings = ninoWarnings();
	return in_array( '/app/Ghost/assets/ghost.js', $registry['ghost']['assets'] ?? [], true ) === true
		&& count( array_filter( $warnings, static fn( string $m ): bool => str_contains( $m, '/app/Ghost/assets/ghost.js' ) ) ) === 1;
} )() );

/*	An icon is the one piece of markup a panel class hands the shell itself
	(see Panels::_entry()), and trusted code is still held to one shape: an
	inline svg and nothing that runs. A panel that answers anything else keeps
	its place in the rail and falls back to the label's initial, which is what
	a panel without an icon gets - so nothing a class returns here can end up
	in the rail as a handler or a script	*/
check( 'an icon a panel answers reaches the rail only as an inline svg with nothing that runs', ( static function() use ( $appData ): bool {

	$plain = '<svg viewBox="0 0 24 24"><path d="M4 4h16"/></svg>';
	$icons = [];

	foreach( [ $plain, '<img src="x" onerror="alert(1)">', '<svg viewBox="0 0 24 24" onload="alert(1)"><path d="M4 4h16"/></svg>', '<svg viewBox="0 0 24 24"><script>alert(1)</script></svg>' ] as $markup ) {
		AdminSmokeIconPanel::$icon = $markup;
		$withIcon = $appData;
		$withIcon['/nino/modules'] = [ 'AdminSmokeIconModule' ];
		$icons[$markup] = (string) ( \Nino\Admin\Admin::panels( $withIcon )['iconpanel']['icon'] ?? '' );
	}

	// The harmless one survives, so this cannot pass by refusing everything
	return $icons[$plain] === $plain
		&& array_filter( $icons, static fn( string $icon ): bool => $icon !== '' && ( str_starts_with( $icon, '<svg' ) === false || preg_match( '/<script|\son[a-z]+\s*=/i', $icon ) === 1 ) ) === [];
} )() );

$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $withModule, $getRequest );
\Nino\Admin\Admin::init( $withModule );
check( 'init bundles every panel script, the module\'s included, after the shell\'s own', in_array( '/app/Dummy/assets/admin.js', $withModule['/nino/html/assets']['/_admin/.cache/script.js'], true ) === true && in_array( '/_nino/Nino/Modules/Navigation/assets/admin.js', $withModule['/nino/html/assets']['/_admin/.cache/script.js'], true ) === true && $withModule['/nino/html/assets']['/_admin/.cache/script.js'][0] === '/_nino/Nino.js' );
check( 'and every panel stylesheet', in_array( '/app/Dummy/assets/admin.css', $withModule['/nino/html/assets']['/_admin/.cache/style.css'], true ) === true );
// The login page is no workbench: the kit the panels are built with
// (Nino.admin.js) is not in its bundle, and neither is any panel's script
check( 'the login bundle is the framework and the login shell alone', $withModule['/nino/html/assets']['/_admin/.cache/login.js'] === [ '/_nino/Nino.js', '/_admin/assets/login.js' ] );
// The registry the form panels report to (Nino.admin.dirty) is defined by the
// shell script, and every panel registers when its own file loads - so the shell
// script has to come first in the bundle, and the design system's primitives
// (the question's dialog) before it
$scriptBundle = $withModule['/nino/html/assets']['/_admin/.cache/script.js'];
$bundlePositions = array_flip( $scriptBundle );
$registers = [];
foreach( $scriptBundle as $file ) {
	if( str_contains( $file, '/Modules/' ) === true && preg_match( '/Nino\.admin\.dirty\.(?:register|watchForm)\(/', (string) file_get_contents( dirname( __DIR__ ). $file ) ) === 1 )
		$registers[] = $file;
}
check( 'the shell\'s registry is bundled before every panel script that registers with it', count( $registers ) >= 13
	&& isset( $bundlePositions['/_admin/assets/Nino.admin.js'], $bundlePositions['/_admin/assets/script.js'] ) === true
	&& $bundlePositions['/_admin/assets/Nino.admin.js'] < $bundlePositions['/_admin/assets/script.js']
	&& array_filter( $registers, static fn( string $file ): bool => $bundlePositions[$file] < $bundlePositions['/_admin/assets/script.js'] ) === [] );
// A fill is parsed as a fill key wherever square brackets appear in its value,
// so the JSON refusal carries none (docs/recipes/admin-panel.md, "Designing an
// admin frontend")
foreach( [ 'en_US', 'de_DE' ] as $locale ) {
	$elementFills = (array) include dirname( __DIR__ ). '/_admin/Nino/Modules/Elements/text/'. $locale. '.php';
	check( $locale. ': the Elements form words its refusals in fills, the JSON one without bracket characters',
		isset( $elementFills['[[/_admin/elements/error/json]]'], $elementFills['[[/_admin/elements/error/field-required]]'], $elementFills['[[/_admin/elements/label/locale-open]]'] ) === true
		&& preg_match( '/[\[\]]/', $elementFills['[[/_admin/elements/error/json]]'] ) === 0
		&& str_contains( $elementFills['[[/_admin/elements/label/locale-open]]'], '%s' ) === true && str_contains( $elementFills['[[/_admin/elements/label/locale-open]]'], '%d' ) === true );
}
check( 'the nav and the panes reach the template as fills', str_contains( \Nino\Html::renderTextfill( $withModule, '/_admin/nav' ), 'data-panel="dummy"' ) === true && str_contains( \Nino\Html::renderTextfill( $withModule, '/_admin/panes' ), 'id="dummy-list"' ) === true );

[ $status, $body ] = ninoCall( $withModule, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'dummy/list' );
check( 'handlePost dispatches the module\'s action', $status === 200 && ( $body['dummies'] ?? null ) === 3 );

[ , $body ] = ninoCall( $withModule, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'config/get' );
check( 'a core action can not be taken over by a module reusing its name', ( $body['hijacked'] ?? false ) === false );

$withoutModule = $appData;
$withoutModule['/nino/modules'] = [];
[ $status ] = ninoCall( $withoutModule, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'navs/list' );
check( 'with the Navigation module off, navs/list is an unknown action and the panel is gone', $status === 404 && isset( \Nino\Admin\Admin::panels( $withoutModule )['navs'] ) === false );

// What the registry keeps of the answers of a panel a project brings, and
// what the rail, the panes, the dashboard and the roles form make of it
$withProject = $appData;
$withProject['/nino/modules'] = [ 'AdminSmokeProjectModule' ];

$projectRegistry = \Nino\Admin\Admin::panels( $withProject );
check( 'a class without actions()/nav() is dropped rather than breaking the tool', in_array( 'NoSuchClassAtAll', array_column( $projectRegistry, 'class' ), true ) === false );
check( 'only well-formed mount ids survive panes()', $projectRegistry['project']['panes'] === [ 'project-list', 'project-form' ] );
check( 'only project-relative .js/.css paths survive assets() - no traversal, no bare names', $projectRegistry['project']['assets'] === [ '/app/Project/assets/project.js', '/app/Project/assets/project.css' ] );
check( 'the module panel is visible to an account holding its perm', isset( \Nino\Admin\Admin::visiblePanels( $withProject )['project'] ) === true );

$projectNav = \Nino\Admin\Admin::navHtml( $withProject );
check( 'a literal label is escaped for html and against the fill syntax', str_contains( $projectNav, 'Project &lt;Panel&gt;' ) === true );

$projectPanes = \Nino\Admin\Admin::panesHtml( $withProject );
check( 'every pane starts hidden, names its layout, opens with the head that names the panel and carries its mount points', str_contains( $projectPanes, '<div id="admin-content-project" data-panel="project" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">Project &lt;Panel&gt;</h2><div class="admin-panel-actions"></div></div><div id="project-list"></div><div id="project-form"></div></div>' ) === true );

[ , $body ] = ninoCall( $withProject, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'dashboard/summary' );
$projectTile = array_values( array_filter( $body['tiles'] ?? [], fn( array $tile ): bool => $tile['panel'] === 'project' ) )[0] ?? null;
check( 'the panel\'s summary() becomes a dashboard tile', $projectTile === [ 'panel' => 'project', 'value' => '7', 'label' => 'Projects' ] );

$permOptions = \Nino\Modules\Users\Admin::permOptions( $withProject );
check( 'the panel\'s perm() is assignable to a role, under its nav label and in its group', in_array( [ 'perm' => AdminSmokeProjectPanel::PERM, 'label' => 'Project <Panel>', 'group' => 'content', 'offered' => true ], $permOptions, true ) === true );

/*	Nothing says a request carries strings. Every one of these used to be
	a 500: 'action[]=x' is an "Illegal offset type in isset" in the
	dispatcher, 'data[]=x' a TypeError in json_decode(), and '?locale[]=x'
	an "Array to string conversion" - a level the runtime treats as fatal
	(see \Nino\Runtime::NON_FATAL_LEVELS). Two of them are reachable
	without signing in, and postData() is what recovery.php reads its
	password through.

	The harness records every warning and the dummy panels of this suite
	raise plenty of their own on the way, so the ones read here are those
	of these three calls - and only the one they would raise is asked for	*/
ninoWarnings();

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = [ 'dummy/list' ];
$_POST['data'] = json_encode( [] );
\Nino\Admin\Admin::handlePost( $withModule, $request );
check( 'an array-shaped action is answered as unknown, not raised at', $request['/nino/http/response']['statusCode'] === 404 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'dummy/list';
$_POST['data'] = [ 'x' ];
\Nino\Admin\Admin::handlePost( $withModule, $request );
check( 'an array-shaped data field reads as no data, not as a 500', $request['/nino/http/response']['statusCode'] === 200 );
check( '...and postData() answers the empty array it promises', \Nino\Admin\Admin::postData() === [] );

$_POST['data'] = json_encode( [] );

$arrayLocaleGet = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
$_GET['locale'] = [ 'de_DE' ];
\Nino\Admin\Admin::handleGet( $withModule, $arrayLocaleGet );
unset( $_GET['locale'] );
$arrayWarnings = ninoWarnings();

check( 'an array-shaped ?locale is ignored rather than cast', $arrayLocaleGet['/nino/http/response']['statusCode'] === 200 );
check( 'none of the three raised an "Array to string conversion" on the way',
	array_filter( $arrayWarnings, fn( string $message ): bool => str_contains( $message, 'Array to string conversion' ) ) === [] );

echo "\n";


// --- The workbench's one reaction point -----------------------------------
//
// A panel declares what it is (see Panels); this says what just happened in
// the tool, so a module can attach without owning the panel the action
// belongs to. Notification only - the action has already answered.

echo "'/nino/admin/action' - a module watching what the workbench does\n";

// The type the element actions below are posted for - the element content
// section of admin-elements-smoke.php makes the same
file_put_contents( $sandbox. '/private/elements/contenttype.php', '<?php return [
	\'title\'	=> \'Content Type\',
	\'model\'	=> [
		\'title\'	=> [ \'type\' => \'string\', \'locale\' => true ],
		\'views\'	=> [ \'type\' => \'integer\', \'default\' => 0 ],
	],
];' );

$lockoutPath	= \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json';
$resetLockout	= static function() use ( &$appData, $lockoutPath ): void {
	\Nino\Filesystem::putFileContent( $appData, $lockoutPath, [ 'tries' => 0, 'until' => 0 ] );
	unset( $appData['./nino/filesystem/cache'][$lockoutPath] );
};

// A recovery secret to post a wrong old password against
\Nino\Admin\Recovery::set( $appData, 'the real password' );

$seen = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/action', function( array &$appData, array &$event ) use ( &$seen ): void {
	$seen[] = $event;
} );

ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'type' => 'contenttype' ], 'elements/list' );

check( 'a dispatched action reaches a listener', count( $seen ) === 1 );
check( '...naming the action and the panel that handled it', ( $seen[0]['action'] ?? '' ) === 'elements/list' && ( $seen[0]['panel'] ?? '' ) === \Nino\Modules\Elements\Admin::class );
check( '...the account behind it', ( $seen[0]['user'] ?? '' ) === 'dev@example.com' );
check( '...what was posted', ( $seen[0]['data']['type'] ?? '' ) === 'contenttype' );
check( '...and the status it answered with', ( $seen[0]['status'] ?? 0 ) === 200 );

// The failures are the half the activity log leaves out, and the half a module
// watching for someone probing a panel actually wants
ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'type' => 'nope', 'uri' => 'nope' ], 'elements/get' );
check( 'a failed action reaches it too, with its status', count( $seen ) === 2 && ( $seen[1]['status'] ?? 0 ) !== 200 );

// Nothing ran, so there is nothing to announce
ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'no/such-action' );
check( 'an action no panel registered announces nothing', count( $seen ) === 2 );

// The listener is handed a description, not the request: a panel's answer is
// already written, and a listener that could rewrite it would be a veto with
// no gate in front of it
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/action', function( array &$appData, array &$event ): void {
	// Both shapes: the flat one this event has, and the one it would have if
	// the request itself were ever handed over by reference
	$event['status'] = 500;
	$event['/nino/http/response']['statusCode'] = 500;
	$event['body'] = [ 'error' => 'rewritten' ];
} );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'type' => 'contenttype' ], 'elements/list' );
check( 'a listener cannot change the answer the panel already gave', $status === 200 );

// The audit line is the tool's own guarantee, not a listener's - a log that
// could be lost by not registering a callback would not be a log
$logBefore = count( \Nino\Modules\Logs\Admin::recentLines( $appData, 50 ) );
ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'uri' => 'testtype', 'title' => 'Test Type Renamed', 'model' => [ 'name' => [ 'type' => 'string' ] ] ], 'types/save' );
check( 'the activity log still runs on its own, beside the event', count( \Nino\Modules\Logs\Admin::recentLines( $appData, 50 ) ) === $logBefore + 1 );

// A password somebody posts is not for a listener: the event carries what was
// done, and the three keys a secret travels under come out blank. The key
// stays, so a listener still sees that one was sent
unset( $appData['./nino/callbacks']['/nino/admin/action'] );
$secretSeen = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/action', function( array &$appData, array &$event ) use ( &$secretSeen ): void {
	$secretSeen[] = $event;
} );
$resetLockout();

ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'current' => 'a wrong old secret', 'pw' => 'a brand new secret' ], 'recoverypw/save' );
check( 'recoverypw/save reaches a listener with its passwords blank', count( $secretSeen ) === 1 && ( $secretSeen[0]['action'] ?? '' ) === 'recoverypw/save'
	&& ( $secretSeen[0]['status'] ?? 0 ) === 401 && ( $secretSeen[0]['data'] ?? null ) === [ 'current' => '', 'pw' => '' ] );

ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'username' => 'nobody-at-all@example.com', 'mail' => 'nobody-at-all@example.com', 'pw' => 'a password for a user', 'currentPassword' => 'the current one', 'role' => '' ], 'users/save' );
check( '...so do the users panel\'s, for every action', count( $secretSeen ) === 2 && ( $secretSeen[1]['data']['pw'] ?? null ) === '' && ( $secretSeen[1]['data']['currentPassword'] ?? null ) === ''
	&& ( $secretSeen[1]['data']['mail'] ?? '' ) === 'nobody-at-all@example.com' && str_contains( json_encode( $secretSeen ), 'secret' ) === false && str_contains( json_encode( $secretSeen ), 'a password for a user' ) === false );

ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'type' => 'contenttype' ], 'elements/list' );
check( '...and an action that posted none gets no blank key invented', array_keys( $secretSeen[2]['data'] ?? [] ) === [ 'type' ] );

$resetLockout();

unset( $appData['./nino/callbacks']['/nino/admin/action'] );

echo "\n";


// --- Scoped permissions ---------------------------------------------------
//
// A panel permission is a door; a scoped one describes what may be done once
// inside ('/_admin/elements/contenttype/update/title'). Enforcement is opt-in
// per account, so the first thing to pin down is the promise that made it
// possible to ship at all: an account that holds no scoped permission keeps
// everything the panel permission has always meant.

echo "Scoped permissions - what a role may do inside a panel it may open\n";

ninoUser( $appData, 'coarse@example.com', [ \Nino\Modules\Elements\Admin::MANAGE_PERM ] );

check( 'an account with only the panel permission is not described in detail', \Nino\Admin\Admin::isScoped( $appData, \Nino\Modules\Elements\Admin::SCOPE ) === false );
check( '...so it may still add', \Nino\Modules\Elements\Admin::mayInsert( $appData, 'contenttype' ) === true );
check( '...change every field', \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'contenttype', 'views' ) === true );
check( '...and delete', \Nino\Modules\Elements\Admin::mayDelete( $appData, 'contenttype' ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'scoped', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'Titel', 'views' => 1 ],
] );
check( '...which the endpoint agrees with', $status === 200 );

// The same panel, described field by field: one field of one type, and
// nothing else. '/_admin/elements/*' is deliberately not among them - that is
// the blanket, and a role holding it is not a described one
ninoUser( $appData, 'scoped@example.com', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/contenttype/update/title',
] );

check( 'one scoped permission is what switches the panel into detail', \Nino\Admin\Admin::isScoped( $appData, \Nino\Modules\Elements\Admin::SCOPE ) === true );
check( 'the named field may be changed', \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'contenttype', 'title' ) === true );
check( '...and its neighbour may not', \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'contenttype', 'views' ) === false );
check( '...nor may anything be added', \Nino\Modules\Elements\Admin::mayInsert( $appData, 'contenttype' ) === false );
check( '...or deleted', \Nino\Modules\Elements\Admin::mayDelete( $appData, 'contenttype' ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'scoped', 'locale' => 'de_DE', 'isNew' => false,
	'fields' => [ 'title' => 'Neuer Titel' ],
] );
check( 'a save of the one allowed field goes through', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'scoped', 'locale' => 'de_DE', 'isNew' => false,
	'fields' => [ 'title' => 'Auch neu', 'views' => 99 ],
] );
check( 'a save carrying a field it may not write is refused', $status === 403 );
// Refused rather than filtered: a 200 that silently dropped 'views' would show
// the old number back and read as the save having failed on its own
check( '...naming the field, so the refusal is not a guess', str_contains( (string) ( $body['error'] ?? '' ), 'views' ) );
check( '...and the allowed field in the same request is not written either', ( \Nino\Elements::getElement( $appData, '/contenttype/scoped', 'de_DE', [] )['title'] ?? null ) === 'Neuer Titel' );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'second', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'Zweiter' ],
] );
check( 'adding an element is refused', $status === 403 );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'scoped' ] );
check( 'deleting one is refused', $status === 403 );
check( '...and the element is still there', \Nino\Elements::getElement( $appData, '/contenttype/scoped', 'de_DE' ) !== false );

// The one write to an element that does not go through apiSave(): an image
// upload changes a field's value over a route of its own, and the same
// scoped permission has to decide it (see Elements\Admin::apiUploadImage())
function callUploadScoped( array &$appData, array $data ): array {
	$img = imagecreatetruecolor( 40, 40 );
	imagefill( $img, 0, 0, imagecolorallocate( $img, 0, 0, 200 ) );
	$path = tempnam( sys_get_temp_dir(), 'nino-upload-' );
	imagejpeg( $img, $path, 90 );
	imagedestroy( $img );
	$_FILES['file'] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'test.jpg', 'size' => filesize( $path ) ];
	$response = ninoCall( $appData, [ \Nino\Modules\Elements\Admin::class, 'apiUploadImage' ], $data );
	@unlink( $path );
	unset( $_FILES['file'] );
	return $response;
}

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
\Nino\Elements::insertElementType( $appData, '/scopedimage', [
	'title' => [ 'type' => 'string', 'locale' => false ],
	'photo' => [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
] );
\Nino\Elements::insertElement( $appData, '/scopedimage/one', [ 'title' => 'Eins' ], 'de_DE' );

ninoUser( $appData, 'uploadtitle@example.com', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/scopedimage/update/title',
] );
[ $status ] = callUploadScoped( $appData, [ 'type' => 'scopedimage', 'uri' => 'one', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'an upload into a field the account may not change is refused like a save of it', $status === 403 );
check( '...and nothing was written', ( \Nino\Elements::getElement( $appData, '/scopedimage/one', '*', [] )['photo'] ?? null ) === null );

ninoUser( $appData, 'uploadphoto@example.com', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/scopedimage/update/photo',
] );
[ $status, $body ] = callUploadScoped( $appData, [ 'type' => 'scopedimage', 'uri' => 'one', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( '...and one into the field it may change goes through', $status === 200 && is_string( $body['filename'] ?? null ) === true );
\Nino\Auth::deleteUser( $appData, 'uploadtitle@example.com' );
\Nino\Auth::deleteUser( $appData, 'uploadphoto@example.com' );

// Back to the account the checks below this block run as - the two uploads
// signed in as their own accounts, which no longer exist
\Nino\Auth::loginUser( $appData, 'scoped@example.com', 'correct horse battery staple' );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
$scopedType = $body['types'][array_search( 'contenttype', array_column( $body['types'], 'type' ), true )] ?? [];
// The form draws itself from this, so a control the save would refuse is
// never offered in the first place
check( 'apiTypes tells the form what this account may do', ( $scopedType['rights']['insert'] ?? null ) === false && ( $scopedType['rights']['delete'] ?? null ) === false );
check( '...field by field', ( $scopedType['rights']['update']['title'] ?? null ) === true && ( $scopedType['rights']['update']['views'] ?? null ) === false );

// A whole type at once, and the wildcard doing the work rather than a rule of
// this module's own - '/_admin/elements/contenttype/*' is an ordinary
// \Nino\Auth::checkPermission() ancestor match
ninoUser( $appData, 'typewide@example.com', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/contenttype/*',
] );

check( 'a wildcard over one type allows every action on it', \Nino\Modules\Elements\Admin::mayInsert( $appData, 'contenttype' ) === true && \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'contenttype', 'views' ) === true && \Nino\Modules\Elements\Admin::mayDelete( $appData, 'contenttype' ) === true );
check( '...and nothing on another type', \Nino\Modules\Elements\Admin::mayInsert( $appData, 'testtype' ) === false && \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'testtype', 'name' ) === false );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
check( 'full access is not described in detail either, and keeps everything', \Nino\Admin\Admin::isScoped( $appData, \Nino\Modules\Elements\Admin::SCOPE ) === false && \Nino\Modules\Elements\Admin::mayDelete( $appData, 'contenttype' ) === true );

callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'scoped' ] );

// The same idea on the Text panel, where the unit is a key rather than a
// field: the permission is '/_admin/text/update' with the key appended, so a
// group is a wildcard and a single key is the string itself
// - written into the file rather than created, as keys of a project from before
// the grammar are: the Text Keys tab no longer creates a key that is no
// /<namespace>/<category>/<part>/<name>, and what is there stays editable
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/scoped/one]]'] = 'Eins';
	$global['[[/unscoped/one]]'] = 'Zwei';
	return $global;
} );

ninoUser( $appData, 'textscoped@example.com', [
	\Nino\Modules\Text\Admin::MANAGE_PERM,
	'/_admin/text/update/scoped/*',
] );

check( 'a group wildcard covers the keys in that group', \Nino\Modules\Text\Admin::mayUpdate( $appData, '/scoped/one' ) === true );
check( '...and no others', \Nino\Modules\Text\Admin::mayUpdate( $appData, '/unscoped/one' ) === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Admin::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/scoped/one', 	'locale' => '*', 'value' => 'Eins neu' ],
	[ 'key' => '/unscoped/one', 'locale' => '*', 'value' => 'Zwei neu' ],
] ] );
// Per key rather than per request: the rest of the category is a legitimate
// save, and the form already reports what became of each key
check( 'a batch saves the keys it may and refuses the ones it may not', $status === 200 && ( $body['results']['/scoped/one']['ok'] ?? null ) === true && ( $body['results']['/unscoped/one']['ok'] ?? null ) === false );
check( '...and the refused key keeps its value', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/unscoped/one]]'] === 'Zwei' );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Admin::class, 'apiKeys' );
$writableByKey = array_column( $body['keys'], 'writable', 'key' );
check( 'apiKeys says per key whether the form may offer it', ( $writableByKey['/scoped/one'] ?? null ) === true && ( $writableByKey['/unscoped/one'] ?? null ) === false );

// The tree the roles form picks from: what these two panels list is what
// granting it does - nothing more, nothing less
\Nino\Elements::insertElementType( $appData, '/spacetype', [
	'title' => [ 'type' => 'string', 'locale' => false ],
	'two words' => [ 'type' => 'string', 'locale' => false ],
] );
\Nino\Auth::insertUser( $appData, 'treeperm@example.com', 'correct horse battery staple', [] );

$elementScope = \Nino\Modules\Elements\Admin::scopes( $appData )[0];
$elementAreas = array_column( $elementScope['areas'], null, 'id' );
check( 'Elements::scopes() lists a type as an area, with its title for a label', isset( $elementAreas['contenttype'] ) === true && $elementAreas['contenttype']['label'] === 'Content Type' && $elementScope['scope'] === \Nino\Modules\Elements\Admin::SCOPE && $elementScope['door'] === \Nino\Modules\Elements\Admin::MANAGE_PERM );
check( '...with add, change (and each field) and delete as its actions', array_column( $elementAreas['contenttype']['actions'], 'id' ) === [ 'insert', 'update', 'delete' ]
	&& array_column( $elementAreas['contenttype']['actions'][1]['fields'], 'id' ) === [ 'title', 'views' ] );
check( '...a field whose name cannot be part of a permission is left out', array_column( $elementAreas['spacetype']['actions'][1]['fields'], 'id' ) === [ 'title' ] );

$grants = static function( string $perm ) use ( &$appData ): array {
	$appData['/nino/auth/user']['treeperm@example.com']['perms'] = [ \Nino\Modules\Elements\Admin::MANAGE_PERM, $perm ];
	$appData['./nino/auth/current'] = \Nino\Auth::getUser( $appData, 'treeperm@example.com' );
	return [
		'insert' => \Nino\Modules\Elements\Admin::mayInsert( $appData, 'contenttype' ),
		'delete' => \Nino\Modules\Elements\Admin::mayDelete( $appData, 'contenttype' ),
		'title' 	=> \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'contenttype', 'title' ),
		'views' 	=> \Nino\Modules\Elements\Admin::mayUpdate( $appData, 'contenttype', 'views' ),
		'other' 	=> \Nino\Modules\Elements\Admin::mayInsert( $appData, 'testtype' ),
	];
};

$insert = $elementAreas['contenttype']['actions'][0]['perm'];
$update = $elementAreas['contenttype']['actions'][1]['perm'];
$delete = $elementAreas['contenttype']['actions'][2]['perm'];
$title 	= $elementAreas['contenttype']['actions'][1]['fields'][0]['perm'];

check( 'every permission the Elements tree lists is shaped like one', array_filter( array_merge( [ $elementScope['door'] ], array_column( $elementAreas['contenttype']['actions'], 'perm' ), array_column( $elementAreas['contenttype']['actions'][1]['fields'], 'perm' ), [ $elementAreas['contenttype']['perm'] ] ),
	static fn( string $perm ): bool => preg_match( '#^/([A-Za-z0-9_.-]+|\*)(/([A-Za-z0-9_.-]+|\*))*$#', $perm ) !== 1 ) === [] );
check( 'granting "add" lets an account add - and nothing else', $grants( $insert ) === [ 'insert' => true, 'delete' => false, 'title' => false, 'views' => false, 'other' => false ] );
check( 'granting "delete" lets it delete - and nothing else', $grants( $delete ) === [ 'insert' => false, 'delete' => true, 'title' => false, 'views' => false, 'other' => false ] );
check( 'granting "change" with all fields lets it change every field of the type', $grants( $update ) === [ 'insert' => false, 'delete' => false, 'title' => true, 'views' => true, 'other' => false ] );
check( 'granting one field lets it change that field alone', $grants( $title ) === [ 'insert' => false, 'delete' => false, 'title' => true, 'views' => false, 'other' => false ] );
check( 'granting the whole area is everything of that type and of no other', $grants( $elementAreas['contenttype']['perm'] ) === [ 'insert' => true, 'delete' => true, 'title' => true, 'views' => true, 'other' => false ] );

// Text: groups by the first segment of a key, the keys by their path - and
// never a value
$textScope = \Nino\Modules\Text\Admin::scopes( $appData )[0];
$textAreas = array_column( $textScope['areas'], null, 'id' );
check( 'Text::scopes() lists the groups of the keys, one action each', isset( $textAreas['scoped'], $textAreas['unscoped'] ) === true && array_column( $textAreas['scoped']['actions'], 'id' ) === [ 'update' ]
	&& $textScope['scope'] === \Nino\Modules\Text\Admin::SCOPE && $textScope['door'] === \Nino\Modules\Text\Admin::MANAGE_PERM );
check( '...whose own permission is the group, and whose fields are the keys by their path', $textAreas['scoped']['actions'][0]['perm'] === '/_admin/text/update/scoped/*'
	&& array_column( $textAreas['scoped']['actions'][0]['fields'], 'label' ) === [ '/scoped/one' ] && $textAreas['scoped']['actions'][0]['fields'][0]['perm'] === '/_admin/text/update/scoped/one' );
check( '...without a single value', str_contains( json_encode( $textScope ), 'Eins' ) === false && str_contains( json_encode( $textScope ), 'Zwei' ) === false );

$appData['/nino/auth/user']['treeperm@example.com']['perms'] = [ \Nino\Modules\Text\Admin::MANAGE_PERM, $textAreas['scoped']['actions'][0]['fields'][0]['perm'] ];
$appData['./nino/auth/current'] = \Nino\Auth::getUser( $appData, 'treeperm@example.com' );
check( 'granting a key lets an account change that key and not another', \Nino\Modules\Text\Admin::mayUpdate( $appData, '/scoped/one' ) === true && \Nino\Modules\Text\Admin::mayUpdate( $appData, '/unscoped/one' ) === false );
$appData['/nino/auth/user']['treeperm@example.com']['perms'] = [ \Nino\Modules\Text\Admin::MANAGE_PERM, $textAreas['scoped']['actions'][0]['perm'] ];
$appData['./nino/auth/current'] = \Nino\Auth::getUser( $appData, 'treeperm@example.com' );
check( '...and granting the group every key of it', \Nino\Modules\Text\Admin::mayUpdate( $appData, '/scoped/one' ) === true && \Nino\Modules\Text\Admin::mayUpdate( $appData, '/unscoped/one' ) === false );

/*	The scoped permissions a panel offers for the three lists of the Roles tab.
	scopes() is optional: a panel without it - AdminSmokeProjectPanel, the
	workbench's own Users - offers none, and an answer that is not the
	shape loses what is wrong with it, never the rest. Whatever comes out is a
	permission a role may hold and a check can match	*/
class AdminSmokeScopedPanel {
	public static function actions(): array { return [ 'scoped/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'scoped', 'Scoped', 63 ]; }
	public static function perm(): string { return '/_admin/scoped/manage'; }
	public static function scopes( array &$appData ): array {
		return [
			[ 'scope' => '/_admin/scoped/', 'door' => '/_admin/scoped/manage', 'label' => 'Scoped', 'areas' => [
				[ 'id' => 'a', 'label' => 'Area A', 'perm' => '/_admin/scoped/a/*', 'actions' => [
					[ 'id' => 'read', 'label' => 'Read', 'perm' => '/_admin/scoped/a/read' ],
					[ 'id' => 'bad', 'label' => 'Bad', 'perm' => 'no slash' ],
					[ 'id' => 'outside', 'label' => 'Outside', 'perm' => '/_admin/other/x' ],
					[ 'id' => 'edit', 'label' => 'Edit', 'perm' => '/_admin/scoped/a/edit/*', 'fields' => [
						[ 'id' => 'f1', 'label' => 'F 1', 'perm' => '/_admin/scoped/a/edit/f1' ],
						[ 'id' => 'f2', 'label' => 'F 2', 'perm' => '/_admin/scoped/a/edit/has space' ],
						[ 'id' => 'f3', 'label' => 'F 3', 'perm' => '/_admin/other/f3' ],
						'junk',
						[ 'id' => 5 ],
					] ],
				] ],
				[ 'id' => 'b', 'label' => 'Area B', 'perm' => 'not below', 'actions' => [ [ 'id' => 'x', 'label' => 'X', 'perm' => '/_admin/scoped/b/x' ] ] ],
				[ 'id' => 'empty', 'label' => 'Empty', 'actions' => [] ],
				'junk',
			] ],
			[ 'scope' => 'broken', 'door' => '/_admin/scoped/manage', 'label' => 'Broken', 'areas' => [] ],
			[ 'scope' => '/_admin/nothing/', 'door' => '/_admin/nothing/manage', 'label' => 'Nothing', 'areas' => [ [ 'id' => 'e', 'label' => 'E', 'actions' => [] ] ] ],
			'junk',
		];
	}
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request, [] ); }
}

class AdminSmokeScopedModule {
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeScopedPanel', 'AdminSmokeProjectPanel' ]; }
}

$withScopes = $appData;
$withScopes['/nino/modules'] = [ 'AdminSmokeScopedModule' ];
$scopeTree = \Nino\Modules\Users\Admin::scopeOptions( $withScopes );
$scopeById = array_column( $scopeTree, null, 'scope' );

check( 'scopeOptions lists a panel that answers scopes()', isset( $scopeById['/_admin/scoped/'] ) === true );
check( '...and skips a panel that has none - what is left besides is the workbench\'s own Elements and Text, which offer something once there is a type or a key', array_diff( array_keys( $scopeById ), [ '/_admin/scoped/', '/_admin/elements/', '/_admin/text/' ] ) === [] );
$scoped = $scopeById['/_admin/scoped/'];
check( 'a scope that is not the shape, and one with nothing left to offer, are dropped', isset( $scopeById['broken'] ) === false && isset( $scopeById['/_admin/nothing/'] ) === false );
check( 'an area with nothing to do and one that is no array are dropped, the rest kept', array_column( $scoped['areas'], 'id' ) === [ 'a', 'b' ] );
check( '...an action with a permission that is no permission, or lies outside the scope, is dropped', array_column( $scoped['areas'][0]['actions'], 'id' ) === [ 'read', 'edit' ] );
check( '...so is a field whose permission cannot be matched or lies outside the scope', array_column( $scoped['areas'][0]['actions'][1]['fields'], 'id' ) === [ 'f1' ] );
check( '...an area permission outside the scope is not carried', isset( $scoped['areas'][1]['perm'] ) === false && $scoped['areas'][0]['perm'] === '/_admin/scoped/a/*' );
check( '...and the door and the label arrive as the panel said them', $scoped['door'] === '/_admin/scoped/manage' && $scoped['label'] === 'Scoped' );

$treePerms = [];
foreach( $scopeTree as $scope )
	foreach( $scope['areas'] as $area ) {
		isset( $area['perm'] ) === true && $treePerms[] = $area['perm'];
		foreach( $area['actions'] as $action ) {
			$treePerms[] = $action['perm'];
			foreach( $action['fields'] ?? [] as $field )
				$treePerms[] = $field['perm'];
		}
	}
check( 'every permission anywhere in the tree is shaped like one a role may hold', count( $treePerms ) >= 4
	&& array_filter( $treePerms, static fn( string $perm ): bool => \Nino\Modules\Users\Roles::isPermShape( $perm ) === false ) === [] );
check( 'isPermShape is what apiSave checks: a string without a leading slash, a space and an overlong one are not', \Nino\Modules\Users\Roles::isPermShape( '/_admin/a/*' ) === true
	&& \Nino\Modules\Users\Roles::isPermShape( 'no/slash' ) === false && \Nino\Modules\Users\Roles::isPermShape( '/with space' ) === false && \Nino\Modules\Users\Roles::isPermShape( '/'. str_repeat( 'a', 200 ) ) === false );

$noScopes = $appData;
$noScopes['/nino/modules'] = [ 'AdminSmokeProjectModule' ];
check( 'a project panel that offers none leaves the tree to the workbench\'s own', array_diff( array_column( \Nino\Modules\Users\Admin::scopeOptions( $noScopes ), 'scope' ), [ '/_admin/elements/', '/_admin/text/' ] ) === [] );

// roles/list carries the tree; roles/save is what it was
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiList' );
check( 'roles/list carries the scopes beside the permission options', $status === 200 && array_column( $body['scopes'], 'scope' ) === [ '/_admin/elements/', '/_admin/text/' ] && is_array( $body['permOptions'] ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'treerole', 'label' => 'Tree', 'perms' => [ \Nino\Modules\Elements\Admin::MANAGE_PERM, $update, $title ] ] );
check( 'a role built from the tree is saved as before', $status === 200 && $appData['/nino/auth/roles']['treerole']['perms'] === [ \Nino\Modules\Elements\Admin::MANAGE_PERM, $update, $title ] );
unset( $appData['/nino/auth/roles']['treerole'] );
\Nino\Auth::deleteUser( $appData, 'treeperm@example.com' );
@unlink( $sandbox. '/private/elements/spacetype.php' );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
\Nino\Auth::deleteUser( $appData, 'coarse@example.com' );
\Nino\Auth::deleteUser( $appData, 'scoped@example.com' );
\Nino\Auth::deleteUser( $appData, 'typewide@example.com' );
\Nino\Auth::deleteUser( $appData, 'textscoped@example.com' );

echo "\n";


// --- The refusals a person can cause carry a code, and the code has words -----

echo "Failure codes - the user-triggerable refusals name themselves, and every code the kernel sends is worded in both languages\n";

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'Not A Slug', 'label' => 'X', 'perms' => [] ] );
check( 'a role id that is no slug is "roles_invalid_id" for the field "id"', $status === 400 && ( $body['code'] ?? '' ) === 'roles_invalid_id' && ( $body['field'] ?? '' ) === 'id' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'tester', 'label' => '', 'perms' => [] ] );
check( '...a role without a name is "roles_name_required" with the length it may have', $status === 400 && ( $body['code'] ?? '' ) === 'roles_name_required' && is_int( $body['params'][0] ?? null ) === true && ( $body['field'] ?? '' ) === 'label' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'tester', 'label' => 'Tester', 'perms' => [ 'not a permission' ] ] );
check( '...a permission that is none is "roles_invalid_perm" naming it', $status === 400 && ( $body['code'] ?? '' ) === 'roles_invalid_perm' && $body['params'] === [ 'not a permission' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => 'nope' ] );
check( 'a text key that is no key is "keys_invalid" with the key and the field', $status === 400 && ( $body['code'] ?? '' ) === 'keys_invalid' && $body['params'] === [ 'nope' ] && ( $body['field'] ?? '' ) === 'key' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiCreate', [ 'uri' => 'Bad Uri', 'title' => 'X', 'model' => [] ] );
check( 'an element type uri that is none is "types_invalid_uri"', $status === 400 && ( $body['code'] ?? '' ) === 'types_invalid_uri' && ( $body['field'] ?? '' ) === 'uri' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [ 'uri' => '/ok', 'httpUri' => 'no spaces allowed', 'template' => 'page-home' ] );
check( 'a page with an http uri that is none is "routes_invalid_http_uri" with the value and the field', $status === 400 && ( $body['code'] ?? '' ) === 'routes_invalid_http_uri' && $body['params'] === [ 'no spaces allowed' ] && ( $body['field'] ?? '' ) === 'httpUri' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'Bad Id', 'originalKey' => '' ] );
check( 'a navigation id that is none is "navs_invalid_id" for the field "key"', $status === 400 && ( $body['code'] ?? '' ) === 'navs_invalid_id' && $body['params'] === [ 'Bad Id' ] && ( $body['field'] ?? '' ) === 'key' );

/*	Every code the kernel sends has words, in both interface languages. The
	words of the shared ones live in the shell's own text, a panel's own - the
	ones carrying its slug - in its text() directory, and the client finds
	either through the same key (/_admin/error/<code>). A code without words
	is the English sentence under a German page, which is exactly what the
	codes are there to avoid. Found in the source rather than listed: a code
	added tomorrow is checked tomorrow	*/
$codes = [];
$source = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ), FilesystemIterator::SKIP_DOTS ) );
foreach( $source as $file ) {
	$path = str_replace( '\\', '/', (string) $file );
	if( str_ends_with( $path, '.php' ) === false || str_contains( $path, '/tests/' ) === true || str_contains( $path, '/.git/' ) === true || str_contains( $path, '/_admin/install/' ) === true )
		continue;
	if( str_contains( $path, '/_admin/' ) === false && str_contains( $path, '/_nino/' ) === false )
		continue;
	$text = (string) file_get_contents( $path );
	$offset = 0;
	while( ( $at = strpos( $text, 'Http::fail(', $offset ) ) !== false ) {
		// The arguments of the call, split where the commas are not inside a string or a bracket
		$arguments = [ '' ];
		$depth = 0;
		$quote = '';
		for( $i = $at + 11, $length = strlen( $text ); $i < $length; $i++ ) {
			$char = $text[$i];
			if( $quote !== '' ) {
				$arguments[count( $arguments ) - 1] .= $char;
				if( $char === '\\' ) {
					$arguments[count( $arguments ) - 1] .= $text[++$i];
				} elseif( $char === $quote ) {
					$quote = '';
				}
				continue;
			}
			if( $char === "'" || $char === '"' ) {
				$quote = $char;
			} elseif( $char === '(' || $char === '[' ) {
				$depth++;
			} elseif( $char === ')' || $char === ']' ) {
				if( $depth === 0 )
					break;
				$depth--;
			} elseif( $char === ',' && $depth === 0 ) {
				$arguments[] = '';
				continue;
			}
			$arguments[count( $arguments ) - 1] .= $char;
		}
		$offset = $at + 11;
		// The fourth argument is the code: a literal, or a choice between two
		$fourth = trim( $arguments[3] ?? '' );
		if( preg_match( "/^'([a-z][a-z0-9_]*)'\$/", $fourth, $found ) === 1 || preg_match( "/\\?\\s*'([a-z][a-z0-9_]*)'\\s*:\\s*'([a-z][a-z0-9_]*)'\$/", $fourth, $found ) === 1 )
			foreach( array_slice( $found, 1 ) as $code )
				$codes[$code] = true;
	}
}
// The ones Admin builds from the type of a field, from a failed upload and from
// the images the kernel refuses - named by variable at the call
foreach( [ 'int_range', 'bool', 'lines', 'lines_ip', 'invalid_value', 'upload_too_large', 'upload_partial', 'upload_missing', 'upload_server', 'image_too_large', 'image_type', 'image_too_many_pixels', 'image_unreadable', 'post_too_large', 'csrf', 'session', 'already_top', 'already_bottom' ] as $code )
	$codes[$code] = true;

$wordFiles = [];
foreach( [ 'en_US', 'de_DE' ] as $locale ) {
	$words = (array) require __DIR__. '/../_admin/text/'. $locale. '.php';
	foreach( array_merge( glob( __DIR__. '/../_admin/Nino/Modules/*/text/'. $locale. '.php' ) ?: [], glob( __DIR__. '/../_nino/Nino/Modules/*/text/'. $locale. '.php' ) ?: [] ) as $moduleText )
		$words += (array) require $moduleText;
	$wordFiles[$locale] = $words;
}
$unworded = [];
foreach( array_keys( $codes ) as $code )
	foreach( [ 'en_US', 'de_DE' ] as $locale )
		if( trim( (string) ( $wordFiles[$locale]['[[/_admin/error/'. $code. ']]'] ?? '' ) ) === '' )
			$unworded[] = $code. ' ('. $locale. ')';
check( 'every code the kernel sends is worded in English and in German'. ( $unworded === [] ? '' : ' - missing: '. implode( ', ', $unworded ) ), count( $codes ) > 40 && $unworded === [] );
$orphans = array_filter( array_keys( $wordFiles['en_US'] ), fn( string $key ): bool => str_starts_with( $key, '[[/_admin/error/' ) === true && isset( $codes[ substr( $key, 16, -2 ) ] ) === false );
check( '...and no word is left for a code nobody sends any more'. ( $orphans === [] ? '' : ' - '. implode( ', ', $orphans ) ), $orphans === [] );
check( 'the two languages word the same codes', array_keys( array_filter( $wordFiles['en_US'], fn( $v, $k ) => str_starts_with( $k, '[[/_admin/error/' ), ARRAY_FILTER_USE_BOTH ) ) === array_keys( array_filter( $wordFiles['de_DE'], fn( $v, $k ) => str_starts_with( $k, '[[/_admin/error/' ), ARRAY_FILTER_USE_BOTH ) ) || count( array_diff_key( array_filter( $wordFiles['en_US'], fn( $v, $k ) => str_starts_with( $k, '[[/_admin/error/' ), ARRAY_FILTER_USE_BOTH ), $wordFiles['de_DE'] ) ) === 0 );
$placeholders = [];
foreach( array_keys( $codes ) as $code ) {
	$en = (string) $wordFiles['en_US']['[[/_admin/error/'. $code. ']]'];
	$de = (string) $wordFiles['de_DE']['[[/_admin/error/'. $code. ']]'];
	if( substr_count( $en, '%s' ) !== substr_count( $de, '%s' ) )
		$placeholders[] = $code;
}
check( 'both languages take the same number of params'. ( $placeholders === [] ? '' : ' - differ: '. implode( ', ', $placeholders ) ), $placeholders === []
	&& substr_count( (string) $wordFiles['en_US']['[[/_admin/error/int_range]]'], '%s' ) === 2
	&& str_contains( (string) $wordFiles['en_US']['[[/_admin/error/upload_too_large]]'], '%s' ) === true && str_contains( (string) $wordFiles['en_US']['[[/_admin/error/image_too_large]]'], '%s' ) === true );
check( 'no word carries a live shortcode - the page substitutes a value before the shortcodes run', array_filter( $wordFiles['en_US'] + $wordFiles['de_DE'], fn( $v, $k ) => str_starts_with( $k, '[[/_admin/error/' ) && preg_match( '/[\\[\\]]/', (string) $v ) === 1, ARRAY_FILTER_USE_BOTH ) === [] );

echo "\n";

ninoDone( $appData );
