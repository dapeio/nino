<?php
declare(strict_types=1);
/**
 *	Nino					A compact filesystembased php framework
 *	Template Builder	Section-first composition of page-*.tpl files. The
 *							visible unit is a complete <section>, never an arbitrary
 *							DOM node. Unknown page-frame source stays byte-for-byte
 *							locked while sections are inserted, reordered or edited.
 *
 *	@package			Dape/Nino
 *	@author				David Perchermeier <mail@dape.io>
 *	@link				https://github.com/dapeio/nino
 */

namespace Nino\Templates {

	require_once __DIR__. '/Areas.php';

	/**
	 *	Bootstrap and authenticated action dispatcher for /_templates.
	 */
	class Templates {

		private const array MODULES = [
			\Nino\Templates\Documents::class,
			\Nino\Templates\Library::class,
			\Nino\Templates\Content::class,
		];

		public static function init( array &$appData ): void {

			// Tool-owned routes always win over stale persisted collisions.
			$appData['/nino/http/routes']['GET://_templates'] = [
				'uri' 			=> '/_templates',
				'body'			=> '[template /_templates/templates/page-index]',
				'statusCode'	=> 200,
			];
			$appData['/nino/http/routes']['POST://_templates'] = [ 'uri' => '/_templates' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_templates', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_templates', [ self::class, 'handlePost' ] );
		}

		public static function handleGet( array &$appData, array &$request ): void {
			$policy = (string) ( $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '' );
			$request['/nino/http/response']['header']['Content-Security-Policy'] = self::_allowPreviewDataFonts( $policy );

			if( \Nino\Admin\Admin::isAuthed( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_templates/templates/page-login]';
		}

		/**
		 * The outer /_templates policy is inherited by sandboxed srcdoc frames.
		 * Allow their inlined preview fonts without widening Nino's global CSP.
		 */
		private static function _allowPreviewDataFonts( string $policy ): string {

			$directives = preg_split( '~\s*;\s*~', trim( $policy, '; ' ), -1, PREG_SPLIT_NO_EMPTY );
			if( is_array( $directives ) === false )
				$directives = [];

			$fontDirective = false;
			foreach( $directives as &$directive ) {
				if( preg_match( '~^font-src(?:\s|$)~i', $directive ) !== 1 )
					continue;

				$fontDirective = true;
				if( preg_match( '~(?:^|\s)data:(?:\s|$)~i', $directive ) !== 1 )
					$directive .= ' data:';
			}
			unset( $directive );

			if( $fontDirective === false )
				$directives[] = "font-src 'self' data:";

			return implode( '; ', $directives );
		}

		public static function handlePost( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$action = (string) ( $_POST['action'] ?? '' );
			$actions = [];

			foreach( self::MODULES as $module )
				$actions += $module::actions();

			if( isset( $actions[$action] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown action' );
				return;
			}

			[ $class, $method ] = $actions[$action];
			$class::{$method}( $appData, $request );
		}

		public static function guard( array &$appData, array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			if( \Nino\Admin\Admin::isAuthed( $appData ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return false;
			}

			return true;
		}

		public static function postData(): array {
			$data = json_decode( $_POST['data'] ?? '', true );
			return is_array( $data ) ? $data : [];
		}
	}

	/**
	 *	Manifest-backed entry points for the named-area Section Composer.
	 *	Only version-3 manifests are exposed; their section templates remain
	 *	code-authored while the UI edits normalized areas and bindings.
	 */
	class Library {

		private const string DIRECTORY = __DIR__. '/library';
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

			try {
				$result = Composer::compose( Templates::postData() );
			} catch( \InvalidArgumentException $exception ) {
				\Nino\Http::fail( $request, 400, $exception->getMessage() );
				return;
			}

			\Nino\Http::ok( $request, $result );
		}

		public static function apiPreview( array &$appData, array &$request ): void {

			$preview = Composer::preview( Templates::postData() );
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

	/**
	 *	Validated SectionSpec -> ordinary HTML+ source. The output is copied
	 *	into the page and remains independent of the library at runtime.
	 */
	class Composer {

		/**
		 * One shortcode's argument list, for the inert preview's own matching.
		 * Quoted values are consumed whole because a bound alt text compiles to
		 * alt="[[/page-…/…-alt]]" - reading up to the first "]" ended the match
		 * inside that fill and left its tail (]"]) standing in the preview.
		 */
		private const string SHORTCODE_ARGUMENTS = '(?:"[^"]*"|\'[^\']*\'|[^\]"\'])*';

		public static function modules(): array {

			return [
				'none' => [
					'key' => 'none', 'name' => 'No content', 'source' => 'none', 'layouts' => [ 'auto' ], 'fields' => [], 'images' => [], 'model' => [],
				],
				'text' => [
					'key' => 'text', 'name' => 'Text', 'source' => 'text', 'layouts' => [ 'wide', 'narrow' ], 'fields' => [ 'content' ], 'images' => [], 'model' => [],
				],
				'media-split' => [
					'key' => 'media-split', 'name' => 'Media / Text 50–50', 'source' => 'text',
					'layouts' => [ 'media-left', 'media-right', 'media-left-full', 'media-right-full' ],
					'fields' => [ 'content' ], 'images' => [ 'image' ], 'model' => [],
				],
				'lists' => [
					'key' => 'lists', 'name' => 'Check / numbered list', 'source' => 'elements',
					'layouts' => [ 'check', 'check-2', 'numbered', 'numbered-2' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 300 ],
					],
				],
				'articles' => [
					'key' => 'articles', 'name' => 'Articles', 'source' => 'elements', 'layouts' => [ '2', '3', '4' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2000 ],
						'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
						'link' => [ 'type' => 'string', 'maxlength' => 500 ],
					],
				],
				'articles-image' => [
					'key' => 'articles-image', 'name' => 'Articles with image', 'source' => 'elements', 'layouts' => [ '2', '3', '4' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2000 ],
						'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
						'link' => [ 'type' => 'string', 'maxlength' => 500 ],
						'image' => [ 'type' => 'image', 'width' => 1200, 'height' => 800 ],
					],
				],
				'cards' => [
					'key' => 'cards', 'name' => 'Visual cards / offers', 'source' => 'elements', 'layouts' => [ 'spotlight', '2', '3', '4' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2000 ],
						'badge' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 80 ],
						'price' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 80 ],
						'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
						'link' => [ 'type' => 'string', 'maxlength' => 500 ],
						'image' => [ 'type' => 'image', 'width' => 1200, 'height' => 800 ],
					],
				],
				'slider' => [
					'key' => 'slider', 'name' => 'Slider', 'source' => 'elements', 'layouts' => [ 'narrow', 'wide' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 300 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 500 ],
					],
				],
				'media-slider' => [
					'key' => 'media-slider', 'name' => 'Media slider', 'source' => 'elements', 'layouts' => [ 'narrow', 'wide' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 300 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 1600 ],
						'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
						'link' => [ 'type' => 'string', 'maxlength' => 500 ],
						'image' => [ 'type' => 'image', 'width' => 1600, 'height' => 1000 ],
					],
				],
				'testimonials' => [
					'key' => 'testimonials', 'name' => 'Testimonials', 'source' => 'elements', 'layouts' => [ 'spotlight', 'slider', '2', '3' ], 'fields' => [], 'images' => [],
					'model' => [
						'quote' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 1500 ],
						'author' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'role' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 220 ],
						'image' => [ 'type' => 'image', 'width' => 600, 'height' => 600 ],
					],
				],
				'profiles' => [
					'key' => 'profiles', 'name' => 'People / team', 'source' => 'elements', 'layouts' => [ 'spotlight', '3', '4' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'role' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 220 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2000 ],
						'email' => [ 'type' => 'string', 'maxlength' => 320 ],
						'phone' => [ 'type' => 'string', 'maxlength' => 80 ],
						'image' => [ 'type' => 'image', 'width' => 900, 'height' => 1100 ],
					],
				],
				'stats' => [
					'key' => 'stats', 'name' => 'Statistics / metrics', 'source' => 'elements', 'layouts' => [ '2', '3', '4' ], 'fields' => [], 'images' => [],
					'model' => [
						'number' => [ 'type' => 'integer', 'required' => true ],
						'suffix' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 40 ],
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
					],
				],
				'features' => [
					'key' => 'features', 'name' => 'Features', 'source' => 'elements', 'layouts' => [ '2', '3', '4', 'bento' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 1600 ],
					],
				],
				'feature-list' => [
					'key' => 'feature-list', 'name' => 'Feature list with media', 'source' => 'elements', 'layouts' => [ 'media-left', 'media-right' ], 'fields' => [], 'images' => [ 'image' ],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 1200 ],
					],
				],
				'accordion' => [
					'key' => 'accordion', 'name' => 'Accordion / FAQ', 'source' => 'elements', 'layouts' => [ 'narrow', 'wide' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 300 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2000 ],
					],
				],
				'tabs' => [
					'key' => 'tabs', 'name' => 'Tabs', 'source' => 'elements', 'layouts' => [ 'narrow', 'wide' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 4000 ],
					],
				],
				'pricing' => [
					'key' => 'pricing', 'name' => 'Pricing', 'source' => 'elements', 'layouts' => [ '2', '3', '4', 'featured' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 1000 ],
						'price' => [ 'type' => 'double', 'suffix' => '€' ],
						'badge' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 80 ],
						'features' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2400 ],
						'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
						'link' => [ 'type' => 'string', 'maxlength' => 500 ],
					],
				],
				'comparison' => [
					'key' => 'comparison', 'name' => 'Comparison table', 'source' => 'elements', 'layouts' => [ 'wide' ],
					'fields' => [ 'column-a', 'column-b' ], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 220 ],
						'optionA' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
						'optionB' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
					],
				],
				'data-table' => [
					'key' => 'data-table', 'name' => 'Data table', 'source' => 'elements',
					'layouts' => [ 'plain', 'striped', 'bordered', 'striped-bordered' ],
					'fields' => [ 'column-a', 'column-b', 'column-c' ], 'images' => [],
					'model' => [
						'columnA' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 500 ],
						'columnB' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 500 ],
						'columnC' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 500 ],
					],
				],
				'logos' => [
					'key' => 'logos', 'name' => 'Logo cloud', 'source' => 'elements', 'layouts' => [ 'wide' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'image' => [ 'type' => 'image', 'width' => 600, 'height' => 300 ],
					],
				],
				'badges' => [
					'key' => 'badges', 'name' => 'Badges / pills', 'source' => 'elements', 'layouts' => [ 'plain', 'pill' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 120 ],
					],
				],
				'gallery' => [
					'key' => 'gallery', 'name' => 'Image gallery', 'source' => 'elements', 'layouts' => [ 'grid', 'mosaic' ], 'fields' => [], 'images' => [],
					'model' => [
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 220 ],
						'image' => [ 'type' => 'image', 'width' => 1600, 'height' => 1200 ],
					],
				],
				'timeline' => [
					'key' => 'timeline', 'name' => 'Timeline', 'source' => 'elements', 'layouts' => [ '3', '4' ], 'fields' => [], 'images' => [],
					'model' => [
						'number' => [ 'type' => 'integer', 'required' => true ],
						'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
						'description' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 1000 ],
					],
				],
				'video' => [
					'key' => 'video', 'name' => 'Video lightbox', 'source' => 'text', 'layouts' => [ 'narrow', 'wide' ],
					'fields' => [ 'video-uri' ], 'images' => [ 'image' ], 'model' => [],
				],
				'video-embed' => [
					'key' => 'video-embed', 'name' => 'Video embed', 'source' => 'text', 'layouts' => [ 'narrow', 'wide', '4-3' ],
					'fields' => [ 'video-uri' ], 'images' => [], 'model' => [],
				],
				'notice' => [
					'key' => 'notice', 'name' => 'Notice / alert', 'source' => 'text', 'layouts' => [ 'info', 'success', 'error' ],
					'fields' => [ 'content' ], 'images' => [], 'model' => [],
				],
				'contact' => [
					'key' => 'contact', 'name' => 'Contact form', 'source' => 'text', 'layouts' => [ 'split' ],
					'fields' => [ 'content', 'address', 'phone', 'email' ], 'images' => [], 'model' => [],
				],
				'newsletter' => [
					'key' => 'newsletter', 'name' => 'Newsletter signup', 'source' => 'none', 'layouts' => [ 'narrow', 'wide' ],
					'fields' => [], 'images' => [], 'model' => [],
				],
			];
		}

		/**
		 *	Compose one section from a preset.
		 *
		 *	Every preset in the library is an Area preset - Library::presets()
		 *	skips a manifest that does not declare version 3 - so this resolves
		 *	the key and hands straight over. It stays a method of its own
		 *	because the key lookup and the "unknown preset" answer belong to the
		 *	caller's vocabulary, not to the Area composer's.
		 *
		 *	@param		array			$input				Preset key plus the browser's own values
		 *
		 *	@return 	array								{ source, spec, fields, imageSlots, elementSchema, segment }
		 */
		public static function compose( array $input ): array {

			$preset = Library::preset( (string) ( $input['preset'] ?? 'blank' ) );

			if( $preset === null )
				throw new \InvalidArgumentException( 'unknown section preset' );

			return AreaComposer::compose( $input, $preset );
		}

		/**
		 * Render the real generated section with deterministic fixture content.
		 * The preview is isolated in a sandboxed iframe by the client; no project
		 * content is read and no Elements collection has to exist yet.
		 */
		public static function preview( array $input ): ?string {

			$input['pageId'] = (string) ( $input['pageId'] ?? 'preview' );
			$input['id'] = (string) ( $input['id'] ?? 'preview-section' );
			$input['elementType'] = (string) ( $input['elementType'] ?? 'preview-items' );

			try {
				$result = self::compose( $input );
			} catch( \Throwable ) {
				return null;
			}

			return self::_previewHtml( $result['source'] );
		}

		private static function _previewHtml( string $source ): string {

			$source = preg_replace( '/[\t ]*<!--\s*nino:section\s+\{[^\r\n]*\}\s*-->[\t ]*(?:\r?\n)?/', '', $source ) ?? $source;
			$source = preg_replace( '#<script\b[^>]*>.*?</script\s*>#is', '', $source ) ?? $source;
			$source = preg_replace( '#<script\b[^>]*>.*$#is', '', $source ) ?? $source;
			$source = preg_replace( '#</?script\b[^>]*>#is', '', $source ) ?? $source;
			$source = preg_replace( '#\s+on[a-z0-9:_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $source ) ?? $source;
			$source = preg_replace_callback(
				'#\s+(href|src|action|formaction|xlink:href)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i',
				fn( array $match ): string => ' '. $match[1]. '="#"',
				$source
			) ?? $source;
			$source = preg_replace( '#\s+(href|src|action|formaction|xlink:href)\s*=\s*javascript:[^\s>]*#i', ' $1="#"', $source ) ?? $source;
			$source = preg_replace( '#<\?.*?\?>#s', '', $source ) ?? $source;
			// Preview frames deliberately run without Nino.ui.js. Remove VPA's
			// hidden initial state (and its variants) instead of leaving motion-
			// enabled sections permanently transparent inside the iframe.
			$source = preg_replace_callback( '#(\bclass\s*=\s*)(["\'])(.*?)\2#is', function( array $match ): string {
				$classes = preg_split( '/\s+/', trim( $match[3] ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
				$previewClasses = array_values( array_filter(
					$classes,
					fn( string $class ): bool => $class !== 'nino-vpa' && str_starts_with( $class, 'nino-vpa--' ) === false
				) );
				if( $previewClasses === $classes )
					return $match[0];
				return $match[1]. $match[2]. implode( ' ', $previewClasses ). $match[2];
			}, $source ) ?? $source;
			$source = preg_replace_callback( '#\[image\s+'. self::SHORTCODE_ARGUMENTS. '\]#i', fn(): string => '<img src="'. self::_previewImage( 'Section image' ). '" alt="">', $source ) ?? $source;

			$source = preg_replace_callback( '#\[elements\s+('. self::SHORTCODE_ARGUMENTS. ')\](.*?)\[/elements\]#is', function( array $match ): string {
				$limit = [];
				preg_match( '/\blimit="(\d+)"/i', $match[1], $limit );
				$columns = match( true ) {
					preg_match( '/(?:^|\s)nino-grid-[a-z]+-25(?:\s|["\'])/i', $match[2] ) === 1 => 4,
					preg_match( '/(?:^|\s)nino-grid-[a-z]+-33(?:\s|["\'])/i', $match[2] ) === 1 => 3,
					preg_match( '/(?:^|\s)nino-grid-[a-z]+-50(?:\s|["\'])/i', $match[2] ) === 1 => 2,
					preg_match( '/(?:^|\s)nino-grid-[a-z]+-100(?:\s|["\'])/i', $match[2] ) === 1 => 1,
					default => 3,
				};
				$count = min( $columns, max( 1, (int) ( $limit[1] ?? $columns ) ) );
				$out = '';
				for( $index = 0; $index < $count; $index++ ) {
					$item = $match[2];
					$item = str_replace( '[[.id]]', (string) $index, $item );
					$item = preg_replace_callback(
						'#src=(["\'])[^"\']*/images/\[\[image\]\]\1#i',
						fn( array $imageMatch ): string => 'src='. $imageMatch[1]. self::_previewImage( 'Preview item '. ( $index + 1 ) ). $imageMatch[1],
						$item
					) ?? $item;
					$item = preg_replace_callback(
						'#\[\[([^\]]+)\]\]#',
						fn( array $fill ): string => self::_previewFill( $fill[1], $index ),
						$item
					) ?? $item;
					$out .= $item;
				}
				return $out;
			}, $source ) ?? $source;

			// [elementvalues] loops one field's distinct values, not records - a
			// fixed, realistic set of sample category buttons stands in, the same
			// way [elements] above stands in with sample cards.
			$source = preg_replace_callback( '#\[elementvalues\s+('. self::SHORTCODE_ARGUMENTS. ')\](.*?)\[/elementvalues\]#is', function( array $match ): string {
				$sampleValues = [ 'Consulting' => 2, 'Design' => 3, 'Development' => 1 ];
				// Honour limit the same way the [elements] fixture above does, so
				// a preset that bounds its button row previews what it ships
				$limit = [];
				preg_match( '/\blimit="(\d+)"/i', $match[1], $limit );
				$count = min( count( $sampleValues ), max( 1, (int) ( $limit[1] ?? count( $sampleValues ) ) ) );
				$out = '';
				$index = 0;
				foreach( array_slice( $sampleValues, 0, $count, true ) as $value => $usage ) {
					$out .= str_replace( [ '[[.id]]', '[[.value]]', '[[.count]]' ], [ (string) $index, $value, (string) $usage ], $match[2] );
					$index++;
				}
				return $out;
			}, $source ) ?? $source;

			$source = preg_replace_callback(
				'#\[\[([^\]]+)\]\]#',
				fn( array $fill ): string => self::_previewFill( $fill[1], null ),
				$source
			) ?? $source;
			$source = str_replace( '[csrf]', '', $source );
			// Each looping shortcode is paired with its own closer via a
			// backreference: a shared (?:elements|elementvalues) alternation let a
			// leftover [elements] swallow everything up to a stray
			// [/elementvalues] and vice versa.
			$source = preg_replace( '#\[(elements|elementvalues)\b\s*'. self::SHORTCODE_ARGUMENTS. '\](?:.*?\[/\1\])?#is', '', $source ) ?? $source;
			$source = preg_replace( '#\[(?:template|image)\b\s*'. self::SHORTCODE_ARGUMENTS. '\]#is', '', $source ) ?? $source;
			$source = preg_replace( '#(<iframe\b[^>]*\bsrc=)(["\'])[^"\']*\2#i', '$1$2about:blank$2', $source ) ?? $source;
			$source = preg_replace( '#(<form\b[^>]*\baction=)(["\'])[^"\']*\2#i', '$1$2#$2', $source ) ?? $source;

			return $source;
		}

		private static function _previewFill( string $token, ?int $index ): string {

			if( $token === '/nino/dir' )
				return '';

			$key = strtolower( basename( str_replace( '\\', '/', $token ) ) );
			$number = ( $index ?? 0 ) + 1;
			$absolute = str_starts_with( $token, '/' );
			$values = [
				'title' => $absolute ? 'A clear headline for this section' : 'Thoughtful item '. $number,
				'subtitle' => 'A concise supporting line makes the purpose immediately clear.',
				'description' => $absolute ? 'Use this space to explain the most important idea in a calm, readable way.' : 'Useful supporting copy that gives this item enough context.',
				'content' => 'Realistic sample content shows spacing, rhythm and hierarchy before anything is inserted.',
				'quote' => '“The result feels focused, considered and remarkably easy to use.”',
				'author' => 'Alex Morgan',
				'role' => 'Product lead',
				'number' => (string) ( 24 + ( $number * 17 ) ),
				'step' => (string) $number,
				'suffix' => $number % 2 === 0 ? '%' : '+',
				'price' => (string) ( 49 + ( $number * 50 ) ),
				'badge' => $number === 2 ? 'Recommended' : 'Popular',
				'features' => 'Strategy · Design · Delivery · Support',
				'linklabel' => 'Learn more',
				'link' => '#',
				'cta-label' => 'Get started',
				'cta-uri' => '#',
				'secondary-cta-label' => 'See details',
				'secondary-cta-uri' => '#',
				'column-a' => 'Service',
				'column-b' => 'Duration',
				'column-c' => 'Investment',
				'columna' => 'Service '. $number,
				'columnb' => ( 30 + $number * 15 ). ' min',
				'columnc' => ( 90 + $number * 40 ). ' €',
				'optiona' => $number % 2 === 0 ? 'Included' : 'Optional',
				'optionb' => $number % 2 === 0 ? 'Advanced' : 'Standard',
				'video-uri' => 'about:blank',
				'email' => 'hello@example.com',
				'phone' => '+49 123 456789',
				'address' => 'Example Street 12<br>12345 Example City',
				'name' => 'Your name',
				'message' => 'Your message',
				'submit' => 'Send message',
				'required' => 'Required fields',
				'image' => 'preview-image.jpg',
			];

			return $values[$key] ?? ucwords( str_replace( [ '-', '_' ], ' ', $key ) );
		}

		private static function _previewImage( string $label ): string {

			$hue = abs( crc32( $label ) ) % 360;
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="hsl('. $hue.',55%,72%)"/><stop offset="1" stop-color="hsl('. ( ( $hue + 65 ) % 360 ). ',48%,38%)"/></linearGradient></defs><rect width="1200" height="800" fill="url(#g)"/><circle cx="900" cy="190" r="220" fill="rgba(255,255,255,.16)"/><path d="M0 650L340 390l190 150 190-190 480 360v90H0z" fill="rgba(255,255,255,.2)"/></svg>';
			return 'data:image/svg+xml,'. rawurlencode( $svg );
		}

	}

	/**
	 *	Lossless top-level section splitter. It scans tags with quote-aware
	 *	boundaries, skips comments and raw script/style bodies, and never
	 *	serializes source. Rejoining unchanged segments reproduces the file.
	 */
	class SectionDocument {

		private const array RAW_TAGS = [ 'script', 'style', 'textarea' ];
		private const array VOID_TAGS = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];

		public static function split( string $source ): array {

			$segments = [];
			$cursor = 0;
			$offset = 0;
			$depth = 0;
			$sectionStart = null;
			$error = null;
			$sectionCount = 0;

			while( ( $tag = self::_nextTag( $source, $offset ) ) !== null ) {

				$offset = $tag['end'];

				if( $tag['comment'] === true )
					continue;

				if( in_array( $tag['name'], self::RAW_TAGS, true ) && $tag['closing'] === false ) {
					$pattern = '#</\s*'. preg_quote( $tag['name'], '#' ). '\s*>#i';
					if( preg_match( $pattern, $source, $close, PREG_OFFSET_CAPTURE, $offset ) === 1 )
						$offset = $close[0][1] + strlen( $close[0][0] );
					else
						$offset = strlen( $source );
					continue;
				}

				if( $tag['name'] !== 'section' )
					continue;
				if( $tag['closing'] === false && $tag['selfClosing'] === true ) {
					$error = 'self-closing <section> is not valid HTML';
					break;
				}

				if( $tag['closing'] === false ) {
					if( $depth === 0 )
						$sectionStart = $tag['start'];
					$depth++;

				} else {
					$depth--;
					if( $depth < 0 ) {
						$error = 'closing </section> without an opening tag';
						break;
					}
				}

				if( $depth !== 0 || $sectionStart === null )
					continue;

				$end = $tag['end'];
				while( $end < strlen( $source ) && str_contains( " \t\r\n", $source[$end] ) )
					$end++;

				if( $sectionStart > $cursor )
					$segments[] = [ 'type' => 'raw', 'source' => substr( $source, $cursor, $sectionStart - $cursor ) ];

				$sectionSource = substr( $source, $sectionStart, $end - $sectionStart );
				$sectionCount++;
				$segments[] = self::_segment( $sectionSource, 'section-'. $sectionCount );

				$cursor = $end;
				$offset = $end;
				$sectionStart = null;
			}

			if( $error === null && $depth !== 0 )
				$error = 'unclosed <section> element';

			if( $cursor < strlen( $source ) )
				$segments[] = [ 'type' => 'raw', 'source' => substr( $source, $cursor ) ];
			else if( $segments === [] )
				$segments[] = [ 'type' => 'raw', 'source' => '' ];

			if( $error === null )
				$segments = self::_splitTemplateIncludes( $segments );

			$componentCount = count( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template' ], true )
			) );

			return [
				'segments' => $segments,
				'error' => $error,
				'sectionCount' => $sectionCount,
				'componentCount' => $componentCount,
			];
		}

		public static function inspectSection( string $source ): array {

			$parsed = self::split( $source );
			$sections = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
			$templates = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'template' ) );
			$slots = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
			$raw = implode( '', array_map( fn( array $segment ): string => $segment['type'] === 'raw' ? $segment['source'] : '', $parsed['segments'] ) );
			$valid = $parsed['error'] === null && count( $sections ) === 1 && $templates === [] && $slots === [] && trim( $raw ) === '';

			return [
				'valid' => $valid,
				'error' => $valid ? null : ( $parsed['error'] ?? 'source must contain exactly one section' ),
				'segment' => $valid ? $sections[0] : null,
			];
		}

		public static function inspectTemplate( string $source ): array {

			$parsed = self::split( $source );
			$templates = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'template' ) );
			$sections = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
			$slots = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
			$raw = implode( '', array_map( fn( array $segment ): string => $segment['type'] === 'raw' ? $segment['source'] : '', $parsed['segments'] ) );
			$valid = $parsed['error'] === null && count( $templates ) === 1 && $sections === [] && $slots === [] && trim( $raw ) === '';

			return [
				'valid' => $valid,
				'error' => $valid ? null : ( $parsed['error'] ?? 'source must contain exactly one template shortcode' ),
				'segment' => $valid ? $templates[0] : null,
			];
		}

		public static function inspectSlot( string $source, string $slot ): array {

			$parsed = self::split( $source );
			$slots = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
			$components = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => in_array( $segment['type'], [ 'section', 'template' ], true ) ) );
			$raw = implode( '', array_map( fn( array $segment ): string => $segment['type'] === 'raw' ? $segment['source'] : '', $parsed['segments'] ) );
			$valid = in_array( $slot, [ 'header', 'footer' ], true )
				&& $parsed['error'] === null
				&& count( $slots ) === 1
				&& ( $slots[0]['slot'] ?? '' ) === $slot
				&& $components === []
				&& trim( $raw ) === '';

			return [
				'valid' => $valid,
				'error' => $valid ? null : ( $parsed['error'] ?? 'source must contain exactly one page template slot' ),
				'segment' => $valid ? $slots[0] : null,
			];
		}

		public static function slotSource( string $slot, string $path = '' ): string {

			if( in_array( $slot, [ 'header', 'footer' ], true ) === false )
				throw new \InvalidArgumentException( 'invalid page template slot' );
			if( $path !== '' && preg_match( '#^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$#', $path ) !== 1 )
				throw new \InvalidArgumentException( 'invalid page template include' );

			return '<!-- nino:template-slot '. $slot. " -->\n"
				. ( $path === '' ? '' : '[template '. $path. "]\n" );
		}

		public static function rawSources( array $segments ): array {
			return array_values( array_map(
				fn( array $segment ): string => (string) ( $segment['source'] ?? '' ),
				array_filter( $segments, fn( array $segment ): bool => ( $segment['type'] ?? '' ) === 'raw' )
			) );
		}

		private static function _segment( string $source, string $id ): array {

			$openingEnd = self::_tagEnd( $source, 0 ) ?? 0;
			$opening = substr( $source, 0, $openingEnd );
			$htmlId = '';

			if( preg_match( '/\bid\s*=\s*(["\'])(.*?)\1/is', $opening, $match ) === 1 )
				$htmlId = html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			$spec = null;
			if( preg_match( '/<!--\s*nino:section\s+(\{.*?\})\s*-->/s', $source, $match ) === 1 ) {
				$decoded = json_decode( $match[1], true );
				if( is_array( $decoded ) )
					$spec = $decoded;
			}

			$fills = [];
			if( preg_match_all( '#\[\[(\/page-[a-z0-9_-]+\/[a-z0-9-]+\/[a-z0-9-]+)\]\]#i', $source, $matches ) > 0 )
				$fills = array_values( array_unique( $matches[1] ) );

			$elementTypes = [];
			if( preg_match_all( '#\[elements\s+/([a-z][a-z0-9_-]*)#i', $source, $matches ) > 0 )
				$elementTypes = array_values( array_unique( $matches[1] ) );

			$imageSlots = [];
			if( preg_match_all( '#\[image\s+(/[a-z0-9_/-]+)#i', $source, $matches ) > 0 )
				$imageSlots = array_values( array_unique( $matches[1] ) );

			return [
				'type'			=> 'section',
				'id'			=> $id,
				'htmlId'		=> $htmlId,
				'source'		=> $source,
				'spec'			=> $spec,
				'fills'			=> $fills,
				'elementTypes'	=> $elementTypes,
				'imageSlots'	=> $imageSlots,
			];
		}

		private static function _splitTemplateIncludes( array $segments ): array {

			$result = [];
			$templateCount = 0;
			$openTags = [];

			foreach( $segments as $segment ) {
				if( ( $segment['type'] ?? '' ) !== 'raw' ) {
					$result[] = $segment;
					continue;
				}

				$raw = (string) ( $segment['source'] ?? '' );
				$searchable = self::_templateSearchSource( $raw, $openTags );
				$cursor = 0;
				$pattern = '~^[\t ]*(?:<!--\s*nino:template-slot\s+(header|footer)\s*-->[\t ]*(?:\r?\n)?(?:[\t ]*\[template[\t ]+(\/templates\/[A-Za-z0-9][A-Za-z0-9._-]*)\][\t ]*(?:\r?\n|$))?|\[template[\t ]+(\/templates\/[A-Za-z0-9][A-Za-z0-9._-]*)\][\t ]*(?:\r?\n|$))~m';
				if( preg_match_all( $pattern, $searchable, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL ) === false ) {
					$result[] = $segment;
					continue;
				}

				foreach( $matches as $match ) {
					$start = $match[0][1];
					if( $start > $cursor )
						$result[] = [ 'type' => 'raw', 'source' => substr( $raw, $cursor, $start - $cursor ) ];

					$slot = (string) ( $match[1][0] ?? '' );
					$path = (string) ( $slot === '' ? ( $match[3][0] ?? '' ) : ( $match[2][0] ?? '' ) );
					$templateCount++;
					$result[] = $slot === ''
						? self::_templateSegment( $match[0][0], $path, 'template-'. $templateCount )
						: self::_slotSegment( $match[0][0], $slot, $path, 'slot-'. $slot );
					$cursor = $start + strlen( $match[0][0] );
				}

				if( $cursor < strlen( $raw ) )
					$result[] = [ 'type' => 'raw', 'source' => substr( $raw, $cursor ) ];
				else if( $raw === '' )
					$result[] = $segment;
			}

			return $result === [] ? [ [ 'type' => 'raw', 'source' => '' ] ] : $result;
		}

		private static function _templateSearchSource( string $source, array &$openTags ): string {

			$searchable = $source;
			$offset = 0;
			$protectedStart = $openTags === [] ? null : 0;

			while( ( $tag = self::_nextTag( $source, $offset ) ) !== null ) {
				$offset = $tag['end'];

				if( $tag['comment'] === true ) {
					$comment = substr( $source, $tag['start'], $tag['end'] - $tag['start'] );
					if( preg_match( '/^<!--\s*nino:template-slot\s+(?:header|footer)\s*-->$/i', trim( $comment ) ) === 1 )
						continue;
					$searchable = self::_maskRange( $searchable, $tag['start'], $tag['end'] );
					continue;
				}

				if( in_array( $tag['name'], self::RAW_TAGS, true ) && $tag['closing'] === false ) {
					$pattern = '#</\s*'. preg_quote( $tag['name'], '#' ). '\s*>#i';
					$end = strlen( $source );
					if( preg_match( $pattern, $source, $close, PREG_OFFSET_CAPTURE, $offset ) === 1 )
						$end = $close[0][1] + strlen( $close[0][0] );
					$searchable = self::_maskRange( $searchable, $tag['start'], $end );
					$offset = $end;
					continue;
				}

				if( $tag['closing'] === false ) {
					if( $tag['selfClosing'] === true || in_array( $tag['name'], self::VOID_TAGS, true ) )
						continue;
					if( $openTags === [] && $protectedStart === null )
						$protectedStart = $tag['start'];
					$openTags[] = $tag['name'];
					continue;
				}

				for( $index = count( $openTags ) - 1; $index >= 0; $index-- )
					if( $openTags[$index] === $tag['name'] ) {
						array_splice( $openTags, $index );
						break;
					}

				if( $openTags === [] && $protectedStart !== null ) {
					$searchable = self::_maskRange( $searchable, $protectedStart, $tag['end'] );
					$protectedStart = null;
				}
			}

			if( $protectedStart !== null )
				$searchable = self::_maskRange( $searchable, $protectedStart, strlen( $source ) );

			return $searchable;
		}

		private static function _maskRange( string $source, int $start, int $end ): string {
			$length = max( 0, $end - $start );
			$masked = preg_replace( '/[^\r\n]/', ' ', substr( $source, $start, $length ) );
			return substr_replace( $source, $masked ?? '', $start, $length );
		}

		private static function _templateSegment( string $source, string $path, string $id ): array {
			return [
				'type' => 'template',
				'id' => $id,
				'template' => basename( $path ),
				'path' => $path,
				'htmlId' => '',
				'source' => $source,
				'spec' => null,
				'fills' => [],
				'elementTypes' => [],
				'imageSlots' => [],
			];
		}

		private static function _slotSegment( string $source, string $slot, string $path, string $id ): array {
			return [
				'type' => 'slot',
				'id' => $id,
				'slot' => $slot,
				'template' => $path === '' ? '' : basename( $path ),
				'path' => $path,
				'htmlId' => '',
				'source' => $source,
				'spec' => null,
				'fills' => [],
				'elementTypes' => [],
				'imageSlots' => [],
			];
		}

		private static function _nextTag( string $source, int $offset ): ?array {

			$length = strlen( $source );

			while( $offset < $length ) {
				$start = strpos( $source, '<', $offset );
				if( $start === false )
					return null;

				if( substr( $source, $start, 4 ) === '<!--' ) {
					$close = strpos( $source, '-->', $start + 4 );
					$end = $close === false ? $length : $close + 3;
					return [ 'start' => $start, 'end' => $end, 'name' => '', 'closing' => false, 'selfClosing' => false, 'comment' => true ];
				}

				// PHP is not part of HTML+'s normal authoring path, but custom
				// templates can still contain it. A string literal such as
				// "<section>" inside that block must never become a visual card.
				if( substr( $source, $start, 2 ) === '<?' ) {
					$close = strpos( $source, '?>', $start + 2 );
					$end = $close === false ? $length : $close + 2;
					return [ 'start' => $start, 'end' => $end, 'name' => '', 'closing' => false, 'selfClosing' => false, 'comment' => true ];
				}

				$end = self::_tagEnd( $source, $start );
				if( $end === null )
					return null;

				$raw = substr( $source, $start, $end - $start );
				if( preg_match( '/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9:-]*)\b/', $raw, $match ) !== 1 ) {
					$offset = $end;
					continue;
				}

				return [
					'start'		=> $start,
					'end'		=> $end,
					'name'		=> strtolower( $match[2] ),
					'closing'	=> $match[1] === '/',
					'selfClosing' => preg_match( '#/\s*>$#', $raw ) === 1,
					'comment'	=> false,
				];
			}

			return null;
		}

		private static function _tagEnd( string $source, int $start ): ?int {

			$quote = '';
			for( $i = $start + 1, $length = strlen( $source ); $i < $length; $i++ ) {
				$char = $source[$i];
				if( $quote !== '' ) {
					if( $char === $quote )
						$quote = '';
					continue;
				}
				if( $char === '"' || $char === "'" ) {
					$quote = $char;
					continue;
				}
				if( $char === '>' )
					return $i + 1;
			}

			return null;
		}
	}

	/**
	 *	Only page-*.tpl documents, with optimistic concurrency and locked raw
	 *	segments. The browser may rearrange section segments but cannot rewrite
	 *	the page frame surrounding them.
	 */
	class Documents {

		public static function actions(): array {
			return [
				'documents/list'	=> [ self::class, 'apiList' ],
				'documents/create'	=> [ self::class, 'apiCreate' ],
				'documents/delete'	=> [ self::class, 'apiDelete' ],
				'documents/includes'	=> [ self::class, 'apiIncludes' ],
				'documents/load'	=> [ self::class, 'apiLoad' ],
				'documents/save'	=> [ self::class, 'apiSave' ],
				'documents/inspect'	=> [ self::class, 'apiInspect' ],
			];
		}

		public static function apiList( array &$appData, array &$request ): void {

			$documents = [];
			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/page-*.tpl' ) ?: [] as $file ) {
				$name = basename( $file, '.tpl' );
				$source = (string) file_get_contents( $file );
				$parsed = self::_pageSegments( $source, $name );
				$documents[] = [
					'name' => $name,
					'filename' => $name. '.tpl',
					'displayName' => $parsed['displayName'],
					'pageId' => self::_pageId( $name ),
					'pageMotion' => $parsed['pageMotion'],
					'sections' => $parsed['sectionCount'],
					'components' => $parsed['componentCount'],
					'editable' => $parsed['error'] === null,
				];
			}

			usort( $documents, fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );
			\Nino\Http::ok( $request, [ 'documents' => $documents ] );
		}

		public static function apiCreate( array &$appData, array &$request ): void {

			$data = Templates::postData();
			$filename = trim( (string) ( $data['filename'] ?? '' ) );
			if( preg_match( '/^page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl$/', $filename ) !== 1 || str_contains( $filename, '..' ) ) {
				\Nino\Http::fail( $request, 400, 'filename must look like page-services.tpl and may contain only letters, numbers, dots, underscores and hyphens' );
				return;
			}

			$name = substr( $filename, 0, -4 );
			$path = self::_path( $appData, $name );
			if( $path === null ) {
				\Nino\Http::fail( $request, 400, 'invalid page template name' );
				return;
			}
			if( is_file( $path ) ) {
				\Nino\Http::fail( $request, 409, 'a page template with this filename already exists' );
				return;
			}

			$displayName = trim( (string) ( $data['displayName'] ?? self::_defaultDisplayName( $name ) ) );
			$pageMotion = (string) ( $data['pageMotion'] ?? 'off' );
			$header = array_key_exists( 'header', $data ) ? trim( (string) $data['header'] ) : '/templates/html-header';
			$footer = array_key_exists( 'footer', $data ) ? trim( (string) $data['footer'] ) : '/templates/html-footer';
			if( self::_validDisplayName( $displayName ) === false ) {
				\Nino\Http::fail( $request, 400, 'template name must contain 1–160 safe characters' );
				return;
			}
			if( in_array( $pageMotion, [ 'on', 'off' ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid VPA setting' );
				return;
			}
			if( self::_validIncludePath( $header ) === false || self::_validIncludePath( $footer ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid header or footer template' );
				return;
			}

			$source = self::_metadataSource( $displayName, $pageMotion )
				. SectionDocument::slotSource( 'header', $header )
				. SectionDocument::slotSource( 'footer', $footer );
			if( self::_write( $path, $source ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not create the page template' );
				return;
			}

			\Nino\Http::ok( $request, [
				'name' => $name,
				'filename' => $filename,
				'displayName' => $displayName,
				'pageId' => self::_pageId( $name ),
				'pageMotion' => $pageMotion,
				'revision' => hash( 'sha256', $source ),
			] );
		}

		public static function apiDelete( array &$appData, array &$request ): void {

			$data = Templates::postData();
			$name = (string) ( $data['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page template' );
				return;
			}
			if( !hash_equals( $name, (string) ( $data['confirmName'] ?? '' ) ) ) {
				\Nino\Http::fail( $request, 400, 'template deletion was not confirmed' );
				return;
			}

			$current = (string) file_get_contents( $path );
			if( !hash_equals( hash( 'sha256', $current ), (string) ( $data['revision'] ?? '' ) ) ) {
				\Nino\Http::fail( $request, 409, 'the page template changed on disk; reload before deleting it' );
				return;
			}
			if( @unlink( $path ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not delete the page template' );
				return;
			}

			clearstatcache( true, $path );
			\Nino\Http::ok( $request, [ 'name' => $name, 'filename' => $name. '.tpl' ] );
		}

		public static function apiIncludes( array &$appData, array &$request ): void {

			$names = [ 'html-header' => true, 'html-footer' => true ];
			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [] as $file ) {
				$name = basename( $file, '.tpl' );
				if( preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name ) === 1 && str_starts_with( $name, 'page-' ) === false )
					$names[$name] = true;
			}

			$includes = [];
			foreach( array_keys( $names ) as $name ) {
				$kind = in_array( $name, [ 'html-header', 'html-footer' ], true )
					? 'Page frame'
					: ( str_starts_with( $name, 'section-' ) ? 'Section template' : 'Partial template' );
				$includes[] = [
					'name' => $name,
					'path' => '/templates/'. $name,
					'label' => ucwords( str_replace( [ '-', '_' ], ' ', $name ) ),
					'kind' => $kind,
					'exists' => is_file( \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' ) ),
				];
			}

			usort( $includes, function( array $a, array $b ): int {
				$priority = [ 'html-header' => 0, 'html-footer' => 1 ];
				return ( $priority[$a['name']] ?? 2 ) <=> ( $priority[$b['name']] ?? 2 ) ?: strcmp( $a['name'], $b['name'] );
			} );
			\Nino\Http::ok( $request, [ 'includes' => $includes ] );
		}

		public static function apiLoad( array &$appData, array &$request ): void {

			$name = (string) ( Templates::postData()['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page template' );
				return;
			}

			$source = (string) file_get_contents( $path );
			$parsed = self::_pageSegments( $source, $name );

			\Nino\Http::ok( $request, [
				'name'		=> $name,
				'filename'	=> $name. '.tpl',
				'displayName' => $parsed['displayName'],
				'pageId'	=> self::_pageId( $name ),
				'pageMotion' => $parsed['pageMotion'],
				'revision'	=> hash( 'sha256', $source ),
				'segments'	=> $parsed['segments'],
				'readonly'	=> $parsed['error'],
			] );
		}

		public static function apiInspect( array &$appData, array &$request ): void {

			$source = (string) ( Templates::postData()['source'] ?? '' );
			if( strlen( $source ) > 1048576 ) {
				\Nino\Http::fail( $request, 413, 'section source is too large' );
				return;
			}

			$result = SectionDocument::inspectSection( $source );
			if( $result['valid'] !== true ) {
				\Nino\Http::fail( $request, 400, $result['error'] ?? 'invalid section source' );
				return;
			}

			\Nino\Http::ok( $request, [ 'segment' => $result['segment'] ] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			$data = Templates::postData();
			$name = (string) ( $data['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page template' );
				return;
			}

			$current = (string) file_get_contents( $path );
			if( !hash_equals( hash( 'sha256', $current ), (string) ( $data['revision'] ?? '' ) ) ) {
				\Nino\Http::fail( $request, 409, 'the page template changed on disk; reload before saving' );
				return;
			}

			$segments = is_array( $data['segments'] ?? null ) ? $data['segments'] : null;
			if( $segments === null || count( $segments ) > 500 ) {
				\Nino\Http::fail( $request, 400, 'invalid page segments' );
				return;
			}
			foreach( $segments as $segment )
				if( is_array( $segment ) === false ) {
					\Nino\Http::fail( $request, 400, 'invalid page segment' );
					return;
				}

			$totalBytes = 0;
			foreach( $segments as $segment ) {
				$partBytes = strlen( (string) ( $segment['source'] ?? '' ) );
				$totalBytes += $partBytes;
				if( $partBytes > 4194304 || $totalBytes > 16777216 ) {
					\Nino\Http::fail( $request, 413, 'page template is too large' );
					return;
				}
			}

			$currentParsed = self::_pageSegments( $current, $name );
			if( $currentParsed['error'] !== null ) {
				\Nino\Http::fail( $request, 400, 'the current page template is not safely editable' );
				return;
			}
			if( SectionDocument::rawSources( $segments ) !== SectionDocument::rawSources( $currentParsed['segments'] ) ) {
				\Nino\Http::fail( $request, 400, 'locked page-frame source was changed' );
				return;
			}

			$displayName = trim( (string) ( $data['displayName'] ?? $currentParsed['displayName'] ) );
			$pageMotion = (string) ( $data['pageMotion'] ?? $currentParsed['pageMotion'] );
			if( self::_validDisplayName( $displayName ) === false ) {
				\Nino\Http::fail( $request, 400, 'template name must contain 1–160 safe characters' );
				return;
			}
			if( in_array( $pageMotion, [ 'on', 'off' ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid VPA setting' );
				return;
			}

			$ids = [];
			$slots = [];
			$source = self::_metadataSource( $displayName, $pageMotion );
			foreach( $segments as $segment ) {
				$type = (string) ( $segment['type'] ?? '' );
				$part = (string) ( $segment['source'] ?? '' );

				if( $type === 'raw' ) {
					$source .= $part;
					continue;
				}

				if( $type === 'template' ) {
					$inspected = SectionDocument::inspectTemplate( $part );
					if( $inspected['valid'] !== true ) {
						\Nino\Http::fail( $request, 400, 'invalid template shortcode' );
						return;
					}
					$source .= $part;
					continue;
				}

				if( $type === 'slot' ) {
					$slot = (string) ( $segment['slot'] ?? '' );
					$inspected = SectionDocument::inspectSlot( $part, $slot );
					if( $inspected['valid'] !== true || isset( $slots[$slot] ) ) {
						\Nino\Http::fail( $request, 400, 'invalid or duplicate page template slot' );
						return;
					}
					$slots[$slot] = true;
					$source .= $part;
					continue;
				}

				if( $type !== 'section' ) {
					\Nino\Http::fail( $request, 400, 'unknown page segment type' );
					return;
				}

				$inspected = SectionDocument::inspectSection( $part );
				if( $inspected['valid'] !== true ) {
					\Nino\Http::fail( $request, 400, 'invalid section source' );
					return;
				}

				$htmlId = (string) $inspected['segment']['htmlId'];
				if( $htmlId !== '' && isset( $ids[$htmlId] ) ) {
					\Nino\Http::fail( $request, 409, 'duplicate section id: "'. $htmlId. '"' );
					return;
				}
				if( $htmlId !== '' )
					$ids[$htmlId] = true;

				$source .= $part;
			}

			if( isset( $slots['header'], $slots['footer'] ) === false ) {
				\Nino\Http::fail( $request, 400, 'page template header and footer slots are required' );
				return;
			}
			$components = array_values( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true )
			) );
			if( ( $components[0]['type'] ?? '' ) !== 'slot' || ( $components[0]['slot'] ?? '' ) !== 'header'
				|| ( $components[count( $components ) - 1]['type'] ?? '' ) !== 'slot' || ( $components[count( $components ) - 1]['slot'] ?? '' ) !== 'footer' ) {
				\Nino\Http::fail( $request, 400, 'header and footer slots must wrap every canvas component' );
				return;
			}

			if( trim( $source ) === '' ) {
				\Nino\Http::fail( $request, 400, 'refusing to write an empty page template' );
				return;
			}

			if( self::_write( $path, $source ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the page template' );
				return;
			}

			\Nino\Http::ok( $request, [
				'name' => $name,
				'filename' => $name. '.tpl',
				'displayName' => $displayName,
				'pageMotion' => $pageMotion,
				'bytes' => strlen( $source ),
				'revision' => hash( 'sha256', $source ),
			] );
		}

		private static function _pageId( string $name ): string {
			$id = strtolower( (string) preg_replace( '/[^a-zA-Z0-9-]+/', '-', substr( $name, 5 ) ) );
			$id = trim( $id, '-' ) ?: 'page';
			return preg_match( '/^[a-z]/', $id ) === 1 ? $id : 'p-'. $id;
		}

		/**
		 * Page-only normalization: marked shell includes become fixed slots. A
		 * hand-written page using the conventional exact html-header/html-footer
		 * pair is recognized in memory and receives markers when deliberately
		 * saved through the Builder.
		 */
		private static function _pageSegments( string $source, string $name = 'page' ): array {

			$metadata = self::_readMetadata( $source, $name );
			$parsed = SectionDocument::split( $metadata['source'] );
			$parsed['displayName'] = $metadata['displayName'];
			$parsed['pageMotion'] = $metadata['pageMotion'];
			$parsed['hasMetadata'] = $metadata['hasMetadata'];
			if( $parsed['error'] !== null )
				return $parsed;

			$segments = $parsed['segments'];
			$slotIndexes = [ 'header' => [], 'footer' => [] ];
			foreach( $segments as $index => $segment )
				if( ( $segment['type'] ?? '' ) === 'slot' && isset( $slotIndexes[$segment['slot'] ?? ''] ) )
					$slotIndexes[$segment['slot']][] = $index;

			foreach( $slotIndexes as $slot => $indexes )
				if( count( $indexes ) > 1 ) {
					$parsed['error'] = 'duplicate '. $slot. ' template slot';
					return $parsed;
				}

			$canvasIndexes = array_keys( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template' ], true )
			) );

			if( $slotIndexes['header'] === [] && $canvasIndexes !== [] ) {
				$first = $canvasIndexes[0];
				if( ( $segments[$first]['type'] ?? '' ) === 'template' && ( $segments[$first]['template'] ?? '' ) === 'html-header' ) {
					$segments[$first] = self::_slotSegment( 'header', '/templates/html-header' );
					$slotIndexes['header'] = [ $first ];
				}
			}

			if( $slotIndexes['footer'] === [] && $canvasIndexes !== [] ) {
				$last = $canvasIndexes[count( $canvasIndexes ) - 1];
				if( ( $segments[$last]['type'] ?? '' ) === 'template' && ( $segments[$last]['template'] ?? '' ) === 'html-footer' ) {
					$segments[$last] = self::_slotSegment( 'footer', '/templates/html-footer' );
					$slotIndexes['footer'] = [ $last ];
				}
			}

			if( $slotIndexes['header'] === [] ) {
				$firstComponent = null;
				foreach( $segments as $index => $segment )
					if( in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true ) ) {
						$firstComponent = $index;
						break;
					}
				$index = $firstComponent ?? 0;
				array_splice( $segments, $index, 0, [ self::_slotSegment( 'header', '' ) ] );
			}

			if( array_filter( $segments, fn( array $segment ): bool => ( $segment['type'] ?? '' ) === 'slot' && ( $segment['slot'] ?? '' ) === 'footer' ) === [] ) {
				$lastComponent = -1;
				foreach( $segments as $index => $segment )
					if( in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true ) )
						$lastComponent = $index;
				array_splice( $segments, $lastComponent + 1, 0, [ self::_slotSegment( 'footer', '' ) ] );
			}

			$components = array_values( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true )
			) );
			if( ( $components[0]['type'] ?? '' ) !== 'slot' || ( $components[0]['slot'] ?? '' ) !== 'header'
				|| ( $components[count( $components ) - 1]['type'] ?? '' ) !== 'slot' || ( $components[count( $components ) - 1]['slot'] ?? '' ) !== 'footer' ) {
				$parsed['error'] = 'header and footer template slots must wrap every canvas component';
				return $parsed;
			}

			$parsed['segments'] = array_values( $segments );
			$parsed['componentCount'] = count( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template' ], true )
			) );
			if( $metadata['hasPageMotion'] === false )
				foreach( $segments as $segment )
					if( ( $segment['type'] ?? '' ) === 'section'
						&& is_array( $segment['spec'] ?? null )
						&& in_array( $segment['spec']['pageMotion'] ?? '', [ 'on', 'off' ], true ) ) {
						$parsed['pageMotion'] = $segment['spec']['pageMotion'];
						break;
					}

			return $parsed;
		}

		private static function _readMetadata( string $source, string $name ): array {

			$result = [
				'source' => $source,
				'displayName' => self::_defaultDisplayName( $name ),
				'pageMotion' => 'off',
				'hasMetadata' => false,
				'hasPageMotion' => false,
			];
			$pattern = '~\A<!--[\t ]*nino:template-name[\t ]+([^\r\n<>]+?)[\t ]*-->[\t ]*(?:\r?\n|$)(?:<!--[\t ]*nino:template-vpa[\t ]+(on|off)[\t ]*-->[\t ]*(?:\r?\n|$))?~';
			if( preg_match( $pattern, $source, $match ) !== 1 )
				return $result;

			$displayName = trim( (string) ( $match[1] ?? '' ) );
			if( self::_validDisplayName( $displayName ) === false )
				return $result;

			$result['source'] = substr( $source, strlen( $match[0] ) );
			$result['displayName'] = $displayName;
			$result['hasMetadata'] = true;
			if( isset( $match[2] ) && $match[2] !== '' ) {
				$result['pageMotion'] = $match[2];
				$result['hasPageMotion'] = true;
			}
			return $result;
		}

		private static function _metadataSource( string $displayName, string $pageMotion ): string {
			return '<!-- nino:template-name '. $displayName. " -->\n"
				. '<!-- nino:template-vpa '. $pageMotion. " -->\n";
		}

		private static function _defaultDisplayName( string $name ): string {
			$plain = preg_replace( '/[._-]+/', ' ', preg_replace( '/^page-/', '', $name ) );
			return ucwords( trim( (string) $plain ) ) ?: 'Page';
		}

		private static function _validDisplayName( string $displayName ): bool {
			return $displayName !== ''
				&& strlen( $displayName ) <= 160
				&& preg_match( '/[\x00-\x1F\x7F<>]/', $displayName ) === 0
				&& str_contains( $displayName, '--' ) === false;
		}

		private static function _validIncludePath( string $path ): bool {
			return $path === '' || preg_match( '~^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$~', $path ) === 1;
		}

		private static function _slotSegment( string $slot, string $path ): array {
			return [
				'type' => 'slot',
				'id' => 'slot-'. $slot,
				'slot' => $slot,
				'template' => $path === '' ? '' : basename( $path ),
				'path' => $path,
				'htmlId' => '',
				'source' => SectionDocument::slotSource( $slot, $path ),
				'spec' => null,
				'fills' => [],
				'elementTypes' => [],
				'imageSlots' => [],
			];
		}

		private static function _path( array &$appData, string $name ): ?string {
			if( preg_match( '/^page-[A-Za-z0-9][A-Za-z0-9._-]*$/', $name ) !== 1 || str_contains( $name, '..' ) )
				return null;
			return \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' );
		}

		private static function _write( string $path, string $content ): bool {

			$temp = $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );
			if( $handle === false )
				return false;

			$written = fwrite( $handle, $content );
			$flushed = fflush( $handle );
			fclose( $handle );

			if( $written === false || $written !== strlen( $content ) || $flushed === false ) {
				@unlink( $temp );
				return false;
			}

			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				return false;
			}

			return true;
		}
	}

	/**
	 *	Native-locale quick fill and schema-safe Element-Type creation. Full
	 *	element CRUD remains the shared _admin implementation and is linked
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

		public static function apiKeys( array &$appData, array &$request ): void {

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

			$keys = array_values( array_unique( array_map( 'strval', (array) ( Templates::postData()['keys'] ?? [] ) ) ) );
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

			$data = Templates::postData();
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
			\Nino\Admin\Elements::apiTypes( $appData, $request );
		}

		public static function apiImages( array &$appData, array &$request ): void {
			\Nino\Admin\Images::apiList( $appData, $request );
		}

		public static function apiCreateType( array &$appData, array &$request ): void {

			$data = Templates::postData();
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

			\Nino\Admin\ElementTypes::apiCreate( $appData, $request );
		}

		public static function apiCreateImage( array &$appData, array &$request ): void {

			$data = Templates::postData();
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

			\Nino\Admin\Images::apiCreate( $appData, $request );
		}

		private static function _validKey( string $key ): bool {
			return strlen( $key ) <= 240
				&& preg_match( self::KEY_PATTERN, $key ) === 1
				&& str_contains( $key, '..' ) === false
				&& str_contains( $key, '//' ) === false;
		}
	}
}
