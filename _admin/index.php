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
require 'Admin.php';

// Init Nino Admin
$appData = \Nino\init();
\Nino\Admin\Admin::init( $appData );

// Output Nino Admin
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
