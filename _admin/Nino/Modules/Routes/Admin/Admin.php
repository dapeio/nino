<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Routes\Admin	Routes panel: the pages a project serves
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Routes {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								"Pages" module: create/edit/delete the site's actual page
	 *												routes without hand-editing /nino/http/routes as raw json
	 *												(Config still covers everything this doesn't, see its own
	 *												docblock). A friendlier continuation of the wizard's
	 *												Webpages step (see _admin/install/Install.php's Webpages class -
	 *												not depended on here, same standalone-folder reasoning
	 *												every other class in this file follows) for once the wizard
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
	 *												written here, in the wizard or by hand in config.php is
	 *												the same page to all three. apiMove() swaps one page
	 *												route with its neighbor, the same ↑/↓ reordering
	 *												Webpages' own list does while the wizard is still around;
	 *												the route order is also what breaks a tie between two
	 *												equal menu priorities (see Modules\Navigation).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/routes/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		// Kept in sync with the wizard's Webpages class. The workbench owns
		// this route at runtime, so it does not show up in the persisted route
		// array used for the general collision check.
		private const array RESERVED_HTTP_URIS = [ '/_admin' ];

		// A fresh entry's text fields when none was posted (or a blank one) -
		// same generic-by-design reasoning _admin/install/Install.php's
		// Webpages::DEFAULT_TEXT docblock explains
		private const array DEFAULT_TEXT = [
			'name' 				=> 'Page',
			'title' 			=> 'Page Title',
			'description' => 'Page description.',
		];

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'routes/list' 		=> [ self::class, 'apiList' ],
				'routes/save' 		=> [ self::class, 'apiSave' ],
				'routes/delete' 	=> [ self::class, 'apiDelete' ],
				'routes/move' 		=> [ self::class, 'apiMove' ],
			];
		}

		/**
		 *	A Dashboard tile - see \Nino\Admin\Panels
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function summary( array &$appData ): array {
			return [ 'value' => self::count( $appData ), 'label' => '/_admin/dashboard/label/routes' ];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'routes', '/_admin/nav/routes', 20, 'structure' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-route-icon lucide-route"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg>';
		}

		public static function panes(): array {
			return [ 'routes-list', 'routes-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	How many pages are persisted - shared by \Nino\Modules\Dashboard\Admin::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

			return count( array_filter( $routes, fn( array $r, string $k ): bool => self::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'routes/save'		=> ( ( $data['originalHttpUri'] ?? '' ) === '' ? 'Add Route ' : 'Edit Route ' ). ( $data['httpUri'] ?? '' ),
				'routes/delete'	=> 'Delete Route '. ( $data['httpUri'] ?? '' ),
				'routes/move'		=> 'Move Route '. ( $data['httpUri'] ?? '' ). ' '. ( $data['direction'] ?? '' ),
				default	=> '',
			};
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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
		 *	that resolves its file at runtime - the wizard's "legal" unit's
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
		 *	them - the wizard's Webpages step builds the very same shape from
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
		 *	frontend to offer no menu fields at all.
		 *
		 *	Own copy of the wizard's Webpages::navKeys(), same standalone-per-
		 *	area reasoning every other helper in this class duplicates
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Nav keys, eg. [ 'main', 'footer' ]
		 */
		public static function navKeys( array &$appData ): array {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return [];

			$navs = $appData['/nino/html/navs'] ?? [];

			return array_values( array_unique( array_map( 'strval', $navs ) ) );
		}

		/**
		 *	Which navigations one posted entry asks to be in, narrowed to the
		 *	navigations this project actually registers.
		 *
		 *	@param		array 		$entry				One posted page entry
		 *	@param		array 		$navs					Registered nav keys (see navKeys())
		 *
		 *	@return 	array										Nav keys this entry belongs to
		 */
		public static function entryNavs( array $entry, array $navs ): array {

			return array_values( array_intersect( $navs, is_array( $entry['navs'] ?? null ) ? $entry['navs'] : [] ) );
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

			$files = glob( \Nino\Filesystem::path( $appData, '/templates' ). '/page-*.tpl' ) ?: [];
			$names = array_map( fn( string $file ): string => basename( $file, '.tpl' ), $files );

			sort( $names );

			return $names;
		}

		/**
		 *	'/foo', '/foo/', 'foo' all normalize to '/foo'; '/' stays '/'.
		 *	Rejects anything empty, without a leading slash once normalized
		 *	is impossible anyway, or containing '..'/characters outside a
		 *	plain path - same rules _admin/install/Install.php's
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
		 *	_admin/install/Install.php's Webpages::_routeKeys() docblock for the
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
		 *	one. Bodies the wizard's library ships aren't always a plain
		 *	template reference (its "legal" unit resolves the file per locale
		 *	via [[/nino/http/response/locale]]); null reports that rather
		 *	than handing back something shaped like a filename but isn't one,
		 *	which is what keeps apiSave() from flattening such an entry. Kept
		 *	as its own copy rather than reaching into the wizard, same as every
		 *	other helper in this class - _admin/install is meant to be deleted
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			// A route body the wizard's library shipped can be more than a
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
			// (empty, for the wizard's locale-resolving "legal" unit), and
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

			// The page's reachable path as a fill, so a template can link to
			// it by name - [[/webpage/site-home/uri]] - rather than repeating
			// a path this form can change. Global, because an entry has one
			// Http-URI for every locale, and blacklisted like every other
			// technical value: /_admin's Text panel edits wording, not routes
			self::_mergeText( $appData, '/text/global.php', [ '[[/webpage'. $uri. '/uri]]' => $httpUri ] );
			\Nino\Text::setBlacklisted( $appData, '/webpage'. $uri. '/uri', true );

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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
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
}
