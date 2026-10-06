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

		// The formats a stored value can be in, from the narrowest to the
		// widest: plain text, the inline tags above, those plus line breaks,
		// and paragraphs and lists around them. A format only ever says what a
		// value may contain - sanitizeHtml() enforces it, the editors offer
		// what it allows, and Text and Elements keep it beside the value
		public const array FORMATS = [ 'plain', 'inline', 'lines', 'blocks' ];

		// Tags that end one run of text and start the next. A sanitizer that
		// unwraps one without a trace glues the words on either side
		// together ('Grill.' + 'Second' read 'Grill.Second')
		private const array BOUNDARY_TAGS = [ 'p', 'div', 'li', 'ul', 'ol', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'tr', 'section', 'article' ];

		// What the blocks format turns into a paragraph of its own
		private const array PARAGRAPH_TAGS = [ 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' ];

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
		 *	[json /project/company/contact/address] - one textfill as a complete
		 *	json string literal, surrounding quotes included, for a json
		 *	document a template writes by hand. The schema.org block in
		 *	html-header.tpl is the reason it exists: a fill goes into the
		 *	page verbatim (see _renderFills()), and
		 *	'/project/company/contact/address' is multi-line by design - it
		 *	renders as a postal address and the wizard's own PersonalInfos
		 *	step offers it as a <textarea>. A
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
		 *	Nested fills are resolved before encoding (see resolveTextfill()), so
		 *	a value that references another one ('[[/project/website/general/url]]'
		 *	inside a subject line, say) still comes out as its final text - and
		 *	the re-render Html::_doShortcode() runs on this return value then has
		 *	nothing left to substitute back into the encoded string.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments, $args[0] is the fill key
		 *
		 *	@return 	string									A json string literal, '""' for an unknown key
		 */
		public static function doJsonShortcode( array &$appData, array $args ): string {

			$value = self::resolveTextfill( $appData, (string) ( $args[0] ?? '' ) ) ?? '';

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

		/**
		 *	What [[<key>]] would put into a page here, in the current language:
		 *	the value of the key with every fill inside it resolved - the same
		 *	passes _renderFills() makes, at most ten - and nothing else. No
		 *	shortcode is run and nothing is escaped; that is the caller's
		 *	business, since it knows where the value is going. Where renderTextfill()
		 *	answers '' for a key with no value, this answers null, so a caller
		 *	can tell a key nobody wrote from one that is empty - [json] and
		 *	Modules\Legal's placeholders both ask it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The key without brackets, eg. '/project/company/contact/email'
		 *
		 *	@return 	string|null							The resolved value, null for a key with no value
		 */
		public static function resolveTextfill( array &$appData, string $key ): ?string {

			$fills = self::getFills( $appData );
			$value = $fills[ '[['. $key. ']]'] ?? null;

			return is_string( $value ) === true ? self::_renderFills( $appData, $value ) : null;
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

		/**
		 *	The shortcodes registered so far, by name - read only
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										The names, in registration order
		 */
		public static function shortcodes( array &$appData ): array {

			return array_map( 'strval', array_keys( (array) ( $appData['./nino/html/shortcodes'] ?? [] ) ) );
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

		// The fills the kernel registers at runtime rather than reading from a
		// text file, stated once, here: bootFills() at the start of every
		// request, before the response round (the mail templates Modules\Form
		// and Newsletter render in that window need /nino/public), and
		// requestFills() once the response is known - by \Nino\request(),
		// and again by Modules\Maintenance, whose page wears the site's
		// header. runtimeFillKeys() is the same list by name, for whoever has
		// to know which placeholders never get a text value: the Text panel's
		// scan for missing keys used to keep its own copy of it, one short
		public static function bootFills( array &$appData ): array {
			return [
				'[[/nino/dir]]'					=> \Nino\Filesystem::getDir( $appData ),
				'[[/nino/public]]'			=> \Nino\Filesystem::getPublicDir( $appData ),
				'[[/nino/date/year]]'				=> date('Y'),
			];
		}

		public static function requestFills( array $request, string $userMail ): array {
			$responseUri = (string) ( $request['/nino/http/response']['uri'] ?? '' );
			return [
				'[[/nino/http/request/uri]]'				=> (string) ( $request['/nino/http/request']['uri'] ?? '' ),
				'[[/nino/http/response/uri]]'				=> $responseUri,
				'[[/nino/http/response/uri/clean]]'	=> str_replace( '/', '_', $responseUri ),
				'[[/nino/http/response/locale]]'		=> (string) ( $request['/nino/http/response']['locale'] ?? '' ),
				'[[/nino/auth/user]]'								=> $userMail,
			];
		}

		public static function runtimeFillKeys( array &$appData ): array {
			return array_map( static fn( string $fill ): string => trim( $fill, '[]' ), array_keys( self::bootFills( $appData ) + self::requestFills( [], '' ) ) );
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
			// (eg. /module/form/subject/owner containing
			// [[/project/website/general/url]]) - a same-count swap would
			// otherwise look "stable" after one pass.
			// The pass cap guards against a fill value that references
			// itself (possible via the Text panel, not just the
			// developer-authored defaults).
			for( $pass = 0; $pass < 10; $pass++ ) {

				$rendered = str_replace( $fillKeys, $fillValues, $html );

				if( $rendered === $html )
					break;

				$html = $rendered;

				// Nothing left that could match, so the pass that would prove
				// it is not run. str_replace() with an array of needles walks
				// the document once per needle - a project with a few hundred
				// fills therefore paid that many scans purely to discover that
				// the first pass had been the final one. This is one scan for
				// two characters.
				// The comparison above still decides the rest: a value that
				// carries a fill of its own leaves '[[' standing, and a
				// same-count swap - which is why the loop compares the string
				// rather than counting - is caught by it as before
				if( substr_count( $html, '[[' ) === 0 )
					break;
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
				// The assignment is a group of its own, so "was there a value?"
				// is answerable: judged by the value alone, alt="" read as no
				// value at all and arrived as the positional argument 'alt' -
				// which is how a decorative picture is written, and how
				// AGENTS.md writes one. A value that happens to equal its own
				// name (name="name") went the same way
				preg_match_all( '/\ ([^\ \=]*)(\=[\"]([^\"]*)[\"])?/i', ' '.$pregArgs[2].' ', $attr );

				foreach( $attr[1] AS $id => $key ) {

					if( $attr[2][$id] !== '' ) {
						$args[$key] = str_replace( '\'', '"', $attr[3][$id] );
						continue;
					}

					$args[] = $key;
				}

				// The trailing space this was given adds one empty positional
				// match at the end, which is not an argument
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

		// Which format a value is in, read from what it holds - for a value
		// nobody has named a format for (a text key without one, see
		// \Nino\Text::entries()). The widest one found wins: paragraphs and
		// lists, then line breaks, then the inline tags. Shared by every
		// domain class with a model/entry format
		public static function detectFormat( string $value ): string {

			if( preg_match( '#<(?:p|ul|ol|li)[ >/]#i', $value ) === 1 )
				return 'blocks';

			if( preg_match( '#<br[ >/]#i', $value ) === 1 )
				return 'lines';

			return self::containsHtml( $value ) ? 'inline' : 'plain';
		}

		// The format an element type's field is kept in. 'html' keeps the
		// inline tags and, with 'blocks', paragraphs and lists; 'breaks' keeps
		// the line breaks of a plain text field. Anything else is plain text.
		// A flag on a field it does not fit is ignored, not an error: a model
		// written by hand is not trusted
		public static function fieldFormat( array $field ): string {

			$isString = ( $field['type'] ?? 'string' ) === 'string';

			if( ( $field['html'] ?? false ) === true )
				return ( $isString === true && ( $field['blocks'] ?? false ) === true ) ? 'blocks' : 'inline';

			return ( $isString === true && ( $field['breaks'] ?? false ) === true ) ? 'breaks' : 'plain';
		}

		// One element field value, made safe to substitute into a template:
		// sanitized to the field's format, line breaks turned into <br> for a
		// 'breaks' field, escaped for a plain one - and then every '[' swapped
		// for its entity, because the surrounding content is rendered again
		// right after this and an editor's '[[...]]' or '[shortcode]' must not
		// be read as one. The one rule every renderer of a field applies, which
		// is why it is public: a feature that draws a field itself calls this
		// instead of keeping a copy
		public static function fieldValue( mixed $value, array $field ): string {

			$format = self::fieldFormat( $field );
			$value 	= strval( $value );

			if( $format === 'blocks' || $format === 'inline' )
				$safe = self::sanitizeHtml( $value, $format );
			else if( $format === 'breaks' )
				$safe = nl2br( htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ), false );
			else
				$safe = htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

			return str_replace( '[', '&#91;', $safe );
		}

		// A value that may hold the tags of a format, as the plain text it
		// reads as: every <br> and the end of every block becomes a newline.
		// strip_tags() alone glues 'Amtsgericht<br>Musterstadt' to
		// 'AmtsgerichtMusterstadt'. Nothing is removed otherwise - the caller
		// strips what is left
		public static function breaksToNewlines( string $value ): string {

			$value = (string) preg_replace( '#(?:</(?:p|div|li|ul|ol|h[1-6]|blockquote|tr|section|article)\s*>\s*)+#i', "\n", $value, -1, $blocks );
			$value = (string) preg_replace( '#<br\s*/?>#i', "\n", $value, -1, $breaks );

			// What the last block end left behind is the end of the value, not a
			// line of its own
			return $blocks + $breaks > 0 ? rtrim( $value, "\n" ) : $value;
		}

		// Rebuild a html value, keeping only whitelisted inline tags (strong/
		// em/span/code/a) one level deep and a safe href scheme on links. Never
		// trust the client's html: the editor's "no nesting" toolbar rule is
		// enforced here too, against a client that bypasses it entirely.
		//
		// $format widens what is kept. 'inline' is the tags above and nothing
		// else: a block that is unwrapped leaves one space where it ended, so
		// '<p>One.</p><p>Two.</p>' reads 'One. Two.' rather than 'One.Two.'.
		// 'lines' adds <br>, and a newline of the text becomes one. 'blocks'
		// keeps paragraphs and lists: p/ul/ol at the top level, li only in a
		// list, the inline tags and <br> inside p/li. Every other value - plain
		// is not html, see \Nino\Text::sanitizeValue() - is read as 'inline'.
		// What comes out of any of them is stable: sanitizing it again changes
		// nothing
		public static function sanitizeHtml( string $html, string $format = 'inline' ): string {

			if( trim( $html ) === '' )
				return '';

			// A byte that is not utf-8 must not reach the parser: libxml 2.13 ends
			// the text node at it and drops the rest of the value, silently,
			// where 2.9 carried it on. It becomes the replacement character, the
			// way a string field's does (see _sanitizeChildren())
			if( preg_match( '//u', $html ) !== 1 ) {
				$substitute = mb_substitute_character();
				mb_substitute_character( 0xFFFD );
				$html = mb_convert_encoding( $html, 'UTF-8', 'UTF-8' );
				mb_substitute_character( $substitute );
			}

			$doc = new \DOMDocument();

			// Wrapped in a tag no html has, not in a <div>: an unbalanced
			// '</div>' is what pasting from a web page looks like, and it closed
			// the wrapper - so everything after it was read as standing outside
			// the value and dropped, silently, on save
			libxml_use_internal_errors( true );
			$doc->loadHTML( '<?xml encoding="utf-8"?><nino-sanitize>'. $html. '</nino-sanitize>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
			libxml_clear_errors();

			$wrap = $doc->getElementsByTagName( 'nino-sanitize' )->item( 0 );

			if( $wrap === null )
				return '';

			if( $format === 'blocks' )
				return self::_sanitizeBlocks( $wrap );

			$ended = false;
			$out 	 = self::_sanitizeChildren( $wrap, false, $format === 'lines', $format === 'lines', $ended );

			// A break at either end of a value is not a line of it
			return $format === 'lines' ? self::_trimBreaks( $out ) : $out;
		}

		// Recursively rebuild a node's children, keeping only whitelisted
		// inline tags one level deep - a whitelisted tag found while already
		// inside another one is unwrapped (kept as plain content).
		// $keepBreaks keeps <br> (otherwise a break is a block boundary like
		// any other), $newlinesToBreaks turns a newline of the text into one,
		// and $ended says that a block just ended, so the next text is kept
		// apart from what came before it
		private static function _sanitizeChildren( \DOMNode $node, bool $insideInline, bool $keepBreaks, bool $newlinesToBreaks, bool &$ended ): string {

			$out = '';

			foreach( iterator_to_array( $node->childNodes ) as $child ) {

				if( $child->nodeType === XML_TEXT_NODE ) {

					// ENT_NOQUOTES: this is text content, not an attribute value - quotes need no escaping here.
					// ENT_SUBSTITUTE because spelling the flags out drops php's own: without it one byte that
					// is not utf-8 anywhere in the node answers '' and takes the whole text with it
					$text = htmlspecialchars( $child->textContent, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );

					if( $text === '' )
						continue;

					if( $ended === true )
						$out .= self::_boundary( $out, $text, $keepBreaks );

					$ended = false;

					// In the blocks format a loose run keeps its newlines until the
					// paragraphs are cut from it - except inside an inline tag, which
					// a paragraph break must not cut open
					if( $newlinesToBreaks === true || ( $insideInline === true && $keepBreaks === true ) ) {

						// A newline straight after a <br> is the source's own
						// formatting, not a second break
						if( $child->previousSibling !== null && strtolower( $child->previousSibling->nodeName ) === 'br' )
							$text = (string) preg_replace( '/^[ \t]*\r?\n/', '', $text );

						$text = str_replace( [ "\r\n", "\r", "\n" ], '<br>', $text );
					}

					$out .= $text;
					continue;
				}

				if( $child->nodeType !== XML_ELEMENT_NODE )
					continue;

				$tag = strtolower( $child->nodeName );

				if( $tag === 'br' && $keepBreaks === true ) {
					$out .= '<br>';
					$ended = false;
					continue;
				}

				if( in_array( $tag, self::BOUNDARY_TAGS, true ) === true ) {

					$innerEnded = false;
					$inner 			= self::_sanitizeChildren( $child, $insideInline, $keepBreaks, $newlinesToBreaks, $innerEnded );

					if( $inner !== '' )
						$out .= self::_boundary( $out, $inner, $keepBreaks ). $inner;

					$ended = $out !== '';
					continue;
				}

				if( in_array( $tag, self::HTML_TAGS, true ) === false || $insideInline === true ) {
					$out .= self::_sanitizeChildren( $child, $insideInline, $keepBreaks, $newlinesToBreaks, $ended );
					continue;
				}

				$innerEnded = false;
				$inner 			= self::_sanitizeChildren( $child, true, $keepBreaks, $newlinesToBreaks, $innerEnded );

				if( $inner === '' )
					continue;

				if( $ended === true )
					$out .= self::_boundary( $out, $inner, $keepBreaks );

				$ended = false;

				if( $tag === 'a' ) {
					$href = self::_safeHref( $child->getAttribute( 'href' ) );
					$out .= ( $href === null ) ? $inner : '<a href="'. htmlspecialchars( $href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ). '">'. $inner. '</a>';
					continue;
				}

				$out .= '<'. $tag. '>'. $inner. '</'. $tag. '>';
			}

			return $out;
		}

		// What keeps two runs of text apart where a block between them was
		// unwrapped: nothing where either side already has a space or a break,
		// one <br> in a value that keeps breaks, otherwise one space
		private static function _boundary( string $before, string $after, bool $keepBreaks ): string {

			if( $before === '' || $after === '' || preg_match( '/(\s|<br>)$/u', $before ) === 1 || preg_match( '/^\s/u', $after ) === 1 )
				return '';

			return $keepBreaks === true ? '<br>' : ' ';
		}

		// The blocks format: paragraphs and lists. Headings, quotes, divs and an
		// item that is not inside a list become paragraphs, anything in a list
		// that is not an item becomes an item of its own - so no word of what
		// was pasted is lost. Loose text and inline tags between the blocks are
		// gathered into paragraphs: a blank line in the text starts a new one
		// (never inside an inline tag, where a newline is a <br>), a single
		// newline is a <br> (the convention \Nino\Modules\Posts uses for its
		// body text)
		private static function _sanitizeBlocks( \DOMNode $wrap ): string {

			$doc 		= $wrap->ownerDocument;
			$out 		= '';
			$run 		= null;

			$flush = function( \DOMElement $run ) use ( &$out ): void {

				// One run, so what a block in it unwrapped keeps its neighbours
				// apart however the run is cut into children
				$ended = false;
				$loose = self::_sanitizeChildren( $run, false, true, false, $ended );

				foreach( preg_split( '/\n[ \t]*\n/u', str_replace( [ "\r\n", "\r" ], "\n", $loose ) ) ?: [] as $paragraph ) {
					$paragraph = self::_trimBreaks( str_replace( "\n", '<br>', $paragraph ) );

					if( $paragraph !== '' )
						$out .= '<p>'. $paragraph. '</p>';
				}
			};

			foreach( iterator_to_array( $wrap->childNodes ) as $child ) {

				$tag = $child->nodeType === XML_ELEMENT_NODE ? strtolower( $child->nodeName ) : '';

				if( in_array( $tag, self::PARAGRAPH_TAGS, true ) === true || $tag === 'li' ) {

					if( $run !== null ) {
						$flush( $run );
						$run = null;
					}

					$ended = false;
					$inner = self::_trimBreaks( self::_sanitizeChildren( $child, false, true, true, $ended ) );

					if( $inner !== '' )
						$out .= '<p>'. $inner. '</p>';

					continue;
				}

				if( $tag === 'ul' || $tag === 'ol' ) {

					if( $run !== null ) {
						$flush( $run );
						$run = null;
					}

					$items = '';

					foreach( iterator_to_array( $child->childNodes ) as $item ) {

						$holder = $item;

						if( $item->nodeType !== XML_ELEMENT_NODE || strtolower( $item->nodeName ) !== 'li' ) {
							$holder = $doc->createElement( 'nino-item' );
							$holder->appendChild( $item->cloneNode( true ) );
						}

						$ended = false;
						$inner = self::_trimBreaks( self::_sanitizeChildren( $holder, false, true, true, $ended ) );

						if( $inner !== '' )
							$items .= '<li>'. $inner. '</li>';
					}

					if( $items !== '' )
						$out .= '<'. $tag. '>'. $items. '</'. $tag. '>';

					continue;
				}

				// Text, an inline tag, a <br>: loose content. Its newlines stay as
				// they are until the paragraphs are cut from it
				$run = $run ?? $doc->createElement( 'nino-loose' );
				$run->appendChild( $child->cloneNode( true ) );
			}

			if( $run !== null )
				$flush( $run );

			return $out;
		}

		// A value without the breaks and spaces around it
		private static function _trimBreaks( string $value ): string {
			return trim( (string) preg_replace( '/^(?:<br>|\s)+|(?:<br>|\s)+$/u', '', $value ) );
		}

		// Validate a link href: only relative/fragment uris or a handful of
		// safe schemes - blocks javascript: and similar injection vectors
		private static function _safeHref( string $href ): string|null {

			// A browser takes a tab or a line break out of a link; here it would
			// also cut a paragraph in two inside the tag
			$href = trim( str_replace( [ "\r", "\n", "\t" ], '', $href ) );

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
