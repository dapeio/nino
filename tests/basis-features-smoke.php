<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-features-smoke.php	Contract test for what \Nino\Features and the Features panel say of the feature Basis, the one the kernel delivers:
 *														that it is listed as a feature of the kernel, beside the ones below features/, under its own name and with the install
 *														unit it brings. The rest of what the list does is tests/features-smoke.php, which reads a directory of its own.
 *
 *	Usage: php tests/basis-features-smoke.php
 */

define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'basis-features' );
$appData['/nino/admin/backups']	= false;
$appData['/nino/admin/logs']		= false;
$appData['/nino/auth/user']			= [];
$appData['/nino/auth/roles']		= [];
ninoUser( $appData, 'dev@example.com', [ \Nino\Modules\Features\Admin::MANAGE_PERM ] );
$ignored = ninoWarnings();

// --- Discovery ---------------------------------------------------------------

echo "Features::all - the feature of the kernel\n";

$all = \Nino\Features::all( $appData );

check( 'Basis is the one feature the kernel delivers: a module of the kernel with a feature.php, listed first of the keys with the ones below features/ - and it is off', array_keys( array_filter( $all, static fn( array $feature ): bool => $feature['shipped'] === true ) ) === [ 'basis' ]
	&& array_keys( $all ) === [ 'basis', 'boxy', 'helper', 'old', 'sample' ] && $all['basis']['active'] === false );

// --- The panel ---------------------------------------------------------------

echo "\nThe Features panel - apiList\n";

[ $status, $body ] = array_slice( ninoCall( $appData, [ \Nino\Modules\Features\Admin::class, 'apiList' ] ), 0, 2 );
$byKey = array_column( $body['features'] ?? [], null, 'key' );

check( 'apiList lists it by its name, as the panel reads the list: in the order of the names a person reads, before the sample feature, with the name of the feature in German', $status === 200
	&& array_column( $body['features'], 'key' ) === [ 'basis', 'sample', 'boxy', 'helper', 'old' ] && array_column( $body['features'], 'name' ) === [ 'Basis-Komponenten', 'Beispiel-Feature', 'Boxy', 'Helper', 'Old' ] );
check( 'it says it is delivered with the kernel, and brings an install unit', $byKey['basis']['shipped'] === true && $byKey['basis']['unit'] === true );

ninoDone( $appData );
