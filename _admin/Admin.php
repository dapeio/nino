<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Admin							Admin dashboard backend - all \Nino\Admin\* classes live in
 *												this single file, same convention as _nino/Nino.php
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Admin {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Admin							Bootstraps the admin area: routes, assets, textfills and the
	 *											GET/POST /_admin request handling. The only class _admin/index.php
	 *											calls into - everything else (routing) is registered here, the
	 *											individual domain classes (eg. Elements) only fill the response.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		/**
		 *	Bootstrap the admin area: register routes, assets, textfills and
		 *	the response callbacks for every admin route
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$appData['/nino/http/routes'] += [
				'GET://_admin' 	=> [
					'uri' 				=> '/_admin',
					'body'				=> '[template /_admin/templates/page-index]',
					'statusCode'	=> 200
				],
				'POST://_admin'	=> [ 'uri' => '/_admin' ],
			];

			$appData['/nino/html/assets']['/_admin/.cache/style.css'] = [
				'/_admin/assets/style.css',
			];
			$appData['/nino/html/assets']['/_admin/.cache/script.js'] = [
				'/_nino/Nino.js',
				'/_admin/assets/script.js',
				'/_admin/assets/html-editor.js',
				'/_admin/assets/dashboard.js',
				'/_admin/assets/elements.js',
				'/_admin/assets/text.js',
				'/_admin/assets/images.js',
				'/_admin/assets/users.js',
				'/_admin/assets/logs.js',
				'/_admin/assets/submissions.js',
				'/_admin/assets/newsletter.js',
			];
			$appData['/nino/html/assets']['/_admin/.cache/login.js'] = [
				'/_nino/Nino.js',
				'/_admin/assets/login.js',
			];

			// A GET ?locale=xx (used by the UI-chrome picker) works even
			// logged out, login screen included - unlike the POST
			// admin/locale action Elements/Text's live content-locale
			// switch uses, this needs no guard() at all: GET /_admin is
			// already reachable without a session, so there's nothing to
			// loosen. Persists to the same ./admin/locale session key.
			$queryLocale = (string) ( $_GET['locale'] ?? '' );
			if( $queryLocale !== '' && \Nino\Locales::verifyLocale( $appData, $queryLocale ) === true )
				\Nino\Runtime::setSessionValue( $appData, './admin/locale', $queryLocale );

			// Sync the shared "current locale" to the admin's own remembered
			// locale (session locale) before loading _admin's own text fills -
			// reuses the exact mechanism Elements/Text's locale switch already
			// persists (POST admin/locale), so switching it also switches
			// which language the admin UI chrome itself renders in
			\Nino\Locales::setCurrentLocale( $appData, self::sessionLocale( $appData ) );

			$currentLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Html::addFills( $appData, \Nino\Filesystem::getFileContent( $appData, '/_admin/text/'. $currentLocale. '.php', [] ), $currentLocale );
			\Nino\Html::addFills( $appData, [ '[[/_admin/localepicker]]' => self::_localePickerHtml( $appData, $currentLocale ) ], '*' );

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_admin', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_admin', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_admin response: dashboard if logged in, else the login form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			self::_logLoginOnce( $appData );

			if( \Nino\Auth::getCurrentUser( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_admin/templates/page-login]';
		}

		/**
		 *	Record a successful login to the activity log, the first time
		 *	the now-authenticated session touches _admin.
		 *
		 *	Not hooked directly into Auth's login (the obvious-seeming
		 *	approach) because that goes through the kernel's generic
		 *	/.nino/auth/login uri, which - unlike GET/POST /_admin - isn't
		 *	guaranteed to be routed through _admin/index.php at all: a
		 *	typical webserver config maps /_admin/* to _admin/index.php via
		 *	its own directory/file, but /.nino/auth/login matches no real
		 *	file under _admin/ and falls through to the site's root
		 *	index.php instead, which never calls Admin::init() and so never
		 *	registers any _admin-specific callback. Piggybacking on guard()/
		 *	handleGet() instead sidesteps that entirely, since _admin's own
		 *	bootstrap is guaranteed to run for every real GET/POST /_admin
		 *	request regardless of which uri physically carried the login.
		 *
		 *	The "loginLoggedFor" session marker is cleared as soon as
		 *	there's no current user, so a logout followed by a fresh login
		 *	(same or different user, same browser session) is always logged
		 *	again, not just the first login of the session
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _logLoginOnce( array &$appData ): void {

			$user = \Nino\Auth::getCurrentUser( $appData );

			if( $user === false ) {
				\Nino\Runtime::unsetSessionValue( $appData, './admin/loginLoggedFor' );
				return;
			}

			if( \Nino\Runtime::getSessionValue( $appData, './admin/loginLoggedFor', '' ) === $user['mail'] )
				return;

			\Nino\Runtime::setSessionValue( $appData, './admin/loginLoggedFor', $user['mail'] );

			Logs::record( $appData, $user['mail'], 'Login' );
		}

		/**
		 *	Fill the POST /_admin response. Every admin api request goes
		 *	through this single route and is dispatched by $_POST['action'],
		 *	so the frontend never depends on the webserver routing anything
		 *	beyond the one already-required /_admin uri.
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

			$actions = [
				'admin/locale' 		=> [ Admin::class, 'apiSetLocale' ],
				'dashboard/summary' => [ Dashboard::class, 'apiSummary' ],
				'elements/types' 	=> [ Elements::class, 'apiTypes' ],
				'elements/list' 		=> [ Elements::class, 'apiList' ],
				'elements/get' 			=> [ Elements::class, 'apiGet' ],
				'elements/uploadimage' => [ Elements::class, 'apiUploadImage' ],
				'elements/save' 		=> [ Elements::class, 'apiSave' ],
				'elements/delete' 	=> [ Elements::class, 'apiDelete' ],
				'text/keys' 				=> [ Text::class, 'apiKeys' ],
				'text/savebatch' 	=> [ Text::class, 'apiSaveBatch' ],
				'text/export' 			=> [ Text::class, 'apiExport' ],
				'text/import' 			=> [ Text::class, 'apiImport' ],
				'users/list' 			=> [ Users::class, 'apiList' ],
				'users/save' 			=> [ Users::class, 'apiSave' ],
				'users/logoutall' => [ Users::class, 'apiLogoutAll' ],
				'users/permissions' => [ Users::class, 'apiSetPermissions' ],
				'images/list' 		=> [ Images::class, 'apiList' ],
				'images/upload' 	=> [ Images::class, 'apiUpload' ],
				'logs/list' 				=> [ Logs::class, 'apiList' ],
				'submissions/list' => [ Submissions::class, 'apiList' ],
				'newsletter/list' => [ Newsletter::class, 'apiList' ],
				'newsletter/delete' => [ Newsletter::class, 'apiDelete' ],
			];

			$action = $_POST['action'] ?? '';

			if( isset( $actions[$action] ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown action' ];
				return;
			}

			// call_user_func() can't pass $appData/$request by reference, so dispatch via a
			// dynamic static call instead - same pattern as Callbacks::doCallbacks()
			[ $class, $method ] = $actions[$action];
			$class::{$method}( $appData, $request );

			if( $request['/nino/http/response']['statusCode'] === 200 )
				self::_logAction( $appData, $action );
		}

		/**
		 *	Describe one mutating action for the activity log, and record
		 *	it - a no-op for actions that only read data (eg. elements/list,
		 *	users/list), since those aren't meaningful audit events
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$action				The dispatched action name (eg. "elements/save")
		 *
		 *	@return 	void
		 */
		private static function _logAction( array &$appData, string $action ): void {

			$data = self::postData();

			$message = match( $action ) {
				'elements/save' 	=> ( ( $data['isNew'] ?? false ) === true ? 'Add Element /' : 'Edit Element /' ). ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ),
				'elements/delete' => 'Delete Element /'. ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ),
				'text/savebatch' 	=> 'Edit Text /'. self::_textCategory( is_array( $data['items'] ?? null ) ? $data['items'] : [] ),
				'text/import' 			=> 'Import Text '. ( $data['locale'] ?? '' ),
				'users/save' 			=> 'Edit User '. ( trim( (string) ( $data['mail'] ?? '' ) ) !== '' ? $data['mail'] : ( $data['username'] ?? '' ) ),
				'users/logoutall' => 'Logout-All '. ( $data['username'] ?? '' ),
				'users/permissions' => 'Edit Permissions '. ( $data['username'] ?? '' ),
				'images/upload' 	=> 'Upload Image '. ( $data['uri'] ?? '' ),
				'newsletter/delete' => 'Delete Newsletter Subscriber '. ( $data['email'] ?? '' ),
				default 					=> null,
			};

			if( $message === null )
				return;

			$user = \Nino\Auth::getCurrentUser( $appData );

			if( $user !== false )
				Logs::record( $appData, $user['mail'], $message );
		}

		/**
		 *	The category a batch of text/savebatch items belongs to - the
		 *	first non-empty path segment of a key (eg. "home" for
		 *	"/home/welcome/h2"), same grouping text.js itself uses to
		 *	present keys as one category's worth of fields per form.
		 *	A batch is always one category's fields saved together, so
		 *	the first item's key is representative of the whole request
		 *
		 *	@param		array 		$items				The request's "items" array
		 *
		 *	@return 	string
		 */
		private static function _textCategory( array $items ): string {

			$parts = array_values( array_filter( explode( '/', (string) ( $items[0]['key'] ?? '' ) ) ) );

			return $parts[0] ?? '-';
		}

		/**
		 *	Require an authenticated admin user and a request not already
		 *	rejected by an earlier global callback (eg. Csrf). Shared by every
		 *	domain class (Elements, Text, ...) that fills a POST /_admin response.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool										If the request may proceed
		 */
		public static function guard( array &$appData, array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			self::_logLoginOnce( $appData );

			if( \Nino\Auth::getCurrentUser( $appData ) === false ) {
				$request['/nino/http/response']['statusCode'] = 401;
				$request['/nino/http/response']['body'] = [ 'error' => 'not logged in' ];
				return false;
			}

			Backup::maybeRun( $appData );

			return true;
		}

		/**
		 *	Same as guard(), plus a specific permission - the check every
		 *	domain class (Elements, Text, ...) other than Users/Dashboard/
		 *	locale uses, now that access can be tailored per competency
		 *	instead of "any logged-in user may do anything". Users keeps its
		 *	own bespoke self-or-manage rule (see Users::_authorize()) since
		 *	"edit your own account" never depends on a permission; Dashboard
		 *	and admin/locale stay guard()-only since they're not module-
		 *	specific actions.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$perm					Required permission (eg. Elements::MANAGE_PERM)
		 *
		 *	@return 	bool										If the request may proceed
		 */
		public static function guardPerm( array &$appData, array &$request, string $perm ): bool {

			if( self::guard( $appData, $request ) === false )
				return false;

			if( \Nino\Auth::checkPermission( $appData, $perm ) === false ) {
				$request['/nino/http/response']['statusCode'] = 403;
				$request['/nino/http/response']['body'] = [ 'error' => 'not allowed' ];
				return false;
			}

			return true;
		}

		/**
		 *	The admin's remembered locale for this session (Elements/Text's
		 *	locale switch), so opening the next form - in this or another
		 *	panel - starts on it instead of always resetting to whichever
		 *	locale happens to come first. Falls back to the site's native
		 *	locale if nothing has been chosen yet, or the stored one is no
		 *	longer available (eg. removed from config since).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		public static function sessionLocale( array &$appData ): string {

			$locale = \Nino\Runtime::getSessionValue( $appData, './admin/locale', '' );

			if( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false )
				return \Nino\Locales::getNativeLocale( $appData );

			return $locale;
		}

		/**
		 *	Build the [[/_admin/localepicker]] fill: a <select> (stays
		 *	compact regardless of how many locales are configured, unlike a
		 *	row of links) with one <option> per available locale (label via
		 *	the same [[/nino/locales/locale/xx]] fills the public site's
		 *	[localepicker] shortcode uses), the current one pre-selected.
		 *	Options carry a real ?locale=xx value - login.js/script.js just
		 *	navigate to it on change, no fetch/POST involved. init()'s query
		 *	handling picks that GET up identically on the login screen (no
		 *	session yet) and the dashboard. Locale codes/labels are
		 *	developer-controlled config, not user input - no escaping
		 *	needed, same as [localepicker] itself.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string 		$currentLocale
		 *
		 *	@return 	string
		 */
		private static function _localePickerHtml( array &$appData, string $currentLocale ): string {

			$options = '';

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
				$options .= '<option value="?locale='. $locale. '"'. ( $locale === $currentLocale ? ' selected' : '' ). '>'.
					\Nino\Html::renderTextfill( $appData, '/nino/locales/locale/'. $locale ). '</option>';

			return '<select id="admin-localepicker">'. $options. '</select>';
		}

		/**
		 *	Remember the admin's currently selected locale for this session
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSetLocale( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$locale = (string) ( self::postData()['locale'] ?? '' );

			if( \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown locale' ];
				return;
			}

			\Nino\Runtime::setSessionValue( $appData, './admin/locale', $locale );
		}

		/**
		 *	Read the "data" post field as a json object
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
	 *	Admin							Admin dashboard backend
	 *	Elements					Elements editor: fills the response for the /_admin/elements/*
	 *											routes registered by Admin::init() - list types, list/get/save/
	 *											delete elements within a type. Element *type* creation stays a
	 *											developer-only task (\Nino\Elements::insertElementType), not
	 *											exposed here.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Elements {

		public const string MANAGE_PERM = '/_admin/elements/manage';

		/**
		 *	List all element types with their model
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiTypes( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$types = [];
			foreach( self::types( $appData ) as $type ) {
				$typeData = self::typeData( $appData, $type );
				$types[] = [ 'type' => $type, 'title' => $typeData['title'] ?? $type, 'descr' => self::typeDescr( $appData, $type ), 'model' => $typeData['model'] ?? [] ];
			}

			$request['/nino/http/response']['body'] = [ 'types' => $types, 'locales' => \Nino\Locales::getAvailableLocales( $appData ), 'selectedLocale' => Admin::sessionLocale( $appData ) ];
		}

		/**
		 *	Element count per type, title included - shared by
		 *	Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'type', 'title', 'count' ], ... ]
		 */
		public static function typeCounts( array &$appData ): array {

			$counts = [];
			foreach( self::types( $appData ) as $type ) {
				$typeData = self::typeData( $appData, $type );
				$counts[] = [
					'type' 	=> $type,
					'title' => $typeData['title'] ?? $type,
					'count' => count( \Nino\Elements::queryElements( $appData, '/'. $type, [] ) ),
				];
			}

			return $counts;
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

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$type = (string) ( Admin::postData()['type'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown type' ];
				return;
			}

			$typeData = self::typeData( $appData, $type );
			$model = $typeData['model'] ?? [];
			$results 	= \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] );

			$elements = [];
			foreach( $results as $element ) {
				$uri = basename( $element['.uri'] ?? '' );
				$elements[] = [ 'uri' => $uri, 'label' => self::label( $model, $element, $uri ) ];
			}

			$request['/nino/http/response']['body'] = [ 'model' => $model, 'elements' => $elements ];
		}

		/**
		 *	Get one element's global fields + per-locale field values, for editing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiGet( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = Admin::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown type or element' ];
				return;
			}

			$typeData = self::typeData( $appData, $type );
			$model = $typeData['model'] ?? [];
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
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'element not found' ];
				return;
			}

			$request['/nino/http/response']['body'] = [ 'model' => $model, 'global' => $global, 'locales' => $locales ];
		}

		/**
		 *	Upload, process and store a new image for one "image"-typed model
		 *	field of an already-saved element. Committed immediately (not tied
		 *	to the form's "Speichern" button, unlike every other field). Stored
		 *	at a deterministic path (elements/<type>/<uri>, disambiguated with
		 *	the key/locale only where a real collision is possible), so a
		 *	replace overwrites in place - the rare leftover (the output format
		 *	itself changed, eg. jpeg -> png) is cleaned up once the new file is
		 *	safely written and the element record actually updated
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUploadImage( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Admin::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$key 			= (string) ( $data['key'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid type, uri or locale' ];
				return;
			}

			$model = self::typeData( $appData, $type )['model'] ?? [];
			if( ( $model[$key]['type'] ?? '' ) !== 'image' ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'not an image field' ];
				return;
			}

			$elementUri = '/'. $type. '/'. $uri;
			$isLocaleField = ( $model[$key]['locale'] ?? false ) === true;

			if( \Nino\Elements::getElement( $appData, $elementUri, '*' ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'element not found - save it once before uploading an image' ];
				return;
			}

			if( isset( $_FILES['file'] ) === false || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'no file uploaded' ];
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'could not read upload' ];
				return;
			}

			// The value to overwrite, per the field's own scope (matches how
			// Elements::_writeElementData() itself routes a save for this key)
			$oldElement 	= \Nino\Elements::getElement( $appData, $elementUri, $isLocaleField ? $locale : '*' );
			$oldFilename 	= is_array( $oldElement ) ? ( $oldElement[$key] ?? null ) : null;

			$width 		= (int) ( $model[$key]['width'] ?? 0 );
			$height 	= (int) ( $model[$key]['height'] ?? 0 );

			// Deterministic path - re-uploading the same slot overwrites in place,
			// so there's nothing to clean up beyond the rare case where the output
			// extension itself changes (handled below via the old/new filename diff).
			// Only disambiguated with "-key"/"-locale" where a collision is actually
			// possible (more than one image field on this type, or a per-locale one),
			// so the common case matches exactly "elements/<type>/<uri>.<ext>"
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
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid or oversized image' ];
				return;
			}

			$result = \Nino\Elements::updateElement( $appData, $elementUri, [ $key => $filename ], $locale );

			if( is_array( $result ) === false ) {
				\Nino\Images::delete( $appData, $filename );
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'save failed' ];
				return;
			}

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			$request['/nino/http/response']['body'] = [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ];
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

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Admin::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$isNew 		= ( $data['isNew'] ?? false ) === true;
			$fields 	= is_array( $data['fields'] ?? null ) ? $data['fields'] : [];

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid type, uri or locale' ];
				return;
			}

			// A model field with 'html' => true gets the same whitelist-tag sanitizing Text
			// uses - never trust the client's html
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
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => $errorMsg ?? 'save failed' ];
				return;
			}

			$request['/nino/http/response']['body'] = [ 'element' => $result ];
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

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = Admin::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid type or uri' ];
				return;
			}

			$elementUri = '/'. $type. '/'. $uri;
			$imageFilenames = self::_collectImageFilenames( $appData, $type, $elementUri );

			$errorMsg = null;
			set_error_handler( function( int $errno, string $errstr ) use ( &$errorMsg ): bool { $errorMsg = $errstr; return true; } );

			\Nino\Elements::deleteElement( $appData, $elementUri, '*' );

			restore_error_handler();

			if( $errorMsg !== null ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => $errorMsg ];
				return;
			}

			// Only once the element itself is actually gone - an "image" field's
			// value is just a filename reference, deleting the element wouldn't
			// otherwise touch the file it points to at all
			foreach( $imageFilenames as $filename )
				\Nino\Images::delete( $appData, $filename );

			$request['/nino/http/response']['body'] = [ 'ok' => true ];
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
		private static function _collectImageFilenames( array &$appData, string $type, string $elementUri ): array {

			$model = self::typeData( $appData, $type )['model'] ?? [];
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
		 *	@return 	array										Type data ('key' => [ 'type', 'locale', ... ])
		 */
		private static function typeData( array &$appData, string $type ): array {
			return \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
		}

		/**
		 *		Pick a human readable description for an type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Type name (eg. "services")
		 *
		 *	@return 	string									Readable description
		 */
		private static function typeDescr( array &$appData, string $type ): string {

		$elements = \Nino\Elements::queryElements( $appData, '/'. $type, [] );
			$info = '('. count( $elements ). ') ';

			foreach( $elements as $elData )
				$info .= str_replace( '/'.$type, '', $elData['.uri'] ). ', ';

			$info = ( strlen( $info ) > 150 ) ? substr( $info, 0, 150 ).' ..' : $info;

			return (string) $info;
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
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Text							Text editor: edits the site's [[key]] textfill values in
	 *											/text/global.php and /text/{locale}.php. The set of known keys
	 *											is developer-owned - this only ever edits existing key values,
	 *											never creates/removes keys. Keys listed in /text/blacklist.php
	 *											(technical values like uris, colors, typography) are hidden
	 *											entirely, since they aren't really "content" and editing them
	 *											could break routing/navigation/design rather than just copy.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Text {

		public const string MANAGE_PERM = '/_admin/text/manage';

		/**
		 *	List every editable key with its current value(s), whether it's a
		 *	global (locale-independent) or per-locale key, whether it currently
		 *	holds markup (so the editor offers the html editor for it) and a
		 *	maxlength derived from the longest current value. Blacklisted keys
		 *	(technical values, not content) are hidden entirely - unlike _dev's
		 *	own text editor, this one only ever edits existing key values,
		 *	never sees the blacklist itself.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiKeys( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$request['/nino/http/response']['body'] = [
				'keys' 					=> \Nino\Text::entries( $appData, false ),
				'locales' 			=> \Nino\Locales::getAvailableLocales( $appData ),
				'selectedLocale' => Admin::sessionLocale( $appData ),
			];
		}

		/**
		 *	Save several keys' values in one request (a whole category's
		 *	worth of fields, all posted together) - see \Nino\Text::saveBatch()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSaveBatch( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= Admin::postData();
			$items 	= is_array( $data['items'] ?? null ) ? $data['items'] : [];

			$request['/nino/http/response']['body'] = [ 'results' => \Nino\Text::saveBatch( $appData, $items, false ) ];
		}

		/**
		 *	Export one locale's raw file content (the same bracket-keyed array
		 *	the file itself stores) for an external translation round-trip -
		 *	see apiImport(). Deliberately per-locale-file, not the merged
		 *	apiKeys() view: global.php holds locale-independent values
		 *	(address, phone, ...) that should never be translated, so it's
		 *	never part of this export
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiExport( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$locale = (string) ( Admin::postData()['locale'] ?? '' );

			if( \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid locale' ];
				return;
			}

			$request['/nino/http/response']['body'] = [
				'locale' 	=> $locale,
				'content' => \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] ),
			];
		}

		/**
		 *	Import a translated locale file - written by pasting apiExport()'s
		 *	JSON output, round-tripped through an external LLM translation,
		 *	back in. Merges into the target locale's existing content rather
		 *	than replacing it wholesale (a key the import doesn't mention
		 *	stays as-is) and silently skips any blacklisted key even if
		 *	present in the import, since those are routing/technical values
		 *	(uris, colors, ...) an LLM asked to "translate everything" could
		 *	otherwise corrupt
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiImport( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Admin::postData();
			$locale 	= (string) ( $data['locale'] ?? '' );
			$content 	= $data['content'] ?? null;

			if( \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid locale' ];
				return;
			}

			if( is_array( $content ) === false || count( $content ) === 0 ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'empty or invalid content' ];
				return;
			}

			$blacklist 	= \Nino\Text::blacklist( $appData );
			$imported 	= 0;
			$skipped 		= 0;

			\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $existing ) use ( $content, $blacklist, &$imported, &$skipped ): array {

				foreach( $content as $bracketKey => $value ) {

					if( is_string( $bracketKey ) === false || str_starts_with( $bracketKey, '[[' ) === false || str_ends_with( $bracketKey, ']]' ) === false ) {
						$skipped++;
						continue;
					}

					if( isset( $blacklist[ trim( $bracketKey, '[]' ) ] ) === true ) {
						$skipped++;
						continue;
					}

					$existing[$bracketKey] = (string) $value;
					$imported++;
				}

				return $existing;
			} );

			$request['/nino/http/response']['body'] = [ 'imported' => $imported, 'skipped' => $skipped ];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Users							User editor: lets a user change their own mail/password (with
	 *											current-password confirmation), or - given the
	 *											'/_admin/users/manage' permission - anyone's, plus (manage-only,
	 *											no self-service) their per-module permissions (KNOWN_PERMS).
	 *											Sessions/tries/status stay a developer-only, direct-json task;
	 *											new users can't be created or deleted here either (same "values
	 *											only, not the set" rule as Elements types and Text keys).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Users {

		public const string MANAGE_PERM = '/_admin/users/manage';

		// Every per-module permission a manager can assign, label included -
		// the single source of truth for both apiSetPermissions()'s whitelist
		// and the checkboxes the frontend renders (see apiList()'s
		// 'permOptions'). Forward-references to Images/Logs/Submissions/
		// Newsletter (declared later in this file) resolve fine - PHP parses
		// the whole file before running any of it
		// 'label' is a [[/...]] fill key, not literal text - resolved client-side
		// via Nino.content.getText(), same as every other label this panel's
		// own JS renders. Reuses the nav bar's existing per-module translations
		// where the module already has one.
		public const array KNOWN_PERMS = [
			[ 'perm' => Elements::MANAGE_PERM, 	'label' => '/_admin/nav/elements' ],
			[ 'perm' => Text::MANAGE_PERM, 			'label' => '/_admin/nav/text' ],
			[ 'perm' => Images::MANAGE_PERM, 		'label' => '/_admin/nav/images' ],
			[ 'perm' => Submissions::VIEW_PERM, 	'label' => '/_admin/nav/submissions' ],
			[ 'perm' => Newsletter::MANAGE_PERM, 'label' => '/_admin/nav/newsletter' ],
			[ 'perm' => Logs::VIEW_PERM, 					'label' => '/_admin/nav/logs' ],
			[ 'perm' => self::MANAGE_PERM, 			'label' => '/_admin/users/label/permissions-manage' ],
		];

		/**
		 *	List every user the current user may see: everyone if they have
		 *	MANAGE_PERM, otherwise just themselves. Includes each user's raw
		 *	perms (so a manager's edit form can pre-check the right boxes)
		 *	and the assignable permOptions list, regardless of canManage -
		 *	harmless to a non-manager since they only ever see their own row
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$canManage = \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM );

			$users = [];
			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user ) {
				if( $canManage === false && $mail !== $current['mail'] )
					continue;
				$users[] = [ 'mail' => $mail, 'isSelf' => $mail === $current['mail'], 'perms' => $user['perms'] ?? [] ];
			}

			$request['/nino/http/response']['body'] = [ 'users' => $users, 'canManage' => $canManage, 'self' => $current['mail'], 'permOptions' => self::KNOWN_PERMS ];
		}

		/**
		 *	Update a user's mail and/or password
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data 				= Admin::postData();
			$username 		= (string) ( $data['username'] ?? '' );
			$newUsername 	= trim( (string) ( $data['mail'] ?? '' ) );
			$pw 					= (string) ( $data['pw'] ?? '' );
			$currentPw 		= (string) ( $data['currentPassword'] ?? '' );

			[ $allowed, $isSelf ] = self::_authorize( $appData, $username );

			if( $allowed === false ) {
				$request['/nino/http/response']['statusCode'] = 403;
				$request['/nino/http/response']['body'] = [ 'error' => 'not allowed' ];
				return;
			}

			if( $newUsername === '' || filter_var( $newUsername, FILTER_VALIDATE_EMAIL ) === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid mail' ];
				return;
			}

			// Changing your own account requires re-confirming your current password -
			// an admin editing someone else's account doesn't need to know their password
			if( $isSelf === true ) {
				$storedUser = \Nino\Auth::getUser( $appData, $username );
				if( $storedUser === false || password_verify( $currentPw, $storedUser['pw'] ) === false ) {
					$request['/nino/http/response']['statusCode'] = 401;
					$request['/nino/http/response']['body'] = [ 'error' => 'wrong current password' ];
					return;
				}
			}

			$result = \Nino\Auth::updateUser( $appData, $username, $newUsername, $pw );

			if( $result === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'mail already in use' ];
				return;
			}

			$request['/nino/http/response']['body'] = [ 'mail' => $result['mail'] ];
		}

		/**
		 *	Log a user out of every session ("überall abmelden")
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiLogoutAll( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data 		= Admin::postData();
			$username = (string) ( $data['username'] ?? '' );

			[ $allowed, $isSelf ] = self::_authorize( $appData, $username );

			if( $allowed === false ) {
				$request['/nino/http/response']['statusCode'] = 403;
				$request['/nino/http/response']['body'] = [ 'error' => 'not allowed' ];
				return;
			}

			$ok = \Nino\Auth::logoutAllSessions( $appData, $username );

			$request['/nino/http/response']['body'] = [ 'ok' => $ok, 'loggedOutSelf' => $isSelf ];
		}

		/**
		 *	Set a user's permissions - manager-only, unlike apiSave()/
		 *	apiLogoutAll() this has no self-service path at all (deliberately:
		 *	editing your own permissions is exactly the kind of accidental
		 *	self-lockout this doesn't try to guard against - _dev's Users
		 *	module remains the recovery path, same as every other "wrecked
		 *	config.php data" scenario in this project). Whitelisted against
		 *	KNOWN_PERMS (+ the '/*' wildcard) so this can never become a way
		 *	to write arbitrary permission strings into config.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSetPermissions( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Admin::postData();
			$username = (string) ( $data['username'] ?? '' );
			$perms 		= is_array( $data['perms'] ?? null ) ? $data['perms'] : [];

			if( \Nino\Auth::getUser( $appData, $username ) === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown user' ];
				return;
			}

			$allowed 	= array_merge( array_column( self::KNOWN_PERMS, 'perm' ), [ '/*' ] );
			$perms 		= array_values( array_intersect( $perms, $allowed ) );

			$appData['/nino/auth/user'][$username]['perms'] = $perms;
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			$request['/nino/http/response']['body'] = [ 'perms' => $perms ];
		}

		/**
		 *	Whether the current user may act on $username, and whether it's themselves
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$username 		Target username
		 *
		 *	@return 	array										[ allowed, isSelf ]
		 */
		private static function _authorize( array &$appData, string $username ): array {

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$isSelf 	= $current !== false && $current['mail'] === $username;
			$canManage = \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM );

			return [ $isSelf === true || $canManage === true, $isSelf ];
		}
	}

	/**
	 *	Nino										A compact filesystembased php framework
	 *	Admin										Admin backend
	 *	Images									Admin "Images" panel: developer-fixed image slots
	 *													(/nino/html/images in config.php) - the admin can only
	 *													replace a slot's current image, never add/remove slots,
	 *													same shape as Users can only edit accounts, not create them
	 *
	 *	@package								Dape/Nino
	 *	@author									David Perchermeier <mail@dape.io>
	 *	@link										https://github.com/dapeio/nino
	 */

	class Images {

		public const string MANAGE_PERM = '/_admin/images/manage';

		/**
		 *	List every developer-fixed image slot
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$slots = [];
			foreach( \Nino\Images::getSlots( $appData ) as $uri => $slot )
				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'url' 			=> ( empty( $slot['filename'] ) === false ) ? \Nino\Images::getUrl( $appData, $slot['filename'] ) : null,
				];

			$request['/nino/http/response']['body'] = [ 'slots' => $slots ];
		}

		/**
		 *	Upload, process and store a new image for one slot. Committed
		 *	immediately, same as Elements::apiUploadImage() - stored at a
		 *	deterministic path ("images/<uri>"), so a replace overwrites in
		 *	place; the previous file only needs deleting in the rare case the
		 *	output format itself changed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUpload( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$uri 	= (string) ( Admin::postData()['uri'] ?? '' );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false ) {
				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['body'] = [ 'error' => 'unknown slot' ];
				return;
			}

			if( isset( $_FILES['file'] ) === false || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'no file uploaded' ];
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'could not read upload' ];
				return;
			}

			$oldFilename = $slot['filename'] ?? null;

			$filename = \Nino\Images::process( $appData, $bytes, (int) ( $slot['width'] ?? 0 ), (int) ( $slot['height'] ?? 0 ), ltrim( $uri, '/' ) );

			if( $filename === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				$request['/nino/http/response']['body'] = [ 'error' => 'invalid or oversized image' ];
				return;
			}

			\Nino\Images::setSlotFilename( $appData, $uri, $filename );

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			$request['/nino/http/response']['body'] = [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Backup						Encrypted daily snapshot of everything the admin panel can
	 *											write to at runtime - triggered once per authenticated
	 *											admin request per day (see Admin::guard()), not a cron
	 *											job, so it needs no server-level scheduling at all. Lives
	 *											here rather than in the kernel (_nino/Nino.php) since
	 *											it's an _admin-specific operational concern, not something
	 *											every Nino deployment needs - a site with no _admin has
	 *											nothing to back up in the first place.
	 *
	 *											Three independent layers, none of which need any
	 *											webserver configuration:
	 *											- stored under a one-time random directory name, never
	 *											  linked anywhere, so it can't be crawled/guessed
	 *											- .php extension with a self-terminating stub - a direct
	 *											  request hits exit() before the real (encrypted) data
	 *											  ever gets output
	 *											- AES-256-GCM encrypted payload (12-byte iv + 16-byte tag
	 *											  + ciphertext), so raw filesystem access to the file
	 *											  alone still isn't enough without the key
	 *
	 *											The encrypted payload is base64 and stays inside a
	 *											single quoted string literal in the same PHP block as
	 *											the exit() - never appended as raw bytes after a closing
	 *											"?>". First attempt did that and broke in practice: PHP
	 *											tokenizes/compiles the *entire* file before running any
	 *											of it, and "?>" turns the tokenizer back into tag-
	 *											scanning mode for the rest of the file. A large enough
	 *											blob of encrypted (ie. uniformly random) bytes will
	 *											eventually contain a literal "<?=" by chance - confirmed
	 *											this empirically, it broke on the very first real backup
	 *											- which reopens a PHP block partway through random noise
	 *											and fails to compile, so exit() never even runs and the
	 *											request 500s instead of 403ing. Keeping everything as one
	 *											valid, never-closed PHP block/string sidesteps this
	 *											entirely - there's no point where the tokenizer re-scans
	 *											for tags inside a string literal.
	 *
	 *											Restore lives in _dev, not here - see _dev/Dev.php. This
	 *											class only ever creates, never reads/decrypts, backups.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Backup {

		private const int RETENTION_DAYS = 14;

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	Most recent backup date on file, if any - shared by
		 *	Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							"Y-m-d", or null if none exist yet
		 */
		public static function lastDate( array &$appData ): ?string {

			if( isset( $appData['/nino/backup/dir'] ) === false )
				return null;

			$dir 	= \Nino\Filesystem::getPath( $appData ). '/_admin/'. $appData['/nino/backup/dir'];
			$dates = [];

			foreach( glob( $dir. '/*.php' ) ?: [] as $file )
				if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
					$dates[] = basename( $file, '.php' );

			if( count( $dates ) === 0 )
				return null;

			rsort( $dates );

			return $dates[0];
		}

		/**
		 *	Bootstrap the backup dir/key on first call, then create today's
		 *	backup if it doesn't exist yet and prune anything past retention.
		 *	Called from Admin::guard() - so once per authenticated request,
		 *	not a cron job. Failures never interrupt the admin action that
		 *	triggered this (backup is a safety net, not something that should
		 *	itself become a new way to break the admin panel) - logged, not
		 *	thrown.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function maybeRun( array &$appData ): void {

			try {

				if( ( $appData['/nino/admin/backups'] ?? true ) === false )
					return;

				self::_bootstrap( $appData );

				$dir 		= \Nino\Filesystem::getPath( $appData ). '/_admin/'. $appData['/nino/backup/dir'];
				$path 	= $dir. '/'. date( 'Y-m-d' ). '.php';

				if( is_file( $path ) === true )
					return;

				self::_create( $appData, $dir, $path );
				self::_prune( $dir );

				$user = \Nino\Auth::getCurrentUser( $appData );
				if( $user !== false )
					Logs::record( $appData, $user['mail'], 'Backup created' );

			} catch( \Throwable $e ) {
				trigger_error( 'Backup failed: '. $e->getMessage() );
			}
		}

		/**
		 *	Generate the random backup directory name and encryption key on
		 *	first use - a project that never opens _admin never gets these
		 *	config.php keys at all.
		 *
		 *	The key also gets an independent copy written into _dev/ (behind
		 *	the same .php exit-stub as a backup itself, since it's just as
		 *	sensitive) - _dev/Dev.php's Restore module reads *that* copy,
		 *	deliberately not this one, so restoring never depends on
		 *	config.php's data being intact. This copy is reconciled on every
		 *	call independent of whether dir/key already existed, since an
		 *	install that had Backup running before Restore/_dev's key file
		 *	existed would otherwise never get one (the dir/key generation
		 *	itself only ever runs once). Skipped if _dev/ isn't present
		 *	(already removed after initial setup, or never deployed) - fine,
		 *	restoring isn't possible without it either way.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _bootstrap( array &$appData ): void {

			if( isset( $appData['/nino/backup/dir'] ) === false || isset( $appData['/nino/backup/key'] ) === false ) {

				$appData['/nino/backup/dir'] = '.backups-'. bin2hex( random_bytes( 16 ) );
				$appData['/nino/backup/key'] = base64_encode( random_bytes( 32 ) );

				\Nino\AppData::writeContentData( $appData, [ '/nino/backup/dir', '/nino/backup/key' ] );
			}

			// Independent of the above: an install that already had a dir/key
			// before _dev's Restore module existed (or whose _dev/.restore-key.php
			// was lost/deleted since) never gets one otherwise, since this only
			// ran once, on first-ever bootstrap
			$devDir 		= \Nino\Filesystem::getPath( $appData ). '/_dev';
			$devKeyPath = $devDir. '/.restore-key.php';

			if( is_dir( $devDir ) === true && is_file( $devKeyPath ) === false )
				file_put_contents( $devKeyPath, self::STUB_PREFIX. $appData['/nino/backup/key']. self::STUB_SUFFIX );
		}

		/**
		 *	Tar+gzip every file the admin panel can write to at runtime,
		 *	encrypt it, and write it out behind the .php exit-stub. Built via
		 *	PharData rather than shelling out to `tar`/`gzip` - pure PHP, and
		 *	unlike the executable Phar class, PharData isn't restricted by
		 *	the phar.readonly ini setting many hosts enable by default.
		 *
		 *	Reads each file's bytes itself and hands them to addFromString()
		 *	rather than pointing addFile() at the path - addFile() alone
		 *	archived a stale, already-out-of-date copy of a just-written file
		 *	(config.php, right after _bootstrap() writes it) even though a
		 *	plain file_get_contents() at that same point already saw the
		 *	current content. Whatever addFile()'s own internal caching is
		 *	keyed on, supplying the bytes directly sidesteps it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$path					Absolute path of today's backup file
		 *
		 *	@return 	void
		 */
		private static function _create( array &$appData, string $dir, string $path ): void {

			if( is_dir( $dir ) === false )
				mkdir( $dir, 0755, true );

			$tmpTar = tempnam( sys_get_temp_dir(), 'ninobackup' ). '.tar';

			$phar = new \PharData( $tmpTar );
			foreach( \Nino\Filesystem::backupManifest( $appData ) as $absolute => $archiveName )
				$phar->addFromString( $archiveName, file_get_contents( $absolute ) );
			$phar->compress( \Phar::GZ );
			unset( $phar );
			unlink( $tmpTar );

			$gz = file_get_contents( $tmpTar. '.gz' );
			unlink( $tmpTar. '.gz' );

			$key 		= base64_decode( $appData['/nino/backup/key'] );
			$iv 		= random_bytes( 12 );
			$tag 		= '';
			$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			file_put_contents( $path, self::STUB_PREFIX. base64_encode( $iv. $tag. $cipher ). self::STUB_SUFFIX );
		}

		/**
		 *	Delete dated backups older than RETENTION_DAYS. Only ever touches
		 *	files whose name is exactly a plain "Y-m-d.php" date - _dev's
		 *	Restore::_safetySnapshot() also writes into this same directory
		 *	(differently named, "pre-restore-<timestamp>.php") and must never
		 *	be swept up here
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir ): void {

			$cutoff = ( new \DateTime( '-'. self::RETENTION_DAYS. ' days' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( $dir, '', 'Y-m-d', '.php', $cutoff );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Logs							Plaintext (behind the same self-terminating .php stub as
	 *											Backup - see that class' docblock for why) audit trail
	 *											of admin actions: who logged in, and every save/delete
	 *											that changed something. One line per event, appended to
	 *											the current day's file; Admin::handlePost() calls
	 *											record() once per successfully-dispatched mutating
	 *											action, Admin::init()'s login callback calls it once per
	 *											successful login. Not encrypted like Backup - there's no
	 *											key to manage and nothing here is as sensitive as a
	 *											password hash, the stub alone (no plaintext without
	 *											executing PHP, which exit()s first) is enough.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Logs {

		public const string VIEW_PERM = '/_admin/logs/view';

		private const int RETENTION_DAYS = 14;

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	Append one line to today's log file, then prune anything past
		 *	RETENTION_DAYS. Failures are logged, never thrown - an audit
		 *	trail is a nice-to-have, it must never break the admin action
		 *	it's recording
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$actor				The acting user's mail
		 *	@param		string		$message			Human-readable description of what happened
		 *
		 *	@return 	void
		 */
		public static function record( array &$appData, string $actor, string $message ): void {

			if( ( $appData['/nino/admin/logs'] ?? true ) === false )
				return;

			try {

				self::_bootstrap( $appData );

				$dir = \Nino\Filesystem::getPath( $appData ). '/_admin/'. $appData['/nino/logs/dir'];

				// 0755, see Filesystem::forceDir() - this directory's only real
				// protection is its random name, world-writable undoes that
				if( is_dir( $dir ) === false )
					mkdir( $dir, 0755, true );

				$relPath = '/_admin/'. $appData['/nino/logs/dir']. '/'. date( 'Y-m-d' ). '.php';
				$path 	 = $dir. '/'. date( 'Y-m-d' ). '.php';

				// Locked directly, not via Filesystem::mutate(): this file is
				// base64+stub encoded, not the plain array format getFileContent()/
				// putFileContent() know how to read - but two concurrent admin
				// actions still need to not both read the same line list and each
				// overwrite the other's append
				if( \Nino\Filesystem::lockFile( $appData, $relPath ) === false )
					return;

				$lines 	 = is_file( $path ) === true ? self::_readLines( $path ) : [];
				$lines[] = date( 'Y-m-d H:i' ). '  '. $actor. '  '. $message;

				file_put_contents( $path, self::STUB_PREFIX. base64_encode( implode( "\n", $lines ) ). self::STUB_SUFFIX );

				\Nino\Filesystem::unlockFile( $appData, $relPath );

				self::_prune( $dir );

			} catch( \Throwable $e ) {
				trigger_error( 'Activity log write failed: '. $e->getMessage() );
			}
		}

		/**
		 *	List every recorded line within the retention window, most
		 *	recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			$request['/nino/http/response']['body'] = [ 'lines' => array_reverse( self::_allLines( $appData ) ) ];
		}

		/**
		 *	The most recent $limit lines, most recent first - shared by
		 *	Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$limit
		 *
		 *	@return 	array
		 */
		public static function recentLines( array &$appData, int $limit ): array {
			return array_slice( array_reverse( self::_allLines( $appData ) ), 0, $limit );
		}

		/**
		 *	Read every recorded line within the retention window, oldest
		 *	first (as stored)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _allLines( array &$appData ): array {

			$lines = [];

			if( isset( $appData['/nino/logs/dir'] ) === true ) {

				$dir 		= \Nino\Filesystem::getPath( $appData ). '/_admin/'. $appData['/nino/logs/dir'];
				$files 	= glob( $dir. '/*.php' ) ?: [];

				sort( $files );

				foreach( $files as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$lines = array_merge( $lines, self::_readLines( $file ) );
			}

			return $lines;
		}

		/**
		 *	Generate the random log directory name on first use - same
		 *	one-time-generation shape as Backup::_bootstrap(), independent
		 *	random directory (not shared with Backup's), so leaking one
		 *	doesn't expose the other
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _bootstrap( array &$appData ): void {

			if( isset( $appData['/nino/logs/dir'] ) === true )
				return;

			$appData['/nino/logs/dir'] = '.logs-'. bin2hex( random_bytes( 16 ) );

			\Nino\AppData::writeContentData( $appData, [ '/nino/logs/dir' ] );
		}

		/**
		 *	Read one day's file back into its individual lines
		 *
		 *	@param		string		$path					Absolute path to the .php file
		 *
		 *	@return 	string[]
		 */
		private static function _readLines( string $path ): array {

			$raw 			= file_get_contents( $path );
			$decoded 	= base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ) );

			return $decoded === '' ? [] : explode( "\n", $decoded );
		}

		/**
		 *	Delete dated log files older than RETENTION_DAYS - same
		 *	basename-must-match-a-plain-date guard as Backup::_prune(), so
		 *	nothing other than a "Y-m-d.php" file is ever considered
		 *
		 *	@param		string		$dir					Absolute path to the log directory
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir ): void {

			$cutoff = ( new \DateTime( '-'. self::RETENTION_DAYS. ' days' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( $dir, '', 'Y-m-d', '.php', $cutoff );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Submissions				Read-only view of the contact form's submissions -
	 *												\Nino\Modules\Form (in _nino/Nino.php) writes these,
	 *												this reads its storage independently (same shape as
	 *												Dev\Restore reading Admin\Backup's output: fixed path,
	 *												never writes anything here). Project-root /data, plain
	 *												array files - not an _admin concern, see Shortcodes\Form's
	 *												own docblock.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Submissions {

		public const string VIEW_PERM = '/_admin/submissions/view';

		/**
		 *	List every recorded submission within the retention window
		 *	(see Shortcodes\Form::RETENTION_MONTHS), most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			$request['/nino/http/response']['body'] = [ 'entries' => array_reverse( self::_entries( $appData ) ) ];
		}

		/**
		 *	How many submissions are currently on file (retention window) -
		 *	shared by apiList above and Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {
			return count( self::_entries( $appData ) );
		}

		/**
		 *	Read every recorded submission within the retention window,
		 *	oldest first (as stored)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _entries( array &$appData ): array {

			$entries 	= [];
			$dir 			= \Nino\Filesystem::getPath( $appData ). '/data';
			$files 		= glob( $dir. '/forms.*.php' ) ?: [];

			sort( $files );

			foreach( $files as $file )
				if( preg_match( '/^\d{4}-\d{2}$/', substr( basename( $file, '.php' ), 6 ) ) === 1 )
					foreach( \Nino\Filesystem::getFileContent( $appData, '/data/'. basename( $file ), [] ) as $entry )
						if( is_array( $entry ) === true )
							$entries[] = $entry;

			return $entries;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Newsletter				View + delete of the newsletter signups -
	 *												\Nino\Modules\Newsletter (in _nino/Nino.php) writes
	 *												these, this reads/deletes its storage independently (same
	 *												shape as Admin\Submissions reading Shortcodes\Form's
	 *												output: fixed path). Project-root /data, plain array file -
	 *												not an _admin concern, see Shortcodes\Newsletter's own
	 *												docblock. Besides apiDelete, entries also go away via the
	 *												self-service unsubscribe link (Shortcodes\Newsletter).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Newsletter {

		public const string MANAGE_PERM = '/_admin/newsletter/manage';

		private const string PATH = '/data/newsletter.php';

		/**
		 *	How many subscribers are currently on file - shared by
		 *	Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {
			return count( \Nino\Filesystem::getFileContent( $appData, self::PATH, [] ) );
		}

		/**
		 *	List every recorded newsletter signup, most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$entries = \Nino\Filesystem::getFileContent( $appData, self::PATH, [] );

			$request['/nino/http/response']['body'] = [ 'entries' => array_reverse( $entries ) ];
		}

		/**
		 *	Delete one subscriber by email - the admin-side counterpart to
		 *	the visitor's own self-service unsubscribe link (see
		 *	Shortcodes\Newsletter in _nino/Nino.php)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$email = (string) ( Admin::postData()['email'] ?? '' );

			if( $email === '' ) {
				$request['/nino/http/response']['statusCode'] = 404;
				return;
			}

			$found = false;

			\Nino\Filesystem::mutate( $appData, self::PATH, function( array $entries ) use ( $email, &$found ): ?array {

				$filtered = array_values( array_filter( $entries, function( $entry ) use ( $email ) { return ( $entry['email'] ?? null ) !== $email; } ) );

				if( count( $filtered ) === count( $entries ) )
					return null;

				$found = true;
				return $filtered;
			} );

			if( $found === false )
				$request['/nino/http/response']['statusCode'] = 404;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Dashboard					Landing panel: a handful of read-only numbers
	 *											already available from the other panels (element
	 *											counts, submissions/newsletter totals, last backup,
	 *											recent activity), pulled together into one overview
	 *											instead of having to open each tab in turn. Doesn't
	 *											add any storage of its own
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Dashboard {

		/**
		 *	Gather the overview numbers. Being reachable by any authenticated
		 *	admin (Admin::guard(), not guardPerm()) only covers the panel
		 *	itself - elements/lastBackup are basic operational info nobody's
		 *	gated behind a specific permission, but submissions/newsletter/
		 *	recentActivity each surface another module's own data and are
		 *	included only for an admin who actually holds that module's
		 *	permission, same as opening its panel directly would require
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSummary( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$body = [
				'elements' 		=> Elements::typeCounts( $appData ),
				'lastBackup' 	=> Backup::lastDate( $appData ),
			];

			if( \Nino\Auth::checkPermission( $appData, Submissions::VIEW_PERM ) === true )
				$body['submissions'] = Submissions::count( $appData );

			if( \Nino\Auth::checkPermission( $appData, Newsletter::MANAGE_PERM ) === true )
				$body['newsletter'] = Newsletter::count( $appData );

			if( \Nino\Auth::checkPermission( $appData, Logs::VIEW_PERM ) === true )
				$body['recentActivity'] = Logs::recentLines( $appData, 8 );

			$request['/nino/http/response']['body'] = $body;
		}
	}
}