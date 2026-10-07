<?php
declare(strict_types=1);

/**
 *	Nino
 *	features-smoke.php	Contract test for \Nino\Features: discovery below
 *											NINO_FEATURES_DIR, manifest validation, version
 *											constraints, settings with every type, activation with
 *											the install unit applied without overwriting, updates
 *											through the upgrade hook, deactivation, and that whatever
 *											feature directory the checkout carries validates (it ships
 *											none). Runs against tests/fixtures/features; of the
 *											checkout's own features/ it only checks the deny rule
 *											and that any directory there validates.
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


/** A panel of some module that happens to use the uri a Settings tab would ask for */
class FeaturesSmokeCollider {
	public static function adminPanels( array &$appData ): array { return [ self::class ]; }
	public static function actions(): array { return []; }
	public static function nav(): array { return [ 'sample-settings', 'Collider', 90, 'content' ]; }
}

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
check( 'the manifest is normalized: every key present', array_keys( $all['helper'] ) === [ 'key', 'dir', 'module', 'name', 'description', 'manual', 'category', 'maturity', 'version', 'nino', 'php', 'requires', 'data', 'settings', 'components', 'stacks', 'active', 'installed', 'update', 'problems' ]
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
/*	Nothing says a manifest's fields are strings - it is a php file
	somebody put in features/. A (string) cast of an array raises "Array to
	string conversion", a level \Nino\Runtime treats as fatal, so the
	reader whose whole job is refusing a bad manifest with a sentence took
	the request down one line before saying what was wrong. manifestFails()
	asserts exactly one warning, which is what makes these fail otherwise	*/
check( 'a field that is an array is refused like any other wrong value, and raises nothing on the way',
	manifestFails( $manifestDir, 'ArrayKey', [ 'key' => [ 'x' ], 'name' => 'x', 'version' => '1.0.0' ], '"key"' )
	&& manifestFails( $manifestDir, 'ArrayVersion', [ 'name' => 'x', 'version' => [ '1.0.0' ] ], '"version"' )
	&& manifestFails( $manifestDir, 'ArrayNino', [ 'name' => 'x', 'version' => '1.0.0', 'nino' => [ '^1.0' ] ], '"nino"' )
	&& manifestFails( $manifestDir, 'ArrayModule', [ 'name' => 'x', 'version' => '1.0.0', 'module' => [ 'x' ] ], '"module"' ) );

check( 'requires lists feature keys, itself left out', manifestFails( $manifestDir, 'BadReq', [ 'name' => 'x', 'version' => '1.0.0', 'requires' => [ 'Not Slug' ] ], '"requires"' )
	&& \Nino\Features::manifest( writeManifest( $manifestDir, 'SelfReq', [ 'name' => 'x', 'version' => '1.0.0', 'requires' => [ 'selfreq', 'a', 'a', 'b' ] ] ) )['requires'] === [ 'a', 'b' ] );
check( 'data paths stay below /data/', manifestFails( $manifestDir, 'BadData', [ 'name' => 'x', 'version' => '1.0.0', 'data' => [ '/config.php' ] ], '"data"' )
	&& manifestFails( $manifestDir, 'DotData', [ 'name' => 'x', 'version' => '1.0.0', 'data' => [ '/data/../config.php' ] ], '"data"' ) );
/*	...and something has to follow it. '/data/' does start with '/data/' and is
	not a path below it - it is the directory itself, and a manifest naming it
	claimed every file this framework keeps there: the throttling counters, the
	catalogue cache, the lock directory. All of them then travelled in every
	backup, and a restore wrote them back	*/
check( '...and the data directory itself is not a path below itself', manifestFails( $manifestDir, 'RootData', [ 'name' => 'x', 'version' => '1.0.0', 'data' => [ '/data/' ] ], '"data"' )
	&& manifestFails( $manifestDir, 'RootData2', [ 'name' => 'x', 'version' => '1.0.0', 'data' => [ '/data//' ] ], '"data"' ) );

// The components and the stacks a manifest declares are read by the schema the
// Components module registers them by, so a bad one is refused here, with the
// name of the component, and not when a request renders a page
$goodComponent = [ 'label' => 'Probe', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'select', 'options' => [ 'a', 'b' ], 'default' => 'a' ] ] ];
check( 'components and stacks are a name => schema map', manifestFails( $manifestDir, 'BadComponents', [ 'name' => 'x', 'version' => '1.0.0', 'components' => 'title' ], '"components"' )
	&& manifestFails( $manifestDir, 'BadStacks', [ 'name' => 'x', 'version' => '1.0.0', 'stacks' => 'rows' ], '"stacks"' ) );
check( 'a component whose schema does not validate refuses the manifest and names the component', manifestFails( $manifestDir, 'BadComponent', [ 'name' => 'x', 'version' => '1.0.0', 'components' => [ 'probe' => [ 'label' => 'x', 'source' => 'weird' ] ] ], 'component "probe": "source"' )
	&& manifestFails( $manifestDir, 'BadComponentName', [ 'name' => 'x', 'version' => '1.0.0', 'components' => [ 'Not A Slug' => $goodComponent ] ], 'a slug' )
	&& manifestFails( $manifestDir, 'BadStack', [ 'name' => 'x', 'version' => '1.0.0', 'stacks' => [ 'rows' => [ 'label' => 'x', 'grid' => 'yes' ] ] ], 'stack "rows": "grid"' ) );
check( 'a manifest that declares them is read complete, the schemas normalized; one that does not has none', \Nino\Features::manifest( writeManifest( $manifestDir, 'WithComponents', [ 'name' => 'x', 'version' => '1.0.0', 'components' => [ 'probe' => $goodComponent ], 'stacks' => [ 'rows' => [ 'label' => 'Rows', 'grid' => true ] ] ] ) ) === \Nino\Features::manifest( $manifestDir. '/WithComponents' )
	&& \Nino\Features::manifest( $manifestDir. '/WithComponents' )['components']['probe']['preview'] === 'block'
	&& \Nino\Features::manifest( $manifestDir. '/WithComponents' )['components']['probe']['attributes']['tone']['options'] === [ 'a', 'b' ]
	&& \Nino\Features::manifest( $manifestDir. '/WithComponents' )['stacks']['rows'] === [ 'label' => 'Rows', 'grid' => true, 'preview' => 'cells', 'attributes' => [] ]
	&& \Nino\Features::manifest( $manifestDir. '/Mini' )['components'] === [] && \Nino\Features::manifest( $manifestDir. '/Mini' )['stacks'] === [] && ninoWarnings() === [] );
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
// The maturity is free text, a word beside the name: one string or one per
// locale, short, and optional
check( 'a maturity is optional and defaults to none', \Nino\Features::manifest( $manifestDir. '/Mini' )['maturity'] === '' );
check( 'one string is kept, and so is a locale => string map', \Nino\Features::manifest( writeManifest( $manifestDir, 'Tagged', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => 'Beta' ] ) )['maturity'] === 'Beta'
	&& \Nino\Features::manifest( writeManifest( $manifestDir, 'TaggedMap', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => [ 'en_US' => 'Example', 'de_DE' => 'Beispiel' ] ] ) )['maturity'] === [ 'en_US' => 'Example', 'de_DE' => 'Beispiel' ] );
check( 'twenty-four characters are allowed, counted as characters and not as bytes, and not counting the space around them', \Nino\Features::manifest( writeManifest( $manifestDir, 'Long24', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => str_repeat( 'ä', 24 ) ] ) )['maturity'] === str_repeat( 'ä', 24 )
	&& \Nino\Features::manifest( writeManifest( $manifestDir, 'Padded', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => '  '. str_repeat( 'a', 24 ). '  ' ] ) ) !== null );
check( 'one longer, empty or of another type is refused, and the message names the field', manifestFails( $manifestDir, 'LongMaturity', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => str_repeat( 'a', 25 ) ], '"maturity"' )
	&& manifestFails( $manifestDir, 'LongMaturityMap', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => [ 'en_US' => 'ok', 'de_DE' => str_repeat( 'a', 25 ) ] ], '"maturity"' )
	&& manifestFails( $manifestDir, 'IntMaturity', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => 3 ], '"maturity"' )
	&& manifestFails( $manifestDir, 'BlankMaturity', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => '   ' ], '"maturity"' )
	&& manifestFails( $manifestDir, 'MapMaturity', [ 'name' => 'x', 'version' => '1.0.0', 'maturity' => [ 'en_US' => [ 'Beta' ] ] ], '"maturity"' ) );
// The manual: the prose the panel puts at the top of a feature's screen,
// localized like the name and the description, and capped so that what does
// not fit a box in a panel stays a README
check( 'a manual is optional and defaults to nothing', \Nino\Features::manifest( $manifestDir. '/Mini' )['manual'] === '' );
check( 'one string is the manual in every language, a map is one per language', \Nino\Features::manifest( writeManifest( $manifestDir, 'Told', [ 'name' => 'x', 'version' => '1.0.0', 'manual' => 'Put `[told]` where it goes.' ] ) )['manual'] === 'Put `[told]` where it goes.'
	&& \Nino\Features::manifest( writeManifest( $manifestDir, 'Both', [ 'name' => 'x', 'version' => '1.0.0', 'manual' => [ 'en_US' => 'Use it.', 'de_DE' => 'So geht es.' ] ] ) )['manual'] === [ 'en_US' => 'Use it.', 'de_DE' => 'So geht es.' ]
	&& \Nino\Features::localized( \Nino\Features::manifest( $manifestDir. '/Both' )['manual'], 'de_DE' ) === 'So geht es.' );
check( 'a manual that is not a string or a map of them is refused', manifestFails( $manifestDir, 'BadManual', [ 'name' => 'x', 'version' => '1.0.0', 'manual' => [ 'en_US' => 5 ] ], '"manual"' ) );
check( 'and one that would not fit a box in a panel is refused as the README it is', manifestFails( $manifestDir, 'LongManual', [ 'name' => 'x', 'version' => '1.0.0', 'manual' => str_repeat( 'a', 10001 ) ], 'at most 10000 characters' ) );

/*	...and the shape a manual is written in now: a section per kind of thing a
	feature adds, each a handle and one line about it. Prose reads well and is
	the wrong thing here - what a developer does with a feature's manual is
	look something up in it, and every feature answering the same questions in
	the same order is worth more than any one of them answering them well	*/
$sectioned = \Nino\Features::manifest( writeManifest( $manifestDir, 'Carded', [ 'name' => 'x', 'version' => '1.0.0', 'manual' => [
	'shortcodes'	=> [ '[carded]' => [ 'en_US' => 'Draws the card.', 'de_DE' => 'Zeichnet die Karte.' ] ],
	'markup'			=> [ 'data-carded' => 'On anything that should carry one.' ],
	'routes'			=> [],
	'panel'				=> [],
	'callbacks'		=> [],
	'install'			=> [ 'templates/page-carded.tpl' => 'The page it renders.' ],
] ] ) );

check( 'a sectioned manual is read as one', is_array( $sectioned['manual'] ) === true
	&& isset( $sectioned['manual']['shortcodes']['[carded]'] ) === true );

$cards = \Nino\Features::manualSections( $sectioned['manual'], 'de_DE' );

check( 'it resolves into one locale, in the order the panel draws them', array_keys( (array) $cards ) === \Nino\Features::MANUAL_SECTIONS );
check( '...with the handle kept and only the line translated', ( $cards['shortcodes'][0] ?? [] ) === [ 'handle' => '[carded]', 'text' => 'Zeichnet die Karte.' ] );
check( '...a line written as one string being that line in every language', ( $cards['markup'][0]['text'] ?? '' ) === 'On anything that should carry one.' );
check( 'an empty section stays, so the panel can say there is nothing', $cards['routes'] === [] && $cards['panel'] === [] );

/*	An entry written as a plain list item has an integer key and no handle -
	a line that stands on its own, which is what a section like 'markup'
	sometimes needs	*/
$bare = \Nino\Features::manifest( writeManifest( $manifestDir, 'Bare', [ 'name' => 'x', 'version' => '1.0.0', 'manual' => [
	'markup' => [ 'The feature ships no form - place the section from the Templates panel.' ],
] ] ) );

check( 'an entry with no handle is a line of its own', ( \Nino\Features::manualSections( $bare['manual'], 'en_US' )['markup'][0] ?? [] )
	=== [ 'handle' => '', 'text' => 'The feature ships no form - place the section from the Templates panel.' ] );

check( 'a section that is not one is refused, and named', manifestFails( $manifestDir, 'BadSection', [ 'name' => 'x', 'version' => '1.0.0',
	'manual' => [ 'shortcode' => [ '[x]' => 'y' ] ] ], 'not "shortcode"' ) );
check( 'a line that is not a string or a map of them is refused', manifestFails( $manifestDir, 'BadLine', [ 'name' => 'x', 'version' => '1.0.0',
	'manual' => [ 'shortcodes' => [ '[x]' => 5 ] ] ], 'the line must be a string' ) );
check( 'a paragraph where a line belongs is refused', manifestFails( $manifestDir, 'LongLine', [ 'name' => 'x', 'version' => '1.0.0',
	'manual' => [ 'shortcodes' => [ '[x]' => str_repeat( 'a', 1001 ) ] ] ], 'one line, not a paragraph' ) );
check( 'a handle nobody could type is refused', manifestFails( $manifestDir, 'LongHandle', [ 'name' => 'x', 'version' => '1.0.0',
	'manual' => [ 'shortcodes' => [ str_repeat( 'a', 121 ) => 'y' ] ] ], 'a handle is' ) );

// The prose form still reads, so a catalogue written before the sections
// existed keeps working - it is the panel that draws whichever it got
check( 'the older prose form is still a manual', \Nino\Features::manualSections( 'Put `[told]` where it goes.', 'en_US' ) === null
	&& \Nino\Features::manualSections( [ 'en_US' => 'Use it.' ], 'en_US' ) === null );

/*	Every shipped feature carries the sectioned shape. A schema half a
	catalogue follows is not a schema, and this is the check that keeps it
	from becoming one	*/
$prose = [];

foreach( (array) glob( __DIR__. '/../features/*', GLOB_ONLYDIR ) as $dir ) {

	$feature = \Nino\Features::manifest( $dir );

	if( $feature !== null && ( $feature['manual'] ?? '' ) !== '' && \Nino\Features::manualSections( $feature['manual'], 'en_US' ) === null )
		$prose[] = basename( $dir );
}

if( $prose !== [] )
	echo '        still prose: ', implode( ', ', $prose ), "\n";

check( 'every feature in this checkout writes its manual in sections', $prose === [] );

check( 'a setting needs a known type', manifestFails( $manifestDir, 'BadType', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'color' ] ] ], 'unknown type' ) );
check( 'a setting name is a lowerCamel identifier', manifestFails( $manifestDir, 'BadSetting', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'api-key' => [ 'type' => 'string' ] ] ], 'setting name' ) );
check( 'a select needs options', manifestFails( $manifestDir, 'NoOptions', [ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'select' ] ] ], '"options"' ) );

// A select whose values are numbers - a page size, a column count - is
// written '12' => '12' like every other option. Php stores a numeric string
// key as an int, so the check for a string key refused the whole manifest
// and the feature vanished from the panel with a message that contradicted
// what its author had written
$numericOptions = \Nino\Features::manifest( writeManifest( $manifestDir, 'NumericOptions', [
	'name' => 'x', 'version' => '1.0.0',
	'settings' => [ 'pageSize' => [ 'type' => 'select', 'options' => [ '12' => 'Zwölf', '24' => 'Vierundzwanzig' ], 'default' => '12' ] ],
] ) );
// The keys come back as ints, because that is what php makes of a numeric
// string key and no amount of casting changes it - what matters is that the
// manifest is read at all and the values are the ones the author wrote
check( 'a select may offer numbers as its values', is_array( $numericOptions ) === true && ninoWarnings() === []
	&& array_map( 'strval', array_keys( $numericOptions['settings']['pageSize']['options'] ?? [] ) ) === [ '12', '24' ] );
check( '...and one of them as its default', ( $numericOptions['settings']['pageSize']['default'] ?? null ) === '12' );
check( 'an option value that is nothing at all is still refused', manifestFails( $manifestDir, 'EmptyOption',
	[ 'name' => 'x', 'version' => '1.0.0', 'settings' => [ 'a' => [ 'type' => 'select', 'options' => [ '' => 'Nichts' ] ] ] ], '"options"' ) );
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
	// An alternative that says nothing used to hold for everything: the
	// parts loop skipped its one empty part and left $holds at the true it
	// started on, so a feature written for a Nino nobody has installed on
	// 1.x without a word
	[ '^9.0 ||', '1.2.0', false ], [ '||', '1.2.0', false ], [ '^9.0 || || ^8.0', '1.2.0', false ],
] as [ $constraint, $version, $expected ] )
	check( str_pad( $constraint, 12 ). ' '. str_pad( $version, 11 ). ' -> '. ( $expected ? 'yes' : 'no' ), \Nino\Features::satisfies( $constraint, $version ) === $expected );
check( 'the default version is the running kernel', \Nino\Features::satisfies( '^'. explode( '.', \Nino\VERSION )[0] ) === true );

// ...and the same shape is refused where a manifest declares it, so it never
// reaches satisfies() from a feature directory at all
check( 'an alternative with nothing in it is not a constraint', \Nino\Features::constraintValid( '^9.0 ||' ) === false
	&& \Nino\Features::constraintValid( '||' ) === false
	&& \Nino\Features::constraintValid( '' ) === false
	&& \Nino\Features::constraintValid( '2.0 || ^1.0' ) === true );
check( 'a manifest declaring one is refused', manifestFails( $manifestDir, 'EmptyAlt', [ 'name' => 'x', 'version' => '1.0.0', 'nino' => '^9.0 ||' ], '"nino"' ) );

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
// (int) saturates rather than failing, so a digit string wider than an int
// became PHP_INT_MAX and was stored as if that was what had been asked for.
// A setting without a 'max' kept it; 'limit' has one, so it was refused -
// but named a bound the value had never been anywhere near. An unbounded
// field is the one that showed it, so this asks both
$wide = static fn( string $n ): array => \Nino\Features::validateSettings( [ 'n' => [ 'type' => 'int' ] ], [ 'n' => $n ] );
check( 'a digit string wider than an int is refused, not saturated', $wide( '99999999999999999999999' )['errors'] === [ 'n' => 'must be a whole number' ]
	&& $wide( (string) PHP_INT_MAX. '0' )['errors'] === [ 'n' => 'must be a whole number' ] );
check( '...at the boundary exactly, either way', $wide( (string) PHP_INT_MAX )['values'] === [ 'n' => PHP_INT_MAX ] && $wide( (string) PHP_INT_MIN )['values'] === [ 'n' => PHP_INT_MIN ]
	&& $wide( '9223372036854775808' )['errors'] !== [] && $wide( '-9223372036854775809' )['errors'] !== [] );
check( '...and leading zeros and a signed zero still pass', $wide( '007' )['values'] === [ 'n' => 7 ] && $wide( '-0' )['values'] === [ 'n' => 0 ] );

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

/*	A list longer than the cap is refused - and refused without having been
	de-duplicated first. The de-duplication was an in_array() over the list
	built so far, so it walked that list once per posted line, and the cap was
	only looked at once the whole post had been through it. Measured on the
	method: 1 000 lines took 2.6 ms, 5 000 took 61 ms, and 20 000 took 883 ms
	of cpu for an answer that was "too long" either way.

	The bound below is generous on purpose - twenty thousand lines now take
	about 0.04 ms, and 883 ms is what it has to stay clear of	*/
$manyLines = [];
for( $i = 0; $i < 20000; $i++ )
	$manyLines[] = 'line number '. $i;

$manyStart	= hrtime( true );
$manyResult	= $v( [ 'hosts' => $manyLines ] );
$manyMs			= ( hrtime( true ) - $manyStart ) / 1e6;

check( 'a list longer than the cap is refused', ( $manyResult['errors']['hosts'] ?? '' ) === 'must be at most 200 lines' );
check( '...as soon as it is too long, not after the whole post has been walked and de-duplicated ('. round( $manyMs, 2 ). ' ms)', $manyMs < 100 );
check( 'and a list at the cap is still accepted whole', count( $v( [ 'hosts' => array_slice( $manyLines, 0, 200 ) ] )['values']['hosts'] ?? [] ) === 200 );
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
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/feature/sample/intro/text]]' => 'Meins' ] );

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
check( 'the unit\'s text keys are added, an existing key kept', \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/feature/sample/intro/text]]'] === 'Meins'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/feature/sample/intro/text]]'] === 'Welcome to the sample.'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/feature/sample/intro/label]]'] === 'Sample label' );
check( 'the unit\'s blacklist and config default are applied', in_array( '/feature/sample/intro/hidden', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true && $stored['/sample/config'] === 'unit-default' );
check( 'the unit\'s "elements" are added: the type is created, its title in the native language, its sections in both available languages', ( static function() use ( $appData ): bool {
	$privacy = \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] );
	return ( $privacy['title'] ?? '' ) === 'Datenschutzerklärung' && array_keys( $privacy['*'] ?? [] ) === [ '*', 'sample', 'sample-more' ] && ( $privacy['de_DE']['sample']['title'] ?? '' ) === 'Beispiel' && ( $privacy['en_US']['sample']['title'] ?? '' ) === 'Sample';
} )() );
$privacyAfterActivation = file_get_contents( \Nino\Filesystem::path( $appData, '/elements/privacy.php' ) );
check( 'the registry now knows it as active and current', \Nino\Features::get( $appData, 'sample' )['active'] === true && \Nino\Features::get( $appData, 'sample' )['installed'] === '1.2.0' && \Nino\Features::get( $appData, 'sample' )['update'] === false );
check( 'the module boots on the next request and its shortcode reads its settings', ( static function() use ( $appData ): bool {
	\Nino\Modules::callModules( $appData, 'init' );
	return ( $appData['./helper/booted'] ?? false ) === true && \Nino\Html::renderHtml( $appData, '[sample]' ) === 'Again';
} )() );
check( 'the workbench lists its panel while it is active', isset( \Nino\Admin\Admin::panels( $appData )['sample'] ) === true );

// Whose a shortcode is: read from the class that answers it, so a feature
// that registers its own - from its class or from one below its namespace -
// is found without anybody keeping a list. A closure has the class it was
// written in, and one written outside any class has none
check( 'a shortcode belongs to the feature whose class answers it, a closure included where it was written in that class; a feature that registered none, and an unknown key, have none', ( static function() use ( $appData ): bool {
	\Nino\Modules::callModules( $appData, 'init' );
	\Nino\Html::addShortcode( $appData, 'sample-below', [ \Nino\Modules\Sample\Admin::class, 'perm' ] );
	\Nino\Html::addShortcode( $appData, 'sample-closure', static fn( array &$appData, array $args ): string => '' );
	\Nino\Modules\Sample::addClosureShortcode( $appData );
	\Nino\Html::addShortcode( $appData, 'sample-function', 'strtoupper' );
	\Nino\Html::addShortcode( $appData, 'sample-string', \Nino\Modules\Sample::class. '::doShortcode' );
	\Nino\Html::addShortcode( $appData, 'other', [ new \Nino\Modules\Helper(), 'init' ] );
	return \Nino\Features::shortcodes( $appData, 'sample' ) === [ 'sample', 'sample-below', 'sample-scoped', 'sample-string' ]
		&& \Nino\Features::shortcodes( $appData, 'helper' ) === [ 'other' ] && \Nino\Features::shortcodes( $appData, 'nope' ) === [];
} )() );

// What the manifest declares becomes shortcodes, once per boot, for the features
// that are listed in '/nino/modules' and for no other
echo "Features::registerComponents - the components and stacks of a manifest\n";

check( 'the reference feature declares a component and a stack, a feature without the keys none', array_keys( $all['sample']['components'] ) === [ 'greeting' ] && array_keys( $all['sample']['stacks'] ) === [ 'rows' ]
	&& $all['helper']['components'] === [] && $all['helper']['stacks'] === [] );

ninoWarnings();
$declared = ninoSandbox( 'components' );
$declared['/nino/dir'] = '';
\Nino\Html::init( $declared );
$declared['/nino/modules'] = [ '\\Nino\\Modules\\Helper' ];
\Nino\Features::registerComponents( $declared );
check( 'a feature that is not listed registers nothing, and one that declares nothing none either', \Nino\Modules\Components::components( $declared ) === [] && \Nino\Modules\Components::stacks( $declared ) === [] && ninoWarnings() === [] );

$declared['/nino/modules'] = [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Sample' ];
\Nino\Features::registerComponents( $declared );
check( 'a listed feature\'s components and stacks are registered with the schema of its manifest', array_keys( \Nino\Modules\Components::components( $declared ) ) === [ 'greeting' ] && array_keys( \Nino\Modules\Components::stacks( $declared ) ) === [ 'rows' ]
	&& \Nino\Modules\Components::components( $declared )['greeting'] === $all['sample']['components']['greeting'] && \Nino\Modules\Components::stacks( $declared )['rows'] === $all['sample']['stacks']['rows']
	&& \Nino\Modules\Components::defaults( $declared, 'greeting' ) === [ 'shout' => '0', 'class' => '' ] && ninoWarnings() === [] );
\Nino\Filesystem::putFileContent( $declared, '/text/global.php', [ '[[/feature/sample/greeting/text]]' => 'Hello [there]' ] );
check( 'the renderer is componentGreeting() of the class: the name in studly caps behind the kind', \Nino\Html::renderHtml( $declared, '[greeting /feature/sample/greeting/text]' ) === '<p class="sample-greeting">Hello &#91;there]</p>'
	&& \Nino\Html::renderHtml( $declared, '[greeting /feature/sample/greeting/text shout="1"]' ) === '<p class="sample-greeting">HELLO &#91;THERE]</p>' && \Nino\Html::renderHtml( $declared, '[greeting /nope/nope/nope/nope]' ) === '' );
\Nino\Elements::insertElementType( $declared, '/rowtype', [ 'title' => [ 'type' => 'string', 'locale' => true ] ] );
\Nino\Elements::insertElement( $declared, '/rowtype/one', [ 'title' => 'One' ], 'de_DE' );
\Nino\Elements::insertElement( $declared, '/rowtype/two', [ 'title' => 'Two' ], 'de_DE' );
check( 'a stack of a feature loops the elements through the kernel\'s renderStack()', \Nino\Html::renderHtml( $declared, '[rows /rowtype sort="-title"][greeting title][/rows]' ) === '<div class="sample-rows"><span><p class="sample-greeting">Two</p></span><span><p class="sample-greeting">One</p></span></div>' );
check( '...and the shortcodes are the feature\'s: the class of the renderer is what says whose they are', \Nino\Features::shortcodes( $declared, 'sample' ) === [ 'greeting', 'rows' ] );

echo "\n";

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
// Present, not active: deactivation keeps data by contract, so a backup taken
// while the feature is off has to keep it too. It did not, and a restore from
// such a backup left config.php recording an installed version whose data was
// gone - the one state deactivation exists to make impossible
check( '...and carries them just the same while the feature is switched off, because deactivating keeps its data', ( static function( array $appData ): bool {
	\Nino\Features::deactivate( $appData, 'sample' );
	$carried = \Nino\Backup::manifest( $appData );
	return in_array( 'data/sample.php', $carried, true ) === true
		&& in_array( 'data/sample-dir/deeper/two.php', $carried, true ) === true;
} )( $appData ) );
/*	And what a backup never carries, whatever asks for it. Backup's own
	docblock calls auth-tries.php and ratelimit.php transient throttling
	counters rather than data, and they were left out by not being listed -
	which held while the list was literals and stopped holding the moment a
	manifest became a source of paths. A restore is what makes it matter:
	restored auth-tries.php re-locks an account somebody already waited out,
	and restored .locks plants lock files for requests that ended weeks ago.

	Driven with a feature claiming the directory itself, which is what the
	validator above now refuses - so this is the second answer to the same
	question, and the one that does not depend on a manifest being well-formed	*/
\Nino\Filesystem::putFileContent( $appData, '/data/auth-tries.php', [ 'x' => 1 ] );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [ 'x' => 1 ] );
\Nino\Filesystem::putFileContent( $appData, '/data/catalogue.php', [ 'x' => 1 ] );
\Nino\Filesystem::putFileContent( $appData, '/data/mail-status.php', [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 1 ] );
\Nino\Filesystem::forceDir( $appData, '/data/.locks' );
file_put_contents( \Nino\Filesystem::path( $appData, '/data/.locks' ). '/probe.lock', '' );

$greedyAppData = $appData;
$greedyAppData['./nino/features/all'] = [ 'greedy' => [
	'key' => 'greedy', 'dir' => \Nino\Features::dir(). '/Greedy', 'data' => [ '/data/' ],
	'active' => false, 'problems' => [], 'version' => '1.0.0', 'installed' => null, 'module' => '',
] ];
/*	Compared with the slashes collapsed: the old walk spelled these
	'data//auth-tries.php', one slash per trailing one in the claim, so a plain
	in_array() would have passed against it for the spelling rather than for
	the file not being there	*/
$greedy = array_map( static fn( string $name ): string => (string) preg_replace( '#/+#', '/', $name ), \Nino\Backup::manifest( $greedyAppData ) );

check( 'a backup carries no throttling counter, whatever a manifest claims', in_array( 'data/auth-tries.php', $greedy, true ) === false
	&& in_array( 'data/ratelimit.php', $greedy, true ) === false );
check( '...nor the catalogue cache, which is fetched again when it is wanted', in_array( 'data/catalogue.php', $greedy, true ) === false );
check( '...nor the record of the last failed mail, which a restore would bring back long after it was fixed', in_array( 'data/mail-status.php', $greedy, true ) === false );
check( '...nor anything hidden, so a restore plants no lock files', array_values( array_filter( $greedy, static fn( string $name ): bool => str_contains( $name, '/.' ) === true ) ) === [] );
check( '...and no entry has a doubled slash from a trailing one somebody wrote', array_values( array_filter( \Nino\Backup::manifest( $greedyAppData ), static fn( string $name ): bool => str_contains( $name, '//' ) === true ) ) === [] );

// ...while what a feature really owns is still carried, which is the half
// this must not break
$ownedAppData = $appData;
$ownedAppData['./nino/features/all'] = [ 'tidy' => [
	'key' => 'tidy', 'dir' => \Nino\Features::dir(). '/Tidy', 'data' => [ '/data/sample-dir/' ],
	'active' => false, 'problems' => [], 'version' => '1.0.0', 'installed' => null, 'module' => '',
] ];
$owned = \Nino\Backup::manifest( $ownedAppData );
check( 'a directory a feature declares is still carried, trailing slash and all', in_array( 'data/sample-dir/one.php', $owned, true ) === true
	&& in_array( 'data/sample-dir/deeper/two.php', $owned, true ) === true );

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
check( '...not even in the elements the unit adds: the file is the one the first activation wrote', file_get_contents( \Nino\Filesystem::path( $appData, '/elements/privacy.php' ) ) === $privacyAfterActivation );

/*	...and "nothing changes" now means nothing is done either. Activating
	sample re-applied helper's whole unit every time - every file copied or
	skipped, every text key walked, config.php written - although helper was
	already on and already at the version its own directory carries, which is a
	state an activation has nothing left to do anything about.

	Observable because helper's unit carries a config default: take it away and
	only something that really applies that unit puts it back	*/
unset( $appData['/helper/config'] );
\Nino\AppData::writeContentData( $appData, [ '/helper/config' ] );
check( 'the default helper\'s unit brings is out of the way', isset( $appData['/helper/config'] ) === false );

check( 'activating sample does not re-apply the unit of a requirement that is already on and current', \Nino\Features::activate( $appData, 'sample' ) === true
	&& isset( $appData['/helper/config'] ) === false );

check( '...while activating helper itself applies it, as it always did', \Nino\Features::activate( $appData, 'helper' ) === true
	&& ( $appData['/helper/config'] ?? null ) === 'unit-default' );

/*	A requirement whose record is older than its directory is an update, and
	that one must not be skipped: the activation of anything requiring it is
	exactly where the update gets carried through	*/
$olderRecord = $appData[ \Nino\Features::STATE_KEY ];
$olderRecord['helper']['version'] = '0.0.9';
$appData[ \Nino\Features::STATE_KEY ] = $olderRecord;
unset( $appData['/helper/config'], $appData['./nino/features/all'] );
\Nino\AppData::writeContentData( $appData, [ \Nino\Features::STATE_KEY, '/helper/config' ] );

check( 'a requirement whose record is older than its directory is activated, not skipped', \Nino\Features::activate( $appData, 'sample' ) === true
	&& ( $appData['/helper/config'] ?? null ) === 'unit-default'
	&& ( $appData[ \Nino\Features::STATE_KEY ]['helper']['version'] ?? null ) === '0.1.0' );

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

// ...and neither the elements: an edited section stays, and a section somebody
// deleted for good - which the module that owns the type writes down under
// '/nino/elements/removed' - does not come back with the next update
\Nino\Elements::updateElement( $appData, '/privacy/sample', [ 'title' => 'Mein Beispiel' ], 'de_DE' );
\Nino\Elements::deleteElement( $appData, '/privacy/sample-more', '*' );
$appData[ \Nino\Elements::REMOVED ] = [ 'privacy' => [ 'sample-more' ] ];
$appData['/nino/features']['sample']['version'] = '0.5.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );
check( 'an update leaves an edited section and does not bring back a deleted one that was written down', \Nino\Features::activate( $appData, 'sample' ) === true
	&& \Nino\Elements::getElement( $appData, '/privacy/sample', 'de_DE' )['title'] === 'Mein Beispiel' && \Nino\Elements::getElement( $appData, '/privacy/sample-more', 'de_DE' ) === false );
unset( $appData[ \Nino\Elements::REMOVED ] );

$appData['/nino/features']['sample']['version'] = '0.0.1';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );
$refused = \Nino\Features::activate( $appData, 'sample' );
check( 'a refused upgrade leaves the record as it was', $refused === 'feature "sample" refused to upgrade from 0.0.1' && \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['version'] === '0.0.1' );

/*	And an update the request cannot apply at all: the directory was replaced
	while the class was already loaded - Catalogue::install() notes that under
	'./nino/features/replaced' - so the class in memory is the previous
	version's, and the hook activate() would call is the old one. Measured on
	the panel's update of a running feature: the new version was recorded with
	its upgrade() never called, and never to be called	*/
$appData['./nino/features/replaced']['sample'] = true;
$deferred = \Nino\Features::activate( $appData, 'sample' );
check( 'an update of a feature whose previous class is loaded is deferred to a new request, the record untouched', $deferred === 'feature "sample" was replaced in this request while its previous version is loaded - the update is applied by activating it in a new request'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['version'] === '0.0.1' );
unset( $appData['./nino/features/replaced'] );
$appData['/nino/features']['sample']['version'] = '1.2.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );

// The same update arriving while the feature is off. deactivate() keeps
// the recorded version on purpose, and a newer directory placed in the
// meantime leaves a switched-off feature off (the panel's install does).
// activate() used to run the hook only for a feature already on - so on
// exactly this path the record jumped to the new version with the
// migration never run, and no later activation could run it either, the
// old version being gone from the record
check( '(switched off for the next check)', \Nino\Features::deactivate( $appData, 'sample' ) === true );
$appData['/nino/features']['sample']['version'] = '0.5.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'], $appData['./sample/upgraded-from'] );
$fromOff = \Nino\Features::activate( $appData, 'sample' );
check( 'activating a switched-off feature whose directory is newer than its record runs the upgrade hook too', $fromOff === true && ( $appData['./sample/upgraded-from'] ?? null ) === '0.5.0'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['sample']['version'] === '1.2.0' && \Nino\Features::get( $appData, 'sample' )['active'] === true );

/*	And what the hook writes to config.php is still there afterwards.
	activate() read the routes before applyUnit() and before the hook, and
	persisted that copy - so a hook doing an ordinary migration with the
	kernel's own mutate() had its work reverted by the activation that called
	it, in one process, with no second request involved. It needed the unit to
	have a route to add for the write to happen at all, which is why one of
	the unit's own is taken back out first.	*/
$appData['./sample/migrate-routes'] = true;
$appData['/nino/features']['sample']['version'] = '0.5.0';

$strippedRoutes = (array) \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
unset( $strippedRoutes['GET://sample-de'] );
$appData['/nino/http/routes'] = $strippedRoutes;

\Nino\AppData::writeContentData( $appData, [ '/nino/features', '/nino/http/routes' ] );
unset( $appData['./nino/features/all'] );

$migrated			= \Nino\Features::activate( $appData, 'sample' );
$routesAfter	= (array) \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];

check( 'what the upgrade hook wrote to config.php survives the activation that called it', $migrated === true && isset( $routesAfter['GET://sample-migrated'] ) === true );
check( '...and the unit\'s own route arrives in the same write', isset( $routesAfter['GET://sample-de'] ) === true );
check( '...and the project\'s own route is untouched by either', ( $routesAfter['GET://sample']['body'] ?? null ) === 'the project\'s own' );

unset( $appData['./sample/migrate-routes'] );

echo "\n";


// --- Deactivation ------------------------------------------------------------

echo "Features::deactivate\n";

check( 'a feature another active one requires stays on', \Nino\Features::deactivate( $appData, 'helper' ) === 'feature "helper" is required by "sample"' );
check( 'an unknown feature cannot be deactivated', \Nino\Features::deactivate( $appData, 'nope' ) === 'unknown feature "nope"' );

$privacyBeforeOff = file_get_contents( \Nino\Filesystem::path( $appData, '/elements/privacy.php' ) );
$off = \Nino\Features::deactivate( $appData, 'sample' );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'deactivation removes the class and nothing else', $off === true && $stored['/nino/modules'] === [ '\\Nino\\Modules\\Helper' ]
	&& $stored['/nino/features']['sample']['settings']['title'] === 'Again' && isset( $stored['/nino/http/routes']['GET://sample-de'] ) === true
	&& is_file( \Nino\Filesystem::path( $appData, '/templates/page-sample.tpl' ) ) === true );
check( 'deactivation deletes none of the sections the unit added', file_get_contents( \Nino\Filesystem::path( $appData, '/elements/privacy.php' ) ) === $privacyBeforeOff );
check( 'the registry reads it as inactive, its record kept', \Nino\Features::get( $appData, 'sample' )['active'] === false && \Nino\Features::get( $appData, 'sample' )['installed'] === '1.2.0' );
check( 'the panel is gone with it', isset( \Nino\Admin\Admin::panels( $appData )['sample'] ) === false );
check( 'now the requirement may go too', \Nino\Features::deactivate( $appData, 'helper' ) === true && \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [] );
check( 'deactivating an inactive feature is harmless', \Nino\Features::deactivate( $appData, 'helper' ) === true );
check( 'reactivation finds the settings as they were', \Nino\Features::activate( $appData, 'sample' ) === true && \Nino\Features::settings( $appData, 'sample' )['title'] === 'Again' );

echo "\n";


// --- A unit file that cannot be copied ----------------------------------------

echo "Features::activate - a unit file that cannot be copied is a refusal\n";

/*	copyFile() wrote a unit's template with an unchecked file_put_contents(),
	and applyUnit() answered nothing to activate() either way. A target that
	could not be written raised php's own warning - fatal under the framework's
	handler, a 500 with half the unit copied - and under a handler that carries
	on (a project's own, this suite's) the activation went on to list the class
	and record the version: a success with the template missing. The target
	here is a directory where the template has to go, which is what a
	permission or a full disk does, provoked without either	*/
$blockedAppData = ninoSandbox( 'features-blocked' );
$blockedAppData['/nino/http/routes'] = [ 'GET://' => [ 'uri' => '/home', 'body' => '' ] ];
\Nino\AppData::writeContentData( $blockedAppData, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native', '/nino/http/routes' ] );
\Nino\Filesystem::forceDir( $blockedAppData, '/templates/page-sample.tpl' );
ninoWarnings();

$blocked			= \Nino\Features::activate( $blockedAppData, 'sample' );
$blockedStored	= \Nino\Filesystem::getFileContent( $blockedAppData, '/config.php', [] );

check( 'a unit file that cannot be copied refuses the activation, naming the file', $blocked === 'feature "sample" could not be activated: could not copy /templates/page-sample.tpl' );
check( '...and nothing is listed or recorded for it', in_array( '\\Nino\\Modules\\Sample', (array) ( $blockedStored['/nino/modules'] ?? [] ), true ) === false
	&& isset( $blockedStored['/nino/features']['sample'] ) === false );
// The rescan of the fixture directory warns about Broken as always; what
// must not be among the warnings is php's own, which the handler this
// framework runs under ends the request on
check( '...and no engine warning is raised on the way', array_filter( ninoWarnings(), static fn( string $w ): bool => str_contains( $w, 'file_put_contents' ) ) === [] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $blockedAppData ) );

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
$unitConfig = [];
\Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE' ], $routes, $blacklist, $unitConfig, false );
check( 'add-only: an existing route, template, file and text key stay, what is missing arrives', $routes['GET://u']['uri'] === '/project-u'
	&& file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.tpl' ) ) === 'project' && file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.de_DE.tpl' ) ) === 'unit de'
	&& file_get_contents( \Nino\Filesystem::path( $appData, '/images/u.txt' ) ) === 'project image' && \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/u/g]]'] === 'project' && $blacklist === [ '/u/x' ] );
check( 'a locale-gated template for a locale not asked for is never copied', is_file( \Nino\Filesystem::path( $appData, '/templates/page-u.fr_FR.tpl' ) ) === false );

\Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE' ], $routes, $blacklist, $unitConfig, true );
check( 'overwrite: the unit replaces all of it', $routes['GET://u']['uri'] === '/u'
	&& file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-u.tpl' ) ) === 'unit' && file_get_contents( \Nino\Filesystem::path( $appData, '/images/u.txt' ) ) === 'unit image'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/u/g]]'] === 'unit' && $blacklist === [ '/u/x', '/u/x' ] );
check( 'a unit without a manifest applies nothing', ( static function() use ( $appData, $sandbox ): bool {
	$r = [ 'a' => 1 ]; $b = []; $c = [];
	\Nino\Features::applyUnit( $appData, $sandbox. '/no-unit', [ 'de_DE' ], $r, $b, $c );
	return $r === [ 'a' => 1 ] && $b === [] && $c === [] && \Nino\Features::readUnitManifest( $sandbox. '/no-unit' ) === null;
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
	check( $method. ' is 401 without an account', callFeatures( $appData, $method, $data ) === [ 401, [ 'error' => 'not logged in', 'code' => 'session' ] ] );

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
check( 'every entry has the same keys', array_keys( $byKey['sample'] ) === [ 'key', 'name', 'description', 'manual', 'manualSections', 'category', 'maturity', 'version', 'installed', 'active', 'update', 'requires', 'problems', 'settings', 'settingsTab' ] );
check( 'names and descriptions arrive in the session locale - de_DE, the native language, since none was chosen', $byKey['sample']['name'] === 'Beispiel-Feature' && $byKey['sample']['description'] === 'Prüft den ganzen Feature-Vertrag.'
	&& $byKey['helper']['name'] === 'Helper' && $byKey['helper']['description'] === '' );
// The slug, not a word: the categories are named in the panel's own fills, so
// the script labels the ones this workbench knows and shows the rest as they
// are - see tests/admin-features-js-smoke.js
// The word is localized here like the name: the badge is drawn as it stands
check( 'the maturity arrives in the session locale, empty for a feature that names none', $byKey['sample']['maturity'] === 'Beispiel' && $byKey['helper']['maturity'] === '' );
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

/*	The settings of a feature with a panel live in a tab of that panel: the
	registry builds the tab, the Features panel's entry says which, and
	nothing else about the form changes - the fields above are the same	*/
check( 'the entry says where the settings are edited: the tab of the feature\'s own panel, nothing for one without a panel', $byKey['sample']['settingsTab'] === 'sample-settings'
	&& $byKey['helper']['settingsTab'] === '' && $byKey['old']['settingsTab'] === '' );
$tab = \Nino\Admin\Admin::panels( $appData )['sample']['tabs']['sample-settings'] ?? null;
check( 'a tab joins the feature\'s panel: the Features permission, the system group and label so the Roles list is unchanged, the mount named by the key', $tab !== null
	&& $tab['perm'] === '/_admin/features/manage' && $tab['group'] === 'system' && $tab['label'] === '/_admin/nav/features'
	&& $tab['tab'] === '/_admin/features/tab/settings' && $tab['panes'] === [ 'feature-settings-sample' ] && $tab['parent'] === 'sample'
	&& $tab['class'] === \Nino\Modules\Features\Settings::class && $tab['feature'] === 'sample' && $tab['assets'] === [] && $tab['template'] === '' && $tab['tabs'] === [] );
check( 'it follows the panel\'s own screen, and carries the Features panel\'s words', array_keys( \Nino\Admin\Admin::panels( $appData )['sample']['tabs'] ) === [ 'sample-settings' ]
	&& $tab['text'] === \Nino\Modules\Features\Admin::text() && \Nino\Modules\Features\Settings::actions() === [] );
check( 'a feature without a panel gets no tab, and neither does the Features panel itself', ( static function() use ( $appData ): bool {
	foreach( \Nino\Admin\Admin::panels( $appData ) as $uri => $panel )
		if( $uri !== 'sample' && array_filter( $panel['tabs'], static fn( array $t ): bool => isset( $t['feature'] ) ) !== [] )
			return false;
	return \Nino\Admin\Admin::panels( $appData )['features']['tabs'] === [];
} )() );
check( 'a feature panel without settings gets none either', ( static function() use ( $appData ): bool {
	$bare = $appData;
	unset( $bare['./_admin/panels'] );
	$bare['./nino/features/all']['sample']['settings'] = [];
	return \Nino\Admin\Admin::panels( $bare )['sample']['tabs'] === [];
} )() );
/*	A panel that already holds the tab's uri: the tab is not attached, and the
	warning names the feature and who holds the uri - a module cannot be made to
	lose a screen to it	*/
check( 'a Settings tab whose uri is taken is left out with a warning naming the feature and the holder', ( static function() use ( $appData ): bool {
	$taken = $appData;
	unset( $taken['./_admin/panels'] );
	$taken['/nino/modules'][] = 'FeaturesSmokeCollider';
	ninoWarnings();
	$panels = \Nino\Admin\Admin::panels( $taken );
	$warnings = ninoWarnings();
	return $panels['sample']['tabs'] === [] && isset( $panels['sample-settings'] ) === true && $panels['sample-settings']['class'] === 'FeaturesSmokeCollider'
		&& count( $warnings ) === 1 && str_contains( $warnings[0], 'Settings tab of feature \'sample\'' ) && str_contains( $warnings[0], 'sample-settings' ) && str_contains( $warnings[0], 'FeaturesSmokeCollider' );
} )() );
check( 'a registry without a feature\'s panel does not read the manifests at all, so a broken one cannot take the workbench down', ( static function() use ( $appData ): bool {
	$none = $appData;
	unset( $none['./_admin/panels'], $none['./nino/features/all'] );
	$none['/nino/modules'] = [];
	ninoWarnings();
	$panels = \Nino\Admin\Admin::panels( $none );
	return isset( $panels['sample'] ) === false && isset( $none['./nino/features/all'] ) === false && ninoWarnings() === [];
} )() );
check( 'an inactive feature\'s panel is gone, and its tab with it', ( static function() use ( $appData ): bool {
	$off = $appData;
	unset( $off['./_admin/panels'] );
	$off['/nino/modules'] = [ '\\Nino\\Modules\\Helper' ];
	unset( $off['./nino/features/all'] );
	return isset( \Nino\Admin\Admin::panels( $off )['sample'] ) === false;
} )() );
check( 'the roles list offers the Features permission once, as it always did', ( static function() use ( $appData ): bool {
	$offers = array_filter( \Nino\Modules\Users\Admin::permOptions( $appData ), static fn( array $o ): bool => $o['perm'] === '/_admin/features/manage' );
	return count( $offers ) === 1 && array_values( $offers )[0] === [ 'perm' => '/_admin/features/manage', 'label' => '/_admin/nav/features', 'group' => 'system', 'offered' => true ];
} )() );
check( 'every tab of the registry has its action map: the Settings tab brings none, so the seven actions stay the Features panel\'s', ( static function() use ( $appData ): bool {
	$actions = \Nino\Admin\Admin::actions( $appData );
	return $actions['features/settings'] === [ \Nino\Modules\Features\Admin::class, 'apiSettings' ] && count( array_filter( array_keys( $actions ), static fn( string $a ): bool => str_starts_with( $a, 'features/' ) ) ) === 7;
} )() );
check( 'the dashboard shows one Features tile, not one per tab', ( static function() use ( $appData ): bool {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Dashboard\Admin::apiSummary( $appData, $request );
	return count( array_filter( $request['/nino/http/response']['body']['tiles'], static fn( array $t ): bool => $t['panel'] === 'features' ) ) === 1;
} )() );

// Whose it is to see: the tab is on the Features permission, so somebody who
// holds the feature's own permission alone still does not see the settings
\Nino\Auth::insertUser( $appData, 'sample@example.com', 'correct horse battery staple', [ \Nino\Modules\Sample\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'sample@example.com', 'correct horse battery staple' );
$visible = \Nino\Admin\Admin::visiblePanels( $appData );
check( 'an account holding only the feature\'s permission gets the panel, no tab, no strip and no mount', $visible['sample']['own'] === true && $visible['sample']['tabs'] === []
	&& str_contains( \Nino\Admin\Admin::panesHtml( $appData ), 'admin-tabbutton-sample-settings' ) === false
	&& str_contains( \Nino\Admin\Admin::panesHtml( $appData ), 'feature-settings-sample' ) === false );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$visible = \Nino\Admin\Admin::visiblePanels( $appData );
$html = \Nino\Admin\Admin::panesHtml( $appData );
check( 'a developer holding the Features permission only gets the tab alone, as a pane with its mount', array_keys( $visible['sample']['tabs'] ) === [ 'sample-settings' ] && $visible['sample']['own'] === false
	&& str_contains( $html, 'id="feature-settings-sample"' ) === true && str_contains( $html, 'data-tab="sample-settings"' ) === true );
\Nino\Auth::insertUser( $appData, 'both@example.com', 'correct horse battery staple', [ \Nino\Modules\Features\Admin::MANAGE_PERM, \Nino\Modules\Sample\Admin::MANAGE_PERM ] );
\Nino\Auth::loginUser( $appData, 'both@example.com', 'correct horse battery staple' );
$html = \Nino\Admin\Admin::panesHtml( $appData );
check( 'an account holding both gets a strip with both tabs', array_keys( \Nino\Admin\Admin::visiblePanels( $appData )['sample']['tabs'] ) === [ 'sample-settings' ]
	&& str_contains( $html, 'id="admin-tabbutton-sample"' ) === true && str_contains( $html, 'id="admin-tabbutton-sample-settings"' ) === true
	&& strpos( $html, 'id="admin-tabbutton-sample"' ) < strpos( $html, 'id="admin-tabbutton-sample-settings"' ) );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Activation through the action
[ $status, $body ] = callFeatures( $appData, 'apiActivate', [ 'key' => 'old' ] );
check( 'activating an incompatible feature is a 400 carrying the kernel\'s reason', $status === 400 && str_starts_with( $body['error'], 'feature "old" cannot be activated: requires Nino ^0.9' ) );
check( 'an unknown, a malformed or a missing key is a 400 before the kernel is asked', callFeatures( $appData, 'apiActivate', [ 'key' => 'nope' ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiActivate', [ 'key' => [ 'sample' ] ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiActivate', [ 'key' => '../etc' ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiDeactivate', [] ) === [ 400, [ 'error' => 'unknown feature' ] ] );
check( 'none of that wrote anything', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [ '\\Nino\\Modules\\Helper', '\\Nino\\Modules\\Sample' ] );

[ $status, $body ] = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'helper' ] );
check( 'deactivating a feature another active one requires is refused with the kernel\'s reason, and says nothing about shortcodes', $status === 400 && $body['error'] === 'feature "helper" is required by "sample"' && isset( $body['found'] ) === false );

/*	What a deactivation leaves behind: the shortcodes of the feature, in the
	templates, the texts and the elements of the project. Registered on the
	request first - the panel answers for a request that booted the module	*/
\Nino\Modules::callModules( $appData, 'init' );
file_put_contents( \Nino\Filesystem::path( $appData, '/templates/page-x.tpl' ), '<p>[sample]</p><p>[sample-other]</p>' );
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$de['[[/found/raw]]']				= 'Vorher [sample x="1"]Mitte[/sample] nachher';
$de['[[/found/neutralized]]']	= 'Nur &#91;sample&#93; als Text';
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', $de );
\Nino\Filesystem::putFileContent( $appData, '/elements/found.php', [
	'title'				=> 'Found [sample]',
	'model'				=> [ 'body' => [ 'type' => 'string', 'locale' => true ], 'list' => [ 'type' => 'string' ] ],
	'*'						=> [ 'one' => [ 'list' => [ 'x', '[sample]' ] ] ],
	'de_DE'				=> [ 'one' => [ 'body' => 'Mit [sample] drin' ], 'two' => [ 'body' => 'Ohne' ] ],
] );

[ $status, $body ] = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'sample' ] );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$found = $body['found'] ?? [];
check( 'deactivating answers, besides the entry, where its shortcodes still stand: opening tags only, one entry per shortcode, a total and the places',
	$status === 200 && count( $found ) === 1 && $found[0]['shortcode'] === 'sample' && $found[0]['total'] === 4 && array_keys( $found[0] ) === [ 'shortcode', 'total', 'places' ] );
check( '...a template (the one that has the neighbouring shortcode too), a text with raw brackets, and the element in both its buckets - nothing else', $found[0]['places'] === [
	[ 'kind' => 'template', 'where' => 'page-x.tpl' ],
	[ 'kind' => 'text', 'where' => '/found/raw (de_DE)' ],
	[ 'kind' => 'element', 'where' => 'found/one list' ],
	[ 'kind' => 'element', 'where' => 'found/one body (de_DE)' ],
] );
check( 'deactivating answers the refreshed entry, and only the class left config.php', $status === 200 && $body['feature']['key'] === 'sample' && $body['feature']['active'] === false && $body['feature']['installed'] === '1.2.0'
	&& $stored['/nino/modules'] === [ '\\Nino\\Modules\\Helper' ] && $stored['/nino/features']['sample']['settings']['title'] === 'Again' );
check( 'the dashboard tile counts what is active now', \Nino\Modules\Features\Admin::summary( $appData ) === [ 'value' => 1, 'label' => '/_admin/features/label/active' ] );
[ $status, $body ] = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'helper' ] );
check( 'a feature that registered no shortcode answers found as an empty list', $status === 200 && $body['found'] === [] );
check( '...and privacy as an empty list: it brought no section', $body['privacy'] === [] );
\Nino\Features::activate( $appData, 'helper' );

[ $status, $body ] = callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] );
check( 'activating a valid key answers the refreshed entry, localized like the list', $status === 200 && $body['feature']['active'] === true && $body['feature']['update'] === false && $body['feature']['name'] === 'Beispiel-Feature'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [ '\\Nino\\Modules\\Helper', '\\Nino\\Modules\\Sample' ] );
check( '...and names what it switched on: itself, as the requirement was on already', $body['switchedOn'] === [ [ 'key' => 'sample', 'name' => 'Beispiel-Feature' ] ] && isset( $body['found'] ) === false );
check( '...and the tile follows', \Nino\Modules\Features\Admin::summary( $appData )['value'] === 2 );
check( 'activating one that is on already switches nothing on', callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] )[1]['switchedOn'] === [] );
callFeatures( $appData, 'apiDeactivate', [ 'key' => 'sample' ] );
callFeatures( $appData, 'apiDeactivate', [ 'key' => 'helper' ] );
check( 'with the requirement off too, both are named: the one asked for first, the requirement after it', callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] )[1]['switchedOn'] === [ [ 'key' => 'sample', 'name' => 'Beispiel-Feature' ], [ 'key' => 'helper', 'name' => 'Helper' ] ] );
check( 'a refused activation is a 400 without switchedOn', ( static function() use ( $appData ): bool {
	[ $status, $body ] = callFeatures( $appData, 'apiActivate', [ 'key' => 'old' ] );
	return $status === 400 && isset( $body['switchedOn'] ) === false;
} )() );

// A shortcode used everywhere is a list nobody reads to the end: ten places are
// named and the total says how many there are
for( $n = 1; $n <= 12; $n++ )
	file_put_contents( \Nino\Filesystem::path( $appData, '/templates/page-cap-'. sprintf( '%02d', $n ). '.tpl' ), '[sample]' );
$found = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'sample' ] )[1]['found'];
check( 'at most ten places are named for one shortcode, the total counts all of them', count( $found ) === 1 && $found[0]['total'] === 16 && count( $found[0]['places'] ) === 10 );
callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] );

// What the privacy policy still says about a feature that is switched off - or
// removed, for which see catalogue-smoke - is named when the Legal module is on:
// the sections the unit brought that nobody hid or deleted, by their titles in
// the language of the workbench. Nothing is deleted by switching off
check( 'while the Legal module is not on, nothing is said about the privacy policy', callFeatures( $appData, 'apiDeactivate', [ 'key' => 'sample' ] )[1]['privacy'] === [] );
callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] );
$appData['/nino/modules'][] = '\\Nino\\Modules\\Legal';
\Nino\Elements::updateElement( $appData, '/privacy/sample-more', [ 'hidden' => true ], '*' );
[ $status, $body ] = callFeatures( $appData, 'apiDeactivate', [ 'key' => 'sample' ] );
check( 'with it on, switching a feature off names the sections of the policy that still describe it - not the hidden one', $status === 200 && $body['privacy'] === [ 'Mein Beispiel' ] );
check( '...and the sections are all still there', ( \Nino\Elements::getElement( $appData, '/privacy/sample', 'de_DE' )['title'] ?? '' ) === 'Mein Beispiel' && ( \Nino\Elements::getElement( $appData, '/privacy/sample-more', 'de_DE' )['hidden'] ?? false ) === true );
callFeatures( $appData, 'apiActivate', [ 'key' => 'sample' ] );
$appData['/nino/modules'] = array_values( array_diff( $appData['/nino/modules'], [ '\\Nino\\Modules\\Legal' ] ) );

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
// A listener of '/nino/admin/action' sees that a secret was posted, never which: the
// fields the manifest types 'secret' reach it blank, the others as they were
$announced = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/action', static function( array &$appData, array &$event ) use ( &$announced ): void {
	$announced[] = $event;
} );
$_POST['action'] = 'features/settings';
$_POST['data'] = json_encode( [ 'key' => 'sample', 'fields' => [ 'limit' => '13', 'apiKey' => 'k-secret-2', 'title' => 'Seen' ] ] );
$announceRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $announceRequest );
check( 'features/settings reaches a listener with the manifest\'s secret blank and the other fields as posted', count( $announced ) === 1 && $announceRequest['/nino/http/response']['statusCode'] === 200
	&& ( $announced[0]['data']['fields'] ?? null ) === [ 'limit' => '13', 'apiKey' => '', 'title' => 'Seen' ] && ( $announced[0]['data']['key'] ?? null ) === 'sample' && str_contains( json_encode( $announced ), 'k-secret-2' ) === false );
check( '...while the secret itself was stored', \Nino\Features::setting( $appData, 'sample', 'apiKey' ) === 'k-secret-2' );
$_POST['data'] = json_encode( [ 'key' => 'sample', 'fields' => [ 'apiKey' => 'k-1', 'limit' => '12' ] ] );
$announceRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::handlePost( $appData, $announceRequest );
check( '...and the same for the next post, the order of the fields being the poster\'s', ( $announced[1]['data']['fields'] ?? null ) === [ 'apiKey' => '', 'limit' => '12' ] && \Nino\Features::setting( $appData, 'sample', 'apiKey' ) === 'k-1' );
unset( $appData['./nino/callbacks']['/nino/admin/action'], $_POST['action'] );

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
