<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\Library	The section presets: manifest-backed entry points for the Section Composer
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

	/**
	 *	Manifest-backed entry points for the named-area Section Composer.
	 *	Only version-3 manifests are exposed; their section templates remain
	 *	code-authored while the UI edits normalized areas and bindings.
	 */
	class Library {

		private const string DIRECTORY = __DIR__. '/../library';
		private const int MAX_PREVIEW_CSS_BYTES = 2_000_000;
		private const int MAX_PREVIEW_FONT_BYTES = 2_000_000;
		private const array PREVIEW_FONT_MIME_TYPES = [
			'woff2'	=> 'font/woff2',
			'woff'	=> 'font/woff',
			'ttf'	=> 'font/ttf',
			'otf'	=> 'font/otf',
		];

		public static function actions(): array {
			return [
				'library/list'		=> [ self::class, 'apiList' ],
				'library/compose'	=> [ self::class, 'apiCompose' ],
				'library/preview'	=> [ self::class, 'apiPreview' ],
			];
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;
			$presets = array_values( self::presets() );
			foreach( $presets as &$preset ) {
				$preset = self::publicPreset( $preset );
				$preset['defaults'] = AreaComposer::defaults( $preset, 'preview', 'preview-'. $preset['key'] );
				$preview = Composer::preview( [
					'preset' => $preset['key'],
					'pageId' => 'preview',
					'id' => 'preview-'. $preset['key'],
					'elementType' => 'preview-items',
				] );
				if( $preview !== null )
					$preset['preview'] = $preview;
			}
			unset( $preset );
			\Nino\Http::ok( $request, [
				'presets'	=> $presets,
				'modules'	=> array_values( Composer::modules() ),
				'choices'	=> AreaComposer::choices(),
				'previewCss' => self::_previewCss( $appData ),
			] );
		}

		public static function apiCompose( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			try {
				$result = Composer::compose( Admin::postData() );
			} catch( \InvalidArgumentException $exception ) {
				\Nino\Http::fail( $request, 400, $exception->getMessage() );
				return;
			}

			\Nino\Http::ok( $request, $result );
		}

		public static function apiPreview( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$preview = Composer::preview( Admin::postData() );
			if( $preview === null ) {
				\Nino\Http::fail( $request, 400, 'could not render section preview' );
				return;
			}

			\Nino\Http::ok( $request, [ 'html' => $preview ] );
		}

		public static function presets(): array {

			static $cache = null;

			if( $cache !== null )
				return $cache;

			$presets = [];

			foreach( scandir( self::DIRECTORY ) ?: [] as $key ) {

				if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $key ) !== 1 )
					continue;

				$path = self::DIRECTORY. '/'. $key. '/manifest.php';
				if( is_file( $path ) === false )
					continue;

				$manifest = include $path;
				if( is_array( $manifest ) === false )
					continue;
				if( (int) ( $manifest['version'] ?? 0 ) !== 3 )
					continue;

				try {
					$presets[$key] = AreaComposer::normalizePreset( $key, $manifest, self::DIRECTORY. '/'. $key );
				} catch( \Throwable ) {
					continue;
				}
			}

			ksort( $presets );
			$cache = $presets;

			return $cache;
		}

		public static function preset( string $key ): ?array {
			return self::presets()[$key] ?? null;
		}

		public static function template( string $key ): ?string {

			if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $key ) !== 1 )
				return null;

			$path = self::DIRECTORY. '/'. $key. '/section.tpl';
			return is_file( $path ) ? (string) file_get_contents( $path ) : null;
		}

		public static function publicPreset( array $preset ): array {
			unset( $preset['_layouts'] );
			return $preset;
		}

		/**
		 * Return the current project stylesheet for inert srcdoc previews.
		 * Run the regular asset shortcode first so a missing or stale cache is
		 * generated exactly as it is for the frontend. Embedding the result avoids
		 * a browser request to a dot-directory, which some webserver configurations
		 * answer with an HTML error page.
		 */
		private static function _previewCss( array &$appData ): string {

			if( \Nino\Html::getAssets( $appData, '/.cache/style.css' ) !== [] )
				\Nino\Modules\Assets::doShortcode( $appData, [ '/.cache/style.css' ] );

			$path = \Nino\Filesystem::path( $appData, '/.cache/style.css' );

			if( is_file( $path ) === false || is_readable( $path ) === false )
				return '';

			$bytes = filesize( $path );
			if( $bytes === false || $bytes > self::MAX_PREVIEW_CSS_BYTES )
				return '';

			$css = file_get_contents( $path );

			if( $css === false || strlen( $css ) > self::MAX_PREVIEW_CSS_BYTES )
				return '';

			return self::_inlinePreviewFonts( $appData, $css );
		}

		/**
		 * Inline project fonts so sandboxed srcdoc previews do not request them
		 * from their opaque `null` origin. A font rule that cannot be resolved
		 * locally is omitted from the preview instead of producing one CORS error
		 * per iframe; the frontend bundle itself remains untouched.
		 */
		private static function _inlinePreviewFonts( array &$appData, string $css ): string {

			$publicUrl = parse_url( \Nino\Filesystem::getPublicDir( $appData ) );
			$fontRoot = realpath( \Nino\Filesystem::path( $appData, '/fonts' ) );

			if( is_array( $publicUrl ) === false || $fontRoot === false )
				return preg_replace( '~@font-face\s*\{[^{}]*\}~i', '', $css ) ?? $css;

			$embeddedBytes = 0;
			$embeddedFonts = [];
			$result = preg_replace_callback( '~@font-face\s*\{[^{}]*\}~i', function( array $fontRule ) use ( &$appData, $publicUrl, $fontRoot, &$embeddedBytes, &$embeddedFonts ): string {
				$unresolved = false;
				$rule = preg_replace_callback( '~url\(\s*(?:(["\'])(.*?)\1|([^\)"\']+))\s*\)~i', function( array $urlMatch ) use ( &$appData, $publicUrl, $fontRoot, &$embeddedBytes, &$embeddedFonts, &$unresolved ): string {
					$source = trim( ( $urlMatch[2] ?? '' ) !== '' ? $urlMatch[2] : ( $urlMatch[3] ?? '' ) );

					if( str_starts_with( strtolower( $source ), 'data:' ) === true )
						return $urlMatch[0];

					$dataUri = self::_previewFontDataUri( $appData, $source, $publicUrl, $fontRoot, $embeddedBytes, $embeddedFonts );
					if( $dataUri === null ) {
						$unresolved = true;
						return $urlMatch[0];
					}

					return 'url("'. $dataUri. '")';
				}, $fontRule[0] );

				return $unresolved === true || is_string( $rule ) === false ? '' : $rule;
			}, $css );

			return is_string( $result ) ? $result : $css;
		}

		/**
		 * Resolve one same-project public font URL to a bounded data URI.
		 */
		private static function _previewFontDataUri( array &$appData, string $source, array $publicUrl, string $fontRoot, int &$embeddedBytes, array &$embeddedFonts ): ?string {

			$url = parse_url( $source );
			if( is_array( $url ) === false )
				return null;

			foreach( [ 'scheme', 'host', 'port' ] as $originPart )
				if( isset( $url[$originPart] ) === true && ( isset( $publicUrl[$originPart] ) === false || strcasecmp( (string) $url[$originPart], (string) $publicUrl[$originPart] ) !== 0 ) )
					return null;

			$publicPath = rtrim( (string) ( $publicUrl['path'] ?? '' ), '/' );
			$fontPath = rawurldecode( (string) ( $url['path'] ?? '' ) );
			if(
				$publicPath === ''
				|| str_starts_with( $fontPath, $publicPath. '/fonts/' ) === false
				|| str_contains( $fontPath, "\0" ) === true
				|| str_contains( $fontPath, '\\' ) === true
				|| preg_match( '~(?:^|/)\.\.(?:/|$)~', $fontPath ) === 1
			)
				return null;

			$virtualPath = substr( $fontPath, strlen( $publicPath ) );
			$extension = strtolower( pathinfo( $virtualPath, PATHINFO_EXTENSION ) );
			$mimeType = self::PREVIEW_FONT_MIME_TYPES[$extension] ?? null;
			if( $mimeType === null )
				return null;

			$file = realpath( \Nino\Filesystem::path( $appData, $virtualPath ) );
			if( $file === false || str_starts_with( $file, $fontRoot. DIRECTORY_SEPARATOR ) === false || is_file( $file ) === false || is_readable( $file ) === false )
				return null;

			if( isset( $embeddedFonts[$file] ) === true )
				return $embeddedFonts[$file];

			$bytes = filesize( $file );
			if( $bytes === false || $bytes > self::MAX_PREVIEW_FONT_BYTES - $embeddedBytes )
				return null;

			$content = file_get_contents( $file );
			if( $content === false )
				return null;

			$embeddedBytes += strlen( $content );
			$embeddedFonts[$file] = 'data:'. $mimeType. ';base64,'. base64_encode( $content );

			return $embeddedFonts[$file];
		}

		public static function normalizeModel( mixed $model ): array {

			if( is_array( $model ) === false )
				return [];

			$result = [];
			foreach( $model as $field => $definition ) {
				if( preg_match( '/^[a-z][A-Za-z0-9]*$/', (string) $field ) !== 1 || is_array( $definition ) === false )
					throw new \InvalidArgumentException( 'area model has an invalid field name' );
				$type = (string) ( $definition['type'] ?? 'string' );
				if( in_array( $type, [ 'string', 'integer', 'double', 'boolean', 'image', 'element' ], true ) === false )
					throw new \InvalidArgumentException( 'area model field '. $field. ' has an unsupported type' );
				$result[(string) $field] = $definition;
				$result[(string) $field]['type'] = $type;
				$result[(string) $field]['label'] = trim( (string) ( $definition['label'] ?? '' ) )
					?: ucwords( preg_replace( '/(?<!^)[A-Z]/', ' $0', (string) $field ) );
			}

			return $result;
		}
	}

}
