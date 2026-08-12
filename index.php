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
// serve; it must exist and be writable. Leave commented to keep the old
// in-webroot behaviour.
// define( 'NINO_CONFIG_DIR', '/absolute/path/outside/webroot' );

// Optional: move this project's own state (currently the _admin password
// hash, see \Nino\Filesystem::getContentPath()) somewhere else. Defaults to
// <project>/content, which ships with an Apache deny rule and whose files
// carry their own 403 stub. Point it outside the webroot for a setup that
// does not depend on either; it must exist and be writable.
// define( 'NINO_CONTENT_DIR', '/absolute/path/outside/webroot' );

require '_nino/Nino.php';

// Init Nino - the contact form's POST / handler is \Nino\Shortcodes\Form,
// registered like every other module via config.php's /nino/modules
$appData    = \Nino\init();

// Output Nino
$request    = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );