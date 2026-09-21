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
			contact form delivers to. The scripts reading it ask for three groups
			(see Nino.ui.js's .nino-form, .nino-newsletter and .nino-slider
			handlers), so those three are what it carries.

			A project whose own script reads another fill names its prefix under
			'/nino/jstext/keys' in config.php; a module or a feature serving a
			request registers one with publish(). A key under a published prefix
			is public - it is in the source of every page that renders the block	*/
		public const string KEYS = '/nino/jstext/keys';

		private const array DEFAULT_KEYS = [ '/form/info/', '/newsletter/info/', '/slider/label/' ];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// Hex, not base64: the nonce goes into the page twice - raw in the
			// script tag, and json_encoded in the block beside it - and
			// json_encode() escapes a '/' as '\/'. About a third of base64
			// nonces carry one, and \Nino\Modules\Cache::_stamp() then
			// re-stamped only the raw one, leaving the render-time nonce
			// standing in a stored page's json for as long as the entry lived.
			// 16 bytes either way; a nonce is a base64-value token, and hex is
			// a subset of that alphabet
			$appData['./nino/jstext/nonce'] = bin2hex( random_bytes( 16 ) );

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
		 *	Every prefix the inline block carries: the three the shipped scripts
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

			/*	Into the policy's own script-src where it has one, and only
				appended where it has none. Appending unconditionally wrote the
				directive a second time, and a repeated directive is not a merge:
				the first occurrence is the one a browser enforces and every
				later one is ignored. So a route declaring a script-src of its
				own - which routes may, that is what a route's 'header' is for
				(see Http::response()) - left the nonce in a directive nothing
				read, the inline jstext block was refused as an unlisted inline
				script, and Nino.content.getText() answered '' for every key on
				that page. Silently: the page renders, the policy is honoured,
				and only the words are missing.

				'none' is left as it is. It is the one value that means the
				project decided against inline scripts, this block is one, and a
				nonce beside it would not merge with that decision but overturn
				it - 'none' is ignored the moment anything stands next to it. A
				page that wants jstext does not say 'none'	*/
			$policy = trim( $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '', '; ' );
			$nonce  = "'nonce-". $appData['./nino/jstext/nonce']. "'";

			$directives = array_values( array_filter( array_map( 'trim', explode( ';', $policy ) ) ) );
			$merged = false;

			foreach( $directives as $index => $directive ) {

				if( preg_match( '/^script-src(?:\s|$)/i', $directive ) !== 1 )
					continue;

				$merged = true;

				if( preg_match( "/(?:^|\s)'none'(?:\s|$)/i", $directive ) !== 1 )
					$directives[$index] = $directive. ' '. $nonce;

				break;
			}

			if( $merged === false )
				$directives[] = "script-src 'self' ". $nonce;

			$request['/nino/http/response']['header']['Content-Security-Policy'] = implode( '; ', $directives );
		}
	}

}
