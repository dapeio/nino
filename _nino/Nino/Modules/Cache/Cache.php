<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Cache				see Nino.php's Modules section for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Cache								Full-page cache for anonymous GET requests. A hit skips
	 *											the render - routing, template reads, the fill and
	 *											shortcode passes - and answers from a stored copy.
	 *
	 *											It does not skip \Nino\init(), and cannot: a stored page
	 *											carries two values that belong to whoever is asking for
	 *											it, not to whoever it was rendered for, and both are only
	 *											knowable once the session is up.
	 *
	 *											  - the [csrf] token. Serving one visitor's token to
	 *											    everybody would leak it and 403 every form
	 *											    submission but theirs.
	 *											  - the [jstext] nonce, which the Content-Security-Policy
	 *											    header whitelists for that one response. Freezing it
	 *											    for the life of a cache entry makes it readable by
	 *											    anyone who fetches the page, which is exactly the
	 *											    property a nonce may not have - and Http's own
	 *											    default policy comment notes that the csp is
	 *											    load-bearing here, since textfills go into the page
	 *											    unescaped.
	 *
	 *											Both are therefore stored as markers and stamped with
	 *											this request's values on the way out (see _stamp()), and
	 *											the header keeps the nonce Jstext just generated. Serving
	 *											before the boot would mean giving up one or the other.
	 *
	 *											What is never cached, regardless of configuration: any
	 *											method but GET, anything with query vars, any uri under
	 *											/_ (the tools) or /. (module endpoints), any response
	 *											that is not a plain 200, and every request from a logged
	 *											in user.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Cache {

		// Under /data, ie. the private tree - never served by a webserver, and
		// dropped wholesale by _invalidate()
		private const string DIR = '/data/cache';

		private const int DEFAULT_TTL = 3600;

		// Stand-ins for the two per-request values, long and unguessable enough
		// that no page content can collide with them
		private const string CSRF_MARK	= '@@nino-cache-csrf-2f8a@@';
		private const string NONCE_MARK	= '@@nino-cache-nonce-2f8a@@';

		// A write through one of these drops the cache. The tools are ordinary
		// requests from here, so this needs no hook inside _admin/_editor/
		// _templates - which a module has no business reaching into anyway.
		private const array TOOL_PREFIXES = [ '/_admin', '/_editor', '/_templates', '/_install' ];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// Priority 9, ie. after \Nino\Csrf (1) and Modules\Jstext (5): a hit
			// is answered from inside this callback and never reaches the render,
			// so everything that composes the response header has to have run
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ], 9 );

			// The only hook with a finished body in hand - registered whether or
			// not the cache is on, so that switching it off (itself a write
			// through /_admin) still drops what is stored
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/output', [ self::class, 'callbackOutput' ] );
		}

		/**
		 *	Answer from the cache if there is a fresh copy, otherwise fall
		 *	through and let the request render normally
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			if( self::_servable( $appData, $request ) === false )
				return;

			$entry = \Nino\Filesystem::getFileContent( $appData, self::DIR. '/'. self::_key( $request ). '.php', false );

			if( is_array( $entry ) === false || is_string( $entry['body'] ?? null ) === false || ( $entry['expires'] ?? 0 ) < time() )
				return;

			// The route's own locale, which \Nino\request() applies through
			// Locales::response() right after this callback round - which
			// answering here skips. Without this a visit to a locale-bound page
			// would stop switching the session over as soon as it was cached.
			if( is_string( $entry['locale'] ?? null ) === true && $entry['locale'] !== '' )
				\Nino\Locales::setCurrentLocale( $appData, $entry['locale'] );

			$request['/nino/http/response']['body'] 									= self::_stamp( $appData, $entry['body'], false );
			$request['/nino/http/response']['header']['X-Nino-Cache']	= 'hit';

			// Ends the request here: no route callbacks, no fills, no render.
			// That is the entire point of the module, and _servable() has
			// already established there is nothing registered that this would
			// silently skip
			\Nino\Http::output( $appData, $request );
		}

		/**
		 *	Store a finished response, or drop the whole cache if this request
		 *	was a successful write through one of the tools
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Finished request
		 *
		 *	@return 	void
		 */
		public static function callbackOutput( array &$appData, array &$request ): void {

			$method	= (string) ( $request['/nino/http/request']['method'] ?? '' );
			$uri 		= (string) ( $request['/nino/http/request']['uri'] ?? '' );
			$status	= (int) ( $request['/nino/http/response']['statusCode'] ?? 0 );

			// Checked here rather than in callbackResponse(): only the finished
			// response says whether the write was accepted, and a rejected one
			// (a 403 from the csrf guard, a validation error) changed nothing
			// that would need dropping
			if( $status === 200 && in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ], true ) === false && self::_isTool( $uri ) === true ) {
				self::_invalidate( $appData );
				return;
			}

			if( self::_cacheable( $appData, $request ) === false || $status !== 200 )
				return;

			$body = $request['/nino/http/response']['body'] ?? null;

			// A json body is an api answer, not a page; a redirect's body is
			// beside the point and its Location header is not stored at all
			if( is_string( $body ) === false || $body === '' || isset( $request['/nino/http/response']['header']['Location'] ) === true )
				return;

			// Says so on the way out as well as on a hit: without both, whether
			// the cache is doing anything is only visible by timing the request
			$request['/nino/http/response']['header']['X-Nino-Cache'] = 'miss';

			\Nino\Filesystem::putFileContent( $appData, self::DIR. '/'. self::_key( $request ). '.php', [
				'expires'	=> time() + self::_ttl( $appData ),
				'locale'	=> (string) ( $request['/nino/http/response']['locale'] ?? '' ),
				'body' 		=> self::_stamp( $appData, $body, true ),
			] );
		}

		/**
		 *	Swap the two per-request values for their markers, or back again.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$html
		 *	@param		bool			$store				true = on the way in, false = on the way out
		 *
		 *	@return 	string
		 */
		private static function _stamp( array &$appData, string $html, bool $store ): string {

			$csrf 	= \Nino\Csrf::getToken( $appData );
			$nonce	= (string) ( $appData['./nino/jstext/nonce'] ?? '' );

			// An absent nonce (Jstext not enabled) must not become an empty
			// needle - str_replace() with one is a no-op, but pairing it with a
			// marker on the way out would put the marker into the page
			$live 	= [ $csrf ];
			$marks	= [ self::CSRF_MARK ];

			if( $nonce !== '' ) {
				$live[] 	= $nonce;
				$marks[]	= self::NONCE_MARK;
			}

			return $store === true ? str_replace( $live, $marks, $html ) : str_replace( $marks, $live, $html );
		}

		/**
		 *	Whether this request may be answered from the cache. Everything
		 *	_cacheable() requires, plus the two conditions that only matter for
		 *	answering: the route resolved to a plain 200, and nothing is
		 *	registered for this exact route that ending the request here would
		 *	skip.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$request
		 *
		 *	@return 	bool
		 */
		private static function _servable( array &$appData, array $request ): bool {

			if( self::_cacheable( $appData, $request ) === false )
				return false;

			if( (int) ( $request['/nino/http/response']['statusCode'] ?? 0 ) !== 200 )
				return false;

			// A project's own module may hang a handler off one specific route
			// (the shape Modules\Form and Modules\Newsletter use for their
			// endpoints). Answering from here would never call it.
			$routeCallback = '/nino/http/response/'. $request['/nino/http/request']['method']. ':/'. ( $request['/nino/http/response']['uri'] ?? '' );

			return isset( $appData['./nino/callbacks'][$routeCallback] ) === false;
		}

		/**
		 *	Whether this request's output may be stored at all - the rules that
		 *	hold in both directions, so that nothing can be served under
		 *	conditions it was not stored under
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$request
		 *
		 *	@return 	bool
		 */
		private static function _cacheable( array &$appData, array $request ): bool {

			if( ( $appData['/nino/cache/status'] ?? false ) !== true )
				return false;

			// rawMethod as well as method: a HEAD is folded to GET for routing
			// (see Http::_cleanRawMethod()) and answers without a body, which is
			// neither worth storing nor safe to store under a GET's key
			if( ( $request['/nino/http/request']['method'] ?? '' ) !== 'GET' || ( $request['/nino/http/request']['rawMethod'] ?? '' ) !== 'GET' )
				return false;

			// A page is only the same page for everyone if nothing in the query
			// reached it - the locale switch is exactly such a query
			if( ( $request['/nino/http/request']['query'] ?? [] ) !== [] )
				return false;

			// Someone is signed in, so the page may carry their name, their
			// permissions, or an editing affordance nobody else may see
			if( \Nino\Auth::getCurrentUser( $appData ) !== false )
				return false;

			$uri = (string) ( $request['/nino/http/request']['uri'] ?? '' );

			// The tools and the module endpoints, always, whatever the
			// blacklist happens to say
			if( $uri === '' || self::_isTool( $uri ) === true || str_starts_with( $uri, '/.' ) === true )
				return false;

			return self::_blacklisted( (array) ( $appData['/nino/cache/blacklist'] ?? [] ), $uri ) === false;
		}

		/**
		 *	@param		string		$uri
		 *
		 *	@return 	bool										Whether the uri addresses one of Nino's own tools
		 */
		private static function _isTool( string $uri ): bool {

			foreach( self::TOOL_PREFIXES as $prefix )
				if( $uri === $prefix || str_starts_with( $uri, $prefix. '/' ) === true )
					return true;

			return false;
		}

		/**
		 *	Match a uri against the configured blacklist. An entry is either an
		 *	exact uri ('/contact') or a subtree ('/blog/*', which covers /blog
		 *	itself and everything under it). Plain string work on purpose - a
		 *	pattern is admin-editable, and handing admin-editable text to
		 *	preg_match() makes the expression itself an input.
		 *
		 *	@param		array			$patterns
		 *	@param		string		$uri
		 *
		 *	@return 	bool
		 */
		private static function _blacklisted( array $patterns, string $uri ): bool {

			foreach( $patterns as $pattern ) {

				if( is_string( $pattern ) === false || $pattern === '' )
					continue;

				if( str_ends_with( $pattern, '/*' ) === false ) {
					if( $uri === $pattern )
						return true;
					continue;
				}

				$prefix = substr( $pattern, 0, -2 );

				if( $uri === $prefix || str_starts_with( $uri, $prefix. '/' ) === true )
					return true;
			}

			return false;
		}

		/**
		 *	@param		array			$request
		 *
		 *	@return 	string									Storage key: the requested uri and the locale it rendered in
		 */
		private static function _key( array $request ): string {

			// The request uri, not the response's: two routes can resolve to the
			// same page while the page itself still differs by the uri it was
			// asked for (html-header.tpl's canonical link is exactly that).
			// Hashed because a uri is not a filename.
			return sha1( ( $request['/nino/http/request']['uri'] ?? '' ). '|'. ( $request['/nino/http/response']['locale'] ?? '' ) );
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int											Configured lifetime in seconds, one hour if unset or nonsense
		 */
		private static function _ttl( array &$appData ): int {

			$ttl = $appData['/nino/cache/ttl'] ?? null;

			return ( is_int( $ttl ) === true && $ttl > 0 ) ? $ttl : self::DEFAULT_TTL;
		}

		/**
		 *	Drop every stored page. The whole directory rather than a matching
		 *	subset: one edit in a tool can change a textfill every page renders,
		 *	so there is no useful smaller unit, and dropping the lot also keeps
		 *	entries for uris nobody visits again from accumulating.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function _invalidate( array &$appData ): void {

			\Nino\Filesystem::removeDir( \Nino\Filesystem::path( $appData, self::DIR ) );
		}
	}

}
