<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	dev-smoke.php			Dependency-free smoke test for the dev-only tooling
 *											(_dev/Dev.php) - the session gate and the element-type
 *											editor in particular, since a mistake there could either
 *											let a save reach a type file it shouldn't, or - worse -
 *											clobber a type's real content ('*'/locale buckets) while
 *											only meaning to touch its model. Runs against an isolated
 *											sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/dev-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_dev/Dev.php';
require __DIR__. '/../_admin/Admin.php'; // only for Backup - Restore reads its output, doesn't call into it

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
mkdir( $sandbox. '/elements', 0777, true );

// A hand-authored-shaped type file (top-level 'title' key, real locale content) -
// apiSave must only ever touch 'title'/'model', never this real content
file_put_contents( $sandbox. '/elements/testtype.php', '<?php return [
	\'title\'	=> \'Test Type\',
	\'model\'	=> [ \'name\' => [ \'type\' => \'string\', \'locale\' => true ] ],
	\'*\'			=> [ \'*\' => [] ],
	\'de_DE\'	=> [ \'item1\' => [ \'name\' => \'Hallo\' ] ],
];' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']	= $sandbox;
// Mirrors \Nino\init()'s default (no NINO_CONFIG_DIR set): configpath falls
// back to the project root, same as this sandbox's regular path
$appData['./nino/filesystem/configpath']	= $sandbox;
$appData['/nino/dir']		= '';
$appData['/nino/locales/native']		= 'de_DE';
$appData['/nino/locales/available']	= [ 'de_DE', 'en_US' ];

// Config::apiList() reads config.php fresh rather than $appData (see its
// docblock) - a real deployment always has $appData booted from this file
// in the first place, so mirror that here instead of only setting $appData
\Nino\Filesystem::putFileContent( $appData, '/config.php', [
	'/nino/error/log'					=> false,
	'/nino/error/display'			=> true,
	'/nino/locales/native'		=> $appData['/nino/locales/native'],
	'/nino/locales/available'	=> $appData['/nino/locales/available'],
	'/nino/html/assets'				=> [],
	'/nino/http/routes'				=> [],
] );

echo "Sandbox: $sandbox\n\n";


// --- Dev::isAuthed / guard / handlePost (login/logout) ------------------

echo "Dev::isAuthed / guard / handlePost (login/logout)\n";

check( 'isAuthed defaults to false', \Nino\Dev\Dev::isAuthed( $appData ) === false );

$guardRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'guard rejects an unauthed request', \Nino\Dev\Dev::guard( $appData, $guardRequest ) === false );
check( 'guard sets a 401', $guardRequest['/nino/http/response']['statusCode'] === 401 );

$_POST['action']	= 'dev/login';
$_POST['data']		= json_encode( [ 'password' => 'definitely-the-wrong-password' ] );
$loginRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Dev::handlePost( $appData, $loginRequest );
check( 'a wrong password is rejected', $loginRequest['/nino/http/response']['statusCode'] === 401 );
check( 'a rejected login never opens the session gate', \Nino\Dev\Dev::isAuthed( $appData ) === false );

// 4 more wrong attempts (5 total) should trip the lockout
for( $i = 0; $i < 4; $i++ ) {
	$attemptRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Dev\Dev::handlePost( $appData, $attemptRequest );
}
$lockedOutRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Dev::handlePost( $appData, $lockedOutRequest );
check( 'the 6th attempt is locked out (429), not just rejected as wrong', $lockedOutRequest['/nino/http/response']['statusCode'] === 429 );

$_POST['data'] = json_encode( [ 'password' => 'definitely-the-wrong-password' ] );
$duringCooldownRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Dev::handlePost( $appData, $duringCooldownRequest );
check( 'the lockout applies regardless of the password tried next', $duringCooldownRequest['/nino/http/response']['statusCode'] === 429 );

// Reset the lockout state for the rest of the file - a fresh cooldown window shouldn't leak into later checks
\Nino\Filesystem::putFileContent( $appData, '/_dev/.lockout.json', [ 'tries' => 0, 'until' => 0 ] );

$_POST['action'] = 'devtypes/list';
$_POST['data']	 = '{}';
$unauthedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Dev::handlePost( $appData, $unauthedRequest );
check( 'a module action is rejected while unauthed', $unauthedRequest['/nino/http/response']['statusCode'] === 401 );

// The shipped PASSWORD_HASH is a placeholder matching no real password (see Dev.php) -
// simulate a successful login directly, the same way Auth-based tests call insertUser()
// directly rather than driving a registration form
\Nino\Runtime::setSessionValue( $appData, './nino/dev/authed', true );
check( 'isAuthed reflects the session flag', \Nino\Dev\Dev::isAuthed( $appData ) === true );

$_POST['action'] = 'dev/logout';
$_POST['data']	 = '{}';
$logoutRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Dev::handlePost( $appData, $logoutRequest );
check( 'logout clears the session flag', \Nino\Dev\Dev::isAuthed( $appData ) === false );

// Re-authenticate for every check below
\Nino\Runtime::setSessionValue( $appData, './nino/dev/authed', true );

echo "\n";


// --- Dev\ElementTypes::apiList / apiGet / apiSave / apiCreate -----------

echo "Dev\\ElementTypes::apiList / apiGet / apiSave / apiCreate\n";

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiList( $appData, $listRequest );
$types = $listRequest['/nino/http/response']['body']['types'] ?? [];
check( 'apiList finds the sandbox type', count( $types ) === 1 && $types[0]['uri'] === 'testtype' );
check( 'apiList reports the real title', $types[0]['title'] === 'Test Type' );
check( 'apiList reports the real field count', $types[0]['fieldCount'] === 1 );
check( 'apiList exposes the allowed field types', in_array( 'image', $listRequest['/nino/http/response']['body']['fieldTypes'] ?? [], true ) === true );

$_POST['data'] = json_encode( [ 'uri' => 'testtype' ] );
$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiGet( $appData, $getRequest );
$got = $getRequest['/nino/http/response']['body'] ?? [];
check( 'apiGet returns the real title', $got['title'] === 'Test Type' );
check( 'apiGet returns the real model', ( $got['model']['name']['type'] ?? null ) === 'string' );

$_POST['data'] = json_encode( [ 'uri' => 'not-a-real-type' ] );
$getMissingRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiGet( $appData, $getMissingRequest );
check( 'apiGet 404s for an unknown type', $getMissingRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'uri' => '../escape' ] );
$getInvalidRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiGet( $appData, $getInvalidRequest );
check( 'apiGet rejects a non-slug type uri before ever touching the filesystem', $getInvalidRequest['/nino/http/response']['statusCode'] === 400 );

// Save: rename the field, add an image field and a suffixed/maxlength'd one,
// and slip in a field with an invalid type - the invalid one must be
// silently dropped, not crash or persist
$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80 ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'suffix' => 'ignored on image' ],
		'price' 	=> [ 'type' => 'double', 'suffix' => '€' ],
		'active' 	=> [ 'type' => 'boolean', 'suffix' => 'ignored on boolean' ],
		'bogus' 	=> [ 'type' => 'not-a-real-type' ],
	],
] );
$saveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiSave( $appData, $saveRequest );
check( 'apiSave succeeds', $saveRequest['/nino/http/response']['statusCode'] === 200 );

$afterSave = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'apiSave updates the title', $afterSave['title'] === 'Test Type Renamed' );
check( 'apiSave keeps the valid field', ( $afterSave['model']['name']['required'] ?? null ) === true );
check( 'apiSave sets a string field\'s maxlength', ( $afterSave['model']['name']['maxlength'] ?? null ) === 80 );
check( 'apiSave adds the new field', ( $afterSave['model']['photo']['width'] ?? null ) === 40 );
check( 'apiSave drops suffix on an image field', isset( $afterSave['model']['photo']['suffix'] ) === false );
check( 'apiSave sets a suffix on a non-boolean/image field', $afterSave['model']['price']['suffix'] === '€' );
check( 'apiSave drops suffix on a boolean field', isset( $afterSave['model']['active']['suffix'] ) === false );
check( 'apiSave silently drops a field with an unknown type', isset( $afterSave['model']['bogus'] ) === false );
check( 'apiSave never touches the "*" bucket', $afterSave['*'] === [ '*' => [] ] );
check( 'apiSave never touches real locale content', ( $afterSave['de_DE']['item1']['name'] ?? null ) === 'Hallo' );

// Switching a field's locale flag must migrate its stored value(s), not just
// the model - otherwise a stale per-locale value keeps shadowing the new
// '*' value forever (_cacheElement() merges locale data over '*' data)
$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [ 'name' => [ 'type' => 'string' ] ],
] );
$globalizeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiSave( $appData, $globalizeRequest );
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
\Nino\Dev\ElementTypes::apiSave( $appData, $localizeRequest );
check( 'apiSave succeeds when switching a field back to per-locale', $localizeRequest['/nino/http/response']['statusCode'] === 200 );

$afterLocalize = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'switching back to per-locale copies the value into every locale', $afterLocalize['de_DE']['item1']['name'] === 'Hallo' && $afterLocalize['en_US']['item1']['name'] === 'Hallo' );
check( 'switching back to per-locale removes the stale value from "*"', isset( $afterLocalize['*']['item1']['name'] ) === false );

$_POST['data'] = json_encode( [ 'uri' => 'testtype', 'title' => 'x', 'model' => [] ] );
foreach( [ '../escape', 'Has Uppercase', '1startswithdigit', '' ] as $badUri ) {
	$_POST['data'] = json_encode( [ 'uri' => $badUri, 'title' => 'x', 'model' => [] ] );
	$badSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Dev\ElementTypes::apiSave( $appData, $badSaveRequest );
	check( "apiSave rejects an invalid type uri ('$badUri')", $badSaveRequest['/nino/http/response']['statusCode'] === 400 );
}

$_POST['data'] = json_encode( [ 'uri' => 'brandnewtype', 'title' => 'Brand New', 'model' => [ 'headline' => [ 'type' => 'string' ] ] ] );
$createRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiCreate( $appData, $createRequest );
check( 'apiCreate succeeds', $createRequest['/nino/http/response']['statusCode'] === 200 );

$created = \Nino\Filesystem::getFileContent( $appData, '/elements/brandnewtype.php', false );
check( 'apiCreate writes the title', $created['title'] === 'Brand New' );
check( 'apiCreate writes the model', ( $created['model']['headline']['type'] ?? null ) === 'string' );
check( 'apiCreate starts with an empty shell, no fabricated content', $created['*'] === [ '*' => [] ] );

$duplicateCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\ElementTypes::apiCreate( $appData, $duplicateCreateRequest );
check( 'apiCreate rejects an already-existing type', $duplicateCreateRequest['/nino/http/response']['statusCode'] === 409 );

echo "\n";


// --- Dev\Restore ----------------------------------------------------------

echo "Dev\\Restore - reads Backup's output independently, doesn't call into _admin/Admin.php\n";

mkdir( $sandbox. '/_admin', 0777, true );
mkdir( $sandbox. '/_dev', 0777, true );

$appData['/nino/auth/maxtries'] 	= 5;
$appData['/nino/auth/cooldown'] 	= 3600;
$appData['/nino/auth/user'] 			= $appData['/nino/auth/user'] ?? [];
\Nino\Auth::insertUser( $appData, 'admin@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $appData, 'admin@example.com', 'correct horse battery staple' );

$guardOk = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::guard( $appData, $guardOk ); // bootstraps + creates today's backup as a side effect

check( 'Backup::maybeRun (via Admin::guard) writes the restore key into _dev/', is_file( $sandbox. '/_dev/.restore-key.php' ) === true );

// Regression: an install that already had Backup running (dir/key already in
// config.php) before Restore/_dev/.restore-key.php existed must still get the
// _dev/ key copy written on the next admin request - not just on the very
// first-ever bootstrap
unlink( $sandbox. '/_dev/.restore-key.php' );
\Nino\Admin\Admin::guard( $appData, $guardOk );
check( 'Backup::maybeRun re-creates a missing _dev/.restore-key.php on an already-bootstrapped install', is_file( $sandbox. '/_dev/.restore-key.php' ) === true );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Restore::apiList( $appData, $listRequest );
$dates = $listRequest['/nino/http/response']['body']['dates'] ?? [];
check( 'apiList finds today\'s backup without reading config.php\'s own copy of the dir/key', count( $dates ) === 1 && $dates[0] === date( 'Y-m-d' ) );

// Simulate config.php DATA corruption (not a syntax error) - eg. a wrecked user record -
// the scenario Restore exists for. A genuine syntax error is out of scope (see Dev.php docblock).
$beforeCorruption = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$corrupted = $beforeCorruption;
$corrupted['/nino/auth/user'] = [];
\Nino\Filesystem::putFileContent( $appData, '/config.php', $corrupted );
check( 'the simulated corruption actually wiped the user record', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user'] === [] );

$_POST['data'] = json_encode( [ 'date' => $dates[0] ] );
$restoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Restore::apiRestore( $appData, $restoreRequest );
check( 'apiRestore reports success', ( $restoreRequest['/nino/http/response']['body']['ok'] ?? false ) === true );

$afterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the wrecked user record is back after restore', isset( $afterRestore['/nino/auth/user']['admin@example.com'] ) === true );

$backupDir = $sandbox. '/_admin/'. $appData['/nino/backup/dir'];
check( 'a pre-restore safety snapshot of the (corrupted) state was made first', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 1 );

$_POST['data'] = json_encode( [ 'date' => '2020-01-01' ] );
$unknownDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Restore::apiRestore( $appData, $unknownDateRequest );
check( 'restoring a date with no matching backup 404s', $unknownDateRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'date' => 'not-a-date' ] );
$badDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Restore::apiRestore( $appData, $badDateRequest );
check( 'restoring a malformed date is rejected before touching the filesystem', $badDateRequest['/nino/http/response']['statusCode'] === 400 );

echo "\n";


// --- Dev\Restore - newsletter restore merges instead of overwriting -------

echo "Dev\\Restore - newsletter restore merges, doesn't resurrect a removal (Art. 17)\n";

// _mergeNewsletterRestore() is exercised directly (Reflection) rather than
// through a real two-backup apiRestore() round trip: Backup::maybeRun()
// only ever creates one backup per calendar day, so a sandboxed test run
// can't produce an "older backup" and a "current, since-changed state" the
// way a real installation would days apart
$mergeMethod = new ReflectionMethod( '\Nino\Dev\Restore', '_mergeNewsletterRestore' );
$mergeMethod->setAccessible( true );

$mergeRoot 		= sys_get_temp_dir(). '/nino-mergetest-root-'. bin2hex( random_bytes( 8 ) );
$mergeStaging = sys_get_temp_dir(). '/nino-mergetest-staging-'. bin2hex( random_bytes( 8 ) );
mkdir( $mergeRoot. '/data', 0755, true );
mkdir( $mergeStaging. '/data', 0755, true );

// root (current): alice unsubscribed since the backup was taken - gone from
// newsletter.php, recorded (as a sha256, see \Nino\Modules\Newsletter's own
// REMOVED_PATH docblock) in newsletter-removed.php; bob untouched
$aliceHash = hash( 'sha256', 'alice@example.com' );
file_put_contents( $mergeRoot. '/data/newsletter.php', '<?php return [ [ "email" => "bob@example.com", "status" => "subscribed" ] ];' );
file_put_contents( $mergeRoot. '/data/newsletter-removed.php', '<?php return [ '. var_export( $aliceHash, true ). ' ];' );

// staging (the backup being restored): older, still has alice subscribed,
// predates this feature entirely - no removed-file of its own
file_put_contents( $mergeStaging. '/data/newsletter.php', '<?php return [ [ "email" => "alice@example.com", "status" => "subscribed" ], [ "email" => "bob@example.com", "status" => "subscribed" ] ];' );

$mergeMethod->invoke( null, $mergeRoot, $mergeStaging );

$mergedEntries = include $mergeStaging. '/data/newsletter.php';
$mergedRemoved = include $mergeStaging. '/data/newsletter-removed.php';

check( 'a restore does not resurrect an address unsubscribed since the backup was taken', in_array( 'alice@example.com', array_column( $mergedEntries, 'email' ), true ) === false );
check( 'an untouched subscriber survives the restore', in_array( 'bob@example.com', array_column( $mergedEntries, 'email' ), true ) === true );
check( 'the removal record itself is carried into the restored state, not just the filtered entries', in_array( $aliceHash, $mergedRemoved, true ) === true );

\Nino\Filesystem::removeDir( $mergeRoot );
\Nino\Filesystem::removeDir( $mergeStaging );

// A backup that predates this feature (or a project that never enabled the
// module) carries neither newsletter file - must be a no-op, not an error
$mergeRoot2 	= sys_get_temp_dir(). '/nino-mergetest-root2-'. bin2hex( random_bytes( 8 ) );
$mergeStaging2 = sys_get_temp_dir(). '/nino-mergetest-staging2-'. bin2hex( random_bytes( 8 ) );
mkdir( $mergeRoot2, 0755, true );
mkdir( $mergeStaging2, 0755, true );

$mergeMethod->invoke( null, $mergeRoot2, $mergeStaging2 );
check( 'a backup with no newsletter files at all is a no-op, not an error', is_file( $mergeStaging2. '/data/newsletter.php' ) === false );

\Nino\Filesystem::removeDir( $mergeRoot2 );
\Nino\Filesystem::removeDir( $mergeStaging2 );

echo "\n";


// --- Backup/Restore with config.php outside the project root --------------

echo "Backup/Restore - config.php resolves via configPath, not root (NINO_CONFIG_DIR)\n";

$outSandbox 	= sys_get_temp_dir(). '/nino-dev-smoke-outofweb-'. uniqid();
$outConfigDir = $outSandbox. '/secret-config';

mkdir( $outSandbox. '/_admin', 0777, true );
mkdir( $outSandbox. '/_dev', 0777, true );
mkdir( $outConfigDir, 0777, true );

$outAppData = [ './nino/uid' => $outSandbox ];
\Nino\AppData::prepare( $outAppData );
$outAppData['./nino/filesystem/path']			= $outSandbox;
$outAppData['./nino/filesystem/configpath']	= $outConfigDir; // simulates NINO_CONFIG_DIR
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

\Nino\Auth::insertUser( $outAppData, 'admin@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $outAppData, 'admin@example.com', 'correct horse battery staple' );

$outGuard = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::guard( $outAppData, $outGuard ); // bootstraps + creates today's backup as a side effect

$outBackupDir = $outSandbox. '/_admin/'. $outAppData['/nino/backup/dir'];
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
\Nino\Runtime::setSessionValue( $outAppData, './nino/dev/authed', true );

$_POST['data'] = json_encode( [ 'date' => date( 'Y-m-d' ) ] );
$outRestoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Restore::apiRestore( $outAppData, $outRestoreRequest );
check( 'apiRestore succeeds for the out-of-webroot setup', ( $outRestoreRequest['/nino/http/response']['body']['ok'] ?? false ) === true );

$outAfterRestore = \Nino\Filesystem::getFileContent( $outAppData, '/config.php', [] );
check( 'the restored config.php landed back under configPath, with the user record restored', isset( $outAfterRestore['/nino/auth/user']['admin@example.com'] ) === true );
check( 'restore did not leak a stray config.php copy into the webroot root', is_file( $outSandbox. '/config.php' ) === false );

\Nino\Filesystem::removeDir( $outSandbox );

echo "\n";


// --- Dev\Config ---------------------------------------------------------

echo "Dev\\Config - soft-value json editor\n";

/**
 *	Dispatch one action directly against a Dev\* module class
 *
 *	@param		array 		&$appData
 *	@param		string		$class				eg. "\Nino\Dev\Config"
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

[ $status, $body ] = callDev( $appData, \Nino\Dev\Config::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList returns exactly the whitelisted keys', array_keys( $body['values'] ) === [
	'/nino/error/log', '/nino/error/display', '/nino/locales/native', '/nino/locales/available',
	'/nino/html/assets', '/nino/http/routes',
] );
check( 'apiList pretty-prints the current value', $body['values']['/nino/locales/native'] === '"de_DE"' );

// Regression: Dev::init() adds GET/POST /_dev into $appData['/nino/http/routes'] at
// runtime (self-registered, same as Admin::init() does for /_admin - never persisted,
// see Dev::init()'s docblock). apiList() must read config.php's own stored routes, not
// that live-merged value, or saving this key back would bake _dev's own route into it.
$appData['/nino/http/routes']['GET://_dev'] = [ 'uri' => '/_dev', 'body' => 'x' ];
[ $status, $body ] = callDev( $appData, \Nino\Dev\Config::class, 'apiList' );
check( 'apiList does not leak _dev\'s own runtime-injected route into the editable value', strpos( $body['values']['/nino/http/routes'], 'GET://_dev' ) === false );
unset( $appData['/nino/http/routes']['GET://_dev'] );

[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiSave', [ 'key' => '/nino/error/display', 'value' => 'false' ] );
check( 'apiSave accepts a valid bool', $status === 200 );
check( 'the saved value actually lands in appData', $appData['/nino/error/display'] === false );

[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiSave', [ 'key' => '/nino/locales/available', 'value' => '["de_DE","en_US","fr_FR"]' ] );
check( 'apiSave accepts a valid array', $status === 200 );
check( 'the saved array round-trips correctly', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiSave', [ 'key' => '/nino/locales/native', 'value' => '{not valid json' ] );
check( 'apiSave rejects malformed json', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiSave', [ 'key' => '/nino/locales/native', 'value' => '["should be a string, not an array"]' ] );
check( 'apiSave rejects valid json of the wrong shape for this key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiSave', [ 'key' => '/nino/modules', 'value' => '[]' ] );
check( 'apiSave refuses a key outside the whitelist (a "hard" value)', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiSave', [ 'key' => '/nino/html/images', 'value' => '[]' ] );
check( 'apiSave refuses /nino/html/images too (it has its own dedicated editor now)', $status === 400 );

\Nino\Runtime::unsetSessionValue( $appData, './nino/dev/authed' );
[ $status ] = callDev( $appData, \Nino\Dev\Config::class, 'apiList' );
check( 'Config actions require an authed _dev session too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/dev/authed', true );

echo "\n";


// --- Dev\Users ------------------------------------------------------------

echo "Dev\\Users - admin account bootstrap (create/delete) + raw permissions editor\n";

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'password' => 'a-long-enough-password', 'isManager' => true ] );
check( 'apiCreate creates a user', $status === 200 );
check( 'the new user has the manage permission', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage', 'newdev@example.com' ) === true );

// A non-manager account still gets full content access (elements/text/...),
// same as any admin account had before per-module permissions existed - only
// /_admin/users/manage is gated by the isManager checkbox
[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiCreate', [ 'mail' => 'newcontenteditor@example.com', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate creates a non-manager user', $status === 200 );
check( 'a non-manager still has content-module access', \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'newcontenteditor@example.com' ) === true );
check( 'a non-manager does not have the manage permission', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage', 'newcontenteditor@example.com' ) === false );
\Nino\Auth::deleteUser( $appData, 'newcontenteditor@example.com' );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiCreate', [ 'mail' => 'not-an-email', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate rejects an invalid mail', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiCreate', [ 'mail' => 'short@example.com', 'password' => 'short' ] );
check( 'apiCreate rejects a too-short password', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate rejects a mail that already exists', $status === 409 );

[ , $body ] = callDev( $appData, \Nino\Dev\Users::class, 'apiList' );
check( 'the new user shows up in apiList, never a password hash', in_array( 'newdev@example.com', array_column( $body['users'], 'mail' ), true ) === true && isset( $body['users'][0]['pw'] ) === false );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiDelete', [ 'mail' => 'newdev@example.com' ] );
check( 'apiDelete removes the user', $status === 200 );
check( 'the user is actually gone', isset( $appData['/nino/auth/user']['newdev@example.com'] ) === false );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiDelete', [ 'mail' => 'newdev@example.com' ] );
check( 'apiDelete 404s for an already-gone user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiCreate', [ 'mail' => 'permsdev@example.com', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate creates the account used for the permissions editor checks', $status === 200 );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiSetPermissions', [ 'mail' => 'unknown@example.com', 'perms' => [ '/*' ] ] );
check( 'apiSetPermissions 404s for an unknown user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiSetPermissions', [ 'mail' => 'permsdev@example.com', 'perms' => 'not-an-array' ] );
check( 'apiSetPermissions rejects a non-array perms value', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Users::class, 'apiSetPermissions', [ 'mail' => 'permsdev@example.com', 'perms' => [ '/_admin/elements/manage', 42 ] ] );
check( 'apiSetPermissions rejects a perms array with a non-string entry', $status === 400 );

// No whitelist here on purpose - unlike _admin's own apiSetPermissions() this is the
// developer bypass, so even a permission string _admin doesn't know about persists
[ $status, $body ] = callDev( $appData, \Nino\Dev\Users::class, 'apiSetPermissions', [ 'mail' => 'permsdev@example.com', 'perms' => [ '/_admin/elements/manage', '/some/future/module' ] ] );
check( 'apiSetPermissions saves an arbitrary, unwhitelisted perms array', $status === 200 && $body['perms'] === [ '/_admin/elements/manage', '/some/future/module' ] );
check( 'the permission actually took effect', \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'permsdev@example.com' ) === true );
check( 'a permission not granted stays denied', \Nino\Auth::checkPermission( $appData, '/_admin/text/manage', 'permsdev@example.com' ) === false );

[ , $body ] = callDev( $appData, \Nino\Dev\Users::class, 'apiList' );
$listed = $body['users'][ array_search( 'permsdev@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( 'apiList exposes the raw perms array too, not just the derived isManager flag', $listed['perms'] === [ '/_admin/elements/manage', '/some/future/module' ] );

\Nino\Auth::deleteUser( $appData, 'permsdev@example.com' );

echo "\n";


// --- Dev\Images -----------------------------------------------------------

echo "Dev\\Images - image slot definitions (label/width/height only)\n";

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'Hero', 'width' => '1200', 'height' => '600' ] );
check( 'apiCreate creates a slot', $status === 200 );
check( 'the slot starts with no filename', array_key_exists( 'filename', $appData['/nino/html/images']['/home/hero'] ) === true && $appData['/nino/html/images']['/home/hero']['filename'] === null );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiCreate', [ 'uri' => 'not valid', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects an invalid uri', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects a uri that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Images::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the new slot', in_array( '/home/hero', array_column( $body['slots'], 'uri' ), true ) === true );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu', 'width' => '800', 'height' => '400' ] );
check( 'apiSave edits an existing slot', $status === 200 );
check( 'the slot metadata actually changed', $appData['/nino/html/images']['/home/hero']['label'] === 'Hero neu' && $appData['/nino/html/images']['/home/hero']['width'] === 800 );

$appData['/nino/html/images']['/home/hero']['filename'] = 'elements/home/hero.800x400.jpg';
[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave never touches an existing filename', $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg' );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiSave', [ 'uri' => '/does/not/exist', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiSave 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiDelete', [ 'uri' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'the slot is gone from appData', isset( $appData['/nino/html/images']['/home/hero'] ) === false );
$imagesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [];
check( 'the slot is gone from config.php too', isset( $imagesAfterDelete['/home/hero'] ) === false );

echo "\n";


// --- Dev\Text ---------------------------------------------------------------

echo "Dev\\Text - text key schema (existence, global/per-locale, blacklist)\n";

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiCreate', [ 'key' => '/home/welcome/subtitle', 'global' => false, 'value' => 'Start' ] );
check( 'apiCreate creates a per-locale key', $status === 200 );

$deDE = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUS = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the initial value is written into every available locale', $deDE['[[/home/welcome/subtitle]]'] === 'Start' && $enUS['[[/home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiCreate', [ 'key' => '/company/tagline', 'global' => true, 'value' => 'Immer' ] );
check( 'apiCreate creates a global key', $status === 200 );

$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the initial value is written into global.php, not any locale file', $global['[[/company/tagline]]'] === 'Immer' );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiCreate', [ 'key' => 'not-a-valid-key', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects an invalid key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiCreate', [ 'key' => '/company/tagline', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects a key that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Text::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
$subtitleEntry = null;
foreach( $body['keys'] as $entry ) if( $entry['key'] === '/home/welcome/subtitle' ) $subtitleEntry = $entry;
check( 'apiList finds the new per-locale key, not blacklisted by default', $subtitleEntry !== null && $subtitleEntry['global'] === false && $subtitleEntry['blacklisted'] === false );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSave', [ 'key' => '/home/welcome/subtitle', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave converts a per-locale key to global', $status === 200 );

$deDEAfter = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'converting to global removes it from every locale file', isset( $deDEAfter['[[/home/welcome/subtitle]]'] ) === false );
check( 'converting to global migrates the value instead of discarding it', $globalAfter['[[/home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSave', [ 'key' => '/home/welcome/subtitle', 'global' => false, 'blacklisted' => true ] );
check( 'apiSave converts back to per-locale and blacklists it in the same call', $status === 200 );

$deDEAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklist = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'converting back to per-locale migrates the value into every locale', $deDEAfter2['[[/home/welcome/subtitle]]'] === 'Start' );
check( 'converting back to per-locale removes it from global.php', isset( $globalAfter2['[[/home/welcome/subtitle]]'] ) === false );
check( 'the key is now blacklisted', in_array( '/home/welcome/subtitle', $blacklist, true ) === true );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSave', [ 'key' => '/does/not/exist', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave 404s for an unknown key', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
	[ 'key' => '/home/welcome/subtitle', 'locale' => 'de_DE', 'value' => 'Neuer Start' ],
] ] );
check( 'apiSaveBatch succeeds', $status === 200 );
check( 'apiSaveBatch saves a global value', $body['results']['/company/tagline']['ok'] === true );
check( 'apiSaveBatch saves a blacklisted key\'s value too - blacklist only hides it from _admin', $body['results']['/home/welcome/subtitle']['ok'] === true );

$globalAfterBatch = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$deDEAfterBatch 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the global value actually landed in global.php', $globalAfterBatch['[[/company/tagline]]'] === 'Immer und ewig' );
check( 'the per-locale value actually landed in the right locale file', $deDEAfterBatch['[[/home/welcome/subtitle]]'] === 'Neuer Start' );

// html is auto-detected from a key's *current* value (see _entries()) - create one
// that already holds a whitelisted tag, so this save actually exercises sanitizeHtml()
// rather than the plain strip_tags() path a fresh/plain-text key would get
[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiCreate', [ 'key' => '/company/note', 'global' => true, 'value' => '<strong>Wichtig</strong>' ] );
check( 'apiCreate creates the html-flagged fixture key', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/note', 'locale' => '*', 'value' => '<script>alert(1)</script><strong>Wichtig</strong><em>auch</em>' ],
] ] );
check( 'apiSaveBatch sanitizes html the same way _admin\'s Text does', $body['results']['/company/note']['value'] === '<strong>Wichtig</strong><em>auch</em>' );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/does/not/exist', 'locale' => '*', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch reports an unknown key without failing the whole request', $status === 200 && $body['results']['/does/not/exist']['ok'] === false );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/home/welcome/subtitle', 'locale' => 'xx_XX', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch rejects an invalid locale for a per-locale key', $body['results']['/home/welcome/subtitle']['ok'] === false );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiRename', [ 'key' => '/company/tagline', 'newKey' => '/company/motto' ] );
check( 'apiRename succeeds for a global key', $status === 200 );

$globalAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the old key is gone from global.php', isset( $globalAfterRename['[[/company/tagline]]'] ) === false );
check( 'the value moved to the new key', $globalAfterRename['[[/company/motto]]'] === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => 'not-a-valid-key' ] );
check( 'apiRename rejects an invalid new key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => '/company/note' ] );
check( 'apiRename rejects a new key that already exists', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiRename', [ 'key' => '/does/not/exist', 'newKey' => '/also/new' ] );
check( 'apiRename 404s for an unknown key', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiDelete', [ 'key' => '/company/note' ] );
check( 'apiDelete succeeds for a global key', $status === 200 );

$globalAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the deleted global key is gone', isset( $globalAfterDelete['[[/company/note]]'] ) === false );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiDelete', [ 'key' => '/home/welcome/subtitle' ] );
check( 'apiDelete succeeds for a blacklisted per-locale key', $status === 200 );

$deDEAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUSAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$blacklistAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the deleted per-locale key is gone from every locale file', isset( $deDEAfterDelete['[[/home/welcome/subtitle]]'] ) === false && isset( $enUSAfterDelete['[[/home/welcome/subtitle]]'] ) === false );
check( 'the deleted key is also gone from the blacklist', in_array( '/home/welcome/subtitle', $blacklistAfterDelete, true ) === false );

[ $status ] = callDev( $appData, \Nino\Dev\Text::class, 'apiDelete', [ 'key' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown key', $status === 404 );

echo "\n";


// --- Text::apiScan / Images::apiScan: template scanners ---------------------

echo "Text::apiScan - missing [[/key]] placeholders in templates/*.tpl\n";

mkdir( $sandbox. '/templates', 0777, true );
file_put_contents( $sandbox. '/templates/scan-fixture.tpl', '<p>[[/page-scan-test/heading]]</p><p>[[/page-scan-test/heading]]</p><p>[[/company/name]]</p>' );
// A nested, dynamically-constructed fill (same shape as html-header.tpl's real
// [[/webpage[[/nino/http/response/uri]]/title]]) - only the inner, always-
// registered kernel fill should ever be visible to a static regex scan
file_put_contents( $sandbox. '/templates/scan-fixture-2.tpl', '<title>[[/webpage[[/nino/http/response/uri]]/title]]</title>' );

// /company/name is genuinely defined (unlike /page-scan-test/heading) - proves
// a real key is correctly excluded, not just absent from the fixture by accident
$globalFixture = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$globalFixture['[[/company/name]]'] = 'Acme';
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $globalFixture );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Text::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );

$missingKeys = array_column( $body['missing'], 'key' );
check( 'a genuinely undefined key is reported', in_array( '/page-scan-test/heading', $missingKeys, true ) === true );
check( 'an undefined key found twice in the same file is only reported once', count( array_filter( $missingKeys, fn( $k ) => $k === '/page-scan-test/heading' ) ) === 1 );
check( 'an already-defined key is not reported', in_array( '/company/name', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/http/response/uri fill is never reported, despite appearing inside a nested [[...]] construct', in_array( '/nino/http/response/uri', $missingKeys, true ) === false );

unlink( $sandbox. '/templates/scan-fixture.tpl' );
unlink( $sandbox. '/templates/scan-fixture-2.tpl' );

echo "\n";


echo "Images::apiScan - hardcoded <img> tags in templates/*.tpl, not backed by a slot\n";

mkdir( $sandbox. '/images', 0777, true );

// A real, tiny (3x2px) PNG so getimagesize() has something genuine to probe
$probeImg = imagecreatetruecolor( 3, 2 );
ob_start();
imagepng( $probeImg );
file_put_contents( $sandbox. '/images/probe.png', ob_get_clean() );
imagedestroy( $probeImg );

file_put_contents( $sandbox. '/templates/scan-fixture-img.tpl', '<img src="[[/nino/dir]]/images/probe.png"><img src="[[/nino/dir]]/images/probe.png"><img src="https://example.com/x.jpg"><img src="data:image/svg+xml,%3Csvg/%3E">' );

[ $status, $body ] = callDev( $appData, \Nino\Dev\Images::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );
check( 'exactly one missing image is reported (deduped, external/data-uri skipped)', count( $body['missing'] ) === 1 );
check( 'the reported filename is the local one, relative to /images/', ( $body['missing'][0]['filename'] ?? null ) === 'probe.png' );
check( 'width/height were probed from the real file since the <img> tag had none', ( $body['missing'][0]['width'] ?? null ) === 3 && ( $body['missing'][0]['height'] ?? null ) === 2 );
check( 'a suggested uri was derived from the filename', ( $body['missing'][0]['suggestedUri'] ?? null ) === '/probe' );

[ $status ] = callDev( $appData, \Nino\Dev\Images::class, 'apiCreate', [ 'uri' => '/probe', 'label' => 'Probe', 'width' => '3', 'height' => '2' ] );
check( 'the suggested slot can actually be created', $status === 200 );

// Once a slot's filename actually tracks it (the manual step _admin's upload
// flow would do next), the same <img> must disappear from the scan
$appData['/nino/html/images']['/probe']['filename'] = 'probe.png';
[ $status, $body ] = callDev( $appData, \Nino\Dev\Images::class, 'apiScan' );
check( 'once a slot tracks the file, it no longer shows up as missing', count( $body['missing'] ) === 0 );

unlink( $sandbox. '/templates/scan-fixture-img.tpl' );

echo "\n";


// --- Dashboard::apiSummary - aggregates Text/Images::missingCount() ---------

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callDev( $appData, \Nino\Dev\Dashboard::class, 'apiSummary' );
check( 'apiSummary succeeds', $status === 200 );
check( 'missingText is 0 - both scan fixtures above were already cleaned up', $body['missingText'] === 0 );
check( 'missingImages is 0 - the scan fixture above was already cleaned up', $body['missingImages'] === 0 );

// A fresh, self-contained fixture (one undefined key, one untracked image) to
// prove the two counts actually reflect Text/Images::_scanMissing(), not just
// always 0 - a new filename, since probe.png is already tracked by the slot
// created in the Images::apiScan section above
file_put_contents( $sandbox. '/templates/dashboard-fixture.tpl', '<p>[[/dashboard-scan-test/heading]]</p><img src="[[/nino/dir]]/images/probe2.png">' );

[ , $body ] = callDev( $appData, \Nino\Dev\Dashboard::class, 'apiSummary' );
check( 'missingText picks up the fresh undefined key', $body['missingText'] === 1 );
check( 'missingImages picks up the fresh untracked image (a new filename, not tracked by any slot)', $body['missingImages'] === 1 );

unlink( $sandbox. '/templates/dashboard-fixture.tpl' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/dev/authed' );
[ $status ] = callDev( $appData, \Nino\Dev\Dashboard::class, 'apiSummary' );
check( 'apiSummary requires an authed dev session too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/dev/authed', true );

echo "\n";


// --- Final: unauthed guard sanity check across every module -------------

echo "Every module rejects an unauthed request\n";

\Nino\Runtime::unsetSessionValue( $appData, './nino/dev/authed' );
$unauthedListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Dev\Restore::apiList( $appData, $unauthedListRequest );
check( 'apiList requires an authed _dev session, same as every other module', $unauthedListRequest['/nino/http/response']['statusCode'] === 401 );

echo "\n";

echo "$checks checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
