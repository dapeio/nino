<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Install						Graphical, developer-only setup wizard - all \Nino\Install\* classes
 *												live in this single file, same convention as _nino/Nino.php,
 *												_editor/Editor.php and _admin/Admin.php. Walks a fresh checkout through
 *												environment/permission checks, assembling starting content from
 *												_install/library/ (locales/modules -> routes/templates/text,
 *												an ordered list of actual pages -> routes/templates/text/
 *												navigation), bulk-filling the site's always-present "Personal
 *												Infos" text, creating the first _editor account(s) and finally
 *												setting the real _admin password.
 *
 *												Guarded by \Nino\Admin\Admin::hasDefaultPassword(): this only ever
 *												runs against a checkout that still ships the placeholder _admin
 *												hash. Setting a real one - the wizard's own last step - is what
 *												locks /_install back out, permanently, with no way back in short
 *												of hand-editing _admin/Admin.php's PASSWORD_HASH again. Not part of
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
	 *												behind Admin::hasDefaultPassword(), dispatches POST actions to a
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

			$appData['/nino/http/routes'] += [
				'GET://_install' 	=> [
					'uri' 				=> '/_install',
					'body'				=> '[template /_install/templates/page-wizard]',
					'statusCode'	=> 200
				],
				'POST://_install'	=> [ 'uri' => '/_install' ],
			];

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

			if( \Nino\Admin\Admin::hasDefaultPassword() === false )
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

			if( self::guard( $request ) === false )
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
		public static function guard( array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			if( \Nino\Admin\Admin::hasDefaultPassword() === false ) {
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
		 *	Rewrite _admin/Admin.php's PASSWORD_HASH constant in place - the one
		 *	piece of the wizard that touches PHP source rather than a data
		 *	file, so it doesn't go through \Nino\Filesystem (which only knows
		 *	how to read/write .php files shaped as `<?php return [...];`).
		 *	Used by Finish::apiComplete(); $path is only ever overridden by
		 *	tests/install-smoke.php, against a sandboxed copy of Admin.php -
		 *	there is exactly one real _admin/Admin.php per project, so production
		 *	code never passes it
		 *
		 *	@param		string				$pw					New plaintext _admin password
		 *	@param		string|null		$path				Admin.php to rewrite - defaults to the real one
		 *
		 *	@return 	bool											Whether the file was rewritten
		 */
		public static function setDevPassword( string $pw, ?string $path = null ): bool {

			$path 	??= dirname( __DIR__ ). '/_admin/Admin.php';
			$source = @file_get_contents( $path );

			if( $source === false )
				return false;

			$hash 	= password_hash( $pw, PASSWORD_DEFAULT );
			$count 	= 0;

			// preg_replace_callback rather than preg_replace(): a bcrypt hash's
			// '$2y$10$...' shape is full of '$<digits>' sequences a plain
			// replacement string would read as backreferences ($2, $10, ...)
			// instead of literal characters
			$updated = preg_replace_callback(
				'/private const string PASSWORD_HASH = \'[^\']*\';/',
				function() use ( $hash ): string { return 'private const string PASSWORD_HASH = \''. $hash. '\';'; },
				$source,
				1,
				$count
			);

			if( $updated === null || $count !== 1 )
				return false;

			return self::_writeFileAtomic( $path, $updated );
		}

		// Replace a file's content atomically: write a temp file next to it,
		// then rename() over the target - same reasoning as
		// \Nino\Filesystem::_writeFile(), duplicated here rather than reused
		// since that one is private and shaped around the filesystem-cache
		// abstraction (json/php-array files under the project root), while
		// this rewrites a specific line of PHP source outside it
		private static function _writeFileAtomic( string $path, string $content ): bool {

			$temp 	= $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );

			if( $handle === false )
				return false;

			$written = fwrite( $handle, $content );
			$flushed = fflush( $handle );
			fclose( $handle );

			if( $written === false || $written !== strlen( $content ) || $flushed === false ) {
				@unlink( $temp );
				return false;
			}

			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				return false;
			}

			// Without this, an opcache-enabled webserver keeps serving the old
			// PASSWORD_HASH (and /_install would look un-installed forever)
			// until the cache entry expires or the process restarts
			if( function_exists( 'opcache_invalidate' ) === true )
				opcache_invalidate( $path, true );

			return true;
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
		private const array DIRECTORIES = [
			''				=> true,	// project root - config.php, _admin/Admin.php
			'text'		=> true,
			'images'	=> true,
			'data'		=> false,	// runtime data - newsletter/submissions/logs
			'.cache'	=> false,	// bundled/minified css+js
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

			$root 	= \Nino\Filesystem::getPath( $appData );
			$result = [];

			foreach( self::DIRECTORIES as $rel => $tracked ) {

				$path 	= rtrim( $root. '/'. $rel, '/' );
				$exists = is_dir( $path );

				// A not-yet-created directory (data/, .cache/) is fine as long as
				// its parent (always the project root here) can create it on
				// first write - only an already-existing one has to be writable itself
				$writable = $exists ? is_writable( $path ) : is_writable( $root );

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
	 *												Library format section.
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
		];

		private const string LIBRARY = __DIR__. '/library';

		// Theme stylesheets live in the project's own /assets, not the
		// library - they're a bundled asset per project.json/build, not
		// starter content this wizard assembles. Only the middle segment
		// of 'style.theme.<key>.css' is ever shown/posted
		private const string THEME_DIR = __DIR__. '/library/base/assets';
		private const string THEME_PATTERN = '/^style\.theme\.([a-z0-9-]+)\.css$/';

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
				$unit['active'] = isset( $manifest['moduleClass'] ) === true && in_array( $manifest['moduleClass'], $activeModules, true ) === true;
			}
			unset( $unit );

			\Nino\Http::ok( $request, [
				'locales' 				=> self::AVAILABLE_LOCALES,
				'activeLocales' 	=> $appData['/nino/locales/available'] ?? [],
				'nativeLocale' 		=> $appData['/nino/locales/native'] ?? null,
				'modules' 				=> $modules,
				'themes' 					=> self::_themes(),
				'activeTheme' 		=> self::_currentTheme( $appData ),
			] );
		}

		/**
		 *	Every /assets/style.theme.&lt;key&gt;.css found on disk, key only
		 *	(no label - the key itself, capitalized client-side, is label
		 *	enough for a handful of theme names)
		 *
		 *	@return 	array			Sorted list of theme keys
		 */
		private static function _themes(): array {

			$themes = [];

			foreach( scandir( self::THEME_DIR ) ?: [] as $entry )
				if( preg_match( self::THEME_PATTERN, $entry, $match ) === 1 )
					$themes[] = $match[1];

			sort( $themes );

			return $themes;
		}

		/**
		 *	The theme key currently bundled into '/.cache/style.css' (see
		 *	apiApply()) - null if config.php's '/nino/html/assets' isn't in
		 *	the shape apiApply() itself would have left it in (eg. hand-edited)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null
		 */
		private static function _currentTheme( array &$appData ): ?string {

			foreach( ( $appData['/nino/html/assets']['/.cache/style.css'] ?? [] ) as $file )
				if( preg_match( self::THEME_PATTERN, basename( (string) $file ), $match ) === 1 )
					return $match[1];

			return null;
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

			$writeKeys = [ '/nino/locales/available', '/nino/locales/native', '/nino/modules', '/nino/http/routes' ];

			// Only ever swaps the one bundled theme stylesheet in place -
			// left alone entirely if no (or an unknown) theme was posted,
			// same reasoning as every other field here defaulting to "no
			// change" rather than a forced pick
			$theme = (string) ( $data['theme'] ?? '' );
			if( in_array( $theme, self::_themes(), true ) === true ) {
				$assets = $appData['/nino/html/assets'] ?? [];
				foreach( ( $assets['/.cache/style.css'] ?? [] ) as $i => $file )
					if( preg_match( self::THEME_PATTERN, basename( (string) $file ), $match ) === 1 )
						$assets['/.cache/style.css'][$i] = '/assets/style.theme.'. $theme. '.css';
				$appData['/nino/html/assets'] = $assets;
				$writeKeys[] = '/nino/html/assets';
			}

			\Nino\AppData::writeContentData( $appData, $writeKeys );

			if( count( $blacklist ) > 0 )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( $list, $blacklist ) ) );
				} );

			\Nino\Http::ok( $request, [ 'locales' => $appData['/nino/locales/available'], 'nativeLocale' => $appData['/nino/locales/native'], 'modules' => $modules, 'theme' => self::_currentTheme( $appData ) ] );
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
			$root 		= \Nino\Filesystem::getPath( $appData );

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
					self::_copyFile( $unitDir. '/templates/'. $file, $root. '/templates/'. $file );
				}
			}

			if( count( $manifest['files'] ?? [] ) > 0 ) {
				foreach( $manifest['files'] as $file )
					if( is_dir( $unitDir. '/'. $file ) === true )
						\Nino\Filesystem::copyDir( $unitDir. '/'. $file, $root. '/'. $file );
					else if( is_file( $unitDir. '/'. $file ) === true )
						self::_copyFile( $unitDir. '/'. $file, $root. '/'. $file );
			}

			if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/elements' );
				foreach( $manifest['elementTypes'] as $file )
					self::_copyFile( $unitDir. '/'. $file, $root. '/elements/'. $file );
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

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

			file_put_contents( $to, $content );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 3: build the project's actual pages - a free-form,
	 *												ordered list of { uri, httpUri, template, nav, text }
	 *												entries a developer adds/reorders/removes here, rather
	 *												than a fixed checkbox per _install/library/pages/&lt;key&gt;
	 *												unit. "template" picks which library page bundle's
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
	 *												The list itself - not the routes/text/templates it produces
	 *												- is the persisted source of truth (see apiApply()'s
	 *												docblock), stored at '/nino/install/webpages'. That array
	 *												is shared, not exclusive to this class: _admin/Admin.php's
	 *												PageEditor manages the same list independently (its own
	 *												standalone copy of this shape/logic, restricted to
	 *												templates already on disk rather than library units) for
	 *												once this folder has been deleted - see its own docblock
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Webpages {

		private const string LIBRARY = __DIR__. '/library';

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
		 *	directory) and the current, persisted webpages list - state
		 *	restoration, same reasoning as Setup::apiLibrary()'s docblock:
		 *	apply() replaces the whole list, so the frontend has to be able
		 *	to show what's already there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			\Nino\Http::ok( $request, [
				'templates' 	=> self::_templates(),
				'locales' 		=> $appData['/nino/locales/available'] ?? [],
				'webpages' 		=> $appData['/nino/install/webpages'] ?? [],
				'navModule' 	=> in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ),
			] );
		}

		/**
		 *	@return 	array			folder name -> { label, requiresModules }
		 */
		private static function _templates(): array {

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
		 *	Validate and persist the posted list, then batch-regenerate
		 *	routes/templates/text/blacklist/modules from it - in that order,
		 *	entry by entry, so the response ("Applied N pages") reflects
		 *	exactly what actually landed.
		 *
		 *	The list itself is a full replace, the same reasoning as
		 *	Setup::apiApply()'s docblock: whatever's posted is the complete,
		 *	authoritative picture. Routes take the same care Setup's own
		 *	replace does, but scoped more narrowly - rather than stripping
		 *	every route key any template in the library could ever produce
		 *	(which, since an entry's httpUri - not a fixed template name -
		 *	now drives the route key, isn't even a fixed set), this only
		 *	strips the route keys the *previous* persisted list would have
		 *	produced. A hand-added route (via _admin's Config module) or one
		 *	Setup's own base/modules own is never among those, so it
		 *	survives untouched.
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

			$webpages 		= [];
			$seenUris 		= [];
			$seenHttpUris = [];

			foreach( $posted as $item ) {

				$uri 			= self::_normalizeUri( (string) ( $item['uri'] ?? '' ) );
				$httpUri 	= self::_normalizeUri( (string) ( $item['httpUri'] ?? '' ) );

				// 'libraryKey' names the _install/library/pages unit this
				// entry starts from - this class's own field. An entry
				// /_admin's Pages module created carries none (there is no
				// library over there, see Admin.php's PageEditor), just the
				// route it already owns; those pass through untouched below
				// rather than being rejected, so the shared list really is
				// shared in both directions. A config.php written before the
				// persisted shape grew this field still names its unit in
				// 'template', so fall back to that when it resolves
				$libraryKey = (string) ( $item['libraryKey'] ?? '' );
				if( $libraryKey === '' && isset( $templates[ (string) ( $item['template'] ?? '' ) ] ) === true )
					$libraryKey = (string) $item['template'];

				$body = (string) ( $item['body'] ?? '' );

				if( $uri === null ) {
					\Nino\Http::fail( $request, 400, 'invalid uri: "'. ( (string) ( $item['uri'] ?? '' ) ). '"' );
					return;
				}

				if( $httpUri === null ) {
					\Nino\Http::fail( $request, 400, 'invalid http uri: "'. ( (string) ( $item['httpUri'] ?? '' ) ). '"' );
					return;
				}

				if( $libraryKey !== '' && isset( $templates[$libraryKey] ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown template: "'. $libraryKey. '"' );
					return;
				}

				if( $libraryKey === '' && $body === '' ) {
					\Nino\Http::fail( $request, 400, 'unknown template: "'. ( (string) ( $item['template'] ?? '' ) ). '"' );
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
				// only inside _applyWebpage(), so 'body'/'statusCode' - and
				// the on-disk template name derived from the body - land in
				// the persisted entry too. /_admin's Pages module reads exactly
				// those three (see docs/_admin.md's shared-shape note): left
				// implicit in the route, they'd be unrecoverable from the
				// list alone, and reopening the entry over there would
				// silently reset eg. the 404 page's status code to 200
				$route 			= $libraryKey !== '' ? self::_unitRoute( $libraryKey, $locales ) : null;
				$routeBody 	= $route !== null ? (string) ( $route['body'] ?? '' ) : $body;
				$statusCode = $route !== null
					? (int) ( $route['statusCode'] ?? 200 )
					: (int) ( $item['statusCode'] ?? 200 );

				$webpages[] = [
					'uri' 				=> $uri,
					'httpUri' 		=> $httpUri,
					'template' 		=> self::_templateFromBody( $routeBody ) ?? '',
					'libraryKey' 	=> $libraryKey,
					'nav' 				=> (bool) ( $item['nav'] ?? false ),
					'statusCode' 	=> $statusCode,
					'body' 				=> $routeBody,
					'text' 				=> $text,
				];
			}

			// The previous list's own route keys - computed before
			// $appData['/nino/install/webpages'] is overwritten below -
			// are exactly what has to come out of $routes before this
			// call's own entries go back in (see this method's docblock)
			$config 		= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$oldWebpages = $config['/nino/install/webpages'] ?? [];
			$routes 		= $config['/nino/http/routes'] ?? [];

			foreach( $oldWebpages as $oldEntry )
				foreach( self::_routeKeys( $oldEntry ) as $routeKey )
					unset( $routes[$routeKey] );

			$appData['/nino/install/webpages'] = $webpages;

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

			\Nino\AppData::writeContentData( $appData, [ '/nino/install/webpages', '/nino/modules', '/nino/http/routes' ] );

			if( count( $blacklist ) > 0 )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( $list, $blacklist ) ) );
				} );

			self::_applyLegalLink( $appData, $webpages, $locales );
			self::_applyNavigation( $appData, $webpages, $locales );

			\Nino\Http::ok( $request, [ 'webpages' => $webpages ] );
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
		 *	one at all. 'template' is only read as a fallback for a
		 *	config.php written before the persisted shape grew its own
		 *	'libraryKey' field - and only when it actually resolves to a
		 *	unit, so /_admin's own on-disk template names (page-404, ...) in
		 *	that same field are never mistaken for one
		 *
		 *	@param		array 		$entry				One webpages-list entry
		 *
		 *	@return 	string|null							Null if no library unit backs this entry
		 */
		private static function _libraryKey( array $entry ): ?string {

			foreach( [ $entry['libraryKey'] ?? '', $entry['template'] ?? '' ] as $candidate ) {
				$candidate = (string) $candidate;
				if( $candidate !== '' && is_dir( self::LIBRARY. '/pages/'. $candidate ) === true )
					return $candidate;
			}

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
				$root 		= \Nino\Filesystem::getPath( $appData );
				$route 		= self::_unitRoute( $libraryKey, $locales );

				if( $routeKey !== null && $route !== null ) {
					$route['uri'] 			= $entry['uri'];
					$routes[$routeKey] = $route;
				}

				if( count( $manifest['templates'] ?? [] ) > 0 ) {
					\Nino\Filesystem::forceDir( $appData, '/templates' );
					foreach( $manifest['templates'] as $locale => $file ) {
						if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
							continue;
						self::_copyFile( $unitDir. '/templates/'. $file, $root. '/templates/'. $file );
					}
				}

				if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
					\Nino\Filesystem::forceDir( $appData, '/elements' );
					foreach( $manifest['elementTypes'] as $file )
						self::_copyFile( $unitDir. '/'. $file, $root. '/elements/'. $file );
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
			$root 		= \Nino\Filesystem::getPath( $appData );

			if( count( $manifest['templates'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/templates' );
				foreach( $manifest['templates'] as $locale => $file ) {
					if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
						continue;
					self::_copyFile( $unitDir. '/templates/'. $file, $root. '/templates/'. $file );
				}
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

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
		 *	Regenerates the site's main-menu text (see
		 *	_install/library/modules/navigation/templates/html-header-nav.tpl
		 *	and html-footer-nav.tpl's [[/website/navigation/main]] fill) from
		 *	every entry with 'nav' checked, one "httpUri:name" line per entry
		 *	- the exact shape \Nino\Modules\Navigation's [navigation]...
		 *	[/navigation] shortcode already expects. httpUri, not Element-
		 *	URI, since this becomes an actual href. Only runs while the
		 *	Navigation module is active; overwrites rather than merges,
		 *	unlike every other piece of text this class writes - it's fully
		 *	derived from the current list, not something a developer is
		 *	expected to have hand-edited before finishing the wizard
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$webpages			The just-applied, current webpages list
		 *	@param		array 		$locales			Picked locales
		 *
		 *	@return 	void
		 */
		private static function _applyNavigation( array &$appData, array $webpages, array $locales ): void {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return;

			$navEntries = array_values( array_filter( $webpages, fn( array $e ): bool => ( $e['nav'] ?? false ) === true ) );

			foreach( $locales as $locale ) {

				$lines = [];
				foreach( $navEntries as $entry )
					$lines[] = $entry['httpUri']. ':'. ( $entry['text'][$locale]['name'] ?? self::DEFAULT_TEXT['name'] );

				self::_setText( $appData, '/text/'. $locale. '.php', '[[/website/navigation/main]]', implode( PHP_EOL, $lines ) );
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
		 *	previous value (see _applyNavigation()'s docblock)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					Filesystem-relative path, eg. '/text/de_DE.php'
		 *	@param		string		$bracketKey		Eg. '[[/website/navigation/main]]'
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

			file_put_contents( $to, $content );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 4: bulk-fill the handful of "Personal Infos" keys every
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
	 *	Install						Step 5: create the first _editor account(s), the same way
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
			\Nino\Http::ok( $request, [ 'users' => array_keys( $appData['/nino/auth/user'] ?? [] ) ] );
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

			\Nino\Http::ok( $request, [ 'users' => array_keys( $appData['/nino/auth/user'] ?? [] ) ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Install						Step 6 - the wizard's last step: set the real _admin password.
	 *												The only step that writes to _admin/Admin.php's PHP source rather
	 *												than a data file (see Install::setDevPassword()), and the one
	 *												action whose success is itself what locks /_install back out -
	 *												Admin::hasDefaultPassword() goes false the moment this returns
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
		 *	Rewrite _admin/Admin.php's PASSWORD_HASH to the posted password's hash
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiComplete( array &$appData, array &$request ): void {

			$data = \Nino\Install\Install::postData();
			$pw 	= (string) ( $data['password'] ?? '' );

			if( strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			if( \Nino\Install\Install::setDevPassword( $pw ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write _admin/Admin.php - check its file permissions' );
				return;
			}

			\Nino\Http::ok( $request );
		}
	}
}
