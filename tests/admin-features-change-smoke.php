<?php
declare(strict_types=1);

/**
 *	Nino
 *	admin-features-change-smoke.php	Contract test for the changes of a feature's state - placing a
 *											directory, switching on, switching off, removing, applying an
 *											update, setting an application up - which run one at a time,
 *											and for the answers of those actions that name no path of this
 *											server. The lock: a change that finds it taken answers 409
 *											with a code and touches nothing, in the panel, in the kernel's
 *											steps and in the setup wizard; a change gives it back however
 *											it ends, and a process that dies holding it does not keep it.
 *											A request that booted before another session changed a feature
 *											decides on the state as it is when it takes the lock: a
 *											directory removed behind its back is not switched on, a
 *											feature switched on behind its back is not removed, and
 *											switching one off does not undo what another switched on.
 *											The answers: every way a download, an archive or the
 *											filesystem can fail is a sentence without a path of the
 *											project or of the temp directory, and the log has the rest.
 *
 *											No network: a stub under './nino/fetch/stub' answers from a
 *											map of urls. The archives are built here, byte by byte.
 *
 *	Usage: php tests/admin-features-change-smoke.php
 */

// Before the kernel loads: the features land in a directory of this run's own
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-features-change-features-'. uniqid() );
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
// One that is placed and cannot be switched on: it requires a feature that is not there
$makeFeature( 'lonely', [], ", 'requires' => [ 'nowhere' ]", [ 'requires' => [] ] );
// One whose catalogue entry requires alpha: planning its install looks at the features that are there
$makeFeature( 'needs', [], '', [ 'requires' => [ 'alpha' ] ] );
// A feature whose unit throws when it is switched on: a change that dies in the middle
$makeFeature( 'boom', [ 'install/manifest.php' => '<?php throw new \\RuntimeException( "the unit of boom" );' ] );

$applications = [
	[ 'key' => 'site', 'name' => [ 'en_US' => 'Site' ], 'description' => [ 'en_US' => 'A site' ], 'features' => [ 'alpha', 'beta' ], 'next' => '/', 'start' => null ],
	[ 'key' => 'solo', 'name' => [ 'en_US' => 'Solo' ], 'description' => [ 'en_US' => 'Just alpha' ], 'features' => [ 'alpha' ], 'next' => '/', 'start' => null ],
];

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


/** Another process, as far as the lock can tell: its own handles on the same lock file */
$elsewhere = static function( array $app ): array {
	$app['./nino/filesystem/locks'] = [];
	unset( $app['./nino/features/lock'], $app['./nino/features/all'] );
	return $app;
};

/** What a change could have touched, as it is on disk: config.php, the directories, the archives asked for */
$snapshot = static function( array &$app ) use ( &$requests ): array {
	$names = array_map( 'basename', glob( NINO_FEATURES_DIR. '/*' ) ?: [] );
	sort( $names );
	return [ (string) file_get_contents( ninoSandboxDir( $app ). '/private/config.php' ), $names, array_values( array_filter( $requests, static fn( string $url ): bool => str_ends_with( $url, '.tar.gz' ) ) ) ];
};

/** A feature of the catalogue whose archive is the bytes given, and the entry's own fields as given */
$rawFeature = static function( string $key, string $bytes, array $entry = [] ) use ( &$remote, &$features ): void {
	$directory = ucfirst( $key );
	$url = 'https://catalogue.test/features/'. $key. '-1.0.0.tar.gz';
	$remote[$url] = $bytes;
	$features[$key] = $entry + [ 'key' => $key, 'name' => [ 'en_US' => $directory ], 'description' => 'The '. $key, 'version' => '1.0.0', 'nino' => '^1.0', 'php' => [ 'ext' => [] ],
		'requires' => [], 'directory' => $directory, 'archive' => $url, 'sha256' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ), 'released' => '2026-11-13' ];
};

$listed = static function( array &$app, string $class ): bool {
	$config = (array) \Nino\Filesystem::getFileContent( $app, '/config.php', [] );
	return in_array( $class, array_map( static fn( mixed $c ): string => '\\'. ltrim( (string) $c, '\\' ), (array) ( $config['/nino/modules'] ?? [] ) ), true );
};

$place = static function( array &$app, string $key, bool $on = false ): void {
	$result = \Nino\Catalogue::install( $app, $key, '1.0.0' );
	unset( $app['./nino/features/all'] );
	if( $result === true && $on === true )
		$result = \Nino\Features::activate( $app, $key );
	if( $result !== true )
		throw new \RuntimeException( 'setup: '. $result );
};

// --- One change at a time ---------------------------------------------------------

echo "Features panel - a change that finds the lock taken answers busy and touches nothing\n";

$reset();
$app = $project( 'change-busy' );
$publish( $app );
$place( $app, 'alpha' );
$catalogue = \Nino\Catalogue::fetch( $app );

$holder = $elsewhere( $app );
check( 'another holder takes the lock', \Nino\Features::lock( $holder ) === true );
$requests = [];
$before = $snapshot( $app );

$answers = [
	'activate'		=> $call( $app, 'apiActivate', [ 'key' => 'alpha' ] ),
	'deactivate'	=> $call( $app, 'apiDeactivate', [ 'key' => 'alpha' ] ),
	'remove'			=> $call( $app, 'apiRemove', [ 'key' => 'alpha' ] ),
	'install'			=> $call( $app, 'apiInstall', [ 'key' => 'gamma', 'version' => '1.0.0' ] ),
	'apply'				=> $apply( $app, 'solo', 'alpha' ),
	'settings'		=> $call( $app, 'apiSettings', [ 'key' => 'alpha', 'fields' => [ 'x' => '1' ] ] ),
];
foreach( $answers as $name => [ $status, $body ] )
	check( $name. ': 409 with the code of a busy lock, and nothing else in the answer but the sentence', $status === 409 && ( $body['code'] ?? '' ) === 'features_busy' && array_keys( $body ) === [ 'error', 'code' ] );
check( '...none of them changed config.php, a directory or asked for an archive', $snapshot( $app ) === $before );

check( 'the kernel\'s steps refuse alike: switching on, off, removing, installing, saving settings', \Nino\Features::activate( $app, 'alpha' ) === \Nino\Features::BUSY
	&& \Nino\Features::deactivate( $app, 'alpha' ) === \Nino\Features::BUSY
	&& \Nino\Features::remove( $app, 'alpha' ) === \Nino\Features::BUSY
	&& \Nino\Catalogue::install( $app, 'gamma', '1.0.0' ) === \Nino\Features::BUSY
	&& \Nino\Features::saveSettings( $app, 'alpha', [] ) === [ '' => \Nino\Features::BUSY ] );
$placed = \Nino\Applications::place( $app, $catalogue, 'alpha' );
check( '...and so does the step both the panel and the wizard set an application up with: status 409, nothing placed', $placed['status'] === 409 && $placed['placed'] === false && $placed['activated'] === false );
check( '...with config.php, the directories and the archives as they were', $snapshot( $app ) === $before );

// The wizard: its own project, the same lock
$wizardApp = ninoSandbox( 'change-wizard' );
$wizardDir = ninoSandboxDir( $wizardApp );
foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $sub )
	mkdir( $wizardDir. $sub, 0777, true );
\Nino\Filesystem::putFileContent( $wizardApp, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [] ] );
$wizardApp['/nino/dir'] = '';
$wizardApp[ \Nino\Install\Applications::KEY_OVERRIDE ] = $publicKey;
$wizardApp['./nino/fetch/stub'] = $app['./nino/fetch/stub'];
\Nino\Auth::insertUser( $wizardApp, 'owner@example.com', 'a long enough password', [ '/*' ] );
$wizardHolder = $elsewhere( $wizardApp );
\Nino\Features::lock( $wizardHolder );
[ $status, $body ] = array_slice( ninoCall( $wizardApp, [ \Nino\Install\Install::class, 'handlePost' ], [ 'applications' => [ 'solo' ], 'feature' => 'alpha' ], 'applications/apply' ), 0, 2 );
check( 'the setup wizard answers 409 with a code of its own and places nothing', $status === 409 && ( $body['code'] ?? '' ) === 'install_busy' && glob( NINO_FEATURES_DIR. '/*' ) === array_map( static fn( string $name ): string => NINO_FEATURES_DIR. '/'. $name, [ 'Alpha' ] ) );
\Nino\Features::unlock( $wizardHolder );
[ $status, $body ] = array_slice( ninoCall( $wizardApp, [ \Nino\Install\Install::class, 'handlePost' ], [ 'applications' => [ 'solo' ], 'feature' => 'alpha' ], 'applications/apply' ), 0, 2 );
check( '...and the same request goes through once the lock is free', $status === 200 && ( $body['feature'] ?? '' ) === 'alpha' );
$wizardLock = $wizardDir. '/private/data/.locks/'. sha1( \Nino\Features::LOCK_PATH ). '.lock';
@unlink( $wizardLock );
mkdir( $wizardLock );
ninoWarnings();
[ $status, $body ] = array_slice( ninoCall( $wizardApp, [ \Nino\Install\Install::class, 'handlePost' ], [ 'applications' => [ 'solo' ], 'feature' => 'alpha' ], 'applications/apply' ), 0, 2 );
check( 'a lock file that cannot be opened is a 500 of the setup wizard with a code of its own', $status === 500 && ( $body['code'] ?? '' ) === 'install_lock' );
ninoWarnings();
rmdir( $wizardLock );
\Nino\Features::lock( $wizardHolder );
[ $status, $body ] = array_slice( ninoCall( $wizardApp, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] ), 0, 2 );
check( 'the wizard\'s step that writes the module list is a change of the features once there are features: it answers 409 with the code while another holds the lock', $status === 409 && ( $body['code'] ?? '' ) === 'install_busy' );
\Nino\Features::unlock( $wizardHolder );
\Nino\Filesystem::removeDir( $wizardDir );

\Nino\Features::unlock( $holder );
[ $status ] = $call( $app, 'apiActivate', [ 'key' => 'alpha' ] );
check( 'with the lock free the same action goes through', $status === 200 && $listed( $app, '\\Nino\\Modules\\Alpha' ) === true );
check( '...and gives the lock back: another holder takes it at once', \Nino\Features::lock( $holder ) === true );
\Nino\Features::unlock( $holder );

[ $status ] = $call( $app, 'apiInstall', [ 'key' => 'gamma', 'version' => '1.0.0' ] );
check( 'an install that places a directory and switches it on - three takers of the lock one inside the other - gives it back as well', $status === 200 && $listed( $app, '\\Nino\\Modules\\Gamma' ) === true && \Nino\Features::lock( $holder ) === true );
\Nino\Features::unlock( $holder );

$threw = false;
try { \Nino\Applications::place( $app, $catalogue, 'boom' ); }
catch( \Throwable ) { $threw = true; }
check( 'a change that dies in the middle (a unit that throws) gives the lock back', $threw === true && \Nino\Features::lock( $holder ) === true );
\Nino\Features::unlock( $holder );
check( '...and the request that took it more than once holds none afterwards', ( $app['./nino/features/lock'] ?? 0 ) === 0 );

check( 'a request takes the lock twice, one change inside another', \Nino\Features::lock( $app ) === true && \Nino\Features::lock( $app ) === true );
\Nino\Features::unlock( $app );
check( '...and it is held until the outer one gives it back', \Nino\Features::lock( $holder ) === \Nino\Features::BUSY );
\Nino\Features::unlock( $app );
check( '...and free after that', \Nino\Features::lock( $holder ) === true );
\Nino\Features::unlock( $holder );

\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- A request that booted before another session changed a feature ------------------

echo "Features panel - the decision is made on the state at the time of the lock\n";

$reset();
$app = $project( 'change-stale-on' );
$publish( $app );
$place( $app, 'alpha' );
\Nino\Features::all( $app );
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR. '/Alpha' );
[ $status ] = $call( $app, 'apiActivate', [ 'key' => 'alpha' ] );
check( 'switching on a feature whose directory another session removed meanwhile is refused', $status === 400 );
check( '...and config.php lists no class whose directory is gone', $listed( $app, '\\Nino\\Modules\\Alpha' ) === false );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

$reset();
$app = $project( 'change-stale-remove' );
$publish( $app );
$place( $app, 'alpha' );
\Nino\Features::all( $app );
$other = $elsewhere( $app );
$switched = \Nino\Features::activate( $other, 'alpha' );
[ $status ] = $call( $app, 'apiRemove', [ 'key' => 'alpha' ] );
check( 'removing a feature another session switched on meanwhile is refused, and its directory stays', $switched === true && $status === 400 && is_file( NINO_FEATURES_DIR. '/Alpha/feature.php' ) === true && $listed( $app, '\\Nino\\Modules\\Alpha' ) === true );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

$reset();
$app = $project( 'change-stale-off' );
$publish( $app );
$place( $app, 'alpha', true );
$place( $app, 'beta', true );
\Nino\Features::all( $app );
$other = $elsewhere( $app );
$place( $other, 'gamma', true );
[ $status ] = $call( $app, 'apiDeactivate', [ 'key' => 'alpha' ] );
check( 'switching one feature off does not undo what another session switched on meanwhile', $status === 200 && $listed( $app, '\\Nino\\Modules\\Alpha' ) === false && $listed( $app, '\\Nino\\Modules\\Beta' ) === true && $listed( $app, '\\Nino\\Modules\\Gamma' ) === true );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- The state a change reads, changes and writes is the file's ----------------------------

echo "Features panel - nothing is written back that config.php does not carry\n";

// A request booted while alpha was on; another session switched it off and removed it; this request switches beta off
$reset();
$app = $project( 'change-gone' );
$publish( $app );
$place( $app, 'alpha', true );
$place( $app, 'beta', true );
\Nino\Features::all( $app );
$other = $elsewhere( $app );
$steps = [ \Nino\Features::deactivate( $other, 'alpha' ), \Nino\Features::remove( $other, 'alpha' ) ];
[ $status ] = $call( $app, 'apiDeactivate', [ 'key' => 'beta' ] );
check( 'a feature another session switched off and removed meanwhile is not listed again by the next switching off', $steps === [ true, true ] && $status === 200
	&& $listed( $app, '\\Nino\\Modules\\Alpha' ) === false && $listed( $app, '\\Nino\\Modules\\Beta' ) === false && is_dir( NINO_FEATURES_DIR. '/Alpha' ) === false );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

// A feature with an upgrade hook and a setting; the directory is replaced by the next version by hand
$hooked = static function( string $version ): void {
	$dir = NINO_FEATURES_DIR. '/Hooked';
	is_dir( $dir ) || mkdir( $dir, 0777, true );
	file_put_contents( $dir. '/feature.php', '<?php return [ \'key\' => \'hooked\', \'name\' => \'Hooked\', \'version\' => \''. $version. '\', \'settings\' => [ \'limit\' => [ \'type\' => \'int\', \'default\' => 1 ] ] ];' );
	file_put_contents( $dir. '/Hooked.php', '<?php namespace Nino\\Modules { class Hooked { public static function upgrade( array &$appData, string $from ): bool { file_put_contents( NINO_FEATURES_DIR. ".upgrades", $from. "\n", FILE_APPEND ); return true; } } }' );
};
$upgrades = static fn(): array => is_file( NINO_FEATURES_DIR. '.upgrades' ) ? array_values( array_filter( explode( "\n", (string) file_get_contents( NINO_FEATURES_DIR. '.upgrades' ) ) ) ) : [];

$reset();
@unlink( NINO_FEATURES_DIR. '.upgrades' );
$app = $project( 'change-record' );
$publish( $app );
$hooked( '1.0.0' );
$activated = \Nino\Features::activate( $app, 'hooked' );
\Nino\Features::all( $app );
$hooked( '1.1.0' );
$stale = $app;
$other = $elsewhere( $app );
$updated = \Nino\Features::activate( $other, 'hooked' );
$again = \Nino\Features::activate( $app, 'hooked' );
check( 'two sessions applying the same update run the upgrade hook once: the second reads the version the first recorded when it takes the lock', $activated === true && $updated === true && $again === true && $upgrades() === [ '1.0.0' ] );

[ $status ] = $call( $stale, 'apiSettings', [ 'key' => 'hooked', 'fields' => [ 'limit' => '5' ] ] );
$record = (array) ( ( (array) \Nino\Filesystem::getFileContent( $app, '/config.php', [] ) )['/nino/features'] ?? [] );
check( 'saving settings from a request that booted before the update keeps the version the update recorded, and only changes the settings', $status === 200 && ( $record['hooked']['version'] ?? '' ) === '1.1.0' && ( $record['hooked']['settings']['limit'] ?? null ) === 5 );

// config.php rewritten with the same size in the same second: the cached copy of a request cannot tell
$config = ninoSandboxDir( $app ). '/private/config.php';
$app2 = $elsewhere( $app );
\Nino\Filesystem::getFileContent( $app2, '/config.php', [] );
$mtime = filemtime( $config );
file_put_contents( $config, str_replace( "'version' => '1.1.0'", "'version' => '1.2.0'", (string) file_get_contents( $config ) ) );
touch( $config, $mtime );
clearstatcache();
check( 'the lock reads config.php from the disk, not from the cached copy a rewrite of the same size and time would leave', \Nino\Features::lock( $app2 ) === true && ( $app2['/nino/features']['hooked']['version'] ?? '' ) === '1.2.0' );
\Nino\Features::unlock( $app2 );
@unlink( NINO_FEATURES_DIR. '.upgrades' );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- A lock that cannot be had, and a change that dies ------------------------------------

echo "Features panel - a lock file that cannot be opened is no busy lock; every way out gives the lock back\n";

$reset();
$app = $project( 'change-nolock' );
$publish( $app );
$place( $app, 'alpha' );
$lockFile = ninoSandboxDir( $app ). '/private/data/.locks/'. sha1( \Nino\Features::LOCK_PATH ). '.lock';
is_dir( dirname( $lockFile ) ) || mkdir( dirname( $lockFile ), 0777, true );
@unlink( $lockFile );
mkdir( $lockFile );
ninoWarnings();
[ $status, $body ] = $call( $app, 'apiActivate', [ 'key' => 'alpha' ] );
check( 'a lock file that cannot be opened (a directory in its place) is a 500 with a code of its own, and nothing changes', $status === 500 && ( $body['code'] ?? '' ) === 'features_lock_failed' && $listed( $app, '\\Nino\\Modules\\Alpha' ) === false );
$warnings = ninoWarnings();
check( '...the kernel\'s steps say the same, and the log names where to look', \Nino\Features::activate( $app, 'alpha' ) === \Nino\Features::UNLOCKABLE && \Nino\Catalogue::install( $app, 'gamma', '1.0.0' ) === \Nino\Features::UNLOCKABLE
	&& count( $warnings ) === 1 && str_contains( $warnings[0], '.locks' ) === true );
rmdir( $lockFile );
$holder = $elsewhere( $app );
check( 'with the file in order again, a taken lock is busy and a free one is had', \Nino\Features::lock( $holder ) === true && \Nino\Features::lock( $app ) === \Nino\Features::BUSY );
\Nino\Features::unlock( $holder );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

// A manifest that throws: every look at the features dies, in the middle of the change
$reset();
$app = $project( 'change-dies' );
$publish( $app );
$place( $app, 'alpha', true );
$holder = $elsewhere( $app );
mkdir( NINO_FEATURES_DIR. '/Throws' );
file_put_contents( NINO_FEATURES_DIR. '/Throws/feature.php', '<?php throw new \RuntimeException( "a manifest that dies" );' );
unset( $app['./nino/features/all'] );
$dies = [
	'switching off'			=> static fn() => \Nino\Features::deactivate( $app, 'alpha' ),
	'removing'					=> static fn() => \Nino\Features::remove( $app, 'alpha' ),
	'installing'				=> static fn() => \Nino\Catalogue::install( $app, 'needs', '1.0.0' ),
	'the panel\'s change'	=> function() use ( &$app, $call ) { return $call( $app, 'apiDeactivate', [ 'key' => 'alpha' ] ); },
	'saving settings'		=> static fn() => \Nino\Features::saveSettings( $app, 'alpha', [] ),
];
$kept = [];
foreach( $dies as $name => $change ) {
	$threw = false;
	try { $change(); }
	catch( \RuntimeException ) { $threw = true; }
	if( $threw === false || \Nino\Features::lock( $holder ) !== true )
		$kept[] = $name;
	\Nino\Features::unlock( $holder );
}
check( 'a change that dies in the middle gives the lock back: switching off, removing, installing, the panel\'s change, saving settings', $kept === [] );
unlink( NINO_FEATURES_DIR. '/Throws/feature.php' );
rmdir( NINO_FEATURES_DIR. '/Throws' );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );

// Every refusal of these actions carries a code
$reset();
$app = $project( 'change-codes' );
$publish( $app );
$place( $app, 'alpha', true );
[ $installStatus, $installBody ] = $call( $app, 'apiInstall', [ 'key' => 'lonely', 'version' => '1.0.0' ] );
$refusals = [
	'activate, no such feature'		=> $call( $app, 'apiActivate', [ 'key' => 'nope' ] ),
	'deactivate, no key'					=> $call( $app, 'apiDeactivate', [] ),
	'remove, no such feature'			=> $call( $app, 'apiRemove', [ 'key' => 'nope' ] ),
	'remove, switched on'					=> $call( $app, 'apiRemove', [ 'key' => 'alpha' ] ),
	'install, no version'					=> $call( $app, 'apiInstall', [ 'key' => 'alpha' ] ),
	'install, not in the catalogue'	=> $call( $app, 'apiInstall', [ 'key' => 'alpha', 'version' => '9.9.9' ] ),
	'activate, cannot be'					=> $call( $app, 'apiActivate', [ 'key' => 'lonely' ] ),
	'install, cannot be switched on'	=> [ $installStatus, $installBody ],
	'settings, no such feature'		=> $call( $app, 'apiSettings', [ 'key' => 'nope', 'fields' => [] ] ),
];
\Nino\Features::deactivate( $app, 'lonely' );
$plain = [];
foreach( $refusals as $name => [ $status, $body ] )
	if( $status < 400 || preg_match( '/^[a-z][a-z0-9_]*$/', (string) ( $body['code'] ?? '' ) ) !== 1 )
		$plain[] = $name;
check( 'every refusal of an install, a switching on or off, a removal and a save carries a code', $plain === [] );
check( 'a switched on feature is refused its removal with a code of its own, not as a directory that cannot be removed', ( $refusals['remove, switched on'][1]['code'] ?? '' ) === 'features_remove_active' );
check( 'an install that places a feature it cannot switch on says so with a code of its own', $installStatus === 400 && ( $installBody['code'] ?? '' ) === 'features_install_inactive' );
\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
echo "\n";

// --- Another process ---------------------------------------------------------------------

echo "Features panel - a process that holds the lock, and one that dies holding it\n";

$reset();
$app = $project( 'change-process' );
$publish( $app );
$place( $app, 'alpha' );
$sandbox = ninoSandboxDir( $app );

$script = $sandbox. '/hold.php';
file_put_contents( $script, "<?php\ndefine( 'NINO_FEATURES_DIR', \$argv[1] );\nrequire \$argv[2];\n"
	. "\$app = [ './nino/uid' => \$argv[3] ];\n\\Nino\\AppData::prepare( \$app );\n"
	. "\$app['./nino/filesystem/path'] = \$argv[3];\n\$app['./nino/filesystem/configpath'] = \$argv[3]. '/private';\n\$app['./nino/filesystem/contentpath'] = \$argv[3]. '/private';\n\$app['./nino/filesystem/publicpath'] = \$argv[3]. '/public';\n"
	. "echo \\Nino\\Features::lock( \$app ) === true ? \"locked\\n\" : \"busy\\n\";\nflush();\nsleep( 60 );\n" );

$child = proc_open( [ PHP_BINARY, $script, NINO_FEATURES_DIR, __DIR__. '/harness.php', $sandbox ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
stream_set_timeout( $pipes[1], 20 );
$said = trim( (string) fgets( $pipes[1] ) );
check( 'a process of its own takes the lock', is_resource( $child ) === true && $said === 'locked' );

$before = $snapshot( $app );
[ $status, $body ] = $call( $app, 'apiActivate', [ 'key' => 'alpha' ] );
check( 'while it holds it, a change in this process is a 409 with the code, and nothing changes', $status === 409 && ( $body['code'] ?? '' ) === 'features_busy' && $snapshot( $app ) === $before );

proc_terminate( $child, 9 );
proc_close( $child );
[ $status ] = $call( $app, 'apiActivate', [ 'key' => 'alpha' ] );
check( 'the process is killed in the lock: the next change goes through, no lock is left behind', $status === 200 && $listed( $app, '\\Nino\\Modules\\Alpha' ) === true );
\Nino\Filesystem::removeDir( $sandbox );
echo "\n";

// --- What the answers of these actions say about this server ------------------------------

echo "Features panel - no answer names a path of this server\n";

$tarFiles = static fn( array $extra = [], array $without = [] ): array => $extra + array_diff_key( [
	'Alpha/'					=> null,
	'Alpha/feature.php'	=> '<?php return [ \'key\' => \'alpha\', \'name\' => \'Alpha\', \'version\' => \'1.0.0\' ];',
	'Alpha/Alpha.php'		=> '<?php namespace Nino\\Modules { class Alpha { public const string VERSION = \'1.0.0\'; } }',
], array_flip( $without ) );

$many = [];
for( $i = 0; $i < 5100; $i++ )
	$many['Alpha/f'. $i. '.txt'] = 'x';

// case => [ the bytes of the archive, fields of the entry that differ, what to do to the project first ]
$cases = [
	'bytes that are no archive'				=> [ str_repeat( 'not an archive at all ', 50 ), [], null, true ],
	'an archive cut short'						=> [ substr( $tar( $tarFiles() ), 0, 60 ), [], null, true ],
	'an empty archive'								=> [ $tar( [] ), [], null, false ],
	'a digest that does not match'		=> [ $tar( $tarFiles() ), [ 'sha256' => str_repeat( 'a', 64 ) ], null, false ],
	'a size that does not match'			=> [ $tar( $tarFiles() ), [ 'size' => 1234567 ], null, false ],
	'an entry beside the directory'		=> [ $tar( $tarFiles( [ 'Other/x.txt' => 'x' ] ) ), [], null, false ],
	'an entry that climbs out'				=> [ $tar( $tarFiles( [ 'Alpha/../evil.txt' => 'x' ] ) ), [], null, false ],
	'more entries than allowed'				=> [ $tar( $tarFiles( $many ) ), [], null, false ],
	'no manifest'											=> [ $tar( $tarFiles( [], [ 'Alpha/feature.php' ] ) ), [], null, false ],
	'a manifest that does not parse'	=> [ $tar( $tarFiles( [ 'Alpha/feature.php' => '<?php return [ ;' ] ) ), [], null, false ],
	'another feature than promised'		=> [ $tar( $tarFiles( [ 'Alpha/feature.php' => '<?php return [ \'key\' => \'zeta\', \'name\' => \'Z\', \'version\' => \'1.0.0\' ];' ] ) ), [], null, false ],
	'a file where the directory goes'	=> [ $tar( [ 'Alpha' => 'x' ] ), [], null, false ],
	'an archive that is not there'		=> [ 'x', [ 'archive' => 'https://catalogue.test/features/missing.tar.gz' ], null, false ],
	'a staging directory that cannot be made' => [ $tar( $tarFiles() ), [], static function( string $dir ): void { mkdir( $dir. '/private/data', 0777, true ); file_put_contents( $dir. '/private/data/.features', 'a file where the directory goes' ); }, false ],
	'a unit that cannot be copied'		=> [ $tar( $tarFiles( [ 'Alpha/install/' => null, 'Alpha/install/manifest.php' => '<?php return [ \'templates\' => [ \'page-x.tpl\' ] ];', 'Alpha/install/templates/page-x.tpl' => 'x' ] ) ), [], static function( string $dir ): void { mkdir( $dir. '/private/templates/page-x.tpl', 0777, true ); }, false ],
];

$leak = static function( mixed $body, string $sandbox ): bool {
	$json = (string) json_encode( $body, JSON_UNESCAPED_SLASHES );
	foreach( [ $sandbox, NINO_FEATURES_DIR, sys_get_temp_dir(), 'phar://' ] as $needle )
		if( $needle !== '' && str_contains( $json, $needle ) === true )
			return true;
	return false;
};

foreach( [ 'apiInstall', 'apiApplicationApply' ] as $method ) {

	$failures = [];
	$leaks = [];
	$logged = [];

	foreach( $cases as $name => [ $bytes, $entry, $prepare, $names ] ) {

		$reset();
		$app = $project( 'change-answers' );
		$sandbox = ninoSandboxDir( $app );
		$features = [];
		$rawFeature( 'alpha', $bytes, $entry );
		$publish( $app );
		if( $prepare !== null )
			$prepare( $sandbox );
		ninoWarnings();

		[ $status, $body ] = $method === 'apiInstall'
			? $call( $app, 'apiInstall', [ 'key' => 'alpha', 'version' => '1.0.0' ] )
			: $apply( $app, 'solo', 'alpha' );
		$warnings = ninoWarnings();

		if( $status < 400 )
			$failures[] = $name;
		if( $leak( $body, $sandbox ) === true )
			$leaks[] = $name;
		// What php and phar said goes to the log, with the path: the two cases that have php speak
		if( $names === true && ( count( $warnings ) !== 1 || str_contains( $warnings[0], $sandbox ) === false ) )
			$logged[] = $name;

		\Nino\Filesystem::removeDir( $sandbox );
	}

	check( $method. ': every one of '. count( $cases ). ' ways an archive or a download can fail is refused', $failures === [] );
	check( '...and no answer holds the project directory, the features directory or the temp directory', $leaks === [] );
	check( '...where php or phar spoke, the log of the house has it once, with the path', $logged === [] );
}

// A transport that names a file of this server (a certificate the php.ini points at)
$reset();
$app = $project( 'change-answers-transport' );
$sandbox = ninoSandboxDir( $app );
$features = [];
$makeFeature( 'alpha' );
$publish( $app );
$archiveUrl = $features['alpha']['archive'];
$stub = $app['./nino/fetch/stub'];
$app['./nino/fetch/stub'] = static function( string $url, array $options ) use ( $stub, $archiveUrl ): array {
	return $url === $archiveUrl ? [ 'ok' => false, 'status' => 0, 'error' => 'error setting certificate file: /srv/certs/ca-bundle.pem' ] : $stub( $url, $options );
};
ninoWarnings();
[ $status, $body ] = $call( $app, 'apiInstall', [ 'key' => 'alpha', 'version' => '1.0.0' ] );
$warnings = ninoWarnings();
check( 'a download that fails on a file of this server is refused without the file', $status === 400 && str_contains( (string) json_encode( $body ), '/srv/certs' ) === false && count( $warnings ) === 1 && str_contains( $warnings[0], '/srv/certs/ca-bundle.pem' ) === true );
$app['./nino/fetch/stub'] = static function( string $url, array $options ) use ( $stub, $archiveUrl ): array {
	return $url === $archiveUrl ? [ 'ok' => false, 'status' => 0, 'error' => 'schannel: failed to open C:\\certs\\ca-bundle.pem' ] : $stub( $url, $options );
};
unset( $app['./nino/catalogue'] );
ninoWarnings();
[ $status, $body ] = $call( $app, 'apiInstall', [ 'key' => 'alpha', 'version' => '1.0.0' ] );
$warnings = ninoWarnings();
check( '...also where the path is a Windows one', $status === 400 && str_contains( (string) json_encode( $body ), 'certs' ) === false && count( $warnings ) === 1 );
$app['./nino/fetch/stub'] = static fn( string $url, array $options ): array => [ 'ok' => false, 'status' => 0, 'error' => 'Could not resolve host: catalogue.test' ];
unset( $app['./nino/catalogue'] );
[ $status, $body ] = $call( $app, 'apiCatalogue' );
check( '...and the reason a download fails for that names no file stays as it is', $status === 400 && str_contains( (string) json_encode( $body ), 'Could not resolve host' ) === true );
\Nino\Filesystem::removeDir( $sandbox );

// An archive that cannot be written to the staging directory: a process with a limit on the size of a file
$reset();
$app = $project( 'change-answers-write' );
$sandbox = ninoSandboxDir( $app );
$features = [];
$rawFeature( 'alpha', $tar( $tarFiles( [ 'Alpha/blob.bin' => random_bytes( 6000 ) ] ) ) );
$publish( $app );
file_put_contents( $sandbox. '/remote.ser', serialize( $remote ) );
file_put_contents( $sandbox. '/key.pem', $publicKey );
file_put_contents( $sandbox. '/write.php', "<?php\ndefine( 'NINO_FEATURES_DIR', \$argv[1] );\nrequire \$argv[2];\n"
	. "\$dir = \$argv[3];\n\$app = [ './nino/uid' => \$dir ];\n\\Nino\\AppData::prepare( \$app );\n"
	. "\$app['./nino/filesystem/path'] = \$dir;\n\$app['./nino/filesystem/configpath'] = \$dir. '/private';\n\$app['./nino/filesystem/contentpath'] = \$dir. '/private';\n\$app['./nino/filesystem/publicpath'] = \$dir. '/public';\n"
	. "\$app['/nino/catalogue/key'] = file_get_contents( \$dir. '/key.pem' );\n\$remote = unserialize( file_get_contents( \$dir. '/remote.ser' ) );\n"
	. "\$app['./nino/fetch/stub'] = static fn( string \$url, array \$o ): array => isset( \$remote[\$url] ) ? [ 'ok' => true, 'status' => 200, 'body' => \$remote[\$url] ] : [ 'ok' => false, 'status' => 404, 'error' => 'http 404' ];\n"
	. "\$result = \\Nino\\Catalogue::install( \$app, 'alpha', '1.0.0' );\necho json_encode( [ \$result, ninoWarnings() ] ), \"\\n\";\n" );
$child = proc_open( [ 'sh', '-c', 'trap "" XFSZ; ulimit -f 4; exec "$@"', 'sh', PHP_BINARY, $sandbox. '/write.php', NINO_FEATURES_DIR, __DIR__. '/harness.php', $sandbox ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
$said = json_decode( trim( (string) stream_get_contents( $pipes[1] ) ), true );
proc_close( $child );
$staged = is_array( $said ) === true ? array_values( array_filter( (array) $said[1], static fn( string $w ): bool => str_contains( $w, $sandbox. '/private/data/.features/' ) ) ) : [];
check( 'an archive that cannot be written to the staging directory is the sentence, with no path', is_array( $said ) === true && is_string( $said[0] ) === true && str_contains( $said[0], $sandbox ) === false && str_contains( $said[0], 'staging' ) === true );
check( '...and the log of the house names the file and what php said', count( $staged ) === 1 );
\Nino\Filesystem::removeDir( $sandbox );

// A directory that cannot be removed
$reset();
$app = $project( 'change-answers-remove' );
$sandbox = ninoSandboxDir( $app );
$features = [];
$makeFeature( 'alpha' );
$publish( $app );
$place( $app, 'alpha' );
// A link to a directory is neither followed nor removed: the feature's directory is not empty afterwards
symlink( sys_get_temp_dir(), NINO_FEATURES_DIR. '/Alpha/link' );
ninoWarnings();
[ $status, $body ] = $call( $app, 'apiRemove', [ 'key' => 'alpha' ] );
$warnings = ninoWarnings();
check( 'a directory that cannot be removed is a 400 with the code of it, without its path', $status === 400 && ( $body['code'] ?? '' ) === 'features_remove_failed' && $leak( $body, $sandbox ) === false );
check( '...and the log of the house has the path, once', count( $warnings ) === 1 && str_contains( $warnings[0], NINO_FEATURES_DIR. '/Alpha' ) === true );
unlink( NINO_FEATURES_DIR. '/Alpha/link' );
\Nino\Filesystem::removeDir( $sandbox );

// The sentences exist in both languages of the workbench
$missing = [];
foreach( [ 'en_US', 'de_DE' ] as $locale )
	foreach( [ 'features_busy', 'features_lock_failed', 'features_unknown', 'features_invalid', 'features_install_failed', 'features_install_inactive', 'features_remove_failed', 'features_remove_active', 'features_activate_failed', 'features_deactivate_failed' ] as $code )
		if( $word( $locale, '/_admin/error/'. $code, 'x' ) === '' )
			$missing[] = $locale. ' '. $code;
check( 'the codes this panel answers a change with have a sentence in both interface languages', $missing === [] );

echo "\n";

$none = [];
ninoDone( $none );
