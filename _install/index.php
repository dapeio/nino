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

// Init Nino Install
$appData = \Nino\init();
\Nino\Install\Install::init( $appData );

// Output Nino Install
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
