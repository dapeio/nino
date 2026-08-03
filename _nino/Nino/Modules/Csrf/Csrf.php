<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Csrf				see Nino.php's Modules section for the
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
	 *	Csrf								Cross-site-request-forgery protection
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Csrf {

		/**
		 *	Module initiating. Registered at priority 1 so the check runs
		 *	before any other global POST handler (eg. Auth's login/logout).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'csrf', [ self::class, 'doShortcode' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ], 1 );
		}

		/**
		 *	Return the current session's csrf token, creating one if missing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Current csrf token
		 */
		public static function getToken( array &$appData ): string {

			$token = \Nino\Runtime::getSessionValue( $appData, './nino/csrf/token' );

			if( is_string( $token ) === false || $token === '' ) {
				$token = bin2hex( random_bytes( 32 ) );
				\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', $token );
			}

			return $token;
		}

		/**
		 *	Replace the current session's csrf token with a fresh one, eg.
		 *	after login/logout to defend against session fixation
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function rotateToken( array &$appData ): void {
			\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', bin2hex( random_bytes( 32 ) ) );
		}

		/**
		 *	Reject a POST request with a missing or wrong csrf token. Sets a
		 *	dedicated './nino/csrf/blocked' flag in addition to the status
		 *	code, since doCallbacks() always runs every registered callback
		 *	regardless of outcome - callbacks that run after this one (eg.
		 *	Auth) must check that flag themselves before acting.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Every method that isn't safe by definition needs a token, not just
			// POST: _cleanRawMethod() also accepts PUT/DELETE/PATCH and routes
			// can be registered for them, which left those completely
			// unprotected. An unrecognized method ('') stays on the checked
			// side on purpose - it has no business writing anything either.
			if( in_array( $request['/nino/http/request']['method'], [ 'GET', 'HEAD', 'OPTIONS' ], true ) === true )
				return;

			$given = self::_extractToken( $request );

			if( $given !== '' && hash_equals( self::getToken( $appData ), $given ) === true )
				return;

			$request['./nino/csrf/blocked']									= true;
			$request['/nino/http/response']['statusCode']	= 403;
			$request['/nino/http/response']['body']					= false;
		}

		/**
		 *	Read the csrf token from wherever the caller put it: the classic
		 *	hidden form field, the X-CSRF-Token header, or the parsed JSON
		 *	body - $_POST is always empty for a json request, so relying on
		 *	it alone 403s every json POST regardless of the token sent.
		 *
		 *	@param		array 		$request			Current server request
		 *
		 *	@return 	string									Token, or '' if none was found
		 */
		private static function _extractToken( array $request ): string {

			if( is_string( $_POST['_csrf'] ?? null ) === true && $_POST['_csrf'] !== '' )
				return $_POST['_csrf'];

			$header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
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

		/**
		 *	Replace shortcode with a hidden csrf input field
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string									Hidden input html
		 */
		public static function doShortcode( array &$appData, array $args ): string {
			return '<input type="hidden" name="_csrf" value="'. htmlspecialchars( self::getToken( $appData ), ENT_QUOTES, 'UTF-8' ). '">';
		}
	}

}
