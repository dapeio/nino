<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\Composer	Composes one managed section out of a preset and its bindings
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

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

}
