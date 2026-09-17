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
			$alt = (string) ( $args['alt'] ?? ( $slot['label'] ?? '' ) );

			return str_replace(
				[ '[[src]]', '[[width]]', '[[height]]', '[[alt]]' ],
				[
					htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
					(string) (int) ( $slot['width'] ?? 0 ),
					(string) (int) ( $slot['height'] ?? 0 ),
					htmlspecialchars( $alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
				],
				self::$html['img']
			);
		}
	}

}
