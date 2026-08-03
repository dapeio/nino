<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Images				see Nino.php's Modules section for the
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

			return '<img src="'. htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ). '" width="'. (int) ( $slot['width'] ?? 0 ). '" height="'. (int) ( $slot['height'] ?? 0 ). '" alt="'. htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' ). '">';
		}
	}

}
