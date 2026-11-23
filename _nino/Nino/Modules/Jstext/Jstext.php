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

		/*	Which text keys the inline block carries: not every fill the site
			has - the legal copy, the addresses, and '/project/mail/address/owner', which is
			the mailbox a contact form delivers to, are no business of a script - but the
			prefixes config.php names under '/nino/jstext/keys' and the ones a module or a
			feature serving the request registers with publish(). The Form module does for
			a page that draws a form (Modules\Form::draws(): the words form.js reads for a
			.nino-form and for the Newsletter feature's signup), so a page without a form
			carries none of them. The words of a form that names its own prefix in
			data-form-texts are the feature's to publish().

			A project whose own script reads another fill names its prefix in config.php.
			A key under a published prefix is public - it is in the source of every page
			that renders the block	*/
		public const string KEYS = '/nino/jstext/keys';

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// 16 random bytes as hex. A nonce is a base64-value token and hex
			// is a subset of that alphabet - one with no character html,
			// json_encode() or a url escapes, so the nonce reads the same
			// wherever it is written, and \Nino\Modules\Cache::_stamp(), which
			// swaps it by its raw string, finds every copy of it
			$appData['./nino/jstext/nonce'] = bin2hex( random_bytes( 16 ) );

			// The block is written when the page is finished, so that a word a form publishes while it is drawn is in it
			// wherever the page puts [jstext]: the shortcode leaves a mark, callbackRender() puts the block in its place
			\Nino\Html::addShortcode( $appData, 'jstext', [ self::class, 'doMark' ], false );
			\Nino\Callbacks::registerCallback( $appData, '/nino/html/render', [ self::class, 'callbackRender' ] );

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
		 *	words; a project does the same thing in config.php under KEYS.
		 *	A prefix starts and ends with a slash with a name between; any other
		 *	is refused with a warning and nothing is published for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$prefixes			Key prefixes, '/project/mine/info/' style
		 *
		 *	@return 	void
		 */
		public static function publish( array &$appData, array $prefixes ): void {

			$published = is_array( $appData['./nino/jstext/keys'] ?? null ) === true ? $appData['./nino/jstext/keys'] : [];

			// A prefix names a group of keys: it starts and ends with a slash and has a name between.
			// '' and '/' would hand the whole table to every visitor, and a prefix without the closing
			// slash reaches the keys of every neighbour that starts with the same letters
			$allowed = [];

			foreach( $prefixes as $prefix ) {

				if( is_string( $prefix ) === true && preg_match( '#^/[^/].*/$#D', $prefix ) === 1 )
					$allowed[] = $prefix;
				else
					trigger_error( 'Jstext::publish(): "'. ( is_string( $prefix ) === true ? $prefix : gettype( $prefix ) ). '" is not a key prefix (it starts and ends with a slash, a name between) - nothing is published for it', E_USER_WARNING );
			}

			$appData['./nino/jstext/keys'] = array_values( array_unique( array_merge( $published, $allowed ) ) );
		}

		/**
		 *	Every prefix the inline block carries: what config.php names and what
		 *	this request registered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Key prefixes
		 */
		public static function keys( array &$appData ): array {

			$configured	= is_array( $appData[ self::KEYS ] ?? null ) === true ? $appData[ self::KEYS ] : [];
			$published	= is_array( $appData['./nino/jstext/keys'] ?? null ) === true ? $appData['./nino/jstext/keys'] : [];

			return array_values( array_unique( array_merge(
				array_values( array_filter( $configured, 'is_string' ) ),
				array_values( array_filter( $published, 'is_string' ) )
			) ) );
		}


		/**
		 *	The mark that stands where the block will be: a comment with a token of this request, made from random bytes so
		 *	that no text of a page can write one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string
		 */
		public static function doMark( array &$appData, array $args ): string {

			if( ( $appData['./nino/jstext/mark'] ?? '' ) === '' )
				$appData['./nino/jstext/mark'] = bin2hex( random_bytes( 8 ) );

			return '<!--nino-jstext-'. $appData['./nino/jstext/mark']. '-->';
		}

		/**
		 *	Finish the page: the block stands where the marks are, with the words published until now
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$html					The finished page
		 *
		 *	@return 	string
		 */
		public static function callbackRender( array &$appData, string $html ): string {

			$mark = '<!--nino-jstext-'. ( $appData['./nino/jstext/mark'] ?? '' ). '-->';

			if( $mark === '<!--nino-jstext--->' || str_contains( $html, $mark ) === false )
				return $html;

			return str_replace( $mark, self::doShortcode( $appData, [] ), $html );
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
			$fills	= [];

			foreach( array_keys( \Nino\Html::getFills( $appData ) ) AS $key ) {

				$key = substr( $key, 2, -2 );

				foreach( $keys as $prefix )
					if( str_starts_with( $key, $prefix ) === true ) {

						/*	The script writes the text with textContent, so it needs the
							text and not its entities: a plain value is stored with its
							quotes and brackets as entities (\Nino\Text::sanitizeValue()),
							and 'couldn&#039;t' is what a visitor would read. It is
							resolved as the page resolves it, a fill inside it filled in,
							and decoded once, as Components::value() does for a plain
							text. The block is a shortcode's output and is finished,
							and the brackets of the decoded text are written as
							the JSON below has them, where a '[' is no fill and no
							shortcode	*/
						$text = \Nino\Html::resolveTextfill( $appData, $key );

						if( $text !== null )
							$fills[$key] = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

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
					// that matter (<>&'") explicitly instead; U+2028 and U+2029 are
					// escaped by json_encode() itself, and a byte that is no UTF-8
					// is a replacement character rather than a json_encode() that
					// fails; an empty table is written as an object, since '[]' with
					// its brackets escaped would be no script.
					str_replace( [ '[', ']' ], [ '\u005B', '\u005D' ], json_encode( $fills, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_FORCE_OBJECT ) ),
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
