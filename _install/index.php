<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

require '../_nino/Nino.php';
require '../_admin/Admin.php';

// The Design step. Loaded when it is there and skipped
// when it isn't: a delivery may ship without /_design, and the installer
// degrades to "no Design controls" rather than to a fatal
if( is_file( '../_design/Design.php' ) === true )
	require '../_design/Design.php';

require 'Install.php';

// Init Nino Install. The one entry point that may boot without config.php:
// there is no project yet, and writing the first one is what this is for
// (see \Nino\AppData::init()). Every other entry point 500s until Setup
// has run.
$appData = \Nino\init( true );
\Nino\Install\Install::init( $appData );

// Output Nino Install
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
