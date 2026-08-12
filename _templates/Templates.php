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
			if( \Nino\Admin\Admin::isAuthed( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_templates/templates/page-login]';
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
	 *	Manifest-backed entry points for the Section Composer. A preset is a
	 *	curated configuration, not a second copy of every colour/layout
	 *	combination. A project can add another directory with manifest.php;
	 *	if section.tpl is present it is used as a code-authored custom preset.
	 */
	class Library {

		private const string DIRECTORY = __DIR__. '/library';

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
				'choices'	=> Composer::choices(),
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

				$defaults = is_array( $manifest['defaults'] ?? null ) ? $manifest['defaults'] : [];
				$allow = [];
				foreach( Composer::choices() as $name => $choices ) {
					$declared = is_array( $manifest['allow'][$name] ?? null )
						? $manifest['allow'][$name]
						: ( isset( $defaults[$name] ) ? [ $defaults[$name] ] : $choices );
					$allow[$name] = array_values( array_intersect( array_map( 'strval', $declared ), $choices ) );
					if( $allow[$name] === [] ) {
						$default = (string) ( $defaults[$name] ?? '' );
						$allow[$name] = in_array( $default, $choices, true ) ? [ $default ] : $choices;
					}
				}

				$presets[$key] = [
					'key'			=> $key,
					'name'			=> (string) ( $manifest['name'] ?? $key ),
					'description'	=> (string) ( $manifest['description'] ?? '' ),
					'category'		=> (string) ( $manifest['category'] ?? 'Other' ),
					'tags'			=> array_values( array_map( 'strval', (array) ( $manifest['tags'] ?? [] ) ) ),
					'version'		=> max( 1, (int) ( $manifest['version'] ?? 1 ) ),
					'shell'			=> in_array( $manifest['shell'] ?? '', [ 'section', 'hero' ], true ) ? $manifest['shell'] : 'section',
					'defaults'		=> $defaults,
					'allow'			=> $allow,
					'custom'		=> is_file( self::DIRECTORY. '/'. $key. '/section.tpl' ),
				];
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
	}

	/**
	 *	Validated SectionSpec -> ordinary HTML+ source. The output is copied
	 *	into the page and remains independent of the library at runtime.
	 */
	class Composer {

		private const array SURFACES = [ 'default', 'alt', 'primary', 'dark', 'black' ];
		private const array BACKGROUNDS = [ 'none', 'image-cover', 'image-static', 'parallax' ];
		private const array HEADERS = [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ];
		private const array ALIGNS = [ 'left', 'center', 'right' ];
		private const array CONTENTS = [
			'none', 'text', 'media-split', 'articles', 'articles-image', 'cards',
			'lists', 'slider', 'media-slider', 'testimonials', 'profiles', 'stats',
			'features', 'feature-list', 'accordion', 'tabs', 'pricing', 'comparison',
			'data-table', 'logos', 'badges', 'gallery', 'timeline', 'video',
			'video-embed', 'notice', 'contact', 'newsletter',
		];
		private const array CONTENT_STYLES = [ 'auto', 'default', 'alt' ];
		private const array ACTIONS = [ 'none', 'link', 'button', 'dual-buttons' ];
		private const array MOTIONS = [ 'page', 'on', 'off' ];
		private const array PAGE_MOTIONS = [ 'on', 'off' ];
		private const array PADDINGS = [ 'default', 'none', 'compact', 'generous' ];
		private const array MARGINS = [ 'none', 'small', 'medium', 'large' ];
		private const array BORDERS = [ 'none', '1', '2', '3' ];
		private const array LAYOUTS = [
			'auto', '2', '3', '4', 'media-left', 'media-right', 'media-left-full',
			'media-right-full', 'narrow', 'wide', 'spotlight', 'slider', 'grid',
			'mosaic', 'bento', 'split', 'featured', 'check', 'check-2', 'numbered',
			'numbered-2', 'plain', 'striped', 'bordered', 'striped-bordered',
			'pill', 'info', 'success', 'error', '4-3',
		];

		public static function choices(): array {
			return [
				'surface'		=> self::SURFACES,
				'background'	=> self::BACKGROUNDS,
				'header'		=> self::HEADERS,
				'align'			=> self::ALIGNS,
				'content'		=> self::CONTENTS,
				'contentStyle'	=> self::CONTENT_STYLES,
				'action'		=> self::ACTIONS,
				'motion'		=> self::MOTIONS,
				'padding'		=> self::PADDINGS,
				'margin'		=> self::MARGINS,
				'border'		=> self::BORDERS,
				'layout'		=> self::LAYOUTS,
			];
		}

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

		public static function compose( array $input ): array {

			$presetKey = (string) ( $input['preset'] ?? 'blank' );
			$preset = Library::preset( $presetKey );

			if( $preset === null )
				throw new \InvalidArgumentException( 'unknown section preset' );

			$raw = array_merge( $preset['defaults'], $input );
			$pageId = self::_slug( (string) ( $raw['pageId'] ?? '' ), 'page id' );
			$id = self::_slug( (string) ( $raw['id'] ?? '' ), 'section id' );

			$spec = [
				'preset'		=> $presetKey,
				'version'		=> $preset['version'],
				'pageId'		=> $pageId,
				'id'			=> $id,
				'shell'			=> $preset['shell'],
				'surface'		=> self::_choice( $raw, 'surface', $preset ),
				'background'	=> self::_choice( $raw, 'background', $preset ),
				'header'		=> self::_choice( $raw, 'header', $preset ),
				'align'			=> self::_choice( $raw, 'align', $preset ),
				'content'		=> self::_choice( $raw, 'content', $preset ),
				'contentStyle'	=> self::_choice( $raw, 'contentStyle', $preset ),
				'action'		=> self::_choice( $raw, 'action', $preset ),
				'motion'		=> self::_choice( $raw, 'motion', $preset ),
				'pageMotion'	=> in_array( $raw['pageMotion'] ?? '', self::PAGE_MOTIONS, true ) ? $raw['pageMotion'] : 'off',
				'padding'		=> self::_choice( $raw, 'padding', $preset ),
				'margin'		=> self::_choice( $raw, 'margin', $preset ),
				'border'		=> self::_choice( $raw, 'border', $preset ),
				'layout'		=> self::_choice( $raw, 'layout', $preset ),
				'limit'			=> min( 12, max( 1, (int) ( $raw['limit'] ?? 3 ) ) ),
			];

			$modules = self::modules();
			$module = $modules[$spec['content']] ?? null;
			if( $module === null )
				throw new \InvalidArgumentException( 'unknown content module' );

			if( in_array( $spec['layout'], $module['layouts'], true ) === false )
				$spec['layout'] = $module['layouts'][0];

			$elementType = trim( (string) ( $raw['elementType'] ?? '' ) );
			if( $module['source'] === 'elements' ) {
				if( $elementType === '' )
					$elementType = $pageId. '-'. $id;
				if( preg_match( '/^[a-z][a-z0-9_-]*$/', $elementType ) !== 1 )
					throw new \InvalidArgumentException( 'invalid element type' );
			} else {
				$elementType = '';
			}
			$spec['elementType'] = $elementType;

			$source = ( $preset['custom'] === true )
				? self::_renderCustom( $spec, Library::template( $presetKey ) ?? '' )
				: self::_renderGeneric( $spec );

			$source = rtrim( $source, "\r\n" ). "\n";
			$inspected = SectionDocument::inspectSection( $source );

			if( $inspected['valid'] !== true )
				throw new \InvalidArgumentException( 'preset did not produce exactly one valid section' );

			return [
				'source'		=> $source,
				'spec'			=> $spec,
				'fields'		=> self::fieldSuffixes( $spec ),
				'imageSlots'	=> self::imageSuffixes( $spec ),
				'elementSchema'	=> $module['model'],
				'segment'		=> $inspected['segment'],
			];
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
			$source = preg_replace( '#<\?.*?\?>#s', '', $source ) ?? $source;
			// Preview frames deliberately run without Nino.ui.js. Remove VPA's
			// hidden initial state (and its variants) instead of leaving motion-
			// enabled sections permanently transparent inside the iframe.
			$source = preg_replace_callback( '#(\bclass\s*=\s*)(["\'])(.*?)\2#is', function( array $match ): string {
				$classes = preg_split( '/\s+/', trim( $match[3] ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
				$previewClasses = array_values( array_filter(
					$classes,
					fn( string $class ): bool => $class !== 'js-vpa' && str_starts_with( $class, 'js-vpa--' ) === false
				) );
				if( $previewClasses === $classes )
					return $match[0];
				return $match[1]. $match[2]. implode( ' ', $previewClasses ). $match[2];
			}, $source ) ?? $source;
			$source = preg_replace_callback( '#\[image\s+[^\]]+\]#i', fn(): string => '<img src="'. self::_previewImage( 'Section image' ). '" alt="">', $source ) ?? $source;

			$source = preg_replace_callback( '#\[elements\s+([^\]]*)\](.*?)\[/elements\]#is', function( array $match ): string {
				$limit = [];
				preg_match( '/\blimit="(\d+)"/i', $match[1], $limit );
				$count = min( 4, max( 1, (int) ( $limit[1] ?? 3 ) ) );
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

			$source = preg_replace_callback(
				'#\[\[([^\]]+)\]\]#',
				fn( array $fill ): string => self::_previewFill( $fill[1], null ),
				$source
			) ?? $source;
			$source = str_replace( '[csrf]', '', $source );
			$source = preg_replace( '#\[(?:template|elements|image)\b[^\]]*\](?:.*?\[/elements\])?#is', '', $source ) ?? $source;
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

		public static function fieldSuffixes( array $spec ): array {

			$fields = [];
			if( $spec['header'] !== 'none' )
				$fields[] = 'title';
			if( in_array( $spec['header'], [ 'title-subtitle', 'title-subtitle-description' ], true ) )
				$fields[] = 'subtitle';
			if( $spec['header'] === 'title-subtitle-description' )
				$fields[] = 'description';
			$module = self::modules()[$spec['content']] ?? null;
			if( $module !== null )
				$fields = array_merge( $fields, $module['fields'] );
			if( $spec['action'] !== 'none' ) {
				$fields[] = 'cta-label';
				$fields[] = 'cta-uri';
			}
			if( $spec['action'] === 'dual-buttons' ) {
				$fields[] = 'secondary-cta-label';
				$fields[] = 'secondary-cta-uri';
			}

			return array_values( array_unique( $fields ) );
		}

		public static function imageSuffixes( array $spec ): array {
			$images = [];
			if( $spec['background'] !== 'none' )
				$images[] = 'background';
			$module = self::modules()[$spec['content']] ?? null;
			if( $module !== null )
				$images = array_merge( $images, $module['images'] );
			return array_values( array_unique( $images ) );
		}

		private static function _choice( array $raw, string $key, array $preset ): string {
			$allowed = $preset['allow'][$key];
			$value = (string) ( $raw[$key] ?? '' );
			return in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}

		private static function _slug( string $value, string $label ): string {
			$value = strtolower( trim( $value ) );
			if( preg_match( '/^[a-z][a-z0-9-]*$/', $value ) !== 1 )
				throw new \InvalidArgumentException( 'invalid '. $label );
			return $value;
		}

		private static function _renderGeneric( array $spec ): string {

			$prefix = '/page-'. $spec['pageId']. '/'. $spec['id'];
			$classes = self::_sectionClasses( $spec );
			$attributes = ' id="'. self::_escape( $spec['id'] ). '" class="'. self::_escape( implode( ' ', $classes ) ). '"';

			if( $spec['shell'] === 'hero' )
				$attributes .= ' data-cover-height="100"';

			$meta = '<!-- nino:section '. json_encode( $spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ). ' -->';
			$inner = "\t". $meta. "\n";

			if( $spec['background'] !== 'none' )
				$inner .= "\t[image ". $prefix. "/background alt=\"\"]\n";

			$content = self::_renderBody( $spec, $prefix );

			if( $spec['background'] !== 'none' ) {
				$backgroundContentClass = $spec['background'] === 'image-static' ? 'ui-img-background-content' : 'js-cover-content';
				$inner .= "\t<div class=\"". $backgroundContentClass. "\">\n". self::_indent( $content, 1 ). "\t</div>\n";
			}
			else
				$inner .= $content;

			return '<section'. $attributes. ">\n". $inner. '</section>';
		}

		private static function _renderBody( array $spec, string $prefix ): string {

			$rowClasses = [ 'ui-grid-row' ];
			if( in_array( $spec['content'], [ 'media-split', 'feature-list' ], true ) )
				$rowClasses[] = 'ui-grid-middle';
			if( in_array( $spec['layout'], [ 'media-left-full', 'media-right-full' ], true ) )
				$rowClasses[] = 'ui-grid--fullwidth';
			if( $spec['motion'] === 'on' || ( $spec['motion'] === 'page' && $spec['pageMotion'] === 'on' ) )
				$rowClasses[] = 'js-vpa';

			$out = "\t<div class=\"". implode( ' ', $rowClasses ). "\">\n";
			$out .= self::_renderHeader( $spec, $prefix );
			$out .= self::_renderContent( $spec, $prefix );
			$out .= self::_renderAction( $spec, $prefix );
			$out .= "\t</div>\n";

			return $out;
		}

		private static function _renderHeader( array $spec, string $prefix ): string {

			if( $spec['header'] === 'none' )
				return '';

			$classes = [ 'ui-grid-100' ];
			if( $spec['content'] !== 'none' || $spec['action'] !== 'none' )
				$classes[] = 'ui-mb-3';
			if( $spec['align'] !== 'left' )
				$classes[] = 'ui-text-'. $spec['align'];

			$headingAlign = $spec['align'] === 'center' ? '' : ' ui-text-'. $spec['align'];
			$titleClass = ( $spec['shell'] === 'hero' ? 'ui-atf-title' : 'ui-section-title' ). $headingAlign;
			$subtitleClass = ( $spec['shell'] === 'hero' ? 'ui-atf-subtitle' : 'ui-section-subtitle' ). $headingAlign;

			$out = "\t\t<div class=\"". implode( ' ', $classes ). "\">\n";
			$out .= "\t\t\t<h2 class=\"". $titleClass. "\">[[". $prefix. "/title]]</h2>\n";

			if( in_array( $spec['header'], [ 'title-subtitle', 'title-subtitle-description' ], true ) )
				$out .= "\t\t\t<p class=\"". $subtitleClass. "\">[[". $prefix. "/subtitle]]</p>\n";

			if( $spec['header'] === 'title-subtitle-description' )
				$out .= "\t\t\t<p>[[". $prefix. "/description]]</p>\n";

			$out .= "\t\t</div>\n";
			return $out;
		}

		private static function _renderContent( array $spec, string $prefix ): string {

			$content = $spec['content'];
			if( $content === 'none' )
				return '';

			if( $content === 'text' ) {
				$width = $spec['layout'] === 'narrow' ? 'ui-grid-m-66' : 'ui-grid-100';
				return "\t\t<div class=\"ui-grid-100 ". $width. "\">\n\t\t\t<p>[[". $prefix. "/content]]</p>\n\t\t</div>\n";
			}

			if( $content === 'media-split' ) {
				$media = "\t\t<div class=\"ui-grid-100 ui-grid-m-50 ui-img-cover\">\n\t\t\t[image ". $prefix. "/image alt=\"\"]\n\t\t</div>\n";
				$text = "\t\t<div class=\"ui-grid-100 ui-grid-m-50\">\n\t\t\t<article class=\"". self::_articleClass( $spec ). "\">\n\t\t\t\t<div class=\"ui-article-content\">[[". $prefix. "/content]]</div>\n\t\t\t</article>\n\t\t</div>\n";
				return in_array( $spec['layout'], [ 'media-right', 'media-right-full' ], true ) ? $text. $media : $media. $text;
			}

			$type = '/'. $spec['elementType'];
			$limit = $spec['limit'];
			$articleClass = self::_articleClass( $spec );

			if( $content === 'lists' ) {
				$numbered = str_starts_with( $spec['layout'], 'numbered' );
				$listClass = 'ui-list '. ( $numbered ? 'ui-list--numbered' : 'ui-list--check' ). ' ui-list--content';
				if( str_ends_with( $spec['layout'], '-2' ) )
					$listClass .= ' ui-list--columns';
				return "\t\t<div class=\"ui-grid-100\">\n\t\t\t<ul class=\"". $listClass. "\">\n\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t<li>[[title]]</li>\n\t\t\t\t[/elements]\n\t\t\t</ul>\n\t\t</div>\n";
			}

			if( in_array( $content, [ 'articles', 'articles-image' ], true ) ) {
				$grid = self::_gridClass( $spec['layout'] );
				$image = $content === 'articles-image' ? "\t\t\t\t<img class=\"ui-article-img\" src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\">\n" : '';
				return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n"
					. "\t\t<div class=\"ui-grid-100 ". $grid. " ui-mb-3\">\n"
					. "\t\t\t<article class=\"". $articleClass. "\">\n". $image
					. "\t\t\t\t<div class=\"ui-article-content\">\n"
					. "\t\t\t\t\t<h3 class=\"ui-article-title\">[[title]]</h3>\n"
					. "\t\t\t\t\t<div class=\"ui-article-descr\">[[description]]</div>\n"
					. "\t\t\t\t\t<a href=\"[[link]]\" class=\"ui-btn ui-btn--primary\">[[linkLabel]]</a>\n"
					. "\t\t\t\t</div>\n\t\t\t</article>\n\t\t</div>\n\t\t[/elements]\n";
			}

			if( $content === 'cards' ) {
				$grid = $spec['layout'] === 'spotlight' ? 'ui-grid-m-66' : self::_gridClass( $spec['layout'] );
				$center = $spec['layout'] === 'spotlight' ? ' style="margin:0 auto;"' : '';
				return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n"
					. "\t\t<div class=\"ui-grid-100 ". $grid. " ui-mb-3\"". $center. ">\n"
					. "\t\t\t<article class=\"". $articleClass. " ui-article--fullwidth\">\n"
					. "\t\t\t\t<div class=\"ui-img-cover\">\n"
					. "\t\t\t\t\t<img class=\"ui-article-img ui-article-img--maxheight\" src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\">\n"
					. "\t\t\t\t\t<span class=\"ui-badge ui-badge--primary\">[[badge]]</span>\n"
					. "\t\t\t\t</div>\n"
					. "\t\t\t\t<div class=\"ui-article-content\">\n"
					. "\t\t\t\t\t<h3 class=\"ui-article-title\">[[title]]</h3>\n"
					. "\t\t\t\t\t<div class=\"ui-article-descr\">[[description]]</div>\n"
					. "\t\t\t\t\t<div class=\"ui-article-price\">[[price]]</div>\n"
					. "\t\t\t\t\t<a href=\"[[link]]\" class=\"ui-btn ui-btn--primary\">[[linkLabel]]</a>\n"
					. "\t\t\t\t</div>\n\t\t\t</article>\n\t\t</div>\n\t\t[/elements]\n";
			}

			if( $content === 'testimonials' ) {
				$testimonial = "<article class=\"". $articleClass. " ui-text-center\"><div class=\"ui-article-content\"><blockquote class=\"ui-article-title\">[[quote]]</blockquote><div class=\"ui-testimonial-author\"><img src=\"[[/nino/dir]]/images/[[image]]\" alt=\"\"><span><strong>[[author]]</strong><small>[[role]]</small></span></div></div></article>";
				if( $spec['layout'] === 'slider' )
					return "\t\t<div class=\"ui-grid-100\">\n\t\t\t<div class=\"js-slider\" data-slider-pos=\"0\" data-slider-width=\"60%\" data-slider-min=\"280px\">\n\t\t\t\t<ul>\n"
						. "\t\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t\t<li>". $testimonial. "</li>\n\t\t\t\t\t[/elements]\n"
						. "\t\t\t\t</ul>\n\t\t\t</div>\n\t\t</div>\n";

				$grid = $spec['layout'] === 'spotlight' ? 'ui-grid-m-66' : self::_gridClass( $spec['layout'] );
				$center = $spec['layout'] === 'spotlight' ? ' style="margin:0 auto;"' : '';
				return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t<div class=\"ui-grid-100 ". $grid. " ui-mb-3\"". $center. ">\n\t\t\t". $testimonial. "\n\t\t</div>\n\t\t[/elements]\n";
			}

			if( $content === 'profiles' ) {
				if( $spec['layout'] === 'spotlight' )
					return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t<div class=\"ui-grid-100 ui-grid-m-66\" style=\"margin:0 auto;\">\n\t\t\t<article class=\"". $articleClass. " ui-article-cols-m\">\n\t\t\t\t<img class=\"ui-article-img ui-profile-image\" src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\">\n\t\t\t\t<div class=\"ui-article-content\"><h3 class=\"ui-article-title\">[[title]]</h3><p class=\"ui-article-subtitle\">[[role]]</p><div class=\"ui-article-descr\">[[description]]</div><p class=\"ui-profile-links\"><a href=\"mailto:[[email]]\">[[email]]</a><a href=\"tel:[[phone]]\">[[phone]]</a></p></div>\n\t\t\t</article>\n\t\t</div>\n\t\t[/elements]\n";

				$grid = self::_gridClass( $spec['layout'] );
				return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t<div class=\"ui-grid-100 ". $grid. " ui-mb-3\">\n\t\t\t<article class=\"". $articleClass. " ui-article--fullwidth\"><img class=\"ui-article-img ui-article-img--maxheight\" src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\"><div class=\"ui-article-content\"><h3 class=\"ui-article-title\">[[title]]</h3><p class=\"ui-article-subtitle\">[[role]]</p><div class=\"ui-article-descr\">[[description]]</div></div></article>\n\t\t</div>\n\t\t[/elements]\n";
			}

			if( $content === 'stats' ) {
				$grid = self::_gridClass( $spec['layout'] );
				return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t<div class=\"ui-grid-100 ". $grid. " ui-text-center ui-mb-2\"><div class=\"js-stat-counter\" data-stat-counter-to=\"[[number]]\" data-stat-counter-suffix=\"[[suffix]]\">0</div><p>[[title]]</p></div>\n\t\t[/elements]\n";
			}

			if( $content === 'features' ) {
				if( $spec['layout'] === 'bento' )
					return "\t\t<div class=\"ui-grid-100\"><div class=\"ui-bento\">\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<article class=\"". $articleClass. " ui-bento-item\"><div class=\"ui-article-content\">". self::_checkIcon(). "<h3 class=\"ui-article-title\">[[title]]</h3><div class=\"ui-article-descr\">[[description]]</div></div></article>\n\t\t\t[/elements]\n\t\t</div></div>\n";

				$grid = self::_gridClass( $spec['layout'] );
				return "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t<div class=\"ui-grid-100 ". $grid. " ui-mb-3\"><article class=\"". $articleClass. "\"><div class=\"ui-article-content\">". self::_checkIcon(). "<h3 class=\"ui-article-title\">[[title]]</h3><div class=\"ui-article-descr\">[[description]]</div></div></article></div>\n\t\t[/elements]\n";
			}

			if( $content === 'feature-list' ) {
				$media = "\t\t<div class=\"ui-grid-100 ui-grid-m-50 ui-img-cover\">\n\t\t\t[image ". $prefix. "/image alt=\"\"]\n\t\t</div>\n";
				$list = "\t\t<div class=\"ui-grid-100 ui-grid-m-50 ui-p-2\">\n\t\t\t<ul class=\"ui-feature-list\">\n\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t<li class=\"ui-feature-item\">". self::_checkIcon(). "<div><h3>[[title]]</h3><div>[[description]]</div></div></li>\n\t\t\t\t[/elements]\n\t\t\t</ul>\n\t\t</div>\n";
				return $spec['layout'] === 'media-right' ? $list. $media : $media. $list;
			}

			if( $content === 'slider' ) {
				$width = $spec['layout'] === 'narrow' ? '60%' : '80%';
				return "\t\t<div class=\"ui-grid-100\">\n\t\t\t<div class=\"js-slider\" data-slider-pos=\"0\" data-slider-width=\"". $width. "\" data-slider-min=\"280px\">\n\t\t\t\t<ul>\n"
					. "\t\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n"
					. "\t\t\t\t\t<li><article class=\"". $articleClass. " ui-text-center\"><div class=\"ui-article-content\"><p class=\"ui-article-title\">[[title]]</p><p>[[description]]</p></div></article></li>\n"
					. "\t\t\t\t\t[/elements]\n\t\t\t\t</ul>\n\t\t\t</div>\n\t\t</div>\n";
			}

			if( $content === 'media-slider' ) {
				$width = $spec['layout'] === 'narrow' ? '72%' : '88%';
				return "\t\t<div class=\"ui-grid-100\">\n\t\t\t<div class=\"js-slider ui-media-slider\" data-slider-pos=\"0\" data-slider-width=\"". $width. "\" data-slider-min=\"280px\">\n\t\t\t\t<ul>\n"
					. "\t\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n"
					. "\t\t\t\t\t<li><article class=\"". $articleClass. " ui-article--fullwidth\"><img class=\"ui-article-img ui-article-img--maxheight\" src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\"><div class=\"ui-article-content\"><h3 class=\"ui-article-title\">[[title]]</h3><div class=\"ui-article-descr\">[[description]]</div><a href=\"[[link]]\" class=\"ui-btn ui-btn--primary\">[[linkLabel]]</a></div></article></li>\n"
					. "\t\t\t\t\t[/elements]\n\t\t\t\t</ul>\n\t\t\t</div>\n\t\t</div>\n";
			}

			if( $content === 'accordion' ) {
				$width = $spec['layout'] === 'narrow' ? 'ui-grid-m-66' : 'ui-grid-100';
				return "\t\t<div class=\"ui-grid-100 ". $width. "\">\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<details class=\"ui-accordion\" name=\"". self::_escape( $spec['id'] ). "\">\n\t\t\t\t<summary class=\"ui-accordion-trigger\">[[title]]</summary>\n\t\t\t\t<div class=\"ui-accordion-panel\">[[description]]</div>\n\t\t\t</details>\n\t\t\t[/elements]\n\t\t</div>\n";
			}

			if( $content === 'tabs' ) {
				$width = $spec['layout'] === 'narrow' ? 'ui-grid-m-75' : 'ui-grid-100';
				$tabId = self::_escape( $spec['id'] ). '-tab-[[.id]]';
				return "\t\t<div class=\"ui-grid-100 ". $width. "\" style=\"margin:0 auto;\">\n\t\t\t<div class=\"js-tabs\">\n\t\t\t\t<div class=\"js-tabs-nav\" role=\"tablist\">\n\t\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t\t<button type=\"button\" class=\"js-tabs-tab\" role=\"tab\" data-tabs-target=\"". $tabId. "\">[[title]]</button>\n\t\t\t\t\t[/elements]\n\t\t\t\t</div>\n\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t<div class=\"js-tabs-panel\" role=\"tabpanel\" id=\"". $tabId. "\">[[description]]</div>\n\t\t\t\t[/elements]\n\t\t\t</div>\n\t\t</div>\n";
			}

			if( $content === 'pricing' ) {
				$rowClass = 'ui-pricing-row'. ( $spec['layout'] === 'featured' ? ' ui-pricing-row--feature-middle' : '' );
				return "\t\t<div class=\"ui-grid-100\">\n\t\t\t<div class=\"". $rowClass. "\">\n\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t<div class=\"ui-pricing-item\"><span class=\"ui-badge ui-badge--primary\">[[badge]]</span><h3 class=\"ui-pricing-title\">[[title]]</h3><div>[[description]]</div><p class=\"ui-pricing-price\">[[price]] &euro;</p><div class=\"ui-pricing-features\">[[features]]</div><a href=\"[[link]]\" class=\"ui-btn ui-btn--primary\">[[linkLabel]]</a></div>\n\t\t\t\t[/elements]\n\t\t\t</div>\n\t\t</div>\n";
			}

			if( $content === 'comparison' ) {
				return "\t\t<div class=\"ui-grid-100\"><div class=\"ui-table-wrap\"><table class=\"ui-table ui-table--striped ui-table--bordered\"><thead><tr><th scope=\"col\">&nbsp;</th><th scope=\"col\">[[". $prefix. "/column-a]]</th><th scope=\"col\">[[". $prefix. "/column-b]]</th></tr></thead><tbody>\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<tr><th scope=\"row\">[[title]]</th><td>[[optionA]]</td><td>[[optionB]]</td></tr>\n\t\t\t[/elements]\n\t\t</tbody></table></div></div>\n";
			}

			if( $content === 'data-table' ) {
				$tableClass = 'ui-table';
				if( in_array( $spec['layout'], [ 'striped', 'striped-bordered' ], true ) )
					$tableClass .= ' ui-table--striped';
				if( in_array( $spec['layout'], [ 'bordered', 'striped-bordered' ], true ) )
					$tableClass .= ' ui-table--bordered';
				return "\t\t<div class=\"ui-grid-100\"><div class=\"ui-table-wrap\"><table class=\"". $tableClass. "\"><thead><tr><th scope=\"col\">[[". $prefix. "/column-a]]</th><th scope=\"col\">[[". $prefix. "/column-b]]</th><th scope=\"col\">[[". $prefix. "/column-c]]</th></tr></thead><tbody>\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<tr><td>[[columnA]]</td><td>[[columnB]]</td><td>[[columnC]]</td></tr>\n\t\t\t[/elements]\n\t\t</tbody></table></div></div>\n";
			}

			if( $content === 'logos' ) {
				return "\t\t<div class=\"ui-grid-100\"><div class=\"ui-logos\">\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<span class=\"ui-logos-item\"><img src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\"></span>\n\t\t\t[/elements]\n\t\t</div></div>\n";
			}

			if( $content === 'badges' ) {
				$badgeClass = 'ui-badge'. ( $spec['layout'] === 'pill' ? ' ui-badge--pill' : '' );
				$cloudClass = 'ui-badge-cloud'. ( $spec['align'] === 'center' ? '' : ' ui-badge-cloud--'. $spec['align'] );
				return "\t\t<div class=\"ui-grid-100\"><div class=\"". $cloudClass. "\">\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<span class=\"". $badgeClass. "\">[[title]]</span>\n\t\t\t[/elements]\n\t\t</div></div>\n";
			}

			if( $content === 'gallery' ) {
				$galleryClass = 'ui-gallery'. ( $spec['layout'] === 'mosaic' ? ' ui-gallery--mosaic' : '' );
				$modalId = self::_escape( $spec['id'] ). '-gallery-[[.id]]';
				return "\t\t<div class=\"ui-grid-100\"><div class=\"". $galleryClass. "\">\n\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t<div class=\"ui-gallery-item\"><button type=\"button\" class=\"js-modal-trigger\" data-modal-target=\"". $modalId. "\" aria-label=\"[[title]]\"><img src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\" loading=\"lazy\"></button></div>\n\t\t\t[/elements]\n\t\t</div></div>\n"
					. "\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t<dialog class=\"js-modal js-modal--lightbox\" id=\"". $modalId. "\"><button type=\"button\" class=\"js-modal-close\" aria-label=\"Close\">&times;</button><img src=\"[[/nino/dir]]/images/[[image]]\" alt=\"[[title]]\"></dialog>\n\t\t[/elements]\n";
			}

			if( $content === 'timeline' ) {
				return "\t\t<div class=\"ui-grid-100\">\n\t\t\t<ol class=\"ui-timeline\">\n\t\t\t\t[elements ". $type. " limit=\"". $limit. "\"]\n\t\t\t\t<li class=\"ui-timeline-step\"><div class=\"ui-timeline-number\">[[number]]</div><h3>[[title]]</h3><p>[[description]]</p></li>\n\t\t\t\t[/elements]\n\t\t\t</ol>\n\t\t</div>\n";
			}

			if( $content === 'video' ) {
				$width = $spec['layout'] === 'narrow' ? 'ui-grid-m-75' : 'ui-grid-100';
				$modalId = self::_escape( $spec['id'] ). '-video';
				return "\t\t<div class=\"ui-grid-100 ". $width. "\" style=\"margin:0 auto;\"><button type=\"button\" class=\"ui-video-poster js-modal-trigger\" data-modal-target=\"". $modalId. "\" aria-label=\"Play video\">[image ". $prefix. "/image alt=\"\"]<svg class=\"ui-video-play\" aria-hidden=\"true\" viewBox=\"0 0 24 24\"><path d=\"M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z\"></path></svg></button></div>\n\t\t<dialog class=\"js-modal js-modal--video\" id=\"". $modalId. "\"><button type=\"button\" class=\"js-modal-close\" aria-label=\"Close\">&times;</button><div class=\"ui-video\"><iframe src=\"[[". $prefix. "/video-uri]]\" title=\"Video\" loading=\"lazy\" allowfullscreen></iframe></div></dialog>\n";
			}

			if( $content === 'video-embed' ) {
				$width = $spec['layout'] === 'narrow' ? 'ui-grid-m-75' : 'ui-grid-100';
				$videoClass = 'ui-video'. ( $spec['layout'] === '4-3' ? ' ui-video--4-3' : '' );
				return "\t\t<div class=\"ui-grid-100 ". $width. "\" style=\"margin:0 auto;\"><div class=\"". $videoClass. "\"><iframe src=\"[[". $prefix. "/video-uri]]\" title=\"Video\" loading=\"lazy\" allowfullscreen></iframe></div></div>\n";
			}

			if( $content === 'notice' ) {
				return "\t\t<div class=\"ui-grid-100 ui-grid-m-75\" style=\"margin:0 auto;\"><div class=\"ui-alert ui-alert--". self::_escape( $spec['layout'] ). " ui-alert--content\">[[". $prefix. "/content]]</div></div>\n";
			}

			if( $content === 'contact' ) {
				$formId = self::_escape( $spec['id'] );
				return "\t\t<div class=\"ui-grid-100 ui-grid-m-50\"><div>[[". $prefix. "/content]]</div><address class=\"ui-contact-details\">[[". $prefix. "/address]]<a href=\"tel:[[". $prefix. "/phone]]\">[[". $prefix. "/phone]]</a><a href=\"mailto:[[". $prefix. "/email]]\">[[". $prefix. "/email]]</a></address></div>\n"
					. "\t\t<div class=\"ui-grid-100 ui-grid-m-50\"><form class=\"ui-form\">[csrf]<label for=\"". $formId. "-name\">[[/form/label/name]]</label><input type=\"text\" id=\"". $formId. "-name\" name=\"name\" class=\"ui-form-input\" required><label for=\"". $formId. "-email\">[[/form/label/email]]</label><input type=\"email\" id=\"". $formId. "-email\" name=\"email\" class=\"ui-form-input\" required><label for=\"". $formId. "-message\">[[/form/label/message]]</label><textarea id=\"". $formId. "-message\" name=\"message\" class=\"ui-form-textarea\" required></textarea><input type=\"text\" name=\"location\" class=\"ui-sr-only\" tabindex=\"-1\" autocomplete=\"off\"><p class=\"ui-form-message\"></p><p><small>[[/form/required]]</small></p><button type=\"submit\" class=\"ui-btn ui-btn--primary ui-form-submit\">[[/form/label/submit]]</button></form></div>\n";
			}

			if( $content === 'newsletter' ) {
				$width = $spec['layout'] === 'narrow' ? 'ui-grid-m-66' : 'ui-grid-100';
				$emailId = self::_escape( $spec['id'] ). '-email';
				return "\t\t<div class=\"ui-grid-100 ". $width. "\" style=\"margin:0 auto;\"><form class=\"ui-form js-newsletter-form ui-newsletter-inline\" action=\"[[/nino/dir]]/.newsletter\">[csrf]<input type=\"text\" name=\"location\" value=\"\" tabindex=\"-1\" autocomplete=\"off\" aria-hidden=\"true\" class=\"ui-sr-only\"><label for=\"". $emailId. "\" class=\"ui-sr-only\">[[/newsletter/label/email]]</label><input type=\"email\" id=\"". $emailId. "\" name=\"email\" class=\"ui-form-input\" placeholder=\"[[/newsletter/label/email]]\" required><button type=\"submit\" class=\"ui-btn ui-btn--primary ui-form-submit\">[[/newsletter/label/submit]]</button><p class=\"ui-form-message ui-grid-100\"></p></form></div>\n";
			}

			return '';
		}

		private static function _renderAction( array $spec, string $prefix ): string {

			if( $spec['action'] === 'none' )
				return '';

			$class = in_array( $spec['action'], [ 'button', 'dual-buttons' ], true ) ? 'ui-btn ui-btn--primary' : '';
			$wrap = 'ui-grid-100 ui-mt-3'. ( $spec['align'] === 'left' ? '' : ' ui-text-'. $spec['align'] );
			$secondary = $spec['action'] === 'dual-buttons'
				? "\t\t\t<a href=\"[[". $prefix. "/secondary-cta-uri]]\" class=\"ui-btn ui-btn--outline\">[[". $prefix. "/secondary-cta-label]]</a>\n"
				: '';
			return "\t\t<div class=\"". $wrap. "\">\n\t\t\t<a href=\"[[". $prefix. "/cta-uri]]\" class=\"". $class. "\">[[". $prefix. "/cta-label]]</a>\n". $secondary. "\t\t</div>\n";
		}

		private static function _sectionClasses( array $spec ): array {

			$classes = $spec['shell'] === 'hero' ? [ 'ui-atf', 'ui-section--fullwidth' ] : [ 'ui-section' ];

			if( $spec['surface'] !== 'default' )
				$classes[] = 'ui-section--'. $spec['surface'];

			if( $spec['background'] === 'image-cover' ) {
				$classes[] = 'ui-section--fullwidth';
				$classes[] = 'js-cover';
				$classes[] = 'js-cover--dim';
				if( $spec['align'] === 'center' )
					$classes[] = 'js-cover-center';
			} else if( $spec['background'] === 'image-static' ) {
				$classes[] = 'ui-section--fullwidth';
				$classes[] = 'ui-img-background';
				$classes[] = 'ui-img-background--dim';
			} else if( $spec['background'] === 'parallax' ) {
				$classes[] = 'ui-section--fullwidth';
				$classes[] = 'js-parallex';
				$classes[] = 'js-parallex--dim';
			} else if( $spec['shell'] === 'hero' ) {
				$classes[] = 'ui-atf--fullscreen';
			}

			if( in_array( $spec['layout'], [ 'media-left-full', 'media-right-full' ], true ) ) {
				$classes[] = 'ui-section--fullwidth';
				$classes[] = 'ui-section--fullheight';
			}

			$padding = [ 'none' => '0', 'compact' => '2', 'generous' => '6' ][$spec['padding']] ?? null;
			if( $padding !== null ) {
				$classes[] = 'ui-pt-'. $padding;
				$classes[] = 'ui-pb-'. $padding;
			}

			$margin = [ 'small' => '1', 'medium' => '2', 'large' => '3' ][$spec['margin']] ?? null;
			if( $margin !== null ) {
				$classes[] = 'ui-mt-'. $margin;
				$classes[] = 'ui-mb-'. $margin;
			}

			if( $spec['border'] !== 'none' )
				$classes[] = 'ui-section--border-'. $spec['border'];

			return array_values( array_unique( $classes ) );
		}

		private static function _articleClass( array $spec ): string {
			$classes = [ 'ui-article' ];
			$autoAlt = in_array( $spec['surface'], [ 'alt', 'primary', 'dark', 'black' ], true );
			if( $spec['contentStyle'] === 'alt' || ( $spec['contentStyle'] === 'auto' && $autoAlt === true ) )
				$classes[] = 'ui-article--alt';
			return implode( ' ', $classes );
		}

		private static function _gridClass( string $layout ): string {
			return match( $layout ) {
				'2' => 'ui-grid-m-50',
				'4' => 'ui-grid-m-25',
				default => 'ui-grid-m-33',
			};
		}

		private static function _checkIcon(): string {
			return '<svg class="ui-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>';
		}

		private static function _renderCustom( array $spec, string $template ): string {

			if( trim( $template ) === '' )
				throw new \InvalidArgumentException( 'custom preset has no section template' );

			$prefix = '/page-'. $spec['pageId']. '/'. $spec['id'];
			$replace = [
				'{{section:id}}'		=> self::_escape( $spec['id'] ),
				'{{section:classes}}'	=> self::_escape( implode( ' ', self::_sectionClasses( $spec ) ) ),
				'{{section:meta}}'		=> '<!-- nino:section '. json_encode( $spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ). ' -->',
				'{{content:prefix}}'		=> $prefix,
				'{{elements:type}}'		=> $spec['elementType'],
			];

			foreach( self::fieldSuffixes( $spec ) as $suffix )
				$replace['{{text:'. $suffix. '}}'] = '[['. $prefix. '/'. $suffix. ']]';
			foreach( self::imageSuffixes( $spec ) as $suffix )
				$replace['{{image:'. $suffix. '}}'] = '[image '. $prefix. '/'. $suffix. ' alt=""]';

			$rendered = strtr( $template, $replace );
			if( preg_match( '/\{\{(?:section|content|elements|text|image):[^}]+\}\}/', $rendered ) === 1 )
				throw new \InvalidArgumentException( 'custom preset contains an unresolved token' );

			return $rendered;
		}

		private static function _indent( string $source, int $levels ): string {
			$indent = str_repeat( "\t", $levels );
			return $indent. str_replace( "\n", "\n". $indent, rtrim( $source, "\r\n" ) ). "\n";
		}

		private static function _escape( string $value ): string {
			return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
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
			// Backwards-compatible with the first Template Builder API. The UI now
			// sends the real filename so it never hides the page- prefix or suffix.
			if( $filename === '' && isset( $data['id'] ) )
				$filename = 'page-'. strtolower( trim( (string) $data['id'] ) ). '.tpl';
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
		 * Page-only normalization: marked shell includes become fixed slots. The
		 * historic exact html-header/html-footer pair is migrated in memory and
		 * receives markers the next time that page is deliberately saved.
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

		private const string KEY_PATTERN = '#^/page-[a-z][a-z0-9-]*/[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#';

		public static function actions(): array {
			return [
				'content/fields'		=> [ self::class, 'apiFields' ],
				'content/save'			=> [ self::class, 'apiSave' ],
				'content/types'			=> [ self::class, 'apiTypes' ],
				'content/type-create'	=> [ self::class, 'apiCreateType' ],
				'content/images'		=> [ self::class, 'apiImages' ],
				'content/image-create'	=> [ self::class, 'apiCreateImage' ],
			];
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
				if( preg_match( self::KEY_PATTERN, $key ) !== 1 ) {
					\Nino\Http::fail( $request, 400, 'invalid page text key' );
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

				if( preg_match( self::KEY_PATTERN, $key ) !== 1 ) {
					\Nino\Http::fail( $request, 400, 'invalid page text key' );
					return;
				}

				$entry = \Nino\Text::entry( $appData, $key );
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
			$module = Composer::modules()[(string) ( $data['module'] ?? '' )] ?? null;
			$uri = (string) ( $data['uri'] ?? '' );

			if( $module === null || $module['source'] !== 'elements' || preg_match( '/^[a-z][a-z0-9_-]*$/', $uri ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid module or element type' );
				return;
			}

			$_POST['data'] = json_encode( [
				'uri' => $uri,
				'title' => trim( (string) ( $data['title'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', $uri ) ),
				'model' => $module['model'],
			] );

			\Nino\Admin\ElementTypes::apiCreate( $appData, $request );
		}

		public static function apiCreateImage( array &$appData, array &$request ): void {

			$data = Templates::postData();
			$uri = (string) ( $data['uri'] ?? '' );
			$suffix = str_ends_with( $uri, '/background' ) ? 'background' : ( str_ends_with( $uri, '/image' ) ? 'image' : '' );

			if( preg_match( '#^/page-[a-z][a-z0-9-]*/[a-z][a-z0-9-]*/(background|image)$#', $uri ) !== 1 || $suffix === '' ) {
				\Nino\Http::fail( $request, 400, 'invalid page image slot' );
				return;
			}

			$_POST['data'] = json_encode( [
				'uri' => $uri,
				'label' => trim( (string) ( $data['label'] ?? '' ) ) ?: ucwords( str_replace( [ '/', '-' ], ' ', trim( $uri, '/' ) ) ),
				'width' => $suffix === 'background' ? 1920 : 1200,
				'height' => $suffix === 'background' ? 1080 : 900,
			] );

			\Nino\Admin\Images::apiCreate( $appData, $request );
		}
	}
}
