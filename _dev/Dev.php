<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Dev								Dev-only tooling - all \Nino\Dev\* classes live in this single
 *												file, same convention as _nino/Nino.php and _admin/Admin.php.
 *												Meant to be deployed only when actually needed and removed
 *												afterwards (rm -rf _dev) - not part of the shipped admin
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
	// `php _dev/Dev.php <pw>`
	if( php_sapi_name() === 'cli' && isset( $argv[1] ) === true )
		die( password_hash( $argv[1], PASSWORD_DEFAULT ). PHP_EOL );
}

namespace Nino\Dev {

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
	class Dev {

		// Generate with: php _dev/Dev.php <your password>
		// CHANGE THIS before deploying - the shipped value matches no password
		private const string PASSWORD_HASH = '$2y$10$JeWFJ0CAi.tAEc6i5IcyTOLSgeCbrspmb4p0Re7aWvJBOajXtwgt.';

		private const int MAX_TRIES = 5;
		private const int COOLDOWN = 3600;

		private const array MODULES = [
			\Nino\Dev\Dashboard::class,
			\Nino\Dev\ElementTypes::class,
			\Nino\Dev\Text::class,
			\Nino\Dev\Images::class,
			\Nino\Dev\Users::class,
			\Nino\Dev\Restore::class,
			\Nino\Dev\Config::class,
		];

		/**
		 *	Register the /_dev route and its response callbacks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$appData['/nino/http/routes'] += [
				'GET://_dev' 	=> [
					'uri' 				=> '/_dev',
					'body'				=> '[template /_dev/templates/page-index]',
					'statusCode'	=> 200
				],
				'POST://_dev'	=> [ 'uri' => '/_dev' ],
			];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_dev', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_dev', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_dev response: the tool dashboard if authed, else the login form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			if( self::isAuthed( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_dev/templates/page-login]';
		}

		/**
		 *	Fill the POST /_dev response. Every dev api request goes through
		 *	this single route, dispatched by $_POST['action'] - same shape as
		 *	Admin::handlePost(), except the login action itself must stay
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
				$request['/nino/http/response']['statusCode'] = 401;
				$request['/nino/http/response']['body'] = [ 'error' => 'not logged in' ];
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
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown action' ];
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
			return \Nino\Runtime::getSessionValue( $appData, './nino/dev/authed', false ) === true;
		}

		/**
		 *	Require an authed session - shared by every module's api methods
		 *	(same role as Admin::guard())
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
				$request['/nino/http/response']['statusCode'] = 401;
				$request['/nino/http/response']['body'] = [ 'error' => 'not logged in' ];
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

			$state = \Nino\Filesystem::getFileContent( $appData, '/_dev/.lockout.json', [ 'tries' => 0, 'until' => 0 ] );

			if( (int) $state['until'] > time() ) {
				$request['/nino/http/response']['statusCode'] = 429;
				$request['/nino/http/response']['body'] = [ 'error' => 'too many attempts' ];
				return;
			}

			$password = (string) ( json_decode( $_POST['data'] ?? '{}', true )['password'] ?? '' );

			if( password_verify( $password, self::PASSWORD_HASH ) === false ) {

				$state['tries'] = (int) $state['tries'] + 1;
				if( $state['tries'] >= self::MAX_TRIES ) {
					$state['tries'] = 0;
					$state['until'] = time() + self::COOLDOWN;
				}
				\Nino\Filesystem::putFileContent( $appData, '/_dev/.lockout.json', $state );

				$request['/nino/http/response']['statusCode'] = 401;
				$request['/nino/http/response']['body'] = [ 'error' => 'wrong password' ];
				return;
			}

			\Nino\Filesystem::putFileContent( $appData, '/_dev/.lockout.json', [ 'tries' => 0, 'until' => 0 ] );

			// Defend against session fixation, same as Auth::loginUser()
			session_regenerate_id( true );
			\Nino\Runtime::setSessionValue( $appData, './nino/dev/authed', true );
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
			\Nino\Runtime::unsetSessionValue( $appData, './nino/dev/authed' );
		}

		/**
		 *	Decode the json-encoded "data" POST field every module action reads
		 *	its payload from - same shape as Admin::postData(), duplicated
		 *	rather than depending on _admin/Admin.php, since this whole folder
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
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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
		 *	List every element type with its title and field count
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

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

			$request['/nino/http/response']['body'] = [ 'types' => $types, 'fieldTypes' => self::FIELD_TYPES ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$typeUri = (string) ( \Nino\Dev\Dev::postData()['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid type uri' ];
				return;
			}

			$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', false );

			if( $typeData === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown type' ];
				return;
			}

			$request['/nino/http/response']['body'] = [
				'uri' 		=> $typeUri,
				'title' 	=> $typeData['title'] ?? $typeUri,
				'model' 	=> $typeData['model'] ?? [],
			];
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

				if( ( $data['required'] ?? false ) === true )
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Dev\Dev::postData();
			$typeUri = (string) ( $data['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid type uri' ];
				return;
			}

			\Nino\Filesystem::lockFile( $appData, '/elements/'. $typeUri. '.php' );
			$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', false );

			if( $typeData === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown type' ];
				return;
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

			\Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData, true );

			$request['/nino/http/response']['body'] = [ 'uri' => $typeUri, 'title' => $typeData['title'], 'model' => $typeData['model'] ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Dev\Dev::postData();
			$typeUri 	= (string) ( $data['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid type uri' ];
				return;
			}

			if( \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', '' ) !== '' ) {
				$request['/nino/http/response']['statusCode'] = 409;
				$request['/nino/http/response']['body'] = [ 'error' => 'type already exists' ];
				return;
			}

			$title = trim( (string) ( $data['title'] ?? '' ) );

			$typeData = [
				'title' 	=> ( $title !== '' ) ? $title : $typeUri,
				'model' 	=> self::cleanModel( $data['model'] ?? [] ),
				'*' 			=> [ '*' => [] ],
			];

			\Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData );

			$request['/nino/http/response']['body'] = [ 'uri' => $typeUri, 'title' => $typeData['title'], 'model' => $typeData['model'] ];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Restore: lists and restores the encrypted daily backups
	 *											_admin/Admin.php's Backup class creates. Deliberately
	 *											independent of _admin/Admin.php's own code (duplicates the
	 *											small bit of archiving logic it needs, same reasoning as
	 *											postData() above - this whole folder stays standalone) and
	 *											of config.php's own data:
	 *											- the backup directory is *found* by globbing _admin/ for
	 *											  its one-time random name, not read from a config value
	 *											- the decryption key has its own independent copy here
	 *											  (_dev/.restore-key.php), written once by
	 *											  Backup::_bootstrap() the first time it runs
	 *											So this still works even if config.php's *data* (not
	 *											syntax) is what's broken - eg. a wrecked admin user
	 *											record. A genuine config.php syntax error is out of scope:
	 *											_dev boots through the same kernel bootstrap as _admin
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
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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
		 *	Find Backup's one random backup directory under _admin/
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false
		 */
		private static function _backupDir( array &$appData ): string|false {
			$matches = glob( \Nino\Filesystem::getPath( $appData ). '/_admin/.backups-*', GLOB_ONLYDIR );
			return $matches[0] ?? false;
		}

		/**
		 *	Read the encryption key's own independent copy under _dev/
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false						Raw (not base64) key bytes
		 */
		private static function _key( array &$appData ): string|false {

			$path = \Nino\Filesystem::getPath( $appData ). '/_dev/.restore-key.php';

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
		 *	List available backup dates, most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$dir 	= self::_backupDir( $appData );
			$dates 	= [];

			if( $dir !== false )
				foreach( glob( $dir. '/*.php' ) ?: [] as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$dates[] = basename( $file, '.php' );

			rsort( $dates );

			$request['/nino/http/response']['body'] = [ 'dates' => $dates ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$date = (string) ( \Nino\Dev\Dev::postData()['date'] ?? '' );

			if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) !== 1 ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid date' ];
				return;
			}

			$dir = self::_backupDir( $appData );
			$key = self::_key( $appData );

			if( $dir === false || $key === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'no backup available' ];
				return;
			}

			$path = $dir. '/'. $date. '.php';

			if( is_file( $path ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown backup date' ];
				return;
			}

			$gz = self::_decrypt( $path, $key );

			if( $gz === false ) {
				$request['/nino/http/response']['statusCode'] = 500;
				$request['/nino/http/response']['body'] = [ 'error' => 'decryption failed' ];
				return;
			}

			self::_safetySnapshot( $appData, $dir, $key );

			$root 	= \Nino\Filesystem::getPath( $appData );
			$tmpGz 	= tempnam( sys_get_temp_dir(), 'ninorestore' ). '.tar.gz';
			file_put_contents( $tmpGz, $gz );
			( new \PharData( $tmpGz ) )->extractTo( $root, null, true );
			unlink( $tmpGz );

			// extractTo() writes straight to disk, bypassing Filesystem's own
			// cache tracking entirely - drop it so any getFileContent() call
			// later in this same request (or a request landing in the same
			// wall-clock second - see AppData::writeContentData()'s docblock
			// for why that specifically matters) re-reads the restored files
			// instead of whatever was cached from before the restore
			unset( $appData['./nino/filesystem/cache'] );

			if( function_exists( 'opcache_reset' ) === true )
				opcache_reset();

			$request['/nino/http/response']['body'] = [ 'ok' => true, 'restoredDate' => $date ];
		}

		/**
		 *	Archive the *current* on-disk state before overwriting it, same
		 *	tar+gzip+encrypt shape as Backup::_create() (duplicated, not
		 *	called into - see this class' own docblock). Named distinctly
		 *	from a dated backup so it's never mistaken for one and never
		 *	collides with/gets pruned by Backup's own retention sweep
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$key					Raw (not base64) 32-byte AES key
		 *
		 *	@return 	void
		 */
		private static function _safetySnapshot( array &$appData, string $dir, string $key ): void {

			$root 	= \Nino\Filesystem::getPath( $appData );
			$files 	= [];

			if( is_file( $root. '/config.php' ) === true )
				$files[$root. '/config.php'] = 'config.php';

			if( is_file( $root. '/text/global.php' ) === true )
				$files[$root. '/text/global.php'] = 'text/global.php';

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
				if( is_file( $root. '/text/'. $locale. '.php' ) === true )
					$files[$root. '/text/'. $locale. '.php'] = 'text/'. $locale. '.php';

			foreach( glob( $root. '/elements/*.php' ) ?: [] as $file )
				$files[$file] = 'elements/'. basename( $file );

			foreach( glob( $root. '/images/*' ) ?: [] as $file )
				if( is_file( $file ) === true )
					$files[$file] = 'images/'. basename( $file );

			$tmpTar = tempnam( sys_get_temp_dir(), 'ninosnapshot' ). '.tar';
			$phar 	= new \PharData( $tmpTar );

			foreach( $files as $absolute => $archiveName )
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
			'/nino/http/routes'				=> 'array',
		];

		/**
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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
		 *	this runs, Dev::init() has already added _dev's own GET/POST
		 *	/_dev route into $appData['/nino/http/routes'] at runtime (same
		 *	as Admin::init() does for /_admin, self-registered, never
		 *	persisted - see Dev::init()'s docblock). Showing that live value
		 *	here would round-trip it straight into config.php on the next
		 *	save of this key.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			$values = [];
			foreach( array_keys( self::KEY_TYPES ) as $key )
				$values[$key] = json_encode( $stored[$key] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			$request['/nino/http/response']['body'] = [ 'values' => $values ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Dev\Dev::postData();
			$key 	= (string) ( $data['key'] ?? '' );
			$raw 	= (string) ( $data['value'] ?? '' );

			if( isset( self::KEY_TYPES[$key] ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown key' ];
				return;
			}

			$decoded = json_decode( $raw, true );

			if( json_last_error() !== JSON_ERROR_NONE ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid json: '. json_last_error_msg() ];
				return;
			}

			if( self::_matchesType( $decoded, self::KEY_TYPES[$key] ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'expected a '. self::KEY_TYPES[$key]. ' value' ];
				return;
			}

			$appData[$key] = $decoded;
			\Nino\AppData::writeContentData( $appData, [ $key ] );

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
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
	 *												_admin's Users panel edits ("values" half: mail/password
	 *												for an existing account). Same split as ElementTypes/Elements.
	 *												This is also the only way to bootstrap the very first
	 *												_admin user without hand-editing config.php, which nothing
	 *												else in the project can do.
	 *
	 *												Permissions are the one exception to that split: this also
	 *												exposes a raw, unwhitelisted permissions editor, deliberately
	 *												independent of _admin's own manager-only, whitelisted
	 *												apiSetPermissions() - the recovery path for when nobody with
	 *												/_admin/users/manage is left to use that one.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Users {

		private const string MANAGE_PERM = '/_admin/users/manage';

		// The individual _admin content perms (deliberately duplicated as plain
		// strings rather than referencing \Nino\Admin\*::MANAGE_PERM/VIEW_PERM -
		// this file stays independent of _admin/Admin.php, see class docblock
		// above). A non-manager account created here still gets full content
		// access, same as every admin account had before per-module permissions
		// existed - only /_admin/users/manage is gated by the checkbox
		private const array CONTENT_PERMS = [
			'/_admin/elements/manage',
			'/_admin/text/manage',
			'/_admin/images/manage',
			'/_admin/submissions/view',
			'/_admin/newsletter/manage',
			'/_admin/logs/view',
		];

		/**
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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
		 *	List every admin user (mail/status/permissions, never the
		 *	password hash)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$users = [];
			foreach( ( $appData['/nino/auth/user'] ?? [] ) as $mail => $user )
				$users[] = [
					'mail' 			=> $mail,
					'status' 		=> $user['status'] ?? 0,
					'perms' 		=> $user['perms'] ?? [],
					// checkPermission(), not a raw in_array() - a '/*' grant (see
					// apiCreate()) implies this too, same recursive wildcard match
					// _admin's own guardPerm() uses
					'isManager' => \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM, $mail ),
				];

			$request['/nino/http/response']['body'] = [ 'users' => $users ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 			= \Nino\Dev\Dev::postData();
			$mail 			= trim( (string) ( $data['mail'] ?? '' ) );
			$password 	= (string) ( $data['password'] ?? '' );
			$isManager 	= ( $data['isManager'] ?? false ) === true;

			if( filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false || strlen( $password ) < 8 ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid mail or password too short' ];
				return;
			}

			$ok = \Nino\Auth::insertUser( $appData, $mail, $password, $isManager === true ? [ '/*' ] : self::CONTENT_PERMS );

			if( $ok === false ) {
				$request['/nino/http/response']['statusCode'] = 409;
				$request['/nino/http/response']['body'] = [ 'error' => 'a user with this mail already exists' ];
				return;
			}

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$mail = trim( (string) ( \Nino\Dev\Dev::postData()['mail'] ?? '' ) );
			$ok 	= \Nino\Auth::deleteUser( $appData, $mail );

			if( $ok === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown user' ];
				return;
			}

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
		}

		/**
		 *	Directly set a user's permissions - unlike _admin's own
		 *	Admin\Users::apiSetPermissions() this isn't whitelisted against a
		 *	known set of permission strings: this file is the deliberate
		 *	developer bypass for exactly the "wrecked config.php data"
		 *	scenario _admin's own docblock already points back here for, eg.
		 *	the last remaining manager accidentally removing their own
		 *	/_admin/users/manage permission with no self-service recovery
		 *	path in _admin itself
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSetPermissions( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Dev\Dev::postData();
			$mail 	= trim( (string) ( $data['mail'] ?? '' ) );
			$perms 	= $data['perms'] ?? null;

			if( \Nino\Auth::getUser( $appData, $mail ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown user' ];
				return;
			}

			if( is_array( $perms ) === false || count( array_filter( $perms, 'is_string' ) ) !== count( $perms ) ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'perms must be an array of strings' ];
				return;
			}

			$appData['/nino/auth/user'][$mail]['perms'] = array_values( $perms );
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			$request['/nino/http/response']['body'] = [ 'perms' => $appData['/nino/auth/user'][$mail]['perms'] ];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Manage image slots (/nino/html/images): create, edit label/
	 *												width/height, delete - the "set" half of what _admin's
	 *												Images panel edits ("values" half: which file currently
	 *												fills a slot). Same split as ElementTypes/Elements. Only
	 *												ever touches a slot's filename when deleting the slot
	 *												itself (cleans up its uploaded file via \Nino\Images::
	 *												delete()) - replacing it stays _admin's job via the actual
	 *												upload/crop pipeline (\Nino\Images::process()/
	 *												setSlotFilename()).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Images {

		/**
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
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

			$request['/nino/http/response']['body'] = [ 'slots' => $slots ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Dev\Dev::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			if( isset( $appData['/nino/html/images'][$uri] ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown slot' ];
				return;
			}

			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$width 	= max( 1, (int) ( $data['width'] ?? 0 ) );
			$height = max( 1, (int) ( $data['height'] ?? 0 ) );

			if( $label === '' ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'label is required' ];
				return;
			}

			$appData['/nino/html/images'][$uri]['label'] 	= $label;
			$appData['/nino/html/images'][$uri]['width'] 	= $width;
			$appData['/nino/html/images'][$uri]['height'] = $height;

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Dev\Dev::postData();
			$uri 		= (string) ( $data['uri'] ?? '' );
			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$width 	= max( 1, (int) ( $data['width'] ?? 0 ) );
			$height = max( 1, (int) ( $data['height'] ?? 0 ) );

			if( self::isValidUri( $uri ) === false || $label === '' ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid uri or missing label' ];
				return;
			}

			if( isset( $appData['/nino/html/images'][$uri] ) === true ) {
				$request['/nino/http/response']['statusCode'] = 409;
				$request['/nino/http/response']['body'] = [ 'error' => 'slot already exists' ];
				return;
			}

			$appData['/nino/html/images'][$uri] = [
				'label' 		=> $label,
				'width' 		=> $width,
				'height' 		=> $height,
				'filename' 	=> null,
			];

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			$request['/nino/http/response']['body'] = [ 'ok' => true, 'uri' => $uri ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Dev\Dev::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			$slot = $appData['/nino/html/images'][$uri] ?? null;

			if( $slot === null ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown slot' ];
				return;
			}

			if( ( $slot['filename'] ?? null ) !== null )
				\Nino\Images::delete( $appData, $slot['filename'] );

			unset( $appData['/nino/html/images'][$uri] );

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
		}

		/**
		 *	Scan every public-site template (templates/*.tpl) for literal
		 *	<img src="/images/..."> tags not backed by any image slot - the
		 *	gap this closes: a template built with a placeholder/demo photo
		 *	hardcoded straight into the markup instead of going through the
		 *	[image /uri] shortcode (see \Nino\Shortcodes\Images), so an
		 *	admin can never swap it without editing code. Proposes a slot
		 *	per file (uri guessed from the filename, width/height read off
		 *	the <img> tag's own attributes or, failing that, probed from the
		 *	actual file), filename deliberately left for apiCreate() to
		 *	leave empty - same "dev only ever creates the empty slot, a real
		 *	upload is _admin's job" rule the class docblock already states.
		 *	An <img> outside /images/ (external url, data: uri, favicon,
		 *	logo) is skipped entirely, none of those fit this slot system
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScan( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$request['/nino/http/response']['body'] = [ 'missing' => self::_scanMissing( $appData ) ];
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
	 *												_admin's Text panel (/text/blacklist.php) - plus, same as
	 *												_admin's Text panel, edit every key's actual value(s).
	 *												Unlike _admin, _dev also sees blacklisted keys and can still
	 *												edit/rename/delete them (the blacklist only hides a key
	 *												from _admin, not from _dev). Converting a key between global
	 *												and per-locale, or renaming it, migrates its current
	 *												value(s) rather than discarding them - see _convertShape()/
	 *												apiRename().
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Text {

		private const int MIN_MAXLENGTH 		= 150;
		private const int MAX_MAXLENGTH 		= 2000;
		private const int MAXLENGTH_BUFFER = 150;
		private const int HARD_MAXLENGTH 	= 20000;

		/**
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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
		 *	A text key follows the same "/segment/segment" shape _admin's
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
		 *	Every known key across global.php + every locale file, plus
		 *	whether it's currently blacklisted (unlike _admin's own
		 *	_entries(), blacklisted keys are NOT filtered out here - this is
		 *	exactly where you'd come to un-blacklist one)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _entries( array &$appData ): array {

			$global 	= \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );

			$localeData = [];
			foreach( $locales as $locale )
				$localeData[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$blacklist = array_flip( \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ) );

			$bracketKeys = array_keys( $global );
			foreach( $localeData as $data )
				$bracketKeys = array_merge( $bracketKeys, array_keys( $data ) );
			$bracketKeys = array_unique( $bracketKeys );

			$entries = [];

			foreach( $bracketKeys as $bracketKey ) {

				$key 			= trim( $bracketKey, '[]' );
				$isGlobal = array_key_exists( $bracketKey, $global );

				$values = $isGlobal
					? [ '*' => $global[$bracketKey] ]
					: array_map( fn( array $data ) => $data[$bracketKey] ?? '', $localeData );

				$longest 	= 0;
				$html 		= false;

				foreach( $values as $value ) {
					$longest 	= max( $longest, strlen( $value ) );
					$html 		= $html || \Nino\Html::containsHtml( $value );
				}

				$entries[] = [
					'key' 					=> $key,
					'global' 				=> $isGlobal,
					'blacklisted' 	=> isset( $blacklist[$key] ) === true,
					'html' 					=> $html,
					'maxlength' 		=> max( self::MAX_MAXLENGTH, max( self::MIN_MAXLENGTH, $longest + self::MAXLENGTH_BUFFER ) ),
					'values' 				=> $values,
				];
			}

			usort( $entries, fn( array $a, array $b ) => strcmp( $a['key'], $b['key'] ) );

			return $entries;
		}

		/**
		 *	List every known text key (see _entries())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$request['/nino/http/response']['body'] = [
				'keys' 		=> self::_entries( $appData ),
				'locales' => \Nino\Locales::getAvailableLocales( $appData ),
			];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Dev\Dev::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$isGlobal = ( $data['global'] ?? false ) === true;
			$value 		= (string) ( $data['value'] ?? '' );

			if( self::isValidKey( $key ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid key' ];
				return;
			}

			foreach( self::_entries( $appData ) as $entry )
				if( $entry['key'] === $key ) {
					$request['/nino/http/response']['statusCode'] = 409;
					$request['/nino/http/response']['body'] = [ 'error' => 'key already exists' ];
					return;
				}

			$bracketKey = '[['. $key. ']]';

			if( $isGlobal === true ) {
				$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
				$global[$bracketKey] = $value;
				\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $global );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {
					$localeData = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
					$localeData[$bracketKey] = $value;
					\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', $localeData );
				}
			}

			$request['/nino/http/response']['body'] = [ 'ok' => true, 'key' => $key ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Dev\Dev::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$isGlobal = ( $data['global'] ?? false ) === true;
			$blacklisted = ( $data['blacklisted'] ?? false ) === true;

			$entry = null;
			foreach( self::_entries( $appData ) as $candidate )
				if( $candidate['key'] === $key )
					$entry = $candidate;

			if( $entry === null ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown key' ];
				return;
			}

			if( $entry['global'] !== $isGlobal )
				self::_convertShape( $appData, $key, $entry, $isGlobal );

			self::_setBlacklisted( $appData, $key, $blacklisted );

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
		}

		/**
		 *	Save several keys' values in one request - same batched-per-file
		 *	shape as _admin's Text::apiSaveBatch(), reusing the same
		 *	sanitizeHtml()/strip_tags() rule via \Nino\Html. Unlike _admin,
		 *	a blacklisted key is still a valid save target here - blacklist
		 *	only hides a key from _admin's Text panel, not from _dev's.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSaveBatch( array &$appData, array &$request ): void {

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Dev\Dev::postData();
			$items 	= is_array( $data['items'] ?? null ) ? $data['items'] : [];

			$entries = self::_entries( $appData );

			$results 	= [];
			$fileData = [];

			foreach( $items as $item ) {

				$key 		= (string) ( $item['key'] ?? '' );
				$locale = (string) ( $item['locale'] ?? '' );
				$value 	= (string) ( $item['value'] ?? '' );

				$entry = null;
				foreach( $entries as $candidate )
					if( $candidate['key'] === $key )
						$entry = $candidate;

				if( $entry === null ) {
					$results[$key] = [ 'ok' => false, 'error' => 'unknown key' ];
					continue;
				}

				if( $entry['global'] === false && \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
					$results[$key] = [ 'ok' => false, 'error' => 'invalid locale' ];
					continue;
				}

				$value = substr( $value, 0, self::HARD_MAXLENGTH );
				$value = ( $entry['html'] === true ) ? \Nino\Html::sanitizeHtml( $value ) : strip_tags( $value );

				$file = ( $entry['global'] === true ) ? '/text/global.php' : '/text/'. $locale. '.php';

				if( isset( $fileData[$file] ) === false )
					$fileData[$file] = \Nino\Filesystem::getFileContent( $appData, $file, [] );

				$fileData[$file]['[['. $key. ']]'] = $value;

				$results[$key] = [ 'ok' => true, 'value' => $value ];
			}

			foreach( $fileData as $file => $content )
				\Nino\Filesystem::putFileContent( $appData, $file, $content );

			$request['/nino/http/response']['body'] = [ 'results' => $results ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data 	= \Nino\Dev\Dev::postData();
			$key 		= (string) ( $data['key'] ?? '' );
			$newKey = (string) ( $data['newKey'] ?? '' );

			if( self::isValidKey( $newKey ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid new key' ];
				return;
			}

			$entries = self::_entries( $appData );

			$entry = null;
			foreach( $entries as $candidate )
				if( $candidate['key'] === $key )
					$entry = $candidate;

			if( $entry === null ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown key' ];
				return;
			}

			if( $newKey !== $key )
				foreach( $entries as $candidate )
					if( $candidate['key'] === $newKey ) {
						$request['/nino/http/response']['statusCode'] = 409;
						$request['/nino/http/response']['body'] = [ 'error' => 'key already exists' ];
						return;
					}

			$oldBracket = '[['. $key. ']]';
			$newBracket = '[['. $newKey. ']]';

			if( $entry['global'] === true ) {
				$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
				$global[$newBracket] = $global[$oldBracket] ?? '';
				unset( $global[$oldBracket] );
				\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $global );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {
					$localeData = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
					$localeData[$newBracket] = $localeData[$oldBracket] ?? '';
					unset( $localeData[$oldBracket] );
					\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', $localeData );
				}
			}

			if( $entry['blacklisted'] === true ) {
				self::_setBlacklisted( $appData, $key, false );
				self::_setBlacklisted( $appData, $newKey, true );
			}

			$request['/nino/http/response']['body'] = [ 'ok' => true, 'key' => $newKey ];
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$data = \Nino\Dev\Dev::postData();
			$key 	= (string) ( $data['key'] ?? '' );

			$entry = null;
			foreach( self::_entries( $appData ) as $candidate )
				if( $candidate['key'] === $key )
					$entry = $candidate;

			if( $entry === null ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown key' ];
				return;
			}

			$bracketKey = '[['. $key. ']]';

			if( $entry['global'] === true ) {
				$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
				unset( $global[$bracketKey] );
				\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $global );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {
					$localeData = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
					unset( $localeData[$bracketKey] );
					\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', $localeData );
				}
			}

			if( $entry['blacklisted'] === true )
				self::_setBlacklisted( $appData, $key, false );

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
		}

		/**
		 *	Move a key's current value(s) between global.php and every
		 *	locale file - see apiSave()'s docblock for the exact migration
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *	@param		array 		$entry					This key's current entry (see _entries())
		 *	@param		bool			$toGlobal				Target shape
		 *
		 *	@return 	void
		 */
		private static function _convertShape( array &$appData, string $key, array $entry, bool $toGlobal ): void {

			$bracketKey = '[['. $key. ']]';
			$locales 		= \Nino\Locales::getAvailableLocales( $appData );

			if( $toGlobal === true ) {

				$native = \Nino\Locales::getNativeLocale( $appData );
				$value 	= $entry['values'][$native] ?? ( array_values( array_filter( $entry['values'], fn( $v ) => $v !== '' ) )[0] ?? '' );

				foreach( $locales as $locale ) {
					$localeData = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
					unset( $localeData[$bracketKey] );
					\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', $localeData );
				}

				$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
				$global[$bracketKey] = $value;
				\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $global );

			} else {

				$value = $entry['values']['*'] ?? '';

				$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
				unset( $global[$bracketKey] );
				\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $global );

				foreach( $locales as $locale ) {
					$localeData = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
					$localeData[$bracketKey] = $value;
					\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', $localeData );
				}
			}
		}

		/**
		 *	Add or remove one key from /text/blacklist.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *	@param		bool			$blacklisted
		 *
		 *	@return 	void
		 */
		private static function _setBlacklisted( array &$appData, string $key, bool $blacklisted ): void {

			$list 	= \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
			$has 		= in_array( $key, $list, true );

			if( $blacklisted === true && $has === false )
				$list[] = $key;
			else if( $blacklisted === false && $has === true )
				$list = array_values( array_diff( $list, [ $key ] ) );
			else
				return;

			\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', $list );
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
		 *	Scan every public-site template (templates/*.tpl - not _admin's
		 *	or _dev's own, those are separate text systems entirely) for
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$request['/nino/http/response']['body'] = [ 'missing' => self::_scanMissing( $appData ) ];
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
			foreach( self::_entries( $appData ) as $entry )
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
	 *	Dev								Dashboard: landing panel with the numbers the other
	 *												modules already compute (missing text keys, missing
	 *												image slots), pulled together into one overview
	 *												instead of having to open Text/Images in turn to
	 *												find out whether either scan is clean. Doesn't add
	 *												any storage of its own
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Dashboard {

		/**
		 *	This module's action map, merged into Dev::handlePost()'s dispatch
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

			if( \Nino\Dev\Dev::guard( $appData, $request ) === false )
				return;

			$request['/nino/http/response']['body'] = [
				'missingText' 		=> Text::missingCount( $appData ),
				'missingImages' 	=> Images::missingCount( $appData ),
			];
		}
	}
}
