<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Blocks				A piece of a page that a tool puts in: a tree of components, its texts in every language, its picture slots
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Blocks - the pieces of a page a tool offers to put in: a hero, two columns of a picture and a text, the header
	 *	or the footer of every page. A block is data and has no code: a folder blocks/<key>/ with
	 *
	 *		block.json			name and description in every language, the category, the rank, the picture slots, optionally the variants
	 *		block.tpl				one call - a section-wrap, a header-wrap or a footer-wrap - with an id, made of components
	 *		text/<locale>.json	the words of the sources the template reads with ~name, one file for each language
	 *		images/<name>.<ext>	optional: the picture of a slot of block.json, svg, webp, png or jpg, at most one for a slot
	 *
	 *	Blocks are read from four places: the folder blocks/ of every feature that is on (the one that comes with the kernel,
	 *	Basis, among them), the folder blocks/ of the project's private tree, the packages of blocks that are installed
	 *	(private/packages/blocks/<key>/<block>/, see \Nino\Packages; the origin is 'pack:' and the key of the package, the label the name in its
	 *	pack.json), and the roots that a callback of the hook
	 *	'/nino/blocks' adds - it is handed a list and answers it with [ 'dir' => the absolute path of a folder of blocks, 'origin'
	 *	=> a word that says whose they are, 'label' => the same for a person: a text or a locale => text map, optional ] added, which is
	 *	where a theme or a package of blocks puts its own. The label of Basis and of every other feature is the name of the feature, the
	 *	one of the project is PROJECT; an entry of the hook without a label, or with one that is none, is labelled by its origin.
	 *
	 *	What a block may be is checked by validate(), the same for each place - a block of the project is no more trusted than one of the
	 *	kernel's - and a block that is not what it says it is is left out of all(), said once in the log, and named by rejected():
	 *
	 *	- the folder is a name of lower case letters, digits and hyphens that no other block has, a folder of its own and no link; it holds
	 *	  block.json, block.tpl, text/ and, where a slot has a picture, images/ - and nothing else - no php, whatever it is called - and text/ holds
	 *	  <locale>.json (xx_XX) and nothing else, en_US among them; a name that begins with a dot is the file system's, not read
	 *	- block.json and the texts are json of a depth and a length that are limited, keys that are words, the same keys in every language,
	 *	  and no control character, no < and no [[ in any text
	 *	- the template is at most MAX_TEMPLATE bytes of utf-8, is read by \Nino\Markup with no note and written back byte for byte, and is one call
	 *	  of a root (ROOTS) that has an id; it has no [[ - a block reads no key of the project - and \Nino\Components::check() finds nothing
	 *	  against it but a variant that the active mixture of the components does not have, which the block names and the tool tells
	 *	  of: no name nobody registered - but a name that a feature which is off brings (components/<name>/) makes the block one that needs that
	 *	  feature: it is listed with the keys in 'missing', and get() gives nothing of it to draw or put in -, no call that has no schema, no [html], no html that is no component, nothing at a place that is not
	 *	  its, no value outside what its attribute takes
	 *	- the id of a call inside the block begins with the id of the root and a hyphen (rule B6): a tool that puts the block in makes the id of
	 *	  the root free and gives the ones inside the same new beginning, and the words of a call that has an id are the keys below it
	 *	- a source is ~name, a picture of \Nino\Images::PLACEHOLDERS or the type of elements of \Nino\Elements::PLACEHOLDERS - below which
	 *	  .field is a field of its model - and nothing else: no key of the project, no slot, no file; a value of an attribute that begins like
	 *	  a source is the same
	 *	- each ~name has a text in every language, and each text is read; a ~name that is a picture has a slot of block.json with a placeholder
	 *	  of the kernel, and each slot is read. A slot may have a picture of its own, images/<slot>.svg|webp|png|jpg: at most MAX_PICTURE
	 *	  bytes, MAX_SIDE pixels on a side, of the type its ending says (\Nino\Images::size()), a svg that \Nino\Images::cleanSvg() takes -
	 *	  and no other file in images/
	 *	- the category says what the root is: 'frame' for a header-wrap and a footer-wrap, one of the others for a section-wrap
	 *	- a variant is a name, a label in every language and a set: place => attribute => value, where a place is 'root' (the root of the template) or
	 *	  the indices from the root down, the blanks counted ('1.1.1', the position \Nino\Markup gives a node and the Builder writes), and the
	 *	  attribute is one of the schema of the component that stands there that is a look - a choice or a switch the register writes as a class of the
	 *	  component, never a source, a text, a picture, a link, an id, the class itself or an attribute that goes into the markup - with a value the schema takes, that is no source. A variant changes how the components stand and
	 *	  never what they say: no markup, no source and no link target comes into a page by it. At most MAX_VARIANTS; see variantOf() and setVariant()
	 *
	 *	The name a text has in its file is the name of the source for a call whose nearest id above is the id of the root, and for one
	 *	below another id the part of that id behind the id of the root and a hyphen, a hyphen and the name: ~title below
	 *	'cards-a', in a block whose root is 'cards', is 'a-title'. Two sources that come to one name are a block that is refused.
	 *
	 *	What was read is kept in a file of the project's cache with the stamp of every file it was read from, the version of the register of components and
	 *	the kernel's, as the register keeps the schemas of a feature: where nothing changed no file is read or checked and nothing is
	 *	said - the folders are still listed and the size and time of each file looked at, which is what the stamp is made of. A block that is refused is kept too, with its reason, so that it is said when it is read and not at every request.
	 *	'/nino/markup/cache' => false turns the file off, and a folder that cannot be written is a read at every request. It is a file
	 *	that is made again and no thing a backup takes; \Nino\Html::dropTrees() - and so the page cache - removes it
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Blocks {

		// The hook that adds roots, see roots()
		public const string HOOK = '/nino/blocks';

		// Where what was read is kept, below the project's data, and where a block of the project's own lives, below its private tree
		public const string CACHE = '/data/cache/blocks.php';
		public const string OWN = '/blocks';

		// The root of the template of a block: pages are a flat run of the first, the frames are the other two
		public const array ROOTS = [ 'section-wrap', 'header-wrap', 'footer-wrap' ];

		// What a block is filed under, in the order a tool lists them; 'frame' is what a header and a footer are
		public const array CATEGORIES = [ 'intro', 'content', 'trust', 'offer', 'action', 'contact', 'frame' ];

		// The language a block always has, since it is the one a site that has no text of its own for a language takes
		public const string FALLBACK = 'en_US';

		// How much of a block is read: the template, the json files and a name or a text, in bytes or characters
		public const int MAX_TEMPLATE = 16384;
		public const int MAX_JSON = 16384;
		public const int MAX_NAME = 80;
		public const int MAX_DESCRIPTION = 240;
		public const int MAX_TEXT = 600;
		public const int MAX_TEXTS = 60;
		public const int MAX_IMAGES = 8;
		public const int MAX_VARIANTS = 8;

		// The picture a slot may bring: bytes, pixels on a side, and the type of the picture each ending stands for (a svg is read by cleanSvg())
		public const int MAX_PICTURE = 524288;
		public const int MAX_SIDE = 4096;
		public const array PICTURE_TYPES = [ 'webp' => IMAGETYPE_WEBP, 'png' => IMAGETYPE_PNG, 'jpg' => IMAGETYPE_JPEG ];

		// Where the packages of blocks are installed, below the private tree of the project, and the files that stand beside the folders of the blocks in one
		public const string PACKAGES = '/packages/blocks';
		public const string PACK = 'pack:';
		public const array PACK_FILES = [ 'pack.json', 'preview.webp', 'LICENSE.txt' ];

		// How many levels of json a file may have, how long the id of a root and the word of a source may be
		// block.json has one level more: a variant is a name, a set, a place and its attributes
		private const int DEPTH = 5;
		private const int DEPTH_META = 6;
		private const int ID_LENGTH = 32;

		// The file names of a block; the key of a block, the root of a folder, a locale, an id, the name of a text or a slot and the name of a source
		private const string ID = '/^[a-z][a-z0-9-]*$/D';
		private const string LOCALE = '/^[a-z]{2}_[A-Z]{2}$/D';
		private const string WORD = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';
		private const string SOURCE = '/^~[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D';
		private const string PICTURE = '/^([a-z0-9]+(?:-[a-z0-9]+)*)\.(svg|webp|png|jpg)$/D';

		// The place of a variant's set: the root of the template, or the indices from the root down, no leading zero
		private const string PLACE = '/^(?:root|(?:0|[1-9][0-9]{0,3})(?:\.(?:0|[1-9][0-9]{0,3})){0,15})$/D';

		// What no folder of a block holds, whatever the name is spelt like
		private const string CODE = '/\.(?:php\d?|phtml|phar|inc)(?:\.|$)/i';

		// Where the words of ~name are read under, while a block is looked at: any template name does
		private const string TEMPLATE = 'block';

		// What a person reads where the origin is the project's own, in each language the kernel has words for
		public const array PROJECT = [ 'en_US' => 'Project', 'de_DE' => 'Projekt' ];

		// What the cache of the request keeps
		private const string STATE = './nino/blocks/state';
		private const string SAID = './nino/blocks/said';

		/**
		 *	The blocks that are there, in the order a tool lists them: by category, by rank, by key
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										key => [ 'key', 'origin' => the key of the feature, 'project' or what the hook said, 'label' => what a person reads for the origin (text or locale => text map), 'name' and 'description' =>
		 *																	locale => text, 'category', 'rank', 'wrap' => the root, 'locales', 'features' => the keys of
		 *																	the features whose components it calls - worked out from the register -, 'variants' => component => the
		 *																	variants it names, 'images' => name => [ 'placeholder', 'width', 'height', 'label' => locale => text ], 'pictures' => slot =>
		 *																	'images/<slot>.<ext>', the file below the folder of the block, for each slot that has a picture of its own,
		 *																	'missing' => the keys of the features that are off and whose components it calls, [] where it can be put in,
		 *																	'alternatives' => the variants block.json names, in its order: name => [ 'label' => locale => text, 'set' => place => attribute => value ], [] for none ]
		 */
		public static function all( array &$appData ): array {

			$list = [];

			foreach( self::_state( $appData )['blocks'] as $key => $block )
				$list[$key] = array_diff_key( $block, [ 'texts' => 1, 'source' => 1, 'slots' => 1, 'id' => 1 ] );

			return $list;
		}

		/**
		 *	One block as a whole: what all() says of it, and
		 *
		 *	- 'tree'		the nodes of its template, as \Nino\Markup::parse() answers them
		 *	- 'source'	the template as it is written
		 *	- 'id'			the id of its root
		 *	- 'texts'		locale => the name of a text => the text
		 *	- 'slots'		each source of the template that is read from the block: [ 'name' => the name the text or the slot has in the files,
		 *								'scope' => the id of the call that holds it without the id of the root and its hyphen, '' for the root's own words,
		 *								'source' => ~name, 'kind' => text, link or image, 'component' ]
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The key of the block
		 *
		 *	@return 	array|null								null for a key that is none of the blocks that are listed; for one with 'missing' what all() says and none of the above
		 */
		public static function get( array &$appData, string $key ): ?array {

			$block = self::_state( $appData )['blocks'][$key] ?? null;

			if( is_array( $block ) === false )
				return null;

			// A block that needs a feature that is off is told of and nothing else: there is nothing in it to draw or to put in
			if( $block['missing'] !== [] )
				return array_diff_key( $block, [ 'texts' => 1, 'source' => 1, 'slots' => 1, 'id' => 1 ] );

			$block['tree'] = \Nino\Markup::parse( $block['source'], \Nino\Components::containers( $appData ) );

			return $block;
		}

		/**
		 *	The blocks that were found and are not listed, and why
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										'<origin>/<folder>' => the reason, as the rest of a sentence
		 */
		public static function rejected( array &$appData ): array {

			return self::_state( $appData )['rejected'];
		}

		/**
		 *	Which variant of a block a tree has: the first, in the order of block.json, whose set holds at every place of it and with which every
		 *	attribute that any other variant sets has the value the template has. A value is read as the register reads it: an attribute that is
		 *	not written has the default of its schema, so that reverse="off" is not written and is the same. A tree whose places are not those of the
		 *	template - a call is taken out, put in or moved at a level on the way to a place any variant names - is its own: null, as is one with no call.
		 *	Pure: nothing is read but what is handed in
		 *
		 *	The places count in the form a block is put into a page in: the root of the template as \Nino\Markup::layout() sets it out, a call on a line
		 *	of its own with the blanks between the calls counted, whatever the file block.tpl has between its calls. The trees are lists of nodes as
		 *	\Nino\Markup::parse() answers them: the first call of the list is the root of the block, and a block that is put into a tree of a page as
		 *	one node is handed as [ $node ]
		 *
		 *	@param		array			$block				A block as get() answers it, with its 'tree', 'alternatives' and 'defaults' - never what a request brings
		 *	@param		array			$tree					The tree of the block as it stands in a page
		 *
		 *	@return 	string|null								The name of the variant, null where none is the tree's: it is its own
		 */
		public static function variantOf( array $block, array $tree ): ?string {

			$alternatives = self::_sets( $block['alternatives'] ?? null );
			$laid = self::_laid( $block['tree'] ?? null );
			$touched = self::_touched( $alternatives );

			if( $alternatives === [] || $laid === [] || self::_root( $tree ) === null )
				return null;

			$fits = [];

			foreach( $touched as $place => $attributes ) {

				$way = self::_fit( $tree, $laid, (string) $place );

				if( $way === null )
					return null;

				$fits[$place] = [ self::_node( $tree, $way[0] ), self::_node( $laid, $way[1] ) ];
			}

			foreach( $alternatives as $name => $variant ) {

				$holds = true;

				foreach( $touched as $place => $attributes )
					foreach( $attributes as $attribute => $unused ) {

						[ $node, $origin ] = $fits[$place];
						$meta = $block['defaults'][$place][$attribute] ?? null;
						$expected = array_key_exists( $attribute, $variant[$place] ?? [] ) === true ? $variant[$place][$attribute] : ( $origin['a'][$attribute] ?? null );

						if( self::_value( $node['a'][$attribute] ?? null, $meta ) !== self::_value( $expected, $meta ) )
							$holds = false;
					}

				if( $holds === true )
					return (string) $name;
			}

			return null;
		}

		/**
		 *	Whether the places of the variants are in a tree: every place any variant of the block names is a call of the name the template has there,
		 *	and on the way to it each level has the same run of calls and blanks as the template. A tree that is not built like that is no tree whose
		 *	variant is told or set - a tool shows no choice of a variant for it. A block with no variants fits nothing. Pure, and in the form of variantOf()
		 *
		 *	@param		array			$block				A block as get() answers it
		 *	@param		array			$tree					The tree of the block as it stands in a page
		 *
		 *	@return 	bool
		 */
		public static function variantsFit( array $block, array $tree ): bool {

			$alternatives = self::_sets( $block['alternatives'] ?? null );
			$laid = self::_laid( $block['tree'] ?? null );

			if( $alternatives === [] || $laid === [] || self::_root( $tree ) === null )
				return false;

			foreach( self::_touched( $alternatives ) as $place => $attributes )
				if( self::_fit( $tree, $laid, (string) $place ) === null )
					return false;

			return true;
		}

		/**
		 *	The tree of a block with a variant set: every attribute that any variant of the block sets is as the template has it, and then the set of the
		 *	variant is on - a value that is the default of its attribute is not written, the attribute is taken out. What no variant names - the texts, the
		 *	sources, the children, an attribute a person changed - stays as it is, to the byte. A place that is not the template's, as variantsFit() says,
		 *	is left out; an unknown name gives the tree back. Nothing is kept of the variant that was set before. Pure, and in the form of variantOf()
		 *
		 *	@param		array			$block				A block as get() answers it, with its 'tree', 'alternatives' and 'defaults' - never what a request brings
		 *	@param		array			$tree					The tree of the block as it stands in a page
		 *	@param		string		$name					The name of the variant
		 *
		 *	@return 	array										The tree
		 */
		public static function setVariant( array $block, array $tree, string $name ): array {

			$alternatives = self::_sets( $block['alternatives'] ?? null );
			$laid = self::_laid( $block['tree'] ?? null );

			if( array_key_exists( $name, $alternatives ) === false || $laid === [] )
				return $tree;

			foreach( self::_touched( $alternatives ) as $place => $attributes ) {

				$way = self::_fit( $tree, $laid, (string) $place );

				if( $way === null )
					continue;

				$origin = self::_node( $laid, $way[1] );
				$values = [];

				foreach( $attributes as $attribute => $unused )
					$values[$attribute] = array_key_exists( $attribute, $origin['a'] ) === true ? [ $origin['a'][$attribute] ] : [];

				foreach( $alternatives[$name][$place] ?? [] as $attribute => $value ) {

					$meta = $block['defaults'][$place][$attribute] ?? null;
					$values[$attribute] = $meta !== null && self::_value( $value, $meta ) === self::_value( null, $meta ) ? [] : [ $value ];
				}

				$node = &$tree[$way[0][0]];

				foreach( array_slice( $way[0], 1 ) as $step )
					$node = &$node['c'][$step];

				foreach( $values as $attribute => $value )
					if( $value === [] )
						unset( $node['a'][$attribute] );
					else
						$node['a'][$attribute] = $value[0];

				unset( $node );
			}

			return $tree;
		}

		/**
		 *	The places blocks are read from, in the order the first block of a key wins: Basis, the
		 *	project's own folder, the installed packages of blocks by their keys, the other features that are on by their keys, and
		 *	what the hook adds. A package is a thing the project chose to install: it stands behind what the project wrote itself and before
		 *	the features, so that no feature that is switched on later puts out a block of it. The origin of a package is 'pack:<key>', a name no feature, no
		 *	project and no root of the hook can have, so that no origin is another's. An entry the hook adds that is no [ 'dir', 'origin' ] of the kind the root has
		 *	is said once and left out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'origin' => ..., 'label' => text or locale => text map, 'dir' => the path of the folder, 'own' => the path it must be inside of, so that it is no link, or '',
		 *																	'except' => the names in the folder that are no blocks, optional ], ... ]
		 */
		public static function roots( array &$appData ): array {

			$roots = [];

			// Basis first, then the project, then the other features by their keys: a block of the project is never put out of the list by a
			// feature that is switched on later, and none of them puts out one of Basis
			$features = array_filter( \Nino\Features::all( $appData ), static fn( array $feature ): bool => $feature['active'] === true );
			$content = (string) ( $appData['./nino/filesystem/contentpath'] ?? '' );

			if( isset( $features['basis'] ) === true )
				$roots[] = [ 'origin' => 'basis', 'label' => $features['basis']['name'], 'dir' => $features['basis']['dir']. '/blocks', 'own' => $features['basis']['dir'] ];

			if( $content !== '' )
				$roots[] = [ 'origin' => 'project', 'label' => self::PROJECT, 'dir' => $content. self::OWN, 'own' => $content ];

			if( $content !== '' )
				foreach( self::_packages( $content. self::PACKAGES ) as $package )
					$roots[] = $package;

			foreach( $features as $key => $feature )
				if( $key !== 'basis' )
					$roots[] = [ 'origin' => $key, 'label' => $feature['name'], 'dir' => $feature['dir']. '/blocks', 'own' => $feature['dir'] ];

			$given = [];
			$given = \Nino\Callbacks::doCallbacks( $appData, self::HOOK, $given );

			foreach( is_array( $given ) === true ? $given : [] as $entry )
				if( is_array( $entry ) === true && is_string( $entry['dir'] ?? null ) === true && $entry['dir'] !== '' && $entry['dir'][0] === '/'
					&& is_string( $entry['origin'] ?? null ) === true && preg_match( self::ID, $entry['origin'] ) === 1 && strlen( $entry['origin'] ) <= \Nino\Components::NAME_LENGTH
					&& str_contains( $entry['dir'], "\0" ) === false )
				{
					$label = $entry['label'] ?? $entry['origin'];

					if( self::_label( $label ) === false ) {
						self::_said( $appData, 'label '. md5( serialize( $entry ) ), 'Blocks: the "label" of an entry of the hook "'. self::HOOK. '" is no text or map of language => text of at most '. self::MAX_NAME. ' characters, without control character, < and > - the origin is read instead' );
						$label = $entry['origin'];
					}

					$roots[] = [ 'origin' => $entry['origin'], 'label' => $label, 'dir' => rtrim( $entry['dir'], '/' ), 'own' => '' ];
				}
				else
					self::_said( $appData, 'hook '. md5( serialize( $entry ) ), 'Blocks: an entry of the hook "'. self::HOOK. '" is no array of an absolute "dir" and an "origin" of lower case letters, digits and hyphens - left out' );

			return $roots;
		}

		/**
		 *	Whether the parts of a block are one: the files of its folder as read() reads them, or what a tool made - which is how a tool checks
		 *	what it is about to write, with the same rules the files are read by
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The key the block is to have
		 *	@param		array			$parts				[ 'json' => the text of block.json, 'tpl' => the text of block.tpl, 'texts' => locale => the text of the file,
		 *																	'pictures' => the name of a file below images/ => its bytes, optional ]
		 *
		 *	@return 	array|string							The block as all() and get() answer it, without its origin; or what is wrong with it
		 */
		public static function validate( array &$appData, string $key, array $parts ): array|string {

			if( strlen( $key ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $key ) !== 1 )
				return 'a key is a slug of lower case letters, digits and hyphens, at most '. \Nino\Components::NAME_LENGTH. ' characters';

			$meta = self::_json( (string) ( $parts['json'] ?? '' ), 'block.json', self::DEPTH_META );

			if( is_string( $meta ) === true )
				return $meta;

			$texts = [];

			foreach( (array) ( $parts['texts'] ?? [] ) as $locale => $raw ) {

				$read = self::_json( (string) $raw, 'text/'. self::_shown( (string) $locale ). '.json' );

				if( is_string( $read ) === true )
					return $read;

				$texts[(string) $locale] = $read;
			}

			$locales = array_keys( $texts );

			foreach( $locales as $locale )
				if( preg_match( self::LOCALE, $locale ) !== 1 )
					return 'a file of text/ is named <xx_XX>.json: "'. self::_shown( $locale ). '"';

			if( in_array( self::FALLBACK, $locales, true ) === false )
				return 'there is no text/'. self::FALLBACK. '.json: it is the language a site that has none of its own takes';

			sort( $locales );
			$names = array_keys( $texts[self::FALLBACK] );

			foreach( $texts as $locale => $words ) {

				if( count( $words ) > self::MAX_TEXTS )
					return 'text/'. $locale. '.json has more than '. self::MAX_TEXTS. ' texts';

				foreach( $words as $name => $text ) {

					if( is_string( $name ) === false || preg_match( self::WORD, $name ) !== 1 || strlen( $name ) > 2 * \Nino\Components::NAME_LENGTH )
						return 'text/'. $locale. '.json: "'. self::_shown( (string) $name ). '" is no name of a text - words of lower case letters and digits, joined by hyphens';

					$problem = self::_text( $text, self::MAX_TEXT, true );

					if( $problem !== null )
						return 'text/'. $locale. '.json, "'. $name. '": '. $problem;
				}

				if( array_diff( array_keys( $words ), $names ) !== [] || array_diff( $names, array_keys( $words ) ) !== [] )
					return 'text/'. $locale. '.json has not the names that text/'. self::FALLBACK. '.json has: a block has the same texts in each language';
			}

			$block = self::_meta( $meta, $locales, json_decode( (string) ( $parts['json'] ?? '' ), false, self::DEPTH_META ) );

			if( is_string( $block ) === true )
				return $block;

			$own = self::_pictures( (array) ( $parts['pictures'] ?? [] ), $block['images'] );

			if( is_string( $own ) === true )
				return $own;

			$found = self::_template( $appData, (string) ( $parts['tpl'] ?? '' ) );

			if( is_string( $found ) === true )
				return $found;

			if( ( $found['wrap'] === 'section-wrap' ) === ( $block['category'] === 'frame' ) )
				return 'the category "'. $block['category']. '" is not one for a '. $found['wrap']. ': a frame is a header-wrap or a footer-wrap, the rest are section-wraps';

			$defaults = self::_places( $appData, $found, $block['alternatives'] );

			if( is_string( $defaults ) === true )
				return $defaults;

			// What the template reads, by the name it has in the files: each text is read and each source has its text
			$used = [];

			foreach( $found['slots'] as $slot ) {

				$kind = $slot['kind'] === 'image' ? 'image' : 'text';
				$where = $slot['scope']. '|'. $slot['source'];

				if( isset( $used[$slot['name']] ) === true && $used[$slot['name']]['kind'] !== $kind )
					return 'the name "'. $slot['name']. '" is read as a text and as a picture';

				if( isset( $used[$slot['name']] ) === true && $used[$slot['name']]['where'] !== $where )
					return 'two sources come to the one name "'. $slot['name']. '" in the files of the block';

				$used[$slot['name']] = [ 'kind' => $kind, 'where' => $where ];
			}

			// The text a link reads is its address, in every language: the same rule as for one that is written into the template
			foreach( $found['slots'] as $slot )
				if( $slot['kind'] === 'link' )
					foreach( $texts as $locale => $said )
						if( isset( $said[$slot['name']] ) === true && \Nino\Components::address( (string) $said[$slot['name']] ) === false )
							return 'text/'. $locale. '.json, "'. $slot['name']. '": a link reads it, and it is no address a link is drawn for';

			$words = array_keys( array_filter( $used, static fn( array $use ): bool => $use['kind'] === 'text' ) );
			$pictures = array_keys( array_filter( $used, static fn( array $use ): bool => $use['kind'] === 'image' ) );

			// What a component that is not registered reads is not known: a block that needs one is held to the sources that are
			foreach( $found['missing'] !== [] ? [] : [
				[ array_diff( $words, $names ), 'the template reads ~%s and the texts have none of that name' ],
				[ array_diff( $names, $words ), 'the texts have "%s" and the template reads no such source' ],
				[ array_diff( $pictures, array_keys( $block['images'] ) ), 'the template reads a picture "%s" that block.json has no slot of' ],
				[ array_diff( array_keys( $block['images'] ), $pictures ), 'block.json has a slot "%s" that the template reads no picture of' ],
			] as [ $missing, $sentence ] )
				if( $missing !== [] )
					return sprintf( $sentence, self::_shown( (string) reset( $missing ) ) );

			$components = array_keys( $found['calls'] );
			$features = [];

			foreach( $components as $name )
				if( is_string( $appData[\Nino\Components::OWNERS]['names'][$name] ?? null ) === true )
					$features[] = $appData[\Nino\Components::OWNERS]['names'][$name];

			$features = array_values( array_unique( array_merge( $features, $found['missing'] ) ) );
			sort( $features );

			return [
				'key'					=> $key,
				'name'				=> $block['name'],
				'description'	=> $block['description'],
				'category'		=> $block['category'],
				'rank'				=> $block['rank'],
				'wrap'				=> $found['wrap'],
				'locales'			=> $locales,
				'features'		=> $features,
				'missing'			=> $found['missing'],
				'variants'		=> $found['variants'],
				'alternatives'	=> $block['alternatives'],
				'defaults'		=> $defaults,
				'images'			=> $block['images'],
				'pictures'		=> $own,
				'id'					=> $found['id'],
				'source'			=> (string) $parts['tpl'],
				'slots'				=> $found['slots'],
				'texts'				=> $texts,
			];
		}

		/**
		 *	Forget what this request read of the blocks, so that the next call reads the folders again: after a package of blocks was put in place or taken away
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function forget( array &$appData ): void {

			unset( $appData[self::STATE] );
		}

		/**
		 *	The state of the blocks for this request: what is in the cache, where that is what the files are, or what the folders hold
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ 'blocks' => key => block, 'rejected' => '<origin>/<folder>' => reason ]
		 */
		private static function _state( array &$appData ): array {

			$version = \Nino\Components::version( $appData );

			if( is_array( $appData[self::STATE] ?? null ) === true && $appData[self::STATE]['version'] === $version )
				return $appData[self::STATE];

			$roots = self::roots( $appData );
			$stamp = self::_stamp( $roots ) + [ 'absent' => self::_absent( $appData ) ];
			$on = ( $appData[\Nino\Html::TREES_SWITCH] ?? true ) !== false;
			$stored = $on === true ? self::_stored( $appData ) : null;

			if( $stored !== null && $stored['kernel'] === \Nino\VERSION && $stored['version'] === $version && $stored['stamp'] === $stamp )
				return $appData[self::STATE] = [ 'version' => $version, 'blocks' => $stored['blocks'], 'rejected' => $stored['rejected'] ];

			$blocks = [];
			$rejected = [];

			foreach( $roots as $root )
				foreach( self::_names( $root ) as $name ) {

					$where = $root['origin']. '/'. $name;
					$parts = self::_read( $root, $name );
					$block = is_string( $parts ) === true ? $parts : self::validate( $appData, $name, $parts );

					if( is_string( $block ) === false && isset( $blocks[$name] ) === true )
						$block = 'the key is the one of the block of "'. $blocks[$name]['origin']. '" already';

					if( is_string( $block ) === true ) {

						$rejected[$where] = $block;
						self::_said( $appData, $where, 'Blocks: block "'. self::_shown( $name ). '" of "'. $root['origin']. '": '. $block. ' - not listed' );

						continue;
					}

					$blocks[$name] = [ 'origin' => $root['origin'], 'label' => $root['label'] ] + $block;
				}

			uasort( $blocks, static fn( array $a, array $b ): int => [ array_search( $a['category'], self::CATEGORIES, true ), $a['rank'], $a['key'] ] <=> [ array_search( $b['category'], self::CATEGORIES, true ), $b['rank'], $b['key'] ] );

			if( $on === true ) {

				// Written atomically and without a lock, as the register of a feature is: two requests that write it write the same blocks
				\Nino\Filesystem::putFileContent( $appData, self::CACHE, [ 'kernel' => \Nino\VERSION, 'version' => $version, 'stamp' => $stamp, 'blocks' => $blocks, 'rejected' => $rejected ], true );
				unset( $appData['./nino/filesystem/cache'][self::CACHE] );
			}

			return $appData[self::STATE] = [ 'version' => $version, 'blocks' => $blocks, 'rejected' => $rejected ];
		}

		/**
		 *	The packages of blocks that are installed: a folder of a key in the folder of the packages, with the name its pack.json says
		 *
		 *	@param		string		$base					The folder of the packages of blocks
		 *
		 *	@return 	array										Roots as roots() lists them, by the key of the package
		 */
		private static function _packages( string $base ): array {

			$packages = [];

			$real = is_dir( $base ) === true && is_link( $base ) === false ? realpath( $base ) : false;

			if( $real === false )
				return $packages;

			foreach( @scandir( $base ) ?: [] as $entry ) {

				if( strlen( $entry ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $entry ) !== 1 || self::_folder( $base. '/'. $entry, $base ) === false )
					continue;

				$label = $entry;
				$raw = self::_file( $real. '/'. $entry. '/pack.json', self::MAX_JSON );

				if( $raw !== null ) {

					$pack = json_decode( $raw, true, self::DEPTH );

					if( is_array( $pack ) === true && self::_label( $pack['name'] ?? null ) === true )
						$label = $pack['name'];
				}

				$packages[] = [ 'origin' => self::PACK. $entry, 'label' => $label, 'dir' => $base. '/'. $entry, 'own' => $base, 'except' => self::PACK_FILES ];
			}

			return $packages;
		}

		/**
		 *	The pictures a block has of its own: the files of images/ against the slots of block.json
		 *
		 *	@param		array			$pictures			The name of a file => its bytes
		 *	@param		array			$slots				The slots of block.json, by name
		 *
		 *	@return 	array|string							slot => 'images/<file>', or what is wrong
		 */
		private static function _pictures( array $pictures, array $slots ): array|string {

			$found = [];

			foreach( $pictures as $file => $bytes ) {

				$file = (string) $file;

				if( preg_match( self::PICTURE, $file, $match ) !== 1 )
					return 'images/ holds "'. self::_shown( $file ). '": there are <slot>.svg, .webp, .png and .jpg files and nothing else';

				[ , $name, $type ] = $match;

				if( isset( $slots[$name] ) === false )
					return 'images/'. $file. ' is the picture of no slot of block.json';

				if( isset( $found[$name] ) === true )
					return 'the slot "'. $name. '" has more than one picture';

				if( is_string( $bytes ) === false || $bytes === '' || strlen( $bytes ) > self::MAX_PICTURE )
					return 'images/'. $file. ' is empty or longer than '. self::MAX_PICTURE. ' bytes';

				if( $type === 'svg' ) {

					if( \Nino\Images::cleanSvg( $bytes ) === null )
						return 'images/'. $file. ' is no svg that the kernel takes';
				}
				else {

					$size = \Nino\Images::size( $bytes );

					if( $size === false || $size['type'] !== self::PICTURE_TYPES[$type] )
						return 'images/'. $file. ' is no '. $type. ' picture';

					if( $size['width'] > self::MAX_SIDE || $size['height'] > self::MAX_SIDE )
						return 'images/'. $file. ' is wider or higher than '. self::MAX_SIDE. ' pixels';
				}

				$found[$name] = 'images/'. $file;
			}

			ksort( $found );

			return $found;
		}

		/**
		 *	The components that the features which are off bring, by the name of the folder components/<name>/ of each: name => the key of the
		 *	feature, the first by key where two bring one. Read once in a request; the kernel does not read a feature that is off, so that
		 *	nothing but the names of the folders is looked at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _absent( array &$appData ): array {

			if( is_array( $appData['./nino/blocks/absent'] ?? null ) === true )
				return $appData['./nino/blocks/absent'];

			$absent = [];

			foreach( \Nino\Features::all( $appData ) as $key => $feature ) {

				if( $feature['active'] === true || is_dir( $feature['dir']. '/components' ) === false )
					continue;

				foreach( @scandir( $feature['dir']. '/components' ) ?: [] as $entry )
					if( $entry[0] !== '.' && preg_match( \Nino\Components::NAME, $entry ) === 1 && isset( $absent[$entry] ) === false && is_dir( $feature['dir']. '/components/'. $entry ) === true )
						$absent[$entry] = (string) $key;
			}

			return $appData['./nino/blocks/absent'] = $absent;
		}

		/**
		 *	The names of the folders of a root that may be blocks, as the root says them: a root that is a link inside what it must be inside of, or is no
		 *	folder, has none. A name that is no slug is the one of a folder that is refused later, by read()
		 *
		 *	@param		array			$root					An entry of roots()
		 *
		 *	@return 	array										Names, in the order of the folder
		 */
		private static function _names( array $root ): array {

			if( self::_folder( $root['dir'], $root['own'] ) === false )
				return [];

			$names = [];

			foreach( @scandir( $root['dir'] ) ?: [] as $entry )
				if( $entry[0] !== '.' && in_array( $entry, (array) ( $root['except'] ?? [] ), true ) === false )
					$names[] = $entry;

			return $names;
		}

		/**
		 *	Whether a path is a folder of its own: it is one, and its real path is the path - so that nothing on the way inside what it belongs to
		 *	is a link. A path with nothing it must be inside of is a root the hook named, which is the code of the project and may be what it likes
		 *
		 *	@param		string		$path
		 *	@param		string		$inside				The folder the path belongs to, '' for none
		 *
		 *	@return 	bool
		 */
		private static function _folder( string $path, string $inside ): bool {

			if( is_dir( $path ) === false || str_contains( $path, "\0" ) === true )
				return false;

			$real = realpath( $path );

			if( $real === false )
				return false;

			if( $inside === '' )
				return true;

			$base = realpath( $inside );

			return $base !== false && $real === $base. '/'. basename( $path );
		}

		/**
		 *	The files of a folder of a block, read: block.json, block.tpl and text/<locale>.json
		 *
		 *	@param		array			$root					An entry of roots()
		 *	@param		string		$name					The name of the folder
		 *
		 *	@return 	array|string							The parts validate() takes, or what is wrong with the folder
		 */
		private static function _read( array $root, string $name ): array|string {

			if( strlen( $name ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $name ) !== 1 )
				return 'a folder of a block is named with a slug of lower case letters, digits and hyphens, at most '. \Nino\Components::NAME_LENGTH. ' characters: "'. self::_shown( $name ). '"';

			$base = realpath( $root['dir'] );
			$path = $base. '/'. $name;

			if( $base === false || is_dir( $path ) === false || realpath( $path ) !== $path )
				return 'the folder is a link, or no folder';

			$parts = [ 'json' => '', 'tpl' => '', 'texts' => [], 'pictures' => [] ];
			$seen = [];

			foreach( @scandir( $path ) ?: [] as $entry ) {

				if( $entry === '.' || $entry === '..' )
					continue;

				if( preg_match( self::CODE, $entry ) === 1 )
					return 'the folder holds "'. self::_shown( $entry ). '": a block is data and has no code';

				if( $entry[0] === '.' )
					continue;

				if( $entry === 'block.json' || $entry === 'block.tpl' ) {

					$read = self::_file( $path. '/'. $entry, $entry === 'block.json' ? self::MAX_JSON : self::MAX_TEMPLATE );

					if( $read === null )
						return $entry. ' is a link, no file, too long or cannot be read';

					$parts[$entry === 'block.json' ? 'json' : 'tpl'] = $read;
					$seen[$entry] = true;
				}
				else if( $entry === 'text' ) {

					if( is_dir( $path. '/text' ) === false || realpath( $path. '/text' ) !== $path. '/text' )
						return 'text is a link, or no folder';

					foreach( @scandir( $path. '/text' ) ?: [] as $file ) {

						if( $file === '.' || $file === '..' )
							continue;

						if( preg_match( self::CODE, $file ) === 1 )
							return 'text/ holds "'. self::_shown( $file ). '": a block is data and has no code';

						if( $file[0] === '.' )
							continue;

						if( preg_match( '/^([a-z]{2}_[A-Z]{2})\.json$/D', $file, $match ) !== 1 )
							return 'text/ holds "'. self::_shown( $file ). '": there are <xx_XX>.json files and nothing else';

						$read = self::_file( $path. '/text/'. $file, self::MAX_JSON );

						if( $read === null )
							return 'text/'. $file. ' is a link, no file, too long or cannot be read';

						$parts['texts'][$match[1]] = $read;
					}

					$seen['text'] = true;
				}
				else if( $entry === 'images' ) {

					if( is_dir( $path. '/images' ) === false || realpath( $path. '/images' ) !== $path. '/images' )
						return 'images is a link, or no folder';

					foreach( @scandir( $path. '/images' ) ?: [] as $file ) {

						if( $file === '.' || $file === '..' )
							continue;

						if( preg_match( self::CODE, $file ) === 1 )
							return 'images/ holds "'. self::_shown( $file ). '": a block is data and has no code';

						if( $file[0] === '.' )
							continue;

						$read = self::_file( $path. '/images/'. $file, self::MAX_PICTURE );

						if( $read === null )
							return 'images/'. self::_shown( $file ). ' is a link, no file, too long or cannot be read';

						$parts['pictures'][$file] = $read;
					}
				}
				else
					return 'the folder holds "'. self::_shown( $entry ). '": there are block.json, block.tpl, text/ and images/';
			}

			foreach( [ 'block.json', 'block.tpl', 'text' ] as $needed )
				if( isset( $seen[$needed] ) === false )
					return 'the folder has no '. $needed;

			return $parts;
		}

		/**
		 *	The text of a file that is a file of its own and not longer than it may be
		 *
		 *	@param		string		$file
		 *	@param		int				$limit				In bytes
		 *
		 *	@return 	string|null								null for a link, no file or a file that is longer
		 */
		private static function _file( string $file, int $limit ): ?string {

			if( is_file( $file ) === false || realpath( $file ) !== $file || filesize( $file ) > $limit )
				return null;

			$text = @file_get_contents( $file, false, null, 0, $limit + 1 );

			return is_string( $text ) === true && strlen( $text ) <= $limit ? $text : null;
		}

		/**
		 *	The size and the time of every file a block is read from, and the names of what each folder holds - so that a file that comes or goes is
		 *	seen - for every root, with the roots as they are. A file that is not there is 0
		 *
		 *	@param		array			$roots
		 *
		 *	@return 	array
		 */
		private static function _stamp( array $roots ): array {

			$stamp = [];

			foreach( $roots as $root ) {

				$stamp['root '. $root['origin']] = $root['dir'];
				$stamp['label '. $root['origin']] = $root['label'];

				foreach( self::_names( $root ) as $name ) {

					$own = @filemtime( $root['dir']. '/'. $name );
					$stamp[$root['dir']. '/'. $name] = $own === false ? 0 : $own;

					foreach( [ '', 'text/', 'images/' ] as $inner ) {

						$path = $root['dir']. '/'. $name. '/'. $inner;

						foreach( @scandir( $path ) ?: [] as $entry ) {

							if( $entry === '.' || $entry === '..' )
								continue;

							$time = @filemtime( $path. $entry );
							$stamp[$path. $entry] = $time === false ? 0 : $time + (int) @filesize( $path. $entry ) * 10000000000;
						}
					}
				}
			}

			return $stamp;
		}

		/**
		 *	What the file of the cache holds, if it is that: php the kernel wrote, of the shape it writes. Whatever the file prints is dropped
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null
		 */
		private static function _stored( array &$appData ): ?array {

			$path = \Nino\Filesystem::path( $appData, self::CACHE );

			if( $path === '' || is_file( $path ) === false )
				return null;

			ob_start();

			try {
				$value = ( static fn( string $path ): mixed => @include $path )( $path );
			}
			catch( \Throwable ) {
				$value = null;
			}
			finally {
				ob_end_clean();
			}

			if( is_array( $value ) === false || array_keys( $value ) !== [ 'kernel', 'version', 'stamp', 'blocks', 'rejected' ]
				|| is_string( $value['kernel'] ) === false || is_string( $value['version'] ) === false || is_array( $value['stamp'] ) === false
				|| is_array( $value['blocks'] ) === false || is_array( $value['rejected'] ) === false )
				return null;

			foreach( $value['blocks'] as $key => $block )
				if( is_string( $key ) === false || is_array( $block ) === false || isset( $block['source'], $block['texts'], $block['slots'], $block['pictures'], $block['alternatives'], $block['defaults'], $block['origin'], $block['label'], $block['category'], $block['rank'] ) === false )
					return null;

			return $value;
		}

		/**
		 *	block.json: the name and the description in each language, the category, the rank, the slots of pictures and the variants
		 *
		 *	@param		array			$meta					What the file holds
		 *	@param		array			$locales			The languages of the texts
		 *	@param		mixed			$objects			The same file read with objects: a list and a map are told apart there, which {"0": ...} is not in an array
		 *
		 *	@return 	array|string							[ 'name', 'description', 'category', 'rank', 'images', 'alternatives' ] or what is wrong
		 */
		private static function _meta( array $meta, array $locales, mixed $objects = null ): array|string {

			foreach( array_keys( $meta ) as $key )
				if( in_array( $key, [ 'name', 'description', 'category', 'rank', 'images', 'variants' ], true ) === false )
					return 'block.json: "'. self::_shown( (string) $key ). '" is no key of a block - there are name, description, category, rank, images, variants';

			$block = [];

			foreach( [ 'name' => self::MAX_NAME, 'description' => self::MAX_DESCRIPTION ] as $key => $limit ) {

				$map = $meta[$key] ?? null;

				if( is_array( $map ) === false || $map === [] )
					return 'block.json: "'. $key. '" is a map of language => text';

				$problem = self::_localized( $map, $locales, $limit );

				if( $problem !== null )
					return 'block.json, "'. $key. '": '. $problem;

				$block[$key] = $map;
			}

			if( is_string( $meta['category'] ?? null ) === false || in_array( $meta['category'], self::CATEGORIES, true ) === false )
				return 'block.json: "category" is one of '. implode( ', ', self::CATEGORIES );

			if( is_int( $meta['rank'] ?? null ) === false || $meta['rank'] < 0 || $meta['rank'] > 999 )
				return 'block.json: "rank" is a whole number from 0 to 999';

			$block['category'] = $meta['category'];
			$block['rank'] = $meta['rank'];
			$images = $meta['images'] ?? [];

			if( is_array( $images ) === false || count( $images ) > self::MAX_IMAGES )
				return 'block.json: "images" is a map of at most '. self::MAX_IMAGES. ' slots';

			$block['images'] = [];

			foreach( $images as $name => $slot ) {

				if( is_string( $name ) === false || preg_match( self::WORD, $name ) !== 1 || strlen( $name ) > 2 * \Nino\Components::NAME_LENGTH )
					return 'block.json, images: "'. self::_shown( (string) $name ). '" is no name of a slot';

				if( is_array( $slot ) === false || count( $slot ) !== 4 || array_diff( array_keys( $slot ), [ 'placeholder', 'width', 'height', 'label' ] ) !== [] )
					return 'block.json, images, "'. $name. '": a slot is a placeholder, a width, a height and a label';

				if( is_string( $slot['placeholder'] ) === false || isset( \Nino\Images::PLACEHOLDERS[$slot['placeholder']] ) === false )
					return 'block.json, images, "'. $name. '": the placeholder is one of the kernel\'s: '. implode( ', ', array_keys( \Nino\Images::PLACEHOLDERS ) );

				foreach( [ 'width', 'height' ] as $side )
					if( is_int( $slot[$side] ) === false || $slot[$side] < 16 || $slot[$side] > 4096 )
						return 'block.json, images, "'. $name. '": the '. $side. ' is a whole number from 16 to 4096';

				if( is_array( $slot['label'] ) === false )
					return 'block.json, images, "'. $name. '": the label is a map of language => text';

				$problem = self::_localized( $slot['label'], $locales, self::MAX_NAME );

				if( $problem !== null )
					return 'block.json, images, "'. $name. '", label: '. $problem;

				$block['images'][$name] = [ 'placeholder' => $slot['placeholder'], 'width' => $slot['width'], 'height' => $slot['height'], 'label' => $slot['label'] ];
			}

			$alternatives = self::_alternatives( array_key_exists( 'variants', $meta ) === true ? $meta['variants'] : [], $locales, array_key_exists( 'variants', $meta ) === true && is_object( $objects ) === true ? $objects->variants : new \stdClass() );

			if( is_string( $alternatives ) === true )
				return $alternatives;

			$block['alternatives'] = $alternatives;

			return $block;
		}

		/**
		 *	The variants of block.json as they are written: a name, a label and a set of places, each with attributes and their values. What the
		 *	template and the register say of them is for _places()
		 *
		 *	@param		mixed			$variants			What the file holds under "variants"
		 *	@param		array			$locales			The languages of the texts
		 *	@param		mixed			$shape				The same value read with objects: a name or a place may be 0, and {"0": ...} is a list as an array
		 *
		 *	@return 	array|string							name => [ 'label', 'set' ] in the order of the file, or what is wrong
		 */
		private static function _alternatives( mixed $variants, array $locales, mixed $shape ): array|string {

			if( is_array( $variants ) === false || ( $variants !== [] && is_object( $shape ) === false ) || count( $variants ) > self::MAX_VARIANTS )
				return 'block.json: "variants" is a map of at most '. self::MAX_VARIANTS. ' variants';

			$shapes = is_object( $shape ) === true ? (array) $shape : [];
			$found = [];

			foreach( $variants as $name => $variant ) {

				$name = (string) $name;
				$where = 'block.json, variants, "'. self::_shown( $name ). '": ';

				if( preg_match( self::WORD, $name ) !== 1 || strlen( $name ) > \Nino\Components::NAME_LENGTH )
					return 'block.json, variants: "'. self::_shown( $name ). '" is no name of a variant - words of lower case letters and digits, joined by hyphens';

				if( is_array( $variant ) === false || is_object( $shapes[$name] ?? null ) === false || count( $variant ) !== 2 || array_key_exists( 'label', $variant ) === false || array_key_exists( 'set', $variant ) === false )
					return $where. 'a variant is a label and a set';

				if( is_array( $variant['label'] ) === false || $variant['label'] === [] )
					return $where. 'the label is a map of language => text';

				$problem = self::_localized( $variant['label'], $locales, self::MAX_NAME );

				if( $problem !== null )
					return $where. 'label: '. $problem;

				if( is_array( $variant['set'] ) === false || ( $variant['set'] !== [] && is_object( $shapes[$name]->set ?? null ) === false ) )
					return $where. 'the set is a map of place => attributes';

				$places = (array) ( $shapes[$name]->set ?? new \stdClass() );
				$set = [];

				foreach( $variant['set'] as $place => $attributes ) {

					$place = (string) $place;

					if( preg_match( self::PLACE, $place ) !== 1 )
						return $where. '"'. self::_shown( $place ). '" is no place: "root", or the indices from the root down, such as "1.1"';

					if( is_array( $attributes ) === false || ( $attributes !== [] && is_object( $places[$place] ?? null ) === false ) )
						return $where. 'the place "'. $place. '" has a map of attribute => value';

					foreach( $attributes as $attribute => $value )
						if( preg_match( '/^[a-z][a-zA-Z0-9]*$/D', (string) $attribute ) !== 1 || strlen( (string) $attribute ) > \Nino\Components::NAME_LENGTH || is_string( $value ) === false
							|| $value === '' || strlen( $value ) > self::MAX_NAME || preg_match( '/^[A-Za-z0-9-][A-Za-z0-9 :-]*$/D', $value ) !== 1 )
							return $where. 'the place "'. $place. '": "'. self::_shown( (string) $attribute ). '" is no attribute with a value that is a plain word';

					$set[$place] = $attributes;
				}

				$found[$name] = [ 'label' => $variant['label'], 'set' => $set ];
			}

			return $found;
		}

		/**
		 *	A map of language => text that has the languages of the block and no other, and plain texts
		 *
		 *	@param		array			$map
		 *	@param		array			$locales			Sorted
		 *	@param		int				$limit				The longest text, in characters
		 *
		 *	@return 	string|null								What is wrong, null where nothing is
		 */
		private static function _localized( array $map, array $locales, int $limit ): ?string {

			$have = array_map( 'strval', array_keys( $map ) );
			sort( $have );

			if( $have !== $locales )
				return 'it has the languages of the texts - '. implode( ', ', $locales ). ' - and no other';

			foreach( $map as $text ) {

				$problem = self::_text( $text, $limit, false );

				if( $problem !== null )
					return $problem;
			}

			return null;
		}

		/**
		 *	What a text of a block may not be: no string, empty, long, with a control character - a line break is one only where the
		 *	text may have lines - a < or a >, the beginning of a textfill
		 *
		 *	@param		mixed			$text
		 *	@param		int				$limit				In characters
		 *	@param		bool			$lines				Where a line break may stand
		 *
		 *	@return 	string|null
		 */
		private static function _text( mixed $text, int $limit, bool $lines ): ?string {

			if( is_string( $text ) === false || trim( $text ) === '' )
				return 'a text is a string that is not empty';

			if( mb_strlen( $text ) > $limit )
				return 'a text has at most '. $limit. ' characters';

			if( preg_match( $lines === true ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $text ) === 1 )
				return 'a text has no control character'. ( $lines === true ? ' but a line break' : '' );

			if( str_contains( $text, '<' ) === true || str_contains( $text, '>' ) === true || str_contains( $text, '[[' ) === true )
				return 'a text has no <, no > and no [[: it is words, not markup and not a key of the project';

			return null;
		}

		/**
		 *	Whether a label of an origin is what a person may read: a text, or a map of language => text, each of at most MAX_NAME
		 *	characters, as the name of a block is
		 *
		 *	@param		mixed			$label
		 *
		 *	@return 	bool
		 */
		private static function _label( mixed $label ): bool {

			if( is_array( $label ) === true && $label === [] )
				return false;

			foreach( is_array( $label ) === true ? $label : [ '' => $label ] as $locale => $text )
				if( ( is_array( $label ) === true && ( is_string( $locale ) === false || preg_match( '/^[a-z]{2,3}_[A-Z]{2}$/D', $locale ) !== 1 ) ) || self::_text( $text, self::MAX_NAME, false ) !== null )
					return false;

			return true;
		}

		/**
		 *	A file of json that is an object: not deeper than DEPTH levels, no list that has something in it
		 *
		 *	@param		string		$raw
		 *	@param		string		$what					The name of the file, for what is said
		 *	@param		int				$depth				How many levels it may have
		 *
		 *	@return 	array|string
		 */
		private static function _json( string $raw, string $what, int $depth = self::DEPTH ): array|string {

			if( strlen( $raw ) > self::MAX_JSON || str_contains( $raw, "\0" ) === true )
				return $what. ' is longer than '. self::MAX_JSON. ' bytes, or holds a null byte';

			try {
				$value = json_decode( $raw, true, $depth, JSON_THROW_ON_ERROR );
			}
			catch( \JsonException ) {
				return $what. ' is no json of at most '. $depth. ' levels';
			}

			if( is_array( $value ) === false || ( $value !== [] && array_is_list( $value ) === true ) )
				return $what. ' is an object, a map of names';

			return $value;
		}

		/**
		 *	The template of a block: read, checked against the register and the rules of a block, and what it reads and calls worked out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source
		 *
		 *	@return 	array|string							[ 'wrap' => the root, 'id' => its id, 'calls' => name => true, 'variants' => name => words, 'slots' => the slots get() lists, 'tree' => the nodes ], or what is wrong
		 */
		private static function _template( array &$appData, string $source ): array|string {

			if( $source === '' || trim( $source ) === '' )
				return 'block.tpl is empty';

			if( strlen( $source ) > self::MAX_TEMPLATE )
				return 'block.tpl is longer than '. self::MAX_TEMPLATE. ' bytes';

			if( str_contains( $source, "\0" ) === true || preg_match( '//u', $source ) !== 1 )
				return 'block.tpl holds a null byte, or is not utf-8';

			if( str_contains( $source, '[[' ) === true )
				return 'block.tpl holds "[[", the beginning of a textfill: a block reads no key of the project\'s';

			$containers = \Nino\Components::containers( $appData );
			$notes = [];
			$nodes = \Nino\Markup::parse( $source, $containers, $notes );

			if( $notes !== [] )
				return 'block.tpl is not read as html+ without a note: '. $notes[0]['code']. ( $notes[0]['name'] === '' ? '' : ' of "'. self::_shown( $notes[0]['name'] ). '"' ). ' at '. $notes[0]['offset'];

			if( \Nino\Markup::write( $nodes, $containers ) !== $source )
				return 'block.tpl is not written back byte for byte by what reads it';

			// One call, of a root, and nothing but blank text beside it
			$root = null;

			foreach( $nodes as $node )
				if( isset( $node['n'] ) === true ) {

					if( $root !== null )
						return 'block.tpl has more than one call at its top: a block has one root';

					$root = $node;
				}
				else if( trim( (string) ( $node['t'] ?? 'x' ) ) !== '' )
					return 'block.tpl has something beside its root that is no blank text';

			if( $root === null || in_array( $root['n'], self::ROOTS, true ) === false )
				return 'the root of block.tpl is one of '. implode( ', ', self::ROOTS );

			$id = $root['a']['id'] ?? null;

			if( is_string( $id ) === false || preg_match( self::ID, $id ) !== 1 || strlen( $id ) > self::ID_LENGTH )
				return 'the root has an id of at most '. self::ID_LENGTH. ' lower case letters, digits and hyphens';

			// A name nobody registered that a feature that is off brings is a block that needs that feature; any other finding is a fault
			$absent = self::_absent( $appData );
			$missing = [];

			foreach( \Nino\Components::check( $appData, $nodes ) as $position => $findings )
				foreach( $findings as $finding ) {

					if( $finding['code'] === \Nino\Components::CODE_UNKNOWN && isset( $absent[$finding['detail']] ) === true ) {

						$missing[$absent[$finding['detail']]] = true;
						continue;
					}

					if( $finding['code'] !== \Nino\Components::CODE_VARIANT )
						return 'block.tpl, at '. $position. ': '. $finding['code']. ( $finding['detail'] === '' ? '' : ' ('. self::_shown( (string) $finding['detail'] ). ')' ). ' - against the rules of the register';
				}

			$registry = \Nino\Components::components( $appData );
			$calls = [];
			$variants = [];
			$problem = self::_calls( $registry, $nodes, $id, true, null, $calls, $variants );

			if( $problem !== null )
				return 'block.tpl: '. $problem;

			// The sources the register reads: the nearest id above the call makes the scope of a name
			$slots = [];
			$prefix = '/template/'. self::TEMPLATE. '/';

			foreach( \Nino\Components::sources( $appData, $source, self::TEMPLATE ) as $found ) {

				if( $found['written'][0] !== '~' || str_starts_with( $found['source'], $prefix ) === false )
					continue;

				$rest = substr( $found['source'], strlen( $prefix ) );
				$cut = (int) strrpos( $rest, '/' );
				$scope = substr( $rest, 0, $cut );
				$name = substr( $rest, $cut + 1 );

				if( $scope !== $id && str_starts_with( $scope, $id. '-' ) === false )
					return 'a source '. $found['written']. ' is read below "'. $scope. '", which is no id of the block';

				$inner = $scope === $id ? '' : substr( $scope, strlen( $id ) + 1 );
				$flat = $inner === '' ? $name : $inner. '-'. $name;

				if( strlen( $flat ) > 2 * \Nino\Components::NAME_LENGTH )
					return 'the name of the source '. $found['written']. ' is longer than '. 2 * \Nino\Components::NAME_LENGTH. ' characters';

				$slots[$flat. '|'. $found['kind']] = [ 'name' => $flat, 'scope' => $inner, 'source' => $found['written'], 'kind' => $found['kind'], 'component' => $found['name'] ];
			}

			ksort( $variants );

			ksort( $missing );

			return [ 'wrap' => $root['n'], 'id' => $id, 'calls' => $calls, 'missing' => array_keys( $missing ), 'variants' => $variants, 'slots' => array_values( $slots ), 'tree' => $nodes ];
		}

		/**
		 *	The calls of a tree against the rules of what a block may read: the ids, the sources, the attribute values that look like sources
		 *
		 *	@param		array			$registry
		 *	@param		array			$nodes
		 *	@param		string		$root					The id of the root of the block
		 *	@param		bool			$top					The nodes are those of the template, whose first call is the root itself
		 *	@param		string|null	$type					The type of elements of the loop the nodes stand in, null outside of one
		 *	@param		array			&$calls				(reference) name => true
		 *	@param		array			&$variants		(reference) name => [ the words of its variants but regular ]
		 *
		 *	@return 	string|null								What is wrong, null where nothing is
		 */
		private static function _calls( array $registry, array $nodes, string $root, bool $top, ?string $type, array &$calls, array &$variants ): ?string {

			foreach( $nodes as $node ) {

				// Words between the calls are no text of the block: it has its texts in text/
				if( isset( $node['n'] ) === false && trim( (string) ( $node['t'] ?? 'x' ) ) !== '' )
					return 'there are words between the calls: a text is a source of a call, ~name';

				if( isset( $node['n'] ) === false || is_string( $node['n'] ) === false )
					continue;

				$name = $node['n'];
				$schema = $registry[$name] ?? [ 'attributes' => [] ];
				$calls[$name] = true;

				// Rule B6: the ids inside a block begin with the id of its root
				foreach( (array) $schema['attributes'] as $attribute => $declared )
					if( $declared['type'] === 'id' && isset( $node['a'][$attribute] ) === true ) {

						$id = $node['a'][$attribute];

						if( is_string( $id ) === false || preg_match( self::ID, $id ) !== 1 || strlen( $id ) > self::ID_LENGTH )
							return 'the id of "'. self::_shown( $name ). '" is at most '. self::ID_LENGTH. ' lower case letters, digits and hyphens';

						if( ( $top === true && $id !== $root ) || ( $top === false && ( str_starts_with( $id, $root. '-' ) === false || strlen( $id ) === strlen( $root ) + 1 ) ) )
							return 'the id "'. $id. '" of "'. self::_shown( $name ). '" does not begin with "'. $root. '-", the id of the root';
					}

				$top = false;

				if( is_string( $node['a']['variant'] ?? null ) === true && $node['a']['variant'] !== 'regular' ) {

					if( preg_match( self::WORD, $node['a']['variant'] ) !== 1 || strlen( $node['a']['variant'] ) > \Nino\Components::NAME_LENGTH )
						return 'the variant of "'. self::_shown( $name ). '" is a word of lower case letters, digits and hyphens';

					$variants[$name] = array_values( array_unique( array_merge( $variants[$name] ?? [], [ $node['a']['variant'] ] ) ) );
					sort( $variants[$name] );
				}

				$loop = is_string( $node['s'] ?? null ) === true && isset( \Nino\Elements::PLACEHOLDERS[$node['s']] ) === true;
				$inside = $loop === true ? $node['s'] : $type;
				$source = is_string( $node['s'] ?? null ) === true ? $node['s'] : '';

				if( $source !== '' && $loop === false && self::_reads( $source, true, $type ) === false )
					return 'a source of "'. self::_shown( $name ). '" - '. self::_shown( $source ). ' - is neither ~name, a placeholder of the kernel nor a field of a placeholder type of elements';

				foreach( (array) ( $node['a'] ?? [] ) as $attribute => $value )
					if( is_string( $value ) === true && $value !== '' && strpos( '~/.', $value[0] ) !== false
						&& self::_reads( $value, in_array( $schema['attributes'][$attribute]['type'] ?? '', [ 'text', 'image', 'link' ], true ), $type ) === false )
						return 'the value of "'. self::_shown( (string) $attribute ). '" of "'. self::_shown( $name ). '" begins like a source and is neither ~name where that is read, a placeholder of the kernel nor a field of a placeholder type of elements';

				// An address that is written into the block is one a link is drawn for: no scheme that runs a script, no other host by
				// two slashes. The drawing drops such a target; a block that carries one is no block
				foreach( (array) ( $node['a'] ?? [] ) as $attribute => $value )
					if( ( $schema['attributes'][$attribute]['type'] ?? '' ) === 'link' && is_string( $value ) === true && $value !== '' && $value[0] !== '~'
						&& \Nino\Components::address( $value ) === false )
						return 'the address in "'. self::_shown( (string) $attribute ). '" of "'. self::_shown( $name ). '" is none a link is drawn for';

				if( is_array( $node['c'] ?? null ) === true ) {

					$problem = self::_calls( $registry, $node['c'], $root, false, $inside, $calls, $variants );

					if( $problem !== null )
						return $problem;
				}
			}

			return null;
		}

		/**
		 *	The variants of a block as the functions read them: what is not a name with places of attributes that have a string for a value is not
		 *	kept, so that nothing a hand-built block holds can throw
		 *
		 *	@param		mixed			$alternatives	What 'alternatives' holds
		 *
		 *	@return 	array										name => place => attribute => value
		 */
		private static function _sets( mixed $alternatives ): array {

			$sets = [];

			foreach( is_array( $alternatives ) === true ? $alternatives : [] as $name => $variant ) {

				if( is_array( $variant ) === false || is_array( $variant['set'] ?? null ) === false )
					continue;

				$sets[$name] = [];

				foreach( $variant['set'] as $place => $attributes )
					foreach( is_array( $attributes ) === true ? $attributes : [] as $attribute => $value )
						if( is_string( $value ) === true )
							$sets[$name][$place][$attribute] = $value;
			}

			return $sets;
		}

		/**
		 *	What the variants of a block set, by place: place => attribute => true, the places in the order they are first named
		 *
		 *	@param		array			$sets					As _sets() answers
		 *
		 *	@return 	array
		 */
		private static function _touched( array $sets ): array {

			$touched = [];

			foreach( $sets as $variant )
				foreach( $variant as $place => $attributes )
					foreach( array_keys( $attributes ) as $attribute )
						$touched[$place][$attribute] = true;

			return $touched;
		}

		/**
		 *	The index of the root of a tree: its first call
		 *
		 *	@param		array			$tree
		 *
		 *	@return 	int|null
		 */
		private static function _root( array $tree ): ?int {

			foreach( $tree as $at => $node )
				if( is_array( $node ) === true && is_string( $node['n'] ?? null ) === true && is_array( $node['a'] ?? [] ) === true )
					return (int) $at;

			return null;
		}

		/**
		 *	The root of the template of a block in the form it is put into a page in: set out by \Nino\Markup::layout(), the first call alone in a list
		 *
		 *	@param		mixed			$tree					The 'tree' of a block
		 *
		 *	@return 	array										[ the root ], or [] where there is none
		 */
		private static function _laid( mixed $tree ): array {

			$root = is_array( $tree ) === true ? self::_root( $tree ) : null;

			return $root === null ? [] : \Nino\Markup::layout( [ $tree[$root] ], 0 );
		}

		/**
		 *	The way to a place in a tree: the index of the root among the nodes, then the indices below it. A place is 'root' or the dotted indices
		 *	from the root down, the blanks counted, and it names a call
		 *
		 *	@param		array			$tree
		 *	@param		string		$place
		 *
		 *	@return 	array|null								null where the tree has no call at the place
		 */
		private static function _steps( array $tree, string $place ): ?array {

			$root = preg_match( self::PLACE, $place ) === 1 ? self::_root( $tree ) : null;

			if( $root === null )
				return null;

			$steps = [ $root ];
			$node = $tree[$root];

			foreach( $place === 'root' ? [] : explode( '.', $place ) as $index ) {

				$node = is_array( $node['c'] ?? null ) === true ? ( $node['c'][(int) $index] ?? null ) : null;

				if( is_array( $node ) === false || is_string( $node['n'] ?? null ) === false || is_array( $node['a'] ?? [] ) === false )
					return null;

				$steps[] = (int) $index;
			}

			return $steps;
		}

		/**
		 *	The ways to a place in a tree and in the template, where the place is the same in both: the call there has the name the template has, and
		 *	on the way down each level has the same run of calls and blanks, by the name of each call
		 *
		 *	@param		array			$tree
		 *	@param		array			$laid					As _laid() answers
		 *	@param		string		$place
		 *
		 *	@return 	array|null								[ the way in the tree, the way in the template ]
		 */
		private static function _fit( array $tree, array $laid, string $place ): ?array {

			$a = self::_steps( $tree, $place );
			$b = self::_steps( $laid, $place );

			if( $a === null || $b === null )
				return null;

			$x = $tree[$a[0]];
			$y = $laid[$b[0]];

			if( $x['n'] !== $y['n'] )
				return null;

			$kinds = static fn( array $nodes ): array => array_map( static fn( mixed $node ): string => is_array( $node ) === true && is_string( $node['n'] ?? null ) === true ? $node['n'] : '', array_values( $nodes ) );

			foreach( array_slice( $a, 1 ) as $step ) {

				if( $kinds( $x['c'] ) !== $kinds( $y['c'] ) )
					return null;

				$x = $x['c'][$step];
				$y = $y['c'][$step];
			}

			return [ $a, $b ];
		}

		/**
		 *	The node at the end of a way that _steps() found, with an empty list of attributes where it has none
		 *
		 *	@param		array			$tree
		 *	@param		array			$steps
		 *
		 *	@return 	array
		 */
		private static function _node( array $tree, array $steps ): array {

			$node = $tree[$steps[0]];

			foreach( array_slice( $steps, 1 ) as $step )
				$node = $node['c'][$step];

			return $node + [ 'a' => [] ];
		}

		/**
		 *	A value of an attribute as the register reads it, to be compared: what is not written is the default of the schema, a switch is on or off
		 *
		 *	@param		mixed			$raw					The value as a call has it, null where it is not written
		 *	@param		array|null	$meta					[ the type, the default ] of the attribute, null where the block does not say
		 *
		 *	@return 	string|bool|null
		 */
		private static function _value( mixed $raw, ?array $meta ): string|bool|null {

			if( $meta === null )
				return is_string( $raw ) === true || is_bool( $raw ) === true ? $raw : null;

			if( $raw === null )
				$raw = $meta[1];

			if( $meta[0] === 'bool' )
				return $raw === true || ( is_string( $raw ) === true && in_array( strtolower( $raw ), \Nino\Components::ON, true ) === true );

			return is_string( $raw ) === true ? $raw : ( $raw === false || $raw === null ? '' : (string) $raw );
		}

		/**
		 *	What the template and the register say of the places of the variants: each place is a call of the template as it is put into a page, its
		 *	attribute is one of the schema of that component that is a look - a choice or a switch that is written as a class of the
		 *	component, so that it is how the component stands and nothing it says - and the tree with the set of the variant in it is read by the
		 *	register as the tree of the template is: the same findings and no other, so that no value comes in that a stored tree would not take
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$found				What _template() answers
		 *	@param		array			$alternatives	What _alternatives() answers
		 *
		 *	@return 	array|string							place => attribute => [ type, default ] of what the variants set, or what is wrong
		 */
		private static function _places( array &$appData, array $found, array $alternatives ): array|string {

			$defaults = [];

			if( $alternatives === [] )
				return $defaults;

			$tree = self::_laid( $found['tree'] );
			$registry = \Nino\Components::components( $appData );
			$absent = self::_absent( $appData );
			$before = [];

			foreach( \Nino\Components::check( $appData, $tree ) as $position => $findings )
				foreach( $findings as $finding )
					$before[$position. '|'. $finding['code']. '|'. $finding['detail']] = true;

			foreach( $alternatives as $name => $variant ) {

				$where = 'block.json, variants, "'. $name. '": ';
				$changed = $tree;

				foreach( $variant['set'] as $place => $attributes ) {

					$place = (string) $place;
					$steps = self::_steps( $tree, $place );

					if( $steps === null )
						return $where. 'the place "'. $place. '" is no call of block.tpl, as it is put into a page';

					$component = (string) self::_node( $tree, $steps )['n'];
					$schema = $registry[$component] ?? null;

					// What a feature that is off brings has no schema to hold a value against: the block is one that needs the feature
					if( $schema === null && isset( $absent[$component] ) === true )
						continue;

					$node = &$changed[$steps[0]];

					foreach( array_slice( $steps, 1 ) as $step )
						$node = &$node['c'][$step];

					foreach( $attributes as $attribute => $value ) {

						$declared = $schema['attributes'][$attribute] ?? null;

						if( $declared === null )
							return $where. '"'. self::_shown( (string) $attribute ). '" is no attribute of "'. $component. '" at "'. $place. '"';

						if( $declared['class'] !== true )
							return $where. '"'. $attribute. '" of "'. $component. '" at "'. $place. '" is no look: a variant sets an attribute that is a class of the component, and says nothing';

						$node['a'][$attribute] = $value;
						$defaults[$place][$attribute] = [ $declared['type'], $declared['default'] ];
					}

					unset( $node );
				}

				foreach( \Nino\Components::check( $appData, $changed ) as $position => $findings )
					foreach( $findings as $finding )
						if( isset( $before[$position. '|'. $finding['code']. '|'. $finding['detail']] ) === false )
							return $where. 'the register finds "'. $finding['code']. '" ('. self::_shown( (string) $finding['detail'] ). ') at '. $position. ' of the tree with the set: a value of the set is none the attribute takes';
			}

			return $defaults;
		}

		/**
		 *	Whether a source or a value that is written like one is something a block may read
		 *
		 *	@param		string			$written
		 *	@param		bool				$named				~name may stand where it is
		 *	@param		string|null	$type					The type of the loop the call is in
		 *
		 *	@return 	bool
		 */
		private static function _reads( string $written, bool $named, ?string $type ): bool {

			if( $written[0] === '~' )
				return $named === true && strlen( $written ) <= 1 + 2 * \Nino\Components::NAME_LENGTH && preg_match( self::SOURCE, $written ) === 1;

			if( isset( \Nino\Images::PLACEHOLDERS[$written] ) === true )
				return true;

			return $type !== null && $written[0] === '.' && isset( \Nino\Elements::PLACEHOLDERS[$type]['model'][substr( $written, 1 )] ) === true;
		}

		/**
		 *	Say what a request found wrong with a block, once for the request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$what					Which thing it is about
		 *	@param		string		$message
		 *
		 *	@return 	void
		 */
		private static function _said( array &$appData, string $what, string $message ): void {

			if( isset( $appData[self::SAID][$what] ) === true )
				return;

			$appData[self::SAID][$what] = true;

			trigger_error( $message, E_USER_WARNING );
		}

		/**
		 *	What a message may say of a name it was found under: printable characters and not many
		 *
		 *	@param		string		$name
		 *
		 *	@return 	string
		 */
		private static function _shown( string $name ): string {

			return (string) preg_replace( '/[^\x20-\x7E]/', '?', substr( $name, 0, 60 ) ). ( strlen( $name ) > 60 ? '...' : '' );
		}
	}

}
