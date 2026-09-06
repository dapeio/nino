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

file_put_contents( $sandbox. '/private/text/global.php', '<?php return [ \'[[/company/name]]\' => \'Acme\', \'[[/website/lang]]\' => \'de\' ];' );
file_put_contents( $sandbox. '/private/text/de_DE.php', '<?php return [ \'[[/home/h2]]\' => \'<span>Hallo</span> Welt.\', \'[[/home/plain]]\' => \'Ein Satz.\', \'[[/home/long]]\' => \''. $longValue. '\' ];' );
file_put_contents( $sandbox. '/private/text/en_US.php', '<?php return [ \'[[/home/h2]]\' => \'<span>Hi</span> World.\', \'[[/home/plain]]\' => \'A sentence.\' ];' );
file_put_contents( $sandbox. '/private/text/blacklist.php', '<?php return [ \'/website/lang\' ];' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']			= $sandbox;
// Mirrors \Nino\init()'s fixed private/public split.
$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
$appData['./nino/filesystem/privatepath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] 	= $sandbox. '/public';
$appData['/nino/dir']				= '';
$appData['/nino/locales/native']				= 'de_DE';
$appData['/nino/locales/available']			= [ 'de_DE', 'en_US' ];
$appData['/nino/auth/maxtries']					= 3;
$appData['/nino/auth/cooldown']					= 3600;
$appData['/nino/auth/user']							= [];
// A set-up project: the shell serves the workbench, not the wizard
$appData['/nino/install/completed']			= true;
// The two optional modules whose /_admin panels the sections below
// exercise - a panel exists in the editor exactly while its module is
// active (see Admin::panels()), so they have to be switched on here the
// way a project's config.php would
$appData['/nino/modules'][]							= '\\Nino\\Modules\\Form';
$appData['/nino/modules'][]							= '\\Nino\\Modules\\Newsletter';
// The two roles the wizard writes (see Roles::defaults()), read off this
// registry: the Editor role holds the two modules' content permissions too
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
check( 'a panel\'s tabs ride along in the registry, each a panel of its own with its parent named', array_keys( $registry['elements']['tabs'] ) === [ 'types' ] && $registry['elements']['tabs']['types']['parent'] === 'elements' && $registry['elements']['tabs']['types']['perm'] === \Nino\Modules\Elements\Types::MANAGE_PERM && array_keys( $registry['users']['tabs'] ) === [ 'roles', 'lockout' ] && array_keys( $registry['language']['tabs'] ) === [ 'translations' ] );
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
check( 'every pane starts hidden, names its layout and carries its mount points', str_contains( $panesHtml, '<div id="admin-content-dummy" data-panel="dummy" data-layout="page" hidden><div id="dummy-list"></div><div id="dummy-form"></div></div>' ) === true );
check( 'the dashboard pane has no mount point of its own', str_contains( $panesHtml, '<div id="admin-content-dashboard" data-panel="dashboard" data-layout="page" hidden></div>' ) === true );
check( 'a pane with tabs holds the strip and one tab pane per tab, its own screen first', str_contains( $panesHtml, '<div id="admin-content-elements" data-panel="elements" data-layout="page" hidden><div class="nino-admin-tabs nino-admin-tabs--bar admin-panel-tabs" role="tablist"><button type="button" role="tab" class="nino-admin-tab" data-tab="elements" aria-selected="false">[[/_admin/nav/elements]]</button><button type="button" role="tab" class="nino-admin-tab" data-tab="types" aria-selected="false">[[/_admin/nav/types]]</button></div><div id="admin-tab-elements" data-tab="elements" hidden><div id="elements-types"></div><div id="elements-list"></div><div id="elements-form"></div></div><div id="admin-tab-types" data-tab="types" hidden><div id="types-list"></div><div id="types-form"></div></div></div>' ) === true );

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

check( 'blacklisted key is excluded', isset( $byKey['/website/lang'] ) === false );
check( 'global key is included and flagged global', ( $byKey['/company/name']['global'] ?? null ) === true );
check( 'locale key is included and flagged non-global', ( $byKey['/home/h2']['global'] ?? null ) === false );
check( 'a key with existing markup is auto-flagged html', ( $byKey['/home/h2']['html'] ?? null ) === true );
check( 'a key without markup is not flagged html', ( $byKey['/home/plain']['html'] ?? null ) === false );
check( 'maxlength is at least the min floor', ( $byKey['/home/plain']['maxlength'] ?? 0 ) >= 150 );
check( 'maxlength is capped at 2000 for a key already longer than that, not grown past it', ( $byKey['/home/long']['maxlength'] ?? 0 ) === 2000 );
check( 'Text::apiKeys exposes the session-remembered locale, defaulting to native', ( $request['/nino/http/response']['body']['selectedLocale'] ?? null ) === 'de_DE' );

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

$result = saveText( $appData, [ 'key' => '/website/lang', 'locale' => 'de_DE', 'value' => 'fr' ] );
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

$result = saveText( $appData, [ 'key' => '/company/name', 'locale' => '*', 'value' => 'New Co' ] );
check( 'saving a global key succeeds', $result['ok'] === true );
$storedGlobal = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'a global key is written to global.php, not a locale file', ( $storedGlobal['[[/company/name]]'] ?? '' ) === 'New Co' );

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

echo "\n";


// --- Admin\Images::apiList / apiUpload --------------------------------------

echo "Admin\\Images::apiList / apiUpload\n";

$appData['/nino/html/images'] = [
	'/hero' => [ 'label' => 'Hero-Banner', 'width' => 60, 'height' => 40, 'filename' => null ],
];

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
[ $categoryStatus, $categoryBody ] = callUploadSlotImage( $appData, '/home/hero' );
check( 'uploading to a category-style ("/<category>/<identifier>") slot uri succeeds', $categoryStatus === 200 && $categoryBody['filename'] === 'home/hero.60x40.jpg' );
check( 'the file lands at the nested deterministic path', is_file( \Nino\Filesystem::path( $appData, '/images/'. $categoryBody['filename'] ) ) === true );

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

$appData['/nino/auth/user']['plain3@example.com']['sessions'] = [ '127.0.0.1' => time(), '10.0.0.1' => time() ];
[ $status, $body ] = callUsers( $appData, 'apiLogoutAll', [ 'username' => 'plain3@example.com' ] );
check( 'a manager can log out another user everywhere', $status === 200 && $body['ok'] === true && $body['loggedOutSelf'] === false );
check( 'that user\'s sessions are actually cleared', \Nino\Auth::getUser( $appData, 'plain3@example.com' )['sessions'] === [] );

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

echo "Admin::guardPerm - per-module permissions (Elements/Text/Images/Submissions/Newsletter/Logs)\n";

// plain3@example.com (renamed from plain2/plain earlier) has no perms at all
\Nino\Auth::loginUser( $appData, 'plain3@example.com', 'plain password' );

[ $status ] = callAdminPost( $appData, 'elements/types' );
check( 'a user with no perms is rejected from elements/types', $status === 403 );

[ $status ] = callAdminPost( $appData, 'text/keys' );
check( 'a user with no perms is rejected from text/keys', $status === 403 );

[ $status ] = callAdminPost( $appData, 'images/list' );
check( 'a user with no perms is rejected from images/list', $status === 403 );

[ $status ] = callAdminPost( $appData, 'submissions/list' );
check( 'a user with no perms is rejected from submissions/list', $status === 403 );

[ $status ] = callAdminPost( $appData, 'newsletter/list' );
check( 'a user with no perms is rejected from newsletter/list', $status === 403 );

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
check( 'the newsletter tile is withheld without Newsletter::MANAGE_PERM', in_array( 'newsletter', $tilePanels, true ) === false );
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
check( 'a full-access account gets every panel in its navigation, content first, then structure, then system', $visiblePanels === [ 'dashboard', 'elements', 'text', 'images', 'submissions', 'newsletter', 'logs', 'routes', 'users', 'language', 'backups', 'config' ] );
check( '...with every tab on its pane', array_keys( \Nino\Admin\Admin::visiblePanels( $appData )['users']['tabs'] ) === [ 'roles', 'lockout' ] && substr_count( \Nino\Html::renderTextfill( $appData, '/_admin/panes' ), 'admin-panel-tabs' ) === 5 );
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

// --- Login hook: Admin::guard()/handleGet() log "Login" the first time a
// newly-authenticated session touches _editor - not a direct Auth hook, since
// the real login POST (/.nino/auth/login) isn't guaranteed to be routed
// through _admin/index.php at all (see Admin::_logLoginOnce()'s docblock)

unset( $appData['./nino/auth/current'] );
\Nino\Runtime::unsetSessionValue( $appData, './admin/loginLoggedFor' );

[ $status ] = callAdminPost( $appData, 'elements/list', [ 'type' => 'logtestdemo' ] );
check( 'a logged-out request is rejected', $status === 401 );
check( 'a logged-out request adds no log entry', count( readTodayLogLines( $appData, $sandbox ) ) === count( $linesAfterText ) );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

[ $status ] = callAdminPost( $appData, 'elements/list', [ 'type' => 'logtestdemo' ] );
check( 'the first request after a fresh login still succeeds', $status === 200 );

$linesAfterLogin = readTodayLogLines( $appData, $sandbox );
check( 'a fresh login is recorded exactly once', count( $linesAfterLogin ) === count( $linesAfterText ) + 1 );
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
	'[[/form/email/owner]]' 	=> 'owner@example.com',
	'[[/form/subject/owner]]' => 'New inquiry',
	'[[/form/subject/user]]' 	=> 'Thanks for reaching out',
], '*' );

$_POST = [ 'name' => 'Jo Client', 'email' => 'jo@example.com', 'message' => 'Hallo!', 'location' => '', 'cat' => 'General' ];
$formRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $formRequest );
check( 'a valid contact submission succeeds', $formRequest['/nino/http/response']['statusCode'] === 200 );

[ $status, $body ] = callAdminPost( $appData, 'submissions/list' );
check( 'submissions/list succeeds', $status === 200 );
check( 'submissions/list finds the submission just sent', count( $body['entries'] ) === 1 && $body['entries'][0]['email'] === 'jo@example.com' );

check( 'forms data lives on the private root, not under _editor', is_file( \Nino\Filesystem::path( $appData, '/data/forms.'. date( 'Y-m' ). '.php' ) ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

unset( $appData['./nino/auth/current'] );
[ $status ] = callAdminPost( $appData, 'submissions/list' );
check( 'submissions/list requires an authed admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

echo "\n";


// --- Newsletter: Modules\Newsletter writes, its own Editor panel reads/deletes independently ---

echo "Newsletter (Modules\\Newsletter writes, Modules\\Newsletter\\Editor::apiList/apiDelete)\n";

$_POST = [ 'email' => 'jo@example.com', 'location' => '' ];
$newsletterRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Newsletter::callbackResponse( $appData, $newsletterRequest );
check( 'a valid newsletter signup succeeds', $newsletterRequest['/nino/http/response']['statusCode'] === 200 );

$_POST = [ 'email' => 'anna@example.com', 'location' => '' ];
$newsletterRequest2 = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Newsletter::callbackResponse( $appData, $newsletterRequest2 );
check( 'a second valid newsletter signup succeeds', $newsletterRequest2['/nino/http/response']['statusCode'] === 200 );

[ $status, $body ] = callAdminPost( $appData, 'newsletter/list' );
check( 'newsletter/list succeeds', $status === 200 );
check( 'newsletter/list finds both signups just sent', count( $body['entries'] ) === 2 );

check( 'newsletter data lives on the private root, not under _editor', is_file( \Nino\Filesystem::path( $appData, '/data/newsletter.php' ) ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

unset( $appData['./nino/auth/current'] );
[ $status ] = callAdminPost( $appData, 'newsletter/list' );
check( 'newsletter/list requires an authed admin session too', $status === 401 );
[ $status ] = callAdminPost( $appData, 'newsletter/delete', [ 'email' => 'jo@example.com' ] );
check( 'newsletter/delete requires an authed admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

[ $status ] = callAdminPost( $appData, 'newsletter/delete', [ 'email' => 'does-not-exist@example.com' ] );
check( 'newsletter/delete 404s for an email that was never subscribed', $status === 404 );

[ $status ] = callAdminPost( $appData, 'newsletter/delete', [ 'email' => 'jo@example.com' ] );
check( 'newsletter/delete succeeds for an existing subscriber', $status === 200 );

[ , $body ] = callAdminPost( $appData, 'newsletter/list' );
check( 'the deleted subscriber is gone, the other one remains', count( $body['entries'] ) === 1 && $body['entries'][0]['email'] === 'anna@example.com' );

check( 'the admin delete also records the removal, same as a self-service unsubscribe', in_array( hash( 'sha256', 'jo@example.com' ), \Nino\Filesystem::getFileContent( $appData, '/data/newsletter-removed.php', [] ), true ) === true );

// The panel is the module's: switch the module off and the screen, its
// actions and its permission are gone from the editor - which is what makes
// a project without a newsletter carry no newsletter admin either
$withoutNewsletter = $appData;
$withoutNewsletter['/nino/modules'] = array_values( array_diff( $withoutNewsletter['/nino/modules'], [ '\\Nino\\Modules\\Newsletter' ] ) );
check( 'with the Newsletter module off, its panel is not in the registry', isset( \Nino\Admin\Admin::panels( $withoutNewsletter )['newsletter'] ) === false );
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'newsletter/list';
$_POST['data'] = json_encode( [] );
\Nino\Admin\Admin::handlePost( $withoutNewsletter, $request );
check( 'with the Newsletter module off, newsletter/list is an unknown action', $request['/nino/http/response']['statusCode'] === 404 );
// ...but a permission the module declared does not vanish from the list while
// a role still holds it. It cannot: permOptions() is the whitelist apiSave()
// filters against and the only thing the Roles picker can send back, so a
// permission missing from here is one the next save of that role drops. The
// Editor role the wizard wrote holds this one, so it stays - marked as offered
// by nothing, which is what the picker shows it as
$offNewsletter = \Nino\Modules\Users\Admin::permOptions( $withoutNewsletter );
check( 'with the Newsletter module off, no panel offers its permission any more', in_array( \Nino\Modules\Newsletter\Admin::MANAGE_PERM, array_column( array_filter( $offNewsletter, fn( array $o ): bool => $o['offered'] === true ), 'perm' ), true ) === false );
check( '...but the Editor role still holds it, so it stays assignable rather than being dropped on the next save', in_array( [ 'perm' => \Nino\Modules\Newsletter\Admin::MANAGE_PERM, 'label' => \Nino\Modules\Newsletter\Admin::MANAGE_PERM, 'group' => 'other', 'offered' => false ], $offNewsletter, true ) === true );
$roundTrip = $withoutNewsletter;
[ $rtStatus, $rtBody ] = callAdminPost( $roundTrip, 'roles/save', [ 'id' => 'editor', 'label' => 'Editor', 'perms' => $roundTrip['/nino/auth/roles']['editor']['perms'] ] );
check( '...and saving that role back unchanged keeps it', $rtStatus === 200 && in_array( \Nino\Modules\Newsletter\Admin::MANAGE_PERM, $rtBody['perms'], true ) === true );
check( 'a permission nothing holds and no panel offers is still refused', in_array( '/_admin/nowhere/manage', array_column( $offNewsletter, 'perm' ), true ) === false );
check( 'with the Newsletter module off, its script leaves the bundle', ( static function() use ( $withoutNewsletter ): bool { \Nino\Admin\Admin::init( $withoutNewsletter ); return in_array( '/app/Nino/Modules/Newsletter/assets/admin.js', $withoutNewsletter['/nino/html/assets']['/_admin/.cache/script.js'], true ) === false; } )() );

echo "\n";


// --- Dashboard::apiSummary - aggregates numbers the other panels already compute ---

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary succeeds', $status === 200 );
$tilesByPanel = array_column( $body['tiles'] ?? [], null, 'panel' );
check( 'the submissions tile matches Submissions::count (1 sent above, none deleted)', ( $tilesByPanel['submissions']['value'] ?? null ) === '1' );
check( 'the newsletter tile matches what remains after the delete above (jo removed, anna remains)', ( $tilesByPanel['newsletter']['value'] ?? null ) === '1' );
check( 'a tile carries the fill key its panel labels it with', ( $tilesByPanel['newsletter']['label'] ?? null ) === '/_admin/dashboard/label/newsletter' );

$imagedemo = array_values( array_filter( $body['elements'], fn( $e ) => $e['type'] === 'imagedemo' ) )[0] ?? null;
check( 'elements includes imagedemo with its final count (item1 remains, item2 was deleted above)', $imagedemo !== null && $imagedemo['count'] === 1 );

check( 'lastBackup is today\'s date (Backup::maybeRun already ran via Admin::guard() many times above)', $body['lastBackup'] === date( 'Y-m-d' ) );
check( 'recentActivity is non-empty (at least the Login line from earlier)', is_array( $body['recentActivity'] ) && count( $body['recentActivity'] ) > 0 );

unset( $appData['./nino/auth/current'] );
[ $status ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary requires an authed admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

echo "\n";


// --- Cleanup ---------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
