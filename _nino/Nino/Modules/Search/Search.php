<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Search				A small weighted fuzzy index for Elements
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	class Search {

		private const array WEIGHTS = [
			0 => 1.00,
			1 => 0.70,
			2 => 0.45,
			3 => 0.25,
		];

		private const int MAX_QUERY_LENGTH = 256;
		private const int MAX_QUERY_TOKENS = 12;

		/**
		 *	Register the write-time refresh. Building the initial set stays an
		 *	explicit action in /_admin > Config; init itself performs no I/O.
		 */
		public static function init( array &$appData ): void {
			\Nino\Callbacks::registerCallback( $appData, '/nino/elements/committed', [ self::class, 'callbackElementsCommitted' ] );
		}

		/**
		 *	Refresh the changed configured type after its Elements write committed.
		 */
		public static function callbackElementsCommitted( array &$appData, array &$change ): void {

			$typeUri = self::_typeUri( $change['type'] ?? null );
			if( $typeUri === null )
				return;

			$types = self::_configuredTypes( $appData );
			if( isset( $types[$typeUri] ) === false )
				return;

			if( self::_createIndex( $appData, $typeUri, $types[$typeUri] ) === false )
				trigger_error( 'Search index for \''. $typeUri. '\' could not be written.' );
		}

		/**
		 *	Recreate every index named by /nino/elements/index. This is the API
		 *	behind /_admin > Config's "Create searchindex" button.
		 *
		 *	@return array{created:int,elements:int,failed:array}
		 */
		public static function createIndexes( array &$appData ): array {

			$result = [
				'created'	=> 0,
				'elements'	=> 0,
				'failed'		=> [],
			];

			foreach( self::_configuredTypes( $appData ) as $typeUri => $fields ) {

				$count = self::_createIndex( $appData, $typeUri, $fields );
				if( $count === false ) {
					$result['failed'][] = $typeUri;
					continue;
				}

				$result['created']++;
				$result['elements'] += $count;
			}

			return $result;
		}

		/**
		 *	Search the current locale and return canonical Elements, ordered by a
		 *	weighted fuzzy score. A missing/invalid index is simply an empty result:
		 *	version 0.9 never creates or repairs files from a read operation.
		 */
		public static function getElements( array &$appData, string $elementType, string $searchString ): array {

			$typeUri = self::_typeUri( $elementType );
			if( $typeUri === null )
				return [];

			$types = self::_configuredTypes( $appData );
			if( isset( $types[$typeUri] ) === false )
				return [];

			$query = self::_normalize( (string) mb_substr( $searchString, 0, self::MAX_QUERY_LENGTH ) );
			$tokens = array_slice( self::_tokens( $query ), 0, self::MAX_QUERY_TOKENS );
			if( $query === '' || $tokens === [] )
				return [];

			$index = self::_readIndex( $appData, $typeUri );
			$locale = \Nino\Locales::getCurrentLocale( $appData );
			$documents = $index[$locale] ?? null;
			if( is_array( $documents ) === false )
				return [];

			$hits = [];
			foreach( $documents as $uri => $fields ) {

				if( is_string( $uri ) === false || is_array( $fields ) === false )
					continue;

				$score = self::_score( $fields, $query, $tokens );
				if( $score !== null )
					$hits[] = [ 'uri' => $uri, 'score' => $score ];
			}

			usort( $hits, function( array $a, array $b ): int {
				$score = $b['score'] <=> $a['score'];
				return $score !== 0 ? $score : strcmp( $a['uri'], $b['uri'] );
			} );

			$elements = [];
			foreach( $hits as $hit ) {
				$element = \Nino\Elements::getElement( $appData, $hit['uri'], $locale );
				if( is_array( $element ) === true )
					$elements[] = $element;
			}

			return $elements;
		}

		/**
		 *	Validated configured types as canonical /type => priority => field.
		 */
		private static function _configuredTypes( array &$appData ): array {

			$config = $appData['/nino/elements/index'] ?? null;
			if( is_array( $config ) === false )
				return [];

			$types = [];
			foreach( $config as $rawType => $rawFields ) {

				$typeUri = self::_typeUri( $rawType );
				if( $typeUri === null || is_array( $rawFields ) === false )
					continue;

				$slug = ltrim( $typeUri, '/' );
				if( \Nino\Filesystem::fileExists( $appData, '/elements/'. $slug. '.php' ) === false )
					continue;

				$model = \Nino\Elements::getElementModel( $appData, $typeUri );
				$fields = [];
				foreach( $rawFields as $priority => $field )
					if( is_int( $priority ) === true && isset( self::WEIGHTS[$priority] ) === true
						&& is_string( $field ) === true && isset( $model[$field] ) === true )
						$fields[$priority] = $field;

				if( $fields !== [] ) {
					ksort( $fields );
					$types[$typeUri] = $fields;
				}
			}

			ksort( $types );
			return $types;
		}

		/**
		 *	One flat Elements type only. Both "services" and "/services" are
		 *	accepted at the API/config boundary and canonicalized to "/services".
		 */
		private static function _typeUri( mixed $type ): ?string {

			if( is_string( $type ) === false )
				return null;

			$slug = trim( $type, '/' );
			return preg_match( '/^[a-z][a-z0-9_-]*$/', $slug ) === 1 ? '/'. $slug : null;
		}

		/**
		 *	Create the complete locale index for one configured type.
		 *
		 *	@return int|false Number of distinct indexed Elements, or false on write failure
		 */
		private static function _createIndex( array &$appData, string $typeUri, array $fields ): int|false {

			$index = [];
			$elementUris = [];

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {

				if( is_string( $locale ) === false || \Nino\Locales::verifyLocale( $appData, $locale ) === false )
					continue;

				$documents = [];
				foreach( \Nino\Elements::queryElements( $appData, $typeUri, [], $locale, [] ) as $element ) {

					$uri = $element['.uri'] ?? null;
					if( is_string( $uri ) === false )
						continue;

					$document = [];
					foreach( $fields as $priority => $field ) {
						$value = self::_normalizeValue( $element[$field] ?? null );
						if( $value !== '' )
							$document[$priority] = $value;
					}

					if( $document !== [] ) {
						$documents[$uri] = $document;
						$elementUris[$uri] = true;
					}
				}

				ksort( $documents );
				$index[$locale] = $documents;
			}

			ksort( $index );
			return self::_writeIndex( $appData, $typeUri, $index ) === true ? count( $elementUris ) : false;
		}

		/**
		 *	Version 0.9 intentionally uses one direct full-file replacement: no
		 *	index mutation, side-car lock, revision or signature.
		 */
		private static function _writeIndex( array &$appData, string $typeUri, array $index ): bool {

			\Nino\Filesystem::forceDir( $appData, '/data' );
			$path = \Nino\Filesystem::path( $appData, self::_indexPath( $typeUri ) );
			$content = '<?php return '. var_export( $index, true ). ';';
			$written = @file_put_contents( $path, $content );

			if( $written === false || $written !== strlen( $content ) )
				return false;

			if( function_exists( 'opcache_invalidate' ) === true )
				opcache_invalidate( $path, true );

			return true;
		}

		/**
		 *	Read only. A broken or missing index stays broken/missing until the
		 *	Config button is pressed or a configured Elements write refreshes it.
		 */
		private static function _readIndex( array &$appData, string $typeUri ): array {

			$path = \Nino\Filesystem::path( $appData, self::_indexPath( $typeUri ) );
			if( is_file( $path ) === false )
				return [];

			try {
				$index = @include $path;
			}
			catch( \Throwable ) {
				return [];
			}

			return is_array( $index ) === true ? $index : [];
		}

		private static function _indexPath( string $typeUri ): string {
			return '/data/index-'. ltrim( $typeUri, '/' ). '.php';
		}

		/**
		 *	Flatten a normal Elements scalar or array field into searchable text.
		 */
		private static function _normalizeValue( mixed $value ): string {

			if( is_array( $value ) === true ) {
				$parts = [];
				array_walk_recursive( $value, function( mixed $part ) use ( &$parts ): void {
					if( is_scalar( $part ) === true && is_bool( $part ) === false )
						$parts[] = (string) $part;
				} );
				return self::_normalize( implode( ' ', $parts ) );
			}

			return is_scalar( $value ) === true && is_bool( $value ) === false
				? self::_normalize( (string) $value )
				: '';
		}

		private static function _normalize( string $value ): string {

			$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$value = mb_strtolower( strip_tags( $value ), 'UTF-8' );
			$value = strtr( $value, [ 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' ] );
			$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
			return is_string( $value ) === true ? trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' ) : '';
		}

		private static function _tokens( string $value ): array {
			return array_values( array_unique( array_filter( explode( ' ', $value ), fn( string $token ): bool => $token !== '' ) ) );
		}

		/**
		 *	Every query token must reach its length-dependent fuzzy threshold.
		 *	Priority weights affect ranking, not whether a word is considered a hit.
		 */
		private static function _score( array $fields, string $query, array $queryTokens ): ?float {

			$fieldTokens = [];
			$score = 0.0;

			foreach( $queryTokens as $queryToken ) {

				$bestSimilarity = 0.0;
				$bestWeighted = 0.0;

				foreach( $fields as $priority => $text ) {

					if( isset( self::WEIGHTS[$priority] ) === false || is_string( $text ) === false )
						continue;

					$fieldTokens[$priority] = $fieldTokens[$priority] ?? self::_tokens( $text );
					foreach( $fieldTokens[$priority] as $candidate ) {
						$similarity = self::_similarity( $queryToken, $candidate );
						$bestSimilarity = max( $bestSimilarity, $similarity );
						$bestWeighted = max( $bestWeighted, $similarity * self::WEIGHTS[$priority] );
					}
				}

				if( $bestSimilarity < self::_threshold( $queryToken ) )
					return null;

				$score += $bestWeighted;
			}

			// A complete phrase is more intentional than the same words scattered
			// through several fields; reward it without making it mandatory.
			foreach( $fields as $priority => $text )
				if( isset( self::WEIGHTS[$priority] ) === true && is_string( $text ) === true && str_contains( $text, $query ) === true )
					$score += self::WEIGHTS[$priority] * 1.5;

			return $score;
		}

		/**
		 *	Exact/prefix/substring shortcuts plus Unicode bigram Dice similarity.
		 */
		private static function _similarity( string $needle, string $candidate ): float {

			if( $needle === $candidate )
				return 1.0;

			$needleLength = mb_strlen( $needle, 'UTF-8' );
			$candidateLength = mb_strlen( $candidate, 'UTF-8' );
			$shortest = min( $needleLength, $candidateLength );
			$longest = max( $needleLength, $candidateLength );

			// One- and two-character terms are useful ("AI", years split into
			// numbers), but fuzzy matching them produces mostly noise.
			if( $shortest <= 2 )
				return 0.0;

			if( str_starts_with( $candidate, $needle ) === true || str_starts_with( $needle, $candidate ) === true )
				return 0.94;

			if( $shortest >= 4 && ( str_contains( $candidate, $needle ) === true || str_contains( $needle, $candidate ) === true ) )
				return 0.86;

			if( abs( $needleLength - $candidateLength ) > max( 2, (int) ceil( $longest * 0.4 ) ) )
				return 0.0;

			return self::_dice( $needle, $candidate );
		}

		private static function _threshold( string $token ): float {
			$length = mb_strlen( $token, 'UTF-8' );
			return match( true ) {
				$length <= 2 => 1.0,
				$length === 3 => 0.70,
				$length === 4 => 0.60,
				default => 0.52,
			};
		}

		private static function _dice( string $a, string $b ): float {

			$aGrams = self::_bigrams( $a );
			$bGrams = self::_bigrams( $b );
			if( $aGrams === [] || $bGrams === [] )
				return 0.0;

			$aCounts = array_count_values( $aGrams );
			$bCounts = array_count_values( $bGrams );
			$common = 0;
			foreach( $aCounts as $gram => $count )
				$common += min( $count, $bCounts[$gram] ?? 0 );

			return ( 2 * $common ) / ( count( $aGrams ) + count( $bGrams ) );
		}

		private static function _bigrams( string $word ): array {

			$characters = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
			if( is_array( $characters ) === false || $characters === [] )
				return [];

			array_unshift( $characters, '^' );
			$characters[] = '$';

			$grams = [];
			for( $i = 0; $i < count( $characters ) - 1; $i++ )
				$grams[] = $characters[$i]. $characters[$i + 1];

			return $grams;
		}
	}
}
