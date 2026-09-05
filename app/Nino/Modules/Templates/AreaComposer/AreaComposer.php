<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\AreaComposer	Named areas and a safe, ordered component model (Template Builder v3)
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

	final class AreaComposer {

		private const string ID_PATTERN = '/^[a-z][a-z0-9-]*$/';
		private const string FIELD_PATTERN = '/^[A-Za-z][A-Za-z0-9]*$/';
		private const string TEXT_KEY_PATTERN = '#^/[A-Za-z0-9][A-Za-z0-9_./-]*$#';
		private const string IMAGE_KEY_PATTERN = '#^/[a-z0-9][a-z0-9_/-]*$#';
		private const string TEMPLATE_PATTERN = '#^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$#';
		private const string DATA_NAME_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';
		private const int MAX_LITERAL_LENGTH = 4000;
		private const int MAX_DATA_LENGTH = 240;
		private const array RESERVED_DATA = [ 'data-cover-height' ];
		private const array FRAME_CHOICES = [
			'screen' => [ 'auto', 'off', '50', '75', '90', '100' ],
			'vertical' => [ 'auto', 'top', 'middle', 'bottom' ],
			'background' => [ 'auto', 'default', 'alt', 'primary', 'dark', 'black', 'cover', 'parallax' ],
			'container' => [ 'auto', 'default', 'narrow', 'wide' ],
			'padding' => [ 'auto', 'none', 'small', 'default', 'big' ],
			'margin' => [ 'auto', 'none', 'small', 'default', 'big' ],
			'focus' => [ 'auto', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
			'overlay' => [ 'auto', 'none', 'dim' ],
		];
		private const array FRAME_FALLBACKS = [
			'screen' => 'off', 'vertical' => 'middle', 'background' => 'default', 'container' => 'default',
			'padding' => 'default', 'margin' => 'none', 'focus' => '5', 'overlay' => 'dim',
		];
		// Structural, inert elements only. The list grew for the presets that need
		// a real list or table row - a timeline step is an <li>, a table row is a
		// <tr> with <td> cells - and it stays free of anything that can load,
		// submit or script: no img, iframe, form, button, script, style, section.
		private const array TAGS = [ 'div', 'header', 'footer', 'article', 'aside', 'nav', 'h2', 'h3', 'h4', 'p', 'span', 'strong', 'ul', 'ol', 'li', 'tr', 'th', 'td' ];

		public static function choices(): array {
			return self::FRAME_CHOICES;
		}

		/**
		 *	The components a named area may be built from, and the properties
		 *	each of them binds.
		 *
		 *	Every label here is a fill key rather than a word: this list is
		 *	module code, the panel renders it, and the panel speaks whichever
		 *	language the rest of the workbench does. The client resolves them
		 *	with Nino.adminUi.text(), which passes literal text through
		 *	unchanged - so a section library manifest may still name its own
		 *	areas, layouts and styles in plain words. The default values below
		 *	are content, not labels: they are what gets written into the page
		 *
		 *	@return 	array
		 */
		public static function catalog(): array {
			return [
				'title' => self::component( '/_admin/templates/catalog/title', [ 'text' => self::property( '/_admin/templates/catalog/text', 'text', 'string', 'Section title' ) ], 'h3', 'nino-section-title', [ 'auto', 'quiet', 'loud' ] ),
				'subtitle' => self::component( '/_admin/templates/catalog/subtitle', [ 'text' => self::property( '/_admin/templates/catalog/text', 'text', 'string', 'A concise supporting line' ) ], 'p', 'nino-section-subtitle', [ 'auto', 'quiet', 'loud' ] ),
				'description' => self::component( '/_admin/templates/catalog/description', [ 'text' => self::property( '/_admin/templates/catalog/text', 'textarea', 'string', 'Explain what this area offers.' ) ], 'div', 'nino-section-text', [ 'auto', 'quiet', 'loud' ] ),
				'text' => self::component( '/_admin/templates/catalog/text', [ 'text' => self::property( '/_admin/templates/catalog/text', 'textarea', 'string', 'Add useful content here.' ) ], 'div', 'nino-section-text', [ 'auto', 'quiet', 'loud' ] ),
				'image' => self::component( '/_admin/templates/catalog/image', [
					'src' => self::property( '/_admin/templates/catalog/image', 'image', 'image', '', 1200, 800 ),
					'alt' => self::property( '/_admin/templates/catalog/alt', 'text', 'string', '' ),
				], 'div', '', [ 'auto', 'cover' ] ),
				'button' => self::component( '/_admin/templates/catalog/button', [
					'label' => self::property( '/_admin/templates/catalog/label', 'text', 'string', 'Learn more' ),
					'href' => self::property( '/_admin/templates/catalog/url', 'url', 'string', '#' ),
				], 'a', '', [ 'link', 'default', 'primary', 'outline' ], [ 'target' => [ 'same', 'new' ] ] ),
				'price' => self::component( '/_admin/templates/catalog/price', [
					'value' => self::property( '/_admin/templates/catalog/price', 'text', 'string', '99' ),
					'suffix' => self::property( '/_admin/templates/catalog/suffix', 'text', 'string', '€' ),
				], 'div', 'nino-section-text', [ 'auto', 'quiet', 'loud' ] ),
				'number' => self::component( '/_admin/templates/catalog/number', [
					'value' => self::property( '/_admin/templates/catalog/value', 'text', 'string', '12' ),
					'label' => self::property( '/_admin/templates/catalog/label', 'text', 'string', 'Projects' ),
				], 'div', 'nino-section-text', [ 'auto', 'quiet', 'loud' ] ),
				'template' => self::component( '/_admin/templates/catalog/template', [ 'path' => self::property( '/_admin/templates/catalog/template', 'template', 'template', '' ) ], 'div', '', [ 'auto' ] ),
			];
		}

		public static function normalizePreset( string $key, array $manifest, string $directory ): array {
			$name = trim( (string) ( $manifest['name'] ?? '' ) );
			$description = trim( (string) ( $manifest['description'] ?? '' ) );
			$tags = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $manifest['tags'] ?? [] ) ) ) ) );
			if( $name === '' || $description === '' || $tags === [] )
				throw new \InvalidArgumentException( 'name, description and searchable tags are required' );

			$areaDefinitions = is_array( $manifest['areas'] ?? null ) ? $manifest['areas'] : [];
			if( $areaDefinitions === [] )
				throw new \InvalidArgumentException( 'v3 manifests require at least one named area' );
			$areas = [];
			foreach( $areaDefinitions as $areaKey => $definition ) {
				$areaKey = (string) $areaKey;
				if( preg_match( self::ID_PATTERN, $areaKey ) !== 1 || is_array( $definition ) === false )
					throw new \InvalidArgumentException( 'area keys need lowercase slug names and array definitions' );
				$areas[$areaKey] = self::normalizeArea( $areaKey, $definition );
			}

			$layoutDefinitions = is_array( $manifest['layouts'] ?? null ) ? $manifest['layouts'] : [];
			if( $layoutDefinitions === [] )
				throw new \InvalidArgumentException( 'v3 manifests require at least one layout' );
			$layouts = [];
			$layoutSources = [];
			foreach( $layoutDefinitions as $layoutKey => $definition ) {
				$layoutKey = (string) $layoutKey;
				if( preg_match( self::ID_PATTERN, $layoutKey ) !== 1 || is_array( $definition ) === false )
					throw new \InvalidArgumentException( 'layout keys need lowercase slug names and array definitions' );
				$template = basename( (string) ( $definition['template'] ?? '' ) );
				$path = $directory. '/'. $template;
				if( $template === '' || preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\.tpl$/', $template ) !== 1 || is_file( $path ) === false )
					throw new \InvalidArgumentException( 'layout '. $layoutKey. ' needs an existing .tpl file' );
				$source = (string) file_get_contents( $path );
				if( str_contains( $source, '<?' ) )
					throw new \InvalidArgumentException( $template. ' must not contain PHP' );
				if( preg_match( '/\[\[(?:intro|content|outro|template|variant-class)\]\]/', $source ) === 1 )
					throw new \InvalidArgumentException( $template. ' contains a obsolete global compile token' );
				foreach( array_keys( $areas ) as $areaKey )
					if( substr_count( $source, '[[area:'. $areaKey. ']]' ) !== 1 )
						throw new \InvalidArgumentException( $template. ' must contain [[area:'. $areaKey. ']] exactly once' );
				if( preg_match_all( '/\[\[area:([a-z][a-z0-9-]*)\]\]/', $source, $matches ) !== count( $areas ) || array_diff( $matches[1], array_keys( $areas ) ) !== [] )
					throw new \InvalidArgumentException( $template. ' contains undeclared or duplicate area tokens' );
				// [[section:collection:<area>]] compiles to the collection slug that
				// Area is actually bound to. A typo would otherwise ship as literal
				// text into the page and quietly query a collection nobody has.
				preg_match_all( '/\[\[section:collection:([^\]]*)\]\]/', $source, $collectionTokens );
				foreach( $collectionTokens[1] as $collectionArea )
					if( ( $areas[$collectionArea]['source'] ?? '' ) !== 'elements' )
						throw new \InvalidArgumentException( $template. ' references the collection of \''. $collectionArea. '\', which is not an Elements area of this preset' );
				$layouts[$layoutKey] = [
					'label' => trim( (string) ( $definition['label'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', $layoutKey ) ),
					'frame' => self::normalizeFrameRecommendation( $definition['frame'] ?? [] ),
					'data' => self::dataAttributes( $definition['data'] ?? [], self::RESERVED_DATA ),
				];
				$layoutSources[$layoutKey] = $source;
			}

			$recommend = is_array( $manifest['recommend'] ?? null ) ? $manifest['recommend'] : [];
			$recommendedLayout = (string) ( $recommend['layout'] ?? array_key_first( $layouts ) );
			if( isset( $layouts[$recommendedLayout] ) === false )
				$recommendedLayout = (string) array_key_first( $layouts );

			return [
				'key' => $key,
				'name' => $name,
				'description' => $description,
				'category' => trim( (string) ( $manifest['category'] ?? '' ) ) ?: 'Other',
				'tags' => $tags,
				'version' => 3,
				'data' => self::dataAttributes( $manifest['data'] ?? [], self::RESERVED_DATA ),
				'recommend' => [
					'layout' => $recommendedLayout,
					'frame' => self::normalizeFrameRecommendation( $recommend['frame'] ?? [] ),
				],
				'layouts' => $layouts,
				'areas' => $areas,
				'componentCatalog' => self::catalog(),
				'_layouts' => $layoutSources,
			];
		}

		public static function defaults( array $preset, string $pageId, string $id ): array {
			$spec = [
				'version' => 3,
				'preset' => $preset['key'],
				'pageId' => $pageId,
				'id' => $id,
				'pageMotion' => 'off',
				'layout' => 'auto',
				'frame' => array_fill_keys( array_keys( self::FRAME_CHOICES ), 'auto' ),
				'areas' => [],
			];
			$spec['frame']['backgroundImage'] = self::generatedKey( $spec, 'background' );
			$spec['frame']['backgroundImageSource'] = 'new';
			foreach( $preset['areas'] as $areaKey => $area ) {
				$components = [];
				foreach( $area['recommend']['components'] as $node ) {
					$components[] = self::defaultBindings( $spec, $area, $node );
				}
				$spec['areas'][$areaKey] = [
					'style' => 'auto',
					'components' => $components,
					'source' => [
						'elementMode' => 'new',
						'elementType' => $pageId. '-'. $id. '-'. $areaKey,
						'shortcode' => $area['shortcode'],
					],
				];
			}
			return $spec;
		}

		public static function compose( array $input, array $preset ): array {
			$spec = self::spec( $input, $preset );
			$effective = self::effective( $spec, $preset );
			$fields = self::fieldDescriptors( $spec, $preset );
			$images = self::imageDescriptors( $spec, $preset, $effective );
			$source = self::render( $spec, $preset, $effective, $fields, $images );
			$inspection = SectionDocument::inspectSection( $source );
			if( $inspection['valid'] !== true )
				throw new \InvalidArgumentException( 'composed preset is not one complete section: '. ( $inspection['error'] ?? 'invalid source' ) );

			$collections = [];
			$imageFields = [];
			foreach( $preset['areas'] as $areaKey => $area ) {
				if( $area['source'] !== 'elements' )
					continue;
				$collections[] = [
					'area' => $areaKey,
					'elementMode' => $spec['areas'][$areaKey]['source']['elementMode'],
					'elementType' => $spec['areas'][$areaKey]['source']['elementType'],
					'typeTitle' => $area['typeTitle'],
					'model' => $area['model'],
				];
				foreach( $spec['areas'][$areaKey]['components'] as $component )
					foreach( $area['render'][$component['type']]['properties'] as $property => $definition )
						if( $definition['fieldType'] === 'image' && ( $component['bindingSources'][$property] ?? 'field' ) === 'field' )
							$imageFields[] = $component['bindings'][$property];
			}

			return [
				'spec' => $spec,
				'effective' => $effective,
				'source' => $source,
				'segment' => $inspection['segment'],
				'fields' => $fields,
				'images' => $images,
				'content' => [ 'source' => 'areas', 'collections' => $collections, 'imageFields' => array_values( array_unique( $imageFields ) ) ],
			];
		}

		public static function collectionDefinition( array $preset, string $areaKey ): ?array {
			$area = $preset['areas'][$areaKey] ?? null;
			return is_array( $area ) && $area['source'] === 'elements' ? $area : null;
		}

		public static function imageDefinition( array $preset, string $areaKey, string $componentId, string $property ): ?array {
			$area = $preset['areas'][$areaKey] ?? null;
			if( is_array( $area ) === false || $area['source'] !== 'single' || preg_match( self::ID_PATTERN, $componentId ) !== 1 || $property !== 'src' || in_array( 'image', $area['allowed'], true ) === false )
				return null;
			$definition = $area['render']['image']['properties']['src'] ?? self::catalog()['image']['properties']['src'];
			return [ 'label' => $area['label']. ' · Image', 'width' => $definition['width'], 'height' => $definition['height'] ];
		}

		private static function normalizeArea( string $key, array $definition ): array {
			$source = (string) ( $definition['source'] ?? 'single' );
			if( in_array( $source, [ 'single', 'elements' ], true ) === false )
				throw new \InvalidArgumentException( 'area '. $key. ' source must be single or elements' );
			$catalog = self::catalog();
			$allowed = array_values( array_unique( array_map( 'strval', (array) ( $definition['allowed'] ?? array_keys( $catalog ) ) ) ) );
			if( $allowed === [] || array_diff( $allowed, array_keys( $catalog ) ) !== [] )
				throw new \InvalidArgumentException( 'area '. $key. ' contains unsupported components' );
			if( $source === 'elements' && in_array( 'template', $allowed, true ) )
				throw new \InvalidArgumentException( 'template components are available only in single areas' );
			$styles = self::styles( $definition['styles'] ?? [] );
			$recommendedStyle = (string) ( $definition['recommend']['style'] ?? array_key_first( $styles ) );
			if( isset( $styles[$recommendedStyle] ) === false )
				$recommendedStyle = (string) array_key_first( $styles );
			$model = Library::normalizeModel( $definition['model'] ?? [] );
			if( $source === 'elements' && $model === [] )
				throw new \InvalidArgumentException( 'elements area '. $key. ' requires a model' );
			// Rich fields are sanitized for element CONTENT, not for an attribute -
			// sanitizeHtml() leaves '"' alone, so one of them substituted into a
			// data-* value would close the attribute and hand editor content a live
			// event handler. Collected here so every data map in this Area can
			// refuse to reference one.
			$htmlFields = array_keys( array_filter( $model, static fn( array $field ): bool => ( $field['html'] ?? false ) === true ) );
			$render = self::renderDefinitions( $definition['render'] ?? [], $allowed, $htmlFields );
			$components = [];
			$seen = [];
			foreach( (array) ( $definition['recommend']['components'] ?? [] ) as $node ) {
				$node = self::normalizeNode( $node, $allowed, $render, $source, $model );
				if( isset( $seen[$node['id']] ) )
					throw new \InvalidArgumentException( 'area '. $key. ' has duplicate component IDs' );
				$seen[$node['id']] = true;
				$components[] = $node;
			}

			return [
				'label' => trim( (string) ( $definition['label'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', $key ) ),
				'help' => trim( (string) ( $definition['help'] ?? '' ) ),
				'source' => $source,
				'allowed' => $allowed,
				'maxComponents' => max( 1, min( 20, (int) ( $definition['maxComponents'] ?? 12 ) ) ),
				'styles' => $styles,
				'recommend' => [ 'style' => $recommendedStyle, 'components' => $components ],
				'container' => self::element( $definition['container'] ?? [], 'div', $source === 'single' ? 'nino-grid-100' : '', $htmlFields ),
				'item' => self::element( $definition['item'] ?? [], 'article', 'nino-grid-m-33', $htmlFields ),
				'render' => $render,
				'model' => $model,
				'typeTitle' => trim( (string) ( $definition['typeTitle'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', $key ) ),
				'shortcode' => self::shortcode( $definition['shortcode'] ?? [] ),
			];
		}

		private static function normalizeNode( mixed $node, array $allowed, array $render, string $source, array $model, bool $strictModel = true, bool $requireSources = false ): array {
			if( is_array( $node ) === false )
				throw new \InvalidArgumentException( 'recommended components must be arrays' );
			$id = (string) ( $node['id'] ?? '' );
			$type = (string) ( $node['type'] ?? '' );
			if( preg_match( self::ID_PATTERN, $id ) !== 1 || in_array( $type, $allowed, true ) === false )
				throw new \InvalidArgumentException( 'components need a slug ID and an allowed type' );
			$component = $render[$type];
			$style = (string) ( $node['style'] ?? 'auto' );
			if( in_array( $style, $component['styles'], true ) === false )
				throw new \InvalidArgumentException( 'component '. $id. ' has an unsupported style' );
			$bindings = [];
			$bindingSources = [];
			$inputBindings = is_array( $node['bindings'] ?? null ) ? $node['bindings'] : [];
			$inputSources = is_array( $node['bindingSources'] ?? null ) ? $node['bindingSources'] : [];
			foreach( $component['properties'] as $property => $definition ) {
				if( isset( $inputBindings[$property] ) && is_scalar( $inputBindings[$property] ) === false )
					throw new \InvalidArgumentException( 'component '. $id. ' has an invalid binding value' );
				if( isset( $inputSources[$property] ) && is_string( $inputSources[$property] ) === false )
					throw new \InvalidArgumentException( 'component '. $id. ' has an invalid binding source' );
				if( $requireSources === true && (string) ( $inputSources[$property] ?? '' ) === '' )
					throw new \InvalidArgumentException( 'component '. $id. ' must declare every binding source' );
				$value = (string) ( $inputBindings[$property] ?? self::suffix( $id, $property, $type ) );
				$bindingSource = self::bindingSource( (string) ( $inputSources[$property] ?? '' ), $source, $definition, $value );
				self::validateBinding( $id, $value, $bindingSource, $source, $definition, $model, $strictModel );
				$bindings[$property] = $value;
				$bindingSources[$property] = $bindingSource;
			}
			$target = (string) ( $node['settings']['target'] ?? 'same' );
			if( in_array( $target, [ 'same', 'new' ], true ) === false )
				throw new \InvalidArgumentException( 'component '. $id. ' has an unsupported target' );
			return [ 'id' => $id, 'type' => $type, 'style' => $style, 'settings' => [ 'target' => $target ], 'bindings' => $bindings, 'bindingSources' => $bindingSources ];
		}

		private static function spec( array $input, array $preset ): array {
			$pageId = self::slug( (string) ( $input['pageId'] ?? '' ), 'page ID' );
			$id = self::slug( (string) ( $input['id'] ?? '' ), 'section ID' );
			$defaults = self::defaults( $preset, $pageId, $id );
			$spec = $defaults;
			$spec['pageMotion'] = in_array( $input['pageMotion'] ?? '', [ 'on', 'off' ], true ) ? $input['pageMotion'] : 'off';
			$layout = (string) ( $input['layout'] ?? 'auto' );
			if( $layout !== 'auto' && isset( $preset['layouts'][$layout] ) === false )
				throw new \InvalidArgumentException( 'invalid layout' );
			$spec['layout'] = $layout;
			$inputFrame = is_array( $input['frame'] ?? null ) ? $input['frame'] : [];
			foreach( self::FRAME_CHOICES as $key => $choices )
				$spec['frame'][$key] = self::choice( (string) ( $inputFrame[$key] ?? 'auto' ), $choices, 'frame.'. $key );
			$spec['frame'] = array_merge( $spec['frame'], self::backgroundBinding( $spec, $inputFrame ) );

			$inputAreas = is_array( $input['areas'] ?? null ) ? $input['areas'] : [];
			foreach( $preset['areas'] as $areaKey => $area ) {
				$inputArea = is_array( $inputAreas[$areaKey] ?? null ) ? $inputAreas[$areaKey] : [];
				$style = (string) ( $inputArea['style'] ?? 'auto' );
				if( $style !== 'auto' && isset( $area['styles'][$style] ) === false )
					throw new \InvalidArgumentException( 'invalid style for area '. $areaKey );
				$sourceInput = is_array( $inputArea['source'] ?? null ) ? $inputArea['source'] : [];
				$elementMode = (string) ( $sourceInput['elementMode'] ?? 'new' );
				$elementType = (string) ( $sourceInput['elementType'] ?? $defaults['areas'][$areaKey]['source']['elementType'] );
				if( $area['source'] === 'elements' && ( ! in_array( $elementMode, [ 'new', 'existing' ], true ) || preg_match( '/^[a-z][a-z0-9_-]*$/', $elementType ) !== 1 ) )
					throw new \InvalidArgumentException( 'invalid collection source for area '. $areaKey );
				$components = [];
				$seen = [];
				$nodes = is_array( $inputArea['components'] ?? null ) ? $inputArea['components'] : $defaults['areas'][$areaKey]['components'];
				if( count( $nodes ) > $area['maxComponents'] )
					throw new \InvalidArgumentException( 'too many components in area '. $areaKey );
				foreach( $nodes as $node ) {
					$node = self::normalizeNode( $node, $area['allowed'], $area['render'], $area['source'], $area['model'], $area['source'] === 'elements' && $elementMode === 'new', true );
					if( isset( $seen[$node['id']] ) )
						throw new \InvalidArgumentException( 'duplicate component ID in area '. $areaKey );
					$seen[$node['id']] = true;
					if( $area['source'] === 'single' )
						$node = self::singleBindings( $spec, $node, $area );
					$components[] = $node;
				}
				$spec['areas'][$areaKey] = [
					'style' => $style,
					'components' => $components,
					'source' => [
						'elementMode' => $elementMode,
						'elementType' => $elementType,
						'shortcode' => self::shortcode( $sourceInput['shortcode'] ?? $area['shortcode'] ),
					],
				];
			}
			return $spec;
		}

		private static function effective( array $spec, array $preset ): array {
			$layout = $spec['layout'] === 'auto' ? $preset['recommend']['layout'] : $spec['layout'];
			$layoutFrame = $preset['layouts'][$layout]['frame'];
			$frame = [];
			foreach( self::FRAME_CHOICES as $key => $choices ) {
				$recommended = ( $layoutFrame[$key] ?? 'auto' ) !== 'auto'
					? $layoutFrame[$key]
					: ( $preset['recommend']['frame'][$key] ?? 'auto' );
				$frame[$key] = self::resolve( $spec['frame'][$key], (string) $recommended, $choices, self::FRAME_FALLBACKS[$key] );
			}
			$areas = [];
			foreach( $preset['areas'] as $areaKey => $area ) {
				$selected = $spec['areas'][$areaKey]['style'];
				$areas[$areaKey] = [ 'style' => $selected === 'auto' ? $area['recommend']['style'] : $selected ];
			}
			return [ 'layout' => $layout, 'frame' => $frame, 'areas' => $areas ];
		}

		private static function fieldDescriptors( array &$spec, array $preset ): array {
			$result = [];
			foreach( $preset['areas'] as $areaKey => $area ) {
				if( $area['source'] !== 'single' )
					continue;
				foreach( $spec['areas'][$areaKey]['components'] as &$node ) {
					foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
						if( in_array( $definition['kind'], [ 'image', 'template' ], true ) )
							continue;
						if( ( $node['bindingSources'][$property] ?? 'new' ) === 'fixed' )
							continue;
						$suffix = self::suffix( $node['id'], $property, $node['type'] );
						$generated = self::generatedKey( $spec, $suffix );
						$key = (string) ( $node['bindings'][$property] ?? $generated );
						if( self::validKey( $key, false ) === false )
							throw new \InvalidArgumentException( 'invalid text binding in area '. $areaKey );
						$node['bindings'][$property] = $key;
						$result[] = [
							'slot' => $areaKey. '.'. $node['id']. '.'. $property, 'area' => $areaKey, 'component' => $node['id'], 'property' => $property,
							'label' => $area['label']. ' · '. $definition['label'], 'control' => $definition['control'], 'default' => $definition['default'],
							'key' => $key, 'generatedKey' => $generated, 'mode' => ( $node['bindingSources'][$property] ?? '' ) === 'new' || $key === $generated ? 'new' : 'existing',
						];
					}
				}
				unset( $node );
			}
			return $result;
		}

		private static function imageDescriptors( array &$spec, array $preset, array $effective ): array {
			$result = [];
			if( in_array( $effective['frame']['background'], [ 'cover', 'parallax' ], true ) && ( $spec['frame']['backgroundImageSource'] ?? 'new' ) !== 'fixed' ) {
				$generated = self::generatedKey( $spec, 'background' );
				$key = (string) ( $spec['frame']['backgroundImage'] ?? $generated );
				if( self::validKey( $key, true ) === false )
					throw new \InvalidArgumentException( 'invalid background image binding' );
				$spec['frame']['backgroundImage'] = $key;
				$result[] = [ 'slot' => 'background', 'area' => '', 'component' => '', 'property' => 'src', 'label' => 'Background image', 'width' => 1920, 'height' => 1080, 'key' => $key, 'generatedKey' => $generated, 'mode' => $key === $generated ? 'new' : 'existing' ];
			}
			foreach( $preset['areas'] as $areaKey => $area ) {
				if( $area['source'] !== 'single' )
					continue;
				foreach( $spec['areas'][$areaKey]['components'] as &$node ) {
					foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
						if( $definition['kind'] !== 'image' )
							continue;
						$suffix = self::suffix( $node['id'], $property, $node['type'] );
						$generated = self::generatedKey( $spec, $suffix );
						$key = (string) ( $node['bindings'][$property] ?? $generated );
						if( self::validKey( $key, true ) === false )
							throw new \InvalidArgumentException( 'invalid image binding in area '. $areaKey );
						$node['bindings'][$property] = $key;
						$result[] = [
							'slot' => $areaKey. '.'. $node['id']. '.'. $property, 'area' => $areaKey, 'component' => $node['id'], 'property' => $property,
							'label' => $area['label']. ' · '. $definition['label'], 'width' => $definition['width'], 'height' => $definition['height'],
							'key' => $key, 'generatedKey' => $generated, 'mode' => $key === $generated ? 'new' : 'existing',
						];
					}
				}
				unset( $node );
			}
			return $result;
		}

		private static function render( array $spec, array $preset, array $effective, array $fields, array $images ): string {
			$fieldMap = array_column( $fields, 'key', 'slot' );
			$imageMap = array_column( $images, 'key', 'slot' );
			$replace = [ '[[section:id]]' => self::escape( $spec['id'] ) ];
			$titleId = '';
			$body = $preset['_layouts'][$effective['layout']];
			// A static block that loops beside an Elements Area has to name the same
			// collection it does - and that name is only known here, because a new
			// Area mints '<page>-<section>-<area>' and Edit Section can later rebind
			// it to something else entirely. Hard-coding a slug in the .tpl would be
			// wrong on the very first insert.
			foreach( $preset['areas'] as $areaKey => $area )
				if( $area['source'] === 'elements' )
					$replace['[[section:collection:'. $areaKey. ']]'] = self::escape( $spec['areas'][$areaKey]['source']['elementType'] );
			foreach( $preset['areas'] as $areaKey => $area ) {
				$rendered = self::renderArea( $spec, $areaKey, $area, $effective['areas'][$areaKey], $fieldMap, $imageMap, $titleId );
				// An Area nobody filled leaves its whole line rather than an empty
				// one: a deliberately empty outro is normal, and the section source
				// is read and edited by hand afterwards
				if( $rendered === '' )
					$body = preg_replace( '/^[\t ]*\[\[area:'. preg_quote( $areaKey, '/' ). '\]\][\t ]*\R?/m', '', $body ) ?? $body;
				$replace['[[area:'. $areaKey. ']]'] = $rendered;
			}
			$body = strtr( $body, $replace );
			if( preg_match( '/\[\[(?:area:[^\]]+|section:id|section:collection:[^\]]+)\]\]/', $body ) === 1 )
				throw new \InvalidArgumentException( 'layout contains an unresolved compile token' );

			$rowClasses = [ 'nino-grid-row' ];
			if( $effective['frame']['container'] !== 'default' )
				$rowClasses[] = 'nino-grid-row--'. $effective['frame']['container'];
			if( $spec['pageMotion'] === 'on' )
				$rowClasses[] = 'nino-vpa';
			$body = '<div class="'. implode( ' ', $rowClasses ). "\">\n". self::indent( $body, 1 ). '</div>';
			$classes = self::sectionClasses( $effective['frame'] );
			$attributes = ' id="'. self::escape( $spec['id'] ). '" class="'. self::escape( implode( ' ', $classes ) ). '"';
			if( $effective['frame']['screen'] !== 'off' )
				$attributes .= ' data-cover-height="'. self::escape( $effective['frame']['screen'] ). '"';
			if( $titleId !== '' )
				$attributes .= ' aria-labelledby="'. self::escape( $titleId ). '"';
			$attributes .= self::attributes( array_replace( $preset['data'], $preset['layouts'][$effective['layout']]['data'] ), $spec['id'] );
			$meta = '<!-- nino:section '. json_encode( $spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ). ' -->';
			$background = '';
			if( isset( $imageMap['background'] ) )
				$background = '[image '. $imageMap['background']. ' alt=""]';
			elseif( ( $spec['frame']['backgroundImageSource'] ?? '' ) === 'fixed' && in_array( $effective['frame']['background'], [ 'cover', 'parallax' ], true ) && $spec['frame']['backgroundImage'] !== '' )
				$background = '<img src="'. self::escape( $spec['frame']['backgroundImage'] ). '" alt="">';
			$inner = $meta. "\n";
			if( $background !== '' )
				$inner .= $background. "\n";
			$needsLayer = $background !== '' || $effective['frame']['screen'] !== 'off';
			$inner .= $needsLayer
				? '<div class="'. ( $effective['frame']['background'] === 'cover' && $effective['frame']['screen'] === 'off' ? 'nino-img-background-content' : 'nino-cover-content' ). "\">\n". self::indent( $body, 1 ). '</div>'
				: $body;
			return '<section'. $attributes. ">\n". self::indent( $inner, 1 ). '</section>';
		}

		private static function renderArea( array $spec, string $areaKey, array $area, array $effective, array $fields, array $images, string &$titleId ): string {
			$styleClass = (string) ( $area['styles'][$effective['style']]['class'] ?? '' );
			$nodes = '';
			foreach( $spec['areas'][$areaKey]['components'] as $node ) {
				$slot = $areaKey. '.'. $node['id']. '.';
				$values = [];
				foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
					$bindingSource = (string) ( $node['bindingSources'][$property] ?? ( $area['source'] === 'elements' ? 'field' : 'new' ) );
					if( $bindingSource === 'fixed' )
						$values[$property] = self::escapeLiteral( (string) $node['bindings'][$property] );
					elseif( $area['source'] === 'elements' )
						$values[$property] = '[['. $node['bindings'][$property]. ']]';
					elseif( $definition['kind'] === 'image' )
						$values[$property] = $images[$slot. $property] ?? '';
					elseif( $definition['kind'] === 'template' )
						$values[$property] = $node['bindings'][$property] ?? '';
					else
						$values[$property] = isset( $fields[$slot. $property] ) ? '[['. $fields[$slot. $property]. ']]' : '';
				}
				$componentId = $area['source'] === 'single' && $node['type'] === 'title' && $titleId === '' ? $spec['id']. '-title' : '';
				if( $componentId !== '' )
					$titleId = $componentId;
				$nodes .= self::renderComponent( $node, $area['render'][$node['type']], $values, $area['source'], $componentId, $spec['id'] );
			}
			if( $nodes === '' )
				return '';
			if( $area['source'] === 'single' ) {
				$element = $area['container'];
				$class = trim( $element['class']. ' '. $styleClass );
				return '<'. $element['tag']. ( $class === '' ? '' : ' class="'. self::escape( $class ). '"' ). self::attributes( $element['data'], $spec['id'] ). '>'. $nodes. '</'. $element['tag']. '>';
			}
			$source = $spec['areas'][$areaKey]['source'];
			$shortcode = $source['shortcode'];
			$element = $area['item'];
			$class = trim( $element['class']. ' '. $styleClass );
			$item = '<'. $element['tag']. ( $class === '' ? '' : ' class="'. self::escape( $class ). '"' ). self::attributes( $element['data'], $spec['id'] ). '>'. $nodes. '</'. $element['tag']. '>';
			return '[elements /'. $source['elementType']
				. ' locale="'. self::escape( $shortcode['locale'] ). '" callback="'. self::escape( $shortcode['callback'] ). '"'
				. ' limit="'. $shortcode['limit']. '" query="'. self::escape( $shortcode['query'] ). '"]'. $item. '[/elements]';
		}

		private static function renderComponent( array $node, array $definition, array $values, string $source, string $id, string $sectionId ): string {
			$style = $node['style'] === 'auto' ? 'auto' : $node['style'];
			$class = trim( $definition['class']. ' '. ( $definition['styleClasses'][$style] ?? '' ) );
			$attribute = $class === '' ? '' : ' class="'. self::escape( $class ). '"';
			if( $id !== '' )
				$attribute .= ' id="'. self::escape( $id ). '"';
			$attribute .= self::attributes( $definition['data'], $sectionId );
			return match( $node['type'] ) {
				'image' => $source === 'elements'
					? '<img src="[[/nino/public]]/images/'. $values['src']. '" alt="'. $values['alt']. '" loading="lazy"'. $attribute. '>'
					: '<div'. $attribute. '>[image '. $values['src']. ' alt="'. $values['alt']. '"]</div>',
				'button' => '<a href="'. $values['href']. '"'. $attribute. ( $node['settings']['target'] === 'new' ? ' target="_blank" rel="noopener noreferrer"' : '' ). '>'. $values['label']. '</a>',
				'price' => '<div'. $attribute. '><strong>'. $values['value']. '</strong><span>'. $values['suffix']. '</span></div>',
				'number' => '<div'. $attribute. '><strong>'. $values['value']. '</strong><span>'. $values['label']. '</span></div>',
				'template' => $values['path'] === '' ? '' : '[template '. $values['path']. ']',
				default => '<'. $definition['tag']. $attribute. '>'. $values['text']. '</'. $definition['tag']. '>',
			};
		}

		private static function renderDefinitions( mixed $overrides, array $allowed, array $htmlFields = [] ): array {
			$catalog = self::catalog();
			$overrides = is_array( $overrides ) ? $overrides : [];
			if( array_diff( array_keys( $overrides ), $allowed ) !== [] )
				throw new \InvalidArgumentException( 'render overrides must target an allowed component' );
			$result = [];
			foreach( $allowed as $type ) {
				$definition = $catalog[$type];
				$override = is_array( $overrides[$type] ?? null ) ? $overrides[$type] : [];
				if( isset( $override['tag'] ) )
					$definition['tag'] = self::tag( (string) $override['tag'] );
				if( isset( $override['class'] ) )
					$definition['class'] = self::classes( (string) $override['class'] );
				if( isset( $override['data'] ) )
					$definition['data'] = self::dataAttributes( $override['data'], [], $htmlFields );
				if( isset( $override['styles'] ) ) {
					$styles = [];
					foreach( (array) $override['styles'] as $style => $class ) {
						if( preg_match( self::ID_PATTERN, (string) $style ) !== 1 && (string) $style !== 'auto' )
							throw new \InvalidArgumentException( 'component styles need slug keys' );
						$styles[(string) $style] = self::classes( (string) $class );
					}
					if( isset( $styles['auto'] ) === false )
						$styles = [ 'auto' => '' ] + $styles;
					$definition['styles'] = array_keys( $styles );
					$definition['styleClasses'] = $styles;
				}
				// A step down or up the type scale is a modifier of the class the
				// component actually carries - nino-section-title--loud, and
				// nino-article-title--loud once a manifest restyles the same
				// component as a card title. A component without a class of its own
				// has nothing to modify and keeps its plain look.
				$base = strtok( $definition['class'], ' ' );
				foreach( [ 'quiet', 'loud' ] as $modifier )
					if( isset( $definition['styleClasses'][$modifier] ) )
						$definition['styleClasses'][$modifier] = $base === false || $base === '' ? '' : $base. '--'. $modifier;
				if( $type === 'image' && isset( $override['width'] ) )
					$definition['properties']['src']['width'] = max( 1, min( 8000, (int) $override['width'] ) );
				if( $type === 'image' && isset( $override['height'] ) )
					$definition['properties']['src']['height'] = max( 1, min( 8000, (int) $override['height'] ) );
				$result[$type] = $definition;
			}
			return $result;
		}

		private static function styles( mixed $styles ): array {
			if( is_array( $styles ) === false || $styles === [] )
				// A fill key like every other label this file sends - see catalog()
				return [ 'default' => [ 'label' => '/_admin/templates/label/style-default', 'class' => '' ] ];
			$result = [];
			foreach( $styles as $key => $definition ) {
				if( preg_match( self::ID_PATTERN, (string) $key ) !== 1 || is_array( $definition ) === false )
					throw new \InvalidArgumentException( 'area styles need slug keys and array definitions' );
				$result[(string) $key] = [
					'label' => trim( (string) ( $definition['label'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', (string) $key ) ),
					'class' => self::classes( (string) ( $definition['class'] ?? '' ) ),
				];
			}
			return $result;
		}

		private static function component( string $label, array $properties, string $tag, string $class, array $styles, array $settings = [] ): array {
			$styleClasses = [ 'auto' => '' ];
			foreach( $styles as $style )
				$styleClasses[$style] = match( $style ) {
					// quiet and loud are resolved in renderDefinitions(), where the
					// class they modify is final
					'cover' => 'nino-img-cover',
					'link' => '', 'default' => 'nino-btn', 'primary' => 'nino-btn nino-btn--primary', 'outline' => 'nino-btn nino-btn--outline', default => '',
				};
			return [ 'label' => $label, 'properties' => $properties, 'tag' => $tag, 'class' => $class, 'data' => [], 'styles' => array_values( array_unique( [ 'auto', ...$styles ] ) ), 'styleClasses' => $styleClasses, 'settings' => $settings ];
		}

		private static function property( string $label, string $control, string $fieldType, string $default, int $width = 0, int $height = 0 ): array {
			return [ 'label' => $label, 'kind' => $control, 'control' => $control === 'image' ? 'image' : $control, 'fieldType' => $fieldType, 'default' => $default, 'width' => $width, 'height' => $height ];
		}

		private static function element( mixed $definition, string $tag, string $class, array $htmlFields = [] ): array {
			$definition = is_array( $definition ) ? $definition : [];
			return [
				'tag' => self::tag( (string) ( $definition['tag'] ?? $tag ) ),
				'class' => self::classes( (string) ( $definition['class'] ?? $class ) ),
				'data' => self::dataAttributes( $definition['data'] ?? [], [], $htmlFields ),
			];
		}

		private static function normalizeFrameRecommendation( mixed $frame ): array {
			$frame = is_array( $frame ) ? $frame : [];
			$result = [];
			foreach( self::FRAME_CHOICES as $key => $choices ) {
				$value = (string) ( $frame[$key] ?? 'auto' );
				if( in_array( $value, $choices, true ) === false )
					throw new \InvalidArgumentException( 'unsupported frame recommendation: '. $key );
				$result[$key] = $value;
			}
			return $result;
		}

		private static function defaultBindings( array $spec, array $area, array $node ): array {
			if( $area['source'] === 'elements' )
				return $node;
			$bindings = [];
			$sources = [];
			foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
				$value = (string) ( $node['bindings'][$property] ?? '' );
				$source = (string) ( $node['bindingSources'][$property] ?? 'new' );
				if( $definition['kind'] === 'template' || $source === 'fixed' || ( in_array( $source, [ 'textfill', 'image' ], true ) && str_starts_with( $value, '/' ) ) ) {
					$bindings[$property] = $value;
					$sources[$property] = $definition['kind'] === 'template' ? 'template' : $source;
					continue;
				}
				$bindings[$property] = self::generatedKey( $spec, self::suffix( $node['id'], $property, $node['type'] ) );
				$sources[$property] = 'new';
			}
			$node['bindings'] = $bindings;
			$node['bindingSources'] = $sources;
			return $node;
		}

		private static function singleBindings( array $spec, array $node, array $area ): array {
			foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
				$value = (string) ( $node['bindings'][$property] ?? '' );
				$source = (string) ( $node['bindingSources'][$property] ?? '' );
				if( $definition['kind'] === 'template' ) {
					if( $value !== '' && preg_match( self::TEMPLATE_PATTERN, $value ) !== 1 )
						throw new \InvalidArgumentException( 'invalid reusable template path' );
					$node['bindings'][$property] = $value;
					$node['bindingSources'][$property] = 'template';
					continue;
				}
				$generated = self::generatedKey( $spec, self::suffix( $node['id'], $property, $node['type'] ) );
				if( $source === 'new' || $value === $generated || ( str_starts_with( $value, '/' ) === false && $source !== 'fixed' ) ) {
					$value = $generated;
					$source = 'new';
				}
				if( $source === 'fixed' && self::validLiteral( $value, $definition ) === false )
					throw new \InvalidArgumentException( 'invalid fixed value for component '. $node['id'] );
				if( $source !== 'fixed' && self::validKey( $value, $definition['kind'] === 'image' ) === false )
					throw new \InvalidArgumentException( 'invalid binding for component '. $node['id'] );
				$node['bindings'][$property] = $value;
				$node['bindingSources'][$property] = $source;
			}
			return $node;
		}

		/**
		 * Resolve the section background image binding from browser data.
		 * A generated slot, an existing slot and a fixed URL are three different
		 * things a key alone cannot tell apart, so the choice is stored next to it.
		 * The source is explicit whenever a background value is supplied.
		 */
		private static function backgroundBinding( array $spec, array $inputFrame ): array {
			$generated = self::generatedKey( $spec, 'background' );
			$value = trim( (string) ( $inputFrame['backgroundImage'] ?? '' ) );
			$source = (string) ( $inputFrame['backgroundImageSource'] ?? '' );
			if( $source === '' && $value === '' )
				$source = 'new';
			elseif( in_array( $source, [ 'new', 'image', 'fixed' ], true ) === false )
				throw new \InvalidArgumentException( 'invalid background image source' );
			if( $source === 'new' )
				$value = $generated;
			if( $source === 'image' && self::validKey( $value, true ) === false )
				throw new \InvalidArgumentException( 'invalid background image binding' );
			if( $source === 'fixed' && self::validImageLiteral( $value ) === false )
				throw new \InvalidArgumentException( 'invalid fixed background image' );
			return [ 'backgroundImage' => $value, 'backgroundImageSource' => $source ];
		}

		/**
		 * Whether a fixed background value is a usable image source. Project
		 * images are addressed through the public prefix, so the two fills that
		 * resolve it stay allowed; every other bracket, quote, space and control
		 * character is refused rather than escaped into something meaningless.
		 */
		private static function validImageLiteral( string $value ): bool {
			if( $value === '' || strlen( $value ) > 500 || preg_match( '/[\x00-\x20\x7F"\'<>]/', $value ) === 1 )
				return false;
			$path = $value;
			foreach( [ '[[/nino/public]]', '[[/nino/dir]]' ] as $fill )
				if( str_starts_with( $path, $fill ) ) {
					$path = substr( $path, strlen( $fill ) );
					break;
				}
			if( str_contains( $path, '[' ) || str_contains( $path, ']' ) || str_contains( $path, '..' ) )
				return false;
			if( preg_match( '#^[A-Za-z][A-Za-z0-9+.-]*:#', $path, $match ) === 1 )
				return in_array( strtolower( rtrim( $match[0], ':' ) ), [ 'http', 'https' ], true );
			return str_starts_with( $path, '//' ) === false;
		}

		private static function bindingSource( string $requested, string $areaSource, array $definition, string $value ): string {
			if( $definition['kind'] === 'template' ) {
				if( $requested !== '' && $requested !== 'template' )
					throw new \InvalidArgumentException( 'template bindings require the template source' );
				return 'template';
			}

			if( $areaSource === 'elements' ) {
				$source = $requested !== '' ? $requested : ( str_starts_with( $value, '/' ) ? 'textfill' : 'field' );
				$allowed = $definition['fieldType'] === 'image' ? [ 'field' ] : [ 'field', 'textfill', 'fixed' ];
			} else {
				$source = $requested !== '' ? $requested : ( str_starts_with( $value, '/' ) ? ( $definition['kind'] === 'image' ? 'image' : 'textfill' ) : 'new' );
				$allowed = $definition['kind'] === 'image' ? [ 'new', 'image' ] : [ 'new', 'textfill', 'fixed' ];
			}

			if( in_array( $source, $allowed, true ) === false )
				throw new \InvalidArgumentException( 'unsupported binding source' );
			return $source;
		}

		private static function validateBinding( string $id, string $value, string $bindingSource, string $areaSource, array $definition, array $model, bool $strictModel ): void {
			if( $bindingSource === 'template' ) {
				if( $value !== '' && preg_match( self::TEMPLATE_PATTERN, $value ) !== 1 )
					throw new \InvalidArgumentException( 'invalid reusable template path' );
				return;
			}
			if( $bindingSource === 'fixed' ) {
				if( self::validLiteral( $value, $definition ) === false )
					throw new \InvalidArgumentException( 'component '. $id. ' has an invalid fixed value' );
				return;
			}
			if( $bindingSource === 'textfill' ) {
				if( self::validKey( $value, false ) === false )
					throw new \InvalidArgumentException( 'component '. $id. ' has an invalid textfill binding' );
				return;
			}
			if( $areaSource !== 'elements' ) {
				if( $bindingSource === 'image' && self::validKey( $value, true ) === false )
					throw new \InvalidArgumentException( 'component '. $id. ' has an invalid image binding' );
				return;
			}
			if( preg_match( self::FIELD_PATTERN, $value ) !== 1 || ( $strictModel && isset( $model[$value] ) === false ) )
				throw new \InvalidArgumentException( 'component '. $id. ' maps to an unknown model field' );
			if( $strictModel && ( $definition['fieldType'] === 'image' ) !== ( $model[$value]['type'] === 'image' ) )
				throw new \InvalidArgumentException( 'component '. $id. ' has an incompatible model mapping' );
		}

		private static function validLiteral( string $value, array $definition ): bool {
			if( strlen( $value ) > self::MAX_LITERAL_LENGTH || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) === 1 )
				return false;
			if( $definition['control'] !== 'url' )
				return true;
			$value = trim( $value );
			if( $value === '' )
				return true;
			if( preg_match( '/[\x00-\x20\x7F]/', $value ) === 1 )
				return false;
			if( preg_match( '#^[A-Za-z][A-Za-z0-9+.-]*:#', $value, $match ) !== 1 )
				return str_starts_with( $value, '//' ) === false;
			return in_array( strtolower( rtrim( $match[0], ':' ) ), [ 'http', 'https', 'mailto', 'tel' ], true );
		}

		private static function suffix( string $id, string $property, string $type ): string {
			if( $type === 'image' && $property === 'src' )
				return $id;
			if( in_array( $type, [ 'title', 'subtitle', 'description', 'text' ], true ) && $property === 'text' )
				return $id;
			return $id. '-'. $property;
		}

		private static function generatedKey( array $spec, string $suffix ): string {
			return '/page-'. $spec['pageId']. '/'. $spec['id']. '/'. $suffix;
		}

		private static function sectionClasses( array $frame ): array {
			$classes = [ 'nino-section' ];
			if( in_array( $frame['background'], [ 'alt', 'primary', 'dark', 'black' ], true ) )
				$classes[] = 'nino-section--'. $frame['background'];
			if( in_array( $frame['background'], [ 'cover', 'parallax' ], true ) ) {
				$classes[] = 'nino-section--fullwidth';
				$classes[] = 'nino-section--black';
				// The scrim belongs to whichever image layer this background uses:
				// each of the three ships its own --dim in the design system
				$layer = $frame['background'] === 'parallax' ? 'nino-parallex' : ( $frame['screen'] === 'off' ? 'nino-img-background' : 'nino-cover' );
				$classes[] = $layer;
				if( $frame['overlay'] === 'dim' )
					$classes[] = $layer. '--dim';
				$classes[] = 'nino-img-focus--'. $frame['focus'];
			}
			if( $frame['screen'] !== 'off' )
				$classes[] = 'nino-cover';
			if( $frame['vertical'] !== 'middle' )
				$classes[] = 'nino-cover-'. $frame['vertical'];
			$spacing = [ 'none' => '0', 'small' => '2', 'big' => '6' ];
			if( isset( $spacing[$frame['padding']] ) ) {
				$classes[] = 'nino-pt-'. $spacing[$frame['padding']];
				$classes[] = 'nino-pb-'. $spacing[$frame['padding']];
			}
			if( isset( $spacing[$frame['margin']] ) ) {
				$classes[] = 'nino-mt-'. $spacing[$frame['margin']];
				$classes[] = 'nino-mb-'. $spacing[$frame['margin']];
			}
			return array_values( array_unique( $classes ) );
		}

		private static function shortcode( mixed $shortcode ): array {
			$shortcode = is_array( $shortcode ) ? $shortcode : [];
			return [
				'locale' => substr( (string) ( $shortcode['locale'] ?? '' ), 0, 80 ),
				'callback' => substr( (string) ( $shortcode['callback'] ?? '' ), 0, 160 ),
				'limit' => max( -1, min( 1000, (int) ( $shortcode['limit'] ?? 6 ) ) ),
				'query' => substr( (string) ( $shortcode['query'] ?? '' ), 0, 500 ),
			];
		}

		private static function choice( string $value, array $choices, string $label ): string {
			if( in_array( $value, $choices, true ) === false )
				throw new \InvalidArgumentException( 'invalid '. $label );
			return $value;
		}

		private static function resolve( string $value, string $recommended, array $choices, string $fallback ): string {
			if( $value !== 'auto' )
				return in_array( $value, $choices, true ) ? $value : $fallback;
			if( $recommended !== 'auto' && in_array( $recommended, $choices, true ) )
				return $recommended;
			return $fallback;
		}

		private static function slug( string $value, string $label ): string {
			if( preg_match( self::ID_PATTERN, $value ) !== 1 )
				throw new \InvalidArgumentException( $label. ' must start with a letter and use lowercase letters, numbers and hyphens' );
			return $value;
		}

		private static function validKey( string $key, bool $image ): bool {
			$pattern = $image ? self::IMAGE_KEY_PATTERN : self::TEXT_KEY_PATTERN;
			return strlen( $key ) <= 240 && preg_match( $pattern, $key ) === 1 && str_contains( $key, '..' ) === false && str_contains( $key, '//' ) === false;
		}

		private static function tag( string $tag ): string {
			if( in_array( $tag, self::TAGS, true ) === false )
				throw new \InvalidArgumentException( 'unsupported HTML tag in area manifest' );
			return $tag;
		}

		private static function classes( string $classes ): string {
			$classes = trim( $classes );
			if( $classes !== '' && preg_match( '/^[A-Za-z0-9_ -]+$/', $classes ) !== 1 )
				throw new \InvalidArgumentException( 'unsafe CSS classes in area manifest' );
			return $classes;
		}

		/**
		 * Normalize the optional data-* attributes a manifest declares for one
		 * generated element. Frontend behavior such as nino-autoheight, nino-slider or
		 * nino-vpa is configured through these attributes, so a preset that owns the
		 * matching class must be able to own its parameters too.
		 *
		 * Only the preset files decide this. Nothing here is read from a request:
		 * the composer UI has no data-* control, and arbitrary attributes remain an
		 * HTML+ decision. Names are the attribute without its data- prefix (the
		 * written prefix is accepted and dropped) and values are bounded literals.
		 */
		private static function dataAttributes( mixed $data, array $reserved = [], array $htmlFields = [] ): array {
			if( is_array( $data ) === false || $data === [] )
				return [];
			$result = [];
			foreach( $data as $name => $value ) {
				$name = strtolower( trim( (string) $name ) );
				if( str_starts_with( $name, 'data-' ) )
					$name = substr( $name, 5 );
				if( preg_match( self::DATA_NAME_PATTERN, $name ) !== 1 )
					throw new \InvalidArgumentException( 'data attributes need lowercase slug names in area manifest' );
				if( in_array( 'data-'. $name, $reserved, true ) )
					throw new \InvalidArgumentException( 'data-'. $name. ' is written by the section frame itself' );
				if( is_scalar( $value ) === false )
					throw new \InvalidArgumentException( 'data-'. $name. ' needs a scalar value in area manifest' );
				$value = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
				if( strlen( $value ) > self::MAX_DATA_LENGTH || preg_match( '/[\x00-\x1F\x7F]/', $value ) === 1 )
					throw new \InvalidArgumentException( 'data-'. $name. ' has an unsupported value in area manifest' );
				// A '[[field]]' here is resolved per record by the [elements] pass at
				// request time, which escapes an ordinary field for the attribute but
				// runs a rich one through sanitizeHtml() instead - and that leaves
				// '"' intact, so the value would break out of the attribute.
				foreach( $htmlFields as $htmlField )
					if( str_contains( $value, '[['. $htmlField. ']]' ) )
						throw new \InvalidArgumentException( 'data-'. $name. ' cannot carry the rich text field '. $htmlField. ': its value is sanitized for content, not for an attribute' );
				$result['data-'. $name] = $value;
			}
			return $result;
		}

		private static function indent( string $source, int $levels ): string {
			if( trim( $source ) === '' )
				return '';
			$indent = str_repeat( "\t", $levels );
			return $indent. str_replace( "\n", "\n". $indent, rtrim( $source, "\r\n" ) ). "\n";
		}

		private static function escape( string $value ): string {
			return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}

		private static function escapeLiteral( string $value ): string {
			return str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], self::escape( $value ) );
		}

		/**
		 * Serialize normalized data-* attributes for one element. [[section:id]] is
		 * the same compile token a Layout may use and keeps a value such as an
		 * autoheight group or a scroll target unique per inserted section.
		 */
		private static function attributes( array $data, string $sectionId ): string {
			$attributes = '';
			foreach( $data as $name => $value )
				$attributes .= ' '. $name. '="'. self::escape( strtr( $value, [ '[[section:id]]' => $sectionId ] ) ). '"';
			return $attributes;
		}
	}
}
