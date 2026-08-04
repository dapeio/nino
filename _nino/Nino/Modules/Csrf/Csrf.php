<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Csrf				see Nino.php's Modules section for the
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
	 *	Csrf								The [csrf] shortcode - the actual protection is
	 *											\Nino\Csrf, a required kernel class active
	 *											whether or not this module is
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Csrf {

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'csrf', [ self::class, 'doShortcode' ] );
		}

		/**
		 *	Replace shortcode with a hidden csrf input field
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string									Hidden input html
		 */
		public static function doShortcode( array &$appData, array $args ): string {
			return '<input type="hidden" name="_csrf" value="'. htmlspecialchars( \Nino\Csrf::getToken( $appData ), ENT_QUOTES, 'UTF-8' ). '">';
		}
	}

}
