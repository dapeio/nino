<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Components					The register of the components a template is written in, and the drawing of a tree of them
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Components				The register of the components and the drawing of a tree.
	 *
	 *										A component is described, not programmed: a schema names the
	 *										places it may stand in, the places it offers to what it holds,
	 *										the source of its value, its attributes, its variants and the
	 *										markup it is drawn as; code is for the component that computes
	 *										(a renderer). validate() says what a schema may hold, addComponent()
	 *										registers one, and render() draws the nodes \Nino\Markup read.
	 *
	 *										The schema, every key but label optional:
	 *
	 *											'label'			a text, or a locale => text map
	 *											'group'			a slug a tool offers the component under
	 *											'in'				the places it may stand in; 'page' and 'flow' are the kernel's
	 *											'offers'		the places it offers to what it holds - present, even as [],
	 *																	makes it a container; its own name is always one of them
	 *											'once'			true: once in a container, which the caller of a tool enforces
	 *											'source'		text, image, link or none (the default): what its first word is
	 *											'format'		for a text: plain (the default), inline, lines or blocks; an attribute named
	 *																	format - a choice among them - says it for each call
	 *											'children'	'nodes': the register does not draw what the component holds, its renderer does
	 *											'attributes'	name => [ 'type' => choice|bool|int|string|text|image|link|id, 'default' => ..., ... ]
	 *											'variants'	the words of the one open axis; regular is always there, first
	 *											'template'	the markup, or the name of a *.tpl file in the directory a feature gives
	 *											'icon', 'preview'	for a tool
	 *
	 *										The markup of a template has [[class]], [[value]], [[content]],
	 *										[[content:<name>]] (what the children of that name drew), [[id]],
	 *										[[lang]] and [[<attribute>]] for each attribute that is no class.
	 *										It is filled in one pass: what a value brings is never looked at
	 *										again, so a value never becomes a call or a fill, and nothing a
	 *										value holds is written as an entity to keep it from being one.
	 *										A renderer has the template filled for it by fill(), with tokens of its own.
	 *
	 *										Where a tree is drawn from: the context
	 *
	 *											[ 'template' => 'page-home', 'scope' => 'hero', 'place' => 'page', 'element' => null, 'fills' => [], 'depth' => 0 ]
	 *
	 *										- the template whose words ~name reads, the id of the nearest
	 *										ancestor that has one - a call that has one itself reads its own words in
	 *										it - the place the nodes stand in, the element
	 *										of a loop (the shape [ 'uri', 'id', 'element', 'model' ] has), the tokens of the loops the nodes stand
	 *										in ('fills', [ '[[token]]' => text ]: a textfill with no slash reads them before the
	 *										element, the call of a shortcode has them put into its arguments, and a string, a text, a link and the
	 *										fixed text of a component the text of the field - see _fills()) and how
	 *										deep the nodes are.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Components {

		// Where the register keeps what it holds in $appData: the clean schemas by name in the order they were
		// registered, the renderers, the [[content:name]] each template names, the lists the methods named by an
		// attribute answered, what has been said in the log (once each), and the version
		public const string REGISTRY = './nino/register/components';
		public const string RENDERERS = './nino/register/renderers';
		public const string SLOTS = './nino/register/slots';
		public const string FILLS = './nino/register/fills';
		public const string OPTIONS = './nino/register/options';
		public const string NOTICED = './nino/register/noticed';
		public const string VERSION = './nino/register/version';

		// What the features that bring components have claimed: the groups by name - the feature, the words, the
		// knobs, whether the group has a standard part and the components in it - and the feature of every
		// name, see restore()
		public const string GROUPS = './nino/register/groups';
		public const string OWNERS = './nino/register/owners';

		// The hook that adds the variants a theme brings, see _variants()
		public const string VARIANTS = '/nino/components/variants';

		// The hook that is asked for the standard part of a group before it goes into the style bundle, see
		// \Nino\Features::registerParts()
		public const string PARTS = '/nino/components/parts';

		// What a group may say of a knob: how many of a component, how many steps of the ladder
		public const int KNOBS = 6;
		public const int STEPS = 5;

		// The words of the attribute ground, which a feature's component may only say as a choice among them: the contract of the
		// grounds, which Nino.css answers for the class <name>--ground-<word> of every component alike
		public const array GROUNDS = [ 'none', 'base', 'alt', 'tint', 'primary', 'dark' ];

		// What a component takes its first word from, see _reference()
		public const array SOURCES = [ 'text', 'image', 'link', 'none' ];

		// What an attribute may be
		public const array TYPES = [ 'choice', 'bool', 'int', 'string', 'text', 'image', 'link', 'id' ];

		// The words a bool is read from. The two lists \Nino\Feature::flag() reads a flag of a shortcode without a schema by as well
		public const array ON = [ '1', 'true', 'yes', 'on' ];
		public const array OFF = [ '0', 'false', 'no', 'off' ];

		// How a tool may draw an attribute, by its type: a hint for the tool and nothing the drawing reads
		public const array INPUTS = [
			'choice'	=> [ 'select', 'segment', 'icons' ],
			'bool'		=> [ 'switch', 'checkbox' ],
		];

		// The pictures a tool draws a component as
		public const array PREVIEWS = [ 'title', 'text', 'image', 'button', 'block', 'cells', 'table' ];

		// The two places the kernel knows - directly in the page, and in the flow of content - which no component
		// may be named for: allowed() reads a name that is none of a component as a place
		public const array PLACES = [ 'page', 'flow' ];

		// The names an attribute may not take: what the drawing writes besides the attributes
		// (the fixed text is read from 'text' by a component whose source is a text)
		public const array RESERVED = [ 'value', 'source', 'content', 'class', 'text', 'variant', 'lang' ];

		// The keys of a schema and of an attribute, which is all validate() accepts
		private const array KEYS = [ 'label', 'group', 'in', 'offers', 'once', 'through', 'source', 'format', 'children', 'attributes', 'variants', 'template', 'icon', 'preview' ];
		private const array ATTRIBUTE_KEYS = [ 'type', 'default', 'options', 'min', 'max', 'responsive', 'class', 'label', 'hint', 'input' ];

		// A component's and a place's name; an attribute's name, lowerCamel - \Nino\Markup reads more words than that (Markup::ATTRIBUTE);
		// the word of a variant or of an option that is written as a class; the value of an id; a field of an element
		public const string NAME = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D';
		private const string ATTRIBUTE = '/^[a-z][a-zA-Z0-9]*$/D';
		private const string WORD = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';
		private const string ID = '/^[a-z][a-z0-9-]*$/D';
		private const string KNOB = '/^([a-z][a-z0-9]*(?:-[a-z0-9]+)*)\.([a-z][a-z0-9]*(?:-[a-z0-9]+)*)$/D';
		private const string STEP = '/^[A-Za-z0-9 .,%()+*\/#-]{1,120}$/D';
		private const string FIELD = '/^[A-Za-z][A-Za-z0-9_-]*$/D';

		// What a reference to a text is written as: one word, with no bracket, no space and no control character in it
		private const string SHAPE = '/^[^\s\[\]\x00-\x1F\x7F]+$/D';

		// A token of a loop in the value of an attribute, [[title]] or [[.slug]], and what is read there: that, or a key of the site
		private const string TOKEN = '/\[\[(\/[^\[\]]+|\.?[A-Za-z][A-Za-z0-9_-]*)\]\]/';

		// The path of an internal page, as page:/path names it: the Element-URI of the page, with no way out of the site
		private const string PAGE = '#^/(?:[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*)?$#D';

		// The longest id
		private const int ID_LENGTH = 64;

		// The longest name of a component, of a place and of an attribute
		public const int NAME_LENGTH = 40;

		// The longest template, in bytes. A page is a few KB; a template of 64 KB is something else
		private const int TEMPLATE_LENGTH = 65536;

		// How many things the log is told about in one request: a page of names nobody registered is a log of its
		// own, and the names are an editor's
		private const int NOTICES = 20;

		// The counter of the things said once in a window (/data/component-notices.php), and the window of a name that is no component: an hour
		private const string NOTICE_COUNTER = 'component-notices';
		private const int UNKNOWN_WINDOW = 3600;

		// The alignments an input of icons is for
		private const array ALIGNMENTS = [
			[ 'left', 'center', 'right' ],
			[ 'top', 'middle', 'bottom', 'last-bottom' ],
			[ 'start', 'center', 'end' ],
		];

		// What an icon is made of: the tags a part may be, each with the attributes it takes (the radii of a rect may
		// be left out), how a value is written, how long it may be and what a message calls it, and how many parts an
		// icon has and how many characters its values have together
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

		/**
		 *	Register a component. The schema is checked by validate(), and a schema that is none is an
		 *	E_USER_ERROR, nothing registered. A
		 *	component that is registered again under its name takes the place of the first, which keeps its
		 *	position in the list - nothing is said
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The component, a slug
		 *	@param		array			$schema				See validate()
		 *	@param		callable|null	$renderer		( array &$appData, array $args, string $content, array $context ): string -
		 *																	for a component that computes; without one the template of the schema is drawn.
		 *																	$args holds each attribute as a string - the default where the call says none,
		 *																	a bool as '1' or '0', a string, a link and an image escaped - and 'value' (escaped,
		 *																	'' for a component without a source), 'source' as it is written (not escaped), 'class'
		 *																	(the classes, the name first), 'variant', 'lang' and 'id' as [[lang]] and [[id]] are
		 *																	written, with a space in front or nothing; an attribute of the type id is its slug
		 *																	under its own name (the tag form, ' id="..."', is the token [[id]] of a template).
		 *																	$content is what the children drew. What it answers is the end of it: nobody reads it again
		 *
		 *	@return 	void
		 */
		public static function addComponent( array &$appData, string $name, array $schema, ?callable $renderer = null ): void {

			$clean = self::validate( $name, $schema );

			if( is_string( $clean ) === true )
				trigger_error( 'Components::addComponent(): '. $clean, E_USER_ERROR );
			else if( $renderer === null && $clean['template'] === '' )
				trigger_error( 'Components::addComponent(): component "'. $name. '": a component without a renderer needs a "template"', E_USER_ERROR );
			else if( $renderer === null && isset( $clean['children'] ) === true )
				trigger_error( 'Components::addComponent(): component "'. $name. '": a component whose "children" are "nodes" needs a renderer, which draws them', E_USER_ERROR );
			else
				self::_store( $appData, $name, $clean, $renderer );
		}

		/**
		 *	Put a component that was checked into the register
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name
		 *	@param		array			$clean				The schema, as validate() answered it
		 *	@param		callable|null	$renderer
		 *	@param		string		$by						The method the registrant called, for the log
		 *
		 *	@return 	void
		 */
		private static function _store( array &$appData, string $name, array $clean, ?callable $renderer, string $by = 'addComponent' ): void {

			// A name that is a shortcode already: the register draws it and the shortcode is never asked - said in the log with both
			// registrants, as \Nino\Html::addShortcode() says it of the other order. The kernel has no name that is both
			if( array_key_exists( $name, (array) ( $appData['./nino/html/shortcodes'] ?? [] ) ) === true )
				self::_notice( $appData, 'shortcode '. $name, 'Components::'. $by. '(): "'. self::_shown( $name ). '" is a shortcode of '. \Nino\Callbacks::owners( $appData, '/nino/html/shortcode/'. $name )
					. ' already; the component of '. ( $appData[self::OWNERS]['names'][$name] ?? 'the register' ). ' takes its place and the shortcode is never drawn', E_USER_WARNING );

			$appData[self::REGISTRY][$name] = $clean;

			if( $renderer === null )
				unset( $appData[self::RENDERERS][$name] );
			else
				$appData[self::RENDERERS][$name] = $renderer;

			// The slots a template names are read once, here, and not at every drawing
			preg_match_all( '/\[\[content:([a-z][a-z0-9]*(?:-[a-z0-9]+)*)\]\]/', $clean['template'], $slots );
			$appData[self::SLOTS][$name] = array_values( array_unique( $slots[1] ) );

			// ...and so are the fills of the site it holds, written with a slash in front: looked up at every drawing, in the one pass
			preg_match_all( '/\[\[(\/[^\[\]\s]+)\]\]/', $clean['template'], $fills );
			$appData[self::FILLS][$name] = array_values( array_unique( $fills[1] ) );

			unset( $appData[self::VERSION] );
		}

		/**
		 *	Register what a feature brings from its folders, as \Nino\Features::readComponents() read and checked it
		 *	- the schemas are not checked again, which is what a request that takes them from the cache of the
		 *	register saves. What a renderer is for a component is the caller's to say, since it is code.
		 *
		 *	A name and a group belong to one feature: what another feature has claimed is refused, with a warning
		 *	that names both, and the feature goes on without it. A component whose group is refused goes with it.
		 *	The same feature that registers again takes the place of what it registered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$feature			The key of the feature
		 *	@param		array			$read					[ 'components' => name => clean schema, 'groups' => group => clean group ]
		 *	@param		array			$renderers		name => callable, for the components that compute
		 *
		 *	@return 	void
		 */
		public static function restore( array &$appData, string $feature, array $read, array $renderers ): void {

			$refused = [];

			foreach( (array) ( $read['groups'] ?? [] ) as $group => $declared ) {

				$owner = $appData[self::OWNERS]['groups'][$group] ?? null;

				if( $owner !== null && $owner !== $feature ) {

					$refused[$group] = true;
					self::_notice( $appData, 'group '. $group, 'Components: the group "'. self::_shown( (string) $group ). '" of the feature "'. $feature. '" is the feature "'. $owner. '"\'s - the feature goes on without it and its components', E_USER_WARNING );
					continue;
				}

				$appData[self::OWNERS]['groups'][$group] = $feature;
				$appData[self::GROUPS][$group] = [ 'feature' => $feature, 'label' => $declared['label'], 'knobs' => $declared['knobs'], 'part' => $declared['part'], 'components' => (array) ( $appData[self::GROUPS][$group]['components'] ?? [] ) ];
			}

			foreach( (array) ( $read['components'] ?? [] ) as $name => $clean ) {

				$name = (string) $name;
				$group = (string) ( $clean['group'] ?? '' );
				$owner = $appData[self::OWNERS]['names'][$name] ?? null;

				if( isset( $refused[$group] ) === true ) {

					self::_notice( $appData, 'name '. $name, 'Components: the component "'. self::_shown( $name ). '" of the feature "'. $feature. '" is in a group that is refused - the feature goes on without it', E_USER_WARNING );
					continue;
				}

				if( $owner !== null && $owner !== $feature ) {

					self::_notice( $appData, 'name '. $name, 'Components: the component "'. self::_shown( $name ). '" of the feature "'. $feature. '" is the feature "'. $owner. '"\'s - the feature goes on without it', E_USER_WARNING );
					continue;
				}

				$appData[self::OWNERS]['names'][$name] = $feature;
				self::_store( $appData, $name, $clean, isset( $renderers[$name] ) === true ? $renderers[$name] : null, 'restore' );

				if( in_array( $name, (array) ( $appData[self::GROUPS][$group]['components'] ?? [] ), true ) === false )
					$appData[self::GROUPS][$group]['components'][] = $name;
			}
		}

		/**
		 *	The groups the features that bring components have claimed, in the order they were registered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										group => [ 'feature' => key, 'label' => text or map, 'knobs' => as the group says them,
		 *																	'part' => whether the group has a standard part, 'components' => names in the order registered ]
		 */
		public static function groups( array &$appData ): array {

			return (array) ( $appData[self::GROUPS] ?? [] );
		}

		/**
		 *	Check what a group.php says and make it complete: a name and the knobs of the group. A knob is
		 *	'<component>.<name>' => [ 'token' => '--<component>-<name>', 'label' => text or map, 'ladder' => five
		 *	steps ], of a component of the group, at most KNOBS for a component. A step is a value of css the
		 *	compiler of a theme may write as it is: no quote, no semicolon, no brace, no comment, no url()
		 *
		 *	@param		string		$group				The group, a slug
		 *	@param		mixed			$declared			What group.php returned
		 *	@param		array			$components		The names of the components that are in the group
		 *
		 *	@return 	array|string						[ 'label' => ..., 'knobs' => ... ], or what is wrong with it
		 */
		public static function validateGroup( string $group, mixed $declared, array $components ): array|string {

			$what = 'group "'. self::_shown( $group ). '"';

			if( strlen( $group ) > self::NAME_LENGTH || preg_match( self::NAME, $group ) !== 1 )
				return $what. ': a name is a slug of lower case letters, digits and hyphens, at most '. self::NAME_LENGTH. ' characters';

			if( is_array( $declared ) === false )
				return $what. ': group.php must return an array';

			foreach( array_keys( $declared ) as $key )
				if( $key !== 'label' && $key !== 'knobs' )
					return $what. ': "'. self::_shown( (string) $key ). '" is no key of a group - there are label, knobs';

			$label = $declared['label'] ?? '';

			if( self::_words( $label ) === false || array_filter( (array) $label, static fn( mixed $text ): bool => trim( (string) $text ) === '' ) !== [] || $label === [] )
				return $what. ': "label" must be a string or a locale => string map, with no empty text';

			$knobs = $declared['knobs'] ?? [];

			if( is_array( $knobs ) === false )
				return $what. ': "knobs" must be a map of component.name => knob';

			$clean = [];
			$counts = [];

			foreach( $knobs as $key => $knob ) {

				$where = $what. ', knob "'. self::_shown( (string) $key ). '": ';

				if( is_string( $key ) === false || preg_match( self::KNOB, $key, $parts ) !== 1 || strlen( $key ) > 2 * self::NAME_LENGTH + 1 )
					return $where. 'the key is <component>.<name>, words of lower case letters, digits and hyphens';

				if( in_array( $parts[1], $components, true ) === false )
					return $where. '"'. $parts[1]. '" is no component of the group';

				$counts[$parts[1]] = ( $counts[$parts[1]] ?? 0 ) + 1;

				if( $counts[$parts[1]] > self::KNOBS )
					return $where. 'a component has '. self::KNOBS. ' knobs at most';

				if( is_array( $knob ) === false )
					return $where. 'a knob is an array of token, label and ladder';

				foreach( array_keys( $knob ) as $name )
					if( in_array( $name, [ 'token', 'label', 'ladder' ], true ) === false )
						return $where. '"'. self::_shown( (string) $name ). '" is no key of a knob - there are token, label, ladder';

				if( ( $knob['token'] ?? null ) !== '--'. $parts[1]. '-'. $parts[2] )
					return $where. '"token" is "--'. $parts[1]. '-'. $parts[2]. '"';

				$words = $knob['label'] ?? '';

				if( self::_words( $words ) === false || array_filter( (array) $words, static fn( mixed $text ): bool => trim( (string) $text ) === '' ) !== [] || $words === [] )
					return $where. '"label" must be a string or a locale => string map, with no empty text';

				$ladder = $knob['ladder'] ?? null;

				if( is_array( $ladder ) === false || array_is_list( $ladder ) === false || count( $ladder ) !== self::STEPS )
					return $where. '"ladder" is a list of '. self::STEPS. ' steps';

				foreach( $ladder as $step )
					if( self::validStep( $step ) === false )
						return $where. 'a step of the ladder is a value of css of at most 120 characters, with no quote, semicolon, brace, comment or url()';

				$clean[$key] = [ 'token' => $knob['token'], 'label' => $words, 'ladder' => $ladder ];
			}

			return [ 'label' => $label, 'knobs' => $clean ];
		}

		/**
		 *	Whether a text is a step of a ladder: a value of css the compiler of a theme may write as it is - at most 120
		 *	characters of letters, digits, spaces and . , % ( ) + * / # -, every bracket closed, no quote, semicolon, brace, comment or url()
		 *
		 *	@param		mixed			$step					What a ladder holds at a place
		 *
		 *	@return 	bool
		 */
		public static function validStep( mixed $step ): bool {
			if( is_string( $step ) === false || preg_match( self::STEP, $step ) !== 1 || str_contains( $step, '/*' ) === true || str_contains( $step, '*/' ) === true || preg_match( '/url\s*\(/i', $step ) === 1 )
				return false;

			// The brackets close in the order they opened: a calc( that is never closed swallows what the stylesheet says after it
			$open = 0;

			foreach( str_split( $step ) as $character ) {

				$open += $character === '(' ? 1 : ( $character === ')' ? -1 : 0 );

				if( $open < 0 )
					return false;
			}

			return $open === 0;
		}

		/**
		 *	What a standard part - the css of a group - does against the rule of mixing. It is read as css is - comments, strings
		 *	with their escapes, blocks - and not searched for patterns:
		 *
		 *	- no backslash outside a string: a part spells no name, selector or function with an escape
		 *	- every selector has a class of its own components (the name, or the name and a hyphen and more), or is :root; a class
		 *	  of another component, an id and an attribute selector on the class are none of its business, and neither is a
		 *	  selector with no class of its own (a type, `*`)
		 *	- it reads only --brand-..., --page-... and the tokens of its own components (--<component>-...) and sets only the
		 *	  latter, :root among the selectors as well
		 *	- no !important, no @import, @layer, @namespace or @charset, and nothing that loads from outside: url(), image-set(),
		 *	  -webkit-image-set(), src(), in any case
		 *
		 *	\Nino\Features::readComponents() asks it of the part of every group, and a suite of a feature asks it of the
		 *	parts it ships
		 *
		 *	@param		string		$css					The text of the part
		 *	@param		array			$components		The names of the components of the group
		 *
		 *	@return 	array										What it does wrong, one sentence each; [] where it keeps the rule
		 */
		public static function partProblems( string $css, array $components ): array {

			$problems = [];
			$escaped = false;

			// Comments become a blank, a string is what it is without its text, a backslash outside of one is said
			$plain = preg_replace_callback( '~/\*.*?\*/|"(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'|\\\\~s', static function( array $found ) use ( &$escaped ): string {

				if( $found[0] === '\\' )
					$escaped = true;

				return $found[0][0] === '/' || $found[0] === '\\' ? ' ' : "\x01";
			}, $css );

			if( $plain === null || preg_match( '/["\']|\/\*/', $plain ) === 1 )
				return [ 'cannot be read: a string or a comment is never closed, or it is too long for the check' ];

			if( $escaped === true )
				$problems[] = 'uses an escape (a backslash), which a part has no use for';

			$items = [];
			$at = 0;

			if( self::_blocks( $plain, $at, 0, $items ) === false || $at < strlen( $plain ) )
				return array_merge( $problems, [ 'cannot be read: its braces do not balance, or it is nested too deep' ] );

			self::_checkPart( $items, $components, false, $problems );

			return array_values( array_unique( $problems ) );
		}

		/**
		 *	The blocks of css: the statements one level holds, each a rule - its prelude and what it holds - or a declaration
		 *
		 *	@param		string		$css					Without comments and without the text of strings
		 *	@param		int				$at						(reference) Where to read from, and where it stopped
		 *	@param		int				$depth
		 *	@param		array			$items				(reference) [ 'rule' => prelude, 'in' => items ] or [ 'declaration' => text ]
		 *
		 *	@return 	bool										Whether the blocks balance
		 */
		private static function _blocks( string $css, int &$at, int $depth, array &$items ): bool {

			if( $depth > 16 )
				return false;

			$length = strlen( $css );

			while( $at < $length ) {

				$stop = strcspn( $css, ';{}', $at );
				$text = trim( substr( $css, $at, $stop ) );
				$at += $stop;

				if( $at >= $length ) {

					if( $text !== '' )
						$items[] = [ 'declaration' => $text ];

					return $depth === 0;
				}

				$mark = $css[$at++];

				if( $mark === '}' ) {

					if( $text !== '' )
						$items[] = [ 'declaration' => $text ];

					return $depth > 0;
				}

				if( $mark === ';' ) {

					if( $text !== '' )
						$items[] = [ 'declaration' => $text ];

					continue;
				}

				$inside = [];

				if( self::_blocks( $css, $at, $depth + 1, $inside ) === false )
					return false;

				$items[] = [ 'rule' => $text, 'in' => $inside ];
			}

			return $depth === 0;
		}

		/**
		 *	Hold the statements of a part to the rule of mixing
		 *
		 *	@param		array			$items				See _blocks()
		 *	@param		array			$components		The components of the group
		 *	@param		bool			$keyframes		The rules are the steps of an animation: no selectors
		 *	@param		array			$problems			(reference)
		 *	@param		bool			$owned				Whether the rules stand in a rule of the part's own
		 *
		 *	@return 	void
		 */
		private static function _checkPart( array $items, array $components, bool $keyframes, array &$problems, bool $owned = false ): void {

			foreach( $items as $item ) {

				if( isset( $item['declaration'] ) === true ) {

					self::_checkDeclaration( $item['declaration'], $components, $problems );
					continue;
				}

				$prelude = $item['rule'];

				if( str_starts_with( $prelude, '@' ) === true ) {

					self::_checkAt( $prelude, $problems );
					self::_checkPart( $item['in'], $components, preg_match( '/^@(?:-[a-z]+-)?keyframes\b/i', $prelude ) === 1, $problems, $owned );
					continue;
				}

				// A rule nested in this one stands on its elements with &: that holds where this rule is the part's own
				$inner = $keyframes === false ? self::_checkSelector( $prelude, $components, $problems, $owned ) : false;

				self::_checkPart( $item['in'], $components, false, $problems, $inner );
			}
		}

		/**
		 *	A declaration, or an at-rule that has no block (@import "x";)
		 *
		 *	@param		string		$text
		 *	@param		array			$components
		 *	@param		array			$problems			(reference)
		 *
		 *	@return 	void
		 */
		private static function _checkDeclaration( string $text, array $components, array &$problems ): void {

			if( str_starts_with( $text, '@' ) === true ) {

				self::_checkAt( $text, $problems );
				return;
			}

			$colon = strpos( $text, ':' );

			if( $colon === false )
				return;

			$property = trim( substr( $text, 0, $colon ) );
			$value = substr( $text, $colon + 1 );

			if( str_starts_with( $property, '--' ) === true && self::_ownToken( $property, $components ) === false )
				$problems[] = 'sets '. self::_shown( $property ). ', which is no token of its own components';

			if( preg_match( '/!\s*important/i', $value ) === 1 )
				$problems[] = 'uses !important';

			if( preg_match( '/(?<![A-Za-z0-9_])(?:-[a-z]+-)?(?:url|image-set|src)\s*\(/i', $value ) === 1 )
				$problems[] = preg_match( '/(?<![A-Za-z0-9_])url\s*\(/i', $value ) === 1 ? 'uses url()' : 'loads from outside: it uses image-set() or src()';

			if( preg_match_all( '/(?<![A-Za-z0-9_-])var\(\s*(--[^\s,)]*)/i', $value, $read ) > 0 )
				foreach( array_unique( $read[1] ) as $token )
					if( str_starts_with( $token, '--brand-' ) === false && str_starts_with( $token, '--page-' ) === false && self::_ownToken( $token, $components ) === false )
						$problems[] = 'reads '. self::_shown( $token ). ', which is no token of the brand, of the page or of its own components';
		}

		/**
		 *	The at-rules a part has none of
		 *
		 *	@param		string		$prelude
		 *	@param		array			$problems			(reference)
		 *
		 *	@return 	void
		 */
		private static function _checkAt( string $prelude, array &$problems ): void {

			if( preg_match( '/^@([A-Za-z-]+)/', $prelude, $name ) === 1 && in_array( strtolower( $name[1] ), [ 'import', 'layer', 'namespace', 'charset' ], true ) === true )
				$problems[] = 'uses @'. strtolower( $name[1] );
		}

		/**
		 *	The selectors of a rule: each starts from an element of the components of the group or is :root, and none meets what is not theirs
		 *
		 *	@param		string		$prelude
		 *	@param		array			$components
		 *	@param		array			$problems			(reference)
		 *	@param		bool			$owned				Whether the rule stands in a rule whose selectors all are the part's own
		 *
		 *	@return 	bool										Whether every selector of the list is the part's own: what a rule nested in this one is relative to
		 */
		private static function _checkSelector( string $prelude, array $components, array &$problems, bool $owned = false ): bool {

			// The commas of a list, not the ones inside a :is( , ) or an attribute
			$list = [];
			$depth = 0;
			$one = '';

			foreach( str_split( $prelude ) as $char ) {

				if( $char === '(' || $char === '[' )
					$depth++;
				else if( $char === ')' || $char === ']' )
					$depth--;

				if( $char === ',' && $depth === 0 ) {
					$list[] = $one;
					$one = '';
				}
				else
					$one .= $char;
			}

			$list[] = $one;

			$all = true;

			foreach( $list as $selector ) {

				$selector = trim( $selector );

				if( $selector === ':root' ) {
					$all = false;
					continue;
				}

				preg_match_all( '/\.([A-Za-z_][A-Za-z0-9_-]*)/', $selector, $classes );
				$foreign = false;

				foreach( array_unique( $classes[1] ) as $class ) {

					$own = false;

					foreach( $components as $component )
						$own = $own === true || $class === $component || str_starts_with( $class, $component. '-' ) === true;

					if( $own === false )
						$foreign = true;

					if( $own === false )
						$problems[] = 'meets the class .'. self::_shown( $class ). ', which is no class of its own components';
				}

				if( preg_match( '/#[A-Za-z_-]/', $selector ) === 1 )
					$problems[] = 'meets an id: '. self::_shown( $selector );

				// An attribute selector on class is a way round the rule - [class*="section"] meets what it likes - but for
				// one that only narrows an element the part owns: .section-col:where( [class*="--ground-"] ) is the column
				// with a ground, and meets no element that .section-col does not meet
				if( preg_match( '/\[\s*(?:[A-Za-z0-9_|*-]*\|)?class\b/i', $selector ) === 1 && self::_narrows( $selector, $components ) === false )
					$problems[] = 'meets the classes of the page by an attribute selector: '. self::_shown( $selector );

				// The element the selector speaks of first has to be the part's own: a class of its components at the top of
				// the selector, outside every function - *:not(.own) and :is(body, .own) name the class and meet the page -
				// or the selector is nested in a rule of its own, which it is relative to. At the top of the sheet, and under :root, & is the
				// root of the page
				$top = '';
				$depth = 0;

				foreach( str_split( $selector ) as $char ) {

					if( $char === '(' || $char === '[' )
						$depth++;
					else if( $char === ')' || $char === ']' )
						$depth--;
					else if( $depth === 0 )
						$top .= $char;
				}

				$stands = $owned;
				preg_match_all( '/\.([A-Za-z_][A-Za-z0-9_-]*)/', $top, $named );

				foreach( $named[1] as $class )
					foreach( $components as $component )
						$stands = $stands === true || $class === $component || str_starts_with( $class, $component. '-' ) === true;

				// A selector that was told of a foreign class has its sentence already
				if( $stands === false ) {

					$all = false;

					if( $foreign === false )
						$problems[] = 'has a selector with no class of its own components: '. self::_shown( $selector );
				}
			}

			return $all;
		}

		/**
		 *	Whether every attribute selector on class of a selector stands on an element the part owns: in a
		 *	compound that names a class of the components by itself, the attribute either beside that class or
		 *	in a function on it - :where(), :is(), :not(), never :has() - whose arguments are compounds with no
		 *	combinator, so that they speak of the same element
		 *
		 *	@param		string		$selector			One selector of a list, strings taken out
		 *	@param		array			$components		The names of the components of the group
		 *
		 *	@return 	bool
		 */
		private static function _narrows( string $selector, array $components ): bool {

			// The compounds of the top level: cut at the combinators that stand in no bracket
			$compounds = [];
			$depth = 0;
			$one = '';

			foreach( str_split( $selector ) as $char ) {

				if( $char === '(' || $char === '[' )
					$depth++;
				else if( $char === ')' || $char === ']' )
					$depth--;

				if( $depth === 0 && ( $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === '>' || $char === '+' || $char === '~' ) ) {
					$compounds[] = $one;
					$one = '';
				}
				else
					$one .= $char;
			}

			$compounds[] = $one;

			foreach( $compounds as $compound ) {

				if( preg_match( '/\[\s*(?:[A-Za-z0-9_|*-]*\|)?class\b/i', $compound ) !== 1 )
					continue;

				// The class of its own at the top of the compound, outside every function
				$top = '';
				$depth = 0;

				foreach( str_split( $compound ) as $char ) {

					if( $char === '(' )
						$depth++;
					else if( $char === ')' )
						$depth--;
					else if( $depth === 0 )
						$top .= $char;
				}

				$own = false;
				preg_match_all( '/\.([A-Za-z_][A-Za-z0-9_-]*)/', (string) preg_replace( '/\[[^\]]*\]/', '', $top ), $classes );

				foreach( $classes[1] as $class )
					foreach( $components as $component )
						$own = $own === true || $class === $component || str_starts_with( $class, $component. '-' ) === true;

				if( $own === false )
					return false;

				// Inside its functions no combinator: what is left once the attributes are taken out has no blank
				// between two parts and none of > + ~
				$inside = (string) preg_replace( '/\[[^\]]*\]/', 'A', $compound );

				if( preg_match( '/[>+~]|[^\s,(]\s+[^\s,)]/', $inside ) === 1 || stripos( $inside, ':has(' ) !== false )
					return false;
			}

			return true;
		}

		/**
		 *	Whether a custom property is the token of one of the components: --<component>-...
		 *
		 *	@param		string		$token
		 *	@param		array			$components
		 *
		 *	@return 	bool
		 */
		private static function _ownToken( string $token, array $components ): bool {

			foreach( $components as $component )
				if( str_starts_with( $token, '--'. $component. '-' ) === true )
					return true;

			return false;
		}

		/**
		 *	Check a schema and make it complete: the answer is the schema with every key but the optional ones
		 *	(group, offers, format, children, icon) present, or what is wrong with it. What it answers is a schema it
		 *	accepts again as it is. Anything the schema does not name is refused, not left out
		 *
		 *	@param		string		$name					The component, a slug of at most 40 characters that is none of the places of the kernel
		 *	@param		mixed			$schema				See the class
		 *	@param		string|null	$feature			The key of the feature whose manifest the schema is read from: a slug, named in what is wrong
		 *	@param		string|null	$directory		The directory of that feature, which a template named by a file is read from: a name of
		 *																	a *.tpl file in it, without a path that leaves it, whose file is there. Without one
		 *																	a template is markup
		 *
		 *	@return 	array|string						The clean schema, or what is wrong with it
		 */
		public static function validate( string $name, mixed $schema, ?string $feature = null, ?string $directory = null ): array|string {

			if( $feature !== null && ( strlen( $feature ) > self::NAME_LENGTH || preg_match( self::NAME, $feature ) !== 1 ) )
				return 'the feature "'. self::_shown( $feature ). '" is no slug';

			$what = ( $feature === null ? '' : 'feature "'. $feature. '", ' ). 'component "'. self::_shown( $name ). '"';

			if( strlen( $name ) > self::NAME_LENGTH || preg_match( self::NAME, $name ) !== 1 )
				return $what. ': a name is a slug of lower case letters, digits and hyphens, at most '. self::NAME_LENGTH. ' characters';

			if( in_array( $name, self::PLACES, true ) === true )
				return $what. ': "'. $name. '" is a place of the kernel and no name of a component';

			if( is_array( $schema ) === false )
				return $what. ': the schema must be an array';

			foreach( array_keys( $schema ) as $key )
				if( is_string( $key ) === false || in_array( $key, self::KEYS, true ) === false )
					return $what. ': "'. self::_shown( (string) $key ). '" is no key of a schema - there are '. implode( ', ', self::KEYS );

			if( self::_words( $schema['label'] ?? '' ) === false )
				return $what. ': "label" must be a string or a locale => string map';

			$clean = [ 'label' => $schema['label'] ];

			if( isset( $schema['group'] ) === true ) {

				if( is_string( $schema['group'] ) === false || strlen( $schema['group'] ) > self::NAME_LENGTH || preg_match( self::NAME, $schema['group'] ) !== 1 )
					return $what. ': "group" is a slug of lower case letters, digits and hyphens';

				$clean['group'] = $schema['group'];
			}

			// What a component stands in and what it offers, always in this order
			$clean['in'] = [];

			foreach( [ 'in', 'offers' ] as $key ) {

				if( isset( $schema[$key] ) === false )
					continue;

				$places = self::_places( $schema[$key] );

				if( is_string( $places ) === true )
					return $what. ': "'. $key. '" '. $places;

				$clean[$key] = $places;
			}

			if( isset( $schema['once'] ) === true && is_bool( $schema['once'] ) === false )
				return $what. ': "once" must be true or false';

			$clean['once'] = ( $schema['once'] ?? false ) === true;

			if( isset( $schema['through'] ) === true ) {

				if( is_bool( $schema['through'] ) === false )
					return $what. ': "through" must be true or false';

				if( $schema['through'] === true && isset( $clean['offers'] ) === false )
					return $what. ': "through" is for a container: it needs "offers"';

				if( $schema['through'] === true && ( $schema['children'] ?? null ) !== 'nodes' )
					return $what. ': "through" is for a component that draws what it holds itself: it needs "children" => "nodes"';

				if( $schema['through'] === true )
					$clean['through'] = true;
			}

			if( isset( $schema['children'] ) === true ) {

				if( $schema['children'] !== 'nodes' )
					return $what. ': "children" is "nodes", the one form there is: the renderer is handed what the component holds and draws it';

				$clean['children'] = 'nodes';
			}

			$source = $schema['source'] ?? 'none';

			if( is_string( $source ) === false || in_array( $source, self::SOURCES, true ) === false )
				return $what. ': "source" must be one of '. implode( ', ', self::SOURCES );

			$clean['source'] = $source;

			if( isset( $schema['format'] ) === true ) {

				if( $source !== 'text' )
					return $what. ': "format" is for a component whose source is a text';

				if( is_string( $schema['format'] ) === false || in_array( $schema['format'], \Nino\Html::FORMATS, true ) === false )
					return $what. ': "format" must be one of '. implode( ', ', \Nino\Html::FORMATS );
			}

			if( $source === 'text' )
				$clean['format'] = $schema['format'] ?? 'plain';

			$preview = $schema['preview'] ?? 'block';

			if( is_string( $preview ) === false || in_array( $preview, self::PREVIEWS, true ) === false )
				return $what. ': "preview" must be one of '. implode( ', ', self::PREVIEWS );

			$clean['preview'] = $preview;

			if( isset( $schema['icon'] ) === true ) {

				$icon = self::_icon( $what, $schema['icon'] );

				if( is_string( $icon ) === true )
					return $icon;

				$clean['icon'] = $icon;
			}

			// regular is always there and stands first; the others are words
			$variants = $schema['variants'] ?? [];

			if( is_array( $variants ) === false || array_is_list( $variants ) === false )
				return $what. ': "variants" must be a list of words';

			$clean['variants'] = [ 'regular' ];

			foreach( $variants as $variant )
				if( is_string( $variant ) === false || strlen( $variant ) > self::NAME_LENGTH || preg_match( self::WORD, $variant ) !== 1 )
					return $what. ': a variant is a word of lower case letters, digits and hyphens';
				else if( in_array( $variant, $clean['variants'], true ) === false )
					$clean['variants'][] = $variant;

			if( is_array( $schema['attributes'] ?? [] ) === false )
				return $what. ': "attributes" must be a map of attribute => schema';

			$clean['attributes'] = [];
			$ids = 0;

			foreach( (array) ( $schema['attributes'] ?? [] ) as $attribute => $declared ) {

				if( is_string( $attribute ) === false || strlen( $attribute ) > self::NAME_LENGTH || preg_match( self::ATTRIBUTE, $attribute ) !== 1 )
					return $what. ': the name of an attribute is a lowerCamel identifier of at most '. self::NAME_LENGTH. ' characters';

				if( in_array( $attribute, self::RESERVED, true ) === true )
					return $what. ': "'. $attribute. '" is a word the drawing reads itself and no name of an attribute';

				$result = self::_attributeSchema( $what, $attribute, $declared, $directory );

				if( is_string( $result ) === true )
					return $result;

				// [[id]] is the one attribute of the type id as it is written into a tag
				if( $attribute === 'id' && $result['type'] !== 'id' )
					return $what. ', attribute "id": the name id is for the attribute of the type id';

				if( $result['type'] === 'id' && ++$ids > 1 )
					return $what. ', attribute "'. $attribute. '": a component has one attribute of the type id at most';

				$clean['attributes'][$attribute] = $result;
			}

			// An attribute named format says for each call what the text may hold: a choice among the formats of a text, whose default
			// is the format of the schema where it names one
			if( isset( $clean['attributes']['format'] ) === true ) {

				$declared = $clean['attributes']['format'];
				$where = $what. ', attribute "format": ';

				if( $source !== 'text' )
					return $where. 'the name is for a component whose source is a text';

				if( $declared['type'] !== 'choice' || is_array( $declared['options'] ?? null ) === false )
					return $where. 'must be a choice whose options are listed, among '. implode( ', ', \Nino\Html::FORMATS );

				foreach( $declared['options'] as $option )
					if( in_array( $option, \Nino\Html::FORMATS, true ) === false )
						return $where. 'offers "'. self::_shown( $option ). '", which is none of '. implode( ', ', \Nino\Html::FORMATS );

				if( isset( $schema['format'] ) === true && $schema['format'] !== $declared['default'] )
					return $where. 'the default is the "format" of the schema, "'. $schema['format']. '"';

				$clean['format'] = $declared['default'];
			}

			$template = self::_template( $what, $schema['template'] ?? '', $directory );

			if( is_array( $template ) === false )
				return $template;

			$clean['template'] = $template[0];

			return $clean;
		}

		/**
		 *	The components registered, by name, in the order they were registered. The schemas as a tool reads
		 *	them: an attribute whose options are a method carries the list that method answers - the answer of
		 *	this request's first call - as 'options', the values, and 'optionLabels', value => label; one whose
		 *	method answered nothing readable is a 'string'
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => schema
		 */
		public static function components( array &$appData ): array {

			$schemas = (array) ( $appData[self::REGISTRY] ?? [] );

			foreach( $schemas as $name => $schema )
				foreach( $schema['attributes'] as $attribute => $declared )
					if( is_string( $declared['options'] ?? null ) === true )
						$schemas[$name]['attributes'][$attribute] = self::_resolve( $appData, (string) $name, (string) $attribute, $declared );

			return $schemas;
		}

		/**
		 *	What \Nino\Markup::parse() is told: which names are containers - they have an end - and which are
		 *	leaves, which never do
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => true for a container, false for a leaf
		 */
		public static function containers( array &$appData ): array {

			$containers = [];

			foreach( (array) ( $appData[self::REGISTRY] ?? [] ) as $name => $schema )
				$containers[$name] = isset( $schema['offers'] );

			return $containers;
		}

		/**
		 *	The components that may stand directly in another, in the order they were registered: those whose
		 *	'in' names a place the parent offers. A container offers the place of its own name and the places
		 *	it names; a leaf offers none. 'page' - and '' - is the page itself, which offers 'page'. A name that
		 *	is no component is a place, which offers itself ('flow', or one a feature opened)
		 *
		 *	A tool reads this, and so does whoever enforces 'once'; drawing does neither: a component at a
		 *	place that is not its own is drawn all the same
		 *
		 *	A container that is 'through' - a loop, which draws no element of its own round what it holds - has
		 *	no places of its own to offer: what may stand in it is what the container it stands in allows, so
		 *	that the columns of a loop in a row are the columns of the row, and not every place the loop offers
		 *	where it is not asked for. A tool that knows the container says it, the nearest one that is not
		 *	'through' itself (for a loop in a loop, the one the outer loop stands in); without one the answer is
		 *	what the places it offers say, as for every container
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$parent				The name of a container, a place, or '' for the page
		 *	@param		string		$container		Where $parent stands, the name of a container or a place; only a 'through' container reads it
		 *
		 *	@return 	array										The names
		 */
		public static function allowed( array &$appData, string $parent, string $container = '' ): array {

			$registry = (array) ( $appData[self::REGISTRY] ?? [] );
			$parent = $parent === '' ? 'page' : $parent;

			if( $container !== '' && ( $registry[$parent]['through'] ?? false ) === true )
				return self::allowed( $appData, $container );

			if( isset( $registry[$parent] ) === false )
				$offers = [ $parent ];
			else if( isset( $registry[$parent]['offers'] ) === false )
				return [];
			else
				$offers = array_merge( [ $parent ], $registry[$parent]['offers'] );

			$names = [];

			foreach( $registry as $name => $schema )
				if( array_intersect( $schema['in'], $offers ) !== [] )
					$names[] = (string) $name;

			return $names;
		}

		/**
		 *	What a tree that was read - or kept - with these components depends on: the sha1 of every clean schema,
		 *	in the order they were registered. The same for the same registrations in every request, another for
		 *	every schema that changes, comes or goes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		public static function version( array &$appData ): string {

			if( is_string( $appData[self::VERSION] ?? null ) === false )
				$appData[self::VERSION] = sha1( serialize( (array) ( $appData[self::REGISTRY] ?? [] ) ) );

			return $appData[self::VERSION];
		}

		/**
		 *	Draw the nodes of a tree. The order of what is done for a call is the contract:
		 *
		 *	1. a name nobody registered draws nothing, and nor do its children (_unknown())
		 *	2. the attributes are made ready, the id first, since it is the scope of what the call reads itself: a foreign
		 *	   one falls away, a value outside its options is the default, a bool is read from its words, an int is kept
		 *	   within its bounds, an id is a word or nothing, a string is escaped, a text is a reference or the text as it
		 *	   is written, a link is an address a browser may be sent to, an image is the reference to a picture. In a
		 *	   loop a string, a text, a link and a fixed text hold the tokens of the round put in - the text of the field,
		 *	   see _fills() The
		 *	   classes: the name, then for each attribute that is a class in the order of the schema - a choice that
		 *	   is not its default as name--attribute-value, a bool that is on as name--attribute; a responsive choice
		 *	   ('100 m:50 l:33') says the width for all in its first part, which is a class where it is not the default,
		 *	   and from the breakpoint on of Markup::BREAKPOINTS in the parts that follow, each a class of its own
		 *	3. the variant: the list of the schema and of the callbacks of '/nino/components/variants', the standard
		 *	   where the call names none that is in it; a class where it is not regular
		 *	4. the source: a fixed text="..." (a text), ~name (the key /template/<template>/<scope>/<name>), a key
		 *	   with a slash in front, .field of the element - a text, a link (an address, a key whose text is one, a
		 *	   field) or an image (the reference, as it is written, not looked up). Never read as markup, escaped for
		 *	   its place (a text by its format, which an attribute named format says for the call), and with every [
		 *	   left as it is
		 *	5. a component with a source and no value draws nothing, nor do its children
		 *	6. an attribute of the type id gives the children the scope its value names - and the words of the call itself
		 *	7. the children, at the place of this component, one deeper than it - or, for a component whose 'children' is
		 *	   'nodes', not at all: the renderer is handed them
		 *	8. the template is filled, or the renderer is asked
		 *
		 *	A text is drawn as it is. A textfill is looked up in the fills of the language (\Nino\Html::getFills()),
		 *	which are the site's words and go into the page as they are - a key nobody has a text for stays written
		 *	as it was, as a page has it; a key without a slash is a field of the element, or stays written
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$nodes				What \Nino\Markup::parse() answered
		 *	@param		array			$context			See the class; what is missing is the default: no template, no scope, the place 'page', no element, depth 0
		 *
		 *	@return 	string
		 */
		public static function render( array &$appData, array $nodes, array $context = [] ): string {

			$element = $context['element'] ?? null;

			$context = [
				'template'	=> is_string( $context['template'] ?? null ) === true ? $context['template'] : '',
				'scope'			=> is_string( $context['scope'] ?? null ) === true ? $context['scope'] : '',
				'place'			=> is_string( $context['place'] ?? null ) === true ? $context['place'] : 'page',
				'element'		=> is_array( $element ) === true ? $element : null,
				'fills'			=> is_array( $context['fills'] ?? null ) === true ? $context['fills'] : [],
				'depth'			=> is_int( $context['depth'] ?? null ) === true ? max( 0, $context['depth'] ) : 0,
			];

			$run = [
				'registry'	=> (array) ( $appData[self::REGISTRY] ?? [] ),
				'renderers'	=> (array) ( $appData[self::RENDERERS] ?? [] ),
				'slots'			=> (array) ( $appData[self::SLOTS] ?? [] ),
				'tfills'		=> (array) ( $appData[self::FILLS] ?? [] ),
				'fills'			=> null,
				'variants'	=> [],
				'plans'			=> [],
			];

			return self::_draw( $appData, $nodes, $context, $run );
		}

		/**
		 *	A string made safe to write into html text or into the value of an attribute. A [ stays one: what
		 *	is drawn is the end of it, and nobody reads it for a call or a fill
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		public static function escape( string $value ): string {
			return htmlspecialchars( self::clean( $value ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}

		/**
		 *	A value without the control characters html has no use for: U+0000 to U+0008, U+000B, U+000C, U+000E to
		 *	U+001F and U+007F. A tab, a line break and a carriage return stay. Every value the register writes - a text,
		 *	an attribute, a field, a fill of a template - goes through it
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		public static function clean( string $value ): string {
			return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value ) ?? '';
		}

		/**
		 *	The template of a component, filled as the register fills it - the placeholders of the class, the
		 *	words and the values of the call, [[content]] and [[content:name]], the fills of the site the template
		 *	names - with tokens of the renderer's own added, in one pass. A renderer that has markup to leave out
		 *	or to add computes it and puts it in as a token of its own, so that the markup stays in the template
		 *	of its component; what the tokens hold is what the renderer made, escaped by it. Nothing a value
		 *	holds, of a call or of a token, is read again
		 *
		 *	Where a token has the name of a placeholder, the token wins. A component nobody registered, or one that
		 *	has no template, is ''
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The component, usually the one the renderer is of
		 *	@param		array			$args					What the renderer was handed, changed as it likes: the
		 *																	strings under the names of its attributes, value, class, lang
		 *																	and content:<name> for each slot; what is not there is nothing
		 *	@param		string		$content			What the children drew, [[content]]
		 *	@param		array			$tokens				[ '[[token]]' => markup ]
		 *
		 *	@return 	string
		 */
		public static function fill( array &$appData, string $name, array $args, string $content, array $tokens ): string {

			$schema = $appData[self::REGISTRY][$name] ?? null;

			if( $schema === null )
				return '';

			// What a renderer did to the fills is seen here, since the table is read where it is first needed
			$run = [ 'fills' => null, 'slots' => (array) ( $appData[self::SLOTS] ?? [] ), 'tfills' => (array) ( $appData[self::FILLS] ?? [] ), 'plans' => [] ];
			$own = [];

			foreach( $tokens as $token => $markup )
				if( is_scalar( $markup ) === true )
					$own[(string) $token] = (string) $markup;

			return strtr( $schema['template'], $own + self::_placeholders( $run, $name, $schema, $args, $content ) + self::_site( $appData, $run, $name ) );
		}

		/**
		 *	The texts and the pictures a source reads through the register: what a tool that looks for the keys a
		 *	template uses, or for the slots of its pictures, asks. It is the source as it is written - the tree of
		 *	it, read once and not kept - and nothing is drawn or looked up: a value is no call, and a value that
		 *	holds one, or a fill, reads nothing
		 *
		 *	Of each call of a registered component it lists, in the order they stand, the first word where its
		 *	source is a text, an image or a link, and each attribute of the type text, image or link - the
		 *	default where the call says none - that names a key: ~name, which the template and the nearest id
		 *	above the call - its own, where it has one - make a key of, and a key with a slash in front. A link
		 *	names a key only as ~name: written with a slash it is an address as well, which the drawing decides.
		 *	Not listed: a fixed text="...", a .field, what has a space, a bracket or a control character in it,
		 *	a ~name where there is no template or no id, a name nobody registered, a fill that stands in the
		 *	source as a textfill with a slash in front and the template a [template] includes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source				html+
		 *	@param		string		$template			The name the words of ~name are read under, what \Nino\Html::context() calls 'template'
		 *
		 *	@return 	array										[ [ 'name' => 'section-title', 'kind' => 'text', 'source' => '/template/page-home/hero/title', 'written' => '~title' ], ... ];
		 *																	an attribute adds 'attribute' => the name of it
		 */
		public static function sources( array &$appData, string $source, string $template ): array {

			$found = [];

			self::_sources( (array) ( $appData[self::REGISTRY] ?? [] ), \Nino\Html::tree( $appData, $source, false ), $template, '', 0, $found );

			return $found;
		}

		/**
		 *	The sources of the nodes of one level, and below
		 *
		 *	@param		array			$registry			The clean schemas
		 *	@param		array			$nodes
		 *	@param		string		$template
		 *	@param		string		$scope				The id of the nearest call above
		 *	@param		int				$depth
		 *	@param		array			&$found				(reference)
		 *
		 *	@return 	void
		 */
		private static function _sources( array $registry, array $nodes, string $template, string $scope, int $depth, array &$found ): void {

			if( $depth > Markup::MAX_DEPTH )
				return;

			foreach( $nodes as $node ) {

				if( is_array( $node ) === false || is_string( $node['n'] ?? null ) === false )
					continue;

				$inner = $scope;
				$schema = $registry[$node['n']] ?? null;

				if( $schema !== null ) {

					$given = is_array( $node['a'] ?? null ) === true ? $node['a'] : [];

					foreach( $schema['attributes'] as $attribute => $declared )
						if( $declared['type'] === 'id' ) {

							$own = self::_id( $declared, $given[$attribute] ?? null );
							$inner = $own === '' ? $scope : $own;
							break;
						}

					if( $schema['source'] !== 'none' && ( $schema['source'] !== 'text' || is_string( $given['text'] ?? null ) === false ) ) {

						$written = is_string( $node['s'] ?? null ) === true ? $node['s'] : '';
						$key = self::_key( $written, $schema['source'], $template, $inner );

						if( $key !== null )
							$found[] = [ 'name' => $node['n'], 'kind' => $schema['source'], 'source' => $key, 'written' => $written ];
					}

					foreach( $schema['attributes'] as $attribute => $declared )
						if( in_array( $declared['type'], [ 'text', 'image', 'link' ], true ) === true ) {

							$written = is_string( $given[$attribute] ?? null ) === true ? $given[$attribute] : $declared['default'];
							$key = self::_key( $written, $declared['type'], $template, $inner );

							if( $key !== null )
								$found[] = [ 'name' => $node['n'], 'kind' => $declared['type'], 'source' => $key, 'written' => $written, 'attribute' => (string) $attribute ];
						}
				}

				if( is_array( $node['c'] ?? null ) === true )
					self::_sources( $registry, $node['c'], $template, $inner, $depth + 1, $found );
			}
		}

		/**
		 *	The key a written source names, where it names one a scan can know: ~name is of the template and the scope, a
		 *	key with a slash in front is as it is written, but for a link, which has no such. What has a space, a
		 *	bracket or a control character in it names no key
		 *
		 *	@param		string		$written
		 *	@param		string		$kind					text, image or link
		 *	@param		string		$template
		 *	@param		string		$scope
		 *
		 *	@return 	string|null
		 */
		private static function _key( string $written, string $kind, string $template, string $scope ): ?string {

			if( $written === '' || ( $written[0] !== '~' && $written[0] !== '/' ) || preg_match( self::SHAPE, $written ) !== 1 )
				return null;

			if( $written[0] === '/' )
				return $kind === 'link' ? null : $written;

			if( $template === '' || $scope === '' || $written === '~' )
				return null;

			$key = '/template/'. $template. '/'. $scope. '/'. substr( $written, 1 );

			return preg_match( self::SHAPE, $key ) === 1 ? $key : null;
		}

		/**
		 *	The nodes of one level, one after another
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$nodes
		 *	@param		array			$context			Whole: all five keys
		 *	@param		array			&$run					(reference) What one render() shares: the register, the fills, the variants
		 *
		 *	@return 	string
		 */
		private static function _draw( array &$appData, array $nodes, array $context, array &$run ): string {

			// A tree built by hand can be as deep as it likes; the parser stops at the same depth
			if( $context['depth'] > Markup::MAX_DEPTH )
				return '';

			$html = '';

			foreach( $nodes as $node ) {

				// What is no node - a string, a number, nothing - has none of the keys, and is nothing
				if( isset( $node['t'] ) === true )
					$html .= is_string( $node['t'] ) === true ? $node['t'] : '';
				else if( isset( $node['f'] ) === true )
					$html .= is_string( $node['f'] ) === true ? self::_fill( $appData, $node['f'], $context, $run ) : '';
				else if( isset( $node['n'] ) === true && is_string( $node['n'] ) === true )
					$html .= self::_call( $appData, $node, $context, $run );
			}

			return $html;
		}

		/**
		 *	A textfill: the text of a key in the language of the page, as the fills hold it, or a field of the element
		 *
		 *	A key with a slash in front is looked up in \Nino\Html::getFills() - the table is read once for a
		 *	drawing - and what it holds goes into the page as it is, as the fills of a page always have: they are
		 *	the site's own words and the project's to write markup into (a fill that holds another is resolved, at
		 *	most ten passes, by \Nino\Html::resolveTextfill()). A fill inside the key is put in first, as the
		 *	fills of a page are. A key nobody has a text for - an empty text is one - stays written, [[key]],
		 *	as a page keeps one. A key without a slash is a field of the element, as \Nino\Modules\Elements::fills()
		 *	writes it - [[.id]], [[.uri]], [[.slug]], [[.lang]] and each field, escaped by the type of its field; one
		 *	the element has no field for, or there is no element, stays written, as an unknown fill does: other code
		 *	of the page fills such a token later
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Without brackets
		 *	@param		array			$context
		 *	@param		array			&$run					(reference)
		 *
		 *	@return 	string
		 */
		private static function _fill( array &$appData, string $key, array $context, array &$run ): string {

			if( $key === '' )
				return '';

			if( $key[0] !== '/' ) {

				// The tokens of a loop the call stands in, the innermost first; then the field of an element
				if( isset( $context['fills']['[['. $key. ']]'] ) === true && is_scalar( $context['fills']['[['. $key. ']]'] ) === true )
					return (string) $context['fills']['[['. $key. ']]'];

				// What no element has a field for is written as it stands: the code that draws the page may fill it later
				$element = self::_element( $context['element'] );
				$fills = $element === null ? [] : \Nino\Modules\Elements::fills( $element['element'], $element['model'], $element['id'] );

				return isset( $fills['[['. $key. ']]'] ) === false ? '[['. $key. ']]' : str_replace( '&#91;', '[', (string) $fills['[['. $key. ']]'] );
			}

			$key = self::_inner( $appData, $key );
			$value = self::_lookup( $appData, $run, $key );

			return $value === null ? '[['. $key. ']]' : $value;
		}

		/**
		 *	The fills inside a key put in, one level, as a page puts them in: a fill nobody has a text for stays
		 *	written. The one place that reads a key with a fill in it - a textfill, the source of a call
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *
		 *	@return 	string
		 */
		private static function _inner( array &$appData, string $key ): string {

			if( str_contains( $key, '[[' ) === false )
				return $key;

			return (string) preg_replace_callback( '/\[\[([^\[\]]+)\]\]/', function( array $inner ) use ( &$appData ): string {
				return \Nino\Html::resolveTextfill( $appData, $inner[1] ) ?? $inner[0];
			}, $key );
		}

		/**
		 *	The text of a value with the fills of the site in it put in - every one with a slash in front, as a
		 *	fixed text of a call has always had them: the text of the key in the language of the page, nothing where
		 *	nobody has one. What is put in is a text like the rest and is escaped with it
		 *
		 *	In a loop the tokens of its round are put in, in the same pass: the [[title]] and the [[.slug]] the
		 *	element has a field for. They are the loop's own and the value is no source for another one - what a token
		 *	holds is the text of the field, read as the text it is: it is never looked at for a fill, a key of the site
		 *	or a call, and a token the loop has no field for stays written. [[.lang]] is markup, not a field, and is
		 *	none of them. $mode says how the text of a token is put in, since the value is read on by what the caller
		 *	does with it: 'raw' - it is escaped as it stands, a string -; 'text' - the text is read as written and
		 *	entities become their characters, so what goes in is escaped once more, which the reading undoes; 'link' -
		 *	the value is read as an address: a token at its start or after page: is the address (its scheme is looked
		 *	at by _address() afterwards), anywhere else it is one part of it and written as such, percent-encoded -
		 *	after mailto: or tel: with the @ and the + it needs left - so that a field cannot make a path of its own
		 *	of what it holds
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$run					(reference)
		 *	@param		string		$value
		 *	@param		array			$tokens				The fills of the loops the value stands in, [ '[[token]]' => escaped text ]; [] outside a loop
		 *	@param		string		$mode					raw, text or link
		 *
		 *	@return 	string
		 */
		private static function _fills( array &$appData, array &$run, string $value, array $tokens = [], string $mode = 'raw' ): string {

			if( str_contains( $value, '[[' ) === false )
				return $value;

			if( $tokens === [] )
				return (string) preg_replace_callback( '/\[\[(\/[^\[\]]+)\]\]/', function( array $found ) use ( &$appData, &$run ): string {
					return self::_lookup( $appData, $run, $found[1] ) ?? '';
				}, $value );

			return (string) preg_replace_callback( self::TOKEN, function( array $found ) use ( &$appData, &$run, $value, $tokens, $mode ): string {

				if( $found[1][0][0] === '/' )
					return self::_lookup( $appData, $run, $found[1][0] ) ?? '';

				$token = '[['. $found[1][0]. ']]';

				if( $found[1][0] === '.lang' || isset( $tokens[$token] ) === false || is_scalar( $tokens[$token] ) === false )
					return $found[0][0];

				$text = self::_tokenText( (string) $tokens[$token] );

				if( $mode === 'raw' )
					return $text;

				$before = substr( $value, 0, $found[0][1] );

				if( $mode === 'text' || $before === '' || $before === 'page:' )
					return self::escape( $text );

				if( preg_match( '/^[a-z][a-z0-9+.-]*:$/iD', $before ) === 1 )
					return str_replace( [ '%40', '%2B' ], [ '@', '+' ], rawurlencode( $text ) );

				return rawurlencode( $text );
			}, $value, -1, $count, PREG_OFFSET_CAPTURE );
		}

		/**
		 *	Whether a value holds a token of the loop it stands in - one the loop has a field for
		 *
		 *	@param		string		$value
		 *	@param		array			$tokens				The fills of the loops
		 *
		 *	@return 	bool
		 */
		private static function _holds( string $value, array $tokens ): bool {

			if( $tokens === [] || preg_match_all( self::TOKEN, $value, $found ) === 0 )
				return false;

			foreach( $found[1] as $name )
				if( $name !== '.lang' && $name[0] !== '/' && isset( $tokens['[['. $name. ']]'] ) === true )
					return true;

			return false;
		}

		/**
		 *	What a token of a loop says as a text: the text of the field, as the fill of the loop holds it - escaped for
		 *	html and, for a field that may hold some, with its tags - read back to the characters it is, so that the
		 *	caller escapes it for the place it writes it to. A line break of a field is a line break of the text
		 *
		 *	@param		string		$fill					The token of the loop
		 *
		 *	@return 	string
		 */
		private static function _tokenText( string $fill ): string {
			$fill = (string) preg_replace( '#<br\s*/?>(?:\r?\n)?#i', "\n", $fill );

			return trim( self::clean( html_entity_decode( strip_tags( \Nino\Html::breaksToNewlines( $fill ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		}

		/**
		 *	The text of a key as the fills hold it, with the fills inside it put in (at most ten passes, by
		 *	\Nino\Html::resolveTextfill()) as a page does. It is a text: what it holds is never read as a call
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$run					(reference)
		 *	@param		string		$key					Without brackets
		 *
		 *	@return 	string|null							Null where nobody has a text for the key
		 */
		private static function _lookup( array &$appData, array &$run, string $key ): ?string {

			// Read once for a drawing; a renderer may add a fill, and the next node sees it (see _call())
			if( $run['fills'] === null )
				$run['fills'] = \Nino\Html::getFills( $appData );

			$value = $run['fills']['[['. $key. ']]'] ?? null;

			if( is_scalar( $value ) === false )
				return null;

			$value = (string) $value;

			// The fills in it, from the table at hand, as \Nino\Html::resolveTextfill() puts them in: every fill that is one, at
			// most ten passes - resolveTextfill() builds the table again for every value that has a fill in it
			for( $pass = 0; $pass < 10 && str_contains( $value, '[[' ) === true; $pass++ ) {

				$fills = $run['fills'];
				$next = (string) preg_replace_callback( '/\[\[([^\[\]]+)\]\]/', static function( array $found ) use ( $fills ): string {
					return is_scalar( $fills[$found[0]] ?? null ) === true ? (string) $fills[$found[0]] : $found[0];
				}, $value );

				if( $next === $value )
					break;

				$value = $next;
			}

			return $value;
		}

		/**
		 *	One call, as the order in render() says
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$node					A call: n, s, a, c
		 *	@param		array			$context
		 *	@param		array			&$run					(reference)
		 *
		 *	@return 	string
		 */
		private static function _call( array &$appData, array $node, array $context, array &$run ): string {

			$name = (string) $node['n'];
			$schema = $run['registry'][$name] ?? null;

			if( $schema === null )
				return self::_unknown( $appData, $node, $context, $run );

			$given = is_array( $node['a'] ?? null ) === true ? $node['a'] : [];
			$source = is_string( $node['s'] ?? null ) === true ? $node['s'] : '';

			// 6. The id of the call, which is the scope of its children and of its own words as well: [section-wrap id="hero" image="~background"]
			// reads the key of the background of the hero. Found first, since an attribute that reads a key may stand before the id in the schema
			$plan = $run['plans'][$name] ?? self::_plan( $run, $name, $schema );
			$here = $context;
			$scope = $context['scope'];
			$own = '';

			if( $plan['id'] !== '' ) {

				$own = self::_id( $schema['attributes'][$plan['id']], $given[$plan['id']] ?? null );

				if( $own !== '' )
					$scope = $here['scope'] = $own;
			}

			// 2. The attributes, and the classes they make
			$class = $name;
			$args = [];
			$fills = [];
			$none = '';

			foreach( $schema['attributes'] as $attribute => $declared ) {

				$attribute = (string) $attribute;
				$raw = $given[$attribute] ?? null;

				if( is_string( $declared['options'] ?? null ) === true )
					$declared = self::_resolve( $appData, $name, $attribute, $declared );

				switch( $declared['type'] ) {

					case 'choice':

						if( ( $declared['responsive'] ?? false ) === true && is_string( $raw ) === true )
							[ $value, $more ] = self::_responsive( $raw, $declared['options'], $declared['default'], ' '. $name. '--', $attribute );
						else {
							$value = is_string( $raw ) === true && in_array( $raw, $declared['options'], true ) === true ? $raw : $declared['default'];
							$more = $declared['class'] === true && $value !== $declared['default'] && $value !== '' ? ' '. $name. '--'. $attribute. '-'. $value : '';
						}

						// An option that is no class is whatever its schema or its method says: written escaped
						$class .= $more;
						$value = self::escape( $value );
						break;

					case 'bool':

						// true is the bare word of a switch; a word that is in neither list is the default
						$word = is_string( $raw ) === true ? strtolower( $raw ) : '';
						$on = $raw === true || in_array( $word, self::ON, true ) === true ? true : ( in_array( $word, self::OFF, true ) === true ? false : $declared['default'] );
						$value = $on === true ? '1' : '0';

						if( $on === true && $declared['class'] === true )
							$class .= ' '. $name. '--'. $attribute;

						break;

					case 'int':

						$value = (string) $declared['default'];

						if( is_string( $raw ) === true && preg_match( '/^-?\d{1,9}$/D', $raw ) === 1 )
							$value = (string) max( $declared['min'] ?? PHP_INT_MIN, min( $declared['max'] ?? PHP_INT_MAX, (int) $raw ) );

						break;

					case 'id':

						$value = $own;
						break;

					case 'link':
					case 'image':

						$value = self::_reference( $appData, $run, $declared['type'], is_string( $raw ) === true ? $raw : $declared['default'], false, $here, 'plain', $none ) ?? '';
						break;

					case 'text':

						// A second text of a call, a caption or a label: a reference to one (~name, a key, .field) as the first word
						// is, or the text as it is written, with the fills of the site in it
						$written = is_string( $raw ) === true ? $raw : $declared['default'];
						$value = self::_isReference( $written ) === true
							? ( self::_reference( $appData, $run, 'text', $written, true, $here, 'plain', $none ) ?? '' )
							: self::_text( self::_fills( $appData, $run, $written, $context['fills'], 'text' ), 'plain' );
						break;

					default:

						$value = self::escape( is_string( $raw ) === true ? self::_fills( $appData, $run, $raw, $context['fills'], 'raw' ) : $declared['default'] );
				}

				$args[$attribute] = $value;

				if( $declared['class'] === false )
					$fills['[['. $attribute. ']]'] = $value;
			}

			// 3. The variant
			[ $variants, $standard ] = self::_variants( $appData, $run, $name, $schema );

			$variant = is_string( $given['variant'] ?? null ) === true && in_array( $given['variant'], $variants, true ) === true ? $given['variant'] : $standard;

			if( $variant !== 'regular' )
				$class .= ' '. $name. '--variant-'. $variant;

			// 4. The source, and 5. nothing to say is nothing drawn
			$value = '';
			$lang = '';

			if( $schema['source'] !== 'none' ) {

				$fixed = $schema['source'] === 'text' && is_string( $given['text'] ?? null ) === true ? $given['text'] : null;

				// What a text may hold is the format of the schema, and where the call has an attribute named format, what that says
				$format = $schema['format'] ?? 'plain';

				if( $plan['format'] === true )
					$format = $args['format'];

				if( $fixed !== null )
					$resolved = self::_text( self::_fills( $appData, $run, $fixed, $context['fills'], 'text' ), $format );
				else
					$resolved = self::_reference( $appData, $run, $schema['source'], $source, true, $here, $format, $lang );

				if( $resolved === null || $resolved === '' )
					return '';

				$value = $resolved;
			}

			// 7. The children - 6. an id gives them its scope
			$inner = [ 'template' => $context['template'], 'scope' => $scope, 'place' => $name, 'element' => $context['element'], 'fills' => $context['fills'], 'depth' => $context['depth'] + 1 ];
			$content = '';
			$slotted = [];

			// A component that draws what it holds itself is handed the nodes, and the context to draw them in, which it changes for each round
			if( ( $schema['children'] ?? '' ) === 'nodes' )
				$context = $inner + [ 'children' => is_array( $node['c'] ?? null ) === true ? $node['c'] : [] ];
			else {

				$slots = $run['slots'][$name] ?? [];

				foreach( $slots as $slot )
					$slotted['[[content:'. $slot. ']]'] = '';

				if( is_array( $node['c'] ?? null ) === true ) {

					if( $slots === [] )
						$content = self::_draw( $appData, $node['c'], $inner, $run );
					else
						foreach( $node['c'] as $child ) {

							$html = self::_draw( $appData, [ $child ], $inner, $run );

							if( is_array( $child ) === true && in_array( $child['n'] ?? null, $slots, true ) === true )
								$slotted['[[content:'. $child['n']. ']]'] .= $html;
							else
								$content .= $html;
						}
				}
			}

			// 8. The renderer, or the template
			if( isset( $run['renderers'][$name] ) === true ) {

				$renderer = $run['renderers'][$name];

				$args = [ 'value' => $value, 'source' => $source, 'class' => $class, 'variant' => $variant, 'lang' => $lang ] + $args;

				foreach( $slotted as $token => $html )
					$args['content:'. substr( $token, 10, -2 )] = $html;

				try {
					$html = $renderer( $appData, $args, $content, $context );
				}
				catch( \Exception $thrown ) {

					// Against AGENTS.md, 8.4, which has a bug in a feature fail the request: a renderer is the code of a
					// feature, and the page it stands in is the site's. A page with a hole in it is the lesser harm, and the
					// log says which renderer it was, once. An \Error - a type, a missing method - is a bug of this code and goes on up
					self::_notice( $appData, 'renderer '. $name, 'Components: the renderer of "'. $name. '" threw '. self::_class( $thrown ). ': '. self::_shown( $thrown->getMessage() ), E_USER_WARNING );
					$html = '';
				}

				// What it did to the fills is seen from the next node on
				$run['fills'] = null;

				return is_string( $html ) === true ? $html : ( is_scalar( $html ) === true ? (string) $html : '' );
			}

			$placeholders = [ '[[class]]' => $class, '[[value]]' => $value, '[[content]]' => $content, '[[id]]' => $own === '' ? '' : ' id="'. $own. '"', '[[lang]]' => $lang ] + $slotted + $fills;

			if( ( $run['tfills'][$name] ?? [] ) !== [] )
				$placeholders += self::_site( $appData, $run, $name );

			return strtr( $schema['template'], $placeholders );
		}

		/**
		 *	The fills of the site that the template of a component holds, written with a slash in front: the text of each
		 *	key, as a plain text, nothing for a key nobody has a text for
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$run					(reference) Holds the keys each template names, and the fills of the drawing
		 *	@param		string		$name
		 *
		 *	@return 	array										[ '[[<key>]]' => text ]
		 */
		private static function _site( array &$appData, array &$run, string $name ): array {

			$site = [];

			foreach( $run['tfills'][$name] ?? [] as $key )
				$site['[['. $key. ']]'] = self::_text( self::_lookup( $appData, $run, $key ) ?? '', 'plain' );

			return $site;
		}

		/**
		 *	The placeholders of a template from what a renderer is handed - the other way round from what _call() makes
		 *	of a call: class, value, content, the tag form of the id, lang, the children of each slot and each attribute that is no class,
		 *	each a string and nothing where the argument is none
		 *
		 *	@param		array			&$run					(reference)
		 *	@param		string		$name
		 *	@param		array			$schema				The clean schema
		 *	@param		array			$args
		 *	@param		string		$content
		 *
		 *	@return 	array
		 */
		private static function _placeholders( array &$run, string $name, array $schema, array $args, string $content ): array {

			$plan = $run['plans'][$name] ?? self::_plan( $run, $name, $schema );
			$text = static fn( string $key ): string => is_string( $args[$key] ?? null ) === true ? $args[$key] : '';
			$slug = $plan['id'] === '' ? '' : $text( $plan['id'] );
			$placeholders = [ '[[class]]' => $text( 'class' ), '[[value]]' => $text( 'value' ), '[[content]]' => $content, '[[id]]' => $slug === '' ? '' : ' id="'. $slug. '"', '[[lang]]' => $text( 'lang' ) ];

			foreach( $run['slots'][$name] ?? [] as $slot )
				$placeholders['[[content:'. $slot. ']]'] = $text( 'content:'. $slot );

			foreach( $plan['tokens'] as $attribute )
				$placeholders += [ '[['. $attribute. ']]' => $text( $attribute ) ];

			return $placeholders;
		}

		/**
		 *	What the drawing of a component needs to know of its attributes, found once for a name in a drawing: the
		 *	attribute of the type id, those that are the token of their own name (no class), and whether there is one
		 *	named format
		 *
		 *	@param		array			&$run					(reference) The plan is kept in it
		 *	@param		string		$name
		 *	@param		array			$schema				The clean schema
		 *
		 *	@return 	array										[ 'id' => the attribute or '', 'tokens' => [ attribute, ... ], 'format' => bool ]
		 */
		private static function _plan( array &$run, string $name, array $schema ): array {

			$plan = [ 'id' => '', 'tokens' => [], 'format' => isset( $schema['attributes']['format'] ) ];

			foreach( $schema['attributes'] as $attribute => $declared ) {

				if( $declared['type'] === 'id' )
					$plan['id'] = (string) $attribute;

				if( $declared['class'] === false )
					$plan['tokens'][] = (string) $attribute;
			}

			return $run['plans'][$name] = $plan;
		}

		/**
		 *	The value of an attribute of the type id: a word, or nothing. A value that is no id is none; a bare word says nothing, which is the default
		 *
		 *	@param		array			$declared			The attribute's clean schema
		 *	@param		mixed			$raw					What the call says
		 *
		 *	@return 	string
		 */
		private static function _id( array $declared, mixed $raw ): string {
			return is_string( $raw ) === false ? $declared['default'] : ( strlen( $raw ) <= self::ID_LENGTH && preg_match( self::ID, $raw ) === 1 ? $raw : '' );
		}

		/**
		 *	Whether what an attribute of the type text says is a reference to a text - ~name, a key with a slash in
		 *	front, .field - and not the text itself. A reference is one word: a ~ or a / with a space after it, or a
		 *	dot that is no field, is a text that begins that way
		 *
		 *	@param		string		$written
		 *
		 *	@return 	bool
		 */
		private static function _isReference( string $written ): bool {

			if( $written === '' )
				return false;

			if( $written[0] === '.' )
				return preg_match( self::FIELD, substr( $written, 1 ) ) === 1;

			return ( $written[0] === '~' || $written[0] === '/' ) && preg_match( self::SHAPE, $written ) === 1;
		}

		/**
		 *	A name that has no schema: a shortcode that was registered with \Nino\Html::addShortcode() is
		 *	called as it always was - its callbacks, with the arguments of the call - and what it answers
		 *	is the end of it (\Nino\Html::drawShortcode()). A name that is none is nothing: nothing is drawn and nor are its
		 *	children, and the log is told - once in a request for each name. The one place that knows what to
		 *	do with a call that has no schema
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$node					The call
		 *	@param		array			$context			Whole: all keys
		 *	@param		array			&$run					(reference)
		 *
		 *	@return 	string
		 */
		private static function _unknown( array &$appData, array $node, array $context, array &$run ): string {

			$html = Html::drawShortcode( $appData, $node, $context );

			if( $html !== null ) {

				// A shortcode may have added a fill: the next node sees it
				$run['fills'] = null;

				return $html;
			}

			$name = (string) $node['n'];

			// A feature that is off leaves its names in every template, and every request of every page meets them: said once an hour for each name, as
			// Http::_dropped() says what a server drops - a site without basis wrote 3 entries for each request, 121617 bytes of log for 132 requests
			self::_notice( $appData, 'unknown '. $name, 'Components: "'. self::_shown( $name ). '" is no component that is registered; it and what it holds are not drawn', E_USER_NOTICE, self::UNKNOWN_WINDOW );

			return '';
		}

		/**
		 *	Say something in the log - once in a request, for the first NOTICES things
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$what					What it is about: the same words, the same line
		 *	@param		string		$message
		 *	@param		int				$level				E_USER_NOTICE, or E_USER_WARNING for what is broken
		 *	@param		int				$window				More than 0: the seconds in which the same thing is said once, however many requests meet it (\Nino\Feature::count()); 0 for once in a request
		 *
		 *	@return 	void
		 */
		private static function _notice( array &$appData, string $what, string $message, int $level = E_USER_NOTICE, int $window = 0 ): void {

			$what = substr( $what, 0, 120 );

			if( isset( $appData[self::NOTICED][$what] ) === true || count( (array) ( $appData[self::NOTICED] ?? [] ) ) >= self::NOTICES )
				return;

			$appData[self::NOTICED][$what] = true;

			if( $window > 0 && \Nino\Feature::count( $appData, self::NOTICE_COUNTER, $what, 1, $window ) === false )
				return;

			trigger_error( $message, $level );
		}

		/**
		 *	The first word of a call, or the value of an attribute that is a link or an image: what it names, ready
		 *	to be written. A text of a key, a field of an element, an address, a reference to a picture - and null where
		 *	there is nothing
		 *
		 *	~name is the key of this template and scope; a template or a scope that is not there is no key and is
		 *	said once. A key with a slash in front is as it is written. .field is a field of the element, nothing
		 *	where there is none. A text is escaped by its format - plain: the entities read as the characters they are,
		 *	then escaped once; the others are sanitized - and for the key's text the language it is in, where that is
		 *	not the language of the page, goes into $lang as the attribute that says it. A link is an address: a key with a slash in front that has no text is written as
		 *	the address it is, a key under /_nino/webpage/ - the address of a page, which is written without the
		 *	directory the site is served from - has that directory put in front, and nothing is an address that
		 *	a browser would take for a script (a scheme but http, https, mailto and tel, a control character, a
		 *	second slash). In an attribute, a link that is no ~key and no .field is an address. An image is the
		 *	reference to the picture - the key as it is, escaped, or, for an image field of the element, the name of the field - and the
		 *	renderer looks the picture up
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$run					(reference)
		 *	@param		string		$kind					text, link or image
		 *	@param		string		$reference		What is written
		 *	@param		bool			$first				The first word of a call, and not the value of an attribute
		 *	@param		array			$context
		 *	@param		string		$format				What a text may hold
		 *	@param		string		&$lang				(reference) The attribute that names the language a text is in, or ''
		 *
		 *	@return 	string|null
		 */
		private static function _reference( array &$appData, array &$run, string $kind, string $reference, bool $first, array $context, string $format, string &$lang ): ?string {

			$start = $reference === '' ? '' : $reference[0];

			// .field is a field of the element; where it is the value of an attribute and no name of a field, an address
			if( $start === '.' && ( $first === true || preg_match( self::FIELD, substr( $reference, 1 ) ) === 1 ) )
				return self::_field( $kind, substr( $reference, 1 ), $context['element'] );

			// Any other value of a link that is no key is an address, with the fills of the site in it. page:/contact is the address of that page
			// in the language of the page
			// In a loop an address that holds a token of it is one as well, whatever it starts with: the key it might be is the author's,
			// not the field's
			$tokens = $first === false ? $context['fills'] : [];

			if( $kind === 'link' && $start !== '~' && ( $start !== '/' || self::_holds( $reference, $tokens ) === true ) ) {

				$address = html_entity_decode( self::_fills( $appData, $run, $reference, $tokens, 'link' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				return str_starts_with( $address, 'page:' ) === true ? self::_page( $appData, substr( $address, 5 ) ) : self::_address( $address );
			}

			if( $start === '~' ) {

				if( $context['template'] === '' || $context['scope'] === '' ) {

					self::_notice( $appData, 'scope '. $context['template']. ' '. $context['scope']. ' '. $reference,
						'Components: "'. self::_shown( $reference ). '" is read in template "'. self::_shown( $context['template'] ). '" and scope "'. self::_shown( $context['scope'] ). '", which leaves no key: it needs a template and an id above it' );

					return null;
				}

				$key = '/template/'. $context['template']. '/'. $context['scope']. '/'. self::_inner( $appData, substr( $reference, 1 ) );
			}
			else if( $start === '/' )
				$key = self::_inner( $appData, $reference );
			else
				return null;

			if( $kind === 'image' )
				return self::escape( $key );

			$value = self::_lookup( $appData, $run, $key );

			if( $kind === 'link' ) {

				if( $value === null )
					return $start === '/' ? self::_address( html_entity_decode( $key, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : null;

				$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				if( str_starts_with( $value, 'page:' ) === true )
					return self::_page( $appData, substr( $value, 5 ) );

				// The address of a page is its own within the project, '/contact'; a link to it carries the directory the project is served from
				if( str_starts_with( $key, '/_nino/webpage/' ) === true && str_starts_with( $value, '/' ) === true )
					$value = rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). $value;

				return self::_address( $value );
			}

			if( $value === null )
				return null;

			$origin = \Nino\Html::fillOrigin( $appData, $key );
			$lang = $origin === null ? '' : ' lang="'. self::escape( \Nino\Locales::languageTag( $origin ) ). '"';

			return self::_text( $value, $format );
		}

		/**
		 *	The address of an internal page in the language of the page, as \Nino\Modules\Legal::url() writes
		 *	the address of one of its own: the route of the page for the current language, else for the native one,
		 *	with the directory the project is served from in front. The path is the Element-URI of the page - names
		 *	of letters, digits, dots, hyphens and underscores between slashes, and no way out - and a page nobody
		 *	routes, or one whose route is a subtree, is no address
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					'/contact'
		 *
		 *	@return 	string|null
		 */
		private static function _page( array &$appData, string $path ): ?string {

			// What follows the name of the page - ?query, #anchor, both - is the link's own and goes behind the address
			$cut = strcspn( $path, '?#' );
			$rest = substr( $path, $cut );
			$path = substr( $path, 0, $cut );

			if( preg_match( self::PAGE, $path ) !== 1 || str_contains( $path, '..' ) === true || preg_match( '/[\s\x00-\x1f\x7f]/', $rest ) === 1
				|| is_array( $appData['/nino/http/routes'] ?? null ) === false )
				return null;

			foreach( array_unique( [ \Nino\Locales::getCurrentLocale( $appData ), \Nino\Locales::getNativeLocale( $appData ) ] ) as $locale ) {

				$routeKey = \Nino\Http::findRouteUri( $appData, $path, $locale, 'GET' );

				if( $routeKey === null )
					continue;

				// A route that is an address of the site - no subtree, no space or control character, not below /_ or /., and not //x or /\x,
				// which a browser reads as another host - by the rule of \Nino\Http::pages(), which the sitemap and the menus of redirects use
				$pages = \Nino\Http::pages( [ $routeKey => $appData['/nino/http/routes'][$routeKey] ] );

				if( $pages === [] || $pages[0]['wildcard'] === true )
					return null;

				return self::_address( rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). $pages[0]['path']. $rest );
			}

			return null;
		}

		/**
		 *	A field of the element of a loop, as a source names it: .id the number in the loop, .uri the uri of the
		 *	element, .slug its name, any other the field of that name - a text by the format of its field, a link as
		 *	an address, an image as the name of the field: the reference \Nino\Images::picture() reads for the element of a
		 *	loop, which looks the file up itself, with the alt text the model of the field names
		 *
		 *	@param		string		$kind					text, link or image
		 *	@param		string		$field				Without the dot
		 *	@param		mixed			$context			The element of the context
		 *
		 *	@return 	string|null							Null where there is no element or no such field
		 */
		private static function _field( string $kind, string $field, mixed $context ): ?string {

			$element = self::_element( $context );

			if( $element === null )
				return null;

			$value = null;

			if( $field === 'id' || $field === 'uri' || $field === 'slug' )
				$value = $field === 'id' ? (string) $element['id'] : ( $field === 'uri' ? (string) ( $element['element']['.uri'] ?? '' ) : \Nino\Modules\Elements::slug( $element['element'] ) );
			else if( is_array( $element['model'][$field] ?? null ) === true )
				$value = $element['element'][$field] ?? '';

			if( $value === null || is_scalar( $value ) === false )
				return null;

			$model = $field === 'id' || $field === 'uri' || $field === 'slug' ? null : $element['model'][$field];

			if( $kind === 'image' )
				return $model === null || ( $model['type'] ?? '' ) !== 'image' || is_string( $value ) === false || $value === '' ? null : self::escape( $field );

			if( $kind === 'link' )
				return self::_address( (string) $value );

			// A field is written by its type, as every renderer of a field writes it; the brackets are the characters they are
			return $model === null ? self::escape( (string) $value ) : str_replace( '&#91;', '[', self::clean( \Nino\Html::fieldValue( is_string( $value ) === true ? self::clean( $value ) : $value, $model ) ) );
		}

		/**
		 *	The element of a loop, if what the context holds is one
		 *
		 *	@param		mixed			$element			[ 'uri', 'id', 'element', 'model' ], or null
		 *
		 *	@return 	array|null
		 */
		private static function _element( mixed $element ): ?array {

			if( is_array( $element ) === false || is_array( $element['element'] ?? null ) === false || is_array( $element['model'] ?? null ) === false )
				return null;

			return [ 'element' => $element['element'], 'model' => $element['model'], 'id' => (int) ( $element['id'] ?? 0 ) ];
		}

		/**
		 *	A text made safe to write into html by what it may hold: plain is read as the text it is, the entities
		 *	being the characters they stand for, and escaped once; the other formats are sanitized
		 *
		 *	@param		string		$text
		 *	@param		string		$format				One of \Nino\Html::FORMATS
		 *
		 *	@return 	string
		 */
		private static function _text( string $text, string $format ): string {

			$text = self::clean( $text );

			if( $format === 'plain' )
				return self::escape( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			// What the cleaning of the html reads out of an entity - &#x7f; - is a character again, and is taken out once more
			return self::clean( \Nino\Html::sanitizeHtml( $text, $format ) );
		}

		/**
		 *	An address, escaped - or null where its scheme is none a link may take. What a site's own address
		 *	looks like, a path or an anchor or one of four schemes, passes; a protocol-relative one does not.
		 *	A browser drops a control character in front of a scheme before it reads the address, so none is
		 *	allowed to stand anywhere in it
		 *
		 *	@param		string		$href					With its entities read
		 *
		 *	@return 	string|null
		 */
		private static function _address( string $href ): ?string {

			$href = trim( str_replace( [ "\r", "\n", "\t" ], '', $href ) );

			if( preg_match( '/[\x00-\x1F\x7F]/', $href ) === 1 )
				return null;

			if( str_starts_with( $href, '//' ) === true || str_starts_with( $href, '/\\' ) === true || str_starts_with( $href, '\\' ) === true )
				return null;

			if( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $href ) === 1 && preg_match( '#^(https?|mailto|tel):#i', $href ) !== 1 )
				return null;

			return self::escape( $href );
		}

		/**
		 *	What a responsive choice makes of what is written, "100 m:50 l:33": the first part, when it has no
		 *	breakpoint in front, is the value for every width - a class where it is not the default - and each
		 *	part after it with a breakpoint of Markup::BREAKPOINTS, that one is a class of its own whatever the
		 *	value is. A second part without a breakpoint, a breakpoint nobody knows, a value outside the options and
		 *	a breakpoint named twice fall away; where the first part has a breakpoint, the value for every width is
		 *	the default
		 *
		 *	@param		string		$raw
		 *	@param		array			$options
		 *	@param		string		$default
		 *	@param		string		$base					' name--'
		 *	@param		string		$attribute
		 *
		 *	@return 	array										[ the value for every width, the classes with a space in front of each ]
		 */
		private static function _responsive( string $raw, array $options, string $default, string $base, string $attribute ): array {

			$value = $default;
			$classes = '';
			$seen = [];

			foreach( preg_split( '/ +/', $raw, -1, PREG_SPLIT_NO_EMPTY ) ?: [] as $index => $part ) {

				$colon = strpos( $part, ':' );

				if( $colon === false ) {

					if( $index === 0 && in_array( $part, $options, true ) === true ) {

						$value = $part;

						if( $part !== $default )
							$classes .= $base. $attribute. '-'. $part;
					}

					continue;
				}

				$width = substr( $part, 0, $colon );
				$word = substr( $part, $colon + 1 );

				if( isset( Markup::BREAKPOINTS[$width] ) === false || isset( $seen[$width] ) === true || in_array( $word, $options, true ) === false || $word === '' )
					continue;

				$seen[$width] = true;
				$classes .= $base. $width. '-'. $attribute. '-'. $word;
			}

			return [ $value, $classes ];
		}

		/**
		 *	The variants of a component and the standard: what the schema names, and what the callbacks of
		 *	'/nino/components/variants' add to it, of [ 'name' => ..., 'variants' => [...], 'standard' => 'regular' ]: the list is
		 *	the schema's and then the callback's words, the standard one of them.
		 *	An answer that is not that shape - a list of words, a standard of the united list - is a notice
		 *	(E_USER_NOTICE, once for the name in a request), and the schema stands. Asked once for a name in a drawing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$run					(reference)
		 *	@param		string		$name
		 *	@param		array			$schema
		 *
		 *	@return 	array										[ the variants, the standard ]
		 */
		private static function _variants( array &$appData, array &$run, string $name, array $schema ): array {

			if( isset( $run['variants'][$name] ) === true )
				return $run['variants'][$name];

			$answer = [ 'name' => $name, 'variants' => $schema['variants'], 'standard' => 'regular' ];
			$answer = \Nino\Callbacks::doCallbacks( $appData, self::VARIANTS, $answer );

			$list = $answer['variants'] ?? null;
			$standard = $answer['standard'] ?? null;
			$right = is_array( $list ) === true && array_is_list( $list ) === true && is_string( $standard ) === true;

			if( $right === true )
				foreach( $list as $word )
					$right = $right && is_string( $word ) === true && strlen( $word ) <= self::NAME_LENGTH && preg_match( self::WORD, $word ) === 1;

			// What the schema names stays, first: a callback adds variants and chooses the standard, it takes none away
			if( $right === true ) {

				$list = array_values( array_unique( array_merge( $schema['variants'], $list ) ) );
				$right = in_array( $standard, $list, true ) === true;
			}

			if( $right === false ) {

				self::_notice( $appData, 'variants '. $name, 'Components: the variants a callback answered for "'. $name. '" are no list of words that holds regular with a standard in it; the schema\'s stand' );

				return $run['variants'][$name] = [ $schema['variants'], 'regular' ];
			}

			return $run['variants'][$name] = [ $list, $standard ];
		}

		/**
		 *	Whether a text is one: a string or a locale => string map, nothing empty in it, no control character
		 *	but a line break and a tab
		 *
		 *	@param		mixed			$words
		 *
		 *	@return 	bool
		 */
		private static function _words( mixed $words ): bool {

			if( \Nino\Features::localizedValid( $words ) === false )
				return false;

			foreach( (array) $words as $text )
				if( self::_plain( (string) $text ) === false )
					return false;

			return true;
		}

		/**
		 *	Whether a string holds no control character but the line break and the tab: a null byte is none
		 *	of what a schema says
		 *
		 *	@param		string		$text
		 *
		 *	@return 	bool
		 */
		private static function _plain( string $text ): bool {
			return preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text ) !== 1;
		}

		/**
		 *	The name of the class of an object as a message may say it: an anonymous class has a null byte and a path in its name
		 *
		 *	@param		object		$object
		 *
		 *	@return 	string
		 */
		private static function _class( object $object ): string {
			return self::_shown( explode( "\0", get_class( $object ) )[0] );
		}

		/**
		 *	What a message may say of a name it was given: the first characters of it, nothing it cannot print
		 *
		 *	@param		string		$name
		 *
		 *	@return 	string
		 */
		private static function _shown( string $name ): string {
			return (string) preg_replace( '/[^\x20-\x7E]/', '?', substr( $name, 0, self::NAME_LENGTH ) ). ( strlen( $name ) > self::NAME_LENGTH ? '...' : '' );
		}

		/**
		 *	A list of places, as 'in' and 'offers' say them
		 *
		 *	@param		mixed			$places
		 *
		 *	@return 	array|string						The list, or what is wrong with it
		 */
		private static function _places( mixed $places ): array|string {

			if( is_array( $places ) === false || array_is_list( $places ) === false )
				return 'must be a list of places';

			foreach( $places as $place )
				if( is_string( $place ) === false || strlen( $place ) > self::NAME_LENGTH || preg_match( self::NAME, $place ) !== 1 )
					return 'lists places, slugs of lower case letters, digits and hyphens';

			if( count( array_unique( $places ) ) !== count( $places ) )
				return 'names a place twice';

			return $places;
		}

		/**
		 *	The template of a component: markup, or the name of a *.tpl file in the directory of a feature, read
		 *
		 *	@param		string		$what
		 *	@param		mixed			$template
		 *	@param		string|null	$directory
		 *
		 *	@return 	array|string						[ the markup ], or what is wrong with it
		 */
		private static function _template( string $what, mixed $template, ?string $directory ): array|string {

			if( is_string( $template ) === false )
				return $what. ': "template" must be a string: markup, or the name of a .tpl file';

			// A template that ends in .tpl and holds no tag is the name of a file
			if( str_ends_with( $template, '.tpl' ) === true && str_contains( $template, '<' ) === false && str_contains( $template, '[[' ) === false ) {

				if( preg_match( '#^[A-Za-z0-9][A-Za-z0-9_-]*(?:/[A-Za-z0-9][A-Za-z0-9_-]*)*\.tpl$#D', $template ) !== 1 )
					return $what. ': "template" names a file that is no file of the directory: a path of letters, digits, hyphens and underscores, ending in .tpl';

				if( $directory === null )
					return $what. ': "template" names a file, and there is no directory to read it from';

				$base = realpath( $directory );
				$file = $base === false ? false : realpath( $base. '/'. $template );

				if( $base === false || $file === false || str_starts_with( $file, $base. '/' ) === false || is_file( $file ) === false )
					return $what. ': "template" names the file "'. $template. '", which is not there';

				if( filesize( $file ) > self::TEMPLATE_LENGTH )
					return $what. ': "template" is the file "'. $template. '", which is longer than '. self::TEMPLATE_LENGTH. ' bytes';

				$template = (string) file_get_contents( $file );
			}

			if( strlen( $template ) > self::TEMPLATE_LENGTH )
				return $what. ': "template" is longer than '. self::TEMPLATE_LENGTH. ' bytes';

			if( str_contains( $template, "\0" ) === true )
				return $what. ': "template" holds a null byte';

			return [ $template ];
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
		private static function _attributeSchema( string $what, string $attribute, mixed $declared, ?string $directory ): array|string {

			$where = $what. ', attribute "'. $attribute. '": ';

			if( is_array( $declared ) === false )
				return $where. 'must be an array';

			foreach( array_keys( $declared ) as $key )
				if( is_string( $key ) === false || in_array( $key, self::ATTRIBUTE_KEYS, true ) === false )
					return $where. '"'. self::_shown( (string) $key ). '" is no key of an attribute - there are '. implode( ', ', self::ATTRIBUTE_KEYS );

			$type = $declared['type'] ?? '';

			if( is_string( $type ) === false || in_array( $type, self::TYPES, true ) === false )
				return $where. 'has an unknown type - there are '. implode( ', ', self::TYPES );

			foreach( [ 'label', 'hint' ] as $words )
				if( isset( $declared[$words] ) === true && $declared[$words] !== '' && self::_words( $declared[$words] ) === false )
					return $where. '"'. $words. '" must be a string or a locale => string map';

			if( array_key_exists( 'default', $declared ) === false )
				return $where. 'has no "default" - every attribute needs one';

			$clean = [ 'type' => $type, 'label' => $declared['label'] ?? $attribute, 'hint' => $declared['hint'] ?? '' ];

			if( isset( $declared['input'] ) === true ) {

				$inputs = self::INPUTS[$type] ?? [];

				if( $inputs === [] )
					return $where. 'the type "'. $type. '" has no "input" - it is for a choice and a bool';

				if( in_array( $declared['input'], $inputs, true ) === false )
					return $where. '"input" of a '. $type. ' must be one of '. implode( ', ', $inputs );

				$clean['input'] = $declared['input'];
			}

			// A class is for a choice and a bool, which are the only ones that make one
			$class = $declared['class'] ?? ( $type === 'choice' || $type === 'bool' );

			if( is_bool( $class ) === false )
				return $where. '"class" must be true or false';

			if( $class === true && $type !== 'choice' && $type !== 'bool' )
				return $where. 'only a choice and a bool can be a class';

			$clean['class'] = $class;

			if( isset( $declared['responsive'] ) === true ) {

				if( is_bool( $declared['responsive'] ) === false )
					return $where. '"responsive" must be true or false';

				if( $declared['responsive'] === true && ( $type !== 'choice' || $class === false ) )
					return $where. 'only a choice that is a class can be responsive';
			}

			if( $type === 'choice' )
				$clean['responsive'] = ( $declared['responsive'] ?? false ) === true;

			foreach( [ 'options', 'min', 'max' ] as $key )
				if( isset( $declared[$key] ) === true && $type !== ( $key === 'options' ? 'choice' : 'int' ) )
					return $where. '"'. $key. '" is for '. ( $key === 'options' ? 'a choice' : 'an int' );

			$default = $declared['default'];

			if( $type === 'choice' ) {

				$options = $declared['options'] ?? null;

				// The other form of 'options': the name of a static method that answers them, asked when the first
				// drawing or the first tool needs them. Not here: a manifest is read for features that are off
				if( is_string( $options ) === true ) {

					$why = self::_method( $options, $directory );

					if( $why !== null )
						return $where. $why;

					if( ( $clean['input'] ?? '' ) === 'icons' )
						return $where. '"input" icons is for an alignment, which a list that is asked for later cannot be checked to be';

					if( is_string( $default ) === false || self::_plain( $default ) === false )
						return $where. 'the default of a choice whose options are a method must be a string';

					$clean['options'] = $options;
					$clean['default'] = $default;

					return $clean;
				}

				if( is_array( $options ) === false || $options === [] || array_is_list( $options ) === false )
					return $where. 'a choice needs "options", a list of values or the name of a static method';

				$clean['options'] = [];

				foreach( $options as $option ) {

					if( ( is_string( $option ) === false && is_int( $option ) === false ) || self::_plain( (string) $option ) === false )
						return $where. '"options" is a list of strings';

					// What is written as a class is a word, which nothing an editor writes can add to
					if( $class === true && (string) $option !== '' && preg_match( self::WORD, (string) $option ) !== 1 )
						return $where. 'the options of a class are words of lower case letters, digits and hyphens, and "'. self::_shown( (string) $option ). '" is none';

					$clean['options'][] = (string) $option;
				}

				if( count( array_unique( $clean['options'] ) ) !== count( $clean['options'] ) )
					return $where. '"options" names a value twice';

				if( ( $clean['input'] ?? '' ) === 'icons' ) {

					$words = array_diff( $clean['options'], [ '' ] );

					if( in_array( '', $clean['options'], true ) === false || $words === [] || array_filter( self::ALIGNMENTS, static fn( array $set ): bool => array_diff( $words, $set ) === [] ) === [] )
						return $where. '"input" icons is for an alignment: the options are "" and words of '. implode( ' or ', array_map( static fn( array $set ): string => implode( '|', $set ), self::ALIGNMENTS ) );
				}

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

			if( is_string( $default ) === false || self::_plain( $default ) === false )
				return $where. 'the default must be a string';

			if( $type === 'id' && $default !== '' && ( strlen( $default ) > self::ID_LENGTH || preg_match( self::ID, $default ) !== 1 ) )
				return $where. 'the default of an id is empty or a word of lower case letters, digits and hyphens';

			$clean['default'] = $default;

			return $clean;
		}

		/**
		 *	Whether the 'options' of an attribute name a method it can be asked: the form '\Namespace\Class::method',
		 *	and then - for a feature's manifest ($directory) - on paper alone: the class is the feature's own,
		 *	\Nino\Modules\<Directory> or below it, and its file stands where the autoloader looks for it, below the
		 *	directory; nothing is loaded. Otherwise a class the autoloader serves and a public static method of it
		 *
		 *	@param		string		$method
		 *	@param		string|null	$directory		The feature's directory, null for the full check
		 *
		 *	@return 	string|null							What is wrong with it, null where it will do
		 */
		private static function _method( string $method, ?string $directory = null ): ?string {

			if( preg_match( '/^\\\\?[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)*::[A-Za-z_]\w*$/D', $method ) !== 1 )
				return '"options" is a list of values or the name of a static method, written \\Namespace\\Class::method - "'. self::_shown( $method ). '" is none';

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
		 *	An attribute whose 'options' name a method, with the list in the place of the name: 'options' the values
		 *	and 'optionLabels' value => label. Asked once for each component and attribute in a request. An answer
		 *	nothing can be read from is a warning, once, and the attribute a 'string' for the rest of the request:
		 *	what a call says passes through, a tool draws a text field - without 'input', a list and a class. An
		 *	empty answer is a list: a project with nothing to choose from yet
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The component's name
		 *	@param		string		$attribute
		 *	@param		array			$declared			The attribute's clean schema
		 *
		 *	@return 	array										The attribute's schema
		 */
		private static function _resolve( array &$appData, string $name, string $attribute, array $declared ): array {

			if( is_string( $declared['options'] ?? null ) === false )
				return $declared;

			$method = $declared['options'];
			$at = $name. '/'. $attribute;

			if( ( $appData[self::OPTIONS][$at]['method'] ?? null ) !== $method ) {

				// Entered before it is asked: a method that asks for the register itself finds this attribute a string
				$appData[self::OPTIONS][$at] = [ 'method' => $method, 'answer' => null ];

				$answer = self::_ask( $appData, $method, $attribute, $declared['class'] === true );

				if( is_string( $answer ) === true )
					trigger_error( 'Components: component "'. $name. '", attribute "'. $attribute. '": '. $answer. ' - treated as a string', E_USER_WARNING );
				else
					$appData[self::OPTIONS][$at]['answer'] = $answer;
			}

			$answer = $appData[self::OPTIONS][$at]['answer'];

			if( is_array( $answer ) === false ) {

				$declared['type'] = 'string';
				$declared['class'] = false;
				unset( $declared['options'], $declared['input'], $declared['responsive'] );

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
		 *	@param		bool			$class				The values are written as a class: they are words
		 *
		 *	@return 	array|string						value => label, or what is wrong with the answer
		 */
		private static function _ask( array &$appData, string $method, string $attribute, bool $class ): array|string {

			try {

				$why = self::_method( $method );

				if( $why !== null )
					return $why;

				$answer = call_user_func_array( $method, [ &$appData, $attribute ] );
			}
			catch( \Throwable $thrown ) {
				return $method. '() threw '. self::_class( $thrown ). ': '. self::_shown( $thrown->getMessage() );
			}

			if( is_array( $answer ) === false )
				return $method. '() did not answer an array of value => label';

			$list = [];

			foreach( $answer as $value => $label ) {

				$value = (string) $value;

				if( \Nino\Features::localizedValid( $label ) === false )
					return $method. '() answers no string or locale => string map as the label of "'. self::_shown( $value ). '"';

				if( $class === true && $value !== '' && preg_match( self::WORD, $value ) !== 1 )
					return $method. '() answers "'. self::_shown( $value ). '", which is no word of a class';

				$list[$value] = $label;
			}

			return $list;
		}

		/**
		 *	The icon of a component, checked and complete: the parts of a 24 by 24 icon that a tool draws with a
		 *	stroke. Geometry and no markup, and checked as such: a list of 1 to ICON_PARTS parts, each a list of
		 *	two, a tag of ICON_TAGS and the map of its attributes, which holds every one the tag takes (but for
		 *	ICON_OPTIONAL) and no other - each value a string of the characters ICON_VALUES allows it and no
		 *	longer than that, and all the values together at most ICON_LENGTH characters
		 *
		 *	@param		string		$what					How the message names the component
		 *	@param		mixed			$icon
		 *
		 *	@return 	array|string						The clean list of parts, or what is wrong with it
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
					return $which. ' names "'. self::_shown( $tag ). '", which is no tag of an icon';

				$article = in_array( $tag[0], [ 'a', 'e', 'i', 'o', 'u' ], true ) === true ? 'an' : 'a';

				foreach( array_keys( $attributes ) as $name )
					if( in_array( $name, self::ICON_TAGS[$tag], true ) === false )
						return $which. ': "'. self::_shown( (string) $name ). '" is none of '. $article. ' '. $tag;

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
	}

}
