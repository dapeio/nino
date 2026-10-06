<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-smoke.php		Dependency-free smoke test for the workbench's shell and content panels
 *											(_admin/Admin.php) - the Text editor's blacklist
 *											filtering and html sanitizer in particular, since a
 *											mistake there could leak unsafe markup into the live
 *											site (values are inserted raw, unescaped, via [[key]]
 *											fills). Runs against an isolated sandbox directory,
 *											never touches the real project data.
 *
 *	Usage: php tests/admin-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';

$failures = 0;
$checks		= 0;

/**
 *	Assert a condition and print the result
 *
 *	@param		string		$label				Description of the check
 *	@param		bool			$condition		Result to assert
 *
 *	@return		void
 */
function check( string $label, bool $condition ): void {
	global $failures, $checks;
	$checks++;
	if( $condition === true ) {
		echo "  ok  - $label\n";
		return;
	}
	$failures++;
	echo "FAIL  - $label\n";
}

set_error_handler( function() { return true; } );

$sandbox = sys_get_temp_dir(). '/nino-admin-smoke-'. uniqid();
mkdir( $sandbox, 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );

// A key already longer than MAX_MAXLENGTH - MAXLENGTH_BUFFER, to prove the computed
// maxlength is capped at MAX_MAXLENGTH rather than growing past it
$longValue = str_repeat( 'x', 1900 );

file_put_contents( $sandbox. '/private/text/global.php', '<?php return [ \'[[/project/company/general/name]]\' => \'Acme\', \'[[/project/website/html/lang]]\' => \'de\' ];' );
file_put_contents( $sandbox. '/private/text/de_DE.php', '<?php return [ \'[[/home/h2]]\' => \'<span>Hallo</span> Welt.\', \'[[/home/plain]]\' => \'Ein Satz.\', \'[[/home/long]]\' => \''. $longValue. '\' ];' );
file_put_contents( $sandbox. '/private/text/en_US.php', '<?php return [ \'[[/home/h2]]\' => \'<span>Hi</span> World.\', \'[[/home/plain]]\' => \'A sentence.\' ];' );
file_put_contents( $sandbox. '/private/text/blacklist.php', '<?php return [ \'/project/website/html/lang\' ];' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']			= $sandbox;
// Mirrors \Nino\init()'s fixed private/public split.
$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] 	= $sandbox. '/public';
$appData['/nino/dir']				= '';
$appData['/nino/locales/native']				= 'de_DE';
$appData['/nino/locales/available']			= [ 'de_DE', 'en_US' ];
$appData['/nino/auth/maxtries']					= 3;
$appData['/nino/auth/cooldown']					= 3600;
$appData['/nino/auth/user']							= [];
// A set-up project: the shell serves the workbench, not the wizard
$appData['/nino/install/completed']			= true;
// The optional kernel module whose /_admin panel the sections below
// exercise - a panel exists in the editor exactly while its module is
// active (see Admin::panels()), so it has to be switched on here the way a
// project's config.php would
$appData['/nino/modules'][]							= '\\Nino\\Modules\\Form';
// The two roles the wizard writes (see Roles::defaults()), read off this
// registry: the Editor role holds the module's content permission too
$appData['/nino/auth/roles']						= \Nino\Modules\Users\Roles::defaults( $appData );

// '/*' - the main test account throughout this file exercises every module,
// same shape as config.php's real seeded admin (full access)
\Nino\Auth::insertUser( $appData, 'admin@example.com', 'correct horse battery staple', [ '/*' ] );
\Nino\Auth::loginUser( $appData, 'admin@example.com', 'correct horse battery staple' );

echo "Sandbox: $sandbox\n\n";


// --- Admin::init route ownership ----------------------------------------

echo "Admin::init - runtime route ownership\n";

// Regression: init() used to merge its own routes with '+=', which does NOT
// overwrite a key that already exists - so a persisted 'GET://_admin' (hand-
// written through _admin's Config module, which exposes '/nino/http/routes' as
// raw json, or a webpage entry created before the reserved-uri check existed)
// shadowed the editor entirely. Same fix Install::init() already carries.
$shadowed = $appData;
$shadowed['/nino/http/routes'] = [
	'GET://_admin' 	=> [ 'uri' => '/_admin', 'body' => 'hijacked' ],
	'POST://_admin' 	=> [ 'uri' => '/_admin', 'body' => 'hijacked' ],
	'GET://custom' 		=> [ 'uri' => '/custom', 'body' => 'hand-written route' ],
];
\Nino\Admin\Admin::init( $shadowed );
check( 'init always restores the tool-owned GET route over a stale/hand-written collision', $shadowed['/nino/http/routes']['GET://_admin']['body'] === '[template /_admin/templates/page-index]' );
check( 'init always restores the tool-owned POST route too', ( $shadowed['/nino/http/routes']['POST://_admin']['body'] ?? null ) === null );
check( 'a route the tool does not own is left untouched', $shadowed['/nino/http/routes']['GET://custom']['body'] === 'hand-written route' );

echo "\n";


// --- Panel registry - a module brings its own /_admin screen ---------------

echo "Admin::panels - a module's panel joins the shell without a shell change\n";

/**
 *	The panel contract, minimal: actions() and nav() are required, everything
 *	else is optional (see \Nino\Panels). Declared here rather than autoloaded -
 *	Modules::collect() only ever asks method_exists(), so a class that already
 *	exists is as good as one under app/
 */
class EditorSmokeDummyPanel {
	public const string PERM = '/_admin/dummy/manage';
	public static function actions(): array { return [ 'dummy/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'dummy', 'Dummy <Panel>', 62 ]; }
	public static function perm(): string { return self::PERM; }
	public static function panes(): array { return [ 'dummy-list', 'dummy-form', 'Not A Pane' ]; }
	public static function assets(): array { return [ '/app/Dummy/assets/dummy.js', '/app/Dummy/assets/dummy.css', '/../etc/passwd.js', 'dummy.js' ]; }
	public static function summary( array &$appData ): array { return [ 'value' => 7, 'label' => 'Dummies' ]; }
	public static function log( string $action, array $data ): string { return $action === 'dummy/list' ? '' : 'never'; }
	public static function apiList( array &$appData, array &$request ): void {
		if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::PERM ) === false )
			return;
		\Nino\Http::ok( $request, [ 'dummies' => 7 ] );
	}
}

/** A second panel trying to take over the core Text screen by reusing its uri */
class EditorSmokeShadowPanel {
	public static function actions(): array { return [ 'text/keys' => [ self::class, 'apiHijack' ] ]; }
	public static function nav(): array { return [ 'text', 'Shadow' ]; }
	public static function apiHijack( array &$appData, array &$request ): void { \Nino\Http::ok( $request, [ 'hijacked' => true ] ); }
}

/** The runtime module that contributes both, answering adminPanels() */
class EditorSmokeDummyModule {
	public static function adminPanels( array &$appData ): array { return [ 'EditorSmokeDummyPanel', 'EditorSmokeShadowPanel', 'NoSuchClassAtAll' ]; }
}

$withModule = $appData;
$withModule['/nino/modules'] = [ 'EditorSmokeDummyModule' ];

$registry = \Nino\Admin\Admin::panels( $withModule );
check( 'the module\'s panel is in the registry under its nav uri', isset( $registry['dummy'] ) === true && $registry['dummy']['class'] === 'EditorSmokeDummyPanel' );
$order = array_keys( $registry );
check( 'nav order follows the weight within the group - the module panel (62) sits after images (40) and before logs (90)', array_search( 'dummy', $order, true ) > array_search( 'images', $order, true ) && array_search( 'dummy', $order, true ) < array_search( 'logs', $order, true ) );
check( 'the content group leads, structure and system follow', array_search( 'logs', $order, true ) < array_search( 'routes', $order, true ) && array_search( 'routes', $order, true ) < array_search( 'users', $order, true ) && array_search( 'users', $order, true ) < array_search( 'backups', $order, true ) );
check( 'a panel\'s tabs ride along in the registry, each a panel of its own with its parent named', array_keys( $registry['elements']['tabs'] ) === [ 'types' ] && $registry['elements']['tabs']['types']['parent'] === 'elements' && $registry['elements']['tabs']['types']['perm'] === \Nino\Modules\Elements\Types::MANAGE_PERM && array_keys( $registry['users']['tabs'] ) === [ 'roles', 'lockout', 'recoverypw' ] && array_keys( $registry['language']['tabs'] ) === [ 'translations' ] );
check( 'a tab is not a rail entry, but allPanels() lists it right after its panel', isset( $registry['types'] ) === false && array_slice( array_keys( \Nino\Admin\Admin::allPanels( $withModule ) ), 1, 2 ) === [ 'elements', 'types' ] );
check( 'a panel reusing a core uri is dropped, the core panel keeps it', $registry['text']['class'] === \Nino\Modules\Text\Admin::class );
check( 'a class without actions()/nav() is dropped rather than breaking the tool', in_array( 'NoSuchClassAtAll', array_column( $registry, 'class' ), true ) === false );
check( 'only well-formed mount ids survive panes()', $registry['dummy']['panes'] === [ 'dummy-list', 'dummy-form' ] );
check( 'only project-relative .js/.css paths survive assets() - no traversal, no bare names', $registry['dummy']['assets'] === [ '/app/Dummy/assets/dummy.js', '/app/Dummy/assets/dummy.css' ] );
check( 'a core panel has no perm() and is open to every account', $registry['users']['perm'] === '' && $registry['dashboard']['perm'] === '' );

\Nino\Admin\Admin::init( $withModule );
check( 'the panel\'s script joins the editor bundle', in_array( '/app/Dummy/assets/dummy.js', $withModule['/nino/html/assets']['/_admin/.cache/script.js'], true ) === true );
check( 'the panel\'s stylesheet joins the style bundle', in_array( '/app/Dummy/assets/dummy.css', $withModule['/nino/html/assets']['/_admin/.cache/style.css'], true ) === true );
check( 'the core files still lead the script bundle', $withModule['/nino/html/assets']['/_admin/.cache/script.js'][0] === '/_nino/Nino.js' );

$navHtml = \Nino\Admin\Admin::navHtml( $withModule );
check( 'the nav carries one link per panel, the module\'s included, named for the shell script', str_contains( $navHtml, 'id="admin-nav-dummy" data-panel="dummy"' ) === true && str_contains( $navHtml, 'id="admin-nav-text" data-panel="text"' ) === true );
check( 'a literal label is escaped for html and against the fill syntax', str_contains( $navHtml, 'Dummy &lt;Panel&gt;' ) === true );
check( 'a core label goes through the fill pass', str_contains( $navHtml, '[[/_admin/nav/text]]' ) === true );
check( 'the shadow panel never reaches the nav', str_contains( $navHtml, 'Shadow' ) === false );

$panesHtml = \Nino\Admin\Admin::panesHtml( $withModule );
check( 'every pane starts hidden, names its layout, opens with the head that names the panel and carries its mount points', str_contains( $panesHtml, '<div id="admin-content-dummy" data-panel="dummy" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">Dummy &lt;Panel&gt;</h2><div class="admin-panel-actions"></div></div><div id="dummy-list"></div><div id="dummy-form"></div></div>' ) === true );
check( 'the dashboard pane has no mount point of its own - and no head: the overview is the tiles, and the one pane that answers head() false', str_contains( $panesHtml, '<div id="admin-content-dashboard" data-panel="dashboard" data-layout="page" hidden></div>' ) === true );
/*	One row across the workbench: every pane but that one opens with its
	head, and the head calls the panel what the rail calls it - the same
	label, through the same escape, so a literal one cannot reach the page
	unescaped by way of the title	*/
check( 'every other pane opens with its head, and the head names the panel the way the rail does', substr_count( $panesHtml, '<div class="admin-panel-head">' ) === count( \Nino\Admin\Admin::visiblePanels( $withModule ) ) - 1
	&& substr_count( $panesHtml, '<h2 class="admin-panel-title">' ) === substr_count( $panesHtml, '<div class="admin-panel-head">' ) && str_contains( $panesHtml, '<h2 class="admin-panel-title">[[/_admin/nav/text]]</h2>' ) === true );
check( 'a pane with tabs holds its head - the name, the strip beside it - and one tab pane per tab, its own screen first', str_contains( $panesHtml, '<div id="admin-content-elements" data-panel="elements" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">[[/_admin/nav/elements]]</h2><div class="nino-admin-tabs nino-admin-tabs--bar nino-admin-tabs--panel admin-panel-tabs" role="tablist"><button type="button" role="tab" id="admin-tabbutton-elements" class="nino-admin-tab" data-tab="elements" aria-controls="admin-tab-elements" aria-selected="false">[[/_admin/nav/elements]]</button><button type="button" role="tab" id="admin-tabbutton-types" class="nino-admin-tab" data-tab="types" aria-controls="admin-tab-types" aria-selected="false">[[/_admin/nav/types]]</button></div><div class="admin-panel-actions"></div></div><div id="admin-tab-elements" role="tabpanel" aria-labelledby="admin-tabbutton-elements" data-tab="elements" hidden><div id="elements-types"></div><div id="elements-list"></div><div id="elements-form"></div></div><div id="admin-tab-types" role="tabpanel" aria-labelledby="admin-tabbutton-types" data-tab="types" hidden><div id="types-list"></div><div id="types-form"></div></div></div>' ) === true );

$allActions = \Nino\Admin\Admin::actions( $withModule );
check( 'the module\'s action is dispatchable', ( $allActions['dummy/list'] ?? null ) === [ 'EditorSmokeDummyPanel', 'apiList' ] );
check( 'a core action can not be taken over by a module reusing its name', $allActions['text/keys'][0] === \Nino\Modules\Text\Admin::class );

// The main account holds '/*' and so the dummy perm too
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'dummy/list';
$_POST['data'] = json_encode( [] );
\Nino\Admin\Admin::handlePost( $withModule, $request );
check( 'handlePost dispatches the module\'s action', $request['/nino/http/response']['statusCode'] === 200 && ( $request['/nino/http/response']['body']['dummies'] ?? null ) === 7 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'dummy/list';
$withoutModule = $appData;
unset( $withoutModule['/nino/modules'] );
\Nino\Admin\Admin::handlePost( $withoutModule, $request );
check( 'the same action is unknown while the module is off - a switched-off module leaves no endpoint behind', $request['/nino/http/response']['statusCode'] === 404 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'dashboard/summary';
\Nino\Admin\Admin::handlePost( $withModule, $request );
$dummyTile = array_values( array_filter( $request['/nino/http/response']['body']['tiles'] ?? [], fn( array $tile ): bool => $tile['panel'] === 'dummy' ) )[0] ?? null;
check( 'the panel\'s summary() becomes a dashboard tile', $dummyTile === [ 'panel' => 'dummy', 'value' => '7', 'label' => 'Dummies' ] );

$permOptions = \Nino\Modules\Users\Admin::permOptions( $withModule );
check( 'the panel\'s perm() is assignable to a role, under its nav label and in its group', in_array( [ 'perm' => EditorSmokeDummyPanel::PERM, 'label' => 'Dummy <Panel>', 'group' => 'content', 'offered' => true ], $permOptions, true ) === true );
check( 'a tab\'s perm() is assignable on its own, under its nav fill', in_array( [ 'perm' => \Nino\Modules\Elements\Types::MANAGE_PERM, 'label' => '/_admin/nav/types', 'group' => 'structure', 'offered' => true ], $permOptions, true ) === true );
check( 'the users manage perm stays assignable too, once - the Roles tab shares it', count( array_filter( $permOptions, fn( array $option ): bool => $option['perm'] === \Nino\Modules\Users\Admin::MANAGE_PERM ) ) === 1 );
check( 'the Recovery password tab is assignable on its own, once, in the system group', array_values( array_filter( $permOptions, fn( array $option ): bool => $option['perm'] === \Nino\Modules\Users\RecoveryPassword::MANAGE_PERM ) ) === [ [ 'perm' => '/_admin/recoverypw/manage', 'label' => '/_admin/nav/recoverypw', 'group' => 'system', 'offered' => true ] ] );

/*	The scoped permissions a panel offers for the three lists of the Roles tab.
	scopes() is optional: a panel without it - EditorSmokeDummyPanel, the
	workbench's own Users - offers none, and an answer that is not the shape
	loses what is wrong with it, never the rest. Whatever comes out is a
	permission a role may hold and a check can match	*/
class EditorSmokeScopedPanel {
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

class EditorSmokeScopedModule {
	public static function adminPanels( array &$appData ): array { return [ 'EditorSmokeScopedPanel', 'EditorSmokeDummyPanel' ]; }
}

$withScopes = $appData;
$withScopes['/nino/modules'] = [ 'EditorSmokeScopedModule' ];
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
$noScopes['/nino/modules'] = [ 'EditorSmokeDummyModule' ];
check( 'a project panel that offers none leaves the tree to the workbench\'s own', array_diff( array_column( \Nino\Modules\Users\Admin::scopeOptions( $noScopes ), 'scope' ), [ '/_admin/elements/', '/_admin/text/' ] ) === [] );

/*	Nothing says a request carries strings. Every one of these used to be
	a 500: 'action[]=x' is an "Illegal offset type in isset" in the
	dispatcher, 'data[]=x' a TypeError in json_decode(), and '?locale[]=x'
	an "Array to string conversion" - a level the runtime treats as fatal
	(see \Nino\Runtime::NON_FATAL_LEVELS). Two of them are reachable
	without signing in, and postData() is what recovery.php reads its
	password through.

	This file's own handler swallows every warning (see the top), so the
	one these three would raise is recorded here instead - only that one:
	the dummy panels of this suite raise plenty of their own on the way	*/
$arrayWarnings = [];
set_error_handler( function( int $level, string $message ) use ( &$arrayWarnings ): bool {
	$arrayWarnings[] = $message;
	return true;
} );

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

check( 'an array-shaped ?locale is ignored rather than cast', $arrayLocaleGet['/nino/http/response']['statusCode'] === 200 );
check( 'none of the three raised an "Array to string conversion" on the way',
	array_filter( $arrayWarnings, fn( string $message ): bool => str_contains( $message, 'Array to string conversion' ) ) === [] );

restore_error_handler();

$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $withModule, $getRequest );
check( 'the module panel is visible to an account holding its perm', isset( \Nino\Admin\Admin::visiblePanels( $withModule )['dummy'] ) === true );
check( 'handleGet fills the nav and the panes for the template', str_contains( \Nino\Html::renderTextfill( $withModule, '/_admin/nav' ), 'data-panel="dummy"' ) === true && str_contains( \Nino\Html::renderTextfill( $withModule, '/_admin/panes' ), 'id="dummy-list"' ) === true );

echo "\n";


// --- Text::apiKeys ------------------------------------------------------

echo "Text::apiKeys\n";

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Text\Admin::apiKeys( $appData, $request );
$keys = $request['/nino/http/response']['body']['keys'] ?? [];

$byKey = [];
foreach( $keys as $entry )
	$byKey[$entry['key']] = $entry;

check( 'blacklisted key is excluded', isset( $byKey['/project/website/html/lang'] ) === false );
check( 'global key is included and flagged global', ( $byKey['/project/company/general/name']['global'] ?? null ) === true );
check( 'locale key is included and flagged non-global', ( $byKey['/home/h2']['global'] ?? null ) === false );
check( 'a key with existing markup is auto-flagged html', ( $byKey['/home/h2']['html'] ?? null ) === true );
check( 'a key without markup is not flagged html', ( $byKey['/home/plain']['html'] ?? null ) === false );
check( 'maxlength is at least the min floor', ( $byKey['/home/plain']['maxlength'] ?? 0 ) >= 150 );
check( 'maxlength is capped at 2000 for a key already longer than that, not grown past it', ( $byKey['/home/long']['maxlength'] ?? 0 ) === 2000 );
check( 'Text::apiKeys exposes the session-remembered locale, defaulting to native', ( $request['/nino/http/response']['body']['selectedLocale'] ?? null ) === 'de_DE' );

echo "\n";


// --- Text::apiKeys - what the form needs to arrange the keys -------------------

echo "Text::apiKeys - the order a template reads the keys in, the pages, the names of the templates\n";

$templatesDir = $sandbox. '/private/templates';
mkdir( $templatesDir, 0777, true );
file_put_contents( $templatesDir. '/page-home.tpl', "<!-- nino:template-name Start -->\n<h1>[[/template/page-home/welcome/title]]</h1>\n<p>[[/template/page-home/intro/text]]</p>\n<i>[[/project/company/general/name]]</i> [[/template/page-home/[[dynamic]]/x]] [[/template/common/label/name]] [[/template/common/label/email]]" );
file_put_contents( $templatesDir. '/page-legal.de_DE.tpl', '<p>[[/project/company/general/name]]</p>' );
file_put_contents( $templatesDir. '/.demo-catalogue.tpl', '<p>[[/project/company/general/name]]</p>' );
file_put_contents( $templatesDir. '/page-more.tpl', '<p>[[/feature/shop/cart/title]] [[/feature/shop/cart/text]] [[/feature/shop/own/note]] [[/module/form/info/success]] [[/module/form/info/error]]</p>' );
// A feature that reads some of its own keys in its own template, the rest in the project's
$featureDir = $sandbox. '/shop-feature';
mkdir( $featureDir. '/templates', 0777, true );
file_put_contents( $featureDir. '/templates/shop-cart.tpl', '<p>[[/feature/shop/cart/text]] [[/feature/shop/cart/title]]</p>' );
$appData['./nino/features/all'] = [ 'shop' => [ 'key' => 'shop', 'dir' => $featureDir, 'name' => [ 'en_US' => 'Shop', 'de_DE' => 'Laden' ] ] ];
file_put_contents( $templatesDir. '/page-Foo.tpl', '<p>[[/template/page-Foo/a/b]]</p>' );
file_put_contents( $templatesDir. '/frame-header.tpl', '<nav>[[/template/frame-header/navigation/label]]</nav>' );
$configFile = $sandbox. '/private/config.php';
$configBefore = is_file( $configFile ) === true ? file_get_contents( $configFile ) : null;
file_put_contents( $configFile, '<?php return [ \'/nino/http/routes\' => [
	\'GET://home\' => [ \'uri\' => \'/home\', \'body\' => \'[template /templates/page-home]\' ],
	\'GET://legal\' => [ \'uri\' => \'/legal\', \'body\' => \'[template /templates/page-legal.[[/nino/http/response/locale]]]\' ],
	\'GET://robots.txt\' => [ \'uri\' => \'/robots.txt\', \'body\' => \'[template /templates/robots]\' ],
	\'GET://.demo-catalogue\' => [ \'uri\' => \'/.demo-catalogue\', \'body\' => \'[template /templates/.demo-catalogue]\' ],
] ];' );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => $texts + [
	'[[/template/page-home/welcome/title]]' => 'Willkommen', '[[/template/page-home/intro/text]]' => 'Los geht\'s', '[[/_nino/webpage/home/name]]' => 'Start', '[[/_admin/elements/field/x/y]]' => 'Hidden by being the workbench\'s',
	'[[/template/common/label/name]]' => 'Name', '[[/template/common/label/email]]' => 'E-Mail',
	'[[/feature/shop/cart/title]]' => 'Warenkorb', '[[/feature/shop/cart/text]]' => 'Dein Warenkorb', '[[/feature/shop/own/note]]' => 'Hinweis',
	'[[/module/form/info/success]]' => 'Gesendet', '[[/module/form/info/error]]' => 'Fehler',
] );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Text\Admin::apiKeys( $appData, $request );
$body = $request['/nino/http/response']['body'];
$keysBody = array_column( $body['keys'], 'key' );

check( 'the words of the workbench are no text of the site: /_admin keys are not listed', in_array( '/_admin/elements/field/x/y', $keysBody, true ) === false && in_array( '/template/page-home/welcome/title', $keysBody, true ) === true );
check( 'order says where a template first reads a key - the title of the home page before its text, as the template has them',
	isset( $body['order']['/template/page-home/welcome/title'], $body['order']['/template/page-home/intro/text'] ) === true && $body['order']['/template/page-home/welcome/title'] < $body['order']['/template/page-home/intro/text'] );
check( '...a key no template reads literally has none', isset( $body['order']['/_nino/webpage/home/name'] ) === false );
check( '...a project key is looked up in the project\'s templates, in the order of their file names: the third file here, after frame-header.tpl and page-Foo.tpl', ( $body['order']['/project/company/general/name'] ?? 0 ) >= 20000000 && ( $body['order']['/project/company/general/name'] ?? 0 ) < 30000000 );
check( '/template/common is read by every template, so it has a position though no file is named common',
	isset( $body['order']['/template/common/label/name'], $body['order']['/template/common/label/email'] ) === true && $body['order']['/template/common/label/name'] < $body['order']['/template/common/label/email'] );
check( 'pages are the stored routes that are pages, with the template, its category and the name it gives itself',
	array_column( $body['pages'], 'uri' ) === [ '/home', '/legal', '/.demo-catalogue' ] && $body['pages'][0] === [ 'uri' => '/home', 'httpUri' => '/home', 'template' => 'page-home', 'category' => 'page-home', 'templateName' => 'Start' ] );
check( '...the legal page, which picks its file by language, has no template to name and no category', $body['pages'][1]['template'] === '' && $body['pages'][1]['category'] === null && $body['pages'][1]['templateName'] === null );
check( '...the demo catalogue, a route to a template that is no page-* and has no category, is a page too, with neither: its details are its own, not the system\'s',
	$body['pages'][2] === [ 'uri' => '/.demo-catalogue', 'httpUri' => '/.demo-catalogue', 'template' => '.demo-catalogue', 'category' => null, 'templateName' => null ] && in_array( '/robots.txt', array_column( $body['pages'], 'uri' ), true ) === false );
check( 'templates are the files directly in templates/ whose name is a category - with the name a template gives itself, null where it gives none',
	$body['templates']['page-home'] === [ 'file' => 'page-home.tpl', 'name' => 'Start' ] && $body['templates']['frame-header'] === [ 'file' => 'frame-header.tpl', 'name' => null ] && isset( $body['templates']['page-Foo'] ) === false && isset( $body['templates']['page-legal.de_DE'] ) === false );
check( 'a feature key is looked up in the templates of the feature first, then in the project\'s: what the feature reads itself comes first, in its order - the text of the cart before its title - and a key only the project\'s template reads after it',
	( $body['order']['/feature/shop/cart/text'] ?? 99999999 ) < 10000000 && $body['order']['/feature/shop/cart/text'] < $body['order']['/feature/shop/cart/title'] && ( $body['order']['/feature/shop/own/note'] ?? 0 ) >= 10000000 );
check( '...and a module key, which no template of the module reads here, in the project\'s, in the order they are read', isset( $body['order']['/module/form/info/success'], $body['order']['/module/form/info/error'] ) === true && $body['order']['/module/form/info/success'] < $body['order']['/module/form/info/error'] );
check( 'features says each installed feature\'s name, by its key, in the language of the workbench', $body['features'] === [ 'shop' => 'Laden' ] );

// What the log says of a batch: every group it touches, once, in the order the batch names them
check( 'the log names every group of a batch - a page\'s texts and its details together, a language\'s name, the project, a key somebody made up',
	\Nino\Modules\Text\Admin::log( 'text/savebatch', [ 'items' => [
		[ 'key' => '/template/page-home/welcome/title' ], [ 'key' => '/_nino/webpage/home/title' ], [ 'key' => '/template/page-home/intro/text' ], [ 'key' => '/_nino/webpage/about/team/name' ],
		[ 'key' => '/project/company/general/name' ], [ 'key' => '/_nino/locale/de_DE/name' ], [ 'key' => '/home/plain' ], [ 'key' => '' ],
	] ] ) === 'Edit Text /template/page-home, /_nino/webpage/home, /_nino/webpage/about/team, /project/company, /_nino/locale, /home, /-' );
check( '...and nothing for another action', \Nino\Modules\Text\Admin::log( 'text/keys', [] ) === '' );

foreach( [ 'page-home', 'page-legal.de_DE', 'page-Foo', 'frame-header', '.demo-catalogue', 'page-more' ] as $file )
	unlink( $templatesDir. '/'. $file. '.tpl' );
unlink( $featureDir. '/templates/shop-cart.tpl' );
rmdir( $featureDir. '/templates' );
rmdir( $featureDir );
unset( $appData['./nino/features/all'] );
rmdir( $templatesDir );
if( $configBefore === null )
	unlink( $configFile );
else
	file_put_contents( $configFile, $configBefore );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', function( array $texts ): array {
	foreach( [ '[[/template/page-home/welcome/title]]', '[[/template/page-home/intro/text]]', '[[/_nino/webpage/home/name]]', '[[/_admin/elements/field/x/y]]', '[[/template/common/label/name]]', '[[/template/common/label/email]]', '[[/feature/shop/cart/title]]', '[[/feature/shop/cart/text]]', '[[/feature/shop/own/note]]', '[[/module/form/info/success]]', '[[/module/form/info/error]]' ] as $key )
		unset( $texts[$key] );
	return $texts;
} );

echo "\n";


// --- Text::untranslatedCounts ------------------------------------------------

echo "Text::untranslatedCounts - what the Dashboard says is not translated yet\n";

/*	Counted per language: a key the native language has a text for and the
	other has none, or an empty one. Hidden keys and the language-independent
	ones are no translation work, an empty native text is nothing to translate,
	and the native language is never counted against itself	*/
check( 'one key of the sandbox lacks an English text, and that is all there is', \Nino\Modules\Text\Admin::untranslatedCounts( $appData ) === [ 'en_US' => 1 ] );

\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => $texts + [ '[[/gap/empty]]' => 'Leer', '[[/gap/blank]]' => '', '[[/gap/hidden]]' => 'Versteckt', '[[/gap/only-en]]' => '' ] );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $texts ): array => $texts + [ '[[/gap/empty]]' => '', '[[/gap/only-en]]' => 'Only English' ] );
\Nino\Text::setBlacklisted( $appData, '/gap/hidden', true );
$gapAppData = $appData;
$gapAppData['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
$counts = \Nino\Modules\Text\Admin::untranslatedCounts( $gapAppData );
check( 'an empty value counts as untranslated, as an absent one does', ( $counts['en_US'] ?? 0 ) === 2 );
check( '...the native language is never counted against itself', array_keys( $counts ) === [ 'en_US', 'fr_FR' ] );
check( 'a configured language with no text file counts every native text - and not the hidden key, the global ones or a native text that is empty', ( $counts['fr_FR'] ?? 0 ) === 4 );

\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $texts ): array => [ '[[/gap/empty]]' => 'Done' ] + $texts );
check( 'a text written into the language is no longer counted', ( \Nino\Modules\Text\Admin::untranslatedCounts( $gapAppData )['en_US'] ?? 0 ) === 1 );

// Back to what the rest of this suite starts from
\Nino\Text::setBlacklisted( $appData, '/gap/hidden', false );
foreach( [ 'de_DE' => [ '[[/gap/empty]]', '[[/gap/blank]]', '[[/gap/hidden]]', '[[/gap/only-en]]' ], 'en_US' => [ '[[/gap/empty]]', '[[/gap/only-en]]' ] ] as $gapLocale => $gapKeys )
	\Nino\Filesystem::mutate( $appData, '/text/'. $gapLocale. '.php', fn( array $texts ): array => array_diff_key( $texts, array_flip( $gapKeys ) ) );

echo "\n";


// --- Admin::sessionLocale / apiSetLocale --------------------------------------

echo "Admin::sessionLocale / apiSetLocale\n";

check( 'sessionLocale defaults to the native locale before anything is chosen', \Nino\Admin\Admin::sessionLocale( $appData ) === 'de_DE' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'locale' => 'not-a-locale' ] );
\Nino\Admin\Admin::apiSetLocale( $appData, $request );
check( 'apiSetLocale rejects a locale that is not available', $request['/nino/http/response']['statusCode'] === 400 );
check( 'sessionLocale is unaffected by the rejected attempt', \Nino\Admin\Admin::sessionLocale( $appData ) === 'de_DE' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'locale' => 'en_US' ] );
\Nino\Admin\Admin::apiSetLocale( $appData, $request );
check( 'apiSetLocale accepts an available locale', $request['/nino/http/response']['statusCode'] === 200 );
check( 'sessionLocale now returns the newly chosen locale', \Nino\Admin\Admin::sessionLocale( $appData ) === 'en_US' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Admin::apiTypes( $appData, $request );
check( 'Elements::apiTypes also exposes the (now updated) session locale', ( $request['/nino/http/response']['body']['selectedLocale'] ?? null ) === 'en_US' );

echo "\n";


// --- Text::apiSaveBatch: access control -----------------------------------

echo "Text::apiSaveBatch - access control\n";

/**
 *	Call Text::apiSaveBatch() with a single item, like the real POST /_admin dispatch does
 *
 *	@param		array 		&$appData
 *	@param		array 		$data					[ key, locale, value ]
 *
 *	@return		array										The item's result ( [ ok, value ] or [ ok, error ] )
 */
function saveText( array &$appData, array $data ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( [ 'items' => [ $data ] ] );
	\Nino\Modules\Text\Admin::apiSaveBatch( $appData, $request );
	$results = $request['/nino/http/response']['body']['results'] ?? [];
	return $results[$data['key'] ?? ''] ?? [ 'ok' => false ];
}

$result = saveText( $appData, [ 'key' => '/project/website/html/lang', 'locale' => 'de_DE', 'value' => 'fr' ] );
check( 'saving a blacklisted key is rejected', $result['ok'] === false && $result['error'] === 'unknown key' );

$result = saveText( $appData, [ 'key' => '/does/not/exist', 'locale' => 'de_DE', 'value' => 'x' ] );
check( 'saving an unknown key is rejected', $result['ok'] === false );

$result = saveText( $appData, [ 'key' => '/home/plain', 'locale' => 'not-a-locale', 'value' => 'x' ] );
check( 'saving with an invalid locale is rejected', $result['ok'] === false && $result['error'] === 'invalid locale' );

echo "\n";


// --- Text::apiSaveBatch: batching -------------------------------------------

echo "Text::apiSaveBatch - batching multiple keys in one request\n";

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'items' => [
	[ 'key' => '/home/h2', 	'locale' => 'de_DE', 'value' => 'Neu 1' ],
	[ 'key' => '/home/plain', 'locale' => 'de_DE', 'value' => 'Neu 2' ],
	[ 'key' => '/does/not/exist', 'locale' => 'de_DE', 'value' => 'x' ],
] ] );
\Nino\Modules\Text\Admin::apiSaveBatch( $appData, $request );
$results = $request['/nino/http/response']['body']['results'] ?? [];

check( 'the request itself succeeds even with one invalid item', $request['/nino/http/response']['statusCode'] === 200 );
check( 'the first valid item in the batch is saved', ( $results['/home/h2']['ok'] ?? false ) === true );
check( 'the second valid item in the same file is also saved', ( $results['/home/plain']['ok'] ?? false ) === true );
check( 'the invalid item in the batch is reported, not silently dropped', ( $results['/does/not/exist']['ok'] ?? true ) === false );

$stored = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'both batched keys landed in the same file write', ( $stored['[[/home/h2]]'] ?? '' ) === 'Neu 1' && ( $stored['[[/home/plain]]'] ?? '' ) === 'Neu 2' );

echo "\n";


// --- Text::apiSaveBatch: sanitizing ------------------------------------------

echo "Text::apiSaveBatch - sanitizing\n";

$result = saveText( $appData, [
	'key' 		=> '/home/h2',
	'locale' 	=> 'de_DE',
	'value' 	=> '<strong><em>Bold Italic</em></strong> <code>const x = 1;</code> <script>alert(1)</script> <a href="javascript:alert(2)">bad</a> <a href="/ok">good</a> <img src=x onerror=alert(3)>',
] );

check( 'save succeeds', $result['ok'] === true );
check( 'nested tags collapse to a single level and inline code survives', ( $result['value'] ?? '' ) === '<strong>Bold Italic</strong> <code>const x = 1;</code>  bad <a href="/ok">good</a> ' );
check( 'script tag never reaches the stored value', str_contains( $result['value'] ?? '', '<script' ) === false );
check( 'javascript: href never reaches the stored value', str_contains( $result['value'] ?? '', 'javascript:' ) === false );
check( 'img tag never reaches the stored value', str_contains( $result['value'] ?? '', '<img' ) === false );

$stored = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the sanitized value is actually persisted to disk', ( $stored['[[/home/h2]]'] ?? '' ) === $result['value'] );

$result = saveText( $appData, [
	'key' 		=> '/home/plain',
	'locale' 	=> 'de_DE',
	'value' 	=> 'Acme <b>Corp</b> <script>x</script>',
] );

check( 'a non-html key has all tags stripped, not sanitized-and-kept', ( $result['value'] ?? '' ) === 'Acme Corp x' );

/*	A word is a word. \Nino\Html renders fills first and shortcodes after them,
	over the finished document, so whatever a stored value carries is read
	again as markup of the page: '[template /templates/mail-owner]' typed into
	a heading put a private template - its copy, the owner's address - onto a
	public page, and the Text panel is an editor's. Naming another fill stays
	allowed; it is deliberate and the renderer resolves it	*/
$result = saveText( $appData, [
	'key'			=> '/home/plain',
	'locale'	=> 'de_DE',
	'value'		=> 'Lies [template /templates/mail-owner] und [elements /people] - siehe [[/project/company/general/name]]',
] );
check( 'a shortcode typed into a textfill is stored as an entity, not as a shortcode', ( $result['value'] ?? '' ) === 'Lies &#91;template /templates/mail-owner&#93; und &#91;elements /people&#93; - siehe [[/project/company/general/name]]' );
// The saved value, registered the way a page's fills are - the render pass
// runs shortcodes over what a fill puts in, so a stored entity has to stay one
\Nino\Html::addFills( $appData, [ '/home/plain' => (string) ( $result['value'] ?? '' ) ], '*' );
$renderedPlain = \Nino\Html::renderHtml( $appData, '[[/home/plain]]' );
check( '...so rendering the page shows the words instead of running them', str_contains( $renderedPlain, '&#91;template /templates/mail-owner&#93;' ) === true
	&& str_contains( $renderedPlain, '[template /templates/mail-owner]' ) === false );
$result = saveText( $appData, [ 'key' => '/home/plain', 'locale' => 'de_DE', 'value' => (string) ( $result['value'] ?? '' ) ] );
check( '...and saving that value again changes nothing - the entities carry no bracket', ( $result['value'] ?? '' ) === 'Lies &#91;template /templates/mail-owner&#93; und &#91;elements /people&#93; - siehe [[/project/company/general/name]]' );

$result = saveText( $appData, [
	'key'			=> '/home/h2',
	'locale'	=> 'de_DE',
	'value'		=> '<strong>Fett</strong> und [template /templates/mail-owner]',
] );
check( 'a rich field is held to the same line, after its own sanitizer ran', str_contains( (string) ( $result['value'] ?? '' ), '&#91;template' ) === true
	&& str_contains( (string) ( $result['value'] ?? '' ), '<strong>Fett</strong>' ) === true );

$result = saveText( $appData, [ 'key' => '/project/company/general/name', 'locale' => '*', 'value' => 'New Co' ] );
check( 'saving a global key succeeds', $result['ok'] === true );
$storedGlobal = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'a global key is written to global.php, not a locale file', ( $storedGlobal['[[/project/company/general/name]]'] ?? '' ) === 'New Co' );

/*	The hard limit is a byte count - what the file on disk has to stay under -
	and the cut used to be substr()'s, which lands inside a multibyte character
	as readily as between two. Measured with 19999 ascii bytes and one 'ä'
	across the boundary: the stored value ended on a lone 0xC3, ie. a text file
	that is not utf-8 any more, and every reader of it substitutes U+FFFD for
	that byte - the panel's own json, htmlspecialchars() on the page, an export
	- so the word came back broken and saving it again made the replacement
	permanent	*/
$hardLimit 	= (int) ( new ReflectionClassConstant( '\Nino\Text', 'HARD_MAXLENGTH' ) )->getValue();
$acrossTheCut	= str_repeat( 'a', $hardLimit - 1 ). 'ä'. str_repeat( 'b', 50 );

$result = saveText( $appData, [ 'key' => '/home/long', 'locale' => 'de_DE', 'value' => $acrossTheCut ] );

check( 'an over-long value is still cut down to the hard byte limit', strlen( (string) ( $result['value'] ?? '' ) ) <= $hardLimit );
check( '...at a character boundary, so the value stays utf-8', mb_check_encoding( (string) ( $result['value'] ?? '' ), 'UTF-8' ) === true
	&& str_ends_with( (string) ( $result['value'] ?? '' ), 'a' ) === true );

$stored = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( '...and so does the text file it was written into', mb_check_encoding( (string) ( $stored['[[/home/long]]'] ?? '' ), 'UTF-8' ) === true
	&& json_encode( $stored ) !== false );

// The same boundary with a four-byte character, which has three ways to be
// split rather than one
$emojiCut = saveText( $appData, [ 'key' => '/home/long', 'locale' => 'de_DE', 'value' => str_repeat( 'a', $hardLimit - 2 ). "\u{1F600}" ] );
check( 'a four-byte character across the limit is dropped whole, not halved', mb_check_encoding( (string) ( $emojiCut['value'] ?? '' ), 'UTF-8' ) === true );

// ...and nothing that fits is touched, multibyte or not
$underTheCut = saveText( $appData, [ 'key' => '/home/long', 'locale' => 'de_DE', 'value' => 'Grüße, Welt' ] );
check( 'a value under the limit is not cut at all', ( $underTheCut['value'] ?? '' ) === 'Grüße, Welt' );

echo "\n";


// --- Text formats: line breaks, paragraphs and lists, per key ----------------

echo "Text - a key's format and limit\n";

// Two keys that hold a <br> and are in no blacklist: the closing of a mail
// ships that way. containsHtml() never saw a break, so the panel showed a
// plain textarea with a literal <br> in it, and the next save stripped it
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => $texts + [
	'[[/mail/closing]]'	=> 'Vielen Dank.<br>Freundliche Grüße,',
	'[[/mail/plain]]'		=> "Eine Zeile\nund noch eine",
] );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $texts ): array => $texts + [
	'[[/mail/closing]]'	=> 'Thank you.<br>Kind regards,',
	'[[/mail/plain]]'		=> "One line\nand another",
] );

function textEntry( array &$appData, string $key ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Text\Admin::apiKeys( $appData, $request );
	foreach( $request['/nino/http/response']['body']['keys'] ?? [] as $entry )
		if( $entry['key'] === $key )
			return $entry;
	return [];
}

$closing = textEntry( $appData, '/mail/closing' );
check( 'a key whose value holds a <br> is read as line breaks, and offered the editor', ( $closing['format'] ?? '' ) === 'lines' && ( $closing['html'] ?? false ) === true && ( $closing['formatSet'] ?? true ) === false );
check( '...a plain one as plain text, a key with inline markup as inline', ( textEntry( $appData, '/mail/plain' )['format'] ?? '' ) === 'plain' && ( textEntry( $appData, '/home/h2' )['format'] ?? '' ) === 'inline' );

$result = saveText( $appData, [ 'key' => '/mail/closing', 'locale' => 'de_DE', 'value' => 'Vielen Dank.<br>Freundliche Grüße,<script>x</script>' ] );
check( 'saving a key that holds a <br> keeps it - it used to be stripped to "Dank.Freundliche"', ( $result['value'] ?? '' ) === 'Vielen Dank.<br>Freundliche Grüße,' );
$result = saveText( $appData, [ 'key' => '/mail/closing', 'locale' => 'en_US', 'value' => "Thank you.\nKind regards," ] );
check( '...and a newline typed into it becomes one', ( $result['value'] ?? '' ) === 'Thank you.<br>Kind regards,' );

// A format somebody chose
check( 'setMeta stores a format and a limit', \Nino\Text::setMeta( $appData, '/home/plain', 'blocks', 400 ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, \Nino\Text::META_PATH, [] )['/home/plain'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 400 ] );
$plain = textEntry( $appData, '/home/plain' );
check( 'the key reports the format and the limit that were set, and says they were', ( $plain['format'] ?? '' ) === 'blocks' && ( $plain['html'] ?? false ) === true && ( $plain['maxlength'] ?? 0 ) === 400 && ( $plain['formatSet'] ?? false ) === true && ( $plain['maxlengthSet'] ?? false ) === true );
check( '...and a key without an entry says its limit is derived', ( textEntry( $appData, '/home/long' )['maxlengthSet'] ?? true ) === false );

$result = saveText( $appData, [ 'key' => '/home/plain', 'locale' => 'de_DE', 'value' => "<p>Eins</p><ul><li>a</li></ul><script>x</script>\n\nZwei" ] );
check( 'saving into a blocks key keeps paragraphs and lists, and makes a paragraph of loose text', ( $result['value'] ?? '' ) === '<p>Eins</p><ul><li>a</li></ul><p>Zwei</p>' );
check( '...and a shortcode in it is made an entity like in any other', ( saveText( $appData, [ 'key' => '/home/plain', 'locale' => 'de_DE', 'value' => '<p>[template /templates/mail-owner]</p>' ] )['value'] ?? '' ) === '<p>&#91;template /templates/mail-owner&#93;</p>' );

check( 'setMeta takes a setting away with null, and drops an entry that is left with none', \Nino\Text::setMeta( $appData, '/home/plain', null, 400 ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, \Nino\Text::META_PATH, [] )['/home/plain'] ?? null ) === [ 'maxlength' => 400 ]
	&& \Nino\Text::setMeta( $appData, '/home/plain', null, null ) === true
	&& array_key_exists( '/home/plain', \Nino\Filesystem::getFileContent( $appData, \Nino\Text::META_PATH, [] ) ) === false );
check( '...answers true when there was nothing to change, false for a format or a limit it cannot keep', \Nino\Text::setMeta( $appData, '/home/plain', null, null ) === true
	&& \Nino\Text::setMeta( $appData, '/home/plain', 'wide', null ) === false
	&& \Nino\Text::setMeta( $appData, '/home/plain', null, 0 ) === false
	&& \Nino\Text::setMeta( $appData, '/home/plain', null, \Nino\Text::MAX_LIMIT + 1 ) === false );
// A limit that is set is never shorter than what the key holds: a longer text written later is not cut by the editor
\Nino\Text::setMeta( $appData, '/home/long', null, 5 );
$limited = textEntry( $appData, '/home/long' );
$longestValue = max( array_map( fn( $value ) => \Nino\Text::visibleLength( (string) $value ), array_filter( $limited['values'], 'is_string' ) ) );
check( 'a limit that was set and is below what the key already holds reports the longer one, and still says it was set', $longestValue > 5 && ( $limited['maxlength'] ?? 0 ) === $longestValue && ( $limited['maxlengthSet'] ?? false ) === true );
\Nino\Text::setMeta( $appData, '/home/long', null, null );

// A request that changes one setting leaves the other as the file has it, decided under the lock
\Nino\Text::setMeta( $appData, '/home/plain', 'lines', 300 );
check( 'updateMeta changes only the settings it is given',
	\Nino\Text::updateMeta( $appData, '/home/plain', [ 'format' => 'blocks' ] ) === true
	&& ( \Nino\Text::meta( $appData )['/home/plain'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 300 ]
	&& \Nino\Text::updateMeta( $appData, '/home/plain', [ 'maxlength' => 500 ] ) === true
	&& ( \Nino\Text::meta( $appData )['/home/plain'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 500 ] );
check( '...null takes one away, and a value it cannot keep is refused without a write',
	\Nino\Text::updateMeta( $appData, '/home/plain', [ 'format' => null ] ) === true
	&& ( \Nino\Text::meta( $appData )['/home/plain'] ?? null ) === [ 'maxlength' => 500 ]
	&& \Nino\Text::updateMeta( $appData, '/home/plain', [ 'maxlength' => 0 ] ) === false
	&& ( \Nino\Text::meta( $appData )['/home/plain'] ?? null ) === [ 'maxlength' => 500 ] );
check( 'moveMeta gives the settings to the new name and leaves nothing under the old one, and a key without any is nothing to move',
	\Nino\Text::moveMeta( $appData, '/home/plain', '/home/moved' ) === true
	&& \Nino\Text::meta( $appData ) === [ '/home/moved' => [ 'maxlength' => 500 ] ]
	&& \Nino\Text::moveMeta( $appData, '/home/plain', '/home/other' ) === true
	&& \Nino\Text::meta( $appData ) === [ '/home/moved' => [ 'maxlength' => 500 ] ] );
\Nino\Text::setMeta( $appData, '/home/moved', null, null );
\Nino\Filesystem::putFileContent( $appData, \Nino\Text::META_PATH, [ '/a/b' => [ 'format' => 'sideways', 'maxlength' => '12' ], '/c/d' => 'x', '/e/f' => [ 'format' => 'lines', 'maxlength' => 0 ] ] );
check( 'meta() leaves out what is not a format or a limit, whatever the file says', \Nino\Text::meta( $appData ) === [ '/e/f' => [ 'format' => 'lines' ] ] );
\Nino\Filesystem::putFileContent( $appData, \Nino\Text::META_PATH, [] );

check( 'sanitizeValue still takes a bool: true is inline, false is plain', \Nino\Text::sanitizeValue( 'a<br>b <em>c</em>', true ) === 'a b <em>c</em>' && \Nino\Text::sanitizeValue( 'a<em>b</em>', false ) === 'ab' );
check( '...and a plain value keeps the lines the tags were: a break and the end of a block are a newline', \Nino\Text::sanitizeValue( 'Amtsgericht<br>Musterstadt', 'plain' ) === "Amtsgericht\nMusterstadt" && \Nino\Text::sanitizeValue( '<p>a</p><p>b</p>', 'plain' ) === "a\nb" );
check( '...and a name that is no format is read as plain, the narrowest', \Nino\Text::sanitizeValue( '<strong>a</strong>', 'sideways' ) === 'a' );
check( 'visibleLength counts what is seen: no tag, one character for an entity', \Nino\Text::visibleLength( '<p>a&amp;b</p><ul><li>c</li></ul>' ) === 4 );

echo "\n";


// --- Elements::apiSave: html field sanitizing -------------------------------

echo "Elements::apiSave - html field sanitizing\n";

\Nino\Elements::insertElementType( $appData, '/demo', [
	'body' 	=> [ 'type' => 'string', 'locale' => true, 'html' => true ],
	'plain' => [ 'type' => 'string', 'locale' => true ],
] );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'type' 		=> 'demo',
	'uri' 		=> 'item1',
	'locale' 	=> 'de_DE',
	'isNew' 	=> true,
	'fields' 	=> [
		'body' 	=> '<strong><em>Bold Italic</em></strong> <script>alert(1)</script>',
		'plain' => 'Acme <b>Corp</b>',
	],
] );
\Nino\Modules\Elements\Admin::apiSave( $appData, $request );
$element = $request['/nino/http/response']['body']['element'] ?? [];

check( 'element save succeeds', $request['/nino/http/response']['statusCode'] === 200 );
check( 'an html-flagged field is sanitized like a Text html key', ( $element['body'] ?? '' ) === '<strong>Bold Italic</strong> ' );
check( 'a plain field is left as-is (Elements never sanitized these before either)', ( $element['plain'] ?? '' ) === 'Acme <b>Corp</b>' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'type' => 'demo', 'uri' => 'not a slug', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'body' => 'x', 'plain' => 'x' ],
] );
\Nino\Modules\Elements\Admin::apiSave( $appData, $request );
check( 'a new element uri outside the documented slug syntax is rejected', $request['/nino/http/response']['statusCode'] === 400 );

echo "\n";


// --- Elements::apiSave: blocks and breaks ---------------------------------------

echo "Elements::apiSave - a blocks field keeps paragraphs and lists, a breaks field its newlines\n";

\Nino\Elements::insertElementType( $appData, '/demoformats', [
	'blocks'	=> [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ],
	'rich'		=> [ 'type' => 'string', 'locale' => true, 'html' => true ],
	'breaks'	=> [ 'type' => 'string', 'locale' => true, 'breaks' => true ],
] );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'type' 		=> 'demoformats',
	'uri' 		=> 'item1',
	'locale' 	=> 'de_DE',
	'isNew' 	=> true,
	'fields' 	=> [
		'blocks'	=> '<p>Eins</p><ul><li>a</li><li>b</li></ul><script>x</script>',
		'rich'		=> '<p>Eins</p><strong>zwei</strong>',
		'breaks'	=> "Zeile 1\nZeile 2",
	],
] );
\Nino\Modules\Elements\Admin::apiSave( $appData, $request );
$element = $request['/nino/http/response']['body']['element'] ?? [];

check( 'a blocks field keeps p and ul', ( $element['blocks'] ?? '' ) === '<p>Eins</p><ul><li>a</li><li>b</li></ul>' );
check( '...a field with html alone still only the inline tags', ( $element['rich'] ?? '' ) === 'Eins <strong>zwei</strong>' );
check( '...and a breaks field its newline, as text', ( $element['breaks'] ?? '' ) === "Zeile 1\nZeile 2" );

echo "\n";


// --- Elements::apiList: the table view -------------------------------------

echo "Elements::apiList - the table view's columns and one translation's values\n";

// The list answers the fields a table cell can show, in model order - not an
// image, not a rich-text string - and every element's values for the
// translation asked. An element that has no data in that translation yet is
// still listed, with its translated cells empty: the table is where an editor
// finds what is left to translate, so it cannot be the place it vanishes from
\Nino\Elements::insertElementType( $appData, '/demotable', [
	'title' 	=> [ 'type' => 'string', 'locale' => true ],
	'body' 		=> [ 'type' => 'string', 'locale' => true, 'html' => true ],
	'price' 	=> [ 'type' => 'double' ],
	'photo' 	=> [ 'type' => 'image', 'width' => 10, 'height' => 10 ],
] );
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'demotable', 'uri' => 'one', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'title' => 'Eins', 'body' => '<p>x</p>', 'price' => 9.5 ] ] );
\Nino\Modules\Elements\Admin::apiSave( $appData, $request );
check( 'the table\'s element is saved in one translation', $request['/nino/http/response']['statusCode'] === 200 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'demotable', 'locale' => 'de_DE' ] );
\Nino\Modules\Elements\Admin::apiList( $appData, $request );
$listed = $request['/nino/http/response']['body'] ?? [];
check( 'apiList names the columns a cell can show, in model order', ( $listed['columns'] ?? null ) === [ 'title', 'price' ] );
check( 'apiList answers the translation asked, global fields included', ( $listed['elements'][0]['values'] ?? null ) === [ 'title' => 'Eins', 'price' => 9.5 ] );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'demotable', 'locale' => 'en_US' ] );
\Nino\Modules\Elements\Admin::apiList( $appData, $request );
$listed = $request['/nino/http/response']['body'] ?? [];
check( 'a translation the element does not have yet still lists it', count( $listed['elements'] ?? [] ) === 1 && ( $listed['elements'][0]['uri'] ?? '' ) === 'one' );
check( '...with its translated cell empty and the global one filled', ( $listed['elements'][0]['values'] ?? null ) === [ 'title' => null, 'price' => 9.5 ] );

echo "\n";


// --- Elements::apiSave: required fields ---------------------------------------

echo "Elements::apiSave - required fields\n";

// Required-field enforcement itself lives in the kernel (Elements::insertElement(),
// via _writeElementData()) - this just confirms apiSave() surfaces its rejection
\Nino\Elements::insertElementType( $appData, '/demoreq', [
	'title' 	=> [ 'type' => 'string', 'required' => true ],
	'tags' 		=> [ 'type' => 'array', 'required' => true ],
	'count' 	=> [ 'type' => 'integer', 'required' => true ],
	'active' 	=> [ 'type' => 'boolean', 'required' => true ],
] );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'type' 		=> 'demoreq',
	'uri' 		=> 'item1',
	'locale' 	=> 'de_DE',
	'isNew' 	=> true,
	'fields' 	=> [ 'title' => '', 'tags' => [ 'a' ], 'count' => 0, 'active' => false ],
] );
\Nino\Modules\Elements\Admin::apiSave( $appData, $request );
check( 'apiSave rejects a missing required string field', $request['/nino/http/response']['statusCode'] === 400 );
check( 'the error names the missing field', str_contains( $request['/nino/http/response']['body']['error'] ?? '', 'title' ) === true );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'type' 		=> 'demoreq',
	'uri' 		=> 'item1',
	'locale' 	=> 'de_DE',
	'isNew' 	=> true,
	'fields' 	=> [ 'title' => 'Hallo', 'tags' => [ 'a' ], 'count' => 0, 'active' => false ],
] );
\Nino\Modules\Elements\Admin::apiSave( $appData, $request );
check( 'apiSave succeeds once every required field actually has a value', $request['/nino/http/response']['statusCode'] === 200 );

echo "\n";


// --- Elements::apiUploadImage ------------------------------------------------

echo "Elements::apiUploadImage\n";

/*	Both upload sections below run with '/nino/images/webp' off. The panel
	logic under test is the part that deletes the previous file when a re-upload
	lands under a different name, and that only ever happens on the png/jpeg
	fallback - with webp on, both branches write .webp and a replacement
	overwrites in place. The one webp case each section needs is checked
	explicitly at its end	*/
$appData['/nino/images/webp'] = false;

/**
 *	Write a small solid-color image to a real temp file and populate $_FILES['file']
 *	as if it had actually been uploaded - apiUploadImage() reads it via
 *	file_get_contents(), not move_uploaded_file(), specifically so it stays testable
 *	outside a real http upload context
 *
 *	@param		bool	$alpha		Encode as png (with transparency) instead of jpeg
 *
 *	@return		string	The temp file path
 */
function fakeUploadedFile( bool $alpha = false, int $variant = 0 ): string {
	$img = imagecreatetruecolor( 300, 150 );
	if( $alpha === true ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, $variant > 0 ? 200 : 0, $variant > 0 ? 0 : 200, 0, 64 ) );
	} else {
		imagefill( $img, 0, 0, imagecolorallocate( $img, $variant > 0 ? 200 : 0, 0, $variant > 0 ? 0 : 200 ) );
	}
	$path = tempnam( sys_get_temp_dir(), 'nino-upload-' );
	$alpha === true ? imagepng( $img, $path ) : imagejpeg( $img, $path, 90 );
	imagedestroy( $img );
	return $path;
}

/**
 *	Call Elements::apiUploadImage with a fake uploaded file
 *
 *	@param		array 		&$appData
 *	@param		array 		$data
 *	@param		bool			$alpha		Upload a png-with-alpha source instead of a jpeg
 *
 *	@return		array			[ statusCode, body ]
 */
function callUploadImage( array &$appData, array $data, bool $alpha = false, int $variant = 0 ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	$path = fakeUploadedFile( $alpha, $variant );
	$_FILES['file'] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'test.jpg', 'size' => filesize( $path ) ];
	\Nino\Modules\Elements\Admin::apiUploadImage( $appData, $request );
	@unlink( $path );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

\Nino\Elements::insertElementType( $appData, '/imagedemo', [
	'photo' => [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
	'plain' => [ 'type' => 'string', 'locale' => true ],
] );
\Nino\Elements::insertElement( $appData, '/imagedemo/item1', [ 'plain' => 'x' ], 'de_DE' );
\Nino\Elements::updateElement( $appData, '/imagedemo/item1', [ 'plain' => 'English' ], 'en_US' );

[ $status, $body ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'en_US', 'key' => 'photo' ] );
check( 'a valid upload for an image field succeeds', $status === 200 && is_string( $body['filename'] ?? null ) === true );

$firstFilename = $body['filename'];
check( 'the sole image field on this type needs no key/locale disambiguation in the path', $firstFilename === 'elements/imagedemo/item1.40x40.jpg' );

$uploadPath = \Nino\Filesystem::path( $appData, '/images/'. $firstFilename );
check( 'the processed file actually exists on disk', is_file( $uploadPath ) === true );

$stored = \Nino\Elements::getElement( $appData, '/imagedemo/item1', '*' );
check( 'the uploaded filename is committed to the element immediately (no Speichern needed)', ( $stored['photo'] ?? null ) === $firstFilename );
check( 'an immediate image update does not copy another locale\'s text into its target locale', \Nino\Elements::getElement( $appData, '/imagedemo/item1', 'en_US' )['plain'] === 'English' );
check( 'an immediate image update leaves the source locale text untouched too', \Nino\Elements::getElement( $appData, '/imagedemo/item1', 'de_DE' )['plain'] === 'x' );

[ $status2, $body2 ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'uploading a replacement succeeds', $status2 === 200 );
check( 'a same-format replacement overwrites the same deterministic path, not a new file', ( $body2['filename'] ?? null ) === $firstFilename && is_file( $uploadPath ) === true );

// When the output format itself changes (a png-with-alpha source this time), the path
// changes with it (different extension) - the old, now-orphaned .jpg must be cleaned up
[ $status3, $body3 ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ], true );
check( 'switching output format succeeds and yields a differently-named file', $status3 === 200 && ( $body3['filename'] ?? null ) === 'elements/imagedemo/item1.40x40.png' );
check( 'the old .jpg is deleted once the new .png is committed (no orphan across a format change)', is_file( $uploadPath ) === false );

/*	With webp on there is no format change left to orphan anything: the branch
	that used to pick between png and jpeg now picks between two webp encodings
	under one name, so a replacement always overwrites in place	*/
if( function_exists( 'imagewebp' ) === true && ( imagetypes() & IMG_WEBP ) !== 0 ) {
	$appData['/nino/images/webp'] = true;
	[ $statusWebp, $bodyWebp ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
	check( 'with webp on the upload is a webp', $statusWebp === 200 && ( $bodyWebp['filename'] ?? null ) === 'elements/imagedemo/item1.40x40.webp' );
	[ $statusWebp2, $bodyWebp2 ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ], true );
	check( '...and a source that would have changed the format keeps the same name, so nothing is orphaned', $statusWebp2 === 200 && ( $bodyWebp2['filename'] ?? null ) === ( $bodyWebp['filename'] ?? '' ) );
	$appData['/nino/images/webp'] = false;
}

// process() overwrites the deterministic path before updateElement() gets a
// chance to run its veto callback. A rejected same-format update must restore
// the old bytes the unchanged element record still references. Keep this on a
// dedicated type so its deliberate update veto cannot affect later cases.
\Nino\Elements::insertElementType( $appData, '/imageveto', [
	'photo' => [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
] );
\Nino\Elements::insertElement( $appData, '/imageveto/item1', [], 'de_DE' );
[ , $imageVetoInitial ] = callUploadImage( $appData, [ 'type' => 'imageveto', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ], true );
$pngFilename = $imageVetoInitial['filename'];
$pngPath = \Nino\Filesystem::path( $appData, '/images/'. $pngFilename );
$pngBeforeVeto = file_get_contents( $pngPath );
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/imageveto/update', function(): bool { return false; } );
[ $vetoStatus ] = callUploadImage( $appData, [ 'type' => 'imageveto', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ], true, 1 );
check( 'a vetoed image metadata update reports failure', $vetoStatus === 400 );
check( 'a vetoed same-format replacement restores the original processed image bytes', file_get_contents( $pngPath ) === $pngBeforeVeto );
check( 'a vetoed image update leaves the element filename unchanged', \Nino\Elements::getElement( $appData, '/imageveto/item1', '*' )['photo'] === $pngFilename );

[ $statusMissing ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'does-not-exist', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'uploading for an element that was never saved is rejected', $statusMissing === 404 );

[ $statusWrongKey ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'plain' ] );
check( 'uploading to a key that is not an image field is rejected', $statusWrongKey === 400 );

// Deleting an element with an image field must also clean up the file it points
// to - an "image" field's value is just a filename reference, so deleteElement()
// itself has no way of knowing to touch it
\Nino\Elements::insertElement( $appData, '/imagedemo/item2', [ 'plain' => 'y' ], 'de_DE' );
[ , $bodyForDelete ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item2', 'locale' => 'de_DE', 'key' => 'photo' ] );
$deletePath = \Nino\Filesystem::path( $appData, '/images/'. $bodyForDelete['filename'] );
check( 'the image exists before the element is deleted', is_file( $deletePath ) === true );

$deleteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'imagedemo', 'uri' => 'item2' ] );
\Nino\Modules\Elements\Admin::apiDelete( $appData, $deleteRequest );
check( 'deleting the element succeeds', $deleteRequest['/nino/http/response']['statusCode'] === 200 );
check( 'deleting the element also deletes its uploaded image (no orphan)', is_file( $deletePath ) === false );

// A module may veto deleteElement(). The editor must keep image files until
// the element deletion itself has really committed.
\Nino\Elements::insertElementType( $appData, '/deleteveto', [
	'photo' => [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
] );
\Nino\Elements::insertElement( $appData, '/deleteveto/item1', [], 'de_DE' );
[ , $deleteVetoUpload ] = callUploadImage( $appData, [ 'type' => 'deleteveto', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
$deleteVetoPath = \Nino\Filesystem::path( $appData, '/images/'. $deleteVetoUpload['filename'] );
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/delete/deleteveto', function(): bool { return false; } );
$deleteVetoRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'deleteveto', 'uri' => 'item1' ] );
\Nino\Modules\Elements\Admin::apiDelete( $appData, $deleteVetoRequest );
check( 'a vetoed element deletion is surfaced as a conflict', $deleteVetoRequest['/nino/http/response']['statusCode'] === 409 );
check( 'a vetoed deletion leaves the element record in place', \Nino\Elements::getElement( $appData, '/deleteveto/item1', '*' ) !== false );
check( 'a vetoed deletion also leaves its referenced image in place', is_file( $deleteVetoPath ) === true );

// Regression: apiDelete always calls deleteElement( ..., '*' ), which used to
// crash with "Cannot unset string offsets" on any type file with a top-level
// 'title' key - ie. every hand-authored type, but NOT one created via
// insertElementType() like 'imagedemo' above, which is why that never caught it
\Nino\Filesystem::putFileContent( $appData, '/elements/titleddemo.php', [
	'title' 	=> 'Titled Demo',
	'model' 	=> [ 'plain' => [ 'type' => 'string', 'locale' => true ] ],
	'*' 			=> [ '*' => [] ],
	'de_DE' 	=> [ 'item1' => [ 'plain' => 'x' ] ],
] );

$titledDeleteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'titleddemo', 'uri' => 'item1' ] );
\Nino\Modules\Elements\Admin::apiDelete( $appData, $titledDeleteRequest );
check( 'deleting an element on a type with a "title" key succeeds (no crash)', $titledDeleteRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the element is actually gone', \Nino\Elements::getElement( $appData, '/titleddemo/item1', '*' ) === false );

// An image field says, like a slot, when the upload was scaled up - and can
// have its image taken away: the field is written empty, and only a file this
// element's own upload wrote is deleted
/**
 *	Call Elements::apiRemoveImage
 *
 *	@param		array 		&$appData
 *	@param		array 		$data
 *	@param		int				$statusBefore
 *
 *	@return		array			[ statusCode, body ]
 */
function callRemoveImage( array &$appData, array $data, int $statusBefore = 200 ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => $statusBefore ] ];
	$_POST['data'] = json_encode( $data );
	\Nino\Modules\Elements\Admin::apiRemoveImage( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ?? null ];
}

\Nino\Elements::insertElementType( $appData, '/imageremove', [
	'photo' 		=> [ 'type' => 'image', 'width' => 400, 'height' => 400 ],
	'photoAlt' 	=> [ 'type' => 'string', 'locale' => true ],
	'label' 		=> [ 'type' => 'string' ],
] );
\Nino\Elements::insertElement( $appData, '/imageremove/item1', [ 'label' => 'x' ], 'de_DE' );

[ $status, $body ] = callUploadImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'an upload below the field\'s target size is saved and says so, with the size it has', $status === 200 && ( $body['belowTarget'] ?? null ) === true && ( $body['source'] ?? null ) === [ 'width' => 300, 'height' => 150 ] );
$removePath = \Nino\Filesystem::path( $appData, '/images/'. $body['filename'] );

[ $status ] = callRemoveImage( $appData, [ 'type' => 'nosuchtype', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'removing from an unknown type is a 400', $status === 400 );
[ $status ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'label' ] );
check( '...and from a field that is not an image field', $status === 400 );
[ $status ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'nope', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( '...and for an element that does not exist a 404', $status === 404 );
[ $status ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ], 403 );
check( 'a request whose status is already 403 (csrf) removes nothing', $status === 403 && is_file( $removePath ) === true );

[ $status, $removeBody ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'removing writes the field empty, deletes the element\'s own file and answers no filename', $status === 200 && array_key_exists( 'filename', $removeBody ) === true && $removeBody['filename'] === null
	&& ( \Nino\Elements::getElement( $appData, '/imageremove/item1', '*' )['photo'] ?? 'x' ) === '' && is_file( $removePath ) === false
	&& \Nino\Elements::getElement( $appData, '/imageremove/item1', 'de_DE' )['label'] === 'x' );
[ $status, $removeBody ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'a field without an image answers 200 with no filename, so the call can be repeated', $status === 200 && $removeBody['filename'] === null );

// A name written by hand is not this element's file
\Nino\Filesystem::putFileContent( $appData, '/images/shared-logo.png', 'a picture other pages use' );
\Nino\Elements::updateElement( $appData, '/imageremove/item1', [ 'photo' => 'shared-logo.png' ], 'de_DE' );
[ $status ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'a hand-set filename is cleared from the field, but its file stays', $status === 200 && \Nino\Filesystem::fileExists( $appData, '/images/shared-logo.png' ) === true
	&& ( \Nino\Elements::getElement( $appData, '/imageremove/item1', '*' )['photo'] ?? 'x' ) === '' );

// ...and neither is a name that only looks like it: this type has one image field and
// no per-language one, so its own uploads are never "<uri>-<something>"
\Nino\Filesystem::putFileContent( $appData, '/images/elements/imageremove/item1-lead.400x400.jpg', 'the picture of another element' );
\Nino\Elements::updateElement( $appData, '/imageremove/item1', [ 'photo' => 'elements/imageremove/item1-lead.400x400.jpg' ], 'de_DE' );
[ $status ] = callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'a hand-set name that starts like the element\'s own, with a suffix its uploads never carry here, is cleared but its file stays', $status === 200 && \Nino\Filesystem::fileExists( $appData, '/images/elements/imageremove/item1-lead.400x400.jpg' ) === true
	&& ( \Nino\Elements::getElement( $appData, '/imageremove/item1', '*' )['photo'] ?? 'x' ) === '' );

// An element whose uri starts with another's does not lose that one's picture
\Nino\Elements::insertElement( $appData, '/imageremove/item10', [], 'de_DE' );
[ , $otherBody ] = callUploadImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item10', 'locale' => 'de_DE', 'key' => 'photo' ] );
\Nino\Elements::updateElement( $appData, '/imageremove/item1', [ 'photo' => $otherBody['filename'] ], 'de_DE' );
callRemoveImage( $appData, [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'a file named like another element\'s upload is not taken for this element\'s own', is_file( \Nino\Filesystem::path( $appData, '/images/'. $otherBody['filename'] ) ) === true );

echo "\n";


// --- Admin\Images::apiList / apiUpload --------------------------------------

echo "Admin\\Images::apiList / apiUpload\n";

$appData['/nino/html/images'] = [
	'/hero' => [ 'label' => 'Hero-Banner', 'width' => 60, 'height' => 40, 'filename' => null ],
];
// The slots live in config.php: an upload writes its record there, not into this request's copy
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

/**
 *	Call Admin\Images::apiUpload with a fake uploaded file
 *
 *	@param		array 		&$appData
 *	@param		string		$uri
 *
 *	@return		array			[ statusCode, body ]
 */
function callUploadSlotImage( array &$appData, string $uri ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( [ 'uri' => $uri ] );
	$path = fakeUploadedFile();
	$_FILES['file'] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'test.jpg', 'size' => filesize( $path ) ];
	\Nino\Modules\Images\Admin::apiUpload( $appData, $request );
	@unlink( $path );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Images\Admin::apiList( $appData, $listRequest );
$slots = $listRequest['/nino/http/response']['body']['slots'] ?? [];
check( 'apiList returns every developer-fixed slot', count( $slots ) === 1 && $slots[0]['uri'] === '/hero' );
check( 'apiList reports no url for a slot with no image yet', $slots[0]['url'] === null );

[ $slotStatus, $slotBody ] = callUploadSlotImage( $appData, '/hero' );
check( 'a valid upload for a known slot succeeds', $slotStatus === 200 && is_string( $slotBody['filename'] ?? null ) === true );
check( 'the slot uri\'s leading slash is stripped for the deterministic path', $slotBody['filename'] === 'hero.60x40.jpg' );

$slotUploadPath = \Nino\Filesystem::path( $appData, '/images/'. $slotBody['filename'] );
check( 'the processed file exists on disk', is_file( $slotUploadPath ) === true );
check( 'the filename is committed to the slot immediately', ( \Nino\Images::getSlot( $appData, '/hero' )['filename'] ?? null ) === $slotBody['filename'] );

[ $slotStatus2, $slotBody2 ] = callUploadSlotImage( $appData, '/hero' );
check( 'uploading a replacement succeeds and overwrites the same path', $slotStatus2 === 200 && $slotBody2['filename'] === $slotBody['filename'] && is_file( $slotUploadPath ) === true );

[ $slotStatusUnknown ] = callUploadSlotImage( $appData, 'does-not-exist' );
check( 'uploading to an unknown slot is rejected', $slotStatusUnknown === 404 );

// The admin now groups slots by the uri's first path segment (eg. "/home/hero" ->
// category "home") - a slot uri is just a free-form array key, so this needs no
// kernel/admin support of its own, but is worth locking in as a real upload
$appData['/nino/html/images']['/home/hero'] = [ 'label' => 'Hero', 'width' => 60, 'height' => 40, 'filename' => null ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
[ $categoryStatus, $categoryBody ] = callUploadSlotImage( $appData, '/home/hero' );
check( 'uploading to a category-style ("/<category>/<identifier>") slot uri succeeds', $categoryStatus === 200 && $categoryBody['filename'] === 'home/hero.60x40.jpg' );
check( 'the file lands at the nested deterministic path', is_file( \Nino\Filesystem::path( $appData, '/images/'. $categoryBody['filename'] ) ) === true );

// The answer says how large the picture is as it is shown, and whether that is
// below what the slot asks for - crop mode scales such a picture up
/**
 *	A jpeg of a given size with an Exif orientation of its own
 *
 *	@param		int				$width				Stored width
 *	@param		int				$height				Stored height
 *	@param		int				$orientation	1-8, 1 writes no Exif block
 *	@param		int				$red					Red of the plain colour it is filled with, blue is the rest
 *
 *	@return		string
 */
function orientedJpeg( int $width, int $height, int $orientation, int $red = 0 ): string {
	$img = imagecreatetruecolor( $width, $height );
	imagefill( $img, 0, 0, imagecolorallocate( $img, $red, 0, 200 - $red ) );
	ob_start();
	imagejpeg( $img, null, 90 );
	$bytes = ob_get_clean();
	imagedestroy( $img );
	if( $orientation === 1 )
		return $bytes;
	$tiff = 'MM'. pack( 'n', 42 ). pack( 'N', 8 ). pack( 'n', 1 ). pack( 'n', 0x0112 ). pack( 'n', 3 ). pack( 'N', 1 ). pack( 'n', $orientation ). "\0\0". pack( 'N', 0 );
	$payload = "Exif\0\0". $tiff;
	return substr( $bytes, 0, 2 ). "\xFF\xE1". pack( 'n', strlen( $payload ) + 2 ). $payload. substr( $bytes, 2 );
}

/**
 *	Call Admin\Images::apiUpload with the given bytes
 *
 *	@param		array 		&$appData
 *	@param		string		$uri
 *	@param		string		$bytes
 *
 *	@return		array			[ statusCode, body ]
 */
function callUploadSlotBytes( array &$appData, string $uri, string $bytes ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( [ 'uri' => $uri ] );
	$path = tempnam( sys_get_temp_dir(), 'nino-upload-' );
	file_put_contents( $path, $bytes );
	$_FILES['file'] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'test.jpg', 'size' => filesize( $path ) ];
	\Nino\Modules\Images\Admin::apiUpload( $appData, $request );
	@unlink( $path );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

$appData['/nino/html/images']['/big'] 	= [ 'label' => 'Big', 'width' => 800, 'height' => 600, 'filename' => null ];
$appData['/nino/html/images']['/small'] = [ 'label' => 'Small', 'width' => 200, 'height' => 150, 'filename' => null ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

[ , $belowBody ] = callUploadSlotImage( $appData, '/big' );
check( 'a 300x150 upload into an 800x600 slot is saved and says it is below the target, with its own size', ( $belowBody['belowTarget'] ?? null ) === true
	&& ( $belowBody['source'] ?? null ) === [ 'width' => 300, 'height' => 150 ] && is_string( $belowBody['filename'] ?? null ) === true );
[ , $aboveBody ] = callUploadSlotImage( $appData, '/hero' );
check( 'the same upload into the 60x40 slot is not below it', ( $aboveBody['belowTarget'] ?? null ) === false );
[ , $upright ] = callUploadSlotBytes( $appData, '/small', orientedJpeg( 300, 160, 1 ) );
[ , $turned ] = callUploadSlotBytes( $appData, '/small', orientedJpeg( 300, 160, 6 ) );
check( 'it is decided on the size as shown: 300x160 is large enough for 200x150, the same pixels with Orientation 6 are 160x300 and are not', ( $upright['belowTarget'] ?? null ) === false
	&& ( $turned['belowTarget'] ?? null ) === true && ( $turned['source'] ?? null ) === [ 'width' => 160, 'height' => 300 ] );

// A write that does not happen leaves the old record and the old file - the old one's
// bytes, not only a file of that name: a re-upload is stored under the same name and
// has overwritten it by the time the record is written
$smallFile = \Nino\Filesystem::path( $appData, '/images/small.200x150.jpg' );
check( 'the slot\'s file stands before the failing write', $appData['/nino/html/images']['/small']['filename'] === 'small.200x150.jpg' && is_file( $smallFile ) === true );
$smallBytes = (string) file_get_contents( $smallFile );
$smallBefore = $appData['/nino/html/images']['/small']['filename'];
$configLock = $sandbox. '/private/data/.locks/'. sha1( '/config.php' ). '.lock';
@unlink( $configLock );
mkdir( $configLock, 0755, true );
[ $failedStatus ] = callUploadSlotBytes( $appData, '/small', orientedJpeg( 300, 200, 1, 200 ) );
$persistedSmall = ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['/small']['filename'] ?? null;
check( 'an upload whose record cannot be written is a 500 that changes nothing: the filename in memory and in config.php, and the old picture\'s bytes, stay', $failedStatus === 500
	&& $appData['/nino/html/images']['/small']['filename'] === $smallBefore && $persistedSmall === $smallBefore && is_file( $smallFile ) === true && (string) file_get_contents( $smallFile ) === $smallBytes );
rmdir( $configLock );

// A first upload has no old picture to put back: the file it wrote is nobody's and goes
$appData['/nino/html/images']['/fresh'] = [ 'label' => 'Fresh', 'width' => 200, 'height' => 150, 'filename' => null ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
@unlink( $configLock );
mkdir( $configLock, 0755, true );
[ $freshStatus ] = callUploadSlotBytes( $appData, '/fresh', orientedJpeg( 300, 200, 1 ) );
rmdir( $configLock );
check( 'a first upload whose record cannot be written is a 500 and leaves no file behind', $freshStatus === 500 && $appData['/nino/html/images']['/fresh']['filename'] === null
	&& glob( \Nino\Filesystem::path( $appData, '/images' ). '/fresh.*' ) === [] );

// --- apiRemove: the image goes, the slot stays -----------------------------

/**
 *	Call Admin\Images::apiRemove, or any other action of its map, directly
 *
 *	@param		array 		&$appData
 *	@param		string		$method
 *	@param		array			$data
 *	@param		int				$statusBefore			The status the request already carries
 *
 *	@return		array			[ statusCode, body ]
 */
function callImagesAdmin( array &$appData, string $method, array $data, int $statusBefore = 200 ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => $statusBefore ] ];
	$_POST['data'] = json_encode( $data );
	\Nino\Modules\Images\Admin::{$method}( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ?? null ];
}

\Nino\Modules\Images::init( $appData );

[ $status ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/nope' ] );
check( 'removing the image of an unknown slot is a 404', $status === 404 );

$appData['/nino/html/images']['/empty'] = [ 'label' => 'Empty', 'width' => 60, 'height' => 40, 'filename' => null ];
$slotsBeforeEmpty = $appData['/nino/html/images'];
[ $status, $body ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/empty' ] );
check( 'a slot without an image answers 200 with no filename and changes nothing - the call can be repeated', $status === 200 && array_key_exists( 'filename', $body ) === true && $body['filename'] === null && $appData['/nino/html/images'] === $slotsBeforeEmpty );

\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
check( 'the slot to remove from shows its image', str_contains( \Nino\Html::renderHtml( $appData, '[image /hero]' ), '<img src=' ) === true && is_file( $slotUploadPath ) === true );
[ $status, $body ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/hero' ] );
$persistedSlots = ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images'];
check( 'removing answers 200 with no filename; the record is null in memory and in config.php', $status === 200 && array_key_exists( 'filename', $body ) === true && $body['filename'] === null
	&& $appData['/nino/html/images']['/hero']['filename'] === null && array_key_exists( 'filename', $persistedSlots['/hero'] ) === true && $persistedSlots['/hero']['filename'] === null );
check( '...the file is deleted, the slot stays with its size, and [image] renders nothing', is_file( $slotUploadPath ) === false
	&& ( $appData['/nino/html/images']['/hero']['width'] ?? null ) === 60 && \Nino\Html::renderHtml( $appData, '[image /hero]' ) === '' );

// A name an editor wrote into config.php by hand - a logo a template includes literally - is cleared from the slot, and its file is not the slot's to delete
\Nino\Filesystem::putFileContent( $appData, '/images/logo.png', 'a logo a template includes literally' );
$appData['/nino/html/images']['/handset'] = [ 'label' => 'Hand set', 'width' => 60, 'height' => 40, 'filename' => 'logo.png' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
[ $status ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/handset' ] );
check( 'a hand-set name is cleared from the slot, but its file stays', $status === 200 && $appData['/nino/html/images']['/handset']['filename'] === null && \Nino\Filesystem::fileExists( $appData, '/images/logo.png' ) === true );

// Two slots naming one file: the one that is removed from does not take it away from the other
\Nino\Filesystem::putFileContent( $appData, '/images/twin.60x40.jpg', 'shared bytes' );
$appData['/nino/html/images']['/twin'] 		= [ 'label' => 'Twin', 'width' => 60, 'height' => 40, 'filename' => 'twin.60x40.jpg' ];
$appData['/nino/html/images']['/twin2'] 	= [ 'label' => 'Twin 2', 'width' => 60, 'height' => 40, 'filename' => 'twin.60x40.jpg' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
[ $status ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/twin' ] );
check( 'a file another slot still names stays', $status === 200 && $appData['/nino/html/images']['/twin']['filename'] === null && \Nino\Filesystem::fileExists( $appData, '/images/twin.60x40.jpg' ) === true
	&& $appData['/nino/html/images']['/twin2']['filename'] === 'twin.60x40.jpg' );

// A write that cannot happen: the file and the filename are what they were
\Nino\Filesystem::putFileContent( $appData, '/images/failing.60x40.jpg', 'bytes' );
$appData['/nino/html/images']['/failing'] = [ 'label' => 'Failing', 'width' => 60, 'height' => 40, 'filename' => 'failing.60x40.jpg' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
@unlink( $configLock );
mkdir( $configLock, 0755, true );
[ $status ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/failing' ] );
check( 'a record that cannot be written is a 500, and both the file and the filename stay', $status === 500 && $appData['/nino/html/images']['/failing']['filename'] === 'failing.60x40.jpg'
	&& \Nino\Filesystem::fileExists( $appData, '/images/failing.60x40.jpg' ) === true );
rmdir( $configLock );

// A request that already failed its csrf check changes nothing
[ $status ] = callImagesAdmin( $appData, 'apiRemove', [ 'uri' => '/failing' ], 403 );
check( 'a request whose status is already 403 (csrf) removes nothing', $status === 403 && $appData['/nino/html/images']['/failing']['filename'] === 'failing.60x40.jpg' && \Nino\Filesystem::fileExists( $appData, '/images/failing.60x40.jpg' ) === true );
unset( $appData['./nino/html/shortcodes']['image'], $appData['./nino/callbacks']['/nino/html/shortcode/image'] );
$appData['./nino/html/cache'] = false;

// --- apiAlt: the alt text per language ---------------------------------------

[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/nope', 'alt' => [ 'de_DE' => 'x' ] ] );
check( 'saving alt texts for an unknown slot is a 404', $status === 404 );
[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => 'x' ] );
check( 'alt that is no list of texts by language is a 400', $status === 400 );
[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'xx_XX' => 'x' ] ] );
check( 'a language the site does not have is a 400', $status === 400 );
[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'de_DE' => [ 'x' ] ] ] );
check( 'a value that is no string is a 400', $status === 400 );
[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'de_DE' => str_repeat( 'ä', 251 ) ] ] );
check( '251 characters are a 400', $status === 400 );
[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'de_DE' => str_repeat( 'ä', 250 ). "\n" ] ] );
check( '...and 250 characters, the trailing line break cleaned away first, are not', $status === 200 );
check( 'nothing of the refused calls was stored', array_keys( \Nino\Images::getSlot( $appData, '/small' )['alt'] ?? [] ) === [ 'de_DE' ] );

[ $status, $body ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'de_DE' => ' Ein Haus ', 'en_US' => 'A house' ] ] );
$persistedAlt = ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['/small']['alt'] ?? null;
check( 'a valid call answers 200 with the stored map, trimmed, and persists it to config.php', $status === 200 && $body['alt'] === [ 'de_DE' => 'Ein Haus', 'en_US' => 'A house' ] && $persistedAlt === $body['alt'] );
[ $status, $body ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'en_US' => '' ] ] );
check( 'an empty text removes that language, and another one is left as it was', $status === 200 && $body['alt'] === [ 'de_DE' => 'Ein Haus' ] );
[ $status ] = callImagesAdmin( $appData, 'apiAlt', [ 'uri' => '/small', 'alt' => [ 'de_DE' => 'x' ] ], 403 );
check( 'a request whose status is already 403 (csrf) saves nothing', $status === 403 && $appData['/nino/html/images']['/small']['alt'] === [ 'de_DE' => 'Ein Haus' ] );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Images\Admin::apiList( $appData, $listRequest );
$listBody = $listRequest['/nino/http/response']['body'];
$smallRow = array_values( array_filter( $listBody['slots'], static fn( array $s ): bool => $s['uri'] === '/small' ) )[0];
check( 'apiList carries every slot\'s alt texts and the languages an alt text can be written in', $smallRow['alt'] === [ 'de_DE' => 'Ein Haus' ] && $listBody['locales'] === [ 'de_DE', 'en_US' ] );
check( '...and where each slot is used: every slot carries usage, a slot no template mentions an empty one', count( array_filter( $listBody['slots'], static fn( array $s ): bool => isset( $s['usage']['pages'], $s['usage']['templates'] ) ) ) === count( $listBody['slots'] )
	&& $smallRow['usage'] === [ 'templates' => [], 'pages' => [] ] );

echo "\n";


// --- Users::apiList / apiSave / apiLogoutAll --------------------------------

echo "Users::apiList / apiSave / apiLogoutAll\n";

/**
 *	Call a Users::api* method as whichever user is currently logged in
 *
 *	@param		array 		&$appData
 *	@param		string		$method				apiList | apiSave | apiLogoutAll
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body ]
 */
function callUsers( array &$appData, string $method, array $data = [] ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	\Nino\Modules\Users\Admin::{$method}( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

// '/*' rather than just users/manage - later sections reuse this session for
// unrelated module actions (Elements/Text/Logs via handlePost), same as any
// real "full admin" account would have
\Nino\Auth::insertUser( $appData, 'manager@example.com', 'manager password', [ '/*' ] );
\Nino\Auth::insertUser( $appData, 'plain@example.com', 'plain password' );

\Nino\Auth::loginUser( $appData, 'plain@example.com', 'plain password' );
[ , $body ] = callUsers( $appData, 'apiList' );
check( 'a plain user only sees themselves in the list', count( $body['users'] ) === 1 && $body['users'][0]['mail'] === 'plain@example.com' );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'plain@example.com', 'mail' => 'plain2@example.com', 'currentPassword' => 'wrong password' ] );
check( 'a plain user editing themselves needs the right current password', $status === 401 );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'plain@example.com', 'mail' => 'plain@example.com', 'pw' => 'short', 'currentPassword' => 'plain password' ] );
check( 'a new password shorter than eight characters is rejected', $status === 400 );

[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'plain@example.com', 'mail' => 'plain2@example.com', 'currentPassword' => 'plain password' ] );
check( 'a plain user can rename themselves with the right current password', $status === 200 && $body['mail'] === 'plain2@example.com' );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'manager@example.com', 'mail' => 'manager2@example.com', 'currentPassword' => '' ] );
check( 'a plain user cannot edit another user at all', $status === 403 );

[ $status ] = callUsers( $appData, 'apiLogoutAll', [ 'username' => 'manager@example.com' ] );
check( 'a plain user cannot log out another user', $status === 403 );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );
[ , $body ] = callUsers( $appData, 'apiList' );
// admin@example.com (from the earlier sections) + manager@example.com + plain2@example.com
check( 'a manager sees every user in the list', count( $body['users'] ) === 3 );

[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'plain2@example.com', 'mail' => 'plain3@example.com' ] );
check( 'a manager can rename another user without knowing their password', $status === 200 && $body['mail'] === 'plain3@example.com' );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'plain3@example.com', 'mail' => 'manager@example.com' ] );
check( 'renaming to a mail already in use is rejected', $status === 400 );

// Whoever sets a password signs in with it - so a manager may set one only
// on an account no wider than their own, the same bound as handing out a
// role (see Roles::notHeld()). A rename grants nothing and stays open
\Nino\Auth::insertUser( $appData, 'wide@example.com', 'wide password', [ '/*' ] );
\Nino\Auth::insertUser( $appData, 'narrow@example.com', 'narrow password', [ \Nino\Modules\Text\Admin::MANAGE_PERM ] );
\Nino\Auth::insertUser( $appData, 'usersonly@example.com', 'users only password', [ \Nino\Modules\Users\Admin::MANAGE_PERM, \Nino\Modules\Text\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'usersonly@example.com', 'users only password' );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'wide@example.com', 'mail' => 'wide@example.com', 'pw' => 'a password of my choosing' ] );
check( 'a manager cannot set the password of an account holding a permission their own does not', $status === 403 );
check( '...and that account\'s stored password is untouched', password_verify( 'wide password', \Nino\Auth::getUser( $appData, 'wide@example.com' )['pw'] ) === true );

[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'wide@example.com', 'mail' => 'wide2@example.com' ] );
check( 'renaming that account stays open - a new address grants nothing', $status === 200 && $body['mail'] === 'wide2@example.com' );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'narrow@example.com', 'mail' => 'narrow@example.com', 'pw' => 'a password of my choosing' ] );
check( 'a manager can set the password of an account no wider than their own', $status === 200 && password_verify( 'a password of my choosing', \Nino\Auth::getUser( $appData, 'narrow@example.com' )['pw'] ) === true );

\Nino\Auth::deleteUser( $appData, 'wide2@example.com' );
\Nino\Auth::deleteUser( $appData, 'narrow@example.com' );
\Nino\Auth::deleteUser( $appData, 'usersonly@example.com' );

// An account written by hand has a hash, a status and permissions and no
// 'role' key (see kernel-smoke.php). Changing its own password must answer
// ok: reading the missing key would raise a warning the framework makes fatal
// on a real request - after the password was already stored. This file's
// handler swallows warnings, so the ones of this call are recorded
$appData['/nino/auth/user']['hand@example.com'] = [ 'pw' => password_hash( 'hand password', PASSWORD_DEFAULT ), 'status' => \Nino\Auth::STATUS_ACTIVE, 'perms' => [ '/*' ] ];
\Nino\Auth::loginUser( $appData, 'hand@example.com', 'hand password' );
$handWarnings = [];
set_error_handler( function( int $level, string $message ) use ( &$handWarnings ): bool { $handWarnings[] = $message; return true; } );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'hand@example.com', 'mail' => 'hand@example.com', 'pw' => 'a password of my choosing', 'currentPassword' => 'hand password' ] );
restore_error_handler();
check( 'a hand-written account without a role key changes its own password without a warning', $status === 200 && $body['mail'] === 'hand@example.com' && $body['role'] === '' && $handWarnings === [] );
check( '...and the new password is the stored one', password_verify( 'a password of my choosing', \Nino\Auth::getUser( $appData, 'hand@example.com' )['pw'] ) === true );
\Nino\Auth::deleteUser( $appData, 'hand@example.com' );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

$appData['/nino/auth/user']['plain3@example.com']['sessions'] = [ '127.0.0.1' => time(), '10.0.0.1' => time() ];
[ $status, $body ] = callUsers( $appData, 'apiLogoutAll', [ 'username' => 'plain3@example.com' ] );
check( 'a manager can log out another user everywhere', $status === 200 && $body['ok'] === true && $body['loggedOutSelf'] === false );
check( 'that user\'s sessions are actually cleared', \Nino\Auth::getUser( $appData, 'plain3@example.com' )['sessions'] === [] );

// --- Users: status, lock and last login in the list; deactivating; one save ---

[ , $body ] = callUsers( $appData, 'apiList' );
$managerRow = $body['users'][ array_search( 'manager@example.com', array_column( $body['users'], 'mail' ), true ) ];
$plainRow 	= $body['users'][ array_search( 'plain3@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( 'apiList says per account whether it is active, locked and when it logged in', $managerRow['status'] === 'active' && $managerRow['locked'] === ''
	&& preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $managerRow['lastLogin'] ) === 1 );
check( '...an account that never logged in has no login, and an account that was not locked no lock', $plainRow['locked'] === '' && is_string( $plainRow['lastLogin'] ) === true );

\Nino\Filesystem::mutate( $appData, '/data/auth-tries.php', static fn( mixed $state ): array => ( is_array( $state ) ? $state : [] ) + [ 'plain3@example.com' => 0 - time() - 3600 ] );
[ , $body ] = callUsers( $appData, 'apiList' );
$plainRow = $body['users'][ array_search( 'plain3@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( '...an account locked out shows until when', preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $plainRow['locked'] ) === 1 && $plainRow['locked'] > date( 'Y-m-d H:i' ) );
\Nino\Auth::unlock( $appData, 'plain3@example.com' );

// Deactivating: a manager's, never one's own, never the last active full access
\Nino\Auth::insertUser( $appData, 'toggle@example.com', 'toggle password', [] );
\Nino\Auth::loginUser( $appData, 'toggle@example.com', 'toggle password' );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'manager@example.com', 'active' => false ] );
check( 'apiStatus refuses your own account', $status === 400 && \Nino\Auth::getUser( $appData, 'manager@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'nobody-here@example.com', 'active' => false ] );
check( '...404s for an unknown account', $status === 404 );
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'toggle@example.com' ] );
check( '...and wants to be told which way', $status === 400 );
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'toggle@example.com', 'active' => 'no' ] );
check( '...a word is not a bool', $status === 400 && \Nino\Auth::getUser( $appData, 'toggle@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );

[ $status, $body ] = callUsers( $appData, 'apiStatus', [ 'username' => 'toggle@example.com', 'active' => false ] );
check( 'a manager deactivates another account', $status === 200 && $body['status'] === 'disabled' && \Nino\Auth::getUser( $appData, 'toggle@example.com' )['status'] === \Nino\Auth::STATUS_DISABLED );
check( '...which ends its sessions and refuses its login', \Nino\Auth::getUser( $appData, 'toggle@example.com' )['sessions'] === [] && \Nino\Auth::loginUser( $appData, 'toggle@example.com', 'toggle password' ) === false );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );
[ , $body ] = callUsers( $appData, 'apiList' );
check( '...and the list says so', $body['users'][ array_search( 'toggle@example.com', array_column( $body['users'], 'mail' ), true ) ]['status'] === 'disabled' );
[ $status, $body ] = callUsers( $appData, 'apiStatus', [ 'username' => 'toggle@example.com', 'active' => true ] );
check( 'and activates it again', $status === 200 && $body['status'] === 'active' && is_array( \Nino\Auth::loginUser( $appData, 'toggle@example.com', 'toggle password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

// The last active account with full access. Every other account is switched
// off in memory for the length of the check - the session of the manager
// doing it is not touched by that
\Nino\Auth::insertUser( $appData, 'lastadmin@example.com', 'last admin password', [ '/*' ] );
$statuses = [];
foreach( $appData['/nino/auth/user'] as $mail => $record )
	if( $mail !== 'lastadmin@example.com' ) {
		$statuses[$mail] = $record['status'] ?? null;
		$appData['/nino/auth/user'][$mail]['status'] = \Nino\Auth::STATUS_DISABLED;
	}
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'lastadmin@example.com', 'active' => false ] );
check( 'the last active account with full access cannot be deactivated - other full access accounts being disabled do not count', $status === 409
	&& \Nino\Auth::getUser( $appData, 'lastadmin@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
check( '...fullAccessExists() counts active accounts only', \Nino\Modules\Users\Roles::fullAccessExists( $appData ) === true && \Nino\Modules\Users\Roles::fullAccessExists( $appData, 'lastadmin@example.com' ) === false );
[ $status ] = callUsers( $appData, 'apiDelete', [ 'username' => 'lastadmin@example.com' ] );
check( '...nor deleted', $status === 409 );
foreach( $statuses as $mail => $was )
	$appData['/nino/auth/user'][$mail]['status'] = $was;
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'lastadmin@example.com', 'active' => false ] );
check( 'with another active full access in place it can be', $status === 200 );
\Nino\Auth::deleteUser( $appData, 'lastadmin@example.com' );

check( 'log() names the direction of the switch', \Nino\Modules\Users\Admin::log( 'users/status', [ 'username' => 'x@example.com', 'active' => false ] ) === 'Deactivate User x@example.com'
	&& \Nino\Modules\Users\Admin::log( 'users/status', [ 'username' => 'x@example.com', 'active' => true ] ) === 'Activate User x@example.com' );

// One save: address, password and role. Nothing is written unless all of it is allowed
\Nino\Auth::insertUser( $appData, 'onesave@example.com', 'one save password', [] );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave@example.com', 'mail' => 'onesave2@example.com', 'pw' => 'a changed password', 'role' => 'editor' ] );
check( 'a manager changes address, password and role in one save', $status === 200 && $body === [ 'mail' => 'onesave2@example.com', 'role' => 'editor' ] );
$saved = \Nino\Auth::getUser( $appData, 'onesave2@example.com' );
check( '...and all three are stored', $saved !== false && $saved['role'] === 'editor' && password_verify( 'a changed password', $saved['pw'] ) === true && \Nino\Auth::getUser( $appData, 'onesave@example.com' ) === false );
check( 'log() puts the role into the line', \Nino\Modules\Users\Admin::log( 'users/save', [ 'username' => 'a@example.com', 'mail' => 'b@example.com', 'role' => 'editor' ] ) === 'Edit User b@example.com Role editor' );

[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave2@example.com', 'mail' => 'onesave3@example.com', 'role' => 'editor' ] );
check( 'a role posted unchanged is not a role change', $status === 200 && \Nino\Auth::getUser( $appData, 'onesave3@example.com' )['role'] === 'editor' );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave3@example.com', 'mail' => 'onesave3@example.com', 'role' => 'no-such-role' ] );
check( 'an unknown role is refused', $status === 400 && \Nino\Auth::getUser( $appData, 'onesave3@example.com' )['role'] === 'editor' );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave3@example.com', 'mail' => 'onesave4@example.com', 'role' => [ 'editor' ] ] );
check( '...and so is a role that is not a string, with nothing written', $status === 400 && \Nino\Auth::getUser( $appData, 'onesave3@example.com' ) !== false );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'manager@example.com', 'mail' => 'manager@example.com', 'currentPassword' => 'manager password', 'role' => 'editor' ] );
check( 'changing your own role is refused', $status === 400 && ( \Nino\Auth::getUser( $appData, 'manager@example.com' )['role'] ?? '' ) === '' );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave3@example.com', 'mail' => 'manager@example.com', 'role' => 'developer' ] );
check( 'a refused address writes no role either: the checks come before the write', $status === 400 && \Nino\Auth::getUser( $appData, 'onesave3@example.com' )['role'] === 'editor' );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave3@example.com', 'mail' => 'onesave3@example.com', 'role' => '' ] );
check( 'a role can be taken away in the same call', $status === 200 && $body['role'] === '' && \Nino\Auth::getUser( $appData, 'onesave3@example.com' )['role'] === '' );

// A manager who is no developer: the address of a wider account stays theirs
// to change with the role as it is, the role itself does not
\Nino\Auth::insertUser( $appData, 'devacct@example.com', 'dev account password', [], 'developer' );
\Nino\Auth::insertUser( $appData, 'usersonly@example.com', 'users only password', [ \Nino\Modules\Users\Admin::MANAGE_PERM, \Nino\Modules\Text\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'usersonly@example.com', 'users only password' );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'devacct@example.com', 'mail' => 'devacct2@example.com', 'role' => 'developer' ] );
check( 'a manager without full access renames a Developer account with the role as it is', $status === 200 && $body === [ 'mail' => 'devacct2@example.com', 'role' => 'developer' ] );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'onesave3@example.com', 'mail' => 'onesave5@example.com', 'role' => 'developer' ] );
check( '...but may not hand out a role wider than their own - and nothing of that save is written', $status === 403
	&& \Nino\Auth::getUser( $appData, 'onesave3@example.com' ) !== false && \Nino\Auth::getUser( $appData, 'onesave5@example.com' ) === false && \Nino\Auth::getUser( $appData, 'onesave3@example.com' )['role'] === '' );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'usersonly@example.com', 'mail' => 'usersonly@example.com', 'currentPassword' => 'users only password', 'role' => 'developer' ] );
check( 'nor their own role', $status === 400 );

\Nino\Auth::deleteUser( $appData, 'devacct2@example.com' );
\Nino\Auth::deleteUser( $appData, 'usersonly@example.com' );
\Nino\Auth::deleteUser( $appData, 'onesave3@example.com' );
\Nino\Auth::deleteUser( $appData, 'toggle@example.com' );

// A plain user: no manager, so no role of their own either, and no status
\Nino\Auth::insertUser( $appData, 'selfsave@example.com', 'self save password', [] );
\Nino\Auth::loginUser( $appData, 'selfsave@example.com', 'self save password' );
[ $status ] = callUsers( $appData, 'apiSave', [ 'username' => 'selfsave@example.com', 'mail' => 'selfsave@example.com', 'currentPassword' => 'self save password', 'role' => 'developer' ] );
check( 'an account that is no manager cannot post itself a role', $status === 403 && ( \Nino\Auth::getUser( $appData, 'selfsave@example.com' )['role'] ?? '' ) === '' );
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'plain3@example.com', 'active' => false ] );
check( '...nor deactivate an account', $status === 403 );
\Nino\Auth::logoutUser( $appData );
[ $status ] = callUsers( $appData, 'apiStatus', [ 'username' => 'plain3@example.com', 'active' => false ] );
check( 'and apiStatus wants a session at all', $status === 401 );
\Nino\Auth::deleteUser( $appData, 'selfsave@example.com' );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

// --- Users::apiSetRole / Roles ---

/**
 *	Call a Roles::api* method as whichever user is currently logged in
 *
 *	@param		array 		&$appData
 *	@param		string		$method				apiList | apiSave | apiDelete
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body ]
 */
function callRoles( array &$appData, string $method, array $data = [] ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	\Nino\Modules\Users\Roles::{$method}( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

// Its own dedicated account - plain3@example.com's empty perms are relied on
// by the "Admin::guardPerm" section below, so this must not touch it
\Nino\Auth::insertUser( $appData, 'permtest@example.com', 'perm test password' );

\Nino\Auth::loginUser( $appData, 'permtest@example.com', 'perm test password' );
[ $status ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'permtest@example.com', 'role' => 'developer' ] );
check( 'a non-manager cannot set anyone\'s role, not even their own', $status === 403 );
check( 'a non-manager cannot read or write the roles either', callRoles( $appData, 'apiList' )[0] === 403 && callRoles( $appData, 'apiSave', [ 'id' => 'x', 'label' => 'X', 'perms' => [] ] )[0] === 403 );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

[ $status ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'does-not-exist@example.com', 'role' => 'editor' ] );
check( 'apiSetRole 404s for an unknown user', $status === 404 );

[ $status ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'permtest@example.com', 'role' => 'no-such-role' ] );
check( 'apiSetRole refuses a role the config does not have', $status === 400 );

[ $status ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'manager@example.com', 'role' => 'editor' ] );
check( 'apiSetRole refuses your own account - log out and ask another manager', $status === 400 );

[ $status, $body ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'permtest@example.com', 'role' => 'editor' ] );
check( 'apiSetRole hands an account a role', $status === 200 && $body['role'] === 'editor' && \Nino\Auth::getUser( $appData, 'permtest@example.com' )['role'] === 'editor' );
check( 'the role\'s permissions are the account\'s - the content panels, not a structure tab', \Nino\Auth::checkPermission( $appData, \Nino\Modules\Elements\Admin::MANAGE_PERM, 'permtest@example.com' ) === true && \Nino\Auth::checkPermission( $appData, \Nino\Modules\Elements\Types::MANAGE_PERM, 'permtest@example.com' ) === false );

[ , $body ] = callUsers( $appData, 'apiList' );
$listed = $body['users'][ array_search( 'permtest@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( 'apiList names each account\'s role and the roles there are', $listed['role'] === 'editor' && array_column( $body['roles'], 'id' ) === [ 'editor', 'developer' ] );

[ $status ] = callRoles( $appData, 'apiDelete', [ 'id' => 'editor' ] );
check( 'a role an account holds cannot be deleted', $status === 409 );

// Shaped like a permission or refused by name. Not a whitelist any more: the
// scoped permissions (see \Nino\Admin\Admin::scoped()) are one string per
// action and per field, and there is no enumerating them to check against
[ $status ] = callRoles( $appData, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ \Nino\Modules\Text\Admin::MANAGE_PERM, 'not/a/real/perm' ] ] );
check( 'apiSave refuses a string that is not shaped like a permission', $status === 400 );

[ $status, $body ] = callRoles( $appData, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ \Nino\Modules\Text\Admin::MANAGE_PERM ] ] );
check( 'apiSave creates the role once every permission is one', $status === 200 && $body['perms'] === [ \Nino\Modules\Text\Admin::MANAGE_PERM ] && $appData['/nino/auth/roles']['reviewer']['label'] === 'Reviewer' );

[ $status ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'permtest@example.com', 'role' => 'reviewer' ] );
check( 'the new role can be handed out at once', $status === 200 && \Nino\Auth::checkPermission( $appData, \Nino\Modules\Text\Admin::MANAGE_PERM, 'permtest@example.com' ) === true && \Nino\Auth::checkPermission( $appData, \Nino\Modules\Elements\Admin::MANAGE_PERM, 'permtest@example.com' ) === false );

[ $status ] = callUsers( $appData, 'apiSetRole', [ 'username' => 'permtest@example.com', 'role' => '' ] );
check( 'an account may hold no role at all', $status === 200 && \Nino\Auth::checkPermission( $appData, \Nino\Modules\Text\Admin::MANAGE_PERM, 'permtest@example.com' ) === false );

[ $status ] = callRoles( $appData, 'apiDelete', [ 'id' => 'reviewer' ] );
check( 'a role nobody holds can go', $status === 200 && isset( $appData['/nino/auth/roles']['reviewer'] ) === false );

echo "\n";


[ $status, $body ] = callUsers( $appData, 'apiLogoutAll', [ 'username' => 'manager@example.com' ] );
check( 'logging out yourself everywhere reports loggedOutSelf', $status === 200 && $body['loggedOutSelf'] === true );
check( 'logging out yourself everywhere actually ends the current session', \Nino\Auth::getCurrentUser( $appData ) === false );

echo "\n";


// --- Admin::guardPerm - per-module permissions ------------------------------

echo "Admin::guardPerm - per-module permissions (Elements/Text/Images/Submissions/Features/Logs)\n";

// plain3@example.com (renamed from plain2/plain earlier) has no perms at all
\Nino\Auth::loginUser( $appData, 'plain3@example.com', 'plain password' );

[ $status ] = callAdminPost( $appData, 'elements/types' );
check( 'a user with no perms is rejected from elements/types', $status === 403 );

[ $status ] = callAdminPost( $appData, 'text/keys' );
check( 'a user with no perms is rejected from text/keys', $status === 403 );

[ $status ] = callAdminPost( $appData, 'images/list' );
check( 'a user with no perms is rejected from images/list', $status === 403 );

[ $status ] = callAdminPost( $appData, 'elements/removeimage', [ 'type' => 'imageremove', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'elements/removeimage is the Elements panel\'s: no perms, no removal', $status === 403 );

[ $status ] = callAdminPost( $appData, 'images/remove', [ 'uri' => '/failing' ] );
check( '...and from images/remove, which would delete a file', $status === 403 && \Nino\Filesystem::fileExists( $appData, '/images/failing.60x40.jpg' ) === true );

[ $status ] = callAdminPost( $appData, 'images/alt', [ 'uri' => '/small', 'alt' => [ 'de_DE' => 'x' ] ] );
check( '...and from images/alt', $status === 403 && $appData['/nino/html/images']['/small']['alt'] === [ 'de_DE' => 'Ein Haus' ] );

[ $status ] = callAdminPost( $appData, 'submissions/list' );
check( 'a user with no perms is rejected from submissions/list', $status === 403 );

[ $status ] = callAdminPost( $appData, 'features/list' );
check( 'a user with no perms is rejected from features/list', $status === 403 );

[ $status ] = callAdminPost( $appData, 'logs/list' );
check( 'a user with no perms is rejected from logs/list', $status === 403 );

// A narrowly-scoped account: only Elements::MANAGE_PERM, nothing else
\Nino\Auth::insertUser( $appData, 'contenteditor@example.com', 'editor password', [ \Nino\Modules\Elements\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'contenteditor@example.com', 'editor password' );

[ $status ] = callAdminPost( $appData, 'elements/types' );
check( 'a user with only Elements::MANAGE_PERM can reach elements/types', $status === 200 );

[ $status ] = callAdminPost( $appData, 'text/keys' );
check( 'that same user still can\'t reach text/keys - perms don\'t leak across modules', $status === 403 );

// The dashboard summary panel itself stays reachable to any logged-in admin
// regardless of perms, but each module-specific field within it is only
// included for an admin who actually holds that module's own permission
// (see Admin\Dashboard::apiSummary) - contenteditor@example.com holds only
// Elements::MANAGE_PERM here
[ $status, $body ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary needs no specific perm, just to be logged in', $status === 200 );
check( 'elements/lastBackup are not module-specific and stay in the body regardless', array_key_exists( 'elements', $body ) === true && array_key_exists( 'lastBackup', $body ) === true );
// A panel's tile is contributed by the panel itself (summary() in the
// panel contract) and only for an admin holding that panel's perm - so the
// tile list is where a withheld number shows as absent
$tilePanels = array_column( $body['tiles'] ?? [], 'panel' );
check( 'the submissions tile is withheld without Submissions::VIEW_PERM', in_array( 'submissions', $tilePanels, true ) === false );
check( 'the features tile is withheld without Features::MANAGE_PERM', in_array( 'features', $tilePanels, true ) === false );
check( 'recentActivity is withheld without Logs::VIEW_PERM - the exact leak the field-level gate closes', array_key_exists( 'recentActivity', $body ) === false );

$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $appData, $getRequest );
$visiblePanels = array_keys( \Nino\Admin\Admin::visiblePanels( $appData ) );
check( 'GET navigation exposes dashboard, the permitted Elements panel and the user\'s own profile', $visiblePanels === [ 'dashboard', 'elements', 'users' ] );
check( 'GET navigation does not advertise endpoints this account cannot use', in_array( 'text', $visiblePanels, true ) === false && in_array( 'logs', $visiblePanels, true ) === false && in_array( 'config', $visiblePanels, true ) === false );
check( 'the rendered nav carries only those links, under the two group headings they span - content, and the own profile under system', substr_count( \Nino\Html::renderTextfill( $appData, '/_admin/nav' ), 'data-panel=' ) === 3 && substr_count( \Nino\Html::renderTextfill( $appData, '/_admin/nav' ), 'nino-admin-nav-group' ) === 2 );
check( 'the Elements pane opens without its Element Types tab for this account', \Nino\Admin\Admin::visiblePanels( $appData )['elements']['tabs'] === [] && str_contains( \Nino\Html::renderTextfill( $appData, '/_admin/panes' ), 'admin-panel-tabs' ) === false );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $appData, $getRequest );
$visiblePanels = array_keys( \Nino\Admin\Admin::visiblePanels( $appData ) );
check( 'a full-access account gets every panel in its navigation, content first, then structure, then system', $visiblePanels === [ 'dashboard', 'elements', 'text', 'images', 'submissions', 'logs', 'routes', 'users', 'language', 'backups', 'features', 'config' ] );
check( '...with every tab on its pane', array_keys( \Nino\Admin\Admin::visiblePanels( $appData )['users']['tabs'] ) === [ 'roles', 'lockout', 'recoverypw' ] && substr_count( \Nino\Html::renderTextfill( $appData, '/_admin/panes' ), 'admin-panel-tabs' ) === 5 );
/*	Every tab the shell renders is announced as a tab of a tablist, so every
	one of them has to name the pane it opens and every pane has to be the
	tabpanel that names it back. Counted rather than spot-checked: a strip
	where one button carries the pair and the next does not is exactly the
	shape a fragment edited by hand takes	*/
$renderedPanes = \Nino\Html::renderTextfill( $appData, '/_admin/panes' );
check( 'every rendered tab names the pane it opens, and every pane is the tabpanel that names it back',
	substr_count( $renderedPanes, 'role="tab"' ) > 0
	&& substr_count( $renderedPanes, 'role="tab"' ) === substr_count( $renderedPanes, 'aria-controls="admin-tab-' )
	&& substr_count( $renderedPanes, 'role="tabpanel"' ) === substr_count( $renderedPanes, 'aria-labelledby="admin-tabbutton-' )
	&& substr_count( $renderedPanes, 'role="tabpanel"' ) === substr_count( $renderedPanes, 'id="admin-tab-' ) );
$firstTab = \Nino\Admin\Admin::visiblePanels( $appData )['users']['uri'];
check( '...and the two ids a tab and its pane point at are really each other\'s',
	str_contains( $renderedPanes, 'id="admin-tabbutton-roles" class="nino-admin-tab" data-tab="roles" aria-controls="admin-tab-roles"' ) === true
	&& str_contains( $renderedPanes, 'id="admin-tab-roles" role="tabpanel" aria-labelledby="admin-tabbutton-roles"' ) === true
	&& $firstTab === 'users' );
check( 'the rendered nav then carries the three group headings', substr_count( \Nino\Html::renderTextfill( $appData, '/_admin/nav' ), 'nino-admin-nav-group' ) === 3 );

echo "\n";


// --- Backup ------------------------------------------------------------

echo "Backup::maybeRun (triggered from Admin::guard on every authenticated request)\n";

// Every earlier guarded call in this whole file (Text::apiSaveBatch etc., way
// above) already ran Backup::maybeRun() at least once, since Admin::guard()
// is the hook point - so the dir/key already exist and today's backup already
// exists too, from whatever state existed back then. Remove it so the checks
// below observe a fresh backup of *this* section's actual current state,
// rather than a stale one made from a much earlier, mostly-empty config.php.
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );
callUsers( $appData, 'apiList' ); // ensures the backup key exists even on a from-scratch run

check( 'the backup key exists in config.php', isset( $appData['/nino/backup/key'] ) === true );
check( 'no random backup directory is generated any more', isset( $appData['/nino/backup/dir'] ) === false );

$backupDir 	= $sandbox. '/private/.backups';
$today 			= $backupDir. '/'. date( 'Y-m-d' ). '.php';
$nestedBackupImage = '/images/elements/backup/nested.jpg';
$nestedBackupBytes = 'nested-image-bytes';
\Nino\Filesystem::putFileContent( $appData, $nestedBackupImage, $nestedBackupBytes );

if( is_file( $today ) === true )
	unlink( $today );

$logLinesBeforeBackup = readTodayLogLines( $appData, $sandbox );

callUsers( $appData, 'apiList' ); // this call's Backup::maybeRun() creates today's backup fresh

check( 'the backup directory is created', is_dir( $backupDir ) === true );
check( 'today\'s backup file is created', is_file( $today ) === true );

$logLinesAfterBackup = readTodayLogLines( $appData, $sandbox );
check( 'creating a backup is recorded to the activity log', count( $logLinesAfterBackup ) === count( $logLinesBeforeBackup ) + 1 && str_ends_with( end( $logLinesAfterBackup ), '  Backup created' ) === true );

$raw 		= file_get_contents( $today );
$prefix = "<?php http_response_code(403); exit; return '";
check( 'the backup file starts with the self-terminating stub, not raw bytes', str_starts_with( $raw, $prefix ) === true );

$suffix 	= "';\n";
$payload 	= base64_decode( substr( $raw, strlen( $prefix ), -strlen( $suffix ) ) );
$key 			= base64_decode( $appData['/nino/backup/key'] );
$iv 			= substr( $payload, 0, 12 );
$tag 			= substr( $payload, 12, 16 );
$cipher 	= substr( $payload, 28 );
$gz 			= openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

check( 'the payload decrypts with the stored key', $gz !== false );

$tmpGz = $sandbox. '/verify.tar.gz';
file_put_contents( $tmpGz, $gz );
$extractDir = $sandbox. '/verify-extracted';
mkdir( $extractDir );
( new \PharData( $tmpGz ) )->extractTo( $extractDir );

check( 'the decrypted archive contains a byte-identical config.php', file_get_contents( $sandbox. '/private/config.php' ) === file_get_contents( $extractDir. '/config.php' ) );
check( 'the decrypted archive preserves nested element images', file_get_contents( $extractDir. '/images/elements/backup/nested.jpg' ) === $nestedBackupBytes );

$mtimeBefore = filemtime( $today );
clearstatcache();
callUsers( $appData, 'apiList' );
clearstatcache();
check( 'a second authenticated request the same day doesn\'t touch an already-existing backup', filemtime( $today ) === $mtimeBefore );

// The restore key is a second, independent copy. It is reconciled on every
// authenticated request, not only on the first bootstrap, so a stale/corrupt
// copy cannot make otherwise-valid backups undecryptable from _admin.
mkdir( $sandbox. '/_admin', 0777, true );
$restoreKeyPath = $sandbox. '/private/.auth/backup-key.php';
file_put_contents( $restoreKeyPath, 'stale' );
callUsers( $appData, 'apiList' );
check( 'a stale restore-key copy is repaired from the locked config value', file_get_contents( $restoreKeyPath ) === $prefix. $appData['/nino/backup/key']. $suffix );

$staleFile = $backupDir. '/2020-01-01.php';
file_put_contents( $staleFile, $prefix. 'x'. $suffix );
unlink( $today ); // force a fresh create+prune cycle, same as a new calendar day would
callUsers( $appData, 'apiList' );

check( 'a stale backup past the retention window gets pruned on the next create', is_file( $staleFile ) === false );
check( 'a fresh backup for today exists after the prune cycle', is_file( $today ) === true );

echo "\n";


// --- Logs::record via Admin::handlePost()'s per-action hook + login hook ---

echo "Admin activity log (Logs::record via handlePost()'s per-action hook + login hook)\n";

/**
 *	Dispatch one POST /_admin action through the real Admin::handlePost()
 *	entry point (not the domain class directly) - the activity log only
 *	hooks in at that level, so exercising it needs the real dispatch path
 *
 *	@param		array 		&$appData
 *	@param		string		$action				eg. "elements/save"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body ]
 */
function callAdminPost( array &$appData, string $action, array $data = [] ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['action'] = $action;
	$_POST['data'] = json_encode( $data );
	\Nino\Admin\Admin::handlePost( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

/**
 *	Read today's log file back into its lines, decoding the same stub +
 *	base64 wrapping Logs::record() writes
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		string[]
 */
function readTodayLogLines( array &$appData, string $sandbox ): array {
	$path = $sandbox. '/private/.logs/'. date( 'Y-m-d' ). '.php';
	if( is_file( $path ) === false )
		return [];
	$prefix 	= "<?php http_response_code(403); exit; return '";
	$suffix 	= "';\n";
	$decoded 	= base64_decode( substr( file_get_contents( $path ), strlen( $prefix ), -strlen( $suffix ) ) );
	return $decoded === '' ? [] : explode( "\n", $decoded );
}

// Admin::guard() has already been called many times by earlier sections
// (every domain action calls it), so the activity log already has entries
// (including "Login" lines from _logLoginOnce()) by this point - every check
// below compares against a fresh baseline instead of assuming an empty log

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

\Nino\Elements::insertElementType( $appData, '/logtestdemo', [ 'model' => [ 'title' => [ 'type' => 'string' ] ] ] );

$before = readTodayLogLines( $appData, $sandbox );

[ $status ] = callAdminPost( $appData, 'elements/save', [ 'type' => 'logtestdemo', 'uri' => 'entry1', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'title' => 'Hi' ] ] );
check( 'elements/save via handlePost succeeds', $status === 200 );

$lines = readTodayLogLines( $appData, $sandbox );
check( 'elements/save is recorded to the activity log', count( $lines ) === count( $before ) + 1 && str_ends_with( end( $lines ), 'Add Element /logtestdemo/entry1' ) === true );
check( 'the log line records the acting admin', str_contains( end( $lines ), 'manager@example.com' ) === true );

// The logs no longer need an unguessable directory name: they sit under the
// private directory, which is denied by its own .htaccess, and every file in
// there carries the same 403 stub it always did
check( 'the logs live under the private directory, not in a tool folder', is_file( $sandbox. '/private/.logs/'. date( 'Y-m-d' ). '.php' ) === true );
check( 'no random log directory is generated any more', isset( $appData['/nino/logs/dir'] ) === false );
check( 'each log file still carries its own 403 stub', str_starts_with( (string) file_get_contents( $sandbox. '/private/.logs/'. date( 'Y-m-d' ). '.php' ), '<?php http_response_code(403); exit;' ) === true );

callAdminPost( $appData, 'elements/list', [ 'type' => 'logtestdemo' ] );
check( 'a read-only action (elements/list) does not add a log entry', count( readTodayLogLines( $appData, $sandbox ) ) === count( $lines ) );

[ $status ] = callAdminPost( $appData, 'elements/save', [ 'type' => 'logtestdemo', 'uri' => '', 'locale' => 'de_DE', 'fields' => [] ] );
check( 'an invalid elements/save is rejected', $status === 400 );
check( 'a failed action does not add a log entry', count( readTodayLogLines( $appData, $sandbox ) ) === count( $lines ) );

[ $status ] = callAdminPost( $appData, 'elements/delete', [ 'type' => 'logtestdemo', 'uri' => 'entry1' ] );
check( 'elements/delete via handlePost succeeds', $status === 200 );

$linesAfterDelete = readTodayLogLines( $appData, $sandbox );
check( 'elements/delete is recorded to the activity log', count( $linesAfterDelete ) === count( $lines ) + 1 && str_ends_with( end( $linesAfterDelete ), 'Delete Element /logtestdemo/entry1' ) === true );

[ $status ] = callAdminPost( $appData, 'text/savebatch', [ 'items' => [ [ 'key' => '/home/plain', 'locale' => 'de_DE', 'value' => 'Neuer Text' ] ] ] );
check( 'text/savebatch via handlePost succeeds', $status === 200 );

$linesAfterText = readTodayLogLines( $appData, $sandbox );
check( 'text/savebatch is recorded with the key\'s category, not the locale', count( $linesAfterText ) === count( $linesAfterDelete ) + 1 && str_ends_with( end( $linesAfterText ), 'Edit Text /home' ) === true );

// A page's form saves its template's texts and its route's details in one request: the line names both groups
[ $status ] = callAdminPost( $appData, 'text/savebatch', [ 'items' => [
	[ 'key' => '/home/plain', 'locale' => 'de_DE', 'value' => 'Noch ein Text' ],
	[ 'key' => '/_nino/webpage/home/title', 'locale' => 'de_DE', 'value' => 'Startseite' ],
] ] );
$linesAfterPage = readTodayLogLines( $appData, $sandbox );
check( 'a batch over two namespaces is recorded with both groups', $status === 200 && count( $linesAfterPage ) === count( $linesAfterText ) + 1 && str_ends_with( end( $linesAfterPage ), 'Edit Text /home, /_nino/webpage/home' ) === true );

// --- Login hook: Admin::guard()/handleGet() log "Login" the first time a
// newly-authenticated session touches _editor - not a direct Auth hook, since
// the real login POST (/.nino/auth/login) isn't guaranteed to be routed
// through _admin/index.php at all (see Admin::_logLoginOnce()'s docblock)

unset( $appData['./nino/auth/current'] );
\Nino\Runtime::unsetSessionValue( $appData, './admin/loginLoggedFor' );

[ $status ] = callAdminPost( $appData, 'elements/list', [ 'type' => 'logtestdemo' ] );
check( 'a logged-out request is rejected', $status === 401 );
check( 'a logged-out request adds no log entry', count( readTodayLogLines( $appData, $sandbox ) ) === count( $linesAfterPage ) );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

[ $status ] = callAdminPost( $appData, 'elements/list', [ 'type' => 'logtestdemo' ] );
check( 'the first request after a fresh login still succeeds', $status === 200 );

$linesAfterLogin = readTodayLogLines( $appData, $sandbox );
check( 'a fresh login is recorded exactly once', count( $linesAfterLogin ) === count( $linesAfterPage ) + 1 );
check( 'the login line names the newly-authenticated user', str_ends_with( end( $linesAfterLogin ), '  Login' ) === true && str_contains( end( $linesAfterLogin ), 'manager@example.com' ) === true );

callAdminPost( $appData, 'elements/list', [ 'type' => 'logtestdemo' ] );
check( 'a second request in the same login does not log another Login line', count( readTodayLogLines( $appData, $sandbox ) ) === count( $linesAfterLogin ) );

[ $status, $body ] = callAdminPost( $appData, 'logs/list' );
$linesSoFar = readTodayLogLines( $appData, $sandbox );
check( 'logs/list succeeds', $status === 200 );
check( 'logs/list returns lines most-recent-first', $body['lines'][0] === end( $linesSoFar ) );
check( 'logs/list returns every recorded line so far', count( $body['lines'] ) === count( $linesSoFar ) );

$logsDir = $sandbox. '/private/.logs';
$staleLogFile = $logsDir. '/2020-01-01.php';
file_put_contents( $staleLogFile, "<?php http_response_code(403); exit; return '". base64_encode( '2020-01-01 00:00  x  y' ). "';\n" );

callAdminPost( $appData, 'elements/save', [ 'type' => 'logtestdemo', 'uri' => 'entry2', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'title' => 'Hi2' ] ] );
check( 'a stale log file past the retention window gets pruned on the next write', is_file( $staleLogFile ) === false );

echo "\n";


// --- Submissions: Modules\Form writes, its own Editor panel reads independently ---

echo "Submissions (Modules\\Form writes, Modules\\Form\\Editor::apiList reads)\n";

\Nino\Html::addFills( $appData, [
	'[[/project/mail/address/owner]]' 	=> 'owner@example.com',
	'[[/module/form/subject/owner]]' => 'New inquiry',
	'[[/module/form/subject/user]]' 	=> 'Thanks for reaching out',
	// The labels the contact form's own install unit writes. A definition
	// carries the fill, not the word, so the panel is what resolves it -
	// in the interface language of whoever is looking
	'[[/template/common/form/name]]'		=> 'Name',
	'[[/template/common/form/email]]'		=> 'E-Mail',
	'[[/template/common/form/reason]]'			=> 'Subject',
	'[[/template/common/form/message]]'	=> 'Message',
], '*' );

// A transport that takes the mails: a submission whose owner mail did not go
// out is a 500, and whether this machine has a sendmail is not what is tested
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );

$_POST = [ 'name' => 'Jo Client', 'email' => 'jo@example.com', 'message' => 'Hallo!', 'location' => '', 'cat' => 'General' ];
$formRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $formRequest );
check( 'a valid contact submission succeeds', $formRequest['/nino/http/response']['statusCode'] === 200 );

[ $status, $body ] = callAdminPost( $appData, 'submissions/list' );
check( 'submissions/list succeeds', $status === 200 );
check( 'submissions/list finds the submission just sent', count( $body['entries'] ) === 1 && $body['entries'][0]['email'] === 'jo@example.com' );
// The panel knows no field names of its own - a project may define any
// form. What turns a stored 'cat' back into "Subject" is the definition
// listed beside the entries, with its labels' fills already resolved in
// the interface language
check( 'and the forms beside them, each with the fields and labels the panel renders a card from', array_keys( $body['forms'] ) === [ 'contact' ]
	&& $body['forms']['contact']['name'] === 'Contact'
	&& array_column( $body['forms']['contact']['fields'], 'name' ) === [ 'name', 'email', 'cat', 'message' ]
	&& array_column( $body['forms']['contact']['fields'], 'type' ) === [ 'text', 'email', 'text', 'textarea' ]
	&& array_column( $body['forms']['contact']['fields'], 'label' ) === [ 'Name', 'E-Mail', 'Subject', 'Message' ] );

// Deleting one - the request a person makes about their own inquiry, and
// the one write this panel does
[ $status ] = callAdminPost( $appData, 'submissions/delete', [ 'id' => str_repeat( 'f', 16 ) ] );
check( 'submissions/delete answers 404 for an id nothing carries', $status === 404 );

[ $status ] = callAdminPost( $appData, 'submissions/delete', [ 'id' => [ 'not', 'a', 'string' ] ] );
check( '...and for something that is not an id at all, without raising anything', $status === 404 );

[ $status, $body ] = callAdminPost( $appData, 'submissions/delete', [ 'id' => $body['entries'][0]['id'] ] );
check( 'submissions/delete removes the one it names', $status === 200 && $body['deleted'] === true );

[ $status, $body ] = callAdminPost( $appData, 'submissions/list' );
check( '...and the list is empty afterwards', $status === 200 && $body['entries'] === [] );

check( 'the deletion is what the activity log writes for it', \Nino\Modules\Form\Admin::log( 'submissions/delete', [ 'id' => 'abc' ] ) === 'Delete submission abc'
	&& \Nino\Modules\Form\Admin::log( 'submissions/list', [] ) === '' );

// Sent again, so what follows sees the same one submission it did before
$_POST = [ 'name' => 'Jo Client', 'email' => 'jo@example.com', 'message' => 'Hallo!', 'location' => '', 'cat' => 'General' ];
$formRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $formRequest );
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );

check( 'forms data lives on the private root, not under _editor', is_file( \Nino\Filesystem::path( $appData, '/data/forms.'. date( 'Y-m' ). '.php' ) ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

unset( $appData['./nino/auth/current'] );
[ $status ] = callAdminPost( $appData, 'submissions/list' );
check( 'submissions/list requires an authed admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

echo "\n";


// --- A module's panel comes and goes with the module -----------------------

echo "Admin::panels - a module's panel, actions, permission and script leave with the module\n";

// The panel is the module's: switch the module off and the screen, its
// actions and its permission are gone from the workbench - which is what
// makes a project without a contact form carry no Submissions panel either.
// Modules\Form is the example here; a feature's panel behaves the same way
// (see tests/features-smoke.php)
$withoutForm = $appData;
$withoutForm['/nino/modules'] = array_values( array_diff( $withoutForm['/nino/modules'], [ '\\Nino\\Modules\\Form' ] ) );
check( 'with the Form module off, its panel is not in the registry', isset( \Nino\Admin\Admin::panels( $withoutForm )['submissions'] ) === false );
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'submissions/list';
$_POST['data'] = json_encode( [] );
\Nino\Admin\Admin::handlePost( $withoutForm, $request );
check( 'with the Form module off, submissions/list is an unknown action', $request['/nino/http/response']['statusCode'] === 404 );
// ...but a permission the module declared does not vanish from the list while
// a role still holds it. It cannot: permOptions() is the whitelist apiSave()
// filters against and the only thing the Roles picker can send back, so a
// permission missing from here is one the next save of that role drops. The
// Editor role the wizard wrote holds this one, so it stays - marked as offered
// by nothing, which is what the picker shows it as
$offForm = \Nino\Modules\Users\Admin::permOptions( $withoutForm );
check( 'with the Form module off, no panel offers its permission any more', in_array( \Nino\Modules\Form\Admin::VIEW_PERM, array_column( array_filter( $offForm, fn( array $o ): bool => $o['offered'] === true ), 'perm' ), true ) === false );
check( '...but the Editor role still holds it, so it stays assignable rather than being dropped on the next save', in_array( [ 'perm' => \Nino\Modules\Form\Admin::VIEW_PERM, 'label' => \Nino\Modules\Form\Admin::VIEW_PERM, 'group' => 'other', 'offered' => false ], $offForm, true ) === true );
$roundTrip = $withoutForm;
[ $rtStatus, $rtBody ] = callAdminPost( $roundTrip, 'roles/save', [ 'id' => 'editor', 'label' => 'Editor', 'perms' => $roundTrip['/nino/auth/roles']['editor']['perms'] ] );
check( '...and saving that role back unchanged keeps it', $rtStatus === 200 && in_array( \Nino\Modules\Form\Admin::VIEW_PERM, $rtBody['perms'], true ) === true );
check( 'a permission nothing holds and no panel offers is still refused', in_array( '/_admin/nowhere/manage', array_column( $offForm, 'perm' ), true ) === false );
check( 'with the Form module off, its script leaves the bundle', ( static function() use ( $withoutForm ): bool { \Nino\Admin\Admin::init( $withoutForm ); return in_array( '/_nino/Nino/Modules/Form/assets/admin.js', $withoutForm['/nino/html/assets']['/_admin/.cache/script.js'], true ) === false; } )() );

echo "\n";


// --- Dashboard::apiSummary - aggregates numbers the other panels already compute ---

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary succeeds', $status === 200 );
$tilesByPanel = array_column( $body['tiles'] ?? [], null, 'panel' );
check( 'the submissions tile matches Submissions::count (1 sent above, none deleted)', ( $tilesByPanel['submissions']['value'] ?? null ) === '1' );
// The Features panel's tile counts the active features - none in a checkout,
// which ships no feature of its own
check( 'the features tile counts the active features', (string) ( $tilesByPanel['features']['value'] ?? '' ) === '0' );
check( 'a tile carries the fill key its panel labels it with', ( $tilesByPanel['features']['label'] ?? null ) === '/_admin/features/label/active' );

$imagedemo = array_values( array_filter( $body['elements'], fn( $e ) => $e['type'] === 'imagedemo' ) )[0] ?? null;
check( 'elements includes imagedemo with its final count (item1 remains, item2 was deleted above)', $imagedemo !== null && $imagedemo['count'] === 1 );

check( 'lastBackup is today\'s date (Backup::maybeRun already ran via Admin::guard() many times above)', $body['lastBackup'] === date( 'Y-m-d' ) );
check( 'recentActivity is non-empty (at least the Login line from earlier)', is_array( $body['recentActivity'] ) && count( $body['recentActivity'] ) > 0 );

unset( $appData['./nino/auth/current'] );
[ $status ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary requires an authed admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

echo "\n";


// --- What a failure says: codes, params, the field - and the session ---------

echo "Failure answers - a code the client can say in its own language, and the end of a session told from a refusal\n";

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );
$failBody = fn( array $request ): mixed => $request['/nino/http/response']['body'];

// Http::fail() adds nothing a caller did not give
$plain = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Http::fail( $plain, 400, 'nope' );
check( 'Http::fail() with three arguments answers exactly what it always did', $plain['/nino/http/response']['statusCode'] === 400 && $failBody( $plain ) === [ 'error' => 'nope' ] );
\Nino\Http::fail( $plain, 400, 'nope', 'some_code', [ 3, 'x' ], 'title' );
check( '...and code, params and field are added when given', $failBody( $plain ) === [ 'error' => 'nope', 'code' => 'some_code', 'params' => [ 3, 'x' ], 'field' => 'title' ] );

// The csrf guard answers a bare 403; the workbench says what it was
$blocked = [ '/nino/http/response' => [ 'statusCode' => 403, 'body' => false ], './nino/csrf/blocked' => true ];
\Nino\Admin\Admin::handlePost( $appData, $blocked );
check( 'a request the csrf guard refused is answered 403 with the code "csrf" and a readable message', $blocked['/nino/http/response']['statusCode'] === 403
	&& is_string( $failBody( $blocked )['error'] ?? null ) === true && ( $failBody( $blocked )['code'] ?? null ) === 'csrf' && array_key_exists( 'params', $failBody( $blocked ) ) === false );

$post = \Nino\Images::limits()['post'];
if( $post > 0 ) {
	$tooBig = [ '/nino/http/response' => [ 'statusCode' => 403, 'body' => false ], './nino/csrf/blocked' => true, '/nino/http/request' => [ 'header' => [ 'Content-Length' => (string) ( $post + 1 ) ] ] ];
	\Nino\Admin\Admin::handlePost( $appData, $tooBig );
	check( 'a refused request whose Content-Length is above post_max_size is 413 "post_too_large" with the limit in MB - php dropped its token with the rest of the body', $tooBig['/nino/http/response']['statusCode'] === 413
		&& ( $failBody( $tooBig )['code'] ?? null ) === 'post_too_large' && $failBody( $tooBig )['params'] === [ \Nino\Admin\Admin::megabytes( $post ) ] );

	$within = [ '/nino/http/response' => [ 'statusCode' => 403, 'body' => false ], './nino/csrf/blocked' => true, '/nino/http/request' => [ 'header' => [ 'Content-Length' => (string) $post ] ] ];
	\Nino\Admin\Admin::handlePost( $appData, $within );
	check( '...one within the limit is a stale token, not a size', ( $failBody( $within )['code'] ?? null ) === 'csrf' );
}

$vetoed = [ '/nino/http/response' => [ 'statusCode' => 409, 'body' => [ 'error' => 'earlier callback' ] ] ];
\Nino\Admin\Admin::handlePost( $appData, $vetoed );
check( 'any other refusal of an earlier callback is left as it was', $vetoed['/nino/http/response']['statusCode'] === 409 && $failBody( $vetoed ) === [ 'error' => 'earlier callback' ] );

// The end of a session carries a code of its own; a refusal does not
$loggedOut = $appData;
unset( $loggedOut['./nino/auth/current'] );
[ $status, $body ] = callAdminPost( $loggedOut, 'elements/types' );
check( 'a logged-out request is 401 with the code "session"', $status === 401 && $body === [ 'error' => 'not logged in', 'code' => 'session' ] );

\Nino\Auth::loginUser( $appData, 'plain3@example.com', 'plain password' );
[ $status, $body ] = callAdminPost( $appData, 'elements/types' );
check( 'a permission refusal stays exactly as it was - no code, so no re-login is offered for it', $status === 403 && $body === [ 'error' => 'not allowed' ] );

\Nino\Auth::insertUser( $appData, 'wrongpw@example.com', 'a long enough password' );
\Nino\Auth::loginUser( $appData, 'wrongpw@example.com', 'a long enough password' );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'wrongpw@example.com', 'mail' => 'wrongpw@example.com', 'currentPassword' => 'not it' ] );
check( 'a wrong current password is 401 with the code "wrong_password" and the field - not the code of a lost session', $status === 401 && $body === [ 'error' => 'wrong current password', 'code' => 'wrong_password', 'field' => 'currentPassword' ] );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'wrongpw@example.com', 'mail' => 'not a mail', 'currentPassword' => 'a long enough password' ] );
check( 'an invalid mail names its code and field', $status === 400 && ( $body['code'] ?? '' ) === 'users_invalid_mail' && ( $body['field'] ?? '' ) === 'mail' );
[ $status, $body ] = callUsers( $appData, 'apiSave', [ 'username' => 'wrongpw@example.com', 'mail' => 'wrongpw@example.com', 'pw' => 'short', 'currentPassword' => 'a long enough password' ] );
check( 'a short password names its code, the minimum as param and the field', $status === 400 && ( $body['code'] ?? '' ) === 'users_password_short' && ( $body['params'] ?? [] ) === [ 8 ] && ( $body['field'] ?? '' ) === 'pw' );

// GET ?session=1 - whose session is this, and the token it holds
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );
$_GET['session'] = '1';
$sessionRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $appData, $sessionRequest );
check( 'GET ?session=1 answers the account and the session\'s token as json, not the page', $failBody( $sessionRequest ) === [ 'user' => 'manager@example.com', 'csrf' => \Nino\Csrf::getToken( $appData ) ] );

$sessionOut = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $loggedOut, $sessionOut );
check( '...logged out it answers no account and the anonymous session\'s token - the login that follows needs it, and it comes before the login page', $failBody( $sessionOut ) === [ 'user' => '', 'csrf' => \Nino\Csrf::getToken( $loggedOut ) ] && \Nino\Csrf::getToken( $loggedOut ) !== '' );
check( '...and neither answer is left to a cache', ( $sessionRequest['/nino/http/response']['header']['Cache-Control'] ?? '' ) === 'no-store' && ( $sessionOut['/nino/http/response']['header']['Cache-Control'] ?? '' ) === 'no-store' );
unset( $_GET['session'] );

$pageRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $appData, $pageRequest );
$limits = \Nino\Images::limits();
check( 'the page carries the upload limits for the browser to check a file against', \Nino\Html::renderTextfill( $appData, '/_admin/upload/bytes' ) === (string) $limits['bytes'] && \Nino\Html::renderTextfill( $appData, '/_admin/upload/pixels' ) === (string) \Nino\Images::MAX_SOURCE_PIXELS );

// What php says about an upload that did not arrive
$_POST['data'] = json_encode( [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'en_US', 'key' => 'photo' ] );
$uploadWith = function( array|null $file, string $target ) use ( &$appData ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $target === 'slot' ? [ 'uri' => '/hero' ] : [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'en_US', 'key' => 'photo' ] );
	if( $file === null )
		unset( $_FILES['file'] );
	else
		$_FILES['file'] = $file;
	$target === 'slot' ? \Nino\Modules\Images\Admin::apiUpload( $appData, $request ) : \Nino\Modules\Elements\Admin::apiUploadImage( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
};

foreach( [ 'element', 'slot' ] as $target ) {
	[ $status, $body ] = $uploadWith( [ 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE ], $target );
	check( $target. ' upload above upload_max_filesize is 413 "upload_too_large" with the PHP limit in MB', $status === 413 && ( $body['code'] ?? '' ) === 'upload_too_large' && $body['params'] === [ \Nino\Admin\Admin::megabytes( $limits['php'] > 0 ? $limits['php'] : $limits['bytes'] ) ] );
	[ $status, $body ] = $uploadWith( [ 'tmp_name' => '', 'error' => UPLOAD_ERR_FORM_SIZE ], $target );
	check( '...so is one above the form\'s own limit', $status === 413 && ( $body['code'] ?? '' ) === 'upload_too_large' );
	[ $status, $body ] = $uploadWith( [ 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL ], $target );
	check( '...a partial upload is 400 "upload_partial"', $status === 400 && ( $body['code'] ?? '' ) === 'upload_partial' );
	[ $status, $body ] = $uploadWith( [ 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE ], $target );
	check( '...no file is 400 "upload_missing"', $status === 400 && ( $body['code'] ?? '' ) === 'upload_missing' );
	[ $status, $body ] = $uploadWith( null, $target );
	check( '...and so is no file field at all', $status === 400 && ( $body['code'] ?? '' ) === 'upload_missing' );
	foreach( [ UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION ] as $serverFault ) {
		[ $status, $body ] = $uploadWith( [ 'tmp_name' => '', 'error' => $serverFault ], $target );
		check( '...a fault of the server\'s own ('. $serverFault. ') is a 500 "upload_server", not the person\'s doing', $status === 500 && ( $body['code'] ?? '' ) === 'upload_server' );
	}
}

// What the kernel says about the picture itself
$pngHeader = function( int $width, int $height ): string {
	$ihdr = pack( 'NN', $width, $height ). "\x08\x02\x00\x00\x00";
	return "\x89PNG\r\n\x1a\n". pack( 'N', 13 ). 'IHDR'. $ihdr. pack( 'N', crc32( 'IHDR'. $ihdr ) );
};
$uploadBytes = function( string $bytes, string $target ) use ( $uploadWith ): array {
	$path = tempnam( sys_get_temp_dir(), 'nino-upload-' );
	file_put_contents( $path, $bytes );
	$result = $uploadWith( [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'x', 'size' => strlen( $bytes ) ], $target );
	@unlink( $path );
	return $result;
};

foreach( [ 'element', 'slot' ] as $target ) {
	[ $status, $body ] = $uploadBytes( str_repeat( 'a', \Nino\Images::MAX_UPLOAD_BYTES + 1 ), $target );
	check( $target. ' upload above the kernel\'s byte cap says "image_too_large" with the limit in MB', $status === 400 && ( $body['code'] ?? '' ) === 'image_too_large' && $body['params'] === [ 8 ] );
	[ $status, $body ] = $uploadBytes( 'not an image at all', $target );
	check( '...a file that is no image says "image_type"', $status === 400 && ( $body['code'] ?? '' ) === 'image_type' && array_key_exists( 'params', $body ) === false );
	[ $status, $body ] = $uploadBytes( $pngHeader( 5000, 5000 ), $target );
	check( '...a picture of more pixels than the kernel decodes says "image_too_many_pixels" with the megapixels', $status === 400 && ( $body['code'] ?? '' ) === 'image_too_many_pixels' && $body['params'] === [ 20 ] );
	[ $status, $body ] = $uploadBytes( $pngHeader( 40, 40 ), $target );
	check( '...and one gd cannot decode after all says "image_unreadable", not that it is oversized', $status === 400 && ( $body['code'] ?? '' ) === 'image_unreadable' );
}
unset( $_FILES['file'] );

echo "\n";


// --- The shell's own text ---------------------------------------------------

/*	Every key the workbench shell renders exists in both shipped locales. A key
	present in one and missing from the other renders as an empty string rather
	than as an error - a login form with a blank message where the reason should
	be is exactly the failure that is hardest to report */
$shellText = [];
foreach( [ 'de_DE', 'en_US' ] as $locale )
	$shellText[$locale] = (array) require __DIR__. '/../_admin/text/'. $locale. '.php';

$missingDe = array_diff_key( $shellText['en_US'], $shellText['de_DE'] );
$missingEn = array_diff_key( $shellText['de_DE'], $shellText['en_US'] );

if( $missingDe !== [] || $missingEn !== [] )
	echo '        missing in de_DE: ', implode( ', ', array_keys( $missingDe ) ), ' | missing in en_US: ', implode( ', ', array_keys( $missingEn ) ), "\n";

check( 'the shell\'s two locales carry the same keys', $missingDe === [] && $missingEn === [] );
check( 'no key is left with an empty value', array_filter( $shellText['de_DE'], fn( $v ) => trim( (string) $v ) === '' ) === []
	&& array_filter( $shellText['en_US'], fn( $v ) => trim( (string) $v ) === '' ) === [] );

/*	The login form tells a refused pair apart from an endpoint that never got to
	read one - see _admin/assets/login.js and tests/admin-login-js-smoke.js. The
	second message carries the status code, so it needs its placeholder */
foreach( [ 'de_DE', 'en_US' ] as $locale ) {
	check( $locale. ' carries the endpoint message the login falls back to',
		isset( $shellText[$locale]['[[/_admin/login/error/endpoint]]'] ) === true );
	check( '...with the place the status code goes',
		str_contains( (string) ( $shellText[$locale]['[[/_admin/login/error/endpoint]]'] ?? '' ), '%s' ) === true );
}

/*	The question asked before unsaved input is lost (Nino.admin.dirty, see
	_admin/assets/script.js) is spoken in the interface language, and German
	addresses the reader as Du - capitalised, as everywhere in the workbench */
foreach( [ 'de_DE', 'en_US' ] as $locale )
	foreach( [ 'confirm/unsaved', 'label/discard', 'label/cancel', 'msg/dirty' ] as $key )
		check( $locale. ' carries /_admin/common/'. $key, trim( (string) ( $shellText[$locale]['[[/_admin/common/'. $key. ']]'] ?? '' ) ) !== '' );
check( 'the question is addressed with a capitalised Du in German', str_contains( (string) $shellText['de_DE']['[[/_admin/common/confirm/unsaved]]'], 'Du hast' ) === true
	&& str_contains( (string) $shellText['de_DE']['[[/_admin/common/confirm/unsaved]]'], 'Möchtest Du' ) === true );
check( 'the three answers are the plain words', $shellText['en_US']['[[/_admin/common/label/save]]'] === 'Save' && $shellText['en_US']['[[/_admin/common/label/discard]]'] === 'Discard' && $shellText['en_US']['[[/_admin/common/label/cancel]]'] === 'Cancel'
	&& $shellText['de_DE']['[[/_admin/common/label/discard]]'] === 'Verwerfen' && $shellText['de_DE']['[[/_admin/common/label/cancel]]'] === 'Abbrechen' );

echo "\n";


// --- Cleanup ---------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
