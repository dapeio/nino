<?php
declare(strict_types=1);

/**
 *	Nino
 *	search-smoke.php	Contract test for Modules\Search: configuration,
 *									activation, derived files, fuzzy ranking, locale
 *									selection, committed Elements writes, and the guarded
 *									/_admin rebuild action.
 *
 *	Usage: php tests/search-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';

$failures = 0;
$checks = 0;

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

function searchUris( array &$appData, string $type, string $query ): array {
	return array_column( \Nino\Modules\Search::getElements( $appData, $type, $query ), '.uri' );
}

function callSearchIndexAction( array &$appData ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Admin\Config::apiCreateSearchIndex( $appData, $request );
	return [
		$request['/nino/http/response']['statusCode'],
		$request['/nino/http/response']['body'],
	];
}

set_error_handler( static function(): bool { return true; } );

$sandbox = sys_get_temp_dir(). '/nino-search-smoke-'. uniqid();
mkdir( $sandbox, 0755, true );

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] = $sandbox;
$appData['./nino/filesystem/configpath'] = $sandbox. '/private';
$appData['./nino/filesystem/contentpath'] = $sandbox. '/private';
$appData['./nino/filesystem/privatepath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] = $sandbox. '/public';
$appData['./nino/locales/current'] = 'de_DE';
$appData['/nino/locales/native'] = 'de_DE';
$appData['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
$appData['/nino/modules'] = [];
$appData['/nino/elements/index'] = [
	// Both leading-slash forms are accepted. Invalid priorities, fields, and
	// types are ignored rather than leaking into a generated document.
	'articles' => [
		0 => 'title',
		1 => 'summary',
		2 => 'keywords',
		3 => 'author',
		4 => 'title',
	],
	'/ignored-fields' => [ 0 => 'missing-field' ],
	'/missing-type' => [ 0 => 'title' ],
	'not/a/type' => [ 0 => 'title' ],
];

echo "Sandbox: $sandbox\n\n";
echo "Search configuration, activation and derived index files\n";

$articleModel = [
	'title' => [ 'type' => 'string', 'locale' => true ],
	'summary' => [ 'type' => 'string', 'locale' => true ],
	'keywords' => [ 'type' => 'array' ],
	'author' => [ 'type' => 'string', 'locale' => true ],
	'internalCode' => [ 'type' => 'string' ],
];

check( 'the Search class autoloads on direct API use', class_exists( '\Nino\Modules\Search' ) === true );
check( 'the indexed element type can be created', \Nino\Elements::insertElementType( $appData, '/articles', $articleModel ) === true );
check( 'a valid type with no valid configured field can be created', \Nino\Elements::insertElementType( $appData, '/ignored-fields', [
	'title' => [ 'type' => 'string', 'locale' => true ],
] ) === true );
\Nino\Elements::insertElement( $appData, '/ignored-fields/one', [ 'title' => 'Not indexed' ], 'de_DE' );

$alpha = \Nino\Elements::insertElement( $appData, '/articles/alpha', [
	'title' => 'Müller Katalog',
	'summary' => '<strong>Leuchttürme</strong> &amp; klare Wege in Berlin',
	'keywords' => [ 'CMS', 'Leicht' ],
	'author' => 'Dape Team',
	'internalCode' => 'A-100',
], 'de_DE' );
check( 'an element can be written before Search is active', is_array( $alpha ) === true );

$alphaEnglish = \Nino\Elements::updateElement( $appData, '/articles/alpha', [
	'title' => 'Miller Catalog',
	'summary' => 'Lighthouses and clear routes in Berlin',
	'author' => 'Dape Team',
], 'en_US' );
check( 'the same element has an independent translated document', ( $alphaEnglish['title'] ?? null ) === 'Miller Catalog' );

$articleIndexPath = \Nino\Filesystem::path( $appData, '/data/index-articles.php' );
check( 'configuration alone does not create an index', is_file( $articleIndexPath ) === false );
check( 'a read with no index is empty', \Nino\Modules\Search::getElements( $appData, 'articles', 'catalog' ) === [] );
check( 'a read never self-heals the missing file', is_file( $articleIndexPath ) === false );

$appData['/nino/modules'] = [ '\Nino\Modules\Search' ];
\Nino\Modules::callModules( $appData, 'init' );
check( 'activating Search only registers its callback', is_file( $articleIndexPath ) === false );

\Nino\Elements::insertElement( $appData, '/articles/orbit-title', [
	'title' => 'Orbit',
	'summary' => 'A remote observatory',
	'keywords' => [],
	'internalCode' => 'O-100',
], 'de_DE' );
check( 'the first committed write after activation creates the configured type index', is_file( $articleIndexPath ) === true );

\Nino\Elements::insertElement( $appData, '/articles/orbit-summary', [
	'title' => 'Remote station',
	'summary' => 'Orbit',
	'keywords' => [],
	'internalCode' => 'O-200',
], 'de_DE' );

$index = @include $articleIndexPath;
check( 'the index is one plain PHP array grouped by locale', is_array( $index ) === true && array_keys( $index ) === [ 'de_DE', 'en_US' ] );
check( 'indexed text is normalized before it reaches disk', ( $index['de_DE']['/articles/alpha'][0] ?? null ) === 'mueller katalog' );
check( 'HTML, entities and nested scalar arrays are flattened safely',
	( $index['de_DE']['/articles/alpha'][1] ?? null ) === 'leuchttuerme klare wege in berlin'
	&& ( $index['de_DE']['/articles/alpha'][2] ?? null ) === 'cms leicht' );
check( 'all four priorities are stored while invalid priorities are omitted', array_keys( $index['de_DE']['/articles/alpha'] ?? [] ) === [ 0, 1, 2, 3 ] );

$created = \Nino\Modules\Search::createIndexes( $appData );
check( 'an explicit rebuild reports only valid configured types', $created === [ 'created' => 1, 'elements' => 3, 'failed' => [] ] );
$ignoredIndexPath = \Nino\Filesystem::path( $appData, '/data/index-ignored-fields.php' );
check( 'a type with no valid configured model field gets no index file', is_file( $ignoredIndexPath ) === false );

check( 'an unconfigured type can be created', \Nino\Elements::insertElementType( $appData, '/notes', [
	'title' => [ 'type' => 'string', 'locale' => true ],
] ) === true );
\Nino\Elements::insertElement( $appData, '/notes/one', [ 'title' => 'Merkzettel' ], 'de_DE' );
$notesIndexPath = \Nino\Filesystem::path( $appData, '/data/index-notes.php' );
check( 'writes to an unconfigured type do not create an index', is_file( $notesIndexPath ) === false );

echo "\nWeighted fuzzy search and locale selection\n";

check( 'exact terms return canonical Elements', searchUris( $appData, '/articles', 'Orbit' ) === [ '/articles/orbit-title', '/articles/orbit-summary' ] );
check( 'a hit in priority 0 ranks ahead of the same hit in priority 1', ( searchUris( $appData, 'articles', 'orbit' )[0] ?? null ) === '/articles/orbit-title' );
check( 'a small spelling error is matched fuzzily', searchUris( $appData, 'articles', 'katalg' ) === [ '/articles/alpha' ] );
check( 'German umlauts use their readable ae/oe/ue form', searchUris( $appData, 'articles', 'mueller' ) === [ '/articles/alpha' ] );
check( 'HTML text and decoded entities remain searchable as words', searchUris( $appData, 'articles', 'klare wege' ) === [ '/articles/alpha' ] );
check( 'array fields participate at their configured weight', searchUris( $appData, 'articles', 'cms' ) === [ '/articles/alpha' ] );
check( 'priority 3 remains searchable at its lower weight', searchUris( $appData, 'articles', 'dape' ) === [ '/articles/alpha' ] );
check( 'every query token must match', searchUris( $appData, 'articles', 'orbit nowhere' ) === [] );
check( 'empty and invalid searches return no result',
	\Nino\Modules\Search::getElements( $appData, 'articles', " \n " ) === []
	&& \Nino\Modules\Search::getElements( $appData, '../articles', 'orbit' ) === [] );

$appData['./nino/locales/current'] = 'en_US';
check( 'search reads only the current locale', searchUris( $appData, 'articles', 'lighthouses' ) === [ '/articles/alpha' ] );
check( 'a term present only in German is absent in English', searchUris( $appData, 'articles', 'leuchttuerme' ) === [] );
$appData['./nino/locales/current'] = 'de_DE';

\Nino\Elements::updateElement( $appData, '/articles/alpha', [ 'title' => 'Neuer Katalog' ], 'de_DE' );
check( 'a committed update refreshes the configured type', searchUris( $appData, 'articles', 'neuer' ) === [ '/articles/alpha' ] );
check( 'the previous indexed value disappears after that refresh', searchUris( $appData, 'articles', 'mueller' ) === [] );

\Nino\Elements::deleteElement( $appData, '/articles/orbit-title', '*' );
check( 'a committed delete removes the Element from search', searchUris( $appData, 'articles', 'orbit' ) === [ '/articles/orbit-summary' ] );

echo "\nRead-only failures and the guarded Admin rebuild action\n";

@unlink( $articleIndexPath );
clearstatcache( true, $articleIndexPath );
check( 'a deleted index produces an empty result', \Nino\Modules\Search::getElements( $appData, 'articles', 'neuer' ) === [] );
check( 'the search call leaves a deleted index deleted', is_file( $articleIndexPath ) === false );

$brokenIndex = '<?php return "not an index";';
file_put_contents( $articleIndexPath, $brokenIndex );
if( function_exists( 'opcache_invalidate' ) === true )
	opcache_invalidate( $articleIndexPath, true );
check( 'a non-array index is treated as empty', \Nino\Modules\Search::getElements( $appData, 'articles', 'neuer' ) === [] );
check( 'a read does not replace the malformed file', file_get_contents( $articleIndexPath ) === $brokenIndex );

\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', false );
[ $status ] = callSearchIndexAction( $appData );
check( 'the Admin rebuild action rejects an unauthenticated request', $status === 401 );
check( 'a rejected request does not touch an index', file_get_contents( $articleIndexPath ) === $brokenIndex );

// Add the second type to the configuration only now. The button contract is to
// rebuild every currently configured index, independently of which one is stale.
$appData['/nino/elements/index']['notes'] = [ 0 => 'title' ];
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );
[ $status, $body ] = callSearchIndexAction( $appData );
check( 'the authenticated Admin rebuild succeeds', $status === 200 );
check( 'one button press rebuilds every configured index', $body === [ 'created' => 2, 'elements' => 3, 'failed' => [] ] );
$rebuiltArticles = @include $articleIndexPath;
$rebuiltNotes = @include $notesIndexPath;
check( 'both derived files were recreated', is_array( $rebuiltArticles ) === true && is_array( $rebuiltNotes ) === true );
check( 'the second type can be searched immediately', searchUris( $appData, 'notes', 'merkzettel' ) === [ '/notes/one' ] );

$sentinel = '<?php return ["sentinel" => true];';
file_put_contents( $articleIndexPath, $sentinel );
file_put_contents( $notesIndexPath, $sentinel );
[ $status, $body ] = callSearchIndexAction( $appData );
check( 'every later button press rebuilds all indexes again', $status === 200 && ( $body['created'] ?? 0 ) === 2 );
check( 'a full rebuild replaces existing index content',
	file_get_contents( $articleIndexPath ) !== $sentinel
	&& file_get_contents( $notesIndexPath ) !== $sentinel );

$configuredIndexes = $appData['/nino/elements/index'];
$appData['/nino/elements/index'] = [];
[ $status, $body ] = callSearchIndexAction( $appData );
check( 'an empty configuration is a successful no-op', $status === 200 && $body === [ 'created' => 0, 'elements' => 0, 'failed' => [] ] );
$appData['/nino/elements/index'] = $configuredIndexes;

// Turn both target filenames into directories. /data and its lock directory
// stay usable for an Element commit, while file_put_contents() cannot open
// either index target. This does not depend on permission bits (the test
// process may run as root).
@unlink( $articleIndexPath );
@unlink( $notesIndexPath );
mkdir( $articleIndexPath );
mkdir( $notesIndexPath );

$committedDespiteIndexFailure = \Nino\Elements::updateElement(
	$appData,
	'/articles/alpha',
	[ 'title' => 'Persisted despite index failure' ],
	'de_DE'
);
check( 'an index failure cannot roll back the Element commit it follows',
	is_array( $committedDespiteIndexFailure ) === true
	&& ( $committedDespiteIndexFailure['title'] ?? null ) === 'Persisted despite index failure' );

$failed = \Nino\Modules\Search::createIndexes( $appData );
check( 'write failures are reported per configured type', $failed === [
	'created' => 0,
	'elements' => 0,
	'failed' => [ '/articles', '/notes' ],
] );
[ $status, $body ] = callSearchIndexAction( $appData );
check( 'the Admin action turns any index write failure into a 500',
	$status === 500
	&& str_contains( (string) ( $body['error'] ?? '' ), '/articles, /notes' ) === true );

restore_error_handler();
\Nino\Filesystem::removeDir( $sandbox );
unset( $_POST['data'] );

echo "\n$checks checks, $failures failed\n";
exit( $failures === 0 ? 0 : 1 );
