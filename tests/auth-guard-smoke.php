<?php
declare(strict_types=1);
/**
 *	Nino
 *	auth-guard-smoke.php	The public guard of a surface outside the workbench, \Nino\Auth::guard() and deny(): a request of a
 *								feature that the site's own index.php serves, in a process that loads _nino/Nino.php and never the
 *								shell of the workbench. Real sessions, real tokens (loginUser()), real requests through
 *								Http::request() and Http::response().
 *
 *								The suite runs twice: this process answers every state with the public guard; a second php process
 *								(this file with --shell) loads the shell as well and answers the same states with the workbench's
 *								guard - POST /_admin and the same route calling Admin::guardPerm() - and hands its answers back as
 *								json, so that the bytes of csrf, post_too_large and session are compared with the workbench's own.
 */

$root	= dirname( __DIR__ );
$shell	= in_array( '--shell', $argv ?? [], true ) === true;

defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', $root. '/features' );
require_once $root. '/_nino/Nino.php';

if( $shell === true )
	require_once $root. '/_admin/Admin.php';

const PW		= 'correct horse battery staple';
const PERM	= '/probe/manage';

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

/**
 *	A project directory with the accounts of the suite in config.php: adm (/*), holder (the right of the probe), wild
 *	(/probe/*), viarole (the right through a role), scopedacc (the door and one fine right), none (no right), other (/*)
 */
function world( string $name, bool $staleTexts = false ): array {

	do {
		$sandbox = sys_get_temp_dir(). '/nino-'. $name. '-smoke-'. bin2hex( random_bytes( 6 ) );
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
	$app['/nino/install/texts']						= $staleTexts === true ? '0.0.0' : \Nino\VERSION;
	$app['/nino/admin/backups']						= $staleTexts;
	$app['/nino/auth/user']								= [];
	$app['/nino/auth/roles']							= [ 'probe' => [ 'label' => 'Probe', 'perms' => [ PERM ] ] ];

	\Nino\AppData::writeContentData( $app, [ '/nino/auth/roles', '/nino/install/completed', '/nino/install/texts', '/nino/admin/backups', '/nino/modules' ] );

	foreach( [ 'adm' => [ [ '/*' ], '' ], 'holder' => [ [ PERM ], '' ], 'wild' => [ [ '/probe/*' ], '' ], 'viarole' => [ [], 'probe' ], 'scopedacc' => [ [ PERM, '/probe/news/update/title' ], '' ], 'none' => [ [], '' ], 'other' => [ [ '/*' ], '' ] ] as $local => [ $perms, $role ] )
		\Nino\Auth::insertUser( $app, $local. '@example.com', PW, $perms, $role );

	// What a feature registers in its init(): the route and the callbacks of a surface outside the workbench
	\Nino\Csrf::init( $app );

	foreach( [ 'api' => PERM, 'any' => '', 'unknown' => '/nope/never', 'maint' => PERM ] as $name => $perm ) {
		$app['/nino/http/routes']['POST://probe/'. $name] = [ 'uri' => '/probe/'. $name, 'csrf' => true ];
		$app['/nino/http/routes']['GET://probe/'. $name]	= [ 'uri' => '/probe/'. $name, 'statusCode' => 200 ];
	}
	$app['/nino/http/routes']['POST://probe/open'] = [ 'uri' => '/probe/open', 'csrf' => false ];
	$app['/nino/http/routes']['GET://probe/open']	= [ 'uri' => '/probe/open', 'statusCode' => 200 ];

	foreach( [ 'api', 'any', 'unknown', 'open' ] as $name )
		foreach( [ 'POST', 'GET' ] as $method )
			\Nino\Callbacks::registerCallback( $app, '/nino/http/response/'. $method. '://probe/'. $name, 'probe_'. $name );

	// Another global callback refused the request first (maintenance, say): its answer is there when the route runs
	foreach( [ 'POST' ] as $method ) {
		\Nino\Callbacks::registerCallback( $app, '/nino/http/response/'. $method. '://probe/maint', 'probe_earlier' );
		\Nino\Callbacks::registerCallback( $app, '/nino/http/response/'. $method. '://probe/maint', 'probe_api' );
	}

	if( defined( 'NINO_SHELL' ) === true ) {
		$app['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin' ];
		\Nino\Callbacks::registerCallback( $app, '/nino/http/response/POST://_admin', [ \Nino\Admin\Admin::class, 'handlePost' ] );
	}

	return $app;
}

/** what a feature writes: the two lines of the guard, then its answer */
function probe_guarded( array &$appData, array &$request, string $perm ): void {

	if( defined( 'NINO_SHELL' ) === true ) {

		// The workbench's guard on the same route, as a feature outside the workbench calls it today
		if( (int) $request['/nino/http/response']['statusCode'] !== 200 ) {
			if( \Nino\Csrf::blocked( $request ) === true )
				\Nino\Admin\Admin::failBlocked( $request );
			return;
		}

		if( \Nino\Admin\Admin::guardPerm( $appData, $request, $perm ) === false )
			return;

		\Nino\Http::ok( $request, [ 'ok' => true ] );
		return;
	}

	$refusal = \Nino\Auth::guard( $appData, $request, $perm );

	if( $refusal !== null ) {
		\Nino\Auth::deny( $request, $refusal );
		return;
	}

	\Nino\Http::ok( $request, [ 'ok' => true ] );
}

function probe_api( array &$appData, array &$request ): void {
	probe_guarded( $appData, $request, PERM );
}

function probe_any( array &$appData, array &$request ): void {
	probe_guarded( $appData, $request, '' );
}

function probe_unknown( array &$appData, array &$request ): void {
	probe_guarded( $appData, $request, '/nope/never' );
}

function probe_open( array &$appData, array &$request ): void {
	probe_guarded( $appData, $request, PERM );
}

function probe_earlier( array &$appData, array &$request ): void {

	$request['/nino/http/response']['statusCode']	= 503;
	$request['/nino/http/response']['body']					= [ 'error' => 'maintenance' ];
}

function load( array &$app ): void {

	$file = (array) eval( substr( (string) @file_get_contents( $app['./nino/test/dir']. '/private/config.php' ), 5 ) );

	foreach( [ '/nino/auth/user', '/nino/auth/roles' ] as $key )
		$app[$key] = (array) ( $file[$key] ?? [] );

	unset( $app['./nino/auth/current'], $app['./nino/auth/revoked'], $app['./nino/auth/rehashed'], $app['./nino/auth/baseline'], $app['./nino/filesystem/cache'], $app['./_admin/panels'] );
}

/** the request of a browser, booted as a live request is: accounts from disk, Auth::init(), Http::request() - not yet answered */
function boot( array &$app, array $session, string $method, string $uri, array $post = [], array $server = [], ?string $body = null ): array {

	$_SESSION	= $session;
	$_POST		= $post;
	$_GET			= [];

	$copy = $app;
	load( $copy );
	\Nino\Auth::init( $copy );

	$request = $server + [ 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1' ];
	\Nino\Http::request( $copy, $request );

	if( $body !== null )
		$request['/nino/http/request']['body'] = $body;

	return [ $copy, $request ];
}

/** one request answered: status, the bytes the client gets, the decoded body */
function ask( array &$app, array &$session, string $method, string $uri, ?string $token, array $server = [] ): array {

	$headers = $method === 'POST' ? [ 'CONTENT_TYPE' => 'application/json' ] : [];

	if( $token !== null )
		$headers['HTTP_X_CSRF_TOKEN'] = $token;

	[ $copy, $request ] = boot( $app, $session, $method, $uri, [], $server + $headers, $method === 'POST' ? '{}' : null );
	\Nino\Http::response( $copy, $request );

	$session	= $_SESSION;
	$final		= $request;
	( new \ReflectionMethod( \Nino\Http::class, '_finalizeResponse' ) )->invokeArgs( null, [ &$final ] );
	$body			= $request['/nino/http/response']['body'] ?? null;

	return [
		'status'	=> (int) $request['/nino/http/response']['statusCode'],
		'bytes'		=> is_string( $final['/nino/http/response']['body'] ?? null ) === true ? $final['/nino/http/response']['body'] : '',
		'code'		=> is_array( $body ) === true ? (string) ( $body['code'] ?? '' ) : '',
	];
}

/** POST /_admin as the workbench's pages send it (shell process only), a guard-only action */
function askAdmin( array &$app, array &$session, ?string $token, array $server = [] ): array {

	$post = [ 'action' => 'admin/locale', 'data' => json_encode( [ 'locale' => 'en_US' ] ) ];

	if( $token !== null )
		$post['_csrf'] = $token;

	[ $copy, $request ] = boot( $app, $session, 'POST', '/_admin', $post, $server );
	\Nino\Http::response( $copy, $request );

	$session	= $_SESSION;
	$final		= $request;
	( new \ReflectionMethod( \Nino\Http::class, '_finalizeResponse' ) )->invokeArgs( null, [ &$final ] );
	$body			= $request['/nino/http/response']['body'] ?? null;

	return [
		'status'	=> (int) $request['/nino/http/response']['statusCode'],
		'bytes'		=> is_string( $final['/nino/http/response']['body'] ?? null ) === true ? $final['/nino/http/response']['body'] : '',
		'code'		=> is_array( $body ) === true ? (string) ( $body['code'] ?? '' ) : '',
	];
}

function signIn( array &$app, string $local ): array {

	$_SESSION = [];
	$copy = $app;
	load( $copy );

	if( \Nino\Auth::loginUser( $copy, $local. '@example.com', PW ) === false )
		throw new \RuntimeException( 'sign in failed for '. $local );

	return $_SESSION;
}

function visitor( array &$app ): array {

	$_SESSION = [];
	\Nino\Csrf::getToken( $app );

	return $_SESSION;
}

function tokenOf( array &$app, array $session ): string {
	return (string) ( $session[$app['./nino/uid']]['./nino/csrf/token'] ?? '' );
}

function authTokenOf( array &$app, array $session ): string {
	return (string) ( $session[$app['./nino/uid']]['./nino/auth/token'] ?? '' );
}

/** an account's stored record changed by hand in config.php, as an operator could */
function editAccount( array &$app, string $mail, callable $change ): void {

	$path = $app['./nino/test/dir']. '/private/config.php';
	$file = (array) eval( substr( (string) file_get_contents( $path ), 5 ) );
	$file['/nino/auth/user'][$mail] = $change( $file['/nino/auth/user'][$mail] );
	file_put_contents( $path, '<?php return '. var_export( $file, true ). ';' );
	clearstatcache();

	if( function_exists( 'opcache_invalidate' ) === true )
		@opcache_invalidate( $path, true );
}

function fresh( array &$app ): array {

	$_SESSION = [];
	$copy = $app;
	load( $copy );
	\Nino\Auth::init( $copy );

	return $copy;
}

/**
 *	A state of the table: the session of the browser, the token it sends (null: none) and what else the request carries
 *
 *	@return 	array										[ session, token, extra server keys ]
 */
function stateOf( string $state, array &$app, string $who ): array {

	$mail		= $who. '@example.com';
	$session	= signIn( $app, $who );
	$token		= tokenOf( $app, $session );
	$server		= [];

	switch( $state ) {
		case 'no session at all':
			return [ [], null, [] ];
		case 'visitor session, its token':
			$session = visitor( $app );
			return [ $session, tokenOf( $app, $session ), [] ];
		case 'expired session':
			$auth = authTokenOf( $app, $session );
			editAccount( $app, $mail, static function( array $u ) use ( $auth ): array { $u['sessions'][$auth]['time'] = time() - 60 * 60 * 24 * 31; return $u; } );
			break;
		case 'deactivated by hand':
			editAccount( $app, $mail, static function( array $u ): array { $u['status'] = \Nino\Auth::STATUS_DISABLED; return $u; } );
			break;
		case 'deactivated (setStatus)':
			$c = fresh( $app );
			\Nino\Auth::setStatus( $c, $mail, false );
			break;
		case 'ended everywhere':
			$c = fresh( $app );
			\Nino\Auth::logoutAllSessions( $c, $mail );
			break;
		case 'account deleted':
			$c = fresh( $app );
			\Nino\Auth::deleteUser( $c, $mail );
			break;
		case 'no token':
			$token = null;
			break;
		case 'wrong token':
			$token = str_repeat( 'a', 64 );
			break;
		case 'token of another session':
			$token = tokenOf( $app, signIn( $app, 'other' ) );
			break;
		case 'body above post_max_size':
			$token = null;
			$server = [ 'CONTENT_LENGTH' => (string) ( \Nino\Images::limits()['post'] + 1 ) ];
			break;
		case 'body at post_max_size':
			$token = null;
			$server = [ 'CONTENT_LENGTH' => (string) \Nino\Images::limits()['post'] ];
			break;
	}

	return [ $session, $token, $server ];
}

/** every file under the sandbox with its size, mtime and hash */
function files( string $dir ): array {

	clearstatcache();
	$list = [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST ) as $file ) {
		$path = (string) $file;
		$list[substr( $path, strlen( $dir ) )] = is_file( $path ) === true ? [ filesize( $path ), filemtime( $path ), md5_file( $path ) ] : 'dir';
	}

	ksort( $list );

	return $list;
}

function done( array &$app ): void {
	\Nino\Filesystem::removeDir( $app['./nino/test/dir'] );
}

$states = [ 'no session at all', 'visitor session, its token', 'expired session', 'deactivated by hand', 'deactivated (setStatus)', 'ended everywhere', 'account deleted', 'no token', 'wrong token', 'token of another session', 'body above post_max_size', 'body at post_max_size' ];

// ---------------------------------------------------------------------------------------------------------------------------------
// The shell process: the workbench's answers for the same states, as json for the process that compares
// ---------------------------------------------------------------------------------------------------------------------------------
if( $shell === true ) {

	define( 'NINO_SHELL', true );
	$answers = [];

	foreach( array_merge( $states, [ 'right missing', 'right present', 'maintenance' ] ) as $state ) {

		$app = world( 'authguardshell' );
			$who = $state === 'right missing' ? 'none' : 'holder';
		[ $session, $token, $server ] = stateOf( in_array( $state, [ 'right missing', 'right present', 'maintenance' ], true ) ? 'right present' : $state, $app, $who );
		$uri = $state === 'maintenance' ? '/probe/maint' : '/probe/api';

		$answers[$state] = [ 'api' => ask( $app, $session, 'POST', $uri, $token, $server ), 'admin' => askAdmin( $app, $session, $token, $server ) ];
		done( $app );
	}

	// What the guard of the workbench writes where a request passes: the line "Login", the base texts, the backup of the day
	$app = world( 'authguardshellw', true );
	[ $session, $token ] = stateOf( 'right present', $app, 'holder' );
	$before = files( $app['./nino/test/dir'] );
	$answers['writes'] = [ 'api' => ask( $app, $session, 'POST', '/probe/api', $token ), 'changed' => files( $app['./nino/test/dir'] ) !== $before ];
	$logs = glob( $app['./nino/test/dir']. '/private/.logs/*' ) ?: [];
	$answers['writes']['log'] = $logs !== [];
	$answers['writes']['backup'] = ( glob( $app['./nino/test/dir']. '/private/.backups/*' ) ?: [] ) !== [];
	done( $app );

	// scoped() through the workbench
	$app = world( 'authguardshells' );
	$answers['scoped'] = [];
	foreach( [ 'none', 'holder', 'scopedacc', 'wild', 'adm' ] as $who ) {
		$session = signIn( $app, $who );
		$_SESSION = $session;
		$c = $app;
		load( $c );
		\Nino\Auth::init( $c );
		$answers['scoped'][$who] = [
			\Nino\Admin\Admin::scoped( $c, '/probe/', '/probe/news/update/title' ), \Nino\Admin\Admin::scoped( $c, '/probe/', '/probe/news/update/body' ),
			\Nino\Admin\Admin::isScoped( $c, '/probe/' ), \Nino\Admin\Admin::isDescribed( [ PERM, '/probe/news/update/title' ], '/probe/' ),
		];
	}
	$_SESSION = [];
	$c = fresh( $app );
	$answers['scoped']['nobody'] = [ \Nino\Admin\Admin::scoped( $c, '/probe/', '/probe/news/update/title' ), \Nino\Admin\Admin::scoped( $c, '/probe/', '/probe/news/update/body' ), \Nino\Admin\Admin::isScoped( $c, '/probe/' ), \Nino\Admin\Admin::isDescribed( [ PERM, '/probe/news/update/title' ], '/probe/' ) ];
	done( $app );

	$answers['megabytes'] = [ \Nino\Admin\Admin::megabytes( 8 * 1048576 ), \Nino\Admin\Admin::megabytes( 524288 ), \Nino\Admin\Admin::megabytes( 1572864 ) ];

	echo json_encode( $answers );
	exit( 0 );
}

// ---------------------------------------------------------------------------------------------------------------------------------
// This process: the public guard, with the shell never loaded
// ---------------------------------------------------------------------------------------------------------------------------------
check( 'the shell of the workbench is not loaded', class_exists( '\\Nino\\Admin\\Admin', false ) === false );

$csrfBytes		= "{\n    \"error\": \"the session token is no longer valid - reload the page\",\n    \"code\": \"csrf\"\n}";
$sessionBytes	= "{\n    \"error\": \"not logged in\",\n    \"code\": \"session\"\n}";
$allowedBytes	= "{\n    \"error\": \"not allowed\",\n    \"code\": \"not_allowed\"\n}";

$expect = [
	'no session at all'					=> [ 403, $csrfBytes, 'csrf' ],
	'visitor session, its token'		=> [ 401, $sessionBytes, 'session' ],
	'expired session'						=> [ 401, $sessionBytes, 'session' ],
	'deactivated by hand'				=> [ 401, $sessionBytes, 'session' ],
	'deactivated (setStatus)'			=> [ 401, $sessionBytes, 'session' ],
	'ended everywhere'					=> [ 401, $sessionBytes, 'session' ],
	'account deleted'						=> [ 401, $sessionBytes, 'session' ],
	'no token'								=> [ 403, $csrfBytes, 'csrf' ],
	'wrong token'							=> [ 403, $csrfBytes, 'csrf' ],
	'token of another session'			=> [ 403, $csrfBytes, 'csrf' ],
	'body at post_max_size'				=> [ 403, $csrfBytes, 'csrf' ],
];

$mine = [];

foreach( $states as $state ) {

	$app = world( 'authguard' );
	[ $session, $token, $server ] = stateOf( $state, $app, 'holder' );
	$mine[$state] = [ 'api' => ask( $app, $session, 'POST', '/probe/api', $token, $server ) ];

	if( isset( $expect[$state] ) === true ) {
		$want = $expect[$state];
		check( 'POST /probe/api, '. $state. ': '. $want[0]. ' '. $want[2]. ', the bytes of the workbench', [ $mine[$state]['api']['status'], $mine[$state]['api']['bytes'], $mine[$state]['api']['code'] ] === $want );
	}

	done( $app );
}

$post = \Nino\Images::limits()['post'];
if( $post > 0 ) {
	$tooBig = $mine['body above post_max_size']['api'];
	check( 'POST /probe/api, body above post_max_size: 413 post_too_large with the limit in megabytes as the param', $tooBig['status'] === 413 && $tooBig['code'] === 'post_too_large'
		&& json_decode( $tooBig['bytes'], true ) === [ 'error' => 'the request is larger than the server accepts (post_max_size)', 'code' => 'post_too_large', 'params' => [ \Nino\Images::megabytes( $post ) ] ] );
}

// right missing, right present, right '', refused earlier
$app = world( 'authguardr' );
$mineRight = [];
foreach( [ 'right missing' => 'none', 'right present' => 'holder' ] as $state => $who ) {
	$session = signIn( $app, $who );
	$mineRight[$state] = ask( $app, $session, 'POST', '/probe/api', tokenOf( $app, $session ) );
}
check( 'right missing: 403 not allowed with the code not_allowed', [ $mineRight['right missing']['status'], $mineRight['right missing']['bytes'], $mineRight['right missing']['code'] ] === [ 403, $allowedBytes, 'not_allowed' ] );
check( 'right present: passes', $mineRight['right present']['status'] === 200 && json_decode( $mineRight['right present']['bytes'], true ) === [ 'ok' => true ] );

$session = signIn( $app, 'none' );
$answer = ask( $app, $session, 'POST', '/probe/any', tokenOf( $app, $session ) );
check( 'the right \'\': an account without any right passes', $answer['status'] === 200 );
$session = visitor( $app );
$answer = ask( $app, $session, 'POST', '/probe/any', tokenOf( $app, $session ) );
check( '...a visitor with a site token does not: 401 session', [ $answer['status'], $answer['code'] ] === [ 401, 'session' ] );
$session = signIn( $app, 'none' );
$answer = ask( $app, $session, 'POST', '/probe/unknown', tokenOf( $app, $session ) );
check( 'an unknown right: 403 not_allowed', [ $answer['status'], $answer['code'] ] === [ 403, 'not_allowed' ] );
$session = signIn( $app, 'adm' );
$answer = ask( $app, $session, 'POST', '/probe/unknown', tokenOf( $app, $session ) );
check( 'a wildcard right of everything holds an unknown right', $answer['status'] === 200 );
$session = signIn( $app, 'wild' );
check( 'a wildcard below the right holds it (/probe/*)', ask( $app, $session, 'POST', '/probe/api', tokenOf( $app, $session ) )['status'] === 200 );
$check = ask( $app, $session, 'POST', '/probe/unknown', tokenOf( $app, $session ) );
check( '...and not a right elsewhere', [ $check['status'], $check['code'] ] === [ 403, 'not_allowed' ] );
$session = signIn( $app, 'viarole' );
check( 'a right of a role holds', ask( $app, $session, 'POST', '/probe/api', tokenOf( $app, $session ) )['status'] === 200 );

// A request another global callback refused first: the public guard says 'refused', deny() leaves what is there
$session = signIn( $app, 'holder' );
$earlier = ask( $app, $session, 'POST', '/probe/maint', tokenOf( $app, $session ) );
check( 'refused earlier by another callback (503): status and body stay as that callback wrote them', [ $earlier['status'], $earlier['bytes'], $earlier['code'] ] === [ 503, "{\n    \"error\": \"maintenance\"\n}", '' ] );
$withoutToken = ask( $app, $session, 'POST', '/probe/maint', null );
check( 'a refused token wins over a callback that answered 503 after it: 403 csrf, written over the 503', [ $withoutToken['status'], $withoutToken['bytes'], $withoutToken['code'] ] === [ 403, $csrfBytes, 'csrf' ] );
[ $copy, $request ] = boot( $app, $session, 'POST', '/probe/maint', [], [ 'HTTP_X_CSRF_TOKEN' => tokenOf( $app, $session ) ], '{}' );
$request['/nino/http/response']['statusCode'] = 503;
$request['/nino/http/response']['body'] = [ 'error' => 'maintenance' ];
$refusal = \Nino\Auth::guard( $copy, $request, PERM );
check( '...guard() names it: the status the response holds and the code refused', is_array( $refusal ) === true && $refusal['status'] === 503 && $refusal['code'] === 'refused' && is_string( $refusal['error'] ) === true && $refusal['params'] === [] );
$before = $request;
\Nino\Auth::deny( $request, $refusal );
check( '...and deny() writes nothing for it', $request === $before );

// every refusal carries a code, and deny() writes exactly what guard() said
foreach( [ [ 401, 'session' ], [ 403, 'csrf' ], [ 403, 'not_allowed' ] ] as [ $status, $code ] ) {
	[ $copy, $request ] = boot( $app, $code === 'session' ? [] : signIn( $app, 'none' ), 'POST', '/probe/api', [], $code === 'csrf' ? [ 'HTTP_X_CSRF_TOKEN' => 'wrong' ] : [], '{}' );
	if( $code === 'session' ) {
		$visitor = visitor( $app );
		[ $copy, $request ] = boot( $app, $visitor, 'POST', '/probe/api', [], [ 'HTTP_X_CSRF_TOKEN' => tokenOf( $app, $visitor ) ], '{}' );
	}
	elseif( $code === 'not_allowed' ) {
		$none = signIn( $app, 'none' );
		[ $copy, $request ] = boot( $app, $none, 'POST', '/probe/api', [], [ 'HTTP_X_CSRF_TOKEN' => tokenOf( $app, $none ) ], '{}' );
	}
	$refusal = \Nino\Auth::guard( $copy, $request, PERM );
	check( 'guard() answers '. $status. ' '. $code. ' with all four keys', is_array( $refusal ) === true && array_keys( $refusal ) === [ 'status', 'error', 'code', 'params' ] && $refusal['status'] === $status && $refusal['code'] === $code );
	\Nino\Auth::deny( $request, $refusal );
	check( '...and deny() writes its status, sentence and code', $request['/nino/http/response']['statusCode'] === $status && $request['/nino/http/response']['body'] === [ 'error' => $refusal['error'], 'code' => $code ] );
}
done( $app );

// the same route with the token guard switched off ('csrf' => false): the public guard asks for the token itself
$app = world( 'authguardo' );
$holder = signIn( $app, 'holder' );
$other = signIn( $app, 'other' );
$session = $holder;
$answer = ask( $app, $session, 'POST', '/probe/open', null );
check( "'csrf' => false, POST with no token: 403 csrf", [ $answer['status'], $answer['bytes'], $answer['code'] ] === [ 403, $csrfBytes, 'csrf' ] );
$answer = ask( $app, $session, 'POST', '/probe/open', str_repeat( 'b', 64 ) );
check( "...a wrong token: 403 csrf", [ $answer['status'], $answer['code'] ] === [ 403, 'csrf' ] );
$answer = ask( $app, $session, 'POST', '/probe/open', tokenOf( $app, $other ) );
check( "...the token of another session: 403 csrf", [ $answer['status'], $answer['code'] ] === [ 403, 'csrf' ] );
$answer = ask( $app, $session, 'POST', '/probe/open', tokenOf( $app, $holder ) );
check( "...the right token: passes", $answer['status'] === 200 && json_decode( $answer['bytes'], true ) === [ 'ok' => true ] );
$answer = ask( $app, $session, 'POST', '/probe/open', tokenOf( $app, $holder ) . 'x' );
check( "...the right token with a byte added: 403 csrf", [ $answer['status'], $answer['code'] ] === [ 403, 'csrf' ] );
$empty = [];
$answer = ask( $app, $empty, 'POST', '/probe/open', null );
check( "...no session at all: 403 csrf", [ $answer['status'], $answer['code'] ] === [ 403, 'csrf' ] );
$visit = visitor( $app );
$answer = ask( $app, $visit, 'POST', '/probe/open', tokenOf( $app, $visit ) );
check( "...a visitor with the token of the site: 401 session", [ $answer['status'], $answer['code'] ] === [ 401, 'session' ] );
$none = signIn( $app, 'none' );
$answer = ask( $app, $none, 'POST', '/probe/open', tokenOf( $app, $none ) );
check( "...a signed-in account without the right: 403 not_allowed", [ $answer['status'], $answer['code'] ] === [ 403, 'not_allowed' ] );

// a GET asks for no token
$session = signIn( $app, 'holder' );
$answer = ask( $app, $session, 'GET', '/probe/api', null );
check( 'GET on a guarded route: signed in with the right passes with no token', $answer['status'] === 200 );
$visit = visitor( $app );
$answer = ask( $app, $visit, 'GET', '/probe/api', null );
check( '...a visitor: 401 session', [ $answer['status'], $answer['bytes'], $answer['code'] ] === [ 401, $sessionBytes, 'session' ] );
$empty = [];
$answer = ask( $app, $empty, 'GET', '/probe/api', null );
check( '...no session at all: 401 session, no token asked', [ $answer['status'], $answer['code'] ] === [ 401, 'session' ] );
$session = signIn( $app, 'none' );
$answer = ask( $app, $session, 'GET', '/probe/api', null );
check( '...signed in without the right: 403 not_allowed', [ $answer['status'], $answer['bytes'], $answer['code'] ] === [ 403, $allowedBytes, 'not_allowed' ] );
$session = signIn( $app, 'holder' );
foreach( [ 'HEAD', 'OPTIONS' ] as $method ) {
	[ $copy, $request ] = boot( $app, $session, $method, '/probe/api' );
	check( $method. ' asks for no token either', \Nino\Auth::guard( $copy, $request, PERM ) === null );
}
[ $copy, $request ] = boot( $app, $session, 'PUT', '/probe/api', [], [], '{}' );
check( 'a PUT with no token is refused csrf', ( \Nino\Auth::guard( $copy, $request, PERM )['code'] ?? '' ) === 'csrf' );
[ $copy, $request ] = boot( $app, $session, 'POST', '/probe/api', [], [ 'CONTENT_TYPE' => 'application/json' ], json_encode( [ '_csrf' => tokenOf( $app, $session ) ] ) );
check( 'the token as _csrf of a json body is a token', \Nino\Auth::guard( $copy, $request, PERM ) === null );
[ $copy, $request ] = boot( $app, $session, 'POST', '/probe/api', [ '_csrf' => tokenOf( $app, $session ) ], [], null );
check( 'the token as the field _csrf is a token', \Nino\Auth::guard( $copy, $request, PERM ) === null );
done( $app );

// the guard writes nothing: a world of its own for each state, on a site whose base texts are not of this version and with the daily backup on
$names = array_merge( $states, [ 'right missing', 'right present', 'GET, right present', 'GET, visitor' ] );
$writes = [];
$leftovers = [];
foreach( $names as $name ) {

	$app = world( 'authguardw', true );
	$dir = $app['./nino/test/dir'];

	if( $name === 'right missing' || $name === 'right present' || $name === 'GET, right present' ) {
		$session = signIn( $app, $name === 'right missing' ? 'none' : 'holder' );
		[ $token, $server ] = [ $name === 'GET, right present' ? null : tokenOf( $app, $session ), [] ];
	}
	elseif( $name === 'GET, visitor' )
		[ $session, $token, $server ] = [ visitor( $app ), null, [] ];
	else
		[ $session, $token, $server ] = stateOf( $name, $app, 'holder' );

	$method = str_starts_with( $name, 'GET' ) === true ? 'GET' : 'POST';
	$headers = $token !== null ? [ 'HTTP_X_CSRF_TOKEN' => $token ] : [];
	[ $copy, $request ] = boot( $app, $session, $method, '/probe/api', [], $server + $headers, $method === 'POST' ? '{}' : null );

	$filesBefore	= files( $dir );
	$sessionBefore	= $_SESSION;
	$copyBefore		= $copy;
	$requestBefore	= $request;

	\Nino\Auth::guard( $copy, $request, PERM );

	if( files( $dir ) !== $filesBefore || $_SESSION !== $sessionBefore || $copy !== $copyBefore || $request !== $requestBefore )
		$writes[] = $name;

	foreach( [ '/private/text', '/private/.logs', '/private/.backups' ] as $path )
		if( file_exists( $dir. $path ) === true )
			$leftovers[] = $name. ' '. $path;

	done( $app );
}
check( 'guard() changes no file under the project, no session value, neither $appData nor $request - for a pass and for each refusal ('. count( $names ). ' states)'. ( $writes === [] ? '' : ' - wrote on: '. implode( ', ', $writes ) ), $writes === [] );
check( '...on a site whose base texts are not of this version: no text file, no "Add Base Texts" line - and so no log, no "Login" line, no backup of the day'. ( $leftovers === [] ? '' : ' - found: '. implode( ', ', $leftovers ) ), $leftovers === [] );
check( '...and the shell is still not loaded', class_exists( '\\Nino\\Admin\\Admin', false ) === false );

// the megabytes sentence is a function of the kernel
check( 'Images::megabytes(): whole where whole (an int), one decimal where not', [ \Nino\Images::megabytes( 8 * 1048576 ), \Nino\Images::megabytes( 524288 ), \Nino\Images::megabytes( 1572864 ) ] === [ 8, 0.5, 1.5 ] );
check( '...rounded to one decimal, not two (1101005 bytes are 1.05 megabytes and are said 1.1), and a whole value is an int', \Nino\Images::megabytes( 1101005 ) === 1.1 && \Nino\Images::megabytes( 3 * 1048576 ) === 3 && \Nino\Images::megabytes( 0 ) === 0 );

// scoped() through \Nino\Auth
$app = world( 'authguards' );
$scoped = [];
foreach( [ 'none', 'holder', 'scopedacc', 'wild', 'adm' ] as $who ) {
	$session = signIn( $app, $who );
	[ $copy ] = boot( $app, $session, 'GET', '/probe/api' );
	$scoped[$who] = [ \Nino\Auth::scoped( $copy, '/probe/', '/probe/news/update/title' ), \Nino\Auth::scoped( $copy, '/probe/', '/probe/news/update/body' ), \Nino\Auth::isScoped( $copy, '/probe/' ), \Nino\Auth::isDescribed( [ PERM, '/probe/news/update/title' ], '/probe/' ) ];
}
[ $copy ] = boot( $app, [], 'GET', '/probe/api' );
$scoped['nobody'] = [ \Nino\Auth::scoped( $copy, '/probe/', '/probe/news/update/title' ), \Nino\Auth::scoped( $copy, '/probe/', '/probe/news/update/body' ), \Nino\Auth::isScoped( $copy, '/probe/' ), \Nino\Auth::isDescribed( [ PERM, '/probe/news/update/title' ], '/probe/' ) ];
done( $app );
check( 'scoped(): no scoped right - the door alone - holds everything of the panel, isScoped() false', $scoped['holder'] === [ true, true, false, true ] && $scoped['none'] === [ true, true, false, true ] );
check( '...a scoped right of this panel: the right named holds, another one of the panel does not, isScoped() true', $scoped['scopedacc'] === [ true, false, true, true ] );
check( '...a blanket /probe/* or /*: not scoped, everything holds', $scoped['wild'] === [ true, true, false, true ] && $scoped['adm'] === [ true, true, false, true ] );
check( '...no account: not scoped (the guard of the action refuses it)', $scoped['nobody'] === [ true, true, false, true ] );

// isDescribed() written out: only a right that names a single action inside the panel describes it
foreach( [
	[ 'a list with no right of the panel', [ '/other/thing' ], false ],
	[ 'the door alone', [ PERM ], false ],
	[ 'a blanket over the panel (/probe/*)', [ '/probe/*' ], false ],
	[ 'a blanket and the door', [ PERM, '/probe/*' ], false ],
	[ 'a fine right of the panel', [ '/probe/news/update/title' ], true ],
	[ 'a fine right beside the door', [ PERM, '/probe/news/update/title' ], true ],
	[ 'a fine right and a blanket', [ '/probe/*', '/probe/news/update/title' ], true ],
	[ 'a blanket below the panel (/probe/news/*)', [ '/probe/news/*' ], true ],
	[ 'a fine right of another panel', [ '/elsewhere/news/update/title' ], false ],
	[ 'a value that is no string', [ true, 5 ], false ],
	[ 'no right at all', [], false ],
] as [ $label, $list, $described ] )
	check( 'isDescribed(): '. $label. ' is '. ( $described === true ? '' : 'not ' ). 'described', \Nino\Auth::isDescribed( $list, '/probe/' ) === $described );

// deny() with a refusal that somebody built by hand
$hand = static function( array $refusal, int $status = 200 ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => $status, 'body' => 'before' ] ];
	$warned = [];
	set_error_handler( static function( int $no, string $text ) use ( &$warned ): bool { $warned[] = $no; return true; } );
	\Nino\Auth::deny( $request, $refusal );
	restore_error_handler();
	return [ $request['/nino/http/response'], $warned ];
};
[ $response, $warned ] = $hand( [ 'status' => 418, 'error' => 'teapot' ] );
check( 'deny(): a refusal with a status and an error only is written without a code and without params', $response === [ 'statusCode' => 418, 'body' => [ 'error' => 'teapot' ] ] && $warned === [] );
[ $response, $warned ] = $hand( [ 'status' => 503, 'error' => 'maintenance', 'code' => 'refused' ], 503 );
check( '...with the code refused over a response that is not 200 the response stays as it is', $response === [ 'statusCode' => 503, 'body' => 'before' ] && $warned === [] );
$closed = [ 'statusCode' => 500, 'body' => [ 'error' => 'the refusal of this request could not be written' ] ];
foreach( [
	'no status'												=> [ 'error' => 'no status', 'code' => 'x' ],
	'a status that is a string ("403")'			=> [ 'status' => '403', 'error' => 'wrong type' ],
	'status 200'											=> [ 'status' => 200, 'error' => 'ok' ],
	'status 0'												=> [ 'status' => 0, 'error' => 'zero' ],
	'status 399'											=> [ 'status' => 399, 'error' => 'below 400' ],
	'status 600'											=> [ 'status' => 600, 'error' => 'above 599' ],
	'an error that is null'							=> [ 'status' => 403, 'error' => null ],
	'an empty refusal'								=> [],
	'the code refused over a 200 response'	=> [ 'status' => 503, 'error' => 'maintenance', 'code' => 'refused' ],
] as $label => $refusal ) {
	[ $response, $warned ] = $hand( $refusal );
	check( 'deny() fails closed: '. $label. ' is answered 500 with the fixed sentence and no code, a warning is raised, the request goes on', $response === $closed && $warned === [ E_USER_WARNING ] );
}
[ $response, $warned ] = $hand( [ 'status' => 403, 'error' => 'denied', 'code' => [ 'x' ], 'params' => 'eight' ] );
check( '...a code that is an array is read as none and params that are a string as none', $response === [ 'statusCode' => 403, 'body' => [ 'error' => 'denied' ] ] && $warned === [] );
[ $response, $warned ] = $hand( [ 'status' => 403, 'error' => 'not allowed', 'code' => 'not_allowed', 'params' => [ 8 ] ] );
check( '...a complete refusal is written with its code and params', $response === [ 'statusCode' => 403, 'body' => [ 'error' => 'not allowed', 'code' => 'not_allowed', 'params' => [ 8 ] ] ] );

// ---------------------------------------------------------------------------------------------------------------------------------
// The workbench's answers for the same states, from a process that loads the shell
// ---------------------------------------------------------------------------------------------------------------------------------
check( 'the shell is still not loaded after every state', class_exists( '\\Nino\\Admin\\Admin', false ) === false );

$output = [];
$status = 1;
exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( __FILE__ ). ' --shell 2>&1', $output, $status );
$workbench = json_decode( implode( "\n", $output ), true );
check( 'the second process answers (shell loaded, same states)', $status === 0 && is_array( $workbench ) === true );

if( is_array( $workbench ) === true ) {

	foreach( $states as $state ) {
		$public = $mine[$state]['api'];
		$shellApi = $workbench[$state]['api'];
		$shellAdmin = $workbench[$state]['admin'];
		check( 'bytes of the public guard equal the workbench\'s (the same route, guardPerm()) and POST /_admin: '. $state, [ $public['status'], $public['bytes'], $public['code'] ] === [ $shellApi['status'], $shellApi['bytes'], $shellApi['code'] ] && [ $public['status'], $public['bytes'], $public['code'] ] === [ $shellAdmin['status'], $shellAdmin['bytes'], $shellAdmin['code'] ] );
	}

	$wbMissing = $workbench['right missing']['api'];
	check( 'right missing: the workbench answers 403 "not allowed" with no code, the public guard the same and the code not_allowed', $wbMissing['status'] === 403 && $wbMissing['code'] === '' && json_decode( $wbMissing['bytes'], true ) === [ 'error' => 'not allowed' ]
		&& json_decode( $mineRight['right missing']['bytes'], true ) === [ 'error' => 'not allowed', 'code' => 'not_allowed' ] );
	check( 'right present: both pass with the same bytes', $workbench['right present']['api']['status'] === 200 && $workbench['right present']['api']['bytes'] === $mineRight['right present']['bytes'] );
	check( 'refused earlier (503): the workbench leaves it as the public guard does', [ $workbench['maintenance']['api']['status'], $workbench['maintenance']['api']['bytes'] ] === [ $earlier['status'], $earlier['bytes'] ] );
	check( 'the workbench sends no code where the public guard says refused or not_allowed - and still none in the workbench (guardPerm())', $workbench['right missing']['api']['code'] === '' && $workbench['maintenance']['api']['code'] === '' );
	check( 'control: the workbench\'s guard on a passing request does write (a "Login" line, the base texts, the backup of the day), which the public guard did not', $workbench['writes']['changed'] === true && $workbench['writes']['log'] === true );
	check( 'scoped()/isScoped()/isDescribed() of the workbench answer what \\Nino\\Auth answers', $workbench['scoped'] === $scoped );
	check( 'Admin::megabytes() answers what Images::megabytes() does', $workbench['megabytes'] === [ 8, 0.5, 1.5 ] );
}

echo "\n". $ninoChecks. " checks, ". $ninoFailures. " failed, ". sprintf( '%.1f', microtime( true ) - $ninoStarted ). "s\n";
exit( $ninoFailures === 0 ? 0 : 1 );
