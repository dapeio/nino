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
	if( php_sapi_name() === 'cli' && isset( $argv[1] ) === true )
		die( password_hash( $argv[1], PASSWORD_DEFAULT ). PHP_EOL );
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

		// Generate with: php _admin/Admin.php <your password> - or let /_install's
		// last wizard step do it for you (see DEFAULT_PASSWORD_HASH below).
		// CHANGE THIS before deploying - the shipped value matches no password
		private const string PASSWORD_HASH = '$2y$10$JeWFJ0CAi.tAEc6i5IcyTOLSgeCbrspmb4p0Re7aWvJBOajXtwgt.';

		// The exact value PASSWORD_HASH ships with, kept separately so it can
		// be compared against rather than duplicated. /_install (see
		// _install/Install.php) reads this through hasDefaultPassword() to
		// decide whether the project has already been through the wizard -
		// once PASSWORD_HASH no longer matches, /_install refuses to run at
		// all, which is also the only thing that ever locks it back out.
		public const string DEFAULT_PASSWORD_HASH = '$2y$10$JeWFJ0CAi.tAEc6i5IcyTOLSgeCbrspmb4p0Re7aWvJBOajXtwgt.';

		private const int MAX_TRIES = 5;
		private const int COOLDOWN = 3600;

		private const array MODULES = [
			\Nino\Admin\Dashboard::class,
			\Nino\Admin\ElementTypes::class,
			\Nino\Admin\Text::class,
			\Nino\Admin\PageEditor::class,
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
		 *	Whether PASSWORD_HASH is still the shipped placeholder - true for
		 *	every fresh checkout, false the moment a real password has been
		 *	set (by hand, or by /_install's finish step). This is the one
		 *	thing /_install checks before it lets itself run at all.
		 *
		 *	@return 	bool
		 */
		public static function hasDefaultPassword(): bool {
			return self::PASSWORD_HASH === self::DEFAULT_PASSWORD_HASH;
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
			$lockedOut 	= false;
			$verified 	= false;

			// The cooldown check, password verify and tries update all happen
			// inside one lock: reading the counter, verifying, then writing as
			// three separate steps would let two concurrent wrong attempts both
			// read the same "tries" and both write back the same +1, losing an
			// increment - the exact way to dodge the lockout with two requests
			// instead of one
			\Nino\Filesystem::mutate( $appData, '/_admin/.lockout.json', function( array $state ) use ( $password, &$lockedOut, &$verified ): ?array {

				if( (int) $state['until'] > time() ) {
					$lockedOut = true;
					return null;
				}

				$verified = password_verify( $password, self::PASSWORD_HASH );

				if( $verified === true )
					return [ 'tries' => 0, 'until' => 0 ];

				$state['tries'] = (int) $state['tries'] + 1;
				if( $state['tries'] >= self::MAX_TRIES ) {
					$state['tries'] = 0;
					$state['until'] = time() + self::COOLDOWN;
				}

				return $state;
			}, [ 'tries' => 0, 'until' => 0 ] );

			if( $lockedOut === true ) {
				\Nino\Http::fail( $request, 429, 'too many attempts' );
				return;
			}

			if( $verified === false ) {
				\Nino\Http::fail( $request, 401, 'wrong password' );
				return;
			}

			// Defend against session fixation, same as Auth::loginUser()
			session_regenerate_id( true );
			\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );
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
	 *												_routeKey()'s docblock for why) and the same
	 *												/nino/install/webpages array as its persisted list -
	 *												shared, not duplicated, so either tool sees the other's
	 *												edits. That array is no longer purely /_install's own
	 *												internal bookkeeping once this class exists alongside it
	 *												(see docs/_install.md's note on this). The list's own
	 *												order is what [[/website/navigation/main]] gets
	 *												generated in (see _applyNavigation()) - apiMove() swaps
	 *												one entry with a neighbor, the same ↑/↓ reordering
	 *												Webpages' own list does while _install is still around.
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
			return [ 'pages', 'Pages' ];
		}

		/**
		 *	How many pages are persisted - shared by Dashboard::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {
			return count( $appData['/nino/install/webpages'] ?? [] );
		}

		/**
		 *	List the persisted page list, every templates/page-*.tpl file
		 *	available to pick from, the active locales and whether the
		 *	Navigation module is active (a page's own "nav" flag only means
		 *	anything while it is)
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
				'pages' 			=> \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/install/webpages'] ?? [],
				'templates' 	=> self::_templates( $appData ),
				'locales' 		=> \Nino\Locales::getAvailableLocales( $appData ),
				'navModule' 	=> in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ),
			] );
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
			$pages 	= $config['/nino/install/webpages'] ?? [];
			$routes = $config['/nino/http/routes'] ?? [];

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

			$entry = [
				'uri' 				=> $uri,
				'httpUri' 		=> $httpUri,
				'template' 		=> $template,
				'nav' 				=> (bool) ( $data['nav'] ?? false ),
				'statusCode' 	=> $statusCode,
				'body' 				=> $body,
				'text' 				=> $text,
			];

			// _install's own field, naming the library unit this entry
			// started from - carried over untouched so its Webpages step can
			// still re-apply that unit after an edit made over here
			if( isset( $previous['libraryKey'] ) === true )
				$entry['libraryKey'] = $previous['libraryKey'];

			if( $selfIndex !== null ) {
				unset( $routes[self::_routeKey( $pages[$selfIndex]['httpUri'] )] );
				$pages[$selfIndex] = $entry;
			} else {
				$pages[] = $entry;
			}

			$routeData = [ 'uri' => $uri, 'body' => $body ];
			if( $statusCode !== 200 )
				$routeData['statusCode'] = $statusCode;

			$routes[$routeKey] = $routeData;

			$appData['/nino/install/webpages'] 	= array_values( $pages );
			$appData['/nino/http/routes'] 				= $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/install/webpages', '/nino/http/routes' ] );

			foreach( $locales as $locale )
				self::_mergeText( $appData, '/text/'. $locale. '.php', [
					'[[/webpage'. $uri. '/name]]' 				=> $text[$locale]['name'],
					'[[/webpage'. $uri. '/title]]' 			=> $text[$locale]['title'],
					'[[/webpage'. $uri. '/description]]' => $text[$locale]['description'],
				] );

			self::_applyNavigation( $appData, $appData['/nino/install/webpages'], $locales );

			\Nino\Http::ok( $request, [ 'pages' => $appData['/nino/install/webpages'] ] );
		}

		/**
		 *	Remove one page entry and its route. Its /webpage&lt;uri&gt;/* text
		 *	meta is deliberately left in place - same additive-only
		 *	philosophy every other apply/save in this codebase follows,
		 *	deleting a file/key a developer may have since hand-edited is a
		 *	much riskier "undo" than toggling a config array
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$httpUri = (string) ( \Nino\Admin\Admin::postData()['httpUri'] ?? '' );

			$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$pages 	= $config['/nino/install/webpages'] ?? [];
			$routes = $config['/nino/http/routes'] ?? [];

			$index = null;
			foreach( $pages as $i => $entry )
				if( ( $entry['httpUri'] ?? null ) === $httpUri ) {
					$index = $i;
					break;
				}

			if( $index === null ) {
				\Nino\Http::fail( $request, 404, 'unknown page' );
				return;
			}

			unset( $routes[self::_routeKey( $httpUri )] );
			unset( $pages[$index] );

			$appData['/nino/install/webpages'] 	= array_values( $pages );
			$appData['/nino/http/routes'] 				= $routes;

			\Nino\AppData::writeContentData( $appData, [ '/nino/install/webpages', '/nino/http/routes' ] );

			self::_applyNavigation( $appData, $appData['/nino/install/webpages'], \Nino\Locales::getAvailableLocales( $appData ) );

			\Nino\Http::ok( $request, [ 'pages' => $appData['/nino/install/webpages'] ] );
		}

		/**
		 *	Swap one page entry with its immediate neighbor - the list's own
		 *	order is what [[/website/navigation/main]] is generated in (see
		 *	_applyNavigation()), so reordering here is the only way to
		 *	control the generated main menu's order once _install (whose
		 *	Webpages step has the same ↑/↓ buttons on its own list for the
		 *	same reason) has been deleted. A swap rather than an arbitrary posted index:
		 *	the list itself never leaves the browser, only which of two
		 *	neighbors should trade places - nothing to validate beyond "is
		 *	this still a valid direction from here"
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

			$pages = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/install/webpages'] ?? [];

			$index = null;
			foreach( $pages as $i => $entry )
				if( ( $entry['httpUri'] ?? null ) === $httpUri ) {
					$index = $i;
					break;
				}

			if( $index === null ) {
				\Nino\Http::fail( $request, 404, 'unknown page' );
				return;
			}

			$swapWith = $direction === 'up' ? $index - 1 : $index + 1;

			if( $swapWith < 0 || $swapWith >= count( $pages ) ) {
				\Nino\Http::fail( $request, 400, 'already at the '. ( $direction === 'up' ? 'top' : 'bottom' ) );
				return;
			}

			[ $pages[$index], $pages[$swapWith] ] = [ $pages[$swapWith], $pages[$index] ];

			$appData['/nino/install/webpages'] = array_values( $pages );
			\Nino\AppData::writeContentData( $appData, [ '/nino/install/webpages' ] );

			self::_applyNavigation( $appData, $appData['/nino/install/webpages'], \Nino\Locales::getAvailableLocales( $appData ) );

			\Nino\Http::ok( $request, [ 'pages' => $appData['/nino/install/webpages'] ] );
		}

		/**
		 *	Regenerates the site's main-menu text (see
		 *	_install/library/modules/navigation/templates/html-header-nav.tpl
		 *	and html-footer-nav.tpl's [[/website/navigation/main]] fill) from
		 *	every entry with 'nav' checked, one "httpUri:name" line per
		 *	entry - the exact shape \Nino\Modules\Navigation's
		 *	[navigation]...[/navigation] shortcode expects. Only runs while
		 *	the Navigation module is active; overwrites rather than merges,
		 *	same reasoning as _install/Install.php's
		 *	Webpages::_applyNavigation() docblock: it's fully derived from
		 *	the current list, not something a developer is expected to
		 *	hand-edit
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$pages				The just-saved, current page list
		 *	@param		array 		$locales			Active locales
		 *
		 *	@return 	void
		 */
		private static function _applyNavigation( array &$appData, array $pages, array $locales ): void {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return;

			$navEntries = array_values( array_filter( $pages, fn( array $p ): bool => ( $p['nav'] ?? false ) === true ) );

			foreach( $locales as $locale ) {

				$lines = [];
				foreach( $navEntries as $entry )
					$lines[] = $entry['httpUri']. ':'. ( $entry['text'][$locale]['name'] ?? self::DEFAULT_TEXT['name'] );

				self::_setText( $appData, '/text/'. $locale. '.php', '[[/website/navigation/main]]', implode( PHP_EOL, $lines ) );
			}
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
