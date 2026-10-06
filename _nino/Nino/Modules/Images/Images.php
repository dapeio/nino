<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Images				see _nino/Nino/Modules/Modules.php for the
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
	 *	Images							A html shortcode for including a developer-fixed image slot
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Images {

		/*	The one fragment this module renders, declared once rather than
			built inside doShortcode(). Being a property is the point: a
			project that wants loading="lazy" or a different attribute order
			replaces it without touching the class - see AGENTS.md, "Markup
			belongs in a template", and \Nino\Modules\Navigation::$html for
			the shape	*/
		public static
			$html = [
				'img' => '<img src="[[src]]" width="[[width]]" height="[[height]]" alt="[[alt]]">',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'image', [ self::class, 'doShortcode' ] );
		}

		/**
		 *	Replace shortcode with an <img> tag for the given slot uri - eg.
		 *	[image hero] or [image uri="hero" alt="..."]. Renders nothing if
		 *	the slot doesn't exist or has no image uploaded yet.
		 *
		 *	With content - [image logo]...[/image] - that content is what is
		 *	rendered instead of the <img>, and again only when the slot has an
		 *	image: [[src]] (the path of the file, from the site's root - an
		 *	absolute address is "https://[[/website/url]][[src]]"), [[width]],
		 *	[[height]] and [[alt]] are filled in with the same values the <img>
		 *	gets. That is how a place that needs the address and not a picture
		 *	- a meta tag, a mail - is written without being left as an empty
		 *	tag, or a broken one, where nothing is uploaded yet.
		 *
		 *	The alt text is, in this order: the one stored for the slot in
		 *	the current language (the Images panel keeps it), the template's
		 *	own alt="...", and none - alt="", which is how a decorative picture
		 *	is written. The slot's label is not an alt text and is not used as
		 *	one. Whichever it is, it is output with '[' turned into &#91; as
		 *	well as escaped: shortcode output is rendered once more, and an
		 *	alt text an editor wrote must not be able to open a fill or a
		 *	shortcode there.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$uri 	= (string) ( $args[0] ?? ( $args['uri'] ?? '' ) );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false || empty( $slot['filename'] ) === true )
				return '';

			$url = \Nino\Images::getUrl( $appData, $slot['filename'] );

			$stored	= $slot['alt'][ \Nino\Locales::getCurrentLocale( $appData ) ] ?? '';
			$alt		= ( is_string( $stored ) === true && $stored !== '' ) ? $stored : (string) ( $args['alt'] ?? '' );

			return str_replace(
				[ '[[src]]', '[[width]]', '[[height]]', '[[alt]]' ],
				[
					str_replace( '[', '&#91;', htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ),
					(string) (int) ( $slot['width'] ?? 0 ),
					(string) (int) ( $slot['height'] ?? 0 ),
					str_replace( '[', '&#91;', htmlspecialchars( $alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ),
				],
				trim( (string) ( $args['content'] ?? '' ) ) !== '' ? (string) $args['content'] : self::$html['img']
			);
		}
	}

}
