<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Template				see _nino/Nino/Modules/Modules.php for the
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

		// A word of a text key: lower-case letters and digits, joined by hyphens
		private const string SEGMENT = '#^[a-z0-9]+(?:-[a-z0-9]+)*$#D';

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
		 *	The category of a template, the second segment of every text key it
		 *	carries (/template/<category>/<part>/<name>): its file name without
		 *	.tpl, prefix and all - page-home.tpl is page-home, html-footer.tpl
		 *	is html-footer. Nothing is derived or cut off. A name that is not a
		 *	word of a key - a dot, an upper-case letter, a slash - has no
		 *	category, and the template carries no keys of its own: it may read
		 *	every other one. Only a file directly in templates/ has one
		 *
		 *	@param		string		$name					A file name (page-home.tpl), or a template as a
		 *																	shortcode or a route body names it (/templates/page-home)
		 *
		 *	@return 	string|null							The category, or null
		 */
		public static function category( string $name ): ?string {

			if( str_starts_with( $name, '/templates/' ) === true )
				$name = substr( $name, strlen( '/templates/' ) );

			if( str_ends_with( $name, '.tpl' ) === true )
				$name = substr( $name, 0, -strlen( '.tpl' ) );

			return preg_match( self::SEGMENT, $name ) === 1 ? $name : null;
		}

		/**
		 *	Replace template shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
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
