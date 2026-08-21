<?php
declare(strict_types=1);
/**
 *	Nino					A compact filesystembased php framework
 *	Design					The generated palette and scale a project is built from
 *
 *	@package			Dape/Nino
 *	@author				David Perchermeier <mail@dape.io>
 *	@link				https://github.com/dapeio/nino
 */

require '../_nino/Nino.php';
require '../_admin/Admin.php';
require 'Theme.php';

$appData = \Nino\init();
\Nino\Admin\Admin::init( $appData );
\Nino\Theme\Theme::init( $appData );

$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
