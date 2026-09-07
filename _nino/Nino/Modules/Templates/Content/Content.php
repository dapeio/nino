<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\Content	Native-locale quick fill and schema-safe Element Type creation for a section
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

	/**
	 *	Native-locale quick fill and schema-safe Element-Type creation. Full
	 *	element CRUD remains the workbench's own Elements panel and is linked
	 *	directly from the selected section.
	 */
	class Content {

		private const string KEY_PATTERN = '#^/[A-Za-z0-9][A-Za-z0-9_./-]*$#';
		private const string GENERATED_KEY_PATTERN = '#^/page-[a-z][a-z0-9-]*/[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#';

		public static function actions(): array {
			return [
				'content/keys'		=> [ self::class, 'apiKeys' ],
				'content/fields'		=> [ self::class, 'apiFields' ],
				'content/save'			=> [ self::class, 'apiSave' ],
				'content/types'			=> [ self::class, 'apiTypes' ],
				'content/type-create'	=> [ self::class, 'apiCreateType' ],
				'content/images'		=> [ self::class, 'apiImages' ],
				'content/image-create'	=> [ self::class, 'apiCreateImage' ],
			];
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'content/save'				=> 'Saved native content of a template section',
				'content/type-create'	=> 'Created element type "'. (string) ( $data['uri'] ?? '' ). '" for a template section',
				'content/image-create'	=> 'Created image slot "'. (string) ( $data['uri'] ?? '' ). '" for a template section',
				default	=> '',
			};
		}

		public static function apiKeys( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$native = \Nino\Locales::getNativeLocale( $appData );
			$entries = [];
			foreach( \Nino\Text::entries( $appData, true ) as $entry ) {
				$key = (string) ( $entry['key'] ?? '' );
				if( self::_validKey( $key ) === false )
					continue;
				$entries[] = [
					'key' => $key,
					'global' => ( $entry['global'] ?? false ) === true,
					'blacklisted' => ( $entry['blacklisted'] ?? false ) === true,
					'value' => (string) ( ( $entry['global'] ?? false ) === true
						? ( $entry['values']['*'] ?? '' )
						: ( $entry['values'][$native] ?? '' ) ),
				];
			}

			\Nino\Http::ok( $request, [ 'nativeLocale' => $native, 'entries' => $entries ] );
		}

		public static function apiFields( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$keys = array_values( array_unique( array_map( 'strval', (array) ( Admin::postData()['keys'] ?? [] ) ) ) );
			if( count( $keys ) > 100 ) {
				\Nino\Http::fail( $request, 400, 'too many text keys' );
				return;
			}

			$native = \Nino\Locales::getNativeLocale( $appData );
			$fields = [];

			foreach( $keys as $key ) {
				if( self::_validKey( $key ) === false ) {
					\Nino\Http::fail( $request, 400, 'invalid textfill key' );
					return;
				}

				$entry = \Nino\Text::entry( $appData, $key );
				$fields[] = [
					'key' => $key,
					'exists' => $entry !== null,
					'global' => ( $entry['global'] ?? false ) === true,
					'value' => $entry === null ? '' : (string) ( $entry['global'] === true ? ( $entry['values']['*'] ?? '' ) : ( $entry['values'][$native] ?? '' ) ),
				];
			}

			\Nino\Http::ok( $request, [ 'nativeLocale' => $native, 'fields' => $fields ] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data = Admin::postData();
			$items = is_array( $data['items'] ?? null ) ? $data['items'] : [];
			if( count( $items ) > 100 ) {
				\Nino\Http::fail( $request, 400, 'too many text values' );
				return;
			}

			$native = \Nino\Locales::getNativeLocale( $appData );
			$missing = [];
			$clean = [];

			foreach( $items as $item ) {
				if( is_array( $item ) === false ) {
					\Nino\Http::fail( $request, 400, 'invalid text value' );
					return;
				}
				$key = (string) ( $item['key'] ?? '' );
				$value = (string) ( $item['value'] ?? '' );

				if( self::_validKey( $key ) === false ) {
					\Nino\Http::fail( $request, 400, 'invalid textfill key' );
					return;
				}

				$entry = \Nino\Text::entry( $appData, $key );
				if( $entry === null && ( $item['create'] ?? false ) !== true ) {
					\Nino\Http::fail( $request, 400, 'an existing textfill binding no longer exists' );
					return;
				}
				if( $entry === null && preg_match( self::GENERATED_KEY_PATTERN, $key ) !== 1 ) {
					\Nino\Http::fail( $request, 400, 'new textfills must use the generated page/section prefix' );
					return;
				}
				if( $entry === null )
					$missing['[['. $key. ']]'] = '';

				$clean[] = [
					'key' => $key,
					'locale' => ( $entry['global'] ?? false ) === true ? '*' : $native,
					'value' => $value,
				];
			}

			if( $missing !== [] ) {
				$written = \Nino\Filesystem::mutate( $appData, '/text/'. $native. '.php', function( mixed $content ) use ( $missing ): array {
					return array_merge( is_array( $content ) ? $content : [], $missing );
				} );
				if( $written === false ) {
					\Nino\Http::fail( $request, 500, 'could not create native text keys' );
					return;
				}
			}

			$results = \Nino\Text::saveBatch( $appData, $clean, true );
			if( array_filter( $results, fn( array $result ): bool => ( $result['ok'] ?? false ) !== true ) !== [] ) {
				\Nino\Http::fail( $request, 500, 'could not save every native text value' );
				return;
			}
			\Nino\Http::ok( $request, [ 'nativeLocale' => $native, 'results' => $results ] );
		}

		public static function apiTypes( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;
			\Nino\Modules\Elements\Admin::apiTypes( $appData, $request );
		}

		public static function apiImages( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;
			\Nino\Modules\Images\Slots::apiList( $appData, $request );
		}

		public static function apiCreateType( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data = Admin::postData();
			$preset = Library::preset( (string) ( $data['preset'] ?? '' ) );
			$area = is_array( $preset )
				? AreaComposer::collectionDefinition( $preset, (string) ( $data['area'] ?? '' ) )
				: null;
			$module = Composer::modules()[(string) ( $data['module'] ?? '' )] ?? null;
			$uri = (string) ( $data['uri'] ?? '' );

			if( ( $area === null && ( $module['source'] ?? '' ) !== 'elements' ) || preg_match( '/^[a-z][a-z0-9_-]*$/', $uri ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid preset area or element type' );
				return;
			}

			$_POST['data'] = json_encode( [
				'uri' => $uri,
				'title' => trim( (string) ( $data['title'] ?? '' ) ) ?: ( $area['typeTitle'] ?? ucwords( str_replace( '-', ' ', $uri ) ) ),
				'model' => $area['model'] ?? $module['model'],
			] );

			\Nino\Modules\Elements\Types::apiCreate( $appData, $request );
		}

		public static function apiCreateImage( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data = Admin::postData();
			$preset = Library::preset( (string) ( $data['preset'] ?? '' ) );
			$uri = (string) ( $data['uri'] ?? '' );
			$slot = (string) ( $data['slot'] ?? '' );
			$component = (string) ( $data['component'] ?? '' );
			// Every preset in the library is an Area preset (Library::presets()
			// skips anything else), so the slot is either the section background
			// or a component's own image property
			$definition = $preset === null
				? null
				: ( $slot === 'background'
					? [ 'label' => 'Background image', 'width' => 1920, 'height' => 1080 ]
					: AreaComposer::imageDefinition(
						$preset,
						(string) ( $data['area'] ?? '' ),
						$component,
						(string) ( $data['property'] ?? '' )
					) );
			$expectedSuffix = $slot === 'background' ? 'background' : $component;

			if( $definition === null || preg_match( '#^/page-[a-z][a-z0-9-]*/[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#', $uri ) !== 1 || str_ends_with( $uri, '/'. $expectedSuffix ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid page image slot' );
				return;
			}

			$_POST['data'] = json_encode( [
				'uri' => $uri,
				'label' => trim( (string) ( $data['label'] ?? '' ) ) ?: $definition['label'],
				'width' => $definition['width'],
				'height' => $definition['height'],
			] );

			\Nino\Modules\Images\Slots::apiCreate( $appData, $request );
		}

		private static function _validKey( string $key ): bool {
			return strlen( $key ) <= 240
				&& preg_match( self::KEY_PATTERN, $key ) === 1
				&& str_contains( $key, '..' ) === false
				&& str_contains( $key, '//' ) === false;
		}
	}
}
