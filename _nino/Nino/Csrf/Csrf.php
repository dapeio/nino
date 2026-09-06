<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Csrf					Protection for every non-safe request via a session token - required and always active
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Csrf - protection for every non-safe request via a session token,
	// required and always active (unlike the hidden field/shortcode, which
	// stays a module - see Modules\Csrf): a login form the developer forgot
	// to protect isn't a login form with a gap, it's unprotected

	class Csrf {

		// Registers the response-side check that runs for every non-safe
		// request, whether or not the optional Modules\Csrf (the shortcode)
		// is enabled - called unconditionally from \Nino\init(), same as
		// Auth::init()
		public static function init( array &$appData ): void {
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ], 1 );
		}

		// Return the current session's csrf token, creating one if missing
		public static function getToken( array &$appData ): string {

			$token = \Nino\Runtime::getSessionValue( $appData, './nino/csrf/token' );

			if( is_string( $token ) === false || $token === '' ) {
				$token = bin2hex( random_bytes( 32 ) );
				\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', $token );
			}

			return $token;
		}

		// Replace the current session's csrf token with a fresh one, eg.
		// after login/logout to defend against session fixation
		public static function rotateToken( array &$appData ): void {
			\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', bin2hex( random_bytes( 32 ) ) );
		}

		// Reject a POST request with a missing or wrong csrf token. Sets a
		// dedicated './nino/csrf/blocked' flag in addition to the status
		// code, since doCallbacks() always runs every registered callback
		// regardless of outcome - callbacks that run after this one (eg.
		// Auth) must check that flag themselves before acting.
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Every method that isn't safe by definition needs a token, not just
			// POST: _cleanRawMethod() also accepts PUT/DELETE/PATCH and routes
			// can be registered for them, which left those completely
			// unprotected. An unrecognized method ('') stays on the checked
			// side on purpose - it has no business writing anything either.
			if( in_array( $request['/nino/http/request']['method'], [ 'GET', 'HEAD', 'OPTIONS' ], true ) === true )
				return;

			// Opt-out per route, set explicitly in config.php - default stays
			// "on". A public POST endpoint that can't carry a session token
			// (webhook, payment callback, a form posted from another site)
			// needs a deliberate way out, or the only fix left after this
			// runs unconditionally is a silent 403 nobody notices for weeks.
			//
			// A fresh requestRoute() lookup on purpose, not the cheaper
			// $request['/nino/http/response']['csrf'] Http::response() has
			// already merged in: for a POST to a route that doesn't exist,
			// that merged data is the /404 route's, looked up with method
			// 'GET' - a 'csrf' => false set there (say, because the 404 page
			// itself is GET-only and reasonably doesn't need it) would
			// silently wave through every POST to an unregistered uri
			// site-wide. The raw lookup below, on the request's own uri and
			// method, correctly comes back null for anything unmatched.
			//
			// 'method' needs no isset() guard the way 'uri' does: the
			// safe-method check above already dereferenced it unconditionally,
			// so surviving that proves it's set. 'uri' has no such prior use -
			// a hand-built $request missing it (the smoke tests build exactly
			// that) would otherwise crash requestRoute(), which requires a
			// real string and would never terminate its dirname() walk on an
			// empty one.
			$routeData = isset( $request['/nino/http/request']['uri'] ) === true
				? \Nino\Http::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] )
				: null;

			if( ( $routeData['csrf'] ?? true ) === false )
				return;

			$given = self::_extractToken( $request );

			if( $given !== '' && hash_equals( self::getToken( $appData ), $given ) === true )
				return;

			$request['./nino/csrf/blocked']									= true;
			$request['/nino/http/response']['statusCode']	= 403;
			$request['/nino/http/response']['body']					= false;
		}

		// Read the csrf token from wherever the caller put it: the classic
		// hidden form field, the X-CSRF-Token header, or the parsed JSON
		// body - $_POST is always empty for a json request, so relying on
		// it alone 403s every json POST regardless of the token sent.
		private static function _extractToken( array $request ): string {

			if( is_string( $_POST['_csrf'] ?? null ) === true && $_POST['_csrf'] !== '' )
				return $_POST['_csrf'];

			// Via the already-normalized/whitelisted request header, not
			// $_SERVER directly - that's the only path a test can drive
			// (there is no way to fake $_SERVER for a smoke test), and the
			// one every other header read in the kernel already goes through.
			$header = $request['/nino/http/request']['header']['X-CSRF-Token'] ?? '';
			if( is_string( $header ) === true && $header !== '' )
				return $header;

			$body = $request['/nino/http/request']['body'] ?? '';
			if( is_string( $body ) === true && $body !== '' ) {
				$decoded = json_decode( $body, true );
				if( is_array( $decoded ) === true && is_string( $decoded['_csrf'] ?? null ) === true )
					return $decoded['_csrf'];
			}

			return '';
		}
	}
}
