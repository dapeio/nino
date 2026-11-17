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
	 *												\Nino\Pages::put() for why), and the same single
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

			return count( \Nino\Pages::blocks( $routes ) );
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
			$templates = \Nino\Pages::templates( $appData );

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
		 *	\Nino\Pages::templateOf(), which deliberately reports null for a body
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
			return \Nino\Pages::isPageRoute( $routeKey, $route );
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
				$uri 			= \Nino\Pages::uriOf( $routeKey, $route );
				$locale 	= is_string( $route['locale'] ?? null ) === true ? $route['locale'] : '';

				if( isset( $pages[$uri] ) === false ) {

					$body = (string) ( $route['body'] ?? '' );

					$pages[$uri] = [
						'uri' 				=> $uri,
						'httpUri' 		=> $httpUri,
						'httpUris' 		=> [],
						'template' 		=> \Nino\Pages::templateOf( $body ) ?? '',
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
		 *	A route whose Element-URI is no safe path (see \Nino\Pages::normalizeUri())
		 *	is left out: the keys it would be given are the system's, and
		 *	not worth guessing at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ uri, httpUri, httpUris, body ], ... ] in route order
		 */
		public static function runtimeRoutes( array &$appData ): array {

			$runtime = [];

			foreach( \Nino\Pages::runtime( $appData ) as $routeKey => $route ) {

				$httpUri = substr( $routeKey, strlen( 'GET:/' ) );
				$uri 		 = \Nino\Pages::uriOf( $routeKey, $route );

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
			return \Nino\Pages::navKeys( $appData );
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
		 *	Create a brand new page, or save an existing one - identified by
		 *	$data['originalUri'], the Element-URI it has now (empty for a new
		 *	page). The panel checks the permission and hands the posted form to
		 *	\Nino\Pages::put(), which holds every rule of a page - the addresses
		 *	of the languages and the routes they make, the Element-URI and the
		 *	addresses against the other routes, the template against the page
		 *	templates there are, a name and a title for every active language, the
		 *	fills of the page's addresses - and writes it all or none of it; a
		 *	refusal of it is the failure this answers with
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();

			$result = \Nino\Pages::put( $appData, [
				'uri' 				=> \Nino\Admin\Admin::postString( $data, 'uri' ),
				'originalUri' => \Nino\Admin\Admin::postString( $data, 'originalUri' ),
				'httpUris' 		=> is_array( $data['httpUris'] ?? null ) === true ? $data['httpUris'] : [],
				'template' 		=> \Nino\Admin\Admin::postString( $data, 'template' ),
				'text' 				=> is_array( $data['text'] ?? null ) === true ? $data['text'] : [],
				'navs' 				=> self::entryNavs( $data, \Nino\Pages::navKeys( $appData ) ),
				'statusCode' 	=> (int) ( $data['statusCode'] ?? 200 ),
			] );

			if( self::_refused( $request, $result ) === true )
				return;

			\Nino\Http::ok( $request, [ 'pages' => self::pages( $appData, \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [], \Nino\Locales::getAvailableLocales( $appData ), \Nino\Pages::navKeys( $appData ) ) ] );
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

			$text = \Nino\Pages::texts( is_array( $data['text'] ?? null ) === true ? $data['text'] : [], $locales );

			return self::_refused( $request, $text ) === true ? null : $text['text'];
		}

		/**
		 *	Make the response of a request the refusal \Nino\Pages answered it with
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$result				What \Nino\Pages answered
		 *
		 *	@return 	bool										Whether it was a refusal
		 */
		private static function _refused( array &$request, array $result ): bool {

			if( isset( $result['error'] ) === false )
				return false;

			$error = $result['error'];

			\Nino\Http::fail( $request, $error['status'], $error['message'], $error['code'], $error['params'], $error['field'] );

			return true;
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
				$keys 	= \Nino\Pages::keys( $routes, $uri );

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
				$blocks = \Nino\Pages::blocks( $routes );

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
