<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\Navigation\Admin		The /_admin panel of the Navigation module - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Navigation {

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
	class Admin {

		public const string MANAGE_PERM = '/_admin/navs/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
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
			return [ 'navs', '/_admin/nav/navs', 25, 'structure' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu-icon lucide-menu"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg>';
		}

		// Drill-down: menu list -> one menu's own running order (see assets/admin.js)
		public static function panes(): array {
			return [ 'navs-list', 'navs-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ), \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ) ];
		}

		// The panel's own strings, one <locale>.php per interface language
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
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

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'navs/save'			=> ( ( $data['originalKey'] ?? '' ) === '' ? 'Add Navigation ' : 'Edit Navigation ' ). ( $data['key'] ?? '' ),
				'navs/delete'		=> 'Delete Navigation '. ( $data['key'] ?? '' ),
				'navs/assign'		=> 'Assign Route '. ( $data['httpUri'] ?? '' ). ' to Navigation '. ( $data['key'] ?? '' ),
				'navs/unassign'	=> 'Unassign Route '. ( $data['httpUri'] ?? '' ). ' from Navigation '. ( $data['key'] ?? '' ),
				'navs/move'			=> 'Move Route '. ( $data['httpUri'] ?? '' ). ' '. ( $data['direction'] ?? '' ). ' in Navigation '. ( $data['key'] ?? '' ),
				default	=> '',
			};
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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
				// empty one - the same shape the setup wizard writes (see its
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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
		 *	Unlike \Nino\Modules\Routes\Admin::navKeys() this is not gated on the Navigation
		 *	module being active: the registry is editable either way, and the
		 *	frontend is told separately (see _payload()'s 'active') so it can
		 *	say so rather than show an empty dialog for no visible reason
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Nav keys, eg. [ 'main', 'footer' ]
		 */
		public static function registry( array &$appData ): array {

			$navs = $appData['/nino/html/navs'] ?? [];

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
		 *	Mirrors \Nino\Modules\Routes\Admin::_routeKey() - a page's route key is derived
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

}
