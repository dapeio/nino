<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-smoke.php		Dependency-free smoke test for the admin backend
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
mkdir( $sandbox. '/text', 0777, true );

// A key already longer than MAX_MAXLENGTH - MAXLENGTH_BUFFER, to prove the computed
// maxlength is capped at MAX_MAXLENGTH rather than growing past it
$longValue = str_repeat( 'x', 1900 );

file_put_contents( $sandbox. '/text/global.php', '<?php return [ \'[[/company/name]]\' => \'Acme\', \'[[/website/lang]]\' => \'de\' ];' );
file_put_contents( $sandbox. '/text/de_DE.php', '<?php return [ \'[[/home/h2]]\' => \'<span>Hallo</span> Welt.\', \'[[/home/plain]]\' => \'Ein Satz.\', \'[[/home/long]]\' => \''. $longValue. '\' ];' );
file_put_contents( $sandbox. '/text/en_US.php', '<?php return [ \'[[/home/h2]]\' => \'<span>Hi</span> World.\', \'[[/home/plain]]\' => \'A sentence.\' ];' );
file_put_contents( $sandbox. '/text/blacklist.php', '<?php return [ \'/website/lang\' ];' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']			= $sandbox;
// Mirrors \Nino\init()'s default (no NINO_CONFIG_DIR set): configpath falls
// back to the project root, same as this sandbox's regular path
$appData['./nino/filesystem/configpath']	= $sandbox;
$appData['/nino/dir']				= '';
$appData['/nino/locales/native']				= 'de_DE';
$appData['/nino/locales/available']			= [ 'de_DE', 'en_US' ];
$appData['/nino/auth/maxtries']					= 3;
$appData['/nino/auth/cooldown']					= 3600;
$appData['/nino/auth/user']							= [];

// '/*' - the main test account throughout this file exercises every module,
// same shape as config.php's real seeded admin (full access)
\Nino\Auth::insertUser( $appData, 'admin@example.com', 'correct horse battery staple', [ '/*' ] );
\Nino\Auth::loginUser( $appData, 'admin@example.com', 'correct horse battery staple' );

echo "Sandbox: $sandbox\n\n";


// --- Text::apiKeys ------------------------------------------------------

echo "Text::apiKeys\n";

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Text::apiKeys( $appData, $request );
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
\Nino\Admin\Elements::apiTypes( $appData, $request );
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
	\Nino\Admin\Text::apiSaveBatch( $appData, $request );
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
\Nino\Admin\Text::apiSaveBatch( $appData, $request );
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
	'value' 	=> '<strong><em>Bold Italic</em></strong> <script>alert(1)</script> <a href="javascript:alert(2)">bad</a> <a href="/ok">good</a> <img src=x onerror=alert(3)>',
] );

check( 'save succeeds', $result['ok'] === true );
check( 'nested tags collapse to a single level', ( $result['value'] ?? '' ) === '<strong>Bold Italic</strong>  bad <a href="/ok">good</a> ' );
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


// --- Text::apiExport/apiImport: translation round-trip -----------------------

echo "Text::apiExport/apiImport - translation round-trip\n";

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'locale' => 'de_DE' ] );
\Nino\Admin\Text::apiExport( $appData, $request );
check( 'export succeeds', $request['/nino/http/response']['statusCode'] === 200 );
check( 'export returns the locale file\'s own raw content', ( $request['/nino/http/response']['body']['content']['[[/home/plain]]'] ?? null ) === 'Acme Corp x' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'locale' => 'xx_XX' ] );
\Nino\Admin\Text::apiExport( $appData, $request );
check( 'export rejects an invalid locale', $request['/nino/http/response']['statusCode'] === 400 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'locale' 	=> 'en_US',
	'content' => [ '[[/home/h2]]' => 'Translated Headline', '[[/home/brand-new]]' => 'A key that never existed before' ],
] );
\Nino\Admin\Text::apiImport( $appData, $request );
check( 'import succeeds', $request['/nino/http/response']['statusCode'] === 200 );
check( 'import reports 2 imported, 0 skipped', $request['/nino/http/response']['body'] === [ 'imported' => 2, 'skipped' => 0 ] );

$enUs = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'an imported key overwrote the existing value', $enUs['[[/home/h2]]'] === 'Translated Headline' );
check( 'a brand-new key from the import was created', $enUs['[[/home/brand-new]]'] === 'A key that never existed before' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [
	'locale' 	=> 'de_DE',
	'content' => [ '[[/home/plain]]' => 'Geänderter Satz', '[[/website/lang]]' => 'HACKED' ],
] );
\Nino\Admin\Text::apiImport( $appData, $request );
check( 'import with a mix of keys still succeeds overall', $request['/nino/http/response']['statusCode'] === 200 );
check( 'a blacklisted key in the import is silently skipped, the rest still imports', $request['/nino/http/response']['body'] === [ 'imported' => 1, 'skipped' => 1 ] );

$deDe = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the blacklisted key was NOT written despite being in the import', isset( $deDe['[[/website/lang]]'] ) === false );
check( 'the non-blacklisted key from the same import was written', $deDe['[[/home/plain]]'] === 'Geänderter Satz' );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'locale' => 'de_DE', 'content' => [] ] );
\Nino\Admin\Text::apiImport( $appData, $request );
check( 'import rejects empty content', $request['/nino/http/response']['statusCode'] === 400 );

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
\Nino\Admin\Elements::apiSave( $appData, $request );
$element = $request['/nino/http/response']['body']['element'] ?? [];

check( 'element save succeeds', $request['/nino/http/response']['statusCode'] === 200 );
check( 'an html-flagged field is sanitized like a Text html key', ( $element['body'] ?? '' ) === '<strong>Bold Italic</strong> ' );
check( 'a plain field is left as-is (Elements never sanitized these before either)', ( $element['plain'] ?? '' ) === 'Acme <b>Corp</b>' );

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
\Nino\Admin\Elements::apiSave( $appData, $request );
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
\Nino\Admin\Elements::apiSave( $appData, $request );
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
function fakeUploadedFile( bool $alpha = false ): string {
	$img = imagecreatetruecolor( 300, 150 );
	if( $alpha === true ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 200, 0, 64 ) );
	} else {
		imagefill( $img, 0, 0, imagecolorallocate( $img, 0, 0, 200 ) );
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
function callUploadImage( array &$appData, array $data, bool $alpha = false ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	$path = fakeUploadedFile( $alpha );
	$_FILES['file'] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'test.jpg', 'size' => filesize( $path ) ];
	\Nino\Admin\Elements::apiUploadImage( $appData, $request );
	@unlink( $path );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

\Nino\Elements::insertElementType( $appData, '/imagedemo', [
	'photo' => [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
	'plain' => [ 'type' => 'string', 'locale' => true ],
] );
\Nino\Elements::insertElement( $appData, '/imagedemo/item1', [ 'plain' => 'x' ], 'de_DE' );

[ $status, $body ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'a valid upload for an image field succeeds', $status === 200 && is_string( $body['filename'] ?? null ) === true );

$firstFilename = $body['filename'];
check( 'the sole image field on this type needs no key/locale disambiguation in the path', $firstFilename === 'elements/imagedemo/item1.40x40.jpg' );

$uploadPath = $appData['./nino/filesystem/path']. '/images/'. $firstFilename;
check( 'the processed file actually exists on disk', is_file( $uploadPath ) === true );

$stored = \Nino\Elements::getElement( $appData, '/imagedemo/item1', '*' );
check( 'the uploaded filename is committed to the element immediately (no Speichern needed)', ( $stored['photo'] ?? null ) === $firstFilename );

[ $status2, $body2 ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'uploading a replacement succeeds', $status2 === 200 );
check( 'a same-format replacement overwrites the same deterministic path, not a new file', ( $body2['filename'] ?? null ) === $firstFilename && is_file( $uploadPath ) === true );

// When the output format itself changes (a png-with-alpha source this time), the path
// changes with it (different extension) - the old, now-orphaned .jpg must be cleaned up
[ $status3, $body3 ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'photo' ], true );
check( 'switching output format succeeds and yields a differently-named file', $status3 === 200 && ( $body3['filename'] ?? null ) === 'elements/imagedemo/item1.40x40.png' );
check( 'the old .jpg is deleted once the new .png is committed (no orphan across a format change)', is_file( $uploadPath ) === false );

[ $statusMissing ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'does-not-exist', 'locale' => 'de_DE', 'key' => 'photo' ] );
check( 'uploading for an element that was never saved is rejected', $statusMissing === 404 );

[ $statusWrongKey ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item1', 'locale' => 'de_DE', 'key' => 'plain' ] );
check( 'uploading to a key that is not an image field is rejected', $statusWrongKey === 400 );

// Deleting an element with an image field must also clean up the file it points
// to - an "image" field's value is just a filename reference, so deleteElement()
// itself has no way of knowing to touch it
\Nino\Elements::insertElement( $appData, '/imagedemo/item2', [ 'plain' => 'y' ], 'de_DE' );
[ , $bodyForDelete ] = callUploadImage( $appData, [ 'type' => 'imagedemo', 'uri' => 'item2', 'locale' => 'de_DE', 'key' => 'photo' ] );
$deletePath = $appData['./nino/filesystem/path']. '/images/'. $bodyForDelete['filename'];
check( 'the image exists before the element is deleted', is_file( $deletePath ) === true );

$deleteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'type' => 'imagedemo', 'uri' => 'item2' ] );
\Nino\Admin\Elements::apiDelete( $appData, $deleteRequest );
check( 'deleting the element succeeds', $deleteRequest['/nino/http/response']['statusCode'] === 200 );
check( 'deleting the element also deletes its uploaded image (no orphan)', is_file( $deletePath ) === false );

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
\Nino\Admin\Elements::apiDelete( $appData, $titledDeleteRequest );
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
	\Nino\Admin\Images::apiUpload( $appData, $request );
	@unlink( $path );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Images::apiList( $appData, $listRequest );
$slots = $listRequest['/nino/http/response']['body']['slots'] ?? [];
check( 'apiList returns every developer-fixed slot', count( $slots ) === 1 && $slots[0]['uri'] === '/hero' );
check( 'apiList reports no url for a slot with no image yet', $slots[0]['url'] === null );

[ $slotStatus, $slotBody ] = callUploadSlotImage( $appData, '/hero' );
check( 'a valid upload for a known slot succeeds', $slotStatus === 200 && is_string( $slotBody['filename'] ?? null ) === true );
check( 'the slot uri\'s leading slash is stripped for the deterministic path', $slotBody['filename'] === 'hero.60x40.jpg' );

$slotUploadPath = $appData['./nino/filesystem/path']. '/images/'. $slotBody['filename'];
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
check( 'the file lands at the nested deterministic path', is_file( $appData['./nino/filesystem/path']. '/images/'. $categoryBody['filename'] ) === true );

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
	\Nino\Admin\Users::{$method}( $appData, $request );
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

// --- Users::apiSetPermissions ---

// Its own dedicated account - plain3@example.com's empty perms are relied on
// by the "Admin::guardPerm" section below, so this must not touch it
\Nino\Auth::insertUser( $appData, 'permtest@example.com', 'perm test password' );

\Nino\Auth::loginUser( $appData, 'permtest@example.com', 'perm test password' );
[ $status ] = callUsers( $appData, 'apiSetPermissions', [ 'username' => 'permtest@example.com', 'perms' => [ \Nino\Admin\Elements::MANAGE_PERM ] ] );
check( 'a non-manager cannot set anyone\'s permissions, not even their own', $status === 403 );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

[ $status ] = callUsers( $appData, 'apiSetPermissions', [ 'username' => 'does-not-exist@example.com', 'perms' => [ '/*' ] ] );
check( 'apiSetPermissions 404s for an unknown user', $status === 404 );

[ $status, $body ] = callUsers( $appData, 'apiSetPermissions', [ 'username' => 'permtest@example.com', 'perms' => [ \Nino\Admin\Elements::MANAGE_PERM, 'not/a/real/perm' ] ] );
check( 'apiSetPermissions succeeds and whitelists away an unknown perm string', $status === 200 && $body['perms'] === [ \Nino\Admin\Elements::MANAGE_PERM ] );
check( 'the perm actually persisted to the user record', \Nino\Auth::getUser( $appData, 'permtest@example.com' )['perms'] === [ \Nino\Admin\Elements::MANAGE_PERM ] );

[ $status, $body ] = callUsers( $appData, 'apiSetPermissions', [ 'username' => 'permtest@example.com', 'perms' => [ '/*' ] ] );
check( 'apiSetPermissions accepts the \'/*\' full-access wildcard', $status === 200 && $body['perms'] === [ '/*' ] );

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
\Nino\Auth::insertUser( $appData, 'contenteditor@example.com', 'editor password', [ \Nino\Admin\Elements::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'contenteditor@example.com', 'editor password' );

[ $status ] = callAdminPost( $appData, 'elements/types' );
check( 'a user with only Elements::MANAGE_PERM can reach elements/types', $status === 200 );

[ $status ] = callAdminPost( $appData, 'text/keys' );
check( 'that same user still can\'t reach text/keys - perms don\'t leak across modules', $status === 403 );

// The dashboard summary stays reachable to any logged-in admin regardless of
// perms (see Admin\Dashboard::apiSummary - it's a read-only overview, not
// gated per-module)
[ $status ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary needs no specific perm, just to be logged in', $status === 200 );

\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

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
callUsers( $appData, 'apiList' ); // ensures /nino/backup/dir exists even on a from-scratch run

check( 'the backup dir/key exist in config.php', isset( $appData['/nino/backup/dir'] ) === true && isset( $appData['/nino/backup/key'] ) === true );

$backupDir 	= $sandbox. '/_admin/'. $appData['/nino/backup/dir'];
$today 			= $backupDir. '/'. date( 'Y-m-d' ). '.php';

if( is_file( $today ) === true )
	unlink( $today );

$logLinesBeforeBackup = isset( $appData['/nino/logs/dir'] ) === true ? readTodayLogLines( $appData, $sandbox ) : [];

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

check( 'the decrypted archive contains a byte-identical config.php', file_get_contents( $sandbox. '/config.php' ) === file_get_contents( $extractDir. '/config.php' ) );

$mtimeBefore = filemtime( $today );
clearstatcache();
callUsers( $appData, 'apiList' );
clearstatcache();
check( 'a second authenticated request the same day doesn\'t touch an already-existing backup', filemtime( $today ) === $mtimeBefore );

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
	$path = $sandbox. '/_admin/'. $appData['/nino/logs/dir']. '/'. date( 'Y-m-d' ). '.php';
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

check( 'Logs uses its own random directory, independent of Backup\'s', $appData['/nino/logs/dir'] !== $appData['/nino/backup/dir'] );

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
// newly-authenticated session touches _admin - not a direct Auth hook, since
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

$logsDir = $sandbox. '/_admin/'. $appData['/nino/logs/dir'];
$staleLogFile = $logsDir. '/2020-01-01.php';
file_put_contents( $staleLogFile, "<?php http_response_code(403); exit; return '". base64_encode( '2020-01-01 00:00  x  y' ). "';\n" );

callAdminPost( $appData, 'elements/save', [ 'type' => 'logtestdemo', 'uri' => 'entry2', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'title' => 'Hi2' ] ] );
check( 'a stale log file past the retention window gets pruned on the next write', is_file( $staleLogFile ) === false );

echo "\n";


// --- Submissions: Modules\Form writes, Admin\Submissions reads independently ---

echo "Submissions (Modules\\Form writes, Admin\\Submissions::apiList reads)\n";

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

check( 'forms data lives at the project-root /data dir, not under _admin', is_file( \Nino\Filesystem::getPath( $appData ). '/data/forms.'. date( 'Y-m' ). '.php' ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

unset( $appData['./nino/auth/current'] );
[ $status ] = callAdminPost( $appData, 'submissions/list' );
check( 'submissions/list requires an authed admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'manager@example.com', 'manager password' );

echo "\n";


// --- Newsletter: Modules\Newsletter writes, Admin\Newsletter reads/deletes independently ---

echo "Newsletter (Modules\\Newsletter writes, Admin\\Newsletter::apiList/apiDelete)\n";

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

check( 'newsletter data lives at the project-root /data dir, not under _admin', is_file( \Nino\Filesystem::getPath( $appData ). '/data/newsletter.php' ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

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

echo "\n";


// --- Dashboard::apiSummary - aggregates numbers the other panels already compute ---

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callAdminPost( $appData, 'dashboard/summary' );
check( 'dashboard/summary succeeds', $status === 200 );
check( 'submissions total matches Submissions::count (1 sent above, none deleted)', $body['submissions'] === 1 );
check( 'newsletter total matches what remains after the delete above (jo removed, anna remains)', $body['newsletter'] === 1 );

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
