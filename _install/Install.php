<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Install						Graphical, developer-only setup wizard - all \Nino\Install\* classes
 *												live in this single file, same convention as _nino/Nino.php,
 *												_editor/Editor.php and _admin/Admin.php. Walks a fresh checkout through
 *												environment/permission checks, assembling starting content from
 *												_install/library/ (locales/modules/pages -> routes/templates/
 *												text/navigation, and appearance -> stylesheet/fonts + the css
 *												bundle, then separate Design/Header/Footer choices),
 *												bulk-filling the site's always-present "Personal Infos" text,
 *												creating the first _editor account(s) and finally
 *												setting the real _admin password.
 *
 *												Guarded by \Nino\Admin\Admin::isInstalled(): this only ever runs
 *												against a project that has never finished the wizard. Its own
 *												last step is what locks /_install back out, permanently - it
 *												stores the real _admin password and marks the project
 *												installed, and either of those alone keeps the gate shut, so
 *												losing one file does not hand the installer back. Not part of
 *												the shipped admin dashboard, not linked from it. Safe (and
 *												recommended) to delete the whole folder once done: `rm -rf _install`.
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

namespace Nino\Install {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Bootstraps /_install: registers the route, gates every request
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
			\Nino\Install\Themes::class,
			\Nino\Install\Webpages::class,
			\Nino\Install\PersonalInfos::class,
			\Nino\Install\Admin::class,
			\Nino\Install\Finish::class,
		];

		/**
		 *	Register the /_install route and its response callbacks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// These runtime-only routes are owned by the installer and must win
			// over a stale or hand-written config entry at the same uri. Using +=
			// here let a Webpages entry mounted at /_install replace the wizard's
			// GET body on the very next request, leaving the still-unfinished
			// installer reachable only through manually crafted POST requests.
			$appData['/nino/http/routes']['GET://_install'] = [
				'uri' 				=> '/_install',
				'body'				=> '[template /_install/templates/page-wizard]',
				'statusCode'	=> 200
			];
			$appData['/nino/http/routes']['POST://_install'] = [ 'uri' => '/_install' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_install', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_install', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_install response: the wizard if the project is still
		 *	on the shipped default _admin hash, else a locked-out notice
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::isInstalled( $appData ) === false )
				return;

			$request['/nino/http/response']['body'] = '[template /_install/templates/page-locked]';
		}

		/**
		 *	Fill the POST /_install response. Every wizard api request goes
		 *	through this single route, dispatched by $_POST['action'] - same
		 *	shape as Admin::handlePost()/Editor::handlePost(), except there is no
		 *	separate login step: the "already installed" gate below is the
		 *	only auth /_install has, or needs - once it trips there is no
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

			$action = $_POST['action'] ?? '';

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
		 *	Require the project to still be on the shipped default _admin hash -
		 *	shared by every module action, checked once per request rather
		 *	than per action
		 *
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
		 *	Decode the json-encoded "data" POST field every module action reads
		 *	its payload from - same shape as Admin::postData()/Editor::postData()
		 *
		 *	@return 	array
		 */
		public static function postData(): array {
			$data = json_decode( $_POST['data'] ?? '', true );
			return is_array( $data ) ? $data : [];
		}

		/**
		 *	Hash the posted password and store it under the private directory
		 *	(see \Nino\Admin\Admin::PASSWORD_PATH).
		 *
		 *	Earlier iterations wrote the hash into _admin/Admin.php itself.
		 *	That is deliberately no longer supported: a tool folder carrying
		 *	project state cannot be replaced on an update, and replacing it
		 *	anyway restored the shipped placeholder - which logged the
		 *	operator out and, because that placeholder is exactly what
		 *	Admin::isInstalled() reads, handed /_install back to whoever asked
		 *	for it. The hash is deliberately not in config.php either, so a
		 *	Restore cannot roll back the credential that authorises restoring
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$pw						New plaintext _admin password
		 *
		 *	@return 	bool										Whether the hash was stored
		 */
		public static function setDevPassword( array &$appData, string $pw ): bool {
			return \Nino\Admin\Admin::writePasswordHash( $appData, password_hash( $pw, PASSWORD_DEFAULT ) );
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
			'gd' 				=> 'image cropping/resizing for uploaded images',
			'mbstring' 	=> 'multibyte-safe string handling (mail headers, admin exports)',
			'session' 	=> 'admin/dev login sessions',
			'json' 			=> 'every api response and .json data file',
		];

		// path relative to the project root -> whether it's expected to
		// already exist in a fresh checkout (git-tracked) or gets created on
		// first use - see README.md's Structure section
		// Virtual paths, resolved per entry - the private ones land under the
		// private directory, the public ones stay where the webserver reaches
		// them (see \Nino\Filesystem::path())
		private const array DIRECTORIES = [
			''				=> true,	// project root - index.php, the tool folders
			'private'	=> true,	// config.php, templates, text, elements, data
			'public'	=> true,	// images, assets, fonts, favicon, the cache
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

			// Checked separately: PharData (used for _editor's automatic backups
			// and _admin's Restore) is only usable if the Phar extension itself is
			// loaded - phar.readonly does NOT block it (see _editor/Editor.php's
			// Backup class), so that ini setting is deliberately not checked here
			$result['Phar'] = [ 'ok' => class_exists( 'PharData' ), 'reason' => 'backup/restore archives (_editor/_admin)' ];

			return $result;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array		relative path -> [ exists, writable, ok ]
		 */
		private static function _directories( array &$appData ): array {

			$result = [];

			foreach( self::DIRECTORIES as $rel => $tracked ) {

				// Resolved, not concatenated: a private directory (templates,
				// text, elements, data) need not sit under the same root as a
				// public one - see \Nino\Filesystem::path()
				$path 	= \Nino\Filesystem::path( $appData, '/'. $rel );
				$parent = dirname( $path );
				$exists = is_dir( $path );

				// A not-yet-created directory (data/, .cache/) is fine as long as
				// its parent can create it on first write - only an
				// already-existing one has to be writable itself
				$writable = $exists ? is_writable( $path ) : is_writable( $parent );

				$result[( $rel === '' ) ? '.' : $rel] = [
					'exists' 		=> $exists,
					'writable' 	=> $writable,
					'tracked'		=> $tracked,
					'ok'				=> $writable,
				];
			}

			return $result;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 2: assembles locales and "modules" (navigation,
	 *												localepicker, forms, mail, newsletter - each their own
	 *												text and/or mail templates) into the project's
	 *												config.php/templates/text. Pages live in their own step
	 *												now (Webpages, below) - this class only ever touches
	 *												base + modules/&lt;key&gt;.
	 *
	 *												The picked locales/modules fully replace whatever was
	 *												picked before - see apiApply()'s docblock - so the picker
	 *												itself (apiLibrary()) reports each module's current active
	 *												state, letting the frontend pre-check what's already on
	 *												rather than starting blank every time.
	 *
	 *												Every unit (base, modules/&lt;key&gt;) is a directory with a
	 *												manifest.php (routes/templates/blacklist/requiresModules)
	 *												plus text/global.php and/or text/&lt;locale&gt;.php fragments
	 *												merged into the real /text files - see docs/_install.md's
	 *												Library format section. Themes are their own unit kind
	 *												with their own step (Themes, below) - this class never
	 *												touches one.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Setup {

		// Locales the library ships translations for - not every locale a
		// project could ever support, just the ones with real text/*.php
		// fragments in _install/library. A project wanting more adds them
		// by hand afterward, same as any other locale addition (see
		// docs/development.md)
		private const array AVAILABLE_LOCALES = [ 'de_DE', 'en_US' ];

		// Structural modules every assembled project needs regardless of
		// what's picked - Template powers every [template ...] route body,
		// Elements/Assets/Csrf/Images/Jstext have no content of their own
		// to opt in or out of. Only content-bearing modules (their own text/
		// mail templates) are selectable - see _install/library/modules/*
		private const array CORE_MODULES = [
			'\\Nino\\Modules\\Assets',
			'\\Nino\\Modules\\Elements',
			'\\Nino\\Modules\\Template',
			'\\Nino\\Modules\\Jstext',
			'\\Nino\\Modules\\Csrf',
			'\\Nino\\Modules\\Images',
			// Inert until '/nino/cache/status' is switched on in /_admin's Config -
			// present in every project so that switch has something to switch
			'\\Nino\\Modules\\Cache',
		];

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
		 *	List everything the picker can offer: available locales, and
		 *	every module's label + auto-selected requirements - each also
		 *	flagged with whether it's currently active, so the picker can
		 *	pre-check the real, current state rather than starting blank
		 *	every time. That matters because apiApply() replaces the whole
		 *	selection: a picker that didn't show what's already on would
		 *	make "go back and add one more module" silently turn everything
		 *	else off
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiLibrary( array &$appData, array &$request ): void {

			$activeModules = $appData['/nino/modules'] ?? [];

			$modules = self::_listUnits( self::LIBRARY. '/modules' );
			foreach( $modules as $key => &$unit ) {
				$manifest 			= self::_readManifest( self::LIBRARY. '/modules/'. $key );
				$unit['active'] = ( isset( $manifest['moduleClass'] ) === true && in_array( $manifest['moduleClass'], $activeModules, true ) === true ) || ( ( $manifest['active'] ?? false ) === true );
			}
			unset( $unit );

			\Nino\Http::ok( $request, [
				'locales' 				=> self::AVAILABLE_LOCALES,
				'activeLocales' 	=> $appData['/nino/locales/available'] ?? [],
				'nativeLocale' 		=> $appData['/nino/locales/native'] ?? null,
				'modules' 				=> $modules,
			] );
		}

		/**
		 *	One directory per unit, each with a manifest.php - build the
		 *	picker's key -> { label, requiresModules } map
		 *
		 *	@param		string		$dir
		 *
		 *	@return 	array
		 */
		private static function _listUnits( string $dir ): array {

			$units = [];

			foreach( scandir( $dir ) ?: [] as $entry ) {

				if( $entry === '.' || $entry === '..' )
					continue;

				$manifest = self::_readManifest( $dir. '/'. $entry );
				if( $manifest === null )
					continue;

				$units[$entry] = [
					'label' 					=> (string) ( $manifest['label'] ?? $entry ),
					'requiresModules' => $manifest['requiresModules'] ?? [],
				];
			}

			ksort( $units );

			return $units;
		}

		/**
		 *	@param		string		$unitDir
		 *
		 *	@return 	array|null							Null if $unitDir has no manifest.php
		 */
		private static function _readManifest( string $unitDir ): ?array {

			$path = $unitDir. '/manifest.php';

			return is_file( $path ) ? include $path : null;
		}

		/**
		 *	Every route key base or any module could ever contribute,
		 *	picked or not. apiApply() strips all of these out of the
		 *	persisted routes before re-adding only the currently picked
		 *	modules' own. A route outside this set was never Setup's to
		 *	begin with (hand-added via _admin's Config module, or generated
		 *	by the Webpages step) and is left alone
		 *
		 *	@return 	array
		 */
		private static function _libraryRouteKeys(): array {

			$keys = array_keys( self::_readManifest( self::LIBRARY. '/base' )['routes'] ?? [] );

			foreach( scandir( self::LIBRARY. '/modules' ) ?: [] as $entry ) {

				if( $entry === '.' || $entry === '..' )
					continue;

				$manifest = self::_readManifest( self::LIBRARY. '/modules/'. $entry );
				if( $manifest === null )
					continue;

				$keys = array_merge( $keys, array_keys( $manifest['routes'] ?? [] ) );
			}

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
		 *	actually removes it, the same way any settings form works. Only
		 *	routes need care doing that: by the time any /_install request
		 *	reaches here, $appData['/nino/http/routes'] already carries this
		 *	request's own runtime-only entries (/_install's own GET/POST
		 *	route, and whichever modules are currently active also self-
		 *	register their own routes at boot, eg. POST://.form) - so routes
		 *	are rebuilt from what's actually persisted on disk, with every
		 *	route key base/modules could ever contribute stripped back out
		 *	first, before this call's own units add theirs back in. A route
		 *	a developer added by hand (outside the library entirely) or the
		 *	Webpages step generated is never one of those keys, so it
		 *	survives untouched.
		 *
		 *	Templates/element types/text are left alone either way - once
		 *	written, never removed just because a later apply() didn't pick
		 *	that unit again. Deleting a file a developer may have since
		 *	hand-edited is a different, much riskier kind of "undo" than
		 *	toggling a config array; see docs/_install.md.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiApply( array &$appData, array &$request ): void {

			$data 		= \Nino\Install\Install::postData();
			$locales 	= array_values( array_intersect( (array) ( $data['locales'] ?? [] ), self::AVAILABLE_LOCALES ) );
			$modules 	= array_values( array_intersect( (array) ( $data['modules'] ?? [] ), array_keys( self::_listUnits( self::LIBRARY. '/modules' ) ) ) );

			if( count( $locales ) === 0 ) {
				\Nino\Http::fail( $request, 400, 'select at least one locale' );
				return;
			}

			$modules = self::_resolveModuleRequirements( $modules );

			$appData['/nino/locales/available'] = $locales;

			// A posted native locale wins as long as it's actually one of
			// this call's own picked locales - a stale value (the dropdown
			// only ever offers currently-checked locales, but the request
			// itself is free-form) falls back to the same rule as no native
			// posted at all: keep the current native if it's still picked,
			// else the first picked locale
			$native = (string) ( $data['native'] ?? '' );
			if( in_array( $native, $locales, true ) === true )
				$appData['/nino/locales/native'] = $native;
			elseif( in_array( $appData['/nino/locales/native'] ?? '', $locales, true ) === false )
				$appData['/nino/locales/native'] = $locales[0];

			$moduleClasses = self::CORE_MODULES;
			foreach( $modules as $key ) {
				$manifest = self::_readManifest( self::LIBRARY. '/modules/'. $key );
				if( isset( $manifest['moduleClass'] ) === true )
					$moduleClasses[] = $manifest['moduleClass'];
			}
			$appData['/nino/modules'] = array_values( array_unique( $moduleClasses ) );

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
			foreach( self::_libraryRouteKeys() as $routeKey )
				unset( $routes[$routeKey] );

			$blacklist = [];

			self::_applyUnit( $appData, self::LIBRARY. '/base', $locales, $routes, $blacklist );
			foreach( $modules as $key )
				self::_applyUnit( $appData, self::LIBRARY. '/modules/'. $key, $locales, $routes, $blacklist );

			$appData['/nino/http/routes'] = $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/locales/available', '/nino/locales/native', '/nino/modules', '/nino/http/routes' ] );

			if( count( $blacklist ) > 0 )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( $list, $blacklist ) ) );
				} );

			\Nino\Http::ok( $request, [ 'locales' => $appData['/nino/locales/available'], 'nativeLocale' => $appData['/nino/locales/native'], 'modules' => $modules ] );
		}

		/**
		 *	Auto-select whatever a picked module declares via
		 *	requiresModules (eg. "forms"/"newsletter" -> "mail"),
		 *	transitively - a fixed-point loop rather than plain recursion
		 *	since two modules could (in principle) require each other, and
		 *	the module count is small enough that this never runs more than
		 *	a couple of iterations
		 *
		 *	@param		array 		$modules			Picked module keys
		 *
		 *	@return 	array			$modules, with requirements folded in
		 */
		private static function _resolveModuleRequirements( array $modules ): array {

			$changed = true;

			while( $changed === true ) {

				$changed = false;

				foreach( $modules as $moduleKey ) {

					$manifest = self::_readManifest( self::LIBRARY. '/modules/'. $moduleKey ) ?? [];

					foreach( ( $manifest['requiresModules'] ?? [] ) as $req )
						if( in_array( $req, $modules, true ) === false ) {
							$modules[] = $req;
							$changed = true;
						}
				}
			}

			return $modules;
		}

		/**
		 *	Apply one unit's manifest.php: merge its routes into $routes
		 *	(skipping any locale-gated route whose locale wasn't picked),
		 *	copy its template/element-type files (same locale gating),
		 *	collect its blacklist entries, and merge its text/global.php +
		 *	text/<locale>.php fragments into the real /text files
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitDir			Absolute path to the unit (base or modules/<key>)
		 *	@param		array 		$locales			Picked locales
		 *	@param		array 		&$routes			(reference) Routes accumulator - starts from what's on
		 *																	disk (see apiApply()), never from $appData directly
		 *	@param		array 		&$blacklist		(reference) Collected blacklist keys, appended to
		 *
		 *	@return 	void
		 */
		private static function _applyUnit( array &$appData, string $unitDir, array $locales, array &$routes, array &$blacklist ): void {

			$manifest = self::_readManifest( $unitDir ) ?? [];

			foreach( ( $manifest['routes'] ?? [] ) as $routeKey => $route ) {
				if( isset( $route['locale'] ) === true && in_array( $route['locale'], $locales, true ) === false )
					continue;
				$routes[$routeKey] = $route;
			}

			if( count( $manifest['templates'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/templates' );
				foreach( $manifest['templates'] as $locale => $file ) {
					if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
						continue;
					self::_copyFile( $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ) );
				}
			}

			if( count( $manifest['files'] ?? [] ) > 0 ) {
				foreach( $manifest['files'] as $file )
					if( is_dir( $unitDir. '/'. $file ) === true )
						\Nino\Filesystem::copyDir( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ) );
					else if( is_file( $unitDir. '/'. $file ) === true )
						self::_copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ) );
			}

			if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/elements' );
				foreach( $manifest['elementTypes'] as $file )
					self::_copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/elements/'. $file ) );
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

			// A unit's own config defaults - only filled in where the project
			// has nothing yet, never overwritten: re-applying a unit must not
			// reset a value the developer has edited since
			foreach( ( $manifest['config'] ?? [] ) as $configKey => $configValue )
				if( isset( $appData[$configKey] ) === false ) {
					$appData[$configKey] = $configValue;
					\Nino\AppData::writeContentData( $appData, [ $configKey ] );
				}

			$globalFragment = $unitDir. '/text/global.php';
			if( is_file( $globalFragment ) === true )
				self::_mergeText( $appData, '/text/global.php', include $globalFragment );

			foreach( $locales as $locale ) {
				$localeFragment = $unitDir. '/text/'. $locale. '.php';
				if( is_file( $localeFragment ) === true )
					self::_mergeText( $appData, '/text/'. $locale. '.php', include $localeFragment );
			}
		}

		/**
		 *	Merge a text fragment's keys into a /text/*.php file - later
		 *	units win a key collision (see apiApply()'s docblock)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					Filesystem-relative path, eg. '/text/de_DE.php'
		 *	@param		array 		$fragment			Bracket-key => value pairs to merge in
		 *
		 *	@return 	void
		 */
		private static function _mergeText( array &$appData, string $path, array $fragment ): void {
			\Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $fragment ): array {
				return array_merge( $content, $fragment );
			} );
		}

		/**
		 *	Copy one library file into the project - plain file_get_contents/
		 *	put_contents rather than \Nino\Filesystem (which only knows how
		 *	to read/write .php files shaped as `<?php return [...];` - these
		 *	are either .tpl markup or, for elementTypes, a .php file that
		 *	has to survive the copy byte-for-byte, not round-trip through
		 *	var_export())
		 *
		 *	@param		string		$from
		 *	@param		string		$to
		 *
		 *	@return 	void
		 */
		private static function _copyFile( string $from, string $to ): void {

			$content = @file_get_contents( $from );
			if( $content === false )
				return;

			if( is_file( $to ) === true )
				@unlink( $to );

			if( is_dir( dirname( $to ) ) === false )
				@mkdir( dirname( $to ), 0755, true );

			file_put_contents( $to, $content );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Steps 3-6: pick one of library/themes/&lt;key&gt; - a
	 *												complete, self-contained look: its own stylesheet, the
	 *												webfonts that stylesheet actually references, and
	 *												whatever else its manifest lists (theme images, ...).
	 *												Applying copies those files into the project exactly
	 *												the way Setup copies a unit's 'files', and points
	 *												config.php's css bundle at the picked theme's stylesheet.
	 *
	 *												A theme's manifest.php adds three keys to the shape
	 *												every other library unit already uses: 'description'
	 *												and 'preview' (both picker-only - the preview image is
	 *												served straight out of the library and never copied
	 *												anywhere) and 'stylesheet', the project-relative path
	 *												the copied stylesheet ends up at, which is what gets
	 *												bundled. See docs/_install.md's Library format section.
	 *
	 *												The picked key is persisted at '/nino/install/theme',
	 *												the same way Webpages persists its own list: the css
	 *												bundle alone can't say which theme produced it once a
	 *												project renames or hand-edits its stylesheets, and this
	 *												step has to be able to show the current pick every time
	 *												it's revisited
	 *
	 *												The Theme apply also installs the manifest's frame and
	 *												Design defaults, so the next three steps open on the
	 *												complete look the preview promised. Design, Header and
	 *												Footer then apply independently rather than making a
	 *												frame choice recopy the theme or reset a settled Design.
	 *
	 *												Frames. The site's &lt;header&gt; and &lt;footer&gt; are
	 *												interchangeable units under library/header|footer/&lt;key&gt;,
	 *												each a template.tpl plus the style.css for the markup it
	 *												brings. The base html-header/footer templates include the
	 *												installed copy through [template /templates/theme.header]
	 *												rather than carrying the markup themselves, so a frame is
	 *												swapped by copying two files. Picks persist at
	 *												'/nino/install/header|footer'.
	 *
	 *												Design. /_design owns the --nino-* colour tokens a theme
	 *												stylesheet assigns to roles; the Design action calls
	 *												Theme::write() with either the operator's settings or the
	 *												defaults the theme's manifest declares. It is optional -
	 *												see _designAvailable() - so a delivery without /_design
	 *												installs exactly as before.
	 *
	 *												Bundle order is the whole contract, and each of the three
	 *												owns one slot in it: Nino.css, then Design's generated
	 *												values, then the theme's role assignments, then the
	 *												frames' own markup styling, then the project's overrides.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Themes {

		// Appearance units outlive the removable installer and are shared with
		// /_design. Installer-only base/module/page material remains below
		// /_install/library and is used only to construct synthetic previews.
		private const string LIBRARY = __DIR__. '/library/themes';
		private const string INSTALL_LIBRARY = __DIR__. '/library';

		// Where a theme's stylesheet gets bundled - the one entry in
		// config.php's '/nino/html/assets' this step owns (see _bundle())
		private const string BUNDLE_KEY = '/.cache/style.css';

		// A frame is the site's <header> or <footer> as an interchangeable
		// unit: library/<kind>/<key>/template.tpl plus the style.css
		// for the markup that template brings. The base html-header/footer
		// templates call the installed copy through
		// [template /templates/theme.<kind>], so swapping a frame is copying
		// two files rather than editing a page template
		private const array FRAMES = [
			'header' => [ 'template' => '/templates/theme.header.tpl', 'stylesheet' => '/assets/style.header.css' ],
			'footer' => [ 'template' => '/templates/theme.footer.tpl', 'stylesheet' => '/assets/style.footer.css' ],
		];

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'themes/list' 		=> [ self::class, 'apiList' ],
				'themes/frame' 		=> [ self::class, 'apiFrame' ],
				'themes/frame/apply' => [ self::class, 'apiFrameApply' ],
				'themes/apply' 		=> [ self::class, 'apiApply' ],
				// The Design step is a wizard step of its own (see
				// page-wizard.tpl), but the same class owns it: a theme's
				// manifest declares the design it was drawn with, and one
				// class reading that manifest is one place it can drift
				'design/read'			=> [ self::class, 'apiDesignRead' ],
				'design/preview'	=> [ self::class, 'apiPreview' ],
				'design/apply'		=> [ self::class, 'apiDesignApply' ],
			];
		}

		/**
		 *	Every theme unit with the label/description/preview its picker
		 *	tile shows, plus whichever one is currently applied so the
		 *	picker can pre-select it - same state-restoration reasoning as
		 *	Setup::apiLibrary()'s docblock
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			\Nino\Http::ok( $request, [
				'themes' 			=> self::_themes( $appData ),
				'activeTheme' => self::_currentTheme( $appData ),
				'frames' 			=> self::_frames(),
				'activeFrames'=> [
					'header' => (string) ( $appData['/nino/install/header'] ?? '' ),
					'footer' => (string) ( $appData['/nino/install/footer'] ?? '' ),
				],
			] );
		}

		/**
		 *	What the Design step opens with: the settings currently in force -
		 *	which, on a fresh install, are the ones the theme picked in the
		 *	previous step declared - plus the vocabulary its controls render
		 *	from, so the frontend never carries a second copy of the lists.
		 *
		 *	Null settings rather than an empty shape when /_design is not part
		 *	of this delivery: the step has to say so and step aside, not render
		 *	controls that would post into nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDesignRead( array &$appData, array &$request ): void {

			if( self::_designAvailable() === false ) {
				\Nino\Http::ok( $request, [ 'settings' => null, 'choices' => [], 'groups' => [] ] );
				return;
			}

			$settings = \Nino\Design\Design::settings( $appData );

			\Nino\Http::ok( $request, [
				'settings' 	=> $settings,
				'choices' 	=> \Nino\Design\Tokens::choices(),
				'groups' 		=> \Nino\Design\Tokens::GROUPS,
				// Borrowed rather than rebuilt: what a set of design settings
				// looks like is Design's question, and a second answer here
				// would be a second one to keep in step
				'example' 	=> \Nino\Design\Preview::document( $appData, $settings, self::_previewMode() ),
			] );
		}

		/**
		 *	Write the design the operator settled on. A delivery without
		 *	/_design has nothing to write and says so rather than failing - the
		 *	step is skippable, not broken
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDesignApply( array &$appData, array &$request ): void {

			if( self::_designAvailable() === false ) {
				\Nino\Http::ok( $request, [ 'settings' => null ] );
				return;
			}

			$data 		= \Nino\Install\Install::postData();
			$settings	= \Nino\Design\Tokens::normalize( is_array( $data['design'] ?? null ) ? $data['design'] : [] );

			if( \Nino\Design\Design::write( $appData, $settings ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the design stylesheet' );
				return;
			}

			\Nino\Http::ok( $request, [ 'settings' => $settings ] );
		}

		/**
		 *	One frame unit rendered as a complete, inert html document, for the
		 *	picker to show in a sandboxed iframe.
		 *
		 *	A frame is a version number in a dropdown otherwise - 'v4' says
		 *	nothing about what it looks like, and there is nothing to open in a
		 *	lightbox the way a theme has a preview image. Rendering the real
		 *	template beats shipping twelve screenshots that go stale the first
		 *	time one of them is edited.
		 *
		 *	Its own document rather than markup spliced into the wizard: a
		 *	frame's stylesheet sets things like 'body main { padding-top }' and
		 *	styles bare element selectors, which would land on the installer's
		 *	own shell. The iframe is the isolation.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiFrame( array &$appData, array &$request ): void {

			$data = \Nino\Install\Install::postData();
			$kind = (string) ( $data['kind'] ?? '' );

			if( isset( self::FRAMES[$kind] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown frame kind' );
				return;
			}

			$frame = self::_frameKey( $kind, (string) ( $data['frame'] ?? '' ), '' );

			if( $frame === null ) {
				\Nino\Http::fail( $request, 400, 'no frame units for "'. $kind. '"' );
				return;
			}

			$unitDir = dirname( self::LIBRARY ). '/'. $kind. '/'. $frame;
			$source 	= @file_get_contents( $unitDir. '/template.tpl' );

			if( $source === false ) {
				\Nino\Http::fail( $request, 500, 'could not read the frame template' );
				return;
			}

			\Nino\Http::ok( $request, [
				'frame'	=> $frame,
				'html'	=> self::_framePreviewDocument(
					self::_framePreviewMarkup( $source ),
					self::_framePreviewCss( $appData, $data, $unitDir )
				),
			] );
		}

		/**
		 *	Install the one frame selected in its dedicated wizard step. Theme
		 *	and Design are deliberately untouched: both were committed before
		 *	Header/Footer, and applying a frame must not reset either decision.
		 *
		 *	All already-active frame stylesheets are rebundled together in
		 *	FRAMES order. Applying Footer after Header would otherwise move the
		 *	footer ahead of the existing header merely because it was last.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiFrameApply( array &$appData, array &$request ): void {

			$data 	= \Nino\Install\Install::postData();
			$kind 	= (string) ( $data['kind'] ?? '' );
			$frame 	= (string) ( $data['frame'] ?? '' );

			if( isset( self::FRAMES[$kind] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown frame kind' );
				return;
			}

			if( in_array( $frame, self::_frames()[$kind] ?? [], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown '. $kind. ' frame: "'. $frame. '"' );
				return;
			}

			self::_applyFrame( $appData, $kind, $frame );

			$appData['/nino/install/'. $kind] = $frame;
			$appData['/nino/html/assets'] = self::_bundleFrames( $appData, self::_activeFrameKinds( $appData ) );

			\Nino\AppData::writeContentData( $appData, [ '/nino/install/'. $kind, '/nino/html/assets' ] );

			\Nino\Http::ok( $request, [ 'kind' => $kind, 'frame' => $frame ] );
		}

		/**
		 *	The frame's markup with everything a request would have resolved
		 *	resolved here instead: its [template] includes, the navigation
		 *	shortcode, and the textfills. Nothing is looked up in the project -
		 *	during an installation most of it does not exist yet, and a preview
		 *	that only works on the last step is no preview at all.
		 *
		 *	@param		string		$source				The unit's template.tpl
		 *
		 *	@return 	string
		 */
		private static function _framePreviewMarkup( string $source ): string {

			// The includes live in whichever unit owns them - the nav pair in
			// the navigation module, the locale picker in its own, the legal
			// link in the legal page. Resolved by name across the library
			// rather than by a hard-coded list, so a frame using a new include
			// does not need this method edited
			$source = (string) preg_replace_callback( '/\[template \/templates\/([a-z0-9-]+)\]/', static function( array $include ): string {

				foreach( glob( self::INSTALL_LIBRARY. '/*/templates/'. $include[1]. '.tpl' ) ?: [] as $path )
					return (string) file_get_contents( $path );

				foreach( glob( self::INSTALL_LIBRARY. '/*/*/templates/'. $include[1]. '.tpl' ) ?: [] as $path )
					return (string) file_get_contents( $path );

				return '';
			}, $source );

			// Fills before shortcodes, and this order is the whole reason the
			// header preview is not empty: html-header-nav.tpl carries
			// [navigation ... title="[[/company/name]]"], so a shortcode
			// pattern run first ends its argument list at the ']]' inside that
			// fill and leaves the rest of the tag on the page as text
			$source = self::_framePreviewFills( $source, false );

			// Built from the module's own markup rather than written out here,
			// so a change to the real navigation shows up in the preview
			$items = '';
			foreach( [ 'Home', 'Services', 'About', 'Contact' ] as $index => $label )
				$items .= str_replace(
					[ '[[uri]]', '[[attributes]]', '[[title]]' ],
					[ '#', $index === 0 ? ' class="nino-is-active"' : '', $label ],
					\Nino\Modules\Navigation::$html['li']
				);

			$source = (string) preg_replace_callback( '/\[navigation\b([^\]]*)\](.*?)\[\/navigation\]|\[navigation\b([^\]]*)\]/s', static function( array $nav ) use ( $items ): string {

				$arguments	= ( $nav[1] ?? '' ). ( $nav[3] ?? '' );
				$shell			= \Nino\Modules\Navigation::$html[str_contains( $arguments, 'burger' ) === true ? 'nav-burger' : 'nav-regular'];

				// The real module wraps each line of the shortcode's own
				// content in a div and puts it ahead of the list, inside the
				// same [[content]] - see doShortcode(). A preview that dropped
				// it would lose the burger's logo
				$content = '';
				foreach( array_filter( array_map( 'trim', explode( "\n", $nav[2] ?? '' ) ) ) as $line )
					$content .= str_replace( '[[content]]', $line, \Nino\Modules\Navigation::$html['div'] );

				$content .= str_replace( '[[content]]', $items, \Nino\Modules\Navigation::$html['ul'] );

				return str_replace(
					[ '[[class]]', '[[id]]', '[[content]]' ],
					[ '', 'preview-nav', $content ],
					$shell
				);
			}, $source );

			// Anything a preview could not resolve comes out empty rather than
			// as its own source code
			return self::_framePreviewFills( $source );
		}

		/**
		 *	Deterministic stand-ins for what a request would have filled in.
		 *
		 *	@param		string		$source
		 *	@param		bool			$stripShortcodes	Markup only - see the bottom of this method
		 *
		 *	@return 	string
		 */
		private static function _framePreviewFills( string $source, bool $stripShortcodes = true ): string {

			$fills = self::_framePreviewText();

			// Longest first: '[[/nino/public]]/images/logo.png' has to be
			// matched before the '[[/nino/public]]' inside it
			uksort( $fills, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

			$source = str_replace( array_keys( $fills ), array_values( $fills ), $source );

			// The page title fill nests one fill inside another, so it cannot
			// be a plain key in the map
			$source = (string) preg_replace( '/\[\[\/webpage\[\[[^\]]*\]\]\/[a-z]+\]\]/', 'Home', $source );

			// Whatever a preview could not resolve comes out empty rather than
			// as its own source code
			$source = (string) preg_replace( '/\[\[[^\]]*\]\]/', '', $source );

			if( $stripShortcodes === false )
				return $source;

			// Markup only. A stylesheet's square brackets are attribute
			// selectors, and [data-nino-mode="dark"] is what carries the whole
			// dark half of the palette - stripping it takes the theme with it
			return (string) preg_replace( '/\[\/?[a-z][a-z0-9]*\b[^\]]*\]/', '', $source );
		}

		/**
		 *	The fills a frame preview is rendered with, read from the library's
		 *	own text files rather than written out here.
		 *
		 *	This matters more than it looks: '[[/global/adress]]' is the label
		 *	"Address" and '[[/company/adress]]' is the street, and a frame that
		 *	shows both would render the street twice under a hand-written map
		 *	that guessed wrong. Reading the real files also means a frame using
		 *	a fill nobody thought of here still previews.
		 *
		 *	@return 	array			fill -> value
		 */
		private static function _framePreviewText(): array {

			$fills = [];

			// Two orderings, and both matter. Locale-independent text first,
			// then en_US, then whatever else a unit ships - the installer's own
			// interface is english, and a plain glob returns de_DE first and
			// would preview an english wizard in german. And base before the
			// units whose templates a frame includes, so a module's own labels
			// resolve the same way its markup does
			foreach( [ 'global.php', 'en_US.php', '*.php' ] as $file )
				foreach( [ '/base/text/', '/*/*/text/' ] as $directory )
					foreach( glob( self::INSTALL_LIBRARY. $directory. $file ) ?: [] as $path ) {

						if( str_ends_with( $path, 'blacklist.php' ) === true )
							continue;

						$text = include $path;

						// += keeps what is already there, so the first pass to
						// name a fill is the one that wins
						if( is_array( $text ) === true )
							$fills += array_filter( $text, static fn( mixed $value ): bool => is_string( $value ) === true );
					}

			// A neutral placeholder mark: there is no logo yet at this point,
			// a broken image icon reads as the frame's fault, and a
			// transparent pixel hides the one thing header variants differ
			// most in - where the logo sits and how much room it takes
			$blank = 'data:image/svg+xml;charset=utf-8,'
				. rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 40" role="img" aria-label="Logo">'
					. '<rect width="160" height="40" rx="6" fill="currentColor" opacity=".12"/>'
					. '<text x="80" y="25" text-anchor="middle" font-family="system-ui,sans-serif" font-size="13"'
					. ' font-weight="600" letter-spacing="2" fill="currentColor" opacity=".55">LOGO</text></svg>' );

			// What no text file can hold: paths, and the date
			return [
				'[[/nino/public]]/images/logo-invert.png'	=> $blank,
				'[[/nino/public]]/images/logo.png'				=> $blank,
				'[[/nino/dir]]'														=> '#',
				'[[/nino/public]]'												=> '#',
				'[[/date/year]]'													=> date( 'Y' ),
			] + $fills;
		}

		/**
		 *	The css a frame preview is rendered against: the framework, the
		 *	design tokens for the settings currently being previewed, the
		 *	picked theme's own stylesheet, and the frame's. Same order as the
		 *	real bundle - a preview rendered in a different order is a preview
		 *	of something else.
		 *
		 *	Read from the library rather than from the project: none of it is
		 *	installed yet at the point this step runs.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$data					The posted payload, for theme and design
		 *	@param		string		$unitDir			The frame unit's directory
		 *
		 *	@return 	string
		 */
		private static function _framePreviewCss( array &$appData, array $data, string $unitDir ): string {

			$css = (string) @file_get_contents( dirname( __DIR__ ). '/_nino/Nino.css' );

			if( self::_designAvailable() === true ) {

				$settings = is_array( $data['design'] ?? null )
					? \Nino\Design\Tokens::normalize( $data['design'] )
					: \Nino\Design\Design::settings( $appData );

				$css .= "\n". \Nino\Design\Tokens::css( $settings );
			}

			$themeManifest = self::_readManifest( (string) ( $data['theme'] ?? '' ) );

			if( $themeManifest !== null )
				foreach( glob( self::LIBRARY. '/'. (string) $data['theme']. '/assets/*.css' ) ?: [] as $themeCss )
					$css .= "\n". (string) file_get_contents( $themeCss );

			if( is_file( $unitDir. '/style.css' ) === true )
				$css .= "\n". (string) file_get_contents( $unitDir. '/style.css' );

			// The webfonts are not copied into the project until this step
			// applies, and a srcdoc iframe has an opaque origin that could not
			// fetch them anyway - so the preview shows the layout in a system
			// face rather than one CORS error per rule
			$css = (string) preg_replace( '~@font-face\s*\{[^{}]*\}~i', '', $css );

			return self::_framePreviewFills( $css, false );
		}

		/**
		 *	The document the iframe gets. Inert by construction: no script tag
		 *	is emitted, and the picker sandboxes it besides
		 *
		 *	@param		string		$markup				The resolved frame markup
		 *	@param		string		$css					The stylesheet to render it against
		 *
		 *	@return 	string
		 */
		private static function _framePreviewDocument( string $markup, string $css ): string {

			return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
				. '<meta name="viewport" content="width=device-width, initial-scale=1">'
				. '<style>'. $css. '</style>'
				// Two corrections to what the preview cannot have. The webfonts
				// are not copied yet and a srcdoc iframe could not fetch them
				// anyway, so the theme's family names would resolve to nothing
				// and the whole frame would render in the browser's serif
				// default - a system stack shows the layout instead of a
				// misleading one. And a frame is drawn against a page: without
				// something between header and footer the two collapse onto
				// each other and neither shows its real spacing
				. '<style>:root{'
				. '--fontfamily-text:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
				. '--fontfamily-title:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
				. '--fontfamily-subtitle:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
				. '.install-frame-filler{min-height:4rem}</style>'
				. '</head><body>'. $markup. '<div class="install-frame-filler"></div></body></html>';
		}

		/**
		 *	The palette a set of Design settings would produce, without
		 *	storing anything. The knobs need immediate feedback and the colour
		 *	maths lives in /_design - mirroring it in javascript would be a
		 *	second implementation to keep in step.
		 *
		 *	/_design has an endpoint of its own for exactly this, but it is
		 *	behind the shared /_admin session; during an installation there is
		 *	no admin password yet, so this step cannot borrow it and asks
		 *	Design directly instead
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiPreview( array &$appData, array &$request ): void {

			if( self::_designAvailable() === false ) {
				\Nino\Http::fail( $request, 400, 'no design engine in this delivery' );
				return;
			}

			$data 		= \Nino\Install\Install::postData();
			$settings	= \Nino\Design\Tokens::normalize( is_array( $data['design'] ?? null ) ? $data['design'] : [] );

			\Nino\Http::ok( $request, [
				'settings' 	=> $settings,
				'raster' 		=> \Nino\Design\Tokens::raster( $settings ),
				'brand' 		=> \Nino\Design\Tokens::brand( $settings ),
				'example' 	=> \Nino\Design\Preview::document( $appData, $settings, self::_previewMode() ),
			] );
		}

		/**
		 *	Which mode the example is being asked for. Not a design setting -
		 *	it is which half of a design that always has both halves is on
		 *	screen - so it travels beside the settings rather than inside them,
		 *	where it would end up written to the config.
		 *
		 *	@return 	string								'light' or 'dark'
		 */
		private static function _previewMode(): string {
			return ( $_POST['mode'] ?? '' ) === 'dark' ? 'dark' : 'light';
		}

		/**
		 *	Copy the picked theme's files into the project and bundle its
		 *	stylesheet. Unlike Setup/Webpages there is nothing to "replace"
		 *	here - exactly one theme is active at a time, so applying a
		 *	different one simply overwrites the previous one's files
		 *	(same names, same places) and swaps the bundled stylesheet.
		 *	A file the previous theme owned that the new one doesn't ship
		 *	(eg. a font no longer used) is left behind rather than deleted,
		 *	the same additive rule Setup's templates/text follow
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiApply( array &$appData, array &$request ): void {

			$data = \Nino\Install\Install::postData();
			$key 	= (string) ( $data['theme'] ?? '' );

			$manifest = self::_readManifest( $key );

			if( $manifest === null ) {
				\Nino\Http::fail( $request, 400, 'unknown theme: "'. $key. '"' );
				return;
			}

			$unitDir = self::LIBRARY. '/'. $key;

			// Each declared file lands wherever this project keeps that kind
			// of file - a unit's templates/ is private, its assets/ public
			foreach( ( $manifest['files'] ?? [] ) as $file ) {

				$target = \Nino\Filesystem::path( $appData, '/'. $file );

				if( is_dir( $unitDir. '/'. $file ) === true )
					\Nino\Filesystem::copyDir( $unitDir. '/'. $file, $target );
				else if( is_file( $unitDir. '/'. $file ) === true )
					self::_copyFile( $unitDir. '/'. $file, $target );
			}

			$appData['/nino/install/theme'] = $key;
			$appData['/nino/html/assets'] 	= self::_bundle( $appData, (string) $manifest['stylesheet'] );

			// Establish the theme's frame defaults now, so Design and both
			// dedicated frame steps start from the complete look its preview
			// promised. The later Header/Footer posts replace one frame without
			// recopying the theme. Explicit frame values remain accepted for
			// callers predating those separate steps.
			$applied = [];

			foreach( self::FRAMES as $kind => $paths ) {

				$frame = self::_frameKey( $kind, (string) ( $data[$kind] ?? '' ), (string) ( $manifest[$kind] ?? '' ) );

				if( $frame === null )
					continue;

				self::_applyFrame( $appData, $kind, $frame );

				$appData['/nino/install/'. $kind] = $frame;
				$applied[$kind] = $frame;
			}

			$appData['/nino/html/assets'] = self::_bundleFrames( $appData, array_keys( $applied ) );

			$keys = array_merge( [ '/nino/install/theme', '/nino/html/assets' ], array_map( static fn( string $kind ): string => '/nino/install/'. $kind, array_keys( $applied ) ) );

			// Design last: Theme::write() generates the stylesheet, splices it
			// into whatever the bundle looks like by now, and persists both.
			// Doing it before the frames would place it against a bundle that
			// is about to change underneath it
			if( self::_designAvailable() === true ) {

				$design = \Nino\Design\Tokens::normalize( is_array( $data['design'] ?? null ) ? $data['design'] : ( is_array( $manifest['design'] ?? null ) ? $manifest['design'] : [] ) );

				if( \Nino\Design\Design::write( $appData, $design ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not write the design stylesheet' );
					return;
				}
			}

			\Nino\AppData::writeContentData( $appData, $keys );

			\Nino\Http::ok( $request, [ 'theme' => $key ] + $applied );
		}

		/**
		 *	Whether /_design is part of this delivery. Install.php is required
		 *	by a tool folder that may have been stripped from a release, so
		 *	the Design step degrades to "not offered" rather than
		 *	to a fatal on a class that isn't there
		 *
		 *	@return 	bool
		 */
		private static function _designAvailable(): bool {
			return class_exists( '\Nino\Design\Design' ) === true && class_exists( '\Nino\Design\Tokens' ) === true;
		}

		/**
		 *	Which frame unit to install for one kind: the posted pick, else
		 *	whatever the theme's manifest names, else the first unit on disk.
		 *	Every candidate goes through _frames() rather than being trusted,
		 *	so a posted key can only ever name a unit that really exists
		 *
		 *	@param		string		$kind					'header' or 'footer'
		 *	@param		string		$posted				The operator's pick, straight off the wire
		 *	@param		string		$declared			What the picked theme's manifest names
		 *
		 *	@return 	string|null							Null if this kind ships no units at all
		 */
		private static function _frameKey( string $kind, string $posted, string $declared ): ?string {

			$available = self::_frames()[$kind] ?? [];

			foreach( [ $posted, $declared ] as $candidate )
				if( $candidate !== '' && in_array( $candidate, $available, true ) === true )
					return $candidate;

			return $available[0] ?? null;
		}

		/**
		 *	Copy one frame unit into the project: its template becomes the
		 *	/templates/theme.&lt;kind&gt;.tpl the base html-header/footer templates
		 *	include, its style.css the bundled stylesheet for that markup. A
		 *	unit with no style.css of its own (one built entirely on classes
		 *	Nino.css already styles) still gets an empty file written, so the
		 *	bundle entry never points at something that isn't there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					'header' or 'footer'
		 *	@param		string		$frame				A key _frames() listed
		 *
		 *	@return 	void
		 */
		private static function _applyFrame( array &$appData, string $kind, string $frame ): void {

			$unitDir = dirname( self::LIBRARY ). '/'. $kind. '/'. $frame;

			self::_copyFile( $unitDir. '/template.tpl', \Nino\Filesystem::path( $appData, self::FRAMES[$kind]['template'] ) );

			$stylesheet = \Nino\Filesystem::path( $appData, self::FRAMES[$kind]['stylesheet'] );

			if( is_file( $unitDir. '/style.css' ) === true )
				self::_copyFile( $unitDir. '/style.css', $stylesheet );
			else
				file_put_contents( $stylesheet, '' );
		}

		/**
		 *	Every frame unit on disk, per kind. A unit is a directory with a
		 *	template.tpl in it - style.css is optional, and there is no
		 *	manifest: a frame has nothing to declare that its two files don't
		 *	already say
		 *
		 *	@return 	array			kind -> list of keys
		 */
		private static function _frames(): array {

			$frames = [];

			foreach( array_keys( self::FRAMES ) as $kind ) {

				$frames[$kind] = [];
				$directory 		 = dirname( self::LIBRARY ). '/'. $kind;

				foreach( scandir( $directory ) ?: [] as $entry ) {

					if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $entry ) !== 1 )
						continue;

					if( is_file( $directory. '/'. $entry. '/template.tpl' ) === true )
						$frames[$kind][] = $entry;
				}

				sort( $frames[$kind] );
			}

			return $frames;
		}

		/**
		 *	Frame kinds already present in the project, in canonical bundle
		 *	order. The persisted key is the normal signal; recognizing an
		 *	existing bundle entry too keeps projects created before those keys
		 *	were introduced stable when only one frame is changed.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _activeFrameKinds( array &$appData ): array {

			$bundled = array_map( 'strval', $appData['/nino/html/assets'][self::BUNDLE_KEY] ?? [] );
			$active 	 = [];

			foreach( self::FRAMES as $kind => $paths )
				if( (string) ( $appData['/nino/install/'. $kind] ?? '' ) !== '' || in_array( $paths['stylesheet'], $bundled, true ) === true )
					$active[] = $kind;

			return $active;
		}

		/**
		 *	'/nino/html/assets' with each applied frame's stylesheet sitting
		 *	directly after the theme's. The order is the contract: Design
		 *	supplies the values, the theme assigns them to roles, and a frame
		 *	styles the markup it brought using those roles - so it has to be
		 *	able to override the theme and not the other way round. An entry
		 *	already in the bundle is moved rather than duplicated
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$kinds				Which frame kinds were just applied
		 *
		 *	@return 	array
		 */
		private static function _bundleFrames( array &$appData, array $kinds ): array {

			$assets = $appData['/nino/html/assets'] ?? [];

			$wanted = [];
			foreach( $kinds as $kind )
				$wanted[] = self::FRAMES[$kind]['stylesheet'];

			$files = array_values( array_filter(
				array_map( 'strval', $assets[self::BUNDLE_KEY] ?? [] ),
				static fn( string $file ): bool => in_array( $file, $wanted, true ) === false
			) );

			// After the theme stylesheet, or - with no theme in the bundle at
			// all - after everything, since a frame overriding project css it
			// has never seen is the worse failure of the two
			$position = false;
			foreach( self::_stylesheets() as $stylesheet ) {
				$found = array_search( $stylesheet, $files, true );
				if( $found !== false )
					$position = $found;
			}

			array_splice( $files, $position === false ? count( $files ) : $position + 1, 0, $wanted );

			$assets[self::BUNDLE_KEY] = $files;

			return $assets;
		}

		/**
		 *	'/nino/html/assets' with the picked theme's stylesheet in place
		 *	of whichever theme stylesheet the css bundle carried before -
		 *	matched against every path the library's own themes declare, so
		 *	a project's hand-added stylesheet in that same bundle is never
		 *	one of them and stays put, at its own position. Carried at the
		 *	old theme entry's index rather than appended, since a
		 *	stylesheet's position in the bundle is what decides which
		 *	:root block wins the cascade (see docs/design.md); with no
		 *	theme entry to replace (a hand-emptied bundle, or none at all)
		 *	it goes last, after whatever the project already loads
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$stylesheet		The picked theme's project-relative stylesheet
		 *
		 *	@return 	array
		 */
		private static function _bundle( array &$appData, string $stylesheet ): array {

			$assets = $appData['/nino/html/assets'] ?? [];
			$known 	= self::_stylesheets();

			$files 	= [];
			$placed = false;

			foreach( ( $assets[self::BUNDLE_KEY] ?? [] ) as $file ) {

				if( in_array( (string) $file, $known, true ) === false ) {
					$files[] = $file;
					continue;
				}

				// Every further theme stylesheet in the same bundle (a
				// hand-edited config listing two) collapses into this one
				if( $placed === false ) {
					$files[] = $stylesheet;
					$placed 	= true;
				}
			}

			if( $placed === false )
				$files[] = $stylesheet;

			$assets[self::BUNDLE_KEY] = $files;

			return $assets;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array			key -> { label, description, preview }
		 */
		private static function _themes( array &$appData ): array {

			$themes = [];

			foreach( scandir( self::LIBRARY ) ?: [] as $entry ) {

				$manifest = self::_readManifest( $entry );
				if( $manifest === null )
					continue;

				// The preview image is served straight off disk, out of the
				// library folder itself - it's picker chrome, not project
				// content, so it is deliberately not among the files apiApply()
				// copies. Prefixed with the deploy path since /_install's own
				// js has no other way to know it (the project may live in a
				// subdirectory rather than at the site root)
				$preview = (string) ( $manifest['preview'] ?? '' );

				$themes[$entry] = [
					'label' 			=> (string) ( $manifest['label'] ?? $entry ),
					'description' => (string) ( $manifest['description'] ?? '' ),
					'preview' 		=> ( $preview !== '' && is_file( self::LIBRARY. '/'. $entry. '/'. $preview ) === true )
						? ( (string) ( $appData['/nino/dir'] ?? '' ) ). '/_install/library/themes/'. $entry. '/'. $preview
						: null,
					// What this look was drawn against. Theme apply installs
					// these as the baseline; the later Design/Header/Footer
					// steps read or preselect them, so a plain trip through
					// the wizard produces the look the preview promised
					'header' 			=> (string) ( $manifest['header'] ?? '' ),
					'footer' 			=> (string) ( $manifest['footer'] ?? '' ),
					'design' 			=> is_array( $manifest['design'] ?? null ) ? $manifest['design'] : null,
				];
			}

			ksort( $themes );

			return $themes;
		}

		/**
		 *	The currently applied theme: whatever '/nino/install/theme'
		 *	names, as long as it still resolves to a real unit.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							Null if no library theme is currently applied
		 */
		private static function _currentTheme( array &$appData ): ?string {

			$key = (string) ( $appData['/nino/install/theme'] ?? '' );

			return self::_readManifest( $key ) !== null ? $key : null;
		}

		/**
		 *	Every theme's declared project-relative stylesheet path - what
		 *	_bundle() strips out of the css bundle before adding the picked
		 *	one back in.
		 *
		 *	@return 	array			key -> stylesheet path
		 */
		private static function _stylesheets(): array {

			$paths = [];

			foreach( scandir( self::LIBRARY ) ?: [] as $entry ) {

				$manifest = self::_readManifest( $entry );
				if( $manifest === null )
					continue;

				$paths[$entry] = (string) $manifest['stylesheet'];
			}

			return $paths;
		}

		/**
		 *	One theme unit's manifest.php. A theme without a 'stylesheet' is
		 *	not a theme this step can apply - there would be nothing to
		 *	bundle - so it never shows up in the picker either.
		 *
		 *	$key comes straight off the wire in apiApply(), so it is matched
		 *	against a plain slug before ever reaching the filesystem - that
		 *	rules out '.'/'..'/separators (a posted key trying to reach a
		 *	directory outside library/themes) and null bytes (which php's own
		 *	path functions raise a ValueError on) in one go, rather than
		 *	relying on the lookup below quietly not resolving
		 *
		 *	@param		string		$key					Directory name under library/themes
		 *
		 *	@return 	array|null							Null if $key names no usable theme unit
		 */
		private static function _readManifest( string $key ): ?array {

			if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $key ) !== 1 )
				return null;

			$path = self::LIBRARY. '/'. $key. '/manifest.php';

			if( is_file( $path ) === false )
				return null;

			$manifest = include $path;

			return ( is_array( $manifest ) === true && ( $manifest['stylesheet'] ?? '' ) !== '' ) ? $manifest : null;
		}

		/**
		 *	Copy one library file into the project - see Setup::_copyFile()'s
		 *	docblock, duplicated here for the same reason every class in this
		 *	file duplicates a small helper instead of reaching into a
		 *	sibling's internals
		 *
		 *	@param		string		$from
		 *	@param		string		$to
		 *
		 *	@return 	void
		 */
		private static function _copyFile( string $from, string $to ): void {

			$content = @file_get_contents( $from );
			if( $content === false )
				return;

			if( is_file( $to ) === true )
				@unlink( $to );

			file_put_contents( $to, $content );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 7: build the project's actual pages - a free-form,
	 *												ordered list of { uri, httpUri, libraryKey, navs, text }
	 *												entries a developer adds/reorders/removes here, rather
	 *												than a fixed checkbox per _install/library/pages/&lt;key&gt;
	 *												unit. "libraryKey" picks which library page bundle's
	 *												routes/templates/deeper content this entry uses (its own
	 *												manifest.php's 'routes' are reused as-is, only 'uri' is
	 *												overridden - the same bundle can be mounted more than
	 *												once, at different uris). "uri" (Element-URI) is a stable
	 *												identifier that never has to look like a real path - it
	 *												namespaces this entry's own [[/webpage&lt;uri&gt;/*]] text meta
	 *												and becomes the route's own 'uri' data field. "httpUri" is
	 *												the actual browser path a visitor requests - it alone
	 *												drives the /nino/http/routes array key (see _routeKeys()'s
	 *												docblock for why the two have to differ, eg. the home page
	 *												keeping a stable '/home' identifier while really living at
	 *												'/'); "text" carries this entry's own name/title/
	 *												description per active locale, defaulting to a generic
	 *												placeholder rather than whatever wording the template's
	 *												manifest may ship, since that's a per-instance choice now,
	 *												not a per-template one.
	 *
	 *												Nothing of this is persisted as a list of its own: the
	 *												routes are the pages (see isPageRoute()/pages()), the
	 *												/webpage&lt;uri&gt;/* text keys are their wording, and the
	 *												route's 'navs' its menu membership. /_admin/Admin.php's
	 *												PageEditor reads and writes exactly the same routes (its
	 *												own standalone copy of this shape/logic, restricted to
	 *												templates already on disk rather than library units) for
	 *												once this folder has been deleted - see its own docblock
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Webpages {

		private const string LIBRARY = __DIR__. '/library';

		// Runtime-owned tool routes are not persisted in config.php, so a
		// collision check against the on-disk route array alone cannot see
		// them. A public page at one of these exact paths would hide the tool's
		// own GET response once its authenticated/default-password gate passes.
		private const array RESERVED_HTTP_URIS = [ '/_admin', '/_editor', '/_install', '/_templates', '/_design' ];

		// The priority a menu membership falls back to when the entry
		// carries no position of its own - apiApply() numbers every entry by
		// its place in the list, so this only ever applies to a hand-built
		// post. The middle of the range, same as
		// Callbacks::registerCallback()'s own default
		private const int DEFAULT_NAV_PRIO = 5;

		// A fresh entry's text fields when the frontend doesn't post its
		// own (or posts a blank one) - see apiApply()'s $text loop.
		// Deliberately generic: unlike a template's own deeper content
		// (which still ships real starter wording, see Setup), an
		// instance's own meta is this developer's to write, and defaulting
		// it to eg. "Home" would just be wrong the moment the same
		// template gets mounted a second time under a different uri
		private const array DEFAULT_TEXT = [
			'name' 				=> 'Page',
			'title' 			=> 'Page Title',
			'description' => 'Page description.',
		];

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'webpages/list' 	=> [ self::class, 'apiList' ],
				'webpages/apply' 	=> [ self::class, 'apiApply' ],
			];
		}

		/**
		 *	List every selectable template (one per _install/library/pages/&lt;key&gt;
		 *	directory) and the page list as it currently stands - state
		 *	restoration, same reasoning as Setup::apiLibrary()'s docblock:
		 *	apply() replaces the whole list, so the frontend has to be able
		 *	to show what's already there. The list is derived from the routes
		 *	on every call, never read back out of a copy this step persisted
		 *	(see pages())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			$locales 	= $appData['/nino/locales/available'] ?? [];
			$navs 		= self::navKeys( $appData );

			// The persisted route array, never the live one polluted by
			// /_install's and the modules' own bootstrap routes
			$routes 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			\Nino\Http::ok( $request, [
				'templates' 	=> self::_templates( $locales ),
				'locales' 		=> $locales,
				'webpages' 		=> self::pages( $appData, $routes, $locales, $navs ),
				'navs' 				=> $navs,
			] );
		}

		/**
		 *	The navigations this project offers, in the order a menu picker
		 *	should list them: '/nino/html/navs', a plain list of keys.
		 *
		 *	Purely an editing affordance - Modules\Navigation renders whatever
		 *	key a template asks for, registered here or not, so a project that
		 *	never writes this array loses nothing but the checkboxes. Empty
		 *	while the Navigation module is inactive, which is what tells the
		 *	frontend to offer no menu fields at all.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Nav keys, eg. [ 'main', 'footer' ]
		 */
		public static function navKeys( array &$appData ): array {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return [];

			$navs = $appData['/nino/html/navs'] ?? [];

			return array_values( array_unique( array_map( 'strval', $navs ) ) );
		}

		/**
		 *	Which navigations one posted entry asks to be in, narrowed to the
		 *	navigations this project actually registers.
		 *
		 *	@param		array 		$entry				One posted webpages entry
		 *	@param		array 		$navs					Registered nav keys (see navKeys())
		 *
		 *	@return 	array										Nav keys this entry belongs to
		 */
		public static function entryNavs( array $entry, array $navs ): array {

			return array_values( array_intersect( $navs, is_array( $entry['navs'] ?? null ) ? $entry['navs'] : [] ) );
		}

		/**
		 *	@param		array 		$locales			Picked locales - drives which locales each unit's own
		 *																	suggested wording is read for (empty: none, which is
		 *																	all apiApply()'s validation-only call needs)
		 *
		 *	@return 	array			folder name -> { label, requiresModules, uri, text }
		 */
		private static function _templates( array $locales = [] ): array {

			$units = [];

			foreach( scandir( self::LIBRARY. '/pages' ) ?: [] as $entry ) {

				if( $entry === '.' || $entry === '..' )
					continue;

				$manifest = self::_readManifest( self::LIBRARY. '/pages/'. $entry );
				if( $manifest === null )
					continue;

				$units[$entry] = [
					'label' 					=> (string) ( $manifest['label'] ?? $entry ),
					'requiresModules' => $manifest['requiresModules'] ?? [],
				] + self::_suggestions( $entry, $locales );
			}

			ksort( $units );

			return $units;
		}

		/**
		 *	The starter wording a library unit ships for an instance of
		 *	itself: the [[/webpage/&lt;folder name&gt;/{uri,name,title,
		 *	description}]] keys its own text/&lt;locale&gt;.php fragments still
		 *	carry.
		 *
		 *	_applyWebpage() deliberately never merges those as text (see
		 *	_withoutWebpageMeta()) - keyed by the template's folder name
		 *	rather than the uri an instance actually gets mounted at, they'd
		 *	be stale the moment the two differ. As the Webpages form's own
		 *	prefill they're exactly right though, and the only place a
		 *	template's per-locale wording exists at all: without it, every
		 *	locale nobody hand-typed silently lands on DEFAULT_TEXT's generic
		 *	"Page"/"Page Title" (which stays the fallback for a blank field -
		 *	see apiApply()'s $text loop - it just isn't what a freshly picked
		 *	template should start from).
		 *
		 *	The menus a unit suggests for itself come from its manifest route's
		 *	own 'navs' instead - a starting point for the form's checkboxes,
		 *	exactly like the wording is for its text fields
		 *
		 *	@param		string		$libraryKey
		 *	@param		array 		$locales			Picked locales
		 *
		 *	@return 	array			{ uri, navs, text: { <locale>: { name, title, description } } }
		 */
		private static function _suggestions( string $libraryKey, array $locales ): array {

			$prefix = '[[/webpage/'. $libraryKey. '/';
			$uri 		= '';
			$text 	= [];

			foreach( $locales as $locale ) {

				$path 		= self::LIBRARY. '/pages/'. $libraryKey. '/text/'. $locale. '.php';
				$fragment = is_file( $path ) === true ? include $path : [];

				$text[$locale] = [
					'name' 				=> (string) ( $fragment[$prefix. 'name]]'] 				?? '' ),
					'title' 			=> (string) ( $fragment[$prefix. 'title]]'] 			?? '' ),
					'description' => (string) ( $fragment[$prefix. 'description]]'] ?? '' ),
				];

				// One Http-URI for every locale - an entry only has the single
				// one (see _routeKeys()'s docblock), so the first locale that
				// names one wins rather than the last
				if( $uri === '' )
					$uri = (string) ( $fragment[$prefix. 'uri]]'] ?? '' );
			}

			$manifestRoute = array_values( ( self::_readManifest( self::LIBRARY. '/pages/'. $libraryKey ) ?? [] )['routes'] ?? [] )[0] ?? [];

			return [
				'uri' 	=> $uri,
				'navs' 	=> array_keys( ( $manifestRoute['navs'] ?? [] ) ),
				'body' 	=> (string) ( $manifestRoute['body'] ?? '' ),
				'text' 	=> $text,
			];
		}

		/**
		 *	Whether one route is a page this step manages.
		 *
		 *	A page is a GET route rendering a templates/page-*.tpl file -
		 *	the same criterion /_admin's template picker already uses, and
		 *	the one thing that keeps robots.txt/sitemap.xml/llms.txt (GET
		 *	routes with their own headers and no page template) out of a
		 *	page list. Matched on the body's prefix rather than through
		 *	_templateFromBody(), which deliberately reports null for a body
		 *	that resolves its file at runtime - the "legal" unit's
		 *	[[/nino/http/response/locale]] body is a page like any other
		 *
		 *	@param		string		$routeKey			Eg. 'GET://kontakt'
		 *	@param		array 		$route
		 *
		 *	@return 	bool
		 */
		public static function isPageRoute( string $routeKey, array $route ): bool {

			return str_starts_with( $routeKey, 'GET://' ) === true
				&& preg_match( '~^\[template /templates/page-~', trim( (string) ( $route['body'] ?? '' ) ) ) === 1;
		}

		/**
		 *	The page list both editing tools show, derived from the routes
		 *	and the text files rather than from a second, parallel array
		 *	that could drift against them.
		 *
		 *	Every field is recovered from where it actually lives: the
		 *	route key is the Http-URI, the route's own 'uri' data field the
		 *	Element-URI, 'navs' the menu membership, and the per-locale
		 *	name/title/description are the /webpage&lt;uri&gt;/* keys this
		 *	step writes into /text/&lt;locale&gt;.php. 'libraryKey' is
		 *	resolved back from the body (see _unitFromBody()) so the form's
		 *	template picker can still show which library unit a page came
		 *	from - it is never stored
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$locales			Picked locales
		 *	@param		array 		$navKeys			Registered nav keys (see navKeys())
		 *
		 *	@return 	array										One entry per page route, in route order
		 */
		public static function pages( array &$appData, array $routes, array $locales, array $navKeys ): array {

			$text = [];
			foreach( $locales as $locale )
				$text[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$pages = [];

			foreach( $routes as $routeKey => $route ) {

				if( self::isPageRoute( $routeKey, $route ) === false )
					continue;

				$httpUri 		= substr( $routeKey, strlen( 'GET:/' ) );
				$uri 				= (string) ( $route['uri'] ?? $httpUri );
				$body 			= (string) ( $route['body'] ?? '' );
				$libraryKey = self::_unitFromBody( $body );

				// A page whose meta has never been written yet starts from the
				// wording its library unit suggests, in every active locale -
				// the same starter text a page newly picked in this step gets
				// (see _suggestions()). This is what the shipped config's four
				// pages look like on a fresh checkout: their routes are
				// tracked, the /text files they read from are not (see
				// docs/_install.md), so without this the wizard would apply
				// "Page"/"Page Title" over a starter site that ships real
				// wording for both languages
				$suggested = $libraryKey !== '' ? self::_suggestions( $libraryKey, $locales )['text'] : [];

				$entryText = [];
				foreach( $locales as $locale )
					foreach( [ 'name', 'title', 'description' ] as $field )
						$entryText[$locale][$field] = (string) ( $text[$locale]['[[/webpage'. $uri. '/'. $field. ']]']
							?? $suggested[$locale][$field]
							?? '' );

				$pages[] = [
					'uri' 				=> $uri,
					'httpUri' 		=> $httpUri,
					'template' 		=> self::_templateFromBody( $body ) ?? '',
					'libraryKey' 	=> $libraryKey,
					'navs' 				=> array_values( array_intersect( $navKeys, array_keys( (array) ( $route['navs'] ?? [] ) ) ) ),
					'statusCode' 	=> (int) ( $route['statusCode'] ?? 200 ),
					'body' 				=> $body,
					'text' 				=> $entryText,
				];
			}

			return $pages;
		}

		/**
		 *	Which library unit a route body came from, or '' for a body no
		 *	unit declares (hand-written, or a page pointed at a template
		 *	that was never part of the library).
		 *
		 *	Every unit's manifest route declares a distinct body, so the
		 *	body identifies the unit on its own - which is why the binding
		 *	does not have to be persisted anywhere. It only decides which
		 *	unit's files a *new* page installs (see apiApply()); an existing
		 *	page is just a route with a template
		 *
		 *	@param		string		$body
		 *
		 *	@return 	string									Unit key, or '' if no unit declares this body
		 */
		private static function _unitFromBody( string $body ): string {

			if( $body === '' )
				return '';

			foreach( self::_templates() as $key => $unit )
				if( ( $unit['body'] ?? '' ) === $body )
					return (string) $key;

			return '';
		}

		/**
		 *	@param		string		$unitDir
		 *
		 *	@return 	array|null							Null if $unitDir has no manifest.php
		 */
		private static function _readManifest( string $unitDir ): ?array {

			$path = $unitDir. '/manifest.php';

			return is_file( $path ) ? include $path : null;
		}

		/**
		 *	Validate and persist the posted list, then batch-regenerate
		 *	routes/templates/text/blacklist/modules from it - in that order,
		 *	entry by entry, so the response ("Applied N pages") reflects
		 *	exactly what actually landed.
		 *
		 *	A full replace of the *page* routes, the same reasoning as
		 *	Setup::apiApply()'s docblock: whatever's posted is the complete,
		 *	authoritative picture. What makes that safe without a second,
		 *	persisted list to remember ownership by is isPageRoute(): the
		 *	step shows every route it could delete, so a page route that
		 *	exists and wasn't posted is one the developer removed. Anything
		 *	that isn't a page route - robots.txt, a module's own, a
		 *	hand-written one - is never a deletion candidate and survives
		 *	untouched.
		 *
		 *	Templates/text stay additive, same as Setup - a uri dropped
		 *	from the list doesn't delete the template file or text it
		 *	already wrote.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiApply( array &$appData, array &$request ): void {

			$data 			= \Nino\Install\Install::postData();
			$posted 		= is_array( $data['webpages'] ?? null ) ? $data['webpages'] : [];
			$templates 	= self::_templates();
			$locales 		= $appData['/nino/locales/available'] ?? [];
			$navKeys 		= self::navKeys( $appData );

			// Work from the persisted route array, never the live one polluted
			// by /_install's and the modules' own bootstrap routes. Everything
			// that isn't a page route belongs to Setup, a module or a
			// developer and must not be touched here at all.
			$config 		= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$routes 		= $config['/nino/http/routes'] ?? [];
			$foreignRoutes = array_filter( $routes, fn( array $r, string $k ): bool => self::isPageRoute( $k, $r ) === false, ARRAY_FILTER_USE_BOTH );

			$webpages 		= [];
			$seenUris 		= [];
			$seenHttpUris = [];

			foreach( $posted as $item ) {

				$uri 			= self::_normalizeUri( (string) ( $item['uri'] ?? '' ) );
				$httpUri 	= self::_normalizeUri( (string) ( $item['httpUri'] ?? '' ) );

				// 'libraryKey' names the _install/library/pages unit this
				// entry starts from - reported by pages(), which resolves it
				// from the route's own body (see _unitFromBody()), or picked
				// in the template select. A page pointed at a template no unit
				// declares carries none; it passes through untouched below on
				// its own body rather than being rejected, which is what lets
				// this step re-apply a list containing a page /_admin created.
				$libraryKey = (string) ( $item['libraryKey'] ?? '' );

				$body = (string) ( $item['body'] ?? '' );

				if( $uri === null ) {
					\Nino\Http::fail( $request, 400, 'invalid uri: "'. ( (string) ( $item['uri'] ?? '' ) ). '"' );
					return;
				}

				if( $httpUri === null ) {
					\Nino\Http::fail( $request, 400, 'invalid http uri: "'. ( (string) ( $item['httpUri'] ?? '' ) ). '"' );
					return;
				}

				if( in_array( $httpUri, self::RESERVED_HTTP_URIS, true ) === true ) {
					\Nino\Http::fail( $request, 409, 'reserved http uri: "'. $httpUri. '"' );
					return;
				}

				$routeKey = 'GET://'. trim( $httpUri, '/' );
				if( isset( $foreignRoutes[$routeKey] ) === true ) {
					\Nino\Http::fail( $request, 409, 'http uri already belongs to another route: "'. $httpUri. '"' );
					return;
				}

				if( $libraryKey !== '' && isset( $templates[$libraryKey] ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown page library key: "'. $libraryKey. '"' );
					return;
				}

				if( $libraryKey === '' && $body === '' ) {
					\Nino\Http::fail( $request, 400, 'page must name a libraryKey or body' );
					return;
				}

				if( isset( $seenUris[$uri] ) === true ) {
					\Nino\Http::fail( $request, 400, 'duplicate uri: "'. $uri. '"' );
					return;
				}
				$seenUris[$uri] = true;

				if( isset( $seenHttpUris[$httpUri] ) === true ) {
					\Nino\Http::fail( $request, 400, 'duplicate http uri: "'. $httpUri. '"' );
					return;
				}
				$seenHttpUris[$httpUri] = true;

				$text = [];
				foreach( $locales as $locale ) {
					$row = (array) ( $item['text'][$locale] ?? [] );
					$text[$locale] = [
						'name' 				=> self::_orDefault( $row['name'] 				?? '', self::DEFAULT_TEXT['name'] ),
						'title' 			=> self::_orDefault( $row['title'] 			?? '', self::DEFAULT_TEXT['title'] ),
						'description' => self::_orDefault( $row['description'] ?? '', self::DEFAULT_TEXT['description'] ),
					];
				}

				// Resolve the route the picked unit declares here rather than
				// only inside _applyWebpage(), so the body this apply is
				// about is known before it is written
				$route 			= $libraryKey !== '' ? self::_unitRoute( $libraryKey, $locales ) : null;
				$routeBody 	= $route !== null ? (string) ( $route['body'] ?? '' ) : $body;

				// A 'templatePerRoute' unit renders its own copy, so the body
				// stored here has to be the one _applyWebpage() will actually
				// write - otherwise the persisted list and the route disagree,
				// and the status-code comparison below reads a body no route
				// ever had
				$perRouteBody = $libraryKey !== '' ? self::_perRouteBody( [ 'uri' => $uri ], $libraryKey ) : null;
				if( $perRouteBody !== null )
					$routeBody = $perRouteBody;

				// The unit's own status code only applies to an entry that is
				// new or has just been moved onto a different template - one
				// staying on the body it already has keeps the code it
				// carries, which is where a page /_admin created (and gave eg.
				// a 201) has it. Necessary because a library unit is
				// identified by its route body (see _unitFromBody()): a page
				// /_admin wired up to templates/page-home.tpl by hand is
				// indistinguishable from the "home" unit, and must not have
				// that unit's status code pushed onto it
				$statusCode = $route !== null && $routeBody !== $body
					? (int) ( $route['statusCode'] ?? 200 )
					: (int) ( $item['statusCode'] ?? 200 );
				if( $statusCode < 100 || $statusCode > 599 )
					$statusCode = 200;

				// Menu membership, with this entry's own position in the list
				// as the priority - the same value for every menu it is in.
				// Sorting the pages here is therefore what orders the menus,
				// and the order is explicit in config.php afterwards rather
				// than implied by the route array's sequence
				$webpages[] = [
					'uri' 				=> $uri,
					'httpUri' 		=> $httpUri,
					'libraryKey' 	=> $libraryKey,
					'navs' 				=> self::entryNavs( $item, $navKeys ),
					'prio' 				=> count( $webpages ) + 1,
					'statusCode' 	=> $statusCode,
					'body' 				=> $routeBody,
					'text' 				=> $text,
				];
			}

			// Every page route the posted list no longer mentions was deleted
			// by the developer - the step shows all of them, so "not posted"
			// can only mean "removed" (see this method's docblock). Rebuilt
			// from $foreignRoutes so the surviving pages also end up in the
			// posted order, foreign routes keeping their place ahead of them
			$routes = $foreignRoutes;

			// Auto-pull whatever module a used template requires (eg.
			// "contact" -> forms+mail), same reasoning as Setup's own
			// module-requirement resolving, just sourced from templates
			// instead of a picked module's own requiresModules. Not just
			// flipping the moduleClass on: a required module not already
			// active (eg. "mail" was never checked in Setup because
			// nothing needed it until this apply) still needs its own
			// templates/text actually copied - see _applyModule() - the
			// same way Setup::_applyUnit() would have, had it been picked
			// there instead
			$requiredModuleKeys = [];
			foreach( array_unique( array_filter( array_column( $webpages, 'libraryKey' ) ) ) as $templateKey ) {
				$manifest = self::_readManifest( self::LIBRARY. '/pages/'. $templateKey ) ?? [];
				foreach( ( $manifest['requiresModules'] ?? [] ) as $moduleKey )
					$requiredModuleKeys[$moduleKey] = true;
			}

			$moduleClasses = $appData['/nino/modules'] ?? [];
			foreach( array_keys( $requiredModuleKeys ) as $moduleKey ) {
				$moduleManifest = self::_readManifest( self::LIBRARY. '/modules/'. $moduleKey ) ?? [];
				if( isset( $moduleManifest['moduleClass'] ) === true && in_array( $moduleManifest['moduleClass'], $moduleClasses, true ) === false )
					$moduleClasses[] = $moduleManifest['moduleClass'];
			}
			$appData['/nino/modules'] = array_values( array_unique( $moduleClasses ) );

			$blacklist = [];

			foreach( array_keys( $requiredModuleKeys ) as $moduleKey )
				self::_applyModule( $appData, self::LIBRARY. '/modules/'. $moduleKey, $locales, $blacklist );

			foreach( $webpages as $entry )
				self::_applyWebpage( $appData, $entry, $locales, $routes, $blacklist );

			$appData['/nino/http/routes'] = $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/http/routes' ] );

			if( count( $blacklist ) > 0 )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( $list, $blacklist ) ) );
				} );

			self::_applyLegalLink( $appData, $webpages, $locales );

			// Derived back off the routes just written, not the working copy
			// above: what the frontend holds after an apply is then exactly
			// what a reload would hand it (see apiList())
			\Nino\Http::ok( $request, [ 'webpages' => self::pages( $appData, $routes, $locales, $navKeys ) ] );
		}

		/**
		 *	Where a 'templatePerRoute' unit's template lands for one entry,
		 *	and which of the unit's own files it is copied from.
		 *
		 *	Named after the entry's Element URI rather than its Http URI: the
		 *	Element URI is the stable identifier (the Http one is free to
		 *	change, and '/' has no name at all), and page-&lt;uri&gt;.tpl is the
		 *	shape /_admin's template picker globs for. A nested uri flattens
		 *	to one segment - that picker lists templates/page-*.tpl and never
		 *	looks into subdirectories.
		 *
		 *	Falls back to the unit's own single template when the uri yields
		 *	nothing usable, so a strange entry still gets a working page
		 *	rather than a route pointing at a file that was never written.
		 *
		 *	@param		array 		$entry				One webpages-list entry
		 *	@param		array 		$manifest			The unit's manifest
		 *
		 *	@return 	array|null							{ file, source }, or null without a template to copy
		 */
		/**
		 *	The route body a 'templatePerRoute' unit produces for one entry,
		 *	or null for a unit that shares one template. Both apiApply() (which
		 *	persists the list) and _applyWebpage() (which writes the route)
		 *	resolve it through here, so the two can never disagree about which
		 *	template a route renders.
		 *
		 *	@param		array 		$entry				One webpages-list entry
		 *	@param		string		$libraryKey
		 *
		 *	@return 	string|null
		 */
		private static function _perRouteBody( array $entry, string $libraryKey ): ?string {

			$manifest = self::_readManifest( self::LIBRARY. '/pages/'. $libraryKey ) ?? [];

			if( ( $manifest['templatePerRoute'] ?? false ) !== true )
				return null;

			$template = self::_perRouteTemplate( $entry, $manifest );

			return $template === null ? null : '[template /templates/'. pathinfo( $template['file'], PATHINFO_FILENAME ). ']';
		}

		private static function _perRouteTemplate( array $entry, array $manifest ): ?array {

			$source = (string) ( array_values( $manifest['templates'] ?? [] )[0] ?? '' );

			if( $source === '' )
				return null;

			$slug = strtolower( trim( (string) ( $entry['uri'] ?? '' ), '/' ) );
			$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
			$slug = trim( (string) $slug, '-' );

			return [
				'file' 		=> $slug === '' ? $source : 'page-'. $slug. '.tpl',
				'source' 	=> $source,
			];
		}

		/**
		 *	'/foo', '/foo/', 'foo' all normalize to '/foo'; '/' stays '/'.
		 *	Rejects anything empty, without a leading slash once normalized
		 *	is impossible anyway, or containing '..'/characters outside a
		 *	plain path
		 *
		 *	@param		string		$uri
		 *
		 *	@return 	string|null							Null if $uri can't be normalized into a safe path
		 */
		private static function _normalizeUri( string $uri ): ?string {

			$uri = trim( $uri );
			if( $uri === '' )
				return null;

			if( $uri[0] !== '/' )
				$uri = '/'. $uri;
			if( $uri !== '/' )
				$uri = rtrim( $uri, '/' );

			if( str_contains( $uri, '..' ) === true || preg_match( '#^/[a-zA-Z0-9\-_./]*$#', $uri ) !== 1 )
				return null;

			return $uri;
		}

		/**
		 *	@param		string		$value
		 *	@param		string		$default
		 *
		 *	@return 	string
		 */
		private static function _orDefault( string $value, string $default ): string {
			$value = trim( $value );
			return $value !== '' ? $value : $default;
		}

		/**
		 *	The route key one webpages-list entry produces - factored out
		 *	of _applyWebpage() so apiApply() can also use it, unapplied, to
		 *	figure out what the *previous* list's entry once produced (see
		 *	apiApply()'s docblock).
		 *
		 *	Always exactly one: \Nino\Http::requestRoute() matches a route
		 *	by looking up '&lt;METHOD&gt;:/'.$httpUri as a literal array key,
		 *	not by scanning for a route whose own 'uri' field matches - so
		 *	an entry can only ever be reached at the single, real browser
		 *	path its own httpUri names, regardless of how many routes the
		 *	picked template's manifest declares. The entry's uri (Element-
		 *	URI) plays no part here - it stays the route's own 'uri' data
		 *	field (see _applyWebpage()) and the /webpage&lt;uri&gt;/* text
		 *	meta namespace, deliberately decoupled from the real path so
		 *	eg. the home page can keep a stable '/home' identifier while
		 *	actually living at '/'. A template needing more than one real,
		 *	reachable uri per locale (eg. legal content whose actual slug
		 *	differs by language) can't express that through a Webpages
		 *	entry's single httpUri field; it has to pick the locale-
		 *	specific body some other way (eg. legal's own manifest, which
		 *	picks the right template file via the same
		 *	[[/nino/http/response/locale]] fill html-header.tpl already
		 *	uses for the page uri) rather than through separate routes
		 *
		 *	@param		array 		$entry				One webpages-list entry
		 *
		 *	@return 	array			Zero or one route key, deterministic from $entry alone
		 */
		private static function _routeKeys( array $entry ): array {

			$libraryKey = self::_libraryKey( $entry );

			// No library unit behind it (an entry /_admin's Pages module
			// created): it still owns exactly one route, keyed the same way -
			// returning nothing here would leave that route behind on the
			// next apply, since apiApply() strips the previous list's keys
			// through this very method
			if( $libraryKey !== null ) {
				$manifest = self::_readManifest( self::LIBRARY. '/pages/'. $libraryKey );
				if( $manifest === null || count( $manifest['routes'] ?? [] ) === 0 )
					return [];
			}

			return [ 'GET://'. trim( (string) ( $entry['httpUri'] ?? '' ), '/' ) ];
		}

		/**
		 *	The _install/library/pages unit one entry starts from, if it has
		 *	one at all.
		 *
		 *	@param		array 		$entry				One webpages-list entry
		 *
		 *	@return 	string|null							Null if no library unit backs this entry
		 */
		private static function _libraryKey( array $entry ): ?string {

			$candidate = (string) ( $entry['libraryKey'] ?? '' );
			if( $candidate !== '' && is_dir( self::LIBRARY. '/pages/'. $candidate ) === true )
				return $candidate;

			return null;
		}

		/**
		 *	The single route a library unit declares - locale-gated units
		 *	whose locale isn't picked resolve to null, same rule
		 *	_applyWebpage() applies when writing it
		 *
		 *	@param		string		$libraryKey
		 *	@param		array 		$locales			Picked locales
		 *
		 *	@return 	array|null							{ body, statusCode?, ... }, or null
		 */
		private static function _unitRoute( string $libraryKey, array $locales ): ?array {

			$manifest = self::_readManifest( self::LIBRARY. '/pages/'. $libraryKey ) ?? [];
			$route 		= array_values( $manifest['routes'] ?? [] )[0] ?? null;

			if( $route === null )
				return null;

			if( isset( $route['locale'] ) === true && in_array( $route['locale'], $locales, true ) === false )
				return null;

			return $route;
		}

		/**
		 *	The on-disk template a route body names, when it names exactly
		 *	one. A body can be more than a plain template reference - the
		 *	"legal" unit picks its file per locale via
		 *	[[/nino/http/response/locale]] - and there is no single template
		 *	name to report for those; null says so, rather than handing back
		 *	something that looks like a filename but isn't one
		 *
		 *	@param		string		$body					A route's body
		 *
		 *	@return 	string|null							Null if $body isn't a plain template reference
		 */
		private static function _templateFromBody( string $body ): ?string {
			return preg_match( '~^\[template /templates/([A-Za-z0-9._-]+)\]$~', trim( $body ), $match ) === 1 ? $match[1] : null;
		}

		/**
		 *	Apply one webpages-list entry: merge its template's (first,
		 *	only supported - see _routeKeys()'s docblock) route into
		 *	$routes, keyed via _routeKeys() (from $entry['httpUri'] - the
		 *	real, reachable path) with its own 'uri' data field overridden
		 *	to $entry['uri'] (the stable Element-URI, used for meta/fills -
		 *	see _routeKeys()'s docblock for why these differ), skipped if
		 *	it's gated to a locale that isn't picked (same as
		 *	Setup::_applyUnit() does); copy its template/
		 *	element-type files, collect its blacklist entries, merge its
		 *	deeper text/global.php + text/&lt;locale&gt;.php content (any
		 *	/webpage/&lt;name&gt;/* meta the manifest still ships is filtered
		 *	out - this method writes that itself, keyed by $entry['uri']
		 *	rather than the template's own folder name, so html-header.tpl's
		 *	[[/webpage[[/nino/http/response/uri]]/title]] lookup resolves
		 *	correctly regardless of which uri a template ends up mounted
		 *	at), and finally the entry's own name/title/description per
		 *	active locale
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				One webpages-list entry
		 *	@param		array 		$locales			Picked locales
		 *	@param		array 		&$routes			(reference) Routes accumulator - starts from what's on
		 *																	disk (see apiApply()), never from $appData directly
		 *	@param		array 		&$blacklist		(reference) Collected blacklist keys, appended to
		 *
		 *	@return 	void
		 */
		private static function _applyWebpage( array &$appData, array $entry, array $locales, array &$routes, array &$blacklist ): void {

			$routeKey 	= self::_routeKeys( $entry )[0] ?? null;
			$libraryKey = self::_libraryKey( $entry );

			if( $libraryKey !== null ) {

				$unitDir 	= self::LIBRARY. '/pages/'. $libraryKey;
				$manifest = self::_readManifest( $unitDir ) ?? [];
				$route 		= self::_unitRoute( $libraryKey, $locales );

				// A unit declaring 'templatePerRoute' hands every route its own
				// copy instead of one shared file - see the "blank" manifest for
				// why. Resolved before the route is written, since the copy's
				// name is also what the route body has to render
				$perRoute = ( $manifest['templatePerRoute'] ?? false ) === true
					? self::_perRouteTemplate( $entry, $manifest )
					: null;

				if( $routeKey !== null && $route !== null ) {

					$route['uri'] = $entry['uri'];

					if( $perRoute !== null )
						$route['body'] = (string) self::_perRouteBody( $entry, $libraryKey );

					// The entry's status code, not the unit's: a library unit is
					// identified by its route body (see _unitFromBody()), so a
					// page /_admin wired up to the same template file resolves
					// to the same unit and must keep the code it carries.
					// apiApply() has already decided which of the two that is
					unset( $route['statusCode'] );
					if( (int) ( $entry['statusCode'] ?? 200 ) !== 200 )
						$route['statusCode'] = (int) $entry['statusCode'];

					$routes[$routeKey] = $route;
				}

				if( $perRoute !== null ) {

					// Never over an existing file: the whole point of a per-route
					// copy is that it becomes that route's own page, and re-running
					// this step (or adding a second route) must not overwrite what
					// has been built in it since
					\Nino\Filesystem::forceDir( $appData, '/templates' );
					$target = \Nino\Filesystem::path( $appData, '/templates/'. $perRoute['file'] );
					if( is_file( $target ) === false )
						self::_copyFile( $unitDir. '/templates/'. $perRoute['source'], $target );

				} elseif( count( $manifest['templates'] ?? [] ) > 0 ) {
					\Nino\Filesystem::forceDir( $appData, '/templates' );
					foreach( $manifest['templates'] as $locale => $file ) {
						if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
							continue;
						self::_copyFile( $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ) );
					}
				}
					
				if( count( $manifest['files'] ?? [] ) > 0 ) {
					foreach( $manifest['files'] as $file )
						if( is_dir( $unitDir. '/'. $file ) === true )
							\Nino\Filesystem::copyDir( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ) );
						else if( is_file( $unitDir. '/'. $file ) === true )
							self::_copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ) );
				}

				if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
					\Nino\Filesystem::forceDir( $appData, '/elements' );
					foreach( $manifest['elementTypes'] as $file )
						self::_copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/elements/'. $file ) );
				}

				foreach( ( $manifest['blacklist'] ?? [] ) as $key )
					$blacklist[] = $key;

				$globalFragment = $unitDir. '/text/global.php';
				if( is_file( $globalFragment ) === true )
					self::_mergeText( $appData, '/text/global.php', self::_withoutWebpageMeta( include $globalFragment ) );

				foreach( $locales as $locale ) {
					$localeFragment = $unitDir. '/text/'. $locale. '.php';
					if( is_file( $localeFragment ) === true )
						self::_mergeText( $appData, '/text/'. $locale. '.php', self::_withoutWebpageMeta( include $localeFragment ) );
				}

			} elseif( $routeKey !== null && (string) ( $entry['body'] ?? '' ) !== '' ) {

				// An entry /_admin's Pages module created: there is no unit to
				// copy anything from - its template already exists on disk -
				// so the route it owns is simply put back as it stands
				$route = [ 'uri' => $entry['uri'], 'body' => (string) $entry['body'] ];

				if( (int) ( $entry['statusCode'] ?? 200 ) !== 200 )
					$route['statusCode'] = (int) $entry['statusCode'];

				$routes[$routeKey] = $route;
			}

			// Menu membership belongs on the route: that is what
			// Modules\Navigation::routeLines() reads, and it is the only copy
			// that renders. The entry's own position in the wizard's list is
			// the priority, the same value for every menu it joins
			if( $routeKey !== null && isset( $routes[$routeKey] ) === true ) {

				$navs = [];
				foreach( ( $entry['navs'] ?? [] ) as $navKey )
					$navs[$navKey] = (int) ( $entry['prio'] ?? self::DEFAULT_NAV_PRIO );

				if( count( $navs ) > 0 )
					$routes[$routeKey]['navs'] = $navs;
				else
					unset( $routes[$routeKey]['navs'] );
			}

			// The page's own Http-URI as a fill, so a template can link to a
			// page by name - [[/webpage/site-home/uri]] - instead of
			// hard-coding a path that this step can change on the next apply.
			// Global rather than per-locale: an entry carries exactly one
			// Http-URI for every locale (see _suggestions()). Blacklisted for
			// the same reason /website/url is - a technical value, not
			// wording anybody edits in /_editor's Text panel
			if( $routeKey !== null ) {
				self::_mergeText( $appData, '/text/global.php', [
					'[[/webpage'. $entry['uri']. '/uri]]' => (string) ( $entry['httpUri'] ?? '' ),
				] );
				$blacklist[] = '/webpage'. $entry['uri']. '/uri';
			}

			// The entry's own meta, whichever tool created it - this is what
			// the Webpages step's name/title/description fields edit, so it
			// has to be written back for a unit-less entry too
			foreach( $locales as $locale ) {

				$meta = $entry['text'][$locale] ?? self::DEFAULT_TEXT;

				self::_mergeText( $appData, '/text/'. $locale. '.php', [
					'[[/webpage'. $entry['uri']. '/name]]' 				=> $meta['name'],
					'[[/webpage'. $entry['uri']. '/title]]' 				=> $meta['title'],
					'[[/webpage'. $entry['uri']. '/description]]' => $meta['description'],
				] );
			}
		}

		/**
		 *	A template's own text fragment isn't expected to declare
		 *	/webpage/&lt;name&gt;/* meta anymore (that's this class's job, see
		 *	_applyWebpage()) - filtered out defensively rather than trusted,
		 *	so a library fork that still ships one doesn't leave a stale,
		 *	folder-name-keyed key permanently behind
		 *
		 *	@param		array 		$fragment
		 *
		 *	@return 	array
		 */
		private static function _withoutWebpageMeta( array $fragment ): array {
			return array_filter( $fragment, fn( string $key ): bool => str_starts_with( $key, '[[/webpage/' ) === false, ARRAY_FILTER_USE_KEY );
		}

		/**
		 *	Apply a required module's own templates/blacklist/text - same
		 *	shape as Setup::_applyUnit(), duplicated rather than shared for
		 *	the usual reason every class in this file duplicates a small
		 *	helper instead of reaching into a sibling's internals. Routes
		 *	are deliberately not handled here: no module in the library
		 *	currently declares any (they self-register at boot instead, or
		 *	have none), and a module a template requires has no uri of its
		 *	own to generate one for anyway
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitDir			Absolute path to the module unit (modules/<key>)
		 *	@param		array 		$locales			Picked locales
		 *	@param		array 		&$blacklist		(reference) Collected blacklist keys, appended to
		 *
		 *	@return 	void
		 */
		private static function _applyModule( array &$appData, string $unitDir, array $locales, array &$blacklist ): void {

			$manifest = self::_readManifest( $unitDir ) ?? [];

			if( count( $manifest['templates'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/templates' );
				foreach( $manifest['templates'] as $locale => $file ) {
					if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
						continue;
					self::_copyFile( $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ) );
				}
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

			// A unit's own config defaults - only filled in where the project
			// has nothing yet, never overwritten: re-applying a unit must not
			// reset a value the developer has edited since
			foreach( ( $manifest['config'] ?? [] ) as $configKey => $configValue )
				if( isset( $appData[$configKey] ) === false ) {
					$appData[$configKey] = $configValue;
					\Nino\AppData::writeContentData( $appData, [ $configKey ] );
				}

			$globalFragment = $unitDir. '/text/global.php';
			if( is_file( $globalFragment ) === true )
				self::_mergeText( $appData, '/text/global.php', include $globalFragment );

			foreach( $locales as $locale ) {
				$localeFragment = $unitDir. '/text/'. $locale. '.php';
				if( is_file( $localeFragment ) === true )
					self::_mergeText( $appData, '/text/'. $locale. '.php', include $localeFragment );
			}
		}

		/**
		 *	The cookie banner and html-footer-legal.tpl's own footer link
		 *	(see _install/library/base/templates/html-footer.tpl and
		 *	pages/legal/templates/html-footer-legal.tpl) need somewhere to
		 *	read "the legal page's real, reachable uri/name" from that isn't
		 *	tied to a fixed uri - whichever entry actually uses the "legal"
		 *	template (if any) is mirrored into these well-known keys, its
		 *	httpUri (not its Element-URI - this becomes an actual href).
		 *	Only ever set, never cleared: if no entry uses "legal" (yet),
		 *	those two footer fragments are a known v1 limitation, see
		 *	docs/_install.md
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$webpages			The just-applied, current webpages list
		 *	@param		array 		$locales			Picked locales
		 *
		 *	@return 	void
		 */
		private static function _applyLegalLink( array &$appData, array $webpages, array $locales ): void {

			$legalEntry = null;
			foreach( $webpages as $entry )
				if( self::_libraryKey( $entry ) === 'legal' ) { $legalEntry = $entry; break; }

			if( $legalEntry === null )
				return;

			self::_mergeText( $appData, '/text/global.php', [ '[[/website/legal/uri]]' => $legalEntry['httpUri'] ] );

			foreach( $locales as $locale ) {
				$name = $legalEntry['text'][$locale]['name'] ?? self::DEFAULT_TEXT['name'];
				self::_setText( $appData, '/text/'. $locale. '.php', '[[/website/legal/name]]', $name );
			}
		}

		/**
		 *	Merge a text fragment's keys into a /text/*.php file - later
		 *	units win a key collision
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					Filesystem-relative path, eg. '/text/de_DE.php'
		 *	@param		array 		$fragment			Bracket-key => value pairs to merge in
		 *
		 *	@return 	void
		 */
		private static function _mergeText( array &$appData, string $path, array $fragment ): void {
			\Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $fragment ): array {
				return array_merge( $content, $fragment );
			} );
		}

		/**
		 *	Set (overwrite) a single text key - unlike _mergeText(), used
		 *	only for fully-derived content that has to track its source on
		 *	every apply() rather than merge on top of a possibly-stale
		 *	previous value (see _applyLegalLink())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					Filesystem-relative path, eg. '/text/de_DE.php'
		 *	@param		string		$bracketKey		Eg. '[[/website/legal/name]]'
		 *	@param		string		$value
		 *
		 *	@return 	void
		 */
		private static function _setText( array &$appData, string $path, string $bracketKey, string $value ): void {
			\Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $bracketKey, $value ): array {
				$content[$bracketKey] = $value;
				return $content;
			} );
		}

		/**
		 *	Copy one library file into the project - see
		 *	Setup::_copyFile()'s docblock, duplicated here for the same
		 *	reason every class in this file duplicates a small helper
		 *	instead of reaching into a sibling's internals
		 *
		 *	@param		string		$from
		 *	@param		string		$to
		 *
		 *	@return 	void
		 */
		private static function _copyFile( string $from, string $to ): void {

			$content = @file_get_contents( $from );
			if( $content === false )
				return;

			if( is_file( $to ) === true )
				@unlink( $to );
			
			if( is_dir( dirname( $to ) ) === false )
				@mkdir( dirname( $to ), 0755, true );

			file_put_contents( $to, $content );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 8: bulk-fill the handful of "Personal Infos" keys every
	 *												project has regardless of what Setup/Webpages picked -
	 *												/company/* and /website/* (company/contact details,
	 *												the site's author/hosting info), each with a friendly
	 *												label instead of its raw key. Everything else - technical/
	 *												design-token keys (text/blacklist.php), a webpage's own
	 *												name/title/description (Webpages already covers that), and
	 *												any deeper module/page content - is left out here on
	 *												purpose: it's fine as the library's own generic default,
	 *												and if it isn't, that's a job for _editor's Text panel
	 *												afterward, not a one-size-fits-all bulk step.
	 *												Thin wrapper around \Nino\Text - all the validation/
	 *												sanitizing/locking already lives there (shared with
	 *												_editor and _admin)
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class PersonalInfos {

		// _install/library/base/text/*.php - the same directory Setup's
		// "base" unit reads. Duplicated as a literal path rather than
		// referencing Setup::LIBRARY (private) - keeps this class free-
		// standing, same reasoning every other class in this file
		// duplicates a small helper instead of reaching into a sibling's
		// internals
		private const string BASE_TEXT_DIR = __DIR__. '/library/base/text';

		// Only these two prefixes are in scope - see this class's docblock
		private const array KEY_PREFIXES = [ '/company/', '/website/' ];

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
		 *	List "base"'s /company/* and /website/* text keys with their
		 *	current value(s) and a friendly label - everything else (see
		 *	this class's docblock, including blacklisted/technical keys) is
		 *	left out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			$keys = self::_keys();

			$entries = array_values( array_filter(
				\Nino\Text::entries( $appData, false ),
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
		 *	Every /company/* and /website/* key base/text/global.php and
		 *	base/text/<locale>.php declare, stripped of their surrounding
		 *	[[ ]] - what apiList() filters \Nino\Text::entries() down to
		 *
		 *	@return 	array			key -> true, for isset() lookups
		 */
		private static function _keys(): array {

			$keys = [];

			foreach( [ 'global.php', 'de_DE.php', 'en_US.php' ] as $file ) {

				$path = self::BASE_TEXT_DIR. '/'. $file;
				if( is_file( $path ) === false )
					continue;

				foreach( array_keys( include $path ) as $bracketKey ) {

					$key = trim( $bracketKey, '[]' );

					foreach( self::KEY_PREFIXES as $prefix )
						if( str_starts_with( $key, $prefix ) === true )
							$keys[$key] = true;
				}
			}

			return $keys;
		}

		/**
		 *	'/company/adress' -> 'Company Adress', '/website/author' ->
		 *	'Website Author' - every segment capitalized, joined with a
		 *	space, no hardcoded per-key lookup table to keep in sync with
		 *	the library's own key names
		 *
		 *	@param		string		$key
		 *
		 *	@return 	string
		 */
		private static function _label( string $key ): string {
			return implode( ' ', array_map( 'ucfirst', array_filter( explode( '/', $key ) ) ) );
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

			\Nino\Http::ok( $request, [ 'results' => \Nino\Text::saveBatch( $appData, $items, false ) ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 9: create the first _editor account(s), the same way
	 *												\Nino\Admin\Users bootstraps them from inside _admin - duplicated
	 *												rather than depended on, since /_install is meant to work even
	 *												before a developer has decided whether to keep _admin around
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		// The shipped, unusable-password placeholder account from config.php -
		// dropped the moment a real admin account is created, so it doesn't
		// linger as a permanent dead entry in '/nino/auth/user'
		private const string PLACEHOLDER_MAIL = 'changeme@domain.com';

		private const int MIN_PW_LENGTH = 8;

		/**
		 *	This module's action map, merged into Install::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'admin/list' 		=> [ self::class, 'apiList' ],
				'admin/create' 	=> [ self::class, 'apiCreate' ],
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
		 *	Every account that can actually pass Auth's login-status gate. Disabled
		 *	legacy placeholders must not satisfy the wizard's "at least one admin"
		 *	precondition merely because an array key for them still exists.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array		Mail addresses
		 */
		public static function usableUsers( array &$appData ): array {

			$users = [];

			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user )
				if( is_array( $user ) === true && ( $user['status'] ?? null ) === 2 && is_string( $user['pw'] ?? null ) === true && $user['pw'] !== '' )
					$users[] = (string) $mail;

			return $users;
		}

		/**
		 *	Create (or replace) an admin account with full permissions. Can be
		 *	called more than once in the same wizard run to set up several
		 *	admins - see docs/_install.md
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			$data = \Nino\Install\Install::postData();
			$mail = trim( (string) ( $data['mail'] ?? '' ) );
			$pw 	= (string) ( $data['pw'] ?? '' );

			if( filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid email address' );
				return;
			}

			if( strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			// Re-running this for the same address (eg. fixing a typo'd
			// password) has to replace, not fail on "account already exists" -
			// insertUser() alone won't, so any existing entry at this address
			// is dropped first
			if( isset( $appData['/nino/auth/user'][$mail] ) === true )
				\Nino\Auth::deleteUser( $appData, $mail );

			if( $mail !== self::PLACEHOLDER_MAIL && isset( $appData['/nino/auth/user'][self::PLACEHOLDER_MAIL] ) === true )
				\Nino\Auth::deleteUser( $appData, self::PLACEHOLDER_MAIL );

			\Nino\Auth::insertUser( $appData, $mail, $pw, [ '/*' ] );

			\Nino\Http::ok( $request, [ 'users' => self::usableUsers( $appData ) ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 10 - the wizard's last step: set the real _admin password.
	 *												Writes it under the private directory rather than into any
	 *												tool folder (see Install::setDevPassword()), and is the one
	 *												action whose success is itself what locks /_install back out -
	 *												Admin::isInstalled() goes true the moment this returns
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Finish {

		private const int MIN_PW_LENGTH = 8;

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
		 *	password yet would lock its operator out of both areas at once
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiComplete( array &$appData, array &$request ): void {

			$data = \Nino\Install\Install::postData();
			$pw 	= (string) ( $data['password'] ?? '' );

			if( count( \Nino\Install\Admin::usableUsers( $appData ) ) === 0 ) {
				\Nino\Http::fail( $request, 409, 'create at least one active _editor account first' );
				return;
			}

			if( strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			if( \Nino\Install\Install::setDevPassword( $appData, $pw ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write '. \Nino\Admin\Admin::PASSWORD_PATH. ' under the private directory - check its file permissions' );
				return;
			}

			$appData['/nino/install/completed'] = true;

			\Nino\AppData::writeContentData( $appData, [ '/nino/install/completed' ] );

			\Nino\Http::ok( $request );
		}
	}
}
