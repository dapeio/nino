<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-system-smoke.php	Dependency-free smoke test for the structure and system panels
 *											(_admin/Admin.php) - the session gate and the element-type
 *											editor in particular, since a mistake there could either
 *											let a save reach a type file it shouldn't, or - worse -
 *											clobber a type's real content ('*'/locale buckets) while
 *											only meaning to touch its model. Runs against an isolated
 *											sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/admin-system-smoke.php
 */

// Run as a child of itself (see "Recovery::handlePost without a Backups
// module" below): a kernel whose autoloader refuses the Backups panel, which
// is the only way to be without that class once this suite has loaded it.
// Prints the status the restore answered, or the error it threw
if( ( $argv[1] ?? '' ) === 'restore-without-backups' ) {
	// Not the harness: it declares check(), and so does this file
	require __DIR__. '/../_nino/Nino.php';
	require __DIR__. '/../_admin/Admin.php';
	$childSandbox = sys_get_temp_dir(). '/nino-recovery-child-'. uniqid();
	mkdir( $childSandbox, 0755, true );
	$appData = [ './nino/uid' => $childSandbox ];
	\Nino\AppData::prepare( $appData );
	$appData['./nino/filesystem/path']				= $childSandbox;
	$appData['./nino/filesystem/configpath']	= $childSandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $childSandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $childSandbox. '/public';
	foreach( spl_autoload_functions() as $loader ) {
		spl_autoload_unregister( $loader );
		spl_autoload_register( function( string $class ) use ( $loader ): void {
			if( $class !== 'Nino\\Modules\\Backups\\Admin' )
				$loader( $class );
		} );
	}
	\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
	$_POST['action'] = 'recovery/restore';
	$_POST['data'] 	= json_encode( [ 'date' => 'not-a-date' ] );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	try {
		\Nino\Admin\Recovery::handlePost( $appData, $request );
		echo $request['/nino/http/response']['statusCode'];
	} catch( \Throwable $e ) {
		echo get_class( $e ). ': '. $e->getMessage();
	}
	\Nino\Filesystem::removeDir( $childSandbox );
	exit;
}

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

$sandbox = sys_get_temp_dir(). '/nino-dev-smoke-'. uniqid();
mkdir( $sandbox, 0777, true );
mkdir( $sandbox. '/private/elements', 0777, true );

// A hand-authored-shaped type file (top-level 'title' key, real locale content) -
// apiSave must only ever touch 'title'/'model', never this real content
file_put_contents( $sandbox. '/private/elements/testtype.php', '<?php return [
	\'title\'	=> \'Test Type\',
	\'model\'	=> [ \'name\' => [ \'type\' => \'string\', \'locale\' => true ] ],
	\'*\'			=> [ \'*\' => [] ],
	\'de_DE\'	=> [ \'item1\' => [ \'name\' => \'Hallo\' ] ],
];' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']	= $sandbox;
// Mirrors \Nino\init()'s fixed private/public split.
$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] 	= $sandbox. '/public';
$appData['/nino/dir']		= '';
$appData['/nino/locales/native']		= 'de_DE';
$appData['/nino/locales/available']	= [ 'de_DE', 'en_US' ];

// Config::apiList() reads config.php fresh rather than $appData (see its
// docblock) - a real deployment always has $appData booted from this file
// in the first place, so mirror that here instead of only setting $appData
\Nino\Filesystem::putFileContent( $appData, '/config.php', [
	'/nino/error/log'					=> false,
	'/nino/error/display'			=> true,
	'/nino/auth/maxtries'			=> 5,
	'/nino/auth/cooldown'			=> 3600,
	'/nino/locales/native'		=> $appData['/nino/locales/native'],
	'/nino/locales/available'	=> $appData['/nino/locales/available'],
	'/nino/locales/textfiles'	=> '/text',
	'/nino/html/assets'				=> [],
	'/nino/http/routes'				=> [],
] );

// A set-up project with one developer account, logged in - the shell serves
// the workbench, every panel action runs against this session
$appData['/nino/install/completed'] = true;
$appData['/nino/auth/user'] = [];
$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
\Nino\Auth::insertUser( $appData, 'dev@example.com', 'correct horse battery staple', [ '/*' ] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "Sandbox: $sandbox\n\n";


// --- Recovery secret and the account gate ---------------------------------

echo "Recovery::verify / Admin::guard / guardPerm\n";

check( 'a fresh checkout has no recovery secret at all', \Nino\Admin\Recovery::hash( $appData ) === null );
check( 'so every attempt fails rather than matching anything', \Nino\Admin\Recovery::verify( $appData, '' ) === 401 );
check( 'storing a secret succeeds', \Nino\Admin\Recovery::set( $appData, 'the real password' ) === true );
check( 'a wrong secret is still rejected', \Nino\Admin\Recovery::verify( $appData, 'not the password' ) === 401 );
check( 'the right one verifies', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 200 );

// 5 wrong attempts trip the lockout
for( $i = 0; $i < 5; $i++ )
	\Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' );
check( 'the 6th attempt is locked out (429), not just rejected as wrong', \Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' ) === 429 );
check( 'the lockout applies regardless of the secret tried next', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 429 );
// Reset the lockout state for the rest of the file - a fresh cooldown window shouldn't leak into later checks
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );

// A counter file that is not the counter. getFileContent()'s $default
// answers for a file that is not there; a file that IS there answers with
// what it holds, and an empty, truncated or unreadable .json decodes to
// null. Under a callback typed `array $state` that was a TypeError, ie. a
// 500 on the one door left to somebody who is locked out of the workbench,
// until the file was repaired by hand. Nothing here can produce such a file
// - _writeFile() renames a temp file into place - but a partial deploy, a
// hand edit or a uid mismatch on the read can
$lockoutFile = \Nino\Filesystem::path( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json' );
foreach( [ 'an empty' => '', 'a truncated' => '{ "tries": ', 'a non-array' => '7' ] as $what => $bytes ) {

	file_put_contents( $lockoutFile, $bytes );
	clearstatcache( true, $lockoutFile );
	unset( $appData['./nino/filesystem/cache'][ \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json' ] );

	check( $what. ' lockout.json still lets recovery answer, rather than 500ing the door shut', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 200 );
}
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );

// How long an answer takes is itself an answer. An installation whose
// secret file went missing must not reject faster than one that has a
// secret to reject against - see Recovery::DECOY_HASH
$attempt = static function() use ( &$appData ): float {
	// A fresh window each time, so neither measurement is cut short by the cooldown
	\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );
	$start = hrtime( true );
	\Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' );
	return ( hrtime( true ) - $start ) / 1e6;
};

$withSecret = $attempt();
@unlink( \Nino\Admin\Recovery::path( $appData ) );
$withoutSecret = $attempt();

// The constant is private, so read it out of the source: a php that moves
// PASSWORD_DEFAULT on must fail a check here rather than quietly leave the
// decoy cheaper than the hashes set() writes
preg_match( '#DECOY_HASH = \'([^\']+)\'#', (string) @file_get_contents( __DIR__. '/../_admin/Admin.php' ), $decoy );
check( 'the decoy hash is of the kind and cost this php hashes with', isset( $decoy[1] ) === true && password_needs_rehash( $decoy[1], PASSWORD_DEFAULT ) === false );
check( 'losing the secret file leaves no stored hash', \Nino\Admin\Recovery::hash( $appData ) === null );
check( 'so nothing authenticates, least of all against the decoy', \Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' ) === 401 );
check( 'and that rejection costs what one against a real hash costs ('. round( $withoutSecret ). ' ms vs '. round( $withSecret ). ' ms)', $withoutSecret > $withSecret / 4 );

check( 'the secret can be stored again for the rest of the file', \Nino\Admin\Recovery::set( $appData, 'the real password' ) === true );
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );
$noMarker = $appData;
unset( $noMarker['/nino/install/completed'] );
check( 'the secret alone counts as installed - losing the marker must not reopen the wizard', \Nino\Admin\Admin::isInstalled( $noMarker ) === true );

// The gate every panel action stands behind: a session with an account, and
// for anything but the everyone-panels that account's permission
\Nino\Auth::logoutUser( $appData );
$guardRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'guard rejects an unauthed request', \Nino\Admin\Admin::guard( $appData, $guardRequest ) === false );
check( 'guard sets a 401', $guardRequest['/nino/http/response']['statusCode'] === 401 );

$_POST['action'] = 'types/list';
$_POST['data']	 = '{}';
$unauthedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $unauthedRequest );
check( 'a panel action is rejected while unauthed', $unauthedRequest['/nino/http/response']['statusCode'] === 401 );

\Nino\Auth::insertUser( $appData, 'editor@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::loginUser( $appData, 'editor@example.com', 'correct horse battery staple' );
$forbiddenRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $forbiddenRequest );
check( 'a structure panel\'s action is 403 for an account without its permission', $forbiddenRequest['/nino/http/response']['statusCode'] === 403 );
check( '...and that panel is not in the account\'s navigation either', isset( \Nino\Admin\Admin::visiblePanels( $appData )['types'] ) === false && isset( \Nino\Admin\Admin::visiblePanels( $appData )['text'] ) === true );
check( 'a tab the account lacks the permission for is left off its pane', \Nino\Admin\Admin::visiblePanels( $appData )['text']['tabs'] === [] );

// The other way round: an account holding only a tab's permission gets the
// pane for the tab alone - no strip, since there is nothing to switch to
\Nino\Auth::insertUser( $appData, 'typesonly@example.com', 'correct horse battery staple', [ '/_admin/types/manage' ] );
\Nino\Auth::loginUser( $appData, 'typesonly@example.com', 'correct horse battery staple' );
$typesOnly = \Nino\Admin\Admin::visiblePanels( $appData );
check( 'an account holding only a tab\'s permission gets the pane without the panel\'s own screen', isset( $typesOnly['elements'] ) === true && $typesOnly['elements']['own'] === false && array_keys( $typesOnly['elements']['tabs'] ) === [ 'types' ] );
check( '...rendered as the tab pane alone under the panel\'s own head - one tab is no strip, but the name stays', str_contains( \Nino\Admin\Admin::panesHtml( $appData ), '<div id="admin-content-elements" data-panel="elements" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">[[/_admin/nav/elements]]</h2><div class="admin-panel-actions"></div></div><div id="admin-tab-types" data-tab="types" hidden><div id="types-list"></div><div id="types-form"></div></div></div>' ) === true );
\Nino\Auth::deleteUser( $appData, 'typesonly@example.com' );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$allowedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $allowedRequest );
check( 'and 200 for full access', $allowedRequest['/nino/http/response']['statusCode'] === 200 );
\Nino\Auth::deleteUser( $appData, 'editor@example.com' );

echo "\n";


// --- Recovery::handlePost without a Backups module -------------------

echo "Recovery::handlePost without a Backups module\n";

// A delivery may drop the Backups module; the recovery page then offers the
// password reset alone and a restore is answered 501 (see the 'recovery/list'
// case). The date check read \Nino\Modules\Backups\Admin::ID_PATTERN before
// the class_exists() guard, so without the module the constant read threw
// first: a 500 behind the one door that is open when nothing else is.
// Measured in a child process whose autoloader refuses that class (see the
// top of this file)
$childAnswer = trim( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( __FILE__ ). ' restore-without-backups 2>&1' ) );
check( 'a delivery without the Backups module answers recovery/restore with 501, not a 500'. ( $childAnswer !== '501' ? ' - child said: '. substr( $childAnswer, 0, 120 ) : '' ), $childAnswer === '501' );

echo "\n";


// --- ElementTypes::apiList / apiGet / apiSave / apiCreate -----------

echo "ElementTypes::apiList / apiGet / apiSave / apiCreate\n";

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiList( $appData, $listRequest );
$types = $listRequest['/nino/http/response']['body']['types'] ?? [];
check( 'apiList finds the sandbox type', count( $types ) === 1 && $types[0]['uri'] === 'testtype' );
check( 'apiList reports the real title', $types[0]['title'] === 'Test Type' );
check( 'apiList reports the real field count', $types[0]['fieldCount'] === 1 );
check( 'apiList exposes the allowed field types', in_array( 'image', $listRequest['/nino/http/response']['body']['fieldTypes'] ?? [], true ) === true );
check( '...and they are the kernel\'s own list, stated once', defined( '\\Nino\\Elements::FIELD_TYPES' ) === true && \Nino\Modules\Elements\Types::FIELD_TYPES === \Nino\Elements::FIELD_TYPES && ( $listRequest['/nino/http/response']['body']['fieldTypes'] ?? null ) === \Nino\Elements::FIELD_TYPES );
// The one place the rule lives: the editor offers a unit for the types the
// server names, and the server keeps a unit for those alone
$suffixTypes = $listRequest['/nino/http/response']['body']['suffixTypes'] ?? null;
check( 'apiList names the types a unit applies to: every field type that renders an input a unit can sit next to', $suffixTypes === array_values( array_diff( \Nino\Modules\Elements\Types::FIELD_TYPES, [ 'boolean', 'image', 'element' ] ) ) );

$_POST['data'] = json_encode( [ 'uri' => 'testtype' ] );
$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiGet( $appData, $getRequest );
$got = $getRequest['/nino/http/response']['body'] ?? [];
check( 'apiGet returns the real title', $got['title'] === 'Test Type' );
check( 'apiGet returns the real model', ( $got['model']['name']['type'] ?? null ) === 'string' );

$_POST['data'] = json_encode( [ 'uri' => 'not-a-real-type' ] );
$getMissingRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiGet( $appData, $getMissingRequest );
check( 'apiGet 404s for an unknown type', $getMissingRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'uri' => '../escape' ] );
$getInvalidRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiGet( $appData, $getInvalidRequest );
check( 'apiGet rejects a non-slug type uri before ever touching the filesystem', $getInvalidRequest['/nino/http/response']['statusCode'] === 400 );

// Save: rename the field, add an image field and a suffixed/maxlength'd one,
// and slip in a field with an invalid type - the invalid one must be
// silently dropped, not crash or persist
$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80, 'inputsize' => 8 ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'suffix' => 'ignored on image', 'required' => true ],
		'price' 	=> [ 'type' => 'double', 'suffix' => '€', 'inputsize' => 5 ],
		'active' 	=> [ 'type' => 'boolean', 'suffix' => 'ignored on boolean' ],
		'ref' 		=> [ 'type' => 'element', 'elementType' => 'testtype', 'suffix' => 'ignored on element' ],
		'bogus' 	=> [ 'type' => 'not-a-real-type' ],
	],
] );
$saveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $saveRequest );
check( 'apiSave succeeds', $saveRequest['/nino/http/response']['statusCode'] === 200 );

$afterSave = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'apiSave updates the title', $afterSave['title'] === 'Test Type Renamed' );
check( 'apiSave keeps the valid field', ( $afterSave['model']['name']['required'] ?? null ) === true );
check( 'apiSave sets a string field\'s maxlength', ( $afterSave['model']['name']['maxlength'] ?? null ) === 80 );
// The rows a field's input opens with belong to a string field, plain or
// rich text - the only ones rendered as a text area
check( 'apiSave sets a string field\'s inputsize', ( $afterSave['model']['name']['inputsize'] ?? null ) === 8 );
check( 'apiSave drops inputsize on a non-string field', isset( $afterSave['model']['price']['inputsize'] ) === false );
check( 'apiSave adds the new field', ( $afterSave['model']['photo']['width'] ?? null ) === 40 );
check( 'apiSave drops suffix on an image field', isset( $afterSave['model']['photo']['suffix'] ) === false );
// An image's file is uploaded only after the element exists, so a required
// image would make the type impossible to add an element to - the flag is
// dropped whatever was posted, and only for image fields
check( 'apiSave drops "required" on an image field', isset( $afterSave['model']['photo']['required'] ) === false );
check( 'apiSave sets a suffix on a non-boolean/image field', $afterSave['model']['price']['suffix'] === '€' );
check( 'apiSave drops suffix on a boolean field', isset( $afterSave['model']['active']['suffix'] ) === false );
check( 'apiSave drops suffix on an element field, which renders a select and no input', ( $afterSave['model']['ref']['elementType'] ?? null ) === 'testtype' && isset( $afterSave['model']['ref']['suffix'] ) === false );
check( 'apiSave silently drops a field with an unknown type', isset( $afterSave['model']['bogus'] ) === false );
check( 'apiSave never touches the "*" bucket', $afterSave['*'] === [ '*' => [] ] );
check( 'apiSave never touches real locale content', ( $afterSave['de_DE']['item1']['name'] ?? null ) === 'Hallo' );

// The order fields are posted in is the order they are written in, and the
// order every element form then renders them in - that is exactly what the
// editor's new ↑/↓ buttons change (see assets/elementtypes.js's _move())
check( 'apiSave keeps the posted field order', array_keys( $afterSave['model'] ) === [ 'name', 'photo', 'price', 'active', 'ref' ] );

$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [
		'price' 	=> [ 'type' => 'double' ],
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80 ],
		'active' 	=> [ 'type' => 'boolean' ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
	],
] );
$reorderRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $reorderRequest );
$afterReorder = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'apiSave rewrites the model in a reordered post\'s own order', array_keys( $afterReorder['model'] ) === [ 'price', 'name', 'active', 'photo' ] );
check( 'a reordered field keeps its own settings', ( $afterReorder['model']['name']['maxlength'] ?? null ) === 80 && ( $afterReorder['model']['photo']['width'] ?? null ) === 40 );
check( 'reordering never touches real locale content', ( $afterReorder['de_DE']['item1']['name'] ?? null ) === 'Hallo' );

// Switching a field's locale flag must migrate its stored value(s), not just
// the model - otherwise a stale per-locale value keeps shadowing the new
// '*' value forever (_cacheElement() merges locale data over '*' data)
$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [ 'name' => [ 'type' => 'string' ] ],
] );
$globalizeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $globalizeRequest );
check( 'apiSave succeeds when switching a field to global', $globalizeRequest['/nino/http/response']['statusCode'] === 200 );

$afterGlobalize = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'switching to global migrates the native locale\'s value into "*"', ( $afterGlobalize['*']['item1']['name'] ?? null ) === 'Hallo' );
check( 'switching to global removes the stale value from the locale bucket', isset( $afterGlobalize['de_DE']['item1']['name'] ) === false );

$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [ 'name' => [ 'type' => 'string', 'locale' => true ] ],
] );
$localizeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $localizeRequest );
check( 'apiSave succeeds when switching a field back to per-locale', $localizeRequest['/nino/http/response']['statusCode'] === 200 );

$afterLocalize = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'switching back to per-locale copies the value into every locale', $afterLocalize['de_DE']['item1']['name'] === 'Hallo' && $afterLocalize['en_US']['item1']['name'] === 'Hallo' );
check( 'switching back to per-locale removes the stale value from "*"', isset( $afterLocalize['*']['item1']['name'] ) === false );

$_POST['data'] = json_encode( [ 'uri' => 'testtype', 'title' => 'x', 'model' => [] ] );
foreach( [ '../escape', 'Has Uppercase', '1startswithdigit', '' ] as $badUri ) {
	$_POST['data'] = json_encode( [ 'uri' => $badUri, 'title' => 'x', 'model' => [] ] );
	$badSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Elements\Types::apiSave( $appData, $badSaveRequest );
	check( "apiSave rejects an invalid type uri ('$badUri')", $badSaveRequest['/nino/http/response']['statusCode'] === 400 );
}

$_POST['data'] = json_encode( [ 'uri' => 'brandnewtype', 'title' => 'Brand New', 'model' => [ 'headline' => [ 'type' => 'string' ] ] ] );
$createRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $createRequest );
check( 'apiCreate succeeds', $createRequest['/nino/http/response']['statusCode'] === 200 );

$created = \Nino\Filesystem::getFileContent( $appData, '/elements/brandnewtype.php', false );
check( 'apiCreate writes the title', $created['title'] === 'Brand New' );
check( 'apiCreate writes the model', ( $created['model']['headline']['type'] ?? null ) === 'string' );
check( 'apiCreate starts with an empty shell, no fabricated content', $created['*'] === [ '*' => [] ] );

$duplicateCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $duplicateCreateRequest );
check( 'apiCreate rejects an already-existing type', $duplicateCreateRequest['/nino/http/response']['statusCode'] === 409 );

// --- element reference fields ---
//
// The type a reference may point at is what both element forms build their
// select from. A reference nobody can satisfy would render as an empty,
// permanently unusable control - and look exactly like a type that simply has
// no elements yet - so the save is refused instead of the field being dropped

$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => 'nonexistenttype' ],
] ] );
$danglingCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $danglingCreateRequest );
check( 'apiCreate refuses a reference to a type that does not exist', $danglingCreateRequest['/nino/http/response']['statusCode'] === 400 );
check( '...and names the field and the missing type, so it can be fixed', str_contains( (string) ( $danglingCreateRequest['/nino/http/response']['body']['error'] ?? '' ), 'author' )
	&& str_contains( (string) ( $danglingCreateRequest['/nino/http/response']['body']['error'] ?? '' ), 'nonexistenttype' ) );
check( '...and writes no half-valid type file', \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', '' ) === '' );

$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '' ],
] ] );
$emptyRefCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $emptyRefCreateRequest );
check( 'apiCreate refuses a reference with no type at all', $emptyRefCreateRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'suffix' => 'ignored', 'options' => [ 'a', 'b' ], 'required' => true, 'locale' => true ],
] ] );
$refCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $refCreateRequest );
check( 'apiCreate accepts a reference to a real type', $refCreateRequest['/nino/http/response']['statusCode'] === 200 );

$refCreated = \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false );
check( 'the referenced type is persisted on the field', ( $refCreated['model']['author']['elementType'] ?? null ) === 'brandnewtype' );
check( 'a reference can be required and per-translation like any other field', ( $refCreated['model']['author']['required'] ?? null ) === true && ( $refCreated['model']['author']['locale'] ?? null ) === true );
check( 'a reference gets no suffix - it renders as a select, not an input', isset( $refCreated['model']['author']['suffix'] ) === false );
// Two selects fighting over one value: the reference list is the choice
check( 'a reference gets no fixed options list either', isset( $refCreated['model']['author']['options'] ) === false );

$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => 'gone-since' ],
] ] );
$danglingSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $danglingSaveRequest );
check( 'apiSave refuses the same dangling reference', $danglingSaveRequest['/nino/http/response']['statusCode'] === 400 );
check( '...leaving the stored model untouched', ( \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false )['model']['author']['elementType'] ?? null ) === 'brandnewtype' );

// The type editor asks "several elements?" and "how many?" as two controls;
// the model carries one int, and its mere presence is what makes the field
// multi-valued. So the fold has to happen server-side too - a hand-written or
// api-posted model goes through exactly the same rule
$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype' ],
	'tags' 		=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'multiple' => true, 'multipleMax' => '3' ],
	'crew' 		=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'multiple' => true, 'multipleMax' => '' ],
] ] );
$multiSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $multiSaveRequest );
check( 'apiSave accepts an element reference marked as a list', $multiSaveRequest['/nino/http/response']['statusCode'] === 200 );

$multiSaved = \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false );
check( 'the cap is folded onto the field as a plain int', ( $multiSaved['model']['tags']['multiple'] ?? null ) === 3 );
check( 'an unfilled cap becomes 0 - the list is wanted, the ceiling is not', ( $multiSaved['model']['crew']['multiple'] ?? null ) === 0 );
// Presence is the switch, so writing the key on a field nobody asked to be a
// list would silently turn every existing single reference into one
check( 'a reference nobody marked keeps no key at all', isset( $multiSaved['model']['author']['multiple'] ) === false );
check( '...which is exactly what the kernel reads it as',
	\Nino\Elements::isMultiElement( $multiSaved['model']['author'] ) === false
	&& \Nino\Elements::isMultiElement( $multiSaved['model']['tags'] ) === true );

// Only an element reference can be a list: the key on any other type would be
// a promise nothing enforces
$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype' ],
	'headline' => [ 'type' => 'string', 'multiple' => true, 'multipleMax' => '2' ],
] ] );
$strayMultiRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $strayMultiRequest );
check( 'a cap on a field that is not a reference is dropped',
	isset( \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false )['model']['headline']['multiple'] ) === false );

/*	A hand-written type file may spell the referenced type with the leading
	slash the rest of the framework accepts: \Nino\Elements builds its
	reference prefix as '/'. trim( elementType, '/' ). '/', and
	Types::referencedBy() compares the same way, so '/brandnewtype' is a
	working model that the kernel reads and the site renders. The panel
	compared the raw value against the bare type uris on disk, so it refused
	every save of that type - including one that changed nothing but the
	title - and named the type it had just been given as unknown.	*/
$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => '/brandnewtype' ],
	'editor' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype/' ],
] ] );
$slashRefRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $slashRefRequest );
check( 'a reference spelled with the slashes the kernel accepts is accepted here too', $slashRefRequest['/nino/http/response']['statusCode'] === 200 );

$slashSaved = \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false );
check( '...and is stored in the one spelling everything downstream reads',
	( $slashSaved['model']['author']['elementType'] ?? null ) === 'brandnewtype'
	&& ( $slashSaved['model']['editor']['elementType'] ?? null ) === 'brandnewtype' );
check( '...so the type knows it is referenced, which is what stops it being deleted',
	\Nino\Modules\Elements\Types::referencedBy( $appData, 'brandnewtype' ) === [ 'refholder.author', 'refholder.editor' ] );

// A slash is not a type either: '/' trimmed to nothing is the same "no type
// to point at" an empty value is, and still refused
$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '/' ],
] ] );
$slashOnlyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $slashOnlyRequest );
check( 'a reference of nothing but a slash is still no reference', $slashOnlyRequest['/nino/http/response']['statusCode'] === 400 );

// ...and the slashes do not smuggle an unknown type past the check
$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '/nonexistenttype/' ],
] ] );
$slashUnknownRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $slashUnknownRequest );
check( 'an unknown type is unknown however it is spelled', $slashUnknownRequest['/nino/http/response']['statusCode'] === 400 );

check( '"element" is offered as a field type by apiList', in_array( 'element', \Nino\Modules\Elements\Types::FIELD_TYPES, true ) === true );

// An image field may name the string field that holds its alt text per
// language. The name is kept only where it can be satisfied: a field of the
// model other than the image itself, plain string, written per language
$_POST['data'] = json_encode( [ 'uri' => 'alttype', 'title' => 'Alt Type', 'model' => [
	'photo' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'caption' ],
	'caption' 	=> [ 'type' => 'string', 'locale' => true ],
	'globalpic' => [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'globalcap' ],
	'globalcap' => [ 'type' => 'string' ],
	'richpic' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'richcap' ],
	'richcap' 	=> [ 'type' => 'string', 'locale' => true, 'html' => true ],
	'lostpic' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'nothere' ],
	'selfpic' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'selfpic' ],
	'imgpic' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'photo' ],
	'note' 			=> [ 'type' => 'string', 'locale' => true, 'alt' => 'caption' ],
	'blank' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => '  ' ],
] ] );
$altCreate = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $altCreate );
$altModel = \Nino\Filesystem::getFileContent( $appData, '/elements/alttype.php', false )['model'] ?? [];
check( 'an image field keeps its alt link to a plain string field written per language', $altCreate['/nino/http/response']['statusCode'] === 200 && ( $altModel['photo']['alt'] ?? null ) === 'caption' );
check( '...and loses it to a global field, a rich-text field, a field that is not there, itself, another image and nothing at all',
	array_filter( [ 'globalpic', 'richpic', 'lostpic', 'selfpic', 'imgpic', 'blank' ], static fn( string $key ): bool => array_key_exists( 'alt', $altModel[$key] ?? [ 'alt' => 'missing field' ] ) ) === [] );
check( '...and a field that is no image never carries one', array_key_exists( 'alt', $altModel['note'] ?? [ 'alt' => 1 ] ) === false && array_key_exists( 'alt', $altModel['caption'] ?? [ 'alt' => 1 ] ) === false );

// Saving the type again with its field renamed away loses the link: the model is checked as a whole each time
$_POST['data'] = json_encode( [ 'uri' => 'alttype', 'title' => 'Alt Type', 'model' => [
	'photo' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'caption' ],
	'caption' 	=> [ 'type' => 'string' ],
] ] );
$altSave = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiSave( $appData, $altSave );
check( 'a link whose target stopped being a per-language field is dropped on the next save', array_key_exists( 'alt', \Nino\Filesystem::getFileContent( $appData, '/elements/alttype.php', false )['model']['photo'] ?? [ 'alt' => 1 ] ) === false );

echo "\n";


// --- ElementTypes::apiDelete ----------------------------------------------
//
// The one action in this module that destroys content, so every guard around
// it is worth a check of its own: the typed confirmation, the reference that
// refuses the deletion outright, and the images that go with the elements.
// Removing the file by hand has none of these - which is the argument for
// having the action at all, not against it.

echo "ElementTypes::apiDelete\n";

$_POST['data'] = json_encode( [ 'uri' => 'deletable', 'title' => 'Deletable', 'model' => [
	'title' => [ 'type' => 'string' ],
	'photo' => [ 'type' => 'image' ],
] ] );
$deletableCreate = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $deletableCreate );

\Nino\Elements::insertElement( $appData, '/deletable/one', [ 'title' => 'One', 'photo' => 'elements/deletable/one.jpg' ], '*' );
\Nino\Elements::insertElement( $appData, '/deletable/two', [ 'title' => 'Two', 'photo' => '' ], '*' );

$deletableImage = \Nino\Filesystem::path( $appData, '/images/elements/deletable/one.jpg' );
@mkdir( dirname( $deletableImage ), 0777, true );
file_put_contents( $deletableImage, 'jpeg-bytes' );

$_POST['data'] = json_encode( [ 'uri' => 'deletable' ] );
$deletableGet = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiGet( $appData, $deletableGet );
// The form says what deleting costs before it is clicked, so the count has to
// travel with the type itself rather than being counted after the fact
check( 'apiGet reports how many elements the type holds', ( $deletableGet['/nino/http/response']['body']['elements'] ?? null ) === 2 );
check( '...and that nothing references it', ( $deletableGet['/nino/http/response']['body']['referencedBy'] ?? null ) === [] );

$_POST['data'] = json_encode( [ 'uri' => 'deletable', 'confirm' => 'deletabel' ] );
$mistypedDelete = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiDelete( $appData, $mistypedDelete );
check( 'apiDelete refuses a mistyped confirmation', $mistypedDelete['/nino/http/response']['statusCode'] === 400 );
check( '...and leaves the type file exactly where it was', \Nino\Filesystem::fileExists( $appData, '/elements/deletable.php' ) === true );

$_POST['data'] = json_encode( [ 'uri' => 'no-such-type', 'confirm' => 'no-such-type' ] );
$unknownDelete = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiDelete( $appData, $unknownDelete );
check( 'apiDelete 404s for a type that is not there', $unknownDelete['/nino/http/response']['statusCode'] === 404 );

// refholder.author points at brandnewtype (created above), so deleting that
// type would leave the reference pointing at nothing - and no later save of
// refholder could tell the difference
$_POST['data'] = json_encode( [ 'uri' => 'brandnewtype', 'confirm' => 'brandnewtype' ] );
$referencedDelete = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiDelete( $appData, $referencedDelete );
check( 'apiDelete refuses a type another type references', $referencedDelete['/nino/http/response']['statusCode'] === 409 );
check( '...naming the field that holds the reference', str_contains( (string) ( $referencedDelete['/nino/http/response']['body']['error'] ?? '' ), 'refholder.author' ) );
check( '...and the referenced type survives it', \Nino\Filesystem::fileExists( $appData, '/elements/brandnewtype.php' ) === true );

// The most destructive action in the module stands behind the same permission
// as the rest of the tab, and through the dispatcher rather than only when
// called directly - the ui never reaches apiDelete() any other way
check( 'types/delete is in the action map at all', isset( \Nino\Modules\Elements\Types::actions()['types/delete'] ) === true );

\Nino\Auth::insertUser( $appData, 'nodelete@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::loginUser( $appData, 'nodelete@example.com', 'correct horse battery staple' );
$_POST['action'] 	= 'types/delete';
$_POST['data'] 		= json_encode( [ 'uri' => 'deletable', 'confirm' => 'deletable' ] );
$forbiddenDelete 	= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $forbiddenDelete );
check( 'an account without the tab\'s permission is refused the deletion', $forbiddenDelete['/nino/http/response']['statusCode'] === 403 );
check( '...and the type is still there afterwards', \Nino\Filesystem::fileExists( $appData, '/elements/deletable.php' ) === true );
\Nino\Auth::deleteUser( $appData, 'nodelete@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$_POST['data'] = json_encode( [ 'uri' => 'deletable', 'confirm' => 'deletable' ] );
$goodDelete = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiDelete( $appData, $goodDelete );
check( 'a confirmed deletion goes through', $goodDelete['/nino/http/response']['statusCode'] === 200 );
// The count is what makes this line worth keeping, and log() - handed the posted
// body - never sees it: uri and confirm are all that is posted
check( '...and the activity log names how much went with the type', str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, 20 ) ), 'Delete Element Type /deletable with 2 element(s) and 1 image(s)' ) );
check( '...removing the type file', \Nino\Filesystem::fileExists( $appData, '/elements/deletable.php' ) === false );
check( '...and says what went with it', ( $goodDelete['/nino/http/response']['body']['elements'] ?? null ) === 2 && ( $goodDelete['/nino/http/response']['body']['images'] ?? null ) === 1 );
// An orphaned upload is not a safety net - nothing in the workbench can reach
// it again, it just sits in /images taking up room
check( '...taking the images the elements held with it', is_file( $deletableImage ) === false );
check( 'the type is gone from the list too', in_array( 'deletable', \Nino\Modules\Elements\Admin::types( $appData ), true ) === false );

$_POST['data'] = json_encode( [ 'uri' => 'deletable', 'confirm' => 'deletable' ] );
$repeatDelete = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiDelete( $appData, $repeatDelete );
check( 'deleting it a second time is a plain 404, not a crash', $repeatDelete['/nino/http/response']['statusCode'] === 404 );

echo "\n";


// --- ElementTypes: blocks and breaks, rename ------------------------

echo "ElementTypes - paragraphs and line breaks, renaming a field\n";

// The two flags that go with html: paragraphs and lists for rich text, line
// breaks for plain text - each kept only where it can mean something
$_POST['data'] = json_encode( [ 'uri' => 'flagtype', 'title' => 'Flags', 'model' => [
	'rich'		=> [ 'type' => 'string', 'html' => true, 'blocks' => true, 'breaks' => true ],
	'inline'	=> [ 'type' => 'string', 'html' => true ],
	'plain'		=> [ 'type' => 'string', 'blocks' => true, 'breaks' => true ],
	'number'	=> [ 'type' => 'integer', 'blocks' => true, 'breaks' => true ],
] ] );
$flagCreate = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Elements\Types::apiCreate( $appData, $flagCreate );
$flagModel = \Nino\Filesystem::getFileContent( $appData, '/elements/flagtype.php', false )['model'] ?? [];
check( 'blocks is kept on a rich text field, and breaks is dropped from it', ( $flagModel['rich'] ?? null ) === [ 'type' => 'string', 'html' => true, 'blocks' => true ] );
check( 'a rich field without blocks stays as it was', ( $flagModel['inline'] ?? null ) === [ 'type' => 'string', 'html' => true ] );
check( 'breaks is kept on a plain string field, blocks is dropped from it', ( $flagModel['plain'] ?? null ) === [ 'type' => 'string', 'breaks' => true ] );
check( 'neither means anything on any other type', ( $flagModel['number'] ?? null ) === [ 'type' => 'integer' ] );
unlink( \Nino\Filesystem::path( $appData, '/elements/flagtype.php' ) );

/*	A type with content in every bucket: the '*' defaults and an element, and
	a locale that is available, one that is not any more, and a default entry
	in a locale	*/
$typeFile = static fn( string $uri ): array|false => \Nino\Filesystem::getFileContent( $appData, '/elements/'. $uri. '.php', false );
$seedRenamer = static function() use ( &$appData ): void {
	\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', [
		'title'	=> 'Renamer',
		'model'	=> [
			'title'	=> [ 'type' => 'string', 'locale' => true ],
			'tags'	=> [ 'type' => 'array' ],
			'photo'	=> [ 'type' => 'image', 'width' => 10, 'height' => 10 ],
			'extra'	=> [ 'type' => 'string' ],
		],
		'*'			=> [ '*' => [ 'tags' => [ 'default' ] ], 'one' => [ 'tags' => [ 'a', 'b' ], 'photo' => 'one.jpg', 'extra' => 'kept' ] ],
		'de_DE'	=> [ '*' => [ 'title' => 'Vorgabe' ], 'one' => [ 'title' => 'Eins' ] ],
		'en_US'	=> [ 'one' => [ 'title' => 'One' ] ],
		'fr_FR'	=> [ 'one' => [ 'title' => 'Un' ] ],
	] );
};
$renamerModel = static fn( array $overrides = [] ): array => array_replace( [
	'title'	=> [ 'type' => 'string', 'locale' => true ],
	'tags'	=> [ 'type' => 'array' ],
	'photo'	=> [ 'type' => 'image', 'width' => 10, 'height' => 10 ],
	'extra'	=> [ 'type' => 'string' ],
], $overrides );
$saveRenamer = static function( array $model, mixed $renames ) use ( &$appData ): array {
	return callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [ 'uri' => 'renamer', 'title' => 'Renamer', 'model' => $model, 'renames' => $renames ] );
};

$seedRenamer();
$renamed = [ 'headline' => [ 'type' => 'string', 'locale' => true ], 'tags' => [ 'type' => 'array' ], 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ], 'extra' => [ 'type' => 'string' ] ];
[ $status, $body ] = $saveRenamer( $renamed, [ 'title' => 'headline' ] );
$after = $typeFile( 'renamer' );
check( 'a rename is saved', $status === 200 && ( $body['renamed'] ?? null ) === [ 'title' => 'headline' ] && array_keys( $after['model'] ) === [ 'headline', 'tags', 'photo', 'extra' ] );
check( 'every value moves to the new key: the elements of every bucket, a locale that is not available any more among them',
	( $after['de_DE']['one'] ?? null ) === [ 'headline' => 'Eins' ] && ( $after['en_US']['one'] ?? null ) === [ 'headline' => 'One' ] && ( $after['fr_FR']['one'] ?? null ) === [ 'headline' => 'Un' ] );
check( '...and the defaults of a locale, which are an entry like any other', ( $after['de_DE']['*'] ?? null ) === [ 'headline' => 'Vorgabe' ] );
check( '...the fields that were not renamed stay where they were, in the order they were', ( $after['*']['one'] ?? null ) === [ 'tags' => [ 'a', 'b' ], 'photo' => 'one.jpg', 'extra' => 'kept' ] && ( $after['*']['*'] ?? null ) === [ 'tags' => [ 'default' ] ] );
check( 'the old key is gone from a fresh read, and the new one is there', array_key_exists( 'title', $after['de_DE']['one'] ) === false && array_key_exists( 'headline', $after['de_DE']['one'] ) === true );
check( 'the log names the rename', \Nino\Modules\Elements\Types::log( 'types/save', [ 'uri' => 'renamer', 'renames' => [ 'title' => 'headline', 'tags' => 'labels' ] ] ) === 'Edit Element Type /renamer (renamed title to headline, tags to labels)'
	&& \Nino\Modules\Elements\Types::log( 'types/save', [ 'uri' => 'renamer' ] ) === 'Edit Element Type /renamer' );

// Renamed in the same save as its shape: the old model is read as it is now called
$seedRenamer();
[ $status ] = $saveRenamer( [ 'headline' => [ 'type' => 'string' ] ] + array_diff_key( $renamerModel(), [ 'title' => 1 ] ), [ 'title' => 'headline' ] );
$after = $typeFile( 'renamer' );
check( 'a rename and a switch to global in one save migrate the values under the new key',
	$status === 200 && ( $after['*']['one']['headline'] ?? null ) === 'Eins' && isset( $after['en_US']['one']['headline'] ) === false && isset( $after['de_DE']['one']['title'] ) === false );

// A swap and a chain are read against the type as it was
$seedRenamer();
[ $status ] = $saveRenamer( [ 'extra' => [ 'type' => 'array' ], 'tags' => [ 'type' => 'string' ] ], [ 'tags' => 'extra', 'extra' => 'tags' ] );
$after = $typeFile( 'renamer' );
check( 'two fields can swap their names', $status === 200 && ( $after['*']['one']['extra'] ?? null ) === [ 'a', 'b' ] && ( $after['*']['one']['tags'] ?? null ) === 'kept' );
$seedRenamer();
[ $status ] = $saveRenamer( [ 'tags' => [ 'type' => 'string' ], 'extra' => [ 'type' => 'array' ], 'last' => [ 'type' => 'string' ] ], [ 'extra' => 'last', 'tags' => 'extra' ] );
$after = $typeFile( 'renamer' );
check( 'a chain, a to b and b to c, moves each value once', $status === 200 && ( $after['*']['one']['last'] ?? null ) === 'kept' && ( $after['*']['one']['extra'] ?? null ) === [ 'a', 'b' ] && array_key_exists( 'tags', $after['*']['one'] ) === false );

// Refusals: nothing is written
$seedRenamer();
$before = $typeFile( 'renamer' );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'extra' => 'tags' ] );
check( 'a name another field keeps is a 409 that names it', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_collision' && ( $body['params'] ?? [] ) === [ 'tags' ] );
[ $status, $body ] = $saveRenamer( [ 'both' => [ 'type' => 'string' ] ], [ 'extra' => 'both', 'tags' => 'both' ] );
check( 'two fields renamed to one name are a 409 too', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_collision' );
// Values a removed field left behind: the editor never deletes data
$left = $typeFile( 'renamer' );
$left['*']['one']['removed'] = 'stale';
\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', $left );
[ $status, $body ] = $saveRenamer( [ 'removed' => [ 'type' => 'string' ] ] + array_diff_key( $renamerModel(), [ 'extra' => 1 ] ), [ 'extra' => 'removed' ] );
check( 'a name that still holds the values of a removed field is a 409: they would turn up in elements that never had them', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_values' && ( $body['params'] ?? [] ) === [ 'removed' ] );
$seedRenamer();
$twoImagesModel = $renamerModel( [ 'second' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ] ] );
\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', [ 'model' => $twoImagesModel ] + $typeFile( 'renamer' ) );
[ $status, $body ] = $saveRenamer( $twoImagesModel, [ 'photo' => 'second', 'second' => 'photo' ] );
check( 'a swap of two image fields is a 409: an upload\'s file is named after the field, so they would overwrite each other', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_image' );
[ $status, $body ] = $saveRenamer( [ 'pic' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ] ] + array_diff_key( $twoImagesModel, [ 'photo' => 1 ] ), [ 'photo' => 'pic' ] );
check( '...a plain rename of an image field is fine, and the value - the file name - moves with it', $status === 200 && ( $typeFile( 'renamer' )['*']['one']['pic'] ?? null ) === 'one.jpg' );

$seedRenamer();
$before = $typeFile( 'renamer' );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'nothere' => 'title' ] );
check( 'a rename of a field the type does not have is a 400', $status === 400 && ( $body['code'] ?? '' ) === 'types_rename_unknown' && ( $body['params'] ?? [] ) === [ 'nothere' ] );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'title' => 'gone' ] );
check( '...so is a rename to a name the saved model does not have', $status === 400 && ( $body['code'] ?? '' ) === 'types_rename_missing' );
foreach( [ 'title', [ 'title' => 5 ], [ 'title' => '' ], [ 'title' => [ 'x' ] ], [ '' => 'x' ] ] as $malformed ) {
	[ $status, $body ] = $saveRenamer( $renamerModel(), $malformed );
	check( 'renames of '. json_encode( $malformed ). ' are a 400, not a guess', $status === 400 && ( $body['code'] ?? '' ) === 'types_renames' );
}
check( 'none of the refusals wrote anything', $typeFile( 'renamer' ) === $before );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'title' => 'title' ] );
check( 'a rename to the name a field has is none', $status === 200 && ( $body['renamed'] ?? null ) === [] && $typeFile( 'renamer' )['de_DE']['one'] === [ 'title' => 'Eins' ] );

// An old client sends no renames
$seedRenamer();
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [ 'uri' => 'renamer', 'title' => 'Renamer', 'model' => $renamerModel() ] );
check( 'a save without renames behaves as before', $status === 200 && $typeFile( 'renamer' )['de_DE']['one'] === [ 'title' => 'Eins' ] );

// An image's alt link follows the rename of the field it points at: the form
// names it the way it was saved while that field is in the form
$altModel = [ 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10, 'alt' => 'photoAlt' ], 'photoAlt' => [ 'type' => 'string', 'locale' => true ] ];
\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', [ 'title' => 'Renamer', 'model' => $altModel, '*' => [ '*' => [] ], 'de_DE' => [ 'one' => [ 'photoAlt' => 'Ein Bild' ] ] ] );
[ $status ] = $saveRenamer( [ 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10, 'alt' => 'photoAlt' ], 'altText' => [ 'type' => 'string', 'locale' => true ] ], [ 'photoAlt' => 'altText' ] );
$after = $typeFile( 'renamer' );
check( 'renaming the field that holds an image\'s alt text keeps the link, under the new name, and the values move', $status === 200 && ( $after['model']['photo']['alt'] ?? null ) === 'altText' && ( $after['de_DE']['one'] ?? null ) === [ 'altText' => 'Ein Bild' ] );
[ $status ] = $saveRenamer( [ 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10, 'alt' => 'altText' ], 'altText' => [ 'type' => 'string', 'locale' => true ] ], [] );
check( '...and a link already at the new name is left alone', $status === 200 && ( $typeFile( 'renamer' )['model']['photo']['alt'] ?? null ) === 'altText' );

// What the rename cannot reach is reported, not changed
$seedRenamer();
mkdir( $sandbox. '/private/templates', 0777, true );
file_put_contents( $sandbox. '/private/templates/page-renamer.tpl', '[elements /renamer]<h1>[[title]]</h1>[/elements]' );
$appData['/nino/auth/roles']['renamer-editor'] = [ 'label' => 'Editor', 'perms' => [ '/_admin/elements/renamer/update/title' ] ];
\Nino\Html::addFills( $appData, [ '/_admin/elements/field/renamer/title' => 'Headline' ], '*' );
[ $status, $body ] = $saveRenamer( $renamed, [ 'title' => 'headline' ] );
$references = $body['references'] ?? [];
check( 'the save reports the template, the role and the label text that still use the old name',
	$status === 200
	&& in_array( [ 'kind' => 'template', 'name' => 'page-renamer.tpl', 'field' => 'title' ], $references, true ) === true
	&& in_array( [ 'kind' => 'role', 'name' => 'renamer-editor', 'field' => 'title' ], $references, true ) === true
	&& in_array( [ 'kind' => 'label', 'name' => '/_admin/elements/field/renamer/title', 'field' => 'title' ], $references, true ) === true );
check( '...and has changed none of them', str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-renamer.tpl' ), '[[title]]' ) === true && $appData['/nino/auth/roles']['renamer-editor']['perms'] === [ '/_admin/elements/renamer/update/title' ] );
unlink( $sandbox. '/private/templates/page-renamer.tpl' );
rmdir( $sandbox. '/private/templates' );
unset( $appData['/nino/auth/roles']['renamer-editor'] );

// Permission
\Nino\Auth::insertUser( $appData, 'norename@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::loginUser( $appData, 'norename@example.com', 'correct horse battery staple' );
$seedRenamer();
$_POST['action'] = 'types/save';
$_POST['data'] 	 = json_encode( [ 'uri' => 'renamer', 'title' => 'Renamer', 'model' => $renamed, 'renames' => [ 'title' => 'headline' ] ] );
$forbiddenRename = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $forbiddenRename );
check( 'an account without the tab\'s permission is refused a rename, and nothing moves', $forbiddenRename['/nino/http/response']['statusCode'] === 403 && ( $typeFile( 'renamer' )['de_DE']['one'] ?? null ) === [ 'title' => 'Eins' ] );
\Nino\Auth::deleteUser( $appData, 'norename@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Dev\Restore ----------------------------------------------------------

echo "Restore - reads Backup's output independently, doesn't call into _admin/Admin.php\n";

mkdir( $sandbox. '/_admin', 0777, true );
mkdir( $sandbox. '/_admin', 0777, true );

$appData['/nino/auth/maxtries'] 	= 5;
$appData['/nino/auth/cooldown'] 	= 3600;
$appData['/nino/auth/user'] 			= $appData['/nino/auth/user'] ?? [];
\Nino\Auth::insertUser( $appData, 'admin@example.com', 'correct horse battery staple', [ '/*' ] );
\Nino\Auth::loginUser( $appData, 'admin@example.com', 'correct horse battery staple' );
// The guard checks above already ran today's backup, before this account
// existed - drop it so the one restored below carries the account
array_map( 'unlink', glob( $sandbox. '/private/.backups/*.php' ) ?: [] );

$guardOk = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::guard( $appData, $guardOk ); // bootstraps + creates today's backup as a side effect

check( 'Backup::maybeRun (via Admin::guard) writes the archive key into the private directory', is_file( $sandbox. '/private/.auth/backup-key.php' ) === true );

check( 'the archives land under the private directory, not in a tool folder', is_dir( $sandbox. '/private/.backups' ) === true );
check( 'no random backup directory is generated any more', isset( $appData['/nino/backup/dir'] ) === false );

// Regression: an install that already had Backup running (key already in
// config.php) before this out-of-config copy existed must still get one
// written on the next admin request - not just on the very first bootstrap
unlink( $sandbox. '/private/.auth/backup-key.php' );
\Nino\Admin\Admin::guard( $appData, $guardOk );
check( 'Backup::maybeRun re-creates a missing key copy on an already-bootstrapped install', is_file( $sandbox. '/private/.auth/backup-key.php' ) === true );

// Restore has to find the archives on an installation that is nothing but a
// kernel and the workbench shell - a broken one is what reaches for it, and by
// this line the test process is the opposite of that: a sandbox full of
// fixtures, accounts, panels and an appData every check above has written to.
// Only a subprocess can say what a bare boot sees. The driver below requires
// \Nino.php and _admin/Admin.php and nothing else, and asks the panel for its
// list: what it answers, it found on disk under private/, not in anything this
// file put there. That is why the panel keeps its own copy of the archive
// directory and the key path instead of reaching for \Nino\Modules\Backups'
// private constants (see its _backupDirs() and _key()).
//
// 'editorLoaded' is what is left of the check this driver was written for, when
// Backup was \Nino\Editor\Backup in a tool of its own: the class has no name in
// this repository any more, so class_exists() on it answers false whatever the
// driver does. What the assertion still catches is a driver that did not answer
// at all - a boot that died leaves no key to read, and the check fails.
$standaloneDriver = $sandbox. '/restore-standalone.php';
file_put_contents( $standaloneDriver, '<?php
declare(strict_types=1);
require '. var_export( __DIR__. '/../_nino/Nino.php', true ). ';
require '. var_export( __DIR__. '/../_admin/Admin.php', true ). ';
set_error_handler( function() { return true; } );
$appData = [ "./nino/uid" => '. var_export( $sandbox, true ). ' ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"]				= '. var_export( $sandbox, true ). ';
$appData["./nino/filesystem/configpath"]	= '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/contentpath"] = '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/publicpath"]	= '. var_export( $sandbox. '/public', true ). ';
$appData["/nino/dir"] = "";
\Nino\AppData::init( $appData );
echo json_encode( [
	"editorLoaded"	=> class_exists( "\\Nino\\Editor\\Backup", false ),
	"dates"					=> \Nino\Modules\Backups\Admin::dates( $appData ),
] );
' );

$standalone = json_decode( (string) shell_exec( 'php '. escapeshellarg( $standaloneDriver ). ' 2>/dev/null' ), true ) ?? [];

check( 'the standalone driver boots on the kernel and the shell alone, with none of this file\'s fixtures', ( $standalone['editorLoaded'] ?? true ) === false );
check( '...and still finds the archives from there', in_array( date( 'Y-m-d' ), $standalone['dates'] ?? [], true ) === true );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiList( $appData, $listRequest );
$dates = $listRequest['/nino/http/response']['body']['dates'] ?? [];
check( 'apiList finds today\'s backup without reading config.php\'s own copy of the dir/key', count( $dates ) === 1 && $dates[0] === date( 'Y-m-d' ) );

/*	"Back up now": one more archive, named by date and time, beside the daily
	one. Today's Y-m-d.php is the state before the day's work - the first
	authenticated request wrote it - and a click in the afternoon must not
	replace it. Done before the restores below, which write snapshots of their
	own into the same directory and would change what the checks above count	*/
$nowDir			= $sandbox. '/private/.backups';
$timedFiles	= static fn(): array => array_map( 'basename', glob( $nowDir. '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9].php' ) ?: [] );
$dailyFile	= $nowDir. '/'. date( 'Y-m-d' ). '.php';
$dailyBytes	= (string) file_get_contents( $dailyFile );

$nowId = \Nino\Modules\Backups::now( $appData );
check( 'Backups::now writes an archive named by date and time', is_string( $nowId ) === true && preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}$/', (string) $nowId ) === 1 && is_file( $nowDir. '/'. $nowId. '.php' ) === true );
check( '...behind the same stub a daily archive has, and no temporary file beside it', str_starts_with( (string) file_get_contents( $nowDir. '/'. $nowId. '.php' ), \Nino\Admin\Recovery::STUB_PREFIX ) && glob( $nowDir. '/*.tmp' ) === [] );
check( '...and leaves today\'s daily archive byte for byte as it was', (string) file_get_contents( $dailyFile ) === $dailyBytes );
check( 'dates() lists it before today\'s date, and an archive of its own is not a snapshot', \Nino\Modules\Backups\Admin::dates( $appData ) === [ $nowId, date( 'Y-m-d' ) ] );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiList( $appData, $listRequest );
check( 'apiList carries the dates and whether backups are on', ( $listRequest['/nino/http/response']['body']['dates'] ?? [] ) === [ $nowId, date( 'Y-m-d' ) ] && ( $listRequest['/nino/http/response']['body']['enabled'] ?? null ) === true );

$idPattern = \Nino\Modules\Backups\Admin::ID_PATTERN;
check( 'ID_PATTERN takes a date and time, and still turns away a time cut short and a path',
	preg_match( $idPattern, '2026-10-02-170512' ) === 1 && preg_match( $idPattern, '2026-10-02' ) === 1 && preg_match( $idPattern, 'pre-restore-2026-10-02-170512' ) === 1
	&& preg_match( $idPattern, '2026-10-02-1705' ) === 0 && preg_match( $idPattern, '../x' ) === 0 && preg_match( $idPattern, '2026-10-02-170512/../x' ) === 0 );

// Retention: the days the daily archives have, and no more than ten of these.
// A snapshot and a daily archive are not touched by it
$staleTimed	= date( 'Y-m-d', strtotime( '-20 days' ) ). '-120000';
$keptDaily	= date( 'Y-m-d', strtotime( '-3 days' ) );
$oldSnap		= 'pre-restore-'. date( 'Y-m-d', strtotime( '-20 days' ) ). '-120000';
foreach( [ $staleTimed, $keptDaily, $oldSnap ] as $name )
	file_put_contents( $nowDir. '/'. $name. '.php', 'x' );
for( $hour = 1; $hour <= 12; $hour++ )
	file_put_contents( $nowDir. '/'. date( 'Y-m-d', strtotime( '-1 day' ) ). '-'. sprintf( '%02d', $hour ). '0000.php', 'x' );

$pruneId = \Nino\Modules\Backups::now( $appData );
$timedNow = $timedFiles();
check( 'an archive of this kind older than the retention days is pruned', in_array( $staleTimed. '.php', $timedNow, true ) === false );
check( '...and only the newest ten are kept - the new one among them', count( $timedNow ) === 10 && in_array( $pruneId. '.php', $timedNow, true ) === true && in_array( $nowId. '.php', $timedNow, true ) === true
	&& in_array( date( 'Y-m-d', strtotime( '-1 day' ) ). '-010000.php', $timedNow, true ) === false && in_array( date( 'Y-m-d', strtotime( '-1 day' ) ). '-120000.php', $timedNow, true ) === true );
check( '...the daily archives and the snapshots are left to their own sweeps', is_file( $nowDir. '/'. $keptDaily. '.php' ) === true && is_file( $dailyFile ) === true && is_file( $nowDir. '/'. $oldSnap. '.php' ) === true );

// The panel's door
$nowCall = static function( array &$appData ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Backups\Admin::apiNow( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ?? null ];
};
$countBefore = count( $timedFiles() );
[ $status, $body ] = $nowCall( $appData );
check( 'apiNow answers the id of the archive it wrote', $status === 200 && is_string( $body['id'] ?? null ) === true && is_file( $nowDir. '/'. $body['id']. '.php' ) === true );
check( '...and the activity log names the action without calling it twice', \Nino\Modules\Backups\Admin::log( 'backups/now', [] ) === 'Create Backup' && \Nino\Modules\Backups\Admin::log( 'backups/list', [] ) === '' );

$appData['/nino/admin/backups'] = false;
[ $status ] = $nowCall( $appData );
$offFiles = count( $timedFiles() );
$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiList( $appData, $listRequest );
check( 'apiNow is 409 while backups are switched off, and writes nothing', $status === 409 && $offFiles === count( $timedFiles() ) && ( $listRequest['/nino/http/response']['body']['enabled'] ?? null ) === false );
unset( $appData['/nino/admin/backups'] );

// An archive that cannot be written: an encryption key that is none
$goodKey = $appData['/nino/backup/key'];
$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => 'not a key' ] + $config );
$timedBefore = $timedFiles();
$tempBefore = count( glob( sys_get_temp_dir(). '/ninobackup*' ) ?: [] );
[ $status, $body ] = $nowCall( $appData );
check( 'a failing run is a 500 with its reason, writes no archive and leaves no temporary file', $status === 500 && is_string( $body['error'] ?? null ) === true && $timedFiles() === $timedBefore
	&& count( glob( sys_get_temp_dir(). '/ninobackup*' ) ?: [] ) === $tempBefore && glob( $nowDir. '/*.tmp' ) === [] );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => $goodKey ] + $config );
$appData['/nino/backup/key'] = $goodKey;
[ $status ] = $nowCall( $appData );
check( '...and the next one, with the key back, is fine', $status === 200 );

\Nino\Auth::logoutUser( $appData );
$timedBefore = $timedFiles();
[ $status ] = $nowCall( $appData );
check( 'apiNow is 401 logged out', $status === 401 && $timedFiles() === $timedBefore );
\Nino\Auth::insertUser( $appData, 'usersmanageonly@example.com', 'correct horse battery staple', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'usersmanageonly@example.com', 'correct horse battery staple' );
[ $status ] = $nowCall( $appData );
check( '...403 without the backups permission', $status === 403 && $timedFiles() === $timedBefore );
\Nino\Auth::deleteUser( $appData, 'usersmanageonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// The restores below count the archives; theirs are the ones to count
foreach( $timedFiles() as $name )
	unlink( $nowDir. '/'. $name );
foreach( [ $keptDaily, $oldSnap ] as $name )
	unlink( $nowDir. '/'. $name. '.php' );

// Simulate config.php DATA corruption (not a syntax error) - eg. a wrecked user record -
// the scenario Restore exists for. A genuine syntax error is out of scope (see Admin.php docblock).
$beforeCorruption = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$corrupted = $beforeCorruption;
$corrupted['/nino/auth/user'] = [];
\Nino\Filesystem::putFileContent( $appData, '/config.php', $corrupted );
check( 'the simulated corruption actually wiped the user record', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user'] === [] );

// What a restore leaves in the system's temp directory: tempnam() creates the
// file it names, and both the restore and its safety snapshot went on to work
// on that name plus a suffix - the file tempnam() made was never removed, two
// per restore, for the life of the server
$tempFiles	= static fn(): int => count( glob( sys_get_temp_dir(). '/ninorestore*' ) ?: [] ) + count( glob( sys_get_temp_dir(). '/ninosnapshot*' ) ?: [] );
$tempBefore	= $tempFiles();

$_POST['data'] = json_encode( [ 'date' => $dates[0] ] );
$restoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $restoreRequest );
check( 'apiRestore reports success', ( $restoreRequest['/nino/http/response']['body']['ok'] ?? false ) === true );

$afterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the wrecked user record is back after restore', isset( $afterRestore['/nino/auth/user']['admin@example.com'] ) === true );

$backupDir = $sandbox. '/private/.backups';
check( 'a pre-restore safety snapshot of the (corrupted) state was made first', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 1 );

/*	...and that snapshot is a way back out, which is what the panel's confirm
	text and both manuals promise. It used to match nothing: not the list,
	not apiRestore(), not recovery.php - so "a wrong pick can itself be
	undone" was true of a file only ssh could reach. It is also the one
	archive nothing ever pruned, so every restore a project did stayed on
	disk as a full encrypted copy, for good	*/
$snapshotId = basename( ( glob( $backupDir. '/pre-restore-*.php' ) ?: [] )[0], '.php' );
check( 'the snapshot is offered, after the dated backups rather than among them', in_array( $snapshotId, \Nino\Modules\Backups\Admin::dates( $appData ), true ) === true
	&& array_key_last( \Nino\Modules\Backups\Admin::dates( $appData ) ) === array_search( $snapshotId, \Nino\Modules\Backups\Admin::dates( $appData ), true ) );

$_POST['data'] = json_encode( [ 'date' => $snapshotId ] );
$undoRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $undoRequest );
check( 'restoring it undoes the restore', ( $undoRequest['/nino/http/response']['body']['ok'] ?? false ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user']['admin@example.com'] ) === false );

// Undoing wrote one of its own, so there are two now - and the bound is what
// keeps that from growing with every restore the project ever does
for( $i = 0; $i < 4; $i++ ) {
	touch( $backupDir. '/pre-restore-2020-01-0'. $i. '-120000.php' );
}
$_POST['data'] = json_encode( [ 'date' => $snapshotId ] );
$boundRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $boundRequest );
check( 'a restore keeps the newest three snapshots and drops the rest', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 3 );

check( 'three restores left nothing behind in the temp directory - neither their own file nor the snapshot\'s', $tempFiles() === $tempBefore );

/*	An archive that decrypts but is not one: a backup file somebody truncated
	or replaced, encrypted with the project's own key so that it passes the
	one check before unpacking. PharData threw out of restore(), which the
	panel answered as a 500 with nothing said - and the staging directory and
	the archive it had written stayed in the temp directory as well. Through
	a try/catch so the old answer is a failed check rather than the end of
	this suite	*/
$backupKey = base64_decode( substr( (string) file_get_contents( $sandbox. '/private/.auth/backup-key.php' ), strlen( "<?php http_response_code(403); exit; return '" ), -strlen( "';\n" ) ), true );
$garbageIv = random_bytes( 12 ); $garbageTag = '';
$garbage	 = openssl_encrypt( 'this is not a tar.gz', 'aes-256-gcm', (string) $backupKey, OPENSSL_RAW_DATA, $garbageIv, $garbageTag );
file_put_contents( $backupDir. '/2020-01-01.php', "<?php http_response_code(403); exit; return '". base64_encode( $garbageIv. $garbageTag. $garbage ). "';\n" );

$_POST['data'] = json_encode( [ 'date' => '2020-01-01' ] );
$garbageRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$garbageOutcome = ( static function() use ( &$appData, &$garbageRequest ): string {
	try {
		\Nino\Modules\Backups\Admin::apiRestore( $appData, $garbageRequest );
		return 'answered';
	}
	catch( \Throwable $e ) {
		return $e::class;
	}
} )();

check( 'a backup that is not an archive is answered, not thrown', $garbageOutcome === 'answered' && $garbageRequest['/nino/http/response']['statusCode'] === 500
	&& str_contains( (string) ( $garbageRequest['/nino/http/response']['body']['error'] ?? '' ), 'could not be unpacked' ) === true );
check( '...and leaves nothing behind either - the staging directory included', $tempFiles() === $tempBefore );

unlink( $backupDir. '/2020-01-01.php' );

/*	config.php is the one file every request reads at boot, and a restore
	replaces it. Written in place, a request booting mid-write read a
	half-written file - an include of a truncated var_export either fatals or
	returns something that is not an array, and AppData::init() answers that
	with "config.php exists but did not return an array" for everybody until
	the write finished. Written beside it and renamed over it, under the lock
	every other writer of that file takes, a reader sees one file or the
	other. Three things, each measured on the files rather than read off the
	source: the file that stands there afterwards is another file (a new
	inode, nothing temporary left beside it), the file does not change while
	somebody else holds the lock, and the module callback below runs with
	the extracted backup in hand	*/
$restoreSeen = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/restore', static function( array &$appData, array &$args ) use ( &$restoreSeen ): void {
	// The first restore is the one under test; the lock experiment below
	// runs the write step once more, with an archive of one file
	if( $restoreSeen !== [] )
		return;
	$staging = (string) ( $args['staging'] ?? '' );
	$restoreSeen = [
		'dataDir'		=> $args['dataDir'] ?? null,
		'staged'		=> $staging !== '' && is_dir( $staging ) ? count( glob( $staging. '/*' ) ?: [] ) : 0,
	];
} );

$configFile		= \Nino\Filesystem::getConfigPath( $appData ). '/config.php';
$inodeBefore	= fileinode( $configFile );

// Back to the state the checks below read
$_POST['data'] = json_encode( [ 'date' => $dates[0] ] );
$backRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $backRequest );
clearstatcache();

check( 'a restore replaces config.php with another file rather than writing into it, and leaves nothing temporary beside it',
	( $backRequest['/nino/http/response']['body']['ok'] ?? false ) === true
	&& fileinode( $configFile ) !== $inodeBefore
	&& glob( $configFile. '.*.tmp' ) === [] );

/*	The lock. Held from a child process, because flock() is per handle and a
	lock this process already holds is re-entered rather than waited for (see
	Filesystem::lockFile()); the child watches config.php's inode for the
	second and a half it holds the lock and says afterwards whether the file
	changed under it. Driven at the write step itself - _restoreFrom(), with
	an archive of one file - because the safety snapshot a whole restore
	takes first waits for the same lock on its own account, and would hide a
	write step that does not. The lock file's name is what lockFile()
	derives it from	*/
$lockKey		= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, '/config.php' ] );
$lockPath		= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $lockKey ). '.lock';
\Nino\Filesystem::lockFile( $appData, '/config.php' );
\Nino\Filesystem::unlockFile( $appData, '/config.php' );

$configBefore	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$configMarked	= $configBefore + [ '/nino/restore/marker' => 'written after the lock was released' ];
$archiveBase	= sys_get_temp_dir(). '/ninotest-lock-'. bin2hex( random_bytes( 6 ) );
$archive			= new PharData( $archiveBase. '.tar' );
$archive->addFromString( 'config.php', "<?php\nreturn ". var_export( $configMarked, true ). ";\n" );
$archive->compress( Phar::GZ );
unset( $archive );
$gz = (string) file_get_contents( $archiveBase. '.tar.gz' );
@unlink( $archiveBase. '.tar' );
@unlink( $archiveBase. '.tar.gz' );

$watcher = 'set_error_handler( function() { return true; } ); $lock = fopen( $argv[1], "c" ); flock( $lock, LOCK_EX ); $was = fileinode( $argv[2] ); $changed = false; echo "held\n";'
	. ' for( $i = 0; $i < 75; $i++ ) { usleep( 20000 ); clearstatcache( true, $argv[2] ); if( fileinode( $argv[2] ) !== $was ) $changed = true; }'
	. ' flock( $lock, LOCK_UN ); echo $changed === true ? "changed\n" : "unchanged\n";';
$holder = proc_open( [ PHP_BINARY, '-r', $watcher, $lockPath, $configFile ], [ 1 => [ 'pipe', 'w' ] ], $holderPipes );
$held		= is_resource( $holder ) === true && trim( (string) fgets( $holderPipes[1] ) ) === 'held';

$tmpBase	= (string) tempnam( sys_get_temp_dir(), 'ninotest' );
$tmpGz		= $tmpBase. '.tar.gz';
$staging	= sys_get_temp_dir(). '/ninotest-staging-'. bin2hex( random_bytes( 6 ) );
$written	= ( new ReflectionMethod( '\Nino\Modules\Backups\Admin', '_restoreFrom' ) )->invokeArgs( null, [ &$appData, $gz, $tmpGz, $staging ] );
$underLock = is_resource( $holder ) === true ? trim( (string) stream_get_contents( $holderPipes[1] ) ) : '';
if( is_resource( $holder ) === true ) { fclose( $holderPipes[1] ); proc_close( $holder ); }
@unlink( $tmpBase );
@unlink( $tmpGz );
if( is_dir( $staging ) === true )
	\Nino\Filesystem::removeDir( $staging );
clearstatcache();

check( '...and under the lock every other writer of that file takes: while another process holds it the file does not change, and once the lock is gone the restored file is what stands there',
	$held === true && $written === true && $underLock === 'unchanged'
	&& ( ( include $configFile )['/nino/restore/marker'] ?? null ) === 'written after the lock was released' );

// The marker was the experiment's, not the project's
\Nino\Filesystem::putFileContent( $appData, '/config.php', $configBefore );

// And the restored file is the one a reader gets afterwards - the check
// above is about how it is written, this one that it was
check( 'the restored config.php is a readable array', is_array( ( static function() use ( $appData ): mixed {
	return include \Nino\Filesystem::getConfigPath( $appData ). '/config.php';
} )() ) === true );

$_POST['data'] = json_encode( [ 'date' => '2020-01-01' ] );
$unknownDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $unknownDateRequest );
check( 'restoring a date with no matching backup 404s', $unknownDateRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'date' => 'not-a-date' ] );
$badDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $badDateRequest );
check( 'restoring a malformed date is rejected before touching the filesystem', $badDateRequest['/nino/http/response']['statusCode'] === 400 );

/*	An archive made on request is one to restore like any other, and it holds
	the state of its own moment: the daily one the state before the day's work
	- which is what a click in the afternoon must not have replaced	*/
$stateKey = '/nino/restore/state';
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ $stateKey => 'afternoon' ] + \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) );
$afternoonId = \Nino\Modules\Backups::now( $appData );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ $stateKey => 'evening' ] + \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) );

$_POST['data'] = json_encode( [ 'date' => $afternoonId ] );
$timedRestore = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $timedRestore );
check( 'restoring an archive made on request gives the state of that moment', ( $timedRestore['/nino/http/response']['body']['ok'] ?? false ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[$stateKey] ?? null ) === 'afternoon' );

$_POST['data'] = json_encode( [ 'date' => date( 'Y-m-d' ) ] );
$dailyRestore = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $dailyRestore );
check( '...and the daily archive of today still gives the state before it', ( $dailyRestore['/nino/http/response']['body']['ok'] ?? false ) === true
	&& array_key_exists( $stateKey, \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );
unlink( $backupDir. '/'. $afternoonId. '.php' );

echo "\n";


// --- Restore - a module merges its own files through the callback ----------

echo "Restore - a module merges its own data files through /nino/admin/restore\n";

// Restore itself carries no knowledge of any module's data: a module that
// keeps files under data/ registers '/nino/admin/restore' in its init() and
// merges its own (the Newsletter feature in dapeio/nino-features is the
// reference, tested in its own suite), so a project without the module has
// nothing to merge and Restore has nothing to know. What it hands every
// callback is the live data directory and the directory the backup was
// extracted to - the one registered before the restore above saw both
check( 'Restore hands the module callback the live data directory and the extracted backup',
	( $restoreSeen['dataDir'] ?? null ) === \Nino\Filesystem::path( $appData, '/data' )
	&& ( $restoreSeen['staged'] ?? 0 ) > 0 );

echo "\n";


// --- Backup/Restore with config.php outside the project root --------------

echo "Backup/Restore - config.php resolves via configPath, not root (NINO_CONFIG_DIR)\n";

$outSandbox 	= sys_get_temp_dir(). '/nino-dev-smoke-outofweb-'. uniqid();
$outConfigDir = $outSandbox. '/secret-config';

mkdir( $outSandbox. '/_admin', 0777, true );
mkdir( $outSandbox. '/_admin', 0777, true );
mkdir( $outConfigDir, 0777, true );

$outAppData = [ './nino/uid' => $outSandbox ];
\Nino\AppData::prepare( $outAppData );
$outAppData['./nino/filesystem/path']			= $outSandbox;
$outAppData['./nino/filesystem/configpath']	= $outConfigDir; // simulates NINO_CONFIG_DIR
$outAppData['./nino/filesystem/contentpath']	= $outSandbox. '/private';
$outAppData['./nino/filesystem/publicpath']	= $outSandbox. '/public';
$outAppData['/nino/dir']										= '';
$outAppData['/nino/locales/native']				= 'de_DE';
$outAppData['/nino/locales/available']			= [ 'de_DE' ];
$outAppData['/nino/auth/maxtries']					= 5;
$outAppData['/nino/auth/cooldown']					= 3600;

\Nino\Filesystem::putFileContent( $outAppData, '/config.php', [
	'/nino/error/log'					=> false,
	'/nino/error/display'			=> true,
	'/nino/locales/native'		=> 'de_DE',
	'/nino/locales/available'	=> [ 'de_DE' ],
	'/nino/html/assets'				=> [],
	'/nino/http/routes'				=> [],
] );

check( 'config.php was written under configPath, not under the project root', is_file( $outConfigDir. '/config.php' ) === true && is_file( $outSandbox. '/config.php' ) === false );

\Nino\Auth::insertUser( $outAppData, 'admin@example.com', 'correct horse battery staple', [ '/*' ] );
\Nino\Auth::loginUser( $outAppData, 'admin@example.com', 'correct horse battery staple' );

$outGuard = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::guard( $outAppData, $outGuard ); // bootstraps + creates today's backup as a side effect

$outBackupDir = $outSandbox. '/private/.backups';
$outToday 		= $outBackupDir. '/'. date( 'Y-m-d' ). '.php';

check( 'a backup was created for the out-of-webroot setup', is_file( $outToday ) === true );

$outKey 		= base64_decode( $outAppData['/nino/backup/key'] );
$outPrefix 	= "<?php http_response_code(403); exit; return '";
$outSuffix 	= "';\n";
$outRaw 		= file_get_contents( $outToday );
$outPayload = base64_decode( substr( $outRaw, strlen( $outPrefix ), -strlen( $outSuffix ) ) );
$outGz 			= openssl_decrypt( substr( $outPayload, 28 ), 'aes-256-gcm', $outKey, OPENSSL_RAW_DATA, substr( $outPayload, 0, 12 ), substr( $outPayload, 12, 16 ) );

$outTmpGz 		= $outSandbox. '/verify.tar.gz';
$outExtractDir = $outSandbox. '/verify-extracted';
file_put_contents( $outTmpGz, $outGz );
mkdir( $outExtractDir );
( new \PharData( $outTmpGz ) )->extractTo( $outExtractDir );

check( 'the out-of-webroot config.php made it into the backup archive', is_file( $outExtractDir. '/config.php' ) === true );

// Corrupt config.php (still under configPath) and restore it
$outCorrupted = \Nino\Filesystem::getFileContent( $outAppData, '/config.php', [] );
$outCorrupted['/nino/auth/user'] = [];
\Nino\Filesystem::putFileContent( $outAppData, '/config.php', $outCorrupted );

// Dev's own session gate, not Auth's - same shortcut the rest of this file
// uses instead of driving the (placeholder-hashed) real login flow
\Nino\Runtime::setSessionValue( $outAppData, './nino/admin/authed', true );

$_POST['data'] = json_encode( [ 'date' => date( 'Y-m-d' ) ] );
$outRestoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $outAppData, $outRestoreRequest );
check( 'apiRestore succeeds for the out-of-webroot setup', ( $outRestoreRequest['/nino/http/response']['body']['ok'] ?? false ) === true );

$outAfterRestore = \Nino\Filesystem::getFileContent( $outAppData, '/config.php', [] );
check( 'the restored config.php landed back under configPath, with the user record restored', isset( $outAfterRestore['/nino/auth/user']['admin@example.com'] ) === true );
check( 'restore did not leak a stray config.php copy into the webroot root', is_file( $outSandbox. '/config.php' ) === false );

\Nino\Filesystem::removeDir( $outSandbox );

echo "\n";


// --- Dev\Config ---------------------------------------------------------

echo "Config - soft-value json editor\n";

/**
 *	Dispatch one action directly against a Dev\* module class
 *
 *	@param		array 		&$appData
 *	@param		string		$class				eg. "\Nino\Modules\Config\Admin"
 *	@param		string		$method				eg. "apiList"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body ]
 */
function callDev( array &$appData, string $class, string $method, array $data = [] ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	$class::{$method}( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList returns the field schema in render order', array_column( $body['fields'], 'key' ) === [
	'/nino/error/log', '/nino/error/display', '/nino/session/force-secure-cookie', '/nino/http/proxies',
	'/nino/admin/backups', '/nino/admin/logs',
	'/nino/cache/status', '/nino/cache/ttl', '/nino/cache/blacklist',
] );
check( 'every field carries the type its editor renders from', array_column( $body['fields'], 'type' ) === [
	'bool', 'bool', 'bool', 'lines', 'bool', 'bool', 'bool', 'int', 'lines',
] );
check( 'apiList returns the group headings', array_keys( $body['groups'] ) === [ 'diagnostics', 'editor', 'cache' ] );
check( 'every field belongs to a declared group', array_diff( array_unique( array_column( $body['fields'], 'group' ) ), array_keys( $body['groups'] ) ) === [] );

$byKey = array_column( $body['fields'], null, 'key' );
check( 'a stored value comes back typed, not as json text', $byKey['/nino/error/display']['value'] === true );
check( 'an int comes back as an int', $byKey['/nino/cache/ttl']['value'] === 3600 );
check( 'an int field carries the bounds its editor and apiSave share', $byKey['/nino/cache/ttl']['min'] === 10 && $byKey['/nino/cache/ttl']['max'] === 2592000 );

// The three keys this panel deliberately stopped editing: routes and navs have
// real editors of their own (Pages/Navigations) and assets is a build concern
// whose order a json textarea shows nobody. A second, unvalidated way to write
// the same data is a way to corrupt it.
foreach( [ '/nino/http/routes', '/nino/html/navs', '/nino/html/assets' ] as $goneKey )
	check( "$goneKey is no longer part of Config", isset( $byKey[$goneKey] ) === false );

// The login throttle and the languages have screens of their own now (the
// Users panel's Lockout tab, the Language panel) and left Config too
foreach( [ '/nino/auth/maxtries', '/nino/auth/cooldown', '/nino/locales/available', '/nino/locales/native' ] as $movedKey )
	check( "$movedKey has moved out of Config", isset( $byKey[$movedKey] ) === false );

// A missing key must report the same default the runtime itself applies, or the
// form shows a value the site is not actually running with
check( 'a key absent from config.php reports the runtime default', $byKey['/nino/admin/backups']['value'] === true );

echo "\n";
echo "Language - the site's languages, and the Translations tab beside them\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiList' );
check( 'apiList returns the two locale settings and the stored native language', $status === 200 && array_column( $body['fields'], 'key' ) === [ '/nino/locales/available', '/nino/locales/native' ] && $body['fields'][1]['value'] === 'de_DE' && $body['intro'] !== '' );

// The locale inventory is the union of what config.php lists and what has a
// text file on disk - a translated language that is merely switched off should
// be one checkbox away from being back on, and a configured language without a
// text file renders every per-locale fill unresolved
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/a]]' => 'a', '[[/b]]' => 'b' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/fr_FR.php', [ '[[/a]]' => 'a' ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiList' );
$inventory = array_column( $body['locales'], null, 'code' );
check( 'the inventory lists a configured locale', isset( $inventory['de_DE'] ) === true && $inventory['de_DE']['active'] === true );
check( 'the inventory also lists a translated but unconfigured locale', isset( $inventory['fr_FR'] ) === true && $inventory['fr_FR']['active'] === false );
check( 'a locale with a text file reports its key count', $inventory['de_DE']['hasText'] === true && $inventory['de_DE']['keys'] === 2 );
check( 'a configured locale without a text file is flagged as such', $inventory['en_US']['hasText'] === false && $inventory['en_US']['active'] === true );
check( 'a known locale gets a readable name', $inventory['de_DE']['name'] === 'German (Germany)' );

// --- apiAddLocale -------------------------------------------------------
//
// Adding a language used to leave the project in exactly the state
// Locales::init() warns about: a configured locale with no text file of its
// own renders every per-locale fill as a raw [[key]]. Text::saveBatch() cannot
// bootstrap one either - it validates against keys that already exist.

[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale creates a text file for a new language', $status === 200 && ( $body['created'] ?? null ) === true );
check( '...copying the key count of the native language', ( $body['keys'] ?? 0 ) === 2 && ( $body['from'] ?? '' ) === 'de_DE' );
check( '...and names the language, with its code, in global.php: /_nino/locale/<code>/name is the system\'s to write, no editor creates it', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/it_IT/name]]'] ?? null ) === 'it_IT' );

$skeleton = \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', false );
check( '...the file really is on disk', is_array( $skeleton ) === true );
check( '...with exactly the native language\'s keys, in its order', array_keys( $skeleton ) === array_keys( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ) ) );
check( '...and every value empty, so an untranslated key reads as untranslated', array_values( array_unique( $skeleton ) ) === [ '' ] );

// The new language is a translation skeleton, not yet a language of the site -
// activating it is the form's Save, together with the native language it has
// to agree with
$afterAdd = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'apiAddLocale does not activate the language on its own', in_array( 'it_IT', $afterAdd['/nino/locales/available'] ?? [], true ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiList' );
$inventoryAfterAdd = array_column( $body['locales'], null, 'code' );
check( '...but the inventory now offers it as a translated, inactive language', ( $inventoryAfterAdd['it_IT']['hasText'] ?? null ) === true && ( $inventoryAfterAdd['it_IT']['active'] ?? null ) === false );

// Reachable from a button, so it must never be one click away from emptying a
// finished translation
\Nino\Filesystem::putFileContent( $appData, '/text/it_IT.php', [ '[[/a]]' => 'tradotto', '[[/b]]' => 'anche' ] );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/locale/it_IT/name]]'] = 'Italiano';
	return $global;
} );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale refuses to overwrite an existing translation', $status === 200 && ( $body['created'] ?? null ) === false );
check( '...and keeps the name an editor gave the language since', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/it_IT/name]]'] ?? null ) === 'Italiano' );
// A file made by hand or imported has no name: the existing file's answer adds it, and nothing else
\Nino\Filesystem::putFileContent( $appData, '/text/fr_FR.php', [ '[[/a]]' => 'traduit' ] );
check( 'a hand-made language file has no name to begin with', isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/fr_FR/name]]'] ) === false );
[ $handStatus, $handBody ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'fr_FR' ] );
check( '...so apiAddLocale names its language when it answers "exists", the one thing it adds', $handStatus === 200 && ( $handBody['created'] ?? null ) === false
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/fr_FR/name]]'] ?? null ) === 'fr_FR' && \Nino\Filesystem::getFileContent( $appData, '/text/fr_FR.php', [] ) === [ '[[/a]]' => 'traduit' ] );
unlink( \Nino\Filesystem::path( $appData, '/text/fr_FR.php' ) );
// A name somebody already wrote - the Localepicker unit's, or an editor's - is left alone
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/locale/es_ES/name]]'] = 'Español';
	return $global;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'es_ES' ] );
check( 'a language that has a name already keeps it when its file is created', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/es_ES/name]]'] ?? null ) === 'Español' );
unlink( \Nino\Filesystem::path( $appData, '/text/es_ES.php' ) );
check( '...reporting what that file actually holds', ( $body['keys'] ?? 0 ) === 2 );
check( '...and leaving its values untouched', \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', [] )['[[/a]]'] === 'tradotto' );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'notalocale' ] );
check( 'apiAddLocale rejects a malformed language id', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'de_DE' ] );
check( 'apiAddLocale answers the native language from its own existing file', $status === 200 );

// A locale whose native has nothing to copy from cannot produce a skeleton -
// better a clear refusal than an empty file that looks like one
$noNative = $appData;
$noNativeConfig = \Nino\Filesystem::getFileContent( $noNative, '/config.php', [] );
$noNativeConfig['/nino/locales/native'] = 'pt_PT';
\Nino\Filesystem::putFileContent( $noNative, '/config.php', $noNativeConfig );
[ $status, $body ] = callDev( $noNative, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'sv_SE' ] );
check( 'apiAddLocale refuses when the native language has no text file', $status === 400 && str_contains( (string) ( $body['error'] ?? '' ), 'pt_PT' ) === true );
check( '...and writes nothing', \Nino\Filesystem::fileExists( $noNative, '/text/sv_SE.php' ) === false );
$noNativeConfig['/nino/locales/native'] = 'de_DE';
\Nino\Filesystem::putFileContent( $appData, '/config.php', $noNativeConfig );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'nl_NL' ] );
check( 'apiAddLocale requires an authed _admin session', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// --- Config::apiSave ------------------------------------------------------

echo "\n";
echo "Config::apiSave and the Lockout tab's - typed writes into config.php\n";

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/display' => false ] ] );
check( 'apiSave accepts a bool', $status === 200 );
check( 'the saved bool lands in appData', $appData['/nino/error/display'] === false );

// A form posts strings; config.php has to receive the real types
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => '600', '/nino/admin/logs' => 'true' ] ] );
check( 'apiSave coerces a posted numeric string', $status === 200 && $appData['/nino/cache/ttl'] === 600 );
check( 'apiSave coerces a posted "true"', $appData['/nino/admin/logs'] === true );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => '5.5' ] ] );
check( 'apiSave rejects a non-integer rather than casting it', $status === 400 && $appData['/nino/cache/ttl'] === 600 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 1 ] ] );
check( 'apiSave rejects an int below its minimum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 999999999 ] ] );
check( 'apiSave rejects an int above its maximum', $status === 400 );

// The refusal says what it is in a way a client can word: the code of the type,
// the limits that belong in the sentence, and the field it was posted for
[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 1 ] ] );
check( 'a refused int carries the code "int_range", its bounds as params and the field',
	$status === 400 && ( $body['code'] ?? '' ) === 'int_range' && is_int( $body['params'][0] ?? null ) === true && is_int( $body['params'][1] ?? null ) === true
	&& $body['params'][0] < $body['params'][1] && ( $body['field'] ?? '' ) === '/nino/cache/ttl'
	&& str_starts_with( (string) ( $body['error'] ?? '' ), '/nino/cache/ttl: expected a whole number between ' ) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => 'yes please' ] ] );
check( 'apiSave rejects a bool that is neither', $status === 400 );
check( '...with the code "bool" and the field', ( $body['code'] ?? '' ) === 'bool' && ( $body['field'] ?? '' ) === '/nino/error/log' && array_key_exists( 'params', $body ) === false );

/*	The proxy list decides which address every per-ip rule in the site counts
	a visitor as, so a line that is not an address at all is refused rather
	than stored: it would match nothing while the form reads as configured */
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => "198.51.100.7\n10.0.0.0/8\n2001:db8::/32" ] ] );
check( 'apiSave takes addresses and cidr ranges as the proxy list', $status === 200 && $appData['/nino/http/proxies'] === [ '198.51.100.7', '10.0.0.0/8', '2001:db8::/32' ] );
check( '...and writes them to config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/proxies'] === [ '198.51.100.7', '10.0.0.0/8', '2001:db8::/32' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => 'cloudflare' ] ] );
check( 'apiSave refuses a proxy entry that is no address', $status === 400 && $appData['/nino/http/proxies'] === [ '198.51.100.7', '10.0.0.0/8', '2001:db8::/32' ] );
check( '...with the code "lines_ip" and the field', ( $body['code'] ?? '' ) === 'lines_ip' && ( $body['field'] ?? '' ) === '/nino/http/proxies' );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => '10.0.0.0/64' ] ] );
check( '...and a cidr prefix the address it belongs to cannot have', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => '' ] ] );
check( 'an emptied proxy list saves as the empty list that trusts nothing', $status === 200 && $appData['/nino/http/proxies'] === [] );

// Nothing is written until every field validates, so one bad value cannot leave
// half a form saved
$beforePartial = $appData['/nino/error/display'];
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/display' => true, '/nino/cache/ttl' => 'nope' ] ] );
check( 'a rejected field leaves the valid ones in the same request unwritten', $status === 400 && $appData['/nino/error/display'] === $beforePartial );

// The login throttle: two ints on a tab of the Users panel, validated the
// way Config validates - a maxtries of 0 would lock every account out, a
// cooldown of 0 removes the throttle
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiList' );
check( 'Lockout::apiList returns both numbers as config.php holds them, with their bounds', $status === 200 && array_column( $body['fields'], 'key' ) === [ '/nino/auth/maxtries', '/nino/auth/cooldown' ] && $body['fields'][0]['value'] === 5 && $body['fields'][1]['value'] === 3600 && $body['fields'][0]['min'] === 1 && $body['fields'][1]['max'] === 604800 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => '8' ] ] );
check( 'Lockout::apiSave coerces a posted numeric string', $status === 200 && $appData['/nino/auth/maxtries'] === 8 );
check( '...and writes it to config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/maxtries'] === 8 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => '5.5' ] ] );
check( 'Lockout::apiSave rejects a non-integer rather than casting it', $status === 400 && $appData['/nino/auth/maxtries'] === 8 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 0 ] ] );
check( 'Lockout::apiSave rejects an int below its minimum', $status === 400 );
check( '...with the code "int_range", the bounds 1 and 100 and the field', ( $body['code'] ?? '' ) === 'int_range' && ( $body['params'] ?? [] ) === [ 1, 100 ] && ( $body['field'] ?? '' ) === '/nino/auth/maxtries' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/cooldown' => 999999999 ] ] );
check( 'Lockout::apiSave rejects an int above its maximum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 60 ] ] );
check( 'Lockout::apiSave knows its two keys and nothing else', $status === 400 );

// The accounts locked out right now, and lifting one lock. The ip buckets are
// not accounts: they stay out of the list and untouched by the lift
$lockTries = static function( array $seed ) use ( &$appData ): void {
	\Nino\Filesystem::mutate( $appData, '/data/auth-tries.php', static function( mixed $state ) use ( $seed ): array {
		$state = is_array( $state ) ? $state : [];
		foreach( $seed as $key => $value )
			if( $value === null )
				unset( $state[$key] );
			else
				$state[$key] = $value;
		return $state;
	} );
};
\Nino\Auth::insertUser( $appData, 'lockedone@example.com', 'correct horse battery staple' );
\Nino\Auth::insertUser( $appData, 'lockedtwo@example.com', 'correct horse battery staple' );
$lockTries( [ 'lockedtwo@example.com' => 0 - time() - 3600, 'lockedone@example.com' => 0 - time() - 7200, 'ip:203.0.113.9' => 0 - time() - 3600 ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiList' );
check( 'Lockout::apiList carries the locked accounts, by mail, with the time the lock ends', $status === 200 && array_column( $body['locked'], 'mail' ) === [ 'lockedone@example.com', 'lockedtwo@example.com' ]
	&& preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $body['locked'][0]['until'] ) === 1 && $body['locked'][0]['until'] > $body['locked'][1]['until'] );
check( '...and no ip', str_contains( json_encode( $body['locked'] ), '203.0.113.9' ) === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'lockedone@example.com' ] );
check( 'apiUnlock lifts the lock and answers with the accounts still locked', $status === 200 && array_column( $body['locked'], 'mail' ) === [ 'lockedtwo@example.com' ] );
check( '...the right password logs the account in again, and the locked ip stays locked', ( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )['ip:203.0.113.9'] ?? 0 ) < 0
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )['lockedone@example.com'] ) === false );
check( '...and the activity log says so', \Nino\Modules\Users\Lockout::log( 'lockout/unlock', [ 'username' => 'lockedone@example.com' ] ) === 'Lift Lock lockedone@example.com'
	&& \Nino\Modules\Users\Lockout::log( 'lockout/save', [] ) === 'Edit Login Protection' && \Nino\Modules\Users\Lockout::log( 'lockout/list', [] ) === '' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'nobody-at-all@example.com' ] );
check( 'apiUnlock 404s for an unknown mail', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => [ 'lockedtwo@example.com' ] ] );
check( '...and for a name that is not a string', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [] );
check( '...and for none', $status === 404 );

// A tries file that cannot be written: the lock is still there and the answer says so
$lockKey	= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, '/data/auth-tries.php' ] );
$lockFile	= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $lockKey ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $lockFile );
@mkdir( $lockFile );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'lockedtwo@example.com' ] );
@rmdir( $lockFile );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( 'apiUnlock answers 500 when the lock could not be lifted', $status === 500 && isset( \Nino\Auth::lockedAccounts( $appData )['lockedtwo@example.com'] ) === true );

\Nino\Auth::insertUser( $appData, 'usersmanageonly@example.com', 'correct horse battery staple', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'usersmanageonly@example.com', 'correct horse battery staple' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'lockedtwo@example.com' ] );
check( 'apiUnlock is 403 for an account that holds users/manage but not the login protection permission', $status === 403 && isset( \Nino\Auth::lockedAccounts( $appData )['lockedtwo@example.com'] ) === true );
\Nino\Auth::deleteUser( $appData, 'usersmanageonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$lockTries( [ 'ip:203.0.113.9' => null ] );
\Nino\Auth::deleteUser( $appData, 'lockedone@example.com' );
\Nino\Auth::deleteUser( $appData, 'lockedtwo@example.com' );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 3 ] ] );
check( 'and Config no longer writes them', $status === 400 && $appData['/nino/auth/maxtries'] === 8 );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 3 ] ] );
check( 'Lockout actions require an authed _admin session too', $status === 401 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'dev@example.com' ] );
check( '...apiUnlock too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/modules' => [] ] ] );
check( 'apiSave ignores a key outside the schema', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/html/assets' => [] ] ] );
check( 'apiSave refuses /nino/html/assets, which this panel no longer owns', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/routes' => [] ] ] );
check( 'apiSave refuses /nino/http/routes, which belongs to Pages', $status === 400 );

// --- the two locale keys constrain each other ---------------------------

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'en_US',
] ] );
check( 'apiSave accepts a language list with a native language inside it', $status === 200 );
check( 'the language list round-trips', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

[ $status, $errorBody ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE' ],
	'/nino/locales/native' 		=> 'en_US',
] ] );
check( 'apiSave refuses a native language outside the list being saved', $status === 400 );
check( '...and says which rule was broken', str_contains( (string) ( $errorBody['error'] ?? '' ), 'native' ) === true );
check( '...leaving the previous list untouched', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

// Dropping the currently native language without naming a new one is the same
// contradiction, just arrived at from the other side
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE', 'fr_FR' ] ] ] );
check( 'apiSave refuses a list that drops the current native language', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [] ] ] );
check( 'apiSave refuses an empty language list', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE', 'notalocale' ] ] ] );
check( 'apiSave refuses a malformed locale id', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE' ] ] ] );
check( 'Config no longer writes the language list', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'de_DE', 'en_US' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );
check( 'apiSave deduplicates a repeated locale', $status === 200 && $appData['/nino/locales/available'] === [ 'de_DE', 'en_US' ] );

// A 'lines' field is typed as a textarea and stored as a list - the split and
// the trim live in the backend, so the two cannot disagree about what a blank
// line means
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => "/contact\n\n  /blog/*  \n/contact\n" ] ] );
check( 'apiSave splits a posted textarea into a list', $status === 200 && $appData['/nino/cache/blacklist'] === [ '/contact', '/blog/*' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => [ '/a', '/b' ] ] ] );
check( '...and accepts an already-split list too', $status === 200 && $appData['/nino/cache/blacklist'] === [ '/a', '/b' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => [ [ 'not', 'a', 'string' ] ] ] ] );
check( '...but refuses a list that is not of strings', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => '' ] ] );
check( '...and an emptied textarea clears the list', $status === 200 && $appData['/nino/cache/blacklist'] === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [] ] );
check( 'apiSave rejects an empty form rather than writing nothing quietly', $status === 400 );

// Put the language list back the way the blocks below expect to find it -
// Translations asserts against all three. Saved through apiSave rather than
// assigned, so this also lands in config.php, which is where its own apiInfo
// reads the list from
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );
check( 'the language list is restored for the blocks below', $status === 200 && $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

// Regression: init() used to merge its own routes with '+=', which does NOT
// overwrite a key that already exists - so a persisted 'GET://_admin' (from a
// hand-written config.php, or from Pages) shadowed the dashboard and left no ui
// path back to the route that did it. Same fix Install::init() already carries.
$shadowed = $appData;
$shadowed['/nino/http/routes']['GET://_admin'] 	= [ 'uri' => '/_admin', 'body' => 'hijacked' ];
$shadowed['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin', 'body' => 'hijacked' ];
\Nino\Admin\Admin::init( $shadowed );
check( 'init always restores the tool-owned GET route over a stale/hand-written collision', $shadowed['/nino/http/routes']['GET://_admin']['body'] === '[template /_admin/templates/page-index]' );
check( 'init always restores the tool-owned POST route too', ( $shadowed['/nino/http/routes']['POST://_admin']['body'] ?? null ) === null );

// Admin::init() may be called more than once in a composed request - the
// bundles and fills it registers have to come out the same, not doubled
\Nino\Admin\Admin::init( $shadowed );
check( 'repeated Admin init keeps the bundles free of duplicates', count( $shadowed['/nino/html/assets']['/_admin/.cache/script.js'] ) === count( array_unique( $shadowed['/nino/html/assets']['/_admin/.cache/script.js'] ) ) );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiList' );
check( 'Config actions require an authed _admin session too', $status === 401 );
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => true ] ] );
check( '...apiSave too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// --- The Recovery password tab --------------------------------------------
//
// The secret recovery.php asks for, changed from the workbench with the old
// one. The checks that matter: a malformed request costs no attempt, a wrong
// old password counts on recovery.php's own five, and what is stored stays
// behind its stub

echo "Users\\RecoveryPassword - changing the recovery password\n";

$lockoutPath	= \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json';
$resetLockout	= static function() use ( &$appData, $lockoutPath ): void {
	\Nino\Filesystem::putFileContent( $appData, $lockoutPath, [ 'tries' => 0, 'until' => 0 ] );
	unset( $appData['./nino/filesystem/cache'][$lockoutPath] );
};
$tries 				= static function() use ( &$appData, $lockoutPath ): int {
	unset( $appData['./nino/filesystem/cache'][$lockoutPath] );
	return (int) ( \Nino\Filesystem::getFileContent( $appData, $lockoutPath, [ 'tries' => 0 ] )['tries'] ?? 0 );
};
$resetLockout();
$secretBefore	= \Nino\Admin\Recovery::hash( $appData );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'RecoveryPassword::apiSave is 401 logged out', $status === 401 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );

\Nino\Auth::insertUser( $appData, 'usersmanageonly@example.com', 'correct horse battery staple', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'usersmanageonly@example.com', 'correct horse battery staple' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( '...403 for an account that manages users but does not hold the recovery password permission', $status === 403 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );
\Nino\Auth::deleteUser( $appData, 'usersmanageonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

check( 'the tab\'s permission is its own, under /_admin/<uri>/manage', \Nino\Modules\Users\RecoveryPassword::MANAGE_PERM === '/_admin/recoverypw/manage' && \Nino\Modules\Users\RecoveryPassword::perm() === '/_admin/recoverypw/manage' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'not the password', 'pw' => 'short' ] );
check( 'a new password under the minimum is 400, before the old one is looked at - no attempt is used up', $status === 400 && $tries() === 0 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );
check( '...named "recoverypw_password_short" with the minimum, for the field "pw"', ( $body['code'] ?? '' ) === 'recoverypw_password_short' && ( $body['params'] ?? [] ) === [ \Nino\Admin\Recovery::MIN_PW_LENGTH ] && ( $body['field'] ?? '' ) === 'pw' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => [ 'a brand new secret' ] ] );
check( '...and so is one that is no string', $status === 400 && $tries() === 0 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'not the password', 'pw' => 'a brand new secret' ] );
check( 'a wrong current password is 401, the secret is unchanged and the attempt counts', $status === 401 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore && $tries() === 1
	&& str_contains( json_encode( $body ), 'a brand new secret' ) === false && str_contains( json_encode( $body ), 'not the password' ) === false );
check( '...named "recoverypw_wrong" for the field "current"', ( $body['code'] ?? '' ) === 'recoverypw_wrong' && ( $body['field'] ?? '' ) === 'current' );

$resetLockout();
for( $i = 0; $i < 5; $i++ )
	callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'not the password', 'pw' => 'a brand new secret' ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'five wrong attempts lock it - the right password is then 429 too, and nothing is stored', $status === 429 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );
check( '...named "recoverypw_locked"', ( $body['code'] ?? '' ) === 'recoverypw_locked' );
check( '...the same lock recovery.php meets', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 429 );
$resetLockout();

$secretFile	= \Nino\Admin\Recovery::path( $appData );
$secretRaw	= (string) file_get_contents( $secretFile );
unlink( $secretFile );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'a project without a recovery password is refused, 409 - there is no old one to compare with', $status === 409 && is_file( $secretFile ) === false && $tries() === 0 );
check( '...named "recoverypw_unset"', ( $body['code'] ?? '' ) === 'recoverypw_unset' );
file_put_contents( $secretFile, $secretRaw );

[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'the right current password changes it, 200', $status === 200 );
check( '...the new one verifies and the old one does not', \Nino\Admin\Recovery::verify( $appData, 'a brand new secret' ) === 200 && \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 401 );
$secretNow = (string) file_get_contents( $secretFile );
check( '...and the file is still behind its stub', str_starts_with( $secretNow, \Nino\Admin\Recovery::STUB_PREFIX ) && str_ends_with( $secretNow, \Nino\Admin\Recovery::STUB_SUFFIX )
	&& str_contains( $secretNow, 'a brand new secret' ) === false );
check( '...the activity log names the action and nothing of the data', \Nino\Modules\Users\RecoveryPassword::log( 'recoverypw/save', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] ) === 'Change Recovery Password'
	&& \Nino\Modules\Users\RecoveryPassword::log( 'recoverypw/other', [] ) === '' );

\Nino\Auth::logoutUser( $appData );
$secretRaw = (string) file_get_contents( $secretFile );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'a brand new secret', 'pw' => 'yet another secret' ] );
check( 'logged out it changes nothing, even with the right current password', $status === 401 && (string) file_get_contents( $secretFile ) === $secretRaw );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// The suite's own secret again, for everything after this
check( 'the suite\'s secret is back for the rest of the file', \Nino\Admin\Recovery::set( $appData, 'the real password' ) === true && \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 200 );
$resetLockout();

echo "\n";

// --- Recovery::handlePost - accounts ---------------------------------------
//
// recovery.php's two account actions, in-process with the session flag the
// login sets (the child above does the same for restore): setting a password
// is for an account that exists, and a typo must not make one - making one is
// its own action and asks for a confirmation the page sends

echo "Recovery::handlePost - set a password, create an account\n";

$recoveryCall = static function( string $action, array $data, bool $open = true ) use ( &$appData ): array {
	if( $open === true )
		\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
	else
		\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );
	$_POST['action'] 	= $action;
	$_POST['data'] 		= json_encode( $data );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\Recovery::handlePost( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ?? null ];
};

\Nino\Auth::insertUser( $appData, 'resetme@example.com', 'the old password', [ '/_admin/text/manage' ] );
\Nino\Auth::loginUser( $appData, 'resetme@example.com', 'the old password' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$accountsBefore = array_keys( $appData['/nino/auth/user'] );

check( 'the account to reset has a session of its own', count( \Nino\Auth::getUser( $appData, 'resetme@example.com' )['sessions'] ) === 1 );

[ $status, $body ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'typo@example.com', 'pw' => 'a new password' ] );
check( 'reset for an address that has no account is 404 and creates nothing', $status === 404 && array_keys( $appData['/nino/auth/user'] ) === $accountsBefore );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'not a mail', 'pw' => 'a new password' ] );
check( '...400 for a bad address', $status === 400 );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'short' ] );
check( '...400 for a password under the minimum, and the account keeps its own', $status === 400 && password_verify( 'the old password', $appData['/nino/auth/user']['resetme@example.com']['pw'] ) === true );

[ $status, $body ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'a new password' ] );
check( 'reset for an account that exists is 200, created false', $status === 200 && ( $body['created'] ?? null ) === false && ( $body['mail'] ?? '' ) === 'resetme@example.com' );
check( '...every session of it is gone', \Nino\Auth::getUser( $appData, 'resetme@example.com' )['sessions'] === [] );
check( '...the new password logs in and the old one does not', \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'the old password' ) === false
	&& is_array( \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'a new password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// What a recovered password is for: the developer who cannot get in. A lock
// or a deactivation would leave the new password as useless as the old one
$lockTries( [ 'resetme@example.com' => 0 - time() - 3600 ] );
\Nino\Auth::setStatus( $appData, 'resetme@example.com', false );
check( 'an account that is locked and deactivated gets in with no password at all', isset( \Nino\Auth::lockedAccounts( $appData )['resetme@example.com'] ) === true
	&& \Nino\Auth::getUser( $appData, 'resetme@example.com' )['status'] === \Nino\Auth::STATUS_DISABLED
	&& \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'a new password' ) === false );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'a second password' ] );
check( 'reset lifts the lock and activates it again', $status === 200 && isset( \Nino\Auth::lockedAccounts( $appData )['resetme@example.com'] ) === false
	&& \Nino\Auth::getUser( $appData, 'resetme@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
check( '...and the new password logs in', is_array( \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'a second password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password' ] );
check( 'create without the confirmation is 400 and writes nothing', $status === 400 && \Nino\Auth::getUser( $appData, 'fresh@example.com' ) === false );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password', 'confirm' => 'yes' ] );
check( '...and so is anything but true for it', $status === 400 && \Nino\Auth::getUser( $appData, 'fresh@example.com' ) === false );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'not a mail', 'pw' => 'a fresh password', 'confirm' => true ] );
check( 'a bad address is 400', $status === 400 && array_keys( $appData['/nino/auth/user'] ) === $accountsBefore );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'short', 'confirm' => true ] );
check( '...so is a password under the minimum', $status === 400 && \Nino\Auth::getUser( $appData, 'fresh@example.com' ) === false );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'resetme@example.com', 'pw' => 'another password', 'confirm' => true ] );
check( 'an address that has an account is 409 - and its password is untouched', $status === 409
	&& password_verify( 'a second password', $appData['/nino/auth/user']['resetme@example.com']['pw'] ) === true );

[ $status, $body ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password', 'confirm' => true ] );
$fresh = \Nino\Auth::getUser( $appData, 'fresh@example.com' );
check( 'create with the confirmation is 200, created true', $status === 200 && ( $body['created'] ?? null ) === true && $fresh !== false );
check( '...an account with full access and the Developer role', $fresh !== false && $fresh['perms'] === [ '/*' ] && $fresh['role'] === 'developer' && $fresh['status'] === \Nino\Auth::STATUS_ACTIVE );
check( '...that can log in', is_array( \Nino\Auth::loginUser( $appData, 'fresh@example.com', 'a fresh password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$accountsAfter = array_keys( $appData['/nino/auth/user'] );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'yet another password' ], false );
check( 'with the session flag unset, reset is 401', $status === 401 && password_verify( 'a second password', $appData['/nino/auth/user']['resetme@example.com']['pw'] ) === true );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'unlocked@example.com', 'pw' => 'yet another password', 'confirm' => true ], false );
check( '...and so is create', $status === 401 && array_keys( $appData['/nino/auth/user'] ) === $accountsAfter );

\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );
\Nino\Auth::deleteUser( $appData, 'resetme@example.com' );
\Nino\Auth::deleteUser( $appData, 'fresh@example.com' );
$lockTries( [ 'resetme@example.com' => null ] );

echo "\n";


// --- Modules\Maintenance\Admin ---------------------------------------------

echo "Modules\\Maintenance\\Admin - the one switch, from the workbench\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus answers the current, unconfigured state', $status === 200 && $body === [ 'status' => false, 'retry' => 3600 ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => true, 'retry' => 120 ] );
check( 'apiSet accepts a valid switch', $status === 200 && $body === [ 'status' => true, 'retry' => 120 ] );
check( '...and writes both keys to config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/status'] === true
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/retry'] === 120 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus reflects the just-saved state', $status === 200 && $body === [ 'status' => true, 'retry' => 120 ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => 'yes', 'retry' => 120 ] );
check( 'apiSet rejects a status that is not a bool', $status === 400 && ( $body['code'] ?? '' ) === 'bool' && ( $body['field'] ?? '' ) === 'status' && $body['error'] === 'status: expected true or false' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 30 ] );
check( 'apiSet rejects a retry below its minimum', $status === 400 && ( $body['code'] ?? '' ) === 'int_range' && ( $body['field'] ?? '' ) === 'retry' && count( $body['params'] ?? [] ) === 2 && str_starts_with( $body['error'], 'retry: expected a whole number between ' ) );

[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 999999999 ] );
check( 'apiSet rejects a retry above its maximum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 'nope' ] );
check( 'apiSet rejects a non-integer retry rather than casting it', $status === 400 );

check( 'a rejected field leaves config.php exactly as it was', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/status'] === true
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/retry'] === 120 );

check( 'log() names the direction of the switch', \Nino\Modules\Maintenance\Admin::log( 'maintenance/set', [ 'status' => true ] ) === 'Switch maintenance on'
	&& \Nino\Modules\Maintenance\Admin::log( 'maintenance/set', [ 'status' => false ] ) === 'Switch maintenance off' );

// Back off, so the rest of the suite runs against a normal site
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 3600 ] );
check( 'switched back off for the rest of the suite', $status === 200 && $appData['/nino/maintenance/status'] === false );

// 401/403, the same way every other panel is checked
\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus requires an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

\Nino\Auth::insertUser( $appData, 'noswitch@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::loginUser( $appData, 'noswitch@example.com', 'correct horse battery staple' );
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus is 403 for an account without the permission', $status === 403 );
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => true, 'retry' => 120 ] );
check( '...apiSet too', $status === 403 );
\Nino\Auth::deleteUser( $appData, 'noswitch@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Dev\Users ------------------------------------------------------------

echo "Users - accounts with a role, deletion and the role change\n";

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'pw' => 'a-long-enough-password', 'role' => 'developer' ] );
check( 'apiCreate creates a user', $status === 200 );
check( 'the new user has the manage permission', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage', 'newdev@example.com' ) === true );

// A non-manager account still gets full content access (elements/text/...),
// same as any admin account had before per-module permissions existed - only
// /_admin/users/manage is gated by the isManager checkbox
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'newcontenteditor@example.com', 'pw' => 'a-long-enough-password', 'role' => 'editor' ] );
check( 'apiCreate creates a non-manager user', $status === 200 );
check( 'a non-manager still has content-module access', \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'newcontenteditor@example.com' ) === true );
check( 'a non-manager does not have the manage permission', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage', 'newcontenteditor@example.com' ) === false );
check( '...nor any structure or system panel', \Nino\Auth::checkPermission( $appData, '/_admin/config/manage', 'newcontenteditor@example.com' ) === false && \Nino\Auth::checkPermission( $appData, '/_admin/types/manage', 'newcontenteditor@example.com' ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'norole@example.com', 'pw' => 'a-long-enough-password', 'role' => 'wizard' ] );
check( 'apiCreate rejects an unknown role', $status === 400 );
\Nino\Auth::deleteUser( $appData, 'newcontenteditor@example.com' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'not-an-email', 'pw' => 'a-long-enough-password' ] );
check( 'apiCreate rejects an invalid mail', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'short@example.com', 'pw' => 'short' ] );
check( 'apiCreate rejects a too-short password', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'pw' => 'a-long-enough-password' ] );
check( 'apiCreate rejects a mail that already exists', $status === 409 );

[ , $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiList' );
check( 'the new user shows up in apiList, never a password hash', in_array( 'newdev@example.com', array_column( $body['users'], 'mail' ), true ) === true && isset( $body['users'][0]['pw'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'dev@example.com' ] );
check( 'apiDelete refuses your own account', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'newdev@example.com' ] );
check( 'apiDelete removes the user', $status === 200 );
check( 'the user is actually gone', isset( $appData['/nino/auth/user']['newdev@example.com'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'newdev@example.com' ] );
check( 'apiDelete 404s for an already-gone user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'permsdev@example.com', 'pw' => 'a-long-enough-password' ] );
check( 'apiCreate creates an account without a role - one that may only edit its own profile', $status === 200 && \Nino\Auth::getUser( $appData, 'permsdev@example.com' )['role'] === '' && \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'permsdev@example.com' ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'unknown@example.com', 'role' => 'editor' ] );
check( 'apiSetRole 404s for an unknown user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'permsdev@example.com', 'role' => 'wizard' ] );
check( 'apiSetRole rejects a role the config does not have', $status === 400 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'permsdev@example.com', 'role' => 'editor' ] );
check( 'apiSetRole hands out a role', $status === 200 && $body['role'] === 'editor' );
check( 'the role\'s permissions took effect', \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'permsdev@example.com' ) === true );
check( 'a permission the role lacks stays denied', \Nino\Auth::checkPermission( $appData, '/_admin/types/manage', 'permsdev@example.com' ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiList' );
$listed = $body['users'][ array_search( 'permsdev@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( 'apiList exposes the role and the roles there are, never a password hash', $listed['role'] === 'editor' && isset( $listed['pw'] ) === false && array_column( $body['roles'], 'label' ) === [ 'Editor', 'Developer' ] );

echo "\n";


// --- Roles ----------------------------------------------------------------

echo "Roles - named permission sets, edited on a tab of the Users panel\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiList' );
check( 'apiList lists the two default roles with how many accounts hold each', $status === 200 && array_column( $body['roles'], 'id' ) === [ 'editor', 'developer' ] && $body['roles'][0]['users'] === 1 && $body['roles'][1]['users'] === 0 );
check( 'apiList offers every declared permission, grouped like the navigation - tabs included, the manage permission once', in_array( [ 'perm' => '/_admin/types/manage', 'label' => '/_admin/nav/types', 'group' => 'structure', 'offered' => true ], $body['permOptions'], true ) === true
	&& in_array( [ 'perm' => '/_admin/users/manage', 'label' => '/_admin/users/label/permissions-manage', 'group' => 'system', 'offered' => true ], $body['permOptions'], true ) === true
	&& in_array( [ 'perm' => '/_admin/lockout/manage', 'label' => '/_admin/nav/lockout', 'group' => 'system', 'offered' => true ], $body['permOptions'], true ) === true
	&& in_array( [ 'perm' => '/_admin/translations/manage', 'label' => '/_admin/nav/translations', 'group' => 'system', 'offered' => true ], $body['permOptions'], true ) === true
	&& count( array_filter( $body['permOptions'], fn( array $o ): bool => $o['perm'] === '/_admin/users/manage' ) ) === 1 );

// A permission an account carries directly, with no panel and no role behind
// it, is in force just the same - so it is on the list, marked as offered by
// nothing, and a role save cannot quietly wash it out of the installation
$strayAppData = $appData;
$strayAppData['/nino/auth/user']['stray@example.com'] = [ 'perms' => [ '/_admin/legacy/manage' ] ];
check( 'a permission held by an account alone is listed too, as offered by nothing', in_array( [ 'perm' => '/_admin/legacy/manage', 'label' => '/_admin/legacy/manage', 'group' => 'other', 'offered' => false ], \Nino\Modules\Users\Admin::permOptions( $strayAppData ), true ) === true );
check( 'full access is never one of them - it is its own control', in_array( '/*', \Nino\Modules\Users\Admin::permsInUse( $strayAppData ), true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'Not A Slug', 'label' => 'X', 'perms' => [] ] );
check( 'apiSave rejects an id that is not a slug', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => '', 'perms' => [] ] );
check( 'apiSave rejects an empty name', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => 'not-an-array' ] );
check( 'apiSave rejects a non-array perms value', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', 42 ] ] );
check( 'apiSave rejects a perms array with a non-string entry', $status === 400 );

// Checked for shape rather than against a list: the scoped permissions (see
// \Nino\Admin\Admin::scoped()) are one string per action, field and text key,
// so there is nothing to enumerate. A string no permission check could ever
// match is still refused, and by name - dropping it silently is how a role
// comes back missing what was just typed into it
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', 'not a perm' ] ] );
check( 'apiSave refuses a string that is not shaped like a permission', $status === 400 );
check( '...naming it, so the typo can be found', str_contains( (string) ( $body['error'] ?? '' ), 'not a perm' ) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', '/_admin/elements/services/update/title' ] ] );
check( 'apiSave keeps a well-formed permission no panel offers', $status === 200 && $body['perms'] === [ '/_admin/elements/manage', '/_admin/elements/services/update/title' ] && $body['users'] === 0 );
check( 'the role landed in config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/roles']['reviewer'] === [ 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', '/_admin/elements/services/update/title' ] ] );
check( 'a wildcard segment is a permission too', callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/text/update/page-home/*' ] ] )[0] === 200 );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', '/*' ] ] );
check( 'full access stands alone - it covers everything else already', $status === 200 && $body['perms'] === [ '/*' ] );

// The two refusals: the account making the change keeps the manage
// permission, and some account keeps full access. dev@example.com relies on
// the Developer role alone for this, the way the wizard's root account does -
// and has to be the last full access there is, so the Backup section's
// account above goes first
\Nino\Auth::deleteUser( $appData, 'admin@example.com' );
$appData['/nino/auth/user']['dev@example.com']['perms'] = [];
\Nino\Auth::setRole( $appData, 'dev@example.com', 'developer' );
check( 'the signed-in account now holds full access through its role only', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage' ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [ '/_admin/elements/manage' ] ] );
check( 'a change that would take the manage permission from your own account is refused', $status === 409 && $appData['/nino/auth/roles']['developer']['perms'] === [ '/*' ] );

\Nino\Auth::insertUser( $appData, 'mgr@example.com', 'a-long-enough-password', [ '/_admin/users/manage', '/_admin/text/manage' ] );
\Nino\Auth::loginUser( $appData, 'mgr@example.com', 'a-long-enough-password' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [ '/_admin/elements/manage', '/_admin/users/manage' ] ] );
check( 'a change that would leave no account with full access is refused', $status === 409 && $appData['/nino/auth/roles']['developer']['perms'] === [ '/*' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'dev@example.com', 'role' => 'editor' ] );
check( 'nor may the last full access be handed away through a role change', $status === 409 && \Nino\Auth::getUser( $appData, 'dev@example.com' )['role'] === 'developer' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'dev@example.com' ] );
check( 'nor deleted - the role counts, not just the account\'s own permissions', $status === 409 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/text/manage' ] ] );
check( 'a manager without full access still edits any other role', $status === 200 );

// Granting is bounded by holding (see Roles::notHeld()). The detour a manager
// could take to full access - write a full-access role, create an account
// holding it with a password of its choosing, sign in as that - is refused
// at every step, and so is the narrower version of the same move with any
// permission the manager does not hold itself
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'super', 'label' => 'Super', 'perms' => [ '/*' ] ] );
check( 'a manager may not write a role holding a permission it does not hold itself', $status === 403 && isset( $appData['/nino/auth/roles']['super'] ) === false );
check( '...and the refusal names the permission', str_contains( (string) ( $body['error'] ?? '' ), '/*' ) );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/text/manage', '/_admin/elements/manage' ] ] );
check( 'nor add one to a role that exists', $status === 403 && $appData['/nino/auth/roles']['reviewer']['perms'] === [ '/_admin/text/manage' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'detour@example.com', 'pw' => 'a-long-enough-password', 'role' => 'developer' ] );
check( 'nor create an account with a role wider than its own', $status === 403 && \Nino\Auth::getUser( $appData, 'detour@example.com' ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'detour@example.com', 'pw' => 'a-long-enough-password', 'role' => 'reviewer' ] );
check( '...while one holding only what the manager holds is created', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'detour@example.com', 'role' => 'developer' ] );
check( 'nor move an account onto such a role', $status === 403 && \Nino\Auth::getUser( $appData, 'detour@example.com' )['role'] === 'reviewer' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developers', 'perms' => [ '/*' ] ] );
check( 'a role it may not widen it may still rename - only what a role gains is measured', $status === 200 && $appData['/nino/auth/roles']['developer']['label'] === 'Developers' && $appData['/nino/auth/roles']['developer']['perms'] === [ '/*' ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [ '/*' ] ] );
\Nino\Auth::deleteUser( $appData, 'detour@example.com' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'nope' ] );
check( 'apiDelete 404s for an unknown role', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'editor' ] );
check( 'apiDelete refuses a role an account holds', $status === 409 && isset( $appData['/nino/auth/roles']['editor'] ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'reviewer' ] );
check( 'apiDelete removes a role nobody holds', $status === 200 && isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/roles']['reviewer'] ) === false );

\Nino\Auth::deleteUser( $appData, 'mgr@example.com' );
\Nino\Auth::deleteUser( $appData, 'permsdev@example.com' );
$appData['/nino/auth/user']['dev@example.com']['perms'] = [ '/*' ];
\Nino\Auth::setRole( $appData, 'dev@example.com', '' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Dev\Images -----------------------------------------------------------

echo "Images - image slot definitions (label/width/height only)\n";

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'Hero', 'width' => '1200', 'height' => '600' ] );
check( 'apiCreate creates a slot', $status === 200 );
check( 'the slot starts with no filename', array_key_exists( 'filename', $appData['/nino/html/images']['/home/hero'] ) === true && $appData['/nino/html/images']['/home/hero']['filename'] === null );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => 'not valid', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects an invalid uri', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects a uri that already exists', $status === 409 );

/*	A slot's width and height are the exact canvas \Nino\Images::process()
	renders onto, and the kernel holds no more than MAX_SOURCE_PIXELS of one
	picture in memory. Saved above that the slot cannot be filled by anything:
	measured against the old panel, apiCreate took 20000x20000 with a 200 and
	the upload meant to fill it came back "invalid or oversized image" - which
	sends the person looking at their photograph instead of at the size they
	typed.	*/
[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/wall', 'label' => 'Wall', 'width' => '20000', 'height' => '20000' ] );
check( 'apiCreate refuses a slot no upload could ever fill, with a reason', $status === 400 && ( $body['error'] ?? '' ) !== '' );
check( '...and stores nothing of it', isset( $appData['/nino/html/images']['/home/wall'] ) === false );

// The cap is the kernel's own budget, so the largest slot it can still
// render has to go through - one pixel more is where it stops
$edge = (int) floor( sqrt( \Nino\Images::MAX_SOURCE_PIXELS ) );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/large', 'label' => 'Large', 'width' => (string) $edge, 'height' => (string) $edge ] );
check( 'the largest slot the kernel can still render is accepted', $status === 200 && ( $appData['/nino/html/images']['/home/large']['width'] ?? 0 ) === $edge );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/large', 'label' => 'Large', 'width' => (string) \Nino\Images::MAX_SOURCE_PIXELS, 'height' => '2' ] );
check( 'apiSave refuses to grow an existing slot past the same budget', $status === 400 );
check( '...and leaves the size it had', ( $appData['/nino/html/images']['/home/large']['width'] ?? 0 ) === $edge );

callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/large' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the new slot', in_array( '/home/hero', array_column( $body['slots'], 'uri' ), true ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu', 'width' => '800', 'height' => '400' ] );
check( 'apiSave edits an existing slot', $status === 200 );
check( 'the slot metadata actually changed', $appData['/nino/html/images']['/home/hero']['label'] === 'Hero neu' && $appData['/nino/html/images']['/home/hero']['width'] === 800 );

$appData['/nino/html/images']['/home/hero']['filename'] = 'elements/home/hero.800x400.jpg';
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave never touches an existing filename', $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg' );

// The alt texts live on the slot and are the Images panel's: saving the slot's label and size keeps them
$appData['/nino/html/images']['/home/hero']['alt'] = [ 'de_DE' => 'Ein Bild' ];
callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave keeps the slot\'s alt texts', ( $appData['/nino/html/images']['/home/hero']['alt'] ?? null ) === [ 'de_DE' => 'Ein Bild' ]
	&& ( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['/home/hero']['alt'] ?? null ) === [ 'de_DE' => 'Ein Bild' ] );
unset( $appData['/nino/html/images']['/home/hero']['alt'] );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/does/not/exist', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiSave 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown slot', $status === 404 );

/*	Deleting a slot deletes its uploaded file with it, and the order of those
	two is the whole of it. The file used to go first: a write that does not
	happen - a config.php that cannot be locked, a disk with nothing left -
	then left the slot standing in config.php pointing at a file that is gone,
	the public page rendering a broken <img>, and the panel with nothing to
	re-upload over. Measured against the old code with the sidecar lock of
	config.php made impossible to open: apiDelete answered 200, the file was
	gone, the slot was still in config.php, and the retry 404'd because the
	only copy that had lost it was the one in memory.	*/
\Nino\Filesystem::putFileContent( $appData, '/images/home/hero.800x400.jpg', 'stand-in for the uploaded bytes' );
$heroFile = \Nino\Filesystem::path( $appData, '/images' ). '/home/hero.800x400.jpg';
check( 'the slot has a file to lose', is_file( $heroFile ) === true
	&& $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg' );
$appData['/nino/html/images']['/home/hero']['filename'] = 'home/hero.800x400.jpg';
callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );

// A directory where config.php's sidecar lock file goes: lockFile() cannot
// open it, so writeContentData() refuses to write - the same answer a
// read-only or full disk gives, without needing either
$configLock = $sandbox. '/private/data/.locks/'. sha1( '/config.php' ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $configLock );
@mkdir( $configLock );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'a delete that could not be persisted says so rather than reporting success', $status === 500 );
check( '...and leaves the uploaded file exactly where it was', is_file( $heroFile ) === true );
check( '...and leaves the slot itself, so the delete can simply be repeated', isset( $appData['/nino/html/images']['/home/hero'] ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/home/hero'] ) === true );

@rmdir( $configLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'the slot is gone from appData', isset( $appData['/nino/html/images']['/home/hero'] ) === false );
$imagesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [];
check( 'the slot is gone from config.php too', isset( $imagesAfterDelete['/home/hero'] ) === false );
check( '...and now that the record is persisted, the file went with it', is_file( $heroFile ) === false );

echo "\n";


// --- Dev\Text ---------------------------------------------------------------

echo "Text - text key schema (existence, global/per-locale, blacklist)\n";

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/template/page-home/welcome/subtitle', 'global' => false, 'value' => 'Start' ] );
check( 'apiCreate creates a per-locale key', $status === 200 );

$deDE = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUS = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the initial value is written into every available locale', $deDE['[[/template/page-home/welcome/subtitle]]'] === 'Start' && $enUS['[[/template/page-home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/company/general/tagline', 'global' => true, 'value' => 'Immer' ] );
check( 'apiCreate creates a global key', $status === 200 );

$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the initial value is written into global.php, not any locale file', $global['[[/project/company/general/tagline]]'] === 'Immer' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => 'not-a-valid-key', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects an invalid key', $status === 400 );

// The server decides what a key made by hand looks like - /<namespace>/<category>/<part>/<name>,
// whatever the form sent - and says so in a sentence that names the shape
$textBefore = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
foreach( [ '/foo/bar', '/template/page-home/title', '/_nino/webpage/x/title', '/_admin/x/y/z', '/Template/x/y/z', '/template/page_home/x/y', '/project/a/b/c/d' ] as $badKey ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => $badKey, 'global' => true, 'value' => 'x' ] );
	check( "apiCreate refuses $badKey with the code and the field", $status === 400 && ( $body['code'] ?? '' ) === 'keys_invalid' && ( $body['params'] ?? [] ) === [ $badKey ] && ( $body['field'] ?? '' ) === 'key' );
}
check( '...and writes nothing for any of them', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) === $textBefore );
foreach( [ 'en_US', 'de_DE' ] as $grammarLocale )
	check( "$grammarLocale: the sentence of the refusal names the form", str_contains( (string) ( include __DIR__. '/../_admin/Nino/Modules/Text/text/'. $grammarLocale. '.php' )['[[/_admin/error/keys_invalid]]'], '/<'. ( $grammarLocale === 'en_US' ? 'namespace' : 'namensraum' ). '>/<'. ( $grammarLocale === 'en_US' ? 'category' : 'kategorie' ). '>/<'. ( $grammarLocale === 'en_US' ? 'part' : 'teil' ). '>/<name>' ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/catalog/list/title', 'global' => false, 'value' => 'Katalog' ] );
check( 'a key that follows the grammar is created', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/catalog/list/title]]'] ?? null ) === 'Katalog' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/catalog/list/title' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/company/general/tagline', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects a key that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
$subtitleEntry = null;
foreach( $body['keys'] as $entry ) if( $entry['key'] === '/template/page-home/welcome/subtitle' ) $subtitleEntry = $entry;
check( 'apiList finds the new per-locale key, not blacklisted by default', $subtitleEntry !== null && $subtitleEntry['global'] === false && $subtitleEntry['blacklisted'] === false );

// What the form that creates or renames a key offers to choose from, per namespace
$templatesDir = $sandbox. '/private/templates';
$madeTemplatesDir = is_dir( $templatesDir ) === false && mkdir( $templatesDir, 0777, true );
foreach( [ 'page-extra', 'frame-header', 'page-Foo', 'page-legal.de_DE' ] as $categoryFile )
	file_put_contents( $templatesDir. '/'. $categoryFile. '.tpl', '' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/shop/list/title', 'global' => false, 'value' => 'Shop' ] );
$appData['./nino/features/all'] = [ 'sample' => [ 'key' => 'sample', 'dir' => $sandbox. '/sample-feature', 'name' => 'Sample' ], 'shop' => [ 'key' => 'shop', 'dir' => $sandbox. '/shop-feature', 'name' => 'Shop' ] ];
[ , $listBody ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
unset( $appData['./nino/features/all'] );
check( 'apiList names the language of the session, which the tab opens in', ( $listBody['selectedLocale'] ?? '' ) === \Nino\Admin\Admin::sessionLocale( $appData ) );
check( 'apiList offers /template the templates directly in templates/ whose name is a category, and "common" first',
	array_slice( $listBody['categories']['template'] ?? [], 0, 1 ) === [ 'common' ] && array_intersect( [ 'frame-header', 'page-extra' ], $listBody['categories']['template'] ?? [] ) === [ 'frame-header', 'page-extra' ] );
check( '...none whose name is no word of a key', in_array( 'page-Foo', $listBody['categories']['template'] ?? [], true ) === false && in_array( 'page-legal.de_DE', $listBody['categories']['template'] ?? [], true ) === false );
check( '/module is the kernel\'s modules, by their directory in lower case', in_array( 'form', $listBody['categories']['module'] ?? [], true ) === true && in_array( 'maintenance', $listBody['categories']['module'] ?? [], true ) === true
	&& in_array( 'Form', $listBody['categories']['module'] ?? [], true ) === false && in_array( 'modules.php', $listBody['categories']['module'] ?? [], true ) === false );
check( '/project is company, website and mail, then the categories keys already use', array_slice( $listBody['categories']['project'] ?? [], 0, 3 ) === [ 'company', 'website', 'mail' ] && in_array( 'shop', $listBody['categories']['project'] ?? [], true ) === true );
check( '/feature is the features that are installed, by their key', ( $listBody['categories']['feature'] ?? null ) === [ 'sample', 'shop' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/shop/list/title' ] );
foreach( [ 'page-extra', 'frame-header', 'page-Foo', 'page-legal.de_DE' ] as $categoryFile )
	unlink( $templatesDir. '/'. $categoryFile. '.tpl' );
if( $madeTemplatesDir === true )
	rmdir( $templatesDir );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/subtitle', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave converts a per-locale key to global', $status === 200 );

$deDEAfter = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'converting to global removes it from every locale file', isset( $deDEAfter['[[/template/page-home/welcome/subtitle]]'] ) === false );
check( 'converting to global migrates the value instead of discarding it', $globalAfter['[[/template/page-home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/subtitle', 'global' => false, 'blacklisted' => true ] );
check( 'apiSave converts back to per-locale and blacklists it in the same call', $status === 200 );

$deDEAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklist = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'converting back to per-locale migrates the value into every locale', $deDEAfter2['[[/template/page-home/welcome/subtitle]]'] === 'Start' );
check( 'converting back to per-locale removes it from global.php', isset( $globalAfter2['[[/template/page-home/welcome/subtitle]]'] ) === false );
check( 'the key is now blacklisted', in_array( '/template/page-home/welcome/subtitle', $blacklist, true ) === true );

/*	Per-locale -> global keeps the native locale's value and, says apiSave()'s
	docblock, falls back to the first non-empty one. '??' only steps aside for
	a null, and a locale file carrying the key with an empty string is not
	null (see \Nino\Text::entries()) - so a key nobody has written in the
	project's own language yet, which is what a fresh translation looks like
	until somebody gets to it, converted to global as '' and took the one
	language that did have text with it.	*/
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/template/page-home/welcome/claim', 'global' => false, 'value' => '' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/template/page-home/welcome/claim', 'locale' => 'en_US', 'value' => 'Nothing but the truth' ],
] ] );

$claimLocales = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the native locale carries the key with an empty value, which is not the same as not carrying it',
	array_key_exists( '[[/template/page-home/welcome/claim]]', $claimLocales ) === true && $claimLocales['[[/template/page-home/welcome/claim]]'] === '' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => true, 'blacklisted' => false ] );
check( 'an empty native value falls back to the first locale that has one, as the docblock says', $status === 200
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/template/page-home/welcome/claim]]'] ?? null ) === 'Nothing but the truth' );

callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => false, 'blacklisted' => false ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/template/page-home/welcome/claim', 'locale' => 'de_DE', 'value' => 'Nichts als die Wahrheit' ],
] ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => true, 'blacklisted' => false ] );
check( '...while a native locale that does have a value still wins over every other one',
	( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/template/page-home/welcome/claim]]'] ?? null ) === 'Nichts als die Wahrheit' );

// Empty in every language is the one case where '' really is the value: the
// key still exists, and converting it must not invent text for it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => false, 'blacklisted' => false ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => array_map(
	static fn( string $locale ): array => [ 'key' => '/template/page-home/welcome/claim', 'locale' => $locale, 'value' => '' ],
	\Nino\Locales::getAvailableLocales( $appData )
) ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => true, 'blacklisted' => false ] );
$claimEmpty = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'a key that is empty everywhere converts to an empty global value, not to no key at all',
	array_key_exists( '[[/template/page-home/welcome/claim]]', $claimEmpty ) === true && $claimEmpty['[[/template/page-home/welcome/claim]]'] === '' );

callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/template/page-home/welcome/claim' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/does/not/exist', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave 404s for an unknown key', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
	[ 'key' => '/template/page-home/welcome/subtitle', 'locale' => 'de_DE', 'value' => 'Neuer Start' ],
] ] );
check( 'apiSaveBatch succeeds', $status === 200 );
check( 'apiSaveBatch saves a global value', $body['results']['/project/company/general/tagline']['ok'] === true );
check( 'apiSaveBatch saves a blacklisted key\'s value too - blacklist only hides it from _editor', $body['results']['/template/page-home/welcome/subtitle']['ok'] === true );

$globalAfterBatch = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$deDEAfterBatch 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the global value actually landed in global.php', $globalAfterBatch['[[/project/company/general/tagline]]'] === 'Immer und ewig' );
check( 'the per-locale value actually landed in the right locale file', $deDEAfterBatch['[[/template/page-home/welcome/subtitle]]'] === 'Neuer Start' );

// html is auto-detected from a key's *current* value (see _entries()) - create one
// that already holds a whitelisted tag, so this save actually exercises sanitizeHtml()
// rather than the plain strip_tags() path a fresh/plain-text key would get
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/company/general/note', 'global' => true, 'value' => '<strong>Wichtig</strong>' ] );
check( 'apiCreate creates the html-flagged fixture key', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/note', 'locale' => '*', 'value' => '<script>alert(1)</script><strong>Wichtig</strong><em>auch</em><code>x()</code>' ],
] ] );
check( 'apiSaveBatch sanitizes html and preserves inline code', $body['results']['/project/company/general/note']['value'] === '<strong>Wichtig</strong><em>auch</em><code>x()</code>' );

// An unbalanced closing tag is what pasting from a web page looks like, and
// the sanitizer parsed the value inside a <div> of its own - so the first
// stray </div> closed that wrapper and everything after it was read as
// standing outside the value and dropped, silently, on save
check( 'a stray closing div does not cut the rest of the value off',
	\Nino\Html::sanitizeHtml( 'pasted <strong>one</strong></div> and the rest' ) === 'pasted <strong>one</strong> and the rest' );
check( '...wherever it stands', \Nino\Html::sanitizeHtml( 'a</div>b <em>c</em>' ) === 'ab <em>c</em>' );
check( 'a balanced block still contributes its text', \Nino\Html::sanitizeHtml( 'before <div>inside</div> after' ) === 'before inside after' );

// A value a developer wrote as something other than a string - an int year,
// a list - used to reach strlen() under strict_types, which is a TypeError,
// which the error handler answers with a 500: the Text panel, the Keys panel
// and the Language panel all stopped opening until somebody found the line
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) + [
	'[[/company/founded]]'	=> 2024,
	'[[/project/company/general/list]]'			=> [ 'a', 'b' ],
] );
unset( $appData['./nino/filesystem/cache'] );

$oddEntries = \Nino\Text::entries( $appData, true );
$oddKeys		= array_column( $oddEntries, 'key' );
check( 'a text value that is not a string does not stop the panel opening', in_array( '/company/founded', $oddKeys, true ) === true );
check( '...and a number is shown as the text it stands for', ( $oddEntries[ array_search( '/company/founded', $oddKeys, true ) ]['values']['*'] ?? null ) === '2024' );
check( '...while a value that is no text at all is left out rather than rendered as one', in_array( '/project/company/general/list', $oddKeys, true ) === false );

// A plain-text value is substituted raw by Html::_renderFills(), attribute
// values included ('<meta name="author" content="[[/project/website/general/author]]">'), so
// a stored quote is an attribute break-out that strip_tags() never sees.
// Entities render as the character itself in both contexts, and re-encode to
// themselves on a re-save
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'x" onmouseover="alert(1)' ],
] ] );
check( 'apiSaveBatch entity-encodes quotes in a plain-text value', $body['results']['/project/company/general/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'x&quot; onmouseover=&quot;alert(1)' ],
] ] );
check( '...and saving that value again does not escape it a second time', $body['results']['/project/company/general/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
] ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/does/not/exist', 'locale' => '*', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch reports an unknown key without failing the whole request', $status === 200 && $body['results']['/does/not/exist']['ok'] === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/template/page-home/welcome/subtitle', 'locale' => 'xx_XX', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch rejects an invalid locale for a per-locale key', $body['results']['/template/page-home/welcome/subtitle']['ok'] === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/tagline', 'newKey' => '/project/company/general/motto' ] );
check( 'apiRename succeeds for a global key', $status === 200 );

$globalAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the old key is gone from global.php', isset( $globalAfterRename['[[/project/company/general/tagline]]'] ) === false );
check( 'the value moved to the new key', $globalAfterRename['[[/project/company/general/motto]]'] === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/motto', 'newKey' => 'not-a-valid-key' ] );
check( 'apiRename rejects an invalid new key', $status === 400 );

// Renaming holds the new name to the grammar as well, and leaves a key of the
// system or the workbench where it is - its name is what the code that reads it asks for
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/webpage/legacy/name]]'] = 'Legacy';
	$global['[[/old/free/form]]'] = 'Frei';
	$global['[[/_admin/legacy/word/name]]'] = 'Wort';
	return $global;
} );
foreach( [ '/foo/bar', '/template/page-home/title', '/_nino/webpage/x/title', '/_admin/x/y/z' ] as $badKey ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/old/free/form', 'newKey' => $badKey ] );
	check( "apiRename refuses $badKey as the new name", $status === 400 && ( $body['code'] ?? '' ) === 'keys_invalid' && ( $body['field'] ?? '' ) === 'newKey' );
}
check( '...and the key is where it was', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/old/free/form]]'] ?? null ) === 'Frei' );
foreach( [ '/_nino/webpage/legacy/name', '/_admin/legacy/word/name' ] as $systemKey ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => $systemKey, 'newKey' => '/project/legacy/page/name' ] );
	check( "apiRename refuses to rename $systemKey, which is not a project's to name", $status === 400 && ( $body['code'] ?? '' ) === 'keys_system' && ( $body['params'] ?? [] ) === [ $systemKey ] );
}
check( '...and the value of a system key stays', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/legacy/name]]'] ?? null ) === 'Legacy' );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/_nino/webpage/legacy/name', 'global' => true, 'blacklisted' => false ] );
check( 'a key that does not follow the grammar stays saveable - what is there is not touched', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/old/free/form', 'global' => true, 'blacklisted' => true ] );
check( '...and can be hidden', $status === 200 && in_array( '/old/free/form', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true );
\Nino\Text::setBlacklisted( $appData, '/old/free/form', false );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/old/free/form', 'newKey' => '/project/old/free/form' ] );
check( '...and renamed to one that follows the grammar', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/old/free/form]]'] ?? null ) === 'Frei'
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/old/free/form]]'] ) === false );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/old/free/form]]'] = 'Frei';
	return $global;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/old/free/form' ] );
check( '...and deleted', $status === 200 && isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/old/free/form]]'] ) === false );
foreach( [ '/project/old/free/form', '/_nino/webpage/legacy/name', '/_admin/legacy/word/name' ] as $cleanKey )
	callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => $cleanKey ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/motto', 'newKey' => '/project/company/general/note' ] );
check( 'apiRename rejects a new key that already exists', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/does/not/exist', 'newKey' => '/project/also/not/new' ] );
check( 'apiRename 404s for an unknown key', $status === 404 );

// Renaming a key to the name it already has is a no-op, not a delete. The
// mutate pair below it ("write the new bracket, unset the old one") collapses
// into a plain unset when both are the same string, so this used to answer 200
// and drop the value - text.js guards it in the ui, the endpoint has to too
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/motto', 'newKey' => '/project/company/general/motto' ] );
check( 'apiRename accepts a rename to the key\'s own name', $status === 200 );
check( '...and leaves the value where it was instead of deleting it', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/company/general/motto]]'] ?? null ) === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/company/general/note' ] );
check( 'apiDelete succeeds for a global key', $status === 200 );

$globalAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the deleted global key is gone', isset( $globalAfterDelete['[[/project/company/general/note]]'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/template/page-home/welcome/subtitle' ] );
check( 'apiDelete succeeds for a blacklisted per-locale key', $status === 200 );

$deDEAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUSAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$blacklistAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the deleted per-locale key is gone from every locale file', isset( $deDEAfterDelete['[[/template/page-home/welcome/subtitle]]'] ) === false && isset( $enUSAfterDelete['[[/template/page-home/welcome/subtitle]]'] ) === false );
check( 'the deleted key is also gone from the blacklist', in_array( '/template/page-home/welcome/subtitle', $blacklistAfterDelete, true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown key', $status === 404 );

echo "\n";


// --- Text keys: format and limit ----------------------------------------------

echo "Text keys - format and limit, per key\n";

$keyEntry = static function( array &$appData, string $key ): ?array {
	[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
	foreach( $body['keys'] as $entry )
		if( $entry['key'] === $key )
			return $entry;
	return null;
};
$meta = static fn(): array => \Nino\Filesystem::getFileContent( $appData, \Nino\Text::META_PATH, [] );

// A key that holds paragraphs, in a global file and in both locale files
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/body', 'global' => false, 'value' => '<p>Eins</p><ul><li>a</li></ul><p>Zwei<br>drei</p>' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/note', 'global' => true, 'value' => "Zeile 1\nZeile 2" ] );

check( 'a key created with paragraphs is read as blocks, from its value', ( $keyEntry( $appData, '/project/notes/page/body' )['format'] ?? '' ) === 'blocks' && ( $keyEntry( $appData, '/project/notes/page/body' )['formatSet'] ?? true ) === false );
check( '...and a plain one is stored as it was written', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/note]]'] ?? '' ) === "Zeile 1\nZeile 2" );

// An absent format or limit is "unchanged": the two checkboxes post without them
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false ] );
check( 'a save without a format or a limit writes no meta', $status === 200 && $meta() === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'blocks', 'maxlength' => 900 ] );
check( 'a format and a limit are written to /text/meta.php, keyed by the key', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 900 ] );
$legal = $keyEntry( $appData, '/project/notes/page/body' );
check( '...and the list reports them, and that they were set', ( $legal['format'] ?? '' ) === 'blocks' && ( $legal['maxlength'] ?? 0 ) === 900 && ( $legal['formatSet'] ?? false ) === true && ( $legal['maxlengthSet'] ?? false ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => true ] );
check( 'a later save that leaves them out leaves them as they are', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 900 ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => null ] );
check( 'a limit posted as null is automatic again; the format stays', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'blocks' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'auto' ] );
check( '"auto" forgets the format, and the entry that is left with nothing is dropped', $status === 200 && array_key_exists( '/project/notes/page/body', $meta() ) === false );
check( '...without touching the values: they are paragraphs still', ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === '<p>Eins</p><ul><li>a</li></ul><p>Zwei<br>drei</p>' );

// Refusals
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'sideways' ] );
check( 'a format that does not exist is a 400 with a code', $status === 400 && ( $body['code'] ?? '' ) === 'keys_format' && ( $body['field'] ?? '' ) === 'format' );
foreach( [ 0, -3, \Nino\Text::MAX_LIMIT + 1, 'many', 2.5, [ 5 ] ] as $badLimit ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => $badLimit ] );
	check( 'a limit of '. json_encode( $badLimit ). ' is a 400 that names the range', $status === 400 && ( $body['code'] ?? '' ) === 'keys_limit' && ( $body['params'] ?? [] ) === [ \Nino\Text::MAX_LIMIT ] );
}
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => 6 ] );
check( 'a limit below the longest text the key holds is refused, with that length - the editor would cut the text at it', $status === 400 && ( $body['code'] ?? '' ) === 'keys_limit_short' && ( $body['params'] ?? [] ) === [ 13 ] );
check( '...and nothing was written', $meta() === [] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => 13 ] );
check( 'a limit that is exactly as long as the text is fine', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'maxlength' => 13 ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => null ] );

// Narrowing converts every stored value, in every locale file
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [ [ 'key' => '/project/notes/page/body', 'locale' => 'en_US', 'value' => '<p>One</p><ol><li>a</li><li>b</li></ol><p><strong>Two</strong></p>' ] ] ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'plain' ] );
$deConverted = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '';
$enConverted = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/project/notes/page/body]]'] ?? '';
check( 'blocks to plain keeps the words and makes lines of the paragraphs, the items and the breaks, in the first locale', $deConverted === "Eins\na\nZwei\ndrei" );
check( '...and in every other', $enConverted === "One\na\nb\nTwo" );
check( '...and the format is the one that was asked for', ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'plain' ] && ( $keyEntry( $appData, '/project/notes/page/body' )['html'] ?? true ) === false );

// Widening: a newline becomes what the format makes of it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'lines' ] );
check( 'plain to lines makes a <br> of every newline, in every locale file',
	( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === 'Eins<br>a<br>Zwei<br>drei'
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === 'One<br>a<br>b<br>Two' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'blocks' ] );
check( 'lines to blocks makes one paragraph of it', ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === '<p>Eins<br>a<br>Zwei<br>drei</p>' );

// The same for a global key, and the shape change that comes with it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/note', 'global' => true, 'blacklisted' => false, 'format' => 'lines' ] );
check( 'a global key is converted in global.php', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/note]]'] ?? '' ) === 'Zeile 1<br>Zeile 2' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/note', 'global' => false, 'blacklisted' => false, 'format' => 'plain' ] );
check( 'a key that changes its shape and its format in one save lands in the new shape, converted',
	( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/note]]'] ?? '' ) === "Zeile 1\nZeile 2"
	&& array_key_exists( '[[/project/notes/page/note]]', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) ) === false );

// The log line says what was posted
$logged = \Nino\Modules\Text\Keys::log( 'keys/save', [ 'key' => '/project/notes/page/body', 'format' => 'lines', 'maxlength' => 400 ] );
check( 'the log names the format and the limit of a save that carries them, and nothing for one that does not', $logged === 'Edit Text Key /project/notes/page/body (format lines, limit 400)' && \Nino\Modules\Text\Keys::log( 'keys/save', [ 'key' => '/project/notes/page/body', 'global' => true ] ) === '' );

// Rename: both branches move the meta; delete drops it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'blocks', 'maxlength' => 700 ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/notes/page/body', 'newKey' => '/project/notes/page/text' ] );
check( 'a rename takes the format and the limit to the new name and leaves nothing behind', ( $meta()['/project/notes/page/text'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 700 ] && array_key_exists( '/project/notes/page/body', $meta() ) === false );

\Nino\Text::setBlacklisted( $appData, '/project/notes/page/retired', true );
\Nino\Text::setMeta( $appData, '/project/notes/page/retired', 'lines', null );
check( 'a retired key, which has no value anywhere, lists with the format it was given', ( $keyEntry( $appData, '/project/notes/page/retired' )['format'] ?? '' ) === 'lines' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/notes/page/retired', 'newKey' => '/project/notes/page/gone' ] );
check( 'a rename of a retired key moves the meta too', ( $meta()['/project/notes/page/gone'] ?? null ) === [ 'format' => 'lines' ] && array_key_exists( '/project/notes/page/retired', $meta() ) === false );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/gone' ] );
check( 'deleting a retired key drops its meta', array_key_exists( '/project/notes/page/gone', $meta() ) === false );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/text' ] );
check( 'deleting a key drops its meta', array_key_exists( '/project/notes/page/text', $meta() ) === false );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/note' ] );

// Creating: through the format, like any value saved from the workbench
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/made', 'global' => true, 'value' => "a\nb<script>x</script>", 'format' => 'lines' ] );
check( 'apiCreate sanitizes the initial value with the format that was asked for, and remembers the choice', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/made]]'] ?? '' ) === 'a<br>b' && ( $meta()['/project/notes/page/made'] ?? null ) === [ 'format' => 'lines' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/read', 'global' => true, 'value' => '<em>x</em><script>y</script> [template /templates/mail-owner]' ] );
check( '...and without one reads it from the value - shortcodes and scripts are not stored, the choice is not remembered', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/read]]'] ?? '' ) === '<em>x</em> &#91;template /templates/mail-owner&#93;' && array_key_exists( '/project/notes/page/read', $meta() ) === false );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/never', 'global' => true, 'value' => 'x', 'format' => 'sideways' ] );
check( 'apiCreate refuses a format that does not exist, and creates nothing', $status === 400 && ( $body['code'] ?? '' ) === 'keys_format' && \Nino\Text::entry( $appData, '/project/notes/page/never' ) === null );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/made' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/read' ] );
check( 'both are cleaned up again', $meta() === [] );

echo "\n";


// --- The vocabulary: the words that name a part of a key -----------------------------

echo "Text - the closed vocabulary of the words /_admin/common/word/*\n";

$wordFills = [];
foreach( [ 'en_US', 'de_DE' ] as $vocabLocale ) {
	$wordFills[$vocabLocale] = [];
	foreach( include __DIR__. '/../_admin/text/'. $vocabLocale. '.php' as $bracketKey => $wordText )
		if( str_starts_with( $bracketKey, '[[/_admin/common/word/' ) === true )
			$wordFills[$vocabLocale][substr( $bracketKey, strlen( '[[/_admin/common/word/' ), -2 )] = $wordText;
}
$words = array_keys( $wordFills['en_US'] );
check( 'the vocabulary is 133 words, the same in English and in German, each with a text', count( $words ) === 133 && array_keys( $wordFills['de_DE'] ) === $words
	&& array_filter( array_merge( $wordFills['en_US'], $wordFills['de_DE'] ), static fn( string $text ): bool => trim( $text ) === '' ) === [] );
check( 'a word is a slug: lower-case words joined by hyphens, one for each slug, in the alphabet', array_filter( $words, static fn( string $slug ): bool => preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) !== 1 ) === [] && $words === array_values( array_unique( $words ) ) && $words === ( static function( array $sorted ): array { sort( $sorted ); return $sorted; } )( $words ) );
check( 'no word is an alias of another: no two slugs share a text in a language', count( array_unique( $wordFills['en_US'] ) ) === count( $words ) && count( array_unique( $wordFills['de_DE'] ) ) === count( $words ) );
$keyWords = [ 'title', 'subtitle', 'text', 'description', 'image', 'alt', 'caption', 'link', 'label', 'name', 'email', 'phone', 'address', 'date', 'author', 'price', 'icon' ];
check( 'the 17 words the Element Type editor offers as the key of a field are in the vocabulary', array_diff( $keyWords, $words ) === [] && preg_match( "/KEY_WORDS\s*:\s*\[ '". implode( "', '", $keyWords ). "' \]/", (string) file_get_contents( __DIR__. '/../_admin/Nino/Modules/Elements/assets/types.js' ) ) === 1 );
check( 'a word names no key and is no key: no fill, no slash in any of them', array_filter( array_merge( $wordFills['en_US'], $wordFills['de_DE'] ), static fn( string $text ): bool => preg_match( '/\[\[|\//', $text ) === 1 ) === [] );

// Every segment of every key the kernel ships is a word, or falls apart into words: a part, a name, the entry of a list,
// a category that names a group. Excepted are the categories of the page templates with a route - the row of a page is
// named after its route - the keys of a feature, whose row is named in its manifest, and the two parts of an image slot a
// page's template shares with its text keys
$slugCovered = static function( string $slug ) use ( $words, &$slugCovered ): bool {
	if( in_array( $slug, $words, true ) === true )
		return true;
	if( preg_match( '/^(.+)-\d+$/', $slug, $numbered ) === 1 )
		return $slugCovered( $numbered[1] );
	for( $dash = strpos( $slug, '-' ); $dash !== false; $dash = strpos( $slug, '-', $dash + 1 ) )
		if( $dash > 0 && in_array( substr( $slug, $dash + 1 ), $words, true ) === true && in_array( substr( $slug, 0, $dash ), $words, true ) === true )
			return true;
	return false;
};
$shippedKeys = [];
foreach( array_merge( glob( dirname( __DIR__ ). '/_admin/install/library/*/text/*.php' ) ?: [], glob( dirname( __DIR__ ). '/_admin/install/library/pages/*/text/*.php' ) ?: [], glob( dirname( __DIR__ ). '/_admin/install/library/pages/.*/text/*.php' ) ?: [], glob( dirname( __DIR__ ). '/_nino/Nino/Modules/*/install/text/*.php' ) ?: [] ) as $fragment )
	foreach( array_keys( (array) include $fragment ) as $bracketKey )
		$shippedKeys[trim( $bracketKey, '[]' )] = true;
$uncovered = [];
$counted = 0;
foreach( array_keys( $shippedKeys ) as $shippedKey ) {
	if( preg_match( '#^/(template|project|feature|module)/([^/]+)/([^/]+)/([^/]+)$#', $shippedKey, $segments ) !== 1 )
		continue;
	$counted++;
	foreach( [ 'category' => $segments[2], 'part' => $segments[3], 'name' => $segments[4] ] as $role => $slug ) {
		if( $role === 'category' && ( $segments[1] === 'feature' || ( $segments[1] === 'template' && str_starts_with( $slug, 'page-' ) === true ) ) )
			continue;
		if( in_array( $slug, [ 'contact-form', 'fullscreen-image' ], true ) === true )
			continue;
		if( $slugCovered( $slug ) === false )
			$uncovered[$role. ' '. $slug] = $shippedKey;
	}
}
check( 'every part, name and group of the '. $counted. ' keys the kernel ships is a word of the vocabulary or falls apart into words'. ( $uncovered === [] ? '' : ' - '. implode( '; ', array_slice( array_keys( $uncovered ), 0, 8 ) ) ), $uncovered === [] && $counted > 50 );

// --- Text::apiScan / Images::apiScan: template scanners ---------------------

echo "Text::apiScan - missing [[/key]] placeholders in templates/*.tpl\n";

mkdir( $sandbox. '/private/templates', 0777, true );
file_put_contents( $sandbox. '/private/templates/scan-fixture.tpl', '<p>[[/template/page-scan-test/intro/heading]]</p><p>[[/template/page-scan-test/intro/heading]]</p><p>[[/project/company/general/name]]</p>' );
// A nested, dynamically-constructed fill (same shape as html-header.tpl's real
// [[/_nino/webpage[[/nino/http/response/uri]]/title]]) - only the inner, always-
// registered kernel fill should ever be visible to a static regex scan
file_put_contents( $sandbox. '/private/templates/scan-fixture-2.tpl', '<title>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</title><img src="[[/nino/public]]/images/example.jpg">' );
// Every fill the kernel registers at runtime, as the kernel names them - the
// scan used to keep its own copy of that list, and the copy was one short:
// a template with the clean uri made the Dashboard report a missing key
$runtimeFillKeys = method_exists( '\\Nino\\Html', 'runtimeFillKeys' ) === true ? \Nino\Html::runtimeFillKeys( $appData ) : [];
file_put_contents( $sandbox. '/private/templates/scan-fixture-3.tpl', '<body class="p-[[/nino/http/response/uri/clean]]">'. implode( ' ', array_map( fn( string $key ): string => '[['. $key. ']]', $runtimeFillKeys ) ). '</body>' );

// /project/company/general/name is genuinely defined (unlike /template/page-scan-test/intro/heading) - proves
// a real key is correctly excluded, not just absent from the fixture by accident
$globalFixture = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$globalFixture['[[/project/company/general/name]]'] = 'Acme';
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $globalFixture );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );

$missingKeys = array_column( $body['missing'], 'key' );
check( 'a genuinely undefined key is reported', in_array( '/template/page-scan-test/intro/heading', $missingKeys, true ) === true );
check( 'an undefined key found twice in the same file is only reported once', count( array_filter( $missingKeys, fn( $k ) => $k === '/template/page-scan-test/intro/heading' ) ) === 1 );
check( 'an already-defined key is not reported', in_array( '/project/company/general/name', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/http/response/uri fill is never reported, despite appearing inside a nested [[...]] construct', in_array( '/nino/http/response/uri', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/public fill is never reported as missing', in_array( '/nino/public', $missingKeys, true ) === false );
check( 'the clean uri the kernel fills at runtime is never reported as missing', in_array( '/nino/http/response/uri/clean', $missingKeys, true ) === false );
$reportedRuntimeFills = array_values( array_intersect( $runtimeFillKeys, $missingKeys ) );
check( 'no fill the kernel names as one it registers at runtime is reported - the scan reads that list, it keeps no copy'. ( $reportedRuntimeFills === [] ? '' : ' - reported: '. implode( ', ', $reportedRuntimeFills ) ), count( $runtimeFillKeys ) >= 8 && $reportedRuntimeFills === [] );

unlink( $sandbox. '/private/templates/scan-fixture.tpl' );
unlink( $sandbox. '/private/templates/scan-fixture-2.tpl' );
unlink( $sandbox. '/private/templates/scan-fixture-3.tpl' );

// --- what the scan says about each key, and what it leaves alone ----------
//
// Every key a template reads and nothing defines is a row, whether it can be
// created here or not: three kinds - 'create' (it follows the grammar),
// 'system' (/_nino, written by a panel) and 'grammar' (anything else). A
// placeholder with no leading slash is the template's own shortcode's and no key.
\Nino\Filesystem::putFileContent( $appData, '/templates/scan-kinds.tpl', implode( "\n", [
	'[[/template/scan-kinds/intro/title]]',
	'[[/feature/consent/banner/title]]',
	'[[/module/form/info/scan-extra]]',
	'[[/foo/bar]]',
	'[[/_admin/scan/word/name]]',
	'[[/_nino/webpage/contact/uri]]',
	'[[/_nino/webpage/about/team/title]]',
	'[[/_nino/locale/fr_CA/name]]',
	'[[/_nino/webpage/.blog/uri]]',
	'[[/_nino/webpage/.blog/name]]',
	'[[/_nino/something/else/here]]',
	'[[name]] [[email]] [[message]] [[date]] [[subject]] [[.rel]] [[category]] [[section:id]]',
	'[[/feature/consent/category-[[category]]/name]]',
] ) );
// A feature's page, routed at runtime: its Element-URI is /.blog
$appData['/nino/http/routes']['GET://blog/*'] = [ 'uri' => '/.blog', 'body' => '[template /templates/page-posts]' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
$kinds = array_column( $body['missing'], null, 'key' );
check( 'the scan reports a key that follows the grammar as one to create', $status === 200 && ( $kinds['/template/scan-kinds/intro/title']['kind'] ?? '' ) === 'create' && ( $kinds['/template/scan-kinds/intro/title']['files'] ?? [] ) === [ 'scan-kinds.tpl' ] );
check( '...a /feature key with the note where it normally comes from - and still to create', ( $kinds['/feature/consent/banner/title']['kind'] ?? '' ) === 'create' && ( $kinds['/feature/consent/banner/title']['hint'] ?? '' ) === 'feature' && ( $kinds['/feature/consent/banner/title']['owner'] ?? '' ) === 'consent' );
check( '...a /module key the same', ( $kinds['/module/form/info/scan-extra']['hint'] ?? '' ) === 'module' && ( $kinds['/module/form/info/scan-extra']['owner'] ?? '' ) === 'form' );
check( 'a key off the grammar is a "grammar" row, a key of the workbench too', ( $kinds['/foo/bar']['kind'] ?? '' ) === 'grammar' && ( $kinds['/_admin/scan/word/name']['kind'] ?? '' ) === 'grammar' && array_key_exists( 'hint', $kinds['/foo/bar'] ) === true && $kinds['/foo/bar']['hint'] === null );
check( 'a page\'s key is a "system" row whose writer is the Routes panel, however many slashes its Element-URI has', ( $kinds['/_nino/webpage/contact/uri']['kind'] ?? '' ) === 'system' && ( $kinds['/_nino/webpage/contact/uri']['writer'] ?? '' ) === 'routes'
	&& ( $kinds['/_nino/webpage/about/team/title']['writer'] ?? '' ) === 'routes' );
check( '...a language\'s name one whose writer is the Language panel', ( $kinds['/_nino/locale/fr_CA/name']['kind'] ?? '' ) === 'system' && ( $kinds['/_nino/locale/fr_CA/name']['writer'] ?? '' ) === 'language' );
check( '...the name of a feature\'s page one the Routes panel writes, but not its path, which the feature decides', ( $kinds['/_nino/webpage/.blog/name']['writer'] ?? '' ) === 'routes' && ( $kinds['/_nino/webpage/.blog/uri']['kind'] ?? '' ) === 'system' && array_key_exists( 'writer', $kinds['/_nino/webpage/.blog/uri'] ) === true && $kinds['/_nino/webpage/.blog/uri']['writer'] === null );
check( '...and another form of the system one nobody writes', ( $kinds['/_nino/something/else/here']['kind'] ?? '' ) === 'system' && array_key_exists( 'writer', $kinds['/_nino/something/else/here'] ) === true && $kinds['/_nino/something/else/here']['writer'] === null );
foreach( [ 'name', 'email', 'message', 'date', 'subject', '.rel', 'category', 'section:id', 'category-[[category' ] as $placeholder )
	check( "a placeholder of the template's own - [[$placeholder]] - is no key and is not reported", isset( $kinds[$placeholder] ) === false && isset( $kinds['/'. $placeholder] ) === false );
check( '...nor is the outer half of a key built from one', array_filter( array_keys( $kinds ), static fn( string $key ): bool => str_contains( $key, 'category-' ) ) === [] );
check( 'the Dashboard counts every row, the ones that cannot be created here as well', \Nino\Modules\Text\Keys::missingCount( $appData ) === count( $body['missing'] ) && count( $body['missing'] ) === 11 );

// What the apply does with each kind
[ $status, $applied ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [
	[ 'key' => '/template/scan-kinds/intro/title', 'value' => 'Titel', 'ignore' => false ],
	[ 'key' => '/foo/bar', 'value' => 'Nein', 'ignore' => false ],
	[ 'key' => '/_admin/scan/word/name', 'value' => '', 'ignore' => true ],
	[ 'key' => '/_nino/webpage/contact/uri', 'value' => '/kontakt', 'ignore' => false ],
	[ 'key' => '/_nino/locale/fr_CA/name', 'value' => '', 'ignore' => true ],
] ] );
$deScan = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$blacklistScan = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'apiScanApply creates only the keys that may be created here', $status === 200 && ( $deScan['[[/template/scan-kinds/intro/title]]'] ?? null ) === 'Titel' && isset( $deScan['[[/foo/bar]]'] ) === false
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/contact/uri]]'] ) === false );
check( '...retires a key off the grammar when asked, and leaves a key of the system out of the blacklist', in_array( '/_admin/scan/word/name', $blacklistScan, true ) === true && in_array( '/_nino/locale/fr_CA/name', $blacklistScan, true ) === false );
check( '...and counts what it did', ( $applied['created'] ?? null ) === 1 && ( $applied['ignored'] ?? null ) === 1 && ( $applied['skipped'] ?? null ) === 1 );
foreach( [ '/template/scan-kinds/intro/title' ] as $cleanKey )
	callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => $cleanKey ] );
\Nino\Text::setBlacklisted( $appData, '/_admin/scan/word/name', false );
unlink( $sandbox. '/private/templates/scan-kinds.tpl' );
unset( $appData['/nino/http/routes']['GET://blog/*'] );

// --- the note "also read by" -----------------------------------------------
//
// A word that one template carries and another reads as well is named, with
// the files - once, as a note: nothing is counted, offered or moved. By the
// rule a word several templates read lives in /template/common, which holds
// from the day the key is created and no day after
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/template/page-alpha/intro/title]]'] = 'Alpha';
	$global['[[/template/common/form/submit]]'] = 'Senden';
	return $global;
} );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-alpha.tpl', '<h1>[[/template/page-alpha/intro/title]]</h1><button>[[/template/common/form/submit]]</button>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-beta.tpl', '<h1>[[/template/page-alpha/intro/title]]</h1><button>[[/template/common/form/submit]]</button>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-legal.de_DE.tpl', '<h1>[[/template/page-alpha/intro/title]]</h1>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-gamma.tpl', '<h1>[[/project/company/general/name]]</h1>' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( 'apiScan names a key of one template that another reads as well, with the files that do', $status === 200 && ( $body['alsoUsed'] ?? [] ) === [ [ 'key' => '/template/page-alpha/intro/title', 'files' => [ 'page-beta.tpl', 'page-legal.de_DE.tpl' ] ] ] );
check( '...but not the template\'s own reading, a word of /template/common, or a key of another namespace', array_column( $body['alsoUsed'], 'key' ) === [ '/template/page-alpha/intro/title' ] );
check( '...and it is no row: the note changes no count', $body['missing'] === [] && \Nino\Modules\Text\Keys::missingCount( $appData ) === 0 );
foreach( [ 'page-alpha', 'page-beta', 'page-gamma', 'page-legal.de_DE' ] as $scratchTemplate )
	unlink( $sandbox. '/private/templates/'. $scratchTemplate. '.tpl' );
foreach( [ '/template/page-alpha/intro/title', '/template/common/form/submit' ] as $cleanKey )
	callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => $cleanKey ] );

// --- apiScanApply: the three answers a scanned key can get -----------------
//
// The scan is a list to work through, not an all-or-nothing pass: a row with
// a value becomes a key, a row left empty comes back next time, and a row
// ticked "ignore" is retired for good. All three in one request, so a long
// list can be answered in sittings.

file_put_contents( $sandbox. '/private/templates/scan-apply-fixture.tpl',
	'<h1>[[/template/page-apply/intro/heading]]</h1><p>[[/template/page-apply/intro/subtitle]]</p><small>[[/template/page-apply/intro/legal]]</small>' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [
	[ 'key' => '/template/page-apply/intro/heading', 	'value' => '  Willkommen  ', 	'ignore' => false ],
	[ 'key' => '/template/page-apply/intro/subtitle', 'value' => '', 							'ignore' => false ],
	[ 'key' => '/template/page-apply/intro/legal', 		'value' => '', 							'ignore' => true 	],
	// Real and defined, so not part of this scan at all - the form's rows come
	// from apiScan() and nowhere else, and this endpoint is not a way to
	// blacklist a key the screen never offered
	[ 'key' => '/project/company/general/name', 				'value' => '', 							'ignore' => true 	],
] ] );
check( 'apiScanApply succeeds', $status === 200 );
check( 'a row with a value is counted as created', ( $body['created'] ?? null ) === 1 );
check( 'a row ticked "ignore" is counted as ignored', ( $body['ignored'] ?? null ) === 1 );
check( 'a row left empty is counted as left for later', ( $body['skipped'] ?? null ) === 1 );

$deApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enApply = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the created key holds its starting value in every language', ( $deApply['[[/template/page-apply/intro/heading]]'] ?? null ) === 'Willkommen' && ( $enApply['[[/template/page-apply/intro/heading]]'] ?? null ) === 'Willkommen' );
// A field holding nothing but spaces is the same "not decided yet" an empty
// one is, and a key created from it would look filled in without being it
check( 'the starting value is trimmed rather than stored as typed', str_contains( (string) ( $deApply['[[/template/page-apply/intro/heading]]'] ?? '' ), ' ' ) === false );
check( 'a row left empty writes nothing at all', isset( $deApply['[[/template/page-apply/intro/subtitle]]'] ) === false && isset( $enApply['[[/template/page-apply/intro/subtitle]]'] ) === false );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the retired key is in the blacklist', in_array( '/template/page-apply/intro/legal', $blacklistAfterApply, true ) === true );
check( '...and a key this scan never offered is not, whatever the request said', in_array( '/project/company/general/name', $blacklistAfterApply, true ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
$missingAfterApply = array_column( $body['missing'], 'key' );
check( 'the created key is no longer missing', in_array( '/template/page-apply/intro/heading', $missingAfterApply, true ) === false );
// The whole point of "permanently": before this, an ignored key was simply
// skipped client-side and came straight back on the next scan
check( 'the retired key is no longer asked about', in_array( '/template/page-apply/intro/legal', $missingAfterApply, true ) === false );
check( 'the key left for later comes back', in_array( '/template/page-apply/intro/subtitle', $missingAfterApply, true ) === true );

// Retiring has to be reversible, or "permanently" is a one-way door with the
// filesystem as its only way out. The key has no value anywhere, so
// \Nino\Text::entries() cannot see it - the Text Keys list merges it back in
[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
$listedKeys = array_column( $body['keys'], 'key' );
check( 'a retired key with no value is still listed by the Text Keys tab', in_array( '/template/page-apply/intro/legal', $listedKeys, true ) === true );
check( '...flagged as hidden, so the checkbox that brings it back is ticked', ( array_column( $body['keys'], 'blacklisted', 'key' )['/template/page-apply/intro/legal'] ?? null ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-apply/intro/legal', 'global' => false, 'blacklisted' => false ] );
check( 'un-ticking "hidden" on it is a valid save, not a 404', $status === 200 );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( '...and the scan offers it again afterwards', in_array( '/template/page-apply/intro/legal', array_column( $body['missing'], 'key' ), true ) === true );

// Same key, deleted rather than un-ignored: there is nothing but the
// blacklist line to remove, and removing it is the whole deletion
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [ [ 'key' => '/template/page-apply/intro/legal', 'value' => '', 'ignore' => true ] ] ] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/template/page-apply/intro/legal' ] );
check( 'a retired key can be deleted outright too', $status === 200 );
check( '...which only removes its blacklist line', in_array( '/template/page-apply/intro/legal', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === false );

// Renaming one must move the blacklist line, not write the empty key into
// every locale file - which is what the value-carrying path below it would do
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [ [ 'key' => '/template/page-apply/intro/legal', 'value' => '', 'ignore' => true ] ] ] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/template/page-apply/intro/legal', 'newKey' => '/template/page-apply/intro/imprint' ] );
$blacklistAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'renaming a retired key moves its blacklist line', $status === 200 && in_array( '/template/page-apply/intro/imprint', $blacklistAfterRename, true ) === true && in_array( '/template/page-apply/intro/legal', $blacklistAfterRename, true ) === false );
check( '...without creating the empty key in any locale file', isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/template/page-apply/intro/imprint]]'] ) === false );

// The Dashboard tile counts the same scan (see missingCount()), so a retired
// key has to leave the tile along with the list
\Nino\Text::setBlacklisted( $appData, '/template/page-apply/intro/imprint', false );
$countBeforeRetiring = \Nino\Modules\Text\Keys::missingCount( $appData );
\Nino\Text::setBlacklisted( $appData, '/template/page-apply/intro/legal', true );
check( 'retiring a key takes it out of the Dashboard count too', \Nino\Modules\Text\Keys::missingCount( $appData ) === $countBeforeRetiring - 1 );
\Nino\Text::setBlacklisted( $appData, '/template/page-apply/intro/legal', false );

unlink( $sandbox. '/private/templates/scan-apply-fixture.tpl' );

echo "\n";


echo "Images::apiScan - hardcoded <img> tags in templates/*.tpl, not backed by a slot\n";

// A real, tiny (3x2px) PNG so getimagesize() has something genuine to probe
$probeImg = imagecreatetruecolor( 3, 2 );
ob_start();
imagepng( $probeImg );
\Nino\Filesystem::forceDir( $appData, '/images' );
file_put_contents( \Nino\Filesystem::path( $appData, '/images/probe.png' ), ob_get_clean() );
imagedestroy( $probeImg );

file_put_contents( $sandbox. '/private/templates/scan-fixture-img.tpl', '<img src="[[/nino/public]]/images/probe.png"><img src="[[/nino/public]]/images/probe.png"><img src="https://example.com/x.jpg"><img src="data:image/svg+xml,%3Csvg/%3E">' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );
check( 'exactly one missing image is reported (deduped, external/data-uri skipped)', count( $body['missing'] ) === 1 );
check( 'the reported filename is the local one, relative to /images/', ( $body['missing'][0]['filename'] ?? null ) === 'probe.png' );
check( 'width/height were probed from the real file since the <img> tag had none', ( $body['missing'][0]['width'] ?? null ) === 3 && ( $body['missing'][0]['height'] ?? null ) === 2 );
check( 'a suggested uri was derived from the filename', ( $body['missing'][0]['suggestedUri'] ?? null ) === '/probe' );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/probe', 'label' => 'Probe', 'width' => '3', 'height' => '2' ] );
check( 'the suggested slot can actually be created', $status === 200 );

// Once a slot's filename actually tracks it (the manual step _editor's upload
// flow would do next), the same <img> must disappear from the scan
$appData['/nino/html/images']['/probe']['filename'] = 'probe.png';
[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiScan' );
check( 'once a slot tracks the file, it no longer shows up as missing', count( $body['missing'] ) === 0 );

unlink( $sandbox. '/private/templates/scan-fixture-img.tpl' );

// --- Slots::usage - where a slot is used -------------------------------------
//
// The pages whose templates show a slot, through [template] includes however
// deep, and the templates that name it - or nothing, which is what the Images
// panel warns about: an image uploaded to a slot no page shows never appears

$usageTemplates = [
	'usage-page' 	=> '<section>[image /use/hero alt=""]</section>[template /templates/usage-part][image uri="/use/viauri"]',
	'usage-part' 	=> '<p>[image /use/inner alt="[[/use/alt]]"]</p>[template /templates/usage-deeper]',
	'usage-deeper' => '[image /use/deep]',
	'usage-orphan' => '[image /use/orphan]',
	'usage-loop' 	=> '[image /use/loop][template /templates/usage-loop][template /templates/usage-loop2]',
	'usage-loop2' => '[template /templates/usage-loop]',
	'usage-de_DE' => '[image /use/german]',
	'usage-en_US' => '[image /use/english]',
];
foreach( $usageTemplates as $usageName => $usageSource )
	file_put_contents( $sandbox. '/private/templates/'. $usageName. '.tpl', $usageSource );

$usageRoutesBefore = $appData['/nino/http/routes'] ?? [];
$appData['/nino/http/routes']['GET://usage'] 	= [ 'uri' => '/usage-page', 'body' => '[template /templates/usage-page]' ];
$appData['/nino/http/routes']['GET://looped'] = [ 'uri' => '/looped', 'body' => '[template /templates/usage-loop]' ];
$appData['/nino/http/routes']['GET://lang'] 	= [ 'uri' => '/lang', 'body' => '[template /templates/usage-[[/nino/http/response/locale]]]' ];
$appData['/nino/http/routes']['POST://usage'] = [ 'uri' => '/usage-post', 'body' => '[image /use/post]' ];

$usageText = \Nino\Filesystem::getFileContent( $appData, '/text/'. \Nino\Admin\Admin::sessionLocale( $appData ). '.php', [] );
$usageText['[[/_nino/webpage/usage-page/name]]'] = 'Nutzung';
\Nino\Filesystem::putFileContent( $appData, '/text/'. \Nino\Admin\Admin::sessionLocale( $appData ). '.php', $usageText );

foreach( [ 'hero', 'viauri', 'inner', 'deep', 'orphan', 'loop', 'german', 'english', 'post', 'unused' ] as $usageSlot )
	$appData['/nino/html/images']['/use/'. $usageSlot] = [ 'label' => 'Use '. $usageSlot, 'width' => 10, 'height' => 10, 'filename' => null ];

$usage = \Nino\Modules\Images\Slots::usage( $appData );
$usagePage = [ 'httpUri' => '/usage', 'name' => 'Nutzung' ];
check( 'a slot in the template a route renders: the template, and the page by its name and http uri', ( $usage['/use/hero'] ?? null ) === [ 'templates' => [ 'usage-page' ], 'pages' => [ $usagePage ] ] );
check( '...the [image uri="..."] form is read like the bare one', ( $usage['/use/viauri']['pages'] ?? null ) === [ $usagePage ] );
check( 'a slot reached through a [template] include, and one further down that include\'s own', ( $usage['/use/inner'] ?? null ) === [ 'templates' => [ 'usage-part' ], 'pages' => [ $usagePage ] ]
	&& ( $usage['/use/deep'] ?? null ) === [ 'templates' => [ 'usage-deeper' ], 'pages' => [ $usagePage ] ] );
check( 'a template no route renders is listed with its templates and no pages', ( $usage['/use/orphan'] ?? null ) === [ 'templates' => [ 'usage-orphan' ], 'pages' => [] ] );
check( 'templates that include themselves, directly or through another, are read once and do not run away', ( $usage['/use/loop']['pages'] ?? null ) === [ [ 'httpUri' => '/looped', 'name' => '/looped' ] ] );
check( 'a body that names its template by language is read for every available language, and a page without a name of its own is named by its http uri',
	( $usage['/use/german']['pages'] ?? null ) === [ [ 'httpUri' => '/lang', 'name' => '/lang' ] ] && ( $usage['/use/english']['pages'] ?? null ) === [ [ 'httpUri' => '/lang', 'name' => '/lang' ] ] );
check( 'only what is served counts: a POST route is no page', ( $usage['/use/post'] ?? null ) === null );
check( 'a slot nothing mentions is not in the answer at all', array_key_exists( '/use/unused', $usage ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiList' );
$usageRows = array_column( $body['slots'], null, 'uri' );
check( 'apiList carries usage on every slot: the used one with its page, the unused one empty', ( $usageRows['/use/hero']['usage']['pages'] ?? null ) === [ $usagePage ] && ( $usageRows['/use/unused']['usage'] ?? null ) === [ 'templates' => [], 'pages' => [] ] );

foreach( array_keys( $usageTemplates ) as $usageName )
	unlink( $sandbox. '/private/templates/'. $usageName. '.tpl' );
$appData['/nino/http/routes'] = $usageRoutesBefore;
foreach( [ 'hero', 'viauri', 'inner', 'deep', 'orphan', 'loop', 'german', 'english', 'post', 'unused' ] as $usageSlot )
	unset( $appData['/nino/html/images']['/use/'. $usageSlot] );

echo "\n";


// --- Dashboard::apiSummary - aggregates Text/Images::missingCount() ---------

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'apiSummary succeeds', $status === 200 );
$tile = static function( array $body, string $panel ): ?string {
	foreach( $body['tiles'] ?? [] as $entry )
		if( $entry['panel'] === $panel )
			return (string) $entry['value'];
	return null;
};
check( 'the Text Keys tile reports 0 missing - both scan fixtures above were already cleaned up', $tile( $body, 'keys' ) === '0' );
check( 'the Image Slots tile reports 0 missing - the scan fixture above was already cleaned up', $tile( $body, 'slots' ) === '0' );
check( 'the structure panels report their counts as tiles too', $tile( $body, 'types' ) !== null && $tile( $body, 'routes' ) !== null && $tile( $body, 'users' ) !== null );

// A fresh, self-contained fixture (one undefined key, one untracked image) to
// prove the two counts actually reflect Text/Images::_scanMissing(), not just
// always 0 - a new filename, since probe.png is already tracked by the slot
// created in the Images::apiScan section above
file_put_contents( $sandbox. '/private/templates/dashboard-fixture.tpl', '<p>[[/dashboard-scan-test/heading]]</p><img src="[[/nino/public]]/images/probe2.png">' );

[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'the Text Keys tile picks up the fresh undefined key', $tile( $body, 'keys' ) === '1' );
check( 'the Image Slots tile picks up the fresh untracked image (a new filename, not tracked by any slot)', $tile( $body, 'slots' ) === '1' );

unlink( $sandbox. '/private/templates/dashboard-fixture.tpl' );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'apiSummary requires an authed dev session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Notices: what needs attention, above the tiles. Mail that failed is shown to
// every account that opens the dashboard - the record holds no address - and
// the texts still to translate to the one that may open the Text panel
$noticesOf = static function( array $body, string $text ): array {
	return array_values( array_filter( $body['notices'] ?? [], static fn( array $notice ): bool => $notice['text'] === $text ) );
};
@unlink( \Nino\Filesystem::path( $appData, '/data/mail-status.php' ) );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'notices is an array, and carries no mail notice while nothing failed', is_array( $body['notices'] ?? null ) === true && $noticesOf( $body, '/_admin/dashboard/notice/mail' ) === [] );

\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = false; } );
$minuteBefore = date( 'Y-m-d H:i' );
\Nino\Mail::send( $appData, 'owner@example.com', 'a', 'b', '' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$mailNotice = $noticesOf( $body, '/_admin/dashboard/notice/mail' );
check( 'a failed mail raises one notice: since when, and how many calls failed - no link', count( $mailNotice ) === 1
	&& in_array( $mailNotice[0]['values'], [ [ $minuteBefore, '1' ], [ date( 'Y-m-d H:i' ), '1' ] ], true ) && $mailNotice[0]['link'] === '' );
check( '...which holds no address', str_contains( json_encode( $body['notices'] ), 'example.com' ) === false );

\Nino\Auth::insertUser( $appData, 'nopanels@example.com', 'correct horse battery staple', [] );
\Nino\Auth::loginUser( $appData, 'nopanels@example.com', 'correct horse battery staple' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'an account with no panel permission sees the mail notice as well', $status === 200 && count( $noticesOf( $body, '/_admin/dashboard/notice/mail' ) ) === 1 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
\Nino\Mail::send( $appData, 'owner@example.com', 'a', 'b', '' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'the next send that delivered everything takes the notice away', $noticesOf( $body, '/_admin/dashboard/notice/mail' ) === [] );
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );

// A text the native language has and English does not
$english = static fn( array $body ): array => array_values( array_filter( $noticesOf( $body, '/_admin/dashboard/notice/untranslated' ), static fn( array $notice ): bool => $notice['values'][1] === 'en_US' ) );
$untranslatedBefore = $english( callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' )[1] );
$countBefore = (int) ( \Nino\Modules\Text\Admin::untranslatedCounts( $appData )['en_US'] ?? 0 );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => $texts + [ '[[/notice-test/gap]]' => 'Eine Lücke' ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$untranslated = $english( $body );
check( 'an account that may open the Text panel is told, per language, how many texts it still lacks, with a link to the panel', count( $untranslated ) === 1
	&& $untranslated[0]['values'] === [ (string) ( $countBefore + 1 ), 'en_US' ] && $untranslated[0]['link'] === '#text' );

\Nino\Auth::loginUser( $appData, 'nopanels@example.com', 'correct horse battery staple' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( '...and an account that may not is told nothing of it', $noticesOf( $body, '/_admin/dashboard/notice/untranslated' ) === [] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

callDev( $appData, \Nino\Modules\Text\Admin::class, 'apiSaveBatch', [ 'items' => [ [ 'key' => '/notice-test/gap', 'locale' => 'en_US', 'value' => 'A gap' ] ] ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$after = $english( $body );
check( '...and the notice follows the text being saved in that language', ( $after[0]['values'][0] ?? '0' ) === (string) $countBefore );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => array_diff_key( $texts, [ '[[/notice-test/gap]]' => 1 ] ) );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $texts ): array => array_diff_key( $texts, [ '[[/notice-test/gap]]' => 1 ] ) );

// What is wrong with the legal texts: told to an account that may edit elements,
// while the module is active - and to nobody else
$legalModulesBefore = $appData['/nino/modules'] ?? [];
$appData['/nino/modules'] = array_merge( $legalModulesBefore, [ '\\Nino\\Modules\\Legal' ] );
$legalRoutes = $appData['/nino/http/routes'] ?? [];
$legalNotices = static fn( array $body ): array => array_values( array_filter( $body['notices'] ?? [], static fn( array $notice ): bool => str_starts_with( $notice['text'], '/_admin/dashboard/notice/legal-' ) ) );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$missingTypes = $legalNotices( $body );
check( 'the module is active and its types are not there: the dashboard says so, once per type, with the file of the unit to copy back and a link to the type', count( array_filter( $missingTypes, static fn( array $notice ): bool => $notice['text'] === '/_admin/dashboard/notice/legal-nothing' ) ) === 2
	&& $missingTypes[0]['values'][0] === 'legal' && str_ends_with( $missingTypes[0]['values'][1], 'install/elements/legal.php' ) && $missingTypes[0]['link'] === '#elements/legal' );

$legalRoutesOut = []; $legalBlacklistOut = []; $legalConfigOut = [];
\Nino\Features::applyUnit( $appData, __DIR__. '/../_nino/Nino/Modules/Legal/install', [ 'de_DE' ], $legalRoutesOut, $legalBlacklistOut, $legalConfigOut, true );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$legalNav = array_values( array_filter( $legalNotices( $body ), static fn( array $notice ): bool => $notice['text'] === '/_admin/dashboard/notice/legal-nav' ) );
$legalAll = $legalNotices( $body );
check( 'with the types there, what is wrong with the texts is told: the placeholder with no value, in the section it stands in, with a link to that section', ( array_values( array_filter( $legalAll, static fn( array $notice ): bool => $notice['text'] === '/_admin/dashboard/notice/legal-unknown' ) )[0]['link'] ?? null ) === '#elements/legal/provider'
	&& in_array( [ '#/project/company/contact/email#', 'Anbieter dieser Website', 'de_DE' ], array_column( $legalAll, 'values' ), true ) === true );
check( '...a language that has no version of a section is told with the section named in the native language', in_array( [ 'Anbieter dieser Website', 'en_US' ], array_column( array_filter( $legalAll, static fn( array $notice ): bool => $notice['text'] === '/_admin/dashboard/notice/legal-translation' ), 'values' ), true ) === true );
check( '...at most eight lines, and one more that counts the rest', count( $legalAll ) === 9 && end( $legalAll )['text'] === '/_admin/dashboard/notice/legal-more' && (int) end( $legalAll )['values'][0] > 1 );

\Nino\Auth::loginUser( $appData, 'nopanels@example.com', 'correct horse battery staple' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( '...and an account that may not edit elements is told nothing of it', $legalNotices( $body ) === [] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$appData['/nino/modules'] = $legalModulesBefore;
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( '...nor is anybody, once the module is off', $legalNotices( $body ) === [] );

// A language added later gets what the module brings for it - once the module is active
$appData['/nino/modules'] = array_merge( $legalModulesBefore, [ '\\Nino\\Modules\\Legal' ] );
$englishFile = \Nino\Filesystem::path( $appData, '/text/en_US.php' );
$englishBefore = is_file( $englishFile ) === true ? (string) file_get_contents( $englishFile ) : null;
@unlink( $englishFile );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'en_US' ] );
$englishTexts = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a language the Legal unit has texts for gets its page details and its versions of the sections with the new file', $status === 200 && ( $body['created'] ?? null ) === true
	&& ( $englishTexts['[[/_nino/webpage/legal/privacy/name]]'] ?? null ) === 'Privacy' && ( $englishTexts['[[/_nino/webpage/legal/privacy/title]]'] ?? null ) === 'Privacy policy'
	&& ( \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] )['en_US']['hosting']['title'] ?? null ) === 'Hosting' );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'sv_SE' ] );
$swedishTexts = \Nino\Filesystem::getFileContent( $appData, '/text/sv_SE.php', [] );
check( 'any other language gets the names of the two pages in the native language, and no sections to translate', $status === 200 && ( $swedishTexts['[[/_nino/webpage/legal/imprint/name]]'] ?? null ) === 'Impressum'
	&& ( $swedishTexts['[[/_nino/webpage/legal/imprint/title]]'] ?? '' ) === '' && isset( \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] )['sv_SE'] ) === false );
unlink( \Nino\Filesystem::path( $appData, '/text/sv_SE.php' ) );
$appData['/nino/modules'] = $legalModulesBefore;
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'sv_SE' ] );
check( 'with the module off the new language gets nothing of it', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/sv_SE.php', [] )['[[/_nino/webpage/legal/imprint/name]]'] ?? '' ) === '' );
unlink( \Nino\Filesystem::path( $appData, '/text/sv_SE.php' ) );
\Nino\Filesystem::mutate( $appData, '/text/global.php', fn( array $texts ): array => array_diff_key( $texts, [ '[[/_nino/locale/sv_SE/name]]' => 1 ] ) );
if( $englishBefore !== null )
	file_put_contents( $englishFile, $englishBefore );
else
	@unlink( $englishFile );
@unlink( \Nino\Filesystem::path( $appData, '/elements/legal.php' ) );
@unlink( \Nino\Filesystem::path( $appData, '/elements/privacy.php' ) );
@unlink( \Nino\Filesystem::path( $appData, '/templates/page-legal-imprint.tpl' ) );
@unlink( \Nino\Filesystem::path( $appData, '/templates/page-legal-privacy.tpl' ) );
unset( $appData['./nino/elements/cache'] );
$appData['/nino/http/routes'] = $legalRoutes;

echo "\n";


// --- Dev\PageEditor - create/edit/delete page routes -------------------------

echo "PageEditor - create/edit/delete page routes\n";

file_put_contents( $sandbox. '/private/templates/page-about.tpl', '<h1>About</h1>' );
file_put_contents( $sandbox. '/private/templates/page-contact.tpl', '<h1>Contact</h1>' );
file_put_contents( $sandbox. '/private/templates/not-a-page.tpl', '<p>ignored - not a page-*.tpl file</p>' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'starts with an empty page list', $body['pages'] === [] );
check( 'lists only templates/page-*.tpl files, sorted, extension stripped', $body['templates'] === [ 'page-about', 'page-contact' ] );
check( 'no navigations are offered - Navigation was never picked', $body['navs'] === [] );
check( 'a project with only finished pages is proposed no template - the form asks for a choice', $body['defaultTemplate'] === '' );
check( 'the list names the locale the workbench is on', $body['selectedLocale'] === \Nino\Admin\Admin::sessionLocale( $appData ) && in_array( $body['selectedLocale'], $body['locales'], true ) );

// The proposal is the blank page and nothing else - not the template that sorts
// first, which is the 404 here, and not any other finished page
file_put_contents( $sandbox. '/private/templates/page-404.tpl', '<h1>Not found</h1>' );
file_put_contents( $sandbox. '/private/templates/page-blank.tpl', '' );
[ , $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( 'page-blank is the proposed template when the project has it', $body['templates'][0] === 'page-404' && $body['defaultTemplate'] === 'page-blank' );
unlink( $sandbox. '/private/templates/page-blank.tpl' );
[ , $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( '...and never page-404, even when that one sorts first and is all there is besides', $body['templates'][0] === 'page-404' && $body['defaultTemplate'] === '' );
unlink( $sandbox. '/private/templates/page-404.tpl' );

// What the save checks of a new route are written against: a name and a title
// in every active language
$pageText = static function( string $name ) use ( &$appData ): array {
	$text = [];
	foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
		$text[$locale] = [ 'name' => $name, 'title' => $name. ' - '. $locale ];
	return $text;
};
check( 'the fixture project has more than two active languages, so "every language" is more than a pair', count( $pageText('x') ) >= 3 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '../etc/passwd', 'httpUri' => '/about', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an unsafe Element-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/about', 'httpUri' => '../etc/passwd', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an unsafe Http-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/admin-shadow', 'httpUri' => '/_admin', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects a page mounted on a runtime-owned tool uri', $status === 409 );

\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://owned'] = [ 'uri' => '/owned', 'body' => 'hand-written route' ];
	return $config;
} );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/owned-page', 'httpUri' => '/owned', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an Http-URI already owned by a non-page route', $status === 409 );
check( 'keeps the colliding hand-written route unchanged', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://owned']['body'] ?? null ) === 'hand-written route' );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/about', 'httpUri' => '/about', 'template' => 'not-a-page', 'text' => [],
] );
check( 'rejects a template outside the page-*.tpl whitelist with 400', $status === 400 );

// The actual, first real save: Element-URI deliberately differs from
// Http-URI, to prove the class uses the entry's own uri, not the request
// path, for the route's own data field/meta namespace
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'statusCode' => 200, 'text' => [
		'de_DE' => [ 'name' => 'Über uns', 'title' => 'Über uns', 'description' => 'Über unser Unternehmen.' ],
		'en_US' => [ 'name' => 'About us', 'title' => 'About us', 'description' => '' ],
		'fr_FR' => [ 'name' => 'A propos', 'title' => 'A propos' ],
	],
] );
check( 'apiSave succeeds', $status === 200 );
check( 'response echoes the persisted list', count( $body['pages'] ) === 1 );

$configAfterSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'registers a route at "/about" (its Http-URI)', isset( $configAfterSave['/nino/http/routes']['GET://about'] ) === true );
check( 'the route\'s own "uri" data field is the Element-URI, not the Http-URI', $configAfterSave['/nino/http/routes']['GET://about']['uri'] === '/site-about' );
check( 'the route body references the picked template, extension stripped', $configAfterSave['/nino/http/routes']['GET://about']['body'] === '[template /templates/page-about]' );
check( 'a 200 status code is not written out (the implicit default)', isset( $configAfterSave['/nino/http/routes']['GET://about']['statusCode'] ) === false );
check( 'the route is the whole persistence - no second list is written alongside it', isset( $configAfterSave['/nino/install/webpages'] ) === false && count( array_filter( $configAfterSave['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Modules\Routes\Admin::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === 1 );

$deAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'writes the page\'s own de_DE meta, keyed by its Element-URI', $deAfterSave['[[/_nino/webpage/site-about/name]]'] === 'Über uns' && $deAfterSave['[[/_nino/webpage/site-about/title]]'] === 'Über uns' );

$enAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a blank description is stored as it is, empty - there is no filler text any more', $enAfterSave['[[/_nino/webpage/site-about/description]]'] === '' && $enAfterSave['[[/_nino/webpage/site-about/name]]'] === 'About us' );
check( '...and no "Page" or "Page Title" placeholder is written anywhere', in_array( 'Page', $enAfterSave, true ) === false && in_array( 'Page Title', $deAfterSave + $enAfterSave, true ) === false );

$globalAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'writes the page\'s reachable Http-URI as one global fill a template can link to by name', ( $globalAfterSave['[[/_nino/webpage/site-about/uri]]'] ?? null ) === '/about'
	&& isset( $deAfterSave['[[/_nino/webpage/site-about/uri]]'], $enAfterSave['[[/_nino/webpage/site-about/uri]]'] ) === false );
check( 'that uri is blacklisted as a technical value, like every other route key', in_array( '/_nino/webpage/site-about/uri', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) );

// A name and a title are required in every active language, and a refusal
// writes nothing: not the route, not one of the texts
$configBeforeRefusal = file_get_contents( $sandbox. '/private/config.php' );
$textBeforeRefusal 	= [ file_get_contents( $sandbox. '/private/text/de_DE.php' ), file_get_contents( $sandbox. '/private/text/en_US.php' ), file_get_contents( $sandbox. '/private/text/global.php' ) ];
$complete = $pageText('Contact');
$without 	= static fn( string $locale, string $field ): array => array_replace( $complete, [ $locale => array_diff_key( $complete[$locale], [ $field => 1 ] ) ] );
$refusals = [
	'a name missing in one language' 	=> [ array_diff_key( $complete, [ 'fr_FR' => 1 ] ), 'routes_missing_name', 'fr_FR' ],
	'a blank name' 										=> [ array_replace( $complete, [ 'de_DE' => [ 'name' => '   ', 'title' => 'T' ] ] ), 'routes_missing_name', 'de_DE' ],
	'a name that is not text' 				=> [ array_replace( $complete, [ 'en_US' => [ 'name' => [ 'x' ], 'title' => 'T' ] ] ), 'routes_missing_name', 'en_US' ],
	'a title missing in one language' => [ $without( 'en_US', 'title' ), 'routes_missing_title', 'en_US' ],
	'a blank title' 									=> [ array_replace( $complete, [ 'de_DE' => [ 'name' => 'Kontakt', 'title' => "\t" ] ] ), 'routes_missing_title', 'de_DE' ],
	'no text at all' 									=> [ [], 'routes_missing_name', 'de_DE' ],
];
foreach( $refusals as $label => [ $refusedText, $refusedCode, $refusedLocale ] ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
		'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => $refusedText,
	] );
	check( $label. ' is refused with 400, a code and the language', $status === 400 && ( $body['code'] ?? '' ) === $refusedCode && ( $body['params'] ?? [] ) === [ $refusedLocale ] );
}
check( '...and every one of them left config.php and the text files exactly as they were', file_get_contents( $sandbox. '/private/config.php' ) === $configBeforeRefusal
	&& $textBeforeRefusal === [ file_get_contents( $sandbox. '/private/text/de_DE.php' ), file_get_contents( $sandbox. '/private/text/en_US.php' ), file_get_contents( $sandbox. '/private/text/global.php' ) ] );

// Duplicate checks: a second entry may not reuse either uri
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-about', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => [],
] );
check( 'rejects a duplicate Element-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/about', 'template' => 'page-contact', 'text' => [],
] );
check( 'rejects a duplicate Http-URI with 400', $status === 400 );

// A real second entry, with a non-200 status code and explicit menu membership
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'statusCode' => 404, 'navs' => [ 'main' ], 'text' => $pageText('Kontakt'),
] );
check( 'apiSave succeeds for the second entry too', $status === 200 );

$configAfterSecond = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'a non-200, in-range status code is written out as posted', $configAfterSecond['/nino/http/routes']['GET://contact']['statusCode'] === 404 );
check( 'both page routes are now persisted, in order', array_keys( array_filter( $configAfterSecond['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Modules\Routes\Admin::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === [ 'GET://about', 'GET://contact' ] );

// Navigation: only regenerated while the module is active
$appData['/nino/modules'][] = '\\Nino\\Modules\\Navigation';
// The registry is what a menu name is checked against, and it is explicit -
// there is no implied default menu to fall back on
$appData['/nino/html/navs'] = [ 'main' ];

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/contact', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'navs' => [ 'main' ], 'text' => $pageText('Kontakt'),
] );
check( 'resaving an existing entry unchanged (identified by originalHttpUri) succeeds', $status === 200 );

// Menu membership lives on the route this entry owns, not in a generated
// textfill - see \Nino\Modules\Navigation::routeLines()
$routesAfterNav = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a page explicitly assigned to main joins that menu on its own route', ( $routesAfterNav['GET://contact']['navs'] ?? null ) === [ 'main' => 1 ] );
check( 'a page in no menu carries no membership at all', isset( $routesAfterNav['GET://about']['navs'] ) === false );
check( 'nothing is generated into the text files anymore', isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/website/navigation/main]]'] ) === false );

// Reordering: assign both entries to main so the generated menu actually
// shows a reorder, not just the list itself
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'navs' => [ 'main' ], 'text' => $pageText('About'),
] );
check( 'assigning the first entry to main too succeeds', $status === 200 );

// A membership a save newly adds goes behind everything already in that menu
check( 'the second page to join the menu lands behind the first, not on top of it', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://about']['navs'] ?? null ) === [ 'main' => 2 ] );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'moving the first entry up (already at the top) 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/contact', 'direction' => 'down' ] );
check( 'moving the last entry down (already at the bottom) 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'sideways' ] );
check( 'rejects an invalid direction with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/does-not-exist', 'direction' => 'up' ] );
check( 'apiMove 404s for an unknown httpUri', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'down' ] );
check( 'moving the first entry down succeeds', $status === 200 );
check( 'the two entries actually swapped places', array_column( $body['pages'], 'httpUri' ) === [ '/contact', '/about' ] );

$configAfterMove = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the swapped order is persisted too', array_slice( array_keys( $configAfterMove['/nino/http/routes'] ), -2 ) === [ 'GET://contact', 'GET://about' ] );
check( 'the hand-written route the swap stepped over kept its own slot', array_keys( $configAfterMove['/nino/http/routes'] )[0] === 'GET://owned' );

// Move it back for the rename/delete checks below, which assume the
// original order ("about" first)
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'moving it back up succeeds', $status === 200 );

// Renaming an entry's Http-URI: the old route key must disappear, the new
// one appear, and the list must stay at 2 entries (replaced in place)
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/ueber-uns', 'template' => 'page-about', 'text' => $pageText('Über uns'),
] );
check( 'renaming an entry\'s Http-URI succeeds', $status === 200 );

$configAfterRename = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the old route key is gone after a rename', isset( $configAfterRename['/nino/http/routes']['GET://about'] ) === false );
check( 'the new route key exists', isset( $configAfterRename['/nino/http/routes']['GET://ueber-uns'] ) === true );
check( 'still exactly two page routes - rename replaced in place, did not append a third', count( array_filter( $configAfterRename['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Modules\Routes\Admin::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === 2 );
check( 'the renamed route keeps the slot the old key stood in, rather than dropping to the bottom', array_slice( array_keys( $configAfterRename['/nino/http/routes'] ), -2 ) === [ 'GET://ueber-uns', 'GET://contact' ] );

// Delete
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/ueber-uns' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'exactly one page remains', count( $body['pages'] ) === 1 );

$configAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the deleted entry\'s route is gone', isset( $configAfterDelete['/nino/http/routes']['GET://ueber-uns'] ) === false );
check( 'the surviving entry\'s route is untouched', isset( $configAfterDelete['/nino/http/routes']['GET://contact'] ) === true );

$deAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the deleted entry\'s own text meta is left in place - additive-only, never auto-deleted', isset( $deAfterDelete['[[/_nino/webpage/site-about/name]]'] ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/does-not-exist' ] );
check( 'apiDelete 404s for an unknown httpUri', $status === 404 );

// What a route carries that the form does not edit stays. Somebody put a
// 'maintenance' => false, a 'locale' and a 'header' on it in config.php - the
// first keeps a page up while the site is down - and a save used to build the
// route anew from the form's four fields and drop the rest
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-kept', 'httpUri' => '/kept', 'template' => 'page-about', 'navs' => [ 'main' ], 'text' => $pageText('Kept'),
] );
check( 'a page for the fields-kept check is created', $status === 200 );
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://kept'] = array_merge( $config['/nino/http/routes']['GET://kept'], [ 'maintenance' => false, 'locale' => 'de_DE', 'header' => [ 'X-Kept' => '1' ], 'statusCode' => 201 ] );
	return $config;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/kept', 'uri' => '/site-kept', 'httpUri' => '/kept-on', 'template' => 'page-contact', 'statusCode' => 200, 'navs' => [], 'text' => $pageText('Kept'),
] );
$keptRoutes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a save keeps the fields of the route it does not edit - it moved to its new path with them', $status === 200 && isset( $keptRoutes['GET://kept'] ) === false
	&& ( $keptRoutes['GET://kept-on']['maintenance'] ?? null ) === false && ( $keptRoutes['GET://kept-on']['locale'] ?? null ) === 'de_DE' && ( $keptRoutes['GET://kept-on']['header'] ?? null ) === [ 'X-Kept' => '1' ] );
check( '...and sets the four it does edit: the body, the status code - 200 is no field -, the menus', $keptRoutes['GET://kept-on']['body'] === '[template /templates/page-contact]'
	&& isset( $keptRoutes['GET://kept-on']['statusCode'] ) === false && isset( $keptRoutes['GET://kept-on']['navs'] ) === false && $keptRoutes['GET://kept-on']['uri'] === '/site-kept' );
callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/kept-on' ] );

// The imprint and the privacy policy are the Legal module's
$configBeforeReserved = file_get_contents( $sandbox. '/private/config.php' );
foreach( [ '/legal/imprint', '/legal/privacy' ] as $reservedUri ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
		'originalHttpUri' => '', 'uri' => $reservedUri, 'httpUri' => '/mine', 'template' => 'page-about', 'text' => $pageText('Mine'),
	] );
	check( 'a page with the Element-URI '. $reservedUri. ' is refused with 409, a code and the uri', $status === 409 && ( $body['code'] ?? '' ) === 'routes_reserved_uri' && ( $body['params'] ?? [] ) === [ $reservedUri ] );
}
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/contact', 'uri' => '/legal/imprint', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => $pageText('Kontakt'),
] );
check( '...and so is an existing page that is moved onto one, and nothing was written', $status === 409 && file_get_contents( $sandbox. '/private/config.php' ) === $configBeforeReserved );

// --- the pages a feature routes at runtime ---------------------------------
//
// Posts' /blog, the Newsletter's /.newsletter, Hello's /hello are in no
// config.php. Their name, title and description are keys of the system the
// Text Keys tab will not create, so this panel lists them apart and writes
// those three keys - and nothing else
$configBeforeRuntime = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
// The text of a post for every language the sandbox has: German and English as given, any other as the English
$runtimeLocales = \Nino\Locales::getAvailableLocales( $appData );
$runtimeText = static fn( array $de, array $en ): array => array_combine( $runtimeLocales, array_map( static fn( string $locale ): array => $locale === 'de_DE' ? $de : $en, $runtimeLocales ) );
$appData['/nino/http/routes']['GET://blog/*'] = [ 'uri' => '/blog/post', 'body' => '[template /templates/page-post]' ];
$appData['/nino/http/routes']['GET://.newsletter'] = [ 'uri' => '/.newsletter', 'body' => '[template /templates/page-newsletter]' ];
$appData['/nino/http/routes']['GET://.search'] = [ 'uri' => '/.search' ];
$appData['/nino/http/routes']['GET://hello'] = [ 'uri' => '/hello', 'body' => '[template /templates/page-hello]' ];
$appData['/nino/http/routes']['POST://.newsletter'] = [ 'uri' => '/.newsletter' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
$runtimeByUri = array_column( $body['runtime'] ?? [], null, 'uri' );
check( 'routes/list lists the pages features route under "runtime"', $status === 200 && array_keys( $runtimeByUri ) === [ '/blog/post', '/.newsletter', '/hello' ] );
check( '...with the path as the feature routes it, placeholder and all', array_column( $body['runtime'], 'httpUri', 'uri' ) === [ '/blog/post' => '/blog/*', '/.newsletter' => '/.newsletter', '/hello' => '/hello' ] );
check( '...a route with no page template (/.search) and a persisted one are not among them', isset( $runtimeByUri['/.search'] ) === false && in_array( '/contact', array_column( $body['runtime'], 'httpUri' ), true ) === false );
check( '...and the persisted pages still come as before, without the runtime ones', array_column( $body['pages'], 'httpUri' ) === [ '/contact' ] );
check( '...each with its name, title and description per language, empty while nobody wrote them', array_keys( $runtimeByUri['/hello']['text'] ) === $runtimeLocales && $runtimeByUri['/hello']['text']['de_DE'] === [ 'name' => '', 'title' => '', 'description' => '' ] );
check( '...every page with its paths: one for a page of its own', $runtimeByUri['/hello']['httpUris'] === [ '/hello' ] );

// A page with a route per language is one page: the imprint of Modules\Legal
$appData['/nino/http/routes']['GET://impressum'] = [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ];
$appData['/nino/http/routes']['GET://imprint'] = [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ];
[ , $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
$languageByUri = array_column( $body['runtime'], null, 'uri' );
check( 'the routes of one Element-URI are one page, with all their paths', array_keys( $languageByUri ) === [ '/blog/post', '/.newsletter', '/hello', '/legal/imprint' ]
	&& $languageByUri['/legal/imprint']['httpUri'] === '/impressum' && $languageByUri['/legal/imprint']['httpUris'] === [ '/impressum', '/imprint' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/legal/imprint', 'text' => $runtimeText( [ 'name' => 'Impressum', 'title' => 'Impressum' ], [ 'name' => 'Imprint', 'title' => 'Imprint' ] ) ] );
check( '...whose details are written once, under the Element-URI they share', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/legal/imprint/name]]'] ?? null ) === 'Impressum' );
unset( $appData['/nino/http/routes']['GET://impressum'], $appData['/nino/http/routes']['GET://imprint'] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => ' Newsletter ', 'title' => 'Newsletter abonnieren', 'description' => 'Alle drei Monate.' ],
	[ 'name' => 'Newsletter', 'title' => 'Subscribe', 'description' => '' ]
) ] );
$deRuntime = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enRuntime = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'routes/savetexts writes name, title and description per language, trimmed', $status === 200 && ( $deRuntime['[[/_nino/webpage/.newsletter/name]]'] ?? null ) === 'Newsletter' && ( $deRuntime['[[/_nino/webpage/.newsletter/title]]'] ?? null ) === 'Newsletter abonnieren'
	&& ( $deRuntime['[[/_nino/webpage/.newsletter/description]]'] ?? null ) === 'Alle drei Monate.' && ( $enRuntime['[[/_nino/webpage/.newsletter/title]]'] ?? null ) === 'Subscribe' && ( $enRuntime['[[/_nino/webpage/.newsletter/description]]'] ?? null ) === '' );
check( '...writes no uri key, so the feature\'s path is not copied and cannot go stale, and nothing goes on the blacklist', isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/.newsletter/uri]]'] ) === false
	&& array_filter( \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), static fn( string $key ): bool => str_starts_with( $key, '/_nino/webpage/.newsletter' ) ) === [] );
check( '...and no route into config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] === $configBeforeRuntime['/nino/http/routes'] );
check( '...and answers with the list as it stands now', ( array_column( $body['runtime'], null, 'uri' )['/.newsletter']['text']['de_DE']['name'] ?? null ) === 'Newsletter' );
$appData['/nino/locales/textfiles'] ??= '/text';
check( 'the key is the one the menu and the page header read, so it renders', \Nino\Html::renderTextfill( $appData, '/_nino/webpage/.newsletter/name' ) === 'Newsletter' );

// A directory where the language file's sidecar lock goes: lockFile() cannot open it, so the
// write is refused - what a read-only or full disk gives, without needing either
$runtimeLock = $sandbox. '/private/data/.locks/'. sha1( '/text/de_DE.php' ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $runtimeLock );
@mkdir( $runtimeLock );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => 'Neu', 'title' => 'Neu', 'description' => '' ],
	[ 'name' => 'New', 'title' => 'New', 'description' => '' ]
) ] );
check( 'routes/savetexts that could not write the language file says so rather than reporting success', $status === 500 && str_contains( (string) ( $body['error'] ?? '' ), '/text/de_DE.php' ) === true && isset( $body['runtime'] ) === false );
@rmdir( $runtimeLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( '...and the text it had is still there', ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/.newsletter/name]]'] ?? null ) === 'Newsletter' );

// The title and the description land in an attribute of the page head, through a blind str_replace
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => '<b>Newsletter</b>', 'title' => 'x" onmouseover="y', 'description' => '<script>z</script>Alle drei Monate.' ],
	[ 'name' => 'Newsletter', 'title' => 'Subscribe', 'description' => '' ]
) ] );
$deRuntime = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'routes/savetexts stores the words as plain text, as the Text panel does: no markup, no quote that closes an attribute', $status === 200 && $deRuntime['[[/_nino/webpage/.newsletter/name]]'] === 'Newsletter'
	&& $deRuntime['[[/_nino/webpage/.newsletter/title]]'] === 'x&quot; onmouseover=&quot;y' && str_contains( $deRuntime['[[/_nino/webpage/.newsletter/description]]'], '<' ) === false );
callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => 'Newsletter', 'title' => 'Newsletter abonnieren', 'description' => 'Alle drei Monate.' ],
	[ 'name' => 'Newsletter', 'title' => 'Subscribe', 'description' => '' ]
) ] );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/nowhere', 'text' => $runtimeText( [ 'name' => 'x', 'title' => 'y' ], [ 'name' => 'x', 'title' => 'y' ] ) ] );
check( 'routes/savetexts is a 404 for an Element-URI no feature route carries', $status === 404 && isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/nowhere/name]]'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/contact', 'text' => $runtimeText( [ 'name' => 'x', 'title' => 'y' ], [ 'name' => 'x', 'title' => 'y' ] ) ] );
check( '...and for the Element-URI of a persisted page, which is saved the way it always was', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.search', 'text' => $runtimeText( [ 'name' => 'x', 'title' => 'y' ], [ 'name' => 'x', 'title' => 'y' ] ) ] );
check( '...and for a route that is no page', $status === 404 );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/hello', 'text' => $runtimeText( [ 'name' => 'Hallo', 'title' => 'Hallo Welt' ], [ 'name' => 'Hello', 'title' => '' ] ) ] );
check( 'a title missing in one language is refused with the code and the field, and nothing is written', $status === 400 && ( $body['code'] ?? '' ) === 'routes_missing_title' && ( $body['params'] ?? [] ) === [ 'en_US' ] && ( $body['field'] ?? '' ) === 'title'
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/hello/name]]'] ) === false );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/hello', 'text' => $runtimeText( [ 'name' => '', 'title' => 'y' ], [ 'name' => 'Hello', 'title' => 'x' ] ) ] );
check( '...and so is a missing name', $status === 400 && ( $body['code'] ?? '' ) === 'routes_missing_name' );
check( 'the log has a line for it, with the Element-URI', \Nino\Modules\Routes\Admin::log( 'routes/savetexts', [ 'uri' => '/.newsletter' ] ) === 'Edit Feature Route /.newsletter' );
\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/hello', 'text' => [] ] );
check( '...and routes/savetexts needs the panel\'s session and permission like every action of it', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
foreach( [ 'name', 'title', 'description' ] as $runtimeField )
	foreach( [ '/text/de_DE.php', '/text/en_US.php' ] as $runtimeFile )
		\Nino\Filesystem::mutate( $appData, $runtimeFile, function( array $content ) use ( $runtimeField ): array {
			unset( $content['[[/_nino/webpage/.newsletter/'. $runtimeField. ']]'] );
			return $content;
		} );
unset( $appData['/nino/http/routes']['GET://blog/*'], $appData['/nino/http/routes']['GET://.newsletter'], $appData['/nino/http/routes']['GET://.search'], $appData['/nino/http/routes']['GET://hello'], $appData['/nino/http/routes']['POST://.newsletter'] );

array_pop( $appData['/nino/modules'] ); // drop the simulated Navigation module again

unlink( $sandbox. '/private/templates/page-about.tpl' );
unlink( $sandbox. '/private/templates/page-contact.tpl' );
unlink( $sandbox. '/private/templates/not-a-page.tpl' );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( 'PageEditor actions require an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Modules\Navigation\Admin - which menus exist, and what stands in them -------

echo "Modules\\Navigation\\Admin - menus and their running order\n";

// Same two config keys the Routes module writes, from the other side: this
// one opens one menu and sets its whole running order. Route bodies are
// deliberately mixed - a page route, and one no page editor manages
// (robots.txt) - to prove a menu is only ever "a path with a name"
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/html/navs'] = [ 'main', 'footer' ];
	$config['/nino/http/routes'] = [
		'GET://' 						=> [ 'uri' => '/home', 	'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 1 ] ],
		'GET://robots.txt' 	=> [ 'uri' => '/robots.txt', 'body' => '[template /templates/robots]' ],
		'GET://contact' 		=> [ 'uri' => '/contact', 'body' => '[template /templates/page-contact]', 'navs' => [ 'main' => 2 ] ],
		'GET://legal' 			=> [ 'uri' => '/legal', 'body' => '[template /templates/page-legal]' ],
	];
	return $config;
} );
$appData['/nino/html/navs'] = [ 'main', 'footer' ];
// The live routes are the persisted ones here: nothing registers a route of its own yet
$appData['/nino/http/routes'] = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ),
	[ '[[/_nino/webpage/home/name]]' => 'Start', '[[/_nino/webpage/contact/name]]' => 'Kontakt', '[[/_nino/webpage/legal/name]]' => 'Impressum' ]
) );

$navKeys = fn( array $body ): array => array_column( $body['navs'], 'key' );
$entriesOf = fn( array $body, string $key ): array => array_column(
	$body['navs'][ array_search( $key, array_column( $body['navs'], 'key' ), true ) ]['entries'], 'httpUri'
);

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'lists every registered menu, in registry order', $navKeys( $body ) === [ 'main', 'footer' ] );
check( 'a menu reports its members in running order', $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );
check( 'a registered menu nobody is in is still listed, empty', $entriesOf( $body, 'footer' ) === [] );
check( 'reports whether the module that renders any of this is active', $body['active'] === false );
check( 'offers every GET route as a possible entry, not just the page ones', array_column( $body['routes'], 'httpUri' ) === [ '/', '/robots.txt', '/contact', '/legal' ] );
check( 'labels a route by the /_nino/webpage<uri>/name key the menu would render', $body['routes'][0]['label'] === 'Start' );
check( '...and falls back to the path for one nobody named', $body['routes'][1]['label'] === '/robots.txt' );
check( 'a route with no name is reported as such - routeLines() would skip it', $body['routes'][1]['named'] === false && $body['routes'][0]['named'] === true );
check( 'a route that is in config.php is not marked as a runtime one', array_column( $body['routes'], 'runtime' ) === [ false, false, false, false ] );

// A menu is saved as a whole: Save posts the complete running order, there is no
// action that adds, moves or removes one entry
check( 'the three per-entry actions are gone', array_keys( \Nino\Modules\Navigation\Admin::actions() ) === [ 'navs/list', 'navs/save', 'navs/delete' ] );

$configNow = static fn(): string => (string) file_get_contents( $sandbox. '/private/config.php' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/legal', '/', '/contact' ] ] );
check( 'saving a menu with its entries succeeds', $status === 200 );
check( 'the entries are the menu\'s running order, in the order they were posted', $entriesOf( $body, 'main' ) === [ '/legal', '/', '/contact' ] );

$routesAfterOrder = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'membership is written onto the route itself, densely numbered 1..n', [
	$routesAfterOrder['GET://legal']['navs'], $routesAfterOrder['GET://']['navs'], $routesAfterOrder['GET://contact']['navs'],
] === [ [ 'main' => 1 ], [ 'main' => 2 ], [ 'main' => 3 ] ] );
check( 'the route array itself is not reordered by a menu save', array_keys( $routesAfterOrder ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );
check( 'no runtime membership key is written while there are none', array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );

// A member that is left out loses its membership - and a route left in no menu
// carries no "navs" at all rather than an empty one
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/contact', '/legal' ] ] );
check( 'a save with fewer entries succeeds', $status === 200 && $entriesOf( $body, 'main' ) === [ '/contact', '/legal' ] );

$routesAfterLeaveOut = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'the entries that stay are renumbered from 1', [ $routesAfterLeaveOut['GET://contact']['navs'], $routesAfterLeaveOut['GET://legal']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );
check( 'a route that was left out carries no "navs" key rather than an empty one, and survives', isset( $routesAfterLeaveOut['GET://'] ) === true && isset( $routesAfterLeaveOut['GET://']['navs'] ) === false );

// Refusals write nothing
$configBeforeRefusals = $configNow();
foreach( [
	'a route listed twice' 					=> [ [ '/contact', '/legal', '/contact' ], 400, 'navs_duplicate_entry' ],
	'the same route in two spellings' => [ [ '/contact', 'contact/' ], 400, 'navs_duplicate_entry' ],
	'a route that does not exist' 	=> [ [ '/contact', '/does-not-exist' ], 404, 'navs_unknown_route' ],
	'a workbench route' 							=> [ [ '/_admin' ], 404, 'navs_unknown_route' ],
	'entries that are no list' 			=> [ 'contact', 400, 'navs_invalid_entries' ],
	'an entry that is no uri' 			=> [ [ '/contact', 7 ], 400, 'navs_invalid_entries' ],
] as $label => [ $refused, $refusedStatus, $refusedCode ] ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => $refused ] );
	check( $label. ' is refused with '. $refusedStatus. ' and its code', $status === $refusedStatus && ( $body['code'] ?? '' ) === $refusedCode );
}
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'nope', 'key' => 'nope', 'entries' => [ '/contact' ] ] );
check( 'entries for an unknown menu 404 as well', $status === 404 );
check( '...and none of the refusals wrote anything', $configNow() === $configBeforeRefusals );

// Entries stay optional: a save without them only creates or renames
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main' ] );
check( 'a save without entries leaves the running order alone', $status === 200 && $entriesOf( $body, 'main' ) === [ '/contact', '/legal' ] );

// Put the menu back the way the checks below were written against
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/contact' ] ] );
$routesAfterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a route that was left out comes back into the menu at the place it was given', [ $routesAfterRestore['GET://']['navs'], $routesAfterRestore['GET://contact']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );
check( '...and the one that was dropped is in no menu, and still a route', isset( $routesAfterRestore['GET://legal']['navs'] ) === false && isset( $routesAfterRestore['GET://legal'] ) === true );

// Create
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'meta' ] );
check( 'creating a menu succeeds', $status === 200 );
check( 'it is registered, at the end', $navKeys( $body ) === [ 'main', 'footer', 'meta' ] );
check( '...and starts empty', $entriesOf( $body, 'meta' ) === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'meta' ] );
check( 'creating one that already exists 409s', $status === 409 );

foreach( [ 'Main Menu', 'main/sub', '', '2fast', 'MAIN' ] as $badKey ) {
	[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => $badKey ] );
	check( 'rejects "'. $badKey. '" as a menu id', $status === 400 );
}

// Rename: follows the key into the registry and onto every member route
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'primary' ] );
check( 'renaming succeeds', $status === 200 );
check( 'the renamed menu keeps its place in the registry', $navKeys( $body ) === [ 'primary', 'footer', 'meta' ] );
check( '...and keeps its members, in order', $entriesOf( $body, 'primary' ) === [ '/', '/contact' ] );

$routesAfterRename = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'every member route carries the new key, at the priority it had', [ $routesAfterRename['GET://']['navs'], $routesAfterRename['GET://contact']['navs'] ] === [ [ 'primary' => 1 ], [ 'primary' => 2 ] ] );
check( 'the old key is gone from the routes', isset( $routesAfterRename['GET://']['navs']['main'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'primary', 'key' => 'footer' ] );
check( 'renaming onto a registered id 409s', $status === 409 );

// ...and onto one only a route carries: without this the rename would merge
// two memberships into one and silently drop a priority
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://']['navs']['handwritten'] = 4;
	return $config;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'primary', 'key' => 'handwritten' ] );
check( 'renaming onto an id only a hand-written route carries 409s too', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'nope', 'key' => 'whatever' ] );
check( 'renaming an unknown menu 404s', $status === 404 );

// Rename and entries in one call, one lock, one write
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'meta', 'key' => 'metax', 'entries' => [ '/legal' ] ] );
check( 'a rename and its entries go through in one save', $status === 200 && $navKeys( $body ) === [ 'primary', 'footer', 'metax' ] && $entriesOf( $body, 'metax' ) === [ '/legal' ] );
check( '...and the route carries the new key only', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://legal']['navs'] === [ 'metax' => 1 ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'metax', 'key' => 'meta', 'entries' => [] ] );
check( 'an empty list empties the menu, and the route it held carries no "navs" afterwards', $status === 200 && $entriesOf( $body, 'meta' ) === []
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://legal']['navs'] ) === false );

// Delete: out of the registry, and off every route
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting succeeds', $status === 200 );
check( 'it is out of the registry', $navKeys( $body ) === [ 'footer', 'meta' ] );

$routesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'every member route lost the membership too', isset( $routesAfterDelete['GET://contact']['navs'] ) === false );
check( 'a route that was in a second menu keeps that one', $routesAfterDelete['GET://']['navs'] === [ 'handwritten' => 4 ] );
check( 'the routes themselves survive', array_keys( $routesAfterDelete ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting an unknown menu 404s', $status === 404 );

// Deleting the last one really does leave none.
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'footer' ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'meta' ] );
check( 'the last menu can be deleted, and stays deleted', $navKeys( $body ) === [] );

/*	The registry a save or a delete starts from is the one config.php holds
	under the lock, not the copy this request loaded when it began: another
	request may have changed the file since, and writing the old copy back
	would lose its menus (or give back one it deleted). The copy is stale
	here on purpose - it knows 'main' and a 'ghost' that is gone, the file
	knows 'footer' and 'side'.	*/
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/html/navs'] = [ 'main', 'footer', 'side' ];
	return $config;
} );
$registryInFile = static fn(): array => \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navs'];

$appData['/nino/html/navs'] = [ 'main', 'ghost' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'top' ] );
check( 'a create keeps the menus that are only in config.php', $status === 200 && $registryInFile() === [ 'main', 'footer', 'side', 'top' ] && $navKeys( $body ) === [ 'main', 'footer', 'side', 'top' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'side' ] );
check( '...and an id that only config.php knows is taken', $status === 409 );

$appData['/nino/html/navs'] = [ 'main', 'ghost' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'side', 'key' => 'flank' ] );
check( 'a rename finds a menu that is only in config.php, and keeps its place', $status === 200 && $registryInFile() === [ 'main', 'footer', 'flank', 'top' ] );

$appData['/nino/html/navs'] = [ 'main', 'ghost' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'footer' ] );
check( 'a delete removes one menu and keeps the others of config.php', $status === 200 && $registryInFile() === [ 'main', 'flank', 'top' ] && $navKeys( $body ) === [ 'main', 'flank', 'top' ] );

\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/html/navs'] = [];
	return $config;
} );

unset( $appData['/nino/html/navs'] );
check( 'a missing navigation registry exposes no implicit menu', \Nino\Modules\Navigation\Admin::registry( $appData ) === [] );
$appData['/nino/html/navs'] = [];
check( 'an empty navigation registry stays empty', \Nino\Modules\Navigation\Admin::registry( $appData ) === [] );

/*	What "named" has to mean: the menu renders the name through the fill
	engine, and that merges the locale-independent global.php under the file
	of the locale the visitor is on. A name written once in global.php, and a
	name written only in a language that is not the native one, are both names
	the menu puts on the page - and the panel read the native locale's file
	alone, so it called them missing and refused to offer the route.	*/
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://shared']	= [ 'uri' => '/shared', 'body' => '[template /templates/page-shared]' ];
	$config['/nino/http/routes']['GET://jobs'] 		= [ 'uri' => '/jobs', 'body' => '[template /templates/page-jobs]' ];
	$config['/nino/http/routes']['GET://press'] 	= [ 'uri' => '/press', 'body' => '[template /templates/page-press]' ];
	return $config;
} );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ),
	[ '[[/_nino/webpage/shared/name]]' => 'Shared', '[[/_nino/webpage/press/name]]' => 'Press' ]
) );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] ),
	[ '[[/_nino/webpage/jobs/name]]' => 'Jobs' ]
) );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ),
	[ '[[/_nino/webpage/press/name]]' => 'Presse' ]
) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
$byUri = array_column( $body['routes'], null, 'httpUri' );

check( 'apiList succeeds after the three unnamed-looking routes joined', $status === 200 );
check( 'a name written once in global.php for every language is a name', ( $byUri['/shared']['named'] ?? null ) === true
	&& ( $byUri['/shared']['label'] ?? null ) === 'Shared' );
check( 'a name only a non-native locale carries is a name too - that menu renders it', ( $byUri['/jobs']['named'] ?? null ) === true
	&& ( $byUri['/jobs']['label'] ?? null ) === 'Jobs' );
check( 'the locale file still beats global.php for the label, as the fill engine does', ( $byUri['/press']['label'] ?? null ) === 'Presse' );
check( '...and a route no language named at all is still reported unnamed', ( $byUri['/robots.txt']['named'] ?? null ) === false
	&& ( $byUri['/robots.txt']['label'] ?? null ) === '/robots.txt' );

/*	Routes that exist only at runtime. A feature registers its routes in its
	init(), so /blog is in the live route array and nowhere in config.php - it
	has no route of its own to carry a "navs". The panel offers it all the same,
	and what it joins lives in '/nino/html/navroutes', which the shortcode reads
	for a live route (see kernel-smoke). Config.php never gets a stub route:	*/
$persistedRoutes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
$runtimeRoutes = [
	'GET://blog' 								=> [ 'uri' => '/blog', 'body' => '' ],
	'GET://blog/*' 							=> [ 'uri' => '/blog/*', 'body' => '' ],
	'GET://.search' 						=> [ 'uri' => '/.search', 'body' => '' ],
	'GET://_admin' 							=> [ 'uri' => '/_admin', 'body' => '' ],
	'GET://_admin/recovery.php' => [ 'uri' => '/_admin/recovery.php', 'body' => '' ],
	'POST://.newsletter' 				=> [ 'uri' => '/.newsletter', 'body' => '' ],
];
$appData['/nino/http/routes'] = $persistedRoutes + $runtimeRoutes;
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'main' ] );
check( 'a menu for the runtime routes to join is created', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
$offered = array_column( $body['routes'], null, 'httpUri' );
check( 'a route registered only at runtime is offered, marked as one', ( $offered['/blog']['runtime'] ?? null ) === true && ( $offered['/blog']['named'] ?? null ) === false );
check( '...a technical route that has no entry in config.php too: the menu\'s owner decides what a visitor should see', isset( $offered['/.search'] ) === true );
check( '...but no wildcard route, no route of the workbench - the recovery page included - and no POST endpoint', isset( $offered['/blog/*'], $offered['/_admin'], $offered['/_admin/recovery.php'], $offered['/.newsletter'] ) === false );
check( '...while the persisted technical route stays offered, and is not marked as a runtime one', ( $offered['/robots.txt']['runtime'] ?? null ) === false );

$configBeforeRuntime = $configNow();
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/blog', '/contact' ] ] );
check( 'a runtime route can be put into the running order', $status === 200 && $entriesOf( $body, 'main' ) === [ '/', '/blog', '/contact' ] );

$configAfterRuntime = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'its membership is written under /nino/html/navroutes, by the page\'s Element-URI, at its position in the order', ( $configAfterRuntime['/nino/html/navroutes'] ?? null ) === [ '/blog' => [ 'main' => 2 ] ] );
check( '...the routes that are in config.php take the numbers around it', [ $configAfterRuntime['/nino/http/routes']['GET://']['navs']['main'], $configAfterRuntime['/nino/http/routes']['GET://contact']['navs']['main'] ] === [ 1, 3 ] );
check( '...and no stub route for it is written into config.php - nor any other runtime route', array_keys( $configAfterRuntime['/nino/http/routes'] ) === array_keys( $persistedRoutes ) );
check( 'the live route array still holds the runtime routes after the save', isset( $appData['/nino/http/routes']['GET://blog'], $appData['/nino/http/routes']['GET://_admin/recovery.php'] ) === true
	&& array_keys( array_diff_key( $appData['/nino/http/routes'], $persistedRoutes ) ) === array_keys( $runtimeRoutes ) );

// The shortcode's side of it, from the same config: the menu renders the
// runtime route in its place once it is named
$appData['/nino/html/navroutes'] = $configAfterRuntime['/nino/html/navroutes'];
\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => 'Blog', '/_nino/webpage/home/name' => 'Start', '/_nino/webpage/contact/name' => 'Kontakt' ], 'de_DE' );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( 'the Navigation shortcode draws the runtime route between the two pages', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/:Start', '/blog:Blog', '/contact:Kontakt' ] );

// A runtime route that is gone - its feature switched off - is not offered, is
// not rendered, and drops out with the next save of that menu
$appData['/nino/http/routes'] = $persistedRoutes;
[ , $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
check( 'a runtime route that is not live any more is neither offered nor listed in its menu', isset( array_column( $body['routes'], null, 'httpUri' )['/blog'] ) === false && $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );
unset( $appData['/nino/html/navroutes'] );
check( '...nor rendered', in_array( '/blog:Blog', \Nino\Modules\Navigation::routeLines( $appData, 'main' ), true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/blog' ] ] );
check( 'it cannot be saved into a menu while it is gone', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/contact' ] ] );
check( 'the next save of that menu drops what was stored for it', $status === 200 && array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );

// Rename and delete follow a runtime membership too
$appData['/nino/http/routes'] = $persistedRoutes + $runtimeRoutes;
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/blog', '/', '/.search' ] ] );
check( 'two runtime routes in one menu, one of them first', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes'] ?? null ) === [ '/blog' => [ 'main' => 1 ], '/.search' => [ 'main' => 3 ] ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'primary' ] );
check( 'a rename follows the key onto a runtime membership', $status === 200 && $entriesOf( $body, 'primary' ) === [ '/blog', '/', '/.search' ]
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes']['/blog'] ?? null ) === [ 'primary' => 1 ] );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'primary' ] );
check( 'a menu id a runtime membership carries is taken', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting a menu takes its runtime memberships away too, and the key with them when none is left', $status === 200
	&& array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );
check( '...and the runtime routes are still live afterwards', isset( $appData['/nino/http/routes']['GET://blog'] ) === true );
$appData['/nino/http/routes'] = $persistedRoutes;
unset( $appData['/nino/html/navroutes'] );

/*	A page with a route per language - the imprint of Modules\Legal - is one
	entry: its routes share an Element-URI, the menu keeps the page by that
	uri, and a path of any of its routes names it. Nothing is written for it
	into config.php's routes	*/
$languageRoutes = [
	'GET://imprint' 		=> [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '', 'maintenance' => false ],
	'GET://impressum' 	=> [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '', 'maintenance' => false ],
	'GET://shared-page' => [ 'uri' => '/legal/shared', 'body' => '' ],
];
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', function( array $content ): array { $content['[[/_nino/webpage/legal/imprint/name]]'] = 'Impressum'; return $content; } );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', function( array $content ): array { $content['[[/_nino/webpage/legal/imprint/name]]'] = 'Imprint'; return $content; } );
$appData['/nino/http/routes'] = $persistedRoutes + $languageRoutes;
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'legal' ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
$languageEntries = array_values( array_filter( $body['routes'], static fn( array $route ): bool => $route['uri'] === '/legal/imprint' ) );
check( 'the routes of one Element-URI are one entry, named in the native language, with every path', count( $languageEntries ) === 1
	&& $languageEntries[0]['label'] === 'Impressum' && $languageEntries[0]['runtime'] === true && $languageEntries[0]['named'] === true );
check( '...the path of the native language names it, all of them are listed', $languageEntries[0]['httpUri'] === '/impressum' && $languageEntries[0]['paths'] === [ '/impressum', '/imprint' ] );
check( 'a route on its own has its one path', array_column( $body['routes'], 'paths', 'httpUri' )['/contact'] === [ '/contact' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'legal', 'key' => 'legal', 'entries' => [ '/contact', '/imprint' ] ] );
check( 'any path of the page puts it into the running order, once', $status === 200 && $entriesOf( $body, 'legal' ) === [ '/contact', '/impressum' ] );
$configAfterLanguage = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( '...under its Element-URI in /nino/html/navroutes, and nowhere as a route of config.php', ( $configAfterLanguage['/nino/html/navroutes'] ?? null ) === [ '/legal/imprint' => [ 'legal' => 2 ] ]
	&& isset( $configAfterLanguage['/nino/http/routes']['GET://imprint'] ) === false && isset( $configAfterLanguage['/nino/http/routes']['GET://impressum'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'legal', 'key' => 'legal', 'entries' => [ '/imprint', '/impressum' ] ] );
check( 'two paths of one page are listed twice', $status === 400 );
check( 'the menu reads it the way the shortcode does, in each language', ( function() use ( &$appData ): bool {
	\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
	$english = \Nino\Modules\Navigation::routeLines( $appData, 'legal' );
	\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
	return in_array( '/imprint:Imprint', $english, true ) === true && in_array( '/impressum:Impressum', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), true ) === true;
} )() );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'legal', 'key' => 'rechtliches' ] );
check( 'a rename follows the page\'s Element-URI', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes']['/legal/imprint'] ?? null ) === [ 'rechtliches' => 2 ] );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'rechtliches' ] );
check( 'a menu id a page is in is taken', $status === 409 );
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'rechtliches' ] );
check( 'deleting the menu takes the page out of it', array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false
	&& isset( $appData['/nino/http/routes']['GET://imprint'] ) === true );
$appData['/nino/http/routes'] = $persistedRoutes;
unset( $appData['/nino/html/navroutes'] );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
check( 'Navigations actions require an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Elements: element *content* CRUD ------------------------------------

echo "Elements - element content CRUD\n";

// A second type alongside testtype, with both a global and a locale field, so
// the global/locale split and the raw-bucket view have something real to show
file_put_contents( $sandbox. '/private/elements/contenttype.php', '<?php return [
	\'title\'	=> \'Content Type\',
	\'model\'	=> [
		\'title\'	=> [ \'type\' => \'string\', \'locale\' => true ],
		\'views\'	=> [ \'type\' => \'integer\', \'default\' => 0 ],
	],
];' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
check( 'apiTypes succeeds', $status === 200 );
check( 'apiTypes lists every type on disk', in_array( 'contenttype', array_column( $body['types'], 'type' ), true ) === true );
check( 'apiTypes carries each type\'s model along', ( $body['types'][array_search( 'contenttype', array_column( $body['types'], 'type' ), true )]['model']['views']['type'] ?? null ) === 'integer' );
// Not a literal list: an earlier Config::apiSave check in this same file adds
// fr_FR to the available locales, and this module must simply report whatever
// is configured at the time it runs
check( 'apiTypes reports the currently available locales', $body['locales'] === \Nino\Locales::getAvailableLocales( $appData ) );
check( 'apiTypes seeds the locale select with the native locale', $body['selectedLocale'] === 'de_DE' );

// The line under a type names its elements and is cut at 150 bytes. An
// element uri may hold any character the kernel api accepts, so the cut could
// land inside a multibyte one - and json_encode() answers a string that is
// not valid utf-8 with false, which is the whole panel coming back empty
\Nino\Filesystem::putFileContent( $appData, '/elements/langtype.php', [
	'title'	=> 'Umlaute',
	'model'	=> [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
	'*'			=> [ '*' => [] ],
] );
// Placed so the cut lands inside the 'ü': '(1) ' and the leading '/' are
// five bytes, so byte 150 of the line is byte 145 of the name
\Nino\Elements::insertElement( $appData, '/langtype/'. str_repeat( 'a', 144 ). 'über-uns', [ 'title' => 'Ü' ], 'de_DE' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
$langRow = $body['types'][ array_search( 'langtype', array_column( $body['types'], 'type' ), true ) ] ?? [];
check( 'a type whose element names are long and not ascii still answers', $status === 200 && ( $langRow['descr'] ?? '' ) !== '' );
check( '...with a line that is text, so the panel\'s answer can be encoded at all', mb_check_encoding( (string) ( $langRow['descr'] ?? '' ), 'UTF-8' ) === true
	&& json_encode( $body ) !== false );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'nope' ] );
check( 'apiList 404s for an unknown type', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'first', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'Erster', 'views' => 3 ],
] );
check( 'apiSave inserts a new element', $status === 200 );
check( 'apiSave returns the stored element', ( $body['element']['title'] ?? null ) === 'Erster' && ( $body['element']['views'] ?? null ) === 3 );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'not a slug', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'x' ],
] );
check( 'apiSave rejects a new uri that is not a plain slug', $status === 400 );

// The second locale of the same element - what the frontend's _saveLocales()
// sends as a follow-up request, global fields omitted (position > 0)
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'first', 'locale' => 'en_US', 'isNew' => false,
	'fields' => [ 'title' => 'First' ],
] );
check( 'apiSave updates a second locale of the same element', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'contenttype' ] );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the element across locales', count( $body['elements'] ) === 1 && $body['elements'][0]['uri'] === 'first' );
check( 'apiList labels an element by its title', $body['elements'][0]['label'] === 'Erster' );

// Only 'title', and the element's own uri otherwise. Guessing a label from
// 'label'/'name' or from whichever string field came first in the model meant
// the list showed a different field per type, and reordering a model silently
// relabelled every row in it
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiCreate', [ 'uri' => 'labeltype', 'title' => 'Label Type', 'model' => [
	'name' 	=> [ 'type' => 'string' ],
	'label' => [ 'type' => 'string' ],
	'title' => [ 'type' => 'string' ],
] ] );
check( 'a type for the label rules is created', $status === 200 );

callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'titled', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'label' => 'A label', 'title' => 'A title' ] ] );
callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'untitled', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'label' => 'A label' ] ] );
callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'blank-title', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'title' => '' ] ] );

[ , $labelBody ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'labeltype' ] );
$labels = array_column( $labelBody['elements'], 'label', 'uri' );

check( 'a title wins', ( $labels['titled'] ?? null ) === 'A title' );
check( 'no title falls back to the element\'s own uri, not to "label" or "name"', ( $labels['untitled'] ?? null ) === '/untitled' );
check( 'an empty title counts as no title', ( $labels['blank-title'] ?? null ) === '/blank-title' );

// --- a type that numbers its own elements --------------------------------
//
// Some types have entries with no name worth putting in a url - a gallery
// image, a price row. Asking for one anyway is how a project ends up with
// "bild-2", "bild-2-neu", "bild-2-final". Such a type is switched to numbering
// in this editor; the kernel then allocates the uri (see
// Elements::AUTOINCREMENT_PAD), and the element form stops asking.

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiCreate', [
	'uri' => 'gallery', 'title' => 'Gallery', 'autoincrement' => true,
	'model' => [ 'caption' => [ 'type' => 'string' ] ],
] );
check( 'a type can be created numbered', $status === 200 && ( $body['autoincrement'] ?? null ) === true );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiGet', [ 'uri' => 'gallery' ] );
check( 'apiGet reports that this type numbers its elements', ( $body['autoincrement'] ?? null ) === true );
check( '...and names the uri the next element would get', ( $body['next'] ?? null ) === '00001' );

// The element form posts no uri at all for such a type - that is the request
// to be numbered, and it is the kernel that decides which number
[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'caption' => 'One' ] ] );
check( 'saving with no uri allocates the first number', $status === 200 && ( $body['element']['.uri'] ?? null ) === '/gallery/00001' );
check( '...and reports the next one back, so the form\'s promise stays true', ( $body['nextUri'] ?? null ) === '00002' );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'caption' => 'Two' ] ] );
check( 'the next save gets the next number', ( $body['element']['.uri'] ?? null ) === '/gallery/00002' );

[ , $listBody ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'gallery' ] );
check( 'both numbered elements are listed', array_column( $listBody['elements'], 'uri' ) === [ '00001', '00002' ] );

[ , $typesBody ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
$galleryEntry = array_column( $typesBody['types'], null, 'type' )['gallery'] ?? [];
check( 'the element form is told which types are numbered', ( $galleryEntry['autoincrement'] ?? null ) === true );
check( '...and what the next uri would be, so it can show it', ( $galleryEntry['nextUri'] ?? null ) === '00003' );

// An update names its element like any other - only the insert is uri-less
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '00001', 'locale' => 'de_DE', 'isNew' => false, 'fields' => [ 'caption' => 'Edited' ] ] );
check( 'a numbered element updates under the uri it was given', $status === 200 );
check( '...and the edit actually landed', ( \Nino\Elements::getElement( $appData, '/gallery/00001', 'de_DE' )['caption'] ?? null ) === 'Edited' );

// The same request against a type that names its own elements stays a 400 -
// numbering is a property of the type, not a way to skip validation
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'x' ] ] );
check( 'an empty uri is still rejected for a type that is not numbered', $status === 400 );

// Turning numbering on later must not be able to collide with an element the
// type already has - including one that happens to look like a number
callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '00007', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'seven' ] ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [
	'uri' => 'labeltype', 'title' => 'Label Type', 'autoincrement' => true,
	'model' => [ 'name' => [ 'type' => 'string' ], 'label' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ] );
check( 'numbering can be switched on for an existing type', ( $body['autoincrement'] ?? null ) === true );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'eight' ] ] );
$seeded = $body['element']['.uri'] ?? null;
check( 'the counter starts past the highest number already in the type', $seeded === '/labeltype/00008' );
check( 'the elements that were named by hand keep their uris',
	( \Nino\Elements::getElement( $appData, '/labeltype/titled', 'de_DE' )['title'] ?? null ) === 'A title' );

// Switching it back off returns the type to being named by hand
[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [
	'uri' => 'labeltype', 'title' => 'Label Type', 'autoincrement' => false,
	'model' => [ 'name' => [ 'type' => 'string' ], 'label' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ] );
check( 'numbering can be switched off again', ( $body['autoincrement'] ?? null ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'x' ] ] );
check( '...and an uri-less save is refused again', $status === 400 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'apiGet succeeds', $status === 200 );
check( 'apiGet splits global fields out of the locale buckets', $body['global'] === [ 'views' => 3 ] );
check( 'apiGet keeps each locale\'s own translation', $body['locales']['de_DE']['title'] === 'Erster' && $body['locales']['en_US']['title'] === 'First' );

// An untranslated locale still shows up in the form, with empty fields: the
// element exists there (getElement resolves the shared '*' bucket, which is
// not empty), it just has no translation of its own yet. Same behaviour as
// _editor's equivalent - the form is the resolved view, not the storage
check( 'a locale with no translation of its own is still offered, with empty fields', array_key_exists( 'fr_FR', $body['locales'] ) === true && $body['locales']['fr_FR']['title'] === null );

// ...and this is exactly what the raw view is for: it shows the storage, so
// the difference between "translated" and "only resolving through '*'" -
// invisible in the form above - becomes readable
check( 'apiGet exposes the raw per-bucket storage', isset( $body['raw'] ) === true );
check( 'the global field sits in the "*" bucket, not in a locale one', $body['raw']['*'] === [ 'views' => 3 ] );
check( 'each locale bucket holds only that locale\'s own fields', $body['raw']['de_DE'] === [ 'title' => 'Erster' ] && $body['raw']['en_US'] === [ 'title' => 'First' ] );
check( 'a locale that only resolves through "*" has no bucket of its own at all', array_key_exists( 'fr_FR', $body['raw'] ) === false );
check( 'the raw view never leaks the type\'s own non-bucket keys', isset( $body['raw']['model'] ) === false && isset( $body['raw']['title'] ) === false );

echo "\nTranslations - native Text + Elements JSON round-trip\n";

// Translation fixtures deliberately mix public/per-locale content with
// global, blacklisted and image values which must never enter the package.
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', function( array $content ): array {
	$content['[[/translation/title]]'] 		= '<code>Nino</code> Start';
	$content['[[/translation/plain]]'] 		= 'Willkommen';
	$content['[[/translation/technical]]'] = 'do-not-translate';
	return $content;
} );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', function( array $content ): array {
	$content['[[/translation/title]]'] 		= '<code>Nino</code> Old';
	$content['[[/translation/plain]]'] 		= 'Old';
	$content['[[/translation/technical]]'] = 'technical';
	return $content;
} );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $content ): array {
	$content['[[/translation/global]]'] = 'Shared';
	return $content;
} );
\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $content ): array {
	$content[] = '/translation/technical';
	return array_values( array_unique( $content ) );
} );

\Nino\Filesystem::putFileContent( $appData, '/elements/translationtype.php', [
	'title' => 'Translation Type',
	'model' => [
		'title' 			=> [ 'type' => 'string', 'locale' => true ],
		'description' => [ 'type' => 'string', 'locale' => true, 'html' => true ],
		'features' 		=> [ 'type' => 'array', 'locale' => true ],
		'views' 			=> [ 'type' => 'integer' ],
		'image' 			=> [ 'type' => 'image', 'locale' => true ],
		'related' 		=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'locale' => true ],
		// A whitelisted translatable field: what the import lets through has
		// to be what the kernel takes, or one bad value fails the whole element
		'code' 				=> [ 'type' => 'string', 'locale' => true, 'whitelist' => [ '01', '02' ] ],
	],
	'*' => [
		'*' => [],
		'item' => [ 'views' => 7 ],
	],
	'de_DE' => [
		'item' => [
			'title' => 'Projekt',
			'description' => '<code>Native</code>',
			'features' => [ 'Schnell', 'Klein' ],
			'image' => 'native.webp',
			'related' => '/brandnewtype/de',
			'code' => '01',
		],
	],
	'en_US' => [
		'item' => [
			'title' => 'Old project',
			'description' => '<code>Old</code>',
			'features' => [ 'Old' ],
			'image' => 'english.webp',
			'related' => '/brandnewtype/en',
			'code' => '01',
		],
	],
] );
unset( $appData['./nino/elements/cache'] );

[ $status, $info ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiInfo' );
check( 'apiInfo succeeds and fixes the source to the native locale', $status === 200 && $info['nativeLocale'] === 'de_DE' );
check( 'apiInfo offers every configured import target', $info['locales'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

$_POST['action'] = 'translations/info';
$_POST['data'] = '{}';
$translationDispatch = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $translationDispatch );
check( 'Translations is registered in Admin\'s central action dispatcher', $translationDispatch['/nino/http/response']['statusCode'] === 200 );

[ $status, $translation ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiExport' );
check( 'apiExport returns a versioned translation document', $status === 200 && $translation['format'] === 'nino.translation' && $translation['version'] === 1 );
check( 'apiExport includes public native text without [[ ]] around its key', ( $translation['text']['/translation/title'] ?? null ) === '<code>Nino</code> Start' );
check( 'apiExport excludes global and blacklisted text', isset( $translation['text']['/translation/global'] ) === false && isset( $translation['text']['/translation/technical'] ) === false );
check( 'apiExport includes locale-scoped element content', ( $translation['elements']['translationtype']['item']['features'] ?? null ) === [ 'Schnell', 'Klein' ] );
check( 'apiExport excludes global and image element fields', isset( $translation['elements']['translationtype']['item']['views'] ) === false && isset( $translation['elements']['translationtype']['item']['image'] ) === false );
// A reference is per-locale content, but a uri is not translatable text - it
// is picked in the element form, where it shows as the element it points at
check( 'apiExport excludes element reference fields too', isset( $translation['elements']['translationtype']['item']['related'] ) === false );

// Post only a small translated subset: import is allowed to be partial and
// must count every invalid path while still applying valid siblings.
$translation['text'] = [
	'/translation/title' 		=> 'Home <code>Nino</code>',
	'/translation/plain' 		=> '<b>Welcome</b>',
	'/translation/global' 		=> 'HACKED',
	'/translation/technical' => 'HACKED',
	'/translation/unknown' 		=> 'HACKED',
];
$translation['elements'] = [
	'translationtype' => [
		'item' => [
			'title' => '<b>Project</b>',
			'description' => '<code>Translated</code><img src=x onerror=alert(1)>',
			'features' => [ 'One <b>x</b>', [ 'Deep <em>y</em>' ] ],
			'views' => 99,
			'image' => 'hacked.webp',
			'related' => '/brandnewtype/hacked',
			// '1' == '01' loosely, and the kernel compares strictly: a
			// pre-check that let it through made the whole element fail
			'code' => '1',
			'unknown' => 'HACKED',
		],
		'ghost' => [ 'title' => 'HACKED' ],
	],
	'unknown-type' => [ 'ghost' => [ 'title' => 'HACKED' ] ],
];

[ $status, $result ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [
	'targetLocale' => 'en_US',
	'translation' => $translation,
] );
check( 'apiImport succeeds for a configured target locale', $status === 200 && $result['targetLocale'] === 'en_US' );
check( 'apiImport reports Text values and rejected keys separately', $result['text'] === [ 'imported' => 2, 'skipped' => 3 ] );
check( 'apiImport reports Element fields and rejected paths separately', $result['elements'] === [ 'imported' => 3, 'skipped' => 7 ] );

$translatedText = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$nativeText = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'Text import preserves allowed code markup and sanitizes plain text', $translatedText['[[/translation/title]]'] === 'Home <code>Nino</code>' && $translatedText['[[/translation/plain]]'] === 'Welcome' );
check( 'Text import leaves native source, global and technical values untouched', $nativeText['[[/translation/title]]'] === '<code>Nino</code> Start' && $translatedText['[[/translation/technical]]'] === 'technical' );

$translatedElements = \Nino\Filesystem::getFileContent( $appData, '/elements/translationtype.php', [] );
check( 'Element import sanitizes plain, HTML and nested array strings', $translatedElements['en_US']['item']['title'] === 'Project' && $translatedElements['en_US']['item']['description'] === '<code>Translated</code>' && $translatedElements['en_US']['item']['features'] === [ 'One x', [ 'Deep y' ] ] );
check( 'Element import cannot overwrite global or image fields', $translatedElements['*']['item']['views'] === 7 && $translatedElements['en_US']['item']['image'] === 'english.webp' );
check( '...nor an element reference, which is a choice rather than a translation', $translatedElements['en_US']['item']['related'] === '/brandnewtype/en' );
check( 'Element import leaves the native bucket untouched', $translatedElements['de_DE']['item']['title'] === 'Projekt' );
check( 'a value the kernel would refuse is skipped on its own, and its siblings are imported', $translatedElements['en_US']['item']['code'] === '01' && $translatedElements['en_US']['item']['title'] === 'Project' );

// A key that holds a line break and a field that keeps paragraphs: what comes
// back from a translator is held to the format, not to the inline tags alone
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $content ): array => $content + [ '[[/translation/closing]]' => 'Danke.<br>Grüße' ] );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $content ): array => $content + [ '[[/translation/closing]]' => 'Thanks.<br>Regards' ] );
\Nino\Filesystem::putFileContent( $appData, '/elements/translationformats.php', [
	'title' => 'Translation Formats',
	'model' => [
		'body' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ],
		'note' => [ 'type' => 'string', 'locale' => true, 'breaks' => true ],
	],
	'*'			=> [ '*' => [] ],
	'de_DE'	=> [ 'item' => [ 'body' => '<p>Eins</p>', 'note' => "Zeile 1\nZeile 2" ] ],
	'en_US'	=> [ 'item' => [ 'body' => '<p>One</p>', 'note' => 'Line' ] ],
] );
unset( $appData['./nino/elements/cache'] );

$formatsTranslation = $translation;
$formatsTranslation['text'] = [ '/translation/closing' => "Thanks.\nBest regards,<script>x</script>" ];
$formatsTranslation['elements'] = [ 'translationformats' => [ 'item' => [
	'body' => '<p>One</p><ul><li>a</li><li>b</li></ul><script>x</script>',
	'note' => "L1\nL2 <b>x</b>",
] ] ];
[ $status, $result ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [ 'targetLocale' => 'en_US', 'translation' => $formatsTranslation ] );
$formatsText 			= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$formatsElements 	= \Nino\Filesystem::getFileContent( $appData, '/elements/translationformats.php', [] );
check( 'a text key that holds a line break takes the newlines of its translation as breaks', $status === 200 && ( $formatsText['[[/translation/closing]]'] ?? '' ) === 'Thanks.<br>Best regards,' );
check( 'a blocks field keeps the paragraphs and the list of its translation', ( $formatsElements['en_US']['item']['body'] ?? '' ) === '<p>One</p><ul><li>a</li><li>b</li></ul>' );
check( 'a breaks field keeps its newlines, and loses its tags like any plain field', ( $formatsElements['en_US']['item']['note'] ?? '' ) === "L1\nL2 x" );

$badTranslation = $translation;
$badTranslation['version'] = 99;
[ $status ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [ 'targetLocale' => 'en_US', 'translation' => $badTranslation ] );
check( 'apiImport rejects an incompatible format version', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [ 'targetLocale' => 'xx_XX', 'translation' => $translation ] );
check( 'apiImport rejects an unknown target locale', $status === 400 );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiExport' );
check( 'Translations actions require an authed _admin session', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

check( 'Routes says where it sits: the structure group, right after Element Types, under a fill', \Nino\Modules\Routes\Admin::nav() === [ 'routes', '/_admin/nav/routes', 20, 'structure' ] );

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

$withModule = $appData;
$withModule['/nino/modules'] = [ 'AdminSmokeDummyModule', '\\Nino\\Modules\\Navigation' ];

$registry = \Nino\Admin\Admin::panels( $withModule );
$order 		= array_keys( $registry );
check( 'the tool\'s own panels are all there, content first, then structure, then system, from nav() alone', array_values( array_diff( $order, [ 'dummy', 'navs' ] ) ) === [ 'dashboard', 'elements', 'text', 'images', 'logs', 'routes', 'users', 'language', 'backups', 'features', 'config' ] );
check( 'the module panel sits where its weight puts it - in the content group, after images (40), before logs (90)', array_search( 'dummy', $order, true ) === array_search( 'images', $order, true ) + 1 && array_search( 'logs', $order, true ) === array_search( 'dummy', $order, true ) + 1 );
check( 'the tool\'s own tabs sit on their panes', array_map( static fn( array $p ): array => array_keys( $p['tabs'] ), array_intersect_key( $registry, array_flip( [ 'elements', 'text', 'images', 'users', 'language' ] ) ) ) === [ 'elements' => [ 'types' ], 'text' => [ 'keys' ], 'images' => [ 'slots' ], 'users' => [ 'roles', 'lockout', 'recoverypw' ], 'language' => [ 'translations' ] ] );
check( 'the Navigations panel names the structure group and sits after routes (20)', array_search( 'navs', $order, true ) === array_search( 'routes', $order, true ) + 1 );
check( 'the Navigations panel is the Navigation module\'s and comes with it', $registry['navs']['class'] === \Nino\Modules\Navigation\Admin::class );
check( 'a panel reusing a core uri is dropped, the core panel keeps it', $registry['config']['class'] === \Nino\Modules\Config\Admin::class );

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
	$warnings = [];
	set_error_handler( static function( int $no, string $message ) use ( &$warnings ): bool { $warnings[] = $message; return true; } );
	$registry = \Nino\Admin\Admin::panels( $withFeatureNamer );
	restore_error_handler();
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
		&& str_contains( $html, 'admin-content-headless' ) === true && substr_count( $html, 'admin-panel-head' ) === count( $registry ) - 2;
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

	foreach( [ 'en_US', 'de_DE' ] as $locale ) {

		$fills = (array) include dirname( __DIR__ ). '/_admin/text/'. $locale. '.php';

		if( $panel['text'] !== '' && is_file( dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php' ) === true )
			$fills += (array) include dirname( __DIR__ ). $panel['text']. '/'. $locale. '.php';

		foreach( array_unique( $keys ) as $key )
			if( isset( $fills['[['. $key. ']]'] ) === false )
				$missingFills[] = $uri. ' '. $key. ' '. $locale;
	}
}
check( 'every word a panel asks for, in its scripts and in its markup, is a fill both interface languages define'. ( $missingFills === [] ? '' : ' - missing: '. implode( ', ', array_slice( $missingFills, 0, 8 ) ) ), $missingFills === [] );

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
check( 'no shipped panel is labelled with literal text any more', array_filter( \Nino\Admin\Admin::allPanels( $shipped ), static fn( array $p ): bool => str_starts_with( $p['label'], '/' ) === false || str_starts_with( $p['tab'], '/' ) === false ) === [] );
check( 'a panel naming a file that is not there is reported, and the file stays in the list for the bundler to skip', ( static function() use ( $appData ): bool {
	$ghost = $appData;
	$ghost['/nino/modules'] = [ 'AdminSmokeGhostAssetModule' ];
	$warnings = [];
	set_error_handler( static function( int $no, string $message ) use ( &$warnings ): bool { $warnings[] = $message; return true; } );
	$registry = \Nino\Admin\Admin::panels( $ghost );
	restore_error_handler();
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
// so the JSON refusal carries none (AGENTS.md, fills)
foreach( [ 'en_US', 'de_DE' ] as $locale ) {
	$elementFills = (array) include dirname( __DIR__ ). '/_admin/Nino/Modules/Elements/text/'. $locale. '.php';
	check( $locale. ': the Elements form words its refusals in fills, the JSON one without bracket characters',
		isset( $elementFills['[[/_admin/elements/error/json]]'], $elementFills['[[/_admin/elements/error/field-required]]'], $elementFills['[[/_admin/elements/label/locale-open]]'] ) === true
		&& preg_match( '/[\[\]]/', $elementFills['[[/_admin/elements/error/json]]'] ) === 0
		&& str_contains( $elementFills['[[/_admin/elements/label/locale-open]]'], '%s' ) === true && str_contains( $elementFills['[[/_admin/elements/label/locale-open]]'], '%d' ) === true );
}
check( 'the nav and the panes reach the template as fills', str_contains( \Nino\Html::renderTextfill( $withModule, '/_admin/nav' ), 'data-panel="dummy"' ) === true && str_contains( \Nino\Html::renderTextfill( $withModule, '/_admin/panes' ), 'id="dummy-list"' ) === true );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'dummy/list';
$_POST['data'] = json_encode( [] );
\Nino\Admin\Admin::handlePost( $withModule, $request );
check( 'handlePost dispatches the module\'s action', $request['/nino/http/response']['statusCode'] === 200 && ( $request['/nino/http/response']['body']['dummies'] ?? null ) === 3 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'config/get';
\Nino\Admin\Admin::handlePost( $withModule, $request );
check( 'a core action can not be taken over by a module reusing its name', ( $request['/nino/http/response']['body']['hijacked'] ?? false ) === false );

$withoutModule = $appData;
$withoutModule['/nino/modules'] = [];
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['action'] = 'navs/list';
\Nino\Admin\Admin::handlePost( $withoutModule, $request );
check( 'with the Navigation module off, navs/list is an unknown action and the panel is gone', $request['/nino/http/response']['statusCode'] === 404 && isset( \Nino\Admin\Admin::panels( $withoutModule )['navs'] ) === false );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
// The search-index rebuild is the Search feature's own action (tested in
// dapeio/nino-features); Config no longer carries it
check( 'Config no longer carries the search-index rebuild', isset( \Nino\Modules\Config\Admin::actions()['config/searchindex'] ) === false );

echo "\n";

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'nope' ] );
check( 'apiGet 404s for an unknown element', $status === 404 );

// Regression guard for the exact shape that used to crash the kernel's own
// deleteElement()/queryElements(): a hand-authored type file's top-level
// 'title' is a plain string, not a bucket of entries. Fed in literally rather
// than read off disk - this is a pure function, and the fixture files earlier
// checks in this file mutate would make it a moving target
$handAuthored = [
	'title' => 'Hand Authored',
	'model' => [ 'name' => [ 'type' => 'string', 'locale' => true ] ],
	'*' 		=> [ '*' => [], 'item1' => [ 'sort' => 1 ] ],
	'de_DE' => [ 'item1' => [ 'name' => 'Hallo' ], 'item2' => [ 'name' => 'Zwei' ] ],
];
check( 'rawBuckets survives a type file whose top-level "title" is a plain string', \Nino\Modules\Elements\Admin::rawBuckets( $handAuthored, 'item1' ) === [ '*' => [ 'sort' => 1 ], 'de_DE' => [ 'name' => 'Hallo' ] ] );
check( 'rawBuckets never returns another element\'s buckets', \Nino\Modules\Elements\Admin::rawBuckets( $handAuthored, 'item2' ) === [ 'de_DE' => [ 'name' => 'Zwei' ] ] );
check( 'rawBuckets returns nothing for an element that does not exist', \Nino\Modules\Elements\Admin::rawBuckets( $handAuthored, 'nope' ) === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'apiDelete succeeds', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'the deleted element is gone from every locale', $status === 404 );

// Deleting again is a no-op that still reports success: the kernel's
// deleteElement() unsets whatever is there and reports the write, it does not
// treat "already gone" as an error. Identical to _editor's own apiDelete -
// asserted here so a future change to either side has to be deliberate
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'deleting an already-deleted element is an idempotent no-op, not an error', $status === 200 );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'nope', 'uri' => 'first' ] );
check( 'apiDelete 400s for an unknown type', $status === 400 );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
check( 'Elements actions require an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Every action this module registers must actually resolve through the
// dispatcher - a typo in the action map would otherwise only surface in the ui
$dispatchable = true;
foreach( array_keys( \Nino\Modules\Elements\Admin::actions() ) as $actionName ) {
	$_POST['action'] = $actionName;
	$_POST['data']	 = json_encode( [ 'type' => 'contenttype', 'uri' => 'first' ] );
	$dispatchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\Admin::handlePost( $appData, $dispatchRequest );
	if( $dispatchRequest['/nino/http/response']['statusCode'] === 404 && ( $dispatchRequest['/nino/http/response']['body']['error'] ?? '' ) === 'unknown action' )
		$dispatchable = false;
}
check( 'every develements/* action is reachable through Admin::handlePost', $dispatchable === true );

echo "\n";


// --- The workbench's one reaction point -----------------------------------
//
// A panel declares what it is (see Panels); this says what just happened in
// the tool, so a module can attach without owning the panel the action
// belongs to. Notification only - the action has already answered.

echo "'/nino/admin/action' - a module watching what the workbench does\n";

$seen = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/action', function( array &$appData, array &$event ) use ( &$seen ): void {
	$seen[] = $event;
} );

$_POST['action'] 	= 'elements/list';
$_POST['data'] 		= json_encode( [ 'type' => 'contenttype' ] );
$listenerRequest 	= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $listenerRequest );

check( 'a dispatched action reaches a listener', count( $seen ) === 1 );
check( '...naming the action and the panel that handled it', ( $seen[0]['action'] ?? '' ) === 'elements/list' && ( $seen[0]['panel'] ?? '' ) === \Nino\Modules\Elements\Admin::class );
check( '...the account behind it', ( $seen[0]['user'] ?? '' ) === 'dev@example.com' );
check( '...what was posted', ( $seen[0]['data']['type'] ?? '' ) === 'contenttype' );
check( '...and the status it answered with', ( $seen[0]['status'] ?? 0 ) === 200 );

// The failures are the half the activity log leaves out, and the half a module
// watching for someone probing a panel actually wants
$_POST['action'] 	= 'elements/get';
$_POST['data'] 		= json_encode( [ 'type' => 'nope', 'uri' => 'nope' ] );
$failedRequest 		= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $failedRequest );
check( 'a failed action reaches it too, with its status', count( $seen ) === 2 && ( $seen[1]['status'] ?? 0 ) !== 200 );

// Nothing ran, so there is nothing to announce
$_POST['action'] 	= 'no/such-action';
$unknownRequest 	= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $unknownRequest );
check( 'an action no panel registered announces nothing', count( $seen ) === 2 );

// The listener is handed a description, not the request: a panel's answer is
// already written, and a listener that could rewrite it would be a veto with
// no gate in front of it
$_POST['action'] 	= 'elements/list';
$_POST['data'] 		= json_encode( [ 'type' => 'contenttype' ] );
$immutableRequest 	= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/action', function( array &$appData, array &$event ): void {
	// Both shapes: the flat one this event has, and the one it would have if
	// the request itself were ever handed over by reference
	$event['status'] = 500;
	$event['/nino/http/response']['statusCode'] = 500;
	$event['body'] = [ 'error' => 'rewritten' ];
} );
\Nino\Admin\Admin::handlePost( $appData, $immutableRequest );
check( 'a listener cannot change the answer the panel already gave', $immutableRequest['/nino/http/response']['statusCode'] === 200 );

// The audit line is the tool's own guarantee, not a listener's - a log that
// could be lost by not registering a callback would not be a log
$logBefore = count( \Nino\Modules\Logs\Admin::recentLines( $appData, 50 ) );
$_POST['action'] 	= 'types/save';
$_POST['data'] 		= json_encode( [ 'uri' => 'testtype', 'title' => 'Test Type Renamed', 'model' => [ 'name' => [ 'type' => 'string' ] ] ] );
$loggedRequest 		= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $loggedRequest );
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

$_POST['action'] 	= 'recoverypw/save';
$_POST['data'] 		= json_encode( [ 'current' => 'a wrong old secret', 'pw' => 'a brand new secret' ] );
$secretRequest 		= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $secretRequest );
check( 'recoverypw/save reaches a listener with its passwords blank', count( $secretSeen ) === 1 && ( $secretSeen[0]['action'] ?? '' ) === 'recoverypw/save'
	&& ( $secretSeen[0]['status'] ?? 0 ) === 401 && ( $secretSeen[0]['data'] ?? null ) === [ 'current' => '', 'pw' => '' ] );

$_POST['action'] 	= 'users/save';
$_POST['data'] 		= json_encode( [ 'username' => 'nobody-at-all@example.com', 'mail' => 'nobody-at-all@example.com', 'pw' => 'a password for a user', 'currentPassword' => 'the current one', 'role' => '' ] );
$secretRequest 		= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $secretRequest );
check( '...so do the users panel\'s, for every action', count( $secretSeen ) === 2 && ( $secretSeen[1]['data']['pw'] ?? null ) === '' && ( $secretSeen[1]['data']['currentPassword'] ?? null ) === ''
	&& ( $secretSeen[1]['data']['mail'] ?? '' ) === 'nobody-at-all@example.com' && str_contains( json_encode( $secretSeen ), 'secret' ) === false && str_contains( json_encode( $secretSeen ), 'a password for a user' ) === false );

$_POST['action'] 	= 'elements/list';
$_POST['data'] 		= json_encode( [ 'type' => 'contenttype' ] );
$secretRequest 		= [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $secretRequest );
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

\Nino\Auth::insertUser( $appData, 'coarse@example.com', 'correct horse battery staple', [ \Nino\Modules\Elements\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'coarse@example.com', 'correct horse battery staple' );

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
\Nino\Auth::insertUser( $appData, 'scoped@example.com', 'correct horse battery staple', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/contenttype/update/title',
] );
\Nino\Auth::loginUser( $appData, 'scoped@example.com', 'correct horse battery staple' );

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
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	$_FILES['file'] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'test.jpg', 'size' => filesize( $path ) ];
	\Nino\Modules\Elements\Admin::apiUploadImage( $appData, $request );
	@unlink( $path );
	unset( $_FILES['file'] );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
\Nino\Elements::insertElementType( $appData, '/scopedimage', [
	'title' => [ 'type' => 'string', 'locale' => false ],
	'photo' => [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
] );
\Nino\Elements::insertElement( $appData, '/scopedimage/one', [ 'title' => 'Eins' ], 'de_DE' );

\Nino\Auth::insertUser( $appData, 'uploadtitle@example.com', 'correct horse battery staple', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/scopedimage/update/title',
] );
\Nino\Auth::loginUser( $appData, 'uploadtitle@example.com', 'correct horse battery staple' );
[ $status ] = callUploadScoped( $appData, [ 'type' => 'scopedimage', 'uri' => 'one', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'an upload into a field the account may not change is refused like a save of it', $status === 403 );
check( '...and nothing was written', ( \Nino\Elements::getElement( $appData, '/scopedimage/one', '*', [] )['photo'] ?? null ) === null );

\Nino\Auth::insertUser( $appData, 'uploadphoto@example.com', 'correct horse battery staple', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/scopedimage/update/photo',
] );
\Nino\Auth::loginUser( $appData, 'uploadphoto@example.com', 'correct horse battery staple' );
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
\Nino\Auth::insertUser( $appData, 'typewide@example.com', 'correct horse battery staple', [
	\Nino\Modules\Elements\Admin::MANAGE_PERM,
	'/_admin/elements/contenttype/*',
] );
\Nino\Auth::loginUser( $appData, 'typewide@example.com', 'correct horse battery staple' );

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

\Nino\Auth::insertUser( $appData, 'textscoped@example.com', 'correct horse battery staple', [
	\Nino\Modules\Text\Admin::MANAGE_PERM,
	'/_admin/text/update/scoped/*',
] );
\Nino\Auth::loginUser( $appData, 'textscoped@example.com', 'correct horse battery staple' );

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


// --- Activity log: one entry is one line, and every write describes itself ---

echo "Activity log - one entry is one line, every write describes itself\n";

$appData['/nino/admin/logs'] = true;
$before = count( \Nino\Modules\Logs\Admin::recentLines( $appData, 500 ) );
// What a posted template name could carry: a line break, then a second
// line shaped exactly like a real one
\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', "Saved template \"x\"\n". date( 'Y-m-d H:i' ). "  root@example.com  Deleted every account" );
$lines = \Nino\Modules\Logs\Admin::recentLines( $appData, 500 );
check( 'a line break in a recorded value writes no second line', count( $lines ) === $before + 1 );
check( '...the whole value stays on the one line, attributed to who wrote it', count( array_filter( $lines, static fn( $l ): bool => str_starts_with( is_array( $l ) ? implode( ' ', $l ) : (string) $l, date( 'Y-m-d H:i' ). '  root@example.com' ) ) ) === 0 );

/*	...and one byte that is not valid utf-8, which is the reachable half of
	Http::_finalizeResponse()'s encoding contract (see kernel-smoke.php). A
	recorded line is built from what a panel posted (Admin::_logAction()),
	and a post is bytes: one latin-1 byte in an element name went into the
	log, json_encode() then refused the whole logs/list body, and false
	written into the body echoed as the empty string. The panel read that
	empty 200 as a success with nothing in it, showed a blank list, and said
	nothing - for every later opening too, until somebody edited the file by
	hand. _oneLine() strips control characters, not malformed utf-8	*/
\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', "Saved element \"Gru\xdfe\"" );

$finalizeLogResponse = new ReflectionMethod( '\Nino\Http', '_finalizeResponse' );
$finalizeLogResponse->setAccessible( true );

$logListRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '' ] ];
\Nino\Modules\Logs\Admin::apiList( $appData, $logListRequest );
check( 'logs/list answers with the lines', is_array( $logListRequest['/nino/http/response']['body']['lines'] ?? null ) === true );

$finalizeLogResponse->invokeArgs( null, [ &$logListRequest ] );
$logListBody = json_decode( (string) $logListRequest['/nino/http/response']['body'], true );
check( 'one malformed byte in a logged value does not blank the whole panel', is_array( $logListBody ) === true && $logListRequest['/nino/http/response']['statusCode'] === 200 );
check( '...and every other line is still there to read', count( array_filter( $logListBody['lines'] ?? [], static fn( $l ): bool => str_contains( is_array( $l ) ? implode( ' ', $l ) : (string) $l, 'Delete Element Type /deletable' ) === true ) ) === 1 );

/*	The write itself, where the day's file cannot be written: a directory
	standing in its place here, which is what a permission or a full disk
	does, provoked without either. Reading it and writing it both raised php's
	own warning, which the framework's handler ends the request on - so the
	action being logged died in its log line, and a panel listing the log died
	on the same file. And the lock taken for the write was released only on
	the way that succeeds	*/
$logsDir		= (string) ( new ReflectionClassConstant( '\Nino\Modules\Logs\Admin', 'LOGS_DIR' ) )->getValue();
$todayLog		= $logsDir. '/'. date( 'Y-m-d' ). '.php';
$todayPath	= \Nino\Filesystem::path( $appData, $todayLog );
$todayAside	= $todayPath. '.aside';

if( is_file( $todayPath ) === true )
	rename( $todayPath, $todayAside );
mkdir( $todayPath, 0755, true );

$logWarnings = [];
// What the code silenced with @ is not a warning anyone sees - the framework's
// handler reads error_reporting() the same way
set_error_handler( static function( int $no, string $message ) use ( &$logWarnings ): bool {
	if( ( error_reporting() & $no ) !== 0 )
		$logWarnings[] = $message;
	return true;
} );
\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', 'Blocked write' );
$blockedList = \Nino\Modules\Logs\Admin::recentLines( $appData, 5 );
restore_error_handler();

check( 'a day\'s file that cannot be written or read raises no engine warning - the action being logged goes on', array_filter( $logWarnings, static fn( string $w ): bool => str_contains( $w, 'file_put_contents' ) || str_contains( $w, 'file_get_contents' ) ) === [] );
check( '...the failure is reported through the framework\'s own channel instead', count( array_filter( $logWarnings, static fn( string $w ): bool => str_starts_with( $w, 'Activity log write failed' ) ) ) === 1 );
// A guard rather than a regression: without a throw the old code released
// the lock too - what it did not do was release it past one
check( '...with the lock released whichever way the write went', \Nino\Filesystem::unlockFile( $appData, $todayLog ) === false );
check( '...and the listing past that file still answers', is_array( $blockedList ) === true );

rmdir( $todayPath );
if( is_file( $todayAside ) === true )
	rename( $todayAside, $todayPath );

\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', 'After the block' );
check( 'the next line is written once the file can be again', str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, 5 ) ), 'After the block' ) === true );

// The shell asks the class that ran an action for its line (see
// Admin::_logAction()): a line on any other class is one nobody reads, which
// is how the whole Templates panel logged nothing. So: the handler of every
// one of these actions is the class asked, and it answers
$described = [
	[ \Nino\Modules\Routes\Admin::class,						'routes/save',				[ 'httpUri' => '/x' ],							'/x' ],
	[ \Nino\Modules\Routes\Admin::class,						'routes/delete',			[ 'httpUri' => '/x' ],							'/x' ],
	[ \Nino\Modules\Routes\Admin::class,						'routes/savetexts',		[ 'uri' => '/.newsletter' ],				'/.newsletter' ],
	[ \Nino\Modules\Config\Admin::class,						'config/save',				[ 'fields' => [ '/nino/cache/ttl' => 1 ] ],	'/nino/cache/ttl' ],
	[ \Nino\Modules\Images\Slots::class,						'slots/delete',				[ 'uri' => '/home/hero' ],					'/home/hero' ],
	[ \Nino\Modules\Language\Translations::class,	'translations/import',	[ 'targetLocale' => 'fr_FR' ],			'fr_FR' ],
	[ \Nino\Modules\Backups\Admin::class,					'backups/restore',		[ 'date' => '2026-09-05' ],				'2026-09-05' ],
	[ \Nino\Modules\Backups\Admin::class,					'backups/now',				[],																	'Backup' ],
	[ \Nino\Modules\Users\RecoveryPassword::class,		'recoverypw/save',		[],																	'Recovery Password' ],
	[ \Nino\Modules\Navigation\Admin::class,				'navs/delete',				[ 'key' => 'main' ],								'main' ],
	[ \Nino\Modules\Features\Admin::class,					'features/activate',	[ 'key' => 'sample' ],							'sample' ],
];
$silent = [];
foreach( $described as [ $class, $action, $data, $needle ] ) {
	$handler = $class::actions()[$action][0] ?? '';
	if( $handler !== $class || method_exists( $class, 'log' ) === false || str_contains( (string) $class::log( $action, $data ), $needle ) === false )
		$silent[] = $action;
}
check( 'every one of these writes is described by the class that runs it'. ( $silent === [] ? '' : ' - silent: '. implode( ', ', $silent ) ), $silent === [] );

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
check( 'both languages take the same number of params'. ( $placeholders === [] ? '' : ' - differ: '. implode( ', ', $placeholders ) ), $placeholders === [] );
check( 'no word carries a live shortcode - the page substitutes a value before the shortcodes run', array_filter( $wordFiles['en_US'] + $wordFiles['de_DE'], fn( $v, $k ) => str_starts_with( $k, '[[/_admin/error/' ) && preg_match( '/[\\[\\]]/', (string) $v ) === 1, ARRAY_FILTER_USE_BOTH ) === [] );

echo "\n";


// --- Final: unauthed guard sanity check across every module -------------

echo "Every module rejects an unauthed request\n";

\Nino\Auth::logoutUser( $appData );
$unauthedListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiList( $appData, $unauthedListRequest );
check( 'apiList requires an authed _admin session, same as every other module', $unauthedListRequest['/nino/http/response']['statusCode'] === 401 );
$unauthedNowRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiNow( $appData, $unauthedNowRequest );
check( '...apiNow too', $unauthedNowRequest['/nino/http/response']['statusCode'] === 401 );

echo "\n";

echo "$checks checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
