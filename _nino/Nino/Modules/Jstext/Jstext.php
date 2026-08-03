<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Jstext				see Nino.php's Modules section for the
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
		 *	Jstext							Provide JS with text translations
		 *
		 *	@package						Dape/Nino
		 *	@author							David Perchermeier <mail@dape.io>
		 *	@link								https://github.com/dapeio/nino
		 */

	class Jstext {

		private static
			$_tpl = [
				'script'	=> '<script nonce="[[nonce]]">NinoJstext=[[content]];</script>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$appData['./nino/jstext/nonce'] = base64_encode(random_bytes(16));

			\Nino\Html::addShortcode( $appData, 'jstext', [ self::class, 'doShortcode' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}


		/**
		 *	Replace shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$fills = ['/nino/jstext/nonce'=>$appData['./nino/jstext/nonce']];
			foreach( \Nino\Html::getFills( $appData ) AS $key => $value )
				$fills[ substr( $key, 2, -2 ) ] = $value;

			return str_replace(
				[
					'[[content]]',
					'[[nonce]]',
				], [
					// The fill values are admin-editable text going straight into an
					// inline <script> block. json_encode()'s default slash escaping
					// happens to neutralize a '</script>' today, but that is a side
					// effect, not a guarantee - one JSON_UNESCAPED_SLASHES away from
					// being a stored xss. JSON_HEX_TAG & co. encode the characters
					// that matter (<>&'") explicitly instead.
					json_encode( $fills, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ),
					$appData['./nino/jstext/nonce'],
				],
				self::$_tpl['script'] );
		}

		/**
		 *	Prepare a http request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$request			Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Append to the seeded default policy (see Http::request()) - never
			// start from an empty string, that would drop default-src & co
			$csp = trim( $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '', '; ' );

			$request['/nino/http/response']['header']['Content-Security-Policy'] = ( $csp === '' ? '' : $csp. '; ' ). "script-src 'self' 'nonce-". $appData['./nino/jstext/nonce'] ."'";
		}
	}

}
