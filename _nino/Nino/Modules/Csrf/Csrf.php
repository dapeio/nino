<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Csrf				see _nino/Nino/Modules/Modules.php for the
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

		/*	The one fragment this module renders, declared once rather than
			built inside doShortcode(). Being a property is the point: it is
			then one place to read, and a project that needs a different field
			name or an extra attribute replaces it without touching the class -
			see AGENTS.md, "Markup belongs in a template", and
			\Nino\Modules\Navigation::$html for the shape	*/
		public static
			$html = [
				'input' => '<input type="hidden" name="_csrf" value="[[token]]">',
			];

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

			return str_replace(
				'[[token]]',
				htmlspecialchars( \Nino\Csrf::getToken( $appData ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
				self::$html['input']
			);
		}
	}

}
