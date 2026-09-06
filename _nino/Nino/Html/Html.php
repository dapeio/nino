<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Html					The html rendering pipeline: textfills, shortcodes, asset lists, and html sanitizing
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Html - the html rendering pipeline: textfills, shortcodes, asset
	// lists, and html sanitizing
	class Html {

		private const array HTML_TAGS = [ 'strong', 'em', 'span', 'code', 'a' ];

		// How deep shortcode output may be re-rendered into more shortcodes
		// before _doShortcode() stops unrolling - see there
		private const int MAX_RENDER_DEPTH = 20;

		// The shortcodes the kernel provides itself. Called unconditionally
		// from \Nino\init(), same as Csrf::init() - the base templates use
		// [json] in their schema.org block, so it has to be there whichever
		// optional modules a project has enabled (or removed).
		public static function init( array &$appData ): void {

			self::addShortcode( $appData, 'json', [ self::class, 'doJsonShortcode' ] );
		}

		public static function response( array &$appData, array &$request ): void {
			if( is_string( $request['/nino/http/response']['body'] ) === true )
				$request['/nino/http/response']['body'] = self::renderHtml( $appData, $request['/nino/http/response']['body'] );
		}

		/**
		 *	[json /company/adress] - one textfill as a complete json string
		 *	literal, surrounding quotes included, for a json document a
		 *	template writes by hand. The schema.org block in
		 *	html-header.tpl is the reason it exists: a fill goes into the
		 *	page verbatim (see _renderFills()), and '/company/adress' is
		 *	multi-line by design - it renders as a postal address and
		 *	the wizard's own PersonalInfos step offers it as a <textarea>. A
		 *	raw newline inside a json string is not valid json, so that
		 *	block failed to parse on every page of every install. A quote
		 *	or a backslash in any of the other values does the same.
		 *
		 *	Same reasoning and the same flags as Modules\Jstext, which
		 *	json-encodes fills for exactly this reason before writing them
		 *	into an inline <script>: the JSON_HEX_* set encodes the
		 *	characters that could end the surrounding element ('<', '&',
		 *	quotes) explicitly rather than relying on slash escaping to
		 *	neutralize a '</script>' as a side effect.
		 *
		 *	Nested fills are resolved before encoding, so a value that
		 *	references another one ('[[/website/url]]' inside a subject
		 *	line, say) still comes out as its final text - and the
		 *	re-render Html::_doShortcode() runs on this return value then
		 *	has nothing left to substitute back into the encoded string.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments, $args[0] is the fill key
		 *
		 *	@return 	string									A json string literal, '""' for an unknown key
		 */
		public static function doJsonShortcode( array &$appData, array $args ): string {

			$value = self::renderTextfill( $appData, (string) ( $args[0] ?? '' ) );
			$value = self::_renderFills( $appData, $value );

			return (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		}

		public static function renderHtml( array &$appData, string $html ): string {

			$html = self::_renderFills( $appData, $html );
			$html = self::_renderShortcodes( $appData, $html );

			$html = \Nino\Callbacks::doCallbacks( $appData, '/nino/html/render', $html );

			return $html;
		}

		public static function renderTextfill( array &$appData, string $fill ): string {

			$fills = self::getFills( $appData );

			return $fills[ '[['. $fill. ']]'] ?? '';
		}

		public static function addAsset( array &$appData, string $library, string $assetfile ): void {

			$appData['/nino/html/assets'][$library] = $appData['/nino/html/assets'][$library] ?? [];

			if( in_array( $assetfile, $appData['/nino/html/assets'][$library] ) === false )
				$appData['/nino/html/assets'][$library][] = $assetfile;
		}

		public static function addShortcode( array &$appData, string $shortcode, mixed $callback ): void {

			// Add shortcode in appData
			$appData['./nino/html/shortcodes'][$shortcode] = $shortcode;

			// Add callback and clear cache
			\Nino\Callbacks::registerCallback( $appData, '/nino/html/shortcode/'. $shortcode, $callback );
			$appData['./nino/html/cache'] = false;
		}

		public static function addFills( array &$appData, array $fills, string $locale = ''  ): void {

			// Check locale
			if( $locale === '' )
				$locale = \Nino\Locales::getCurrentLocale( $appData );
			else if( $locale !== '*' && \Nino\Locales::verifyLocale( $appData, $locale ) === false )
				return;

			$appData['./nino/html/fills'][$locale] = $appData['./nino/html/fills'][$locale] ?? [];

			foreach( $fills as $fillKey => $fillValue )
				$appData['./nino/html/fills'][$locale]['[['. trim( $fillKey, '[[]]' ). ']]'] = $fillValue;
		}

		public static function getAssets( array &$appData, string $library ): array {
			return $appData['/nino/html/assets'][$library] ?? [];
		}

		public static function getFills( array &$appData ): array {

			$locale = \Nino\Locales::getCurrentLocale( $appData );

			return array_merge(
				\Nino\Filesystem::getFileContent( $appData, $appData['/nino/locales/textfiles']. '/global.php', [] ),
				\Nino\Filesystem::getFileContent( $appData, $appData['/nino/locales/textfiles']. '/'. $locale. '.php', [] ),
				( $appData['./nino/html/fills'][$locale] ?? [] ),
				( $appData['./nino/html/fills']['*'] ?? [] )
			);
		}

		private static function _renderShortcodes( array &$appData, string $html ): string {

			// Check html text for [ or [[
			if( $html === '' || strpos( $html, '[' ) === false || empty( $appData['./nino/html/shortcodes'] ) === true )
				return $html;

			// Run shortcodes
			if( $appData['./nino/html/cache'] === false )
				$appData['./nino/html/cache'] = implode( '|', array_keys( ( $appData['./nino/html/shortcodes'] ?? [] ) ) );

			// A closure capturing $appData by reference, not the previous
			// static-property-as-callback-target approach: preg_replace_callback()
			// only accepts a callable, but binding $appData to a class property
			// so the static _doShortcode() could reach it meant any addAsset()/
			// addFills()/addShortcode() call a shortcode's own callback made
			// was silently lost - the property held a copy, not the caller's
			// actual array. The closure sidesteps that entirely: it's a real
			// reference to $appData, exactly like passing it as a parameter
			// anywhere else in this file.
			$callback = function( array $pregArgs ) use ( &$appData ): string {
				return self::_doShortcode( $appData, $pregArgs );
			};
			$html = preg_replace_callback( '/\[('. $appData['./nino/html/cache'] . ')(?: ([^\]]*))?\](?:([^\[]*+(?:\[(?!\/\1\])[^\[]*+)*+)(?:\[\/(?:\1)\]))?/', $callback, $html );

			return $html;
		}

		private static function _renderFills( array &$appData, string $html ): string {

			if( substr_count( $html, '[[' ) === 0 )
				return $html;

			$fills			= self::getFills( $appData );
			$fillKeys		= array_keys( $fills );
			$fillValues	= array_values( $fills );

			// Comparing the rendered string itself (not just its '[[' count)
			// catches fill values that reference another fill of their own
			// (eg. /form/subject/owner containing [[/website/url]]) - a
			// same-count swap would otherwise look "stable" after one pass.
			// The pass cap guards against a fill value that references
			// itself (possible via the Text panel, not just the
			// developer-authored defaults).
			for( $pass = 0; $pass < 10; $pass++ ) {
				$rendered = str_replace( $fillKeys, $fillValues, $html );
				if( $rendered === $html )
					break;
				$html = $rendered;
			}

			return $html;
		}

		private static function _doShortcode( array &$appData, array $pregArgs ): string {

			// Read preg arguments
			$shortcode	= $pregArgs[1];
			$content		= $pregArgs[3] ?? '';

			// Check shortcode
			if( isset( $appData['./nino/html/shortcodes'][$shortcode] ) === false )
				return '';

			// Split shortcode arguments
			$args = [];
			if( isset( $pregArgs[2] ) === true && $pregArgs[2] !== '' ) {
				preg_match_all( '/\ ([^\ \=]*)(?:\=[\"]([^\"]*)[\"])?/i', ' '.$pregArgs[2].' ', $attr );

				foreach( $attr[1] AS $id => $key ) {

					$value = ( $attr[2][$id] !== '' ) ? str_replace( '\'', '"', $attr[2][$id] ) : $attr[1][$id];

					if( $value === $key )
						$args[] 		= substr( $value, 0 );
					else
						$args[$key] = $value;
				}

				array_pop( $args );
			}

			// Modify arguments
			if( strlen( $content ) > 0 )
				$args['content'] = $content;

			$value = \Nino\Callbacks::doCallbacks( $appData, '/nino/html/shortcode/'. $shortcode, $args );

			if( is_string( $value ) === false )
				return '';

			// A shortcode's output is rendered again (its own fills/shortcodes),
			// which is what makes [template] able to contain other shortcodes -
			// and also what makes a template including itself, directly or
			// through a second one, recurse until the memory limit kills the
			// request. Templates and textfills are editable from the workbench, so
			// that is one typo away. The depth cap stops the recursion without
			// putting a rule on any single shortcode; MAX_RENDER_DEPTH is far
			// above what real nesting (page -> section -> element) reaches.
			$depth = $appData['./nino/html/depth'] ?? 0;

			if( $depth >= self::MAX_RENDER_DEPTH )
				return $value;

			$appData['./nino/html/depth'] = $depth + 1;
			$value = self::renderHtml( $appData, $value );
			$appData['./nino/html/depth'] = $depth;

			return $value;
		}

		// Whether a value currently contains one of the allowed inline tags -
		// used to auto-decide whether a field/key gets the html editor.
		// Shared by every domain class with a model/entry 'html' flag
		// (the Text, Text Keys and Elements panels).
		public static function containsHtml( string $value ): bool {
			return preg_match( '/<(?:'. implode( '|', self::HTML_TAGS ). ')[ >]/i', $value ) === 1;
		}

		// Rebuild a html value, keeping only whitelisted inline tags (strong/
		// em/span/code/a) one level deep and a safe href scheme on links. Never
		// trust the client's html: the editor's "no nesting" toolbar rule is
		// enforced here too, against a client that bypasses it entirely.
		public static function sanitizeHtml( string $html ): string {

			if( trim( $html ) === '' )
				return '';

			$doc = new \DOMDocument();

			libxml_use_internal_errors( true );
			$doc->loadHTML( '<?xml encoding="utf-8"?><div>'. $html. '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
			libxml_clear_errors();

			$wrap = $doc->getElementsByTagName( 'div' )->item( 0 );

			return $wrap === null ? '' : self::_sanitizeChildren( $wrap, false );
		}

		// Recursively rebuild a node's children, keeping only whitelisted
		// inline tags one level deep - a whitelisted tag found while already
		// inside another one is unwrapped (kept as plain content)
		private static function _sanitizeChildren( \DOMNode $node, bool $insideInline ): string {

			$out = '';

			foreach( iterator_to_array( $node->childNodes ) as $child ) {

				if( $child->nodeType === XML_TEXT_NODE ) {
					// ENT_NOQUOTES: this is text content, not an attribute value - quotes need no escaping here
					$out .= htmlspecialchars( $child->textContent, ENT_NOQUOTES, 'UTF-8' );
					continue;
				}

				if( $child->nodeType !== XML_ELEMENT_NODE )
					continue;

				$tag = strtolower( $child->nodeName );

				if( in_array( $tag, self::HTML_TAGS, true ) === false || $insideInline === true ) {
					$out .= self::_sanitizeChildren( $child, $insideInline );
					continue;
				}

				$inner = self::_sanitizeChildren( $child, true );

				if( $tag === 'a' ) {
					$href = self::_safeHref( $child->getAttribute( 'href' ) );
					$out .= ( $href === null ) ? $inner : '<a href="'. htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' ). '">'. $inner. '</a>';
					continue;
				}

				$out .= '<'. $tag. '>'. $inner. '</'. $tag. '>';
			}

			return $out;
		}

		// Validate a link href: only relative/fragment uris or a handful of
		// safe schemes - blocks javascript: and similar injection vectors
		private static function _safeHref( string $href ): string|null {

			$href = trim( $href );

			if( $href === '' )
				return null;


			if( $href[0] === '#' )
				return $href;

			// '//evil.com' (and '/\evil.com', which browsers normalise to the
			// same thing) are protocol-relative, ie. off-site - a leading slash
			// alone is not enough to call an href relative
			if( $href[0] === '/' && ( $href[1] ?? '' ) !== '/' && ( $href[1] ?? '' ) !== '\\' )
				return $href;

			return preg_match( '#^(https?|mailto|tel):#i', $href ) === 1 ? $href : null;
		}
	}
}
