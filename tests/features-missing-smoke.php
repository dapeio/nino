<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	features-missing-smoke.php	Test of a project that switched a feature on while the kernel carried it as a module of its own - Basis - and has the kernel that
 *											does not: the class of the feature stands in its modules, the folder below features/ is not there. The boot carries on and
 *											says so once; a page that calls its components draws nothing for them and the rest of it is drawn; the Features panel lists the feature as
 *											missing, with the sentence that says where it comes from; the folder put in place is the feature that is on at once; and the
 *											switch-off of a feature that is not there takes its class out of the modules and writes nothing else.
 *
 *	Usage: php tests/features-missing-smoke.php
 */

// A features directory of the run's own
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-features-missing-'. bin2hex( random_bytes( 6 ) ) );
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

$old = plainBoot( [ '\\Nino\\Modules\\Basis', '\\Nino\\Modules\\Assets' ] );
$listed = \Nino\Features::all( $old );
$said = ninoWarnings();
$config = $old['/nino/modules'];
check( 'the boot carries on: no feature is listed, the one that is not there is named in missing() and in rejected() with a sentence that says to install it, and the log has one line about it',
	$listed === [] && \Nino\Features::missing( $old ) === [ 'Basis' ] && str_contains( (string) ( \Nino\Features::rejected( $old )['Basis'] ?? '' ), 'install it from the catalogue' ) === true
	&& array_keys( \Nino\Features::rejectedActive( $old ) ) === [ 'Basis' ] && count( $said ) === 1 && str_contains( $said[0], 'Basis' ) === true );

// Every request that asks for the features meets it: fifteen of them put one line in the log
for( $request = 0; $request < 15; $request++ ) {
	unset( $old['./nino/features/all'], $old['./nino/features/missing'], $old['./nino/features/rejected'] );
	\Nino\Features::all( $old );
}
$requests = ninoWarnings();
check( 'fifteen more requests that ask for the features say nothing: the line of the first one stands once in the window, as the kernel says what a page calls that nobody registered', count( $requests ) === 0 );
unset( $old['./nino/features/all'], $old['./nino/features/missing'], $old['./nino/features/rejected'] );

// A directory whose manifest is refused and that has no class file is the refused manifest it is, with its own reason - and not a feature that is missing
mkdir( NINO_FEATURES_DIR. '/Broken', 0755, true );
file_put_contents( NINO_FEATURES_DIR. '/Broken/feature.php', '<?php return [ "key" => "broken", "name" => "Broken", "version" => "one" ];' );
$refusedProject = plainBoot( [ '\\Nino\\Modules\\Broken' ] );
\Nino\Features::all( $refusedProject );
$reason = (string) ( \Nino\Features::rejected( $refusedProject )['Broken'] ?? '' );
check( 'a directory with a manifest that is refused and no class keeps the reason of the manifest: it is not a feature that is missing, and the sentence of the missing one is not its sentence',
	str_contains( $reason, 'version' ) === true && str_contains( $reason, 'no directory' ) === false && \Nino\Features::missing( $refusedProject ) === [] && array_keys( \Nino\Features::rejectedActive( $refusedProject ) ) === [ 'Broken' ] );
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR. '/Broken' );
ninoWarnings();

// A page that calls the components of the feature draws nothing for them, and nothing is thrown
$page = \Nino\Html::renderHtml( $old, '<p>before</p>[plain-box]<p>after</p>' );
$noticed = ninoWarnings();
check( 'a page that calls a component that is not there draws nothing for the call, says so in the log, and the rest of the page is drawn',
	str_contains( $page, '<p>before</p>' ) === true && str_contains( $page, '<p>after</p>' ) === true && str_contains( $page, 'box</i>' ) === false && $noticed !== [] );

ninoUser( $old, 'dev@example.com' );
[ $status, $body ] = ninoCall( $old, [ \Nino\Modules\Features\Admin::class, 'apiList' ] );
$entry = array_column( (array) ( $body['rejected'] ?? [] ), null, 'dir' )['Basis'] ?? [];
check( 'the Features panel lists it among the directories that are refused, as one that is switched on and is missing, and lists no feature',
	$status === 200 && ( $entry['missing'] ?? null ) === true && ( $entry['active'] ?? null ) === true && is_string( $entry['reason'] ?? null ) === true && ( $body['features'] ?? null ) === [] );
ninoWarnings();

// The folder comes: the class is listed already, so the feature is on at once, as an installation of an older version that is to be applied
plainBasis();
$placed = plainBoot( $config, $old );
check( 'once the folder is put in place the feature is the one that is on, with nothing missing and nothing refused, its component is drawn, and, in a project that never recorded its version, it asks to be applied as an update',
	\Nino\Features::missing( $placed ) === [] && \Nino\Features::rejected( $placed ) === [] && ( \Nino\Features::get( $placed, 'basis' )['active'] ?? null ) === true && ( \Nino\Features::get( $placed, 'basis' )['update'] ?? null ) === true
	&& str_contains( \Nino\Html::renderHtml( $placed, '[plain-box]' ), '>box</i>' ) === true && ninoWarnings() === [] );

// Switching off what is not there takes the class out of the modules and nothing else: a feature of another name, since the class of Basis is loaded by now
$gone = plainBoot( [ '\\Nino\\Modules\\Vanished', '\\Nino\\Modules\\Assets' ], $placed );
ninoWarnings();
\Nino\Filesystem::putFileContent( $gone, '/config.php', [ '/nino/modules' => (array) $gone['/nino/modules'], '/nino/html/routes-probe' => 'kept' ] );
[ $offStatus ] = ninoCall( $gone, [ \Nino\Modules\Features\Admin::class, 'apiDeactivateRefused' ], [ 'dir' => 'Vanished' ] );
$after = include \Nino\Filesystem::path( $gone, '/config.php' );
check( 'the switch-off of a feature that is not there takes its class out of the modules and writes nothing else to the configuration',
	$offStatus === 200 && $after['/nino/modules'] === [ '\\Nino\\Modules\\Assets' ] && $after['/nino/html/routes-probe'] === 'kept' && \Nino\Features::missing( $gone ) === [] );
ninoWarnings();

$none = [];
ninoDone( $none );
