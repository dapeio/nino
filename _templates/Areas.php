<?php
declare(strict_types=1);
/**
 * Nino Template Builder v3 — named areas and a safe, ordered component model.
 *
 * A v3 preset owns its section layout and the semantic areas in that layout.
 * Users may order a deliberately small component vocabulary inside every area;
 * manifests may restrict and restyle it without accepting arbitrary markup.
 *
 * @package Dape/Nino
 */

namespace Nino\Templates;

final class AreaComposer {

	private const string ID_PATTERN = '/^[a-z][a-z0-9-]*$/';
	private const string FIELD_PATTERN = '/^[A-Za-z][A-Za-z0-9]*$/';
	private const string TEXT_KEY_PATTERN = '#^/[A-Za-z0-9][A-Za-z0-9_./-]*$#';
	private const string IMAGE_KEY_PATTERN = '#^/[a-z0-9][a-z0-9_/-]*$#';
	private const string TEMPLATE_PATTERN = '#^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$#';
	private const array FRAME_CHOICES = [
		'screen' => [ 'auto', 'off', '50', '75', '90', '100' ],
		'vertical' => [ 'auto', 'top', 'middle', 'bottom' ],
		'background' => [ 'auto', 'default', 'alt', 'primary', 'dark', 'black', 'cover', 'parallax' ],
		'container' => [ 'auto', 'default', 'narrow', 'wide' ],
		'padding' => [ 'auto', 'none', 'small', 'default', 'big' ],
		'margin' => [ 'auto', 'none', 'small', 'default', 'big' ],
		'focus' => [ 'auto', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
		'overlay' => [ 'auto', 'none', 'soft', 'medium', 'strong' ],
	];
	private const array FRAME_FALLBACKS = [
		'screen' => 'off', 'vertical' => 'middle', 'background' => 'default', 'container' => 'default',
		'padding' => 'default', 'margin' => 'none', 'focus' => '5', 'overlay' => 'medium',
	];
	private const array TAGS = [ 'div', 'header', 'footer', 'article', 'aside', 'nav', 'h2', 'h3', 'h4', 'p', 'span', 'strong' ];

	public static function choices(): array {
		return self::FRAME_CHOICES;
	}

	public static function catalog(): array {
		return [
			'title' => self::component( 'Title', [ 'text' => self::property( 'Text', 'text', 'string', 'Section title' ) ], 'h3', 'ui-section-title', [ 'auto', 'quiet', 'loud' ] ),
			'subtitle' => self::component( 'Subtitle', [ 'text' => self::property( 'Text', 'text', 'string', 'A concise supporting line' ) ], 'p', 'ui-section-subtitle', [ 'auto', 'quiet', 'loud' ] ),
			'description' => self::component( 'Description', [ 'text' => self::property( 'Text', 'textarea', 'string', 'Explain what this area offers.' ) ], 'div', 'nino-area-description', [ 'auto', 'quiet', 'lead' ] ),
			'text' => self::component( 'Text', [ 'text' => self::property( 'Text', 'textarea', 'string', 'Add useful content here.' ) ], 'div', 'nino-area-text', [ 'auto', 'quiet', 'lead' ] ),
			'image' => self::component( 'Image', [
				'src' => self::property( 'Image', 'image', 'image', '', 1200, 800 ),
				'alt' => self::property( 'Alternative text', 'text', 'string', '' ),
			], 'div', 'nino-area-image', [ 'auto', 'rounded', 'cover' ] ),
			'button' => self::component( 'Button', [
				'label' => self::property( 'Label', 'text', 'string', 'Learn more' ),
				'href' => self::property( 'URL', 'url', 'string', '#' ),
			], 'a', '', [ 'link', 'default', 'primary', 'outline' ], [ 'target' => [ 'same', 'new' ] ] ),
			'price' => self::component( 'Price', [
				'value' => self::property( 'Price', 'text', 'string', '99' ),
				'suffix' => self::property( 'Suffix', 'text', 'string', '€' ),
			], 'div', 'nino-area-price', [ 'auto', 'quiet', 'loud' ] ),
			'number' => self::component( 'Number', [
				'value' => self::property( 'Value', 'text', 'string', '12' ),
				'label' => self::property( 'Label', 'text', 'string', 'Projects' ),
			], 'div', 'nino-area-number', [ 'auto', 'quiet', 'loud' ] ),
			'template' => self::component( 'Template', [ 'path' => self::property( 'Template', 'template', 'template', '' ) ], 'div', '', [ 'auto' ] ),
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
			$layouts[$layoutKey] = [
				'label' => trim( (string) ( $definition['label'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', $layoutKey ) ),
				'frame' => self::normalizeFrameRecommendation( $definition['frame'] ?? [] ),
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
			'previewHeight' => max( 360, min( 1000, (int) ( $manifest['previewHeight'] ?? 680 ) ) ),
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
		foreach( $preset['areas'] as $areaKey => $area ) {
			$components = [];
			foreach( $area['recommend']['components'] as $node ) {
				$copy = $node;
				$copy['bindings'] = self::defaultBindings( $spec, $area, $copy );
				$components[] = $copy;
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
					if( $definition['fieldType'] === 'image' )
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
		$render = self::renderDefinitions( $definition['render'] ?? [], $allowed );
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
			'container' => self::element( $definition['container'] ?? [], 'div', $source === 'single' ? 'ui-grid-100 nino-area' : '' ),
			'item' => self::element( $definition['item'] ?? [], 'article', 'ui-grid-m-33 nino-area-item' ),
			'render' => $render,
			'model' => $model,
			'typeTitle' => trim( (string) ( $definition['typeTitle'] ?? '' ) ) ?: ucwords( str_replace( '-', ' ', $key ) ),
			'shortcode' => self::shortcode( $definition['shortcode'] ?? [] ),
		];
	}

	private static function normalizeNode( mixed $node, array $allowed, array $render, string $source, array $model, bool $strictModel = true ): array {
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
		foreach( $component['properties'] as $property => $definition ) {
			$value = (string) ( $node['bindings'][$property] ?? self::suffix( $id, $property, $type ) );
			if( $source === 'elements' && preg_match( self::FIELD_PATTERN, $value ) !== 1 )
				throw new \InvalidArgumentException( 'component '. $id. ' maps to an unknown model field' );
			if( $source === 'elements' && $strictModel && isset( $model[$value] ) === false )
				throw new \InvalidArgumentException( 'component '. $id. ' maps to an unknown model field' );
			if( $source === 'elements' && $strictModel && ( $definition['fieldType'] === 'image' ) !== ( $model[$value]['type'] === 'image' ) )
				throw new \InvalidArgumentException( 'component '. $id. ' has an incompatible model mapping' );
			$bindings[$property] = $value;
		}
		$target = (string) ( $node['settings']['target'] ?? 'same' );
		if( in_array( $target, [ 'same', 'new' ], true ) === false )
			throw new \InvalidArgumentException( 'component '. $id. ' has an unsupported target' );
		return [ 'id' => $id, 'type' => $type, 'style' => $style, 'settings' => [ 'target' => $target ], 'bindings' => $bindings ];
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
				$node = self::normalizeNode( $node, $area['allowed'], $area['render'], $area['source'], $area['model'], $area['source'] === 'elements' && $elementMode === 'new' );
				if( isset( $seen[$node['id']] ) )
					throw new \InvalidArgumentException( 'duplicate component ID in area '. $areaKey );
				$seen[$node['id']] = true;
				if( $area['source'] === 'single' )
					$node['bindings'] = self::singleBindings( $spec, $node, $area );
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
					$suffix = self::suffix( $node['id'], $property, $node['type'] );
					$generated = self::generatedKey( $spec, $suffix );
					$key = (string) ( $node['bindings'][$property] ?? $generated );
					if( self::validKey( $key, false ) === false )
						throw new \InvalidArgumentException( 'invalid text binding in area '. $areaKey );
					$node['bindings'][$property] = $key;
					$result[] = [
						'slot' => $areaKey. '.'. $node['id']. '.'. $property, 'area' => $areaKey, 'component' => $node['id'], 'property' => $property,
						'label' => $area['label']. ' · '. $definition['label'], 'control' => $definition['control'], 'default' => $definition['default'],
						'key' => $key, 'generatedKey' => $generated, 'mode' => $key === $generated ? 'new' : 'existing',
					];
				}
			}
			unset( $node );
		}
		return $result;
	}

	private static function imageDescriptors( array &$spec, array $preset, array $effective ): array {
		$result = [];
		if( in_array( $effective['frame']['background'], [ 'cover', 'parallax' ], true ) ) {
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
		foreach( $preset['areas'] as $areaKey => $area ) {
			$replace['[[area:'. $areaKey. ']]'] = self::renderArea( $spec, $areaKey, $area, $effective['areas'][$areaKey], $fieldMap, $imageMap, $titleId );
		}
		$body = strtr( $preset['_layouts'][$effective['layout']], $replace );
		if( preg_match( '/\[\[(?:area:[^\]]+|section:id)\]\]/', $body ) === 1 )
			throw new \InvalidArgumentException( 'layout contains an unresolved compile token' );

		$rowClasses = [ 'ui-grid-row' ];
		if( $effective['frame']['container'] !== 'default' )
			$rowClasses[] = 'nino-section-container--'. $effective['frame']['container'];
		if( $spec['pageMotion'] === 'on' )
			$rowClasses[] = 'js-vpa';
		$body = '<div class="'. implode( ' ', $rowClasses ). "\">\n". self::indent( $body, 1 ). '</div>';
		$classes = self::sectionClasses( $effective['frame'] );
		$attributes = ' id="'. self::escape( $spec['id'] ). '" class="'. self::escape( implode( ' ', $classes ) ). '"';
		if( $effective['frame']['screen'] !== 'off' )
			$attributes .= ' data-cover-height="'. self::escape( $effective['frame']['screen'] ). '"';
		if( $titleId !== '' )
			$attributes .= ' aria-labelledby="'. self::escape( $titleId ). '"';
		$meta = '<!-- nino:section '. json_encode( $spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ). ' -->';
		$inner = $meta. "\n";
		if( isset( $imageMap['background'] ) )
			$inner .= '[image '. $imageMap['background']. ' alt=""]'. "\n";
		$needsLayer = isset( $imageMap['background'] ) || $effective['frame']['screen'] !== 'off';
		$inner .= $needsLayer
			? '<div class="'. ( $effective['frame']['background'] === 'cover' && $effective['frame']['screen'] === 'off' ? 'ui-img-background-content' : 'js-cover-content' ). "\">\n". self::indent( $body, 1 ). '</div>'
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
				if( $area['source'] === 'elements' )
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
			$nodes .= self::renderComponent( $node, $area['render'][$node['type']], $values, $area['source'], $componentId );
		}
		if( $nodes === '' )
			return '';
		if( $area['source'] === 'single' ) {
			$element = $area['container'];
			$class = trim( $element['class']. ' '. $styleClass );
			return '<'. $element['tag']. ( $class === '' ? '' : ' class="'. self::escape( $class ). '"' ). '>'. $nodes. '</'. $element['tag']. '>';
		}
		$source = $spec['areas'][$areaKey]['source'];
		$shortcode = $source['shortcode'];
		$element = $area['item'];
		$class = trim( $element['class']. ' '. $styleClass );
		$item = '<'. $element['tag']. ( $class === '' ? '' : ' class="'. self::escape( $class ). '"' ). '>'. $nodes. '</'. $element['tag']. '>';
		return '[elements /'. $source['elementType']
			. ' locale="'. self::escape( $shortcode['locale'] ). '" callback="'. self::escape( $shortcode['callback'] ). '"'
			. ' limit="'. $shortcode['limit']. '" query="'. self::escape( $shortcode['query'] ). '"]'. $item. '[/elements]';
	}

	private static function renderComponent( array $node, array $definition, array $values, string $source, string $id ): string {
		$style = $node['style'] === 'auto' ? 'auto' : $node['style'];
		$class = trim( $definition['class']. ' '. ( $definition['styleClasses'][$style] ?? '' ) );
		$attribute = $class === '' ? '' : ' class="'. self::escape( $class ). '"';
		if( $id !== '' )
			$attribute .= ' id="'. self::escape( $id ). '"';
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

	private static function renderDefinitions( mixed $overrides, array $allowed ): array {
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
			return [ 'default' => [ 'label' => 'Default', 'class' => '' ] ];
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
				'quiet' => 'nino-component--quiet', 'loud' => 'nino-component--loud', 'lead' => 'nino-component--lead', 'rounded' => 'nino-component--rounded', 'cover' => 'nino-component--cover',
				'link' => '', 'default' => 'ui-btn', 'primary' => 'ui-btn ui-btn--primary', 'outline' => 'ui-btn ui-btn--outline', default => '',
			};
		return [ 'label' => $label, 'properties' => $properties, 'tag' => $tag, 'class' => $class, 'styles' => array_values( array_unique( [ 'auto', ...$styles ] ) ), 'styleClasses' => $styleClasses, 'settings' => $settings ];
	}

	private static function property( string $label, string $control, string $fieldType, string $default, int $width = 0, int $height = 0 ): array {
		return [ 'label' => $label, 'kind' => $control, 'control' => $control === 'image' ? 'image' : $control, 'fieldType' => $fieldType, 'default' => $default, 'width' => $width, 'height' => $height ];
	}

	private static function element( mixed $definition, string $tag, string $class ): array {
		$definition = is_array( $definition ) ? $definition : [];
		return [ 'tag' => self::tag( (string) ( $definition['tag'] ?? $tag ) ), 'class' => self::classes( (string) ( $definition['class'] ?? $class ) ) ];
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
			return $node['bindings'];
		$result = [];
		foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
			$value = (string) ( $node['bindings'][$property] ?? '' );
			$result[$property] = $definition['kind'] === 'template'
				? $value
				: self::generatedKey( $spec, self::suffix( $node['id'], $property, $node['type'] ) );
		}
		return $result;
	}

	private static function singleBindings( array $spec, array $node, array $area ): array {
		$result = [];
		foreach( $area['render'][$node['type']]['properties'] as $property => $definition ) {
			$value = (string) ( $node['bindings'][$property] ?? '' );
			if( $definition['kind'] === 'template' ) {
				if( $value !== '' && preg_match( self::TEMPLATE_PATTERN, $value ) !== 1 )
					throw new \InvalidArgumentException( 'invalid reusable template path' );
				$result[$property] = $value;
				continue;
			}
			if( str_starts_with( $value, '/' ) === false )
				$value = self::generatedKey( $spec, $value === '' ? self::suffix( $node['id'], $property, $node['type'] ) : $value );
			if( self::validKey( $value, $definition['kind'] === 'image' ) === false )
				throw new \InvalidArgumentException( 'invalid binding for component '. $node['id'] );
			$result[$property] = $value;
		}
		return $result;
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
		$classes = [ 'ui-section', 'nino-section' ];
		if( in_array( $frame['background'], [ 'alt', 'primary', 'dark', 'black' ], true ) )
			$classes[] = 'ui-section--'. $frame['background'];
		if( in_array( $frame['background'], [ 'cover', 'parallax' ], true ) ) {
			$classes[] = 'ui-section--fullwidth';
			$classes[] = 'ui-section--black';
			$classes[] = $frame['background'] === 'parallax' ? 'js-parallex' : ( $frame['screen'] === 'off' ? 'ui-img-background' : 'js-cover' );
			$classes[] = 'nino-section-overlay--'. $frame['overlay'];
			$classes[] = 'nino-section-focus--'. $frame['focus'];
		}
		if( $frame['screen'] !== 'off' )
			$classes[] = 'js-cover';
		$classes[] = 'nino-section-vertical--'. $frame['vertical'];
		$spacing = [ 'none' => '0', 'small' => '2', 'big' => '6' ];
		if( isset( $spacing[$frame['padding']] ) ) {
			$classes[] = 'ui-pt-'. $spacing[$frame['padding']];
			$classes[] = 'ui-pb-'. $spacing[$frame['padding']];
		}
		if( isset( $spacing[$frame['margin']] ) ) {
			$classes[] = 'ui-mt-'. $spacing[$frame['margin']];
			$classes[] = 'ui-mb-'. $spacing[$frame['margin']];
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

	private static function indent( string $source, int $levels ): string {
		if( trim( $source ) === '' )
			return '';
		$indent = str_repeat( "\t", $levels );
		return $indent. str_replace( "\n", "\n". $indent, rtrim( $source, "\r\n" ) ). "\n";
	}

	private static function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
