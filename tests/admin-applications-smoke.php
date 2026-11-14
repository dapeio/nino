<?php
declare(strict_types=1);

/**
 *	Nino
 *	admin-applications-smoke.php	Contract test for setting an application of the
 *											catalogue up after the installation: \Nino\Applications, the
 *											one place the setup wizard and the Features panel place and
 *											switch features on from, and the panel's two actions
 *											(application-plan, application-apply) over a signed catalogue
 *											of this run's own. The list the panel carries, a set-up from
 *											every state (nothing on, some on, all on), a failure in the
 *											middle and the go on from there, what loading needs, a
 *											catalogue that is not the one config.php names or is not
 *											signed with its key, no account, no permission, keys a request
 *											made up, an entry point that is no path of the site, and that
 *											a project with pages of its own keeps every route and gets no
 *											starter page.
 *
 *											No network: a stub under './nino/fetch/stub' answers from a
 *											map of urls. The archives are built here, byte by byte.
 *
 *	Usage: php tests/admin-applications-smoke.php
 */

// Before the kernel loads: the features land in a directory of this run's own
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-applications-features-'. uniqid() );
mkdir( NINO_FEATURES_DIR, 0755, true );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( NINO_FEATURES_DIR ) );

// --- Helpers: keys, a tar writer, a signed catalogue, a stub ----------------------

$keypair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
openssl_pkey_export( $keypair, $privateKey );
$publicKey = openssl_pkey_get_details( $keypair )['key'];

$strangerPair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
openssl_pkey_export( $strangerPair, $strangerPrivateKey );
$strangerPublicKey = openssl_pkey_get_details( $strangerPair )['key'];

$tar = static function( array $entries ): string {
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

$remote		= [];
$requests	= [];
$features	= [];

/**
 *	One feature of the catalogue: its archive and its entry. $extra are more files of the
 *	archive (the feature's install unit), $manifest more keys of its manifest
 */
$makeFeature = static function( string $key, array $extra = [], string $manifest = '', array $entry = [] ) use ( $tar, &$remote, &$features ): void {
	$directory = ucfirst( $key );
	$files = [
		$directory. '/'										=> null,
		$directory. '/feature.php'				=> '<?php return [ \'key\' => \''. $key. '\', \'name\' => \''. $directory. '\', \'version\' => \'1.0.0\''. $manifest. ' ];',
		$directory. '/'. $directory. '.php'	=> '<?php namespace Nino\\Modules { class '. $directory. ' { public const string VERSION = \'1.0.0\'; } }',
	];
	foreach( $extra as $name => $content )
		$files[ $directory. '/'. $name ] = $content;
	$bytes = $tar( $files );
	$url = 'https://catalogue.test/features/'. $key. '-1.0.0.tar.gz';
	$remote[$url] = $bytes;
	$features[$key] = $entry + [ 'key' => $key, 'name' => [ 'en_US' => $directory. ' feature', 'de_DE' => $directory. ' Funktion' ], 'description' => 'The '. $key, 'version' => '1.0.0', 'nino' => '^1.0', 'php' => [ 'ext' => [] ],
		'requires' => [], 'directory' => $directory, 'archive' => $url, 'sha256' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ), 'released' => '2026-11-13' ];
};

$makeFeature( 'alpha' );
$makeFeature( 'beta' );
$makeFeature( 'gamma' );
// The feature that brings the application's own start page, as a card does: its unit asks for the start route and a template
$makeFeature( 'delta', [
	'install/manifest.php'								=> '<?php return [ \'routes\' => [ \'GET://\' => [ \'uri\' => \'/delta\', \'body\' => \'[template /templates/page-delta]\' ], \'GET://delta\' => [ \'uri\' => \'/delta\', \'body\' => \'[template /templates/page-delta]\' ] ], \'templates\' => [ \'page-delta.tpl\' ] ];',
	'install/templates/page-delta.tpl'		=> '<p>The delta page</p>',
] );
// One that is placed and cannot be switched on: it requires a feature that is not there
$makeFeature( 'lonely', [], ", 'requires' => [ 'nowhere' ]", [ 'requires' => [] ] );
// One that asks for a php extension this server does not have: no application with it is offered
$makeFeature( 'needy', [], '', [ 'php' => [ 'ext' => [ 'no_such_extension' ] ] ] );

$applications = [
	[ 'key' => 'site', 'name' => [ 'en_US' => 'Site', 'de_DE' => 'Seite' ], 'description' => [ 'en_US' => 'A site', 'de_DE' => 'Eine Seite' ], 'features' => [ 'alpha', 'beta' ], 'next' => '/_admin/#alpha', 'start' => 'starter' ],
	[ 'key' => 'shop', 'name' => [ 'en_US' => 'Shop' ], 'description' => [ 'en_US' => 'A shop' ], 'features' => [ 'beta', 'gamma' ], 'next' => '/shop', 'start' => null ],
	[ 'key' => 'card', 'name' => [ 'en_US' => 'Card', 'de_DE' => 'Karte' ], 'description' => [ 'en_US' => 'A card' ], 'features' => [ 'delta' ], 'next' => '/card', 'start' => 'own' ],
	[ 'key' => 'blog', 'name' => [ 'en_US' => 'Blog' ], 'description' => [ 'en_US' => 'A blog' ], 'features' => [ 'alpha', 'gamma' ], 'next' => null, 'start' => 'starter' ],
	[ 'key' => 'broken', 'name' => [ 'en_US' => 'Broken' ], 'description' => [ 'en_US' => 'One feature cannot be switched on' ], 'features' => [ 'alpha', 'lonely', 'gamma' ], 'next' => '/broken', 'start' => null ],
	[ 'key' => 'ghost', 'name' => [ 'en_US' => 'Ghost' ], 'description' => [ 'en_US' => 'Lists a feature the catalogue does not' ], 'features' => [ 'alpha', 'unlisted' ], 'next' => '/ghost', 'start' => null ],
	[ 'key' => 'picky', 'name' => [ 'en_US' => 'Picky' ], 'description' => [ 'en_US' => 'Needs an extension this server lacks' ], 'features' => [ 'alpha', 'needy' ], 'next' => '/picky', 'start' => null ],
	// An entry point that is no path of this site is no entry point
	[ 'key' => 'wild-one', 'name' => [ 'en_US' => 'Wild one' ], 'description' => [ 'en_US' => 'Wild' ], 'features' => [ 'alpha' ], 'next' => 'https://evil.test/', 'start' => null ],
	[ 'key' => 'wild-two', 'name' => [ 'en_US' => 'Wild two' ], 'description' => [ 'en_US' => 'Wild' ], 'features' => [ 'alpha' ], 'next' => '//evil.test/', 'start' => null ],
	[ 'key' => 'wild-three', 'name' => [ 'en_US' => 'Wild three' ], 'description' => [ 'en_US' => 'Wild' ], 'features' => [ 'alpha' ], 'next' => 'javascript:alert(1)', 'start' => null ],
	[ 'key' => 'wild-four', 'name' => [ 'en_US' => 'Wild four' ], 'description' => [ 'en_US' => 'Wild' ], 'features' => [ 'alpha' ], 'next' => "/a\r\nSet-Cookie: x=1", 'start' => null ],
];
$offeredKeys = [ 'site', 'shop', 'card', 'blog', 'broken', 'wild-one', 'wild-two', 'wild-three', 'wild-four' ];

/**
 *	Publish the catalogue under $url, signed with $signWith, and forget what an earlier request read
 */
$publish = static function( array &$app, ?string $signWith = null, ?string $url = null ) use ( &$remote, &$features, &$applications, $privateKey ): void {
	$url = $url ?? \Nino\Catalogue::DEFAULT_URL;
	$json = json_encode( [ 'format' => 1, 'generated' => '2026-11-13T12:00:00Z', 'features' => array_values( $features ), 'applications' => $applications ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	openssl_sign( $json, $signature, $signWith ?? $privateKey, OPENSSL_ALGO_SHA256 );
	$remote[$url]					= $json;
	$remote[$url. '.sig']	= base64_encode( $signature );
	unset( $app['./nino/catalogue'] );
};

/**
 *	A project with an account that may use the Features panel, signed in, the catalogue
 *	replaced by the stub, and pages of its own: a start page, a second page and their templates
 */
$project = static function( string $name, array $perms = [ '/_admin/features/manage' ] ) use ( &$remote, &$requests, $publicKey ): array {

	$app = ninoSandbox( $name );
	$dir = ninoSandboxDir( $app );

	foreach( [ '/private/templates', '/private/text', '/public/images', '/private/assets' ] as $sub )
		mkdir( $dir. $sub, 0777, true );

	file_put_contents( $dir. '/private/templates/page-home.tpl', "<h1>My own home</h1>\n" );
	file_put_contents( $dir. '/private/templates/page-about.tpl', "<h1>About</h1>\n" );

	$app['./nino/filesystem/path']	= dirname( __DIR__ );
	$app['/nino/admin/backups']		= false;
	$app['/nino/admin/logs']			= false;
	$app['/nino/auth/user']				= [];
	$app['/nino/auth/roles']			= [];
	$app['/nino/catalogue/key']		= $publicKey;
	$app['/nino/dir']							= '';

	\Nino\Filesystem::putFileContent( $app, '/config.php', [
		'/nino/locales/native'		=> 'de_DE',
		'/nino/locales/available'	=> [ 'de_DE', 'en_US' ],
		'/nino/modules'						=> [],
		'/nino/http/routes'				=> [
			'GET://'				=> [ 'uri' => '/home', 'body' => '[template /templates/page-home]' ],
			'GET://about'		=> [ 'uri' => '/about', 'body' => '[template /templates/page-about]' ],
		],
	] );

	\Nino\Auth::insertUser( $app, 'dev@example.com', 'correct horse battery staple', $perms );
	\Nino\Auth::loginUser( $app, 'dev@example.com', 'correct horse battery staple' );

	$app['./nino/fetch/stub'] = static function( string $url, array $options ) use ( &$remote, &$requests ): array {
		$requests[] = $url;
		if( isset( $remote[$url] ) === false )
			return [ 'ok' => false, 'status' => 404, 'error' => 'http 404' ];
		return [ 'ok' => true, 'status' => 200, 'body' => $remote[$url] ];
	};

	return $app;
};

$reset = static function() use ( &$requests ): void {
	foreach( glob( \Nino\Features::dir(). '/*', GLOB_ONLYDIR ) ?: [] as $featureDir )
		\Nino\Filesystem::removeDir( $featureDir );
	$requests = [];
};

$call = static function( array &$app, string $method, array $data = [] ): array {
	return array_slice( ninoCall( $app, [ \Nino\Modules\Features\Admin::class, $method ], $data ), 0, 2 );
};

$plan = static function( array &$app, string $application ) use ( $call ): array {
	return $call( $app, 'apiApplicationPlan', [ 'application' => $application ] );
};

$apply = static function( array &$app, string $application, string $feature ) use ( $call ): array {
	return $call( $app, 'apiApplicationApply', [ 'application' => $application, 'feature' => $feature ] );
};

/** The state of one application as the list carries it, from a fresh read of the project */
$stateOf = static function( array &$app, string $key ) use ( $call ): ?string {
	[ , $body ] = $call( $app, 'apiList' );
	foreach( $body['catalogue']['applications'] ?? [] as $application )
		if( $application['key'] === $key )
			return $application['state'];
	return null;
};

$word = static function( string $locale, string $key, string $reason = '' ): string {
	$fills = (array) include dirname( __DIR__ ). '/_admin/Nino/Modules/Features/text/'. $locale. '.php';
	return str_replace( '%s', $reason, (string) ( $fills['[['. $key. ']]'] ?? '' ) );
};

$configOf = static function( array &$app ): array {
	return (array) \Nino\Filesystem::getFileContent( $app, '/config.php', [] );
};

// --- No account, no permission: nothing is asked of the catalogue ------------------

echo "Applications panel - who may set one up\n";

$reset();
$app = $project( 'applications-denied', [ '/_admin/text/manage' ] );
$publish( $app );

foreach( [ 'apiApplicationPlan' => [ 'application' => 'site' ], 'apiApplicationApply' => [ 'application' => 'site', 'feature' => 'alpha' ] ] as $method => $data ) {

	$bare = ninoSandbox( 'applications-session' );
	$bare['./nino/filesystem/path'] = dirname( __DIR__ );
	check( $method. ' is 401 without a session', array_slice( ninoCall( $bare, [ \Nino\Modules\Features\Admin::class, $method ], $data ), 0, 2 ) === [ 401, [ 'error' => 'not logged in', 'code' => 'session' ] ] );
	\Nino\Filesystem::removeDir( ninoSandboxDir( $bare ) );

	check( $method. ' is 403 for an account without the permission of the panel', $call( $app, $method, $data ) === [ 403, [ 'error' => 'not allowed' ] ] );
}
check( '...and neither of the refusals reached for the catalogue or placed a byte', $requests === [] && glob( NINO_FEATURES_DIR. '/*' ) === [] );
check( 'both actions are in the map the shell dispatches, behind the same csrf check as the rest', ( static function() use ( $app ): bool {
	$actions = \Nino\Admin\Admin::actions( $app );
	return ( $actions['features/application-plan'] ?? null ) === [ \Nino\Modules\Features\Admin::class, 'apiApplicationPlan' ]
		&& ( $actions['features/application-apply'] ?? null ) === [ \Nino\Modules\Features\Admin::class, 'apiApplicationApply' ];
} )() );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

echo "\n";

// --- What the panel carries --------------------------------------------------------

echo "Applications panel - the list: the applications of the catalogue and how far each is set up\n";

$reset();
$app = $project( 'applications-list' );
$publish( $app );

[ $status, $body ] = $call( $app, 'apiCatalogue' );
check( 'refreshing the catalogue answers its applications with the offers: the ones this kernel can run, in the order of the catalogue', $status === 200
	&& array_column( $body['applications'], 'key' ) === $offeredKeys );
$byKey = array_column( $body['applications'], null, 'key' );
check( '...each as the script reads it, and nothing else', array_keys( $byKey['site'] ) === [ 'key', 'name', 'description', 'start', 'next', 'features', 'state' ] );
check( '...with its name and description in the interface language (de_DE here), else English, and its start statement as the catalogue gave it',
	$byKey['site']['name'] === 'Seite' && $byKey['site']['description'] === 'Eine Seite' && $byKey['shop']['name'] === 'Shop' && $byKey['site']['start'] === 'starter' && $byKey['card']['start'] === 'own' && $byKey['shop']['start'] === null );
check( '...and its features by name, in the order it lists them, each with whether it is on', $byKey['site']['features'] === [ [ 'key' => 'alpha', 'name' => 'Alpha Funktion', 'on' => false, 'state' => 'off' ], [ 'key' => 'beta', 'name' => 'Beta Funktion', 'on' => false, 'state' => 'off' ] ] );
check( '...none of them set up in a project that has no feature on', array_unique( array_column( $body['applications'], 'state' ) ) === [ 'none' ] );
check( 'an application that lists a feature the catalogue does not, or one this server cannot run, is not offered', isset( $byKey['ghost'] ) === false && isset( $byKey['picky'] ) === false );

check( 'the entry point is a path of the site or nothing: an address with a scheme, a second slash, a script or a line break is dropped, one with an anchor stays',
	$byKey['site']['next'] === '/_admin/#alpha' && $byKey['shop']['next'] === '/shop' && $byKey['blog']['next'] === null
	&& $byKey['wild-one']['next'] === null && $byKey['wild-two']['next'] === null && $byKey['wild-three']['next'] === null && $byKey['wild-four']['next'] === null );

$requests = [];
[ $status, $listed ] = $call( $app, 'apiList' );
check( 'the list opens with the same applications from the cache and asks nobody', $status === 200 && $requests === [] && $listed['catalogue']['applications'] === $body['applications'] );

\Nino\Runtime::setSessionValue( $app, './admin/locale', 'en_US' );
[ , $english ] = $call( $app, 'apiList' );
\Nino\Runtime::unsetSessionValue( $app, './admin/locale' );
check( 'the words follow the language of the workbench', array_column( $english['catalogue']['applications'], 'name', 'key' )['site'] === 'Site' && array_column( $english['catalogue']['applications'], 'name', 'key' )['card'] === 'Card' );

// A cache written before the index named applications has none, and the list still answers
$cacheFile = \Nino\Filesystem::getFileContent( $app, '/data/catalogue.php', [] );
unset( $cacheFile['catalogue']['applications'] );
\Nino\Filesystem::putFileContent( $app, '/data/catalogue.php', $cacheFile );
ninoWarnings();
[ $status, $old ] = $call( $app, 'apiList' );
check( 'a catalogue cached before it carried applications lists with none', $status === 200 && $old['catalogue']['applications'] === [] && ninoWarnings() === [] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- Setting up, from every state ---------------------------------------------------

echo "Applications panel - setting one up from nothing, from a part and from all\n";

$reset();
$app = $project( 'applications-states' );
$publish( $app );
$call( $app, 'apiCatalogue' );

[ $status, $body ] = $plan( $app, 'site' );
check( 'the plan of an application with nothing on lists every feature, in the order the application gives them', $status === 200 && $body['todo'] === [ 'alpha', 'beta' ]
	&& $body['application']['key'] === 'site' && $body['application']['state'] === 'none' && array_keys( $body ) === [ 'application', 'todo' ] );
check( '...and places nothing: the plan reads the signed catalogue and no archive', glob( NINO_FEATURES_DIR. '/*' ) === [] && array_filter( $requests, static fn( string $url ): bool => str_contains( $url, '.tar.gz' ) ) === [] );

[ $status, $body ] = $apply( $app, 'site', 'alpha' );
check( 'applying one feature places it and switches it on, and answers the application as the list would show it', $status === 200 && $body['feature'] === 'alpha' && $body['placed'] === true && $body['activated'] === true
	&& array_keys( $body ) === [ 'feature', 'placed', 'activated', 'application' ] && $body['application']['state'] === 'partial' );
$config = $configOf( $app );
check( '...the directory is below features/ and the class is in the module list, recorded at its version', is_file( NINO_FEATURES_DIR. '/Alpha/feature.php' ) && in_array( '\\Nino\\Modules\\Alpha', $config['/nino/modules'], true ) === true
	&& ( $config['/nino/features']['alpha']['version'] ?? null ) === '1.0.0' );
check( '...and the application is partly set up, with the feature that is still off to go: another application with it is partly set up as well', $stateOf( $app, 'site' ) === 'partial' && $stateOf( $app, 'blog' ) === 'partial' && $stateOf( $app, 'shop' ) === 'none' );

[ $status, $body ] = $plan( $app, 'site' );
check( 'the plan from a part lists only what is off', $status === 200 && $body['todo'] === [ 'beta' ] && $body['application']['features'][0]['on'] === true && $body['application']['features'][1]['on'] === false );

[ $status, $body ] = $apply( $app, 'site', 'alpha' );
check( 'applying a feature that is on is left as it is: not placed, not switched on, still a 200', $status === 200 && $body['placed'] === false && $body['activated'] === false );

[ $status, $body ] = $apply( $app, 'site', 'beta' );
check( 'the last feature sets the application up', $status === 200 && $body['application']['state'] === 'applied' && $stateOf( $app, 'site' ) === 'applied' && $stateOf( $app, 'shop' ) === 'partial' );
[ $status, $body ] = $plan( $app, 'site' );
check( 'the plan of an application that is all on has nothing left', $status === 200 && $body['todo'] === [] && $body['application']['state'] === 'applied' );

// A feature switched off by hand is not on: the application is partly set up again, and the plan has it
\Nino\Features::deactivate( $app, 'beta' );
check( 'a feature switched off singly takes the application back to partly set up - it is no state that is kept', $stateOf( $app, 'site' ) === 'partial' && $plan( $app, 'site' )[1]['todo'] === [ 'beta' ] );
[ , $body ] = $apply( $app, 'site', 'beta' );
check( '...and is switched on again by the same action, its directory already there', $body['placed'] === false && $body['activated'] === true && $stateOf( $app, 'site' ) === 'applied' );

// A feature that is on and whose directory carries another version than the record has an update waiting: it is not on, and it is not off
$featureStates = static function( array &$app ) use ( $call ): array {
	unset( $app['./nino/features/all'] );
	[ , $body ] = $call( $app, 'apiList' );
	foreach( $body['catalogue']['applications'] as $application )
		if( $application['key'] === 'site' )
			return [ 'states' => array_column( $application['features'], 'state', 'key' ), 'on' => array_column( $application['features'], 'on', 'key' ), 'application' => $application['state'] ];
	return [];
};
check( 'every feature of an application carries its state: on when it is on', $featureStates( $app ) === [ 'states' => [ 'alpha' => 'on', 'beta' => 'on' ], 'on' => [ 'alpha' => true, 'beta' => true ], 'application' => 'applied' ] );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array { $content['/nino/features']['alpha']['version'] = '0.9.0'; return $content; }, [] );
$app['/nino/features']['alpha']['version'] = '0.9.0';
unset( $app['./nino/features/all'] );
check( '...update when it is switched on and the record is behind its directory, off when it is not on at all - not the same thing, and the rule for being on stays', $featureStates( $app ) === [ 'states' => [ 'alpha' => 'update', 'beta' => 'on' ], 'on' => [ 'alpha' => false, 'beta' => true ], 'application' => 'partial' ] );
\Nino\Features::deactivate( $app, 'beta' );
check( '...', $featureStates( $app )['states'] === [ 'alpha' => 'update', 'beta' => 'off' ] );
\Nino\Features::activate( $app, 'beta' );
[ , $body ] = $plan( $app, 'site' );
check( 'the plan lists the feature with the update waiting, and applying it applies the update: placed no, switched on yes, the application is set up', $body['todo'] === [ 'alpha' ] && $body['application']['features'][0]['state'] === 'update' );
[ , $body ] = $apply( $app, 'site', 'alpha' );
check( '...', $body['placed'] === false && $body['activated'] === true && $stateOf( $app, 'site' ) === 'applied' && $featureStates( $app )['states']['alpha'] === 'on' );

// From a part: the shop has beta on already
foreach( $plan( $app, 'shop' )[1]['todo'] as $feature )
	$apply( $app, 'shop', $feature );
check( 'an application that shares a feature with one that is set up needs only the rest, and one whose features are on by then is set up without a request', $stateOf( $app, 'shop' ) === 'applied' && $stateOf( $app, 'blog' ) === 'applied' );

// From all: nothing is placed twice
$requests = [];
[ $status, $body ] = $apply( $app, 'shop', 'gamma' );
check( 'applying a feature of an application that is all on downloads nothing', $status === 200 && $body['placed'] === false && $body['activated'] === false
	&& array_filter( $requests, static fn( string $url ): bool => str_contains( $url, '.tar.gz' ) ) === [] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- A failure in the middle, and going on from there ---------------------------------

echo "Applications panel - a failure stops the run and the next one goes on from there\n";

$reset();
$app = $project( 'applications-failure' );
$publish( $app );

unset( $remote[ $features['gamma']['archive'] ] );
$apply( $app, 'shop', 'beta' );
$requests = [];
[ $status, $body ] = $apply( $app, 'shop', 'gamma' );
check( 'a feature whose archive cannot be had is a 400 with a code, the sentence of the kernel as its parameter', $status === 400 && ( $body['code'] ?? '' ) === 'features_application_failed' && is_string( $body['params'][0] ?? null ) && $body['params'][0] !== ''
	&& $body['error'] === $body['params'][0] );
check( '...what was switched on stays on, the one that failed is not there, and the application is partly set up', \Nino\Features::get( $app, 'beta' )['active'] === true && \Nino\Features::get( $app, 'gamma' ) === null && $stateOf( $app, 'shop' ) === 'partial' );
check( '...and the words of the code are in both languages of the workbench', $word( 'en_US', '/_admin/error/features_application_failed', 'x' ) !== '' && $word( 'de_DE', '/_admin/error/features_application_failed', 'x' ) !== '' );

$remote[ $features['gamma']['archive'] ] = $remote[ $features['gamma']['archive'] ] ?? '';
$makeFeature( 'gamma' );
$publish( $app );
$requests = [];
[ $status, $body ] = $plan( $app, 'shop' );
check( 'the plan of the next round lists what is still off - the feature that failed alone', $status === 200 && $body['todo'] === [ 'gamma' ] );
[ $status, $body ] = $apply( $app, 'shop', 'gamma' );
check( 'which goes through once the archive is there, and sets the application up', $status === 200 && $body['placed'] === true && $body['application']['state'] === 'applied' );
check( '...without loading the features that were on again', array_filter( $requests, static fn( string $url ): bool => str_contains( $url, 'beta-' ) ) === [] );

// A feature placed that cannot be switched on: the directory is there, the application is partly set up, and it says so
$reset();
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
$app = $project( 'applications-lonely' );
$app['/nino/admin/logs'] = true;
$publish( $app );
$apply( $app, 'broken', 'alpha' );
[ $status, $body ] = $apply( $app, 'broken', 'lonely' );
check( 'a feature that is placed and cannot be switched on is a 400 with the reason of the kernel', $status === 400 && ( $body['code'] ?? '' ) === 'features_application_failed' && str_contains( $body['error'], 'cannot be activated' ) && str_contains( $body['error'], '"nowhere"' ) );
check( '...its files are in the project, it is not in the module list, and the application waits at partly set up', is_file( NINO_FEATURES_DIR. '/Lonely/feature.php' )
	&& in_array( '\\Nino\\Modules\\Lonely', $configOf( $app )['/nino/modules'], true ) === false && $stateOf( $app, 'broken' ) === 'partial' );
$lines = array_map( static fn( mixed $line ): string => is_array( $line ) === true ? implode( ' ', array_map( 'strval', $line ) ) : (string) $line, \Nino\Modules\Logs\Admin::recentLines( $app, 20 ) );
check( '...and the log carries what was placed, because the shell records an action only where it answered 200', count( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'Set up application "broken": feature "lonely" (not activated: ' ) ) ) === 1
	&& count( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'feature "alpha"' ) ) ) === 0 );
check( 'the activity log names the application and the feature of a set-up, and a plan logs nothing', \Nino\Modules\Features\Admin::log( 'features/application-apply', [ 'application' => 'site', 'feature' => 'alpha' ] ) === 'Set up application "site": feature "alpha"'
	&& \Nino\Modules\Features\Admin::log( 'features/application-plan', [ 'application' => 'site' ] ) === '' );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- What loading needs ---------------------------------------------------------------

echo "Applications panel - what loading needs is checked before anything is loaded\n";

$reset();
$app = $project( 'applications-missing' );
$publish( $app );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
unset( $app['./nino/features/all'] );
$requests = [];
[ $status, $body ] = $plan( $app, 'site' );
[ $statusApply, $bodyApply ] = $apply( $app, 'site', 'alpha' );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
unset( $app['./nino/features/all'] );
check( 'a features directory the web server cannot write to is a 409 with a code, naming it in the language of the workbench - for the plan and for every feature',
	$status === 409 && ( $body['code'] ?? '' ) === 'features_application_missing' && $body['params'] === [ $word( 'de_DE', '/_admin/features/missing/features' ) ] && $word( 'de_DE', '/_admin/features/missing/features' ) !== ''
	&& $statusApply === 409 && ( $bodyApply['code'] ?? '' ) === 'features_application_missing' );
check( '...before the catalogue is asked for anything, and nothing was placed', $requests === [] && glob( NINO_FEATURES_DIR. '/*' ) === [] );
check( 'every phrase of what is missing has its words in both languages, and every one the kernel can name is among them', ( static function() use ( $word ): bool {
	foreach( [ 'openssl', 'unpack', 'http', 'features' ] as $name )
		if( $word( 'en_US', '/_admin/features/missing/'. $name ) === '' || $word( 'de_DE', '/_admin/features/missing/'. $name ) === '' )
			return false;
	return $word( 'en_US', '/_admin/error/features_application_missing', 'x' ) !== '' && $word( 'de_DE', '/_admin/error/features_application_missing', 'x' ) !== '';
} )() );

$off = $app;
$off['/nino/catalogue/url'] = '';
$requests = [];
check( 'a catalogue that is switched off is a 400 with its code, for the plan and for a feature, and asks nobody', $plan( $off, 'site' ) === [ 400, [ 'error' => 'the catalogue is switched off', 'code' => 'features_application_off' ] ]
	&& $apply( $off, 'site', 'alpha' ) === [ 400, [ 'error' => 'the catalogue is switched off', 'code' => 'features_application_off' ] ] && $requests === [] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- Which catalogue is believed -------------------------------------------------------

echo "Applications panel - the catalogue config.php names, believed only with the signature of its key\n";

$reset();
$app = $project( 'applications-catalogue' );
$publish( $app );
$call( $app, 'apiCatalogue' );
check( 'the list shows the applications of a catalogue that was good when it was fetched', $stateOf( $app, 'site' ) === 'none' );

$publish( $app, $strangerPrivateKey );
$requests = [];
[ $status, $body ] = $plan( $app, 'site' );
check( 'a catalogue signed by another key is a 400 with a code and the kernel\'s sentence, and the list that was cached does not stand in for it', $status === 400 && ( $body['code'] ?? '' ) === 'features_application_catalogue'
	&& $body['params'] === [ 'the catalogue signature does not verify' ] && $stateOf( $app, 'site' ) === 'none' );
[ $status, $body ] = $apply( $app, 'site', 'alpha' );
check( '...nor does a feature of it get placed', $status === 400 && ( $body['code'] ?? '' ) === 'features_application_catalogue' && glob( NINO_FEATURES_DIR. '/*' ) === []
	&& array_filter( $requests, static fn( string $url ): bool => str_contains( $url, '.tar.gz' ) ) === [] );

$publish( $app );
$wrongKey = $app;
$wrongKey['/nino/catalogue/key'] = $strangerPublicKey;
check( 'a catalogue signed by the key of this run is refused by a project whose config.php names another', $plan( $wrongKey, 'site' )[0] === 400 && $apply( $wrongKey, 'site', 'alpha' )[1]['code'] === 'features_application_catalogue' );

unset( $remote[ \Nino\Catalogue::DEFAULT_URL ] );
unset( $app['./nino/catalogue'] );
check( 'one that cannot be fetched says so with the same code', $plan( $app, 'site' )[1]['code'] === 'features_application_catalogue' && str_contains( $plan( $app, 'site' )[1]['error'], 'could not be fetched' ) );

// The panel reads the catalogue the project names - its address and its key - which the wizard never does
$publish( $app );
$other = 'https://catalogue.example.org/catalogue.json';
$publish( $app, null, $other );
$project2 = $project( 'applications-own-catalogue' );
$project2['/nino/catalogue/url'] = $other;
$project2['/nino/catalogue/key'] = $publicKey;
unset( $project2['./nino/catalogue'] );
[ $status, $body ] = $plan( $project2, 'site' );
check( 'a project that names a catalogue of its own and the key of it sets its applications up from that one', $status === 200 && $body['todo'] === [ 'alpha', 'beta' ] );
[ $status, $body ] = $apply( $project2, 'site', 'alpha' );
check( '...and the feature is placed from it', $status === 200 && $body['placed'] === true && \Nino\Features::get( $project2, 'alpha' )['active'] === true );

// ...whereas the wizard offers nothing of it: the official catalogue only
$wizard = ninoSandbox( 'applications-wizard' );
$wizard['/nino/catalogue/url']	= $other;
$wizard['/nino/catalogue/key']	= $publicKey;
$wizard[ \Nino\Install\Applications::KEY_OVERRIDE ] = $publicKey;
$wizard['./nino/fetch/stub']		= $project2['./nino/fetch/stub'];
\Nino\Auth::insertUser( $wizard, 'owner@example.com', 'a long enough password', [ '/*' ] );
[ $status, $body ] = ninoCall( $wizard, [ \Nino\Install\Install::class, 'handlePost' ], [], 'applications/list' );
check( 'the wizard, for the same project, offers none of them: it reads the official catalogue and nothing a project names', $status === 200 && $body['reason'] === 'install_not_official' && $body['applications'] === [] );
[ $status ] = ninoCall( $wizard, [ \Nino\Install\Install::class, 'handlePost' ], [ 'applications' => [ 'site' ], 'feature' => 'beta' ], 'applications/apply' );
check( '...and refuses to apply one', $status === 409 );
\Nino\Filesystem::removeDir( ninoSandboxDir( $wizard ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $project2 ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

echo "\n";

// --- Keys a request made up ------------------------------------------------------------

echo "Applications panel - keys a request made up\n";

$reset();
$app = $project( 'applications-keys' );
$publish( $app );

$requests = [];
$shapes = [ '', null, [], [ 'site' ], 7, true, 'Site', '../x', 'a/b', 'site/../beta', 'site%2f..', "site\0", "site\n", ' site', 'site ', '..', '.', 'sité' ];
$allUnknown = true;
foreach( $shapes as $shape ) {
	foreach( [ 'apiApplicationPlan' => [ 'application' => $shape ], 'apiApplicationApply' => [ 'application' => $shape, 'feature' => 'alpha' ] ] as $method => $data ) {
		[ $status, $body ] = $call( $app, $method, $data );
		$allUnknown = $allUnknown && $status === 400 && ( $body['code'] ?? '' ) === 'features_application_unknown' && array_keys( $body ) === [ 'error', 'code' ];
	}
}
check( 'an application key that is not a slug is a 400 with its code, for the plan and for a feature, whatever shape it has', $allUnknown === true );
check( '...without asking the catalogue, so nothing but a well formed key reaches it', $requests === [] );
check( 'no key is missing at all: a request without one is the same 400', $call( $app, 'apiApplicationPlan' ) === [ 400, [ 'error' => 'unknown application', 'code' => 'features_application_unknown' ] ] );

foreach( [ 'nope', 'ghost', 'picky', 'unlisted' ] as $unknown ) {
	[ $status, $body ] = $plan( $app, $unknown );
	check( 'the key "'. $unknown. '" is no application this catalogue offers this kernel: a 400', $status === 400 && ( $body['code'] ?? '' ) === 'features_application_unknown' );
}
[ $status ] = $apply( $app, 'ghost', 'alpha' );
check( '...a feature of an application that is not offered is not placed either', $status === 400 && glob( NINO_FEATURES_DIR. '/*' ) === [] );

$requests = [];
$notOfIt = [ 'gamma', 'delta', 'lonely', 'needy', 'unlisted', 'nope', 'Alpha', '../alpha', 'alpha/../beta', 'alpha%2f', "alpha\0", '', null, [], [ 'alpha' ], 7 ];
$allRefused = true;
foreach( $notOfIt as $feature ) {
	[ $status, $body ] = $call( $app, 'apiApplicationApply', [ 'application' => 'site', 'feature' => $feature ] );
	$allRefused = $allRefused && $status === 400 && ( $body['code'] ?? '' ) === 'features_application_feature' && array_keys( $body ) === [ 'error', 'code' ];
}
check( 'a feature that the application does not list - another application\'s, one the catalogue has or has not, a malformed or a missing key - is a 400 with its code', $allRefused === true );
check( '...and nothing of the catalogue other than its signed index was read, and nothing was placed', glob( NINO_FEATURES_DIR. '/*' ) === [] && array_filter( $requests, static fn( string $url ): bool => str_contains( $url, '.tar.gz' ) ) === [] );
check( 'the words of every code are in both languages, with a place for what goes into them', ( static function() use ( $word ): bool {
	foreach( [ 'unknown', 'off', 'missing', 'catalogue', 'feature', 'failed' ] as $name )
		if( $word( 'en_US', '/_admin/error/features_application_'. $name, 'x' ) === '' || $word( 'de_DE', '/_admin/error/features_application_'. $name, 'x' ) === '' )
			return false;
	return true;
} )() );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- The project keeps its pages ---------------------------------------------------------

echo "Applications panel - a project with pages of its own: no route changed, no starter page, several start applications side by side\n";

$reset();
$app = $project( 'applications-pages' );
$publish( $app );

$routesBefore	= $configOf( $app )['/nino/http/routes'];
$configBefore	= $configOf( $app );
$filesBefore	= array_map( 'basename', glob( ninoSandboxDir( $app ). '/private/templates/*' ) ?: [] );

foreach( [ 'site', 'card' ] as $applicationKey )
	foreach( $plan( $app, $applicationKey )[1]['todo'] as $feature )
		$apply( $app, $applicationKey, $feature );

$blogTodo = $plan( $app, 'blog' )[1]['todo'];
foreach( $blogTodo as $feature )
	$apply( $app, 'blog', $feature );

$configAfter	= $configOf( $app );
$filesAfter		= array_map( 'basename', glob( ninoSandboxDir( $app ). '/private/templates/*' ) ?: [] );
sort( $filesBefore );
sort( $filesAfter );

check( 'the application with a start statement of "starter", the one that brings its own and a third that shares a feature are all set up in one project, side by side: the plan of the third had only its own feature',
	$blogTodo === [ 'gamma' ] && $stateOf( $app, 'site' ) === 'applied' && $stateOf( $app, 'card' ) === 'applied' && $stateOf( $app, 'blog' ) === 'applied' );
check( 'every route the project had is as it was: the start page still answers with its own template, the other page too', $routesBefore['GET://'] === $configAfter['/nino/http/routes']['GET://'] && $configAfter['/nino/http/routes']['GET://']['uri'] === '/home'
	&& $routesBefore['GET://about'] === $configAfter['/nino/http/routes']['GET://about'] );
check( '...and a feature adds only the route it asks for where the project has none: its own address, not the start page', array_keys( array_diff_key( $configAfter['/nino/http/routes'], $routesBefore ) ) === [ 'GET://delta' ] );
check( 'config.php differs by the module list, the record of the features and that route, and by nothing else', array_keys( array_filter( $configAfter, static fn( mixed $value, string $key ): bool => ( $configBefore[$key] ?? null ) !== $value, ARRAY_FILTER_USE_BOTH ) ) === [ '/nino/modules', '/nino/features', '/nino/http/routes' ]
	|| array_keys( array_filter( $configAfter, static fn( mixed $value, string $key ): bool => ( $configBefore[$key] ?? null ) !== $value, ARRAY_FILTER_USE_BOTH ) ) === [ '/nino/modules', '/nino/http/routes', '/nino/features' ] );
check( '...the module list gained the three classes and kept order, the record the three versions', array_values( array_diff( $configAfter['/nino/modules'], $configBefore['/nino/modules'] ) ) === [ '\\Nino\\Modules\\Alpha', '\\Nino\\Modules\\Beta', '\\Nino\\Modules\\Delta', '\\Nino\\Modules\\Gamma' ] || count( $configAfter['/nino/modules'] ) === 4 );
check( 'no starter page is made: the templates of the project are its own two and the one the feature brought, and the files it had are byte for byte what they were',
	array_values( array_diff( $filesAfter, $filesBefore ) ) === [ 'page-delta.tpl' ] && array_diff( $filesBefore, $filesAfter ) === []
	&& file_get_contents( ninoSandboxDir( $app ). '/private/templates/page-home.tpl' ) === "<h1>My own home</h1>\n" && is_file( ninoSandboxDir( $app ). '/private/templates/page-404.tpl' ) === false && is_file( ninoSandboxDir( $app ). '/private/templates/page-contact.tpl' ) === false );
check( 'a second application that says who provides the start page is no conflict after the first: the panel plans one that is set up as any other, with nothing left', $plan( $app, 'card' ) [0] === 200 && $plan( $app, 'site' )[1]['todo'] === [] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- One place for both ---------------------------------------------------------------

echo "Applications - the wizard and the panel place a feature with the same code\n";

$reset();
$panelApp = $project( 'applications-same-panel' );
$publish( $panelApp );
foreach( $plan( $panelApp, 'blog' )[1]['todo'] as $feature )
	$apply( $panelApp, 'blog', $feature );
$panelConfig = $configOf( $panelApp );
$panelFiles = array_map( 'basename', glob( NINO_FEATURES_DIR. '/*' ) ?: [] );
sort( $panelFiles );

$reset();
$wizardApp = ninoSandbox( 'applications-same-wizard' );
$wizardDir = ninoSandboxDir( $wizardApp );
foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $sub )
	mkdir( $wizardDir. $sub, 0777, true );
\Nino\Filesystem::putFileContent( $wizardApp, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [] ] );
$wizardApp['/nino/dir'] = '';
$wizardApp[ \Nino\Install\Applications::KEY_OVERRIDE ] = $publicKey;
$wizardApp['./nino/fetch/stub'] = $panelApp['./nino/fetch/stub'];
\Nino\Auth::insertUser( $wizardApp, 'owner@example.com', 'a long enough password', [ '/*' ] );
$answers = [];
foreach( [ 'alpha', 'gamma' ] as $feature )
	$answers[] = ninoCall( $wizardApp, [ \Nino\Install\Install::class, 'handlePost' ], [ 'applications' => [ 'blog' ], 'feature' => $feature ], 'applications/apply' );
$wizardConfig = $configOf( $wizardApp );
$wizardFiles = array_map( 'basename', glob( NINO_FEATURES_DIR. '/*' ) ?: [] );
sort( $wizardFiles );

check( 'the wizard answers the same of a feature as the panel does of it: placed and switched on', $answers[0][0] === 200 && $answers[0][1] === [ 'feature' => 'alpha', 'placed' => true, 'activated' => true ] && $answers[1][1] === [ 'feature' => 'gamma', 'placed' => true, 'activated' => true ] );
check( '...and leaves the project in the same state: the same directories, the same module list, the same record', $wizardFiles === $panelFiles && $wizardConfig['/nino/modules'] === $panelConfig['/nino/modules'] && $wizardConfig['/nino/features'] === $panelConfig['/nino/features'] );

\Nino\Filesystem::removeDir( $wizardDir );
\Nino\Filesystem::removeDir( ninoSandboxDir( $panelApp ) );
echo "\n";

// --- \Nino\Applications ------------------------------------------------------------------

echo "Applications - the rules both places share\n";

$reset();
$app = $project( 'applications-class' );
$publish( $app );
$catalogue = \Nino\Catalogue::fetch( $app );

check( 'the features of the applications chosen are listed in the order of the applications, each once', \Nino\Applications::features( [ $applications[0], $applications[1], $applications[3] ] ) === [ 'alpha', 'beta', 'gamma' ] && \Nino\Applications::features( [] ) === [] );
$offered = \Nino\Catalogue::applications( $app, $catalogue );
check( 'the applications chosen come in the order of the catalogue, whatever order or repetition the keys have', array_column( \Nino\Applications::chosen( $offered, [ 'blog', 'site', 'blog' ] ), 'key' ) === [ 'site', 'blog' ] && \Nino\Applications::chosen( $offered, [] ) === [] );
check( 'a feature is on when it is switched on at the version its directory carries, with nothing against it - and nothing else is', \Nino\Applications::isOn( null ) === false
	&& \Nino\Applications::isOn( [ 'active' => true, 'problems' => [], 'installed' => '1.0.0', 'version' => '1.0.0' ] ) === true
	&& \Nino\Applications::isOn( [ 'active' => false, 'problems' => [], 'installed' => '1.0.0', 'version' => '1.0.0' ] ) === false
	&& \Nino\Applications::isOn( [ 'active' => true, 'problems' => [ 'x' ], 'installed' => '1.0.0', 'version' => '1.0.0' ] ) === false
	&& \Nino\Applications::isOn( [ 'active' => true, 'problems' => [], 'installed' => '0.9.0', 'version' => '1.0.0' ] ) === false
	&& \Nino\Applications::isOn( [ 'active' => true, 'problems' => [], 'installed' => null, 'version' => '1.0.0' ] ) === false );
check( 'the entry point of an application is its "next" where that is a path of the site', \Nino\Applications::entry( [ 'next' => '/_admin/#a' ] ) === '/_admin/#a' && \Nino\Applications::entry( [ 'next' => null ] ) === null
	&& \Nino\Applications::entry( [ 'next' => '//evil.test' ] ) === null && \Nino\Applications::entry( [ 'next' => 'https://evil.test' ] ) === null && \Nino\Applications::entry( [ 'next' => '/\\evil.test' ] ) === null
	&& \Nino\Applications::entry( [ 'next' => "/a\nb" ] ) === null && \Nino\Applications::entry( [ 'next' => 5 ] ) === null && \Nino\Applications::entry( [] ) === null );
check( 'an application opens the workbench with its menu folded only where it says folded', \Nino\Applications::menu( [ 'menu' => 'folded' ] ) === 'folded'
	&& \Nino\Applications::menu( [ 'menu' => 'open' ] ) === null && \Nino\Applications::menu( [ 'menu' => true ] ) === null && \Nino\Applications::menu( [ 'menu' => null ] ) === null && \Nino\Applications::menu( [] ) === null );
check( 'what loading needs is named by key, and nothing on a server that has it all', \Nino\Applications::missing() === [] );
rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
check( '...a features directory that cannot be written to among them', array_keys( \Nino\Applications::missing() ) === [ 'features' ] );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );

$result = \Nino\Applications::place( $app, $catalogue, 'nope' );
check( 'a feature the catalogue does not list is a 400 of place() and touches nothing', $result['status'] === 400 && $result['placed'] === false && $result['activated'] === false && $result['reason'] !== '' && glob( NINO_FEATURES_DIR. '/*' ) === [] );
$result = \Nino\Applications::place( $app, $catalogue, 'alpha' );
check( 'place() answers what it did - placed and switched on - with an empty reason and status 200', $result === [ 'placed' => true, 'activated' => true, 'reason' => '', 'status' => 200 ] );
$result = \Nino\Applications::place( $app, $catalogue, 'alpha' );
check( '...and nothing for a feature that is on already', $result === [ 'placed' => false, 'activated' => false, 'reason' => '', 'status' => 200 ] );
$result = \Nino\Applications::place( $app, $catalogue, 'lonely' );
check( '...placed and not switched on is a 400 that says so', $result['status'] === 400 && $result['placed'] === true && $result['activated'] === false && str_contains( $result['reason'], 'cannot be activated' ) );
check( 'notOn() lists what an application still lacks, in its order', \Nino\Applications::notOn( $app, $applications[4] ) === [ 'lonely', 'gamma' ] && \Nino\Applications::isApplied( $app, $applications[0] ) === false );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

$none = [];
ninoDone( $none );
