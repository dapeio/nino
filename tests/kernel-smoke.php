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

// The features root is the fixture directory: a checkout ships no feature of
// its own (they come from dapeio/nino-features), and the autoload check
// below needs one to resolve. Before the kernel loads, like every constant
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

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
	$lockPath	= $sandbox. '/private/data/.locks/'. sha1( $filename ). '.lock';
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

echo "Sandbox: $sandbox\n\n";


// --- Elements ------------------------------------------------------------

echo "Elements::insertElementType / insertElement / updateElement / deleteElement\n";

$model = [
	'title' => [ 'type' => 'string', 'locale' => true ],
	'views' => [ 'type' => 'integer', 'default' => 0 ],
	'price' => [ 'type' => 'double' ],
	'rating' => [ 'type' => 'double', 'default' => 5 ],
	'invalidDefault' => [ 'type' => 'integer', 'default' => 'not-an-integer' ],
];

check( 'insertElementType creates a new type', \Nino\Elements::insertElementType( $appData, '/testtype', $model ) !== false );
check( 'insertElementType rejects a duplicate type', \Nino\Elements::insertElementType( $appData, '/testtype', $model ) === false );
check( 'element type I/O uses one canonical cache path', isset( $appData['./nino/filesystem/cache']['/elements/testtype.php'] ) === true && isset( $appData['./nino/filesystem/cache']['/elements//testtype.php'] ) === false );
$storedModel = \Nino\Elements::getElementModel( $appData, '/testtype' );
check( 'a whole-number double default is normalized to a double', gettype( $storedModel['rating']['default'] ?? null ) === 'double' );
check( 'an invalid optional-field default is not persisted into the model', isset( $storedModel['invalidDefault'] ) === false );

$notADirectory = $sandbox. '/not-a-private-directory';
file_put_contents( $notADirectory, 'x' );
$typeWriteFailure = $appData;
$typeWriteFailure['./nino/filesystem/cache'] = [];
$typeWriteFailure['./nino/filesystem/contentpath'] = $notADirectory;
check( 'insertElementType reports a failed type-file write', \Nino\Elements::insertElementType( $typeWriteFailure, '/cannot-write', $model ) === false );
unlink( $notADirectory );

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

// An image is exempt from "required" on purpose: the element form uploads
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
check( 'insertElement releases the type file lock after a required-field validation failure', probeLockFree( $sandbox, '/elements/reqtype.php' ) === true );

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

$resetDefault = \Nino\Elements::updateElement( $appData, '/testtype/item1', [ 'views' => 0 ], 'en_US' );
$typeOnDisk = include \Nino\Filesystem::path( $appData, '/elements/testtype.php' );
check( 'updating a field back to its model default returns that default', ( $resetDefault['views'] ?? null ) === 0 );
check( 'resetting to a default removes the stale explicit override on disk', isset( $typeOnDisk['*']['item1']['views'] ) === false );
check( 'the inherited default is visible in every locale', \Nino\Elements::getElement( $appData, '/testtype/item1', 'de_DE' )['views'] === 0 );
\Nino\Elements::updateElement( $appData, '/testtype/item1', [ 'views' => 7 ], 'en_US' );

// A field callback may normalize an element URI from a supplied slug. The
// triggering update is intentionally partial: all omitted localized/global
// fields must move with the URI instead of disappearing with the old key.
\Nino\Callbacks::registerCallback( $appData, '/tests/elements/slug-uri', function( array &$appData, array &$data ): array {
	$data['.uri'] = '/renametest/'. $data['slug'];
	return $data;
} );
\Nino\Elements::insertElementType( $appData, '/renametest', [
	'slug' 			=> [ 'type' => 'string', 'callbacks' => [ '/tests/elements/slug-uri' ] ],
	'title' 		=> [ 'type' => 'string', 'locale' => true ],
	'description' => [ 'type' => 'string', 'locale' => true ],
	'views' 			=> [ 'type' => 'integer' ],
] );
\Nino\Elements::insertElement( $appData, '/renametest/old', [ 'slug' => 'old', 'title' => 'Deutsch', 'description' => 'Beschreibung', 'views' => 4 ], 'de_DE' );
\Nino\Elements::updateElement( $appData, '/renametest/old', [ 'title' => 'English', 'description' => 'Description' ], 'en_US' );
$renamed = \Nino\Elements::updateElement( $appData, '/renametest/old', [ 'slug' => 'new' ], 'de_DE' );
check( 'a callback-driven partial URI rename succeeds at the destination', is_array( $renamed ) === true && ( $renamed['.uri'] ?? null ) === '/renametest/new' );
check( 'a URI rename preserves omitted localized fields in the updated locale', $renamed['title'] === 'Deutsch' && $renamed['description'] === 'Beschreibung' );
check( 'a URI rename preserves omitted fields in every other locale too', ( \Nino\Elements::getElement( $appData, '/renametest/new', 'en_US' )['description'] ?? null ) === 'Description' );
check( 'a URI rename preserves omitted global fields', ( $renamed['views'] ?? null ) === 4 );
check( 'a URI rename removes the old element key', \Nino\Elements::getElement( $appData, '/renametest/old', '*' ) === false );

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

// A type uri is written with or without its slashes wherever one is accepted
// (getElementFile() trims its own), but the hits were built by gluing the uri
// as given to the element's name - so '/wildcardtest/' asked for
// '/wildcardtest//a' and found nothing at all
$slashSpellings = [];
foreach( [ '/wildcardtest', '/wildcardtest/', 'wildcardtest', 'wildcardtest/' ] as $spelling )
	$slashSpellings[$spelling] = array_column( \Nino\Elements::queryElements( $appData, $spelling, [], 'de_DE', [] ), '.uri' );
check( 'a type uri is the same type however its slashes are written', $slashSpellings === array_fill_keys(
	[ '/wildcardtest', '/wildcardtest/', 'wildcardtest', 'wildcardtest/' ], [ '/wildcardtest/a', '/wildcardtest/b' ] ) );

// A boolean is stored as one, and '(string) false' is '' - so a query for the
// off state matched nothing, whatever it was written as, while the on state
// worked. The two spellings a query can use for a boolean are 1/0
\Nino\Filesystem::putFileContent( $appData, '/elements/flagtest.php', [
	'title' 	=> 'Flag Test',
	'model' 	=> [ 'live' => [ 'type' => 'boolean' ] ],
	'*' 			=> [ '*' => [], 'on' => [ 'live' => true ], 'off' => [ 'live' => false ] ],
] );
check( 'a query finds the elements whose boolean is off', array_column( \Nino\Elements::queryElements( $appData, '/flagtest', [ 'live' => '0' ], '*', [] ), '.uri' ) === [ '/flagtest/off' ] );
check( '...as well as the ones whose boolean is on', array_column( \Nino\Elements::queryElements( $appData, '/flagtest', [ 'live' => '1' ], '*', [] ), '.uri' ) === [ '/flagtest/on' ] );

// The element before and after one, in the order the type lists them - the
// type file's order, which is what the panel's list and an [elements] block
// without sort show - or in the caller's sort and subset. Past either end,
// off the list and for a uri that is no element: $return
check( 'Elements::prevElement() and nextElement() exist', method_exists( '\Nino\Elements', 'prevElement' ) === true && method_exists( '\Nino\Elements', 'nextElement' ) === true );
if( method_exists( '\Nino\Elements', 'prevElement' ) === true ) {
	\Nino\Filesystem::putFileContent( $appData, '/elements/walktest.php', [
		'title' 	=> 'Walk Test',
		'model' 	=> [ 'title' => [ 'type' => 'string', 'locale' => true ], 'date' => [ 'type' => 'string' ], 'live' => [ 'type' => 'boolean' ] ],
		'*' 			=> [ '*' => [], 'a' => [ 'date' => '2026-03-01', 'live' => true ], 'b' => [ 'date' => '2026-01-01', 'live' => false ], 'c' => [ 'date' => '2026-02-01', 'live' => true ] ],
		'de_DE' 	=> [ 'a' => [ 'title' => 'A' ], 'b' => [ 'title' => 'B' ], 'c' => [ 'title' => 'C' ] ],
		// d exists in one translation only, with no global entry
		'en_US' 	=> [ 'a' => [ 'title' => 'A en' ], 'b' => [ 'title' => 'B en' ], 'c' => [ 'title' => 'C en' ], 'd' => [ 'title' => 'D en' ] ],
	] );
	$walk = function( string $method, string $uri, string $locale = 'de_DE', array $options = [] ) use ( &$appData ) {
		$hit = \Nino\Elements::$method( $appData, $uri, $locale, false, $options );
		return is_array( $hit ) === true ? ( $hit['.uri'] ?? '?' ) : $hit;
	};
	check( 'nextElement() is the element after, in the type file\'s order', $walk( 'nextElement', '/walktest/a' ) === '/walktest/b' && $walk( 'nextElement', '/walktest/b' ) === '/walktest/c' );
	check( 'prevElement() is the element before', $walk( 'prevElement', '/walktest/b' ) === '/walktest/a' && $walk( 'prevElement', '/walktest/c' ) === '/walktest/b' );
	check( 'past either end there is nothing', $walk( 'prevElement', '/walktest/a' ) === false && $walk( 'nextElement', '/walktest/c' ) === false );
	check( 'the neighbour comes resolved for the locale asked', ( \Nino\Elements::nextElement( $appData, '/walktest/a', 'de_DE' )['title'] ?? null ) === 'B' && ( \Nino\Elements::nextElement( $appData, '/walktest/a', 'en_US' )['title'] ?? null ) === 'B en' );
	check( 'the list walked is the locale\'s: an element one translation alone holds is its last neighbour there, and absent elsewhere', $walk( 'nextElement', '/walktest/c', 'en_US' ) === '/walktest/d' && $walk( 'nextElement', '/walktest/c', 'de_DE' ) === false && $walk( 'nextElement', '/walktest/c', '*' ) === '/walktest/d' );
	check( 'sort under $options walks the sorted list', $walk( 'nextElement', '/walktest/b', 'de_DE', [ 'sort' => 'date' ] ) === '/walktest/c' && $walk( 'prevElement', '/walktest/a', 'de_DE', [ 'sort' => 'date' ] ) === '/walktest/c' && $walk( 'nextElement', '/walktest/a', 'de_DE', [ 'sort' => '-date' ] ) === '/walktest/c' );
	check( 'query under $options walks the subset, and an element outside it has no place in it', $walk( 'nextElement', '/walktest/a', 'de_DE', [ 'query' => [ 'live' => '1' ] ] ) === '/walktest/c' && $walk( 'prevElement', '/walktest/b', 'de_DE', [ 'query' => [ 'live' => '1' ] ] ) === false );
	check( 'a uri is the same element however its slashes are written', $walk( 'nextElement', 'walktest/a/' ) === '/walktest/b' );
	check( 'an unknown element, an unknown type and a uri without a type separator answer $return, quietly', $walk( 'nextElement', '/walktest/zzz' ) === false && $walk( 'nextElement', '/nosuchtype/a' ) === false && $walk( 'nextElement', '/walktest' ) === false && \Nino\Elements::nextElement( $appData, '/walktest/c', 'de_DE', 'none' ) === 'none' );
}

// What a write refuses is one check, valueError(), and whoever validates
// ahead of a write asks it - the Translations import kept a copy that
// compared loosely, so '1' passed its whitelist of '01' and the kernel then
// refused the whole element. Strict lists, the (array) cast a hand-written
// model relies on, a whole number for a double, and a reference's rules
check( 'Elements::valueError() exists, and the kernel names its field types once', method_exists( '\\Nino\\Elements', 'valueError' ) === true && defined( '\\Nino\\Elements::FIELD_TYPES' ) === true );
if( method_exists( '\\Nino\\Elements', 'valueError' ) === true ) {
	$refuses = fn( array $field, mixed $value ): bool => \Nino\Elements::valueError( $field, $value ) !== null;
	check( 'a whitelist is a list of values: \'1\' is not \'01\', and \'01\' is', $refuses( [ 'type' => 'string', 'whitelist' => [ '01' ] ], '1' ) === true && $refuses( [ 'type' => 'string', 'whitelist' => [ '01' ] ], '01' ) === false );
	check( 'a list written as one value is that one value', $refuses( [ 'type' => 'string', 'whitelist' => '01' ], '01' ) === false && $refuses( [ 'type' => 'string', 'blacklist' => 'x' ], 'x' ) === true );
	check( 'a blacklist refuses strictly too', $refuses( [ 'type' => 'integer', 'blacklist' => [ 1 ] ], 1 ) === true && $refuses( [ 'type' => 'integer', 'blacklist' => [ '1' ] ], 1 ) === false );
	check( 'the type is checked as the write checks it: a whole number is a double, a fraction is no integer', $refuses( [ 'type' => 'double' ], 5 ) === false && $refuses( [ 'type' => 'integer' ], 5.5 ) === true && $refuses( [ 'type' => 'date' ], '2026-01-01' ) === false );
	check( 'a reference may only point into its type, and an empty one is no reference', $refuses( [ 'type' => 'element', 'elementType' => 'tags' ], '/tags/a' ) === false && $refuses( [ 'type' => 'element', 'elementType' => 'tags' ], '/people/a' ) === true && $refuses( [ 'type' => 'element', 'elementType' => 'tags' ], '' ) === false );
	check( 'a list reference refuses a foreign uri, a duplicate and one entry too many', $refuses( [ 'type' => 'element', 'elementType' => 'tags', 'multiple' => 2 ], [ '/tags/a', '/tags/b' ] ) === false && $refuses( [ 'type' => 'element', 'elementType' => 'tags', 'multiple' => 2 ], [ '/tags/a', '/people/b' ] ) === true && $refuses( [ 'type' => 'element', 'elementType' => 'tags', 'multiple' => 2 ], [ '/tags/a', '/tags/a' ] ) === true && $refuses( [ 'type' => 'element', 'elementType' => 'tags', 'multiple' => 2 ], [ '/tags/a', '/tags/b', '/tags/c' ] ) === true );
	check( 'the answer names what the write would say', str_contains( (string) \Nino\Elements::valueError( [ 'type' => 'string', 'whitelist' => [ '01' ] ], '1', 'code', '/t/x' ), 'not whitelisted' ) === true );
	// The one list of field types: a model keeps every type in it and nothing else
	$everyType = [];
	foreach( \Nino\Elements::FIELD_TYPES as $fieldType )
		$everyType[ 'f_'. $fieldType ] = $fieldType === 'element' ? [ 'type' => 'element', 'elementType' => 'tags' ] : [ 'type' => $fieldType ];
	$everyType['f_video'] = [ 'type' => 'video' ];
	\Nino\Elements::insertElementType( $appData, '/typelisttest', $everyType );
	check( 'a model keeps every field type the kernel names, and drops one it does not', array_keys( \Nino\Elements::getElementModel( $appData, '/typelisttest' ) ) === array_map( fn( string $t ): string => 'f_'. $t, \Nino\Elements::FIELD_TYPES ) );
}

// The type file and the elements read out of it shared one cache map, keyed
// by uri - so once a type had been read, asking for the type uri as if it
// were an element handed back that type's whole locale bucket, every element
// in it, as one element
\Nino\Elements::queryElements( $appData, '/wildcardtest', [], 'de_DE', [] );
check( 'a type uri is not an element, however often the type has been read', \Nino\Elements::getElement( $appData, '/wildcardtest', 'de_DE' ) === false );

// The existence check for an insert stood outside the lock the write takes,
// so two requests inserting the same uri both passed it and the second one
// merged its fields into the first one's element. Checked where the write
// happens now, which is the only place it can be checked
\Nino\Elements::insertElementType( $appData, '/racetest', [ 'title' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/racetest/one', [ 'title' => 'First' ], '*' );
// insertElement()'s own look happens before the lock, so the interleaving is
// what it cannot see: the write itself is driven directly here, which is
// exactly the state the second request arrives in
$writeElement = new ReflectionMethod( '\Nino\Elements', '_writeElementData' );
$writeElement->setAccessible( true );

$raceWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$raceWarnings ): bool { $raceWarnings[] = $message; return true; } );
$secondInsert = $writeElement->invokeArgs( null, [ &$appData, '/racetest/one', [ 'title' => 'Second' ], '*', false ] );
restore_error_handler();
check( 'inserting an element that is already there is refused where the write happens, not only before it', $secondInsert === false
	&& str_contains( implode( ' ', $raceWarnings ), 'already exists' ) === true );
check( '...and leaves the one that is there as it was', ( \Nino\Elements::getElement( $appData, '/racetest/one', '*' )['title'] ?? null ) === 'First' );

// --- Elements::queryElementValues() - distinct values of one model key ---

\Nino\Elements::insertElementType( $appData, '/valuetest', [
	'title'		=> [ 'type' => 'string' ],
	'category'	=> [ 'type' => 'string', 'options' => [ 'Consulting', 'Design', 'Development' ] ],
	'tag'			=> [ 'type' => 'string' ], // no options - a free field
] );
\Nino\Elements::insertElement( $appData, '/valuetest/a', [ 'title' => 'A', 'category' => 'Design', 'tag' => 'red' ], '*' );
\Nino\Elements::insertElement( $appData, '/valuetest/b', [ 'title' => 'B', 'category' => 'Design', 'tag' => 'blue' ], '*' );
\Nino\Elements::insertElement( $appData, '/valuetest/c', [ 'title' => 'C', 'category' => 'Consulting', 'tag' => 'red' ], '*' );

$categoryValues = \Nino\Elements::queryElementValues( $appData, '/valuetest', 'category', [], '*', [] );
check( 'queryElementValues returns declared options in model order', array_column( $categoryValues, 'value' ) === [ 'Consulting', 'Design', 'Development' ] );
check( 'queryElementValues counts matching elements per declared value, including a count of 0', array_column( $categoryValues, 'count' ) === [ 1, 2, 0 ] );

$tagValues = \Nino\Elements::queryElementValues( $appData, '/valuetest', 'tag', [], '*', [] );
check( 'queryElementValues falls back to observed values for a field without options', array_column( $tagValues, 'value' ) === [ 'red', 'blue' ] );
check( '...with counts of how often each was actually used', array_column( $tagValues, 'count' ) === [ 2, 1 ] );

$redOnly = \Nino\Elements::queryElementValues( $appData, '/valuetest', 'category', [ 'tag' => 'red' ], '*', [] );
check( 'queryElementValues scopes its counts to the given query, exactly like queryElements()', array_column( $redOnly, 'count' ) === [ 1, 1, 0 ] );

check( 'queryElementValues returns the given default for an unknown key', \Nino\Elements::queryElementValues( $appData, '/valuetest', 'nope', [], '*', 'fallback' ) === 'fallback' );
check( 'queryElementValues returns the given default for an unknown type', \Nino\Elements::queryElementValues( $appData, '/no-such-type', 'category', [], '*', 'fallback' ) === 'fallback' );

\Nino\Elements::insertElementType( $appData, '/emptyvaluetest', [ 'category' => [ 'type' => 'string', 'options' => [ 'X', 'Y' ] ] ] );
check( 'queryElementValues returns every declared option at count 0 when no element exists yet',
	\Nino\Elements::queryElementValues( $appData, '/emptyvaluetest', 'category', [], '*', [] ) === [ [ 'value' => 'X', 'count' => 0 ], [ 'value' => 'Y', 'count' => 0 ] ] );

\Nino\Elements::insertElementType( $appData, '/novaluetest', [ 'free' => [ 'type' => 'string' ] ] );
check( 'queryElementValues returns the given default when a field has neither options nor any elements yet',
	\Nino\Elements::queryElementValues( $appData, '/novaluetest', 'free', [], '*', 'fallback' ) === 'fallback' );

// Regression: an observed value is collected as an array key, and php turns an
// integer-like key back into an int on the way out. A portfolio filtered by
// year ('2024') therefore used to leave here as int, which broke the
// documented [ 'value' => string ] shape and made the shortcode's own
// strnatcasecmp() sort fatal under strict_types - a 500 for the whole page
\Nino\Elements::insertElementType( $appData, '/numericvaluetest', [
	'year'	=> [ 'type' => 'string' ],
	'count'	=> [ 'type' => 'integer' ],
] );
\Nino\Elements::insertElement( $appData, '/numericvaluetest/a', [ 'year' => '2024', 'count' => 7 ], '*' );
\Nino\Elements::insertElement( $appData, '/numericvaluetest/b', [ 'year' => '2025', 'count' => 7 ], '*' );
\Nino\Elements::insertElement( $appData, '/numericvaluetest/c', [ 'year' => '2024', 'count' => 3 ], '*' );

$yearValues = \Nino\Elements::queryElementValues( $appData, '/numericvaluetest', 'year', [], '*', [] );
check( 'queryElementValues keeps a numeric observed value a string, not an int array key',
	array_column( $yearValues, 'value' ) === [ '2024', '2025' ] );
check( '...and still counts it correctly', array_column( $yearValues, 'count' ) === [ 2, 1 ] );
check( 'the same holds for a genuinely numeric field type',
	array_column( \Nino\Elements::queryElementValues( $appData, '/numericvaluetest', 'count', [], '*', [] ), 'value' ) === [ '7', '3' ] );

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

/*	Deleting it again is still a success - "already gone" is what was asked
	for - and now it is a success that does nothing. It used to unset nothing,
	write the whole type file back regardless (a temp file, a rename, an
	opcache invalidation and a new mtime that invalidates every cached read of
	that file elsewhere), and then fire '/nino/elements/committed' with
	operation 'delete' - so a module keeping derived data was told about a
	deletion that had not happened	*/
$committed = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/committed', static function( array &$appData, array &$change ) use ( &$committed ): void { $committed[] = $change['operation']. ' '. $change['uri']; } );

/*	Judged by the inode, not by the mtime or the bytes: a rewrite that puts
	the same content back is invisible in both (mtime has one-second
	resolution), while \Nino\Filesystem::_writeFile() replaces the file by
	rename()ing a temp file over it - which is a different inode every time	*/
$typeFilePath = \Nino\Filesystem::path( $appData, '/elements/testtype.php' );
clearstatcache( true, $typeFilePath );
$beforeDelete = (int) @fileinode( $typeFilePath );

$emptyDelete = \Nino\Elements::deleteElement( $appData, '/testtype/item1', 'en_US' );
clearstatcache( true, $typeFilePath );

check( 'deleting an element that is already gone still reports success', $emptyDelete === true );
check( '...without rewriting the type file', $beforeDelete > 0 && (int) @fileinode( $typeFilePath ) === $beforeDelete );
check( '...and without announcing a deletion that did not happen', $committed === [] );

// ...while a delete that does remove something still announces it
\Nino\Elements::insertElement( $appData, '/testtype/gone', [ 'title' => 'Here for a moment' ], 'de_DE' );
$committed = [];
check( 'a delete that removes something is announced as before', \Nino\Elements::deleteElement( $appData, '/testtype/gone', '*' ) === true && $committed === [ 'delete /testtype/gone' ] );

/*	A '*' read resolves to whichever locale actually holds the element, and
	that resolution is remembered: the read cache could not be hit by a '*'
	read at all, so every one of them went back to the type file, walked its
	locale buckets again and rebuilt the merged array. On a page rendering a
	collection that is once per element per render.

	The resolution is only ever written by a read that asked with '*'. Writing
	it for a named-locale read too - which is how this was first written - made
	the next '*' read answer with whichever locale happened to have been read
	last, and the workbench's own apiList then labelled an element with its
	English title on a German site	*/
\Nino\Elements::insertElement( $appData, '/testtype/both', [ 'title' => 'Beide' ], 'de_DE' );
\Nino\Elements::insertElement( $appData, '/testtype/both', [ 'title' => 'Both' ], 'en_US' );

check( 'a star read finds the first locale that holds the element', ( \Nino\Elements::getElement( $appData, '/testtype/both', '*' )['title'] ?? null ) === 'Beide' );
check( '...and remembers what it resolved to, so the next one does not walk the file again', ( $appData['./nino/elements/cache']['resolved']['/testtype/both'] ?? null ) === 'de_DE' );

\Nino\Elements::getElement( $appData, '/testtype/both', 'en_US' );
check( 'a read of a named locale does not overwrite that resolution', ( $appData['./nino/elements/cache']['resolved']['/testtype/both'] ?? null ) === 'de_DE' );
check( '...so a later star read still answers with the first locale, not the last one read', ( \Nino\Elements::getElement( $appData, '/testtype/both', '*' )['title'] ?? null ) === 'Beide' );

\Nino\Elements::deleteElement( $appData, '/testtype/both', '*' );
check( 'a write drops the remembered resolution with the rest of the cache', isset( $appData['./nino/elements/cache']['resolved'] ) === false );
check( 'the element is gone from every other locale as well', \Nino\Elements::getElement( $appData, '/testtype/item1', '*' ) === false );

// Regression: 'date'/'datetime' model fields hold a plain string value (php has no
// native date type) - insertElement used to reject them outright, comparing
// gettype() of the string value against the literal type name 'date'/'datetime'
check( 'insertElementType accepts a datetime field', \Nino\Elements::insertElementType( $appData, '/datetesttype', [ 'when' => [ 'type' => 'date' ], 'at' => [ 'type' => 'datetime' ] ] ) !== false );

$dateInserted = \Nino\Elements::insertElement( $appData, '/datetesttype/item1', [ 'when' => '2026-07-14', 'at' => '2026-07-14T15:30' ], '*' );
check( 'insertElement accepts a date field value', is_array( $dateInserted ) === true && $dateInserted['when'] === '2026-07-14' );
check( 'insertElement accepts a datetime field value', is_array( $dateInserted ) === true && $dateInserted['at'] === '2026-07-14T15:30' );

// An 'element' field references another element by its full uri - the value is
// a plain string like every date/image value, and the type it may point at is
// part of the field
\Nino\Elements::insertElementType( $appData, '/author', [ 'name' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/author/ada', [ 'name' => 'Ada' ], '*' );

check( 'insertElementType accepts an element reference field', \Nino\Elements::insertElementType( $appData, '/article', [
	'headline' 	=> [ 'type' => 'string' ],
	'author' 		=> [ 'type' => 'element', 'elementType' => 'author' ],
] ) !== false );

check( 'the referenced type is kept on the field, not dropped as an unknown key',
	( \Nino\Elements::getElementModel( $appData, '/article' )['author']['elementType'] ?? null ) === 'author' );

// Without one there is nothing to choose from - the field would render as a
// permanently empty select, so it is not a field at all
\Nino\Elements::insertElementType( $appData, '/looseref', [ 'a' => [ 'type' => 'element' ], 'b' => [ 'type' => 'element', 'elementType' => '  ' ], 'keep' => [ 'type' => 'string' ] ] );
check( 'an element field without a referenced type is dropped from the model',
	array_keys( \Nino\Elements::getElementModel( $appData, '/looseref' ) ) === [ 'keep' ] );

$referencing = \Nino\Elements::insertElement( $appData, '/article/first', [ 'headline' => 'Hello', 'author' => '/author/ada' ], '*' );
check( 'insertElement stores the referenced element\'s full uri', is_array( $referencing ) === true && $referencing['author'] === '/author/ada' );
check( '...which is exactly what getElement() takes, with no re-joining',
	( \Nino\Elements::getElement( $appData, \Nino\Elements::getElement( $appData, '/article/first', '*' )['author'], '*' )['name'] ?? null ) === 'Ada' );

// The reference is checked against the model, not against the referenced file:
// pointing into another type would hand a reader an element of a shape the
// model never promised
check( 'a reference into a different type is rejected',
	\Nino\Elements::insertElement( $appData, '/article/wrong', [ 'headline' => 'x', 'author' => '/testtype/item1' ], '*' ) === false );
check( 'a bare slug without the type prefix is rejected too',
	\Nino\Elements::insertElement( $appData, '/article/bare', [ 'headline' => 'x', 'author' => 'ada' ], '*' ) === false );
check( 'an empty reference is accepted - "no reference" is a legitimate value',
	is_array( \Nino\Elements::insertElement( $appData, '/article/none', [ 'headline' => 'x', 'author' => '' ], '*' ) ) === true );

// A target deleted later stays readable rather than being scrubbed: the
// element form shows it as missing, which is recoverable - silently dropping
// it is not
\Nino\Elements::deleteElement( $appData, '/author/ada', '*' );
check( 'a reference whose target is gone keeps its value',
	( \Nino\Elements::getElement( $appData, '/article/first', '*' )['author'] ?? null ) === '/author/ada' );

echo "\n";

// --- An element reference holding several elements ----------------------

echo "Elements multi references - one field, an ordered list of elements\n";

\Nino\Elements::insertElementType( $appData, '/tag', [ 'name' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/tag/php', [ 'name' => 'PHP' ], '*' );
\Nino\Elements::insertElement( $appData, '/tag/css', [ 'name' => 'CSS' ], '*' );
\Nino\Elements::insertElement( $appData, '/tag/html', [ 'name' => 'HTML' ], '*' );

check( 'insertElementType keeps a multi reference\'s cap on the field',
	\Nino\Elements::insertElementType( $appData, '/post', [
		'title' => [ 'type' => 'string' ],
		'tags' 	=> [ 'type' => 'element', 'elementType' => 'tag', 'multiple' => 2 ],
	] ) !== false
	&& ( \Nino\Elements::getElementModel( $appData, '/post' )['tags']['multiple'] ?? null ) === 2 );

// Presence of an int is the switch. Anything else is a mistake rather than a
// smaller cap, and dropping the key leaves the field the single reference it
// has always been - never a silently unlimited list
\Nino\Elements::insertElementType( $appData, '/loosemulti', [
	'a' => [ 'type' => 'element', 'elementType' => 'tag', 'multiple' => 'many' ],
	'b' => [ 'type' => 'element', 'elementType' => 'tag', 'multiple' => -1 ],
	'c' => [ 'type' => 'element', 'elementType' => 'tag', 'multiple' => true ],
] );
$loose = \Nino\Elements::getElementModel( $appData, '/loosemulti' );
check( 'a cap that is not a whole number at least zero is dropped',
	isset( $loose['a']['multiple'] ) === false
	&& isset( $loose['b']['multiple'] ) === false
	&& isset( $loose['c']['multiple'] ) === false );
check( '...and isMultiElement() reads the model the writer just wrote',
	\Nino\Elements::isMultiElement( $loose['a'] ) === false
	&& \Nino\Elements::isMultiElement( \Nino\Elements::getElementModel( $appData, '/post' )['tags'] ) === true );

$multi = \Nino\Elements::insertElement( $appData, '/post/first', [ 'title' => 'Hi', 'tags' => [ '/tag/php', '/tag/css' ] ], '*' );
check( 'insertElement stores a multi reference as an ordered list',
	is_array( $multi ) === true && $multi['tags'] === [ '/tag/php', '/tag/css' ] );
check( '...and the order is what a read gives back',
	( \Nino\Elements::getElement( $appData, '/post/first', '*' )['tags'] ?? null ) === [ '/tag/php', '/tag/css' ] );
check( '...each entry being exactly what getElement() takes, as a single one is',
	( \Nino\Elements::getElement( $appData, \Nino\Elements::getElement( $appData, '/post/first', '*' )['tags'][1], '*' )['name'] ?? null ) === 'CSS' );

// The cap is the model's promise about the value, so the kernel is what keeps
// it - an api caller never went near the control that drew the list
check( 'a list longer than the cap is rejected',
	\Nino\Elements::insertElement( $appData, '/post/toomany', [ 'title' => 'x', 'tags' => [ '/tag/php', '/tag/css', '/tag/html' ] ], '*' ) === false );
check( 'the same element twice is rejected - the list is an ordered set',
	\Nino\Elements::insertElement( $appData, '/post/dupe', [ 'title' => 'x', 'tags' => [ '/tag/php', '/tag/php' ] ], '*' ) === false );
check( 'one entry pointing into another type rejects the whole list',
	\Nino\Elements::insertElement( $appData, '/post/wrong', [ 'title' => 'x', 'tags' => [ '/tag/php', '/author/ada' ] ], '*' ) === false );
check( 'a non-string entry is rejected',
	\Nino\Elements::insertElement( $appData, '/post/nonstring', [ 'title' => 'x', 'tags' => [ '/tag/php', 42 ] ], '*' ) === false );
check( 'a bare string is rejected where the model promises a list',
	\Nino\Elements::insertElement( $appData, '/post/scalar', [ 'title' => 'x', 'tags' => '/tag/php' ], '*' ) === false );
check( 'an empty list is accepted - "no references" is a legitimate value',
	is_array( \Nino\Elements::insertElement( $appData, '/post/none', [ 'title' => 'x', 'tags' => [] ], '*' ) ) === true );

// 0 is the honest way to say "no ceiling": an empty box meaning unlimited
// cannot be told apart from one nobody filled in
\Nino\Elements::insertElementType( $appData, '/unlimited', [ 'tags' => [ 'type' => 'element', 'elementType' => 'tag', 'multiple' => 0 ] ] );
check( 'a cap of 0 takes as many as are offered',
	is_array( \Nino\Elements::insertElement( $appData, '/unlimited/all', [ 'tags' => [ '/tag/php', '/tag/css', '/tag/html' ] ], '*' ) ) === true );

// A removal in the form, or a json object, can arrive with gaps or string keys.
// A template iterating the value would see those keys instead of the order
$gapped = \Nino\Elements::insertElement( $appData, '/unlimited/gapped', [ 'tags' => [ 2 => '/tag/php', 5 => '/tag/css' ] ], '*' );
check( 'a gapped list is stored as a list, so its order is what iterates',
	is_array( $gapped ) === true
	&& ( \Nino\Elements::getElement( $appData, '/unlimited/gapped', '*' )['tags'] ?? null ) === [ '/tag/php', '/tag/css' ] );

\Nino\Elements::insertElementType( $appData, '/reqmulti', [ 'tags' => [ 'type' => 'element', 'elementType' => 'tag', 'multiple' => 0, 'required' => true ] ] );
check( 'a required multi reference is empty when its list is',
	\Nino\Elements::insertElement( $appData, '/reqmulti/none', [ 'tags' => [] ], '*' ) === false );

// The whole point of keying the behaviour off the key's presence: a type file
// written before any of this existed means exactly what it always meant
check( 'a field carrying no cap is still a single string reference',
	is_array( \Nino\Elements::insertElement( $appData, '/article/compat', [ 'headline' => 'x', 'author' => '/author/ada' ], '*' ) ) === true );
check( '...and a list posted into that single reference is rejected',
	\Nino\Elements::insertElement( $appData, '/article/listintosingle', [ 'headline' => 'x', 'author' => [ '/author/ada' ] ], '*' ) === false );

echo "\n";


// --- Sequential element uris (the type file's own AUTO_INCREMENT) --------

echo "Elements sequential uris - a type that numbers its own entries\n";

$numbered = [ 'title' => [ 'type' => 'string', 'locale' => true ] ];

check( 'insertElementType can start a type off numbered',
	\Nino\Elements::insertElementType( $appData, '/gallery', $numbered, true ) !== false );
check( '...with its counter on the first number',
	\Nino\Elements::getAutoincrement( $appData, '/gallery' ) === 1 );

// '/gallery/' - the type with an empty slug - is the request to be numbered
$first	= \Nino\Elements::insertElement( $appData, '/gallery/', [ 'title' => 'A' ], 'de_DE' );
$second = \Nino\Elements::insertElement( $appData, '/gallery/', [ 'title' => 'B' ], 'de_DE' );

check( 'an empty slug is allocated the first number, zero-padded',
	( $first['.uri'] ?? null ) === '/gallery/00001' );
check( '...and the next insert gets the one after it',
	( $second['.uri'] ?? null ) === '/gallery/00002' );
check( 'the returned element carries the uri it was given, so a caller can use it',
	( $second['title'] ?? null ) === 'B' );
check( 'the allocated element reads back under that uri',
	( \Nino\Elements::getElement( $appData, '/gallery/00001', 'de_DE' )['title'] ?? null ) === 'A' );
check( 'the counter has moved past both',
	\Nino\Elements::getAutoincrement( $appData, '/gallery' ) === 3 );

// A uri is a public address. Handing a deleted element's number to a different
// one would silently repoint every link that still uses it, so the counter is
// stored rather than derived from what is currently there.
\Nino\Elements::deleteElement( $appData, '/gallery/00002', '*' );
check( 'deleting the newest entry does not hand its number out again',
	( \Nino\Elements::insertElement( $appData, '/gallery/', [ 'title' => 'C' ], 'de_DE' )['.uri'] ?? null ) === '/gallery/00003' );

// Numbering is about what the tools offer, not a restriction on the kernel -
// an import or a migration can still name an entry
check( 'an explicit slug is still accepted on a numbered type',
	( \Nino\Elements::insertElement( $appData, '/gallery/named', [ 'title' => 'D' ], 'de_DE' )['.uri'] ?? null ) === '/gallery/named' );

// ...which is why the seed is re-checked on every allocation: a counter
// trailing behind an imported number would otherwise overwrite it
\Nino\Elements::insertElement( $appData, '/gallery/00042', [ 'title' => 'imported' ], 'de_DE' );
check( 'the counter jumps past an entry numbered higher by hand',
	( \Nino\Elements::insertElement( $appData, '/gallery/', [ 'title' => 'E' ], 'de_DE' )['.uri'] ?? null ) === '/gallery/00043' );
check( '...leaving that entry untouched',
	( \Nino\Elements::getElement( $appData, '/gallery/00042', 'de_DE' )['title'] ?? null ) === 'imported' );

check( 'every numbered entry is found by queryElements',
	count( \Nino\Elements::queryElements( $appData, '/gallery', [], 'de_DE' ) ?: [] ) === 5 );

// The counter shares the type file's top level with 'title', not with the
// locale buckets - nothing may read it as one
$galleryFile = \Nino\Filesystem::getFileContent( $appData, '/elements/gallery.php', [] );
check( 'the counter is stored as a plain int beside the data buckets',
	( $galleryFile['autoincrement'] ?? null ) === 44 );
check( 'no element was ever stored under an empty uri',
	array_key_exists( '', $galleryFile['*'] ?? [] ) === false && array_key_exists( '', $galleryFile['de_DE'] ?? [] ) === false );

check( 'a type that names its own elements has no counter',
	\Nino\Elements::getAutoincrement( $appData, '/testtype' ) === null );
check( '...so an empty slug there is still refused',
	\Nino\Elements::insertElement( $appData, '/testtype/', [ 'title' => 'x' ], 'de_DE' ) === false );

// Only an int is the switch. A hand-edited 'true' says nothing about which
// number is next, so it is not a counter and must not be treated as one.
\Nino\Elements::insertElementType( $appData, '/halfway', $numbered );
\Nino\Filesystem::mutate( $appData, '/elements/halfway.php', function( mixed $d ): array { $d['autoincrement'] = true; return $d; }, false );
check( 'a non-int autoincrement key is not read as a counter',
	\Nino\Elements::getAutoincrement( $appData, '/halfway' ) === null );

echo "\n";


// --- Images ------------------------------------------------------------

echo "Images::process / delete\n";

/*	Everything down to the webp-output block runs with '/nino/images/webp'
	off, so the png/jpeg fallback - what a gd without webp writes, and what a
	project that switched it off gets - keeps being exercised in full. The
	alpha reader is what picks between those two, so it is visible here rather
	than hidden behind one container that fits both	*/
$appData['/nino/images/webp'] = false;

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

$squarePath = \Nino\Filesystem::path( $appData, '/images/'. ( $squareFilename ?: '' ) );
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

// A png or gif is re-encoded as png: the format is the guess at the content
// too, and what arrives as one is usually line art that jpeg would soften
$alphaSource = makeTestImage( 100, 100, true );
$alphaFilename = \Nino\Images::process( $appData, $alphaSource, 50, 50, 'elements/demo/item2' );
check( 'process() outputs png for a source that may have transparency', $alphaFilename === 'elements/demo/item2.50x50.png' );
\Nino\Images::delete( $appData, $alphaFilename );

/*	webp is the other way round: it is what phones and export tools write for
	photographs, and answering it with png cost a factor of twelve on every
	derived size. Its container says whether there is an alpha channel - 'VP8 '
	is the simple lossy chunk and never has one, 'VP8L' and 'VP8X' carry a flag -
	so that is read rather than guessed. Real encoder output here, not a
	hand-built header	*/
function makeTestWebp( int $width, int $height, bool $alpha, bool $lossless = false ): string {
	$img = imagecreatetruecolor( $width, $height );
	if( $alpha === true ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 200, 0, 64 ) );
	} else {
		imagefill( $img, 0, 0, imagecolorallocate( $img, 200, 120, 60 ) );
	}
	ob_start();
	imagewebp( $img, null, $lossless === true ? IMG_WEBP_LOSSLESS : 80 );
	$bytes = ob_get_clean();
	imagedestroy( $img );
	return $bytes;
}
if( function_exists( 'imagewebp' ) === true && ( imagetypes() & IMG_WEBP ) !== 0 ) {

	$webpChunks = [];
	foreach( [ 'lossy opaque' => [ false, false ], 'lossy alpha' => [ true, false ], 'lossless opaque' => [ false, true ], 'lossless alpha' => [ true, true ] ] as $webpCase => $webpHow )
		$webpChunks[$webpCase] = substr( makeTestWebp( 60, 40, $webpHow[0], $webpHow[1] ), 12, 4 );
	check( 'the webp fixtures really are the three container shapes the reader knows - '. json_encode( $webpChunks ),
	( $webpChunks['lossy opaque'] ?? '' ) === 'VP8 ' && ( $webpChunks['lossy alpha'] ?? '' ) === 'VP8X'
	&& in_array( $webpChunks['lossless opaque'] ?? '', [ 'VP8L', 'VP8X' ], true ) === true
	&& in_array( $webpChunks['lossless alpha'] ?? '', [ 'VP8L', 'VP8X' ], true ) === true );

	$webpOpaque = \Nino\Images::process( $appData, makeTestWebp( 200, 200, false ), 60, 60, 'elements/demo/webp-opaque' );
	check( 'an opaque webp is answered with jpeg, not png', $webpOpaque === 'elements/demo/webp-opaque.60x60.jpg' );
	\Nino\Images::delete( $appData, (string) $webpOpaque );

	$webpAlpha = \Nino\Images::process( $appData, makeTestWebp( 200, 200, true ), 60, 60, 'elements/demo/webp-alpha' );
	check( '...and a webp that carries alpha still gets png, so the channel survives', $webpAlpha === 'elements/demo/webp-alpha.60x60.png' );
	\Nino\Images::delete( $appData, (string) $webpAlpha );

	$webpLossless = \Nino\Images::process( $appData, makeTestWebp( 200, 200, true, true ), 60, 60, 'elements/demo/webp-lossless' );
	check( '...lossless webp too, whose flag sits behind its dimensions', $webpLossless === 'elements/demo/webp-lossless.60x60.png' );
	\Nino\Images::delete( $appData, (string) $webpLossless );

	// Anything the reader does not recognize costs bytes rather than a channel:
	// a truncated or foreign container is answered as if it had alpha
	$webpTruncated = \Nino\Images::process( $appData, substr( makeTestWebp( 200, 200, false ), 0, 20 ), 60, 60, 'elements/demo/webp-broken' );
	check( '...and bytes that are no longer a decodable image are refused outright', $webpTruncated === false );
} else {
	check( 'this php has no webp support, so the container reader is not exercised here', true );
}

/*	And with it on, which is the default: webp replaces both formats rather
	than the decision between them. The branch stays, the container changes -
	lossless where png would have been (no quality lost, alpha carried along),
	lossy where jpeg would have been. The written file says which: a lossless
	webp is a 'VP8L' chunk, a lossy one 'VP8 ' or 'VP8X'	*/
if( function_exists( 'imagewebp' ) === true && ( imagetypes() & IMG_WEBP ) !== 0 ) {

	$appData['/nino/images/webp'] = true;
	$webpChunkOf = static function( string $filename ) use ( &$appData ): string {
		return substr( (string) @file_get_contents( \Nino\Filesystem::path( $appData, '/images/'. $filename ) ), 12, 4 );
	};

	$outPhoto = \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/out-photo' );
	check( 'with webp on, a source that would have been jpeg is written as webp', $outPhoto === 'elements/demo/out-photo.100x100.webp' );
	check( '...lossy, the way jpeg was', in_array( $webpChunkOf( (string) $outPhoto ), [ 'VP8 ', 'VP8X' ], true ) === true );
	\Nino\Images::delete( $appData, (string) $outPhoto );

	$outArt = \Nino\Images::process( $appData, makeTestImage( 100, 100, true ), 50, 50, 'elements/demo/out-art' );
	check( '...and one that would have been png is webp too', $outArt === 'elements/demo/out-art.50x50.webp' );
	check( '...lossless, so the edges png was chosen for stay sharp and the alpha channel survives', $webpChunkOf( (string) $outArt ) === 'VP8L' );
	\Nino\Images::delete( $appData, (string) $outArt );

	$appData['/nino/images/webp'] = false;
	$outOff = \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/out-off' );
	check( '...and switching it off in config.php brings jpeg back, name and all', $outOff === 'elements/demo/out-off.100x100.jpg' );
	\Nino\Images::delete( $appData, (string) $outOff );

} else {
	check( 'this php writes no webp, so the kernel keeps png and jpeg by itself', \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/out-none' ) === 'elements/demo/out-none.100x100.jpg' );
	\Nino\Images::delete( $appData, 'elements/demo/out-none.100x100.jpg' );
}

check( 'process() rejects bytes that are not a valid image', \Nino\Images::process( $appData, 'not an image', 100, 100, 'elements/demo/item3' ) === false );
check( 'process() rejects an empty target dimension', \Nino\Images::process( $appData, $wideSource, 0, 100, 'elements/demo/item3' ) === false );
check( 'process() rejects a path-traversal basePath', \Nino\Images::process( $appData, $wideSource, 100, 100, '../escape' ) === false );

echo "\n";


// --- Images::limits / reject - what an upload form tells, what the kernel refuses

echo "Images::limits / Images::reject\n";

/*	php's two limits are ini strings, so what limits() reads is stood in for by
	_limits()'s arguments here - upload_max_filesize and post_max_size cannot be changed
	at run time. The smaller of the two is what can arrive, the kernel's own
	cap is the other bound, and a value php cannot read counts as no limit
	instead of raising the warning this framework would treat as fatal	*/
$limitWarnings = [];
// - a handler is called under @ too, so it asks error_reporting() the way Runtime::handleError() does
set_error_handler( function( int $no, string $message ) use ( &$limitWarnings ) {
	if( ( error_reporting() & $no ) !== 0 )
		$limitWarnings[] = $message;
	return true;
} );

$limits = \Nino\Images::_limits( '2M', '8M' );
check( 'limits() takes the smaller of php\'s two limits for what can arrive', $limits['php'] === 2 * 1024 * 1024 && $limits['post'] === 8 * 1024 * 1024 && $limits['bytes'] === 2 * 1024 * 1024 );
$limits = \Nino\Images::_limits( '64M', '16M' );
check( '...and the kernel\'s own cap when that is smaller still', $limits['php'] === 16 * 1024 * 1024 && $limits['kernel'] === 8 * 1024 * 1024 && $limits['bytes'] === 8 * 1024 * 1024 );
$limits = \Nino\Images::_limits( '512K', '0' );
check( 'post_max_size = 0 means no limit - the upload limit alone applies', $limits['php'] === 512 * 1024 && $limits['post'] === 0 && $limits['bytes'] === 512 * 1024 );
$limits = \Nino\Images::_limits( '0', '0' );
check( 'no php limit at all leaves the kernel\'s cap', $limits['php'] === 0 && $limits['bytes'] === \Nino\Images::MAX_UPLOAD_BYTES );
$limits = \Nino\Images::_limits( 'plenty', '-1' );
check( 'a value php cannot read, or a negative one, counts as no limit', $limits['php'] === 0 && $limits['post'] === 0 && $limits['bytes'] === \Nino\Images::MAX_UPLOAD_BYTES );
$limits = \Nino\Images::_limits( '1G', '2G' );
check( 'the pixel cap is the kernel\'s whatever php says', $limits['pixels'] === \Nino\Images::MAX_SOURCE_PIXELS && $limits['bytes'] === \Nino\Images::MAX_UPLOAD_BYTES );
$limits = \Nino\Images::limits();
check( 'limits() reads the running php\'s own', $limits['kernel'] === 8 * 1024 * 1024 && $limits['bytes'] > 0 && $limits['bytes'] <= $limits['kernel'] );
check( '...and none of it raised a warning, malformed values included', $limitWarnings === [] );
restore_error_handler();

check( 'reject() lets an image the kernel takes through', \Nino\Images::reject( makeTestImage( 40, 30 ) ) === null );
check( 'reject() names bytes above the cap, with the limit in MB', \Nino\Images::reject( str_repeat( 'a', \Nino\Images::MAX_UPLOAD_BYTES + 1 ) ) === [ 'code' => 'image_too_large', 'params' => [ 8 ] ] );
check( 'reject() names something that is no image', \Nino\Images::reject( 'not an image' ) === [ 'code' => 'image_type', 'params' => [] ] && \Nino\Images::reject( '' )['code'] === 'image_type' );
$ihdr = pack( 'NN', 5000, 5000 ). "\x08\x02\x00\x00\x00";
$hugePng = "\x89PNG\r\n\x1a\n". pack( 'N', 13 ). 'IHDR'. $ihdr. pack( 'N', crc32( 'IHDR'. $ihdr ) );
check( 'reject() names a picture of more pixels than the kernel decodes, with the megapixels', \Nino\Images::reject( $hugePng ) === [ 'code' => 'image_too_many_pixels', 'params' => [ 20 ] ] );
check( '_render() refuses the same three through process() - one implementation of the limits', \Nino\Images::process( $appData, str_repeat( 'a', \Nino\Images::MAX_UPLOAD_BYTES + 1 ), 10, 10, 'elements/demo/limit' ) === false
	&& \Nino\Images::process( $appData, 'not an image', 10, 10, 'elements/demo/limit' ) === false && \Nino\Images::process( $appData, $hugePng, 10, 10, 'elements/demo/limit' ) === false );

// Http::fail() - a code, params and a field only when they are given
$failRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Http::fail( $failRequest, 400, 'invalid' );
check( 'Http::fail() with three arguments answers the body it always did', $failRequest['/nino/http/response'] === [ 'statusCode' => 400, 'body' => [ 'error' => 'invalid' ] ] );
\Nino\Http::fail( $failRequest, 409, 'taken', 'slug_taken' );
check( '...a code is added when given', $failRequest['/nino/http/response']['body'] === [ 'error' => 'taken', 'code' => 'slug_taken' ] );
\Nino\Http::fail( $failRequest, 400, 'range', 'int_range', [ 1, 'x' => 100 ], 'maxtries' );
check( '...and params as a list, and the field', $failRequest['/nino/http/response']['body'] === [ 'error' => 'range', 'code' => 'int_range', 'params' => [ 1, 100 ], 'field' => 'maxtries' ] );

echo "\n";


// --- Images::fit - the whole picture, in a box -----------------------------

echo "Images::fit / the render callback\n";

$fitFilename = \Nino\Images::fit( $appData, $wideSource, 100, 100, 'gallery/demo/one' );
check( 'fit() names the box rather than the result, so the path stays predictable per slot', $fitFilename === 'gallery/demo/one.fit100x100.jpg' );

[ $fitWidth, $fitHeight ] = getimagesize( \Nino\Filesystem::path( $appData, '/images/'. ( $fitFilename ?: '' ) ) );
check( 'the whole 400x200 picture is in the 100x100 box, its own proportions kept - nothing cropped', $fitWidth === 100 && $fitHeight === 50 );

$tallFilename = \Nino\Images::fit( $appData, makeTestImage( 200, 400 ), 100, 100, 'gallery/demo/two' );
[ $tallWidth, $tallHeight ] = getimagesize( \Nino\Filesystem::path( $appData, '/images/'. ( $tallFilename ?: '' ) ) );
check( '...whichever edge is the long one', $tallWidth === 50 && $tallHeight === 100 );

$smallFilename = \Nino\Images::fit( $appData, makeTestImage( 40, 30 ), 800, 800, 'gallery/demo/three' );
[ $smallWidth, $smallHeight ] = getimagesize( \Nino\Filesystem::path( $appData, '/images/'. ( $smallFilename ?: '' ) ) );
check( 'a source smaller than the box is stored as it is - four times the bytes for the same picture is not an improvement', $smallWidth === 40 && $smallHeight === 30 );

check( 'fit() refuses what process() refuses', \Nino\Images::fit( $appData, 'not an image', 100, 100, 'gallery/demo/four' ) === false
	&& \Nino\Images::fit( $appData, $wideSource, 0, 100, 'gallery/demo/four' ) === false
	&& \Nino\Images::fit( $appData, $wideSource, 100, 100, '../escape' ) === false );

// The seam a richer uploader hooks into: past the checks, before the
// encoding. A handler that renders the image itself says so with a filename
$rendered = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ) use ( &$rendered ): void {
	$rendered[] = $image['mode']. ' '. $image['width']. 'x'. $image['height']. ' from '. $image['source']['width']. 'x'. $image['source']['height'];
	$image['filename'] = $image['basePath']. '.handled.webp';
} );

check( 'a handler renders instead of gd, and gets the mode, the box and the source it was given', \Nino\Images::process( $appData, $wideSource, 64, 64, 'gallery/demo/hooked' ) === 'gallery/demo/hooked.handled.webp'
	&& \Nino\Images::fit( $appData, $wideSource, 64, 64, 'gallery/demo/hooked' ) === 'gallery/demo/hooked.handled.webp'
	&& $rendered === [ 'crop 64x64 from 400x200', 'fit 64x64 from 400x200' ] );
check( '...and nothing was written by the kernel for either', \Nino\Filesystem::fileExists( $appData, '/images/gallery/demo/hooked.64x64.jpg' ) === false
	&& \Nino\Filesystem::fileExists( $appData, '/images/gallery/demo/hooked.fit64x64.jpg' ) === false );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ): void {
	$image['filename'] = false;
} );
check( 'a handler that refuses the upload refuses it, and gd never runs', \Nino\Images::process( $appData, $wideSource, 64, 64, 'gallery/demo/refused' ) === false
	&& \Nino\Filesystem::fileExists( $appData, '/images/gallery/demo/refused.64x64.jpg' ) === false );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

// A handler is project code, not a reason to stop checking what comes back
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ): void {
	$image['filename'] = '../../escape.jpg';
} );
check( 'a filename that climbs out of the upload directory is not taken - gd renders it after all', \Nino\Images::process( $appData, $wideSource, 64, 64, 'gallery/demo/climb' ) === 'gallery/demo/climb.64x64.jpg' );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

// The checks are ahead of the callback on purpose: what keeps an upload
// endpoint safe is not something a feature switches off by registering
$reached = false;
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ) use ( &$reached ): void {
	$reached = true;
} );
check( 'a handler never sees bytes that are not a decodable image of a sane size', \Nino\Images::process( $appData, 'not an image', 64, 64, 'gallery/demo/bad' ) === false && $reached === false );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

echo "\n";


// --- Images - the orientation a camera recorded ------------------------

echo "Images::process / fit / size - EXIF orientation\n";

/**
 *	A 300x200 picture of four flat quadrants (red, green / blue, yellow),
 *	stored the way a camera that wrote this EXIF orientation stores it: the
 *	pixels are the inverse of the turn, so a reader that applies the
 *	orientation sees the upright picture again. The mapping is the table of
 *	the EXIF specification - which edge of the stored image is the visual top,
 *	left, right or bottom - and deliberately not gd's own rotate, which is
 *	what the kernel is checked against
 *
 *	@param		int				$orientation	1-8
 *
 *	@return		string										Plain jpeg bytes, no EXIF
 */
function makeQuadrantJpeg( int $orientation ): string {

	$displayWidth		= 300;
	$displayHeight	= 200;
	$turned					= $orientation >= 5;
	$storedWidth		= $turned ? $displayHeight : $displayWidth;
	$storedHeight		= $turned ? $displayWidth : $displayHeight;

	$img = imagecreatetruecolor( $storedWidth, $storedHeight );
	$colors = [
		'TL' => imagecolorallocate( $img, 220, 0, 0 ),
		'TR' => imagecolorallocate( $img, 0, 220, 0 ),
		'BL' => imagecolorallocate( $img, 0, 0, 220 ),
		'BR' => imagecolorallocate( $img, 220, 220, 0 ),
	];

	for( $row = 0; $row < $storedHeight; $row++ )
		for( $column = 0; $column < $storedWidth; $column++ ) {
			[ $x, $y ] = match( $orientation ) {
				1 => [ $column, $row ],
				2 => [ $displayWidth - 1 - $column, $row ],
				3 => [ $displayWidth - 1 - $column, $displayHeight - 1 - $row ],
				4 => [ $column, $displayHeight - 1 - $row ],
				5 => [ $row, $column ],
				6 => [ $displayWidth - 1 - $row, $column ],
				7 => [ $displayWidth - 1 - $row, $displayHeight - 1 - $column ],
				8 => [ $row, $displayHeight - 1 - $column ],
			};
			imagesetpixel( $img, $column, $row, $colors[ ( $y < $displayHeight / 2 ? 'T' : 'B' ). ( $x < $displayWidth / 2 ? 'L' : 'R' ) ] );
		}

	ob_start();
	imagejpeg( $img, null, 95 );
	$bytes = ob_get_clean();
	imagedestroy( $img );

	return $bytes;
}

/**
 *	The payload of an Exif APP1 segment - "Exif\0\0", a TIFF header and an
 *	IFD0 of two entries (an ASCII one first, so the reader has to walk past
 *	something before it finds the orientation)
 *
 *	@param		string		$order				'II' (little) or 'MM' (big endian)
 *	@param		int				$orientation
 *	@param		int				$type					The TIFF type of the orientation entry: 3 SHORT, 4 LONG
 *	@param		int				$ifdOffset		Where the header says IFD0 starts
 *
 *	@return		string
 */
function makeExifPayload( string $order, int $orientation, int $type = 3, int $ifdOffset = 8 ): string {

	$short	= $order === 'II' ? 'v' : 'n';
	$long		= $order === 'II' ? 'V' : 'N';
	$value	= $type === 3 ? pack( $short, $orientation ). "\0\0" : pack( $long, $orientation );

	$ifd = pack( $short, 2 )
		. pack( $short, 0x010E ). pack( $short, 2 ). pack( $long, 4 ). "abc\0"
		. pack( $short, 0x0112 ). pack( $short, $type ). pack( $long, 1 ). $value
		. pack( $long, 0 );

	return "Exif\0\0". $order. pack( $short, 42 ). pack( $long, $ifdOffset ). $ifd;
}

/**
 *	A jpeg with an APP1 segment (or several) put in right behind its start marker
 *
 *	@param		string		$jpeg
 *	@param		string		...$payloads	One segment payload each, in this order
 *
 *	@return		string
 */
function withApp1( string $jpeg, string ...$payloads ): string {

	$segments = '';
	foreach( $payloads as $payload )
		$segments .= "\xFF\xE1". pack( 'n', strlen( $payload ) + 2 ). $payload;

	return substr( $jpeg, 0, 2 ). $segments. substr( $jpeg, 2 );
}

/**
 *	Which quadrant colour a pixel is nearest to: R, G, B or Y
 *
 *	@param		string		$bytes				An image
 *	@param		float			$x						As a share of the width
 *	@param		float			$y						As a share of the height
 *
 *	@return		string
 */
function quadrantAt( string $bytes, float $x, float $y ): string {

	$img		= imagecreatefromstring( $bytes );
	$color	= imagecolorat( $img, (int) ( imagesx( $img ) * $x ), (int) ( imagesy( $img ) * $y ) );
	imagedestroy( $img );

	[ $red, $green, $blue ] = [ ( $color >> 16 ) & 255, ( $color >> 8 ) & 255, $color & 255 ];

	return $blue > 120 ? 'B' : ( $red > 120 && $green > 120 ? 'Y' : ( $red > 120 ? 'R' : 'G' ) );
}

/**
 *	The four quadrants of a stored image, top left to bottom right
 *
 *	@param		array			&$appData
 *	@param		string		$filename			Below /images/
 *
 *	@return		string
 */
function quadrantsOf( array &$appData, string $filename ): string {

	$bytes = (string) file_get_contents( \Nino\Filesystem::path( $appData, '/images/'. $filename ) );

	return quadrantAt( $bytes, 0.25, 0.25 ). quadrantAt( $bytes, 0.75, 0.25 ). quadrantAt( $bytes, 0.25, 0.75 ). quadrantAt( $bytes, 0.75, 0.75 );
}

$exifRight = true;
$exifWhy = '';
foreach( [ 'II', 'MM' ] as $order )
	foreach( range( 1, 8 ) as $orientation ) {

		$source = withApp1( makeQuadrantJpeg( $orientation ), makeExifPayload( $order, $orientation ) );
		$read		= \Nino\Images::size( $source );
		if( $read === false || $read['orientation'] !== $orientation || $read['width'] !== 300 || $read['height'] !== 200 ) {
			$exifRight = false;
			$exifWhy .= " size($order $orientation)";
		}

		// A wide target, a tall one (which crops the sides off the picture as
		// it is shown, not as it is stored) and a fit - each one has to come
		// out the size and with the quadrants of the upright picture
		$crops = [ [ 150, 100 ], [ 100, 150 ] ];
		foreach( $crops as [ $w, $h ] ) {
			$stored = \Nino\Images::process( $appData, $source, $w, $h, 'exif/crop-'. $order. $orientation. '-'. $w );
			$size		= $stored === false ? false : getimagesize( \Nino\Filesystem::path( $appData, '/images/'. $stored ) );
			if( $stored === false || $size[0] !== $w || $size[1] !== $h || quadrantsOf( $appData, $stored ) !== 'RGBY' ) {
				$exifRight = false;
				$exifWhy .= " process($order $orientation {$w}x{$h})";
			}
		}

		$stored = \Nino\Images::fit( $appData, $source, 120, 120, 'exif/fit-'. $order. $orientation );
		$size		= $stored === false ? false : getimagesize( \Nino\Filesystem::path( $appData, '/images/'. $stored ) );
		if( $stored === false || $size[0] !== 120 || $size[1] !== 80 || quadrantsOf( $appData, $stored ) !== 'RGBY' ) {
			$exifRight = false;
			$exifWhy .= " fit($order $orientation)";
		}
	}
check( 'every EXIF orientation 1-8, in both byte orders, comes out upright: process() to 150x100 and 100x150 and fit() to 120x120 keep the size and the quadrants of the upright picture'. $exifWhy, $exifRight === true );

$unturned = \Nino\Images::process( $appData, makeQuadrantJpeg( 6 ), 100, 150, 'exif/none' );
check( '...while the same stored pixels without an Exif block are not turned - it is the header that decides', $unturned !== false && quadrantsOf( $appData, $unturned ) !== 'RGBY' );

// What a header reader may and may not believe - and that it never raises
// a warning on the way, which the framework's error handler would make a 500
$exifWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$exifWarnings ): bool { if( ( error_reporting() & $no ) !== 0 ) $exifWarnings[] = $message; return true; } );

$upright		= makeQuadrantJpeg( 1 );
$plainPng		= makeTestImage( 40, 30, true );
$xmp				= "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta/>";
$exif6			= makeExifPayload( 'MM', 6 );
$shortOf		= static fn( string $bytes ): int => \Nino\Images::size( $bytes )['orientation'] ?? 0;

check( 'size() of a png is its size and orientation 1', \Nino\Images::size( $plainPng ) === [ 'width' => 40, 'height' => 30, 'type' => IMAGETYPE_PNG, 'orientation' => 1 ] );
check( '...of a jpeg without an APP1 segment, orientation 1', $shortOf( $upright ) === 1 );
check( '...of a truncated Exif block, orientation 1', $shortOf( withApp1( $upright, substr( $exif6, 0, -16 ) ) ) === 1 );
check( '...of an IFD offset past the end of the segment, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 6, 3, 4000 ) ) ) === 1 );
check( '...of an IFD offset inside the header, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 6, 3, 4 ) ) ) === 1 );
check( '...of an orientation that is not a SHORT, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 6, 4 ) ) ) === 1 );
check( '...of an orientation value out of range, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 9 ) ) ) === 1 && $shortOf( withApp1( $upright, makeExifPayload( 'MM', 0 ) ) ) === 1 );
check( '...of a TIFF header that is neither II nor MM, or has no 42, orientation 1', $shortOf( withApp1( $upright, str_replace( 'MM', 'XX', $exif6 ) ) ) === 1
	&& $shortOf( withApp1( $upright, substr_replace( $exif6, "\x00\x2B", 8, 2 ) ) ) === 1 );
check( '...an XMP APP1 in front of the Exif one is skipped', $shortOf( withApp1( $upright, $xmp, $exif6 ) ) === 6 );
check( '...and an APP1 that is not Exif is not read as one', $shortOf( withApp1( $upright, $xmp ) ) === 1 );

$sizeSix		= \Nino\Images::size( withApp1( $upright, makeExifPayload( 'II', 6 ) ) );
$sizeEight	= \Nino\Images::size( withApp1( $upright, makeExifPayload( 'MM', 8 ) ) );
$sizeThree	= \Nino\Images::size( withApp1( $upright, makeExifPayload( 'MM', 3 ) ) );
check( 'size() swaps width and height for 6 and 8, and not for 3', $sizeSix['width'] === 200 && $sizeSix['height'] === 300 && $sizeEight['width'] === 200 && $sizeEight['height'] === 300
	&& $sizeThree['width'] === 300 && $sizeThree['height'] === 200 && $sizeThree['orientation'] === 3 );
check( 'size() answers false for what is not an image, or not one the kernel takes', \Nino\Images::size( 'not an image' ) === false && \Nino\Images::size( '' ) === false );

// Fill bytes in front of a marker are legal, and a header of endless
// segments is not followed to its end
$app1		= "\xFF\xE1". pack( 'n', strlen( $exif6 ) + 2 ). $exif6;
$behind	= static fn( string $segments ): string => substr( $upright, 0, 2 ). $segments. substr( $upright, 2 );
check( 'fill bytes in front of the Exif marker are skipped, and a header with 70 segments in front of it is not followed that far',
	$shortOf( $behind( "\xFF\xFF\xFF". $app1 ) ) === 6
	&& $shortOf( $behind( str_repeat( "\xFF\xE2\x00\x02", 70 ). $app1 ) ) === 1
	&& $shortOf( $behind( str_repeat( "\xFF\xE2\x00\x02", 10 ). $app1 ) ) === 6 );
check( 'nothing above raised a php warning'. ( $exifWarnings === [] ? '' : ' - '. $exifWarnings[0] ), $exifWarnings === [] );

restore_error_handler();

// A handler that renders the picture itself is told what the pixels need
$seenSource = null;
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ) use ( &$seenSource ): void {
	$seenSource = $image['source'];
	$image['filename'] = false;
} );
\Nino\Images::process( $appData, withApp1( makeQuadrantJpeg( 6 ), makeExifPayload( 'MM', 6 ) ), 64, 64, 'exif/hooked' );
check( 'a RENDER handler is told the orientation, while width and height stay the stored pixels', ( $seenSource['orientation'] ?? null ) === 6 && ( $seenSource['width'] ?? null ) === 200 && ( $seenSource['height'] ?? null ) === 300 );
\Nino\Images::process( $appData, makeTestImage( 40, 30, true ), 64, 64, 'exif/hooked' );
check( '...and 1 for an image that has none', ( $seenSource['orientation'] ?? null ) === 1 );
unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

check( 'the header is read without ext-exif: Images.php calls no exif_ function', preg_match( '/\bexif_[a-z_]+\s*\(/', (string) file_get_contents( __DIR__. '/../_nino/Nino/Images/Images.php' ) ) === 0 );

echo "\n";


// --- Images::delete --------------------------------------------------------

echo "Images::delete - never outside its own directory\n";

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
// The slots live in config.php: the writers below change what that holds, not this request's copy
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

check( 'getSlots returns every developer-fixed slot', array_keys( \Nino\Images::getSlots( $appData ) ) === [ 'hero' ] );
check( 'getSlot returns one slot\'s definition', ( \Nino\Images::getSlot( $appData, 'hero' )['label'] ?? null ) === 'Hero' );
check( 'getSlot returns false for an unknown uri', \Nino\Images::getSlot( $appData, 'nope' ) === false );

check( 'setSlotFilename rejects an unknown slot', \Nino\Images::setSlotFilename( $appData, 'nope', 'x.jpg' ) === false );
check( 'setSlotFilename accepts a known slot', \Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' ) === true );
check( 'setSlotFilename updates the in-memory slot immediately', \Nino\Images::getSlot( $appData, 'hero' )['filename'] === 'hero.1600x600.jpg' );

$persisted = include \Nino\Filesystem::path( $appData, '/config.php' );
check( 'setSlotFilename persists to config.php (same as Auth::updateUser)', ( $persisted['/nino/html/images']['hero']['filename'] ?? null ) === 'hero.1600x600.jpg' );

// null is "no image": the record is written, and a slot with none renders nothing
check( 'setSlotFilename( null ) takes the image out of the slot and persists that', \Nino\Images::setSlotFilename( $appData, 'hero', null ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['filename'] === null
	&& array_key_exists( 'filename', ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero'] ) === true
	&& ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['filename'] === null );
\Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' );

/*	A write that cannot happen answers false and leaves the record as it was -
	the caller decides about a file on the strength of that answer. The lock
	file's path is made a directory, the way a full disk or a missing right
	would stop it	*/
$configLock = $sandbox. '/private/data/.locks/'. sha1( '/config.php' ). '.lock';
@unlink( $configLock );
mkdir( $configLock, 0755, true );
check( 'setSlotFilename answers false where config.php cannot be written, and leaves the filename as it was', \Nino\Images::setSlotFilename( $appData, 'hero', null ) === false
	&& \Nino\Images::getSlot( $appData, 'hero' )['filename'] === 'hero.1600x600.jpg' );
rmdir( $configLock );
check( '...and writes again once it can', \Nino\Images::setSlotFilename( $appData, 'hero', null ) === true );
\Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' );

$appData['/nino/html/images']['logo'] = [ 'label' => 'Logo', 'width' => 400, 'height' => 400, 'filename' => null ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

\Nino\Modules\Images::init( $appData );
check( '[image] shortcode renders an <img> tag under the public prefix', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/public/images/hero.1600x600.jpg" width="1600" height="600"' ) === true );
check( '[image] shortcode renders nothing for a slot with no file uploaded yet', \Nino\Html::renderHtml( $appData, '[image logo]' ) === '' );
check( '[image] shortcode renders nothing for an unknown slot', \Nino\Html::renderHtml( $appData, '[image nope]' ) === '' );

// What a shortcode is handed. An empty value used to read as no value at all,
// so alt="" - the way a decorative picture is written, and the way AGENTS.md
// writes one - arrived as the positional argument 'alt' and the picture kept
// the slot's label as its alt text. A value that happens to equal its own
// name went the same way
$probeArgs = null;
\Nino\Html::addShortcode( $appData, 'argprobe', static function( array &$appData, array $args ) use ( &$probeArgs ): string {
	$probeArgs = $args;
	return '';
} );

\Nino\Html::renderHtml( $appData, '[argprobe /uri alt="" title="A title" name="name" bare]' );
check( 'an empty argument value is a value, not a name', array_key_exists( 'alt', $probeArgs ?? [] ) === true && ( $probeArgs['alt'] ?? null ) === '' );
check( '...a value that reads like its own name is one too', ( $probeArgs['name'] ?? null ) === 'name' );
check( '...and the ordinary two shapes are unchanged', ( $probeArgs['title'] ?? null ) === 'A title'
	&& in_array( '/uri', $probeArgs ?? [], true ) === true && in_array( 'bare', $probeArgs ?? [], true ) === true );

// The picture this is really about
check( 'a decorative picture is written with an empty alt and keeps it', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt=""]' ), 'alt=""' ) === true );

/*	The <img> is a named property, not a string the method builds - which is
	what makes it one place to read and something a project can replace. Proven
	by replacing it: a project that wants loading="lazy" on every picture sets
	the entry, and the shortcode renders through it	*/
$shippedImg = \Nino\Modules\Images::$html['img'];
\Nino\Modules\Images::$html['img'] = '<img loading="lazy" src="[[src]]" alt="[[alt]]" data-size="[[width]]x[[height]]">';
$replacedImg = \Nino\Html::renderHtml( $appData, '[image hero]' );
\Nino\Modules\Images::$html['img'] = $shippedImg;
check( 'the <img> fragment is the property, so replacing it replaces what the shortcode renders', $replacedImg === '<img loading="lazy" src="/public/images/hero.1600x600.jpg" alt="" data-size="1600x600">' );
check( '...and putting the shipped one back renders the shipped markup again', str_starts_with( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/public/images/hero.1600x600.jpg"' ) === true );

unset( $appData['./nino/html/shortcodes']['argprobe'], $appData['./nino/callbacks']['/nino/html/shortcode/argprobe'] );

// The alt text of a slot: the one stored for the language, then the template's
// own, then none - and never the slot's label, which is a name for the person
// who edits the slot and says nothing about the picture
check( '[image] with no alt anywhere is alt="" - the label of the slot is not an alt text', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), ' alt="">' ) === true );

check( 'setSlotAlt answers false for an unknown slot, a language the site does not have, a value that is no string and bytes that are not utf-8',
	\Nino\Images::setSlotAlt( $appData, 'nope', [ 'de_DE' => 'x' ] ) === false
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'xx_XX' => 'x' ] ) === false
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => [ 'x' ] ] ) === false
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => "caf\xE9" ] ) === false
	&& isset( \Nino\Images::getSlot( $appData, 'hero' )['alt'] ) === false );
check( 'setSlotAlt trims, and turns control characters - a line break among them - into spaces', \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => "  Ein\nHaus\t am See \x07 " ] ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['alt'] === [ 'de_DE' => 'Ein Haus  am See' ] );
check( '...and persists exactly that to config.php', ( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['alt'] ?? null ) === [ 'de_DE' => 'Ein Haus  am See' ] );
check( 'a language that is not posted keeps its text', \Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => 'A house' ] ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['alt'] === [ 'de_DE' => 'Ein Haus  am See', 'en_US' => 'A house' ] );
check( 'an empty value removes that language\'s entry, and the last one takes the key away', \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => '' ] ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['alt'] === [ 'en_US' => 'A house' ]
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => '  ' ] ) === true
	&& isset( \Nino\Images::getSlot( $appData, 'hero' )['alt'] ) === false
	&& isset( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['alt'] ) === false );
check( 'cleanAlt() is the same cleaning, and false for what is no utf-8', \Nino\Images::cleanAlt( " a\r\nb " ) === 'a  b' && \Nino\Images::cleanAlt( "\xFF" ) === false );

// An alt text saved while the slot's record is written by someone else too: only
// its own entry is changed, so an upload that finished in between survives
$earlierRequest = $appData;
$appData['/nino/html/images']['hero']['filename'] = 'stale.jpg';
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => 'Haus' ] );
check( 'setSlotAlt changes the alt entry alone: what config.php holds for the rest of the slot stays, whatever this request believes', ( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['filename'] ?? null ) === 'hero.1600x600.jpg' );
$appData['/nino/html/images']['hero']['filename'] = 'hero.1600x600.jpg';

// ...and the other order: a request that booted before the alt text was saved - an
// upload or a removal that is slow - writes its filename afterwards. Only the filename
// is changed, so the alt text survives, and the request takes the filename as its own
check( 'setSlotFilename changes the filename alone: an alt text saved after this request booted stays, whatever this request believes', isset( $earlierRequest['/nino/html/images']['hero']['alt'] ) === false
	&& \Nino\Images::setSlotFilename( $earlierRequest, 'hero', 'later.1600x600.jpg' ) === true
	&& ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['alt'] === [ 'de_DE' => 'Haus' ]
	&& ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['filename'] === 'later.1600x600.jpg'
	&& $earlierRequest['/nino/html/images']['hero']['filename'] === 'later.1600x600.jpg' );
\Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' );

// A slot this request still knows but config.php no longer holds (a developer deleted
// it in between) is not written back into it
$staleSlot = $appData;
$staleSlot['/nino/html/images']['ghost'] = [ 'label' => 'Ghost', 'width' => 10, 'height' => 10, 'filename' => null ];
check( 'setSlotFilename answers false for a slot config.php does not hold any more, writes nothing and leaves this request as it was', \Nino\Images::setSlotFilename( $staleSlot, 'ghost', 'ghost.10x10.jpg' ) === false
	&& $staleSlot['/nino/html/images']['ghost']['filename'] === null
	&& isset( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['ghost'] ) === false );

check( '[image] prefers the alt stored for the current language over the template\'s own', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt="tpl"]' ), ' alt="Haus">' ) === true );
$appData['./nino/locales/current'] = 'en_US';
check( '...and uses the template\'s where nothing is stored for that language', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt="tpl"]' ), ' alt="tpl">' ) === true );
check( '...and none, alt="", where there is neither', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), ' alt="">' ) === true );

// Shortcode output is rendered once more: a text an editor wrote must not open a fill or a shortcode there
\Nino\Html::addFills( $appData, [ '[[/k]]' => 'FILLED' ], '*' );
\Nino\Filesystem::putFileContent( $appData, '/templates/t.tpl', 'INCLUDED' );
\Nino\Modules\Template::init( $appData );
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => 'x [[/k]] [template /templates/t] "q" <b>' ] );
$hostileAlt = \Nino\Html::renderHtml( $appData, '[image hero]' );
check( 'a stored alt text is escaped and its brackets neutralised: neither the fill nor the template runs', str_contains( $hostileAlt, 'alt="x &#91;&#91;/k]] &#91;template /templates/t] &quot;q&quot; &lt;b&gt;"' ) === true
	&& str_contains( $hostileAlt, 'FILLED' ) === false && str_contains( $hostileAlt, 'INCLUDED' ) === false );
check( '...and it wins over the template\'s own alt text in that language', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt="y"]' ), 'alt="x &#91;' ) === true );
// Between the tags the content is what is rendered, and only for a slot that has a picture: where an address and not a <img> is wanted - a meta tag, a mail
check( '[image]...[/image] renders its content with [[src]] filled in, and an alt text stored for the slot is brackets-neutralised there too', \Nino\Html::renderHtml( $appData, '[image hero]<i data-alt="[[alt]]"></i>[/image]' ) === '<i data-alt="x &#91;&#91;/k]] &#91;template /templates/t] &quot;q&quot; &lt;b&gt;"></i>' );
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => '' ] );
check( '[image]...[/image] fills [[src]], [[width]], [[height]] and [[alt]] - the alt from the template where nothing is stored', \Nino\Html::renderHtml( $appData, '[image hero alt="Haus"]<meta content="https://example.com[[src]]" data-size="[[width]]x[[height]]" data-alt="[[alt]]">[/image]' )
	=== '<meta content="https://example.com/public/images/hero.1600x600.jpg" data-size="1600x600" data-alt="Haus">' );
check( '...and renders nothing at all - no content, no empty tag - for a slot with no file or one that is not there', \Nino\Html::renderHtml( $appData, '[image logo]<meta content="[[src]]">[/image]' ) === '' && \Nino\Html::renderHtml( $appData, '[image nope]<meta content="[[src]]">[/image]' ) === '' );
check( '...and a bare [image] with no content is the <img> as before', str_starts_with( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/public/images/hero.1600x600.jpg"' ) === true );
// The content of a shortcode runs up to the first closing tag, across a second opening of the same one: a bare [image] ahead of the content form has to close itself
check( '...a bare [image] ahead of the content form in one template is written [image x][/image], and then the two do not run into each other', \Nino\Html::renderHtml( $appData, '<p>[image hero alt=""][/image]</p><h1>T</h1>[image hero]<meta content="[[src]]">[/image]' )
	=== '<p><img src="/public/images/hero.1600x600.jpg" width="1600" height="600" alt=""></p><h1>T</h1><meta content="/public/images/hero.1600x600.jpg">' );
$appData['./nino/locales/current'] = 'de_DE';
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => '' ] );
unset( $appData['./nino/html/shortcodes']['template'], $appData['./nino/callbacks']['/nino/html/shortcode/template'] );
$appData['./nino/html/cache'] = false;

echo "\n";


// --- [elementvalues] - the companion shortcode to [elements], looping one
// model key's distinct values instead of records -------------------------

\Nino\Modules\Elements::init( $appData );

$categoryButtons = \Nino\Html::renderHtml( $appData, '[elementvalues /valuetest key="category" sort="value"]<b>[[.id]]:[[.value]]([[.count]])</b>[/elementvalues]' );
check( '[elementvalues] renders one iteration per value, hiding a 0-count option by default',
	$categoryButtons === '<b>0:Consulting(1)</b><b>1:Design(2)</b>' );

$withEmpty = \Nino\Html::renderHtml( $appData, '[elementvalues /valuetest key="category" sort="value" includeEmpty="1"]<b>[[.value]]</b>[/elementvalues]' );
check( '[elementvalues includeEmpty="1"] also renders a declared option with no matching element',
	$withEmpty === '<b>Consulting</b><b>Design</b><b>Development</b>' );

$byCount = \Nino\Html::renderHtml( $appData, '[elementvalues /valuetest key="category" sort="count"]<b>[[.value]]</b>[/elementvalues]' );
check( '[elementvalues sort="count"] orders the most-used value first', $byCount === '<b>Design</b><b>Consulting</b>' );

$declaredOrder = \Nino\Html::renderHtml( $appData, '[elementvalues /valuetest key="category" sort="declared" includeEmpty="1"]<b>[[.value]]</b>[/elementvalues]' );
check( '[elementvalues sort="declared"] keeps the model\'s own option order', $declaredOrder === '<b>Consulting</b><b>Design</b><b>Development</b>' );

check( '[elementvalues] respects "query", scoping counts like [elements] does',
	\Nino\Html::renderHtml( $appData, '[elementvalues /valuetest key="category" query="tag=red" sort="value"]<b>[[.value]]([[.count]])</b>[/elementvalues]' )
	=== '<b>Consulting(1)</b><b>Design(1)</b>' );

check( '[elementvalues] respects "limit"',
	\Nino\Html::renderHtml( $appData, '[elementvalues /valuetest key="category" sort="value" limit="1"]<b>[[.value]]</b>[/elementvalues]' ) === '<b>Consulting</b>' );

check( '[elementvalues] renders nothing without a "key" argument', \Nino\Html::renderHtml( $appData, '[elementvalues /valuetest]<b>[[.value]]</b>[/elementvalues]' ) === '' );
check( '[elementvalues] renders nothing for an unknown type', \Nino\Html::renderHtml( $appData, '[elementvalues /no-such-type key="category"]<b>[[.value]]</b>[/elementvalues]' ) === '' );

// A value is editor/import content, not developer-authored markup - it must
// survive the same escaping [[title]] etc. already get inside [elements]
\Nino\Elements::insertElementType( $appData, '/unsafevaluetest', [ 'label' => [ 'type' => 'string', 'options' => [ '<b>hi</b> & [[bye]]' ] ] ] );
\Nino\Elements::insertElement( $appData, '/unsafevaluetest/a', [ 'label' => '<b>hi</b> & [[bye]]' ], '*' );
check( '[elementvalues] escapes an unsafe value the same way [elements] escapes a field',
	\Nino\Html::renderHtml( $appData, '[elementvalues /unsafevaluetest key="label"]<b>[[.value]]</b>[/elementvalues]' )
	=== '<b>&lt;b&gt;hi&lt;/b&gt; &amp; &#91;&#91;bye]]</b>' );

// Regression: the default sort compares values with strnatcasecmp(), which is
// fatal under strict_types the moment a value arrives as an int - see the
// numeric-value checks against queryElementValues() above. A portfolio
// filtered by year is exactly this, and the error handler turns the TypeError
// into a 500 for the whole page rather than an empty section
check( '[elementvalues] sorts numeric values without a fatal, on every sort mode',
	\Nino\Html::renderHtml( $appData, '[elementvalues /numericvaluetest key="year" sort="value"]<b>[[.value]]</b>[/elementvalues]' ) === '<b>2024</b><b>2025</b>'
	&& \Nino\Html::renderHtml( $appData, '[elementvalues /numericvaluetest key="year" sort="count"]<b>[[.value]]([[.count]])</b>[/elementvalues]' ) === '<b>2024(2)</b><b>2025(1)</b>'
	&& \Nino\Html::renderHtml( $appData, '[elementvalues /numericvaluetest key="year" sort="declared"]<b>[[.value]]</b>[/elementvalues]' ) === '<b>2024</b><b>2025</b>' );

echo "\n";


// --- Elements::queryElements - sort, offset, limit - and [elements] ------

echo "Elements::queryElements / sortElements - order and window\n";

// Four elements, deliberately out of order in the file, with a localized
// title, a number that is missing on one and a string on another, and a
// group two of them share
\Nino\Filesystem::putFileContent( $appData, '/elements/sorttest.php', [
	'title'	=> 'Sort Test',
	'model'	=> [ 'title' => [ 'type' => 'string', 'locale' => true ], 'weight' => [ 'type' => 'integer' ], 'group' => [ 'type' => 'string' ] ],
	'*'			=> [ '*' => [], 'a' => [ 'weight' => 10, 'group' => 'b' ], 'b' => [ 'weight' => 9, 'group' => 'a' ], 'c' => [ 'group' => 'a' ], 'd' => [ 'weight' => '100', 'group' => 'b' ] ],
	'de_DE'	=> [ 'a' => [ 'title' => 'Item 10' ], 'b' => [ 'title' => 'item 9' ], 'c' => [ 'title' => 'Item 2' ], 'd' => [ 'title' => 'Item 1' ] ],
] );

function sortedUris( array &$appData, array $options ): string {
	return implode( ',', array_map( static fn( array $e ): string => basename( (string) $e['.uri'] ), \Nino\Elements::queryElements( $appData, '/sorttest', [], 'de_DE', [], $options ) ) );
}

check( 'without options the file order stands', sortedUris( $appData, [] ) === 'a,b,c,d' );
check( 'sort by a string field is natural and case-insensitive: Item 9 before Item 10', sortedUris( $appData, [ 'sort' => 'title' ] ) === 'd,c,b,a' );
check( 'a leading minus turns it around', sortedUris( $appData, [ 'sort' => '-title' ] ) === 'a,b,c,d' );
check( 'two numbers compare as numbers, even one stored as a string; an element without the field comes last', sortedUris( $appData, [ 'sort' => 'weight' ] ) === 'b,a,d,c' );
check( 'descending, the element without the field still comes last', sortedUris( $appData, [ 'sort' => '-weight' ] ) === 'd,a,b,c' );
check( 'a second field breaks ties of the first', sortedUris( $appData, [ 'sort' => 'group,-weight' ] ) === 'b,c,d,a' );
check( 'the sort is stable: equal values keep the file order', sortedUris( $appData, [ 'sort' => 'group' ] ) === 'b,c,a,d' );
check( 'an empty or meaningless sort leaves the order alone', sortedUris( $appData, [ 'sort' => '' ] ) === 'a,b,c,d' && sortedUris( $appData, [ 'sort' => ' , - ' ] ) === 'a,b,c,d' );
check( 'offset skips, limit cuts, both together page', sortedUris( $appData, [ 'offset' => 1 ] ) === 'b,c,d' && sortedUris( $appData, [ 'limit' => 2 ] ) === 'a,b'
	&& sortedUris( $appData, [ 'sort' => 'title', 'offset' => 1, 'limit' => 2 ] ) === 'c,b' && sortedUris( $appData, [ 'offset' => 9 ] ) === '' );
check( 'a negative offset or limit means none', sortedUris( $appData, [ 'offset' => -3, 'limit' => -1 ] ) === 'a,b,c,d' );
check( 'sort and query combine: filtered first, then ordered', sortedUris( $appData, [ 'sort' => '-weight' ] ) === 'd,a,b,c'
	&& implode( ',', array_map( static fn( array $e ): string => basename( (string) $e['.uri'] ), \Nino\Elements::queryElements( $appData, '/sorttest', [ 'group' => 'b' ], 'de_DE', [], [ 'sort' => '-weight' ] ) ) ) === 'd,a' );
check( 'sortElements() tolerates what is not an element: it sorts last', \Nino\Elements::sortElements( [ 'x', [ 'title' => 'b' ], [ 'title' => 'a' ] ], 'title' ) === [ [ 'title' => 'a' ], [ 'title' => 'b' ], 'x' ] );

check( '[elements] takes sort, offset and limit; ids count from 0 after the cut',
	\Nino\Html::renderHtml( $appData, '[elements /sorttest sort="-weight" offset="1" limit="2"][[.id]]:[[title]];[/elements]' ) === '0:Item 10;1:item 9;' );

// A callback that drops the first hit: the page has to be a page of what
// the callback let through, so offset and limit apply after it
\Nino\Callbacks::registerCallback( $appData, 'sorttest-drop-first', static function( array &$appData, array &$elements ): void {
	array_shift( $elements );
} );
check( '[elements] with a callback: sorted before it, offset and limit after it',
	\Nino\Html::renderHtml( $appData, '[elements /sorttest sort="-weight" callback="sorttest-drop-first" offset="1" limit="1"][[title]];[/elements]' ) === 'item 9;' );

/*	Fills are replaced until nothing changes, because a fill's value may name
	another fill (a mail subject carrying [[/project/website/general/url]], say). Proving that
	the pass just made was the final one meant running a whole further
	str_replace() over the document, once per fill key - so a project with a
	few hundred fills paid that many scans of the page to discover that nothing
	had been left. A document with no '[[' in it any more cannot have anything
	left, and that is one scan for two characters. The comparison still decides
	every other case	*/
\Nino\Html::addFills( $appData, [
	'[[/chain/outer]]'	=> 'outer sees [[/chain/middle]]',
	'[[/chain/middle]]'	=> 'middle sees [[/chain/inner]]',
	'[[/chain/inner]]'	=> 'the inner one',
], '*' );
check( 'a fill whose value names another fill still resolves all the way down', \Nino\Html::renderHtml( $appData, '<p>[[/chain/outer]]</p>' ) === '<p>outer sees middle sees the inner one</p>' );

// The cap that stops a value referencing itself - the Text panel can write one
\Nino\Html::addFills( $appData, [ '[[/chain/loop]]' => 'round [[/chain/loop]]' ], '*' );
check( 'a fill that names itself stops at the pass cap instead of running forever', str_starts_with( \Nino\Html::renderHtml( $appData, '[[/chain/loop]]' ), 'round round round' ) === true );

// A key nothing answers is left standing, and is exactly the case where the
// early exit must not fire - the document still holds '[[' after the pass
check( 'an unresolved key survives the render as itself', \Nino\Html::renderHtml( $appData, '<p>[[/chain/nobody]]</p>' ) === '<p>[[/chain/nobody]]</p>' );

// One byte that is not utf-8 - out of an import, a feed, a paste from a
// latin-1 source - used to take the whole value with it: htmlspecialchars()
// answers invalid input with '' unless ENT_SUBSTITUTE is among the flags, and
// spelling the flags out drops php's own default. The field rendered as
// nothing, silently, with no warning and no log line. \Nino\Form had the same
// defect (see the submission tests further down); this is the render side
\Nino\Filesystem::putFileContent( $appData, '/elements/badbytes.php', [
	'title'	=> 'Bad Bytes',
	'model'	=> [ 'title' => [ 'type' => 'string' ], 'note' => [ 'type' => 'string' ] ],
	'*'			=> [ 'one' => [ 'title' => "Cafe\xE9 Munchen", 'note' => 'plain' ] ],
] );
$badByteRender = \Nino\Html::renderHtml( $appData, '[elements /badbytes][[title]]|[[note]];[/elements]' );
check( 'an element value with one invalid utf-8 byte still renders, replacement character and all', str_contains( $badByteRender, 'Munchen' )
	&& str_contains( $badByteRender, '|plain;' )
	&& $badByteRender !== '|plain;' );
// The rich branch takes the other road - sanitizeHtml(), whose serializer
// escapes every text node - and had the same hole one level down
check( '...and so does a rich field, whose text nodes go through the sanitizer instead', str_contains(
	\Nino\Html::sanitizeHtml( "<p>Cafe\xE9 Munchen</p>" ), 'Munchen'
) === true );

// An image field names the string field that holds its alt text per language.
// An element, or one language of it, that has no value there carries no key at
// all - and the template would show the field itself: alt="[[photoAlt]]"
\Nino\Filesystem::putFileContent( $appData, '/elements/alttest.php', [
	'title'	=> 'Alt Test',
	'model'	=> [
		'photo'			=> [ 'type' => 'image', 'width' => 40, 'height' => 30, 'alt' => 'photoAlt' ],
		'photoAlt'	=> [ 'type' => 'string', 'locale' => true ],
		'plain'			=> [ 'type' => 'image', 'width' => 40, 'height' => 30, 'alt' => 'globalAlt' ],
		'globalAlt'	=> [ 'type' => 'string' ],
	],
	'*'			=> [ '*' => [], 'one' => [ 'photo' => 'one.jpg' ], 'two' => [ 'photo' => 'two.jpg' ] ],
	'de_DE'	=> [ 'one' => [ 'photoAlt' => 'Ein Haus' ] ],
] );
$altLoop = '[elements /alttest locale="%s"]<img src="[[photo]]" alt="[[photoAlt]]" data-plain="[[globalAlt]]">|[/elements]';
check( '[elements] renders the stored alt text of an element, and alt="" for one that has none', \Nino\Html::renderHtml( $appData, sprintf( $altLoop, 'de_DE' ) )
	=== '<img src="one.jpg" alt="Ein Haus" data-plain="[[globalAlt]]">|<img src="two.jpg" alt="" data-plain="[[globalAlt]]">|' );
check( '...and alt="" for every element in a language nobody wrote one for', \Nino\Html::renderHtml( $appData, sprintf( $altLoop, 'en_US' ) )
	=== '<img src="one.jpg" alt="" data-plain="[[globalAlt]]">|<img src="two.jpg" alt="" data-plain="[[globalAlt]]">|' );
check( '[element] does the same, for a language with a value and for one without', \Nino\Html::renderHtml( $appData, '[element /alttest/one locale="de_DE"][[photoAlt]][/element]' ) === 'Ein Haus'
	&& \Nino\Html::renderHtml( $appData, '[element /alttest/one locale="en_US"]<[[photoAlt]]>[/element]' ) === '<>' );
check( 'a link to a field that is global is not read: the fill stays what it was', str_contains( \Nino\Html::renderHtml( $appData, sprintf( $altLoop, 'de_DE' ) ), '[[globalAlt]]' ) === true );

// --- Elements::seed ---------------------------------------------------------
//
// What an install unit brings to a type is added, and never replaces: a unit
// is applied again with every update of a feature, and by the wizard on every
// run until the setup is done, and what an editor wrote or deleted in between
// is not the unit's to take back

echo "Elements::seed - what an install unit adds to a type, and nothing else\n";

$seedUnit = [
	'title' => [ 'de_DE' => 'Abschnitte', 'en_US' => 'Sections' ],
	'model' => [
		'title' 	=> [ 'type' => 'string', 'locale' => true, 'required' => true ],
		'text' 		=> [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ],
		'order' 	=> [ 'type' => 'integer' ],
		'hidden' 	=> [ 'type' => 'boolean' ],
		'ghost' 	=> [ 'type' => 'hologram' ],
	],
	'*' => [
		'*' 	=> [ 'hidden' => false ],
		'one' => [ 'order' => 100 ],
		'two' => [ 'order' => 200 ],
	],
	'de_DE' => [
		'one' => [ 'title' => 'Eins', 'text' => '<p>a</p>' ],
		'two' => [ 'title' => 'Zwei', 'text' => '<p>b</p>' ],
	],
	'en_US' => [
		'one' => [ 'title' => 'One', 'text' => '<p>a</p>' ],
		'two' => [ 'title' => 'Two', 'text' => '<p>b</p>' ],
	],
];

$seedCommitted = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/elements/committed', function( &$appData, &$change ) use ( &$seedCommitted ) { $seedCommitted[] = $change['operation']. ' '. $change['uri']. ' '. $change['locale']; } );

check( 'a type that is not there and a unit with no model: nothing, and no failure', \Nino\Elements::seed( $appData, '/seedtest', [ '*' => [ 'one' => [ 'order' => 1 ] ] ], [ 'de_DE' ] ) === true
	&& is_file( $sandbox. '/private/elements/seedtest.php' ) === false && $seedCommitted === [] );

check( 'a type name that is none is refused with the reason', is_string( \Nino\Elements::seed( $appData, '/Seed Test', $seedUnit, [ 'de_DE' ] ) ) === true
	&& is_string( \Nino\Elements::seed( $appData, '/../x', $seedUnit, [ 'de_DE' ] ) ) === true && is_file( $sandbox. '/private/elements/seedtest.php' ) === false );

check( 'with a model the type is created', \Nino\Elements::seed( $appData, '/seedtest', $seedUnit, [ 'de_DE' ] ) === true && is_file( $sandbox. '/private/elements/seedtest.php' ) === true );
$seedFile = \Nino\Filesystem::getFileContent( $appData, '/elements/seedtest.php', [] );
check( '...with its title in the native language, the model cut to the field types the kernel knows and the defaults of the unit in the bucket every element inherits',
	$seedFile['title'] === 'Abschnitte' && array_keys( $seedFile['model'] ) === [ 'title', 'text', 'order', 'hidden' ] && $seedFile['*']['*'] === [ 'hidden' => false ] );
check( '...its elements in * and in the languages asked for, no others', $seedFile['*']['one'] === [ 'order' => 100 ] && $seedFile['de_DE']['one']['title'] === 'Eins' && isset( $seedFile['en_US'] ) === false );
check( '...a value in the bucket its field says, a field with "locale" per language and the others global', isset( $seedFile['*']['one']['title'] ) === false && isset( $seedFile['de_DE']['one']['order'] ) === false );
check( '...seen in the same request: the element cache was dropped', ( \Nino\Elements::getElement( $appData, '/seedtest/two', 'de_DE' )['title'] ?? null ) === 'Zwei' );
check( '...each element is announced as inserted, as a save in the panel does', $seedCommitted === [ 'insert /seedtest/one *', 'insert /seedtest/two *' ] );

// Idempotent: a second run writes nothing, not even the file's time
$seedBefore = md5_file( $sandbox. '/private/elements/seedtest.php' );
$seedCommitted = [];
clearstatcache();
$seedTime = filemtime( $sandbox. '/private/elements/seedtest.php' );
check( 'a second run answers true and writes nothing', \Nino\Elements::seed( $appData, '/seedtest', $seedUnit, [ 'de_DE' ] ) === true
	&& md5_file( $sandbox. '/private/elements/seedtest.php' ) === $seedBefore && filemtime( $sandbox. '/private/elements/seedtest.php' ) === $seedTime && $seedCommitted === [] );

// What an editor wrote stays; what the unit says about the type does not come back
\Nino\Elements::updateElement( $appData, '/seedtest/one', [ 'title' => 'Mein Titel' ], 'de_DE' );
$seedChanged = $seedUnit;
$seedChanged['title'] = 'Anders';
$seedChanged['model']['title']['required'] = false;
$seedChanged['de_DE']['one']['title'] = 'Wieder Eins';
$seedChanged['*']['one']['order'] = 5;
$seedCommitted = [];
check( 'a value, a title and a model the project has are left as they are', \Nino\Elements::seed( $appData, '/seedtest', $seedChanged, [ 'de_DE' ] ) === true
	&& \Nino\Elements::getElement( $appData, '/seedtest/one', 'de_DE' )['title'] === 'Mein Titel' && \Nino\Elements::getElement( $appData, '/seedtest/one', 'de_DE' )['order'] === 100
	&& \Nino\Filesystem::getFileContent( $appData, '/elements/seedtest.php', [] )['title'] === 'Abschnitte' && \Nino\Elements::getElementModel( $appData, '/seedtest' )['title']['required'] === true && $seedCommitted === [] );

// A language that is new brings the unit's version for an element that is there
$seedCommitted = [];
check( 'a language that was not there is added to the elements that are', \Nino\Elements::seed( $appData, '/seedtest', $seedUnit, [ 'de_DE', 'en_US' ] ) === true
	&& \Nino\Elements::getElement( $appData, '/seedtest/one', 'en_US' )['title'] === 'One' && \Nino\Elements::getElement( $appData, '/seedtest/one', 'de_DE' )['title'] === 'Mein Titel' );
check( '...announced as an update with the language', $seedCommitted === [ 'update /seedtest/one en_US', 'update /seedtest/two en_US' ] );
check( '...and a language the unit has no version for is not made up', \Nino\Elements::seed( $appData, '/seedtest', $seedUnit, [ 'fr_FR' ] ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/elements/seedtest.php', [] )['fr_FR'] ) === false );

// Deleted for good, and remembered: the element does not come back
\Nino\Elements::deleteElement( $appData, '/seedtest/two', '*' );
check( 'an element somebody deleted comes back with the next run - unless it is on the list of what was removed',
	( \Nino\Elements::seed( $appData, '/seedtest', $seedUnit, [ 'de_DE', 'en_US' ] ) === true && \Nino\Elements::getElement( $appData, '/seedtest/two', 'de_DE' ) !== false ) === true );
\Nino\Elements::deleteElement( $appData, '/seedtest/two', '*' );
$appData[ \Nino\Elements::REMOVED ] = [ 'seedtest' => [ 'two' ], 'another' => [ 'one' ] ];
check( '...which names it under its type', \Nino\Elements::seed( $appData, '/seedtest', $seedUnit, [ 'de_DE', 'en_US' ] ) === true
	&& \Nino\Elements::getElement( $appData, '/seedtest/two', 'de_DE' ) === false );
unset( $appData[ \Nino\Elements::REMOVED ] );

// Fields the project's model does not know, and values it refuses
$seedWarnings = [];
set_error_handler( function( int $level, string $message ) use ( &$seedWarnings ) { $seedWarnings[] = $message; return true; } );
$seedOdd = $seedUnit;
$seedOdd['*']['three'] = [ 'order' => 'first', 'nofield' => 'x' ];
$seedOdd['de_DE']['three'] = [ 'title' => 'Drei', 'nofield' => 'y' ];
$seedOdd['*']['../four'] = [ 'order' => 4 ];
$seededOdd = \Nino\Elements::seed( $appData, '/seedtest', $seedOdd, [ 'de_DE' ] );
set_error_handler( function() { return true; } );
$three = \Nino\Filesystem::getFileContent( $appData, '/elements/seedtest.php', [] );
check( 'a value the model refuses is left out with a warning, a field it does not have without one, an id that is no slug is skipped', $seededOdd === true
	&& isset( $three['*']['three']['order'] ) === false && isset( $three['*']['three']['nofield'] ) === false && isset( $three['de_DE']['three']['nofield'] ) === false && $three['de_DE']['three']['title'] === 'Drei'
	&& count( $seedWarnings ) === 1 && str_contains( $seedWarnings[0], 'order' ) === true && isset( $three['*']['../four'] ) === false );

// An element without its required field is still created: the panel marks it
$seedRequired = [ '*' => [ 'five' => [ 'order' => 5 ] ] ];
check( 'an element that lacks a required field is created all the same', \Nino\Elements::seed( $appData, '/seedtest', $seedRequired, [ 'de_DE' ] ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/elements/seedtest.php', [] )['*']['five'] ) === true );

// A file that is there but is no type is nobody's to overwrite
file_put_contents( $sandbox. '/private/elements/brokentype.php', '<?php return "not a type";' );
check( 'a file that does not read as a type is left alone', \Nino\Elements::seed( $appData, '/brokentype', $seedUnit, [ 'de_DE' ] ) === true && str_contains( (string) file_get_contents( $sandbox. '/private/elements/brokentype.php' ), 'not a type' ) === true );

// Failure: a lock or a write that fails is said, as a string
$seedUnwritable = $appData;
$seedUnwritable['./nino/filesystem/cache'] = [];
$seedUnwritable['./nino/filesystem/contentpath'] = $sandbox. '/not-a-private-directory-either';
file_put_contents( $sandbox. '/not-a-private-directory-either', 'x' );
check( 'a type file that cannot be written is a string, the way a unit says it could not copy a file', \Nino\Elements::seed( $seedUnwritable, '/seedfails', $seedUnit, [ 'de_DE' ] ) === 'could not write /elements/seedfails.php' );
unlink( $sandbox. '/not-a-private-directory-either' );
check( 'the lock is released after a run that wrote and after one that did not', probeLockFree( $sandbox, '/elements/seedtest.php' ) === true && probeLockFree( $sandbox, '/elements/brokentype.php' ) === true );

echo "\n";

// --- Features::applyUnit and the 'elements' key -------------------------------

echo "Features::applyUnit - 'elements', added whatever the unit's overwrite says\n";

$unitDir = $sandbox. '/unit-with-elements';
mkdir( $unitDir. '/elements', 0777, true );
file_put_contents( $unitDir. '/manifest.php', '<?php return [ "elements" => [ "unitseed" => "elements/unitseed.php" ] ];' );
file_put_contents( $unitDir. '/elements/unitseed.php', '<?php return '. var_export( $seedUnit, true ). ';' );
$unitRoutes = []; $unitBlacklist = []; $unitConfig = [];

check( 'a unit with "elements" creates the type and its elements', \Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE', 'en_US' ], $unitRoutes, $unitBlacklist, $unitConfig, true ) === true
	&& \Nino\Elements::getElement( $appData, '/unitseed/one', 'en_US' )['title'] === 'One' );
\Nino\Elements::updateElement( $appData, '/unitseed/one', [ 'title' => 'Mein Titel' ], 'de_DE' );
\Nino\Elements::deleteElement( $appData, '/unitseed/two', '*' );
$appData[ \Nino\Elements::REMOVED ] = [ 'unitseed' => [ 'two' ] ];
foreach( [ true, false ] as $overwrite )
	check( 'applied again with overwrite '. ( $overwrite === true ? 'on' : 'off' ). ': the edit stays, the element that was removed stays removed',
		\Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE', 'en_US' ], $unitRoutes, $unitBlacklist, $unitConfig, $overwrite ) === true
		&& \Nino\Elements::getElement( $appData, '/unitseed/one', 'de_DE' )['title'] === 'Mein Titel' && \Nino\Elements::getElement( $appData, '/unitseed/two', 'de_DE' ) === false );
unset( $appData[ \Nino\Elements::REMOVED ] );

foreach( [ '../outside.php', '/etc/passwd', 'elements/../../x.php', 'elements/missing.php', '' ] as $badFile ) {
	file_put_contents( $unitDir. '/manifest.php', '<?php return [ "elements" => [ "unitseed" => '. var_export( $badFile, true ). ' ] ];' );
	check( 'a file named '. var_export( $badFile, true ). ' is a failure of the unit, not a read of whatever is there', is_string( \Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE' ], $unitRoutes, $unitBlacklist, $unitConfig, true ) ) === true );
}
file_put_contents( $unitDir. '/manifest.php', '<?php return [ "elements" => [ "Bad Type" => "elements/unitseed.php" ] ];' );
check( 'a type name that is none is a failure of the unit too', is_string( \Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE' ], $unitRoutes, $unitBlacklist, $unitConfig, true ) ) === true );

echo "\n";

// --- Html::resolveTextfill ---------------------------------------------------

echo "Html::resolveTextfill - what [[key]] would put into a page, nested fills resolved\n";

\Nino\Html::addFills( $appData, [
	'/seed/resolve/plain' 	=> 'Hello',
	'/seed/resolve/nested' 	=> 'Dear [[/seed/resolve/plain]], see [[/seed/resolve/deeper]]',
	'/seed/resolve/deeper' 	=> '[[/seed/resolve/plain]] again',
	'/seed/resolve/empty' 	=> '',
	'/seed/resolve/loop' 		=> '[[/seed/resolve/loop]]',
	'/seed/resolve/code' 		=> 'a [json /seed/resolve/plain] b',
], 'de_DE' );
\Nino\Html::addFills( $appData, [ '/seed/resolve/runtime' => 'runtime value' ], '*' );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( 'a value comes as it is', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/plain' ) === 'Hello' );
check( '...with the fills inside it resolved, the ones inside those too', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/nested' ) === 'Dear Hello, see Hello again' );
check( '...a runtime fill as well', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/runtime' ) === 'runtime value' );
check( 'a key with no value is null, a key with an empty one is the empty string', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/nope' ) === null && \Nino\Html::resolveTextfill( $appData, '/seed/resolve/empty' ) === '' );
check( 'a value that names itself ends after ten passes', str_contains( (string) \Nino\Html::resolveTextfill( $appData, '/seed/resolve/loop' ), '[[/seed/resolve/loop]]' ) === true );
check( 'no shortcode is run, and nothing is escaped', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/code' ) === 'a [json /seed/resolve/plain] b' );
check( 'the language is the current one', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/plain' ) === 'Hello' && ( \Nino\Locales::setCurrentLocale( $appData, 'en_US' ) === 'en_US' ) && \Nino\Html::resolveTextfill( $appData, '/seed/resolve/plain' ) === null );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( '[json] gives the strings it always gave, from the same rule', \Nino\Html::doJsonShortcode( $appData, [ '/seed/resolve/nested' ] ) === '"Dear Hello, see Hello again"'
	&& \Nino\Html::doJsonShortcode( $appData, [ '/seed/resolve/nope' ] ) === '""' && \Nino\Html::doJsonShortcode( $appData, [] ) === '""' );

// Text lives in /text, whatever '/nino/locales/textfiles' says. The key stays
// in AppData::DEFAULTS for catalogue features that read it, and a config.php
// may hold another value - the site must not render words the Text panel
// does not edit
$textDirApp = $appData;
$textDirApp['./nino/filesystem/path']				= $sandbox. '/textdir';
$textDirApp['./nino/filesystem/contentpath']	= $sandbox. '/textdir/private';
$textDirApp['./nino/filesystem/cache']			= [];
$textDirApp['/nino/locales/textfiles']			= '/text2';
\Nino\Filesystem::putFileContent( $textDirApp, '/text/de_DE.php', [ '[[/seed/textdir/probe]]' => 'from /text' ] );
\Nino\Filesystem::putFileContent( $textDirApp, '/text2/de_DE.php', [ '[[/seed/textdir/probe]]' => 'from /text2' ] );
check( 'a page reads its words from /text, whatever /nino/locales/textfiles says', ( \Nino\Html::getFills( $textDirApp )['[[/seed/textdir/probe]]'] ?? null ) === 'from /text' );

$textDirReaders = [];
foreach( [ '/../_nino', '/../_admin' ] as $textDirRoot )
	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__. $textDirRoot, \FilesystemIterator::SKIP_DOTS ) ) as $textDirFile )
		if( $textDirFile->getExtension() === 'php' && str_contains( (string) file_get_contents( $textDirFile->getPathname() ), "'/nino/locales/textfiles'" ) === true )
			$textDirReaders[] = basename( $textDirFile->getPathname() );
check( '...and nothing in the kernel or the workbench reads the key but its own default', $textDirReaders === [ 'AppData.php' ] );

echo "\n";

// --- Html formats: line breaks, paragraphs and lists ---------------------
//
// sanitizeHtml() has three profiles. 'inline' is what it always was - the
// five tags - plus one space where an unwrapped block kept two runs apart;
// 'lines' adds <br>, 'blocks' paragraphs and lists. A field names which it
// keeps (fieldFormat()), a text key has it stored or read from its value
// (detectFormat()), and fieldValue() is the one rule every renderer applies.

echo "Html - formats, fieldValue() and the element shortcodes\n";

check( 'Html::FORMATS lists the four formats, narrowest first', \Nino\Html::FORMATS === [ 'plain', 'inline', 'lines', 'blocks' ] );

// What inline already did, byte for byte - the vectors are the ones the Text,
// Elements and Translations tests pin on their own
foreach( [
	'<strong><em>Bold Italic</em></strong> <code>const x = 1;</code> <script>alert(1)</script> <a href="javascript:alert(2)">bad</a> <a href="/ok">good</a> <img src=x onerror=alert(3)>',
	'pasted <strong>one</strong></div> and the rest',
	'a</div>b <em>c</em>',
	'before <div>inside</div> after',
	'<strong>Fett</strong> und [template /templates/mail-owner]',
	'plain text',
] as $vector )
	check( 'inline keeps '. substr( $vector, 0, 40 ). ' as it was', \Nino\Html::sanitizeHtml( $vector, 'inline' ) === \Nino\Html::sanitizeHtml( $vector ) );

check( 'a format that is not one is read as inline, and plain is not a html format', \Nino\Html::sanitizeHtml( 'a<br>b', 'nonsense' ) === 'a b' && \Nino\Html::sanitizeHtml( 'a<br>b', 'plain' ) === 'a b' );

// The glue: an unwrapped block used to join its neighbours ('Grill.Zweiter')
check( 'inline leaves a space where a paragraph ended', \Nino\Html::sanitizeHtml( '<p>Grill.</p><p>Zweiter Absatz</p>' ) === 'Grill. Zweiter Absatz' );
check( '...a break, a list and a div the same', \Nino\Html::sanitizeHtml( 'Danke.<br>Gruss' ) === 'Danke. Gruss'
	&& \Nino\Html::sanitizeHtml( '<ul><li>a</li><li>b</li></ul>' ) === 'a b'
	&& \Nino\Html::sanitizeHtml( '<div>x</div><div>y</div>' ) === 'x y' );
check( '...and none where a space is there already, or at an end', \Nino\Html::sanitizeHtml( 'a <p>b</p>' ) === 'a b' && \Nino\Html::sanitizeHtml( '<p>a</p> b' ) === 'a b' && \Nino\Html::sanitizeHtml( '<p>a</p>' ) === 'a' );
check( '...Posts\' pinned heading vector keeps its words apart', \Nino\Html::sanitizeHtml( '<h2>Not a heading</h2>Second.' ) === 'Not a heading Second.' );

// lines
check( 'lines: a newline of the text is a <br>', \Nino\Html::sanitizeHtml( "Street 1\n12345 City", 'lines' ) === 'Street 1<br>12345 City' );
check( 'lines: a newline straight after a <br> is source formatting, not a second break', \Nino\Html::sanitizeHtml( "a<br>\nb", 'lines' ) === 'a<br>b' );
check( 'lines: a block that ends is a break, a break at either end is not a line', \Nino\Html::sanitizeHtml( '<p>x</p><p>y</p>', 'lines' ) === 'x<br>y' && \Nino\Html::sanitizeHtml( '<br>a<br>', 'lines' ) === 'a' );
check( 'lines: the inline tags stay, with a break inside one, and everything else goes', \Nino\Html::sanitizeHtml( '<strong>a<br>b</strong><script>x</script><u>c</u>', 'lines' ) === '<strong>a<br>b</strong>c' );
check( 'lines: blank lines the text had are kept', \Nino\Html::sanitizeHtml( "a\n\nb", 'lines' ) === 'a<br><br>b' );

// blocks
$blocks = [
	'<p>Grill.</p><p>Zweiter Absatz</p><ul><li>a</li><li>b</li></ul>'	=> '<p>Grill.</p><p>Zweiter Absatz</p><ul><li>a</li><li>b</li></ul>',
	"Grill.\n\nZweiter Absatz\nmit Umbruch"															=> '<p>Grill.</p><p>Zweiter Absatz<br>mit Umbruch</p>',
	'loose <strong>x</strong><p>para</p>tail'														=> '<p>loose <strong>x</strong></p><p>para</p><p>tail</p>',
	'<li>stray</li><ul>junk<li>ok</li><p>bad</p></ul>'									=> '<p>stray</p><ul><li>junk</li><li>ok</li><li>bad</li></ul>',
	'<p><ul><li>nested</li></ul></p>'																		=> '<ul><li>nested</li></ul>',
	'<h2>Title</h2><div>Box</div><blockquote>Said</blockquote>'					=> '<p>Title</p><p>Box</p><p>Said</p>',
	'<strong><p>in strong</p></strong>'																	=> '<p><strong>in strong</strong></p>',
	'<p><strong><em>deep</em></strong> <a href="javascript:x">j</a> <a href="/ok" onclick="x()">k</a></p>'	=> '<p><strong>deep</strong> j <a href="/ok">k</a></p>',
	"<p>a</p>\n<p>b</p>"																								=> '<p>a</p><p>b</p>',
	'<p><br></p><p>x</p><p></p><ul></ul><ol><li><br></li></ol>'				=> '<p>x</p>',
	'<ol><li><br></li><li>one<br>two</li></ol>'													=> '<ol><li>one<br>two</li></ol>',
	'<p>[template /templates/mail-owner]</p><script>alert(1)</script>'	=> '<p>[template /templates/mail-owner]</p>',
	'<table><tr><td>cell</td></tr></table>'															=> '<p>cell</p>',
	"<strong>a\n\nb</strong>"																			=> '<p><strong>a<br><br>b</strong></p>',
	"<a href=\"/x\">a\n\nb</a>"																		=> '<p><a href="/x">a<br><br>b</a></p>',
	'<li>a</li><li>b</li>'																							=> '<p>a</p><p>b</p>',
	'text<li>item</li>'																									=> '<p>text</p><p>item</p>',
	'a<section>b</section>c'																						=> '<p>a<br>b<br>c</p>',
	"<a href=\"/x\ny\">z</a>"																			=> '<p><a href="/xy">z</a></p>',
];
foreach( $blocks as $in => $out )
	check( 'blocks: '. substr( str_replace( "\n", '\n', $in ), 0, 60 ), \Nino\Html::sanitizeHtml( $in, 'blocks' ) === $out );

// What comes out of a profile is stable: sanitizing it again changes nothing
$unstable = [];
foreach( array_merge( array_keys( $blocks ), [ "Street 1\n12345 City", 'Thank you.<br>Kind regards,', '<p>Grill.</p><p>Zweiter Absatz</p>', 'a</div>b <em>c</em>', '<strong>a<br>b</strong>' ] ) as $vector )
	foreach( [ 'inline', 'lines', 'blocks' ] as $format ) {
		$once = \Nino\Html::sanitizeHtml( $vector, $format );
		if( \Nino\Html::sanitizeHtml( $once, $format ) !== $once )
			$unstable[] = $format. ': '. $vector;
	}
check( 'every profile is idempotent'. ( $unstable === [] ? '' : ' - '. implode( ' | ', $unstable ) ), $unstable === [] );
check( 'a bracket survives the sanitizer - the callers swap it - and nothing becomes a tag', \Nino\Html::sanitizeHtml( '<p>[[/a/key]] [x]</p>', 'blocks' ) === '<p>[[/a/key]] [x]</p>' );

// detectFormat(): the widest thing a value holds
check( 'detectFormat reads plain text, the inline tags, a break and paragraphs or lists',
	\Nino\Html::detectFormat( 'just words' ) === 'plain'
	&& \Nino\Html::detectFormat( 'a <strong>b</strong>' ) === 'inline'
	&& \Nino\Html::detectFormat( 'a<br>b' ) === 'lines'
	&& \Nino\Html::detectFormat( 'a<br />b' ) === 'lines'
	&& \Nino\Html::detectFormat( '<p>a</p>' ) === 'blocks'
	&& \Nino\Html::detectFormat( '<ul><li>a</li></ul>' ) === 'blocks' );
check( '...and the widest wins', \Nino\Html::detectFormat( '<p><strong>a</strong><br>b</p>' ) === 'blocks' && \Nino\Html::detectFormat( '<strong>a</strong><br>b' ) === 'lines' );
check( 'containsHtml still means the inline tags only', \Nino\Html::containsHtml( 'a<br>b' ) === false && \Nino\Html::containsHtml( '<p>a</p>' ) === false && \Nino\Html::containsHtml( '<em>a</em>' ) === true );

// fieldFormat(): what a model says
check( 'fieldFormat reads blocks, inline, breaks and plain from a field',
	\Nino\Html::fieldFormat( [ 'type' => 'string', 'html' => true, 'blocks' => true ] ) === 'blocks'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string', 'html' => true ] ) === 'inline'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string', 'breaks' => true ] ) === 'breaks'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string' ] ) === 'plain'
	&& \Nino\Html::fieldFormat( [] ) === 'plain' );
check( '...and ignores a flag on a field it does not fit - a hand-written model is not trusted',
	\Nino\Html::fieldFormat( [ 'type' => 'string', 'blocks' => true ] ) === 'plain'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string', 'html' => true, 'breaks' => true ] ) === 'inline'
	&& \Nino\Html::fieldFormat( [ 'type' => 'integer', 'breaks' => true ] ) === 'plain' );

// fieldValue(): the rule every renderer of a field applies
check( 'fieldValue keeps paragraphs and lists of a blocks field and swaps a bracket', \Nino\Html::fieldValue( '<p>One [x]</p><ul><li>a</li></ul><script>1</script>', [ 'type' => 'string', 'html' => true, 'blocks' => true ] ) === '<p>One &#91;x]</p><ul><li>a</li></ul>' );
check( 'fieldValue keeps only the inline tags of an html field', \Nino\Html::fieldValue( '<p>One</p><strong>two</strong>', [ 'type' => 'string', 'html' => true ] ) === 'One <strong>two</strong>' );
check( 'fieldValue turns the newlines of a breaks field into <br>, after escaping, and swaps a bracket', \Nino\Html::fieldValue( "a\r\nb <i>\"c\"</i> [[x]]", [ 'type' => 'string', 'breaks' => true ] ) === "a<br>\r\nb &lt;i&gt;&quot;c&quot;&lt;/i&gt; &#91;&#91;x]]" );
check( 'fieldValue escapes everything else, whatever its type', \Nino\Html::fieldValue( '<b>[x]</b>', [] ) === '&lt;b&gt;&#91;x]&lt;/b&gt;' && \Nino\Html::fieldValue( 12, [ 'type' => 'integer' ] ) === '12' );

// breaksToNewlines(): what strip_tags() alone would glue together
check( 'breaksToNewlines makes a line of every break and every end of a block',
	\Nino\Html::breaksToNewlines( 'Amtsgericht<br>Musterstadt' ) === "Amtsgericht\nMusterstadt"
	&& \Nino\Html::breaksToNewlines( '<p>a</p><p>b</p>' ) === "<p>a\n<p>b"
	&& \Nino\Html::breaksToNewlines( '<ul><li>a</li><li>b</li></ul>' ) === "<ul><li>a\n<li>b"
	&& \Nino\Html::breaksToNewlines( 'one<br />two<BR>three' ) === "one\ntwo\nthree"
	&& \Nino\Html::breaksToNewlines( "kept\n" ) === "kept\n" );

// [element] and [elements] hand each field to fieldValue(); [elementvalues] lists values, not fields
\Nino\Filesystem::putFileContent( $appData, '/elements/formats.php', [
	'title'	=> 'Formats',
	'model'	=> [
		'body'	=> [ 'type' => 'string', 'html' => true, 'blocks' => true ],
		'rich'	=> [ 'type' => 'string', 'html' => true ],
		'note'	=> [ 'type' => 'string', 'breaks' => true ],
		'plain'	=> [ 'type' => 'string' ],
	],
	'*'			=> [ 'one' => [
		'body'	=> '<p>One [x]</p><ul><li>a</li></ul><script>1</script>',
		'rich'	=> '<p>One</p><strong>two</strong>',
		'note'	=> "Line 1\nLine <2> [y]",
		'plain'	=> "<p>raw</p>\nnext",
	] ],
] );
$formatsOne = \Nino\Html::renderHtml( $appData, '[element /formats/one][[body]]|[[rich]]|[[note]]|[[plain]][/element]' );
check( '[element] renders a blocks field with its paragraphs and list, a rich one inline',
	str_starts_with( $formatsOne, '<p>One &#91;x]</p><ul><li>a</li></ul>|One <strong>two</strong>|' ) === true );
check( '...a breaks field with <br> and escaped text, a plain field escaped whole',
	str_ends_with( $formatsOne, "|Line 1<br>\nLine &lt;2&gt; &#91;y]|&lt;p&gt;raw&lt;/p&gt;\nnext" ) === true );
check( '[elements] does the same', \Nino\Html::renderHtml( $appData, '[elements /formats][[body]]|[[note]][/elements]' ) === "<p>One &#91;x]</p><ul><li>a</li></ul>|Line 1<br>\nLine &lt;2&gt; &#91;y]" );
check( '[elementvalues] lists plain values: a breaks field\'s are escaped, not turned into <br>', \Nino\Html::renderHtml( $appData, '[elementvalues /formats key="note"][[.value]];[/elementvalues]' ) === "Line 1\nLine &lt;2&gt; &#91;y];" );

echo "\n";


// --- [json] textfills into a hand-written json document -----------------
//
// html-header.tpl's schema.org block writes json by hand and used to drop
// raw fill values into it. '/project/company/contact/address' is multi-line by design, and a
// raw newline in a json string is not valid json, so that block failed to
// parse on every page of every install.

\Nino\Html::init( $appData );
\Nino\Html::addFills( $appData, [
	'/test/json/multiline'	=> "Musterstraße 1\n10115 Berlin",
	'/test/json/quotes'			=> 'We say "hello" & build 5 < 10 sites',
	'/test/json/nested'			=> 'Contact [[/test/json/quotes]]',
], '*' );

check( '[json] wraps a value in its own quotes', \Nino\Html::renderHtml( $appData, '[json /test/json/multiline]' ) === '"Musterstraße 1\n10115 Berlin"' );
check( '[json] renders an unknown key as an empty string literal', \Nino\Html::renderHtml( $appData, '[json /test/json/nope]' ) === '""' );
check( '[json] resolves a nested fill before encoding', str_contains( \Nino\Html::renderHtml( $appData, '[json /test/json/nested]' ), 'hello' ) === true );

$jsonDocument = \Nino\Html::renderHtml( $appData, '{ "streetAddress": [json /test/json/multiline], "description": [json /test/json/quotes] }' );
$jsonDecoded  = json_decode( $jsonDocument, true );
check( 'a document built this way parses as json', is_array( $jsonDecoded ) === true );
check( '...with the multi-line value intact', ( $jsonDecoded['streetAddress'] ?? '' ) === "Musterstraße 1\n10115 Berlin" );
check( '...and the quotes/ampersand round-tripped', ( $jsonDecoded['description'] ?? '' ) === 'We say "hello" & build 5 < 10 sites' );
check( 'nothing that could close the surrounding <script> survives encoding', str_contains( $jsonDocument, '<' ) === false && str_contains( $jsonDocument, '&' ) === false );

// The same document with the old raw-fill approach, as a regression guard
check( 'the raw-fill form this replaces really is invalid json', json_decode( \Nino\Html::renderHtml( $appData, '{ "streetAddress": "[[/test/json/multiline]]" }' ), true ) === null );

echo "\n";


// --- Filesystem cache after a rejected write ----------------------------

echo "Filesystem::putFileContent - failed writes never become cached state\n";

\Nino\Filesystem::putFileContent( $appData, '/data/cache-state.json', [ 'state' => 'old' ] );
check( 'the cache-write fixture starts with the persisted old value', \Nino\Filesystem::getFileContent( $appData, '/data/cache-state.json', [] ) === [ 'state' => 'old' ] );

$recursiveJson = [];
$recursiveJson['self'] =& $recursiveJson;
check( 'an unserializable JSON write is rejected', \Nino\Filesystem::putFileContent( $appData, '/data/cache-state.json', $recursiveJson ) === false );
unset( $recursiveJson );
check( 'a rejected write leaves the previous bytes on disk', json_decode( (string) file_get_contents( \Nino\Filesystem::path( $appData, '/data/cache-state.json' ) ), true ) === [ 'state' => 'old' ] );
check( 'a rejected write leaves the in-request read cache on the persisted value', \Nino\Filesystem::getFileContent( $appData, '/data/cache-state.json', [] ) === [ 'state' => 'old' ] );

/*	And a .json that does not decode: an empty file, a truncated one, one
	holding anything but json. json_decode() answers null for all of them, and
	that null was cached and handed back as the file's content - where
	$default is what every other file that cannot be read answers, and what
	mutate() promises its callback: one typed for the array it was given as
	the default met a TypeError instead	*/
file_put_contents( \Nino\Filesystem::path( $appData, '/data/broken.json' ), '{ "state": ' );
check( 'a .json that does not decode answers the default, like a file that is not there', \Nino\Filesystem::getFileContent( $appData, '/data/broken.json', [ 'fresh' => true ] ) === [ 'fresh' => true ] );

file_put_contents( \Nino\Filesystem::path( $appData, '/data/empty.json' ), '' );
check( '...and so does an empty one', \Nino\Filesystem::getFileContent( $appData, '/data/empty.json', [ 'fresh' => true ] ) === [ 'fresh' => true ] );

// Through a try/catch so the old answer is reported as a failed check rather
// than ending the suite: the callback is typed for an array, and null is not one
$mutatedBroken = ( static function() use ( &$appData ): string {
	try {
		return \Nino\Filesystem::mutate( $appData, '/data/broken.json', static fn( array $state ): array => $state + [ 'added' => true ], [ 'seed' => 1 ] ) === true ? 'written' : 'refused';
	}
	catch( \Throwable $e ) {
		return $e::class;
	}
} )();
check( 'mutate() starts its callback from the default it was given rather than from null', $mutatedBroken === 'written'
	&& \Nino\Filesystem::getFileContent( $appData, '/data/broken.json', [] ) === [ 'seed' => 1, 'added' => true ] );

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

// A burst of parallel guesses has every request pass loginUser()'s cooldown
// check before the first of them locks the bucket, so the registrations
// after that one arrive at a bucket that is already locked. They used to
// count the lock as a fresh first try, which reopened the account the moment
// the maxtries'th attempt had closed it - a lockout that held for guesses
// made one after the other and never for the ones made at once. loginUser()
// itself never registers against a bucket it found locked, so the late
// registration is exercised directly (invokeArgs() with a reference - see
// the Mail::_hit section below for why)
$register			= new ReflectionMethod( '\Nino\Auth', '_registerFailedAttemp' );
$triesBefore	= \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] );
$register->invokeArgs( null, [ &$appData, [ 'ip:127.0.0.1', 'test@example.com' ] ] );
$triesAfter		= \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] );
check( 'a failed attempt registered against a bucket a parallel request has just locked leaves the lock as it is', $triesBefore['test@example.com'] < 0 && $triesAfter['test@example.com'] === $triesBefore['test@example.com'] );
check( '...and still counts the other buckets of the same attempt', $triesAfter['ip:127.0.0.1'] === $triesBefore['ip:127.0.0.1'] + 1 );
check( '...so the account stays locked', \Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' ) === false );

check( 'deleteUser removes the user', \Nino\Auth::deleteUser( $appData, 'test@example.com' ) === true );
check( 'getUser no longer finds the deleted user', \Nino\Auth::getUser( $appData, 'test@example.com' ) === false );

// A successful login has to clear the buckets that were counting towards a
// lockout. They used to survive it - _dropTries() ran on deleteUser() only -
// so a user's occasional typos added up across months of correct logins until
// the maxtries'th one, years apart, tripped the cooldown
\Nino\Auth::insertUser( $appData, 'counter@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
check( 'a login still succeeds with failed attempts on the record', is_array( \Nino\Auth::loginUser( $appData, 'counter@example.com', 'correct horse battery staple' ) ) === true );
check( '...and drops the account\'s bucket from auth-tries.php', isset( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )['counter@example.com'] ) === false );

\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
check( '...so two more typos later do not add up to a lockout', is_array( \Nino\Auth::loginUser( $appData, 'counter@example.com', 'correct horse battery staple' ) ) === true );
\Nino\Auth::deleteUser( $appData, 'counter@example.com' );

/*	The ip bucket's own rules, none of which had an assertion: every login in
	this suite comes from 127.0.0.1, so the bucket was live in all of them and
	read by none of them. Each rule below was proven by breaking it in Auth.php
	and watching its check fail. The buckets are seeded through the file the
	way a burst of requests would have left them, rather than by thirty
	bcrypt rounds	*/
$triesFile	= '/data/auth-tries.php';
$ipFactor		= (int) ( new ReflectionClassConstant( '\Nino\Auth', 'IP_TRIES_FACTOR' ) )->getValue();
$seedTries	= static function( array $seed ) use ( &$appData, $triesFile ): void {
	\Nino\Filesystem::mutate( $appData, $triesFile, static function( array $state ) use ( $seed ): array {
		foreach( $seed as $key => $value )
			if( $value === null )
				unset( $state[$key] );
			else
				$state[$key] = $value;
		return $state;
	} );
};
$readTries	= static fn( string $key ): ?int => \Nino\Filesystem::getFileContent( $appData, $triesFile, [] )[$key] ?? null;

\Nino\Auth::insertUser( $appData, 'bucket@example.com', 'correct horse battery staple' );

// An ip in cooldown is refused before anything else is looked at - the
// right password included - and the refusal moves no account bucket
$seedTries( [ 'ip:127.0.0.1' => 0 - time() - 3600, 'bucket@example.com' => null ] );
check( 'an ip in cooldown is refused with the right password too', \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' ) === false );
check( '...and that refusal does not count against the account', $readTries( 'bucket@example.com' ) === null );

// The ip bucket counts guesses against accounts that do not exist - that is
// what it is for - and trips at maxtries times the factor, never at
// maxtries: a shared exit ip is not one person mistyping
$seedTries( [ 'ip:127.0.0.1' => $appData['/nino/auth/maxtries'] - 1 ] );
\Nino\Auth::loginUser( $appData, 'nobody@example.com', 'wrong' );
check( 'a guess against an account that does not exist counts against the ip', $readTries( 'ip:127.0.0.1' ) === $appData['/nino/auth/maxtries'] );
check( '...and at maxtries the ip is not locked', is_array( \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' ) ) === true );
check( 'a successful login clears the ip bucket along with the account\'s', $readTries( 'ip:127.0.0.1' ) === null );

$seedTries( [ 'ip:127.0.0.1' => $appData['/nino/auth/maxtries'] * $ipFactor - 1 ] );
\Nino\Auth::loginUser( $appData, 'nobody@example.com', 'wrong' );
check( 'at maxtries times the factor the ip is locked, and the right password no longer helps', $readTries( 'ip:127.0.0.1' ) < 0 && \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' ) === false );

// An account already in cooldown feeds no bucket: its owner retrying their
// own locked login must not take their whole ip out with it
$seedTries( [ 'ip:127.0.0.1' => null, 'bucket@example.com' => 0 - time() - 3600 ] );
\Nino\Auth::loginUser( $appData, 'bucket@example.com', 'wrong' );
check( 'a guess against an account in cooldown moves no bucket, the ip\'s included', $readTries( 'ip:127.0.0.1' ) === null && $readTries( 'bucket@example.com' ) < 0 );

// No client ip - the cli, a test - means no ip bucket, rather than one
// shared empty bucket every such caller falls into
$seedTries( [ 'ip:127.0.0.1' => null, 'ip:' => null, 'bucket@example.com' => null ] );
$_SERVER['REMOTE_ADDR'] = '';
\Nino\Auth::loginUser( $appData, 'bucket@example.com', 'wrong' );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
check( 'without a client ip there is no ip bucket at all', array_filter( array_keys( \Nino\Filesystem::getFileContent( $appData, $triesFile, [] ) ), static fn( string $key ): bool => str_starts_with( $key, 'ip:' ) ) === []
	&& $readTries( 'bucket@example.com' ) === 1 );

$seedTries( [ 'bucket@example.com' => null ] );
\Nino\Auth::deleteUser( $appData, 'bucket@example.com' );

// Which accounts are locked out right now, and lifting one lock. Only the
// account buckets are accounts: an ip bucket and a bucket whose account is
// gone are never listed, and a lock that has run out is not one any more
\Nino\Auth::insertUser( $appData, 'locked@example.com', 'correct horse battery staple' );
$seedTries( [ 'ip:127.0.0.1' => null, 'locked@example.com' => null ] );

for( $attempt = 0; $attempt < $appData['/nino/auth/maxtries']; $attempt++ )
	\Nino\Auth::loginUser( $appData, 'locked@example.com', 'wrong' );

$locked = \Nino\Auth::lockedAccounts( $appData );
check( 'an account locked by maxtries failures is listed, until a time in the future', isset( $locked['locked@example.com'] ) === true && $locked['locked@example.com'] > time()
	&& $locked['locked@example.com'] <= time() + $appData['/nino/auth/cooldown'] );
check( '...and the right password no longer logs it in', \Nino\Auth::loginUser( $appData, 'locked@example.com', 'correct horse battery staple' ) === false );

$seedTries( [ 'locked@example.com' => 0 - time() + 5, 'ip:127.0.0.1' => 0 - time() - 3600, 'ghost@example.com' => 0 - time() - 3600 ] );
check( 'a lock that has run out is not listed', isset( \Nino\Auth::lockedAccounts( $appData )['locked@example.com'] ) === false );
check( '...nor is an ip bucket, locked as it is', array_filter( array_keys( \Nino\Auth::lockedAccounts( $appData ) ), static fn( string $key ): bool => str_starts_with( $key, 'ip:' ) ) === [] );
check( '...nor a locked bucket without an account', isset( \Nino\Auth::lockedAccounts( $appData )['ghost@example.com'] ) === false );
check( 'a count that is no lock yet is not listed either', ( function() use ( $seedTries, &$appData ): bool {
	$seedTries( [ 'locked@example.com' => 3 ] );
	return \Nino\Auth::lockedAccounts( $appData ) === [];
} )() === true );

$seedTries( [ 'locked@example.com' => 0 - time() - 3600, 'ghost@example.com' => null ] );
$ipBefore = $readTries( 'ip:127.0.0.1' );
check( 'unlock clears the account\'s bucket', \Nino\Auth::unlock( $appData, 'locked@example.com' ) === true && $readTries( 'locked@example.com' ) === null
	&& isset( \Nino\Auth::lockedAccounts( $appData )['locked@example.com'] ) === false );
check( '...and leaves the locked ip as it was', $readTries( 'ip:127.0.0.1' ) === $ipBefore && $ipBefore < 0 );
$seedTries( [ 'ip:127.0.0.1' => null ] );
check( '...so the right password logs in again', is_array( \Nino\Auth::loginUser( $appData, 'locked@example.com', 'correct horse battery staple' ) ) === true );
check( 'unlock of an account that is not locked is true and changes nothing', \Nino\Auth::unlock( $appData, 'locked@example.com' ) === true );
check( 'unlock of an unknown account is false', \Nino\Auth::unlock( $appData, 'nobody-at-all@example.com' ) === false );

// A tries file that cannot be locked: the lift did not happen and must not say
// it did. A directory where the file's sidecar lock goes is that - the same
// answer a read-only or full disk gives, without needing either
$seedTries( [ 'locked@example.com' => 0 - time() - 3600 ] );
$triesLockKey	= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, $triesFile ] );
$triesLock		= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $triesLockKey ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $triesLock );
@mkdir( $triesLock );
$blockedUnlock = \Nino\Auth::unlock( $appData, 'locked@example.com' );
@rmdir( $triesLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( 'unlock is false when the tries file cannot be written', $blockedUnlock === false );
check( '...and the lock is still there afterwards', $readTries( 'locked@example.com' ) < 0 );
$seedTries( [ 'locked@example.com' => null ] );
\Nino\Auth::deleteUser( $appData, 'locked@example.com' );

// An account written by hand. Perms beside a role are written into config.php
// by hand, and status was until the workbench could set it - so a record that is
// a hash and a permission list and nothing else is a thing a project has, and
// reading a key that is not there is a warning this framework treats as
// fatal: a 500 on the login form rather than a refusal
$appData['/nino/auth/user']['handwritten@example.com'] = [ 'pw' => password_hash( 'correct horse battery staple', PASSWORD_DEFAULT ), 'perms' => [ '/*' ] ];
$handWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$handWarnings ): bool { $handWarnings[] = $message; return true; } );
$handLogin = \Nino\Auth::loginUser( $appData, 'handwritten@example.com', 'correct horse battery staple' );
restore_error_handler();
check( 'a hand-written account without a status is refused, not raised at', $handLogin === false && $handWarnings === [] );

$appData['/nino/auth/user']['handwritten@example.com']['status'] = 2;
$handWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$handWarnings ): bool { $handWarnings[] = $message; return true; } );
$handEnabled = \Nino\Auth::loginUser( $appData, 'handwritten@example.com', 'correct horse battery staple' );
restore_error_handler();
check( '...and one with a status but no sessions list logs in', is_array( $handEnabled ) === true && $handWarnings === [] );
check( '...with the session it just opened', count( \Nino\Auth::getUser( $appData, 'handwritten@example.com' )['sessions'] ) === 1 );
\Nino\Auth::deleteUser( $appData, 'handwritten@example.com' );

// Disabling an account must end the sessions it already holds. _resumeSession()
// checked only that the token was listed and unexpired, so a disabled account
// stayed fully authorised in every browser holding one - for up to SESSION_TTL
\Nino\Auth::insertUser( $appData, 'disabled@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $appData, 'disabled@example.com', 'correct horse battery staple' );

unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a live account is resumed from its session token on the next request', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'disabled@example.com' );

$appData['/nino/auth/user']['disabled@example.com']['status'] = 0;
unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a disabled account is not resumed from that same token', \Nino\Auth::getCurrentUser( $appData ) === false );
check( '...and the token is dropped rather than left to age out', \Nino\Auth::getUser( $appData, 'disabled@example.com' )['sessions'] === [] );
\Nino\Auth::deleteUser( $appData, 'disabled@example.com' );

// An account is switched off and on from the Users panel, and says when it
// logged in last - in its own record, since a file of its own would be one
// more thing a project keeps beside data/ (the catalogue's Hello test holds
// data/ to the one file a feature writes)
\Nino\Auth::insertUser( $appData, 'switch@example.com', 'correct horse battery staple' );
check( 'a new account has never logged in', \Nino\Auth::lastLogin( \Nino\Auth::getUser( $appData, 'switch@example.com' ) ) === 0 );

$beforeLogin = time();
\Nino\Auth::loginUser( $appData, 'switch@example.com', 'correct horse battery staple' );
$switchRecord = \Nino\Auth::getUser( $appData, 'switch@example.com' );
check( 'a login stores the time on the account record', is_int( $switchRecord['lastLogin'] ?? null ) === true && $switchRecord['lastLogin'] >= $beforeLogin && $switchRecord['lastLogin'] <= time() );
check( '...which lastLogin() reads', \Nino\Auth::lastLogin( $switchRecord ) === $switchRecord['lastLogin'] );
check( '...in the record, not in a file of its own', array_filter( array_map( 'basename', glob( \Nino\Filesystem::path( $appData, '/data' ). '/*' ) ?: [] ), static fn( string $file ): bool => str_contains( $file, 'login' ) ) === [] );

$legacy = [ 'sessions' => [ 'old' => [ 'time' => 1000, 'ip' => 'x' ], 'newer' => [ 'time' => 5000, 'ip' => 'y' ], 'broken' => 'not a session' ] ];
check( 'lastLogin() falls back to the newest session for a record without the field', \Nino\Auth::lastLogin( $legacy ) === 5000 );
check( '...takes the later of the field and the sessions', \Nino\Auth::lastLogin( $legacy + [ 'lastLogin' => 9000 ] ) === 9000 && \Nino\Auth::lastLogin( $legacy + [ 'lastLogin' => 100 ] ) === 5000 );
check( '...and is 0 for a record with neither, or with junk in their place', \Nino\Auth::lastLogin( [] ) === 0 && \Nino\Auth::lastLogin( [ 'lastLogin' => 'yesterday', 'sessions' => 'none' ] ) === 0 );

$updates = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/auth/user/update', static function( array &$appData, array &$user ) use ( &$updates ): void { $updates[] = $user['mail']. ':'. $user['status']; } );

check( 'setStatus of an unknown account is false', \Nino\Auth::setStatus( $appData, 'nobody-at-all@example.com', false ) === false );
check( 'setStatus(false) disables the account', \Nino\Auth::setStatus( $appData, 'switch@example.com', false ) === true && \Nino\Auth::getUser( $appData, 'switch@example.com' )['status'] === \Nino\Auth::STATUS_DISABLED );
check( '...ends its sessions, the one this request holds included', \Nino\Auth::getUser( $appData, 'switch@example.com' )['sessions'] === [] && \Nino\Auth::getCurrentUser( $appData ) === false );
check( '...and keeps its last login', \Nino\Auth::lastLogin( \Nino\Auth::getUser( $appData, 'switch@example.com' ) ) === $switchRecord['lastLogin'] );
check( '...tells /nino/auth/user/update', $updates === [ 'switch@example.com:0' ] );
check( 'a disabled account is refused at the login', \Nino\Auth::loginUser( $appData, 'switch@example.com', 'correct horse battery staple' ) === false );
check( 'setStatus(true) lets it in again', \Nino\Auth::setStatus( $appData, 'switch@example.com', true ) === true && is_array( \Nino\Auth::loginUser( $appData, 'switch@example.com', 'correct horse battery staple' ) ) === true
	&& $updates === [ 'switch@example.com:0', 'switch@example.com:2' ] );

// A session resumed on a later request is dropped once the account is off
unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a session of the account is resumed while it is active', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'switch@example.com' );
\Nino\Auth::setStatus( $appData, 'switch@example.com', false );
unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( '...and not once setStatus(false) has run', \Nino\Auth::getCurrentUser( $appData ) === false );

unset( $appData['/nino/callbacks']['/nino/auth/user/update'] );
\Nino\Auth::deleteUser( $appData, 'switch@example.com' );

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

// Roles: a named set of permissions under '/nino/auth/roles' an account
// holds one of - what it may do is its own permissions plus the role's
$appData['/nino/auth/roles'] = [ 'section' => [ 'label' => 'Section', 'perms' => [ '/section/*', 42 ] ] ];
check( 'insertUser refuses a role the config does not have', \Nino\Auth::insertUser( $appData, 'role@example.com', 'correct horse battery staple', [], 'nope' ) === false && \Nino\Auth::getUser( $appData, 'role@example.com' ) === false );
check( 'insertUser stores a known role', \Nino\Auth::insertUser( $appData, 'role@example.com', 'correct horse battery staple', [ '/own/thing' ], 'section' ) === true && \Nino\Auth::getUser( $appData, 'role@example.com' )['role'] === 'section' );
check( 'permissions() is the account\'s own plus the role\'s, strings only', \Nino\Auth::permissions( $appData, \Nino\Auth::getUser( $appData, 'role@example.com' ) ) === [ '/own/thing', '/section/*' ] );
check( 'checkPermission grants through the role', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'role@example.com' ) === true );
check( 'setRole refuses an unknown role and an unknown account', \Nino\Auth::setRole( $appData, 'role@example.com', 'nope' ) === false && \Nino\Auth::setRole( $appData, 'nobody@example.com', 'section' ) === false && \Nino\Auth::getUser( $appData, 'role@example.com' )['role'] === 'section' );
check( 'setRole to none takes the role\'s permissions away, the own ones stay', \Nino\Auth::setRole( $appData, 'role@example.com', '' ) === true && \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'role@example.com' ) === false && \Nino\Auth::checkPermission( $appData, '/own/thing', 'role@example.com' ) === true );
\Nino\Auth::setRole( $appData, 'role@example.com', 'section' );
check( 'the role is persisted with the account', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user']['role@example.com']['role'] === 'section' );
unset( $appData['/nino/auth/roles']['section'] );
check( 'a role the config no longer has grants nothing', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'role@example.com' ) === false );
$appData['/nino/auth/roles']['section'] = [ 'label' => 'Section', 'perms' => [ '/section/*' ] ];
\Nino\Auth::loginUser( $appData, 'role@example.com', 'correct horse battery staple' );
check( 'the signed-in account holds its role\'s permissions', \Nino\Auth::checkPermission( $appData, '/section/thing/manage' ) === true );
\Nino\Auth::setRole( $appData, 'role@example.com', '' );
check( 'a role changed on the signed-in account applies to the very request that changed it', \Nino\Auth::checkPermission( $appData, '/section/thing/manage' ) === false );
\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, 'role@example.com' );
$appData['/nino/auth/roles'] = [];

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
check( 'deleteElement releases the type file lock after a callback veto', probeLockFree( $sandbox, '/elements/hooktest.php' ) === true );

echo "\n";


// --- Filesystem::mutate - the lock comes off however the callback leaves ------------------------------

echo "Filesystem::mutate releases its lock when what runs under it throws\n";

// mutate() releases on both of its own exits - a callback answering null,
// and the write at the end. A throwable had no exit at all: it walked past
// every unlockFile() there is, and lockFile() keeps the handle outside the
// cache slot on purpose, so nothing else dropped it either. The file then
// stayed locked against every other process for the rest of the request.
// Two ways in: the callback itself, and the include of a .php file that no
// longer parses - which throws from inside the same lock, one line earlier
$thrown = null;
try {
	\Nino\Filesystem::mutate( $appData, '/data/throwing.php', static function( mixed $state ): array {
		throw new \RuntimeException( 'the callback gave up' );
	} );
}
catch( \Throwable $e ) {
	$thrown = $e;
}
check( 'a callback\'s throwable carries on out of mutate unchanged', $thrown instanceof \RuntimeException && $thrown->getMessage() === 'the callback gave up' );
check( 'and the lock it walked out of is released', probeLockFree( $sandbox, '/data/throwing.php' ) === true );

file_put_contents( \Nino\Filesystem::path( $appData, '/data/unparseable.php' ), '<?php return [ ' );
$parseError = null;
try {
	\Nino\Filesystem::mutate( $appData, '/data/unparseable.php', static fn( mixed $state ): array => [ 'x' ] );
}
catch( \Throwable $e ) {
	$parseError = $e;
}
check( 'a .php file that no longer parses throws from inside the lock', $parseError instanceof \ParseError );
check( 'and that lock is released as well', probeLockFree( $sandbox, '/data/unparseable.php' ) === true );

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

/*	...and a callback that is not one says so. A hook is a string and a
	callable, and both are easy to get slightly wrong - a method renamed and one
	registration left behind, a typo in 'callbackRespones'. The registration
	returned in silence, the hook never fired, and nothing anywhere said why:
	the symptom is a feature that quietly does not work	*/
$badCallbackWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$badCallbackWarnings ): bool { $badCallbackWarnings[] = $message; return true; } );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ KernelSmokeCallbackTarget::class, 'methodThatIsNotThere' ] );
restore_error_handler();

check( 'a callback that is not callable is refused with a warning naming the hook', count( $badCallbackWarnings ) === 1
	&& str_contains( $badCallbackWarnings[0], "test/callbackshapes" ) === true
	&& str_contains( $badCallbackWarnings[0], 'not callable' ) === true );

$afterBad = [ 'start' => true ];
\Nino\Callbacks::doCallbacks( $appData, 'test/callbackshapes', $afterBad );
check( '...and is not registered, so firing the hook still works', ( $afterBad['closure'] ?? false ) === true && count( $callbackTarget->seen ) === 2 );

// The read-only side: who answers a hook, without firing it
$registered = \Nino\Callbacks::registered( $appData, 'test/callbackshapes' );
check( 'registered() lists the callables in the order they are called, and fires nothing', count( $registered ) === 4 && $registered[0] === [ $callbackTarget, 'instanceMethod' ]
	&& $registered[1] === [ KernelSmokeCallbackTarget::class, 'staticMethod' ] && $registered[2] === 'kernelSmokeCallbackFunction' && $registered[3] instanceof \Closure && count( $callbackTarget->seen ) === 2 );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ KernelSmokeCallbackTarget::class, 'staticMethod' ], 2 );
check( '...a lower priority number first, as doCallbacks() runs it, and a name nobody registered has none', \Nino\Callbacks::registered( $appData, 'test/callbackshapes' )[0] === [ KernelSmokeCallbackTarget::class, 'staticMethod' ]
	&& count( \Nino\Callbacks::registered( $appData, 'test/callbackshapes' ) ) === 5 && \Nino\Callbacks::registered( $appData, 'test/nobody-registered-this' ) === [] );
\Nino\Html::addShortcode( $appData, 'shortcodelist-a', 'kernelSmokeCallbackFunction' );
\Nino\Html::addShortcode( $appData, 'shortcodelist-b', 'kernelSmokeCallbackFunction' );
check( 'Html::shortcodes() lists the registered names, the kernel\'s own among them, in the order they were added', array_slice( \Nino\Html::shortcodes( $appData ), -2 ) === [ 'shortcodelist-a', 'shortcodelist-b' ]
	&& in_array( 'json', \Nino\Html::shortcodes( $appData ), true ) === true );

echo "\n";


// --- AppData::writeContentData concurrency ------------------------------------------------------------

echo "AppData::writeContentData doesn't clobber a concurrent write to a different key\n";

// Two independently-"booted" appData snapshots, both built fresh from the same
// on-disk config.php before either has written - simulating two overlapping
// requests (eg. a login in one tab, an image-slot save in another)
$buildAppData = function() use ( $sandbox ) {
	$fresh = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $fresh );
	$fresh['./nino/filesystem/path'] 				= $sandbox;
	$fresh['./nino/filesystem/configpath'] 	= $sandbox. '/private';
	$fresh['./nino/filesystem/contentpath'] = $sandbox. '/private';
	$fresh['./nino/filesystem/publicpath'] 	= $sandbox. '/public';
	\Nino\AppData::init( $fresh );
	return $fresh;
};

$reqA = $buildAppData();
$reqB = $buildAppData();

$reqA['/nino/auth/user']['race@example.com'] = [ 'marker' => 'FROM_A' ];
\Nino\AppData::writeContentData( $reqA, [ '/nino/auth/user' ] );

$reqB['/nino/html/images'] = [ 'marker' => 'FROM_B' ];
\Nino\AppData::writeContentData( $reqB, [ '/nino/html/images' ] );

$onDisk = include $sandbox. '/private/config.php';
check( 'a later write to a different key doesn\'t erase an earlier request\'s change', ( $onDisk['/nino/auth/user']['race@example.com']['marker'] ?? null ) === 'FROM_A' );
check( 'the later request\'s own change is still persisted', ( $onDisk['/nino/html/images']['marker'] ?? null ) === 'FROM_B' );

// Two overlapping logins of the *same* user: the second must not drop the
// first one's session token just because its own copy predates it
$sessionUser = 'parallel@example.com';
\Nino\Auth::insertUser( $appData, $sessionUser, 'correct horse battery staple' );

$sessions = function() use ( $sandbox, $sessionUser ): array {
	$onDisk = include $sandbox. '/private/config.php';
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

/*	Only the sessions were merged, so everything else about an account was
	whatever the writing request had copied at boot: a login finishing after
	an administrator's change wrote its own stale copy of every record back
	over that change, and an account created in between vanished. The records
	are merged now too - this request's where it changed one, the file's
	where it did not	*/
$accounts = function() use ( $sandbox ): array {
	$onDisk = include $sandbox. '/private/config.php';
	return $onDisk['/nino/auth/user'] ?? [];
};

\Nino\Auth::insertUser( $appData, 'bystander@example.com', 'correct horse battery staple' );

// The slow request: booted, and about to write a session of its own
$slowLogin = $buildAppData();
$slowLogin['./nino/auth/baseline'] = $slowLogin['/nino/auth/user'] ?? [];

// Meanwhile: an administrator changes one account and creates another
$admin = $buildAppData();
$admin['./nino/auth/baseline'] = $admin['/nino/auth/user'] ?? [];
$admin['/nino/auth/user']['bystander@example.com']['marker'] = 'CHANGED_BY_THE_ADMIN';
$admin['/nino/auth/user']['brandnew@example.com'] = [ 'pw' => 'x', 'status' => 2, 'perms' => [], 'sessions' => [] ];
\Nino\AppData::writeContentData( $admin, [ '/nino/auth/user' ] );

$slowLogin['/nino/auth/user'][$sessionUser]['sessions']['tokenSlow'] = [ 'time' => time(), 'ip' => '10.0.0.5' ];
\Nino\AppData::writeContentData( $slowLogin, [ '/nino/auth/user' ] );

check( 'a login finishing later keeps its own session', isset( $sessions()['tokenSlow'] ) === true );
check( '...and does not write its stale copy over an account it never touched', ( $accounts()['bystander@example.com']['marker'] ?? null ) === 'CHANGED_BY_THE_ADMIN' );
check( '...nor drop an account created while it was running', isset( $accounts()['brandnew@example.com'] ) === true );

// The other direction: what this request did change is this request's
$changer = $buildAppData();
$changer['./nino/auth/baseline'] = $changer['/nino/auth/user'] ?? [];
$other = $buildAppData();
$other['./nino/auth/baseline'] = $other['/nino/auth/user'] ?? [];

$other['/nino/auth/user']['bystander@example.com']['marker'] = 'AND_AGAIN';
\Nino\AppData::writeContentData( $other, [ '/nino/auth/user' ] );

$changer['/nino/auth/user']['bystander@example.com']['marker'] = 'CHANGED_HERE';
\Nino\AppData::writeContentData( $changer, [ '/nino/auth/user' ] );
check( 'a record this request did change is written as this request left it', ( $accounts()['bystander@example.com']['marker'] ?? null ) === 'CHANGED_HERE' );

// ...and a deletion is a change like any other: the account stays gone
$deleter = $buildAppData();
$deleter['./nino/auth/baseline'] = $deleter['/nino/auth/user'] ?? [];
unset( $deleter['/nino/auth/user']['brandnew@example.com'] );
\Nino\AppData::writeContentData( $deleter, [ '/nino/auth/user' ] );
check( 'a deleted account is not carried back in from the file', isset( $accounts()['brandnew@example.com'] ) === false );

/*	A login writes its session and the time it happened into the account's own
	record. The time is not a change of the record - or a login finishing
	after an administrator had switched the account off would write the status
	it booted with back over that, and one finishing before would lose its
	timestamp to the administrator's copy. Both orders, with two copies booted
	from the same file	*/
\Nino\Auth::insertUser( $appData, 'merge@example.com', 'correct horse battery staple' );

$loginAt		= 1790000000;
$record 		= function() use ( $accounts ): array { return $accounts()['merge@example.com'] ?? []; };
$bootBoth		= function() use ( $buildAppData ): array {
	$admin = $buildAppData();
	$login = $buildAppData();
	foreach( [ &$admin, &$login ] as &$booted )
		$booted['./nino/auth/baseline'] = $booted['/nino/auth/user'] ?? [];
	unset( $booted );
	return [ $admin, $login ];
};
$doLogin		= function( array &$booted, string $token ) use ( $loginAt ): void {
	$booted['/nino/auth/user']['merge@example.com']['lastLogin'] = $loginAt;
	$booted['/nino/auth/user']['merge@example.com']['sessions'][$token] = [ 'time' => $loginAt, 'ip' => '10.0.0.9' ];
	\Nino\AppData::writeContentData( $booted, [ '/nino/auth/user' ] );
};

// The administrator first, the login finishing after
[ $adminCopy, $loginCopy ] = $bootBoth();
\Nino\Auth::setStatus( $adminCopy, 'merge@example.com', false );
$doLogin( $loginCopy, 'tokenLate' );
check( 'a login finishing after a deactivation does not bring the account back', ( $record()['status'] ?? null ) === \Nino\Auth::STATUS_DISABLED );
check( '...and keeps its own login time', ( $record()['lastLogin'] ?? null ) === $loginAt );

// The login first, the administrator's older copy writing after
\Nino\Auth::setStatus( $appData, 'merge@example.com', true );
[ $adminCopy, $loginCopy ] = $bootBoth();
$doLogin( $loginCopy, 'tokenEarly' );
\Nino\Auth::setStatus( $adminCopy, 'merge@example.com', false );
check( 'a deactivation written after a login keeps the login time', ( $record()['status'] ?? null ) === \Nino\Auth::STATUS_DISABLED && ( $record()['lastLogin'] ?? null ) === $loginAt );
check( '...and ends the session that login opened, as a revocation does', ( $record()['sessions'] ?? null ) === [] );

// A password an administrator changed is not undone by a login in parallel
\Nino\Auth::setStatus( $appData, 'merge@example.com', true );
[ $adminCopy, $loginCopy ] = $bootBoth();
\Nino\Auth::updateUser( $adminCopy, 'merge@example.com', 'merge@example.com', 'the administrator chose this one' );
$doLogin( $loginCopy, 'tokenParallel' );
check( 'an administrator\'s password change is not undone by a parallel login', password_verify( 'the administrator chose this one', (string) ( $record()['pw'] ?? '' ) ) === true );
check( '...and the login keeps its time', ( $record()['lastLogin'] ?? null ) === $loginAt );

// The later of two times wins, whichever copy holds it
[ $adminCopy, $loginCopy ] = $bootBoth();
$adminCopy['/nino/auth/user']['merge@example.com']['lastLogin'] = $loginAt - 500;
$adminCopy['/nino/auth/user']['merge@example.com']['role'] = '';
\Nino\AppData::writeContentData( $adminCopy, [ '/nino/auth/user' ] );
check( 'a record written with an older login time does not turn the clock back', ( $record()['lastLogin'] ?? null ) === $loginAt );
\Nino\Auth::deleteUser( $appData, 'merge@example.com' );

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


// --- AppData::writeContentData - a key the request does not carry -------------

echo "AppData::writeContentData - a key the request does not carry\n";

/*	Regression: the loop persisted '$appData[$key] ?? null' for every key it
	was handed, so a caller naming one its own appData never carried stored an
	explicit null - and a stored null is not an absent key. AppData::init()
	merges config.php *over* DEFAULTS key by key, so that null shadowed the
	framework default on every later boot, for the life of the file	*/
$absentKey = $buildAppData();
unset( $absentKey['/nino/cache/ttl'] );
\Nino\AppData::writeContentData( $absentKey, [ '/nino/cache/ttl' ] );

$absentOnDisk = include $sandbox. '/private/config.php';
check( 'a key the request does not carry is not persisted as null', array_key_exists( '/nino/cache/ttl', $absentOnDisk ) === false );
check( '...so the next boot still reads the framework default', ( $buildAppData()['/nino/cache/ttl'] ?? null ) === \Nino\AppData::DEFAULTS['/nino/cache/ttl'] );

// The other half of the same rule: naming a key that is gone from the
// request is how a value is taken back out of config.php - absent in
// memory, absent in the file
$storedTtl = $buildAppData();
$storedTtl['/nino/cache/ttl'] = 60;
\Nino\AppData::writeContentData( $storedTtl, [ '/nino/cache/ttl' ] );
$withTtl = include $sandbox. '/private/config.php';

$droppedTtl = $buildAppData();
unset( $droppedTtl['/nino/cache/ttl'] );
\Nino\AppData::writeContentData( $droppedTtl, [ '/nino/cache/ttl' ] );
$withoutTtl = include $sandbox. '/private/config.php';

check( 'a value the request dropped is removed from the file rather than nulled', ( $withTtl['/nino/cache/ttl'] ?? null ) === 60
	&& array_key_exists( '/nino/cache/ttl', $withoutTtl ) === false );

// The accounts are deliberately not in that rule - '/nino/auth/user' is
// merged three ways, where a record missing from this request's copy is a
// deletion the merge decides about rather than an absence
$accountsBefore	= ( include $sandbox. '/private/config.php' )['/nino/auth/user'] ?? [];
$noAccounts			= $buildAppData();
unset( $noAccounts['/nino/auth/user'] );
\Nino\AppData::writeContentData( $noAccounts, [ '/nino/auth/user' ] );
$accountsAfter = ( include $sandbox. '/private/config.php' )['/nino/auth/user'] ?? null;

check( 'the accounts key is still written by its own merge, not removed', $accountsBefore !== [] && is_array( $accountsAfter ) === true );

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

// Same as the [image] fragment above: the hidden input is a property, so a
// project that needs a different field name sets the entry rather than
// patching the module
$shippedCsrf = \Nino\Modules\Csrf::$html['input'];
\Nino\Modules\Csrf::$html['input'] = '<input type="hidden" name="authenticity_token" value="[[token]]">';
$replacedCsrf = \Nino\Modules\Csrf::doShortcode( $appData, [] );
\Nino\Modules\Csrf::$html['input'] = $shippedCsrf;
check( 'the hidden input is the property, so replacing it replaces what the shortcode renders', $replacedCsrf === '<input type="hidden" name="authenticity_token" value="'. $token2. '">' );
check( '...and the shipped fragment names the field the kernel checks', str_contains( \Nino\Modules\Csrf::doShortcode( $appData, [] ), 'name="_csrf"' ) === true );


/*	Everything above drives one path: a POST carrying the token in
	$_POST['_csrf']. The guard has four more, every one of them written for a
	failure that had already happened once, and none of them measured:

	- which methods are checked. It used to be POST alone, so a route
	  registered for PUT, DELETE or PATCH was unprotected, and a method the
	  kernel does not recognize ('') stays on the checked side deliberately.
	- the X-CSRF-Token header.
	- the token in a json body - $_POST is empty for a json request, so the
	  field alone 403s every json POST whatever token it carries.
	- the per-route opt-out, and the one thing it must not do: a POST to an
	  address no route is registered for resolves to the /404 route, and a
	  'csrf' => false there would wave through every POST to every
	  unregistered uri site-wide.

	One helper, because each of these is the same call with one thing
	different	*/
function csrfCheck( array &$appData, string $method, string $uri = '/api/thing', array $post = [], array $header = [], string $body = '' ): array {
	$_POST = $post;
	$request = [
		'/nino/http/request'	=> [ 'method' => $method, 'uri' => $uri, 'header' => $header, 'body' => $body ],
		'/nino/http/response'	=> [ 'statusCode' => 200 ],
	];
	\Nino\Csrf::callbackResponse( $appData, $request );
	return $request;
}

/** Whether one such request was let through */
function csrfPassed( array $request ): bool {
	return $request['/nino/http/response']['statusCode'] === 200 && ( $request['./nino/csrf/blocked'] ?? false ) === false;
}

$csrfRoutesBefore = $appData['/nino/http/routes'] ?? null;
$appData['/nino/http/routes'] = [
	'POST://api/thing'	=> [ 'uri' => '/api/thing' ],
	'POST://webhook'		=> [ 'uri' => '/webhook', 'csrf' => false ],
	'POST://hooks/*'		=> [ 'uri' => '/hooks', 'csrf' => false ],
	'POST://plain'			=> [ 'uri' => '/plain', 'csrf' => false ],
	// The trap: a 404 page is GET-only and may reasonably say it needs no token
	'GET://404'					=> [ 'uri' => '/404', 'csrf' => false ],
];

// A safe method carries no token and needs none
check( 'GET, HEAD and OPTIONS pass without a token', csrfPassed( csrfCheck( $appData, 'GET' ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'HEAD' ) ) === true && csrfPassed( csrfCheck( $appData, 'OPTIONS' ) ) === true );

// Every method that writes is checked, not POST alone
check( 'POST, PUT, DELETE and PATCH without a token are all refused', csrfPassed( csrfCheck( $appData, 'POST' ) ) === false
	&& csrfPassed( csrfCheck( $appData, 'PUT' ) ) === false
	&& csrfPassed( csrfCheck( $appData, 'DELETE' ) ) === false
	&& csrfPassed( csrfCheck( $appData, 'PATCH' ) ) === false );
check( '...and all four pass with the current token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => $token2 ] ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'PUT', '/api/thing', [ '_csrf' => $token2 ] ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'DELETE', '/api/thing', [ '_csrf' => $token2 ] ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'PATCH', '/api/thing', [ '_csrf' => $token2 ] ) ) === true );

// A method the kernel does not recognize is '' by the time this runs, and it
// has no business writing anything either
check( 'a method the kernel does not recognize is on the checked side', csrfPassed( csrfCheck( $appData, '' ) ) === false );

// The header, which is the only source a cross-site form cannot set
check( 'the token is taken from X-CSRF-Token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [ 'X-CSRF-Token' => $token2 ] ) ) === true );
check( '...and a wrong one there is refused like any other', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [ 'X-CSRF-Token' => 'nope' ] ) ) === false );

/*	_extractToken() reads that header off the already normalized request
	header rather than $_SERVER, and says so: it is "the one every other
	header read in the kernel already goes through". Every check above hands
	callbackResponse() a header array built by hand, so all of them stay
	green whether or not the kernel actually carries the header that far -
	Http::filterHeaderFields() keeps an allowlist, and a name missing from it
	is dropped in silence, which would leave the whole documented path dead
	with nothing to say so. Driven from $_SERVER through Http::request(), the
	way a real request arrives	*/
function csrfCheckRaw( array &$appData, string $header ): array {

	$_POST = [];
	$request = [
		'REQUEST_METHOD'		=> 'POST',
		'REQUEST_URI'				=> '/api/thing',
		'REMOTE_ADDR'				=> '127.0.0.1',
		'HTTP_X_CSRF_TOKEN'	=> $header,
	];

	\Nino\Http::request( $appData, $request );
	\Nino\Csrf::callbackResponse( $appData, $request );

	return $request;
}

check( 'a client\'s X-CSRF-Token header survives normalization and reaches the guard', csrfPassed( csrfCheckRaw( $appData, $token2 ) ) === true );
check( '...and a wrong one sent that way is refused, so the header is read rather than trusted', csrfPassed( csrfCheckRaw( $appData, 'nope' ) ) === false );

// The json body: $_POST is empty for one of these, so without this path every
// json POST was a 403 whatever token it carried
check( 'the token is taken from a json body', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ '_csrf' => $token2 ] ) ) ) === true );
check( '...and a wrong one there is refused', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ '_csrf' => 'nope' ] ) ) ) === false );
check( '...and a body that is no json at all is not a token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], 'not json' ) ) === false );

// Anything a client can send that is not a string is not a token. '_csrf[]=x'
// parses to an array, and json carries types of its own
check( 'a _csrf field that is an array is no token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => [ $token2 ] ] ) ) === false );
check( '...nor is a json _csrf that is an array', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ '_csrf' => [ $token2 ] ] ) ) ) === false );
check( '...nor a json body that is a list rather than an object', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ $token2 ] ) ) ) === false );

// ...and an empty field falls through to the next source rather than counting
// as an answer, while a wrong one is the answer
check( 'an empty _csrf field falls through to the header', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => '' ], [ 'X-CSRF-Token' => $token2 ] ) ) === true );
check( '...and a wrong one does not', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => 'nope' ], [ 'X-CSRF-Token' => $token2 ] ) ) === false );

// hash_equals(), so neither end of the token is enough
check( 'a prefix of the token is refused', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => substr( $token2, 0, 20 ) ] ) ) === false );
check( '...and so is the token with anything appended', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => $token2. 'x' ] ) ) === false );

// The per-route opt-out a public endpoint needs
check( "a route's own 'csrf' => false lets it through", csrfPassed( csrfCheck( $appData, 'POST', '/webhook' ) ) === true );
check( '...for the method it is registered for, and no other', csrfPassed( csrfCheck( $appData, 'PUT', '/webhook' ) ) === false );

/*	And the thing it must not do. A POST to an address no route is registered
	for is looked up here on its own uri and method, which correctly finds
	nothing - not the /404 route Http::response() has already merged into the
	response array, looked up with method GET. Reading that merged value
	instead would let one 'csrf' => false on the 404 page wave through every
	POST to every unregistered uri on the site	*/
check( 'a POST to an unregistered uri does not inherit the 404 page\'s opt-out', csrfPassed( csrfCheck( $appData, 'POST', '/nowhere' ) ) === false );

// A wildcard route opts out what it declares: its children, not itself, and
// a plain parent route opts out nothing but itself
check( 'a wildcard opt-out covers the children it declares', csrfPassed( csrfCheck( $appData, 'POST', '/hooks/stripe' ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'POST', '/hooks/stripe/v2' ) ) === true );
check( '...and not the address it is written under', csrfPassed( csrfCheck( $appData, 'POST', '/hooks' ) ) === false );
check( 'a plain route\'s opt-out reaches no child of it', csrfPassed( csrfCheck( $appData, 'POST', '/plain/child' ) ) === false );

// What a refusal leaves behind: the status, the flag every later callback has
// to read for itself, and a body that is not a handler's own
$csrfRefused = csrfCheck( $appData, 'POST', '/api/thing' );
check( 'a refusal is a 403 with the blocked flag and no body', $csrfRefused['/nino/http/response']['statusCode'] === 403
	&& ( $csrfRefused['./nino/csrf/blocked'] ?? false ) === true
	&& $csrfRefused['/nino/http/response']['body'] === false );

$_POST = [];
if( $csrfRoutesBefore === null )
	unset( $appData['/nino/http/routes'] );
else
	$appData['/nino/http/routes'] = $csrfRoutesBefore;

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
// module involved anywhere. Use the same Authorization header Nino.js sends
// instead of injecting normalized user/pw values: CGI/FastCGI often exposes
// only HTTP_AUTHORIZATION, which used to leave both values empty and make
// this route answer 401 for every correct login.
$_POST['_csrf'] = \Nino\Csrf::getToken( $appData );
$goodLoginRequest = [
	'REQUEST_METHOD' 			=> 'POST',
	'REQUEST_URI' 				=> '/.nino/auth/login',
	'REMOTE_ADDR' 				=> '127.0.0.1',
	'HTTP_AUTHORIZATION'	=> 'Basic '. base64_encode( $csrfUser. ':correct horse battery staple' ),
];
\Nino\Http::request( $appData, $goodLoginRequest );
\Nino\Csrf::callbackResponse( $appData, $goodLoginRequest );
\Nino\Auth::callbackLoginResponse( $appData, $goodLoginRequest );

check( 'callbackLoginResponse succeeds end-to-end with a Basic header and valid token', $goodLoginRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and actually logs the user in', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === $csrfUser );

/*	...and a refused attempt while that session is still live. The answer
	used to be decided by getCurrentUser(), which answers for the session as
	a whole: loginUser() leaves a resumed session alone when it refuses, so a
	wrong password posted from such a tab was answered 200/true while the
	attempt itself was counted as failed. \Nino.js takes any 200 for a login
	and redirects, so that tab walked into the workbench as the identity it
	already had	*/
$_POST['_csrf'] = \Nino\Csrf::getToken( $appData );
$wrongWhileLoggedIn = [
	'REQUEST_METHOD' 			=> 'POST',
	'REQUEST_URI' 				=> '/.nino/auth/login',
	'REMOTE_ADDR' 				=> '127.0.0.1',
	'HTTP_AUTHORIZATION'	=> 'Basic '. base64_encode( 'nobody@example.com:WRONG' ),
];
\Nino\Http::request( $appData, $wrongWhileLoggedIn );
\Nino\Csrf::callbackResponse( $appData, $wrongWhileLoggedIn );
\Nino\Auth::callbackLoginResponse( $appData, $wrongWhileLoggedIn );

check( 'a refused login answers 401 even while another session is live', $wrongWhileLoggedIn['/nino/http/response']['statusCode'] === 401
	&& $wrongWhileLoggedIn['/nino/http/response']['body'] === false );
check( '...and the session that was live is still the one that is', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === $csrfUser );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, $csrfUser );
unset( $_POST['_csrf'] );

echo "\n";


// --- Modules\Form -------------------------------------------------------

echo "Modules\\Form::callbackResponse - validate/send/record a contact submission\n";

\Nino\Html::addFills( $appData, [
	'[[/project/mail/address/owner]]' 	=> 'owner@example.com',
	'[[/module/form/subject/owner]]' => 'New inquiry',
	'[[/module/form/subject/user]]' 	=> 'Thanks for reaching out',
], '*' );

// A transport that takes every mail. A submission whose owner mail did not go
// out is a 500, so what these checks pin must not depend on whether this
// machine has a sendmail - it is registered once and the unset after the
// 'recording off' check removes it, where the checks that want to read the
// mails register their own
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );

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
check( 'a valid submission bootstraps the data dir on the private root, not under _editor', is_dir( \Nino\Filesystem::path( $appData, '/data' ) ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

$formsDir 	= \Nino\Filesystem::path( $appData, '/data' );
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

// A post that is not a flat map of strings. Casting one raised an engine
// warning Runtime::handleError() treats as fatal - an unauthenticated 500
// from the one endpoint the whole internet may post to
unset( $_POST['_csrf'] );
$arrayRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST = [ 'name' => [ 'x' ], 'email' => 'jo@example.com', 'message' => 'Hi', 'location' => '' ];

$raised = [];
set_error_handler( static function( int $no, string $message ) use ( &$raised ): bool { $raised[] = $message; return true; } );
\Nino\Modules\Form::callbackResponse( $appData, $arrayRequest );
restore_error_handler();

check( 'a field posted as an array is a 400, and raises nothing the error handler would turn fatal', $arrayRequest['/nino/http/response']['statusCode'] === 400 && $raised === [] );

echo "\n";


// --- Form - several forms, and the seam a guard refuses at ------------------

echo "Form - a project's own forms, and refusing a submission ahead of the engine\n";

/*	The shipped form is DEFAULT_FORM, read from there rather than copied
	here: what these pin is that it is offered whole - normalize() drops a
	field it does not accept without a word - and that its fields are typed
	from the vocabulary and named uniquely outside the reserved names, which
	normalize() would silently repair (an unknown type becomes text) rather
	than refuse	*/
$shippedForms = \Nino\Form::forms( $appData );
check( 'with nothing configured, the form this framework ships is what is offered, whole', count( $shippedForms ) === 1 && $shippedForms[0]['key'] === \Nino\Form::DEFAULT_FORM['key']
	&& array_column( $shippedForms[0]['fields'], 'name' ) === array_column( \Nino\Form::DEFAULT_FORM['fields'], 'name' ) );
check( '...its fields typed from the vocabulary, named uniquely and outside the reserved names', array_diff( array_column( \Nino\Form::DEFAULT_FORM['fields'], 'type' ), \Nino\Form::TYPES ) === []
	&& count( array_unique( array_column( \Nino\Form::DEFAULT_FORM['fields'], 'name' ) ) ) === count( \Nino\Form::DEFAULT_FORM['fields'] )
	&& array_intersect( array_column( \Nino\Form::DEFAULT_FORM['fields'], 'name' ), \Nino\Form::RESERVED ) === [] );

$appData[ \Nino\Form::FORMS ] = [
	\Nino\Form::DEFAULT_FORM,
	[
		'key' => 'quote', 'name' => 'Quote', 'to' => 'sales@example.com', 'confirm' => false,
		'fields' => [
			[ 'name' => 'email',   'label' => 'Mail',    'type' => 'email',    'required' => true ],
			[ 'name' => 'budget',  'label' => 'Budget',  'type' => 'number' ],
			[ 'name' => 'date',    'label' => 'Ignored', 'type' => 'text' ],
		],
	],
	[ 'key' => 'broken', 'fields' => [] ],
];

check( 'a project defines its forms in config.php, beside its routes', array_column( \Nino\Form::forms( $appData ), 'key' ) === [ 'contact', 'quote' ] );
check( 'a definition with no usable field is left out rather than half-read', \Nino\Form::form( $appData, 'broken' ) === null );
check( 'a field named like something a record already carries is dropped', array_column( \Nino\Form::form( $appData, 'quote' )['fields'], 'name' ) === [ 'email', 'budget' ] );
check( 'an empty key is the first form defined - which is what a page posting no key belongs to', \Nino\Form::form( $appData, '' )['key'] === 'contact' );

$unknownRequest = submitForm( $appData, [ 'form' => 'nowhere', 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a key no form has is a 404 - a page pointing at a form that was renamed, not spam', $unknownRequest['/nino/http/response']['statusCode'] === 404 );

// handle() used to pre-filter the key against the slug shape and collapse a
// miss to '' - the first form - so a page posting 'Quote' was validated
// against, mailed to and recorded under the contact form instead of 404'd
$shapedKeyRequest = submitForm( $appData, [ 'form' => 'Quote', 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( '...whatever shape the key has - a miss is never routed to the first form', $shapedKeyRequest['/nino/http/response']['statusCode'] === 404 );

// posted() reads keys of at most 64 characters; a definition allowing a
// longer name described a field that could never be submitted
$longNames = \Nino\Form::normalize( [ 'key' => 'long', 'fields' => [ [ 'name' => str_repeat( 'n', 65 ) ], [ 'name' => str_repeat( 'n', 64 ) ] ] ] );
check( 'a field name is bounded at definition time to what posted() reads', array_column( $longNames['fields'] ?? [], 'name' ) === [ str_repeat( 'n', 64 ) ] );

$_POST = [ 'form' => 'quote', 'email' => 'jo@example.com', 'budget' => 'not-a-number', 'location' => '' ];
$badTypeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $badTypeRequest );
check( 'a value that is not of its field\'s declared type is a 400', $badTypeRequest['/nino/http/response']['statusCode'] === 400 );

$_POST = [ 'form' => 'quote', 'email' => 'jo@example.com', 'budget' => '5000', 'location' => '' ];
$quoteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $quoteRequest );
$quoteEntries = \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] );
$quoteEntry   = end( $quoteEntries );
check( 'a submission to another form is accepted and recorded under that form', $quoteRequest['/nino/http/response']['statusCode'] === 200
	&& $quoteEntry['form'] === 'quote' && $quoteEntry['budget'] === '5000' && preg_match( '/^[0-9a-f]{16}$/', $quoteEntry['id'] ) === 1 );
// An entry from before this framework knew more than one form: no 'form',
// no 'id', the values flat beside the date and the ip. A project's history
// has to survive the update untouched
\Nino\Filesystem::mutate( $appData, '/data/forms.'. date( 'Y-m' ). '.php', static function( array $entries ): array {
	$entries[] = [ 'date' => '2020-01-01 00:00:00', 'name' => 'Old Entry', 'email' => 'old@example.com', 'message' => 'Hi', 'cat' => '', 'ip' => '127.0.0.1' ];
	return $entries;
} );
check( 'an entry written before there was more than one form reads as the first form\'s, unchanged otherwise', ( static function( array &$appData ): bool {
	foreach( \Nino\Form::entries( $appData ) as $entry )
		if( ( $entry['name'] ?? '' ) === 'Old Entry' )
			return $entry['form'] === 'contact' && $entry['id'] === '' && $entry['email'] === 'old@example.com' && $entry['date'] === '2020-01-01 00:00:00';
	return false;
} )( $appData ) );

// What is kept, and for how long. Both beside the definitions in
// config.php, so a project decides them the way it decides its forms -
// and the Forms feature's builder writes these very keys
check( 'without a setting, the window is the built-in one', \Nino\Form::retention( $appData ) === \Nino\Form::RETENTION_MONTHS && \Nino\Form::stores( $appData ) === true );

$appData[ \Nino\Form::RETENTION ] = 12;
check( 'a project sets its own window', \Nino\Form::retention( $appData ) === 12 );

$appData[ \Nino\Form::RETENTION ] = 0;
check( 'a window outside 1..60 is a hand edit gone wrong, and falls back rather than deleting everything', \Nino\Form::retention( $appData ) === \Nino\Form::RETENTION_MONTHS );
$appData[ \Nino\Form::RETENTION ] = 999;
check( '...at either end', \Nino\Form::retention( $appData ) === \Nino\Form::RETENTION_MONTHS );
unset( $appData[ \Nino\Form::RETENTION ] );

// A transport takes the mail here: with recording off, a mail that did not
// go out is a 500 (see below), so "goes out" has to mean it
$taken = 0;
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken ): void {
	$taken++;
	$mail['sent'] = true;
} );
$appData[ \Nino\Form::STORE ] = false;
$storedBefore = count( \Nino\Form::entries( $appData ) );
$_POST = [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi', 'location' => '', 'cat' => '' ];
$noStoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $noStoreRequest );
check( 'with recording off the mail still goes out and nothing is written', $noStoreRequest['/nino/http/response']['statusCode'] === 200
	&& $taken === 2 && count( \Nino\Form::entries( $appData ) ) === $storedBefore );
unset( $appData[ \Nino\Form::STORE ], $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );

// What a submission's text survives. posted() cut a value at
// MAX_FIELD_LENGTH with substr(), so a multibyte character at the cut lost
// half its bytes - and render() and record() escaped without
// ENT_SUBSTITUTE, which answers invalid utf-8 with '' rather than a
// replacement character: the message went out and was stored as nothing,
// and the visitor saw ok. A transport takes the mails here, so their bodies
// can be read instead of handed to mail()
$mailed = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$mailed ): void {
	$mailed[] = $mail;
	$mail['sent'] = true;
} );
\Nino\Filesystem::putFileContent( $appData, '/templates/mail-test.tpl', '<p>[[message]]</p>[[fields]]' );
\Nino\Modules\Template::init( $appData );
// The submissions above have used up part of Mail's per-ip cap - a fresh
// window for the ones below, whose mails have to go out
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
$formsBefore = $appData[ \Nino\Form::FORMS ];
$appData[ \Nino\Form::FORMS ] = [ [ 'ownerTemplate' => '/templates/mail-test', 'userTemplate' => '/templates/mail-test' ] + \Nino\Form::DEFAULT_FORM ];
$formsFile = '/data/forms.'. date( 'Y-m' ). '.php';

$almostAll = str_repeat( 'a', \Nino\Form::MAX_FIELD_LENGTH - 1 );
$longRequest	= submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => $almostAll. 'ä und hier geht es weiter' ] );
$formEntries	= \Nino\Filesystem::getFileContent( $appData, $formsFile, [] );
$longEntry		= end( $formEntries );
check( 'a value is cut at MAX_FIELD_LENGTH on a character boundary - the character at the cut goes, the message stays', $longRequest['/nino/http/response']['statusCode'] === 200 && $longEntry['message'] === $almostAll && str_contains( $mailed[0]['body'] ?? '', '<p>'. $almostAll. '</p>' ) === true );

$mailed = [];
$latinRequest	= submitForm( $appData, [ 'name' => "Ren\xE9", 'email' => 'jo@example.com', 'message' => "caf\xE9 au lait" ] );
$formEntries	= \Nino\Filesystem::getFileContent( $appData, $formsFile, [] );
$latinEntry		= end( $formEntries );
check( 'a byte that is not utf-8 becomes the replacement character, in the record and in the mail, rather than blanking the value', $latinRequest['/nino/http/response']['statusCode'] === 200 && $latinEntry['name'] === "Ren\u{FFFD}" && $latinEntry['message'] === "caf\u{FFFD} au lait" && str_contains( $mailed[0]['body'] ?? '', "<p>caf\u{FFFD} au lait</p>" ) === true );

// render() promises a submitted value is never read as a placeholder. It
// filled with str_replace() over arrays, pair by pair over the whole
// string - so the [[fields]] table, inserted first, had the [[date]] and
// [[email]] inside a visitor's message rewritten by the pairs after it
$mailed = [];
$placeholderRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'see [[date]] and [[email]]' ] );
check( 'a submitted value is never read as a placeholder - in the [[fields]] table included', $placeholderRequest['/nino/http/response']['statusCode'] === 200 && substr_count( $mailed[0]['body'] ?? '', 'see [[date]] and [[email]]' ) === 2 );

// A submission the per-ip cap refused was answered ok: 200 and {status: ok}
// were set before the flag was looked at, nothing had gone out and nothing
// was recorded, and the visitor waited for a reply to a message nobody
// received. Over budget is a 429 - the same generic message on the page,
// and the visitor knows to try again later. The cap is Mail's, 5 per hour
$mailed = [];
$countBefore = count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [ '127.0.0.1' => [ 'tries' => 5, 'reset' => time() + 3600 ] ] );
$cappedRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a submission the per-ip mail cap refuses is a 429, not ok - and neither mailed nor recorded', $cappedRequest['/nino/http/response']['statusCode'] === 429
	&& isset( $cappedRequest['/nino/http/response']['body'] ) === false && $mailed === [] && count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) ) === $countBefore );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
unset( $appData['./nino/mail/ratelimited'] );

// A mail that did not go out. The inquiry is recorded where a copy is kept -
// it did happen, and it is in the panel - but the visitor is told it did not
// arrive, whether or not a copy is kept: a 500 and no body, the generic
// message on the page. What the owner reads is the dashboard's notice, and the
// record behind it stays
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = false; } );
$countBefore = count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) );
$keptRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a submission whose owner mail did not go out is a 500, not ok - with no body', $keptRequest['/nino/http/response']['statusCode'] === 500 && isset( $keptRequest['/nino/http/response']['body'] ) === false );
check( '...and the inquiry is still recorded where a copy is kept', count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) ) === $countBefore + 1 );
check( '...and the failure is on record for the dashboard', ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) >= 1 );
$appData[ \Nino\Form::STORE ] = false;
$lostRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( '...and with none kept it is a 500 as well - nothing has the inquiry', $lostRequest['/nino/http/response']['statusCode'] === 500 && isset( $lostRequest['/nino/http/response']['body'] ) === false );
unset( $appData[ \Nino\Form::STORE ] );

// Only the owner's mail decides. A transport that refuses the visitor's
// confirmation and takes the owner's: the owner has the inquiry, and a visitor
// sent back to the form would send it twice - 200 whether it is kept or not
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = $mail['to'] === 'owner@example.com'; } );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
$countBefore = count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) );
$confirmRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'only the visitor\'s confirmation refused, the owner mail out: 200, and recorded', $confirmRequest['/nino/http/response']['statusCode'] === 200
	&& ( $confirmRequest['/nino/http/response']['body']['status'] ?? '' ) === 'ok' && count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) ) === $countBefore + 1 );
$appData[ \Nino\Form::STORE ] = false;
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
$confirmRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( '...and with no copy kept the same: 200', $confirmRequest['/nino/http/response']['statusCode'] === 200 && ( $confirmRequest['/nino/http/response']['body']['status'] ?? '' ) === 'ok' );
unset( $appData[ \Nino\Form::STORE ] );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
/*	The owner's notification goes out in the site's native locale whatever
	language the visitor wrote in, and that switch was made with
	setCurrentLocale() - which writes what it is given into the visitor's
	session. So did the switch back, and what it wrote was whatever
	getCurrentLocale() answered: for a visitor who never picked a locale,
	that is the project's default, which Locales::init() takes care never to
	persist. Its comment says why - "a default nobody chose has no business
	being written into the visitor's session, where it would then outlive a
	later change of the project's native locale" - and sending one inquiry
	was the one thing that wrote it there anyway.

	A fill of its own per locale, because the block above registered
	[[/module/form/subject/owner]] for '*': a fill that answers the same in either
	locale says nothing about which one a mail was rendered in	*/
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
$localeMails = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$localeMails ): void {
	$localeMails[] = $mail;
	$mail['sent'] = true;
} );
\Nino\Html::addFills( $appData, [ '[[/test/locale/probe]]' => 'deutsch' ], 'de_DE' );
\Nino\Html::addFills( $appData, [ '[[/test/locale/probe]]' => 'english' ], 'en_US' );
$appData[ \Nino\Form::FORMS ] = [ [ 'subject' => '[[/test/locale/probe]]', 'ownerTemplate' => '/templates/mail-test', 'userTemplate' => '/templates/mail-test' ] + \Nino\Form::DEFAULT_FORM ];
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );

// A visitor who has chosen nothing, on a page that declares no locale of its
// own: the current locale is the project default (de_DE here), and their
// session holds no locale at all
\Nino\Runtime::unsetSessionValue( $appData, './nino/locales/current' );
\Nino\Locales::useLocale( $appData, \Nino\Locales::getNativeLocale( $appData ) );

$defaultRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'the inquiry is answered as before', $defaultRequest['/nino/http/response']['statusCode'] === 200 );
check( 'sending one writes no locale into the visitor session', \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', 'unwritten' ) === 'unwritten' );

/*	Which is what makes that write more than untidy: init() reads the session
	value back and lets it win over the project's own native locale. With the
	default pinned there, a project that changes its native locale afterwards
	never reaches the visitor who once wrote in - for as long as their session
	lives. A copy of the sandbox, so the same session is read by a kernel
	that boots with a different native locale	*/
$changedProject = $appData;
$changedProject['/nino/locales/native'] = 'en_US';
\Nino\Locales::init( $changedProject );
check( '...so a later change of the project native locale still reaches them', \Nino\Locales::getCurrentLocale( $changedProject ) === 'en_US' );

// The mail is unchanged by all this: the owner's still renders in the native
// locale while the visitor keeps reading the site in theirs
$localeMails = [];
\Nino\Locales::useLocale( $appData, 'en_US' );
$visitorRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'the owner mail still goes out in the native locale', $visitorRequest['/nino/http/response']['statusCode'] === 200 && ( $localeMails[0]['subject'] ?? '' ) === 'deutsch' );
check( '...and the visitor is still reading the site in their own', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US' );

// A locale the visitor did choose is theirs, and a submission leaves it be
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$localeMails = [];
$chosenRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a locale the visitor did choose survives a submission unchanged', $chosenRequest['/nino/http/response']['statusCode'] === 200
	&& \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', '' ) === 'en_US' );
check( '...and the owner mail is still the native one', ( $localeMails[0]['subject'] ?? '' ) === 'deutsch' );

// The two switches, side by side: one is a choice and is remembered, the
// other is this request's business and is not
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( 'setCurrentLocale persists a choice', \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', '' ) === 'de_DE' );
\Nino\Locales::useLocale( $appData, 'en_US' );
check( '...and useLocale switches the request without touching the session', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US'
	&& \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', '' ) === 'de_DE' );
check( '...and neither takes a locale this project does not have', \Nino\Locales::useLocale( $appData, 'fr_FR' ) === 'en_US'
	&& \Nino\Locales::setCurrentLocale( $appData, 'fr_FR' ) === 'en_US' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/locales/current' );
\Nino\Locales::useLocale( $appData, 'de_DE' );

unset( $appData[ \Nino\Form::STORE ], $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/html/shortcodes']['template'], $appData['./nino/callbacks']['/nino/html/shortcode/template'] );
$appData[ \Nino\Form::FORMS ] = $formsBefore;
@unlink( \Nino\Filesystem::path( $appData, '/templates/mail-test.tpl' ) );

// Removing one entry - the request a person makes about their own inquiry.
// By id, which is why record() writes one
$ids = array_values( array_filter( array_column( \Nino\Form::entries( $appData ), 'id' ) ) );
$removeId = $ids[0];
$countBefore = count( \Nino\Form::entries( $appData ) );

check( 'an id nothing carries removes nothing, and says so', \Nino\Form::remove( $appData, str_repeat( 'a', 16 ) ) === false
	&& count( \Nino\Form::entries( $appData ) ) === $countBefore );
check( 'something that is not an id at all is refused without touching a file', \Nino\Form::remove( $appData, '../../config' ) === false
	&& \Nino\Form::remove( $appData, '' ) === false && count( \Nino\Form::entries( $appData ) ) === $countBefore );
check( 'one submission is removed by its id, and the rest of the month stays', \Nino\Form::remove( $appData, $removeId ) === true
	&& count( \Nino\Form::entries( $appData ) ) === $countBefore - 1
	&& in_array( $removeId, array_column( \Nino\Form::entries( $appData ), 'id' ), true ) === false );
check( 'removing it twice is not an error, it is simply not there', \Nino\Form::remove( $appData, $removeId ) === false );

// The seam a spam guard sits at: the same route callback, ahead of the
// module, and why there is no callback name of its own for it (\Nino\Csrf
// refuses the same way one step earlier, on the global /nino/http/response)
$guarded = 0;
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ) use ( &$guarded ): void {
	$guarded++;
	$request['/nino/http/response']['statusCode'] = 418;
}, 1 );

$before = count( \Nino\Form::entries( $appData ) );
$_POST = [ 'name' => 'Spam', 'email' => 'spam@example.com', 'message' => 'Buy', 'location' => '' ];
$refusedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response/POST://.form', $refusedRequest );
check( 'a guard registered ahead of the module refuses without a callback name of its own', $guarded === 1
	&& $refusedRequest['/nino/http/response']['statusCode'] === 418 && count( \Nino\Form::entries( $appData ) ) === $before );

unset( $appData['./nino/callbacks']['/nino/http/response/POST://.form'][1], $appData[ \Nino\Form::FORMS ] );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', [ '\Nino\Modules\Form', 'callbackResponse' ] );

// --- Form - checkbox, radio and date, and what problems() says -------------

echo "Form - checkbox, radio and date fields, and Form::problems()\n";

check( 'the vocabulary holds checkbox, radio and date', array_diff( [ 'checkbox', 'radio', 'date' ], \Nino\Form::TYPES ) === [] );

$typed = \Nino\Form::normalize( [ 'key' => 'typed', 'fields' => [
	[ 'name' => 'consent',	'type' => 'checkbox',	'required' => true ],
	[ 'name' => 'plan',			'type' => 'radio',		'options' => [ 'small', ' large ', '', 5 ] ],
	[ 'name' => 'since',		'type' => 'date' ],
] ] );
check( 'the three normalise to what was declared', array_column( $typed['fields'] ?? [], 'type', 'name' ) === [ 'consent' => 'checkbox', 'plan' => 'radio', 'since' => 'date' ]
	&& $typed['fields'][0]['required'] === true );
check( '...a radio keeping its non-empty options, trimmed', ( $typed['fields'][1]['options'] ?? [] ) === [ 'small', 'large' ] );

$noOptions = [ 'key' => 'noopts', 'fields' => [ [ 'name' => 'plan', 'type' => 'radio' ], [ 'name' => 'blank', 'type' => 'radio', 'options' => [ '', '  ' ] ], [ 'name' => 'kept', 'type' => 'text' ] ] ];
check( 'a radio without a non-empty option is left out', array_column( \Nino\Form::normalize( $noOptions )['fields'] ?? [], 'name' ) === [ 'kept' ] );
check( '...and reported, once for each', \Nino\Form::problems( $noOptions ) === [ [ 'field' => 0, 'code' => 'options' ], [ 'field' => 1, 'code' => 'options' ] ] );
check( 'a select without options stays what it was: kept, and nothing to report', array_column( \Nino\Form::normalize( [ 'key' => 'sel', 'fields' => [ [ 'name' => 'pick', 'type' => 'select' ] ] ] )['fields'] ?? [], 'name' ) === [ 'pick' ]
	&& \Nino\Form::problems( [ 'key' => 'sel', 'fields' => [ [ 'name' => 'pick', 'type' => 'select' ] ] ] ) === [] );

// problems(): the code and the place, for each thing normalize() would change
$oneField = static fn( array $field ): array => [ 'key' => 'probe', 'fields' => [ [ 'name' => 'ok' ], $field ] ];

check( 'a name with a non-ascii letter is reported at its field', \Nino\Form::problems( $oneField( [ 'name' => 'Straße' ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ] );
check( '...a label typed into the name box, with its space', \Nino\Form::problems( $oneField( [ 'name' => 'Ihre Nachricht' ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ] );
check( '...an empty name and one of 65 characters', \Nino\Form::problems( $oneField( [ 'name' => '' ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ]
	&& \Nino\Form::problems( $oneField( [ 'name' => str_repeat( 'n', 65 ) ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ] );
check( '...a reserved name', \Nino\Form::problems( $oneField( [ 'name' => 'date' ] ) ) === [ [ 'field' => 1, 'code' => 'reserved' ] ] );
check( '...a name taken already', \Nino\Form::problems( $oneField( [ 'name' => 'ok' ] ) ) === [ [ 'field' => 1, 'code' => 'duplicate' ] ] );
check( '...a type that is none of the vocabulary, which normalize() repairs to text', \Nino\Form::problems( $oneField( [ 'name' => 'more', 'type' => 'colour' ] ) ) === [ [ 'field' => 1, 'code' => 'type' ] ]
	&& \Nino\Form::normalize( $oneField( [ 'name' => 'more', 'type' => 'colour' ] ) )['fields'][1]['type'] === 'text' );
check( '...while a field with no type is a text field and nothing to report', \Nino\Form::problems( $oneField( [ 'name' => 'more' ] ) ) === [] );
check( '...a field that is not an array', \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [ [ 'name' => 'ok' ], 'text' ] ] ) === [ [ 'field' => 1, 'code' => 'field' ] ] );
check( '...a recipient that is no address', \Nino\Form::problems( [ 'to' => 'a@b' ] + $oneField( [ 'name' => 'more' ] ) ) === [ [ 'field' => null, 'code' => 'to' ] ] );
check( '...a template path that is none, each of the two', \Nino\Form::problems( [ 'ownerTemplate' => '/templates/../x' ] + $oneField( [ 'name' => 'more' ] ) ) === [ [ 'field' => null, 'code' => 'ownerTemplate' ] ]
	&& \Nino\Form::problems( [ 'userTemplate' => 'templates/mail-user' ] + $oneField( [ 'name' => 'more' ] ) ) === [ [ 'field' => null, 'code' => 'userTemplate' ] ] );
check( '...a key that is none', \Nino\Form::problems( [ 'key' => 'Not A Key', 'fields' => [ [ 'name' => 'ok' ] ] ] ) === [ [ 'field' => null, 'code' => 'key' ] ]
	&& \Nino\Form::normalize( [ 'key' => 'Not A Key', 'fields' => [ [ 'name' => 'ok' ] ] ] ) === null );
check( '...also one that is an array, which is a problem and not a warning', \Nino\Form::problems( [ 'key' => [ 'x' ], 'fields' => [ [ 'name' => 'ok' ] ] ] ) === [ [ 'field' => null, 'code' => 'key' ] ]
	&& \Nino\Form::normalize( [ 'key' => [ 'x' ], 'fields' => [ [ 'name' => 'ok' ] ] ] ) === null );
check( '...and a definition with no field left', \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [] ] ) === [ [ 'field' => null, 'code' => 'fields' ] ]
	&& \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [ [ 'name' => 'Straße' ] ] ] ) === [ [ 'field' => 0, 'code' => 'name' ], [ 'field' => null, 'code' => 'fields' ] ] );
check( 'a field that is posted at another place keeps its own index', \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [ 3 => [ 'name' => 'Straße' ], 7 => [ 'name' => 'ok' ] ] ] ) === [ [ 'field' => 3, 'code' => 'name' ] ] );

check( 'the shipped contact form has nothing to report', \Nino\Form::problems( \Nino\Form::DEFAULT_FORM ) === [] );
check( '...nor a clean form of the new types, with an address and templates', \Nino\Form::problems( [
	'key' => 'quote', 'name' => 'Quote', 'to' => 'sales@example.com', 'ownerTemplate' => '/templates/mail-owner', 'userTemplate' => '/templates/mail-user',
	'fields' => [
		[ 'name' => 'email', 'label' => 'Mail', 'type' => 'email', 'required' => true ],
		[ 'name' => 'since', 'label' => 'Since', 'type' => 'date' ],
		[ 'name' => 'plan', 'label' => 'Plan', 'type' => 'radio', 'options' => [ 'small', 'large' ] ],
		[ 'name' => 'consent', 'label' => 'Consent', 'type' => 'checkbox', 'required' => true ],
	],
] ) === [] );

// One routine, so the two cannot drift: nothing to report exactly when
// normalize() keeps every field, the address and both templates
$entries = [
	\Nino\Form::DEFAULT_FORM,
	[ 'key' => 'a', 'fields' => [ [ 'name' => 'x', 'type' => 'radio', 'options' => [ 'one' ] ], [ 'name' => 'y', 'type' => 'date' ] ] ],
	[ 'key' => 'b', 'fields' => [ [ 'name' => 'x', 'type' => 'radio' ] ] ],
	[ 'key' => 'c', 'fields' => [ [ 'name' => 'x' ], [ 'name' => 'x' ] ] ],
	[ 'key' => 'd', 'fields' => [ [ 'name' => 'x', 'type' => 'nope' ] ] ],
	[ 'key' => 'e', 'to' => 'a@b', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'f', 'to' => 'a@b.de', 'ownerTemplate' => '/templates/x', 'userTemplate' => '/templates/y', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'g', 'ownerTemplate' => '/templates/../x', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'h', 'userTemplate' => 'mail-user', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'i', 'fields' => [ [ 'name' => 'Straße' ], [ 'name' => 'date' ], [ 'name' => 'ok' ] ] ],
	[ 'key' => 'Not A Key', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'j', 'fields' => [] ],
	[ 'key' => 'k', 'fields' => [ 'text', [ 'name' => 'x' ] ] ],
];
$agreeing = true;
foreach( $entries as $entry ) {
	$normalized = \Nino\Form::normalize( $entry );
	$declared = array_map( static fn( mixed $f ): array => is_array( $f ) ? [ $f['name'] ?? '', $f['type'] ?? 'text' ] : [ '', '' ], (array) $entry['fields'] );
	$kept = $normalized !== null
		&& array_map( static fn( array $f ): array => [ $f['name'], $f['type'] ], $normalized['fields'] ) === $declared
		&& $normalized['to'] === ( $entry['to'] ?? '' )
		&& $normalized['ownerTemplate'] === ( $entry['ownerTemplate'] ?? '/templates/mail-owner' )
		&& $normalized['userTemplate'] === ( $entry['userTemplate'] ?? '/templates/mail-user' );
	if( ( \Nino\Form::problems( $entry ) === [] ) !== $kept )
		$agreeing = false;
}
check( 'problems() is empty exactly when normalize() keeps every field, the address and both templates', $agreeing === true );

// normalize() cut at bytes: an ascii letter ahead of 120 two-byte letters puts
// every bound (200, 100) in the middle of one, which left invalid utf-8
$wide = 'a'. str_repeat( 'ä', 120 );
$cut = \Nino\Form::normalize( [ 'key' => 'wide', 'name' => $wide, 'subject' => $wide, 'fields' => [ [ 'name' => 'x', 'label' => $wide, 'type' => 'select', 'options' => [ $wide ] ] ] ] );
check( 'a label, an option, a name and a subject cut at their bound stay valid utf-8', $cut !== null
	&& mb_check_encoding( $cut['fields'][0]['label'], 'UTF-8' ) === true && mb_check_encoding( $cut['fields'][0]['options'][0], 'UTF-8' ) === true
	&& mb_check_encoding( $cut['name'], 'UTF-8' ) === true && mb_check_encoding( $cut['subject'], 'UTF-8' ) === true );
check( '...within the bytes they were bound to', strlen( $cut['fields'][0]['label'] ) <= 200 && strlen( $cut['fields'][0]['options'][0] ) <= 200
	&& strlen( $cut['name'] ) <= 100 && strlen( $cut['subject'] ) <= 200 );

// What a submission of each is held to, through the endpoint
$appData[ \Nino\Form::FORMS ] = [ [
	'key' => 'typed', 'name' => 'Typed', 'to' => 'owner@example.com', 'confirm' => false,
	'fields' => [
		[ 'name' => 'consent',	'label' => 'Consent',	'type' => 'checkbox',	'required' => true ],
		[ 'name' => 'plan',			'label' => 'Plan',		'type' => 'radio',		'options' => [ 'small', 'large' ] ],
		[ 'name' => 'since',		'label' => 'Since',		'type' => 'date' ],
	],
] ];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );
$typedPost = static function( array $post ) use ( &$appData ): int {
	\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
	$_POST = array_merge( [ 'form' => 'typed', 'consent' => 'on', 'plan' => '', 'since' => '', 'location' => '' ], $post );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Form::callbackResponse( $appData, $request );
	return $request['/nino/http/response']['statusCode'];
};

check( 'a required checkbox that is ticked is accepted', $typedPost( [] ) === 200 );
check( '...and one that is not ticked is a 400', $typedPost( [ 'consent' => '' ] ) === 400 );
check( 'a radio takes one of its options, and an empty answer where it is not required', $typedPost( [ 'plan' => 'large' ] ) === 200 && $typedPost( [ 'plan' => '' ] ) === 200 );
check( '...and a value outside them is a 400', $typedPost( [ 'plan' => 'huge' ] ) === 400 );
check( 'a date is Y-m-d', $typedPost( [ 'since' => '2026-02-28' ] ) === 200 );
check( '...a day the month does not have is a 400', $typedPost( [ 'since' => '2026-02-30' ] ) === 400 );
check( '...and so is the other way round, and a date with no leading zeros', $typedPost( [ 'since' => '28.02.2026' ] ) === 400 && $typedPost( [ 'since' => '2026-2-3' ] ) === 400 );

// [[fields]] is in the mails a project starts from: the answers to the
// fields nobody knew in advance reach the owner, and the visitor
$shipped = [ 'mail-owner', 'mail-user' ];
$shippedFields = true;
foreach( $shipped as $name )
	$shippedFields = $shippedFields && str_contains( (string) file_get_contents( __DIR__. '/../_nino/Nino/Modules/Form/install/templates/'. $name. '.tpl' ), '[[fields]]' );
check( 'both mail templates the form unit ships carry [[fields]]', $shippedFields === true );

$ownerShipped = (string) file_get_contents( __DIR__. '/../_nino/Nino/Modules/Form/install/templates/mail-owner.tpl' );
\Nino\Filesystem::putFileContent( $appData, '/templates/mail-shipped.tpl', str_replace( [ "[template /templates/mail-header]\n", "\n[template /templates/mail-footer]" ], '', $ownerShipped ) );
\Nino\Modules\Template::init( $appData );
$shippedMail = \Nino\Form::render( $appData, \Nino\Form::form( $appData, 'typed' ), [ 'consent' => 'on', 'plan' => 'large', 'since' => '2026-02-28' ], '/templates/mail-shipped' );
check( 'a submission of fields of its own reaches the owner mail through them', str_contains( $shippedMail, '<th>Consent</th><td>on</td>' )
	&& str_contains( $shippedMail, '<th>Plan</th><td>large</td>' ) && str_contains( $shippedMail, '<th>Since</th><td>2026-02-28</td>' ) );
@unlink( \Nino\Filesystem::path( $appData, '/templates/mail-shipped.tpl' ) );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/html/shortcodes']['template'], $appData['./nino/callbacks']['/nino/html/shortcode/template'], $appData[ \Nino\Form::FORMS ] );
$_POST = [];

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


// --- Mail::send - the transport callback -----------------------------------

echo "Mail::send - '/nino/mail/send' takes a mail before mail() does\n";

check( 'the callback name is a constant', \Nino\Mail::TRANSPORT === '/nino/mail/send' );

// A fresh budget for this test's client ip, whatever ran before
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
unset( $appData['./nino/mail/ratelimited'] );

// The envelope sender is a global textfill, like the owner address it
// falls back to - one place, the Text panel, for both
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'noreply@example.org' ], '*' );

$taken = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken ): void {
	$taken[] = $mail;
	$mail['sent'] = true;
} );

check( 'a transport that sets sent = true makes send() answer true without mail()', \Nino\Mail::send( $appData, 'to@example.org', "Grüße\r\nBcc: x@example.org", '<p>Hallo</p>', 'reply@example.org' ) === true && count( $taken ) === 1 );
check( 'it gets the mail as an array with every key', array_keys( $taken[0] ) === [ 'to', 'subject', 'body', 'replyTo', 'sender', 'headers', 'sent' ] );
check( 'the subject is raw utf-8, not mime-encoded - but cleaned of anything that starts a header line', $taken[0]['subject'] === 'GrüßeBcc: x@example.org' );
check( 'to, body, replyTo and the sender arrive as given', $taken[0]['to'] === 'to@example.org' && $taken[0]['body'] === '<p>Hallo</p>' && $taken[0]['replyTo'] === 'reply@example.org' && $taken[0]['sender'] === 'noreply@example.org' );
check( 'the headers are what mail() would get', str_contains( $taken[0]['headers'], 'Content-Type: text/html; charset=UTF-8' ) && str_contains( $taken[0]['headers'], "\r\nFrom: noreply@example.org" ) && str_contains( $taken[0]['headers'], "\r\nReply-To: reply@example.org" ) );
check( 'a display-name address is reduced to the address before the transport sees it', \Nino\Mail::send( $appData, 'Max Mustermann <max@example.org>', 'x', 'y', '' ) === true && $taken[1]['to'] === 'max@example.org' && $taken[1]['replyTo'] === '' );
check( 'an invalid address is refused before any transport', \Nino\Mail::send( $appData, 'not an address', 'x', 'y', '' ) === false && count( $taken ) === 2 );

// mail() refuses a nul byte in any of its arguments with a ValueError -
// which nothing caught, so a message with one (a form field can carry it)
// was a 500 rather than the false send() promises. Dropped with the CR/LF,
// before any transport sees the mail
check( 'a nul byte is dropped from the body and every header value, never thrown at', \Nino\Mail::send( $appData, 'to@example.org', "Sub\0ject", "Hello\0world", "re\0ply@example.org" ) === true
	&& $taken[2]['body'] === 'Helloworld' && $taken[2]['subject'] === 'Subject' && $taken[2]['replyTo'] === 'reply@example.org' );

/*	The reply address was the one thing on a header line here that nothing
	checked. $to is validated and refuses the mail; _getSender() validates
	From and drops it rather than "passing something unchecked to sendmail".
	The reply address had neither, and it comes from where those two do: an
	admin-editable textfill read through renderHtml(). A fill the project
	never installed renders as its own literal, so '[[/project/mail/address/owner]]'
	went out as the Reply-To header verbatim. That fill belongs to the Form
	module's install unit and the wizard offers that module rather than
	installing it always, so a project running the Newsletter feature without
	the contact form sent every confirmation mail with a header naming a fill.

	It is dropped, not refused: the recipient and the body are fine, and a
	confirmation nobody receives is worse than one nobody can reply to. This
	suite silences trigger_error() wholesale, so the line it records is
	captured around the calls	*/
// Six more mails than this block budgeted for - a fresh window, the same way
// the block above opened one
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
unset( $appData['./nino/mail/ratelimited'] );

$replyWarnings = [];
set_error_handler( static function( int $level, string $message ) use ( &$replyWarnings ): bool { $replyWarnings[] = $message; return true; } );

$replySent = [];
foreach( [ '[[/project/mail/address/owner]]', 'ask us anything', 'a@example.org, b@example.org', '<script>alert(1)</script>' ] as $notAnAddress )
	$replySent[$notAnAddress] = \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', $notAnAddress );

// Six sends, and the per-ip cap is five an hour - a fresh window between the
// two groups, or the last of them never reaches a transport at all
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
unset( $appData['./nino/mail/ratelimited'] );

$replyKept = [];
foreach( [ 'reply@example.org', 'Max Mustermann <max@example.org>' ] as $isAnAddress )
	$replyKept[$isAnAddress] = \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', $isAnAddress );

restore_error_handler();

$replyHeaders = array_map( static fn( array $mail ): string => (string) $mail['replyTo'], array_slice( $taken, 3 ) );

check( 'an unresolved textfill does not go out as the Reply-To header', ( $replyHeaders[0] ?? null ) === '' );
check( '...nor does anything else that is not an address', ( $replyHeaders[1] ?? null ) === '' && ( $replyHeaders[2] ?? null ) === '' && ( $replyHeaders[3] ?? null ) === '' );
check( '...and the mail still goes out - the recipient and the body were never the problem', array_values( $replySent ) === [ true, true, true, true ] );
check( '...with no Reply-To line on it at all', str_contains( $taken[3]['headers'] ?? '', 'Reply-To:' ) === false );
check( '...and one recorded line per mail, naming the value, or nothing tells the operator why replies stopped',
	count( array_filter( $replyWarnings, static fn( string $w ): bool => str_contains( $w, 'is no reply address' ) === true ) ) === 4
	&& count( array_filter( $replyWarnings, static fn( string $w ): bool => str_contains( $w, '[[/project/mail/address/owner]]' ) === true ) ) === 1 );

// A real address is untouched, and so is the display-name form - valid for
// this header, unlike mail()'s own $to, and what a site owner types
/*	Where the From header and the envelope sender come from, which is one
	textfill for every mail this framework sends. It is shipped as
	'[[/project/company/contact/email]]' rather than as an address, so the normal case is the
	mailbox the project already named - one answer, in one place - and an
	operator who needs another one overwrites the key without touching the
	company address. A chained fill, and this is the check that it resolves:
	a value nobody resolves is a From header reading '[[/project/company/contact/email]]'	*/
\Nino\Html::addFills( $appData, [ '[[/project/company/contact/email]]' => 'hallo@example.com', '[[/project/mail/address/owner]]' => '[[/project/company/contact/email]]', '[[/project/mail/address/envelope]]' => '' ], '*' );

$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
unset( $appData['./nino/mail/ratelimited'] );

$chained = [];
$chainedTransport = static function( array &$appData, array &$mail ) use ( &$chained ): void { $chained[] = $mail; $mail['sent'] = true; };
$transportBefore = $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] ?? null;
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, $chainedTransport );

\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
check( 'the sender is the company address the owner fill points at', ( $chained[0]['sender'] ?? null ) === 'hallo@example.com' );
check( '...and reaches the From header as an address, not as the fill it was written as', str_contains( $chained[0]['headers'] ?? '', "\r\nFrom: hallo@example.com" ) === true );

// An operator who needs a different mailbox overwrites the one key
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/owner]]' => 'kontakt@example.org' ], '*' );
$chained = [];
\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
check( 'overwriting that one key changes the sender and nothing else', ( $chained[0]['sender'] ?? null ) === 'kontakt@example.org'
	&& \Nino\Html::renderHtml( $appData, '[[/project/company/contact/email]]' ) === 'hallo@example.com' );

/*	...and '[[/project/mail/address/envelope]]' wins over both where it is set, because the
	envelope sender has to be an address the sending host may send for
	(spf/dmarc), which is not necessarily the mailbox replies should reach.
	A textfill like the owner address, not a config.php key: the operator
	sets both in the Text panel, and an empty one means "the same"	*/
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'no-reply@example.net' ], '*' );
$chained = [];
\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
check( 'the sender fill wins over the owner fill', ( $chained[0]['sender'] ?? null ) === 'no-reply@example.net'
	&& str_contains( $chained[0]['headers'] ?? '', "\r\nFrom: no-reply@example.net" ) === true );

// ...and one that is not an address falls back to the owner rather than
// costing every mail its From - a typo in the Text panel is one log line
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'not an address' ], '*' );
$chained = []; $senderWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$senderWarnings ): bool { if( ( error_reporting() & $no ) !== 0 ) $senderWarnings[] = $message; return true; } );
\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
restore_error_handler();
check( 'a sender fill that is no address falls back to the owner, and says so', ( $chained[0]['sender'] ?? null ) === 'kontakt@example.org'
	&& count( array_filter( $senderWarnings, static fn( string $w ): bool => str_contains( $w, 'no sender address' ) ) ) === 1 );

\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => '' ], '*' );
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
if( $transportBefore !== null )
	$appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] = $transportBefore;
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
unset( $appData['./nino/mail/ratelimited'] );

check( 'a plain reply address is untouched', ( $replyHeaders[4] ?? null ) === 'reply@example.org' && ( $replyKept['reply@example.org'] ?? false ) === true );
check( '...and a display-name address keeps its name, because only the address part has to hold up',
	( $replyHeaders[5] ?? null ) === 'Max Mustermann <max@example.org>'
	&& str_contains( $taken[8]['headers'] ?? '', "\r\nReply-To: Max Mustermann <max@example.org>" ) === true );

// ...and back to the budget the rest of this block was written against
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
unset( $appData['./nino/mail/ratelimited'] );

// A first transport that leaves sent alone passes the mail on; the one
// after it decides
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
$seen = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$seen ): void {
	$seen[] = 'looked';
}, 1 );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$seen ): void {
	$seen[] = 'refused';
	$mail['sent'] = false;
}, 2 );
check( 'a transport that leaves sent at null passes the mail on; sent = false is a refusal', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === false && $seen === [ 'looked', 'refused' ] );

// The cap comes first: over budget, no transport is asked
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
$rateState['127.0.0.1'] = [ 'tries' => 5, 'reset' => time() + 3600 ];
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
$seen = [];
check( 'the per-ip cap applies before any transport', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === false && $seen === [] && ( $appData['./nino/mail/ratelimited'] ?? false ) === true );

// One action of one visitor, several envelopes: a contact form's owner
// notification and the confirmation that answers it. Charging each mail
// separately made a cap of five allow two submissions, and the third was
// answered "sent" while nothing left the server
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/mail/ratelimited'] );
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState['127.0.0.1'] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

$batched = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$batched ): void {
	$batched[] = $mail['to'];
	$mail['sent'] = true;
} );

$pair = [
	[ 'to' => 'owner@example.org',   'subject' => 'a', 'body' => 'x', 'replyTo' => 'jo@example.org' ],
	[ 'to' => 'jo@example.org',      'subject' => 'b', 'body' => 'y', 'replyTo' => 'owner@example.org' ],
];
check( 'sendAll delivers every mail of one action', \Nino\Mail::sendAll( $appData, $pair ) === true && $batched === [ 'owner@example.org', 'jo@example.org' ] );
check( 'and charges the cap once for the pair, not once per envelope', ( \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] )['127.0.0.1']['tries'] ?? 0 ) === 1 );

$batched = [];
for( $i = 0; $i < 4; $i++ )
	\Nino\Mail::sendAll( $appData, $pair );
check( 'so five submissions fit the window of five, and the sixth is refused whole', count( $batched ) === 8
	&& \Nino\Mail::sendAll( $appData, $pair ) === false && count( $batched ) === 8 && ( $appData['./nino/mail/ratelimited'] ?? false ) === true );
check( 'an empty batch is not an action and costs nothing', \Nino\Mail::sendAll( $appData, [] ) === true );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/mail/ratelimited'] );
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => '' ], '*' );

echo "\n";


// --- Mail - the record of mail that did not go out --------------------------

echo "Mail::failure - a call that failed leaves a record, one that delivered everything clears it\n";

/*	The workbench's dashboard says "mail has been failing since ...", and
	what it says rests on this: /data/mail-status.php, written once per call
	of send() or sendAll() rather than once per mail. A contact form sends the
	owner's mail and the visitor's confirmation as one call, and a
	confirmation that went out must not wipe the failure of the mail the owner
	is waiting for	*/
$statusPath = '/data/mail-status.php';
$refused 		= [ 'refused@example.org' ];
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/mail/ratelimited'] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$refused ): void {
	$mail['sent'] = in_array( $mail['to'], $refused, true ) === false;
} );
$freshWindow = static function() use ( &$appData, $ratelimitPath ): void {
	\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, [] );
	unset( $appData['./nino/mail/ratelimited'] );
};

@unlink( \Nino\Filesystem::path( $appData, $statusPath ) );
$freshWindow();
check( 'with nothing ever refused there is no record', \Nino\Mail::failure( $appData ) === null );

check( 'a delivered mail with no record writes none', \Nino\Mail::send( $appData, 'ok@example.org', 'a', 'b', '' ) === true
	&& is_file( \Nino\Filesystem::path( $appData, $statusPath ) ) === false && ( $appData['./nino/mail/results'] ?? null ) === [ true ] );

$freshWindow();
check( 'a refused send answers false and is on record: since, last, count 1', \Nino\Mail::send( $appData, 'refused@example.org', 'a', 'b', '' ) === false
	&& ( $appData['./nino/mail/results'] ?? null ) === [ false ] );
$record = \Nino\Mail::failure( $appData );
check( '...the two dates are minutes, equal for the first failure', $record !== null && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $record['since'] ) === 1
	&& $record['since'] === $record['last'] && $record['count'] === 1 );
check( '...and holds no address, no subject and nothing a transport said', str_contains( (string) file_get_contents( \Nino\Filesystem::path( $appData, $statusPath ) ), 'example.org' ) === false );

// since stays where the failing began, last moves, count counts calls
\Nino\Filesystem::putFileContent( $appData, $statusPath, [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 1 ] );
$freshWindow();
$minuteBefore = date( 'Y-m-d H:i' );
\Nino\Mail::send( $appData, 'refused@example.org', 'a', 'b', '' );
$record = \Nino\Mail::failure( $appData );
check( 'a second failure keeps the first date and adds one to the count', $record !== null && $record['since'] === '2026-01-02 03:04' && $record['count'] === 2 && in_array( $record['last'], [ $minuteBefore, date( 'Y-m-d H:i' ) ], true ) );

// A call that sent nothing neither raises nor clears: the cap refused it, or
// there was nothing to send
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, [ '127.0.0.1' => [ 'tries' => 5, 'reset' => time() + 3600 ] ] );
unset( $appData['./nino/mail/ratelimited'] );
$appData['./nino/mail/results'] = [ true, true ];
check( 'a send the cap refused answers false and leaves the record as it was', \Nino\Mail::send( $appData, 'ok@example.org', 'a', 'b', '' ) === false
	&& ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 2 );
check( '...with no results left over from the call before', ( $appData['./nino/mail/results'] ?? null ) === [] );
$appData['./nino/mail/results'] = [ true ];
check( 'a batch the cap refused leaves it as it was too', \Nino\Mail::sendAll( $appData, [ [ 'to' => 'ok@example.org', 'subject' => 'a', 'body' => 'b', 'replyTo' => '' ] ] ) === false
	&& ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 2 && ( $appData['./nino/mail/results'] ?? null ) === [] );
$freshWindow();
$appData['./nino/mail/results'] = [ true ];
check( 'an empty batch is not an action: nothing cleared, results reset', \Nino\Mail::sendAll( $appData, [] ) === true
	&& ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 2 && ( $appData['./nino/mail/results'] ?? null ) === [] );

// The regression the per-call write exists for: the owner's mail refused and
// the confirmation behind it delivered is one failed call, not a failure
// wiped by the mail that went out
$pair = [
	[ 'to' => 'refused@example.org', 'subject' => 'a', 'body' => 'x', 'replyTo' => 'jo@example.org' ],
	[ 'to' => 'jo@example.org',      'subject' => 'b', 'body' => 'y', 'replyTo' => 'refused@example.org' ],
];
$freshWindow();
check( 'a batch whose first mail is refused and second delivered is false, one result per mail, in order', \Nino\Mail::sendAll( $appData, $pair ) === false
	&& ( $appData['./nino/mail/results'] ?? null ) === [ false, true ] );
check( '...and keeps the record, the count moved on', ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 3 );

$freshWindow();
check( 'a batch whose second mail is refused is the same failure', \Nino\Mail::sendAll( $appData, array_reverse( $pair ) ) === false
	&& ( $appData['./nino/mail/results'] ?? null ) === [ true, false ] && ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 4 );

$freshWindow();
check( 'a batch in which every mail is delivered clears the record', \Nino\Mail::sendAll( $appData, [ $pair[1], $pair[1] ] ) === true
	&& ( $appData['./nino/mail/results'] ?? null ) === [ true, true ] && \Nino\Mail::failure( $appData ) === null );

\Nino\Filesystem::putFileContent( $appData, $statusPath, [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 1 ] );
$freshWindow();
check( 'so does one delivered mail', \Nino\Mail::send( $appData, 'ok@example.org', 'a', 'b', '' ) === true && \Nino\Mail::failure( $appData ) === null );

// A file that is not what this class writes is no record - and the next
// failure writes a new one over it
foreach( [
	'a string'				=> 'broken',
	'no dates'				=> [ 'count' => 2 ],
	'a date that is not one'	=> [ 'since' => 'yesterday', 'last' => '2026-01-02 03:04', 'count' => 1 ],
	'a count of nought'		=> [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 0 ],
	'a count that is text'	=> [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => '3' ],
] as $label => $content ) {
	\Nino\Filesystem::putFileContent( $appData, $statusPath, $content );
	check( 'a status file holding '. $label. ' is no record', \Nino\Mail::failure( $appData ) === null );
}
\Nino\Filesystem::putFileContent( $appData, $statusPath, [ 'since' => 'yesterday', 'count' => 'x' ] );
$freshWindow();
$minuteBefore = date( 'Y-m-d H:i' );
\Nino\Mail::send( $appData, 'refused@example.org', 'a', 'b', '' );
check( '...and the next failure starts a clean one', ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 1 && in_array( \Nino\Mail::failure( $appData )['since'] ?? '', [ $minuteBefore, date( 'Y-m-d H:i' ) ], true ) );

// A transport that takes no mail and leaves 'sent' alone falls through to
// mail(): not exercised here, the record rests on _deliver()'s answer alone
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/mail/ratelimited'], $appData['./nino/mail/results'] );
\Nino\Filesystem::putFileContent( $appData, $statusPath, [] );
$freshWindow();

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

/*	Regression: the sweep globbed '<dir>/<prefix>*<suffix>', and a directory is
	a path rather than a pattern. One project installed below a directory
	called "site[2]" - or any name carrying '[', ']', '*' or '?' - had the
	brackets read as a character class, so the pattern described a path that
	does not exist, glob() answered nothing and the sweep pruned nothing at
	all, for the life of that installation and without a word about it	*/
$bracketDir 	= $rotDir. '/site[2]';
mkdir( $bracketDir, 0777, true );
$bracketOld 	= $bracketDir. '/logs.2020-01.php';
$bracketFresh	= $bracketDir. '/logs.'. date( 'Y-m' ). '.php';
file_put_contents( $bracketOld, '<?php return [];' );
file_put_contents( $bracketFresh, '<?php return [];' );

\Nino\RotatingLog::prune( $bracketDir, 'logs.', 'Y-m', '.php', $monthlyCutoff );

check( 'a directory whose name carries glob metacharacters is swept like any other', is_file( $bracketOld ) === false );
check( '...and the fresh bucket in it is still kept', is_file( $bracketFresh ) === true );

/*	And a $dateFormat the sweep was never taught. 'Y-m' and 'Y-m-d' were the
	only two it understood - the date was always parsed as a full 'Y-m-d' - so
	every other format a caller might name parsed as nothing, matched nothing
	and deleted nothing, in the same silence	*/
$compactOld 	= $rotDir. '/report.20200101.txt';
$compactFresh	= $rotDir. '/report.'. date( 'Ymd' ). '.txt';
file_put_contents( $compactOld, 'x' );
file_put_contents( $compactFresh, 'x' );

\Nino\RotatingLog::prune( $rotDir, 'report.', 'Ymd', '.txt', $dailyCutoff );

check( 'a date format the kernel does not use itself deletes what it names', is_file( $compactOld ) === false );
check( '...and keeps what is inside the cutoff', is_file( $compactFresh ) === true );

// The anchoring the two kernel formats depend on, pinned where today's date
// cannot reach it: a bucket named for a month is the first of that month,
// never today's day-of-month in it - which would drift the boundary from day
// to day and roll a bucket into the next month entirely (parsing "...-02" on
// the 31st)
$anchorInside		= $rotDir. '/anchor.2020-01.php';
$anchorOutside	= $rotDir. '/anchor.2020-02.php';
file_put_contents( $anchorInside, 'x' );
file_put_contents( $anchorOutside, 'x' );

\Nino\RotatingLog::prune( $rotDir, 'anchor.', 'Y-m', '.php', new \DateTime( '2020-01-15 00:00:00' ) );

check( 'a monthly bucket is judged by the first of its month', is_file( $anchorInside ) === false );
check( '...and one whose month starts after the cutoff is kept', is_file( $anchorOutside ) === true );

echo "\n";


// --- Http header composition / locale redirects ---------------------------

echo "Http::request/response - header composition, csp, locale redirects\n";

$appData['/nino/http/routes'] = [
	'GET://' 						=> [ 'uri' => '/home', 'body' => '' ],
	'GET://rechtliches'	=> [ 'uri' => '/legal', 'body' => '', 'locale' => 'de_DE' ],
	// statusCode mirrors a route like GET://_admin declaring its own status -
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

$malformedSessionAppData = [ './nino/uid' => $sandbox. '-malformed-locale-session' ];
\Nino\AppData::prepare( $malformedSessionAppData );
$malformedSessionAppData['./nino/filesystem/path'] = $sandbox;
$malformedSessionAppData['/nino/locales/native'] = 'en_US';
$malformedSessionAppData['/nino/locales/available'] = [ 'en_US', 'de_DE' ];
\Nino\Runtime::setSessionValue( $malformedSessionAppData, './nino/locales/current', [ 'de_DE' ] );
\Nino\Locales::init( $malformedSessionAppData );
check( 'Locales::init ignores a malformed non-string locale stored in the session', \Nino\Locales::getCurrentLocale( $malformedSessionAppData ) === 'en_US' );

// The default must not be written into the session - it is nobody's choice,
// and stored there it would outlive a later change of the native locale
check( 'resolving the default does not persist it as a visitor locale', \Nino\Runtime::getSessionValue( $nativeAppData, './nino/locales/current' ) === null );

/*	...and neither must the fallback a stale session locale lands on. A
	visitor who once picked a locale the project has since dropped had
	setCurrentLocale() called with it: useLocale() inside it answered the
	project's default, and setCurrentLocale() then wrote that default into
	their session - the one thing the comment above it forbids, done by
	init() itself. From then on init() read it back and let it win, so a
	project that changed its native locale never reached that visitor again	*/
$staleSessionAppData = [ './nino/uid' => $sandbox. '-stale-locale-session' ];
\Nino\AppData::prepare( $staleSessionAppData );
$staleSessionAppData['./nino/filesystem/path']	= $sandbox;
$staleSessionAppData['/nino/locales/native']			= 'en_US';
$staleSessionAppData['/nino/locales/available']	= [ 'en_US', 'de_DE' ];
\Nino\Runtime::setSessionValue( $staleSessionAppData, './nino/locales/current', 'fr_FR' );
\Nino\Locales::init( $staleSessionAppData );

check( 'a session locale the project no longer has falls back for this request', \Nino\Locales::getCurrentLocale( $staleSessionAppData ) === 'en_US' );
// Left as it was rather than corrected: it is not honoured while the
// project does not have that locale, and it is the visitor's own again if
// it comes back. What must not be in there is a default nobody chose
check( '...without the fallback being written into the visitor\'s session', \Nino\Runtime::getSessionValue( $staleSessionAppData, './nino/locales/current' ) === 'fr_FR' );

// And the ordinary case is untouched: a stored locale the project still has
// is what the request runs in, and stays where it is
$chosenSessionAppData = [ './nino/uid' => $sandbox. '-chosen-locale-session' ];
\Nino\AppData::prepare( $chosenSessionAppData );
$chosenSessionAppData['./nino/filesystem/path']		= $sandbox;
$chosenSessionAppData['/nino/locales/native']			= 'en_US';
$chosenSessionAppData['/nino/locales/available']	= [ 'en_US', 'de_DE' ];
\Nino\Runtime::setSessionValue( $chosenSessionAppData, './nino/locales/current', 'de_DE' );
\Nino\Locales::init( $chosenSessionAppData );

check( 'a session locale the project does have still wins over the native default', \Nino\Locales::getCurrentLocale( $chosenSessionAppData ) === 'de_DE'
	&& \Nino\Runtime::getSessionValue( $chosenSessionAppData, './nino/locales/current' ) === 'de_DE' );

/*	Runtime's session, which is started on demand rather than on every
	request: an anonymous GET of a page that renders no form and signs
	nobody in used to leave a session file and a PHPSESSID cookie behind
	for a session that never held one value. Measured with php -S against
	this kernel: a plain page answers with neither, the same page with a
	[csrf] in it answers with both, and a visitor who has one keeps the one
	they have. The suites build their appData without Runtime::init() (see
	harness.php), so there is nothing to start here and $_SESSION stays the
	ordinary array it is on the cli - which is what these assert	*/
check( 'startSession reports no session where init() never ran', \Nino\Runtime::startSession( $appData ) === false );

\Nino\Runtime::setSessionValue( $appData, './nino/test/value', 'stored' );
check( '...and a session value is still written and read back', \Nino\Runtime::getSessionValue( $appData, './nino/test/value' ) === 'stored' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/test/value' );
check( '...and unset again', \Nino\Runtime::getSessionValue( $appData, './nino/test/value', 'gone' ) === 'gone' );

// Reading never starts one either: a value nobody wrote is the default,
// not an empty session brought into being by asking for it
check( 'reading an unwritten key answers the default', \Nino\Runtime::getSessionValue( $appData, './nino/test/never-written', 'default' ) === 'default'
	&& session_status() !== PHP_SESSION_ACTIVE );

function fakeRequest( array &$appData, string $uri, string $method = 'GET', array $server = [] ): array {
	$request = array_merge( [ 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1' ], $server );
	\Nino\Http::request( $appData, $request );
	return $request;
}

/**
 *	The uri request() resolves for one server array, or the class name of
 *	whatever it threw instead. Every case below used to throw, and a suite
 *	that dies on the throw reports no failed check at all - it reports
 *	nothing
 */
function resolvedUri( array &$appData, array $server ): string {
	try {
		$request = $server;
		\Nino\Http::request( $appData, $request );
		return (string) ( $request['/nino/http/request']['uri'] ?? '' );
	} catch( \Throwable $e ) {
		return get_class( $e );
	}
}

$homeRequest = fakeRequest( $appData, '/' );
$seededCsp = $homeRequest['/nino/http/response']['header']['Content-Security-Policy'] ?? '';
check( 'the response header is seeded with the default csp', str_contains( $seededCsp, "default-src 'self'" ) === true );

/*	Whether a response may be stored is Nino's answer now. It used to be
	php's: the session was started on every request, and its cache limiter
	put Expires/Pragma/Cache-Control on every response as a side effect.
	A request that writes no session value starts none any more, so the
	same answer has to come from here or it would depend on whether the
	page happened to render a form	*/
check( 'the response header carries an explicit no-store', ( $homeRequest['/nino/http/response']['header']['Cache-Control'] ?? '' ) === 'no-store' );

// img-src '*' covers network schemes only, so a data: uri needs spelling out.
/*	Every custom property Nino.css reads without a fallback is one it declares
	itself. The base unit's theme.css declares the same names again for a
	project, but a page without that theme - or with an older copy of it -
	gets the browser's initial value where a name is missing, which for a
	font-family is whatever the parent had; --fontfamily-subtitle was that
	name, read by two rules and declared by theme.css alone	*/
$ninoCss = (string) @file_get_contents( __DIR__. '/../_nino/Nino.css' );
preg_match_all( '/var\(\s*(--[a-zA-Z0-9-]+)\s*\)/', $ninoCss, $consumedProperties );
preg_match_all( '/(--[a-zA-Z0-9-]+)\s*:/', $ninoCss, $declaredProperties );
$undeclaredProperties = array_values( array_diff( array_unique( $consumedProperties[1] ), $declaredProperties[1] ) );
check( 'every custom property Nino.css reads without a fallback is declared in Nino.css itself'. ( $undeclaredProperties === [] ? '' : ' - missing: '. implode( ', ', $undeclaredProperties ) ), $undeclaredProperties === [] );

/*	Every manual and every method a Nino.css comment sends its reader to is
	where the comment says. Two pointed at docs/design-system.md, which this
	repository never had, and the two shortcode chapters at _nino/Nino.php for
	methods that live in their modules	*/
preg_match_all( '#\bdocs/[\w./-]+\.md\b#', $ninoCss, $cssManuals );
preg_match_all( '#\b(\w+)::(\w+)\(\) in (_nino/[\w./-]+\.php)#', $ninoCss, $cssMethods, PREG_SET_ORDER );
$cssPointers = array_values( array_filter( array_unique( $cssManuals[0] ), static fn( string $manual ): bool => is_file( __DIR__. '/../'. $manual ) === false ) );
foreach( $cssMethods as [ , $cssClass, $cssMethod, $cssFile ] )
	if( preg_match( '/\bclass\s+'. preg_quote( $cssClass, '/' ). '\b.*\bfunction\s+'. preg_quote( $cssMethod, '/' ). '\s*\(/s', (string) @file_get_contents( __DIR__. '/../'. $cssFile ) ) !== 1 )
		$cssPointers[] = $cssClass. '::'. $cssMethod. '() in '. $cssFile;
check( 'every manual and every method a Nino.css comment points to is where it says'. ( $cssPointers === [] ? '' : ' - '. implode( ', ', $cssPointers ) ), $cssPointers === [] && $cssMethods !== [] );

// Nino.css uses one for .nino-atf-arrowdown, ie. the framework's own default
// policy used to block the framework's own icon
check( 'the default csp allows data: images', str_contains( $seededCsp, 'img-src * data:' ) === true );
check( '...which is what Nino.css\'s own data: uri needs', str_contains(
	(string) @file_get_contents( __DIR__. '/../_nino/Nino.css' ), 'url("data:image/svg+xml'
) === false || str_contains( $seededCsp, 'data:' ) === true );

/*	A request that names neither a method nor a uri, and a uri that is
	nothing but the markers a path is cut at. Neither REQUEST_METHOD nor
	REQUEST_URI is guaranteed by the cgi environment - php-cgi under IIS
	composes no REQUEST_URI at all - and reading a key that is not there is
	an undefined-key warning, which is fatal in Nino. What followed was
	worse: cleanUri() cut the path with strtok(), which answers false for a
	string holding nothing but delimiters, so '' and '#' were a TypeError
	thrown inside request() before anything could answer. Measured against
	this kernel: all three ended in a TypeError, and '#frag' answered with
	'frag' - the fragment standing in for the path, because strtok() skips
	leading delimiters too	*/
check( 'a request naming no method and no uri is answered rather than thrown on', resolvedUri( $appData, [] ) === '/' );
$nameless = [];
try { \Nino\Http::request( $appData, $nameless ); } catch( \Throwable $e ) {}
check( '...with no method, which is what matches no route', ( $nameless['/nino/http/request']['method'] ?? 'unset' ) === '' );

check( 'an empty REQUEST_URI is the home uri', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '' ] ) === '/' );
check( '...and so is one that is only a fragment marker', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '#' ] ) === '/' );
check( 'a uri starting with a fragment marker does not route to the fragment', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '#frag' ] ) === '/' );
check( '...nor one starting with a query marker to the query', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '?a=b' ] ) === '/' );

// The cut itself is unchanged: the path is what precedes the first '?' or '#'
check( 'the path is still everything before the query and the fragment', fakeRequest( $appData, '/a/b?x=1#f' )['/nino/http/request']['uri'] === '/a/b' );
check( '...whichever of the two comes first', fakeRequest( $appData, '/a/b#f?x=1' )['/nino/http/request']['uri'] === '/a/b' );
check( '...and the query is still read off the raw uri', ( fakeRequest( $appData, '/a/b?x=1' )['/nino/http/request']['query']['x'] ?? '' ) === '1' );
check( '...and a trailing slash is still dropped', fakeRequest( $appData, '/a/b/' )['/nino/http/request']['uri'] === '/a/b' );

/*	Http::getClientIp() - who the visitor is behind a reverse proxy.

	REMOTE_ADDR is the proxy's own address for every single visitor there, so
	every per-ip rule in the site (Mail's send cap, the login cooldown - a
	session is not pinned to an address) counts the internet as one client. X-Forwarded-For
	carries the visitor - and is a request header anyone can write, so it is
	read only where the peer is a proxy this site was told about	*/
$proxyServer = [ $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null ];

$_SERVER['REMOTE_ADDR'] 					= '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] 	= '198.51.100.7';

$noProxyAppData = $appData;
$noProxyAppData['/nino/http/proxies'] = [];
check( 'with no proxy configured a forwarded address is ignored', \Nino\Http::getClientIp( $noProxyAppData ) === '203.0.113.9' );
check( '...and for a caller that passes no appData at all', \Nino\Http::getClientIp() === '203.0.113.9' );

$proxyAppData = $appData;
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.9' ];
check( 'a forwarded address is read where the peer is a configured proxy', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// What the rest of the request sees - the ip field is where every consumer
// reads it from
$proxiedRequest = fakeRequest( $proxyAppData, '/' );
check( 'the resolved address is what the request carries', ( $proxiedRequest['/nino/http/request']['ip'] ?? '' ) === '198.51.100.7' );

// Only the rightmost hop was written by our own proxy. Everything left of it
// is whatever the client sent, because a forwarding proxy appends rather than
// verifies - a client that writes its own chain cannot pick its address
$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.9.9.9, 198.51.100.7';
check( 'the rightmost hop wins over the ones a client put in front of it', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// Several of our own proxies in a row: the walk stops at the first address
// none of them is
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.9', '10.0.0.0/8' ];
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.5, 10.0.0.6';
check( 'a chain of trusted proxies is walked back to the client', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// A cidr range is its first bits, not its text: the neighbouring network is
// not in 10.0.0.0/8 however similar it reads
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 11.0.0.5';
check( 'an address outside the configured range is the client itself', \Nino\Http::getClientIp( $proxyAppData ) === '11.0.0.5' );

// ...and a prefix that ends inside a byte is honoured to the bit
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.0/26' ];
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
check( 'a peer inside a part-byte cidr range is trusted', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.70';
check( '...and one past its end is not', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.70' );

// A v6 range never matches a v4 address, and the other way round: the packed
// forms differ in length before a single bit is compared
$proxyAppData['/nino/http/proxies'] = [ '2001:db8::/32' ];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
check( 'a v4 peer is not trusted by a v6 range', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.9' );

// The same v6 address written two ways is one address, so the list is
// compared packed rather than as text
$proxyAppData['/nino/http/proxies'] = [ '2001:0db8:0000:0000:0000:0000:0000:0009' ];
$_SERVER['REMOTE_ADDR'] = '2001:db8::9';
check( 'a v6 peer is recognized whatever notation the list uses', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// A port some proxies append, and the spacing the header is written with
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.9', '10.0.0.0/8' ];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '  198.51.100.7:53238 ,10.0.0.5';
check( 'a port on a forwarded address is dropped', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '[2001:db8::7]:53238, 10.0.0.5';
check( '...including the bracketed v6 form a port needs', \Nino\Http::getClientIp( $proxyAppData ) === '2001:db8::7' );

// Anything that is not an address is dropped rather than returned: a hop
// cannot smuggle in a host name, a range, or a rate-limit key of its choosing
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, 10.0.0.5';
check( 'a hop that is not an ip address is skipped', \Nino\Http::getClientIp( $proxyAppData ) === '10.0.0.5' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
check( 'an empty header leaves the peer as the client', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.9' );

// The list is what decides, not the header: an unlisted peer is the client
// even where the header looks exactly as a proxy would have written it
$_SERVER['REMOTE_ADDR'] = '192.0.2.50';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
check( 'a peer that is no listed proxy is the client, header or not', \Nino\Http::getClientIp( $proxyAppData ) === '192.0.2.50' );

// A typo in the list matches nothing rather than everything - the failure
// mode of trusting a header is worse than the one of ignoring it
$proxyAppData['/nino/http/proxies'] = [ 'cloudflare', '', '10.0.0.0/999', 42 ];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
check( 'an unusable list entry trusts nothing', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.9' );

[ $_SERVER['REMOTE_ADDR'], $forwardedBefore ] = $proxyServer;
if( $forwardedBefore === null )
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
else
	$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwardedBefore;

$basicHeader = 'Basic '. base64_encode( 'editor@example.com:secret:with-colons' );
$basicRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [ 'HTTP_AUTHORIZATION' => $basicHeader ] );
check( 'Http::request keeps a CGI/FastCGI Authorization header', ( $basicRequest['/nino/http/request']['header']['Authorization'] ?? '' ) === $basicHeader );
check( 'Http::request decodes Basic credentials when PHP_AUTH_* is absent',
	( $basicRequest['/nino/http/request']['user'] ?? '' ) === 'editor@example.com'
	&& ( $basicRequest['/nino/http/request']['pw'] ?? '' ) === 'secret:with-colons' );

$nativeBasicRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [
	'PHP_AUTH_USER' 			=> 'native@example.com',
	'PHP_AUTH_PW' 				=> 'native-secret',
	'HTTP_AUTHORIZATION'	=> $basicHeader,
] );
check( 'PHP_AUTH_* stays authoritative when the SAPI already parsed Basic auth',
	( $nativeBasicRequest['/nino/http/request']['user'] ?? '' ) === 'native@example.com'
	&& ( $nativeBasicRequest['/nino/http/request']['pw'] ?? '' ) === 'native-secret' );

$malformedBasicRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [ 'HTTP_AUTHORIZATION' => 'Basic not!base64' ] );
check( 'a malformed Basic header produces no partial credentials',
	( $malformedBasicRequest['/nino/http/request']['user'] ?? '' ) === ''
	&& ( $malformedBasicRequest['/nino/http/request']['pw'] ?? '' ) === '' );

/*	Where Apache hands a cgi/cgi-fcgi script no Authorization header at all -
	an Apache older than CGIPassAuth (2.4.13), or a php-cgi wrapper the
	directive does not cover - the .htaccess copies it into an environment
	variable instead, and Apache prefixes a variable set during an internal
	redirect with REDIRECT_, once per redirect. The login used to see nothing
	on exactly the hosts the documented fallback was written for */
$redirectedBasicRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [ 'REDIRECT_HTTP_AUTHORIZATION' => $basicHeader ] );
check( 'Basic credentials survive the REDIRECT_ prefix Apache\'s rewrite fallback produces',
	( $redirectedBasicRequest['/nino/http/request']['user'] ?? '' ) === 'editor@example.com'
	&& ( $redirectedBasicRequest['/nino/http/request']['pw'] ?? '' ) === 'secret:with-colons' );

$nestedBasicRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [ 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION' => $basicHeader ] );
check( '...and the second prefix a nested redirect adds',
	( $nestedBasicRequest['/nino/http/request']['user'] ?? '' ) === 'editor@example.com' );

// A request header lands in $_SERVER as HTTP_<NAME>, so the one name a client
// could try reads as HTTP_REDIRECT_HTTP_AUTHORIZATION and matches nothing
$forgedBasicRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [ 'HTTP_REDIRECT_HTTP_AUTHORIZATION' => $basicHeader ] );
check( 'a client cannot reach that path with a header of its own',
	( $forgedBasicRequest['/nino/http/request']['user'] ?? '' ) === ''
	&& ( $forgedBasicRequest['/nino/http/request']['pw'] ?? '' ) === '' );

$emptyRedirectRequest = fakeRequest( $appData, '/.nino/auth/login', 'POST', [ 'REDIRECT_HTTP_AUTHORIZATION' => '' ] );
check( 'an empty variant is skipped rather than read as a credential',
	( $emptyRedirectRequest['/nino/http/request']['user'] ?? '' ) === '' );

check( 'the shipped .htaccess carries both halves of the Apache workaround, and says whether it is applied',
	str_contains( $htaccess = (string) @file_get_contents( __DIR__. '/../.htaccess' ), 'CGIPassAuth On' ) === true
	&& str_contains( $htaccess, 'E=HTTP_AUTHORIZATION:%{HTTP:Authorization}' ) === true
	&& str_contains( $htaccess, 'SetEnv NINO_HTACCESS 1' ) === true );

/*	The two rules that make a fresh install answer at all on Apache. Without
	the first, "/" finds no index file on a host whose php configuration does
	not add one to Apache's list, falls through to the directory listing and is
	refused by the Options line - a homepage that 403s while /index.php answers
	with the project's own 404 page. Without the second, every other address is
	the server's 404 rather than a route. Both are the line router.php already
	draws for the development server */
check( 'the shipped .htaccess names the index file that answers "/"',
	str_contains( $htaccess, 'DirectoryIndex index.php' ) === true );

/*	\Nino\Images writes a webp wherever gd can, so the file has to say what
	that is: every mime.types of the last decade carries the type and the line
	then changes nothing, but an older host without it hands the image back as
	a download. Php's own development server needs no help here - it answers
	image/webp by itself, which is why router.php carries no counterpart	*/
check( '...and declares the type of the images the kernel writes',
	str_contains( $htaccess, 'AddType image/webp .webp' ) === true );
check( '...and forwards everything that is neither file nor directory to it',
	str_contains( $htaccess, 'RewriteCond %{REQUEST_FILENAME} !-f' ) === true
	&& str_contains( $htaccess, 'RewriteCond %{REQUEST_FILENAME} !-d' ) === true
	&& str_contains( $htaccess, 'RewriteRule . /index.php [L]' ) === true );

/*	...to an absolute url path, which is the whole of it. The relative form
	reads like the portable one - Apache resolves it against the directory the
	file sits in - and on some hosts the per-directory prefix it strips is not
	the one it puts back: the substitution resolves to nothing, Apache retries,
	and every address that needs forwarding 500s at ten internal redirects.
	Measured on an IONOS host, where this one character was the difference
	between a site and a stack of 500s. A subdirectory install edits the line
	(/shop/index.php); there is no form that is both absolute and
	location-independent, so the file takes the one that works everywhere	*/
check( '...as an absolute url path, not a relative one',
	str_contains( $htaccess, 'RewriteRule . index.php' ) === false );

/*	Four directories a checkout ships hold nothing a browser ever asks for:
	the project's classes, the installed features, the wizard's library and
	the suites. Each is denied twice - its own .htaccess for Apache, a rule
	in router.php for php's development server, which applies no .htaccess
	at all. tests/ had neither: over http, kernel-smoke.php booted the kernel
	against a sandbox and ran every check for whoever asked, seconds of cpu
	and a temp directory per request, the sandbox path printed back. Held as
	a pair: a directory denied on one server and open on the other is the
	shape the next omission takes	*/
$undenied = [];
$router 	= (string) file_get_contents( __DIR__. '/../router.php' );
foreach( [ 'app', 'features', 'tests', '_admin/install/library' ] as $shippedSource ) {
	if( str_contains( (string) @file_get_contents( __DIR__. '/../'. $shippedSource. '/.htaccess' ), 'Require all denied' ) === false )
		$undenied[] = $shippedSource. '/.htaccess';
	if( str_contains( $router, "'#^/". $shippedSource. "(?:/|$)#'" ) === false )
		$undenied[] = $shippedSource. ' in router.php';
}
check( 'every shipped directory a browser never needs is denied for Apache and for the development server alike'. ( $undenied === [] ? '' : ' - '. implode( ', ', $undenied ) ), $undenied === [] );

/*	...and the stop in front of it. mod_rewrite runs per-directory before the
	url is mapped, and where %{REQUEST_FILENAME} is not the mapped path there,
	!-f stays true for index.php itself: the catch-all rewrites its own result
	until Apache gives up at ten internal redirects with a 500. The symptom is
	that "/" and "/_admin/" answer (mod_dir resolves those without a rewrite)
	and every other address 500s. The guard has to come first to be one */
$guard		= strpos( $htaccess, 'RewriteRule ^index\.php$ - [L]' );
$catchAll	= strpos( $htaccess, 'RewriteRule . /index.php [L]' );

check( 'the front controller cannot rewrite its own result into a loop', $guard !== false );
check( '...because that stop stands ahead of the catch-all', $guard !== false && $catchAll !== false && $guard < $catchAll );

/*	A dot uri is a route here, not a file: /.nino/auth/login is the workbench
	login, /.form the contact form, /.newsletter and /.protected belong to
	features. All three deployments say the same sentence - deny a dot path
	only where it resolves to something on disk - and only one of them said
	it in a way Apache reads: the shipped .htaccess denied the pattern
	outright, and Apache stops its directory walk at the first component that
	does not exist and tests <FilesMatch> against that one. '/.nino/auth/login'
	therefore matched as '.nino'. Measured against Apache 2.4.58: every one of
	those routes answered 403 before php saw the request, while
	/gibt-es-nicht-12345 reached index.php - which is what made it look like a
	host problem rather than this file	*/
$denyStart = strpos( $htaccess, '<FilesMatch "^\\.">' );
$existsIf  = strpos( $htaccess, '<If "-f %{REQUEST_FILENAME} || -d %{REQUEST_FILENAME}">' );

check( 'the .htaccess denies a dotfile only where one exists, so a dot route still reaches the front controller',
	$existsIf !== false && $denyStart !== false && $existsIf < $denyStart
	&& strpos( $htaccess, '</If>', $denyStart ) !== false );

// The other two halves of the same sentence, so a change to one of the three
// stands out as the odd one
$router = (string) @file_get_contents( __DIR__. '/../router.php' );
$deploy = (string) @file_get_contents( __DIR__. '/../docs/deployment.md' );

check( '...and the development server draws the same line', str_contains( $router, 'is_file( __DIR__. $uri ) === true' ) === true );
check( '...and so does the nginx recipe', str_contains( $deploy, 'if ( -e $request_filename ) { return 403; }' ) === true );

$appData['./nino/jstext/nonce'] = base64_encode( random_bytes( 16 ) );
\Nino\Modules\Jstext::callbackResponse( $appData, $homeRequest );
$jstextCsp = $homeRequest['/nino/http/response']['header']['Content-Security-Policy'];
check( 'Jstext appends its script-src to the csp', str_contains( $jstextCsp, "script-src 'self' 'nonce-" ) === true );
check( '...once, and only where the policy has none of its own', substr_count( $jstextCsp, 'script-src' ) === 1 );

/*	A route may declare header fields of its own - that is what a route's
	'header' is for, and Http::response() merges them into the seeded policy
	before these callbacks run. A route that declares a script-src used to get
	a second one appended, and a repeated directive is not a merge: the first
	occurrence is the one a browser enforces and every later one is ignored.
	So the nonce sat in a directive nothing read, the inline jstext block was
	refused as an unlisted inline script, and Nino.content.getText() answered
	'' for every key on that page - silently, because the page renders and the
	policy is honoured, only the words are missing	*/
$ownPolicyRequest = [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'self' https://cdn.example" ] ] ];
\Nino\Modules\Jstext::callbackResponse( $appData, $ownPolicyRequest );
$ownPolicy = $ownPolicyRequest['/nino/http/response']['header']['Content-Security-Policy'];
check( 'a route with a script-src of its own gets the nonce in that one', substr_count( $ownPolicy, 'script-src' ) === 1
	&& str_contains( $ownPolicy, "script-src 'self' https://cdn.example 'nonce-" ) === true );
check( '...and keeps the rest of what it declared', str_starts_with( $ownPolicy, "default-src 'self'; " ) === true );

// 'none' is the one value that means the project decided against inline
// scripts. A nonce beside it would not merge with that decision but overturn
// it - 'none' is ignored the moment anything stands next to it
$noneRequest = [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'none'" ] ] ];
\Nino\Modules\Jstext::callbackResponse( $appData, $noneRequest );
check( "a script-src of 'none' is left as it is", $noneRequest['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'; script-src 'none'" );

// ...and an empty policy is still the one case that must not start from ''
$emptyRequest = [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => '' ] ] ];
\Nino\Modules\Jstext::callbackResponse( $appData, $emptyRequest );
check( 'an empty policy gets the directive and no stray separator', $emptyRequest['/nino/http/response']['header']['Content-Security-Policy'] === "script-src 'self' 'nonce-". $appData['./nino/jstext/nonce']. "'" );

/*	The nonce reaches the page twice - raw in the script tag, and json
	encoded in the block beside it - and json_encode() escapes a '/' as
	'\\/'. A base64 nonce carries one about a third of the time, and
	Modules\Cache::_stamp() re-stamped the raw one only, so a stored page
	kept the render-time nonce in its json for as long as the entry lived.
	Hex carries no character json touches	*/
$freshNonceData = $appData;
unset( $freshNonceData['./nino/jstext/nonce'] );
\Nino\Modules\Jstext::init( $freshNonceData );
$freshNonce = (string) ( $freshNonceData['./nino/jstext/nonce'] ?? '' );
check( 'the jstext nonce is 16 bytes of hex', preg_match( '/^[0-9a-f]{32}$/', $freshNonce ) === 1 );
check( '...so json_encode leaves it exactly as the page carries it', json_encode( $freshNonce ) === '"'. $freshNonce. '"' );
check( 'Jstext keeps the default-src while doing so', str_contains( $jstextCsp, "default-src 'self'" ) === true );
check( 'the composed csp does not start with a stray separator', str_starts_with( $jstextCsp, ';' ) === false );

// The header is composed before anything can end the request: Modules\
// Maintenance answers from priority 1 and ends the request there, so a
// maintenance page - which renders the site's own footer, and with it
// [jstext] - used to ship an inline script the policy then refused, because
// the policy naming its nonce was added at priority 5 and never ran
$jstextProbe = [];
\Nino\Modules\Jstext::init( $jstextProbe );
$jstextPrios = [];
foreach( ( $jstextProbe['./nino/callbacks']['/nino/http/response'] ?? [] ) as $prio => $callbacks )
	foreach( $callbacks as $callback )
		if( is_array( $callback ) === true && str_ends_with( (string) ( $callback[0] ?? '' ), 'Modules\\Jstext' ) === true )
			$jstextPrios[] = $prio;
check( 'the csp is composed ahead of everything that can end a request', $jstextPrios !== [] && max( $jstextPrios ) < 1 );

// What the inline block carries. It used to be every fill the site has -
// including '/project/mail/address/owner', the mailbox a contact form delivers to, and
// every address and legal line a project keeps in its text files - on every
// public page, while the scripts reading it only ever ask for two groups
\Nino\Html::addFills( $appData, [
	'[[/module/form/info/success]]'	=> 'Danke!',
	'[[/project/mail/address/owner]]'		=> 'post@example.com',
	'[[/project/company/contact/street]]' 		=> 'Musterweg 1',
	'[[/template/common/slider/prev]]'	=> 'Zurück',
	'[[/feature/newsletter/info/success]]'	=> 'Eingetragen',
	'[[/template/common/label/phone]]'	=> 'Telefon',
], '*' );
/** The block's own table, read back the way the browser reads it */
function jstextTable( array &$appData ): array {
	$block = \Nino\Modules\Jstext::doShortcode( $appData, [] );
	preg_match( '/NinoJstext=(.*);<\/script>/', $block, $found );
	return json_decode( $found[1] ?? '[]', true ) ?? [];
}

$jstextTable = jstextTable( $appData );
check( 'the inline block carries the words the shipped scripts ask for', ( $jstextTable['/module/form/info/success'] ?? null ) === 'Danke!' );
check( '...and not the mailbox a form delivers to, nor the rest of the site\'s text', isset( $jstextTable['/project/mail/address/owner'] ) === false
	&& isset( $jstextTable['/project/company/contact/street'] ) === false );
check( 'the three groups are the form\'s messages, the newsletter\'s and the slider\'s words - and a label like the phone\'s is none of them', ( $jstextTable['/template/common/slider/prev'] ?? null ) === 'Zurück'
	&& ( $jstextTable['/feature/newsletter/info/success'] ?? null ) === 'Eingetragen' && isset( $jstextTable['/template/common/label/phone'] ) === false );
check( 'the contact form\'s welcome text is no key any more, so nothing of it is published', isset( $jstextTable['/module/form/info/welcome'] ) === false
	&& str_contains( (string) file_get_contents( dirname( __DIR__ ). '/_nino/Nino/Modules/Form/install/text/en_US.php' ), '/form/info/welcome' ) === false );

// A project or a feature whose own script reads a fill says so
$appData[ \Nino\Modules\Jstext::KEYS ] = [ '/project/company/' ];
$jstextConfigured = jstextTable( $appData );
check( 'a project may publish a group of its own', ( $jstextConfigured['/project/company/contact/street'] ?? null ) === 'Musterweg 1'
	&& isset( $jstextConfigured['/project/mail/address/owner'] ) === false );
unset( $appData[ \Nino\Modules\Jstext::KEYS ] );

\Nino\Modules\Jstext::publish( $appData, [ '/project/mail/address/' ] );
check( '...and a module registers one for the request it is serving', isset( jstextTable( $appData )['/project/mail/address/owner'] ) === true );
unset( $appData['./nino/jstext/keys'] );

// The last-resort 404 fallback, ie. a project without its own /404 route.
// Written as '.uri' it merged a stray key in and left the response uri on the
// unmatched request path, so every [[/_nino/webpage[[/nino/http/response/uri]]/...]]
// fill on that page resolved against a webpage that does not exist
$noFallbackAppData = $appData;
unset( $noFallbackAppData['/nino/http/routes']['GET://404'] );
$unmatchedRequest = fakeRequest( $noFallbackAppData, '/no-such-page-xyz' );
\Nino\Http::response( $noFallbackAppData, $unmatchedRequest );
check( 'a project without a /404 route still answers 404', $unmatchedRequest['/nino/http/response']['statusCode'] === 404 );
check( '...and points the response uri at /404 rather than the unmatched path', ( $unmatchedRequest['/nino/http/response']['uri'] ?? '' ) === '/404' );
check( '...leaving no stray \'.uri\' key behind', isset( $unmatchedRequest['/nino/http/response']['.uri'] ) === false );

// A route entry without a 'uri' is a hand-edited config.php away, and an
// "Undefined array key" here is an engine-raised level, ie. fatal per
// Runtime::handleError() - one config typo used to 500 every locale switch
// Prepended, so the loop has to walk past it before reaching the match it
// is looking for. The return value alone proves nothing here: these tests
// call the kernel directly and never install Runtime's error handler, so the
// "Undefined array key" this used to raise is a warning the run survives -
// while in a real request that same level is fatal. The raised level is
// therefore what gets asserted.
$uriLessAppData = $appData;
$uriLessAppData['/nino/http/routes'] = [ 'GET://broken-entry' => [ 'body' => 'no uri key here' ] ] + $appData['/nino/http/routes'];

$routeWarnings = [];
set_error_handler( function( int $level, string $message ) use ( &$routeWarnings ): bool {
	$routeWarnings[] = $message;
	return true;
} );
$foundGerman  = \Nino\Http::findRouteUri( $uriLessAppData, '/legal', 'de_DE' );
$foundNothing = \Nino\Http::findRouteUri( $uriLessAppData, '/nowhere', 'de_DE' );
restore_error_handler();

check( 'findRouteUri() raises nothing on a route entry without a uri', $routeWarnings === [] );
check( '...walks past it to the route that does match', $foundGerman === 'GET://rechtliches' );
check( '...and still returns null for a response uri no route renders', $foundNothing === null );

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

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$arrayLocaleRequest = fakeRequest( $appData, '/legal?/_nino/locales/current[]=de_DE' );
\Nino\Http::response( $appData, $arrayLocaleRequest );
check( 'an array-shaped locale query is ignored instead of causing a TypeError', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US' && $arrayLocaleRequest['/nino/http/response']['statusCode'] === 201 );

$arrayPickerRequest = fakeRequest( $appData, '/legal?/_nino/localepicker/current[]=de_DE' );
\Nino\Http::response( $appData, $arrayPickerRequest );
\Nino\Modules\Localepicker::callbackResponse( $appData, $arrayPickerRequest );
check( 'the localepicker also ignores an array-shaped locale query', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US' && $arrayPickerRequest['/nino/http/response']['statusCode'] === 201 );

// A language is named by the fill after its code, /_nino/locale/<code>/name, which the
// module's install unit delivers for the two languages the wizard offers and the
// Language panel for one it adds
\Nino\Html::addFills( $appData, [ '[[/_nino/locale/de_DE/name]]' => 'Deutsch', '[[/_nino/locale/en_US/name]]' => 'English (US)' ], '*' );
$pickerHtml = \Nino\Html::renderHtml( $appData, \Nino\Modules\Localepicker::doShortcode( $appData, [] ) );
check( 'the localepicker names each language from /_nino/locale/<code>/name', str_contains( $pickerHtml, '>Deutsch</a>' ) === true && str_contains( $pickerHtml, '>English (US)</a>' ) === true
	&& str_contains( $pickerHtml, '/_nino/locale/' ) === false );

/*	The two switches are one method under two keys now. Modules\Localepicker's
	callback used to be a verbatim copy of the kernel's - every line and every
	comment, differing in the query key alone - which is two places to fix
	whenever one of them turns out to be wrong	*/
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$ownKeyRequest = fakeRequest( $appData, '/legal?/_project/language=de_DE' );
\Nino\Http::response( $appData, $ownKeyRequest );
\Nino\Locales::switchFromQuery( $appData, $ownKeyRequest, '/_project/language' );
check( 'the locale switch is one method taking the query key, so a project can answer under its own', $ownKeyRequest['/nino/http/response']['statusCode'] === 302
	&& ( $ownKeyRequest['/nino/http/response']['header']['Location'] ?? '' ) === '/rechtliches'
	&& \Nino\Locales::getCurrentLocale( $appData ) === 'de_DE' );
check( '...and the module carries no copy of it any more', str_contains( (string) file_get_contents( dirname( __DIR__ ). '/_nino/Nino/Modules/Localepicker/Localepicker.php' ), 'findRouteUri' ) === false );
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );

$queryParser = new ReflectionMethod( '\Nino\Http', '_getRequestQueryVarsPart' );
$queryParser->setAccessible( true );
check( 'a URL parse failure produces an empty query array', $queryParser->invoke( null, 'http://[' ) === [] );
check( 'requestRoute rejects an empty URI without entering its parent walk', \Nino\Http::requestRoute( $appData, '', 'GET' ) === null );
check( 'requestRoute rejects a relative URI without entering its parent walk', \Nino\Http::requestRoute( $appData, 'relative/path', 'GET' ) === null );

/*	The signed-in account's mail address, the one fill \Nino\request()
	registers that carries something a person typed. It is built here through
	that call rather than by hand, and read back through the markup the
	workbench's rail actually uses	*/
$hostileMail = '"<script>alert(1)</script>[x]"@example.com';
check( 'php calls an address carrying a script element and a bracket valid, so the FILTER_VALIDATE_EMAIL every account panel runs stores one', filter_var( $hostileMail, FILTER_VALIDATE_EMAIL ) !== false );

\Nino\Auth::insertUser( $appData, $hostileMail, 'correct horse battery staple' );
\Nino\Auth::loginUser( $appData, $hostileMail, 'correct horse battery staple' );
\Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1' ] );

// The fills a request registers are the ones the kernel names as its runtime
// fills, and the names are what the Text panel's scan reads - so a fill
// added to one and not the other cannot happen: both are the same two lists
$registeredFills = array_keys( $appData['./nino/html/fills']['*'] ?? [] );
$namedFills = method_exists( '\\Nino\\Html', 'runtimeFillKeys' ) === true ? array_map( fn( string $key ): string => '[['. $key. ']]', \Nino\Html::runtimeFillKeys( $appData ) ) : [];
check( 'every fill the kernel names as a runtime fill is registered by a request, the clean uri included', $namedFills !== [] && in_array( '[[/nino/http/response/uri/clean]]', $namedFills, true ) === true && array_diff( $namedFills, $registeredFills ) === [] );
check( '...and the year is one of them, under /nino/ like the rest: a runtime fill is never a text key', in_array( '[[/nino/date/year]]', $namedFills, true ) === true
	&& array_filter( $namedFills, static fn( string $fill ): bool => str_starts_with( $fill, '[[/nino/' ) === false ) === [] );

$railSpan = \Nino\Html::renderHtml( $appData, '<span id="admin-user-email">[[/nino/auth/user]]</span>' );
check( 'the address reaches the page as the text it is, never as markup', str_contains( $railSpan, '<script' ) === false && str_contains( $railSpan, '&lt;script&gt;' ) === true );
check( '...brackets neutralized as well, so the shortcode pass over the finished document cannot read one as syntax', str_contains( $railSpan, '[x]' ) === false && str_contains( $railSpan, '&#91;x&#93;' ) === true );
check( 'and the shell draws the address through exactly that fill', str_contains( (string) @file_get_contents( dirname( __DIR__ ). '/_admin/templates/page-index.tpl' ), '<span id="admin-user-email">[[/nino/auth/user]]</span>' ) === true );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, $hostileMail );

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
	'/_nino/webpage/home/name' 			=> 'Home',
	'/_nino/webpage/contact/name' 	=> 'Contact',
	'/_nino/webpage/legal/name' 		=> 'Legal',
	'/_nino/webpage/top/name' 			=> 'Top',
], 'en_US' );
\Nino\Html::addFills( $appData, [
	'/_nino/webpage/legal-de/name' 	=> 'Rechtliches',
	'/_nino/webpage/home/name' 			=> 'Start',
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

// A page's name is editor content, and a line is '<uri>:<title>'. Splitting
// on every ':' made the second colon of a perfectly ordinary name ("Angebot:
// Sommer") end the title and open the third field, which is written into the
// <a> tag as attributes - a name typed in the Text panel deciding what the
// markup says
\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => 'Angebot: Sommer" onmouseover="alert(1)' ], 'en_US' );
$navColon = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'a colon in a page name stays part of the name', str_contains( $navColon, 'Angebot: Sommer' ) === true );
check( '...and none of it reaches the tag', str_contains( $navColon, '" onmouseover="' ) === false
	&& str_contains( $navColon, '&quot; onmouseover=&quot;' ) === true );

// ...and the name itself is text, not markup - the same rule every other
// place an editor's words reach a page follows
\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => '<script>alert(1)</script>' ], 'en_US' );
$navMarkup = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'markup in a page name is drawn as text', str_contains( $navMarkup, '&lt;script&gt;' ) === true
	&& str_contains( $navMarkup, '<script>alert(1)' ) === false );

// ...and a shortcode's result is rendered again, fills and shortcodes
// included, so a '[' in a name has to be an entity by the time it leaves
// here - the pass that follows would otherwise fill it
\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => 'Angebot [[/_nino/webpage/home/name]] [navigation nav="main"]' ], 'en_US' );
$navBracket = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'a bracket in a page name is an entity, so the render pass after the shortcode cannot fill it',
	str_contains( $navBracket, 'Angebot &#91;&#91;/_nino/webpage/home/name&#93;&#93; &#91;navigation nav=&quot;main&quot;&#93;' ) === true
	&& str_contains( $navBracket, '[[/_nino/webpage/home/name]]' ) === false );

// A hand-written line is the page author's own - three fields, the third of
// them attributes for the tag, and written as typed. That is what it has
// always been, and what a template that uses it keeps
$navAttributes = \Nino\Modules\Navigation::doShortcode( $appData, [ 'content' => '/a:A: target="_blank"' ] );
check( 'a hand-written line still carries its attributes in the third field', str_contains( $navAttributes, '>A<' ) === true
	&& str_contains( $navAttributes, 'target="_blank"' ) === true );
check( '...and its markup is still the author\'s own', str_contains( \Nino\Modules\Navigation::doShortcode( $appData, [ 'content' => '/a:<b>A</b>' ] ), '<b>A</b>' ) === true );

\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => 'Top' ], 'en_US' );

// A route that exists only at runtime (a feature's /blog) has no entry in
// config.php to carry 'navs'. Its memberships are '/nino/html/navroutes', by the
// page's Element-URI, read for a live route and merged with the route's own, which
// wins for the same menu
\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => 'Blog' ], 'en_US' );
$appData['/nino/http/routes']['GET://blog'] = [ 'uri' => '/blog', 'body' => '' ];
$appData['/nino/html/navroutes'] = [
	'/blog' 		=> [ 'main' => 3 ],
	'/top' 			=> [ 'main' => 9, 'footer' => 1 ],
];
check( 'a runtime route stands in the menu at its priority, in order with the persisted members', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/top:Top', '/blog:Blog', '/:Home', '/kontakt:Contact' ] );
check( 'the route\'s own membership wins for the same menu, the key adds the others', \Nino\Modules\Navigation::routeLines( $appData, 'footer' ) === [ '/top:Top', '/:Home', '/impressum:Legal' ] );
check( 'a menu nobody is in through the key renders as before', \Nino\Modules\Navigation::routeLines( $appData, 'nope' ) === [] );

$appData['/nino/html/navroutes']['/gone'] = [ 'main' => 1 ];
\Nino\Html::addFills( $appData, [ '/_nino/webpage/gone/name' => 'Gone' ], 'en_US' );
check( 'a membership of a route that is not live is skipped, though its name exists - the feature is off', in_array( '/gone:Gone', \Nino\Modules\Navigation::routeLines( $appData, 'main' ), true ) === false );

\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => '' ], 'en_US' );
check( 'a runtime route nobody named stays out, like any other', str_contains( implode( '', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) ), 'blog' ) === false );
\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => 'Blog' ], 'en_US' );

// Whatever else is written there is not a priority
$appData['/nino/html/navroutes'] = [
	'/blog' 			=> [ 'main' => 3 ],
	'/contact' 		=> [ 'footer' => '2', 'side' => 2.5, 'x' => [ 1 ], 'y' => null ],
	'/legal' 			=> 'main',
	'/intern' 		=> 7,
	'/.form' 			=> [ 'main' => 1 ],
];
check( 'malformed entries are skipped: no menu is changed by them, and none of them throws',
	\Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/top:Top', '/blog:Blog', '/:Home', '/kontakt:Contact' ]
	&& \Nino\Modules\Navigation::routeLines( $appData, 'footer' ) === [ '/:Home', '/impressum:Legal' ]
	&& \Nino\Modules\Navigation::routeLines( $appData, 'side' ) === [] );
check( '...and a POST route is no menu entry through the key either', str_contains( implode( '', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) ), '.form' ) === false );

unset( $appData['/nino/html/navroutes'] );
check( 'with no key at all nothing changes', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/top:Top', '/:Home', '/kontakt:Contact' ] );

// A page with a route per language - the imprint of Modules\Legal - is one page:
// the key is its Element-URI, every GET route with that uri is a member, the one
// of the current language is the entry, and a wildcard is never one
$appData['/nino/http/routes'] = [
	'GET://home' 				=> [ 'uri' => '/home', 'body' => '', 'navs' => [ 'legal' => 3 ] ],
	'GET://impressum' 	=> [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '', 'maintenance' => false ],
	'GET://imprint' 		=> [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '', 'maintenance' => false ],
	'GET://datenschutz' => [ 'uri' => '/legal/privacy', 'locale' => 'de_DE', 'body' => '' ],
	'GET://privacy' 		=> [ 'uri' => '/legal/privacy', 'locale' => 'en_US', 'body' => '' ],
	'GET://wildcard/*' 	=> [ 'uri' => '/legal/imprint', 'body' => '' ],
	'GET://shared' 			=> [ 'uri' => '/legal/shared', 'body' => '' ],
	'GET://fr-fr/shared' => [ 'uri' => '/legal/shared', 'locale' => 'fr_FR', 'body' => '' ],
];
$appData['/nino/html/navroutes'] = [
	'/legal/imprint' 	=> [ 'legal' => 1 ],
	'/legal/privacy' 	=> [ 'legal' => 2 ],
	'/legal/shared' 	=> [ 'legal' => 4, 'main' => 1 ],
	'/home' 					=> [ 'legal' => 9 ],
];
\Nino\Html::addFills( $appData, [ '/_nino/webpage/legal/imprint/name' => 'Imprint', '/_nino/webpage/legal/privacy/name' => 'Privacy', '/_nino/webpage/legal/shared/name' => 'Shared', '/_nino/webpage/home/name' => 'Home' ], 'en_US' );
\Nino\Html::addFills( $appData, [ '/_nino/webpage/legal/imprint/name' => 'Impressum', '/_nino/webpage/legal/privacy/name' => 'Datenschutz', '/_nino/webpage/legal/shared/name' => 'Geteilt', '/_nino/webpage/home/name' => 'Start' ], 'de_DE' );
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
check( 'every route of an Element-URI is a member, the one of the current language is the entry, in the order of the priorities',
	\Nino\Modules\Navigation::routeLines( $appData, 'legal' ) === [ '/imprint:Imprint', '/privacy:Privacy', '/home:Home', '/shared:Shared' ] );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( '...in each language its own path and name', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ) === [ '/impressum:Impressum', '/datenschutz:Datenschutz', '/home:Start', '/shared:Geteilt' ] );
check( 'a route without a language is the entry where no route of the current language has the Element-URI', in_array( '/shared:Geteilt', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), true ) === true
	&& in_array( '/fr-fr/shared:Geteilt', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), true ) === false );
check( '...and a route on a wildcard never is a member through the key', str_contains( implode( '', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ) ), 'wildcard' ) === false );
check( 'a persisted route\'s own membership goes before the one its Element-URI has', \Nino\Modules\Navigation::routeLines( $appData, 'legal' )[2] === '/home:Start' );
check( 'an Element-URI is in a menu once', count( array_filter( \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), static fn( string $line ): bool => str_ends_with( $line, ':Impressum' ) ) ) === 1 );
$appData['/nino/html/navroutes']['/legal/privacy'] = [ 'legal' => '2' ];
$appData['/nino/html/navroutes']['legal/imprint'] = [ 'legal' => 1 ];
check( 'an entry that is no whole number is no membership, and a key that is no Element-URI matches no route', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ) === [ '/impressum:Impressum', '/home:Start', '/shared:Geteilt' ] );
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
unset( $appData['/nino/html/navroutes'] );

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

/*	A body json_encode() refuses. false went into the body and echoed as the
	empty string: an empty 200 carrying a json content-type, which every
	_apiCall in _admin reads as a success with nothing in it - a blank panel
	and no message anywhere. The reachable cause is a single malformed utf-8
	byte, which the activity log's own test in admin-system-smoke.php drives
	end to end; that one is substituted now rather than refused	*/
$badUtf8Request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => [ 'files' => [ "Gru\xdfe.jpg" ] ] ] ];
$finalizeResponse->invokeArgs( null, [ &$badUtf8Request ] );
$badUtf8Body = json_decode( (string) $badUtf8Request['/nino/http/response']['body'], true );
check( 'a malformed utf-8 byte no longer costs the whole response', is_array( $badUtf8Body ) === true );
check( '...the byte is substituted and the rest of the value arrives', ( $badUtf8Body['files'][0] ?? '' ) === "Gru\u{FFFD}e.jpg" );
check( '...and the status stays the one the handler set', $badUtf8Request['/nino/http/response']['statusCode'] === 200 );

// What cannot be substituted - Inf/NaN, a resource, a recursion. This suite
// silences trigger_error() wholesale (see the handler at the top), so the one
// warning worth reading is captured around the call
$recordedWarnings = [];
set_error_handler( static function( int $level, string $message ) use ( &$recordedWarnings ): bool { $recordedWarnings[] = $message; return true; } );

$unencodableRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => [ 'n' => INF ] ] ];
$finalizeResponse->invokeArgs( null, [ &$unencodableRequest ] );

// A handler that already failed named its own status: a 403 is a 403 whatever
// became of its body
$failedBodyRequest = [ '/nino/http/response' => [ 'statusCode' => 403, 'header' => [], 'body' => [ 'n' => NAN ] ] ];
$finalizeResponse->invokeArgs( null, [ &$failedBodyRequest ] );

restore_error_handler();

$unencodableBody = json_decode( (string) $unencodableRequest['/nino/http/response']['body'], true );
check( 'a body that cannot be encoded at all is not answered as a 200', $unencodableRequest['/nino/http/response']['statusCode'] === 500 );
check( '...it says why, where the panel that asked will print it', str_contains( $unencodableBody['error'] ?? '', 'could not be encoded' ) === true );
check( '...and the kernel recorded it as well', count( array_filter( $recordedWarnings, static fn( string $w ): bool => str_contains( $w, 'could not be json-encoded' ) === true ) ) === 2 );
check( 'an unencodable body behind a 4xx keeps that status', $failedBodyRequest['/nino/http/response']['statusCode'] === 403 );

// A string body is not json and is never touched
$htmlRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<html>page</html>' ] ];
$finalizeResponse->invokeArgs( null, [ &$htmlRequest ] );
check( 'a string body passes through unencoded and untyped', $htmlRequest['/nino/http/response']['body'] === '<html>page</html>'
	&& isset( $htmlRequest['/nino/http/response']['header']['Content-Type'] ) === false );

/*	The request header allowlist names request headers. Location, the
	Content-Security-Policy and the other security headers are what Nino
	sends, never what it reads - one a client sends is dropped like any other
	name Nino does not know, by the public filter and through Http::request()
	alike	*/
$responseOnly = [ 'Location' => '/rechtliches', 'Content-Security-Policy' => 'default-src *', 'X-Frame-Options' => 'DENY', 'X-Content-Type-Options' => 'nosniff', 'Strict-Transport-Security' => 'max-age=1', 'Referrer-Policy' => 'no-referrer', 'Feature-Policy' => 'camera none', 'Permissions-Policy' => 'camera=()' ];
check( 'a response-only header name is dropped by filterHeaderFields()', \Nino\Http::filterHeaderFields( $responseOnly ) === [] );
$headerRequest = [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_LOCATION' => '/elsewhere', 'HTTP_CONTENT_SECURITY_POLICY' => 'default-src *', 'HTTP_HOST' => 'example.test', 'HTTP_REFERER' => 'https://example.test/', 'HTTP_ACCEPT_LANGUAGE' => 'de' ];
\Nino\Http::request( $appData, $headerRequest );
check( '...and a request that sends one arrives without it, with the request headers beside it under their own names', $headerRequest['/nino/http/request']['header'] === [ 'Host' => 'example.test', 'Referer' => 'https://example.test/', 'Accept-Language' => 'de' ] );

check( 'a bare [assets] shortcode without argument renders nothing instead of erroring', \Nino\Modules\Assets::doShortcode( $appData, [] ) === '' );

/*	The bundle keeps one address on disk and changes the one in the page: a
	regenerated bundle used to be served at the url a browser was already
	holding a copy of, so the page asked for /public/.cache/style.css and got
	the stylesheet from before the change. The way out was a hard reload nobody
	knows to do	*/
\Nino\Filesystem::putFileContent( $appData, '/assets/probe.custom.css', 'body{color:red}' );
\Nino\Html::addAsset( $appData, '/.cache/probe.css', '/assets/probe.custom.css' );

$bundleTag		= \Nino\Modules\Assets::doShortcode( $appData, [ 0 => '/.cache/probe.css' ] );
$bundleFirst	= ( preg_match( '/href="([^"]+)"/', $bundleTag, $bundleMatch ) === 1 ) ? $bundleMatch[1] : '';

check( 'the bundle is linked under its own url with a version on it', str_starts_with( $bundleFirst, \Nino\Filesystem::url( $appData, '/.cache/probe.css' ). '?v=' ) === true );
check( '...and the file on disk keeps the one name, so nothing piles up beside it', is_file( \Nino\Filesystem::path( $appData, '/.cache/probe.css' ) ) === true );

// Rendering it again changes nothing, because nothing changed
check( 'an unchanged bundle keeps its url, so the copy a browser holds stays good', \Nino\Modules\Assets::doShortcode( $appData, [ 0 => '/.cache/probe.css' ] ) === $bundleTag );

// ...and a changed source moves it, which is the whole point
sleep( 1 );
\Nino\Filesystem::putFileContent( $appData, '/assets/probe.custom.css', 'body{color:blue}' );
unset( $appData['./nino/filesystem/cache']['/assets/probe.custom.css'] );

$bundleSecond = ( preg_match( '/href="([^"]+)"/', \Nino\Modules\Assets::doShortcode( $appData, [ 0 => '/.cache/probe.css' ] ), $bundleMatch ) === 1 ) ? $bundleMatch[1] : '';
check( 'a rebuilt bundle is linked under a different url', $bundleSecond !== '' && $bundleSecond !== $bundleFirst );
check( '...at the same path, differing in the version alone', explode( '?', $bundleSecond )[0] === explode( '?', $bundleFirst )[0] );

echo "\n";


// --- Autoloading - project root, override, kernel guard and fallback ------

echo "Autoloading resolves project classes separately from the Nino kernel\n";

$projectAppRoot = __DIR__. '/../app';
$appDummyRoot = $projectAppRoot. '/KernelSmokeDummyModules';
$legacyDummyRoot = __DIR__. '/../_nino/KernelSmokeDummyModules';
$projectAppRootExisted = is_dir( $projectAppRoot );
$projectAppNinoRootExisted = is_dir( $projectAppRoot. '/Nino' );

$dummyModuleDir = $appDummyRoot. '/DummyCallModulesFix';
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
// the name (see the spl_autoload_register() call at the bottom of Nino.php).
$appData['/nino/modules'] = [ 'KernelSmokeDummyModules\DummyCallModulesFix' ];
\Nino\Modules::callModules( $appData, 'init' );
check( 'a configured project module loads from the default app/ root without a leading backslash', \KernelSmokeDummyModules\DummyCallModulesFix::$called === true );

unset( $appData['/nino/modules'] );
\Nino\Modules::callModules( $appData, 'init' );
check( 'callModules() does not warn/crash when /nino/modules is entirely unset', true );

// Modules::collect() - the read-only twin: every active module answers one
// question, the answers are merged in module order, and a module without
// the method contributes nothing rather than failing the call
$collectDummyDir = $appDummyRoot. '/DummyCollect';
mkdir( $collectDummyDir, 0777, true );
file_put_contents( $collectDummyDir. '/DummyCollect.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DummyCollect {
		public static function adminPanels( array &$appData ): array {
			return [ 'DummyCollectPanelA', 'DummyCollectPanelB' ];
		}
	}
}
PHP
);

$appData['/nino/modules'] = [ 'KernelSmokeDummyModules\DummyCallModulesFix', '\\KernelSmokeDummyModules\\DummyCollect', '\\KernelSmokeDummyModules\\DummyCollect' ];
check( 'collect() merges every module\'s answer in module order and skips modules without the method', \Nino\Modules::collect( $appData, 'adminPanels' ) === [ 'DummyCollectPanelA', 'DummyCollectPanelB', 'DummyCollectPanelA', 'DummyCollectPanelB' ] );
check( 'collect() answers an empty list for a question no module implements', \Nino\Modules::collect( $appData, 'somethingNobodyImplements' ) === [] );

unset( $appData['/nino/modules'] );
check( 'collect() answers an empty list when /nino/modules is entirely unset', \Nino\Modules::collect( $appData, 'adminPanels' ) === [] );

// A second project class is never listed in '/nino/modules': activation and
// class existence are independent, so a direct reference has to autoload it.
$directDummyDir = $appDummyRoot. '/DummyDirectAutoload';
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

check( 'a project class in app/ autoloads on a direct reference without going through callModules()', \KernelSmokeDummyModules\DummyDirectAutoload::ping() === 'pong' );

/*	The one namespace every root serves: Nino\Modules\* is looked for below
	_nino/ first - where the runtime modules Nino ships live, the optional
	ones (Form, Navigation, Localepicker, Design, Templates) among them -
	then below _admin/, then below features/, where a project installs a
	feature as one directory (see \Nino\Features), then below the
	application root.	*/
check( 'an optional kernel module below _nino/Nino/Modules/ autoloads through the Nino\Modules namespace', class_exists( '\Nino\Modules\Navigation\Admin' ) === true
	&& class_exists( '\Nino\Modules\Form' ) === true );
check( 'a feature below features/ autoloads through the same namespace, the directory standing for the Nino/Modules prefix', class_exists( '\Nino\Modules\Sample' ) === true
	&& class_exists( '\Nino\Modules\Sample\Admin' ) === true && class_exists( '\Nino\Modules\Helper' ) === true );

/*	A class outside Nino\ resolves against the application root and nowhere
	else. _nino/ is not a second place to look: that is what keeps the kernel
	replaceable wholesale, and what makes a project class accidentally dropped
	in there fail loudly instead of being found and then vanishing on the next
	update.	*/
$strayDir = $legacyDummyRoot. '/StraySource';
mkdir( $strayDir, 0777, true );
file_put_contents( $strayDir. '/StraySource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class StraySource {
		public static function source(): string {
			return 'kernel';
		}
	}
}
PHP
);
check( 'a project class below _nino/ does not autoload', class_exists( 'KernelSmokeDummyModules\StraySource' ) === false );

check( 'the autoload class-path allowlist rejects traversal segments', class_exists( 'KernelSmokeDummyModules\..\PrioritySource' ) === false );

// Nino\ is kernel-owned. Even if a project creates the matching app/ path,
// it must not be able to shadow a class shipped by the kernel.
$appKernelGuardDir = $projectAppRoot. '/Nino/KernelSmokeAutoloadGuard';
$kernelGuardDir = __DIR__. '/../_nino/Nino/KernelSmokeAutoloadGuard';
mkdir( $appKernelGuardDir, 0777, true );
mkdir( $kernelGuardDir, 0777, true );
file_put_contents( $appKernelGuardDir. '/KernelSmokeAutoloadGuard.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Nino {
	class KernelSmokeAutoloadGuard {
		public static function source(): string {
			return 'app';
		}
	}
}
PHP
);
file_put_contents( $kernelGuardDir. '/KernelSmokeAutoloadGuard.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Nino {
	class KernelSmokeAutoloadGuard {
		public static function source(): string {
			return 'kernel';
		}
	}
}
PHP
);
check( 'the Nino namespace resolves only inside _nino/ and cannot be shadowed from app/', \Nino\KernelSmokeAutoloadGuard::source() === 'kernel' );

// NINO_APP_DIR replaces the default app/ root when it is defined before the
// kernel is required - it is the only root a project class resolves against,
// so what app/ held before is no longer found.
$overrideAppRoot = $sandbox. '/custom-app';
$overrideDummyDir = $overrideAppRoot. '/KernelSmokeDummyModules/OverrideSource';
$defaultOverrideDir = $appDummyRoot. '/OverrideSource';
$defaultOnlyDir = $appDummyRoot. '/DefaultOnly';
mkdir( $overrideDummyDir, 0777, true );
mkdir( $defaultOverrideDir, 0777, true );
mkdir( $defaultOnlyDir, 0777, true );
file_put_contents( $overrideDummyDir. '/OverrideSource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class OverrideSource {
		public static function source(): string {
			return 'override';
		}
	}
}
PHP
);
file_put_contents( $defaultOverrideDir. '/OverrideSource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class OverrideSource {
		public static function source(): string {
			return 'default';
		}
	}
}
PHP
);
file_put_contents( $defaultOnlyDir. '/DefaultOnly.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DefaultOnly {}
}
PHP
);

$overrideResult = runIsolated(
	'echo \KernelSmokeDummyModules\OverrideSource::source(). "|".
		( class_exists( "KernelSmokeDummyModules\\DefaultOnly" ) === true ? "loaded" : "missing" );',
	'define( "NINO_APP_DIR", '. var_export( $overrideAppRoot, true ). ' );'
);
check( 'NINO_APP_DIR replaces the default app/ root outright', trim( $overrideResult['stdout'] ) === 'override|missing' && $overrideResult['exitCode'] === 0 );

\Nino\Filesystem::removeDir( $appDummyRoot );
\Nino\Filesystem::removeDir( $legacyDummyRoot );
\Nino\Filesystem::removeDir( $appKernelGuardDir );
\Nino\Filesystem::removeDir( $kernelGuardDir );
if( $projectAppNinoRootExisted === false )
	@rmdir( $projectAppRoot. '/Nino' );
if( $projectAppRootExisted === false )
	@rmdir( $projectAppRoot );

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
function runIsolated( string $body, string $beforeRequire = '' ): array {
	// php -r's code is implicitly already inside a php open/close pair,
	// unlike a regular script file - a literal '<?php' prefix here is a
	// syntax error
	$script = $beforeRequire. ' require '. var_export( __DIR__. '/../_nino/Nino.php', true ). '; '. $body;
	$descriptors = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
	// display_errors=stderr: with display_errors=On, which php.ini-development
	// and every Homebrew/Herd/MAMP php carry, the cli writes a fatal to stdout
	// and the check against what the script printed reads the message too
	$process = proc_open( [ PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script ], $descriptors, $pipes );
	$stdout = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exitCode = proc_close( $process );
	return [ 'stdout' => $stdout, 'exitCode' => $exitCode ];
}

$invalidContentDir = runIsolated( '
	define( "NINO_PRIVATE_DIR", "/definitely/not/a/nino-private-directory" );
	echo "before\n";
	\Nino\init();
	echo "after\n";
' );
check( 'an invalid NINO_PRIVATE_DIR fails before boot instead of falling back elsewhere', trim( $invalidContentDir['stdout'] ) === 'before' && $invalidContentDir['exitCode'] !== 0 );

/*	Booting with no project at all. The checkout itself is that case now -
	it ships no private/config.php - so these run against the real root.

	The site half stops, and stops *quietly*: Runtime::handleError() cannot
	know '/nino/error/display' before config.php has been read, so it
	defaults to not displaying and the visitor gets a bare 500. That is the
	deliberate answer. Somebody who stumbles onto an unfinished install must
	not be told that an installer exists, let alone where - the person doing
	the installing came to /_install on purpose.	*/
$noProject = runIsolated( '
	echo "before\n";
	\Nino\init();
	echo "after\n";
' );
/*	Judged by what stopped running, not by the exit code: this failure lands
	*after* Runtime::init() has installed Nino's own handler, which answers
	with a 500 header and a bare exit() - status 0. The NINO_PRIVATE_DIR case
	above fails one step earlier, before the handler exists, so php's own
	fatal handling gives it a non-zero code. Both stop; only one can say so
	in $?.	*/
check( 'a site with no config.php stops at boot', trim( $noProject['stdout'] ) === 'before' );
check( '...without naming the file, the path or the installer', preg_match( '/config\.php|_install|NINO_CONFIG_DIR/i', $noProject['stdout'] ) !== 1 );

// ...and the installer half boots on the defaults alone, which is the whole
// reason the flag exists: it is what has to write the first config.php
$installing = runIsolated( '
	$appData = \Nino\init( true );
	echo json_encode( [
		"modules"	=> $appData["/nino/modules"] ?? null,
		"routes"		=> $appData["/nino/http/routes"] ?? null,
		"ttl"			=> $appData["/nino/cache/ttl"] ?? null,
		"display"	=> $appData["/nino/error/display"] ?? null,
	] );
' );
$installingData = json_decode( $installing['stdout'], true ) ?? [];

check( 'the installer boots without one', $installing['exitCode'] === 0 && $installingData !== [] );
check( '...on the framework defaults', ( $installingData['ttl'] ?? null ) === 3600
	&& ( $installingData['display'] ?? null ) === false
	&& in_array( '\\Nino\\Modules\\Assets', $installingData['modules'] ?? [], true ) === true );
/*	Nothing the project has decided is present - the routes it does carry are
	the kernel's own, registered at runtime by Auth::init() rather than read
	from a file. Not one of them renders a template, which is what a page
	route is: on a fresh install every url falls through to a 404 the wizard
	has not installed either.	*/
$installingPages = array_filter( $installingData['routes'] ?? [], static fn( array $route ): bool => str_contains( (string) ( $route['body'] ?? '' ), '[template' ) );
check( '...carrying no page the project has not made yet', $installingPages === [] );

/*	And the half of the split that every existing project depends on: the
	defaults sit *under* config.php, so a file that omits a key gets the
	default and a file that carries one wins. That is what lets a config.php
	written before the defaults existed keep working unchanged - it simply
	overrides each of them with the same value it always had.	*/
$partialRoot = $sandbox. '/partial';
mkdir( $partialRoot. '/private', 0777, true );
file_put_contents( $partialRoot. '/private/config.php', '<?php return '. var_export( [
	'/nino/cache/ttl'			=> 99,
	'/nino/locales/available'	=> [ 'en_US', 'fr_FR' ],
], true ). ';' );

$partial = [ './nino/uid' => $partialRoot ];
\Nino\AppData::prepare( $partial );
$partial['./nino/filesystem/path'] 				= $partialRoot;
$partial['./nino/filesystem/configpath'] 	= $partialRoot. '/private';
$partial['./nino/filesystem/contentpath'] = $partialRoot. '/private';
$partial['./nino/filesystem/publicpath'] 	= $partialRoot. '/public';
\Nino\AppData::init( $partial );

check( 'a config.php that omits a key gets the framework default', ( $partial['/nino/auth/maxtries'] ?? null ) === 5
	&& ( $partial['/nino/error/log'] ?? null ) === true );
check( '...and a key it does carry overrides that default', ( $partial['/nino/cache/ttl'] ?? null ) === 99 );
// A list is replaced wholesale rather than merged - see AppData::_merge(). A
// project that drops a locale has to actually lose it
check( '...including a list, which is replaced rather than appended to', ( $partial['/nino/locales/available'] ?? null ) === [ 'en_US', 'fr_FR' ] );

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

// The one engine level that is not a bug in the code it is raised for: a
// deprecation says a future php will do this differently, not that this
// request is wrong. It used to stop the request like any other engine
// level, so a php minor upgrade took a site down - intermittently, since a
// compile-time deprecation only fires on the run that recompiles the file
$engineDeprecated = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "as the engine raises one", E_USER_DEPRECATED );
	// Not under @, and with every level reported: the handler bails out on a
	// level error_reporting() masks before it decides anything, so a masked
	// call would pass here whatever the handler did with a deprecation
	error_reporting( E_ALL );
	\Nino\Runtime::handleError( E_DEPRECATED, "a deprecation the engine raised", __FILE__, __LINE__ );
	echo "after\n";
' );
check( 'an engine-raised deprecation is recorded and the request carries on', trim( $engineDeprecated['stdout'] ) === "before\nafter" );

echo "\n";


// --- Runtime::startSession() - a session php refuses to start ----------------

echo "Runtime::startSession() - a session that cannot start says so\n";

/*	Regression: Runtime::init() starts the session a visitor already carries,
	and it runs before AppData::init() has read config.php - the cookie flags
	are fixed at session_start() time and cannot be retrofitted afterwards.
	php raises a warning of its own when session_start() fails, an engine level
	is fatal in here, and handleError() knew neither '/nino/error/log' nor
	'/nino/error/display' that early: an unusable session.save_path was a bare
	500 with nothing on the page, nothing on stderr and nothing in the log, on
	every request that carried a session cookie	*/
$sessionFailRoot = $sandbox. '/session-fail';
mkdir( $sessionFailRoot, 0777, true );
file_put_contents( $sessionFailRoot. '/config.php', '<?php return '. var_export( [ '/nino/modules' => [] ], true ). ';' );

// No output before \Nino\init() in any of these: on the cli headers_sent()
// is true the moment anything has been echoed, and startSession() answers
// false for that before it reaches php at all
$sessionFail = runIsolated(
	'$appData = \Nino\init();
	echo json_encode( [ "status" => session_status(), "csrf" => strlen( \Nino\Csrf::getToken( $appData ) ) ] );',
	'define( "NINO_PRIVATE_DIR", '. var_export( $sessionFailRoot, true ). ' );
	ini_set( "session.save_path", "/definitely/not/a/nino-session-directory" );
	$_COOKIE[ session_name() ] = "0123456789abcdef0123456789abcdef";'
);

$sessionFailData	= json_decode( $sessionFail['stdout'], true );
$sessionFailLog		= glob( $sessionFailRoot. '/data/logs.*.php' ) ?: [];
$sessionFailEntries	= ( $sessionFailLog !== [] ) ? (array) ( include $sessionFailLog[0] ) : [];
$sessionFailReasons	= implode( "\n", array_map( static fn( mixed $entry ): string => is_array( $entry ) === true ? (string) ( $entry['message'] ?? '' ) : '', $sessionFailEntries ) );

check( 'a session php refuses to start does not end the request', is_array( $sessionFailData ) === true && $sessionFail['exitCode'] === 0 );
check( '...and the request carries on without one, which fails a csrf check rather than passing it', ( $sessionFailData['status'] ?? null ) === PHP_SESSION_NONE
	&& ( $sessionFailData['csrf'] ?? 0 ) === 64 );
check( '...and the reason php gave is in the log, where a bare 500 left nothing at all', str_contains( $sessionFailReasons, 'startSession' ) === true
	&& str_contains( $sessionFailReasons, 'session_start()' ) === true );

// The other side of it: a session that can start still starts, cookie
// flags and all - the silencing above must not swallow the normal path
$sessionOkRoot = $sandbox. '/session-ok';
mkdir( $sessionOkRoot. '/sessions', 0777, true );
file_put_contents( $sessionOkRoot. '/config.php', '<?php return '. var_export( [ '/nino/modules' => [] ], true ). ';' );

$sessionOk = runIsolated(
	'$appData = \Nino\init();
	echo json_encode( [ "status" => session_status() ] );',
	'define( "NINO_PRIVATE_DIR", '. var_export( $sessionOkRoot, true ). ' );
	ini_set( "session.save_path", '. var_export( $sessionOkRoot. '/sessions', true ). ' );
	$_COOKIE[ session_name() ] = "0123456789abcdef0123456789abcdef";'
);

check( 'a session that can start is started as it always was', ( json_decode( $sessionOk['stdout'], true )['status'] ?? null ) === PHP_SESSION_ACTIVE );
check( '...and that boot logs nothing', glob( $sessionOkRoot. '/data/logs.*.php' ) === [] );

echo "\n";


// A month's log is one file, rewritten whole on every entry. A template with
// a broken shortcode raises one notice per view, so the file grew with the
// traffic - and every entry rewrote all of it under an exclusive lock, until
// the request that had to read a megabyte of php to add a line was the thing
// taking the site down
$logSandbox = $sandbox. '/logcap';
@mkdir( $logSandbox. '/data', 0777, true );
$logApp = [ './nino/uid' => 'logcap' ];
\Nino\AppData::prepare( $logApp );
$logApp['./nino/filesystem/path'] = $logSandbox;
$logApp['./nino/filesystem/contentpath'] = $logSandbox;
$logApp['./nino/filesystem/configpath'] = $logSandbox;
$logApp['/nino/error/log'] = true;
$logApp['/nino/error/display'] = false;

$record = new ReflectionMethod( '\Nino\Runtime', '_recordError' );
$record->setAccessible( true );

for( $i = 1; $i <= \Nino\Runtime::MAX_LOG_ENTRIES + 20; $i++ )
	$record->invokeArgs( null, [ &$logApp, [ 'type' => E_USER_NOTICE, 'message' => 'entry '. $i, 'file' => 'x.php', 'line' => $i ] ] );

$logged = \Nino\Filesystem::getFileContent( $logApp, '/data/logs.'. date( 'Y-m' ). '.php', [] );
check( 'a month\'s log stops growing at its cap', count( $logged ) === \Nino\Runtime::MAX_LOG_ENTRIES );
check( '...keeping the newest entries, which are the ones being read', ( end( $logged )['message'] ?? '' ) === 'entry '. ( \Nino\Runtime::MAX_LOG_ENTRIES + 20 )
	&& ( $logged[0]['message'] ?? '' ) === 'entry 21' );

echo "\n";


// --- Runtime::handleShutdown() - the fatals the error handler never sees ---

echo "Runtime::handleShutdown() - a fatal php never hands the handler still reaches the log\n";

/*	Regression: everything Nino offers for diagnosis hung off
	set_error_handler(), and php never calls that for the levels it raises
	and stops on. An exhausted memory limit, an expired max_execution_time
	and a compile-time fatal produced a bare 500 with an empty log and no
	effect from /nino/error/display - the failures that most need explaining
	were the ones that explained themselves least.

	Narrower than it sounds, and the reason these cases are the ones tested:
	on php 8.4 a parse error in a lazily autoloaded class and a call to a
	missing function are a ParseError and an Error, thrown objects
	handleException() has always caught. What is left is what is below.	*/
$shutdownBootstrap = static function( bool $log, bool $display, int $history = 0 ): string {
	return '
		$sandbox = sys_get_temp_dir(). "/nino-handleshutdown-". bin2hex( random_bytes( 4 ) );
		mkdir( $sandbox. "/private", 0755, true );
		$history = '. var_export( $history, true ). ';
		if( $history > 0 ) {
			mkdir( $sandbox. "/private/data", 0755, true );
			$seed = [];
			for( $i = 0; $i < $history; $i++ )
				$seed[] = [ "type" => E_WARNING, "message" => "an earlier warning, number ". $i. ", ". str_repeat( "y", 300 ), "file" => "/templates/page.tpl", "line" => $i, "date" => "2026-09-01 12:00:00" ];
			file_put_contents( $sandbox. "/private/data/logs.". date( "Y-m" ). ".php", "<?php return ". var_export( $seed, true ). ";" );
		}
		$appData = [ "./nino/uid" => $sandbox ];
		\Nino\AppData::prepare( $appData );
		$appData["./nino/filesystem/path"] = $sandbox;
		$appData["./nino/filesystem/configpath"] = $sandbox. "/private";
		$appData["./nino/filesystem/contentpath"] = $sandbox. "/private";
		$appData["./nino/filesystem/publicpath"] = $sandbox. "/public";
		$appData["/nino/error/log"] = '. var_export( $log, true ). ';
		$appData["/nino/error/display"] = '. var_export( $display, true ). ';
		\Nino\Runtime::init( $appData );
		// Announced after init(), not before it: output sent first means
		// headers_sent(), session_start() raises a plain E_WARNING about that,
		// and the handler ends the child on it long before its own fatal
		echo "sandbox:". $sandbox. "\n";
	';
};

/**
 *	Read back the month's log the child process wrote, and take its sandbox
 *	with it - the child announces the directory before it dies, which is the
 *	only way the parent can know where a randomly named sandbox went.
 *
 *	@param		array			$run			runIsolated() result
 *
 *	@return		array<int, array<string, mixed>>		The entries, or [] for no log at all
 */
function shutdownLogEntries( array $run ): array {

	if( preg_match( '/^sandbox:(.+)$/m', $run['stdout'], $match ) !== 1 )
		return [];

	$sandbox	= rtrim( $match[1] );
	$logs		= glob( $sandbox. '/private/data/logs.*.php' ) ?: [];
	$entries	= $logs === [] ? [] : ( include $logs[0] );

	\Nino\Filesystem::removeDir( $sandbox );

	return is_array( $entries ) === true ? $entries : [];
}

/*	The memory case, which is the one that does not work by simply asking
	error_get_last(): nothing is freed before a shutdown function runs, so
	_recordError() has to fit reading this month's log back in, appending
	to it and writing it out again into whatever the dying request left
	below the limit. What that is comes down to the block php just refused
	- it asked for one it could not have, and that much is still unused -
	which has nothing to do with what the write costs. handleShutdown()'s
	ini_set() is what decides whether either of these lands at all.	*/
$memoryFatal = runIsolated( $shutdownBootstrap( true, false ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
$memoryEntries = shutdownLogEntries( $memoryFatal );

check( 'an exhausted memory limit is written to the log', count( $memoryEntries ) === 1
	&& ( $memoryEntries[0]['type'] ?? null ) === E_ERROR
	&& str_contains( (string) ( $memoryEntries[0]['message'] ?? '' ), 'Allowed memory size' ) === true );
check( '...with the file and line it died on, and the date it happened', ( $memoryEntries[0]['line'] ?? null ) > 0
	&& ( $memoryEntries[0]['file'] ?? '' ) !== ''
	&& ( $memoryEntries[0]['date'] ?? '' ) !== '' );

/*	The same failure against a log that has been collecting all month, which
	is the ordinary case and the one with no doubt left in it: what the entry
	costs is the size of that file, read back in and written out again, while
	what the request left behind is the size of the block that did not fit.
	Measured, a fatal refused 132 KiB of hash table against a 600 KiB log
	wrote nothing at all - and left the month's log intact, so not even a
	damaged file said that an entry had gone missing.	*/
$memoryWithHistory = runIsolated( $shutdownBootstrap( true, false, 1200 ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
$historyEntries	= shutdownLogEntries( $memoryWithHistory );
$historyLast	= $historyEntries === [] ? [] : end( $historyEntries );

// A file seeded over the cap comes back under it on the next write (see
// MAX_LOG_ENTRIES), and the entry this run had to leave is the last one
check( '...and appended to a log that has been collecting all month, which costs more room than the request left', count( $historyEntries ) === \Nino\Runtime::MAX_LOG_ENTRIES
	&& ( $historyLast['type'] ?? null ) === E_ERROR
	&& str_contains( (string) ( $historyLast['message'] ?? '' ), 'Allowed memory size' ) === true );

// A compile-time fatal - the other kind php raises and stops on, and the one
// an autoloader can still walk into on a single route
$compileFatal = runIsolated( $shutdownBootstrap( true, false ). '
	eval( "class TwiceDeclared {}" );
	eval( "class TwiceDeclared {}" );
' );
$compileEntries = shutdownLogEntries( $compileFatal );

check( 'a compile-time fatal (a redeclared class) is written to the log', count( $compileEntries ) === 1
	&& ( $compileEntries[0]['type'] ?? null ) === E_COMPILE_ERROR );

// One entry per fatal, whatever is registered to report it: handleError()
// ends a fatal request with an exit(), and an exit() runs shutdown
// functions too, so both handlers are reached by the same failure. The
// outcome is what is pinned here - $_reported is what guarantees it, but
// not the only reason it holds today (see the property's own note)
$handledFatal = runIsolated( $shutdownBootstrap( true, false ). '
	trigger_error( "a real failure", E_USER_ERROR );
' );
$handledEntries = shutdownLogEntries( $handledFatal );

check( 'a fatal the error handler does see is logged once, not once per handler', count( $handledEntries ) === 1
	&& ( $handledEntries[0]['type'] ?? null ) === E_USER_ERROR );

// The same outcome where two handlers really are registered: a second
// Runtime::init() in one process adds a second handleShutdown(), and both
// are called with the same error_get_last() to read. $_reported is what
// makes one of them the reporter; without it the write the first one does
// happens to overwrite what the second would have found, which is luck
// rather than a rule
$twiceInitialised = runIsolated( $shutdownBootstrap( true, false ). '
	\Nino\Runtime::init( $appData );
	eval( "class TwiceDeclared {}" );
	eval( "class TwiceDeclared {}" );
' );
check( '...and once per fatal, not once per registered shutdown function', count( shutdownLogEntries( $twiceInitialised ) ) === 1 );

// The switch still governs: a shutdown that reports is one the site asked for
$memoryUnlogged = runIsolated( $shutdownBootstrap( false, false ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
check( '...and nothing is logged when /nino/error/log is off', shutdownLogEntries( $memoryUnlogged ) === [] );

// And /nino/error/display reaches the fatals it never reached before. No
// backtrace with it: the stack this died on is gone by the time a shutdown
// function runs, and error_get_last() is all php kept
$memoryShown = runIsolated( $shutdownBootstrap( true, true ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
check( 'a display-on install is shown the fatal instead of a bare 500', str_contains( $memoryShown['stdout'], 'Allowed memory size' ) === true
	&& count( shutdownLogEntries( $memoryShown ) ) === 1 );

// The quiet half: a request that ends normally must leave no trace at all,
// whatever warnings error_get_last() still holds from the way there
$noFatal = runIsolated( $shutdownBootstrap( true, false ). '
	@file_get_contents( $sandbox. "/definitely-not-there" );
	echo "done\n";
' );
check( 'a request that ends cleanly writes no entry, even after a suppressed warning', str_contains( $noFatal['stdout'], 'done' ) === true
	&& shutdownLogEntries( $noFatal ) === [] );

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
// and a never-served one are always separate paths.
$pathAppData = $appData;
$pathAppData['./nino/filesystem/path'] 				= '/srv/site';
$pathAppData['./nino/filesystem/configpath'] 	= '/srv/site/private';
$pathAppData['./nino/filesystem/contentpath'] = '/srv/site/private';
$pathAppData['./nino/filesystem/publicpath'] 	= '/srv/site/public';

foreach( [ '/images/hero.jpg', '/fonts/text.woff2', '/.cache/script.js' ] as $public )
	check( "$public stays on the public root", \Nino\Filesystem::path( $pathAppData, $public ) === '/srv/site/public'. $public );

/*	The one that reads like public content and is not. Nothing ever requests an
	/assets file - Modules\Assets reads them off disk and links only the bundle
	it writes into /.cache - so the sources sit with the templates and the text
	they belong to, and the webroot holds only what a browser actually loads.	*/
check( 'the asset sources are private, the bundle they build is not', \Nino\Filesystem::path( $pathAppData, '/assets/style.custom.css' ) === '/srv/site/private/assets/style.custom.css'
	&& \Nino\Filesystem::path( $pathAppData, '/.cache/style.css' ) === '/srv/site/public/.cache/style.css' );

check( 'tool code stays on the project root', \Nino\Filesystem::path( $pathAppData, '/_admin/x' ) === '/srv/site/_admin/x' );
check( 'the installed features resolve against Features::dir(), wherever that is', \Nino\Filesystem::path( $pathAppData, '/features/Sample/assets/x.css' ) === \Nino\Features::dir(). '/Sample/assets/x.css' && \Nino\Filesystem::path( $pathAppData, '/features' ) === \Nino\Features::dir() && \Nino\Filesystem::path( $pathAppData, '/featuresets/x' ) === '/srv/site/featuresets/x' );

foreach( \Nino\Filesystem::PRIVATE_DIRS as $private )
	check( "$private resolves against the private root", \Nino\Filesystem::path( $pathAppData, $private ) === '/srv/site/private'. $private );

check( 'a path under a private directory follows it', \Nino\Filesystem::path( $pathAppData, '/text/de_DE.php' ) === '/srv/site/private/text/de_DE.php' );

/*	The '..' rejection is documented as a layer under this whole class, and
	only the two content calls made it: every other door - the one that says
	whether a file is there, the one that builds a path for somebody else to
	read, the one that creates a directory, the one that takes a lock, the one
	that builds a url - resolved a traversal and handed it on. Every call site
	is still expected to validate its own input; this is what catches the one
	that forgot	*/
$traversalWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$traversalWarnings ): bool { $traversalWarnings[] = $message; return true; } );

$refused = [
	'path'				=> \Nino\Filesystem::path( $pathAppData, '/text/../../etc/passwd' ),
	'url'					=> \Nino\Filesystem::url( $pathAppData, '/images/../../etc/passwd' ),
	'fileExists'	=> \Nino\Filesystem::fileExists( $appData, '/text/../../etc/passwd' ),
	'lockFile'		=> \Nino\Filesystem::lockFile( $appData, '/data/../../escape.lock' ),
	'read'				=> \Nino\Filesystem::getFileContent( $appData, '/text/../../etc/passwd', 'the default' ),
	'write'				=> \Nino\Filesystem::putFileContent( $appData, '/data/../../escape.php', [ 'x' ] ),
	'mutate'			=> \Nino\Filesystem::mutate( $appData, '/data/../../escape.php', static fn( array $state ): array => [ 'x' ] ),
];

\Nino\Filesystem::forceDir( $appData, '/data/../../escape-dir' );
restore_error_handler();

/*	A nul byte is the same rule with a sharper edge. Every i/o call in the
	class carries an @ so a failure comes back as false - but @ does not
	suppress an exception, and mkdir()/fopen()/rename()/glob() throw a
	ValueError for a path containing one. So where a '..' answered false, a
	nul was an uncaught 500, for any caller whose own allowlist let one
	through	*/
$nulWarnings = [];
set_error_handler( static function( int $no, string $message ) use ( &$nulWarnings ): bool { $nulWarnings[] = $message; return true; } );

$nulRefused = [
	'path'				=> \Nino\Filesystem::path( $pathAppData, "/text/de_DE\0.php" ),
	'url'					=> \Nino\Filesystem::url( $pathAppData, "/images/x\0.png" ),
	'fileExists'	=> \Nino\Filesystem::fileExists( $appData, "/text/de_DE\0.php" ),
	'lockFile'		=> \Nino\Filesystem::lockFile( $appData, "/data/x\0.lock" ),
	'read'				=> \Nino\Filesystem::getFileContent( $appData, "/text/de_DE\0.php", 'the default' ),
	'write'				=> \Nino\Filesystem::putFileContent( $appData, "/data/nul\0.php", [ 'x' ] ),
	'mutate'			=> \Nino\Filesystem::mutate( $appData, "/data/nul\0.php", static fn( array $state ): array => [ 'x' ] ),
];

\Nino\Filesystem::forceDir( $appData, "/data/nul\0dir" );
restore_error_handler();

check( 'a nul byte is refused at every door too, rather than thrown out of', $nulRefused === [
	'path' => '', 'url' => '', 'fileExists' => false, 'lockFile' => false, 'read' => 'the default', 'write' => false, 'mutate' => false,
] );

check( 'every door of the filesystem refuses a traversal, not just the two that read and write content', $refused === [
	'path' => '', 'url' => '', 'fileExists' => false, 'lockFile' => false, 'read' => 'the default', 'write' => false, 'mutate' => false,
] );
check( '...and says so, so a call site that forgot to validate is findable', count( $traversalWarnings ) >= 3 );
check( '...and the directory it refused does not exist', is_dir( dirname( $sandbox, 2 ). '/escape-dir' ) === false );
check( 'a directory merely starting with a private name does not', \Nino\Filesystem::path( $pathAppData, '/textures/x.png' ) === '/srv/site/textures/x.png' );
check( 'everything under /private follows the private root', \Nino\Filesystem::path( $pathAppData, '/private/.auth/pw.php' ) === '/srv/site/private/.auth/pw.php' );
check( 'the old /content prefix is not a private-path alias', \Nino\Filesystem::path( $pathAppData, '/content/.auth/pw.php' ) === '/srv/site/content/.auth/pw.php' );
check( 'the typo /privat prefix is not a private-path alias', \Nino\Filesystem::path( $pathAppData, '/privat/.auth/pw.php' ) === '/srv/site/privat/.auth/pw.php' );

// Moving the private root moves every private path with it, and nothing else
$pathAppData['./nino/filesystem/contentpath'] = '/var/nino-private';

check( 'config.php keeps following its own configpath, not the private root', \Nino\Filesystem::path( $pathAppData, '/config.php' ) === '/srv/site/private/config.php' );
check( 'a moved private root takes the templates with it', \Nino\Filesystem::path( $pathAppData, '/templates/page-home.tpl' ) === '/var/nino-private/templates/page-home.tpl' );
check( '...and text, elements and data', [
	\Nino\Filesystem::path( $pathAppData, '/text' ),
	\Nino\Filesystem::path( $pathAppData, '/elements' ),
	\Nino\Filesystem::path( $pathAppData, '/data' ),
] === [ '/var/nino-private/text', '/var/nino-private/elements', '/var/nino-private/data' ] );
check( '...and the asset sources, which are private content like the rest', \Nino\Filesystem::path( $pathAppData, '/assets/style.design.css' ) === '/var/nino-private/assets/style.design.css' );
/*	...and what is addressed through the '/private' prefix rather than as one
	of the PRIVATE_DIRS. Those were two keys resolved as two roots, so moving
	the private root moved the templates, the text and the data and left
	everything under the prefix - the recovery secret, the backups, the logs -
	where it had been. Nothing did move them apart in practice, because
	\Nino\init() wrote both from one value; there is one key now	*/
check( '...and everything addressed through the /private prefix, which used to stay behind', [
	\Nino\Filesystem::path( $pathAppData, '/private/.auth/pw.php' ),
	\Nino\Filesystem::path( $pathAppData, '/private/data/forms.php' ),
] === [ '/var/nino-private/.auth/pw.php', '/var/nino-private/data/forms.php' ] );
check( 'the two names for it answer the same directory', \Nino\Filesystem::getPrivatePath( $pathAppData ) === \Nino\Filesystem::getContentPath( $pathAppData ) );
check( 'but leaves the public ones where the webserver reaches them', \Nino\Filesystem::path( $pathAppData, '/images/hero.jpg' ) === '/srv/site/public/images/hero.jpg' );

// config.php keeps its own, older override - it wins over the private root
$pathAppData['./nino/filesystem/configpath'] = '/etc/nino';
check( 'NINO_CONFIG_DIR still wins for config.php alone', \Nino\Filesystem::path( $pathAppData, '/config.php' ) === '/etc/nino/config.php' );

// --- and the url side, which has to split exactly the same way ----------
//
// A tool bundles its own login css into /_admin/.cache/ - that is code
// shipped with the tool, not this project's public content. Giving every
// /.cache path the public prefix pointed /_admin's stylesheet and login
// script at /public/_admin/.cache/... and left the login page unstyled
// with its script 404ing, which no unit assertion here noticed
$pathAppData['./nino/filesystem/publicpath'] = '/srv/site/public';
$pathAppData['/nino/dir'] = '';

check( 'public content is reached under the public prefix', \Nino\Filesystem::url( $pathAppData, '/images/hero.jpg' ) === '/public/images/hero.jpg' );
check( '...including the generated bundle', \Nino\Filesystem::url( $pathAppData, '/.cache/style.css' ) === '/public/.cache/style.css' );
check( "a tool's own bundle keeps resolving next to the tool", \Nino\Filesystem::url( $pathAppData, '/_admin/.cache/login.js' ) === '/_admin/.cache/login.js' );
check( '...and so does any other tool file', \Nino\Filesystem::url( $pathAppData, '/_admin/assets/script.js' ) === '/_admin/assets/script.js' );

/*	The two lists have to stay disjoint or path() answers whichever it tests
	first, and a directory that is private on disk but public by url is the
	worst of both: written where nothing serves it, linked where nothing is.	*/
check( 'nothing is on both sides of the split', array_intersect( \Nino\Filesystem::PRIVATE_DIRS, \Nino\Filesystem::PUBLIC_DIRS ) === [] );
// The asset sources are the one thing that looks public and is not - and the
// invariant that lets them be private is that no caller ever builds a url for
// one. If that ever changes, this is where it breaks first
check( 'an asset source has no public url to be reached under', \Nino\Filesystem::url( $pathAppData, '/assets/style.custom.css' ) === '/assets/style.custom.css' );

$pathAppData['/nino/dir'] = '/subdir';
check( 'a subdirectory install carries into both', [
	\Nino\Filesystem::url( $pathAppData, '/images/hero.jpg' ),
	\Nino\Filesystem::url( $pathAppData, '/_admin/assets/script.js' ),
] === [ '/subdir/public/images/hero.jpg', '/subdir/_admin/assets/script.js' ] );

echo "\n";


// --- A project served from a subdirectory ---------------------------------

echo "A subdirectory install - the router reads past the directory, every address the kernel writes carries it\n";

/*	Routes are keyed from the project's root ('GET://about'), and every
	address a page writes puts [[/nino/dir]] in front of one. The request
	path was looked up as it came in, though, so a site at example.com/shop/
	answered every one of its own addresses with the 404 page. Measured
	before the router read past the directory: with '/nino/dir' = '/sub',
	'/sub/page' answered 404 and '/page' 200	*/
$subAppData = $appData;
$subAppData['/nino/dir'] = '/sub';
$subAppData['/nino/http/routes'] = [
	'GET://'				=> [ 'uri' => '/home', 'body' => 'home' ],
	'GET://page'		=> [ 'uri' => '/page', 'body' => 'page', 'locale' => 'de_DE' ],
	'GET://en/page'	=> [ 'uri' => '/page', 'body' => 'page', 'locale' => 'en_US', 'navs' => [ 'main' => 5 ] ],
];
$subAppData['./nino/http/requests'] = [];

$subRequest = fakeRequest( $subAppData, '/sub/page' );
\Nino\Http::response( $subAppData, $subRequest );
check( 'the request path is read without the directory, and the route is found', $subRequest['/nino/http/request']['uri'] === '/page' && $subRequest['/nino/http/response']['uri'] === '/page' && $subRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the directory alone is the root', fakeRequest( $subAppData, '/sub' )['/nino/http/request']['uri'] === '/' );
check( '...with a trailing slash too', fakeRequest( $subAppData, '/sub/' )['/nino/http/request']['uri'] === '/' );
check( 'a path that merely begins with the same letters is left as it is', fakeRequest( $subAppData, '/subway' )['/nino/http/request']['uri'] === '/subway' );
check( 'the query part is read as before', fakeRequest( $subAppData, '/sub/page?x=1' )['/nino/http/request']['query'] === [ 'x' => '1' ] );
check( 'a root install reads the path as it came', fakeRequest( $appData, '/page' )['/nino/http/request']['uri'] === '/page' );

// The route key a locale switch redirects to is project-relative too, and
// the browser is sent to the address, not the key
$switchRequest = fakeRequest( $subAppData, '/sub/page?lang=en_US' );
\Nino\Locales::switchFromQuery( $subAppData, $switchRequest, 'lang' );
check( 'a locale switch redirects under the directory', ( $switchRequest['/nino/http/response']['header']['Location'] ?? '' ) === '/sub/en/page' && $switchRequest['/nino/http/response']['statusCode'] === 302 );

// A menu entry names its page the way the routes do - and links to where
// the page is; what a hand-written line names elsewhere stays as written.
// The entry for the requested page is the active one, compared before the
// directory goes in front
$subAppData['./nino/http/requests'] = [];
fakeRequest( $subAppData, '/sub/page' );
$subMenu = \Nino\Modules\Navigation::doShortcode( $subAppData, [ 'content' => "/page:Page\n/other:Other\n//example.com/x:Elsewhere\n#top:Top" ] );
check( 'a menu entry links under the directory', str_contains( $subMenu, 'href="/sub/page"' ) === true && str_contains( $subMenu, 'href="/sub/other"' ) === true );
check( '...and the requested page is still the active entry', preg_match( '/href="\/sub\/page" class="nino-is-active"/', $subMenu ) === 1 );
check( '...while a protocol-relative url and a fragment are written as they stand', str_contains( $subMenu, 'href="//example.com/x"' ) === true && str_contains( $subMenu, 'href="#top"' ) === true );
\Nino\Html::addFills( $subAppData, [ '/_nino/webpage/page/name' => 'Page' ], 'en_US' );
\Nino\Locales::setCurrentLocale( $subAppData, 'en_US' );
check( 'a generated entry links under the directory too', str_contains( \Nino\Modules\Navigation::doShortcode( $subAppData, [ 'nav' => 'main' ] ), 'href="/sub/en/page"' ) === true );

/*	Where the directory comes from. config.php may name it; before config.php
	exists the wizard runs from that directory already and posts to an
	endpoint under it, so the entry script's own address is read: the part
	of SCRIPT_NAME that is not the script's path inside the project	*/
if( is_dir( $sandbox. '/_admin' ) === false )
	mkdir( $sandbox. '/_admin', 0777, true );
file_put_contents( $sandbox. '/_admin/index.php', '' );
file_put_contents( $sandbox. '/index.php', '' );
$entry = function( string $name, string $file ): array { return [ 'SCRIPT_NAME' => $name, 'SCRIPT_FILENAME' => $file ]; };

check( "the directory is what stands before the entry script's path inside the project", \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/_admin/index.php', $sandbox. '/_admin/index.php' ) ) === '/shop' );
check( "...for the site's own index.php too", \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/index.php', $sandbox. '/index.php' ) ) === '/shop' );
check( '...and two levels deep', \Nino\Filesystem::deriveDir( $appData, $entry( '/sites/shop/index.php', $sandbox. '/index.php' ) ) === '/sites/shop' );
check( 'a root install answers the empty directory', \Nino\Filesystem::deriveDir( $appData, $entry( '/_admin/index.php', $sandbox. '/_admin/index.php' ) ) === '' );
check( 'a script outside the project answers nothing', \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/index.php', __FILE__ ) ) === '' );
check( 'a name that does not end in the script\'s path answers nothing', \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/other.php', $sandbox. '/index.php' ) ) === '' );
check( 'a request without the pair answers nothing', \Nino\Filesystem::deriveDir( $appData, [] ) === '' );

$seedAppData = $subAppData;
$seedAppData['/nino/dir'] = '';
$seedRequest = \Nino\request( $seedAppData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/shop/page', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => $sandbox. '/index.php' ] );
check( 'a request seeds the directory config.php left empty, and is routed under it', $seedAppData['/nino/dir'] === '/shop' && $seedRequest['/nino/http/response']['uri'] === '/page' && $seedRequest['/nino/http/response']['statusCode'] === 200 );
$namedAppData = $subAppData;
$namedAppData['/nino/dir'] = '/named';
\Nino\request( $namedAppData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/named/page', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => $sandbox. '/index.php' ] );
check( 'a directory config.php names is kept over the derived one', $namedAppData['/nino/dir'] === '/named' );

/*	And the other half: nothing the kernel ships sends a browser to an address
	from the domain root. A bundled script writes the [[/nino/dir]] fill
	Modules\Assets substitutes, the two scripts a page loads as they are on
	disk (recovery.js, the wizard's) read Nino.dir, and a template writes
	the fill. The workbench's panels, its login, its logout, the recovery
	page and the wizard all posted to '/_admin/' or '/.nino/auth/...' as it
	stood - twenty-eight places, none of which a site at /shop reached	*/
$kernelRoot = dirname( __DIR__ );
$rootAbsoluteRequests = [];
foreach( array_merge( glob( $kernelRoot. '/_nino/*.js' ), glob( $kernelRoot. '/_admin/assets/*.js' ), glob( $kernelRoot. '/_admin/install/assets/*.js' ), glob( $kernelRoot. '/_admin/Nino/Modules/*/assets/*.js' ), glob( $kernelRoot. '/_nino/Nino/Modules/*/assets/*.js' ) ) as $shippedScript ) {
	$shippedSource = (string) file_get_contents( $shippedScript );
	// Over the whole file, not line by line: a call may put its first
	// argument on the next line, and Nino.js's own two did
	preg_match_all( '/\b(?:sendRequest|logout|replace|assign)\(\s*\'\/(?!\/)|\blogin\(\s*[^;]*?,\s*\'\/[^\']*\'\s*,\s*function/', $shippedSource, $rootAbsoluteHits, PREG_OFFSET_CAPTURE );
	foreach( $rootAbsoluteHits[0] as $hit )
		$rootAbsoluteRequests[] = substr( $shippedScript, strlen( $kernelRoot ) + 1 ). ':'. ( substr_count( $shippedSource, "\n", 0, $hit[1] ) + 1 );
}
check( 'no shipped script sends a request to, or a browser to, an address from the domain root'. ( $rootAbsoluteRequests === [] ? '' : ' - '. implode( ', ', $rootAbsoluteRequests ) ), $rootAbsoluteRequests === [] );

$rootAbsoluteTemplates = [];
foreach( array_merge( glob( $kernelRoot. '/_admin/templates/*.tpl' ), glob( $kernelRoot. '/_admin/install/templates/*.tpl' ), glob( $kernelRoot. '/_nino/Nino/Modules/*/templates/*.tpl' ) ) as $shippedTemplate )
	if( preg_match( '/\b(?:action|href|src)="\/(?!\/)/', (string) file_get_contents( $shippedTemplate ) ) === 1 )
		$rootAbsoluteTemplates[] = substr( $shippedTemplate, strlen( $kernelRoot ) + 1 );
check( 'no template the kernel ships writes an address from the domain root'. ( $rootAbsoluteTemplates === [] ? '' : ' - '. implode( ', ', $rootAbsoluteTemplates ) ), $rootAbsoluteTemplates === [] );

echo "\n";


// --- Escaping never answers bad input with nothing ------------------------

echo "htmlspecialchars() keeps what it cannot encode\n";

/*	htmlspecialchars() returns '' for input that is not valid utf-8, unless
	ENT_SUBSTITUTE is among its flags - and php's own default carries it only
	as long as no flags are given at all. Every call here spells them out, so
	every call has to spell out that one too. It has bitten twice: \Nino\Form
	stored and mailed a submission as nothing, and the Elements module rendered
	a field as nothing. A grep is the only thing that stops the third.	*/
$escapeSources = [];
$escapeWalk = static function( string $dir ) use ( &$escapeWalk, &$escapeSources ): void {
	foreach( (array) glob( $dir. '/*' ) as $path ) {
		if( is_dir( $path ) === true )
			$escapeWalk( $path );
		elseif( str_ends_with( (string) $path, '.php' ) === true )
			$escapeSources[] = (string) $path;
	}
};
$escapeWalk( __DIR__. '/../_nino' );
$escapeWalk( __DIR__. '/../_admin' );
$escapeOffenders = [];
foreach( $escapeSources as $escapeFile ) {
	foreach( explode( "\n", (string) file_get_contents( $escapeFile ) ) as $escapeNo => $escapeLine ) {
		if( str_contains( $escapeLine, 'htmlspecialchars(' ) === false || str_contains( $escapeLine, 'ENT_' ) === false )
			continue;
		if( str_contains( $escapeLine, 'ENT_SUBSTITUTE' ) === true )
			continue;
		$escapeOffenders[] = substr( (string) realpath( $escapeFile ), strlen( (string) realpath( __DIR__. '/..' ) ) + 1 ). ':'. ( $escapeNo + 1 );
	}
}
check( 'no escape in the kernel or the workbench drops ENT_SUBSTITUTE'. ( $escapeOffenders === [] ? '' : ' - '. implode( ' | ', $escapeOffenders ) ), $escapeOffenders === [] );
check( '...and the rule has something to find: the sources were actually read', count( $escapeSources ) > 40 );

echo "\n";


// --- Every docblock documents something ------------------------------------

echo "No docblock in the kernel or the workbench documents another docblock\n";

/*	A method renamed or moved away from its docblock leaves the block behind,
	and the next member's own block then lands directly under it. Php takes the
	second one and every reader follows - Reflection, an editor, the person
	looking for the rule - so the first documents nothing and rots where it
	stands, describing a method that may no longer exist. Two T_DOC_COMMENTs
	with no declaration between them is the whole test.	*/
$docOffenders = [];
foreach( $escapeSources as $docFile ) {

	$docPrevious = null;

	foreach( token_get_all( (string) file_get_contents( $docFile ) ) as $docToken ) {

		if( is_array( $docToken ) === false ) {
			$docPrevious = null;
			continue;
		}

		if( $docToken[0] === T_WHITESPACE || $docToken[0] === T_COMMENT )
			continue;

		if( $docToken[0] === T_DOC_COMMENT ) {

			if( $docPrevious !== null )
				$docOffenders[] = substr( (string) realpath( $docFile ), strlen( (string) realpath( __DIR__. '/..' ) ) + 1 ). ':'. $docPrevious;

			$docPrevious = $docToken[2];
			continue;
		}

		$docPrevious = null;
	}
}
check( 'no docblock documents another docblock'. ( $docOffenders === [] ? '' : ' - '. implode( ' | ', $docOffenders ) ), $docOffenders === [] );

echo "\n";


// --- One language in the source -------------------------------------------

echo "Comments in the kernel and the workbench are written in one language\n";

/*	The project is written in English - code, comments and docblocks alike -
	and German belongs in the two places that are about German: a locale's
	text file and a *.de.md manual. What kept turning up instead was a
	docblock naming the screen it belongs to in the language the screenshot
	was taken in ("Elemente nach Typ", "ueberall abmelden"), which reads fine
	to whoever wrote it and not at all to the next person.

	Comment lines only, and by two marks together: an umlaut, and a word list
	of german function words that are not also english ones. Neither alone is
	enough - "Elemente nach Typ" carries no umlaut, and an umlaut on its own
	would flag \Nino.ui.js's 'de' locale table, which is the point of that
	table. Words that are german and english both ("die", "man", "war",
	"hat", "fast", "also") are deliberately not in the list; the whole source
	tree is checked against it below, so a false positive is visible here
	rather than in somebody's next patch.	*/
$languageWords = 'nach|nicht|wird|werden|oder|aber|wenn|damit|durch|zwischen|schon|noch|immer|eine|einen|einem|einer|kein|keine|jede|jeden|jedes|dieser|diese|dieses|welche|und|sich|auch|sind|nur|beim|zum|zur|vom|der|das|des|dem|ist|sein|Datei|Zeile|Seite|Elemente|Typ|sowie|bereits|etwa|zwar';
$languageSources = [];
$languageWalk = static function( string $dir ) use ( &$languageWalk, &$languageSources ): void {
	foreach( (array) glob( $dir. '/*' ) as $path ) {
		$path = (string) $path;
		// A locale's own text lives under text/, a shipped library page is a
		// project's content rather than this project's source
		if( str_contains( $path, '/text' ) === true || str_contains( $path, '/install/library' ) === true || str_contains( $path, 'de_DE' ) === true )
			continue;
		if( is_dir( $path ) === true )
			$languageWalk( $path );
		elseif( preg_match( '/\\.(php|js|css)$/', $path ) === 1 )
			$languageSources[] = $path;
	}
};
$languageWalk( __DIR__. '/../_nino' );
$languageWalk( __DIR__. '/../_admin' );

$languageOffenders = [];
foreach( $languageSources as $languageFile ) {
	foreach( explode( "\n", (string) file_get_contents( $languageFile ) ) as $languageNo => $languageLine ) {

		$opening	= ltrim( $languageLine, " \t" );
		$trailing	= strpos( $languageLine, '//' );

		// The comment half of the line, and only that: a trailing '// ...' is
		// one (the '//' of a url is not), and everything left of it is code,
		// where a variable named $der is nobody's german
		if( $opening !== '' && ( $opening[0] === '*' || str_starts_with( $opening, '//' ) || str_starts_with( $opening, '/*' ) ) )
			$languageComment = $languageLine;
		elseif( $trailing !== false && $trailing > 0 && $languageLine[$trailing - 1] !== ':' )
			$languageComment = substr( $languageLine, $trailing );
		else
			continue;

		if( preg_match( '/[\x{00e4}\x{00f6}\x{00fc}\x{00df}\x{00c4}\x{00d6}\x{00dc}]|\b('. $languageWords. ')\b/u', $languageComment ) === 1 )
			$languageOffenders[] = substr( (string) realpath( $languageFile ), strlen( (string) realpath( __DIR__. '/..' ) ) + 1 ). ':'. ( $languageNo + 1 );
	}
}
check( 'no comment in the kernel or the workbench is written in german'. ( $languageOffenders === [] ? '' : ' - '. implode( ' | ', $languageOffenders ) ), $languageOffenders === [] );
check( '...and the rule has something to find: the sources were actually read', count( $languageSources ) > 60 );

echo "\n";


// --- The private root is never reachable over http -----------------------

echo "router.php - the private root is never served\n";

// Moving the templates out of the public root only helps if nothing hands
// them back at their new path. The dev server applies no .htaccess at all,
// so router.php has to refuse /private itself - a plain request for
// /private/templates/page-home.tpl returned the full template source until
// it did. Production has private/.htaccess (and NINO_PRIVATE_DIR for a
// setup that cannot rely on it), see docs/deployment.md
$routerSource = file_get_contents( __DIR__. '/../router.php' );

check( 'router.php refuses the private root', str_contains( $routerSource, '#^/private(?:/|$)#' ) === true );
check( '...before it ever looks for a static file', strpos( $routerSource, '/private' ) < strpos( $routerSource, 'is_file( __DIR__. $uri )' ) );

// The library used to have one deliberately public file, the theme picker's
// preview image; since 1.2 it has none, and the router says so without an
// exception to get wrong
check( 'the installer library is refused whole, with nothing carved out of it',
	str_contains( $routerSource, '#^/_admin/install/library(?:/|$)#' ) === true
	&& str_contains( $routerSource, '/_admin/install/library/themes' ) === false
	&& str_contains( (string) file_get_contents( __DIR__. '/../_admin/install/library/.htaccess' ), 'FilesMatch' ) === false );
check( '...and refuses its source before static-file delivery', strpos( $routerSource, '#^/_admin/install/library(?:/|$)#' ) < strpos( $routerSource, 'is_file( __DIR__. $uri )' ) );

/*	A checkout ships no private directory at all - it is one installation's
	own state, not repository content. The wizard creates it and brings the
	deny rule that protects it (see _admin/install/library/base), so what has to
	hold here is that the rule exists to be brought.	*/
check( 'a checkout ships no private directory - the wizard creates it', is_dir( __DIR__. '/../private' ) === false );
check( '...and the deny rule that protects it travels with the installer', str_contains(
	(string) @file_get_contents( __DIR__. '/../_admin/install/library/base/private/.htaccess' ), 'Require all denied'
) === true );
check( 'the installer library ships the matching Apache protection', str_contains(
	(string) @file_get_contents( __DIR__. '/../_admin/install/library/.htaccess' ), 'Require all denied'
) === true );

// Nothing private may sit in the public root of a fresh checkout
foreach( \Nino\Filesystem::PRIVATE_DIRS as $private )
	check( "a checkout ships no $private in the public root", file_exists( __DIR__. '/..'. $private ) === false );

check( 'and no config.php either - the wizard writes the first one', is_file( __DIR__. '/../private/config.php' ) === false );

echo "\n";


// --- Modules\Cache ---------------------------------------------------------

echo "Modules\\Cache - full-page cache for anonymous GET\n";

// A request as Http::request() leaves it, plus the response fields the two
// callbacks read. Http::output() exits, so the hit itself is driven through
// _prepare(), the decide-and-shape half callbackResponse() calls before it -
// the same split Modules\Maintenance uses for the same reason
function cacheRequest( string $uri, string $method = 'GET', array $response = [] ): array {
	return [
		'/nino/http/request' => [
			'method' 		=> $method === 'HEAD' ? 'GET' : $method,
			'rawMethod'	=> $method,
			'uri' 			=> $uri,
			'query' 		=> [],
		],
		'/nino/http/response' => array_merge( [
			'uri' 				=> $uri,
			'locale' 			=> 'de_DE',
			'statusCode'	=> 200,
			'header' 			=> [],
			'body' 				=> '<html>cached</html>',
		], $response ),
	];
}

function cacheEntryCount( array &$appData ): int {
	return count( glob( \Nino\Filesystem::path( $appData, '/data/cache' ). '/*.php' ) ?: [] );
}

$appData['/nino/cache/status'] = true;
$appData['/nino/cache/ttl'] = 60;
$appData['/nino/cache/blacklist'] = [];
\Nino\Modules\Cache::_invalidate( $appData );

$store = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $store );
check( 'an anonymous GET 200 is stored', cacheEntryCount( $appData ) === 1 );

// --- what must never be stored, whatever the configuration says -----------

foreach( [
	'a POST' 												=> [ cacheRequest( '/home', 'POST' ), [] ],
	'a HEAD, which carries no body' => [ cacheRequest( '/home', 'HEAD' ), [] ],
	'a 404' 												=> [ cacheRequest( '/nope', 'GET', [ 'statusCode' => 404 ] ), [] ],
	'a redirect' 										=> [ cacheRequest( '/home', 'GET', [ 'header' => [ 'Location' => '/other' ] ] ), [] ],
	'a json body' 									=> [ cacheRequest( '/home', 'GET', [ 'body' => [ 'ok' => true ] ] ), [] ],
	'an empty body' 								=> [ cacheRequest( '/home', 'GET', [ 'body' => '' ] ), [] ],
	'a tool uri' 										=> [ cacheRequest( '/_admin' ), [] ],
	'a module endpoint' 						=> [ cacheRequest( '/.newsletter' ), [] ],
] as $label => $case ) {
	\Nino\Modules\Cache::_invalidate( $appData );
	$req = $case[0];
	\Nino\Modules\Cache::callbackOutput( $appData, $req );
	check( "$label is never stored", cacheEntryCount( $appData ) === 0 );
}

// A query var is what makes a page not the same page for everyone - the
// locale switch is exactly one
\Nino\Modules\Cache::_invalidate( $appData );
$withQuery = cacheRequest( '/home' );
$withQuery['/nino/http/request']['query'] = [ '/_nino/localepicker/current' => 'de_DE' ];
\Nino\Modules\Cache::callbackOutput( $appData, $withQuery );
check( 'a request carrying query vars is never stored', cacheEntryCount( $appData ) === 0 );

// A signed-in visitor's page may carry their name or an editing affordance
\Nino\Modules\Cache::_invalidate( $appData );
$appData['./nino/auth/current'] = [ 'mail' => 'someone@example.com', 'perms' => [] ];
$signedIn = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $signedIn );
check( 'a signed-in visitor\'s page is never stored', cacheEntryCount( $appData ) === 0 );
unset( $appData['./nino/auth/current'] );

// Off means off
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/status'] = false;
$offRequest = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $offRequest );
check( 'nothing is stored while the cache is switched off', cacheEntryCount( $appData ) === 0 );
$appData['/nino/cache/status'] = true;

// --- the two per-request values ------------------------------------------
//
// A stored page must not carry the csrf token or the jstext nonce of whoever
// it was rendered for: serving one visitor's token to everybody leaks it and
// 403s every other form submission, and a nonce that survives in the cache is
// readable by anyone who fetches the page - which is the one property a csp
// nonce may not have.

\Nino\Modules\Cache::_invalidate( $appData );
$token = \Nino\Csrf::getToken( $appData );
$appData['./nino/jstext/nonce'] = 'nonce-of-this-request';

$tokenRequest = cacheRequest( '/home', 'GET', [
	'body' => '<input name="_csrf" value="'. $token. '"><script nonce="nonce-of-this-request">x</script>',
] );
\Nino\Modules\Cache::callbackOutput( $appData, $tokenRequest );

$storedFile	= glob( \Nino\Filesystem::path( $appData, '/data/cache' ). '/*.php' )[0] ?? '';
$storedBody = ( include $storedFile )['body'] ?? '';

check( 'the stored page carries no csrf token', str_contains( $storedBody, $token ) === false );
check( 'the stored page carries no jstext nonce', str_contains( $storedBody, 'nonce-of-this-request' ) === false );
check( '...both are held as markers instead', substr_count( $storedBody, '@@nino-cache-' ) === 2 );

// --- blacklist ------------------------------------------------------------

$appData['/nino/cache/blacklist'] = [ '/contact', '/blog/*' ];

foreach( [ '/contact' => false, '/blog' => false, '/blog/post-1' => false, '/blog/2026/one' => false,
           '/contacts' => true, '/blogging' => true, '/home' => true ] as $uri => $expectStored ) {
	\Nino\Modules\Cache::_invalidate( $appData );
	$req = cacheRequest( $uri );
	\Nino\Modules\Cache::callbackOutput( $appData, $req );
	check( "blacklist: $uri is ". ( $expectStored ? 'cached' : 'excluded' ), ( cacheEntryCount( $appData ) === 1 ) === $expectStored );
}

// A pattern is admin-editable text, so it must never reach a regex engine
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/blacklist'] = [ '/.*' ];
$regexish = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $regexish );
check( 'a regex-looking pattern is matched literally, not evaluated', cacheEntryCount( $appData ) === 1 );
$appData['/nino/cache/blacklist'] = [];

// --- expiry ---------------------------------------------------------------

\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/ttl'] = 60;
$fresh = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $fresh );
$entryPath	= glob( \Nino\Filesystem::path( $appData, '/data/cache' ). '/*.php' )[0];
$entry 			= include $entryPath;
check( 'a stored entry carries an expiry from the configured ttl', ( $entry['expires'] - time() ) > 50 && ( $entry['expires'] - time() ) <= 60 );
check( '...and the locale it rendered in, which serving has to re-apply', ( $entry['locale'] ?? '' ) === 'de_DE' );

// --- a write through a tool drops everything ------------------------------

foreach( [ '/_admin' ] as $tool ) {
	\Nino\Modules\Cache::_invalidate( $appData );
	$seed = cacheRequest( '/home' );
	\Nino\Modules\Cache::callbackOutput( $appData, $seed );
	$toolWrite = cacheRequest( $tool, 'POST' );
	\Nino\Modules\Cache::callbackOutput( $appData, $toolWrite );
	check( "a successful POST to $tool drops the cache", cacheEntryCount( $appData ) === 0 );
}

// A rejected write changed nothing, so it must not cost the cache either
\Nino\Modules\Cache::_invalidate( $appData );
$seed = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $seed );
$rejected = cacheRequest( '/_admin', 'POST', [ 'statusCode' => 403 ] );
\Nino\Modules\Cache::callbackOutput( $appData, $rejected );
check( 'a rejected tool write leaves the cache alone', cacheEntryCount( $appData ) === 1 );

// Reading in a tool is not a write either
$toolRead = cacheRequest( '/_admin', 'GET' );
\Nino\Modules\Cache::callbackOutput( $appData, $toolRead );
check( 'merely opening a tool leaves the cache alone', cacheEntryCount( $appData ) === 1 );

// The frontend's own endpoints are not tool writes - a contact form
// submission changes no page
$formPost = cacheRequest( '/.form', 'POST' );
\Nino\Modules\Cache::callbackOutput( $appData, $formPost );
check( 'a form submission does not drop the cache', cacheEntryCount( $appData ) === 1 );

// --- keying ---------------------------------------------------------------

\Nino\Modules\Cache::_invalidate( $appData );
$de = cacheRequest( '/home' );
$en = cacheRequest( '/home', 'GET', [ 'locale' => 'en_US' ] );
\Nino\Modules\Cache::callbackOutput( $appData, $de );
\Nino\Modules\Cache::callbackOutput( $appData, $en );
check( 'the same uri in two locales is two entries', cacheEntryCount( $appData ) === 2 );

// Two routes can resolve to the same page while the page still differs by the
// uri it was asked for - html-header.tpl's canonical link is exactly that
\Nino\Modules\Cache::_invalidate( $appData );
$slash = cacheRequest( '/' );
$slash['/nino/http/response']['uri'] = '/home';
$named = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $slash );
\Nino\Modules\Cache::callbackOutput( $appData, $named );
check( 'two request uris resolving to one page stay two entries', cacheEntryCount( $appData ) === 2 );

// --- what a wildcard route and a route handler mean for the store ---------

// A wildcard route ('GET://blog/*') answers an unbounded set of uris, and
// the key is the uri as asked for - so every made-up address under it used
// to become an entry of its own, and a lock file beside it. An anonymous
// client could grow private/data/ as fast as it could send requests
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/http/routes']['GET://blog/*'] = [ 'uri' => '/blog', 'body' => '[template /templates/page-blog]' ];
$wildcard = cacheRequest( '/blog/anything-at-all' );
$wildcard['/nino/http/response']['uri'] = '/blog';
\Nino\Modules\Cache::callbackOutput( $appData, $wildcard );
check( 'a page answered through a wildcard route is never stored - its uris are the client\'s to invent', cacheEntryCount( $appData ) === 0 );
check( '...and it is not told it was a miss either', isset( $wildcard['/nino/http/response']['header']['X-Nino-Cache'] ) === false );

$exact = cacheRequest( '/blog' );
\Nino\Modules\Cache::callbackOutput( $appData, $exact );
check( 'the wildcard\'s own address, which is a route of its own, still is', cacheEntryCount( $appData ) === 1 );
unset( $appData['/nino/http/routes']['GET://blog/*'] );

// A route with a handler of its own (the shape Modules\Form and the Posts
// feature use) is never answered from the cache - and so must never be
// stored: those entries could only ever be written, never read
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/http/routes']['GET://post'] = [ 'uri' => '/post' ];
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://post', static function( array &$appData, array &$request ): void {} );
$handled = cacheRequest( '/post' );
\Nino\Modules\Cache::callbackOutput( $appData, $handled );
check( 'a page whose route has a handler of its own is not stored - it could never be served', cacheEntryCount( $appData ) === 0 );
check( '...and is not marked as a miss', isset( $handled['/nino/http/response']['header']['X-Nino-Cache'] ) === false );
unset( $appData['./nino/callbacks']['/nino/http/response/GET://post'], $appData['/nino/http/routes']['GET://post'] );

// --- the hit ---------------------------------------------------------------

\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/http/routes']['GET://home'] = [ 'uri' => '/home' ];
$appData['./nino/jstext/nonce'] = 'a-render-time-nonce';
$rendered = cacheRequest( '/home', 'GET', [ 'body' => '<html data-csrf="'. \Nino\Csrf::getToken( $appData ). '" data-nonce="a-render-time-nonce">cached</html>' ] );
\Nino\Modules\Cache::callbackOutput( $appData, $rendered );
$storedEntry = \Nino\Filesystem::getFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', [] );
check( 'the two per-request values are stored as markers, not as this request\'s own', str_contains( $storedEntry['body'] ?? '', \Nino\Csrf::getToken( $appData ) ) === false
	&& str_contains( $storedEntry['body'] ?? '', 'a-render-time-nonce' ) === false );

// The next visitor: another session, so another token and another nonce
\Nino\Csrf::rotateToken( $appData );
$appData['./nino/jstext/nonce'] = 'the-next-requests-nonce';
$appData['./nino/locales/current'] = 'en_US';
$hit = cacheRequest( '/home', 'GET', [ 'body' => '' ] );
check( 'a stored page is served', \Nino\Modules\Cache::_prepare( $appData, $hit ) === true );
check( '...carrying this request\'s token and nonce, with no marker left in it', str_contains( $hit['/nino/http/response']['body'], 'data-csrf="'. \Nino\Csrf::getToken( $appData ). '"' ) === true
	&& str_contains( $hit['/nino/http/response']['body'], 'data-nonce="the-next-requests-nonce"' ) === true
	&& str_contains( $hit['/nino/http/response']['body'], '@@nino-cache' ) === false );
check( '...and saying so in the header', ( $hit['/nino/http/response']['header']['X-Nino-Cache'] ?? '' ) === 'hit' );
check( '...with the locale the page was rendered in applied to the session', \Nino\Locales::getCurrentLocale( $appData ) === 'de_DE' );

// --- the policy a feature widened -------------------------------------------
//
// A hit never renders, so the policy a module widened for what the page uses
// (an embed's frame-src) has to come out of the entry. The output callback
// runs at priority 9 so that it stores what the features' own priority 5
// callbacks made of it, and the nonce in it is the request's, not the stored one.

\Nino\Modules\Cache::_invalidate( $appData );
$outputCallbacks = $appData['./nino/callbacks']['/nino/http/output'] ?? null;
$appData['./nino/jstext/nonce'] = 'policy-render-nonce';
$widened = cacheRequest( '/home', 'GET', [ 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-policy-render-nonce'" ] ] );
$freshData = [];
\Nino\Modules\Cache::init( $freshData );
check( 'the module registers its output callback at priority 9, after the features\' own', in_array( [ \Nino\Modules\Cache::class, 'callbackOutput' ], $freshData['./nino/callbacks']['/nino/http/output'][9] ?? [], true ) === true );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/output', [ \Nino\Modules\Cache::class, 'callbackOutput' ], 9 );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/output', static function( array &$appData, array &$request ): void {
	$request['/nino/http/response']['header']['Content-Security-Policy'] .= "; frame-src 'self' https://www.youtube-nocookie.com";
} );
\Nino\Callbacks::doCallbacks( $appData, '/nino/http/output', $widened );
$policyEntry = \Nino\Filesystem::getFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', [] );
check( 'the stored entry keeps the policy a feature widened in the output phase, though Cache\'s callback registered first', str_contains( $policyEntry['csp'] ?? '', "frame-src 'self' https://www.youtube-nocookie.com" ) === true );
check( '...with the nonce as a marker, not the one of the request that rendered it', str_contains( $policyEntry['csp'] ?? '', 'policy-render-nonce' ) === false
	&& str_contains( $policyEntry['csp'] ?? '', "'nonce-@@nino-cache-nonce-2f8a@@'" ) === true );

$appData['./nino/jstext/nonce'] = 'policy-hit-nonce';
$policyHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-policy-hit-nonce'" ] ] );
check( 'a hit on that entry is served', \Nino\Modules\Cache::_prepare( $appData, $policyHit ) === true );
check( '...under the widened policy', str_contains( $policyHit['/nino/http/response']['header']['Content-Security-Policy'], "frame-src 'self' https://www.youtube-nocookie.com" ) === true );
check( '...carrying this request\'s nonce and no marker', str_contains( $policyHit['/nino/http/response']['header']['Content-Security-Policy'], "'nonce-policy-hit-nonce'" ) === true
	&& str_contains( $policyHit['/nino/http/response']['header']['Content-Security-Policy'], '@@nino-cache' ) === false );

// An entry without a policy (stored by an earlier kernel, or a page sent
// without one) keeps the header the response callbacks composed
unset( $policyEntry['csp'] );
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $policyEntry );
$bareHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( 'an entry without a stored policy is still served', \Nino\Modules\Cache::_prepare( $appData, $bareHit ) === true );
check( '...and leaves the composed header as it was', $bareHit['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'" );

// ...and so does one stored empty, as a page sent without a policy leaves it
$policyEntry['csp'] = '';
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $policyEntry );
$emptyHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( 'an entry stored with an empty policy is served and leaves the composed header as it was', \Nino\Modules\Cache::_prepare( $appData, $emptyHit ) === true
	&& $emptyHit['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'" );

// A policy stored with a nonce, read by a request that has none (Jstext off
// since): the marker cannot be completed and must not be sent as a source -
// the composed header stands
$policyEntry['csp'] = "default-src 'self'; script-src 'self' 'nonce-@@nino-cache-nonce-2f8a@@'";
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $policyEntry );
$appData['./nino/jstext/nonce'] = '';
$noNonceHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( 'a stored policy with a nonce marker, read by a request without a nonce, is served', \Nino\Modules\Cache::_prepare( $appData, $noNonceHit ) === true );
check( '...and sends the composed header, not a policy with "nonce-@@...@@" in it', $noNonceHit['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'"
	&& str_contains( json_encode( $noNonceHit['/nino/http/response']['header'] ), '@@nino-cache' ) === false );
$appData['./nino/jstext/nonce'] = 'policy-hit-nonce';
$withNonceHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( '...while the same entry still gets the nonce of a request that has one', \Nino\Modules\Cache::_prepare( $appData, $withNonceHit ) === true
	&& str_contains( $withNonceHit['/nino/http/response']['header']['Content-Security-Policy'], "'nonce-policy-hit-nonce'" ) === true );

if( $outputCallbacks === null )
	unset( $appData['./nino/callbacks']['/nino/http/output'] );
else
	$appData['./nino/callbacks']['/nino/http/output'] = $outputCallbacks;
$appData['./nino/jstext/nonce'] = 'the-next-requests-nonce';

// An entry past its lifetime is not served - and goes, rather than sitting
// there being read and rejected on every request until the next invalidation
$expiredPath = \Nino\Filesystem::path( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php' );
$expiredEntry = \Nino\Filesystem::getFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', [] );
$expiredEntry['expires'] = time() - 1;
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $expiredEntry );
$stale = cacheRequest( '/home', 'GET', [ 'body' => '' ] );
check( 'an entry past its lifetime is not served', \Nino\Modules\Cache::_prepare( $appData, $stale ) === false );
check( '...and is dropped rather than read again on every request', is_file( $expiredPath ) === false );

// The lock side-car every write creates (see Filesystem::lockFile) belongs
// to the entry, so dropping the cache drops it too - otherwise /data/.locks
// kept one empty file per page ever cached, for good
\Nino\Modules\Cache::_invalidate( $appData );
$locked = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $locked );
$lockPath = \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( '/data/cache/'. sha1( '/home|de_DE' ). '.php' ). '.lock';
check( 'a stored page has a lock side-car', is_file( $lockPath ) === true );
\Nino\Modules\Cache::_invalidate( $appData );
check( '...and dropping the cache takes it with it', is_file( $lockPath ) === false );

unset( $appData['/nino/http/routes']['GET://home'], $appData['./nino/jstext/nonce'] );
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/status'] = false;

echo "\n";


// --- Modules\Maintenance ---------------------------------------------------

echo "Modules\\Maintenance - one switch answers every page with 503\n";

// A request as Http::request()/response() would leave it before this
// module's global callback runs - same shape as cacheRequest() above.
function maintenanceRequest( string $uri, string $method = 'GET', array $route = [] ): array {
	return [
		'/nino/http/request' => [
			'method' 		=> $method,
			'rawMethod'	=> $method,
			'uri' 			=> $uri,
			'query' 		=> [],
		],
		// The route's own fields are mixed into the answer by Http::response()
		// before this module's callback runs
		'/nino/http/response' => array_merge( [
			'uri' 				=> $uri,
			'locale' 			=> 'de_DE',
			'statusCode'	=> 200,
			'header' 			=> [],
			'body' 				=> '<html>page</html>',
		], $route ),
	];
}

unset( $appData['./nino/auth/current'] );
$appData['/nino/maintenance/status'] = false;
unset( $appData['/nino/maintenance/retry'] );

$off = maintenanceRequest( '/' );
check( 'status off changes nothing', \Nino\Modules\Maintenance::_prepare( $appData, $off ) === false );
check( '...the response is left exactly as it was', $off['/nino/http/response']['statusCode'] === 200 && $off['/nino/http/response']['body'] === '<html>page</html>' );

// --- status on ---

$appData['/nino/maintenance/status'] = true;
$appData['/nino/maintenance/retry'] = 120;

$home = maintenanceRequest( '/' );
check( 'status on: an anonymous GET of / is answered here', \Nino\Modules\Maintenance::_prepare( $appData, $home ) === true );
check( '...with statusCode 503', $home['/nino/http/response']['statusCode'] === 503 );
check( '...Retry-After from the configured seconds', $home['/nino/http/response']['header']['Retry-After'] === '120' );
check( '...Cache-Control: no-store', $home['/nino/http/response']['header']['Cache-Control'] === 'no-store' );
check( '...the built-in fallback body, no /templates/page-maintenance.tpl on this sandbox',
	str_contains( $home['/nino/http/response']['body'], 'Under maintenance' ) === true
	&& str_contains( $home['/nino/http/response']['body'], 'We will be back shortly.' ) === true );

// /_admin and everything below it keeps working, so an operator can still
// log in and switch this back off
check( '/_admin is untouched', \Nino\Modules\Maintenance::_prepare( $appData, maintenanceRequest( '/_admin' ) ) === false );
check( '...and a screen below it too', \Nino\Modules\Maintenance::_prepare( $appData, maintenanceRequest( '/_admin/config' ) ) === false );

// A module endpoint is answered too - the site is down for a form
// submission exactly as much as for the page it sits on
$formPost = maintenanceRequest( '/.form', 'POST' );
check( 'a /.form POST is answered with the maintenance page as well', \Nino\Modules\Maintenance::_prepare( $appData, $formPost ) === true );
check( '...with statusCode 503', $formPost['/nino/http/response']['statusCode'] === 503 );

// A signed-in account sees the site as it is
$appData['./nino/auth/current'] = [ 'mail' => 'operator@example.com', 'perms' => [] ];
$signedIn = maintenanceRequest( '/' );
check( 'a signed-in user gets the page, not the 503', \Nino\Modules\Maintenance::_prepare( $appData, $signedIn ) === false );
check( '...the response is left exactly as it was', $signedIn['/nino/http/response']['statusCode'] === 200 && $signedIn['/nino/http/response']['body'] === '<html>page</html>' );
unset( $appData['./nino/auth/current'] );

// init() takes the full-page cache out of the loop at runtime, without
// persisting anything - config.php's own /nino/cache/status is untouched
$appData['/nino/cache/status'] = true;
\Nino\Modules\Maintenance::init( $appData );
check( '/nino/cache/status is false at runtime while maintenance is on', $appData['/nino/cache/status'] === false );

$appData['/nino/maintenance/status'] = false;
$appData['/nino/cache/status'] = true;
\Nino\Modules\Maintenance::init( $appData );
check( '...and left alone while maintenance is off', $appData['/nino/cache/status'] === true );

// A route that says 'maintenance' => false stays reachable. The login is one: an
// operator who is not signed in has to be able to sign in, and the answer to that
// POST was the 503 page, the workbench's login form included. Auth carries the
// field on its route, and the module reads it from the answer Http::response()
// has mixed the route into - one mechanism, whatever the route is
$appData['/nino/maintenance/status'] = true;
unset( $appData['./nino/auth/current'] );
$authApp = $appData;
\Nino\Auth::init( $authApp );
$loginRoute 	= $authApp['/nino/http/routes']['POST://.nino/auth/login'] ?? [];
$logoutRoute 	= $authApp['/nino/http/routes']['POST://.nino/auth/logout'] ?? [];
check( 'Auth\'s login route carries the field, its logout route does not', ( $loginRoute['maintenance'] ?? null ) === false && isset( $logoutRoute['maintenance'] ) === false );
$loginPost = maintenanceRequest( '/.nino/auth/login', 'POST', $loginRoute );
check( 'the login POST of a visitor who is not signed in is not answered with the maintenance page', \Nino\Modules\Maintenance::_prepare( $appData, $loginPost ) === false
	&& $loginPost['/nino/http/response']['statusCode'] === 200 && $loginPost['/nino/http/response']['body'] === '<html>page</html>' );
$loginGet = maintenanceRequest( '/.nino/auth/login', 'GET' );
$logoutPost = maintenanceRequest( '/.nino/auth/logout', 'POST', $logoutRoute );
check( '...only a route that says so: anything else at the address, and the logout, is still the maintenance page', \Nino\Modules\Maintenance::_prepare( $appData, $loginGet ) === true
	&& \Nino\Modules\Maintenance::_prepare( $appData, $logoutPost ) === true );
$legalPage = maintenanceRequest( '/impressum', 'GET', [ 'uri' => '/legal/imprint', 'maintenance' => false ] );
check( 'any route with the field is reachable, a page of the Legal module among them', \Nino\Modules\Maintenance::_prepare( $appData, $legalPage ) === false && $legalPage['/nino/http/response']['statusCode'] === 200 );
$notQuite = [ maintenanceRequest( '/x', 'GET', [ 'maintenance' => true ] ), maintenanceRequest( '/x', 'GET', [ 'maintenance' => 'false' ] ), maintenanceRequest( '/x', 'GET', [ 'maintenance' => 0 ] ), maintenanceRequest( '/x', 'GET', [ 'maintenance' => null ] ) ];
check( '...and only the boolean false counts: true, a string, 0 and null are the maintenance page', array_unique( array_map( static function( array $request ) use ( &$appData ): bool { return \Nino\Modules\Maintenance::_prepare( $appData, $request ); }, $notQuite ) ) === [ true ] );
$appData['/nino/maintenance/status'] = false;

/*	The page the module brings. It is its own template per language, read
	from the module's directory - the sandbox has no _nino/, so these point
	the file system at the checkout, as the workbench's own render test does,
	while the text, the templates and the config stay in the sandbox. The
	site's native language is the page's, whatever language the route asked
	for: the visitor has chosen nothing, and this page is not the site's	*/
$moduleApp = $appData;
$moduleApp['./nino/filesystem/path'] = dirname( __DIR__ );
// A path is remembered per file name, and the earlier checks have asked for
// these names with the sandbox as the root
unset( $moduleApp['./nino/filesystem/cache'] );
$moduleApp['/nino/maintenance/status'] = true;
$moduleApp['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
unset( $moduleApp['./nino/auth/current'] );
\Nino\Runtime::unsetSessionValue( $moduleApp, './nino/locales/current' );

$german = maintenanceRequest( '/' );
$german['/nino/http/response']['locale'] = 'en_US';
\Nino\Locales::useLocale( $moduleApp, 'en_US' );
check( 'native de_DE: the module\'s German page, though the route asked for en_US', \Nino\Modules\Maintenance::_prepare( $moduleApp, $german ) === true
	&& str_contains( $german['/nino/http/response']['body'], '<html lang="de">' ) === true );
check( '...with the module\'s German defaults for the two texts', str_contains( $german['/nino/http/response']['body'], '<h1>Wartungsarbeiten</h1>' ) === true
	&& str_contains( $german['/nino/http/response']['body'], 'Wir sind in Kürze wieder für Dich da.' ) === true && str_contains( $german['/nino/http/response']['body'], '[[' ) === false );
check( '...answered as the 503 it is, in the native locale', $german['/nino/http/response']['statusCode'] === 503 && $german['/nino/http/response']['locale'] === 'de_DE' && \Nino\Locales::getCurrentLocale( $moduleApp ) === 'de_DE' );
check( '...and nothing was written into the visitor\'s session', \Nino\Runtime::getSessionValue( $moduleApp, './nino/locales/current', 'unwritten' ) === 'unwritten' );

$moduleApp['/nino/locales/native'] = 'en_US';
$english = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $moduleApp, $english );
check( 'native en_US: the English page', str_contains( $english['/nino/http/response']['body'], '<html lang="en">' ) === true
	&& str_contains( $english['/nino/http/response']['body'], '<h1>Under maintenance</h1>' ) === true && str_contains( $english['/nino/http/response']['body'], 'We will be back shortly.' ) === true );

$moduleApp['/nino/locales/native'] = 'fr_FR';
$french = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $moduleApp, $french );
check( 'a native language the module has no page for gets the English one, in that locale', str_contains( $french['/nino/http/response']['body'], '<html lang="en">' ) === true
	&& str_contains( $french['/nino/http/response']['body'], '<h1>Under maintenance</h1>' ) === true && $french['/nino/http/response']['locale'] === 'fr_FR' );

// What becomes a path is checked first
$moduleApp['/nino/locales/native'] = '../../x';
\Nino\Locales::useLocale( $moduleApp, 'de_DE' );
$odd = maintenanceRequest( '/' );
check( 'a native locale that is no locale id is not made part of a path: the page of the current one is shown', \Nino\Modules\Maintenance::_prepare( $moduleApp, $odd ) === true
	&& str_contains( $odd['/nino/http/response']['body'], '<html lang="de">' ) === true );
$moduleApp['/nino/locales/native'] = 'de_DE';

// A project's own text wins over the module's default, and is escaped - the
// page is output and a title is something a person typed
\Nino\Html::addFills( $moduleApp, [ '/module/maintenance/page/title' => 'Back <b>soon</b> & "soon"', '/module/maintenance/page/text' => "Line one\nLine <two>" ], '*' );
$filledPage = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $moduleApp, $filledPage );
check( 'a project\'s /module/maintenance/page/title and /module/maintenance/page/text win over the module\'s defaults - on the module\'s page', str_contains( $filledPage['/nino/http/response']['body'], '<h1>Back &lt;b&gt;soon&lt;/b&gt; &amp; &quot;soon&quot;</h1>' ) === true
	&& str_contains( $filledPage['/nino/http/response']['body'], '<p>Line one<br />' ) === true && str_contains( $filledPage['/nino/http/response']['body'], 'Line &lt;two&gt;' ) === true );
check( '...and no markup of theirs reaches the page', str_contains( $filledPage['/nino/http/response']['body'], '<b>' ) === false && str_contains( $filledPage['/nino/http/response']['body'], '<two>' ) === false );

// A project's own template keeps the language of the route: it wears the
// site's design, and the site is in the visitor's language
$moduleApp['/nino/locales/native'] = 'de_DE';
\Nino\Filesystem::putFileContent( $moduleApp, '/templates/page-maintenance.tpl', '<main><h1>own</h1></main>' );
\Nino\Modules\Template::init( $moduleApp );
$ownRequest = maintenanceRequest( '/' );
$ownRequest['/nino/http/response']['locale'] = 'en_US';
\Nino\Modules\Maintenance::_prepare( $moduleApp, $ownRequest );
check( 'a project\'s page-maintenance.tpl wins, and is rendered in the language of the route, not forced to the native one', str_contains( $ownRequest['/nino/http/response']['body'], '<h1>own</h1>' ) === true
	&& $ownRequest['/nino/http/response']['locale'] === 'en_US' && \Nino\Locales::getCurrentLocale( $moduleApp ) === 'en_US' );
@unlink( \Nino\Filesystem::path( $moduleApp, '/templates/page-maintenance.tpl' ) );

// The banner a signed-in account gets while the site shows everybody else the
// maintenance page. /nino/http/output runs on the finished page, after every
// fill is replaced, so the link is built from the directory
$bannerApp = $moduleApp;
\Nino\Locales::useLocale( $bannerApp, 'de_DE' );
$bannerApp['/nino/dir'] = '/shop';
$bannerApp['./nino/auth/current'] = [ 'mail' => 'operator@example.com', 'perms' => [ '/_admin/maintenance/manage' ] ];
$pageHtml = '<!doctype html><html><head><title>x</title></head><body class="home"><main>page</main></body></html>';
$outputRequest = static function( string $uri = '/', mixed $body = null, array $header = [] ) use ( $pageHtml ): array {
	return [
		'/nino/http/request'	=> [ 'method' => 'GET', 'uri' => $uri ],
		'/nino/http/response'	=> [ 'statusCode' => 200, 'header' => $header, 'body' => $body ?? $pageHtml ],
	];
};

$bannered = $outputRequest();
\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $bannered );
$bannerBody = $bannered['/nino/http/response']['body'];
check( 'a signed-in account gets the banner once, right after the opening <body> - in the flow, not fixed', substr_count( $bannerBody, 'role="status"' ) === 1
	&& str_contains( $bannerBody, '<body class="home"><div role="status"' ) === true && str_contains( $bannerBody, 'position:fixed' ) === false && str_contains( $bannerBody, 'position:relative' ) === true && preg_match( '/z-index:\d+/', $bannerBody ) === 1 && str_contains( $bannerBody, '<main>page</main>' ) === true );
check( '...in the current language, with the words of the module\'s own text file', str_contains( $bannerBody, 'Die Wartung ist aktiv – Besucher sehen die Wartungsseite.' ) === true );
check( '...and with the account\'s permission, a link built from the project directory', str_contains( $bannerBody, '<a href="/shop/_admin#maintenance"' ) === true && str_contains( $bannerBody, '>Zur Wartung</a>' ) === true && str_contains( $bannerBody, '[[' ) === false );

\Nino\Locales::useLocale( $bannerApp, 'fr_FR' );
$frenchBanner = $outputRequest();
\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $frenchBanner );
check( 'a language without words of its own gets the English ones', str_contains( $frenchBanner['/nino/http/response']['body'], 'Maintenance is on – visitors see the maintenance page.' ) === true );
\Nino\Locales::useLocale( $bannerApp, 'de_DE' );

$noLink = $bannerApp;
$noLink['./nino/auth/current'] = [ 'mail' => 'editor@example.com', 'perms' => [ '/_admin/text/manage' ] ];
$editorBanner = $outputRequest();
\Nino\Modules\Maintenance::callbackOutput( $noLink, $editorBanner );
check( 'an account without the maintenance permission gets the banner and no link', str_contains( $editorBanner['/nino/http/response']['body'], 'Die Wartung ist aktiv' ) === true
	&& str_contains( $editorBanner['/nino/http/response']['body'], '<a ' ) === false );

$untouched = [];
$untouched['an anonymous visitor']				= [ $moduleApp, $outputRequest() ];
$untouched['/_admin']											= [ $bannerApp, $outputRequest( '/_admin' ) ];
$untouched['a screen below /_admin']			= [ $bannerApp, $outputRequest( '/_admin/config' ) ];
$untouched['an array body (a json answer)']	= [ $bannerApp, $outputRequest( '/', [ 'status' => 'ok' ] ) ];
$untouched['application/xml']							= [ $bannerApp, $outputRequest( '/sitemap.xml', $pageHtml, [ 'Content-Type' => 'application/xml' ] ) ];
$untouched['a fragment without </body>']	= [ $bannerApp, $outputRequest( '/', '<div>a fragment</div>' ) ];
$statusOff = $bannerApp;
$statusOff['/nino/maintenance/status'] = false;
$untouched['maintenance switched off']		= [ $statusOff, $outputRequest() ];
foreach( $untouched as $label => [ $app, $req ] ) {
	$before = $req['/nino/http/response']['body'];
	\Nino\Modules\Maintenance::callbackOutput( $app, $req );
	check( 'no banner for '. $label, $req['/nino/http/response']['body'] === $before );
}

// A '>' inside a quoted value of the <body> tag is no end of it
foreach( [ '<body data-x="a>b" class=\'c>d\'>' => '<body data-x="a>b" class=\'c>d\'><div role="status"', '<body class="home">' => '<body class="home"><div role="status"' ] as $bodyTag => $expectedStart ) {
	$quoted = $outputRequest( '/', '<!doctype html><html><head><title>x</title></head>'. $bodyTag. '<main>page</main></body></html>' );
	\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $quoted );
	check( 'the banner goes after the whole opening tag '. $bodyTag, str_contains( $quoted['/nino/http/response']['body'], $expectedStart ) === true && substr_count( $quoted['/nino/http/response']['body'], 'role="status"' ) === 1 );
}

$typed = $outputRequest( '/', $pageHtml, [ 'Content-Type' => 'text/html; charset=utf-8' ] );
\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $typed );
check( 'text/html with a charset is a page', str_contains( $typed['/nino/http/response']['body'], 'role="status"' ) === true );

$registered = [];
$hookApp = $appData;
$hookApp['./nino/callbacks'] = [];
\Nino\Modules\Maintenance::init( $hookApp );
foreach( $hookApp['./nino/callbacks']['/nino/http/output'][9] ?? [] as $hook )
	$registered[] = $hook;
check( 'init() registers the banner on /nino/http/output beside Cache\'s own callback (priority 9)', $registered === [ [ \Nino\Modules\Maintenance::class, 'callbackOutput' ] ] );

// The fallback page renders both fills - a project's own text (however it
// got there) always wins over the hardcoded default
$appData['/nino/maintenance/status'] = true;
\Nino\Html::addFills( $appData, [
	'/module/maintenance/page/title'	=> 'Back soon',
	'/module/maintenance/page/text'		=> 'Custom maintenance notice',
], '*' );

$filled = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $appData, $filled );
check( 'the fallback page renders both fills', str_contains( $filled['/nino/http/response']['body'], 'Back soon' ) === true
	&& str_contains( $filled['/nino/http/response']['body'], 'Custom maintenance notice' ) === true );

// A project's own template wears the site's header, and that header names
// the request fills \Nino\request() only adds after this callback round -
// so the module adds them itself before it renders
\Nino\Filesystem::putFileContent( $appData, '/templates/page-maintenance.tpl', '<main data-uri="[[/nino/http/request/uri]]" data-locale="[[/nino/http/response/locale]]"><h1>[[/module/maintenance/page/title]]</h1></main>' );
\Nino\Modules\Template::init( $appData );
$templated = maintenanceRequest( '/kontakt' );
\Nino\Modules\Maintenance::_prepare( $appData, $templated );
check( 'a project\'s page-maintenance.tpl renders with the request fills the kernel would have added', str_contains( $templated['/nino/http/response']['body'], 'data-uri="/kontakt"' ) === true
	&& str_contains( $templated['/nino/http/response']['body'], '<h1>Back soon</h1>' ) === true && str_contains( $templated['/nino/http/response']['body'], '[[/nino' ) === false );
@unlink( \Nino\Filesystem::path( $appData, '/templates/page-maintenance.tpl' ) );

$appData['/nino/maintenance/status'] = false;
$appData['/nino/cache/status'] = false;
unset( $appData['/nino/maintenance/retry'] );

echo "\n";

// --- Cleanup ------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
