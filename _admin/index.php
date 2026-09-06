<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Admin							The workbench's entry point - the setup wizard until a
 *												project exists, the tool itself from then on (see Admin::init())
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
// The kernel boots here on its own, not through the site's index.php: a
// NINO_PRIVATE_DIR, NINO_CONFIG_DIR or NINO_APP_DIR defined there is not in
// force for the workbench. A deployment that uses one defines the same
// constant, with the same value, here as well - and in recovery.php
// (without it the workbench finds no config.php, takes the project for
// uninstalled and offers the setup wizard).
// define( 'NINO_PRIVATE_DIR', '/absolute/path/outside/webroot' );
// define( 'NINO_CONFIG_DIR', '/absolute/path/outside/webroot' );
// define( 'NINO_APP_DIR', '/absolute/path/to/app' );

require '../_nino/Nino.php';
require 'Admin.php';

// The one entry point that may boot without config.php: there is no project
// yet while the wizard runs, and writing the first one is what it is for
// (see \Nino\AppData::init()). With a project in place this is the same
// boot every other entry point does
$appData = \Nino\init( true );
\Nino\Admin\Admin::init( $appData );

$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
