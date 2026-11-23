<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	features-plain-smoke.php	Test that no feature is more than a feature: a folder below features/ of any name - the name Basis too, which the kernel once
 *											carried as a module of its own - is read, listed, switched on and off, offered by the catalogue and removed by the one
 *											rule for all: the manifest, the standard part and the script below features/, the components it registers, the offer of the
 *											catalogue for an older one, its switch-off and its removal. A project that has the class of a feature in its modules while
 *											its folder is not there is tested by tests/features-missing-smoke.php.
 *
 *											What Basis is, as a feature, is tested by the suites of the feature in the catalogue.
 *
 *	Usage: php tests/features-plain-smoke.php
 */

// A features directory of the run's own
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-features-plain-'. bin2hex( random_bytes( 6 ) ) );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

/**
 *	A feature of the folder Basis: its manifest with a script, a class, one component, a group with a standard part
 *
 *	@param		string		$version
 *
 *	@return 	void
 */
function plainBasis( string $version = '1.0.0' ): void {

	$dir = NINO_FEATURES_DIR. '/Basis';

	foreach( [ '', '/assets', '/components/plain-box', '/groups/plain', '/groups/plain/part' ] as $folder )
		is_dir( $dir. $folder ) === true || mkdir( $dir. $folder, 0755, true );

	file_put_contents( $dir. '/feature.php', '<?php return '. var_export( [ 'key' => 'basis', 'name' => 'Basis', 'category' => 'content', 'version' => $version, 'assets' => [ '/features/Basis/assets/probe.js' ] ], true ). ';' );
	file_put_contents( $dir. '/Basis.php', '<?php namespace Nino\\Modules { class Basis {} }' );
	file_put_contents( $dir. '/assets/probe.js', "// probe\n" );
	file_put_contents( $dir. '/components/plain-box/component.php', '<?php return '. var_export( [ 'group' => 'plain', 'label' => 'Box', 'in' => [ 'page' ], 'template' => '<i class="[[class]]">box</i>' ], true ). ';' );
	file_put_contents( $dir. '/groups/plain/group.php', '<?php return [ "label" => "Plain" ];' );
	file_put_contents( $dir. '/groups/plain/part/part.css', ".plain-box { color: inherit; }\n" );
}

/**
 *	A project as the kernel boots it, with the classes named in its modules
 *
 *	@param		array			$modules
 *	@param		array|null	$project			The project of an earlier boot, to boot it again
 *
 *	@return 	array										$appData
 */
function plainBoot( array $modules, ?array $project = null ): array {

	$appData = $project ?? ninoSandbox( 'features-plain' );
	$appData['/nino/modules'] = $modules;

	foreach( [ './nino/features/all', './nino/features/listed', './nino/features/names', './nino/features/said', './nino/features/missing', \Nino\Components::REGISTRY, \Nino\Components::OWNERS, \Nino\Components::GROUPS, '/nino/html/assets' ] as $key )
		unset( $appData[$key] );

	\Nino\Html::init( $appData );
	\Nino\Features::registerAssets( $appData );
	\Nino\Features::registerComponents( $appData );
	\Nino\Features::registerParts( $appData );

	return $appData;
}

echo "A feature of the folder Basis is a feature like any other\n";

plainBasis();
$project = plainBoot( [] );
$all = \Nino\Features::all( $project );
check( 'it is listed under its key, with no problem and no refusal, and no module of the kernel or of the workbench has the name of its folder',
	array_keys( $all ) === [ 'basis' ] && $all['basis']['problems'] === [] && $all['basis']['active'] === false && \Nino\Features::rejected( $project ) === [] && \Nino\Features::moduleOwner( 'Basis' ) === null
	&& \Nino\Features::missing( $project ) === [] && ninoWarnings() === [] );

$switched = \Nino\Features::activate( $project, 'basis' );
$on = plainBoot( (array) $project['/nino/modules'], $project );
check( 'switched on, its component is registered and drawn, its script and its standard part are files of the bundles below features/, and nothing was said',
	$switched === true && \Nino\Features::components( $on, 'basis' ) === [ 'plain-box' ] && str_contains( \Nino\Html::renderHtml( $on, '[plain-box]' ), '>box</i>' ) === true
	&& in_array( '/features/Basis/assets/probe.js', (array) ( $on['/nino/html/assets']['/.cache/script.js'] ?? [] ), true ) === true
	&& in_array( '/features/Basis/groups/plain/part/part.css', (array) ( $on['/nino/html/assets']['/.cache/style.css'] ?? [] ), true ) === true && ninoWarnings() === [] );

check( 'its words are the ones of every feature: below /feature/basis/',
	\Nino\Features::textRoot( $on, 'basis' ) === '/feature/basis/' );

// A callback of the hook for the standard parts may name a stylesheet of a feature and none of the kernel's: the part of the group stays and the answer is said
$hooked = $project;
$hooked['/nino/modules'] = (array) $on['/nino/modules'];
$hooked['./nino/filesystem/path'] = dirname( __DIR__ );
foreach( [ './nino/features/all', './nino/features/listed', './nino/features/said', \Nino\Components::REGISTRY, \Nino\Components::OWNERS, \Nino\Components::GROUPS, '/nino/html/assets' ] as $key )
	unset( $hooked[$key] );
\Nino\Html::init( $hooked );
\Nino\Features::registerAssets( $hooked );
\Nino\Features::registerComponents( $hooked );
\Nino\Callbacks::registerCallback( $hooked, \Nino\Components::PARTS, static fn( array &$appData, array $asked ): array => [ 'css' => '/_nino/Nino/Modules/Form/assets/form.css' ] );
ninoWarnings();
\Nino\Features::registerParts( $hooked );
$styles = (array) ( $hooked['/nino/html/assets']['/.cache/style.css'] ?? [] );
check( 'an answer of the hook for the standard parts that names a stylesheet below the modules of the kernel is not taken: the part of the group stands and the answer is said',
	in_array( '/features/Basis/groups/plain/part/part.css', $styles, true ) === true && in_array( '/_nino/Nino/Modules/Form/assets/form.css', $styles, true ) === false && count( ninoWarnings() ) === 1 );

ninoUser( $on, 'dev@example.com' );
[ $listStatus, $listBody ] = ninoCall( $on, [ \Nino\Modules\Features\Admin::class, 'apiList' ] );
$row = array_column( (array) ( $listBody['features'] ?? [] ), null, 'key' )['basis'] ?? [];
// A feature that is off and requires it: the row of Basis names it, for the question before a removal
mkdir( NINO_FEATURES_DIR. '/Needs', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/Needs/feature.php', '<?php return '. var_export( [ 'key' => 'needs', 'name' => [ 'en_US' => 'Needs', 'de_DE' => 'Braucht' ], 'version' => '1.0.0', 'requires' => [ 'basis' ] ], true ). ';' );
file_put_contents( NINO_FEATURES_DIR. '/Needs/Needs.php', '<?php namespace Nino\\Modules { class Needs {} }' );
unset( $on['./nino/features/all'], $on['./nino/features/listed'] );
[ , $listBody ] = ninoCall( $on, [ \Nino\Modules\Features\Admin::class, 'apiList' ] );
$rows = array_column( (array) ( $listBody['features'] ?? [] ), null, 'key' );
check( 'the row of a feature names the features that require it, by their names in the language of the panel, and a feature nobody requires names none',
	( $rows['basis']['dependents'] ?? null ) === [ 'Braucht' ] && ( $rows['needs']['dependents'] ?? null ) === [] );
[ $refusedStatus, $refusedBody ] = ninoCall( $on, [ \Nino\Modules\Features\Admin::class, 'apiRemove' ], [ 'key' => 'basis' ] );
check( 'the panel lists it as active, and refuses to remove it while it is on, for the one reason it gives any feature that is on',
	$listStatus === 200 && ( $row['active'] ?? null ) === true && $refusedStatus === 400 && ( $refusedBody['code'] ?? '' ) === 'features_remove_active' && is_dir( NINO_FEATURES_DIR. '/Basis' ) === true );

// A catalogue that has a newer one: the local one is not the one the kernel carries, so it is upgraded like any other
$offers = \Nino\Catalogue::offers( $on, \Nino\Catalogue::parse( ninoCatalogue( [ [ 'key' => 'basis', 'name' => 'Basis', 'description' => [ 'en_US' => 'Basis', 'de_DE' => 'Basis' ], 'version' => '1.1.0', 'nino' => '^2.0', 'directory' => 'Basis', 'archive' => 'https://catalogue.test/features/basis-1.1.0.tar.gz', 'sha256' => str_repeat( 'a', 64 ), 'size' => 100, 'released' => '2026-09-07' ] ] ) ), 'feature' );
check( 'the catalogue offers a newer version of it as an upgrade, with the one on disk named',
	( $offers['basis']['state'] ?? '' ) === 'upgrade' && ( $offers['basis']['local'] ?? '' ) === '1.0.0' && ( $offers['basis']['version'] ?? '' ) === '1.1.0' );

[ $offStatus ] = ninoCall( $on, [ \Nino\Modules\Features\Admin::class, 'apiDeactivate' ], [ 'key' => 'basis' ] );
unset( $on['./nino/features/all'], $on['./nino/features/listed'] );
[ $removeStatus, $removeBody ] = ninoCall( $on, [ \Nino\Modules\Features\Admin::class, 'apiRemove' ], [ 'key' => 'basis' ] );
check( 'switched off it is removed like any feature: the folder is gone, and the panel says which feature it was',
	$offStatus === 200 && $removeStatus === 200 && ( $removeBody['removed'] ?? '' ) === 'basis' && is_dir( NINO_FEATURES_DIR. '/Basis' ) === false && ( \Nino\Features::get( $on, 'needs' )['problems'] ?? [] ) !== [] );
ninoWarnings();

$none = [];
ninoDone( $none );
