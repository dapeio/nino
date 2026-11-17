<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Pages				The pages of a site: put one, list them
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Pages - a page is a route that draws a template of templates/page-*.tpl, with a name and a title in every language of
	 *	the site, an address for each language and a place in the menus. put() writes all of that in one call, under the one
	 *	lock of config.php, and all() reads it back; the Routes panel of the workbench and a feature that makes pages both
	 *	call them, and what a page has to be to be written is decided here and nowhere else.
	 *
	 *	The functions know no account: whoever calls them has decided that the account may. A panel checks its permission
	 *	(\Nino\Admin\Admin::guardPerm()) before it calls put(), and a feature does the same with the right of its own.
	 *
	 *	A page is the routes of one Element-URI: one route when the page has one address for every language, a route for each
	 *	language that has an address of its own - with its 'locale' - and a route without a 'locale' for the address that the
	 *	languages without one share. The names of the page, its title, its description and its address in each language are
	 *	the keys /_nino/webpage<uri>/{name,title,description,uri} of the text files
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Pages {

		// The name of a page template: page-, then words of lower case letters and digits joined by hyphens - the shape of a name
		// a template can have as the category of a text key, with the prefix that makes it a page
		public const string TEMPLATE = '#^page-[a-z0-9]+(?:-[a-z0-9]+)*$#D';

		// The longest name of a template this makes: a file name has 255 bytes, and what the name is joined to - the extension, a
		// temporary name beside it - has to fit as well
		public const int MAX_NAME = 100;

		/**
		 *	Make a page, or save an existing one - the one whose Element-URI is $page['originalUri']; empty for a new page.
		 *	All of it or none of it: the routes of the page, the names, the title, the description and the address of each
		 *	language, the entry in the menus and - where the caller hands the source of a template over - the template file are
		 *	written under the one lock of config.php, which is held from the first check to the last write: the routes are the
		 *	last of the writes, and a write that fails takes back the ones before it - byte for byte, see _write() - so that
		 *	neither a file nor a route nor a key of the text is left of a page that was refused.
		 *
		 *	A page that is saved a second time keeps what its first route carries and the form does not edit - a 'maintenance' => false,
		 *	a 'header' - on every route of the page, and its priority in the menus; a hand-written body that picks the template
		 *	by a fill is kept as it is. A page that is renamed or given other addresses stays at the slot of its first route,
		 *	which is what breaks a tie between two equal priorities in a menu.
		 *
		 *	The template is a file of templates/ that is there: put() does not make one. Where $page['source'] is given it is
		 *	made, from the source, as the one file of that name - the name is a page's (TEMPLATE, at most MAX_NAME long, and none
		 *	the kernel draws by itself, see reserved()), the file is not there yet, and the source is no larger than
		 *	\Nino\Modules\Template::MAX_BYTES, the largest template that is drawn.
		 *
		 *	The source is not checked. It is written as the bytes it is and read by nobody here: a call in it is drawn when the
		 *	page is, with the rules every template has - free html and a call without a schema in it are drawn for every
		 *	visitor. Whoever hands over a source that an account or another party wrote checks it first, with the rules of the
		 *	right that account has (a feature: \Nino\Components::check() and its own rule), and checks the right itself.
		 *
		 *	$page is
		 *		uri 				the Element-URI of the page, eg. '/about' - the identity its texts are filed under
		 *		originalUri	the Element-URI of the page that is saved, '' (or none) for a new one
		 *		httpUris		the addresses: a language => its address, and '' => the address of every language that has none of
		 *								its own; each is a path that is no reserved one and no other route has
		 *		template 		the name of a template of templates/ - or of the one that source makes
		 *		text 				language => [ name, title, description ], a name and a title for every language of the site
		 *		navs 				the menus the page is in, eg. [ 'main' ]; a menu that is not registered is left out
		 *		statusCode 	the status the route answers; 200 where it is none
		 *		source 			the html+ of a new template, or none
		 *
		 *	The answer is that of a page that is written: [ 'uri' => the Element-URI, 'routes' => the route keys in the order
		 *	they stand in, 'template' => the name of the template or '' where the body of the page is a hand-written one,
		 *	'created' => whether the template file was made by this call ]. The answer of a page that is refused is [ 'error'
		 *	=> [ 'status' => the HTTP status that fits, 'message' => a sentence for a log, 'code' => the code a panel makes a
		 *	sentence of in the language of the account ('' where there is none), 'params' => what the sentence names, 'field'
		 *	=> the field of the form that is to blame, '' for none ] ] - the arguments of \Nino\Http::fail() - and nothing is
		 *	written. A failed write of config.php is such an answer as well, with the status 500 and no code. Whether the caller
		 *	may ask is for the caller to say; this knows no account.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$page					The page, see above
		 *
		 *	@return 	array										The page that was written, or its error
		 */
		public static function put( array &$appData, array $page ): array {

			// Read, checked and written under one lock: the whole route array is read, decided against and written back, so
			// two saves of two pages at the same moment would each write their own copy and the second would drop the first
			// one's page. writeContentData() locks for its own write only, which is too late to help - the decision is what
			// has to be under the lock. Taking it a second time inside is nothing to do (see lockFile())
			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
				return self::_refuse( 500, 'could not lock config.php for writing' );

			try {
				return self::_put( $appData, $page );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	The pages of the site, one entry for each Element-URI: the routes of one page are one entry, whatever their number,
		 *	and several pages may draw the same template - a caller that wants a template once groups the entries by 'template'.
		 *	The pages a module or feature routes at runtime and no config.php has (the imprint and the privacy policy of
		 *	\Nino\Modules\Legal) stand in the list as well, after the stored ones, with 'runtime' => true; what a panel lists
		 *	beyond this - the status code, the body, the titles - is its own to read (\Nino\Modules\Routes\Admin::pages()).
		 *
		 *	Each entry is [ 'uri' => the Element-URI, 'template' => the name of the template, '' where the body of the page is a
		 *	hand-written one, 'names' => language => the name of the page, the title where the page has no name, 'addresses' =>
		 *	language => the address that language is reached at, 'locales' => the languages the page has an address in, 'navs' =>
		 *	the menus it is in, 'runtime' => whether no config.php has it ] in the order of the routes. A page that has an
		 *	address of its own for some languages only has none for the others unless a route without a language serves them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ entry, ... ]
		 */
		public static function all( array &$appData ): array {

			$stored 	= \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$stored 	= is_array( $stored ) === true ? $stored : [];
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );
			$navKeys 	= self::navKeys( $appData );
			$text 		= [];
			$pages 		= [];

			foreach( $locales as $locale )
				$text[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			foreach( [ [ false, $stored ], [ true, self::runtime( $appData ) ] ] as [ $runtime, $routes ] )
				foreach( self::blocks( $routes ) as $block ) {

					$first 	= (array) $routes[ $block['keys'][0] ];
					$bound 	= [];
					$shared = null;

					foreach( $block['keys'] as $routeKey ) {

						$httpUri 	= substr( $routeKey, strlen( 'GET:/' ) );
						$locale 	= is_string( $routes[$routeKey]['locale'] ?? null ) === true ? $routes[$routeKey]['locale'] : '';

						if( $locale === '' )
							$shared ??= $httpUri;
						else
							$bound[$locale] ??= $httpUri;
					}

					$names 			= [];
					$addresses 	= [];

					foreach( $locales as $locale ) {

						$name 					= (string) ( $text[$locale]['[[/_nino/webpage'. $block['uri']. '/name]]'] ?? '' );
						$names[$locale] = $name !== '' ? $name : (string) ( $text[$locale]['[[/_nino/webpage'. $block['uri']. '/title]]'] ?? '' );
						$address 				= $bound[$locale] ?? $shared;

						if( $address !== null )
							$addresses[$locale] = $address;
					}

					$pages[] = [
						'uri' 			=> $block['uri'],
						'template' 	=> self::templateOf( (string) ( $first['body'] ?? '' ) ) ?? '',
						'names' 		=> $names,
						'addresses' => $addresses,
						'locales' 	=> array_keys( $addresses ),
						'navs' 			=> array_values( array_intersect( $navKeys, array_keys( (array) ( $first['navs'] ?? [] ) ) ) ),
						'runtime' 	=> $runtime,
					];
				}

			return $pages;
		}

		/**
		 *	The page routes features and modules make at runtime and no config.php has: a route of the live route array that is a
		 *	page (see isPageRoute()) and whose key is not among the stored ones. A route whose Element-URI is no safe path (see
		 *	normalizeUri()) is left out: the keys it would be given are the system's, and not worth guessing at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Route key => route, in route order
		 */
		public static function runtime( array &$appData ): array {

			$stored = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$stored = is_array( $stored ) === true ? $stored : [];
			$found 	= [];

			foreach( $appData['/nino/http/routes'] ?? [] as $routeKey => $route ) {

				$routeKey = (string) $routeKey;

				if( is_array( $route ) === false || isset( $stored[$routeKey] ) === true || self::isPageRoute( $routeKey, $route ) === false )
					continue;

				$uri = self::uriOf( $routeKey, $route );

				if( self::normalizeUri( $uri ) !== $uri )
					continue;

				$found[$routeKey] = $route;
			}

			return $found;
		}

		/**
		 *	The templates the kernel draws by itself, by name: the page of the maintenance and the pages of the legal module. A
		 *	template of one of these names is theirs, whoever made the page, so a source is never made under one
		 *
		 *	@return 	array										Template names, eg. 'page-maintenance'
		 */
		public static function reserved(): array {

			$names = [ 'page-maintenance' ];

			if( class_exists( '\\Nino\\Modules\\Legal' ) === true )
				$names = array_merge( $names, array_column( \Nino\Modules\Legal::PAGES, 'template' ) );

			return $names;
		}

		/**
		 *	Whether one route is a page: a GET route that draws a templates/page-*.tpl file - the thing that keeps robots.txt,
		 *	sitemap.xml and llms.txt, GET routes with headers of their own and no page template, out of the pages. Matched on the
		 *	prefix of the body rather than through templateOf(), which reports null for a body that picks its file at runtime: a
		 *	hand-written [template /templates/page-x.[[/nino/http/response/locale]]] is a page like any other
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
		 *	The pages of a route array, one block of routes each: the page routes of one Element-URI, in the order of the first
		 *	route of each page
		 *
		 *	@param		array 		$routes				The persisted route array
		 *
		 *	@return 	array										[ [ 'uri' => the Element-URI, 'keys' => [ route key, ... ] ], ... ]
		 */
		public static function blocks( array $routes ): array {

			$blocks = [];

			foreach( $routes as $routeKey => $route ) {

				if( is_array( $route ) === false || self::isPageRoute( (string) $routeKey, $route ) === false )
					continue;

				$uri 	= self::uriOf( (string) $routeKey, $route );
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
		public static function keys( array $routes, string $uri ): array {

			foreach( self::blocks( $routes ) as $block )
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
		public static function uriOf( string $routeKey, array $route ): string {
			return (string) ( $route['uri'] ?? substr( $routeKey, strlen( 'GET:/' ) ) );
		}

		/**
		 *	'/foo', '/foo/', 'foo' all normalize to '/foo'; '/' stays '/'. Anything empty, with '..' in it or with a character
		 *	outside a plain path is none
		 *
		 *	@param		string		$uri
		 *
		 *	@return 	string|null							Null if $uri can't be normalized into a safe path
		 */
		public static function normalizeUri( string $uri ): ?string {

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
		 *	The on-disk template a route body names, when it names exactly one. A body is not always a plain template reference
		 *	(a hand-written one may pick the file per language by [[/nino/http/response/locale]]); null says so, rather than
		 *	handing back something shaped like a file name that is none
		 *
		 *	@param		string		$body					A route's body
		 *
		 *	@return 	string|null							Null if $body isn't a plain template reference
		 */
		public static function templateOf( string $body ): ?string {
			return preg_match( '~^\[template /templates/([A-Za-z0-9._-]+)\]$~', trim( $body ), $match ) === 1 ? $match[1] : null;
		}

		/**
		 *	Every templates/page-*.tpl file on disk, sorted, stripped of its extension - the whitelist a template is checked
		 *	against, so that a page can never be pointed at an arbitrary file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function templates( array &$appData ): array {

			$files = array_filter( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/page-*.tpl' ) ?: [], 'is_file' );
			$names = array_map( fn( string $file ): string => basename( $file, '.tpl' ), $files );

			sort( $names );

			return $names;
		}

		/**
		 *	The menus this project offers, in the order a picker lists them: '/nino/html/navs', a plain list of keys. Empty while
		 *	the Navigation module is inactive, which is what tells a form to offer no menu at all. Modules\Navigation renders any
		 *	key a template asks for, registered or not - this is an editing affordance and nothing more
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Menu keys, eg. [ 'main', 'footer' ]
		 */
		public static function navKeys( array &$appData ): array {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return [];

			$navs = $appData['/nino/html/navs'] ?? [];

			return array_values( array_unique( array_map( 'strval', $navs ) ) );
		}

		/**
		 *	The name, title and description a request posts, per language, trimmed and as plain text. A name and a title are
		 *	required in every language: a key nobody wrote renders as the raw [[...]] on the page and in the menu. The description
		 *	is optional. The first thing missing is the answer, and nothing is written
		 *
		 *	@param		array 		$rows					Language => [ name, title, description ]
		 *	@param		array 		$locales			The languages of the site
		 *
		 *	@return 	array										[ 'text' => language => [ name, title, description ] ], or [ 'error' => ... ] as put() answers it
		 */
		public static function texts( array $rows, array $locales ): array {

			$text = [];

			foreach( $locales as $locale ) {

				$row = is_array( $rows[$locale] ?? null ) === true ? $rows[$locale] : [];

				if( trim( is_string( $row['name'] ?? null ) === true ? $row['name'] : '' ) === '' )
					return self::_refuse( 400, 'missing name for '. $locale, 'routes_missing_name', [ $locale ], 'name' );

				if( trim( is_string( $row['title'] ?? null ) === true ? $row['title'] : '' ) === '' )
					return self::_refuse( 400, 'missing title for '. $locale, 'routes_missing_title', [ $locale ], 'title' );

				// Plain text, as the Text panel stores the same keys: the fill pass is a blind str_replace, and the title and
				// the description land in an attribute of the page head
				$text[$locale] = [
					'name' 				=> \Nino\Text::sanitizeValue( trim( $row['name'] ), 'plain' ),
					'title' 			=> \Nino\Text::sanitizeValue( trim( $row['title'] ), 'plain' ),
					'description' => \Nino\Text::sanitizeValue( trim( is_string( $row['description'] ?? null ) === true ? $row['description'] : '' ), 'plain' ),
				];
			}

			return [ 'text' => $text ];
		}

		/**
		 *	The refusal of a page, in the shape put() answers it
		 *
		 *	@param		int				$status				The HTTP status that fits
		 *	@param		string		$message			A sentence for a log
		 *	@param		string		$code					The code a panel makes a sentence of, '' for none
		 *	@param		array 		$params				What the sentence names
		 *	@param		string		$field				The field of the form to blame, '' for none
		 *
		 *	@return 	array										[ 'error' => [ status, message, code, params, field ] ]
		 */
		private static function _refuse( int $status, string $message, string $code = '', array $params = [], string $field = '' ): array {
			return [ 'error' => [ 'status' => $status, 'message' => $message, 'code' => $code, 'params' => $params, 'field' => $field ] ];
		}

		/**
		 *	put() under the lock: the checks, in the order a form is read in, and then the writes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$page					See put()
		 *
		 *	@return 	array
		 */
		private static function _put( array &$appData, array $page ): array {

			$rawUri 			= is_string( $page['uri'] ?? null ) === true ? $page['uri'] : '';
			$originalUri 	= is_string( $page['originalUri'] ?? null ) === true ? $page['originalUri'] : '';
			$template 		= is_string( $page['template'] ?? null ) === true ? $page['template'] : '';
			$source 			= is_string( $page['source'] ?? null ) === true ? $page['source'] : null;
			$uri 					= self::normalizeUri( $rawUri );

			if( $uri === null )
				return self::_refuse( 400, 'invalid uri: "'. $rawUri. '"', 'routes_invalid_uri', [ $rawUri ], 'uri' );

			$locales = \Nino\Locales::getAvailableLocales( $appData );
			$posted 	= self::_addresses( is_array( $page['httpUris'] ?? null ) === true ? $page['httpUris'] : [], $locales );

			if( isset( $posted['error'] ) === true )
				return $posted;

			[ $addresses, $fields ] = [ $posted['addresses'], $posted['fields'] ];

			// The imprint and the privacy policy are the Legal module's, with their routes, names and menu entries: a page of
			// the project's own under their Element-URI would share the details and the language switch with them, whatever
			// template it renders
			if( class_exists( '\\Nino\\Modules\\Legal' ) === true && in_array( $uri, array_column( \Nino\Modules\Legal::PAGES, 'uri' ), true ) === true )
				return self::_refuse( 409, 'the uri "'. $uri. '" belongs to the legal module', 'routes_reserved_uri', [ $uri ], 'uri' );

			$statusCode = (int) ( $page['statusCode'] ?? 200 );
			if( $statusCode < 100 || $statusCode > 599 )
				$statusCode = 200;

			$config 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$routes 	= $config['/nino/http/routes'] ?? [];
			$navKeys 	= self::navKeys( $appData );

			// The page as it stands: its routes in the order they stand in, the first of them leading. A page that is gone
			// since the form was opened is a new one
			$previousKeys = $originalUri !== '' ? self::keys( $routes, $originalUri ) : [];
			$first 				= $previousKeys !== [] && is_array( $routes[$previousKeys[0]] ?? null ) === true ? $routes[$previousKeys[0]] : [];

			if( $uri !== $originalUri && self::keys( $routes, $uri ) !== [] )
				return self::_refuse( 400, 'duplicate uri: "'. $uri. '"', 'routes_duplicate_uri', [ $uri ], 'uri' );

			$plan = self::_plan( $addresses );

			// Every route counts here, not just the page ones: a page may never take a uri robots.txt, a module or a developer
			// already answers on. Its own routes of before are the one place it may stay
			foreach( $plan as $entry ) {

				$routeKey = self::_routeKey( $entry['address'] );

				if( isset( $routes[$routeKey] ) === false || in_array( $routeKey, $previousKeys, true ) === true )
					continue;

				// The field of the key the request posted this address with
				$field = $fields[ $entry['address'] ];

				if( is_array( $routes[$routeKey] ) === true && self::isPageRoute( $routeKey, $routes[$routeKey] ) === true )
					return self::_refuse( 400, 'duplicate http uri: "'. $entry['address']. '"', 'routes_duplicate_http_uri', [ $entry['address'] ], $field );

				return self::_refuse( 409, 'http uri already belongs to another route: "'. $entry['address']. '"', 'routes_http_uri_taken', [ $entry['address'] ], $field );
			}

			$body = '[template /templates/'. $template. ']';

			// A route body can be more than a plain template reference - a hand-written one may pick the template file per
			// locale via [[/nino/http/response/locale]] - and the template <select> has no way to spell that. Keep the body
			// such an entry already carries, rather than flattening it into whichever single option happened to be
			// preselected. A template that is made by this call is the body's, whatever the page had
			if( $source === null && isset( $first['body'] ) === true && self::templateOf( (string) $first['body'] ) === null ) {
				$body 		= (string) $first['body'];
				// ...and with it the template field, which for such an entry names nothing: the disabled <select> still posts
				// whichever option the browser preselected, and storing that would leave the list claiming a template this
				// page never uses
				$template = '';
			}

			// Checked here rather than up front: an entry whose body the <select> can't spell keeps the template field it
			// already had (empty), and posts an empty value from its own disabled option - neither of which names a real
			// file, and neither of which is an error
			if( $source !== null ) {

				// The name is the one of a page and the file is a new one: the source of a template that is there is not this
				// call's to replace, a name outside the pattern is no file of templates/ this could write, and a name the
				// kernel reads by itself - the page of the maintenance, the pages of the legal module - is the kernel's. A name
				// that only differs in case from a template there is is that template on a file system that folds case
				if( strlen( $template ) > self::MAX_NAME || preg_match( self::TEMPLATE, $template ) !== 1 )
					return self::_refuse( 400, 'invalid template: "'. $template. '"', 'routes_invalid_template', [ $template ], 'template' );

				if( in_array( $template, self::reserved(), true ) === true )
					return self::_refuse( 409, 'reserved template: "'. $template. '"', 'routes_reserved_template', [ $template ], 'template' );

				if( \Nino\Filesystem::fileExists( $appData, '/templates/'. $template. '.tpl' ) === true || in_array( $template, array_map( 'strtolower', self::templates( $appData ) ), true ) === true )
					return self::_refuse( 409, 'template already exists: "'. $template. '"', 'routes_template_exists', [ $template ], 'template' );

				if( strlen( $source ) > \Nino\Modules\Template::MAX_BYTES )
					return self::_refuse( 400, 'template source is larger than '. \Nino\Modules\Template::MAX_BYTES. ' bytes', 'routes_template_too_large', [ \Nino\Modules\Template::MAX_BYTES ], 'source' );

			} else if( $body === '[template /templates/'. $template. ']' && in_array( $template, self::templates( $appData ), true ) === false ) {
				return self::_refuse( 400, 'unknown template: "'. $template. '"', 'routes_unknown_template', [ $template ], 'template' );
			}

			// A name and a title in every language, checked last of the refusals and before anything is written
			$text = self::texts( is_array( $page['text'] ?? null ) === true ? $page['text'] : [], $locales );

			if( isset( $text['error'] ) === true )
				return $text;

			$text = $text['text'];

			// Menu membership lives on the routes and nowhere else - that is what Modules\Navigation::routeLines() reads, and
			// the only copy that renders
			$navs = array_values( array_intersect( $navKeys, is_array( $page['navs'] ?? null ) === true ? $page['navs'] : [] ) );

			// What the first route carries and the form does not edit stays, on every route of the page: a 'maintenance' =>
			// false that keeps a page up while the site is down, a 'header' - put there by hand in config.php. Only the four
			// the form is about are set anew - and the 'locale' of each, which is the rule's -, and a status code of 200 is
			// no field
			$kept = array_diff_key( $first, [ 'uri' => true, 'locale' => true, 'body' => true, 'statusCode' => true, 'navs' => true ] );

			// A priority someone tuned on this page is not this save's to reset - only the set of keys is rewritten (see the
			// shortcode's own routeLines() for what the value means). A membership this save adds starts behind everything
			// already in that menu
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

			// The page's reachable path as a fill, so a template can link to it by name - [[/_nino/webpage/site-home/uri]] -
			// rather than repeating a path this form can change. Each language's own file says the address of that language;
			// this one is for what no language's file decides: the address that serves every language, else the native
			// language's, else the first
			$global = $addresses[ \Nino\Locales::getNativeLocale( $appData ) ] ?? (string) reset( $addresses );

			foreach( $plan as $entry )
				if( $entry['locale'] === '' ) {
					$global = $entry['address'];
					break;
				}

			$fragments = [];

			foreach( $locales as $locale )
				$fragments['/text/'. $locale. '.php'] = [
					'[[/_nino/webpage'. $uri. '/name]]' 				=> $text[$locale]['name'],
					'[[/_nino/webpage'. $uri. '/title]]' 			=> $text[$locale]['title'],
					'[[/_nino/webpage'. $uri. '/description]]' => $text[$locale]['description'],
					'[[/_nino/webpage'. $uri. '/uri]]' 				=> $addresses[$locale],
				];

			$fragments['/text/global.php'] = [ '[[/_nino/webpage'. $uri. '/uri]]' => $global ];

			$routes = self::_putRoute( $routes, $previousKeys, $put );

			return self::_write( $appData, $routes, $fragments, $uri, $template, $source, array_keys( $put ) );
		}

		/**
		 *	The writes of a page, in an order that lets each be taken back: the template, the text of each language, the address
		 *	in global.php, the blacklist - and the routes last. The lock of config.php is held from the first check of put() to
		 *	the write of the routes, which is why the routes are never written and taken back: a failure before them leaves
		 *	config.php as it is, and a failure of that write itself is an answer like any other. Each file of the text is read
		 *	under its own lock, in the same step that merges the keys of the page into it, and what the page changed there is
		 *	what a failure takes out again - its keys back to what they were, no key of anybody else touched - and the template
		 *	file is removed; the answer names the step that failed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The route array as it is to be
		 *	@param		array 		$fragments		File => the keys of the text that are to be merged into it
		 *	@param		string		$uri					The Element-URI of the page
		 *	@param		string		$template			The name of the template of the page, '' where its body is a hand-written one
		 *	@param		string|null	$source			The source of the template to make, null for none
		 *	@param		array 		$keys					The route keys of the page
		 *
		 *	@return 	array										See put()
		 */
		private static function _write( array &$appData, array $routes, array $fragments, string $uri, string $template, ?string $source, array $keys ): array {

			$file 		= '/templates/'. $template. '.tpl';
			$listed 	= '/_nino/webpage'. $uri. '/uri';
			$blacklist = '/text/blacklist.php';
			$changed 	= [];
			$made 		= false;
			$fail 		= null;

			if( $source !== null ) {

				// Made as a new file and by nobody else: a template another writer put there since the check is not this call's
				// to replace
				$made = self::_create( $appData, $file, $source );

				if( $made === null )
					return self::_refuse( 409, 'template already exists: "'. $template. '"', 'routes_template_exists', [ $template ], 'template' );

				if( $made === false )
					return self::_refuse( 500, 'could not write '. $file );
			}

			// What each file said of the keys of this page when it was merged into, and whether it was a file at all: the
			// state it is given back to
			foreach( $fragments as $path => $fragment ) {

				$real = \Nino\Filesystem::path( $appData, $path );
				$seen = [];

				if( \Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $fragment, $real, &$seen ): array {
					$seen = [ 'existed' => is_file( $real ), 'prior' => array_intersect_key( $content, $fragment ) ];
					return array_merge( $content, $fragment );
				} ) === false ) {
					$fail = 'could not write '. $path;
					break;
				}

				$changed[$path] = $seen;
			}

			if( $fail === null ) {

				$real = \Nino\Filesystem::path( $appData, $blacklist );
				$seen = [];

				$written = \Nino\Filesystem::mutate( $appData, $blacklist, function( array $list ) use ( $listed, $real, &$seen ): ?array {

					$seen = [ 'existed' => is_file( $real ), 'had' => in_array( $listed, $list, true ) ];

					if( $seen['had'] === true )
						return null;

					$list[] = $listed;

					return $list;
				} );

				if( $written === false && ( $seen['had'] ?? false ) === false )
					$fail = 'could not write '. $blacklist;
				else if( $written === true )
					$changed[$blacklist] = $seen;
			}

			if( $fail === null ) {

				if( \Nino\Filesystem::mutate( $appData, '/config.php', function( array $content ) use ( $routes ): array {
					$content['/nino/http/routes'] = $routes;
					return $content;
				} ) === false )
					$fail = 'could not write config.php';
				else
					$appData['/nino/http/routes'] = $routes;
			}

			if( $fail === null )
				return [ 'uri' => $uri, 'routes' => $keys, 'template' => $template, 'created' => $source !== null ];

			foreach( $changed as $path => $seen )
				self::_giveBack( $appData, $path, $seen, $fragments[$path] ?? [ $listed ], $path === $blacklist );

			if( $made === true && self::_bytes( $appData, $file ) === $source ) {
				@unlink( \Nino\Filesystem::path( $appData, $file ) );
				unset( $appData['./nino/filesystem/cache'][$file] );
			}

			return self::_refuse( 500, $fail );
		}

		/**
		 *	The bytes of a file of the project, null where there is none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A virtual path
		 *
		 *	@return 	string|null
		 */
		private static function _bytes( array &$appData, string $path ): ?string {

			$real = \Nino\Filesystem::path( $appData, $path );
			$read = is_file( $real ) === true ? @file_get_contents( $real ) : false;

			return $read === false ? null : $read;
		}

		/**
		 *	A file of a template, made as a new one and with nothing else: the bytes of $source
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$file					A virtual path, eg. '/templates/page-x.tpl'
		 *	@param		string		$source
		 *
		 *	@return 	bool|null								True where it was made, null where a file of that name is there, false where it could not be written
		 */
		private static function _create( array &$appData, string $file, string $source ): ?bool {

			\Nino\Filesystem::forceDir( $appData, dirname( ltrim( $file, '/' ) ) );

			$real 	= \Nino\Filesystem::path( $appData, $file );
			$temp 	= $real. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';

			unset( $appData['./nino/filesystem/cache'][$file] );

			// Written beside it and linked into place: link() fails for any name that is taken - a link that points nowhere
			// as well, which fopen( 'x' ) follows - and the file is whole or not there
			if( @file_put_contents( $temp, $source ) !== strlen( $source ) ) {
				@unlink( $temp );
				return false;
			}

			$linked = @link( $temp, $real );

			@unlink( $temp );

			if( $linked === true )
				return true;

			if( file_exists( $real ) === true || is_link( $real ) === true )
				return null;

			// A filesystem that has no hard links: the name is free and is no link, so the file is made at its place, and only
			// where it is not there yet
			$handle = @fopen( $real, 'xb' );

			if( $handle === false )
				return is_file( $real ) === true ? null : false;

			$written = fwrite( $handle, $source );
			fclose( $handle );

			if( $written !== strlen( $source ) ) {
				@unlink( $real );
				return false;
			}

			return true;
		}

		/**
		 *	One file of the text of a failed page given back, under its own lock: the keys of the page in it are what they were
		 *	before - gone where they were new, their old value and their place where they stood - and every other key stays as
		 *	it is, whoever wrote it meanwhile. A file that was none and holds nothing else is removed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A virtual path
		 *	@param		array 		$seen					What the write saw: 'existed', and 'prior' - the keys of the page the file had, with their values - or, for the blacklist, 'had'
		 *	@param		array 		$fragment			The keys the page merged into the file, or the one entry of the blacklist
		 *	@param		bool			$list					Whether the file is the blacklist, a list of keys and no map
		 *
		 *	@return 	void
		 */
		private static function _giveBack( array &$appData, string $path, array $seen, array $fragment, bool $list ): void {

			\Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $seen, $fragment, $list ): array {

				if( $list === true )
					return array_values( array_diff( $content, $fragment ) );

				$out = [];

				foreach( $content as $key => $value )
					if( array_key_exists( $key, $fragment ) === false )
						$out[$key] = $value;
					else if( array_key_exists( $key, $seen['prior'] ) === true )
						$out[$key] = $seen['prior'][$key];

				return $out;
			} );

			if( $seen['existed'] === false && \Nino\Filesystem::getFileContent( $appData, $path, [] ) === [] ) {
				@unlink( \Nino\Filesystem::path( $appData, $path ) );
				unset( $appData['./nino/filesystem/cache'][$path], $appData['./nino/html/filecache'], $appData['./nino/locales/text'] );
			}
		}

		/**
		 *	The addresses a request posts, one for each language: a language maps to its address, and '' to the address of every
		 *	language that has none of its own. Every address is made a safe path and kept off what the workbench owns, and is
		 *	given once: an address posted for two keys is refused, the later key named, like an address another page has -
		 *	languages that are to be reached at the same address are the empty key's, not two entries that agree. An empty one is
		 *	none, so a language has an address of its own, else the shared one, else the request is refused for it. So is a key
		 *	that is no language of the site - the one a form drawn before a language was removed sends. The first thing wrong is
		 *	the answer
		 *
		 *	@param		array 		$posted				The posted addresses
		 *	@param		array 		$locales			The languages of the site
		 *
		 *	@return 	array										[ 'addresses' => language => its address, in the order of the languages, 'fields' => address => the field of the key it was posted with ], or [ 'error' => ... ]
		 */
		private static function _addresses( array $posted, array $locales ): array {

			$given 	= [];
			$fields = [];

			foreach( $posted as $key => $value ) {

				$key 		= (string) $key;
				$field 	= $key === '' ? 'httpUri' : 'httpUri-'. $key;

				if( $key !== '' && in_array( $key, $locales, true ) === false )
					return self::_refuse( 400, 'unknown language: "'. $key. '"', 'routes_unknown_locale', [ $key ], $field );

				$raw = is_string( $value ) === true ? trim( $value ) : '';

				if( $raw === '' )
					continue;

				$address = self::normalizeUri( $raw );

				if( $address === null )
					return self::_refuse( 400, 'invalid http uri: "'. $raw. '"', 'routes_invalid_http_uri', [ $raw ], $field );

				if( \Nino\Http::isReservedUri( $address ) === true )
					return self::_refuse( 409, 'reserved http uri: "'. $address. '"', 'routes_reserved_http_uri', [ $address ], $field );

				// Once: languages that are to share an address are the empty key's to say
				if( isset( $fields[$address] ) === true )
					return self::_refuse( 400, 'duplicate http uri: "'. $address. '"', 'routes_duplicate_http_uri', [ $address ], $field );

				$given[$key]			= $address;
				$fields[$address]	= $field;
			}

			$addresses = [];

			foreach( $locales as $locale ) {

				$address = $given[$locale] ?? $given[''] ?? null;

				if( $address === null )
					return self::_refuse( 400, 'missing http uri for '. $locale, 'routes_missing_http_uri', [ $locale ], 'httpUri-'. $locale );

				$addresses[$locale] = $address;
			}

			return [ 'addresses' => $addresses, 'fields' => $fields ];
		}

		/**
		 *	The routes a page gets from the address of each language: the languages are grouped by address, the rule
		 *	\Nino\Modules\Legal routes its pages by. An address of exactly one language is a route of that language, an address
		 *	several languages share is a route without one, and when every language shares the address - a page that has one, on
		 *	a site with one language as well - that is the one route that serves them all, with no language. An address is given
		 *	once in a request (see _addresses()), so the languages that share one are those the empty key serves, and a page has
		 *	at most one route without a language. The routes of one language stand first, as \Nino\Http::findRouteUri() answers
		 *	the first route that fits
		 *
		 *	@param		array 		$addresses		Locale => its address
		 *
		 *	@return 	array										[ [ 'address' => ..., 'locale' => the language, '' for every one ], ... ] in the order the routes are written
		 */
		private static function _plan( array $addresses ): array {

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
		 *	The /nino/http/routes array key one page entry occupies - always derived from its address (the real, reachable
		 *	path), never its Element-URI: \Nino\Http::requestRoute() matches a route by looking up '<METHOD>:/'.$httpUri as a
		 *	literal array key, not by scanning for a route whose own 'uri' field matches. So a page can only be reached at the
		 *	path its key names, and its Element-URI stays decoupled from it: the home page keeps a stable '/home' identifier
		 *	while it lives at '/'
		 *
		 *	@param		string		$httpUri
		 *
		 *	@return 	string
		 */
		private static function _routeKey( string $httpUri ): string {
			return 'GET://'. trim( $httpUri, '/' );
		}

		/**
		 *	Where a membership a save newly adds goes: behind everything already in that menu. Read across every route rather
		 *	than just the page ones, so a hand-written route in the same menu is counted too. The runtime-only memberships
		 *	('/nino/html/navroutes') are not read: a route that shares the number falls back to the order the routes stand in,
		 *	which the shortcode keeps for equal priorities
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
		 *	The routes of one page written into the route array, keeping the slot the page already occupies even when its keys
		 *	change: the route array's own order is what breaks a tie between two equal menu priorities (see
		 *	Modules\Navigation::routeLines()) and what the list of a panel is sorted by, so renaming the address of a page must
		 *	not quietly move it to the bottom of both. The routes it had before go, and the new ones stand where the first of
		 *	them stood - behind everything for a page that had none
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$previousKeys	The keys of the page's routes as they stand, in the order they stand in; none for a new page
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
	}
}
