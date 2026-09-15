<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Jstext				see _nino/Nino/Modules/Modules.php for the
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

		/*	Which text keys the inline block carries. It used to carry every
			fill the site has, on every page that renders [jstext] - the legal
			copy, the addresses, and '/form/email/owner', which is the mailbox a
			contact form delivers to. The scripts reading it ask for two groups
			(see Nino.ui.js's .nino-form and .nino-newsletter handlers), so those
			two are what it carries.

			A project whose own script reads another fill names its prefix under
			'/nino/jstext/keys' in config.php; a module or a feature serving a
			request registers one with publish(). A key under a published prefix
			is public - it is in the source of every page that renders the block	*/
		public const string KEYS = '/nino/jstext/keys';

		private const array DEFAULT_KEYS = [ '/form/info/', '/newsletter/info/' ];

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

			// Priority 0, ahead of everything that can end a request from inside
			// this same callback: Modules\Maintenance answers at 1 and exits
			// there, so a maintenance page - which renders the site's own footer,
			// and with it [jstext] - shipped an inline script the policy then
			// refused, because the policy naming its nonce was never composed
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ], 0 );
		}

		/**
		 *	Publish one or more text key prefixes to the inline block for this
		 *	request - what a module or a feature calls in its own init() when
		 *	its public script reads a fill. The workbench does it for its own
		 *	words; a project does the same thing in config.php under KEYS
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$prefixes			Key prefixes, '/mine/info/' style
		 *
		 *	@return 	void
		 */
		public static function publish( array &$appData, array $prefixes ): void {

			$published = is_array( $appData['./nino/jstext/keys'] ?? null ) === true ? $appData['./nino/jstext/keys'] : [];

			$appData['./nino/jstext/keys'] = array_values( array_unique( array_merge( $published, array_values( array_filter( $prefixes, 'is_string' ) ) ) ) );
		}

		/**
		 *	Every prefix the inline block carries: the two the shipped scripts
		 *	read, what config.php adds, and what this request registered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Key prefixes
		 */
		public static function keys( array &$appData ): array {

			$configured	= is_array( $appData[ self::KEYS ] ?? null ) === true ? $appData[ self::KEYS ] : [];
			$published	= is_array( $appData['./nino/jstext/keys'] ?? null ) === true ? $appData['./nino/jstext/keys'] : [];

			return array_values( array_unique( array_merge(
				self::DEFAULT_KEYS,
				array_values( array_filter( $configured, 'is_string' ) ),
				array_values( array_filter( $published, 'is_string' ) )
			) ) );
		}


		/**
		 *	Replace shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$keys		= self::keys( $appData );
			$fills	= ['/nino/jstext/nonce'=>$appData['./nino/jstext/nonce']];

			foreach( \Nino\Html::getFills( $appData ) AS $key => $value ) {

				$key = substr( $key, 2, -2 );

				foreach( $keys as $prefix )
					if( str_starts_with( $key, $prefix ) === true ) {
						$fills[$key] = $value;
						break;
					}
			}

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
