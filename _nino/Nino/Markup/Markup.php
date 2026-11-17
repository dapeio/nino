<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Markup							HTML+ read into a tree and a tree written as HTML+
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Markup						The notation of a template: text, textfills and calls of
	 *										components, read in one pass into a tree and written back
	 *										from one. Pure functions - no $appData, no file, no state
	 *										between two calls - and neither of them ever throws: what
	 *										the notation cannot say is text, or a note.
	 *
	 *										A node is one of
	 *
	 *											[ 't' => 'text' ]
	 *											[ 'f' => '/project/company/general/name' ]
	 *											[ 'n' => 'card', 's' => '~title', 'a' => [ 'fill' => true, 'size' => '100 m:50' ], 'c' => null|[ nodes ], 'o' => 12 ]
	 *
	 *										'f' is a textfill without its brackets, 's' the source of a
	 *										call ('' for none), 'c' null for a leaf and [] for a container
	 *										with nothing in it, 'o' the byte offset of the call's opening
	 *										mark in the source. Text that follows text is one node, and a
	 *										node of empty text does not exist.
	 *
	 *										The marks are found with one preg_match_all() over the whole
	 *										document and the attributes of a call with a second one per
	 *										mark; there is no reader that walks the characters. The
	 *										patterns have no quantifier that gives back what it took
	 *										(possessive quantifiers throughout, no group that repeats a
	 *										group that repeats), so the time they need is linear in the
	 *										size of the document whatever the document is. Where PCRE
	 *										gives up all the same, the document is one text node and the
	 *										note says 'pattern'; so it is where it holds more than MAX_MARKS
	 *										brackets or more than MAX_WORDS_READ words in its calls (note
	 *										'limit'). A call has at most MAX_WORDS words, and a read keeps
	 *										MAX_NOTES notes (then the last one says 'limit'): the memory
	 *										that a document costs is bounded by these four.
	 *
	 *										What the notation reads, by example:
	 *
	 *											[name]  [name ~key]  [name /a/b/c/d]  [name .field]	a call; the source is the first word
	 *																											and begins with ~ / or . - one that stands
	 *																											later is a switch of that name
	 *											[name attr="value" switch]										a value of a name in double quotes, a switch
	 *																											is a bare word and true
	 *											[name /]																	a leaf, whatever the name is
	 *											[/name]																		the end of the next open call of that name
	 *											[[/project/company/general/name]]					a textfill, read before the calls
	 *											\[name]																		the text [name]
	 *
	 *										A value ends at the next " that no \ masks; \" is " and \\ is \,
	 *										any other \ stays what it is, and so does [ ] ' and a tab. A value
	 *										has no line break, and neither has a mark: one that has a line break
	 *										in it is text, whatever stands in it. A mark that is not
	 *										a call - a name that is none, a word that is no attribute, a value
	 *										that is never closed - is text, and so is a [[ ]] with anything
	 *										but the characters of a key between its brackets.
	 *
	 *										Every call is a container, a leaf or - where the caller does not
	 *										know the name - a container exactly if its end stands somewhere
	 *										in the document (see parse()).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Markup {

		// How many calls may be open at once: a container that would be the next is not opened (see parse())
		public const int MAX_DEPTH = 16;

		// How many [ a document may hold and still be read: the matches of a document of marks cost memory by their
		// number (see parse()), so a document with more is one text node and the note 'limit'. A template of 600
		// calls has 1300
		public const int MAX_MARKS = 50000;

		// How many words a call may have - its source, its attributes and its switches together: a mark with more is text.
		// A template has 3 to 8; the limit is in the pattern, so a mark of 200000 words is never taken apart
		public const int MAX_WORDS = 64;

		// How many words the calls of one document may have together, counted as they are kept in the tree: every word is
		// an entry of a node, so a document with more is one text node and the note 'limit', as with MAX_MARKS. 50000
		// calls of 64 words each stay under both other limits and take 300 MB; at this limit a read stays under 60 MB
		public const int MAX_WORDS_READ = 200000;

		// How many notes parse() keeps. The ones after are not kept: the last note is 'limit', with the offset of the
		// first that was not
		public const int MAX_NOTES = 1000;

		// The widths a responsive value names behind its first one: [ 'm' => from 768 px, 'l' => from 1024 px ]
		public const array BREAKPOINTS = [ 'm' => 768, 'l' => 1024 ];

		// A blank inside a mark: a space or a tab, never a line break - a mark stands in one line, as its value does.
		// Spelled out: \s follows the locale of the process
		private const string WS = '[ \t]';

		// The name of a call: [card], [section-col]
		private const string NAME = '[a-z][a-z0-9-]*+';

		// One character of a key: /project/company/general/name, .id, imageAlt, content:card-image.
		// Counted over the templates, texts, docs and code of the kernel: -./0-9_a-z, the capitals of lowerCamel field
		// names (imageAlt, linkLabel) and the colon of [[content:name]], which the templates of a component use
		// for the children of one name. Nothing else stands in a key anywhere, so nothing else is one
		private const string KEY_CHAR = '[A-Za-z0-9_./:-]';

		// The key of a textfill: its characters and, in one place, a textfill of its own - the one level the templates
		// of the kernel use ([[/_nino/webpage[[/nino/http/response/uri]]/title]]), written in the key as it stands.
		// At least one character stands outside the inner one, so that [[[[x]]]] is [[, the textfill x and ]]
		private const string KEY = '(?:'. self::KEY_CHAR. '++\[\['. self::KEY_CHAR. '++\]\]'. self::KEY_CHAR. '*+|\[\['. self::KEY_CHAR. '++\]\]'. self::KEY_CHAR. '++|'. self::KEY_CHAR. '++)';

		// A word of a call - the name of an attribute, or a switch: the form of the name of a field of an element, since
		// the field a component of a cell reads is written as the first word of its call ([text long_name]). The register
		// takes fewer names for its attributes (Components::validate(): [a-z][a-zA-Z0-9]*)
		private const string ATTRIBUTE = '[A-Za-z][A-Za-z0-9_-]*+';

		// The source of a call, and a switch that is written like one: ~title, /a/b/c/d, .field, /templates/page-x.[[/nino/http/response/locale]].
		// A key, the ~ of a relative one, and textfills, which a mark takes whole: their ]] is not the end of the mark.
		// At least one character behind the first, so that the / of ' /]' is none
		private const string SOURCE = '[~./](?:[A-Za-z0-9_./:~-]++|\[\['. self::KEY_CHAR. '++\]\])++';

		// What stands between two double quotes: no line break, \" and \\ and any other \x as they are, no quote that is not masked.
		// Written as a loop that unrolls (normal*+ (special normal*+)*+), the one shape that needs no memory per character
		private const string VALUE = '[^"\\\\\n\r]*+(?:\\\\[^\n\r][^"\\\\\n\r]*+)*+';

		// A value without quotes (size=50, size='50'): read to be dropped, and noted
		private const string BARE = '[^ \t\r\n"\[\]]*+';

		// One mark, without the \ in front of it, and with no group: the whole mark is all that is kept of a match, which
		// is what a document of many marks costs least memory with. By its first characters it is a textfill ([[ and a key),
		// an end ([/ and a name) or a call ([ and a name, then its words, then ' /' or not, then ]); the name is read
		// from the match with strspn(), the words with WORDS. The textfill comes first, the call needs a letter after its [
		private const string MARK_BODY = '\[(?:\['. self::KEY. '\]\]|/'. self::NAME. self::WS. '*+\]'
			. '|'. self::NAME. '(?:'. self::WS. '++(?:'. self::SOURCE. '|'. self::ATTRIBUTE. '(?:=(?:"'. self::VALUE. '"|'. self::BARE. '))?)){0,'. self::MAX_WORDS. '}+'
			. '(?:'. self::WS. '++/)?+'. self::WS. '*+\])';

		// A mark in a document: a \ in front of it makes it text (the match starts with the \ then)
		private const string MARK = '#\\\\?+'. self::MARK_BODY. '#';

		// A mark in a text: what write() puts a \ in front of
		private const string MARK_IN_TEXT = '#'. self::MARK_BODY. '#';

		// The characters of a name, for strspn()
		private const string NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

		// The words of one call, one at a time: 1 a source or a switch written like one, 2 the name of an attribute,
		// 3 its value in quotes (null where there is none), 4 its value without quotes (null where there is none)
		private const string WORDS = '#'. self::WS. '++(?:('. self::SOURCE. ')|('. self::ATTRIBUTE. ')(?:=(?:"('. self::VALUE. ')"|('. self::BARE. ')))?)#';

		/**
		 *	The tree of a document
		 *
		 *	$containers says what each name is: true for a container, which needs an end, false for a
		 *	leaf, which has none - a [/name] after a leaf is a stray end. A name that is not in it
		 *	is a container exactly if [/name] stands somewhere in the document (one that a \ makes
		 *	text does not count). [name ... /] is a leaf whatever the name is.
		 *
		 *	An end closes the next open call of its name, and the open calls between are closed
		 *	with it (each one 'unclosed'). An end with no open call of its name is skipped
		 *	('stray-end'), and what is still open at the end of the document is closed
		 *	('unclosed'). Calls of one name nest.
		 *
		 *	A container is open while it has not ended, and at most MAX_DEPTH containers are open at
		 *	once. The container that would be the next stays a leaf ('depth'), what it would have
		 *	held stands behind it as its siblings, and its end is skipped without a note. The leaves
		 *	that stand in the innermost of the sixteen are not counted: they open nothing.
		 *
		 *	A word of a call that is the name of an attribute with a value that has no quotes
		 *	(size=50) is dropped, and so is the second of the same name, whose value takes the place
		 *	of the first one's: the call has the one note 'attribute', however many words that is. The
		 *	source is the first word that is kept: behind a dropped word a word that looks like a source
		 *	is one, behind a word that is kept it is a switch.
		 *
		 *	A call of more than MAX_WORDS words (the source, the attributes and the switches together) is
		 *	no call: it is text, and the mark has no note. A document with more than MAX_MARKS brackets,
		 *	or with more than MAX_WORDS_READ words in its calls together, is not read: it is one text
		 *	node and the one note 'limit' with the offset 0 (and so is one that PCRE gives up on, with
		 *	the note 'pattern'). Of the notes of a read the first MAX_NOTES in the
		 *	order they are found are kept; the tree is the whole tree all the same, and the last note is
		 *	'limit' with the offset of the first one that was not kept - it stands last whatever that
		 *	offset is, so that the last note alone says whether the list is whole.
		 *
		 *	@param		string		$source				The document
		 *	@param		array 		$containers		name => true for a container, false for a leaf
		 *	@param		array 		&$notes				(reference) What was noticed, emptied first, in the order of the offsets:
		 *																	[ [ 'code' => 'unclosed'|'stray-end'|'depth'|'attribute'|'pattern'|'limit', 'name' => 'card', 'offset' => 123 ], ... ];
		 *																	the offset is the mark's own; 'pattern' and 'limit' (too many marks) have 0 and no name,
		 *																	'limit' (too many notes) the offset of the first note that was left out and is the last
		 *
		 *	@return 	array										The nodes of the document
		 */
		public static function parse( string $source, array $containers, array &$notes = [] ): array {

			$notes = [];
			$dropped = null;

			if( $source === '' )
				return [];

			if( strpos( $source, '[' ) === false )
				return [ [ 't' => $source ] ];

			// Every match is an array with the text and the offset, and holds memory until the loop is done: that is
			// what the number of marks costs, and a document of more than MAX_MARKS of them is not read
			if( substr_count( $source, '[' ) > self::MAX_MARKS ) {
				$notes[] = [ 'code' => 'limit', 'name' => '', 'offset' => 0 ];
				return [ [ 't' => $source ] ];
			}

			if( preg_match_all( self::MARK, $source, $marks, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE ) === false ) {
				$notes[] = [ 'code' => 'pattern', 'name' => '', 'offset' => 0 ];
				return [ [ 't' => $source ] ];
			}

			$marks		= $marks[0];
			$kept		= 0;
			$ends		= null;
			$list		= [];
			$text		= '';
			$at			= 0;

			// The calls that are open, innermost last: the list a call's parent has so far, its node (null for one
			// that stayed a leaf), its name and its offset. $byName: the places of one name in them
			$parents	= [];
			$nodes		= [];
			$names		= [];
			$starts		= [];
			$byName		= [];

			foreach( $marks as [ $whole, $begin ] ) {

				if( $begin > $at )
					$text .= substr( $source, $at, $begin - $at );

				$at = $begin + strlen( $whole );

				// \[ in front of a mark: the mark is text, and the \ is not
				if( $whole[0] === '\\' ) {
					$text .= substr( $whole, 1 );
					continue;
				}

				$kind = $whole[1];

				// [[key]]
				if( $kind === '[' ) {

					if( $text !== '' ) {
						$list[] = [ 't' => $text ];
						$text = '';
					}

					$list[] = [ 'f' => substr( $whole, 2, -2 ) ];
					continue;
				}

				$name = substr( $whole, $kind === '/' ? 2 : 1, strspn( $whole, self::NAME_CHARS, $kind === '/' ? 2 : 1 ) );

				if( $kind === '/' ) {

					if( isset( $byName[$name] ) === false || $byName[$name] === [] ) {
						self::_note( $notes, $dropped, 'stray-end', $name, $begin );
						continue;
					}

					$target = array_pop( $byName[$name] );

					for( $top = count( $names ) - 1; $top >= $target; $top-- ) {

						$node		= array_pop( $nodes );
						$closed	= array_pop( $names );
						$offset	= array_pop( $starts );

						if( $top > $target ) {
							self::_note( $notes, $dropped, 'unclosed', $closed, $offset );
							array_pop( $byName[$closed] );
						}

						// A call that stayed a leaf has no list: what is round its end stays one text
						if( $node !== null ) {

							if( $text !== '' ) {
								$list[] = [ 't' => $text ];
								$text = '';
							}

							$node['c']	= $list;
							$list				= array_pop( $parents );
							$list[]			= $node;
						}
					}

					continue;
				}

				$node = [ 'n' => $name, 's' => '', 'a' => [], 'c' => null, 'o' => $begin ];

				// What stands between the name and the ], the ' /' of a leaf with it
				$words = substr( $whole, 1 + strlen( $name ), -1 );

				[ $node['s'], $node['a'], $attributed ] = self::_words( $words );

				$kept += count( $node['a'] ) + ( $node['s'] === '' ? 0 : 1 );

				if( $kept > self::MAX_WORDS_READ ) {
					$notes = [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ];
					return [ [ 't' => $source ] ];
				}

				if( $attributed === true )
					self::_note( $notes, $dropped, 'attribute', $name, $begin );

				// ' /' at the end is a leaf: a / behind a blank is no word of any other kind (a source has a second character)
				$tail = rtrim( $words, " \t" );

				if( strlen( $tail ) > 1 && $tail[-1] === '/' && ( $tail[-2] === ' ' || $tail[-2] === "\t" ) )
					$container = false;
				else if( isset( $containers[$name] ) === true )
					$container = $containers[$name] === true;
				else {
					// The name is not known: a container if its end stands anywhere - found once, when the first name asks
					if( $ends === null ) {

						$ends = [];

						foreach( $marks as [ $other ] )
							if( $other[1] === '/' )
								$ends[ substr( $other, 2, strspn( $other, self::NAME_CHARS, 2 ) ) ] = true;
					}

					$container = isset( $ends[$name] );
				}

				if( $text !== '' ) {
					$list[] = [ 't' => $text ];
					$text = '';
				}

				if( $container === false ) {
					$list[] = $node;
					continue;
				}

				$byName[$name][]	= count( $names );
				$names[]					= $name;
				$starts[]					= $begin;

				// The seventeenth: a leaf that something is open behind, whose end is read and skipped
				if( count( $parents ) >= self::MAX_DEPTH ) {
					self::_note( $notes, $dropped, 'depth', $name, $begin );
					$list[]			= $node;
					$nodes[]		= null;
					continue;
				}

				$parents[]	= $list;
				$list				= [];
				$nodes[]		= $node;
			}

			if( $at < strlen( $source ) )
				$text .= substr( $source, $at );

			if( $text !== '' )
				$list[] = [ 't' => $text ];

			while( $names !== [] ) {

				$node = array_pop( $nodes );
				self::_note( $notes, $dropped, 'unclosed', array_pop( $names ), array_pop( $starts ) );

				if( $node !== null ) {
					$node['c']	= $list;
					$list				= array_pop( $parents );
					$list[]			= $node;
				}
			}

			// The notes of an end that closes calls come after the ones of the marks in between: by offset they are in the order of the document
			if( count( $notes ) > 1 ) {
				$offsets = array_column( $notes, 'offset' );
				array_multisort( $offsets, SORT_ASC, SORT_NUMERIC, range( 0, count( $notes ) - 1 ), SORT_ASC, SORT_NUMERIC, $notes );
			}

			if( $dropped !== null )
				$notes[] = [ 'code' => 'limit', 'name' => '', 'offset' => $dropped ];

			return $list;
		}

		/**
		 *	What a note is kept for: the first MAX_NOTES of a read, and the offset of the first that is not
		 *
		 *	@param		array 		&$notes				(reference) The notes kept
		 *	@param		int|null	&$dropped			(reference) The offset of the first note that was not kept, null while all are
		 *	@param		string		$code
		 *	@param		string		$name
		 *	@param		int				$offset
		 *
		 *	@return 	void
		 */
		private static function _note( array &$notes, ?int &$dropped, string $code, string $name, int $offset ): void {

			if( count( $notes ) < self::MAX_NOTES )
				$notes[] = [ 'code' => $code, 'name' => $name, 'offset' => $offset ];
			else if( $dropped === null )
				$dropped = $offset;
		}

		/**
		 *	The words of one call as its source and its attributes
		 *
		 *	@param		string		$raw					What stands between the name and the end of the mark, blank in front of each word
		 *
		 *	@return 	array										[ source, [ attribute => string|true ], whether a word was dropped or said twice ]
		 */
		private static function _words( string $raw ): array {

			$source		= '';
			$attributes	= [];
			$noted		= false;

			if( $raw === '' )
				return [ $source, $attributes, $noted ];

			preg_match_all( self::WORDS, $raw, $words, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL );

			$kept = 0;

			foreach( $words as $word ) {

				// A word written like a source is the source in front of all, if it is the first that is kept, and a switch behind another word
				if( $word[1] !== null ) {

					if( $kept === 0 ) {
						$source = $word[1];
						$kept++;
						continue;
					}

					$key		= $word[1];
					$quoted	= null;
					$bare		= null;
				}
				else {
					$key		= $word[2];
					$quoted	= $word[3];
					$bare		= $word[4];
				}

				// A value with no quotes is dropped
				if( $quoted === null && $bare !== null ) {
					$noted = true;
					continue;
				}

				$kept++;

				// The last one of a name counts, at the place of the first
				if( isset( $attributes[$key] ) === true )
					$noted = true;

				if( $quoted === null )
					$attributes[$key] = true;
				else
					$attributes[$key] = strpos( $quoted, '\\' ) === false ? $quoted : strtr( $quoted, [ '\\"' => '"', '\\\\' => '\\' ] );
			}

			return [ $source, $attributes, $noted ];
		}

		/**
		 *	A tree as a document, in the one form it has: [name source attr="value" switch]content[/name],
		 *	the source first, the attributes in the order they stand, a switch as a bare word, \ and "
		 *	in a value masked, and no blank that no text holds. A leaf is [name ... /] where the same
		 *	name is a container anywhere in the tree or in the list - a name the reader does not know is a
		 *	container only by the end it finds - and [name ...] where it is not. 'o' is not written.
		 *
		 *	What the notation cannot say is left out, and nothing is thrown: a node that is none, a name
		 *	that is no name, an attribute that is no attribute, a value with a line break or that is not
		 *	a string or true, a textfill with something else than the characters of a key, a source
		 *	that is no source, and, with all it holds, the node that has one. A text is written with a
		 *	\ in front of each place that would be read as a mark otherwise.
		 *
		 *	For a tree that parse() gave, parse() of what is written, with the list of containers the tree
		 *	was read with, gives the tree again (save 'o'), and writing that gives the same document - except
		 *	for two forms, which the notation cannot say:
		 *
		 *	- A text that ends in a mark that was started and not finished ([a x=", [[a[[b]]), with something behind
		 *		it that finishes the mark (a quote, a ]]), and between the two a skipped end ([/z], 'stray-end'): the text
		 *		was two texts and one text then, and its two halves stand side by side in the document written, where
		 *		they make a mark. A document that has no skipped end cannot do this: it reads the mark where it stands.
		 *	- A \ at the end of a text, in front of a mark, would mask the mark, and the notation has no word
		 *		for a \ that stands there. parse() gives such a text only where the document ends with the \
		 *		inside calls that were never closed: their ends are not written then, and the reader closes them
		 *		where the document ends, as it did - which it does for a name it knows, or one whose end stands
		 *		somewhere else. Anywhere else (a tree made by hand) every \ at the end of the text is left out: the mark
		 *		behind it is worth more than the character.
		 *
		 *	$containers is the list the tree was read with: a leaf of a name that it calls a container is
		 *	written [name ... /], as one of a name that is a container in the tree is.
		 *
		 *	@param		array 		$nodes				The nodes of a document
		 *	@param		array 		$containers		name => true for a container, as parse() takes it
		 *
		 *	@return 	string
		 */
		public static function write( array $nodes, array $containers = [] ): string {

			// A name that is a container, by the list or anywhere in the tree: its leaves need ' /]'
			$containers	= array_filter( $containers, static fn( mixed $container ): bool => $container === true );
			$walk				= [ $nodes ];

			while( $walk !== [] ) {

				foreach( array_pop( $walk ) as $node )
					if( is_array( $node ) === true && is_string( $node['n'] ?? null ) === true && is_array( $node['c'] ?? null ) === true ) {
						$containers[ $node['n'] ] = true;
						$walk[] = $node['c'];
					}
			}

			$out			= '';
			$text			= '';
			$ends			= '';
			$stack		= [];
			$list			= array_values( $nodes );
			$index		= 0;

			while( true ) {

				if( $index >= count( $list ) ) {

					if( $stack === [] )
						break;

					[ $list, $index, $close ] = array_pop( $stack );

					// Written when the next thing is: a \ that ends the document has no mark behind it
					$ends .= $close;
					continue;
				}

				$node = $list[$index++];

				if( is_array( $node ) === false )
					continue;

				if( isset( $node['t'] ) === true && is_string( $node['t'] ) === true && isset( $node['n'] ) === false && isset( $node['f'] ) === false ) {

					if( $ends !== '' )
						self::_settle( $out, $text, $ends, true );

					$text .= $node['t'];
					continue;
				}

				if( isset( $node['f'] ) === true ) {

					if( is_string( $node['f'] ) === false || preg_match( '#^'. self::KEY. '$#D', $node['f'] ) !== 1 )
						continue;

					self::_settle( $out, $text, $ends, true );
					$out .= '[['. $node['f']. ']]';
					continue;
				}

				$open = self::_open( $node, $containers );

				if( $open === null )
					continue;

				self::_settle( $out, $text, $ends, true );

				// A call that has nothing but its name, behind a [ : [[x]] would be a textfill
				if( $out !== '' && $out[-1] === '[' && $open[0] === '[' . $node['n'] . ']' )
					$open[0] = '['. $node['n']. ' ]';

				$out .= $open[0];

				if( $open[1] === null )
					continue;

				$stack[]	= [ $list, $index, $open[1] ];
				$list			= array_values( $node['c'] );
				$index		= 0;
			}

			// The end of the document. A \ that stands last, inside calls: those are not closed
			if( substr( $text, -1 ) === '\\' )
				$ends = '';

			self::_settle( $out, $text, $ends, false );

			return $out;
		}

		/**
		 *	A tree that is new - a block made by a tool - set out the way a person writes a template: each call on a line of its
		 *	own, a tab for each level, the end of a container at the level of its start. The blank texts between the calls of a
		 *	level are replaced by a line break and the tabs, and where a container holds calls and nothing else, one stands before
		 *	each and one before its end. A level that holds anything but calls and blank text - words, a textfill, html, a node that is
		 *	none - is inline content and stays as it is, with what it holds: a line break there would be part of the text.
		 *	A call that is a leaf stays a leaf. The first call of $nodes has no line break before it: whatever stands before it in the
		 *	document is the document's
		 *
		 *	What it answers is the tree: write() of it is the template as it would be written by hand, and parse() of that is the tree
		 *	again but for the blank texts. Nothing that was not given is touched - a tree that is read from a file and not given here
		 *	stays byte for byte as it was, which is what a tool lays out for what it makes and for nothing else
		 *
		 *	@param		array 		$nodes				The nodes of the new part, as parse() gives them
		 *	@param		int				$depth				How many tabs the first level stands behind, at most MAX_DEPTH
		 *
		 *	@return 	array
		 */
		public static function layout( array $nodes, int $depth ): array {
			return self::_lay( $nodes, max( 0, min( self::MAX_DEPTH, $depth ) ), false );
		}

		/**
		 *	One level of layout()
		 *
		 *	@param		array 		$nodes
		 *	@param		int				$depth				The level, 1 or more where $inside
		 *	@param		bool			$inside				The children of a call: a line break before each and before the end
		 *
		 *	@return 	array
		 */
		private static function _lay( array $nodes, int $depth, bool $inside ): array {

			$calls = 0;

			foreach( $nodes as $node ) {

				if( is_array( $node ) === true && isset( $node['n'] ) === true && is_string( $node['n'] ) === true && isset( $node['t'] ) === false && isset( $node['f'] ) === false )
					$calls++;
				else if( is_array( $node ) === false || isset( $node['n'] ) === true || isset( $node['f'] ) === true || is_string( $node['t'] ?? null ) === false || trim( $node['t'], " \t\r\n" ) !== '' )
					return $nodes;
			}

			if( $calls === 0 )
				return $nodes;

			$out = [];

			foreach( $nodes as $node ) {

				if( isset( $node['n'] ) === false )
					continue;

				if( $out !== [] || $inside === true )
					$out[] = [ 't' => "\n". str_repeat( "\t", $depth ) ];

				if( is_array( $node['c'] ?? null ) === true )
					$node['c'] = self::_lay( $node['c'], min( self::MAX_DEPTH, $depth + 1 ), true );

				$out[] = $node;
			}

			if( $inside === true )
				$out[] = [ 't' => "\n". str_repeat( "\t", $depth - 1 ) ];

			return $out;
		}

		/**
		 *	What waits in write() is written before whatever comes next: the text, then the ends of the calls that
		 *	closed after it. The text loses a \ at its end where a mark follows, as the doc of write() says. An end that
		 *	comes right behind a [ gets a blank inside: a [ and an end written [/x] would read as a textfill with the
		 *	key /x, and with the blank it is a [ and an end
		 *
		 *	@param		string		&$out					(reference) What is written so far
		 *	@param		string		&$text				(reference) The text that waits, emptied
		 *	@param		string		&$ends				(reference) The ends that wait, emptied
		 *	@param		bool			$more					Whether a mark follows
		 *
		 *	@return 	void
		 */
		private static function _settle( string &$out, string &$text, string &$ends, bool $more ): void {

			if( $more === true )
				$text = rtrim( $text, '\\' );

			$out .= self::_escape( $text );
			$text = '';

			if( $ends !== '' ) {

				if( $out !== '' && $out[-1] === '[' )
					$ends = substr_replace( $ends, ' ', (int) strpos( $ends, ']' ), 0 );

				$out .= $ends;
				$ends = '';
			}
		}

		/**
		 *	The opening mark of a call as it is written, or null for a call that cannot be
		 *
		 *	@param		array 		$node					A node that is not a text and not a textfill
		 *	@param		array 		$containers		The names that are containers somewhere in the tree: name => true
		 *
		 *	@return 	array|null							[ the mark, the end that closes it or null for a leaf ]
		 */
		private static function _open( array $node, array $containers ): ?array {

			$name			= $node['n'] ?? null;
			$source		= $node['s'] ?? '';
			$words		= $node['a'] ?? [];
			$children	= $node['c'] ?? null;

			if( is_string( $name ) === false || preg_match( '#^'. self::NAME. '$#D', $name ) !== 1 )
				return null;

			if( is_string( $source ) === false || is_array( $words ) === false || ( $children !== null && is_array( $children ) === false ) )
				return null;

			if( $source !== '' && preg_match( '#^'. self::SOURCE. '$#D', $source ) !== 1 )
				return null;

			if( count( $words ) + ( $source === '' ? 0 : 1 ) > self::MAX_WORDS )
				return null;

			$mark		= '['. $name. ( $source === '' ? '' : ' '. $source );
			$first	= $source === '';

			foreach( $words as $key => $value ) {

				if( is_string( $key ) === false )
					return null;

				if( $value === true ) {

					// A switch written like a source is one only behind another word: in front of all it would be the source
					if( preg_match( '#^'. self::ATTRIBUTE. '$#D', $key ) !== 1 && ( $first === true || preg_match( '#^'. self::SOURCE. '$#D', $key ) !== 1 ) )
						return null;

					$mark .= ' '. $key;
				}
				else if( is_string( $value ) === true && preg_match( '#^'. self::ATTRIBUTE. '$#D', $key ) === 1 && strpbrk( $value, "\n\r" ) === false )
					$mark .= ' '. $key. '="'. strtr( $value, [ '\\' => '\\\\', '"' => '\\"' ] ). '"';
				else
					return null;

				$first = false;
			}

			if( $children === null )
				return [ $mark. ( isset( $containers[$name] ) === true ? ' /]' : ']' ), null ];

			return [ $mark. ']', '[/'. $name. ']' ];
		}

		/**
		 *	A text with a \ in front of every place that would be read as a mark
		 *
		 *	@param		string		$text
		 *
		 *	@return 	string
		 */
		private static function _escape( string $text ): string {

			if( strpos( $text, '[' ) === false )
				return $text;

			// A pattern that gave up (the limits of the process are the only way it can) cannot say what is a mark: nothing is written
			return preg_replace( self::MARK_IN_TEXT, '\\\\$0', $text ) ?? '';
		}
	}
}
