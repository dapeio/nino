<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\Basis				see _nino/Nino/Modules/Modules.php for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						All optional modules
	 *	Basis							The basic components of a page - header, footer, section, button, image and text -
	 *										with a standard look. A feature that is delivered with the kernel and switched off
	 *
	 *										What this class holds is what the schemas of components/ cannot say: the renderers
	 *										of the ten components that compute (a picture looked up, the entries of a menu, a link
	 *										that is none, a text that is cleaned) and the options of the attribute that names
	 *										a menu. The kernel reads the folders, registers the components and puts the standard
	 *										part of each group into the style bundle (\Nino\Features::registerComponents(),
	 *										registerParts()); there is nothing to register here, so there is no init(). The other
	 *										ten components are drawn from their template alone.
	 *
	 *										A renderer has the template of its component filled by \Nino\Components::fill() and
	 *										gives it the tokens it made - markup that is there only sometimes, written once as a
	 *										fragment of $html. What a token holds is text, escaped for its place by _text(); what
	 *										the call said arrives escaped by the register. Nothing here reads a value again.
	 *
	 *										Menus, the legal pages and the languages stay modules of the kernel (Navigation,
	 *										Legal, Localepicker): this class asks them for data and draws it. A picture is
	 *										\Nino\Images::picture().
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Basis {

		/**
		 *	What a renderer puts into the template of its component where there is something to put: one line each,
		 *	the tokens in double brackets
		 */
		public static array $html = [
			'img'					=> '<img src="[[src]]"[[size]] alt="[[alt]]" loading="lazy" decoding="async">',
			'logo'				=> '<img class="header-logo-[[ground]]" src="[[src]]"[[size]] alt="">',
			'background'	=> '<div class="section-wrap-image"><img class="section-wrap-picture" src="[[src]]"[[size]] alt="" decoding="async"></div>',
			'size'				=> ' width="[[width]]" height="[[height]]"',
			'link'				=> '<a href="[[href]]">',
			'a'						=> ' href="[[href]]"[[blank]]',
			'blank'				=> ' target="_blank" rel="noopener"',
			'entry'				=> '<li><a href="[[href]]"[[current]]>[[title]]</a></li>',
			'entry-text'	=> '<li><span>[[title]]</span></li>',
			'page'				=> ' aria-current="page"',
			'language'		=> ' aria-current="true"',
			'open'				=> ' open',
			'legal'				=> '<ul class="footer-legal-pages">[[entries]]</ul>',
			'locales'			=> '<ul class="footer-legal-locales">[[entries]]</ul>',
			'locale'			=> '<li><a href="[[href]]" lang="[[lang]]" hreflang="[[lang]]"[[current]]>[[name]]</a></li>',
			'figcaption'	=> '<figcaption>[[caption]]</figcaption>',
		];

		// Where the log has been told that a picture is a link with no name: once for each picture of the request
		private const string NOTICED = './nino/basis/noticed';

		/**
		 *	The menus of the project, for the attribute menu of header-nav and footer-nav
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$attribute		The attribute that asks
		 *
		 *	@return 	array										menu => its name
		 */
		public static function menus( array &$appData, string $attribute ): array {

			$menus = [];

			foreach( (array) ( $appData['/nino/html/navs'] ?? [] ) as $menu )
				if( is_string( $menu ) === true && $menu !== '' )
					$menus[$menu] = $menu;

			return $menus;
		}

		/**
		 *	A text of the site as it is written into html: a control character that is no tab and no line break left out
		 *	(html has no use for it), escaped, and every bracket an entity, as the words of an editor are written wherever
		 *	they reach a page (\Nino\Modules\Navigation does the same with the title of an entry)
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		private static function _text( string $value ): string {
			return str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], \Nino\Components::escape( $value ) );
		}

		/**
		 *	The picture a reference names, written for an <img>: the address, the size where the picture has one, and the
		 *	alt text that is stored with it. The register hands a reference over escaped, which is read back here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$reference		The uri of a slot or the name of an image field of the element, as the register wrote it
		 *	@param		array			$context			What the renderer was handed
		 *
		 *	@return 	array|null								[ 'src', 'size', 'alt' ], each of them written for html; null where there is no picture
		 */
		private static function _picture( array &$appData, string $reference, array $context ): ?array {

			$picture = \Nino\Images::picture( $appData, html_entity_decode( $reference, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), is_array( $context['element'] ?? null ) === true ? $context['element'] : null );

			if( $picture === null )
				return null;

			// A size that is not known is no size: width="0" would be a box of nothing
			$size = $picture['width'] > 0 && $picture['height'] > 0 ? strtr( self::$html['size'], [ '[[width]]' => (string) $picture['width'], '[[height]]' => (string) $picture['height'] ] ) : '';

			return [ 'src' => self::_text( $picture['url'] ), 'size' => $size, 'alt' => self::_text( $picture['alt'] ) ];
		}

		/**
		 *	The name of a navigation: the one the call gives it, else the word of the feature for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					What the renderer was handed
		 *	@param		string		$word					primary or footer: the part of /module/basis/navigation/
		 *
		 *	@return 	string										Written for html
		 */
		private static function _name( array &$appData, array $args, string $word ): string {
			return $args['label'] !== '' ? $args['label'] : self::_text( (string) ( \Nino\Html::resolveTextfill( $appData, '/module/basis/navigation/'. $word ) ?? '' ) );
		}

		/**
		 *	The alt text of a picture: the call's own where it says one, nothing where it says the picture is decorative,
		 *	else the one stored with the picture for the language of the page
		 *
		 *	@param		array			$args					What the renderer was handed
		 *	@param		array			$picture			As _picture() answers it
		 *
		 *	@return 	string										Written for html
		 */
		private static function _alt( array $args, array $picture ): string {
			return $args['alt'] !== '' ? $args['alt'] : ( $args['decorative'] === '1' ? '' : $picture['alt'] );
		}

		/**
		 *	The entries of a menu as list items. An entry that has no address is a line a callback left behind:
		 *	text in the list, and one with no title is none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$menu
		 *
		 *	@return 	string										'' where the menu has no entry
		 */
		private static function _entries( array &$appData, string $menu ): string {

			$html = '';

			foreach( Navigation::entries( $appData, $menu ) as $entry ) {

				if( trim( $entry['title'] ) === '' )
					continue;

				$html .= strtr( self::$html[ $entry['href'] === '' ? 'entry-text' : 'entry' ], [
					'[[href]]'		=> self::_text( $entry['href'] ),
					'[[current]]'	=> $entry['current'] === true ? self::$html['page'] : '',
					'[[title]]'		=> self::_text( $entry['title'] ),
				] );
			}

			return $html;
		}

		/**
		 *	The link a title or an image is put in: the address the register made of the call, or nothing
		 *
		 *	@param		string		$href					An address as the register wrote it, '' for none
		 *
		 *	@return 	array										[ the opening tag, the closing tag ]
		 */
		private static function _link( string $href ): array {
			return $href === '' ? [ '', '' ] : [ str_replace( '[[href]]', $href, self::$html['link'] ), '</a>' ];
		}

		/**
		 *	Replace header-logo: the picture for a light and the one for a dark ground, and the name of the company, which
		 *	is the name of the link and stays where there is no picture
		 *
		 *	The two pictures are both written and the style shows the one that fits the colour scheme of the page. A link
		 *	that leads nowhere is the start of the site. The <img> has no alt text: the name beside it is the one a screen
		 *	reader reads, and a second would say it twice
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentHeaderLogo( array &$appData, array $args, string $content, array $context ): string {

			$pictures = '';

			foreach( [ 'light', 'dark' ] as $ground ) {

				$picture = self::_picture( $appData, $args[$ground], $context );

				if( $picture !== null )
					$pictures .= strtr( self::$html['logo'], [ '[[ground]]' => $ground, '[[src]]' => $picture['src'], '[[size]]' => $picture['size'] ] );
			}

			if( $args['href'] === '' )
				$args['href'] = self::_text( rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). '/' );

			return \Nino\Components::fill( $appData, 'header-logo', $args, $content, [ '[[pictures]]' => $pictures ] );
		}

		/**
		 *	Replace header-nav: the menu in a details, which a small screen opens and closes with its button and a wide one
		 *	shows as a row. No script: the browser does it, and the keyboard finds the entries in their order. A menu that
		 *	never collapses is a details that is open. A menu with no entry is not drawn, and neither is its button
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentHeaderNav( array &$appData, array $args, string $content, array $context ): string {

			$entries = self::_entries( $appData, html_entity_decode( $args['menu'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			if( $entries === '' )
				return '';

			return \Nino\Components::fill( $appData, 'header-nav', $args, $content, [ '[[entries]]' => $entries, '[[open]]' => $args['collapse'] === 'never' ? self::$html['open'] : '', '[[name]]' => self::_name( $appData, $args, 'primary' ) ] );
		}

		/**
		 *	Replace footer-logo: the picture of the first word, a slot or an image field. No picture, nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentFooterLogo( array &$appData, array $args, string $content, array $context ): string {

			$picture = self::_picture( $appData, $args['value'], $context );

			if( $picture === null )
				return '';

			$picture['alt'] = self::_alt( $args, $picture );

			return \Nino\Components::fill( $appData, 'footer-logo', $args, $content, [ '[[picture]]' => strtr( self::$html['img'], [ '[[src]]' => $picture['src'], '[[size]]' => $picture['size'], '[[alt]]' => $picture['alt'] ] ) ] );
		}

		/**
		 *	Replace footer-nav: the entries of a menu as a list. A menu with no entry is not drawn
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentFooterNav( array &$appData, array $args, string $content, array $context ): string {

			$entries = self::_entries( $appData, html_entity_decode( $args['menu'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			return $entries === '' ? '' : \Nino\Components::fill( $appData, 'footer-nav', $args, $content, [ '[[entries]]' => $entries, '[[name]]' => self::_name( $appData, $args, 'footer' ) ] );
		}

		/**
		 *	Replace footer-legal: the copyright line, the legal pages (the menu legal) and - where the site has more than
		 *	one - the languages, as the modules of the kernel list them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentFooterLegal( array &$appData, array $args, string $content, array $context ): string {

			$legal = self::_entries( $appData, 'legal' );
			$languages = '';
			$entries = Localepicker::entries( $appData );

			if( count( $entries ) > 1 )
				foreach( $entries as $entry )
					$languages .= strtr( self::$html['locale'], [
						'[[href]]'		=> self::_text( $entry['href'] ),
						'[[lang]]'		=> self::_text( $entry['lang'] ),
						'[[current]]'	=> $entry['current'] === true ? self::$html['language'] : '',
						'[[name]]'		=> self::_text( $entry['name'] ),
					] );

			return \Nino\Components::fill( $appData, 'footer-legal', $args, $content, [
				'[[legal]]'		=> $legal === '' ? '' : str_replace( '[[entries]]', $legal, self::$html['legal'] ),
				'[[locales]]'	=> $languages === '' ? '' : str_replace( '[[entries]]', $languages, self::$html['locales'] ),
			] );
		}

		/**
		 *	Replace section-wrap: the picture behind the content, where the call names one and it has a file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentSectionWrap( array &$appData, array $args, string $content, array $context ): string {

			$picture = self::_picture( $appData, $args['image'], $context );

			return \Nino\Components::fill( $appData, 'section-wrap', $args, $content, [
				'[[picture]]' => $picture === null ? '' : strtr( self::$html['background'], [ '[[src]]' => $picture['src'], '[[size]]' => $picture['size'] ] ),
			] );
		}

		/**
		 *	Replace section-title: the title, in a link where the call has an address
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentSectionTitle( array &$appData, array $args, string $content, array $context ): string {

			[ $open, $close ] = self::_link( $args['href'] );

			return \Nino\Components::fill( $appData, 'section-title', $args, $content, [ '[[open]]' => $open, '[[close]]' => $close ] );
		}

		/**
		 *	Replace button: a link, or - where the call has no address, or one that is none - a span of the same classes,
		 *	which no keyboard stops at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentButton( array &$appData, array $args, string $content, array $context ): string {

			$link = $args['href'] === '' ? '' : strtr( self::$html['a'], [ '[[href]]' => $args['href'], '[[blank]]' => $args['target'] === 'blank' ? self::$html['blank'] : '' ] );

			return \Nino\Components::fill( $appData, 'button', $args, $content, [ '[[tag]]' => $link === '' ? 'span' : 'a', '[[link]]' => $link ] );
		}

		/**
		 *	Replace image: the picture of a slot or of an image field, in a figure with its caption, in a link where the
		 *	call has an address. No picture, nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentImage( array &$appData, array $args, string $content, array $context ): string {

			$picture = self::_picture( $appData, $args['value'], $context );

			if( $picture === null )
				return '';

			$picture['alt'] = self::_alt( $args, $picture );

			// A picture with no alt text in a link is a link with no name, which neither a keyboard user nor a screen reader can use: the link is left out and the log says so
			$href = $args['href'];

			if( $href !== '' && $picture['alt'] === '' ) {

				$href = '';

				if( isset( $appData[self::NOTICED][$args['value']] ) === false ) {

					$appData[self::NOTICED][$args['value']] = true;
					trigger_error( 'Basis: the image "'. preg_replace( '/[^\x20-\x7E]/', '?', substr( html_entity_decode( $args['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 0, 80 ) ). '" is a link and has no alt text - it is drawn without the link', E_USER_NOTICE );
				}
			}

			[ $open, $close ] = self::_link( $href );

			return \Nino\Components::fill( $appData, 'image', $args, $content, [
				'[[picture]]'		=> strtr( self::$html['img'], [ '[[src]]' => $picture['src'], '[[size]]' => $picture['size'], '[[alt]]' => $picture['alt'] ] ),
				'[[open]]'			=> $open,
				'[[close]]'			=> $close,
				'[[figcaption]]'	=> $args['caption'] === '' ? '' : str_replace( '[[caption]]', $args['caption'], self::$html['figcaption'] ),
			] );
		}

		/**
		 *	Replace html: what stands between the tags is the page's own markup, drawn, and then what
		 *	\Nino\Html::sanitizeHtml() keeps of it in the format blocks - paragraphs, lists and the inline tags - with the
		 *	brackets it was written with. What a fill put into it is cleaned with the rest. Where nothing is left, nothing is drawn
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by the register
		 *	@param		string		$content			What the children drew
		 *	@param		array			$context			The place the call stands in
		 *
		 *	@return 	string
		 */
		public static function componentHtml( array &$appData, array $args, string $content, array $context ): string {

			$clean = \Nino\Components::clean( \Nino\Html::sanitizeHtml( $content, 'blocks', true ) );

			return $clean === '' ? '' : \Nino\Components::fill( $appData, 'html', $args, $clean, [] );
		}
	}
}
