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
check( '...rendered as the tab pane alone', str_contains( \Nino\Admin\Admin::panesHtml( $appData ), '<div id="admin-content-elements" data-panel="elements" data-layout="page" hidden><div id="admin-tab-types" data-tab="types" hidden><div id="types-list"></div><div id="types-form"></div></div></div>' ) === true );
\Nino\Auth::deleteUser( $appData, 'typesonly@example.com' );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$allowedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $allowedRequest );
check( 'and 200 for full access', $allowedRequest['/nino/http/response']['statusCode'] === 200 );
\Nino\Auth::deleteUser( $appData, 'editor@example.com' );

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
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80 ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'suffix' => 'ignored on image', 'required' => true ],
		'price' 	=> [ 'type' => 'double', 'suffix' => '€' ],
		'active' 	=> [ 'type' => 'boolean', 'suffix' => 'ignored on boolean' ],
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

check( '"element" is offered as a field type by apiList', in_array( 'element', \Nino\Modules\Elements\Types::FIELD_TYPES, true ) === true );

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

// Restore has to work with /_admin deleted - that is the whole point of it
// reading Backup's output rather than calling into it, and this file loads
// Editor.php for its own fixtures, so only a subprocess can prove it. A
// plain \Nino\Modules\Backups:: reference in Restore would pass every check
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
	"dates"					=> \Nino\Modules\Backups\Admin::dates( $appData ),
] );
' );

$standalone = json_decode( (string) shell_exec( 'php '. escapeshellarg( $standaloneDriver ). ' 2>/dev/null' ), true ) ?? [];

check( 'Restore runs without _admin/Admin.php loaded at all', ( $standalone['editorLoaded'] ?? true ) === false );
check( '...and still finds the archives from there', in_array( date( 'Y-m-d' ), $standalone['dates'] ?? [], true ) === true );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiList( $appData, $listRequest );
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
\Nino\Modules\Backups\Admin::apiRestore( $appData, $restoreRequest );
check( 'apiRestore reports success', ( $restoreRequest['/nino/http/response']['body']['ok'] ?? false ) === true );

$afterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the wrecked user record is back after restore', isset( $afterRestore['/nino/auth/user']['admin@example.com'] ) === true );

$backupDir = $sandbox. '/private/.backups';
check( 'a pre-restore safety snapshot of the (corrupted) state was made first', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 1 );

$_POST['data'] = json_encode( [ 'date' => '2020-01-01' ] );
$unknownDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $unknownDateRequest );
check( 'restoring a date with no matching backup 404s', $unknownDateRequest['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'date' => 'not-a-date' ] );
$badDateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiRestore( $appData, $badDateRequest );
check( 'restoring a malformed date is rejected before touching the filesystem', $badDateRequest['/nino/http/response']['statusCode'] === 400 );

echo "\n";


// --- Dev\Restore - newsletter restore merges instead of overwriting -------

echo "Restore - a module merges its own files on restore (Newsletter, Art. 17)\n";

// The merge is the Newsletter module's own, reached from Restore through
// the '/nino/admin/restore' callback it registers - exercised here directly
// rather than through a real two-backup apiRestore() round trip:
// Backup::maybeRun() only ever creates one backup per calendar day, so a
// sandboxed test run can't produce an "older backup" and a "current,
// since-changed state" the way a real installation would days apart
$mergeInvoke = static function( string $dataDir, string $staging ) use ( &$appData ): void {
	$args = [ 'dataDir' => $dataDir, 'staging' => $staging ];
	\Nino\Modules\Newsletter::callbackRestore( $appData, $args );
};

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

$mergeInvoke( $mergeRoot. '/data', $mergeStaging );

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

$mergeInvoke( $mergeRoot2, $mergeStaging2 );
check( 'a backup with no newsletter files at all is a no-op, not an error', is_file( $mergeStaging2. '/data/newsletter.php' ) === false );

// Restore itself carries no newsletter knowledge any more - the module
// registers the merge in its init(), so a project without the module has
// nothing to merge and Restore has nothing to know
$restoreSource = file_get_contents( __DIR__. '/../_admin/Nino/Modules/Backups/Admin/Admin.php' );
check( 'Restore fires the module callback instead of naming the newsletter', str_contains( $restoreSource, "'/nino/admin/restore'" ) === true && str_contains( $restoreSource, 'newsletter' ) === false );
$initProbe = [ '/nino/modules' => [] ];
\Nino\Modules\Newsletter::init( $initProbe );
check( 'the Newsletter module registers itself on /nino/admin/restore', isset( $initProbe['./nino/callbacks']['/nino/admin/restore'] ) === true );

\Nino\Filesystem::removeDir( $mergeRoot2 );
\Nino\Filesystem::removeDir( $mergeStaging2 );

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
	'/nino/error/log', '/nino/error/display', '/nino/session/force-secure-cookie',
	'/nino/admin/backups', '/nino/admin/logs',
	'/nino/cache/status', '/nino/cache/ttl', '/nino/cache/blacklist',
] );
check( 'every field carries the type its editor renders from', array_column( $body['fields'], 'type' ) === [
	'bool', 'bool', 'bool', 'bool', 'bool', 'bool', 'int', 'lines',
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
[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale refuses to overwrite an existing translation', $status === 200 && ( $body['created'] ?? null ) === false );
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

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => 'yes please' ] ] );
check( 'apiSave rejects a bool that is neither', $status === 400 );

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

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 0 ] ] );
check( 'Lockout::apiSave rejects an int below its minimum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/cooldown' => 999999999 ] ] );
check( 'Lockout::apiSave rejects an int above its maximum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 60 ] ] );
check( 'Lockout::apiSave knows its two keys and nothing else', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 3 ] ] );
check( 'and Config no longer writes them', $status === 400 && $appData['/nino/auth/maxtries'] === 8 );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 3 ] ] );
check( 'Lockout actions require an authed _admin session too', $status === 401 );
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

[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the new slot', in_array( '/home/hero', array_column( $body['slots'], 'uri' ), true ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu', 'width' => '800', 'height' => '400' ] );
check( 'apiSave edits an existing slot', $status === 200 );
check( 'the slot metadata actually changed', $appData['/nino/html/images']['/home/hero']['label'] === 'Hero neu' && $appData['/nino/html/images']['/home/hero']['width'] === 800 );

$appData['/nino/html/images']['/home/hero']['filename'] = 'elements/home/hero.800x400.jpg';
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave never touches an existing filename', $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg' );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/does/not/exist', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiSave 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'the slot is gone from appData', isset( $appData['/nino/html/images']['/home/hero'] ) === false );
$imagesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [];
check( 'the slot is gone from config.php too', isset( $imagesAfterDelete['/home/hero'] ) === false );

echo "\n";


// --- Dev\Text ---------------------------------------------------------------

echo "Text - text key schema (existence, global/per-locale, blacklist)\n";

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/home/welcome/subtitle', 'global' => false, 'value' => 'Start' ] );
check( 'apiCreate creates a per-locale key', $status === 200 );

$deDE = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUS = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the initial value is written into every available locale', $deDE['[[/home/welcome/subtitle]]'] === 'Start' && $enUS['[[/home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/company/tagline', 'global' => true, 'value' => 'Immer' ] );
check( 'apiCreate creates a global key', $status === 200 );

$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the initial value is written into global.php, not any locale file', $global['[[/company/tagline]]'] === 'Immer' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => 'not-a-valid-key', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects an invalid key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/company/tagline', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects a key that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
$subtitleEntry = null;
foreach( $body['keys'] as $entry ) if( $entry['key'] === '/home/welcome/subtitle' ) $subtitleEntry = $entry;
check( 'apiList finds the new per-locale key, not blacklisted by default', $subtitleEntry !== null && $subtitleEntry['global'] === false && $subtitleEntry['blacklisted'] === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/home/welcome/subtitle', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave converts a per-locale key to global', $status === 200 );

$deDEAfter = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'converting to global removes it from every locale file', isset( $deDEAfter['[[/home/welcome/subtitle]]'] ) === false );
check( 'converting to global migrates the value instead of discarding it', $globalAfter['[[/home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/home/welcome/subtitle', 'global' => false, 'blacklisted' => true ] );
check( 'apiSave converts back to per-locale and blacklists it in the same call', $status === 200 );

$deDEAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklist = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'converting back to per-locale migrates the value into every locale', $deDEAfter2['[[/home/welcome/subtitle]]'] === 'Start' );
check( 'converting back to per-locale removes it from global.php', isset( $globalAfter2['[[/home/welcome/subtitle]]'] ) === false );
check( 'the key is now blacklisted', in_array( '/home/welcome/subtitle', $blacklist, true ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/does/not/exist', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave 404s for an unknown key', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
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
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/company/note', 'global' => true, 'value' => '<strong>Wichtig</strong>' ] );
check( 'apiCreate creates the html-flagged fixture key', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/note', 'locale' => '*', 'value' => '<script>alert(1)</script><strong>Wichtig</strong><em>auch</em><code>x()</code>' ],
] ] );
check( 'apiSaveBatch sanitizes html and preserves inline code', $body['results']['/company/note']['value'] === '<strong>Wichtig</strong><em>auch</em><code>x()</code>' );

// A plain-text value is substituted raw by Html::_renderFills(), attribute
// values included ('<a href="[[/company/facebook]]">'), so a stored quote is
// an attribute break-out that strip_tags() never sees. Entities render as the
// character itself in both contexts, and re-encode to themselves on a re-save
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'x" onmouseover="alert(1)' ],
] ] );
check( 'apiSaveBatch entity-encodes quotes in a plain-text value', $body['results']['/company/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'x&quot; onmouseover=&quot;alert(1)' ],
] ] );
check( '...and saving that value again does not escape it a second time', $body['results']['/company/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/company/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
] ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/does/not/exist', 'locale' => '*', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch reports an unknown key without failing the whole request', $status === 200 && $body['results']['/does/not/exist']['ok'] === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/home/welcome/subtitle', 'locale' => 'xx_XX', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch rejects an invalid locale for a per-locale key', $body['results']['/home/welcome/subtitle']['ok'] === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/company/tagline', 'newKey' => '/company/motto' ] );
check( 'apiRename succeeds for a global key', $status === 200 );

$globalAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the old key is gone from global.php', isset( $globalAfterRename['[[/company/tagline]]'] ) === false );
check( 'the value moved to the new key', $globalAfterRename['[[/company/motto]]'] === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => 'not-a-valid-key' ] );
check( 'apiRename rejects an invalid new key', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => '/company/note' ] );
check( 'apiRename rejects a new key that already exists', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/does/not/exist', 'newKey' => '/also/new' ] );
check( 'apiRename 404s for an unknown key', $status === 404 );

// Renaming a key to the name it already has is a no-op, not a delete. The
// mutate pair below it ("write the new bracket, unset the old one") collapses
// into a plain unset when both are the same string, so this used to answer 200
// and drop the value - text.js guards it in the ui, the endpoint has to too
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/company/motto', 'newKey' => '/company/motto' ] );
check( 'apiRename accepts a rename to the key\'s own name', $status === 200 );
check( '...and leaves the value where it was instead of deleting it', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/company/motto]]'] ?? null ) === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/company/note' ] );
check( 'apiDelete succeeds for a global key', $status === 200 );

$globalAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the deleted global key is gone', isset( $globalAfterDelete['[[/company/note]]'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/home/welcome/subtitle' ] );
check( 'apiDelete succeeds for a blacklisted per-locale key', $status === 200 );

$deDEAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUSAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$blacklistAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the deleted per-locale key is gone from every locale file', isset( $deDEAfterDelete['[[/home/welcome/subtitle]]'] ) === false && isset( $enUSAfterDelete['[[/home/welcome/subtitle]]'] ) === false );
check( 'the deleted key is also gone from the blacklist', in_array( '/home/welcome/subtitle', $blacklistAfterDelete, true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/does/not/exist' ] );
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

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );

$missingKeys = array_column( $body['missing'], 'key' );
check( 'a genuinely undefined key is reported', in_array( '/page-scan-test/heading', $missingKeys, true ) === true );
check( 'an undefined key found twice in the same file is only reported once', count( array_filter( $missingKeys, fn( $k ) => $k === '/page-scan-test/heading' ) ) === 1 );
check( 'an already-defined key is not reported', in_array( '/company/name', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/http/response/uri fill is never reported, despite appearing inside a nested [[...]] construct', in_array( '/nino/http/response/uri', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/public fill is never reported as missing', in_array( '/nino/public', $missingKeys, true ) === false );

unlink( $sandbox. '/private/templates/scan-fixture.tpl' );
unlink( $sandbox. '/private/templates/scan-fixture-2.tpl' );

// --- apiScanApply: the three answers a scanned key can get -----------------
//
// The scan is a list to work through, not an all-or-nothing pass: a row with
// a value becomes a key, a row left empty comes back next time, and a row
// ticked "ignore" is retired for good. All three in one request, so a long
// list can be answered in sittings.

file_put_contents( $sandbox. '/private/templates/scan-apply-fixture.tpl',
	'<h1>[[/page-apply/heading]]</h1><p>[[/page-apply/subtitle]]</p><small>[[/page-apply/legal]]</small>' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [
	[ 'key' => '/page-apply/heading', 	'value' => '  Willkommen  ', 	'ignore' => false ],
	[ 'key' => '/page-apply/subtitle', 'value' => '', 							'ignore' => false ],
	[ 'key' => '/page-apply/legal', 		'value' => '', 							'ignore' => true 	],
	// Real and defined, so not part of this scan at all - the form's rows come
	// from apiScan() and nowhere else, and this endpoint is not a way to
	// blacklist a key the screen never offered
	[ 'key' => '/company/name', 				'value' => '', 							'ignore' => true 	],
] ] );
check( 'apiScanApply succeeds', $status === 200 );
check( 'a row with a value is counted as created', ( $body['created'] ?? null ) === 1 );
check( 'a row ticked "ignore" is counted as ignored', ( $body['ignored'] ?? null ) === 1 );
check( 'a row left empty is counted as left for later', ( $body['skipped'] ?? null ) === 1 );

$deApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enApply = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the created key holds its starting value in every language', ( $deApply['[[/page-apply/heading]]'] ?? null ) === 'Willkommen' && ( $enApply['[[/page-apply/heading]]'] ?? null ) === 'Willkommen' );
// A field holding nothing but spaces is the same "not decided yet" an empty
// one is, and a key created from it would look filled in without being it
check( 'the starting value is trimmed rather than stored as typed', str_contains( (string) ( $deApply['[[/page-apply/heading]]'] ?? '' ), ' ' ) === false );
check( 'a row left empty writes nothing at all', isset( $deApply['[[/page-apply/subtitle]]'] ) === false && isset( $enApply['[[/page-apply/subtitle]]'] ) === false );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the retired key is in the blacklist', in_array( '/page-apply/legal', $blacklistAfterApply, true ) === true );
check( '...and a key this scan never offered is not, whatever the request said', in_array( '/company/name', $blacklistAfterApply, true ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
$missingAfterApply = array_column( $body['missing'], 'key' );
check( 'the created key is no longer missing', in_array( '/page-apply/heading', $missingAfterApply, true ) === false );
// The whole point of "permanently": before this, an ignored key was simply
// skipped client-side and came straight back on the next scan
check( 'the retired key is no longer asked about', in_array( '/page-apply/legal', $missingAfterApply, true ) === false );
check( 'the key left for later comes back', in_array( '/page-apply/subtitle', $missingAfterApply, true ) === true );

// Retiring has to be reversible, or "permanently" is a one-way door with the
// filesystem as its only way out. The key has no value anywhere, so
// \Nino\Text::entries() cannot see it - the Text Keys list merges it back in
[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
$listedKeys = array_column( $body['keys'], 'key' );
check( 'a retired key with no value is still listed by the Text Keys tab', in_array( '/page-apply/legal', $listedKeys, true ) === true );
check( '...flagged as hidden, so the checkbox that brings it back is ticked', ( array_column( $body['keys'], 'blacklisted', 'key' )['/page-apply/legal'] ?? null ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/page-apply/legal', 'global' => false, 'blacklisted' => false ] );
check( 'un-ticking "hidden" on it is a valid save, not a 404', $status === 200 );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( '...and the scan offers it again afterwards', in_array( '/page-apply/legal', array_column( $body['missing'], 'key' ), true ) === true );

// Same key, deleted rather than un-ignored: there is nothing but the
// blacklist line to remove, and removing it is the whole deletion
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [ [ 'key' => '/page-apply/legal', 'value' => '', 'ignore' => true ] ] ] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/page-apply/legal' ] );
check( 'a retired key can be deleted outright too', $status === 200 );
check( '...which only removes its blacklist line', in_array( '/page-apply/legal', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === false );

// Renaming one must move the blacklist line, not write the empty key into
// every locale file - which is what the value-carrying path below it would do
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [ [ 'key' => '/page-apply/legal', 'value' => '', 'ignore' => true ] ] ] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/page-apply/legal', 'newKey' => '/page-apply/imprint' ] );
$blacklistAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'renaming a retired key moves its blacklist line', $status === 200 && in_array( '/page-apply/imprint', $blacklistAfterRename, true ) === true && in_array( '/page-apply/legal', $blacklistAfterRename, true ) === false );
check( '...without creating the empty key in any locale file', isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/page-apply/imprint]]'] ) === false );

// The Dashboard tile counts the same scan (see missingCount()), so a retired
// key has to leave the tile along with the list
\Nino\Text::setBlacklisted( $appData, '/page-apply/imprint', false );
$countBeforeRetiring = \Nino\Modules\Text\Keys::missingCount( $appData );
\Nino\Text::setBlacklisted( $appData, '/page-apply/legal', true );
check( 'retiring a key takes it out of the Dashboard count too', \Nino\Modules\Text\Keys::missingCount( $appData ) === $countBeforeRetiring - 1 );
\Nino\Text::setBlacklisted( $appData, '/page-apply/legal', false );

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
check( 'writes the page\'s own de_DE meta, keyed by its Element-URI', $deAfterSave['[[/webpage/site-about/name]]'] === 'Über uns' && $deAfterSave['[[/webpage/site-about/title]]'] === 'Über uns' );

$enAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a locale left entirely unposted still gets the generic placeholder, not left unset', $enAfterSave['[[/webpage/site-about/name]]'] === 'Page' );

$globalAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'writes the page\'s reachable Http-URI as one global fill a template can link to by name', ( $globalAfterSave['[[/webpage/site-about/uri]]'] ?? null ) === '/about'
	&& isset( $deAfterSave['[[/webpage/site-about/uri]]'], $enAfterSave['[[/webpage/site-about/uri]]'] ) === false );
check( 'that uri is blacklisted as a technical value, like every other route key', in_array( '/webpage/site-about/uri', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) );

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
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'statusCode' => 404, 'navs' => [ 'main' ], 'text' => [
		'de_DE' => [ 'name' => 'Kontakt' ],
	],
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
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'navs' => [ 'main' ], 'text' => [
		'de_DE' => [ 'name' => 'About' ],
	],
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
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/ueber-uns', 'template' => 'page-about', 'text' => [
		'de_DE' => [ 'name' => 'Über uns' ],
	],
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
check( 'the deleted entry\'s own text meta is left in place - additive-only, never auto-deleted', isset( $deAfterDelete['[[/webpage/site-about/name]]'] ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/does-not-exist' ] );
check( 'apiDelete 404s for an unknown httpUri', $status === 404 );

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
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ),
	[ '[[/webpage/home/name]]' => 'Start', '[[/webpage/contact/name]]' => 'Kontakt', '[[/webpage/legal/name]]' => 'Impressum' ]
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
check( 'labels a route by the /webpage<uri>/name key the menu would render', $body['routes'][0]['label'] === 'Start' );
check( '...and falls back to the path for one nobody named', $body['routes'][1]['label'] === '/robots.txt' );
check( 'a route with no name is reported as such - routeLines() would skip it', $body['routes'][1]['named'] === false && $body['routes'][0]['named'] === true );

// Assign: joins at the end, never in the middle
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiAssign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'apiAssign succeeds', $status === 200 );
check( 'the new entry joins at the end', $entriesOf( $body, 'main' ) === [ '/', '/contact', '/legal' ] );

$routesAfterAssign = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'membership is written onto the route itself, densely numbered', [
	$routesAfterAssign['GET://']['navs'], $routesAfterAssign['GET://contact']['navs'], $routesAfterAssign['GET://legal']['navs'],
] === [ [ 'main' => 1 ], [ 'main' => 2 ], [ 'main' => 3 ] ] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiAssign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'assigning the same route twice 409s', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiAssign', [ 'key' => 'nope', 'httpUri' => '/legal' ] );
check( 'assigning into an unknown menu 404s', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiAssign', [ 'key' => 'main', 'httpUri' => '/does-not-exist' ] );
check( 'assigning an unknown route 404s', $status === 404 );

// Move
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/legal', 'direction' => 'up' ] );
check( 'apiMove succeeds', $status === 200 );
check( 'the two entries swapped places', $entriesOf( $body, 'main' ) === [ '/', '/legal', '/contact' ] );

$routesAfterMove = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'the swap is a swap of two priorities, still dense', [
	$routesAfterMove['GET://']['navs'], $routesAfterMove['GET://legal']['navs'], $routesAfterMove['GET://contact']['navs'],
] === [ [ 'main' => 1 ], [ 'main' => 2 ], [ 'main' => 3 ] ] );
check( 'the route array itself is not reordered by a menu move', array_keys( $routesAfterMove ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/', 'direction' => 'up' ] );
check( 'moving the first entry up 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/contact', 'direction' => 'sideways' ] );
check( 'an invalid direction 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiMove', [ 'key' => 'main', 'httpUri' => '/robots.txt', 'direction' => 'up' ] );
check( 'moving a route that is not in this menu 404s', $status === 404 );

// Unassign: closes the gap it leaves
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiUnassign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'apiUnassign succeeds', $status === 200 );
check( 'the entry is gone from the menu', $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );

$routesAfterUnassign = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'the gap it left is closed right away', [ $routesAfterUnassign['GET://']['navs'], $routesAfterUnassign['GET://contact']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );
check( 'a route in no menu at all carries no "navs" key rather than an empty one', isset( $routesAfterUnassign['GET://legal']['navs'] ) === false );
check( 'the route itself survives losing its membership', isset( $routesAfterUnassign['GET://legal'] ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiUnassign', [ 'key' => 'main', 'httpUri' => '/legal' ] );
check( 'unassigning a route that is not in the menu 404s', $status === 404 );

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

unset( $appData['/nino/html/navs'] );
check( 'a missing navigation registry exposes no implicit menu', \Nino\Modules\Navigation\Admin::registry( $appData ) === [] );
$appData['/nino/html/navs'] = [];
check( 'an empty navigation registry stays empty', \Nino\Modules\Navigation\Admin::registry( $appData ) === [] );

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
	public static function adminPanels( array &$appData ): array { return [ 'AdminSmokeDummyPanel', 'AdminSmokeShadowPanel' ]; }
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

$withModule = $appData;
$withModule['/nino/modules'] = [ 'AdminSmokeDummyModule', '\\Nino\\Modules\\Navigation' ];

$registry = \Nino\Admin\Admin::panels( $withModule );
$order 		= array_keys( $registry );
check( 'the tool\'s own panels are all there, content first, then structure, then system, from nav() alone', array_values( array_diff( $order, [ 'dummy', 'navs' ] ) ) === [ 'dashboard', 'elements', 'text', 'images', 'logs', 'routes', 'users', 'language', 'backups', 'config' ] );
check( 'the module panel sits where its weight puts it - in the content group, after images (40), before logs (90)', array_search( 'dummy', $order, true ) === array_search( 'images', $order, true ) + 1 && array_search( 'logs', $order, true ) === array_search( 'dummy', $order, true ) + 1 );
check( 'the tool\'s own tabs sit on their panes', array_map( static fn( array $p ): array => array_keys( $p['tabs'] ), array_intersect_key( $registry, array_flip( [ 'elements', 'text', 'images', 'users', 'language' ] ) ) ) === [ 'elements' => [ 'types' ], 'text' => [ 'keys' ], 'images' => [ 'slots' ], 'users' => [ 'roles', 'lockout' ], 'language' => [ 'translations' ] ] );
check( 'the Navigations panel names the structure group and sits after routes (20)', array_search( 'navs', $order, true ) === array_search( 'routes', $order, true ) + 1 );
check( 'the Navigations panel is the Navigation module\'s and comes with it', $registry['navs']['class'] === \Nino\Modules\Navigation\Admin::class );
check( 'a panel reusing a core uri is dropped, the core panel keeps it', $registry['config']['class'] === \Nino\Modules\Config\Admin::class );

$navHtml = \Nino\Admin\Panels::navHtml( $registry );
check( 'nav() is rendered - every panel has its link, the module\'s included, an icon or its initial beside the label', str_contains( $navHtml, 'id="admin-nav-dummy" data-panel="dummy" data-layout="page"><span class="nino-admin-nav-icon" aria-hidden="true"><b>D</b></span><span class="nino-admin-nav-label">Dummy</span></a>' ) === true && str_contains( $navHtml, 'id="admin-nav-navs" data-panel="navs" data-layout="page">' ) === true && str_contains( $navHtml, '<span class="nino-admin-nav-label">[[/_admin/nav/navs]]</span></a>' ) === true );
check( 'every label is a fill - structure and system panels the same as the content ones, so the rail speaks one language', str_contains( $navHtml, '>[[/_admin/nav/routes]]</span></a>' ) === true && str_contains( $navHtml, '>[[/_admin/nav/backups]]</span></a>' ) === true && str_contains( $navHtml, '>[[/_admin/nav/text]]</span></a>' ) === true && str_contains( $navHtml, '>[[/_admin/nav/user]]</span></a>' ) === true );
check( 'the three groups get their headings, in order', preg_match( '/nav-group" data-group="content".*data-group="structure".*data-group="system"/s', $navHtml ) === 1 );

$panesHtml = \Nino\Admin\Panels::panesHtml( $registry );
check( 'every pane carries the mount points its script renders into', str_contains( $panesHtml, '<div id="admin-content-navs" data-panel="navs" data-layout="page" hidden><div id="navs-list"></div><div id="navs-form"></div></div>' ) === true );
check( 'a panel without panes() gets the conventional <uri>-list mount', str_contains( $panesHtml, '<div id="admin-content-dummy" data-panel="dummy" data-layout="page" hidden><div id="dummy-list"></div></div>' ) === true );
check( 'a pane with tabs carries the shared tab bar, its own screen first, and a tab pane per tab', str_contains( $panesHtml, '<div id="admin-content-language" data-panel="language" data-layout="page" hidden><div class="nino-admin-tabs nino-admin-tabs--bar admin-panel-tabs" role="tablist"><button type="button" role="tab" class="nino-admin-tab" data-tab="language" aria-selected="false">[[/_admin/nav/languages]]</button><button type="button" role="tab" class="nino-admin-tab" data-tab="translations" aria-selected="false">[[/_admin/nav/translations]]</button></div><div id="admin-tab-language" data-tab="language" hidden><div id="language-form"></div></div><div id="admin-tab-translations" data-tab="translations" hidden><div id="translations-content"></div></div></div>' ) === true );

// Every file a shipped panel names has to be there, and every mount id it
// answers has to be one its script renders into - a renamed script or a
// pane spelled differently on the two sides is a panel whose tab opens on
// nothing, with no error anywhere (the bundler skips a missing file)
$shipped = $appData;
$shipped['/nino/modules'] = array_merge( \Nino\AppData::DEFAULTS['/nino/modules'], [ '\\Nino\\Modules\\Form', '\\Nino\\Modules\\Newsletter', '\\Nino\\Modules\\Navigation', '\\Nino\\Modules\\Search', '\\Nino\\Modules\\Design', '\\Nino\\Modules\\Templates' ] );
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
	// the app modules are where a whole .tpl is rendered into a pane, and a
	// registry without them would leave exactly those files unchecked
	$render['/nino/modules'] = array_values( array_filter( array_map(
		static function( string $dir ): string {
			$class = '\\Nino\\Modules\\'. basename( $dir );
			return class_exists( $class ) === true && method_exists( $class, 'adminPanels' ) === true ? $class : '';
		},
		glob( dirname( __DIR__ ). '/app/Nino/Modules/*', GLOB_ONLYDIR ) ?: []
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
	}

	// The registry has to actually contain the app panels, or this whole check
	// silently proves nothing about the files it was written for
	if( $locale === 'en_US' )
		check( 'the render check covers the runtime modules\' panels too', isset( \Nino\Admin\Admin::allPanels( $render )['templates'] ) === true && isset( \Nino\Admin\Admin::allPanels( $render )['design'] ) === true );

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

$getRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => '[template /_admin/templates/page-index]' ] ];
\Nino\Admin\Admin::handleGet( $withModule, $getRequest );
\Nino\Admin\Admin::init( $withModule );
check( 'init bundles every panel script, the module\'s included, after the shell\'s own', in_array( '/app/Dummy/assets/admin.js', $withModule['/nino/html/assets']['/_admin/.cache/script.js'], true ) === true && in_array( '/app/Nino/Modules/Navigation/assets/admin.js', $withModule['/nino/html/assets']['/_admin/.cache/script.js'], true ) === true && $withModule['/nino/html/assets']['/_admin/.cache/script.js'][0] === '/_nino/Nino.js' );
check( 'and every panel stylesheet', in_array( '/app/Dummy/assets/admin.css', $withModule['/nino/html/assets']['/_admin/.cache/style.css'], true ) === true );
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
$_POST['action'] = 'search/createindex';
$withSearch = $appData;
$withSearch['/nino/modules'] = [ '\\Nino\\Modules\\Search' ];
\Nino\Admin\Admin::handlePost( $withSearch, $request );
check( 'the search-index rebuild is the Search module\'s own action, reachable while it is active', $request['/nino/http/response']['statusCode'] === 200 );
check( 'and Config no longer carries it', isset( \Nino\Modules\Config\Admin::actions()['config/searchindex'] ) === false );

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
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/scoped/one', 'global' => true, 'value' => 'Eins' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/unscoped/one', 'global' => true, 'value' => 'Zwei' ] );

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

// The shell asks the class that ran an action for its line (see
// Admin::_logAction()): a line on any other class is one nobody reads, which
// is how the whole Templates panel logged nothing. So: the handler of every
// one of these actions is the class asked, and it answers
$described = [
	[ \Nino\Modules\Templates\Documents::class,		'documents/save',			[ 'name' => 'page-x' ],						'page-x' ],
	[ \Nino\Modules\Templates\Documents::class,		'documents/delete',		[ 'name' => 'page-x' ],						'page-x' ],
	[ \Nino\Modules\Templates\Content::class,			'content/type-create',	[ 'uri' => '/cards' ],							'/cards' ],
	[ \Nino\Modules\Templates\Content::class,			'content/image-create',	[ 'uri' => '/page-x/hero/background' ],	'/page-x/hero/background' ],
	[ \Nino\Modules\Routes\Admin::class,						'routes/save',				[ 'httpUri' => '/x' ],							'/x' ],
	[ \Nino\Modules\Routes\Admin::class,						'routes/delete',			[ 'httpUri' => '/x' ],							'/x' ],
	[ \Nino\Modules\Config\Admin::class,						'config/save',				[ 'fields' => [ '/nino/cache/ttl' => 1 ] ],	'/nino/cache/ttl' ],
	[ \Nino\Modules\Images\Slots::class,						'slots/delete',				[ 'uri' => '/home/hero' ],					'/home/hero' ],
	[ \Nino\Modules\Language\Translations::class,	'translations/import',	[ 'targetLocale' => 'fr_FR' ],			'fr_FR' ],
	[ \Nino\Modules\Backups\Admin::class,					'backups/restore',		[ 'date' => '2026-09-05' ],				'2026-09-05' ],
	[ \Nino\Modules\Navigation\Admin::class,				'navs/delete',				[ 'key' => 'main' ],								'main' ],
	[ \Nino\Modules\Search\Admin::class,						'search/createindex',	[],															'Index' ],
];
$silent = [];
foreach( $described as [ $class, $action, $data, $needle ] ) {
	$handler = $class::actions()[$action][0] ?? '';
	if( $handler !== $class || method_exists( $class, 'log' ) === false || str_contains( (string) $class::log( $action, $data ), $needle ) === false )
		$silent[] = $action;
}
check( 'every one of these writes is described by the class that runs it'. ( $silent === [] ? '' : ' - silent: '. implode( ', ', $silent ) ), $silent === [] );

echo "\n";


// --- Final: unauthed guard sanity check across every module -------------

echo "Every module rejects an unauthed request\n";

\Nino\Auth::logoutUser( $appData );
$unauthedListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Backups\Admin::apiList( $appData, $unauthedListRequest );
check( 'apiList requires an authed _admin session, same as every other module', $unauthedListRequest['/nino/http/response']['statusCode'] === 401 );

echo "\n";

echo "$checks checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
