<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Elements				see _nino/Nino/Modules/Modules.php for the
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
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
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

			$element['.id'] = 0;

			// A map, not two parallel lists: the blank alt texts below go in
			// first and the element's own values over them. Two lists would let
			// the first entry for a key win in str_replace(), the empty one
			$fills = self::_altSeed( $model );

			foreach( $element as $key => $value ) {
				if( is_scalar( $value ) === false )
					continue;

				$fills['[['. $key. ']]'] = self::_escapeFieldValue( $value, $model[$key] ?? [] );
			}

			return str_replace( array_keys( $fills ), array_values( $fills ), $content );
		}

		/**
		 *	The alt texts of a type's image fields as blank fills. An image
		 *	field names the string field that holds its alt text (model
		 *	property 'alt'), and an element - or one language of it - that has no
		 *	value there carries no key at all, so the template would show the
		 *	field literally: alt="[[imageAlt]]". Blank, it is alt="", which is
		 *	what a decorative picture is and what the form's hint promises
		 *	for an empty field. A link to a field that is not a plain string
		 *	written per language is not read (the Element Types tab drops one
		 *	on save, a hand-edited model is not trusted)
		 *
		 *	@param		mixed			$model				The type's model
		 *
		 *	@return 	array										[ '[[<altKey>]]' => '' ]
		 */
		private static function _altSeed( mixed $model ): array {

			$seed = [];

			if( is_array( $model ) === false )
				return $seed;

			foreach( $model as $key => $field ) {

				if( ( $field['type'] ?? '' ) !== 'image' || is_string( $field['alt'] ?? null ) === false || $field['alt'] === $key )
					continue;

				$target = $model[ $field['alt'] ] ?? null;

				if( is_array( $target ) === true && ( $target['type'] ?? '' ) === 'string' && ( $target['locale'] ?? false ) === true && ( $target['html'] ?? false ) !== true )
					$seed['[['. $field['alt']. ']]'] = '';
			}

			return $seed;
		}

		/**
		 *	Escape a single element field value before it is substituted into a
		 *	template. Values are editor content, not developer-authored markup -
		 *	htmlspecialchars() neutralizes raw HTML/script (matches
		 *	Modules\Components::escape()'s pattern), and the extra '['
		 *	swap stops an editor-supplied "[[...]]" or "[shortcode]" string from
		 *	being interpreted when the surrounding content is re-rendered by
		 *	Html::_doShortcode() right after this callback returns.
		 *
		 *	What a field keeps is its model's to say: 'html' => true keeps the
		 *	whitelisted inline tags and drops everything else, which is what
		 *	makes such a field rich text rather than markup an editor may write
		 *	freely, 'blocks' adds paragraphs and lists to it, and 'breaks' turns
		 *	the line breaks of a plain field into <br>. All of that is
		 *	\Nino\Html::fieldValue(), which this only hands the field to: it is
		 *	the same rule a feature that draws a field itself applies.
		 *
		 *	@param		mixed			$value				Raw scalar field value
		 *	@param		mixed			$field				The field's model entry, [] for a value that has none
		 *
		 *	@return 	string									Safe-to-substitute value
		 */
		private static function _escapeFieldValue( mixed $value, mixed $field ): string {
			return \Nino\Html::fieldValue( $value, is_array( $field ) === true ? $field : [] );
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
		 *	The elements an [elements] block - or a stack, see
		 *	\Nino\Modules\Components::renderStack() - loops over: queried with
		 *	the shortcode's own arguments, handed to its callback, then cut to
		 *	the offset and the limit. One place, so that the two read the same
		 *	arguments the same way
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments of the shortcode: the type uri at 0, and
		 *																	locale, callback, sort, offset, limit and query
		 *
		 *	@return 	array										The elements, [] when the type has none
		 */
		public static function queryLoop( array &$appData, array $args ): array {

			$uri			= $args[0] ?? '';
			$locale 	= $args['locale'] ?? '';
			$callback	= $args['callback'] ?? '';
			$sort			= (string) ( $args['sort'] ?? '' );
			$offset		= (int) ( $args['offset'] ?? 0 );
			$limit		= (int) ( $args['limit'] ?? -1 );
			$queryArr	= self::_parseQuery( $args['query'] ?? '' );

			// Sorted by the query, cut here: offset and limit come after the
			// callback, which may drop or reorder hits of its own, so a page
			// is a page of what the callback let through
			$result = \Nino\Elements::queryElements( $appData, $uri, $queryArr, $locale, [], [ 'sort' => $sort ] );
			if( $result === [] )
				return [];

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			if( $offset > 0 )
				$result = array_slice( $result, $offset );

			if( $limit > 0 )
				$result = array_slice( $result, 0, $limit );

			return $result;
		}

		/**
		 *	Replace elements shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
		 */
		public static function doShortcodeElements( array &$appData, array $args ): string {

			$uri			= $args[0] ?? '';
			$content	= $args['content'] ?? '';

			$result = self::queryLoop( $appData, $args );
			if( $result === [] )
				return '';

			$html = '';

			$id = 0;

			$model = \Nino\Elements::getElementModel( $appData, $uri );
			$altSeed = self::_altSeed( $model );

			foreach( $result as $element ) {

				$fills = [ '[[.id]]' => $id ] + $altSeed;

				foreach( $element as $key => $value ) {
					if( is_scalar( $value ) === false )
						continue;

					$fills['[['. $key. ']]'] = self::_escapeFieldValue( $value, $model[$key] ?? [] );
				}

				$html .= str_replace( array_keys( $fills ), array_values( $fills ), $content );

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
		 *	@param		array			$args					Shortcode arguments
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
					[ (string) $id, self::_escapeFieldValue( $row['value'], [] ), (string) $row['count'] ],
				];

				$html .= str_replace( $fills[0], $fills[1], $content );

				$id++;
			}

			return $html;
		}
	}

}
