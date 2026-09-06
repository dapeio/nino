<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Admin							The workbench: setup wizard, developer tooling and editorial
 *												work behind one login. This file is the base and nothing
 *												else - the shell (Admin), the panel registry (Panels) and
 *												the recovery secret (Recovery). Every screen is a module
 *												under _admin/Nino/Modules/, or one a runtime module brings
 *												along (see Panels).
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Admin {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							The workbench's shell: one login for developers and editors
	 *											alike, and what an account sees is what its permissions
	 *											allow. The shell brings no screen of its own. Everything
	 *											on screen is a panel a module brought - the workbench's
	 *											own under _admin/Nino/Modules/ (modules()), a runtime
	 *											module's wherever that module lives (adminPanels()) - and
	 *											the navigation, the panes, the bundles and the text fills
	 *											are rendered from that registry (see Panels). Take the
	 *											modules away and this is a login and an empty rail, which
	 *											is what it is meant to be. The only class _admin/index.php
	 *											calls into: routes, assets and the GET/POST /_admin handling
	 *											are registered here, the panels only fill the response.
	 *
	 *											Until a project exists this same route serves the setup
	 *											wizard instead (see init() and _admin/install/Install.php),
	 *											and _admin/recovery.php is the way back in when the
	 *											accounts themselves are what is broken (see Recovery).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		// Where the workbench is, and the project root it sits in - the two
		// anchors a module reaches for when it needs a tool path (the setup
		// library, the framework stylesheet) rather than a project file
		public const string DIR = __DIR__;
		public const string ROOT = __DIR__. '/..';

		// The appearance catalogue the wizard ships - themes, header and
		// footer units - read by the Design panel after setup as well
		public const string LIBRARY = __DIR__. '/install/library';

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
			// over a stale or hand-written config entry at the same uri - a
			// persisted 'GET://_admin' (hand-written through the Config panel,
			// or a webpage entry created before the reserved-uri check existed)
			// would otherwise shadow the whole tool, and leave no ui path back
			// to the route that did it
			$appData['/nino/http/routes']['GET://_admin'] = [
				'uri' 				=> '/_admin',
				'body'				=> '[template /_admin/templates/page-index]',
				'statusCode'	=> 200
			];
			$appData['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin' ];

			// No project yet: the same route serves the setup wizard, which
			// registers its own handlers on it and locks itself out for good
			// with its last step (see isInstalled()). The wizard is a
			// directory a delivery may drop after setup - without it, an
			// uninstalled project gets a notice rather than a login form that
			// has no accounts behind it
			if( self::isInstalled( $appData ) === false ) {

				if( is_file( __DIR__. '/install/Install.php' ) === true ) {
					require_once __DIR__. '/install/Install.php';
					\Nino\Install\Install::init( $appData );
					return;
				}

				$appData['/nino/http/routes']['GET://_admin']['body'] = '[template /_admin/templates/page-locked]';
				return;
			}

			// Both bundles are built from the panel registry, so every panel
			// ships its own script and stylesheet (see panels()) and nothing
			// here has to know which ones exist. What is listed here is the
			// base a panel is written against and nothing a screen owns: the
			// framework's browser half, the design system's behaviour, and
			// the shell script that seeds the namespace every panel file
			// attaches to. html-editor.js is not among them - it is a
			// primitive the panels that want it name in their own assets()
			$styles 	= [ '/_admin/assets/style.css' ];
			$scripts	= [
				'/_nino/Nino.js',
				'/_admin/assets/Nino.admin.js',
				'/_admin/assets/script.js',
			];

			foreach( self::allPanels( $appData ) as $panel )
				foreach( $panel['assets'] as $asset )
					if( str_ends_with( $asset, '.css' ) === true )
						$styles[] = $asset;
					else
						$scripts[] = $asset;

			$appData['/nino/html/assets']['/_admin/.cache/style.css'] = array_values( array_unique( $styles ) );
			$appData['/nino/html/assets']['/_admin/.cache/script.js'] = array_values( array_unique( $scripts ) );
			$appData['/nino/html/assets']['/_admin/.cache/login.js'] = [
				'/_nino/Nino.js',
				'/_admin/assets/Nino.admin.js',
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
			// locale (session locale) before loading the tool's own text fills -
			// reuses the exact mechanism Elements/Text's locale switch already
			// persists (POST admin/locale), so switching it also switches
			// which language the admin UI chrome itself renders in
			\Nino\Locales::setCurrentLocale( $appData, self::sessionLocale( $appData ) );

			$currentLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Html::addFills( $appData, self::textFills( $appData, '/_admin/text', $currentLocale ), $currentLocale );

			// A panel's own words join the shell's - the same file shape
			// _admin/text/<locale>.php has, read from wherever the panel
			// says its text lives (see panels()). The shell's own file holds
			// what the shell renders and what every panel may reach for
			// (/_admin/common/*); everything a screen says travels with it
			foreach( self::allPanels( $appData ) as $panel )
				if( $panel['text'] !== '' )
					\Nino\Html::addFills( $appData, self::textFills( $appData, $panel['text'], $currentLocale ), $currentLocale );

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

			if( \Nino\Auth::getCurrentUser( $appData ) === false ) {
				$request['/nino/http/response']['body'] = '[template /_admin/templates/page-login]';
				return;
			}

			// Only the panels this account may use are rendered at all - the
			// permission checks in every action stay authoritative, this keeps
			// the navigation honest and the page free of predictable 403s
			\Nino\Html::addFills( $appData, [
				'[[/_admin/nav]]'		=> self::navHtml( $appData ),
				'[[/_admin/panes]]'	=> self::panesHtml( $appData ),
			], '*' );
		}

		/**
		 *	The navigation for the current account - see Panels::navHtml()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		public static function navHtml( array &$appData ): string {

			return Panels::navHtml( self::visiblePanels( $appData ) );
		}

		/**
		 *	The content panes for the current account - see Panels::panesHtml()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		public static function panesHtml( array &$appData ): string {

			return Panels::panesHtml( self::visiblePanels( $appData ) );
		}

		/**
		 *	The panels the current account holds the permission for - a
		 *	panel without perm() is everyone's
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Same shape as panels(), filtered
		 */
		public static function visiblePanels( array &$appData ): array {

			$allowed = static fn( array $panel ): bool => $panel['perm'] === '' || \Nino\Auth::checkPermission( $appData, $panel['perm'] ) === true;
			$visible = [];

			foreach( self::panels( $appData ) as $uri => $panel ) {

				// A tab is its own screen with its own permission: the pane
				// shows the tabs the account holds, and stays in the rail as
				// long as one of them - or the panel's own screen - is
				$panel['own']		= $allowed( $panel );
				$panel['tabs']	= array_filter( $panel['tabs'], $allowed );

				if( $panel['own'] === true || $panel['tabs'] !== [] )
					$visible[$uri] = $panel;
			}

			return $visible;
		}

		/**
		 *	Every panel and every tab of the registry as one flat list, in
		 *	navigation order with a panel's tabs right after it - for the
		 *	callers that care about screens rather than the rail: the action
		 *	map, the bundles, the fills, the dashboard tiles, the permission
		 *	lists
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ uri => entry ], see panels()
		 */
		public static function allPanels( array &$appData ): array {

			$all = [];

			foreach( self::panels( $appData ) as $uri => $panel ) {
				$all[$uri] = $panel;
				foreach( $panel['tabs'] as $tabUri => $tab )
					$all[$tabUri] = $tab;
			}

			return $all;
		}

		/**
		 *	Whether this project has been set up: the wizard's last step
		 *	writes the recovery secret and a marker, and either alone keeps
		 *	the wizard shut - losing one file must not hand it back to
		 *	whoever asks for it on a live site
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function isInstalled( array &$appData ): bool {

			if( ( $appData['/nino/install/completed'] ?? false ) === true )
				return true;

			return Recovery::hash( $appData ) !== null;
		}

		// Where the workbench's own modules live - one directory per screen,
		// the same shape a runtime module has under _nino/ or app/ (see
		// modules(), and the autoloader at the bottom of _nino/Nino.php)
		public const string MODULES = __DIR__. '/Nino/Modules';

		/**
		 *	The workbench's own panels: one per module directory under
		 *	_admin/Nino/Modules/, read off the disk rather than a list kept
		 *	here. The shell is the base and brings no screen of its own -
		 *	every one of them is a module that can be added, updated or
		 *	deleted on its own, and a workbench without the directory is a
		 *	login and an empty rail rather than a broken one.
		 *
		 *	A module's screen is <Name>/Admin/Admin.php, the class
		 *	\Nino\Modules\<Name>\Admin - the same file the autoloader
		 *	resolves, so nothing here has to require anything. Its tabs are
		 *	its own business (see tabs() in the panel contract), and the
		 *	order of this list is irrelevant: each panel's nav() says where
		 *	it sits.
		 *
		 *	@return 	array										Class names, alphabetically by module
		 */
		public static function modules(): array {

			$classes = [];

			foreach( glob( self::MODULES. '/*/Admin/Admin.php' ) ?: [] as $file ) {

				$name = basename( dirname( dirname( $file ) ) );

				// Written the way ::class writes it - no leading separator -
				// so a core panel and a module-contributed one compare equal
				if( preg_match( '/^[A-Z][A-Za-z0-9]*$/', $name ) === 1 )
					$classes[] = 'Nino\\Modules\\'. $name. '\\Admin';
			}

			return $classes;
		}

		/**
		 *	Every panel on offer, in navigation order - the one place the
		 *	shell reads. The workbench's own modules come first (see
		 *	modules()), then every panel a runtime module contributes by
		 *	answering adminPanels() with a class name, so a runtime module
		 *	can add a screen but never take one over. A panel is a class
		 *	with actions() and nav(), and optionally perm(), panes(),
		 *	assets(), text(), summary() and log() (see Panels, and
		 *	docs/development.md).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ uri => { class, uri, label, weight, group, perm, panes, assets, text } ]
		 */
		public static function panels( array &$appData ): array {

			return Panels::collect( $appData, self::modules(), 'adminPanels' );
		}

		/**
		 *	Every action every panel dispatches, core first so a module can
		 *	never take over a core action by reusing its name
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ 'panel/action' => [ class, method ] ]
		 */
		public static function actions( array &$appData ): array {

			$actions = [ 'admin/locale' => [ Admin::class, 'apiSetLocale' ] ];

			foreach( self::allPanels( $appData ) as $panel )
				$actions += $panel['class']::actions();

			return $actions;
		}

		/**
		 *	Record a successful login to the activity log, the first time
		 *	the now-authenticated session touches the workbench.
		 *
		 *	Not hooked directly into Auth's login (the obvious-seeming
		 *	approach) because that goes through the kernel's generic
		 *	/.nino/auth/login uri, which - unlike GET/POST /_admin - isn't
		 *	guaranteed to be routed through _admin/index.php at all: a
		 *	typical webserver config maps /_admin/* to _admin/index.php via
		 *	its own directory/file, but /.nino/auth/login matches no real
		 *	file under _admin/ and falls through to the site's root
		 *	index.php instead, which never calls Admin::init() and so never
		 *	registers any workbench callback. Piggybacking on guard()/
		 *	handleGet() instead sidesteps that entirely, since the workbench's own
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

			self::record( $appData, $user['mail'], 'Login' );
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

			$actions = self::actions( $appData );
			$action	 = $_POST['action'] ?? '';

			if( isset( $actions[$action] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown action' );
				return;
			}

			// call_user_func() can't pass $appData/$request by reference, so dispatch via a
			// dynamic static call instead - same pattern as Callbacks::doCallbacks()
			[ $class, $method ] = $actions[$action];
			$class::{$method}( $appData, $request );

			// Deliberately not a listener on the event below: an audit line that
			// can be lost by not registering a callback is not an audit line.
			// The log is the tool's own guarantee, the event is for modules
			if( $request['/nino/http/response']['statusCode'] === 200 )
				self::_logAction( $appData, $class, $action );

			self::_announce( $appData, $class, $action, $request );
		}

		/**
		 *	Tell whoever is listening that a panel action ran - the workbench's
		 *	one reaction point, and the counterpart to the declarations a panel
		 *	makes through \Nino\Admin\Panels.
		 *
		 *	The registry says what a module *is* in the workbench; this says what
		 *	just *happened* in it. Without it a module could only ever observe
		 *	the panel it owns itself: an audit or notification module, one that
		 *	reacts to another panel's save, or one that needs to drop a cache
		 *	after any change at all had nowhere to attach. '/nino/admin/restore'
		 *	was the same need, solved once for one case.
		 *
		 *	Notification only, and after the fact by construction: the action has
		 *	already run and written its response, so a listener can act on what
		 *	happened but cannot change or refuse it. Refusing is guardPerm()'s
		 *	job and stays there.
		 *
		 *	Fires for a failed action too, with its status - a listener watching
		 *	for repeated 403s wants exactly the ones the log leaves out. It does
		 *	not fire for an action no panel registered: nothing ran.
		 *
		 *	What changed to the *content* is not this event's business - the
		 *	kernel says that itself, per element ('/nino/elements/committed'),
		 *	per account ('/nino/auth/user/...'). This one says who did what in
		 *	the tool.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$class				The panel class that handled the action
		 *	@param		string		$action				The dispatched action name (eg. "elements/save")
		 *	@param		array 		$request			The request, already answered
		 *
		 *	@return 	void
		 */
		private static function _announce( array &$appData, string $class, string $action, array $request ): void {

			$user = \Nino\Auth::getCurrentUser( $appData );

			// A plain description rather than the request itself: handed the
			// response by reference, a listener could rewrite an answer the
			// panel has already given, which is a veto through the back door
			$event = [
				'action' 	=> $action,
				'panel' 	=> $class,
				'status' 	=> (int) ( $request['/nino/http/response']['statusCode'] ?? 0 ),
				'user' 		=> ( $user !== false ) ? (string) $user['mail'] : '',
				'data' 		=> self::postData(),
			];

			\Nino\Callbacks::doCallbacks( $appData, '/nino/admin/action', $event );
		}

		/**
		 *	Describe one mutating action for the activity log, and record
		 *	it - a no-op for actions that only read data (eg. elements/list,
		 *	users/list), since those aren't meaningful audit events
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$class				The panel class that handled the action
		 *	@param		string		$action				The dispatched action name (eg. "elements/save")
		 *
		 *	@return 	void
		 */
		private static function _logAction( array &$appData, string $class, string $action ): void {

			// The panel that handled the action phrases the line itself - its
			// log() answers '' for a read, and a panel without one logs nothing
			if( method_exists( $class, 'log' ) === false )
				return;

			$message = (string) $class::log( $action, self::postData() );

			if( $message === '' )
				return;

			$user = \Nino\Auth::getCurrentUser( $appData );

			if( $user !== false )
				self::record( $appData, $user['mail'], $message );
		}

		/**
		 *	Hand one line to the activity log, if this installation has one.
		 *
		 *	The log is a module like every other screen
		 *	(_admin/Nino/Modules/Logs) and a delivery may drop it, so the
		 *	shell asks rather than assumes: without it the workbench keeps
		 *	working and records nothing. class_exists() is what asks - it
		 *	runs the autoloader, so a present module answers true without
		 *	anything here naming a file.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$actor				Who did it - an account's mail address
		 *	@param		string		$message			The line, already phrased
		 *
		 *	@return 	void
		 */
		public static function record( array &$appData, string $actor, string $message ): void {

			if( class_exists( '\\Nino\\Modules\\Logs\\Admin' ) === true )
				\Nino\Modules\Logs\Admin::record( $appData, $actor, $message );
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
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return false;
			}

			// Same as the activity log: the daily backup belongs to the
			// Backups module, and a workbench delivered without it simply
			// takes none (see record())
			if( class_exists( '\\Nino\\Modules\\Backups' ) === true )
				\Nino\Modules\Backups::maybeRun( $appData );

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
		 *	Scoped permissions - the finer half of the permission model.
		 *
		 *	A panel's own permission ('/_admin/elements/manage') is a door: it
		 *	says the account may work in that panel at all, and guardPerm()
		 *	above is where it is checked. A scoped permission describes what
		 *	the account may do once inside - '/_admin/elements/services/insert',
		 *	'/_admin/elements/services/update/title',
		 *	'/_admin/text/update/page-home/atf/title'. Both kinds are ordinary
		 *	strings checked with the same \Nino\Auth::checkPermission(), so
		 *	'/_admin/elements/services/*' covers every action on that type and
		 *	'/_admin/text/update/page-home/*' every key of that group, without
		 *	this file knowing anything about types, fields or keys.
		 *
		 *	Enforcement is opt-in per account, and deliberately so. An account
		 *	that holds no scoped permission for a panel keeps exactly what the
		 *	panel permission has always meant - all of it. The other way round,
		 *	every role in every existing installation would have lost write
		 *	access on the day this arrived, silently. Giving a role its first
		 *	scoped permission for a panel is what says "describe this one in
		 *	detail"; from then on that panel allows what the role names and
		 *	nothing else.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$prefix				The panel's scope, with its slash (eg. '/_admin/elements/')
		 *	@param		string		$perm					The specific permission the action needs
		 *
		 *	@return 	bool										Whether this account may do it
		 */
		public static function scoped( array &$appData, string $prefix, string $perm ): bool {

			if( self::isScoped( $appData, $prefix ) === false )
				return true;

			return \Nino\Auth::checkPermission( $appData, $perm );
		}

		/**
		 *	Whether this account is described in detail for a panel - ie.
		 *	holds at least one permission naming a single action inside it,
		 *	rather than only the door or a blanket over the whole panel.
		 *
		 *	'/_admin/elements/manage' is the door and '/_admin/elements/*' the
		 *	blanket: neither names an action, so neither switches the panel
		 *	into described-in-detail mode. '/_admin/elements/services/insert'
		 *	does, and so does '/_admin/elements/services/*' - a role limited
		 *	to one type is still a role that was described.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$prefix				The panel's scope, with its slash
		 *
		 *	@return 	bool
		 */
		public static function isScoped( array &$appData, string $prefix ): bool {

			$user = \Nino\Auth::getCurrentUser( $appData );

			if( $user === false )
				return false;

			foreach( \Nino\Auth::permissions( $appData, $user ) as $held ) {

				if( str_starts_with( $held, $prefix ) === false )
					continue;

				$rest = substr( $held, strlen( $prefix ) );

				if( $rest === '*' || str_contains( $rest, '/' ) === false )
					continue;

				return true;
			}

			return false;
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

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {

				// The name of a language is the project's to give, and the fill
				// that carries it is written by the Localepicker module's install
				// step - an optional unit an operator may uncheck, and one that
				// cannot know about a locale the Language panel adds later either.
				// Without the fallback every such option renders empty, which is
				// a language switcher with nothing readable in it
				$label = \Nino\Html::renderTextfill( $appData, '/nino/locales/locale/'. $locale );

				$options .= '<option value="?locale='. $locale. '"'. ( $locale === $currentLocale ? ' selected' : '' ). '>'.
					( $label !== '' ? $label : $locale ). '</option>';
			}

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

		/**
		 *	The words of one text directory in the interface language - or in
		 *	English when there are none in that language. The interface
		 *	language follows the site's (see sessionLocale()), and the
		 *	workbench speaks two; a site in a third would otherwise render
		 *	every label of every panel as its own fill key
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					A text directory, virtual path (eg. '/_admin/text')
		 *	@param		string		$locale				The interface language
		 *
		 *	@return 	array										The fills of that file, or of en_US.php
		 */
		public static function textFills( array &$appData, string $dir, string $locale ): array {

			$fills = \Nino\Filesystem::getFileContent( $appData, $dir. '/'. $locale. '.php', false );

			if( is_array( $fills ) === true )
				return $fills;

			return (array) \Nino\Filesystem::getFileContent( $appData, $dir. '/en_US.php', [] );
		}

		/**
		 *	A whole number within a field's 'min'/'max', or null - shared by
		 *	the panels that write typed values into config.php (Config, the
		 *	Login protection tab), so a panel a delivery drops takes no
		 *	validation with it that another one relies on
		 *
		 *	@param		mixed			$value				As posted
		 *	@param		array			$field				Its FIELDS entry, for 'min'/'max'
		 *
		 *	@return 	int|null								Null if not an int or out of range
		 */
		public static function cleanInt( mixed $value, array $field ): ?int {

			// A float, a bool or "5 " are all rejected rather than cast: this
			// writes into config.php, where the value has to be an int and
			// nothing else
			if( is_int( $value ) === false && ( is_string( $value ) === false || preg_match( '/^-?\d+$/', $value ) !== 1 ) )
				return null;

			$int = (int) $value;

			if( $int < ( $field['min'] ?? PHP_INT_MIN ) || $int > ( $field['max'] ?? PHP_INT_MAX ) )
				return null;

			return $int;
		}

		/**
		 *	The message a rejected field answers with - specific enough to fix
		 *	the value from, which "invalid value" is not
		 *
		 *	@param		string		$key
		 *	@param		array			$field
		 *
		 *	@return 	string
		 */
		public static function typeError( string $key, array $field ): string {

			return match( $field['type'] ) {
				'int' 		=> $key. ': expected a whole number between '. ( $field['min'] ?? PHP_INT_MIN ). ' and '. ( $field['max'] ?? PHP_INT_MAX ),
				'bool' 		=> $key. ': expected true or false',
				'lines' 	=> $key. ': expected one entry per line',
				default 	=> $key. ': invalid value',
			};
		}

	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Panels							The panel registry the shell is rendered from: reading a
	 *											panel class, ordering the panels, rendering the navigation
	 *											and the content panes from that list rather than from a
	 *											hand-written template. The PHP half of assets/Nino.admin.js
	 *											and style.css.
	 *
	 *	A panel							is a class with two required and six optional static
	 *											methods - no interface, no base class, same as a runtime
	 *											module is a class with init():
	 *
	 *											  actions(): array          'panel/action' => [ class, method ]
	 *											  nav(): array              [ uri, label, weight = 50, group = 'content' ]
	 *											  perm(): string            permission gating nav and api, '' = none
	 *											  panes(): array            mount ids inside the pane, default [ '<uri>-list' ]
	 *											  template(): string        a .tpl (project-relative, without the
	 *											                            extension) included into the pane instead
	 *											                            of mount points - for a screen with a lot
	 *											                            of static markup
	 *											  layout(): string          'page' (default: a column of content) or
	 *											                            'workspace' (the whole pane, edge to
	 *											                            edge, the rail folded away)
	 *											  tabs(): array             further panel classes shown as tabs of this
	 *											                            panel's pane, each with its own perm(), script
	 *											                            and hash prefix; tab(): string names the tab
	 *											                            when the nav label will not do
	 *											  icon(): string            an inline <svg> for the folded rail;
	 *											                            without one the label's initial stands in
	 *											  assets(): array           its own .js/.css files, project-relative
	 *											  text(): string            directory of its <locale>.php fills
	 *											  summary( &$appData ): ?array   a dashboard tile { value, label }
	 *											  log( $action, $data ): string  the activity-log line, '' for none
	 *
	 *											The navigation has three groups, in this order: 'content'
	 *											(what editors work in), 'structure' (the shape of the
	 *											project) and 'system'. Weight orders within a group. A
	 *											label starting with '/' is a fill key, resolved by the
	 *											normal fill pass; anything else is literal text.
	 *
	 *	Where a panel			lives is what kind of panel it is. The workbench's own
	 *											screens are modules under _admin/Nino/Modules/<Name>/, one
	 *											directory each: Admin/Admin.php is the panel, <Tab>/<Tab>.php
	 *											a tab of it, assets/ its scripts and stylesheet, text/ its
	 *											words - the same shape _nino/Nino/Modules and app/Nino/Modules
	 *											have, and the same namespace, so \Nino\Modules\Elements is
	 *											the runtime module and \Nino\Modules\Elements\Admin the
	 *											screen for it. A runtime module contributes a panel of its own
	 *											by answering adminPanels() with the class name - see
	 *											\Nino\Modules::collect(). Paths a panel answers are
	 *											project-relative; relative() turns the directory its class
	 *											file sits in into one.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Panels {

		// The navigation's groups, in the order they are rendered
		public const array GROUPS = [ 'content', 'structure', 'system' ];

		/**
		 *	Build one tool's registry: its own panels first, then every
		 *	panel the active modules contribute, the whole list stable-sorted
		 *	by nav() weight - which is what lets a module panel sit between
		 *	two core ones.
		 *
		 *	A second panel claiming an already taken uri is dropped with a
		 *	warning rather than allowed to shadow the first: a module must
		 *	not be able to replace a core screen by picking its name. The
		 *	same goes for a class missing actions() or nav() - the tool keeps
		 *	working without it, and the warning names it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$core					The tool's own panel classes
		 *	@param		string		$method				The module question, 'adminPanels'
		 *
		 *	@return 	array										[ uri => { class, uri, label, tab, weight, group, perm, panes, template, layout, icon, assets, text, tabs, parent, own } ]
		 */
		public static function collect( array &$appData, array $core, string $method ): array {

			$panels = [];
			$taken	= [];

			foreach( array_merge( $core, \Nino\Modules::collect( $appData, $method ) ) as $class ) {

				$entry = self::_entry( $class, $taken );

				if( $entry === null )
					continue;

				$taken[$entry['uri']] = $class;

				// A panel may bring further panels along as tabs of its own
				// pane - each a complete panel with its own permission,
				// script and hash prefix, placed here rather than in the rail.
				// A uri is taken across panels and tabs alike, and a tab has no
				// tabs of its own
				if( method_exists( $class, 'tabs' ) === true )
					foreach( (array) $class::tabs() as $tabClass ) {

						$tab = self::_entry( $tabClass, $taken );

						if( $tab === null )
							continue;

						$tab['parent'] = $entry['uri'];
						$taken[$tab['uri']] = $tabClass;
						$entry['tabs'][$tab['uri']] = $tab;
					}

				uasort( $entry['tabs'], static fn( array $a, array $b ): int => $a['weight'] <=> $b['weight'] );

				$panels[$entry['uri']] = $entry;
			}

			// Group first, weight within the group - stable, so two panels
			// of equal weight keep their registration order
			$order = array_flip( self::GROUPS );
			uasort( $panels, static fn( array $a, array $b ): int => [ $order[$a['group']], $a['weight'] ] <=> [ $order[$b['group']], $b['weight'] ] );

			return $panels;
		}

		/**
		 *	One panel class read into its registry entry, or null with a
		 *	warning naming what is wrong with it - a module must never be
		 *	able to replace a core screen by picking its name, and a class
		 *	missing actions() or nav() must not take the tool down
		 *
		 *	@param		mixed 		$class				What a module answered - a class name, hopefully
		 *	@param		array 		$taken				uri => class, everything registered so far
		 *
		 *	@return 	array|null
		 */
		private static function _entry( mixed $class, array $taken ): ?array {

			if( is_string( $class ) === false || method_exists( $class, 'actions' ) === false || method_exists( $class, 'nav' ) === false ) {
				trigger_error( 'Panel '. ( is_string( $class ) === true ? $class : gettype( $class ) ). ' needs actions() and nav()', E_USER_WARNING );
				return null;
			}

			$nav = (array) $class::nav();
			$uri = (string) ( $nav[0] ?? '' );

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $uri ) !== 1 ) {
				trigger_error( 'Panel '. $class. ' has no usable nav uri', E_USER_WARNING );
				return null;
			}

			if( isset( $taken[$uri] ) === true ) {
				trigger_error( 'Panel '. $class. ' wants uri \''. $uri. '\', already taken by '. $taken[$uri], E_USER_WARNING );
				return null;
			}

			// An asset path reaches a <script src> or a bundle list:
			// project-relative, no traversal, only the two kinds of file
			// a panel can bring. A file that is not there is kept in the
			// list (the bundler skips it) but named: a panel whose script
			// silently never loads is a pane that stays empty, and the
			// warning is the only thing that says why
			$assets = [];
			if( method_exists( $class, 'assets' ) === true )
				foreach( (array) $class::assets() as $asset )
					if( is_string( $asset ) === true && preg_match( '#^/[A-Za-z0-9_./-]+\.(js|css)$#', $asset ) === 1 && str_contains( $asset, '..' ) === false ) {
						if( is_file( Admin::ROOT. $asset ) === false )
							trigger_error( 'Panel '. $class. ' names an asset that does not exist: '. $asset, E_USER_WARNING );
						$assets[] = $asset;
					}

			$panes = [ $uri. '-list' ];
			if( method_exists( $class, 'panes' ) === true )
				$panes = array_values( array_filter( (array) $class::panes(), static fn( mixed $pane ): bool => is_string( $pane ) === true && preg_match( '/^[a-z][a-z0-9-]*$/', $pane ) === 1 ) );

			// A template reaches the [template] shortcode: project-relative,
			// no traversal, and only ever a path - the shortcode adds .tpl
			$template = method_exists( $class, 'template' ) === true ? (string) $class::template() : '';
			if( $template !== '' && ( preg_match( '#^/[A-Za-z0-9_./-]+$#', $template ) !== 1 || str_contains( $template, '..' ) === true ) ) {
				trigger_error( 'Panel '. $class. ' names an unusable template path', E_USER_WARNING );
				$template = '';
			}

			$layout = method_exists( $class, 'layout' ) === true ? (string) $class::layout() : 'page';
			if( in_array( $layout, [ 'page', 'workspace' ], true ) === false )
				$layout = 'page';

			// An icon is markup a panel class ships - trusted code, but
			// held to one shape: an inline svg and nothing that runs
			$icon = method_exists( $class, 'icon' ) === true ? trim( (string) $class::icon() ) : '';
			if( $icon !== '' && ( str_starts_with( $icon, '<svg' ) === false || stripos( $icon, '<script' ) !== false || stripos( $icon, 'on' ) !== false && preg_match( '/\son[a-z]+\s*=/i', $icon ) === 1 ) )
				$icon = '';

			$group = (string) ( $nav[3] ?? 'content' );
			if( in_array( $group, self::GROUPS, true ) === false ) {
				trigger_error( 'Panel '. $class. ' names an unknown nav group \''. $group. '\'', E_USER_WARNING );
				$group = 'content';
			}

			$label = (string) ( $nav[1] ?? $uri );

			return [
				'class'		=> $class,
				'uri'			=> $uri,
				'label'		=> $label,
				// What the tab strip calls it when the panel is one of
				// several in a pane - the nav label unless tab() says otherwise
				'tab'			=> method_exists( $class, 'tab' ) === true ? (string) $class::tab() : $label,
				'weight'	=> (int) ( $nav[2] ?? 50 ),
				'group'		=> $group,
				'perm'		=> method_exists( $class, 'perm' ) === true ? (string) $class::perm() : '',
				'panes'		=> $panes,
				'template'=> $template,
				'layout'	=> $layout,
				'icon'		=> $icon,
				'assets'	=> $assets,
				'text'		=> method_exists( $class, 'text' ) === true ? (string) $class::text() : '',
				'tabs'		=> [],
				'parent'	=> '',
				// Whether the panel's own screen is on offer - false once
				// visiblePanels() found the account lacks its permission but
				// holds one of its tabs'
				'own'			=> true,
			];
		}

		/**
		 *	The navigation, one link per panel under its group's heading -
		 *	rendered here rather than written into the shell template, which
		 *	is what lets a module's panel appear without touching the shell.
		 *	The link ids follow the convention the tool always had
		 *	(admin-nav-<uri>); data-panel is what the shell script reads. A
		 *	heading is only rendered when more than one group is on screen:
		 *	an account that sees content alone gets a plain list
		 *
		 *	@param		array 		$panels				A registry, see collect() - already filtered to what the account may see
		 *
		 *	@return 	string
		 */
		public static function navHtml( array $panels ): string {

			$groups = array_unique( array_column( $panels, 'group' ) );
			$html 	= '';
			$open 	= '';

			foreach( $panels as $panel ) {

				if( count( $groups ) > 1 && $panel['group'] !== $open ) {
					$open = $panel['group'];
					$html .= '<span class="nino-admin-nav-group" data-group="'. $open. '">[[/_admin/nav/group/'. $open. ']]</span>';
				}

				$html .= '<a href="#" id="admin-nav-'. $panel['uri']. '" data-panel="'. $panel['uri']. '" data-layout="'. $panel['layout']. '">'
					. '<span class="nino-admin-nav-icon" aria-hidden="true">'. ( $panel['icon'] !== '' ? $panel['icon'] : '<b>'. self::initial( $panel['label'] ). '</b>' ). '</span>'
					. '<span class="nino-admin-nav-label">'. self::label( $panel['label'] ). '</span></a>';
			}

			return $html;
		}

		/**
		 *	The letter that stands in for a panel without an icon on the
		 *	folded rail - a fill key's last segment, else the label's first
		 *	character
		 *
		 *	@param		string		$label				Fill key or literal text
		 *
		 *	@return 	string
		 */
		public static function initial( string $label ): string {

			$word = str_starts_with( $label, '/' ) === true ? (string) substr( $label, strrpos( $label, '/' ) + 1 ) : $label;

			return htmlspecialchars( mb_strtoupper( mb_substr( trim( $word ), 0, 1 ) ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
		}

		/**
		 *	A file's project-relative path, the form every path a panel
		 *	answers takes - so a panel says where its files are from where
		 *	its class is, and moves with its module:
		 *
		 *	  Panels::relative( dirname( __DIR__ ). '/assets/admin.js' )
		 *
		 *	@param		string		$absolute			An absolute path inside the project
		 *
		 *	@return 	string										'' when the path is not inside the project
		 */
		public static function relative( string $absolute ): string {

			$root = dirname( __DIR__ );

			if( str_starts_with( $absolute, $root. '/' ) === false )
				return '';

			return str_replace( '\\', '/', substr( $absolute, strlen( $root ) ) );
		}

		/**
		 *	The content panes, one per panel - the other half of navHtml().
		 *	A pane holds the mount points the panel's own script renders
		 *	into, or its template included whole. A panel with tabs (see
		 *	collect()) holds a strip of tab buttons and one tab pane per
		 *	tab, its own screen first; the strip is only rendered when there
		 *	is more than one tab to choose from. Every pane and tab pane
		 *	starts hidden; the shell script shows the selected ones and
		 *	reads a panel's layout off data-layout
		 *
		 *	@param		array 		$panels				A registry, see collect() - already filtered to what the account may see
		 *
		 *	@return 	string
		 */
		public static function panesHtml( array $panels ): string {

			$html = '';

			foreach( $panels as $panel ) {

				$html .= '<div id="admin-content-'. $panel['uri']. '" data-panel="'. $panel['uri']. '" data-layout="'. $panel['layout']. '" hidden>';

				if( $panel['tabs'] === [] ) {
					$html .= self::_paneContent( $panel ). '</div>';
					continue;
				}

				$tabs = ( $panel['own'] === true ? [ $panel['uri'] => $panel ] : [] ) + $panel['tabs'];

				if( count( $tabs ) > 1 ) {
					$html .= '<div class="nino-admin-tabs nino-admin-tabs--bar admin-panel-tabs" role="tablist">';
					foreach( $tabs as $tab )
						$html .= '<button type="button" role="tab" class="nino-admin-tab" data-tab="'. $tab['uri']. '" aria-selected="false">'. self::label( $tab['tab'] ). '</button>';
					$html .= '</div>';
				}

				foreach( $tabs as $tab )
					$html .= '<div id="admin-tab-'. $tab['uri']. '" data-tab="'. $tab['uri']. '" hidden>'. self::_paneContent( $tab ). '</div>';

				$html .= '</div>';
			}

			return $html;
		}

		/**
		 *	What one panel renders into: its template, or its mount points
		 *
		 *	@param		array 		$panel				A registry entry
		 *
		 *	@return 	string
		 */
		private static function _paneContent( array $panel ): string {

			if( $panel['template'] !== '' )
				return '[template '. $panel['template']. ']';

			$html = '';
			foreach( $panel['panes'] as $pane )
				$html .= '<div id="'. $pane. '"></div>';

			return $html;
		}

		/**
		 *	A nav label as it goes into the shell markup: a fill key wrapped
		 *	for the fill pass, literal text escaped for html and against the
		 *	bracket syntax that same pass would otherwise read
		 *
		 *	@param		string		$label				Fill key ('/_admin/nav/x') or literal text
		 *
		 *	@return 	string
		 */
		public static function label( string $label ): string {

			if( str_starts_with( $label, '/' ) === true )
				return '[['. $label. ']]';

			return str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], htmlspecialchars( $label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' ) );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Recovery					The way back in when the accounts are what is broken: a
	 *											second secret, set by the wizard's last step and kept under
	 *											private/.auth/ - outside config.php, so a Restore cannot roll
	 *											it back, and outside every tool folder, so an update cannot
	 *											take it along. _admin/recovery.php verifies it (rate-limited,
	 *											one shared counter) and offers exactly two things: restoring
	 *											a backup and resetting an account's password. Nothing in the
	 *											workbench itself ever asks for it.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Recovery {

		// Where the secret's hash lives, relative to the content directory
		// (see \Nino\Filesystem::getContentPath()). The wizard's last step
		// writes it; hash() reads it
		public const string PASSWORD_PATH = '/.auth/pw.php';

		// The attempt counter, next to the credential it guards. A virtual
		// path (see \Nino\Filesystem::CONTENT_DIR), so it goes through
		// mutate() and keeps its lock - two concurrent wrong attempts must
		// not both read the same "tries" and each write back the same +1
		private const string LOCKOUT_PATH = \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json';

		// The stub that file is wrapped in - a php file that 403s and exits
		// before it ever returns the hash, so it stays useless even where a
		// webserver happily serves it. Same convention (and same constants)
		// Backup uses for its key and its archives
		public const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		public const string STUB_SUFFIX = "';\n";

		private const int MAX_TRIES = 5;
		private const int COOLDOWN = 3600;

		// The session flag recovery.php sets once the secret was verified
		public const string SESSION_KEY = './nino/admin/recovery';

		private const int MIN_PW_LENGTH = 8;

		/**
		 *	Register recovery.php's own route pair - the file is its own
		 *	entry point, so nothing the workbench registers applies here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$appData['/nino/http/routes']['GET://_admin/recovery.php'] = [
				'uri' 				=> '/_admin/recovery.php',
				'body'				=> '[template /_admin/templates/page-recovery]',
				'statusCode'	=> 200
			];
			$appData['/nino/http/routes']['POST://_admin/recovery.php'] = [ 'uri' => '/_admin/recovery.php' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_admin/recovery.php', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_admin/recovery.php', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Tell the page whether the gate is open - the one thing it renders
		 *	differently. The tools' markup is in the page either way; what
		 *	they do is guarded server-side by handlePost()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			\Nino\Html::addFills( $appData, [ '[[/_admin/recovery/open]]' => self::isOpen( $appData ) === true ? '1' : '0' ], '*' );
		}

		/**
		 *	Dispatch recovery.php's four actions: login opens the gate, the
		 *	other three need it open. Same one-route/$_POST['action'] shape as
		 *	Admin::handlePost(), deliberately without the panel registry -
		 *	this runs when config.php may be the very thing that is broken
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handlePost( array &$appData, array &$request ): void {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return;

			$action = (string) ( $_POST['action'] ?? '' );
			$data 	= Admin::postData();

			if( $action === 'recovery/login' ) {

				$status = self::verify( $appData, (string) ( $data['password'] ?? '' ) );

				if( $status !== 200 ) {
					\Nino\Http::fail( $request, $status, $status === 429 ? 'too many attempts' : 'wrong password' );
					return;
				}

				// Same fixation defence as a login through Auth
				session_regenerate_id( true );
				\Nino\Csrf::rotateToken( $appData );
				\Nino\Runtime::setSessionValue( $appData, self::SESSION_KEY, true );
				\Nino\Http::ok( $request );
				return;
			}

			if( self::isOpen( $appData ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return;
			}

			switch( $action ) {

				case 'recovery/logout':
					\Nino\Runtime::unsetSessionValue( $appData, self::SESSION_KEY );
					\Nino\Csrf::rotateToken( $appData );
					\Nino\Http::ok( $request );
					return;

				case 'recovery/list':
					// No Backups module, no backups to offer - the page then
					// has the password reset alone, which is the half that
					// needs nothing but this file (see \Nino\Admin\Admin::record())
					\Nino\Http::ok( $request, [
						'dates' => class_exists( '\\Nino\\Modules\\Backups\\Admin' ) === true ? \Nino\Modules\Backups\Admin::dates( $appData ) : [],
						'users' => array_keys( $appData['/nino/auth/user'] ?? [] )
					] );
					return;

				case 'recovery/restore':
					$date = (string) ( $data['date'] ?? '' );
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) !== 1 ) {
						\Nino\Http::fail( $request, 400, 'invalid date' );
						return;
					}
					if( class_exists( '\\Nino\\Modules\\Backups\\Admin' ) === false ) {
						\Nino\Http::fail( $request, 501, 'no backups module' );
						return;
					}
					$result = \Nino\Modules\Backups\Admin::restore( $appData, $date );
					if( $result !== true ) {
						\Nino\Http::fail( $request, $result[0], $result[1] );
						return;
					}
					\Nino\Http::ok( $request, [ 'restoredDate' => $date ] );
					return;

				case 'recovery/reset':
					self::_reset( $appData, $request, $data );
					return;
			}

			\Nino\Http::fail( $request, 404, 'unknown action' );
		}

		/**
		 *	Whether this session has passed the secret
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function isOpen( array &$appData ): bool {
			return \Nino\Runtime::getSessionValue( $appData, self::SESSION_KEY, false ) === true;
		}

		/**
		 *	Give an account a new password - or, when no account of that
		 *	name exists, create it with full access: the case where every
		 *	developer account was deleted and there is nothing left to log
		 *	in with. Logs every session of the account out on the way, the
		 *	same as changing a password through the Users panel would not
		 *	- a reset from here is a takeover by whoever holds the secret
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$data					The posted payload
		 *
		 *	@return 	void
		 */
		private static function _reset( array &$appData, array &$request, array $data ): void {

			$mail = trim( (string) ( $data['mail'] ?? '' ) );
			$pw 	= (string) ( $data['pw'] ?? '' );

			if( $mail === '' || filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid mail' );
				return;
			}

			if( strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			if( \Nino\Auth::getUser( $appData, $mail ) === false ) {

				// Full access of its own, whatever the roles say - this is
				// the emergency path - and the Developer role beside it
				// where there is one, so the account reads as what it is
				$role = isset( $appData['/nino/auth/roles']['developer'] ) === true ? 'developer' : '';

				if( \Nino\Auth::insertUser( $appData, $mail, $pw, [ '/*' ], $role ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not create the account' );
					return;
				}

				\Nino\Http::ok( $request, [ 'mail' => $mail, 'created' => true ] );
				return;
			}

			if( \Nino\Auth::updateUser( $appData, $mail, $mail, $pw ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not update the account' );
				return;
			}

			\Nino\Auth::logoutAllSessions( $appData, $mail );

			\Nino\Http::ok( $request, [ 'mail' => $mail, 'created' => false ] );
		}

		/**
		 *	Check a posted secret against the stored hash, under the shared
		 *	attempt counter, and answer with the http status recovery.php
		 *	should send: 200, 401 (wrong), or 429 (locked out).
		 *
		 *	The cooldown check, the verify and the tries update all happen
		 *	inside one lock: as three separate steps, two concurrent wrong
		 *	attempts could both read the same "tries" and both write back the
		 *	same +1, losing an increment - the exact way to dodge the lockout
		 *	with two requests instead of one. A project without a hash at all
		 *	(one whose file went missing) still runs the counter: answering
		 *	instantly would tell an unauthenticated caller which of the two
		 *	states this installation is in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$password
		 *
		 *	@return 	int											200 | 401 | 429
		 */
		public static function verify( array &$appData, string $password ): int {

			$hash 			= self::hash( $appData );
			$lockedOut 	= false;
			$verified 	= false;

			\Nino\Filesystem::mutate( $appData, self::LOCKOUT_PATH, function( array $state ) use ( $password, $hash, &$lockedOut, &$verified ): ?array {

				if( (int) $state['until'] > time() ) {
					$lockedOut = true;
					return null;
				}

				$verified = $hash !== null && password_verify( $password, $hash );

				if( $verified === true )
					return [ 'tries' => 0, 'until' => 0 ];

				$state['tries'] = (int) $state['tries'] + 1;
				if( $state['tries'] >= self::MAX_TRIES ) {
					$state['tries'] = 0;
					$state['until'] = time() + self::COOLDOWN;
				}

				return $state;
			}, [ 'tries' => 0, 'until' => 0 ] );

			if( $lockedOut === true )
				return 429;

			return $verified === true ? 200 : 401;
		}

		/**
		 *	Hash a new secret and store it - the wizard's last step, and
		 *	recovery.php itself when the secret is changed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$password			The plaintext secret
		 *
		 *	@return 	bool										Whether the hash was stored
		 */
		public static function set( array &$appData, string $password ): bool {

			return self::writeHash( $appData, password_hash( $password, PASSWORD_DEFAULT ) );
		}

		/**
		 *	The password hash currently in effect, or null when this project
		 *	has none yet.
		 *
		 *	Canonically PASSWORD_PATH, under the private directory - never
		 *	config.php: a Restore rewrites config.php, and a credential that
		 *	authorises restoring must not be part of what gets restored (the
		 *	same reasoning Backup already applies to the encryption key, see
		 *	Backup::_key()). It is also outside every tool folder, so
		 *	replacing _admin/ on an update no longer takes the
		 *	password with it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							Null when no password has been set at all
		 */
		public static function hash( array &$appData ): ?string {

			$raw = @file_get_contents( self::path( $appData ) );

			if( is_string( $raw ) === false )
				return null;

			if( str_starts_with( $raw, self::STUB_PREFIX ) === false || str_ends_with( $raw, self::STUB_SUFFIX ) === false )
				return null;

			$hash = substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) );

			// A truncated or half-written file must read as "no secret" and
			// therefore fail every attempt, never as a hash password_verify()
			// would happily reject in a way that looks like a wrong password
			return $hash !== '' ? $hash : null;
		}

		/**
		 *	Write a hash to PASSWORD_PATH, creating the private directory if
		 *	it isn't there yet - see set()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$hash					An already-hashed password, never a plaintext one
		 *
		 *	@return 	bool										False when the file could not be written
		 */
		public static function writeHash( array &$appData, string $hash ): bool {

			if( $hash === '' )
				return false;

			$path = self::path( $appData );
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

			// hash() reads these bytes directly rather than including
			// them, so opcache never sits in that path - but the file is
			// still php a webserver may compile if it is requested, and
			// leaving a stale compiled copy of a credential file around is
			// not something to rely on being harmless
			if( function_exists( 'opcache_invalidate' ) === true )
				opcache_invalidate( $path, true );

			return true;
		}

		/**
		 *	Drop an Apache deny rule into the private directory if it has
		 *	none. Belt and braces next to the stub inside the file itself:
		 *	the stub is what protects the hash on any webserver, this is what
		 *	keeps the directory from being browsable on the common one. Never
		 *	overwrites an existing file - a deployment may have its own rules
		 *
		 *	@param		string		$dir					Absolute path of the private directory
		 *
		 *	@return 	void
		 */
		private static function _denyDirectory( string $dir ): void {

			$path = $dir. '/.htaccess';

			if( is_dir( $dir ) === false || file_exists( $path ) === true )
				return;

			@file_put_contents( $path, "# Nino private directory - never served, only read by php.\n". "Require all denied\n" );
		}

		/**
		 *	The absolute path of the file hash() reads
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		public static function path( array &$appData ): string {
			return \Nino\Filesystem::getContentPath( $appData ). self::PASSWORD_PATH;
		}
	}
}
