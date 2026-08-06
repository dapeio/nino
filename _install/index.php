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
require 'Install.php';

// Init Nino Install
$appData = \Nino\init();
\Nino\Install\Install::init( $appData );

// Output Nino Install
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
