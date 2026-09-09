<?php
declare(strict_types=1);

/**
 *	Nino
 *	features-smoke.php	Contract test for \Nino\Features: discovery below
 *											NINO_FEATURES_DIR, manifest validation, version
 *											constraints, settings with every type, activation with
 *											the install unit applied without overwriting, updates
 *											through the upgrade hook, deactivation, and the two
 *											delivered manifests. Runs against tests/fixtures/features,
 *											never against the checkout's own features/.
 *
 *	Usage: php tests/features-smoke.php
 */

// Before the kernel loads: the autoloader and Features::dir() read the
// same constant, so the fixture directory serves the fixture classes too
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'features' );
$sandbox = ninoSandboxDir( $appData );

// The wizard writes the first config.php; here it is written by hand, with
// the two routes a project always has, so the activation's route merge has
// something to keep
$appData['/nino/http/routes'] = [
	'GET://' 				=> [ 'uri' => '/home', 'body' => '[template /templates/page-home]' ],
	'GET://sample'	=> [ 'uri' => '/sample-mine', 'body' => 'the project\'s own' ],
];
\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native', '/nino/http/routes' ] );
ninoWarnings();


// --- Discovery ---------------------------------------------------------------

echo "Features::all - discovery below NINO_FEATURES_DIR\n";

$all = \Nino\Features::all( $appData );
$warnings = ninoWarnings();

check( 'dir() is the constant', \Nino\Features::dir() === __DIR__. '/fixtures/features' );
check( 'a virtual /features path resolves there too, so a feature can name its own files', \Nino\Filesystem::path( $appData, '/features/Sample/feature.php' ) === __DIR__. '/fixtures/features/Sample/feature.php' );
check( 'every readable manifest is listed, sorted by key', array_keys( $all ) === [ 'helper', 'old', 'sample' ] );
check( 'a directory whose manifest does not validate is skipped with a warning naming it', isset( $all['broken'] ) === false
	&& count( array_filter( $warnings, static fn( string $w ): bool => str_contains( $w, '/Broken/feature.php' ) && str_contains( $w, 'version' ) ) ) === 1 );
check( 'the module class is derived from the directory', $all['sample']['module'] === '\\Nino\\Modules\\Sample' && $all['helper']['module'] === '\\Nino\\Modules\\Helper' );
check( 'the manifest is normalized: every key present', array_keys( $all['helper'] ) === [ 'key', 'dir', 'module', 'name', 'description', 'category', 'version', 'nino', 'php', 'requires', 'data', 'settings', 'active', 'installed', 'update', 'problems' ]
	&& $all['helper']['nino'] === '*' && $all['helper']['requires'] === [] && $all['helper']['settings'] === [] );
check( 'nothing is active or recorded on a fresh project', $all['sample']['active'] === false && $all['sample']['installed'] === null && $all['sample']['update'] === false );
check( 'a compatible feature has no problems', $all['sample']['problems'] === [] && $all['helper']['problems'] === [] );
check( 'an incompatible one names every problem: kernel version, extension, missing requirement', count( $all['old']['problems'] ) === 3
	&& str_contains( $all['old']['problems'][0], 'requires Nino ^0.9' ) && str_contains( $all['old']['problems'][1], 'no_such_extension' ) && str_contains( $all['old']['problems'][2], '"nowhere"' ) );
check( 'get() answers one by key, null for an unknown one', \Nino\Features::get( $appData, 'sample' )['key'] === 'sample' && \Nino\Features::get( $appData, 'nope' ) === null );
check( 'all() is read once per request', \Nino\Features::all( $appData ) === $all && ninoWarnings() === [] );

$active = $appData;
$active['/nino/modules'] = [ 'Nino\\Modules\\Helper' ];
unset( $active['./nino/features/all'] );
check( 'a class listed without its leading separator still counts as active', \Nino\Features::get( $active, 'helper' )['active'] === true );

echo "\n";


// --- Manifest validation -----------------------------------------------------

echo "Features::manifest - what a manifest must say\n";

// Every rescan of the fixture directory warns about Broken again - drained
// here so the checks below see only their own warnings
ninoWarnings();

$manifestDir = $sandbox. '/manifests';
function writeManifest( string $dir, string $name, mixed $manifest, bool $withClass = true ): string {
	$path = $dir. '/'. $name;
	@mkdir( $path, 0755, true );
	file_put_contents( $path. '/feature.php', '<?php return '. var_export( $manifest, true ). ';' );
	if( $withClass === true )
		file_put_contents( $path. '/'. $name. '.php', '<?php namespace Nino\\Modules { class '. $name. ' {} }' );
	return $path;
}
function manifestFails( string $dir, string $name, mixed $manifest, string $why, bool $withClass = true ): bool {
	$path = writeManifest( $dir, $name, $manifest, $withClass );
	$result = \Nino\Features::manifest( $path );
	$warnings = ninoWarnings();
	return $result === null && count( $warnings ) === 1 && str_contains( $warnings[0], $why );
}

check( 'a minimal manifest is name and version', is_array( \Nino\Features::manifest( writeManifest( $manifestDir, 'Mini', [ 'name' => 'Mini', 'version' => '0.1.0' ] ) ) ) && ninoWarnings() === [] );
check( 'the key defaults to the lowercased directory name', \Nino\Features::manifest( $manifestDir. '/Mini' )['key'] === 'mini' );
check( 'a lowercase directory is refused - it cannot serve a class', manifestFails( $manifestDir, 'lower', [ 'name' => 'x', 'version' => '1.0.0' ], 'directory name' ) );
check( 'a missing class file is refused', manifestFails( $manifestDir, 'NoClass', [ 'name' => 'x', 'version' => '1.0.0' ], 'NoClass.php is missing', false ) );
check( 'a manifest that is not an array is refused', ( static function() use ( $manifestDir ): bool {
	@mkdir( $manifestDir. '/Scalar', 0755, true );
	file_put_contents( $manifestDir. '/Scalar/feature.php', '<?php return "no";' );
	file_put_contents( $manifestDir. '/Scalar/Scalar.php', '<?php' );
	$r = \Nino\Features::manifest( $manifestDir. '/Scalar' );
	return $r === null && str_contains( ninoWarnings()[0] ?? '', 'must return an array' );
} )() );
check( 'a key that is not a slug is refused', manifestFails( $manifestDir, 'BadKey', [ 'key' => 'Bad Key', 'name' => 'x', 'version' => '1.0.0' ], '"key"' ) );
check( 'a name is required', manifestFails( $manifestDir, 'NoName', [ 'version' => '1.0.0' ], '"name"' ) );
check( 'a version is major.minor.patch', manifestFails( $manifestDir, 'BadVersion', [ 'name' => 'x', 'version' => '1.0' ], '"version"' ) );
check( 'a pre-release suffix is a version too', \Nino\Features::manifest( writeManifest( $manifestDir, 'Pre', [ 'name' => 'x', 'version' => '1.0.0-beta.2' ] ) )['version'] === '1.0.0-beta.2' );
check( 'a constraint has to be one satisfies() reads', manifestFails( $manifestDir, 'BadNino', [ 'name' => 'x', 'version' => '1.0.0', 'nino' => 'latest' ], '"nino"' ) );
check( 'a module entry may only name the class the directory serves', manifestFails( $manifestDir, 'WrongModule', [ 'name' => 'x', 'version' => '1.0.0', 'module' => '\\Acme\\Other' ], '"module"' )
	&& is_array( \Nino\Features::manifest( writeManifest( $manifestDir, 'RightModule', [ 'name' => 'x', 'version' => '1.0.0', 'module' => 'Nino\\Modules\\RightModule' ] ) ) ) );
check( 'requires lists feature keys, itself left out', manifestFails( $manifestDir, 'BadReq', [ 'name' => 'x', 'version' => '1.0.0', 'requires' => [ 'Not Slug' ] ], '"requires"' )
	&& \Nino\Features::manifest( writeManifest( $manifestDir, 'SelfReq', [ 'name' => 'x', 'version' => '1.0.0', 'requires' => [ 'selfreq', 'a', 'a', 'b' ] ] ) )['requires'] === [ 'a', 'b' ] );
check( 'data paths stay below /data/', manifestFails( $manifestDir, 'BadData', [ 'name' => 'x', 'version' => '1.0.0', 'data' => [ '/config.php' ] ], '"data"' )
	&& manifestFails( $manifestDir, 'DotData', [ 'name' => 'x', 'version' => '1.0.0', 'data' => [ '/data/../config.php' ] ], '"data"' ) );
check( 'extensions are names', manifestFails( $manifestDir, 'BadExt', [ 'name' => 'x', 'version' => '1.0.0', 'php' => [ 'ext' => [ 'g d' ] ] ], '"php"' ) );

// The vocabulary is CATEGORIES, the rule is a slug. A feature written for a
// catalogue this kernel predates is filed under a category this kernel has
// never heard of, and has to install anyway - so an unknown slug is kept, and
// only something that is not a slug at all is refused
check( 'a category is optional and defaults to none', \Nino\Features::manifest( $manifestDir. '/Mini' )['category'] === '' );
check( 'one of the published categories is kept', \Nino\Features::manifest( writeManifest( $manifestDir, 'Filed', [ 'name' => 'x', 'version' => '1.0.0', 'category' => 'security' ] ) )['category'] === 'security'
	&& \Nino\Features::CATEGORIES === [ 'content', 'ui', 'communication', 'marketing', 'security', 'system' ] );
check( 'and so is one this kernel does not publish - a later catalogue files features under names this one cannot know', \Nino\Features::manifest( writeManifest( $manifestDir, 'Later', [ 'name' => 'x', 'version' => '1.0.0', 'category' => 'commerce' ] ) )['category'] === 'commerce'
	&& ninoWarnings() === [] );
check( 'a category that is not a slug is refused, and the message names the vocabulary', manifestFails( $manifestDir, 'BadCategory', [ 'name' => 'x', 'version' => '1.0.0', 'category' => 'UI Effects' ], '"category"' )
	&& manifestFails( $manifestDir, 'ArrayCategory', [ 'name' => 'x', 'version' => '1.0.0', 'category' => [ 'security' ] ], 'security, system' ) );
check( 'a setting needs a known type', manifestFails( $manifestDir, 'BadType', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'color' ] ] ], 'unknown type' ) );
check( 'a setting name is a lowerCamel identifier', manifestFails( $manifestDir, 'BadSetting', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'api-key' => [ 'type' => 'string' ] ] ], 'setting name' ) );
check( 'a select needs options', manifestFails( $manifestDir, 'NoOptions', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'select' ] ] ], '"options"' ) );
check( 'an int bound is an int, and min stays below max', manifestFails( $manifestDir, 'BadMin', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'int', 'min' => '1' ] ] ], '"min"' )
	&& manifestFails( $manifestDir, 'Crossed', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'int', 'min' => 5, 'max' => 1 ] ] ], 'above' ) );
check( 'a pattern is a valid regular expression', manifestFails( $manifestDir, 'BadPattern', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'string', 'pattern' => '/[' ] ] ], '"pattern"' ) );
check( 'a default has to validate against its own schema', manifestFails( $manifestDir, 'BadDefault', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'int', 'max' => 3, 'default' => 9 ] ] ], 'default' ) );
check( 'a secret cannot have a default', manifestFails( $manifestDir, 'SecretDefault', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'secret', 'default' => 'x' ] ] ], 'secret' ) );
check( 'maxlength is bounded by the type', manifestFails( $manifestDir, 'LongMax', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'string', 'maxlength' => 5000 ] ] ], '"maxlength"' ) );
check( 'a schema comes back normalized', ( static function() use ( $all ): bool {
	$s = $all['sample']['settings'];
	return $s['limit'] === [ 'type' => 'int', 'label' => 'Limit', 'hint' => 'Items per page', 'required' => false, 'min' => 1, 'max' => 50, 'unit' => 'items', 'default' => 10 ]
		&& $s['apiKey'] === [ 'type' => 'secret', 'label' => 'API key', 'hint' => '', 'required' => false, 'maxlength' => 1000 ]
		&& $s['mode']['options']['fast'] === 'Fast' && $s['title']['required'] === true && $s['notes']['maxlength'] === 10000;
} )() );

echo "\n";


// --- Version constraints -----------------------------------------------------

echo "Features::satisfies - the constraint vocabulary\n";

foreach( [
	[ '^1.0', '1.0.0-beta', true ], [ '^1.0', '1.9.3', true ], [ '^1.0', '2.0.0', false ], [ '^1.2', '1.1.0', false ],
	[ '^0.13', '0.13.5', true ], [ '^0.13', '0.14.0', false ], [ '^0', '0.9.0', true ],
	[ '~1.2', '1.9.0', true ], [ '~1.2', '2.0.0', false ], [ '~1.2.3', '1.2.9', true ], [ '~1.2.3', '1.3.0', false ],
	[ '>=1.0 <2.0', '1.5.0', true ], [ '>=1.0, <2.0', '2.0.0', false ], [ '>1.0.0', '1.0.0', false ], [ '<=1.0', '1.0.0', true ], [ '!=1.0.0', '1.0.0', false ],
	[ '1.0.0', '1.0.0-beta', true ], [ '1.0.0', '1.0.1', false ], [ '1', '1.7.2', true ], [ '1.7', '1.7.2', true ], [ '1.7', '1.8.0', false ],
	[ '2.0 || ^1.0', '1.0.0', true ], [ '2.0 || ^1.0', '3.0.0', false ], [ '*', '9.9.9', true ],
	[ '^1.0', 'nope', false ],
] as [ $constraint, $version, $expected ] )
	check( str_pad( $constraint, 12 ). ' '. str_pad( $version, 11 ). ' -> '. ( $expected ? 'yes' : 'no' ), \Nino\Features::satisfies( $constraint, $version ) === $expected );
check( 'the default version is the running kernel', \Nino\Features::satisfies( '^'. explode( '.', \Nino\VERSION )[0] ) === true );

echo "\n";


// --- Localized values --------------------------------------------------------

echo "Features::localized\n";

check( 'a string is itself', \Nino\Features::localized( 'Plain', 'de_DE' ) === 'Plain' );
check( 'a map answers the locale asked for', \Nino\Features::localized( [ 'en_US' => 'Sample', 'de_DE' => 'Beispiel' ], 'de_DE' ) === 'Beispiel' );
check( '...falls back to en_US', \Nino\Features::localized( [ 'en_US' => 'Sample', 'de_DE' => 'Beispiel' ], 'fr_FR' ) === 'Sample' );
check( '...then to the first', \Nino\Features::localized( [ 'de_DE' => 'Beispiel' ], 'fr_FR' ) === 'Beispiel' );
check( 'anything else is empty', \Nino\Features::localized( 42, 'de_DE' ) === '' && \Nino\Features::localized( [], 'de_DE' ) === '' );

echo "\n";


// --- Settings ----------------------------------------------------------------

echo "Features::settings / validateSettings / saveSettings\n";

$defaults = \Nino\Features::settings( $appData, 'sample' );
check( 'every declared setting is answered - the default, else the type\'s zero', $defaults === [
	'enabled' => true, 'limit' => 10, 'title' => 'Hello', 'slug' => '', 'notes' => '', 'contact' => '', 'site' => '', 'mode' => 'fast', 'apiKey' => '', 'hosts' => [],
] );
check( 'setting() answers one, with a default for one the schema does not declare', \Nino\Features::setting( $appData, 'sample', 'limit' ) === 10
	&& \Nino\Features::setting( $appData, 'sample', 'nope', 'dflt' ) === 'dflt' && \Nino\Features::setting( $appData, 'unknown', 'x', 7 ) === 7 );
check( 'an unknown feature has no settings', \Nino\Features::settings( $appData, 'nope' ) === [] );

$schema = $all['sample']['settings'];
$v = static fn( array $posted, array $current = [] ): array => \Nino\Features::validateSettings( $schema, $posted, $current );

check( 'a form posts strings and gets the real types back', $v( [ 'enabled' => 'false', 'limit' => '25' ] )['values'] === [ 'enabled' => false, 'limit' => 25 ] );
check( 'a bool takes only its own spellings', $v( [ 'enabled' => 'yes' ] )['errors'] === [ 'enabled' => 'must be true or false' ] && $v( [ 'enabled' => 1 ] )['values']['enabled'] === true );
check( 'an int is a whole number within its bounds', $v( [ 'limit' => '5.5' ] )['errors']['limit'] === 'must be a whole number'
	&& $v( [ 'limit' => 0 ] )['errors']['limit'] === 'must be at least 1' && $v( [ 'limit' => '51' ] )['errors']['limit'] === 'must be at most 50' );
check( 'a string is trimmed, capped and, when required, not empty', $v( [ 'title' => '  Hi  ' ] )['values']['title'] === 'Hi'
	&& $v( [ 'title' => str_repeat( 'x', 41 ) ] )['errors']['title'] === 'must be at most 40 characters' && $v( [ 'title' => ' ' ] )['errors']['title'] === 'is required' );
check( 'a pattern is enforced, an empty optional value passes it by', $v( [ 'slug' => 'my-slug' ] )['values']['slug'] === 'my-slug'
	&& $v( [ 'slug' => 'My Slug' ] )['errors']['slug'] === 'does not match the required form' && $v( [ 'slug' => '' ] )['values']['slug'] === '' );
check( 'text keeps its lines', $v( [ 'notes' => " a\nb " ] )['values']['notes'] === "a\nb" && $v( [ 'notes' => 5 ] )['errors']['notes'] === 'must be a string' );
check( 'an email is an email', $v( [ 'contact' => 'o\'brien@example.com' ] )['values']['contact'] === 'o\'brien@example.com' && $v( [ 'contact' => 'nope' ] )['errors']['contact'] === 'must be an email address' );
check( 'a url is http(s)', $v( [ 'site' => 'https://example.com/x?y=1' ] )['values']['site'] === 'https://example.com/x?y=1'
	&& $v( [ 'site' => 'ftp://example.com' ] )['errors']['site'] === 'must be an http(s) url' && $v( [ 'site' => 'javascript:alert(1)' ] )['errors']['site'] === 'must be an http(s) url' );
check( 'a select takes one of its options', $v( [ 'mode' => 'safe' ] )['values']['mode'] === 'safe' && $v( [ 'mode' => 'turbo' ] )['errors']['mode'] === 'must be one of the options' && $v( [ 'mode' => 3 ] )['errors']['mode'] === 'must be one of the options' );
check( 'a secret posted empty keeps the current one, posted null clears it', $v( [ 'apiKey' => '' ], [ 'apiKey' => 'old' ] )['values']['apiKey'] === 'old'
	&& $v( [ 'apiKey' => null ], [ 'apiKey' => 'old' ] )['values']['apiKey'] === '' && $v( [ 'apiKey' => 'new' ], [ 'apiKey' => 'old' ] )['values']['apiKey'] === 'new' );
check( 'lines come as a textarea or a list, trimmed and unique', $v( [ 'hosts' => " a.example \n\n b.example \n a.example " ] )['values']['hosts'] === [ 'a.example', 'b.example' ]
	&& $v( [ 'hosts' => [ 'x', 'y' ] ] )['values']['hosts'] === [ 'x', 'y' ] && $v( [ 'hosts' => [ 'x', 3 ] ] )['errors']['hosts'] === 'must be a list of strings' );
check( 'a setting the form did not send keeps its current value, and a stray key is ignored', $v( [ 'limit' => 3, 'stray' => 1 ], [ 'title' => 'Kept', 'stray' => 2 ] )['values'] === [ 'limit' => 3, 'title' => 'Kept' ] );
check( 'every setting is checked before any is accepted', count( $v( [ 'limit' => 'x', 'title' => '', 'mode' => 'z' ] )['errors'] ) === 3 );

$errors = \Nino\Features::saveSettings( $appData, 'sample', [ 'limit' => '7', 'title' => 'Saved', 'apiKey' => 'k-1', 'hosts' => "one\ntwo" ] );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'saveSettings validates, then writes the state key once', $errors === [] && $stored['/nino/features']['sample']['settings'] === [ 'limit' => 7, 'title' => 'Saved', 'apiKey' => 'k-1', 'hosts' => [ 'one', 'two' ] ] );
check( 'settings() now answers what was saved, defaults for the rest', \Nino\Features::settings( $appData, 'sample' )['limit'] === 7 && \Nino\Features::settings( $appData, 'sample' )['enabled'] === true );
check( 'a rejected form writes nothing', \Nino\Features::saveSettings( $appData, 'sample', [ 'limit' => '99', 'title' => 'Not' ] ) === [ 'limit' => 'must be at most 50' ]
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['settings']['title'] === 'Saved' );
check( 'a second save keeps the secret when the form sends it empty', \Nino\Features::saveSettings( $appData, 'sample', [ 'apiKey' => '', 'title' => 'Again' ] ) === []
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['settings']['apiKey'] === 'k-1' );
check( 'saving for an unknown feature is refused', \Nino\Features::saveSettings( $appData, 'nope', [] ) === [ '' => 'unknown feature "nope"' ] );
check( 'a stored value that no longer validates falls back to the default', ( static function() use ( $appData ): bool {
	$appData['/nino/features']['sample']['settings']['limit'] = 'corrupt';
	unset( $appData['./nino/features/all'] );
	return \Nino\Features::settings( $appData, 'sample' )['limit'] === 10;
} )() );

echo "\n";


// --- Activation --------------------------------------------------------------

echo "Features::activate - requirements, the unit, the module list, the record\n";

// The project already has a template and a text key the unit also ships
\Nino\Filesystem::forceDir( $appData, '/templates' );
file_put_contents( \Nino\Filesystem::path( $appData, '/templates/page-sample.tpl' ), 'mine' );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/sample/intro]]' => 'Meins' ] );

check( 'an unknown feature cannot be activated', \Nino\Features::activate( $appData, 'nope' ) === 'unknown feature "nope"' );
check( 'an incompatible feature is refused with its problems', str_starts_with( (string) \Nino\Features::activate( $appData, 'old' ), 'feature "old" cannot be activated: requires Nino ^0.9' ) );

$result = \Nino\Features::activate( $appData, 'sample' );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'activation succeeds', $result === true );
check( 'the required feature was activated first, then the feature itself', $stored['/nino/modules'] === [ '\\Nino\\Modules\\Helper', '\\Nino\\Modules\\Sample' ] && $appData['/nino/modules'] === $stored['/nino/modules'] );
check( 'both versions are recorded, the earlier settings kept', $stored['/nino/features']['helper'] === [ 'version' => '0.1.0', 'settings' => [] ]
	&& $stored['/nino/features']['sample']['version'] === '1.2.0' && $stored['/nino/features']['sample']['settings']['title'] === 'Again' );
check( 'the unit\'s routes are added for the available locales, a key the project holds is left alone', isset( $stored['/nino/http/routes']['GET://sample-de'] ) === true
	&& isset( $stored['/nino/http/routes']['GET://sample-fr'] ) === false && $stored['/nino/http/routes']['GET://sample']['body'] === 'the project\'s own' && isset( $stored['/nino/http/routes']['GET://'] ) === true );
check( 'the live routes go on with the new ones added', $appData['/nino/http/routes']['GET://sample-de']['uri'] === '/beispiel' );
check( 'the unit\'s template is copied only where the project has none', file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-sample.tpl' ) ) === 'mine' );
check( 'the unit\'s text keys are added, an existing key kept', \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/sample/intro]]'] === 'Meins'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/sample/intro]]'] === 'Welcome to the sample.'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/sample/label]]'] === 'Sample label' );
check( 'the unit\'s blacklist and config default are applied', in_array( '/sample/hidden', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true && $stored['/sample/config'] === 'unit-default' );
check( 'the registry now knows it as active and current', \Nino\Features::get( $appData, 'sample' )['active'] === true && \Nino\Features::get( $appData, 'sample' )['installed'] === '1.2.0' && \Nino\Features::get( $appData, 'sample' )['update'] === false );
check( 'the module boots on the next request and its shortcode reads its settings', ( static function() use ( $appData ): bool {
	\Nino\Modules::callModules( $appData, 'init' );
	return ( $appData['./helper/booted'] ?? false ) === true && \Nino\Html::renderHtml( $appData, '[sample]' ) === 'Again';
} )() );
check( 'the workbench lists its panel while it is active', isset( \Nino\Admin\Admin::panels( $appData )['sample'] ) === true );

// The manifest key 'data' is documented as "what a backup carries", and until
// Backup::manifest() read it, it was not: only two hardcoded literals for the
// one feature that predates the catalogue were ever carried, so a directory a
// feature owns was silently absent from every backup
\Nino\Filesystem::putFileContent( $appData, '/data/sample.php', [ 'kept' => true ] );
\Nino\Filesystem::putFileContent( $appData, '/data/sample-dir/one.php', [ 1 ] );
\Nino\Filesystem::putFileContent( $appData, '/data/sample-dir/deeper/two.php', [ 2 ] );
$carried = \Nino\Backup::manifest( $appData );
check( 'a backup carries the file an active feature\'s manifest declares under data', in_array( 'data/sample.php', $carried, true ) === true );
check( '...and every file below a directory it declares, at its own relative path', in_array( 'data/sample-dir/one.php', $carried, true ) === true
	&& in_array( 'data/sample-dir/deeper/two.php', $carried, true ) === true );
check( 'and carries nothing for a feature that is not switched on', ( static function( array $appData ): bool {
	\Nino\Features::deactivate( $appData, 'sample' );
	$carried = \Nino\Backup::manifest( $appData );
	return in_array( 'data/sample.php', $carried, true ) === false;
} )( $appData ) );
check( 'its class file sits below NINO_FEATURES_DIR, so the registry moves it into the features group though its own nav() names content', \Nino\Admin\Admin::panels( $appData )['sample']['group'] === 'features' );
check( 'the registry sits it in the rail between the structure and the system panels, GROUPS order rather than its own nav()', ( static function() use ( $appData ): bool {
	$order = array_keys( \Nino\Admin\Admin::panels( $appData ) );
	return array_search( 'routes', $order, true ) < array_search( 'sample', $order, true )
		&& array_search( 'sample', $order, true ) < array_search( 'users', $order, true );
} )() );
check( 'the Roles tab offers the panel\'s permission under the features group, the same way it offers a content panel\'s', in_array(
	[ 'perm' => \Nino\Modules\Sample\Admin::MANAGE_PERM, 'label' => '/_admin/nav/sample', 'group' => 'features', 'offered' => true ],
	\Nino\Modules\Users\Admin::permOptions( $appData ),
	true
) === true );

$again = \Nino\Features::activate( $appData, 'sample' );
check( 'activating again is harmless: nothing changes', $again === true && \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === $stored['/nino/modules'] && isset( $appData['./sample/upgraded-from'] ) === false );

echo "\n";


// --- Updates -----------------------------------------------------------------

echo "Features::activate - an update through the upgrade hook\n";

$appData['/nino/features']['sample']['version'] = '0.5.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );
check( 'a recorded version behind the manifest reads as an update', \Nino\Features::get( $appData, 'sample' )['update'] === true );

file_put_contents( \Nino\Filesystem::path( $appData, '/templates/page-sample.tpl' ), 'edited since' );
$upgraded = \Nino\Features::activate( $appData, 'sample' );
check( 'the module is asked to upgrade from the recorded version, then the new one is recorded', $upgraded === true && $appData['./sample/upgraded-from'] === '0.5.0'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['version'] === '1.2.0' && \Nino\Features::get( $appData, 'sample' )['update'] === false );
check( 'an update never overwrites what the project edited', file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-sample.tpl' ) ) === 'edited since' );

$appData['/nino/features']['sample']['version'] = '0.0.1';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );
$refused = \Nino\Features::activate( $appData, 'sample' );
check( 'a refused upgrade leaves the record as it was', $refused === 'feature "sample" refused to upgrade from 0.0.1' && \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['version'] === '0.0.1' );
$appData['/nino/features']['sample']['version'] = '1.2.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );

echo "\n";


// --- Deactivation ------------------------------------------------------------

echo "Features::deactivate\n";

check( 'a feature another active one requires stays on', \Nino\Features::deactivate( $appData, 'helper' ) === 'feature "helper" is required by "sample"' );
check( 'an unknown feature cannot be deactivated', \Nino\Features::deactivate( $appData, 'nope' ) === 'unknown feature "nope"' );

$off = \Nino\Features::deactivate( $appData, 'sample' );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'deactivation removes the class and nothing else', $off === true && $stored['/nino/modules'] === [ '\\Nino\\Modules\\Helper' ]
	&& $stored['/nino/features']['sample']['settings']['title'] === 'Again' && isset( $stored['/nino/http/routes']['GET://sample-de'] ) === true
	&& is_file( \Nino\Filesystem::path( $appData, '/templates/page-sample.tpl' ) ) === true );
check( 'the registry reads it as inactive, its record kept', \Nino\Features::get( $appData, 'sample' )['active'] === false && \Nino\Features::get( $appData, 'sample' )['installed'] === '1.2.0' );
check( 'the panel is gone with it', isset( \Nino\Admin\Admin::panels( $appData )['sample'] ) === false );
check( 'now the requirement may go too', \Nino\Features::deactivate( $appData, 'helper' ) === true && \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [] );
check( 'deactivating an inactive feature is harmless', \Nino\Features::deactivate( $appData, 'helper' ) === true );
check( 'reactivation finds the settings as they were', \Nino\Features::activate( $appData, 'sample' ) === true && \Nino\Features::settings( $appData, 'sample' )['title'] === 'Again' );

echo "\n";


// --- The unit application both callers share ----------------------------------

echo "Features::applyUnit - overwrite for the wizard, add-only for a feature\n";

$unitDir = $sandbox. '/unit';
@mkdir( $unitDir. '/templates', 0755, true );
@mkdir( $unitDir. '/text', 0755, true );
@mkdir( $unitDir. '/images', 0755, true );
file_put_contents( $unitDir. '/manifest.php', '<?php return '. var_export( [
	'routes'		=> [ 'GET://u' => [ 'uri' => '/u' ] ],
	'templates'	=> [ 'page-u.tpl', 'de_DE' => 'page-u.de_DE.tpl', 'fr_FR' => 'page-u.fr_FR.tpl' ],
	'files'			=> [ 'images' ],
	'blacklist'	=> [ '/u/x' ],
], true ). ';' );
file_put_contents( $unitDir. '/templates/page-u.tpl', 'unit' );
file_put_contents( $unitDir. '/templates/page-u.de_DE.tpl', 'unit de' );
file_put_contents( $unitDir. '/templates/page-u.fr_FR.tpl', 'unit fr' );
file_put_contents( $unitDir. '/images/u.txt', 'unit image' );
file_put_contents( $unitDir. '/text/global.php', '<?php return [ "[[/u/g]]" => "unit" ];' );

file_put_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.tpl' ), 'project' );
\Nino\Filesystem::forceDir( $appData, '/images' );
file_put_contents( \Nino\Filesystem::path( $appData, '/images/u.txt' ), 'project image' );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/u/g]]' => 'project' ] );

$routes = [ 'GET://u' => [ 'uri' => '/project-u' ] ];
$blacklist = [];
\Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE' ], $routes, $blacklist, false );
check( 'add-only: an existing route, template, file and text key stay, what is missing arrives', $routes['GET://u']['uri'] === '/project-u'
	&& file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.tpl' ) ) === 'project' && file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.de_DE.tpl' ) ) === 'unit de'
	&& file_get_contents( \Nino\Filesystem::path( $appData, '/images/u.txt' ) ) === 'project image' && \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/u/g]]'] === 'project' && $blacklist === [ '/u/x' ] );
check( 'a locale-gated template for a locale not asked for is never copied', is_file( \Nino\Filesystem::path( $appData, '/templates/page-u.fr_FR.tpl' ) ) === false );

\Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE' ], $routes, $blacklist, true );
check( 'overwrite: the unit replaces all of it', $routes['GET://u']['uri'] === '/u'
	&& file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.tpl' ) ) === 'unit' && file_get_contents( \Nino\Filesystem::path( $appData, '/images/u.txt' ) ) === 'unit image'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/u/g]]'] === 'unit' && $blacklist === [ '/u/x', '/u/x' ] );
check( 'a unit without a manifest applies nothing', ( static function() use ( $appData, $sandbox ): bool {
	$r = [ 'a' => 1 ]; $b = [];
	\Nino\Features::applyUnit( $appData, $sandbox. '/no-unit', [ 'de_DE' ], $r, $b );
	return $r === [ 'a' => 1 ] && $b === [] && \Nino\Features::readUnitManifest( $sandbox. '/no-unit' ) === null;
} )() );

echo "\n";


// --- The checkout's features directory -----------------------------------------

echo "The checkout's features/\n";

ninoWarnings();

// A checkout ships the directory and its deny rule, and no feature of its own:
// the features come from dapeio/nino-features, one directory each, and
// whatever a checkout does carry has to validate
$checkout = dirname( __DIR__ ). '/features';
check( 'features/ exists and carries its deny rule', is_dir( $checkout ) === true && str_contains( (string) file_get_contents( $checkout. '/.htaccess' ), 'Require all denied' ) === true );
check( 'every feature directory the checkout carries validates', array_filter( glob( $checkout. '/*', GLOB_ONLYDIR ) ?: [], static fn( string $d ): bool => \Nino\Features::manifest( $d ) === null ) === [] && ninoWarnings() === [] );

echo "\n";


// --- The Features panel ------------------------------------------------------

echo "Features panel - the workbench's actions over the same kernel\n";

/**
 *	Dispatch one action directly against the panel class, the way
 *	tests/admin-system-smoke.php's callDev() does: the payload as the json
 *	"data" post field, the answer as [ statusCode, body ]
 *
 *	@param		array 		&$appData
 *	@param		string		$method				eg. "apiList"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body ]
 */
function callFeatures( array &$appData, string $method, array $data = [] ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	\Nino\Modules\Features\Admin::{$method}( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

// The state the sections above left behind: sample and helper active,
// sample's settings saved (limit 7, title "Again", the secret "k-1", two
// hosts), old inactive with its three problems
$panelActions = [
	'apiList' 			=> [],
	'apiActivate' 	=> [ 'key' => 'helper' ],
	'apiDeactivate'	=> [ 'key' => 'helper' ],
	'apiSettings' 	=> [ 'key' => 'sample', 'fields' => [] ],
	'apiCatalogue'	=> [],
	'apiInstall' 		=> [ 'key' => 'helper', 'version' => '1.0.0' ],
];

check( 'the panel is a system entry between Backups (10) and Config (20), with its two mount points and its permission', \Nino\Modules\Features\Admin::nav() === [ 'features', '/_admin/nav/features', 15, 'system' ]
	&& \Nino\Modules\Features\Admin::panes() === [ 'features-list', 'features-detail' ] && \Nino\Modules\Features\Admin::perm() === '/_admin/features/manage' );
check( 'it offers exactly the seven actions', array_keys( \Nino\Modules\Features\Admin::actions() ) === [ 'features/list', 'features/activate', 'features/deactivate', 'features/settings', 'features/remove', 'features/catalogue', 'features/install' ] );
check( 'the workbench finds it by reading the directory, nothing registered', in_array( \Nino\Modules\Features\Admin::class, \Nino\Admin\Admin::modules(), true ) === true && isset( \Nino\Admin\Admin::panels( $appData )['features'] ) === true );

// Nobody is signed in: every action is a 401
foreach( $panelActions as $method => $data )
	check( $method. ' is 401 without an account', callFeatures( $appData, $method, $data ) === [ 401, [ 'error' => 'not logged in' ] ] );

// The minimum of the set-up project tests/admin-system-smoke.php seeds: an
// account without the permission and one with it. The daily backup and the
// activity log the gate would otherwise run are off - not what this tests
$appData['/nino/admin/backups']	= false;
$appData['/nino/admin/logs']		= false;
$appData['/nino/auth/user']			= [];
$appData['/nino/auth/roles']		= [];
\Nino\Auth::insertUser( $appData, 'editor@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::insertUser( $appData, 'dev@example.com', 'correct horse battery staple', [ \Nino\Modules\Features\Admin::MANAGE_PERM ] );

\Nino\Auth::loginUser( $appData, 'editor@example.com', 'correct horse battery staple' );
foreach( $panelActions as $method => $data )
	check( $method. ' is 403 without the permission', callFeatures( $appData, $method, $data ) === [ 403, [ 'error' => 'not allowed' ] ] );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status, $body ] = callFeatures( $appData, 'apiList' );
// Sorted by the name a person reads, not by the key: in this session's
// locale the sample feature is "Beispiel-Feature", so it comes first here
// and would come last if the panel still answered in key order
check( 'apiList answers the directory the panel reads from, the catalogue url, whether it is writable, no cached catalogue yet, and every feature, sorted by the localized name', $status === 200 && $body['dir'] === '/tests/fixtures/features'
	&& $body['catalogueUrl'] === \Nino\Catalogue::DEFAULT_URL && $body['writable'] === true && $body['catalogue'] === null
	&& array_keys( $body ) === [ 'dir', 'catalogueUrl', 'writable', 'catalogue', 'features' ] && array_column( $body['features'], 'key' ) === [ 'sample', 'helper', 'old' ]
	&& array_column( $body['features'], 'name' ) === [ 'Beispiel-Feature', 'Helper', 'Old' ] );
$byKey = array_column( $body['features'], null, 'key' );
check( 'every entry has the same keys', array_keys( $byKey['sample'] ) === [ 'key', 'name', 'description', 'category', 'version', 'installed', 'active', 'update', 'requires', 'problems', 'settings' ] );
check( 'names and descriptions arrive in the session locale - de_DE, the native language, since none was chosen', $byKey['sample']['name'] === 'Beispiel-Feature' && $byKey['sample']['description'] === 'Prüft den ganzen Feature-Vertrag.'
	&& $byKey['helper']['name'] === 'Helper' && $byKey['helper']['description'] === '' );
// The slug, not a word: the categories are named in the panel's own fills, so
// the script labels the ones this workbench knows and shows the rest as they
// are - see tests/admin-features-js-smoke.js
check( 'the category travels as the slug it is, empty for a feature that names none', $byKey['sample']['category'] === 'content' && $byKey['helper']['category'] === '' );
check( 'the state travels with each entry', $byKey['sample']['active'] === true && $byKey['sample']['installed'] === '1.2.0' && $byKey['sample']['update'] === false && $byKey['sample']['requires'] === [ 'helper' ]
	&& $byKey['old']['active'] === false && $byKey['old']['installed'] === null );
check( 'an incompatible feature lists its problems, a compatible one none', count( $byKey['old']['problems'] ) === 3 && str_contains( $byKey['old']['problems'][0], 'requires Nino ^0.9' ) && $byKey['sample']['problems'] === [] && $byKey['helper']['settings'] === [] );

$fields = array_column( $byKey['sample']['settings'], null, 'name' );
check( 'the settings schema is a list in manifest order', array_keys( $fields ) === [ 'enabled', 'limit', 'title', 'slug', 'notes', 'contact', 'site', 'mode', 'apiKey', 'hosts' ] );
check( 'every field carries the same keys - a value for all but a secret', array_keys( $fields['limit'] ) === [ 'name', 'type', 'label', 'hint', 'required', 'min', 'max', 'maxlength', 'unit', 'options', 'value' ]
	&& array_keys( $fields['apiKey'] ) === [ 'name', 'type', 'label', 'hint', 'required', 'min', 'max', 'maxlength', 'unit', 'options', 'set' ] );
check( 'labels, hints and option labels are localized', $fields['enabled']['label'] === 'Aktiv' && $fields['limit']['hint'] === 'Items per page'
	&& $fields['mode']['options'] === [ [ 'value' => 'fast', 'label' => 'Fast' ], [ 'value' => 'safe', 'label' => 'Sicher' ] ] );
check( 'an int carries its bounds and unit, a string its maxlength and whether it is required', $fields['limit']['min'] === 1 && $fields['limit']['max'] === 50 && $fields['limit']['unit'] === 'items'
	&& $fields['title']['maxlength'] === 40 && $fields['title']['required'] === true && $fields['slug']['min'] === null && $fields['slug']['required'] === false );
check( 'values are the effective ones - saved, else the default, else the type\'s zero', $fields['limit']['value'] === 7 && $fields['title']['value'] === 'Again' && $fields['enabled']['value'] === true
	&& $fields['hosts']['value'] === [ 'one', 'two' ] && $fields['mode']['value'] === 'fast' && $fields['notes']['value'] === '' );
check( 'a secret never travels - only whether one is stored', array_key_exists( 'value', $fields['apiKey'] ) === false && $fields['apiKey']['set'] === true );

// Activation through the action
[ $status, $body ] = callFeatures( $appData, 'apiActivate', [ 'key' => 'old' ] );
check( 'activating an incompatible feature is a 400 carrying the kernel\'s reason', $status === 400 && str_starts_with( $body['error'], 'feature "old" cannot be activated: requires Nino ^0.9' ) );
check( 'an unknown, a malformed or a missing key is a 400 before the kernel is asked', callFeatures( $appData, 'apiActivate', [ 'key' => 'nope' ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiActivate', [ 'key' => [ 'sample' ] ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiActivate', [ 'key' => '../etc' ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiDeactivate', [] ) === [ 400, [ 'error' => 'unknown feature' ] ] );
check( 'none of that wrote anything', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [ '\\Nino\\Modules\\Helper', '\\Nino\\Modules\\Sample' ] );

[ $status, $body ] = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'helper' ] );
check( 'deactivating a feature another active one requires is refused with the kernel\'s reason', $status === 400 && $body['error'] === 'feature "helper" is required by "sample"' );

[ $status, $body ] = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'sample' ] );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'deactivating answers the refreshed entry, and only the class left config.php', $status === 200 && $body['feature']['key'] === 'sample' && $body['feature']['active'] === false && $body['feature']['installed'] === '1.2.0'
	&& $stored['/nino/modules'] === [ '\\Nino\\Modules\\Helper' ] && $stored['/nino/features']['sample']['settings']['title'] === 'Again' );
check( 'the dashboard tile counts what is active now', \Nino\Modules\Features\Admin::summary( $appData ) === [ 'value' => 1, 'label' => '/_admin/features/label/active' ] );

[ $status, $body ] = callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] );
check( 'activating a valid key answers the refreshed entry, localized like the list', $status === 200 && $body['feature']['active'] === true && $body['feature']['update'] === false && $body['feature']['name'] === 'Beispiel-Feature'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [ '\\Nino\\Modules\\Helper', '\\Nino\\Modules\\Sample' ] );
check( '...and the tile follows', \Nino\Modules\Features\Admin::summary( $appData )['value'] === 2 );

// Settings through the action
[ $status, $body ] = callFeatures( $appData, 'apiSettings', [ 'key' => 'sample', 'fields' => [ 'limit' => '99', 'contact' => 'nope', 'title' => 'Not' ] ] );
check( 'a bad form is a 400 naming every rejected field, and nothing is written', $status === 400 && $body['error'] === 'limit: must be at most 50; contact: must be an email address'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['settings']['title'] === 'Again' );

[ $status, $body ] = callFeatures( $appData, 'apiSettings', [ 'key' => 'sample', 'fields' => [ 'enabled' => false, 'limit' => '12', 'contact' => 'a@example.com', 'mode' => 'safe', 'apiKey' => '', 'hosts' => "x.example\n\ny.example" ] ] );
$fields = array_column( $body['feature']['settings'] ?? [], null, 'name' );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['settings'];
check( 'a good form is persisted in one write and echoed back typed', $status === 200 && $fields['limit']['value'] === 12 && $fields['enabled']['value'] === false && $fields['contact']['value'] === 'a@example.com'
	&& $fields['mode']['value'] === 'safe' && $fields['hosts']['value'] === [ 'x.example', 'y.example' ] && $stored['limit'] === 12 && $stored['enabled'] === false && $stored['hosts'] === [ 'x.example', 'y.example' ] );
check( 'a field the form did not send keeps its value', $fields['title']['value'] === 'Again' && $stored['title'] === 'Again' );
check( 'the secret posted empty is kept, and still never echoed', $fields['apiKey']['set'] === true && array_key_exists( 'value', $fields['apiKey'] ) === false && $stored['apiKey'] === 'k-1' );
check( 'fields has to be an array, the key a known feature', callFeatures( $appData, 'apiSettings', [ 'key' => 'sample', 'fields' => 'limit=1' ] ) === [ 400, [ 'error' => 'no fields posted' ] ]
	&& callFeatures( $appData, 'apiSettings', [ 'key' => 'nope', 'fields' => [] ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiSettings', [ 'fields' => [] ] ) === [ 400, [ 'error' => 'unknown feature' ] ] );
check( 'the activity log names the feature, and a read logs nothing', \Nino\Modules\Features\Admin::log( 'features/activate', [ 'key' => 'sample' ] ) === 'Activate feature "sample"'
	&& \Nino\Modules\Features\Admin::log( 'features/settings', [ 'key' => 'sample' ] ) === 'Edit settings of feature "sample"' && \Nino\Modules\Features\Admin::log( 'features/list', [] ) === ''
	&& \Nino\Modules\Features\Admin::log( 'features/install', [ 'key' => 'sample', 'version' => '1.3.0' ] ) === 'Install feature "sample" 1.3.0' && \Nino\Modules\Features\Admin::log( 'features/catalogue', [] ) === '' );

// The catalogue, switched off: the one catalogue answer that reads no
// catalogue and installs nothing - this run's features directory is the
// checkout's fixtures. The panel phrases it itself, in the session locale,
// reading its words through the project path (Admin::textFills()), which
// here has to be the checkout's; the rest of the catalogue half is
// tests/catalogue-smoke.php's, over a features directory of its own
$off = $appData;
$off['/nino/catalogue/url']			= '';
$off['./nino/filesystem/path']	= dirname( __DIR__ );
$offWords = (array) include dirname( __DIR__ ). '/_admin/Nino/Modules/Features/text/de_DE.php';
check( 'apiCatalogue with the catalogue switched off is a 400 in the panel\'s own words, in the session locale, and apiList says the same with an empty catalogueUrl and no cached catalogue',
	callFeatures( $off, 'apiCatalogue' ) === [ 400, [ 'error' => $offWords['[[/_admin/features/error/catalogue-off]]'] ] ]
	&& callFeatures( $off, 'apiList' )[1]['catalogueUrl'] === '' && callFeatures( $off, 'apiList' )[1]['catalogue'] === null );

ninoDone( $appData );
