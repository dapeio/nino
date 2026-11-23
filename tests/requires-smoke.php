<?php
declare(strict_types=1);

/**
 *	Nino
 *	requires-smoke.php	Contract test for what a feature may call of another and where it stands among the others - the two rules that
 *											read 'requires' in the manifest and name no feature:
 *
 *												A. The demo of a group calls the components of its own feature and of the features it requires, those
 *													 they require as well - and nothing of a feature it does not require, however the name is written, whoever
 *													 else has a component of that name, whatever the requirement is (off, missing, itself, a circle)
 *												B. A feature stands after every feature it requires: where the register has its components and groups, in the
 *													 order the standard parts of the groups go into the style bundle, and in the places blocks are read from
 *
 *											The features are the two test features of the kernel (tests/fixtures/kit/Kit and tests/fixtures/rider/Addon, which
 *											requires it) and small ones the run writes below its features directory. The rules as they hold for Basis are tested in the suites
 *											of the feature Basis in the catalogue.
 *
 *	Usage: php tests/requires-smoke.php
 */

// A features directory of the run's own: the test features are copied into it, the rest is written there
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-requires-features-'. bin2hex( random_bytes( 6 ) ) );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

\Nino\Filesystem::copyDir( __DIR__. '/fixtures/kit/Kit', NINO_FEATURES_DIR. '/Kit' );
\Nino\Filesystem::copyDir( __DIR__. '/fixtures/rider/Addon', NINO_FEATURES_DIR. '/Addon' );

/**
 *	A small feature below the features directory of the run: one component, one group with a demo, and the standard part of the group
 *	where the demo is none of it
 *
 *	@param		string		$key					The key; the directory is that in studly caps
 *	@param		array			$requires			What the manifest requires
 *	@param		string|null	$demo				The demo.tpl of the group, null for none
 *	@param		string		$component		The name of its one component
 *	@param		bool			$part					Whether the group has a standard part
 *
 *	@return 	void
 */
function reqFeature( string $key, array $requires, ?string $demo, string $component = '', bool $part = false ): void {

	$name = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $key ) ) );
	$component = $component === '' ? $key. '-box' : $component;
	$dir = NINO_FEATURES_DIR. '/'. $name;

	foreach( [ '', '/components/'. $component, '/groups/'. $key, '/groups/'. $key. '/part' ] as $folder )
		is_dir( $dir. $folder ) === true || mkdir( $dir. $folder, 0755, true );

	file_put_contents( $dir. '/feature.php', '<?php return '. var_export( [ 'key' => $key, 'name' => $name, 'category' => 'content', 'version' => '1.0.0', 'requires' => $requires ], true ). ';' );
	file_put_contents( $dir. '/'. $name. '.php', '<?php namespace Nino\\Modules { class '. $name. ' {} }' );
	file_put_contents( $dir. '/components/'. $component. '/component.php', '<?php return '. var_export( [ 'group' => $key, 'label' => $name, 'in' => [ 'page' ], 'template' => '<i class="[[class]]"></i>' ], true ). ';' );
	file_put_contents( $dir. '/groups/'. $key. '/group.php', '<?php return '. var_export( [ 'label' => $name ], true ). ';' );

	if( $demo !== null )
		file_put_contents( $dir. '/groups/'. $key. '/demo.tpl', $demo );

	if( $part === true )
		file_put_contents( $dir. '/groups/'. $key. '/part/part.css', '.'. $component. ' { color: inherit; }'. "\n" );
}

/**
 *	A project with the features on that are named, in the order they are named, as the kernel boots it: the components
 *	of the ones that bring them are registered
 *
 *	@param		array			$keys					The keys of the features - or their classes - listed in this order
 *	@param		array|null	$sandbox			The project of an earlier boot, to boot it again
 *
 *	@return 	array										$appData
 */
function reqBoot( array $keys, ?array $sandbox = null ): array {

	$appData = $sandbox ?? ninoSandbox( 'requires' );
	$appData['/nino/modules'] = array_map( static fn( string $key ): string => str_starts_with( $key, '\\' ) === true ? $key : '\\Nino\\Modules\\'. str_replace( ' ', '', ucwords( str_replace( '-', ' ', $key ) ) ), $keys );

	foreach( [ './nino/features/all', './nino/features/listed', './nino/features/names', './nino/features/said', \Nino\Components::REGISTRY, \Nino\Components::OWNERS, \Nino\Components::GROUPS, '/nino/html/assets' ] as $key )
		unset( $appData[$key] );

	\Nino\Features::registerComponents( $appData );

	return $appData;
}

/**
 *	The demo of a group as the register of a project has it
 *
 *	@param		array 		&$appData			(reference)
 *	@param		string		$group
 *
 *	@return 	string										'' for none
 */
function reqDemo( array &$appData, string $group ): string {

	return (string) ( \Nino\Components::groups( $appData )[$group]['demo'] ?? '' );
}

$kit = "[band-wrap]\n\t[band-row]\n\t\t[band-col]\n\t\t\t[band-title text=\"A title\"]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]\n";

// --- A. the demo --------------------------------------------------------------------------------------------------------

echo "The demo of a group: the components of the features the feature requires\n";

$addon = reqBoot( [ 'kit', 'addon' ] );
$problems = ninoWarnings();
$drawn = \Nino\Html::renderSource( $addon, reqDemo( $addon, 'addon' ), \Nino\Html::context( $addon ) );
check( 'the demo of a feature that requires another one calls the components of both - read as it stands, nothing refused, and drawn it is the page of both',
	$problems === [] && reqDemo( $addon, 'addon' ) === (string) file_get_contents( NINO_FEATURES_DIR. '/Addon/groups/addon/demo.tpl' ) && str_contains( $drawn, 'band-wrap' ) === true && str_contains( $drawn, 'A note below it' ) === true
	&& \Nino\Features::componentProblems( $addon, 'addon' ) === [] );

$without = \Nino\Features::readComponents( NINO_FEATURES_DIR. '/Addon', 'addon' );
$with = \Nino\Features::readComponents( NINO_FEATURES_DIR. '/Addon', 'addon', \Nino\Features::componentNames( NINO_FEATURES_DIR. '/Kit' ) );
check( 'readComponents() reads the demos of a feature against the names it is given for the features it is built on - those of componentNames() are the folders of the components of the feature - and against none it refuses the demo that calls them',
	is_array( $without ) === true && array_keys( $without['rejected'] ) === [ 'demo addon' ] && $without['groups']['addon']['demo'] === '' && is_array( $with ) === true && $with['rejected'] === [] && $with['groups']['addon']['demo'] !== ''
	&& in_array( 'band-wrap', \Nino\Features::componentNames( NINO_FEATURES_DIR. '/Kit' ), true ) === true && \Nino\Features::componentNames( NINO_FEATURES_DIR. '/Nobody' ) === [] );
ninoWarnings();

reqFeature( 'loner', [], $kit );
$loner = reqBoot( [ 'kit', 'loner' ] );
$problems = ninoWarnings();
check( 'the demo of a feature that does not require it calls none of its components: the demo is left out with a sentence in the log and in the panel, the group and the component of the feature stay',
	reqDemo( $loner, 'loner' ) === '' && isset( \Nino\Components::groups( $loner )['loner'] ) === true && isset( \Nino\Components::components( $loner )['loner-box'] ) === true
	&& count( $problems ) === 1 && count( \Nino\Features::componentProblems( $loner, 'loner' ) ) === 1 );

reqFeature( 'chain', [ 'addon' ], $kit );
$chain = reqBoot( [ 'kit', 'addon', 'chain' ] );
$problems = ninoWarnings();
check( 'a requirement counts through the features that require it: a feature that requires the one that requires Kit calls the components of Kit, and one that has no requirement in between does not',
	$problems === [] && reqDemo( $chain, 'chain' ) === $kit && reqDemo( reqBoot( [ 'kit', 'loner' ] ), 'loner' ) === '' );
ninoWarnings();

reqFeature( 'lower', [], "[upper-box /]\n" );
reqFeature( 'upper', [ 'lower' ], null );
$both = reqBoot( [ 'lower', 'upper' ] );
check( 'a requirement is one way: the feature that is required may not call the components of the feature that requires it',
	reqDemo( $both, 'lower' ) === '' && isset( \Nino\Components::components( $both )['upper-box'] ) === true && count( ninoWarnings() ) === 1 );

reqFeature( 'ghost', [ 'nothing-here' ], $kit );
$ghost = reqBoot( [ 'kit', 'ghost' ] );
$problems = ninoWarnings();
check( 'a requirement that is not installed: nothing is thrown, its demo calls nothing of Kit, the feature has the problem the kernel gives a requirement that is not there and cannot be switched on',
	reqDemo( $ghost, 'ghost' ) === '' && count( $problems ) === 1 && count( \Nino\Features::get( $ghost, 'ghost' )['problems'] ) === 1
	&& is_string( \Nino\Features::activate( $ghost, 'ghost' ) ) === true );
ninoWarnings();

$off = reqBoot( [ 'addon' ] );
$problems = ninoWarnings();
check( 'a requirement that is off has no components to call: the demo is left out, nothing is thrown - and a feature that is switched on brings the requirement with it, after which the demo is read',
	reqDemo( $off, 'addon' ) === '' && count( $problems ) === 1 && ( static function() use ( $off ): bool {
		$project = ninoSandbox( 'requires-on' );
		$switched = \Nino\Features::activate( $project, 'addon' );
		$booted = reqBoot( array_map( 'strval', (array) $project['/nino/modules'] ), $project );
		return $switched === true && \Nino\Features::get( $booted, 'kit' )['active'] === true && reqDemo( $booted, 'addon' ) !== '';
	} )() );
ninoWarnings();

// A cache written while the requirement had a component is not read once it has not
reqFeature( 'base', [], null, 'base-x' );
reqFeature( 'top', [ 'base' ], "[base-x /]\n", 'top-box' );
$first = reqBoot( [ 'base', 'top' ] );
$accepted = reqDemo( $first, 'top' ) !== '';
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR. '/Base/components/base-x' );
$second = reqBoot( [ 'base', 'top' ], $first );
check( 'the schemas a feature was read to are not served for a requirement that has lost the component its demo calls: the demo is read again and left out',
	$accepted === true && reqDemo( $second, 'top' ) === '' && count( ninoWarnings() ) === 1 );
ninoWarnings();

reqFeature( 'ping', [ 'pong' ], "[pong-box /]\n" );
reqFeature( 'pong', [ 'ping' ], "[ping-box /]\n" );
$circle = reqBoot( [ 'ping', 'pong' ] );
$problems = ninoWarnings();
check( 'two features that require each other: nothing is thrown, each calls the components of the other in its demo, each is registered',
	$problems === [] && reqDemo( $circle, 'ping' ) !== '' && reqDemo( $circle, 'pong' ) !== '' && isset( \Nino\Components::components( $circle )['ping-box'], \Nino\Components::components( $circle )['pong-box'] ) );

$project = ninoSandbox( 'requires-circle' );
$both = \Nino\Features::activate( $project, 'ping' );
$on = array_keys( array_filter( \Nino\Features::all( $project ), static fn( array $feature ): bool => $feature['active'] === true ) );
$offFirst = \Nino\Features::deactivate( $project, 'ping' );
$offSecond = \Nino\Features::deactivate( $project, 'pong' );
check( 'a circle of requirements locks nothing: both are switched on by one switch-on, and each can be switched off again',
	$both === true && $on === [ 'ping', 'pong' ] && $offFirst === true && $offSecond === true
	&& array_filter( \Nino\Features::all( $project ), static fn( array $feature ): bool => $feature['active'] === true ) === [] );
ninoWarnings();

reqFeature( 'selfish', [ 'selfish' ], $kit );
$selfish = reqBoot( [ 'kit', 'selfish' ] );
check( 'a feature that requires itself has no requirement by it, and that gives its demo nothing of Kit',
	\Nino\Features::get( $selfish, 'selfish' )['requires'] === [] && reqDemo( $selfish, 'selfish' ) === '' && count( ninoWarnings() ) === 1 );

$write = static function( string $dir, array $manifest ): void {
	is_dir( NINO_FEATURES_DIR. '/'. $dir ) === true || mkdir( NINO_FEATURES_DIR. '/'. $dir, 0755, true );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/feature.php', '<?php return '. var_export( $manifest, true ). ';' );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/'. $dir. '.php', '<?php namespace Nino\\Modules { class '. $dir. ' {} }' );
};
$write( 'Shouty', [ 'key' => 'shouty', 'name' => 'Shouty', 'version' => '1.0.0', 'requires' => [ 'Kit' ] ] );
$reason = null;
check( 'a requirement is written as the key is, in lower case: a manifest that spells it otherwise is no feature and brings no demo',
	\Nino\Features::manifest( NINO_FEATURES_DIR. '/Shouty', $reason ) === null && is_string( $reason ) === true && isset( \Nino\Features::all( reqBoot( [ 'kit' ] ) )['shouty'] ) === false );
ninoWarnings();

// What is not written as a name is no call: the text is drawn as it is
reqFeature( 'spelt', [], "[Band-Wrap]\n[band_wrap]\n" );
$spelt = reqBoot( [ 'kit', 'spelt' ] );
$drawn = \Nino\Html::renderSource( $spelt, reqDemo( $spelt, 'spelt' ), \Nino\Html::context( $spelt ) );
ninoWarnings();
check( 'a name written in another case or with an underscore is no call of the component: what the demo has is text, and drawn it is text',
	str_contains( $drawn, '[Band-Wrap]' ) === true && str_contains( $drawn, '[band_wrap]' ) === true && str_contains( $drawn, 'class="band-wrap' ) === false );

// A component of the same name as one of Kit, which Kit has registered first, is Kit's
reqFeature( 'squat', [], "[band-wrap /]\n", 'band-wrap' );
$squat = reqBoot( [ 'kit', 'squat' ] );
$problems = ninoWarnings();
check( 'a feature that has a component of the name of one of Kit and does not require it does not get the demo: the name is Kit\'s, the component of the feature is not registered, and its demo is left out',
	( $squat[\Nino\Components::OWNERS]['names']['band-wrap'] ?? '' ) === 'kit' && reqDemo( $squat, 'squat' ) === ''
	&& count( array_filter( $problems, static fn( string $problem ): bool => str_contains( $problem, 'its demo.tpl calls "band-wrap"' ) === true ) ) === 1 );

reqFeature( 'squat-on', [ 'kit' ], "[band-wrap /]\n", 'band-wrap' );
$squatOn = reqBoot( [ 'kit', 'squat-on' ] );
$problems = ninoWarnings();
check( 'the same feature that requires Kit has its demo: the component it calls is Kit\'s, as it is meant to be',
	reqDemo( $squatOn, 'squat-on' ) === "[band-wrap /]\n" && array_filter( $problems, static fn( string $problem ): bool => str_contains( $problem, 'demo' ) === true ) === [] );

check( 'the root of the words of a feature that stands below features/ is /feature/ and its key, and so is that of a key nobody has',
	\Nino\Features::textRoot( $addon, 'addon' ) === '/feature/addon/' && \Nino\Features::textRoot( $addon, 'kit' ) === '/feature/kit/' && \Nino\Features::textRoot( $addon, 'nobody' ) === '/feature/nobody/' );

// --- B. the order -------------------------------------------------------------------------------------------------------

echo "\nA feature stands after the features it requires\n";

$entry = static fn( string $key, array $requires = [] ): array => [ 'key' => $key, 'requires' => $requires ];
$list = [ $entry( 'a', [ 'c' ] ), $entry( 'b' ), $entry( 'c', [ 'd' ] ), $entry( 'd' ), $entry( 'e', [ 'zz' ] ) ];
$keys = static fn( array $manifests ): array => array_values( array_map( static fn( array $manifest ): string => $manifest['key'], $manifests ) );

check( 'ordered(): a feature stands after every feature it requires, after those they require, and the others keep their places - a requirement that is not in the list is waited for by none',
	$keys( \Nino\Features::ordered( $list ) ) === [ 'b', 'd', 'c', 'a', 'e' ] && $keys( \Nino\Features::ordered( array_reverse( $list ) ) ) === [ 'e', 'd', 'c', 'b', 'a' ]
	&& array_keys( \Nino\Features::ordered( [ 'x' => $entry( 'a', [ 'b' ] ), 'y' => $entry( 'b' ) ] ) ) === [ 'y', 'x' ] );

$circular = [ $entry( 'a', [ 'b' ] ), $entry( 'b', [ 'c' ] ), $entry( 'c', [ 'a' ] ), $entry( 'd', [ 'd' ] ) ];
check( 'ordered(): a circle of requirements and a feature that requires itself throw nothing and leave nothing out - every feature is there, the circle is read where it is first met - and two manifests of one key both stand in the list',
	$keys( \Nino\Features::ordered( $circular ) ) === [ 'd', 'a', 'c', 'b' ] && $keys( \Nino\Features::ordered( [ $entry( 'a', [ 'b' ] ), $entry( 'a', [ 'b' ] ), $entry( 'b' ) ] ) ) === [ 'b', 'a', 'a' ] );

check( 'ordered(): a requirement is never drawn in front of a feature that stood before it - whoever requires it: the feature that waits moves back, the others keep their places',
	$keys( \Nino\Features::ordered( [ $entry( 'aa-straw', [ 'zz-evil' ] ), $entry( 'mm-victim' ), $entry( 'zz-evil' ) ] ) ) === [ 'mm-victim', 'zz-evil', 'aa-straw' ]
	&& $keys( \Nino\Features::ordered( [ $entry( 'x', [ 'y', 'z' ] ), $entry( 'q' ), $entry( 'y' ), $entry( 'z' ) ] ) ) === [ 'q', 'y', 'z', 'x' ] );

check( 'ordered(): the order of the files does not enter - the same features in any order of the list give an order in which every feature stands after what it requires, and a list that is in order is not touched',
	( static function() use ( $list, $keys ): bool {
		foreach( [ [ 0, 1, 2, 3, 4 ], [ 4, 3, 2, 1, 0 ], [ 2, 4, 0, 3, 1 ], [ 3, 0, 4, 1, 2 ] ] as $order ) {
			$given = array_map( static fn( int $at ): array => $list[$at], $order );
			$got = $keys( \Nino\Features::ordered( $given ) );
			if( array_search( 'd', $got, true ) > array_search( 'c', $got, true ) || array_search( 'c', $got, true ) > array_search( 'a', $got, true ) || count( $got ) !== 5 )
				return false;
		}
		return $keys( \Nino\Features::ordered( [ $list[3], $list[2], $list[0], $list[1] ] ) ) === [ 'd', 'c', 'a', 'b' ];
	} )() );

$addonFirst = reqBoot( [ 'addon', 'kit' ] );
$problems = ninoWarnings();
$names = array_keys( \Nino\Components::components( $addonFirst ) );
$groups = array_keys( \Nino\Components::groups( $addonFirst ) );
check( 'the register has the components and the groups of the feature that is required before those of the one that requires it, whatever the order of the modules: nothing is refused for it',
	$problems === [] && array_search( 'band-wrap', $names, true ) < array_search( 'addon-note', $names, true ) && array_search( 'band', $groups, true ) < array_search( 'addon', $groups, true )
	&& $names === array_keys( \Nino\Components::components( reqBoot( [ 'kit', 'addon' ] ) ) ) );

reqFeature( 'late', [ 'early' ], null, 'late-box', true );
reqFeature( 'early', [], null, 'early-box', true );
$parts = static function( array $keys ): array {
	$project = reqBoot( $keys );
	\Nino\Features::registerParts( $project );
	ninoWarnings();
	return array_map( static fn( string $path ): string => basename( dirname( $path, 4 ) ), (array) ( $project['/nino/html/assets']['/.cache/style.css'] ?? [] ) );
};
check( 'the standard parts of the groups go into the style bundle in that order: the part of the feature that is required first, whichever the project switched on first',
	$parts( [ 'late', 'early' ] ) === [ 'Early', 'Late' ] && $parts( [ 'early', 'late' ] ) === [ 'Early', 'Late' ] );

// Two features that bring a block of the same key: the one that is required is read first, and its block is the one of the key
foreach( [ 'aa-late' => [ 'zz-early' ], 'zz-early' => [] ] as $key => $requires ) {

	reqFeature( $key, $requires, null );

	$dir = NINO_FEATURES_DIR. '/'. str_replace( ' ', '', ucwords( str_replace( '-', ' ', $key ) ) ). '/blocks/shared';
	mkdir( $dir. '/text', 0755, true );
	file_put_contents( $dir. '/block.json', json_encode( [ 'name' => [ 'de_DE' => $key, 'en_US' => $key ], 'description' => [ 'de_DE' => $key, 'en_US' => $key ], 'category' => 'content', 'rank' => 50 ] ) );
	file_put_contents( $dir. '/block.tpl', "[band-wrap id=\"shared\"]\n\t[band-row]\n\t\t[band-col]\n\t\t\t[band-title ~title]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]" );
	file_put_contents( $dir. '/text/en_US.json', '{ "title": "'. $key. '" }' );
	file_put_contents( $dir. '/text/de_DE.json', '{ "title": "'. $key. '" }' );
}

$project = reqBoot( [ 'aa-late', 'kit', 'zz-early', 'addon' ] );
$origins = array_column( \Nino\Blocks::roots( $project ), 'origin' );
check( 'the places blocks are read from: the project, then the features in the order they are read in - the folder of a feature that is required before the one of the feature that requires it - and no feature is put in front of one that stood before it',
	$origins === [ 'project', 'kit', 'addon', 'zz-early', 'aa-late' ] );
check( 'a key that two features bring is the block of the one that stands first of them: the one that is required, here',
	( \Nino\Blocks::all( $project )['shared']['origin'] ?? '' ) === 'zz-early' );
ninoWarnings();

// The block of a feature is put out by no package and by no feature behind it; the project puts out any
reqFeature( 'blocky', [], null );
$dir = NINO_FEATURES_DIR. '/Blocky/blocks/solo';
mkdir( $dir. '/text', 0755, true );
$solo = static function( string $label ): array {
	return [ 'block.json' => json_encode( [ 'name' => [ 'de_DE' => $label, 'en_US' => $label ], 'description' => [ 'de_DE' => $label, 'en_US' => $label ], 'category' => 'content', 'rank' => 50 ] ),
		'block.tpl' => "[band-wrap id=\"solo\"]\n\t[band-row]\n\t\t[band-col]\n\t\t\t[band-title ~title]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]", 'text/en_US.json' => '{ "title": "'. $label. '" }', 'text/de_DE.json' => '{ "title": "'. $label. '" }' ];
};
foreach( $solo( 'feature' ) as $file => $bytes )
	file_put_contents( $dir. '/'. $file, $bytes );

$pack = static function( array &$project, string $label ) use ( $solo ): bool {
	$files = [ 'pack.json' => json_encode( [ 'key' => 'evilpack', 'version' => '1.0.0', 'name' => [ 'de_DE' => 'E', 'en_US' => 'E' ], 'description' => [ 'de_DE' => 'E', 'en_US' => 'E' ] ] ) ];
	foreach( $solo( $label ) as $file => $bytes )
		$files['solo/'. $file] = $bytes;
	return \Nino\Packages::place( $project, \Nino\Packages::BLOCKS, 'evilpack', $files ) === true;
};
$ownerOf = static fn( array &$project ): string => (string) ( \Nino\Blocks::all( $project )['solo']['origin'] ?? '-' );

$withPack = reqBoot( [ 'kit', 'blocky' ] );
$placed = $pack( $withPack, 'package' );
$withPack = reqBoot( [ 'kit', 'blocky' ], $withPack );
$packOnly = reqBoot( [ 'kit' ] );
$packOnlyPlaced = $pack( $packOnly, 'package' );
$packOnly = reqBoot( [ 'kit' ], $packOnly );
$withProject = reqBoot( [ 'kit', 'blocky' ] );
mkdir( ninoSandboxDir( $withProject ). '/private/blocks/solo/text', 0755, true );
foreach( $solo( 'project' ) as $file => $bytes )
	file_put_contents( ninoSandboxDir( $withProject ). '/private/blocks/solo/'. $file, $bytes );
$withProject = reqBoot( [ 'kit', 'blocky' ], $withProject );
check( 'a package of blocks puts out no block of a feature that is on - and has its block where no feature has one: the block of a feature is the one of the key, whoever else brings it',
	$placed === true && $ownerOf( $withPack ) === 'blocky' && $packOnlyPlaced === true && $ownerOf( $packOnly ) === 'pack:evilpack' );
check( 'the project puts out any block, a block of a feature that is on included - its own files stand first of what a feature or a package brings',
	$ownerOf( $withProject ) === 'project' );
ninoWarnings();

// --- C. what a foreign package may do -------------------------------------------------------------------------------------

echo "\nWhat a feature may call and own through what it requires\n";

// An empty folder of a feature that is required is no component: the name belongs to who has it registered
reqFeature( 'straw', [], null );
mkdir( NINO_FEATURES_DIR. '/Straw/components/band-title', 0755, true );
reqFeature( 'viastraw', [ 'straw' ], "[band-title text=\"T\"]\n" );
$viaStraw = reqBoot( [ 'kit', 'straw', 'viastraw' ] );
$problems = ninoWarnings();
check( 'a feature cannot call a component of Kit through a feature it requires that has an empty folder of that name: the name is Kit\'s, the demo is left out with an entry in the log (beside the one of the empty folder) and one in the panel',
	reqDemo( $viaStraw, 'viastraw' ) === '' && count( $problems ) === 2 && count( \Nino\Features::componentProblems( $viaStraw, 'viastraw' ) ) === 1
	&& ( $viaStraw[\Nino\Components::OWNERS]['names']['band-title'] ?? '' ) === 'kit' );

$noKit = reqBoot( [ 'straw', 'viastraw' ] );
ninoWarnings();
check( 'a name that is registered for nobody is no component a demo may call, whatever folder of a feature it stands built on bears it: where Kit is off the demo is left out as well',
	reqDemo( $noKit, 'viastraw' ) === '' && isset( $noKit[\Nino\Components::OWNERS]['names']['band-title'] ) === false );

// A component of its own that is left out for want of a renderer is no component of its own
reqFeature( 'dropper', [], "[band-title text=\"T\"]\n" );
mkdir( NINO_FEATURES_DIR. '/Dropper/components/band-title', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/Dropper/components/band-title/component.php', '<?php return '. var_export( [ 'group' => 'dropper', 'label' => 'D', 'in' => [ 'page' ] ], true ). ';' );
$dropped = reqBoot( [ 'kit', 'dropper' ] );
$dropProblems = ninoWarnings();
check( 'a component of its own that is left out for want of a template and a renderer does not give the demo the component of Kit: the demo is left out and the panel says it',
	reqDemo( $dropped, 'dropper' ) === '' && ( $dropped[\Nino\Components::OWNERS]['names']['band-title'] ?? '' ) === 'kit' && count( $dropProblems ) >= 2 && count( \Nino\Features::componentProblems( $dropped, 'dropper' ) ) >= 2 );

// Every requirement is followed, not the first alone
reqFeature( 'other', [], null );
reqFeature( 'two', [ 'other', 'kit' ], $kit );
ninoWarnings();
$two = reqBoot( [ 'kit', 'other', 'two' ] );
check( 'every requirement of a feature counts for its demo, the second as well as the first', reqDemo( $two, 'two' ) === $kit && ninoWarnings() === [] );

// The panel says what it leaves out
$squatted = reqBoot( [ 'kit', 'squat' ] );
ninoWarnings();
check( 'the Features panel says of a demo that is left out what the log says: an entry for the feature whose component has the name of another feature\'s, none for the feature that has the name of its own',
	count( \Nino\Features::componentProblems( $squatted, 'squat' ) ) >= 2 && \Nino\Features::componentProblems( $squatted, 'kit' ) === [] );

// Who owns a name does not move through a requirement
reqFeature( 'mm-victim', [], null, 'shared-box' );
reqFeature( 'zz-evil', [], null, 'shared-box' );
reqFeature( 'aa-straw', [], null );
$before = reqBoot( [ 'aa-straw', 'mm-victim', 'zz-evil' ] );
reqFeature( 'aa-straw', [ 'zz-evil' ], null );
$after = reqBoot( [ 'aa-straw', 'mm-victim', 'zz-evil' ] );
ninoWarnings();
check( 'a feature that comes to require another does not move the other in front of the features that stood before it: the owner of a name stays the same, with the requirement and without it',
	( $before[\Nino\Components::OWNERS]['names']['shared-box'] ?? '' ) === 'mm-victim' && ( $after[\Nino\Components::OWNERS]['names']['shared-box'] ?? '' ) === 'mm-victim' );

// The root of the words
check( 'the root of the words of a feature is asked for a key of the form of one: what is none - empty, a path, a null byte, capitals - has a root under which nothing lies',
	( static function() use ( $addon ): bool {
		foreach( [ '', '../module/legal', "kit\0", 'Kit', 'a/b', '-kit' ] as $bad )
			if( \Nino\Features::textRoot( $addon, $bad ) !== '/feature/-/' )
				return false;
		return \Nino\Features::textRoot( $addon, 'kit' ) === '/feature/kit/';
	} )() );

// A circle of requirements is told in the panel of each feature that is in it
$circles = ninoSandbox( 'requires-panel' );
$circles['/nino/admin/backups'] = false;
$circles['/nino/admin/logs'] = false;
$circles['/nino/auth/user'] = [];
$circles['/nino/auth/roles'] = [];
ninoUser( $circles, 'dev@example.com', [ \Nino\Modules\Features\Admin::MANAGE_PERM ] );
$circles['/nino/modules'] = [ '\\Nino\\Modules\\Kit', '\\Nino\\Modules\\Ping', '\\Nino\\Modules\\Pong', '\\Nino\\Modules\\Addon' ];
[ , $listBody ] = array_slice( ninoCall( $circles, [ \Nino\Modules\Features\Admin::class, 'apiList' ] ), 0, 2 );
$circle = array_column( (array) ( $listBody['features'] ?? [] ), 'circle', 'key' );
$words = [ include dirname( __DIR__ ). '/_admin/Nino/Modules/Features/text/de_DE.php', include dirname( __DIR__ ). '/_admin/Nino/Modules/Features/text/en_US.php' ];
check( 'the Features panel marks each feature that is in a circle of requirements, and no other, with a note of its own whose words stand in the texts of the panel in both languages: it is no problem, so that the feature can be switched off',
	( $circle['ping'] ?? null ) === true && ( $circle['pong'] ?? null ) === true && ( $circle['kit'] ?? null ) === false && ( $circle['addon'] ?? null ) === false
	&& \Nino\Features::inCircle( $circles, 'selfish' ) === false && \Nino\Features::get( $circles, 'ping' )['problems'] === []
	&& $words[0]['[[/_admin/features/label/circle]]'] !== '' && $words[1]['[[/_admin/features/label/circle]]'] !== '' && $words[0]['[[/_admin/features/label/circle]]'] !== $words[1]['[[/_admin/features/label/circle]]'] );

// --- D. who has a name: the one that is required, else the one that stands first in the modules ----------------------------------

echo "\nWho has a name that several features bring\n";

$of = static fn( array $project, string $name ): string => (string) ( $project[\Nino\Components::OWNERS]['names'][$name] ?? '-' );

// An accomplice: a requirement that the claimants do not have between them moves nothing
reqFeature( 'xlib', [], null );
reqFeature( 'vict', [ 'xlib' ], null, 'prized' );
reqFeature( 'yacc', [], null, 'prized' );
reqFeature( 'zlate', [], null );
$modules = [ 'xlib', 'vict', 'yacc', 'zlate' ];
$plain = $of( reqBoot( $modules ), 'prized' );
reqFeature( 'xlib', [ 'zlate' ], null );
$viaLib = $of( reqBoot( $modules ), 'prized' );
reqFeature( 'xlib', [], null );
reqFeature( 'vict', [ 'xlib', 'zlate' ], null, 'prized' );
$viaSelf = $of( reqBoot( $modules ), 'prized' );
ninoWarnings();
check( 'a requirement that the features that bring a name have not between them moves the name to nobody: it stays with the one that stands first, whether the feature it requires comes to require a late feature or it requires one itself',
	$plain === 'vict' && $viaLib === 'vict' && $viaSelf === 'vict' );

// Whoever is required goes before whoever requires
reqFeature( 'early-req', [ 'late-win' ], null, 'taken-box' );
reqFeature( 'late-win', [], null, 'taken-box' );
$required = reqBoot( [ 'early-req', 'late-win' ] );
$first = reqBoot( [ 'late-win', 'early-req' ] );
ninoWarnings();
check( 'a name that an earlier feature brings and a later one that the earlier requires also brings is the later one\'s: who is required goes before who requires - and where the one that requires stands later, the name stays the required one\'s',
	$of( $required, 'taken-box' ) === 'late-win' && $of( $first, 'taken-box' ) === 'late-win' );

// Who was there first keeps the name against a feature that is required by a third
reqFeature( 'aasquat', [], null, 'band-title' );
$squatFirst = reqBoot( [ 'aasquat', 'kit', 'addon' ] );
$squatLate = reqBoot( [ 'kit', 'addon', 'aasquat' ] );
ninoWarnings();
check( 'a feature that stands before Kit in the modules and does not require it keeps a name that Kit brings, though a third feature requires Kit; stood behind Kit, it does not have it',
	$of( $squatFirst, 'band-title' ) === 'aasquat' && $of( $squatLate, 'band-title' ) === 'kit' );

// Two that require each other: the one that stands first
reqFeature( 'ringc', [], null, 'ring-box' );
reqFeature( 'ping', [ 'pong' ], null, 'ring-box' );
reqFeature( 'pong', [ 'ping' ], null, 'ring-box' );
$ringA = reqBoot( [ 'ping', 'pong' ] );
$ringB = reqBoot( [ 'pong', 'ping' ] );
ninoWarnings();
$ringC = reqBoot( [ 'ping', 'pong', 'ringc' ] );
$ringD = reqBoot( [ 'ringc', 'ping', 'pong' ] );
check( 'a name that two features that require each other bring is the one of the one that stands first in the modules - with a third that is tied to none, the first of the three has it',
	$of( $ringA, 'ring-box' ) === 'ping' && $of( $ringB, 'ring-box' ) === 'pong' && $of( $ringC, 'ring-box' ) === 'ping' && $of( $ringD, 'ring-box' ) === 'ringc' );

// Three: one requires another, the third is not tied to them
reqFeature( 'ta', [ 'tb' ], null, 'tri-box' );
reqFeature( 'tb', [], null, 'tri-box' );
reqFeature( 'tc', [], null, 'tri-box' );
$tri1 = $of( reqBoot( [ 'ta', 'tc', 'tb' ] ), 'tri-box' );
$tri2 = $of( reqBoot( [ 'ta', 'tb', 'tc' ] ), 'tri-box' );
$tri3 = $of( reqBoot( [ 'tc', 'ta', 'tb' ] ), 'tri-box' );
ninoWarnings();
check( 'of three that bring a name - one requires another, the third is tied to none - the one that requires gives way to the one it requires, and of what is left the first in the modules has it',
	$tri1 === 'tc' && $tri2 === 'tb' && $tri3 === 'tc' );

// The group of a component is held by the same rule
reqFeature( 'ga', [ 'gb' ], null, 'ga-box' );
file_put_contents( NINO_FEATURES_DIR. '/Ga/components/ga-box/component.php', '<?php return '. var_export( [ 'group' => 'shared-g', 'label' => 'G', 'in' => [ 'page' ], 'template' => '<i></i>' ], true ). ';' );
mkdir( NINO_FEATURES_DIR. '/Ga/groups/shared-g', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/Ga/groups/shared-g/group.php', '<?php return [ "label" => "G" ];' );
reqFeature( 'gb', [], null, 'gb-box' );
file_put_contents( NINO_FEATURES_DIR. '/Gb/components/gb-box/component.php', '<?php return '. var_export( [ 'group' => 'shared-g', 'label' => 'G', 'in' => [ 'page' ], 'template' => '<i></i>' ], true ). ';' );
mkdir( NINO_FEATURES_DIR. '/Gb/groups/shared-g', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/Gb/groups/shared-g/group.php', '<?php return [ "label" => "G" ];' );
$groups = reqBoot( [ 'ga', 'gb' ] );
ninoWarnings();
check( 'a group that two features bring has the owner a name has: the one that is required', ( $groups[\Nino\Components::OWNERS]['groups']['shared-g'] ?? '' ) === 'gb' );

// The blocks of two features, and the Features panel says what is not valid
reqFeature( 'bk-early', [], null );
reqFeature( 'bk-late', [], null );
$blockOf = static function( string $dir, string $label ): void {
	mkdir( NINO_FEATURES_DIR. '/'. $dir. '/blocks/pair/text', 0755, true );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/pair/block.json', json_encode( [ 'name' => [ 'de_DE' => $label, 'en_US' => $label ], 'description' => [ 'de_DE' => $label, 'en_US' => $label ], 'category' => 'content', 'rank' => 50 ] ) );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/pair/block.tpl', "[band-wrap id=\"pair\"]\n\t[band-row]\n\t\t[band-col]\n\t\t\t[band-title ~title]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]" );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/pair/text/en_US.json', '{ "title": "'. $label. '" }' );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/pair/text/de_DE.json', '{ "title": "'. $label. '" }' );
};
$blockOf( 'BkEarly', 'early' );
$blockOf( 'BkLate', 'late' );
$blockFirst = reqBoot( [ 'kit', 'bk-late', 'bk-early' ] );
$blockSecond = reqBoot( [ 'kit', 'bk-early', 'bk-late' ] );
$blocksOf = static fn( array $project ): string => (string) ( \Nino\Blocks::all( $project )['pair']['origin'] ?? '-' );
reqFeature( 'bk-late', [ 'bk-early' ], null );
$blockRequired = reqBoot( [ 'kit', 'bk-late', 'bk-early' ] );
check( 'a block that two features that are on bring is the block of the one that stands first in the modules - not the one with the earlier key - unless one requires the other: then the one that is required has it',
	$blocksOf( $blockFirst ) === 'bk-late' && $blocksOf( $blockSecond ) === 'bk-early' && $blocksOf( $blockRequired ) === 'bk-early' );
check( 'the Features panel says of the feature that has lost a name or a block that it has, and to whom it belongs: one entry for the component, one for the block',
	count( \Nino\Features::componentProblems( $squatLate, 'aasquat' ) ) >= 1 && count( array_filter( \Nino\Features::componentProblems( $blockSecond, 'bk-late' ), static fn( string $line ): bool => str_contains( $line, 'bk-early' ) ) ) >= 1 );
ninoWarnings();

// A demo may not call through a nested call, and not through a twin
reqFeature( 'nest', [ 'straw' ], null );
file_put_contents( NINO_FEATURES_DIR. '/Nest/components/nest-box/component.php', '<?php return '. var_export( [ 'group' => 'nest', 'label' => 'N', 'in' => [ 'page' ], 'offers' => [ 'flow' ], 'template' => '<div>[[content]]</div>' ], true ). ';' );
file_put_contents( NINO_FEATURES_DIR. '/Nest/groups/nest/demo.tpl', "[nest-box]\n\t[band-title text=\"T\"]\n[/nest-box]\n" );
$nested = reqBoot( [ 'kit', 'straw', 'nest' ] );
ninoWarnings();
check( 'a call that stands inside another is held to the owners as well: a demo that calls what it may not inside a component of its own is left out', reqDemo( $nested, 'nest' ) === '' );

reqFeature( 'strawx', [], null );
mkdir( NINO_FEATURES_DIR. '/Strawx/components/twin-box', 0755, true );
foreach( [ 'Twin1' => 'twin-box', 'Twin2' => 'other-box' ] as $dir => $component ) {
	mkdir( NINO_FEATURES_DIR. '/'. $dir. '/components/'. $component, 0755, true );
	mkdir( NINO_FEATURES_DIR. '/'. $dir. '/groups/twin', 0755, true );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/feature.php', '<?php return '. var_export( [ 'key' => 'twin', 'name' => $dir, 'category' => 'content', 'version' => '1.0.0', 'requires' => $dir === 'Twin2' ? [ 'strawx' ] : [] ], true ). ';' );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/'. $dir. '.php', '<?php namespace Nino\\Modules { class '. $dir. ' {} }' );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/components/'. $component. '/component.php', '<?php return '. var_export( [ 'group' => 'twin', 'label' => 'T', 'in' => [ 'page' ], 'template' => '<i></i>' ], true ). ';' );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/groups/twin/group.php', '<?php return [ "label" => "Twin" ];' );
}
file_put_contents( NINO_FEATURES_DIR. '/Twin2/groups/twin/demo.tpl', "[twin-box /]\n" );
$twins = reqBoot( [ 'twin1', 'twin2', 'strawx' ] );
check( 'a twin - a second folder of the key - may not call in a demo what the first folder brings: the name is of the key, the component is not the twin\'s own', reqDemo( $twins, 'twin' ) === '' );
ninoWarnings();

// Three features bring one name and one block: the owner is asked of all three at once, for the block as for the component - asked of two at a time, in the order the
// places are read, the block would go to the one that has to give way
foreach( [ 'tri-a' => [ 'tri-z' ], 'tri-m' => [], 'tri-z' => [] ] as $key => $requires ) {
	reqFeature( $key, $requires, null, 'tri-c' );
	$dir = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $key ) ) );
	mkdir( NINO_FEATURES_DIR. '/'. $dir. '/blocks/tri/text', 0755, true );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/tri/block.json', json_encode( [ 'name' => [ 'de_DE' => $key, 'en_US' => $key ], 'description' => [ 'de_DE' => $key, 'en_US' => $key ], 'category' => 'content', 'rank' => 50 ] ) );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/tri/block.tpl', "[band-wrap id=\"tri\"]\n\t[band-row]\n\t\t[band-col]\n\t\t\t[band-title ~title]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]" );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/tri/text/en_US.json', '{ "title": "'. $key. '" }' );
	file_put_contents( NINO_FEATURES_DIR. '/'. $dir. '/blocks/tri/text/de_DE.json', '{ "title": "'. $key. '" }' );
}
$triOf = static function( array $order ): array {
	$project = reqBoot( array_merge( [ 'kit' ], $order ) );
	$owner = [ (string) ( $project[\Nino\Components::OWNERS]['names']['tri-c'] ?? '-' ), (string) ( \Nino\Blocks::all( $project )['tri']['origin'] ?? '-' ) ];
	ninoWarnings();
	return $owner;
};
check( 'of three features that bring one component and one block - one requires another, the third is tied to none - the block has the owner the component has, in every order of the modules',
	$triOf( [ 'tri-a', 'tri-m', 'tri-z' ] ) === [ 'tri-m', 'tri-m' ] && $triOf( [ 'tri-z', 'tri-a', 'tri-m' ] ) === [ 'tri-z', 'tri-z' ] && $triOf( [ 'tri-m', 'tri-z', 'tri-a' ] ) === [ 'tri-m', 'tri-m' ]
	&& $triOf( [ 'tri-a', 'tri-z', 'tri-m' ] ) === [ 'tri-z', 'tri-z' ] );

// A feature that does not get the group of a component claims no name with it: the name goes to the next that brings it, and nobody's component is left without an owner
reqFeature( 'dead-a', [], null, 'dead-a-box' );
reqFeature( 'dead-b', [], null, 'disputed' );
reqFeature( 'dead-c', [], null, 'disputed' );
mkdir( NINO_FEATURES_DIR. '/DeadA/groups/dead-b', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/DeadA/groups/dead-b/group.php', '<?php return [ "label" => "Taken" ];' );
file_put_contents( NINO_FEATURES_DIR. '/DeadA/components/dead-a-box/component.php', '<?php return '. var_export( [ 'group' => 'dead-b', 'label' => 'A', 'in' => [ 'page' ], 'template' => '<i class="[[class]]"></i>' ], true ). ';' );
$dead = reqBoot( [ 'dead-a', 'dead-b', 'dead-c' ] );
ninoWarnings();
check( 'a component in a group its feature brings and loses claims no name: the group is the first feature\'s, the component of the second is not registered, and the third - which brings the same name in a group of its own - has it',
	( $dead[\Nino\Components::OWNERS]['groups']['dead-b'] ?? '' ) === 'dead-a' && ( $dead[\Nino\Components::OWNERS]['names']['disputed'] ?? '' ) === 'dead-c' && isset( $dead[\Nino\Components::REGISTRY]['disputed'] ) === true );

ninoDone( $addon );
