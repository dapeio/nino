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

		// Every known key across global.php + every locale file, with its
		// current value(s), whether it's global or per-locale, its format
		// (the one set in meta(), else the widest the values hold - 'html' is
		// whether that is more than plain text), a maxlength (the one set,
		// else derived from its longest current value), and whether it's
		// blacklisted (see blacklist()). 'formatSet' and 'maxlengthSet' say
		// whether the two come from meta() or from the values. A limit that is
		// set is never shorter than the longest text the key holds - a longer
		// one written later (an import, the wizard, a feature) is not cut by
		// the editor at its first keystroke.
		// $includeBlacklisted controls whether a blacklisted key is skipped
		// entirely or just flagged - the Text panel hides them, Text Keys
		// editor needs to see them to be able to un-blacklist one.
		public static function entries( array &$appData, bool $includeBlacklisted = true ): array {

			$global 	= \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );

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

				$entries[] = [
					'key' 				=> $key,
					'global' 			=> $isGlobal,
					'blacklisted' => $isBlacklisted,
					'html' 				=> $format !== 'plain',
					'format' 			=> $format,
					'formatSet' 	=> isset( $meta[$key]['format'] ),
					'maxlength' 	=> $limit !== null ? max( $limit, $visible ) : self::maxlength( $longest ),
					'maxlengthSet' => $limit !== null,
					'values' 			=> $values,
				];
			}

			usort( $entries, fn( array $a, array $b ) => strcmp( $a['key'], $b['key'] ) );

			return $entries;
		}

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

		// Whether a key has the form of a text key a person may create or
		// rename (see KEY_GRAMMAR). Not a statement about the keys already
		// there: a project may hold older ones, and they stay as they are
		public static function isGrammarKey( string $key ): bool {
			return preg_match( self::KEY_GRAMMAR, $key ) === 1;
		}

		// How many characters of a value a person sees: the tags are not
		// text, and an entity is one character. What a key's limit is
		// measured in - the editor counts the same way
		public static function visibleLength( string $value ): int {
			return mb_strlen( html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'UTF-8' );
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
		// $includeBlacklisted has the same meaning as entries()'s own
		// parameter: whether a blacklisted key is a valid save target.
		public static function saveBatch( array &$appData, array $items, bool $includeBlacklisted ): array {

			$results 		= [];
			$fileChanges = [];
			$fileKeys 	= [];

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

				if( $entry['global'] === false && \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
					$results[$key] = [ 'ok' => false, 'error' => 'invalid locale' ];
					continue;
				}

				$value = self::sanitizeValue( $value, $entry['format'] );

				$file = ( $entry['global'] === true ) ? '/text/global.php' : '/text/'. $locale. '.php';

				$fileChanges[$file]['[['. $key. ']]'] = $value;
				$fileKeys[$file][] = $key;

				$results[$key] = [ 'ok' => true, 'value' => $value ];
			}

			foreach( $fileChanges as $file => $changes ) {

				$written = \Nino\Filesystem::mutate( $appData, $file, function( array $content ) use ( $changes ): array {
					return array_merge( $content, $changes );
				} );

				// mutate()'s result was previously discarded - every key
				// destined for this file was reported 'ok' even if the write
				// itself (lock failure, disk full) never happened
				if( $written === false )
					foreach( $fileKeys[$file] as $key )
						$results[$key] = [ 'ok' => false, 'error' => 'could not be written' ];
			}

			return $results;
		}

		// The one value-normalization path shared by regular batch saves and
		// _admin's JSON translation import. Keeping it here prevents import
		// from bypassing the hard length limit and HTML whitelist that the form
		// itself enforces.
		//
		// $format is one of \Nino\Html::FORMATS; true is 'inline' and false is
		// 'plain', which is what the parameter was before there were more. A
		// name that is none of them is read as 'plain': the narrowest answer.
		// 'lines' and 'blocks' read a newline of the value as a break or a
		// paragraph - a form posts the text of a textarea, and a value may
		// move from plain to either with one
		public static function sanitizeValue( string $value, bool|string $format ): string {

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
			$value = mb_strcut( $value, 0, self::HARD_MAXLENGTH, 'UTF-8' );

			if( in_array( $format, [ 'inline', 'lines', 'blocks' ], true ) === true )
				return self::_neutralizeShortcodes( \Nino\Html::sanitizeHtml( $value, $format ) );

			// A break or the end of a block is a line of the text, not nothing:
			// strip_tags() alone writes 'Amtsgericht<br>Musterstadt' as
			// 'AmtsgerichtMusterstadt'
			$value = \Nino\Html::breaksToNewlines( $value );

			// strip_tags() answers "no markup of its own", which is the whole
			// requirement as long as a fill lands in text content. It does not
			// survive an attribute though, and Html::_renderFills() is a blind
			// str_replace over the finished document: the shipped templates put
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
		 *	\Nino\Html renders fills first and shortcodes after them, over the
		 *	finished document - so whatever a value carries is read again as
		 *	markup of the page. A fill naming another fill is deliberate and the
		 *	shipped texts use it (see _renderFills()'s own loop), but a
		 *	shortcode is not a word: '[template /templates/mail-owner]' stored
		 *	in a heading puts a private template - its copy, the owner's address
		 *	- on a public page, and '[elements /type]' empties a collection onto
		 *	one. The Text panel is an editor's, and an editor edits words; what
		 *	a page includes is a developer's decision.
		 *
		 *	So '[[/template/page-home/welcome/title]]' survives and every other bracket becomes an
		 *	entity, which renders as itself and carries no meaning on the next
		 *	pass. Neither entity contains a bracket, so re-saving a value that
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
