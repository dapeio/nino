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
	 *	Routes						The Routes panel: create/edit/delete the site's actual page
	 *												routes without hand-editing /nino/http/routes as raw json
	 *												(the Config panel no longer edits that key, see its own
	 *												docblock). The only place pages are made once the
	 *												setup wizard is done - the wizard has no step for them, it
	 *												only creates the starter pages when it ends (see
	 *												_admin/install/Install.php's StarterPages class - not
	 *												depended on here, the same standalone-module reasoning every
	 *												panel under _admin/Nino/Modules/ follows): template selection
	 *												is restricted to whichever templates/page-*.tpl files already
	 *												exist on disk - no copying, no library units, just wiring an
	 *												existing template up to a uri.
	 *
	 *												The Element-URI/Http-URI split (see
	 *												_routeKey()'s docblock for why), and the same single
	 *												source of truth: /nino/http/routes plus the
	 *												/_nino/webpage&lt;uri&gt;/* keys in /text/*.php. There is no
	 *												second list to keep in sync - pages() derives the whole
	 *												thing from the routes on every request, so a route
	 *												written here, by the wizard's starter pages or by hand in
	 *												config.php is the same page to all three. apiMove() swaps one
	 *												page with its neighbor; the route order is also what breaks a
	 *												tie between two equal menu priorities (see Modules\Navigation).
	 *
	 *												A page has one address for every language, or one for
	 *												each. The first is one route; the second is a route for
	 *												each language that has an address of its own, with the
	 *												'locale' it is for and the 'uri' of the page - the way
	 *												Modules\Legal routes its two pages - and a route
	 *												without a 'locale' for the address the languages that
	 *												have none of their own share. An address is given once
	 *												in a request, so a page has at most one route without a
	 *												'locale'. The address of a language sets the language the
	 *												visitor is in when it is hit (see
	 *												\Nino\Locales::response()) and the language switch
	 *												leads to the address of the language picked, so pages()
	 *												reads the routes of one Element-URI back as one page,
	 *												and the fill /_nino/webpage&lt;uri&gt;/uri says the
	 *												address of each language in the text file of that
	 *												language.
	 *
	 *												A page a feature or a module routes at runtime - Posts'
	 *												/blog, the Newsletter's /.newsletter, Hello's /hello, the
	 *												imprint of Modules\Legal - is in no config.php, so none
	 *												of the above applies to it: it is not listed, ordered or
	 *												deleted here. Its name, title and description are still
	 *												the /_nino/webpage&lt;uri&gt;/* keys the menu and
	 *												html-header.tpl read, and nothing else creates them - the
	 *												Text Keys tab does not create a /_nino key - so
	 *												runtimeRoutes() lists those pages and apiSaveTexts()
	 *												writes exactly those three keys. A page that has a route
	 *												per language is one entry, with its paths.
	 *
	 *												Saving a page keeps whatever its first route carries that
	 *												this form does not edit - a 'maintenance' => false or a
	 *												'header' somebody put into config.php by hand - on every
	 *												route of the page, the 'locale' of each being the
	 *												language it is the address of. The two Element-URIs of
	 *												the legal pages are the module's: no page of the
	 *												project's own may take them.
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

		// The template a new route starts on, when the project has it: a blank
		// page of its own. Never another one - the templates a project has are
		// finished pages (a contact page, the 404), and one of them preselected
		// would publish a copy of it under the new path
		private const string DEFAULT_TEMPLATE = 'page-blank';

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
				'routes/savetexts' => [ self::class, 'apiSaveTexts' ],
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
		 *	Nav entry for this module - a structure panel, at the weight the
		 *	rail orders it by within that group
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'routes', '/_admin/nav/routes', 20, 'structure' ];
		}

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'address', 'template', 'name', 'navigation', 'open', 'delete', 'feature' ];
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
		 *	How many pages are persisted - the Dashboard tile, see summary(). A
		 *	page with a route per language is one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {

			$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$routes = is_array( $routes ) === true ? $routes : [];

			return count( self::_blocks( $routes ) );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'routes/save'		=> ( \Nino\Admin\Admin::postString( $data, 'originalUri' ) === '' ? 'Add Route ' : 'Edit Route ' ). \Nino\Admin\Admin::postString( $data, 'uri' ),
				'routes/delete'	=> 'Delete Route '. \Nino\Admin\Admin::postString( $data, 'uri' ),
				'routes/move'		=> 'Move Route '. \Nino\Admin\Admin::postString( $data, 'uri' ). ' '. \Nino\Admin\Admin::postString( $data, 'direction' ),
				'routes/savetexts' => 'Edit Feature Route '. \Nino\Admin\Admin::postString( $data, 'uri' ),
				default	=> '',
			};
		}

		/**
		 *	List the pages, every templates/page-*.tpl file available to
		 *	pick from, the template a new route is proposed ('' when the project
		 *	has no page-blank - then the form asks for a choice), the active
		 *	locales with what each is called and the one the workbench is on,
		 *	and the navigations a page can be put into (empty while the
		 *	Navigation module is inactive, which is what tells the frontend to
		 *	offer no menu fields at all), and the pages features route at
		 *	runtime (see runtimeRoutes()), apart from the persisted ones
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
			$templates = self::_templates( $appData );

			\Nino\Http::ok( $request, [
				'pages' 				=> self::pages( $appData, $config['/nino/http/routes'] ?? [], $locales, $navs ),
				'runtime' 			=> self::runtimePages( $appData, $locales ),
				'templates' 		=> $templates,
				'defaultTemplate' => in_array( self::DEFAULT_TEMPLATE, $templates, true ) === true ? self::DEFAULT_TEMPLATE : '',
				'locales' 			=> $locales,
				'localeNames' 	=> self::_localeNames( $appData, $locales ),
				'selectedLocale' => \Nino\Admin\Admin::contentLocale( $appData, true ),
				'navs' 					=> $navs,
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
		 *	that resolves its file at runtime - a hand-written
		 *	[template /templates/page-x.[[/nino/http/response/locale]]] is a
		 *	page like any other
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
		 *	them.
		 *
		 *	The route key is the Http-URI, the route's own 'uri' data field
		 *	the Element-URI, 'navs' the menu membership, and the per-locale
		 *	name/title/description are the /_nino/webpage&lt;uri&gt;/* keys both
		 *	tools write into /text/&lt;locale&gt;.php
		 *
		 *	The routes of one Element-URI are one page: a page has an address
		 *	for every language - one route, no 'locale' - or one for each, and
		 *	'httpUris' maps what a language is reached at, the key being the
		 *	language of a route that has one and '' that of a route that has
		 *	none (the first of each). 'httpUri' is the one address the list
		 *	line and the link out use: the native language's where it has one
		 *	of its own, else the one for every language, else the first. The
		 *	template, the menus, the status code and the body are the first
		 *	route's, which is what apiSave() has written to all of them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$locales			Available locales
		 *	@param		array 		$navKeys			Registered nav keys (see navKeys())
		 *
		 *	@return 	array										One entry per page, in the order of its first route
		 */
		public static function pages( array &$appData, array $routes, array $locales, array $navKeys ): array {

			$text = [];
			foreach( $locales as $locale )
				$text[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$native = \Nino\Locales::getNativeLocale( $appData );
			$pages 	= [];

			foreach( $routes as $routeKey => $route ) {

				if( self::isPageRoute( $routeKey, $route ) === false )
					continue;

				$httpUri 	= substr( $routeKey, strlen( 'GET:/' ) );
				$uri 			= self::_uriOf( $routeKey, $route );
				$locale 	= is_string( $route['locale'] ?? null ) === true ? $route['locale'] : '';

				if( isset( $pages[$uri] ) === false ) {

					$body = (string) ( $route['body'] ?? '' );

					$pages[$uri] = [
						'uri' 				=> $uri,
						'httpUri' 		=> $httpUri,
						'httpUris' 		=> [],
						'template' 		=> self::_templateFromBody( $body ) ?? '',
						'navs' 				=> array_values( array_intersect( $navKeys, array_keys( (array) ( $route['navs'] ?? [] ) ) ) ),
						'statusCode' 	=> (int) ( $route['statusCode'] ?? 200 ),
						'body' 				=> $body,
						'text' 				=> self::_pageText( $text, $locales, $uri ),
					];
				}

				$pages[$uri]['httpUris'][$locale] ??= $httpUri;
			}

			foreach( $pages as $key => $page )
				$pages[$key]['httpUri'] = $page['httpUris'][$native] ?? $page['httpUris'][''] ?? $page['httpUri'];

			return array_values( $pages );
		}

		/**
		 *	The pages features and modules route at runtime and no config.php has:
		 *	a route in the live route array that is a page (see isPageRoute())
		 *	and whose key is not among the persisted ones. A placeholder
		 *	route such as GET://blog/* is one, a route with no page template
		 *	(GET://.search) is not.
		 *
		 *	The routes of one Element-URI are one page, whatever their number:
		 *	the imprint of Modules\Legal has a route per language, and is listed
		 *	once, with every path it is reached at - 'httpUri' is the first one,
		 *	'httpUris' all of them. The details a page is given are named after
		 *	its Element-URI, and that is the same for all of them
		 *
		 *	A route whose Element-URI is no safe path (see _normalizeUri())
		 *	is left out: the keys it would be given are the system's, and
		 *	not worth guessing at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ uri, httpUri, httpUris, body ], ... ] in route order
		 */
		public static function runtimeRoutes( array &$appData ): array {

			$persisted = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$runtime 	 = [];

			foreach( $appData['/nino/http/routes'] ?? [] as $routeKey => $route ) {

				$routeKey = (string) $routeKey;

				if( is_array( $route ) === false || isset( $persisted[$routeKey] ) === true || self::isPageRoute( $routeKey, $route ) === false )
					continue;

				$httpUri = substr( $routeKey, strlen( 'GET:/' ) );
				$uri 		 = (string) ( $route['uri'] ?? $httpUri );

				if( self::_normalizeUri( $uri ) !== $uri )
					continue;

				if( isset( $runtime[$uri] ) === true ) {
					$runtime[$uri]['httpUris'][] = $httpUri;
					continue;
				}

				$runtime[$uri] = [ 'uri' => $uri, 'httpUri' => $httpUri, 'httpUris' => [ $httpUri ], 'body' => (string) ( $route['body'] ?? '' ) ];
			}

			return array_values( $runtime );
		}

		/**
		 *	runtimeRoutes() with each page's name, title and description in
		 *	every language, the shape pages() gives a persisted one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$locales			Available locales
		 *
		 *	@return 	array										[ [ uri, httpUri, body, text ], ... ]
		 */
		public static function runtimePages( array &$appData, array $locales ): array {

			$text = [];
			foreach( $locales as $locale )
				$text[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			return array_map( fn( array $route ): array => $route + [ 'text' => self::_pageText( $text, $locales, $route['uri'] ) ], self::runtimeRoutes( $appData ) );
		}

		/**
		 *	One page's name, title and description per language
		 *
		 *	@param		array 		$text					Locale => the locale's text file
		 *	@param		array 		$locales			Available locales
		 *	@param		string		$uri					The page's Element-URI
		 *
		 *	@return 	array										Locale => [ name, title, description ]
		 */
		private static function _pageText( array $text, array $locales, string $uri ): array {

			$pageText = [];

			foreach( $locales as $locale )
				$pageText[$locale] = [
					'name' 				=> (string) ( $text[$locale]['[[/_nino/webpage'. $uri. '/name]]'] 				?? '' ),
					'title' 			=> (string) ( $text[$locale]['[[/_nino/webpage'. $uri. '/title]]'] 			?? '' ),
					'description' => (string) ( $text[$locale]['[[/_nino/webpage'. $uri. '/description]]'] ?? '' ),
				];

			return $pageText;
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

			$files = array_filter( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/page-*.tpl' ) ?: [], 'is_file' );
			$names = array_map( fn( string $file ): string => basename( $file, '.tpl' ), $files );

			sort( $names );

			return $names;
		}

		/**
		 *	'/foo', '/foo/', 'foo' all normalize to '/foo'; '/' stays '/'.
		 *	Rejects anything empty, without a leading slash once normalized
		 *	is impossible anyway, or containing '..'/characters outside a
		 *	plain path
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
		 *	The /nino/http/routes array key one page entry occupies - always
		 *	derived from its httpUri (the real, reachable path), never its
		 *	uri (a stable identifier used only for the route's own 'uri'
		 *	data field and this entry's /_nino/webpage&lt;uri&gt;/* text meta):
		 *	\Nino\Http::requestRoute() matches a route by looking up
		 *	'&lt;METHOD&gt;:/'.$httpUri as a literal array key, not by scanning
		 *	for a route whose own 'uri' field matches. So a page can only
		 *	be reached at the single path its httpUri names, and its uri stays
		 *	decoupled from it: the home page keeps a stable '/home' identifier
		 *	while it lives at '/'. A page here is always exactly one template
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
		 *	one. A body isn't always a plain template reference (a hand-written
		 *	one may resolve the file per locale via
		 *	[[/nino/http/response/locale]]); null reports that rather
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
		 *	Create a brand new page, or save an existing one - identified by
		 *	$data['originalUri'], the Element-URI it has now (empty for a new
		 *	page): that is a page's identity, since it has one address for every
		 *	language or one for each. The addresses are $data['httpUris'], a
		 *	language for each and '' for the one that serves the languages that
		 *	have none of their own (see _postedAddresses()). The languages are
		 *	grouped by address, the way Modules\Legal does it for its two pages:
		 *	an address of one language is a route with that 'locale', an address
		 *	several languages share is a route without one, and a page whose
		 *	languages all share the address is the one route it always was
		 *	(see _routePlan()). They replace the page's routes of before, at the
		 *	slot of the first of them, all with the same body, status code and
		 *	menus.
		 *
		 *	The Element-URI is deduped against every *other* page (the page being
		 *	edited, found via originalUri, is excluded from its own dedupe check,
		 *	so re-saving a page unchanged - or renaming only one of its uris -
		 *	never falsely collides with itself); an address counts against every
		 *	route there is but the page's own and against the others of the
		 *	request; template is checked against _templates()'s whitelist. A
		 *	name and a title are required for every active language - a key
		 *	nobody wrote renders as the raw [[...]] - and refused before
		 *	anything is written; the description is optional and may stay
		 *	empty.
		 *
		 *	The fill /_nino/webpage&lt;uri&gt;/uri says the address of the page
		 *	in the language it is read in, so it is written into the text file
		 *	of every active language - the fallback chain of \Nino\Html::getFills()
		 *	would otherwise give a language without it the address of another
		 *	one - and into global.php for what no language file decides: the
		 *	address that serves every language, else the native language's.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			// Read, checked and written under one lock: all three of these read
			// the routes, decide against what they find and write the whole key
			// back, so two editors saving two different pages at the same moment
			// each wrote their own full copy and the second one dropped the
			// first one's page. writeContentData() locks for its own write only,
			// which is too late to help here - the decision is what has to be
			// under the lock. Re-locking inside is a no-op (see lockFile())
			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lock config.php for writing' );
				return;
			}

			try {

				$data 				= \Nino\Admin\Admin::postData();
				$originalUri 	= \Nino\Admin\Admin::postString( $data, 'originalUri' );

				$uri 			= self::_normalizeUri( \Nino\Admin\Admin::postString( $data, 'uri' ) );
				$template = \Nino\Admin\Admin::postString( $data, 'template' );

				if( $uri === null ) {
					\Nino\Http::fail( $request, 400, 'invalid uri: "'. ( \Nino\Admin\Admin::postString( $data, 'uri' ) ). '"', 'routes_invalid_uri', [ \Nino\Admin\Admin::postString( $data, 'uri' ) ], 'uri' );
					return;
				}

				$locales = \Nino\Locales::getAvailableLocales( $appData );

				$posted = self::_postedAddresses( $request, $data, $locales );
				if( $posted === null )
					return;

				[ $addresses, $fields ] = $posted;

				// The imprint and the privacy policy are the Legal module's, with
				// their routes, names and menu entries: a page of the project's own
				// under their Element-URI would share the details and the language
				// switch with them, whatever template it renders
				if( class_exists( '\\Nino\\Modules\\Legal' ) === true && in_array( $uri, array_column( \Nino\Modules\Legal::PAGES, 'uri' ), true ) === true ) {
					\Nino\Http::fail( $request, 409, 'the uri "'. $uri. '" belongs to the legal module', 'routes_reserved_uri', [ $uri ], 'uri' );
					return;
				}


				$statusCode = (int) ( $data['statusCode'] ?? 200 );
				if( $statusCode < 100 || $statusCode > 599 )
					$statusCode = 200;

				$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
				$routes = $config['/nino/http/routes'] ?? [];
				$navKeys = self::navKeys( $appData );

				// The page as it is now: its routes in the order they stand in, the
				// first of them leading. A page that is gone since the form was opened
				// is a new one
				$previousKeys = $originalUri !== '' ? self::_pageKeys( $routes, $originalUri ) : [];
				$first 				= $previousKeys !== [] && is_array( $routes[$previousKeys[0]] ?? null ) === true ? $routes[$previousKeys[0]] : [];

				if( $uri !== $originalUri && self::_pageKeys( $routes, $uri ) !== [] ) {
					\Nino\Http::fail( $request, 400, 'duplicate uri: "'. $uri. '"', 'routes_duplicate_uri', [ $uri ], 'uri' );
					return;
				}

				$plan = self::_routePlan( $addresses );

				// Every route counts here, not just the page ones: a page may
				// never take a uri robots.txt, a module or a developer already
				// answers on. Its own routes of before are the one place it may stay
				foreach( $plan as $entry ) {

					$routeKey = self::_routeKey( $entry['address'] );

					if( isset( $routes[$routeKey] ) === false || in_array( $routeKey, $previousKeys, true ) === true )
						continue;

					// The field of the key the request posted this address with
					$field = $fields[ $entry['address'] ];

					if( is_array( $routes[$routeKey] ) === true && self::isPageRoute( $routeKey, $routes[$routeKey] ) === true )
						\Nino\Http::fail( $request, 400, 'duplicate http uri: "'. $entry['address']. '"', 'routes_duplicate_http_uri', [ $entry['address'] ], $field );
					else
						\Nino\Http::fail( $request, 409, 'http uri already belongs to another route: "'. $entry['address']. '"', 'routes_http_uri_taken', [ $entry['address'] ], $field );

					return;
				}

				$body = '[template /templates/'. $template. ']';

				// A route body can be more than a plain template reference - a
				// hand-written one may pick the template file per locale via
				// [[/nino/http/response/locale]] - and the template <select> has no
				// way to spell that. Keep the body such an entry already carries
				// instead of flattening it into whichever single option happened to
				// be preselected
				if( isset( $first['body'] ) === true && self::_templateFromBody( (string) $first['body'] ) === null ) {
					$body 		= (string) $first['body'];
					// ...and with it the template field, which for such an entry
					// names nothing: the disabled <select> still posts whichever
					// option the browser preselected, and storing that would
					// leave the list claiming a template this page never uses
					$template = '';
				}

				// Checked here rather than up front: an entry whose body the
				// <select> can't spell keeps the template field it already had
				// (empty), and posts an empty value from its own disabled
				// option - neither of which names a real file, and neither of
				// which is an error
				if( $body === '[template /templates/'. $template. ']' && in_array( $template, self::_templates( $appData ), true ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown template: "'. $template. '"', 'routes_unknown_template', [ $template ], 'template' );
					return;
				}

				// A name and a title in every active language, checked last of the
				// refusals and before anything is written (see _postedText())
				$text = self::_postedText( $request, $data, $locales );
				if( $text === null )
					return;

				// Menu membership lives on the routes and nowhere else - that is
				// what Modules\Navigation::routeLines() reads, and the only copy
				// that renders
				$navs = self::entryNavs( $data, $navKeys );

				// What the first route carries and this form does not edit stays, on
				// every route of the page: a 'maintenance' => false that keeps a page up
				// while the site is down, a 'header' - put there by hand in config.php.
				// Only the four the form is about are set anew - and the 'locale' of
				// each, which is the rule's -, and a status code of 200 is no field,
				// as before
				$kept = array_diff_key( $first, [ 'uri' => true, 'locale' => true, 'body' => true, 'statusCode' => true, 'navs' => true ] );

				// A priority someone tuned on this page is not this save's to
				// reset - only the set of keys is rewritten (see the shortcode's
				// own routeLines() for what the value means). A membership this
				// save adds starts behind everything already in that menu
				$prios 		= is_array( $first['navs'] ?? null ) === true ? $first['navs'] : [];
				$memberOf = [];
				foreach( $navs as $navKey )
					$memberOf[$navKey] = (int) ( $prios[$navKey] ?? self::_nextPrio( $routes, $navKey ) );

				$put = [];

				foreach( $plan as $entry ) {

					$routeData = [ 'uri' => $uri ] + ( $entry['locale'] !== '' ? [ 'locale' => $entry['locale'] ] : [] ) + [ 'body' => $body ] + $kept;

					if( $statusCode !== 200 )
						$routeData['statusCode'] = $statusCode;
					if( $memberOf !== [] )
						$routeData['navs'] = $memberOf;

					$put[ self::_routeKey( $entry['address'] ) ] = $routeData;
				}

				$routes = self::_putRoute( $routes, $previousKeys, $put );

				$appData['/nino/http/routes'] = $routes;

				\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

				foreach( $locales as $locale )
					if( self::_mergeText( $appData, '/text/'. $locale. '.php', [
						'[[/_nino/webpage'. $uri. '/name]]' 				=> $text[$locale]['name'],
						'[[/_nino/webpage'. $uri. '/title]]' 			=> $text[$locale]['title'],
						'[[/_nino/webpage'. $uri. '/description]]' => $text[$locale]['description'],
						'[[/_nino/webpage'. $uri. '/uri]]' 				=> $addresses[$locale],
					] ) === false ) {
						\Nino\Http::fail( $request, 500, 'could not write /text/'. $locale. '.php' );
						return;
					}

				// The page's reachable path as a fill, so a template can link to
				// it by name - [[/_nino/webpage/site-home/uri]] - rather than repeating
				// a path this form can change. Each language's own file says the address of
				// that language (above); this one is for what no language's file decides: the
				// address that serves every language, else the native language's, else the
				// first. Blacklisted like every other technical value: /_admin's Text panel
				// edits wording, not routes
				$global = $addresses[ \Nino\Locales::getNativeLocale( $appData ) ] ?? (string) reset( $addresses );

				foreach( $plan as $entry )
					if( $entry['locale'] === '' ) {
						$global = $entry['address'];
						break;
					}

				if( self::_mergeText( $appData, '/text/global.php', [ '[[/_nino/webpage'. $uri. '/uri]]' => $global ] ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not write /text/global.php' );
					return;
				}

				if( \Nino\Text::setBlacklisted( $appData, '/_nino/webpage'. $uri. '/uri', true ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not write /text/blacklist.php' );
					return;
				}

				\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, $routes, $locales, $navKeys ) ] );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	The addresses a request posts, one for each active language:
		 *	'httpUris' maps a language to its address, and '' to the address of
		 *	every language that has none of its own. Every address is made a
		 *	safe path and kept off what the workbench owns, and is given once: an
		 *	address posted for two keys is refused, the later key named, like an
		 *	address another page has - languages that are to be reached at the
		 *	same address are the empty key's, not two entries that agree. An
		 *	empty one is none, so a language has an address of its own, else the
		 *	shared one, else the request is refused for it. So is a key that is
		 *	no language of the site - the one a form drawn before a language was
		 *	removed sends. The first thing wrong fails the request, and nothing
		 *	is written
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$data					The posted data
		 *	@param		array 		$locales			Available locales
		 *
		 *	@return 	array|null							[ addresses, fields ]: addresses is locale => its address, in the order of the locales, fields is address => the field of the key it was posted with; null once the request has failed
		 */
		private static function _postedAddresses( array &$request, array $data, array $locales ): ?array {

			$posted = is_array( $data['httpUris'] ?? null ) === true ? $data['httpUris'] : [];
			$given 	= [];
			$fields = [];

			foreach( $posted as $key => $value ) {

				$key 		= (string) $key;
				$field 	= $key === '' ? 'httpUri' : 'httpUri-'. $key;

				if( $key !== '' && in_array( $key, $locales, true ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown language: "'. $key. '"', 'routes_unknown_locale', [ $key ], $field );
					return null;
				}

				$raw = is_string( $value ) === true ? trim( $value ) : '';

				if( $raw === '' )
					continue;

				$address = self::_normalizeUri( $raw );

				if( $address === null ) {
					\Nino\Http::fail( $request, 400, 'invalid http uri: "'. $raw. '"', 'routes_invalid_http_uri', [ $raw ], $field );
					return null;
				}

				if( \Nino\Http::isReservedUri( $address ) === true ) {
					\Nino\Http::fail( $request, 409, 'reserved http uri: "'. $address. '"', 'routes_reserved_http_uri', [ $address ], $field );
					return null;
				}

				// Once: languages that are to share an address are the empty key's to say
				if( isset( $fields[$address] ) === true ) {
					\Nino\Http::fail( $request, 400, 'duplicate http uri: "'. $address. '"', 'routes_duplicate_http_uri', [ $address ], $field );
					return null;
				}

				$given[$key]		= $address;
				$fields[$address]	= $field;
			}

			$addresses = [];

			foreach( $locales as $locale ) {

				$address = $given[$locale] ?? $given[''] ?? null;

				if( $address === null ) {
					\Nino\Http::fail( $request, 400, 'missing http uri for '. $locale, 'routes_missing_http_uri', [ $locale ], 'httpUri-'. $locale );
					return null;
				}

				$addresses[$locale] = $address;
			}

			return [ $addresses, $fields ];
		}

		/**
		 *	The routes a page gets from the address of each language: the
		 *	languages are grouped by address, the rule \Nino\Modules\Legal routes
		 *	its pages by. An address of exactly one language is a route of that
		 *	language, an address several languages share is a route without one,
		 *	and when every language shares the address - a page that has one, on
		 *	a site with one language as well - that is the one route that serves
		 *	them all, with no language. An address is given once in a request
		 *	(see _postedAddresses()), so the languages that share one are those
		 *	the empty key serves, and a page has at most one route without a
		 *	language. The routes of one language stand first, as
		 *	\Nino\Http::findRouteUri() answers the first route that fits
		 *
		 *	@param		array 		$addresses		Locale => its address
		 *
		 *	@return 	array										[ [ 'address' => ..., 'locale' => the language, '' for every one ], ... ] in the order the routes are written
		 */
		private static function _routePlan( array $addresses ): array {

			$groups = [];

			foreach( $addresses as $locale => $address )
				$groups[$address][] = (string) $locale;

			if( count( $groups ) === 1 )
				return [ [ 'address' => (string) array_key_first( $groups ), 'locale' => '' ] ];

			$bound 	= [];
			$shared = [];

			foreach( $groups as $address => $sharing )
				if( count( $sharing ) === 1 )
					$bound[] = [ 'address' => (string) $address, 'locale' => $sharing[0] ];
				else
					$shared[] = [ 'address' => (string) $address, 'locale' => '' ];

			return array_merge( $bound, $shared );
		}

		/**
		 *	The name, title and description a request posts, per active
		 *	language, trimmed and as plain text. A name and a title are required in every one:
		 *	a key nobody wrote renders as the raw [[...]] on the page and in
		 *	the menu, so a route without them is not one to create. The
		 *	description is optional - an empty one renders nothing, which is a
		 *	valid description. The first thing missing fails the request, and
		 *	nothing is written
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$data					The posted data
		 *	@param		array 		$locales			Available locales
		 *
		 *	@return 	array|null							Locale => [ name, title, description ], null once the request has failed
		 */
		private static function _postedText( array &$request, array $data, array $locales ): ?array {

			$text = [];

			foreach( $locales as $locale ) {

				$row = is_array( $data['text'][$locale] ?? null ) ? $data['text'][$locale] : [];

				if( trim( is_string( $row['name'] ?? null ) ? $row['name'] : '' ) === '' ) {
					\Nino\Http::fail( $request, 400, 'missing name for '. $locale, 'routes_missing_name', [ $locale ], 'name' );
					return null;
				}

				if( trim( is_string( $row['title'] ?? null ) ? $row['title'] : '' ) === '' ) {
					\Nino\Http::fail( $request, 400, 'missing title for '. $locale, 'routes_missing_title', [ $locale ], 'title' );
					return null;
				}

				// Plain text, as the Text panel stores the same keys: the fill pass is a
				// blind str_replace, and the title and the description land in an
				// attribute of the page head
				$text[$locale] = [
					'name' 				=> \Nino\Text::sanitizeValue( trim( $row['name'] ), 'plain' ),
					'title' 			=> \Nino\Text::sanitizeValue( trim( $row['title'] ), 'plain' ),
					'description' => \Nino\Text::sanitizeValue( trim( is_string( $row['description'] ?? null ) ? $row['description'] : '' ), 'plain' ),
				];
			}

			return $text;
		}

		/**
		 *	Save the name, title and description, per language, of a page a
		 *	feature routes at runtime (see runtimeRoutes()) - the
		 *	/_nino/webpage<uri>/{name,title,description} keys, with the rules
		 *	apiSave() has for them. Nothing else: no route is written to
		 *	config.php - there is none, and a copy of the feature's would
		 *	outlive the feature's own change of it - no uri key, because the
		 *	feature decides the path and a stored one would be wrong the day
		 *	it moves, and nothing goes on the blacklist.
		 *
		 *	Only for an Element-URI a runtime route carries right now: any
		 *	other is a 404, so this is no way to write a /_nino key for a
		 *	page that is not there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSaveTexts( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$uri 	= \Nino\Admin\Admin::postString( $data, 'uri' );

			if( in_array( $uri, array_column( self::runtimeRoutes( $appData ), 'uri' ), true ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown feature route' );
				return;
			}

			$locales = \Nino\Locales::getAvailableLocales( $appData );
			$text 	 = self::_postedText( $request, $data, $locales );

			if( $text === null )
				return;

			foreach( $locales as $locale )
				if( self::_mergeText( $appData, '/text/'. $locale. '.php', [
					'[[/_nino/webpage'. $uri. '/name]]' 				=> $text[$locale]['name'],
					'[[/_nino/webpage'. $uri. '/title]]' 			=> $text[$locale]['title'],
					'[[/_nino/webpage'. $uri. '/description]]' => $text[$locale]['description'],
				] ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not write /text/'. $locale. '.php' );
					return;
				}

			\Nino\Http::ok( $request, [ 'runtime' => self::runtimePages( $appData, $locales ) ] );
		}

		/**
		 *	Where a membership this save newly adds goes: behind everything
		 *	already in that menu. Read across every route rather than just the
		 *	page ones, so a hand-written route in the same menu is counted too.
		 *	The runtime-only memberships ('/nino/html/navroutes') are not read:
		 *	a route that shares the number falls back to the order the routes
		 *	stand in, which the shortcode keeps for equal priorities
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
		 *	Write the routes of one page, keeping the slot the page already
		 *	occupies even when its keys change: the route array's own order is
		 *	what breaks a tie between two equal menu priorities (see
		 *	Modules\Navigation::routeLines()) and what the list in the browser
		 *	is sorted by, so renaming a page's http uri must not quietly move
		 *	it to the bottom of both. The routes it had before go, and the new
		 *	ones stand where the first of them stood - behind everything for a
		 *	page that had none
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$previousKeys	The keys of the page's routes now, in the order they stand in; none for a new page
		 *	@param		array 		$put					Route key => route, the routes to write in the order they are to stand in
		 *
		 *	@return 	array
		 */
		private static function _putRoute( array $routes, array $previousKeys, array $put ): array {

			$present = array_values( array_filter( $previousKeys, fn( string $key ): bool => isset( $routes[$key] ) ) );

			if( $present === [] )
				return $routes + $put;

			$out = [];

			foreach( $routes as $key => $route )
				if( in_array( $key, $present, true ) === false )
					$out[$key] = $route;
				else if( $key === $present[0] )
					$out = $out + $put;

			return $out;
		}

		/**
		 *	The pages of a route array, one block of routes each: the page
		 *	routes of one Element-URI, in the order of the first route of each
		 *	page - the order the list stands in
		 *
		 *	@param		array 		$routes				The persisted route array
		 *
		 *	@return 	array										[ [ 'uri' => the Element-URI, 'keys' => [ route key, ... ] ], ... ]
		 */
		private static function _blocks( array $routes ): array {

			$blocks = [];

			foreach( $routes as $routeKey => $route ) {

				if( is_array( $route ) === false || self::isPageRoute( (string) $routeKey, $route ) === false )
					continue;

				$uri 	= self::_uriOf( (string) $routeKey, $route );
				$at 	= array_search( $uri, array_column( $blocks, 'uri' ), true );

				if( $at === false )
					$blocks[] = [ 'uri' => $uri, 'keys' => [ (string) $routeKey ] ];
				else
					$blocks[$at]['keys'][] = (string) $routeKey;
			}

			return $blocks;
		}

		/**
		 *	The route keys of one page, in the order they stand in
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string		$uri					The page's Element-URI
		 *
		 *	@return 	array										Route keys, none for a uri no page has
		 */
		private static function _pageKeys( array $routes, string $uri ): array {

			foreach( self::_blocks( $routes ) as $block )
				if( $block['uri'] === $uri )
					return $block['keys'];

			return [];
		}

		/**
		 *	The Element-URI of a page route: its own 'uri', else its path
		 *
		 *	@param		string		$routeKey			Eg. 'GET://kontakt'
		 *	@param		array 		$route
		 *
		 *	@return 	string
		 */
		private static function _uriOf( string $routeKey, array $route ): string {
			return (string) ( $route['uri'] ?? substr( $routeKey, strlen( 'GET:/' ) ) );
		}

		/**
		 *	What each language is called, the way the picker of the site names
		 *	it - the fill /_nino/locale/<code>/name, which the project gives -
		 *	and the code itself where nothing does
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$locales			Available locales
		 *
		 *	@return 	array										Locale => name
		 */
		private static function _localeNames( array &$appData, array $locales ): array {

			$names = [];

			foreach( $locales as $locale ) {
				$name = \Nino\Html::renderTextfill( $appData, '/_nino/locale/'. $locale. '/name' );
				$names[$locale] = $name !== '' ? $name : $locale;
			}

			return $names;
		}

		/**
		 *	Remove a page: every route it has. Its /_nino/webpage&lt;uri&gt;/* text
		 *	meta - the fills of its addresses among it - is deliberately left in
		 *	place, same additive-only philosophy every other apply/save in this
		 *	codebase follows, deleting a file/key a developer may have since
		 *	hand-edited is a much riskier "undo" than dropping the routes. The
		 *	template file stays as well, whether or not another route uses it -
		 *	the browser's confirmation names both (see assets/admin.js's
		 *	_delete())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			// Read, checked and written under one lock: all three of these read
			// the routes, decide against what they find and write the whole key
			// back, so two editors saving two different pages at the same moment
			// each wrote their own full copy and the second one dropped the
			// first one's page. writeContentData() locks for its own write only,
			// which is too late to help here - the decision is what has to be
			// under the lock. Re-locking inside is a no-op (see lockFile())
			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lock config.php for writing' );
				return;
			}

			try {

				$uri 		= \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'uri' );
				$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
				$routes = is_array( $routes ) === true ? $routes : [];
				$keys 	= self::_pageKeys( $routes, $uri );

				// Only ever the routes of a page: this module lists nothing else, so
				// anything else is a module's or a developer's and not this button's
				// to delete
				if( $keys === [] ) {
					\Nino\Http::fail( $request, 404, 'unknown page' );
					return;
				}

				foreach( $keys as $routeKey )
					unset( $routes[$routeKey] );

				$appData['/nino/http/routes'] = $routes;

				\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

				\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, $routes, \Nino\Locales::getAvailableLocales( $appData ), self::navKeys( $appData ) ) ] );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	Swap one page with its immediate neighbor - the order this list
		 *	stands in, and the tie-breaker between two equal menu priorities
		 *	(see Modules\Navigation::routeLines()). A swap rather than an
		 *	arbitrary posted index: the list itself never leaves the browser,
		 *	only which of two neighbors should trade places - nothing to
		 *	validate beyond "is this still a valid direction from here". The
		 *	page goes with all of its routes, as one block
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

			// Read, checked and written under one lock: all three of these read
			// the routes, decide against what they find and write the whole key
			// back, so two editors saving two different pages at the same moment
			// each wrote their own full copy and the second one dropped the
			// first one's page. writeContentData() locks for its own write only,
			// which is too late to help here - the decision is what has to be
			// under the lock. Re-locking inside is a no-op (see lockFile())
			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lock config.php for writing' );
				return;
			}

			try {

				$data 			= \Nino\Admin\Admin::postData();
				$uri 				= \Nino\Admin\Admin::postString( $data, 'uri' );
				$direction 	= \Nino\Admin\Admin::postString( $data, 'direction' );

				if( in_array( $direction, [ 'up', 'down' ], true ) === false ) {
					\Nino\Http::fail( $request, 400, 'direction must be "up" or "down"' );
					return;
				}

				$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
				$routes = is_array( $routes ) === true ? $routes : [];
				$blocks = self::_blocks( $routes );

				$index = array_search( $uri, array_column( $blocks, 'uri' ), true );

				if( $index === false ) {
					\Nino\Http::fail( $request, 404, 'unknown page' );
					return;
				}

				$swapWith = $direction === 'up' ? $index - 1 : $index + 1;

				if( $swapWith < 0 || $swapWith >= count( $blocks ) ) {
					\Nino\Http::fail( $request, 400, 'already at the '. ( $direction === 'up' ? 'top' : 'bottom' ), $direction === 'up' ? 'already_top' : 'already_bottom' );
					return;
				}

				[ $blocks[$index], $blocks[$swapWith] ] = [ $blocks[$swapWith], $blocks[$index] ];

				// Refill the slots the page routes occupy, in the swapped order -
				// every other route stays exactly where it was
				$pageKeys = array_merge( ...array_column( $blocks, 'keys' ) );
				$ordered 	= [];
				$next 		= 0;

				foreach( $routes as $routeKey => $route )
					if( is_array( $route ) === true && self::isPageRoute( (string) $routeKey, $route ) === true ) {
						$ordered[ $pageKeys[$next] ] = $routes[ $pageKeys[$next] ];
						$next++;
					} else {
						$ordered[$routeKey] = $route;
					}

				$appData['/nino/http/routes'] = $ordered;

				\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

				\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, $ordered, \Nino\Locales::getAvailableLocales( $appData ), self::navKeys( $appData ) ) ] );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
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
		 *	@return 	bool								False when the file could not be written
		 */
		private static function _mergeText( array &$appData, string $path, array $fragment ): bool {
			return \Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $fragment ): array {
				return array_merge( $content, $fragment );
			} );
		}
	}
}
