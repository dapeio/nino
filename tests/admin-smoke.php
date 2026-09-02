<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-smoke.php		Dependency-free smoke test for the developer tooling
 *											(_admin/Admin.php) - the session gate and the element-type
 *											editor in particular, since a mistake there could either
 *											let a save reach a type file it shouldn't, or - worse -
 *											clobber a type's real content ('*'/locale buckets) while
 *											only meaning to touch its model. Runs against an isolated
 *											sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/dev-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_editor/Editor.php'; // only for Backup - Restore reads its output, doesn't call into it

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
$appData['./nino/filesystem/privatepath'] = $sandbox. '/private';
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

echo "Sandbox: $sandbox\n\n";


// --- Admin::isAuthed / guard / handlePost (login/logout) ------------------

echo "Admin::isAuthed / guard / handlePost (login/logout)\n";

check( 'isAuthed defaults to false', \Nino\Admin\Admin::isAuthed( $appData ) === false );

$guardRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'guard rejects an unauthed request', \Nino\Admin\Admin::guard( $appData, $guardRequest ) === false );
check( 'guard sets a 401', $guardRequest['/nino/http/response']['statusCode'] === 401 );

$_POST['action']	= 'dev/login';
$_POST['data']		= json_encode( [ 'password' => 'definitely-the-wrong-password' ] );
$loginRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $loginRequest );
check( 'a wrong password is rejected', $loginRequest['/nino/http/response']['statusCode'] === 401 );
check( 'a rejected login never opens the session gate', \Nino\Admin\Admin::isAuthed( $appData ) === false );

// 4 more wrong attempts (5 total) should trip the lockout
for( $i = 0; $i < 4; $i++ ) {
	$attemptRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\Admin::handlePost( $appData, $attemptRequest );
}
$lockedOutRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $lockedOutRequest );
check( 'the 6th attempt is locked out (429), not just rejected as wrong', $lockedOutRequest['/nino/http/response']['statusCode'] === 429 );

$_POST['data'] = json_encode( [ 'password' => 'definitely-the-wrong-password' ] );
$duringCooldownRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $duringCooldownRequest );
check( 'the lockout applies regardless of the password tried next', $duringCooldownRequest['/nino/http/response']['statusCode'] === 429 );

// Reset the lockout state for the rest of the file - a fresh cooldown window shouldn't leak into later checks
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );

$_POST['action'] = 'devtypes/list';
$_POST['data']	 = '{}';
$unauthedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $unauthedRequest );
check( 'a module action is rejected while unauthed', $unauthedRequest['/nino/http/response']['statusCode'] === 401 );

// --- the password gate itself -------------------------------------------
//
// The hash lives under the private directory, not in _admin/Admin.php and
// not in config.php: a tool folder carrying state cannot be replaced on an
// update, and a Restore rewrites config.php - the credential that authorises
// restoring must survive both (see Admin::passwordHash())

$login = static function( array &$appData, string $password ): int {
	$_POST['action'] = 'dev/login';
	$_POST['data'] 	 = json_encode( [ 'password' => $password ] );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\Admin::handlePost( $appData, $request );
	return $request['/nino/http/response']['statusCode'];
};

check( 'a fresh checkout has no password at all', \Nino\Admin\Admin::passwordHash( $appData ) === null );
check( 'so every login fails rather than matching the shipped placeholder', $login( $appData, '' ) === 401 );
check( '...including one against the placeholder hash itself', \Nino\Admin\Admin::isAuthed( $appData ) === false );

check( 'storing a hash succeeds', \Nino\Admin\Admin::writePasswordHash( $appData, password_hash( 'the real password', PASSWORD_DEFAULT ) ) === true );
check( 'a wrong password is still rejected', $login( $appData, 'not the password' ) === 401 );
check( 'the right one opens the gate', $login( $appData, 'the real password' ) === 200 && \Nino\Admin\Admin::isAuthed( $appData ) === true );

// The lockout counter runs whether or not a password exists - answering an
// unknown-password project instantly would tell an unauthenticated caller
// which state this installation is in
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => time() + 60 ] );
check( 'a locked-out login reports 429, not 401', $login( $appData, 'the real password' ) === 429 );
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );

\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );
check( 'isAuthed reflects the session flag', \Nino\Admin\Admin::isAuthed( $appData ) === true );

$_POST['action'] = 'dev/logout';
$_POST['data']	 = '{}';
$logoutRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $logoutRequest );
check( 'logout clears the session flag', \Nino\Admin\Admin::isAuthed( $appData ) === false );

// Re-authenticate for every check below
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

echo "\n";


// --- Dev\ElementTypes::apiList / apiGet / apiSave / apiCreate -----------

echo "Dev\\ElementTypes::apiList / apiGet / apiSave / apiCreate\n";

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiList( $appData, $listRequest );
$types = $listRequest['/nino/http/response']['body']['types'] ?? [];
check( 'apiList finds the sandbox type', count( $types ) === 1 && $types[0]['uri'] === 'testtype' );
check( 'apiList reports the real title', $types[0]['title'] === 'Test Type' );
check( 'apiList reports the real field count', $types[0]['fieldCount'] === 1 );
check( 'apiList exposes the allowed field types', in_array( 'image', $listRequest['/nino/http/response']['body']['fieldTypes'] ?? [], true ) === true );

$_POST['data'] = json_encode( [ 'uri' => 'testtype' ] );
$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiGet( $appData, $getRequest );
$got = $getRequest['/nino/http/response']['body'] ?? [];
check( 'apiGet returns the real title', $got['title'] === 'Test Type' );
check( 'apiGet returns the real model', ( $got['model']['name']['type'] ?? null ) === 'string' );

$_POST['data'] = json_encode( [ 'uri' => 'not-a-real-type' ] );
$getMissingRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiGet( $appData, $getMissingRequest );
check( 'apiGet 404s for an unknown type', $getMissingRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'uri' => '../escape' ] );
$getInvalidRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiGet( $appData, $getInvalidRequest );
check( 'apiGet rejects a non-slug type uri before ever touching the filesystem', $getInvalidRequest['/nino/http/response']['statusCode'] === 400 );

// Save: rename the field, add an image field and a suffixed/maxlength'd one,
// and slip in a field with an invalid type - the invalid one must be
// silently dropped, not crash or persist
$_POST['data'] = json_encode( [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80 ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'suffix' => 'ignored on image', 'required' => true ],
		'price' 	=> [ 'type' => 'double', 'suffix' => '€' ],
		'active' 	=> [ 'type' => 'boolean', 'suffix' => 'ignored on boolean' ],
		'bogus' 	=> [ 'type' => 'not-a-real-type' ],
	],
] );
$saveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiSave( $appData, $saveRequest );
check( 'apiSave succeeds', $saveRequest['/nino/http/response']['statusCode'] === 200 );

$afterSave = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'apiSave updates the title', $afterSave['title'] === 'Test Type Renamed' );
check( 'apiSave keeps the valid field', ( $afterSave['model']['name']['required'] ?? null ) === true );
check( 'apiSave sets a string field\'s maxlength', ( $afterSave['model']['name']['maxlength'] ?? null ) === 80 );
check( 'apiSave adds the new field', ( $afterSave['model']['photo']['width'] ?? null ) === 40 );
check( 'apiSave drops suffix on an image field', isset( $afterSave['model']['photo']['suffix'] ) === false );
// An image's file is uploaded only after the element exists, so a required
// image would make the type impossible to add an element to - the flag is
// dropped whatever was posted, and only for image fields
check( 'apiSave drops "required" on an image field', isset( $afterSave['model']['photo']['required'] ) === false );
check( 'apiSave sets a suffix on a non-boolean/image field', $afterSave['model']['price']['suffix'] === '€' );
check( 'apiSave drops suffix on a boolean field', isset( $afterSave['model']['active']['suffix'] ) === false );
check( 'apiSave silently drops a field with an unknown type', isset( $afterSave['model']['bogus'] ) === false );
check( 'apiSave never touches the "*" bucket', $afterSave['*'] === [ '*' => [] ] );
check( 'apiSave never touches real locale content', ( $afterSave['de_DE']['item1']['name'] ?? null ) === 'Hallo' );

// The order fields are posted in is the order they are written in, and the
// order every element form then renders them in - that is exactly what the
// editor's new ↑/↓ buttons change (see assets/elementtypes.js's _move())
check( 'apiSave keeps the posted field order', array_keys( $afterSave['model'] ) === [ 'name', 'photo', 'price', 'active' ] );

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
\Nino\Admin\ElementTypes::apiSave( $appData, $reorderRequest );
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
\Nino\Admin\ElementTypes::apiSave( $appData, $globalizeRequest );
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
\Nino\Admin\ElementTypes::apiSave( $appData, $localizeRequest );
check( 'apiSave succeeds when switching a field back to per-locale', $localizeRequest['/nino/http/response']['statusCode'] === 200 );

$afterLocalize = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'switching back to per-locale copies the value into every locale', $afterLocalize['de_DE']['item1']['name'] === 'Hallo' && $afterLocalize['en_US']['item1']['name'] === 'Hallo' );
check( 'switching back to per-locale removes the stale value from "*"', isset( $afterLocalize['*']['item1']['name'] ) === false );

$_POST['data'] = json_encode( [ 'uri' => 'testtype', 'title' => 'x', 'model' => [] ] );
foreach( [ '../escape', 'Has Uppercase', '1startswithdigit', '' ] as $badUri ) {
	$_POST['data'] = json_encode( [ 'uri' => $badUri, 'title' => 'x', 'model' => [] ] );
	$badSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\ElementTypes::apiSave( $appData, $badSaveRequest );
	check( "apiSave rejects an invalid type uri ('$badUri')", $badSaveRequest['/nino/http/response']['statusCode'] === 400 );
}

$_POST['data'] = json_encode( [ 'uri' => 'brandnewtype', 'title' => 'Brand New', 'model' => [ 'headline' => [ 'type' => 'string' ] ] ] );
$createRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiCreate( $appData, $createRequest );
check( 'apiCreate succeeds', $createRequest['/nino/http/response']['statusCode'] === 200 );

$created = \Nino\Filesystem::getFileContent( $appData, '/elements/brandnewtype.php', false );
check( 'apiCreate writes the title', $created['title'] === 'Brand New' );
check( 'apiCreate writes the model', ( $created['model']['headline']['type'] ?? null ) === 'string' );
check( 'apiCreate starts with an empty shell, no fabricated content', $created['*'] === [ '*' => [] ] );

$duplicateCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiCreate( $appData, $duplicateCreateRequest );
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
\Nino\Admin\ElementTypes::apiCreate( $appData, $danglingCreateRequest );
check( 'apiCreate refuses a reference to a type that does not exist', $danglingCreateRequest['/nino/http/response']['statusCode'] === 400 );
check( '...and names the field and the missing type, so it can be fixed', str_contains( (string) ( $danglingCreateRequest['/nino/http/response']['body']['error'] ?? '' ), 'author' )
	&& str_contains( (string) ( $danglingCreateRequest['/nino/http/response']['body']['error'] ?? '' ), 'nonexistenttype' ) );
check( '...and writes no half-valid type file', \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', '' ) === '' );

$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '' ],
] ] );
$emptyRefCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiCreate( $appData, $emptyRefCreateRequest );
check( 'apiCreate refuses a reference with no type at all', $emptyRefCreateRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'suffix' => 'ignored', 'options' => [ 'a', 'b' ], 'required' => true, 'locale' => true ],
] ] );
$refCreateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\ElementTypes::apiCreate( $appData, $refCreateRequest );
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
\Nino\Admin\ElementTypes::apiSave( $appData, $danglingSaveRequest );
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
\Nino\Admin\ElementTypes::apiSave( $appData, $multiSaveRequest );
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
\Nino\Admin\ElementTypes::apiSave( $appData, $strayMultiRequest );
check( 'a cap on a field that is not a reference is dropped',
	isset( \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false )['model']['headline']['multiple'] ) === false );

check( '"element" is offered as a field type by apiList', in_array( 'element', \Nino\Admin\ElementTypes::FIELD_TYPES, true ) === true );

echo "\n";


// --- Dev\Restore ----------------------------------------------------------

echo "Dev\\Restore - reads Backup's output independently, doesn't call into _editor/Editor.php\n";

mkdir( $sandbox. '/_editor', 0777, true );
mkdir( $sandbox. '/_admin', 0777, true );

$appData['/nino/auth/maxtries'] 	= 5;
$appData['/nino/auth/cooldown'] 	= 3600;
$appData['/nino/auth/user'] 			= $appData['/nino/auth/user'] ?? [];
\Nino\Auth::insertUser( $appData, 'admin@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $appData, 'admin@example.com', 'correct horse battery staple' );

$guardOk = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Editor\Editor::guard( $appData, $guardOk ); // bootstraps + creates today's backup as a side effect

check( 'Backup::maybeRun (via Editor::guard) writes the archive key into the private directory', is_file( $sandbox. '/private/.auth/backup-key.php' ) === true );

check( 'the archives land under the private directory, not in a tool folder', is_dir( $sandbox. '/private/.backups' ) === true );
check( 'no random backup directory is generated any more', isset( $appData['/nino/backup/dir'] ) === false );

// Regression: an install that already had Backup running (key already in
// config.php) before this out-of-config copy existed must still get one
// written on the next admin request - not just on the very first bootstrap
unlink( $sandbox. '/private/.auth/backup-key.php' );
\Nino\Editor\Editor::guard( $appData, $guardOk );
check( 'Backup::maybeRun re-creates a missing key copy on an already-bootstrapped install', is_file( $sandbox. '/private/.auth/backup-key.php' ) === true );

// Restore has to work with /_editor deleted - that is the whole point of it
// reading Backup's output rather than calling into it, and this file loads
// Editor.php for its own fixtures, so only a subprocess can prove it. A
// plain \Nino\Editor\Backup:: reference in Restore would pass every check
// above and still 500 on the one install that actually needs restoring
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
$appData["./nino/filesystem/privatepath"] = '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/publicpath"]	= '. var_export( $sandbox. '/public', true ). ';
$appData["/nino/dir"] = "";
\Nino\AppData::init( $appData );
echo json_encode( [
	"editorLoaded"	=> class_exists( "\\Nino\\Editor\\Backup", false ),
	"dates"					=> \Nino\Admin\Restore::dates( $appData ),
] );
' );

$standalone = json_decode( (string) shell_exec( 'php '. escapeshellarg( $standaloneDriver ). ' 2>/dev/null' ), true ) ?? [];

check( 'Restore runs without _editor/Editor.php loaded at all', ( $standalone['editorLoaded'] ?? true ) === false );
check( '...and still finds the archives from there', in_array( date( 'Y-m-d' ), $standalone['dates'] ?? [], true ) === true );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Restore::apiList( $appData, $listRequest );
$dates = $listRequest['/nino/http/response']['body']['dates'] ?? [];
check( 'apiList finds today\'s backup without reading config.php\'s own copy of the dir/key', count( $dates ) === 1 && $dates[0] === date( 'Y-m-d' ) );

// Simulate config.php DATA corruption (not a syntax error) - eg. a wrecked user record -
// the scenario Restore exists for. A genuine syntax error is out of scope (see Admin.php docblock).
$beforeCorruption = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$corrupted = $beforeCorruption;
$corrupted['/nino/auth/user'] = [];
\Nino\Filesystem::putFileContent( $appData, '/config.php', $corrupted );
check( 'the simulated corruption actually wiped the user record', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user'] === [] );

$_POST['data'] = json_encode( [ 'date' => $dates[0] ] );
$restoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Restore::apiRestore( $appData, $restoreRequest );
check( 'apiRestore reports success', ( $restoreRequest['/nino/http/response']['body']['ok'] ?? false ) === true );

$afterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the wrecked user record is back after restore', isset( $afterRestore['/nino/auth/user']['admin@example.com'] ) === true );

$backupDir = $sandbox. '/private/.backups';
check( 'a pre-restore safety snapshot of the (corrupted) state was made first', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 1 );

$_POST['data'] = json_encode( [ 'date' => '2020-01-01' ] );
$unknownDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Restore::apiRestore( $appData, $unknownDateRequest );
check( 'restoring a date with no matching backup 404s', $unknownDateRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'date' => 'not-a-date' ] );
$badDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Restore::apiRestore( $appData, $badDateRequest );
check( 'restoring a malformed date is rejected before touching the filesystem', $badDateRequest['/nino/http/response']['statusCode'] === 400 );

echo "\n";


// --- Dev\Restore - newsletter restore merges instead of overwriting -------

echo "Dev\\Restore - newsletter restore merges, doesn't resurrect a removal (Art. 17)\n";

// _mergeNewsletterRestore() is exercised directly (Reflection) rather than
// through a real two-backup apiRestore() round trip: Backup::maybeRun()
// only ever creates one backup per calendar day, so a sandboxed test run
// can't produce an "older backup" and a "current, since-changed state" the
// way a real installation would days apart
$mergeMethod = new ReflectionMethod( '\Nino\Admin\Restore', '_mergeNewsletterRestore' );
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

// staging (the backup being restored): taken before Alice unsubscribed, so it
// still has her subscription and no removal record of its own
file_put_contents( $mergeStaging. '/data/newsletter.php', '<?php return [ [ "email" => "alice@example.com", "status" => "subscribed" ], [ "email" => "bob@example.com", "status" => "subscribed" ] ];' );

$mergeMethod->invoke( null, $mergeRoot. '/data', $mergeStaging );

$mergedEntries = include $mergeStaging. '/data/newsletter.php';
$mergedRemoved = include $mergeStaging. '/data/newsletter-removed.php';

check( 'a restore does not resurrect an address unsubscribed since the backup was taken', in_array( 'alice@example.com', array_column( $mergedEntries, 'email' ), true ) === false );
check( 'an untouched subscriber survives the restore', in_array( 'bob@example.com', array_column( $mergedEntries, 'email' ), true ) === true );
check( 'the removal record itself is carried into the restored state, not just the filtered entries', in_array( $aliceHash, $mergedRemoved, true ) === true );

\Nino\Filesystem::removeDir( $mergeRoot );
\Nino\Filesystem::removeDir( $mergeStaging );

// A project without the Newsletter module carries neither file. Merging that
// state must be a no-op, not an error.
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

mkdir( $outSandbox. '/_editor', 0777, true );
mkdir( $outSandbox. '/_admin', 0777, true );
mkdir( $outConfigDir, 0777, true );

$outAppData = [ './nino/uid' => $outSandbox ];
\Nino\AppData::prepare( $outAppData );
$outAppData['./nino/filesystem/path']			= $outSandbox;
$outAppData['./nino/filesystem/configpath']	= $outConfigDir; // simulates NINO_CONFIG_DIR
$outAppData['./nino/filesystem/contentpath']	= $outSandbox. '/private';
$outAppData['./nino/filesystem/privatepath']	= $outSandbox. '/private';
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

\Nino\Auth::insertUser( $outAppData, 'admin@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $outAppData, 'admin@example.com', 'correct horse battery staple' );

$outGuard = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Editor\Editor::guard( $outAppData, $outGuard ); // bootstraps + creates today's backup as a side effect

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
\Nino\Admin\Restore::apiRestore( $outAppData, $outRestoreRequest );
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
 *	@param		string		$class				eg. "\Nino\Admin\Config"
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

[ $status, $body ] = callDev( $appData, \Nino\Admin\Config::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList returns the field schema in render order', array_column( $body['fields'], 'key' ) === [
	'/nino/error/log', '/nino/error/display', '/nino/session/force-secure-cookie',
	'/nino/auth/maxtries', '/nino/auth/cooldown',
	'/nino/editor/backups', '/nino/editor/logs',
	'/nino/cache/status', '/nino/cache/ttl', '/nino/cache/blacklist',
	'/nino/locales/available', '/nino/locales/native',
] );
check( 'every field carries the type its editor renders from', array_column( $body['fields'], 'type' ) === [
	'bool', 'bool', 'bool', 'int', 'int', 'bool', 'bool', 'bool', 'int', 'lines', 'locales', 'native',
] );
check( 'apiList returns the group headings', array_keys( $body['groups'] ) === [ 'diagnostics', 'auth', 'editor', 'cache', 'locales' ] );
check( 'every field belongs to a declared group', array_diff( array_unique( array_column( $body['fields'], 'group' ) ), array_keys( $body['groups'] ) ) === [] );

$byKey = array_column( $body['fields'], null, 'key' );
check( 'a stored value comes back typed, not as json text', $byKey['/nino/error/display']['value'] === true );
check( 'an int comes back as an int', $byKey['/nino/auth/cooldown']['value'] === 3600 );
check( 'an int field carries the bounds its editor and apiSave share', $byKey['/nino/auth/maxtries']['min'] === 1 && $byKey['/nino/auth/maxtries']['max'] === 100 );

// The three keys this panel deliberately stopped editing: routes and navs have
// real editors of their own (Pages/Navigations) and assets is a build concern
// whose order a json textarea shows nobody. A second, unvalidated way to write
// the same data is a way to corrupt it.
foreach( [ '/nino/http/routes', '/nino/html/navs', '/nino/html/assets' ] as $goneKey )
	check( "$goneKey is no longer part of Config", isset( $byKey[$goneKey] ) === false );

// A missing key must report the same default the runtime itself applies, or the
// form shows a value the site is not actually running with
check( 'a key absent from config.php reports the runtime default', $byKey['/nino/editor/backups']['value'] === true );

// The locale inventory is the union of what config.php lists and what has a
// text file on disk - a translated language that is merely switched off should
// be one checkbox away from being back on, and a configured language without a
// text file renders every per-locale fill unresolved
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/a]]' => 'a', '[[/b]]' => 'b' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/fr_FR.php', [ '[[/a]]' => 'a' ] );
[ , $body ] = callDev( $appData, \Nino\Admin\Config::class, 'apiList' );
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

[ $status, $body ] = callDev( $appData, \Nino\Admin\Config::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale creates a text file for a new language', $status === 200 && ( $body['created'] ?? null ) === true );
check( '...copying the key count of the native language', ( $body['keys'] ?? 0 ) === 2 && ( $body['from'] ?? '' ) === 'de_DE' );

$skeleton = \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', false );
check( '...the file really is on disk', is_array( $skeleton ) === true );
check( '...with exactly the native language\'s keys, in its order', array_keys( $skeleton ) === array_keys( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ) ) );
check( '...and every value empty, so an untranslated key reads as untranslated', array_values( array_unique( $skeleton ) ) === [ '' ] );

// The new language is a translation skeleton, not yet a language of the site -
// activating it is the form's Save, together with the native language it has
// to agree with
$afterAdd = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'apiAddLocale does not activate the language on its own', in_array( 'it_IT', $afterAdd['/nino/locales/available'] ?? [], true ) === false );

[ , $body ] = callDev( $appData, \Nino\Admin\Config::class, 'apiList' );
$inventoryAfterAdd = array_column( $body['locales'], null, 'code' );
check( '...but the inventory now offers it as a translated, inactive language', ( $inventoryAfterAdd['it_IT']['hasText'] ?? null ) === true && ( $inventoryAfterAdd['it_IT']['active'] ?? null ) === false );

// Reachable from a button, so it must never be one click away from emptying a
// finished translation
\Nino\Filesystem::putFileContent( $appData, '/text/it_IT.php', [ '[[/a]]' => 'tradotto', '[[/b]]' => 'anche' ] );
[ $status, $body ] = callDev( $appData, \Nino\Admin\Config::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale refuses to overwrite an existing translation', $status === 200 && ( $body['created'] ?? null ) === false );
check( '...reporting what that file actually holds', ( $body['keys'] ?? 0 ) === 2 );
check( '...and leaving its values untouched', \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', [] )['[[/a]]'] === 'tradotto' );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiAddLocale', [ 'locale' => 'notalocale' ] );
check( 'apiAddLocale rejects a malformed language id', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiAddLocale', [ 'locale' => 'de_DE' ] );
check( 'apiAddLocale answers the native language from its own existing file', $status === 200 );

// A locale whose native has nothing to copy from cannot produce a skeleton -
// better a clear refusal than an empty file that looks like one
$noNative = $appData;
$noNativeConfig = \Nino\Filesystem::getFileContent( $noNative, '/config.php', [] );
$noNativeConfig['/nino/locales/native'] = 'pt_PT';
\Nino\Filesystem::putFileContent( $noNative, '/config.php', $noNativeConfig );
[ $status, $body ] = callDev( $noNative, \Nino\Admin\Config::class, 'apiAddLocale', [ 'locale' => 'sv_SE' ] );
check( 'apiAddLocale refuses when the native language has no text file', $status === 400 && str_contains( (string) ( $body['error'] ?? '' ), 'pt_PT' ) === true );
check( '...and writes nothing', \Nino\Filesystem::fileExists( $noNative, '/text/sv_SE.php' ) === false );
$noNativeConfig['/nino/locales/native'] = 'de_DE';
\Nino\Filesystem::putFileContent( $appData, '/config.php', $noNativeConfig );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiAddLocale', [ 'locale' => 'nl_NL' ] );
check( 'apiAddLocale requires an authed _admin session', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

// --- apiSave ------------------------------------------------------------

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/error/display' => false ] ] );
check( 'apiSave accepts a bool', $status === 200 );
check( 'the saved bool lands in appData', $appData['/nino/error/display'] === false );

// A form posts strings; config.php has to receive the real types
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => '8', '/nino/editor/logs' => 'true' ] ] );
check( 'apiSave coerces a posted numeric string', $status === 200 && $appData['/nino/auth/maxtries'] === 8 );
check( 'apiSave coerces a posted "true"', $appData['/nino/editor/logs'] === true );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => '5.5' ] ] );
check( 'apiSave rejects a non-integer rather than casting it', $status === 400 && $appData['/nino/auth/maxtries'] === 8 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 0 ] ] );
check( 'apiSave rejects an int below its minimum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/auth/cooldown' => 999999999 ] ] );
check( 'apiSave rejects an int above its maximum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => 'yes please' ] ] );
check( 'apiSave rejects a bool that is neither', $status === 400 );

// Nothing is written until every field validates, so one bad value cannot leave
// half a form saved
$beforePartial = $appData['/nino/error/display'];
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/error/display' => true, '/nino/auth/maxtries' => 'nope' ] ] );
check( 'a rejected field leaves the valid ones in the same request unwritten', $status === 400 && $appData['/nino/error/display'] === $beforePartial );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/modules' => [] ] ] );
check( 'apiSave ignores a key outside the schema', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/html/assets' => [] ] ] );
check( 'apiSave refuses /nino/html/assets, which this panel no longer owns', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/http/routes' => [] ] ] );
check( 'apiSave refuses /nino/http/routes, which belongs to Pages', $status === 400 );

// --- the two locale keys constrain each other ---------------------------

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'en_US',
] ] );
check( 'apiSave accepts a language list with a native language inside it', $status === 200 );
check( 'the language list round-trips', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

[ $status, $errorBody ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE' ],
	'/nino/locales/native' 		=> 'en_US',
] ] );
check( 'apiSave refuses a native language outside the list being saved', $status === 400 );
check( '...and says which rule was broken', str_contains( (string) ( $errorBody['error'] ?? '' ), 'native' ) === true );
check( '...leaving the previous list untouched', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

// Dropping the currently native language without naming a new one is the same
// contradiction, just arrived at from the other side
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE', 'fr_FR' ] ] ] );
check( 'apiSave refuses a list that drops the current native language', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [] ] ] );
check( 'apiSave refuses an empty language list', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE', 'notalocale' ] ] ] );
check( 'apiSave refuses a malformed locale id', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'de_DE', 'en_US' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );
check( 'apiSave deduplicates a repeated locale', $status === 200 && $appData['/nino/locales/available'] === [ 'de_DE', 'en_US' ] );

// A 'lines' field is typed as a textarea and stored as a list - the split and
// the trim live in the backend, so the two cannot disagree about what a blank
// line means
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => "/contact\n\n  /blog/*  \n/contact\n" ] ] );
check( 'apiSave splits a posted textarea into a list', $status === 200 && $appData['/nino/cache/blacklist'] === [ '/contact', '/blog/*' ] );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => [ '/a', '/b' ] ] ] );
check( '...and accepts an already-split list too', $status === 200 && $appData['/nino/cache/blacklist'] === [ '/a', '/b' ] );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => [ [ 'not', 'a', 'string' ] ] ] ] );
check( '...but refuses a list that is not of strings', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => '' ] ] );
check( '...and an emptied textarea clears the list', $status === 200 && $appData['/nino/cache/blacklist'] === [] );

[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [] ] );
check( 'apiSave rejects an empty form rather than writing nothing quietly', $status === 400 );

// Put the language list back the way the blocks below expect to find it -
// Translations asserts against all three. Saved through apiSave rather than
// assigned, so this also lands in config.php, which is where its own apiInfo
// reads the list from
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [
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

// --- shared Admin / Templates / Theme bridge -----------------------------

$toolRoot = $sandbox. '/tool-delivery';
$writeToolFile = static function( string $file ) use ( $toolRoot ): void {
	$path = $toolRoot. '/'. $file;
	if( is_dir( dirname( $path ) ) === false )
		mkdir( dirname( $path ), 0777, true );
	file_put_contents( $path, '' );
};

foreach( [ '_admin/index.php', '_admin/Admin.php', '_admin/templates/page-index.tpl' ] as $file )
	$writeToolFile( $file );

check( 'the tool bridge stays out of a delivery that has nowhere else to go', \Nino\Admin\ToolBridge::render( $appData, 'admin', $toolRoot ) === '' );

foreach( [ '_templates/index.php', '_templates/Templates.php', '_templates/templates/page-index.tpl' ] as $file )
	$writeToolFile( $file );

$appData['/nino/dir'] = '/subdir';
$toolBridge = \Nino\Admin\ToolBridge::render( $appData, 'templates', $toolRoot );
$adminToolPosition = strpos( $toolBridge, 'data-nino-admin-tool="admin"' );
$templatesToolPosition = strpos( $toolBridge, 'data-nino-admin-tool="templates"' );
check( 'the bridge lists exactly the complete tools in their stable order', $adminToolPosition !== false && $templatesToolPosition !== false
	&& $adminToolPosition < $templatesToolPosition
	&& str_contains( $toolBridge, 'data-nino-admin-tool="design"' ) === false );
check( 'the bridge keeps links below the configured project directory', str_contains( $toolBridge, 'href="/subdir/_admin/"' )
	&& str_contains( $toolBridge, 'href="/subdir/_templates/"' ) );
check( 'the bridge exposes exactly one semantic current tool', substr_count( $toolBridge, 'aria-current="page"' ) === 1
	&& str_contains( $toolBridge, 'data-nino-admin-tool="templates" aria-current="page"' ) );

$writeToolFile( '_design/index.php' );
check( 'a partially copied tool is not advertised', str_contains( \Nino\Admin\ToolBridge::render( $appData, 'admin', $toolRoot ), 'data-nino-admin-tool="design"' ) === false );

foreach( [ '_design/Design.php', '_design/templates/page-index.tpl' ] as $file )
	$writeToolFile( $file );

$toolBridge = \Nino\Admin\ToolBridge::render( $appData, 'design', $toolRoot );
check( 'a complete optional tool joins the bridge without a config switch', substr_count( $toolBridge, 'data-nino-admin-tool=' ) === 3
	&& str_contains( $toolBridge, 'data-nino-admin-tool="design" aria-current="page"' ) );

// Admin::init() may be called more than once in a composed request. The bridge
// callback must still execute once: a second callback would receive the first
// callback's rendered string instead of the shortcode argument array.
\Nino\Admin\Admin::init( $shadowed );
$renderedToolBridge = \Nino\Html::renderHtml( $shadowed, '[admin-tools admin]' );
check( 'repeated Admin init registers the shared shortcode only once', substr_count( $renderedToolBridge, '<nav class="nino-admin-tools"' ) === 1
	&& substr_count( $renderedToolBridge, 'aria-current="page"' ) === 1 );
$appData['/nino/dir'] = '';

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiList' );
check( 'Config actions require an authed _admin session too', $status === 401 );
[ $status ] = callDev( $appData, \Nino\Admin\Config::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => true ] ] );
check( '...apiSave too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

echo "\n";


// --- Dev\Users ------------------------------------------------------------

echo "Dev\\Users - admin account bootstrap (create/delete) + raw permissions editor\n";

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'password' => 'a-long-enough-password', 'isManager' => true ] );
check( 'apiCreate creates a user', $status === 200 );
check( 'the new user has the manage permission', \Nino\Auth::checkPermission( $appData, '/_editor/users/manage', 'newdev@example.com' ) === true );

// A non-manager account still gets full content access (elements/text/...),
// same as any admin account had before per-module permissions existed - only
// /_editor/users/manage is gated by the isManager checkbox
[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiCreate', [ 'mail' => 'newcontenteditor@example.com', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate creates a non-manager user', $status === 200 );
check( 'a non-manager still has content-module access', \Nino\Auth::checkPermission( $appData, '/_editor/elements/manage', 'newcontenteditor@example.com' ) === true );
check( 'a non-manager does not have the manage permission', \Nino\Auth::checkPermission( $appData, '/_editor/users/manage', 'newcontenteditor@example.com' ) === false );
\Nino\Auth::deleteUser( $appData, 'newcontenteditor@example.com' );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiCreate', [ 'mail' => 'not-an-email', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate rejects an invalid mail', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiCreate', [ 'mail' => 'short@example.com', 'password' => 'short' ] );
check( 'apiCreate rejects a too-short password', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate rejects a mail that already exists', $status === 409 );

[ , $body ] = callDev( $appData, \Nino\Admin\Users::class, 'apiList' );
check( 'the new user shows up in apiList, never a password hash', in_array( 'newdev@example.com', array_column( $body['users'], 'mail' ), true ) === true && isset( $body['users'][0]['pw'] ) === false );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiDelete', [ 'mail' => 'newdev@example.com' ] );
check( 'apiDelete removes the user', $status === 200 );
check( 'the user is actually gone', isset( $appData['/nino/auth/user']['newdev@example.com'] ) === false );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiDelete', [ 'mail' => 'newdev@example.com' ] );
check( 'apiDelete 404s for an already-gone user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiCreate', [ 'mail' => 'permsdev@example.com', 'password' => 'a-long-enough-password' ] );
check( 'apiCreate creates the account used for the permissions editor checks', $status === 200 );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiSetPermissions', [ 'mail' => 'unknown@example.com', 'perms' => [ '/*' ] ] );
check( 'apiSetPermissions 404s for an unknown user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiSetPermissions', [ 'mail' => 'permsdev@example.com', 'perms' => 'not-an-array' ] );
check( 'apiSetPermissions rejects a non-array perms value', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Users::class, 'apiSetPermissions', [ 'mail' => 'permsdev@example.com', 'perms' => [ '/_editor/elements/manage', 42 ] ] );
check( 'apiSetPermissions rejects a perms array with a non-string entry', $status === 400 );

// No whitelist here on purpose - unlike _editor's own apiSetPermissions() this is the
// developer bypass, so even a permission string _editor doesn't know about persists
[ $status, $body ] = callDev( $appData, \Nino\Admin\Users::class, 'apiSetPermissions', [ 'mail' => 'permsdev@example.com', 'perms' => [ '/_editor/elements/manage', '/some/future/module' ] ] );
check( 'apiSetPermissions saves an arbitrary, unwhitelisted perms array', $status === 200 && $body['perms'] === [ '/_editor/elements/manage', '/some/future/module' ] );
check( 'the permission actually took effect', \Nino\Auth::checkPermission( $appData, '/_editor/elements/manage', 'permsdev@example.com' ) === true );
check( 'a permission not granted stays denied', \Nino\Auth::checkPermission( $appData, '/_editor/text/manage', 'permsdev@example.com' ) === false );

[ , $body ] = callDev( $appData, \Nino\Admin\Users::class, 'apiList' );
$listed = $body['users'][ array_search( 'permsdev@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( 'apiList exposes the raw perms array too, not just the derived isManager flag', $listed['perms'] === [ '/_editor/elements/manage', '/some/future/module' ] );

\Nino\Auth::deleteUser( $appData, 'permsdev@example.com' );

echo "\n";


// --- Dev\Images -----------------------------------------------------------

echo "Dev\\Images - image slot definitions (label/width/height only)\n";

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'Hero', 'width' => '1200', 'height' => '600' ] );
check( 'apiCreate creates a slot', $status === 200 );
check( 'the slot starts with no filename', array_key_exists( 'filename', $appData['/nino/html/images']['/home/hero'] ) === true && $appData['/nino/html/images']['/home/hero']['filename'] === null );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiCreate', [ 'uri' => 'not valid', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects an invalid uri', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects a uri that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Images::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the new slot', in_array( '/home/hero', array_column( $body['slots'], 'uri' ), true ) === true );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu', 'width' => '800', 'height' => '400' ] );
check( 'apiSave edits an existing slot', $status === 200 );
check( 'the slot metadata actually changed', $appData['/nino/html/images']['/home/hero']['label'] === 'Hero neu' && $appData['/nino/html/images']['/home/hero']['width'] === 800 );

$appData['/nino/html/images']['/home/hero']['filename'] = 'elements/home/hero.800x400.jpg';
[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave never touches an existing filename', $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg' );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiSave', [ 'uri' => '/does/not/exist', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiSave 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiDelete', [ 'uri' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'the slot is gone from appData', isset( $appData['/nino/html/images']['/home/hero'] ) === false );
$imagesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [];
check( 'the slot is gone from config.php too', isset( $imagesAfterDelete['/home/hero'] ) === false );

echo "\n";


// --- Dev\Text ---------------------------------------------------------------

echo "Dev\\Text - text key schema (existence, global/per-locale, blacklist)\n";

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiCreate', [ 'key' => '/home/welcome/subtitle', 'global' => false, 'value' => 'Start' ] );
check( 'apiCreate creates a per-locale key', $status === 200 );

$deDE = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUS = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the initial value is written into every available locale', $deDE['[[/home/welcome/subtitle]]'] === 'Start' && $enUS['[[/home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiCreate', [ 'key' => '/company/tagline', 'global' => true, 'value' => 'Immer' ] );
check( 'apiCreate creates a global key', $status === 200 );

$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the initial value is written into global.php, not any locale file', $global['[[/company/tagline]]'] === 'Immer' );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiCreate', [ 'key' => 'not-a-valid-key', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects an invalid key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiCreate', [ 'key' => '/company/tagline', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects a key that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
$subtitleEntry = null;
foreach( $body['keys'] as $entry ) if( $entry['key'] === '/home/welcome/subtitle' ) $subtitleEntry = $entry;
check( 'apiList finds the new per-locale key, not blacklisted by default', $subtitleEntry !== null && $subtitleEntry['global'] === false && $subtitleEntry['blacklisted'] === false );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSave', [ 'key' => '/home/welcome/subtitle', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave converts a per-locale key to global', $status === 200 );

$deDEAfter = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'converting to global removes it from every locale file', isset( $deDEAfter['[[/home/welcome/subtitle]]'] ) === false );
check( 'converting to global migrates the value instead of discarding it', $globalAfter['[[/home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSave', [ 'key' => '/home/welcome/subtitle', 'global' => false, 'blacklisted' => true ] );
check( 'apiSave converts back to per-locale and blacklists it in the same call', $status === 200 );

$deDEAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklist = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'converting back to per-locale migrates the value into every locale', $deDEAfter2['[[/home/welcome/subtitle]]'] === 'Start' );
check( 'converting back to per-locale removes it from global.php', isset( $globalAfter2['[[/home/welcome/subtitle]]'] ) === false );
check( 'the key is now blacklisted', in_array( '/home/welcome/subtitle', $blacklist, true ) === true );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSave', [ 'key' => '/does/not/exist', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave 404s for an unknown key', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
	[ 'key' => '/home/welcome/subtitle', 'locale' => 'de_DE', 'value' => 'Neuer Start' ],
] ] );
check( 'apiSaveBatch succeeds', $status === 200 );
check( 'apiSaveBatch saves a global value', $body['results']['/company/tagline']['ok'] === true );
check( 'apiSaveBatch saves a blacklisted key\'s value too - blacklist only hides it from _editor', $body['results']['/home/welcome/subtitle']['ok'] === true );

$globalAfterBatch = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$deDEAfterBatch 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the global value actually landed in global.php', $globalAfterBatch['[[/company/tagline]]'] === 'Immer und ewig' );
check( 'the per-locale value actually landed in the right locale file', $deDEAfterBatch['[[/home/welcome/subtitle]]'] === 'Neuer Start' );

// html is auto-detected from a key's *current* value (see _entries()) - create one
// that already holds a whitelisted tag, so this save actually exercises sanitizeHtml()
// rather than the plain strip_tags() path a fresh/plain-text key would get
[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiCreate', [ 'key' => '/company/note', 'global' => true, 'value' => '<strong>Wichtig</strong>' ] );
check( 'apiCreate creates the html-flagged fixture key', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/note', 'locale' => '*', 'value' => '<script>alert(1)</script><strong>Wichtig</strong><em>auch</em><code>x()</code>' ],
] ] );
check( 'apiSaveBatch sanitizes html and preserves inline code', $body['results']['/company/note']['value'] === '<strong>Wichtig</strong><em>auch</em><code>x()</code>' );

// A plain-text value is substituted raw by Html::_renderFills(), attribute
// values included ('<a href="[[/company/facebook]]">'), so a stored quote is
// an attribute break-out that strip_tags() never sees. Entities render as the
// character itself in both contexts, and re-encode to themselves on a re-save
[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'x" onmouseover="alert(1)' ],
] ] );
check( 'apiSaveBatch entity-encodes quotes in a plain-text value', $body['results']['/company/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'x&quot; onmouseover=&quot;alert(1)' ],
] ] );
check( '...and saving that value again does not escape it a second time', $body['results']['/company/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
] ] );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/does/not/exist', 'locale' => '*', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch reports an unknown key without failing the whole request', $status === 200 && $body['results']['/does/not/exist']['ok'] === false );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/home/welcome/subtitle', 'locale' => 'xx_XX', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch rejects an invalid locale for a per-locale key', $body['results']['/home/welcome/subtitle']['ok'] === false );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiRename', [ 'key' => '/company/tagline', 'newKey' => '/company/motto' ] );
check( 'apiRename succeeds for a global key', $status === 200 );

$globalAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the old key is gone from global.php', isset( $globalAfterRename['[[/company/tagline]]'] ) === false );
check( 'the value moved to the new key', $globalAfterRename['[[/company/motto]]'] === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => 'not-a-valid-key' ] );
check( 'apiRename rejects an invalid new key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => '/company/note' ] );
check( 'apiRename rejects a new key that already exists', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiRename', [ 'key' => '/does/not/exist', 'newKey' => '/also/new' ] );
check( 'apiRename 404s for an unknown key', $status === 404 );

// Renaming a key to the name it already has is a no-op, not a delete. The
// mutate pair below it ("write the new bracket, unset the old one") collapses
// into a plain unset when both are the same string, so this used to answer 200
// and drop the value - text.js guards it in the ui, the endpoint has to too
[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => '/company/motto' ] );
check( 'apiRename accepts a rename to the key\'s own name', $status === 200 );
check( '...and leaves the value where it was instead of deleting it', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/company/motto]]'] ?? null ) === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiDelete', [ 'key' => '/company/note' ] );
check( 'apiDelete succeeds for a global key', $status === 200 );

$globalAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the deleted global key is gone', isset( $globalAfterDelete['[[/company/note]]'] ) === false );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiDelete', [ 'key' => '/home/welcome/subtitle' ] );
check( 'apiDelete succeeds for a blacklisted per-locale key', $status === 200 );

$deDEAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUSAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$blacklistAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the deleted per-locale key is gone from every locale file', isset( $deDEAfterDelete['[[/home/welcome/subtitle]]'] ) === false && isset( $enUSAfterDelete['[[/home/welcome/subtitle]]'] ) === false );
check( 'the deleted key is also gone from the blacklist', in_array( '/home/welcome/subtitle', $blacklistAfterDelete, true ) === false );

[ $status ] = callDev( $appData, \Nino\Admin\Text::class, 'apiDelete', [ 'key' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown key', $status === 404 );

echo "\n";


// --- Text::apiScan / Images::apiScan: template scanners ---------------------

echo "Text::apiScan - missing [[/key]] placeholders in templates/*.tpl\n";

mkdir( $sandbox. '/private/templates', 0777, true );
file_put_contents( $sandbox. '/private/templates/scan-fixture.tpl', '<p>[[/page-scan-test/heading]]</p><p>[[/page-scan-test/heading]]</p><p>[[/company/name]]</p>' );
// A nested, dynamically-constructed fill (same shape as html-header.tpl's real
// [[/webpage[[/nino/http/response/uri]]/title]]) - only the inner, always-
// registered kernel fill should ever be visible to a static regex scan
file_put_contents( $sandbox. '/private/templates/scan-fixture-2.tpl', '<title>[[/webpage[[/nino/http/response/uri]]/title]]</title><img src="[[/nino/public]]/images/example.jpg">' );

// /company/name is genuinely defined (unlike /page-scan-test/heading) - proves
// a real key is correctly excluded, not just absent from the fixture by accident
$globalFixture = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$globalFixture['[[/company/name]]'] = 'Acme';
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $globalFixture );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Text::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );

$missingKeys = array_column( $body['missing'], 'key' );
check( 'a genuinely undefined key is reported', in_array( '/page-scan-test/heading', $missingKeys, true ) === true );
check( 'an undefined key found twice in the same file is only reported once', count( array_filter( $missingKeys, fn( $k ) => $k === '/page-scan-test/heading' ) ) === 1 );
check( 'an already-defined key is not reported', in_array( '/company/name', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/http/response/uri fill is never reported, despite appearing inside a nested [[...]] construct', in_array( '/nino/http/response/uri', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/public fill is never reported as missing', in_array( '/nino/public', $missingKeys, true ) === false );

unlink( $sandbox. '/private/templates/scan-fixture.tpl' );
unlink( $sandbox. '/private/templates/scan-fixture-2.tpl' );

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

[ $status, $body ] = callDev( $appData, \Nino\Admin\Images::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );
check( 'exactly one missing image is reported (deduped, external/data-uri skipped)', count( $body['missing'] ) === 1 );
check( 'the reported filename is the local one, relative to /images/', ( $body['missing'][0]['filename'] ?? null ) === 'probe.png' );
check( 'width/height were probed from the real file since the <img> tag had none', ( $body['missing'][0]['width'] ?? null ) === 3 && ( $body['missing'][0]['height'] ?? null ) === 2 );
check( 'a suggested uri was derived from the filename', ( $body['missing'][0]['suggestedUri'] ?? null ) === '/probe' );

[ $status ] = callDev( $appData, \Nino\Admin\Images::class, 'apiCreate', [ 'uri' => '/probe', 'label' => 'Probe', 'width' => '3', 'height' => '2' ] );
check( 'the suggested slot can actually be created', $status === 200 );

// Once a slot's filename actually tracks it (the manual step _editor's upload
// flow would do next), the same <img> must disappear from the scan
$appData['/nino/html/images']['/probe']['filename'] = 'probe.png';
[ $status, $body ] = callDev( $appData, \Nino\Admin\Images::class, 'apiScan' );
check( 'once a slot tracks the file, it no longer shows up as missing', count( $body['missing'] ) === 0 );

unlink( $sandbox. '/private/templates/scan-fixture-img.tpl' );

echo "\n";


// --- Dashboard::apiSummary - aggregates Text/Images::missingCount() ---------

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callDev( $appData, \Nino\Admin\Dashboard::class, 'apiSummary' );
check( 'apiSummary succeeds', $status === 200 );
check( 'missingText is 0 - both scan fixtures above were already cleaned up', $body['missingText'] === 0 );
check( 'missingImages is 0 - the scan fixture above was already cleaned up', $body['missingImages'] === 0 );

// A fresh, self-contained fixture (one undefined key, one untracked image) to
// prove the two counts actually reflect Text/Images::_scanMissing(), not just
// always 0 - a new filename, since probe.png is already tracked by the slot
// created in the Images::apiScan section above
file_put_contents( $sandbox. '/private/templates/dashboard-fixture.tpl', '<p>[[/dashboard-scan-test/heading]]</p><img src="[[/nino/public]]/images/probe2.png">' );

[ , $body ] = callDev( $appData, \Nino\Admin\Dashboard::class, 'apiSummary' );
check( 'missingText picks up the fresh undefined key', $body['missingText'] === 1 );
check( 'missingImages picks up the fresh untracked image (a new filename, not tracked by any slot)', $body['missingImages'] === 1 );

unlink( $sandbox. '/private/templates/dashboard-fixture.tpl' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\Dashboard::class, 'apiSummary' );
check( 'apiSummary requires an authed dev session too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

echo "\n";


// --- Dev\PageEditor - create/edit/delete page routes -------------------------

echo "Dev\\PageEditor - create/edit/delete page routes\n";

file_put_contents( $sandbox. '/private/templates/page-about.tpl', '<h1>About</h1>' );
file_put_contents( $sandbox. '/private/templates/page-contact.tpl', '<h1>Contact</h1>' );
file_put_contents( $sandbox. '/private/templates/not-a-page.tpl', '<p>ignored - not a page-*.tpl file</p>' );

[ $status, $body ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'starts with an empty page list', $body['pages'] === [] );
check( 'lists only templates/page-*.tpl files, sorted, extension stripped', $body['templates'] === [ 'page-about', 'page-contact' ] );
check( 'no navigations are offered - Navigation was never picked', $body['navs'] === [] );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '../etc/passwd', 'httpUri' => '/about', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an unsafe Element-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/about', 'httpUri' => '../etc/passwd', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an unsafe Http-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/admin-shadow', 'httpUri' => '/_admin', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects a page mounted on a runtime-owned tool uri', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/designer-shadow', 'httpUri' => '/_templates', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects a page mounted on the Template Builder runtime uri', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/appearance-shadow', 'httpUri' => '/_design', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects a page mounted on the Appearance runtime uri', $status === 409 );

\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://owned'] = [ 'uri' => '/owned', 'body' => 'hand-written route' ];
	return $config;
} );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/owned-page', 'httpUri' => '/owned', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an Http-URI already owned by a non-page route', $status === 409 );
check( 'keeps the colliding hand-written route unchanged', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://owned']['body'] ?? null ) === 'hand-written route' );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/about', 'httpUri' => '/about', 'template' => 'not-a-page', 'text' => [],
] );
check( 'rejects a template outside the page-*.tpl whitelist with 400', $status === 400 );

// The actual, first real save: Element-URI deliberately differs from
// Http-URI, to prove the class uses the entry's own uri, not the request
// path, for the route's own data field/meta namespace
[ $status, $body ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'statusCode' => 200, 'text' => [
		'de_DE' => [ 'name' => 'Über uns', 'title' => 'Über uns', 'description' => 'Über unser Unternehmen.' ],
	],
] );
check( 'apiSave succeeds', $status === 200 );
check( 'response echoes the persisted list', count( $body['pages'] ) === 1 );

$configAfterSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'registers a route at "/about" (its Http-URI)', isset( $configAfterSave['/nino/http/routes']['GET://about'] ) === true );
check( 'the route\'s own "uri" data field is the Element-URI, not the Http-URI', $configAfterSave['/nino/http/routes']['GET://about']['uri'] === '/site-about' );
check( 'the route body references the picked template, extension stripped', $configAfterSave['/nino/http/routes']['GET://about']['body'] === '[template /templates/page-about]' );
check( 'a 200 status code is not written out (the implicit default)', isset( $configAfterSave['/nino/http/routes']['GET://about']['statusCode'] ) === false );
check( 'the route is the whole persistence - no second list is written alongside it', isset( $configAfterSave['/nino/install/webpages'] ) === false && count( array_filter( $configAfterSave['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Admin\PageEditor::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === 1 );

$deAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'writes the page\'s own de_DE meta, keyed by its Element-URI', $deAfterSave['[[/webpage/site-about/name]]'] === 'Über uns' && $deAfterSave['[[/webpage/site-about/title]]'] === 'Über uns' );

$enAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a locale left entirely unposted still gets the generic placeholder, not left unset', $enAfterSave['[[/webpage/site-about/name]]'] === 'Page' );

$globalAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'writes the page\'s reachable Http-URI as one global fill a template can link to by name', ( $globalAfterSave['[[/webpage/site-about/uri]]'] ?? null ) === '/about'
	&& isset( $deAfterSave['[[/webpage/site-about/uri]]'], $enAfterSave['[[/webpage/site-about/uri]]'] ) === false );
check( 'that uri is blacklisted as a technical value, like every other route key', in_array( '/webpage/site-about/uri', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) );

// Duplicate checks: a second entry may not reuse either uri
[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-about', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => [],
] );
check( 'rejects a duplicate Element-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/about', 'template' => 'page-contact', 'text' => [],
] );
check( 'rejects a duplicate Http-URI with 400', $status === 400 );

// A real second entry, with a non-200 status code and explicit menu membership
[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'statusCode' => 404, 'navs' => [ 'main' ], 'text' => [
		'de_DE' => [ 'name' => 'Kontakt' ],
	],
] );
check( 'apiSave succeeds for the second entry too', $status === 200 );

$configAfterSecond = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'a non-200, in-range status code is written out as posted', $configAfterSecond['/nino/http/routes']['GET://contact']['statusCode'] === 404 );
check( 'both page routes are now persisted, in order', array_keys( array_filter( $configAfterSecond['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Admin\PageEditor::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === [ 'GET://about', 'GET://contact' ] );

// Navigation: only regenerated while the module is active
$appData['/nino/modules'][] = '\\Nino\\Modules\\Navigation';
// The registry is what a menu name is checked against, and it is explicit -
// there is no implied default menu to fall back on
$appData['/nino/html/navs'] = [ 'main' ];

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '/contact', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'navs' => [ 'main' ], 'text' => [
		'de_DE' => [ 'name' => 'Kontakt' ],
	],
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
[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'navs' => [ 'main' ], 'text' => [
		'de_DE' => [ 'name' => 'About' ],
	],
] );
check( 'assigning the first entry to main too succeeds', $status === 200 );

// A membership a save newly adds goes behind everything already in that menu
check( 'the second page to join the menu lands behind the first, not on top of it', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://about']['navs'] ?? null ) === [ 'main' => 2 ] );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'moving the first entry up (already at the top) 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiMove', [ 'httpUri' => '/contact', 'direction' => 'down' ] );
check( 'moving the last entry down (already at the bottom) 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'sideways' ] );
check( 'rejects an invalid direction with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiMove', [ 'httpUri' => '/does-not-exist', 'direction' => 'up' ] );
check( 'apiMove 404s for an unknown httpUri', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'down' ] );
check( 'moving the first entry down succeeds', $status === 200 );
check( 'the two entries actually swapped places', array_column( $body['pages'], 'httpUri' ) === [ '/contact', '/about' ] );

$configAfterMove = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the swapped order is persisted too', array_slice( array_keys( $configAfterMove['/nino/http/routes'] ), -2 ) === [ 'GET://contact', 'GET://about' ] );
check( 'the hand-written route the swap stepped over kept its own slot', array_keys( $configAfterMove['/nino/http/routes'] )[0] === 'GET://owned' );

// Move it back for the rename/delete checks below, which assume the
// original order ("about" first)
[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'moving it back up succeeds', $status === 200 );

// Renaming an entry's Http-URI: the old route key must disappear, the new
// one appear, and the list must stay at 2 entries (replaced in place)
[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/ueber-uns', 'template' => 'page-about', 'text' => [
		'de_DE' => [ 'name' => 'Über uns' ],
	],
] );
check( 'renaming an entry\'s Http-URI succeeds', $status === 200 );

$configAfterRename = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the old route key is gone after a rename', isset( $configAfterRename['/nino/http/routes']['GET://about'] ) === false );
check( 'the new route key exists', isset( $configAfterRename['/nino/http/routes']['GET://ueber-uns'] ) === true );
check( 'still exactly two page routes - rename replaced in place, did not append a third', count( array_filter( $configAfterRename['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Admin\PageEditor::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === 2 );
check( 'the renamed route keeps the slot the old key stood in, rather than dropping to the bottom', array_slice( array_keys( $configAfterRename['/nino/http/routes'] ), -2 ) === [ 'GET://ueber-uns', 'GET://contact' ] );

// Delete
[ $status, $body ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiDelete', [ 'httpUri' => '/ueber-uns' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'exactly one page remains', count( $body['pages'] ) === 1 );

$configAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the deleted entry\'s route is gone', isset( $configAfterDelete['/nino/http/routes']['GET://ueber-uns'] ) === false );
check( 'the surviving entry\'s route is untouched', isset( $configAfterDelete['/nino/http/routes']['GET://contact'] ) === true );

$deAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the deleted entry\'s own text meta is left in place - additive-only, never auto-deleted', isset( $deAfterDelete['[[/webpage/site-about/name]]'] ) === true );

[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiDelete', [ 'httpUri' => '/does-not-exist' ] );
check( 'apiDelete 404s for an unknown httpUri', $status === 404 );

array_pop( $appData['/nino/modules'] ); // drop the simulated Navigation module again

unlink( $sandbox. '/private/templates/page-about.tpl' );
unlink( $sandbox. '/private/templates/page-contact.tpl' );
unlink( $sandbox. '/private/templates/not-a-page.tpl' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\PageEditor::class, 'apiList' );
check( 'PageEditor actions require an authed _admin session too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

echo "\n";


// --- Dev\Navigations - which menus exist, and what stands in them ----------

echo "Dev\\Navigations - menus and their running order\n";

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
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ),
	[ '[[/webpage/home/name]]' => 'Start', '[[/webpage/contact/name]]' => 'Kontakt', '[[/webpage/legal/name]]' => 'Impressum' ]
) );

$navKeys = fn( array $body ): array => array_column( $body['navs'], 'key' );
$entriesOf = fn( array $body, string $key ): array => array_column(
	$body['navs'][ array_search( $key, array_column( $body['navs'], 'key' ), true ) ]['entries'], 'httpUri'
);

[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'lists every registered menu, in registry order', $navKeys( $body ) === [ 'main', 'footer' ] );
check( 'a menu reports its members in running order', $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );
check( 'a registered menu nobody is in is still listed, empty', $entriesOf( $body, 'footer' ) === [] );
check( 'reports whether the module that renders any of this is active', $body['active'] === false );
check( 'offers every GET route as a possible entry, not just the page ones', array_column( $body['routes'], 'httpUri' ) === [ '/', '/robots.txt', '/contact', '/legal' ] );
check( 'labels a route by the /webpage<uri>/name key the menu would render', $body['routes'][0]['label'] === 'Start' );
check( '...and falls back to the path for one nobody named', $body['routes'][1]['label'] === '/robots.txt' );
check( 'a route with no name is reported as such - routeLines() would skip it', $body['routes'][1]['named'] === false && $body['routes'][0]['named'] === true );

// Assign: joins at the end, never in the middle
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiAssign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'apiAssign succeeds', $status === 200 );
check( 'the new entry joins at the end', $entriesOf( $body, 'main' ) === [ '/', '/contact', '/legal' ] );

$routesAfterAssign = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'membership is written onto the route itself, densely numbered', [
	$routesAfterAssign['GET://']['navs'], $routesAfterAssign['GET://contact']['navs'], $routesAfterAssign['GET://legal']['navs'],
] === [ [ 'main' => 1 ], [ 'main' => 2 ], [ 'main' => 3 ] ] );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiAssign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'assigning the same route twice 409s', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiAssign', [ 'key' => 'nope', 'httpUri' => '/legal' ] );
check( 'assigning into an unknown menu 404s', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiAssign', [ 'key' => 'main', 'httpUri' => '/does-not-exist' ] );
check( 'assigning an unknown route 404s', $status === 404 );

// Move
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/legal', 'direction' => 'up' ] );
check( 'apiMove succeeds', $status === 200 );
check( 'the two entries swapped places', $entriesOf( $body, 'main' ) === [ '/', '/legal', '/contact' ] );

$routesAfterMove = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'the swap is a swap of two priorities, still dense', [
	$routesAfterMove['GET://']['navs'], $routesAfterMove['GET://legal']['navs'], $routesAfterMove['GET://contact']['navs'],
] === [ [ 'main' => 1 ], [ 'main' => 2 ], [ 'main' => 3 ] ] );
check( 'the route array itself is not reordered by a menu move', array_keys( $routesAfterMove ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/', 'direction' => 'up' ] );
check( 'moving the first entry up 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/contact', 'direction' => 'sideways' ] );
check( 'an invalid direction 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/robots.txt', 'direction' => 'up' ] );
check( 'moving a route that is not in this menu 404s', $status === 404 );

// Unassign: closes the gap it leaves
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiUnassign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'apiUnassign succeeds', $status === 200 );
check( 'the entry is gone from the menu', $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );

$routesAfterUnassign = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'the gap it left is closed right away', [ $routesAfterUnassign['GET://']['navs'], $routesAfterUnassign['GET://contact']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );
check( 'a route in no menu at all carries no "navs" key rather than an empty one', isset( $routesAfterUnassign['GET://legal']['navs'] ) === false );
check( 'the route itself survives losing its membership', isset( $routesAfterUnassign['GET://legal'] ) === true );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiUnassign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'unassigning a route that is not in the menu 404s', $status === 404 );

// Create
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => '', 'key' => 'meta' ] );
check( 'creating a menu succeeds', $status === 200 );
check( 'it is registered, at the end', $navKeys( $body ) === [ 'main', 'footer', 'meta' ] );
check( '...and starts empty', $entriesOf( $body, 'meta' ) === [] );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => '', 'key' => 'meta' ] );
check( 'creating one that already exists 409s', $status === 409 );

foreach( [ 'Main Menu', 'main/sub', '', '2fast', 'MAIN' ] as $badKey ) {
	[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => '', 'key' => $badKey ] );
	check( 'rejects "'. $badKey. '" as a menu id', $status === 400 );
}

// Rename: follows the key into the registry and onto every member route
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'primary' ] );
check( 'renaming succeeds', $status === 200 );
check( 'the renamed menu keeps its place in the registry', $navKeys( $body ) === [ 'primary', 'footer', 'meta' ] );
check( '...and keeps its members, in order', $entriesOf( $body, 'primary' ) === [ '/', '/contact' ] );

$routesAfterRename = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'every member route carries the new key, at the priority it had', [ $routesAfterRename['GET://']['navs'], $routesAfterRename['GET://contact']['navs'] ] === [ [ 'primary' => 1 ], [ 'primary' => 2 ] ] );
check( 'the old key is gone from the routes', isset( $routesAfterRename['GET://']['navs']['main'] ) === false );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => 'primary', 'key' => 'footer' ] );
check( 'renaming onto a registered id 409s', $status === 409 );

// ...and onto one only a route carries: without this the rename would merge
// two memberships into one and silently drop a priority
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://']['navs']['handwritten'] = 4;
	return $config;
} );
[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => 'primary', 'key' => 'handwritten' ] );
check( 'renaming onto an id only a hand-written route carries 409s too', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiSave', [ 'originalKey' => 'nope', 'key' => 'whatever' ] );
check( 'renaming an unknown menu 404s', $status === 404 );

// Delete: out of the registry, and off every route
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting succeeds', $status === 200 );
check( 'it is out of the registry', $navKeys( $body ) === [ 'footer', 'meta' ] );

$routesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'every member route lost the membership too', isset( $routesAfterDelete['GET://contact']['navs'] ) === false );
check( 'a route that was in a second menu keeps that one', $routesAfterDelete['GET://']['navs'] === [ 'handwritten' => 4 ] );
check( 'the routes themselves survive', array_keys( $routesAfterDelete ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );

[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting an unknown menu 404s', $status === 404 );

// Deleting the last one really does leave none.
callDev( $appData, \Nino\Admin\Navigations::class, 'apiDelete', [ 'key' => 'footer' ] );
[ $status, $body ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiDelete', [ 'key' => 'meta' ] );
check( 'the last menu can be deleted, and stays deleted', $navKeys( $body ) === [] );

unset( $appData['/nino/html/navs'] );
check( 'a missing navigation registry exposes no implicit menu', \Nino\Admin\Navigations::registry( $appData ) === [] );
$appData['/nino/html/navs'] = [];
check( 'an empty navigation registry stays empty', \Nino\Admin\Navigations::registry( $appData ) === [] );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\Navigations::class, 'apiList' );
check( 'Navigations actions require an authed _admin session too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

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

[ $status, $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiTypes' );
check( 'apiTypes succeeds', $status === 200 );
check( 'apiTypes lists every type on disk', in_array( 'contenttype', array_column( $body['types'], 'type' ), true ) === true );
check( 'apiTypes carries each type\'s model along', ( $body['types'][array_search( 'contenttype', array_column( $body['types'], 'type' ), true )]['model']['views']['type'] ?? null ) === 'integer' );
// Not a literal list: an earlier Config::apiSave check in this same file adds
// fr_FR to the available locales, and this module must simply report whatever
// is configured at the time it runs
check( 'apiTypes reports the currently available locales', $body['locales'] === \Nino\Locales::getAvailableLocales( $appData ) );
check( 'apiTypes seeds the locale select with the native locale', $body['selectedLocale'] === 'de_DE' );

[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiList', [ 'type' => 'nope' ] );
check( 'apiList 404s for an unknown type', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'first', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'Erster', 'views' => 3 ],
] );
check( 'apiSave inserts a new element', $status === 200 );
check( 'apiSave returns the stored element', ( $body['element']['title'] ?? null ) === 'Erster' && ( $body['element']['views'] ?? null ) === 3 );

[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'not a slug', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'x' ],
] );
check( 'apiSave rejects a new uri that is not a plain slug', $status === 400 );

// The second locale of the same element - what the frontend's _saveLocales()
// sends as a follow-up request, global fields omitted (position > 0)
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'first', 'locale' => 'en_US', 'isNew' => false,
	'fields' => [ 'title' => 'First' ],
] );
check( 'apiSave updates a second locale of the same element', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiList', [ 'type' => 'contenttype' ] );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the element across locales', count( $body['elements'] ) === 1 && $body['elements'][0]['uri'] === 'first' );
check( 'apiList labels an element by its title', $body['elements'][0]['label'] === 'Erster' );

// Only 'title', and the element's own uri otherwise. Guessing a label from
// 'label'/'name' or from whichever string field came first in the model meant
// the list showed a different field per type, and reordering a model silently
// relabelled every row in it
[ $status ] = callDev( $appData, \Nino\Admin\ElementTypes::class, 'apiCreate', [ 'uri' => 'labeltype', 'title' => 'Label Type', 'model' => [
	'name' 	=> [ 'type' => 'string' ],
	'label' => [ 'type' => 'string' ],
	'title' => [ 'type' => 'string' ],
] ] );
check( 'a type for the label rules is created', $status === 200 );

callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'titled', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'label' => 'A label', 'title' => 'A title' ] ] );
callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'untitled', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'label' => 'A label' ] ] );
callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'blank-title', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'title' => '' ] ] );

[ , $labelBody ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiList', [ 'type' => 'labeltype' ] );
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

[ $status, $body ] = callDev( $appData, \Nino\Admin\ElementTypes::class, 'apiCreate', [
	'uri' => 'gallery', 'title' => 'Gallery', 'autoincrement' => true,
	'model' => [ 'caption' => [ 'type' => 'string' ] ],
] );
check( 'a type can be created numbered', $status === 200 && ( $body['autoincrement'] ?? null ) === true );

[ , $body ] = callDev( $appData, \Nino\Admin\ElementTypes::class, 'apiGet', [ 'uri' => 'gallery' ] );
check( 'apiGet reports that this type numbers its elements', ( $body['autoincrement'] ?? null ) === true );
check( '...and names the uri the next element would get', ( $body['next'] ?? null ) === '00001' );

// The element form posts no uri at all for such a type - that is the request
// to be numbered, and it is the kernel that decides which number
[ $status, $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'caption' => 'One' ] ] );
check( 'saving with no uri allocates the first number', $status === 200 && ( $body['element']['.uri'] ?? null ) === '/gallery/00001' );
check( '...and reports the next one back, so the form\'s promise stays true', ( $body['nextUri'] ?? null ) === '00002' );

[ , $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'caption' => 'Two' ] ] );
check( 'the next save gets the next number', ( $body['element']['.uri'] ?? null ) === '/gallery/00002' );

[ , $listBody ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiList', [ 'type' => 'gallery' ] );
check( 'both numbered elements are listed', array_column( $listBody['elements'], 'uri' ) === [ '00001', '00002' ] );

[ , $typesBody ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiTypes' );
$galleryEntry = array_column( $typesBody['types'], null, 'type' )['gallery'] ?? [];
check( 'the element form is told which types are numbered', ( $galleryEntry['autoincrement'] ?? null ) === true );
check( '...and what the next uri would be, so it can show it', ( $galleryEntry['nextUri'] ?? null ) === '00003' );

// An update names its element like any other - only the insert is uri-less
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '00001', 'locale' => 'de_DE', 'isNew' => false, 'fields' => [ 'caption' => 'Edited' ] ] );
check( 'a numbered element updates under the uri it was given', $status === 200 );
check( '...and the edit actually landed', ( \Nino\Elements::getElement( $appData, '/gallery/00001', 'de_DE' )['caption'] ?? null ) === 'Edited' );

// The same request against a type that names its own elements stays a 400 -
// numbering is a property of the type, not a way to skip validation
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'x' ] ] );
check( 'an empty uri is still rejected for a type that is not numbered', $status === 400 );

// Turning numbering on later must not be able to collide with an element the
// type already has - including one that happens to look like a number
callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '00007', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'seven' ] ] );
[ , $body ] = callDev( $appData, \Nino\Admin\ElementTypes::class, 'apiSave', [
	'uri' => 'labeltype', 'title' => 'Label Type', 'autoincrement' => true,
	'model' => [ 'name' => [ 'type' => 'string' ], 'label' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ] );
check( 'numbering can be switched on for an existing type', ( $body['autoincrement'] ?? null ) === true );

[ , $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'eight' ] ] );
$seeded = $body['element']['.uri'] ?? null;
check( 'the counter starts past the highest number already in the type', $seeded === '/labeltype/00008' );
check( 'the elements that were named by hand keep their uris',
	( \Nino\Elements::getElement( $appData, '/labeltype/titled', 'de_DE' )['title'] ?? null ) === 'A title' );

// Switching it back off returns the type to being named by hand
[ , $body ] = callDev( $appData, \Nino\Admin\ElementTypes::class, 'apiSave', [
	'uri' => 'labeltype', 'title' => 'Label Type', 'autoincrement' => false,
	'model' => [ 'name' => [ 'type' => 'string' ], 'label' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ] );
check( 'numbering can be switched off again', ( $body['autoincrement'] ?? null ) === false );
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'x' ] ] );
check( '...and an uri-less save is refused again', $status === 400 );

[ $status, $body ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'first' ] );
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
		],
	],
	'en_US' => [
		'item' => [
			'title' => 'Old project',
			'description' => '<code>Old</code>',
			'features' => [ 'Old' ],
			'image' => 'english.webp',
			'related' => '/brandnewtype/en',
		],
	],
] );
unset( $appData['./nino/elements/cache'] );

[ $status, $info ] = callDev( $appData, \Nino\Admin\Translations::class, 'apiInfo' );
check( 'apiInfo succeeds and fixes the source to the native locale', $status === 200 && $info['nativeLocale'] === 'de_DE' );
check( 'apiInfo offers every configured import target', $info['locales'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

$_POST['action'] = 'translations/info';
$_POST['data'] = '{}';
$translationDispatch = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $translationDispatch );
check( 'Translations is registered in Admin\'s central action dispatcher', $translationDispatch['/nino/http/response']['statusCode'] === 200 );

[ $status, $translation ] = callDev( $appData, \Nino\Admin\Translations::class, 'apiExport' );
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
			'unknown' => 'HACKED',
		],
		'ghost' => [ 'title' => 'HACKED' ],
	],
	'unknown-type' => [ 'ghost' => [ 'title' => 'HACKED' ] ],
];

[ $status, $result ] = callDev( $appData, \Nino\Admin\Translations::class, 'apiImport', [
	'targetLocale' => 'en_US',
	'translation' => $translation,
] );
check( 'apiImport succeeds for a configured target locale', $status === 200 && $result['targetLocale'] === 'en_US' );
check( 'apiImport reports Text values and rejected keys separately', $result['text'] === [ 'imported' => 2, 'skipped' => 3 ] );
check( 'apiImport reports Element fields and rejected paths separately', $result['elements'] === [ 'imported' => 3, 'skipped' => 6 ] );

$translatedText = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$nativeText = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'Text import preserves allowed code markup and sanitizes plain text', $translatedText['[[/translation/title]]'] === 'Home <code>Nino</code>' && $translatedText['[[/translation/plain]]'] === 'Welcome' );
check( 'Text import leaves native source, global and technical values untouched', $nativeText['[[/translation/title]]'] === '<code>Nino</code> Start' && $translatedText['[[/translation/technical]]'] === 'technical' );

$translatedElements = \Nino\Filesystem::getFileContent( $appData, '/elements/translationtype.php', [] );
check( 'Element import sanitizes plain, HTML and nested array strings', $translatedElements['en_US']['item']['title'] === 'Project' && $translatedElements['en_US']['item']['description'] === '<code>Translated</code>' && $translatedElements['en_US']['item']['features'] === [ 'One x', [ 'Deep y' ] ] );
check( 'Element import cannot overwrite global or image fields', $translatedElements['*']['item']['views'] === 7 && $translatedElements['en_US']['item']['image'] === 'english.webp' );
check( '...nor an element reference, which is a choice rather than a translation', $translatedElements['en_US']['item']['related'] === '/brandnewtype/en' );
check( 'Element import leaves the native bucket untouched', $translatedElements['de_DE']['item']['title'] === 'Projekt' );

$badTranslation = $translation;
$badTranslation['version'] = 99;
[ $status ] = callDev( $appData, \Nino\Admin\Translations::class, 'apiImport', [ 'targetLocale' => 'en_US', 'translation' => $badTranslation ] );
check( 'apiImport rejects an incompatible format version', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Admin\Translations::class, 'apiImport', [ 'targetLocale' => 'xx_XX', 'translation' => $translation ] );
check( 'apiImport rejects an unknown target locale', $status === 400 );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\Translations::class, 'apiExport' );
check( 'Translations actions require an authed _admin session', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

check( 'PageEditor keeps the stable pages tab id but labels it Routes', \Nino\Admin\PageEditor::nav() === [ 'pages', 'Routes' ] );

[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'nope' ] );
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
check( 'rawBuckets survives a type file whose top-level "title" is a plain string', \Nino\Admin\Elements::rawBuckets( $handAuthored, 'item1' ) === [ '*' => [ 'sort' => 1 ], 'de_DE' => [ 'name' => 'Hallo' ] ] );
check( 'rawBuckets never returns another element\'s buckets', \Nino\Admin\Elements::rawBuckets( $handAuthored, 'item2' ) === [ 'de_DE' => [ 'name' => 'Zwei' ] ] );
check( 'rawBuckets returns nothing for an element that does not exist', \Nino\Admin\Elements::rawBuckets( $handAuthored, 'nope' ) === [] );

[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'apiDelete succeeds', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'the deleted element is gone from every locale', $status === 404 );

// Deleting again is a no-op that still reports success: the kernel's
// deleteElement() unsets whatever is there and reports the write, it does not
// treat "already gone" as an error. Identical to _editor's own apiDelete -
// asserted here so a future change to either side has to be deliberate
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'deleting an already-deleted element is an idempotent no-op, not an error', $status === 200 );

[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiDelete', [ 'type' => 'nope', 'uri' => 'first' ] );
check( 'apiDelete 400s for an unknown type', $status === 400 );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
[ $status ] = callDev( $appData, \Nino\Admin\Elements::class, 'apiTypes' );
check( 'Elements actions require an authed _admin session too', $status === 401 );
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

// Every action this module registers must actually resolve through the
// dispatcher - a typo in the action map would otherwise only surface in the ui
$dispatchable = true;
foreach( array_keys( \Nino\Admin\Elements::actions() ) as $actionName ) {
	$_POST['action'] = $actionName;
	$_POST['data']	 = json_encode( [ 'type' => 'contenttype', 'uri' => 'first' ] );
	$dispatchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\Admin::handlePost( $appData, $dispatchRequest );
	if( $dispatchRequest['/nino/http/response']['statusCode'] === 404 && ( $dispatchRequest['/nino/http/response']['body']['error'] ?? '' ) === 'unknown action' )
		$dispatchable = false;
}
check( 'every develements/* action is reachable through Admin::handlePost', $dispatchable === true );

echo "\n";


// --- Final: unauthed guard sanity check across every module -------------

echo "Every module rejects an unauthed request\n";

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
$unauthedListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Restore::apiList( $appData, $unauthedListRequest );
check( 'apiList requires an authed _admin session, same as every other module', $unauthedListRequest['/nino/http/response']['statusCode'] === 401 );

echo "\n";

echo "$checks checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
