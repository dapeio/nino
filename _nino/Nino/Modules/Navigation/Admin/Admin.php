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
	 *	Navigation				"Navigations" module: which menus this project has, and
	 *												which routes stand in each of them, in which order.
	 *
	 *												The other half of what the Routes module edits. Over
	 *												there a page ticks the menus it belongs to, one page at
	 *												a time; here one menu is opened and its whole running
	 *												order set - which is the only view in which "third entry
	 *												in the footer" is a thing you can see, let alone move.
	 *
	 *												Three config keys, and no copy of anything: the
	 *												'/nino/html/navs' list of menu keys both page editors
	 *												offer a checkbox for, each route's own 'navs' =&gt;
	 *												[ &lt;key&gt; =&gt; &lt;prio&gt; ] - the membership that actually
	 *												renders (see \Nino\Modules\Navigation) - and
	 *												'/nino/html/navroutes', the same memberships for a
	 *												page that exists only at runtime, such as a feature's
	 *												/blog and so has no route in config.php to carry them -
	 *												kept under the page's Element-URI, so a page with a
	 *												route per language (the imprint of Modules\Legal) is
	 *												one entry here, listed with its paths, and stays in the
	 *												menu when a path changes or a language joins.
	 *												Priorities are kept dense, 1..n per menu, so a position
	 *												in this list reads as the position in the menu rather
	 *												than as an arbitrary number someone has to space out by
	 *												hand. A hand-written route joins a menu exactly the same
	 *												way and shows up here like any other.
	 *
	 *												A menu is saved as a whole: the panel keeps a working copy
	 *												of the entries, and Save writes the complete running order
	 *												in one request, under the config lock (see apiSave()).
	 *												There is no action that moves, adds or removes a single
	 *												entry.
	 *
	 *												No locale picker, deliberately: a menu has nothing
	 *												per-locale about it. The wording it renders is each
	 *												page's own /_nino/webpage&lt;uri&gt;/name key, edited in Routes (or
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
			];
		}

		/**
		 *	Nav entry for this module - a structure panel, at the weight the rail
		 *	orders it by within that group
		 *
		 *	@return 	array										[ uri, label, weight, group ]
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
				default	=> '',
			};
		}

		/**
		 *	Every menu, each with the routes standing in it in their running
		 *	order, plus every route that could be added to one.
		 *
		 *	Also the whole response of the other actions here: each of them
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

			\Nino\Http::ok( $request, self::_payload( $appData, (array) ( $appData['/nino/http/routes'] ?? [] ) ) );
		}

		/**
		 *	Create a menu, rename one, and/or set its complete running order -
		 *	identified by $data['originalKey'] (empty for a new one).
		 *
		 *	'entries', when posted, is the menu's whole order: a list of http
		 *	uris, every one an entry the panel offers (see _candidates(); a page
		 *	with a route per language is one entry, any of its paths names it),
		 *	none twice. It is the only way entries change. Every entry of the
		 *	menu that is not in it loses its membership, the ones in it get the
		 *	dense priorities 1..n - a runtime-only page under its Element-URI in
		 *	'/nino/html/navroutes' - and what is stored for a page that is gone
		 *	(a feature switched off) goes with the first save of that menu.
		 *	Without 'entries' a save only creates or renames, as it always did.
		 *
		 *	A rename follows the key everywhere it is used as a key: the
		 *	registry, keeping its place in it, and every route that is a member,
		 *	keeping its priority. Templates are deliberately not rewritten - a
		 *	[navigation nav="..."] argument is content, and silently editing
		 *	template files out from under a developer is not this dialog's
		 *	business (the renamed menu simply renders nowhere until the template
		 *	is updated too)
		 *
		 *	Read, checked and written under one lock, and written once: the
		 *	registry, the routes and the runtime memberships are three keys of
		 *	one file. The persisted routes alone are written back - the live
		 *	array also holds the routes modules register at runtime, which are
		 *	not config.php's to keep (they are put back into it afterwards)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			// Before anything below replaces a route: what is live right now
			$live = (array) ( $appData['/nino/http/routes'] ?? [] );

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lock config.php for writing' );
				return;
			}

			try {

				$data 				= \Nino\Admin\Admin::postData();
				$key 					= trim( (string) ( $data['key'] ?? '' ) );
				$originalKey 	= trim( (string) ( $data['originalKey'] ?? '' ) );

				if( self::isValidKey( $key ) === false ) {
					\Nino\Http::fail( $request, 400, 'invalid navigation id: "'. $key. '"', 'navs_invalid_id', [ $key ], 'key' );
					return;
				}

				$config 		= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
				$routes 		= (array) ( $config['/nino/http/routes'] ?? [] );
				$navroutes 	= (array) ( $config['/nino/html/navroutes'] ?? [] );
				$registry 	= self::_storedRegistry( $config );

				// Free means free everywhere, not just in the registry: a route
				// carrying an unregistered menu key by hand would otherwise have
				// its two memberships silently collapsed into one by the rename
				// below - and a "new" menu would start out with members nobody
				// put in it
				if( $key !== $originalKey && self::_isTaken( $key, $registry, $routes, $navroutes ) === true ) {
					\Nino\Http::fail( $request, 409, 'navigation id already taken: "'. $key. '"', 'navs_id_taken', [ $key ], 'key' );
					return;
				}

				$index = $originalKey === '' ? null : array_search( $originalKey, $registry, true );

				if( $originalKey !== '' && $index === false ) {
					\Nino\Http::fail( $request, 404, 'unknown navigation' );
					return;
				}

				$candidates = self::_candidates( $routes, $live, \Nino\Locales::getNativeLocale( $appData ) );
				$order 			= null;

				if( ( $data['entries'] ?? null ) !== null ) {

					if( is_array( $data['entries'] ) === false ) {
						\Nino\Http::fail( $request, 400, 'entries must be a list of http uris', 'navs_invalid_entries' );
						return;
					}

					$order = [];

					foreach( $data['entries'] as $httpUri ) {

						if( is_string( $httpUri ) === false ) {
							\Nino\Http::fail( $request, 400, 'entries must be a list of http uris', 'navs_invalid_entries' );
							return;
						}

						// A page with a route per language is one entry, and any of its
						// paths names it
						$entryKey = self::_entryKey( $candidates, $httpUri );

						if( $entryKey !== null && in_array( $entryKey, $order, true ) === true ) {
							\Nino\Http::fail( $request, 400, 'route listed twice: "'. $httpUri. '"', 'navs_duplicate_entry', [ $httpUri ] );
							return;
						}

						if( $entryKey === null ) {
							\Nino\Http::fail( $request, 404, 'unknown route: "'. $httpUri. '"', 'navs_unknown_route', [ $httpUri ] );
							return;
						}

						$order[] = $entryKey;
					}
				}

				if( $originalKey === '' ) {
					$registry[] = $key;
				} else {

					$registry[$index] = $key;

					foreach( $routes as $routeKey => $route )
						if( isset( $route['navs'][$originalKey] ) === true )
							$routes[$routeKey]['navs'] = self::_renamed( $route['navs'], $originalKey, $key );

					foreach( $navroutes as $routeKey => $memberships )
						if( isset( $memberships[$originalKey] ) === true )
							$navroutes[$routeKey] = self::_renamed( $memberships, $originalKey, $key );
				}

				if( $order !== null )
					self::_applyOrder( $routes, $navroutes, $candidates, $key, $order );

				$appData['/nino/html/navs'] = array_values( $registry );

				if( $originalKey === '' && $order === null ) {

					// Nothing but the registry changed
					if( \Nino\AppData::writeContentData( $appData, [ '/nino/html/navs' ] ) === false ) {
						\Nino\Http::fail( $request, 500, 'could not write config.php' );
						return;
					}

					\Nino\Http::ok( $request, self::_payload( $appData, $live ) );
					return;
				}

				if( self::_persist( $appData, $request, $routes, $navroutes, $live ) === false )
					return;

				\Nino\Http::ok( $request, self::_payload( $appData, $live ) );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	Remove a menu: out of the registry, and off every route that was
		 *	a member - the persisted ones and the runtime-only ones alike.
		 *	Templates asking for it by name are left alone, same reasoning as
		 *	apiSave()'s rename - the menu they name simply renders empty
		 *	afterwards
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$live = (array) ( $appData['/nino/http/routes'] ?? [] );

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lock config.php for writing' );
				return;
			}

			try {

				$key 			= (string) ( \Nino\Admin\Admin::postData()['key'] ?? '' );
				$config 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
				$registry = self::_storedRegistry( $config );
				$index 		= array_search( $key, $registry, true );

				if( $index === false ) {
					\Nino\Http::fail( $request, 404, 'unknown navigation' );
					return;
				}

				unset( $registry[$index] );

				$routes 		= (array) ( $config['/nino/http/routes'] ?? [] );
				$navroutes 	= (array) ( $config['/nino/html/navroutes'] ?? [] );

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

				foreach( $navroutes as $routeKey => $memberships ) {

					if( isset( $memberships[$key] ) === false )
						continue;

					unset( $navroutes[$routeKey][$key] );

					if( count( $navroutes[$routeKey] ) === 0 )
						unset( $navroutes[$routeKey] );
				}

				$appData['/nino/html/navs'] = array_values( $registry );

				if( self::_persist( $appData, $request, $routes, $navroutes, $live ) === false )
					return;

				\Nino\Http::ok( $request, self::_payload( $appData, $live ) );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	The menu keys this project registered, in the order a picker
		 *	should list them - '/nino/html/navs', which is what
		 *	\Nino\Modules\Routes\Admin::navKeys() returns while the Navigation
		 *	module is active, the only state this panel exists in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Nav keys, eg. [ 'main', 'footer' ]
		 */
		public static function registry( array &$appData ): array {

			return self::_normalised( $appData['/nino/html/navs'] ?? [] );
		}

		/**
		 *	The registry as config.php holds it right now, which is what a
		 *	save or a delete has to start from: $appData carries the copy that
		 *	was loaded when the request began, and a concurrent request may
		 *	have changed the file since. A config.php without the key means the
		 *	framework default, as it does at boot
		 *
		 *	@param		array 		$config				The content of config.php, read under the lock
		 *
		 *	@return 	array										See registry()
		 */
		private static function _storedRegistry( array $config ): array {
			return self::_normalised( $config['/nino/html/navs'] ?? \Nino\AppData::DEFAULTS['/nino/html/navs'] );
		}

		/**
		 *	@param		mixed 		$navs					A '/nino/html/navs' value
		 *
		 *	@return 	array										Unique menu keys as strings, re-indexed
		 */
		private static function _normalised( mixed $navs ): array {
			return array_values( array_unique( array_map( 'strval', (array) $navs ) ) );
		}

		/**
		 *	Whether a menu key is already in use - registered, or carried by
		 *	a route that was wired up by hand without registering it
		 *
		 *	@param		string		$key
		 *	@param		array 		$registry			See registry()
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$navroutes		The runtime-only memberships, Element-URI =&gt; [ menu =&gt; prio ]
		 *
		 *	@return 	bool
		 */
		private static function _isTaken( string $key, array $registry, array $routes, array $navroutes ): bool {

			if( in_array( $key, $registry, true ) === true )
				return true;

			foreach( $routes as $route )
				if( isset( $route['navs'][$key] ) === true )
					return true;

			foreach( $navroutes as $memberships )
				if( isset( $memberships[$key] ) === true )
					return true;

			return false;
		}

		/**
		 *	A route's memberships with one menu key renamed, the others and
		 *	their order left as they are
		 *
		 *	@param		array 		$memberships	menu =&gt; priority
		 *	@param		string		$from
		 *	@param		string		$to
		 *
		 *	@return 	array
		 */
		private static function _renamed( array $memberships, string $from, string $to ): array {

			$renamed = [];
			foreach( $memberships as $navKey => $prio )
				$renamed[ $navKey === $from ? $to : $navKey ] = $prio;

			return $renamed;
		}

		/**
		 *	The entries a menu could contain: every persisted GET route, and
		 *	every page that exists only at runtime - live, but not in
		 *	config.php - which a feature or a module registers in its init()
		 *	(Posts' /blog). Technical routes stay in: robots.txt, sitemap.xml,
		 *	llms.txt and the dot-routes are routes like any other, and it is the
		 *	menu's owner who knows what a visitor should see. Out are what no
		 *	link can point at, among the runtime-only routes: a wildcard route
		 *	(a key ending in /*), a route that names no Element-URI, and the
		 *	workbench itself, /_admin and everything below it, the recovery page
		 *	included. A route persisted in config.php is offered as it always
		 *	was, one entry each.
		 *
		 *	The runtime-only routes of one Element-URI are one entry: a page
		 *	that has a route per language is one page, and it is kept in a menu
		 *	by that uri (see \Nino\Modules\Navigation::routeLines()). Its key
		 *	here is the Element-URI - which no route key can equal, those start
		 *	with GET:// - and 'routeKeys' lists its routes, the one of the native
		 *	language first.
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$live					The live route array, as it was before this request replaced anything
		 *	@param		string		$native				The native locale, whose route leads an entry
		 *
		 *	@return 	array										Entry key =&gt; [ 'route' =&gt; the leading route, 'runtime' =&gt; whether it is runtime-only, 'routeKeys' =&gt; its route keys ]
		 */
		private static function _candidates( array $routes, array $live, string $native = '' ): array {

			$candidates = [];

			foreach( $routes as $routeKey => $route )
				if( str_starts_with( (string) $routeKey, 'GET://' ) === true && is_array( $route ) === true )
					$candidates[$routeKey] = [ 'route' => $route, 'runtime' => false, 'routeKeys' => [ (string) $routeKey ] ];

			foreach( $live as $routeKey => $route ) {

				$routeKey = (string) $routeKey;

				if( isset( $candidates[$routeKey] ) === true || isset( $routes[$routeKey] ) === true || is_array( $route ) === false )
					continue;

				if( str_starts_with( $routeKey, 'GET://' ) === false || str_ends_with( $routeKey, '/*' ) === true )
					continue;

				if( $routeKey === 'GET://_admin' || str_starts_with( $routeKey, 'GET://_admin/' ) === true )
					continue;

				$uri = is_string( $route['uri'] ?? null ) === true ? $route['uri'] : '';

				if( str_starts_with( $uri, '/' ) === false )
					continue;

				if( isset( $candidates[$uri] ) === false ) {
					$candidates[$uri] = [ 'route' => $route, 'runtime' => true, 'routeKeys' => [ $routeKey ] ];
					continue;
				}

				$candidates[$uri]['routeKeys'][] = $routeKey;

				if( $native !== '' && ( $route['locale'] ?? null ) === $native && ( $candidates[$uri]['route']['locale'] ?? null ) !== $native ) {
					$candidates[$uri]['route'] = $route;
					array_unshift( $candidates[$uri]['routeKeys'], array_pop( $candidates[$uri]['routeKeys'] ) );
				}
			}

			return $candidates;
		}

		/**
		 *	The entry a posted http uri names: the persisted route with that
		 *	path, or the runtime-only page one of whose routes has it
		 *
		 *	@param		array 		$candidates		See _candidates()
		 *	@param		string		$httpUri
		 *
		 *	@return 	string|null							The entry's key, null when no entry has that path
		 */
		private static function _entryKey( array $candidates, string $httpUri ): ?string {

			$routeKey = self::_routeKey( $httpUri );

			foreach( $candidates as $key => $candidate )
				if( in_array( $routeKey, $candidate['routeKeys'], true ) === true )
					return (string) $key;

			return null;
		}

		/**
		 *	The priority one entry holds in one menu: a persisted route's own
		 *	'navs' first, then what '/nino/html/navroutes' says for the page's
		 *	Element-URI - the order the \Nino\Modules\Navigation shortcode
		 *	reads them in
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$navroutes		The runtime-only memberships
		 *	@param		string		$key					The entry's key, see _candidates()
		 *	@param		array 		$candidate		The entry
		 *	@param		string		$navKey
		 *
		 *	@return 	int|null								Null when the entry is not in this menu
		 */
		private static function _priority( array $routes, array $navroutes, string $key, array $candidate, string $navKey ): ?int {

			$own = $routes[$key]['navs'][$navKey] ?? null;

			if( $own !== null )
				return (int) $own;

			// Only an int counts here, as \Nino\Modules\Navigation::_runtimePriority()
			// reads it: a hand-written '3' never renders, so it is no member
			$uri	= $candidate['runtime'] === true ? $key : (string) ( $candidate['route']['uri'] ?? '' );
			$prio = $uri === '' ? null : ( $navroutes[$uri][$navKey] ?? null );

			return is_int( $prio ) === true ? $prio : null;
		}

		/**
		 *	The entries standing in one menu, in their running order -
		 *	priority first, the order the candidates stand in breaking a tie,
		 *	which is the order \Nino\Modules\Navigation::routeLines() renders
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$navroutes		The runtime-only memberships
		 *	@param		array 		$candidates		See _candidates()
		 *	@param		string		$navKey
		 *
		 *	@return 	array										Entry keys, eg. [ 'GET://', 'GET://contact', '/legal/imprint' ]
		 */
		private static function _members( array $routes, array $navroutes, array $candidates, string $navKey ): array {

			$members = [];
			foreach( $candidates as $key => $candidate )
				if( ( $prio = self::_priority( $routes, $navroutes, (string) $key, $candidate, $navKey ) ) !== null )
					$members[$key] = $prio;

			// asort() is stable as of php 8, so equal priorities keep the
			// order the routes stand in rather than an arbitrary one
			asort( $members );

			return array_keys( $members );
		}

		/**
		 *	Set one menu's running order: its entries in $order get the dense
		 *	priorities 1..n, every other entry of the menu - and whatever is
		 *	stored for a runtime page that is not there any more - leaves it.
		 *	Dense numbers keep config.php readable as positions. A persisted
		 *	route keeps its membership on itself (and a route with none left
		 *	carries no 'navs' at all, the shape the wizard writes), a
		 *	runtime-only page in $navroutes, under its Element-URI
		 *
		 *	@param		array 		&$routes			(reference) The persisted route array
		 *	@param		array 		&$navroutes		(reference) The runtime-only memberships
		 *	@param		array 		$candidates		See _candidates()
		 *	@param		string		$navKey
		 *	@param		array 		$order				Entry keys, in the intended order
		 *
		 *	@return 	void
		 */
		private static function _applyOrder( array &$routes, array &$navroutes, array $candidates, string $navKey, array $order ): void {

			foreach( $routes as $routeKey => $route ) {

				if( isset( $candidates[$routeKey] ) === false || isset( $route['navs'][$navKey] ) === false )
					continue;

				unset( $routes[$routeKey]['navs'][$navKey] );

				if( count( $routes[$routeKey]['navs'] ) === 0 )
					unset( $routes[$routeKey]['navs'] );
			}

			foreach( $navroutes as $uri => $memberships ) {

				if( isset( $memberships[$navKey] ) === false )
					continue;

				unset( $navroutes[$uri][$navKey] );

				if( count( $navroutes[$uri] ) === 0 )
					unset( $navroutes[$uri] );
			}

			$prio = 1;
			foreach( $order as $key ) {

				if( isset( $routes[$key] ) === true )
					$routes[$key]['navs'][$navKey] = $prio;
				else
					$navroutes[$key][$navKey] = $prio;

				$prio++;
			}
		}

		/**
		 *	Write the three keys one save or delete changed, and put the live
		 *	route array back as it was
		 *
		 *	writeContentData() serializes what $appData holds under a key, and
		 *	the live '/nino/http/routes' also carries the routes modules
		 *	registered at runtime - config.php must get the persisted ones
		 *	only, so that is what is assigned for the write. It is not what is
		 *	left behind: the rest of this request, and the response, still see
		 *	the runtime routes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$routes				The persisted route array to write
		 *	@param		array 		$navroutes		The runtime-only memberships to write
		 *	@param		array 		$live					The live route array from before
		 *
		 *	@return 	bool										False when config.php could not be written, the response says so
		 */
		private static function _persist( array &$appData, array &$request, array $routes, array $navroutes, array $live ): bool {

			$appData['/nino/http/routes'] = $routes;

			// Absent rather than empty: a project with no runtime memberships
			// has no such key in its config.php
			if( count( $navroutes ) === 0 )
				unset( $appData['/nino/html/navroutes'] );
			else
				$appData['/nino/html/navroutes'] = $navroutes;

			$written = \Nino\AppData::writeContentData( $appData, [ '/nino/html/navs', '/nino/http/routes', '/nino/html/navroutes' ] );

			$appData['/nino/http/routes'] = array_merge( $live, $routes );

			if( $written === false )
				\Nino\Http::fail( $request, 500, 'could not write config.php' );

			return $written;
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
		 *	Everything the dialog draws itself from: the menus with their members
		 *	in order and every route that could join one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$live					The live route array, as it was before this request replaced anything
		 *
		 *	@return 	array
		 */
		private static function _payload( array &$appData, array $live ): array {

			$config 		= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$routes 		= (array) ( $config['/nino/http/routes'] ?? [] );
			$navroutes 	= (array) ( $config['/nino/html/navroutes'] ?? [] );
			$candidates = self::_candidates( $routes, $live, \Nino\Locales::getNativeLocale( $appData ) );
			$labels 		= self::_labels( $appData, $candidates );

			$navs = [];
			foreach( self::registry( $appData ) as $key ) {

				$entries = [];
				foreach( self::_members( $routes, $navroutes, $candidates, $key ) as $entryKey )
					$entries[] = $labels[$entryKey];

				$navs[] = [ 'key' => $key, 'entries' => $entries ];
			}

			return [
				'navs' 		=> $navs,
				'routes' 	=> array_values( $labels ),
			];
		}

		/**
		 *	Every entry a menu could contain, labelled the way the menu would
		 *	label it.
		 *
		 *	Every GET route qualifies, not just the page ones the Routes
		 *	module manages - a menu entry is only ever "a path with a name",
		 *	and a route a module or a developer owns is as good a target as
		 *	any, one that exists only at runtime included ('runtime').
		 *	'named' reports whether the /_nino/webpage&lt;uri&gt;/name key the menu
		 *	renders from resolves at all: a route without one is skipped by
		 *	\Nino\Modules\Navigation::routeLines(), so offering it silently
		 *	would be offering an entry that never shows up
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$candidates		See _candidates()
		 *
		 *	@return 	array										Entry key =&gt; { httpUri, paths, uri, label, named, runtime } - 'httpUri' the path that names the entry, 'paths' every path of it (more than one for a page with a route per language)
		 */
		private static function _labels( array &$appData, array $candidates ): array {

			/*	The menu resolves the name through the fill engine, and that
				merges the locale-independent global.php under the file of the
				locale the visitor is on - see \Nino\Html::getFills(). Reading
				the native locale's file alone therefore reported two kinds of
				named route as unnamed: one named once in global.php for every
				language, and one named only in a language that is not the
				native one. Both render in the menu; only the panel refused to
				offer them. This dialog has no locale picker - a menu has
				nothing per-locale about it - so a route counts as named when
				any locale the project offers has a name for it, and the label
				is the native locale's wording wherever there is one.	*/
			$textDir 	= '/text';
			$global 	= \Nino\Filesystem::getFileContent( $appData, $textDir. '/global.php', [] );
			$texts 		= [];

			// Native first, so its wording wins the label where more than one
			// language named the same page
			foreach( array_unique( array_merge( [ \Nino\Locales::getNativeLocale( $appData ) ], \Nino\Locales::getAvailableLocales( $appData ) ) ) as $locale )
				$texts[] = array_merge( $global, \Nino\Filesystem::getFileContent( $appData, $textDir. '/'. $locale. '.php', [] ) );

			$labels = [];

			foreach( $candidates as $entryKey => $candidate ) {

				$paths 		= array_map( static fn( string $routeKey ): string => substr( $routeKey, strlen( 'GET:/' ) ), $candidate['routeKeys'] );
				$httpUri 	= $paths[0];
				$uri 			= (string) ( $candidate['route']['uri'] ?? $httpUri );
				$name 		= '';

				foreach( $texts as $fills )
					if( ( $name = (string) ( $fills['[[/_nino/webpage'. $uri. '/name]]'] ?? '' ) ) !== '' )
						break;

				$labels[$entryKey] = [
					'httpUri' => $httpUri,
					'paths' 	=> $paths,
					'uri' 		=> $uri,
					'label' 	=> $name !== '' ? $name : $httpUri,
					'named' 	=> $name !== '',
					'runtime' => $candidate['runtime'],
				];
			}

			return $labels;
		}
	}

}
