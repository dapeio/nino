<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Elements				see Nino.php's Modules section for the
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
	 *	Elements						A html shortcode for including Elements
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */


	class Elements {

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'element', [ self::class, 'doShortcodeElement' ] );
			\Nino\Html::addShortcode( $appData, 'elements', [ self::class, 'doShortcodeElements' ] );
		}


		/**
		 *	Replace element shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcodeElement( array &$appData, array $args ): string {

			$uri			= $args[0] ?? '';
			$content	= $args['content'] ?? '';
			$locale 	= $args['locale'] ?? '';
			$callback	= $args['callback'] ?? '';

			$element	= \Nino\Elements::getElement( $appData, $uri, $locale );
			$model = \Nino\Elements::getElementModel( $appData, \Nino\Elements::getElementTypeFromUri( $uri ) );

			if( $element === null || $element === false )
				return '';

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $element );

			$fills = [
				[],
				[],
			];

			$element['.id'] = 0;

			foreach( $element as $key => $value ) {
				if( is_scalar( $value ) === false )
					continue;

				$isHtml = ( isset( $model[$key] ) && isset( $model[$key]['html'] ) && $model[$key]['html'] === true );

				$fills[0][] = '[['. $key. ']]';
				$fills[1][] = self::_escapeFieldValue( $value, $isHtml );
			}

			return str_replace( $fills[0], $fills[1], $content );
		}

		/**
		 *	Escape a single element field value before it is substituted into a
		 *	template. Values are editor content, not developer-authored markup -
		 *	htmlspecialchars() neutralizes raw HTML/script (matches
		 *	Modules\Images::doShortcode()'s existing pattern), and the extra '['
		 *	swap stops an editor-supplied "[[...]]" or "[shortcode]" string from
		 *	being interpreted when the surrounding content is re-rendered by
		 *	Html::_doShortcode() right after this callback returns.
		 *
		 *	@param		mixed			$value				Raw scalar field value
		 *
		 *	@return 	string									Safe-to-substitute value
		 */
		private static function _escapeFieldValue( mixed $value, bool $isHtml ): string {
		    $safe = $isHtml === true
		        ? \Nino\Html::sanitizeHtml( strval( $value ) )
		        : htmlspecialchars( strval( $value ), ENT_QUOTES, 'UTF-8' );
		    return str_replace( '[', '&#91;', $safe );
		}

		/**
		 *	Replace elements shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcodeElements( array &$appData, array $args ): string {

			$uri			= $args[0] ?? '';
			$content	= $args['content'] ?? '';
			$locale 	= $args['locale'] ?? '';
			$callback	= $args['callback'] ?? '';
			$limit		= (int) ( $args['limit'] ?? -1 );
			$query		= $args['query'] ?? '';
			$queryArr	= [];

			// Parse query array - a pair without '=' (query="foo") used to read
			// an undefined index, and the error handler turns that warning into
			// a 500 for the entire page. Limit 2 so a value may contain '='.
			if( $query !== '' )
				foreach( explode( '&', $query ) as $qParts ) {

					$qSubparts = explode( '=', $qParts, 2 );

					if( count( $qSubparts ) !== 2 || $qSubparts[0] === '' )
						continue;

					$queryArr[$qSubparts[0]] = $qSubparts[1];
				}

			$result = \Nino\Elements::queryElements( $appData, $uri, $queryArr, $locale, [] );
			if( $result === [] )
				return '';

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			if( $limit > 0 )
				$result = array_slice( $result, 0, $limit );

			$html = '';

			$id = 0;

			$model = \Nino\Elements::getElementModel( $appData, $uri );

			foreach( $result as $element ) {

				$fills = [
					['[[.id]]'],
					[$id],
				];

				foreach( $element as $key => $value ) {
					if( is_scalar( $value ) === false )
						continue;

					$isHtml = ( isset( $model[$key] ) && isset( $model[$key]['html'] ) && $model[$key]['html'] === true );

					$fills[0][] = '[['. $key. ']]';
					$fills[1][] = self::_escapeFieldValue( $value, $isHtml );
				}

				$html .= str_replace( $fills[0], $fills[1], $content );

				$id++;
			}

			return $html;
		}
	}

}
