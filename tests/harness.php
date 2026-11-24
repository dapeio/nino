<?php
declare(strict_types=1);
/**
 *	Nino
 *	harness.php		The one bootstrap of every PHP suite under tests/ and of
 *								every feature's own test: the kernel and the workbench shell,
 *								a sandbox project directory, check(), the helpers below and
 *								the summary line with the seconds the run took.
 *
 *								A feature's test lives in its own directory
 *								(features/<Name>/tests/<key>-smoke.php) and finds the
 *								checkout it runs against three levels up - or wherever
 *								NINO_ROOT points, which is how the same test runs against
 *								another Nino version from outside the checkout. The
 *								features directory is the one the feature lives in, named
 *								before the kernel loads (the autoloader and
 *								\Nino\Features::dir() read the same constant), and kept if
 *								the test was included by something that named it already:
 *
 *									$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
 *									defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', dirname( __DIR__, 2 ) );
 *									require $root. '/tests/harness.php';
 *
 *								check()				One assertion, one line of output
 *								ninoSandbox()		A fresh, isolated project directory
 *								ninoSandboxDir()	The directory of a sandbox
 *								ninoWarnings()		The warnings raised since the last call
 *								ninoErrors()		Every error one call raises, with its level, and the Failure that ended it
 *								ninoCall()			One workbench request, as a panel sees it
 *								ninoUser()			An account, inserted and signed in
 *								ninoVisit()			One request of the site, through Http::request()
 *								ninoCatalogue()	The json of a catalogue of format 2, from entries of features written flat
 *								ninoDone()			The summary and its seconds, the sandbox removed
 *
 *	Usage: require it, build a sandbox with ninoSandbox(), check() away,
 *	end with ninoDone().
 */

if( defined( 'NINO_TEST_HARNESS' ) === true )
	return;

define( 'NINO_TEST_HARNESS', true );

require_once __DIR__. '/../_nino/Nino.php';
require_once __DIR__. '/../_admin/Admin.php';

$GLOBALS['ninoChecks']		= 0;
$GLOBALS['ninoFailures']	= 0;
$GLOBALS['ninoWarnings']	= [];
$GLOBALS['ninoStarted']		= microtime( true );

/**
 *	One assertion, one line of output
 *
 *	@param		string		$label
 *	@param		bool			$condition
 *
 *	@return 	void
 */
function check( string $label, bool $condition ): void {

	$GLOBALS['ninoChecks']++;

	if( $condition === true ) {
		echo "  ok  - $label\n";
		return;
	}

	$GLOBALS['ninoFailures']++;
	echo "FAIL  - $label\n";
}

/**
 *	A fresh, isolated project directory to run against: $appData with every
 *	filesystem path pointing into it, the directory it is served from ('', the
 *	domain root), two locales, and no modules - the
 *	test switches on what it tests. The directory is removed by ninoDone().
 *
 *	@param		string		$name					Names the temp directory, eg. 'features'
 *
 *	@return 	array										$appData
 */
function ninoSandbox( string $name ): array {

	// A name of random bytes, and a directory that this call made: uniqid() is the clock to the microsecond, and two runs of a suite started at once - in two copies
	// - get the same one in one start in thirty, and share a sandbox without either knowing. mkdir() says no for a directory that is there, and the name is made again
	do {
		$sandbox = sys_get_temp_dir(). '/nino-'. preg_replace( '/[^a-z0-9-]/', '-', strtolower( $name ) ). '-smoke-'. bin2hex( random_bytes( 6 ) );
	} while( @mkdir( $sandbox, 0755, true ) === false && is_dir( $sandbox ) === true );

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );

	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	$appData['./nino/locales/current']				= 'de_DE';
	$appData['/nino/locales/native']					= 'de_DE';
	$appData['/nino/locales/available']				= [ 'de_DE', 'en_US' ];
	$appData['/nino/dir']											= '';
	$appData['/nino/modules']									= [];
	$appData['/nino/install/texts']					= \Nino\VERSION;
	$appData['./nino/test/sandbox']						= $sandbox;

	return $appData;
}

/**
 *	The names a function of a source calls, in the order they stand - read as php reads it (token_get_all), so that how the source is formatted
 *	and how a call is spelt (a blank, a line, a comment) is no matter. A name is a T_STRING that is followed by a bracket and is no declaration; a
 *	method or a static call counts by its own name. Where a source holds several functions of the name, the first
 *
 *	@param		string		$source				The php of a file (with its <?php)
 *	@param		string		$function			The name of the function or method
 *
 *	@return 	array<int, string>					[] where the function is not there
 */
function ninoCalls( string $source, string $function ): array {

	$tokens = array_values( array_filter( token_get_all( $source ), static fn( mixed $token ): bool => is_array( $token ) === false || in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) === false ) );
	$calls = [];

	foreach( $tokens as $at => $token ) {

		if( is_array( $token ) === false || $token[0] !== T_FUNCTION || is_array( $tokens[$at + 1] ?? null ) === false || $tokens[$at + 1][1] !== $function )
			continue;

		// The body: from the first { after the signature to its end
		$depth = 0;
		$begun = false;

		for( $at += 2; $at < count( $tokens ); $at++ ) {

			$part = $tokens[$at];
			$text = is_array( $part ) === true ? $part[1] : $part;

			if( $text === '{' || ( is_array( $part ) === true && $part[0] === T_CURLY_OPEN ) || ( is_array( $part ) === true && $part[0] === T_DOLLAR_OPEN_CURLY_BRACES ) ) {
				$depth++;
				$begun = true;
			}
			else if( $text === '}' ) {
				$depth--;

				if( $begun === true && $depth === 0 )
					break;
			}
			else if( is_array( $part ) === true && $part[0] === T_STRING && ( $tokens[$at + 1] ?? null ) === '(' )
				$calls[] = $part[1];
		}

		return $calls;
	}

	return [];
}

/**
 *	The directory a sandbox lives in, with the private/ and public/ halves of
 *	its project below it - to put a file there for the code under test, or to
 *	look at what that code wrote
 *
 *	@param		array 		&$appData			(reference) A sandbox's app data
 *
 *	@return 	string									Its directory; '' for app data that is no sandbox's
 */
function ninoSandboxDir( array &$appData ): string {

	return (string) ( $appData['./nino/test/sandbox'] ?? '' );
}

/**
 *	The warnings raised since the last call - a test that expects one
 *	(a malformed manifest, an unknown requirement) reads them here rather
 *	than seeing them on the console
 *
 *	@return 	array										Messages, oldest first
 */
function ninoWarnings(): array {

	$warnings = $GLOBALS['ninoWarnings'];
	$GLOBALS['ninoWarnings'] = [];

	return $warnings;
}

/**
 *	Every error one call raises, the level with it: the call runs under a
 *	handler of its own that records [ level, message ] and swallows the error.
 *	What the code silenced with @ is not recorded. The handler in force before
 *	is back when this returns. A \Nino\Failure the call throws (see
 *	\Nino\Runtime::fail()) ends the call and is no error of the list: it is
 *	handed back in $failure, null where the call raised none; a caller that
 *	passes no $failure gets the Failure thrown on. A test that needs
 *	the result of the call sets it inside the closure:
 *
 *		$result = null;
 *		$errors = ninoErrors( function() use ( &$appData, &$result ) { $result = \Nino\Foo::bar( $appData ); } );
 *		$errors = ninoErrors( function() use ( &$appData ) { \Nino\Foo::bar( $appData ); }, $failure );
 *
 *	@param		callable	$run						The call to watch
 *	@param		\Nino\Failure|null	&$failure	(reference) The failure that ended the call
 *
 *	@return 	array										[ level, message ] per error, oldest first
 */
function ninoErrors( callable $run, ?\Nino\Failure &$failure = null ): array {

	$asked = func_num_args() >= 2;
	$errors = [];
	$failure = null;

	set_error_handler( static function( int $level, string $message ) use ( &$errors ): bool {

		// Silenced with @ by the code itself: not an error anyone meant to see
		if( ( error_reporting() & $level ) === 0 )
			return true;

		$errors[] = [ $level, $message ];

		return true;
	} );

	try {
		$run();
	}
	catch( \Nino\Failure $caught ) {

		// Only a caller that asks for it takes the Failure: one that does not would see a call that ended midway as one that raised nothing
		if( $asked === false )
			throw $caught;

		$failure = $caught;
	}
	finally {
		restore_error_handler();
	}

	return $errors;
}

/**
 *	One workbench request, as Admin::handlePost() and every api* method of a
 *	panel see it: $_POST['data'] is the json of $data, $_POST['action'] is
 *	set when an action is given (and left as it is when not), and the
 *	response starts with the status the request already carries. The target
 *	is called directly, so $appData and the request travel by reference the
 *	way the panel methods declare them:
 *
 *		[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Users\Admin::class, 'apiList' ] );
 *		[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'type' => 'news' ], 'elements/list' );
 *
 *	@param		array 		&$appData			(reference) A sandbox's app data
 *	@param		callable	$target				A method ( array &$appData, array &$request ), eg. [ \Nino\Modules\Users\Admin::class, 'apiList' ]
 *	@param		array 		$data					Becomes $_POST['data'], json encoded
 *	@param		string|null	$action			Becomes $_POST['action'] when given, null leaves it as it is
 *	@param		int				$statusBefore	The status the request carries into the call, eg. 403 after a failed csrf check
 *
 *	@return 	array										[ statusCode, body, request ] - the body null where the answer has none, the request for a header or the whole response
 */
function ninoCall( array &$appData, callable $target, array $data = [], ?string $action = null, int $statusBefore = 200 ): array {

	$_POST['data'] = json_encode( $data );

	if( $action !== null )
		$_POST['action'] = $action;

	$request = [ '/nino/http/response' => [ 'statusCode' => $statusBefore ] ];

	$target( $appData, $request );

	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ?? null, $request ];
}

/**
 *	An account that exists and is signed in: inserted with the permissions
 *	given - full access unless the test says otherwise - and logged in with
 *	the password the suites use everywhere
 *
 *	@param		array 		&$appData			(reference) A sandbox's app data
 *	@param		string		$mail
 *	@param		array 		$perms				[] for an account that may do nothing
 *	@param		string		$password			Another one, where the test signs in again with it
 *
 *	@return 	void
 */
function ninoUser( array &$appData, string $mail, array $perms = [ '/*' ], string $password = 'correct horse battery staple' ): void {

	\Nino\Auth::insertUser( $appData, $mail, $password, $perms );
	\Nino\Auth::loginUser( $appData, $mail, $password );
}

/**
 *	A nonce of 12 bytes plus a number, as three words of 32 bits with the carry between them, wrapping at 96 bits
 *
 *	@param		string		$nonce
 *	@param		int				$number
 *
 *	@return 	string
 */
function ninoNonce( string $nonce, int $number ): string {

	$words 	= array_reverse( array_values( unpack( 'N3', $nonce ) ) );
	$carry 	= $number;

	foreach( $words as $index => $word ) {
		$sum 							= $word + $carry;
		$words[$index] 		= $sum & 0xFFFFFFFF;
		$carry 						= $sum >> 32;
	}

	return pack( 'N3', ...array_reverse( $words ) );
}

/**
 *	The file of a backup archive in format 2, made from the layout in the docblock of \Nino\Backup\Seal and by none of its code, so that
 *	a test reads what the product wrote with another reader and hands the product what another writer made: the stub, the base64 of the
 *	header (the magic "NinoBak" and the version, the size of a chunk, a nonce of 12 bytes) and the chunks, each its ciphertext and its
 *	tag, the number of the chunk and a byte for the last one in what the tag covers besides the header
 *
 *	@param		string		$plain				The compressed archive
 *	@param		string		$key					The 32 bytes of the key
 *	@param		int				$chunk				How much of the archive a chunk holds
 *	@param		string|null	$nonce			The 12 bytes of the header, random where none is given
 *
 *	@return 	string									The bytes of the file
 */
function ninoSealed( string $plain, string $key, int $chunk = 1048576, ?string $nonce = null ): string {

	$nonce 		= $nonce ?? random_bytes( 12 );
	$header 	= "NinoBak\x02". pack( 'N', $chunk ). $nonce;
	$parts 		= $plain === '' ? [ '' ] : str_split( $plain, $chunk );
	$body 		= $header;

	foreach( $parts as $number => $part ) {

		// The nonce of a chunk is the nonce of the header plus its number, as a number of 96 bits
		$iv 		= ninoNonce( $nonce, $number );
		$tag 		= '';
		$cipher = openssl_encrypt( $part, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $header. pack( 'N', $number ). ( $number === count( $parts ) - 1 ? "\x01" : "\x00" ), 16 );
		$body 	.= $cipher. $tag;
	}

	return "<?php http_response_code(403); exit; return '". base64_encode( $body ). "';\n";
}

/**
 *	The archive of a file of format 1 or 2 read back the way a person with the key would, by none of the code of the product
 *
 *	@param		string		$file					The bytes of the file
 *	@param		string		$key					The 32 bytes of the key
 *
 *	@return 	string|false							The compressed archive, false where a tag does not hold or the file is none
 */
function ninoUnsealed( string $file, string $key ): string|false {

	$prefix = "<?php http_response_code(403); exit; return '";
	$suffix = "';\n";

	if( str_starts_with( $file, $prefix ) === false || str_ends_with( $file, $suffix ) === false )
		return false;

	$bytes = base64_decode( substr( $file, strlen( $prefix ), -strlen( $suffix ) ), true );

	if( $bytes === false )
		return false;

	if( str_starts_with( $bytes, "NinoBak\x02" ) === false )
		return openssl_decrypt( substr( $bytes, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $bytes, 0, 12 ), substr( $bytes, 12, 16 ) );

	$header = substr( $bytes, 0, 24 );
	$chunk 	= (int) unpack( 'N', substr( $header, 8, 4 ) )[1];
	$nonce 	= substr( $header, 12 );
	$rest 	= substr( $bytes, 24 );
	$count 	= max( 1, (int) ceil( strlen( $rest ) / ( $chunk + 16 ) ) );
	$plain 	= '';

	for( $number = 0; $number < $count; $number++ ) {

		$piece 	= substr( $rest, $number * ( $chunk + 16 ), $chunk + 16 );
		$iv 		= ninoNonce( $nonce, $number );
		$part 	= openssl_decrypt( substr( $piece, 0, -16 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, substr( $piece, -16 ), $header. pack( 'N', $number ). ( $number === $count - 1 ? "\x01" : "\x00" ) );

		if( $part === false )
			return false;

		$plain .= $part;
	}

	return $plain;
}

/**
 *	The file of an archive in format 1, which no code writes any more: iv, tag and the ciphertext of the whole archive in one stub
 *
 *	@param		string		$plain				The compressed archive
 *	@param		string		$key					The 32 bytes of the key
 *
 *	@return 	string									The bytes of the file
 */
function ninoSealedOld( string $plain, string $key ): string {

	$iv 		= random_bytes( 12 );
	$tag 		= '';
	$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

	return "<?php http_response_code(403); exit; return '". base64_encode( $iv. $tag. (string) $cipher ). "';\n";
}

/**
 *	One request of the site, the way a web server hands it over, as far as
 *	its first stage: the server array run through Http::request(), which
 *	builds the request, seeds the response with the default headers and runs
 *	the callbacks of '/nino/http/request'. No route is resolved and nothing is
 *	rendered - Http::response() and the output are not run
 *
 *	@param		array 		&$appData			(reference) A sandbox's app data
 *	@param		string		$uri					eg. '/legal?x=1'
 *	@param		string		$method
 *	@param		array 		$server				More of $_SERVER, or what replaces the defaults (REMOTE_ADDR is 127.0.0.1)
 *
 *	@return 	array										The request after Http::request(), response and all
 */
function ninoVisit( array &$appData, string $uri, string $method = 'GET', array $server = [] ): array {

	$request = array_merge( [ 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1' ], $server );
	\Nino\Http::request( $appData, $request );

	return $request;
}

/**
 *	The json of a catalogue in format 2. A feature is given the way a test writes it - key, version, nino, php => [ ext ],
 *	requires => [ keys of features ] and the rest flat - and is put in the shape the catalogue has: kind, and what it asks
 *	for under requires. An entry that has a kind already is taken as it is, and so is a field that is not one of those
 *
 *	@param		array 		$features			Entries of features, flat
 *	@param		array 		$applications	Applications
 *	@param		array 		$packages			Entries of themes and of packages of blocks, as the catalogue has them
 *	@param		string		$generated
 *
 *	@return 	string
 */
function ninoCatalogue( array $features, array $applications = [], array $packages = [], string $generated = '2026-11-13T12:00:00Z' ): string {

	$entries = [];

	foreach( $features as $entry ) {

		if( is_array( $entry ) === true && isset( $entry['kind'] ) === false ) {

			$requires = [ 'nino' => $entry['nino'] ?? '*', 'php' => (array) ( $entry['php']['ext'] ?? [] ), 'features' => $entry['requires'] ?? [] ];
			unset( $entry['nino'], $entry['php'], $entry['requires'] );
			$entry = [ 'kind' => 'feature' ] + $entry + [ 'requires' => $requires ];
		}

		$entries[] = $entry;
	}

	return (string) json_encode( [ 'format' => 2, 'generated' => $generated, 'packages' => array_merge( $entries, $packages ), 'applications' => $applications ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}

/**
 *	Print the summary - checks, failures and the seconds the run took -
 *	remove the sandbox, exit with the suite's status
 *
 *	@param		array 		&$appData			(reference) A sandbox's app data, or [] for none
 *
 *	@return 	never
 */
function ninoDone( array &$appData ): never {

	restore_error_handler();

	if( ninoSandboxDir( $appData ) !== '' )
		\Nino\Filesystem::removeDir( ninoSandboxDir( $appData ) );

	unset( $_POST['action'], $_POST['data'] );

	// One decimal every time, so the columns of a run over all suites line up
	$seconds = sprintf( '%.1f', microtime( true ) - $GLOBALS['ninoStarted'] );

	echo "\n". $GLOBALS['ninoChecks']. " checks, ". $GLOBALS['ninoFailures']. " failed, {$seconds}s\n";

	exit( $GLOBALS['ninoFailures'] === 0 ? 0 : 1 );
}

// Warnings are recorded, not printed: the kernel raises E_USER_WARNING for
// what it skips (a manifest it cannot read, a unit key claimed twice) and a
// test asserts on that rather than scrolling past it. Everything else is
// still fatal enough to see
set_error_handler( static function( int $level, string $message ): bool {

	// Silenced with @ by the code itself: not a warning anyone meant to see
	if( ( error_reporting() & $level ) === 0 )
		return true;

	$GLOBALS['ninoWarnings'][] = $message;

	return in_array( $level, [ E_USER_WARNING, E_USER_NOTICE, E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_DEPRECATED ], true );
} );
