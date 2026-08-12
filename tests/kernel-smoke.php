<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	kernel-smoke.php		Dependency-free smoke test for the write paths in
 *											_nino/Nino.php that have no other caller yet
 *											(Elements insert/update/delete, Auth insert/login/delete).
 *											Runs against an isolated sandbox directory, never touches
 *											the real project data.
 *
 *	Usage: php tests/kernel-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';

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

/**
 *	Probe whether a Filesystem::lockFile()'d path's sidecar .lock file is
 *	currently free, via a second, independent file handle - flock() is per
 *	open-file-description, so this genuinely conflicts with a handle Nino
 *	itself is still holding open, the same way a second request would.
 *	Releases its own probe lock again before returning, so it never leaves
 *	the sidecar locked for whatever runs next.
 *
 *	@param		string		$sandbox			Sandbox root (./nino/filesystem/path)
 *	@param		string		$filename			Locked path as passed to lockFile(), eg. '/elements/foo.php'
 *
 *	@return		bool										True if the lock was free (and has now been released again)
 */
function probeLockFree( string $sandbox, string $filename ): bool {
	$lockPath	= $sandbox. '/data/.locks/'. sha1( $filename ). '.lock';
	$probe		= @fopen( $lockPath, 'c' );
	if( $probe === false )
		return false;
	$free = flock( $probe, LOCK_EX | LOCK_NB );
	if( $free === true )
		flock( $probe, LOCK_UN );
	fclose( $probe );
	return $free;
}

// Silence trigger_error() output (we're using the plain php handler, no Nino\Runtime error handler
// is registered here on purpose - keeps expected-failure paths from being noisy)
set_error_handler( function() { return true; } );

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$sandbox = sys_get_temp_dir(). '/nino-kernel-smoke-'. uniqid();
mkdir( $sandbox, 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']			= $sandbox;
// Mirrors \Nino\init()'s default (no NINO_CONFIG_DIR set): configpath falls
// back to the project root, same as this sandbox's regular path
$appData['./nino/filesystem/configpath']	= $sandbox;
$appData['./nino/filesystem/contentpath']	= $sandbox. '/content';
$appData['/nino/dir']				= '';
$appData['/nino/locales/native']				= 'de_DE';
$appData['/nino/locales/available']			= [ 'de_DE', 'en_US' ];
$appData['/nino/auth/maxtries']					= 3;
$appData['/nino/auth/cooldown']					= 3600;
$appData['/nino/auth/user']							= [];

echo "Sandbox: $sandbox\n\n";


// --- Elements ------------------------------------------------------------

echo "Elements::insertElementType / insertElement / updateElement / deleteElement\n";

$model = [
	'title' => [ 'type' => 'string', 'locale' => true ],
	'views' => [ 'type' => 'integer', 'default' => 0 ],
	'price' => [ 'type' => 'double' ],
];

check( 'insertElementType creates a new type', \Nino\Elements::insertElementType( $appData, '/testtype', $model ) !== false );
check( 'insertElementType rejects a duplicate type', \Nino\Elements::insertElementType( $appData, '/testtype', $model ) === false );

$inserted = \Nino\Elements::insertElement( $appData, '/testtype/item1', [ 'title' => 'Hello' ], 'de_DE' );
check( 'insertElement returns the new element', is_array( $inserted ) === true );
check( 'insertElement applies the locale field', ( $inserted['title'] ?? null ) === 'Hello' );
check( 'insertElement applies the type default', ( $inserted['views'] ?? null ) === 0 );
check( 'insertElement rejects a duplicate uri', \Nino\Elements::insertElement( $appData, '/testtype/item1', [ 'title' => 'Again' ], 'de_DE' ) === false );

// A whole-number 'double' round-trips through json_decode() as an 'integer' (json has no int/float
// distinction for a value without a decimal point) - must be accepted and coerced, not rejected
$wholeNumberDouble = \Nino\Elements::insertElement( $appData, '/testtype/item2', [ 'title' => 'Priced', 'price' => 5 ], 'de_DE' );
check( 'insertElement accepts a whole-number int for a double field', is_array( $wholeNumberDouble ) === true );
check( 'insertElement coerces the whole-number int into an actual double', gettype( $wholeNumberDouble['price'] ?? null ) === 'double' );
\Nino\Elements::deleteElement( $appData, '/testtype/item2', 'de_DE' );

// Required fields: presence is enough for boolean/integer/double (0/false are
// legitimate values, not "missing") - only string/array need an actual non-empty value
\Nino\Elements::insertElementType( $appData, '/reqtype', [
	'title' 	=> [ 'type' => 'string', 'required' => true ],
	'tags' 		=> [ 'type' => 'array', 'required' => true ],
	'count' 	=> [ 'type' => 'integer', 'required' => true ],
	'active' 	=> [ 'type' => 'boolean', 'required' => true ],
] );

check( 'insertElement rejects a missing required string field', \Nino\Elements::insertElement( $appData, '/reqtype/item1', [ 'title' => '', 'tags' => [ 'a' ], 'count' => 1, 'active' => true ], 'de_DE' ) === false );
check( 'insertElement rejects an empty required array field', \Nino\Elements::insertElement( $appData, '/reqtype/item1', [ 'title' => 'x', 'tags' => [], 'count' => 1, 'active' => true ], 'de_DE' ) === false );
check( 'insertElement accepts a legitimate 0 for a required integer field', \Nino\Elements::insertElement( $appData, '/reqtype/item1', [ 'title' => 'x', 'tags' => [ 'a' ], 'count' => 0, 'active' => true ], 'de_DE' ) !== false );
\Nino\Elements::deleteElement( $appData, '/reqtype/item1', '*' );
check( 'insertElement accepts a legitimate false for a required boolean field', \Nino\Elements::insertElement( $appData, '/reqtype/item2', [ 'title' => 'x', 'tags' => [ 'a' ], 'count' => 1, 'active' => false ], 'de_DE' ) !== false );
\Nino\Elements::deleteElement( $appData, '/reqtype/item2', '*' );

// An image is exempt from "required" on purpose: both editing tools upload
// its file only once the element exists and has a uri to attach it to, so
// enforcing the flag here would reject the very insert that has to come
// first - the type could never get an element at all. A model carrying the
// flag from an older version or from hand-editing must not be that dead end
\Nino\Elements::insertElementType( $appData, '/reqimagetype', [
	'title' 	=> [ 'type' => 'string', 'required' => true ],
	'photo' 	=> [ 'type' => 'image', 'required' => true, 'width' => 40, 'height' => 40 ],
] );

check( 'insertElement accepts an element whose required image field has no file yet', \Nino\Elements::insertElement( $appData, '/reqimagetype/item1', [ 'title' => 'x', 'photo' => '' ], 'de_DE' ) !== false );
check( '...and one that omits the image field entirely', \Nino\Elements::insertElement( $appData, '/reqimagetype/item2', [ 'title' => 'x' ], 'de_DE' ) !== false );
check( 'the exemption is image-only - a required string alongside it is still enforced', \Nino\Elements::insertElement( $appData, '/reqimagetype/item3', [ 'title' => '', 'photo' => '' ], 'de_DE' ) === false );
check( 'a filename passed for an image field is still stored', ( \Nino\Elements::updateElement( $appData, '/reqimagetype/item1', [ 'photo' => 'reqimagetype/item1/photo.webp' ], 'de_DE' )['photo'] ?? null ) === 'reqimagetype/item1/photo.webp' );

\Nino\Elements::deleteElement( $appData, '/reqimagetype/item1', '*' );
\Nino\Elements::deleteElement( $appData, '/reqimagetype/item2', '*' );

// Regression: every early return between taking the type file's write lock
// and putFileContent() (which is the only place that used to release it)
// leaked that lock for the rest of the request - a required-field
// validation failure is one of them. Probed via a second, independent file
// handle on the sidecar .lock file, since Nino's own re-lock check inside
// the same $appData would otherwise mask a leak as "already holding it".
\Nino\Elements::insertElement( $appData, '/reqtype/leaktest', [ 'title' => '', 'tags' => [ 'a' ], 'count' => 1, 'active' => true ], 'de_DE' );
// Note the double slash: Elements builds its lock/cache key as '/elements/'.
// $typeUri. '.php' and $typeUri already carries its own leading slash - the
// probe has to match that exact key, not the visually "clean" single-slash path
check( 'insertElement releases the type file lock after a required-field validation failure', probeLockFree( $sandbox, '/elements//reqtype.php' ) === true );

$fetched = \Nino\Elements::getElement( $appData, '/testtype/item1', 'de_DE' );
check( 'getElement finds the inserted element', is_array( $fetched ) === true && $fetched['title'] === 'Hello' );

$updated = \Nino\Elements::updateElement( $appData, '/testtype/item1', [ 'title' => 'Hello updated' ], 'de_DE' );
check( 'updateElement changes the field value', is_array( $updated ) === true && $updated['title'] === 'Hello updated' );
check( 'updateElement rejects an unknown uri', \Nino\Elements::updateElement( $appData, '/testtype/does-not-exist', [ 'title' => 'x' ], 'de_DE' ) === false );

// getElement( ..., '*' ) must resolve to the locale that actually has data,
// not just return the global defaults
$wildcard = \Nino\Elements::getElement( $appData, '/testtype/item1', '*' );
check( 'getElement( *, ) resolves to a locale with actual data', is_array( $wildcard ) === true && $wildcard['title'] === 'Hello updated' );

// updateElement without re-sending "title" must not lose the existing value
// (it depends on the same '*' fallback resolution internally)
$partialUpdate = \Nino\Elements::updateElement( $appData, '/testtype/item1', [], 'de_DE' );
check( 'updateElement preserves fields that are not re-sent', is_array( $partialUpdate ) === true && $partialUpdate['title'] === 'Hello updated' );

// A partial update must merge the locale it is actually targeting, and only
// persist the supplied keys. The old wildcard merge picked the first locale
// that happened to exist and then wrote every merged field into en_US - a
// global counter update could silently replace its English title with German.
\Nino\Elements::updateElement( $appData, '/testtype/item1', [ 'title' => 'Hello English' ], 'en_US' );
$partialGlobalUpdate = \Nino\Elements::updateElement( $appData, '/testtype/item1', [ 'views' => 7 ], 'en_US' );
$afterPartialDe = \Nino\Elements::getElement( $appData, '/testtype/item1', 'de_DE' );
$afterPartialEn = \Nino\Elements::getElement( $appData, '/testtype/item1', 'en_US' );
check( 'a partial global update returns the requested locale\'s complete element', is_array( $partialGlobalUpdate ) === true && $partialGlobalUpdate['title'] === 'Hello English' && $partialGlobalUpdate['views'] === 7 );
check( 'a partial global update does not overwrite the target locale from another locale', $afterPartialEn['title'] === 'Hello English' );
check( 'a partial global update leaves the other locale untouched', $afterPartialDe['title'] === 'Hello updated' && $afterPartialDe['views'] === 7 );

$queried = \Nino\Elements::queryElements( $appData, '/testtype', [ 'title' => '%updated%' ], 'de_DE', [] );
check( 'queryElements finds the element via wildcard match', count( $queried ) === 1 );
check( 'queryElements returns no hits for a non-matching query', \Nino\Elements::queryElements( $appData, '/testtype', [ 'title' => 'nope' ], 'de_DE', [] ) === [] );

// Regression: locale '*' used to only ever look inside the '*' bucket itself
// (treating '*' as if it were just another locale key), silently missing any
// element that only exists under a locale-specific key - true for a
// hand-authored type with no global model fields at all (eg. portfolio.php),
// where an element inserted via insertElement() always gets a '*' shell entry,
// but hand-authored content can plainly omit it
// Note the top-level "title" key here (the type's own display name, a plain
// string - every real hand-authored type file has one) - a naive
// array_keys($typeData) to enumerate "every locale" would trip over it
\Nino\Filesystem::putFileContent( $appData, '/elements/wildcardtest.php', [
	'title' 	=> 'Wildcard Test',
	'model' 	=> [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
	'*' 			=> [ '*' => [] ],
	'de_DE' 	=> [ 'a' => [ 'title' => 'Eins' ], 'b' => [ 'title' => 'Zwei' ] ],
	'en_US' 	=> [ 'a' => [ 'title' => 'One' ], 'b' => [ 'title' => 'Two' ] ],
] );

check( 'queryElements( locale: * ) finds elements that only exist under a locale key, not just the "*" bucket', count( \Nino\Elements::queryElements( $appData, '/wildcardtest', [], '*', [] ) ) === 2 );
check( 'queryElements( locale: * ) with a query matches across every locale, not just "*"\'s (empty) data', count( \Nino\Elements::queryElements( $appData, '/wildcardtest', [ 'title' => 'One' ], '*', [] ) ) === 1 );
check( 'queryElements( locale: de_DE ) is unaffected - still only searches that one locale', count( \Nino\Elements::queryElements( $appData, '/wildcardtest', [ 'title' => 'One' ], 'de_DE', [] ) ) === 0 );

// Regression: deleteElement( ..., '*' ) used to crash with "Cannot unset
// string offsets" - like queryElements above, it iterated every top-level
// key of $typeData excluding only 'model', tripping over the type's own
// 'title' key (a plain string) as soon as a real hand-authored type (ie.
// any type with a 'title' key, which is every one of them) was deleted from
$deleted = \Nino\Elements::deleteElement( $appData, '/wildcardtest/a', '*' );
check( 'deleteElement( locale: * ) succeeds on a type with a "title" key', $deleted === true );
check( 'deleteElement( locale: * ) actually removes the element from every locale', \Nino\Elements::getElement( $appData, '/wildcardtest/a', '*' ) === false );
check( 'deleteElement( locale: * ) leaves other elements untouched', \Nino\Elements::getElement( $appData, '/wildcardtest/b', '*' ) !== false );

// item1 picked up an en_US entry in the partial-update checks above, so it now
// lives in two locales. A single-locale delete drops that locale's own data and
// nothing else: the element still exists for English, and with it the shared
// '*' bucket entry holding its global fields
\Nino\Elements::deleteElement( $appData, '/testtype/item1', 'de_DE' );
$afterDeDelete = \Nino\Elements::getElement( $appData, '/testtype/item1', 'de_DE' );
check( 'deleteElement drops the requested locale\'s own data', is_array( $afterDeDelete ) === true && isset( $afterDeDelete['title'] ) === false );
check( 'deleteElement keeps the shared global fields while another locale still references the element', ( $afterDeDelete['views'] ?? null ) === 7 );
check( 'deleteElement leaves the other locale fully intact', ( \Nino\Elements::getElement( $appData, '/testtype/item1', 'en_US' )['title'] ?? null ) === 'Hello English' );

// ...and once the last locale holding it goes, the '*' shell entry goes too
\Nino\Elements::deleteElement( $appData, '/testtype/item1', 'en_US' );
check( 'deleteElement removes the element (no leftover "*" shell entry)', \Nino\Elements::getElement( $appData, '/testtype/item1', 'en_US' ) === false );
check( 'the element is gone from every other locale as well', \Nino\Elements::getElement( $appData, '/testtype/item1', '*' ) === false );

// Regression: 'date'/'datetime' model fields hold a plain string value (php has no
// native date type) - insertElement used to reject them outright, comparing
// gettype() of the string value against the literal type name 'date'/'datetime'
check( 'insertElementType accepts a datetime field', \Nino\Elements::insertElementType( $appData, '/datetesttype', [ 'when' => [ 'type' => 'date' ], 'at' => [ 'type' => 'datetime' ] ] ) !== false );

$dateInserted = \Nino\Elements::insertElement( $appData, '/datetesttype/item1', [ 'when' => '2026-07-14', 'at' => '2026-07-14T15:30' ], '*' );
check( 'insertElement accepts a date field value', is_array( $dateInserted ) === true && $dateInserted['when'] === '2026-07-14' );
check( 'insertElement accepts a datetime field value', is_array( $dateInserted ) === true && $dateInserted['at'] === '2026-07-14T15:30' );

echo "\n";


// --- Images ------------------------------------------------------------

echo "Images::process / delete\n";

/**
 *	Build raw jpeg/png bytes for a solid-color test image
 *
 *	@param		int				$width
 *	@param		int				$height
 *	@param		bool			$alpha				Encode as png with a transparent pixel, instead of jpeg
 *
 *	@return		string
 */
function makeTestImage( int $width, int $height, bool $alpha = false ): string {
	$img = imagecreatetruecolor( $width, $height );
	if( $alpha === true ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 200, 0, 64 ) );
	} else {
		imagefill( $img, 0, 0, imagecolorallocate( $img, 200, 0, 0 ) );
	}
	ob_start();
	$alpha === true ? imagepng( $img ) : imagejpeg( $img, null, 90 );
	$bytes = ob_get_clean();
	imagedestroy( $img );
	return $bytes;
}

// A wide (400x200, 2:1) source cropped to a square (100x100) target must center-crop
// horizontally rather than distort - the output must be exactly the target size either way
$wideSource = makeTestImage( 400, 200 );
$squareFilename = \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/item1' );
check( 'process() returns the deterministic filename (basePath + dimensions + extension)', $squareFilename === 'elements/demo/item1.100x100.jpg' );

$squarePath = $appData['./nino/filesystem/path']. '/images/'. ( $squareFilename ?: '' );
check( 'process() writes the file to disk, creating parent dirs as needed', is_file( $squarePath ) === true );

[ $outWidth, $outHeight ] = getimagesize( $squarePath );
check( 'process() resizes to exactly the target dimensions', $outWidth === 100 && $outHeight === 100 );

check( 'process() outputs jpeg for a source with no transparency', str_ends_with( $squareFilename, '.jpg' ) === true );

// Progressive jpeg (SOF2, 0xFFC2) rather than baseline (SOF0, 0xFFC0) - smaller
// perceived load time, same content
$squareBytes = file_get_contents( $squarePath );
check( 'process() encodes jpeg output as progressive', strpos( $squareBytes, "\xFF\xC2" ) !== false && strpos( $squareBytes, "\xFF\xC0" ) === false );

// Re-uploading to the same basePath overwrites in place, not a new file
$replacementSource = makeTestImage( 400, 200 );
$secondFilename = \Nino\Images::process( $appData, $replacementSource, 100, 100, 'elements/demo/item1' );
check( 'process() overwrites the same deterministic path on a repeat upload, not a new file', $secondFilename === $squareFilename );

\Nino\Images::delete( $appData, $squareFilename );
check( 'delete() removes the file', is_file( $squarePath ) === false );

// A source that might carry transparency (png/gif/webp) is re-encoded as png, to preserve it
$alphaSource = makeTestImage( 100, 100, true );
$alphaFilename = \Nino\Images::process( $appData, $alphaSource, 50, 50, 'elements/demo/item2' );
check( 'process() outputs png for a source that may have transparency', $alphaFilename === 'elements/demo/item2.50x50.png' );
\Nino\Images::delete( $appData, $alphaFilename );

check( 'process() rejects bytes that are not a valid image', \Nino\Images::process( $appData, 'not an image', 100, 100, 'elements/demo/item3' ) === false );
check( 'process() rejects an empty target dimension', \Nino\Images::process( $appData, $wideSource, 0, 100, 'elements/demo/item3' ) === false );
check( 'process() rejects a path-traversal basePath', \Nino\Images::process( $appData, $wideSource, 100, 100, '../escape' ) === false );

// delete() must never escape its own upload dir, even given a maliciously crafted filename
\Nino\Filesystem::putFileContent( $appData, '/canary.txt', 'still here' );
\Nino\Images::delete( $appData, '../canary.txt' );
check( 'delete() refuses a path-traversal filename', \Nino\Filesystem::fileExists( $appData, '/canary.txt' ) === true );

echo "\n";


// --- Images::getSlots / getSlot / setSlotFilename -----------------------

echo "Images::getSlots / getSlot / setSlotFilename\n";

$appData['/nino/html/images'] = [
	'hero' => [ 'label' => 'Hero', 'width' => 1600, 'height' => 600, 'filename' => null ],
];

check( 'getSlots returns every developer-fixed slot', array_keys( \Nino\Images::getSlots( $appData ) ) === [ 'hero' ] );
check( 'getSlot returns one slot\'s definition', ( \Nino\Images::getSlot( $appData, 'hero' )['label'] ?? null ) === 'Hero' );
check( 'getSlot returns false for an unknown uri', \Nino\Images::getSlot( $appData, 'nope' ) === false );

check( 'setSlotFilename rejects an unknown slot', \Nino\Images::setSlotFilename( $appData, 'nope', 'x.jpg' ) === false );
check( 'setSlotFilename accepts a known slot', \Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' ) === true );
check( 'setSlotFilename updates the in-memory slot immediately', \Nino\Images::getSlot( $appData, 'hero' )['filename'] === 'hero.1600x600.jpg' );

$persisted = include $appData['./nino/filesystem/path']. '/config.php';
check( 'setSlotFilename persists to config.php (same as Auth::updateUser)', ( $persisted['/nino/html/images']['hero']['filename'] ?? null ) === 'hero.1600x600.jpg' );

$appData['/nino/html/images']['logo'] = [ 'label' => 'Logo', 'width' => 400, 'height' => 400, 'filename' => null ];

\Nino\Modules\Images::init( $appData );
check( '[image] shortcode renders an <img> tag for a slot with an uploaded file', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/images/hero.1600x600.jpg" width="1600" height="600"' ) === true );
check( '[image] shortcode renders nothing for a slot with no file uploaded yet', \Nino\Html::renderHtml( $appData, '[image logo]' ) === '' );
check( '[image] shortcode renders nothing for an unknown slot', \Nino\Html::renderHtml( $appData, '[image nope]' ) === '' );

echo "\n";


// --- Auth ------------------------------------------------------------

echo "Auth::insertUser / loginUser / deleteUser\n";

check( 'insertUser creates a new user', \Nino\Auth::insertUser( $appData, 'test@example.com', 'correct horse battery staple' ) === true );
check( 'insertUser rejects a duplicate user', \Nino\Auth::insertUser( $appData, 'test@example.com', 'whatever' ) === false );

$loginFail = \Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong password' );
check( 'loginUser rejects a wrong password', $loginFail === false );

$loginOk = \Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' );
check( 'loginUser accepts the right password', is_array( $loginOk ) === true );
check( 'loginUser sets the current user', \Nino\Auth::getCurrentUser( $appData ) !== false );
$loginSessions = \Nino\Auth::getUser( $appData, 'test@example.com' )['sessions'];
check( 'loginUser records a session token carrying the client ip', count( $loginSessions ) === 1 && array_values( $loginSessions )[0]['ip'] === '127.0.0.1' );

// Password hash rotation: force an outdated (low-cost) hash, then log in again and confirm it gets rotated
$appData['/nino/auth/user']['test@example.com']['pw'] = password_hash( 'correct horse battery staple', PASSWORD_BCRYPT, [ 'cost' => 4 ] );
$oldHash = $appData['/nino/auth/user']['test@example.com']['pw'];
\Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' );
$newHash = $appData['/nino/auth/user']['test@example.com']['pw'];
check( 'loginUser rotates an outdated password hash', $newHash !== $oldHash && password_verify( 'correct horse battery staple', $newHash ) === true );

// Cooldown after too many failed attempts (maxtries = 3 in this sandbox)
\Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong' );
check( 'loginUser locks out after maxtries failed attempts', \Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' ) === false );

check( 'deleteUser removes the user', \Nino\Auth::deleteUser( $appData, 'test@example.com' ) === true );
check( 'getUser no longer finds the deleted user', \Nino\Auth::getUser( $appData, 'test@example.com' ) === false );

echo "\n";


// --- Auth::updateUser / logoutAllSessions ------------------------------------------------------------

echo "Auth::updateUser / logoutAllSessions\n";

\Nino\Auth::insertUser( $appData, 'rename@example.com', 'correct horse battery staple' );
\Nino\Auth::insertUser( $appData, 'taken@example.com', 'whatever' );

check( 'updateUser rejects a mail already taken by another user', \Nino\Auth::updateUser( $appData, 'rename@example.com', 'taken@example.com' ) === false );

$renamed = \Nino\Auth::updateUser( $appData, 'rename@example.com', 'renamed@example.com' );
check( 'updateUser renames the mail (moves the array key)', is_array( $renamed ) === true && $renamed['mail'] === 'renamed@example.com' );
check( 'updateUser removes the old mail key', \Nino\Auth::getUser( $appData, 'rename@example.com' ) === false );
check( 'updateUser is reachable under the new mail', \Nino\Auth::getUser( $appData, 'renamed@example.com' ) !== false );

$oldHash = \Nino\Auth::getUser( $appData, 'renamed@example.com' )['pw'];
\Nino\Auth::updateUser( $appData, 'renamed@example.com', 'renamed@example.com', 'a brand new password' );
$newHash = \Nino\Auth::getUser( $appData, 'renamed@example.com' )['pw'];
check( 'updateUser rotates the password hash when a new password is given', $newHash !== $oldHash && password_verify( 'a brand new password', $newHash ) === true );

\Nino\Auth::updateUser( $appData, 'renamed@example.com', 'renamed@example.com' );
check( 'updateUser keeps the existing hash when no new password is given', \Nino\Auth::getUser( $appData, 'renamed@example.com' )['pw'] === $newHash );

// A self-rename must keep the current session pointed at the (new) right identity
\Nino\Auth::loginUser( $appData, 'renamed@example.com', 'a brand new password' );
\Nino\Auth::updateUser( $appData, 'renamed@example.com', 'stillme@example.com' );
check( 'updateUser keeps the current session logged in as the new mail after a self-rename', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'stillme@example.com' );

// Regression: Auth::init() re-hydrates the current user straight from the raw
// stored record on every request, bypassing getUser()'s "add mail back if it's
// missing" fallback - a rename that left the raw record without a mail field
// would only break on the *next* request, not in updateUser()'s own return value
check( 'updateUser keeps a mail field in the raw stored record, not just via getUser()', $appData['/nino/auth/user']['stillme@example.com']['mail'] === 'stillme@example.com' );

unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a fresh request (Auth::init) still resolves the current user\'s mail after a self-rename', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'stillme@example.com' );

// logoutAllSessions
\Nino\Auth::insertUser( $appData, 'multisession@example.com', 'correct horse battery staple' );
$appData['/nino/auth/user']['multisession@example.com']['sessions'] = [ '127.0.0.1' => time(), '10.0.0.1' => time() ];
check( 'logoutAllSessions clears every session entry', \Nino\Auth::logoutAllSessions( $appData, 'multisession@example.com' ) === true && \Nino\Auth::getUser( $appData, 'multisession@example.com' )['sessions'] === [] );

\Nino\Auth::loginUser( $appData, 'multisession@example.com', 'correct horse battery staple' );
check( 'logoutAllSessions on the current user also ends the current session', \Nino\Auth::logoutAllSessions( $appData, 'multisession@example.com' ) === true && \Nino\Auth::getCurrentUser( $appData ) === false );

// checkPermission
\Nino\Auth::insertUser( $appData, 'perms@example.com', 'correct horse battery staple', [ '/section/*' ] );
check( 'checkPermission grants a wildcard-covered permission', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'perms@example.com' ) === true );
check( 'checkPermission denies one outside the wildcard', \Nino\Auth::checkPermission( $appData, '/other/thing/manage', 'perms@example.com' ) === false );
check( 'a permission without a slash is denied, not a fatal', \Nino\Auth::checkPermission( $appData, 'manage', 'perms@example.com' ) === false );

// A non-string truthy value in perms (a config typo) must not loosely match
// every permission there is
$appData['/nino/auth/user']['perms@example.com']['perms'] = [ true ];
check( 'a truthy non-string in perms grants nothing', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'perms@example.com' ) === false );

\Nino\Auth::deleteUser( $appData, 'perms@example.com' );

echo "\n";


// --- Callback points: /nino/auth/login|logout|user/insert|update|delete, /nino/elements<type>/insert|update ---

echo "Module hook points: Auth login/logout/user CRUD, Elements insert/update\n";

$seen = [];
foreach( [ '/nino/auth/login', '/nino/auth/logout', '/nino/auth/user/insert', '/nino/auth/user/update', '/nino/auth/user/delete' ] as $hook )
	\Nino\Callbacks::registerCallback( $appData, $hook, function( &$appData, &$args ) use ( &$seen, $hook ) { $seen[$hook][] = $args; return $args; } );

\Nino\Auth::insertUser( $appData, 'hooktest@example.com', 'correct horse battery staple' );
check( '/nino/auth/user/insert fires with the new user\'s data', ( $seen['/nino/auth/user/insert'][0]['mail'] ?? null ) === 'hooktest@example.com' );

\Nino\Auth::loginUser( $appData, 'hooktest@example.com', 'correct horse battery staple' );
check( '/nino/auth/login fires on a successful login', ( $seen['/nino/auth/login'][0]['mail'] ?? null ) === 'hooktest@example.com' );

\Nino\Auth::updateUser( $appData, 'hooktest@example.com', 'hooktest@example.com', 'a new password' );
check( '/nino/auth/user/update fires with the updated user\'s data', ( $seen['/nino/auth/user/update'][0]['mail'] ?? null ) === 'hooktest@example.com' );

\Nino\Auth::logoutUser( $appData );
check( '/nino/auth/logout fires with the logged-out user\'s data', ( $seen['/nino/auth/logout'][0]['mail'] ?? null ) === 'hooktest@example.com' );

\Nino\Auth::deleteUser( $appData, 'hooktest@example.com' );
check( '/nino/auth/user/delete fires with the deleted user\'s data', ( $seen['/nino/auth/user/delete'][0]['mail'] ?? null ) === 'hooktest@example.com' );

\Nino\Elements::insertElementType( $appData, '/hooktest', [ 'name' => [ 'type' => 'string', 'locale' => true ] ] );

$elementSeen = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/hooktest/insert', function( &$appData, &$args ) use ( &$elementSeen ) { $elementSeen['insert'][] = $args; return $args; } );
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/hooktest/update', function( &$appData, &$args ) use ( &$elementSeen ) { $elementSeen['update'][] = $args; return $args; } );

\Nino\Elements::insertElement( $appData, '/hooktest/item1', [ 'name' => 'Item 1' ], 'de_DE' );
check( '/nino/elements<type>/insert fires on insertElement, not on update', count( $elementSeen['insert'] ?? [] ) === 1 && empty( $elementSeen['update'] ?? [] ) === true );

\Nino\Elements::updateElement( $appData, '/hooktest/item1', [ 'name' => 'Item 1 renamed' ], 'de_DE' );
check( '/nino/elements<type>/update fires on updateElement, not again on insert', count( $elementSeen['update'] ?? [] ) === 1 && count( $elementSeen['insert'] ?? [] ) === 1 );

// Regression: same lock-leak as the insertElement one above, but on
// deleteElement()'s own early return - a callback veto used to skip
// putFileContent(), the only place that released the lock
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/delete/hooktest', function( &$appData, &$args ) { return false; } );

$vetoed = \Nino\Elements::deleteElement( $appData, '/hooktest/item1', 'de_DE' );
check( 'deleteElement returns null when the delete callback vetoes', $vetoed === null );
// Same double-slash key as above, see the insertElement leak check
check( 'deleteElement releases the type file lock after a callback veto', probeLockFree( $sandbox, '/elements//hooktest.php' ) === true );

echo "\n";


// --- Callbacks::doCallbacks - every callable shape registerCallback() accepts ---------------------------

echo "Callbacks::doCallbacks runs every registered callable shape\n";

class KernelSmokeCallbackTarget {
	public array $seen = [];
	public function instanceMethod( array &$appData, mixed $args ): mixed { $this->seen[] = $args; return $args; }
	public static function staticMethod( array &$appData, mixed $args ): mixed { return ( is_array( $args ) ? $args : [] ) + [ 'static' => true ]; }
}
function kernelSmokeCallbackFunction( array &$appData, mixed $args ): mixed { return ( is_array( $args ) ? $args : [] ) + [ 'function' => true ]; }

$callbackTarget = new KernelSmokeCallbackTarget();

\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ $callbackTarget, 'instanceMethod' ] );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ KernelSmokeCallbackTarget::class, 'staticMethod' ] );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', 'kernelSmokeCallbackFunction' );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', function( array &$appData, mixed $args ): mixed { return ( is_array( $args ) ? $args : [] ) + [ 'closure' => true ]; } );

$callbackArgs = [ 'start' => true ];
$callbackResult = \Nino\Callbacks::doCallbacks( $appData, 'test/callbackshapes', $callbackArgs );

check( 'an [object, method] callback actually ran, not just registered', count( $callbackTarget->seen ) === 1 );
check( 'a [class, method] static callback ran', ( $callbackResult['static'] ?? false ) === true );
check( 'a plain function-name callback ran', ( $callbackResult['function'] ?? false ) === true );
check( 'a closure callback ran', ( $callbackResult['closure'] ?? false ) === true );

echo "\n";


// --- AppData::writeContentData concurrency ------------------------------------------------------------

echo "AppData::writeContentData doesn't clobber a concurrent write to a different key\n";

// Two independently-"booted" appData snapshots, both built fresh from the same
// on-disk config.php before either has written - simulating two overlapping
// requests (eg. a login in one tab, an image-slot save in another)
$buildAppData = function() use ( $sandbox ) {
	$fresh = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $fresh );
	$fresh['./nino/filesystem/path'] = $sandbox;
	\Nino\AppData::init( $fresh );
	return $fresh;
};

$reqA = $buildAppData();
$reqB = $buildAppData();

$reqA['/nino/auth/user']['race@example.com'] = [ 'marker' => 'FROM_A' ];
\Nino\AppData::writeContentData( $reqA, [ '/nino/auth/user' ] );

$reqB['/nino/html/images'] = [ 'marker' => 'FROM_B' ];
\Nino\AppData::writeContentData( $reqB, [ '/nino/html/images' ] );

$onDisk = include $sandbox. '/config.php';
check( 'a later write to a different key doesn\'t erase an earlier request\'s change', ( $onDisk['/nino/auth/user']['race@example.com']['marker'] ?? null ) === 'FROM_A' );
check( 'the later request\'s own change is still persisted', ( $onDisk['/nino/html/images']['marker'] ?? null ) === 'FROM_B' );

// Two overlapping logins of the *same* user: the second must not drop the
// first one's session token just because its own copy predates it
$sessionUser = 'parallel@example.com';
\Nino\Auth::insertUser( $appData, $sessionUser, 'correct horse battery staple' );

$sessions = function() use ( $sandbox, $sessionUser ): array {
	$onDisk = include $sandbox. '/config.php';
	return $onDisk['/nino/auth/user'][$sessionUser]['sessions'] ?? [];
};

$loginA = $buildAppData();
$loginB = $buildAppData();
foreach( [ $loginA, $loginB ] as $req )
	$req['./nino/auth/baseline'] = $req['/nino/auth/user'] ?? [];

$loginA['/nino/auth/user'][$sessionUser]['sessions']['tokenA'] = [ 'time' => time(), 'ip' => '10.0.0.1' ];
\Nino\AppData::writeContentData( $loginA, [ '/nino/auth/user' ] );

$loginB['/nino/auth/user'][$sessionUser]['sessions']['tokenB'] = [ 'time' => time(), 'ip' => '10.0.0.2' ];
\Nino\AppData::writeContentData( $loginB, [ '/nino/auth/user' ] );

check( 'a parallel login keeps its own session', isset( $sessions()['tokenB'] ) === true );
check( 'and does not log the other one out', isset( $sessions()['tokenA'] ) === true );

// ...but a revocation must not have those same "unseen" tokens merged back
// in: after a password change or a "log out everywhere" everything goes,
// including a session that appeared while the request was running
$revoke = $buildAppData();
$revoke['./nino/auth/baseline'] = $revoke['/nino/auth/user'] ?? [];

$sneaky = $buildAppData();
$sneaky['./nino/auth/baseline'] = $sneaky['/nino/auth/user'] ?? [];
$sneaky['/nino/auth/user'][$sessionUser]['sessions']['tokenC'] = [ 'time' => time(), 'ip' => '10.0.0.3' ];
\Nino\AppData::writeContentData( $sneaky, [ '/nino/auth/user' ] );

\Nino\Auth::logoutAllSessions( $revoke, $sessionUser );
check( 'log out everywhere really clears every session, including a parallel one', $sessions() === [] );

$pwChange = $buildAppData();
$pwChange['./nino/auth/baseline'] = $pwChange['/nino/auth/user'] ?? [];

$sneaky = $buildAppData();
$sneaky['./nino/auth/baseline'] = $sneaky['/nino/auth/user'] ?? [];
$sneaky['/nino/auth/user'][$sessionUser]['sessions']['tokenD'] = [ 'time' => time(), 'ip' => '10.0.0.4' ];
\Nino\AppData::writeContentData( $sneaky, [ '/nino/auth/user' ] );

\Nino\Auth::updateUser( $pwChange, $sessionUser, $sessionUser, 'a brand new password' );
check( 'a password change ends a session opened while it was running', $sessions() === [] );

// Regression: mutate()'s return value used to be silently discarded here -
// a failed config.php write (disk full, permission denied, ...) had no
// observable effect anywhere. Forced via a configpath whose base is a
// plain file, not a directory: nothing can be created "inside" it
// (ENOTDIR), which fails the write even for root, unlike a chmod-based
// denial. lockFile() itself still succeeds - it locks a sidecar file
// under the (perfectly normal) main filesystem path, not configpath - so
// this exercises mutate()'s own failure, not writeContentData()'s
// pre-existing "could not lock" check.
$writeFailDir = sys_get_temp_dir(). '/nino-kernel-smoke-writefail-'. uniqid();
mkdir( $writeFailDir, 0777, true );
$brokenConfigBase = $writeFailDir. '/not-a-directory';
file_put_contents( $brokenConfigBase, 'x' );

$writeFailAppData = [ './nino/uid' => $writeFailDir ];
\Nino\AppData::prepare( $writeFailAppData );
$writeFailAppData['./nino/filesystem/path']				= $writeFailDir;
$writeFailAppData['./nino/filesystem/configpath']	= $brokenConfigBase;
$writeFailAppData['/nino/auth/user'] = [ 'someone@example.com' => [] ];

$capturedErrors = [];
set_error_handler( function( int $errno, string $errstr ) use ( &$capturedErrors ): bool {
	$capturedErrors[] = [ 'errno' => $errno, 'errstr' => $errstr ];
	return true;
} );

\Nino\AppData::writeContentData( $writeFailAppData, [ '/nino/auth/user' ] );

restore_error_handler();

$sawWriteFailure = count( array_filter( $capturedErrors, fn( $e ) => $e['errno'] === E_USER_ERROR && str_contains( $e['errstr'], 'failed to write config.php' ) ) ) === 1;
check( 'writeContentData() surfaces a failed config.php write via trigger_error(E_USER_ERROR)', $sawWriteFailure );

\Nino\Filesystem::removeDir( $writeFailDir );

echo "\n";


// --- Csrf ------------------------------------------------------------

echo "Csrf::getToken / rotateToken / callbackResponse (kernel, required) / Modules\\Csrf::doShortcode (optional)\n";

$token1 = \Nino\Csrf::getToken( $appData );
check( 'getToken creates a token', is_string( $token1 ) === true && strlen( $token1 ) > 0 );
check( 'getToken returns the same token on a second call', \Nino\Csrf::getToken( $appData ) === $token1 );

\Nino\Csrf::rotateToken( $appData );
$token2 = \Nino\Csrf::getToken( $appData );
check( 'rotateToken replaces the token', $token2 !== $token1 );

$_POST['_csrf'] = 'wrong-token';
$fakeRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $fakeRequest );
check( 'callbackResponse rejects a wrong token', $fakeRequest['/nino/http/response']['statusCode'] === 403 );
check( 'callbackResponse sets the blocked flag on rejection', ( $fakeRequest['./nino/csrf/blocked'] ?? false ) === true );

$_POST['_csrf'] = $token2;
$fakeRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $fakeRequest );
check( 'callbackResponse accepts the current token', $fakeRequest['/nino/http/response']['statusCode'] === 200 );

check( 'Modules\Csrf::doShortcode renders a hidden input with the current token, reading the kernel token', str_contains( \Nino\Modules\Csrf::doShortcode( $appData, [] ), 'value="'. $token2. '"' ) === true );

// Regression: Auth::callbackLoginResponse()/callbackLogoutResponse() used
// to hard-refuse (trigger_error(E_USER_ERROR)) unless the Csrf module was
// listed in '/nino/modules' - protection is now the required \Nino\Csrf
// kernel class instead, always active, so that refusal is gone. This
// sandbox's $appData never lists Csrf under '/nino/modules' anywhere in
// this file (see below), which used to make every login/logout POST here
// a 500 - the two callbacks cooperating correctly via the shared
// './nino/csrf/blocked' flag, with no module in the loop at all, is
// exactly the property this split is for.
$csrfUser = 'csrfpipeline@example.com';
\Nino\Auth::insertUser( $appData, $csrfUser, 'correct horse battery staple' );

$capturedErrors = [];
set_error_handler( function( int $errno, string $errstr ) use ( &$capturedErrors ): bool {
	$capturedErrors[] = [ 'errno' => $errno, 'errstr' => $errstr ];
	return true;
} );

// Wrong token - same order the real pipeline runs in: Csrf at priority 1
// sets the blocked flag before Auth's own route callback ever sees it
$_POST['_csrf'] = 'not-the-real-token';
$badLoginRequest = [
	'/nino/http/request' 	=> [ 'method' => 'POST', 'user' => $csrfUser, 'pw' => 'correct horse battery staple' ],
	'/nino/http/response'	=> [ 'statusCode' => 200 ],
];
\Nino\Csrf::callbackResponse( $appData, $badLoginRequest );
\Nino\Auth::callbackLoginResponse( $appData, $badLoginRequest );

restore_error_handler();

check( 'callbackLoginResponse no longer hard-refuses regardless of /nino/modules', count( $capturedErrors ) === 0 );
check( 'callbackLoginResponse still respects a Csrf rejection (403, not the 401/200 it sets itself)', $badLoginRequest['/nino/http/response']['statusCode'] === 403 );
check( 'a Csrf-blocked login request never reaches loginUser()', \Nino\Auth::getCurrentUser( $appData ) === false );

// Valid token - the full pipeline succeeds end-to-end, still with no
// module involved anywhere
$_POST['_csrf'] = \Nino\Csrf::getToken( $appData );
$goodLoginRequest = [
	'/nino/http/request' 	=> [ 'method' => 'POST', 'user' => $csrfUser, 'pw' => 'correct horse battery staple' ],
	'/nino/http/response'	=> [ 'statusCode' => 200 ],
];
\Nino\Csrf::callbackResponse( $appData, $goodLoginRequest );
\Nino\Auth::callbackLoginResponse( $appData, $goodLoginRequest );

check( 'callbackLoginResponse succeeds end-to-end with a valid token', $goodLoginRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and actually logs the user in', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === $csrfUser );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, $csrfUser );
unset( $_POST['_csrf'] );

echo "\n";


// --- Modules\Form -------------------------------------------------------

echo "Modules\\Form::callbackResponse - validate/send/record a contact submission\n";

\Nino\Html::addFills( $appData, [
	'[[/form/email/owner]]' 	=> 'owner@example.com',
	'[[/form/subject/owner]]' => 'New inquiry',
	'[[/form/subject/user]]' 	=> 'Thanks for reaching out',
], '*' );

function submitForm( array &$appData, array $post ): array {
	$_POST = array_merge( [ 'name' => '', 'email' => '', 'message' => '', 'location' => '', 'cat' => '' ], $post );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Form::callbackResponse( $appData, $request );
	return $request;
}

$missingRequest = submitForm( $appData, [ 'name' => 'Jo' ] );
check( 'a missing required field is rejected (400)', $missingRequest['/nino/http/response']['statusCode'] === 400 );

$invalidEmailRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'not-an-email', 'message' => 'Hi' ] );
check( 'an invalid email is rejected (400)', $invalidEmailRequest['/nino/http/response']['statusCode'] === 400 );

$honeypotRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi', 'location' => 'filled-by-a-bot' ] );
check( 'a filled honeypot is rejected (418)', $honeypotRequest['/nino/http/response']['statusCode'] === 418 );

// /data itself already exists at this point (the Auth cooldown test above
// writes /data/auth-tries.php on a failed login) - check the form's own
// file specifically instead of the whole directory
check( 'neither rejected submission created the form data file', is_file( \Nino\Filesystem::getPath( $appData ). '/data/forms.'. date( 'Y-m' ). '.php' ) === false );

$okRequest = submitForm( $appData, [ 'name' => 'Jo Client', 'email' => 'jo@example.com', 'message' => "Line one\nLine two", 'cat' => 'General' ] );
check( 'a valid submission succeeds (200)', $okRequest['/nino/http/response']['statusCode'] === 200 );
check( 'a valid submission bootstraps the data dir at the project root, not under _editor', is_dir( \Nino\Filesystem::getPath( $appData ). '/data' ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_editor/data' ) === false );

$formsDir 	= \Nino\Filesystem::getPath( $appData ). '/data';
$monthFile 	= $formsDir. '/forms.'. date( 'Y-m' ). '.php';
check( 'this month\'s submissions file was actually written', is_file( $monthFile ) === true );

$monthEntries = \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] );
$lastEntry 		= end( $monthEntries );

check( 'the recorded entry has the submitted name/email/message/cat', $lastEntry['name'] === 'Jo Client' && $lastEntry['email'] === 'jo@example.com' && $lastEntry['message'] === "Line one\nLine two" && $lastEntry['cat'] === 'General' );
check( 'the recorded entry has a date and ip', isset( $lastEntry['date'] ) === true && $lastEntry['ip'] === '127.0.0.1' );

check( 'the file is a plain, human-readable php array file - not an encoded stub', str_starts_with( file_get_contents( $monthFile ), "<?php return array (" ) === true );

$submissionsBefore = count( \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] ) );
$_POST['_csrf'] = 'wrong-token';
$blockedRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $blockedRequest ); // sets 403 + the blocked flag, same as the real POST pipeline
$_POST = array_merge( $_POST, [ 'name' => 'Attacker', 'email' => 'attacker@example.com', 'message' => 'Hi', 'location' => '', 'cat' => '' ] );
\Nino\Modules\Form::callbackResponse( $appData, $blockedRequest );
check( 'a csrf-blocked request is rejected even with otherwise-valid fields', $blockedRequest['/nino/http/response']['statusCode'] === 403 );
check( 'a csrf-blocked request does not send mail or record a submission', count( \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] ) ) === $submissionsBefore );

echo "\n";


// --- Modules\Newsletter ---------------------------------------------------

echo "Modules\\Newsletter - double opt-in signup, confirm, unsubscribe\n";

function submitNewsletter( array &$appData, array $post ): array {
	$_POST = array_merge( [ 'email' => '', 'location' => '' ], $post );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Newsletter::callbackResponse( $appData, $request );
	return $request;
}

function visitNewsletterLink( array &$appData, array $query ): array {
	$request = [ '/nino/http/request' => [ 'query' => $query ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Newsletter::callbackAction( $appData, $request );
	return $request;
}

\Nino\Modules\Newsletter::init( $appData );
check( 'init registers the POST route under /.newsletter', isset( $appData['/nino/http/routes']['POST://.newsletter'] ) === true );
check( 'init registers the GET route (confirm/unsubscribe page) under /.newsletter', isset( $appData['/nino/http/routes']['GET://.newsletter'] ) === true );
check( 'init does not register the old /newsletter route anymore', isset( $appData['/nino/http/routes']['POST://newsletter'] ) === false );

$missingEmailRequest = submitNewsletter( $appData, [] );
check( 'a missing email is rejected (400)', $missingEmailRequest['/nino/http/response']['statusCode'] === 400 );

$invalidEmailRequest = submitNewsletter( $appData, [ 'email' => 'not-an-email' ] );
check( 'an invalid email is rejected (400)', $invalidEmailRequest['/nino/http/response']['statusCode'] === 400 );

$honeypotRequest = submitNewsletter( $appData, [ 'email' => 'jo@example.com', 'location' => 'filled-by-a-bot' ] );
check( 'a filled honeypot is rejected (418)', $honeypotRequest['/nino/http/response']['statusCode'] === 418 );

check( 'none of the rejected signups created the newsletter file', is_file( \Nino\Filesystem::getPath( $appData ). '/data/newsletter.php' ) === false );

$_POST['_csrf'] = 'wrong-token';
$blockedNewsletterRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $blockedNewsletterRequest );
$_POST = array_merge( $_POST, [ 'email' => 'jo@example.com', 'location' => '' ] );
\Nino\Modules\Newsletter::callbackResponse( $appData, $blockedNewsletterRequest );
check( 'a csrf-blocked signup is rejected too', $blockedNewsletterRequest['/nino/http/response']['statusCode'] === 403 );
check( 'a csrf-blocked signup does not create the newsletter file', is_file( \Nino\Filesystem::getPath( $appData ). '/data/newsletter.php' ) === false );

$okNewsletterRequest = submitNewsletter( $appData, [ 'email' => 'jo@example.com' ] );
check( 'a valid signup succeeds (200)', $okNewsletterRequest['/nino/http/response']['statusCode'] === 200 );
check( 'a valid new signup reports the generic status', $okNewsletterRequest['/nino/http/response']['body']['status'] === 'ok' );
check( 'a valid signup bootstraps the newsletter file at the project root, not under _editor', is_file( \Nino\Filesystem::getPath( $appData ). '/data/newsletter.php' ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_editor/data' ) === false );

$subscribersPath 	= '/data/newsletter.php';
$subscribersFile 	= \Nino\Filesystem::getPath( $appData ). $subscribersPath;

$subscribers = \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] );
check( 'exactly one entry was recorded', count( $subscribers ) === 1 );
check( 'the entry is pending, not subscribed - the form submit alone must not subscribe', ( $subscribers[0]['status'] ?? '' ) === 'pending' );
check( 'the entry has the submitted email, a confirm token, a date and ip', $subscribers[0]['email'] === 'jo@example.com' && empty( $subscribers[0]['token'] ) === false && isset( $subscribers[0]['date'] ) === true && $subscribers[0]['ip'] === '127.0.0.1' );

check( 'the file is a plain, human-readable php array file - not an encoded stub', str_starts_with( file_get_contents( $subscribersFile ), "<?php return array (" ) === true );

$pendingToken = $subscribers[0]['token'];

$dupeRequest = submitNewsletter( $appData, [ 'email' => 'jo@example.com' ] );
check( 'a repeated signup while still pending succeeds (200)', $dupeRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and reports the same generic status - the confirm mail is simply re-sent', $dupeRequest['/nino/http/response']['body']['status'] === 'ok' );
$subscribers = \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] );
check( 'without creating a duplicate entry', count( $subscribers ) === 1 );
check( 'and without rotating the pending token', $subscribers[0]['token'] === $pendingToken );

$wrongTokenRequest = visitNewsletterLink( $appData, [ 'confirm' => 'not-the-token' ] );
check( 'a confirm link with an unknown token answers 404', $wrongTokenRequest['/nino/http/response']['statusCode'] === 404 );
check( 'and leaves the entry pending', \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] )[0]['status'] === 'pending' );
check( 'and prepares the "invalid" page fills', ( $appData['./nino/html/fills']['*']['[[/newsletter/page/title]]'] ?? '' ) === '[[/newsletter/page/invalid/title]]' );

$confirmRequest = visitNewsletterLink( $appData, [ 'confirm' => $pendingToken ] );
check( 'a confirm link with the mailed token answers 200', $confirmRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and flips the entry to subscribed', \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] )[0]['status'] === 'subscribed' );
check( 'and prepares the "confirmed" page fills', ( $appData['./nino/html/fills']['*']['[[/newsletter/page/title]]'] ?? '' ) === '[[/newsletter/page/confirmed/title]]' );

$reconfirmRequest = visitNewsletterLink( $appData, [ 'confirm' => $pendingToken ] );
check( 'confirming twice stays a friendly 200, not an error', $reconfirmRequest['/nino/http/response']['statusCode'] === 200 );

$subscribedRequest = submitNewsletter( $appData, [ 'email' => 'jo@example.com' ] );
// The response must not distinguish a known address from a new one - that
// would let anyone test whether a given address is subscribed
check( 'signing up a confirmed subscriber again reports the same generic status', $subscribedRequest['/nino/http/response']['body']['status'] === 'ok' );
check( 'and does not create a duplicate entry', count( \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] ) ) === 1 );

$unsubscribeLink = \Nino\Modules\Newsletter::getUnsubscribeLink( $appData, 'jo@example.com' );
check( 'getUnsubscribeLink builds the /.newsletter unsubscribe url for a subscriber', is_string( $unsubscribeLink ) === true && str_contains( $unsubscribeLink, '/.newsletter?unsubscribe='. $pendingToken ) === true );
check( 'getUnsubscribeLink is case-insensitive about the email', \Nino\Modules\Newsletter::getUnsubscribeLink( $appData, 'JO@example.com' ) === $unsubscribeLink );
check( 'getUnsubscribeLink returns false for an unknown email', \Nino\Modules\Newsletter::getUnsubscribeLink( $appData, 'nobody@example.com' ) === false );

$badUnsubscribeRequest = visitNewsletterLink( $appData, [ 'unsubscribe' => 'not-the-token' ] );
check( 'an unsubscribe link with an unknown token answers 404 and removes nothing', $badUnsubscribeRequest['/nino/http/response']['statusCode'] === 404 && count( \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] ) ) === 1 );

$unsubscribeRequest = visitNewsletterLink( $appData, [ 'unsubscribe' => $pendingToken ] );
check( 'an unsubscribe link with the subscriber\'s token answers 200', $unsubscribeRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and removes the entry from the list', count( \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] ) ) === 0 );
check( 'and prepares the "unsubscribed" page fills', ( $appData['./nino/html/fills']['*']['[[/newsletter/page/title]]'] ?? '' ) === '[[/newsletter/page/unsubscribed/title]]' );

// Dev\Restore::_mergeNewsletterRestore() relies on this record surviving
// every unsubscribe - see its own test in dev-smoke.php for the restore side.
// A sha256 of the address, not the address itself - see
// \Nino\Modules\Newsletter's own REMOVED_PATH docblock for why
$joRemovalHash = hash( 'sha256', 'jo@example.com' );
check( 'the unsubscribe is recorded, for a later restore not to undo it', in_array( $joRemovalHash, \Nino\Filesystem::getFileContent( $appData, '/data/newsletter-removed.php', [] ), true ) === true );

$resubscribeRequest = submitNewsletter( $appData, [ 'email' => 'jo@example.com' ] );
check( 'resubscribing after an unsubscribe succeeds', $resubscribeRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and creates a fresh pending entry', count( \Nino\Filesystem::getFileContent( $appData, $subscribersPath, [] ) ) === 1 );
check( 'and clears the earlier removal record - a fresh signup is a fresh consent', in_array( $joRemovalHash, \Nino\Filesystem::getFileContent( $appData, '/data/newsletter-removed.php', [] ), true ) === false );

$bareVisitRequest = visitNewsletterLink( $appData, [] );
check( 'a bare GET /.newsletter without confirm/unsubscribe answers 404', $bareVisitRequest['/nino/http/response']['statusCode'] === 404 );

echo "\n";


// --- Mail::_hit - per-ip send rate limiting --------------------------------

echo "Mail::_hit - fixed-window rate limiting (private, exercised via Reflection)\n";

$hit = new ReflectionMethod( '\Nino\Mail', '_hit' );
$hit->setAccessible( true );

// invokeArgs() with an array of references - plain invoke() can't pass
// $appData by reference (Mail::_hit()'s first parameter), which would
// silently work against a copy and never see this test's own prior writes
function hitRateLimit( ReflectionMethod $hit, array &$appData, string $key ): bool {
	return $hit->invokeArgs( null, [ &$appData, $key ] );
}

$ratelimitPath = '/data/ratelimit.php';
$rateKey 			= '203.0.113.1';

for( $i = 1; $i <= 5; $i++ )
	check( "hit $i of 5 stays within budget (true)", hitRateLimit( $hit, $appData, $rateKey ) === true );

check( 'the 6th hit within the same window is rejected (false)', hitRateLimit( $hit, $appData, $rateKey ) === false );
check( 'a 7th hit stays rejected too - the counter keeps growing, not stuck at the cap', hitRateLimit( $hit, $appData, $rateKey ) === false );

$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
check( 'the ratelimit file recorded every hit for this key, past the cap', ( $rateState[$rateKey]['tries'] ?? 0 ) === 7 );

check( 'a different key has its own independent budget', hitRateLimit( $hit, $appData, '203.0.113.2' ) === true );

// Force this key's window to already have elapsed, same as real wall-clock
// expiry - the next hit must treat it as a fresh window, not carry the old count
$rateState[$rateKey]['reset'] = time() - 1;
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

check( 'a hit after the window elapsed starts a fresh budget (true)', hitRateLimit( $hit, $appData, $rateKey ) === true );
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
check( 'and the stale entry was dropped rather than accumulating forever', $rateState[$rateKey]['tries'] === 1 );

echo "\n";


// --- RotatingLog::prune ----------------------------------------------------

echo "RotatingLog::prune - shared dated-file sweep (Runtime, Form, Admin\\Logs, Admin\\Backup)\n";

$rotDir = $sandbox. '/rotatinglog';
mkdir( $rotDir, 0777, true );

$oldMonthly 		= $rotDir. '/logs.2020-01.php';
$freshMonthly 	= $rotDir. '/logs.'. date( 'Y-m' ). '.php';
$oldDaily 			= $rotDir. '/2020-01-01.php';
$freshDaily 		= $rotDir. '/'. date( 'Y-m-d' ). '.php';
$unparseable 		= $rotDir. '/pre-restore-1234567890.php';

foreach( [ $oldMonthly, $freshMonthly, $oldDaily, $freshDaily, $unparseable ] as $f )
	file_put_contents( $f, '<?php return [];' );

$monthlyCutoff = ( new \DateTime( 'first day of -3 months' ) )->setTime( 0, 0 );
\Nino\RotatingLog::prune( $rotDir, 'logs.', 'Y-m', '.php', $monthlyCutoff );

check( 'an old monthly bucket past the cutoff is deleted', is_file( $oldMonthly ) === false );
check( 'a fresh monthly bucket is kept', is_file( $freshMonthly ) === true );
check( 'a filename this sweep does not own (no "logs." prefix) is untouched', is_file( $unparseable ) === true );

// Regression: an empty $suffix used to hide every file from this sweep -
// substr()'s own -strlen('') is -0, and PHP has no negative zero, so that
// collapsed to a length of 0 ("take zero characters") instead of "to the
// end of the string". The date portion was always extracted as '', which
// never parses, so every file silently survived regardless of its age.
$noSuffixOld 		= $rotDir. '/nosuffix.2020-01';
$noSuffixFresh	= $rotDir. '/nosuffix.'. date( 'Y-m' );
file_put_contents( $noSuffixOld, 'x' );
file_put_contents( $noSuffixFresh, 'x' );

\Nino\RotatingLog::prune( $rotDir, 'nosuffix.', 'Y-m', '', $monthlyCutoff );

check( 'an empty suffix still deletes an old file past the cutoff', is_file( $noSuffixOld ) === false );
check( 'an empty suffix still keeps a fresh file', is_file( $noSuffixFresh ) === true );

$dailyCutoff = ( new \DateTime( '-14 days' ) )->setTime( 0, 0 );
\Nino\RotatingLog::prune( $rotDir, '', 'Y-m-d', '.php', $dailyCutoff );

check( 'an old daily file past the cutoff is deleted', is_file( $oldDaily ) === false );
check( 'a fresh daily file is kept', is_file( $freshDaily ) === true );
check( 'a same-directory file whose name does not parse as a date is left alone, not deleted', is_file( $unparseable ) === true );

echo "\n";


// --- Http header composition / locale redirects ---------------------------

echo "Http::request/response - header composition, csp, locale redirects\n";

$appData['/nino/http/routes'] = [
	'GET://' 						=> [ 'uri' => '/home', 'body' => '' ],
	'GET://rechtliches'	=> [ 'uri' => '/legal', 'body' => '', 'locale' => 'de_DE' ],
	// statusCode mirrors a route like GET://_editor declaring its own status -
	// exactly the shape that used to swallow the locale-switch redirect below
	'GET://legal'				=> [ 'uri' => '/legal', 'body' => '', 'locale' => 'en_US', 'statusCode' => 201 ],
	'GET://robots.txt'	=> [ 'uri' => '/robots.txt', 'body' => '', 'header' => [ 'Content-Type' => 'text/plain; charset=utf-8' ] ],
];

// Mirrors what \Nino\Locales::init() registers in the real request flow -
// this test builds appData by hand and never calls \Nino\init() itself
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ \Nino\Locales::class, 'callbackResponse' ] );

// Locales::init() resolves the default locale for a visitor who has not
// picked one. AppData::prepare() can only seed a hardcoded placeholder
// there (config.php isn't read yet at that point), and leaving it at that
// meant a project rendered in that hardcoded locale rather than its own
// configured native one - and, for a project that doesn't install the
// hardcoded one at all, out of a text file that does not exist: every
// per-locale [[key]] on the page unresolved
// Its own uid, so its session bucket (keyed by exactly that, see
// Runtime::getSessionValue()) cannot be one an earlier check already wrote
$nativeAppData = [ './nino/uid' => $sandbox. '-native' ];
\Nino\AppData::prepare( $nativeAppData );
$nativeAppData['./nino/filesystem/path'] = $sandbox;
$nativeAppData['/nino/locales/native'] 		= 'en_US';
$nativeAppData['/nino/locales/available'] = [ 'en_US' ];
\Nino\Locales::init( $nativeAppData );
check( 'Locales::init defaults to the configured native locale, not the seeded placeholder', \Nino\Locales::getCurrentLocale( $nativeAppData ) === 'en_US' );
check( '...which is therefore always one this project actually has text for', \Nino\Locales::verifyLocale( $nativeAppData, \Nino\Locales::getCurrentLocale( $nativeAppData ) ) === true );

// A native locale outside the available list is a broken config (_admin's
// raw Config editor can produce one) - still better answered with a locale
// the project has than with one it has no text file for
$brokenNativeAppData = [ './nino/uid' => $sandbox. '-broken-native' ];
\Nino\AppData::prepare( $brokenNativeAppData );
$brokenNativeAppData['./nino/filesystem/path'] = $sandbox;
$brokenNativeAppData['/nino/locales/native'] 		= 'fr_FR';
$brokenNativeAppData['/nino/locales/available'] = [ 'en_US', 'de_DE' ];
\Nino\Locales::init( $brokenNativeAppData );
check( 'a native locale that is not available falls back to the first available one', \Nino\Locales::getCurrentLocale( $brokenNativeAppData ) === 'en_US' );

// The default must not be written into the session - it is nobody's choice,
// and stored there it would outlive a later change of the native locale
check( 'resolving the default does not persist it as a visitor locale', \Nino\Runtime::getSessionValue( $nativeAppData, './nino/locales/current' ) === null );

function fakeRequest( array &$appData, string $uri, string $method = 'GET' ): array {
	$request = [ 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1' ];
	\Nino\Http::request( $appData, $request );
	return $request;
}

$homeRequest = fakeRequest( $appData, '/' );
$seededCsp = $homeRequest['/nino/http/response']['header']['Content-Security-Policy'] ?? '';
check( 'the response header is seeded with the default csp', str_contains( $seededCsp, "default-src 'self'" ) === true );

$appData['./nino/jstext/nonce'] = base64_encode( random_bytes( 16 ) );
\Nino\Modules\Jstext::callbackResponse( $appData, $homeRequest );
$jstextCsp = $homeRequest['/nino/http/response']['header']['Content-Security-Policy'];
check( 'Jstext appends its script-src to the csp', str_contains( $jstextCsp, "script-src 'self' 'nonce-" ) === true );
check( 'Jstext keeps the default-src while doing so', str_contains( $jstextCsp, "default-src 'self'" ) === true );
check( 'the composed csp does not start with a stray separator', str_starts_with( $jstextCsp, ';' ) === false );

$robotsRequest = fakeRequest( $appData, '/robots.txt' );
\Nino\Http::response( $appData, $robotsRequest );
check( 'a route-level header extends the response header', ( $robotsRequest['/nino/http/response']['header']['Content-Type'] ?? '' ) === 'text/plain; charset=utf-8' );
check( 'a route-level header does not wipe the seeded security headers', isset( $robotsRequest['/nino/http/response']['header']['X-Content-Type-Options'] ) === true );

$localesRedirect = fakeRequest( $appData, '/legal?/_nino/locales/current=de_DE' );
\Nino\Http::response( $appData, $localesRedirect );
check( 'a locale switch redirects with a 302 status code even on a route with its own statusCode', $localesRedirect['/nino/http/response']['statusCode'] === 302 );
check( 'the Location points at the locale variant of the page, without the route-key method prefix', ( $localesRedirect['/nino/http/response']['header']['Location'] ?? '' ) === '/rechtliches' );

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$pickerRedirect = fakeRequest( $appData, '/legal?/_nino/localepicker/current=de_DE' );
\Nino\Http::response( $appData, $pickerRedirect );
\Nino\Modules\Localepicker::callbackResponse( $appData, $pickerRedirect );
check( 'the localepicker redirects with a 302 status code too', $pickerRedirect['/nino/http/response']['statusCode'] === 302 );
check( 'and its Location points at the locale variant of the page', ( $pickerRedirect['/nino/http/response']['header']['Location'] ?? '' ) === '/rechtliches' );

echo "\n";


// --- Modules\Navigation ----------------------------------------------------

echo "Modules\\Navigation: menus built from the routes\n";

// A menu is not stored anywhere - it is computed per request from the routes
// that list themselves under its key. That is what lets a route added to
// config.php by hand be a menu entry with no tool involved, and what keeps a
// menu from ever going stale against the routes it describes.
$routesBeforeNav = $appData['/nino/http/routes'];

$appData['/nino/http/routes'] = [
	'GET://top' 				=> [ 'uri' => '/top', 			'body' => '', 'navs' => [ 'main' => 1 ] ],
	'GET://' 						=> [ 'uri' => '/home', 			'body' => '', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	'GET://kontakt' 		=> [ 'uri' => '/contact', 	'body' => '', 'navs' => [ 'main' => 5 ] ],
	'GET://impressum' 	=> [ 'uri' => '/legal', 		'body' => '', 'navs' => [ 'footer' => 5 ] ],
	'GET://intern' 			=> [ 'uri' => '/intern', 		'body' => '' ],
	'GET://namenlos' 		=> [ 'uri' => '/namenlos', 	'body' => '', 'navs' => [ 'main' => 5 ] ],
	'GET://rechtliches' => [ 'uri' => '/legal-de', 	'body' => '', 'navs' => [ 'footer' => 5 ], 'locale' => 'de_DE' ],
	'POST://.form' 			=> [ 'uri' => '/.form', 		'body' => '', 'navs' => [ 'main' => 1 ] ],
];

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
\Nino\Html::addFills( $appData, [
	'/webpage/home/name' 			=> 'Home',
	'/webpage/contact/name' 	=> 'Contact',
	'/webpage/legal/name' 		=> 'Legal',
	'/webpage/top/name' 			=> 'Top',
], 'en_US' );
\Nino\Html::addFills( $appData, [
	'/webpage/legal-de/name' 	=> 'Rechtliches',
	'/webpage/home/name' 			=> 'Start',
], 'de_DE' );

$mainLines = \Nino\Modules\Navigation::routeLines( $appData, 'main' );

check( 'a menu collects every route that lists itself under its key', $mainLines === [ '/top:Top', '/:Home', '/kontakt:Contact' ] );
check( 'a lower priority sorts first, equal priorities keep the routes\' own order', $mainLines[0] === '/top:Top' );
check( 'a route with no membership stays out', in_array( '/intern:', $mainLines, true ) === false && str_contains( implode( '', $mainLines ), 'intern' ) === false );
check( 'a route nobody named stays out rather than rendering an empty link', str_contains( implode( '', $mainLines ), 'namenlos' ) === false );
check( 'a POST route is never a menu entry', str_contains( implode( '', $mainLines ), '.form' ) === false );

$footerLines = \Nino\Modules\Navigation::routeLines( $appData, 'footer' );
check( 'a second menu is an independent selection of the same routes', $footerLines === [ '/:Home', '/impressum:Legal' ] );

// A locale-gated route only exists for its own locale (same rule
// Http::findRouteUri() applies), so it only belongs in that locale's menu
check( 'a locale-gated route stays out of another locale\'s menu', str_contains( implode( '', $footerLines ), 'rechtliches' ) === false );

\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
$footerDe = \Nino\Modules\Navigation::routeLines( $appData, 'footer' );
check( '...and appears in its own', in_array( '/rechtliches:Rechtliches', $footerDe, true ) === true );
check( 'every title comes from the current locale', in_array( '/:Start', $footerDe, true ) === true );

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );

$navHtml = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'the shortcode renders one <li> per entry', substr_count( $navHtml, '<li>' ) === 3 );
check( '...in menu order', strpos( $navHtml, 'Top' ) < strpos( $navHtml, 'Home' ) );

$navMixed = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main', 'content' => '/extra:Extra' ] );
check( 'a hand-written line is appended after the generated ones', substr_count( $navMixed, '<li>' ) === 4 && strpos( $navMixed, 'Extra' ) > strpos( $navMixed, 'Contact' ) );

$navManual = \Nino\Modules\Navigation::doShortcode( $appData, [ 'content' => "/a:A\n/b:B" ] );
check( 'a menu written entirely by hand still works, with no nav argument at all', substr_count( $navManual, '<li>' ) === 2 );
check( 'an empty shortcode renders nothing', \Nino\Modules\Navigation::doShortcode( $appData, [] ) === '' );
check( 'an unknown menu key renders nothing', \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'nope' ] ) === '' );

$appData['/nino/http/routes'] = $routesBeforeNav;

// Http::output() itself exit()s, so the header-finalizing part it delegates
// to is exercised directly via Reflection instead (same approach as
// Mail::_hit above). invokeArgs() with an array of references - plain
// invoke() can't pass $request by reference, which would silently run
// against a copy and never show this test its own mutations
$finalizeResponse = new ReflectionMethod( '\Nino\Http', '_finalizeResponse' );
$finalizeResponse->setAccessible( true );

$cookieRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [ 'Set-Cookie' => 'sid=abc123; HttpOnly' ], 'body' => '' ] ];
$finalizeResponse->invokeArgs( null, [ &$cookieRequest ] );
check( 'a response header outside the request-side whitelist (Set-Cookie) is not dropped', ( $cookieRequest['/nino/http/response']['header']['Set-Cookie'] ?? null ) === 'sid=abc123; HttpOnly' );

$downloadRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [ 'Content-Disposition' => 'attachment; filename="export.json"' ], 'body' => '' ] ];
$finalizeResponse->invokeArgs( null, [ &$downloadRequest ] );
check( 'another whitelist-only-on-the-request-side header (Content-Disposition) survives too', ( $downloadRequest['/nino/http/response']['header']['Content-Disposition'] ?? null ) === 'attachment; filename="export.json"' );

check( 'Location (on the request-side whitelist too) still survives', array_key_exists( 'Location', \Nino\Http::filterHeaderFields( [ 'Location' => '/rechtliches' ] ) ) === true );

check( 'a bare [assets] shortcode without argument renders nothing instead of erroring', \Nino\Modules\Assets::doShortcode( $appData, [] ) === '' );

echo "\n";


// --- Modules::callModules() - path resolution for a config-listed module --

echo "Modules::callModules() resolves a module class regardless of a leading backslash\n";

// __DIR__ inside callModules() is _nino/ (where it's defined), not this
// test file's own directory - the dummy module has to live where that
// method will actually look for it
$dummyModuleRoot = __DIR__. '/../_nino/KernelSmokeDummyModules';
$dummyModuleDir = $dummyModuleRoot. '/DummyCallModulesFix';
mkdir( $dummyModuleDir, 0777, true );
file_put_contents( $dummyModuleDir. '/DummyCallModulesFix.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DummyCallModulesFix {
		public static bool $called = false;
		public static function init( array &$appData ): void {
			self::$called = true;
		}
	}
}
PHP
);

// No leading backslash - config.php can write either form, both name the
// same class, and PHP normalizes it away before the autoloader ever sees
// the name (see the spl_autoload_register() call at the bottom of Nino.php)
$appData['/nino/modules'] = [ 'KernelSmokeDummyModules\DummyCallModulesFix' ];
\Nino\Modules::callModules( $appData, 'init' );
check( 'a module class name without a leading backslash still resolves and loads', \KernelSmokeDummyModules\DummyCallModulesFix::$called === true );

unset( $appData['/nino/modules'] );
\Nino\Modules::callModules( $appData, 'init' );
check( 'callModules() does not warn/crash when /nino/modules is entirely unset', true );

// A second dummy, never listed in '/nino/modules' at all - proves a
// project's own custom module class now autoloads on a direct reference
// too, not just when routed through callModules() first. Before the
// spl_autoload_register() switch, only the ten built-in \Nino\Modules\*
// classes had that property (require_once'd unconditionally by
// Nino.php); a custom module's class only ever got loaded via
// callModules()'s own lazy-load branch - a direct call anywhere else,
// same as every \Nino\Modules\* call in this test file, would have been
// a fatal "class not found".
$directDummyDir = $dummyModuleRoot. '/DummyDirectAutoload';
mkdir( $directDummyDir, 0777, true );
file_put_contents( $directDummyDir. '/DummyDirectAutoload.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DummyDirectAutoload {
		public static function ping(): string {
			return 'pong';
		}
	}
}
PHP
);

check( 'a custom module class autoloads on a direct reference, without ever going through callModules()', \KernelSmokeDummyModules\DummyDirectAutoload::ping() === 'pong' );

\Nino\Filesystem::removeDir( $dummyModuleRoot );

echo "\n";


// --- Runtime::handleError() - severity-aware, not unconditionally fatal ---

echo "Runtime::handleError() - a NON_FATAL_LEVELS trigger_error() returns instead of exiting\n";

// Regression: handleError() used to exit() for every error level alike,
// including the default E_USER_NOTICE trigger_error() calls. Every
// "return ! trigger_error( ... )" in Elements and every "catch
// (\Throwable $e) { trigger_error(...); return ...; }" in Newsletter/Form
// is written assuming the handler returns and execution continues past the
// trigger_error() call - before this fix, none of them ever did outside
// Admin\Elements::apiSave()'s own temporary set_error_handler() override,
// which shadows the real one. A genuine exit() can't be observed from
// inside this same process without taking the whole suite down with it, so
// this spawns a child php process per case and checks what actually
// survived to run.
function runIsolated( string $body ): array {
	// php -r's code is implicitly already inside a php open/close pair,
	// unlike a regular script file - a literal '<?php' prefix here is a
	// syntax error
	$script = 'require '. var_export( __DIR__. '/../_nino/Nino.php', true ). '; '. $body;
	$descriptors = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
	$process = proc_open( [ PHP_BINARY, '-r', $script ], $descriptors, $pipes );
	$stdout = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exitCode = proc_close( $process );
	return [ 'stdout' => $stdout, 'exitCode' => $exitCode ];
}

$bootstrap = '
	$sandbox = sys_get_temp_dir(). "/nino-handleerror-". bin2hex( random_bytes( 4 ) );
	mkdir( $sandbox, 0755, true );
	$appData = [ "./nino/uid" => $sandbox ];
	\Nino\AppData::prepare( $appData );
	$appData["./nino/filesystem/path"] = $sandbox;
	$appData["./nino/filesystem/configpath"] = $sandbox;
	$appData["/nino/error/log"] = false;
	$appData["/nino/error/display"] = false;
	\Nino\Runtime::init( $appData );
';

$notice = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "a plain notice", E_USER_NOTICE );
	echo "after\n";
' );
check( 'a default-level (E_USER_NOTICE) trigger_error() lets execution continue past it', trim( $notice['stdout'] ) === "before\nafter" );
check( 'and the request does not exit early', $notice['exitCode'] === 0 );

$warning = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "a plain warning", E_USER_WARNING );
	echo "after\n";
' );
check( 'an E_USER_WARNING trigger_error() also lets execution continue past it', trim( $warning['stdout'] ) === "before\nafter" );

$fatal = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "a real failure", E_USER_ERROR );
	echo "after\n";
' );
check( 'an E_USER_ERROR trigger_error() still terminates the request', trim( $fatal['stdout'] ) === 'before' );

$engineWarning = runIsolated( $bootstrap. '
	echo "before\n";
	$undefined[0];
	echo "after\n";
' );
check( 'an engine-raised warning (not one of our own E_USER_* calls) still terminates the request', trim( $engineWarning['stdout'] ) === 'before' );

echo "\n";


// --- Filesystem's own I/O calls stay non-fatal under the real handler -----

echo "Filesystem - a real fopen()/mkdir()/unlink() failure returns false, doesn't 500\n";

// Regression: fopen()/fwrite()/fflush()/rename()/mkdir()/unlink() raise a
// plain E_WARNING on failure, which - same as the engine-raised warning
// above - is fatal under the real handler unless the call itself is @-
// silenced. Without @, the warning kills the request before the
// "=== false" checks right below each call ever run: _writeFile()'s "short
// write has to fail here" comment, lockFile()'s "could not lock" return,
// and writeContentData()'s own error message were all unreachable the same
// way the Newsletter catch blocks were - just one layer down, at the
// engine level instead of trigger_error()
$writeFailure = runIsolated( $bootstrap. '
	echo "before\n";
	$brokenBase = $sandbox. "/not-a-dir";
	file_put_contents( $brokenBase, "x" );
	$appData["./nino/filesystem/configpath"] = $brokenBase;
	$result = \Nino\Filesystem::putFileContent( $appData, "/config.php", [ "a" => 1 ] );
	echo "after:". var_export( $result, true );
' );
check( 'a real fopen() failure (configpath base is a file, not a dir) returns false instead of terminating the request', trim( $writeFailure['stdout'] ) === 'before' . "\n" . 'after:false' );

// forceDir()'s own is_dir() check can't be driven into the race from a
// single synchronous script (by definition: TOCTOU is the gap between two
// processes, not two lines in one) - this proves the underlying mechanism
// forceDir()'s @mkdir() relies on instead: mkdir() on a directory that
// exists because something else (another request) created it first
$mkdirRace = runIsolated( $bootstrap. '
	mkdir( $sandbox. "/already-there", 0755, true );
	echo "before\n";
	@mkdir( $sandbox. "/already-there", 0755, true );
	echo "after\n";
' );
check( '@mkdir() on a directory another request already created (forceDir()\'s TOCTOU gap) does not terminate the request', trim( $mkdirRace['stdout'] ) === "before\nafter" );

// The exact race RotatingLog::prune() (and removeDir(), Images::delete())
// guard against - two concurrent deletes reaching the same since-removed
// file - cannot be forced deterministically without actually forking a
// second process at the right instant; this proves the underlying
// mechanism their shared @unlink() relies on instead: unlink() on a file
// that is already gone by the time it runs
$unlinkRace = runIsolated( $bootstrap. '
	$f = $sandbox. "/already-gone.txt";
	file_put_contents( $f, "x" );
	unlink( $f );
	echo "before\n";
	@unlink( $f );
	echo "after\n";
' );
check( '@unlink() on a file another request already removed (the race prune()/removeDir()/Images::delete() all guard against) does not terminate the request', trim( $unlinkRace['stdout'] ) === "before\nafter" );

echo "\n";


// --- Filesystem::path - public vs private roots --------------------------

echo "Filesystem::path - which root a virtual path resolves against\n";

// Naming the split is the whole point of this: a webserver-facing directory
// and a never-served one stop being the same path. Nothing has moved yet -
// the private root still defaults to the project root - so every one of
// these resolves exactly where a plain concatenation used to put it
$pathAppData = $appData;
$pathAppData['./nino/filesystem/path'] 				= '/srv/site';
$pathAppData['./nino/filesystem/configpath'] 	= '/srv/site';
$pathAppData['./nino/filesystem/contentpath'] = '/srv/site/content';
$pathAppData['./nino/filesystem/privatepath'] = '/srv/site';

foreach( [ '/images/hero.jpg', '/assets/style.css', '/.cache/script.js', '/_editor/x' ] as $public )
	check( "$public stays on the public root", \Nino\Filesystem::path( $pathAppData, $public ) === '/srv/site'. $public );

foreach( \Nino\Filesystem::PRIVATE_DIRS as $private )
	check( "$private resolves against the private root", \Nino\Filesystem::path( $pathAppData, $private ) === '/srv/site'. $private );

check( 'a path under a private directory follows it', \Nino\Filesystem::path( $pathAppData, '/text/de_DE.php' ) === '/srv/site/text/de_DE.php' );
check( 'a directory merely starting with a private name does not', \Nino\Filesystem::path( $pathAppData, '/textures/x.png' ) === '/srv/site/textures/x.png' );
check( 'everything under /content follows the content root', \Nino\Filesystem::path( $pathAppData, '/content/.auth/pw.php' ) === '/srv/site/content/.auth/pw.php' );

// Moving the private root moves every private path with it, and nothing else
$pathAppData['./nino/filesystem/privatepath'] = '/var/nino-private';

check( 'config.php keeps following its own configpath, not the private root', \Nino\Filesystem::path( $pathAppData, '/config.php' ) === '/srv/site/config.php' );
check( 'a moved private root takes the templates with it', \Nino\Filesystem::path( $pathAppData, '/templates/page-home.tpl' ) === '/var/nino-private/templates/page-home.tpl' );
check( '...and text, elements and data', [
	\Nino\Filesystem::path( $pathAppData, '/text' ),
	\Nino\Filesystem::path( $pathAppData, '/elements' ),
	\Nino\Filesystem::path( $pathAppData, '/data' ),
] === [ '/var/nino-private/text', '/var/nino-private/elements', '/var/nino-private/data' ] );
check( 'but leaves the public ones where the webserver reaches them', \Nino\Filesystem::path( $pathAppData, '/images/hero.jpg' ) === '/srv/site/images/hero.jpg' );

// config.php keeps its own, older override - it wins over the private root
$pathAppData['./nino/filesystem/configpath'] = '/etc/nino';
check( 'NINO_CONFIG_DIR still wins for config.php alone', \Nino\Filesystem::path( $pathAppData, '/config.php' ) === '/etc/nino/config.php' );

echo "\n";


// --- Cleanup ------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
