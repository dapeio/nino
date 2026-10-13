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
 *								ninoErrors()		Every error one call raises, with its level
 *								ninoCall()			One workbench request, as a panel sees it
 *								ninoUser()			An account, inserted and signed in
 *								ninoVisit()			One request of the site, through Http::request()
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
 *	filesystem path pointing into it, two locales, and no modules - the
 *	test switches on what it tests. The directory is removed by ninoDone().
 *
 *	@param		string		$name					Names the temp directory, eg. 'features'
 *
 *	@return 	array										$appData
 */
function ninoSandbox( string $name ): array {

	$sandbox = sys_get_temp_dir(). '/nino-'. preg_replace( '/[^a-z0-9-]/', '-', strtolower( $name ) ). '-smoke-'. uniqid();
	mkdir( $sandbox, 0755, true );

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );

	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	$appData['./nino/locales/current']				= 'de_DE';
	$appData['/nino/locales/native']					= 'de_DE';
	$appData['/nino/locales/available']				= [ 'de_DE', 'en_US' ];
	$appData['/nino/modules']									= [];
	$appData['./nino/test/sandbox']						= $sandbox;

	return $appData;
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
 *	handler of its own that records [ level, message ] and swallows the error
 *	- E_USER_ERROR too, which the handler below hands on to php as the fatal
 *	it is. What the code silenced with @ is not recorded. The handler in
 *	force before is back when this returns. A test that needs the result of
 *	the call sets it inside the closure:
 *
 *		$result = null;
 *		$errors = ninoErrors( function() use ( &$appData, &$result ) { $result = \Nino\Foo::bar( $appData ); } );
 *
 *	@param		callable	$run						The call to watch
 *
 *	@return 	array										[ level, message ] per error, oldest first
 */
function ninoErrors( callable $run ): array {

	$errors = [];

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
