<?php
declare(strict_types=1);
/**
 *	Nino
 *	harness.php		The shared bootstrap of a smoke test: the kernel and the
 *								workbench shell, a sandbox project directory, check() and
 *								the summary line. The older suites carry the same few
 *								lines themselves; a new test, and every feature's own
 *								test, loads this instead.
 *
 *								A feature's test lives in its own directory
 *								(features/<Name>/tests/<key>-smoke.php) and finds the
 *								checkout it runs against three levels up - or wherever
 *								NINO_ROOT points, which is how the same test runs against
 *								another Nino version from outside the checkout:
 *
 *									$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
 *									require $root. '/tests/harness.php';
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
	$appData['./nino/filesystem/privatepath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	$appData['./nino/locales/current']				= 'de_DE';
	$appData['/nino/locales/native']					= 'de_DE';
	$appData['/nino/locales/available']				= [ 'de_DE', 'en_US' ];
	$appData['/nino/modules']									= [];
	$appData['./nino/test/sandbox']						= $sandbox;

	return $appData;
}

/**
 *	@param		array 		&$appData			(reference) A sandbox's app data
 *
 *	@return 	string									Its directory
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
 *	Print the summary, remove the sandbox, exit with the suite's status
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

	echo "\n". $GLOBALS['ninoChecks']. " checks, ". $GLOBALS['ninoFailures']. " failed\n";

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
