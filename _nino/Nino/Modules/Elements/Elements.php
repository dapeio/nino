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
			\Nino\Html::addShortcode( $appData, 'elementvalues', [ self::class, 'doShortcodeElementValues' ] );
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
		 *	Parse a shortcode's "query" argument ("key=val&key2=val2") into an
		 *	array, shared by doShortcodeElements() and doShortcodeElementValues().
		 *	A pair without '=' (query="foo") used to read an undefined index, and
		 *	the error handler turns that warning into a 500 for the entire page.
		 *	Limit 2 on the '='-split so a value may itself contain '='.
		 *
		 *	@param		string		$query				Raw "key=val&..." argument
		 *
		 *	@return 	array										Parsed key => value pairs
		 */
		private static function _parseQuery( string $query ): array {

			$queryArr = [];

			if( $query !== '' )
				foreach( explode( '&', $query ) as $qParts ) {

					$qSubparts = explode( '=', $qParts, 2 );

					if( count( $qSubparts ) !== 2 || $qSubparts[0] === '' )
						continue;

					$queryArr[$qSubparts[0]] = $qSubparts[1];
				}

			return $queryArr;
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
			$queryArr	= self::_parseQuery( $args['query'] ?? '' );

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

		/**
		 *	Replace elementvalues shortcode - loops the distinct values of one
		 *	model key across a type's elements (eg. every "category" a Services
		 *	collection uses), each with a usage count. Companion to [elements]:
		 *	that shortcode loops records, this one loops one field's values -
		 *	the piece a client-side category filter's button row needs and that
		 *	queryElements() alone cannot answer (it filters BY a known value, it
		 *	never enumerates which values exist).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	string									Rendered HTML
		 */
		public static function doShortcodeElementValues( array &$appData, array $args ): string {

			$uri					= $args[0] ?? '';
			$content			= $args['content'] ?? '';
			$key					= $args['key'] ?? '';
			$locale				= $args['locale'] ?? '';
			$callback			= $args['callback'] ?? '';
			$limit				= (int) ( $args['limit'] ?? -1 );
			$sort					= $args['sort'] ?? 'value';
			$includeEmpty	= ( $args['includeEmpty'] ?? '' ) === '1';
			$queryArr			= self::_parseQuery( $args['query'] ?? '' );

			if( $key === '' )
				return '';

			$rows = \Nino\Elements::queryElementValues( $appData, $uri, $key, $queryArr, $locale, [] );
			if( $rows === [] )
				return '';

			if( $includeEmpty === false )
				$rows = array_values( array_filter( $rows, static function( array $row ): bool {
					return $row['count'] > 0;
				} ) );

			// 'declared' (or any other value) keeps queryElementValues()'s own
			// order - declared model options first, then observed values.
			if( $sort === 'count' )
				usort( $rows, static function( array $a, array $b ): int {
					return $b['count'] <=> $a['count'];
				} );
			elseif( $sort === 'value' )
				usort( $rows, static function( array $a, array $b ): int {
					return strnatcasecmp( $a['value'], $b['value'] );
				} );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $rows );

			if( $limit > 0 )
				$rows = array_slice( $rows, 0, $limit );

			$html = '';
			$id = 0;

			foreach( $rows as $row ) {

				$fills = [
					[ '[[.id]]', '[[.value]]', '[[.count]]' ],
					[ $id, self::_escapeFieldValue( $row['value'], false ), (string) $row['count'] ],
				];

				$html .= str_replace( $fills[0], $fills[1], $content );

				$id++;
			}

			return $html;
		}
	}

}
