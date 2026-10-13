<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	elements-smoke.php		Dependency-free smoke test for \Nino\Elements: insert, update and
 *											delete of element types and elements, an element reference holding
 *											several elements, sequential uris, queryElementValues(), the sort,
 *											offset and limit of queryElements(), seed() and the 'elements' key
 *											of an install unit, the [element], [elements] and [elementvalues]
 *											shortcodes that draw them, and the hook points of the accounts and
 *											of the elements. Runs against an isolated sandbox directory, never
 *											touches the real project data.
 *
 *	Usage: php tests/elements-smoke.php
 */

require __DIR__. '/harness.php';

/**
 *	Probe whether a Filesystem::lockFile()'d path's sidecar .lock file is
 *	currently free, via a second, independent file handle - flock() is per
 *	open-file-description, so this genuinely conflicts with a handle Nino
 *	itself is still holding open, the same way a second request would.
 *	Releases its own probe lock again before returning, so it never leaves
 *	the sidecar locked for whatever runs next.
 *
 *	The same probe is in runtime-smoke.php: two suites call it, and it is
 *	no helper the harness should carry.
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

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'elements' );
$sandbox = ninoSandboxDir( $appData );

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

// What a write refuses is one check, valueError(), and whoever validates
// ahead of a write asks it - the Translations import kept a copy that
// compared loosely, so '1' passed its whitelist of '01' and the kernel then
// refused the whole element. Strict lists, the (array) cast a hand-written
// model relies on, a whole number for a double, and a reference's rules
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

ninoWarnings();
$secondInsert = $writeElement->invokeArgs( null, [ &$appData, '/racetest/one', [ 'title' => 'Second' ], '*', false ] );
$raceWarnings = ninoWarnings();
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
// defect (see the submission tests in form-mail-smoke.php); this is the render side
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
ninoWarnings();
$seedOdd = $seedUnit;
$seedOdd['*']['three'] = [ 'order' => 'first', 'nofield' => 'x' ];
$seedOdd['de_DE']['three'] = [ 'title' => 'Drei', 'nofield' => 'y' ];
$seedOdd['*']['../four'] = [ 'order' => 4 ];
$seededOdd = \Nino\Elements::seed( $appData, '/seedtest', $seedOdd, [ 'de_DE' ] );
$seedWarnings = ninoWarnings();
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

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
