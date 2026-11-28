<?php
declare(strict_types=1);
/**
 *	Nino
 *	elements-api-smoke.php	The public functions of \Nino\Elements - read(), save(), remove() and rights() - in a process that loads
 *								_nino/Nino.php and never the shell of the workbench, as a feature outside the workbench calls them: every
 *								check the panel makes, each refusal with its status, sentence, code, params and field, the scoped rights
 *								of the account that is signed in, the callbacks of a write, and no notice of a writer that reaches the caller.
 *
 *	Usage: php tests/elements-api-smoke.php
 */

$root = dirname( __DIR__ );

defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', $root. '/features' );
require_once $root. '/_nino/Nino.php';

const PW = 'correct horse battery staple';

$ninoChecks		= 0;
$ninoFailures	= 0;
$ninoStarted	= microtime( true );

function check( string $label, bool $condition ): void {

	$GLOBALS['ninoChecks']++;

	if( $condition === true ) {
		echo "  ok  - $label\n";
		return;
	}

	$GLOBALS['ninoFailures']++;
	echo "FAIL  - $label\n";
}

/** a refusal as the functions answer it, every key */
function refusal( int $status, string $error, string $code, array $params = [], string $field = '', array $fields = [] ): array {
	return [ 'ok' => false, 'status' => $status, 'error' => $error, 'code' => $code, 'params' => $params, 'field' => $field, 'fields' => $fields ];
}

/** one account is signed in, and nobody for ''. What the sign-in itself says to the error handler is the account's, not a function's: left out of $leaked */
function signIn( array &$app, string $local ): void {

	$seen = count( $GLOBALS['leaked'] );

	\Nino\Auth::logoutUser( $app );

	if( $local !== '' && \Nino\Auth::loginUser( $app, $local. '@example.com', PW ) === false )
		throw new \RuntimeException( 'sign in failed for '. $local );

	array_splice( $GLOBALS['leaked'], $seen );
}

function veto( array &$appData, mixed &$data ): bool {
	return false;
}

function refuseOne( array &$appData, mixed &$data ): bool {
	return \Nino\Elements::refuse( $appData, 'title', 'the title is taken', 'news_title_taken' );
}

function refuseTwo( array &$appData, mixed &$data ): bool {
	\Nino\Elements::refuse( $appData, 'title', 'one', 'some_code' );
	return \Nino\Elements::refuse( $appData, 'nofield', 'general sentence', '' );
}

function refuseNamed( array &$appData, mixed &$data ): bool {
	\Nino\Elements::refuse( $appData, 'title', 'first sentence', '' );
	return \Nino\Elements::refuse( $appData, 'count', 'second sentence', '' );
}

function refuseGuard( array &$appData, mixed &$data ): bool {
	return false;
}

function noticing( array &$appData, mixed &$data ): void {
	trigger_error( 'a callback that talks', E_USER_NOTICE );
}

/** A listener that says why it refuses in a notice of its own */
function listening( array &$appData, mixed &$data ): bool {
	trigger_error( 'the listener says no', E_USER_WARNING );
	return false;
}

/** A veto that has php's warning silenced with @, as code that reads a file that may be missing does */
function silenced( array &$appData, mixed &$data ): bool {
	@file_get_contents( '/nonexistent-directory/never.json' );
	return false;
}

function warning( array &$appData, mixed &$data ): void {
	trigger_error( 'a callback that warns', E_USER_WARNING );
}

/** Puts a directory in the place of the type file while the write that is called for runs: the write is then refused by the file system whoever the process is */
function swapTypeFile( array &$appData, mixed &$data ): void {

	$file = $GLOBALS['swapFile'];

	if( is_file( $file ) === true ) {
		unlink( $file );
		mkdir( $file );
	}
}

/** What a call answers and the notices it raised, caught here so that none reaches the handler of the suite */
function caught( callable $call ): array {

	$notices = [];
	set_error_handler( static function( int $errno, string $errstr ) use ( &$notices ): bool { $notices[] = $errstr; return true; } );

	try {
		$result = $call();
	}
	finally {
		restore_error_handler();
	}

	return [ $result, $notices ];
}

// ---------------------------------------------------------------------------------------------------------------------------------
// the project
// ---------------------------------------------------------------------------------------------------------------------------------

check( 'the shell of the workbench is not loaded', class_exists( '\Nino\Admin\Admin', false ) === false );

do {
	$sandbox = sys_get_temp_dir(). '/nino-elements-api-smoke-'. bin2hex( random_bytes( 6 ) );
} while( @mkdir( $sandbox, 0755, true ) === false && is_dir( $sandbox ) === true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];

$app = [ './nino/uid' => $sandbox, './nino/test/dir' => $sandbox ];
\Nino\AppData::prepare( $app );

$app['./nino/filesystem/path']				= $sandbox;
$app['./nino/filesystem/configpath']	= $sandbox. '/private';
$app['./nino/filesystem/contentpath']	= $sandbox. '/private';
$app['./nino/filesystem/publicpath']	= $sandbox. '/public';
$app['./nino/locales/current']				= 'de_DE';
$app['/nino/locales/native']					= 'de_DE';
$app['/nino/locales/available']				= [ 'de_DE', 'en_US' ];
$app['/nino/dir']											= '';
$app['/nino/modules']									= [];
$app['/nino/install/completed']				= true;
$app['/nino/install/texts']						= \Nino\VERSION;
$app['/nino/auth/user']								= [];
$app['/nino/auth/roles']							= [];

\Nino\AppData::writeContentData( $app, [ '/nino/install/completed', '/nino/install/texts', '/nino/modules' ] );

// admin: every right. door: the door of the panel alone - not described in detail, so every action. described: the door and one field of one
// type - described in detail, so nothing else. blanket: the door and the blanket over the elements - not described in detail either
foreach( [
	'admin'			=> [ '/*' ],
	'door'			=> [ '/_admin/elements/manage' ],
	'described'	=> [ '/_admin/elements/manage', '/_admin/elements/news/update/title' ],
	'blanket'		=> [ '/_admin/elements/manage', '/_admin/elements/*' ],
	'onetype'		=> [ '/_admin/elements/manage', '/_admin/elements/news/*' ],
	'inserter'	=> [ '/_admin/elements/manage', '/_admin/elements/shots/insert' ],
	'deleter'		=> [ '/_admin/elements/manage', '/_admin/elements/shots/delete' ],
] as $local => $perms )
	\Nino\Auth::insertUser( $app, $local. '@example.com', PW, $perms );

$model = [
	'title'		=> [ 'type' => 'string', 'required' => true ],
	'body'		=> [ 'type' => 'string', 'locale' => true ],
	'count'		=> [ 'type' => 'integer' ],
	'flag'		=> [ 'type' => 'boolean' ],
	'kind'		=> [ 'type' => 'string', 'whitelist' => [ 'a', 'b' ] ],
	'ref'			=> [ 'type' => 'element', 'elementType' => 'news' ],
	'refs'		=> [ 'type' => 'element', 'elementType' => 'news', 'multiple' => 2 ],
	'pic'			=> [ 'type' => 'image', 'width' => 10, 'height' => 10 ],
	'html'		=> [ 'type' => 'string', 'html' => true ],
	'guarded'	=> [ 'type' => 'string', 'callbacks' => [ '/probe/guard' ] ],
];

\Nino\Elements::insertElementType( $app, '/news', $model );
\Nino\Elements::insertElementType( $app, '/gallery', [ 'title' => [ 'type' => 'string' ] ], true );
\Nino\Elements::insertElement( $app, '/news/first', [ 'title' => 'First', 'body' => 'Erster', 'count' => 1 ], 'de_DE' );
\Nino\Elements::updateElement( $app, '/news/first', [ 'body' => 'First body' ], 'en_US' );

$typeFile = $sandbox. '/private/elements/news.php';

// What reaches this handler is a notice that a function let through
$leaked = [];
set_error_handler( static function( int $errno, string $errstr ): bool { $GLOBALS['leaked'][] = $errstr; return true; } );

signIn( $app, 'admin' );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "read() - the element as a form reads it, and its refusals\n";
// ---------------------------------------------------------------------------------------------------------------------------------

$read = \Nino\Elements::read( $app, 'news', 'first' );
check( 'an element is read as the panel answers it, with ok in front', array_keys( $read ) === [ 'ok', 'global', 'locales', 'raw' ] && $read['ok'] === true );
check( '...the global fields with every field of the model, a field that holds nothing as null', $read['global'] === [ 'title' => 'First', 'count' => 1, 'flag' => null, 'kind' => null, 'ref' => null, 'refs' => null, 'pic' => null, 'html' => null, 'guarded' => null ] );
check( '...each language of the site with the fields it writes', $read['locales'] === [ 'de_DE' => [ 'body' => 'Erster' ], 'en_US' => [ 'body' => 'First body' ] ] );
check( '...and every bucket of the type file that holds it', $read['raw'] === [ '*' => [ 'title' => 'First', 'count' => 1 ], 'de_DE' => [ 'body' => 'Erster' ], 'en_US' => [ 'body' => 'First body' ] ] );

check( 'a type that is none is 404 elements_type_unknown', \Nino\Elements::read( $app, 'nope', 'first' ) === refusal( 404, 'unknown type or element', 'elements_type_unknown' ) );
check( 'an empty uri is 404 elements_uri_invalid', \Nino\Elements::read( $app, 'news', '' ) === refusal( 404, 'unknown type or element', 'elements_uri_invalid' ) );
check( 'a uri with a slash is 404 elements_uri_invalid', \Nino\Elements::read( $app, 'news', 'a/b' ) === refusal( 404, 'unknown type or element', 'elements_uri_invalid' ) );
check( 'a type that is none and an empty uri: the type is the reason, the order of the panel\'s condition', \Nino\Elements::read( $app, 'nope', '' ) === refusal( 404, 'unknown type or element', 'elements_type_unknown' ) );
check( 'an element no language of the site holds is 404 elements_unknown', \Nino\Elements::read( $app, 'news', 'ghost' ) === refusal( 404, 'element not found', 'elements_unknown' ) );
check( 'reading asks no right: an account described in detail reads', ( static function() use ( &$app ): bool { signIn( $app, 'described' ); $ok = \Nino\Elements::read( $app, 'news', 'first' )['ok'] === true; signIn( $app, 'admin' ); return $ok; } )() );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() - a write with every check of the panel\n";
// ---------------------------------------------------------------------------------------------------------------------------------

$saved = \Nino\Elements::save( $app, 'news', 'second', 'de_DE', [ 'title' => 'Second', 'body' => 'Zweiter', 'count' => 2 ], [ 'new' => true ] );
check( 'a new element is saved: ok, the element as it reads, nextUri empty for a type that names its elements', array_keys( $saved ) === [ 'ok', 'element', 'nextUri' ] && $saved['ok'] === true && $saved['nextUri'] === '' && $saved['element']['title'] === 'Second' && $saved['element']['.uri'] === '/news/second' && $saved['element']['.locale'] === 'de_DE' );
check( '...and it is in the type file', ( \Nino\Elements::getElement( $app, '/news/second', 'de_DE' )['body'] ?? null ) === 'Zweiter' );

$saved = \Nino\Elements::save( $app, 'news', 'second', 'de_DE', [ 'title' => 'Second changed', 'count' => 3 ] );
check( 'an update writes the keys it names alone', $saved['ok'] === true && $saved['element']['title'] === 'Second changed' && $saved['element']['count'] === 3 && $saved['element']['body'] === 'Zweiter' );

$saved = \Nino\Elements::save( $app, 'news', 'second', 'en_US', [ 'body' => 'Second body' ] );
check( 'a language field is written in its language alone', $saved['ok'] === true && ( \Nino\Elements::getElement( $app, '/news/second', 'en_US' )['body'] ?? null ) === 'Second body' && ( \Nino\Elements::getElement( $app, '/news/second', 'de_DE' )['body'] ?? null ) === 'Zweiter' );

$numbered = \Nino\Elements::save( $app, 'gallery', '', 'de_DE', [ 'title' => 'One' ], [ 'new' => true ] );
check( 'an insert into a numbered type needs no uri: the kernel assigns it and says the number that is next', $numbered['ok'] === true && $numbered['element']['.uri'] === '/gallery/'. \Nino\Elements::autoincrementUri( 1 ) && $numbered['nextUri'] === \Nino\Elements::autoincrementUri( 2 ) );
check( '...an update of a numbered type names its element like any other', \Nino\Elements::save( $app, 'gallery', '', 'de_DE', [ 'title' => 'x' ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );

$saved = \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'html' => '<script>x</script><b>y</b><p onclick="z">p</p>' ] );
check( 'the html of a field with html is sanitised before it is written', $saved['ok'] === true && ( \Nino\Elements::getElement( $app, '/news/first', 'de_DE' )['html'] ?? null ) === 'y p' );

$saved = \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [] );
check( 'a save with no field writes nothing new and is ok', $saved['ok'] === true );

$saved = \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'pic' => '' ] );
check( 'an image field may be emptied', $saved['ok'] === true );

check( 'an image field set to a name it does not hold is refused: elements_image_upload with the field and the param', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'pic' => 'elements/news/x.png' ] ) === refusal( 400, 'an image is set by uploading it: pic', 'elements_image_upload', [ 'pic' ], 'pic' ) );
check( '...and nothing is written of it', ( \Nino\Elements::getElement( $app, '/news/first', 'de_DE' )['pic'] ?? '' ) === '' );

// The checks of the panel, in the order of the panel's condition
check( 'a type that is none: 400 elements_type_unknown', \Nino\Elements::save( $app, 'nope', 'first', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_type_unknown' ) );
check( 'an empty uri of an update: elements_uri_invalid', \Nino\Elements::save( $app, 'news', '', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( 'an empty uri of an insert into a type that names its elements: elements_uri_invalid', \Nino\Elements::save( $app, 'news', '', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( 'a uri with a slash: elements_uri_invalid', \Nino\Elements::save( $app, 'news', 'a/b', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( 'a new uri that is no slug: elements_uri_invalid', \Nino\Elements::save( $app, 'news', 'Bad uri!', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( 'a uri that is no slug is no refusal of an update: reading and updating never re-validate a name written by hand', \Nino\Elements::save( $app, 'news', 'Bad uri!', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'Element \'/news/Bad uri!\' does not exist.', 'elements_unknown' ) );
check( 'a locale the site does not serve: elements_locale_invalid', \Nino\Elements::save( $app, 'news', 'first', 'fr_FR', [ 'title' => 'X' ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_locale_invalid' ) );
check( 'no locale: elements_locale_invalid', \Nino\Elements::save( $app, 'news', 'first', '', [ 'title' => 'X' ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_locale_invalid' ) );
check( 'a request with three mistakes is refused for the type: type, then uri, then locale', \Nino\Elements::save( $app, 'nope', '', 'fr_FR', [] ) === refusal( 400, 'invalid type, uri or locale', 'elements_type_unknown' ) );
check( '...with the uri and the locale wrong, for the uri', \Nino\Elements::save( $app, 'news', 'a/b', 'fr_FR', [] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( 'a type that is none is refused before any right is asked', ( static function() use ( &$app ): bool { signIn( $app, 'described' ); $r = \Nino\Elements::save( $app, 'nope', 'x', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ); signIn( $app, 'admin' ); return $r === refusal( 400, 'invalid type, uri or locale', 'elements_type_unknown' ); } )() );

check( 'a value of the wrong type: elements_wrong_type with the key as param and field', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'count' => 'abc' ] ) === refusal( 400, 'Wrong var type \'count\' in \'/news/first\'. \'integer\' required, \'string\' given.', 'elements_wrong_type', [ 'count' ], 'count' ) );
check( 'a required key that is missing: elements_missing_key', \Nino\Elements::save( $app, 'news', 'third', 'de_DE', [ 'body' => 'x' ], [ 'new' => true ] ) === refusal( 400, 'Missing required element key \'title\' in \'/news/third\'.', 'elements_missing_key', [ 'title' ], 'title' ) );
check( 'a required key that is emptied: elements_missing_key', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => '' ] ) === refusal( 400, 'Missing required element key \'title\' in \'/news/first\'.', 'elements_missing_key', [ 'title' ], 'title' ) );
check( 'a value that is not on the whitelist: elements_value_refused', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'kind' => 'z' ] ) === refusal( 400, 'Element value \'kind\' is not whitelisted.', 'elements_value_refused', [ 'kind' ], 'kind' ) );
check( 'a reference into another type: elements_reference', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'ref' => '/other/x' ] ) === refusal( 400, 'Element reference \'ref\' in \'/news/first\' must point into \'news\', got \'/other/x\'.', 'elements_reference', [ 'ref' ], 'ref' ) );
check( 'a list that names one element twice: elements_reference', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'refs' => [ '/news/first', '/news/first' ] ] ) === refusal( 400, 'Element reference \'refs\' in \'/news/first\' lists \'/news/first\' twice.', 'elements_reference', [ 'refs' ], 'refs' ) );
check( 'a list of more entries than the field takes: elements_reference', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'refs' => [ '/news/a', '/news/b', '/news/c' ] ] ) === refusal( 400, 'Element reference \'refs\' in \'/news/first\' holds 3 entries, at most 2 allowed.', 'elements_reference', [ 'refs' ], 'refs' ) );
check( 'an element made twice: elements_exists', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'Element \'/news/first\' already exists.', 'elements_exists' ) );
check( 'an element that is none updated: elements_unknown', \Nino\Elements::save( $app, 'news', 'ghost', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'Element \'/news/ghost\' does not exist.', 'elements_unknown' ) );
check( 'whether the element exists is decided before its values: an update of an element that is none with a value of the wrong type is elements_unknown', \Nino\Elements::save( $app, 'news', 'ghost', 'de_DE', [ 'count' => 'abc' ] ) === refusal( 400, 'Element \'/news/ghost\' does not exist.', 'elements_unknown' ) );
check( '...an insert of an element that exists with a required key missing is elements_exists', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'count' => 2 ], [ 'new' => true ] ) === refusal( 400, 'Element \'/news/first\' already exists.', 'elements_exists' ) );
check( 'the values are judged field by field in the order of the model: title (required, missing) is before count (wrong type)', \Nino\Elements::save( $app, 'news', 'orderly', 'de_DE', [ 'count' => 'abc' ], [ 'new' => true ] ) === refusal( 400, 'Missing required element key \'title\' in \'/news/orderly\'.', 'elements_missing_key', [ 'title' ], 'title' ) );
check( '...and a wrong type of title is before a value off the whitelist of kind', \Nino\Elements::save( $app, 'news', 'orderly', 'de_DE', [ 'title' => 5, 'kind' => 'z' ], [ 'new' => true ] ) === refusal( 400, 'Wrong var type \'title\' in \'/news/orderly\'. \'string\' required, \'integer\' given.', 'elements_wrong_type', [ 'title' ], 'title' ) );
check( 'a field the model does not have is left out, as the kernel leaves it', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'zzz' => 'x' ] )['ok'] === true );
check( 'fields with integer keys are no refusal of a right and no crash', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 0 => 'x' ] )['ok'] === true );
check( 'a save refused for a value writes none of the others: the file is as it was', ( static function() use ( &$app, $typeFile ): bool {
	$before = (string) file_get_contents( $typeFile );
	$r = \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'Changed', 'count' => 'abc' ] );
	return $r['ok'] === false && (string) file_get_contents( $typeFile ) === $before;
} )() );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() - the callbacks of a write\n";
// ---------------------------------------------------------------------------------------------------------------------------------

\Nino\Callbacks::registerCallback( $app, '/probe/guard', 'refuseGuard' );

check( 'a field callback that says no is a veto with a notice of the kernel: 400 elements_vetoed with that sentence', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'guarded' => 'x' ] ) === refusal( 400, 'Callback \'/probe/guard\' returns an error.', 'elements_vetoed' ) );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/news/update', 'veto' );
check( 'a veto of the update callback that names nothing: 400 \'save failed\' elements_vetoed', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'save failed', 'elements_vetoed' ) );
unset( $app['./nino/callbacks']['/nino/elements/news/update'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/news/update', 'refuseOne' );
check( 'Elements::refuse() with one sentence: the sentence, its own code, the field, and the list of fields', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'the title is taken', 'news_title_taken', [], 'title', [ [ 'field' => 'title', 'error' => 'the title is taken', 'code' => 'news_title_taken' ] ] ) );
unset( $app['./nino/callbacks']['/nino/elements/news/update'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/news/update', 'refuseTwo' );
check( 'two sentences, one for a field and one for none: the general one is the sentence, no code (two sentences have none), the field of the first named', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'general sentence', '', [], 'title', [ [ 'field' => 'title', 'error' => 'one', 'code' => 'some_code' ] ] ) );
unset( $app['./nino/callbacks']['/nino/elements/news/update'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/news/update', 'refuseNamed' );
check( 'two sentences that both name a field: the first is the sentence and the field, both are listed in their order', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'first sentence', '', [], 'title', [ [ 'field' => 'title', 'error' => 'first sentence', 'code' => '' ], [ 'field' => 'count', 'error' => 'second sentence', 'code' => '' ] ] ) );
unset( $app['./nino/callbacks']['/nino/elements/news/update'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/news/update', 'noticing' );
$before = count( $leaked );
$saved = \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'Noticed' ] );
check( 'a callback that raises a notice of its own is caught like the notices of the writer: the save goes through and the caller sees none', $saved['ok'] === true && count( $leaked ) === $before );
unset( $app['./nino/callbacks']['/nino/elements/news/update'] );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() and remove() - the scoped rights of the account that is signed in\n";
// ---------------------------------------------------------------------------------------------------------------------------------

signIn( $app, 'described' );
check( 'an account described in detail, with no right to insert: 403 elements_insert_not_allowed', \Nino\Elements::save( $app, 'news', 'fourth', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 403, 'not allowed to add elements of this type', 'elements_insert_not_allowed' ) );
check( '...the right to insert is asked before the fields', \Nino\Elements::save( $app, 'news', 'fourth', 'de_DE', [ 'count' => 5 ], [ 'new' => true ] ) === refusal( 403, 'not allowed to add elements of this type', 'elements_insert_not_allowed' ) );
check( '...a field it may not change: 403 elements_fields_not_allowed with the field as param and field', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X', 'count' => 2 ] ) === refusal( 403, 'not allowed to change the field(s) count', 'elements_fields_not_allowed', [ 'count' ], 'count' ) );
check( '...two fields it may not change are named at once, the first is the field', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'count' => 2, 'title' => 'X', 'flag' => true ] ) === refusal( 403, 'not allowed to change the field(s) count, flag', 'elements_fields_not_allowed', [ 'count, flag' ], 'count' ) );
check( '...nothing of a refused save is written', ( \Nino\Elements::getElement( $app, '/news/first', 'de_DE' )['count'] ?? null ) === 1 );
check( '...a field it may change is saved', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'By the described' ] )['ok'] === true );
check( '...remove is refused: 403 elements_delete_not_allowed', \Nino\Elements::remove( $app, 'news', 'first' ) === refusal( 403, 'not allowed to delete elements of this type', 'elements_delete_not_allowed' ) );
check( '...and the element is still there', \Nino\Elements::getElement( $app, '/news/first', 'de_DE' ) !== false );
check( '...a request with a type that is none is refused for the type, not for the right', \Nino\Elements::remove( $app, 'nope', 'first' ) === refusal( 400, 'invalid type or uri', 'elements_type_unknown' ) );

foreach( [ 'door' => 'an account that holds the door alone', 'blanket' => 'an account with the blanket over the elements', 'onetype' => 'an account with the blanket over the type', 'admin' => 'an account with every right' ] as $local => $who ) {
	signIn( $app, $local );
	$id = 'by'. $local;
	check( $who. ' may insert, change any field and delete', \Nino\Elements::save( $app, 'news', $id, 'de_DE', [ 'title' => 'T', 'count' => 5 ], [ 'new' => true ] )['ok'] === true && \Nino\Elements::save( $app, 'news', $id, 'de_DE', [ 'count' => 6 ] )['ok'] === true && \Nino\Elements::remove( $app, 'news', $id ) === [ 'ok' => true ] && \Nino\Elements::getElement( $app, '/news/'. $id, 'de_DE' ) === false );
}

signIn( $app, '' );
check( 'with no account signed in no scoped right narrows anything: the functions check what they check and the caller guards', \Nino\Elements::save( $app, 'news', 'visitor', 'de_DE', [ 'title' => 'V', 'count' => 1 ], [ 'new' => true ] )['ok'] === true && \Nino\Elements::remove( $app, 'news', 'visitor' ) === [ 'ok' => true ] );
signIn( $app, 'admin' );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "remove() - the checks of the panel and what a delete takes with it\n";
// ---------------------------------------------------------------------------------------------------------------------------------

check( 'a type that is none: 400 elements_type_unknown', \Nino\Elements::remove( $app, 'nope', 'first' ) === refusal( 400, 'invalid type or uri', 'elements_type_unknown' ) );
check( 'an empty uri: 400 elements_uri_invalid', \Nino\Elements::remove( $app, 'news', '' ) === refusal( 400, 'invalid type or uri', 'elements_uri_invalid' ) );
check( 'a uri with a slash: 400 elements_uri_invalid', \Nino\Elements::remove( $app, 'news', 'a/b' ) === refusal( 400, 'invalid type or uri', 'elements_uri_invalid' ) );
check( 'an element that does not exist is deleted all the same: ok, the kernel\'s delete is idempotent', \Nino\Elements::remove( $app, 'news', 'ghost' ) === [ 'ok' => true ] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/delete/news', 'veto' );
check( 'a veto of the delete callback: 409 \'delete vetoed\' elements_vetoed, and the element stays', \Nino\Elements::remove( $app, 'news', 'first' ) === refusal( 409, 'delete vetoed', 'elements_vetoed' ) && \Nino\Elements::getElement( $app, '/news/first', 'de_DE' ) !== false );
unset( $app['./nino/callbacks']['/nino/elements/delete/news'] );

// The pictures of the element: the file of the type's own upload goes with it, a name that lies elsewhere stays, and so does a file another element names
$images = $sandbox. '/public/images';
mkdir( $images. '/elements/news', 0755, true );
mkdir( $images. '/elsewhere', 0755, true );
foreach( [ 'elements/news/owned.10x10.png', 'elements/news/shared.10x10.png', 'elsewhere/own.png' ] as $file )
	file_put_contents( $images. '/'. $file, 'x' );
\Nino\Elements::insertElement( $app, '/news/pictured', [ 'title' => 'P', 'pic' => 'elements/news/owned.10x10.png' ], 'de_DE' );
\Nino\Elements::insertElement( $app, '/news/holder', [ 'title' => 'H', 'pic' => 'elements/news/shared.10x10.png' ], 'de_DE' );
\Nino\Elements::insertElement( $app, '/news/pictured2', [ 'title' => 'P2', 'pic' => 'elements/news/shared.10x10.png' ], 'de_DE' );
\Nino\Elements::insertElement( $app, '/news/pictured3', [ 'title' => 'P3', 'pic' => 'elsewhere/own.png' ], 'de_DE' );
check( 'a removed element takes the picture of its own upload with it', \Nino\Elements::remove( $app, 'news', 'pictured' ) === [ 'ok' => true ] && is_file( $images. '/elements/news/owned.10x10.png' ) === false );
check( '...not a picture another element still names', \Nino\Elements::remove( $app, 'news', 'pictured2' ) === [ 'ok' => true ] && is_file( $images. '/elements/news/shared.10x10.png' ) === true );
check( '...and not a file outside the folder of the type\'s uploads', \Nino\Elements::remove( $app, 'news', 'pictured3' ) === [ 'ok' => true ] && is_file( $images. '/elsewhere/own.png' ) === true );

$removed = \Nino\Elements::remove( $app, 'news', 'second' );
check( 'an element is removed in every language: ok, and nothing of it is left', $removed === [ 'ok' => true ] && \Nino\Elements::getElement( $app, '/news/second', 'de_DE' ) === false && \Nino\Elements::getElement( $app, '/news/second', 'en_US' ) === false && \Nino\Elements::read( $app, 'news', 'second' )['code'] === 'elements_unknown' );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "rights() - what the account may do with a type\n";
// ---------------------------------------------------------------------------------------------------------------------------------

$fields = array_keys( $model );
$all = array_fill_keys( $fields, true );

signIn( $app, 'admin' );
check( 'an account with every right may everything', \Nino\Elements::rights( $app, 'news' ) === [ 'insert' => true, 'delete' => true, 'update' => $all ] );
signIn( $app, 'door' );
check( 'an account that holds the door alone is not described in detail: it may everything', \Nino\Elements::rights( $app, 'news' ) === [ 'insert' => true, 'delete' => true, 'update' => $all ] );
signIn( $app, 'blanket' );
check( 'an account with the blanket may everything', \Nino\Elements::rights( $app, 'news' ) === [ 'insert' => true, 'delete' => true, 'update' => $all ] );
signIn( $app, 'described' );
check( 'an account described in detail may what it names: one field, no insert, no delete', \Nino\Elements::rights( $app, 'news' ) === [ 'insert' => false, 'delete' => false, 'update' => array_replace( array_fill_keys( $fields, false ), [ 'title' => true ] ) ] );
check( '...and nothing of a type it does not name', \Nino\Elements::rights( $app, 'gallery' ) === [ 'insert' => false, 'delete' => false, 'update' => [ 'title' => false ] ] );
signIn( $app, '' );
check( 'with no account signed in no scoped right narrows anything', \Nino\Elements::rights( $app, 'news' ) === [ 'insert' => true, 'delete' => true, 'update' => $all ] );
signIn( $app, 'admin' );
check( 'a type that is none has nothing to allow', \Nino\Elements::rights( $app, 'nope' ) === [ 'insert' => false, 'delete' => false, 'update' => [] ] );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() and remove() - the return value of the write decides, not the notices that fall while it runs\n";
// ---------------------------------------------------------------------------------------------------------------------------------

foreach( [ 'a callback of the write that raises a warning' => [ '/nino/elements/news/update', '/nino/elements/delete/news' ], 'a hook of /nino/elements/committed that raises a warning' => [ '/nino/elements/committed', '/nino/elements/committed' ] ] as $what => [ $onSave, $onDelete ] ) {

	\Nino\Callbacks::registerCallback( $app, $onSave, 'warning' );
	$before = count( $leaked );
	$saved = \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'count' => 7 ] );
	check( 'save with '. $what. ': ok, and the element is written', $saved['ok'] === true && $saved['element']['count'] === 7 && ( \Nino\Elements::getElement( $app, '/news/first', 'de_DE' )['count'] ?? null ) === 7 && count( $leaked ) === $before );
	unset( $app['./nino/callbacks'][$onSave] );

	\Nino\Elements::insertElement( $app, '/news/warned', [ 'title' => 'W', 'pic' => 'elements/news/warned.10x10.png' ], 'de_DE' );
	file_put_contents( $images. '/elements/news/warned.10x10.png', 'x' );
	\Nino\Callbacks::registerCallback( $app, $onDelete, 'warning' );
	$removed = \Nino\Elements::remove( $app, 'news', 'warned' );
	check( 'remove with '. $what. ': ok, the element is gone in every language and its picture with it', $removed === [ 'ok' => true ] && \Nino\Elements::getElement( $app, '/news/warned', 'de_DE' ) === false && is_file( $images. '/elements/news/warned.10x10.png' ) === false && count( $leaked ) === $before );
	unset( $app['./nino/callbacks'][$onDelete] );
}
\Nino\Elements::updateElement( $app, '/news/first', [ 'count' => 1 ], 'de_DE' );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() and remove() - a type file that cannot be locked or written is elements_save_failed\n";
// ---------------------------------------------------------------------------------------------------------------------------------

caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElementType( $app, '/locked', [ 'title' => [ 'type' => 'string' ] ] ); } );
\Nino\Elements::insertElement( $app, '/locked/one', [ 'title' => 'One' ], 'de_DE' );
$lockedFile = $sandbox. '/private/elements/locked.php';
$lockedWhole = (string) file_get_contents( $lockedFile );
$GLOBALS['swapFile'] = $lockedFile;

$mend = static function() use ( &$app, $lockedFile, $lockedWhole ): void {
	if( is_dir( $lockedFile ) === true )
		rmdir( $lockedFile );
	file_put_contents( $lockedFile, $lockedWhole );
	unset( $app['./nino/elements/cache'] );
};

\Nino\Callbacks::registerCallback( $app, '/nino/elements/locked/insert', 'swapTypeFile' );
check( 'save of a new element when the write fails: 400 elements_save_failed with the sentence of the kernel', \Nino\Elements::save( $app, 'locked', 'two', 'de_DE', [ 'title' => 'Two' ], [ 'new' => true ] ) === refusal( 400, 'Element \'/locked/two\' could not be written.', 'elements_save_failed' ) );
$mend();
unset( $app['./nino/callbacks']['/nino/elements/locked/insert'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/locked/update', 'swapTypeFile' );
check( 'save of an update when the write fails: 400 elements_save_failed', \Nino\Elements::save( $app, 'locked', 'one', 'de_DE', [ 'title' => 'Changed' ] ) === refusal( 400, 'Element \'/locked/one\' could not be written.', 'elements_save_failed' ) );
$mend();
unset( $app['./nino/callbacks']['/nino/elements/locked/update'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/delete/locked', 'swapTypeFile' );
check( 'remove when the write fails: 409 elements_save_failed', \Nino\Elements::remove( $app, 'locked', 'one' ) === refusal( 409, 'Element \'/locked/one\' could not be written.', 'elements_save_failed' ) );
$mend();
unset( $app['./nino/callbacks']['/nino/elements/delete/locked'] );
check( '...and the element is there once the file is back', ( \Nino\Elements::getElement( $app, '/locked/one', 'de_DE' )['title'] ?? null ) === 'One' );

// A directory in the place of the lock file: it cannot be opened, whoever the process is
$known = glob( $sandbox. '/private/data/.locks/*.lock' ) ?: [];
caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElementType( $app, '/locky', [ 'title' => [ 'type' => 'string' ] ] ); } );
\Nino\Elements::insertElement( $app, '/locky/one', [ 'title' => 'One' ], 'de_DE' );
$lockFiles = array_values( array_diff( glob( $sandbox. '/private/data/.locks/*.lock' ) ?: [], $known ) );
check( 'the type file has a lock file of its own', $lockFiles !== [] );
$app['./nino/filesystem/locks'] = [];
foreach( $lockFiles as $lockFile ) {
	unlink( $lockFile );
	mkdir( $lockFile );
}
check( 'save of a new element when the type file cannot be locked: 400 elements_save_failed', \Nino\Elements::save( $app, 'locky', 'two', 'de_DE', [ 'title' => 'Two' ], [ 'new' => true ] ) === refusal( 400, 'Element type \'/locky\' could not be locked for writing.', 'elements_save_failed' ) );
check( 'save of an update when the type file cannot be locked: 400 elements_save_failed', \Nino\Elements::save( $app, 'locky', 'one', 'de_DE', [ 'title' => 'Changed' ] ) === refusal( 400, 'Element type \'/locky\' could not be locked for writing.', 'elements_save_failed' ) );
check( 'remove when the type file cannot be locked: 409 elements_save_failed', \Nino\Elements::remove( $app, 'locky', 'one' ) === refusal( 409, 'Element type \'/locky\' could not be locked for writing.', 'elements_save_failed' ) );
foreach( $lockFiles as $lockFile )
	rmdir( $lockFile );
check( '...and nothing of it was written: the element is as it was, and it can be changed once the lock can be had', ( \Nino\Elements::getElement( $app, '/locky/one', 'de_DE' )['title'] ?? null ) === 'One' && \Nino\Elements::save( $app, 'locky', 'one', 'de_DE', [ 'title' => 'Changed' ] )['ok'] === true );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() and remove() - the order of the checks and the rights, as the panel always had them\n";
// ---------------------------------------------------------------------------------------------------------------------------------

signIn( $app, 'described' );
$numberedUri = basename( (string) ( $numbered['element']['.uri'] ?? '' ) );
check( 'an element of a numbered type, an account described in detail without the right to delete: 403 elements_delete_not_allowed', $numberedUri !== '' && \Nino\Elements::remove( $app, 'gallery', $numberedUri ) === refusal( 403, 'not allowed to delete elements of this type', 'elements_delete_not_allowed' ) );
check( '...and the element is still there', \Nino\Elements::getElement( $app, '/gallery/'. $numberedUri, 'de_DE' ) !== false );
check( 'a field the model does not have, from an account described in detail: 403 elements_fields_not_allowed with the field as param and field', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'nosuchfield' => 'x' ] ) === refusal( 403, 'not allowed to change the field(s) nosuchfield', 'elements_fields_not_allowed', [ 'nosuchfield' ], 'nosuchfield' ) );
check( '...beside a field it may change: the unknown one is named alone', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'Y', 'nosuchfield' => 'x' ] ) === refusal( 403, 'not allowed to change the field(s) nosuchfield', 'elements_fields_not_allowed', [ 'nosuchfield' ], 'nosuchfield' ) );
check( 'a new element in a language the site does not serve, from an account without the right to insert: the language is the reason, the code of the order of the checks', \Nino\Elements::save( $app, 'news', 'fifth', 'fr_FR', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_locale_invalid' ) );
signIn( $app, 'admin' );

caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElementType( $app, '/shots', [ 'title' => [ 'type' => 'string' ], 'cover' => [ 'type' => 'image', 'locale' => true, 'width' => 10, 'height' => 10 ] ] ); } );
\Nino\Elements::insertElement( $app, '/shots/one', [ 'title' => 'One', 'cover' => 'elements/shots/a.10x10.png' ], 'de_DE' );
check( 'an image field of a language, set by a name it does not hold: 400 elements_image_upload with the field', \Nino\Elements::save( $app, 'shots', 'one', 'de_DE', [ 'cover' => 'elements/shots/other.10x10.png' ] ) === refusal( 400, 'an image is set by uploading it: cover', 'elements_image_upload', [ 'cover' ], 'cover' ) );
check( '...the name the language holds may be saved as it is', \Nino\Elements::save( $app, 'shots', 'one', 'de_DE', [ 'cover' => 'elements/shots/a.10x10.png' ] )['ok'] === true );
check( '...in another language that holds none it is a name that is not held: refused', \Nino\Elements::save( $app, 'shots', 'one', 'en_US', [ 'cover' => 'elements/shots/a.10x10.png' ] ) === refusal( 400, 'an image is set by uploading it: cover', 'elements_image_upload', [ 'cover' ], 'cover' ) );
check( '...and the field may be emptied in its language', \Nino\Elements::save( $app, 'shots', 'one', 'de_DE', [ 'cover' => '' ] )['ok'] === true && ( \Nino\Elements::getElement( $app, '/shots/one', 'de_DE' )['cover'] ?? '' ) === '' );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "save() and remove() - the exact rights, what stays where the element stays, and the order of the checks\n";
// ---------------------------------------------------------------------------------------------------------------------------------

signIn( $app, 'inserter' );
check( 'rights() of an account that holds exactly the insert right of a type: it may add, may not delete and changes no field', \Nino\Elements::rights( $app, 'shots' ) === [ 'insert' => true, 'delete' => false, 'update' => [ 'title' => false, 'cover' => false ] ] );
check( '...it adds an element that has no field to write', \Nino\Elements::save( $app, 'shots', 'by-inserter', 'de_DE', [], [ 'new' => true ] )['ok'] === true && \Nino\Elements::getElement( $app, '/shots/by-inserter', 'de_DE' ) !== false );
check( '...and may not delete it: 403 elements_delete_not_allowed, the element stays', \Nino\Elements::remove( $app, 'shots', 'by-inserter' ) === refusal( 403, 'not allowed to delete elements of this type', 'elements_delete_not_allowed' ) && \Nino\Elements::getElement( $app, '/shots/by-inserter', 'de_DE' ) !== false );
signIn( $app, 'deleter' );
check( 'rights() of an account that holds exactly the delete right of a type: it may delete, may not add and changes no field', \Nino\Elements::rights( $app, 'shots' ) === [ 'insert' => false, 'delete' => true, 'update' => [ 'title' => false, 'cover' => false ] ] );
check( '...it may not add: 403 elements_insert_not_allowed', \Nino\Elements::save( $app, 'shots', 'by-deleter', 'de_DE', [], [ 'new' => true ] ) === refusal( 403, 'not allowed to add elements of this type', 'elements_insert_not_allowed' ) && \Nino\Elements::getElement( $app, '/shots/by-deleter', 'de_DE' ) === false );
check( '...and deletes the element the other account added', \Nino\Elements::remove( $app, 'shots', 'by-inserter' ) === [ 'ok' => true ] && \Nino\Elements::getElement( $app, '/shots/by-inserter', 'de_DE' ) === false );
signIn( $app, 'admin' );

// An element with a picture stays with its picture wherever the delete is refused or fails
caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElementType( $app, '/snaps', [ 'title' => [ 'type' => 'string' ], 'pic' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ] ] ); } );
mkdir( $images. '/elements/snaps', 0755, true );
$snapsFile = $sandbox. '/private/elements/snaps.php';
$snapPicture = $images. '/elements/snaps/one.10x10.png';
$snap = static function() use ( &$app, $snapPicture ): void {
	if( \Nino\Elements::getElement( $app, '/snaps/one', 'de_DE' ) === false )
		\Nino\Elements::insertElement( $app, '/snaps/one', [ 'title' => 'S', 'pic' => 'elements/snaps/one.10x10.png' ], 'de_DE' );
	file_put_contents( $snapPicture, 'x' );
};
$snapStands = static fn(): bool => \Nino\Elements::getElement( $app, '/snaps/one', 'de_DE' ) !== false && is_file( $snapPicture ) === true;

$snap();
\Nino\Callbacks::registerCallback( $app, '/nino/elements/delete/snaps', 'veto' );
check( 'a delete that a callback vetoes: 409 \'delete vetoed\', and the element and its picture stay', \Nino\Elements::remove( $app, 'snaps', 'one' ) === refusal( 409, 'delete vetoed', 'elements_vetoed' ) && $snapStands() === true );
unset( $app['./nino/callbacks']['/nino/elements/delete/snaps'] );

\Nino\Callbacks::registerCallback( $app, '/nino/elements/delete/snaps', 'listening' );
check( 'a delete that a listener vetoes with a notice of its own: 409, the listener\'s sentence, elements_vetoed - and the element and its picture stay', \Nino\Elements::remove( $app, 'snaps', 'one' ) === refusal( 409, 'the listener says no', 'elements_vetoed' ) && $snapStands() === true );
unset( $app['./nino/callbacks']['/nino/elements/delete/snaps'] );

$snapsWhole = (string) file_get_contents( $snapsFile );
$GLOBALS['swapFile'] = $snapsFile;
\Nino\Callbacks::registerCallback( $app, '/nino/elements/delete/snaps', 'swapTypeFile' );
$failed = \Nino\Elements::remove( $app, 'snaps', 'one' );
rmdir( $snapsFile );
file_put_contents( $snapsFile, $snapsWhole );
unset( $app['./nino/elements/cache'], $app['./nino/callbacks']['/nino/elements/delete/snaps'] );
check( 'a delete whose write fails: 409 elements_save_failed, and the element and its picture stay', $failed === refusal( 409, 'Element \'/snaps/one\' could not be written.', 'elements_save_failed' ) && $snapStands() === true );

signIn( $app, 'described' );
check( 'a delete by an account without the right: 403, and the element and its picture stay', \Nino\Elements::remove( $app, 'snaps', 'one' ) === refusal( 403, 'not allowed to delete elements of this type', 'elements_delete_not_allowed' ) && $snapStands() === true );

// The order of the checks of remove(): the type, the uri, the right - each request has two mistakes
check( 'remove, a type that is none and an empty uri: the type', \Nino\Elements::remove( $app, 'nope', '' ) === refusal( 400, 'invalid type or uri', 'elements_type_unknown' ) );
check( '...a type that is none and no right to delete (the account is described in detail): the type', \Nino\Elements::remove( $app, 'nope', 'first' ) === refusal( 400, 'invalid type or uri', 'elements_type_unknown' ) );
check( '...an empty uri and no right to delete: the uri', \Nino\Elements::remove( $app, 'news', '' ) === refusal( 400, 'invalid type or uri', 'elements_uri_invalid' ) );
check( '...a uri with a slash and no right to delete: the uri', \Nino\Elements::remove( $app, 'news', 'a/b' ) === refusal( 400, 'invalid type or uri', 'elements_uri_invalid' ) );

// The image rule is asked after the rights of the fields
check( 'an image field set by a name, by an account that may not change the field: 403 elements_fields_not_allowed, not the image rule', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'pic' => 'elements/news/x.10x10.png' ] ) === refusal( 403, 'not allowed to change the field(s) pic', 'elements_fields_not_allowed', [ 'pic' ], 'pic' ) );
signIn( $app, 'admin' );

// A new uri begins with a letter or a digit, and the option 'new' is a boolean true and nothing that reads like one
check( 'a new uri that begins with a hyphen: elements_uri_invalid', \Nino\Elements::save( $app, 'news', '-third', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( '...one that begins with an underscore: elements_uri_invalid', \Nino\Elements::save( $app, 'news', '_third', 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
check( '...one with a letter that is no a-z: elements_uri_invalid', \Nino\Elements::save( $app, 'news', 'dr' . "\xc3\xbc", 'de_DE', [ 'title' => 'X' ], [ 'new' => true ] ) === refusal( 400, 'invalid type, uri or locale', 'elements_uri_invalid' ) );
foreach( [ 'the string \'true\'' => 'true', 'the number 1' => 1, 'a list' => [ true ] ] as $what => $flag )
	check( 'the option new as '. $what. ' is no insert: an update of an element that is none, 400 elements_unknown, and nothing is made', \Nino\Elements::save( $app, 'news', 'strict-flag', 'de_DE', [ 'title' => 'X' ], [ 'new' => $flag ] ) === refusal( 400, 'Element \'/news/strict-flag\' does not exist.', 'elements_unknown' ) && \Nino\Elements::getElement( $app, '/news/strict-flag', 'de_DE' ) === false );

// php's own warning under an @ is no sentence of the write: the answer is what it is without it
\Nino\Callbacks::registerCallback( $app, '/nino/elements/news/update', 'silenced' );
check( 'a veto callback that silences a warning with @: save answers \'save failed\', not php\'s sentence with its path', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'title' => 'X' ] ) === refusal( 400, 'save failed', 'elements_vetoed' ) );
unset( $app['./nino/callbacks']['/nino/elements/news/update'] );
\Nino\Callbacks::registerCallback( $app, '/nino/elements/delete/news', 'silenced' );
check( '...and remove answers \'delete vetoed\'; the element stays', \Nino\Elements::remove( $app, 'news', 'first' ) === refusal( 409, 'delete vetoed', 'elements_vetoed' ) && \Nino\Elements::getElement( $app, '/news/first', 'de_DE' ) !== false );
unset( $app['./nino/callbacks']['/nino/elements/delete/news'] );

// The order inside the writer: the lock of the type file, then the callbacks of the fields, then the values
\Nino\Callbacks::registerCallback( $app, '/probe/guard', 'refuseGuard' );
check( 'a callback of a field that says no comes before the value of another field: elements_vetoed, not elements_wrong_type', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'guarded' => 'x', 'count' => 'abc' ] ) === refusal( 400, 'Callback \'/probe/guard\' returns an error.', 'elements_vetoed' ) );
check( '...and before a required key that is emptied', \Nino\Elements::save( $app, 'news', 'first', 'de_DE', [ 'guarded' => 'x', 'title' => '' ] ) === refusal( 400, 'Callback \'/probe/guard\' returns an error.', 'elements_vetoed' ) );
check( '...an element that is none is judged before the callbacks: elements_unknown', \Nino\Elements::save( $app, 'news', 'ghost', 'de_DE', [ 'guarded' => 'x' ] ) === refusal( 400, 'Element \'/news/ghost\' does not exist.', 'elements_unknown' ) );
unset( $app['./nino/callbacks']['/probe/guard'] );

$known = glob( $sandbox. '/private/data/.locks/*.lock' ) ?: [];
caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElementType( $app, '/lockorder', [ 'count' => [ 'type' => 'integer' ] ] ); } );
\Nino\Elements::insertElement( $app, '/lockorder/one', [ 'count' => 1 ], 'de_DE' );
$lockFiles = array_values( array_diff( glob( $sandbox. '/private/data/.locks/*.lock' ) ?: [], $known ) );
$app['./nino/filesystem/locks'] = [];
foreach( $lockFiles as $lockFile ) {
	unlink( $lockFile );
	mkdir( $lockFile );
}
check( 'a type file that cannot be locked comes before the value of a field: elements_save_failed, not elements_wrong_type', $lockFiles !== [] && \Nino\Elements::save( $app, 'lockorder', 'one', 'de_DE', [ 'count' => 'abc' ] ) === refusal( 400, 'Element type \'/lockorder\' could not be locked for writing.', 'elements_save_failed' ) );
check( '...an element that is none is judged before the lock: elements_unknown', \Nino\Elements::save( $app, 'lockorder', 'ghost', 'de_DE', [ 'count' => 1 ] ) === refusal( 400, 'Element \'/lockorder/ghost\' does not exist.', 'elements_unknown' ) );
foreach( $lockFiles as $lockFile )
	rmdir( $lockFile );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "the older writers refuse a uri that has no leading slash before anything is locked or written\n";
// ---------------------------------------------------------------------------------------------------------------------------------

// 'news/first' reads as the type 'news' with no slash: the hooks asked would be those of another name, and the veto of the type never asked
$asked = [];
$spy = static function( array &$appData, mixed &$data ) use ( &$asked ): bool { $asked[] = 'asked'; return false; };
foreach( [ '/nino/elements/delete/news', '/nino/elements/news/insert', '/nino/elements/news/update' ] as $hook )
	\Nino\Callbacks::registerCallback( $app, $hook, $spy );

$newsBefore = (string) file_get_contents( $typeFile );
$noSlash = "Element uri 'news/first' has no leading slash (expected '/type/slug').";

[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::deleteElement( $app, 'news/first', '*' ); } );
check( 'deleteElement() with no leading slash: false and the notice that says so, once', $result === false && $notices === [ $noSlash ] );
[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElement( $app, 'news/fresh', [ 'title' => 'F' ], 'de_DE' ); } );
check( 'insertElement() with no leading slash: false and the notice, once', $result === false && $notices === [ "Element uri 'news/fresh' has no leading slash (expected '/type/slug')." ] );
[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::updateElement( $app, 'news/first', [ 'title' => 'U' ], 'de_DE' ); } );
check( 'updateElement() with no leading slash: false and the notice, once', $result === false && $notices === [ $noSlash ] );
check( '...the type file is as it was byte for byte, the element is there and no veto was asked', (string) file_get_contents( $typeFile ) === $newsBefore && \Nino\Elements::getElement( $app, '/news/first', 'de_DE' ) !== false && $asked === [] );
[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::deleteElement( $app, '/news/first', '*' ); } );
check( 'with the slash the veto of the type is asked and holds', $result === null && $asked === [ 'asked' ] && (string) file_get_contents( $typeFile ) === $newsBefore );
foreach( [ '/nino/elements/delete/news', '/nino/elements/news/insert', '/nino/elements/news/update' ] as $hook )
	unset( $app['./nino/callbacks'][$hook] );

// A type that is spelled otherwise than its file is spelled: a disk that does not tell the cases apart opens news.php for it, so the
// veto asked and the lock taken would be those of another spelling. The writers refuse it before they lock anything
$locksBefore = glob( $sandbox. '/private/data/.locks/*.lock' ) ?: [];
$newsBefore = (string) file_get_contents( $typeFile );
$spelled = "Element type '/NEWS' does not exist.";
foreach( [ '/nino/elements/delete/NEWS', '/nino/elements/NEWS/insert', '/nino/elements/NEWS/update' ] as $hook )
	\Nino\Callbacks::registerCallback( $app, $hook, $spy );
$asked = [];
[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::deleteElement( $app, '/NEWS/first', '*' ); } );
check( 'deleteElement() of a type spelled otherwise than its file: false and one notice', $result === false && $notices === [ $spelled ] );
[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::insertElement( $app, '/NEWS/fresh', [ 'title' => 'F' ], 'de_DE' ); } );
check( 'insertElement() of such a type: false and one notice', $result === false && $notices === [ $spelled ] );
[ $result, $notices ] = caught( static function() use ( &$app ): mixed { return \Nino\Elements::updateElement( $app, '/NEWS/first', [ 'title' => 'U' ], 'de_DE' ); } );
check( 'updateElement() of such a type: false and one notice', $result === false && $notices === [ $spelled ] );
check( '...no veto was asked, no lock file was made and the type file is as it was', $asked === [] && ( glob( $sandbox. '/private/data/.locks/*.lock' ) ?: [] ) === $locksBefore && (string) file_get_contents( $typeFile ) === $newsBefore );
foreach( [ '/nino/elements/delete/NEWS', '/nino/elements/NEWS/insert', '/nino/elements/NEWS/update' ] as $hook )
	unset( $app['./nino/callbacks'][$hook] );

// ---------------------------------------------------------------------------------------------------------------------------------
echo "no writer's notice reaches the caller, and the shell stays out\n";
// ---------------------------------------------------------------------------------------------------------------------------------

check( 'not one notice reached this process\'s error handler: the refusals above all came as results'. ( $leaked === [] ? '' : ' - '. implode( ' | ', $leaked ) ), $leaked === [] );
check( 'the shell of the workbench was not loaded by any of it', class_exists( '\Nino\Admin\Admin', false ) === false );

restore_error_handler();
\Nino\Filesystem::removeDir( $sandbox );

$seconds = number_format( microtime( true ) - $ninoStarted, 1 );
echo "\n{$GLOBALS['ninoChecks']} checks, {$GLOBALS['ninoFailures']} failed, {$seconds}s\n";
exit( $GLOBALS['ninoFailures'] === 0 ? 0 : 1 );
