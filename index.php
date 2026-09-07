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

// The application half - the project's own PHP classes and modules. It
// defaults to <project>/app. The Nino\ namespace always stays in _nino/,
// the optional modules Nino ships (Design, Templates, Form, Navigation,
// Localepicker) among them.
// define( 'NINO_APP_DIR', '/absolute/path/to/app' );

// The installed features - one directory each, activated in the workbench's
// Features panel (see \Nino\Features). Defaults to <project>/features and
// is replaced as a whole: a project that points it elsewhere moves the
// features along, or loses them silently.
// define( 'NINO_FEATURES_DIR', '/absolute/path/to/features' );

require '_nino/Nino.php';

// Init Nino - the contact form's POST /.form handler is \Nino\Modules\Form,
// registered like every other module via config.php's /nino/modules
$appData    = \Nino\init();

// Output Nino
$request    = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
