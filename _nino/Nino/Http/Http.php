<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Http					Request/response composition, headers, routing, and locale redirects
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Http - request/response composition, headers, routing, and locale
	// redirects
	class Http {

		private static
			$_defaultResponse = [
				'statusCode'	=> 404,
				'header'			=> [
					'Strict-Transport-Security' 	=> 'max-age=31536000; includeSubDomains',
					// frame-ancestors is what actually stops framing in a current
					// browser - X-Frame-Options below is the legacy fallback, and
					// only DENY/SAMEORIGIN are valid values there ('same-origin'
					// was silently ignored, ie. no clickjacking protection at all).
					// base-uri/form-action are not covered by default-src: without
					// them an injected <base>/<form action> escapes the policy -
					// and textfills (Html::_renderFills()) put admin-editable text
					// into the page unescaped, so the csp is load-bearing here.
					// img-src needs 'data:' spelled out: the '*' source covers
					// network schemes only, so without it the browser refused
					// every data: uri image - including the one Nino's own
					// Nino.css uses for .nino-atf-arrowdown, which therefore
					// rendered as an empty button on every page that has one.
					'Content-Security-Policy' 		=> 'default-src \'self\'; img-src * data:; style-src \'self\' \'unsafe-inline\'; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'',
					'X-Frame-Options' 						=> 'SAMEORIGIN',
					'X-Content-Type-Options'			=> 'nosniff',
				],
				'body'				=> '',
				'uri'					=> '',
			];

		public static function request( array &$appData, array &$request ): void {

			$currentLocale = \Nino\Locales::getCurrentLocale( $appData );
			$header 			= self::_filterRequestHeaderFields( $request );
			$auth 				= self::_getBasicAuthCredentials( $request, $header );

			// Add request/response values
			$request['/nino/http/request'] = [
				'method'				=> self::_cleanRawMethod( $request['REQUEST_METHOD'] ),
				// The method as it came in, before HEAD is folded into GET for
				// routing - output() needs it to send a HEAD response without a
				// body, and it keeps that fold from being invisible to anything
				// else that cares
				'rawMethod'			=> self::_cleanRawMethod( $request['REQUEST_METHOD'], [], false ),
				'uri'						=> self::cleanUri( $request['REQUEST_URI'] ),
				'query'					=> self::_getRequestQueryVarsPart( $request['REQUEST_URI'] ),
				'header'				=> $header,
				'body'					=> file_get_contents( 'php://input' ),
				'user'					=> $auth['user'],
				'pw'						=> $auth['pw'],
				'ip'						=> self::getClientIp(),
			];
			// Seed the response header with the default security headers, so a
			// callback that extends one of them (eg. Jstext appending its
			// nonce to the Content-Security-Policy) works on the real value
			// instead of an empty string that would later shadow the default
			$request['/nino/http/response'] = [
				'uri'					=> $request['/nino/http/request']['uri'],
				'locale'			=> $currentLocale,
				'header'			=> self::$_defaultResponse['header'],
				'body'				=> '',
				'statusCode'	=> 200,
			];

			array_unshift( $appData['./nino/http/requests'], $request );

			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/request', $request );
		}

		public static function response( array &$appData, array &$request ): void {

			// Find current route
			// 'uri', not '.uri': the dot prefix is the convention for runtime
			// keys elsewhere (see Elements), but the response array's own key
			// is the plain one - written with the dot, this last-resort
			// fallback merged in a stray key and left the response uri
			// pointing at the unmatched request path, so every
			// [[/webpage[[/nino/http/response/uri]]/...]] fill on the 404
			// resolved against a page that does not exist
			$routeData = self::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] ) ??
				self::requestRoute( $appData, '/404', 'GET' ) ??
				[ 'uri' => '/404', 'statusCode' => 404 ];

			// A route's own header fields extend the seeded response header
			// (see request()) instead of replacing the whole array - eg.
			// robots.txt's Content-Type must not wipe the security defaults
			if( isset( $routeData['header'] ) === true )
				$routeData['header'] = array_merge( $request['/nino/http/response']['header'], $routeData['header'] );

			$request['/nino/http/response'] = array_merge( $request['/nino/http/response'], $routeData );

			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response', $request );
			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response/'. $request['/nino/http/request']['method']. ':/'. $request['/nino/http/response']['uri'], $request );
		}



		public static function output( array &$appData, array &$request ): never {

			self::_finalizeResponse( $request );

			// Output header
			if( headers_sent() === false )
				foreach( $request['/nino/http/response']['header'] AS $headerKey => $headerValue )
					header( $headerKey. ': '. $headerValue );

			// Send status code
			http_response_code( $request['/nino/http/response']['statusCode'] );

			// Send body - a HEAD response carries the headers of the GET it
			// stands for and nothing else. The request's method has already been
			// folded to GET for routing, so the unmapped value is what decides
			// here (see _cleanRawMethod()). Most sapis would drop the body
			// anyway; not generating it is both cheaper and unambiguous.
			if( ( $request['/nino/http/request']['rawMethod'] ?? '' ) !== 'HEAD' )
				echo $request['/nino/http/response']['body'];

			exit;
		}

		// json-encode a non-string body, then merge in the default status/
		// header/etc. keys - the part of output() that decides what would
		// actually be sent, split out since output() itself exit()s and so
		// can't be called from a test.
		//
		// Not run through filterHeaderFields(): that whitelist is built for
		// the request side (Accept, Cookie, ...) and silently drops
		// response-only headers a module has every right to set (Set-Cookie,
		// Content-Disposition, ETag, ...). Response header values are
		// framework/module-computed, not a direct echo of user input, and
		// header() itself already refuses to send a value containing a CR/LF.
		private static function _finalizeResponse( array &$request ): void {

			// Catch json output
			if( is_string( $request['/nino/http/response']['body'] ) === false ) {
				$request['/nino/http/response']['body'] = json_encode( $request['/nino/http/response']['body'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
				$request['/nino/http/response']['header']['Content-Type'] = 'application/json; charset=utf-8';
			}

			$request['/nino/http/response'] = array_merge( self::$_defaultResponse, $request['/nino/http/response'] );
			$request['/nino/http/response']['header'] = array_merge( self::$_defaultResponse['header'], $request['/nino/http/response']['header'] );
		}

		// Set a response's status code and error body in one call - the
		// statusCode/body pair a handler otherwise sets by hand on every
		// failure branch, with 'error' as the body key enforced rather
		// than just remembered by convention
		public static function fail( array &$request, int $statusCode, string $error ): void {
			$request['/nino/http/response']['statusCode'] = $statusCode;
			$request['/nino/http/response']['body'] = [ 'error' => $error ];
		}

		// Set a response's success body - see fail()
		public static function ok( array &$request, mixed $body = [ 'ok' => true ] ): void {
			$request['/nino/http/response']['body'] = $body;
		}

		public static function requestRoute( array &$appData, string $uri, string $method ): ?array {

			// Only normalized absolute request paths are routable. dirname('')
			// and dirname('relative') both stabilize at '.', which made the
			// parent walk below loop forever for a malformed direct call.
			if( $uri === '' || str_starts_with( $uri, '/' ) === false )
				return null;

			$result 		= $appData['/nino/http/routes'][$method. ':/'. $uri] ?? null;
			$parentUri	= $uri;

			while( $parentUri !== '/' && $result === null ) {
				$nextParent = dirname( $parentUri );
				if( $nextParent === '.' || $nextParent === $parentUri )
					break;
				$parentUri	= $nextParent;
				$result 		= $appData['/nino/http/routes'][$method. ':/'. $parentUri.'/*'] ?? null;
			}

			return $result;
		}

		// Find the route key (eg. "GET://about") that renders a given
		// response uri in a specific locale, eg. to redirect to the
		// locale-specific variant of the current page
		public static function findRouteUri( array &$appData, string $responseUri, string $locale ): ?string {

			// '??' like every other route read in this file: a route entry
			// without a 'uri' (hand-edited config.php, a module registering
			// only a body) raised an "Undefined array key" here, and
			// Runtime::handleError() treats an engine-raised level as fatal -
			// so one typo in the config turned every locale switch into a 500
			foreach( $appData['/nino/http/routes'] as $routeUri => $routeData )
				if( ( $routeData['uri'] ?? null ) === $responseUri && ( isset( $routeData['locale'] ) === false || $routeData['locale'] === $locale ) )
					return $routeUri;

			return null;
		}

		public static function getRequest( array &$appData, int $offset = 0 ): array|false {
			return $appData['./nino/http/requests'][$offset] ?? false;
		}

		// Return clean uri
		static private function cleanUri( string $rawUri ): string {

			// Clean uri
			$cleanUri = strtok( $rawUri, '#' );
			$cleanUri = strtok( $cleanUri, '?' );
			$cleanUri = preg_replace( '/[^a-zA-Z0-9:\/\.~_\-%]/', '', $cleanUri );
			$cleanUri = rtrim( $cleanUri, '/' );

			if( $cleanUri === '' )
				$cleanUri = '/';

			return $cleanUri;
		}

		// Return current client ip address. Only the actual TCP peer address
		// (REMOTE_ADDR) is trusted - Client-Ip/X-Forwarded-For are ordinary
		// request headers any client can set to an arbitrary value, and Nino
		// has no trusted-proxy configuration to verify them against. Trusting
		// them here would let a session's ip-pinning check (see Auth) and the
		// activity log's ip field both be spoofed by the request itself.
		//
		// Behind a reverse proxy (Cloudflare, a load balancer, ...) this is
		// necessarily the proxy's own address for every single visitor, not
		// theirs - REMOTE_ADDR simply has no other value to be. Mail::_hit()'s
		// per-ip send cap is the one place that currently matters: it becomes
		// a per-site cap instead, and five contact-form submissions from
		// anyone lock out every visitor's mail (newsletter confirmations
		// included) for the rest of the window, silently, since a rate-limit
		// refusal is not surfaced as an error. A trusted-proxy allowlist
		// (only trust X-Forwarded-For's rightmost hop when REMOTE_ADDR itself
		// is a known proxy) would fix this properly but isn't implemented -
		// this comment exists so that gap gets found in the source, not at
		// the mail server, if it ever bites.
		public static function getClientIp(): string {

			return $_SERVER['REMOTE_ADDR'] ?? '';
		}

		// PHP_AUTH_USER/PHP_AUTH_PW are populated by some SAPIs, while CGI and
		// FastCGI commonly expose only HTTP_AUTHORIZATION after the web server
		// has been configured to pass it through. Prefer PHP's parsed values for
		// backwards compatibility, then decode one strict Basic credential pair
		// from the already normalized request header. A password may contain a
		// colon, so split only at the first one.
		private static function _getBasicAuthCredentials( array $rawServer, array $header ): array {

			if( array_key_exists( 'PHP_AUTH_USER', $rawServer ) === true || array_key_exists( 'PHP_AUTH_PW', $rawServer ) === true )
				return [
					'user' 	=> is_string( $rawServer['PHP_AUTH_USER'] ?? null ) ? $rawServer['PHP_AUTH_USER'] : '',
					'pw' 		=> is_string( $rawServer['PHP_AUTH_PW'] ?? null ) ? $rawServer['PHP_AUTH_PW'] : '',
				];

			$authorization = $header['Authorization'] ?? '';
			if( is_string( $authorization ) === false || preg_match( '/^Basic[ \t]+([A-Za-z0-9+\/]+={0,2})$/iD', trim( $authorization ), $matches ) !== 1 )
				return [ 'user' => '', 'pw' => '' ];

			$decoded = base64_decode( $matches[1], true );
			if( $decoded === false || str_contains( $decoded, ':' ) === false )
				return [ 'user' => '', 'pw' => '' ];

			[ $user, $pw ] = explode( ':', $decoded, 2 );

			return [ 'user' => $user, 'pw' => $pw ];
		}

		// Build the request-side header array from $_SERVER (passed in as
		// $rawServer). PHP exposes request headers as HTTP_FOO_BAR keys
		// (Content-Type/-Length are the CGI-spec exception, without the
		// HTTP_ prefix) - filterHeaderFields() expects real header names
		// like 'Foo-Bar' as keys, which is what the response side already
		// has, so this normalizes the request side to match before reusing
		// it. The exact case reached here doesn't matter - filterHeaderFields()
		// now matches case-insensitively and returns its own whitelist's
		// casing, since header names are case-insensitive per HTTP anyway.
		static private function _filterRequestHeaderFields( array $rawServer ): array {

			$normalized = [];

			foreach( $rawServer as $key => $value ) {

				if( str_starts_with( $key, 'HTTP_' ) === true )
					$headerName = substr( $key, 5 );
				elseif( in_array( $key, [ 'CONTENT_TYPE', 'CONTENT_LENGTH' ], true ) === true )
					$headerName = $key;
				else
					continue;

				$normalized[str_replace( '_', '-', $headerName )] = $value;
			}

			return self::filterHeaderFields( $normalized );
		}

		// Filter all non-http keys from an array. Matches case-insensitively
		// and normalizes to the whitelist's own casing - a naive exact match
		// would silently drop a header whose casing doesn't match the
		// whitelist's literal entry (eg. the request side's 'TE' normalized
		// to 'Te' via ucwords()).
		static public function filterHeaderFields( array $headerArray ): array {

			$allowed = [ 'Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language', 'Authorization', 'Cache-Control', 'Connection', 'Content-Length', 'Content-Type', 'Cookie', 'Date', 'Expect', 'From', 'Host', 'If-Modified-Since', 'If-None-Match', 'Location', 'Max-Forwards', 'Origin', 'Pragma', 'Proxy-Authorization', 'Range', 'Referer', 'TE', 'User-Agent', 'Upgrade', 'Via', 'Warning', 'X-CSRF-Token', 'X-Frame-Options', 'X-Content-Type-Options', 'Strict-Transport-Security', 'Content-Security-Policy', 'Referrer-Policy', 'Feature-Policy', 'Permissions-Policy' ];

			$filteredArray = [];

			foreach( $headerArray as $key => $value )
				foreach( $allowed as $canonical )
					if( strcasecmp( $key, $canonical ) === 0 ) {
						$filteredArray[$canonical] = $value;
						break;
					}

			return $filteredArray;
		}


		private static function _getRequestQueryVarsPart( string $rawRequest ): array {

			// Parse query vars
			$queryVars = [];
			$parsedUrl = @parse_url( $rawRequest, PHP_URL_QUERY );
			if( is_string( $parsedUrl ) === false || $parsedUrl === '' )
				return $queryVars;
			parse_str( $parsedUrl, $queryVars );

			return $queryVars;
		}


		// Clean request methods
		static private function _cleanRawMethod( string $rawMethod, array $legalMethods = [], bool $mapHead = true ): string {

			if( $legalMethods === [] )
				$legalMethods = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH' ];

			$cleanMethod	= preg_replace( '/[^a-zA-Z]/', '', $rawMethod );
			$cleanMethod	= strtoupper( $cleanMethod );

			// Exact match rather than a substring test - 'XXGETXX' used to be
			// accepted as GET, since any legal method contained anywhere in the
			// value won
			if( in_array( $cleanMethod, $legalMethods, true ) === false )
				return '';

			// HEAD is a GET without a response body, but routes are only ever
			// registered for GET - so every HEAD request (uptime monitors, link
			// checkers, some crawlers) found no route and answered 404. The
			// unmapped value stays available as the request's 'rawMethod', which
			// is what output() suppresses the body by.
			return ( $mapHead === true && $cleanMethod === 'HEAD' ) ? 'GET' : $cleanMethod;
		}
	}
}
