<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	const VERSION = '1.1.0-beta';

	/**
	 *	Boot Nino.
	 *
	 *	@param		bool			$installing		True only from the workbench's entry points: the
	 *															wizard is the one caller that has to run
	 *															*before* a project exists, so it is the one
	 *															caller allowed to boot without config.php.
	 *															Every other entry point keeps the fatal.
	 *
	 *	@return 	array
	 */
	function init( bool $installing = false ): array {

		$root 		= dirname(__DIR__);

		// These paths are needed before Runtime installs Nino's error handler:
		// AppData::prepareSession() already reads config.php from them. Validate
		// them before any path concatenation can obscure the real problem.
		if( defined( 'NINO_PRIVATE_DIR' ) === true && ( is_string( NINO_PRIVATE_DIR ) === false || NINO_PRIVATE_DIR === '' || is_dir( NINO_PRIVATE_DIR ) === false || is_writable( NINO_PRIVATE_DIR ) === false ) )
			trigger_error( 'NINO_PRIVATE_DIR must point to an existing, writable directory.', E_USER_ERROR );

		if( defined( 'NINO_CONFIG_DIR' ) === true && ( is_string( NINO_CONFIG_DIR ) === false || NINO_CONFIG_DIR === '' || is_dir( NINO_CONFIG_DIR ) === false || is_writable( NINO_CONFIG_DIR ) === false ) )
			trigger_error( 'NINO_CONFIG_DIR must point to an existing, writable directory.', E_USER_ERROR );

		// Everything this project *is*, as opposed to the code that runs it:
		// configuration, templates, content and management state all live in
		// private/. NINO_PRIVATE_DIR may deliberately move that complete tree
		// outside the webroot; no alternative in-project layout is detected.
		$private = defined( 'NINO_PRIVATE_DIR' ) === true
			? NINO_PRIVATE_DIR
			: $root. '/private';

		$config = defined( 'NINO_CONFIG_DIR' ) === true
			? NINO_CONFIG_DIR
			: $private;

		// ...and the public half, the mirror of it: images, assets, fonts,
		// the favicon set and the generated asset cache. Gathered in public/
		// so the project root holds the code that runs the site and nothing
		// else. Still inside the webroot - the urls simply gain a /public
		// segment (see Filesystem::getPublicDir()) - so no deployment has to
		// change its document root
		$public = $root. '/public';

		$appData = [
			'./nino/uid'	=> $root,
			// config.php holds the password hashes and route/module wiring -
			// letting it live outside the webroot means a webserver
			// misconfiguration that serves raw .php source (the exact case
			// the go-live checklist warns about) can't leak it. Follows the
			// private root unless the site's index.php defines
			// NINO_CONFIG_DIR before requiring this file.
			'./nino/filesystem/configpath'	=> $config,
			'./nino/filesystem/contentpath'	=> $private,
			// Where the private half of a project lives - config.php, the
			// templates, the text/elements they render from, and the data
			// visitors produce (see Filesystem::PRIVATE_DIRS). Never served
			// by a webserver
			'./nino/filesystem/privatepath'	=> $private,
			// ...and where the half that *is* served lives (see
			// Filesystem::PUBLIC_DIRS)
			'./nino/filesystem/publicpath'	=> $public,
		];

		\Nino\AppData::prepare( $appData );

		// Session cookie params are fixed the moment session_start() runs and
		// can't be retrofitted afterwards, so the keys Runtime::init() needs
		// have to be known before it. The full config load still happens in
		// AppData::init() below (it needs Filesystem::init() first) - this only
		// pulls the '/nino/session/' keys forward.
		\Nino\AppData::prepareSession( $appData );

		\Nino\Runtime::init( $appData );

		\Nino\Filesystem::init( $appData );
		\Nino\AppData::init( $appData, $installing );
		\Nino\Locales::init( $appData );
		\Nino\Csrf::init( $appData );
		\Nino\Html::init( $appData );
		\Nino\Auth::init( $appData );
		\Nino\Modules::callModules( $appData, 'init' );

		return $appData;
	}

	function request( array &$appData, array $request ): array {

		\Nino\Http::request( $appData, $request );

		// Before Http::response(), because these three answer to $appData
		// alone - no resolved route, no locale, nothing the response phase
		// produces. A '/nino/http/response/...' callback that renders a
		// template is a real caller: Modules\Form and Modules\Newsletter
		// build their html mails in that window, and a fill registered after
		// it would reach them as the literal '[[/nino/public]]' inside the
		// mail's logo url. Everything that genuinely needs the request stays
		// in the second call below
		\Nino\Html::addFills( $appData, [
			'[[/nino/dir]]'										=> \Nino\Filesystem::getDir( $appData ),
			'[[/nino/public]]'								=> \Nino\Filesystem::getPublicDir( $appData ),
			'[[/date/year]]'									=> date('Y'),
		], '*' );

		\Nino\Http::response( $appData, $request );

		// After Http::response(), not before: only there has the route been
		// resolved and merged, so only there is a route's own 'locale' known
		\Nino\Locales::response( $appData, $request );

		$currentUser = \Nino\Auth::getCurrentUser( $appData );
		\Nino\Html::addFills( $appData, [
			'[[/nino/http/request/uri]]'			=> $request['/nino/http/request']['uri'],
			'[[/nino/http/response/uri]]'		=> $request['/nino/http/response']['uri'],
			'[[/nino/http/response/uri/clean]]'		=> str_replace( '/', '_', $request['/nino/http/response']['uri'] ),
			'[[/nino/http/response/locale]]'	=> $request['/nino/http/response']['locale'],
			'[[/nino/auth/user]]'						=> ( ( $currentUser !== false ) ? $currentUser['mail'] : '' ),
		], '*' );

		\Nino\Html::response( $appData, $request );

		return $request;
	}



	function output( array &$appData, array $request ): void {

		// The one point with a finished response in hand - every other hook
		// runs before Html::response() has rendered the body. A module that
		// needs the bytes that are actually about to be sent (Modules\Cache
		// stores them) has nowhere else to stand. Http::output() exits, so
		// this is also the last chance to run anything at all.
		\Nino\Callbacks::doCallbacks( $appData, '/nino/http/output', $request );

		\Nino\Http::output( $appData, $request );
	}

	// The kernel classes - AppData, Auth, Callbacks, Catalogue, Csrf,
	// Features, Fetch, Filesystem, Backup, RotatingLog, Elements, Html, Http,
	// Images, Locales, Text, Mail, Modules and Runtime - each live in their own file under
	// _nino/Nino/<Class>/<Class>.php and are autoloaded on first use by
	// the spl_autoload_register() call at the bottom of this file, from
	// the same <namespace-as-path>/<basename>.php layout every module
	// follows. Nothing here needs to be required by hand: init() below
	// touches AppData first, and the loader resolves every class after it
	// the moment it is first named.

}


namespace {

	// Autoloads \Nino\Modules\* and any project's custom module classes on
	// first use, from the same <namespace-as-path>/<basename>.php layout
	// callModules() derives a class' file from - '/nino/modules' only ever
	// controlled which modules get a method called, never which classes
	// exist, so a direct reference (tests among them) needs to resolve the
	// same way without going through callModules() first
	spl_autoload_register( function( string $className ): void {

		$relativePath = str_replace( '\\', '/', $className );

		if( preg_match( '#^[A-Za-z0-9_/]+$#', $relativePath ) !== 1 )
			return;

		$file = '/'. $relativePath. '/'. basename( $relativePath ). '.php';

		// The kernel owns Nino\ and nothing else, and a project's own classes
		// resolve against the application root alone - so _nino/ stays pure
		// code an update may replace wholesale, and a class that lands in it
		// by accident fails loudly instead of being found there.
		//
		// The one deliberate exception is Nino\Modules\, which is a merged
		// view over four roots rather than one directory: the runtime modules
		// Nino ships in _nino/ (the always-on ones and the optional ones a
		// project switches on or off in '/nino/modules'), the workbench's own
		// screens in _admin/Nino/Modules/ (a module's Admin panel, its tabs,
		// its assets and its words - see \Nino\Admin\Admin::modules()), the
		// features a project installs below features/ (one directory each,
		// with a feature.php manifest - see \Nino\Features), and the
		// application root, where a project's own code lives. The order is
		// what the roots are allowed to do to each other: _nino/ first, so a
		// shipped module can never be shadowed; _admin/ before features/, so a
		// feature cannot replace a workbench screen; features/ before app/, so
		// a project cannot replace an installed feature by dropping a file
		// next to its own modules; app/ last, which can only add.
		//
		// The same module name may hold a class in more than one root, since
		// it is the whole relative path that is resolved and not the first
		// segment: \Nino\Modules\Elements is the kernel's runtime module in
		// _nino/, \Nino\Modules\Elements\Admin the workbench panel for it
		// in _admin/ - two halves of one module, each where it belongs.
		$appRoot 			= defined( 'NINO_APP_DIR' ) === true ? NINO_APP_DIR : dirname( __DIR__ ). '/app';
		$featuresRoot	= defined( 'NINO_FEATURES_DIR' ) === true ? NINO_FEATURES_DIR : dirname( __DIR__ ). '/features';
		$adminRoot 		= dirname( __DIR__ ). '/_admin';
		$roots 	 	 		= str_starts_with( $relativePath, 'Nino/Modules/' ) === true
			? [ __DIR__, $adminRoot, $featuresRoot, $appRoot ]
			: ( str_starts_with( $relativePath, 'Nino/' ) === true ? [ __DIR__ ] : [ $appRoot ] );

		foreach( $roots as $root ) {

			// A feature is one directory named after its module - features/
			// Newsletter/Newsletter.php, features/Newsletter/Admin/Admin.php -
			// so below that root the Nino/Modules/ prefix is the directory
			// itself rather than two levels of it
			$path = ( $root === $featuresRoot ) ? $root. substr( $file, strlen( '/Nino/Modules' ) ) : $root. $file;

			if( is_file( $path ) === true ) {
				require $path;
				return;
			}
		}
	} );
}
