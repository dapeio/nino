<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	session-rotation-smoke.php	Dependency-free smoke test for what a sign-in and a sign-out do to the session: the id of the
 *												php session is a new one when an account signs in - by password, or by the step of the
 *												setup wizard that makes the first account, which signs it in without one - so that an id
 *												known before the sign-in is of no use after it, and the csrf token of the session is a new
 *												one when somebody signs in or out. A real php session: the kernel's session is started
 *												(Runtime::init(), which the other suites leave alone on purpose) with its files in the
 *												sandbox, and the output of the run is held back, since a session cannot start once the
 *												headers are out.
 *
 *	Usage: php tests/session-rotation-smoke.php
 */

define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-session-rotation-'. bin2hex( random_bytes( 6 ) ) );
mkdir( NINO_FEATURES_DIR, 0755, true );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( NINO_FEATURES_DIR ) );

$appData = ninoSandbox( 'session-rotation' );
$sandbox = ninoSandboxDir( $appData );
mkdir( $sandbox. '/sessions', 0755, true );
mkdir( $sandbox. '/private', 0755, true );
session_save_path( $sandbox. '/sessions' );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE' ] ] );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// What the run saw, held back until the output is let go: a session cannot start once the headers are out
ob_start();
\Nino\Runtime::init( $appData );

\Nino\Auth::insertUser( $appData, 'by-password@example.com', 'correct horse battery staple', [ '/*' ] );

/**
 *	The id and the csrf token of the session as it stands: the session is started where it is not, as the first write of the request does
 *
 *	@param		array 		&$appData
 *
 *	@return 	array										[ id, token ]
 */
$seen = static function( array &$appData ): array {
	\Nino\Runtime::startSession( $appData );
	return [ session_id(), \Nino\Csrf::getToken( $appData ) ];
};

$seen1 = $seen( $appData );
$byPassword = \Nino\Auth::loginUser( $appData, 'by-password@example.com', 'correct horse battery staple' );
$seen2 = $seen( $appData );

\Nino\Auth::logoutUser( $appData );
$seen3 = $seen( $appData );

$resumed = \Nino\Auth::resumeUser( $appData, 'by-password@example.com' );
$seen4 = $seen( $appData );
\Nino\Auth::logoutUser( $appData );

// The step of the wizard that makes the first account, in a project of its own with no account: the session of the visitor is a new one when the account is signed in
$wizard = ninoSandbox( 'session-rotation-wizard' );
\Nino\Filesystem::putFileContent( $wizard, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE' ] ] );
$appData = &$wizard;
$seen5 = $seen( $appData );
$_POST['data'] = json_encode( [ 'mail' => 'first@example.com', 'pw' => 'a long enough password' ] );
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $request );
$seen6 = $seen( $appData );
$signedIn = \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '';

// The same step for the account that is there changes it, and the session stays the one it is
$_POST['data'] = json_encode( [ 'mail' => 'first@example.com', 'pw' => 'another long password', 'currentPassword' => 'a long enough password' ] );
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $request );
$seen7 = $seen( $appData );
$own = $request['/nino/http/response']['statusCode'];

ob_end_clean();
unset( $_POST['data'] );

echo "Session id and token at sign-in and sign-out\n";

check( 'signing in by password gives the session an id of its own and a new csrf token: an id known before the sign-in is of no use after it', $byPassword !== false && $seen1[0] !== '' && $seen2[0] !== '' && $seen1[0] !== $seen2[0] && $seen1[1] !== $seen2[1] );
check( 'signing out gives the session a new csrf token: the token of the page that was open is of no use after it', $seen2[1] !== $seen3[1] );
check( 'signing in without the password - what a remembered device and the account step of the wizard do - rotates the id and the token as well', is_array( $resumed ) === true && $seen3[0] !== $seen4[0] && $seen3[1] !== $seen4[1] );
check( 'the wizard makes the first account and signs it in with a session id that is not the one the visitor had, and a new token', $signedIn === 'first@example.com' && $seen5[0] !== '' && $seen5[0] !== $seen6[0] && $seen5[1] !== $seen6[1] );
check( 'changing the own account in the wizard is no sign-in: the session keeps its id and its token', $own === 200 && $seen6[0] === $seen7[0] && $seen6[1] === $seen7[1] && ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '' ) === 'first@example.com' );

\Nino\Filesystem::removeDir( $sandbox );
ninoDone( $appData );
