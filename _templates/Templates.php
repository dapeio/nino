<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Templates					Graphical, developer-only template builder - all \Nino\Templates\*
 *												classes live in this single file, same convention as
 *												_nino/Nino.php, _editor/Editor.php, _admin/Admin.php and
 *												_install/Install.php. Loads a project's own /templates/*.tpl
 *												into a block tree, lets it be reshaped through a visual
 *												editor, and writes it back as a .tpl again.
 *
 *												Its own folder, not an _admin module, for the same reason
 *												_install has one: it's a development-time tool a project
 *												is free to delete (`rm -rf _templates`) once the design is
 *												settled, and _admin/Admin.php is already large enough. The
 *												password gate is _admin's, though - this requires
 *												_admin/Admin.php and reuses its session flag rather than
 *												introducing a second password to keep in sync (same
 *												dependency /_install already has).
 *
 *												The whole design rests on one idea: a block's *identity*
 *												comes from its html tag plus css classes, and its
 *												*properties* are those same css classes (ui-grid-50,
 *												ui-btn--primary, ui-mb-3). There is no separate data model
 *												next to the markup, and nothing is written into a template
 *												that a hand-written one wouldn't already contain - a
 *												template stays a plain .tpl a developer can keep editing by
 *												hand. See docs/_templates.md.
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

namespace Nino\Templates {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Templates					Bootstraps /_templates: registers the route, gates every
	 *												request behind _admin's session flag, dispatches POST
	 *												actions to a small registry of modules. Same shape as
	 *												Admin::handlePost()/Install::handlePost().
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Templates {

		private const array MODULES = [
			\Nino\Templates\Documents::class,
			\Nino\Templates\Library::class,
		];

		/**
		 *	Register the /_templates route and its response callbacks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$appData['/nino/http/routes'] += [
				'GET://_templates' 	=> [
					'uri' 				=> '/_templates',
					'body'				=> '[template /_templates/templates/page-index]',
					'statusCode'	=> 200
				],
				'POST://_templates'	=> [ 'uri' => '/_templates' ],
			];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_templates', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_templates', [ self::class, 'handlePost' ] );
		}

		/**
		 *	Fill the GET /_templates response: the builder if the _admin
		 *	session gate is open, else _admin's own login form - posting it
		 *	logs into /_admin (the session flag is shared), and the reload
		 *	afterwards lands back here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handleGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::isAuthed( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_templates/templates/page-login]';
		}

		/**
		 *	Fill the POST /_templates response. Every builder api request goes
		 *	through this single route, dispatched by $_POST['action'] - same
		 *	shape as Admin::handlePost(), minus the login action: logging in
		 *	is /_admin's job, this only ever checks the flag that leaves
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handlePost( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$action = $_POST['action'] ?? '';

			$actions = [];
			foreach( self::MODULES as $module )
				$actions += $module::actions();

			if( isset( $actions[$action] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown action' );
				return;
			}

			// call_user_func() can't pass $appData/$request by reference, so dispatch via a
			// dynamic static call instead - same pattern as Callbacks::doCallbacks()
			[ $class, $method ] = $actions[$action];
			$class::{$method}( $appData, $request );
		}

		/**
		 *	Require an open _admin session - shared by every module action,
		 *	checked once per request rather than per action
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool										If the request may proceed
		 */
		public static function guard( array &$appData, array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			if( \Nino\Admin\Admin::isAuthed( $appData ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return false;
			}

			return true;
		}

		/**
		 *	Decode the json-encoded "data" POST field every module action reads
		 *	its payload from - same shape as Admin::postData()/Install::postData()
		 *
		 *	@return 	array
		 */
		public static function postData(): array {
			$data = json_decode( $_POST['data'] ?? '', true );
			return is_array( $data ) ? $data : [];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Templates					The block library: one directory per block under
	 *												_templates/library/&lt;key&gt;, each with a manifest.php and
	 *												(optionally) a block.tpl holding the markup an "insert"
	 *												produces - same directory-plus-manifest convention
	 *												_install/library uses.
	 *
	 *												A manifest declares what the block *is* (category/tag/
	 *												name, for the palette), how to *recognize* it in an
	 *												existing template ('match'), and which css classes and
	 *												attributes are exposed as editable properties
	 *												('settings'). Nothing here is code - adding a block is
	 *												one new directory, no change to this file.
	 *
	 *												Settings come in six types, all of them a two-way
	 *												mapping between a form control and the node's own class
	 *												list, attributes, tag or text:
	 *
	 *												  classenum   one value out of a list, rendered through a
	 *												              printf pattern ('ui-grid-%s' -> ui-grid-50),
	 *												              optionally per breakpoint ('ui-grid-%b-%s')
	 *												  classgroup  one class out of an explicit list (variants
	 *												              that share no common pattern)
	 *												  classtoggle a single class, on or off
	 *												  attr        a plain html attribute (href, src, alt)
	 *												  tag         the element name itself (h2 vs h3), the one
	 *												              property that is neither class nor attribute
	 *												  text        the node's own text content
	 *
	 *												The mapping itself runs client-side (assets/blocks.js) so
	 *												there is exactly one implementation of it; this class only
	 *												ships the declarations it works from.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Library {

		private const string LIBRARY = __DIR__. '/library';

		// Setting groups nearly every block wants (spacing, alignment,
		// viewport animation). A manifest opts in with 'use' => [ 'spacing' ]
		// instead of repeating the same twenty lines in forty files. Kept
		// here rather than in a library/<name> directory of their own so the
		// library really is "one directory per block", nothing else
		private const array SHARED = [

			'spacing' => [
				'mt' => [ 'label' => 'Margin top', 		'type' => 'classenum', 'pattern' => 'ui-mt-%s', 'values' => [ '0', '1', '2', '3', '4', '5', '6' ] ],
				'mb' => [ 'label' => 'Margin bottom', 'type' => 'classenum', 'pattern' => 'ui-mb-%s', 'values' => [ '0', '1', '2', '3', '4', '5', '6' ] ],
				'pt' => [ 'label' => 'Padding top', 	'type' => 'classenum', 'pattern' => 'ui-pt-%s', 'values' => [ '0', '1', '2', '3', '4', '5', '6' ] ],
				'pb' => [ 'label' => 'Padding bottom','type' => 'classenum', 'pattern' => 'ui-pb-%s', 'values' => [ '0', '1', '2', '3', '4', '5', '6' ] ],
			],

			'align' => [
				'align' => [
					'label' 			=> 'Text align',
					'type' 				=> 'classenum',
					'pattern' 		=> 'ui-text-%s',
					'bpPattern' 	=> 'ui-text-%b-%s',
					'breakpoints' => [ 's', 'm', 'l', 'xl' ],
					'values' 			=> [ 'left', 'center', 'right' ],
				],
			],

			'vpa' => [
				'vpa' 			=> [ 'label' => 'Viewport animation', 'type' => 'classtoggle', 'class' => 'js-vpa' ],
				'vpaEffect' => [
					'label' 	=> 'Effect',
					'type' 		=> 'classenum',
					'pattern' => 'js-vpa--%s',
					'values' 	=> [ 'zoom', 'zoom-out', 'slide-left', 'slide-right', 'blur', 'flip' ],
				],
				'vpaSpeed' => [
					'label' 	=> 'Speed',
					'type' 		=> 'classenum',
					'pattern' => 'js-vpa--speed-%s',
					'values' 	=> [ 'fast', 'medium', 'slow' ],
				],
			],
		];

		/**
		 *	This module's action map, merged into Templates::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [ 'library/blocks' => [ self::class, 'apiBlocks' ] ];
		}

		/**
		 *	Every block the palette can offer, its settings schema resolved
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiBlocks( array &$appData, array &$request ): void {
			\Nino\Http::ok( $request, [ 'blocks' => self::blocks() ] );
		}

		/**
		 *	Read every library/&lt;key&gt;/manifest.php, resolve its shared
		 *	setting groups and attach the markup an insert produces
		 *
		 *	@return 	array			key -> block definition
		 */
		public static function blocks(): array {

			static $cache = null;

			if( $cache !== null )
				return $cache;

			$blocks = [];

			foreach( scandir( self::LIBRARY ) ?: [] as $entry ) {

				$manifest = self::_readManifest( $entry );
				if( $manifest === null )
					continue;

				$settings = [];
				foreach( ( $manifest['use'] ?? [] ) as $group )
					$settings += self::SHARED[$group] ?? [];

				// A block's own settings win a name collision with a shared
				// group's - that's the point of declaring one (eg. a block
				// that needs its own margin scale)
				$settings = ( $manifest['settings'] ?? [] ) + $settings;

				$blocks[$entry] = [
					'key' 			=> $entry,
					'category' 	=> (string) ( $manifest['category'] ?? 'Other' ),
					'tag' 			=> (string) ( $manifest['tag'] ?? '' ),
					'name' 			=> (string) ( $manifest['name'] ?? $entry ),
					'match' 		=> self::_normalizeMatch( $manifest['match'] ?? [] ),
					'children' 	=> $manifest['children'] ?? false,
					'settings' 	=> $settings,
					'actions' 	=> $manifest['actions'] ?? [ 'remove', 'duplicate', 'moveup', 'movedown' ],
					'html' 			=> self::_readHtml( $entry, $manifest ),
				];
			}

			ksort( $blocks );

			$cache = $blocks;

			return $cache;
		}

		/**
		 *	A match declaration, filled out to its full shape so the client
		 *	never has to null-check any of it. 'classes' must all be present,
		 *	'classesAny' at least one of (what lets one "Grid Column" block
		 *	cover ui-grid-25 through ui-grid-100), 'attrs' must match exactly
		 *	(used by the shortcode blocks, whose placeholder element carries
		 *	the shortcode name in an attribute), 'not' rules a node out
		 *
		 *	@param		array 		$match
		 *
		 *	@return 	array
		 */
		private static function _normalizeMatch( array $match ): array {
			return [
				'tags' 				=> array_values( array_map( 'strtolower', (array) ( $match['tags'] ?? $match['tag'] ?? [] ) ) ),
				'classes' 		=> array_values( (array) ( $match['classes'] ?? [] ) ),
				'classesAny' 	=> array_values( (array) ( $match['classesAny'] ?? [] ) ),
				'attrs' 			=> (array) ( $match['attrs'] ?? [] ),
				'not' 				=> array_values( (array) ( $match['not'] ?? [] ) ),
			];
		}

		/**
		 *	The markup inserting this block produces - its own block.tpl, or
		 *	an inline 'html' key for a one-liner not worth a file of its own.
		 *	Parsed into a node tree by the client through the same Parser the
		 *	documents themselves go through, so a block's starting markup is
		 *	written exactly like template markup and nothing about it is a
		 *	special case
		 *
		 *	@param		string		$key
		 *	@param		array 		$manifest
		 *
		 *	@return 	string
		 */
		private static function _readHtml( string $key, array $manifest ): string {

			$path = self::LIBRARY. '/'. $key. '/block.tpl';

			if( is_file( $path ) === true )
				return rtrim( (string) file_get_contents( $path ), "\r\n" );

			return (string) ( $manifest['html'] ?? '' );
		}

		/**
		 *	One block's manifest.php. $key comes off the wire in places, so
		 *	it is matched against a plain slug before ever reaching the
		 *	filesystem - same reasoning as Install\Themes::_readManifest()
		 *
		 *	@param		string		$key					Directory name under library/
		 *
		 *	@return 	array|null							Null if $key names no usable block
		 */
		private static function _readManifest( string $key ): ?array {

			if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $key ) !== 1 )
				return null;

			$path = self::LIBRARY. '/'. $key. '/manifest.php';

			if( is_file( $path ) === false )
				return null;

			$manifest = include $path;

			return is_array( $manifest ) === true ? $manifest : null;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Templates					Turns a .tpl file into a node tree and back again.
	 *
	 *												Two things make a .tpl not quite html: shortcodes
	 *												([template ...], [elements ...]...[/elements]) and text
	 *												fills ([[/key]]). Fills need no handling at all - they
	 *												are ordinary text, in text nodes and in attribute values
	 *												alike. Shortcodes do: they nest around html, so they are
	 *												part of the tree's structure, not a string inside it.
	 *												_preprocess() rewrites every one into a
	 *												&lt;nino-sc name args&gt; placeholder element before parsing
	 *												and Serializer turns it back, which lets php 8.4's real
	 *												html5 parser (\Dom\HTMLDocument) do all the actual work.
	 *
	 *												The tree keeps *everything*: attribute order, the exact
	 *												whitespace between tags, comments. Serializing a tree
	 *												nothing has touched reproduces the source file byte for
	 *												byte - which is the property tests/templates-smoke.php
	 *												asserts against every template the library ships, and the
	 *												reason opening a template in the builder and saving it
	 *												unchanged is a no-op rather than a reformat.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Parser {

		// Shortcode names the parser treats as structure. Taken from the
		// kernel's own registered set at runtime where possible, unioned
		// with this list so recognition doesn't quietly change when a
		// project disables a module - a template's markup doesn't change
		// just because the shortcode rendering it is currently off
		public const array SHORTCODES = [
			'template', 'elements', 'element', 'image', 'assets',
			'navigation', 'localepicker', 'jstext', 'csrf',
		];

		// Shortcodes that never wrap content. Everything else is matched
		// with an optional [/name] closing tag, exactly like the kernel's
		// own regex does (see Html::_doShortcodes())
		private const array VOID_SHORTCODES = [ 'template', 'image', 'assets', 'jstext', 'csrf' ];

		// Html entities are decoded by the parser and can't be re-encoded
		// afterwards without guessing which spelling they had ('&shy;',
		// '&#173;' and a literal soft hyphen all arrive as the same
		// character). They are therefore swapped for a placeholder *before*
		// parsing and swapped back after serializing - carrying their own
		// text between two Private Use Area characters, so restoring needs
		// no lookup table and Serializer can do it without shared state.
		// U+E000/U+E001 are unassigned by definition and cannot occur in
		// real template content
		public const string ENTITY_OPEN 	= "\u{E000}";
		public const string ENTITY_CLOSE 	= "\u{E001}";

		/**
		 *	Swap every html entity for its placeholder - see ENTITY_OPEN
		 *
		 *	@param		string		$html
		 *
		 *	@return 	string
		 */
		public static function protectEntities( string $html ): string {
			return (string) preg_replace(
				'/&(#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/',
				self::ENTITY_OPEN. '$1'. self::ENTITY_CLOSE,
				$html
			);
		}

		/**
		 *	Swap every placeholder back for the entity it stands for
		 *
		 *	@param		string		$html
		 *
		 *	@return 	string
		 */
		public static function restoreEntities( string $html ): string {
			return (string) preg_replace(
				'/'. self::ENTITY_OPEN. '([^'. self::ENTITY_CLOSE. ']*)'. self::ENTITY_CLOSE. '/u',
				'&$1;',
				$html
			);
		}

		/**
		 *	Parse a .tpl's source into a list of nodes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source				Raw .tpl contents
		 *
		 *	@return 	array			Ordered list of node arrays - see _node()
		 */
		public static function parse( array &$appData, string $source ): array {

			$html = self::_preprocess( $appData, self::protectEntities( $source ) );

			// LIBXML_HTML_NOIMPLIED keeps the parser from wrapping everything
			// in <html><body>; the explicit root div is still needed as a
			// single container to walk from
			$doc = \Dom\HTMLDocument::createFromString(
				'<div id="nino-builder-root">'. $html. '</div>',
				LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED
			);

			$root = $doc->getElementById( 'nino-builder-root' );

			if( $root === null )
				return [];

			$counter = 0;

			return self::_children( $root, $counter );
		}

		/**
		 *	Rewrite every shortcode call into a &lt;nino-sc&gt; placeholder
		 *	element, so the html parser sees one tree rather than html with
		 *	foreign syntax in it.
		 *
		 *	Only shortcodes in *text* position are rewritten. One sitting
		 *	inside a tag (in an attribute value, eg. src="[image /x]") would
		 *	otherwise have an element injected into the middle of an
		 *	attribute and corrupt the whole document, so the scan below
		 *	tracks whether it is currently inside a &lt;...&gt; and skips those -
		 *	they stay literal text, which is also what a hand-written
		 *	template does with them today
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source
		 *
		 *	@return 	string
		 */
		private static function _preprocess( array &$appData, string $source ): string {

			$names = array_values( array_unique( array_merge(
				self::SHORTCODES,
				array_keys( $appData['./nino/html/shortcodes'] ?? [] )
			) ) );

			// Longest first, so 'elements' is tried before 'element' - the
			// alternation is otherwise happy to match the shorter one and
			// leave a stray 's' behind
			usort( $names, fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

			$group 	= implode( '|', array_map( fn( string $n ): string => preg_quote( $n, '/' ), $names ) );
			$ranges = self::_tagRanges( $source );

			// Same shape as the kernel's own shortcode regex: [name args]
			// with an optional content...[/name] tail
			$pattern = '/\[('. $group. ')(?: ([^\]]*))?\](?:([^\[]*+(?:\[(?!\/\1\])[^\[]*+)*+)(?:\[\/(?:\1)\]))?/';

			return preg_replace_callback( $pattern, function( array $m ) use ( $ranges ): string {

				$offset = $m[0][1];
				$whole 	= $m[0][0];

				foreach( $ranges as $range )
					if( $offset > $range[0] && $offset < $range[1] )
						return $whole;

				$name = $m[1][0];
				$args = trim( $m[2][0] ?? '' );

				$open = '<nino-sc name="'. $name. '" args="'. htmlspecialchars( $args, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ). '"';

				if( in_array( $name, self::VOID_SHORTCODES, true ) === true || ( $m[3][0] ?? '' ) === '' && str_contains( $whole, '[/'. $name. ']' ) === false )
					return $open. ' void="1"></nino-sc>';

				return $open. '>'. ( $m[3][0] ?? '' ). '</nino-sc>';

			}, $source, -1, $count, PREG_OFFSET_CAPTURE ) ?? $source;
		}

		/**
		 *	Byte ranges of every &lt;...&gt; tag in the source - what
		 *	_preprocess() checks a shortcode's position against. A plain
		 *	scan rather than a regex: an attribute value is allowed to
		 *	contain '&gt;' as long as it's quoted, and only tracking the
		 *	quote state gets that right
		 *
		 *	@param		string		$source
		 *
		 *	@return 	array			List of [ start, end ] offsets
		 */
		private static function _tagRanges( string $source ): array {

			$ranges = [];
			$length = strlen( $source );
			$start 	= null;
			$quote 	= '';

			for( $i = 0; $i < $length; $i++ ) {

				$char = $source[$i];

				if( $start === null ) {
					if( $char === '<' )
						$start = $i;
					continue;
				}

				if( $quote !== '' ) {
					if( $char === $quote )
						$quote = '';
					continue;
				}

				if( $char === '"' || $char === '\'' ) {
					$quote = $char;
					continue;
				}

				if( $char === '>' ) {
					$ranges[] = [ $start, $i ];
					$start 		= null;
				}
			}

			return $ranges;
		}

		/**
		 *	@param		\Dom\Node	$parent
		 *	@param		int 			&$counter			(reference) Running id counter, unique per parse
		 *
		 *	@return 	array
		 */
		private static function _children( \Dom\Node $parent, int &$counter ): array {

			$nodes = [];

			foreach( $parent->childNodes as $child )
				if( ( $node = self::_node( $child, $counter ) ) !== null )
					$nodes[] = $node;

			return $nodes;
		}

		/**
		 *	One dom node as a plain array. Three kinds:
		 *
		 *	  text     { type, value }        - kept verbatim, indentation included
		 *	  comment  { type, value }
		 *	  element  { type, id, tag, attrs, classes, children, void }
		 *
		 *	An element carries no block identity at all here - which library
		 *	block it is (if any) is decided client-side against the same
		 *	'match' declarations the palette is built from, so recognition
		 *	and insertion can never drift apart. A shortcode placeholder is
		 *	just an element whose tag is 'nino-sc'
		 *
		 *	@param		\Dom\Node	$node
		 *	@param		int 			&$counter			(reference) Running id counter
		 *
		 *	@return 	array|null							Null for a node kind the tree doesn't carry
		 */
		private static function _node( \Dom\Node $node, int &$counter ): ?array {

			if( $node->nodeType === XML_TEXT_NODE )
				return [ 'type' => 'text', 'value' => $node->textContent ];

			if( $node->nodeType === XML_COMMENT_NODE )
				return [ 'type' => 'comment', 'value' => $node->textContent ];

			if( $node->nodeType !== XML_ELEMENT_NODE )
				return null;

			$attrs = [];
			foreach( $node->attributes as $attribute )
				$attrs[$attribute->name] = $attribute->value;

			// 'class' lives in its own ordered list rather than among the
			// attributes: it is the block's property store (see this file's
			// header), and keeping the original order is what lets an
			// untouched document serialize back byte for byte. Its position
			// among the other attributes is kept too, for the same reason -
			// see Serializer::_attributes()
			$classes 		= [];
			$classIndex = 0;

			if( isset( $attrs['class'] ) === true ) {
				$classes 		= array_values( array_filter( preg_split( '/\s+/', trim( $attrs['class'] ) ) ?: [] ) );
				$classIndex = (int) array_search( 'class', array_keys( $attrs ), true );
				unset( $attrs['class'] );
			}

			$tag = strtolower( $node->nodeName );

			return [
				'type' 				=> 'element',
				'id' 					=> 'n'. ( ++$counter ),
				'tag' 				=> $tag,
				'attrs' 			=> $attrs,
				'classes' 		=> $classes,
				'classIndex' 	=> $classIndex,
				'void' 				=> in_array( $tag, Serializer::VOID_ELEMENTS, true ),
				'children' 		=> self::_children( $node, $counter ),
			];
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Templates					Writes a node tree back out as .tpl source - the exact
	 *												inverse of Parser, down to the byte for a tree nothing
	 *												has changed (see Parser's docblock).
	 *
	 *												Deliberately not \Dom\HTMLDocument::saveHtml(): that
	 *												normalizes boolean attributes (required -> required=""),
	 *												and a template builder whose first save rewrites lines
	 *												nobody touched is a builder developers stop trusting.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Serializer {

		// Html void elements - written without a closing tag, and without a
		// self-closing slash either (which is what the shipped templates,
		// and the html5 spec, already do: <img src="...">)
		public const array VOID_ELEMENTS = [
			'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
			'link', 'meta', 'source', 'track', 'wbr',
		];

		// Attributes whose presence alone is the value. Written bare rather
		// than as attr="" - see this class's docblock
		private const array BOOLEAN_ATTRIBUTES = [
			'allowfullscreen', 'async', 'autofocus', 'autoplay', 'checked',
			'controls', 'default', 'defer', 'disabled', 'hidden', 'loop',
			'multiple', 'muted', 'novalidate', 'open', 'readonly',
			'required', 'reversed', 'selected',
		];

		/**
		 *	@param		array 		$nodes				Ordered list of node arrays
		 *
		 *	@return 	string									.tpl source
		 */
		public static function serialize( array $nodes ): string {
			return Parser::restoreEntities( self::_nodes( $nodes ) );
		}

		/**
		 *	The recursion itself - entity placeholders are still in place
		 *	here and only resolved once, by serialize(), on the finished
		 *	string (see Parser::protectEntities())
		 *
		 *	@param		array 		$nodes
		 *
		 *	@return 	string
		 */
		private static function _nodes( array $nodes ): string {

			$out = '';

			foreach( $nodes as $node )
				$out .= self::_node( $node );

			return $out;
		}

		/**
		 *	@param		array 		$node
		 *
		 *	@return 	string
		 */
		private static function _node( array $node ): string {

			$type = (string) ( $node['type'] ?? '' );

			if( $type === 'text' )
				return (string) ( $node['value'] ?? '' );

			if( $type === 'comment' )
				return '<!--'. ( (string) ( $node['value'] ?? '' ) ). '-->';

			if( $type !== 'element' )
				return '';

			$tag = strtolower( (string) ( $node['tag'] ?? '' ) );

			if( $tag === 'nino-sc' )
				return self::_shortcode( $node );

			$inner = self::_nodes( (array) ( $node['children'] ?? [] ) );
			$open 	= '<'. $tag. self::_attributes( $node ). '>';

			if( ( $node['void'] ?? false ) === true || in_array( $tag, self::VOID_ELEMENTS, true ) === true )
				return $open;

			return $open. $inner. '</'. $tag. '>';
		}

		/**
		 *	A &lt;nino-sc&gt; placeholder back as the shortcode it stands for
		 *
		 *	@param		array 		$node
		 *
		 *	@return 	string
		 */
		private static function _shortcode( array $node ): string {

			$name = (string) ( $node['attrs']['name'] ?? '' );
			$args = (string) ( $node['attrs']['args'] ?? '' );

			if( $name === '' )
				return '';

			$open = '['. $name. ( $args !== '' ? ' '. $args : '' ). ']';

			if( ( $node['attrs']['void'] ?? '' ) === '1' )
				return $open;

			return $open. self::_nodes( (array) ( $node['children'] ?? [] ) ). '[/'. $name. ']';
		}

		/**
		 *	An element's attributes, class list folded back in at the
		 *	position it originally held. Parser pulls 'class' out of the
		 *	attribute map, so it is re-inserted here in the same place a
		 *	source document had it - alphabetical or append-at-end ordering
		 *	would both reformat every line carrying a class
		 *
		 *	@param		array 		$node
		 *
		 *	@return 	string
		 */
		private static function _attributes( array $node ): string {

			$attrs 		= (array) ( $node['attrs'] ?? [] );
			$classes 	= array_values( array_filter( array_map( 'strval', (array) ( $node['classes'] ?? [] ) ) ) );

			// Parser pulls 'class' out of $attrs but records the index it
			// sat at, so it goes back exactly there - a node written as
			// <a href="..." class="ui-btn"> must not come back as
			// <a class="ui-btn" href="..."> just because the builder keeps
			// classes in a separate list internally
			$index = (int) ( $node['classIndex'] ?? 0 );

			$parts = [];
			$i 		 = 0;

			if( count( $classes ) > 0 && $index === 0 )
				$parts[] = 'class="'. self::_escape( implode( ' ', $classes ) ). '"';

			foreach( $attrs as $name => $value ) {

				$i++;

				$parts[] = self::_attribute( (string) $name, (string) $value );

				if( count( $classes ) > 0 && $index === $i )
					$parts[] = 'class="'. self::_escape( implode( ' ', $classes ) ). '"';
			}

			return count( $parts ) > 0 ? ' '. implode( ' ', $parts ) : '';
		}

		/**
		 *	@param		string		$name
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		private static function _attribute( string $name, string $value ): string {

			if( $value === '' && in_array( strtolower( $name ), self::BOOLEAN_ATTRIBUTES, true ) === true )
				return $name;

			return $name. '="'. self::_escape( $value ). '"';
		}

		/**
		 *	Escape an attribute value. Only '"' needs handling: every entity
		 *	the source spelled out is a placeholder by this point (see
		 *	Parser::protectEntities()), so a '&' still standing here was a
		 *	bare '&' in the source - as in href="?a=1&b=2" - and re-encoding
		 *	it would rewrite a line the builder never touched
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		private static function _escape( string $value ): string {
			return str_replace( '"', '&quot;', $value );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Templates					Finds every css id selector the project's own stylesheets
	 *												declare.
	 *
	 *												The builder shows a node's properties as its css classes
	 *												(see this file's header). A rule bound to an id -
	 *												'#hero { padding: 0 }' - overrides those classes in the
	 *												real page but is invisible to the builder, so what the
	 *												canvas draws and what the browser renders can legitimately
	 *												disagree. Every node whose id appears here is flagged, and
	 *												the editor puts a warning on it rather than pretending the
	 *												class list is the whole truth.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Stylesheets {

		/**
		 *	Every id a css rule in the project's style bundle targets
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array			Sorted list of id names, without the '#'
		 */
		public static function styledIds( array &$appData ): array {

			$root = \Nino\Filesystem::getPath( $appData );
			$ids 	= [];

			foreach( self::_sources( $appData ) as $source ) {

				$path = $root. '/'. ltrim( $source, '/' );

				if( is_file( $path ) === false )
					continue;

				foreach( self::_idsIn( (string) file_get_contents( $path ) ) as $id )
					$ids[$id] = true;
			}

			$ids = array_keys( $ids );
			sort( $ids );

			return $ids;
		}

		/**
		 *	Every .css file in any of config.php's '/nino/html/assets'
		 *	bundles - not just the one named style.css, since a project is
		 *	free to bundle its stylesheets under any cache path it likes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _sources( array &$appData ): array {

			$sources = [];

			foreach( ( $appData['/nino/html/assets'] ?? [] ) as $files )
				foreach( (array) $files as $file )
					if( str_ends_with( strtolower( (string) $file ), '.css' ) === true )
						$sources[] = (string) $file;

			return array_values( array_unique( $sources ) );
		}

		/**
		 *	Pull the id selectors out of one stylesheet's source. Comments
		 *	are stripped first, then everything ahead of a '{' is treated as
		 *	a selector list - crude next to a real css parser, but it only
		 *	ever needs to answer "does anything style this id", and a false
		 *	positive costs one unnecessary warning
		 *
		 *	@param		string		$css
		 *
		 *	@return 	array
		 */
		private static function _idsIn( string $css ): array {

			$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
			$ids = [];

			foreach( explode( '{', $css ) as $index => $chunk ) {

				// The first chunk is a selector list; every later one starts
				// with the previous rule's body, so only what follows the
				// last '}' in it can be one
				if( $index > 0 ) {
					$close = strrpos( $chunk, '}' );
					if( $close === false )
						continue;
					$chunk = substr( $chunk, $close + 1 );
				}

				if( preg_match_all( '/#([A-Za-z][A-Za-z0-9_-]*)/', $chunk, $matches ) > 0 )
					foreach( $matches[1] as $id )
						$ids[] = $id;
			}

			return array_values( array_unique( $ids ) );
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Templates					The documents the builder works on: the project's own
	 *												/templates/*.tpl files.
	 *
	 *												Only page-* and section-* files are editable. The rest -
	 *												html-header.tpl and html-footer.tpl above all - are
	 *												deliberately not: those two are one structure split
	 *												across two files (the header opens &lt;main&gt;, the footer
	 *												closes it), so neither is a well-formed fragment on its
	 *												own. An html parser "corrects" that by closing the tag it
	 *												sees left open, and saving the result would quietly
	 *												destroy the page frame of every page on the site. They
	 *												are still listed, marked as not editable, so the
	 *												omission is visible rather than looking like a bug.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Documents {

		// Which /templates/*.tpl files the builder will open - see this
		// class's docblock for why this isn't simply "all of them"
		private const array EDITABLE_PREFIXES = [ 'page-', 'section-' ];

		// Tags the builder is willing to write back out. A template holding
		// anything else is opened read-only rather than round-tripped
		// through a tree that has no representation for it - <script> above
		// all, whose content is not markup and must never be reformatted or
		// re-escaped as if it were
		private const array ALLOWED_TAGS = [
			'a', 'abbr', 'address', 'article', 'aside', 'b', 'blockquote', 'br',
			'button', 'caption', 'cite', 'code', 'col', 'colgroup', 'data',
			'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl',
			'dt', 'em', 'fieldset', 'figcaption', 'figure', 'footer', 'form',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'i',
			'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'li',
			'main', 'mark', 'menu', 'nav', 'nino-sc', 'ol', 'optgroup',
			'option', 'output', 'p', 'picture', 'q', 's', 'samp', 'section',
			'select', 'small', 'source', 'span', 'strong', 'sub', 'summary',
			'sup', 'svg', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th',
			'thead', 'time', 'tr', 'u', 'ul', 'var', 'video', 'wbr',
		];

		/**
		 *	This module's action map, merged into Templates::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'documents/list' 	=> [ self::class, 'apiList' ],
				'documents/load' 	=> [ self::class, 'apiLoad' ],
				'documents/save' 	=> [ self::class, 'apiSave' ],
			];
		}

		/**
		 *	Every /templates/*.tpl on disk, each flagged with whether the
		 *	builder will open it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			$files 			= glob( \Nino\Filesystem::getPath( $appData ). '/templates/*.tpl' ) ?: [];
			$documents 	= [];

			foreach( $files as $file ) {

				$name = basename( $file, '.tpl' );

				$documents[] = [
					'name' 			=> $name,
					'editable' 	=> self::_isEditable( $name ),
					'size' 			=> (int) filesize( $file ),
				];
			}

			usort( $documents, fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );

			\Nino\Http::ok( $request, [ 'documents' => $documents ] );
		}

		/**
		 *	Parse one template into a node tree, alongside everything the
		 *	editor needs to render it: the ids the project's css targets
		 *	(see Stylesheets) and, when the document can't safely be written
		 *	back, why not
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiLoad( array &$appData, array &$request ): void {

			$name = (string) ( \Nino\Templates\Templates::postData()['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown template: "'. $name. '"' );
				return;
			}

			$source = (string) file_get_contents( $path );
			$nodes 	= \Nino\Templates\Parser::parse( $appData, $source );

			// Two independent reasons a template opens read-only: it isn't
			// one of the editable kinds at all, or it round-trips to
			// something other than its own source - which means this file
			// holds markup the tree has no faithful representation for, and
			// saving it would silently change it
			$readonly = null;

			if( self::_isEditable( $name ) === false )
				$readonly = 'Only page-* and section-* templates can be edited - html-header/html-footer are one structure split across two files (see docs/_templates.md).';
			elseif( ( $tag = self::_disallowedTag( $nodes ) ) !== null )
				$readonly = 'Contains a <'. $tag. '> element the builder will not rewrite.';
			elseif( \Nino\Templates\Serializer::serialize( $nodes ) !== $source )
				$readonly = 'This template does not round-trip unchanged - saving it would reformat markup the builder did not understand.';

			\Nino\Http::ok( $request, [
				'name' 			=> $name,
				'nodes' 		=> $nodes,
				'styledIds' => \Nino\Templates\Stylesheets::styledIds( $appData ),
				'readonly' 	=> $readonly,
			] );
		}

		/**
		 *	Write a posted node tree back out as the template's new source.
		 *
		 *	The tree is serialized first and only then checked and written:
		 *	a posted tree is developer input (this whole area sits behind
		 *	_admin's password, same trust level as its Config module, which
		 *	edits config.php as raw json), but "trusted" and "well-formed"
		 *	are different questions, and a tree carrying a tag the builder
		 *	would not have produced is a bug worth refusing rather than
		 *	writing to disk
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			$data = \Nino\Templates\Templates::postData();
			$name = (string) ( $data['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown template: "'. $name. '"' );
				return;
			}

			if( self::_isEditable( $name ) === false ) {
				\Nino\Http::fail( $request, 403, 'template is not editable' );
				return;
			}

			$nodes = is_array( $data['nodes'] ?? null ) ? $data['nodes'] : null;

			if( $nodes === null ) {
				\Nino\Http::fail( $request, 400, 'missing nodes' );
				return;
			}

			if( ( $tag = self::_disallowedTag( $nodes ) ) !== null ) {
				\Nino\Http::fail( $request, 400, 'refusing to write a <'. $tag. '> element' );
				return;
			}

			$source = \Nino\Templates\Serializer::serialize( $nodes );

			if( trim( $source ) === '' ) {
				\Nino\Http::fail( $request, 400, 'refusing to write an empty template' );
				return;
			}

			if( self::_write( $path, $source ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write '. basename( $path ). ' - check its file permissions' );
				return;
			}

			\Nino\Http::ok( $request, [ 'name' => $name, 'bytes' => strlen( $source ) ] );
		}

		/**
		 *	The first tag in a tree that isn't on the allowlist, if any -
		 *	also what makes an event-handler attribute impossible to
		 *	smuggle in on an otherwise fine element, since those are
		 *	checked here too
		 *
		 *	@param		array 		$nodes
		 *
		 *	@return 	string|null							Null if the whole tree is writable
		 */
		private static function _disallowedTag( array $nodes ): ?string {

			foreach( $nodes as $node ) {

				if( ( $node['type'] ?? '' ) !== 'element' )
					continue;

				$tag = strtolower( (string) ( $node['tag'] ?? '' ) );

				if( in_array( $tag, self::ALLOWED_TAGS, true ) === false )
					return $tag;

				foreach( array_keys( (array) ( $node['attrs'] ?? [] ) ) as $attribute )
					if( str_starts_with( strtolower( (string) $attribute ), 'on' ) === true )
						return $tag. ' '. $attribute;

				if( ( $found = self::_disallowedTag( (array) ( $node['children'] ?? [] ) ) ) !== null )
					return $found;
			}

			return null;
		}

		/**
		 *	@param		string		$name					Template name, without the .tpl extension
		 *
		 *	@return 	bool
		 */
		private static function _isEditable( string $name ): bool {

			foreach( self::EDITABLE_PREFIXES as $prefix )
				if( str_starts_with( $name, $prefix ) === true )
					return true;

			return false;
		}

		/**
		 *	Resolve a posted template name to its file. The name is matched
		 *	against a plain slug first - no separators, no dots - so it can
		 *	only ever name a file directly inside /templates
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name
		 *
		 *	@return 	string|null							Null if $name is not a usable template name
		 */
		private static function _path( array &$appData, string $name ): ?string {

			if( preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name ) !== 1 || str_contains( $name, '..' ) === true )
				return null;

			return \Nino\Filesystem::getPath( $appData ). '/templates/'. $name. '.tpl';
		}

		/**
		 *	Replace a template's content atomically - write a temp file next
		 *	to it, then rename() over the target. Same reasoning as
		 *	Install::_writeFileAtomic(): a half-written page template is a
		 *	broken site, and a rename() is the one filesystem operation that
		 *	can't leave one behind
		 *
		 *	@param		string		$path
		 *	@param		string		$content
		 *
		 *	@return 	bool
		 */
		private static function _write( string $path, string $content ): bool {

			$temp 	= $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
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
}
