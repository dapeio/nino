<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	AppData				Loading config.php at boot, and selectively writing individual top-level keys back
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// AppData - loading config.php at boot, and selectively writing individual
	// top-level keys back
	class AppData {

		private static
			$_initialInstance = [
				'./nino/callbacks'							=> [],
				'./nino/filesystem/cache'			=> [],
				'./nino/filesystem/locks'			=> [],
				'./nino/filesystem/path'				=> '',
				'./nino/elements/cache'				=> [],
				'./nino/html/shortcodes'				=> [],
				'./nino/html/fills'						=> [],
				'./nino/html/cache'						=> [],
				'./nino/http/requests'					=> [],
					// Placeholder only, for the window before config.php has been
				// read - Locales::init() replaces it with the project's own
				// native locale (see its docblock) as soon as that is known
				'./nino/locales/current'				=> 'de_DE',
				'./nino/auth/currentUser'			=> [],
			];

		/*	Everything a project does not have to decide. These are framework
			policy, not project state: a value here is the same on every Nino
			site until somebody deliberately changes it, and a config.php that
			omits one is a project that never had an opinion rather than a
			project missing a key.

			Two things follow. A fresh install starts from a working kernel
			with nothing written yet - which is what lets the setup wizard boot before
			a project exists at all - and config.php shrinks to what this
			particular site decided: its locales, its routes, its pages, its
			theme, its accounts.

			The module list is the always-on half only. Form, Navigation and
			Localepicker are units the wizard offers and a project may not
			want, so they are its answer to give (each ships its unit as
			install/ beside its class - see \Nino\Install\Setup::units()).	*/
		public const array DEFAULTS = [
			// The always-on half, and only that: a unit the setup wizard offers as a
			// checkbox must never be listed here, or unchecking it in the
			// wizard changes nothing and the step lies about what it controls.
			// Form, Navigation and Localepicker are such units (each module's
			// own install/ directory) - Setup::apiApply() adds their
			// moduleClass when they are picked, and a page unit that needs one
			// pulls it in through its own requiresModules.
			'/nino/modules'		=> [
				'\\Nino\\Modules\\Assets',
				'\\Nino\\Modules\\Elements',
				'\\Nino\\Modules\\Template',
				'\\Nino\\Modules\\Jstext',
				'\\Nino\\Modules\\Csrf',
				'\\Nino\\Modules\\Images',
				'\\Nino\\Modules\\Cache',
			],
			'/nino/cache/status'		=> false,
			'/nino/cache/ttl'			=> 3600,
			'/nino/cache/blacklist'	=> [],
			'/nino/admin/backups'		=> true,
			'/nino/admin/logs'			=> true,
			'/nino/dir'					=> '',
			// Log by default, never display: a site that wants a stack trace on
			// the page has to ask for it, and asking is a decision worth writing
			// down. See Runtime::handleError().
			'/nino/error/log'			=> true,
			'/nino/error/display'		=> false,
			'/nino/session/force-secure-cookie'	=> false,
			'/nino/locales/native'		=> 'en_US',
			'/nino/locales/available'	=> [ 'en_US' ],
			'/nino/locales/textfiles'	=> '/text',
			'/nino/auth/maxtries'		=> 5,
			'/nino/auth/cooldown'		=> 3600,
			// The four registries the tools fill. Empty is the honest starting
			// value for all of them: no bundle, no image slots, no menus, no
			// accounts, no routes - a kernel that boots and serves a 404
			'/nino/html/assets'		=> [
		    '/.cache/style.css' => [
		      '/_nino/Nino.css',
		    ],
		    '/.cache/script.js' => [
		      '/_nino/Nino.js',
		      '/_nino/Nino.ui.js',
		    ],
		  ],
			'/nino/html/images'		=> [],
			'/nino/html/navs'			=> [
		    'main',
		    'footer',
			],
			'/nino/http/routes'		=> [],
			'/nino/auth/user'			=> [],
			'/nino/auth/roles'		=> [],
			// What the Features panel records per installed feature: the
			// version it activated and the settings it saved (see
			// \Nino\Features). Empty until the first activation
			'/nino/features'			=> [],
			// Where the Features panel loads the catalogue from when asked, and
			// the key it has to be signed with - '' switches the catalogue off,
			// the key '' falls back to the one the kernel ships (see
			// \Nino\Catalogue). Nothing here is fetched on its own
			'/nino/catalogue/url'	=> \Nino\Catalogue::DEFAULT_URL,
			'/nino/catalogue/key'	=> '',
			// Modules\Maintenance's one switch and its Retry-After seconds -
			// present regardless of whether the module's class is part of a
			// given delivery, same as every other default here. Appended at
			// the end rather than beside the cache keys it is modelled on:
			// phpstan's inferred literal shape of this array is part of
			// phpstan-baseline.neon's message for a finding lower in this
			// file, and an insertion nearer the front reflows which keys
			// that message truncates to, turning an unrelated existing
			// finding into an apparently new one
			'/nino/maintenance/status'	=> false,
			'/nino/maintenance/retry'	=> 3600,
		];

		public static function prepare( array &$appData ): void {

			$appData = self::_merge( self::$_initialInstance, $appData );
		}


		// Reads the '/nino/session/' keys out of config.php ahead of the
		// regular init(), for the one consumer that runs before it:
		// Runtime::init() starts the php session, and a session cookie's
		// flags are set once at session_start() time. Reading them from an
		// appData that hasn't seen config.php yet meant
		// '/nino/session/force-secure-cookie' was always missing and always
		// fell back to false - so the option existed but could never take
		// effect, on exactly the tls-terminating-proxy setup it was built
		// for (no $_SERVER['HTTPS'], secure flag never set).
		// Deliberately narrow: only these keys, no merge of anything else -
		// init() below stays the single place the config is actually loaded.
		// A missing/unreadable config.php is not diagnosed here either; that
		// is init()'s job and its error message is the better one.
		public static function prepareSession( array &$appData ): void {

			$staticAppData = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			if( is_array( $staticAppData ) === false )
				return;

			foreach( $staticAppData as $key => $value )
				if( is_string( $key ) === true && str_starts_with( $key, '/nino/session/' ) === true )
					$appData[$key] = $value;
		}

		/**
		 *	Load config.php over the framework defaults.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$installing		True only from the workbench's entry points - see \Nino\init()
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData, bool $installing = false ): void {

			// getFileContent()'s $default is itself [], so an is_array() check
			// alone never caught a missing file - checked directly here so a
			// typo'd NINO_CONFIG_DIR fails loud instead of silently booting empty
			if( \Nino\Filesystem::fileExists( $appData, '/config.php' ) === false ) {

				/*	No project here yet. The wizard is the one caller that has
					to run before one exists, so it - and only it - boots on the
					defaults alone and writes the file at the end of its Setup
					step.

					Everything else fails, and fails the way it already did:
					Runtime::handleError() cannot know '/nino/error/display'
					this early, so it defaults to not displaying and the visitor
					gets a bare 500. That is the intended answer. A passing
					stranger who finds an unfinished install must not be told
					where the installer is - a 500 says nothing, where "not
					installed yet, go to /_admin" is an invitation. The person
					who *is* installing came to /_admin on purpose and never
					sees this.	*/
				if( $installing !== true )
					trigger_error( 'AppData::init(): config.php was not found under \''. ( $appData['./nino/filesystem/configpath'] ?? $appData['./nino/filesystem/path'] ). '\' - run the setup wizard at /_admin, or check NINO_CONFIG_DIR if it is set.', E_USER_ERROR );

				$appData = self::_merge( $appData, self::DEFAULTS );
				return;
			}

			$staticAppData = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			if( is_array( $staticAppData ) === false )
				trigger_error( 'AppData::init(): config.php exists but did not return an array.', E_USER_ERROR );

			/*	Defaults first, the project's own file over them: a key the file
				does not carry is one this site never decided, not one it lost.
				That is what lets config.php hold only what this project chose -
				and what keeps every config.php written before the defaults
				existed working unchanged, since it simply overrides each of
				them with the same value.	*/
			$appData = self::_merge( $appData, self::_merge( self::DEFAULTS, $staticAppData ) );
		}

		// Merges $overlay into $base: associative arrays merge key-by-key
		// recursively; a plain list (array_is_list(), eg. '/nino/modules')
		// is replaced wholesale; anything else is overwritten by $overlay.
		// Unlike array_merge_recursive(), which turns conflicting scalars
		// into an array and appends list sub-arrays instead of replacing -
		// a module writing a persistent key that already exists in the
		// defaults would otherwise silently become an array, discovered
		// far from here.
		private static function _merge( array $base, array $overlay ): array {

			foreach( $overlay as $key => $value ) {

				$baseValue = $base[$key] ?? null;
				$bothMergeableArrays = is_array( $value ) === true && array_is_list( $value ) === false
					&& is_array( $baseValue ) === true && array_is_list( $baseValue ) === false;

				$base[$key] = ( $bothMergeableArrays === true ) ? self::_merge( $baseValue, $value ) : $value;
			}

			return $base;
		}

		// Persists only the given top-level keys into config.php, not the
		// whole in-memory appData - which is loaded once at boot, so a full
		// re-serialize would silently discard whatever a concurrent request
		// (a second admin session, Auth's failed-attempt tracking) wrote in
		// the meantime. mutate() re-reads fresh under the lock for the same
		// reason; this just merges the given keys into that copy.
		//
		// A failed lock here means a deployment problem (no /data/.locks, no
		// free handles), not contention - unlike every other mutate() caller,
		// this trigger_error()s instead of failing silently, since a dropped
		// config.php write can mean a route, permission, or login never
		// actually saved.
		//
		// Returns whether the write happened - under Nino's own error handler the
		// E_USER_ERROR above already ended the request, so the false only ever
		// reaches a caller running with a handler that continues (the tests).
		public static function writeContentData( array &$appData, array $keys ): bool {

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				trigger_error( 'AppData::writeContentData(): could not lock config.php for writing - refusing to write unserialized.', E_USER_ERROR );
				return false;
			}

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', function( array $content, array &$appData ) use ( $keys ): array {

				foreach( $keys as $key )
					$content[$key] = ( $key === '/nino/auth/user' )
						? self::_mergeAuthUsers( $appData['./nino/auth/baseline'] ?? [], $content[$key] ?? [], $appData[$key] ?? [], $appData['./nino/auth/revoked'] ?? [] )
						: ( $appData[$key] ?? null );

				return $content;
			} );

			if( $written === false )
				trigger_error( 'AppData::writeContentData(): failed to write config.php.', E_USER_ERROR );

			return $written !== false;
		}

		// Three-way merge for '/nino/auth/user' sessions - two parallel
		// logins both write the whole key from their own stale copy, so
		// re-reading alone isn't enough; whoever writes second would
		// otherwise drop the other's session.
		// $baseline = sessions at boot, $onDisk = current file, $inMemory =
		// this request's own decision. A token on disk but missing from both
		// baseline and memory is someone else's parallel login and is kept;
		// one that was in baseline but is gone from memory was deliberately
		// removed and stays removed.
		// Only sessions are merged, never the rest of a user's record.
		// $revoked (mail => true) overrides the "keep what we never saw"
		// rule for a request that means to end every session - a password
		// change or "log out everywhere" (Auth::updateUser()/
		// logoutAllSessions()) must not resurrect a token a parallel login
		// created in the meantime.
		private static function _mergeAuthUsers( array $baseline, array $onDisk, array $inMemory, array $revoked = [] ): array {

			foreach( $inMemory as $mail => $user ) {

				if( is_array( $user['sessions'] ?? null ) === false || is_array( $onDisk[$mail]['sessions'] ?? null ) === false )
					continue;

				// Nothing to carry over for a user this request revoked
				if( ( $revoked[$mail] ?? false ) === true )
					continue;

				$baseSessions = $baseline[$mail]['sessions'] ?? [];

				foreach( $onDisk[$mail]['sessions'] as $token => $session )
					if( isset( $baseSessions[$token] ) === false && isset( $user['sessions'][$token] ) === false )
						$inMemory[$mail]['sessions'][$token] = $session;
			}

			return $inMemory;
		}
	}
}
