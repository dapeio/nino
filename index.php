<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

// Optional overrides for the three halves of a project. Each entry point
// boots the kernel on its own - this file, _admin/index.php and
// _admin/recovery.php - so a constant defined here is in force for the
// public site alone: define the same ones, with the same values, in the
// other two files as well. Every target must exist and be writable; an
// invalid path stops the boot rather than falling back into the project.
//
// The private half - config.php with the accounts in it, templates, text,
// elements, data and management state. It defaults to <project>/private,
// which ships with an Apache deny rule; point it outside the webroot for a
// setup that does not depend on that rule.
// define( 'NINO_PRIVATE_DIR', '/absolute/path/outside/webroot' );

// Narrower than that: config.php alone, with the rest of the private half
// staying where it is. Rarely what a deployment wants - see docs/deployment.md.
// define( 'NINO_CONFIG_DIR', '/absolute/path/outside/webroot' );

// The application half - project-owned PHP classes and the optional runtime
// modules Nino ships (Design, Templates, Form, Newsletter, Navigation,
// Localepicker, Search). It defaults to <project>/app, and it is replaced as
// a whole: a project that points it elsewhere moves those modules along, or
// loses them silently. The Nino\ namespace always stays in _nino/.
// define( 'NINO_APP_DIR', '/absolute/path/to/app' );

require '_nino/Nino.php';

// Init Nino - the contact form's POST /.form handler is \Nino\Modules\Form,
// registered like every other module via config.php's /nino/modules
$appData    = \Nino\init();

// Output Nino
$request    = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
