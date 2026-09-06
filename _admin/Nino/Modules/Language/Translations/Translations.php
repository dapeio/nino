<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Language\Translations	Translations tab: export and import a whole language
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Language {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin						Batch translation hand-off for the complete native
	 *										content layer: public textfills and locale-scoped
	 *										element fields. The JSON deliberately contains no
	 *										global/technical values, images or element structure.
	 *										Import is merge-only and validates every posted path
	 *										against a fresh native export before it writes.
	 *
	 *	@package					Dape/Nino
	 *	@author					David Perchermeier <mail@dape.io>
	 *	@link						https://github.com/dapeio/nino
	 */
	class Translations {

		public const string MANAGE_PERM = '/_admin/translations/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		private const string FORMAT = 'nino.translation';
		private const int VERSION = 1;

		// Locale-flagged element fields whose value is not translatable text
		// and therefore never leaves in an export, nor is accepted back from
		// one. An image field holds a generated filename, an element field a
		// referenced element's uri: both may legitimately differ per locale,
		// but neither is something a translator can translate - and both are
		// picked in the element form, where the choice is shown as what it is
		// rather than as a string to overwrite
		private const array UNTRANSLATABLE_TYPES = [ 'image', 'element' ];

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'translations/info' 		=> [ self::class, 'apiInfo' ],
				'translations/export' 	=> [ self::class, 'apiExport' ],
				'translations/import' 	=> [ self::class, 'apiImport' ],
			];
		}

		// A tab of the Language pane (see \Nino\Modules\Language\Admin::tabs()) - the other half
		// of the same workflow, with its own permission
		public static function nav(): array {
			return [ 'translations', '/_admin/nav/translations', 10, 'system' ];
		}

		public static function panes(): array {
			return [ 'translations-content' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/translations.js' ) ];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'translations/import'	=> 'Import Translation '. ( $data['targetLocale'] ?? '' ),
				default	=> '',
			};
		}

		/**
		 *	Report the fixed source language, possible targets and package size
		 */
		public static function apiInfo( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$translation = self::_translationData( $appData );

			$elementFields = 0;
			foreach( $translation['elements'] as $elements )
				foreach( $elements as $fields )
					$elementFields += count( $fields );

			\Nino\Http::ok( $request, [
				'nativeLocale' 	=> $translation['sourceLocale'],
				'locales' 			=> \Nino\Locales::getAvailableLocales( $appData ),
				'textCount' 		=> count( $translation['text'] ),
				'elementCount' 	=> $elementFields,
			] );
		}

		/**
		 *	Return the versioned native-language hand-off document
		 */
		public static function apiExport( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, self::_translationData( $appData ) );
		}

		/**
		 *	Merge a translated hand-off document into one configured locale.
		 *	The document's keys are untrusted: only paths which still occur in
		 *	a freshly generated native package may reach the write APIs.
		 */
		public static function apiImport( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 			= \Nino\Admin\Admin::postData();
			$targetLocale = (string) ( $data['targetLocale'] ?? '' );
			$translation 	= $data['translation'] ?? null;

			if( \Nino\Locales::verifyLocale( $appData, $targetLocale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid target locale' );
				return;
			}

			if(
				is_array( $translation ) === false ||
				( $translation['format'] ?? null ) !== self::FORMAT ||
				( $translation['version'] ?? null ) !== self::VERSION ||
				( $translation['sourceLocale'] ?? null ) !== \Nino\Locales::getNativeLocale( $appData ) ||
				is_array( $translation['text'] ?? null ) === false ||
				is_array( $translation['elements'] ?? null ) === false
			) {
				\Nino\Http::fail( $request, 400, 'invalid or incompatible translation document' );
				return;
			}

			$reference = self::_translationData( $appData );

			$textItems 		= [];
			$textSkipped 	= 0;

			foreach( $translation['text'] as $key => $value ) {
				if( is_string( $key ) === false || array_key_exists( $key, $reference['text'] ) === false || is_string( $value ) === false ) {
					$textSkipped++;
					continue;
				}
				$textItems[] = [ 'key' => $key, 'locale' => $targetLocale, 'value' => $value ];
			}

			$textImported = 0;
			foreach( \Nino\Text::saveBatch( $appData, $textItems, false ) as $result ) {
				if( ( $result['ok'] ?? false ) === true )
					$textImported++;
				else
					$textSkipped++;
			}

			$elementImported = 0;
			$elementSkipped 	= 0;
			$errors 				= [];

			foreach( $translation['elements'] as $type => $submittedElements ) {

				$referenceElements = is_string( $type ) ? ( $reference['elements'][$type] ?? null ) : null;
				if( is_array( $submittedElements ) === false || is_array( $referenceElements ) === false ) {
					$elementSkipped += self::_submittedFieldCount( $submittedElements );
					continue;
				}

				$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
				$model 		= is_array( $typeData['model'] ?? null ) ? $typeData['model'] : [];
				$changes 	= [];

				foreach( $submittedElements as $elementUri => $submittedFields ) {

					$elementUri = is_string( $elementUri ) || is_int( $elementUri ) ? (string) $elementUri : '';
					$referenceFields = $elementUri !== '' ? ( $referenceElements[$elementUri] ?? null ) : null;
					if( is_array( $submittedFields ) === false || is_array( $referenceFields ) === false ) {
						$elementSkipped += is_array( $submittedFields ) ? count( $submittedFields ) : 1;
						continue;
					}

					foreach( $submittedFields as $key => $value ) {
						$key = is_string( $key ) || is_int( $key ) ? (string) $key : '';
						$field = $key !== '' ? ( $model[$key] ?? null ) : null;
						if(
							is_array( $field ) === false ||
							array_key_exists( $key, $referenceFields ) === false ||
							( $field['locale'] ?? false ) !== true ||
							in_array( $field['type'] ?? '', self::UNTRANSLATABLE_TYPES, true ) === true
						) {
							$elementSkipped++;
							continue;
						}

						$value = self::_sanitizeElementValue( $value, $field );
						if( self::_validElementValue( $value, $field ) === false ) {
							$elementSkipped++;
							continue;
						}

						$changes[$elementUri][$key] = $value;
					}
				}

				if( $changes === [] )
					continue;

				// Old hand-authored type files sometimes only carry a native
				// bucket. The kernel needs the empty global identity stub to see
				// that same element from a target locale which has no bucket yet.
				$missingStubs = array_values( array_filter( array_keys( $changes ), fn( string|int $uri ): bool => isset( $typeData['*'][$uri] ) === false ) );

				if( $missingStubs !== [] ) {
					$stubWritten = \Nino\Filesystem::mutate( $appData, '/elements/'. $type. '.php', function( array $fresh ) use ( $missingStubs ): array {
						foreach( $missingStubs as $uri )
							$fresh['*'][$uri] = $fresh['*'][$uri] ?? [];
						return $fresh;
					} );

					if( $stubWritten === false ) {
						foreach( $changes as $fields )
							$elementSkipped += count( $fields );
						$errors[] = $type. ': element identities could not be written';
						continue;
					}
					unset( $appData['./nino/elements/cache'] );
				}

				foreach( $changes as $elementUri => $fields ) {
					$result = \Nino\Elements::updateElement( $appData, '/'. $type. '/'. $elementUri, $fields, $targetLocale );
					if( is_array( $result ) === true )
						$elementImported += count( $fields );
					else {
						$elementSkipped += count( $fields );
						$errors[] = $type. '/'. $elementUri. ': values were rejected';
					}
				}
			}

			\Nino\Http::ok( $request, [
				'targetLocale' 	=> $targetLocale,
				'text' 				=> [ 'imported' => $textImported, 'skipped' => $textSkipped ],
				'elements' 		=> [ 'imported' => $elementImported, 'skipped' => $elementSkipped ],
				'errors' 			=> $errors,
			] );
		}

		/**
		 *	Build the deterministic source document from raw native buckets
		 */
		private static function _translationData( array &$appData ): array {

			$native = \Nino\Locales::getNativeLocale( $appData );
			$text 	= [];

			foreach( \Nino\Text::entries( $appData, false ) as $entry )
				if( $entry['global'] === false && array_key_exists( $native, $entry['values'] ) === true && $entry['values'][$native] !== null )
					$text[$entry['key']] = $entry['values'][$native];

			$elements = [];

			foreach( glob( \Nino\Filesystem::path( $appData, '/elements' ). '/*.php' ) ?: [] as $file ) {

				$type 		= basename( $file, '.php' );
				$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
				$model 		= is_array( $typeData['model'] ?? null ) ? $typeData['model'] : [];
				$nativeData = is_array( $typeData[$native] ?? null ) ? $typeData[$native] : [];

				foreach( $nativeData as $elementUri => $rawFields ) {
					$elementUri = (string) $elementUri;
					if( $elementUri === '*' || is_array( $rawFields ) === false )
						continue;

					foreach( $model as $key => $field )
						if(
							is_array( $field ) === true &&
							( $field['locale'] ?? false ) === true &&
							in_array( $field['type'] ?? '', self::UNTRANSLATABLE_TYPES, true ) === false &&
							array_key_exists( $key, $rawFields ) === true
						)
							$elements[$type][$elementUri][$key] = $rawFields[$key];
				}
			}

			ksort( $text );
			ksort( $elements );
			foreach( $elements as &$typeElements ) {
				ksort( $typeElements );
				foreach( $typeElements as &$fields )
					ksort( $fields );
				unset( $fields );
			}
			unset( $typeElements );

			return [
				'format' 			=> self::FORMAT,
				'version' 			=> self::VERSION,
				'sourceLocale' 	=> $native,
				'instructions' 	=> [
					'Translate values only. Never change, add, or remove object keys.',
					'Keep JSON value types unchanged and preserve HTML tags, URLs, [[placeholders]], [shortcodes], and identifiers.',
				],
				'text' 				=> $text,
				'elements' 		=> $elements,
			];
		}

		/**
		 *	Normalize external values before the kernel validates/stores them
		 */
		private static function _sanitizeElementValue( mixed $value, array $field ): mixed {

			$type = (string) ( $field['type'] ?? '' );

			if( is_string( $value ) === true )
				return $type === 'string' && ( $field['html'] ?? false ) === true
					? \Nino\Html::sanitizeHtml( $value )
					: strip_tags( $value );

			if( $type === 'array' && is_array( $value ) === true )
				return array_map( function( mixed $item ): mixed {
					if( is_string( $item ) === true )
						return strip_tags( $item );
					if( is_array( $item ) === true )
						return self::_sanitizeElementValue( $item, [ 'type' => 'array' ] );
					return $item;
				}, $value );

			return $value;
		}

		/**
		 *	Reject one malformed field without making the kernel reject all
		 *	valid sibling fields in that element's partial update.
		 */
		private static function _validElementValue( mixed $value, array $field ): bool {

			$type = (string) ( $field['type'] ?? '' );
			$expected = in_array( $type, [ 'date', 'datetime', 'image', 'element' ], true ) ? 'string' : $type;

			if( $type === 'double' && is_int( $value ) === true ) {
				// The kernel accepts and coerces whole-number JSON values too.
			} else if( gettype( $value ) !== $expected )
				return false;

			if( ( $field['required'] ?? false ) === true ) {
				if( is_string( $value ) === true && $value === '' )
					return false;
				if( is_array( $value ) === true && $value === [] )
					return false;
			}

			if( isset( $field['whitelist'] ) === true && in_array( $value, $field['whitelist'] ) === false )
				return false;

			if( is_array( $field['options'] ?? null ) === true && $field['options'] !== [] && in_array( $value, $field['options'], true ) === false )
				return false;

			if( isset( $field['blacklist'] ) === true && in_array( $value, $field['blacklist'] ) === true )
				return false;

			return true;
		}

		/**
		 *	Count field-shaped entries in an unknown submitted element branch
		 */
		private static function _submittedFieldCount( mixed $elements ): int {

			if( is_array( $elements ) === false )
				return 1;

			$count = 0;
			foreach( $elements as $fields )
				$count += is_array( $fields ) ? count( $fields ) : 1;

			return $count;
		}
	}
}
