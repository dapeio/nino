<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Install						Graphical, developer-only setup wizard - all \Nino\Install\* classes
 *												live in this single file, same convention as
 *												_admin/Admin.php. The workbench's first-run mode. Walks a fresh checkout through
 *												environment/permission checks, assembling starting content from
 *												_admin/install/library/ and every module's own install/ unit
 *												(locales/modules -> routes/templates/text/navigation, and
 *												the base unit's fixed look -> theme.css/fonts + the css bundle),
 *												bulk-filling the site's always-present "Personal Infos" text,
 *												creating the first workbench account(s), placing the
 *												applications of the official catalogue that were picked and,
 *												last, creating the starter pages and setting the recovery
 *												password.
 *
 *												Guarded by \Nino\Admin\Admin::isInstalled(): this only ever runs
 *												against a project that has never finished the wizard. Its own
 *												last step is what locks the wizard back out, permanently - it
 *												stores the recovery secret and marks the project
 *												installed, and either of those alone keeps the gate shut, so
 *												losing one file does not hand the installer back. Not part of
 *												the shipped admin dashboard, not linked from it. Safe (and
 *												recommended) to delete the whole folder once done: `rm -rf _admin/install`.
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

namespace Nino\Install {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Bootstraps the wizard: takes over the /_admin route, gates every request
	 *												behind Admin::isInstalled(), dispatches POST actions to a
	 *												small registry of step modules. Add a new step by writing a
	 *												class with an actions() method and listing it in MODULES -
	 *												nothing else here needs to change.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Install {

		private const array MODULES = [
			\Nino\Install\Checks::class,
			\Nino\Install\Setup::class,
			\Nino\Install\PersonalInfos::class,
			\Nino\Install\Accounts::class,
			\Nino\Install\Applications::class,
			\Nino\Install\Finish::class,
		];

		/**
		 *	Take over the /_admin route and its response callbacks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// These runtime-only routes are owned by the installer and must win
			// over a stale or hand-written config entry at the same uri. Using +=
			// here let a page route mounted at /_admin replace the wizard's
			// GET body on the very next request, leaving the still-unfinished
			// installer reachable only through manually crafted POST requests.

			// The wizard runs on the workbench's own route: Admin::init()
			// registers GET/POST /_admin and hands both to this class for as
			// long as the project is not set up (see Admin::isInstalled())
			$appData['/nino/http/routes']['GET://_admin'] = [
				'uri' 				=> '/_admin',
				'body'				=> '[template /_admin/install/templates/page-wizard]',
				'statusCode'	=> 200
			];
			$appData['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_admin', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_admin', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_admin response: the wizard while the project has
		 *	never finished the setup, else a locked-out notice -
		 *	Admin::isInstalled() answers that from '/nino/install/completed'
		 *	and the stored recovery secret
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::isInstalled( $appData ) === false )
				return;

			$request['/nino/http/response']['body'] = '[template /_admin/install/templates/page-locked]';
		}

		/**
		 *	Fill the POST /_admin response. Every wizard api request goes
		 *	through this single route, dispatched by $_POST['action'] - same
		 *	shape as Admin::handlePost(), except there is no
		 *	separate login step: the "already installed" gate below is the
		 *	only auth the wizard has, or needs - once it trips there is no
		 *	action left that should still run.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handlePost( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			// Same reading as the workbench's own dispatcher: a non-string
			// action is no action, rather than an "Illegal offset type in
			// isset" (a 500) where the 404 below is the answer
			$action = is_string( $_POST['action'] ?? null ) === true ? $_POST['action'] : '';

			$actions = [];
			foreach( self::MODULES as $module )
				$actions += $module::actions();

			if( isset( $actions[$action] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown action' );
				return;
			}

			// call_user_func() can't pass $appData/$request by reference, so dispatch via a
			// dynamic static call instead - same pattern as Callbacks::doCallbacks()
			[ $class, $method ] = $actions[$action];
			$class::{$method}( $appData, $request );
		}

		/**
		 *	Require the project to be one that has never finished the wizard -
		 *	Admin::isInstalled() again, the same gate handleGet() reads -
		 *	shared by every module action, checked once per request rather
		 *	than per action
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool										If the request may proceed
		 */
		public static function guard( array &$appData, array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			if( \Nino\Admin\Admin::isInstalled( $appData ) === true ) {
				\Nino\Http::fail( $request, 423, 'already installed' );
				return false;
			}

			return true;
		}

		/**
		 *	The json-encoded "data" POST field every wizard action reads its
		 *	payload from - the workbench's own read, not a copy of it. A copy
		 *	is what this was, and it missed the guard Admin::postData() got
		 *	when 'data[]=x' (an array where a string belongs) turned out to be
		 *	a TypeError: a 500 that the wizard, which has no authentication
		 *	until it finishes, answered to anyone
		 *
		 *	@return 	array
		 */
		public static function postData(): array {
			return \Nino\Admin\Admin::postData();
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 1: read-only environment/permission diagnostics - PHP
	 *												version, the extensions README.md documents as required, and
	 *												whether the directories Nino writes to at runtime are usable.
	 *												Nothing here is ever written to; "Recheck" just re-runs the
	 *												same probes.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Checks {

		private const string MIN_PHP_VERSION = '8.4.0';

		// extension name -> why it's needed, shown next to a failed check
		private const array EXTENSIONS = [
			'dom' 				=> 'reading and cleaning rich text (the sanitizer of html fields)',
			'gd' 				=> 'image cropping/resizing for uploaded images',
			'mbstring' 	=> 'multibyte-safe string handling (mail headers, admin exports)',
			'session' 	=> 'admin/dev login sessions',
			'xmlwriter' => 'writing xml documents (the e-invoices of a project)',
		];

		// Virtual paths, resolved per entry - the private one lands under the
		// private directory, the public one stays where the webserver reaches it
		// (see \Nino\Filesystem::path()). '' is the project root.
		//
		// A checkout carries neither private/ nor public/, so on a fresh one this
		// step is the only thing that says whether the wizard will be able to
		// create them at all - which is why it runs first and why a
		// not-yet-existing directory is judged by its parent's writability
		// (see _directories())
		private const array DIRECTORIES = [
			'',					// project root - index.php, the tool folders
			'private',	// config.php, templates, text, elements, data, assets
			'public',		// images, fonts, favicon, the generated bundles
		];

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [ 'checks/run' => [ self::class, 'apiRun' ] ];
		}

		/**
		 *	Run every environment/permission probe and return the results
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRun( array &$appData, array &$request ): void {

			\Nino\Http::ok( $request, [
				'php' 				=> [
					'version'	=> PHP_VERSION,
					'required'	=> self::MIN_PHP_VERSION,
					'ok'				=> version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' ),
				],
				'extensions' 	=> self::_extensions(),
				'directories' => self::_directories( $appData ),
			] );
		}

		/**
		 *	@return 	array		name -> [ ok, reason ]
		 */
		private static function _extensions(): array {

			$result = [];
			foreach( self::EXTENSIONS as $name => $reason )
				$result[$name] = [ 'ok' => extension_loaded( $name ), 'reason' => $reason ];

			// Checked separately: PharData (used for the workbench's automatic backups
			// and _admin's Restore) is only usable if the Phar extension itself is
			// loaded - phar.readonly does NOT block it (see _admin/Nino/Modules/Backups/Backups.php's
			// Backup class), so that ini setting is deliberately not checked here
			$result['Phar'] = [ 'ok' => class_exists( 'PharData' ), 'reason' => 'backup/restore archives (the Backups panel)' ];

			return $result;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array		relative path -> [ exists, writable, ok ]
		 */
		private static function _directories( array &$appData ): array {

			$result = [];

			foreach( self::DIRECTORIES as $rel ) {

				// Resolved, not concatenated: a private directory (templates,
				// text, elements, data) need not sit under the same root as a
				// public one - see \Nino\Filesystem::path()
				$path 	= \Nino\Filesystem::path( $appData, '/'. $rel );
				$parent = dirname( $path );
				$exists = is_dir( $path );

				// A not-yet-created directory - private/ and public/, neither of
				// which a checkout carries - is fine as long as its parent can
				// create it on first write - only an already-existing one has to
				// be writable itself
				$writable = $exists ? is_writable( $path ) : is_writable( $parent );

				$result[( $rel === '' ) ? '.' : $rel] = [
					'exists' 		=> $exists,
					'writable' 	=> $writable,
					'ok'				=> $writable,
				];
			}

			return $result;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 2: assembles the picked locales, plus every module unit,
	 *												into the project's config.php/templates/text. Navigation,
	 *												the locale picker, the contact form and the legal texts
	 *												(each their own text and/or mail templates) are no
	 *												longer a choice here - ALWAYS_MODULES lists their unit
	 *												keys, and apiApply() applies their units the same way it
	 *												applies a picked one, on every run. What the picker (apiLibrary()) still offers is any
	 *												*other* unit - a project's own module below the app dir, or
	 *												a fork below library/modules - which is why the mechanism
	 *												itself (units(), the posted 'modules' list, requiresModules)
	 *												stays exactly as it was. Pages are no step of the wizard
	 *												(StarterPages, below, creates the starter pages when it ends,
	 *												and the workbench's Routes panel does the rest) - this class
	 *												only ever touches base + modules/&lt;key&gt;. Features (Newsletter, Search, ...) are
	 *												not offered here: they come with an application in the step
	 *												after the accounts, or are switched on in the workbench's
	 *												Features panel (see \Nino\Features); one that is on stays on.
	 *
	 *												The picked locales/modules fully replace whatever was
	 *												picked before - see apiApply()'s docblock - so the picker
	 *												itself (apiLibrary()) reports each remaining module's
	 *												current active state, letting the frontend pre-check what's
	 *												already on rather than starting blank every time.
	 *
	 *												Every unit (base, modules/&lt;key&gt;) is a directory with a
	 *												manifest.php (routes/templates/blacklist/requiresModules)
	 *												plus text/global.php and/or text/&lt;locale&gt;.php fragments
	 *												merged into the real /text files - see docs/setup.md's
	 *												Library format section. The site's look is not a choice
	 *												here: the base unit delivers one fixed theme, and the
	 *												Design feature is what replaces it later.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Setup {

		// The css bundle this step seeds, and what goes into it. The theme is one
		// file the base unit delivers - the framework's tokens, the theme that
		// assigns them to roles and the header/footer rules, compiled into one -
		// and style.css is the project's own, shipped empty
		private const string BUNDLE_KEY = '/.cache/style.css';
		private const array BASE_STYLESHEETS = [ '/assets/theme.css', '/assets/style.css' ];

		// Unit keys (see units()) that used to be a picker choice and are now
		// applied on every apply, exactly like a module a project actually
		// picked - Navigation, the locale picker, the contact form and the
		// legal texts (an imprint and a privacy policy are owed by practically
		// every website, and a link to them forgotten is the dearer mistake).
		// Unit keys, not classes: each one still has to go through the
		// normal applyUnit()/moduleClass path in apiApply(), so its
		// routes/templates/text land the same way they always did
		public const array ALWAYS_MODULES = [ 'forms', 'navigation', 'localepicker', 'legal' ];

		private const string LIBRARY = __DIR__. '/library';

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'setup/library' => [ self::class, 'apiLibrary' ],
				'setup/apply' 	 => [ self::class, 'apiApply' ],
			];
		}

		/**
		 *	Locales the library ships translations for: every text/<locale>.php of
		 *	the base unit - not every locale a project could ever support. A
		 *	project wanting more adds them by hand afterward, same as any other
		 *	locale addition (see docs/development.md)
		 *
		 *	@return 	array			Locale codes, sorted
		 */
		private static function _locales(): array {

			$locales = array_values( array_filter(
				array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( self::LIBRARY. '/base/text/*.php' ) ?: [] ),
				static fn( string $name ): bool => preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $name ) === 1
			) );
			sort( $locales );

			return $locales;
		}

		/**
		 *	List everything the picker can still offer: available locales,
		 *	and every *other* module's label + auto-selected requirements -
		 *	ALWAYS_MODULES is left out, since Navigation, the locale picker
		 *	and the contact form are no longer a choice (see apiApply()) -
		 *	each remaining one also flagged with whether it's currently
		 *	active, so the picker can pre-check the real, current state
		 *	rather than starting blank every time. That matters because
		 *	apiApply() replaces the whole selection: a picker that didn't
		 *	show what's already on would make "go back and add one more
		 *	module" silently turn everything else off
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiLibrary( array &$appData, array &$request ): void {

			$activeModules = $appData['/nino/modules'] ?? [];

			/*	Whether this project has answered this step before. Its own
				answer is the only one that counts from then on - a manifest
				flag that kept re-checking a box the operator unchecked would
				be a step lying about what it controls, which is the whole
				reason the recommended set is not simply always-on in
				AppData::DEFAULTS. Read from disk rather than from $appData,
				which carries the defaults merged in underneath	*/
			$decided = array_key_exists( '/nino/modules', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) );

			$modules = [];
			foreach( self::units() as $key => $unitDir ) {

				if( in_array( $key, self::ALWAYS_MODULES, true ) === true )
					continue;

				$manifest = \Nino\Features::readUnitManifest( $unitDir ) ?? [];
				$modules[$key] = [
					'label' 					=> (string) ( $manifest['label'] ?? $key ),
					'requiresModules' => $manifest['requiresModules'] ?? [],
					// Active, or - on a project that has decided nothing yet -
					// part of the set the library recommends a site start from
					'active' 					=> ( isset( $manifest['moduleClass'] ) === true && in_array( $manifest['moduleClass'], $activeModules, true ) === true )
						|| ( $decided === false && ( $manifest['preset'] ?? false ) === true ),
				];
			}

			\Nino\Http::ok( $request, [
				'locales' 				=> self::_locales(),
				'activeLocales' 	=> $appData['/nino/locales/available'] ?? [],
				'nativeLocale' 		=> $appData['/nino/locales/native'] ?? null,
				'modules' 				=> $modules,
			] );
		}

		/**
		 *	Every module unit the wizard can offer: key => absolute unit
		 *	directory. The one place that knows where units live
		 *
		 *	A unit is a directory with a manifest.php (docs/setup.md,
		 *	"Library Format"). It travels with the module it installs: a
		 *	module ships it as install/ beside its class file, so a module
		 *	is added to a project by adding its directory, and nothing in
		 *	the wizard has to know it exists. Searched, in this order:
		 *
		 *	- _admin/install/library/modules/<key>/ - units without a module class
		 *	  of their own, or a project's fork of a module's unit
		 *	- _nino/Nino/Modules/<Module>/install/ - Nino's own optional
		 *	  modules (Form, Navigation, Localepicker)
		 *	- <app dir>/(...)/<Module>/install/ - project modules, up to four
		 *	  levels below the directory the autoloader resolves project
		 *	  classes against (app/, or NINO_APP_DIR)
		 *
		 *	Not searched: features/. A feature is activated in the Features
		 *	panel after setup, which applies its install/ unit then (see
		 *	\Nino\Features::activate()) - the wizard offers the kernel's
		 *	modules and nothing it would have to download first.
		 *
		 *	The key is the manifest's 'key' or, without one, the module
		 *	directory's lowercased name ('Navigation' -> 'navigation'); under
		 *	library/modules it is the directory name. It is a slug, because
		 *	the picker posts it back and requiresModules lists it. The first
		 *	unit to claim a key keeps it; a later one is dropped with a
		 *	warning naming both - and Nino's own modules come first: the
		 *	kernel's below _nino/, then whatever the app dir holds.
		 *
		 *	The filesystem rather than '/nino/modules', deliberately: the
		 *	wizard runs before a module is active - offering it is the whole
		 *	point - and has to see every module a checkout ships, picked or
		 *	not.
		 *
		 *	@return 	array			key => unit directory, sorted by key
		 */
		public static function units(): array {

			$units = [];

			if( is_dir( self::LIBRARY. '/modules' ) === true )
				foreach( scandir( self::LIBRARY. '/modules' ) ?: [] as $entry )
					if( $entry[0] !== '.' )
						self::_addUnit( $units, $entry, self::LIBRARY. '/modules/'. $entry );

			$appDir = defined( 'NINO_APP_DIR' ) === true ? NINO_APP_DIR : dirname( __DIR__, 2 ). '/app';

			foreach( [ dirname( __DIR__, 2 ). '/_nino/Nino/Modules', $appDir ] as $root )
				self::_scanUnits( $units, $root, 4 );

			ksort( $units );

			return $units;
		}

		/**
		 *	Walk $dir for module directories carrying install/manifest.php.
		 *	A directory that is a unit is not descended into - its templates/
		 *	and text/ hold no further modules - and nothing hidden is entered
		 *
		 *	@param		array 		&$units				(reference) key => unit directory, appended to
		 *	@param		string		$dir
		 *	@param		int				$depth				Levels left to descend
		 *
		 *	@return 	void
		 */
		private static function _scanUnits( array &$units, string $dir, int $depth ): void {

			if( is_dir( $dir ) === false )
				return;

			foreach( scandir( $dir ) ?: [] as $entry ) {

				$path = $dir. '/'. $entry;

				if( $entry[0] === '.' || is_dir( $path ) === false )
					continue;

				if( is_file( $path. '/install/manifest.php' ) === true ) {
					$manifest = include $path. '/install/manifest.php';
					if( is_array( $manifest ) === true )
						self::_addUnit( $units, (string) ( $manifest['key'] ?? strtolower( $entry ) ), $path. '/install' );
					continue;
				}

				if( $depth > 1 )
					self::_scanUnits( $units, $path, $depth - 1 );
			}
		}

		/**
		 *	@param		array 		&$units				(reference) key => unit directory
		 *	@param		string		$key
		 *	@param		string		$unitDir
		 *
		 *	@return 	void
		 */
		private static function _addUnit( array &$units, string $key, string $unitDir ): void {

			if( is_file( $unitDir. '/manifest.php' ) === false )
				return;

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $key ) !== 1 ) {
				trigger_error( 'Install unit in '. $unitDir. ' has no usable key ("'. $key. '" is not a slug)', E_USER_WARNING );
				return;
			}

			// The same unit reached twice - the delivered modules are scanned
			// ahead of the rest of the app dir they sit in - is not a shadow
			if( isset( $units[$key] ) === true ) {
				if( $units[$key] !== $unitDir )
					trigger_error( 'Install unit "'. $key. '" in '. $unitDir. ' is shadowed by '. $units[$key], E_USER_WARNING );
				return;
			}

			$units[$key] = $unitDir;
		}

		/**
		 *	Every route key base or any module could ever contribute,
		 *	picked or not. apiApply() strips all of these out of the
		 *	persisted routes before re-adding only the currently picked
		 *	modules' own. A route outside this set was never Setup's to
		 *	begin with (hand-added via _admin's Config module, or created
		 *	by the Routes panel) and is left alone
		 *
		 *	@return 	array
		 */
		private static function _libraryRouteKeys(): array {

			$keys = array_keys( \Nino\Features::readUnitManifest( self::LIBRARY. '/base' )['routes'] ?? [] );

			foreach( self::units() as $unitDir )
				$keys = array_merge( $keys, array_keys( ( \Nino\Features::readUnitManifest( $unitDir ) ?? [] )['routes'] ?? [] ) );

			return array_values( array_unique( $keys ) );
		}

		/**
		 *	Assemble the picked locales/modules into the real project:
		 *	config.php's locales/modules/routes, /templates, /elements and
		 *	/text - base first so a later module can overwrite one of its
		 *	keys (none currently do, but a project's own customized library
		 *	fork might reasonably want to). A posted native locale is
		 *	honored too, as long as it's one of this call's own picked
		 *	locales - see the native-locale block below for the fallback
		 *	when it isn't.
		 *
		 *	Locales, modules and routes are a full replace, not a merge: the
		 *	posted selection is the complete, authoritative picture, every
		 *	time this runs - unchecking a locale/module and re-applying
		 *	actually removes it, the same way any settings form works. The
		 *	one exception is ALWAYS_MODULES: Navigation, the locale picker,
		 *	the contact form and the legal texts are folded into the applied set
		 *	regardless of what was posted, so they can never be "unchecked"
		 *	away. Only routes need care doing that: by the time any wizard request
		 *	reaches here, $appData['/nino/http/routes'] already carries this
		 *	request's own runtime-only entries (the wizard's own GET/POST
		 *	route, and whichever modules are currently active also self-
		 *	register their own routes at boot, eg. POST://.form) - so routes
		 *	are rebuilt from what's actually persisted on disk, with every
		 *	route key base/modules could ever contribute stripped back out
		 *	first, before this call's own units add theirs back in. A route
		 *	a developer added by hand (outside the library entirely), the Routes
		 *	panel or the starter pages created is never one of those keys, so
		 *	it survives untouched.
		 *
		 *	Templates/element types/text are left alone either way - once
		 *	written, never removed just because a later apply() didn't pick
		 *	that unit again. Deleting a file a developer may have since
		 *	hand-edited is a different, much riskier kind of "undo" than
		 *	toggling a config array; see docs/setup.md. The elements of a
		 *	unit ('elements') are never replaced, not even by a unit this step
		 *	applies with 'overwrite' - they are content (\Nino\Elements::seed()).
		 *
		 *	A unit can also ask for a menu of its own, 'navs': [ '<key>' =>
		 *	[ '<Element-URI>', ... ] ]. This step - and only this step - adds the
		 *	key to '/nino/html/navs' and the pages to '/nino/html/navroutes' in
		 *	that order, when the project does not have the menu yet. Where it
		 *	has, the menu is the editors' and a second run leaves it alone.
		 *	\Nino\Features::activate() does not read
		 *	'navs': a feature brings no menu. See _applyNavs().
		 *
		 *	The name of each language the project has is written here too, in the
		 *	language's own tongue: [[/_nino/locale/<code>/name]] in text/global.php,
		 *	from \Nino\Locales::ENDONYMS - the code itself for a language that table
		 *	does not know - and only where the project has no name for it yet, so
		 *	that from the first run on the name is the editors'. No unit delivers
		 *	it: the text of a unit reaches every project that applies it, the
		 *	languages that project has or not.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiApply( array &$appData, array &$request ): void {

			$data 		= \Nino\Install\Install::postData();
			$locales 	= array_values( array_intersect( (array) ( $data['locales'] ?? [] ), self::_locales() ) );
			$units 		= self::units();

			// Navigation, the locale picker, the contact form and the legal
			// texts are no longer posted - they are unconditionally part of the applied
			// set, ahead of whatever else was actually picked, so their
			// units go through the exact same applyUnit()/moduleClass path
			// below as any other module a project chose
			$modules 	= array_values( array_unique( array_merge(
				array_intersect( self::ALWAYS_MODULES, array_keys( $units ) ),
				array_intersect( (array) ( $data['modules'] ?? [] ), array_keys( $units ) )
			) ) );

			if( count( $locales ) === 0 ) {
				\Nino\Http::fail( $request, 400, 'select at least one locale' );
				return;
			}

			$modules = self::_resolveModuleRequirements( $modules, $units );

			$appData['/nino/locales/available'] = $locales;

			// A posted native locale wins as long as it's actually one of
			// this call's own picked locales - a stale value (the dropdown
			// only ever offers currently-checked locales, but the request
			// itself is free-form) falls back to the same rule as no native
			// posted at all: keep the current native if it's still picked,
			// else the first picked locale
			$native = \Nino\Admin\Admin::postString( $data, 'native' );
			if( in_array( $native, $locales, true ) === true )
				$appData['/nino/locales/native'] = $native;
			elseif( in_array( $appData['/nino/locales/native'] ?? '', $locales, true ) === false )
				$appData['/nino/locales/native'] = $locales[0];

			$moduleClasses = \Nino\AppData::DEFAULTS['/nino/modules'];
			foreach( $modules as $key ) {
				$manifest = \Nino\Features::readUnitManifest( $units[$key] );
				if( isset( $manifest['moduleClass'] ) === true )
					$moduleClasses[] = $manifest['moduleClass'];
			}
			// Maintenance ships in _nino/ with every delivery and has no unit a
			// picker could offer, so it is listed on every run - see its docblock
			$moduleClasses[] = '\\Nino\\Modules\\Maintenance';

			// A feature the Applications step switched on is not a choice of this
			// step and stays on when it is applied again after a step back
			foreach( \Nino\Features::all( $appData ) as $feature )
				if( $feature['active'] === true )
					$moduleClasses[] = $feature['module'];
			$appData['/nino/modules'] = array_values( array_unique( $moduleClasses ) );

			/*	The css bundle of a fresh project, in the one order that makes it
				work: the framework first, then the look the base unit just
				delivered, then the project's own - which ships empty and is never
				touched again, so a rule put there overrules both. The stylesheets
				of features and modules are not listed here: \Nino\Html::addAsset()
				puts them in at every boot, in front of these two.

				A kernel on its own carries '/_nino/Nino.css' and nothing else
				(\Nino\AppData::DEFAULTS), which is the honest default: the two
				files after it are project files, and until this step has run there
				is no project. Anything a project added itself keeps its place -
				only the two entries this unit owns are appended, and only when
				they are not there yet.	*/
			// The persisted bundle, or the kernel's default when there is none to
			// persist yet - the same reading the routes above take, and for the
			// same reason: the runtime value holds what the features and modules
			// switched on added at this boot, and none of that belongs in config.php
			$assets = \Nino\AppData::stored( $appData, '/nino/html/assets' );
			$assets = is_array( $assets ) === true ? $assets : \Nino\AppData::DEFAULTS['/nino/html/assets'];
			$bundle = array_map( 'strval', $assets[self::BUNDLE_KEY] ?? \Nino\AppData::DEFAULTS['/nino/html/assets'][self::BUNDLE_KEY] );

			foreach( self::BASE_STYLESHEETS as $stylesheet )
				if( in_array( $stylesheet, $bundle, true ) === false )
					$bundle[] = $stylesheet;

			$assets[self::BUNDLE_KEY] = $bundle;
			$appData['/nino/html/assets'] = $assets;


			// The two roles a project starts with, written on every apply:
			// the Editor role is every content panel's permission and follows
			// the modules just picked (see \Nino\Modules\Users\Roles::defaults()), the
			// Developer role is full access. Any other role is left alone
			$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData ) + ( $appData['/nino/auth/roles'] ?? [] );

			/*	The persisted route array, never the live one: by the time any
				wizard request reaches here $appData carries this request's
				own runtime-only routes (the wizard's GET/POST pair, plus
				whatever the currently active modules self-registered at boot,
				eg. POST://.form). Reading them here would persist them into
				config.php - and would drop, in the same move, every route that
				only exists on disk, a developer's own hand-written ones among
				them. StarterPages::apply() reads it the same way.	*/
			$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', \Nino\AppData::DEFAULTS['/nino/http/routes'] );
			$routes = is_array( $routes ) === true ? $routes : \Nino\AppData::DEFAULTS['/nino/http/routes'];
			foreach( self::_libraryRouteKeys() as $routeKey )
				unset( $routes[$routeKey] );

			$blacklist = [];
			$config = [];

			/*	Read, and the first file a unit could not copy ends the step
				before config.php is written: applyUnit() answers that file, and
				a step that read no answer went on to persist locales, modules
				and routes over a project missing a template, and reported
				success. What was copied before the failure stays - the units
				overwrite here, so applying again once the target is writable
				picks up whole	*/
			$applied = \Nino\Features::applyUnit( $appData, self::LIBRARY. '/base', $locales, $routes, $blacklist, $config, true );
			if( $applied !== true ) {
				\Nino\Http::fail( $request, 500, 'the base unit could not be applied: '. $applied );
				return;
			}

			// The base unit's own image slots - the logo its frames show - added
			// below to the project's, never replacing one
			$imageSlots = [];
			$seeded = self::imageSlots( $appData, \Nino\Features::readUnitManifest( self::LIBRARY. '/base' ) ?? [], $imageSlots );
			if( $seeded !== true ) {
				\Nino\Http::fail( $request, 500, 'the base unit could not be applied: '. $seeded );
				return;
			}

			// The sentences the dashboard greets with, an element type of the
			// workbench's own, added like the elements of a unit: the project
			// has them from its first login on
			$greetings = \Nino\Modules\Dashboard\Admin::seedGreetings( $appData, $locales );
			if( $greetings !== true ) {
				\Nino\Http::fail( $request, 500, 'the base unit could not be applied: '. $greetings );
				return;
			}

			foreach( $modules as $key ) {
				$applied = \Nino\Features::applyUnit( $appData, $units[$key], $locales, $routes, $blacklist, $config, true );
				if( $applied !== true ) {
					\Nino\Http::fail( $request, 500, 'the unit "'. $key. '" could not be applied: '. $applied );
					return;
				}
			}

			// Each language the project has, named in its own tongue and added to
			// the names there are - see the docblock
			$names = [];
			foreach( $locales as $locale )
				$names['[[/_nino/locale/'. $locale. '/name]]'] = \Nino\Locales::ENDONYMS[$locale] ?? $locale;

			if( \Nino\Features::mergeText( $appData, '/text/global.php', $names, false ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write /text/global.php' );
				return;
			}

			$appData['/nino/http/routes'] = $routes;

			// Only added to, like the starter pages do: applying this step
			// again never takes a logo somebody uploaded or a size somebody
			// set away from the slot
			if( count( $imageSlots ) > 0 )
				$appData['/nino/html/images'] = ( $appData['/nino/html/images'] ?? [] ) + $imageSlots;

			$navKeys = self::_applyNavs( $appData, $modules, $units );

			// The units' config defaults go with the keys this step writes
			// anyway - applyUnit() collects them now instead of writing one
			// full config.php rewrite per unit that brings any
			\Nino\AppData::writeContentData( $appData, array_merge( [ '/nino/locales/available', '/nino/locales/native', '/nino/modules', '/nino/auth/roles', '/nino/http/routes', '/nino/html/assets' ], count( $imageSlots ) > 0 ? [ '/nino/html/images' ] : [], $navKeys, array_keys( $config ) ) );

			if( count( $blacklist ) > 0 )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( $list, $blacklist ) ) );
				} );

			\Nino\Http::ok( $request, [ 'locales' => $appData['/nino/locales/available'], 'nativeLocale' => $appData['/nino/locales/native'], 'modules' => $modules ] );
		}

		/**
		 *	The menus the applied units ask for, 'navs' in their manifests - a key
		 *	and the pages it starts with, by Element-URI: [ 'legal' => [
		 *	'/legal/imprint', '/legal/privacy' ] ]. A key the project does not have
		 *	in '/nino/html/navs' is appended to it, and the Element-URIs become its
		 *	members in '/nino/html/navroutes', priority 1, 2, ... in that order -
		 *	by Element-URI, because a page of a module has a route per language and
		 *	is one page (see \Nino\Modules\Navigation::routeLines()). A key the
		 *	project has is left alone, its entries with it: from the first run on,
		 *	the menu is the editors'.
		 *
		 *	A key has to be a slug the Navigations panel would take, an entry has to
		 *	start with '/'; anything else is skipped with a warning, which is said
		 *	here, once, and never for a page request. Only this step reads 'navs'
		 *	(see apiApply())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data - '/nino/html/navs' and '/nino/html/navroutes' are set in it
		 *	@param		array 		$modules			The applied unit keys
		 *	@param		array 		$units				units(): key => unit directory
		 *
		 *	@return 	array											The config keys that were changed, for the caller to write with its own
		 */
		private static function _applyNavs( array &$appData, array $modules, array $units ): array {

			$registry		= array_values( array_unique( array_map( 'strval', (array) ( $appData['/nino/html/navs'] ?? [] ) ) ) );
			$navroutes	= is_array( $appData['/nino/html/navroutes'] ?? null ) === true ? $appData['/nino/html/navroutes'] : [];
			$changed		= false;

			foreach( $modules as $key ) {

				foreach( (array) ( ( \Nino\Features::readUnitManifest( $units[$key] ) ?? [] )['navs'] ?? [] ) as $nav => $uris ) {

					if( is_string( $nav ) === false || preg_match( '/^[a-z][a-z0-9_-]*$/', $nav ) !== 1 || is_array( $uris ) === false ) {
						trigger_error( 'Install unit "'. $key. '" asks for a menu that is no usable key: "'. ( is_string( $nav ) === true ? $nav : '' ). '"', E_USER_WARNING );
						continue;
					}

					if( in_array( $nav, $registry, true ) === true )
						continue;

					$registry[]	= $nav;
					$changed		= true;
					$priority		= 1;

					foreach( $uris as $uri ) {

						if( is_string( $uri ) === false || str_starts_with( $uri, '/' ) === false ) {
							trigger_error( 'Install unit "'. $key. '" puts something that is no Element-URI into its menu "'. $nav. '"', E_USER_WARNING );
							continue;
						}

						$navroutes[$uri][$nav] = $priority++;
					}
				}
			}

			if( $changed === false )
				return [];

			$appData['/nino/html/navs']				= $registry;
			$appData['/nino/html/navroutes']	= $navroutes;

			return [ '/nino/html/navs', '/nino/html/navroutes' ];
		}

		/**
		 *	The image slots a unit declares, 'imageSlots' in its manifest -
		 *	uri => [ 'label' => string or locale map, 'width', 'height' and
		 *	optionally 'filename' ] - checked and collected into $into for the
		 *	caller to add to the project's slots. 'imageSlots' and not 'images':
		 *	that is the directory 'files' copies from. A slot with a 'filename'
		 *	is seeded with that file, which has to be there once the unit's files
		 *	are copied; one without is an empty slot, which renders nothing until
		 *	somebody uploads a picture. The label is one string for the project,
		 *	in its native language: a slot has a single label, not one per locale.
		 *	The first unit to name a uri is the one that counts
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			The unit's manifest
		 *	@param		array 		&$into				(reference) uri => slot, collected across units
		 *
		 *	@return 	true|string							true, or 'could not seed image slot <uri>'
		 */
		public static function imageSlots( array &$appData, array $manifest, array &$into ): true|string {

			foreach( ( $manifest['imageSlots'] ?? [] ) as $slotUri => $slot ) {

				$label 		= \Nino\Features::localized( $slot['label'] ?? '', \Nino\Locales::getNativeLocale( $appData ) );
				$width 		= (int) ( $slot['width'] ?? 0 );
				$height 	= (int) ( $slot['height'] ?? 0 );
				$filename = $slot['filename'] ?? null;

				if( is_string( $slotUri ) === false || preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)*$#', $slotUri ) !== 1
					|| $label === '' || $width < 1 || $height < 1 || $width * $height > \Nino\Images::MAX_SOURCE_PIXELS
					|| ( $filename !== null && ( is_string( $filename ) === false || $filename === '' || str_contains( $filename, '..' ) === true || str_starts_with( $filename, '/' ) === true
						|| is_file( \Nino\Filesystem::path( $appData, '/images/'. $filename ) ) === false ) ) )
					return 'could not seed image slot '. ( is_string( $slotUri ) === true ? $slotUri : '' );

				$into[$slotUri] ??= [ 'label' => $label, 'width' => $width, 'height' => $height, 'filename' => $filename ];
			}

			return true;
		}

		/**
		 *	Auto-select whatever a picked module declares via
		 *	requiresModules - other units' keys, the same slugs a page unit
		 *	names (eg. "forms") - transitively: a fixed-point loop rather
		 *	than plain recursion, since two modules could (in principle)
		 *	require each other, and the module count is small enough that
		 *	this never runs more than a couple of iterations
		 *
		 *	@param		array 		$modules			Picked module keys
		 *	@param		array 		$units				units(): key => unit directory
		 *
		 *	@return 	array			$modules, with requirements folded in
		 */
		private static function _resolveModuleRequirements( array $modules, array $units ): array {

			$changed = true;
			$unknown = [];

			while( $changed === true ) {

				$changed = false;

				foreach( $modules as $moduleKey ) {

					$manifest = \Nino\Features::readUnitManifest( $units[$moduleKey] ) ?? [];

					foreach( ( $manifest['requiresModules'] ?? [] ) as $req ) {

						if( in_array( $req, $modules, true ) === true )
							continue;

						// A requirement no unit answers to is a typo in a
						// manifest, not something to apply: say so once, skip it
						if( isset( $units[$req] ) === false ) {
							if( isset( $unknown[$req] ) === false )
								trigger_error( 'Install unit "'. $moduleKey. '" requires unknown unit "'. $req. '"', E_USER_WARNING );
							$unknown[$req] = true;
							continue;
						}

						$modules[] = $req;
						$changed = true;
					}
				}
			}

			return $modules;
		}

	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 3: bulk-fill the handful of "Personal Infos" keys every
	 *												project has regardless of what Setup picked -
	 *												/project/company/* and /project/website/general/*
	 *												(company/contact details, the site's author/hosting
	 *												info), each with a friendly label instead of its raw
	 *												key. Everything else - technical/
	 *												design-token keys (text/blacklist.php), a webpage's own
	 *												name/title/description (the Routes panel covers that), and
	 *												any deeper module/page content - is left out here on
	 *												purpose: it's fine as the library's own generic default,
	 *												and if it isn't, that's a job for the workbench's Text panel
	 *												afterward, not a one-size-fits-all bulk step.
	 *												Thin wrapper around \Nino\Text - all the validation/
	 *												sanitizing/locking already lives there (shared with
	 *												the workbench)
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class PersonalInfos {

		// _admin/install/library/base/text/*.php - the same directory Setup's
		// "base" unit reads. Duplicated as a literal path rather than
		// referencing Setup::LIBRARY (private) - keeps this class free-
		// standing, same reasoning every other class in this file
		// duplicates a small helper instead of reaching into a sibling's
		// internals
		private const string BASE_TEXT_DIR = __DIR__. '/library/base/text';

		// The workbench's own English words, which hold the vocabulary of a key's
		// segments (/_admin/common/word/<slug>). The wizard publishes no [jstext],
		// so it reads the file itself
		private const string WORDS_FILE = __DIR__. '/../text/en_US.php';

		// Only these two prefixes are in scope - see this class's docblock.
		// The technical /project/website/html/* values and the mails' look
		// are outside both
		private const array KEY_PREFIXES = [ '/project/company/', '/project/website/general/' ];

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'personalinfos/list' 			=> [ self::class, 'apiList' ],
				'personalinfos/savebatch' 	=> [ self::class, 'apiSaveBatch' ],
			];
		}

		/**
		 *	List "base"'s /project/company/* and /project/website/general/*
		 *	text keys with their current value(s) and a friendly label -
		 *	everything else (see this class's docblock, including
		 *	blacklisted/technical keys) is left out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			$keys = self::_keys();

			$entries = array_values( array_filter(
				\Nino\Text::entries( $appData, true ),
				fn( array $entry ): bool => isset( $keys[$entry['key']] )
			) );

			foreach( $entries as &$entry )
				$entry['label'] = self::_label( $entry['key'] );
			unset( $entry );

			\Nino\Http::ok( $request, [
				'entries' => $entries,
				'locales' => \Nino\Locales::getAvailableLocales( $appData ),
			] );
		}

		/**
		 *	Every key of KEY_PREFIXES base/text/global.php and
		 *	base/text/<locale>.php declare, stripped of their surrounding
		 *	[[ ]] - what apiList() filters \Nino\Text::entries() down to
		 *
		 *	@return 	array			key -> true, for isset() lookups
		 */
		private static function _keys(): array {

			$keys = [];

			foreach( glob( self::BASE_TEXT_DIR. '/*.php' ) ?: [] as $path ) {

				foreach( array_keys( include $path ) as $bracketKey ) {

					$key = trim( (string) $bracketKey, '[]' );

					foreach( self::KEY_PREFIXES as $prefix )
						if( str_starts_with( $key, $prefix ) === true )
							$keys[$key] = true;
				}
			}

			return $keys;
		}

		/**
		 *	'/project/company/contact/address' -> 'Company › Address',
		 *	'/project/website/general/url' -> 'Website › URL' - the category and
		 *	the name, each as the vocabulary of the workbench says it in English
		 *	(see _word()), no hardcoded per-key lookup table to keep in sync with
		 *	the library's own key names. English like the whole wizard
		 *
		 *	@param		string		$key
		 *
		 *	@return 	string
		 */
		private static function _label( string $key ): string {

			$segments = explode( '/', trim( $key, '/' ) );

			return self::_word( $segments[1] ?? '' ). ' › '. self::_word( $segments[3] ?? '' );
		}

		/**
		 *	One segment of a key as the English vocabulary of the workbench names
		 *	it: 'company' is "Company", 'url' is "URL". A slug the vocabulary has
		 *	no word for is capitalized with its hyphens as spaces
		 *
		 *	@param		string		$slug
		 *
		 *	@return 	string
		 */
		private static function _word( string $slug ): string {

			static $words = null;

			$words ??= is_file( self::WORDS_FILE ) === true ? (array) ( include self::WORDS_FILE ) : [];

			return (string) ( $words['[[/_admin/common/word/'. $slug. ']]'] ?? ucfirst( str_replace( '-', ' ', $slug ) ) );
		}

		/**
		 *	Save several keys' values in one request - see \Nino\Text::saveBatch()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSaveBatch( array &$appData, array &$request ): void {

			$data 	= \Nino\Install\Install::postData();
			$items 	= is_array( $data['items'] ?? null ) ? $data['items'] : [];

			\Nino\Http::ok( $request, [ 'results' => \Nino\Text::saveBatch( $appData, $items, true ) ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Accounts					Step 4: create the first workbench account(s) through
	 *												\Nino\Auth, the way \Nino\Modules\Users\Admin creates them - repeated
	 *												here rather than called, since the Users module can be
	 *												deleted on its own. The password rule is
	 *												\Nino\Admin\Recovery::MIN_PW_LENGTH, the one every password
	 *												of the workbench follows
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Accounts {

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'accounts/list' 		=> [ self::class, 'apiList' ],
				'accounts/create' 	=> [ self::class, 'apiCreate' ],
			];
		}

		/**
		 *	List the mail addresses of every currently configured admin account
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {
			\Nino\Http::ok( $request, [ 'users' => self::usableUsers( $appData ) ] );
		}

		/**
		 *	Every account that can actually pass Auth's login-status gate. A
		 *	disabled or password-less entry must not satisfy the wizard's "at
		 *	least one admin" precondition merely because an array key exists.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array		Mail addresses
		 */
		public static function usableUsers( array &$appData ): array {

			$users = [];

			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user )
				if( is_array( $user ) === true && ( $user['status'] ?? null ) === \Nino\Auth::STATUS_ACTIVE && is_string( $user['pw'] ?? null ) === true && $user['pw'] !== '' )
					$users[] = (string) $mail;

			return $users;
		}

		/**
		 *	Create (or replace) the root account: the Developer role the Setup
		 *	step wrote, or - should that step not have run - full access of
		 *	its own. Can be called more than once in the same wizard run to
		 *	set up several accounts - see docs/setup.md.
		 *
		 *	The account is signed in to this browser's session as it is
		 *	created - the one moment its password is in hand. Whether that
		 *	session is kept is decided when the wizard finishes: only the
		 *	one account of a one-account setup walks into the workbench
		 *	(see Finish::apiComplete())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			$data = \Nino\Install\Install::postData();
			$mail = trim( \Nino\Admin\Admin::postString( $data, 'mail' ) );
			$pw 	= \Nino\Admin\Admin::postString( $data, 'pw' );

			if( filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid email address' );
				return;
			}

			if( strlen( $pw ) < \Nino\Admin\Recovery::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. \Nino\Admin\Recovery::MIN_PW_LENGTH. ' characters' );
				return;
			}

			// Re-running this for the same address (eg. fixing a typo'd
			// password) has to replace, not fail on "account already exists" -
			// insertUser() alone won't, so any existing entry at this address
			// is dropped first
			if( isset( $appData['/nino/auth/user'][$mail] ) === true )
				\Nino\Auth::deleteUser( $appData, $mail );

			$role = isset( $appData['/nino/auth/roles']['developer'] ) === true ? 'developer' : '';
			\Nino\Auth::insertUser( $appData, $mail, $pw, $role === '' ? [ '/*' ] : [], $role );

			// A refusal here (an ip bucket still cooling down from before the
			// wizard) costs nothing but the sign-in: the account is created
			// either way, and the login page takes it after the wizard
			\Nino\Auth::loginUser( $appData, $mail, $pw );

			// The sign-in rotated the session's csrf token, and the wizard page
			// still carries the one it was rendered with - every request after
			// this one would be refused with it. The page is told the new one
			\Nino\Http::ok( $request, [ 'users' => self::usableUsers( $appData ), 'csrf' => \Nino\Csrf::getToken( $appData ) ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 5: applications - named sets of features from the
	 *												official catalogue, each with an entry point, which the
	 *												wizard places below features/ and switches on (see
	 *												\Nino\Catalogue and \Nino\Features::activate()). "Without an
	 *												application" is the default and leaves the project as the
	 *												steps before it made it.
	 *
	 *												The wizard has no login until it ends, so this step trusts
	 *												nothing a request names. It reads the one catalogue the
	 *												kernel ships the address and the key of (Catalogue::DEFAULT_URL,
	 *												Catalogue::PUBLIC_KEY) - and none at all where config.php
	 *												names another address or key or switches the catalogue off -
	 *												believes it only after the signature and the digest of every
	 *												archive hold, and takes from a request nothing but the keys of
	 *												applications of that index and of a feature one of them lists.
	 *												It needs an account first: an index is fetched and a package
	 *												unpacked only once somebody has proved to be able to log in.
	 *
	 *												A request applies one feature, so the page can show every one
	 *												of them as it happens and, where one fails, name it and offer
	 *												to go on from there: what is switched on stays, and a second
	 *												round skips it. Starter pages and the entry points are the
	 *												business of Finish.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Applications {

		/**
		 *	A runtime key a test or a sandbox puts the PEM public key of a catalogue it
		 *	signed itself under, the way './nino/fetch/stub' replaces the network. Like
		 *	that one it is state of the running request that no request and no way of
		 *	writing in the workbench sets; a config.php that carries it is the code of
		 *	whoever runs the project, which can replace the kernel's key as well
		 */
		public const string KEY_OVERRIDE = './nino/install/catalogue-key';

		// What a refusal says, by the code it carries: the page and the tests read the
		// code, the sentence is for a developer or a log
		private const array ERRORS = [
			'install_no_account'					=> 'create at least one account first',
			'install_not_official'				=> 'the project names another catalogue or switches it off',
			'install_missing'							=> 'loading an application needs what this server lacks',
			'install_catalogue'						=> 'the catalogue could not be read',
			'install_shape'								=> 'applications must be a list of keys',
			'install_unknown_application'	=> 'unknown application',
			'install_not_applied'					=> 'an application reported is not set up',
			'install_start_exclusive'			=> 'applications that provide the start page exclude each other',
			'install_start_applied'				=> 'an application that provides the start page is set up and stays the one',
			'install_unknown_feature'			=> 'not a feature of the chosen applications',
			'install_unresolved'					=> 'the catalogue the applications came from is not kept - open the Applications step',
			'install_feature'							=> 'the feature could not be applied',
		];

		/**
		 *	Answer a refusal with its code and sentence
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		int				$status
		 *	@param		string		$code					A key of ERRORS
		 *	@param		string		$detail				What the kernel said, put behind the sentence
		 *
		 *	@return 	void
		 */
		public static function refuse( array &$request, int $status, string $code, string $detail = '' ): void {
			\Nino\Http::fail( $request, $status, self::ERRORS[$code]. ( $detail === '' ? '' : ': '. $detail ), $code );
		}

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'applications/list'		=> [ self::class, 'apiList' ],
				'applications/plan'		=> [ self::class, 'apiPlan' ],
				'applications/apply'	=> [ self::class, 'apiApply' ],
			];
		}

		/**
		 *	What the step shows: the applications of the official catalogue that
		 *	fit this kernel - key, name, description, the features each one
		 *	switches on and whether it provides the start page ('start') - or why
		 *	there are none: a sentence, whether asking again can help ('retry'),
		 *	and what the load needs that this server lacks ('missing')
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( self::_hasAccount( $appData, $request ) === false )
				return;

			$answer = [ 'applications' => [], 'missing' => [], 'message' => '', 'retry' => false, 'reason' => '', 'offline' => false, 'startApplied' => null ];

			if( self::_official( $appData ) === false ) {
				$answer['reason']		= 'install_not_official';
				$answer['message'] = 'This project names another catalogue or switches the catalogue off, so the wizard offers no applications.';
				\Nino\Http::ok( $request, $answer );
				return;
			}

			$missing = self::_missing();
			if( $missing !== [] ) {
				self::_offline( $appData, $answer );
				$answer['missing'] = array_map( static fn( string $key, string $text ): array => [ 'key' => $key, 'text' => $text ], array_keys( $missing ), array_values( $missing ) );
				$answer['reason'] = 'install_missing';
				$answer['message'] = 'Loading an application needs what this server lacks.';
				$answer['retry'] = true;
				\Nino\Http::ok( $request, $answer );
				return;
			}

			$catalogue = \Nino\Catalogue::fetch( $appData );
			if( is_string( $catalogue ) === true ) {
				self::_offline( $appData, $answer );
				$answer['reason'] = 'install_catalogue';
				$answer['message'] = 'The applications could not be loaded - '. $catalogue. '.';
				$answer['retry'] = true;
				\Nino\Http::ok( $request, $answer );
				return;
			}

			$offered = \Nino\Catalogue::applications( $appData, $catalogue );
			$answer['startApplied'] = self::_startApplied( $appData, $offered );
			$answer['applications'] = self::_describe( $appData, $catalogue, $offered );

			if( $answer['applications'] === [] )
				$answer['message'] = 'The official catalogue offers no application for this version of Nino.';

			\Nino\Http::ok( $request, $answer );
		}

		/**
		 *	The features the chosen applications switch on, in the order they are
		 *	applied: the applications in the order of the catalogue, the features of
		 *	each in the order it lists them, each feature once
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiPlan( array &$appData, array &$request ): void {

			$plan = self::_plan( $appData, $request );
			if( $plan === null )
				return;

			$locale	= \Nino\Locales::getNativeLocale( $appData );
			$offers	= \Nino\Catalogue::offers( $appData, $plan['catalogue'] );

			\Nino\Http::ok( $request, [ 'features' => array_map(
				static fn( string $key ): array => [ 'key' => $key, 'name' => \Nino\Features::localized( $offers[$key]['name'], $locale ) ],
				$plan['features']
			) ] );
		}

		/**
		 *	Place one feature of the plan below features/ where it is not, and
		 *	switch it on where it is not - with the features it requires, which
		 *	come along. A feature that is on, at the version its directory
		 *	carries, is left as it is: that is what makes a second round after a
		 *	failure go on from there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiApply( array &$appData, array &$request ): void {

			$plan = self::_plan( $appData, $request );
			if( $plan === null )
				return;

			$key = \Nino\Admin\Admin::postString( \Nino\Install\Install::postData(), 'feature' );

			// Only a feature one of the chosen applications lists - not a key a
			// request made up, and not one of another application
			if( in_array( $key, $plan['features'], true ) === false ) {
				self::refuse( $request, 400, 'install_unknown_feature' );
				return;
			}

			$offer	= \Nino\Catalogue::offers( $appData, $plan['catalogue'] )[$key];
			$placed	= false;

			if( \Nino\Features::get( $appData, $key ) === null ) {

				$result = \Nino\Catalogue::install( $appData, $key, $offer['version'] );
				if( $result !== true ) {
					self::refuse( $request, 400, 'install_feature', $result );
					return;
				}

				$placed = true;
			}

			$feature = \Nino\Features::get( $appData, $key );
			if( $feature === null ) {
				\Nino\Http::fail( $request, 500, 'feature "'. $key. '" is not there after it was placed' );
				return;
			}

			$activated = false;

			if( self::_isOn( $feature ) === false ) {

				$result = \Nino\Features::activate( $appData, $key );
				if( $result !== true ) {
					self::refuse( $request, 400, 'install_feature', $result );
					return;
				}

				$activated = true;
			}

			\Nino\Http::ok( $request, [ 'feature' => $key, 'placed' => $placed, 'activated' => $activated ] );
		}

		/**
		 *	Of the keys a request names, the applications that are in: offered by the
		 *	official catalogue this kernel is served and every feature of which is
		 *	switched on. The catalogue is the one the list step fetched and kept - the
		 *	end of the wizard asks nobody for the network. An application with a
		 *	feature that is not on is not in, whatever the page believes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$keys					Application keys
		 *
		 *	@return 	array|string						The applications as the catalogue lists them, or the code of why not
		 */
		public static function applied( array &$appData, array $keys ): array|string {

			$catalogue = null;

			if( self::_official( $appData ) === true )
				$catalogue = \Nino\Catalogue::cached( $appData );

			// Nothing to believe and nothing in the way: no application is a plan of its own
			if( $keys === [] && $catalogue === null )
				return [];

			if( self::_official( $appData ) === false )
				return 'install_not_official';

			if( $catalogue === null )
				return 'install_unresolved';

			$offered	= [];
			foreach( \Nino\Catalogue::applications( $appData, $catalogue ) as $application )
				$offered[ $application['key'] ] = $application;

			foreach( $keys as $key )
				if( isset( $offered[$key] ) === false )
					return 'install_unknown_application';

			if( self::_startConflict( $appData, $offered, $keys ) === true )
				return 'install_start_applied';

			// In the order of the catalogue, whatever order was posted
			$applied = [];

			foreach( $offered as $key => $application ) {

				if( in_array( $key, $keys, true ) === false )
					continue;

				// Only what is really in is taken: a key reported for an application that is not is no more to be believed than one nobody lists
				if( self::_isApplied( $appData, $application ) === false )
					return 'install_not_applied';

				$applied[] = $application;
			}

			return $applied;
		}

		/**
		 *	The places the wizard leads to once it is done: the entry point of each
		 *	applied application that has one, in the order given
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$applied			Applications as applied() answers them
		 *
		 *	@return 	array										[ { key, name, next } ]
		 */
		public static function targets( array &$appData, array $applied ): array {

			$locale		= \Nino\Locales::getNativeLocale( $appData );
			$targets	= [];

			foreach( $applied as $application )
				if( is_string( $application['next'] ) === true && \Nino\Http::isSitePath( $application['next'] ) === true )
					$targets[] = [ 'key' => $application['key'], 'name' => \Nino\Features::localized( $application['name'], $locale ), 'next' => $application['next'] ];

			return $targets;
		}

		/**
		 *	Everything a request that changes the project has to hold before it
		 *	does: an account, the official catalogue, what loading needs and the
		 *	signed index - and the applications named, which have to be of it and
		 *	which may have no more than one that provides the start page
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	array|null							{ catalogue, features }, null where the request was answered
		 */
		private static function _plan( array &$appData, array &$request ): ?array {

			if( self::_hasAccount( $appData, $request ) === false )
				return null;

			if( self::_official( $appData ) === false ) {
				self::refuse( $request, 409, 'install_not_official' );
				return null;
			}

			$missing = self::_missing();
			if( $missing !== [] ) {
				self::refuse( $request, 409, 'install_missing', implode( ', ', array_values( $missing ) ) );
				return null;
			}

			$catalogue = \Nino\Catalogue::fetch( $appData );
			if( is_string( $catalogue ) === true ) {
				self::refuse( $request, 400, 'install_catalogue', $catalogue );
				return null;
			}

			$posted = \Nino\Install\Install::postData()['applications'] ?? [];
			if( is_array( $posted ) === false || array_is_list( $posted ) === false ) {
				self::refuse( $request, 400, 'install_shape' );
				return null;
			}

			$offered	= [];
			foreach( \Nino\Catalogue::applications( $appData, $catalogue ) as $application )
				$offered[ $application['key'] ] = $application;

			foreach( $posted as $key )
				if( is_string( $key ) === false || isset( $offered[$key] ) === false ) {
					self::refuse( $request, 400, 'install_unknown_application' );
					return null;
				}

			// In the order of the catalogue, whatever order or repetition was posted
			$chosen = array_values( array_filter( $offered, static fn( array $application ): bool => in_array( $application['key'], $posted, true ) ) );

			if( count( array_filter( $chosen, static fn( array $application ): bool => $application['start'] !== null ) ) > 1 ) {
				self::refuse( $request, 400, 'install_start_exclusive' );
				return null;
			}

			if( self::_startConflict( $appData, $offered, array_map( static fn( array $application ): string => $application['key'], $chosen ) ) === true ) {
				self::refuse( $request, 409, 'install_start_applied' );
				return null;
			}

			$features = [];
			foreach( $chosen as $application )
				foreach( $application['features'] as $feature )
					if( in_array( $feature, $features, true ) === false )
						$features[] = $feature;

			return [ 'catalogue' => $catalogue, 'features' => $features ];
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$application	One application of the catalogue
		 *
		 *	@return 	bool										Whether every feature of it is on
		 */
		private static function _isApplied( array &$appData, array $application ): bool {

			foreach( $application['features'] as $feature )
				if( self::_isOn( \Nino\Features::get( $appData, $feature ) ) === false )
					return false;

			return true;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$offered			The applications on offer
		 *
		 *	@return 	string|null							The key of the application that provides the start page and is set up, if one is
		 */
		private static function _startApplied( array &$appData, array $offered ): ?string {

			$set = null;

			// An application is on as soon as its features are, which a larger one applied
			// brings about for a smaller: the larger is the one that was chosen
			foreach( $offered as $application )
				if( $application['start'] !== null && self::_isApplied( $appData, $application ) === true && ( $set === null || count( $application['features'] ) > count( $set['features'] ) ) )
					$set = $application;

			return $set === null ? null : $set['key'];
		}

		/**
		 *	Whether the choice is another start page than the one that is set up: an
		 *	application that provides it stays the one once its features are on, so a
		 *	choice without it, or with another, is one the project cannot take
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$offered			key => application
		 *	@param		array 		$keys					The keys chosen
		 *
		 *	@return 	bool
		 */
		private static function _startConflict( array &$appData, array $offered, array $keys ): bool {

			$set = self::_startApplied( $appData, array_values( $offered ) );

			if( $set === null )
				return false;

			$chosen = array_values( array_filter( $keys, static fn( string $key ): bool => ( $offered[$key]['start'] ?? null ) !== null ) );

			if( $chosen === [ $set ] )
				return false;

			// While a larger one is being applied a smaller is on as soon as its features are:
			// that is the way to the choice, not another choice
			return count( $chosen ) !== 1 || array_diff( $offered[$set]['features'], $offered[ $chosen[0] ]['features'] ) !== [];
		}

		/**
		 *	The applications as the step shows them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue
		 *	@param		array 		$offered			The applications on offer
		 *
		 *	@return 	array										[ { key, applied, entry, name, description, features, start } ]
		 */
		private static function _describe( array &$appData, array $catalogue, array $offered ): array {

			$locale	= \Nino\Locales::getNativeLocale( $appData );
			$offers	= \Nino\Catalogue::offers( $appData, $catalogue );
			$list		= [];

			foreach( $offered as $application )
				$list[] = [
					'key'					=> $application['key'],
					'applied'			=> self::_isApplied( $appData, $application ),
					'entry'				=> is_string( $application['next'] ) === true && \Nino\Http::isSitePath( $application['next'] ) === true,
					'name'				=> \Nino\Features::localized( $application['name'], $locale ),
					'description'	=> \Nino\Features::localized( $application['description'], $locale ),
					'features'		=> array_map( static fn( string $key ): array => [ 'key' => $key, 'name' => \Nino\Features::localized( $offers[$key]['name'], $locale ) ], $application['features'] ),
					'start'				=> $application['start'],
				];

			return $list;
		}

		/**
		 *	What is set up is known without the network: where the catalogue cannot be
		 *	reached the answer carries the applications of the catalogue that was kept,
		 *	when one of them is set up, with the start choice they fix and 'offline'
		 *	- so that a step back or a new load shows what is taken and nothing else is
		 *	offered. Without a kept catalogue, or without an application set up, the
		 *	answer is as it was
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$answer			(reference) The answer of the list
		 *
		 *	@return 	void
		 */
		private static function _offline( array &$appData, array &$answer ): void {

			$catalogue = \Nino\Catalogue::cached( $appData );
			if( $catalogue === null )
				return;

			$offered = \Nino\Catalogue::applications( $appData, $catalogue );

			foreach( $offered as $application )
				if( self::_isApplied( $appData, $application ) === true ) {
					$answer['applications']		= self::_describe( $appData, $catalogue, $offered );
					$answer['startApplied']		= self::_startApplied( $appData, $offered );
					$answer['offline']				= true;
					return;
				}
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool										Whether an account exists - answered with a 409 where not
		 */
		private static function _hasAccount( array &$appData, array &$request ): bool {

			if( Accounts::usableUsers( $appData ) !== [] )
				return true;

			self::refuse( $request, 409, 'install_no_account' );
			return false;
		}

		/**
		 *	Whether this project reads the official catalogue: the address and the
		 *	key it holds are the kernel's own. When it does, this request reads that
		 *	catalogue - with the key a test replaced it by, if one did (KEY_OVERRIDE)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		private static function _official( array &$appData ): bool {

			$official	= $appData[ self::KEY_OVERRIDE ] ?? null;
			$official	= is_string( $official ) === true && trim( $official ) !== '' ? trim( $official ) : trim( \Nino\Catalogue::PUBLIC_KEY );
			$key			= trim( (string) ( $appData['/nino/catalogue/key'] ?? '' ) );

			if( \Nino\Catalogue::url( $appData ) !== \Nino\Catalogue::DEFAULT_URL || ( $key !== '' && $key !== $official ) )
				return false;

			$appData['/nino/catalogue/key'] = $official;

			return true;
		}

		/**
		 *	What loading an application needs and this server lacks
		 *
		 *	@return 	array										key => phrase, [] when nothing is missing
		 */
		private static function _missing(): array {

			$missing = [];

			if( function_exists( 'openssl_verify' ) === false )
				$missing['openssl'] = 'the openssl extension, which checks the signature of the catalogue';

			if( class_exists( 'PharData' ) === false || extension_loaded( 'zlib' ) === false )
				$missing['unpack'] = 'the Phar and zlib extensions, which unpack what is downloaded';

			if( function_exists( 'curl_init' ) === false && filter_var( ini_get( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN ) === false )
				$missing['http'] = 'the curl extension or allow_url_fopen, which download the catalogue';

			if( \Nino\Catalogue::writable() === false )
				$missing['features'] = 'a features/ directory the web server can write to';

			return $missing;
		}

		/**
		 *	@param		array|null	$feature			A feature as Features::get() answers it
		 *
		 *	@return 	bool											Whether it is on at the version its directory carries, with nothing against it
		 */
		private static function _isOn( ?array $feature ): bool {

			return $feature !== null && $feature['active'] === true && $feature['problems'] === [] && $feature['installed'] === $feature['version'];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						The starter pages: what a site without a page of its own starts
	 *												from - Start, Contact and the 404 page, the page units of
	 *												_admin/install/library/pages whose manifest claims a
	 *												'preset' position - created by Finish::apiComplete() when
	 *												the wizard is about to end. There is no step for the pages: a
	 *												project builds the rest in the workbench's Routes panel, which
	 *												reads and writes the very same routes and /_nino/webpage&lt;uri&gt;/*
	 *												text keys.
	 *
	 *												Nothing here asks anybody. A project that already has a page
	 *												route keeps it and gets no starter page; one that has none gets
	 *												the units in the order of their 'preset', each at the Http-URI
	 *												its 'suggest' names and the Element-URI '/&lt;folder&gt;', with the
	 *												wording the unit suggests in every active language and, where
	 *												the Navigation module is on, the menus its route proposes - the
	 *												page's position in the list is its priority there.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class StarterPages {

		private const string LIBRARY = __DIR__. '/library';

		/**
		 *	Create the starter pages, unless the project has a page route already.
		 *	Routes, templates, files, image slots, element types, text and the
		 *	blacklist of each unit are written the way the Setup step writes a
		 *	unit of a module; the first thing that cannot be written ends the call
		 *	with its name, before the routes are persisted, so the caller can say
		 *	so and the next call starts over.
		 *
		 *	Two things are done whatever the project has: the empty page template
		 *	(templates/page-blank.tpl, the one the Routes panel starts a new route
		 *	on) is put in place - the template alone, never over a file there - and,
		 *	where an application provides the start page itself ($own), the 404 page
		 *	is the only page made: the application brings no page for an address that
		 *	is not there. That one only where no route answers with that status yet.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$own					Whether an applied application provides the start page
		 *
		 *	@return 	true|string							true, or why not
		 */
		public static function apply( array &$appData, bool $own = false ): true|string {

			\Nino\Filesystem::forceDir( $appData, '/templates' );
			if( \Nino\Features::copyFile( self::LIBRARY. '/pages/blank/templates/page-blank.tpl', \Nino\Filesystem::path( $appData, '/templates/page-blank.tpl' ), false ) === false )
				return 'could not copy /templates/page-blank.tpl';

			// The persisted routes, never the live ones, which carry this request's
			// own runtime-only routes - the wizard's, and those of the active modules
			$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$routes = is_array( $routes ) === true ? $routes : [];

			if( $own === true ) {

				foreach( $routes as $route )
					if( is_array( $route ) === true && (int) ( $route['statusCode'] ?? 200 ) === 404 )
						return true;
			}
			else
				foreach( $routes as $routeKey => $route )
					if( is_string( $routeKey ) === true && is_array( $route ) === true && self::_isPageRoute( $routeKey, $route ) === true )
						return true;

			$locales	= $appData['/nino/locales/available'] ?? [];
			$navKeys	= in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === true
				? array_values( array_unique( array_map( 'strval', $appData['/nino/html/navs'] ?? [] ) ) )
				: [];

			$blacklist	= [];
			$imageSlots	= [];
			$position		= 0;

			foreach( self::_units( $own ) as $unitKey ) {

				$applied = self::_applyPage( $appData, $unitKey, ++$position, $locales, $navKeys, $routes, $blacklist, $imageSlots );
				if( $applied !== true )
					return 'the page "/'. $unitKey. '" could not be applied: '. $applied;
			}

			// The 404 page links to the start page by name. Where the application brings the
			// start page itself there is no page of the starter set to write that address, so it
			// is the address of the site, left as it is where something wrote it already
			if( $own === true ) {

				if( \Nino\Features::mergeText( $appData, '/text/global.php', [ '[[/_nino/webpage/home/uri]]' => '/' ], false ) === false )
					return 'could not write /text/global.php';
				$blacklist[] = '/_nino/webpage/home/uri';
			}

			$appData['/nino/http/routes'] = $routes;

			// Only added to: a slot the project already has is not touched
			if( count( $imageSlots ) > 0 )
				$appData['/nino/html/images'] = ( $appData['/nino/html/images'] ?? [] ) + $imageSlots;

			\Nino\AppData::writeContentData( $appData, array_merge( [ '/nino/http/routes' ], count( $imageSlots ) > 0 ? [ '/nino/html/images' ] : [] ) );

			if( count( $blacklist ) > 0 )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( $list, $blacklist ) ) );
				} );

			return true;
		}

		/**
		 *	A page is a GET route rendering a templates/page-*.tpl file - the
		 *	criterion the Routes panel lists pages by, and the one thing that
		 *	keeps robots.txt and the sitemap out of a page list
		 *
		 *	@param		string		$routeKey			Eg. 'GET://contact'
		 *	@param		array 		$route
		 *
		 *	@return 	bool
		 */
		private static function _isPageRoute( string $routeKey, array $route ): bool {

			return str_starts_with( $routeKey, 'GET://' ) === true
				&& preg_match( '~^\[template /templates/page-~', trim( (string) ( $route['body'] ?? '' ) ) ) === 1;
		}

		/**
		 *	The page units that claim a 'preset' position, in the order of that
		 *	position - not by folder name: the order is the order of the menus
		 *
		 *	@return 	array										Unit keys, in order
		 */
		private static function _units( bool $only404 = false ): array {

			$units = [];

			foreach( scandir( self::LIBRARY. '/pages' ) ?: [] as $entry ) {

				if( str_starts_with( $entry, '.' ) === true )
					continue;

				$manifest = \Nino\Features::readUnitManifest( self::LIBRARY. '/pages/'. $entry );

				if( $manifest === null || isset( $manifest['preset'] ) === false )
					continue;

				// Only the page that answers for an address that is not there
				if( $only404 === true && (int) ( array_values( $manifest['routes'] ?? [] )[0]['statusCode'] ?? 200 ) !== 404 )
					continue;

				$units[$entry] = (int) $manifest['preset'];
			}

			asort( $units );

			return array_map( 'strval', array_keys( $units ) );
		}

		/**
		 *	Create one starter page: its route, the unit's files and text, and the
		 *	page's own meta - the name, title and description of every active
		 *	language, and its Http-URI as a fill a template can link to by name
		 *	(blacklisted: a technical value, not wording anybody edits)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitKey			The folder of the unit below library/pages
		 *	@param		int				$position			The page's place in the list, its priority in every menu
		 *	@param		array 		$locales			The active locales
		 *	@param		array 		$navKeys			The menus this project registers
		 *	@param		array 		&$routes			(reference) The persisted routes, collected
		 *	@param		array 		&$blacklist		(reference) Collected blacklist keys
		 *	@param		array 		&$imageSlots	(reference) Collected image slots, the first unit to name a uri winning
		 *
		 *	@return 	true|string							true, or the first file that could not be copied or written
		 */
		private static function _applyPage( array &$appData, string $unitKey, int $position, array $locales, array $navKeys, array &$routes, array &$blacklist, array &$imageSlots ): true|string {

			$unitDir	= self::LIBRARY. '/pages/'. $unitKey;
			$manifest	= \Nino\Features::readUnitManifest( $unitDir ) ?? [];
			$suggest	= is_array( $manifest['suggest'] ?? null ) === true ? $manifest['suggest'] : [];
			$route		= array_values( $manifest['routes'] ?? [] )[0] ?? null;

			// A unit gated to a language the project does not have is no page of it
			if( $route !== null && isset( $route['locale'] ) === true && in_array( $route['locale'], $locales, true ) === false )
				$route = null;

			// The Element-URI is the unit's own folder name; the Http-URI the first
			// language that names one suggests, else the Element-URI. One address
			// for every language - a route is found by its key alone
			$uri			= '/'. $unitKey;
			$httpUri	= '';
			foreach( $locales as $locale )
				if( $httpUri === '' )
					$httpUri = \Nino\Features::localized( $suggest['uri'] ?? '', $locale );
			$httpUri	= $httpUri !== '' ? $httpUri : $uri;
			$routeKey	= 'GET://'. trim( $httpUri, '/' );

			if( $route !== null ) {

				$statusCode	= (int) ( $route['statusCode'] ?? 200 );
				$unitNavs		= array_keys( (array) ( $route['navs'] ?? [] ) );

				$route['uri'] = $uri;
				unset( $route['statusCode'] );
				if( $statusCode !== 200 )
					$route['statusCode'] = $statusCode;

				// Menu membership belongs on the route: that is what
				// Modules\Navigation::routeLines() reads. The menus the unit proposes,
				// narrowed to those the project registers, in the order it registers them
				$navs = [];
				foreach( $navKeys as $navKey )
					if( in_array( $navKey, $unitNavs, true ) === true )
						$navs[$navKey] = $position;

				if( count( $navs ) > 0 )
					$route['navs'] = $navs;
				else
					unset( $route['navs'] );

				$routes[$routeKey] = $route;
			}

			if( count( $manifest['templates'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/templates' );
				foreach( $manifest['templates'] as $locale => $file ) {
					if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
						continue;
					if( \Nino\Features::copyFile( $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ) ) === false )
						return 'could not copy /templates/'. $file;
				}
			}

			// A file or a whole directory, as the unit ships it - and one the
			// manifest names but the unit does not carry is a failure
			foreach( ( $manifest['files'] ?? [] ) as $file )
				if( \Nino\Features::copyTree( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ) ) === false )
					return 'could not copy /'. $file;

			// The image slots the unit's templates show, checked once the files are in place
			$seeded = Setup::imageSlots( $appData, $manifest, $imageSlots );
			if( $seeded !== true )
				return $seeded;

			if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/elements' );
				foreach( $manifest['elementTypes'] as $file )
					if( \Nino\Features::copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/elements/'. $file ) ) === false )
						return 'could not copy /elements/'. $file;
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

			$globalFragment = $unitDir. '/text/global.php';
			if( is_file( $globalFragment ) === true && \Nino\Features::mergeText( $appData, '/text/global.php', (array) include $globalFragment ) === false )
				return 'could not write /text/global.php';

			foreach( $locales as $locale ) {
				$localeFragment = $unitDir. '/text/'. $locale. '.php';
				if( is_file( $localeFragment ) === true && \Nino\Features::mergeText( $appData, '/text/'. $locale. '.php', (array) include $localeFragment ) === false )
					return 'could not write /text/'. $locale. '.php';
			}

			if( $route !== null ) {

				if( \Nino\Features::mergeText( $appData, '/text/global.php', [ '[[/_nino/webpage'. $uri. '/uri]]' => $httpUri ] ) === false )
					return 'could not write /text/global.php';
				$blacklist[] = '/_nino/webpage'. $uri. '/uri';
			}

			// The page's own meta, from what the unit suggests - the same filter the
			// Routes panel puts these three through: a plain text, so markup is
			// stripped and a quote cannot close the attribute a template writes the
			// title or description into
			foreach( $locales as $locale ) {

				$meta = [];
				foreach( [ 'name', 'title', 'description' ] as $field )
					$meta['[[/_nino/webpage'. $uri. '/'. $field. ']]'] = \Nino\Text::sanitizeValue( \Nino\Features::localized( $suggest[$field] ?? '', $locale ), 'plain' );

				if( \Nino\Features::mergeText( $appData, '/text/'. $locale. '.php', $meta ) === false )
					return 'could not write /text/'. $locale. '.php';
			}

			return true;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 6 - the wizard's last step: create the starter pages (unless
	 *												an application provides the start page itself) and set the
	 *												recovery password. Writes it under the private directory
	 *												rather than into any tool folder (see \Nino\Admin\Recovery),
	 *												and is the one action whose success is itself what locks the
	 *												wizard back out - Admin::isInstalled() goes true the moment
	 *												this returns
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Finish {

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [ 'finish/complete' => [ self::class, 'apiComplete' ] ];
		}

		/**
		 *	Store the posted password's hash and mark this project installed.
		 *
		 *	The marker is what makes "already installed" survive the loss of
		 *	the password file: without it, deleting one file would re-open
		 *	the wizard on a live site (see Admin::isInstalled()). Written
		 *	after the hash, never before - a marker on a project that has no
		 *	password yet would lock its operator out of both areas at once.
		 *
		 *	Before anything is stored the starter pages are created
		 *	(StarterPages::apply()), unless an application that was applied
		 *	says it provides the start page ('start' => 'own'): the pages
		 *	of a site that has one already are not for the wizard to add,
		 *	and a failure there ends the call with the wizard still open.
		 *	The applications are the keys the page posts, believed only as
		 *	far as the catalogue it fetched lists them and every feature of
		 *	them is on (Applications::applied()).
		 *
		 *	Answers { login: bool, targets: [ { key, name, next } ] }: whether
		 *	this session walks into the workbench, and the entry point of each
		 *	application that has one. It walks when the wizard created exactly
		 *	one account and that is the one Accounts::apiCreate() signed in -
		 *	the person who just typed its password is the person at the
		 *	keyboard. With several accounts the session is ended instead:
		 *	which of them stays signed in is nobody's decision but the login
		 *	page's. The page decides where to: the one target there is, or the
		 *	list of them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiComplete( array &$appData, array &$request ): void {

			$data 	= \Nino\Install\Install::postData();
			$pw 		= \Nino\Admin\Admin::postString( $data, 'password' );
			$users	= \Nino\Install\Accounts::usableUsers( $appData );

			if( count( $users ) === 0 ) {
				\Nino\Http::fail( $request, 409, 'create at least one account first' );
				return;
			}

			if( strlen( $pw ) < \Nino\Admin\Recovery::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. \Nino\Admin\Recovery::MIN_PW_LENGTH. ' characters' );
				return;
			}

			$keys = $data['applications'] ?? [];
			if( is_array( $keys ) === false || array_is_list( $keys ) === false || count( array_filter( $keys, 'is_string' ) ) !== count( $keys ) ) {
				\Nino\Install\Applications::refuse( $request, 400, 'install_shape' );
				return;
			}

			$applied = \Nino\Install\Applications::applied( $appData, array_values( array_unique( $keys ) ) );
			if( is_string( $applied ) === true ) {
				\Nino\Install\Applications::refuse( $request, 409, $applied );
				return;
			}

			// An application that provides the start page leaves the starter pages out and
			// the 404 page alone: it brings no page for an address that is not there
			$own = count( array_filter( $applied, static fn( array $application ): bool => $application['start'] === 'own' ) ) > 0;

			$pages = \Nino\Install\StarterPages::apply( $appData, $own );
			if( $pages !== true ) {
				\Nino\Http::fail( $request, 500, $pages );
				return;
			}

			if( \Nino\Admin\Recovery::set( $appData, $pw ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write '. \Nino\Admin\Recovery::PASSWORD_PATH. ' under the private directory - check its file permissions' );
				return;
			}

			$appData['/nino/install/completed'] = true;

			\Nino\AppData::writeContentData( $appData, [ '/nino/install/completed' ] );

			$current	= \Nino\Auth::getCurrentUser( $appData );
			$login		= $current !== false && $users === [ $current['mail'] ];

			if( $login === false && $current !== false )
				\Nino\Auth::logoutUser( $appData );

			\Nino\Http::ok( $request, [ 'login' => $login, 'targets' => \Nino\Install\Applications::targets( $appData, $applied ) ] );
		}
	}
}
