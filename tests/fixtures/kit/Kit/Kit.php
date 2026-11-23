<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\Kit					The test feature of the kernel's suites: what the machine - the register of the components, the parser, the blocks, the
 *											packages and the features - is tried on, in the shape a feature of a project brings it: components and groups in
 *											folders, blocks in blocks/, and this class for the renderers of what computes.
 *
 *											What it has, for the suites that read it:
 *											  band-wrap, -row, -col, -title, -subtitle	a page that is a stack of bands: the wrap is the root of a block of the page, a column
 *																												has the variant card, a title is a text with an address
 *											  crown-wrap, -logo, -nav, -actions					a head: the wrap is the root of a block of the header and offers its logo and its
 *																												menu to the columns below it (deep), the logo is once in it and is the one that draws the
 *																												logo of the project
 *											  foot-wrap, -col, -logo, -nav								a foot of the same kind, whose logo is a picture
 *											  lever, lever-group														a link that looks like a button, with choices that are classes and choices that are not
 *											  pic, prose, raw														a picture of a slot, a text, and the one whose content is the markup of the page
 *											and the groups band, crown, foot, lever, pic and prose with their knobs, their standard
 *											part and a demo, and five blocks (blocks/): hero and split with variants, columns of cards,
 *											a head and a foot.
 *
 *											Read by tests/archive-smoke.php, blocks-smoke.php, catalogue-smoke.php, components-rules-smoke.php and
 *											packages-smoke.php, which name the directory it stands in as the features directory of their run (or copy it
 *											below the one they made), switch it on in the modules of their project and register its components as a
 *											project does.
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	class Kit {

		/**
		 *	The menus of the project, for the attribute menu of crown-nav and foot-nav
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
		 *	The picture a reference names, written for an <img>: the address, and the size where the picture has one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$reference		The uri of a slot or the name of an image field of the element, as the register wrote it
		 *	@param		array			$context			What the renderer was handed
		 *
		 *	@return 	array|null								[ 'src', 'size' ], written for html; null where there is no picture
		 */
		private static function _picture( array &$appData, string $reference, array $context ): ?array {

			$picture = \Nino\Images::picture( $appData, html_entity_decode( $reference, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), is_array( $context['element'] ?? null ) === true ? $context['element'] : null );

			if( $picture === null )
				return null;

			return [
				'src'		=> \Nino\Components::escape( $picture['url'] ),
				'size'	=> $picture['width'] > 0 && $picture['height'] > 0 ? ' width="'. $picture['width']. '" height="'. $picture['height']. '"' : '',
				'alt'		=> \Nino\Components::escape( $picture['alt'] ),
			];
		}

		/**
		 *	The picture behind the content of a wrap, where the call names one, it has a file and picture is not off
		 */
		private static function _wrap( array &$appData, string $wrap, array $args, string $content, array $context ): string {

			$picture = $args['picture'] === 'off' ? null : self::_picture( $appData, $args['image'], $context );

			return \Nino\Components::fill( $appData, $wrap, $args, $content, [
				'[[picture]]' => $picture === null ? '' : '<div class="'. $wrap. '-image"><img src="'. $picture['src']. '"'. $picture['size']. ' alt="" decoding="async"></div>',
			] );
		}

		public static function componentBandWrap( array &$appData, array $args, string $content, array $context ): string {
			return self::_wrap( $appData, 'band-wrap', $args, $content, $context );
		}

		public static function componentCrownWrap( array &$appData, array $args, string $content, array $context ): string {
			return self::_wrap( $appData, 'crown-wrap', $args, $content, $context );
		}

		public static function componentFootWrap( array &$appData, array $args, string $content, array $context ): string {
			return self::_wrap( $appData, 'foot-wrap', $args, $content, $context );
		}

		/**
		 *	band-title: the title, in a link where the call has an address
		 */
		public static function componentBandTitle( array &$appData, array $args, string $content, array $context ): string {

			return \Nino\Components::fill( $appData, 'band-title', $args, $content, [
				'[[open]]'	=> $args['href'] === '' ? '' : '<a href="'. $args['href']. '">',
				'[[close]]'	=> $args['href'] === '' ? '' : '</a>',
			] );
		}

		/**
		 *	crown-logo: the picture for a light and the one for a dark ground, and the name of the company
		 */
		public static function componentCrownLogo( array &$appData, array $args, string $content, array $context ): string {

			$pictures = '';

			foreach( [ 'light', 'dark' ] as $ground ) {

				$picture = self::_picture( $appData, $args[$ground], $context );

				if( $picture !== null )
					$pictures .= '<img class="crown-logo-'. $ground. '" src="'. $picture['src']. '"'. $picture['size']. ' alt="">';
			}

			if( $args['href'] === '' )
				$args['href'] = \Nino\Components::escape( rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). '/' );

			return \Nino\Components::fill( $appData, 'crown-logo', $args, $content, [ '[[pictures]]' => $pictures ] );
		}

		/**
		 *	foot-logo: the picture of the first word, a slot or an image field. No picture, nothing
		 */
		public static function componentFootLogo( array &$appData, array $args, string $content, array $context ): string {

			$picture = self::_picture( $appData, $args['value'], $context );

			if( $picture === null )
				return '';

			$alt = $args['alt'] !== '' ? $args['alt'] : ( $args['decorative'] === '1' ? '' : $picture['alt'] );

			return \Nino\Components::fill( $appData, 'foot-logo', $args, $content, [ '[[picture]]' => '<img src="'. $picture['src']. '"'. $picture['size']. ' alt="'. $alt. '">' ] );
		}

		/**
		 *	The entries of a menu as list items
		 */
		private static function _entries( array &$appData, string $menu ): string {

			$html = '';

			foreach( Navigation::entries( $appData, $menu ) as $entry )
				if( trim( $entry['title'] ) !== '' )
					$html .= '<li><a href="'. \Nino\Components::escape( $entry['href'] ). '">'. \Nino\Components::escape( $entry['title'] ). '</a></li>';

			return $html;
		}

		public static function componentCrownNav( array &$appData, array $args, string $content, array $context ): string {

			$entries = self::_entries( $appData, html_entity_decode( $args['menu'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			return $entries === '' ? '' : \Nino\Components::fill( $appData, 'crown-nav', $args, $content, [ '[[entries]]' => $entries, '[[open]]' => $args['collapse'] === 'never' ? ' open' : '', '[[name]]' => $args['label'] ] );
		}

		public static function componentFootNav( array &$appData, array $args, string $content, array $context ): string {

			$entries = self::_entries( $appData, html_entity_decode( $args['menu'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			return $entries === '' ? '' : \Nino\Components::fill( $appData, 'foot-nav', $args, $content, [ '[[entries]]' => $entries, '[[name]]' => $args['label'] ] );
		}

		/**
		 *	lever: a link, or - where the call has no address - a span of the same classes
		 */
		public static function componentLever( array &$appData, array $args, string $content, array $context ): string {

			$link = $args['href'] === '' ? '' : ' href="'. $args['href']. '"'. ( $args['target'] === 'blank' ? ' target="_blank" rel="noopener"' : '' );

			return \Nino\Components::fill( $appData, 'lever', $args, $content, [ '[[tag]]' => $link === '' ? 'span' : 'a', '[[link]]' => $link ] );
		}

		/**
		 *	pic: the picture of a slot or of an image field, in a figure. No picture, nothing
		 */
		public static function componentPic( array &$appData, array $args, string $content, array $context ): string {

			$picture = self::_picture( $appData, $args['value'], $context );

			if( $picture === null )
				return '';

			$alt = $args['alt'] !== '' ? $args['alt'] : ( $args['decorative'] === '1' ? '' : $picture['alt'] );

			return \Nino\Components::fill( $appData, 'pic', $args, $content, [
				'[[picture]]'		=> '<img src="'. $picture['src']. '"'. $picture['size']. ' alt="'. $alt. '" loading="lazy" decoding="async">',
				'[[open]]'			=> $args['href'] === '' ? '' : '<a href="'. $args['href']. '">',
				'[[close]]'			=> $args['href'] === '' ? '' : '</a>',
				'[[figcaption]]'	=> $args['caption'] === '' ? '' : '<figcaption>'. $args['caption']. '</figcaption>',
			] );
		}

		/**
		 *	raw: what stands between the tags is the page's own markup, drawn, and then what \Nino\Html::sanitizeHtml() keeps of it
		 */
		public static function componentRaw( array &$appData, array $args, string $content, array $context ): string {

			$clean = \Nino\Components::clean( \Nino\Html::sanitizeHtml( $content, 'blocks', true ) );

			return $clean === '' ? '' : \Nino\Components::fill( $appData, 'raw', $args, $clean, [] );
		}
	}
}
