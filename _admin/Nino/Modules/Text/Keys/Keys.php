<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Text\Keys	Text Keys tab: the keys themselves
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Text {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Full CRUD for text keys: create, rename, delete, change global/
	 *												per-locale shape, toggle whether a key is hidden from
	 *												the Text panel (/text/blacklist.php) - plus, same as
	 *												the Text panel, edit every key's actual value(s).
	 *												Unlike the Text panel, this one also sees blacklisted keys and can still
	 *												edit/rename/delete them (the blacklist only hides a key
	 *												from the Text panel, not from here). Converting a key between global
	 *												and per-locale, or renaming it, migrates its current
	 *												value(s) rather than discarding them - see _convertShape()/
	 *												apiRename().
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Keys {

		public const string MANAGE_PERM = '/_admin/keys/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'keys/list' 			=> [ self::class, 'apiList' ],
				'keys/create' 		=> [ self::class, 'apiCreate' ],
				'keys/save' 			=> [ self::class, 'apiSave' ],
				'keys/savebatch' => [ self::class, 'apiSaveBatch' ],
				'keys/rename' 		=> [ self::class, 'apiRename' ],
				'keys/delete' 		=> [ self::class, 'apiDelete' ],
				'keys/scan' 			=> [ self::class, 'apiScan' ],
				'keys/scanapply' => [ self::class, 'apiScanApply' ],
			];
		}

		/**
		 *	The activity-log line for a mutating action - see
		 *	\Nino\Admin\Admin::_logAction()
		 *
		 *	@param		string		$action				The dispatched action name
		 *	@param		array			$data					The posted data
		 *
		 *	@return 	string
		 */
		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'keys/create' 		=> 'Add Text Key '. ( $data['key'] ?? '' ),
				// Only when the format or the limit is posted: the other thing
				// this action does, the shape and the hidden flag, is not logged
				'keys/save' 			=> ( array_key_exists( 'format', $data ) === true || array_key_exists( 'maxlength', $data ) === true )
					? 'Edit Text Key '. ( $data['key'] ?? '' ). ' ('. implode( ', ', array_filter( [
						array_key_exists( 'format', $data ) === true ? 'format '. ( is_string( $data['format'] ) === true ? $data['format'] : '?' ) : '',
						array_key_exists( 'maxlength', $data ) === true ? 'limit '. ( $data['maxlength'] === null ? 'automatic' : ( is_int( $data['maxlength'] ) === true ? (string) $data['maxlength'] : '?' ) ) : '',
					] ) ). ')'
					: '',
				'keys/rename' 		=> 'Rename Text Key '. ( $data['key'] ?? '' ). ' to '. ( $data['newKey'] ?? '' ),
				'keys/delete' 		=> 'Delete Text Key '. ( $data['key'] ?? '' ),
				// The one action that retires keys in bulk, and the reason this
				// module has a log() at all: an ignored key leaves the scan and
				// the Text panel for good, which is worth a line naming who did it
				'keys/scanapply' => 'Apply Text Scan: '. count( is_array( $data['rows'] ?? null ) ? $data['rows'] : [] ). ' key(s) reviewed',
				default 					=> '',
			};
		}

		/**
		 *	A Dashboard tile: keys the templates use that no text file has yet
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function summary( array &$appData ): array {
			return [ 'value' => self::missingCount( $appData ), 'label' => '/_admin/dashboard/label/keys' ];
		}

		/**
		 *	A tab of the Text pane (see \Nino\Modules\Text\Admin::tabs()) - the uri names the
		 *	hash prefix and the script, the weight orders the strip, and the
		 *	group only says where the permission is listed
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'keys', '/_admin/nav/keys', 30, 'structure' ];
		}

		public static function panes(): array {
			return [ 'keys-list', 'keys-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/textkeys.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/keys.js' ),
				'/_admin/assets/html-editor.js',
			];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	List every known text key, blacklisted or not (unlike the Text panel's
		 *	own panel, this is exactly where you'd come to un-blacklist one)
		 *	- see \Nino\Text::entries() - and what the form that creates or
		 *	renames a key offers to choose from, 'categories': per namespace the
		 *	categories a key can go in -
		 *
		 *	  - template	the templates directly in templates/ whose name is a
		 *								category (see \Nino\Modules\Template::category()), and common
		 *	  - feature		the keys of the installed features
		 *	  - module		the kernel modules, by their directory in lower case
		 *	  - project		company, website and mail, and the ones keys already use
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$keys = array_merge( \Nino\Text::entries( $appData ), self::_blacklistOnly( $appData ) );
			usort( $keys, fn( array $a, array $b ) => strcmp( $a['key'], $b['key'] ) );

			\Nino\Http::ok( $request, [
				'keys' 		=> $keys,
				'locales' => \Nino\Locales::getAvailableLocales( $appData ),
				'selectedLocale' => \Nino\Admin\Admin::sessionLocale( $appData ),
				'categories' => self::_categories( $appData, $keys ),
			] );
		}

		/**
		 *	The categories a key can be created in, per namespace - see apiList()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$keys					The known keys, in \Nino\Text::entries()' shape
		 *
		 *	@return 	array										[ 'template' => [ ... ], 'feature' => [ ... ], 'module' => [ ... ], 'project' => [ ... ] ]
		 */
		private static function _categories( array &$appData, array $keys ): array {

			$template = [];
			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [] as $file ) {
				$category = \Nino\Modules\Template::category( basename( $file ) );
				if( $category !== null && $category !== 'common' )
					$template[] = $category;
			}

			// The kernel this workbench runs from - not the project's directory, which is
			// wherever the data are
			$module = [];
			foreach( glob( dirname( __DIR__, 5 ). '/_nino/Nino/Modules/*', GLOB_ONLYDIR ) ?: [] as $dir )
				$module[] = strtolower( basename( $dir ) );

			$shipped = [ 'company', 'website', 'mail' ];
			$project = [];
			foreach( $keys as $entry )
				if( preg_match( '#^/project/([a-z0-9]+(?:-[a-z0-9]+)*)/#', $entry['key'], $match ) === 1 && in_array( $match[1], $shipped, true ) === false )
					$project[] = $match[1];

			$template = array_unique( $template );
			$module 	= array_unique( $module );
			$project 	= array_unique( $project );
			$feature 	= array_map( 'strval', array_keys( \Nino\Features::all( $appData ) ) );

			sort( $template );
			sort( $module );
			sort( $project );
			sort( $feature );

			return [
				'template' => array_merge( [ 'common' ], $template ),
				'feature' 	=> $feature,
				'module' 	=> $module,
				'project' 	=> array_merge( $shipped, $project ),
			];
		}

		/**
		 *	Create a brand new key with an initial value - global.php gets
		 *	one value, or every locale file gets the same starting value,
		 *	depending on $isGlobal.
		 *
		 *	The key has to follow the grammar of a text key,
		 *	/<namespace>/<category>/<part>/<name> (see
		 *	\Nino\Text::isGrammarKey()): the server decides that, whatever the
		 *	form sent. A key the system writes by itself, /_nino/..., is not
		 *	created by hand
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$isGlobal = ( $data['global'] ?? false ) === true;
			$value 		= (string) ( $data['value'] ?? '' );
			$format 	= self::_postedFormat( $data );

			if( \Nino\Text::isGrammarKey( $key ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid key', 'keys_invalid', [ $key ], 'key' );
				return;
			}

			if( $format === false ) {
				\Nino\Http::fail( $request, 400, 'unknown format', 'keys_format', [], 'format' );
				return;
			}

			if( \Nino\Text::entry( $appData, $key ) !== null ) {
				\Nino\Http::fail( $request, 409, 'key already exists', 'keys_exists', [ $key ], 'key' );
				return;
			}

			self::_writeKey( $appData, $key, $isGlobal, $value, $format );

			// A format that was chosen is remembered; one that was only
			// detected is not, it follows the value
			if( is_string( $format ) === true )
				\Nino\Text::setMeta( $appData, $key, $format, null );

			\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $key ] );
		}

		/**
		 *	Write one key's starting value - into global.php once, or into
		 *	every locale file with the same text. Shared by apiCreate() and
		 *	apiScanApply(), which create keys on exactly the same terms: one
		 *	starting value, to be translated later (see the Translations tab's
		 *	export/import round-trip)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					A key that passed \Nino\Text::isGrammarKey()
		 *	@param		bool			$isGlobal			Whether it lives in global.php
		 *	@param		string		$value				The starting value
		 *	@param		string|null	$format			The format it is kept in, null to read it from the value
		 *
		 *	@return 	void
		 */
		private static function _writeKey( array &$appData, string $key, bool $isGlobal, string $value, ?string $format = null ): void {

			$bracketKey = '[['. $key. ']]';

			// Like any value saved from the workbench: through the format's
			// whitelist, with the shortcodes taken out. It used to be stored
			// as it came, which is the one road into a text file that skipped
			// \Nino\Text::sanitizeValue()
			$value = \Nino\Text::sanitizeValue( $value, $format ?? \Nino\Html::detectFormat( $value ) );

			if( $isGlobal === true ) {
				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey, $value ): array {
					$global[$bracketKey] = $value;
					return $global;
				} );
				return;
			}

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
				\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey, $value ): array {
					$localeData[$bracketKey] = $value;
					return $localeData;
				} );
		}

		/**
		 *	Every key that is in /text/blacklist.php and in no text file at
		 *	all, shaped like a \Nino\Text::entries() entry.
		 *
		 *	The scan form's "ignore" writes exactly this: a key retired
		 *	without ever being given a value (see apiScanApply()).
		 *	\Nino\Text::entries() enumerates global.php and the locale files,
		 *	so such a key appears in none of them - and a key nothing lists is
		 *	a key nobody can un-ignore again. This tab is the one screen that
		 *	sees blacklisted keys at all, so this is where they belong.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Entries in \Nino\Text::entries()' shape
		 */
		private static function _blacklistOnly( array &$appData ): array {

			$known = [];
			foreach( \Nino\Text::entries( $appData ) as $entry )
				$known[$entry['key']] = true;

			// Empty in every locale, and per-locale rather than global - the
			// shape a key gets when the scan form creates one
			$values = [];
			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
				$values[$locale] = null;

			$entries = [];
			$meta 	 = \Nino\Text::meta( $appData );

			foreach( array_keys( \Nino\Text::blacklist( $appData ) ) as $key ) {

				if( isset( $known[$key] ) === true )
					continue;

				$format = $meta[$key]['format'] ?? 'plain';

				$entries[] = [
					'key' 				=> $key,
					'global' 			=> false,
					'blacklisted' => true,
					'html' 				=> $format !== 'plain',
					'format' 			=> $format,
					'formatSet' 	=> isset( $meta[$key]['format'] ),
					// The floor \Nino\Text::entries() itself lands on for an
					// empty value, so an un-ignored key keeps the same counter
					// it had a moment before
					'maxlength' 	=> $meta[$key]['maxlength'] ?? 150,
					'maxlengthSet' => isset( $meta[$key]['maxlength'] ),
					'values' 			=> $values,
				];
			}

			return $entries;
		}

		/**
		 *	Whether a key exists in the blacklist and nowhere else - see
		 *	_blacklistOnly(). Such a key is still editable here (un-ignoring
		 *	and deleting are the two things that have to work on it), it just
		 *	has no value to migrate or remove
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *
		 *	@return 	bool
		 */
		private static function _isBlacklistOnly( array &$appData, string $key ): bool {
			return \Nino\Text::entry( $appData, $key ) === null && isset( \Nino\Text::blacklist( $appData )[$key] ) === true;
		}

		/**
		 *	Edit an existing key's global/per-locale shape, blacklist status,
		 *	format and limit. Converting shape migrates the current value(s)
		 *	instead of discarding them: global -> per-locale copies the one value
		 *	into every locale; per-locale -> global keeps the native
		 *	locale's value (falling back to the first non-empty one).
		 *
		 *	The format ('auto' or one of \Nino\Html::FORMATS) and the limit (an
		 *	int, null or '' for automatic) are optional: a request that leaves
		 *	one out leaves it as it is - the two checkboxes of the form post
		 *	without them. A format that is set converts every stored value to it
		 *	(see _convertFormat()); 'auto' only forgets the choice, the values
		 *	stay as they are. A limit below the longest text the key holds is
		 *	refused: the editor would cut the text at it on the next keystroke
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= (string) ( $data['key'] ?? '' );
			$isGlobal = ( $data['global'] ?? false ) === true;
			$blacklisted = ( $data['blacklisted'] ?? false ) === true;

			$formatPosted = array_key_exists( 'format', $data );
			$limitPosted 	= array_key_exists( 'maxlength', $data );
			$format 			= self::_postedFormat( $data );
			$limit 				= self::_postedLimit( $data );

			$entry = \Nino\Text::entry( $appData, $key );

			// A key the scan form retired has no value anywhere, so
			// \Nino\Text::entry() does not know it - but unticking "hidden" on
			// it is precisely how it comes back, and that is a save like any
			// other. There is simply no shape to convert
			if( $entry === null && self::_isBlacklistOnly( $appData, $key ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown key' );
				return;
			}

			if( $format === false ) {
				\Nino\Http::fail( $request, 400, 'unknown format', 'keys_format', [], 'format' );
				return;
			}

			if( $limit === false ) {
				\Nino\Http::fail( $request, 400, 'invalid limit', 'keys_limit', [ \Nino\Text::MAX_LIMIT ], 'maxlength' );
				return;
			}

			if( $limit !== null && $entry !== null ) {

				$longest = 0;
				foreach( $entry['values'] as $value )
					$longest = max( $longest, \Nino\Text::visibleLength( (string) $value ) );

				if( $limit < $longest ) {
					\Nino\Http::fail( $request, 400, 'limit below the current text', 'keys_limit_short', [ $longest ], 'maxlength' );
					return;
				}
			}

			if( $entry !== null && $entry['global'] !== $isGlobal )
				self::_convertShape( $appData, $key, $entry, $isGlobal );

			if( $entry !== null && $format !== null && $format !== $entry['format'] )
				self::_convertFormat( $appData, $key, $isGlobal, $format );

			if( $formatPosted === true || $limitPosted === true ) {

				// What the request leaves out stays as the file has it - decided
				// under the file's own lock, not from a read made before it
				$changes = [];

				if( $formatPosted === true )
					$changes['format'] = $format;

				if( $limitPosted === true )
					$changes['maxlength'] = $limit;

				if( \Nino\Text::updateMeta( $appData, $key, $changes ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not save the format' );
					return;
				}
			}

			\Nino\Text::setBlacklisted( $appData, $key, $blacklisted );

			\Nino\Http::ok( $request );
		}

		/**
		 *	The format a request names: a name from \Nino\Html::FORMATS, null for
		 *	'auto' or when it names none, false for anything else
		 *
		 *	@param		array 		$data					The posted data
		 *
		 *	@return 	string|false|null
		 */
		private static function _postedFormat( array $data ): string|false|null {

			$format = $data['format'] ?? null;

			if( $format === null || $format === '' || $format === 'auto' )
				return null;

			return ( is_string( $format ) === true && in_array( $format, \Nino\Html::FORMATS, true ) === true ) ? $format : false;
		}

		/**
		 *	The limit a request names: a whole number from 1 to
		 *	\Nino\Text::MAX_LIMIT, null for none (automatic), false for anything else
		 *
		 *	@param		array 		$data					The posted data
		 *
		 *	@return 	int|false|null
		 */
		private static function _postedLimit( array $data ): int|false|null {

			$limit = $data['maxlength'] ?? null;

			if( $limit === null || $limit === '' )
				return null;

			if( is_string( $limit ) === true && ctype_digit( $limit ) === true )
				$limit = (int) $limit;

			return ( is_int( $limit ) === true && $limit >= 1 && $limit <= \Nino\Text::MAX_LIMIT ) ? $limit : false;
		}

		/**
		 *	Bring a key's stored value(s) into a format, after somebody chose
		 *	it: through \Nino\Text::sanitizeValue(), so a value that went from
		 *	paragraphs to plain text keeps its words and its lines, and one that
		 *	went from plain text to line breaks gets its newlines as <br>.
		 *	Same migrate-don't-discard reasoning as _convertShape(), and one lock
		 *	per file the same way
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *	@param		bool			$isGlobal			Where the key lives now
		 *	@param		string		$format				One of \Nino\Html::FORMATS
		 *
		 *	@return 	void
		 */
		private static function _convertFormat( array &$appData, string $key, bool $isGlobal, string $format ): void {

			$bracketKey = '[['. $key. ']]';
			$files 			= $isGlobal === true ? [ '/text/global.php' ] : array_map( fn( string $locale ): string => '/text/'. $locale. '.php', \Nino\Locales::getAvailableLocales( $appData ) );

			foreach( $files as $file )
				\Nino\Filesystem::mutate( $appData, $file, function( array $content ) use ( $bracketKey, $format ): ?array {

					if( is_scalar( $content[$bracketKey] ?? null ) === false )
						return null;

					$value = \Nino\Text::sanitizeValue( (string) $content[$bracketKey], $format );

					if( $value === (string) $content[$bracketKey] )
						return null;

					$content[$bracketKey] = $value;

					return $content;
				} );
		}

		/**
		 *	Save several keys' values in one request - see \Nino\Text::saveBatch().
		 *	Unlike the Text panel, a blacklisted key is still a valid save target here -
		 *	blacklist only hides a key from the Text panel, not from this one.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSaveBatch( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$items 	= is_array( $data['items'] ?? null ) ? $data['items'] : [];

			\Nino\Http::ok( $request, [ 'results' => \Nino\Text::saveBatch( $appData, $items, true ) ] );
		}

		/**
		 *	Rename a key, moving its current value(s) and blacklist status
		 *	to the new name - the file(s)/shape don't change, only the
		 *	bracket key itself.
		 *
		 *	The new name has to follow the grammar (see apiCreate()). A key
		 *	of the system - /_nino/..., named after a page's Element-URI or a
		 *	language's code - and one of the workbench - /_admin/... - is not
		 *	renamed here: its name is what the code that reads it asks for,
		 *	only its value is editable. Every other key, however it is formed,
		 *	can be renamed to one that follows the grammar
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRename( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$key 		= (string) ( $data['key'] ?? '' );
			$newKey = (string) ( $data['newKey'] ?? '' );

			if( \Nino\Text::isGrammarKey( $newKey ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid new key', 'keys_invalid', [ $newKey ], 'newKey' );
				return;
			}

			if( str_starts_with( $key, '/_nino/' ) === true || str_starts_with( $key, '/_admin/' ) === true ) {
				\Nino\Http::fail( $request, 400, 'a key of the system is not renamed', 'keys_system', [ $key ], 'key' );
				return;
			}

			$entry 			= \Nino\Text::entry( $appData, $key );
			$valueless 	= ( $entry === null && self::_isBlacklistOnly( $appData, $key ) === true );

			if( $entry === null && $valueless === false ) {
				\Nino\Http::fail( $request, 404, 'unknown key' );
				return;
			}

			// Renaming a key to the name it already has is the no-op the
			// collision check below already treats it as - but it has to
			// return before the mutate further down, whose "write the new
			// key, unset the old one" pair collapses into a plain delete
			// when both brackets are the same string. This tab's own
			// assets/keys.js guards it in the ui (see _renameKey()); the
			// endpoint has to guard it too, or a
			// direct post drops the value from every locale file and still
			// answers 200
			if( $newKey === $key ) {
				\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $newKey ] );
				return;
			}

			if( \Nino\Text::entry( $appData, $newKey ) !== null ) {
				\Nino\Http::fail( $request, 409, 'key already exists', 'keys_exists', [ $newKey ], 'newKey' );
				return;
			}

			// A retired key is only a line in the blacklist: moving it is the
			// whole rename. Writing through the locale files as below would
			// create the empty key in every one of them, which is the opposite
			// of what retiring it meant
			if( $valueless === true ) {
				\Nino\Text::setBlacklisted( $appData, $key, false );
				\Nino\Text::setBlacklisted( $appData, $newKey, true );
				\Nino\Text::moveMeta( $appData, $key, $newKey );
				\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $newKey ] );
				return;
			}

			$oldBracket = '[['. $key. ']]';
			$newBracket = '[['. $newKey. ']]';

			if( $entry['global'] === true ) {
				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $oldBracket, $newBracket ): array {
					$global[$newBracket] = $global[$oldBracket] ?? '';
					unset( $global[$oldBracket] );
					return $global;
				} );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $oldBracket, $newBracket ): array {
						$localeData[$newBracket] = $localeData[$oldBracket] ?? '';
						unset( $localeData[$oldBracket] );
						return $localeData;
					} );
			}

			if( $entry['blacklisted'] === true ) {
				\Nino\Text::setBlacklisted( $appData, $key, false );
				\Nino\Text::setBlacklisted( $appData, $newKey, true );
			}

			\Nino\Text::moveMeta( $appData, $key, $newKey );

			\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $newKey ] );
		}

		/**
		 *	Delete a key entirely - its value(s) from global.php or every
		 *	locale file, and its blacklist entry if any
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$key 	= (string) ( $data['key'] ?? '' );

			$entry = \Nino\Text::entry( $appData, $key );

			// Nothing but a blacklist line to remove - and removing it is a
			// real deletion here, since the scan will offer the key again the
			// next time a template still asks for it
			if( $entry === null ) {

				if( self::_isBlacklistOnly( $appData, $key ) === false ) {
					\Nino\Http::fail( $request, 404, 'unknown key' );
					return;
				}

				\Nino\Text::setBlacklisted( $appData, $key, false );
				\Nino\Text::setMeta( $appData, $key, null, null );
				\Nino\Http::ok( $request );
				return;
			}

			$bracketKey = '[['. $key. ']]';

			if( $entry['global'] === true ) {
				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey ): array {
					unset( $global[$bracketKey] );
					return $global;
				} );
			} else {
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey ): array {
						unset( $localeData[$bracketKey] );
						return $localeData;
					} );
			}

			if( $entry['blacklisted'] === true )
				\Nino\Text::setBlacklisted( $appData, $key, false );

			\Nino\Text::setMeta( $appData, $key, null, null );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Move a key's current value(s) between global.php and every
		 *	locale file - see apiSave()'s docblock for the exact migration
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *	@param		array 		$entry					This key's current entry (see \Nino\Text::entries())
		 *	@param		bool			$toGlobal				Target shape
		 *
		 *	@return 	void
		 */
		private static function _convertShape( array &$appData, string $key, array $entry, bool $toGlobal ): void {

			$bracketKey = '[['. $key. ']]';
			$locales 		= \Nino\Locales::getAvailableLocales( $appData );

			if( $toGlobal === true ) {

				/*	Empty is nothing to keep, not the thing to keep. '??' only
					steps aside for a null, and a locale file that carries this
					key with an empty string is not null (see below) - so a key
					nobody has written in the project's own language yet, which
					is what every fresh translation looks like until somebody
					gets to it, converted to global as '' and threw away the one
					language that did have text. The fallback the docblock of
					apiSave() promises never ran for it.	*/
				$native = \Nino\Locales::getNativeLocale( $appData );
				$value 	= $entry['values'][$native] ?? '';

				// A locale missing this key entirely is null (see
				// \Nino\Text::entries()), not '' - both are excluded here, so
				// "the first non-empty value" doesn't pick a locale that never
				// had one
				if( $value === '' )
					$value = array_values( array_filter( $entry['values'], fn( $v ) => $v !== null && $v !== '' ) )[0] ?? '';

				foreach( $locales as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey ): array {
						unset( $localeData[$bracketKey] );
						return $localeData;
					} );

				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey, $value ): array {
					$global[$bracketKey] = $value;
					return $global;
				} );

			} else {

				$value = $entry['values']['*'] ?? '';

				\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ) use ( $bracketKey ): array {
					unset( $global[$bracketKey] );
					return $global;
				} );

				foreach( $locales as $locale )
					\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', function( array $localeData ) use ( $bracketKey, $value ): array {
						$localeData[$bracketKey] = $value;
						return $localeData;
					} );
			}
		}

		/**
		 *	Scan every public-site template (templates/*.tpl - not the workbench's
		 *	or _admin's own, those are separate text systems entirely) for
		 *	text keys that aren't yet defined for any locale -
		 *	the exact gap this module exists to close: designing a template,
		 *	inventing a key along the way, then forgetting to actually
		 *	add it anywhere. Doesn't write anything - apiScanApply() is what
		 *	turns an accepted result into a real key.
		 *
		 *	Every key a template reads and nothing defines is a row, whether it
		 *	can be created here or not (see _scanRow() for the three kinds). The
		 *	answer carries a second list as well, 'alsoUsed': a key of the form
		 *	/template/<category>/... that a template of another category reads
		 *	too. That is only a note - nothing is counted, offered or moved - for
		 *	the day somebody wants a word two templates share to live in
		 *	/template/common
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScan( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$scan = self::_scan( $appData, true );

			\Nino\Http::ok( $request, [ 'missing' => $scan['missing'], 'alsoUsed' => $scan['alsoUsed'] ] );
		}

		/**
		 *	Answer one pass of the scan form, row by row. Three outcomes, and
		 *	the row itself says which:
		 *
		 *	  - a row with a value becomes a key, with that value as its
		 *	    starting text in every language
		 *	  - a row left empty is passed over this once and nothing is
		 *	    written: the next scan offers it again, which is what makes
		 *	    working through a long list in several sittings possible
		 *	  - a row ticked "ignore" goes into /text/blacklist.php and stops
		 *	    being asked about at all - it leaves this scan, the Dashboard
		 *	    tile and the Text panel together
		 *
		 *	Only keys the scan itself currently reports are accepted. The
		 *	form's rows come from apiScan() and nowhere else, so anything
		 *	beyond them is a posted key this screen never offered - and
		 *	blacklisting or overwriting an existing key is not what this
		 *	action is for.
		 *
		 *	And only what the row's kind allows: a key is created only where the
		 *	grammar lets it be, so a value posted for any other is passed over;
		 *	a key off the grammar can still be retired; a key of the system
		 *	(/_nino/...) is neither created nor retired - it is the system's to
		 *	write, and ignoring it would hide a gap that only a page or a
		 *	language can close
		 *
		 *	Retiring a key is reversible: the Text Keys list shows it (see
		 *	_blacklistOnly()) and unticking "hidden" there brings it back.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScanApply( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$rows = is_array( $data['rows'] ?? null ) ? $data['rows'] : [];

			$missing = [];
			foreach( self::_scanMissing( $appData ) as $entry )
				$missing[$entry['key']] = $entry['kind'];

			$created = 0;
			$ignored = 0;
			$skipped = 0;

			foreach( $rows as $row ) {

				$key = is_array( $row ) === true ? (string) ( $row['key'] ?? '' ) : '';

				if( isset( $missing[$key] ) === false || $missing[$key] === 'system' )
					continue;

				if( ( $row['ignore'] ?? false ) === true ) {
					\Nino\Text::setBlacklisted( $appData, $key, true );
					$ignored++;
					continue;
				}

				// Trimmed, because a value of nothing but spaces is the same
				// "I have not decided yet" an empty field is
				$value = trim( (string) ( $row['value'] ?? '' ) );

				if( $value === '' || $missing[$key] !== 'create' ) {
					$skipped++;
					continue;
				}

				self::_writeKey( $appData, $key, false, $value );
				$created++;
			}

			\Nino\Http::ok( $request, [ 'created' => $created, 'ignored' => $ignored, 'skipped' => $skipped ] );
		}

		/**
		 *	How many missing keys apiScan() above would currently
		 *	report as missing - shared by \Nino\Modules\Dashboard\Admin::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function missingCount( array &$appData ): int {
			return count( self::_scanMissing( $appData ) );
		}

		/**
		 *	Scan every public-site template for text keys that
		 *	aren't yet defined for any locale - the actual work behind
		 *	apiScan()/missingCount() above
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										The rows of _scan()
		 */
		private static function _scanMissing( array &$appData ): array {
			return self::_scan( $appData )['missing'];
		}

		/**
		 *	Read the templates once and answer both lists the scan has.
		 *
		 *	A key counts when a template reads it as a textfill and no text file, runtime
		 *	fill or blacklist line knows it. Only the innermost [[...]] of a
		 *	nested fill is seen, as a static scan can see no more, and only the
		 *	ones with a leading slash: [[name]] in mail-user.tpl and [[.rel]] in
		 *	Posts' page-post.tpl are placeholders the code of the template's own
		 *	shortcode fills in, not keys, and are no gap in any text file.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$alsoUsed			Whether to work out 'alsoUsed' too, which the Dashboard count has no use for
		 *
		 *	@return 	array										[ 'missing' => [ row, ... ], 'alsoUsed' => [ [ 'key', 'files' ], ... ] ], see _scanRow()
		 */
		private static function _scan( array &$appData, bool $alsoUsed = false ): array {

			$known 	= [];
			$stored = [];
			foreach( \Nino\Text::entries( $appData ) as $entry )
				$known[$entry['key']] = $stored[$entry['key']] = true;
			// The fills the kernel injects at request time (see
			// \Nino\Html::bootFills() and requestFills()) are never stored in
			// any /text/*.php file, so the scan would flag them as missing
			// forever. Read from the kernel, which states them once: a copy
			// kept here fell one behind and reported the clean uri. Only the
			// innermost, non-nested [[...]] a static regex scan can even see
			// (the [[/nino/http/response/uri]] inside html-header.tpl's
			// [[/_nino/webpage[[/nino/http/response/uri]]/title]]) - the outer,
			// dynamically built key is not something a scan of the raw source
			// can resolve, since its final shape depends on the page rendering
			foreach( \Nino\Html::runtimeFillKeys( $appData ) as $key )
				$known[$key] = true;
			// A retired key is usually in the blacklist and in no text file at
			// all (apiScanApply() writes no value for it), so without this it
			// would be reported again on the very next scan - which is the
			// thing "ignore" exists to stop. It also takes blacklisted keys
			// that do have a value out of the count: a key deliberately hidden
			// from the Text panel is not a gap anyone means to close
			foreach( array_keys( \Nino\Text::blacklist( $appData ) ) as $key )
				$known[$key] = true;

			$found 	= [];
			$usedIn = [];

			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [] as $file ) {

				// A directory that is called x.tpl is no template
				$content = is_file( $file ) === true ? file_get_contents( $file ) : false;
				if( $content === false || preg_match_all( '/\[\[([^\[\]]+)\]\]/', $content, $matches ) === false )
					continue;

				$category = \Nino\Modules\Template::category( basename( $file ) );

				foreach( array_unique( $matches[1] ) as $key ) {

					// Not a key at all: a placeholder the template's own shortcode fills
					if( str_starts_with( $key, '/' ) === false )
						continue;

					if( isset( $known[$key] ) === false ) {
						$found[$key][] = basename( $file );
						continue;
					}

					// A word of one template that another reads as well - by
					// the rule a word several templates read lives in
					// /template/common, which holds from the day the key is
					// created and no day after, so this is a note and no more
					if( $alsoUsed === true && isset( $stored[$key] ) === true
						&& preg_match( '#^/template/([^/]+)/#', $key, $owner ) === 1 && $owner[1] !== 'common' && $owner[1] !== $category )
						$usedIn[$key][] = basename( $file );
				}
			}

			ksort( $found );
			ksort( $usedIn );

			// Asked once for every row rather than for each: the writer of a
			// page's keys is the Routes panel, if there is one
			$runtimeUris = class_exists( '\\Nino\\Modules\\Routes\\Admin' ) === true
				? array_column( \Nino\Modules\Routes\Admin::runtimeRoutes( $appData ), 'uri' )
				: null;

			return [
				'missing' 	=> array_map( fn( $key, $files ) => self::_scanRow( (string) $key, $files, $runtimeUris ), array_keys( $found ), array_values( $found ) ),
				'alsoUsed' 	=> array_map( fn( $key, $files ) => [ 'key' => (string) $key, 'files' => $files ], array_keys( $usedIn ), array_values( $usedIn ) ),
			];
		}

		/**
		 *	One row of the scan: the key, the templates that read it, and what
		 *	may be done about it here. Three kinds:
		 *
		 *	  - 'create': it follows the grammar (\Nino\Text::isGrammarKey()), so a
		 *	    value turns it into a key. When it is a /feature or /module key
		 *	    the row also carries 'hint' ('feature' or 'module') and 'owner'
		 *	    (the feature's key, the module's directory): such a key normally
		 *	    belongs to that feature or module, whose install unit delivers
		 *	    it - if it is missing, the feature may not be active, or the key
		 *	    misspelled
		 *	  - 'system': it is a /_nino key, named after a page or a language
		 *	    and written by the system. No input and no ignoring; 'writer'
		 *	    says who writes it - 'routes', 'language' - or is null where
		 *	    nobody does, and the template's reading is what should change
		 *	  - 'grammar': it follows no grammar - a key of the workbench, one of
		 *	    an older form, one invented in a template. Not created here; the
		 *	    template should read a key that follows the grammar. It can be
		 *	    ignored for good, like any other
		 *
		 *	@param		string		$key
		 *	@param		array 		$files				The templates that read it
		 *	@param		array|null	$runtimeUris	The Element-URIs of the pages features route (see \Nino\Modules\Routes\Admin::runtimeRoutes()), null where the Routes panel is not there
		 *
		 *	@return 	array										[ 'key', 'files', 'kind', 'writer', 'hint', 'owner' ]
		 */
		private static function _scanRow( string $key, array $files, ?array $runtimeUris ): array {

			$row = [ 'key' => $key, 'files' => $files, 'kind' => 'create', 'writer' => null, 'hint' => null, 'owner' => null ];

			if( \Nino\Text::isGrammarKey( $key ) === true ) {

				if( preg_match( '#^/(feature|module)/([^/]+)/#', $key, $origin ) === 1 ) {
					$row['hint'] 	= $origin[1];
					$row['owner'] = $origin[2];
				}

				return $row;
			}

			if( str_starts_with( $key, '/_nino/' ) === false ) {
				$row['kind'] = 'grammar';
				return $row;
			}

			$row['kind'] = 'system';

			// Read from the right: an Element-URI may hold slashes and dots
			if( preg_match( '#^/_nino/webpage(/.+)/(name|title|description|uri)$#', $key, $page ) === 1 ) {

				// The path of a feature's page is the feature's: no panel writes it
				if( $runtimeUris !== null && ( $page[2] !== 'uri' || in_array( $page[1], $runtimeUris, true ) === false ) )
					$row['writer'] = 'routes';

			} elseif( preg_match( '#^/_nino/locale/[^/]+/name$#', $key ) === 1 && class_exists( '\\Nino\\Modules\\Language\\Admin' ) === true ) {
				$row['writer'] = 'language';
			}

			return $row;
		}
	}
}
