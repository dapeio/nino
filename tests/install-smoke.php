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
require __DIR__. '/../_admin/install/Install.php';

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
$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
$appData['./nino/filesystem/privatepath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] 	= $sandbox. '/public';
$appData['/nino/dir']										= '';
$appData['/nino/locales/native']					= 'de_DE';
// Matches the real shipped config.php default - only the native locale,
// see Setup::apiApply()'s docblock for why that matters (picking just the
// native locale must not silently keep some other locale "available" too)
$appData['/nino/locales/available']			= [ 'de_DE' ];
$appData['/nino/modules']								= [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf', '\\Nino\\Modules\\Images' ];

mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/public/images', 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );
mkdir( $sandbox. '/private/assets', 0777, true );

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
check( '...because there is no stored hash at all', \Nino\Admin\Recovery::hash( $appData ) === null );

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

$appData['/nino/http/routes']['GET://_admin'] = [ 'uri' => '/_admin', 'body' => 'stale persisted page' ];
\Nino\Install\Install::init( $appData );
check( 'init always restores the installer-owned GET route over a stale collision', $appData['/nino/http/routes']['GET://_admin']['body'] === '[template /_admin/install/templates/page-wizard]' );
check( 'init always restores the installer-owned POST route too', $appData['/nino/http/routes']['POST://_admin']['uri'] === '/_admin' );

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
check( 'the canonical private directory is reported as private', isset( $checksBody['directories']['private'] ) === true && isset( $checksBody['directories']['content'] ) === false );
// Only the three roots are probed, not their children: whatever can write
// into private/ can create text/, elements/ and data/ inside it, and the
// same holds for public/. A child listed here reported "not yet created"
// on a perfectly healthy project and told the installer nothing the parent
// had not already answered
check( 'the public root is reported too', ( $checksBody['directories']['public']['ok'] ?? null ) === true );
check( 'no child directory is probed separately any more', isset( $checksBody['directories']['data'], $checksBody['directories']['text'], $checksBody['directories']['images'] ) === false );

echo "\n";


// --- Setup::apiLibrary / apiApply -----------------------------------------

echo "Setup::apiLibrary / apiApply\n";

$libraryRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiLibrary( $appData, $libraryRequest );
$libraryBody = $libraryRequest['/nino/http/response']['body'];

check( 'lists the two locales the library ships translations for', $libraryBody['locales'] === [ 'de_DE', 'en_US' ] );
check( 'reports the config\'s current native locale as already active', $libraryBody['activeLocales'] === [ 'de_DE' ] );
check( 'reports the config\'s current native locale itself, for the Native Locale dropdown to pre-select', $libraryBody['nativeLocale'] === 'de_DE' );
check( 'lists no module unit at all: forms/navigation/localepicker are no longer a choice, and a fresh checkout ships no other unit - pages have their own step now (Webpages), not listed here', $libraryBody['modules'] === [] );

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
$appData['/nino/http/routes']['GET://_admin'] 	= [ 'uri' => '/_admin', 'body' => '[template /_admin/install/templates/page-wizard]', 'statusCode' => 200 ];
$appData['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin' ];
$appData['/nino/http/routes']['POST://.form'] 		= [ 'uri' => '/.form' ];

// de_DE only, nothing left to pick - forms/navigation/localepicker are
// applied regardless
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [] ] );
$applyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $applyRequest );
$applyBody = $applyRequest['/nino/http/response']['body'];

check( 'apply succeeds', $applyRequest['/nino/http/response']['statusCode'] === 200 );
check( 'reports back the three always-on units, nothing was picked to add to them', $applyBody['modules'] === [ 'forms', 'navigation', 'localepicker' ] );

$configAfterApply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'picking only the already-native de_DE does not pull in en_US too', $configAfterApply['/nino/locales/available'] === [ 'de_DE' ] );
// The look is not a choice any more: the base unit delivers one theme.css and
// this step puts it in the bundle, with the project's own stylesheet last
var_export( $configAfterApply['/nino/html/assets'] ); echo "
";
check( 'the Setup step seeds the css bundle with the delivered look and the project\'s own', $configAfterApply['/nino/html/assets']['/.cache/style.css'] === [
	'/_nino/Nino.css', '/assets/theme.css', '/assets/style.css',
] );
check( '...and the two files it names are really in the project', is_file( $sandbox. '/private/assets/theme.css' ) === true && is_file( $sandbox. '/private/assets/style.css' ) === true
	&& is_file( $sandbox. '/private/templates/theme.header.tpl' ) === true && is_file( $sandbox. '/private/templates/theme.footer.tpl' ) === true
	&& is_file( $sandbox. '/public/fonts/league-spartan.woff2' ) === true );
check( 'core structural modules are always present', in_array( '\\Nino\\Modules\\Template', $configAfterApply['/nino/modules'], true ) === true );
check( 'the always-on Form module is present, with nothing picked', in_array( '\\Nino\\Modules\\Form', $configAfterApply['/nino/modules'], true ) === true );
check( 'the always-on Navigation module is present too', in_array( '\\Nino\\Modules\\Navigation', $configAfterApply['/nino/modules'], true ) === true );
check( 'and so is the always-on Localepicker module', in_array( '\\Nino\\Modules\\Localepicker', $configAfterApply['/nino/modules'], true ) === true );
check( 'the one developer tool that still ships as a module is active from the first config on', in_array( '\\Nino\\Modules\\Maintenance', $configAfterApply['/nino/modules'], true ) === true
	// Neither of the other two is among them any more: the Template Builder is
	// a feature installed from the catalogue, and the look is no longer a
	// choice at all - the base unit delivers one theme.css and nothing reads a
	// Design module, because there is none
	&& in_array( '\\Nino\\Modules\\Templates', $configAfterApply['/nino/modules'], true ) === false
	&& in_array( '\\Nino\\Modules\\Design', $configAfterApply['/nino/modules'], true ) === false );
check( 'apply writes the two roles a project starts with', array_keys( $configAfterApply['/nino/auth/roles'] ) === [ 'editor', 'developer' ] && $configAfterApply['/nino/auth/roles']['developer'] === [ 'label' => 'Developer', 'perms' => [ '/*' ] ] );
check( 'the Editor role is every content panel\'s permission - the always-on Form module\'s included - and no structure, system or tab permission', in_array( '/_admin/elements/manage', $configAfterApply['/nino/auth/roles']['editor']['perms'], true ) === true
	&& in_array( '/_admin/submissions/view', $configAfterApply['/nino/auth/roles']['editor']['perms'], true ) === true
	&& in_array( '/_admin/types/manage', $configAfterApply['/nino/auth/roles']['editor']['perms'], true ) === false
	&& in_array( '/_admin/users/manage', $configAfterApply['/nino/auth/roles']['editor']['perms'], true ) === false );
check( 'registers base\'s always-on robots.txt route', isset( $configAfterApply['/nino/http/routes']['GET://robots.txt'] ) === true );
check( 'a hand-written route outside the library survives apply untouched', isset( $configAfterApply['/nino/http/routes']['GET://custom'] ) === true );
check( '/_install\'s own runtime-only route never leaks into the persisted routes', isset( $configAfterApply['/nino/http/routes']['GET://_admin'] ) === false && isset( $configAfterApply['/nino/http/routes']['POST://_admin'] ) === false );
check( 'a module\'s self-registered runtime route (POST://.form) never leaks in either', isset( $configAfterApply['/nino/http/routes']['POST://.form'] ) === false );

/*	The deny rule for the private tree, which a checkout no longer ships:
	base brings it, so the directory the wizard creates is protected by the
	same step that creates it.	*/
check( 'copies base\'s deny rule into the private root it just filled', is_file( \Nino\Filesystem::getContentPath( $appData ). '/.htaccess' ) === true
	&& str_contains( (string) file_get_contents( \Nino\Filesystem::getContentPath( $appData ). '/.htaccess' ), 'Require all denied' ) === true );

/*	...and it has to land *first*. A unit's templates are copied through
	forceDir(), which creates private/ on its own - do that before the files
	block and the directory exists unprotected for the rest of the request,
	with a project's templates already in it if the request then fails. The
	order is load-bearing, so it is pinned here rather than left to whoever
	next tidies that method - which is the kernel's \Nino\Features::applyUnit()
	now, the one unit application the wizard and a feature activation share.	*/
$applyUnitSource = (string) file_get_contents( __DIR__. '/../_nino/Nino/Features/Features.php' );
$applyUnitBody 	= substr( $applyUnitSource, strpos( $applyUnitSource, 'public static function applyUnit(' ) );
$applyUnitBody 	= substr( $applyUnitBody, 0, strpos( $applyUnitBody, "\n\t\t}" ) );
check( 'the wizard\'s Setup applies a unit through the kernel, with overwrite on', str_contains( (string) file_get_contents( __DIR__. '/../_admin/install/Install.php' ), '\\Nino\\Features::applyUnit( $appData, $unitDir, $locales, $routes, $blacklist, true )' ) === true );

check( '...before any template can create that directory unprotected', strpos( $applyUnitBody, "\$manifest['files']" ) < strpos( $applyUnitBody, "forceDir( \$appData, '/templates' )" ) );

check( 'copies base\'s html-header.tpl', \Nino\Filesystem::fileExists( $appData, '/templates/html-header.tpl' ) === true );
check( 'copies "forms"\'s own mail-header/footer templates', \Nino\Filesystem::fileExists( $appData, '/templates/mail-header.tpl' ) === true );
check( 'copies "navigation"\'s own templates too - always-on now, nothing had to pick it', \Nino\Filesystem::fileExists( $appData, '/templates/html-header-nav.tpl' ) === true );
check( '...and "localepicker"\'s', \Nino\Filesystem::fileExists( $appData, '/templates/html-footer-localepicker.tpl' ) === true );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'base\'s blacklist entries (design tokens) landed in text/blacklist.php', in_array( '/website/lang', $blacklistAfterApply, true ) === true );
check( '"forms"\'s own blacklist entries (its mail design tokens) landed too', in_array( '/mail/style/color/primary', $blacklistAfterApply, true ) === true );

$deAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'merges the picked locale\'s text fragments (base + forms)', ( $deAfterApply['[[/form/title]]'] ?? null ) !== null );
check( '...and "localepicker"\'s, always-on now too', ( $deAfterApply['[[/nino/locales/title]]'] ?? null ) === 'Wählen Sie Ihre Sprache' );
check( 'the navigation menus config default lands even though nothing picked navigation', $configAfterApply['/nino/html/navs'] === [ 'main', 'footer' ] );
check( 'never writes a fragment for a locale that was not picked', \Nino\Filesystem::fileExists( $appData, '/text/en_US.php' ) === false );

$libraryAfterApply = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiLibrary( $appData, $libraryAfterApply );
$libraryAfterApplyBody = $libraryAfterApply['/nino/http/response']['body'];

check( 'apiLibrary now reports de_DE as the active locale', $libraryAfterApplyBody['activeLocales'] === [ 'de_DE' ] );
check( 'apiLibrary still lists no module choice at all - forms/navigation/localepicker never appear here', $libraryAfterApplyBody['modules'] === [] );

// A second run with a different locale selection replaces the first run's
// locales, exactly as before - but nothing about the always-on three can be
// "unpicked" any more, so this only ever exercises the locale replace now.
// The hand-written and runtime-only routes above still have to survive
$_POST['data'] = json_encode( [ 'locales' => [ 'en_US' ], 'modules' => [] ] );
$secondApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $secondApplyRequest );
check( 'second apply succeeds', $secondApplyRequest['/nino/http/response']['statusCode'] === 200 );

$configAfterSecondApply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'replaces rather than grows: de_DE is gone, only en_US is available now', $configAfterSecondApply['/nino/locales/available'] === [ 'en_US' ] );
check( 'the native locale follows along once it is no longer available', $configAfterSecondApply['/nino/locales/native'] === 'en_US' );
check( 'the always-on Form module survives a reapply that picks nothing at all', in_array( '\\Nino\\Modules\\Form', $configAfterSecondApply['/nino/modules'], true ) === true );
check( 'so does Navigation', in_array( '\\Nino\\Modules\\Navigation', $configAfterSecondApply['/nino/modules'], true ) === true );
check( 'the Editor role keeps the contact form\'s permission - it can no longer be dropped by unpicking a module', in_array( '/_admin/submissions/view', $configAfterSecondApply['/nino/auth/roles']['editor']['perms'], true ) === true );
check( 'a hand-written route outside the library still survives the replace', isset( $configAfterSecondApply['/nino/http/routes']['GET://custom'] ) === true );
check( 'the still-present simulated runtime pollution still never leaks in, on this second apply either', isset( $configAfterSecondApply['/nino/http/routes']['GET://_admin'] ) === false && isset( $configAfterSecondApply['/nino/http/routes']['POST://.form'] ) === false );
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
unset( $appData['/nino/http/routes']['GET://_admin'], $appData['/nino/http/routes']['POST://_admin'], $appData['/nino/http/routes']['POST://.form'] );

echo "\n";


// --- a header that scrolls away really goes ---------------------------------

// max-height is the weakest of the four ways a box keeps its height:
// min-height beats it outright, padding is never squeezed below what it asks
// for, and a border is drawn whatever the box does. Every header preset uses
// at least one of them to give its bar a height, so the collapsed state has
// to take all of them back - the rule that only said max-height: 0 left five
// of the wizard's six header presets sitting on screen while the page
// scrolled under them
$collapsed = '';

if( preg_match( '/body\.nino-scroll-down\s+\.nino-scroll-header\s*\{([^}]*)\}/', (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ), $match ) === 1 )
	$collapsed = $match[1];

check( 'the collapsed header takes back every way a frame can give its bar a height', $collapsed !== ''
	&& preg_match( '/max-height:\s*0/', $collapsed ) === 1 && preg_match( '/min-height:\s*0/', $collapsed ) === 1
	&& preg_match( '/padding-top:\s*0/', $collapsed ) === 1 && preg_match( '/padding-bottom:\s*0/', $collapsed ) === 1
	&& preg_match( '/border-top-width:\s*0/', $collapsed ) === 1 && preg_match( '/border-bottom-width:\s*0/', $collapsed ) === 1 );

// And the delivered header must not reach for the one thing that rule cannot
// take back. A plain height on the bar would survive all of it - so the frame
// the base unit ships does not have one, and this is where a replacement that
// does finds that out
$fixedHeight 	= [];
$frameMarkup 	= (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/theme.header.tpl' );
$frameStyle 	= (string) file_get_contents( __DIR__. '/../_admin/install/library/base/assets/theme.css' );

if( preg_match( '/class="([^"]*nino-scroll-header[^"]*)"/', $frameMarkup, $match ) === 1 )
	foreach( preg_split( '/\s+/', trim( $match[1] ) ) ?: [] as $class ) {

		// A frame that is not a bar says so for itself: a sidebar rail hands
		// max-height back above its own breakpoint, and from there its height is
		// the layout's business rather than this rule's
		if( preg_match( '/body\.nino-scroll-down[^{}]*\.'. preg_quote( $class, '/' ). '\s*\{[^}]*max-height:\s*none/', $frameStyle ) === 1 )
			continue;

		// Every block whose selector ends on one of the bar's own classes
		if( preg_match_all( '/([^{}]*\.'. preg_quote( $class, '/' ). ')\s*\{([^}]*)\}/', $frameStyle, $blocks, PREG_SET_ORDER ) === 0 )
			continue;

		foreach( $blocks as $found )
			if( preg_match( '/(?<![a-z-])height:\s*(?!auto)/', $found[2] ) === 1 )
				$fixedHeight[] = $class;
	}

check( 'the header markup carries the class that rule acts on', preg_match( '/class="[^"]*nino-scroll-header/', $frameMarkup ) === 1 );
check( 'and the delivered frame gives its bar no height the collapsed state cannot take back'. ( $fixedHeight === [] ? '' : ' - '. implode( ', ', array_unique( $fixedHeight ) ) ), $fixedHeight === [] );

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

check( 'lists every page template, one per _admin/install/library/pages/<key>', in_array( 'home', array_keys( $wpLibraryBody['templates'] ), true ) === true && in_array( 'blank', array_keys( $wpLibraryBody['templates'] ), true ) === true && in_array( 'contact', array_keys( $wpLibraryBody['templates'] ), true ) === true );
check( '"contact" declares it requires "forms"', $wpLibraryBody['templates']['contact']['requiresModules'] === [ 'forms' ] );
/*	A project that has written no page yet opens on the starter site the
	library declares - the pages a site is normally built from, already
	filled in. A proposal in a list nothing has been written from yet, not a
	default underneath config.php: see Webpages::_presetPages()	*/
$presetUnits = array_values( array_filter( array_map(
	static fn( string $entry ): array => [ $entry, ( @include __DIR__. '/../_admin/install/library/pages/'. $entry. '/manifest.php' ) ?: [] ],
	array_values( array_diff( scandir( __DIR__. '/../_admin/install/library/pages' ) ?: [], [ '.', '..' ] ) ) ),
	static fn( array $unit ): bool => isset( $unit[1]['preset'] ) ) );
usort( $presetUnits, static fn( array $a, array $b ): int => $a[1]['preset'] <=> $b[1]['preset'] );
$presetKeys = array_map( static fn( array $unit ): string => $unit[0], $presetUnits );

check( 'starts on the starter site the library declares, in the order its units claim',
	array_column( $wpLibraryBody['webpages'], 'libraryKey' ) === $presetKeys );
// Derived from the units rather than restated here: a page added to or
// dropped from the starter set is one manifest key, not an edit in two places
check( '...which is the handful a site is normally built from', count( array_intersect( [ 'home', 'contact', '404', 'legal' ], $presetKeys ) ) === 4 );
check( '...each carrying its own suggested Http-URI, not its folder name', ( $wpLibraryBody['webpages'][0]['httpUri'] ?? null ) === '/'
	&& ( $wpLibraryBody['webpages'][0]['uri'] ?? null ) === '/home' );
check( '...its own per-locale wording rather than the generic fallback', ( $wpLibraryBody['webpages'][0]['text']['de_DE']['name'] ?? null ) === 'Startseite'
	&& ( $wpLibraryBody['webpages'][0]['text']['en_US']['name'] ?? null ) === 'Home' );
// The one field no form offers and apiApply() takes straight off the entry
check( '...and the status code its manifest route declares', ( array_values( array_filter( $wpLibraryBody['webpages'],
	static fn( array $e ): bool => $e['libraryKey'] === '404' ) )[0]['statusCode'] ?? null ) === 404 );
check( 'navigations are offered - Navigation is always active now, not something that had to be picked', $wpLibraryBody['navs'] === [ 'main', 'footer' ] );
// ...so a preset page proposes whatever menus its own manifest route
// suggests, intersected with what the project actually registers - home/
// contact suggest both, legal only footer, 404 none
check( '...and each proposed page carries the menu membership its own unit suggests', array_column( $wpLibraryBody['webpages'], 'navs' ) === [ [ 'main', 'footer' ], [ 'main', 'footer' ], [], [ 'footer' ] ] );

// Each template also reports the starter wording its own text fragments
// ship, so the form can prefill a new entry per locale instead of leaving
// every locale nobody hand-typed on DEFAULT_TEXT (see _suggestions())
check( 'reports "home"\'s own suggested wording for every picked locale, not just one', array_keys( $wpLibraryBody['templates']['home']['text'] ) === [ 'de_DE', 'en_US' ] );
check( '...with de_DE\'s wording read from the unit\'s own de_DE fragment', $wpLibraryBody['templates']['home']['text']['de_DE']['name'] === 'Startseite' );
check( '...and en_US\'s from its own en_US fragment - the locale that used to end up generic', $wpLibraryBody['templates']['home']['text']['en_US']['name'] === 'Home' && $wpLibraryBody['templates']['home']['text']['en_US']['title'] === 'Welcome.' );
check( 'reports the Http-URI "home" suggests for itself, which is not its folder name', $wpLibraryBody['templates']['home']['uri'] === '/' );
check( '...and "contact"\'s, which is', $wpLibraryBody['templates']['contact']['uri'] === '/contact' );
check( 'the legal manifest declares the stable Element-URI its generated route receives', ( array_values( ( include __DIR__. '/../_admin/install/library/pages/legal/manifest.php' )['routes'] )[0]['uri'] ?? null ) === '/legal' );
check( 'the blank template starts every locale with useful page metadata', $wpLibraryBody['templates']['blank']['text']['de_DE']['name'] === 'Neue Webseite' && $wpLibraryBody['templates']['blank']['text']['en_US']['name'] === 'New webpage' );
check( 'the blank template suggests a stable Http-URI and its own page template', $wpLibraryBody['templates']['blank']['uri'] === '/new-webpage' && $wpLibraryBody['templates']['blank']['body'] === '[template /templates/page-blank]' );
check( 'the blank page template deliberately has no section', strpos( (string) file_get_contents( __DIR__. '/../_admin/install/library/pages/blank/templates/page-blank.tpl' ), '<section' ) === false );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '../etc/passwd', 'httpUri' => '/x', 'libraryKey' => 'home', 'text' => [] ] ] ] );
$badUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $badUriRequest );
check( 'rejects an unsafe Element-URI with 400', $badUriRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/x', 'httpUri' => '../etc/passwd', 'libraryKey' => 'home', 'text' => [] ] ] ] );
$badHttpUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $badHttpUriRequest );
check( 'rejects an unsafe Http-URI with 400', $badHttpUriRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/x', 'httpUri' => '/x', 'libraryKey' => 'does-not-exist', 'text' => [] ] ] ] );
$badTemplateRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $badTemplateRequest );
check( 'rejects an unknown library key with 400', $badTemplateRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/installer-shadow', 'httpUri' => '/_admin', 'libraryKey' => 'home', 'text' => [] ] ] ] );
$reservedHttpUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $reservedHttpUriRequest );
check( 'rejects a Webpage mounted on the installer\'s own runtime uri', $reservedHttpUriRequest['/nino/http/response']['statusCode'] === 409 );


$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => '/custom-page', 'httpUri' => '/custom', 'libraryKey' => 'home', 'text' => [] ] ] ] );
$foreignRouteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $foreignRouteRequest );
check( 'rejects an Http-URI already owned by a non-Webpages route', $foreignRouteRequest['/nino/http/response']['statusCode'] === 409 );
check( 'the colliding hand-written route remains untouched', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://custom']['body'] ?? null ) === 'hand-written route' );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/x', 'httpUri' => '/x', 'libraryKey' => 'home', 'text' => [] ],
	[ 'uri' => '/x', 'httpUri' => '/y', 'libraryKey' => 'contact', 'text' => [] ],
] ] );
$dupeUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dupeUriRequest );
check( 'rejects a duplicate Element-URI with 400', $dupeUriRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/x', 'httpUri' => '/x', 'libraryKey' => 'home', 'text' => [] ],
	[ 'uri' => '/y', 'httpUri' => '/x', 'libraryKey' => 'contact', 'text' => [] ],
] ] );
$dupeHttpUriRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dupeHttpUriRequest );
check( 'rejects a duplicate Http-URI with 400, even when the Element-URIs differ', $dupeHttpUriRequest['/nino/http/response']['statusCode'] === 400 );

// The actual, first real apply: home at Element-URI "/site-home" / Http-URI
// "/" + contact at "/site-contact" / "/kontakt" (drags in
// forms via requiresModules) - Element-URI deliberately differs from
// Http-URI throughout, and from the template's own folder name too, to
// prove the class uses the entry's own uri, not the request path or the
// template name, for the meta namespace (see _routeKeys()'s docblock).
// Only de_DE's text is posted for one field, proving the missing en_US/
// other fields fall back to the generic placeholder rather than failing
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [
		'de_DE' => [ 'name' => 'Start', 'title' => 'Willkommen', 'description' => 'Startseite' ],
		'en_US' => [ 'name' => 'Home', 'title' => 'Welcome', 'description' => 'Home page' ],
	] ],
	[ 'uri' => '/site-contact', 'httpUri' => '/kontakt', 'libraryKey' => 'contact', 'navs' => [ 'main' ], 'text' => [
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
check( 'copies a page unit\'s declared files into the project', is_file( $sandbox. '/public/images/demo.jpg' ) === true );

$deAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );

check( 'writes the home entry\'s own de_DE meta, keyed by its Element-URI (not its Http-URI or the "home" template name)', $deAfterWpApply['[[/webpage/site-home/name]]'] === 'Start' && $deAfterWpApply['[[/webpage/site-home/title]]'] === 'Willkommen' );
check( 'writes the home entry\'s own en_US meta too', $enAfterWpApply['[[/webpage/site-home/name]]'] === 'Home' );
check( 'a field left blank in the post (contact\'s en_US) falls back to the generic placeholder, not the "contact" template\'s own wording', $enAfterWpApply['[[/webpage/site-contact/title]]'] === 'Page Title' );
check( 'a field that was posted (contact\'s de_DE name) is used as-is', $deAfterWpApply['[[/webpage/site-contact/name]]'] === 'Kontakt' );
check( 'a template\'s own /webpage/<foldername>/* meta is never merged in - only the Element-URI-keyed one this class writes itself', isset( $deAfterWpApply['[[/webpage/home/name]]'] ) === false );
$globalAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklistAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'writes every page\'s reachable Http-URI as one global fill, so a template can link to it by name', ( $globalAfterWpApply['[[/webpage/site-home/uri]]'] ?? null ) === '/'
	&& ( $globalAfterWpApply['[[/webpage/site-contact/uri]]'] ?? null ) === '/kontakt'
	&& isset( $deAfterWpApply['[[/webpage/site-home/uri]]'], $enAfterWpApply['[[/webpage/site-home/uri]]'] ) === false );
check( 'a page uri is a technical value, blacklisted out of the Text panel like every other route key', in_array( '/webpage/site-home/uri', $blacklistAfterWpApply, true )
	&& in_array( '/webpage/site-contact/uri', $blacklistAfterWpApply, true )
	&& count( array_unique( $blacklistAfterWpApply ) ) === count( $blacklistAfterWpApply ) );
check( 'a template\'s own deeper content (unprefixed, shared across instances) still merges in', isset( $deAfterWpApply['[[/page-home/welcome/title]]'] ) === true );

$libraryAfterWpApply = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiList( $appData, $libraryAfterWpApply );
check( 'apiList now reflects the persisted, current list', array_column( $libraryAfterWpApply['/nino/http/response']['body']['webpages'], 'httpUri' ) === [ '/', '/kontakt' ] );

// --- a 'templatePerRoute' unit gets one template per route ----------------
//
// "blank" is the empty starting point, so every route picking it has to get
// its own file: with one shared page-blank.tpl, building a second blank page
// silently rewrote the first one. A finished unit (home, contact, ...) is a
// one-off and keeps sharing - that is what its template is for
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'text' => [] ],
	[ 'uri' => '/site-contact', 'httpUri' => '/kontakt', 'libraryKey' => 'contact', 'text' => [] ],
	[ 'uri' => '/team', 'httpUri' => '/team', 'libraryKey' => 'blank', 'text' => [] ],
	[ 'uri' => '/jobs/open', 'httpUri' => '/jobs', 'libraryKey' => 'blank', 'text' => [] ],
] ] );
$perRouteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $perRouteRequest );
check( 'applying two blank routes succeeds', $perRouteRequest['/nino/http/response']['statusCode'] === 200 );

$configPerRoute = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'each blank route gets its own template file, named after its Element-URI', \Nino\Filesystem::fileExists( $appData, '/templates/page-team.tpl' ) === true && \Nino\Filesystem::fileExists( $appData, '/templates/page-jobs-open.tpl' ) === true );
check( '...and renders it, rather than the unit\'s shared one', ( $configPerRoute['/nino/http/routes']['GET://team']['body'] ?? null ) === '[template /templates/page-team]'
	&& ( $configPerRoute['/nino/http/routes']['GET://jobs']['body'] ?? null ) === '[template /templates/page-jobs-open]' );
check( 'a nested Element-URI flattens into one page-*.tpl, which is the only shape the template pickers glob for', \Nino\Filesystem::fileExists( $appData, '/templates/page-jobs/open.tpl' ) === false );
check( 'the unit\'s own page-blank.tpl is never copied in as a shared file', \Nino\Filesystem::fileExists( $appData, '/templates/page-blank.tpl' ) === false );
check( 'a unit without the flag still shares one template', \Nino\Filesystem::fileExists( $appData, '/templates/page-contact.tpl' ) === true
	&& ( $configPerRoute['/nino/http/routes']['GET://kontakt']['body'] ?? null ) === '[template /templates/page-contact]' );

// Re-applying must not undo work done in a per-route template since - it is
// that route's page now, not a copy of the library's starting point
\Nino\Filesystem::putFileContent( $appData, '/templates/page-team.tpl', 'edited by hand' );
$perRouteAgain = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $perRouteAgain );
check( 're-applying leaves an edited per-route template alone', trim( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/page-team.tpl', '' ) ) === 'edited by hand' );

// Reading the list back: the route no longer carries the unit's body, so it
// reports as a page of its own - the same thing an /_admin-created page is,
// and exactly what it has become
$perRouteList = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiList( $appData, $perRouteList );
$teamEntry = array_values( array_filter( $perRouteList['/nino/http/response']['body']['webpages'], fn( array $e ): bool => $e['httpUri'] === '/team' ) )[0] ?? [];
check( 'a per-route page reads back as owning its template rather than as the library unit', ( $teamEntry['libraryKey'] ?? null ) === '' && ( $teamEntry['body'] ?? null ) === '[template /templates/page-team]' );

// Navigation: always active now (see ALWAYS_MODULES above). Membership is
// posted explicitly per entry and stored on its route.
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$navSetupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $navSetupRequest );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Start' ], 'en_US' => [ 'name' => 'Home' ] ] ],
	[ 'uri' => '/site-contact', 'httpUri' => '/kontakt', 'libraryKey' => 'contact', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Kontakt' ], 'en_US' => [ 'name' => 'Contact' ] ] ],
	[ 'uri' => '/site-legal', 'httpUri' => '/impressum', 'libraryKey' => 'legal', 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Recht' ], 'en_US' => [ 'name' => 'Legal' ] ] ],
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
check( 'an entry explicitly assigned to main joins that menu at its own position in the list', ( $routesAfterNav['GET://']['navs'] ?? null ) === [ 'main' => 1 ] );
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
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Start' ], 'en_US' => [ 'name' => 'Home' ] ] ],
] ] );
$dropWpApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dropWpApplyRequest );

$configAfterDrop = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'dropping "kontakt"/"impressum" removes their routes (replace, not merge)', isset( $configAfterDrop['/nino/http/routes']['GET://kontakt'] ) === false && isset( $configAfterDrop['/nino/http/routes']['GET://impressum'] ) === false );
check( 'the home route survives, still keyed the same way', isset( $configAfterDrop['/nino/http/routes']['GET://'] ) === true );
check( 'a hand-written route still survives this replace too', isset( $configAfterDrop['/nino/http/routes']['GET://custom'] ) === true );
check( '/website/legal/uri is only ever set, never cleared - known v1 limitation, see docs/setup.md', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/website/legal/uri]]'] ?? null ) === '/impressum' );

/*	...and the starter site stays gone. The one property that separates a
	proposal in the step's list from a default underneath config.php: this
	project has decided which pages it has, so reopening the step shows that
	decision rather than putting the four back. Seeded in AppData::DEFAULTS
	the dropped pages would be merged in again on the very next boot	*/
$reopenWpRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiList( $appData, $reopenWpRequest );
$reopened = $reopenWpRequest['/nino/http/response']['body']['webpages'] ?? [];

check( 'reopening the step shows the pages this project kept, not the starter site again',
	array_column( $reopened, 'httpUri' ) === [ '/' ] );

// One step earlier, re-applying with nothing posted still leaves navigation,
// the locale picker and the contact form active - there is no "opening
// position" to decline any more, nor a choice that could outlive it
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$declineRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $declineRequest );

$reopenSetupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiLibrary( $appData, $reopenSetupRequest );
$reopenedModules = $reopenSetupRequest['/nino/http/response']['body']['modules'] ?? [];
$configAfterDecline = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'reopening the step still offers nothing to choose - nothing else ships in this checkout',
	$reopenedModules === [] );
check( '...and the three always-on modules are still there, unaffected by posting nothing',
	in_array( '\\Nino\\Modules\\Navigation', $configAfterDecline['/nino/modules'], true ) === true
	&& in_array( '\\Nino\\Modules\\Form', $configAfterDecline['/nino/modules'], true ) === true
	&& in_array( '\\Nino\\Modules\\Localepicker', $configAfterDecline['/nino/modules'], true ) === true );

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
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Start' ] ] ],
	[ 'uri' => '/site-404', 'httpUri' => '/404', 'libraryKey' => '404', 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Weg' ] ] ],
	[ 'uri' => '/site-legal', 'httpUri' => '/legal', 'libraryKey' => 'legal', 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Recht' ] ] ],
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
\Nino\Auth::insertUser( $appData, 'dev@example.com', 'correct horse battery staple', [ '/*' ] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$adminListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] 	= json_encode( [] );
\Nino\Modules\Routes\Admin::apiList( $appData, $adminListRequest );
$adminPages = $adminListRequest['/nino/http/response']['body']['pages'];
$adminTemplates = $adminListRequest['/nino/http/response']['body']['templates'];

check( 'every Webpages-made entry names a template /_admin actually offers', count( array_filter(
	$adminPages,
	fn( array $entry ): bool => $entry['template'] !== '' && in_array( $entry['template'], $adminTemplates, true ) === false
) ) === 0 );

$_POST['data'] = json_encode( [
	'originalHttpUri' => '/404', 'uri' => '/site-404', 'httpUri' => '/404',
	'template' => $adminPages[1]['template'], 'navs' => [],
	'statusCode' => $adminPages[1]['statusCode'], 'text' => $adminPages[1]['text'],
] );
$adminSaveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Routes\Admin::apiSave( $appData, $adminSaveRequest );
check( 'saving a Webpages-made entry from /_admin succeeds', $adminSaveRequest['/nino/http/response']['statusCode'] === 200 );

$afterDevSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'saving it there does not quietly reset its 404 to a 200', ( $afterDevSave['/nino/http/routes']['GET://404']['statusCode'] ?? 200 ) === 404 );
check( 'the entry still resolves to /_install\'s own "404" unit after a save made in /_admin', ( \Nino\Install\Webpages::pages( $appData, $afterDevSave['/nino/http/routes'], [ 'de_DE' ], [] )[1]['libraryKey'] ?? null ) === '404' );

// The locale-resolving body the template <select> can't spell: saving that
// entry from /_admin keeps the body it already has rather than flattening it
// into whichever option the disabled select happened to preselect
$_POST['data'] = json_encode( [
	'originalHttpUri' => '/legal', 'uri' => '/site-legal', 'httpUri' => '/legal',
	'template' => '', 'navs' => [], 'statusCode' => 200, 'text' => $adminPages[2]['text'],
] );
$adminLegalRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Routes\Admin::apiSave( $appData, $adminLegalRequest );
check( 'saving the locale-resolving entry from /_admin succeeds', $adminLegalRequest['/nino/http/response']['statusCode'] === 200 );

$afterLegalSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'its runtime-resolved body is kept, not flattened to one locale\'s file', $afterLegalSave['/nino/http/routes']['GET://legal']['body'] === '[template /templates/page-legal.[[/nino/http/response/locale]]]' );
check( '...and the derived list claims no template for it', ( \Nino\Install\Webpages::pages( $appData, $afterLegalSave['/nino/http/routes'], [ 'de_DE' ], [] )[2]['template'] ?? null ) === '' );

// An entry /_admin created has no library unit at all - Webpages has to carry
// it through its own replace rather than reject it as an unknown template
$_POST['data'] = json_encode( [
	'originalHttpUri' => '', 'uri' => '/dev-made', 'httpUri' => '/dev-made',
	'template' => 'page-home', 'navs' => [], 'statusCode' => 201, 'text' => [],
] );
$adminNewRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Routes\Admin::apiSave( $appData, $adminNewRequest );
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
	'template' => 'page-nope', 'navs' => [], 'statusCode' => 404, 'text' => [],
] );
$adminBadRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Routes\Admin::apiSave( $appData, $adminBadRequest );
check( 'a genuinely unknown template is still rejected', $adminBadRequest['/nino/http/response']['statusCode'] === 400 );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, 'dev@example.com' );

echo "\n";


// --- PersonalInfos::apiList / apiSaveBatch ---------------------------------

echo "PersonalInfos::apiList / apiSaveBatch\n";

// Deliberately overwrites whatever the Webpages section left behind above -
// this section is independently scoped, only real
// _admin/install/library/base key *names* matter here (apiList() filters
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


// --- Admin::apiList / apiCreate -------------------------------------------

echo "Admin::apiList / apiCreate\n";

$adminListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiList( $appData, $adminListRequest );
check( 'does not count the shipped, disabled placeholder as a usable admin account', $adminListRequest['/nino/http/response']['body']['users'] === [] );

$_POST['data'] = json_encode( [ 'password' => 'a-long-enough-admin-password' ] );
$finishWithoutAdminRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $finishWithoutAdminRequest );
check( 'refuses to lock the installer before an active _editor account exists', $finishWithoutAdminRequest['/nino/http/response']['statusCode'] === 409 );

$_POST['data'] = json_encode( [ 'mail' => 'not-an-email', 'pw' => 'a-long-enough-password' ] );
$invalidMailRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $invalidMailRequest );
check( 'rejects an invalid email with 400', $invalidMailRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'mail' => 'admin@example.com', 'pw' => 'short' ] );
$shortPwRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $shortPwRequest );
check( 'rejects a too-short password with 400', $shortPwRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'mail' => 'admin@example.com', 'pw' => 'a-long-enough-password' ] );
$createRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $createRequest );
check( 'creates the account', \Nino\Auth::getUser( $appData, 'admin@example.com' ) !== false );
check( 'the root account holds the Developer role the Setup step wrote, and full access through it', \Nino\Auth::getUser( $appData, 'admin@example.com' )['role'] === 'developer' && \Nino\Auth::getUser( $appData, 'admin@example.com' )['perms'] === [] && \Nino\Auth::checkPermission( $appData, '/_admin/config/manage', 'admin@example.com' ) === true );
check( 'drops the shipped placeholder account once a real admin exists', \Nino\Auth::getUser( $appData, 'changeme@domain.com' ) === false );
check( 'returns only the newly usable account to the frontend', $createRequest['/nino/http/response']['body']['users'] === [ 'admin@example.com' ] );
check( 'the new account can actually authenticate', \Nino\Auth::loginUser( $appData, 'admin@example.com', 'a-long-enough-password' ) !== false );
\Nino\Auth::logoutUser( $appData );

$_POST['data'] = json_encode( [ 'mail' => 'admin@example.com', 'pw' => 'a-different-password' ] );
$replaceRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $replaceRequest );
check( 'creating the same address again replaces it rather than failing', $replaceRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the replaced account uses the new password', \Nino\Auth::loginUser( $appData, 'admin@example.com', 'a-different-password' ) !== false );
\Nino\Auth::logoutUser( $appData );

echo "\n";


// --- Finish::apiComplete / Install::setRecoverySecret ------------------------

echo "Finish::apiComplete / Install::setRecoverySecret\n";

$_POST['data'] = json_encode( [ 'password' => 'short' ] );
$shortFinishRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $shortFinishRequest );
check( 'rejects a too-short _admin password with 400', $shortFinishRequest['/nino/http/response']['statusCode'] === 400 );

// setDevPassword() no longer rewrites php source: it stores the hash under
// the private directory, outside every tool folder and outside config.php.
// _admin/Admin.php therefore stays byte-identical, which is what makes it
// replaceable on an update (see Install::setRecoverySecret()'s docblock)
$adminBefore = file_get_contents( __DIR__. '/../_admin/Admin.php' );

$_POST['data'] = json_encode( [ 'password' => 'a brand new dev password' ] );
$finishRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $finishRequest );
check( 'apiComplete succeeds once an editor account exists', $finishRequest['/nino/http/response']['statusCode'] === 200 );

check( 'the real _admin/Admin.php was not touched', file_get_contents( __DIR__. '/../_admin/Admin.php' ) === $adminBefore );

$pwPath = \Nino\Admin\Recovery::path( $appData );
check( 'the hash lands under the private directory', $pwPath === $sandbox. '/private/.auth/pw.php' && is_file( $pwPath ) === true );

$pwRaw = file_get_contents( $pwPath );
check( 'it is wrapped in the self-exiting 403 stub', str_starts_with( $pwRaw, \Nino\Admin\Recovery::STUB_PREFIX ) === true && str_ends_with( $pwRaw, \Nino\Admin\Recovery::STUB_SUFFIX ) === true );
// Run in a subprocess, not inline: the stub's whole job is to exit(), which
// would take this test run with it. What matters is that executing the file -
// which is what a webserver that happily serves it would do - prints nothing
$stubOutput = (string) shell_exec( 'php -r '. escapeshellarg( 'include '. var_export( $pwPath, true ). ';' ). ' 2>&1' );
check( 'executing that file prints nothing - the hash never reaches a response body', trim( $stubOutput ) === '' );

check( 'passwordHash() reads it back', password_verify( 'a brand new dev password', (string) \Nino\Admin\Recovery::hash( $appData ) ) === true );
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
check( 'a missing password file leaves no usable hash', \Nino\Admin\Recovery::hash( $appData ) === null );
check( '...but the project still counts as installed', \Nino\Admin\Admin::isInstalled( $appData ) === true );

$reopenRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'so /_install stays locked', \Nino\Install\Install::guard( $appData, $reopenRequest ) === false );

// A half-written file must read as "no password", never as a hash that just
// happens not to match
file_put_contents( $pwPath, \Nino\Admin\Recovery::STUB_PREFIX );
check( 'a truncated password file reads as no password at all', \Nino\Admin\Recovery::hash( $appData ) === null );
unlink( $pwPath );

check( 'writePasswordHash() refuses an empty hash rather than storing one nothing can match', \Nino\Admin\Recovery::writeHash( $appData, '' ) === false );
check( 'setDevPassword() can write the file again afterwards', \Nino\Install\Install::setRecoverySecret( $appData, 'another dev password' ) === true );
check( '...and the new password is the one that verifies', password_verify( 'another dev password', (string) \Nino\Admin\Recovery::hash( $appData ) ) === true );

check( 'the private directory carries its own deny rule', is_file( $sandbox. '/private/.htaccess' ) === true );

echo "\n";


// --- Shipped defaults (the real, git-tracked config.php + library) ---------

/*	Read-only sanity check on the actual checkout, not the sandbox above.
	A checkout ships no project at all now - no private/, no config.php, no
	templates, no assets - so what has to hold is that _admin/install/library can
	still produce one. Every route a starter site gets, every template it
	renders from and the deny rule that protects the directory it lands in
	come from a unit here, and nowhere else.	*/
echo "Shipped defaults (_admin/install/library, the only source of a starter site)\n";

$realRoot = __DIR__. '/..';

// The whole point of the move: nothing of one installation's own state is
// repository content any more
check( 'a checkout ships no private directory at all - the wizard creates it', is_dir( $realRoot. '/private' ) === false );
check( '...and no public one either', is_dir( $realRoot. '/public' ) === false );
check( 'the deny rule for the private tree travels with the base unit instead', str_contains(
	(string) @file_get_contents( $realRoot. '/_admin/install/library/base/private/.htaccess' ), 'Require all denied'
) === true );
// ...and it is declared, or it ships without ever being copied
check( '...and the base unit actually copies it', in_array( 'private', ( include $realRoot. '/_admin/install/library/base/manifest.php' )['files'] ?? [], true ) === true );

// The shell every page renders inside
foreach( [ 'html-header.tpl', 'html-footer.tpl' ] as $file )
	check( "the base unit ships $file", is_file( $realRoot. '/_admin/install/library/base/templates/'. $file ) === true );

/*	Every page unit stands on its own: the routes it declares, the templates
	those routes render, and the files they reference. A unit whose route
	points at a template it does not ship is a page the wizard can offer and
	then fail to produce.	*/
$pageUnits 		= [];
$pageFailures 	= [];

foreach( scandir( $realRoot. '/_admin/install/library/pages' ) ?: [] as $pageEntry ) {

	if( is_file( $realRoot. '/_admin/install/library/pages/'. $pageEntry. '/manifest.php' ) === false )
		continue;

	$pageDir 			= $realRoot. '/_admin/install/library/pages/'. $pageEntry;
	$pageManifest 	= include $pageDir. '/manifest.php';
	$pageUnits[] 	= $pageEntry;

	if( ( $pageManifest['label'] ?? '' ) === '' )
		$pageFailures[] = $pageEntry. ': no label for the picker';

	foreach( ( $pageManifest['templates'] ?? [] ) as $pageTemplate )
		if( is_file( $pageDir. '/templates/'. $pageTemplate ) === false )
			$pageFailures[] = $pageEntry. ': declares '. $pageTemplate. ' and does not ship it';

	/*	The body is what ties a route to a file on disk. A route naming a
		template no unit ships is the one failure this whole block exists for.
		Every route is checked, not only the page routes: the demo units
		deliberately register something Webpages::isPageRoute() does not
		recognise (so the Routes editor leaves them alone), and their
		templates still have to exist.	*/
	foreach( ( $pageManifest['routes'] ?? [] ) as $routeKey => $route ) {

		if( preg_match( '#\[template /templates/([a-z0-9._-]+)#i', (string) ( $route['body'] ?? '' ), $routeTemplate ) !== 1 )
			continue;

		// A locale-suffixed body ('page-legal.[[/nino/http/response/locale]]')
		// matches whichever locales the unit ships, so the stem is what counts
		$stem 			= preg_replace( '/\.$/', '', $routeTemplate[1] );
		$candidates = glob( $pageDir. '/templates/'. $stem. '*.tpl' ) ?: [];

		if( $candidates === [] )
			$pageFailures[] = $pageEntry. ': '. $routeKey. ' renders '. $stem. ', which no template file matches';
	}
}

check( 'every page unit ships the templates its own routes render'. ( $pageFailures === [] ? '' : ' - '. implode( ' | ', $pageFailures ) ), $pageFailures === [] );
// The four a starter site is normally built from have to be among them, or
// the Routes step has nothing to offer on a fresh install
check( 'the page library offers the four a starter site is built from', count( array_intersect( [ 'home', 'contact', '404', 'legal' ], $pageUnits ) ) === 4 );
check( '...and a blank one to start a page of your own from', in_array( 'blank', $pageUnits, true ) === true );

// The 404 unit is the one whose route Http::response() looks up by an exact
// key of its own when nothing else matches
$notFound = ( include $realRoot. '/_admin/install/library/pages/404/manifest.php' )['routes'] ?? [];
check( 'the 404 unit registers the exact key the fallback lookup needs', isset( $notFound['GET://404'] ) === true
	&& ( $notFound['GET://404']['statusCode'] ?? null ) === 404 );
// ...and home is the one that has to register at "/" while carrying its own
// Element-URI in the data field
$home = ( include $realRoot. '/_admin/install/library/pages/home/manifest.php' )['routes'] ?? [];
check( 'the home unit registers at "/" and keeps "/home" as its Element-URI', isset( $home['GET://'] ) === true
	&& ( $home['GET://']['uri'] ?? null ) === '/home' );

// The optional modules a page can pull in with it are units too, so a page
// declaring one nobody ships would be a dead requirement. A unit travels
// with its module - install/ beside the class file - and Setup::units()
// finds it there without /_install listing it anywhere
$moduleUnits = \Nino\Install\Setup::units();
$modulesDirs 	= [ realpath( $realRoot. '/_nino/Nino/Modules' ) ];

check( 'the contact page can pull the forms module in with it', isset( $moduleUnits['forms'] ) === true );
check( 'a checkout keeps no module unit in _admin/install/library any more - every one sits in its module as install/', $moduleUnits !== [] && array_filter( $moduleUnits,
	static fn( string $unitDir ): bool => basename( $unitDir ) !== 'install' || in_array( dirname( realpath( $unitDir ) ?: '', 2 ), $modulesDirs, true ) === false ) === [] );
check( 'the optional modules ship below _nino/Nino/Modules, switched on or off in /nino/modules', str_ends_with( $moduleUnits['forms'], '/_nino/Nino/Modules/Form/install' ) === true );
// A feature is not a wizard unit: it is activated in the Features panel after
// setup (see \Nino\Features), so its install/ is never offered here
check( 'nothing below features/ is offered by the wizard', array_filter( $moduleUnits, static fn( string $unitDir ): bool => str_contains( $unitDir, '/features/' ) === true ) === [] );
check( 'the forms unit takes its key from its manifest - its directory is "Form"', basename( dirname( $moduleUnits['forms'] ) ) === 'Form' && isset( $moduleUnits['form'] ) === false );
check( '...the others from their directory\'s lowercased name', basename( dirname( $moduleUnits['navigation'] ) ) === 'Navigation' && basename( dirname( $moduleUnits['localepicker'] ) ) === 'Localepicker' );
// ...and each unit activates the module it sits in, or the wizard would
// switch one module on and copy another's templates
check( 'each unit activates the module it ships with', array_filter( $moduleUnits, static fn( string $unitDir ): bool =>
	( ( include $unitDir. '/manifest.php' )['moduleClass'] ?? '' ) !== '\\Nino\\Modules\\'. basename( dirname( $unitDir ) ) ) === [] );
// Everything the always-on half does not cover is a unit somebody can decline
check( 'the always-on module list carries no unit the wizard offers', array_intersect(
	array_map( static fn( string $unitDir ): string => (string) ( ( include $unitDir. '/manifest.php' )['moduleClass'] ?? '' ), $moduleUnits ),
	\Nino\AppData::DEFAULTS['/nino/modules']
) === [] );

/*	The look is one file the base unit delivers, so that file has to be
	self-contained: the fonts it @font-faces are copied by the same unit, and
	the two frame templates it styles are copied by it too. Nothing looks for
	a theme unit any more - a missing font here is a font that silently never
	loads, and a missing template a header or footer that silently is not
	there (\Nino\Template resolves an absent include to '').	*/
$baseUnit 	= $realRoot. '/_admin/install/library/base';
$baseFiles 	= (array) ( ( include $baseUnit. '/manifest.php' )['files'] ?? [] );
$themeCss 	= (string) file_get_contents( $baseUnit. '/assets/theme.css' );

check( 'the base unit ships the one stylesheet the css bundle names, and copies the directory it is in', is_file( $baseUnit. '/assets/theme.css' ) === true
	&& in_array( 'assets', $baseFiles, true ) === true );

preg_match_all( '#url\(["\']?\[\[/nino/public\]\](/fonts/[^)"\']+)#', $themeCss, $themeFonts );
check( 'and every webfont it @font-faces', count( $themeFonts[1] ) > 0
	&& array_values( array_filter( array_unique( $themeFonts[1] ), fn( string $font ): bool => is_file( $baseUnit. $font ) === false ) ) === [] );
check( '...and names fonts among the files it copies, so they reach the project at all', in_array( 'fonts', $baseFiles, true ) === true );

/*	The root size is a percentage of the visitor's browser default, in both
	files that set it - a px length here silently overrules someone who raised
	their default to 20px, and that is the one setting a visitor changes
	because they need it rather than because they prefer it. theme.css matters
	as much as Nino.css: it assigns --base-size from its own token, so a px
	there wins over a correct kernel.	*/
$rootSizes = [];
foreach( [ '/../_nino/Nino.css' => '--base-size', '/../_admin/install/library/base/assets/theme.css' => '--nino-base-size' ] as $file => $token )
	if( preg_match_all( '/'. preg_quote( $token, '/' ). ':\s*([^;]+);/', (string) file_get_contents( __DIR__. $file ), $found ) > 0 )
		foreach( $found[1] as $value )
			$rootSizes[] = $file. ': '. trim( $value );

check( 'both files set the root size, and neither as a length'. ( $rootSizes === [] ? ' - none found' : '' ), count( $rootSizes ) === 4
	&& array_filter( $rootSizes, static fn( string $entry ): bool => str_ends_with( $entry, '%' ) === false ) === [] );

// The stylesheet and the markup it styles are one delivery: theme.css names
// .nino-frame-header and .nino-footer-nav, and nothing else writes either
// template into a project
foreach( [ 'theme.header.tpl', 'theme.footer.tpl' ] as $frame )
	check( "the base unit ships $frame and lists it among its templates", is_file( $baseUnit. '/templates/'. $frame ) === true
		&& in_array( $frame, (array) ( ( include $baseUnit. '/manifest.php' )['templates'] ?? [] ), true ) === true );

echo "\n";


// --- Install units travel with their modules ---------------------------------

/*	A module is added to a project by adding its directory: its install unit
	sits beside its class as install/, and Setup::units() finds it there
	without the wizard listing it anywhere. A project's own modules are found
	the same way below the app dir - the directory the autoloader resolves
	project classes against, so NINO_APP_DIR moves them along. Nino's own
	optional modules stay where the kernel is, below _nino/Nino/Modules, and
	keep their keys against a project unit claiming the same one.	*/
echo "Install units travel with their modules\n";

if( defined( 'NINO_APP_DIR' ) === true ) {

	echo "  (NINO_APP_DIR is already defined - the app dir checks are skipped)\n";

} else {

	$appDir = $sandbox. '/app';

	// Two levels down (Vendor/Module) and three (Vendor/Modules/Module) -
	// the autoloader allows either shape, so both have to be found
	foreach( [
		'/Acme/Widget/install' 				=> [ 'label' => 'Widget', 'moduleClass' => '\\Acme\\Widget', 'requiresModules' => [ 'forms' ] ],
		'/Acme/Modules/Deep/install' 	=> [ 'label' => 'Deep', 'moduleClass' => '\\Acme\\Modules\\Deep', 'key' => 'deep-unit' ],
		// A second unit claiming "forms" - Nino's own Form module below
		// _nino/Nino/Modules keeps the key
		'/Acme/Forms/install' 				=> [ 'label' => 'Shadow', 'moduleClass' => '\\Acme\\Forms' ],
		// Not a slug: dropped
		'/Acme/Bad/install' 					=> [ 'label' => 'Bad', 'moduleClass' => '\\Acme\\Bad', 'key' => 'Not A Slug' ],
	] as $unitPath => $unitManifest ) {
		$unitTemplate = 'page-'. strtolower( basename( dirname( $unitPath ) ) ). '.tpl';
		@mkdir( $appDir. $unitPath. '/templates', 0755, true );
		file_put_contents( $appDir. $unitPath. '/manifest.php', '<?php return '. var_export( $unitManifest + [ 'templates' => [ $unitTemplate ] ], true ). ';' );
		file_put_contents( $appDir. $unitPath. '/templates/'. $unitTemplate, '<p>'. $unitManifest['label']. '</p>' );
	}

	define( 'NINO_APP_DIR', $appDir );

	$foundUnits = \Nino\Install\Setup::units();

	check( 'a project module\'s install/ is found below the app dir', ( $foundUnits['widget'] ?? '' ) === $appDir. '/Acme/Widget/install' );
	check( '...three levels down too', ( $foundUnits['deep-unit'] ?? '' ) === $appDir. '/Acme/Modules/Deep/install' );
	check( 'the key comes from the manifest when it names one, else from the directory', isset( $foundUnits['deep'] ) === false && isset( $foundUnits['widget'] ) === true );
	check( 'Nino\'s own unit keeps a key a project module also claims', str_ends_with( $foundUnits['forms'], '/_nino/Nino/Modules/Form/install' ) === true );
	check( 'a key that is not a slug is dropped', array_filter( $foundUnits, static fn( string $dir ): bool => str_ends_with( $dir, '/Acme/Bad/install' ) === true ) === [] );
	$sortedKeys = array_keys( $foundUnits );
	sort( $sortedKeys );
	check( 'the map is sorted by key', array_keys( $foundUnits ) === $sortedKeys );

	// The picker offers it like any other unit, and applying it activates
	// the class and copies the template - with its requirement pulled in
	$appLibraryRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Setup::apiLibrary( $appData, $appLibraryRequest );
	$appLibraryBody = $appLibraryRequest['/nino/http/response']['body'];
	check( 'the Setup step offers the project module', ( $appLibraryBody['modules']['widget']['label'] ?? null ) === 'Widget'
		&& ( $appLibraryBody['modules']['widget']['requiresModules'] ?? null ) === [ 'forms' ] );
	check( '...and forms/navigation/localepicker stay excluded even once other project modules exist', array_intersect( [ 'forms', 'navigation', 'localepicker' ], array_keys( $appLibraryBody['modules'] ) ) === [] );

	$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [ 'widget' ] ] );
	$appApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Setup::apiApply( $appData, $appApplyRequest );
	$appApplyBody = $appApplyRequest['/nino/http/response']['body'] ?? [];

	check( 'applying it activates the class and its requirement, alongside the three always-on units', ( $appApplyBody['modules'] ?? null ) === [ 'forms', 'navigation', 'localepicker', 'widget' ]
		&& in_array( '\\Acme\\Widget', $appData['/nino/modules'], true ) === true
		&& in_array( '\\Nino\\Modules\\Form', $appData['/nino/modules'], true ) === true );
	check( '...and copies its template out of the module directory', \Nino\Filesystem::fileExists( $appData, '/templates/page-widget.tpl' ) === true );

	// A requirement nobody answers to is skipped, not applied
	file_put_contents( $appDir. '/Acme/Widget/install/manifest.php', '<?php return '. var_export( [ 'label' => 'Widget', 'moduleClass' => '\\Acme\\Widget', 'requiresModules' => [ 'nonexistent' ] ], true ). ';' );
	$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [ 'widget' ] ] );
	$unknownRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Setup::apiApply( $appData, $unknownRequest );
	check( 'a requirement no unit answers to is left out of the applied set - only the always-on three plus what was actually picked remain', ( $unknownRequest['/nino/http/response']['body']['modules'] ?? null ) === [ 'forms', 'navigation', 'localepicker', 'widget' ] );
}

echo "\n";


// --- Cleanup ---------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
