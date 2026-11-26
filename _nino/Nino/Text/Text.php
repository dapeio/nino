<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Text					The [[key]] textfill layer the Text and Text Keys editors sit on top of
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Text - the [[key]] textfill layer the workbench's Text and Text Keys
	// editor both sit on top of: reads every key out of /text/global.php +
	// every /text/{locale}.php, batches a save into one lock/read/write per
	// file. Only holds what was byte-for-byte identical between the two
	// UIs - blacklist filtering, PERM- vs session-gated saves, shape
	// conversion stay in the panels' own files, Text/Admin/Admin.php and
	// Text/Keys/Keys.php.
	class Text {

		private const int MIN_MAXLENGTH 		= 150;
		private const int MAX_MAXLENGTH 		= 2000;
		private const int MAXLENGTH_BUFFER = 150;
		private const int HARD_MAXLENGTH 	= 20000;

		// How many times as long as its stored form a text may be sent to the editors' save: see saveBatch()
		private const int SANITIZED_REACH 	= 12;

		// Where a key's format and limit are kept, when someone has decided
		// them: ['/the/key' => ['format' => 'blocks', 'maxlength' => 400]].
		// Neither is required - a key without an entry takes its format from
		// what it holds and its limit from how long it is
		public const string META_PATH = '/text/meta.php';

		// The most an explicit limit may be: every character of it fits the
		// hard byte limit above, at four bytes a character
		public const int MAX_LIMIT = self::HARD_MAXLENGTH / 4;

		// The limit a key without a set one gets: its longest value in
		// bytes plus room to grow, never under the floor or over the cap.
		// The one formula - entries() and the Text Keys tab's list of
		// hidden keys with no value both ask it. Internal: an implementation
		// detail of entries() and the Text Keys tab, not part of the
		// documented Text API
		public static function maxlength( int $longest = 0 ): int {
			return min( self::MAX_MAXLENGTH, max( self::MIN_MAXLENGTH, $longest + self::MAXLENGTH_BUFFER ) );
		}

		// The form a text key a person creates or renames takes:
		// /<namespace>/<category>/<part>/<name>, the namespace one of a closed
		// list and every other segment lower-case words joined by hyphens.
		// What the system writes by itself - /_nino/... - and the workbench's
		// own words - /_admin/... - are not of this form and are never
		// created by hand
		private const string KEY_GRAMMAR = '#^/(?:template|project|feature|module)(?:/[a-z0-9]+(?:-[a-z0-9]+)*){3}$#D';

		/**
		 *	Every known key across global.php + every locale file - the files of
		 *	all the languages that have one (\Nino\Locales::getTextLocales()), so
		 *	a language that is translated before it is switched on is among them -
		 *	with its current value(s), whether it's global or per-locale, its format
		 *	(the one set in meta(), else the widest the values hold - 'html' is
		 *	whether that is more than plain text), a maxlength (the one set,
		 *	else derived from its longest current value), and whether it's
		 *	blacklisted (see blacklist()). 'formatSet' and 'maxlengthSet' say
		 *	whether the two come from meta() or from the values. A limit that is
		 *	set is never shorter than the longest text the key holds - a longer
		 *	one written later (an import, the wizard, a feature) is not cut by
		 *	the editor at its first keystroke.
		 *	$includeBlacklisted controls whether a blacklisted key is skipped
		 *	entirely or just flagged - the Text panel hides them, Text Keys
		 *	editor needs to see them to be able to un-blacklist one.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool		$includeBlacklisted	Whether a blacklisted key is in the list (flagged) or left out
		 *
		 *	@return 	array							One entry per key, sorted by key: { key, global, blacklisted, html, format, formatSet, maxlength, maxlengthSet, keyLimit, limits, empty, values, typed } - values is locale => text as it is stored, null for a language without one, or '*' => text for a global key. keyLimit is the limit of the key itself, the one set or the one derived from its longest text, where maxlength is lifted to the longest text any language holds. limits is the same map for the number of characters a text of that language may have (see saveBatch()): the limit of the key, or the length the language's text has where that is more. empty is the list of the languages that have no text for the key (see isEmpty()), [] for a key that is the same in every language. typed is the same map for the texts a person types differently from how they are stored (see typed()): only the locales whose typed form differs from the stored one are in it, and an entry with none has no 'typed' key
		 */
		public static function entries( array &$appData, bool $includeBlacklisted = true ): array {

			$global 	= \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
			$locales 	= \Nino\Locales::getTextLocales( $appData );

			$localeData = [];
			foreach( $locales as $locale )
				$localeData[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$blacklist = self::blacklist( $appData );
			$meta 		 = self::meta( $appData );

			$bracketKeys = array_keys( $global );
			foreach( $localeData as $data )
				$bracketKeys = array_merge( $bracketKeys, array_keys( $data ) );
			$bracketKeys = array_unique( $bracketKeys );

			$entries = [];

			foreach( $bracketKeys as $bracketKey ) {

				$key 					= trim( $bracketKey, '[]' );
				$isBlacklisted = isset( $blacklist[$key] ) === true;

				if( $isBlacklisted === true && $includeBlacklisted === false )
					continue;

				$isGlobal = array_key_exists( $bracketKey, $global );
				$values 	= $isGlobal
					? [ '*' => $global[$bracketKey] ]
					: array_map( fn( array $data ) => $data[$bracketKey] ?? null, $localeData );

				$longest 	= 0;
				$visible 	= 0;
				$detected = 0;

				// A value a developer wrote as something other than a string - an
				// int year, a list - used to reach strlen(), which under
				// strict_types is a TypeError, which the error handler answers
				// with a 500: every panel that reads the text stopped opening
				// until somebody found the line. A scalar reads as the text it
				// stands for; anything else is not text and is left out
				foreach( $values as $locale => $value ) {

					if( $value === null )
						continue;

					if( is_scalar( $value ) === false ) {
						unset( $values[$locale] );
						continue;
					}

					$values[$locale]	= (string) $value;
					$longest 					= max( $longest, strlen( $values[$locale] ) );
					$visible 					= max( $visible, self::visibleLength( $values[$locale] ) );
					$detected 				= max( $detected, (int) array_search( \Nino\Html::detectFormat( $values[$locale] ), \Nino\Html::FORMATS, true ) );
				}

				if( $values === [] )
					continue;

				$format = $meta[$key]['format'] ?? \Nino\Html::FORMATS[$detected];
				$limit 	= $meta[$key]['maxlength'] ?? null;
				$typed 	= [];
				$limits = [];
				$empty 	= [];

				foreach( $values as $locale => $value ) {

					$shown = $value === null ? null : self::typed( $value, $format );

					if( $shown !== null && $shown !== $value )
						$typed[$locale] = $shown;

					// What the language may hold: the limit, and the text it already has where that is longer - a text that was
					// there before the limit was lowered stays as it is and is not made longer
					$limits[$locale] = max( $limit ?? self::maxlength( $longest ), $shown === null ? 0 : self::length( $shown, $format ) );
				}

				// The languages that have no text for a key that is one text for each (see isEmpty()): every language of the
				// site that has a text file, the ones whose file does not hold the key among them
				if( $isGlobal === false )
					foreach( $locales as $locale )
						if( self::isEmpty( isset( $values[$locale] ) === true ? (string) $values[$locale] : null ) === true )
							$empty[] = $locale;

				$entries[] = [
					'key' 				=> $key,
					'global' 			=> $isGlobal,
					'blacklisted' => $isBlacklisted,
					'html' 				=> $format !== 'plain',
					'format' 			=> $format,
					'formatSet' 	=> isset( $meta[$key]['format'] ),
					'maxlength' 	=> $limit !== null ? max( $limit, $visible ) : self::maxlength( $longest ),
					'maxlengthSet' => $limit !== null,
					'keyLimit' 		=> $limit ?? self::maxlength( $longest ),
					'limits' 			=> $limits,
					'empty' 			=> $empty,
					'values' 			=> $values,
				] + ( $typed === [] ? [] : [ 'typed' => $typed ] );
			}

			usort( $entries, fn( array $a, array $b ) => strcmp( $a['key'], $b['key'] ) );

			return $entries;
		}

		/**
		 *	One entry of entries() by its key.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key				The key without brackets, eg. '/template/page-home/hero/title'
		 *	@param		bool		$includeBlacklisted	Whether a blacklisted key may be found
		 *
		 *	@return 	array|null						The entry, null for a key nobody holds
		 */
		public static function entry( array &$appData, string $key, bool $includeBlacklisted = true ): array|null {

			foreach( self::entries( $appData, $includeBlacklisted ) as $entry )
				if( $entry['key'] === $key )
					return $entry;

			return null;
		}

		// The format and limit somebody decided for a key, read from
		// /text/meta.php: ['/the/key' => ['format' => ..., 'maxlength' => ...]].
		// The file is editable, so what is not a format or a limit is left
		// out here rather than trusted
		public static function meta( array &$appData ): array {

			$stored = \Nino\Filesystem::getFileContent( $appData, self::META_PATH, [] );
			$meta 	= [];

			foreach( is_array( $stored ) === true ? $stored : [] as $key => $settings ) {

				if( is_string( $key ) === false || is_array( $settings ) === false )
					continue;

				$entry = [];

				if( in_array( $settings['format'] ?? null, \Nino\Html::FORMATS, true ) === true )
					$entry['format'] = $settings['format'];

				if( is_int( $settings['maxlength'] ?? null ) === true && $settings['maxlength'] >= 1 && $settings['maxlength'] <= self::MAX_LIMIT )
					$entry['maxlength'] = $settings['maxlength'];

				if( $entry !== [] )
					$meta[$key] = $entry;
			}

			return $meta;
		}

		// Set one key's format and limit in /text/meta.php - what both are to
		// be afterwards: null takes a setting away, so the key goes back to
		// what its values say, and an entry left with neither is dropped.
		// Answers whether the file is as asked
		public static function setMeta( array &$appData, string $key, ?string $format, ?int $maxlength ): bool {
			return self::updateMeta( $appData, $key, [ 'format' => $format, 'maxlength' => $maxlength ] );
		}

		// Change some of one key's settings in /text/meta.php: $changes names
		// the ones to set ('format', 'maxlength'), null takes one away, and a
		// setting it leaves out is left as the file has it - read and written
		// under the one lock, so two requests that change different settings
		// of a key both land. Answers whether the file is as asked
		public static function updateMeta( array &$appData, string $key, array $changes ): bool {

			$changes = array_intersect_key( $changes, [ 'format' => 1, 'maxlength' => 1 ] );

			if( isset( $changes['format'] ) === true && in_array( $changes['format'], \Nino\Html::FORMATS, true ) === false )
				return false;

			if( isset( $changes['maxlength'] ) === true && ( is_int( $changes['maxlength'] ) === false || $changes['maxlength'] < 1 || $changes['maxlength'] > self::MAX_LIMIT ) )
				return false;

			$unchanged = false;

			$written = \Nino\Filesystem::mutate( $appData, self::META_PATH, function( mixed $meta ) use ( $key, $changes, &$unchanged ): ?array {

				$meta 		= is_array( $meta ) === true ? $meta : [];
				$settings = array_merge( is_array( $meta[$key] ?? null ) === true ? $meta[$key] : [], $changes );
				$entry 		= array_filter( [ 'format' => $settings['format'] ?? null, 'maxlength' => $settings['maxlength'] ?? null ], fn( mixed $setting ) => $setting !== null );

				// Nothing to write: mutate() reads a null as "abort", which is
				// not a failure here
				if( $entry === [] ? array_key_exists( $key, $meta ) === false : ( $meta[$key] ?? null ) === $entry ) {
					$unchanged = true;
					return null;
				}

				if( $entry === [] )
					unset( $meta[$key] );
				else
					$meta[$key] = $entry;

				return $meta;
			} );

			return $written === true || $unchanged === true;
		}

		// Give a key's settings to its new name in /text/meta.php: one
		// mutation, so nothing is left under the old name and a change made
		// meanwhile is not lost. A key without settings has nothing to move.
		// Answers whether the file is as asked
		public static function moveMeta( array &$appData, string $key, string $newKey ): bool {

			$unchanged = false;

			$written = \Nino\Filesystem::mutate( $appData, self::META_PATH, function( mixed $meta ) use ( $key, $newKey, &$unchanged ): ?array {

				if( is_array( $meta ) === false || array_key_exists( $key, $meta ) === false ) {
					$unchanged = true;
					return null;
				}

				$meta[$newKey] = $meta[$key];
				unset( $meta[$key] );

				return $meta;
			} );

			return $written === true || $unchanged === true;
		}

		/**
		 *	Whether a key has the form of a text key a person may create or
		 *	rename (see KEY_GRAMMAR). Not a statement about the keys already
		 *	there: a project may hold older ones, and they stay as they are
		 *
		 *	@param		string		$key				A text key without brackets
		 *
		 *	@return 	bool
		 */
		public static function isGrammarKey( string $key ): bool {
			return preg_match( self::KEY_GRAMMAR, $key ) === 1;
		}

		/**
		 *	The text of a stored value as a person typed it: a plain text is stored with its quotes and brackets as entities
		 *	(see sanitizeValue()), and those four entities - &quot;, &#039;, &#91; and &#93; - are written back as the
		 *	characters they stand for. Only where the stored value is what sanitizeValue() makes of the decoded text: a value
		 *	that would be stored differently (a shortcode written out as entities, a value somebody put in the file by hand
		 *	with a tag in it) is answered as it is stored. Every other format is answered as it is stored
		 *
		 *	@param		string		$stored				The value as it is stored
		 *	@param		string		$format				plain, inline, lines or blocks
		 *
		 *	@return		string									What the field shows: the text, or the stored value where it cannot be written back
		 */
		public static function typed( string $stored, string $format ): string {

			if( $format !== 'plain' )
				return $stored;

			$typed = str_replace( [ '&quot;', '&#039;', '&#91;', '&#93;' ], [ '"', "'", '[', ']' ], $stored );

			return self::sanitizeValue( $typed, 'plain' ) === $stored ? $typed : $stored;
		}

		// How many characters of a value a person sees: the tags are not
		// text, and an entity is one character. What a key's limit is
		// measured in - the editor counts the same way
		public static function visibleLength( string $value ): int {
			return mb_strlen( html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'UTF-8' );
		}

		/**
		 *	How long a text is as the field of the workbench counts it, in the unit of the browser: UTF-16 code units, so
		 *	that an emoji is two and a family of emoji is eight. A plain text is counted as the field holds it - the text
		 *	as it is typed (see typed()), a line break one character whatever it was sent as - and a text with markup by what
		 *	a person sees: the tags are no text, an entity is the character it stands for, nothing is collapsed. That is
		 *	what the plain field's counter and the rich-text editor count, and what a text may not exceed in the Text panel
		 *	and the Text Keys tab (see saveBatch()). visibleLength() counts the same text in characters of Unicode and
		 *	decodes the entities of a plain text too
		 *
		 *	@param		string		$text					The text as its field holds it: as typed for a plain text, as stored for any other
		 *	@param		bool|string	$format				plain, inline, lines or blocks; true is 'inline', false 'plain'
		 *
		 *	@return		int
		 */
		public static function length( string $text, bool|string $format ): int {

			if( $format === true || ( is_string( $format ) === true && $format !== 'plain' ) )
				$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			else
				$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

			return intdiv( strlen( (string) mb_convert_encoding( $text, 'UTF-16LE', 'UTF-8' ) ), 2 );
		}

		/**
		 *	Whether a text says nothing: there is none, or nothing is left of it when the markup is taken away. A break, a
		 *	paragraph and a list item leave a space and every other tag nothing; an entity of the characters of markup, a
		 *	number written as a reference and the non-breaking space are the characters they stand for, read once - so a
		 *	reference that spells another reference stays text -; and white space is no text, the non-breaking space and
		 *	the spaces of other scripts among it. An image with no alt text and a comment are tags: a text of nothing
		 *	else is empty. A shortcode, a fill, a zero and a zero-width space are text. The one rule of the workbench for
		 *	"this language has no text for it" - the Text panel's lists, its counts and its filter, and the number the
		 *	Dashboard says is still to translate - while the site's own fallback to another language (see
		 *	\Nino\Html::getFills()) asks whether a text is blank, which only white space is
		 *
		 *	@param		string|null	$text					A text as it is stored, null where a language has none
		 *
		 *	@return		bool
		 */
		public static function isEmpty( ?string $text ): bool {

			if( $text === null )
				return true;

			$text = (string) preg_replace( [ '#</?(?:br|p|li|ul|ol)\b[^>]*>#i', '#<[^>]*>#' ], [ ' ', '' ], $text );

			$text = (string) preg_replace_callback( '/&(#x?[0-9a-f]+|[a-z]+);/i', static function( array $match ): string {

				$name = $match[1];

				if( $name[0] === '#' ) {

					$hex 	= strtolower( $name[1] ) === 'x';
					$code = $hex === true ? hexdec( substr( $name, 2 ) ) : ( preg_match( '/^[0-9]+/', substr( $name, 1 ), $digits ) === 1 ? (int) $digits[0] : 0 );

					// A lone surrogate is no character of a string in PHP; in the browser it is text
					if( $code > 0 && $code < 0x110000 )
						return mb_chr( (int) $code, 'UTF-8' ) ?: 'x';

					return $match[0];
				}

				return match( strtolower( $name ) ) {
					'amp' 	=> '&',
					'lt' 		=> '<',
					'gt' 		=> '>',
					'quot' 	=> '"',
					'apos' 	=> "'",
					'nbsp' 	=> ' ',
					default => $match[0],
				};
			}, $text );

			// The white space of a browser's trim(), spelled out: a class of the pattern engine would count more of it
			return preg_match( '/[^\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', $text ) === 0;
		}

		// Read the developer-maintained list of keys hidden from the workbench's
		// Text panel (technical values, not content - uris, colors,
		// typography, ...)
		public static function blacklist( array &$appData ): array {
			return array_flip( \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ) );
		}

		// Add or remove one key from /text/blacklist.php - _admin-only,
		// the Text panel only ever reads the list. True when the list is as
		// asked afterwards: written, or already so. False when it could not be
		// written - a caller whose answer depends on the key being hidden
		// says so instead of reporting a success
		public static function setBlacklisted( array &$appData, string $key, bool $blacklisted ): bool {

			$unchanged = false;

			$written = \Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $key, $blacklisted, &$unchanged ): ?array {

				$has = in_array( $key, $list, true );

				if( $blacklisted === true && $has === false )
					$list[] = $key;
				else if( $blacklisted === false && $has === true )
					$list = array_values( array_diff( $list, [ $key ] ) );
				else {
					$unchanged = true;
					return null;
				}

				return $list;
			} );

			return $written === true || $unchanged === true;
		}

		// Save several keys' values in one request, batched per target file
		// - a locale file gets one lock -> re-read -> write cycle no matter
		// how many of its keys changed, instead of one per key. That's not
		// just an optimization: two separate saves hitting the same file
		// concurrently would race (each reads the file before the other's
		// write lands, so one update gets silently lost) - batching removes
		// the race entirely by construction.
		//
		// A per-item failure (unknown/blacklisted key, invalid locale)
		// doesn't fail the whole call - it's reported per key in the
		// returned results so the other, valid items still get saved.
		// A locale is valid when it has a text file, one of a language the
		// site does not serve yet included: it is translated before it is
		// switched on. $includeBlacklisted has the same meaning as
		// entries()'s own parameter: whether a blacklisted key is a valid
		// save target.
		//
		// An item may carry 'was', the text its editor read - a string, an
		// empty one for a text there was none of: where the file holds
		// another text by the time the lock is taken - another tab or
		// another person wrote the key since - the key is not written and its
		// result says 'changed'. 'force' => true (a bool) writes it over what
		// is there, once; an item without 'was' is written as it is; one
		// whose 'was' is no string or whose 'force' is no bool is refused
		// as 'invalid'. Every item is decided for itself, by the file it
		// goes to and the text that file holds when the lock is taken: an
		// item that says 'changed' is not written, the others of the batch
		// are. The results are one for each key: a key named twice - in two
		// languages - says 'changed' where either of its items did, and
		// the other item is written all the same, by its own stamp.
		// A written key's result is { ok, value, typed, empty }: value is the text as
		// it is stored, typed the text as a person types it (see typed()), the
		// two forms the editor holds afterwards, and empty whether that language
		// has no text for the key after the write (see isEmpty()).
		//
		// $enforceLimit is what the editors of the workbench ask for: a text
		// longer than its language's limit (see length(), entries()' limits) is
		// not written, and its key's result is { ok: false, error: 'too long',
		// code: 'text_too_long', params: [ limit, count ], locale }, the language
		// of the item. A text that was longer than the limit before - stored then,
		// or the limit was lowered - is written as long as it does not get longer.
		// Nothing is cut: the text is sanitised whole, and where its stored form does
		// not fit the 20000 bytes of the file - its markup counts - it is refused as
		// a whole, { ok: false, error: 'too large', code: 'text_too_large', params:
		// [ bytes, limit of the file ], locale }, unless it is not larger than the text
		// the file holds for that key and language: a text that was stored above the
		// limit of the file is written as long as it does not grow. A text sent at more
		// than 12 times what the file takes is refused without being read (bytes is
		// the size that was sent). An item whose stamp is not what the file holds is
		// the conflict of that, 'changed', answered before any length is asked and
		// never written; 'force' has no stamp and has every check made. A key named in
		// two languages says it where either of its items was refused, and the other
		// item is written all the same; a written key's result carries limit - what
		// its language may hold after the write - and keyLimit - the limit of the key
		// itself - worked out from what was written and what the entries read (see
		// entries()' limits). Without it the limit is the editor's hint alone, and
		// sanitizeValue()'s cut is what it has always been, as it is for a feature that
		// writes through here
		public static function saveBatch( array &$appData, array $items, bool $includeBlacklisted, bool $enforceLimit = false ): array {

			$results 		= [];
			$fileChanges = [];
			$fileKeys 	= [];
			$fileWas 		= [];
			$refused 		= [];
			$conflicts 	= [];
			$wrote 			= [];
			$after 			= [];

			// entry() rebuilds the full entries() list (global.php + every
			// locale file) on every call - fine for a single lookup, not for
			// one per item in a batch. Built once here and indexed by key
			// instead.
			$entriesByKey = array_column( self::entries( $appData, $includeBlacklisted ), null, 'key' );

			foreach( $items as $item ) {

				$key 		= (string) ( $item['key'] ?? '' );
				$locale = (string) ( $item['locale'] ?? '' );
				$value 	= (string) ( $item['value'] ?? '' );

				$entry = $entriesByKey[$key] ?? null;

				if( $entry === null ) {
					$results[$key] = [ 'ok' => false, 'error' => 'unknown key' ];
					continue;
				}

				// A stamp is a text - an empty one says "there was none" - and force is a bool: anything else is a faulty entry, written
				// as little as any other
				if( ( array_key_exists( 'was', $item ) === true && is_string( $item['was'] ) === false ) || ( array_key_exists( 'force', $item ) === true && is_bool( $item['force'] ) === false ) ) {
					$results[$key] = [ 'ok' => false, 'error' => 'invalid', 'code' => 'text_invalid' ];
					continue;
				}

				if( $entry['global'] === false && in_array( $locale, \Nino\Locales::getTextLocales( $appData ), true ) === false ) {
					$results[$key] = [ 'ok' => false, 'error' => 'invalid locale' ];
					continue;
				}

				$where 	= $entry['global'] === true ? '*' : $locale;
				$stored = is_scalar( $entry['values'][$where] ?? null ) === true ? (string) $entry['values'][$where] : '';

				if( $enforceLimit === true ) {

					// A text that was written by another since its editor read it is the conflict of that, whatever its length - the person is not
					// to shorten a text and meet the conflict afterwards -, and it is answered here and not queued: the file is checked under
					// the lock only for what is written. A forced item has no stamp to be held to, and every check below is made for it
					if( array_key_exists( 'was', $item ) === true && ( $item['force'] ?? false ) !== true && $stored !== (string) $item['was'] ) {
						$conflicts[$key] = true;
						$results[$key] = [ 'ok' => false, 'error' => 'changed' ];
						continue;
					}

					$refusal = null;
					$typed = '';

					// What sanitising cannot shrink to the file is refused before it is read: every named entity of HTML5 is at most 12 times
					// as long as the character it stands for (&DiacriticalDoubleAcute; is 24 bytes and 2), and what the editor sends is no more
					// than 3 times as long (&nbsp;, 6 bytes and 2). A numeric reference padded with zeros and markup the sanitiser drops have no
					// such bound, so none is provable for every text; the one of 12 refuses nothing an editor or a person typing entities makes
					if( strlen( $value ) > self::SANITIZED_REACH * max( self::HARD_MAXLENGTH, strlen( $stored ) ) )
						$refusal = [ 'ok' => false, 'error' => 'too large', 'code' => 'text_too_large', 'params' => [ strlen( $value ), self::HARD_MAXLENGTH ], 'locale' => $locale ];

					if( $refusal === null ) {

						// Sanitised whole: what the limit of the file would cut is refused, not stored as half of the text
						$value = self::_sanitize( $value, $entry['format'], null );
						$typed = self::typed( $value, $entry['format'] );
						$limit = (int) ( $entry['limits'][$where] ?? $entry['maxlength'] );
						$count = self::length( $typed, $entry['format'] );

						if( $count > $limit )
							$refusal = [ 'ok' => false, 'error' => 'too long', 'code' => 'text_too_long', 'params' => [ $limit, $count ], 'locale' => $locale ];
						// A text that is larger than the file takes was stored so, and is written as long as it does not grow
						elseif( strlen( $value ) > self::HARD_MAXLENGTH && strlen( $value ) > strlen( $stored ) )
							$refusal = [ 'ok' => false, 'error' => 'too large', 'code' => 'text_too_large', 'params' => [ strlen( $value ), self::HARD_MAXLENGTH ], 'locale' => $locale ];
					}

					if( $refusal !== null ) {
						$refused[$key] = $refusal;
						$results[$key] = $refusal;
						continue;
					}
				}
				else {
					$value = self::sanitizeValue( $value, $entry['format'] );
					$typed = self::typed( $value, $entry['format'] );
				}

				$file = ( $entry['global'] === true ) ? '/text/global.php' : '/text/'. $locale. '.php';

				$fileChanges[$file]['[['. $key. ']]'] = $value;
				$fileKeys[$file][] = $key;

				if( array_key_exists( 'was', $item ) === true && ( $item['force'] ?? false ) !== true )
					$fileWas[$file]['[['. $key. ']]'] = (string) $item['was'];

				$results[$key] = [ 'ok' => true, 'value' => $value, 'typed' => $typed, 'empty' => $entry['global'] === false && self::isEmpty( $value ) ];
				$wrote[$key] = $where;
				$after[$key] ??= $entry['values'];
				$after[$key][$where] = $value;
			}

			foreach( $fileChanges as $file => $changes ) {

				$changed = [];

				$written = \Nino\Filesystem::mutate( $appData, $file, function( array $content ) use ( $changes, $fileWas, $file, &$changed ): ?array {

					// What the editor read is compared with what the file holds now, under the lock
					foreach( $fileWas[$file] ?? [] as $bracket => $was )
						if( (string) ( is_scalar( $content[$bracket] ?? null ) === true ? $content[$bracket] : '' ) !== $was ) {
							$changed[$bracket] = true;
							unset( $changes[$bracket] );
						}

					return $changes === [] ? null : array_merge( $content, $changes );
				} );

				foreach( $fileKeys[$file] as $key ) {

					if( isset( $changed['[['. $key. ']]'] ) === true ) {
						$results[$key] = [ 'ok' => false, 'error' => 'changed' ];
						continue;
					}

					// mutate()'s result was previously discarded - every key
					// destined for this file was reported 'ok' even if the write
					// itself (lock failure, disk full) never happened. A file where
					// every key was left as it was has no write to fail
					if( $written === false && count( $changed ) < count( $changes ) )
						$results[$key] = [ 'ok' => false, 'error' => 'could not be written' ];
				}
			}

			// A key one of whose languages was refused says so, whatever another language of it answered
			foreach( $refused as $key => $refusal )
				if( ( $results[$key]['ok'] ?? false ) === true )
					$results[$key] = $refusal;

			// ...and a key one of whose languages is the conflict of another says that, before any refusal
			foreach( $conflicts as $key => $unused )
				if( ( $results[$key]['ok'] ?? false ) === true || isset( $refused[$key] ) === true )
					$results[$key] = [ 'ok' => false, 'error' => 'changed' ];

			// What the language may hold after the write and what the key's own limit is, from what was written and what the entries
			// read before it: the longest text of the key decides the limit that is derived, a set one is the file's
			if( $enforceLimit === true && $wrote !== [] ) {

				$meta = self::meta( $appData );

				foreach( $wrote as $key => $where ) {

					if( ( $results[$key]['ok'] ?? false ) === false )
						continue;

					$entry 	= $entriesByKey[$key];
					$longest = 0;

					foreach( $after[$key] as $text )
						if( is_string( $text ) === true )
							$longest = max( $longest, strlen( $text ) );

					$own = $entry['maxlengthSet'] === true ? (int) $meta[$key]['maxlength'] : self::maxlength( $longest );

					$results[$key]['keyLimit'] = $own;
					$results[$key]['limit'] = max( $own, self::length( self::typed( (string) $after[$key][$where], $entry['format'] ), $entry['format'] ) );
				}
			}

			return $results;
		}

		/**
		 *	Create, set and remove keys in one step, for a tool that writes the words of a page together with the page - the
		 *	Builder. A language file is changed by one mutate() however many keys it takes, so that two writers do not lose each
		 *	other's keys, and the answer says what stood there before, which is what a tool that has to undo the step needs.
		 *
		 *	Only a key of the form a person may create (see isGrammarKey()) is written or removed, and the whole call is refused,
		 *	before any file is touched, if one is not - or names a language that has no text file, or holds a text that is none, or
		 *	sets and removes the same key, or sets a key that is global (a key of global.php has no language to be set in). A new
		 *	key is written to the file of every language of the site, the texts named in theirs and an empty text in the others, as
		 *	a key a person creates is (see the Text Keys tab); a key that is there is set in the languages named and in no other.
		 *	A text goes through sanitizeValue() as saveBatch() sends it: by the format that was set for the key in meta.php - the Text Keys tab's decision, which
		 *	a caller does not overrule -, else the one the caller names for it, else the one the key's texts hold. A key that is removed leaves every language file, global.php too, and the
		 *	format and limit it had in meta.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$set					[ '/template/page-x/hero/title' => [ 'de_DE' => 'Titel', 'en_US' => 'Title' ] ]
		 *	@param		array			$drop					[ '/template/page-x/hero/old' ]
		 *	@param		array			$formats			[ key => plain, inline, lines or blocks ] - what the component that reads a key says of it
		 *
		 *	@return 	array|null								What stood there before, key => language => text - null where there was none, '*' for a global key that was removed - for every key set or removed; null where nothing was done or a file could not be written (a file written before it stays)
		 */
		public static function put( array &$appData, array $set, array $drop = [], array $formats = [] ): ?array {

			$locales = \Nino\Locales::getTextLocales( $appData );

			foreach( $set as $key => $texts ) {

				if( is_string( $key ) === false || self::isGrammarKey( $key ) === false || is_array( $texts ) === false || in_array( $key, $drop, true ) === true )
					return null;

				foreach( $texts as $locale => $text )
					if( is_string( $locale ) === false || in_array( $locale, $locales, true ) === false || is_string( $text ) === false )
						return null;
			}

			foreach( $drop as $key )
				if( is_string( $key ) === false || self::isGrammarKey( $key ) === false )
					return null;

			$entries = array_column( self::entries( $appData, true ), null, 'key' );
			$meta = self::meta( $appData );

			// The text of each file to write: file => [ '[[key]]' => text ]; and the keys each file loses
			$changes = [];
			$removals = [];

			foreach( $set as $key => $texts ) {

				if( ( $entries[$key]['global'] ?? false ) === true )
					return null;

				$format = isset( $meta[$key]['format'] ) === true ? $meta[$key]['format']
					: ( in_array( $formats[$key] ?? null, \Nino\Html::FORMATS, true ) === true ? $formats[$key] : ( $entries[$key]['format'] ?? \Nino\Html::detectFormat( implode( ' ', $texts ) ) ) );
				$named = isset( $entries[$key] ) === true ? array_keys( $texts ) : array_unique( array_merge( \Nino\Locales::getAvailableLocales( $appData ), array_keys( $texts ) ) );

				foreach( $named as $locale )
					$changes['/text/'. $locale. '.php']['[['. $key. ']]'] = self::sanitizeValue( $texts[$locale] ?? '', $format );
			}

			foreach( $drop as $key )
				foreach( array_merge( array_map( static fn( string $locale ): string => '/text/'. $locale. '.php', $locales ), [ '/text/global.php' ] ) as $file )
					$removals[$file][] = '[['. $key. ']]';

			$before = [];

			foreach( array_unique( array_merge( array_keys( $changes ), array_keys( $removals ) ) ) as $file ) {

				$locale = basename( $file, '.php' ) === 'global' ? '*' : basename( $file, '.php' );
				$put = $changes[$file] ?? [];
				$gone = $removals[$file] ?? [];

				$unchanged = false;

				$written = \Nino\Filesystem::mutate( $appData, $file, static function( mixed $content ) use ( $put, $gone, $locale, &$before, &$unchanged ): ?array {

					$content = is_array( $content ) === true ? $content : [];
					$changed = false;

					foreach( array_merge( array_keys( $put ), $gone ) as $bracket ) {

						$was = is_scalar( $content[$bracket] ?? null ) === true ? (string) $content[$bracket] : null;

						// A key that was not there and is not removed has no "before" to tell of in a file it does not have it in
						if( $was !== null || isset( $put[$bracket] ) === true )
							$before[substr( $bracket, 2, -2 )][$locale] = $was;
					}

					foreach( $put as $bracket => $text )
						if( ( $content[$bracket] ?? null ) !== $text ) {
							$content[$bracket] = $text;
							$changed = true;
						}

					foreach( $gone as $bracket )
						if( array_key_exists( $bracket, $content ) === true ) {
							unset( $content[$bracket] );
							$changed = true;
						}

					$unchanged = $changed === false;

					return $changed === true ? $content : null;
				} );

				// A file the step has nothing to change in is an abort of the mutation, and as it should be
				if( $written === false && $unchanged === false )
					return null;
			}

			if( $drop !== [] ) {

				\Nino\Filesystem::mutate( $appData, self::META_PATH, static function( mixed $meta ) use ( $drop ): ?array {

					if( is_array( $meta ) === false || array_intersect_key( $meta, array_flip( $drop ) ) === [] )
						return null;

					return array_diff_key( $meta, array_flip( $drop ) );
				} );
			}

			return $before;
		}

		/**
		 *	Every text of a namespace, in every language: the keys that stand below a prefix with what each language says - the
		 *	words of one page, for a tool that shows or stamps them together
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$prefix				A namespace with its slash in front, eg. '/template/page-home' - the keys below it, not a key that only begins alike
		 *
		 *	@return 	array											key => language => text, '*' for a global key; a language that has no text for the key is not in it. [] for a prefix that is none
		 */
		public static function values( array &$appData, string $prefix ): array {

			$prefix = rtrim( $prefix, '/' );

			if( preg_match( '#^(?:/[a-z0-9]+(?:-[a-z0-9]+)*)+$#D', $prefix ) !== 1 )
				return [];

			$found = [];

			foreach( self::entries( $appData, true ) as $entry )
				if( str_starts_with( $entry['key'], $prefix. '/' ) === true )
					$found[$entry['key']] = array_filter( $entry['values'], static fn( mixed $text ): bool => is_string( $text ) === true );

			return $found;
		}

		// The two ways a text puts a textfill: the fill of a template and of HTML+, the key in two pairs of
		// brackets, and the placeholder of an element's text, the key between two hashes (see \Nino\Modules\Legal::placeholders()) - each with
		// the namespaces a field written in it may name. The facts of the company and of the website are
		// public anyway, and the words several templates share are no secret; the mailbox the forms deliver
		// to, a technical value and the words of one template or feature are not offered - fills() lists
		// no key outside these
		public const array FILL_FORMS = [
			'textfill' 		=> [ 'open' => '[[', 'close' => ']]', 'prefixes' => [ ...\Nino\Modules\Legal::PREFIXES, '/template/common/' ] ],
			'placeholder' => [ 'open' => '#', 'close' => '#', 'prefixes' => \Nino\Modules\Legal::PREFIXES ],
		];

		// How long the value of a fill is in the list that offers it: a person recognises a fill by its start
		private const int FILL_SHOWN = 120;

		/**
		 *	The textfills a field may take, each in the notation the field reads and with what it says today: the keys
		 *	of one of FILL_FORMS' namespaces that follow the grammar and are not hidden from the workbench, with the
		 *	value they have in a language - the text as plain words, cut where it is long, the fallback languages
		 *	applied as a page applies them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$form					A name of FILL_FORMS; any other is a form that offers nothing
		 *	@param		string		$locale				A language of the site; one that is none has no fills
		 *
		 *	@return 	array										[ { key, notation, value } ], by key
		 */
		public static function fills( array &$appData, string $form, string $locale ): array {

			if( isset( self::FILL_FORMS[$form] ) === false )
				return [];

			$notation = self::FILL_FORMS[$form];
			$hidden 	= self::blacklist( $appData );
			$found 		= [];

			foreach( \Nino\Html::getFills( $appData, $locale ) as $bracket => $value ) {

				if( is_string( $bracket ) === false || is_scalar( $value ) === false || str_starts_with( $bracket, '[[' ) === false || str_ends_with( $bracket, ']]' ) === false )
					continue;

				$key = substr( $bracket, 2, -2 );

				if( self::isGrammarKey( $key ) === false || isset( $hidden[$key] ) === true )
					continue;

				foreach( $notation['prefixes'] as $prefix )
					if( str_starts_with( $key, $prefix ) === true ) {

						$plain = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( \Nino\Html::breaksToNewlines( (string) $value ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

						$found[] = [
							'key' 			=> $key,
							'notation' 	=> $notation['open']. $key. $notation['close'],
							'value' 		=> mb_strlen( $plain, 'UTF-8' ) > self::FILL_SHOWN ? mb_substr( $plain, 0, self::FILL_SHOWN, 'UTF-8' ). '…' : $plain,
						];

						break;
					}
			}

			usort( $found, static fn( array $a, array $b ): int => strcmp( $a['key'], $b['key'] ) );

			return $found;
		}

		/**
		 *	The one value-normalization path shared by regular batch saves and
		 *	_admin's JSON translation import. Keeping it here prevents import
		 *	from bypassing the hard length limit and HTML whitelist that the form
		 *	itself enforces.
		 *
		 *	$format is one of \Nino\Html::FORMATS; true is 'inline' and false is
		 *	'plain', which is what the parameter was before there were more. A
		 *	name that is none of them is read as 'plain': the narrowest answer.
		 *	'lines' and 'blocks' read a newline of the value as a break or a
		 *	paragraph - a form posts the text of a textarea, and a value may
		 *	move from plain to either with one
		 *
		 *	@param		string		$value				What was typed, imported or sent
		 *	@param		bool|string	$format				plain, inline, lines or blocks; true is 'inline', false 'plain'
		 *
		 *	@return 	string							The value as it may be stored: cut at the hard byte limit, its markup reduced to what the format keeps, every bracket but those of a fill written as an entity
		 */
		public static function sanitizeValue( string $value, bool|string $format ): string {
			return self::_sanitize( $value, $format, self::HARD_MAXLENGTH );
		}

		// What sanitizeValue() does, with the byte limit it cuts at as a parameter: null cuts nothing, for the editors of the
		// workbench, which refuse a text that does not fit the limit of the file, where storing half of it would lose the rest
		private static function _sanitize( string $value, bool|string $format, ?int $cut ): string {

			if( is_bool( $format ) === true )
				$format = $format === true ? 'inline' : 'plain';

			/*	mb_strcut() rather than substr(): the limit is a byte count -
				what the file on disk has to stay under - but a cut at a byte
				offset lands inside a multibyte character as readily as between
				two, and half a character is a text file that is not utf-8 any
				more. Measured with 19999 ascii bytes and one 'ä' across the
				boundary: the stored value ended on a lone 0xC3, and every
				reader of it answers U+FFFD for that byte - the panel's json,
				htmlspecialchars() with ENT_SUBSTITUTE on the page, an export -
				so the word came back broken and saving it again wrote the
				replacement character in for good. mb_strcut() backs off to the
				last character boundary instead: the same byte limit, at most
				one character less of it, and always utf-8.	*/
			if( $cut !== null )
				$value = mb_strcut( $value, 0, $cut, 'UTF-8' );

			if( in_array( $format, [ 'inline', 'lines', 'blocks' ], true ) === true )
				return self::_neutralizeShortcodes( \Nino\Html::sanitizeHtml( $value, $format ) );

			// A break or the end of a block is a line of the text, not nothing:
			// strip_tags() alone writes 'Amtsgericht<br>Musterstadt' as
			// 'AmtsgerichtMusterstadt'
			$value = \Nino\Html::breaksToNewlines( $value );

			// strip_tags() answers "no markup of its own", which is the whole
			// requirement as long as a fill lands in text content. It does not
			// survive an attribute though, and a fill is put into the page, and into the arguments of a
			// call, as it is: the shipped templates put
			// plain-text fills inside href/src/alt/content/title (eg.
			// '<meta name="author" content="[[/project/website/general/author]]">' in
			// html-header.tpl), where a stored value of  x" onmouseover="...
			// closes the attribute and opens an event handler that fires for
			// every visitor. The quotes go in as entities, which render as
			// themselves in text and as themselves in an attribute, so nothing
			// on screen changes.
			// Deliberately not htmlspecialchars(): that also encodes '&', and a
			// value re-saved from the editor would gain a round of escaping on
			// every pass. Neither entity below contains a quote, so this stays
			// idempotent.
			return self::_neutralizeShortcodes( str_replace( [ '"', "'" ], [ '&quot;', '&#039;' ], strip_tags( $value ) ) );
		}

		/**
		 *	A stored value may name another fill. It may not carry a shortcode.
		 *
		 *	\Nino\Html puts the value of a fill into the page as it is and reads
		 *	it for nothing but the fills inside it, so a call in a value is no
		 *	call - but the same value is put into the arguments of calls, and
		 *	what a shortcode of a feature makes of it is its own business. A
		 *	fill naming another fill is deliberate and the
		 *	shipped texts use it (see Html::resolveTextfill()), but a
		 *	shortcode is not a word: '[template /templates/mail-owner]' stored
		 *	in a heading puts a private template - its copy, the owner's address
		 *	- on a public page, and '[elements /type]' empties a collection onto
		 *	one. The Text panel is an editor's, and an editor edits words; what
		 *	a page includes is a developer's decision.
		 *
		 *	So '[[/template/page-home/welcome/title]]' survives and every other bracket becomes an
		 *	entity, which renders as itself and carries no meaning to a reader.
		 *	Neither entity contains a bracket, so re-saving a value that
		 *	went through here changes nothing - the same idempotence the quotes
		 *	above rely on. AGENTS.md, "Rendering and escaping rules", is the
		 *	standing rule; \Nino\Modules\Elements and the Search and
		 *	ProtectedArea features already neutralize theirs.
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		private static function _neutralizeShortcodes( string $value ): string {

			return (string) preg_replace_callback(
				'#\[\[/[a-zA-Z0-9/_.-]*\]\]|[\[\]]#',
				static fn( array $match ): string => match( $match[0] ) {
					'['			=> '&#91;',
					']'			=> '&#93;',
					default	=> $match[0],
				},
				$value
			);
		}
	}
}
