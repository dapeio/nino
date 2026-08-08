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
require 'Templates.php';

// Init Nino Templates - _admin is initialized too, so its own login action
// stays reachable from the login form this serves when the gate is closed
$appData = \Nino\init();
\Nino\Admin\Admin::init( $appData );
\Nino\Templates\Templates::init( $appData );

// Output Nino Templates
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
