<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-owners-smoke.php	Test of what the kernel lets a feature do with the names and the blocks of the feature it delivers, Basis: a demo of a feature that does not
 *													require it may call its components, whether it is on or off, and no feature that is none of its own may call what a third feature owns;
 *													a block of Basis is the block of its key against a block of the project - the features the kernel delivers stand in front of it.
 *
 *	Usage: php tests/basis-owners-smoke.php
 */

define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-basis-owners-'. bin2hex( random_bytes( 6 ) ) );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

$dir = NINO_FEATURES_DIR. '/Plain';
mkdir( $dir. '/components/plain-box', 0755, true );
mkdir( $dir. '/groups/plain', 0755, true );
file_put_contents( $dir. '/feature.php', '<?php return '. var_export( [ 'key' => 'plain', 'name' => 'Plain', 'category' => 'content', 'version' => '1.0.0' ], true ). ';' );
file_put_contents( $dir. '/Plain.php', '<?php namespace Nino\\Modules { class Plain {} }' );
file_put_contents( $dir. '/components/plain-box/component.php', '<?php return '. var_export( [ 'group' => 'plain', 'label' => 'P', 'in' => [ 'page' ], 'template' => '<i></i>' ], true ). ';' );
file_put_contents( $dir. '/groups/plain/group.php', '<?php return [ "label" => "Plain" ];' );
file_put_contents( $dir. '/groups/plain/demo.tpl', "[section-wrap]\n\t[section-row]\n\t\t[section-col]\n\t\t\t[section-title text=\"T\"]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]\n" );

$boot = static function( array $modules, ?array $again = null ): array {
	$appData = $again ?? ninoSandbox( 'basis-owners' );
	foreach( [ \Nino\Components::REGISTRY, \Nino\Components::OWNERS, \Nino\Components::GROUPS, './nino/features/listed', './nino/features/all', './nino/blocks/state' ] as $key )
		unset( $appData[$key] );
	$appData['/nino/modules'] = $modules;
	\Nino\Features::registerComponents( $appData );
	return $appData;
};

echo "A demo may call the components of the feature the kernel delivers\n";

$on = $boot( [ '\\Nino\\Modules\\Basis', '\\Nino\\Modules\\Plain' ] );
$onWarnings = ninoWarnings();
$off = $boot( [ '\\Nino\\Modules\\Plain' ] );
$offWarnings = ninoWarnings();
check( 'a feature that does not require Basis calls its components in a demo, where Basis is on - the name is Basis\'s - and where it is off, where nobody owns it',
	( \Nino\Components::groups( $on )['plain']['demo'] ?? '' ) !== '' && $onWarnings === [] && ( $on[\Nino\Components::OWNERS]['names']['section-wrap'] ?? '' ) === 'basis'
	&& ( \Nino\Components::groups( $off )['plain']['demo'] ?? '' ) !== '' && $offWarnings === [] && isset( $off[\Nino\Components::OWNERS]['names']['section-wrap'] ) === false );

echo "\nThe blocks of the feature the kernel delivers\n";

$project = $boot( [ '\\Nino\\Modules\\Basis' ] );
$copy = ninoSandboxDir( $project ). '/private/blocks/hero-full';
\Nino\Filesystem::copyDir( dirname( __DIR__ ). '/_nino/Nino/Modules/Basis/blocks/hero-full', $copy );
$json = json_decode( (string) file_get_contents( $copy. '/block.json' ), true );
$json['name'] = [ 'de_DE' => 'PROJECT', 'en_US' => 'PROJECT' ];
file_put_contents( $copy. '/block.json', json_encode( $json ) );
$project = $boot( [ '\\Nino\\Modules\\Basis' ], $project );
ninoWarnings();
check( 'a block of the project with the key of a block of Basis does not put it out: the features the kernel delivers stand in front of the project',
	( \Nino\Blocks::all( $project )['hero-full']['origin'] ?? '' ) === 'basis' && array_column( \Nino\Blocks::roots( $project ), 'origin' ) === [ 'basis', 'project' ] );

ninoDone( $project );
