<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Admin							Admin dashboard backend - all \Nino\Editor\* classes live in
 *												this single file, same convention as _nino/Nino.php
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Editor {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Admin							Bootstraps the admin area: routes, assets, textfills and the
	 *											GET/POST /_editor request handling. The only class _editor/index.php
	 *											calls into - everything else (routing) is registered here, the
	 *											individual domain classes (eg. Elements) only fill the response.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Editor {

		/**
		 *	Bootstrap the admin area: register routes, assets, textfills and
		 *	the response callbacks for every admin route
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// These runtime-only routes are owned by the tool itself and must win
			// over a stale or hand-written config entry at the same uri - the same
			// reasoning (and the same former '+=' bug) as Install::init()'s own
			// routes. A persisted 'GET://_editor' (hand-written through _admin's
			// Config module, or a webpage entry created before the reserved-uri
			// check existed) would otherwise shadow the editor entirely
			$appData['/nino/http/routes']['GET://_editor'] = [
				'uri' 				=> '/_editor',
				'body'				=> '[template /_editor/templates/page-index]',
				'statusCode'	=> 200
			];
			$appData['/nino/http/routes']['POST://_editor'] = [ 'uri' => '/_editor' ];

			$appData['/nino/html/assets']['/_editor/.cache/style.css'] = [
				'/_editor/assets/style.css',
			];
			$appData['/nino/html/assets']['/_editor/.cache/script.js'] = [
				'/_nino/Nino.js',
				'/_nino/Nino.admin.js',
				'/_editor/assets/script.js',
				'/_editor/assets/html-editor.js',
				'/_editor/assets/dashboard.js',
				'/_editor/assets/elements.js',
				'/_editor/assets/text.js',
				'/_editor/assets/images.js',
				'/_editor/assets/users.js',
				'/_editor/assets/logs.js',
				'/_editor/assets/submissions.js',
				'/_editor/assets/newsletter.js',
			];
			$appData['/nino/html/assets']['/_editor/.cache/login.js'] = [
				'/_nino/Nino.js',
				'/_nino/Nino.admin.js',
				'/_editor/assets/login.js',
			];

			// A GET ?locale=xx (used by the UI-chrome picker) works even
			// logged out, login screen included - unlike the POST
			// admin/locale action Elements/Text's live content-locale
			// switch uses, this needs no guard() at all: GET /_editor is
			// already reachable without a session, so there's nothing to
			// loosen. Persists to the same ./admin/locale session key.
			$queryLocale = (string) ( $_GET['locale'] ?? '' );
			if( $queryLocale !== '' && \Nino\Locales::verifyLocale( $appData, $queryLocale ) === true )
				\Nino\Runtime::setSessionValue( $appData, './admin/locale', $queryLocale );

			// Sync the shared "current locale" to the admin's own remembered
			// locale (session locale) before loading _editor's own text fills -
			// reuses the exact mechanism Elements/Text's locale switch already
			// persists (POST admin/locale), so switching it also switches
			// which language the admin UI chrome itself renders in
			\Nino\Locales::setCurrentLocale( $appData, self::sessionLocale( $appData ) );

			$currentLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Html::addFills( $appData, \Nino\Filesystem::getFileContent( $appData, '/_editor/text/'. $currentLocale. '.php', [] ), $currentLocale );
			\Nino\Html::addFills( $appData, [ '[[/_editor/localepicker]]' => self::_localePickerHtml( $appData, $currentLocale ) ], '*' );

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_editor', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_editor', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_editor response: dashboard if logged in, else the login form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			self::_logLoginOnce( $appData );

			if( \Nino\Auth::getCurrentUser( $appData ) === false ) {
				$request['/nino/http/response']['body'] = '[template /_editor/templates/page-login]';
				return;
			}

			// Do not advertise panels whose endpoint this account cannot use.
			// The API permission checks remain authoritative; this fill only keeps
			// the navigation honest and avoids predictable 403 requests.
			$panels = [ 'dashboard' ];
			foreach( [
				'elements' 		=> Elements::MANAGE_PERM,
				'text' 				=> Text::MANAGE_PERM,
				'images' 			=> Images::MANAGE_PERM,
			] as $panel => $perm )
				if( \Nino\Auth::checkPermission( $appData, $perm ) === true )
					$panels[] = $panel;

			$panels[] = 'users'; // every account may edit its own profile

			foreach( [
				'submissions' 	=> Submissions::VIEW_PERM,
				'newsletter' 	=> Newsletter::MANAGE_PERM,
				'logs' 				=> Logs::VIEW_PERM,
			] as $panel => $perm )
				if( \Nino\Auth::checkPermission( $appData, $perm ) === true )
					$panels[] = $panel;

			\Nino\Html::addFills( $appData, [ '[[/_editor/panels]]' => implode( ' ', $panels ) ], '*' );
		}

		/**
		 *	Record a successful login to the activity log, the first time
		 *	the now-authenticated session touches _editor.
		 *
		 *	Not hooked directly into Auth's login (the obvious-seeming
		 *	approach) because that goes through the kernel's generic
		 *	/.nino/auth/login uri, which - unlike GET/POST /_editor - isn't
		 *	guaranteed to be routed through _editor/index.php at all: a
		 *	typical webserver config maps /_editor/* to _editor/index.php via
		 *	its own directory/file, but /.nino/auth/login matches no real
		 *	file under _editor/ and falls through to the site's root
		 *	index.php instead, which never calls Editor::init() and so never
		 *	registers any _editor-specific callback. Piggybacking on guard()/
		 *	handleGet() instead sidesteps that entirely, since _editor's own
		 *	bootstrap is guaranteed to run for every real GET/POST /_editor
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
		 *	Fill the POST /_editor response. Every admin api request goes
		 *	through this single route and is dispatched by $_POST['action'],
		 *	so the frontend never depends on the webserver routing anything
		 *	beyond the one already-required /_editor uri.
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
				'admin/locale' 		=> [ Editor::class, 'apiSetLocale' ],
				'dashboard/summary' => [ Dashboard::class, 'apiSummary' ],
				'elements/types' 	=> [ Elements::class, 'apiTypes' ],
				'elements/list' 		=> [ Elements::class, 'apiList' ],
				'elements/get' 			=> [ Elements::class, 'apiGet' ],
				'elements/uploadimage' => [ Elements::class, 'apiUploadImage' ],
				'elements/save' 		=> [ Elements::class, 'apiSave' ],
				'elements/delete' 	=> [ Elements::class, 'apiDelete' ],
				'text/keys' 				=> [ Text::class, 'apiKeys' ],
				'text/savebatch' 	=> [ Text::class, 'apiSaveBatch' ],
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
				\Nino\Http::fail( $request, 404, 'unknown action' );
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
				'elements/uploadimage' => 'Upload Element Image /'. ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ). ' '. ( $data['key'] ?? '' ),
				'elements/delete' => 'Delete Element /'. ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ),
				'text/savebatch' 	=> 'Edit Text /'. self::_textCategory( is_array( $data['items'] ?? null ) ? $data['items'] : [] ),
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
		 *	domain class (Elements, Text, ...) that fills a POST /_editor response.
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
				\Nino\Http::fail( $request, 401, 'not logged in' );
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
				\Nino\Http::fail( $request, 403, 'not allowed' );
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
		 *	Build the [[/_editor/localepicker]] fill: a <select> (stays
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

			return '<select id="editor-localepicker">'. $options. '</select>';
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
				\Nino\Http::fail( $request, 400, 'unknown locale' );
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
	 *	Elements					Elements editor: fills the response for the /_editor/elements/*
	 *											routes registered by Editor::init() - list types, list/get/save/
	 *											delete elements within a type. Element *type* creation stays a
	 *											developer-only task (\Nino\Elements::insertElementType), not
	 *											exposed here.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Elements {

		public const string MANAGE_PERM = '/_editor/elements/manage';

		/**
		 *	List all element types with their model
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiTypes( array &$appData, array &$request ): void {

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$types = [];
			foreach( self::types( $appData ) as $type ) {
				$typeData = self::typeData( $appData, $type );
				$next 		= \Nino\Elements::readAutoincrement( $typeData );
				// A numbered type (set up in /_admin, see Elements::AUTOINCREMENT_PAD)
				// has no uri to ask for - the form shows the number the next
				// element would get instead of a text field
				$types[] = [
					'type' 					=> $type,
					'title' 				=> $typeData['title'] ?? $type,
					'descr' 				=> self::typeDescr( $appData, $type ),
					'model' 				=> $typeData['model'] ?? [],
					'autoincrement' => $next !== null,
					'nextUri' 			=> ( $next === null ) ? '' : \Nino\Elements::autoincrementUri( max( $next, \Nino\Elements::autoincrementSeed( $typeData ) ) ),
				];
			}

			\Nino\Http::ok( $request, [ 'types' => $types, 'locales' => \Nino\Locales::getAvailableLocales( $appData ), 'selectedLocale' => Editor::sessionLocale( $appData ) ] );
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
					'count' => count( \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] ) ),
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$type = (string) ( Editor::postData()['type'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			$typeData = self::typeData( $appData, $type );
			$model = $typeData['model'] ?? [];
			$locale = (string) ( Editor::postData()['locale'] ?? '' );

			// The table view reads one translation at a time: global fields plus
			// the picked locale's. Without one, fall back to the old "any locale"
			// behaviour, which is what the plain list wants
			$results 	= \Nino\Elements::queryElements( $appData, '/'. $type, [],
				$locale !== '' && \Nino\Locales::verifyLocale( $appData, $locale ) === true ? $locale : '*', [] );

			$columns = self::displayableColumns( $model );

			$elements = [];
			foreach( $results as $element ) {

				$uri 		= basename( $element['.uri'] ?? '' );
				$values = [];

				// Only the fields a cell can actually show, so a type with a
				// large rich-text or image field does not ship it to a list
				// that would never render it.
				//
				// Nested rather than merged into the row: a model is free to
				// name a field "label" or "uri", and flattening let such a field
				// overwrite the row's own identity
				foreach( $columns as $key )
					$values[$key] = $element[$key] ?? null;

				$elements[] = [
					'uri' 		=> $uri,
					'label' 	=> self::label( $model, $element, $uri ),
					'values' 	=> $values,
				];
			}

			\Nino\Http::ok( $request, [
				'model' 		=> $model,
				'columns' 	=> $columns,
				'elements' 	=> $elements,
				'total' 		=> count( $elements ),
			] );
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = Editor::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				\Nino\Http::fail( $request, 404, 'unknown type or element' );
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
				\Nino\Http::fail( $request, 404, 'element not found' );
				return;
			}

			\Nino\Http::ok( $request, [ 'model' => $model, 'global' => $global, 'locales' => $locales ] );
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Editor::postData();
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

			$elementUri = '/'. $type. '/'. $uri;
			$isLocaleField = ( $model[$key]['locale'] ?? false ) === true;

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

			// The value to overwrite, per the field's own scope (matches how
			// Elements::_writeElementData() itself routes a save for this key)
			$oldElement 	= \Nino\Elements::getElement( $appData, $elementUri, $isLocaleField ? $locale : '*' );
			$oldFilename 	= is_array( $oldElement ) ? ( $oldElement[$key] ?? null ) : null;
			$oldBytes 		= is_string( $oldFilename ) && $oldFilename !== '' ? \Nino\Images::read( $appData, $oldFilename ) : false;

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
				\Nino\Http::fail( $request, 400, 'invalid or oversized image' );
				return;
			}

			$result = \Nino\Elements::updateElement( $appData, $elementUri, [ $key => $filename ], $locale );

			if( is_array( $result ) === false ) {
				// process() writes deterministic paths. A same-format replacement
				// therefore already overwrote the old file before the metadata update
				// above could be vetoed; restore that snapshot instead of deleting the
				// filename the unchanged element record still references.
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Editor::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$isNew 		= ( $data['isNew'] ?? false ) === true;
			$fields 	= is_array( $data['fields'] ?? null ) ? $data['fields'] : [];

			// A numbered type assigns the uri, so an insert into one arrives
			// without one and the kernel allocates it under the type file's lock
			// (see Elements::AUTOINCREMENT_PAD). An update always names its
			// element - by then it has a uri like any other.
			$numbered = $isNew === true && $uri === ''
				&& in_array( $type, self::types( $appData ), true ) === true
				&& \Nino\Elements::readAutoincrement( self::typeData( $appData, $type ) ) !== null;

			if( in_array( $type, self::types( $appData ), true ) === false || ( $uri === '' && $numbered === false ) || str_contains( $uri, '/' ) === true || ( $isNew === true && $numbered === false && self::_validNewUri( $uri ) === false ) || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type, uri or locale' );
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
				\Nino\Http::fail( $request, 400, $errorMsg ?? 'save failed' );
				return;
			}

			// A numbered type's counter has just moved on, so the form's "saving
			// creates /type/00007" promise would otherwise still name the number
			// this very save consumed
			$next = \Nino\Elements::getAutoincrement( $appData, '/'. $type );

			\Nino\Http::ok( $request, [
				'element' => $result,
				'nextUri' => ( $next === null ) ? '' : \Nino\Elements::autoincrementUri( $next ),
			] );
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = Editor::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				\Nino\Http::fail( $request, 400, 'invalid type or uri' );
				return;
			}

			$elementUri = '/'. $type. '/'. $uri;
			$imageFilenames = self::_collectImageFilenames( $appData, $type, $elementUri );

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
			foreach( glob( \Nino\Filesystem::path( $appData, '/elements' ). '/*.php' ) ?: [] as $file )
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

			$elements = \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] );
			$info = '('. count( $elements ). ') ';

			foreach( $elements as $elData )
				$info .= str_replace( '/'.$type, '', $elData['.uri'] ). ', ';

			$info = ( strlen( $info ) > 150 ) ? substr( $info, 0, 150 ).' ..' : $info;

			return (string) $info;
		}

		/**
		 *	The model field keys a table cell can show: everything except an
		 *	image (a file), an array (a structure) and a rich-text string (its
		 *	value is markup, and a cell renders text).
		 *
		 *	Mirrored by Nino.adminUi.tableModel.isDisplayable() on the frontend -
		 *	the server decides what to send, the client what to draw, and the two
		 *	have to agree on the same rule.
		 *
		 *	@param		array 		$model				Type model definition
		 *
		 *	@return 	array										Field keys, in model order
		 */
		public static function displayableColumns( array $model ): array {

			$keys = [];

			foreach( $model as $key => $field ) {

				$type = (string) ( $field['type'] ?? '' );

				if( in_array( $type, [ 'image', 'array' ], true ) === true )
					continue;

				if( $type === 'string' && ( $field['html'] ?? false ) === true )
					continue;

				$keys[] = (string) $key;
			}

			return $keys;
		}

		/**
		 *	Pick a human readable label for an element (for the list view):
		 *	its 'title' field, or its own uri.
		 *
		 *	Deliberately only 'title'. Guessing - 'label'/'name', then the
		 *	first string field in model order - meant the list showed whichever
		 *	field happened to come first, so two types with the same content
		 *	labelled their rows differently and reordering a model silently
		 *	relabelled every row in it. A uri is not as pretty, but it is the
		 *	thing the row actually is, and it matches how the type overview
		 *	already lists its elements (see typeDescr()).
		 *
		 *	@param		array 		$model				Type model definition (unused, kept for call-site symmetry)
		 *	@param		array 		$element			Resolved element data
		 *	@param		string		$uri					Element uri, used when there is no title
		 *
		 *	@return 	string									Label
		 */
		private static function label( array $model, array $element, string $uri ): string {

			if( isset( $element['title'] ) === true && is_scalar( $element['title'] ) === true && (string) $element['title'] !== '' )
				return (string) $element['title'];

			return '/'. $uri;
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
		// filename component, so it is held to the slug syntax the form's own
		// hint promises. Only new ones: reading and deleting never re-validate,
		// so a uri written by hand stays reachable.
		private static function _validNewUri( string $uri ): bool {
			return preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $uri ) === 1;
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

		public const string MANAGE_PERM = '/_editor/text/manage';

		/**
		 *	List every editable key with its current value(s), whether it's a
		 *	global (locale-independent) or per-locale key, whether it currently
		 *	holds markup (so the editor offers the html editor for it) and a
		 *	maxlength derived from the longest current value. Blacklisted keys
		 *	(technical values, not content) are hidden entirely - unlike _admin's
		 *	own text editor, this one only ever edits existing key values,
		 *	never sees the blacklist itself.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiKeys( array &$appData, array &$request ): void {

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [
				'keys' 					=> \Nino\Text::entries( $appData, false ),
				'locales' 			=> \Nino\Locales::getAvailableLocales( $appData ),
				'selectedLocale' => Editor::sessionLocale( $appData ),
			] );
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= Editor::postData();
			$items 	= is_array( $data['items'] ?? null ) ? $data['items'] : [];

			\Nino\Http::ok( $request, [ 'results' => \Nino\Text::saveBatch( $appData, $items, false ) ] );
		}

	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Users							User editor: lets a user change their own mail/password (with
	 *											current-password confirmation), or - given the
	 *											'/_editor/users/manage' permission - anyone's, plus (manage-only,
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

		public const string MANAGE_PERM = '/_editor/users/manage';
		private const int MIN_PW_LENGTH = 8;

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
			[ 'perm' => Elements::MANAGE_PERM, 	'label' => '/_editor/nav/elements' ],
			[ 'perm' => Text::MANAGE_PERM, 			'label' => '/_editor/nav/text' ],
			[ 'perm' => Images::MANAGE_PERM, 		'label' => '/_editor/nav/images' ],
			[ 'perm' => Submissions::VIEW_PERM, 	'label' => '/_editor/nav/submissions' ],
			[ 'perm' => Newsletter::MANAGE_PERM, 'label' => '/_editor/nav/newsletter' ],
			[ 'perm' => Logs::VIEW_PERM, 					'label' => '/_editor/nav/logs' ],
			[ 'perm' => self::MANAGE_PERM, 			'label' => '/_editor/users/label/permissions-manage' ],
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

			if( Editor::guard( $appData, $request ) === false )
				return;

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$canManage = \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM );

			$users = [];
			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user ) {
				if( $canManage === false && $mail !== $current['mail'] )
					continue;
				$users[] = [ 'mail' => $mail, 'isSelf' => $mail === $current['mail'], 'perms' => $user['perms'] ?? [] ];
			}

			\Nino\Http::ok( $request, [ 'users' => $users, 'canManage' => $canManage, 'self' => $current['mail'], 'permOptions' => self::KNOWN_PERMS ] );
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

			if( Editor::guard( $appData, $request ) === false )
				return;

			$data 				= Editor::postData();
			$username 		= (string) ( $data['username'] ?? '' );
			$newUsername 	= trim( (string) ( $data['mail'] ?? '' ) );
			$pw 					= (string) ( $data['pw'] ?? '' );
			$currentPw 		= (string) ( $data['currentPassword'] ?? '' );

			[ $allowed, $isSelf ] = self::_authorize( $appData, $username );

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			if( $newUsername === '' || filter_var( $newUsername, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid mail' );
				return;
			}

			if( $pw !== '' && strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			// Changing your own account requires re-confirming your current password -
			// an admin editing someone else's account doesn't need to know their password
			if( $isSelf === true ) {
				$storedUser = \Nino\Auth::getUser( $appData, $username );
				if( $storedUser === false || password_verify( $currentPw, $storedUser['pw'] ) === false ) {
					\Nino\Http::fail( $request, 401, 'wrong current password' );
					return;
				}
			}

			$result = \Nino\Auth::updateUser( $appData, $username, $newUsername, $pw );

			if( $result === false ) {
				\Nino\Http::fail( $request, 400, 'mail already in use' );
				return;
			}

			\Nino\Http::ok( $request, [ 'mail' => $result['mail'] ] );
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

			if( Editor::guard( $appData, $request ) === false )
				return;

			$data 		= Editor::postData();
			$username = (string) ( $data['username'] ?? '' );

			[ $allowed, $isSelf ] = self::_authorize( $appData, $username );

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			$ok = \Nino\Auth::logoutAllSessions( $appData, $username );

			\Nino\Http::ok( $request, [ 'ok' => $ok, 'loggedOutSelf' => $isSelf ] );
		}

		/**
		 *	Set a user's permissions - manager-only, unlike apiSave()/
		 *	apiLogoutAll() this has no self-service path at all (deliberately:
		 *	editing your own permissions is exactly the kind of accidental
		 *	self-lockout this doesn't try to guard against - _admin's Users
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= Editor::postData();
			$username = (string) ( $data['username'] ?? '' );
			$perms 		= is_array( $data['perms'] ?? null ) ? $data['perms'] : [];

			if( \Nino\Auth::getUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user' );
				return;
			}

			$allowed 	= array_merge( array_column( self::KNOWN_PERMS, 'perm' ), [ '/*' ] );
			$perms 		= array_values( array_intersect( $perms, $allowed ) );

			$appData['/nino/auth/user'][$username]['perms'] = $perms;
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			\Nino\Http::ok( $request, [ 'perms' => $perms ] );
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

		public const string MANAGE_PERM = '/_editor/images/manage';

		/**
		 *	List every developer-fixed image slot
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			\Nino\Http::ok( $request, [ 'slots' => $slots ] );
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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$uri 	= (string) ( Editor::postData()['uri'] ?? '' );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
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

			$oldFilename = $slot['filename'] ?? null;

			$filename = \Nino\Images::process( $appData, $bytes, (int) ( $slot['width'] ?? 0 ), (int) ( $slot['height'] ?? 0 ), ltrim( $uri, '/' ) );

			if( $filename === false ) {
				\Nino\Http::fail( $request, 400, 'invalid or oversized image' );
				return;
			}

			\Nino\Images::setSlotFilename( $appData, $uri, $filename );

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			\Nino\Http::ok( $request, [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ] );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Admin dashboard backend
	 *	Backup						Encrypted daily snapshot of everything the admin panel can
	 *											write to at runtime - triggered once per authenticated
	 *											admin request per day (see Editor::guard()), not a cron
	 *											job, so it needs no server-level scheduling at all. Lives
	 *											here rather than in the kernel (_nino/Nino.php) since
	 *											it's an _editor-specific operational concern, not something
	 *											every Nino deployment needs - a site with no _editor has
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
	 *											Restore lives in _admin, not here - see _admin/Admin.php. This
	 *											class only ever creates, never reads/decrypts, backups.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Backup {

		private const int RETENTION_DAYS = 14;
		private const string LOCK_PATH = '/_editor/backup-daily';

		// Under the private directory, not in this tool's own folder: archives
		// are project data, and a tool folder holding them cannot be replaced
		// independently on an update.
		private const string BACKUPS_DIR = \Nino\Filesystem::CONTENT_DIR. '/.backups';

		// The archive key's own copy, kept outside config.php on purpose: a
		// restore rewrites config.php, so the key that decrypts the archive
		// being restored cannot only live in it. Next to the /_admin
		// credential rather than next to the archives - it is a credential,
		// they are data
		public const string KEY_PATH = \Nino\Filesystem::CONTENT_DIR. '/.auth/backup-key.php';

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	The directory that holds backup archives.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Absolute paths, existing or not
		 */
		public static function dirs( array &$appData ): array {

			return [ \Nino\Filesystem::getContentPath( $appData ). substr( self::BACKUPS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) ) ];
		}

		/**
		 *	Where the archive key's own copy lives.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@return 	string									Absolute path
		 */
		public static function keyPath( array &$appData ): string {

			return \Nino\Filesystem::getContentPath( $appData ). substr( self::KEY_PATH, strlen( \Nino\Filesystem::CONTENT_DIR ) );
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

			$dates = [];

			foreach( self::dirs( $appData ) as $dir )
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
		 *	Called from Editor::guard() - so once per authenticated request,
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

			$locked = false;

			try {

				if( ( $appData['/nino/editor/backups'] ?? true ) === false )
					return;

				// The directory name does not exist until _bootstrap(), so it
				// cannot itself be the lock key. A stable, virtual path serializes
				// first-use key generation and the once-a-day existence check alike.
				$locked = \Nino\Filesystem::lockFile( $appData, self::LOCK_PATH );
				if( $locked === false )
					throw new \RuntimeException( 'daily backup could not be locked' );

				self::_bootstrap( $appData );

				\Nino\Filesystem::forceDir( $appData, self::BACKUPS_DIR );

				$dir 		= self::dirs( $appData )[0];
				$path 	= $dir. '/'. date( 'Y-m-d' ). '.php';

				if( is_file( $path ) === true )
					return;

				self::_create( $appData, $dir, $path );
				foreach( self::dirs( $appData ) as $backupDir )
					self::_prune( $backupDir );

				$user = \Nino\Auth::getCurrentUser( $appData );
				if( $user !== false )
					Logs::record( $appData, $user['mail'], 'Backup created' );

			} catch( \Throwable $e ) {
				trigger_error( 'Backup failed: '. $e->getMessage() );
			} finally {
				if( $locked === true )
					\Nino\Filesystem::unlockFile( $appData, self::LOCK_PATH );
			}
		}

		/**
		 *	Generate the random backup directory name and encryption key on
		 *	first use - a project that never opens _editor never gets these
		 *	config.php keys at all.
		 *
		 *	The key also gets an independent copy written into _admin/ (behind
		 *	the same .php exit-stub as a backup itself, since it's just as
		 *	sensitive) - _admin/Admin.php's Restore module reads *that* copy,
		 *	deliberately not this one, so restoring never depends on
		 *	config.php's data being intact. This copy is reconciled on every
		 *	call independent of whether dir/key already existed, since an
		 *	install that had Backup running before Restore/_admin's key file
		 *	existed would otherwise never get one (the dir/key generation
		 *	itself only ever runs once). Skipped if _admin/ isn't present
		 *	(already removed after initial setup, or never deployed) - fine,
		 *	restoring isn't possible without it either way.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _bootstrap( array &$appData ): void {

			$readConfig = false;
			$changed 	= false;
			// Generated before mutate() takes config.php's lock: random_bytes()
			// can throw, and Filesystem::mutate() deliberately expects a callback
			// that returns normally in order to release its lock.
			//
			// Only the key. The directory used to be generated alongside it,
			// as an unguessable name; the archives live under the content
			// directory now and need none (see dirs())
			$candidateKey = base64_encode( random_bytes( 32 ) );

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', function( mixed $content ) use ( &$appData, &$readConfig, &$changed, $candidateKey ): mixed {

				$readConfig = true;
				if( is_array( $content ) === false )
					return null;

				// Re-read under config.php's own lock. A second request may have
				// booted before the first generated this value; trusting its stale
				// in-memory appData here would generate a different key and overwrite
				// the first backup's only decryption key.
				if( is_string( $content['/nino/backup/key'] ?? null ) === false ) {
					$content['/nino/backup/key'] = $candidateKey;
					$changed = true;
				}

				$appData['/nino/backup/key'] = $content['/nino/backup/key'];

				return $changed === true ? $content : null;
			}, false );

			if( $readConfig === false )
				throw new \RuntimeException( 'config.php could not be locked' );
			if( isset( $appData['/nino/backup/key'] ) === false )
				throw new \RuntimeException( 'config.php could not be read' );
			if( $changed === true && $written === false )
				throw new \RuntimeException( 'backup configuration could not be written' );

			// Independent of the above: an install that already had a dir/key
			// before this copy existed (or whose copy was lost/deleted since)
			// never gets one otherwise, since the block above only ran once,
			// on first-ever bootstrap
			\Nino\Filesystem::forceDir( $appData, dirname( self::KEY_PATH ) );

			$keyPath 		= self::keyPath( $appData );
			$keyContent = self::STUB_PREFIX. $appData['/nino/backup/key']. self::STUB_SUFFIX;

			if( @file_get_contents( $keyPath ) !== $keyContent )
				self::_writeRawAtomic( $keyPath, $keyContent );
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

			if( is_dir( $dir ) === false && @mkdir( $dir, 0755, true ) === false && is_dir( $dir ) === false )
				throw new \RuntimeException( 'backup directory could not be created' );

			$tmpBase = tempnam( sys_get_temp_dir(), 'ninobackup' );
			if( $tmpBase === false )
				throw new \RuntimeException( 'temporary backup file could not be created' );

			$tmpTar = $tmpBase. '.tar';
			@unlink( $tmpBase );

			try {
				$phar = new \PharData( $tmpTar );
				foreach( \Nino\Backup::manifest( $appData ) as $absolute => $archiveName ) {
					$bytes = @file_get_contents( $absolute );
					if( $bytes === false )
						throw new \RuntimeException( 'backup source could not be read: '. $archiveName );
					$phar->addFromString( $archiveName, $bytes );
				}
				$phar->compress( \Phar::GZ );
				unset( $phar );

				$gz = @file_get_contents( $tmpTar. '.gz' );
				if( $gz === false )
					throw new \RuntimeException( 'compressed backup could not be read' );

				$key = base64_decode( (string) $appData['/nino/backup/key'], true );
				if( is_string( $key ) === false || strlen( $key ) !== 32 )
					throw new \RuntimeException( 'backup encryption key is invalid' );

				$iv 		= random_bytes( 12 );
				$tag 		= '';
				$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
				if( $cipher === false )
					throw new \RuntimeException( 'backup encryption failed' );

				self::_writeRawAtomic( $path, self::STUB_PREFIX. base64_encode( $iv. $tag. $cipher ). self::STUB_SUFFIX );
			} finally {
				@unlink( $tmpBase );
				@unlink( $tmpTar );
				@unlink( $tmpTar. '.gz' );
			}
		}

		// Raw (non-serialized) counterpart to Filesystem's atomic writer. Backup
		// blobs and the restore-key stub are valid PHP source strings already;
		// passing either through putFileContent() would var_export them instead.
		private static function _writeRawAtomic( string $path, string $content ): void {

			$temp = $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );

			if( $handle === false )
				throw new \RuntimeException( 'temporary file could not be opened: '. basename( $path ) );

			$written = @fwrite( $handle, $content );
			$flushed = @fflush( $handle );
			@fclose( $handle );

			if( $written === false || $written !== strlen( $content ) || $flushed === false ) {
				@unlink( $temp );
				throw new \RuntimeException( 'file could not be written: '. basename( $path ) );
			}

			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				throw new \RuntimeException( 'file could not be replaced: '. basename( $path ) );
			}
		}

		/**
		 *	Delete dated backups older than RETENTION_DAYS. Only ever touches
		 *	files whose name is exactly a plain "Y-m-d.php" date - _admin's
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
	 *											the current day's file; Editor::handlePost() calls
	 *											record() once per successfully-dispatched mutating
	 *											action, Editor::init()'s login callback calls it once per
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

		public const string VIEW_PERM = '/_editor/logs/view';

		// Under the private directory, not in this tool's own folder: a tool
		// folder holding runtime state cannot be replaced independently.
		private const string LOGS_DIR = \Nino\Filesystem::CONTENT_DIR. '/.logs';

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

			if( ( $appData['/nino/editor/logs'] ?? true ) === false )
				return;

			try {

				$relPath = self::LOGS_DIR. '/'. date( 'Y-m-d' ). '.php';

				\Nino\Filesystem::forceDir( $appData, self::LOGS_DIR );

				$dir 	= \Nino\Filesystem::getContentPath( $appData ). substr( self::LOGS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) );
				$path = $dir. '/'. date( 'Y-m-d' ). '.php';

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

				foreach( self::_logDirs( $appData ) as $logDir )
					self::_prune( $logDir );

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

			if( Editor::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'lines' => array_reverse( self::_allLines( $appData ) ) ] );
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

			foreach( self::_logDirs( $appData ) as $dir ) {

				$files = glob( $dir. '/*.php' ) ?: [];

				sort( $files );

				foreach( $files as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$lines = array_merge( $lines, self::_readLines( $file ) );
			}

			return $lines;
		}

		/**
		 *	The directory that holds activity logs.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Absolute paths, existing or not
		 */
		private static function _logDirs( array &$appData ): array {

			return [ \Nino\Filesystem::getContentPath( $appData ). substr( self::LOGS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) ) ];
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
	 *												array files - not an _editor concern, see Modules\Form's
	 *												own docblock.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Submissions {

		public const string VIEW_PERM = '/_editor/submissions/view';

		/**
		 *	List every recorded submission within the retention window
		 *	(see Modules\Form::RETENTION_MONTHS), most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( Editor::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'entries' => array_reverse( self::_entries( $appData ) ) ] );
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
			$dir 			= \Nino\Filesystem::path( $appData, '/data' );
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
	 *												shape as Admin\Submissions reading Modules\Form's
	 *												output: fixed path). Project-root /data, plain array file -
	 *												not an _editor concern, see Modules\Newsletter's own
	 *												docblock. Besides apiDelete, entries also go away via the
	 *												self-service unsubscribe link (Modules\Newsletter).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Newsletter {

		public const string MANAGE_PERM = '/_editor/newsletter/manage';

		private const string PATH = '/data/newsletter.php';

		// Same literal \Nino\Modules\Newsletter uses for its own removal
		// record (see that class' REMOVED_PATH docblock, including why it's
		// a sha256 hash and not the address) - duplicated rather than
		// referenced, same reasoning as PATH just above already being its
		// own copy rather than \Nino\Modules\Newsletter::PATH
		private const string REMOVED_PATH = '/data/newsletter-removed.php';

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

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$entries = \Nino\Filesystem::getFileContent( $appData, self::PATH, [] );

			\Nino\Http::ok( $request, [ 'entries' => array_reverse( $entries ) ] );
		}

		/**
		 *	Delete one subscriber by email - the admin-side counterpart to
		 *	the visitor's own self-service unsubscribe link (see
		 *	Modules\Newsletter in _nino/Nino/Modules/Newsletter/Newsletter.php)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( Editor::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$email = (string) ( Editor::postData()['email'] ?? '' );

			if( $email === '' ) {
				\Nino\Http::fail( $request, 404, 'unknown email' );
				return;
			}

			$outcome = 'notfound';
			$readEntries = false;
			$removedHash = hash( 'sha256', mb_strtolower( trim( $email ) ) );

			$written = \Nino\Filesystem::mutate( $appData, self::PATH, function( array $entries ) use ( $email, $removedHash, &$appData, &$outcome, &$readEntries ): ?array {

				$readEntries = true;

				$filtered = array_values( array_filter( $entries, function( $entry ) use ( $email ) { return ( $entry['email'] ?? null ) !== $email; } ) );

				if( count( $filtered ) === count( $entries ) )
					return null;

				// Persist the durable removal before dropping the address. If that
				// write fails, leave the subscriber list untouched; if the following
				// list write fails, a retry is safe because the hash is idempotent.
				$removalWritten = \Nino\Filesystem::mutate( $appData, self::REMOVED_PATH, function( array $removed ) use ( $removedHash ): array {
					if( in_array( $removedHash, $removed, true ) === false )
						$removed[] = $removedHash;
					return $removed;
				} );

				if( $removalWritten === false ) {
					$outcome = 'removal-failed';
					return null;
				}

				$outcome = 'ready';
				return $filtered;
			} );

			if( $readEntries === false ) {
				\Nino\Http::fail( $request, 500, 'subscriber list could not be locked' );
				return;
			}

			if( $outcome === 'notfound' ) {
				\Nino\Http::fail( $request, 404, 'unknown email' );
				return;
			}

			if( $outcome !== 'ready' || $written === false ) {
				\Nino\Http::fail( $request, 500, 'subscriber could not be deleted' );
				return;
			}

			\Nino\Http::ok( $request );
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
		 *	admin (Editor::guard(), not guardPerm()) only covers the panel
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

			if( Editor::guard( $appData, $request ) === false )
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
