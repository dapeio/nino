<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Dev								Dev-only tooling - all \Nino\Admin\* classes live in this single
 *												file, same convention as _nino/Nino.php and _editor/Editor.php.
 *												Meant to be deployed only when actually needed and removed
 *												afterwards (rm -rf _admin) - not part of the shipped admin
 *												dashboard, not linked from it, no config.php registration
 *												required to work (self-registers its route, same as Admin).
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

namespace {

	// Password hash helper - deliberately only reachable via the CLI SAPI,
	// never over HTTP (a webserver never runs PHP as "cli"), so this can stay
	// in the file without being a usable-over-the-web endpoint:
	// `php _admin/Admin.php <pw>`
	//
	// The hash it prints goes into the project's content/.auth/pw.php, wrapped
	// in the stub below - not into this file. Nothing here is project state
	// any more, which is what lets an update replace it (see
	// Admin::passwordHash())
	if( php_sapi_name() === 'cli' && isset( $argv[1] ) === true )
		die( \Nino\Admin\Admin::STUB_PREFIX. password_hash( $argv[1], PASSWORD_DEFAULT ). \Nino\Admin\Admin::STUB_SUFFIX );
}

namespace Nino\Admin {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Bootstraps the dev area: one hardcoded-password session gate
	 *												in front of a small registry of dev-only modules. Add a new
	 *												tool by writing a class with actions()/nav() and listing it
	 *												in MODULES - nothing else here needs to change.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		// Where the real password hash lives, relative to the content
		// directory (see \Nino\Filesystem::getContentPath()). /_install's
		// last wizard step writes it; passwordHash() reads it
		public const string PASSWORD_PATH = '/.auth/pw.php';

		// The login throttle counter, next to the credential it guards. A
		// virtual path (see \Nino\Filesystem::CONTENT_DIR), so it goes
		// through mutate() and keeps its lock - two concurrent wrong attempts
		// must not both read the same "tries" and each write back the same +1
		private const string LOCKOUT_PATH = \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json';

		// Where that counter used to live, inside this tool's own folder.
		// Read once on the next login so an upgrade does not hand an
		// attacker mid-lockout a fresh set of attempts; never written again
		private const string LEGACY_LOCKOUT_PATH = '/_admin/.lockout.json';

		// The stub that file is wrapped in - a php file that 403s and exits
		// before it ever returns the hash, so it stays useless even where a
		// webserver happily serves it. Same convention (and same constants)
		// Backup uses for its key and its archives
		public const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		public const string STUB_SUFFIX = "';\n";

		// Legacy fallback only, for a project installed while the hash still
		// lived in this file. Kept read-only: setDevPassword() no longer
		// rewrites it, and apiLogin() migrates such a project to
		// PASSWORD_PATH on the next successful login. A hash in here made
		// this file carry state, which is why replacing it on an update used
		// to log the operator out *and* hand /_install back to whoever asked
		private const string PASSWORD_HASH = '$2y$10$JeWFJ0CAi.tAEc6i5IcyTOLSgeCbrspmb4p0Re7aWvJBOajXtwgt.';

		// The exact value PASSWORD_HASH ships with, kept separately so it can
		// be compared against rather than duplicated - a checkout still
		// carrying it has no legacy password to fall back to
		public const string DEFAULT_PASSWORD_HASH = '$2y$10$JeWFJ0CAi.tAEc6i5IcyTOLSgeCbrspmb4p0Re7aWvJBOajXtwgt.';

		private const int MAX_TRIES = 5;
		private const int COOLDOWN = 3600;

		private const array MODULES = [
			\Nino\Admin\Dashboard::class,
			\Nino\Admin\ElementTypes::class,
			\Nino\Admin\Elements::class,
			\Nino\Admin\Text::class,
			\Nino\Admin\Translations::class,
			\Nino\Admin\PageEditor::class,
			\Nino\Admin\Navigations::class,
			\Nino\Admin\Images::class,
			\Nino\Admin\Users::class,
			\Nino\Admin\Restore::class,
			\Nino\Admin\Config::class,
		];

		/**
		 *	Register the /_admin route and its response callbacks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// These runtime-only routes are owned by the tool itself and must win
			// over a stale or hand-written config entry at the same uri - the same
			// reasoning (and the same former '+=' bug) as Install::init()'s own
			// routes. '/nino/http/routes' is editable as raw json through this very
			// tool's Config module, so a persisted 'GET://_admin' would otherwise
			// shadow the dashboard and leave no ui path back to the route that did
			// it - locking an operator out of the one tool that could remove it
			$appData['/nino/http/routes']['GET://_admin'] = [
				'uri' 				=> '/_admin',
				'body'				=> '[template /_admin/templates/page-index]',
				'statusCode'	=> 200
			];
			$appData['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_admin', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_admin', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_admin response: the tool dashboard if authed, else the login form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			if( self::isAuthed( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_admin/templates/page-login]';
		}

		/**
		 *	Fill the POST /_admin response. Every dev api request goes through
		 *	this single route, dispatched by $_POST['action'] - same shape as
		 *	Editor::handlePost(), except the login action itself must stay
		 *	reachable before the auth gate, since there is no separate route
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handlePost( array &$appData, array &$request ): void {

			// Respect a rejection from an earlier global callback (eg. Csrf)
			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return;

			$action = $_POST['action'] ?? '';

			if( $action === 'dev/login' ) {
				self::apiLogin( $appData, $request );
				return;
			}

			if( self::isAuthed( $appData ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return;
			}

			if( $action === 'dev/logout' ) {
				self::apiLogout( $appData, $request );
				return;
			}

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
		 *	Whether the current session already passed the password gate
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function isAuthed( array &$appData ): bool {
			return \Nino\Runtime::getSessionValue( $appData, './nino/admin/authed', false ) === true;
		}

		/**
		 *	The password hash currently in effect, or null when this project
		 *	has none yet.
		 *
		 *	Canonically PASSWORD_PATH, under the content directory - never
		 *	config.php: a Restore rewrites config.php, and a credential that
		 *	authorises restoring must not be part of what gets restored (the
		 *	same reasoning Backup already applies to the encryption key, see
		 *	Restore::_key()). It is also outside every tool folder, so
		 *	replacing _admin/Admin.php on an update no longer takes the
		 *	password with it.
		 *
		 *	PASSWORD_HASH is the fallback for a project installed before that
		 *	file existed - apiLogin() migrates such a project across on the
		 *	next successful login, after which the constant is dead weight
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							Null when no password has been set at all
		 */
		public static function passwordHash( array &$appData ): ?string {

			$stored = self::_readPasswordFile( $appData );

			if( $stored !== null )
				return $stored;

			return self::PASSWORD_HASH !== self::DEFAULT_PASSWORD_HASH ? self::PASSWORD_HASH : null;
		}

		/**
		 *	Whether this project has been through /_install already - the one
		 *	thing /_install checks before it lets itself run at all.
		 *
		 *	Deliberately not "a password exists": that alone would mean
		 *	deleting one file re-opens the installer on a live site, which is
		 *	exactly the hazard moving the hash out of the source file is meant
		 *	to remove. The persisted marker is what makes the answer stick, so
		 *	a project missing its password file locks its admin area rather
		 *	than handing out a fresh installer (put the file back by hand -
		 *	the same escape hatch editing the constant used to be)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function isInstalled( array &$appData ): bool {

			if( ( $appData['/nino/install/completed'] ?? false ) === true )
				return true;

			return self::passwordHash( $appData ) !== null;
		}

		/**
		 *	The absolute path of the file passwordHash() reads
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		public static function passwordPath( array &$appData ): string {
			return \Nino\Filesystem::getContentPath( $appData ). self::PASSWORD_PATH;
		}

		/**
		 *	Read the stored hash back out of its self-exiting php stub - the
		 *	same shape (and the same reason) Backup writes its key and its
		 *	archives with: the file sits under a path a webserver may well
		 *	serve, and a stub that 403s before returning anything is the one
		 *	protection that does not depend on .htaccess applying
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							Null if there is no readable, well-formed file
		 */
		private static function _readPasswordFile( array &$appData ): ?string {

			$raw = @file_get_contents( self::passwordPath( $appData ) );

			if( is_string( $raw ) === false )
				return null;

			if( str_starts_with( $raw, self::STUB_PREFIX ) === false || str_ends_with( $raw, self::STUB_SUFFIX ) === false )
				return null;

			$hash = substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) );

			// A truncated or half-written file must read as "no password" and
			// therefore fail every login, never as a hash password_verify()
			// would happily reject in a way that looks like a wrong password
			return $hash !== '' ? $hash : null;
		}

		/**
		 *	Require an authed session - shared by every module's api methods
		 *	(same role as Editor::guard())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool										If the request may proceed
		 */
		public static function guard( array &$appData, array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			if( self::isAuthed( $appData ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return false;
			}

			return true;
		}

		/**
		 *	Check the posted password against the hardcoded hash and open
		 *	the session gate. Rate-limited the same shape as Auth's per-user
		 *	tries/cooldown (see _nino/Nino.php Auth::_registerFailedAttemp()),
		 *	just against one shared counter instead of a per-user record,
		 *	since there's only the one hardcoded password to guess. This
		 *	tool was originally meant to be deployed only briefly, which
		 *	didn't need this - now that a project might reasonably leave it
		 *	up permanently (for Restore, see below), an unlimited-attempts
		 *	single password stops being an acceptable tradeoff
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		private static function apiLogin( array &$appData, array &$request ): void {

			$password 	= (string) ( json_decode( $_POST['data'] ?? '{}', true )['password'] ?? '' );
			$hash 			= self::passwordHash( $appData );
			$lockedOut 	= false;
			$verified 	= false;

			// The cooldown check, password verify and tries update all happen
			// inside one lock: reading the counter, verifying, then writing as
			// three separate steps would let two concurrent wrong attempts both
			// read the same "tries" and both write back the same +1, losing an
			// increment - the exact way to dodge the lockout with two requests
			// instead of one
			\Nino\Filesystem::mutate( $appData, self::LOCKOUT_PATH, function( array $state ) use ( $password, $hash, &$lockedOut, &$verified ): ?array {

				if( (int) $state['until'] > time() ) {
					$lockedOut = true;
					return null;
				}

				// No password at all - a project whose file went missing, or
				// one that never finished /_install. Still run the counter:
				// answering instantly would tell an unauthenticated caller
				// which of the two states this installation is in
				$verified = $hash !== null && password_verify( $password, $hash );

				if( $verified === true )
					return [ 'tries' => 0, 'until' => 0 ];

				$state['tries'] = (int) $state['tries'] + 1;
				if( $state['tries'] >= self::MAX_TRIES ) {
					$state['tries'] = 0;
					$state['until'] = time() + self::COOLDOWN;
				}

				return $state;
			}, self::_lockoutState( $appData ) );

			if( $lockedOut === true ) {
				\Nino\Http::fail( $request, 429, 'too many attempts' );
				return;
			}

			if( $verified === false ) {
				\Nino\Http::fail( $request, 401, 'wrong password' );
				return;
			}

			// A project whose hash still lives in this file's constant moves
			// across on its first successful login, so an update may replace
			// _admin/Admin.php from then on. Done after the verify, not
			// inside the lock above: this writes a different file, and a
			// failure here must not cost the operator the login they just
			// passed - they simply migrate on the next one
			if( self::_readPasswordFile( $appData ) === null )
				self::writePasswordHash( $appData, (string) $hash );

			// Defend against session fixation, same as Auth::loginUser()
			session_regenerate_id( true );
			\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );
		}

		/**
		 *	The counter mutate() starts from when the current file is missing:
		 *	whatever this tool's own folder still holds from before the
		 *	counter moved, so an update cannot be used to clear an active
		 *	lockout by simply replacing that folder
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										{ tries, until }
		 */
		private static function _lockoutState( array &$appData ): array {

			$legacy = \Nino\Filesystem::getFileContent( $appData, self::LEGACY_LOCKOUT_PATH, [] );

			return [
				'tries' => (int) ( $legacy['tries'] ?? 0 ),
				'until' => (int) ( $legacy['until'] ?? 0 ),
			];
		}

		/**
		 *	Write a hash to PASSWORD_PATH, creating the content directory if
		 *	it isn't there yet. The one writer - /_install's finish step goes
		 *	through Install::setDevPassword(), which calls this
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$hash					An already-hashed password, never a plaintext one
		 *
		 *	@return 	bool										False when the file could not be written
		 */
		public static function writePasswordHash( array &$appData, string $hash ): bool {

			if( $hash === '' )
				return false;

			$path = self::passwordPath( $appData );
			$dir 	= dirname( $path );

			// @ throughout, same reasoning Filesystem::forceDir() documents:
			// a concurrent request may have created the directory between the
			// is_dir() and the mkdir()
			if( is_dir( $dir ) === false && @mkdir( $dir, 0777, true ) === false && is_dir( $dir ) === false )
				return false;

			self::_denyDirectory( dirname( $dir ) );

			return self::_writeFileAtomic( $path, self::STUB_PREFIX. $hash. self::STUB_SUFFIX );
		}

		/**
		 *	Write a file's content atomically: a temp file next to it, then
		 *	rename() over the target - so a concurrent reader sees either the
		 *	old credential or the new one, never a half-written file.
		 *
		 *	Own copy rather than \Nino\Filesystem's, which is private and
		 *	shaped around the filesystem cache (php-array files under the
		 *	project root); this one writes a php *stub* under the content
		 *	directory, which that abstraction cannot express
		 *
		 *	@param		string		$path
		 *	@param		string		$content
		 *
		 *	@return 	bool
		 */
		private static function _writeFileAtomic( string $path, string $content ): bool {

			$temp 	= $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );

			if( $handle === false )
				return false;

			$written = fwrite( $handle, $content );
			$flushed = fflush( $handle );
			fclose( $handle );

			// fwrite() reports a short write rather than failing, and without
			// the fflush() a rename() can win the race against the bytes ever
			// reaching disk - either way the result is a truncated hash that
			// would lock the operator out
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

			// passwordHash() reads these bytes directly rather than including
			// them, so opcache never sits in that path - but the file is
			// still php a webserver may compile if it is requested, and
			// leaving a stale compiled copy of a credential file around is
			// not something to rely on being harmless
			if( function_exists( 'opcache_invalidate' ) === true )
				opcache_invalidate( $path, true );

			return true;
		}

		/**
		 *	Drop an Apache deny rule into the content directory if it has
		 *	none. Belt and braces next to the stub inside the file itself:
		 *	the stub is what protects the hash on any webserver, this is what
		 *	keeps the directory from being browsable on the common one. Never
		 *	overwrites an existing file - a deployment may have its own rules
		 *
		 *	@param		string		$dir					Absolute path of the content directory
		 *
		 *	@return 	void
		 */
		private static function _denyDirectory( string $dir ): void {

			$path = $dir. '/.htaccess';

			if( is_dir( $dir ) === false || file_exists( $path ) === true )
				return;

			@file_put_contents( $path, "# Nino content directory - never served, only read by php.\n". "Require all denied\n" );
		}

		/**
		 *	End the session gate
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		private static function apiLogout( array &$appData, array &$request ): void {
			\Nino\Runtime::unsetSessionValue( $appData, './nino/admin/authed' );
		}

		/**
		 *	Decode the json-encoded "data" POST field every module action reads
		 *	its payload from - same shape as Editor::postData(), duplicated
		 *	rather than depending on _editor/Editor.php, since this whole folder
		 *	is meant to work standalone
		 *
		 *	@return 	array
		 */
		public static function postData(): array {
			$data = json_decode( $_POST['data'] ?? '', true );
			return is_array( $data ) ? $data : [];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Manage element types (elements/<type>.php): title + model
	 *												(field definitions) only - never touches a type's actual
	 *												content ('*' and locale buckets), so existing elements are
	 *												never at risk from a save here. Type deletion is deliberately
	 *												not exposed - that would destroy real content; use rm.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class ElementTypes {

		public const array FIELD_TYPES = [ 'string', 'integer', 'double', 'boolean', 'array', 'date', 'datetime', 'image' ];

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'devtypes/list' 	=> [ self::class, 'apiList' ],
				'devtypes/get' 		=> [ self::class, 'apiGet' ],
				'devtypes/save' 	=> [ self::class, 'apiSave' ],
				'devtypes/create' => [ self::class, 'apiCreate' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'types', 'Element-Typen' ];
		}

		/**
		 *	A type uri is always a single flat filename segment (elements/<type>.php,
		 *	no nesting) - reject anything else outright, same defensive spirit as
		 *	Images::process()'s basePath check, before it ever reaches a filesystem call
		 *
		 *	@param		string		$typeUri
		 *
		 *	@return 	bool
		 */
		private static function isValidTypeUri( string $typeUri ): bool {
			return preg_match( '/^[a-z][a-z0-9_-]*$/', $typeUri ) === 1;
		}

		/**
		 *	Every element type with its title and field count, sorted by
		 *	uri - shared by apiList() and Dashboard::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'uri', 'title', 'fieldCount' ], ... ]
		 */
		public static function summaries( array &$appData ): array {

			$types = [];
			foreach( glob( \Nino\Filesystem::getPath( $appData ). '/elements/*.php' ) ?: [] as $file ) {

				$typeUri 	= basename( $file, '.php' );
				$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', [] );

				$types[] = [
					'uri' 				=> $typeUri,
					'title' 			=> $typeData['title'] ?? $typeUri,
					'fieldCount' 	=> count( $typeData['model'] ?? [] ),
				];
			}

			usort( $types, fn( array $a, array $b ) => strcmp( $a['uri'], $b['uri'] ) );

			return $types;
		}

		/**
		 *	List every element type with its title and field count
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, [ 'types' => self::summaries( $appData ), 'fieldTypes' => self::FIELD_TYPES ] );
		}

		/**
		 *	Read one type's title + model
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$typeUri = (string) ( \Nino\Admin\Admin::postData()['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type uri' );
				return;
			}

			$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', false );

			if( $typeData === false ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			\Nino\Http::ok( $request, [
				'uri' 		=> $typeUri,
				'title' 	=> $typeData['title'] ?? $typeUri,
				'model' 	=> $typeData['model'] ?? [],
			] );
		}

		/**
		 *	Validate a posted model definition, dropping anything malformed -
		 *	same rules as Elements::insertElementType(), plus width/height for
		 *	image fields, maxlength for string fields, a fixed unit suffix for
		 *	every type but boolean/image, and a plain string list for options
		 *
		 *	@param		mixed			$model				Posted model, expected array<string,array>
		 *
		 *	@return 	array										Cleaned model
		 */
		private static function cleanModel( mixed $model ): array {

			if( is_array( $model ) === false )
				return [];

			$clean = [];

			foreach( $model as $key => $data ) {

				$key = trim( (string) $key );

				if( $key === '' || is_array( $data ) === false || in_array( $data['type'] ?? '', self::FIELD_TYPES, true ) === false )
					continue;

				$field = [ 'type' => $data['type'] ];

				if( ( $data['locale'] ?? false ) === true )
					$field['locale'] = true;

				if( $data['type'] === 'string' && ( $data['html'] ?? false ) === true )
					$field['html'] = true;

				// Never on an image, whatever was posted: its file is uploaded
				// separately, once the element already exists and has a uri to
				// attach it to (see assets/elements.js's image branch), so a
				// required image could not be satisfied by the very save that
				// creates the element - the type would be impossible to add an
				// element to at all. The frontend stops offering the checkbox
				// for image fields (see assets/elementtypes.js), and this drops
				// it from a hand-written or older model on the next save
				if( $data['type'] !== 'image' && ( $data['required'] ?? false ) === true )
					$field['required'] = true;

				if( $data['type'] === 'image' ) {
					$field['width'] 	= max( 1, (int) ( $data['width'] ?? 0 ) );
					$field['height'] 	= max( 1, (int) ( $data['height'] ?? 0 ) );
				}

				// Only rendered for a string field (elements.js's maxlength+counter and
				// html-editor branches) - 0/absent falls back to DEFAULT_MAXLENGTH client-side
				if( $data['type'] === 'string' ) {
					$maxlength = (int) ( $data['maxlength'] ?? 0 );
					if( $maxlength > 0 )
						$field['maxlength'] = $maxlength;
				}

				// A fixed unit/label shown next to the input (eg. a "price" field's
				// "€") - elements.js applies it to every type except boolean/image
				if( $data['type'] !== 'boolean' && $data['type'] !== 'image' ) {
					$suffix = trim( (string) ( $data['suffix'] ?? '' ) );
					if( $suffix !== '' )
						$field['suffix'] = $suffix;
				}

				if( is_array( $data['options'] ?? null ) === true && count( $data['options'] ) > 0 )
					$field['options'] = array_values( array_map( 'strval', $data['options'] ) );

				$clean[$key] = $field;
			}

			return $clean;
		}

		/**
		 *	Save an existing type's title + model. '*' and every locale
		 *	bucket (the type's actual content) are read back and written
		 *	right along with them - untouched, EXCEPT for a field whose
		 *	locale/global shape just changed, whose stored value(s) are
		 *	migrated via _migrateFieldShape() the same way Text::apiSave()
		 *	already migrates a text key's value(s) on the same kind of
		 *	change. Without that, a field switched to global keeps its old
		 *	per-locale value(s) sitting in the locale buckets - and since
		 *	_cacheElement() merges locale data over '*' data (so a locale
		 *	can legitimately override a global default), that stale locale
		 *	value would keep winning over the new global one forever.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$typeUri = (string) ( $data['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type uri' );
				return;
			}

			// Locking and re-reading through mutate() means the "unknown type"
			// 404 below can return null from inside the callback and let
			// mutate() release the lock itself - unlike the manual lock/read/
			// write this used to be, there is no early-return branch left that
			// could walk away holding the lock (see Filesystem::mutate()'s
			// docblock)
			$notFound 			= false;
			$resultTypeData	= null;

			$written = \Nino\Filesystem::mutate( $appData, '/elements/'. $typeUri. '.php', function( mixed $typeData ) use ( $appData, $data, $typeUri, &$notFound, &$resultTypeData ): ?array {

				if( $typeData === false ) {
					$notFound = true;
					return null;
				}

				$title 		= trim( (string) ( $data['title'] ?? '' ) );
				$oldModel = $typeData['model'] ?? [];
				$newModel = self::cleanModel( $data['model'] ?? [] );

				foreach( $newModel as $key => $field ) {
					if( array_key_exists( $key, $oldModel ) === false )
						continue;
					$wasLocale = ( $oldModel[$key]['locale'] ?? false ) === true;
					$isLocale 	= ( $field['locale'] ?? false ) === true;
					if( $wasLocale !== $isLocale )
						self::_migrateFieldShape( $appData, $typeData, $key, $isLocale );
				}

				$typeData['title'] = ( $title !== '' ) ? $title : ( $typeData['title'] ?? $typeUri );
				$typeData['model'] = $newModel;

				$resultTypeData = $typeData;

				return $typeData;
			}, false );

			if( $notFound === true ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			if( $written === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the type file' );
				return;
			}

			\Nino\Http::ok( $request, [ 'uri' => $typeUri, 'title' => $resultTypeData['title'], 'model' => $resultTypeData['model'] ] );
		}

		/**
		 *	Move one field's stored value(s), across every element of this
		 *	type, between the '*' bucket and every locale bucket - called
		 *	from apiSave() when that field's model 'locale' flag just
		 *	changed. Same migrate-don't-discard reasoning as
		 *	Text::_convertShape(): global -> per-locale copies the current
		 *	'*' value into every locale; per-locale -> global keeps the
		 *	native locale's value (falling back to the first non-empty one)
		 *	and removes the now-stale per-locale copies so they can't keep
		 *	shadowing the new global value in _cacheElement()'s merge
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$typeData		(reference) This type's file content ('*' + locale buckets)
		 *	@param		string		$key					The field key whose shape just changed
		 *	@param		bool			$toLocale			Target shape (true = per-locale, false = global)
		 *
		 *	@return 	void
		 */
		private static function _migrateFieldShape( array &$appData, array &$typeData, string $key, bool $toLocale ): void {

			$locales = \Nino\Locales::getAvailableLocales( $appData );

			$elementUris = array_keys( $typeData['*'] ?? [] );
			foreach( $locales as $locale )
				$elementUris = array_merge( $elementUris, array_keys( $typeData[$locale] ?? [] ) );
			$elementUris = array_unique( $elementUris );

			if( $toLocale === true ) {

				foreach( $elementUris as $elementUri ) {

					if( isset( $typeData['*'][$elementUri][$key] ) === false )
						continue;

					foreach( $locales as $locale ) {
						$typeData[$locale][$elementUri] = $typeData[$locale][$elementUri] ?? [];
						$typeData[$locale][$elementUri][$key] = $typeData['*'][$elementUri][$key];
					}

					unset( $typeData['*'][$elementUri][$key] );
				}

			} else {

				$native = \Nino\Locales::getNativeLocale( $appData );

				foreach( $elementUris as $elementUri ) {

					$value = null;

					if( isset( $typeData[$native][$elementUri][$key] ) === true )
						$value = $typeData[$native][$elementUri][$key];
					else
						foreach( $locales as $locale )
							if( isset( $typeData[$locale][$elementUri][$key] ) === true && $typeData[$locale][$elementUri][$key] !== '' ) {
								$value = $typeData[$locale][$elementUri][$key];
								break;
							}

					if( $value !== null )
						$typeData['*'][$elementUri][$key] = $value;

					foreach( $locales as $locale )
						unset( $typeData[$locale][$elementUri][$key] );
				}
			}
		}

		/**
		 *	Create a brand new, empty element type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$typeUri 	= (string) ( $data['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type uri' );
				return;
			}

			if( \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', '' ) !== '' ) {
				\Nino\Http::fail( $request, 409, 'type already exists' );
				return;
			}

			$title = trim( (string) ( $data['title'] ?? '' ) );

			$typeData = [
				'title' 	=> ( $title !== '' ) ? $title : $typeUri,
				'model' 	=> self::cleanModel( $data['model'] ?? [] ),
				'*' 			=> [ '*' => [] ],
			];

			\Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData );

			\Nino\Http::ok( $request, [ 'uri' => $typeUri, 'title' => $typeData['title'], 'model' => $typeData['model'] ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Restore: lists and restores the encrypted daily backups
	 *											_editor/Editor.php's Backup class creates. Deliberately
	 *											independent of _editor/Editor.php's own code (duplicates the
	 *											small bit of archiving logic it needs, same reasoning as
	 *											postData() above - this whole folder stays standalone) and
	 *											of config.php's own data:
	 *											- the backup directory is *found* by globbing _editor/ for
	 *											  its one-time random name, not read from a config value
	 *											- the decryption key has its own independent copy here
	 *											  (_admin/.restore-key.php), written once by
	 *											  Backup::_bootstrap() the first time it runs
	 *											So this still works even if config.php's *data* (not
	 *											syntax) is what's broken - eg. a wrecked admin user
	 *											record. A genuine config.php syntax error is out of scope:
	 *											_admin boots through the same kernel bootstrap as _editor
	 *											and can't survive that either - that's a manual recovery.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Elements {

		// Same DEFAULT_MAXLENGTH the frontend falls back to - kept here only
		// so the two can't drift apart silently; nothing server-side enforces
		// it (the kernel's own model validation does), it's a form hint
		private const int DEFAULT_MAXLENGTH = 2000;

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'develements/types' 			=> [ self::class, 'apiTypes' ],
				'develements/list' 				=> [ self::class, 'apiList' ],
				'develements/get' 				=> [ self::class, 'apiGet' ],
				'develements/save' 				=> [ self::class, 'apiSave' ],
				'develements/delete' 			=> [ self::class, 'apiDelete' ],
				'develements/uploadimage' => [ self::class, 'apiUploadImage' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'elements', 'Elements' ];
		}

		/**
		 *	List all element types with their model. 'selectedLocale' is the
		 *	native locale rather than a remembered per-session one: _editor
		 *	persists that choice (Editor::sessionLocale) because an editor works
		 *	in one language for a whole session, whereas here it is only the
		 *	locale select's initial value - the pick itself stays client-side
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiTypes( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$types = [];
			foreach( self::types( $appData ) as $type ) {
				$typeData = self::typeData( $appData, $type );
				$types[] = [
					'type' 	=> $type,
					'title' => $typeData['title'] ?? $type,
					'descr' => self::typeDescr( $appData, $type ),
					'model' => $typeData['model'] ?? [],
				];
			}

			\Nino\Http::ok( $request, [
				'types' 					=> $types,
				'locales' 				=> \Nino\Locales::getAvailableLocales( $appData ),
				'selectedLocale' 	=> \Nino\Locales::getNativeLocale( $appData ),
				'maxlength' 			=> self::DEFAULT_MAXLENGTH,
			] );
		}

		/**
		 *	List all elements of a type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$type = (string) ( \Nino\Admin\Admin::postData()['type'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			$model 		= self::typeData( $appData, $type )['model'] ?? [];
			$results 	= \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] );

			$elements = [];
			foreach( $results as $element ) {
				$uri = basename( $element['.uri'] ?? '' );
				$elements[] = [ 'uri' => $uri, 'label' => self::label( $model, $element, $uri ) ];
			}

			\Nino\Http::ok( $request, [ 'model' => $model, 'elements' => $elements ] );
		}

		/**
		 *	Get one element's global fields + per-locale field values, for
		 *	editing - plus, unlike _editor's equivalent, the element's raw
		 *	per-bucket storage exactly as it sits in elements/&lt;type&gt;.php.
		 *	Same reasoning as this tool's Text module seeing blacklisted keys:
		 *	_admin is the developer-facing view, and the resolved form alone
		 *	can't show *which* bucket a value actually came from - a locale
		 *	falling back to '*', or a translation that only looks present
		 *	because another locale supplies it, is invisible otherwise
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				\Nino\Http::fail( $request, 404, 'unknown type or element' );
				return;
			}

			$typeData = self::typeData( $appData, $type );
			$model 		= $typeData['model'] ?? [];
			[ $globalKeys, $localeKeys ] = self::splitModel( $model );

			$global 	= [];
			$locales	= [];
			$found		= false;

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {

				$element = \Nino\Elements::getElement( $appData, '/'. $type. '/'. $uri, $locale, [] );

				if( $element === [] )
					continue;

				$found = true;

				foreach( $globalKeys as $key )
					$global[$key] = $element[$key] ?? null;

				$locales[$locale] = [];
				foreach( $localeKeys as $key )
					$locales[$locale][$key] = $element[$key] ?? null;
			}

			if( $found === false ) {
				\Nino\Http::fail( $request, 404, 'element not found' );
				return;
			}

			\Nino\Http::ok( $request, [
				'model' 	=> $model,
				'global' 	=> $global,
				'locales' => $locales,
				'raw' 		=> self::rawBuckets( $typeData, $uri ),
			] );
		}

		/**
		 *	One element's untouched per-bucket storage: the '*' (global) bucket
		 *	and every locale bucket that actually carries an entry for it.
		 *
		 *	Deliberately not iterating $typeData's keys blindly - a type file
		 *	also has non-bucket top-level keys (its own display 'title', a plain
		 *	string, and 'model'), which is the exact shape that used to crash
		 *	the kernel's own deleteElement()/queryElements() (see their
		 *	docblocks). Anything that isn't an array of entries is skipped.
		 *
		 *	@param		array 		$typeData			Raw elements/<type>.php content
		 *	@param		string		$uri					Bare element uri (eg. "item1")
		 *
		 *	@return 	array										bucket => that bucket's entry for $uri
		 */
		public static function rawBuckets( array $typeData, string $uri ): array {

			$raw = [];

			foreach( $typeData as $bucket => $entries ) {

				if( $bucket === 'model' || is_array( $entries ) === false )
					continue;

				if( array_key_exists( $uri, $entries ) === true )
					$raw[(string) $bucket] = $entries[$uri];
			}

			return $raw;
		}

		/**
		 *	Upload, process and store a new image for one "image"-typed model
		 *	field of an already-saved element - identical contract to _editor's
		 *	own (deterministic path, committed immediately, previous file only
		 *	removed once the new one is safely written and the element record
		 *	actually updated). See _editor/Editor.php's Elements::apiUploadImage()
		 *	for the full reasoning behind the rollback handling below
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUploadImage( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$key 			= (string) ( $data['key'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type, uri or locale' );
				return;
			}

			$model = self::typeData( $appData, $type )['model'] ?? [];
			if( ( $model[$key]['type'] ?? '' ) !== 'image' ) {
				\Nino\Http::fail( $request, 400, 'not an image field' );
				return;
			}

			$elementUri 		= '/'. $type. '/'. $uri;
			$isLocaleField 	= ( $model[$key]['locale'] ?? false ) === true;

			if( \Nino\Elements::getElement( $appData, $elementUri, '*' ) === false ) {
				\Nino\Http::fail( $request, 404, 'element not found - save it once before uploading an image' );
				return;
			}

			if( isset( $_FILES['file'] ) === false || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
				\Nino\Http::fail( $request, 400, 'no file uploaded' );
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				\Nino\Http::fail( $request, 400, 'could not read upload' );
				return;
			}

			$oldElement 	= \Nino\Elements::getElement( $appData, $elementUri, $isLocaleField ? $locale : '*' );
			$oldFilename 	= is_array( $oldElement ) ? ( $oldElement[$key] ?? null ) : null;
			$oldBytes 		= is_string( $oldFilename ) && $oldFilename !== '' ? \Nino\Images::read( $appData, $oldFilename ) : false;

			$width 		= (int) ( $model[$key]['width'] ?? 0 );
			$height 	= (int) ( $model[$key]['height'] ?? 0 );

			$imageFieldCount = 0;
			foreach( $model as $modelField )
				if( ( $modelField['type'] ?? '' ) === 'image' )
					$imageFieldCount++;

			$basePath = 'elements/'. $type. '/'. $uri;
			if( $imageFieldCount > 1 )
				$basePath .= '-'. $key;
			if( $isLocaleField === true )
				$basePath .= '-'. $locale;

			$filename = \Nino\Images::process( $appData, $bytes, $width, $height, $basePath );

			if( $filename === false ) {
				\Nino\Http::fail( $request, 400, 'invalid or oversized image' );
				return;
			}

			$result = \Nino\Elements::updateElement( $appData, $elementUri, [ $key => $filename ], $locale );

			if( is_array( $result ) === false ) {
				if( $filename === $oldFilename && is_string( $oldBytes ) === true )
					\Nino\Images::restore( $appData, $filename, $oldBytes );
				else
					\Nino\Images::delete( $appData, $filename );
				\Nino\Http::fail( $request, 400, 'save failed' );
				return;
			}

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			\Nino\Http::ok( $request, [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ] );
		}

		/**
		 *	Insert or update an element
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$isNew 		= ( $data['isNew'] ?? false ) === true;
			$fields 	= is_array( $data['fields'] ?? null ) ? $data['fields'] : [];

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true || ( $isNew === true && self::validNewUri( $uri ) === false ) || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type, uri or locale' );
				return;
			}

			// A model field with 'html' => true gets the same whitelist-tag
			// sanitizing Text uses - never trust the client's html
			$model = self::typeData( $appData, $type )['model'] ?? [];
			foreach( $fields as $key => $value )
				if( is_string( $value ) === true && ( $model[$key]['html'] ?? false ) === true )
					$fields[$key] = \Nino\Html::sanitizeHtml( $value );

			// Required-field enforcement lives in the kernel itself
			// (Elements::insertElement()/updateElement()) - $errorMsg below
			// already captures its "Missing required element key '...'" message
			$errorMsg = null;
			set_error_handler( function( int $errno, string $errstr ) use ( &$errorMsg ): bool { $errorMsg = $errstr; return true; } );

			$result = ( $isNew === true )
				? \Nino\Elements::insertElement( $appData, '/'. $type. '/'. $uri, $fields, $locale )
				: \Nino\Elements::updateElement( $appData, '/'. $type. '/'. $uri, $fields, $locale );

			restore_error_handler();

			if( is_array( $result ) === false ) {
				\Nino\Http::fail( $request, 400, $errorMsg ?? 'save failed' );
				return;
			}

			\Nino\Http::ok( $request, [ 'element' => $result ] );
		}

		/**
		 *	Delete an element (all locales)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				\Nino\Http::fail( $request, 400, 'invalid type or uri' );
				return;
			}

			$elementUri 		= '/'. $type. '/'. $uri;
			$imageFilenames = self::collectImageFilenames( $appData, $type, $elementUri );

			$errorMsg = null;
			set_error_handler( function( int $errno, string $errstr ) use ( &$errorMsg ): bool { $errorMsg = $errstr; return true; } );

			$deleted = \Nino\Elements::deleteElement( $appData, $elementUri, '*' );

			restore_error_handler();

			if( $errorMsg !== null || $deleted !== true ) {
				\Nino\Http::fail( $request, 409, $errorMsg ?? 'delete vetoed' );
				return;
			}

			// Only once the element itself is actually gone - an "image" field's
			// value is just a filename reference, deleting the element wouldn't
			// otherwise touch the file it points to at all
			foreach( $imageFilenames as $filename )
				\Nino\Images::delete( $appData, $filename );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Every uploaded image filename currently stored on an element - a
		 *	locale-scoped image field can differ per locale, so all of them
		 *	need checking, not just the global bucket
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Element type
		 *	@param		string		$elementUri		Full element uri (eg. "/portfolio/item1")
		 *
		 *	@return 	array										Distinct, non-empty filenames
		 */
		private static function collectImageFilenames( array &$appData, string $type, string $elementUri ): array {

			$model 		= self::typeData( $appData, $type )['model'] ?? [];
			$imageKeys = [];
			foreach( $model as $key => $field )
				if( ( $field['type'] ?? '' ) === 'image' )
					$imageKeys[] = $key;

			if( $imageKeys === [] )
				return [];

			$filenames = [];

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {
				$element = \Nino\Elements::getElement( $appData, $elementUri, $locale, [] );
				foreach( $imageKeys as $key )
					if( is_string( $element[$key] ?? null ) === true && $element[$key] !== '' )
						$filenames[$element[$key]] = true;
			}

			return array_keys( $filenames );
		}

		/**
		 *	Return all element type uris (bare names, eg. "services") found on disk
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										List of type names
		 */
		private static function types( array &$appData ): array {

			$types = [];
			foreach( glob( \Nino\Filesystem::getPath( $appData ). '/elements/*.php' ) ?: [] as $file )
				$types[] = basename( $file, '.php' );

			sort( $types );

			return $types;
		}

		/**
		 *	Read a type's data
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Type name (eg. "services")
		 *
		 *	@return 	array										Raw elements/<type>.php content
		 */
		private static function typeData( array &$appData, string $type ): array {
			return \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
		}

		/**
		 *	Pick a human readable description for a type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Type name (eg. "services")
		 *
		 *	@return 	string									Readable description
		 */
		private static function typeDescr( array &$appData, string $type ): string {

			$elements = \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] );
			$info = '('. count( $elements ). ') ';

			foreach( $elements as $elData )
				$info .= str_replace( '/'. $type, '', $elData['.uri'] ). ', ';

			return ( strlen( $info ) > 150 ) ? substr( $info, 0, 150 ). ' ..' : $info;
		}

		/**
		 *	Pick a human readable label for an element (for the list view)
		 *
		 *	@param		array 		$model				Type model definition
		 *	@param		array 		$element			Resolved element data
		 *	@param		string		$uri					Element uri, used as fallback label
		 *
		 *	@return 	string									Label
		 */
		private static function label( array $model, array $element, string $uri ): string {

			foreach( [ 'title', 'label', 'name' ] as $key )
				if( isset( $element[$key] ) === true && $element[$key] !== '' )
					return (string) $element[$key];

			foreach( $model as $key => $field )
				if( ( $field['type'] ?? '' ) === 'string' && isset( $element[$key] ) === true && $element[$key] !== '' )
					return (string) $element[$key];

			return $uri;
		}

		/**
		 *	Split a model into its global-only and locale-specific field keys
		 *
		 *	@param		array 		$model				Type model definition
		 *
		 *	@return 	array										[ globalKeys[], localeKeys[] ]
		 */
		private static function splitModel( array $model ): array {

			$globalKeys = [];
			$localeKeys = [];

			foreach( $model as $key => $field )
				if( ( $field['locale'] ?? false ) === true )
					$localeKeys[] = $key;
				else
					$globalKeys[] = $key;

			return [ $globalKeys, $localeKeys ];
		}

		// A new element uri becomes both an array key and (for image fields) a
		// filename component. Keep it to the slug syntax the form's own hint
		// promises; existing legacy uris remain readable/deletable.
		private static function validNewUri( string $uri ): bool {
			return preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $uri ) === 1;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Disaster recovery: restore a _editor daily backup.
	 *
	 *											Deliberately independent of config.php's own backup keys:
	 *											- the backup directory is *found* by globbing _editor/ for
	 *											  its one-time random name, not read from a config value
	 *											- the decryption key has its own independent copy here
	 *											  (_admin/.restore-key.php), written once by
	 *											  Backup::_bootstrap() the first time it runs
	 *											So this still works even if config.php's *data* (not
	 *											syntax) is what's broken - eg. a wrecked admin user
	 *											record. A genuine config.php syntax error is out of scope:
	 *											_admin boots through the same kernel bootstrap as _editor
	 *											and can't survive that either - that's a manual recovery.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Restore {

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'restore/list' 		=> [ self::class, 'apiList' ],
				'restore/restore' 	=> [ self::class, 'apiRestore' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'restore', 'Wiederherstellung' ];
		}

		/**
		 *	Find Backup's one random backup directory under _editor/
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false
		 */
		private static function _backupDir( array &$appData ): string|false {
			$matches = glob( \Nino\Filesystem::getPath( $appData ). '/_editor/.backups-*', GLOB_ONLYDIR );
			return $matches[0] ?? false;
		}

		/**
		 *	Read the encryption key's own independent copy under _admin/
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false						Raw (not base64) key bytes
		 */
		private static function _key( array &$appData ): string|false {

			$path = \Nino\Filesystem::getPath( $appData ). '/_admin/.restore-key.php';

			if( is_file( $path ) === false )
				return false;

			$raw = file_get_contents( $path );

			return base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ) );
		}

		/**
		 *	Decrypt one backup/snapshot file's payload
		 *
		 *	@param		string		$path					Absolute path to the .php file
		 *	@param		string		$key					Raw (not base64) 32-byte AES key
		 *
		 *	@return 	string|false						Decrypted tar.gz bytes, or false if decryption failed
		 */
		private static function _decrypt( string $path, string $key ): string|false {

			$raw 			= file_get_contents( $path );
			$payload 	= base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ) );
			$iv 			= substr( $payload, 0, 12 );
			$tag 			= substr( $payload, 12, 16 );
			$cipher 	= substr( $payload, 28 );

			return openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		}

		/**
		 *	Available backup dates, most recent first - shared by
		 *	apiList() and Dashboard::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ "Y-m-d", ... ]
		 */
		public static function dates( array &$appData ): array {

			$dir 	= self::_backupDir( $appData );
			$dates 	= [];

			if( $dir !== false )
				foreach( glob( $dir. '/*.php' ) ?: [] as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$dates[] = basename( $file, '.php' );

			rsort( $dates );

			return $dates;
		}

		/**
		 *	Most recent backup date on file, if any - shared by
		 *	Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							"Y-m-d", or null if none exist yet
		 */
		public static function lastDate( array &$appData ): ?string {
			return self::dates( $appData )[0] ?? null;
		}

		/**
		 *	List available backup dates, most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, [ 'dates' => self::dates( $appData ) ] );
		}

		/**
		 *	Restore one chosen backup date: snapshot the *current* state
		 *	first (see _safetySnapshot()), so a wrong choice is itself
		 *	undoable, then overwrite every file the backup contains
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRestore( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$date = (string) ( \Nino\Admin\Admin::postData()['date'] ?? '' );

			if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid date' );
				return;
			}

			$dir = self::_backupDir( $appData );
			$key = self::_key( $appData );

			if( $dir === false || $key === false ) {
				\Nino\Http::fail( $request, 404, 'no backup available' );
				return;
			}

			$path = $dir. '/'. $date. '.php';

			if( is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown backup date' );
				return;
			}

			$gz = self::_decrypt( $path, $key );

			if( $gz === false ) {
				\Nino\Http::fail( $request, 500, 'decryption failed' );
				return;
			}

			self::_safetySnapshot( $appData, $dir, $key );

			$root 			= \Nino\Filesystem::getPath( $appData );
			$configPath	= \Nino\Filesystem::getConfigPath( $appData );
			$tmpGz 			= tempnam( sys_get_temp_dir(), 'ninorestore' ). '.tar.gz';
			$staging		= sys_get_temp_dir(). '/ninorestore-'. bin2hex( random_bytes( 8 ) );

			file_put_contents( $tmpGz, $gz );
			mkdir( $staging, 0755, true );
			( new \PharData( $tmpGz ) )->extractTo( $staging, null, true );
			unlink( $tmpGz );

			// config.php belongs under configPath, not root - see
			// \Nino\Filesystem::getConfigPath()'s docblock. Extracting it straight
			// to root like every other file would restore it to the wrong place,
			// and under a hardened NINO_CONFIG_DIR setup would additionally leak
			// a stray copy back into the webroot - so it's staged first and moved
			// separately from the rest of the archive.
			$stagedConfig = $staging. '/config.php';

			if( is_file( $stagedConfig ) === true ) {
				file_put_contents( $configPath. '/config.php', file_get_contents( $stagedConfig ) );
				unlink( $stagedConfig );
			}

			// Newsletter: merged, not overwritten - see _mergeNewsletterRestore()
			self::_mergeNewsletterRestore( $root, $staging );

			\Nino\Filesystem::copyDir( $staging, $root );
			\Nino\Filesystem::removeDir( $staging );

			// extractTo()/copyDir() write straight to disk, bypassing Filesystem's own
			// cache tracking entirely - drop it so any getFileContent() call
			// later in this same request (or a request landing in the same
			// wall-clock second - see AppData::writeContentData()'s docblock
			// for why that specifically matters) re-reads the restored files
			// instead of whatever was cached from before the restore
			unset( $appData['./nino/filesystem/cache'] );

			if( function_exists( 'opcache_reset' ) === true )
				opcache_reset();

			\Nino\Http::ok( $request, [ 'ok' => true, 'restoredDate' => $date ] );
		}

		/**
		 *	Rewrite the staged newsletter files in place, before copyDir()
		 *	copies the staging tree over $root, so a restore merges instead of
		 *	overwriting: an address someone removed (self-service unsubscribe
		 *	or an admin delete, see \Nino\Modules\Newsletter's own removal
		 *	record docblock) stays removed no matter how old the backup being
		 *	restored is - Art. 17 (right to erasure), once exercised, must
		 *	survive a later disaster-recovery restore the same way it
		 *	survives everything else.
		 *
		 *	The removal list itself is unioned rather than replaced (a removal
		 *	recorded on either side stays a removal) since $root's own copy
		 *	could itself be the very thing being recovered from and is not to
		 *	be trusted as complete. A resubscribe clears its own address from
		 *	that list already (see \Nino\Modules\Newsletter's own
		 *	_clearRemoval() call site) - this merge only ever excludes, never
		 *	decides who should be excluded. The list holds a sha256 per
		 *	address, not the address itself (see REMOVED_PATH's own
		 *	docblock), so an entry is matched by hashing it the same way,
		 *	not by comparing emails directly.
		 *
		 *	'/data/newsletter.php' / '/data/newsletter-removed.php' as plain
		 *	literals throughout, deliberately not
		 *	\Nino\Modules\Newsletter::PATH/REMOVED_PATH: a class constant
		 *	read autoloads the class unconditionally, same as a method call
		 *	would, turning a restore - the exact tool a broken install needs
		 *	most - into a fatal error for a project that deleted this
		 *	optional module's file. See Backup::manifest()'s own docblock in
		 *	_nino/Nino.php for the identical reasoning on the backup side.
		 *
		 *	A no-op if the backup being restored predates this feature and
		 *	carries neither file - nothing to merge, copyDir() below restores
		 *	them exactly as it always did.
		 *
		 *	@param		string 		$root					Project root - reads $root's *current* files, unchanged otherwise
		 *	@param		string 		$staging			Extracted backup, rewritten in place
		 *
		 *	@return 	void
		 */
		private static function _mergeNewsletterRestore( string $root, string $staging ): void {

			$stagedEntries = $staging. '/data/newsletter.php';
			$stagedRemoved = $staging. '/data/newsletter-removed.php';

			if( is_file( $stagedEntries ) === false && is_file( $stagedRemoved ) === false )
				return;

			$removed = array_values( array_unique( array_merge(
				self::_readDataFile( $root. '/data/newsletter-removed.php' ),
				self::_readDataFile( $stagedRemoved )
			) ) );

			$entries = array_values( array_filter(
				self::_readDataFile( $stagedEntries ),
				function( array $entry ) use ( $removed ): bool {
					$hash = hash( 'sha256', mb_strtolower( trim( (string) ( $entry['email'] ?? '' ) ) ) );
					return in_array( $hash, $removed, true ) === false;
				}
			) );

			file_put_contents( $stagedEntries, '<?php return '. var_export( $entries, true ). ';' );
			file_put_contents( $stagedRemoved, '<?php return '. var_export( $removed, true ). ';' );
		}

		// Reads one of Filesystem's own "<?php return [...];" data files
		// straight off disk, bypassing $appData/Filesystem entirely - used
		// here for files under $staging (a tempdir, not the project root
		// Filesystem resolves against) and, for the same reason, for $root's
		// own copy alongside it, so both sides of the merge above go through
		// the identical read path
		private static function _readDataFile( string $path ): array {

			if( is_file( $path ) === false )
				return [];

			$data = include $path;

			return is_array( $data ) ? $data : [];
		}

		/**
		 *	Archive the *current* on-disk state before overwriting it, same
		 *	tar+gzip+encrypt shape as Backup::_create() (duplicated, not
		 *	called into - see this class' own docblock; the file manifest
		 *	itself is shared via \Nino\Backup::manifest(), which carries
		 *	no such coupling). Named distinctly from a dated backup so it's
		 *	never mistaken for one and never collides with/gets pruned by
		 *	Backup's own retention sweep
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$key					Raw (not base64) 32-byte AES key
		 *
		 *	@return 	void
		 */
		private static function _safetySnapshot( array &$appData, string $dir, string $key ): void {

			$tmpTar = tempnam( sys_get_temp_dir(), 'ninosnapshot' ). '.tar';
			$phar 	= new \PharData( $tmpTar );

			foreach( \Nino\Backup::manifest( $appData ) as $absolute => $archiveName )
				$phar->addFromString( $archiveName, file_get_contents( $absolute ) );

			$phar->compress( \Phar::GZ );
			unset( $phar );
			unlink( $tmpTar );

			$gz = file_get_contents( $tmpTar. '.gz' );
			unlink( $tmpTar. '.gz' );

			$iv 		= random_bytes( 12 );
			$tag 		= '';
			$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			file_put_contents( $dir. '/pre-restore-'. date( 'Y-m-d-His' ). '.php', self::STUB_PREFIX. base64_encode( $iv. $tag. $cipher ). self::STUB_SUFFIX );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Edit a curated whitelist of "soft" config.php values without
	 *												touching the file by hand: error display/logging, available
	 *												locales, asset bundles, and routes. Deliberately excludes
	 *												"hard" values a wrong edit could brick the whole site over -
	 *												modules, filesystem/dir, locales/textfiles - those stay a
	 *												by-hand, deliberate-only task.
	 *
	 *												/nino/html/images and /nino/auth/user aren't part of this
	 *												generic json editor either, despite being just as "soft"
	 *												conceptually - both get their own dedicated, richer editors
	 *												instead (Images and Users below), the same reasoning as
	 *												ElementTypes gets one instead of hand-editing elements/*.php:
	 *												structured data with real validation beats a raw textarea
	 *												once there's enough shape to it to be worth the editor.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Config {

		// key -> expected decoded-json type, checked before ever writing back to
		// config.php - a malformed save (wrong shape, not just invalid json)
		// would otherwise silently corrupt that key for the rest of the site
		private const array KEY_TYPES = [
			'/nino/error/log'					=> 'bool',
			'/nino/error/display'			=> 'bool',
			'/nino/locales/native'		=> 'string',
			'/nino/locales/available'	=> 'array',
			'/nino/html/assets'				=> 'array',
			'/nino/html/navs'					=> 'array',
			'/nino/http/routes'				=> 'array',
		];

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'config/list' => [ self::class, 'apiList' ],
				'config/save' => [ self::class, 'apiSave' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'config', 'Konfiguration' ];
		}

		/**
		 *	List every editable key's current value (pretty-printed json).
		 *	Reads config.php fresh rather than $appData directly: by the time
		 *	this runs, Admin::init() has already added _admin's own GET/POST
		 *	/_admin route into $appData['/nino/http/routes'] at runtime (same
		 *	as Editor::init() does for /_editor, self-registered, never
		 *	persisted - see Admin::init()'s docblock). Showing that live value
		 *	here would round-trip it straight into config.php on the next
		 *	save of this key.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			$values = [];
			foreach( array_keys( self::KEY_TYPES ) as $key )
				$values[$key] = json_encode( $stored[$key] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			\Nino\Http::ok( $request, [ 'values' => $values ] );
		}

		/**
		 *	Save one whitelisted key - validates both that the posted value is
		 *	parseable json AND that it decodes to the type this key expects,
		 *	before ever touching config.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$key 	= (string) ( $data['key'] ?? '' );
			$raw 	= (string) ( $data['value'] ?? '' );

			if( isset( self::KEY_TYPES[$key] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown key' );
				return;
			}

			$decoded = json_decode( $raw, true );

			if( json_last_error() !== JSON_ERROR_NONE ) {
				\Nino\Http::fail( $request, 400, 'invalid json: '. json_last_error_msg() );
				return;
			}

			if( self::_matchesType( $decoded, self::KEY_TYPES[$key] ) === false ) {
				\Nino\Http::fail( $request, 400, 'expected a '. self::KEY_TYPES[$key]. ' value' );
				return;
			}

			$appData[$key] = $decoded;
			\Nino\AppData::writeContentData( $appData, [ $key ] );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Whether a json-decoded value matches the type KEY_TYPES expects
		 *
		 *	@param		mixed			$value
		 *	@param		string		$type					'bool' | 'string' | 'array'
		 *
		 *	@return 	bool
		 */
		private static function _matchesType( mixed $value, string $type ): bool {
			return match( $type ) {
				'bool' 		=> is_bool( $value ),
				'string' 	=> is_string( $value ),
				'array' 	=> is_array( $value ),
				default 	=> false,
			};
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Manage admin accounts: create/delete - the "set" half of what
	 *												_editor's Users panel edits ("values" half: mail/password
	 *												for an existing account). Same split as ElementTypes/Elements.
	 *												This is also the only way to bootstrap the very first
	 *												_editor user without hand-editing config.php, which nothing
	 *												else in the project can do.
	 *
	 *												Permissions are the one exception to that split: this also
	 *												exposes a raw, unwhitelisted permissions editor, deliberately
	 *												independent of _editor's own manager-only, whitelisted
	 *												apiSetPermissions() - the recovery path for when nobody with
	 *												/_editor/users/manage is left to use that one.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Users {

		private const string MANAGE_PERM = '/_editor/users/manage';

		// The individual _editor content perms (deliberately duplicated as plain
		// strings rather than referencing \Nino\Editor\*::MANAGE_PERM/VIEW_PERM -
		// this file stays independent of _editor/Editor.php, see class docblock
		// above). A non-manager account created here still gets full content
		// access, same as every admin account had before per-module permissions
		// existed - only /_editor/users/manage is gated by the checkbox
		private const array CONTENT_PERMS = [
			'/_editor/elements/manage',
			'/_editor/text/manage',
			'/_editor/images/manage',
			'/_editor/submissions/view',
			'/_editor/newsletter/manage',
			'/_editor/logs/view',
		];

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'devusers/list' 				=> [ self::class, 'apiList' ],
				'devusers/create' 			=> [ self::class, 'apiCreate' ],
				'devusers/delete' 			=> [ self::class, 'apiDelete' ],
				'devusers/permissions' 	=> [ self::class, 'apiSetPermissions' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'users', 'Nutzer' ];
		}

		/**
		 *	How many admin accounts exist - shared by Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {
			return count( $appData['/nino/auth/user'] ?? [] );
		}

		/**
		 *	List every admin user (mail/status/permissions, never the
		 *	password hash)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$users = [];
			foreach( ( $appData['/nino/auth/user'] ?? [] ) as $mail => $user )
				$users[] = [
					'mail' 			=> $mail,
					'status' 		=> $user['status'] ?? 0,
					'perms' 		=> $user['perms'] ?? [],
					// checkPermission(), not a raw in_array() - a '/*' grant (see
					// apiCreate()) implies this too, same recursive wildcard match
					// _editor's own guardPerm() uses
					'isManager' => \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM, $mail ),
				];

			\Nino\Http::ok( $request, [ 'users' => $users ] );
		}

		/**
		 *	Create a new admin user
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 			= \Nino\Admin\Admin::postData();
			$mail 			= trim( (string) ( $data['mail'] ?? '' ) );
			$password 	= (string) ( $data['password'] ?? '' );
			$isManager 	= ( $data['isManager'] ?? false ) === true;

			if( filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false || strlen( $password ) < 8 ) {
				\Nino\Http::fail( $request, 400, 'invalid mail or password too short' );
				return;
			}

			$ok = \Nino\Auth::insertUser( $appData, $mail, $password, $isManager === true ? [ '/*' ] : self::CONTENT_PERMS );

			if( $ok === false ) {
				\Nino\Http::fail( $request, 409, 'a user with this mail already exists' );
				return;
			}

			\Nino\Http::ok( $request );
		}

		/**
		 *	Delete an admin user
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$mail = trim( (string) ( \Nino\Admin\Admin::postData()['mail'] ?? '' ) );
			$ok 	= \Nino\Auth::deleteUser( $appData, $mail );

			if( $ok === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user' );
				return;
			}

			\Nino\Http::ok( $request );
		}

		/**
		 *	Directly set a user's permissions - unlike _editor's own
		 *	Admin\Users::apiSetPermissions() this isn't whitelisted against a
		 *	known set of permission strings: this file is the deliberate
		 *	developer bypass for exactly the "wrecked config.php data"
		 *	scenario _editor's own docblock already points back here for, eg.
		 *	the last remaining manager accidentally removing their own
		 *	/_editor/users/manage permission with no self-service recovery
		 *	path in _editor itself
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSetPermissions( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$mail 	= trim( (string) ( $data['mail'] ?? '' ) );
			$perms 	= $data['perms'] ?? null;

			if( \Nino\Auth::getUser( $appData, $mail ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user' );
				return;
			}

			if( is_array( $perms ) === false || count( array_filter( $perms, 'is_string' ) ) !== count( $perms ) ) {
				\Nino\Http::fail( $request, 400, 'perms must be an array of strings' );
				return;
			}

			$appData['/nino/auth/user'][$mail]['perms'] = array_values( $perms );
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			\Nino\Http::ok( $request, [ 'perms' => $appData['/nino/auth/user'][$mail]['perms'] ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Manage image slots (/nino/html/images): create, edit label/
	 *												width/height, delete - the "set" half of what _editor's
	 *												Images panel edits ("values" half: which file currently
	 *												fills a slot). Same split as ElementTypes/Elements. Only
	 *												ever touches a slot's filename when deleting the slot
	 *												itself (cleans up its uploaded file via \Nino\Images::
	 *												delete()) - replacing it stays _editor's job via the actual
	 *												upload/crop pipeline (\Nino\Images::process()/
	 *												setSlotFilename()).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Images {

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'devimages/list' 	=> [ self::class, 'apiList' ],
				'devimages/save' 	=> [ self::class, 'apiSave' ],
				'devimages/create' => [ self::class, 'apiCreate' ],
				'devimages/delete' => [ self::class, 'apiDelete' ],
				'devimages/scan' 	=> [ self::class, 'apiScan' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'images', 'Bilder' ];
		}

		/**
		 *	A slot uri follows the same shape as an element uri (Elements/
		 *	Images shortcodes address slots by a "/segment/segment" path)
		 *
		 *	@param		string		$uri
		 *
		 *	@return 	bool
		 */
		private static function isValidUri( string $uri ): bool {
			return preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)*$#', $uri ) === 1;
		}

		/**
		 *	List every image slot with its current filename (if any)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$slots = [];
			foreach( ( $appData['/nino/html/images'] ?? [] ) as $uri => $slot )
				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'hasImage' 	=> ( $slot['filename'] ?? null ) !== null,
				];

			usort( $slots, fn( array $a, array $b ) => strcmp( $a['uri'], $b['uri'] ) );

			\Nino\Http::ok( $request, [ 'slots' => $slots ] );
		}

		/**
		 *	Edit an existing slot's label/width/height - never its filename
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			if( isset( $appData['/nino/html/images'][$uri] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$width 	= max( 1, (int) ( $data['width'] ?? 0 ) );
			$height = max( 1, (int) ( $data['height'] ?? 0 ) );

			if( $label === '' ) {
				\Nino\Http::fail( $request, 400, 'label is required' );
				return;
			}

			$appData['/nino/html/images'][$uri]['label'] 	= $label;
			$appData['/nino/html/images'][$uri]['width'] 	= $width;
			$appData['/nino/html/images'][$uri]['height'] = $height;

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Create a brand new, empty (no filename yet) image slot
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$uri 		= (string) ( $data['uri'] ?? '' );
			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$width 	= max( 1, (int) ( $data['width'] ?? 0 ) );
			$height = max( 1, (int) ( $data['height'] ?? 0 ) );

			if( self::isValidUri( $uri ) === false || $label === '' ) {
				\Nino\Http::fail( $request, 400, 'invalid uri or missing label' );
				return;
			}

			if( isset( $appData['/nino/html/images'][$uri] ) === true ) {
				\Nino\Http::fail( $request, 409, 'slot already exists' );
				return;
			}

			$appData['/nino/html/images'][$uri] = [
				'label' 		=> $label,
				'width' 		=> $width,
				'height' 		=> $height,
				'filename' 	=> null,
			];

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			\Nino\Http::ok( $request, [ 'ok' => true, 'uri' => $uri ] );
		}

		/**
		 *	Delete an image slot - its currently uploaded file too, if any
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			$slot = $appData['/nino/html/images'][$uri] ?? null;

			if( $slot === null ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			if( ( $slot['filename'] ?? null ) !== null )
				\Nino\Images::delete( $appData, $slot['filename'] );

			unset( $appData['/nino/html/images'][$uri] );

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Scan every public-site template (templates/*.tpl) for literal
		 *	<img src="/images/..."> tags not backed by any image slot - the
		 *	gap this closes: a template built with a placeholder/demo photo
		 *	hardcoded straight into the markup instead of going through the
		 *	[image /uri] shortcode (see \Nino\Modules\Images), so an
		 *	admin can never swap it without editing code. Proposes a slot
		 *	per file (uri guessed from the filename, width/height read off
		 *	the <img> tag's own attributes or, failing that, probed from the
		 *	actual file), filename deliberately left for apiCreate() to
		 *	leave empty - same "dev only ever creates the empty slot, a real
		 *	upload is _editor's job" rule the class docblock already states.
		 *	An <img> outside /images/ (external url, data: uri, favicon,
		 *	logo) is skipped entirely, none of those fit this slot system
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScan( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, [ 'missing' => self::_scanMissing( $appData ) ] );
		}

		/**
		 *	How many <img> tags apiScan() above would currently report as
		 *	missing a slot - shared by Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function missingCount( array &$appData ): int {
			return count( self::_scanMissing( $appData ) );
		}

		/**
		 *	Scan every public-site template for <img src="/images/..."> tags
		 *	not backed by any image slot - the actual work behind
		 *	apiScan()/missingCount() above
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'filename', 'src', 'suggestedUri', 'width', 'height', 'files' ], ... ]
		 */
		private static function _scanMissing( array &$appData ): array {

			$known = [];
			foreach( ( $appData['/nino/html/images'] ?? [] ) as $slot )
				if( ( $slot['filename'] ?? null ) !== null )
					$known[ $slot['filename'] ] = true;

			$path 	= \Nino\Filesystem::getPath( $appData );
			$found 	= [];

			foreach( glob( $path. '/templates/*.tpl' ) ?: [] as $file ) {

				$content = file_get_contents( $file );
				if( $content === false || preg_match_all( '/<img\b[^>]*\bsrc="([^"]+)"[^>]*>/i', $content, $matches, PREG_SET_ORDER ) === false )
					continue;

				foreach( $matches as $match ) {

					// A local image is always referenced as [[/nino/dir]]/images/...
					// in raw template source (the /nino/dir fill hasn't resolved
					// yet at this point - this scans the .tpl source directly)
					$src = preg_replace( '#^\[\[/nino/dir\]\]#', '', $match[1] );

					if( str_starts_with( $src, '/images/' ) === false )
						continue;

					$relative = substr( $src, strlen( '/images/' ) );

					if( isset( $known[$relative] ) === true )
						continue;

					if( isset( $found[$relative] ) === false ) {

						$width 	= 0;
						$height = 0;

						if( preg_match( '/\bwidth="(\d+)"/i', $match[0], $w ) === 1 )
							$width = (int) $w[1];
						if( preg_match( '/\bheight="(\d+)"/i', $match[0], $h ) === 1 )
							$height = (int) $h[1];

						if( ( $width === 0 || $height === 0 ) && is_file( $path. '/images/'. $relative ) === true ) {
							$size = @getimagesize( $path. '/images/'. $relative );
							if( $size !== false ) {
								$width 	= $width ?: $size[0];
								$height = $height ?: $size[1];
							}
						}

						$suggestedUri = '/'. preg_replace( '/[^a-z0-9-]+/', '-', strtolower( pathinfo( $relative, PATHINFO_FILENAME ) ) );

						$found[$relative] = [ 'src' => $src, 'suggestedUri' => $suggestedUri, 'width' => $width, 'height' => $height, 'files' => [] ];
					}

					$found[$relative]['files'][] = basename( $file );
				}
			}

			foreach( $found as &$entry )
				$entry['files'] = array_values( array_unique( $entry['files'] ) );
			unset( $entry );

			ksort( $found );

			return array_map( fn( $filename, $entry ) => array_merge( [ 'filename' => $filename ], $entry ), array_keys( $found ), array_values( $found ) );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Full CRUD for text keys: create, rename, delete, change global/
	 *												per-locale shape, toggle whether a key is hidden from
	 *												_editor's Text panel (/text/blacklist.php) - plus, same as
	 *												_editor's Text panel, edit every key's actual value(s).
	 *												Unlike _editor, _admin also sees blacklisted keys and can still
	 *												edit/rename/delete them (the blacklist only hides a key
	 *												from _editor, not from _admin). Converting a key between global
	 *												and per-locale, or renaming it, migrates its current
	 *												value(s) rather than discarding them - see _convertShape()/
	 *												apiRename().
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Text {

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'devtext/list' 			=> [ self::class, 'apiList' ],
				'devtext/create' 		=> [ self::class, 'apiCreate' ],
				'devtext/save' 			=> [ self::class, 'apiSave' ],
				'devtext/savebatch' => [ self::class, 'apiSaveBatch' ],
				'devtext/rename' 		=> [ self::class, 'apiRename' ],
				'devtext/delete' 		=> [ self::class, 'apiDelete' ],
				'devtext/scan' 			=> [ self::class, 'apiScan' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'text', 'Texte' ];
		}

		/**
		 *	A text key follows the same "/segment/segment" shape _editor's
		 *	Text panel groups by (first segment = category)
		 *
		 *	@param		string		$key
		 *
		 *	@return 	bool
		 */
		private static function isValidKey( string $key ): bool {
			return preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z0-9_-]+)+$#', $key ) === 1;
		}

		/**
		 *	List every known text key, blacklisted or not (unlike _editor's
		 *	own panel, this is exactly where you'd come to un-blacklist one)
		 *	- see \Nino\Text::entries()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, [
				'keys' 		=> \Nino\Text::entries( $appData ),
				'locales' => \Nino\Locales::getAvailableLocales( $appData ),
			] );
		}

		/**
		 *	Create a brand new key with an initial value - global.php gets
		 *	one value, or every locale file gets the same starting value,
		 *	depending on $isGlobal
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$isGlobal = ( $data['global'] ?? false ) === true;
			$value 		= (string) ( $data['value'] ?? '' );

			if( self::isValidKey( $key ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid key' );
				return;
			}

			if( \Nino\Text::entry( $appData, $key ) !== null ) {
				\Nino\Http::fail( $request, 409, 'key already exists' );
				return;
			}

			$bracketKey = '[['. $key. ']]';

			if( $isGlobal === true ) {
				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey, $value ): array {
					$global[$bracketKey] = $value;
					return $global;
				} );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey, $value ): array {
						$localeData[$bracketKey] = $value;
						return $localeData;
					} );
			}

			\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $key ] );
		}

		/**
		 *	Edit an existing key's global/per-locale shape and/or blacklist
		 *	status. Converting shape migrates the current value(s) instead
		 *	of discarding them: global -> per-locale copies the one value
		 *	into every locale; per-locale -> global keeps the native
		 *	locale's value (falling back to the first non-empty one)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$isGlobal = ( $data['global'] ?? false ) === true;
			$blacklisted = ( $data['blacklisted'] ?? false ) === true;

			$entry = \Nino\Text::entry( $appData, $key );

			if( $entry === null ) {
				\Nino\Http::fail( $request, 404, 'unknown key' );
				return;
			}

			if( $entry['global'] !== $isGlobal )
				self::_convertShape( $appData, $key, $entry, $isGlobal );

			\Nino\Text::setBlacklisted( $appData, $key, $blacklisted );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Save several keys' values in one request - see \Nino\Text::saveBatch().
		 *	Unlike _editor, a blacklisted key is still a valid save target here -
		 *	blacklist only hides a key from _editor's Text panel, not from _admin's.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSaveBatch( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$items 	= is_array( $data['items'] ?? null ) ? $data['items'] : [];

			\Nino\Http::ok( $request, [ 'results' => \Nino\Text::saveBatch( $appData, $items, true ) ] );
		}

		/**
		 *	Rename a key, moving its current value(s) and blacklist status
		 *	to the new name - the file(s)/shape don't change, only the
		 *	bracket key itself
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRename( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$key 		= (string) ( $data['key'] ?? '' );
			$newKey = (string) ( $data['newKey'] ?? '' );

			if( self::isValidKey( $newKey ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid new key' );
				return;
			}

			$entry = \Nino\Text::entry( $appData, $key );

			if( $entry === null ) {
				\Nino\Http::fail( $request, 404, 'unknown key' );
				return;
			}

			if( $newKey !== $key && \Nino\Text::entry( $appData, $newKey ) !== null ) {
				\Nino\Http::fail( $request, 409, 'key already exists' );
				return;
			}

			$oldBracket = '[['. $key. ']]';
			$newBracket = '[['. $newKey. ']]';

			if( $entry['global'] === true ) {
				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $oldBracket, $newBracket ): array {
					$global[$newBracket] = $global[$oldBracket] ?? '';
					unset( $global[$oldBracket] );
					return $global;
				} );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $oldBracket, $newBracket ): array {
						$localeData[$newBracket] = $localeData[$oldBracket] ?? '';
						unset( $localeData[$oldBracket] );
						return $localeData;
					} );
			}

			if( $entry['blacklisted'] === true ) {
				\Nino\Text::setBlacklisted( $appData, $key, false );
				\Nino\Text::setBlacklisted( $appData, $newKey, true );
			}

			\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $newKey ] );
		}

		/**
		 *	Delete a key entirely - its value(s) from global.php or every
		 *	locale file, and its blacklist entry if any
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$key 	= (string) ( $data['key'] ?? '' );

			$entry = \Nino\Text::entry( $appData, $key );

			if( $entry === null ) {
				\Nino\Http::fail( $request, 404, 'unknown key' );
				return;
			}

			$bracketKey = '[['. $key. ']]';

			if( $entry['global'] === true ) {
				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey ): array {
					unset( $global[$bracketKey] );
					return $global;
				} );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey ): array {
						unset( $localeData[$bracketKey] );
						return $localeData;
					} );
			}

			if( $entry['blacklisted'] === true )
				\Nino\Text::setBlacklisted( $appData, $key, false );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Move a key's current value(s) between global.php and every
		 *	locale file - see apiSave()'s docblock for the exact migration
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *	@param		array 		$entry					This key's current entry (see \Nino\Text::entries())
		 *	@param		bool			$toGlobal				Target shape
		 *
		 *	@return 	void
		 */
		private static function _convertShape( array &$appData, string $key, array $entry, bool $toGlobal ): void {

			$bracketKey = '[['. $key. ']]';
			$locales 		= \Nino\Locales::getAvailableLocales( $appData );

			if( $toGlobal === true ) {

				// A locale missing this key entirely is null (see
				// \Nino\Text::entries()), not '' - both are excluded here, so
				// "the first non-empty value" doesn't pick a locale that never
				// had one
				$native = \Nino\Locales::getNativeLocale( $appData );
				$value 	= $entry['values'][$native] ?? ( array_values( array_filter( $entry['values'], fn( $v ) => $v !== null && $v !== '' ) )[0] ?? '' );

				foreach( $locales as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey ): array {
						unset( $localeData[$bracketKey] );
						return $localeData;
					} );

				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey, $value ): array {
					$global[$bracketKey] = $value;
					return $global;
				} );

			} else {

				$value = $entry['values']['*'] ?? '';

				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey ): array {
					unset( $global[$bracketKey] );
					return $global;
				} );

				foreach( $locales as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey, $value ): array {
						$localeData[$bracketKey] = $value;
						return $localeData;
					} );
			}
		}

		/**
		 *	A handful of fills the kernel itself injects at request time
		 *	(Nino::request()'s Html::addFills() call) - never stored in any
		 *	/text/*.php file, so apiScan() below would otherwise flag them as
		 *	"missing" forever. Only the innermost, non-nested [[...]] a
		 *	static regex scan can even see (eg. the [[/nino/http/response/
		 *	uri]] inside html-header.tpl's [[/webpage[[/nino/http/response/
		 *	uri]]/title]]) - the outer, dynamically-constructed key itself
		 *	isn't something a scan of the raw template source can resolve at
		 *	all, since its final shape depends on which page is rendering
		 */
		private const array KERNEL_FILLS = [
			'/nino/http/request/uri', '/nino/http/response/uri', '/nino/http/response/locale',
			'/nino/auth/user', '/nino/dir', '/date/year',
		];

		/**
		 *	Scan every public-site template (templates/*.tpl - not _editor's
		 *	or _admin's own, those are separate text systems entirely) for
		 *	[[/key]] placeholders that aren't yet defined for any locale -
		 *	the exact gap this module exists to close: designing a template,
		 *	inventing a [[/key]] along the way, then forgetting to actually
		 *	add it anywhere. Doesn't write anything - apiCreate() (already
		 *	existing) is what turns an accepted result into a real key
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScan( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, [ 'missing' => self::_scanMissing( $appData ) ] );
		}

		/**
		 *	How many [[/key]] placeholders apiScan() above would currently
		 *	report as missing - shared by Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function missingCount( array &$appData ): int {
			return count( self::_scanMissing( $appData ) );
		}

		/**
		 *	Scan every public-site template for [[/key]] placeholders that
		 *	aren't yet defined for any locale - the actual work behind
		 *	apiScan()/missingCount() above
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'key', 'files' ], ... ]
		 */
		private static function _scanMissing( array &$appData ): array {

			$known = [];
			foreach( \Nino\Text::entries( $appData ) as $entry )
				$known[$entry['key']] = true;
			foreach( self::KERNEL_FILLS as $key )
				$known[$key] = true;

			$found = [];

			foreach( glob( \Nino\Filesystem::getPath( $appData ). '/templates/*.tpl' ) ?: [] as $file ) {

				$content = file_get_contents( $file );
				if( $content === false || preg_match_all( '/\[\[([^\[\]]+)\]\]/', $content, $matches ) === false )
					continue;

				foreach( array_unique( $matches[1] ) as $key ) {

					if( isset( $known[$key] ) === true || self::isValidKey( $key ) === false )
						continue;

					$found[$key] 			= $found[$key] ?? [];
					$found[$key][] 		= basename( $file );
				}
			}

			ksort( $found );

			return array_map( fn( $key, $files ) => [ 'key' => $key, 'files' => $files ], array_keys( $found ), array_values( $found ) );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin						Batch translation hand-off for the complete native
	 *										content layer: public textfills and locale-scoped
	 *										element fields. The JSON deliberately contains no
	 *										global/technical values, images or element structure.
	 *										Import is merge-only and validates every posted path
	 *										against a fresh native export before it writes.
	 *
	 *	@package					Dape/Nino
	 *	@author					David Perchermeier <mail@dape.io>
	 *	@link						https://github.com/dapeio/nino
	 */
	class Translations {

		private const string FORMAT = 'nino.translation';
		private const int VERSION = 1;

		/**
		 *	This module's action map, merged into Admin::handlePost()
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'translations/info' 		=> [ self::class, 'apiInfo' ],
				'translations/export' 	=> [ self::class, 'apiExport' ],
				'translations/import' 	=> [ self::class, 'apiImport' ],
			];
		}

		public static function nav(): array {
			return [ 'translations', 'Translations' ];
		}

		/**
		 *	Report the fixed source language, possible targets and package size
		 */
		public static function apiInfo( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$translation = self::_translationData( $appData );

			$elementFields = 0;
			foreach( $translation['elements'] as $elements )
				foreach( $elements as $fields )
					$elementFields += count( $fields );

			\Nino\Http::ok( $request, [
				'nativeLocale' 	=> $translation['sourceLocale'],
				'locales' 			=> \Nino\Locales::getAvailableLocales( $appData ),
				'textCount' 		=> count( $translation['text'] ),
				'elementCount' 	=> $elementFields,
			] );
		}

		/**
		 *	Return the versioned native-language hand-off document
		 */
		public static function apiExport( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, self::_translationData( $appData ) );
		}

		/**
		 *	Merge a translated hand-off document into one configured locale.
		 *	The document's keys are untrusted: only paths which still occur in
		 *	a freshly generated native package may reach the write APIs.
		 */
		public static function apiImport( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 			= \Nino\Admin\Admin::postData();
			$targetLocale = (string) ( $data['targetLocale'] ?? '' );
			$translation 	= $data['translation'] ?? null;

			if( \Nino\Locales::verifyLocale( $appData, $targetLocale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid target locale' );
				return;
			}

			if(
				is_array( $translation ) === false ||
				( $translation['format'] ?? null ) !== self::FORMAT ||
				( $translation['version'] ?? null ) !== self::VERSION ||
				( $translation['sourceLocale'] ?? null ) !== \Nino\Locales::getNativeLocale( $appData ) ||
				is_array( $translation['text'] ?? null ) === false ||
				is_array( $translation['elements'] ?? null ) === false
			) {
				\Nino\Http::fail( $request, 400, 'invalid or incompatible translation document' );
				return;
			}

			$reference = self::_translationData( $appData );

			$textItems 		= [];
			$textSkipped 	= 0;

			foreach( $translation['text'] as $key => $value ) {
				if( is_string( $key ) === false || array_key_exists( $key, $reference['text'] ) === false || is_string( $value ) === false ) {
					$textSkipped++;
					continue;
				}
				$textItems[] = [ 'key' => $key, 'locale' => $targetLocale, 'value' => $value ];
			}

			$textImported = 0;
			foreach( \Nino\Text::saveBatch( $appData, $textItems, false ) as $result ) {
				if( ( $result['ok'] ?? false ) === true )
					$textImported++;
				else
					$textSkipped++;
			}

			$elementImported = 0;
			$elementSkipped 	= 0;
			$errors 				= [];

			foreach( $translation['elements'] as $type => $submittedElements ) {

				$referenceElements = is_string( $type ) ? ( $reference['elements'][$type] ?? null ) : null;
				if( is_array( $submittedElements ) === false || is_array( $referenceElements ) === false ) {
					$elementSkipped += self::_submittedFieldCount( $submittedElements );
					continue;
				}

				$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
				$model 		= is_array( $typeData['model'] ?? null ) ? $typeData['model'] : [];
				$changes 	= [];

				foreach( $submittedElements as $elementUri => $submittedFields ) {

					$elementUri = is_string( $elementUri ) || is_int( $elementUri ) ? (string) $elementUri : '';
					$referenceFields = $elementUri !== '' ? ( $referenceElements[$elementUri] ?? null ) : null;
					if( is_array( $submittedFields ) === false || is_array( $referenceFields ) === false ) {
						$elementSkipped += is_array( $submittedFields ) ? count( $submittedFields ) : 1;
						continue;
					}

					foreach( $submittedFields as $key => $value ) {
						$key = is_string( $key ) || is_int( $key ) ? (string) $key : '';
						$field = $key !== '' ? ( $model[$key] ?? null ) : null;
						if(
							is_array( $field ) === false ||
							array_key_exists( $key, $referenceFields ) === false ||
							( $field['locale'] ?? false ) !== true ||
							( $field['type'] ?? '' ) === 'image'
						) {
							$elementSkipped++;
							continue;
						}

						$value = self::_sanitizeElementValue( $value, $field );
						if( self::_validElementValue( $value, $field ) === false ) {
							$elementSkipped++;
							continue;
						}

						$changes[$elementUri][$key] = $value;
					}
				}

				if( $changes === [] )
					continue;

				// Old hand-authored type files sometimes only carry a native
				// bucket. The kernel needs the empty global identity stub to see
				// that same element from a target locale which has no bucket yet.
				$missingStubs = array_values( array_filter( array_keys( $changes ), fn( string|int $uri ): bool => isset( $typeData['*'][$uri] ) === false ) );

				if( $missingStubs !== [] ) {
					$stubWritten = \Nino\Filesystem::mutate( $appData, '/elements/'. $type. '.php', function( array $fresh ) use ( $missingStubs ): array {
						foreach( $missingStubs as $uri )
							$fresh['*'][$uri] = $fresh['*'][$uri] ?? [];
						return $fresh;
					} );

					if( $stubWritten === false ) {
						foreach( $changes as $fields )
							$elementSkipped += count( $fields );
						$errors[] = $type. ': element identities could not be written';
						continue;
					}
					unset( $appData['./nino/elements/cache'] );
				}

				foreach( $changes as $elementUri => $fields ) {
					$result = \Nino\Elements::updateElement( $appData, '/'. $type. '/'. $elementUri, $fields, $targetLocale );
					if( is_array( $result ) === true )
						$elementImported += count( $fields );
					else {
						$elementSkipped += count( $fields );
						$errors[] = $type. '/'. $elementUri. ': values were rejected';
					}
				}
			}

			\Nino\Http::ok( $request, [
				'targetLocale' 	=> $targetLocale,
				'text' 				=> [ 'imported' => $textImported, 'skipped' => $textSkipped ],
				'elements' 		=> [ 'imported' => $elementImported, 'skipped' => $elementSkipped ],
				'errors' 			=> $errors,
			] );
		}

		/**
		 *	Build the deterministic source document from raw native buckets
		 */
		private static function _translationData( array &$appData ): array {

			$native = \Nino\Locales::getNativeLocale( $appData );
			$text 	= [];

			foreach( \Nino\Text::entries( $appData, false ) as $entry )
				if( $entry['global'] === false && array_key_exists( $native, $entry['values'] ) === true && $entry['values'][$native] !== null )
					$text[$entry['key']] = $entry['values'][$native];

			$elements = [];

			foreach( glob( \Nino\Filesystem::getPath( $appData ). '/elements/*.php' ) ?: [] as $file ) {

				$type 		= basename( $file, '.php' );
				$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
				$model 		= is_array( $typeData['model'] ?? null ) ? $typeData['model'] : [];
				$nativeData = is_array( $typeData[$native] ?? null ) ? $typeData[$native] : [];

				foreach( $nativeData as $elementUri => $rawFields ) {
					$elementUri = (string) $elementUri;
					if( $elementUri === '*' || is_array( $rawFields ) === false )
						continue;

					foreach( $model as $key => $field )
						if(
							is_array( $field ) === true &&
							( $field['locale'] ?? false ) === true &&
							( $field['type'] ?? '' ) !== 'image' &&
							array_key_exists( $key, $rawFields ) === true
						)
							$elements[$type][$elementUri][$key] = $rawFields[$key];
				}
			}

			ksort( $text );
			ksort( $elements );
			foreach( $elements as &$typeElements ) {
				ksort( $typeElements );
				foreach( $typeElements as &$fields )
					ksort( $fields );
				unset( $fields );
			}
			unset( $typeElements );

			return [
				'format' 			=> self::FORMAT,
				'version' 			=> self::VERSION,
				'sourceLocale' 	=> $native,
				'instructions' 	=> [
					'Translate values only. Never change, add, or remove object keys.',
					'Keep JSON value types unchanged and preserve HTML tags, URLs, [[placeholders]], [shortcodes], and identifiers.',
				],
				'text' 				=> $text,
				'elements' 		=> $elements,
			];
		}

		/**
		 *	Normalize external values before the kernel validates/stores them
		 */
		private static function _sanitizeElementValue( mixed $value, array $field ): mixed {

			$type = (string) ( $field['type'] ?? '' );

			if( is_string( $value ) === true )
				return $type === 'string' && ( $field['html'] ?? false ) === true
					? \Nino\Html::sanitizeHtml( $value )
					: strip_tags( $value );

			if( $type === 'array' && is_array( $value ) === true )
				return array_map( function( mixed $item ): mixed {
					if( is_string( $item ) === true )
						return strip_tags( $item );
					if( is_array( $item ) === true )
						return self::_sanitizeElementValue( $item, [ 'type' => 'array' ] );
					return $item;
				}, $value );

			return $value;
		}

		/**
		 *	Reject one malformed field without making the kernel reject all
		 *	valid sibling fields in that element's partial update.
		 */
		private static function _validElementValue( mixed $value, array $field ): bool {

			$type = (string) ( $field['type'] ?? '' );
			$expected = in_array( $type, [ 'date', 'datetime', 'image' ], true ) ? 'string' : $type;

			if( $type === 'double' && is_int( $value ) === true ) {
				// The kernel accepts and coerces whole-number JSON values too.
			} else if( gettype( $value ) !== $expected )
				return false;

			if( ( $field['required'] ?? false ) === true ) {
				if( is_string( $value ) === true && $value === '' )
					return false;
				if( is_array( $value ) === true && $value === [] )
					return false;
			}

			if( isset( $field['whitelist'] ) === true && in_array( $value, $field['whitelist'] ) === false )
				return false;

			if( is_array( $field['options'] ?? null ) === true && $field['options'] !== [] && in_array( $value, $field['options'], true ) === false )
				return false;

			if( isset( $field['blacklist'] ) === true && in_array( $value, $field['blacklist'] ) === true )
				return false;

			return true;
		}

		/**
		 *	Count field-shaped entries in an unknown submitted element branch
		 */
		private static function _submittedFieldCount( mixed $elements ): int {

			if( is_array( $elements ) === false )
				return 1;

			$count = 0;
			foreach( $elements as $fields )
				$count += is_array( $fields ) ? count( $fields ) : 1;

			return $count;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								"Pages" module: create/edit/delete the site's actual page
	 *												routes without hand-editing /nino/http/routes as raw json
	 *												(Config still covers everything this doesn't, see its own
	 *												docblock). A friendlier continuation of /_install's
	 *												Webpages step (see _install/Install.php's Webpages class -
	 *												not depended on here, same standalone-folder reasoning
	 *												every other class in this file follows) for once _install
	 *												has been deleted: template selection is restricted to
	 *												whichever templates/page-*.tpl files already exist on
	 *												disk - no copying, no library units, just wiring an
	 *												existing template up to a uri.
	 *
	 *												Same Element-URI/Http-URI split Webpages introduced (see
	 *												_routeKey()'s docblock for why), and the same single
	 *												source of truth: /nino/http/routes plus the
	 *												/webpage&lt;uri&gt;/* keys in /text/*.php. There is no
	 *												second list to keep in sync - pages() derives the whole
	 *												thing from the routes on every request, so a route
	 *												written here, in /_install or by hand in config.php is
	 *												the same page to all three. apiMove() swaps one page
	 *												route with its neighbor, the same ↑/↓ reordering
	 *												Webpages' own list does while _install is still around;
	 *												the route order is also what breaks a tie between two
	 *												equal menu priorities (see Modules\Navigation).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class PageEditor {

		// Kept in sync with _install's Webpages class. These routes are owned
		// at runtime by the optional tools themselves and therefore do not show
		// up in the persisted route array used for the general collision check.
		private const array RESERVED_HTTP_URIS = [ '/_admin', '/_editor', '/_install', '/_templates' ];

		// A fresh entry's text fields when none was posted (or a blank one) -
		// same generic-by-design reasoning _install/Install.php's
		// Webpages::DEFAULT_TEXT docblock explains
		private const array DEFAULT_TEXT = [
			'name' 				=> 'Page',
			'title' 			=> 'Page Title',
			'description' => 'Page description.',
		];

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'pages/list' 		=> [ self::class, 'apiList' ],
				'pages/save' 		=> [ self::class, 'apiSave' ],
				'pages/delete' 	=> [ self::class, 'apiDelete' ],
				'pages/move' 		=> [ self::class, 'apiMove' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'pages', 'Routes' ];
		}

		/**
		 *	How many pages are persisted - shared by Dashboard::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			return count( array_filter( $routes, fn( array $r, string $k ): bool => self::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) );
		}

		/**
		 *	List the page routes, every templates/page-*.tpl file available to
		 *	pick from, the active locales and the navigations a page can be
		 *	put into (empty while the Navigation module is inactive, which is
		 *	what tells the frontend to offer no menu fields at all)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$config 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$navs 		= self::navKeys( $appData );
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );

			\Nino\Http::ok( $request, [
				'pages' 			=> self::pages( $appData, $config['/nino/http/routes'] ?? [], $locales, $navs ),
				'templates' 	=> self::_templates( $appData ),
				'locales' 		=> $locales,
				'navs' 				=> $navs,
			] );
		}

		/**
		 *	Whether one route is a page this module manages.
		 *
		 *	A page is a GET route rendering a templates/page-*.tpl file -
		 *	the same criterion the template picker below already uses, and
		 *	the one thing that keeps robots.txt/sitemap.xml/llms.txt (GET
		 *	routes with their own headers and no page template) out of the
		 *	list. Matched on the body's prefix rather than through
		 *	_templateFromBody(), which deliberately reports null for a body
		 *	that resolves its file at runtime - /_install's "legal" unit's
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
		 *	The page list, derived from the routes and the text files rather
		 *	than from a second, parallel array that could drift against
		 *	them - /_install's Webpages step builds the very same shape from
		 *	the very same places (see its own pages()).
		 *
		 *	The route key is the Http-URI, the route's own 'uri' data field
		 *	the Element-URI, 'navs' the menu membership, and the per-locale
		 *	name/title/description are the /webpage&lt;uri&gt;/* keys both
		 *	tools write into /text/&lt;locale&gt;.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$locales			Available locales
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

				$httpUri 	= substr( $routeKey, strlen( 'GET:/' ) );
				$uri 			= (string) ( $route['uri'] ?? $httpUri );
				$body 		= (string) ( $route['body'] ?? '' );

				$entryText = [];
				foreach( $locales as $locale )
					$entryText[$locale] = [
						'name' 				=> (string) ( $text[$locale]['[[/webpage'. $uri. '/name]]'] 				?? '' ),
						'title' 			=> (string) ( $text[$locale]['[[/webpage'. $uri. '/title]]'] 			?? '' ),
						'description' => (string) ( $text[$locale]['[[/webpage'. $uri. '/description]]'] ?? '' ),
					];

				$pages[] = [
					'uri' 				=> $uri,
					'httpUri' 		=> $httpUri,
					'template' 		=> self::_templateFromBody( $body ) ?? '',
					'navs' 				=> array_values( array_intersect( $navKeys, array_keys( (array) ( $route['navs'] ?? [] ) ) ) ),
					'statusCode' 	=> (int) ( $route['statusCode'] ?? 200 ),
					'body' 				=> $body,
					'text' 				=> $entryText,
				];
			}

			return $pages;
		}

		/**
		 *	The navigations this project offers, in the order a menu picker
		 *	should list them: '/nino/html/navs', a plain list of keys.
		 *
		 *	Purely an editing affordance - Modules\Navigation renders whatever
		 *	key a template asks for, registered here or not, so a project that
		 *	never writes this array loses nothing but the checkboxes. Empty
		 *	while the Navigation module is inactive, which is what tells the
		 *	frontend to offer no menu fields at all (the old 'navModule' flag
		 *	said exactly that, and nothing else).
		 *
		 *	Own copy of /_install's Webpages::navKeys(), same standalone-per-
		 *	area reasoning every other helper in this class duplicates
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Nav keys, eg. [ 'main', 'footer' ]
		 */
		public static function navKeys( array &$appData ): array {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return [];

			$navs = $appData['/nino/html/navs'] ?? null;

			// A project from before this registry existed still has the single
			// menu the old generated fill was hardcoded to, so it keeps an
			// editable one rather than none. An array that *is* there and empty
			// is a deliberate "no menus at all" - the Navigations module can
			// delete the last one - and stays empty
			if( is_array( $navs ) === false )
				return [ 'main' ];

			return array_values( array_unique( array_map( 'strval', $navs ) ) );
		}

		/**
		 *	Which navigations one posted entry asks to be in, narrowed to the
		 *	navigations this project actually registers.
		 *
		 *	'nav' =&gt; true is what a frontend written before per-menu
		 *	membership existed posts, and means the first registered
		 *	navigation - the single menu an entry could have been in back then
		 *
		 *	@param		array 		$entry				One posted page entry
		 *	@param		array 		$navs					Registered nav keys (see navKeys())
		 *
		 *	@return 	array										Nav keys this entry belongs to
		 */
		public static function entryNavs( array $entry, array $navs ): array {

			if( isset( $entry['navs'] ) === true && is_array( $entry['navs'] ) === true )
				return array_values( array_intersect( $navs, $entry['navs'] ) );

			return ( $entry['nav'] ?? false ) === true ? array_slice( $navs, 0, 1 ) : [];
		}

		/**
		 *	Every templates/page-*.tpl file on disk, sorted, stripped of its
		 *	extension - the [template ...] shortcode's own path argument
		 *	(see \Nino\Modules\Template::doShortcode()), and the whitelist
		 *	apiSave() checks a posted template against so this can never be
		 *	pointed at an arbitrary file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _templates( array &$appData ): array {

			$files = glob( \Nino\Filesystem::getPath( $appData ). '/templates/page-*.tpl' ) ?: [];
			$names = array_map( fn( string $file ): string => basename( $file, '.tpl' ), $files );

			sort( $names );

			return $names;
		}

		/**
		 *	'/foo', '/foo/', 'foo' all normalize to '/foo'; '/' stays '/'.
		 *	Rejects anything empty, without a leading slash once normalized
		 *	is impossible anyway, or containing '..'/characters outside a
		 *	plain path - same rules _install/Install.php's
		 *	Webpages::_normalizeUri() enforces, duplicated rather than
		 *	depended on (this folder stays standalone, see class docblock)
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
		 *	The /nino/http/routes array key one page entry occupies - always
		 *	derived from its httpUri (the real, reachable path), never its
		 *	uri (a stable identifier used only for the route's own 'uri'
		 *	data field and this entry's /webpage&lt;uri&gt;/* text meta):
		 *	\Nino\Http::requestRoute() matches a route by looking up
		 *	'&lt;METHOD&gt;:/'.$httpUri as a literal array key, not by scanning
		 *	for a route whose own 'uri' field matches - see
		 *	_install/Install.php's Webpages::_routeKeys() docblock for the
		 *	full reasoning (identical here, just GET-only and without that
		 *	class's multi-route-manifest case, since a page here is always
		 *	exactly one template)
		 *
		 *	@param		string		$httpUri
		 *
		 *	@return 	string
		 */
		private static function _routeKey( string $httpUri ): string {
			return 'GET://'. trim( $httpUri, '/' );
		}

		/**
		 *	The on-disk template a route body names, when it names exactly
		 *	one. Bodies /_install's library ships aren't always a plain
		 *	template reference (its "legal" unit resolves the file per locale
		 *	via [[/nino/http/response/locale]]); null reports that rather
		 *	than handing back something shaped like a filename but isn't one,
		 *	which is what keeps apiSave() from flattening such an entry. Kept
		 *	as its own copy rather than reaching into _install, same as every
		 *	other helper in this class - /_install is meant to be deleted
		 *
		 *	@param		string		$body					A route's body
		 *
		 *	@return 	string|null							Null if $body isn't a plain template reference
		 */
		private static function _templateFromBody( string $body ): ?string {
			return preg_match( '~^\[template /templates/([A-Za-z0-9._-]+)\]$~', trim( $body ), $match ) === 1 ? $match[1] : null;
		}

		/**
		 *	Create a brand new page entry, or save an existing one -
		 *	identified by $data['originalHttpUri'] (empty for a new entry),
		 *	since httpUri is this list's real, unique identity. Both uris
		 *	are independently validated and deduped against every *other*
		 *	entry (the one being edited, found via originalHttpUri, is
		 *	excluded from its own dedupe check, so re-saving an entry
		 *	unchanged - or renaming only one of its two uris - never
		 *	falsely collides with itself); template is checked against
		 *	_templates()'s whitelist.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 						= \Nino\Admin\Admin::postData();
			$originalHttpUri 	= (string) ( $data['originalHttpUri'] ?? '' );

			$uri 			= self::_normalizeUri( (string) ( $data['uri'] ?? '' ) );
			$httpUri 	= self::_normalizeUri( (string) ( $data['httpUri'] ?? '' ) );
			$template = (string) ( $data['template'] ?? '' );

			if( $uri === null ) {
				\Nino\Http::fail( $request, 400, 'invalid uri: "'. ( (string) ( $data['uri'] ?? '' ) ). '"' );
				return;
			}

			if( $httpUri === null ) {
				\Nino\Http::fail( $request, 400, 'invalid http uri: "'. ( (string) ( $data['httpUri'] ?? '' ) ). '"' );
				return;
			}

			if( in_array( $httpUri, self::RESERVED_HTTP_URIS, true ) === true ) {
				\Nino\Http::fail( $request, 409, 'reserved http uri: "'. $httpUri. '"' );
				return;
			}


			$statusCode = (int) ( $data['statusCode'] ?? 200 );
			if( $statusCode < 100 || $statusCode > 599 )
				$statusCode = 200;

			$locales = \Nino\Locales::getAvailableLocales( $appData );

			$text = [];
			foreach( $locales as $locale ) {
				$row = (array) ( $data['text'][$locale] ?? [] );
				$text[$locale] = [
					'name' 				=> self::_orDefault( $row['name'] 				?? '', self::DEFAULT_TEXT['name'] ),
					'title' 			=> self::_orDefault( $row['title'] 			?? '', self::DEFAULT_TEXT['title'] ),
					'description' => self::_orDefault( $row['description'] ?? '', self::DEFAULT_TEXT['description'] ),
				];
			}

			$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$routes = $config['/nino/http/routes'] ?? [];
			$navKeys = self::navKeys( $appData );
			$pages 	= self::pages( $appData, $routes, $locales, $navKeys );

			$selfIndex = null;

			foreach( $pages as $index => $existing ) {

				if( $originalHttpUri !== '' && ( $existing['httpUri'] ?? null ) === $originalHttpUri ) {
					$selfIndex = $index;
					continue;
				}

				if( ( $existing['uri'] ?? null ) === $uri ) {
					\Nino\Http::fail( $request, 400, 'duplicate uri: "'. $uri. '"' );
					return;
				}

				if( ( $existing['httpUri'] ?? null ) === $httpUri ) {
					\Nino\Http::fail( $request, 400, 'duplicate http uri: "'. $httpUri. '"' );
					return;
				}
			}

			$routeKey 			= self::_routeKey( $httpUri );
			$previousRouteKey = $selfIndex !== null ? self::_routeKey( (string) $pages[$selfIndex]['httpUri'] ) : null;

			// Every route counts here, not just the page ones: a page may
			// never take a uri robots.txt, a module or a developer already
			// answers on
			if( isset( $routes[$routeKey] ) === true && $routeKey !== $previousRouteKey ) {
				\Nino\Http::fail( $request, 409, 'http uri already belongs to another route: "'. $httpUri. '"' );
				return;
			}

			$previous = $selfIndex !== null ? $pages[$selfIndex] : [];

			$body = '[template /templates/'. $template. ']';

			// A route body /_install's library shipped can be more than a
			// plain template reference - its "legal" unit picks the template
			// file per locale via [[/nino/http/response/locale]] - and the
			// template <select> has no way to spell that. Keep the body such
			// an entry already carries instead of flattening it into
			// whichever single option happened to be preselected
			if( isset( $previous['body'] ) === true && self::_templateFromBody( (string) $previous['body'] ) === null ) {
				$body 		= (string) $previous['body'];
				// ...and with it the template field, which for such an entry
				// names nothing: the disabled <select> still posts whichever
				// option the browser preselected, and storing that would
				// leave the list claiming a template this page never uses
				$template = (string) ( $previous['template'] ?? '' );
			}

			// Checked here rather than up front: an entry whose body the
			// <select> can't spell keeps the template field it already had
			// (empty, for /_install's locale-resolving "legal" unit), and
			// posts an empty value from its own disabled option - neither of
			// which names a real file, and neither of which is an error
			if( $body === '[template /templates/'. $template. ']' && in_array( $template, self::_templates( $appData ), true ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown template: "'. $template. '"' );
				return;
			}

			// Menu membership lives on the route and nowhere else - that is
			// what Modules\Navigation::routeLines() reads, and the only copy
			// that renders
			$navs = self::entryNavs( $data, $navKeys );

			$routeData = [ 'uri' => $uri, 'body' => $body ];
			if( $statusCode !== 200 )
				$routeData['statusCode'] = $statusCode;

			// A priority someone tuned on this route is not this save's to
			// reset - only the set of keys is rewritten (see the shortcode's
			// own routeLines() for what the value means). A membership this
			// save adds starts behind everything already in that menu
			$prios = $routes[$previousRouteKey ?? $routeKey]['navs'] ?? [];
			foreach( $navs as $navKey )
				$routeData['navs'][$navKey] = (int) ( $prios[$navKey] ?? self::_nextPrio( $routes, $navKey ) );

			$routes = self::_putRoute( $routes, $previousRouteKey, $routeKey, $routeData );

			$appData['/nino/http/routes'] = $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

			foreach( $locales as $locale )
				self::_mergeText( $appData, '/text/'. $locale. '.php', [
					'[[/webpage'. $uri. '/name]]' 				=> $text[$locale]['name'],
					'[[/webpage'. $uri. '/title]]' 			=> $text[$locale]['title'],
					'[[/webpage'. $uri. '/description]]' => $text[$locale]['description'],
				] );

			\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, $routes, $locales, $navKeys ) ] );
		}

		/**
		 *	Where a membership this save newly adds goes: behind everything
		 *	already in that menu. Read across every route rather than just the
		 *	page ones, so a hand-written route in the same menu is counted too
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string		$navKey				Eg. 'main'
		 *
		 *	@return 	int
		 */
		private static function _nextPrio( array $routes, string $navKey ): int {

			$last = 0;
			foreach( $routes as $route )
				$last = max( $last, (int) ( $route['navs'][$navKey] ?? 0 ) );

			return $last + 1;
		}

		/**
		 *	Write one route, keeping the slot it already occupies even when
		 *	its key changes: the route array's own order is what breaks a tie
		 *	between two equal menu priorities (see
		 *	Modules\Navigation::routeLines()) and what the list in the browser
		 *	is sorted by, so renaming a page's http uri must not quietly move
		 *	it to the bottom of both
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string|null	$previousKey	The key this route currently occupies, null for a new one
		 *	@param		string		$routeKey			The key it should occupy afterwards
		 *	@param		array 		$routeData
		 *
		 *	@return 	array
		 */
		private static function _putRoute( array $routes, ?string $previousKey, string $routeKey, array $routeData ): array {

			if( $previousKey === null || isset( $routes[$previousKey] ) === false ) {
				$routes[$routeKey] = $routeData;
				return $routes;
			}

			$out = [];

			foreach( $routes as $key => $route )
				if( $key === $previousKey )
					$out[$routeKey] = $routeData;
				else
					$out[$key] = $route;

			return $out;
		}

		/**
		 *	Remove one page route. Its /webpage&lt;uri&gt;/* text meta is
		 *	deliberately left in place - same additive-only philosophy every
		 *	other apply/save in this codebase follows, deleting a file/key a
		 *	developer may have since hand-edited is a much riskier "undo" than
		 *	dropping one route
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$httpUri 	= (string) ( \Nino\Admin\Admin::postData()['httpUri'] ?? '' );
			$routes 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
			$routeKey = self::_routeKey( $httpUri );

			// Only ever a page route: this module lists nothing else, so
			// anything else under that key is a module's or a developer's and
			// not this button's to delete
			if( isset( $routes[$routeKey] ) === false || self::isPageRoute( $routeKey, $routes[$routeKey] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page' );
				return;
			}

			unset( $routes[$routeKey] );

			$appData['/nino/http/routes'] = $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

			\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, $routes, \Nino\Locales::getAvailableLocales( $appData ), self::navKeys( $appData ) ) ] );
		}

		/**
		 *	Swap one page route with its immediate neighbor - the order this
		 *	list stands in, and the tie-breaker between two equal menu
		 *	priorities (see Modules\Navigation::routeLines()). A swap rather
		 *	than an arbitrary posted index: the list itself never leaves the
		 *	browser, only which of two neighbors should trade places - nothing
		 *	to validate beyond "is this still a valid direction from here"
		 *
		 *	Only page routes take part. Everything else in the array - a
		 *	module's route, a hand-written one - keeps the exact slot it
		 *	stands in, so reordering pages can never reshuffle a route this
		 *	module does not own
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiMove( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 			= \Nino\Admin\Admin::postData();
			$httpUri 		= (string) ( $data['httpUri'] ?? '' );
			$direction 	= (string) ( $data['direction'] ?? '' );

			if( in_array( $direction, [ 'up', 'down' ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'direction must be "up" or "down"' );
				return;
			}

			$routes 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
			$pageKeys = array_keys( array_filter( $routes, fn( array $r, string $k ): bool => self::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) );

			$index = array_search( self::_routeKey( $httpUri ), $pageKeys, true );

			if( $index === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page' );
				return;
			}

			$swapWith = $direction === 'up' ? $index - 1 : $index + 1;

			if( $swapWith < 0 || $swapWith >= count( $pageKeys ) ) {
				\Nino\Http::fail( $request, 400, 'already at the '. ( $direction === 'up' ? 'top' : 'bottom' ) );
				return;
			}

			[ $pageKeys[$index], $pageKeys[$swapWith] ] = [ $pageKeys[$swapWith], $pageKeys[$index] ];

			// Refill the slots the page routes occupy, in the swapped order -
			// every other route stays exactly where it was
			$ordered 	= [];
			$next 		= 0;

			foreach( $routes as $routeKey => $route )
				if( self::isPageRoute( $routeKey, $route ) === true ) {
					$ordered[ $pageKeys[$next] ] = $routes[ $pageKeys[$next] ];
					$next++;
				} else {
					$ordered[$routeKey] = $route;
				}

			$appData['/nino/http/routes'] = $ordered;

			\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

			\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, $ordered, \Nino\Locales::getAvailableLocales( $appData ), self::navKeys( $appData ) ) ] );
		}

		/**
		 *	Merge a text fragment's keys into a /text/*.php file - later
		 *	keys win a key collision
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
		 *	every save rather than merge on top of a possibly-stale
		 *	previous value
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
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								"Navigations" module: which menus this project has, and
	 *												which routes stand in each of them, in which order.
	 *
	 *												The other half of what the Routes module edits. Over
	 *												there a page ticks the menus it belongs to, one page at
	 *												a time; here one menu is opened and its whole running
	 *												order set - which is the only view in which "third entry
	 *												in the footer" is a thing you can see, let alone move.
	 *
	 *												Two config keys, and no third copy of anything:
	 *												'/nino/html/navs' is the plain list of menu keys both
	 *												page editors offer a checkbox for, and each route's own
	 *												'navs' =&gt; [ &lt;key&gt; =&gt; &lt;prio&gt; ] is the membership that
	 *												actually renders (see \Nino\Modules\Navigation).
	 *												Priorities are kept dense, 1..n per menu, so a position
	 *												in this list reads as the position in the menu rather
	 *												than as an arbitrary number someone has to space out by
	 *												hand. A hand-written route joins a menu exactly the same
	 *												way and shows up here like any other.
	 *
	 *												No locale picker, deliberately: a menu has nothing
	 *												per-locale about it. The wording it renders is each
	 *												page's own /webpage&lt;uri&gt;/name key, edited in Routes (or
	 *												in Text), and the same running order serves every
	 *												language.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Navigations {

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'navs/list' 			=> [ self::class, 'apiList' ],
				'navs/save' 			=> [ self::class, 'apiSave' ],
				'navs/delete' 		=> [ self::class, 'apiDelete' ],
				'navs/assign' 		=> [ self::class, 'apiAssign' ],
				'navs/unassign' 	=> [ self::class, 'apiUnassign' ],
				'navs/move' 			=> [ self::class, 'apiMove' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'navs', 'Navigations' ];
		}

		/**
		 *	A menu key ends up as a config array key and inside a template's
		 *	[navigation nav="main"] argument, so it stays a plain lowercase
		 *	slug - the same shape every other key this tool creates has
		 *
		 *	@param		string		$key
		 *
		 *	@return 	bool
		 */
		private static function isValidKey( string $key ): bool {
			return preg_match( '#^[a-z][a-z0-9_-]*$#', $key ) === 1;
		}

		/**
		 *	Every menu, each with the routes standing in it in their running
		 *	order, plus every route that could be added to one.
		 *
		 *	Also the whole response of every other action here: each of them
		 *	changes what this returns, and the frontend simply re-renders from
		 *	it rather than patching its own copy
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, self::_payload( $appData ) );
		}

		/**
		 *	Create a menu, or rename one - identified by $data['originalKey']
		 *	(empty for a new one). A rename follows the key everywhere it is
		 *	used as a key: the registry, keeping its place in it, and every
		 *	route that is a member, keeping its priority. Templates are
		 *	deliberately not rewritten - a [navigation nav="..."] argument is
		 *	content, and silently editing template files out from under a
		 *	developer is not this dialog's business (the renamed menu simply
		 *	renders nowhere until the template is updated too)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 				= \Nino\Admin\Admin::postData();
			$key 					= trim( (string) ( $data['key'] ?? '' ) );
			$originalKey 	= trim( (string) ( $data['originalKey'] ?? '' ) );

			if( self::isValidKey( $key ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid navigation id: "'. $key. '"' );
				return;
			}

			$registry = self::registry( $appData );
			$routes 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			// Free means free everywhere, not just in the registry: a route
			// carrying an unregistered menu key by hand would otherwise have
			// its two memberships silently collapsed into one by the rename
			// below - and a "new" menu would start out with members nobody
			// put in it
			if( $key !== $originalKey && self::_isTaken( $key, $registry, $routes ) === true ) {
				\Nino\Http::fail( $request, 409, 'navigation id already taken: "'. $key. '"' );
				return;
			}

			if( $originalKey === '' ) {

				$registry[] = $key;

				$appData['/nino/html/navs'] = $registry;

				\Nino\AppData::writeContentData( $appData, [ '/nino/html/navs' ] );

				\Nino\Http::ok( $request, self::_payload( $appData ) );
				return;
			}

			$index = array_search( $originalKey, $registry, true );

			if( $index === false ) {
				\Nino\Http::fail( $request, 404, 'unknown navigation' );
				return;
			}

			$registry[$index] = $key;

			foreach( $routes as $routeKey => $route ) {

				if( isset( $route['navs'][$originalKey] ) === false )
					continue;

				// Rebuilt rather than added-and-unset, so the renamed key keeps
				// the place it had among this route's other memberships
				$renamed = [];
				foreach( $route['navs'] as $navKey => $prio )
					$renamed[ $navKey === $originalKey ? $key : $navKey ] = $prio;

				$routes[$routeKey]['navs'] = $renamed;
			}

			$appData['/nino/html/navs'] 	= $registry;
			$appData['/nino/http/routes'] = $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/navs', '/nino/http/routes' ] );

			\Nino\Http::ok( $request, self::_payload( $appData ) );
		}

		/**
		 *	Remove a menu: out of the registry, and off every route that was
		 *	a member. Templates asking for it by name are left alone, same
		 *	reasoning as apiSave()'s rename - the menu they name simply
		 *	renders empty afterwards
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$key 			= (string) ( \Nino\Admin\Admin::postData()['key'] ?? '' );
			$registry = self::registry( $appData );
			$index 		= array_search( $key, $registry, true );

			if( $index === false ) {
				\Nino\Http::fail( $request, 404, 'unknown navigation' );
				return;
			}

			unset( $registry[$index] );

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			foreach( $routes as $routeKey => $route ) {

				if( isset( $route['navs'][$key] ) === false )
					continue;

				unset( $routes[$routeKey]['navs'][$key] );

				// A route in no menu at all carries no 'navs' rather than an
				// empty one - the same shape /_install writes (see its
				// _applyWebpage()), so a config.php stays readable by hand
				if( count( $routes[$routeKey]['navs'] ) === 0 )
					unset( $routes[$routeKey]['navs'] );
			}

			$appData['/nino/html/navs'] 	= array_values( $registry );
			$appData['/nino/http/routes'] = $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/navs', '/nino/http/routes' ] );

			\Nino\Http::ok( $request, self::_payload( $appData ) );
		}

		/**
		 *	Put one route into a menu, at the end of it - a menu is a running
		 *	order, and "somewhere in the middle" is not something an add
		 *	button can guess. Moving it up from there is one click per step
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiAssign( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$routeKey = self::_routeKey( (string) ( $data['httpUri'] ?? '' ) );

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			if( in_array( $key, self::registry( $appData ), true ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown navigation' );
				return;
			}

			if( isset( $routes[$routeKey] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown route' );
				return;
			}

			if( isset( $routes[$routeKey]['navs'][$key] ) === true ) {
				\Nino\Http::fail( $request, 409, 'route is already in this navigation' );
				return;
			}

			$order 	 = self::_members( $routes, $key );
			$order[] = $routeKey;

			$appData['/nino/http/routes'] = self::_applyOrder( $routes, $key, $order );

			\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

			\Nino\Http::ok( $request, self::_payload( $appData ) );
		}

		/**
		 *	Take one route back out of a menu. Only its membership goes - the
		 *	route itself, and everything else about it, is the Routes module's
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUnassign( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$routeKey = self::_routeKey( (string) ( $data['httpUri'] ?? '' ) );

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			if( isset( $routes[$routeKey]['navs'][$key] ) === false ) {
				\Nino\Http::fail( $request, 404, 'route is not in this navigation' );
				return;
			}

			unset( $routes[$routeKey]['navs'][$key] );

			if( count( $routes[$routeKey]['navs'] ) === 0 )
				unset( $routes[$routeKey]['navs'] );

			// The gap the removed entry left is closed right away, so the
			// numbers in config.php keep reading as positions
			$appData['/nino/http/routes'] = self::_applyOrder( $routes, $key, self::_members( $routes, $key ) );

			\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

			\Nino\Http::ok( $request, self::_payload( $appData ) );
		}

		/**
		 *	Swap one entry with its neighbor inside one menu - the same ↑/↓
		 *	reordering the Routes module's own list has, except that this one
		 *	really is the menu's order rather than the route array's
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiMove( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 			= \Nino\Admin\Admin::postData();
			$key 				= (string) ( $data['key'] ?? '' );
			$routeKey 	= self::_routeKey( (string) ( $data['httpUri'] ?? '' ) );
			$direction 	= (string) ( $data['direction'] ?? '' );

			if( in_array( $direction, [ 'up', 'down' ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'direction must be "up" or "down"' );
				return;
			}

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
			$order 	= self::_members( $routes, $key );
			$index 	= array_search( $routeKey, $order, true );

			if( $index === false ) {
				\Nino\Http::fail( $request, 404, 'route is not in this navigation' );
				return;
			}

			$swapWith = $direction === 'up' ? $index - 1 : $index + 1;

			if( $swapWith < 0 || $swapWith >= count( $order ) ) {
				\Nino\Http::fail( $request, 400, 'already at the '. ( $direction === 'up' ? 'top' : 'bottom' ) );
				return;
			}

			[ $order[$index], $order[$swapWith] ] = [ $order[$swapWith], $order[$index] ];

			$appData['/nino/http/routes'] = self::_applyOrder( $routes, $key, $order );

			\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

			\Nino\Http::ok( $request, self::_payload( $appData ) );
		}

		/**
		 *	The menu keys this project registered, in the order a picker
		 *	should list them.
		 *
		 *	Unlike PageEditor::navKeys() this is not gated on the Navigation
		 *	module being active: the registry is editable either way, and the
		 *	frontend is told separately (see _payload()'s 'active') so it can
		 *	say so rather than show an empty dialog for no visible reason
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Nav keys, eg. [ 'main', 'footer' ]
		 */
		public static function registry( array &$appData ): array {

			$navs = $appData['/nino/html/navs'] ?? null;

			// Same legacy rule PageEditor::navKeys() applies, so both areas
			// offer the same menus: a project from before this registry
			// existed has the one menu the old generated fill was hardcoded
			// to, while a registry that is there and empty stays empty
			if( is_array( $navs ) === false )
				return [ 'main' ];

			return array_values( array_unique( array_map( 'strval', $navs ) ) );
		}

		/**
		 *	Whether a menu key is already in use - registered, or carried by
		 *	a route that was wired up by hand without registering it
		 *
		 *	@param		string		$key
		 *	@param		array 		$registry			See registry()
		 *	@param		array 		$routes				The persisted route array
		 *
		 *	@return 	bool
		 */
		private static function _isTaken( string $key, array $registry, array $routes ): bool {

			if( in_array( $key, $registry, true ) === true )
				return true;

			foreach( $routes as $route )
				if( isset( $route['navs'][$key] ) === true )
					return true;

			return false;
		}

		/**
		 *	The route keys standing in one menu, in their running order -
		 *	priority first, the route array's own order breaking a tie, which
		 *	is exactly the order \Nino\Modules\Navigation::routeLines() renders
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string		$navKey
		 *
		 *	@return 	array										Route keys, eg. [ 'GET://', 'GET://contact' ]
		 */
		private static function _members( array $routes, string $navKey ): array {

			$members = [];
			foreach( $routes as $routeKey => $route )
				if( str_starts_with( $routeKey, 'GET://' ) === true && isset( $route['navs'][$navKey] ) === true )
					$members[$routeKey] = (int) $route['navs'][$navKey];

			// asort() is stable as of php 8, so equal priorities keep the
			// order the routes stand in rather than an arbitrary one
			asort( $members );

			return array_keys( $members );
		}

		/**
		 *	Write one menu's running order back onto its routes as dense
		 *	priorities, 1..n. Renumbering on every change is what keeps the
		 *	numbers in config.php readable as positions - and what makes
		 *	"move up" a swap of two adjacent numbers rather than arithmetic
		 *	on whatever gaps a previous edit happened to leave
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string		$navKey
		 *	@param		array 		$order				Route keys, in the intended order
		 *
		 *	@return 	array
		 */
		private static function _applyOrder( array $routes, string $navKey, array $order ): array {

			$prio = 1;
			foreach( $order as $routeKey )
				if( isset( $routes[$routeKey] ) === true )
					$routes[$routeKey]['navs'][$navKey] = $prio++;

			return $routes;
		}

		/**
		 *	Mirrors PageEditor::_routeKey() - a page's route key is derived
		 *	from its Http-URI, never its Element-URI (see that method's own
		 *	docblock for the full reasoning)
		 *
		 *	@param		string		$httpUri
		 *
		 *	@return 	string
		 */
		private static function _routeKey( string $httpUri ): string {
			return 'GET://'. trim( $httpUri, '/' );
		}

		/**
		 *	Everything the dialog draws itself from: the menus with their
		 *	members in order, every route that could join one, and whether
		 *	the Navigation module that renders any of this is even active
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _payload( array &$appData ): array {

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
			$labels = self::_labels( $appData, $routes );

			$navs = [];
			foreach( self::registry( $appData ) as $key ) {

				$entries = [];
				foreach( self::_members( $routes, $key ) as $routeKey )
					$entries[] = $labels[$routeKey];

				$navs[] = [ 'key' => $key, 'entries' => $entries ];
			}

			return [
				'navs' 		=> $navs,
				'routes' 	=> array_values( $labels ),
				'active' 	=> in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ),
			];
		}

		/**
		 *	Every route a menu could contain, labelled the way the menu would
		 *	label it.
		 *
		 *	Every GET route qualifies, not just the page ones the Routes
		 *	module manages - a menu entry is only ever "a path with a name",
		 *	and a route a module or a developer owns is as good a target as
		 *	any. 'named' reports whether the /webpage&lt;uri&gt;/name key the menu
		 *	renders from exists at all: a route without one is skipped by
		 *	\Nino\Modules\Navigation::routeLines(), so offering it silently
		 *	would be offering an entry that never shows up
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The persisted route array
		 *
		 *	@return 	array										Route key => { httpUri, uri, label, named }
		 */
		private static function _labels( array &$appData, array $routes ): array {

			$text 	= \Nino\Filesystem::getFileContent( $appData, '/text/'. \Nino\Locales::getNativeLocale( $appData ). '.php', [] );
			$labels = [];

			foreach( $routes as $routeKey => $route ) {

				if( str_starts_with( $routeKey, 'GET://' ) === false )
					continue;

				$httpUri 	= substr( $routeKey, strlen( 'GET:/' ) );
				$uri 			= (string) ( $route['uri'] ?? $httpUri );
				$name 		= (string) ( $text['[[/webpage'. $uri. '/name]]'] ?? '' );

				$labels[$routeKey] = [
					'httpUri' => $httpUri,
					'uri' 		=> $uri,
					'label' 	=> $name !== '' ? $name : $httpUri,
					'named' 	=> $name !== '',
				];
			}

			return $labels;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Dashboard: landing panel with the numbers the other
	 *												modules already compute (element types, admin accounts,
	 *												last backup, missing text keys, missing image slots),
	 *												pulled together into one overview instead of having to
	 *												open each tab in turn to find them. Doesn't add any
	 *												storage of its own
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Dashboard {

		/**
		 *	This module's action map, merged into Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'dashboard/summary' => [ self::class, 'apiSummary' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'dashboard', 'Dashboard' ];
		}

		/**
		 *	Gather the overview numbers
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSummary( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			\Nino\Http::ok( $request, [
				'types' 					=> ElementTypes::summaries( $appData ),
				'pages' 					=> PageEditor::count( $appData ),
				'users' 					=> Users::count( $appData ),
				'lastBackup' 			=> Restore::lastDate( $appData ),
				'missingText' 		=> Text::missingCount( $appData ),
				'missingImages' 	=> Images::missingCount( $appData ),
			] );
		}
	}
}
