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
				'title'				=> '<[[tag]] class="nino-section-title[[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'card-title'	=> '<[[tag]] class="nino-card-title[[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'hero-title'	=> '<[[tag]] class="nino-hero-title[[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'subtitle'		=> '<[[tag]] class="nino-section-subtitle[[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'card-subtitle'	=> '<[[tag]] class="nino-card-subtitle[[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'hero-subtitle'	=> '<[[tag]] class="nino-hero-subtitle[[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'text'				=> '<[[tag]] class="[[base]][[richtext]][[style]][[color]][[class]]"[[lang]]>[[value]]</[[tag]]>',
				'img'					=> '<img src="[[src]]" width="[[width]]" height="[[height]]" alt="[[alt]]"[[class]]>',
				'image'				=> '<div class="nino-image[[ratio]][[focus]][[class]]">[[img]]</div>',
				'logo'				=> '<span class="nino-logo-set[[class]]">[[light]][[dark]]</span>',
				'button'			=> '<a class="nino-btn[[style]][[size]][[width]][[class]]"[[href]][[target]][[lang]]>[[value]]</a>',
				'button-span'	=> '<span class="nino-btn[[style]][[size]][[width]][[class]]"[[lang]]>[[value]]</span>',
				'link'				=> '<a[[class]][[href]][[target]]>[[inner]]</a>',
				'scroll-hint'	=> '<a class="nino-hero-arrowdown[[class]]" href="#[[to]]"[[next]] aria-label="[[/template/common/navigation/next-section]]"></a>',
				'spacer'			=> '<div class="nino-spacer nino-spacer-[[size]][[class]]"></div>',
				'card'				=> '<article class="nino-card[[ground]][[dim]][[borderless]][[fill]][[linked]][[vertical]][[align]][[padding]][[class]]">[[background]][[inner]]</article>',
				'card-bg'			=> '<div class="nino-card-bg[[focus]]">[[img]]</div>',
				'card-content'	=> '<div class="nino-card-content">[[inner]]</div>',
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
				'table'				=> '<div class="nino-table-wrap"><table class="nino-table[[style]][[class]]"[[id]]>[[head]]<tbody>[[cells]]</tbody></table></div>',
				'table-head'	=> '<thead><tr>[[titles]]</tr></thead>',
				'th'					=> '<th>[[value]]</th>',
				'row'					=> '<tr>[[inner]]</tr>',
				'td'					=> '<td[[class]]>[[value]]</td>',
				'line'				=> '<[[tag]] class="nino-line[[class]]"[[lang]]>[[value]]</[[tag]]>',
				'sections'		=> '<section[[id]] class="nino-section[[class]]">[[inner]]</section>',
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

		// The lists the methods named by an attribute's 'options' have answered
		// in this request, one entry for each component or stack and attribute:
		// the method asked and what it answered, null where it answered nothing
		// that can be read - see _resolve()
		private const string OPTIONS = './nino/components/options';

		// What a variant's word is: lower case letters and digits, hyphens
		// between them
		private const string WORD = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

		// What a component takes its first argument from, see value()
		public const array SOURCES = [ 'text', 'image', 'href', 'content', 'none' ];

		// The words a bool is read from: an attribute of a component here, and a
		// flag of a shortcode without a schema in \Nino\Feature::flag() - one
		// table, so that the two cannot come to mean different things by "yes".
		// The script that reads a flag off a data attribute, Nino.ui.options(),
		// carries a copy of the two lists (ON and OFF in Nino.ui.js): the three
		// must stay equal
		public const array ON = [ '1', 'true', 'yes', 'on' ];
		public const array OFF = [ '0', 'false', 'no', 'off' ];

		// What an attribute may be: the types of a feature's settings that
		// make sense here, and four of this module's own - a text key, an
		// image slot, an address and a variant: a select whose words are the
		// modifiers of a class, which the renderer writes as that class (see
		// _variant())
		public const array TYPES = [ 'string', 'int', 'bool', 'select', 'variant', 'lines', 'key', 'image', 'href' ];

		// How a tool may draw an attribute, by the type that takes the hint -
		// the 'input' of an attribute: a choice of words as a select, as a row of
		// buttons or as the icons of an alignment, a flag as a switch or as a
		// checkbox. A hint for the tool and nothing the rendering reads
		public const array INPUTS = [
			'select'	=> [ 'select', 'segment', 'icons' ],
			'variant'	=> [ 'select', 'segment', 'icons' ],
			'bool'		=> [ 'switch', 'checkbox' ],
		];

		// The tabs of a tool's dialog an attribute may ask to be drawn on - the 'tab' of an
		// attribute, where the others stand among the properties: the colour of a card,
		// its picture, the focus and the dimming of that picture belong on the tab of the
		// background. The registry owns the list, so that a tool draws the attributes of
		// a tab together and knows no component by name. A hint for the tool and nothing
		// the rendering reads
		public const array TABS = [ 'background' ];

		// The words a tool has an icon for, by alignment: where a text stands,
		// where the content stands in a box - last-bottom is the card's, the
		// last element at the bottom - and where a stack places what is in it.
		// The options of an attribute drawn as icons are the empty word and
		// words of one of them
		private const array ALIGNMENTS = [
			[ 'left', 'center', 'right' ],
			[ 'top', 'middle', 'bottom', 'last-bottom' ],
			[ 'start', 'center', 'end' ],
		];

		// The pictures the Builder draws a component or a stack as - a fixed
		// list, so that no feature can break the preview with one of its own
		public const array PREVIEWS = [ 'title', 'text', 'image', 'button', 'block', 'cells', 'table' ];

		// Where a component stands, and so where a tool offers it - see validate():
		// directly in a column of a section (the cell of a stack that holds no
		// card is such a column), in a [card], directly in a column of the
		// hero, or between the tags of a [table], which holds its [col]s and
		// nothing else
		public const array PLACES = [ 'section', 'card', 'hero', 'table' ];

		// The group a tool offers a component under, the one word of its schema's
		// 'group' - see validate(): the components of a section, of the hero and
		// of a card, and those that stand anywhere, in the order a tool lists
		// them. A place says where a component may stand and a group where it is
		// offered: the title of a hero may stand in a section as well, and is
		// offered with the hero
		public const array GROUPS = [ 'section', 'hero', 'card', 'global' ];

		// What an icon is made of - see validate(): the tags a part may be, each
		// with the attributes it takes (a part holds all of them and none other;
		// the radii of a rect may be left out, ICON_OPTIONAL); how a value is
		// written, how long it may be and what a message calls it - a number,
		// unless it is the d of a path or the points of a polyline or a polygon;
		// and how many parts an icon has and how many characters its values have
		// together
		private const array ICON_TAGS = [
			'path'		=> [ 'd' ],
			'circle'	=> [ 'cx', 'cy', 'r' ],
			'ellipse'	=> [ 'cx', 'cy', 'rx', 'ry' ],
			'rect'		=> [ 'x', 'y', 'width', 'height', 'rx', 'ry' ],
			'line'		=> [ 'x1', 'y1', 'x2', 'y2' ],
			'polyline'	=> [ 'points' ],
			'polygon'	=> [ 'points' ],
		];
		private const array ICON_OPTIONAL = [ 'rect' => [ 'rx', 'ry' ] ];
		private const array ICON_VALUES = [
			'number'	=> [ '/^-?\d+(\.\d+)?$/D', 12, 'a number' ],
			'd'			=> [ '/^[MmLlHhVvCcSsQqTtAaZz0-9 .,-]+$/D', 512, 'a path' ],
			'points'	=> [ '/^[0-9 .,-]+$/D', 256, 'a list of points' ],
		];
		private const int ICON_PARTS = 12;
		private const int ICON_LENGTH = 2048;

		// The widths a cell may have in a grid, as Nino.css has them
		private const array WIDTHS = [ '25', '33', '50', '66', '75', '100' ];

		// The components whose text is a line of free text with [[field]] in it, and
		// not a fixed text: dispatch() leaves its fills to _line()
		private const array LINES = [ 'line', 'col' ];

		// Set while a table renders its rows: the one place a [col] draws something
		private const string TABLE = './nino/components/table';

		// What a slot of a stack's grid is, when the shortcode does not say
		private const string COLS = '100 50 33';

		// Names an attribute may not take: the arguments the wrapper hands the
		// callback besides the attributes, and the ones it reads itself
		private const array RESERVED = [ 'value', 'source', 'content', 'text', 'uri', 'grid' ];

		// The attributes every stack has, as a schema's attributes are written:
		// the loop of [elements] and the id that filter, autoheight and slider
		// find a stack by - and, for a stack with a grid, what its cells are:
		// their widths, the gap between them and whether they are one height.
		// What a cell looks like is not the stack's business: put a [card] in
		// its content
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

		// The two attributes of a component that says 'link' => true, written
		// as a schema's attributes are: where the link goes and where it
		// opens - the button's own, which is why the button takes them from here.
		// They stand after the attributes the schema declares and before the class
		private const array LINK = [
			'href'				=> [ 'type' => 'href', 'default' => '', 'label' => [ 'en_US' => 'Link', 'de_DE' => 'Ziel' ],
				'hint' => [ 'en_US' => 'A text key, an address, or in a stack a field of the element.', 'de_DE' => 'Ein Textschlüssel, eine Adresse oder in einem Stapel ein Feld des Elements.' ] ],
			'target'			=> [ 'type' => 'select', 'options' => [ '', '_blank' ], 'default' => '', 'label' => [ 'en_US' => 'Opens in', 'de_DE' => 'Öffnet in' ] ],
		];

		// The icons of the kernel's components: ten icons of Lucide 0.577.0
		// (lucide.dev, ISC - see THIRD-PARTY-NOTICES.md) under the names Lucide
		// gives them. Each is 24 by 24 and drawn with a stroke by the tool that
		// shows it, and is kept as parts - the elements inside the <svg> of its
		// file, each a tag and its attributes as the file spells them, in its
		// order - and not as markup, which is the shape AGENTS.md, "Markup
		// belongs in a template", wants an icon in: the tool builds the elements
		// from the parts it is handed
		private const array SHAPES = [
			'heading' => [
				[ 'path', [ 'd' => 'M6 12h12' ] ],
				[ 'path', [ 'd' => 'M6 20V4' ] ],
				[ 'path', [ 'd' => 'M18 20V4' ] ],
			],
			'type' => [
				[ 'path', [ 'd' => 'M12 4v16' ] ],
				[ 'path', [ 'd' => 'M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2' ] ],
				[ 'path', [ 'd' => 'M9 20h6' ] ],
			],
			'text-align-start' => [
				[ 'path', [ 'd' => 'M21 5H3' ] ],
				[ 'path', [ 'd' => 'M15 12H3' ] ],
				[ 'path', [ 'd' => 'M17 19H3' ] ],
			],
			'panel-top' => [
				[ 'rect', [ 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '3', 'rx' => '2' ] ],
				[ 'path', [ 'd' => 'M3 9h18' ] ],
			],
			'mouse-pointer-click' => [
				[ 'path', [ 'd' => 'M14 4.1 12 6' ] ],
				[ 'path', [ 'd' => 'm5.1 8-2.9-.8' ] ],
				[ 'path', [ 'd' => 'm6 12-1.9 2' ] ],
				[ 'path', [ 'd' => 'M7.2 2.2 8 5.1' ] ],
				[ 'path', [ 'd' => 'M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z' ] ],
			],
			'image' => [
				[ 'rect', [ 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '3', 'rx' => '2', 'ry' => '2' ] ],
				[ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '2' ] ],
				[ 'path', [ 'd' => 'm21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21' ] ],
			],
			'code' => [
				[ 'path', [ 'd' => 'm16 18 6-6-6-6' ] ],
				[ 'path', [ 'd' => 'm8 6-6 6 6 6' ] ],
			],
			'separator-horizontal' => [
				[ 'path', [ 'd' => 'm16 16-4 4-4-4' ] ],
				[ 'path', [ 'd' => 'M3 12h18' ] ],
				[ 'path', [ 'd' => 'm8 8 4-4 4 4' ] ],
			],
			'chevron-down' => [
				[ 'path', [ 'd' => 'm6 9 6 6 6-6' ] ],
			],
			'contrast' => [
				[ 'circle', [ 'cx' => '12', 'cy' => '12', 'r' => '10' ] ],
				[ 'path', [ 'd' => 'M12 18a6 6 0 0 0 0-12v12z' ] ],
			],
		];

		// Which of them each component carries: the three titles are one heading,
		// the three subtitles one line of type, the two texts the lines of a text,
		// the two images one picture, the logo the circle that is half light and
		// half dark and the scroll hint the chevron it draws
		private const array ICONS = [
			'title'			=> 'heading',
			'subtitle'		=> 'type',
			'card-title'	=> 'heading',
			'card-subtitle'	=> 'type',
			'card-text'		=> 'text-align-start',
			'card-image'	=> 'image',
			'hero-title'	=> 'heading',
			'hero-subtitle'	=> 'type',
			'text'			=> 'text-align-start',
			'card'			=> 'panel-top',
			'button'		=> 'mouse-pointer-click',
			'image'			=> 'image',
			'logo'			=> 'contrast',
			'html'			=> 'code',
			'spacer'		=> 'separator-horizontal',
			'scroll-hint'	=> 'chevron-down',
			'line'			=> 'text-align-start',
			'col'				=> 'panel-top',
		];

		/**
		 *	Module initiating: the components and the stacks the kernel brings,
		 *	and then those of the active features that declare them in their
		 *	manifest.
		 *
		 *	The components are registered in the order a column offers them, because
		 *	the registry's order is the one a tool shows: components() answers it as
		 *	it stands, and a tool that lists what can be added to a column knows no
		 *	component by name. So the title, the subtitle and the text of a section
		 *	come first, then the title, the subtitle, the text and the image of a
		 *	card, then the title and the subtitle of a hero, and then the card, the
		 *	button, the image, the logo, the html, the spacer and the scroll hint - and for any
		 *	one place ('in') that is the order its column has. The components of a
		 *	feature follow these.
		 *
		 *	Each of the kernel's components carries an icon, so that the registry that
		 *	lists them hands a tool the picture to show each by: ICONS names the shape
		 *	it takes and SHAPES holds the parts of it. A stack has none. Ten of them
		 *	can be a link ('link', see _linked()); each is the kernel's ('by') and is
		 *	offered under one of the four groups ('group', see GROUPS), as a
		 *	feature's are its own and are offered under the feature.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$components = [
				[ 'title', 'Title (Section)', 'Titel (Sektion)', 'title', 'text', [ 'tag' => self::_tag( [ 'div', 'h2', 'h3', 'h4' ], 'h3' ), 'style' => self::_style(), 'color' => self::_color() ], [ 'hero', 'section' ], '{tag} .nino-section-title', null, true, 'section' ],
				[ 'subtitle', 'Subtitle (Section)', 'Untertitel (Sektion)', 'title', 'text', [ 'tag' => self::_tag( [ 'div', 'p', 'h4', 'h5' ], 'div' ), 'style' => self::_style(), 'color' => self::_color() ], [ 'hero', 'section' ], '{tag} .nino-section-subtitle', null, true, 'section' ],
				[ 'text', 'Text', 'Text', 'text', 'text', [
					'format' => [ 'type' => 'select', 'options' => [ 'inline', 'lines', 'blocks' ], 'default' => 'blocks', 'input' => 'segment', 'label' => [ 'en_US' => 'Format', 'de_DE' => 'Format' ] ],
					'tag' => self::_tag( [ 'p', 'div', 'span' ], 'p' ) + [ 'hint' => [ 'en_US' => 'In the format blocks, which holds paragraphs and lists, the text is always a div.', 'de_DE' => 'Im Format blocks, das Absätze und Listen enthält, ist der Text immer ein div.' ] ],
					'style' => self::_style(),
					'color' => self::_color(),
				], null, null, null, true, 'global' ],
				// A line of text that fills the fields of the element it stands for: the
				// text is free text with [[field]] in it, see _line(). It is the row of a list
				[ 'line', 'Line of fields', 'Zeile aus Feldern', 'text', 'text', [
					'tag' => self::_tag( [ 'p', 'span', 'div' ], 'p' ),
				], null, null, null, false, 'global' ],
				[ 'card-title', 'Title (Card)', 'Titel (Karte)', 'title', 'text', [ 'tag' => self::_tag( [ 'div', 'h3', 'h4' ], 'h4' ), 'style' => self::_style(), 'color' => self::_color() ], [ 'card' ], '{tag} .nino-card-title', null, true, 'card' ],
				[ 'card-subtitle', 'Subtitle (Card)', 'Untertitel (Karte)', 'title', 'text', [ 'tag' => self::_tag( [ 'div', 'p', 'h5', 'h6' ], 'p' ), 'style' => self::_style(), 'color' => self::_color() ], [ 'card' ], '{tag} .nino-card-subtitle', null, true, 'card' ],
				[ 'card-text', 'Text (Card)', 'Text (Karte)', 'text', 'text', [
					'format' => [ 'type' => 'select', 'options' => [ 'inline', 'lines', 'blocks' ], 'default' => 'blocks', 'input' => 'segment', 'label' => [ 'en_US' => 'Format', 'de_DE' => 'Format' ] ],
					'style' => self::_style(),
					'color' => self::_color(),
				], [ 'card' ], 'div .nino-card-descr', null, true, 'card' ],
				[ 'card-image', 'Image (Card)', 'Bild (Karte)', 'image', 'image', [
					'style' => [ 'type' => 'variant', 'options' => [ '', 'inline' ], 'prefix' => 'nino-card-img--', 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
					'alt' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Alternative text', 'de_DE' => 'Alternativtext' ],
						'hint' => [ 'en_US' => 'Used where the slot has no text of its own. Empty marks the picture as decoration.', 'de_DE' => 'Gilt, wo der Slot keinen eigenen Text hat. Leer kennzeichnet das Bild als Dekoration.' ] ],
					'focus' => [ 'type' => 'select', 'options' => [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], 'default' => '', 'label' => [ 'en_US' => 'Focus', 'de_DE' => 'Fokus' ],
						'hint' => [ 'en_US' => 'Which part of the picture survives a crop, as one of nine positions.', 'de_DE' => 'Welcher Teil des Bildes beim Zuschneiden bleibt, als eine von neun Positionen.' ] ],
					'ratio' => [ 'type' => 'select', 'options' => [ '', '1-1', '4-3', '3-2', '16-9', '21-9', '4-1', '5-1' ], 'default' => '', 'label' => [ 'en_US' => 'Ratio', 'de_DE' => 'Seitenverhältnis' ] ],
				], [ 'card' ], 'img .nino-card-img', null, true, 'card' ],
				[ 'hero-title', 'Title (Hero)', 'Titel (Hero)', 'title', 'text', [ 'tag' => self::_tag( [ 'div', 'h2', 'h3' ], 'h2' ), 'style' => self::_style(), 'color' => self::_color() ], [ 'hero', 'section' ], '{tag} .nino-hero-title', null, true, 'hero' ],
				[ 'hero-subtitle', 'Subtitle (Hero)', 'Untertitel (Hero)', 'title', 'text', [ 'tag' => self::_tag( [ 'div', 'p', 'h3', 'h4' ], 'p' ), 'style' => self::_style(), 'color' => self::_color() ], [ 'hero', 'section' ], '{tag} .nino-hero-subtitle', null, true, 'hero' ],
				// The card is the one component that holds components: what stands between
				// its tags is the calls of the components of a card, which are rendered
				// when its output is (see componentCard()), and 'holds' says so to a
				// tool. It stands once in a column and, in the content of a stack, once
				// per element
				[ 'card', 'Card', 'Karte', 'block', 'content', [
					'fill' => [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Full height', 'de_DE' => 'Volle Höhe' ],
						'hint' => [ 'en_US' => 'The card takes the whole height.', 'de_DE' => 'Die Karte nimmt die ganze Höhe ein.' ] ],
					'padding' => [ 'type' => 'select', 'options' => [ '', '0', '1', '2', '3', '4', '5', '6' ], 'default' => '', 'label' => [ 'en_US' => 'Padding', 'de_DE' => 'Padding' ] ],
					'vertical' => [ 'type' => 'select', 'options' => [ '', 'middle', 'bottom', 'last-bottom' ], 'default' => '', 'input' => 'icons', 'label' => [ 'en_US' => 'Vertical element alignment', 'de_DE' => 'Vertikale Element-Ausrichtung' ] ],
					'align' => [ 'type' => 'select', 'options' => [ '', 'left', 'center', 'right' ], 'default' => '', 'input' => 'icons', 'label' => [ 'en_US' => 'Horizontal text alignment', 'de_DE' => 'Horizontale Text-Ausrichtung' ] ],
					'ground' => [ 'type' => 'select', 'options' => [ '', 'counter', 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt' ], 'default' => '', 'tab' => 'background', 'label' => [ 'en_US' => 'Background colour', 'de_DE' => 'Hintergrund-Farbe' ] ],
					'borderless' => [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Without border', 'de_DE' => 'Ohne Rahmen' ] ],
					'linked' => [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Whole card is the link', 'de_DE' => 'Ganze Karte verlinkt' ],
						'hint' => [ 'en_US' => 'A link in the card - its title\'s, or another component\'s that may be the card\'s link - covers the whole card; text in the card cannot be selected then.', 'de_DE' => 'Ein Link in der Karte - der des Titels oder einer anderen Komponente, die der Link der Karte sein darf - gilt für die ganze Fläche; Text in der Karte lässt sich dann nicht markieren.' ] ],
					'background' => [ 'type' => 'image', 'default' => '', 'tab' => 'background', 'label' => [ 'en_US' => 'Background picture', 'de_DE' => 'Hintergrund-Bild' ],
						'hint' => [ 'en_US' => 'A slot, or in a stack an image field of the element, laid behind the content.', 'de_DE' => 'Ein Slot oder in einem Stapel ein Bildfeld des Elements, hinter den Inhalt gelegt.' ] ],
					'focus' => [ 'type' => 'select', 'options' => [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], 'default' => '', 'tab' => 'background', 'label' => [ 'en_US' => 'Focus', 'de_DE' => 'Fokus' ],
						'hint' => [ 'en_US' => 'Which part of the picture survives a crop, as one of nine positions.', 'de_DE' => 'Welcher Teil des Bildes beim Zuschneiden bleibt, als eine von neun Positionen.' ] ],
					'dim' => [ 'type' => 'bool', 'default' => false, 'tab' => 'background', 'label' => [ 'en_US' => 'Dim the picture', 'de_DE' => 'Bild abdunkeln' ] ],
				], [ 'section', 'hero' ], 'article .nino-card', 'card', false, 'card' ],
				[ 'button', 'Button', 'Button', 'button', 'text', [
					'href' => self::LINK['href'],
					'style' => [ 'type' => 'select', 'options' => [ '', 'primary', 'outline', 'light', 'dark', 'brand-alt' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
					'size' => [ 'type' => 'select', 'options' => [ '', 'small', 'big' ], 'default' => '', 'input' => 'segment', 'label' => [ 'en_US' => 'Size', 'de_DE' => 'Größe' ] ],
					'width' => [ 'type' => 'select', 'options' => [ '', 'full' ], 'default' => '', 'input' => 'segment', 'label' => [ 'en_US' => 'Width', 'de_DE' => 'Breite' ] ],
					'target' => self::LINK['target'],
				], null, 'a .nino-btn', null, false, 'global' ],
				[ 'image', 'Image', 'Bild', 'image', 'image', [
					'alt' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Alternative text', 'de_DE' => 'Alternativtext' ],
						'hint' => [ 'en_US' => 'Used where the slot has no text of its own. Empty marks the picture as decoration.', 'de_DE' => 'Gilt, wo der Slot keinen eigenen Text hat. Leer kennzeichnet das Bild als Dekoration.' ] ],
					'focus' => [ 'type' => 'select', 'options' => [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], 'default' => '', 'label' => [ 'en_US' => 'Focus', 'de_DE' => 'Fokus' ],
						'hint' => [ 'en_US' => 'Which part of the picture survives a crop, as one of nine positions.', 'de_DE' => 'Welcher Teil des Bildes beim Zuschneiden bleibt, als eine von neun Positionen.' ] ],
					'ratio' => [ 'type' => 'select', 'options' => [ '', '1-1', '4-3', '3-2', '16-9', '21-9', '4-1', '5-1' ], 'default' => '', 'label' => [ 'en_US' => 'Ratio', 'de_DE' => 'Seitenverhältnis' ] ],
				], null, null, null, true, 'global' ],
				// The logo: the pictures of two slots in one set, and the ground it stands on says
				// which of them shows (see componentLogo()). It has no source of its own - both
				// slots are attributes - and no link: the frames wrap it in the link of their own
				[ 'logo', 'Logo', 'Logo', 'block', 'none', [
					'light' => [ 'type' => 'image', 'default' => '/logo', 'label' => [ 'en_US' => 'Picture for a light ground', 'de_DE' => 'Bild für hellen Grund' ],
						'hint' => [ 'en_US' => 'A slot, or in a stack an image field of the element. Empty leaves the picture out.', 'de_DE' => 'Ein Slot oder in einem Stapel ein Bildfeld des Elements. Leer lässt das Bild weg.' ] ],
					'dark' => [ 'type' => 'image', 'default' => '/logo/dark', 'label' => [ 'en_US' => 'Picture for a dark ground', 'de_DE' => 'Bild für dunklen Grund' ],
						'hint' => [ 'en_US' => 'A slot, or in a stack an image field of the element. Empty leaves the picture out.', 'de_DE' => 'Ein Slot oder in einem Stapel ein Bildfeld des Elements. Leer lässt das Bild weg.' ] ],
					'alt' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Alternative text', 'de_DE' => 'Alternativtext' ],
						'hint' => [ 'en_US' => 'Used where the slot has no text of its own. Empty marks the picture as decoration.', 'de_DE' => 'Gilt, wo der Slot keinen eigenen Text hat. Leer kennzeichnet das Bild als Dekoration.' ] ],
				], [ 'hero', 'section' ], 'span .nino-logo-set', null, false, 'global' ],
				[ 'html', 'HTML', 'HTML', 'block', 'content', [], null, null, null, false, 'global' ],
				[ 'spacer', 'Spacer', 'Abstand', 'block', 'none', [
					'size' => [ 'type' => 'select', 'options' => [ '1', '2', '3', '4', '5', '6' ], 'default' => '2', 'label' => [ 'en_US' => 'Size', 'de_DE' => 'Größe' ] ],
				], null, null, null, false, 'global' ],
				// The arrow at the foot of a hero, a link to the next section - or to the one
				// whose id it is given - which Nino.ui.js has scroll there
				[ 'scroll-hint', 'Scroll hint', 'Scroll-Hinweis', 'block', 'none', [
					'to' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Target', 'de_DE' => 'Ziel' ],
						'hint' => [ 'en_US' => 'The id of the section to scroll to. Empty scrolls to the next section.', 'de_DE' => 'Die Id der Sektion, zu der gescrollt wird. Leer scrollt zur nächsten Sektion.' ] ],
				], [ 'hero', 'section' ], 'a .nino-hero-arrowdown', null, false, 'hero' ],
				// A column of a table: standing between the tags of a [table] and nowhere
				// else. Its first argument is the text key of the title of the column, none
				// for no title, and what a cell shows is a field of the element or a line of
				// free text, see componentCol(). It has no source of its own, which is why
				// the call is read by the table as well as drawn by itself
				[ 'col', 'Column', 'Spalte', 'block', 'none', [
					'field' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Field', 'de_DE' => 'Feld' ],
						'hint' => [ 'en_US' => 'The field of the element the cell shows. A line of text, if the column has one, is shown instead.', 'de_DE' => 'Das Feld des Elements, das die Zelle zeigt. Hat die Spalte eine Textzeile, steht sie an dessen Stelle.' ] ],
					'align' => [ 'type' => 'select', 'options' => [ '', 'right', 'center' ], 'default' => '', 'label' => [ 'en_US' => 'Alignment', 'de_DE' => 'Ausrichtung' ] ],
				], [ 'table' ], null, null, false, 'global' ],
			];

			// Each class is a component of its own, and the schema says where a tool
			// offers it ('in') and under which group ('group'), what it draws ('draws')
			// and what it holds ('holds', the card's) - and whether the one link it draws may
			// be the link of a linked holder it stands in ('stretched', the card's title): the
			// renderer reads no context, so a title is the same in a card and out of one. Its
			// icon is the shape ICONS names, as the parts SHAPES holds, and 'link' whether a
			// link may wrap what it draws
			$stretched = [ 'card-title', 'title' ];

			foreach( $components as [ $name, $en, $de, $preview, $source, $attributes, $in, $draws, $holds, $link, $group ] )
				self::addComponent( $appData, $name, [ self::class, 'component'. str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) ) ], [
					'label'				=> [ 'en_US' => $en, 'de_DE' => $de ],
					'source'			=> $source,
					'loop'				=> true,
					'attributes'	=> $attributes,
					'preview'			=> $preview,
					'icon'				=> self::SHAPES[ self::ICONS[$name] ],
					'link'				=> $link,
					'group'				=> $group,
				] + ( $in !== null ? [ 'in' => $in ] : [] ) + ( $draws !== null ? [ 'draws' => $draws ] : [] ) + ( $holds !== null ? [ 'holds' => $holds ] : [] ) + ( in_array( $name, $stretched, true ) === true ? [ 'stretched' => true ] : [] ) );

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
						'hint' => [ 'en_US' => 'The field whose values become the buttons.', 'de_DE' => 'Das Feld, dessen Werte zu den Buttons werden.' ] ],
					'all' => [ 'type' => 'string', 'default' => '', 'label' => [ 'en_US' => 'Label of the first button', 'de_DE' => 'Beschriftung des ersten Buttons' ],
						'hint' => [ 'en_US' => 'Empty takes the text /template/common/filter/all, or the word of the page\'s language.', 'de_DE' => 'Leer nimmt den Text /template/common/filter/all oder das Wort der Seitensprache.' ] ],
				] ],
				[ 'list', 'List', 'Liste', 'block', false, [
					'style' => [ 'type' => 'select', 'options' => [ '', 'check', 'numbered', 'columns' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
				] ],
				// The elements as the rows of a table, and what stands between its tags
				// are its columns, the calls of [col] and nothing else: a column is a
				// title and what its cells show, see stackTable()
				[ 'table', 'Table', 'Tabelle', 'table', false, [
					'style' => [ 'type' => 'select', 'options' => [ '', 'striped', 'bordered', 'striped-bordered' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
				], 'table' ],
				// What the stack loops is a whole section each: no grid, no cell, the
				// sections one after the other where the call stands. 'alternate' has
				// every second of them mirrored, see stackSections()
				[ 'sections', 'Sections', 'Sektionen', 'cells', false, [
					'alternate' => [ 'type' => 'bool', 'default' => false, 'label' => [ 'en_US' => 'Mirror every second', 'de_DE' => 'Jede zweite spiegeln' ],
						'hint' => [ 'en_US' => 'Every second section shows its columns in the opposite order, from tablet width up.', 'de_DE' => 'Jede zweite Sektion zeigt ihre Spalten in der umgekehrten Reihenfolge, ab Tablet-Breite.' ] ],
				] ],
			];

			// The seventh entry of a stack is what it holds, where it holds something
			foreach( $stacks as $stack ) {

				[ $name, $en, $de, $preview, $grid, $attributes, $holds ] = $stack + [ 6 => null ];

				self::addStack( $appData, $name, [ self::class, 'stack'. ucfirst( $name ) ], [
					'label'				=> [ 'en_US' => $en, 'de_DE' => $de ],
					'grid'				=> $grid,
					'attributes'	=> $attributes,
					'preview'			=> $preview,
				] + ( $holds !== null ? [ 'holds' => $holds ] : [] ) );
			}

			\Nino\Features::registerComponents( $appData );
		}

		/**
		 *	The style the text components share: a variant of the class each is
		 *	named for, which is what a variant's prefix is where it declares none
		 *
		 *	@return 	array										The attribute's schema
		 */
		private static function _style(): array {
			return [ 'type' => 'variant', 'options' => [ '', 'loud', 'quiet' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ];
		}

		/**
		 *	The colour of the eight text components: a variant of the ink the
		 *	ground they stand on has, which is why the two words are the ground's
		 *	own - the subtitle's and the accent - and there is no light, no dark and
		 *	no status colour among them
		 *
		 *	@return 	array										The attribute's schema
		 */
		private static function _color(): array {
			return [ 'type' => 'variant', 'options' => [ '', 'muted', 'accent' ], 'default' => '', 'prefix' => 'nino-ink--', 'label' => [ 'en_US' => 'Text colour', 'de_DE' => 'Text-Farbe' ] ];
		}

		/**
		 *	The tag select of the six titles and subtitles: the tags one of them may
		 *	be drawn as, and the one it is where the shortcode names none - a title
		 *	in a hero is an h2, a section's an h3, a card's an h4
		 *
		 *	@param		array			$options			The tags it may be, a list
		 *	@param		string		$default			The tag it is where the shortcode names none, one of the options
		 *
		 *	@return 	array										The attribute's schema
		 */
		private static function _tag( array $options, string $default ): array {
			return [ 'type' => 'select', 'options' => $options, 'default' => $default, 'label' => [ 'en_US' => 'HTML tag', 'de_DE' => 'HTML-Tag' ] ];
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
		 *	The components registered, by name - read only. The schema as a tool
		 *	reads it: an attribute whose 'options' name a method (see validate())
		 *	carries the list that method answers - the answer of this request's
		 *	first call, see _resolve() - as 'options', the values, and
		 *	'optionLabels', value => label, where a label is a string or a
		 *	locale => string map (\Nino\Features::localized() reads it); one
		 *	whose method answered nothing readable is a 'string'
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => schema, in registration order
		 */
		public static function components( array &$appData ): array {
			return self::_issued( $appData, self::registered( $appData, false ), false );
		}

		/**
		 *	The stacks registered, by name - read only, and as components() hands out
		 *	the components
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => schema, in registration order
		 */
		public static function stacks( array &$appData ): array {
			return self::_issued( $appData, self::registered( $appData, true ), true );
		}

		/**
		 *	The registry as it was registered, with no method asked: the clean schemas
		 *	as validate() made them, an attribute whose options are a method still
		 *	carrying the name of the method as 'options'. What the module itself reads
		 *	at registration and at every render, and what a feature reads that
		 *	registers a component again in its init() (addComponent() takes a clean
		 *	schema). components() and stacks() are not that: they are the schemas
		 *	handed to a tool, with the answers in place of the names, and a schema of
		 *	theirs is not one to register
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$stack				The stacks and not the components
		 *
		 *	@return 	array										name => clean schema, in registration order
		 */
		public static function registered( array &$appData, bool $stack = false ): array {
			return (array) ( $appData[ $stack === true ? self::STACKS : self::REGISTRY ] ?? [] );
		}

		/**
		 *	The schemas with the lists their methods answer in the place of the
		 *	names of the methods
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$schemas			name => clean schema
		 *	@param		bool			$stack
		 *
		 *	@return 	array										name => schema
		 */
		private static function _issued( array &$appData, array $schemas, bool $stack ): array {

			foreach( $schemas as $name => $schema )
				foreach( (array) ( $schema['attributes'] ?? [] ) as $attribute => $declared )
					if( is_string( $declared['options'] ?? null ) === true ) {

						$schemas[$name]['attributes'][$attribute] = self::_resolve( $appData, (string) $name, $stack, (string) $attribute, $declared );

						// A mark for _normalize() and nobody else's
						unset( $schemas[$name]['attributes'][$attribute]['words'] );
					}

			return $schemas;
		}

		/**
		 *	An attribute whose 'options' name a method, with the list in the place of
		 *	the name: 'options' the values and 'optionLabels' value => label. Asked
		 *	once for each component or stack and attribute in a request (the method
		 *	is asked again only where the registry names another by then), at the
		 *	first time the schema is issued or a value is made ready. An answer
		 *	nothing can be read from is a warning, once, and the attribute a
		 *	'string' for the rest of the request - what a shortcode says passes
		 *	through, a tool draws a text field - without 'input', 'prefix' and a list.
		 *	An empty answer is a list: a project with nothing to choose from yet.
		 *	An attribute that has a list of its own is returned as it is
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The component's or the stack's name
		 *	@param		bool			$stack
		 *	@param		string		$attribute
		 *	@param		array			$declared			The attribute's clean schema
		 *
		 *	@return 	array										The attribute's schema
		 */
		private static function _resolve( array &$appData, string $name, bool $stack, string $attribute, array $declared ): array {

			if( is_string( $declared['options'] ?? null ) === false )
				return $declared;

			$method	= $declared['options'];
			$at			= ( $stack === true ? 'stack' : 'component' ). '/'. $name. '/'. $attribute;

			if( ( $appData[self::OPTIONS][$at]['method'] ?? null ) !== $method ) {

				// Entered before it is asked: a method that asks for the registry itself
				// finds this attribute a string, and does not ask itself again
				$appData[self::OPTIONS][$at] = [ 'method' => $method, 'answer' => null ];

				$answer = self::_ask( $appData, $method, $attribute, (string) $declared['type'] );

				if( is_string( $answer ) === true )
					trigger_error( 'Components: '. ( $stack === true ? 'stack' : 'component' ). ' "'. $name. '", attribute "'. $attribute. '": '. $answer. ' - treated as a string', E_USER_WARNING );
				else
					$appData[self::OPTIONS][$at]['answer'] = $answer;
			}

			$answer = $appData[self::OPTIONS][$at]['answer'];

			if( is_array( $answer ) === false ) {

				// A variant is written as a class and stays a word: what passes is the empty word
				// and a word (see _normalize(), which reads the mark)
				if( $declared['type'] === 'variant' )
					$declared['words'] = true;

				$declared['type'] = 'string';
				unset( $declared['options'], $declared['input'], $declared['prefix'] );

				return $declared;
			}

			$declared['options'] = array_map( 'strval', array_keys( $answer ) );
			$declared['optionLabels'] = $answer;

			return $declared;
		}

		/**
		 *	Ask the method of an attribute for its options and read the answer
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$method				'\Namespace\Class::method'
		 *	@param		string		$attribute
		 *	@param		string		$type					select or variant: the words of a variant are checked
		 *
		 *	@return 	array|string						value => label, or what is wrong with the answer
		 */
		private static function _ask( array &$appData, string $method, string $attribute, string $type ): array|string {

			try {

				// What a manifest was not asked about when it was read: that the class loads
				// and the method is a public static one
				$why = self::_method( $method );

				if( $why !== null )
					return $why;

				$answer = call_user_func_array( $method, [ &$appData, $attribute ] );
			}
			catch( \Throwable $thrown ) {
				return $method. '() threw '. get_class( $thrown ). ': '. $thrown->getMessage();
			}

			if( is_array( $answer ) === false )
				return $method. '() did not answer an array of value => label';

			$list = [];

			foreach( $answer as $value => $label ) {

				$value = (string) $value;

				if( \Nino\Features::localizedValid( $label ) === false )
					return $method. '() answers no string or locale => string map as the label of "'. $value. '"';

				if( $type === 'variant' && $value !== '' && preg_match( self::WORD, $value ) !== 1 )
					return $method. '() answers "'. $value. '", which is no word of a variant';

				$list[$value] = $label;
			}

			return $list;
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

			$schema = self::registered( $appData, $stack )[$name] ?? null;

			if( is_array( $schema ) === false )
				return [];

			$defaults = [];

			foreach( self::_attributes( $schema, $stack ) as $attribute => $declared )
				$defaults[$attribute] = self::_normalize( $declared, null );

			return $defaults;
		}

		/**
		 *	The text keys and the image slots a piece of html+ names as the
		 *	source of a component - what the scans of the templates need, since
		 *	a page the Builder writes names its texts and its pictures like this
		 *	and in no fill. The calls of the components whose schema says 'text'
		 *	or 'image' are found as the rendering finds them (see
		 *	\Nino\Html::shortcodeCalls()), those in a card or a stack among
		 *	them, and what is kept of each is its first argument where that
		 *	begins with a slash: it is what dispatch() hands to value() as a key
		 *	or as a slot. A name without one is a field of the element in a
		 *	stack and nobody's key or slot, and the source of a stack is a type,
		 *	which is neither
		 *
		 *	Read as dispatch() reads it: the slot of an image may be written
		 *	uri="..." as well as bare, and a text with a fixed text="..." has no
		 *	key to look up. A source with a [ in it holds a fill that is put in
		 *	when the page is rendered, which is no name a scan can give: it is
		 *	left out
		 *
		 *	The first argument is not the only way a call names a slot: an
		 *	attribute of the type 'image' does - the background of a card -
		 *	whatever the component's own source is. Each of them is one more
		 *	entry of the call, after the first argument's, with its attribute
		 *	named, and the same rule keeps it: a value that begins with a slash
		 *	and holds no [. One that does not is the image field of the element
		 *	in a stack, which is nobody's slot. A call that leaves such an attribute
		 *	out names the slot its default names: [logo] is the entry of /logo and
		 *	of /logo/dark
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$content			html+, eg. a template
		 *
		 *	@return 	array										[ [ 'name' => 'card-text', 'kind' => 'text', 'source' => '/template/page-home/cards/one-text' ], ... ], in the order the calls stand, one entry per call and one more for each image attribute of it, that with the 'attribute' as well: [ 'name' => 'card', 'kind' => 'image', 'source' => '/template/page-home/cards/one-background', 'attribute' => 'background' ]
		 */
		public static function sources( array &$appData, string $content ): array {

			$components = self::registered( $appData, false );
			$sources = [];

			// A key or a slot begins with a slash; what has a [ in it is a fill the
			// rendering puts in
			$named = static fn( string $source ): bool => $source !== '' && $source[0] === '/' && str_contains( $source, '[' ) === false;

			foreach( \Nino\Html::shortcodeCalls( $appData, $content ) as $call ) {

				$kind = (string) ( $components[ $call['name'] ]['source'] ?? '' );

				if( ( $kind === 'text' && is_string( $call['args']['text'] ?? null ) === false ) || $kind === 'image' ) {

					$source = (string) ( $call['args'][0] ?? '' );

					if( $kind === 'image' && $source === '' )
						$source = (string) ( $call['args']['uri'] ?? '' );

					if( $named( $source ) === true )
						$sources[] = [ 'name' => $call['name'], 'kind' => $kind, 'source' => $source ];
				}

				foreach( (array) ( $components[ $call['name'] ]['attributes'] ?? [] ) as $attribute => $declared ) {

					// An attribute the call leaves out is the slot its default names: [logo] shows /logo
					$source = $call['args'][$attribute] ?? ( ( $declared['type'] ?? '' ) === 'image' ? $declared['default'] : null );

					if( ( $declared['type'] ?? '' ) === 'image' && is_string( $source ) === true && $named( $source ) === true )
						$sources[] = [ 'name' => $call['name'], 'kind' => 'image', 'source' => $source, 'attribute' => (string) $attribute ];
				}
			}

			return $sources;
		}

		/**
		 *	Check a schema and make it complete: the answer is the schema with
		 *	every key present, or what is wrong with it. Not for a page to
		 *	call - it is what addComponent() and addStack() refuse a schema
		 *	by, and what a feature's manifest is read through (see
		 *	\Nino\Features::manifest()). A stack's assets are the files a tool
		 *	tells the developer about; the manifest key of the same name, which
		 *	\Nino\Features::registerAssets() reads into the bundles, is another
		 *	thing
		 *
		 *	Who registered a component ('by') is also how a tool offers it: one that
		 *	is the kernel's own or a project module's - 'nino', which is what a schema
		 *	that says nothing reads as - is offered in a group and declares which,
		 *	one of GROUPS, and one that is a feature's is offered under the feature
		 *	and declares none: a schema that names a group for it is refused.
		 *
		 *	@param		string		$name					The shortcode, a slug
		 *	@param		mixed			$schema				label, source (not for a stack), loop, attributes, preview;
		 *																	icon, in, draws, holds, stretched, link, by and group for a component, grid, holds and assets for a stack.
		 *																	See docs/development.md, "Components"
		 *	@param		bool			$stack				Read it as a stack's
		 *	@param		string|null	$feature			The key of the feature whose manifest the schema is read from, null for a module's: its components are the feature's own, whatever 'by' they say
		 *	@param		string|null	$directory		The directory of that feature: a method that names the options of an attribute is then checked on paper alone (it is a static method of the feature's own class, whose file stands in the directory), where the class may not be loadable yet; null checks the class itself, as a module's registration does
		 *
		 *	@return 	array|string						The clean schema, or what is wrong with it
		 */
		public static function validate( string $name, mixed $schema, bool $stack = false, ?string $feature = null, ?string $directory = null ): array|string {

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

				// What stands between its tags, as a component's 'holds' says it: the place
				// of the components it holds, none where the key is left out
				if( isset( $schema['holds'] ) === true ) {

					if( is_string( $schema['holds'] ) === false || in_array( $schema['holds'], self::PLACES, true ) === false )
						return $what. ': "holds" names a place: '. implode( ', ', array_slice( self::PLACES, 0, -1 ) ). ' or '. self::PLACES[count( self::PLACES ) - 1];

					$clean['holds'] = $schema['holds'];
				}
			}

			$preview = $schema['preview'] ?? ( $stack === true ? 'cells' : 'block' );

			if( in_array( $preview, self::PREVIEWS, true ) === false )
				return $what. ': "preview" must be one of '. implode( ', ', self::PREVIEWS );

			$clean['preview'] = $preview;

			// The icon a tool shows the component by, optional and a component's
			// alone: the key of a stack is dropped unread, as 'in', 'draws' and
			// 'holds' are below. Left out of the clean schema where the schema
			// leaves it out, and an empty one is refused, so that leaving the key
			// out is the one way to say so; the clean one is the list as it was
			// given
			if( $stack === false && isset( $schema['icon'] ) === true ) {

				$icon = self::_icon( $what, $schema['icon'] );

				if( is_string( $icon ) === true )
					return $icon;

				$clean['icon'] = $icon;
			}

			$taken = $stack === true ? array_merge( array_keys( self::LOOP ), array_keys( self::GRID ), self::RESERVED ) : self::RESERVED;

			if( is_array( $schema['attributes'] ?? [] ) === false )
				return $what. ': "attributes" must be a map of attribute => schema';

			$clean['attributes'] = [];

			foreach( (array) ( $schema['attributes'] ?? [] ) as $attribute => $declared ) {

				if( is_string( $attribute ) === false || preg_match( '/^[a-z][a-zA-Z0-9]*$/', $attribute ) !== 1 )
					return $what. ': an attribute name is a lowerCamel identifier';

				if( in_array( $attribute, $taken, true ) === true )
					return $what. ': "'. $attribute. '" is an argument the kernel reads itself and cannot be an attribute';

				$result = self::_attributeSchema( $what, $attribute, $declared, $directory );

				if( is_string( $result ) === true )
					return $result;

				$clean['attributes'][$attribute] = $result;
			}

			// Where a tool offers the component, what it draws and what it holds, all
			// three optional: left out of the clean schema where the schema leaves them
			// out - no 'in' is everywhere, no 'draws' is nothing to show, no 'holds' is
			// a component with nothing inside - and an empty one is refused, so that
			// leaving the key out is the one way to say so
			if( $stack === false ) {

				if( isset( $schema['in'] ) === true ) {

					if( is_array( $schema['in'] ) === false || array_is_list( $schema['in'] ) === false || $schema['in'] === [] )
						return $what. ': "in" must be a non-empty list of '. implode( ', ', self::PLACES );

					foreach( $schema['in'] as $place )
						if( in_array( $place, self::PLACES, true ) === false )
							return $what. ': "in" lists only '. implode( ', ', self::PLACES );

					if( count( array_unique( $schema['in'] ) ) !== count( $schema['in'] ) )
						return $what. ': "in" names a place twice';

					$clean['in'] = $schema['in'];
				}

				if( isset( $schema['draws'] ) === true ) {

					$draws = $schema['draws'];

					if( is_string( $draws ) === false || strlen( $draws ) > 64 || preg_match( '/^[a-z0-9 .{}-]+$/D', $draws ) !== 1 )
						return $what. ': "draws" must be a non-empty string of at most 64 characters from a-z, 0-9, space, ".", "-", "{" and "}"';

					// Every placeholder names an attribute the schema declares, and no
					// brace is left that is none
					preg_match_all( '/\{([^{}]*)\}/', $draws, $placeholders );

					foreach( $placeholders[1] as $placeholder )
						if( array_key_exists( $placeholder, $clean['attributes'] ) === false )
							return $what. ': "draws" has the placeholder {'. $placeholder. '}, and no attribute of that name';

					if( preg_match( '/[{}]/', (string) preg_replace( '/\{[^{}]*\}/', '', $draws ) ) === 1 )
						return $what. ': "draws" has a brace that closes or opens no placeholder';

					$clean['draws'] = $draws;
				}

				// What the content is: the calls of the components of that place, which
				// are rendered with it and which a builder edits as children. Only a
				// component whose source is its content has one
				if( isset( $schema['holds'] ) === true ) {

					if( is_string( $schema['holds'] ) === false || in_array( $schema['holds'], self::PLACES, true ) === false )
						return $what. ': "holds" names a place: '. implode( ', ', array_slice( self::PLACES, 0, -1 ) ). ' or '. self::PLACES[count( self::PLACES ) - 1];

					if( $clean['source'] !== 'content' )
						return $what. ': "holds" needs the source content';

					$clean['holds'] = $schema['holds'];
				}

				// Whether the one link it draws may be the link of a linked holder it
				// stands in - the card's 'linked' makes the link of its title the link of
				// the whole card, and a component of a feature that draws a link of its
				// own says so the same way. Always in the clean schema, false where the
				// schema says nothing; a holder says no, since a holder is no link. The
				// renderer reads no context: the link carries the class nino-stretch in a
				// card and out of one (see _linked()), and the stylesheet reads it only
				// under a linked card
				if( isset( $schema['stretched'] ) === true && is_bool( $schema['stretched'] ) === false )
					return $what. ': "stretched" must be true or false';

				$clean['stretched'] = ( $schema['stretched'] ?? false ) === true;

				if( $clean['stretched'] === true && isset( $clean['holds'] ) === true )
					return $what. ': "stretched" is for a component that draws a link, and a component that holds components is none';

				// Whether what it draws may be a link: the component then has the
				// attributes 'href' and 'target' without declaring them (see
				// _attributes()), and dispatch() wraps what the renderer made in the
				// <a> they say (see _linked()). Always in the clean schema, false where
				// the schema says nothing; a schema that declares one of the two
				// itself has its own, and is refused
				if( isset( $schema['link'] ) === true && is_bool( $schema['link'] ) === false )
					return $what. ': "link" must be true or false';

				$clean['link'] = ( $schema['link'] ?? false ) === true;

				foreach( array_keys( self::LINK ) as $linked )
					if( $clean['link'] === true && array_key_exists( $linked, $clean['attributes'] ) === true )
						return $what. ': "link" gives the component "href" and "target", and the schema declares "'. $linked. '" itself';

				// Who registered it, for a tool that groups what it offers: the kernel's
				// own components and everything a module of the project registers are
				// 'nino' where the schema says nothing, and the components of a feature's
				// manifest are the feature's own whatever the schema says - the manifest
				// is read for the key it hands over (see \Nino\Features::manifest()), and
				// what the schema says itself is read all the same and refused if it is no slug
				$by = $schema['by'] ?? 'nino';

				if( is_string( $by ) === false || preg_match( '/^[a-z][a-z0-9-]*$/D', $by ) !== 1 )
					return $what. ': "by" is a slug of lower case letters, digits and hyphens';

				$clean['by'] = $feature ?? $by;

				// The group a tool offers it under follows from who registered it: what
				// the kernel and the modules of a project register is offered in one of
				// the groups and says which, what a feature registers is offered under
				// the feature - a schema that names a group for it is refused
				if( $clean['by'] === 'nino' ) {

					if( is_string( $schema['group'] ?? null ) === false || in_array( $schema['group'], self::GROUPS, true ) === false )
						return $what. ': "group" must be one of '. implode( ', ', self::GROUPS );

					$clean['group'] = $schema['group'];
				}
				else if( isset( $schema['group'] ) === true )
					return $what. ': a feature\'s components are offered under the feature, not under a group';
			}

			// A note for a tool about the files a stack needs: nothing adds them to a
			// page. Not the 'assets' key of a feature's manifest, which lists files of
			// the feature that the bundles of the site carry (\Nino\Features::registerAssets())
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
		 *	@param		string|null	$directory		The feature's directory, see validate()
		 *
		 *	@return 	array|string						The clean schema, or what is wrong with it
		 */
		private static function _attributeSchema( string $what, string $attribute, mixed $declared, ?string $directory = null ): array|string {

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

			// How a tool may draw it, where the schema says: a hint for the tool, left
			// out of the clean schema where the schema leaves it out. Only a choice of
			// words and a flag have more than one way to be drawn
			if( isset( $declared['input'] ) === true ) {

				$inputs = self::INPUTS[$type] ?? [];

				if( $inputs === [] )
					return $where. 'the type "'. $type. '" has no "input" - it is for a select, a variant and a bool';

				if( in_array( $declared['input'], $inputs, true ) === false )
					return $where. '"input" of a '. $type. ' must be one of '. implode( ', ', $inputs );

				$clean['input'] = $declared['input'];
			}

			// On which tab of its dialog a tool draws it, where the schema says: one word
			// of TABS, a hint for the tool like the input, and left out of the clean schema
			// where the schema leaves it out. Any type may have one
			if( isset( $declared['tab'] ) === true ) {

				if( in_array( $declared['tab'], self::TABS, true ) === false )
					return $where. '"tab" must be one of '. implode( ', ', self::TABS );

				$clean['tab'] = $declared['tab'];
			}

			if( isset( $declared['prefix'] ) === true && $type !== 'variant' )
				return $where. 'only a variant has a "prefix"';

			$default = $declared['default'];

			if( $type === 'select' || $type === 'variant' ) {

				$options = $declared['options'] ?? null;

				// The other form of 'options': the name of a static method that answers
				// them, asked when the schema is issued and a value is made ready - not
				// here, a manifest is read for features that are off as well, and while a
				// feature is being installed or updated, when its class is not the one that
				// is loaded. What can be said now is said now: the name is a method of the
				// feature's own class (see _method()) and the default a string; that the
				// method is there, that the default is one of the options, and that the
				// answer is readable, only the asking can tell
				if( is_string( $options ) === true ) {

					$why = self::_method( $options, $directory );

					if( $why !== null )
						return $where. $why;

					if( ( $clean['input'] ?? '' ) === 'icons' )
						return $where. '"input" icons is for an alignment, which a list that is asked for later cannot be checked to be';

					if( is_string( $default ) === false )
						return $where. 'the default of a '. $type. ' whose options are a method must be a string';

					// A variant's list is words and the empty word (see _ask()): so is its default
					if( $type === 'variant' && $default !== '' && preg_match( self::WORD, $default ) !== 1 )
						return $where. 'the default of a variant is a word';

					$clean['options'] = $options;
				}
				else {

					if( is_array( $options ) === false || $options === [] || array_is_list( $options ) === false )
						return $where. 'a '. $type. ' needs "options", a list of values or the name of a static method';

					$clean['options'] = [];

					foreach( $options as $option )
						if( is_string( $option ) === false && is_int( $option ) === false )
							return $where. '"options" is a list of strings';
						else
							$clean['options'][] = (string) $option;

					// What a variant's value is written as is a class, one: a word of lower case
					// letters and digits, hyphens between them - and the empty word, which is none
					if( $type === 'variant' )
						foreach( $clean['options'] as $option )
							if( $option !== '' && preg_match( self::WORD, $option ) !== 1 )
								return $where. 'the options of a variant are words, and "'. $option. '" is none';

					// A row of icons is drawn for an alignment: the empty word - the one the
					// default is - and words of one set of ALIGNMENTS
					if( ( $clean['input'] ?? '' ) === 'icons' ) {

						$words = array_diff( $clean['options'], [ '' ] );

						if( in_array( '', $clean['options'], true ) === false || $words === [] || array_filter( self::ALIGNMENTS, static fn( array $set ): bool => array_diff( $words, $set ) === [] ) === [] )
							return $where. '"input" icons is for an alignment: the options are "" and words of '. implode( ' or ', array_map( static fn( array $set ): string => implode( '|', $set ), self::ALIGNMENTS ) );
					}

					if( ( is_string( $default ) === false && is_int( $default ) === false ) || in_array( (string) $default, $clean['options'], true ) === false )
						return $where. 'the default is not one of the options';
				}

				// The class a variant is written as: this prefix and the word. Left out, the
				// renderer's own - the class of the component and two hyphens
				if( isset( $declared['prefix'] ) === true ) {

					if( is_string( $declared['prefix'] ) === false || preg_match( '/^[a-z][a-z0-9-]*--$/D', $declared['prefix'] ) !== 1 )
						return $where. '"prefix" is the start of a class, in lower case letters, digits and hyphens, and ends in two hyphens';

					$clean['prefix'] = $declared['prefix'];
				}

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
		 *	Whether the 'options' of an attribute name a method it can be asked: the
		 *	form '\Namespace\Class::method', and then
		 *
		 *	- for a feature's manifest ($directory): on paper alone. The class is the
		 *	feature's own, \Nino\Modules\<Directory> or below it - so that a manifest
		 *	cannot have the schema ask any other static method of the kernel or of the
		 *	project - and its file stands where the autoloader looks for it, below the
		 *	directory. Nothing is loaded: a manifest is read for features that are off,
		 *	from a staging directory before an install, and while the previous version of
		 *	the class is the one that is loaded, and none of that is a reason to refuse it
		 *	- the asking finds out whether the class loads and the method is a public
		 *	static one (see _ask()), and says so as it says any answer it cannot read
		 *	- otherwise, for a module's registration: a class the autoloader serves and a
		 *	public static method of it
		 *
		 *	@param		string		$method
		 *	@param		string|null	$directory		The feature's directory, null for the full check
		 *
		 *	@return 	string|null							What is wrong with it, null where it will do
		 */
		private static function _method( string $method, ?string $directory = null ): ?string {

			if( preg_match( '/^\\\\?[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)*::[A-Za-z_]\w*$/D', $method ) !== 1 )
				return '"options" is a list of values or the name of a static method, written \\Namespace\\Class::method - "'. $method. '" is none';

			[ $class, $name ] = explode( '::', $method );
			$class = ltrim( $class, '\\' );

			if( $directory !== null ) {

				$module = 'Nino\\Modules\\'. basename( rtrim( $directory, '/' ) );

				if( $class !== $module && str_starts_with( $class, $module. '\\' ) === false )
					return '"options" names a static method of the feature\'s own class, \\'. $module. ' or below it: "'. $method. '" is not';

				// The autoloader's layout: the module's file is <Directory>/<Directory>.php, a class
				// below it <Directory>/<Segments>/<Class>/<Class>.php
				$below = $class === $module ? [] : explode( '\\', substr( $class, strlen( $module ) + 1 ) );
				$file = rtrim( $directory, '/' ). ( $below === [] ? '' : '/'. implode( '/', $below ) ). '/'. basename( str_replace( '\\', '/', $class ) ). '.php';

				return is_file( $file ) === true ? null : '"options" names a class whose file is not in the feature: "'. $method. '"';
			}

			if( class_exists( $class ) === false )
				return '"options" names a method of a class that cannot be loaded: "'. $method. '"';

			// Not is_callable(): asked from here, it would say yes to a private method of this class
			$reflected = method_exists( $class, $name ) === true ? new \ReflectionMethod( $class, $name ) : null;

			if( $reflected === null || $reflected->isPublic() === false || $reflected->isStatic() === false )
				return '"options" names a method that is no public static method: "'. $method. '"';

			return null;
		}

		/**
		 *	The icon of a component, checked and complete: the parts of a 24 by 24
		 *	icon that a tool draws with a stroke. It is geometry and no markup, and
		 *	checked as such, so that the tool builds its elements from the parts with
		 *	no parser or sanitiser of its own: a list of 1 to ICON_PARTS parts, each a
		 *	list of two, a tag of ICON_TAGS and the map of its attributes, which holds
		 *	every one the tag takes (but for ICON_OPTIONAL) and no other, in any order -
		 *	each value a string of the characters ICON_VALUES allows it and no longer
		 *	than that, and all the values together at most ICON_LENGTH characters
		 *
		 *	@param		string		$what					How the message names the component
		 *	@param		mixed			$icon
		 *
		 *	@return 	array|string						The clean list of parts, as it was given, or what is wrong with it
		 */
		private static function _icon( string $what, mixed $icon ): array|string {

			$where = $what. ': "icon" ';

			if( is_array( $icon ) === false || array_is_list( $icon ) === false || $icon === [] )
				return $where. 'is a list of parts, each a tag and its attributes';

			if( count( $icon ) > self::ICON_PARTS )
				return $where. 'holds more than '. self::ICON_PARTS. ' parts';

			$clean = [];
			$length = 0;

			foreach( $icon as $index => $part ) {

				if( is_array( $part ) === false || array_is_list( $part ) === false || count( $part ) !== 2 || is_string( $part[0] ) === false || is_array( $part[1] ) === false )
					return $where. 'is a list of parts, each a tag and its attributes';

				[ $tag, $attributes ] = $part;

				$which = $where. 'part '. ( $index + 1 );

				if( array_key_exists( $tag, self::ICON_TAGS ) === false )
					return $which. ' names "'. $tag. '", which is no tag of an icon';

				$article = in_array( $tag[0], [ 'a', 'e', 'i', 'o', 'u' ], true ) === true ? 'an' : 'a';

				// What the part may say, then what it has to say, then how it says it
				foreach( array_keys( $attributes ) as $name )
					if( in_array( $name, self::ICON_TAGS[$tag], true ) === false )
						return $which. ': "'. $name. '" is none of '. $article. ' '. $tag;

				foreach( array_diff( self::ICON_TAGS[$tag], self::ICON_OPTIONAL[$tag] ?? [] ) as $name )
					if( array_key_exists( $name, $attributes ) === false )
						return $which. ': '. $article. ' '. $tag. ' needs "'. $name. '"';

				foreach( $attributes as $name => $value ) {

					[ $pattern, $most, $kind ] = self::ICON_VALUES[$name] ?? self::ICON_VALUES['number'];

					if( is_string( $value ) === false || preg_match( $pattern, $value ) !== 1 )
						return $which. ': "'. $name. '" is not '. $kind;

					if( strlen( $value ) > $most )
						return $which. ': "'. $name. '" is longer than '. $most. ' characters';

					$length += strlen( $value );
				}

				$clean[] = [ $tag, $attributes ];
			}

			if( $length > self::ICON_LENGTH )
				return $where. 'is longer than '. self::ICON_LENGTH. ' characters';

			return $clean;
		}

		/**
		 *	The attributes a shortcode reads: what its schema declares, the link of
		 *	a component that says 'link' after them, 'class' where it does not
		 *	declare it, and for a stack its loop and, with a grid, its cells in
		 *	front of its own
		 *
		 *	@param		array 		$schema				A clean schema
		 *	@param		bool			$stack
		 *
		 *	@return 	array										attribute => its schema
		 */
		private static function _attributes( array $schema, bool $stack ): array {

			$attributes = $stack === true ? self::LOOP + ( ( $schema['grid'] ?? false ) === true ? self::GRID : [] ) : [];

			return $attributes + (array) ( $schema['attributes'] ?? [] ) + ( ( $schema['link'] ?? false ) === true ? self::LINK : [] ) + [ 'class' => [ 'type' => 'string', 'default' => '' ] ];
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
				case 'variant':
					return in_array( $raw, (array) ( $declared['options'] ?? [] ), true ) === true ? $raw : $default;

				case 'int':
					if( preg_match( '/^-?\d{1,9}$/', $raw ) !== 1 )
						return $default;
					return (string) max( $declared['min'] ?? PHP_INT_MIN, min( $declared['max'] ?? PHP_INT_MAX, (int) $raw ) );

				case 'bool':
					if( in_array( strtolower( $raw ), self::ON, true ) === true )
						return '1';
					return in_array( strtolower( $raw ), self::OFF, true ) === true ? '0' : $default;
			}

			// A variant whose method could not be read: the word it is written as, or none
			if( ( $declared['words'] ?? false ) === true && $raw !== '' && preg_match( self::WORD, $raw ) !== 1 )
				return $default;

			return $raw;
		}

		/**
		 *	The fixed text of a call with the fills in it put in, as the fill pass of
		 *	a page puts them in: every [[key]] is the text of the key in the language
		 *	of the page, as the fills hold it - a plain text with its quotes and
		 *	brackets as entities, which value() reads as the characters they are - and
		 *	nothing where the key has no text. The fills inside that text stay as they
		 *	are. A page has its fills put in before its shortcodes run, so this is the
		 *	path of a call that comes from elsewhere, a renderer or a tool that calls
		 *	the shortcode itself; what the text goes on to be is the renderer's
		 *	business, which writes it safe
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$value				The fixed text of the call
		 *
		 *	@return 	string
		 */
		private static function _fills( array &$appData, string $value ): string {

			if( strpos( $value, '[[' ) === false )
				return $value;

			$callback = function( array $match ) use ( &$appData ): string {
				return \Nino\Html::renderTextfill( $appData, $match[1] );
			};

			return (string) preg_replace_callback( '/\[\[([^\[\]]+)\]\]/', $callback, $value );
		}

		/**
		 *	The part every registered shortcode of this module runs before its
		 *	callback: the fixed text of the call is read with the fills in it put
		 *	in (see _fills()), the first argument is resolved over value(), the
		 *	missing attributes are filled from the schema and the ones outside
		 *	their options are reset, and a component that has no value renders
		 *	nothing. The fixed text is the one static value: the other attributes
		 *	are taken as they stand, a fill in the first argument is not a source
		 *	and the content is left to the rendering that follows. Public because
		 *	the closure that stands for the callback in the shortcodes has to
		 *	reach it from the callback's class (see _register()), not because a
		 *	page calls it
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

			// A fixed text says what it says with its fills put in, before anything is done with it.
			// The text of a line is the exception: it fills what it can, its site texts and the
			// fields of the element, and leaves the rest as it stands (see _line())
			$fixed		= is_string( $args['text'] ?? null ) === true ? ( in_array( $name, self::LINES, true ) === true ? $args['text'] : self::_fills( $appData, $args['text'] ) ) : null;

			$prepared = [ 'source' => $source, 'content' => $content ];

			foreach( self::_attributes( $schema, $stack ) as $attribute => $declared )
				$prepared[$attribute] = self::_normalize( self::_resolve( $appData, $name, $stack, (string) $attribute, $declared ), $args[$attribute] ?? null );

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

			$html = (string) call_user_func_array( $callback, [ &$appData, &$prepared ] );

			return ( $schema['link'] ?? false ) === true ? self::_linked( $appData, $prepared, $html, ( $schema['stretched'] ?? false ) === true ) : $html;
		}

		/**
		 *	What a component that says 'link' drew, in the link its attributes
		 *	say: the address of 'href' - resolved as a button resolves its own, a
		 *	text key, an address, in a stack a field of the element - and the window
		 *	it opens in. Nothing wrapped where there is nothing drawn or no address
		 *	that comes out of it. The address is written as value() answers it,
		 *	which has escaped it: a second escaping would turn the & of a query
		 *	into &amp;amp;
		 *
		 *	The <a> of a component that says 'stretched' carries the class
		 *	nino-stretch, first, as the kernel writes the classes of its tags: it
		 *	is written in a card and out of one, since the renderer reads no
		 *	context, and Nino.css reads it only under a linked card.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch(), the link's two among them
		 *	@param		string		$html					What the renderer returned
		 *	@param		bool			$stretched		Whether the schema says 'stretched'
		 *
		 *	@return 	string
		 */
		private static function _linked( array &$appData, array $args, string $html, bool $stretched ): string {

			if( $html === '' || $args['href'] === '' )
				return $html;

			$href = self::value( $appData, $args['href'], 'href' );

			if( $href === null || $href === '' )
				return $html;

			return str_replace(
				[ '[[class]]', '[[href]]', '[[target]]', '[[inner]]' ],
				[ $stretched ? ' class="nino-stretch"' : '', ' href="'. $href. '"', $args['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '', $html ],
				self::$html['link']
			);
		}

		/**
		 *	Resolve the first argument of a component: the one place that
		 *	knows what a source is.
		 *
		 *	A source that begins with a slash is a text key: the value it has
		 *	in the current language, null where it has none. In a stack, a
		 *	name without one is a field of the element the cell is rendered
		 *	for - drawn by \Nino\Html::fieldValue() after its field type, null
		 *	where the type has no such field - and .id, .uri and .slug are the
		 *	number of the element in the loop, its uri '/<type>/<name>' and the
		 *	name alone, the last segment of the uri (see
		 *	\Nino\Modules\Elements::slug()). Outside a stack, all three are
		 *	nothing. A fixed value, the text="..." attribute, takes the place of
		 *	the argument altogether; in the format plain it is read as the text
		 *	of a key is, an entity in it being the character it stands for.
		 *
		 *	Whatever it answers can be written into html as it is: escaped,
		 *	and with every '[' as &#91;, because shortcode output is rendered
		 *	once more and a value an editor wrote must not open a fill or a
		 *	shortcode there. Two kinds of source are not text. An image's value
		 *	is the reference to the picture - the uri of a slot, or in a stack
		 *	the filename an image field holds - escaped like text, so that it
		 *	is safe to write; the renderer looks the picture up through
		 *	\Nino\Images by $args['source'], or by the field it names. An
		 *	address is escaped like text, and one with a scheme that is not http,
		 *	https, mailto or tel is a '#'. An address that is written, in an
		 *	attribute or in a fixed text, is read as a text is: an entity in it - the
		 *	&amp; that a fill of a field puts there - is the character it stands for,
		 *	so that it is escaped once. An address that is a key under /_nino/webpage/ -
		 *	a page's own, which the Routes step writes without the directory the
		 *	project is served from - is written with that directory in front of it,
		 *	as the navigation writes it.
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

			if( $fixed !== null && $kind !== 'image' && $kind !== 'content' ) {

				// A fixed plain text is read as the plain text of a key is: what a page's fill
				// puts into text="..." is the text as it is stored, its quotes and brackets
				// entities (\Nino\Text::sanitizeValue()), and an author may write an entity
				// as well; they are decoded here, so that escaping them once is the only time
				if( $format === 'plain' )
					$fixed = html_entity_decode( $fixed, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				return $kind === 'href' ? self::_href( $fixed ) : self::_formatText( $fixed, $format );
			}

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

				if( $kind === 'href' ) {

					// The address of a page is its own within the project, '/contact', and the
					// project may be served from a directory: a link to the page carries it, as
					// a navigation does. A key that has no fill stays the key
					if( $fill !== null && str_starts_with( $source, '/_nino/webpage/' ) === true && str_starts_with( $fill, '/' ) === true )
						$fill = rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). $fill;

					return self::_href( $fill ?? html_entity_decode( $source, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				}

				return $fill === null ? null : self::_formatText( $fill, $format );
			}

			if( $context !== null ) {

				if( $source === '.id' )
					return $kind === 'href' ? self::_href( (string) $context['id'] ) : (string) $context['id'];

				if( $source === '.uri' )
					return $kind === 'href' ? self::_href( (string) ( $context['element']['.uri'] ?? '' ) ) : self::escape( (string) ( $context['element']['.uri'] ?? '' ) );

				if( $source === '.slug' ) {

					$slug = \Nino\Modules\Elements::slug( is_array( $context['element'] ) === true ? $context['element'] : [] );

					return $kind === 'href' ? self::_href( $slug ) : self::escape( $slug );
				}

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
			if( $kind === 'href' && in_array( $source, [ '.id', '.uri', '.slug' ], true ) === false )
				return self::_href( html_entity_decode( $source, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

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
		private static function _formatText( string $text, string $format ): string {

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
		 *	The content is first filled with the element, by the fills [elements]
		 *	makes (see \Nino\Modules\Elements::fills()): [[.id]], [[.uri]],
		 *	[[.slug]] and [[<field>]] of the element, in running text and inside
		 *	the value of a call - text="[[title]]", href="/blog/[[.slug]]".
		 *	[[.uri]] is the uri of the element, '/<type>/<name>', and [[.slug]]
		 *	the name alone, its last segment - the token of a link to a page of
		 *	the element; [[.lang]] is nothing here, a stack does not take a field
		 *	from another language. A value is escaped as a field is written
		 *	into [elements], with every '[' as &#91; and, because a call ends at the
		 *	first ']' it meets, every ']' as &#93;. In text= and href= it is escaped
		 *	once: the first is read as a text is and the second as an address, an
		 *	entity in either being the character it stands for. Any other attribute is
		 *	taken as it stands and escaped again by the component. A field of the
		 *	model without a value is nothing. The page's own fills come first:
		 *	renderHtml() has put them in before the shortcode is read, so a key
		 *	between double brackets that is a text key is the page's.
		 *
		 *	The fill is a plain replacement over the content as it is written, with
		 *	one exception: what stands between the tags of a call that is no
		 *	component - [elements], another stack, a feature's loop - is that call's
		 *	own and is left as it is, to be filled with its own element; its opening
		 *	tag is filled, so that query="category=[[slug]]" takes the field of the
		 *	outer element. What stands between the tags of a component, a [card],
		 *	is filled. What a template included in the cell brings is not part of
		 *	the content and is not filled.
		 *
		 *	With a grid, each cell is told the classes of its width in the
		 *	viewports (nino-grid-s-, -m- and -l-, from 'cols'), nino-autoheight
		 *	and its group if 'autoheight' is set; without, nothing. The cell
		 *	function dresses it, and puts those on the element it draws as
		 *	the cell - cell() does that. The cell is the grid's and nothing
		 *	more: a card is drawn by a [card] in the content of the stack,
		 *	which is rendered here, so that the card is drawn once per element
		 *	with the element as its context.
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

			$html		= '';
			$parts	= self::_cellParts( $appData, $content );
			// The tokens of the image fields: a line has no use for the path of a file, and prints nothing for one
			$images	= array_map( static fn( int|string $field ): string => '[['. $field. ']]', array_keys( array_filter( $model, static fn( array $declared ): bool => ( $declared['type'] ?? '' ) === 'image' ) ) );

			foreach( array_values( $elements ) as $index => $element ) {

				$previous = $appData[self::CONTEXT] ?? null;

				$appData[self::CONTEXT] = [ 'uri' => $typeUri, 'id' => $index, 'element' => $element, 'model' => $model ];

				$fills = array_map(
					static fn( string|int $fill ): string => str_replace( ']', '&#93;', (string) $fill ),
					\Nino\Modules\Elements::fills( is_array( $element ) === true ? $element : [], $model, $index )
				);

				try {
					$inner = \Nino\Html::renderHtml( $appData, self::_fillCell( $parts, $fills, $images ) );
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
		 *	The content of a stack cut where a call that is no component holds
		 *	something between its tags: [elements], another stack, a feature's
		 *	loop. The text up to and including the opening tag of such a call is
		 *	one part and what stands between its tags is the next, so that the
		 *	fill of the cell (see _fillCell()) leaves the second as it is - it is
		 *	the call's own, filled with its own element - and fills the opening
		 *	tag, in which a token may stand. A component holds components and is
		 *	not cut: what is between its tags is the cell's. The opening tag is
		 *	read with a token in its arguments, which the shortcode pass will see
		 *	once it is filled
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$content			The content of the stack
		 *
		 *	@return 	array										[ [ text, whether it is the call's own ], ... ], put together the content again
		 */
		private static function _cellParts( array &$appData, string $content ): array {

			$names = array_diff( \Nino\Html::shortcodes( $appData ), array_keys( self::components( $appData ) ) );

			if( $names === [] || strpos( $content, '[/' ) === false
				|| preg_match_all( '/\[('. implode( '|', array_map( static fn( string $name ): string => preg_quote( $name, '/' ), $names ) ). ')(?: (?>\[\[[^\[\]]+\]\]|[^\]])*+)?\]([^\[]*+(?:\[(?!\/\1\])[^\[]*+)*+)\[\/\1\]/', $content, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) < 1 )
				return [ [ $content, false ] ];

			$parts	= [];
			$at			= 0;

			foreach( $found as $call ) {

				[ $inner, $start ] = $call[2];

				$parts[] = [ substr( $content, $at, $start - $at ), false ];
				$parts[] = [ $inner, true ];

				$at = $start + strlen( $inner );
			}

			$parts[] = [ substr( $content, $at ), false ];

			return $parts;
		}

		/**
		 *	The content of a stack filled with the fills of one element, except the
		 *	parts that are a call's own (see _cellParts())
		 *
		 *	@param		array			$parts				From _cellParts()
		 *	@param		array			$fills				'[[token]]' => value
		 *
		 *	@return 	string
		 */
		private static function _fillCell( array $parts, array $fills, array $images = [] ): string {

			$tokens	= array_keys( $fills );
			$values	= array_values( $fills );
			$html		= '';

			foreach( $parts as [ $text, $own ] )
				$html .= $own === true ? $text : self::_keepTokens( str_replace( $tokens, $values, self::_dropTokens( $text, $images ) ) );

			return $html;
		}

		/**
		 *	The tokens of the image fields taken out of the text of the [line]s and [col]s of a cell: a path of a file is no text
		 *
		 *	@param		string		$html					The content of a cell
		 *	@param		array			$tokens				'[[field]]' of every image field of the type
		 *
		 *	@return 	string
		 */
		private static function _dropTokens( string $html, array $tokens ): string {

			if( $tokens === [] || strpos( $html, '[[' ) === false )
				return $html;

			return (string) preg_replace_callback(
				'/\[('. implode( '|', self::LINES ). ')(?: ((?>\[\[[^\[\]]+\]\]|[^\]])*+))?\]/',
				static fn( array $call ): string => '['. $call[1]. ( isset( $call[2] ) === true ? ' '. str_replace( $tokens, '', $call[2] ) : '' ). ']',
				$html
			);
		}

		/**
		 *	The fills a cell has left in the text of its [line]s and [col]s, written as
		 *	entities: a [[name]] that is no field of the element is the text it was
		 *	written as, and a call is read up to its first closing bracket - the one of
		 *	the token would end it there. _line() reads the entities back as the
		 *	characters they are and writes them escaped
		 *
		 *	@param		string		$html					The content of a cell, its fields filled in
		 *
		 *	@return 	string
		 */
		private static function _keepTokens( string $html ): string {

			if( strpos( $html, '[[' ) === false )
				return $html;

			return (string) preg_replace_callback(
				'/\[('. implode( '|', self::LINES ). ')(?: ((?>\[\[[^\[\]]+\]\]|[^\]])*+))?\]/',
				static fn( array $call ): string => '['. $call[1]. ( isset( $call[2] ) === true ? ' '. strtr( $call[2], [ '[[' => '&#91;&#91;', ']]' => '&#93;&#93;' ] ) : '' ). ']',
				$html
			);
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
			return preg_match( '/^[a-z][a-z0-9_-]*$/iD', $id ) === 1 ? $id : '';
		}

		/**
		 *	One cell of a stack as an element: the dressing renderStack() hands
		 *	the cell function, a class and attributes of the cell function's
		 *	own added to it. The element is a div, the slot of the grid, and
		 *	nothing else: a card is drawn by a [card] in what the cell holds
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
		 *	The class a variant is written as, a space in front: the word behind the
		 *	prefix its attribute declares - the class of the component and two
		 *	hyphens where it declares none, which is what a title's style is - and
		 *	nothing for the word that says none
		 *
		 *	@param		string		$base					The class the component is named for
		 *	@param		array			$declared			The attribute's clean schema, [] where the registry has none
		 *	@param		string		$value				What dispatch() made of the attribute, one of its options
		 *
		 *	@return 	string
		 */
		private static function _variant( string $base, array $declared, string $value ): string {
			return self::_modifier( (string) ( $declared['prefix'] ?? $base. '--' ), $value );
		}

		/**
		 *	The attributes the registry has for a component, which is where a
		 *	renderer of the kernel's reads what a variant of its own is written as:
		 *	a component registered again under a schema of its own gets the prefix
		 *	that schema declares
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$component		The component's name
		 *
		 *	@return 	array										attribute => its clean schema, [] for a name nobody registered
		 */
		private static function _declared( array &$appData, string $component ): array {
			return (array) ( self::registered( $appData, false )[$component]['attributes'] ?? [] );
		}

		/**
		 *	The lang attribute of a component whose text is the value of a text key
		 *	the language of the page has no text for, which the page then shows in
		 *	another language (see \Nino\Html::fillOrigin()): that language's tag with a
		 *	space in front - and nothing for a text of the page's own language, for
		 *	one the call fixes with text="..." and for a field of an element, which
		 *	has no key. A screen reader reads the text in the voice of the language
		 *	it is in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		private static function _lang( array &$appData, array $args ): string {

			$source = (string) ( $args['source'] ?? '' );

			if( isset( $args['text'] ) === true || $source === '' || $source[0] !== '/' )
				return '';

			$origin = \Nino\Html::fillOrigin( $appData, $source );

			return $origin === null ? '' : self::_attribute( 'lang', \Nino\Locales::languageTag( $origin ) );
		}

		/**
		 *	The part of the six titles they share: the fragment of the one that
		 *	is rendered, filled with its tag, its style and its colour as variants
		 *	of the class it is named for and of the ink, its own class and its value
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$component		The component and the key of its markup in $html
		 *	@param		string		$base					The class it is named for, which its style is written behind
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		private static function _title( array &$appData, string $component, string $base, array $args ): string {

			$declared = self::_declared( $appData, $component );

			// The tag is written as it is: it is one of the select's options, which dispatch() reduced it to
			return str_replace(
				[ '[[tag]]', '[[style]]', '[[color]]', '[[class]]', '[[lang]]', '[[value]]' ],
				[
					$args['tag'],
					self::_variant( $base, (array) ( $declared['style'] ?? [] ), $args['style'] ),
					self::_variant( $base, (array) ( $declared['color'] ?? [] ), $args['color'] ?? '' ),
					self::_modifier( '', $args['class'] ),
					self::_lang( $appData, $args ),
					$args['value'],
				],
				self::$html[$component]
			);
		}

		/**
		 *	The part of [text] and [card-text] they share: the fragment of the text,
		 *	filled with its tag, the class the text draws, the wrapper's mark as rich
		 *	text where it holds paragraphs and lists, its style and its colour as
		 *	variants of that class and of the ink, its own class and its value. A
		 *	text that holds paragraphs and lists is a div, whatever tag was asked
		 *	for - a p or a span cannot hold a p -, and so is the text of a card, which
		 *	asks for none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$component		The component: text or card-text
		 *	@param		string		$base					The class the text draws: nino-section-text or nino-card-descr
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		private static function _text( array &$appData, string $component, string $base, array $args ): string {

			$declared = self::_declared( $appData, $component );

			// Paragraphs and lists are no content of a p or a span: that text is a div. The tag of another
			// is written as it is, one of the select's options which dispatch() reduced it to; the text of
			// a card has none
			$tag = $args['format'] === 'blocks' ? 'div' : (string) ( $args['tag'] ?? 'div' );

			return str_replace(
				[ '[[tag]]', '[[base]]', '[[richtext]]', '[[style]]', '[[color]]', '[[class]]', '[[lang]]', '[[value]]' ],
				[
					$tag,
					$base,
					$args['format'] === 'blocks' ? ' nino-richtext' : '',
					self::_variant( $base, (array) ( $declared['style'] ?? [] ), $args['style'] ),
					self::_variant( $base, (array) ( $declared['color'] ?? [] ), $args['color'] ?? '' ),
					self::_modifier( '', $args['class'] ),
					self::_lang( $appData, $args ),
					$args['value'],
				],
				self::$html['text']
			);
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
			return self::_title( $appData, 'title', 'nino-section-title', $args );
		}

		/**
		 *	Replace [card-title]: the heading of a card
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentCardTitle( array &$appData, array $args ): string {
			return self::_title( $appData, 'card-title', 'nino-card-title', $args );
		}

		/**
		 *	Replace [hero-title]: the heading of a hero
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentHeroTitle( array &$appData, array $args ): string {
			return self::_title( $appData, 'hero-title', 'nino-hero-title', $args );
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
			return self::_title( $appData, 'subtitle', 'nino-section-subtitle', $args );
		}

		/**
		 *	Replace [card-subtitle]: the line under the heading of a card
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentCardSubtitle( array &$appData, array $args ): string {
			return self::_title( $appData, 'card-subtitle', 'nino-card-subtitle', $args );
		}

		/**
		 *	Replace [hero-subtitle]: the line under the heading of a hero
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentHeroSubtitle( array &$appData, array $args ): string {
			return self::_title( $appData, 'hero-subtitle', 'nino-hero-subtitle', $args );
		}

		/**
		 *	Replace [text]: the rich text of a key or of a field, in the
		 *	format the attribute names - a field keeps the one its type
		 *	declares. The value is what \Nino\Html::sanitizeHtml() left of
		 *	it, a text key's value read again as that format, so the wrapper
		 *	carries .nino-richtext where it holds paragraphs and lists. The wrapper
		 *	is the p, the div or the span that 'tag' says, and a div where the format
		 *	is blocks, which holds paragraphs of its own
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentText( array &$appData, array $args ): string {
			return self::_text( $appData, 'text', 'nino-section-text', $args );
		}

		/**
		 *	Replace [card-text]: the text of a card, as [text] has it - the rich text
		 *	of a key or of a field, in the format the attribute names, and the
		 *	wrapper marked as rich text where it holds paragraphs and lists - in the
		 *	class of the text of a card, nino-card-descr, and always in a div
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentCardText( array &$appData, array $args ): string {
			return self::_text( $appData, 'card-text', 'nino-card-descr', $args );
		}

		/**
		 *	The picture a reference names, looked up the way [image] looks it up:
		 *	in a stack, a name that is a field of the type image in the model of
		 *	the element is the file that field holds; anything else is the uri of
		 *	a slot. What the picture is drawn as is the caller's business, and so
		 *	is the alt text it chooses: this gives the one stored with the picture
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$reference		The uri of a slot, or the name of an image field of the element
		 *
		 *	@return 	array|null							[ 'url', 'width', 'height', 'alt' ], the alt text as it is stored for
		 *																	the language of the page and '' where there is none; null where
		 *																	the slot has no file and where the field holds none
		 */
		private static function _picture( array &$appData, string $reference ): ?array {

			if( $reference === '' )
				return null;

			$context = self::element( $appData );

			if( $context !== null && $reference[0] !== '/' && ( $context['model'][$reference]['type'] ?? '' ) === 'image' ) {

				$filename = $context['element'][$reference] ?? '';

				if( is_string( $filename ) === false || $filename === '' )
					return null;

				$link		= $context['model'][$reference]['alt'] ?? null;
				$stored	= is_string( $link ) === true ? ( $context['element'][$link] ?? '' ) : '';

				return [
					'url'			=> \Nino\Images::getUrl( $appData, $filename ),
					'width'		=> (int) ( $context['model'][$reference]['width'] ?? 0 ),
					'height'	=> (int) ( $context['model'][$reference]['height'] ?? 0 ),
					'alt'			=> is_string( $stored ) === true ? $stored : '',
				];
			}

			$slot = \Nino\Images::getSlot( $appData, $reference );

			if( $slot === false || empty( $slot['filename'] ) === true )
				return null;

			$stored = $slot['alt'][ \Nino\Locales::getCurrentLocale( $appData ) ] ?? '';

			return [
				'url'			=> \Nino\Images::getUrl( $appData, $slot['filename'] ),
				'width'		=> (int) ( $slot['width'] ?? 0 ),
				'height'	=> (int) ( $slot['height'] ?? 0 ),
				'alt'			=> is_string( $stored ) === true ? $stored : '',
			];
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
		 *	image: [[src]] (the path of the file, from the site's root, with the
		 *	directory the project is served from in front of it - what an <img>
		 *	takes), [[width]], [[height]] and [[alt]] are filled in with the values
		 *	the <img> gets, and [[url]] with the absolute address of the file. A place
		 *	that needs the absolute address - a meta tag, a mail - writes [[url]],
		 *	and never "https://[[/project/website/general/url]][[src]]": the address
		 *	of the website carries the directory already, and so does [[src]], so
		 *	that form names it twice under a subdirectory. [[url]] is
		 *	Html::absoluteUrl() of the path without the directory, which names it
		 *	once; where the website has no address it is the path as [[src]] has
		 *	it - a relative address is still an address in a meta tag or a mail,
		 *	an empty string is none. That is how such a place is written without
		 *	being left as an empty tag, or a broken one, where nothing is uploaded
		 *	yet.
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
		 *	<div class="nino-image ...">, which crops it as Nino.css has it. The class
		 *	is the frame's, never the <img>'s: the frame, cut at its corners, is what
		 *	gives the picture its radius, the one of the image kind (--radius-image),
		 *	or the other strength where the class says nino-radius or nino-radius-alt.
		 *	A bare <img> has no frame, and no corners of its own.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentImage( array &$appData, array $args ): string {
			return self::_image( $appData, 'image', 'nino-image', $args );
		}

		/**
		 *	Replace [card-image]: the picture of a card, found and written as
		 *	[image] finds and writes its own - the slot or the image field, the
		 *	alt text, the content form - as the <img> of .nino-card-img. A focus,
		 *	a ratio or a class of its own puts it in the frame of [image], which
		 *	then carries .nino-card-img: the rules of the picture of a card (the
		 *	width, the room below it, the corners, the style inline, which is the
		 *	class nino-card-img--inline) work on the frame as on the <img>, and the
		 *	frame crops and has the classes that stand on a frame, nino-hover among
		 *	them. The class of its own is always the frame's, since it is what makes
		 *	one; the style goes where .nino-card-img goes: on the <img> where there
		 *	is no frame, on the frame where there is one - and makes none: a
		 *	picture with style="inline" and nothing else is the bare <img>, at its
		 *	own size
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentCardImage( array &$appData, array $args ): string {
			return self::_image( $appData, 'card-image', 'nino-card-img', $args );
		}

		/**
		 *	Replace [logo]: the pictures of two slots in one set, <span class="nino-logo-set">,
		 *	and which of them is seen is the ground it stands on. 'light' is the picture
		 *	for a light ground (the slot /logo) and 'dark' the one for a dark ground
		 *	(/logo/dark); each is an <img> of the fragment [image] writes, of the class
		 *	nino-logo-light or nino-logo-dark, with the size of its slot and its alt text,
		 *	found as [image] finds it: the one stored for the slot, then the alt of the
		 *	call, then none. Nino.css shows the one the pair --logo-light and --logo-dark
		 *	names (see the logo in chapter 03 of Nino.css), so that nothing here knows a ground, and the
		 *	pair is set by the ground a thing paints, a frame included.
		 *
		 *	A slot without a file draws no <img>: with one picture there is nothing to
		 *	switch and the picture is always seen, whichever ground it stands on; with
		 *	none the component draws nothing, which is what keeps a wrapper that hides
		 *	itself when it is :empty - the logo of the navigation - empty, and lets the
		 *	name of the site be seen in the link of a frame. A call that names a slot of
		 *	its own, light="/brand/mark" dark="/brand/mark-white", or none, dark="",
		 *	draws that. The content form, [logo]...[/logo], is not needed: both
		 *	pictures are written whole, and a place that asks for the address of the
		 *	file - a meta tag, a mail - asks [image /logo] for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentLogo( array &$appData, array $args ): string {

			$pictures = [];

			foreach( [ 'light', 'dark' ] as $ground ) {

				$picture = self::_picture( $appData, (string) $args[$ground] );

				if( $picture === null ) {
					$pictures[$ground] = '';
					continue;
				}

				$pictures[$ground] = str_replace(
					[ '[[src]]', '[[width]]', '[[height]]', '[[alt]]', '[[class]]' ],
					[ self::escape( $picture['url'] ), (string) $picture['width'], (string) $picture['height'], self::escape( $picture['alt'] !== '' ? $picture['alt'] : $args['alt'] ), self::_attribute( 'class', 'nino-logo-'. $ground ) ],
					self::$html['img']
				);
			}

			if( $pictures['light'] === '' && $pictures['dark'] === '' )
				return '';

			return str_replace(
				[ '[[class]]', '[[light]]', '[[dark]]' ],
				[ self::_modifier( '', $args['class'] ), $pictures['light'], $pictures['dark'] ],
				self::$html['logo']
			);
		}

		/**
		 *	The part of [image] and [card-image] they share: the picture a
		 *	reference names as an <img> - or as what the content between the tags
		 *	makes of it, the same for both - and the frame around it where there
		 *	is one. A focus, a ratio or a class of its own call for the frame, in
		 *	the one as in the other, and the class is the frame's, behind the ratio
		 *	and the focus.
		 *
		 *	The picture of [image] is its frame, nino-image, which the fragment
		 *	names; with none of the three it is the bare <img>, which has no class
		 *	attribute. The picture of a card is the box nino-card-img names, and what
		 *	it carries - that class, then its style and its class of its own - stands
		 *	on the frame where there is one, in the place of its class, which then
		 *	holds an <img> with none, and on the <img> where there is none. A style
		 *	is no call for a frame, so a card's picture with a style and nothing else
		 *	is that <img>. The style, where a component has one, is a variant, written
		 *	as the class its prefix and the word make
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$component		The component: image or card-image
		 *	@param		string		$base					The class of the picture: nino-image or nino-card-img
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		private static function _image( array &$appData, string $component, string $base, array $args ): string {

			// The value is the reference made safe to write; the lookup takes the
			// reference itself
			$picture = self::_picture( $appData, (string) $args['source'] );

			if( $picture === null )
				return '';

			$alt = $picture['alt'] !== '' ? $picture['alt'] : $args['alt'];

			$content = trim( $args['content'] ) !== '' ? $args['content'] : null;

			// What the picture carries - the class of a card, then its style and its class of
			// its own - is the frame's where there is one and the <img>'s where there is none.
			// A focus, a ratio or a class of its own call for the frame, in a card as in [image];
			// the class of the card and the style do not, so $framed reads the class of the call
			// and not the list that is made of all three
			$card	= $base === 'nino-card-img';
			$style	= trim( self::_variant( $base, (array) ( self::_declared( $appData, $component )['style'] ?? [] ), $args['style'] ?? '' ) );
			$class	= implode( ' ', array_filter( [ $card === true ? $base : '', $style, $args['class'] ], static fn( string $word ): bool => $word !== '' ) );
			$framed	= $args['focus'] !== '' || $args['ratio'] !== '' || $args['class'] !== '';

			// The absolute address is the website's, which carries the directory, and the path of the
			// file without the one the picture's own address starts with; where the website has none
			// the address of the picture is all there is
			$url		= $picture['url'];
			$markup	= $content ?? str_replace( '[[class]]', $card === true && $framed === false ? self::_attribute( 'class', $class ) : '', self::$html['img'] );

			// Worked out where the markup asks for it - the content form, or a fragment a project put [[url]] into
			if( str_contains( $markup, '[[url]]' ) === true ) {

				$dir	= rtrim( \Nino\Filesystem::getDir( $appData ), '/' );
				$path	= $dir !== '' && str_starts_with( $url, $dir. '/' ) === true ? substr( $url, strlen( $dir ) ) : $url;
				$url	= \Nino\Html::absoluteUrl( $appData, $path ) ?: $url;
			}

			$img = str_replace(
				[ '[[src]]', '[[url]]', '[[width]]', '[[height]]', '[[alt]]' ],
				[ self::escape( $picture['url'] ), self::escape( $url ), (string) $picture['width'], (string) $picture['height'], self::escape( $alt ) ],
				$markup
			);

			if( $content !== null || $framed === false )
				return $img;

			return str_replace(
				[ '[[ratio]]', '[[focus]]', '[[class]]', '[[img]]' ],
				[ self::_modifier( 'nino-image--', $args['ratio'] ), self::_modifier( 'nino-img-focus--', $args['focus'] ), self::_modifier( '', $class ), $img ],
				self::$html['image']
			);
		}

		/**
		 *	Replace [button]: a link drawn as a button. The label is the
		 *	source - a key, a field or text="..."; the address is the href
		 *	attribute, and where that is not given and the label is a fixed
		 *	one, the first argument is: [button .uri text="More"] is a button
		 *	"More" to the uri of the element. The width full is the class
		 *	nino-btn--fullwidth: a button as wide as the box it stands in. Where it
		 *	has no address at all it is a <span class="nino-btn">, the same classes
		 *	and no link: an <a> without an href looks like a button and is not a stop
		 *	of the Tab key, and neither a keyboard nor a screen reader has anything to
		 *	press
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
				[ '[[style]]', '[[size]]', '[[width]]', '[[class]]', '[[href]]', '[[target]]', '[[lang]]', '[[value]]' ],
				[
					self::_modifier( 'nino-btn--', $args['style'] ),
					self::_modifier( 'nino-btn--', $args['size'] ),
					self::_modifier( 'nino-btn--', ( $args['width'] ?? '' ) === 'full' ? 'fullwidth' : '' ),
					self::_modifier( '', $args['class'] ),
					$href !== null && $href !== '' ? ' href="'. $href. '"' : '',
					$args['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '',
					self::_lang( $appData, $args ),
					$args['value'],
				],
				self::$html[$href !== null && $href !== '' ? 'button' : 'button-span']
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
		 *	Replace [spacer]: empty room between two components - a div of the
		 *	class nino-spacer-<size>, whose height is that step of the scale
		 *	(1 to 6), and not a margin: a margin class given to it - the side
		 *	classes of the Builder, nino-mt-2 - is room beside the height, where a
		 *	nino-mt-<size> of its own was the same property and the larger step won
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
		 *	Replace [scroll-hint]: the arrow at the foot of a hero - a link to the
		 *	section whose id 'to' names, and to the next one where it names none:
		 *	the link is then a '#', which Nino.ui.js answers on the attribute
		 *	data-arrow-next by scrolling to what follows the section the arrow is
		 *	in. The chevron is Nino.css's, and what the arrow is called for a
		 *	screen reader is a text of its own, /template/common/navigation/next-section,
		 *	in the language of the page
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentScrollHint( array &$appData, array $args ): string {

			// A target that is no id is none: it goes into the address as it is
			$to = self::_id( $args['to'] );

			return str_replace(
				[ '[[class]]', '[[to]]', '[[next]]' ],
				[ self::_modifier( '', $args['class'] ), $to, $to === '' ? ' data-arrow-next' : '' ],
				self::$html['scroll-hint']
			);
		}

		/**
		 *	Replace [card]: a component that holds components. What stands
		 *	between its tags is the calls of the components of a card, and this
		 *	draws the <article> around them: its ground, its padding, whether it
		 *	takes the whole height of its column or cell, where its content stands
		 *	in it, a picture behind the content, its border, whether its link - the
		 *	title's, or another component's that may be the card's - is stretched over
		 *	the whole of it, and a class of its own. It
		 *	stands once in a column - a card beside a picture - and in the content
		 *	of a stack once per element: the cards of a loop.
		 *
		 *	The content is $args['value'], and it is not rendered here, only sorted
		 *	(see _sorted()): the first child that is a picture stands first in the
		 *	<article>, and every other child stands behind it in a <div> of the class
		 *	nino-card-content, in the order it was written in. A card with no padding
		 *	of its own, nino-p-0, has its picture at its edges and its words in a room
		 *	of their own; a card without a picture has the div alone, and one with
		 *	nothing but a picture has no div. The engine renders the output of a
		 *	shortcode again
		 *	(\Nino\Html::_doShortcode(), to a depth it caps), which is what makes
		 *	[template] work, so the components in the card are rendered as soon as
		 *	this returns - in a stack with the element still their context,
		 *	because renderStack() holds it around the \Nino\Html::renderHtml()
		 *	this card is rendered in. That is why the renderer calls no
		 *	renderHtml() of its own. A card with nothing between its tags renders
		 *	nothing (dispatch() sees to that for a source of content); a card whose
		 *	components all render nothing is an <article> with an empty div, as a
		 *	section with nothing in it is a section.
		 *
		 *	The picture is looked up as [image] looks its picture up - a slot by
		 *	its uri, in a stack a field of the element's model of the type image
		 *	by its name - and is an <img> with alt="" in a <div> of its own in
		 *	front of the content: behind a card it is decoration. A slot with no
		 *	file and a field that holds none draw no <div>. The kernel writes every
		 *	class it is told to: a dim without a picture is the author's mistake
		 *	and a builder's business, as the dim of a column is.
		 *
		 *	The card is no <a>, whatever 'linked' says: it holds buttons, and a link
		 *	in a link is no markup. 'linked' writes the class nino-card--linked and
		 *	nothing else; Nino.css stretches the link of a component that says
		 *	'stretched' - the <a> of class nino-stretch, which for the title is the one
		 *	that a [card-title] with an address is drawn in (see _linked()) - over
		 *	the whole card with its ::after, so that the keyboard has one stop with
		 *	the name of that link, shown as a ring on the card, and the pointer the
		 *	whole area, and the buttons, links, fields, frames and players in the
		 *	card stay above it. A card with no such link shows nothing of it.
		 *
		 *	A [card] directly inside a [card] is not supported, for the
		 *	reason a stack inside a stack is not: the shortcode reads its content
		 *	up to the first closing tag.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentCard( array &$appData, array $args ): string {

			$picture		= self::_picture( $appData, $args['background'] );
			$background	= '';

			[ $first, $rest ] = self::_sorted( $appData, $args['value'] );

			$inner = $first. ( $rest === '' ? '' : str_replace( '[[inner]]', $rest, self::$html['card-content'] ) );

			if( $picture !== null ) {

				$img = str_replace(
					[ '[[src]]', '[[width]]', '[[height]]', '[[alt]]', '[[class]]' ],
					[ self::escape( $picture['url'] ), (string) $picture['width'], (string) $picture['height'], '', '' ],
					self::$html['img']
				);

				$background = str_replace(
					[ '[[focus]]', '[[img]]' ],
					[ self::_modifier( 'nino-img-focus--', $args['focus'] ), $img ],
					self::$html['card-bg']
				);
			}

			return str_replace(
				[ '[[ground]]', '[[dim]]', '[[borderless]]', '[[fill]]', '[[linked]]', '[[vertical]]', '[[align]]', '[[padding]]', '[[class]]', '[[background]]', '[[inner]]' ],
				[
					self::_modifier( 'nino-card--', $args['ground'] ),
					self::_modifier( 'nino-card--', $args['dim'] === '1' ? 'dim' : '' ),
					self::_modifier( 'nino-card--', $args['borderless'] === '1' ? 'borderless' : '' ),
					self::_modifier( 'nino-card--', $args['fill'] === '1' ? 'fill' : '' ),
					self::_modifier( 'nino-card--', $args['linked'] === '1' ? 'linked' : '' ),
					self::_modifier( 'nino-card--', $args['vertical'] ),
					self::_modifier( 'nino-text-', $args['align'] ),
					self::_modifier( 'nino-p-', $args['padding'] ),
					self::_modifier( '', $args['class'] ),
					$background,
					$inner,
				],
				self::$html['card']
			);
		}

		/**
		 *	What stands between the tags of a card, sorted: the call of its first
		 *	picture - a component whose source is an image, wherever among the others
		 *	it stands - and the rest as it was written, in its order and without the
		 *	white space at its ends. The calls are found as the rendering finds them
		 *	(\Nino\Html::shortcodeParts()), so a picture in the content of another call
		 *	is that call's and not the card's. Nothing is rendered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$content			html+, the content of the card
		 *
		 *	@return 	array										[ the call of the picture or '' where there is none, the rest ]
		 */
		private static function _sorted( array &$appData, string $content ): array {

			$components	= self::registered( $appData, false );
			$picture		= '';
			$rest				= '';

			foreach( \Nino\Html::shortcodeParts( $appData, $content ) as $part ) {

				if( $picture === '' && $part['name'] !== '' && ( $components[$part['name']]['source'] ?? '' ) === 'image' ) {
					$picture = $part['text'];
					continue;
				}

				$rest .= $part['text'];
			}

			return [ $picture, trim( $rest ) ];
		}

		/**
		 *	Replace [stack]: a stack of the elements of a type, one cell each,
		 *	in a grid row nested where the stack stands. The gap is a class of
		 *	the row, nino-stack-gap-<n>, and the cells carry the widths of 'cols'.
		 *	A cell is a div: the cards of a loop are [card]s in its content
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
		 *	Replace [table]: the elements as the rows of a table, and its columns the
		 *	calls of [col] between its tags - nothing else is read, what stands
		 *	between them is not drawn. Each row is a <tr> of the cells the columns make
		 *	for its element (see componentCol()); the header row is written when at
		 *	least one column has a title, and a column without one has an empty <th>.
		 *	'style' is striped, bordered or both, written with a hyphen. The table is
		 *	inside a wrap that scrolls where the viewport is too narrow. A table with no
		 *	column and one with no element is nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function stackTable( array &$appData, array $args ): string {

			preg_match_all( '/\[col(?: ((?>\[\[[^\[\]]+\]\]|[^\]])*+))?\]/', (string) $args['content'], $calls, PREG_SET_ORDER );

			if( $calls === [] )
				return '';

			// The titles are the text keys the columns name, the first argument of each
			$titles = [];
			$titled = false;

			foreach( $calls as $call ) {

				$key = '';

				preg_match_all( '/\ ([^\ \=]*)(\=[\"]([^\"]*)[\"])?/i', ' '. ( $call[1] ?? '' ). ' ', $found );

				foreach( $found[1] as $position => $word )
					if( $found[2][$position] === '' && $word !== '' ) {
						$key = $word;
						break;
					}

				$title = $key !== '' ? self::value( $appData, $key, 'text' ) : null;
				$titled = $titled === true || ( $title !== null && $title !== '' );
				$titles[] = str_replace( '[[value]]', (string) $title, self::$html['th'] );
			}

			// The columns alone are the content, so that nothing else is drawn in a row
			$args['content'] = implode( '', array_column( $calls, 0 ) );

			$previous = $appData[self::TABLE] ?? null;
			$appData[self::TABLE] = true;

			try {
				$rows = self::renderStack( $appData, $args, static function( string $inner ): string {
					return str_replace( '[[inner]]', $inner, self::$html['row'] );
				} );
			}
			finally {
				if( $previous === null )
					unset( $appData[self::TABLE] );
				else
					$appData[self::TABLE] = $previous;
			}

			if( $rows === '' )
				return '';

			return str_replace(
				[ '[[style]]', '[[class]]', '[[id]]', '[[head]]', '[[cells]]' ],
				[
					( str_contains( $args['style'], 'striped' ) === true ? ' nino-table--striped' : '' ). ( str_contains( $args['style'], 'bordered' ) === true ? ' nino-table--bordered' : '' ),
					self::_modifier( '', $args['class'] ),
					self::_attribute( 'id', self::_id( $args['id'] ) ),
					$titled === true ? str_replace( '[[titles]]', implode( '', $titles ), self::$html['table-head'] ) : '',
					$rows,
				],
				self::$html['table']
			);
		}

		/**
		 *	Replace [col]: one cell of a row of a table - nothing anywhere else, since
		 *	a cell with no table is none. What it shows is, in this order: the line of
		 *	text="" with its fields filled in (see _line()), the field="" of the element
		 *	as [text] shows a field, or - for a field of the type image - the picture as
		 *	[image] draws it, in the class nino-table__img; and nothing, an empty cell,
		 *	where it has neither. align is right or center. The first argument, the text
		 *	key of the title, is read by the table and not here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentCol( array &$appData, array $args ): string {

			if( ( $appData[self::TABLE] ?? false ) !== true )
				return '';

			$field = (string) $args['field'];

			if( isset( $args['text'] ) === true )
				$cell = self::_line( $appData, (string) $args['text'] );
			elseif( $field !== '' && ( self::element( $appData )['model'][$field]['type'] ?? '' ) === 'image' )
				$cell = self::_image( $appData, 'image', 'nino-image', [ 'source' => $field, 'alt' => '', 'content' => '', 'focus' => '', 'ratio' => '', 'class' => 'nino-table__img' ] );
			else
				$cell = $field !== '' ? (string) self::value( $appData, $field, 'text' ) : '';

			return str_replace(
				[ '[[class]]', '[[value]]' ],
				[ self::_attribute( 'class', $args['align'] !== '' ? 'nino-table__cell--'. $args['align'] : '' ), $cell ],
				self::$html['td']
			);
		}

		/**
		 *	Replace [line]: a line of text - a p, a span or a div of the class nino-line,
		 *	after it the class of the call - that is text="" with its fields filled in
		 *	(see _line()): [line text="[[price]],- EUR"]. Outside a loop only its site
		 *	texts are filled. Without a text="" it shows what its first argument is, as
		 *	[text] does. Nothing where the line comes out empty
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function componentLine( array &$appData, array $args ): string {

			$value = isset( $args['text'] ) === true ? self::_line( $appData, (string) $args['text'] ) : (string) $args['value'];

			return $value === '' ? '' : str_replace(
				[ '[[tag]]', '[[class]]', '[[lang]]', '[[value]]' ],
				[ $args['tag'], self::_modifier( '', $args['class'] ), self::_lang( $appData, $args ), $value ],
				self::$html['line']
			);
		}

		/**
		 *	A line of free text with its fields filled in, written safe: the one place
		 *	that fills the text of a [col] and of a [line]. A [[name]] is a field of the
		 *	element the line is drawn for, filled as the element's value is written
		 *	everywhere - escaped, a plain text whatever it holds - and a [[name]] that
		 *	begins with a slash is the site text of that key; a token that is neither
		 *	stays as it was written.
		 *	Every character of the text itself is escaped, so that a '<' of it is text.
		 *	A stack has put the fields of its element into the call by the time it is
		 *	drawn, as entities, and they are read back as the characters they stand for
		 *	before the text is escaped once
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$text					As written, or as a stack has filled it
		 *
		 *	@return 	string
		 */
		private static function _line( array &$appData, string $text ): string {

			$context	= self::element( $appData );
			$decode		= static fn( string $value ): string => html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$html			= '';

			foreach( preg_split( '/(\[\[[^\[\]]+\]\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [] as $index => $part ) {

				if( $index % 2 === 0 ) {
					$html .= self::escape( $decode( $part ) );
					continue;
				}

				$name = substr( $part, 2, -2 );

				if( $name[0] === '/' ) {

					$fill = \Nino\Html::resolveTextfill( $appData, $name );
					$html .= self::escape( $fill === null ? $part : $decode( $fill ) );
				}
				else if( $context !== null && ( $context['model'][$name]['type'] ?? '' ) === 'image' )
					continue;
				else if( $context !== null && isset( $context['model'][$name] ) === true && is_scalar( $context['element'][$name] ?? '' ) === true )
					$html .= self::escape( (string) ( $context['element'][$name] ?? '' ) );
				else
					$html .= self::escape( $part );
			}

			return $html;
		}

		/**
		 *	Replace [sections]: a section for each element, one after the other where
		 *	the call stands - what is between the tags is a section's own, its row
		 *	and its columns, drawn once per element with the element as the context.
		 *	The sections are the call's whole output, with no element around them, so
		 *	that what a page says of its first section and of its sections one after
		 *	the other stays true. A section has the class 'class' says after
		 *	nino-section, and an id where the call has one: the id and the number of
		 *	the element in the loop, counted from 1. With 'alternate' on, every
		 *	second section - the second, the fourth and so on - has the class
		 *	nino-section--mirrored after the classes of the call, and Nino.css turns
		 *	the row of such a section around from 768px. The class is written here
		 *	and no rule counts sections: two loops on a page count each for itself,
		 *	and a <section> that carries the class by hand is turned the same way
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					The arguments made ready by dispatch()
		 *
		 *	@return 	string
		 */
		public static function stackSections( array &$appData, array $args ): string {

			$id = self::_id( $args['id'] );

			return self::renderStack( $appData, $args, static function( string $inner, array $element, int $index ) use ( $id, $args ): string {

				// The index counts from 0: the second section is 1. The class goes into the [[class]] of the
				// fragment, behind the classes of the call, so that a project's own fragment needs no token for it
				$mirrored = $args['alternate'] === '1' && $index % 2 === 1;

				return str_replace(
					[ '[[class]]', '[[id]]', '[[inner]]' ],
					[ self::_modifier( '', $args['class'] ). self::_modifier( 'nino-section--', $mirrored === true ? 'mirrored' : '' ), self::_attribute( 'id', $id !== '' ? $id. '-'. ( $index + 1 ) : '' ), $inner ],
					self::$html['sections']
				);
			} );
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
