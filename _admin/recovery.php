<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Recovery					The way back in when the accounts are what is broken: asks
 *												for the recovery secret the wizard's last step set, then
 *												restores a backup or resets an account's password. Its own
 *												entry point, on purpose - it has to work when the workbench
 *												does not (see \Nino\Admin\Recovery)
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
// The kernel boots here on its own, not through the site's index.php: a
// NINO_PRIVATE_DIR, NINO_CONFIG_DIR or NINO_APP_DIR defined there is not in
// force for the workbench. A deployment that uses one defines the same
// constant, with the same value, here as well - and in index.php
// (without it this page finds no config.php, looks in the wrong place
// for the accounts it is meant to repair).
// define( 'NINO_PRIVATE_DIR', '/absolute/path/outside/webroot' );
// define( 'NINO_CONFIG_DIR', '/absolute/path/outside/webroot' );
// define( 'NINO_APP_DIR', '/absolute/path/to/app' );

require '../_nino/Nino.php';
require 'Admin.php';

// Boots without config.php, like the wizard: a broken one is one of the
// reasons to be here
$appData = \Nino\init( true );
\Nino\Admin\Recovery::init( $appData );

$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
