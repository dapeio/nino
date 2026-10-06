<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	install-smoke.php		Dependency-free smoke test for the setup wizard
 *												(_install/Install.php). Runs against an isolated sandbox
 *												directory, never touches the real project data - in
 *												particular, it never rewrites the real _admin/Admin.php (see the
 *												Finish section below): setRecoverySecret() only takes a
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
// 'data[]=x' posts an array, and json_decode() takes a string: unguarded that
// is a TypeError, a 500 the wizard answered before any authentication.
// Admin::postData() has carried the guard since 92bc3fb; this read is that one
$_POST['data'] = [ 'x' ];
try { $arrayPayload = \Nino\Install\Install::postData(); } catch( \TypeError $e ) { $arrayPayload = 'TypeError'; }
check( 'postData() answers an array payload with an empty one, not a TypeError', $arrayPayload === [] );

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

// 'action[]=x' is an "Illegal offset type in isset" in the dispatcher, ie. a
// 500 on the wizard's address where the answer is the 404 above
$_POST['action'] = [ 'checks/run' ];
$arrayActionRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Install::handlePost( $appData, $arrayActionRequest );
check( 'an array-shaped action is rejected the same way, not thrown on', $arrayActionRequest['/nino/http/response']['statusCode'] === 404 );
$_POST['action'] = 'checks/run';

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

/*	The locales the library lists are its base unit's text files: the wizard
	derives them as this test does. What this pins is that global.php is not
	among them and that nothing else is	*/
$shippedLocales = array_values( array_filter(
	array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( __DIR__. '/../_admin/install/library/base/text/*.php' ) ?: [] ),
	static fn( string $locale ): bool => preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $locale ) === 1
) );
$listedLocales = $libraryBody['locales'];
sort( $shippedLocales ); sort( $listedLocales );
check( 'lists exactly the locales the library ships translations for', $listedLocales === $shippedLocales && count( $shippedLocales ) > 0 );
/*	The step keeps no list of its own: the always-on modules are
	AppData::DEFAULTS', the languages the base unit's text files. A class
	name or a locale written into Install.php is a second copy that the next
	addition forgets	*/
$setupSource = (string) file_get_contents( __DIR__. '/../_admin/install/Install.php' );
$restated = array_filter( \Nino\AppData::DEFAULTS['/nino/modules'], static fn( string $class ): bool => str_contains( $setupSource, "'". str_replace( '\\', '\\\\', $class ). "'" ) );
check( 'the wizard restates neither the always-on modules nor the shipped locales', $restated === []
	&& preg_match( "/'[a-z]{2}_[A-Z]{2}(\\.php)?'/", $setupSource ) !== 1 );
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
// Every always-on unit, and nothing else since nothing was picked - and
// every key the constant names has a unit to apply, or apply() would drop
// it on the floor without a word
check( 'reports back every always-on unit and nothing else, and each of them has a unit', $applyBody['modules'] === \Nino\Install\Setup::ALWAYS_MODULES
	&& array_diff( \Nino\Install\Setup::ALWAYS_MODULES, array_keys( \Nino\Install\Setup::units() ) ) === [] );

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
	&& is_file( $sandbox. '/private/templates/frame-header.tpl' ) === true && is_file( $sandbox. '/private/templates/frame-footer.tpl' ) === true
	&& is_file( $sandbox. '/public/fonts/league-spartan.woff2' ) === true );
// The logo is a slot with nothing in it: the frames, the navigation and the
// mails show it with [image /logo], and ship no picture of their own
$logoSlot = $configAfterApply['/nino/html/images']['/logo'] ?? [];
check( 'the base unit seeds the empty logo slot into config.php', ( $logoSlot['label'] ?? null ) === 'Logo' && ( $logoSlot['width'] ?? null ) === 500 && ( $logoSlot['height'] ?? null ) === 100
	&& array_key_exists( 'filename', $logoSlot ) === true && $logoSlot['filename'] === null && array_keys( $configAfterApply['/nino/html/images'] ) === [ '/logo' ] );
check( '...and delivers no logo picture, the unit has no images directory', glob( $sandbox. '/public/images/*' ) === [] && is_dir( __DIR__. '/../_admin/install/library/base/images' ) === false );

// Applying the step again only adds: the label, the size and the picture of a
// slot the project has stay as they are
$appData['/nino/html/images']['/logo'] = [ 'label' => 'Firmenlogo', 'width' => 300, 'height' => 100, 'filename' => 'logo.webp' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
$reapplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $reapplyRequest );
$logoAfterReapply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/logo'] ?? [];
check( 'applying the Setup step again keeps a logo slot the project changed', $reapplyRequest['/nino/http/response']['statusCode'] === 200 && ( $logoAfterReapply['label'] ?? null ) === 'Firmenlogo'
	&& ( $logoAfterReapply['width'] ?? null ) === 300 && ( $logoAfterReapply['filename'] ?? null ) === 'logo.webp' );
$appData['/nino/html/images']['/logo'] = $logoSlot;
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

check( 'core structural modules are always present', in_array( '\\Nino\\Modules\\Template', $configAfterApply['/nino/modules'], true ) === true );
check( 'the always-on Form module is present, with nothing picked', in_array( '\\Nino\\Modules\\Form', $configAfterApply['/nino/modules'], true ) === true );
check( 'the always-on Navigation module is present too', in_array( '\\Nino\\Modules\\Navigation', $configAfterApply['/nino/modules'], true ) === true );
check( 'and so is the always-on Localepicker module', in_array( '\\Nino\\Modules\\Localepicker', $configAfterApply['/nino/modules'], true ) === true );
check( 'the one developer tool that still ships as a module is active from the first config on', in_array( '\\Nino\\Modules\\Maintenance', $configAfterApply['/nino/modules'], true ) === true
	// Neither of the other two is among them any more: the Template Builder is
	// a feature installed from the catalogue, and the look is no longer a
	// choice at all - the base unit delivers one theme.css, and the Design
	// feature that composes a look is a catalogue feature the Features panel
	// installs, not a module this config could list
	&& in_array( '\\Nino\\Modules\\Templates', $configAfterApply['/nino/modules'], true ) === false
	&& in_array( '\\Nino\\Modules\\Design', $configAfterApply['/nino/modules'], true ) === false );
// The roles a project starts with are the Users module's defaults, whatever
// they are called - what this pins is that apply writes all of them and that
// the developer role is the one that may do everything
check( 'apply writes every role the Users module starts a project with, the developer one with everything', array_keys( $configAfterApply['/nino/auth/roles'] ) === array_keys( \Nino\Modules\Users\Roles::defaults( $appData ) )
	&& in_array( '/*', $configAfterApply['/nino/auth/roles']['developer']['perms'] ?? [], true ) === true );
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
preg_match_all( '/\\\\Nino\\\\Features::applyUnit\( [^;]*?\)/', (string) file_get_contents( __DIR__. '/../_admin/install/Install.php' ), $applyUnitCalls );
check( 'the wizard\'s Setup applies its units through the kernel, every call with overwrite on', count( $applyUnitCalls[0] ) >= 2 && array_filter( $applyUnitCalls[0], fn( string $call ): bool => str_ends_with( $call, ', true )' ) === false ) === [] );

check( '...before any template can create that directory unprotected', strpos( $applyUnitBody, "\$manifest['files']" ) < strpos( $applyUnitBody, "forceDir( \$appData, '/templates' )" ) );

check( 'copies base\'s html-header.tpl', \Nino\Filesystem::fileExists( $appData, '/templates/html-header.tpl' ) === true );
check( 'copies "forms"\'s own mail-header/footer templates', \Nino\Filesystem::fileExists( $appData, '/templates/mail-header.tpl' ) === true );
check( 'copies "navigation"\'s own templates too - always-on now, nothing had to pick it', \Nino\Filesystem::fileExists( $appData, '/templates/html-header-nav.tpl' ) === true );
check( '...and "localepicker"\'s', \Nino\Filesystem::fileExists( $appData, '/templates/html-footer-localepicker.tpl' ) === true );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'base\'s blacklist entries (design tokens) landed in text/blacklist.php', in_array( '/project/website/html/lang', $blacklistAfterApply, true ) === true );
check( 'the mail design tokens landed on the blacklist too - they are the base unit\'s now, not the contact form\'s', in_array( '/project/mail/color/primary', $blacklistAfterApply, true ) === true
	&& in_array( '/project/mail/spacing/large', $blacklistAfterApply, true ) === true );
check( '"forms" ships no blacklist and no global text of its own any more', isset( ( include __DIR__. '/../_nino/Nino/Modules/Form/install/manifest.php' )['blacklist'] ) === false
	&& is_file( __DIR__. '/../_nino/Nino/Modules/Form/install/text/global.php' ) === false );

$deAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'merges the picked locale\'s text fragments (base + forms)', ( $deAfterApply['[[/module/form/info/required]]'] ?? null ) !== null );
check( '...and "localepicker"\'s, always-on now too', ( $deAfterApply['[[/module/localepicker/menu/title]]'] ?? null ) === 'Wähle Deine Sprache' );
// The base site ships no cookie banner: consent is the Consent feature's job.
// The footer a project is set up with carries no banner block, and the
// wizard writes none of the four fills that block read
check( 'the installed footer carries no cookie banner', str_contains( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/html-footer.tpl', '' ), 'nino-cookie-banner' ) === false );
check( '...and the picked locale\'s text has no /cookiebanner/ key', array_filter( array_keys( $deAfterApply ), static fn( string $key ): bool => str_starts_with( $key, '[[/cookiebanner/' ) ) === [] );
// The menus are whatever the Navigation unit's manifest declares as the
// config default - read from there, so the unit can change its menus without
// a second edit here; what this pins is that the default lands at all
$navigationDefaults = (array) ( ( include __DIR__. '/../_nino/Nino/Modules/Navigation/install/manifest.php' )['config'] ?? [] );
// The Legal unit asks for a menu of its own on top (its 'navs' key, see
// Setup::_applyNavs()), so the registry is the Navigation unit's menus and 'legal'
$expectedNavs = array_merge( (array) ( $navigationDefaults['/nino/html/navs'] ?? [] ), [ 'legal' ] );
check( 'the navigation unit\'s config default lands even though nothing picked navigation - and the menu the Legal unit asks for stands behind it', isset( $navigationDefaults['/nino/html/navs'] ) === true
	&& $configAfterApply['/nino/html/navs'] === $expectedNavs );
/*	The Legal unit is one of ALWAYS_MODULES: nothing picked it and it is applied
	- its two element types seeded in the picked language, its two templates and
	its path default copied, its menu created. What the unit's own words and
	texts say is tests/legal-smoke.php's business.	*/
$legalManifest = include __DIR__. '/../_nino/Nino/Modules/Legal/install/manifest.php';
check( 'the Legal unit is always applied - its class is active though nothing picked it', in_array( 'legal', \Nino\Install\Setup::ALWAYS_MODULES, true ) === true
	&& in_array( '\\Nino\\Modules\\Legal', $configAfterApply['/nino/modules'], true ) === true );
check( '...its two templates are copied', \Nino\Filesystem::fileExists( $appData, '/templates/page-legal-imprint.tpl' ) === true && \Nino\Filesystem::fileExists( $appData, '/templates/page-legal-privacy.tpl' ) === true );
check( '...and its path default, which only lands where the project has none', $configAfterApply['/nino/legal/paths'] === $legalManifest['config']['/nino/legal/paths'] );
$legalAfterApply = \Nino\Filesystem::getFileContent( $appData, '/elements/legal.php', [] );
$privacyAfterApply = \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] );
check( '...the types legal and privacy exist, with the sections in the picked language and none in the other', count( $legalAfterApply['de_DE'] ?? [] ) > 0 && count( $privacyAfterApply['de_DE'] ?? [] ) > 5
	&& isset( $legalAfterApply['en_US'] ) === false && isset( $privacyAfterApply['en_US'] ) === false );
check( '...each section with a title and a text, and the global bucket with its position', array_filter( $privacyAfterApply['de_DE'], static fn( array $section ): bool => ( $section['title'] ?? '' ) === '' || ( $section['text'] ?? '' ) === '' ) === []
	&& ( $privacyAfterApply['*']['hosting']['order'] ?? null ) === 200 );
check( 'the menu legal is created with the two pages by Element-URI, in that order', ( $configAfterApply['/nino/html/navroutes']['/legal/imprint']['legal'] ?? null ) === 1
	&& ( $configAfterApply['/nino/html/navroutes']['/legal/privacy']['legal'] ?? null ) === 2 );
check( '...and the footer of the base frames outputs it', str_contains( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/frame-footer.tpl', '' ), '[navigation nav="legal" id="legal__nav"]' ) === true );
check( 'the unit\'s words for the Elements panel are on the blacklist', in_array( '/_admin/elements/type/privacy/hint', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true
	&& in_array( '/_admin/elements/field/legal/text', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true );

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
/*	Elements are the editors' content: the second run, in another language,
	adds that language's versions and replaces nothing - not a section somebody
	rewrote, not a menu somebody ordered, whatever the wizard's overwrite says	*/
$privacyAfterSecond = \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] );
check( 'a second run in another language adds that language\'s versions of the sections', count( $privacyAfterSecond['en_US'] ?? [] ) === count( $privacyAfterSecond['de_DE'] ?? [] ) && count( $privacyAfterSecond['en_US'] ?? [] ) > 5
	&& ( $privacyAfterSecond['de_DE'] ?? [] ) === $privacyAfterApply['de_DE'] );
check( '...and leaves the menu legal and the path default as they were', ( $configAfterSecondApply['/nino/html/navroutes']['/legal/imprint']['legal'] ?? null ) === 1 && $configAfterSecondApply['/nino/legal/paths'] === $configAfterApply['/nino/legal/paths'] );
// An editor rewrites a section, deletes one and puts the privacy page first
\Nino\Filesystem::mutate( $appData, '/elements/privacy.php', static function( array $type ): array {
	$type['de_DE']['hosting']['title'] = 'Mein Hosting';
	unset( $type['de_DE']['tls'], $type['en_US']['tls'], $type['*']['tls'] );
	return $type;
}, [] );
$editedNavroutes = $appData['/nino/html/navroutes'];
$editedNavroutes['/legal/privacy']['legal'] = 1;
$editedNavroutes['/legal/imprint']['legal'] = 2;
$appData['/nino/html/navroutes'] = $editedNavroutes;
\Nino\AppData::writeContentData( $appData, [ '/nino/html/navroutes' ] );
$_POST['data'] = json_encode( [ 'locales' => [ 'en_US' ], 'modules' => [] ] );
$thirdApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $thirdApplyRequest );
$privacyAfterThird = \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] );
check( 'a third run leaves a section an editor rewrote as it is', ( $privacyAfterThird['de_DE']['hosting']['title'] ?? null ) === 'Mein Hosting' );
check( '...and a section deleted by hand without a tombstone comes back in the language of this run: the wizard only ever adds', isset( $privacyAfterThird['en_US']['tls'] ) === true && isset( $privacyAfterThird['*']['tls'] ) === true );
check( '...and the menu the editors ordered is theirs: the wizard creates it only while the project has none', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes']['/legal/privacy']['legal'] ?? null ) === 1
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes']['/legal/imprint']['legal'] ?? null ) === 2 );


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

/*	A unit file the step cannot copy. \Nino\Features::applyUnit() answers the
	first file it could not copy, and the wizard read no answer from it: the
	step went on to write config.php and answer 200, with a template missing
	and nothing to say so. A directory standing where the template goes is
	what a permission or a full disk does, provoked without either. The
	warning check is a guard - copyFile() is silenced already - kept so the
	refusal stays the whole outcome	*/
$blockedTemplate = $sandbox. '/private/templates/frame-header.tpl';
unlink( $blockedTemplate );
mkdir( $blockedTemplate, 0755, true );
$configBeforeBlocked	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$blockedWarnings			= [];
set_error_handler( static function( int $no, string $message ) use ( &$blockedWarnings ): bool {
	if( ( error_reporting() & $no ) !== 0 )
		$blockedWarnings[] = $message;
	return true;
} );
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$blockedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $blockedRequest );
restore_error_handler();
rmdir( $blockedTemplate );

check( 'a unit file the step cannot copy is a 500 naming the file', $blockedRequest['/nino/http/response']['statusCode'] === 500
	&& str_contains( (string) ( $blockedRequest['/nino/http/response']['body']['error'] ?? '' ), '/templates/frame-header.tpl' ) === true );
check( '...nothing was written to config.php for that apply', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) === $configBeforeBlocked );
check( '...and no engine warning was raised on the way', array_filter( $blockedWarnings, static fn( string $w ): bool => str_contains( $w, 'file_put_contents' ) ) === [] );

// What the refused apply changed in memory, back to what the file says -
// a real request ends with the refusal, this one goes on
$appData['/nino/locales/available']	= $configBeforeBlocked['/nino/locales/available'];
$appData['/nino/locales/native']		= $configBeforeBlocked['/nino/locales/native'];

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

// One step's message is shown by the same pane class the panes themselves are
// shown by - so a class the shell never sets means that step's messages are
// never seen. The Accounts step keyed on 'show-admin' while the shell sets
// 'show-accounts', so everything it had to say, "mail already in use"
// included, was written into an element with display:none
$wizardCss	= (string) file_get_contents( __DIR__. '/../_admin/install/assets/style.css' );
$wizardJs		= (string) file_get_contents( __DIR__. '/../_admin/install/assets/script.js' );

preg_match_all( '/paneClass\s*:\s*\x27([a-z-]+)\x27/', $wizardJs, $paneMatches );
preg_match_all( '/#install-page-wrap\.(show-[a-z-]+) #[a-z-]+-msg/', $wizardCss, $msgMatches );

$stepClasses	= $paneMatches[1];
$msgClasses		= array_values( array_unique( $msgMatches[1] ) );
$orphans			= array_values( array_diff( $msgClasses, $stepClasses ) );

check( 'the wizard has a pane class per step', count( $stepClasses ) === 6 && in_array( 'show-accounts', $stepClasses, true ) === true );
check( 'every step message is shown by a class the shell actually sets'. ( $orphans === [] ? '' : ' - orphaned: '. implode( ', ', $orphans ) ), $orphans === [] );

// The burger menu is a checkbox behind a label, and the checkbox used to be
// display:none - which is not rendered, and what is not rendered cannot be
// focused: the whole navigation of every narrow viewport could be opened with
// a pointer and by nothing else. Hidden, not removed, and the icon carries
// the focus ring the control has nothing left to show one with
$ninoCss = (string) file_get_contents( __DIR__. '/../_nino/Nino.css' );
$burger  = '';

// Without the comments: this file explains itself, and one of the sentences
// in that block is about the display:none it no longer has
if( preg_match( '/\.nino-nav-burger input\[type="checkbox"\]\s*\{([^}]*)\}/', $ninoCss, $match ) === 1 )
	$burger = (string) preg_replace( '#/\*.*?\*/#s', '', $match[1] );

check( 'the burger control is hidden without being taken out of the tab order', $burger !== ''
	&& preg_match( '/display:\s*none/', $burger ) !== 1
	&& preg_match( '/opacity:\s*0/', $burger ) === 1 );
check( '...and something shows when it has the focus', str_contains( $ninoCss, '.nino-nav-burger input[type="checkbox"]:focus-visible' ) === true );

// A visitor who asked their system for less motion. The parallax has said so
// since it was written; everything else that moves on its own had not
$reducedMotion = '';

if( preg_match( '/@media \(prefers-reduced-motion: reduce\) \{(.*?)\n\}/s', $ninoCss, $match ) === 1 )
	$reducedMotion = $match[1];

check( 'what runs forever on its own stops for a visitor who asked for less motion', $reducedMotion !== ''
	&& str_contains( $reducedMotion, '.nino-atf-arrowdown' ) === true
	&& str_contains( $reducedMotion, '.nino-fx-vertical-bounce' ) === true
	&& str_contains( $reducedMotion, 'animation: none' ) === true );
check( '...a viewport animation is simply there rather than arriving', str_contains( $reducedMotion, '.nino-vpa' ) === true
	&& str_contains( $reducedMotion, 'opacity: 1' ) === true );
check( '...and a jump to an anchor is a jump, not a ride', str_contains( $reducedMotion, 'scroll-behavior: auto' ) === true );

// The attribute the scripts set beside their classes, said again in the
// stylesheet: an author display rule beats the browser's own [hidden]
check( 'the stylesheet keeps what the scripts hide hidden', str_contains( $ninoCss, '.nino-tabs-panel[hidden]' ) === true
	&& str_contains( $ninoCss, '.nino-filter-item[hidden]' ) === true );

// And the delivered header must not reach for the one thing that rule cannot
// take back. A plain height on the bar would survive all of it - so the frame
// the base unit ships does not have one, and this is where a replacement that
// does finds that out
$fixedHeight 	= [];
$frameMarkup 	= (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/frame-header.tpl' );
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
// What a page template requires is its manifest's business - every listed
// template has to answer with exactly what its manifest declares
$pagesLibrary = __DIR__. '/../_admin/install/library/pages';
check( 'every page template lists exactly the modules its manifest requires', count( $wpLibraryBody['templates'] ) > 0
	&& array_filter( $wpLibraryBody['templates'], static fn( array $t, string $key ): bool => $t['requiresModules'] !== (array) ( ( include $pagesLibrary. '/'. $key. '/manifest.php' )['requiresModules'] ?? [] ), ARRAY_FILTER_USE_BOTH ) === [] );
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
check( '...which is the handful a site is normally built from', count( array_intersect( [ 'home', 'contact', '404' ], $presetKeys ) ) === 3 && in_array( 'legal', $presetKeys, true ) === false );
check( '...each carrying its own suggested Http-URI, not its folder name', ( $wpLibraryBody['webpages'][0]['httpUri'] ?? null ) === '/'
	&& ( $wpLibraryBody['webpages'][0]['uri'] ?? null ) === '/home' );
check( '...its own per-locale wording rather than the generic fallback', ( $wpLibraryBody['webpages'][0]['text']['de_DE']['name'] ?? null ) === 'Startseite'
	&& ( $wpLibraryBody['webpages'][0]['text']['en_US']['name'] ?? null ) === 'Home' );
// The one field no form offers and apiApply() takes straight off the entry
check( '...and the status code its manifest route declares', ( array_values( array_filter( $wpLibraryBody['webpages'],
	static fn( array $e ): bool => $e['libraryKey'] === '404' ) )[0]['statusCode'] ?? null ) === 404 );
check( 'navigations are offered - the menus the config holds, since Navigation is always active', $wpLibraryBody['navs'] === $expectedNavs );
// ...so a preset page proposes whatever menus its own manifest route
// suggests, intersected with what the project actually registers - home/
// contact suggest both, 404 none - the imprint is no page of the starter site, the Legal module has it
check( '...and each proposed page carries the menu membership its own unit suggests', array_column( $wpLibraryBody['webpages'], 'navs' ) === [ [ 'main', 'footer' ], [ 'main', 'footer' ], [] ] );

// Each template also reports the starter wording its own text fragments
// ship, so the form can prefill a new entry per locale instead of leaving
// every locale nobody hand-typed on DEFAULT_TEXT (see _suggestions())
check( 'reports "home"\'s own suggested wording for every picked locale, not just one', array_keys( $wpLibraryBody['templates']['home']['text'] ) === [ 'de_DE', 'en_US' ] );
check( '...with de_DE\'s wording read from the unit\'s own de_DE fragment', $wpLibraryBody['templates']['home']['text']['de_DE']['name'] === 'Startseite' );
check( '...and en_US\'s from its own en_US fragment - the locale that used to end up generic', $wpLibraryBody['templates']['home']['text']['en_US']['name'] === 'Home' && $wpLibraryBody['templates']['home']['text']['en_US']['title'] === 'Welcome.' );
check( 'reports the Http-URI "home" suggests for itself, which is not its folder name', $wpLibraryBody['templates']['home']['uri'] === '/' );
check( '...and "contact"\'s, which is', $wpLibraryBody['templates']['contact']['uri'] === '/contact' );
check( 'the page library has no legal page any more - the Legal module brings the imprint and the privacy policy', is_dir( __DIR__. '/../_admin/install/library/pages/legal' ) === false && in_array( 'legal', array_keys( $wpLibraryBody['templates'] ), true ) === false );
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

// The two Element-URIs of the Legal module's pages are its: a page of the
// project's own cannot take them, whatever route it brings
foreach( [ '/legal/imprint', '/legal/privacy' ] as $reservedElementUri ) {
	$_POST['data'] = json_encode( [ 'webpages' => [ [ 'uri' => $reservedElementUri, 'httpUri' => '/my-page', 'libraryKey' => 'blank', 'text' => [] ] ] ] );
	$reservedElementRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Webpages::apiApply( $appData, $reservedElementRequest );
	check( 'rejects the Legal module\'s Element-URI '. $reservedElementUri. ' with 409', $reservedElementRequest['/nino/http/response']['statusCode'] === 409 && isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://my-page'] ) === false );
}

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
		'en_US' => [ 'name' => '<script>alert(1)</script>Contact', 'title' => 'Say "hello" to <b>us</b>' ],
	] ],
] ] );
/*	A copy the step cannot make is the step's failure, not a success over a
	route that renders a file that is not there. The Routes step kept its own
	copy of the unit helpers, and that copy swallowed a failed write - here a
	directory stands where contact's template has to go, so the write fails
	the way a permission or a full disk would	*/
mkdir( $sandbox. '/private/templates/page-contact.tpl', 0755, true );
$blockedApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $blockedApplyRequest );
check( 'a template the step cannot write fails the apply and names the file', $blockedApplyRequest['/nino/http/response']['statusCode'] === 500 && str_contains( (string) ( $blockedApplyRequest['/nino/http/response']['body']['error'] ?? '' ), '/templates/page-contact.tpl' ) === true );
check( '...before any route is written', isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://kontakt'] ) === false );
rmdir( $sandbox. '/private/templates/page-contact.tpl' );

/*	The home unit's hero is an image slot seeded with its shipped placeholder.
	A picture the step cannot put in place fails the step by name, the way the
	template above did - and no slot is seeded for a file that is not there	*/
$seedFile = $sandbox. '/public/images/template/page-home/fullscreen-image/background.svg';
if( is_file( $seedFile ) === true )
	unlink( $seedFile );
mkdir( $seedFile, 0755, true );
$blockedSeedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $blockedSeedRequest );
check( 'a seed file the step cannot copy fails the apply and names the file', $blockedSeedRequest['/nino/http/response']['statusCode'] === 500 && str_contains( (string) ( $blockedSeedRequest['/nino/http/response']['body']['error'] ?? '' ), '/images/template/page-home/fullscreen-image/background.svg' ) === true );
check( '...and no page image slot is seeded', array_keys( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [] ) === [ '/logo' ] );
rmdir( $seedFile );

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
check( 'copies a page unit\'s declared files into the project', is_file( $sandbox. '/public/images/template/page-home/fullscreen-image/background.svg' ) === true );

// The hero is the slot the page template names, and nothing is literal in it
$heroSlot = $configAfterWpApply['/nino/html/images']['/template/page-home/fullscreen-image/background'] ?? [];
// The native locale of this run is en_US (the steps above moved it), so the label is that one
check( 'seeds the unit\'s image slot into config.php: the native-locale label, 1920x1080 and the shipped placeholder', ( $heroSlot['label'] ?? null ) === 'Home – hero image'
	&& ( $heroSlot['width'] ?? null ) === 1920 && ( $heroSlot['height'] ?? null ) === 1080 && ( $heroSlot['filename'] ?? null ) === 'template/page-home/fullscreen-image/background.svg'
	&& is_file( $sandbox. '/public/images/'. ( $heroSlot['filename'] ?? 'missing' ) ) === true );

check( 'the seeded placeholder carries the slot\'s own name, so an upload replaces it and Remove image deletes it, as for any file the slot wrote', str_starts_with( (string) ( $heroSlot['filename'] ?? '' ), 'template/page-home/fullscreen-image/background.' ) === true
	&& is_file( __DIR__. '/../_admin/install/library/pages/home/images/demo.jpg' ) === false );
\Nino\Modules\Images::init( $appData );
\Nino\Modules\Template::init( $appData );
$homeHtml = \Nino\Html::renderHtml( $appData, '[template /templates/page-home]' );
check( 'the home template renders the slot as an <img> with its size and an empty alt, and no literal image path', str_contains( $homeHtml, '/images/template/page-home/fullscreen-image/background.svg" width="1920" height="1080" alt="">' ) === true
	&& str_contains( $homeHtml, '[[/nino/public]]/images/' ) === false && str_contains( $homeHtml, '[image' ) === false );

// The logo, which is a slot too: nothing in the shipped frames, the navigation
// or the mail names a logo file, and with no logo uploaded none of them
// leaves a broken <img> or an empty meta tag behind
\Nino\Modules\Navigation::init( $appData );
$logoFrames = static function( array &$appData ): array {
	return [
		'header'	=> \Nino\Html::renderHtml( $appData, '[template /templates/frame-header]' ),
		'meta'		=> \Nino\Html::renderHtml( $appData, '[template /templates/html-header]' ),
		'mail'		=> \Nino\Html::renderHtml( $appData, '[template /templates/mail-header]' ),
	];
};
$logoShipped = '';
foreach( [ '/templates/frame-header.tpl', '/templates/html-header.tpl', '/templates/mail-header.tpl', '/templates/html-header-nav.tpl' ] as $logoTemplate )
	$logoShipped .= (string) \Nino\Filesystem::getFileContent( $appData, $logoTemplate, '' );
check( 'no shipped template names a logo file: every one asks the logo slot', preg_match( '#images/logo#', $logoShipped ) === 0 && substr_count( $logoShipped, '[image /logo' ) === 5 );

\Nino\Html::addFills( $appData, [ '[[/project/website/general/url]]' => 'www.example.com', '[[/project/company/general/name]]' => 'Acme' ], '*' );
$noLogo = $logoFrames( $appData );
check( 'without an uploaded logo the header has no <img> and the mail header no picture at all', str_contains( $noLogo['header'], '<img' ) === false && str_contains( $noLogo['header'], '[image' ) === false
	&& str_contains( $noLogo['mail'], '<img' ) === false && str_contains( $noLogo['mail'], '[image' ) === false );
check( '...and the page head has no og:image or twitter:image, not even an empty one', str_contains( $noLogo['meta'], 'og:image' ) === false && str_contains( $noLogo['meta'], 'twitter:image' ) === false && str_contains( $noLogo['meta'], '[image' ) === false );
check( '...the navigation\'s logo is an empty wrapper', str_contains( $noLogo['header'], '<div class="nino-headernav-logo"></div>' ) === true );

$appData['/nino/html/images']['/logo']['filename'] = 'logo.webp';
$withLogo = $logoFrames( $appData );
$logoUrl = \Nino\Images::getUrl( $appData, 'logo.webp' );
check( 'with a logo the header shows it with an empty alt, as the frame always did', str_contains( $withLogo['header'], '<img src="'. $logoUrl. '" width="500" height="100" alt="">' ) === true );
check( '...the navigation shows it in its wrapper, with the company name as its alt', preg_match( '#<div class="nino-headernav-logo"><img src="'. preg_quote( $logoUrl, '#' ). '" width="500" height="100" alt="Acme"></div>#', $withLogo['header'] ) === 1 );
check( '...og:image and twitter:image carry its absolute address', str_contains( $withLogo['meta'], '<meta property="og:image" content="https://www.example.com'. $logoUrl. '">' ) === true
	&& str_contains( $withLogo['meta'], '<meta name="twitter:image" content="https://www.example.com'. $logoUrl. '">' ) === true );
check( '...and the mail an <img> with the absolute address and the company name as its alt', preg_match( '#<img src="https://www\.example\.com'. preg_quote( $logoUrl, '#' ). '" width="180" alt="Acme">#', $withLogo['mail'] ) === 1 );
$appData['/nino/html/images']['/logo']['filename'] = null;

// So the Image Slots tab's scan has nothing to propose for it: the copied template is no literal <img> of the images directory
check( 'the copied home template names the slot and has no literal <img> of the images directory for the template scan to find', str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ), '[image /template/page-home/fullscreen-image/background alt=""]' ) === true
	&& preg_match( '#<img\b[^>]*src="[^"]*/images/#i', (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ) ) === 0 );
// A fresh install is the thing the dashboard's "Missing image slots" tile counts: with the logo and the hero both slots, no template has an <img> of the images directory left
check( 'on a fresh install the Missing image slots tile reads 0 - every picture of the starter site is a slot', \Nino\Modules\Images\Slots::missingCount( $appData ) === 0 );

$deAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );

check( 'writes the home entry\'s own de_DE meta, keyed by its Element-URI (not its Http-URI or the "home" template name)', $deAfterWpApply['[[/_nino/webpage/site-home/name]]'] === 'Start' && $deAfterWpApply['[[/_nino/webpage/site-home/title]]'] === 'Willkommen' );
check( 'writes the home entry\'s own en_US meta too', $enAfterWpApply['[[/_nino/webpage/site-home/name]]'] === 'Home' );
check( 'a field left blank in the post (contact\'s en_US) falls back to the generic placeholder, not the "contact" template\'s own wording', $enAfterWpApply['[[/_nino/webpage/site-contact/description]]'] === 'Page description.' );
check( 'a page name, title and description are filtered like the Routes panel filters them: markup stripped, a quote written as an entity', $enAfterWpApply['[[/_nino/webpage/site-contact/name]]'] === 'alert(1)Contact'
	&& $enAfterWpApply['[[/_nino/webpage/site-contact/title]]'] === 'Say &quot;hello&quot; to us' && str_contains( $enAfterWpApply['[[/_nino/webpage/site-contact/name]]'], '<' ) === false );
check( 'a field that was posted (contact\'s de_DE name) is used as-is', $deAfterWpApply['[[/_nino/webpage/site-contact/name]]'] === 'Kontakt' );
check( 'no key named for a library folder is in the project - only the Element-URI-keyed one this class writes itself', isset( $deAfterWpApply['[[/_nino/webpage/home/name]]'] ) === false );
// The system's own form is a key of the system's: the only /_nino keys the
// texts of a project hold are the ones written after an Element-URI or a
// language code. A unit that delivered another would be a unit writing what
// is not its to write (tests/keys-smoke.php checks the library for it)
$systemKeys = array_filter( array_merge( array_keys( $deAfterWpApply ), array_keys( $enAfterWpApply ), array_keys( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) ) ), static fn( string $key ): bool => str_starts_with( $key, '[[/_nino/' ) );
check( 'the only /_nino keys of the project texts are the pages\' details (the two legal pages among them) and the languages\' names', $systemKeys !== []
	&& array_filter( $systemKeys, static fn( string $key ): bool => preg_match( '#^\[\[/_nino/(webpage/(site-(home|contact)|legal\/(imprint|privacy))/(name|title|description|uri)|locale/(de_DE|en_US)/name)\]\]$#', $key ) !== 1 ) === [] );
$globalAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklistAfterWpApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'writes every page\'s reachable Http-URI as one global fill, so a template can link to it by name', ( $globalAfterWpApply['[[/_nino/webpage/site-home/uri]]'] ?? null ) === '/'
	&& ( $globalAfterWpApply['[[/_nino/webpage/site-contact/uri]]'] ?? null ) === '/kontakt'
	&& isset( $deAfterWpApply['[[/_nino/webpage/site-home/uri]]'], $enAfterWpApply['[[/_nino/webpage/site-home/uri]]'] ) === false );
check( 'a page uri is a technical value, blacklisted out of the Text panel like every other route key', in_array( '/_nino/webpage/site-home/uri', $blacklistAfterWpApply, true )
	&& in_array( '/_nino/webpage/site-contact/uri', $blacklistAfterWpApply, true )
	&& count( array_unique( $blacklistAfterWpApply ) ) === count( $blacklistAfterWpApply ) );
check( 'a template\'s own deeper content (unprefixed, shared across instances) still merges in', isset( $deAfterWpApply['[[/template/page-home/welcome/title]]'] ) === true );

$libraryAfterWpApply = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiList( $appData, $libraryAfterWpApply );
check( 'apiList now reflects the persisted, current list', array_column( $libraryAfterWpApply['/nino/http/response']['body']['webpages'], 'httpUri' ) === [ '/', '/kontakt' ] );

// --- a 'templatePerRoute' unit gets one template per route ----------------
//
// "blank" is the empty starting point, so every route picking it has to get
// its own file: with one shared page-blank.tpl, building a second blank page
// silently rewrote the first one. A finished unit (home, contact, ...) is a
// one-off and keeps sharing - that is what its template is for
// An editor has replaced the hero's image and renamed the slot since: applying
// the step again only adds, it never puts the shipped state back
$appData['/nino/html/images']['/template/page-home/fullscreen-image/background']['label'] = 'Mein Titelbild';
$appData['/nino/html/images']['/template/page-home/fullscreen-image/background']['filename'] = 'template/page-home/fullscreen-image/background.1920x1080.jpg';
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

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

$heroAfterSecondApply = $configPerRoute['/nino/html/images']['/template/page-home/fullscreen-image/background'] ?? [];
check( 'a second apply keeps a slot the project already has - its label and its changed image', ( $heroAfterSecondApply['label'] ?? null ) === 'Mein Titelbild'
	&& ( $heroAfterSecondApply['filename'] ?? null ) === 'template/page-home/fullscreen-image/background.1920x1080.jpg' && array_keys( $configPerRoute['/nino/html/images'] ) === [ '/logo', '/template/page-home/fullscreen-image/background' ] );

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

// A page of your own whose Element-URI is the name of a library unit: its own
// copy would be written to templates/page-home.tpl, which is the file the
// home unit owns - and its body would be the home unit's body to the byte, so
// the wizard read the page back as that unit and the next apply wrote the
// library's home page over whatever had been built in it. Refused where it is
// typed, naming the page of the library that has the name
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/home', 'httpUri' => '/eigene-startseite', 'libraryKey' => 'blank', 'text' => [] ],
] ] );
$collisionRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $collisionRequest );
check( 'a page of your own cannot take a library page\'s template name', $collisionRequest['/nino/http/response']['statusCode'] === 409
	&& str_contains( (string) ( $collisionRequest['/nino/http/response']['body']['error'] ?? '' ), '"home"' ) === true );
$configAfterCollision = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( '...and nothing of it was written - no route for it, and the library page\'s template as it was', isset( $configAfterCollision['/nino/http/routes']['GET://eigene-startseite'] ) === false
	&& trim( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/page-home.tpl', '' ) ) !== '' );

// The same for a module's unit: /legal-imprint would be given the Legal
// module's own page-legal-imprint.tpl as its copy
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/legal-imprint', 'httpUri' => '/eigenes-impressum', 'libraryKey' => 'blank', 'text' => [] ],
] ] );
$moduleCollisionRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $moduleCollisionRequest );
check( 'a page of your own cannot take the template name of a module\'s unit, and the answer names the unit - not a page of the library', $moduleCollisionRequest['/nino/http/response']['statusCode'] === 409
	&& str_contains( (string) ( $moduleCollisionRequest['/nino/http/response']['body']['error'] ?? '' ), '"legal" unit' ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://eigenes-impressum'] ) === false );

// A name no unit owns is one more page of your own, as before
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/team', 'httpUri' => '/team', 'libraryKey' => 'blank', 'text' => [] ],
	[ 'uri' => '/startseite', 'httpUri' => '/eigene-startseite', 'libraryKey' => 'blank', 'text' => [] ],
] ] );
$ownNameRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $ownNameRequest );
check( 'a name no library page owns is a page of your own like any other', $ownNameRequest['/nino/http/response']['statusCode'] === 200
	&& \Nino\Filesystem::fileExists( $appData, '/templates/page-startseite.tpl' ) === true );

// A template's file name is its category (see \Nino\Modules\Template::category()),
// and every page of your own is page-<slug>: a slug that is the name of a
// category elsewhere - /footer, /common - or begins with a digit - /2026-home -
// is a page like any other, with a category of its own and none to collide with
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/footer', 'httpUri' => '/footer', 'libraryKey' => 'blank', 'text' => [] ],
	[ 'uri' => '/common', 'httpUri' => '/common', 'libraryKey' => 'blank', 'text' => [] ],
	[ 'uri' => '/2026-home', 'httpUri' => '/2026-home', 'libraryKey' => 'blank', 'text' => [] ],
] ] );
$categoryNameRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $categoryNameRequest );
check( 'pages named /footer, /common and /2026-home are accepted', $categoryNameRequest['/nino/http/response']['statusCode'] === 200 );
check( '...and get page-footer.tpl, page-common.tpl and page-2026-home.tpl, each of which has a category', \Nino\Filesystem::fileExists( $appData, '/templates/page-footer.tpl' ) === true
	&& \Nino\Filesystem::fileExists( $appData, '/templates/page-common.tpl' ) === true && \Nino\Filesystem::fileExists( $appData, '/templates/page-2026-home.tpl' ) === true
	&& \Nino\Modules\Template::category( 'page-footer.tpl' ) === 'page-footer' && \Nino\Modules\Template::category( 'page-common.tpl' ) === 'page-common'
	&& \Nino\Modules\Template::category( 'page-2026-home.tpl' ) === 'page-2026-home' );

// Navigation: always active now (see ALWAYS_MODULES above). Membership is
// posted explicitly per entry and stored on its route.
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$navSetupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $navSetupRequest );

$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Start' ], 'en_US' => [ 'name' => 'Home' ] ] ],
	[ 'uri' => '/site-contact', 'httpUri' => '/kontakt', 'libraryKey' => 'contact', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Kontakt' ], 'en_US' => [ 'name' => 'Contact' ] ] ],
	[ 'uri' => '/site-rules', 'httpUri' => '/impressum', 'libraryKey' => 'blank', 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Recht' ], 'en_US' => [ 'name' => 'Rules' ] ] ],
] ] );
$navWpApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $navWpApplyRequest );

$deAfterNav = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enAfterNav = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );

// Menu membership lives on the route each entry owns, not in a generated
// textfill - see \Nino\Modules\Navigation::routeLines(). Nothing is written
// per locale here at all: the menu is built per request, from the same
// /_nino/webpage<uri>/name keys the entries already carry
$routesAfterNav = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];

check( 'the Navigation module registers the menus the editors offer, the Legal unit\'s among them', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navs'] === $expectedNavs );
check( 'an entry explicitly assigned to main joins that menu at its own position in the list', ( $routesAfterNav['GET://']['navs'] ?? null ) === [ 'main' => 1 ] );
check( '...and so does the second one, one position further down', ( $routesAfterNav['GET://kontakt']['navs'] ?? null ) === [ 'main' => 2 ] );
check( 'an entry that is in no menu carries no membership at all', isset( $routesAfterNav['GET://impressum']['navs'] ) === false );
check( 'nothing is generated into the text files anymore', isset( $deAfterNav['[[/website/navigation/main]]'] ) === false && isset( $enAfterNav['[[/website/navigation/main]]'] ) === false );

// The routes stand in list order, which is what equal priorities fall back
// to - reordering pages is what reorders the menus
check( 'the applied routes stand in the list\'s own order', array_slice( array_keys( $routesAfterNav ), -3 ) === [ 'GET://', 'GET://kontakt', 'GET://impressum' ] );

// A page is registered at whatever Http-URI the entry picked
check( 'registers an entry at its own picked Http-URI', isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://impressum'] ) === true );

// The imprint is the Legal module's, not a page of the wizard: nothing mirrors
// a legal link into /website/legal/* any more, the menu 'legal' is the link
$globalAfterNav = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'no /website/legal/* key is written for a page', isset( $globalAfterNav['[[/website/legal/uri]]'] ) === false && isset( $deAfterNav['[[/website/legal/name]]'] ) === false && isset( $enAfterNav['[[/website/legal/name]]'] ) === false );

// Dropping pages narrows the routes (replace semantics)
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Start' ], 'en_US' => [ 'name' => 'Home' ] ] ],
] ] );
$dropWpApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $dropWpApplyRequest );

$configAfterDrop = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'dropping "kontakt"/"impressum" removes their routes (replace, not merge)', isset( $configAfterDrop['/nino/http/routes']['GET://kontakt'] ) === false && isset( $configAfterDrop['/nino/http/routes']['GET://impressum'] ) === false );
check( 'the home route survives, still keyed the same way', isset( $configAfterDrop['/nino/http/routes']['GET://'] ) === true );
check( 'a hand-written route still survives this replace too', isset( $configAfterDrop['/nino/http/routes']['GET://custom'] ) === true );
check( 'the legal menu and its two Element-URIs are not touched by a replace of the pages', ( $configAfterDrop['/nino/html/navroutes']['/legal/privacy']['legal'] ?? null ) === 1 && ( $configAfterDrop['/nino/html/navroutes']['/legal/imprint']['legal'] ?? null ) === 2 );

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
// /nino/http/routes plus the /_nino/webpage<uri>/* keys in the text files (see
// Webpages::pages() and Admin.php's PageEditor::pages()). That only works if
// the route really carries everything either side needs to reopen an entry -
// its Element-URI, its body and its status code. Without that, every shipped
// page is unopenable in /_admin, and saving the 404 page there quietly turns
// it into a 200.
$_POST['data'] = json_encode( [ 'webpages' => [
	[ 'uri' => '/site-home', 'httpUri' => '/', 'libraryKey' => 'home', 'navs' => [ 'main' ], 'text' => [ 'de_DE' => [ 'name' => 'Start' ] ] ],
	[ 'uri' => '/site-404', 'httpUri' => '/404', 'libraryKey' => '404', 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Weg' ] ] ],
	[ 'uri' => '/site-legal', 'httpUri' => '/legal', 'libraryKey' => '', 'body' => '[template /templates/page-legal.[[/nino/http/response/locale]]]', 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Recht' ] ] ],
] ] );
$sharedApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $sharedApplyRequest );
check( 'apply succeeds', $sharedApplyRequest['/nino/http/response']['statusCode'] === 200 );

$sharedConfig = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$sharedList 	= $sharedApplyRequest['/nino/http/response']['body']['webpages'];

check( 'reports "template" as the on-disk template file /_admin selects from', array_column( $sharedList, 'template' ) === [ 'page-home', 'page-404', '' ] );
check( 'resolves each entry back to the library unit it came from, from its route body alone', array_column( $sharedList, 'libraryKey' ) === [ 'home', '404', '' ] );
check( 'reports each entry\'s status code, read back off its route', array_column( $sharedList, 'statusCode' ) === [ 200, 404, 200 ] );
check( 'the 404 entry really is a 404 in the route too', ( $sharedConfig['/nino/http/routes']['GET://404']['statusCode'] ?? 200 ) === 404 );
check( 'a body that resolves one template per locale reports no single template', $sharedList[2]['body'] === '[template /templates/page-legal.[[/nino/http/response/locale]]]' );

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

// The locale-resolving body (a hand-made entry: no library unit has one any
// more) the template <select> can't spell: saving that
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
	'template' => 'page-home', 'navs' => [], 'statusCode' => 201,
	// A name and a title in every active language are required there
	'text' => array_fill_keys( \Nino\Locales::getAvailableLocales( $appData ), [ 'name' => 'Dev made', 'title' => 'Dev made' ] ),
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

// Saving, deleting and moving a route all read the routes, decide against
// what they find and write the whole key back - so two editors saving two
// different pages at the same moment each wrote their own full copy, and the
// second one dropped the first one's page. writeContentData() locks for its
// own write, which is after the decision; these hold the lock across both
$routesSource = (string) file_get_contents( __DIR__. '/../_admin/Nino/Modules/Routes/Admin/Admin.php' );
$unlocked = [];

foreach( [ 'apiSave', 'apiDelete', 'apiMove' ] as $action ) {

	$body = substr( $routesSource, strpos( $routesSource, 'public static function '. $action. '(' ) ?: 0 );
	$body = substr( $body, 0, strpos( $body, "\n\t\t}" ) ?: strlen( $body ) );

	$lockAt		= strpos( $body, "lockFile( \$appData, '/config.php' )" );
	$readAt		= strpos( $body, "getFileContent( \$appData, '/config.php'" );
	$unlockAt	= strpos( $body, "unlockFile( \$appData, '/config.php' )" );

	if( $lockAt === false || $readAt === false || $unlockAt === false || $lockAt > $readAt || $unlockAt < $readAt || str_contains( $body, '} finally {' ) === false )
		$unlocked[] = $action;
}

check( 'every route write decides and writes under one lock'. ( $unlocked === [] ? '' : ' - unlocked: '. implode( ', ', $unlocked ) ), $unlocked === [] );

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
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/project/company/general/name]]' => 'Acme Inc', '[[/project/company/contact/address]]' => 'Street 1', '[[/project/website/general/author]]' => 'Acme Inc', '[[/project/website/general/url]]' => 'www.acme.test', '[[/project/website/general/host]]' => 'Acme Hosting', '[[/project/company/social/instagram]]' => 'https://www.instagram.com/acme' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/project/company/contact/country]]' => 'Deutschland', '[[/project/website/html/lang]]' => 'de', '[[/template/page-home/welcome/headline]]' => 'Willkommen', '[[/_nino/webpage/kontakt/name]]' => 'Kontakt' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/project/company/contact/country]]' => 'Germany', '[[/project/website/html/lang]]' => 'en', '[[/template/page-home/welcome/headline]]' => 'Welcome', '[[/_nino/webpage/kontakt/name]]' => 'Contact' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', [ '/project/website/html/lang' ] );

$personalInfosListRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\PersonalInfos::apiList( $appData, $personalInfosListRequest );
$personalInfosBody = $personalInfosListRequest['/nino/http/response']['body'];
$personalInfosEntries = $personalInfosBody['entries'];
$personalInfosKeys = array_column( $personalInfosEntries, 'key' );

check( 'lists the locales', $personalInfosBody['locales'] === [ 'de_DE', 'en_US' ] );
check( 'a /project/company/* key is listed', array_search( '/project/company/general/name', $personalInfosKeys, true ) !== false );
check( 'another /project/company/* key is listed', array_search( '/project/company/contact/country', $personalInfosKeys, true ) !== false );
check( 'the technical /project/website/html/* keys are left out: they are in no prefix, blacklisted or not', array_search( '/project/website/html/lang', $personalInfosKeys, true ) === false
	&& array_search( '/project/website/html/charset', $personalInfosKeys, true ) === false );
check( '...and so is the look of the mails, which the base unit ships under /project/mail/', array_search( '/project/mail/color/primary', $personalInfosKeys, true ) === false );
check( 'a key outside /project/company/* and /project/website/general/* is left out', array_search( '/template/page-home/welcome/headline', $personalInfosKeys, true ) === false );
check( 'a webpage\'s own meta key is left out too, despite existing in text/*.php', array_search( '/_nino/webpage/kontakt/name', $personalInfosKeys, true ) === false );
// The links to a site's profiles elsewhere are the catalogue's Social links
// feature - an element type the editors keep - and the base unit ships none
// of the four /company/<network> keys it had, so the step asks for none of
// them, not even where a project's text still holds one
check( 'a social network\'s address is not asked for: the base unit ships no such key', array_search( '/project/company/social/instagram', $personalInfosKeys, true ) === false );

$personalInfosLabels = array_column( $personalInfosEntries, 'label', 'key' );
check( 'derives a friendly label from the category and the name, each as the English vocabulary of the workbench says it', $personalInfosLabels['/project/company/general/name'] === 'Company › Name' );
check( '...also for a key of the website', $personalInfosLabels['/project/website/general/author'] === 'Website › Author' );
check( '...and an address, spelled right', $personalInfosLabels['/project/company/contact/address'] === 'Company › Address' );
check( '...the words of the vocabulary as they are written there, not capitalized by rule: an address is a "URL", hosting is "Hosting"', $personalInfosLabels['/project/website/general/url'] === 'Website › URL' && $personalInfosLabels['/project/website/general/host'] === 'Website › Hosting' );
check( 'every label of the step is made of words of the vocabulary', array_filter( $personalInfosLabels, static function( string $label ): bool {
	$english = include __DIR__. '/../_admin/text/en_US.php';
	return count( array_filter( explode( ' › ', $label ), static fn( string $word ): bool => in_array( $word, $english, true ) === false ) ) > 0;
} ) === [] );

$saveBatchRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST['data'] = json_encode( [ 'items' => [
	[ 'key' => '/project/company/contact/country', 'locale' => 'de_DE', 'value' => 'Musterland' ],
	[ 'key' => '/project/company/contact/country', 'locale' => 'en_US', 'value' => 'Sample Country' ],
] ] );
\Nino\Install\PersonalInfos::apiSaveBatch( $appData, $saveBatchRequest );
$saveResults = $saveBatchRequest['/nino/http/response']['body']['results'];

check( 'saves the de_DE value', $saveResults['/project/company/contact/country']['ok'] === true && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/company/contact/country]]'] ?? null ) === 'Musterland' );
check( 'saves the en_US value', ( \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/project/company/contact/country]]'] ?? null ) === 'Sample Country' );

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

/*	The other half of usableUsers()' rule. The placeholder above is disabled;
	an entry that is enabled but carries no password is an array key rather
	than an account, and must not satisfy the same "at least one admin"
	precondition merely because the key exists	*/
$appData['/nino/auth/user']['no-password@example.com'] = [ 'pw' => '', 'status' => 2, 'sessions' => [], 'perms' => [ '/*' ] ];
$finishWithoutPasswordRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $finishWithoutPasswordRequest );
check( '...nor an enabled entry that carries no password', \Nino\Install\Accounts::usableUsers( $appData ) === [] && $finishWithoutPasswordRequest['/nino/http/response']['statusCode'] === 409 );
unset( $appData['/nino/auth/user']['no-password@example.com'] );

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

/*	The rule the form names up front is the rule the server enforces. The
	number is read from the wizard's own template - the label and the
	minlength of all four password fields - and tried against the public
	API either side of it, so neither half can move alone	*/
$wizardTemplate = (string) file_get_contents( __DIR__. '/../_admin/install/templates/page-wizard.tpl' );
$pwLengths = [];
foreach( [ 'accounts-add-pw', 'accounts-add-pw2', 'finish-pw', 'finish-pw2' ] as $pwField )
	$pwLengths[ $pwField ] = preg_match( '/<input id="'. $pwField. '"[^>]*minlength="(\d+)"/', $wizardTemplate, $pwMatch ) === 1 ? (int) $pwMatch[1] : 0;
$minPw = $pwLengths['accounts-add-pw'];
check( 'the wizard\'s four password fields carry one and the same minlength', $minPw > 0 && count( array_unique( $pwLengths ) ) === 1 );
check( '...and both password labels name that number', preg_match( '/<span>Password \(at least '. $minPw. ' characters\)<\/span>/', $wizardTemplate ) === 1
	&& preg_match( '/<span>New recovery password \(at least '. $minPw. ' characters\)<\/span>/', $wizardTemplate ) === 1 );
check( 'the Accounts step asks for the password twice', str_contains( $wizardTemplate, 'for="accounts-add-pw2"' ) === true );

$_POST['data'] = json_encode( [ 'mail' => 'boundary@example.com', 'pw' => str_repeat( 'x', max( 0, $minPw - 1 ) ) ] );
$belowRuleRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $belowRuleRequest );
check( 'a password one character below the rule the form shows is refused by the server', $belowRuleRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'mail' => 'boundary@example.com', 'pw' => str_repeat( 'x', $minPw ) ] );
$atRuleRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Accounts::apiCreate( $appData, $atRuleRequest );
check( '...and one of exactly that length is accepted', $atRuleRequest['/nino/http/response']['statusCode'] === 200 );

echo "\n";


// --- Finish::apiComplete / Install::setRecoverySecret ------------------------

echo "Finish::apiComplete / Install::setRecoverySecret\n";

$_POST['data'] = json_encode( [ 'password' => 'short' ] );
$shortFinishRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $shortFinishRequest );
check( 'rejects a too-short _admin password with 400', $shortFinishRequest['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'password' => str_repeat( 'x', max( 0, $minPw - 1 ) ) ] );
$belowFinishRuleRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Finish::apiComplete( $appData, $belowFinishRuleRequest );
check( '...also one character below the rule the Finish form shows', $belowFinishRuleRequest['/nino/http/response']['statusCode'] === 400 );

// setRecoverySecret() no longer rewrites php source: it stores the hash under
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
check( 'setRecoverySecret() can write the file again afterwards', \Nino\Install\Install::setRecoverySecret( $appData, 'another dev password' ) === true );
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

	/*	The pictures a unit shows are image slots it declares ('imageSlots'),
		each seeded with a file it ships under 'files' - and none of its
		templates carries a literal <img> of the public images directory, which
		is a picture nobody can replace without editing code	*/
	if( str_starts_with( $pageEntry, '.' ) === false ) {

		$declared = $pageManifest['imageSlots'] ?? [];

		foreach( glob( $pageDir. '/templates/*.tpl' ) ?: [] as $pageFile ) {

			$pageSource = (string) file_get_contents( $pageFile );

			if( preg_match( '#<img\b[^>]*src="\[\[/nino/public\]\]/images/#i', $pageSource ) === 1 )
				$pageFailures[] = $pageEntry. ': '. basename( $pageFile ). ' has a literal <img> of the images directory';

			if( preg_match_all( '#\[image (/[^\s\]]+)#', $pageSource, $pageImages ) > 0 )
				foreach( $pageImages[1] as $pageImage )
					if( isset( $declared[$pageImage] ) === false )
						$pageFailures[] = $pageEntry. ': '. basename( $pageFile ). ' shows the slot '. $pageImage. ', which the manifest does not declare';
		}

		foreach( $declared as $slotUri => $slot ) {

			$slotFile = (string) ( $slot['filename'] ?? '' );

			if( preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)*$#', (string) $slotUri ) !== 1 )
				$pageFailures[] = $pageEntry. ': the slot '. $slotUri. ' is not a slot uri';
			if( \Nino\Features::localized( $slot['label'] ?? '', 'en_US' ) === '' || \Nino\Features::localized( $slot['label'] ?? '', 'de_DE' ) === '' )
				$pageFailures[] = $pageEntry. ': the slot '. $slotUri. ' has no label';
			if( (int) ( $slot['width'] ?? 0 ) < 1 || (int) ( $slot['height'] ?? 0 ) < 1 || (int) $slot['width'] * (int) $slot['height'] > \Nino\Images::MAX_SOURCE_PIXELS )
				$pageFailures[] = $pageEntry. ': the slot '. $slotUri. ' has a size no upload could fill';
			if( $slotFile === '' || is_file( $pageDir. '/images/'. $slotFile ) === false || in_array( 'images/'. $slotFile, $pageManifest['files'] ?? [], true ) === false && in_array( 'images', $pageManifest['files'] ?? [], true ) === false )
				$pageFailures[] = $pageEntry. ': the slot '. $slotUri. ' names a seed file the unit does not ship under \'files\'';
		}
	}

	/*	The body is what ties a route to a file on disk. A route naming a
		template no unit ships is the one failure this whole block exists for.
		Every route is checked, not only the page routes: the demo units
		deliberately register something Webpages::isPageRoute() does not
		recognise (so the Routes editor leaves them alone), and their
		templates still have to exist.	*/
	foreach( ( $pageManifest['routes'] ?? [] ) as $routeKey => $route ) {

		if( preg_match( '#\[template /templates/([a-z0-9._-]+)#i', (string) ( $route['body'] ?? '' ), $routeTemplate ) !== 1 )
			continue;

		// A locale-suffixed body ('page-x.[[/nino/http/response/locale]]')
		// matches whichever locales the unit ships, so the stem is what counts
		$stem 			= preg_replace( '/\.$/', '', $routeTemplate[1] );
		$candidates = glob( $pageDir. '/templates/'. $stem. '*.tpl' ) ?: [];

		if( $candidates === [] )
			$pageFailures[] = $pageEntry. ': '. $routeKey. ' renders '. $stem. ', which no template file matches';
	}
}

check( 'every page unit ships the templates its own routes render'. ( $pageFailures === [] ? '' : ' - '. implode( ' | ', $pageFailures ) ), $pageFailures === [] );
// The three a starter site is normally built from have to be among them, or
// the Routes step has nothing to offer on a fresh install
check( 'the page library offers the three a starter site is built from', count( array_intersect( [ 'home', 'contact', '404' ], $pageUnits ) ) === 3 );
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

/*	And the mailbox every mail this framework sends is addressed from.
	\Nino\Mail::_getSender() reads '[[/project/mail/address/owner]]' for the From header
	and the envelope sender of every one of them, and that fill used to ship
	with the Form module's own install unit - which the wizard offers rather
	than installs (see Install::units()). A project that did not pick that
	module had neither header, and a mail without a From goes out as the
	webserver user, which _getSender()'s own comment calls the most reliable
	way there is to land in a spam folder	*/
$baseGlobal = (array) ( include $baseUnit. '/text/global.php' );

check( 'the base unit ships the mailbox every mail is sent from', isset( $baseGlobal['[[/project/mail/address/owner]]'] ) === true );

/*	As a fill rather than an address: the normal case is the one the project
	already gave, so there is one answer in one place and changing it changes
	both. An operator who needs a different one overwrites this key and the
	company address stays what it is	*/
check( '...with the company address as its value', $baseGlobal['[[/project/mail/address/owner]]'] === '[[/project/company/contact/email]]' );
// The envelope sender beside it, empty: "the same as the owner address"
// until an operator whose host may not send for that address sets it
check( 'the base unit ships the envelope sender fill, empty, beside the owner address', array_key_exists( '[[/project/mail/address/envelope]]', $baseGlobal ) === true && $baseGlobal['[[/project/mail/address/envelope]]'] === '' );
check( '...which the same unit ships, or it would resolve to nothing', isset( $baseGlobal['[[/project/company/contact/email]]'] ) === true
	&& str_contains( (string) $baseGlobal['[[/project/company/contact/email]]'], '[[' ) === false );

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

/*	A font stack is a comma separated list of family names, and the quotes in
	it belong to the single name that needs them - "Segoe UI". A value quoted
	as a whole is one family whose name happens to contain commas: no system
	has it, and the generic keyword that should have caught the fall sits
	inside the string rather than after it, so the browser ends up on its own
	standard font instead of the sans the stack asked for. Measured in
	headless Chromium against the quoted value: a canvas set to it measured
	the same text at the same width as a family name invented for the test,
	and 8% narrower than the same stack unquoted.	*/
$fontStacks		= 0;
$quotedWhole	= [];
$withoutBackup	= [];

foreach( [ '/../_nino/Nino.css', '/../_admin/install/library/base/assets/theme.css' ] as $file )
	if( preg_match_all( '/--fontfamily-[a-z]+:\s*([^;]+);/', (string) file_get_contents( __DIR__. $file ), $found ) > 0 )
		foreach( $found[1] as $value ) {

			$stack = trim( $value );
			$fontStacks++;

			if( preg_match( '/^(?:\'[^\']*\'|"[^"]*")$/', $stack ) === 1 )
				$quotedWhole[] = $file. ': '. $stack;

			// The last entry is the one the browser is guaranteed to have, so
			// it has to reach it as a keyword and not as part of a name
			if( preg_match( '/,\s*(?:sans-serif|serif|monospace|system-ui|cursive|fantasy)$/', $stack ) !== 1 )
				$withoutBackup[] = $file. ': '. $stack;
		}

check( 'every font stack of both files is a list of names and not one quoted name'. ( $quotedWhole === [] ? '' : ' - '. implode( ', ', $quotedWhole ) ), $fontStacks > 0 && $quotedWhole === [] );
check( '...and every one of them ends in a bare generic family'. ( $withoutBackup === [] ? '' : ' - '. implode( ', ', $withoutBackup ) ), $withoutBackup === [] );

/*	An address a shipped template writes is the project's, and a site may sit
	in a subdirectory: a form that posts to "/.newsletter" posts beside a site
	at /shop. [[/nino/dir]] is what the library's own templates put in front
	of every address - page-contact.tpl's action, frame-header.tpl's links -
	and the demo catalogue page, assembled from the Templates feature's
	presets, was the one template that did not	*/
$rootAbsoluteTemplates = [];
$libraryTemplates = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__. '/../_admin/install/library', FilesystemIterator::SKIP_DOTS ) );
foreach( $libraryTemplates as $libraryTemplate )
	if( str_ends_with( $libraryTemplate->getFilename(), '.tpl' ) === true && preg_match( '/\b(?:action|href)="\//', (string) file_get_contents( $libraryTemplate->getPathname() ) ) === 1 )
		$rootAbsoluteTemplates[] = substr( $libraryTemplate->getPathname(), strlen( __DIR__. '/../_admin/install/library/' ) );
sort( $rootAbsoluteTemplates );
check( 'no template of the install library writes an address from the domain root - a site in a subdirectory posts and links within itself'. ( $rootAbsoluteTemplates === [] ? '' : ' - '. implode( ', ', $rootAbsoluteTemplates ) ), $rootAbsoluteTemplates === [] );

/*	The web manifest is copied into public/favicon/ as it is - json, not a
	template, so the check above never saw it, and its icons pointed from the
	domain root at the place the set lived before the public/ split. A src
	the browser resolves against the manifest's own address is right wherever
	the site sits. And every icon the base unit ships is one the page can
	reach: named by the header template or by the manifest - favicon.ico
	was neither, and a browser probes it at the root, not under public/	*/
$manifest = json_decode( (string) file_get_contents( $baseUnit. '/favicon/site.webmanifest' ), true );
$manifestIcons = array_map( static fn( array $icon ): string => (string) ( $icon['src'] ?? '' ), (array) ( $manifest['icons'] ?? [] ) );
$rootAbsoluteIcons = array_filter( $manifestIcons, static fn( string $src ): bool => $src === '' || preg_match( '#^(?:/|[a-z]+:)#i', $src ) === 1 );
check( "the base unit's web manifest names its icons relative to itself, not from the domain root". ( $rootAbsoluteIcons === [] ? '' : ' - '. implode( ', ', $rootAbsoluteIcons ) ), count( $manifestIcons ) >= 2 && $rootAbsoluteIcons === [] );
$headerTemplate = (string) file_get_contents( $baseUnit. '/templates/html-header.tpl' );
$unreachableIcons = [];
foreach( scandir( $baseUnit. '/favicon' ) as $iconFile )
	if( $iconFile !== '.' && $iconFile !== '..' && $iconFile !== 'site.webmanifest' && str_contains( $headerTemplate, '/favicon/'. $iconFile ) === false && in_array( $iconFile, $manifestIcons, true ) === false )
		$unreachableIcons[] = $iconFile;
check( 'every icon the base unit ships is named by its header template or its manifest'. ( $unreachableIcons === [] ? '' : ' - unreachable: '. implode( ', ', $unreachableIcons ) ), $unreachableIcons === [] );

/*	robots.txt is delivered content, and a Disallow line names a path a
	crawler could otherwise reach. Two of the three the base unit named,
	nothing could reach any more: /data/ moved under private/ with the split
	(Filesystem::PRIVATE_DIRS) and is not served at all, and /.cache/ is
	/public/.cache/ now (PUBLIC_DIRS, getPublicDir()) - so the file fenced two
	dead addresses. Not that the bundle cache wants fencing: the stylesheets
	and scripts in it are what a crawler renders the page with. Held as a rule
	rather than as the list: every path the file disallows begins with a
	directory that exists under the webroot after the split - a tool folder,
	the public half, the kernel - or is the root itself	*/
$robotsTemplate = (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/robots.tpl' );
preg_match_all( '/^Disallow:\s*(\S+)/m', $robotsTemplate, $disallowed );
$deadDisallows = array_filter( $disallowed[1], static fn( string $path ): bool => $path !== '/' && preg_match( '#^/(?:_admin|_nino|public)/#', $path ) !== 1 );
check( "every path the base unit's robots.txt disallows exists under the webroot after the split". ( $deadDisallows === [] ? '' : ' - '. implode( ', ', $deadDisallows ) ), $disallowed[1] !== [] && $deadDisallows === [] );

// The stylesheet and the markup it styles are one delivery: theme.css names
// .nino-frame-header and .nino-footer-nav, and nothing else writes either
// template into a project
foreach( [ 'frame-header.tpl', 'frame-footer.tpl' ] as $frame )
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
echo "A starter site leaves no text key missing\n";

// Every page of the wizard's presets, each at the Element-URI and Http-URI its manifest names, with
// the three modules the wizard always applies. The Missing text keys tile counts every [[/...]] a
// template names that no file has - a comment is no exception, and a placeholder in one is a row
// the Text panel cannot clear: it names a key the system writes, and there is no field for it
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$starterSetupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $appData, $starterSetupRequest );
$starterEntries = [];
foreach( [ 'home' => [ '/home', '/' ], 'contact' => [ '/contact', '/contact' ], 'services' => [ '/services', '/services' ], 'about-me' => [ '/about-me', '/about-me' ], '404' => [ '/404', '/404' ] ] as $starterKey => [ $starterUri, $starterHttpUri ] )
	$starterEntries[] = [ 'uri' => $starterUri, 'httpUri' => $starterHttpUri, 'libraryKey' => (string) $starterKey, 'navs' => [], 'text' => [ 'de_DE' => [ 'name' => 'Seite '. $starterKey ], 'en_US' => [ 'name' => 'Page '. $starterKey ] ] ];
$_POST['data'] = json_encode( [ 'webpages' => $starterEntries ] );
$starterRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Webpages::apiApply( $appData, $starterRequest );
check( 'the whole starter site applies', $starterRequest['/nino/http/response']['statusCode'] === 200 && count( $starterRequest['/nino/http/response']['body']['webpages'] ?? [] ) === 5 );
$starterMissing = ( new ReflectionMethod( \Nino\Modules\Text\Keys::class, '_scanMissing' ) )->invokeArgs( null, [ &$appData ] );
check( 'on a fresh install the Missing text keys tile reads 0 - no template names a key nobody writes'
	. ( $starterMissing === [] ? '' : ' - '. implode( ', ', array_column( $starterMissing, 'key' ) ) ), \Nino\Modules\Text\Keys::missingCount( $appData ) === 0 );

echo "\n";


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
	check( '...and forms/navigation/localepicker/legal stay excluded even once other project modules exist', array_intersect( [ 'forms', 'navigation', 'localepicker', 'legal' ], array_keys( $appLibraryBody['modules'] ) ) === [] );

	$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [ 'widget' ] ] );
	$appApplyRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Setup::apiApply( $appData, $appApplyRequest );
	$appApplyBody = $appApplyRequest['/nino/http/response']['body'] ?? [];

	check( 'applying it activates the class and its requirement, alongside the four always-on units', ( $appApplyBody['modules'] ?? null ) === [ 'forms', 'navigation', 'localepicker', 'legal', 'widget' ]
		&& in_array( '\\Acme\\Widget', $appData['/nino/modules'], true ) === true
		&& in_array( '\\Nino\\Modules\\Form', $appData['/nino/modules'], true ) === true );
	check( '...and copies its template out of the module directory', \Nino\Filesystem::fileExists( $appData, '/templates/page-widget.tpl' ) === true );

	// A requirement nobody answers to is skipped, not applied
	file_put_contents( $appDir. '/Acme/Widget/install/manifest.php', '<?php return '. var_export( [ 'label' => 'Widget', 'moduleClass' => '\\Acme\\Widget', 'requiresModules' => [ 'nonexistent' ] ], true ). ';' );
	$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [ 'widget' ] ] );
	$unknownRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Setup::apiApply( $appData, $unknownRequest );
	check( 'a requirement no unit answers to is left out of the applied set - only the always-on four plus what was actually picked remain', ( $unknownRequest['/nino/http/response']['body']['modules'] ?? null ) === [ 'forms', 'navigation', 'localepicker', 'legal', 'widget' ] );
}

echo "\n";


// --- Cleanup ---------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
