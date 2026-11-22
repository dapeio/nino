<?php
declare(strict_types=1);

/**
 *	Nino
 *	register-features-smoke.php	Contract test for the components a feature brings in folders -
 *											\Nino\Features::readComponents(), registerComponents(),
 *											registerParts() and \Nino\Components::restore(), groups(),
 *											validateGroup(), partProblems():
 *
 *											A. a feature that is on draws its components, its groups are
 *											   claimed and its standard parts stand in the layer of the
 *											   components of the style bundle, which the hook
 *											   '/nino/components/parts' may replace or take away
 *											B. the cache of the schemas: the second request includes no
 *											   component.php, a file that changed (time or size), another
 *											   kernel, a folder that was emptied or cannot be written, the
 *											   switch - each draws the right page; a file of the cache that
 *											   is not one is none; the file holds data and nothing else
 *											C. two features, one name or one group; a schema that is
 *											   refused is said once and the feature runs without it
 *											D. what a folder may not bring: a name that is no slug, a link,
 *											   a template outside the folder, a foreign key, a group of a
 *											   path, a knob that is a way into the css; what is included
 *											   and when
 *											E. the rule of mixing for a standard part
 *											F. a feature the kernel delivers: a module of the kernel with a
 *											   feature.php - listed, switched on and off, not removed, not
 *											   updated from the catalogue
 *											G. the boot, in a process of its own; the two ways a feature
 *											   brings components side by side; what it costs
 *
 *											The kernel that is delivered with a feature is made here:
 *											the suite copies _nino/ and _admin/ into a directory of its own,
 *											puts the feature of tests/fixtures/register/ into the copy as a
 *											module of the kernel - a second feature of the same files under
 *											another name - and runs against the copy. No file of the
 *											checkout is added for it, and what is run is the code of the
 *											checkout and the real path a module of the kernel is found by.
 *
 *	Usage: php tests/register-features-smoke.php
 */

// --- The copy of the kernel, and the directories of this run -----------------------------------

$registerSource	= dirname( __DIR__ );
$registerWork		= sys_get_temp_dir(). '/nino-register-features-'. bin2hex( random_bytes( 6 ) );
$registerKernel	= $registerWork. '/kernel';

/**	rm -rf, before the kernel is loaded to do it	*/
function registerRemove( string $path ): void {

	if( is_link( $path ) === true || is_file( $path ) === true ) {
		@unlink( $path );
		return;
	}

	foreach( is_dir( $path ) === true ? ( scandir( $path ) ?: [] ) : [] as $entry )
		if( $entry !== '.' && $entry !== '..' )
			registerRemove( $path. '/'. $entry );

	@rmdir( $path );
}

/**	cp -r; $rename turns the words of a twin of a feature into its own	*/
function registerCopy( string $from, string $to, array $rename = [] ): void {

	mkdir( $to, 0755, true );

	foreach( scandir( $from ) ?: [] as $entry ) {

		if( $entry === '.' || $entry === '..' )
			continue;

		$target = $to. '/'. strtr( $entry, $rename );

		if( is_dir( $from. '/'. $entry ) === true )
			registerCopy( $from. '/'. $entry, $target, $rename );
		else
			file_put_contents( $target, $rename === [] ? file_get_contents( $from. '/'. $entry ) : strtr( (string) file_get_contents( $from. '/'. $entry ), $rename ) );
	}
}

mkdir( $registerWork. '/features', 0755, true );
$GLOBALS['registerSandboxes'] = [];
register_shutdown_function( static function() use ( $registerWork ): void {

	foreach( $GLOBALS['registerSandboxes'] as $sandbox )
		registerRemove( $sandbox );

	registerRemove( $registerWork );
} );

registerCopy( $registerSource. '/_nino', $registerKernel. '/_nino' );
registerCopy( $registerSource. '/_admin', $registerKernel. '/_admin' );
mkdir( $registerKernel. '/tests', 0755, true );
copy( $registerSource. '/tests/harness.php', $registerKernel. '/tests/harness.php' );

// The feature of the fixture twice: as a feature below features/, and - Probe becomes Parcel, probe parcel - as a module of the kernel
$registerTwin = [ 'Probe' => 'Parcel', 'probe' => 'parcel' ];
registerCopy( __DIR__. '/fixtures/register/Probe', $registerKernel. '/_nino/Nino/Modules/Parcel', $registerTwin );

define( 'NINO_FEATURES_DIR', $registerWork. '/features' );

require $registerKernel. '/tests/harness.php';

$GLOBALS['ninoRegisterReads']			= [];
$GLOBALS['ninoRegisterGroupReads']	= [];

// --- Helpers -------------------------------------------------------------------------------------

/**	The feature of the fixture, as a directory below features/ - as it came, with the modification time of every file at $time	*/
function registerProbe( int $time = 1700000000 ): string {

	registerRemove( NINO_FEATURES_DIR. '/Probe' );
	registerCopy( __DIR__. '/fixtures/register/Probe', NINO_FEATURES_DIR. '/Probe' );
	registerTouch( NINO_FEATURES_DIR. '/Probe', $time );

	return NINO_FEATURES_DIR. '/Probe';
}

/**	Every file below a directory gets one time: a test that changes a file sets another, so that no change hides in the second of the one before it	*/
function registerTouch( string $dir, int $time ): void {

	foreach( scandir( $dir ) ?: [] as $entry ) {

		if( $entry === '.' || $entry === '..' )
			continue;

		if( is_dir( $dir. '/'. $entry ) === true && is_link( $dir. '/'. $entry ) === false )
			registerTouch( $dir. '/'. $entry, $time );
		else
			touch( $dir. '/'. $entry, $time );
	}
}

/**	A feature of the test's own below features/: a manifest and a class with no method; what it brings is written with registerComponent() and registerGroup()	*/
function registerFeature( string $name, array $manifest = [] ): string {

	$dir = NINO_FEATURES_DIR. '/'. $name;
	registerRemove( $dir );
	mkdir( $dir, 0755, true );

	file_put_contents( $dir. '/feature.php', '<?php return '. var_export( $manifest + [ 'key' => strtolower( $name ), 'name' => $name, 'version' => '1.0.0' ], true ). ';' );
	file_put_contents( $dir. '/'. $name. '.php', '<?php namespace Nino\\Modules { if( class_exists( \'\\Nino\\Modules\\'. $name. '\', false ) === false ) { class '. $name. ' {} } }' );

	return $dir;
}

/**	components/<name>/component.php, and template.tpl where there is a template; $schema is what the file returns	*/
function registerComponent( string $dir, string $name, array $schema, ?string $template = null ): void {

	mkdir( $dir. '/components/'. $name, 0755, true );
	file_put_contents( $dir. '/components/'. $name. '/component.php', '<?php return '. var_export( $schema, true ). ';' );

	if( $template !== null )
		file_put_contents( $dir. '/components/'. $name. '/template.tpl', $template );
}

/**	groups/<group>/group.php, and part/part.css where there is a part	*/
function registerGroup( string $dir, string $group, array $declared, ?string $css = null ): void {

	mkdir( $dir. '/groups/'. $group. '/part', 0755, true );
	file_put_contents( $dir. '/groups/'. $group. '/group.php', '<?php return '. var_export( $declared, true ). ';' );

	if( $css !== null )
		file_put_contents( $dir. '/groups/'. $group. '/part/part.css', $css );
}

/**	The schema of a component that the register takes, with what a test changes put over it	*/
function registerSchema( array $changed = [] ): array {
	return $changed + [ 'group' => 'tidy', 'label' => 'Tidy', 'in' => [ 'page', 'flow' ], 'template' => '<b class="[[class]]">tidy</b>' ];
}

/**	A knob with a ladder that is five steps	*/
function registerKnob( string $key, array $changed = [] ): array {

	[ $component, $name ] = explode( '.', $key );

	return [ $key => $changed + [ 'token' => '--'. $component. '-'. $name, 'label' => 'Knob', 'ladder' => [ '1px', '2px', '3px', '4px', '5px' ] ] ];
}

/**	A project as \Nino\init() finds it before the registrations: its folders, the fills of the page, the modules that are on	*/
function registerProject( string $name, array $modules ): array {

	$appData = ninoSandbox( $name );
	$GLOBALS['registerSandboxes'][] = ninoSandboxDir( $appData );
	$appData['/nino/dir'] = '';
	\Nino\Html::init( $appData );
	$appData['/nino/modules'] = $modules;

	return $appData;
}

/**	One request of a project, with the registrations the boot makes in the order it makes them: the modules' init() is not part of it	*/
function registerBoot( array $project ): array {

	\Nino\Features::registerAssets( $project );
	\Nino\Features::registerComponents( $project );
	\Nino\Features::registerParts( $project );

	return $project;
}

/**	The components and groups the spies saw since the last call	*/
function registerReads(): array {

	$reads = [ $GLOBALS['ninoRegisterReads'], $GLOBALS['ninoRegisterGroupReads'] ];
	$GLOBALS['ninoRegisterReads'] = [];
	$GLOBALS['ninoRegisterGroupReads'] = [];
	sort( $reads[0] );
	sort( $reads[1] );

	return $reads;
}

/**	The file of the cache of a feature	*/
function registerCache( array $appData, string $key = 'probe' ): string {
	return ninoSandboxDir( $appData ). '/private/data/cache/register/'. $key. '.php';
}

/**	A file of the cache that a test writes by hand: opcache is told, as \Nino\Filesystem tells it of what it writes	*/
function registerWrite( string $file, string $content ): void {

	file_put_contents( $file, $content );

	if( function_exists( 'opcache_invalidate' ) === true )
		opcache_invalidate( $file, true );
}

/**	The style bundle of the project, as the [assets] shortcode builds it	*/
function registerBundle( array &$appData ): string {

	\Nino\Modules\Assets::doShortcode( $appData, [ '/.cache/style.css' ] );

	return (string) file_get_contents( \Nino\Filesystem::path( $appData, '/.cache/style.css' ) );
}

/**	The layer a rule of the bundle stands in: the last @layer block that opened before it	*/
function registerLayerOf( string $bundle, string $rule ): ?string {

	$at = strpos( $bundle, $rule );

	if( $at === false )
		return null;

	return preg_match_all( '/@layer ([a-z.]+) \{/', substr( $bundle, 0, $at ), $found ) > 0 ? end( $found[1] ) : null;
}

/**	The page a project draws of the markup: the whole of what the components of the fixture are drawn as	*/
const REGISTER_PAGE = '[probe-box size="l"][probe-line text="Hi"][/probe-box][probe-note text="note"][probe-card text="card"]';

/**	Whether a php file of the cache holds data and nothing else: no variable, no call, no operator but the concatenation of a string with a byte that has no spelling	*/
function registerOnlyData( string $php ): bool {

	foreach( token_get_all( $php ) as $token ) {

		if( is_string( $token ) === true ) {

			if( in_array( $token, [ '(', ')', ',', ';', '[', ']', '-', '.' ], true ) === false )
				return false;

			continue;
		}

		if( in_array( $token[0], [ T_OPEN_TAG, T_RETURN, T_WHITESPACE, T_ARRAY, T_DOUBLE_ARROW, T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER ], true ) === true )
			continue;

		if( $token[0] === T_STRING && in_array( strtolower( $token[1] ), [ 'true', 'false', 'null' ], true ) === true )
			continue;

		return false;
	}

	return true;
}

$modules = [ '\\Nino\\Modules\\Probe' ];
$fixtureTime = 1700000000;
$probeDir = registerProbe( $fixtureTime );

// --- A. A feature that is on draws its components ----------------------------------------------

echo "Features::registerComponents - the folders of a feature that is on\n";

$project = registerProject( 'register-a', $modules );
$a = registerBoot( $project );
$spied = registerReads();

check( 'the components of the folders are registered, one for each folder of components/ with a component.php, in the order of the folders',
	array_keys( \Nino\Components::components( $a ) ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ] && ninoWarnings() === [] );
check( '...each one included once, and the group.php of each group the components name', $spied === [ [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ], [ 'probe', 'probe-more' ] ] );
check( '...with the template.tpl beside it as its template, the markup in the schema where there is none, and the group of the schema as the group',
	\Nino\Components::components( $a )['probe-box']['template'] === '<section class="[[class]]" data-mark="[[mark]]">[[content]]</section>'
	&& \Nino\Components::components( $a )['probe-note']['template'] === '<i class="[[class]]">[[value]]</i>' && \Nino\Components::components( $a )['probe-card']['group'] === 'probe-more' );
check( 'a component is drawn with the renderer of the class of the feature - [ the class, \'component\' and the name in studly caps ] - and the template filled by it; one without a renderer by its template',
	\Nino\Html::renderHtml( $a, REGISTER_PAGE ) === '<section class="probe-box probe-box--size-l" data-mark="box"><p class="probe-line" data-mark="calm">Hi</p></section><i class="probe-note">note</i><article class="probe-card">card</article>' );
check( 'the options of an attribute are the answer of the method the schema names, and the places of a component are the ones the register reads',
	\Nino\Components::components( $a )['probe-line']['attributes']['tone']['options'] === [ 'calm', 'loud' ] && \Nino\Components::allowed( $a, 'probe-box' ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ]
	&& \Nino\Html::renderHtml( $a, '[probe-line text="x" tone="loud"][probe-line text="x" tone="wild"]' ) === '<p class="probe-line probe-line--tone-loud" data-mark="loud">x</p><p class="probe-line" data-mark="calm">x</p>' );
// A shortcode that takes the name of a component of a feature is said, with the feature that has it and who registers the shortcode, and is never asked
class RegisterSmokeShortcodeOwner { public static function answer( array &$appData, array $args ): string { return 'shortcode'; } }
$clash = $a;
ninoWarnings();
\Nino\Html::addShortcode( $clash, 'probe-note', [ RegisterSmokeShortcodeOwner::class, 'answer' ] );
$clashSaid = ninoWarnings();
check( 'a shortcode that takes the name of the component of a feature is one warning that names the name, the key of the feature and the class of the shortcode - and the component is the one that draws',
	count( $clashSaid ) === 1 && str_contains( $clashSaid[0], '"probe-note"' ) && str_contains( $clashSaid[0], ' of probe ' ) && str_contains( $clashSaid[0], 'RegisterSmokeShortcodeOwner' )
	&& \Nino\Html::renderHtml( $clash, '[probe-note text="n"]' ) === '<i class="probe-note">n</i>' );
check( 'the groups are claimed by the feature, with the words, the knobs and the components of each in the order they were registered',
	array_keys( \Nino\Components::groups( $a ) ) === [ 'probe', 'probe-more' ] && \Nino\Components::groups( $a )['probe']['feature'] === 'probe'
	&& \Nino\Components::groups( $a )['probe']['components'] === [ 'probe-box', 'probe-line', 'probe-note' ] && \Nino\Components::groups( $a )['probe-more']['components'] === [ 'probe-card' ]
	&& array_keys( \Nino\Components::groups( $a )['probe']['knobs'] ) === [ 'probe-box.space', 'probe-line.size' ] && \Nino\Components::groups( $a )['probe']['label'] === [ 'en_US' => 'Probe', 'de_DE' => 'Probe' ]
	&& \Nino\Components::groups( $a )['probe-more']['knobs'] === [] && \Nino\Components::groups( $a )['probe']['part'] === true );

$off = registerProject( 'register-off', [] );
$off = registerBoot( $off );
check( 'a feature that is not on registers nothing and includes nothing', \Nino\Components::components( $off ) === [] && \Nino\Components::groups( $off ) === [] && registerReads() === [ [], [] ] );

// A feature with no folder of components reads nothing and keeps nothing
registerFeature( 'Plain' );
$plain = registerBoot( registerProject( 'register-plain', [ '\\Nino\\Modules\\Plain' ] ) );
check( 'a feature that has no folder components/ registers nothing, says nothing and writes no file of the cache', \Nino\Components::components( $plain ) === [] && \Nino\Components::groups( $plain ) === [] && ninoWarnings() === []
	&& is_dir( ninoSandboxDir( $plain ). '/private/data/cache/register' ) === false );

// The class of the feature that has no method of the name of a component
$bare = registerFeature( 'Bare' );
registerGroup( $bare, 'tidy', [ 'label' => 'Tidy' ] );
registerComponent( $bare, 'bare-one', registerSchema( [ 'template' => '' ] ) );
registerComponent( $bare, 'bare-two', registerSchema() );
registerComponent( $bare, 'bare-three', registerSchema( [ 'children' => 'nodes' ] ) );
registerReads();
ninoWarnings();
$b = registerBoot( registerProject( 'register-bare', [ '\\Nino\\Modules\\Bare' ] ) );
$said = ninoWarnings();
check( 'a component with neither a template nor a renderer, and one that draws what it holds and has none, are said and left out; the one with a template is drawn',
	array_keys( \Nino\Components::components( $b ) ) === [ 'bare-two' ] && count( $said ) === 2 && str_contains( $said[0], 'Bare::componentBareOne()' ) === true && str_contains( $said[0], 'no template either' ) === true
	&& str_contains( $said[1], 'Bare::componentBareThree()' ) === true && str_contains( $said[1], 'what it holds' ) === true );
\Nino\Features::registerComponents( $b );
check( '...and said once in a request however often the features are registered', ninoWarnings() === [] );
$reasons = \Nino\Features::componentProblems( $b, 'bare' );
check( 'the Features panel is told the same: a component without a renderer is named with the method it lacks', count( $reasons ) === 2 && str_contains( implode( ' ', $reasons ), 'Bare::componentBareOne()' ) === true && str_contains( implode( ' ', $reasons ), 'Bare::componentBareThree()' ) === true );

echo "\n";


echo "Features::registerParts - the standard part of a group, in the layer of the components\n";

$appData = registerProject( 'register-parts', $modules );
$appData['./nino/filesystem/path'] = $registerKernel;
$asked = new ArrayObject();
\Nino\Callbacks::registerCallback( $appData, \Nino\Components::PARTS, static function( array &$appData, array $answer ) use ( $asked ): ?array {
	$asked[] = $answer;
	return null;
} );
$appData = registerBoot( $appData );
check( 'the hook is asked once for each group that has a part, with the group, the feature and the path of the standard part, in the order of the groups',
	$asked->getArrayCopy() === [
		[ 'group' => 'probe', 'feature' => 'probe', 'css' => '/features/Probe/groups/probe/part/part.css' ],
		[ 'group' => 'probe-more', 'feature' => 'probe', 'css' => '/features/Probe/groups/probe-more/part/part.css' ],
	] );
check( 'the parts are files of the style bundle, once each, in that order, and a request that registers again adds none',
	$appData['/nino/html/assets']['/.cache/style.css'] === [ '/features/Probe/groups/probe/part/part.css', '/features/Probe/groups/probe-more/part/part.css' ] && ( \Nino\Features::registerParts( $appData ) ?? true ) === true
	&& $appData['/nino/html/assets']['/.cache/style.css'] === [ '/features/Probe/groups/probe/part/part.css', '/features/Probe/groups/probe-more/part/part.css' ] );

$bundle = registerBundle( $appData );
check( 'the bundle holds them in the layer of the components, behind the four layers that its first rule declares',
	str_starts_with( $bundle, '/**' ) === true && str_contains( $bundle, \Nino\Modules\Assets::LAYERS ) === true
	&& registerLayerOf( $bundle, '.probe-box { padding' ) === 'nino.components' && registerLayerOf( $bundle, '.probe-card { margin' ) === 'nino.components'
	&& substr_count( $bundle, '.probe-box { padding' ) === 1 && strpos( $bundle, \Nino\Modules\Assets::LAYERS ) < strpos( $bundle, '.probe-box { padding' ) );

// A theme replaces a part, takes it away, or answers what is no part
file_put_contents( $probeDir. '/alt.css', ".probe-box { padding: 9px; }\n" );
$replace = $appData;
unset( $replace['/nino/html/assets'], $replace['./nino/callbacks'] );
$replaced = new ArrayObject();
\Nino\Callbacks::registerCallback( $replace, \Nino\Components::PARTS, static function( array &$appData, array $answer ) use ( $replaced ): array {

	$replaced[] = $answer['group'];

	return $answer['group'] === 'probe' ? [ 'css' => '/features/Probe/alt.css' ] + $answer : ( $answer['group'] === 'probe-more' ? [ 'css' => '' ] + $answer : $answer );
} );
$replace = registerBoot( $replace );
check( 'a callback that answers another css file replaces the part - the answer is what goes into the bundle - and one that answers an empty path takes the part of its group away',
	$replace['/nino/html/assets']['/.cache/style.css'] === [ '/features/Probe/alt.css' ] && $replaced->getArrayCopy() === [ 'probe', 'probe-more' ] );
$replaceBundle = registerBundle( $replace );
check( '...so the bundle has the replacement and neither of the standard parts', str_contains( $replaceBundle, '.probe-box { padding: 9px; }' ) === true && str_contains( $replaceBundle, '.probe-box { padding: var(--probe-box-space)' ) === false
	&& str_contains( $replaceBundle, '.probe-card' ) === false );

$secret = ninoSandboxDir( $appData );
@mkdir( $secret. '/private/data', 0755, true );
mkdir( $secret. '/private/assets', 0755, true );
file_put_contents( $secret. '/private/data/secret.css', '.secret { x: y; }' );
file_put_contents( $secret. '/private/assets/theme.css', '.theme { x: y; }' );

foreach( [ 'a file below the private tree' => [ 'css' => '/private/data/secret.css' ], 'a file of the project\'s theme' => [ 'css' => '/assets/theme.css' ], 'a stylesheet of a feature that is not there' => [ 'css' => '/features/Probe/nothing.css' ], 'a stylesheet of no feature' => [ 'css' => '/features/x.css' ],
	'not an array' => 'nothing', 'no css' => [ 'group' => 'probe' ], 'css is a number' => [ 'css' => 7 ], 'a path out' => [ 'css' => '/features/Probe/../../private/evil.css' ], 'not a css file' => [ 'css' => '/private/config.php' ], 'a scheme' => [ 'css' => 'https://example.org/a.css' ], 'a null byte' => [ 'css' => "/features/Probe/a.css\0.css" ] ] as $what => $answer ) {

	$wrong = $appData;
	unset( $wrong['/nino/html/assets'], $wrong['./nino/callbacks'], $wrong['./nino/features/said'] );
	\Nino\Callbacks::registerCallback( $wrong, \Nino\Components::PARTS, static fn( array &$appData, array $given ): mixed => $answer );
	ninoWarnings();
	$wrong = registerBoot( $wrong );
	$said = ninoWarnings();
	check( 'an answer that is '. $what. ' is said once for each group and the standard part stands',
		$wrong['/nino/html/assets']['/.cache/style.css'] === [ '/features/Probe/groups/probe/part/part.css', '/features/Probe/groups/probe-more/part/part.css' ]
		&& count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, '/nino/components/parts' ) === true ) ) === 2 );
}

$twice = $appData;
unset( $twice['./nino/features/said'] );
ninoWarnings();
\Nino\Features::registerParts( $twice );
check( 'a part that the callback does not touch is no reason to say anything', ninoWarnings() === [] );

echo "\n";


// --- B. The cache of the register ----------------------------------------------------------

echo "The cache of the schemas - what a second request does not read, and what it reads again\n";

registerProbe( $fixtureTime );
registerReads();
$first = registerProject( 'register-cache', $modules );
$firstBooted = registerBoot( $first );
$spied = registerReads();
$drawn = \Nino\Html::renderHtml( $firstBooted, REGISTER_PAGE );
check( 'the first request reads every component.php and group.php and keeps the schemas in a file of its own, below the private tree, named for the feature',
	count( $spied[0] ) === 4 && count( $spied[1] ) === 2 && is_file( registerCache( $first ) ) === true && str_ends_with( registerCache( $first ), '/private/data/cache/register/probe.php' ) );

$second = registerBoot( $first );
check( 'the second request includes no component.php and no group.php, and draws what the first drew', registerReads() === [ [], [] ] && \Nino\Html::renderHtml( $second, REGISTER_PAGE ) === $drawn
	&& \Nino\Components::components( $second ) === \Nino\Components::components( $firstBooted ) && \Nino\Components::groups( $second ) === \Nino\Components::groups( $firstBooted ) && ninoWarnings() === [] );
check( '...with the renderers and the parts as the first request had them', \Nino\Components::components( $second ) !== [] && $second['/nino/html/assets'] === $firstBooted['/nino/html/assets']
	&& \Nino\Html::renderHtml( $second, '[probe-line text="x" tone="loud"]' ) === '<p class="probe-line probe-line--tone-loud" data-mark="loud">x</p>' );

$kept = (string) file_get_contents( registerCache( $first ) );
$stored = (array) include registerCache( $first );
check( 'the file holds the kernel it was written for, the stamp and the clean schemas and groups - and php of data and nothing else',
	array_keys( $stored ) === [ 'kernel', 'stamp', 'components', 'groups' ] && $stored['kernel'] === \Nino\VERSION && array_keys( $stored['components'] ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ]
	&& $stored['components'] === $firstBooted[\Nino\Components::REGISTRY] && array_keys( $stored['groups'] ) === [ 'probe', 'probe-more' ] && registerOnlyData( $kept ) === true );

$changed = static function( string $label, callable $change, callable $seen ) use ( $modules, $fixtureTime ): void {

	registerProbe( $fixtureTime );
	$request = registerProject( 'register-changed', $modules );
	registerBoot( $request );
	registerReads();
	$change();
	$again = registerBoot( $request );
	$spied = registerReads();

	check( $label, $seen( $again, $spied ) === true );
};

// A file that is another size, or another time with the same size, is read again; the page is the one the file says
$time = $fixtureTime + 500;
$changed( 'a template that is another size is read again: every file is read, and the page has the new template',
	static function() use ( $probeDir, $time ): void { file_put_contents( $probeDir. '/components/probe-note/component.php', file_get_contents( $probeDir. '/components/probe-note/component.php' ). "\n// more\n" ); touch( $probeDir. '/components/probe-note/component.php', $time ); },
	static fn( array $again, array $spied ): bool => count( $spied[0] ) === 4 && count( $spied[1] ) === 2 && \Nino\Html::renderHtml( $again, '[probe-note text="n"]' ) === '<i class="probe-note">n</i>' );
$changed( 'a template.tpl of another size at the time it had is read again, and the page has the new template',
	static function() use ( $probeDir, $fixtureTime ): void { file_put_contents( $probeDir. '/components/probe-card/template.tpl', '<article class="[[class]]">[[value]]!!</article>' ); touch( $probeDir. '/components/probe-card/template.tpl', $fixtureTime ); },
	static fn( array $again, array $spied ): bool => count( $spied[0] ) === 4 && \Nino\Html::renderHtml( $again, '[probe-card text="c"]' ) === '<article class="probe-card">c!!</article>' );
$changed( 'a component.php of the same size and another time is read again, and the register has what it says now',
	static function() use ( $probeDir, $time ): void { file_put_contents( $probeDir. '/components/probe-note/component.php', str_replace( "'Note'", "'Nope'", (string) file_get_contents( $probeDir. '/components/probe-note/component.php' ) ) ); touch( $probeDir. '/components/probe-note/component.php', $time ); },
	static fn( array $again, array $spied ): bool => count( $spied[0] ) === 4 && \Nino\Components::components( $again )['probe-note']['label'] === 'Nope' );
$changed( 'a template.tpl of the same size and another time is read again, and the page has the new one',
	static function() use ( $probeDir, $time ): void { file_put_contents( $probeDir. '/components/probe-card/template.tpl', '<section class="[[class]]">[[value]]</section>' ); touch( $probeDir. '/components/probe-card/template.tpl', $time ); },
	static fn( array $again, array $spied ): bool => count( $spied[0] ) === 4 && \Nino\Html::renderHtml( $again, '[probe-card text="c"]' ) === '<section class="probe-card">c</section>' );
$changed( 'a group.php of another size is read again, and a part.css of another size too: the standard part of the group is known to the register as it is now',
	static function() use ( $probeDir, $time ): void { file_put_contents( $probeDir. '/groups/probe-more/group.php', '<?php $GLOBALS[\'ninoRegisterGroupReads\'][] = \'probe-more\'; return [ \'label\' => \'Moreover\' ];' ); touch( $probeDir. '/groups/probe-more/group.php', $time ); unlink( $probeDir. '/groups/probe-more/part/part.css' ); },
	static fn( array $again, array $spied ): bool => count( $spied[1] ) === 2 && \Nino\Components::groups( $again )['probe-more']['label'] === 'Moreover' && \Nino\Components::groups( $again )['probe-more']['part'] === false );
$changed( 'a part.css that changes - and nothing else - is read again: the rule of mixing is asked of what it says now',
	static function() use ( $probeDir, $time ): void { file_put_contents( $probeDir. '/groups/probe-more/part/part.css', ".probe-card { margin: var(--page-space-2); }\n.other { margin: 0; }" ); touch( $probeDir. '/groups/probe-more/part/part.css', $time ); },
	static fn( array $again, array $spied ): bool => \Nino\Components::groups( $again )['probe-more']['part'] === false && \Nino\Components::groups( $again )['probe']['part'] === true );
$changed( 'a component that is added and one that is taken away are seen: the list of the folders is part of what was written',
	static function() use ( $probeDir ): void { registerComponent( $probeDir, 'probe-new', [ 'group' => 'probe', 'label' => 'New', 'in' => [ 'page' ], 'template' => '<u>new</u>' ] ); registerRemove( $probeDir. '/components/probe-card' ); },
	static fn( array $again, array $spied ): bool => array_keys( \Nino\Components::components( $again ) ) === [ 'probe-box', 'probe-line', 'probe-new', 'probe-note' ] && \Nino\Html::renderHtml( $again, '[probe-new]' ) === '<u>new</u>' );
$changed( 'a template.tpl that comes beside a component whose markup is the schema\'s is seen',
	static function() use ( $probeDir, $time ): void { file_put_contents( $probeDir. '/components/probe-note/component.php', "<?php\n\$GLOBALS['ninoRegisterReads'][] = 'probe-note';\nreturn [ 'group' => 'probe', 'label' => 'Note', 'in' => [ 'page', 'flow' ], 'source' => 'text' ];" ); touch( $probeDir. '/components/probe-note/component.php', $time ); file_put_contents( $probeDir. '/components/probe-note/template.tpl', '<em class="[[class]]">[[value]]</em>' ); touch( $probeDir. '/components/probe-note/template.tpl', $time ); },
	static fn( array $again ): bool => \Nino\Html::renderHtml( $again, '[probe-note text="n"]' ) === '<em class="probe-note">n</em>' );

// The limit of the comparison: another text of the same size and the same time is not seen
registerProbe( $fixtureTime );
$limit = registerProject( 'register-limit', $modules );
registerBoot( $limit );
registerReads();
file_put_contents( $probeDir. '/components/probe-card/template.tpl', '<article class="[[class]]">[[value]]</article>' );
touch( $probeDir. '/components/probe-card/template.tpl', $fixtureTime );
file_put_contents( $probeDir. '/components/probe-card/template.tpl', '<section class="[[class]]">[[value]]</section>' );
touch( $probeDir. '/components/probe-card/template.tpl', $fixtureTime );
$limited = registerBoot( $limit );
check( 'the limit of the comparison: a file that is the same size at the same time - a tool that sets neither - is not seen until the folder is emptied',
	registerReads() === [ [], [] ] && \Nino\Html::renderHtml( $limited, '[probe-card text="c"]' ) === '<article class="probe-card">c</article>' );
\Nino\Html::dropTrees( $limit );
$emptied = registerBoot( $limit );
check( '...and once it is, the file is read and the page is the one the file says', count( registerReads()[0] ) === 4 && \Nino\Html::renderHtml( $emptied, '[probe-card text="c"]' ) === '<section class="probe-card">c</section>' );

// Another kernel
registerProbe( $fixtureTime );
$kernel = registerProject( 'register-kernel', $modules );
registerBoot( $kernel );
registerReads();
$old = (array) include registerCache( $kernel );
$old['kernel'] = '0.0.1';
registerWrite( registerCache( $kernel ), '<?php return '. var_export( $old, true ). ';' );
$updated = registerBoot( $kernel );
check( 'a file written for another kernel is not taken: the schemas are read, the page is the one of the folders, and the file is written for this kernel',
	count( registerReads()[0] ) === 4 && \Nino\Html::renderHtml( $updated, REGISTER_PAGE ) === $drawn && ( (array) include registerCache( $kernel ) )['kernel'] === \Nino\VERSION );

// The folder emptied, and a folder that cannot be written
\Nino\Html::dropTrees( $kernel );
check( 'dropTrees() empties the cache of the register with the trees of the templates', is_dir( dirname( registerCache( $kernel ) ) ) === false );
$emptied = registerBoot( $kernel );
check( '...and the next request reads the folders and writes the file again', count( registerReads()[0] ) === 4 && is_file( registerCache( $kernel ) ) === true && \Nino\Html::renderHtml( $emptied, REGISTER_PAGE ) === $drawn );

$blocked = registerProject( 'register-blocked', $modules );
mkdir( ninoSandboxDir( $blocked ). '/private/data/cache', 0755, true );
file_put_contents( ninoSandboxDir( $blocked ). '/private/data/cache/register', 'a file where the folder should be' );
ninoWarnings();
$blockedBooted = registerBoot( $blocked );
$again = registerBoot( $blocked );
check( 'a folder that cannot be written is a feature that is read at every request - the page is right, nothing is said, and no file is written',
	count( registerReads()[0] ) === 8 && \Nino\Html::renderHtml( $blockedBooted, REGISTER_PAGE ) === $drawn && \Nino\Html::renderHtml( $again, REGISTER_PAGE ) === $drawn && ninoWarnings() === []
	&& is_file( ninoSandboxDir( $blocked ). '/private/data/cache/register' ) === true );
$taken = registerProject( 'register-taken', $modules );
\Nino\Filesystem::forceDir( $taken, '/data/cache/register/probe.php' );
$takenBooted = registerBoot( $taken );
check( 'a directory where the file should be is the same: read, drawn, and said nothing', count( registerReads()[0] ) === 4 && \Nino\Html::renderHtml( $takenBooted, REGISTER_PAGE ) === $drawn && ninoWarnings() === [] );

// The switch
$switch = registerProject( 'register-switch', $modules );
registerBoot( $switch );
registerReads();
$switch['/nino/markup/cache'] = false;
$switched = registerBoot( $switch );
check( 'with the switch off a valid file is not read: the folders are read at every request', count( registerReads()[0] ) === 4 && \Nino\Html::renderHtml( $switched, REGISTER_PAGE ) === $drawn );
$mtime = filemtime( registerCache( $switch ) );
registerWrite( registerCache( $switch ), '<?php return [ \'kept\' => true ];' );
registerBoot( $switch );
check( '...and nothing is written: the file that was there is the file that is there', (string) file_get_contents( registerCache( $switch ) ) === '<?php return [ \'kept\' => true ];' && $mtime > 0 );
\Nino\Filesystem::removeDir( dirname( registerCache( $switch ) ) );
registerBoot( $switch );
check( '...and none is created where there is none', is_dir( dirname( registerCache( $switch ) ) ) === false );
$switch['/nino/markup/cache'] = true;
registerBoot( $switch );
check( 'with the switch on again the file is written', is_file( registerCache( $switch ) ) === true );

// Not in a backup
$backup = registerProject( 'register-backup', $modules );
registerBoot( $backup );
\Nino\Filesystem::putFileContent( $backup, '/data/sample.php', [ 'kept' => true ] );
$carried = \Nino\Backup::manifest( $backup );
check( 'the file is in no backup', is_file( registerCache( $backup ) ) === true && array_filter( $carried, static fn( string $name ): bool => str_contains( $name, 'cache' ) === true || str_contains( $name, 'register' ) === true ) === [] );
$greedy = $backup;
$greedy['./nino/features/all'] = [ 'greedy' => [
	'key' => 'greedy', 'dir' => \Nino\Features::dir(). '/Greedy', 'data' => [ '/data/', '/data/cache', '/data/cache/register/', '/data/sample.php' ],
	'active' => false, 'problems' => [], 'version' => '1.0.0', 'installed' => null, 'module' => '',
] ];
$all = array_map( static fn( string $name ): string => (string) preg_replace( '#/+#', '/', $name ), \Nino\Backup::manifest( $greedy ) );
check( '...nor where a feature claims the folder of the cache, or the folder of the register, as its own - and the files that are content still are', array_filter( $all, static fn( string $name ): bool => str_contains( $name, 'cache' ) === true ) === [] && in_array( 'data/sample.php', $all, true ) === true );

// A file that is not the cache is no cache
registerProbe( $fixtureTime );
$real = registerProject( 'register-real', $modules );
registerBoot( $real );
$valid = (array) include registerCache( $real );
$like = static fn( array $changed ): string => '<?php return '. var_export( array_replace( $valid, $changed ), true ). ';';
$foreign = [
	'text'								=> 'this is no php',
	'php that returns a number'	=> '<?php return 5;',
	'php that parses no more'		=> '<?php return [ "kernel" => ',
	'an array of another shape'	=> '<?php return [ "components" => [] ];',
	'the right keys, no lists'	=> $like( [ 'stamp' => 1 ] ),
	'components that are no list'	=> $like( [ 'components' => 1 ] ),
	'groups that are no list'		=> $like( [ 'groups' => 'x' ] ),
	'components that are none'	=> $like( [ 'components' => [ 'a' => 1 ] ] ),
	'a list in place of a map'	=> $like( [ 'components' => [ [ 'a' ] ] ] ),
	'groups that are none'			=> $like( [ 'groups' => [ 'probe' => 'x' ] ] ),
	'an empty file'							=> '',
	'a throw'										=> '<?php throw new \\RuntimeException( "no" );',
];

foreach( $foreign as $what => $content ) {

	registerProbe( $fixtureTime );
	$strange = registerProject( 'register-foreign', $modules );
	registerBoot( $strange );
	registerReads();
	registerWrite( registerCache( $strange ), $content );

	ob_start();
	ninoWarnings();
	$booted = registerBoot( $strange );
	$printed = ob_get_clean();

	check( 'a file that is '. $what. ' is no cache: the folders are read, nothing is printed or said, the page is right and the file is written over',
		count( registerReads()[0] ) === 4 && $printed === '' && ninoWarnings() === [] && \Nino\Html::renderHtml( $booted, REGISTER_PAGE ) === $drawn && ( (array) include registerCache( $strange ) )['kernel'] === \Nino\VERSION );
}

// php that prints: what it prints is dropped, and the data it returns is the cache
registerProbe( $fixtureTime );
$loud = registerProject( 'register-prints', $modules );
registerBoot( $loud );
registerReads();
registerWrite( registerCache( $loud ), '<?php echo "printed"; return '. var_export( (array) include registerCache( $loud ), true ). ';' );
ob_start();
$printing = registerBoot( $loud );
$printed = ob_get_clean();
check( 'a file of the cache that prints says nothing: what it prints is dropped, and the schemas it returns are taken', $printed === '' && registerReads() === [ [], [] ] && \Nino\Html::renderHtml( $printing, REGISTER_PAGE ) === $drawn );

echo "\n";


// --- C. Two features, and a schema that is refused ------------------------------------------------

echo "A name and a group belong to one feature\n";

registerProbe( $fixtureTime );
$rival = registerFeature( 'Rival' );
registerGroup( $rival, 'probe', [ 'label' => 'The rival of probe' ], '.rival-two { color: red; }' );
registerGroup( $rival, 'rival', [ 'label' => 'Rival' ] );
registerComponent( $rival, 'rival-one', registerSchema( [ 'group' => 'rival', 'template' => '<r>one</r>' ] ) );
registerComponent( $rival, 'probe-box', registerSchema( [ 'group' => 'rival', 'template' => '<r>box of the rival</r>' ] ) );
registerComponent( $rival, 'rival-two', registerSchema( [ 'group' => 'probe', 'template' => '<r>two</r>' ] ) );

$both = registerProject( 'register-two', [ '\\Nino\\Modules\\Probe', '\\Nino\\Modules\\Rival' ] );
ninoWarnings();
$both = registerBoot( $both );
$said = ninoWarnings();
check( 'a name another feature registered first is refused: the second feature runs without it, and the first one draws as it did',
	array_keys( \Nino\Components::components( $both ) ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note', 'rival-one' ] && \Nino\Html::renderHtml( $both, '[probe-box][/probe-box][rival-one]' ) === '<section class="probe-box" data-mark="box"></section><r>one</r>'
	&& count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, '"probe-box"' ) === true && str_contains( $line, '"rival"' ) === true && str_contains( $line, '"probe"' ) === true ) ) === 1 );
check( 'a group another feature claimed first is refused, with its components: the standard part of the second is not in the bundle, and the warning names the group once',
	array_keys( \Nino\Components::groups( $both ) ) === [ 'probe', 'probe-more', 'rival' ] && \Nino\Components::groups( $both )['probe']['feature'] === 'probe' && \Nino\Components::groups( $both )['rival']['feature'] === 'rival'
	&& isset( \Nino\Components::components( $both )['rival-two'] ) === false && count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, 'group "probe"' ) === true ) ) === 1
	&& count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, '"rival-two"' ) === true ) ) === 1 && str_contains( registerBundle( $both ), '.rival-two' ) === false );
check( '...and the features that register again in the same request add nothing and say nothing', ( static function( array $request ): bool { \Nino\Features::registerComponents( $request ); return ninoWarnings() === [] && count( \Nino\Components::groups( $request ) ) === 3; } )( $both ) );

$reverse = registerProject( 'register-reverse', [ '\\Nino\\Modules\\Rival', '\\Nino\\Modules\\Probe' ] );
$reverse = registerBoot( $reverse );
$said = ninoWarnings();
check( 'the order of the modules decides who has the name and the group: the other way round the rival has them and probe is the one that goes without',
	\Nino\Components::groups( $reverse )['probe']['feature'] === 'rival' && \Nino\Html::renderHtml( $reverse, '[probe-box][/probe-box]' ) === '<r>box of the rival</r>'
	&& isset( \Nino\Components::components( $reverse )['probe-line'] ) === false && isset( \Nino\Components::components( $reverse )['probe-card'] ) === true && count( $said ) >= 2 );

// The Features panel names the reason, for a feature that is on
$panel = registerProject( 'register-panel', [ '\\Nino\\Modules\\Probe', '\\Nino\\Modules\\Rival' ] );
$panel = registerBoot( $panel );
ninoWarnings();
$problems = \Nino\Features::componentProblems( $panel, 'rival' );
check( 'componentProblems() names what a feature that is on lacks of its folders: the name and the group another feature has',
	count( $problems ) === 2 && str_contains( implode( ' ', $problems ), 'component "probe-box" is the feature "probe"' ) === true && str_contains( implode( ' ', $problems ), 'group "probe" is the feature "probe"' ) === true
	&& \Nino\Features::componentProblems( $panel, 'probe' ) === [] && \Nino\Features::componentProblems( $panel, 'nowhere' ) === [] );

echo "\n";


echo "A schema that is refused\n";

registerProbe( $fixtureTime );
$bad = registerFeature( 'Bad' );
registerGroup( $bad, 'tidy', [ 'label' => 'Tidy' ] );
registerComponent( $bad, 'bad-fine', registerSchema( [ 'template' => '<f>fine</f>' ] ) );
registerComponent( $bad, 'bad-wrong', registerSchema( [ 'frobnicate' => true ] ) );
registerComponent( $bad, 'bad-also', registerSchema( [ 'label' => '' ] ) );
registerReads();
$project = registerProject( 'register-bad', [ '\\Nino\\Modules\\Bad' ] );
ninoWarnings();
$badBooted = registerBoot( $project );
$said = ninoWarnings();
check( 'a component whose schema is refused is said with the reason and left out; the feature runs with the others',
	array_keys( \Nino\Components::components( $badBooted ) ) === [ 'bad-fine' ] && \Nino\Html::renderHtml( $badBooted, '[bad-fine][bad-wrong]' ) === '<f>fine</f>'
	&& count( $said ) === 2 && str_contains( $said[0], 'component "bad-also"' ) === true && str_contains( $said[1], '"frobnicate" is no key of a schema' ) === true && str_contains( $said[1], 'feature "bad"' ) === true );
check( '...and nothing is stored while something is refused', is_file( registerCache( $project, 'bad' ) ) === false );
ninoWarnings();
\Nino\Features::registerComponents( $badBooted );
$sameRequest = ninoWarnings();
$request = registerBoot( $project );
$nextRequest = ninoWarnings();
check( '...a request says it once, however often it registers, and the next request says it again',
	$sameRequest === [] && count( $nextRequest ) === 2 && is_file( registerCache( $project, 'bad' ) ) === false );

// The panel asks the kernel why
$badAll = $project;
unset( $badAll['./nino/features/all'] );
$badAll['/nino/modules'] = [ '\\Nino\\Modules\\Bad' ];
$reasons = \Nino\Features::componentProblems( $badAll, 'bad' );
check( 'componentProblems() has the sentence of each refusal, for the feature that is on - and none for the same feature when it is off',
	count( $reasons ) === 2 && str_contains( implode( ' ', $reasons ), 'bad-also' ) === true && str_contains( implode( ' ', $reasons ), 'frobnicate' ) === true
	&& \Nino\Features::componentProblems( registerProject( 'register-bad-off', [] ), 'bad' ) === [] );

// ...and when the feature is mended the file is written and the page is right
unlink( $bad. '/components/bad-wrong/component.php' );
rmdir( $bad. '/components/bad-wrong' );
registerRemove( $bad. '/components/bad-also' );
ninoWarnings();
$mended = registerBoot( $project );
check( 'a feature that is mended is stored again', is_file( registerCache( $project, 'bad' ) ) === true && array_keys( \Nino\Components::components( $mended ) ) === [ 'bad-fine' ] && ninoWarnings() === [] );

// A group that is refused takes its components with it
$groupless = registerFeature( 'Groupless' );
registerGroup( $groupless, 'tidy', [ 'label' => 'Tidy', 'knobs' => registerKnob( 'groupless-one.space', [ 'token' => '--other' ] ) ] );
registerComponent( $groupless, 'groupless-one', registerSchema() );
registerComponent( $groupless, 'groupless-two', registerSchema( [ 'group' => 'elsewhere' ] ) );
registerComponent( $groupless, 'groupless-three', registerSchema( [ 'group' => null ] ) );
unset( $groupless );
$request = registerBoot( registerProject( 'register-groupless', [ '\\Nino\\Modules\\Groupless' ] ) );
$said = implode( "\n", ninoWarnings() );
check( 'a group whose group.php is refused takes its components with it, a group that has no group.php and a component that names none are refused, each with its reason',
	\Nino\Components::components( $request ) === [] && \Nino\Components::groups( $request ) === [] && str_contains( $said, 'knob "groupless-one.space": "token" is "--groupless-one-space"' ) === true
	&& str_contains( $said, 'component "groupless-one": its group "tidy" is refused' ) === true && str_contains( $said, 'there is no groups/elsewhere/group.php' ) === true
	&& str_contains( $said, 'component "groupless-three"' ) === true );

echo "\n";


// --- D. What a folder may not bring ----------------------------------------------------------------

echo "Features::readComponents - what a folder may not bring\n";

$read = static function( string $dir, string $key = 'probe' ): array|string { return \Nino\Features::readComponents( $dir, $key ); };

$reads = $read( registerProbe( $fixtureTime ) );
check( 'readComponents() answers the clean schemas, the groups with their standard part and nothing refused for the folders of a feature that is whole',
	is_array( $reads ) === true && array_keys( $reads['components'] ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ] && $reads['rejected'] === [] && array_keys( $reads['groups'] ) === [ 'probe', 'probe-more' ]
	&& $reads['groups']['probe']['part'] === true && array_keys( $reads['groups']['probe'] ) === [ 'label', 'knobs', 'part', 'demo' ] && $reads['groups']['probe']['demo'] === '' );
check( '...and every schema is one the register takes again as it is: a clean schema is a schema', ( static function() use ( $reads ): bool {

	foreach( $reads['components'] as $name => $clean )
		if( \Nino\Components::validate( $name, $clean ) !== $clean )
			return false;

	return true;
} )() );
check( 'a directory that is not there, and a feature with no folders, answer a sentence and nothing', is_string( $read( $registerWork. '/nowhere' ) ) === true
	&& $read( registerFeature( 'Empty' ) ) === [ 'components' => [], 'groups' => [], 'rejected' => [] ] );

$evil = registerFeature( 'Evil' );
$mark = static function( string $what ): string { return '<?php $GLOBALS[\'ninoRegisterEvil\'][] = '. var_export( $what, true ). ';'; };
$GLOBALS['ninoRegisterEvil'] = [];

// A folder whose name is no slug, and is none of the folders that are skipped: nothing of it is included
foreach( [ 'Upper', 'two words', 'a..b', 'dot.dot', 'under_score', '-lead', 'trail-', str_repeat( 'a', 41 ), "line\nbreak", "tab\there", "esc\x1b[31m" ] as $name ) {
	mkdir( $evil. '/components/'. $name, 0755, true );
	file_put_contents( $evil. '/components/'. $name. '/component.php', $mark( 'name '. $name ). ' return '. var_export( registerSchema(), true ). ';' );
}
mkdir( $evil. '/components/.hidden', 0755, true );
file_put_contents( $evil. '/components/.hidden/component.php', $mark( 'hidden' ). ' return [];' );
file_put_contents( $evil. '/components/.DS_Store', 'a file of the system' );
file_put_contents( $evil. '/components/README.md', 'a file' );
$result = $read( $evil, 'evil' );
check( 'a folder of components/ whose name is no slug is refused with its reason and its component.php is not included; a hidden folder and a file are none',
	is_array( $result ) === true && $result['components'] === [] && count( $result['rejected'] ) === 11 && $GLOBALS['ninoRegisterEvil'] === []
	&& str_contains( $result['rejected']['a..b'], 'a name is a slug' ) === true && str_contains( $result['rejected'][str_repeat( 'a', 41 )], 'at most 40 characters' ) === true );

check( '...and what is said of such a name is one line of printable characters: no line break, tab or escape of a folder\'s name reaches a message', array_filter( $result['rejected'], static fn( string $text ): bool => preg_match( '/[^\x20-\x7E]/', $text ) === 1 ) === [] && str_contains( implode( ' ', $result['rejected'] ), 'line?break' ) === true );

// A path that leaves the folder: a template, a group, a name
registerRemove( $evil );
$evil = registerFeature( 'Evil' );
file_put_contents( $evil. '/outside.tpl', '<outside>' );
mkdir( $evil. '/groups/tidy', 0755, true );
file_put_contents( $evil. '/groups/tidy/group.php', '<?php return [ \'label\' => \'Tidy\' ];' );
file_put_contents( $registerWork. '/features/stray.tpl', '<stray>' );
foreach( [ 'up' => '../outside.tpl', 'up twice' => 'components/up/../../outside.tpl', 'absolute' => $evil. '/outside.tpl', 'rooted' => '/outside.tpl', 'stream' => 'php://filter/resource=outside.tpl', 'a null byte' => "outside.tpl\0.tpl", 'a backslash' => '..\\outside.tpl', 'no file' => 'components/nowhere.tpl' ] as $label => $template ) {

	registerComponent( $evil, 'ev-'. str_replace( ' ', '-', $label ), registerSchema( [ 'template' => $template ] ) );
}
registerComponent( $evil, 'ev-group', registerSchema( [ 'group' => '../outside' ] ) );
registerComponent( $evil, 'ev-nogroup', [ 'label' => 'No group', 'in' => [ 'page' ], 'template' => '<n>' ] );
registerComponent( $evil, 'ev-slash', registerSchema( [ 'group' => 'tidy/x' ] ) );
$result = $read( $evil, 'evil' );
check( 'a template that leaves the folder - up, absolute, a stream, a backslash, a null byte - is refused, and so is a group that is a path',
	is_array( $result ) === true && $result['components'] === [] && count( $result['rejected'] ) === 11 && str_contains( $result['rejected']['ev-nogroup'], 'a component of a feature names its "group"' ) === true && str_contains( $result['rejected']['ev-up'], 'not the name of a file' ) === true
	&& str_contains( $result['rejected']['ev-no-file'], 'not the name of a file' ) === true && str_contains( $result['rejected']['ev-group'], '"group" is a slug' ) === true && $GLOBALS['ninoRegisterEvil'] === [] );

// The attribute ground is the contract of the grounds, like the variant: a choice among none, base, alt, tint, primary, dark - nothing else is a ground
registerRemove( $evil );
$evil = registerFeature( 'Evil' );
registerGroup( $evil, 'tidy', [ 'label' => 'Tidy' ] );
$ground = static fn( array $declared ): array => registerSchema( [ 'attributes' => [ 'ground' => $declared + [ 'default' => 'base', 'label' => 'Ground' ] ] ] );
registerComponent( $evil, 'g-all', $ground( [ 'type' => 'choice', 'options' => [ 'none', 'base', 'alt', 'tint', 'primary', 'dark' ] ] ) );
registerComponent( $evil, 'g-some', $ground( [ 'type' => 'choice', 'options' => [ 'base', 'dark' ] ] ) );
registerComponent( $evil, 'g-more', $ground( [ 'type' => 'choice', 'options' => [ 'base', 'alternative' ] ] ) );
registerComponent( $evil, 'g-word', $ground( [ 'type' => 'choice', 'options' => [ 'base', 'alt', 'blue' ] ] ) );
registerComponent( $evil, 'g-string', $ground( [ 'type' => 'string', 'default' => 'alt' ] ) );
registerComponent( $evil, 'g-bool', $ground( [ 'type' => 'bool', 'default' => false ] ) );
$result = $read( $evil, 'evil' );
check( 'an attribute named ground is accepted as a choice among the words of the grounds, or part of them, and refused as anything else: another word, a text, a switch',
	is_array( $result ) === true && array_keys( $result['components'] ) === [ 'g-all', 'g-some' ] && array_keys( $result['rejected'] ) === [ 'g-bool', 'g-more', 'g-string', 'g-word' ]
	&& str_contains( $result['rejected']['g-word'], 'the attribute "ground" is a choice among none, base, alt, tint, primary, dark' ) === true );

// bleed is the other name of the kernel: a bool, or it is refused
registerRemove( $evil );
$evil = registerFeature( 'Evil' );
registerGroup( $evil, 'tidy', [ 'label' => 'Tidy' ] );
$bleed = static fn( array $declared ): array => registerSchema( [ 'attributes' => [ 'bleed' => $declared + [ 'label' => 'Bleed' ] ] ] );
registerComponent( $evil, 'b-bool', $bleed( [ 'type' => 'bool', 'default' => false ] ) );
registerComponent( $evil, 'b-string', $bleed( [ 'type' => 'string', 'default' => 'yes' ] ) );
registerComponent( $evil, 'b-choice', $bleed( [ 'type' => 'choice', 'options' => [ 'on', 'off' ], 'default' => 'off' ] ) );
$result = $read( $evil, 'evil' );
check( 'an attribute named bleed is accepted as a bool and refused as anything else: a text, a choice',
	is_array( $result ) === true && array_keys( $result['components'] ) === [ 'b-bool' ] && array_keys( $result['rejected'] ) === [ 'b-choice', 'b-string' ]
	&& str_contains( $result['rejected']['b-string'], 'the attribute "bleed" is a bool' ) === true );

// A schema that brings what is none of a schema
registerRemove( $evil );
$evil = registerFeature( 'Evil' );
registerGroup( $evil, 'tidy', [ 'label' => 'Tidy' ] );
foreach( [
	'a foreign key'				=> registerSchema( [ 'by' => 'nino' ] ),
	'an unknown key'			=> registerSchema( [ 'draws' => 'p .x' ] ),
	'no array'						=> 'a string',
	'a label that is empty'		=> registerSchema( [ 'label' => '' ] ),
	'a null byte in a label'	=> registerSchema( [ 'label' => "a\0b" ] ),
	'a template of a megabyte'	=> registerSchema( [ 'template' => str_repeat( 'x', 1048576 ) ] ),
	'a template with a null byte'	=> registerSchema( [ 'template' => "<a>\0</a>" ] ),
	'a name of a place'		=> registerSchema( [ 'in' => 'page' ] ),
] as $label => $schema )
	registerComponent( $evil, 'ev-'. str_replace( ' ', '-', $label ), is_array( $schema ) === true ? $schema : [] );
file_put_contents( $evil. '/components/ev-no-array/component.php', '<?php return "a string";' );
$result = $read( $evil, 'evil' );
check( 'a schema with a key that is none of the register\'s, no array, an empty label, a null byte, a template of a megabyte is refused - each with its reason, none of them registered',
	is_array( $result ) === true && $result['components'] === [] && count( $result['rejected'] ) === 8 && str_contains( $result['rejected']['ev-a-foreign-key'], '"by" is no key of a schema' ) === true
	&& str_contains( $result['rejected']['ev-a-template-of-a-megabyte'], 'longer than 65536 bytes' ) === true && str_contains( $result['rejected']['ev-no-array'], 'the schema must be an array' ) === true );
check( 'a reason says no more of a name than printable characters: the line of a log is one line', array_filter( $result['rejected'], static fn( string $why ): bool => preg_match( '/[^\x20-\x7E]/', $why ) === 1 ) === [] );

// A component.php that throws, that does not parse, that prints, and one that is a link
registerRemove( $evil );
$evil = registerFeature( 'Evil' );
registerGroup( $evil, 'tidy', [ 'label' => 'Tidy' ] );
mkdir( $evil. '/components/ev-throws', 0755, true );
file_put_contents( $evil. '/components/ev-throws/component.php', '<?php throw new \\RuntimeException( "no way\nsecond line\x1b[31m" );' );
mkdir( $evil. '/components/ev-parse', 0755, true );
file_put_contents( $evil. '/components/ev-parse/component.php', '<?php return [ ;' );
mkdir( $evil. '/components/ev-prints', 0755, true );
file_put_contents( $evil. '/components/ev-prints/component.php', '<?php echo "printed"; return '. var_export( registerSchema(), true ). ';' );
mkdir( $evil. '/components/ev-empty', 0755, true );
ob_start();
$result = $read( $evil, 'evil' );
$printed = ob_get_clean();
check( 'a component.php that throws or does not parse is refused with what it threw; one that prints is taken for what it returns and the page is not written to; an empty folder is refused',
	is_array( $result ) === true && array_keys( $result['components'] ) === [ 'ev-prints' ] && $printed === '' && str_contains( $result['rejected']['ev-throws'], 'RuntimeException: no way?second line?[31m' ) === true && preg_match( '/[^\x20-\x7E]/', $result['rejected']['ev-throws'] ) !== 1
	&& str_contains( $result['rejected']['ev-parse'], 'ParseError' ) === true && str_contains( $result['rejected']['ev-empty'], 'component.php of its own' ) === true && count( $result['rejected'] ) === 3 );

// Links: a folder, a component.php, a template.tpl, a group.php, a part.css, a folder of folders - to a file or a folder outside, and inside
registerRemove( $evil );
$evil = registerFeature( 'Evil' );
$away = $registerWork. '/away';
mkdir( $away. '/ev-away', 0755, true );
file_put_contents( $away. '/ev-away/component.php', $mark( 'away' ). ' return '. var_export( registerSchema(), true ). ';' );
file_put_contents( $away. '/component.php', $mark( 'away file' ). ' return '. var_export( registerSchema(), true ). ';' );
file_put_contents( $away. '/secret.tpl', '<secret>' );
file_put_contents( $away. '/group.php', $mark( 'away group' ). ' return [ \'label\' => \'Away\' ];' );
file_put_contents( $away. '/part.css', '/* the config of the project */' );
mkdir( $evil. '/components', 0755, true );
mkdir( $evil. '/groups/tidy/part', 0755, true );
symlink( $away. '/ev-away', $evil. '/components/ev-linked' );
mkdir( $evil. '/components/ev-file', 0755, true );
symlink( $away. '/component.php', $evil. '/components/ev-file/component.php' );
registerComponent( $evil, 'ev-tpl', registerSchema( [ 'template' => null ] ) );
file_put_contents( $evil. '/components/ev-tpl/component.php', '<?php return [ \'group\' => \'tidy\', \'label\' => \'Tpl\' ];' );
symlink( $away. '/secret.tpl', $evil. '/components/ev-tpl/template.tpl' );
mkdir( $evil. '/components/ev-inside', 0755, true );
symlink( $evil. '/components/ev-tpl/component.php', $evil. '/components/ev-inside/component.php' );
symlink( $away. '/group.php', $evil. '/groups/tidy/group.php' );
registerComponent( $evil, 'ev-fine', registerSchema() );
$result = $read( $evil, 'evil' );
check( 'a folder, a component.php and a template.tpl that is a link - outside the feature, or to another file of it - is refused and nothing is included through it',
	is_array( $result ) === true && $GLOBALS['ninoRegisterEvil'] === [] && array_keys( $result['components'] ) === [] && isset( $result['rejected']['ev-linked'] ) === true && isset( $result['rejected']['ev-file'] ) === true
	&& isset( $result['rejected']['ev-tpl'] ) === true && isset( $result['rejected']['ev-inside'] ) === true && str_contains( $result['rejected']['ev-tpl'], 'template.tpl is a link' ) === true );
check( '...a group.php that is a link refuses the group and the components in it', isset( $result['rejected']['group tidy'] ) === true && str_contains( $result['rejected']['ev-fine'], 'its group "tidy" is refused' ) === true && $result['groups'] === [] );

unlink( $evil. '/groups/tidy/group.php' );
file_put_contents( $evil. '/groups/tidy/group.php', '<?php return [ \'label\' => \'Tidy\' ];' );
symlink( $away. '/part.css', $evil. '/groups/tidy/part/part.css' );
$result = $read( $evil, 'evil' );
check( 'a part.css that is a link is refused - what a part is, is read into the bundle that is served - and the group with it', isset( $result['rejected']['group tidy'] ) === true && str_contains( $result['rejected']['group tidy'], 'part/part.css is a link' ) === true && $result['groups'] === [] );

unlink( $evil. '/groups/tidy/part/part.css' );
symlink( $away, $evil. '/groups/linked' );
rename( $evil. '/groups', $evil. '/groups-real' );
symlink( $evil. '/groups-real', $evil. '/groups' );
$result = $read( $evil, 'evil' );
check( 'a folder components/ or groups/ that is a link reads nothing', is_string( $result ) === true && str_contains( $result, 'the folder "groups" is a link' ) === true );
unlink( $evil. '/groups' );
rename( $evil. '/groups-real', $evil. '/groups' );
rename( $evil. '/components', $evil. '/components-real' );
symlink( $away, $evil. '/components' );
$result = $read( $evil, 'evil' );
check( '...the same for the folder of the components, whose component.php stays unread', is_string( $result ) === true && str_contains( $result, 'the folder "components" is a link' ) === true && $GLOBALS['ninoRegisterEvil'] === [] );

$linked = registerProject( 'register-linked', [ '\\Nino\\Modules\\Evil' ] );
$linkedBooted = registerBoot( $linked );
$said = ninoWarnings();
check( 'a feature whose folder cannot be read is said once, registers nothing and writes nothing', \Nino\Components::components( $linkedBooted ) === [] && count( $said ) === 1 && str_contains( $said[0], 'the folder "components" is a link' ) === true && is_file( registerCache( $linked, 'evil' ) ) === false );

// The directory of the feature itself may be a link: a checkout that is linked into features/
registerRemove( $evil );
$checkout = $registerWork. '/checkout/Linked';
registerCopy( __DIR__. '/fixtures/register/Probe', $checkout );
registerRemove( NINO_FEATURES_DIR. '/Probe' );
symlink( $checkout, NINO_FEATURES_DIR. '/Probe' );
$result = $read( NINO_FEATURES_DIR. '/Probe' );
check( 'the directory of a feature that is a link to a checkout is read: only what is inside of it must be no link', is_array( $result ) === true && count( $result['components'] ) === 4 && $result['rejected'] === [] );
unlink( NINO_FEATURES_DIR. '/Probe' );
registerProbe( $fixtureTime );

// A standard part that breaks the rule of mixing is refused alone
$blend = registerFeature( 'Blend' );
$parts = [
	'mix-a'		=> ".mix-a { color: red; }\n.button { color: red; }",
	'mix-b'		=> ".mix-b { color: var(--button-color); }",
	'mix-c'		=> ".mix-c { color: red !important; }",
	'mix-d'		=> ".mix-d { color: red; }\n/* ".str_repeat( 'x', 262144 ). " */",
	'mix-ok'	=> ".mix-ok { color: var(--brand-text); }",
];
foreach( $parts as $name => $css ) {
	registerGroup( $blend, $name, [ 'label' => $name ], $css );
	registerComponent( $blend, $name, registerSchema( [ 'group' => $name, 'template' => '<m class="[[class]]">'. $name. '</m>' ] ) );
}
$result = $read( $blend, 'blend' );
check( 'a standard part that breaks the rule of mixing - a class of another group, a token of another component, an !important, a part that is too long - is refused alone: its group says it has none, the components are the feature\'s, the others\' parts stand',
	is_array( $result ) === true && array_keys( $result['components'] ) === [ 'mix-a', 'mix-b', 'mix-c', 'mix-d', 'mix-ok' ] && array_keys( $result['rejected'] ) === [ 'part mix-a', 'part mix-b', 'part mix-c', 'part mix-d' ]
	&& array_column( $result['groups'], 'part' ) === [ false, false, false, false, true ] && str_contains( $result['rejected']['part mix-a'], 'meets the class .button' ) === true
	&& str_contains( $result['rejected']['part mix-b'], 'reads --button-color' ) === true && str_contains( $result['rejected']['part mix-c'], 'uses !important' ) === true && str_contains( $result['rejected']['part mix-d'], 'longer than 262144 bytes' ) === true );
ninoWarnings();
$blendBooted = registerBoot( registerProject( 'register-blend', [ '\\Nino\\Modules\\Blend' ] ) );
$said = ninoWarnings();
check( '...the boot says each once, puts into the bundle the one that keeps the rule and none of the others, and stores nothing while one is refused',
	count( $said ) === 4 && $blendBooted['/nino/html/assets']['/.cache/style.css'] === [ '/features/Blend/groups/mix-ok/part/part.css' ] && count( \Nino\Components::components( $blendBooted ) ) === 5
	&& is_file( registerCache( $blendBooted, 'blend' ) ) === false );

// Groups and knobs: what a group.php may say
$group = static function( mixed $declared, array $own = [ 'tidy' ] ): array|string { return \Nino\Components::validateGroup( 'tidy', $declared, $own ); };
$knobOf = static fn( array $changed = [] ): array => registerKnob( 'tidy.space', $changed );

check( 'validateGroup() answers the label and the knobs it took, a knob of a component of the group with a token of its name and five steps',
	$group( [ 'label' => 'Tidy', 'knobs' => $knobOf() ] ) === [ 'label' => 'Tidy', 'knobs' => $knobOf() ] && $group( [ 'label' => [ 'en_US' => 'T', 'de_DE' => 'T' ] ] ) === [ 'label' => [ 'en_US' => 'T', 'de_DE' => 'T' ], 'knobs' => [] ]
	&& $group( [ 'label' => 'Tidy', 'knobs' => registerKnob( 'tidy.space', [ 'ladder' => [ 'var(--page-space-1)', 'calc( var(--page-space-2) * 1.5 )', '.5rem', '#fff', 'rgb(0 0 0 / 50%)' ] ] ) ] )['knobs']['tidy.space']['ladder'][1] === 'calc( var(--page-space-2) * 1.5 )' );

$refused = [
	'no array'								=> 'x',
	'a key that is none'			=> [ 'label' => 'T', 'colour' => 'red' ],
	'no label'								=> [ 'knobs' => [] ],
	'an empty label'					=> [ 'label' => '' ],
	'a map with an empty text'	=> [ 'label' => [ 'en_US' => 'T', 'de_DE' => ' ' ] ],
	'a label with a null byte'	=> [ 'label' => "T\0" ],
	'knobs that are no map'		=> [ 'label' => 'T', 'knobs' => 'x' ],
	'a knob of nobody'				=> [ 'label' => 'T', 'knobs' => registerKnob( 'other.space' ) ],
	'a key with no dot'				=> [ 'label' => 'T', 'knobs' => [ 'tidy' => $knobOf()['tidy.space'] ] ],
	'a key with a path'				=> [ 'label' => 'T', 'knobs' => [ 'tidy.../x' => $knobOf()['tidy.space'] ] ],
	'a token of another name'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'token' => '--tidy-gap' ] ) ],
	'a token that is a property of the page'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'token' => '--page-space-1' ] ) ],
	'a key of a knob that is none'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'default' => 1 ] ) ],
	'a knob that is no array'	=> [ 'label' => 'T', 'knobs' => [ 'tidy.space' => 'x' ] ],
	'a sixth step'						=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '2px', '3px', '4px', '5px', '6px' ] ] ) ],
	'four steps'							=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '2px', '3px', '4px' ] ] ) ],
	'a ladder with names'			=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ 'a' => '1px', 'b' => '2px', 'c' => '3px', 'd' => '4px', 'e' => '5px' ] ] ) ],
	'a step that ends the rule'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '2px; } body { display: none', '3px', '4px', '5px' ] ] ) ],
	'a step with a brace'			=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '2px', '}', '4px', '5px' ] ] ) ],
	'a step with a quote'			=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '2px', '"x"', '4px', '5px' ] ] ) ],
	'a step with a url'				=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', 'url(//evil.example/x)', '3px', '4px', '5px' ] ] ) ],
	'a step with a comment'		=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '2px/* x', '3px', '4px', '5px' ] ] ) ],
	'a step with a tag'				=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ '1px', '</style><script>', '3px', '4px', '5px' ] ] ) ],
	'a step that is a number'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ 1, 2, 3, 4, 5 ] ] ) ],
	'a step with a null byte'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ "1px\0", '2px', '3px', '4px', '5px' ] ] ) ],
	'a step of a megabyte'		=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'ladder' => [ str_repeat( '1', 1048576 ), '2px', '3px', '4px', '5px' ] ] ) ],
	'a label of a knob that is empty'	=> [ 'label' => 'T', 'knobs' => $knobOf( [ 'label' => '' ] ) ],
];

foreach( $refused as $what => $declared ) {

	$why = $group( $declared );
	check( 'a group.php is refused for '. $what, is_string( $why ) === true && preg_match( '/[^\x20-\x7E]/', $why ) !== 1 );
}

$knobs = [];

for( $i = 1; $i <= 7; $i++ )
	$knobs += registerKnob( 'tidy.knob'. $i );

check( 'a component has six knobs at most, and a seventh is refused with the number - the sixth is not', is_string( $group( [ 'label' => 'T', 'knobs' => $knobs ] ) ) === true && str_contains( (string) $group( [ 'label' => 'T', 'knobs' => $knobs ] ), '6 knobs at most' ) === true
	&& is_array( $group( [ 'label' => 'T', 'knobs' => array_slice( $knobs, 0, 6 ) ] ) ) === true && is_array( $group( [ 'label' => 'T', 'knobs' => array_slice( $knobs, 0, 6 ) + registerKnob( 'other.space' ) ], [ 'tidy', 'other' ] ) ) === true );
check( 'validStep() is the check of a step of a ladder: a value of css as it is - a calc() with tokens, a colour - and nothing with a quote, brace, semicolon, comment, url(), a bracket that does not close, a number, a null byte or more than 120 characters',
	\Nino\Components::validStep( 'calc( var(--page-space-2) * 1.5 )' ) === true && \Nino\Components::validStep( 'rgb(0 0 0 / 50%)' ) === true && \Nino\Components::validStep( '#fff' ) === true
	&& array_filter( [ '', '}', '"x"', 'a;b', '1px/* x', '1px */', 'URL (//evil.example/x)', 'url(x)', 'calc( 1px', '1px )', ')(', 'calc( ( 1px + 2px )', 5, null, [ '1px' ], "1px\0", str_repeat( '1', 121 ) ], static fn( mixed $step ): bool => \Nino\Components::validStep( $step ) === true ) === []
	&& \Nino\Components::validStep( str_repeat( '1', 120 ) ) === true );

$extra = $group( [ 'label' => 'Tidy', 'knobs' => registerKnob( 'tidy.x-glow' ) ] );
check( 'an additional knob is --<component>-x-<name>: the key tidy.x-glow with that token is taken, and a part reads and sets it as it does a core one; another token for the key is refused',
	is_array( $extra ) === true && $extra['knobs']['tidy.x-glow']['token'] === '--tidy-x-glow'
	&& \Nino\Components::partProblems( '.tidy { --tidy-x-glow: 1px; box-shadow: 0 0 var( --tidy-x-glow ) var( --brand-accent ); }', [ 'tidy' ] ) === []
	&& is_string( $group( [ 'label' => 'Tidy', 'knobs' => registerKnob( 'tidy.x-glow', [ 'token' => '--tidy-glow' ] ) ] ) ) === true );
check( 'a group is named by a slug', is_string( \Nino\Components::validateGroup( '../tidy', [ 'label' => 'T' ], [] ) ) === true && is_string( \Nino\Components::validateGroup( 'Tidy', [ 'label' => 'T' ], [] ) ) === true
	&& is_string( \Nino\Components::validateGroup( str_repeat( 'a', 41 ), [ 'label' => 'T' ], [] ) ) === true );

echo "\n";


echo "What is included, and when\n";

// group.php is php of the feature, as its manifest is: included for a feature that is on and for none that is off
$GLOBALS['ninoRegisterGroupReads'] = [];
registerProbe( $fixtureTime );
file_put_contents( $probeDir. '/groups/probe/group.php', "<?php\n\$GLOBALS['ninoRegisterGroupReads'][] = 'probe';\nfile_put_contents( '". $registerWork. "/side-effect', 'ran' );\nreturn [ 'label' => 'Probe' ];" );
registerReads();
$quiet = registerProject( 'register-quiet', [] );
\Nino\Features::all( $quiet );
\Nino\Features::rejected( $quiet );
\Nino\Features::get( $quiet, 'probe' );
\Nino\Features::componentProblems( $quiet, 'probe' );
registerBoot( $quiet );
check( 'a group.php and a component.php of a feature that is off are never included - not by the listing of the features, not by the check of the Features panel, not by a boot',
	registerReads() === [ [], [] ] && is_file( $registerWork. '/side-effect' ) === false );
$loud = registerBoot( registerProject( 'register-loud', $modules ) );
check( 'a feature that is on has them included - the code of a feature that is on runs as its init() does - once, in the request that reads the folders',
	registerReads()[1] === [ 'probe', 'probe-more' ] && is_file( $registerWork. '/side-effect' ) === true );
unlink( $registerWork. '/side-effect' );
\Nino\Features::registerComponents( $loud );
check( '...and by no later request until a file changes', registerReads() === [ [], [] ] && is_file( $registerWork. '/side-effect' ) === false );
registerProbe( $fixtureTime );

// The cache holds data of schemas that were accepted
$odd = registerFeature( 'Odd' );
registerGroup( $odd, 'tidy', [ 'label' => [ 'en_US' => "It's \\ \"quoted\"; ?> <?php phpinfo(); // ", 'de_DE' => "Tab\there\nnewline" ] ] );
registerComponent( $odd, 'odd-one', registerSchema( [ 'label' => "'; system('id'); //", 'template' => '<b class="[[class]]">\' . phpinfo() . \'</b>', 'attributes' => [ 'say' => [ 'type' => 'string', 'default' => "'.system('id').'" ] ] ] ) );
$project = registerProject( 'register-odd', [ '\\Nino\\Modules\\Odd' ] );
$oddBooted = registerBoot( $project );
$file = (string) file_get_contents( registerCache( $project, 'odd' ) );
$kept = (array) include registerCache( $project, 'odd' );
check( 'a value that spells php is data in the file of the cache: no token that is code, and what is included is what was registered',
	registerOnlyData( $file ) === true && $kept['components'] === \Nino\Components::components( $oddBooted ) && $kept['groups'] === [ 'tidy' => [ 'label' => [ 'en_US' => "It's \\ \"quoted\"; ?> <?php phpinfo(); // ", 'de_DE' => "Tab\there\nnewline" ], 'knobs' => [], 'part' => false, 'demo' => '' ] ]
	&& count( array_filter( token_get_all( $file ), static fn( mixed $token ): bool => is_array( $token ) === true && $token[0] === T_OPEN_TAG ) ) === 1 );
$oddAgain = registerBoot( $project );
check( '...and the second request has the same, from the file', \Nino\Components::components( $oddAgain ) === \Nino\Components::components( $oddBooted ) && \Nino\Html::renderHtml( $oddAgain, '[odd-one]' ) === '<b class="odd-one">\' . phpinfo() . \'</b>' );

echo "\n";


// --- E. The rule of mixing -----------------------------------------------------------------------

echo "Components::partProblems - the rule a standard part keeps\n";

$own = [ 'probe-box', 'probe-line', 'probe-note' ];
$clean = (string) file_get_contents( __DIR__. '/fixtures/register/Probe/groups/probe/part/part.css' );

check( 'the standard parts of the fixture keep the rule', \Nino\Components::partProblems( $clean, $own ) === [] && \Nino\Components::partProblems( (string) file_get_contents( __DIR__. '/fixtures/register/Probe/groups/probe-more/part/part.css' ), [ 'probe-card' ] ) === [] );
check( 'a part may read the tokens of the brand, of the page and of its own components, and a property it sets itself - where it stands and in a media rule',
	\Nino\Components::partProblems( ".probe-box { --probe-box-n: 1px; padding: var(--probe-box-n) var(--brand-x) var(--page-y) var(--probe-line-z); }\n@media (min-width: 40.5em) { .probe-line--wide { margin: 0.5rem; } }", $own ) === [] );
check( 'a class of a component is its name, or its name and a hyphen and more - probe-boxy is no class of probe-box',
	\Nino\Components::partProblems( '.probe-boxy { x: y; }', $own ) === [ 'meets the class .probe-boxy, which is no class of its own components' ]
	&& \Nino\Components::partProblems( '.probe-box--wide .probe-line, .probe-box-inner { x: y; }', $own ) === [] );

$violations = [
	'a class of another group'		=> [ ".probe-box { x: y; }\n.button { x: y; }", 'meets the class .button, which is no class of its own components' ],
	'a class in a media rule'			=> [ "@media (min-width: 40em) { .other .probe-box { x: y; } }", 'meets the class .other, which is no class of its own components' ],
	'a class in a selector list'	=> [ ".probe-box, .section { x: y; }", 'meets the class .section, which is no class of its own components' ],
	'a class behind a pseudo'			=> [ ".probe-box:hover > .probe-line, :is(.card) { x: y; }", 'meets the class .card, which is no class of its own components' ],
	'a token of another component'	=> [ ".probe-box { padding: var(--button-space); }", 'reads --button-space, which is no token of the brand, of the page or of its own components' ],
	'a token with a fallback'			=> [ ".probe-box { padding: var( --other-x, 1px ); }", 'reads --other-x, which is no token of the brand, of the page or of its own components' ],
	'a token that only starts like the brand\'s'	=> [ ".probe-box { padding: var(--brandx-a); }", 'reads --brandx-a, which is no token of the brand, of the page or of its own components' ],
	'a token of a component that only starts like an own'	=> [ ".probe-box { padding: var(--probe-boxy-a); }", 'reads --probe-boxy-a, which is no token of the brand, of the page or of its own components' ],
	'!important'									=> [ ".probe-box { padding: 1px !important; }", 'uses !important' ],
	'! important'									=> [ ".probe-box { padding: 1px !IMPORTANT; }", 'uses !important' ],
	'an @import'									=> [ "@import 'other.css';\n.probe-box { x: y; }", 'uses @import' ],
	'an url()'										=> [ ".probe-box { background: url( x.png ); }", 'uses url()' ],
];

foreach( $violations as $what => [ $css, $says ] ) {

	$found = \Nino\Components::partProblems( $css, $own );
	check( 'a part with '. $what. ' is refused'. ( $says === null ? '' : ' with its sentence' ), $says === null ? $found === [] : $found === [ $says ] );
}

$found = [
	'a type selector'				=> [ 'body { x: y; }', 'has a selector with no class of its own components: body' ],
	'the universal selector'	=> [ '* { x: y; }', 'has a selector with no class of its own components: *' ],
	'an id'									=> [ '#main { x: y; }', 'meets an id: #main' ],
	'an attribute selector on the class'	=> [ '[class*=section] { x: y; }', 'meets the classes of the page by an attribute selector: [class*=section]' ],
	'an attribute selector on the class, with a word'	=> [ '[class~="section"] { x: y; }', 'meets the classes of the page by an attribute selector: [class~=?]' ],
	'a selector of a list that has no class of its own'	=> [ '.probe-box, p { x: y; }', 'has a selector with no class of its own components: p' ],
	'an escape in a class'	=> [ '.\73 ection { x: y; }', 'uses an escape (a backslash), which a part has no use for' ],
	'an escape behind an own class'	=> [ '.probe-box\2e section { x: y; }', 'uses an escape (a backslash), which a part has no use for' ],
	'a quote that is escaped in a string, round a class of another'	=> [ '.probe-box::after { content: "\""; } .section { color: red; } .probe-box::before { content: "\""; }', 'meets the class .section, which is no class of its own components' ],
	'a token in capital letters'	=> [ '.probe-box { color: VAR(--section-x); }', 'reads --section-x, which is no token of the brand, of the page or of its own components' ],
	'a token spelt with an escape'	=> [ '.probe-box { color: var(--\73 ection-x); }', 'uses an escape (a backslash), which a part has no use for' ],
	'a token a string says is set'	=> [ '.probe-box { content: "--section-x:"; color: var(--section-x); }', 'reads --section-x, which is no token of the brand, of the page or of its own components' ],
	'a token of another component that the part sets'	=> [ ':root { --section-gap: 0; }', 'sets --section-gap, which is no token of its own components' ],
	'a token of the brand that the part sets'	=> [ ':root { --probe-box-a: 1px; --brand-text: red; }', 'sets --brand-text, which is no token of its own components' ],
	'a custom property of nobody\'s that the part sets'	=> [ '.probe-box { --n: 1px; }', 'sets --n, which is no token of its own components' ],
	'url() in capital letters'	=> [ '.probe-box { background: URL(x.png); }', 'uses url()' ],
	'url() spelt with an escape'	=> [ '.probe-box { background: u\72l(x.png); }', 'uses an escape (a backslash), which a part has no use for' ],
	'image-set()'						=> [ '.probe-box { background: image-set("x.png" 1x); }', 'loads from outside: it uses image-set() or src()' ],
	'-webkit-image-set()'		=> [ '.probe-box { background: -webkit-image-set("x.png" 1x); }', 'loads from outside: it uses image-set() or src()' ],
	'src()'									=> [ '.probe-box { background: SRC("x.png"); }', 'loads from outside: it uses image-set() or src()' ],
	'an @import in capital letters'	=> [ '@IMPORT "x.css"; .probe-box { x: y; }', 'uses @import' ],
	'an @import spelt with an escape'	=> [ '@\69mport "x.css"; .probe-box { x: y; }', 'uses an escape (a backslash), which a part has no use for' ],
	'an @layer'							=> [ '@layer nino.theme { .probe-box { x: y; } }', 'uses @layer' ],
	'a @namespace'					=> [ '@namespace svg url(http://www.w3.org/2000/svg); .probe-box { x: y; }', 'uses @namespace' ],
	'a @charset'						=> [ '@charset "utf-8"; .probe-box { x: y; }', 'uses @charset' ],
	'a blank in !important'	=> [ '.probe-box { x: y ! important; }', 'uses !important' ],
	'a comment in !important'	=> [ '.probe-box { x: y !/**/important; }', 'uses !important' ],
	'!important spelt with an escape'	=> [ '.probe-box { x: y !\69mportant; }', 'uses an escape (a backslash), which a part has no use for' ],
	'a string that is not closed'	=> [ '.probe-box { content: "x; } .other { y: z; }', 'cannot be read: a string or a comment is never closed, or it is too long for the check' ],
	'a block that is not closed'	=> [ '.probe-box { x: y;', 'cannot be read: its braces do not balance, or it is nested too deep' ],
	'a block that is closed too often'	=> [ '.probe-box { x: y; } }', 'cannot be read: its braces do not balance, or it is nested too deep' ],
];

foreach( $found as $what => [ $css, $says ] ) {

	$problems = \Nino\Components::partProblems( $css, $own );
	check( 'a part with '. $what. ' is refused: '. $says, in_array( $says, $problems, true ) === true );
}

check( 'what a part keeps to is no reason to refuse it: nesting under an own class, the steps of an animation, a media query with a decimal, a type after an own class, :where() on an own class, a custom property of its own',
	\Nino\Components::partProblems( ".probe-box { & .probe-line { x: y; } &:hover { x: y; } }\n@keyframes probe-spin { from { x: 0; } 50.5% { x: 1; } to { x: 2; } }\n@media (min-width: 40.5em) { .probe-box .probe-line, .probe-box p { margin: 0.5rem; } }\n.probe-note:where( .probe-note--quiet ) { --probe-note-n: 1px; color: var(--probe-note-n); }", $own ) === []
	&& \Nino\Components::partProblems( ".probe-box p, .probe-line:where(.probe-line--x) > i { margin: 0; }", $own ) === [] );

check( 'a comment, a string and a number say nothing: a rule written in a comment, a class in an attribute selector\'s string and 0.5rem are no class',
	\Nino\Components::partProblems( "/* .other { color: red !important; url(x) var(--other-x) @import */\n.probe-box[href\$=\".pdf\"], .probe-box { margin: 0.5rem; }", $own ) === [] );
check( 'a part that breaks all three kinds says each - and a part that cannot be read says so',
	count( \Nino\Components::partProblems( ".other { padding: var(--other-x) !important; }", $own ) ) === 3 && count( \Nino\Components::partProblems( '.probe-box { x: y; }', [] ) ) === 1 && \Nino\Components::partProblems( '', $own ) === [] );

echo "\n";


// --- F. A feature the kernel delivers ------------------------------------------------------------

echo "A feature that is delivered with the kernel\n";

// Nothing below features/ but the feature of the fixture
foreach( scandir( NINO_FEATURES_DIR ) ?: [] as $entry )
	if( $entry !== '.' && $entry !== '..' && $entry !== 'Probe' )
		registerRemove( NINO_FEATURES_DIR. '/'. $entry );

$shipped = registerProject( 'register-shipped', [] );
$shipped['./nino/filesystem/path'] = $registerKernel;
$all = \Nino\Features::all( $shipped );

check( 'a module of the kernel with a feature.php is a feature: it is listed beside the ones below features/, with shipped true - and the others with shipped false',
	isset( $all['parcel'] ) === true && $all['parcel']['shipped'] === true && $all['parcel']['dir'] === $registerKernel. '/_nino/Nino/Modules/Parcel' && $all['parcel']['module'] === '\\Nino\\Modules\\Parcel'
	&& $all['probe']['shipped'] === false && array_keys( array_filter( $all, static fn( array $feature ): bool => $feature['shipped'] === true ) ) === [ 'basis', 'parcel' ] && $all['parcel']['active'] === false );
check( '...with the kernel\'s modules that have no manifest not among them, in the order of the keys: the feature the kernel really delivers, and the one this suite puts there', array_keys( $all ) === [ 'basis', 'parcel', 'probe' ] );

$taken = registerFeature( 'Parcel', [ 'key' => 'parceltwin' ] );
$mine = registerFeature( 'Mine', [ 'key' => 'parcel' ] );
$shippedAgain = registerProject( 'register-shipped-two', [] );
ninoWarnings();
$allAgain = \Nino\Features::all( $shippedAgain );
$rejected = \Nino\Features::rejected( $shippedAgain );
check( 'a directory below features/ named like the module of the kernel is refused, as it is for a module of the kernel that is no feature, and one that claims the key of the delivered feature is refused, naming the directory that holds it',
	isset( $rejected['Parcel'] ) === true && str_contains( $rejected['Parcel'], 'the directory name is taken' ) === true && isset( $rejected['Mine'] ) === true && str_contains( $rejected['Mine'], 'claims the key "parcel" that the directory Parcel already holds' ) === true
	&& $allAgain['parcel']['shipped'] === true && isset( $allAgain['parceltwin'] ) === false );
registerRemove( $taken );
registerRemove( $mine );

// Switched on and off
$project = registerProject( 'register-shipped-on', [] );
$project['./nino/filesystem/path'] = $registerKernel;
$project['/nino/http/routes'] = [ 'GET://' => [ 'uri' => '/home', 'body' => 'x' ] ];
\Nino\AppData::writeContentData( $project, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native', '/nino/http/routes' ] );
check( 'a delivered feature is switched on like any other: its class is listed, it is active, and its components are registered by the next boot - the standard part in the bundle by its path in the kernel',
	\Nino\Features::activate( $project, 'parcel' ) === true && \Nino\Features::get( $project, 'parcel' )['active'] === true && in_array( '\\Nino\\Modules\\Parcel', $project['/nino/modules'], true ) === true );
$request = registerBoot( $project );
$bundle = registerBundle( $request );
check( '...and draws what the fixture draws of the same files, with the parts in the layer of the components',
	array_keys( \Nino\Components::components( $request ) ) === [ 'parcel-box', 'parcel-card', 'parcel-line', 'parcel-note' ] && \Nino\Components::groups( $request )['parcel']['feature'] === 'parcel'
	&& \Nino\Html::renderHtml( $request, strtr( REGISTER_PAGE, [ 'probe' => 'parcel' ] ) ) === strtr( '<section class="probe-box probe-box--size-l" data-mark="box"><p class="probe-line" data-mark="calm">Hi</p></section><i class="probe-note">note</i><article class="probe-card">card</article>', [ 'probe' => 'parcel' ] )
	&& $request['/nino/html/assets']['/.cache/style.css'] === [ '/_nino/Nino/Modules/Parcel/groups/parcel/part/part.css', '/_nino/Nino/Modules/Parcel/groups/parcel-more/part/part.css' ] && registerLayerOf( $bundle, '.parcel-box { padding' ) === 'nino.components' );
check( '...with its cache under its own key, and a second request that includes none of its files', is_file( registerCache( $request, 'parcel' ) ) === true && ( static function( array $project ): bool { registerReads(); registerBoot( $project ); return registerReads() === [ [], [] ]; } )( $project ) );

// A directory below features/ named like the delivered feature does not take its place at the boot: it is refused, and the delivered one is registered
file_put_contents( $registerWork. '/features/Parcel.tmp', '' );
@unlink( $registerWork. '/features/Parcel.tmp' );
mkdir( NINO_FEATURES_DIR. '/Parcel', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/Parcel/feature.php', '<?php return [ \'key\' => \'parcel\', \'name\' => \'Parcel\', \'version\' => \'9.0.0\' ];' );
$covered = registerProject( 'register-covered', [ '\\Nino\\Modules\\Parcel' ] );
$covered['./nino/filesystem/path'] = $registerKernel;
ninoWarnings();
$coveredBooted = registerBoot( $covered );
$said = ninoWarnings();
check( 'a directory below features/ named like a delivered feature does not cover it at the boot: the delivered feature is registered, with its part in the bundle, and the directory is no feature',
	array_keys( \Nino\Components::components( $coveredBooted ) ) === [ 'parcel-box', 'parcel-card', 'parcel-line', 'parcel-note' ] && ( $coveredBooted['/nino/html/assets']['/.cache/style.css'][0] ?? '' ) === '/_nino/Nino/Modules/Parcel/groups/parcel/part/part.css'
	&& $said === [] && \Nino\Features::get( $coveredBooted, 'parcel' )['shipped'] === true && isset( \Nino\Features::rejected( $coveredBooted )['Parcel'] ) === true );
ninoWarnings();
registerRemove( NINO_FEATURES_DIR. '/Parcel' );

// Who has a name and a group does not depend on the order the features were switched on in: the delivered feature has them
$taker = registerFeature( 'Rival' );
registerGroup( $taker, 'parcel', [ 'label' => 'Rival of parcel' ], '.rival-two { color: red; }' );
registerComponent( $taker, 'parcel-box', registerSchema( [ 'group' => 'parcel', 'template' => '<r>box of the rival</r>' ] ) );
registerComponent( $taker, 'rival-two', registerSchema( [ 'group' => 'parcel', 'template' => '<r>two</r>' ] ) );
registerComponent( $taker, 'rival-one', registerSchema( [ 'group' => 'rival', 'template' => '<r>one</r>' ] ) );
registerGroup( $taker, 'rival', [ 'label' => 'Rival' ] );

foreach( [ 'the rival switched on first' => [ '\\Nino\\Modules\\Rival', '\\Nino\\Modules\\Parcel' ], 'the delivered feature switched on first' => [ '\\Nino\\Modules\\Parcel', '\\Nino\\Modules\\Rival' ] ] as $what => $modules ) {

	$ordered = registerProject( 'register-order', $modules );
	$ordered['./nino/filesystem/path'] = $registerKernel;
	ninoWarnings();
	$orderedBooted = registerBoot( $ordered );
	check( 'with '. $what. ' the delivered feature has its group, its name and its part, and the rival has what is its own',
		\Nino\Components::groups( $orderedBooted )['parcel']['feature'] === 'parcel' && \Nino\Components::groups( $orderedBooted )['parcel']['components'] === [ 'parcel-box', 'parcel-line', 'parcel-note' ]
		&& \Nino\Html::renderHtml( $orderedBooted, '[parcel-box][/parcel-box][rival-one]' ) === '<section class="parcel-box" data-mark="box"></section><r>one</r>' && isset( \Nino\Components::components( $orderedBooted )['rival-two'] ) === false
		&& in_array( '/_nino/Nino/Modules/Parcel/groups/parcel/part/part.css', $orderedBooted['/nino/html/assets']['/.cache/style.css'], true ) === true && count( ninoWarnings() ) >= 2 );
}
registerRemove( $taker );

// The panel
$panelApp = registerProject( 'register-shipped-panel', [] );
$panelApp['./nino/filesystem/path'] = $registerKernel;
$panelApp['/nino/admin/backups'] = false;
$panelApp['/nino/admin/logs'] = false;
$panelApp['/nino/auth/user'] = [];
$panelApp['/nino/auth/roles'] = [];
\Nino\Filesystem::putFileContent( $panelApp, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [ '\\Nino\\Modules\\Probe' ], '/nino/http/routes' => [ 'GET://' => [ 'uri' => '/home', 'body' => 'x' ] ] ] );
$panelApp['/nino/modules'] = [ '\\Nino\\Modules\\Probe' ];
ninoUser( $panelApp, 'dev@example.com' );
$panelCall = static function( array &$app, string $method, array $data = [] ): array { return array_slice( ninoCall( $app, [ \Nino\Modules\Features\Admin::class, $method ], $data ), 0, 2 ); };

[ $status, $body ] = $panelCall( $panelApp, 'apiList' );
$entries = array_column( $body['features'] ?? [], null, 'key' );
check( 'the Features panel lists a delivered feature with shipped true, an installed one with shipped false', $status === 200 && ( $entries['parcel']['shipped'] ?? null ) === true && ( $entries['probe']['shipped'] ?? null ) === false && $entries['parcel']['active'] === false && $entries['probe']['active'] === true );
check( '...and the reason a component of a feature that is on is not registered - none where all are', $entries['probe']['components'] === [] && $entries['parcel']['components'] === [] );

// Switching a feature off is asked about with the templates that call its components: the list says them before the switch, the answer of the switch
// names the components among the shortcodes that stay behind, and a template that calls none of them, a closing tag, a name that only starts like one
// and a feature that has no component say nothing
$calledApp = registerBoot( $panelApp );
\Nino\Filesystem::putFileContent( $calledApp, '/templates/page-one.tpl', "[probe-box][probe-note]x[/probe-note][/probe-box]\n[probe-line]" );
\Nino\Filesystem::putFileContent( $calledApp, '/templates/page-two.tpl', '[probe-note]y[/probe-note]' );
\Nino\Filesystem::putFileContent( $calledApp, '/templates/page-none.tpl', "[/probe-box] [probe-boxy] [probe] [text]z[/text]" );
\Nino\Filesystem::putFileContent( $calledApp, '/text/de_DE.php', [ '[[/found/words]]' => '[probe-card]' ] );
[ $status, $body ] = $panelCall( $calledApp, 'apiList' );
$entries = array_column( $body['features'] ?? [], null, 'key' );
check( 'the list says, for a feature that is on and has components, the templates that call them - by name, sorted, with their number - and an empty answer for the others',
	$status === 200 && ( $entries['probe']['calledIn'] ?? null ) === [ 'total' => 2, 'templates' => [ 'page-one.tpl', 'page-two.tpl' ] ] && ( $entries['parcel']['calledIn'] ?? null ) === [ 'total' => 0, 'templates' => [] ] );
for( $more = 1; $more <= 12; $more++ )
	\Nino\Filesystem::putFileContent( $calledApp, '/templates/page-more-'. sprintf( '%02d', $more ). '.tpl', '[probe-line]' );
[ , $body ] = $panelCall( $calledApp, 'apiList' );
$entries = array_column( $body['features'] ?? [], null, 'key' );
check( '...twelve more templates are counted, and ten of them named', ( $entries['probe']['calledIn']['total'] ?? 0 ) === 14 && count( $entries['probe']['calledIn']['templates'] ?? [] ) === 10 );
for( $more = 1; $more <= 12; $more++ )
	unlink( \Nino\Filesystem::path( $calledApp, '/templates/page-more-'. sprintf( '%02d', $more ). '.tpl' ) );
[ $status, $body ] = $panelCall( $calledApp, 'apiDeactivate', [ 'key' => 'probe' ] );
$found = array_column( $body['found'] ?? [], null, 'shortcode' );
check( 'the answer of the switch-off names the components that stand in templates and texts as the shortcodes do, one entry for each name',
	$status === 200 && array_keys( $found ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ] && $found['probe-note']['total'] === 2 && $found['probe-card']['places'] === [ [ 'kind' => 'text', 'where' => '/found/words (de_DE)' ] ] );
registerReads();
$offApp = $panelApp;
unset( $offApp['./nino/features/all'] );
$offApp['/nino/modules'] = [];
$panelCall( $offApp, 'apiList' );
check( 'the list of the Features panel includes no file of the folders of a feature that is off', registerReads() === [ [], [] ] );

$removal = 'feature "parcel" is delivered with the kernel and has no directory of its own to remove';
[ $status, $body ] = $panelCall( $panelApp, 'apiRemove', [ 'key' => 'parcel' ] );
check( 'Remove is refused for a delivered feature, in the panel under a code of its own - 400 - and the directory of the kernel is where it was',
	$status === 400 && ( $body['code'] ?? '' ) === 'features_remove_shipped' && is_file( $registerKernel. '/_nino/Nino/Modules/Parcel/feature.php' ) === true );
check( 'the kernel refuses it as well', \Nino\Features::remove( $panelApp, 'parcel' ) === $removal && is_dir( $registerKernel. '/_nino/Nino/Modules/Parcel' ) === true );
$wasOn = \Nino\Features::activate( $panelApp, 'parcel' );
[ $status, $body ] = $panelCall( $panelApp, 'apiRemove', [ 'key' => 'parcel' ] );
check( '...with the feature on, the reason is the one that holds when it is off: it is delivered', $wasOn === true && $status === 400 && ( $body['code'] ?? '' ) === 'features_remove_shipped' && \Nino\Features::remove( $panelApp, 'parcel' ) === $removal );
check( 'a delivered feature is switched off like any other, and stays where it is', \Nino\Features::deactivate( $panelApp, 'parcel' ) === true && \Nino\Features::get( $panelApp, 'parcel' )['active'] === false && is_file( $registerKernel. '/_nino/Nino/Modules/Parcel/feature.php' ) === true );
check( 'an ordinary feature is removed as it was', ( static function() use ( &$panelApp, $panelCall ): bool {

	\Nino\Features::deactivate( $panelApp, 'probe' );
	[ $status, $body ] = $panelCall( $panelApp, 'apiRemove', [ 'key' => 'probe' ] );

	return $status === 200 && ( $body['removed'] ?? '' ) === 'probe' && is_dir( NINO_FEATURES_DIR. '/Probe' ) === false;
} )() );
registerProbe( $fixtureTime );

// The panel names the reason of a refused component
$bad = registerFeature( 'Bad' );
registerGroup( $bad, 'tidy', [ 'label' => 'Tidy' ] );
registerComponent( $bad, 'bad-fine', registerSchema() );
registerComponent( $bad, 'bad-wrong', registerSchema( [ 'frobnicate' => true ] ) );
$badApp = $panelApp;
unset( $badApp['./nino/features/all'] );
$badApp['/nino/modules'] = [ '\\Nino\\Modules\\Bad' ];
[ , $body ] = $panelCall( $badApp, 'apiList' );
$entries = array_column( $body['features'] ?? [], null, 'key' );
check( 'the Features panel names why a component of an active feature is not registered, in the kernel\'s words, and the feature is no problem for it: it can still be switched off and on',
	count( $entries['bad']['components'] ) === 1 && str_contains( $entries['bad']['components'][0], '"frobnicate" is no key of a schema' ) === true && $entries['bad']['problems'] === [] );

// The catalogue
$keypair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
openssl_pkey_export( $keypair, $privateKey );
$publicKey = openssl_pkey_get_details( $keypair )['key'];
$archive = 'not an archive';
$entryOf = static fn( string $key, string $version, array $more = [] ): array => $more + [
	'key' => $key, 'name' => ucfirst( $key ), 'description' => [ 'en_US' => 'A '. $key, 'de_DE' => 'Ein '. $key ], 'version' => $version, 'nino' => '^2.0', 'php' => [ 'ext' => [] ], 'requires' => [],
	'directory' => ucfirst( $key ), 'archive' => 'https://catalogue.test/features/'. $key. '-'. $version. '.tar.gz', 'sha256' => hash( 'sha256', $archive ), 'size' => strlen( $archive ), 'released' => '2026-11-13',
];
$json = ninoCatalogue( [ $entryOf( 'parcel', '9.9.9' ), $entryOf( 'probe', '9.9.9' ), $entryOf( 'newcomer', '1.0.0' ) ] );
openssl_sign( $json, $signature, $privateKey, OPENSSL_ALGO_SHA256 );
$remote = [ \Nino\Catalogue::DEFAULT_URL => $json, \Nino\Catalogue::DEFAULT_URL. '.sig' => base64_encode( $signature ) ];
$requests = [];
$catalogueApp = $panelApp;
unset( $catalogueApp['./nino/features/all'] );
$catalogueApp['/nino/catalogue/key'] = $publicKey;
$catalogueApp['./nino/fetch/stub'] = static function( string $url, array $options ) use ( &$remote, &$requests ): array {
	$requests[] = $url;
	return isset( $remote[$url] ) === true ? [ 'ok' => true, 'status' => 200, 'body' => $remote[$url] ] : [ 'ok' => false, 'status' => 404, 'error' => 'http 404' ];
};
$fetched = \Nino\Catalogue::fetch( $catalogueApp );
$offers = \Nino\Catalogue::offers( $catalogueApp, is_array( $fetched ) === true ? $fetched : [ 'packages' => [] ], 'feature' );
check( 'the catalogue offers no update of a delivered feature: whatever version it lists, the offer is current and says it is delivered - and an installed feature is offered the update as it was',
	is_array( $fetched ) === true && $offers['parcel']['state'] === 'current' && $offers['parcel']['shipped'] === true && $offers['parcel']['local'] === '1.0.0' && $offers['probe']['state'] === 'upgrade' && $offers['probe']['shipped'] === false && $offers['newcomer']['state'] === 'available' );
$requests = [];
$installed = \Nino\Catalogue::install( $catalogueApp, 'feature', 'parcel', '9.9.9' );
check( 'an install of a delivered feature is refused before a byte is downloaded, with the sentence for it - and the one of an installed feature goes on to the download',
	$installed === 'the feature "parcel" is delivered with the kernel and is updated with it' && array_filter( $requests, static fn( string $url ): bool => str_contains( $url, '.tar.gz' ) === true ) === []
	&& str_contains( (string) \Nino\Catalogue::install( $catalogueApp, 'feature', 'probe', '9.9.9' ), 'archive' ) === true && array_filter( $requests, static fn( string $url ): bool => str_contains( $url, 'probe-9.9.9.tar.gz' ) === true ) !== [] );
check( '...nor does a feature that requires a delivered one place it: the requirement is there, and the feature itself goes to the download', ( static function() use ( $entryOf, &$remote, &$catalogueApp, &$requests, $privateKey ): bool {

	$json = ninoCatalogue( [ $entryOf( 'parcel', '9.9.9' ), $entryOf( 'needy', '1.0.0', [ 'requires' => [ 'parcel' ] ] ) ] );
	openssl_sign( $json, $signature, $privateKey, OPENSSL_ALGO_SHA256 );
	$remote[\Nino\Catalogue::DEFAULT_URL] = $json;
	$remote[\Nino\Catalogue::DEFAULT_URL. '.sig'] = base64_encode( $signature );
	unset( $catalogueApp['./nino/catalogue'] );
	$requests = [];
	$plan = \Nino\Catalogue::install( $catalogueApp, 'feature', 'needy', '1.0.0' );

	return is_string( $plan ) === true && str_contains( $plan, 'archive' ) === true && array_filter( $requests, static fn( string $url ): bool => str_contains( $url, 'parcel-9.9.9' ) === true ) === []
		&& array_filter( $requests, static fn( string $url ): bool => str_contains( $url, 'needy-1.0.0' ) === true ) !== [];
} )() );

echo "\n";


// --- G. The boot, the two ways side by side, and what it costs -------------------------------------

echo "The boot, in a process of its own\n";

$bootDir = $registerWork. '/boot';
mkdir( $bootDir. '/private', 0755, true );
file_put_contents( $bootDir. '/private/config.php', '<?php return '. var_export( [
	'/nino/modules'							=> [ '\\Nino\\Modules\\Probe' ],
	'/nino/locales/available'		=> [ 'de_DE', 'en_US' ],
	'/nino/locales/native'			=> 'de_DE',
	'/nino/install/completed'		=> true,
], true ). ';' );
$bootScript = static function( string $extra ) use ( $bootDir, $registerKernel ): string {

	file_put_contents( $bootDir. '/boot.php', '<?php declare(strict_types=1);
define( \'NINO_FEATURES_DIR\', '. var_export( NINO_FEATURES_DIR, true ). ' );
define( \'NINO_PRIVATE_DIR\', '. var_export( $bootDir. '/private', true ). ' );
'. $extra. '
require '. var_export( $registerKernel. '/_nino/Nino.php', true ). ';
$appData = \\Nino\\init();
echo json_encode( [ $appData[\'/nino/html/assets\'], array_keys( \\Nino\\Components::components( $appData ) ), array_keys( \\Nino\\Components::groups( $appData ) ), $appData[\'./nino/probe/seen\'] ?? null, $GLOBALS[\'ninoRegisterReads\'] ?? [] ] );' );

	return (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $bootDir. '/boot.php' ). ' 2>/dev/null' );
};

registerProbe( $fixtureTime );
$booted = json_decode( $bootScript( '' ), true ) ?? [];
$again = json_decode( $bootScript( '' ), true ) ?? [];
check( 'a boot has the components of a feature that is on registered before its init() runs, and the standard parts of its groups in the bundle',
	( $booted[1] ?? [] ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ] && ( $booted[2] ?? [] ) === [ 'probe', 'probe-more' ] && ( $booted[3] ?? [] ) === [ 'probe-box', 'probe-card', 'probe-line', 'probe-note' ]
	&& ( $booted[0]['/.cache/style.css'] ?? [] ) === [ '/_nino/Nino.css', '/features/Probe/groups/probe/part/part.css', '/features/Probe/groups/probe-more/part/part.css' ] );
check( '...having read every component.php, with its cache written, and a second boot includes no file of the folders and has the same components and parts',
	count( $booted[4] ?? [] ) === 4 && is_file( $bootDir. '/private/data/cache/register/probe.php' ) === true && ( $again[4] ?? null ) === [] && ( $again[1] ?? [] ) === ( $booted[1] ?? null ) && ( $again[0] ?? [] ) === ( $booted[0] ?? null ) );

// opcache: a file that changed is the file that is read, in the same process and within the seconds opcache does not look at the time of a file
echo "\nWith opcache\n";

if( extension_loaded( 'Zend OPcache' ) === false )
	echo "  not run: opcache is not loaded in this php\n";
else {

	registerProbe( $fixtureTime );
	$opcacheScript = $registerWork. '/opcache.php';
	file_put_contents( $opcacheScript, '<?php declare(strict_types=1);
define( \'NINO_FEATURES_DIR\', '. var_export( NINO_FEATURES_DIR, true ). ' );
require '. var_export( $registerKernel. '/tests/harness.php', true ). ';
$pristine = ninoSandbox( \'register-opcache\' );
$pristine[\'/nino/dir\'] = \'\';
\\Nino\\Html::init( $pristine );
$pristine[\'/nino/modules\'] = [ \'\\\\Nino\\\\Modules\\\\Probe\' ];
$boot = static function( array $request ): array { \\Nino\\Features::registerAssets( $request ); \\Nino\\Features::registerComponents( $request ); return $request; };
$labels = [ \\Nino\\Components::components( $boot( $pristine ) )[\'probe-note\'][\'label\'] ];
$file = NINO_FEATURES_DIR. \'/Probe/components/probe-note/component.php\';
file_put_contents( $file, str_replace( "\'Note\'", "\'Nope\'", (string) file_get_contents( $file ) ) );
touch( $file, '. ( $fixtureTime + 500 ). ' );
$labels[] = \\Nino\\Components::components( $boot( $pristine ) )[\'probe-note\'][\'label\'];
$labels[] = ( (array) include ninoSandboxDir( $pristine ). \'/private/data/cache/register/probe.php\' )[\'components\'][\'probe-note\'][\'label\'];
$labels[] = \\Nino\\Components::components( $boot( $pristine ) )[\'probe-note\'][\'label\'];
$labels[] = ninoSandboxDir( $pristine );
echo json_encode( $labels );' );
	$ran = json_decode( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -d opcache.enable_cli=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=60 '. escapeshellarg( $opcacheScript ). ' 2>/dev/null' ), true );
	check( 'a component.php that changes is read as it is now, with opcache on and in the same process: the first request has the old label, the second the new, the file of the cache has the new, and the third request - from the file - has it too',
		is_array( $ran ) === true && array_slice( $ran, 0, 4 ) === [ 'Note', 'Nope', 'Nope', 'Nope' ] );

	if( is_array( $ran ) === true && is_string( $ran[4] ?? null ) === true && str_starts_with( $ran[4], sys_get_temp_dir() ) === true )
		registerRemove( $ran[4] );

	registerProbe( $fixtureTime );
}

echo "\n";

// A theme in the init() of a module answers the hook of the parts: the hook is asked after the init() of the modules
registerProbe( $fixtureTime );
\Nino\Filesystem::removeDir( $bootDir. '/private/data' );
$booted = json_decode( $bootScript( 'define( \'NINO_PROBE_THEME\', true );' ), true ) ?? [];
check( 'a callback a module registers in its init() is asked for the parts: the hook is asked behind the init() of the modules', ( $booted[0]['/.cache/style.css'] ?? [] ) === [ '/_nino/Nino.css', '/features/Probe/groups/probe-more/part/part.css' ] );

echo "\n";


echo "The keys of a manifest that are not read, beside the folders\n";

// A feature may still have the keys 'components' and 'stacks' in its manifest: they are not read, the folders are, and the Features panel says why once
registerRemove( NINO_FEATURES_DIR. '/Twofold' );
$twofold = registerFeature( 'Twofold', [ 'components' => [ 'old-way' => [ 'label' => 'Old', 'source' => 'text', 'attributes' => [] ] ], 'stacks' => [ 'old-rows' => [ 'label' => 'Rows' ] ] ] );
registerGroup( $twofold, 'tidy', [ 'label' => 'Tidy' ] );
registerComponent( $twofold, 'new-way', registerSchema( [ 'template' => '<n>[[value]]</n>', 'source' => 'text' ] ) );
file_put_contents( $twofold. '/Twofold.php', '<?php namespace Nino\\Modules { class Twofold { public static function componentOldWay( array &$appData, array $args ): string { return "<o>". $args["value"]. "</o>"; } } }' );
$project = registerProject( 'register-twofold', [ '\\Nino\\Modules\\Twofold' ] );
$project['./nino/filesystem/path'] = $registerKernel;
ninoWarnings();
$project = registerBoot( $project );
check( 'a feature that has the keys and the folders registers the folders and nothing of the keys, and says nothing at boot',
	array_keys( \Nino\Components::components( $project ) ) === [ 'new-way' ] && array_keys( \Nino\Components::groups( $project ) ) === [ 'tidy' ] && ninoWarnings() === [] );
check( '...the page is drawn by the folders alone: the call of a component of the key is a name nobody registered', \Nino\Html::renderHtml( $project, '[old-way text="a"][new-way text="b"]' ) === '<n>b</n>' );
$reasons = \Nino\Features::componentProblems( $project, 'twofold' );
check( '...and the Features panel is told one thing of the keys, however many there are, beside what it says of the folders: here nothing else',
	count( $reasons ) === 1 && is_string( $reasons[0] ) === true && $reasons[0] !== '' );

echo "\n";


echo "What it costs\n";

// A feature of twenty components in six groups, each with attributes of three kinds, a template.tpl and a standard part - the size of the one the kernel ships next
$score = registerFeature( 'Score' );
$letters = [ 'a', 'b', 'c', 'd', 'e', 'f' ];

foreach( $letters as $index => $letter ) {

	$first = 'score-'. $letter. '-'. sprintf( '%02d', $index + 1 );
	registerGroup( $score, 'score-'. $letter, [ 'label' => [ 'en_US' => 'Score '. $letter, 'de_DE' => 'Wertung '. $letter ], 'knobs' => registerKnob( $first. '.space' ) + registerKnob( $first. '.gap' ) ],
		'.'. $first. ' { padding: var(--'. $first. '-space); margin: var(--page-space-2); }' );
}

for( $i = 1; $i <= 20; $i++ ) {

	$letter = $letters[( $i - 1 ) % 6];
	registerComponent( $score, 'score-'. $letter. '-'. sprintf( '%02d', $i ), [
		'group' => 'score-'. $letter, 'label' => [ 'en_US' => 'Component '. $i, 'de_DE' => 'Komponente '. $i ], 'in' => [ 'page', 'flow' ], 'offers' => [ 'flow' ], 'source' => 'text', 'variants' => [ 'quiet', 'loud' ],
		'attributes' => [
			'tone'	=> [ 'type' => 'choice', 'options' => [ 'a', 'b', 'c' ], 'default' => 'a', 'label' => [ 'en_US' => 'Tone', 'de_DE' => 'Ton' ] ],
			'wide'	=> [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Wide', 'de_DE' => 'Breit' ] ],
			'title'	=> [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Title', 'de_DE' => 'Titel' ] ],
		],
	], '<div class="[[class]]" title="[[title]]">[[value]][[content]]</div>' );
}

// What the register costs is told by what it reads, and no clock: each component.php of the feature counts the times it is included, so that a registration that is
// served by the cache of the register is one in which not one of the twenty schemas is included - and one that reads the folders includes all of them
foreach( glob( $score. '/components/*/component.php' ) ?: [] as $file )
	file_put_contents( $file, str_replace( '<?php return', '<?php $GLOBALS["registerIncluded"] = ( $GLOBALS["registerIncluded"] ?? 0 ) + 1; return', (string) file_get_contents( $file ) ) );

$GLOBALS['registerIncluded'] = 0;
$timed = registerProject( 'register-time', [ '\\Nino\\Modules\\Score' ] );
$timed = registerBoot( $timed );
$count = count( \Nino\Components::components( $timed ) );
$regFirst = $GLOBALS['registerIncluded'];
$cacheFile = registerCache( $timed, 'score' );

// Five more requests, each with nothing of the one before: the cache of the register serves them
$GLOBALS['registerIncluded'] = 0;

for( $regRequest = 0; $regRequest < 5; $regRequest++ ) {
	$regOne = $timed;
	unset( $regOne['./nino/features/listed'], $regOne[\Nino\Components::REGISTRY], $regOne[\Nino\Components::OWNERS], $regOne[\Nino\Components::GROUPS] );
	\Nino\Features::registerComponents( $regOne );
}

$regServed = $GLOBALS['registerIncluded'];
$regStamp = is_file( $cacheFile ) === true ? filemtime( $cacheFile ) : null;
$regOne = $timed;
unset( $regOne['./nino/features/listed'], $regOne[\Nino\Components::REGISTRY], $regOne[\Nino\Components::OWNERS], $regOne[\Nino\Components::GROUPS] );
$regOne['/nino/markup/cache'] = false;
$GLOBALS['registerIncluded'] = 0;
\Nino\Features::registerComponents( $regOne );
$regRead = $GLOBALS['registerIncluded'];
clearstatcache();
echo '  '. $count. ' schemas: included at the first registration '. $regFirst. ', in five requests from the cache '. $regServed. ', in one with the cache off '. $regRead. "\n";
check( 'twenty schemas are registered, in six groups, from the cache and from the folders', $count === 20 && count( \Nino\Components::groups( $timed ) ) === 6 );
check( 'registering them from the cache includes not one of the twenty component files, in any of five requests; the first registration read them all, and one with the cache turned off reads them all again and leaves the cache as it was',
	$regFirst === 20 && $regServed === 0 && $regRead === 20 && is_file( $cacheFile ) === true && filemtime( $cacheFile ) === $regStamp );

\Nino\Filesystem::removeDir( ninoSandboxDir( $timed ) );
// An attribute selector on class is allowed where it only narrows an element the part owns, and nowhere else
$narrowing = [ 'section-wrap', 'section-col', 'section-row' ];
$allowed = [
	'.section-col:where( [class*="--ground-"] ) { color: red; }',
	'.section-col:where( [class*="--ground-"], [class*="--pad-"], .section-col--variant-card ) { color: red; }',
	'.section-col[class*="--ground-"] { color: red; }',
	'.section-wrap:where( [class*="--height-"] ) > .section-row { color: red; }',
	'.section-col:not( [class*="--ground-"] ) { color: red; }',
];
$denied = [
	'[class*="section"] { color: red; }',
	'.section-col [class*="x"] { color: red; }',
	'.section-col > [class~="section"] { color: red; }',
	'.section-col:where( .section-row [class*="x"] ) { color: red; }',
	'.section-col:has( > [class*="x"] ) { color: red; }',
	'.section-col:has( [class*="x"] ) { color: red; }',
	'.section-col:where( [class*="--ground-"] ) [class*="text"] { color: red; }',
	'div:where( [class*="x"] ) { color: red; }',
	':where( .section-col ) [class*="x"] { color: red; }',
];
check( 'an attribute selector on class that narrows an element of the part - beside its class, or in :where(), :is() or :not() on it - keeps the rule of mixing',
	array_filter( $allowed, static fn( string $css ): bool => \Nino\Components::partProblems( $css, $narrowing ) !== [] ) === [] );
check( '...and one that meets another element - by itself, behind a combinator, inside :has(), on an element that is no component - breaks it',
	array_filter( $denied, static fn( string $css ): bool => \Nino\Components::partProblems( $css, $narrowing ) === [] ) === [] );

// The element a selector speaks of first is the part's own: a class of its components at the top of the selector, or a
// rule nested in such a rule. A class named inside a function only, and & where no rule of the part stands round it, are none
$own = [ 'probe-box' ];
$kept = [
	'.probe-box { & p { color: red } }', '.probe-box { &:hover { color: red } }', '.probe-box { p { color: red } }', '.probe-box p { color: red }',
	'@media (min-width: 40rem) { .probe-box { & > li { color: red } } }', ':root { --probe-box-x: 1px }',
	'.probe-box:is(.probe-box--a, .probe-box--b) { color: red }', '.probe-box, .probe-box--x > p { color: red }',
];
$broken = [
	'*:not(.probe-box){margin:0}', ':not(.probe-box){margin:0}', 'body:not(.probe-box){color:red}', ':is(body, .probe-box){color:red}',
	':where(*, .probe-box){color:red}', 'html:has(.probe-box){overflow:hidden}', '& * { color: red }', ':root { & * { color: red } }',
	':root { p { color: red } }', '@media all { & * { color: red } }', '@scope (.probe-box) { & * { color: red } }',
	'[class*=section]:not(.probe-box) { color: red }', '.probe-box[*|class*=section] p, [*|class*=section] { color: red }',
];
check( 'a selector that starts from an element of the part, by its class or nested in a rule of its own, keeps the rule of mixing',
	array_filter( $kept, static fn( string $css ): bool => \Nino\Components::partProblems( $css, $own ) !== [] ) === [] );
check( '...and one that names the class inside a function only, or stands on & with no rule of the part round it, breaks it',
	array_filter( $broken, static fn( string $css ): bool => \Nino\Components::partProblems( $css, $own ) === [] ) === [] );
check( 'rules nested deeper than sixteen levels are not read: the part is refused', \Nino\Components::partProblems( str_repeat( '.probe-box {', 17 ). ' color: red; '. str_repeat( '}', 17 ), $own ) !== []
	&& \Nino\Components::partProblems( str_repeat( '.probe-box {', 16 ). ' color: red; '. str_repeat( '}', 16 ), $own ) === [] );

// --- The demo of a group ----------------------------------------------------------------------------------

echo "\nThe demo of a group: groups/<group>/demo.tpl\n";

ninoWarnings();

$show = registerFeature( 'Show' );
registerComponent( $show, 'show-box', registerSchema( [ 'group' => 'show', 'label' => 'Box', 'in' => [ 'page' ], 'offers' => [], 'template' => '<div class="[[class]]">[[content]]</div>' ] ) );
registerComponent( $show, 'show-line', registerSchema( [ 'group' => 'show', 'label' => 'Line', 'in' => [ 'show-box' ], 'source' => 'text', 'template' => '<p class="[[class]]">[[value]]</p>' ] ) );
registerGroup( $show, 'show', [ 'label' => 'Show' ] );
$demo = '[show-box]'. "\n\t". '[show-line text="Hello"]'. "\n\t". '[section-title text="A title" href="#"]'. "\n\t". '[image /nino/placeholder/mark decorative]'. "\n". '[/show-box]'. "\n";
file_put_contents( $show. '/groups/show/demo.tpl', $demo );
$result = $read( $show, 'show' );
check( 'a demo.tpl of a group is read with the folders: its text is the demo of the group, and nothing is refused', is_array( $result ) === true && $result['rejected'] === [] && $result['groups']['show']['demo'] === $demo
	&& array_keys( $result['groups']['show'] ) === [ 'label', 'knobs', 'part', 'demo' ] && $result['groups']['show']['part'] === false );
check( '...it may call the components of its feature and those of basis, and a picture of the kernel', array_keys( $result['components'] ) === [ 'show-box', 'show-line' ] );

registerFeature( 'None' );
registerComponent( NINO_FEATURES_DIR. '/None', 'none-box', registerSchema( [ 'group' => 'none', 'in' => [ 'page' ] ] ) );
registerGroup( NINO_FEATURES_DIR. '/None', 'none', [ 'label' => 'None' ] );
$result = $read( NINO_FEATURES_DIR. '/None', 'none' );
check( 'a group with no demo.tpl has \'\' for a demo, and that is no reason to say anything', is_array( $result ) === true && $result['groups']['none']['demo'] === '' && $result['rejected'] === [] );
registerRemove( NINO_FEATURES_DIR. '/None' );

$refused = [
	'a component nobody has'										=> [ '[nobody text="x"]', 'calls "nobody", which is no component' ],
	'a component of a feature that is none of these'	=> [ '[show-box][form][/show-box]', 'calls "form"' ],
	'a textfill of the project'								=> [ '[show-box][[/project/company/general/name]][/show-box]', 'holds "[["' ],
	'a textfill in a fixed text'								=> [ '[show-box][show-line text="[[/project/company/general/name]]"][/show-box]', 'holds "[["' ],
	'a source that is a key of the project'				=> [ '[show-box][show-line /project/company/general/name][/show-box]', 'a source' ],
	'a source that is a text of the template'			=> [ '[show-box][show-line ~title][/show-box]', 'a source' ],
	'a source that is a field'									=> [ '[show-box][show-line .title][/show-box]', 'a source' ],
	'a picture that is a slot of the project'		=> [ '[show-box][image /logo decorative][/show-box]', 'a source' ],
	'a picture that is none of the kernel\'s'		=> [ '[show-box][image /nino/placeholder/other decorative][/show-box]', 'a source' ],
	'a picture of an attribute that is a slot'	=> [ '[show-box][header-logo light="/logo"][/show-box]', 'begins like a reference' ],
	'an address of the project'								=> [ '[show-box][button text="x" href="/contact"][/show-box]', 'begins like a reference' ],
	'a call that is not closed'								=> [ '[show-box][show-line text="x"]', 'unclosed of "show-box"' ],
	'an end that is none'											=> [ '[show-box][/show-box][/show-box]', 'stray-end' ],
	'a document of more levels than are read'	=> [ str_repeat( '[show-box]', 17 ). str_repeat( '[/show-box]', 17 ), 'depth' ],
	'a null byte'															=> [ "[show-box]\0[/show-box]", 'null byte' ],
	'bytes that are no utf-8'									=> [ "[show-box]\xff[/show-box]", 'not utf-8' ],
	'nothing'																	=> [ " \n\t", 'is empty' ],
	'a file of three megabytes'								=> [ '[show-box]'. str_repeat( 'a', 3 * 1024 * 1024 ). '[/show-box]', 'longer than '. \Nino\Components::TEMPLATE_LENGTH ],
	'more than the template of a component'		=> [ '[show-box]'. str_repeat( 'a', \Nino\Components::TEMPLATE_LENGTH ). '[/show-box]', 'longer than '. \Nino\Components::TEMPLATE_LENGTH ],
];
$wrong = [];

foreach( $refused as $what => [ $text, $says ] ) {

	file_put_contents( $show. '/groups/show/demo.tpl', $text );
	$result = $read( $show, 'show' );

	if( is_array( $result ) === false || array_keys( $result['rejected'] ) !== [ 'demo show' ] || str_contains( $result['rejected']['demo show'], $says ) === false || $result['groups']['show']['demo'] !== ''
		|| array_keys( $result['components'] ) !== [ 'show-box', 'show-line' ] || $result['groups']['show']['label'] !== 'Show' )
		$wrong[] = $what. ' - '. ( is_array( $result ) === true ? json_encode( $result['rejected'] ) : $result );
}
check( 'a demo that calls what its feature and basis do not have, reads or fills in what is the project\'s, is not read as html+ without a note, holds a null byte or is too long is refused with a sentence and no demo: the group, its knobs and its components stay ('. count( $refused ). ' cases)'
	. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 3 ) ) ), $wrong === [] );

$result = $read( $show, 'show' );
check( '...the sentence names the feature and the group', str_contains( (string) ( $result['rejected']['demo show'] ?? '' ), 'feature "show", group "show": its demo.tpl ' ) === true );

file_put_contents( $show. '/groups/show/demo.tpl', '[show-box][show-line text="Hello"][/show-box]' );
check( 'a demo of exactly the length of a template is read: the limit is the one of a component', ( static function() use ( $show, $read ): bool {

	file_put_contents( $show. '/groups/show/demo.tpl', '[show-box]'. str_repeat( 'a', \Nino\Components::TEMPLATE_LENGTH - 21 ). '[/show-box]' );
	$result = $read( $show, 'show' );

	return is_array( $result ) === true && $result['rejected'] === [] && strlen( $result['groups']['show']['demo'] ) === \Nino\Components::TEMPLATE_LENGTH;
} )() );

// A demo that is a link, or is no file
$away = $registerWork. '/away-demo';
mkdir( $away, 0755, true );
file_put_contents( $away. '/demo.tpl', '[show-box][/show-box]' );
unlink( $show. '/groups/show/demo.tpl' );
symlink( $away. '/demo.tpl', $show. '/groups/show/demo.tpl' );
$result = $read( $show, 'show' );
check( 'a demo.tpl that is a link, to a file outside the feature, is refused: no text is read through it, and the group stays', is_array( $result ) === true && $result['groups']['show']['demo'] === '' && str_contains( (string) ( $result['rejected']['demo show'] ?? '' ), 'is a link, or no file' ) === true );
unlink( $show. '/groups/show/demo.tpl' );
symlink( '../../../../away-demo', $show. '/groups/show/demo.tpl' );
$result = $read( $show, 'show' );
check( '...a link by a path that climbs out of the folder as well', is_array( $result ) === true && $result['groups']['show']['demo'] === '' && isset( $result['rejected']['demo show'] ) === true );
unlink( $show. '/groups/show/demo.tpl' );
mkdir( $show. '/groups/show/demo.tpl', 0755, true );
$result = $read( $show, 'show' );
check( '...and a folder with that name is no demo', is_array( $result ) === true && $result['groups']['show']['demo'] === '' && str_contains( (string) ( $result['rejected']['demo show'] ?? '' ), 'is a link, or no file' ) === true );
rmdir( $show. '/groups/show/demo.tpl' );

// The demo is in the register, the cache of it, and the stamp
file_put_contents( $show. '/groups/show/demo.tpl', $demo );
registerTouch( $show, $fixtureTime );
$project = registerProject( 'register-demo', [ '\\Nino\\Modules\\Show' ] );
$first = registerBoot( $project );
$stored = (array) include registerCache( $project, 'show' );
check( 'the demo is in the groups of the register, and in the file of the cache that a request writes', \Nino\Components::groups( $first )['show']['demo'] === $demo && ( $stored['groups']['show']['demo'] ?? null ) === $demo && ninoWarnings() === [] );
$second = registerBoot( $project );
check( '...and a request that finds the file as it was has the same demo from it', \Nino\Components::groups( $second )['show']['demo'] === $demo && \Nino\Components::groups( $second ) === \Nino\Components::groups( $first ) );
file_put_contents( $show. '/groups/show/demo.tpl', str_replace( 'Hello', 'Hallo', $demo ) );
touch( $show. '/groups/show/demo.tpl', $fixtureTime + 7 );
$third = registerBoot( $project );
check( '...a demo.tpl of the same size and another time is read again: the stamp holds it', \Nino\Components::groups( $third )['show']['demo'] === str_replace( 'Hello', 'Hallo', $demo ) );
unlink( $show. '/groups/show/demo.tpl' );
$fourth = registerBoot( $project );
check( '...and one that goes is seen: the group has no demo', \Nino\Components::groups( $fourth )['show']['demo'] === '' );

file_put_contents( $show. '/groups/show/demo.tpl', '[nobody]' );
$fifth = registerBoot( $project );
$said = ninoWarnings();
check( 'a demo that is refused is said once in a request, with the group and the reason - and the group and its components are registered all the same, with no file of the cache written for it',
	count( $said ) === 1 && str_contains( $said[0], 'demo.tpl' ) === true && str_contains( $said[0], 'calls "nobody"' ) === true && \Nino\Components::groups( $fifth )['show']['demo'] === '' && array_keys( \Nino\Components::components( $fifth ) ) === [ 'show-box', 'show-line' ] );
check( '...and the Features panel is told the same sentence', in_array( 'feature "show", group "show": its demo.tpl calls "nobody", which is no component of its feature or of basis', \Nino\Features::componentProblems( $fifth, 'show' ), true ) === true );
registerRemove( $show );

// What demoProblem() asks, by itself
$names = [ 'a', 'b' ];
check( 'demoProblem() takes a piece of html+ of the names it is given, with a container that is closed, a leaf and text', \Nino\Components::demoProblem( '[a]x[b text="y"][/a]', $names, [ 'a' => true, 'b' => false ] ) === null && \Nino\Components::demoProblem( 'text only', $names ) === null );
check( '...a text of more than a template is too long, and one of that length is not', str_contains( (string) \Nino\Components::demoProblem( str_repeat( 'a', \Nino\Components::TEMPLATE_LENGTH + 1 ), $names ), 'longer than' ) && \Nino\Components::demoProblem( str_repeat( 'a', \Nino\Components::TEMPLATE_LENGTH ), $names ) === null );
check( '...and not a container that a list of kinds says is a leaf, whose end is then stray', str_contains( (string) \Nino\Components::demoProblem( '[a]x[/a]', $names, [ 'a' => false ] ), 'stray-end' ) );
check( '...a name that is a prefix of one of the names, or its capitals, is none', \Nino\Components::demoProblem( '[ab]', $names ) !== null && \Nino\Components::demoProblem( '[A]', $names ) === null && \Nino\Components::demoProblem( '[a-]', $names ) !== null );

// The type of elements the kernel brings, in a demo
$loopType = array_key_first( \Nino\Elements::PLACEHOLDERS );
$loopFields = array_keys( \Nino\Elements::PLACEHOLDERS[$loopType]['model'] );
$loopKinds = [ 'a' => true, 'b' => false ];
check( 'a demo may loop the kernel\'s type of elements: the call names it, and a child takes a field of its model as its source and in an attribute',
	\Nino\Components::demoProblem( '[a '. $loopType. '][b .'. $loopFields[0]. '][b text=".'. $loopFields[1]. '"][/a]', $names, $loopKinds ) === null
	&& \Nino\Components::demoProblem( '[a '. $loopType. ' sort="-'. $loopFields[2]. '" limit="3"][a][b .'. $loopFields[0]. '][/a][/a]', $names, $loopKinds ) === null
	&& array_filter( $loopFields, static fn( string $field ): bool => \Nino\Components::demoProblem( '[a '. $loopType. '][b .'. $field. '][/a]', $names, $loopKinds ) !== null ) === [] );
check( '...a field outside the loop, after it and before it, is a reference of the project\'s',
	str_contains( (string) \Nino\Components::demoProblem( '[b .'. $loopFields[0]. ']', $names, $loopKinds ), 'source' )
	&& str_contains( (string) \Nino\Components::demoProblem( '[a '. $loopType. '][/a][b .'. $loopFields[0]. ']', $names, $loopKinds ), 'source' )
	&& str_contains( (string) \Nino\Components::demoProblem( '[b text=".'. $loopFields[0]. '"][a '. $loopType. '][/a]', $names, $loopKinds ), 'value' ) );
check( '...a field the model does not have, the number, the slug and the uri of the round, its fields in other letters or with other marks before them are none either',
	array_filter( [ '.nothing', '.id', '.slug', '.uri', '..'. $loopFields[0], '.'. strtoupper( $loopFields[0] ), '.'. $loopFields[0]. '.x', '/'. $loopFields[0], '~'. $loopFields[0] ], static fn( string $reference ): bool => \Nino\Components::demoProblem( '[a '. $loopType. '][b '. $reference. '][/a]', $names, $loopKinds ) === null || \Nino\Components::demoProblem( '[a '. $loopType. '][b text="'. $reference. '"][/a]', $names, $loopKinds ) === null ) === [] );
check( '...another type of elements, a project\'s type inside the loop and the name of the type as a value, none either',
	str_contains( (string) \Nino\Components::demoProblem( '[a /services][b .'. $loopFields[0]. '][/a]', $names, $loopKinds ), 'source' )
	&& str_contains( (string) \Nino\Components::demoProblem( '[a '. $loopType. '][a /services][/a][/a]', $names, $loopKinds ), 'source' )
	&& str_contains( (string) \Nino\Components::demoProblem( '[a '. $loopType. '][b text="'. $loopType. '"][/a]', $names, $loopKinds ), 'value' )
	&& str_contains( (string) \Nino\Components::demoProblem( '[a '. $loopType. 'x][/a]', $names, $loopKinds ), 'source' ) );
check( '...a textfill stays one, in the loop as outside of it',
	str_contains( (string) \Nino\Components::demoProblem( '[a '. $loopType. '][b text="[['. $loopFields[0]. ']]"][/a]', $names, $loopKinds ), '[[' ) );

$none = [];
ninoDone( $none );
