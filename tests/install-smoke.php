<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	install-smoke.php		Dependency-free smoke test for the setup wizard
 *												(_install/Install.php). Runs against an isolated sandbox
 *												directory, never touches the real project data - in
 *												particular, it never rewrites the real _admin/Admin.php (see the
 *												Finish section below): setDevPassword() only takes a
 *												sandboxed path here, exactly so this file is safe to run
 *												against a real checkout.
 *
 *	Usage: php tests/install-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_install/Install.php';

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

set_error_handler( function() { return true; } );

$sandbox = sys_get_temp_dir(). '/nino-install-smoke-'. uniqid();
mkdir( $sandbox, 0777, true );


$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path']				= $sandbox;
$appData['./nino/filesystem/configpath']	= $sandbox. '/content';
$appData['./nino/filesystem/contentpath']	= $sandbox. '/content';
$appData['./nino/filesystem/privatepath'] = $sandbox. '/content';
$appData['/nino/dir']										= '';
$appData['/nino/locales/native']					= 'de_DE';
// Matches the real shipped config.php default - only the native locale,
// see Setup::apiApply()'s docblock for why that matters (picking just the
// native locale must not silently keep some other locale "available" too)
$appData['/nino/locales/available']			= [ 'de_DE' ];
$appData['/nino/modules']								= [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf', '\\Nino\\Modules\\Images' ];

mkdir( $sandbox. '/content/templates', 0777, true );
mkdir( $sandbox. '/images', 0777, true );
mkdir( $sandbox. '/content/text', 0777, true );
mkdir( $sandbox. '/assets', 0777, true );

\Nino\Filesystem::putFileContent( $appData, '/config.php', [
	'/nino/error/log'					=> false,
	'/nino/error/display'			=> true,
	'/nino/locales/native'		=> $appData['/nino/locales/native'],
	'/nino/locales/available'	=> $appData['/nino/locales/available'],
	'/nino/modules'						=> $appData['/nino/modules'],
	'/nino/html/assets'				=> [],
	'/nino/http/routes'				=> [],
	'/nino/auth/user' => [
		'changeme@domain.com' => [ 'pw' => '$2y$10$bdAzpYYC2Yyn3wyr.kcIf.gtjBwDKm1yNNX6oTpAoak15QHnCS2gm', 'status' => 0, 'sessions' => [], 'perms' => [ '/*' ] ],
	],
] );
$appData['/nino/auth/user'] = [
	'changeme@domain.com' => [ 'pw' => '$2y$10$bdAzpYYC2Yyn3wyr.kcIf.gtjBwDKm1yNNX6oTpAoak15QHnCS2gm', 'status' => 0, 'sessions' => [], 'perms' => [ '/*' ] ],
];
$appData['./nino/auth/baseline'] = $appData['/nino/auth/user'];

echo "Sandbox: $sandbox\n\n";


// --- Install::guard / postData -------------------------------------------

echo "Install::guard / postData\n";

// The sandbox has no password file and no marker, which is exactly what a
// project that never finished the wizard looks like
check( 'Admin::isInstalled() is false on a project that never ran the wizard', \Nino\Admin\Admin::isInstalled( $appData ) === false );
check( '...because there is no stored hash at all', \Nino\Admin\Admin::passwordHash( $appData ) === null );

$nonOkRequest = [ '/nino/http/response' => [ 'statusCode' => 404 ] ];
check( 'guard rejects a request an earlier callback already failed', \Nino\Install\Install::guard( $appData, $nonOkRequest ) === false );

$okRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'guard passes while the project is not installed yet', \Nino\Install\Install::guard( $appData, $okRequest ) === true );

// The marker alone closes the gate - that is the whole point of it: losing
// the password file must lock the admin area, never re-open the installer
$appData['/nino/install/completed'] = true;
$markedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'a project marked installed is locked out even with no password file', \Nino\Install\Install::guard( $appData, $markedRequest ) === false );
check( '...with 423, not a silent pass', $markedRequest['/nino/http/response']['statusCode'] === 423 );
check( 'isInstalled() agrees', \Nino\Admin\Admin::isInstalled( $appData ) === true );
unset( $appData['/nino/install/completed'] );

$_POST['data'] = json_encode( [ 'foo' => 'bar' ] );
check( 'postData() decodes the json payload', \Nino\Install\Install::postData() === [ 'foo' => 'bar' ] );
$_POST['data'] = 'not json';
check( 'postData() falls back to an empty array on invalid json', \Nino\Install\Install::postData() === [] );

$appData['/nino/http/routes']['GET://_install'] = [ 'uri' => '/_install', 'body' => 'stale persisted page' ];
\Nino\Install\Install::init( $appData );
check( 'init always restores the installer-owned GET route over a stale collision', $appData['/nino/http/routes']['GET://_install']['body'] === '[template /_install/templates/page-wizard]' );
check( 'init always restores the installer-owned POST route too', $appData['/nino/http/routes']['POST://_install']['uri'] === '/_install' );

echo "\n";


// --- Install::handlePost dispatch ----------------------------------------

echo "Install::handlePost dispatch\n";

$_POST['action'] = 'checks/run';
$_POST['data']	 = '{}';
$dispatchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Install::handlePost( $appData, $dispatchRequest );
check( 'a known action dispatches and succeeds', $dispatchRequest['/nino/http/response']['statusCode'] === 200 );
check( 'checks/run responds with a php/extensions/directories shape', isset( $dispatchRequest['/nino/http/response']['body']['php'], $dispatchRequest['/nino/http/response']['body']['extensions'], $dispatchRequest['/nino/http/response']['body']['directories'] ) );

$_POST['action'] = 'install/does-not-exist';
$unknownRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Install::handlePost( $appData, $unknownRequest );
check( 'an unknown action is rejected with 404', $unknownRequest['/nino/http/response']['statusCode'] === 404 );

echo "\n";


// --- Checks::apiRun --------------------------------------------------------

echo "Checks::apiRun\n";

$checksRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Checks::apiRun( $appData, $checksRequest );
$checksBody = $checksRequest['/nino/http/response']['body'];

check( 'reports the running php version', $checksBody['php']['version'] === PHP_VERSION );
check( 'gd is reported as loaded (required by CI)', $checksBody['extensions']['gd']['ok'] === true );
check( 'the project root is reported as writable', $checksBody['directories']['.']['ok'] === true );
// data/ isn't tracked in git, but by this point in the file it likely
// already exists - writing config.php above already took a Filesystem
// lock, which itself creates data/.locks as a side effect. Either way
// (not yet created, parent writable / already created, itself writable)
// it has to come back ok
check( 'a possibly-not-yet-created directory (data) still comes back ok', $checksBody['directories']['data']['ok'] === true );

echo "\n";


// --- Setup::apiLibrary / apiApply -----------------------------------------

echo "Setup::apiLibrary / apiApply\n";

$libraryRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiLibrary( $appData, $libraryRequest );
$libraryBody = $libraryRequest['/nino/http/response']['body'];

check( 'lists the two locales the library ships translations for', $libraryBody['locales'] === [ 'de_DE', 'en_US' ] );
check( 'reports the config\'s current native locale as already active', $libraryBody['activeLocales'] === [ 'de_DE' ] );
check( 'reports the config\'s current native locale itself, for the Native Locale dropdown to pre-select', $libraryBody['nativeLocale'] === 'de_DE' );
check( 'lists every module unit - pages have their own step now (Webpages), not listed here', array_keys( $libraryBody['modules'] ) === [ 'forms', 'localepicker', 'navigation', 'newsletter' ] );
check( 'no module is active yet', $libraryBody['modules']['forms']['active'] === false );

$_POST['data'] = json_encode( [ 'locales' => [], 'modules' => [] ] );
$noLocaleRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $noLocaleRequest );
check( 'rejects a selection with no locale at all', $noLocaleRequest['/nino/http/response']['statusCode'] === 400 );

// A route _install itself never wrote - eg. added by hand later through
// _admin - has to survive every apply() below untouched: apply() only ever
// owns the route keys base/modules could produce. Written straight to
// config.php on disk, the same place _admin's own Config module would leave
// it, since that (not $appData) is what apiApply() reads its starting set
// of routes from
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://custom'] = [ 'uri' => '/custom', 'body' => 'hand-written route' ];
	return $config;
} );

// Simulate exactly what a real /_install request's $appData looks like by
// the time any action runs: Install::init() (and, once a module is
// active, that module's own init()) have already added their own
// runtime-only routes to $appData['/nino/http/routes'] at boot - never
// persisted to config.php themselves, but present in memory all the same.
// apiApply() must not let any of these leak into the persisted routes.
$appData['/nino/http/routes']['GET://_install'] 	= [ 'uri' => '/_install', 'body' => '[template /_install/templates/page-wizard]', 'statusCode' => 200 ];
$appData['/nino/http/routes']['POST://_install'] = [ 'uri' => '/_install' ];
$appData['/nino/http/routes']['POST://.form'] 		= [ 'uri' => '/.form' ];

// de_DE only, "forms" picked directly
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [ 'forms' ] ] );
$applyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $applyRequest );
$applyBody = $applyRequest['/nino/http/response']['body'];

check( 'apply succeeds', $applyRequest['/nino/http/response']['statusCode'] === 200 );
check( 'reports back exactly what was picked - "forms" has no requiresModules of its own to pull in anything else', $applyBody['modules'] === [ 'forms' ] );

$configAfterApply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'picking only the already-native de_DE does not pull in en_US too', $configAfterApply['/nino/locales/available'] === [ 'de_DE' ] );
check( 'core structural modules are always present', in_array( '\\Nino\\Modules\\Template', $configAfterApply['/nino/modules'], true ) === true );
check( 'the auto-required Form module is present', in_array( '\\Nino\\Modules\\Form', $configAfterApply['/nino/modules'], true ) === true );
check( 'a module nobody picked (Newsletter) is absent', in_array( '\\Nino\\Modules\\Newsletter', $configAfterApply['/nino/modules'], true ) === false );
check( 'registers base\'s always-on robots.txt route', isset( $configAfterApply['/nino/http/routes']['GET://robots.txt'] ) === true );
check( 'a hand-written route outside the library survives apply untouched', isset( $configAfterApply['/nino/http/routes']['GET://custom'] ) === true );
check( '/_install\'s own runtime-only route never leaks into the persisted routes', isset( $configAfterApply['/nino/http/routes']['GET://_install'] ) === false && isset( $configAfterApply['/nino/http/routes']['POST://_install'] ) === false );
check( 'a module\'s self-registered runtime route (POST://.form) never leaks in either', isset( $configAfterApply['/nino/http/routes']['POST://.form'] ) === false );

check( 'copies base\'s html-header.tpl', \Nino\Filesystem::fileExists( $appData, '/templates/html-header.tpl' ) === true );
check( 'copies "forms"\'s own mail-header/footer templates', \Nino\Filesystem::fileExists( $appData, '/templates/mail-header.tpl' ) === true );
check( 'does not copy a module that was never picked (newsletter)', \Nino\Filesystem::fileExists( $appData, '/templates/page-newsletter.tpl' ) === false );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'base\'s blacklist entries (design tokens) landed in text/blacklist.php', in_array( '/website/lang', $blacklistAfterApply, true ) === true );
check( '"forms"\'s own blacklist entries (its mail design tokens) landed too', in_array( '/mail/style/color/primary', $blacklistAfterApply, true ) === true );

$deAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'merges the picked locale\'s text fragments (base + forms)', ( $deAfterApply['[[/form/title]]'] ?? null ) !== null );
check( 'never writes a fragment for a locale that was not picked', \Nino\Filesystem::fileExists( $appData, '/text/en_US.php' ) === false );

$libraryAfterApply = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiLibrary( $appData, $libraryAfterApply );
$libraryAfterApplyBody = $libraryAfterApply['/nino/http/response']['body'];

check( 'apiLibrary now reports de_DE as the active locale', $libraryAfterApplyBody['activeLocales'] === [ 'de_DE' ] );
check( 'apiLibrary now reports "forms" as active', $libraryAfterApplyBody['modules']['forms']['active'] === true );
check( 'apiLibrary now reports "newsletter" as still inactive', $libraryAfterApplyBody['modules']['newsletter']['active'] === false );

// A second run with a different, non-overlapping selection replaces the
// first run rather than adding to it: de_DE drops out, "forms" drops out
// with it, "navigation" (en_US) comes in - the hand-written and
// runtime-only routes above still have to survive
$_POST['data'] = json_encode( [ 'locales' => [ 'en_US' ], 'modules' => [ 'navigation' ] ] );
$secondApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $secondApplyRequest );
check( 'second apply succeeds', $secondApplyRequest['/nino/http/response']['statusCode'] === 200 );

$configAfterSecondApply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'replaces rather than grows: de_DE is gone, only en_US is available now', $configAfterSecondApply['/nino/locales/available'] === [ 'en_US' ] );
check( 'the native locale follows along once it is no longer available', $configAfterSecondApply['/nino/locales/native'] === 'en_US' );
check( 'the no-longer-picked Form module is gone', in_array( '\\Nino\\Modules\\Form', $configAfterSecondApply['/nino/modules'], true ) === false );
check( 'the newly-picked Navigation module is present', in_array( '\\Nino\\Modules\\Navigation', $configAfterSecondApply['/nino/modules'], true ) === true );
check( 'a hand-written route outside the library still survives the replace', isset( $configAfterSecondApply['/nino/http/routes']['GET://custom'] ) === true );
check( 'the still-present simulated runtime pollution still never leaks in, on this second apply either', isset( $configAfterSecondApply['/nino/http/routes']['GET://_install'] ) === false && isset( $configAfterSecondApply['/nino/http/routes']['POST://.form'] ) === false );
check( 'text/de_DE.php from the first run is left in place - replace only touches routes/modules/locales, never deletes content already written', \Nino\Filesystem::fileExists( $appData, '/text/de_DE.php' ) === true );
check( 'text/en_US.php now exists, written by the second run', \Nino\Filesystem::fileExists( $appData, '/text/en_US.php' ) === true );

// Explicit native-locale pick: both de_DE and en_US available, and en_US
// (the current native) is still among them, so the "keep current if still
// picked" fallback would ordinarily leave it alone - posting native=de_DE
// overrides that
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [], 'native' => 'de_DE' ] );
$nativeSwitchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $nativeSwitchRequest );
check( 'apply succeeds with a native locale posted explicitly', $nativeSwitchRequest['/nino/http/response']['statusCode'] === 200 );
check( 'response echoes the newly picked native locale', $nativeSwitchRequest['/nino/http/response']['body']['nativeLocale'] === 'de_DE' );
check( 'a posted native locale wins even though the previous native is still among the picked locales', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/locales/native'] === 'de_DE' );

// A posted native locale that isn't among this call's own picked locales
// is ignored - falls back to the same rule as posting none at all: keep
// the current native if it's still picked, else the first picked locale
$_POST['data'] = json_encode( [ 'locales' => [ 'en_US' ], 'modules' => [], 'native' => 'de_DE' ] );
$staleNativeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $staleNativeRequest );
check( 'a posted native locale outside the picked set is ignored, falling back to the (only) picked locale', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/locales/native'] === 'en_US' );

// Drop the simulated runtime-only routes again - nothing below this point
// exercises routes, but leaving them in $appData would misrepresent what
// a fresh request actually looks like for any test added here later
unset( $appData['/nino/http/routes']['GET://_install'], $appData['/nino/http/routes']['POST://_install'], $appData['/nino/http/routes']['POST://.form'] );

echo "\n";


// --- Themes::apiList / apiApply --------------------------------------------

echo "Themes::apiList / apiApply\n";

// The css bundle starts out exactly as a hand-set-up project's would:
// the kernel stylesheet plus one of the project's own, neither of them a
// library theme. Both have to survive every apply below, in place
$appData['/nino/html/assets'] = [
	'/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.custom.css' ],
	'/.cache/script.js' => [ '/_nino/Nino.js' ],
];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/assets' ] );

$themeListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiList( $appData, $themeListRequest );
$themeListBody = $themeListRequest['/nino/http/response']['body'];

check( 'lists every theme unit, one per _install/library/themes/<key>', array_keys( $themeListBody['themes'] ) === [ 'agency', 'correct', 'glow', 'kontor', 'marketplace', 'nighty', 'solid', 'wellness' ] );
check( 'each theme carries the label and description its manifest declares', $themeListBody['themes']['nighty']['label'] === 'Nighty' && $themeListBody['themes']['nighty']['description'] !== '' );
check( 'each theme carries a preview image path, served out of the library itself', $themeListBody['themes']['nighty']['preview'] === '/_install/library/themes/nighty/preview.svg' );
check( 'no theme is applied yet - the bundle carries none of the library\'s own stylesheets', $themeListBody['activeTheme'] === null );

$_POST['data'] = json_encode( [ 'theme' => 'does-not-exist' ] );
$unknownThemeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiApply( $appData, $unknownThemeRequest );
check( 'rejects an unknown theme with 400', $unknownThemeRequest['/nino/http/response']['statusCode'] === 400 );

// A traversal attempt has to be rejected the same way any other unknown
// key is - never resolved into a directory outside library/themes
$_POST['data'] = json_encode( [ 'theme' => '../modules/democontent' ] );
$traversalThemeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiApply( $appData, $traversalThemeRequest );
check( 'rejects a theme key trying to escape library/themes', $traversalThemeRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'theme' => 'nighty' ] );
$themeApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiApply( $appData, $themeApplyRequest );
check( 'apply succeeds', $themeApplyRequest['/nino/http/response']['statusCode'] === 200 );
check( 'response echoes the applied theme', $themeApplyRequest['/nino/http/response']['body']['theme'] === 'nighty' );

$configAfterTheme = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'copies the theme\'s own stylesheet into /assets', is_file( $sandbox. '/assets/style.theme.nighty.css' ) === true );
check( 'copies the webfonts that stylesheet references, keeping their subdirectories', is_file( $sandbox. '/fonts/text/lato-regular.woff2' ) === true && is_file( $sandbox. '/fonts/title/bebas-neue.woff2' ) === true );
check( 'never copies the picker-only preview image into the project', is_file( $sandbox. '/preview.svg' ) === false );
check( 'persists the picked key at /nino/install/theme', ( $configAfterTheme['/nino/install/theme'] ?? null ) === 'nighty' );
check( 'appends the theme\'s stylesheet to the css bundle', in_array( '/assets/style.theme.nighty.css', $configAfterTheme['/nino/html/assets']['/.cache/style.css'], true ) === true );
check( 'leaves the project\'s own stylesheets in the bundle alone, in order', array_slice( $configAfterTheme['/nino/html/assets']['/.cache/style.css'], 0, 2 ) === [ '/_nino/Nino.css', '/assets/style.custom.css' ] );
check( 'never touches another bundle in the same array', $configAfterTheme['/nino/html/assets']['/.cache/script.js'] === [ '/_nino/Nino.js' ] );

$themeListAfterApply = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiList( $appData, $themeListAfterApply );
check( 'apiList now reports the applied theme, for the picker to pre-select', $themeListAfterApply['/nino/http/response']['body']['activeTheme'] === 'nighty' );

// Switching themes replaces the bundled stylesheet at the position the
// previous one held, rather than adding a second one next to it
$_POST['data'] = json_encode( [ 'theme' => 'wellness' ] );
$themeSwitchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiApply( $appData, $themeSwitchRequest );

$configAfterSwitch = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'switching themes swaps the bundled stylesheet rather than adding a second one', $configAfterSwitch['/nino/html/assets']['/.cache/style.css'] === [ '/_nino/Nino.css', '/assets/style.custom.css', '/assets/style.theme.wellness.css' ] );
check( '...and updates the persisted key with it', $configAfterSwitch['/nino/install/theme'] === 'wellness' );
check( 'copies the new theme\'s own fonts too', is_file( $sandbox. '/fonts/text/pt-serif-regular.woff2' ) === true );
check( 'a file the previous theme wrote is left behind, not deleted - same additive rule as Setup\'s templates/text', is_file( $sandbox. '/assets/style.theme.nighty.css' ) === true );

// A config that predates '/nino/install/theme' (or was hand-edited since)
// still has to resolve its current theme - from the css bundle alone
unset( $appData['/nino/install/theme'] );
$themeListLegacy = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Themes::apiList( $appData, $themeListLegacy );
check( 'falls back to matching the css bundle when /nino/install/theme is absent', $themeListLegacy['/nino/http/response']['body']['activeTheme'] === 'wellness' );

echo "\n";


// --- Webpages::apiList / apiApply ------------------------------------------

echo "Webpages::apiList / apiApply\n";

// Back to a clean, known Setup state - de_DE + en_US, no content modules
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$resetSetupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $resetSetupRequest );

$wpLibraryRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiList( $appData, $wpLibraryRequest );
$wpLibraryBody = $wpLibraryRequest['/nino/http/response']['body'];

check( 'lists every page template, one per _install/library/pages/<key>', in_array( 'home', array_keys( $wpLibraryBody['templates'] ), true ) === true && in_array( 'blank', array_keys( $wpLibraryBody['templates'] ), true ) === true && in_array( 'contact', array_keys( $wpLibraryBody['templates'] ), true ) === true );
check( '"contact" declares it requires "forms"', $wpLibraryBody['templates']['contact']['requiresModules'] === [ 'forms' ] );
check( 'starts with an empty list - nothing persisted yet', $wpLibraryBody['webpages'] === [] );
check( 'no navigations are offered - Navigation was never picked', $wpLibraryBody['navs'] === [] );

// Each template also reports the starter wording its own text fragments
// ship, so the form can prefill a new entry per locale instead of leaving
// every locale nobody hand-typed on DEFAULT_TEXT (see _suggestions())
check( 'reports "home"\'s own suggested wording for every picked locale, not just one', array_keys( $wpLibraryBody['templates']['home']['text'] ) === [ 'de_DE', 'en_US' ] );
check( '...with de_DE\'s wording read from the unit\'s own de_DE fragment', $wpLibraryBody['templates']['home']['text']['de_DE']['name'] === 'Startseite' );
check( '...and en_US\'s from its own en_US fragment - the locale that used to end up generic', $wpLibraryBody['templates']['home']['text']['en_US']['name'] === 'Home' && $wpLibraryBody['templates']['home']['text']['en_US']['title'] === 'Welcome.' );
check( 'reports the Http-URI "home" suggests for itself, which is not its folder name', $wpLibraryBody['templates']['home']['uri'] === '/' );
check( '...and "contact"\'s, which is', $wpLibraryBody['templates']['contact']['uri'] === '/contact' );
check( 'the blank template starts every locale with useful page metadata', $wpLibraryBody['templates']['blank']['text']['de_DE']['name'] === 'Neue Webseite' && $wpLibraryBody['templates']['blank']['text']['en_US']['name'] === 'New webpage' );
check( 'the blank template suggests a stable Http-URI and its own page template', $wpLibraryBody['templates']['blank']['uri'] === '/new-webpage' && $wpLibraryBody['templates']['blank']['body'] === '[template /templates/page-blank]' );
check( 'the blank page template deliberately has no section', strpos( (string) file_get_contents( __DIR__. '/../_install/library/pages/blank/templates/page-blank.tpl' ), '<section' ) === false );
check( 'a template whose fragments ship no wording at all reports empty strings, never a missing key', array_keys( $wpLibraryBody['templates']['.demo-elements']['text']['en_US'] ) === [ 'name', 'title', 'description' ] );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '../etc/passwd', 'httpUri' => '/x', 'template' => 'home', 'text' => [] ] ] ] );
$badUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $badUriRequest );
check( 'rejects an unsafe Element-URI with 400', $badUriRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/x', 'httpUri' => '../etc/passwd', 'template' => 'home', 'text' => [] ] ] ] );
$badHttpUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $badHttpUriRequest );
check( 'rejects an unsafe Http-URI with 400', $badHttpUriRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/x', 'httpUri' => '/x', 'template' => 'does-not-exist', 'text' => [] ] ] ] );
$badTemplateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $badTemplateRequest );
check( 'rejects an unknown template with 400', $badTemplateRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/installer-shadow', 'httpUri' => '/_install', 'template' => 'home', 'text' => [] ] ] ] );
$reservedHttpUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $reservedHttpUriRequest );
check( 'rejects a Webpage mounted on the installer\'s own runtime uri', $reservedHttpUriRequest['/nino/http/response']['statusCode'] === 409 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/designer-shadow', 'httpUri' => '/_templates', 'template' => 'home', 'text' => [] ] ] ] );
$reservedDesignerRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $reservedDesignerRequest );
check( 'rejects a Webpage mounted on the Template Builder runtime uri', $reservedDesignerRequest['/nino/http/response']['statusCode'] === 409 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/custom-page', 'httpUri' => '/custom', 'template' => 'home', 'text' => [] ] ] ] );
$foreignRouteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $foreignRouteRequest );
check( 'rejects an Http-URI already owned by a non-Webpages route', $foreignRouteRequest['/nino/http/response']['statusCode'] === 409 );
check( 'the colliding hand-written route remains untouched', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://custom']['body'] ?? null ) === 'hand-written route' );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/x', 'httpUri' => '/x', 'template' => 'home', 'text' => [] ],
	[ 'uri' => '/x', 'httpUri' => '/y', 'template' => 'contact', 'text' => [] ],
] ] );
$dupeUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dupeUriRequest );
check( 'rejects a duplicate Element-URI with 400', $dupeUriRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/x', 'httpUri' => '/x', 'template' => 'home', 'text' => [] ],
	[ 'uri' => '/y', 'httpUri' => '/x', 'template' => 'contact', 'text' => [] ],
] ] );
$dupeHttpUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dupeHttpUriRequest );
check( 'rejects a duplicate Http-URI with 400, even when the Element-URIs differ', $dupeHttpUriRequest['/nino/http/response']['statusCode'] === 400 );

// The actual, first real apply: home at Element-URI "/site-home" / Http-URI
// "/" (nav on) + contact at "/site-contact" / "/kontakt" (nav on, drags in
// forms via requiresModules) - Element-URI deliberately differs from
// Http-URI throughout, and from the template's own folder name too, to
// prove the class uses the entry's own uri, not the request path or the
// template name, for the meta namespace (see _routeKeys()'s docblock).
// Only de_DE's text is posted for one field, proving the missing en_US/
// other fields fall back to the generic placeholder rather than failing
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'template' => 'home', 'nav' => true, 'text' => [
		'de_DE' => [ 'name' => 'Start', 'title' => 'Willkommen', 'description' => 'Startseite' ],
		'en_US' => [ 'name' => 'Home', 'title' => 'Welcome', 'description' => 'Home page' ],
	] ],
	[ 'uri' => '/site-contact', 'httpUri' => '/kontakt', 'template' => 'contact', 'nav' => true, 'text' => [
		'de_DE' => [ 'name' => 'Kontakt' ],
	] ],
] ] );
$wpApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $wpApplyRequest );
check( 'apply succeeds', $wpApplyRequest['/nino/http/response']['statusCode'] === 200 );
check( 'response echoes both entries back', count( $wpApplyRequest['/nino/http/response']['body']['webpages'] ) === 2 );

$configAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'registers a route at "/" for the home entry, keyed by its Http-URI', isset( $configAfterWpApply['/nino/http/routes']['GET://'] ) === true );
check( 'the route\'s own "uri" data field is the Element-URI, not the Http-URI', $configAfterWpApply['/nino/http/routes']['GET://']['uri'] === '/site-home' );
check( 'registers a route at "/kontakt" for the contact entry, keyed by its Http-URI', isset( $configAfterWpApply['/nino/http/routes']['GET://kontakt'] ) === true );
check( 'auto-pulls "forms" (contact\'s requiresModules)', in_array( '\\Nino\\Modules\\Form', $configAfterWpApply['/nino/modules'], true ) === true );
check( 'the routes are the whole persisted list - no second copy of it anywhere in config.php', isset( $configAfterWpApply['/nino/install/webpages'] ) === false );
check( 'the page routes stand in the posted order, Element-URI', array_values( array_map( fn( array $r ): string => $r['uri'], array_filter( $configAfterWpApply['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Install\Webpages::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) ) === [ '/site-home', '/site-contact' ] );
check( '...and Http-URI (the route keys themselves)', array_keys( array_filter( $configAfterWpApply['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Install\Webpages::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === [ 'GET://', 'GET://kontakt' ] );
check( 'a hand-written route outside the library still survives Webpages apply too', isset( $configAfterWpApply['/nino/http/routes']['GET://custom'] ) === true );

check( 'copies "forms"\'s own mail-header/footer templates too, auto-pulled in by "contact"', \Nino\Filesystem::fileExists( $appData, '/templates/mail-header.tpl' ) === true );
check( 'copies "contact"\'s own template', \Nino\Filesystem::fileExists( $appData, '/templates/page-contact.tpl' ) === true );

$deAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );

check( 'writes the home entry\'s own de_DE meta, keyed by its Element-URI (not its Http-URI or the "home" template name)', $deAfterWpApply['[[/webpage/site-home/name]]'] === 'Start' && $deAfterWpApply['[[/webpage/site-home/title]]'] === 'Willkommen' );
check( 'writes the home entry\'s own en_US meta too', $enAfterWpApply['[[/webpage/site-home/name]]'] === 'Home' );
check( 'a field left blank in the post (contact\'s en_US) falls back to the generic placeholder, not the "contact" template\'s own wording', $enAfterWpApply['[[/webpage/site-contact/title]]'] === 'Page Title' );
check( 'a field that was posted (contact\'s de_DE name) is used as-is', $deAfterWpApply['[[/webpage/site-contact/name]]'] === 'Kontakt' );
check( 'a template\'s own /webpage/<foldername>/* meta is never merged in - only the Element-URI-keyed one this class writes itself', isset( $deAfterWpApply['[[/webpage/home/name]]'] ) === false );
check( 'a template\'s own deeper content (unprefixed, shared across instances) still merges in', isset( $deAfterWpApply['[[/page-home/welcome/title]]'] ) === true );

$libraryAfterWpApply = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiList( $appData, $libraryAfterWpApply );
check( 'apiList now reflects the persisted, current list', array_column( $libraryAfterWpApply['/nino/http/response']['body']['webpages'], 'httpUri' ) === [ '/', '/kontakt' ] );

// Navigation: only shows up once the module is active, one "httpUri:name"
// line per entry with nav checked (a real, clickable href - not the
// Element-URI), regenerated (not merged) on every apply
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [ 'navigation' ] ] );
$navSetupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $navSetupRequest );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'template' => 'home', 'nav' => true, 'text' => [ 'de_DE' => [ 'name' => 'Start' ], 'en_US' => [ 'name' => 'Home' ] ] ],
	[ 'uri' => '/site-contact', 'httpUri' => '/kontakt', 'template' => 'contact', 'nav' => true, 'text' => [ 'de_DE' => [ 'name' => 'Kontakt' ], 'en_US' => [ 'name' => 'Contact' ] ] ],
	[ 'uri' => '/site-legal', 'httpUri' => '/impressum', 'template' => 'legal', 'nav' => false, 'text' => [ 'de_DE' => [ 'name' => 'Recht' ], 'en_US' => [ 'name' => 'Legal' ] ] ],
] ] );
$navWpApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $navWpApplyRequest );

$deAfterNav = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enAfterNav = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );

// Menu membership lives on the route each entry owns, not in a generated
// textfill - see \Nino\Modules\Navigation::routeLines(). Nothing is written
// per locale here at all: the menu is built per request, from the same
// /webpage<uri>/name keys the entries already carry
$routesAfterNav = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];

check( 'the Navigation module registers the menus the editors offer', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navs'] === [ 'main', 'footer' ] );
check( 'a nav-checked entry joins the first registered menu on its own route, at its own position in the list', ( $routesAfterNav['GET://']['navs'] ?? null ) === [ 'main' => 1 ] );
check( '...and so does the second one, one position further down', ( $routesAfterNav['GET://kontakt']['navs'] ?? null ) === [ 'main' => 2 ] );
check( 'an entry that is in no menu carries no membership at all', isset( $routesAfterNav['GET://impressum']['navs'] ) === false );
check( 'nothing is generated into the text files anymore', isset( $deAfterNav['[[/website/navigation/main]]'] ) === false && isset( $enAfterNav['[[/website/navigation/main]]'] ) === false );

// The routes stand in list order, which is what equal priorities fall back
// to - reordering pages is what reorders the menus
check( 'the applied routes stand in the list\'s own order', array_slice( array_keys( $routesAfterNav ), -3 ) === [ 'GET://', 'GET://kontakt', 'GET://impressum' ] );

// The legal template's single route (see _routeKeys()' docblock) is
// registered at whatever Http-URI the entry picked, its body driven by
// [[/nino/http/response/locale]] rather than a locale-gated second route
check( 'registers "legal" at its own picked Http-URI', isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://impressum'] ) === true );

$globalAfterLegal = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'mirrors the legal entry\'s Http-URI (a real href, not its Element-URI) into the well-known /website/legal/uri key', $globalAfterLegal['[[/website/legal/uri]]'] === '/impressum' );
check( 'mirrors its de_DE name into /website/legal/name too', $deAfterNav['[[/website/legal/name]]'] === 'Recht' );

// Dropping legal again narrows the routes (replace semantics) but leaves
// the /website/legal/* mirror alone - see _applyLegalLink()'s docblock
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'template' => 'home', 'nav' => true, 'text' => [ 'de_DE' => [ 'name' => 'Start' ], 'en_US' => [ 'name' => 'Home' ] ] ],
] ] );
$dropWpApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dropWpApplyRequest );

$configAfterDrop = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'dropping "kontakt"/"impressum" removes their routes (replace, not merge)', isset( $configAfterDrop['/nino/http/routes']['GET://kontakt'] ) === false && isset( $configAfterDrop['/nino/http/routes']['GET://impressum'] ) === false );
check( 'the home route survives, still keyed the same way', isset( $configAfterDrop['/nino/http/routes']['GET://'] ) === true );
check( 'a hand-written route still survives this replace too', isset( $configAfterDrop['/nino/http/routes']['GET://custom'] ) === true );
check( '/website/legal/uri is only ever set, never cleared - known v1 limitation, see docs/_install.md', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/website/legal/uri]]'] ?? null ) === '/impressum' );

echo "\n";


// --- Webpages <-> _admin's Routes module share one source of truth ------------

echo "Webpages <-> _admin's Routes module share one source of truth\n";

// Neither tool keeps a list of its own: both derive one from
// /nino/http/routes plus the /webpage<uri>/* keys in the text files (see
// Webpages::pages() and Admin.php's PageEditor::pages()). That only works if
// the route really carries everything either side needs to reopen an entry -
// its Element-URI, its body and its status code. Without that, every shipped
// page is unopenable in /_admin, and saving the 404 page there quietly turns
// it into a 200.
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'nav' => true, 'text' => [ 'de_DE' => [ 'name' => 'Start' ] ] ],
	[ 'uri' => '/site-404', 'httpUri' => '/404', 'libraryKey' => '404', 'nav' => false, 'text' => [ 'de_DE' => [ 'name' => 'Weg' ] ] ],
	[ 'uri' => '/site-legal', 'httpUri' => '/legal', 'libraryKey' => 'legal', 'nav' => false, 'text' => [ 'de_DE' => [ 'name' => 'Recht' ] ] ],
] ] );
$sharedApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $sharedApplyRequest );
check( 'apply succeeds', $sharedApplyRequest['/nino/http/response']['statusCode'] === 200 );

$sharedConfig = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$sharedList 	= $sharedApplyRequest['/nino/http/response']['body']['webpages'];

check( 'reports "template" as the on-disk template file /_admin selects from', array_column( $sharedList, 'template' ) === [ 'page-home', 'page-404', '' ] );
check( 'resolves each entry back to the library unit it came from, from its route body alone', array_column( $sharedList, 'libraryKey' ) === [ 'home', '404', 'legal' ] );
check( 'reports each entry\'s status code, read back off its route', array_column( $sharedList, 'statusCode' ) === [ 200, 404, 200 ] );
check( 'the 404 entry really is a 404 in the route too', ( $sharedConfig['/nino/http/routes']['GET://404']['statusCode'] ?? 200 ) === 404 );
check( '"legal" reports no single template - its body resolves one per locale', $sharedList[2]['body'] === '[template /templates/page-legal.[[/nino/http/response/locale]]]' );

// Now the other direction: open one of those entries in /_admin's Routes module
// and save it back unchanged, exactly as pages.js posts it
\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

$adminListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] 	= json_encode( [] );
\Nino\Admin\PageEditor::apiList( $appData, $adminListRequest );
$adminPages = $adminListRequest['/nino/http/response']['body']['pages'];
$adminTemplates = $adminListRequest['/nino/http/response']['body']['templates'];

check( 'every Webpages-made entry names a template /_admin actually offers', count( array_filter(
	$adminPages,
	fn( array $entry ): bool => $entry['template'] !== '' && in_array( $entry['template'], $adminTemplates, true ) === false
) ) === 0 );

$_POST['data'] = json_encode( [
	'originalHttpUri' => '/404', 'uri' => '/site-404', 'httpUri' => '/404',
	'template' => $adminPages[1]['template'], 'nav' => false,
	'statusCode' => $adminPages[1]['statusCode'], 'text' => $adminPages[1]['text'],
] );
$adminSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\PageEditor::apiSave( $appData, $adminSaveRequest );
check( 'saving a Webpages-made entry from /_admin succeeds', $adminSaveRequest['/nino/http/response']['statusCode'] === 200 );

$afterDevSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'saving it there does not quietly reset its 404 to a 200', ( $afterDevSave['/nino/http/routes']['GET://404']['statusCode'] ?? 200 ) === 404 );
check( 'the entry still resolves to /_install\'s own "404" unit after a save made in /_admin', ( \Nino\Install\Webpages::pages( $appData, $afterDevSave['/nino/http/routes'], [ 'de_DE' ], [] )[1]['libraryKey'] ?? null ) === '404' );

// The locale-resolving body the template <select> can't spell: saving that
// entry from /_admin keeps the body it already has rather than flattening it
// into whichever option the disabled select happened to preselect
$_POST['data'] = json_encode( [
	'originalHttpUri' => '/legal', 'uri' => '/site-legal', 'httpUri' => '/legal',
	'template' => '', 'nav' => false, 'statusCode' => 200, 'text' => $adminPages[2]['text'],
] );
$adminLegalRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\PageEditor::apiSave( $appData, $adminLegalRequest );
check( 'saving the locale-resolving entry from /_admin succeeds', $adminLegalRequest['/nino/http/response']['statusCode'] === 200 );

$afterLegalSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'its runtime-resolved body is kept, not flattened to one locale\'s file', $afterLegalSave['/nino/http/routes']['GET://legal']['body'] === '[template /templates/page-legal.[[/nino/http/response/locale]]]' );
check( '...and the derived list claims no template for it', ( \Nino\Install\Webpages::pages( $appData, $afterLegalSave['/nino/http/routes'], [ 'de_DE' ], [] )[2]['template'] ?? null ) === '' );

// An entry /_admin created has no library unit at all - Webpages has to carry
// it through its own replace rather than reject it as an unknown template
$_POST['data'] = json_encode( [
	'originalHttpUri' => '', 'uri' => '/dev-made', 'httpUri' => '/dev-made',
	'template' => 'page-home', 'nav' => false, 'statusCode' => 201, 'text' => [],
] );
$adminNewRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\PageEditor::apiSave( $appData, $adminNewRequest );
check( 'creating a page in /_admin succeeds', $adminNewRequest['/nino/http/response']['statusCode'] === 200 );

$beforeReapply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$_POST['data'] = json_encode( [ 'webpages' => \Nino\Install\Webpages::pages( $appData, $beforeReapply['/nino/http/routes'], [ 'de_DE' ], [] ) ] );
$reapplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $reapplyRequest );
check( 'Webpages re-applies a list containing a /_admin-made entry', $reapplyRequest['/nino/http/response']['statusCode'] === 200 );

$afterReapply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the /_admin-made entry keeps its route through that replace', isset( $afterReapply['/nino/http/routes']['GET://dev-made'] ) === true );
check( '...including its own status code', ( $afterReapply['/nino/http/routes']['GET://dev-made']['statusCode'] ?? 200 ) === 201 );
check( 'the library-backed entries still resolve their own units too', ( $afterReapply['/nino/http/routes']['GET://404']['statusCode'] ?? 200 ) === 404 );

$_POST['data'] = json_encode( [
	'originalHttpUri' => '/404', 'uri' => '/site-404', 'httpUri' => '/404',
	'template' => 'page-nope', 'nav' => false, 'statusCode' => 404, 'text' => [],
] );
$adminBadRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\PageEditor::apiSave( $appData, $adminBadRequest );
check( 'a genuinely unknown template is still rejected', $adminBadRequest['/nino/http/response']['statusCode'] === 400 );

\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );

echo "\n";


// --- PersonalInfos::apiList / apiSaveBatch ---------------------------------

echo "PersonalInfos::apiList / apiSaveBatch\n";

// Deliberately overwrites whatever the Webpages section left behind above -
// this section is independently scoped, only real
// _install/library/base key *names* matter here (apiList() filters
// against the real library on disk, not anything sandboxed), plus a
// couple of made-up, clearly-out-of-scope keys to prove both "not
// /company or /website" and "webpage meta, even though it looks similar"
// content stays out of this step. Locales/available is reset too, back to
// both - the Webpages section above deliberately ends on a narrowed list
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
	return $config;
} );
$appData['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/company/name]]' => 'Acme Inc', '[[/website/author]]' => 'Acme Inc' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/company/country]]' => 'Deutschland', '[[/website/lang]]' => 'de', '[[/home/headline]]' => 'Willkommen', '[[/webpage/kontakt/name]]' => 'Kontakt' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/company/country]]' => 'Germany', '[[/website/lang]]' => 'en', '[[/home/headline]]' => 'Welcome', '[[/webpage/kontakt/name]]' => 'Contact' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', [ '/website/lang' ] );

$personalInfosListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\PersonalInfos::apiList( $appData, $personalInfosListRequest );
$personalInfosBody = $personalInfosListRequest['/nino/http/response']['body'];
$personalInfosEntries = $personalInfosBody['entries'];
$personalInfosKeys = array_column( $personalInfosEntries, 'key' );

check( 'lists the locales', $personalInfosBody['locales'] === [ 'de_DE', 'en_US' ] );
check( 'a /company/* key is listed', array_search( '/company/name', $personalInfosKeys, true ) !== false );
check( 'a /website/* key is listed', array_search( '/company/country', $personalInfosKeys, true ) !== false );
check( 'a blacklisted key is left out even though it\'s a /website/* key', array_search( '/website/lang', $personalInfosKeys, true ) === false );
check( 'a key outside /company/* and /website/* is left out', array_search( '/home/headline', $personalInfosKeys, true ) === false );
check( 'a webpage\'s own meta key is left out too, despite existing in text/*.php', array_search( '/webpage/kontakt/name', $personalInfosKeys, true ) === false );

$personalInfosLabels = array_column( $personalInfosEntries, 'label', 'key' );
check( 'derives a friendly label by capitalizing each path segment', $personalInfosLabels['/company/name'] === 'Company Name' );
check( 'same for a /website/* key', $personalInfosLabels['/website/author'] === 'Website Author' );

$saveBatchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'items' => [
	[ 'key' => '/company/country', 'locale' => 'de_DE', 'value' => 'Musterland' ],
	[ 'key' => '/company/country', 'locale' => 'en_US', 'value' => 'Sample Country' ],
] ] );
\Nino\Install\PersonalInfos::apiSaveBatch( $appData, $saveBatchRequest );
$saveResults = $saveBatchRequest['/nino/http/response']['body']['results'];

check( 'saves the de_DE value', $saveResults['/company/country']['ok'] === true && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/company/country]]'] ?? null ) === 'Musterland' );
check( 'saves the en_US value', ( \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/company/country]]'] ?? null ) === 'Sample Country' );

echo "\n";


// --- Editor::apiList / apiCreate -------------------------------------------

echo "Editor::apiList / apiCreate\n";

$adminListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Admin::apiList( $appData, $adminListRequest );
check( 'does not count the shipped, disabled placeholder as a usable admin account', $adminListRequest['/nino/http/response']['body']['users'] === [] );

$_POST['data'] = json_encode( [ 'password' => 'a-long-enough-admin-password' ] );
$finishWithoutAdminRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $finishWithoutAdminRequest );
check( 'refuses to lock the installer before an active _editor account exists', $finishWithoutAdminRequest['/nino/http/response']['statusCode'] === 409 );

$_POST['data'] = json_encode( [ 'mail' => 'not-an-email', 'pw' => 'a-long-enough-password' ] );
$invalidMailRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Admin::apiCreate( $appData, $invalidMailRequest );
check( 'rejects an invalid email with 400', $invalidMailRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'mail' => 'admin@example.com', 'pw' => 'short' ] );
$shortPwRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Admin::apiCreate( $appData, $shortPwRequest );
check( 'rejects a too-short password with 400', $shortPwRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'mail' => 'admin@example.com', 'pw' => 'a-long-enough-password' ] );
$createRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Admin::apiCreate( $appData, $createRequest );
check( 'creates the account', \Nino\Auth::getUser( $appData, 'admin@example.com' ) !== false );
check( 'drops the shipped placeholder account once a real admin exists', \Nino\Auth::getUser( $appData, 'changeme@domain.com' ) === false );
check( 'returns only the newly usable account to the frontend', $createRequest['/nino/http/response']['body']['users'] === [ 'admin@example.com' ] );
check( 'the new account can actually authenticate', \Nino\Auth::loginUser( $appData, 'admin@example.com', 'a-long-enough-password' ) !== false );
\Nino\Auth::logoutUser( $appData );

$_POST['data'] = json_encode( [ 'mail' => 'admin@example.com', 'pw' => 'a-different-password' ] );
$replaceRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Admin::apiCreate( $appData, $replaceRequest );
check( 'creating the same address again replaces it rather than failing', $replaceRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the replaced account uses the new password', \Nino\Auth::loginUser( $appData, 'admin@example.com', 'a-different-password' ) !== false );
\Nino\Auth::logoutUser( $appData );

echo "\n";


// --- Finish::apiComplete / Install::setDevPassword ------------------------

echo "Finish::apiComplete / Install::setDevPassword\n";

$_POST['data'] = json_encode( [ 'password' => 'short' ] );
$shortFinishRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $shortFinishRequest );
check( 'rejects a too-short _admin password with 400', $shortFinishRequest['/nino/http/response']['statusCode'] === 400 );

// setDevPassword() no longer rewrites php source: it stores the hash under
// the content directory, outside every tool folder and outside config.php.
// _admin/Admin.php therefore stays byte-identical, which is what makes it
// replaceable on an update (see Install::setDevPassword()'s docblock)
$adminBefore = file_get_contents( __DIR__. '/../_admin/Admin.php' );

$_POST['data'] = json_encode( [ 'password' => 'a brand new dev password' ] );
$finishRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $finishRequest );
check( 'apiComplete succeeds once an editor account exists', $finishRequest['/nino/http/response']['statusCode'] === 200 );

check( 'the real _admin/Admin.php was not touched', file_get_contents( __DIR__. '/../_admin/Admin.php' ) === $adminBefore );

$pwPath = \Nino\Admin\Admin::passwordPath( $appData );
check( 'the hash lands under the content directory', $pwPath === $sandbox. '/content/.auth/pw.php' && is_file( $pwPath ) === true );

$pwRaw = file_get_contents( $pwPath );
check( 'it is wrapped in the self-exiting 403 stub', str_starts_with( $pwRaw, \Nino\Admin\Admin::STUB_PREFIX ) === true && str_ends_with( $pwRaw, \Nino\Admin\Admin::STUB_SUFFIX ) === true );
// Run in a subprocess, not inline: the stub's whole job is to exit(), which
// would take this test run with it. What matters is that executing the file -
// which is what a webserver that happily serves it would do - prints nothing
$stubOutput = (string) shell_exec( 'php -r '. escapeshellarg( 'include '. var_export( $pwPath, true ). ';' ). ' 2>&1' );
check( 'executing that file prints nothing - the hash never reaches a response body', trim( $stubOutput ) === '' );

check( 'passwordHash() reads it back', password_verify( 'a brand new dev password', (string) \Nino\Admin\Admin::passwordHash( $appData ) ) === true );
check( 'the project is now installed', \Nino\Admin\Admin::isInstalled( $appData ) === true );
check( '...and the marker is persisted, so losing the file cannot re-open the wizard', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/install/completed'] ?? false ) === true );

// The credential is not part of what a backup restores - a backup recovers
// content, and a stolen archive must not carry an admin hash
check( 'the password file is not in the backup manifest', count( array_filter(
	array_keys( \Nino\Backup::manifest( $appData ) ),
	fn( string $file ): bool => str_contains( $file, '/.auth/' )
) ) === 0 );

// Deleting it locks the admin area rather than handing back the installer
unlink( $pwPath );
check( 'a missing password file leaves no usable hash', \Nino\Admin\Admin::passwordHash( $appData ) === null );
check( '...but the project still counts as installed', \Nino\Admin\Admin::isInstalled( $appData ) === true );

$reopenRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'so /_install stays locked', \Nino\Install\Install::guard( $appData, $reopenRequest ) === false );

// A half-written file must read as "no password", never as a hash that just
// happens not to match
file_put_contents( $pwPath, \Nino\Admin\Admin::STUB_PREFIX );
check( 'a truncated password file reads as no password at all', \Nino\Admin\Admin::passwordHash( $appData ) === null );
unlink( $pwPath );

check( 'writePasswordHash() refuses an empty hash rather than storing one nothing can match', \Nino\Admin\Admin::writePasswordHash( $appData, '' ) === false );
check( 'setDevPassword() can write the file again afterwards', \Nino\Install\Install::setDevPassword( $appData, 'another dev password' ) === true );
check( '...and the new password is the one that verifies', password_verify( 'another dev password', (string) \Nino\Admin\Admin::passwordHash( $appData ) ) === true );

check( 'the content directory carries its own deny rule', is_file( $sandbox. '/content/.htaccess' ) === true );

echo "\n";


// --- Shipped defaults (the real, git-tracked config.php + library) ---------

// Read-only sanity check on the actual checkout, not the sandbox above. The
// project ships config.php already describing the four default pages, but
// not the templates/text/assets they render from - those live in
// _install/library and only land on disk once the wizard runs (see
// docs/_install.md). So this guards two things: that the shipped config
// still describes that starter site, and that the library it draws on can
// actually produce it
echo "Shipped defaults (real config.php + _install/library)\n";

$realRoot 	= __DIR__. '/..';
$realConfig = include $realRoot. '/content/config.php';

$realAppData 		= [ '/nino/locales/available' => $realConfig['/nino/locales/available'] ?? [], '/nino/configpath' => $realRoot ];
$realPageRoutes = array_filter( $realConfig['/nino/http/routes'] ?? [], fn( array $r, string $k ): bool => \Nino\Install\Webpages::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH );
$realPages 			= \Nino\Install\Webpages::pages( $realAppData, $realConfig['/nino/http/routes'] ?? [], [], $realConfig['/nino/html/navs'] ?? [] );

check( 'the real config.php returns an array', is_array( $realConfig ) === true );
check( 'ships exactly the four default page routes, in order, Element-URI', array_column( $realPages, 'uri' ) === [ '/home', '/contact', '/404', '/legal' ] );
check( '...and Http-URI', array_column( $realPages, 'httpUri' ) === [ '/', '/contact', '/404', '/legal' ] );
check( 'no second copy of that list is shipped alongside the routes', isset( $realConfig['/nino/install/webpages'] ) === false );
check( 'registers the home entry\'s route at "/" (its Http-URI), "uri" data field is its Element-URI', ( ( $realConfig['/nino/http/routes']['GET://'] ?? [] )['uri'] ?? null ) === '/home' );
check( 'registers the 404 entry at "/404" too - the exact key Http::response()\'s own fallback lookup needs', isset( $realConfig['/nino/http/routes']['GET://404'] ) === true );
check( 'registers "legal" and "contact" as well', isset( $realConfig['/nino/http/routes']['GET://legal'] ) === true && isset( $realConfig['/nino/http/routes']['GET://contact'] ) === true );
check( 'auto-pulled "forms" (contact\'s requiresModules) into /nino/modules', in_array( '\\Nino\\Modules\\Form', $realConfig['/nino/modules'] ?? [], true ) === true );
check( 'ships both library locales available', $realConfig['/nino/locales/available'] === [ 'en_US', 'de_DE' ] );
check( 'a theme is bundled into /nino/html/assets', preg_match( '#^/assets/style\.theme\.[a-z0-9-]+\.css$#', (string) ( $realConfig['/nino/html/assets']['/.cache/style.css'][1] ?? '' ) ) === 1 );
check( '...and named by the theme step\'s own persisted key', ( $realConfig['/nino/install/theme'] ?? null ) === 'agency' );

// Every shipped route resolves back to the on-disk template file it renders,
// and to the library unit it came from - what lets /_admin's Routes module
// work with the shipped pages at all (see the shared-source-of-truth section
// above)
check( 'each shipped route resolves to its own on-disk template', array_column( $realPages, 'template' ) === [ 'page-home', 'page-contact', 'page-404', '' ] );
check( '...and to the library unit behind it', array_column( $realPages, 'libraryKey' ) === [ 'home', 'contact', '404', 'legal' ] );
check( 'the shipped 404 route really carries a 404', array_column( $realPages, 'statusCode' ) === [ 200, 200, 404, 200 ] );

// The menus are on the routes themselves, densely numbered by the position
// the wizard's list had them in (see Webpages::apiApply())
check( 'the registry the page editors offer checkboxes for is shipped', ( $realConfig['/nino/html/navs'] ?? null ) === [ 'main', 'footer' ] );
check( 'the two menu pages carry their membership on their own route', array_column( $realPages, 'navs' ) === [ [ 'main' ], [ 'main' ], [], [] ] );
check( '...at their own position in that list', [ $realPageRoutes['GET://']['navs'], $realPageRoutes['GET://contact']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );

// The generated site itself is deliberately not tracked - it is the
// wizard's output, not repository content
foreach( [ 'content/templates', 'content/text', 'content/elements', 'assets' ] as $dir )
	check( "does not ship a generated /$dir - the wizard writes it", is_dir( $realRoot. '/'. $dir ) === false );

// ...but everything needed to generate it has to be in the library, or a
// fresh checkout could never produce the pages config.php already describes
foreach( [ 'html-header.tpl', 'html-footer.tpl' ] as $file )
	check( "the base unit ships $file", is_file( $realRoot. '/_install/library/base/templates/'. $file ) === true );

foreach( [ 'home/templates/page-home.tpl', '404/templates/page-404.tpl',
           'legal/templates/page-legal.de_DE.tpl', 'contact/templates/page-contact.tpl',
           'blank/templates/page-blank.tpl' ] as $file )
	check( "the page library ships $file", is_file( $realRoot. '/_install/library/pages/'. $file ) === true );

// Every theme unit has to be self-contained: its own manifest, the
// stylesheet that manifest names, and every webfont that stylesheet
// @font-faces - nothing else in the library ships fonts anymore, so a
// missing one is a font that silently never loads
foreach( scandir( $realRoot. '/_install/library/themes' ) ?: [] as $themeEntry ) {

	if( $themeEntry === '.' || $themeEntry === '..' )
		continue;

	$themeDir 			= $realRoot. '/_install/library/themes/'. $themeEntry;
	$themeManifest 	= include $themeDir. '/manifest.php';
	$themeCss 			= (string) ( $themeManifest['stylesheet'] ?? '' );

	check( "the \"$themeEntry\" theme ships the stylesheet its manifest names", $themeCss !== '' && is_file( $themeDir. '/'. $themeCss ) === true );
	check( "the \"$themeEntry\" theme ships a preview image and a description for the picker", is_file( $themeDir. '/'. ( $themeManifest['preview'] ?? '' ) ) === true && ( $themeManifest['description'] ?? '' ) !== '' );

	preg_match_all( '#url\(["\']?(/fonts/[^)"\']+)#', (string) file_get_contents( $themeDir. '/'. $themeCss ), $themeFonts );

	$missingFonts = array_values( array_filter( array_unique( $themeFonts[1] ), fn( string $font ): bool => is_file( $themeDir. $font ) === false ) );
	check( "the \"$themeEntry\" theme ships every webfont its stylesheet references", $missingFonts === [] );
}

$themeKey = (string) ( $realConfig['/nino/install/theme'] ?? '' );
check( 'the theme the config names exists in the library', is_file( $realRoot. '/_install/library/themes/'. $themeKey. '/manifest.php' ) === true );
check( '...and the stylesheet it declares is the one the config bundles', ( include $realRoot. '/_install/library/themes/'. $themeKey. '/manifest.php' )['stylesheet'] === ( $realConfig['/nino/html/assets']['/.cache/style.css'][1] ?? '' ) );

echo "\n";


// --- Cleanup ---------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
