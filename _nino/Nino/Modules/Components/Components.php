<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Components			see _nino/Nino/Modules/Modules.php for the
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
	 *	Components					Shortcodes with a schema: the components a section is written
	 *											in, the stacks that loop elements around them, and the
	 *											registry both are listed in
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Components {

		/*	The markup this module renders, declared once rather than built
			inside the methods - see AGENTS.md, "Markup belongs in a template",
			and \Nino\Modules\Navigation::$html for the shape. A project that
			wants another element or another class replaces a fragment here,
			or registers a component of the same name in MyApp. A value that
			is filled in - [[value]], [[inner]], [[cells]] - comes last in each
			list of replacements: what a fill brings is never looked at again
			for a token of its own	*/
		public static
			$html = [
				'title'				=> '<h[[level]] class="nino-section-title[[style]][[class]]">[[value]]</h[[level]]>',
				'subtitle'		=> '<p class="nino-section-subtitle[[style]][[class]]">[[value]]</p>',
				'text'				=> '<div class="nino-section-text[[richtext]][[style]][[class]]">[[value]]</div>',
				'img'					=> '<img src="[[src]]" width="[[width]]" height="[[height]]" alt="[[alt]]">',
				'image'				=> '<div class="nino-image[[ratio]][[focus]][[class]]">[[img]]</div>',
				'button'			=> '<a class="nino-btn[[style]][[size]][[class]]"[[href]][[target]]>[[value]]</a>',
				'spacer'			=> '<div class="nino-mt-[[size]][[class]]"></div>',
				'cell'				=> '<div[[class]][[attributes]]>[[inner]]</div>',
				'stack'				=> '<div class="nino-grid-row nino-stack[[gap]][[class]]"[[id]]>[[cells]]</div>',
				'slider'			=> '<div class="nino-slider[[class]]"[[id]][[width]][[min]]><ul>[[cells]]</ul></div>',
				'slide'				=> '<li>[[inner]]</li>',
				'filter'			=> '<div class="nino-filter[[class]]"[[id]]>[[nav]]<div class="nino-grid-row nino-stack[[gap]]">[[cells]]</div></div>',
				'filter-nav'	=> '<nav class="nino-filter-nav" aria-label="[[label]]">[[buttons]]</nav>',
				'filter-all'	=> '<button type="button" class="nino-filter-btn nino-is-active" data-filter-value="" aria-pressed="true">[[label]] <span class="nino-filter-count">([[count]])</span></button>',
				'filter-btn'	=> '<button type="button" class="nino-filter-btn" data-filter-value="[[.value]]" aria-pressed="false">[[.value]] <span class="nino-filter-count">([[.count]])</span></button>',
				'list'				=> '<ul class="nino-list[[style]][[class]]"[[id]]>[[cells]]</ul>',
				'item'				=> '<li>[[inner]]</li>',
			];

		// Where the registry, the stacks and the element a stack is rendering
		// a cell for live in $appData: three keys of their own, each holding one
		// thing, so that no name a component takes can meet another's
		public const string REGISTRY = './nino/components';
		public const string STACKS = './nino/components/stacks';
		public const string CONTEXT = './nino/components/element';

		// How many stacks without an id this request has rendered: the
		// autoheight group of one is made from it
		private const string COUNTER = './nino/components/count';

		// What a component takes its first argument from, see value()
		public const array SOURCES = [ 'text', 'image', 'href', 'content', 'none' ];

		// What an attribute may be: the types of a feature's settings that
		// make sense here, and three of this module's own - a text key, an
		// image slot and an address
		public const array TYPES = [ 'string', 'int', 'bool', 'select', 'lines', 'key', 'image', 'href' ];

		// The pictures the Builder draws a component or a stack as - a fixed
		// list, so that no feature can break the preview with one of its own
		public const array PREVIEWS = [ 'title', 'text', 'image', 'button', 'block', 'cells' ];

		// The widths a cell may have in a grid, as Nino.css has them
		private const array WIDTHS = [ '25', '33', '50', '66', '75', '100' ];

		// What a slot of a stack's grid is, when the shortcode does not say
		private const string COLS = '100 50 33';

		// Names an attribute may not take: the arguments the wrapper hands the
		// callback besides the attributes, and the ones it reads itself
		private const array RESERVED = [ 'value', 'source', 'content', 'text', 'uri', 'grid' ];

		// The attributes every stack has, as a schema's attributes are written:
		// the loop of [elements] and the id that filter, autoheight and slider
		// find a stack by - and, for a stack with a grid, what its cells are
		private const array LOOP = [
			'sort'				=> [ 'type' => 'string', 'default' => '' ],
			'offset'			=> [ 'type' => 'int', 'min' => 0, 'default' => 0 ],
			'limit'				=> [ 'type' => 'int', 'min' => 0, 'default' => 0 ],
			'query'				=> [ 'type' => 'string', 'default' => '' ],
			'callback'		=> [ 'type' => 'string', 'default' => '' ],
			'locale'			=> [ 'type' => 'string', 'default' => '' ],
			'id'					=> [ 'type' => 'string', 'default' => '' ],
		];
		private const array GRID = [
			'cols'				=> [ 'type' => 'string', 'default' => self::COLS ],
			'gap'					=> [ 'type' => 'int', 'min' => 0, 'max' => 6, 'default' => 2 ],
			'autoheight'	=> [ 'type' => 'bool', 'default' => false ],
		];

		/**
		 *	Module initiating: the components and the stacks the kernel brings,
		 *	and then those of the active features that declare them in their
		 *	manifest
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$components = [
				[ 'title', 'Title', 'Titel', 'title', 'text', [
					'level' => [ 'type' => 'select', 'options' => [ '1', '2', '3', '4' ], 'default' => '2', 'label' => [ 'en_US' => 'Level', 'de_DE' => 'Ebene' ] ],
					'style' => self::_style(),
				] ],
				[ 'subtitle', 'Subtitle', 'Untertitel', 'title', 'text', [
					'style' => self::_style(),
				] ],
				[ 'text', 'Text', 'Text', 'text', 'text', [
					'format' => [ 'type' => 'select', 'options' => [ 'inline', 'lines', 'blocks' ], 'default' => 'blocks', 'label' => [ 'en_US' => 'Format', 'de_DE' => 'Format' ],
						'hint' => [ 'en_US' => 'What the text may hold: inline tags, line breaks, or paragraphs and lists.', 'de_DE' => 'Was der Text enthalten darf: Auszeichnungen, Zeilenumbrüche oder Absätze und Listen.' ] ],
					'style' => self::_style(),
				] ],
				[ 'image', 'Image', 'Bild', 'image', 'image', [
					'alt' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Alternative text', 'de_DE' => 'Alternativtext' ],
						'hint' => [ 'en_US' => 'Used where the slot has no text of its own. Empty marks the picture as decoration.', 'de_DE' => 'Gilt, wo der Slot keinen eigenen Text hat. Leer kennzeichnet das Bild als Dekoration.' ] ],
					'focus' => [ 'type' => 'select', 'options' => [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], 'default' => '', 'label' => [ 'en_US' => 'Focus', 'de_DE' => 'Fokus' ],
						'hint' => [ 'en_US' => 'Which part of the picture survives a crop, as one of nine positions.', 'de_DE' => 'Welcher Teil des Bildes beim Zuschneiden bleibt, als eine von neun Positionen.' ] ],
					'ratio' => [ 'type' => 'select', 'options' => [ '', '1-1', '4-3', '3-2', '16-9', '21-9' ], 'default' => '', 'label' => [ 'en_US' => 'Ratio', 'de_DE' => 'Seitenverhältnis' ] ],
				] ],
				[ 'button', 'Button', 'Knopf', 'button', 'text', [
					'href' => [ 'type' => 'href', 'default' => '', 'label' => [ 'en_US' => 'Link', 'de_DE' => 'Ziel' ],
						'hint' => [ 'en_US' => 'A text key, an address, or in a stack a field of the element.', 'de_DE' => 'Ein Textschlüssel, eine Adresse oder in einem Stapel ein Feld des Elements.' ] ],
					'style' => [ 'type' => 'select', 'options' => [ '', 'primary', 'outline', 'light', 'dark', 'brand-alt' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
					'size' => [ 'type' => 'select', 'options' => [ '', 'small', 'big' ], 'default' => '', 'label' => [ 'en_US' => 'Size', 'de_DE' => 'Größe' ] ],
					'target' => [ 'type' => 'select', 'options' => [ '', '_blank' ], 'default' => '', 'label' => [ 'en_US' => 'Opens in', 'de_DE' => 'Öffnet in' ] ],
				] ],
				[ 'html', 'HTML', 'HTML', 'block', 'content', [] ],
				[ 'spacer', 'Spacer', 'Abstand', 'block', 'none', [
					'size' => [ 'type' => 'select', 'options' => [ '1', '2', '3', '4', '5', '6' ], 'default' => '2', 'label' => [ 'en_US' => 'Size', 'de_DE' => 'Größe' ] ],
				] ],
			];

			foreach( $components as [ $name, $en, $de, $preview, $source, $attributes ] )
				self::addComponent( $appData, $name, [ self::class, 'component'. ucfirst( $name ) ], [
					'label'				=> [ 'en_US' => $en, 'de_DE' => $de ],
					'source'			=> $source,
					'loop'				=> true,
					'attributes'	=> $attributes,
					'preview'			=> $preview,
				] );

			$stacks = [
				[ 'stack', 'Stack', 'Stapel', 'cells', true, [] ],
				[ 'slider', 'Slider', 'Slider', 'cells', false, [
					'width' => [ 'type' => 'string', 'default' => '75%', 'label' => [ 'en_US' => 'Slide width', 'de_DE' => 'Breite einer Folie' ],
						'hint' => [ 'en_US' => 'In percent of the slider or in pixels: 60% or 320px.', 'de_DE' => 'In Prozent des Sliders oder in Pixeln: 60% oder 320px.' ] ],
					'min' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Least slide width', 'de_DE' => 'Kleinste Breite einer Folie' ],
						'hint' => [ 'en_US' => 'Written like the width; empty sets none.', 'de_DE' => 'Geschrieben wie die Breite; leer setzt keine.' ] ],
				] ],
				[ 'filter', 'Filter', 'Filter', 'cells', true, [
					'by' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Filter by', 'de_DE' => 'Filtern nach' ],
						'hint' => [ 'en_US' => 'The field whose values become the buttons.', 'de_DE' => 'Das Feld, dessen Werte zu den Knöpfen werden.' ] ],
					'all' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Label of the first button', 'de_DE' => 'Beschriftung des ersten Knopfs' ],
						'hint' => [ 'en_US' => 'Empty takes the text /template/common/filter/all, or the word of the page\'s language.', 'de_DE' => 'Leer nimmt den Text /template/common/filter/all oder das Wort der Seitensprache.' ] ],
				] ],
				[ 'list', 'List', 'Liste', 'block', false, [
					'style' => [ 'type' => 'select', 'options' => [ '', 'check', 'numbered', 'columns' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
				] ],
			];

			foreach( $stacks as [ $name, $en, $de, $preview, $grid, $attributes ] )
				self::addStack( $appData, $name, [ self::class, 'stack'. ucfirst( $name ) ], [
					'label'				=> [ 'en_US' => $en, 'de_DE' => $de ],
					'grid'				=> $grid,
					'attributes'	=> $attributes,
					'preview'			=> $preview,
				] );

			\Nino\Features::registerComponents( $appData );
		}

		/**
		 *	The style select the text components share
		 *
		 *	@return 	array										The attribute's schema
		 */
		private static function _style(): array {
			return [ 'type' => 'select', 'options' => [ '', 'loud', 'quiet' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ],
				'hint' => [ 'en_US' => 'A step up or down the type scale.', 'de_DE' => 'Eine Stufe größer oder kleiner in der Schriftgröße.' ] ];
		}

		/**
		 *	Register a component: a shortcode whose first argument names where
		 *	its value comes from, and whose attributes a schema declares. The
		 *	callback is not the shortcode itself but its renderer - it is
		 *	called with the arguments made ready (see dispatch()) and only
		 *	renders. A component of the same name that is registered already,
		 *	or a shortcode, is replaced; that is how a project changes the
		 *	markup of one. A schema the kernel cannot work with is refused
		 *	here, at registration, with an E_USER_ERROR - never while a page
		 *	is rendered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The shortcode, a slug
		 *	@param		callable	$callback			( array &$appData, array $args ): string
		 *	@param		array			$schema				See validate()
		 *
		 *	@return 	void
		 */
		public static function addComponent( array &$appData, string $name, callable $callback, array $schema ): void {
			self::_register( $appData, $name, $callback, $schema, false );
		}

		/**
		 *	Register a stack: a component that loops the elements of a type
		 *	around its content, and draws what it needs around the cells -
		 *	see renderStack(). Its first argument is the type's uri
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The shortcode, a slug
		 *	@param		callable	$callback			( array &$appData, array $args ): string
		 *	@param		array			$schema				See validate()
		 *
		 *	@return 	void
		 */
		public static function addStack( array &$appData, string $name, callable $callback, array $schema ): void {
			self::_register( $appData, $name, $callback, $schema, true );
		}

		/**
		 *	The part of addComponent() and addStack() they share
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name
		 *	@param		callable	$callback
		 *	@param		array			$schema
		 *	@param		bool			$stack				Register a stack
		 *
		 *	@return 	void
		 */
		private static function _register( array &$appData, string $name, callable $callback, array $schema, bool $stack ): void {

			$clean = self::validate( $name, $schema, $stack );

			if( is_string( $clean ) === true )
				trigger_error( 'Components::'. ( $stack === true ? 'addStack' : 'addComponent' ). '(): '. $clean, E_USER_ERROR );
			else
				self::_store( $appData, $name, $callback, $clean, $stack );
		}

		/**
		 *	Registers a schema that has been validated
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name
		 *	@param		callable	$callback
		 *	@param		array			$clean				The schema, as validate() answered it
		 *	@param		bool			$stack				Register a stack
		 *
		 *	@return 	void
		 */
		private static function _store( array &$appData, string $name, callable $callback, array $clean, bool $stack ): void {

			$key = $stack === true ? self::STACKS : self::REGISTRY;

			// The shortcode a feature registers is told apart from the kernel's
			// by the class its callback belongs to (see \Nino\Features::shortcodes()).
			// The wrapper is a closure of this module and would read as this
			// module's, so it takes the scope of the callback's class
			$wrapper = static function( array &$appData, mixed &$args ) use ( $name, $clean, $callback, $stack ): string {
				return \Nino\Modules\Components::dispatch( $appData, is_array( $args ) === true ? $args : [], $name, $clean, $callback, $stack );
			};

			$owner = is_array( $callback ) === true && is_string( $callback[0] ) === true ? ltrim( $callback[0], '\\' ) : '';

			// bind() answers null, with a warning, for the scope of an internal
			// class alone - kept out, so there is nothing to fall back from
			if( $owner !== '' && class_exists( $owner ) === true && ( new \ReflectionClass( $owner ) )->isInternal() === false )
				$wrapper = \Closure::bind( $wrapper, null, $owner );

			$appData[$key][$name] = $clean;

			\Nino\Callbacks::removeCallbacks( $appData, '/nino/html/shortcode/'. $name );
			\Nino\Html::addShortcode( $appData, $name, $wrapper );
		}

		/**
		 *	The components registered, by name - read only
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => schema, in registration order
		 */
		public static function components( array &$appData ): array {
			return (array) ( $appData[self::REGISTRY] ?? [] );
		}

		/**
		 *	The stacks registered, by name - read only
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => schema, in registration order
		 */
		public static function stacks( array &$appData ): array {
			return (array) ( $appData[self::STACKS] ?? [] );
		}

		/**
		 *	The values an attribute has when a shortcode does not set it, from
		 *	the schema and nowhere else - every one a string, as a shortcode
		 *	carries it: a bool is '1' or '0', an int its digits. A stack has
		 *	those of its loop and, with a grid, of its cells besides its own.
		 *	The Builder writes an attribute into a file only where it differs
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					A component's or a stack's name
		 *	@param		bool			$stack				Look the name up among the stacks
		 *
		 *	@return 	array										attribute => default; [] for a name nobody registered
		 */
		public static function defaults( array &$appData, string $name, bool $stack = false ): array {

			$schema = ( $stack === true ? self::stacks( $appData ) : self::components( $appData ) )[$name] ?? null;

			if( is_array( $schema ) === false )
				return [];

			$defaults = [];

			foreach( self::_attributes( $schema, $stack ) as $attribute => $declared )
				$defaults[$attribute] = self::_normalize( $declared, null );

			return $defaults;
		}

		/**
		 *	Check a schema and make it complete: the answer is the schema with
		 *	every key present, or what is wrong with it. Not for a page to
		 *	call - it is what addComponent() and addStack() refuse a schema
		 *	by, and what a feature's manifest is read through (see
		 *	\Nino\Features::manifest())
		 *
		 *	@param		string		$name					The shortcode, a slug
		 *	@param		mixed			$schema				label, source (not for a stack), loop, attributes, preview;
		 *																	for a stack grid and assets. See docs/development.md
		 *	@param		bool			$stack				Read it as a stack's
		 *
		 *	@return 	array|string						The clean schema, or what is wrong with it
		 */
		public static function validate( string $name, mixed $schema, bool $stack = false ): array|string {

			$what = ( $stack === true ? 'stack' : 'component' ). ' "'. $name. '"';

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $name ) !== 1 )
				return $what. ': a name is a slug of lower case letters, digits and hyphens';

			if( is_array( $schema ) === false )
				return $what. ': the schema must be an array';

			if( \Nino\Features::localizedValid( $schema['label'] ?? '' ) === false )
				return $what. ': "label" must be a string or a locale => string map';

			$clean = [ 'label' => $schema['label'] ];

			if( $stack === false ) {

				$source = $schema['source'] ?? '';

				if( in_array( $source, self::SOURCES, true ) === false )
					return $what. ': "source" must be one of '. implode( ', ', self::SOURCES );

				$clean['source'] = $source;
				$clean['loop'] = ( $schema['loop'] ?? true ) === true;
			}
			else {

				if( isset( $schema['grid'] ) === true && is_bool( $schema['grid'] ) === false )
					return $what. ': "grid" must be true or false';

				$clean['grid'] = ( $schema['grid'] ?? false ) === true;
			}

			$preview = $schema['preview'] ?? ( $stack === true ? 'cells' : 'block' );

			if( in_array( $preview, self::PREVIEWS, true ) === false )
				return $what. ': "preview" must be one of '. implode( ', ', self::PREVIEWS );

			$clean['preview'] = $preview;

			$taken = $stack === true ? array_merge( array_keys( self::LOOP ), array_keys( self::GRID ), self::RESERVED ) : self::RESERVED;

			if( is_array( $schema['attributes'] ?? [] ) === false )
				return $what. ': "attributes" must be a map of attribute => schema';

			$clean['attributes'] = [];

			foreach( (array) ( $schema['attributes'] ?? [] ) as $attribute => $declared ) {

				if( is_string( $attribute ) === false || preg_match( '/^[a-z][a-zA-Z0-9]*$/', $attribute ) !== 1 )
					return $what. ': an attribute name is a lowerCamel identifier';

				if( in_array( $attribute, $taken, true ) === true )
					return $what. ': "'. $attribute. '" is an argument the kernel reads itself and cannot be an attribute';

				$result = self::_attributeSchema( $what, $attribute, $declared );

				if( is_string( $result ) === true )
					return $result;

				$clean['attributes'][$attribute] = $result;
			}

			if( $stack === true && isset( $schema['assets'] ) === true ) {

				if( is_array( $schema['assets'] ) === false )
					return $what. ': "assets" must be a map of css and js to lists of files';

				$clean['assets'] = [];

				foreach( $schema['assets'] as $kind => $files ) {

					if( in_array( $kind, [ 'css', 'js' ], true ) === false || is_array( $files ) === false || array_is_list( $files ) === false )
						return $what. ': "assets" holds the lists "css" and "js"';

					foreach( $files as $file )
						if( is_string( $file ) === false || str_starts_with( $file, '/' ) === false || str_contains( $file, '..' ) === true )
							return $what. ': "assets" lists project paths, written with a leading slash';

					$clean['assets'][$kind] = $files;
				}
			}

			return $clean;
		}

		/**
		 *	One attribute's schema, checked and complete
		 *
		 *	@param		string		$what					How the message names the component
		 *	@param		string		$attribute
		 *	@param		mixed			$declared
		 *
		 *	@return 	array|string						The clean schema, or what is wrong with it
		 */
		private static function _attributeSchema( string $what, string $attribute, mixed $declared ): array|string {

			$where = $what. ', attribute "'. $attribute. '": ';

			if( is_array( $declared ) === false )
				return $where. 'must be an array';

			$type = (string) ( $declared['type'] ?? '' );

			if( in_array( $type, self::TYPES, true ) === false )
				return $where. 'has an unknown type "'. $type. '"';

			// A hint that is '' is none: that is what a schema that was checked
			// before carries, and checking one again must not refuse it
			foreach( [ 'label', 'hint' ] as $words )
				if( isset( $declared[$words] ) === true && $declared[$words] !== '' && \Nino\Features::localizedValid( $declared[$words] ) === false )
					return $where. '"'. $words. '" must be a string or a locale => string map';

			if( array_key_exists( 'default', $declared ) === false )
				return $where. 'has no "default" - every attribute needs one';

			$clean = [
				'type'		=> $type,
				'label'		=> $declared['label'] ?? $attribute,
				'hint'		=> $declared['hint'] ?? '',
			];

			$default = $declared['default'];

			if( $type === 'select' ) {

				$options = $declared['options'] ?? null;

				if( is_array( $options ) === false || $options === [] || array_is_list( $options ) === false )
					return $where. 'a select needs "options", a list of values';

				$clean['options'] = [];

				foreach( $options as $option )
					if( is_string( $option ) === false && is_int( $option ) === false )
						return $where. '"options" is a list of strings';
					else
						$clean['options'][] = (string) $option;

				if( ( is_string( $default ) === false && is_int( $default ) === false ) || in_array( (string) $default, $clean['options'], true ) === false )
					return $where. 'the default is not one of the options';

				$clean['default'] = (string) $default;

				return $clean;
			}

			if( $type === 'int' ) {

				foreach( [ 'min', 'max' ] as $bound )
					if( isset( $declared[$bound] ) === true ) {

						if( is_int( $declared[$bound] ) === false )
							return $where. '"'. $bound. '" must be an int';

						$clean[$bound] = $declared[$bound];
					}

				if( isset( $clean['min'], $clean['max'] ) === true && $clean['min'] > $clean['max'] )
					return $where. '"min" is above "max"';

				if( is_int( $default ) === false || $default < ( $clean['min'] ?? PHP_INT_MIN ) || $default > ( $clean['max'] ?? PHP_INT_MAX ) )
					return $where. 'the default must be an int within its bounds';

				$clean['default'] = $default;

				return $clean;
			}

			if( $type === 'bool' ) {

				if( is_bool( $default ) === false )
					return $where. 'the default must be true or false';

				$clean['default'] = $default;

				return $clean;
			}

			if( is_string( $default ) === false )
				return $where. 'the default must be a string';

			$clean['default'] = $default;

			return $clean;
		}

		/**
		 *	The attributes a shortcode reads: what its schema declares, 'class'
		 *	where it does not, and for a stack its loop and, with a grid, its
		 *	cells in front of its own
		 *
		 *	@param		array 		$schema				A clean schema
		 *	@param		bool			$stack
		 *
		 *	@return 	array										attribute => its schema
		 */
		private static function _attributes( array $schema, bool $stack ): array {

			$attributes = $stack === true ? self::LOOP + ( ( $schema['grid'] ?? false ) === true ? self::GRID : [] ) : [];

			return $attributes + (array) ( $schema['attributes'] ?? [] ) + [ 'class' => [ 'type' => 'string', 'default' => '' ] ];
		}

		/**
		 *	What a shortcode's argument is as one attribute, by its schema:
		 *	the default for an argument that is not there, a select outside
		 *	its options and a number that is none, and the value otherwise -
		 *	kept inside the bounds of an int. A string either way
		 *
		 *	@param		array 		$declared			The attribute's clean schema
		 *	@param		mixed			$raw					What the shortcode said, null when it did not
		 *
		 *	@return 	string
		 */
		private static function _normalize( array $declared, mixed $raw ): string {

			$default = $declared['default'] ?? '';
			$default = is_bool( $default ) === true ? ( $default === true ? '1' : '0' ) : (string) $default;

			if( is_string( $raw ) === false )
				return $default;

			switch( $declared['type'] ?? 'string' ) {

				case 'select':
					return in_array( $raw, (array) ( $declared['options'] ?? [] ), true ) === true ? $raw : $default;

				case 'int':
					if( preg_match( '/^-?\d{1,9}$/', $raw ) !== 1 )
						return $default;
					return (string) max( $declared['min'] ?? PHP_INT_MIN, min( $declared['max'] ?? PHP_INT_MAX, (int) $raw ) );

				case 'bool':
					if( in_array( strtolower( $raw ), [ '1', 'true', 'yes', 'on' ], true ) === true )
						return '1';
					return in_array( strtolower( $raw ), [ '0', 'false', 'no', 'off' ], true ) === true ? '0' : $default;
			}

			return $raw;
		}

		/**
		 *	The part every registered shortcode of this module runs before its
		 *	callback: the first argument is resolved over value(), the missing
		 *	attributes are filled from the schema and the ones outside their
		 *	options are reset, and a component that has no value renders
		 *	nothing. Public because the closure that stands for the callback in
		 *	the shortcodes has to reach it from the callback's class (see
		 *	_register()), not because a page calls it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments of the shortcode
		 *	@param		string		$name
		 *	@param		array			$schema				The clean schema
		 *	@param		callable	$callback			The renderer
		 *	@param		bool			$stack
		 *
		 *	@return 	string
		 */
		public static function dispatch( array &$appData, array $args, string $name, array $schema, callable $callback, bool $stack ): string {

			$source		= (string) ( $args[0] ?? '' );
			$content	= (string) ( $args['content'] ?? '' );
			$fixed		= is_string( $args['text'] ?? null ) === true ? $args['text'] : null;

			$prepared = [ 'source' => $source, 'content' => $content ];

			foreach( self::_attributes( $schema, $stack ) as $attribute => $declared )
				$prepared[$attribute] = self::_normalize( $declared, $args[$attribute] ?? null );

			if( $fixed !== null )
				$prepared['text'] = $fixed;

			if( $stack === true ) {

				$prepared['grid'] = $schema['grid'] === true;

				return (string) call_user_func_array( $callback, [ &$appData, &$prepared ] );
			}

			$kind = (string) $schema['source'];

			// The one argument a slot was ever given by name: [image uri="hero"]
			if( $kind === 'image' && $source === '' )
				$prepared['source'] = $source = (string) ( $args['uri'] ?? '' );

			// A component with a 'format' attribute - [text] - says what a text key
			// may hold; one without reads its keys as plain text
			$format = (string) ( $prepared['format'] ?? 'plain' );

			$value = $kind === 'content' ? ( trim( $content ) === '' ? null : $content ) : self::value( $appData, $source, $kind, $format, $fixed );

			// Nothing to say is not a heading with nothing in it - for a missing
			// value as for an empty one. A component without a source says its
			// piece anyway
			if( $value === null || ( $value === '' && $kind !== 'none' ) )
				return '';

			$prepared['value'] = $value;

			return (string) call_user_func_array( $callback, [ &$appData, &$prepared ] );
		}

		/**
		 *	Resolve the first argument of a component: the one place that
		 *	knows what a source is.
		 *
		 *	A source that begins with a slash is a text key: the value it has
		 *	in the current language, null where it has none. In a stack, a
		 *	name without one is a field of the element the cell is rendered
		 *	for - drawn by \Nino\Html::fieldValue() after its field type, null
		 *	where the type has no such field - and .id and .uri are the
		 *	number of the element in the loop and its uri. Outside a stack,
		 *	both are nothing. A fixed value, the text="..." attribute, takes
		 *	the place of the argument altogether.
		 *
		 *	Whatever it answers can be written into html as it is: escaped,
		 *	and with every '[' as &#91;, because shortcode output is rendered
		 *	once more and a value an editor wrote must not open a fill or a
		 *	shortcode there. Two kinds of source are not text. An image's value
		 *	is the reference to the picture - the uri of a slot, or in a stack
		 *	the filename an image field holds - escaped like text, so that it
		 *	is safe to write; the renderer looks the picture up through
		 *	\Nino\Images by $args['source'], or by the field it names. An
		 *	address is escaped like text, and one with
		 *	a scheme that is not http, https, mailto or tel is a '#'.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source				The first argument of the shortcode
		 *	@param		string		$kind					The schema's source: text, image, href, content or none
		 *	@param		string		$format				What a text key's value may hold: plain, inline, lines or blocks;
		 *																	a value in the other formats is sanitized, not escaped
		 *	@param		string|null	$fixed				The text="..." attribute, null where there is none
		 *
		 *	@return 	string|null							The value, null where the source has none
		 */
		public static function value( array &$appData, string $source, string $kind, string $format = 'plain', ?string $fixed = null ): ?string {

			if( $kind === 'none' )
				return '';

			if( $fixed !== null && $kind !== 'image' && $kind !== 'content' )
				return $kind === 'href' ? self::_href( $fixed ) : self::_text( $fixed, $format );

			if( $source === '' )
				return null;

			$context = self::element( $appData );

			if( $kind === 'image' ) {

				if( $context !== null && $source[0] !== '/' && ( $context['model'][$source]['type'] ?? '' ) === 'image' ) {

					$filename = $context['element'][$source] ?? '';

					return is_string( $filename ) === true && $filename !== '' ? self::escape( $filename ) : null;
				}

				return self::escape( $source );
			}

			if( $source[0] === '/' ) {

				$fill = \Nino\Html::resolveTextfill( $appData, $source );

				// A plain value was stored with its quotes and brackets as entities
				// (\Nino\Text::sanitizeValue()); they are decoded here, so that
				// escaping them once is the only time
				if( $fill !== null && $format === 'plain' )
					$fill = html_entity_decode( $fill, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				if( $kind === 'href' )
					return self::_href( $fill ?? $source );

				return $fill === null ? null : self::_text( $fill, $format );
			}

			if( $context !== null ) {

				if( $source === '.id' )
					return $kind === 'href' ? self::_href( (string) $context['id'] ) : (string) $context['id'];

				if( $source === '.uri' )
					return $kind === 'href' ? self::_href( (string) ( $context['element']['.uri'] ?? '' ) ) : self::escape( (string) ( $context['element']['.uri'] ?? '' ) );

				if( isset( $context['model'][$source] ) === true ) {

					$field = $context['element'][$source] ?? '';

					if( is_scalar( $field ) === false )
						return null;

					return $kind === 'href' ? self::_href( (string) $field ) : \Nino\Html::fieldValue( $field, is_array( $context['model'][$source] ) === true ? $context['model'][$source] : [] );
				}
			}

			// An address that is no key is itself - the '#contact' of a button.
			// Anything else names a field there is none of, or an element there
			// is none of
			if( $kind === 'href' && in_array( $source, [ '.id', '.uri' ], true ) === false )
				return self::_href( $source );

			return null;
		}

		/**
		 *	A text made safe to write into html, by the format it may hold
		 *
		 *	@param		string		$text
		 *	@param		string		$format				plain, or the format to sanitize it to
		 *
		 *	@return 	string
		 */
		private static function _text( string $text, string $format ): string {

			if( in_array( $format, [ 'inline', 'lines', 'blocks' ], true ) === false )
				return self::escape( $text );

			return str_replace( '[', '&#91;', \Nino\Html::sanitizeHtml( $text, $format ) );
		}

		/**
		 *	An address, escaped - or a '#' where its scheme is none a link may
		 *	take. What a site's own address looks like, a path or an anchor or
		 *	one of the four schemes, passes; a protocol-relative one does not
		 *
		 *	@param		string		$href
		 *
		 *	@return 	string
		 */
		private static function _href( string $href ): string {

			$href = trim( str_replace( [ "\r", "\n", "\t" ], '', $href ) );

			if( $href === '' )
				return '';

			// A browser drops a control character in front of a scheme before it
			// reads the address, so none is allowed to stand anywhere in it
			if( preg_match( '/[\x00-\x1F\x7F]/', $href ) === 1 )
				return '#';

			if( str_starts_with( $href, '//' ) === true || str_starts_with( $href, '/\\' ) === true || str_starts_with( $href, '\\' ) === true )
				return '#';

			if( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $href ) === 1 && preg_match( '#^(https?|mailto|tel):#i', $href ) !== 1 )
				return '#';

			return self::escape( $href );
		}

		/**
		 *	A string made safe to write into html text or into an attribute:
		 *	escaped, and with every '[' as &#91;, which is what keeps a value
		 *	from being read as a fill or a shortcode in the next rendering
		 *	pass. What a component of a project's own writes a value of its
		 *	own with
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		public static function escape( string $value ): string {
			return str_replace( '[', '&#91;', htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
		}

		/**
		 *	The element a stack is rendering the cell of at this moment, with
		 *	the type's model beside it - null anywhere else
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							[ 'uri' => the type's uri, 'id' => the number in the loop,
		 *																	'element' => the element, 'model' => the type's model ]
		 */
		public static function element( array &$appData ): ?array {
			return is_array( $appData[self::CONTEXT] ?? null ) === true ? $appData[self::CONTEXT] : null;
		}

		/**
		 *	The loop of every stack: the elements of the type the first
		 *	argument names, looked up, sorted, handed to the callback and cut
		 *	exactly as [elements] does it, and then each one rendered into a
		 *	cell. The content of the stack is rendered once per element,
		 *	here, with the element as the context components read - so that
		 *	no shortcode is left in what this returns (see element() and
		 *	value()). The context is restored after each cell: a stack in a
		 *	template that a stack includes leaves the outer one as it found
		 *	it. A stack directly inside another is not supported - the
		 *	shortcode reads the content up to the first closing tag.
		 *
		 *	With a grid, each cell is told the classes of its width in the
		 *	viewports (nino-grid-s-, -m- and -l-, from 'cols'), nino-autoheight
		 *	and its group if 'autoheight' is set; without, nothing. The cell
		 *	function dresses it, and puts those on the element it draws as
		 *	the cell - cell() does that.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					What a stack's callback is given: source (the type's uri),
		 *																	content, grid, id and the attributes of the loop and the grid
		 *	@param		callable	$cell					( string $inner, array $element, int $index, array $cell ): string -
		 *																	$cell is [ 'class' => ..., 'attributes' => ... ], both empty
		 *																	without a grid
		 *
		 *	@return 	string									The cells, '' where there is no element
		 */
		public static function renderStack( array &$appData, array $args, callable $cell ): string {

			$source = (string) ( $args['source'] ?? $args[0] ?? '' );

			if( $source === '' )
				return '';

			$typeUri	= '/'. trim( $source, '/' );
			$content	= (string) ( $args['content'] ?? '' );

			$elements = \Nino\Modules\Elements::queryLoop( $appData, [
				$typeUri,
				'locale'		=> $args['locale'] ?? '',
				'callback'	=> $args['callback'] ?? '',
				'sort'			=> $args['sort'] ?? '',
				'offset'		=> $args['offset'] ?? 0,
				'limit'			=> $args['limit'] ?? 0,
				'query'			=> $args['query'] ?? '',
			] );

			if( $elements === [] )
				return '';

			$model	= \Nino\Elements::getElementModel( $appData, $typeUri );
			$info		= [ 'class' => '', 'attributes' => '' ];

			if( ( $args['grid'] ?? false ) === true ) {

				$widths	= self::_cols( (string) ( $args['cols'] ?? self::COLS ) );
				$info['class'] = 'nino-grid-s-'. $widths[0]. ' nino-grid-m-'. $widths[1]. ' nino-grid-l-'. $widths[2];

				if( ( $args['autoheight'] ?? '0' ) === '1' || ( $args['autoheight'] ?? false ) === true ) {

					$group = self::_id( (string) ( $args['id'] ?? '' ) );

					if( $group === '' ) {
						$appData[self::COUNTER] = (int) ( $appData[self::COUNTER] ?? 0 ) + 1;
						$group = 'stack-'. $appData[self::COUNTER];
					}

					$info['class'] .= ' nino-autoheight';
					$info['attributes'] = ' data-autoheight-group="'. $group. '"';
				}
			}

			$html = '';

			foreach( array_values( $elements ) as $index => $element ) {

				$previous = $appData[self::CONTEXT] ?? null;

				$appData[self::CONTEXT] = [ 'uri' => $typeUri, 'id' => $index, 'element' => $element, 'model' => $model ];

				try {
					$inner = \Nino\Html::renderHtml( $appData, $content );
				}
				finally {
					if( $previous === null )
						unset( $appData[self::CONTEXT] );
					else
						$appData[self::CONTEXT] = $previous;
				}

				$html .= $cell( $inner, is_array( $element ) === true ? $element : [], $index, $info );
			}

			return $html;
		}

		/**
		 *	The widths of a cell in the three viewports from a stack's 'cols':
		 *	three of the widths Nino.css has, or one for all of them. What is
		 *	not one is the default of its place - it goes into a class name
		 *
		 *	@param		string		$cols
		 *
		 *	@return 	array										Three widths, as strings
		 */
		private static function _cols( string $cols ): array {

			$given		= preg_split( '/\s+/', trim( $cols ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
			$defaults	= explode( ' ', self::COLS );

			if( count( $given ) === 1 )
				$given = [ $given[0], $given[0], $given[0] ];

			$widths = [];

			foreach( $defaults as $place => $default )
				$widths[] = in_array( $given[$place] ?? '', self::WIDTHS, true ) === true ? $given[$place] : $default;

			return $widths;
		}

		/**
		 *	An id a stack may carry: a slug that starts with a letter, or ''
		 *
		 *	@param		string		$id
		 *
		 *	@return 	string
		 */
		private static function _id( string $id ): string {
			return preg_match( '/^[a-z][a-z0-9_-]*$/i', $id ) === 1 ? $id : '';
		}

		/**
		 *	One cell of a stack as an element: the dressing renderStack() hands
		 *	the cell function, a class and attributes of the cell function's
		 *	own added to it
		 *
		 *	@param		array			$cell					The last argument of the cell function
		 *	@param		string		$inner				The rendered content
		 *	@param		string		$class				Classes of its own, space first not needed
		 *	@param		string		$attributes		Attributes of its own, with a space in front of each
		 *
		 *	@return 	string
		 */
		public static function cell( array $cell, string $inner, string $class = '', string $attributes = '' ): string {

			$classes = trim( (string) ( $cell['class'] ?? '' ). ' '. $class );

			return str_replace(
				[ '[[class]]', '[[attributes]]', '[[inner]]' ],
				[ $classes !== '' ? ' class="'. $classes. '"' : '', (string) ( $cell['attributes'] ?? '' ). $attributes, $inner ],
				self::$html['cell']
			);
		}

		/**
		 *	A modifier of a class, written the way a template writes it: the
		 *	word behind a prefix, a space in front - and nothing for no word
		 *
		 *	@param		string		$prefix
		 *	@param		string		$word
		 *
		 *	@return 	string
		 */
		private static function _modifier( string $prefix, string $word ): string {
			return $word === '' ? '' : ' '. $prefix. self::escape( $word );
		}

		/**
		 *	Replace [title]: the heading of a section
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentTitle( array &$appData, array $args ): string {
			return str_replace(
				[ '[[level]]', '[[style]]', '[[class]]', '[[value]]' ],
				[ $args['level'], self::_modifier( 'nino-section-title--', $args['style'] ), self::_modifier( '', $args['class'] ), $args['value'] ],
				self::$html['title']
			);
		}

		/**
		 *	Replace [subtitle]: the line under a heading
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentSubtitle( array &$appData, array $args ): string {
			return str_replace(
				[ '[[style]]', '[[class]]', '[[value]]' ],
				[ self::_modifier( 'nino-section-subtitle--', $args['style'] ), self::_modifier( '', $args['class'] ), $args['value'] ],
				self::$html['subtitle']
			);
		}

		/**
		 *	Replace [text]: the rich text of a key or of a field, in the
		 *	format the attribute names - a field keeps the one its type
		 *	declares. The value is what \Nino\Html::sanitizeHtml() left of
		 *	it, a text key's value read again as that format, so the wrapper
		 *	carries .nino-richtext where it holds paragraphs and lists
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentText( array &$appData, array $args ): string {
			return str_replace(
				[ '[[richtext]]', '[[style]]', '[[class]]', '[[value]]' ],
				[ $args['format'] === 'blocks' ? ' nino-richtext' : '', self::_modifier( 'nino-section-text--', $args['style'] ), self::_modifier( '', $args['class'] ), $args['value'] ],
				self::$html['text']
			);
		}

		/**
		 *	Replace [image]: the picture of a slot - or in a stack of an image
		 *	field - as an <img>, or as what the content between [image] and
		 *	[/image] makes of it. Everything the shortcode did before it was a
		 *	component it still does: [image hero], [image uri="hero" alt="..."]
		 *	and the content form.
		 *
		 *	With content - [image logo]...[/image] - that content is what is
		 *	rendered instead of the <img>, and again only when the slot has an
		 *	image: [[src]] (the path of the file, from the site's root - an
		 *	absolute address is "https://[[/project/website/general/url]][[src]]"), [[width]],
		 *	[[height]] and [[alt]] are filled in with the same values the <img>
		 *	gets. That is how a place that needs the address and not a picture
		 *	- a meta tag, a mail - is written without being left as an empty
		 *	tag, or a broken one, where nothing is uploaded yet.
		 *
		 *	The alt text is, in this order: the one stored for the slot in
		 *	the current language (the Images panel keeps it; for an image
		 *	field the one in the field the type names for it), the template's
		 *	own alt="...", and none - alt="", which is how a decorative picture
		 *	is written. The slot's label is not an alt text and is not used as
		 *	one. Whichever it is, it is output with '[' turned into &#91; as
		 *	well as escaped: shortcode output is rendered once more, and an
		 *	alt text an editor wrote must not be able to open a fill or a
		 *	shortcode there.
		 *
		 *	A focus, a ratio or a class of its own puts the <img> in a frame,
		 *	<div class="nino-image ...">, which crops it as Nino.css has it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentImage( array &$appData, array $args ): string {

			$source		= (string) $args['source'];
			$context	= self::element( $appData );
			$field		= $context !== null && $source !== '' && $source[0] !== '/' && ( $context['model'][$source]['type'] ?? '' ) === 'image';

			if( $field === true ) {

				// The value is the reference made safe to write; the lookup takes
				// what the field holds
				$url		= \Nino\Images::getUrl( $appData, (string) ( $context['element'][$source] ?? '' ) );
				$width	= (int) ( $context['model'][$source]['width'] ?? 0 );
				$height	= (int) ( $context['model'][$source]['height'] ?? 0 );
				$link		= $context['model'][$source]['alt'] ?? null;
				$stored	= is_string( $link ) === true ? ( $context['element'][$link] ?? '' ) : '';
			}
			else {

				$slot	= \Nino\Images::getSlot( $appData, $source );

				if( $slot === false || empty( $slot['filename'] ) === true )
					return '';

				$url		= \Nino\Images::getUrl( $appData, $slot['filename'] );
				$width	= (int) ( $slot['width'] ?? 0 );
				$height	= (int) ( $slot['height'] ?? 0 );
				$stored	= $slot['alt'][ \Nino\Locales::getCurrentLocale( $appData ) ] ?? '';
			}

			$alt = ( is_string( $stored ) === true && $stored !== '' ) ? $stored : $args['alt'];

			$content = trim( $args['content'] ) !== '' ? $args['content'] : null;

			$img = str_replace(
				[ '[[src]]', '[[width]]', '[[height]]', '[[alt]]' ],
				[ self::escape( $url ), (string) $width, (string) $height, self::escape( $alt ) ],
				$content ?? self::$html['img']
			);

			if( $content !== null || ( $args['focus'] === '' && $args['ratio'] === '' && $args['class'] === '' ) )
				return $img;

			return str_replace(
				[ '[[ratio]]', '[[focus]]', '[[class]]', '[[img]]' ],
				[ self::_modifier( 'nino-image--', $args['ratio'] ), self::_modifier( 'nino-img-focus--', $args['focus'] ), self::_modifier( '', $args['class'] ), $img ],
				self::$html['image']
			);
		}

		/**
		 *	Replace [button]: a link drawn as a button. The label is the
		 *	source - a key, a field or text="..."; the address is the href
		 *	attribute, and where that is not given and the label is a fixed
		 *	one, the first argument is: [button .uri text="More"] is a button
		 *	"More" to the uri of the element
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentButton( array &$appData, array $args ): string {

			$target = $args['href'] !== '' ? $args['href'] : ( isset( $args['text'] ) === true ? $args['source'] : '' );
			$href		= $target !== '' ? self::value( $appData, $target, 'href' ) : null;

			return str_replace(
				[ '[[style]]', '[[size]]', '[[class]]', '[[href]]', '[[target]]', '[[value]]' ],
				[
					self::_modifier( 'nino-btn--', $args['style'] ),
					self::_modifier( 'nino-btn--', $args['size'] ),
					self::_modifier( '', $args['class'] ),
					$href !== null && $href !== '' ? ' href="'. $href. '"' : '',
					$args['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '',
					$args['value'],
				],
				self::$html['button']
			);
		}

		/**
		 *	Replace [html]: the content between [html] and [/html], as rich
		 *	text - paragraphs, lists and the inline tags, what
		 *	\Nino\Html::sanitizeHtml() keeps of it in the format blocks. The
		 *	content is the template's own, not a value somebody entered, so
		 *	its '[' stay: a shortcode written in it is rendered with the rest
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentHtml( array &$appData, array $args ): string {
			return \Nino\Html::sanitizeHtml( (string) $args['value'], 'blocks' );
		}

		/**
		 *	Replace [spacer]: empty room between two components
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentSpacer( array &$appData, array $args ): string {
			return str_replace( [ '[[size]]', '[[class]]' ], [ $args['size'], self::_modifier( '', $args['class'] ) ], self::$html['spacer'] );
		}

		/**
		 *	Replace [stack]: a stack of the elements of a type, one cell each,
		 *	in a grid row nested where the stack stands. The gap is a class of
		 *	the row, nino-stack-gap-<n>, and the cells carry the widths of 'cols'
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function stackStack( array &$appData, array $args ): string {

			$cells = self::renderStack( $appData, $args, static function( string $inner, array $element, int $index, array $cell ): string {
				return self::cell( $cell, $inner );
			} );

			return $cells === '' ? '' : str_replace(
				[ '[[gap]]', '[[class]]', '[[id]]', '[[cells]]' ],
				[ ' nino-stack-gap-'. $args['gap'], self::_modifier( '', $args['class'] ), self::_attribute( 'id', self::_id( $args['id'] ) ), $cells ],
				self::$html['stack']
			);
		}

		/**
		 *	Replace [slider]: the cells as the slides of a .nino-slider, which
		 *	Nino.ui.js gives its controls and its touch. The slides are list
		 *	items, as it expects them; the width of one is 'width', a share of
		 *	the slider or pixels, and none is narrower than 'min'
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function stackSlider( array &$appData, array $args ): string {

			$cells = self::renderStack( $appData, $args, static function( string $inner ): string {
				return str_replace( '[[inner]]', $inner, self::$html['slide'] );
			} );

			$size = static fn( string $value ): string => preg_match( '/^\d{1,4}(?:%|px)$/', $value ) === 1 ? $value : '';

			return $cells === '' ? '' : str_replace(
				[ '[[class]]', '[[id]]', '[[width]]', '[[min]]', '[[cells]]' ],
				[ self::_modifier( '', $args['class'] ), self::_attribute( 'id', self::_id( $args['id'] ) ), self::_attribute( 'data-slider-width', $size( $args['width'] ) ), self::_attribute( 'data-slider-min', $size( $args['min'] ) ), $cells ],
				self::$html['slider']
			);
		}

		/**
		 *	Replace [filter]: the cells with a row of buttons above them, which
		 *	Nino.ui.js turns into a filter. The buttons are the values the
		 *	field 'by' holds across the type's elements, found as
		 *	[elementvalues] finds them, and each cell carries its element's
		 *	value of it. Without 'by' there is no row, and so nothing to filter
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function stackFilter( array &$appData, array $args ): string {

			$by = $args['by'];

			$count = 0;

			$cells = self::renderStack( $appData, $args, static function( string $inner, array $element, int $index, array $cell ) use ( $by, &$count ): string {

				$count++;

				$value = is_scalar( $element[$by] ?? null ) === true ? (string) $element[$by] : '';

				return self::cell( $cell, $inner, 'nino-filter-item', ' data-filter-item="'. \Nino\Html::fieldValue( $value, [] ). '"' );
			} );

			if( $cells === '' )
				return '';

			$nav = '';

			if( $by !== '' ) {

				$buttons = \Nino\Modules\Elements::doShortcodeElementValues( $appData, [
					'/'. trim( $args['source'], '/' ),
					'key'			=> $by,
					'query'		=> $args['query'],
					'locale'	=> $args['locale'],
					'content'	=> self::$html['filter-btn'],
				] );

				if( $buttons !== '' )
					$nav = str_replace(
						[ '[[label]]', '[[buttons]]' ],
						[ 'Filter', str_replace( [ '[[label]]', '[[count]]' ], [ self::escape( self::_all( $appData, $args['all'] ) ), (string) $count ], self::$html['filter-all'] ). $buttons ],
						self::$html['filter-nav']
					);
			}

			return str_replace(
				[ '[[class]]', '[[id]]', '[[gap]]', '[[nav]]', '[[cells]]' ],
				[ self::_modifier( '', $args['class'] ), self::_attribute( 'id', self::_id( $args['id'] ) ), ' nino-stack-gap-'. $args['gap'], $nav, $cells ],
				self::$html['filter']
			);
		}

		/**
		 *	What the button that shows everything says: the attribute, else
		 *	the text /template/common/filter/all where a project has written
		 *	it, else the word of the page's language - the order the labels
		 *	of a slider are taken in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$given				The 'all' attribute
		 *
		 *	@return 	string
		 */
		private static function _all( array &$appData, string $given ): string {

			if( $given !== '' )
				return $given;

			$own = \Nino\Html::resolveTextfill( $appData, '/template/common/filter/all' );

			if( $own !== null && $own !== '' )
				return html_entity_decode( $own, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			return str_starts_with( \Nino\Locales::getCurrentLocale( $appData ), 'de' ) === true ? 'Alle' : 'All';
		}

		/**
		 *	Replace [list]: the cells as the items of a .nino-list, with no
		 *	grid - a list of what the elements say. 'style' is check,
		 *	numbered or columns
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function stackList( array &$appData, array $args ): string {

			$cells = self::renderStack( $appData, $args, static function( string $inner ): string {
				return str_replace( '[[inner]]', $inner, self::$html['item'] );
			} );

			return $cells === '' ? '' : str_replace(
				[ '[[style]]', '[[class]]', '[[id]]', '[[cells]]' ],
				[ self::_modifier( 'nino-list--', $args['style'] ), self::_modifier( '', $args['class'] ), self::_attribute( 'id', self::_id( $args['id'] ) ), $cells ],
				self::$html['list']
			);
		}

		/**
		 *	An attribute for a tag, with a space in front - and nothing for an
		 *	empty value
		 *
		 *	@param		string		$name
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		private static function _attribute( string $name, string $value ): string {
			return $value === '' ? '' : ' '. $name. '="'. self::escape( $value ). '"';
		}
	}

}
