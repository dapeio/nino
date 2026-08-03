<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Template				see Nino.php's Modules section for the
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
	 *	Template						A html shortcode for including templates
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */


	class Template {

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'template', [ self::class, 'doShortcode' ] );
		}


		/**
		 *	Replace template shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$html = \Nino\Filesystem::getFileContent( $appData, ( $args[0] ?? '' ). '.tpl', '' );

			// No '/nino/html/render' callback here: Html::_doShortcode() passes
			// every shortcode's return value through renderHtml() right after
			// this returns, and that runs the same callback - so a template body
			// went through the render pipeline twice, once for nothing
			return is_string( $html ) === true ? $html : '';
		}
	}

}
