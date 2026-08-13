<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

// Optional: keep config.php (password hashes, routes, module list) outside
// the webroot. Uncomment and point it at a directory the webserver does not
// serve; it must exist and be writable. By default it lives in private/.
// define( 'NINO_CONFIG_DIR', '/absolute/path/outside/webroot' );

// Optional: move the complete private tree (config, templates, text,
// elements, data and management state) somewhere else. It defaults to
// <project>/private, which ships with an Apache deny rule. Point it outside
// the webroot for a setup that does not depend on that rule; the target must
// exist and be writable.
// define( 'NINO_CONTENT_DIR', '/absolute/path/outside/webroot' );

require '_nino/Nino.php';

// Init Nino - the contact form's POST / handler is \Nino\Shortcodes\Form,
// registered like every other module via config.php's /nino/modules
$appData    = \Nino\init();

// Output Nino
$request    = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
