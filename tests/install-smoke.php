<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	install-smoke.php		Dependency-free smoke test for the setup wizard
 *												(_admin/install/Install.php). Runs against an isolated sandbox
 *												directory, never touches the real project data - in
 *												particular, it never rewrites the real _admin/Admin.php (see the
 *												Finish section below): Recovery::set() writes under the
 *												sandbox's private directory here, so this file is safe to run
 *												against a real checkout.
 *
 *	Usage: php tests/install-smoke.php
 */

// Before the kernel loads: the features the applications place land in a directory of this
// run's own, never in the checkout's features/
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-install-applications-'. uniqid() );
mkdir( NINO_FEATURES_DIR, 0755, true );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( NINO_FEATURES_DIR ) );

$appData = ninoSandbox( 'install' );
$sandbox = ninoSandboxDir( $appData );


$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData['/nino/dir']										= '';
// Only the native locale - see Setup::apiApply()'s docblock for why that
// matters (picking just the native locale must not silently keep some other
// locale "available" too)
$appData['/nino/locales/available']			= [ 'de_DE' ];
// The framework's menus, as AppData::init() merges them under every request
// the wizard answers - the sandbox only runs prepare()
$appData['/nino/html/navs']								= \Nino\AppData::DEFAULTS['/nino/html/navs'];
$appData['/nino/modules']								= [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf' ];

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
		'disabled@example.com' => [ 'pw' => '$2y$10$bdAzpYYC2Yyn3wyr.kcIf.gtjBwDKm1yNNX6oTpAoak15QHnCS2gm', 'status' => 0, 'sessions' => [], 'perms' => [ '/*' ] ],
		'changeme@domain.com' => [ 'pw' => '$2y$10$bdAzpYYC2Yyn3wyr.kcIf.gtjBwDKm1yNNX6oTpAoak15QHnCS2gm', 'status' => 0, 'sessions' => [], 'perms' => [ '/*' ] ],
	],
] );
$appData['/nino/auth/user'] = [
	'disabled@example.com' => [ 'pw' => '$2y$10$bdAzpYYC2Yyn3wyr.kcIf.gtjBwDKm1yNNX6oTpAoak15QHnCS2gm', 'status' => 0, 'sessions' => [], 'perms' => [ '/*' ] ],
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

[ $status, $body ] = ninoCall( $appData, [ \Nino\Install\Install::class, 'handlePost' ], [], 'checks/run' );
check( 'a known action dispatches and succeeds', $status === 200 );
check( 'checks/run responds with a php/extensions/directories shape', isset( $body['php'], $body['extensions'], $body['directories'] ) );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Install::class, 'handlePost' ], [], 'install/does-not-exist' );
check( 'an unknown action is rejected with 404', $status === 404 );

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

[ , $checksBody ] = ninoCall( $appData, [ \Nino\Install\Checks::class, 'apiRun' ] );

check( 'reports the running php version', $checksBody['php']['version'] === PHP_VERSION );
check( 'gd is reported as loaded (required by CI)', $checksBody['extensions']['gd']['ok'] === true );
check( 'dom is reported as loaded (required by CI)', $checksBody['extensions']['dom']['ok'] === true );
check( 'xmlwriter is reported as loaded (required by CI)', $checksBody['extensions']['xmlwriter']['ok'] === true );
check( 'every extension of the list carries a reason', array_filter( $checksBody['extensions'], fn( $e ) => !is_string( $e['reason'] ?? null ) || trim( $e['reason'] ) === '' ) === [] );
check( 'the project root is reported as writable', $checksBody['directories']['.']['ok'] === true );
check( 'the canonical private directory is reported as private', isset( $checksBody['directories']['private'] ) === true && isset( $checksBody['directories']['content'] ) === false );
check( 'the public root is reported too', ( $checksBody['directories']['public']['ok'] ?? null ) === true );

echo "\n";


// --- Setup::apiLibrary / apiApply -----------------------------------------

echo "Setup::apiLibrary / apiApply\n";

[ , $libraryBody ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiLibrary' ] );

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

[ $status ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [], 'modules' => [] ] );
check( 'rejects a selection with no locale at all', $status === 400 );

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
[ $status, $applyBody ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE' ], 'modules' => [] ] );

check( 'apply succeeds', $status === 200 );
// Every always-on unit, and nothing else since nothing was picked - and
// every key the constant names has a unit to apply, or apply() would drop
// it on the floor without a word
check( 'reports back every always-on unit and nothing else, and each of them has a unit', $applyBody['modules'] === \Nino\Install\Setup::ALWAYS_MODULES
	&& array_diff( \Nino\Install\Setup::ALWAYS_MODULES, array_keys( \Nino\Install\Setup::units() ) ) === [] );

$configAfterApply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'picking only the already-native de_DE does not pull in en_US too', $configAfterApply['/nino/locales/available'] === [ 'de_DE' ] );
// A language is named once the project has it, in its own tongue: the Localepicker unit used to write the names of both languages the library ships
// into every project, the one it had not chosen among them
$languageNames = function() use ( &$appData ): array {
	$names = array_filter( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ), static fn( string $key ): bool => str_starts_with( $key, '[[/_nino/locale/' ), ARRAY_FILTER_USE_KEY );
	ksort( $names );
	return $names;
};
check( 'the setup names the language the project has, in its own tongue, and no language it has not', $languageNames() === [ '[[/_nino/locale/de_DE/name]]' => \Nino\Locales::ENDONYMS['de_DE'] ] );
$pickerNameLines = [];
foreach( glob( \Nino\Install\Setup::units()['localepicker']. '/text/*.php' ) ?: [] as $file )
	foreach( array_keys( (array) include $file ) as $key )
		if( str_contains( (string) $key, '/_nino/locale/' ) === true )
			$pickerNameLines[] = basename( $file ). ' '. $key;
check( '...since no unit delivers it: the text of the Localepicker unit has no name of a language in it, and reaches every project the languages it has or not', $pickerNameLines === [] );
// The look is not a choice any more: the standard part of the feature Basis is the look, so this step seeds the
// bundle with Nino.css and the project's own stylesheet, which stays empty and last
check( 'the Setup step seeds the css bundle with the project\'s own stylesheet behind the one of the kernel', $configAfterApply['/nino/html/assets']['/.cache/style.css'] === [
	'/_nino/Nino.css', '/assets/style.css',
] );
check( '...and the file it names is really in the project, and no theme and no script of the kernel with it', is_file( $sandbox. '/private/assets/style.css' ) === true && is_file( $sandbox. '/private/assets/theme.css' ) === false
	&& is_file( $sandbox. '/private/templates/frame-header.tpl' ) === true && is_file( $sandbox. '/private/templates/frame-footer.tpl' ) === true
	&& ( $configAfterApply['/nino/html/assets']['/.cache/script.js'] ?? \Nino\AppData::DEFAULTS['/nino/html/assets']['/.cache/script.js'] ) === [ '/_nino/Nino.js' ] );
// No face is delivered: the page is set in the fonts of the visitor's system
check( '...and no font is delivered to the project', glob( $sandbox. '/public/fonts/*' ) === [] );
// The wizard switches the feature Basis on, which draws the starter pages: it is in the module list and its words are in the project
check( '...and the feature Basis is on: in the module list, in the record of the features, and its words are in the language of the project',
	in_array( '\\Nino\\Modules\\Basis', $configAfterApply['/nino/modules'], true ) === true && isset( $configAfterApply['/nino/features']['basis'] ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/module/basis/navigation/primary]]'] ?? '' ) !== ''
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/module/basis/navigation/menu]]'] ?? '' ) !== '' );
// A script in the project's assets is only ever loaded through a bundle - one
// that no bundle names is a file that looks like the site's own hook and runs
// nowhere
$bundled = array_merge( ...array_values( array_map( 'array_values', $configAfterApply['/nino/html/assets'] + \Nino\AppData::DEFAULTS['/nino/html/assets'] ) ) );
$unbundled = array_filter( glob( $sandbox. '/private/assets/*.js' ) ?: [], static fn( string $file ): bool => in_array( '/assets/'. basename( $file ), $bundled, true ) === false );
check( 'the Setup step copies no script into the project that no bundle loads', $unbundled === [] );
// The order config.php is written in is part of what a project's diff shows: the picture places keep their place
$configKeys = array_keys( $configAfterApply );
check( 'the Setup step writes the picture places of the units where it always did: behind the roles and in front of the menus', array_search( '/nino/html/images', $configKeys, true ) === array_search( '/nino/auth/roles', $configKeys, true ) + 1
	&& array_search( '/nino/html/navs', $configKeys, true ) === array_search( '/nino/html/images', $configKeys, true ) + 1 );
// The logo is two slots with nothing in them: the frames and the navigation show
// them with [logo], the mails and the page head ask the light one with [image /logo],
// and none ships a picture of its own
$logoSlot = $configAfterApply['/nino/html/images']['/logo'] ?? [];
$logoDarkSlot = $configAfterApply['/nino/html/images']['/logo/dark'] ?? [];
check( 'the base unit seeds the empty logo slot into config.php', ( $logoSlot['label'] ?? null ) === 'Logo' && ( $logoSlot['width'] ?? null ) === 500 && ( $logoSlot['height'] ?? null ) === 100
	&& array_key_exists( 'filename', $logoSlot ) === true && $logoSlot['filename'] === null && array_keys( $configAfterApply['/nino/html/images'] ) === [ '/logo', '/logo/dark' ] );
check( '...and beside it the empty slot of the picture for a dark ground, of the same shape, labelled "Logo, dark"', ( $logoDarkSlot['label'] ?? null ) === 'Logo, dark' && ( $logoDarkSlot['width'] ?? null ) === 500 && ( $logoDarkSlot['height'] ?? null ) === 100
	&& array_key_exists( 'filename', $logoDarkSlot ) === true && $logoDarkSlot['filename'] === null );
check( '...and delivers no logo picture, the unit has no images directory', glob( $sandbox. '/public/images/*' ) === [] && is_dir( __DIR__. '/../_admin/install/library/base/images' ) === false );

// Applying the step again only adds: the label, the size and the picture of a
// slot the project has stay as they are
$appData['/nino/html/images']['/logo'] = [ 'label' => 'Firmenlogo', 'width' => 300, 'height' => 100, 'filename' => 'logo.webp' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
[ $status ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE' ], 'modules' => [] ] );
$logoAfterReapply = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/logo'] ?? [];
check( 'applying the Setup step again keeps a logo slot the project changed', $status === 200 && ( $logoAfterReapply['label'] ?? null ) === 'Firmenlogo'
	&& ( $logoAfterReapply['width'] ?? null ) === 300 && ( $logoAfterReapply['filename'] ?? null ) === 'logo.webp' );
$appData['/nino/html/images']['/logo'] = $logoSlot;
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

check( 'core structural modules are always present', in_array( '\\Nino\\Modules\\Template', $configAfterApply['/nino/modules'], true ) === true );
check( 'the always-on Form module is present, with nothing picked', in_array( '\\Nino\\Modules\\Form', $configAfterApply['/nino/modules'], true ) === true );
check( 'the always-on Navigation module is present too', in_array( '\\Nino\\Modules\\Navigation', $configAfterApply['/nino/modules'], true ) === true );
check( 'and so is the always-on Localepicker module', in_array( '\\Nino\\Modules\\Localepicker', $configAfterApply['/nino/modules'], true ) === true );
check( 'the one developer tool that still ships as a module is active from the first config on', in_array( '\\Nino\\Modules\\Maintenance', $configAfterApply['/nino/modules'], true ) === true
	// Neither of the other two is among them any more: the template editor is
	// the Builder, a feature installed from the catalogue, and the look is no
	// longer a choice at all - the base unit delivers one theme.css, and the
	// Design feature that composes a look is a catalogue feature the Features
	// panel installs, not a module this config could list
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
check( 'copies the frames of the base unit, written in the components of Basis, and no template of "navigation" and "localepicker", whose menus the header and the footer of Basis draw',
	\Nino\Filesystem::fileExists( $appData, '/templates/frame-header.tpl' ) === true && \Nino\Filesystem::fileExists( $appData, '/templates/frame-footer.tpl' ) === true
	&& \Nino\Filesystem::fileExists( $appData, '/templates/html-header-nav.tpl' ) === false && \Nino\Filesystem::fileExists( $appData, '/templates/html-footer-nav.tpl' ) === false
	&& \Nino\Filesystem::fileExists( $appData, '/templates/html-footer-localepicker.tpl' ) === false );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'base\'s blacklist entries (design tokens) landed in text/blacklist.php', in_array( '/project/website/html/lang', $blacklistAfterApply, true ) === true );
check( 'the mail design tokens landed on the blacklist too - they are the base unit\'s now, not the contact form\'s', in_array( '/project/mail/color/primary', $blacklistAfterApply, true ) === true
	&& in_array( '/project/mail/spacing/large', $blacklistAfterApply, true ) === true );

$deAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'merges the picked locale\'s text fragments (base + forms)', ( $deAfterApply['[[/module/form/info/required]]'] ?? null ) !== null );
check( '...and "localepicker"\'s, always-on now too', ( $deAfterApply['[[/module/localepicker/menu/title]]'] ?? null ) === 'Wähle Deine Sprache' );
// The base site ships no cookie banner: consent is the Consent feature's job.
// The footer a project is set up with carries no banner block, and the
// wizard writes none of the four fills that block read
check( 'the installed footer carries no cookie banner', str_contains( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/html-footer.tpl', '' ), 'nino-cookie-banner' ) === false );
check( '...and the picked locale\'s text has no /cookiebanner/ key', array_filter( array_keys( $deAfterApply ), static fn( string $key ): bool => str_starts_with( $key, '[[/cookiebanner/' ) ) === [] );
// The menus a project starts with are the framework's (AppData::DEFAULTS),
// and the Legal unit asks for one of its own on top (its 'navs' key, see
// Setup::_applyNavs()) - so config.php holds both, the framework's first
$expectedNavs = array_merge( \Nino\AppData::DEFAULTS['/nino/html/navs'], [ 'legal' ] );
check( 'the setup step writes the framework\'s menus and the one the Legal unit asks for behind them', $configAfterApply['/nino/html/navs'] === $expectedNavs );
// A unit the wizard applies on every run states nothing that run never reads:
// a config default for a key AppData::DEFAULTS sets is never applied -
// applyUnit() fills a key only where $appData has none, and every request
// carries the defaults - and a label only names a choice in the picker, which
// leaves these units out
$deadInAlwaysUnits = [];
foreach( \Nino\Install\Setup::ALWAYS_MODULES as $unitKey ) {
	$unitManifest = \Nino\Features::readUnitManifest( \Nino\Install\Setup::units()[$unitKey] ) ?? [];
	foreach( array_keys( array_intersect_key( (array) ( $unitManifest['config'] ?? [] ), \Nino\AppData::DEFAULTS ) ) as $configKey )
		$deadInAlwaysUnits[] = $unitKey. ': config '. $configKey;
	if( isset( $unitManifest['label'] ) === true )
		$deadInAlwaysUnits[] = $unitKey. ': label';
}
check( 'the units the wizard always applies state no default the framework sets already, and no picker label', $deadInAlwaysUnits === [] );
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
check( '...and the footer of the base frames outputs it, with the component that draws the legal pages', str_contains( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/frame-footer.tpl', '' ), '[footer-legal]' ) === true );
check( 'the unit\'s words for the Elements panel are on the blacklist', in_array( '/_admin/elements/type/privacy/hint', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true
	&& in_array( '/_admin/elements/field/legal/text', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true );

check( 'never writes a fragment for a locale that was not picked', \Nino\Filesystem::fileExists( $appData, '/text/en_US.php' ) === false );

[ , $libraryAfterApplyBody ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiLibrary' ] );

check( 'apiLibrary now reports de_DE as the active locale', $libraryAfterApplyBody['activeLocales'] === [ 'de_DE' ] );

// A second run with a different locale selection replaces the first run's
// locales, exactly as before - but nothing about the always-on three can be
// "unpicked" any more, so this only ever exercises the locale replace now.
// The hand-written and runtime-only routes above still have to survive
[ $status ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'en_US' ], 'modules' => [] ] );
check( 'second apply succeeds', $status === 200 );

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
ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'en_US' ], 'modules' => [] ] );
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
[ $status, $body ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [], 'native' => 'de_DE' ] );
check( 'apply succeeds with a native locale posted explicitly', $status === 200 );
check( 'response echoes the newly picked native locale', $body['nativeLocale'] === 'de_DE' );
check( 'a posted native locale wins even though the previous native is still among the picked locales', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/locales/native'] === 'de_DE' );
check( 'a project that has both languages has both names, each in its own tongue', $languageNames() === [ '[[/_nino/locale/de_DE/name]]' => \Nino\Locales::ENDONYMS['de_DE'], '[[/_nino/locale/en_US/name]]' => \Nino\Locales::ENDONYMS['en_US'] ] );

// ...and the name is the project's from then on: an editor changed it, and the setup run again leaves it
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/locale/de_DE/name]]'] = 'Deutsch (Standard)';
	return $global;
} );
ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [], 'native' => 'de_DE' ] );
check( '...and applying the setup again leaves a name an editor gave a language', ( $languageNames()['[[/_nino/locale/de_DE/name]]'] ?? null ) === 'Deutsch (Standard)' );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/locale/de_DE/name]]'] = \Nino\Locales::ENDONYMS['de_DE'];
	return $global;
} );

// A posted native locale that isn't among this call's own picked locales
// is ignored - falls back to the same rule as posting none at all: keep
// the current native if it's still picked, else the first picked locale
ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'en_US' ], 'modules' => [], 'native' => 'de_DE' ] );
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
ninoWarnings();
[ $status, $body ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$blockedWarnings = ninoWarnings();
rmdir( $blockedTemplate );

check( 'a unit file the step cannot copy is a 500 naming the file', $status === 500
	&& str_contains( (string) ( $body['error'] ?? '' ), '/templates/frame-header.tpl' ) === true );
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


// --- the frame and the reset -------------------------------------------------

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

// The first stop of every page is a link to its main part: a keyboard that has gone through the header on every page should not have to again. The link is hidden
// until it has the focus (.nino-sr-only, which Nino.css shows then) and its target can be focused without being a stop of the Tab key
$documentHead = (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/html-header.tpl' );
check( 'the first element of the body of a page is a skip link to the main part, which the page names by an id and can focus: tabindex="-1", a target and no stop of the Tab key',
	preg_match( '/<body>\s*<a class="nino-sr-only" href="#nino-main">\[\[\/template\/common\/navigation\/skip\]\]<\/a>/', $documentHead ) === 1
	&& substr_count( $documentHead, 'id="nino-main"' ) === 1 && preg_match( '/<main id="nino-main" tabindex="-1">/', $documentHead ) === 1 );
check( '...and the frames of the base unit are written in the components of Basis alone: a header with its logo, its menu and its action, a footer with its columns and its legal line, and no class of Nino\'s own',
	preg_match( '/^\[header-wrap\b.*\[header-logo\].*\[header-nav menu="main"\].*\[header-actions\]/s', (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/frame-header.tpl' ) ) === 1
	&& preg_match( '/^\[footer-wrap\b.*\[footer-col\b.*\[footer-nav menu="footer"\].*\[footer-legal\]/s', (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/frame-footer.tpl' ) ) === 1
	&& str_contains( (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/frame-header.tpl' ). (string) file_get_contents( __DIR__. '/../_admin/install/library/base/templates/frame-footer.tpl' ), 'nino-' ) === false );
$contactPage = (string) file_get_contents( __DIR__. '/../_admin/install/library/pages/contact/templates/page-contact.tpl' );
check( 'the contact form says what its two fields are - autocomplete name and email - and leaves the saying of what is missing to the form\'s own words: the script checks the fields, in the language of the page, and a form without a script keeps the check of the browser, so the markup has no novalidate',
	preg_match( '/<input type="text" id="contact-form-name"[^>]* autocomplete="name"/', $contactPage ) === 1 && preg_match( '/<input type="email" id="contact-form-email"[^>]* autocomplete="email"/', $contactPage ) === 1
	&& preg_match( '/<form class="nino-form"[^>]*>/', $contactPage ) === 1 && str_contains( $contactPage, 'novalidate' ) === false );

// Nino.css is the reset and nothing else: its size, the tokens a part reads and no class of a component
$ninoCss = (string) file_get_contents( __DIR__. '/../_nino/Nino.css' );
check( 'Nino.css is the reset: under 15 KB, with the tokens of the brand and of the page and the contract of the grounds, and no rule of a component and no script beside it',
	strlen( $ninoCss ) < 15 * 1024 && str_contains( $ninoCss, '--brand-color-1:' ) === true && str_contains( $ninoCss, '--page-width:' ) === true && str_contains( $ninoCss, '[class$="--ground-dark"], [class*="--ground-dark "]' ) === true && str_contains( $ninoCss, '@supports not ( color: light-dark(' ) === true
	&& preg_match( '/\[class\*="--ground-[a-z]+"\]/', $ninoCss ) !== 1
	&& preg_match( '/\.nino-(?!sr-only|richtext)[a-z]/', $ninoCss ) !== 1 && is_file( __DIR__. '/../_nino/Nino.ui.js' ) === false );

// What the reset does for every page, each its own check: less motion, the focus ring, the width of a picture, the skip link, a hidden element, the room under a bar, the foot
$ninoRules = static function( string $selector ) use ( $ninoCss ): string {
	preg_match_all( '/(?:^|[}\s])'. preg_quote( $selector, '/' ). '\s*\{([^{}]*)\}/m', $ninoCss, $found );
	return implode( ' ', $found[1] );
};
check( 'a visitor who asked for less motion gets none: the block of the media query sets the motion token to nothing and the scroll to a jump, with no !important',
	preg_match( '/@media \(prefers-reduced-motion: reduce\) \{[^{}]*:root \{ --page-motion: 0s; \}[^{}]*html \{ scroll-behavior: auto; \}/', $ninoCss ) === 1 );
check( 'every control that has the focus has a ring of the accent, offset from the edge', preg_match( '/outline:\s*2px solid var\(--brand-accent\)/', $ninoRules( ':focus-visible' ) ) === 1 && preg_match( '/outline-offset:\s*2px/', $ninoRules( ':focus-visible' ) ) === 1 );
check( 'a picture is never wider than the box it stands in', preg_match( '/img, picture, svg, video, canvas, iframe \{[^}]*max-width:\s*100%/', $ninoCss ) === 1 && preg_match( '/img, video \{[^}]*height:\s*auto/', $ninoCss ) === 1 );
check( 'the skip link is shown when the keyboard reaches it: hidden only while it has no focus, and fixed, above the page and with a ground of its own where it has',
	str_contains( $ninoCss, '.nino-sr-only:not(:focus):not(:focus-within)' ) === true && preg_match( '/a\.nino-sr-only:focus \{[^}]*position:\s*fixed[^}]*z-index:\s*1000[^}]*background:\s*var\(--brand-ground\)/', $ninoCss ) === 1 );
check( 'an element that is hidden is hidden, whatever display a rule gives its tag, but for one that is found by the page search: no !important',
	str_contains( $ninoCss, '[hidden]:not( [hidden="until-found"] ) { display: none; }' ) === true && strpos( $ninoCss, '[hidden]:not(' ) > strpos( $ninoCss, 'img, picture, svg' ) );
check( 'the room under a bar that stands at the top is one token, which html keeps a focused element from', preg_match( '/--page-scroll-pad:\s*5rem/', $ninoCss ) === 1 && str_contains( $ninoCss, 'scroll-padding-top: var(--page-scroll-pad);' ) === true );
check( 'the foot of a short page stands at the bottom: body is a column of at least the height of the window, and main takes the rest',
	preg_match( '/\n\tbody \{\n.*?min-height:\s*100dvh;.*?display:\s*flex;\s*flex-direction:\s*column;/s', $ninoCss ) === 1 && preg_match( '/main \{ flex: 1 0 auto;/', $ninoCss ) === 1 );

echo "\n";


// --- StarterPages::apply ----------------------------------------------------

echo "StarterPages::apply\n";

// Back to a clean, known Setup state - de_DE + en_US, no content modules
ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );

$pagesLibrary = __DIR__. '/../_admin/install/library/pages';
$pageRoutes = static function( array $config ): array {
	return array_filter( $config['/nino/http/routes'] ?? [], static fn( mixed $route, string $key ): bool => str_starts_with( $key, 'GET://' ) === true && is_array( $route ) === true
		&& preg_match( '~^\[template /templates/page-~', (string) ( $route['body'] ?? '' ) ) === 1, ARRAY_FILTER_USE_BOTH );
};

check( 'the wizard has no step for pages: no action of it names them', array_filter( array_keys( array_merge( ...array_map( static fn( string $class ): array => $class::actions(), [
	\Nino\Install\Checks::class, \Nino\Install\Setup::class, \Nino\Install\PersonalInfos::class, \Nino\Install\Accounts::class, \Nino\Install\Applications::class, \Nino\Install\Finish::class,
] ) ) ), static fn( string $action ): bool => str_contains( $action, 'webpage' ) === true ) === [] );

$presetKeys = [];
foreach( array_diff( scandir( $pagesLibrary ) ?: [], [ '.', '..' ] ) as $entry ) {
	$unitManifest = ( @include $pagesLibrary. '/'. $entry. '/manifest.php' ) ?: [];
	if( isset( $unitManifest['preset'] ) === true )
		$presetKeys[$entry] = (int) $unitManifest['preset'];
}
asort( $presetKeys );
$presetKeys = array_map( 'strval', array_keys( $presetKeys ) );
check( 'the units that claim a position are Start, Contact and 404, in that order', $presetKeys === [ 'home', 'contact', '404' ] );

check( 'before anything is created the project has no page route', $pageRoutes( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === [] );

/*	A copy the starter pages cannot make is their failure, not a success over a
	route that renders a file that is not there: here a directory stands where
	contact's template has to go, so the write fails the way a permission or a
	full disk would	*/
mkdir( $sandbox. '/private/templates/page-contact.tpl', 0755, true );
$failed = \Nino\Install\StarterPages::apply( $appData );
check( 'a template that cannot be written fails the call and names the file', is_string( $failed ) === true && str_contains( $failed, '/templates/page-contact.tpl' ) === true );
check( '...before any route is persisted', $pageRoutes( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === [] );
rmdir( $sandbox. '/private/templates/page-contact.tpl' );

/*	The home unit's hero is an image slot seeded with its shipped placeholder.
	A picture that cannot be put in place fails the call by name, the way the
	template above did - and no slot is seeded for a file that is not there	*/
$seedFile = $sandbox. '/public/images/template/page-home/hero/background.svg';
if( is_file( $seedFile ) === true )
	unlink( $seedFile );
mkdir( $seedFile, 0755, true );
$failed = \Nino\Install\StarterPages::apply( $appData );
check( 'a seed file that cannot be copied fails the call and names the file', is_string( $failed ) === true && str_contains( $failed, '/images/template/page-home/hero/background.svg' ) === true );
check( '...and no page image slot is seeded', array_keys( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [] ) === [ '/logo', '/logo/dark' ] );
rmdir( $seedFile );

check( 'where nothing is in the way the call succeeds', \Nino\Install\StarterPages::apply( $appData ) === true );

$configAfterStarter = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$starterRoutes = $pageRoutes( $configAfterStarter );

check( 'Start, Contact and 404 are routed at the address their unit suggests, in the order of their positions', array_keys( $starterRoutes ) === [ 'GET://', 'GET://contact', 'GET://404' ] );
check( '...each route\'s own "uri" is the Element-URI "/<folder>", not the address', array_column( $starterRoutes, 'uri' ) === [ '/home', '/contact', '/404' ] );
check( '...the 404 carries its status code, the others none', ( $starterRoutes['GET://404']['statusCode'] ?? null ) === 404 && isset( $starterRoutes['GET://']['statusCode'] ) === false && isset( $starterRoutes['GET://contact']['statusCode'] ) === false );
check( '...Start and Contact are in the menus their unit proposes at their own position in the list - main and footer, the legal menu is not one of them - and 404 in none',
	( $starterRoutes['GET://']['navs'] ?? null ) === [ 'main' => 1, 'footer' => 1 ] && ( $starterRoutes['GET://contact']['navs'] ?? null ) === [ 'main' => 2, 'footer' => 2 ] && isset( $starterRoutes['GET://404']['navs'] ) === false );
check( 'a hand-written route outside the library survives', ( $configAfterStarter['/nino/http/routes']['GET://custom']['body'] ?? null ) === 'hand-written route' );
check( 'the contact page\'s module is the one the wizard always applies', in_array( '\\Nino\\Modules\\Form', $configAfterStarter['/nino/modules'], true ) === true );

foreach( [ 'page-home.tpl', 'page-contact.tpl', 'page-404.tpl' ] as $starterTemplate )
	check( 'copies '. $starterTemplate. ' into the project', \Nino\Filesystem::fileExists( $appData, '/templates/'. $starterTemplate ) === true );
check( 'copies a page unit\'s declared files into the project', is_file( $sandbox. '/public/images/template/page-home/hero/background.svg' ) === true );
check( 'the one unit that claims no position puts its template in place, the empty page the Routes panel starts a new route on, and no route of its own',
	\Nino\Filesystem::fileExists( $appData, '/templates/page-blank.tpl' ) === true && array_filter( $starterRoutes, static fn( array $route ): bool => str_contains( (string) $route['body'], 'page-blank' ) ) === [] );

// The hero is the slot the page template names, and nothing is literal in it
$heroSlot = $configAfterStarter['/nino/html/images']['/template/page-home/hero/background'] ?? [];
// The native locale of this run is en_US (the steps above moved it), so the label is that one
check( 'seeds the unit\'s image slot into config.php: the native-locale label, 1920x1080 and the shipped placeholder', ( $heroSlot['label'] ?? null ) === 'Home – hero image'
	&& ( $heroSlot['width'] ?? null ) === 1920 && ( $heroSlot['height'] ?? null ) === 1080 && ( $heroSlot['filename'] ?? null ) === 'template/page-home/hero/background.svg'
	&& is_file( $sandbox. '/public/images/'. ( $heroSlot['filename'] ?? 'missing' ) ) === true );

check( 'the seeded placeholder carries the slot\'s own name, so an upload replaces it and Remove image deletes it, as for any file the slot wrote', str_starts_with( (string) ( $heroSlot['filename'] ?? '' ), 'template/page-home/hero/background.' ) === true
	&& is_file( __DIR__. '/../_admin/install/library/pages/home/images/demo.jpg' ) === false );
// The request as \Nino\init() boots it: the feature Basis the wizard switched on is in the list of the modules, and its components are read from its folders
\Nino\Features::registerComponents( $appData );
\Nino\Modules\Template::init( $appData );
$homeHtml = \Nino\Html::renderHtml( $appData, '[template /templates/page-home]' );
check( 'the home template renders the slot as the picture behind its hero: an <img> with its size and an empty alt inside the section, and no literal image path', preg_match( '#<section id="hero"[^>]*>.*?<img [^>]*src="[^"]*/images/template/page-home/hero/background\.svg"[^>]*width="1920" height="1080" alt=""#s', $homeHtml ) === 1
	&& str_contains( $homeHtml, '[[/nino/public]]/images/' ) === false && str_contains( $homeHtml, '[section-wrap' ) === false );

// The logo, which is a slot too: nothing in the shipped frames, the navigation
// or the mail names a logo file, and with no logo uploaded none of them
// leaves a broken <img> or an empty meta tag behind
\Nino\Html::init( $appData );
$logoFrames = static function( array &$appData ): array {
	return [
		'header'	=> \Nino\Html::renderHtml( $appData, '[template /templates/frame-header]' ),
		'meta'		=> \Nino\Html::renderHtml( $appData, '[template /templates/html-header]' ),
		'mail'		=> \Nino\Html::renderHtml( $appData, '[template /templates/mail-header]' ),
	];
};
$logoShipped = '';
foreach( [ '/templates/frame-header.tpl', '/templates/frame-footer.tpl', '/templates/html-header.tpl', '/templates/mail-header.tpl' ] as $logoTemplate )
	$logoShipped .= (string) \Nino\Filesystem::getFileContent( $appData, $logoTemplate, '' );
check( 'no shipped template names a logo file: every one asks the logo slots - the header as [header-logo], which takes both, the page head and the mail as the light slot alone',
	preg_match( '#images/logo#', $logoShipped ) === 0 && substr_count( $logoShipped, '[header-logo' ) === 1 && substr_count( $logoShipped, '[footer-logo /logo]' ) === 1 && substr_count( $logoShipped, '[imagevalues /logo' ) === 3
	&& substr_count( (string) \Nino\Filesystem::getFileContent( $appData, '/templates/mail-header.tpl', '' ), '[imagevalues /logo]' ) === 1
	&& str_contains( $logoShipped, '/logo/dark' ) === false );

\Nino\Html::addFills( $appData, [ '[[/project/website/general/url]]' => 'www.example.com', '[[/project/company/general/name]]' => 'Acme' ], '*' );
$noLogo = $logoFrames( $appData );
check( 'without an uploaded logo the header has no <img> and the mail header no picture at all', str_contains( $noLogo['header'], '<img' ) === false && str_contains( $noLogo['header'], '[header-logo' ) === false
	&& str_contains( $noLogo['mail'], '<img' ) === false && str_contains( $noLogo['mail'], '[image' ) === false );
check( '...and the page head has no og:image or twitter:image, not even an empty one', str_contains( $noLogo['meta'], 'og:image' ) === false && str_contains( $noLogo['meta'], 'twitter:image' ) === false && str_contains( $noLogo['meta'], '[image' ) === false );

$appData['/nino/html/images']['/logo']['filename'] = 'logo.webp';
$withLogo = $logoFrames( $appData );
$logoUrl = \Nino\Images::getUrl( $appData, 'logo.webp' );
check( 'with a logo the header shows the one picture, with its size and an empty alt, and no picture for the dark ground',
	preg_match( '#<img [^>]*src="'. preg_quote( $logoUrl, '#' ). '"[^>]*width="500" height="100" alt=""#', $withLogo['header'] ) === 1 && substr_count( $withLogo['header'], '<img' ) === 1 );
$appData['/nino/html/images']['/logo/dark']['filename'] = 'logo-dark.webp';
$darkUrl = \Nino\Images::getUrl( $appData, 'logo-dark.webp' );
$withBoth = $logoFrames( $appData );
check( '...with the picture for a dark ground as well both are in the header, the light one first',
	substr_count( $withBoth['header'], '<img' ) === 2 && strpos( $withBoth['header'], $logoUrl ) !== false && strpos( $withBoth['header'], $darkUrl ) > strpos( $withBoth['header'], $logoUrl ) );
// A name with a quote or a bracket in it breaks nothing: the pictures are whole and no part of a call is left standing as text
\Nino\Html::addFills( $appData, [ '[[/project/company/general/name]]' => 'O\'Brien\'s "Studio" [Nord] GmbH' ], '*' );
$oddName = $logoFrames( $appData );
\Nino\Html::addFills( $appData, [ '[[/project/company/general/name]]' => 'Acme' ], '*' );
check( '...a company name with a quote or a bracket in it leaves the pictures whole and no part of a call standing as text',
	substr_count( $oddName['header'], '<img' ) === 2 && str_contains( $oddName['header'], '[header-logo' ) === false && str_contains( $oddName['header'], 'GmbH"]' ) === false );
check( '...the page head and the mail ask the light slot alone: the dark one is in neither',
	str_contains( $withBoth['meta'], 'https://www.example.com'. $darkUrl ) === false && str_contains( $withBoth['mail'], $darkUrl ) === false && str_contains( $withBoth['meta'], '<meta property="og:image" content="https://www.example.com'. $logoUrl. '">' ) === true
	&& str_contains( $withBoth['mail'], '<img src="https://www.example.com'. $logoUrl. '" width="180" alt="Acme">' ) === true );
$appData['/nino/html/images']['/logo']['filename'] = null;
$onlyDark = $logoFrames( $appData );
check( '...a project that uploaded the dark picture alone has it in the header, and the mail and the page head have none',
	str_contains( $onlyDark['header'], $darkUrl ) === true && str_contains( $onlyDark['header'], $logoUrl ) === false && substr_count( $onlyDark['header'], '<img' ) === 1
	&& str_contains( $onlyDark['mail'], '<img' ) === false && str_contains( $onlyDark['meta'], 'og:image' ) === false );
$appData['/nino/html/images']['/logo/dark']['filename'] = null;
$appData['/nino/html/images']['/logo']['filename'] = 'logo.webp';
check( '...og:image and twitter:image carry its absolute address', str_contains( $withLogo['meta'], '<meta property="og:image" content="https://www.example.com'. $logoUrl. '">' ) === true
	&& str_contains( $withLogo['meta'], '<meta name="twitter:image" content="https://www.example.com'. $logoUrl. '">' ) === true );
check( '...and the mail an <img> with the absolute address and the company name as its alt', preg_match( '#<img src="https://www\.example\.com'. preg_quote( $logoUrl, '#' ). '" width="180" alt="Acme">#', $withLogo['mail'] ) === 1 );
$appData['/nino/html/images']['/logo']['filename'] = null;

// So the Image Slots tab's scan has nothing to propose for it: the copied template is no literal <img> of the images directory
check( 'the copied home template names the slot and has no literal <img> of the images directory for the template scan to find', str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ), 'image="~background"' ) === true
	&& preg_match( '#<img\b[^>]*src="[^"]*/images/#i', (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ) ) === 0 );
// A fresh install is the thing the dashboard's "Missing image slots" tile counts: with the logo and the hero both slots, no template has an <img> of the images directory left
check( 'on a fresh install the Missing image slots tile reads 0 - every picture of the starter site is a slot', \Nino\Modules\Images\Slots::missingCount( $appData ) === 0 );

$deAfterStarter = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enAfterStarter = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );

check( 'writes each page\'s details in every language, from what its unit suggests and under its Element-URI - not under the folder name of an earlier layout',
	$deAfterStarter['[[/_nino/webpage/home/name]]'] === 'Startseite' && $enAfterStarter['[[/_nino/webpage/home/name]]'] === 'Home' && $enAfterStarter['[[/_nino/webpage/home/title]]'] === 'Welcome.'
	&& ( $deAfterStarter['[[/_nino/webpage/contact/name]]'] ?? '' ) !== '' && ( $enAfterStarter['[[/_nino/webpage/404/title]]'] ?? '' ) !== '' );
check( '...none of them the generic placeholder', in_array( 'Page', [ $deAfterStarter['[[/_nino/webpage/404/name]]'], $enAfterStarter['[[/_nino/webpage/404/name]]'] ], true ) === false );
// The system's own form is a key of the system's: the only /_nino keys the
// texts of a project hold are the ones written after an Element-URI or a
// language code. A unit that delivered another would be a unit writing what
// is not its to write (tests/keys-smoke.php checks the library for it)
$systemKeys = array_filter( array_merge( array_keys( $deAfterStarter ), array_keys( $enAfterStarter ), array_keys( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) ) ), static fn( string $key ): bool => str_starts_with( $key, '[[/_nino/' ) );
check( 'the only /_nino keys of the project texts are the pages\' details (the two legal pages among them) and the languages\' names', $systemKeys !== []
	&& array_filter( $systemKeys, static fn( string $key ): bool => preg_match( '#^\[\[/_nino/(webpage/(home|contact|404|legal\/(imprint|privacy))/(name|title|description|uri)|locale/(de_DE|en_US)/name)\]\]$#', $key ) !== 1 ) === [] );
$globalAfterStarter = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklistAfterStarter = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'writes every page\'s reachable address as one global fill, so a template can link to it by name', ( $globalAfterStarter['[[/_nino/webpage/home/uri]]'] ?? null ) === '/'
	&& ( $globalAfterStarter['[[/_nino/webpage/contact/uri]]'] ?? null ) === '/contact' && ( $globalAfterStarter['[[/_nino/webpage/404/uri]]'] ?? null ) === '/404'
	&& isset( $deAfterStarter['[[/_nino/webpage/home/uri]]'], $enAfterStarter['[[/_nino/webpage/home/uri]]'] ) === false );
check( 'a page address is a technical value, blacklisted out of the Text panel like every other route key', in_array( '/_nino/webpage/home/uri', $blacklistAfterStarter, true )
	&& in_array( '/_nino/webpage/contact/uri', $blacklistAfterStarter, true ) && count( array_unique( $blacklistAfterStarter ) ) === count( $blacklistAfterStarter ) );
check( 'a template\'s own deeper content (unprefixed, shared across instances) merges in', isset( $deAfterStarter['[[/template/page-home/hero/title]]'] ) === true );

/*	A second call changes nothing: the project has pages now, and what an editor
	did to them since is not the wizard's to put back	*/
$appData['/nino/html/images']['/template/page-home/hero/background']['label'] = 'Mein Titelbild';
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );
$beforeAgain = (string) file_get_contents( $sandbox. '/private/config.php' );
check( 'a project that has a page route gets no starter page: the call changes nothing, an editor\'s label stays', \Nino\Install\StarterPages::apply( $appData ) === true
	&& (string) file_get_contents( $sandbox. '/private/config.php' ) === $beforeAgain && \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/template/page-home/hero/background']['label'] === 'Mein Titelbild' );

// The empty page is put in place whatever the project has, and never over a file that is there
file_put_contents( $sandbox. '/private/templates/page-blank.tpl', 'the editors\' own' );
check( 'a template of the project\'s own at the name of the empty page is left as it is', \Nino\Install\StarterPages::apply( $appData ) === true && file_get_contents( $sandbox. '/private/templates/page-blank.tpl' ) === 'the editors\' own' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-blank.tpl', (string) file_get_contents( $pagesLibrary. '/blank/templates/page-blank.tpl' ) );

/*	One page of the project's own is enough to keep the starter pages away:
	a project of its own, so that the state above stays as it is	*/
$owned = ninoSandbox( 'install-owned' );
$ownedDir = ninoSandboxDir( $owned );
$owned['/nino/dir'] = '';
foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $ownedSub )
	mkdir( $ownedDir. $ownedSub, 0777, true );
\Nino\Filesystem::putFileContent( $owned, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE' ], '/nino/http/routes' => [ 'GET://about' => [ 'uri' => '/about', 'body' => '[template /templates/page-about]' ] ] ] );
$owned['/nino/locales/available'] = [ 'de_DE' ];
// The menus are registered, the module that draws them is not on
$owned['/nino/html/navs'] = \Nino\AppData::DEFAULTS['/nino/html/navs'];
check( 'one page route of the project\'s own keeps the starter pages away: nothing is written', \Nino\Install\StarterPages::apply( $owned ) === true
	&& array_keys( \Nino\Filesystem::getFileContent( $owned, '/config.php', [] )['/nino/http/routes'] ) === [ 'GET://about' ] && \Nino\Filesystem::fileExists( $owned, '/templates/page-home.tpl' ) === false );

// A route that is no page - the sitemap's, a technical one - is not a page of the project
\Nino\Filesystem::putFileContent( $owned, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE' ], '/nino/http/routes' => [ 'GET://sitemap.xml' => [ 'uri' => '/sitemap.xml', 'body' => '[sitemap]' ], 'POST://.form' => [ 'uri' => '/.form', 'body' => '[template /templates/page-form]' ] ] ] );
unset( $owned['./nino/filesystem/cache'] );
check( '...while routes that are no page do not - a route of another method that renders a page template among them', \Nino\Install\StarterPages::apply( $owned ) === true
	&& array_keys( $pageRoutes( \Nino\Filesystem::getFileContent( $owned, '/config.php', [] ) ) ) === [ 'GET://', 'GET://contact', 'GET://404' ] );
// No Navigation module on: the pages are in no menu, and the languages the project has are the ones written
check( '...and without the Navigation module the pages are in no menu, in the one language the project has',
	array_filter( \Nino\Filesystem::getFileContent( $owned, '/config.php', [] )['/nino/http/routes'], static fn( array $route ): bool => isset( $route['navs'] ) === true ) === []
	&& isset( \Nino\Filesystem::getFileContent( $owned, '/text/en_US.php', [] )['[[/_nino/webpage/home/name]]'] ) === false
	&& ( \Nino\Filesystem::getFileContent( $owned, '/text/de_DE.php', [] )['[[/_nino/webpage/home/name]]'] ?? '' ) === 'Startseite' );
\Nino\Filesystem::removeDir( $ownedDir );


// Neither the Routes panel nor the starter pages keep a list of their own: both
// derive one from /nino/http/routes plus the /_nino/webpage<uri>/* keys in the text
// files. That only works if the route really carries everything the panel needs to
// reopen a page - its Element-URI, its body and its status code. Without that,
// every page the wizard made is unopenable in /_admin, and saving the 404 page
// there quietly turns it into a 200.
\Nino\Auth::logoutUser( $appData );
ninoUser( $appData, 'dev@example.com' );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Routes\Admin::class, 'apiList' ] );
$adminPages = $body['pages'];
$adminTemplates = $body['templates'];

check( 'the Routes panel lists the starter pages as its own, each on the template its route renders', array_column( $adminPages, 'uri' ) === [ '/home', '/contact', '/404' ]
	&& array_column( $adminPages, 'template' ) === [ 'page-home', 'page-contact', 'page-404' ] && array_column( $adminPages, 'statusCode' ) === [ 200, 200, 404 ] );
check( '...every one on a template the panel offers', array_filter( $adminPages, static fn( array $entry ): bool => in_array( $entry['template'], $adminTemplates, true ) === false ) === [] );
check( '...and with the empty page to start a new route on, which is the one the Routes panel proposes', ( $body['defaultTemplate'] ?? null ) === 'page-blank' && in_array( 'page-blank', $adminTemplates, true ) === true );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Routes\Admin::class, 'apiSave' ], [
	'originalUri' => '/404', 'uri' => '/404', 'httpUris' => [ '' => '/404' ],
	'template' => $adminPages[2]['template'], 'navs' => [],
	'statusCode' => $adminPages[2]['statusCode'], 'text' => $adminPages[2]['text'],
] );
check( 'saving a starter page from /_admin succeeds', $status === 200 );
check( '...and does not quietly reset its 404 to a 200', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://404']['statusCode'] ?? 200 ) === 404 );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Routes\Admin::class, 'apiSave' ], [
	'originalUri' => '', 'uri' => '/dev-made', 'httpUris' => [ '' => '/dev-made' ],
	'template' => 'page-home', 'navs' => [], 'statusCode' => 201,
	'text' => array_fill_keys( \Nino\Locales::getAvailableLocales( $appData ), [ 'name' => 'Dev made', 'title' => 'Dev made' ] ),
] );
check( 'creating a page in /_admin succeeds, beside the starter pages', $status === 200 && isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://dev-made'] ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://dev-made']['statusCode'] ?? 200 ) === 201 );

// Saving, deleting and moving a route all read the routes, decide against
// what they find and write the whole key back - so two editors saving two
// different pages at the same moment each wrote their own full copy, and the
// second one dropped the first one's page. writeContentData() locks for its
// own write, which is after the decision; these hold the lock across both
$routesSource = (string) file_get_contents( __DIR__. '/../_admin/Nino/Modules/Routes/Admin/Admin.php' );
$pagesSource = (string) file_get_contents( __DIR__. '/../_nino/Nino/Pages/Pages.php' );
$unlocked = [];

foreach( [ 'apiSave', 'apiDelete', 'apiMove' ] as $action ) {

	// The save is the function of the kernel the panel calls: the lock is taken in put(), round the call that reads and writes
	$from = $action === 'apiSave' ? $pagesSource : $routesSource;
	$body = substr( $from, strpos( $from, 'public static function '. ( $action === 'apiSave' ? 'put' : $action ). '(' ) ?: 0 );
	$body = substr( $body, 0, strpos( $body, "\n\t\t}" ) ?: strlen( $body ) );

	// The read is the whole file or one key of it, AppData::stored()
	$reads		= array_filter( [ strpos( $body, "getFileContent( \$appData, '/config.php'" ), strpos( $body, 'AppData::stored( $appData' ), strpos( $body, 'self::_put(' ) ], static fn( $at ): bool => $at !== false );
	$lockAt		= strpos( $body, "lockFile( \$appData, '/config.php' )" );
	$readAt		= $reads === [] ? false : min( $reads );
	$unlockAt	= strpos( $body, "unlockFile( \$appData, '/config.php' )" );

	if( $lockAt === false || $readAt === false || $unlockAt === false || $lockAt > $readAt || $unlockAt < $readAt || str_contains( $body, '} finally {' ) === false )
		$unlocked[] = $action;
}

check( 'every route write decides and writes under one lock'. ( $unlocked === [] ? '' : ' - unlocked: '. implode( ', ', $unlocked ) ), $unlocked === [] );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, 'dev@example.com' );

// One step earlier, re-applying with nothing posted still leaves navigation,
// the locale picker and the contact form active - there is no "opening
// position" to decline any more, nor a choice that could outlive it
ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );

$configAfterDecline = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( '...and the three always-on modules are still there, unaffected by posting nothing',
	in_array( '\\Nino\\Modules\\Navigation', $configAfterDecline['/nino/modules'], true ) === true
	&& in_array( '\\Nino\\Modules\\Form', $configAfterDecline['/nino/modules'], true ) === true
	&& in_array( '\\Nino\\Modules\\Localepicker', $configAfterDecline['/nino/modules'], true ) === true );

// A config.php that holds the routes and the bundles as null - a hand edit - is a project that has none, as the readers
// took it before they read through AppData::stored(): the apply goes through and writes
// both again, the kernel's script bundle with them. A project of its own, so that the state above stays as it is
$nulled = ninoSandbox( 'install-null' );
$nulledDir = ninoSandboxDir( $nulled );
$nulled['/nino/dir'] = '';
foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $nulledSub )
	mkdir( $nulledDir. $nulledSub, 0777, true );
\Nino\Filesystem::putFileContent( $nulled, '/config.php', [ '/nino/http/routes' => null, '/nino/html/assets' => null, '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE' ] ] );
[ $nullApplyStatus ] = ninoCall( $nulled, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE' ], 'modules' => [] ] );
$nullConfig = \Nino\Filesystem::getFileContent( $nulled, '/config.php', [] );
check( 'a config.php that holds the routes and the bundles as null is a project that has none: the apply writes both again, the script bundle with them', $nullApplyStatus === 200
	&& is_array( $nullConfig['/nino/http/routes'] ?? null ) === true && isset( $nullConfig['/nino/html/assets']['/.cache/script.js'] ) === true );
\Nino\Filesystem::removeDir( $nulledDir );

pinSetupLeavesTheStylesheetsOfFeaturesOut();

echo "\n";


// --- PersonalInfos::apiList / apiSaveBatch ---------------------------------

echo "PersonalInfos::apiList / apiSaveBatch\n";

// Deliberately overwrites whatever the starter pages section left behind above -
// this section is independently scoped, only real
// _admin/install/library/base key *names* matter here (apiList() filters
// against the real library on disk, not anything sandboxed), plus a
// couple of made-up, clearly-out-of-scope keys to prove both "not
// /company or /website" and "webpage meta, even though it looks similar"
// content stays out of this step. Locales/available is reset too, back to
// both - an earlier section may end on a narrowed list
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
	return $config;
} );
$appData['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/project/company/general/name]]' => 'Acme Inc', '[[/project/company/contact/address]]' => 'Street 1', '[[/project/website/general/author]]' => 'Acme Inc', '[[/project/website/general/url]]' => 'www.acme.test', '[[/project/website/general/host]]' => 'Acme Hosting', '[[/project/company/social/instagram]]' => 'https://www.instagram.com/acme' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/project/company/contact/country]]' => 'Deutschland', '[[/project/website/html/lang]]' => 'de', '[[/template/page-home/welcome/headline]]' => 'Willkommen', '[[/_nino/webpage/kontakt/name]]' => 'Kontakt' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/project/company/contact/country]]' => 'Germany', '[[/project/website/html/lang]]' => 'en', '[[/template/page-home/welcome/headline]]' => 'Welcome', '[[/_nino/webpage/kontakt/name]]' => 'Contact' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', [ '/project/website/html/lang' ] );

[ , $personalInfosBody ] = ninoCall( $appData, [ \Nino\Install\PersonalInfos::class, 'apiList' ] );
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

[ , $body ] = ninoCall( $appData, [ \Nino\Install\PersonalInfos::class, 'apiSaveBatch' ], [ 'items' => [
	[ 'key' => '/project/company/contact/country', 'locale' => 'de_DE', 'value' => 'Musterland' ],
	[ 'key' => '/project/company/contact/country', 'locale' => 'en_US', 'value' => 'Sample Country' ],
] ] );
$saveResults = $body['results'];

check( 'saves the de_DE value', $saveResults['/project/company/contact/country']['ok'] === true && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/company/contact/country]]'] ?? null ) === 'Musterland' );
check( 'saves the en_US value', ( \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/project/company/contact/country]]'] ?? null ) === 'Sample Country' );

echo "\n";


// --- Admin::apiList / apiCreate -------------------------------------------

echo "Admin::apiList / apiCreate\n";

[ , $body ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiList' ] );
check( 'does not count a disabled account as a usable admin account', $body['users'] === [] );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'a-long-enough-admin-password' ] );
check( 'refuses to lock the installer before an active _editor account exists', $status === 409 );

/*	The other half of usableUsers()' rule. The account above is disabled;
	an entry that is enabled but carries no password is an array key rather
	than an account, and must not satisfy the same "at least one admin"
	precondition merely because the key exists	*/
$appData['/nino/auth/user']['no-password@example.com'] = [ 'pw' => '', 'status' => 2, 'sessions' => [], 'perms' => [ '/*' ] ];
[ $status ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'a-long-enough-admin-password' ] );
check( '...nor an enabled entry that carries no password', \Nino\Install\Accounts::usableUsers( $appData ) === [] && $status === 409 );
unset( $appData['/nino/auth/user']['no-password@example.com'] );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'not-an-email', 'pw' => 'a-long-enough-password' ] );
check( 'rejects an invalid email with 400', $status === 400 );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'admin@example.com', 'pw' => 'short' ] );
check( 'rejects a too-short password with 400', $status === 400 );

[ , $body ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'admin@example.com', 'pw' => 'a-long-enough-password' ] );
check( 'creates the account', \Nino\Auth::getUser( $appData, 'admin@example.com' ) !== false );
check( 'the root account holds the Developer role the Setup step wrote, and full access through it', \Nino\Auth::getUser( $appData, 'admin@example.com' )['role'] === 'developer' && \Nino\Auth::getUser( $appData, 'admin@example.com' )['perms'] === [] && \Nino\Auth::checkPermission( $appData, '/_admin/config/manage', 'admin@example.com' ) === true );
check( 'creating an account leaves every other account as it was', array_diff_key( $appData['/nino/auth/user'], [ 'admin@example.com' => 1 ] ) === $appData['./nino/auth/baseline'] );
check( 'returns only the newly usable account to the frontend', $body['users'] === [ 'admin@example.com' ] );
check( 'and signs it in to this session as it is created', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '' ) === 'admin@example.com' );
check( '...and hands the page the csrf token the sign-in rotated to', ( $body['csrf'] ?? '' ) === \Nino\Csrf::getToken( $appData ) && strlen( $body['csrf'] ) === 64 );
\Nino\Auth::logoutUser( $appData );
check( 'the new account can actually authenticate', \Nino\Auth::loginUser( $appData, 'admin@example.com', 'a-long-enough-password' ) !== false );
\Nino\Auth::logoutUser( $appData );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'admin@example.com', 'pw' => 'a-different-password' ] );
check( 'creating the same address again replaces it rather than failing', $status === 200 );
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

/*	One minimum for every password the workbench and the wizard set:
	Recovery::MIN_PW_LENGTH. No class keeps a copy of its own, and every form,
	script and label that tells a person the rule names that number	*/
$minPwDeclared = 0;
foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__. '/../_admin', FilesystemIterator::SKIP_DOTS ) ) as $phpFile )
	if( $phpFile->getExtension() === 'php' )
		$minPwDeclared += preg_match_all( '/const\s+int\s+MIN_PW_LENGTH\b/', (string) file_get_contents( $phpFile->getPathname() ) );
$minPwShown = [];
foreach( [
	'templates/page-recovery.tpl' 						=> '/(?:minlength="|at least )(\d+)/',
	'install/templates/page-wizard.tpl' 				=> '/(?:minlength="|at least )(\d+)/',
	'Nino/Modules/Users/assets/admin.js' 			=> '/minLength = (\d+)/',
	'Nino/Modules/Users/assets/recoverypw.js' 	=> '/minLength = (\d+)/',
	'Nino/Modules/Users/text/en_US.php' 				=> '/at least (\d+) characters/',
	'Nino/Modules/Users/text/de_DE.php' 				=> '/mindestens (\d+) Zeichen/',
] as $minPwFile => $minPwPattern ) {
	preg_match_all( $minPwPattern, (string) file_get_contents( __DIR__. '/../_admin/'. $minPwFile ), $minPwMatch );
	$minPwShown[$minPwFile] = array_values( array_unique( array_map( 'intval', $minPwMatch[1] ) ) );
}
check( 'one minimum password length, Recovery::MIN_PW_LENGTH, declared once and named by every form, script and label', $minPwDeclared === 1
	&& array_filter( $minPwShown, static fn( array $numbers ): bool => $numbers !== [ \Nino\Admin\Recovery::MIN_PW_LENGTH ] ) === [] );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'boundary@example.com', 'pw' => str_repeat( 'x', max( 0, $minPw - 1 ) ) ] );
check( 'a password one character below the rule the form shows is refused by the server', $status === 400 );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'boundary@example.com', 'pw' => str_repeat( 'x', $minPw ) ] );
check( '...and one of exactly that length is accepted', $status === 200 );

echo "\n";


// --- Applications::apiList / apiPlan / apiApply -----------------------------

echo "Applications::apiList / apiPlan / apiApply\n";

/*	The official catalogue is replaced the way catalogue-smoke.php replaces it: a
	callable under './nino/fetch/stub' answers from a map of urls, a key pair of
	this run signs, and the archives are built here byte by byte. The one thing the
	wizard does not take from the project - the key it verifies with - is put under
	Applications::KEY_OVERRIDE, a runtime key nothing but a test sets	*/
$appKeypair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
openssl_pkey_export( $appKeypair, $appPrivateKey );
$appPublicKey = openssl_pkey_get_details( $appKeypair )['key'];

$strangerKeypair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
openssl_pkey_export( $strangerKeypair, $strangerPrivateKey );

$appTar = static function( array $entries ): string {
	$out = '';
	foreach( $entries as $name => $content ) {
		$header  = str_pad( $name, 100, "\0" ). "0000644\0". "0000000\0". "0000000\0";
		$header .= str_pad( decoct( $content === null ? 0 : strlen( $content ) ), 11, '0', STR_PAD_LEFT ). "\0". str_pad( decoct( 1700000000 ), 11, '0', STR_PAD_LEFT ). "\0";
		$header .= '        '. ( $content === null ? '5' : '0' ). str_pad( '', 100, "\0" ). "ustar\0". '00';
		$header  = str_pad( $header, 512, "\0" );
		$sum = 0;
		for( $i = 0; $i < 512; $i++ )
			$sum += ord( $header[$i] );
		$header = substr( $header, 0, 148 ). str_pad( decoct( $sum ), 6, '0', STR_PAD_LEFT ). "\0 ". substr( $header, 156 );
		$out .= $header. ( $content === null ? '' : str_pad( $content, (int) ( ceil( strlen( $content ) / 512 ) * 512 ), "\0" ) );
	}
	return gzencode( $out. str_repeat( "\0", 1024 ), 6 );
};

$appRemote		= [];
$appRequests	= [];
$appArchives	= [];

$appFeature = static function( string $key, array $files = [] ) use ( $appTar, &$appRemote, &$appArchives ): array {
	$directory = ucfirst( $key );
	$bytes = $appTar( [
		$directory. '/'									=> null,
		$directory. '/feature.php'			=> '<?php return [ \'key\' => \''. $key. '\', \'name\' => \''. $directory. '\', \'version\' => \'1.0.0\' ];',
		$directory. '/'. $directory. '.php'	=> '<?php namespace Nino\\Modules { class '. $directory. ' { public const string VERSION = \'1.0.0\'; } }',
	] + $files );
	$url = 'https://catalogue.test/features/'. $key. '-1.0.0.tar.gz';
	$appRemote[$url] = $bytes;
	$appArchives[$key] = $bytes;
	return [ 'key' => $key, 'name' => [ 'en_US' => $directory. ' feature', 'de_DE' => $directory. ' Funktion' ], 'description' => 'The '. $key, 'version' => '1.0.0', 'nino' => '^2.0', 'php' => [ 'ext' => [] ],
		'requires' => [], 'directory' => $directory, 'archive' => $url, 'sha256' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ), 'released' => '2026-11-13' ];
};

/*	A feature whose install unit takes the start page where the project has no route for it, the way
	a card or a landing page does: add-only, the address of its own page as the unit's Element-URI	*/
$probeFiles = [
	'Probe/install/'								=> null,
	'Probe/install/manifest.php'		=> '<?php return [ \'routes\' => [ \'GET://\' => [ \'uri\' => \'/probe\', \'body\' => \'[template /templates/page-probe]\' ] ], \'templates\' => [ \'page-probe.tpl\' ] ];',
	'Probe/install/templates/'			=> null,
	'Probe/install/templates/page-probe.tpl'	=> 'probe',
];
$appFeatureList = [ $appFeature( 'alpha' ), $appFeature( 'beta' ), $appFeature( 'gamma' ), $appFeature( 'delta' ), $appFeature( 'probe', $probeFiles ) ];
$appList = [
	[ 'key' => 'site', 'name' => [ 'en_US' => 'Site', 'de_DE' => 'Seite' ], 'description' => [ 'en_US' => 'A site', 'de_DE' => 'Eine Seite' ], 'features' => [ 'alpha', 'beta' ], 'next' => '/_admin/#alpha', 'start' => 'starter' ],
	[ 'key' => 'shop', 'name' => [ 'en_US' => 'Shop' ], 'description' => [ 'en_US' => 'A shop' ], 'features' => [ 'beta', 'gamma' ], 'next' => '/shop', 'start' => null ],
	[ 'key' => 'card', 'name' => [ 'en_US' => 'Card', 'de_DE' => 'Karte' ], 'description' => [ 'en_US' => 'A card' ], 'features' => [ 'delta' ], 'next' => '/card', 'start' => 'own' ],
	[ 'key' => 'blog', 'name' => [ 'en_US' => 'Blog' ], 'description' => [ 'en_US' => 'A blog' ], 'features' => [ 'alpha', 'gamma' ], 'next' => null, 'start' => 'starter' ],
	[ 'key' => 'ghost', 'name' => [ 'en_US' => 'Ghost' ], 'description' => [ 'en_US' => 'Not offered' ], 'features' => [ 'alpha', 'unlisted' ], 'next' => '/ghost', 'start' => null ],
];

$appPublish = static function( ?string $signWith = null, ?array $applications = null ) use ( &$appRemote, $appFeatureList, $appList, $appPrivateKey, $appArchives ): void {
	foreach( $appFeatureList as $feature )
		$appRemote[ $feature['archive'] ] = $appArchives[ $feature['key'] ];
	$json = ninoCatalogue( $appFeatureList, $applications ?? $appList );
	openssl_sign( $json, $signature, $signWith ?? $appPrivateKey, OPENSSL_ALGO_SHA256 );
	$appRemote[ \Nino\Catalogue::DEFAULT_URL ]					= $json;
	$appRemote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ]	= base64_encode( $signature );
};
$appPublish();

/*	A project the wizard has taken as far as the accounts: the setup applied, one account
	signed in, the network replaced. The features directory is the run's own	*/
$appProject = static function( string $name, bool $account = true ) use ( &$appRemote, &$appRequests, $appPublicKey ): array {

	$app = ninoSandbox( $name );
	$dir = ninoSandboxDir( $app );
	$app['/nino/dir']								= '';
	$app['/nino/locales/available']	= [ 'de_DE', 'en_US' ];
	$app['/nino/html/navs']					= \Nino\AppData::DEFAULTS['/nino/html/navs'];
	$app['/nino/modules']						= [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf' ];
	foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $sub )
		mkdir( $dir. $sub, 0777, true );
	\Nino\Filesystem::putFileContent( $app, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ] ] );
	ninoCall( $app, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );

	if( $account === true )
		ninoCall( $app, [ \Nino\Install\Accounts::class, 'apiCreate' ], [ 'mail' => 'owner@example.com', 'pw' => 'a long enough password' ] );

	$app[ \Nino\Install\Applications::KEY_OVERRIDE ] = $appPublicKey;
	$app['./nino/fetch/stub'] = static function( string $url, array $options ) use ( &$appRemote, &$appRequests ): array {
		$appRequests[] = $url;
		if( isset( $appRemote[$url] ) === false )
			return [ 'ok' => false, 'status' => 404, 'error' => 'http 404' ];
		return [ 'ok' => true, 'status' => 200, 'body' => $appRemote[$url] ];
	};

	return $app;
};

$appReset = static function() use ( &$appRequests ): void {
	foreach( glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) ?: [] as $featureDir )
		\Nino\Filesystem::removeDir( $featureDir );
	$appRequests = [];
};

$appCall = static function( array &$app, string $action, array $data = [] ): array {
	return ninoCall( $app, [ \Nino\Install\Install::class, 'handlePost' ], $data, $action );
};

$appReset();
$project = $appProject( 'install-applications' );

// --- what is offered

[ $status, $body ] = $appCall( $project, 'applications/list' );
check( 'the list answers the applications of the signed catalogue that fit this kernel, in its order', $status === 200 && array_column( $body['applications'], 'key' ) === [ 'site', 'shop', 'card', 'blog' ] && $body['message'] === '' && $body['missing'] === [] );
check( '...one that lists a feature the catalogue does not has no place in it', in_array( 'ghost', array_column( $body['applications'], 'key' ), true ) === false );
check( '...with the name and the description in the language of the wizard, English, whatever the language of the project is (de_DE here), and the features it switches on by name in it',
	$body['applications'][0]['name'] === 'Site' && $body['applications'][0]['description'] === 'A site' && $body['applications'][1]['name'] === 'Shop'
	&& $body['applications'][0]['features'] === [ [ 'key' => 'alpha', 'name' => 'Alpha feature' ], [ 'key' => 'beta', 'name' => 'Beta feature' ] ] );
$requestsBefore = $appRequests;
[ , $planBody ] = $appCall( $project, 'applications/plan', [ 'applications' => [ 'site', 'shop' ] ] );
check( '...the plan names the features in the language of the wizard as well', array_column( $planBody['features'], 'name' ) === [ 'Alpha feature', 'Beta feature', 'Gamma feature' ] );
$englishProject = $appProject( 'install-applications-english' );
$englishProject['/nino/locales/native'] = 'en_US';
[ , $englishBody ] = $appCall( $englishProject, 'applications/list' );
check( '...and a project whose own language is English is answered the same, byte for byte', json_encode( $englishBody ) === json_encode( $body ) );
$onlyGerman = $appList;
$onlyGerman[0]['name'] = [ 'de_DE' => 'Nur Deutsch' ];
$appPublish( null, $onlyGerman );
[ , $germanBody ] = $appCall( $appProject( 'install-applications-fallback' ), 'applications/list' );
check( '...a text without an English version falls back as the catalogue does everywhere: to the first it has', $germanBody['applications'][0]['name'] === 'Nur Deutsch' );
$appPublish();
$appRequests = $requestsBefore;
check( '...and whether it provides the start page', array_column( $body['applications'], 'start' ) === [ 'starter', null, 'own', 'starter' ] );
check( 'the answer carries no address, no key and no archive of the catalogue', preg_match( '#https?://|PUBLIC KEY|sha256|archive#', json_encode( $body ) ) === 0 );
check( 'the catalogue was asked for twice and nothing else: its index and its signature, at the official address', $appRequests === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
check( 'nothing was placed by looking', glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );

// --- the plan

[ $status, $body ] = $appCall( $project, 'applications/plan', [ 'applications' => [ 'shop', 'site' ] ] );
check( 'the plan of two applications that share a feature lists it once, in the order of the catalogue and of each application\'s list - however they were posted',
	$status === 200 && array_column( $body['features'], 'key' ) === [ 'alpha', 'beta', 'gamma' ] );
[ , $body ] = $appCall( $project, 'applications/plan', [ 'applications' => [ 'site', 'site', 'shop' ] ] );
check( '...a key twice counts once', array_column( $body['features'], 'key' ) === [ 'alpha', 'beta', 'gamma' ] );
[ , $body ] = $appCall( $project, 'applications/plan', [ 'applications' => [] ] );
check( 'nothing chosen is a plan of nothing', $body === [ 'features' => [] ] );

[ $status ] = $appCall( $project, 'applications/plan', [ 'applications' => [ 'site', 'card' ] ] );
check( 'two applications that both provide the start page exclude each other: 400', $status === 400 );
[ $status ] = $appCall( $project, 'applications/plan', [ 'applications' => [ 'site', 'blog' ] ] );
check( '...whichever they are', $status === 400 );
[ $status ] = $appCall( $project, 'applications/plan', [ 'applications' => [ 'shop', 'card' ] ] );
check( 'one that provides it and one that does not go together', $status === 200 );
foreach( [ 'ghost' => [ 'ghost' ], 'a key nobody offers' => [ 'nope' ], 'a key that is no key' => [ '../x' ], 'a number' => [ 5 ], 'a string, not a list' => 'site', 'an object' => [ 'a' => 'site' ] ] as $why => $wrong ) {
	[ $status ] = $appCall( $project, 'applications/plan', [ 'applications' => $wrong ] );
	check( 'a plan of '. $why. ' is refused with 400', $status === 400 );
}

// --- applying one feature at a time

[ $status ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'shop' ], 'feature' => 'alpha' ] );
check( 'a feature of another application than the chosen ones is refused: 400, and nothing is placed', $status === 400 && glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );
foreach( [ 'unlisted', 'Alpha', '../etc', '', 'delta' ] as $wrongFeature ) {
	[ $status ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => $wrongFeature ] );
	check( 'the feature "'. $wrongFeature. '" is not one of the plan: 400', $status === 400 );
}
check( '...and still nothing is placed', glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );

[ $status, $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => 'alpha' ] );
check( 'a feature of the plan is placed below features/ and switched on', $status === 200 && $body === [ 'feature' => 'alpha', 'placed' => true, 'activated' => true ]
	&& is_file( \Nino\Features::dir(). '/Alpha/feature.php' ) === true && in_array( '\\Nino\\Modules\\Alpha', \Nino\Filesystem::getFileContent( $project, '/config.php', [] )['/nino/modules'], true ) === true );
[ , $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => 'alpha' ] );
check( 'a feature that is on is left as it is: nothing placed, nothing activated', $body === [ 'feature' => 'alpha', 'placed' => false, 'activated' => false ] );

// A feature that is placed and not on (switched off by hand, or left by an interrupted round) is switched on, not placed again
\Nino\Features::deactivate( $project, 'alpha' );
[ , $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] );
check( 'one that is placed and not on is switched on, not placed again', $body === [ 'feature' => 'alpha', 'placed' => false, 'activated' => true ] );

// --- an archive that does not match fails in the middle, and the second round goes on from there

$appRemote[ $appFeatureList[2]['archive'] ] = 'not what the catalogue promised';
[ , $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => 'beta' ] );
check( 'beta goes through', $body['activated'] === true );
[ $status, $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => 'gamma' ] );
check( 'an archive that is not what the signed catalogue promised fails its feature: the answer says why', $status === 400 && ( $body['code'] ?? '' ) === 'install_feature' );
check( '...nothing of it is on the disk, and what went through before stays on',
	is_dir( \Nino\Features::dir(). '/Gamma' ) === false && \Nino\Features::get( $project, 'alpha' )['active'] === true && \Nino\Features::get( $project, 'beta' )['active'] === true );
$appRemote[ $appFeatureList[2]['archive'] ] = $appArchives['gamma'];
$appRequests = [];
[ , $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => 'beta' ] );
check( 'the second round skips what is on', $body === [ 'feature' => 'beta', 'placed' => false, 'activated' => false ] );
[ , $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'site', 'shop' ], 'feature' => 'gamma' ] );
check( '...and places what failed', $body === [ 'feature' => 'gamma', 'placed' => true, 'activated' => true ] );

// What the setup does when it is applied again after a step back: a feature that is on stays on
ninoCall( $project, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$modulesAfterBack = \Nino\Filesystem::getFileContent( $project, '/config.php', [] )['/nino/modules'];
check( 'applying the languages step again leaves the features of the applications switched on', array_diff( [ '\\Nino\\Modules\\Alpha', '\\Nino\\Modules\\Beta', '\\Nino\\Modules\\Gamma' ], $modulesAfterBack ) === []
	&& in_array( '\\Nino\\Modules\\Maintenance', $modulesAfterBack, true ) === true && count( $modulesAfterBack ) === count( array_unique( $modulesAfterBack ) ) );

// --- a start application that is set up stays the one

[ , $body ] = $appCall( $project, 'applications/list' );
check( 'the list says which start application is set up, and which applications are set up and have an entry point', $body['startApplied'] === 'site'
	&& array_column( $body['applications'], 'applied', 'key' ) === [ 'site' => true, 'shop' => true, 'card' => false, 'blog' => true ]
	&& array_column( $body['applications'], 'entry', 'key' ) === [ 'site' => true, 'shop' => true, 'card' => true, 'blog' => false ] );
$swaps = [ 'another start application' => [ [ 'card' ], 409, 'install_start_applied' ], 'none of them, the other chosen' => [ [ 'shop' ], 409, 'install_start_applied' ],
	'two of them' => [ [ 'site', 'card' ], 400, 'install_start_exclusive' ], 'the one that is set up and another' => [ [ 'site', 'shop' ], 200, '' ] ];
foreach( $swaps as $why => [ $chosen, $expected, $code ] ) {
	[ $status, $body ] = $appCall( $project, 'applications/plan', [ 'applications' => $chosen ] );
	check( 'a plan with '. $why. ' is a '. $expected. ( $code === '' ? '' : ' with the code that says so' ), $status === $expected && ( $body['code'] ?? '' ) === $code );
}
[ $status, $body ] = $appCall( $project, 'applications/apply', [ 'applications' => [ 'card' ], 'feature' => 'delta' ] );
check( 'a feature of another start application is refused, and not placed', $status === 409 && ( $body['code'] ?? '' ) === 'install_start_applied' && is_dir( \Nino\Features::dir(). '/Delta' ) === false );
// An application whose features are all on because a larger one was applied is not the one that was chosen: of two that are on, the larger is the start application
$appPublish( null, [ $appList[0], [ 'key' => 'big', 'name' => 'Big', 'description' => 'Big', 'features' => [ 'alpha', 'beta', 'gamma' ], 'next' => null, 'start' => 'starter' ] ] );
$larger = $appProject( 'install-applications-larger' );
foreach( [ 'alpha', 'beta', 'gamma' ] as $largerFeature )
	$appCall( $larger, 'applications/apply', [ 'applications' => [ 'big' ], 'feature' => $largerFeature ] );
[ , $body ] = $appCall( $larger, 'applications/list' );
check( 'where a smaller start application is on as well because a larger was applied, the larger is the one that is set up', $body['startApplied'] === 'big' && array_column( $body['applications'], 'applied', 'key' ) === [ 'site' => true, 'big' => true ] );
$appPublish();
$open = $appProject( 'install-applications-open' );
$appCall( $open, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] );
[ , $body ] = $appCall( $open, 'applications/list' );
[ $status ] = $appCall( $open, 'applications/plan', [ 'applications' => [ 'card' ] ] );
[ $statusNone ] = $appCall( $open, 'applications/plan', [ 'applications' => [] ] );
check( 'while no start application is complete the choice stays open: another one, or none, is planned', $body['startApplied'] === null && $status === 200 && $statusNone === 200 );

// --- what the end of the wizard believes

unset( $project['./nino/features/all'] );
$applied = \Nino\Install\Applications::applied( $project, [ 'site', 'shop' ] );
check( 'the applications whose features are all on are applied, as the catalogue lists them', is_array( $applied ) && array_column( $applied, 'key' ) === [ 'site', 'shop' ] && $applied[0]['start'] === 'starter' );
check( '...with a start application set up a choice without it is the choice of another, and a refusal that says so', \Nino\Install\Applications::applied( $project, [] ) === 'install_start_applied'
	&& \Nino\Install\Applications::applied( $project, [ 'shop' ] ) === 'install_start_applied' && \Nino\Install\Applications::applied( $project, [ 'card', 'shop' ] ) === 'install_start_applied'
	&& \Nino\Install\Applications::applied( $project, [ 'site', 'card' ] ) === 'install_start_applied' );
check( '...a key the catalogue does not list is refused', \Nino\Install\Applications::applied( $project, [ 'nope' ] ) === 'install_unknown_application' );
check( '...as is one the kernel cannot offer', \Nino\Install\Applications::applied( $project, [ 'ghost' ] ) === 'install_unknown_application' );

// A project where only the first of the two features of an application is on: the application is not in
$halfway = $appProject( 'install-applications-halfway' );
$appCall( $halfway, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] );
unset( $halfway['./nino/features/all'] );
$appRequests = [];
check( 'an application reported with a feature that is not on is refused, with a code, whatever is posted', \Nino\Install\Applications::applied( $halfway, [ 'site' ] ) === 'install_not_applied' );
check( '...no application chosen is none applied where no start application is set up, and asks nobody', \Nino\Install\Applications::applied( $halfway, [] ) === [] && $appRequests === [] );
$appCall( $halfway, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'beta' ] );
unset( $halfway['./nino/features/all'] );
check( '...and in once the second is on', array_column( (array) \Nino\Install\Applications::applied( $halfway, [ 'site' ] ), 'key' ) === [ 'site' ] );
// A feature that is switched on at another version than its directory carries is not on: an update is still to be applied
$halfway[ \Nino\Features::STATE_KEY ]['alpha']['version'] = '0.0.1';
unset( $halfway['./nino/features/all'] );
check( '...as is one while a feature of it is recorded at another version than its directory has', \Nino\Install\Applications::applied( $halfway, [ 'site' ] ) === 'install_not_applied' );

// The catalogue that was kept is only believed for the project that reads the official one
$swapped = $appProject( 'install-applications-swapped' );
$appCall( $swapped, 'applications/list' );
$swapped['/nino/catalogue/key'] = $appPublicKey;
unset( $swapped[ \Nino\Install\Applications::KEY_OVERRIDE ] );
check( 'a kept catalogue is not believed once the project names another key: the end refuses with the code of that', \Nino\Install\Applications::applied( $swapped, [ 'site' ] ) === 'install_not_official' );
$targets = \Nino\Install\Applications::targets( $project, $applied );
check( 'the entry points are the paths of the applied applications that have one, in order, named in the language of the wizard, whatever the language of the project is', $targets === [ [ 'key' => 'site', 'name' => 'Site', 'next' => '/_admin/#alpha' ], [ 'key' => 'shop', 'name' => 'Shop', 'next' => '/shop' ] ] );
check( '...an application without one adds none, and a path that is none is not led to',
	\Nino\Install\Applications::targets( $project, [ [ 'key' => 'x', 'name' => 'X', 'next' => null ], [ 'key' => 'y', 'name' => 'Y', 'next' => 'https://evil.test/' ], [ 'key' => 'z', 'name' => 'Z', 'next' => '//evil.test' ] ] ) === [] );

check( 'an application that starts the workbench with the menu folded says so in its entry point, one that says nothing leaves the key out',
	\Nino\Install\Applications::targets( $project, [ [ 'key' => 'x', 'name' => 'X', 'next' => '/x', 'menu' => 'folded' ], [ 'key' => 'y', 'name' => 'Y', 'next' => '/y' ], [ 'key' => 'z', 'name' => 'Z', 'next' => '/z', 'menu' => 'open' ] ] )
	=== [ [ 'key' => 'x', 'name' => 'X', 'next' => '/x', 'menu' => 'folded' ], [ 'key' => 'y', 'name' => 'Y', 'next' => '/y' ], [ 'key' => 'z', 'name' => 'Z', 'next' => '/z' ] ] );

// --- what is kept for the end when the catalogue is gone

$appRemote = [];
$appRequests = [];
$kept = \Nino\Install\Applications::applied( $project, [ 'site' ] );
check( 'the end of the wizard does not ask the network: it reads what the step kept', is_array( $kept ) && $appRequests === [] );

// --- a catalogue that cannot be reached, or that does not verify

$appRemote = [];
$appPublish();
unset( $appRemote[ \Nino\Catalogue::DEFAULT_URL ] );
$unreachable = $appProject( 'install-applications-down' );
$appRequests = [];
[ $status, $body ] = $appCall( $unreachable, 'applications/list' );
check( 'a catalogue that cannot be reached is a sentence, "retry" and no application - and a 200: the way without an application stays open', $status === 200 && $body['applications'] === [] && $body['retry'] === true
	&& $body['reason'] === 'install_catalogue' && $body['message'] !== '' && $appRequests === [ \Nino\Catalogue::DEFAULT_URL ] );
[ $status ] = $appCall( $unreachable, 'applications/plan', [ 'applications' => [ 'site' ] ] );
check( '...and a plan of it is refused', $status === 400 );

$appPublish( $strangerPrivateKey );
$appReset();
$forged = $appProject( 'install-applications-forged' );
$appRequests = [];
[ $status, $body ] = $appCall( $forged, 'applications/list' );
check( 'a catalogue signed with another key is not believed: a sentence and "retry"', $status === 200 && $body['applications'] === [] && $body['retry'] === true && $body['reason'] === 'install_catalogue' && $body['message'] !== '' );
check( '...it was read and its signature asked for, and then not believed: both requests went out, which a catalogue that is not there does not get to', $appRequests === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
[ $status ] = $appCall( $forged, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] );
check( '...and nothing is applied from it', $status === 400 && glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );

$appPublish();
$unreachable[ \Nino\Install\Applications::KEY_OVERRIDE ] = $appPublicKey;
[ , $body ] = $appCall( $unreachable, 'applications/list' );
check( 'asking again once it is there lists the applications', count( $body['applications'] ) === 4 );

// --- the official catalogue and no other

$appReset();
foreach( [
	'another address'		=> [ '/nino/catalogue/url' => 'https://elsewhere.test/catalogue.json' ],
	'no address at all'	=> [ '/nino/catalogue/url' => '' ],
	'another key'				=> [ '/nino/catalogue/key' => $appPublicKey ],
] as $why => $settings ) {

	$foreign = $appProject( 'install-applications-foreign' );
	foreach( $settings as $settingKey => $settingValue )
		$foreign[$settingKey] = $settingValue;
	if( isset( $settings['/nino/catalogue/key'] ) === true )
		unset( $foreign[ \Nino\Install\Applications::KEY_OVERRIDE ] );
	$appRequests = [];

	[ $status, $body ] = $appCall( $foreign, 'applications/list' );
	check( 'a project that names '. $why. ' gets no application from the wizard, and a sentence that says so', $status === 200 && $body['applications'] === [] && $body['message'] !== '' && $body['retry'] === false );
	[ $status ] = $appCall( $foreign, 'applications/plan', [ 'applications' => [ 'site' ] ] );
	[ $statusApply ] = $appCall( $foreign, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] );
	check( '...plans and applies nothing: 409 each - and no request was made for any of it', $status === 409 && $statusApply === 409 && $appRequests === [] && glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );
	check( '...and none of what it holds is believed at the end: the applications posted are refused', is_string( \Nino\Install\Applications::applied( $foreign, [ 'site' ] ) ) );
}

// --- nothing is asked of a project that has no account, or that is done

$appReset();
$bare = $appProject( 'install-applications-bare', false );
foreach( [ 'applications/list' => [], 'applications/plan' => [ 'applications' => [ 'site' ] ], 'applications/apply' => [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] ] as $action => $payload ) {
	[ $status ] = $appCall( $bare, $action, $payload );
	check( $action. ' before there is an account: 409', $status === 409 );
}
check( '...and not one request went out, not one file was placed', $appRequests === [] && glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );

$appReset();
$done = $appProject( 'install-applications-done' );
$done['/nino/install/completed'] = true;
$appRequests = [];
foreach( [ 'applications/list' => [], 'applications/plan' => [ 'applications' => [ 'site' ] ], 'applications/apply' => [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] ] as $action => $payload ) {
	[ $status ] = $appCall( $done, $action, $payload );
	check( $action. ' after the wizard is done: 423', $status === 423 );
}
check( '...and not one request went out, not one file was placed', $appRequests === [] && glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );

// --- what loading needs, said before it fails

$appReset();
$lacking = $appProject( 'install-applications-lacking' );
rename( \Nino\Features::dir(), \Nino\Features::dir(). '-aside' );
$appRequests = [];
[ $status, $body ] = $appCall( $lacking, 'applications/list' );
check( 'where features/ cannot be written the step says so and asks for nothing', $status === 200 && $body['applications'] === [] && $body['retry'] === true && $body['reason'] === 'install_missing' && array_column( $body['missing'], 'key' ) === [ 'features' ] && $appRequests === [] );
[ $status ] = $appCall( $lacking, 'applications/apply', [ 'applications' => [ 'site' ], 'feature' => 'alpha' ] );
check( '...and applies nothing: 409', $status === 409 && $appRequests === [] );
rename( \Nino\Features::dir(). '-aside', \Nino\Features::dir() );
[ , $body ] = $appCall( $lacking, 'applications/list' );
check( '...and lists the applications once it is there', $body['missing'] === [] && count( $body['applications'] ) === 4 );

// --- the end of the wizard

echo "\n";
echo "Finish::apiComplete with applications\n";

$appFinish = static function( array &$app, array $payload = [] ): array {
	return ninoCall( $app, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'a brand new dev password' ] + $payload );
};
$starterOf = static function( array &$app ): array {
	return array_keys( array_filter( \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes'] ?? [], static fn( mixed $route, string $key ): bool => str_starts_with( $key, 'GET://' ) === true && is_array( $route ) === true
		&& preg_match( '~^\[template /templates/page-~', (string) ( $route['body'] ?? '' ) ) === 1, ARRAY_FILTER_USE_BOTH ) );
};
$appApplyAll = static function( array &$app, array $keys ) use ( $appCall ): void {
	[ , $plan ] = $appCall( $app, 'applications/plan', [ 'applications' => $keys ] );
	foreach( $plan['features'] as $feature )
		$appCall( $app, 'applications/apply', [ 'applications' => $keys, 'feature' => $feature['key'] ] );
};

// No application: the starter pages, and the way ahead as it always was
$appReset();
$plain = $appProject( 'install-finish-plain' );
[ $status, $body ] = $appFinish( $plain );
check( 'without an application the end of the wizard creates the starter pages: Start, Contact and 404', $status === 200 && $starterOf( $plain ) === [ 'GET://', 'GET://contact', 'GET://404' ] );
check( '...answers where it leads to: nowhere in particular', array_diff_key( $body, [ 'recoveryKey' => 1 ] ) === [ 'login' => true, 'targets' => [] ] );
check( '...and the empty page, which the Routes panel starts a new route on, is in the project beside them', \Nino\Filesystem::fileExists( $plain, '/templates/page-blank.tpl' ) === true );
check( '...and the project is done', \Nino\Admin\Admin::isInstalled( $plain ) === true );

// A key that cannot be made leaves the wizard open: nothing is shown that is not kept, and the project does not count as installed
$appReset();
$noKey = $appProject( 'install-finish-nokey' );
$holder = $noKey;
$holder['./nino/filesystem/locks'] = [];
\Nino\Filesystem::lockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
[ $status, $body ] = $appFinish( $noKey );
\Nino\Filesystem::unlockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
check( 'where the recovery key cannot be made the end of the wizard fails with a 500 and shows no key', $status === 500 && isset( $body['recoveryKey'] ) === false );
check( '...and the project is not installed and has no recovery password yet: the wizard is still open', \Nino\Admin\Admin::isInstalled( $noKey ) === false && \Nino\Admin\Recovery::hash( $noKey ) === null );
[ $status, $body ] = $appFinish( $noKey );
check( '...and the end that follows, with the lock free, makes the key and shows it', $status === 200 && preg_match( '~^[A-Za-z0-9+/]{43}=$~D', (string) ( $body['recoveryKey'] ?? '' ) ) === 1 );

// An application whose starter pages are the wizard's
$appReset();
$withSite = $appProject( 'install-finish-site' );
$appApplyAll( $withSite, [ 'site' ] );
[ $status, $body ] = $appFinish( $withSite, [ 'applications' => [ 'site' ] ] );
check( 'with an application that has an entry point the end creates the starter pages and names the entry point', $status === 200 && $starterOf( $withSite ) === [ 'GET://', 'GET://contact', 'GET://404' ]
	&& array_diff_key( $body, [ 'recoveryKey' => 1 ] ) === [ 'login' => true, 'targets' => [ [ 'key' => 'site', 'name' => 'Site', 'next' => '/_admin/#alpha' ] ] ] );

// Several applications: all their entry points
$appReset();
$withTwo = $appProject( 'install-finish-two' );
$appApplyAll( $withTwo, [ 'site', 'shop' ] );
[ , $body ] = $appFinish( $withTwo, [ 'applications' => [ 'shop', 'site' ] ] );
check( 'with several, every entry point is named, in the order of the catalogue', array_column( $body['targets'], 'key' ) === [ 'site', 'shop' ] );

// An application that brings the start page itself
$appReset();
$own = $appProject( 'install-finish-own' );
$appApplyAll( $own, [ 'card' ] );
[ $status, $body ] = $appFinish( $own, [ 'applications' => [ 'card' ] ] );
$ownRoutes = \Nino\Filesystem::getFileContent( $own, '/config.php', [] )['/nino/http/routes'] ?? [];
check( 'an application that provides the start page leaves the starter pages out: no start page, no contact page, no menu entry', $status === 200 && $starterOf( $own ) === [ 'GET://404' ]
	&& \Nino\Filesystem::fileExists( $own, '/templates/page-home.tpl' ) === false && \Nino\Filesystem::fileExists( $own, '/templates/page-contact.tpl' ) === false
	&& isset( $ownRoutes['GET://'] ) === false && isset( $ownRoutes['GET://contact'] ) === false && isset( $ownRoutes['GET://404']['navs'] ) === false && \Nino\Admin\Admin::isInstalled( $own ) === true );
check( '...the page for an address that is not there is made all the same, with its status code and its words in both languages', ( $ownRoutes['GET://404']['statusCode'] ?? null ) === 404
	&& isset( \Nino\Filesystem::getFileContent( $own, '/text/de_DE.php', [] )['[[/_nino/webpage/404/name]]'], \Nino\Filesystem::getFileContent( $own, '/text/en_US.php', [] )['[[/_nino/webpage/404/name]]'] ) );
$ownTemplate = (string) \Nino\Filesystem::getFileContent( $own, '/templates/page-404.tpl', '' );
$ownGlobal = \Nino\Filesystem::getFileContent( $own, '/text/global.php', [] );
preg_match_all( '#href="(/_nino/webpage/[^"]+)"#', $ownTemplate, $ownLinks );
check( '...and every address the 404 page links to by name is written, so that no link of it leads nowhere', $ownLinks[1] !== [] && array_filter( $ownLinks[1], static fn( string $link ): bool => ( $ownGlobal['[['. $link. ']]'] ?? '' ) === '' ) === []
	&& in_array( '/_nino/webpage/home/uri', \Nino\Filesystem::getFileContent( $own, '/text/blacklist.php', [] ), true ) );
// Drawn: where an application brings the start page there is no page /home, and a link by page:/home would be a span - the button of the 404 page links by the address the wizard wrote
$own['/nino/modules'] = \Nino\Filesystem::getFileContent( $own, '/config.php', [] )['/nino/modules'];
$own['/nino/dir'] = '';
\Nino\Html::init( $own );
\Nino\Features::registerComponents( $own );
\Nino\Modules\Template::init( $own );
$own404 = \Nino\Html::renderHtml( $own, '[template /templates/page-404]' );
check( '...and its button is a link to the address of the site, drawn so, and no span',
	preg_match( '#<a class="button" href="/">#', $own404 ) === 1 && str_contains( $own404, '<span class="button">' ) === false
	&& str_contains( \Nino\Html::renderHtml( $own, '[button /template/page-404/hero/button href="page:/home"]' ), '<span' ) === true );
check( '...and the empty page is in the project, with no route of its own', \Nino\Filesystem::fileExists( $own, '/templates/page-blank.tpl' ) === true );
check( '...and names its entry point', array_column( $body['targets'], 'next' ) === [ '/card' ] );

// ...but only one that is really in: posting it is not enough
$appReset();
$claimed = $appProject( 'install-finish-claimed' );
$appCall( $claimed, 'applications/list' );
[ $status, $body ] = $appFinish( $claimed, [ 'applications' => [ 'card' ] ] );
check( 'an application reported whose features are not on is refused with the code of that: 409, the wizard stays open and no page is made', $status === 409 && ( $body['code'] ?? '' ) === 'install_not_applied'
	&& \Nino\Admin\Admin::isInstalled( $claimed ) === false && $starterOf( $claimed ) === [] );

// The start application that is set up stays the one at the end too
$appReset();
$swap = $appProject( 'install-finish-swap' );
$appApplyAll( $swap, [ 'card' ] );
foreach( [ 'another start application' => [ 'site' ], 'none' => [] ] as $why => $chosen ) {
	[ $status, $body ] = $appFinish( $swap, [ 'applications' => $chosen ] );
	check( 'the end with '. $why. ' where a start application is set up is a 409 with the code that says so, and the wizard stays open and makes no page', $status === 409 && ( $body['code'] ?? '' ) === 'install_start_applied'
		&& \Nino\Admin\Admin::isInstalled( $swap ) === false && $starterOf( $swap ) === [] );
}
[ $status ] = $appFinish( $swap, [ 'applications' => [ 'card' ] ] );
check( '...and with the one that is set up it ends', $status === 200 && \Nino\Admin\Admin::isInstalled( $swap ) === true );

// An application of which only a smaller one is complete is reported: refused, the wizard stays open
$appReset();
$appPublish( null, [ $appList[0], [ 'key' => 'big', 'name' => 'Big', 'description' => 'Big', 'features' => [ 'alpha', 'beta', 'gamma' ], 'next' => null, 'start' => 'starter' ] ] );
$partialBig = $appProject( 'install-finish-partial-big' );
$appApplyAll( $partialBig, [ 'site' ] );
[ $status, $body ] = $appFinish( $partialBig, [ 'applications' => [ 'big' ] ] );
check( 'the end takes what is really applied: a larger application reported while only the smaller is complete is a 409 with the code of that, as a key nobody lists is',
	$status === 409 && ( $body['code'] ?? '' ) === 'install_not_applied' && \Nino\Admin\Admin::isInstalled( $partialBig ) === false );
$appPublish();

// Without the network what is set up is still known: a step back, a new load, and the end all go on
$appReset();
$offline = $appProject( 'install-finish-offline' );
$appApplyAll( $offline, [ 'site', 'shop' ] );
$appRemote = [];
$appRequests = [];
unset( $offline['./nino/catalogue'] );
[ $status, $body ] = $appCall( $offline, 'applications/list' );
check( 'where the catalogue cannot be reached the list still says what is set up, from the catalogue that was kept: the start choice it fixes and the applications that are in, marked offline, with the reason of the failure and a retry',
	$status === 200 && $body['offline'] === true && $body['startApplied'] === 'site' && $body['reason'] === 'install_catalogue' && $body['retry'] === true
	&& array_column( $body['applications'], 'applied', 'key' )['site'] === true && array_column( $body['applications'], 'applied', 'key' )['shop'] === true );
[ $status, $body ] = $appFinish( $offline, [ 'applications' => [ 'site', 'shop' ] ] );
check( '...and the end, which asks nobody, goes through with them and names their entry points', $status === 200 && array_column( $body['targets'], 'key' ) === [ 'site', 'shop' ] );
$appPublish();
$noneYet = $appProject( 'install-applications-none-yet' );
$appCall( $noneYet, 'applications/list' );
$appRemote = [];
unset( $noneYet['./nino/catalogue'] );
[ , $body ] = $appCall( $noneYet, 'applications/list' );
check( 'with a catalogue that was kept and nothing set up, an unreachable one is a sentence and a retry and no application, as it is without a kept one', $body['offline'] === false && $body['applications'] === [] && $body['retry'] === true );
$appPublish();

// A route that answers 404 already is the page for an address that is not there
$appReset();
$answers404 = $appProject( 'install-finish-404' );
\Nino\AppData::writeContentData( $answers404, [] );
\Nino\Filesystem::mutate( $answers404, '/config.php', static function( array $config ): array { $config['/nino/http/routes']['GET://gone'] = [ 'uri' => '/gone', 'body' => 'x', 'statusCode' => 404 ]; return $config; } );
$answers404['/nino/http/routes']['GET://gone'] = [ 'uri' => '/gone', 'body' => 'x', 'statusCode' => 404 ];
$appApplyAll( $answers404, [ 'card' ] );
$appFinish( $answers404, [ 'applications' => [ 'card' ] ] );
check( 'where a route answers with the status 404 an application that provides the start page adds no page for it', $starterOf( $answers404 ) === [] && isset( \Nino\Filesystem::getFileContent( $answers404, '/config.php', [] )['/nino/http/routes']['GET://404'] ) === false );

// What cannot be believed ends nothing
$appReset();
$refused = $appProject( 'install-finish-refused' );
foreach( [ 'a key as a string' => [ 'site', 400, 'install_shape' ], 'a number in the list' => [ [ 5 ], 400, 'install_shape' ], 'an object' => [ [ 'a' => 'site' ], 400, 'install_shape' ],
	'a key nobody kept in the catalogue' => [ [ 'nope' ], 409, 'install_unresolved' ] ] as $why => [ $wrong, $expected, $code ] ) {
	[ $status, $body ] = $appFinish( $refused, [ 'applications' => $wrong ] );
	check( 'the end with '. $why. ' is a '. $expected. ' with the code that says so', $status === $expected && ( $body['code'] ?? '' ) === $code );
}
$appCall( $refused, 'applications/list' );
[ $status, $body ] = $appFinish( $refused, [ 'applications' => [ 'nope' ] ] );
check( 'the end with a key the catalogue does not list is a 409 with the code of that', $status === 409 && ( $body['code'] ?? '' ) === 'install_unknown_application' );
check( '...and the wizard is still open, with no page made', \Nino\Admin\Admin::isInstalled( $refused ) === false && $starterOf( $refused ) === [] );

// The catalogue the keys came from is not there any more: the end says so instead of guessing at the start page
$appReset();
$nothingKept = $appProject( 'install-finish-nothing-kept' );
$appApplyAll( $nothingKept, [ 'card' ] );
unlink( \Nino\Filesystem::path( $nothingKept, '/data/catalogue.php' ) );
[ $status ] = $appFinish( $nothingKept, [ 'applications' => [ 'card' ] ] );
check( 'where the catalogue the applications came from is not kept the end is refused, the wizard stays open and no starter page is made', $status === 409 && \Nino\Admin\Admin::isInstalled( $nothingKept ) === false && $starterOf( $nothingKept ) === [] );

// A starter page that cannot be written ends nothing either
$appReset();
$blocked = $appProject( 'install-finish-blocked' );
mkdir( ninoSandboxDir( $blocked ). '/private/templates/page-contact.tpl', 0755, true );
[ $status, $body ] = $appFinish( $blocked );
check( 'a starter page that cannot be written ends the call with the file named - the wizard stays open and no password is stored', $status === 500 && str_contains( (string) ( $body['error'] ?? '' ), '/templates/page-contact.tpl' ) === true
	&& \Nino\Admin\Admin::isInstalled( $blocked ) === false && \Nino\Admin\Recovery::hash( $blocked ) === null );
rmdir( ninoSandboxDir( $blocked ). '/private/templates/page-contact.tpl' );
[ $status ] = $appFinish( $blocked );
check( '...and ends once the place is there again', $status === 200 && \Nino\Admin\Admin::isInstalled( $blocked ) === true && $starterOf( $blocked ) === [ 'GET://', 'GET://contact', 'GET://404' ] );

// A feature that takes the start page where the project has none
$probeApplications = [
	[ 'key' => 'probeplain', 'name' => 'Probe plain', 'description' => 'No start page of its own', 'features' => [ 'probe' ], 'next' => null, 'start' => null ],
	[ 'key' => 'probecard', 'name' => 'Probe card', 'description' => 'Brings its own start page', 'features' => [ 'probe' ], 'next' => null, 'start' => 'own' ],
	[ 'key' => 'probesite', 'name' => 'Probe site', 'description' => 'A site', 'features' => [ 'alpha', 'probe' ], 'next' => null, 'start' => 'starter' ],
];
$routesOf = static fn( array &$app ): array => \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes'] ?? [];
$appPublish( null, $probeApplications );

$appReset();
$probeSite = $appProject( 'install-finish-probe-site' );
$appCall( $probeSite, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'alpha' ] );
[ $status, $body ] = $appCall( $probeSite, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'probe' ] );
check( 'a feature that takes the start page where there is none leaves it to an application that starts with the starter pages: it is on, its template is placed and no route of its own is written',
	$status === 200 && $body['activated'] === true && isset( $routesOf( $probeSite )['GET://'] ) === false && \Nino\Filesystem::fileExists( $probeSite, '/templates/page-probe.tpl' ) === true );
$appCall( $probeSite, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'probe' ] );
check( '...also where the feature is on already and the round goes on: the route stays out', isset( $routesOf( $probeSite )['GET://'] ) === false );
check( '...the reservation is kept to the request: nothing of it stays in the state of the project', isset( $probeSite[ \Nino\Features::RESERVED ] ) === false );
[ $status ] = $appFinish( $probeSite, [ 'applications' => [ 'probesite' ] ] );
check( 'the end of the wizard makes the starter pages, the start page among them', $status === 200 && $starterOf( $probeSite ) === [ 'GET://', 'GET://contact', 'GET://404' ] && ( $routesOf( $probeSite )['GET://']['uri'] ?? '' ) === '/home' );

// A round that reserved the start page is abandoned and an application that brings its own start page is chosen: the feature is on, its route was left out
$appReset();
$probeSwitch = $appProject( 'install-finish-probe-switch' );
$appCall( $probeSwitch, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'probe' ] );
[ $status, $body ] = $appCall( $probeSwitch, 'applications/apply', [ 'applications' => [ 'probecard' ], 'feature' => 'probe' ] );
check( 'a feature that is on already is answered as one that is on', $status === 200 && $body === [ 'feature' => 'probe', 'placed' => false, 'activated' => false ] );
[ $status ] = $appFinish( $probeSwitch, [ 'applications' => [ 'probecard' ] ] );
check( 'the end of the wizard gives the route a reserving round left out to an application that brings its own start page: the project has a start page, and no starter page beside it', $status === 200
	&& ( $routesOf( $probeSwitch )['GET://']['uri'] ?? '' ) === '/probe' && array_diff( $starterOf( $probeSwitch ), [ 'GET://', 'GET://404' ] ) === [] && isset( $routesOf( $probeSwitch )['GET://contact'] ) === false );

// What the project has stays: a start page of its own is not replaced by the route that was left out
$appReset();
$probeOwn = $appProject( 'install-finish-probe-own' );
$appCall( $probeOwn, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'probe' ] );
\Nino\Filesystem::mutate( $probeOwn, '/config.php', static function( array $config ): array { $config['/nino/http/routes']['GET://'] = [ 'uri' => '/mine', 'body' => '[template /templates/page-mine]' ]; return $config; } );
$appCall( $probeOwn, 'applications/apply', [ 'applications' => [ 'probecard' ], 'feature' => 'probe' ] );
$appFinish( $probeOwn, [ 'applications' => [ 'probecard' ] ] );
check( 'the route that was left out is added where the project has none and no other: a start page of the project\'s own stays', ( $routesOf( $probeOwn )['GET://']['uri'] ?? '' ) === '/mine' );

// The starter application is not the first of the applications chosen
$appReset();
$probeTwo = $appProject( 'install-finish-probe-two' );
foreach( [ 'alpha', 'probe' ] as $probeFeature )
	$appCall( $probeTwo, 'applications/apply', [ 'applications' => [ 'probesite', 'probeplain' ], 'feature' => $probeFeature ] );
check( 'one of several chosen applications that starts with the starter pages reserves the start page, whichever place it has in the list', isset( $routesOf( $probeTwo )['GET://'] ) === false );

// A request that is refused writes nothing and reserves nothing
$appReset();
$probeRefused = $appProject( 'install-finish-probe-refused' );
[ $status ] = $appCall( $probeRefused, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'unlisted' ] );
check( 'a feature that is not of the plan is refused and leaves no reservation, no route and no feature', $status === 400 && isset( $probeRefused[ \Nino\Features::RESERVED ] ) === false
	&& isset( $routesOf( $probeRefused )['GET://'] ) === false && glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) === [] );
[ $status ] = $appCall( $probeRefused, 'applications/apply', [ 'applications' => [ 'probecard' ], 'feature' => 'probe' ] );
check( '...so that the feature of an application that brings its own start page takes the start page where the project has none', $status === 200 && ( $routesOf( $probeRefused )['GET://']['uri'] ?? '' ) === '/probe' );

// A round of a starter application that failed before the feature was placed leaves nothing behind
$appReset();
$probeFailed = $appProject( 'install-finish-probe-failed' );
$appRemote[ $appFeatureList[0]['archive'] ] = 'not what the catalogue promised';
[ $status ] = $appCall( $probeFailed, 'applications/apply', [ 'applications' => [ 'probesite' ], 'feature' => 'alpha' ] );
$appRemote[ $appFeatureList[0]['archive'] ] = $appArchives['alpha'];
[ $statusProbe ] = $appCall( $probeFailed, 'applications/apply', [ 'applications' => [ 'probecard' ], 'feature' => 'probe' ] );
[ $statusEnd ] = $appFinish( $probeFailed, [ 'applications' => [ 'probecard' ] ] );
check( 'a starter application whose first feature fails, and then an application that brings its own start page: the feature takes the start page and the end leaves it',
	$status === 400 && $statusProbe === 200 && $statusEnd === 200 && ( $routesOf( $probeFailed )['GET://']['uri'] ?? '' ) === '/probe' && array_diff( $starterOf( $probeFailed ), [ 'GET://', 'GET://404' ] ) === [] );

$appPublish();
$appReset();
echo "\n";


// --- Finish::apiComplete / Recovery::set ------------------------------------

echo "Finish::apiComplete / Recovery::set\n";

[ $status ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'short' ] );
check( 'rejects a too-short _admin password with 400', $status === 400 );

[ $status ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => str_repeat( 'x', max( 0, $minPw - 1 ) ) ] );
check( '...also one character below the rule the Finish form shows', $status === 400 );

// The hash is stored under the private directory, outside every tool folder
// and outside config.php. _admin/Admin.php therefore stays byte-identical,
// which is what makes it replaceable on an update (see Recovery::set()'s
// docblock)
$adminBefore = file_get_contents( __DIR__. '/../_admin/Admin.php' );

[ $status, $body, $completed ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'a brand new dev password' ] );
check( 'apiComplete succeeds once an editor account exists', $status === 200 );

/*	The recovery key: the key the archives are made with, made by the end of the wizard so that
	it stands when it is shown, and handed out in this one answer	*/
$shown 			= $body['recoveryKey'] ?? null;
$storedKey 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/backup/key'] ?? null;
$keyCopy 		= (string) @file_get_contents( $sandbox. '/private/.auth/backup-key.php' );
check( 'the end of the wizard answers with the recovery key: base64 of 32 bytes', is_string( $shown ) === true && preg_match( '~^[A-Za-z0-9+/]{43}=$~D', $shown ) === 1 && strlen( (string) base64_decode( $shown, true ) ) === 32 );
check( '...which is the key the archives are encrypted with: the one in config.php', $shown === $storedKey );
check( '...and its copy under .auth is that key behind the exit stub, written before the project counts as installed', $keyCopy === \Nino\Admin\Recovery::STUB_PREFIX. $shown. \Nino\Admin\Recovery::STUB_SUFFIX && \Nino\Admin\Admin::isInstalled( $appData ) === true );
check( '...the answer is not to be kept by a cache', ( $completed['/nino/http/response']['header']['Cache-Control'] ?? '' ) === 'private, no-store' );
check( '...and no other key of config.php holds it: it is under /nino/backup/key alone', substr_count( (string) file_get_contents( $sandbox. '/private/config.php' ), (string) $shown ) === 1 );

/*	Two accounts were created above (admin@ and boundary@), and the last
	apiCreate() left boundary@ signed in. Which of the two stays signed in
	is the login page's decision, not the wizard's: the session is ended
	and the wizard is told so	*/
check( 'with several accounts the wizard answers login: false', array_diff_key( $body, [ 'recoveryKey' => 1 ] ) === [ 'login' => false, 'targets' => [] ] );
check( '...and the session the Accounts step opened is ended', \Nino\Auth::getCurrentUser( $appData ) === false );

/*	The one-account case: the person who just typed the only password is
	the person at the keyboard, so that session walks into the workbench	*/
\Nino\Auth::deleteUser( $appData, 'boundary@example.com' );
\Nino\Auth::loginUser( $appData, 'admin@example.com', 'a-different-password' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'a brand new dev password' ] );
check( 'with exactly one account, the one signed in, it answers login: true', $status === 200 && array_diff_key( $body, [ 'recoveryKey' => 1 ] ) === [ 'login' => true, 'targets' => [] ] );
check( 'a later end of the wizard shows the same key: a key that exists is never made again', ( $body['recoveryKey'] ?? '' ) === $shown );
check( '...and leaves that session signed in', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '' ) === 'admin@example.com' );

// One account, but nobody signed in (a cooled-down ip, a session that did
// not survive): the login page is the way in, as before
\Nino\Auth::logoutUser( $appData );
[ , $body ] = ninoCall( $appData, [ \Nino\Install\Finish::class, 'apiComplete' ], [ 'password' => 'a brand new dev password' ] );
check( 'with one account and no session it answers login: false', array_diff_key( $body, [ 'recoveryKey' => 1 ] ) === [ 'login' => false, 'targets' => [] ] );

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
check( 'Recovery::set() can write the file again afterwards', \Nino\Admin\Recovery::set( $appData, 'another dev password' ) === true );
check( '...and the new password is the one that verifies', password_verify( 'another dev password', (string) \Nino\Admin\Recovery::hash( $appData ) ) === true );

check( 'the private directory carries its own deny rule', is_file( $sandbox. '/private/.htaccess' ) === true );

echo "\n";


// --- Shipped defaults (the real library) -----------------------------------

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

	/*	The body is what ties a route to a file on disk. A route naming a
		template no unit ships is the one failure this whole block exists for.
		Every route is checked, not only the page routes: a unit may register
		something the Routes panel's page list does not recognise, and its
		template still has to exist.	*/
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

/*	The pages of the library are written in the components of Basis, so a fresh project edits them in that notation. A template has its name
	line at byte zero, the head, the sections and the foot, every [section-wrap] has an id that is a slug no section before it has, and no page
	draws a section, a column or a button by hand. By grammar and position: no page, section or key of the library is named here	*/
$shapeFailures	= [];
$shapeTemplates	= 0;

foreach( glob( $realRoot. '/_admin/install/library/pages/*/templates/*.tpl' ) ?: [] as $shapeFile ) {

	$shapeSource = (string) file_get_contents( $shapeFile );

	if( str_contains( $shapeSource, '[section-wrap' ) === false )
		continue;

	$shapeTemplates++;
	$shapeLabel	= substr( $shapeFile, strlen( $realRoot. '/_admin/install/library/pages/' ) );
	$shapeIds	= [];

	if( preg_match( '~\A<!-- nino:template-name [^\r\n<>]+ -->\n\[template /templates/html-header(?:-[a-z0-9]+)*\]\n\[section-wrap\b.*\n\[template /templates/html-footer(?:-[a-z0-9]+)*\]\n\z~s', $shapeSource ) !== 1 )
		$shapeFailures[] = $shapeLabel. ': the name line, the head, the sections and the foot do not stand where they are read';

	if( preg_match( '~<(?:section|header|footer)\b|class="[^"]*\bnino-(?!form)~', $shapeSource ) === 1 )
		$shapeFailures[] = $shapeLabel. ': a section, a header or a footer drawn by hand, or a class of the old stylesheet';

	preg_match_all( '~\[section-wrap\b([^\]]*)\]~', $shapeSource, $shapeTags );

	foreach( $shapeTags[1] as $shapeTag )
		if( preg_match( '~ id="([a-z0-9]+(?:-[a-z0-9]+)*)"~', $shapeTag, $shapeId ) !== 1 || isset( $shapeIds[$shapeId[1]] ) === true )
			$shapeFailures[] = $shapeLabel. ': a section without an id that is a slug, or with one a section before it has ('. trim( $shapeTag ). ')';
		else
			$shapeIds[$shapeId[1]] = true;
}

check( 'the pages of the library are written in the components of Basis ('. $shapeTemplates. ' templates)'. ( $shapeFailures === [] ? '' : ' - '. implode( ' | ', $shapeFailures ) ), $shapeFailures === [] && $shapeTemplates >= 3 );

// The three a starter site is normally built from have to be among them, or
// the starter pages have nothing to create on a fresh install
check( 'the page library offers the three a starter site is built from', count( array_intersect( [ 'home', 'contact', '404' ], $pageUnits ) ) === 3 );

// The optional modules a page can pull in with it are units too, so a page
// declaring one nobody ships would be a dead requirement. A unit travels
// with its module - install/ beside the class file - and Setup::units()
// finds it there without /_install listing it anywhere
$moduleUnits = \Nino\Install\Setup::units();

check( 'the contact page can pull the forms module in with it', isset( $moduleUnits['forms'] ) === true );
check( 'the optional modules ship below _nino/Nino/Modules, switched on or off in /nino/modules', str_ends_with( $moduleUnits['forms'], '/_nino/Nino/Modules/Form/install' ) === true );
// A feature is not a wizard unit: it is activated in the Features panel after
// setup (see \Nino\Features), so its install/ is never offered here
check( 'nothing below features/ is offered by the wizard', array_filter( $moduleUnits, static fn( string $unitDir ): bool => str_contains( $unitDir, '/features/' ) === true ) === [] );
check( 'the forms unit takes its key from its manifest - its directory is "Form"', basename( dirname( $moduleUnits['forms'] ) ) === 'Form' && isset( $moduleUnits['form'] ) === false );
check( '...the others from their directory\'s lowercased name', basename( dirname( $moduleUnits['navigation'] ) ) === 'Navigation' && basename( dirname( $moduleUnits['localepicker'] ) ) === 'Localepicker' );
// ...and each unit activates the module it sits in, or the wizard would
// switch one module on and copy another's templates. A unit has two homes:
// below _nino/Nino/Modules/<Name>/install the class is \Nino\Modules\<Name>;
// anywhere else - the app dir - the class's short name, the part after the
// last backslash, is the name of the directory the install/ sits in, which is
// what "beside the module's class file" means for a project module
$unitFits = static function( string $unitDir ): bool {

	$class = (string) ( ( include $unitDir. '/manifest.php' )['moduleClass'] ?? '' );
	$name = basename( dirname( $unitDir ) );

	return str_contains( $unitDir, '/_nino/Nino/Modules/' ) === true
		? $class === '\\Nino\\Modules\\'. $name
		: ( $class !== '' && substr( $class, (int) strrpos( $class, '\\' ) + 1 ) === $name );
};

check( 'each unit activates the module it ships with', array_filter( $moduleUnits, static fn( string $unitDir ): bool => $unitFits( $unitDir ) === false ) === [] );
// Everything the always-on half does not cover is a unit somebody can decline
check( 'the always-on module list carries no unit the wizard offers', array_intersect(
	array_map( static fn( string $unitDir ): string => (string) ( ( include $unitDir. '/manifest.php' )['moduleClass'] ?? '' ), $moduleUnits ),
	\Nino\AppData::DEFAULTS['/nino/modules']
) === [] );

/*	The base unit delivers no look of its own: the look of the starter pages is the standard part of the feature Basis, and what the unit
	copies is the project's own empty stylesheet, the frames and the favicons - no theme and no webfont	*/
$baseUnit 	= $realRoot. '/_admin/install/library/base';
$baseFiles 	= (array) ( ( include $baseUnit. '/manifest.php' )['files'] ?? [] );

check( 'the base unit ships the project\'s own stylesheet and copies the directory it is in, and no theme, no webfont and no directory of fonts', is_file( $baseUnit. '/assets/style.css' ) === true
	&& in_array( 'assets', $baseFiles, true ) === true && is_file( $baseUnit. '/assets/theme.css' ) === false && is_dir( $baseUnit. '/fonts' ) === false && in_array( 'fonts', $baseFiles, true ) === false
	&& ( ( include $baseUnit. '/manifest.php' )['features'] ?? null ) === [ 'basis' ] );

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

/*	The root size is a percentage of the visitor's browser default - a px length here silently overrules someone who raised their default
	to 20px, and that is the one setting a visitor changes because they need it rather than because they prefer it. Nino.css names it once,
	as --page-size, and sets the size of the root from it	*/
preg_match_all( '/--page-size:\s*([^;]+);/', (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ), $rootSizes );
check( 'Nino.css sets the root size once, as a percentage and not as a length, and the root takes it', count( $rootSizes[1] ) === 1 && str_ends_with( trim( $rootSizes[1][0] ), '%' ) === true
	&& str_contains( (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ), 'font-size: var(--page-size);' ) === true );

/*	A font stack is a comma separated list of family names, and the quotes in it belong to the single name that needs them - "Segoe UI". A value
	quoted as a whole is one family whose name happens to contain commas: no system has it, and the generic keyword that should have caught the
	fall sits inside the string rather than after it, so the browser ends up on its own standard font instead of the sans the stack asked for	*/
$fontStacks		= [];
$quotedWhole	= [];
$withoutBackup	= [];

preg_match_all( '/(--brand-font-[a-z]+):\s*([^;]+);/', (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ), $found, PREG_SET_ORDER );

foreach( $found as [ , $name, $value ] ) {

	$stack = trim( $value );

	// A role with the face of another - --brand-font-text is var(--brand-font-title) until a project names one - is that one's stack,
	// which is checked as itself
	if( preg_match( '/^var\((--brand-font-[a-z]+)\)$/', $stack ) === 1 )
		continue;

	$fontStacks[] = $name;

	if( preg_match( '/^(?:\'[^\']*\'|"[^"]*")$/', $stack ) === 1 )
		$quotedWhole[] = $stack;

	// The last entry is the one the browser is guaranteed to have, so it has to reach it as a keyword and not as part of a name
	if( preg_match( '/,\s*(?:sans-serif|serif|monospace|system-ui|cursive|fantasy)$/', $stack ) !== 1 )
		$withoutBackup[] = $name. ': '. $stack;
}

check( 'every font stack of Nino.css is a list of names and not one quoted name'. ( $quotedWhole === [] ? '' : ' - '. implode( ', ', $quotedWhole ) ), $fontStacks !== [] && $quotedWhole === [] );
check( '...and every one of them ends in a bare generic family'. ( $withoutBackup === [] ? '' : ' - '. implode( ', ', $withoutBackup ) ), $withoutBackup === [] );

/*	An address a shipped template writes is the project's, and a site may sit
	in a subdirectory: a form that posts to "/.newsletter" posts beside a site
	at /shop. [[/nino/dir]] is what the library's own templates put in front
	of every address - page-contact.tpl's action, frame-header.tpl's links	*/
$rootAbsoluteTemplates = [];
$libraryTemplates = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__. '/../_admin/install/library', FilesystemIterator::SKIP_DOTS ) );
foreach( $libraryTemplates as $libraryTemplate )
	if( str_ends_with( $libraryTemplate->getFilename(), '.tpl' ) === true ) {

		// The href of a component's call that begins with the namespace of a text key names that key - the address of a page a template links to is the fill of the starter pages and the Routes panel,
		// /_nino/webpage/contact/uri - and a call is looked at without it. What the key holds is the page's address within the project, without the directory a project in
		// a subdirectory is served from, and a component puts the directory in front of the address such a key resolves to (Components::value(), as the navigation does): which
		// is why the key is the right form of the address of a page, and why this pin sees the address a template writes and not the one a key resolves to
		$libraryMarkup = (string) preg_replace( '#(\[[a-z][a-z0-9-]* [^\]]*?) href="/(?:_nino|template|project|feature|module)/[^"]*"#', '$1', (string) file_get_contents( $libraryTemplate->getPathname() ) );

		if( preg_match( '/\b(?:action|href)="\//', $libraryMarkup ) === 1 )
			$rootAbsoluteTemplates[] = substr( $libraryTemplate->getPathname(), strlen( __DIR__. '/../_admin/install/library/' ) );
	}
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
pinHeaderNamesTheSetWithItsStamp( $headerTemplate, $baseUnit );

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

// The pages the wizard creates when it ends and the three modules it always applies. The Missing
// text keys tile counts every [[/...]] a template names that no file has - a comment is no exception,
// and a placeholder in one is a row the Text panel cannot clear: it names a key the system writes,
// and there is no field for it
$keysApp = ninoSandbox( 'install-keys' );
$keysDir = ninoSandboxDir( $keysApp );
$keysApp['/nino/dir'] = '';
$keysApp['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
$keysApp['/nino/html/navs'] = \Nino\AppData::DEFAULTS['/nino/html/navs'];
$keysApp['/nino/modules'] = $appData['/nino/modules'];
foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $keysSub )
	mkdir( $keysDir. $keysSub, 0777, true );
\Nino\Filesystem::putFileContent( $keysApp, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ] ] );
ninoCall( $keysApp, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$starterApplied = \Nino\Install\StarterPages::apply( $keysApp );
check( 'the starter pages apply', $starterApplied === true && \Nino\Filesystem::fileExists( $keysApp, '/templates/page-contact.tpl' ) === true );
// The style bundle of a fresh starter site, built as a request builds it, with the files of the checkout where the site's root is: the reset, the standard parts of Basis,
// the look of the form and the project's own stylesheet, in at most 40 KB, and the reset alone in at most 12 KB
$keysBuilt = $keysApp;
$keysBuilt['./nino/filesystem/path'] = dirname( __DIR__ );
$keysBuilt['/nino/html/assets'] = \Nino\Filesystem::getFileContent( $keysApp, '/config.php', [] )['/nino/html/assets'];
$keysBuilt['/nino/modules'] = \Nino\Filesystem::getFileContent( $keysApp, '/config.php', [] )['/nino/modules'];
\Nino\Html::init( $keysBuilt );
\Nino\Features::registerAssets( $keysBuilt );
\Nino\Modules\Form::init( $keysBuilt );
\Nino\Features::registerComponents( $keysBuilt );
\Nino\Features::registerParts( $keysBuilt );
\Nino\Modules\Assets::doShortcode( $keysBuilt, [ '/.cache/style.css' ] );
$keysBundle = (string) @file_get_contents( \Nino\Filesystem::path( $keysBuilt, '/.cache/style.css' ) );
check( 'the style bundle of a fresh starter site is the reset, the standard parts of Basis, the look of the form and the project\'s own stylesheet, and it is at most 40 KB; Nino.css alone is at most 12 KB',
	strlen( $keysBundle ) > 20000 && strlen( $keysBundle ) <= 40 * 1024 && str_contains( $keysBundle, '.header-wrap' ) === true && str_contains( $keysBundle, '.nino-form' ) === true && str_contains( $keysBundle, '--brand-color-1' ) === true
	&& filesize( dirname( __DIR__ ). '/_nino/Nino.css' ) <= 12 * 1024 );
// The scans of the workbench read the words and the pictures of a page of Basis through the register: ~title under the id of its section is a key read, image="~background" a place of a picture
$keysApp['/nino/modules'] = \Nino\Filesystem::getFileContent( $keysApp, '/config.php', [] )['/nino/modules'];
\Nino\Features::registerComponents( $keysApp );
// The Features panel of the workbench lists Basis as delivered with the kernel and as on, and it cannot be removed
ninoUser( $keysApp, 'dev@example.com' );
[ $featuresStatus, $featuresBody ] = ninoCall( $keysApp, [ \Nino\Modules\Features\Admin::class, 'apiList' ] );
$basisRow = array_values( array_filter( (array) ( $featuresBody['features'] ?? [] ), static fn( array $row ): bool => ( $row['key'] ?? '' ) === 'basis' ) )[0] ?? [];
[ $removeStatus, $removeBody ] = ninoCall( $keysApp, [ \Nino\Modules\Features\Admin::class, 'apiRemove' ], [ 'key' => 'basis' ] );
check( 'the Features panel lists Basis as delivered with the kernel and as on, and refuses to remove it', $featuresStatus === 200 && ( $basisRow['shipped'] ?? null ) === true && ( $basisRow['active'] ?? null ) === true
	&& $removeStatus === 400 && ( $removeBody['code'] ?? '' ) === 'features_remove_shipped' );
$keysRead = \Nino\Components::sources( $keysApp, (string) \Nino\Filesystem::getFileContent( $keysApp, '/templates/page-home.tpl', '' ), 'page-home' );
$keysReadOf = static fn( string $kind ): array => array_column( array_filter( $keysRead, static fn( array $read ): bool => $read['kind'] === $kind ), 'source' );
check( 'the scans read the home page through the register: its title and its text as the keys they are under the id of their section, and the picture behind the hero as a place of a picture',
	array_diff( [ '/template/page-home/hero/title', '/template/page-home/hero/subtitle', '/template/page-home/start-1/text', '/template/page-home/start-3/title' ], $keysReadOf( 'text' ) ) === []
	&& in_array( '/template/page-home/hero/background', $keysReadOf( 'image' ), true ) === true );
$starterMissing = ( new ReflectionMethod( \Nino\Modules\Text\Keys::class, '_scanMissing' ) )->invokeArgs( null, [ &$keysApp ] );
check( 'on a fresh install the Missing text keys tile reads 0 - no template names a key nobody writes'
	. ( $starterMissing === [] ? '' : ' - '. implode( ', ', array_column( $starterMissing, 'key' ) ) ), \Nino\Modules\Text\Keys::missingCount( $keysApp ) === 0 );
\Nino\Filesystem::removeDir( $keysDir );

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
		// Found, but its directory is not the class's short name: the check
		// "each unit activates the module it ships with" refuses it below
		'/Acme/Other/install' 				=> [ 'label' => 'Other', 'moduleClass' => '\\Acme\\Widget' ],
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

	// The check of the units, over the app dir's too: the module whose class is named like its directory passes, one that names another's class does not
	$misfits = array_keys( array_filter( $foundUnits, static fn( string $unitDir ): bool => $unitFits( $unitDir ) === false ) );
	check( 'a unit below the app dir activates the module whose class is named like its directory - Widget, Deep and the second claim of forms pass - and one named after another class is the only one refused', $misfits === [ 'other' ] && isset( $foundUnits['widget'], $foundUnits['deep-unit'] ) === true );

	// The picker offers it like any other unit, and applying it activates
	// the class and copies the template - with its requirement pulled in
	[ , $appLibraryBody ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiLibrary' ] );
	check( 'the Setup step offers the project module', ( $appLibraryBody['modules']['widget']['label'] ?? null ) === 'Widget'
		&& ( $appLibraryBody['modules']['widget']['requiresModules'] ?? null ) === [ 'forms' ] );
	check( '...and forms/navigation/localepicker/legal stay excluded even once other project modules exist', array_intersect( [ 'forms', 'navigation', 'localepicker', 'legal' ], array_keys( $appLibraryBody['modules'] ) ) === [] );

	[ , $body ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE' ], 'modules' => [ 'widget' ] ] );
	$appApplyBody = $body ?? [];

	check( 'applying it activates the class and its requirement, alongside the four always-on units', ( $appApplyBody['modules'] ?? null ) === [ 'forms', 'navigation', 'localepicker', 'legal', 'widget' ]
		&& in_array( '\\Acme\\Widget', $appData['/nino/modules'], true ) === true
		&& in_array( '\\Nino\\Modules\\Form', $appData['/nino/modules'], true ) === true );
	check( '...and copies its template out of the module directory', \Nino\Filesystem::fileExists( $appData, '/templates/page-widget.tpl' ) === true );

	// A requirement nobody answers to is skipped, not applied
	file_put_contents( $appDir. '/Acme/Widget/install/manifest.php', '<?php return '. var_export( [ 'label' => 'Widget', 'moduleClass' => '\\Acme\\Widget', 'requiresModules' => [ 'nonexistent' ] ], true ). ';' );
	[ , $body ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE' ], 'modules' => [ 'widget' ] ] );
	check( 'a requirement no unit answers to is left out of the applied set - only the always-on four plus what was actually picked remain', ( $body['modules'] ?? null ) === [ 'forms', 'navigation', 'localepicker', 'legal', 'widget' ] );
}


// --- Cleanup ---------------------------------------------------------------

ninoDone( $appData );


// --- The pins ----------------------------------------------------------------

/**
 *	The header the base unit ships names the vector icon through its fill and puts the stamp of the set on
 *	every address of it - the stamp is what makes a browser fetch an icon again once the Favicon tab wrote it
 *
 *	@param		string		$header				The text of html-header.tpl
 *	@param		string		$baseUnit			The directory of the base unit
 *
 *	@return		void
 */
function pinHeaderNamesTheSetWithItsStamp( string $header, string $baseUnit ): void {

	preg_match_all( '#(?:href)="\[\[/nino/public\]\]/favicon/([a-z0-9.-]+)([^"]*)"#', $header, $named, PREG_SET_ORDER );
	$unstamped = array_filter( $named, static fn( array $link ): bool => $link[2] !== '?v=[[/nino/favicon/stamp]]' );

	check( 'the header names the vector icon through the fill that is empty without one, right behind the ico link - no line of its own to leave empty', preg_match( '#favicon\.ico[^"]*" sizes="any">\[\[/nino/favicon/svg\]\]\n#', $header ) === 1 && preg_match( '#^\s*\[\[/nino/favicon/svg\]\]#m', $header ) === 0 );
	check( 'every address of the set in the header carries the stamp, and the header names every raster icon and the manifest the base unit ships'. ( $unstamped === [] ? '' : ' - without: '. implode( ', ', array_column( $unstamped, 1 ) ) ),
		$unstamped === [] && array_diff( array_column( $named, 1 ), array_values( array_diff( scandir( $baseUnit. '/favicon' ), [ '.', '..' ] ) ) ) === []
		&& array_diff( [ 'favicon.ico', 'apple-touch-icon.png', 'favicon-32x32.png', 'favicon-16x16.png', 'site.webmanifest' ], array_column( $named, 1 ) ) === [] );
}

/**
 *	The css bundle the setup writes is the kernel's and the setup's, never what a switched-on feature put in the runtime list at boot: its stylesheet
 *	reaches the page at run time, once, and a config.php that holds a list already is left to that list. A process of its own, since the features
 *	directory is a constant that has to be there before the kernel loads
 */
function pinSetupLeavesTheStylesheetsOfFeaturesOut(): void {

	$dir = sys_get_temp_dir(). '/nino-install-features-'. uniqid();
	mkdir( $dir, 0755, true );
	file_put_contents( $dir. '/run.php', '<?php declare(strict_types=1);
define( \'NINO_FEATURES_DIR\', '. var_export( __DIR__. '/fixtures/features', true ). ' );
require '. var_export( __DIR__. '/harness.php', true ). ';
require '. var_export( dirname( __DIR__ ). '/_admin/install/Install.php', true ). ';
$css = \'/features/Sample/assets/sample.css\';
$out = [];
foreach( [ \'absent\' => null, \'held\' => [ \'/.cache/style.css\' => [ \'/_nino/Nino.css\', \'/assets/style.css\', \'/assets/own.css\' ] ] ] as $case => $held ) {
	$appData = ninoSandbox( \'install-features\' );
	$sandbox = ninoSandboxDir( $appData );
	$appData[\'/nino/dir\'] = \'\';
	foreach( [ \'/private/templates\', \'/public/images\', \'/private/text\', \'/private/assets\' ] as $sub )
		mkdir( $sandbox. $sub, 0777, true );
	$config = [ \'/nino/locales/native\' => \'de_DE\', \'/nino/locales/available\' => [ \'de_DE\' ] ];
	if( $held !== null )
		$config[\'/nino/html/assets\'] = $held;
	\\Nino\\Filesystem::putFileContent( $appData, \'/config.php\', $config );
	$appData[\'/nino/html/assets\'] = \\Nino\\AppData::DEFAULTS[\'/nino/html/assets\'];
	$appData[\'/nino/modules\'] = [ \'\\\\Nino\\\\Modules\\\\Assets\', \'\\\\Nino\\\\Modules\\\\Sample\' ];
	\\Nino\\Features::registerAssets( $appData );
	[ $status ] = ninoCall( $appData, [ \\Nino\\Install\\Setup::class, \'apiApply\' ], [ \'locales\' => [ \'de_DE\' ], \'modules\' => [] ] );
	$written = \\Nino\\Filesystem::getFileContent( $appData, \'/config.php\', [] )[\'/nino/html/assets\'][\'/.cache/style.css\'] ?? [];
	// The next request: the list of the file, the feature switched on again
	$page = ninoSandbox( \'install-features-page\' );
	$page[\'./nino/filesystem/configpath\'] = $sandbox. \'/private\';
	$page[\'./nino/filesystem/contentpath\'] = $sandbox. \'/private\';
	$page[\'/nino/dir\'] = \'\';
	$page[\'/nino/html/assets\'] = \\Nino\\Filesystem::getFileContent( $page, \'/config.php\', [] )[\'/nino/html/assets\'];
	$page[\'./nino/filesystem/publicpath\'] = $sandbox. \'/public\';
	$page[\'./nino/filesystem/path\'] = $sandbox;
	$page[\'/nino/modules\'] = [ \'\\\\Nino\\\\Modules\\\\Assets\', \'\\\\Nino\\\\Modules\\\\Sample\' ];
	\\Nino\\Features::registerAssets( $page );
	\\Nino\\Modules\\Assets::doShortcode( $page, [ \'/.cache/style.css\' ] );
	$bundle = (string) file_get_contents( \\Nino\\Filesystem::path( $page, \'/.cache/style.css\' ) );
	$out[$case] = [ \'status\' => $status, \'written\' => $written, \'runtime\' => $page[\'/nino/html/assets\'][\'/.cache/style.css\'], \'times\' => substr_count( $bundle, trim( (string) file_get_contents( '. var_export( __DIR__. '/fixtures/features/Sample/assets/sample.css', true ). ' ) ) ) ];
}
echo json_encode( $out );' );
	$out = json_decode( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $dir. '/run.php' ). ' 2>/dev/null | tail -n 1' ), true ) ?? [];
	\Nino\Filesystem::removeDir( $dir );

	$css = '/features/Sample/assets/sample.css';
	check( 'with a feature switched on and no list in config.php, the setup writes the kernel\'s stylesheet and the two of the base unit, and not the one of the feature',
		( $out['absent']['status'] ?? null ) === 200 && ( $out['absent']['written'] ?? [] ) === [ '/_nino/Nino.css', '/assets/style.css' ] );
	check( '...and the page still carries the stylesheet of the feature, listed once and bundled once',
		( array_count_values( $out['absent']['runtime'] ?? [] )[$css] ?? 0 ) === 1 && ( $out['absent']['times'] ?? null ) === 1 );
	check( 'where config.php holds a list already, the setup leaves it as it is, with nothing of the feature in it',
		( $out['held']['status'] ?? null ) === 200 && ( $out['held']['written'] ?? [] ) === [ '/_nino/Nino.css', '/assets/style.css', '/assets/own.css' ] );
}
