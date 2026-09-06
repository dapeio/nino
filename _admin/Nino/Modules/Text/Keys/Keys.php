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
		 *	A text key follows the same "/segment/segment" shape the Text panel's
		 *	Text panel groups by (first segment = category)
		 *
		 *	@param		string		$key
		 *
		 *	@return 	bool
		 */
		private static function isValidKey( string $key ): bool {
			return preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z0-9_-]+)+$#', $key ) === 1;
		}

		/**
		 *	List every known text key, blacklisted or not (unlike the Text panel's
		 *	own panel, this is exactly where you'd come to un-blacklist one)
		 *	- see \Nino\Text::entries()
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
			] );
		}

		/**
		 *	Create a brand new key with an initial value - global.php gets
		 *	one value, or every locale file gets the same starting value,
		 *	depending on $isGlobal
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

			if( self::isValidKey( $key ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid key' );
				return;
			}

			if( \Nino\Text::entry( $appData, $key ) !== null ) {
				\Nino\Http::fail( $request, 409, 'key already exists' );
				return;
			}

			self::_writeKey( $appData, $key, $isGlobal, $value );

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
		 *	@param		string		$key					A key that passed isValidKey()
		 *	@param		bool			$isGlobal			Whether it lives in global.php
		 *	@param		string		$value				The starting value
		 *
		 *	@return 	void
		 */
		private static function _writeKey( array &$appData, string $key, bool $isGlobal, string $value ): void {

			$bracketKey = '[['. $key. ']]';

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

			foreach( array_keys( \Nino\Text::blacklist( $appData ) ) as $key ) {

				if( isset( $known[$key] ) === true )
					continue;

				$entries[] = [
					'key' 				=> $key,
					'global' 			=> false,
					'blacklisted' => true,
					'html' 				=> false,
					// The floor \Nino\Text::entries() itself lands on for an
					// empty value, so an un-ignored key keeps the same counter
					// it had a moment before
					'maxlength' 	=> 150,
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
		 *	Edit an existing key's global/per-locale shape and/or blacklist
		 *	status. Converting shape migrates the current value(s) instead
		 *	of discarding them: global -> per-locale copies the one value
		 *	into every locale; per-locale -> global keeps the native
		 *	locale's value (falling back to the first non-empty one)
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

			$entry = \Nino\Text::entry( $appData, $key );

			// A key the scan form retired has no value anywhere, so
			// \Nino\Text::entry() does not know it - but unticking "hidden" on
			// it is precisely how it comes back, and that is a save like any
			// other. There is simply no shape to convert
			if( $entry === null && self::_isBlacklistOnly( $appData, $key ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown key' );
				return;
			}

			if( $entry !== null && $entry['global'] !== $isGlobal )
				self::_convertShape( $appData, $key, $entry, $isGlobal );

			\Nino\Text::setBlacklisted( $appData, $key, $blacklisted );

			\Nino\Http::ok( $request );
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
		 *	bracket key itself
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

			if( self::isValidKey( $newKey ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid new key' );
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
			// when both brackets are the same string. _admin/assets/text.js
			// guards this in the ui; the endpoint has to guard it too, or a
			// direct post drops the value from every locale file and still
			// answers 200
			if( $newKey === $key ) {
				\Nino\Http::ok( $request, [ 'ok' => true, 'key' => $newKey ] );
				return;
			}

			if( \Nino\Text::entry( $appData, $newKey ) !== null ) {
				\Nino\Http::fail( $request, 409, 'key already exists' );
				return;
			}

			// A retired key is only a line in the blacklist: moving it is the
			// whole rename. Writing through the locale files as below would
			// create the empty key in every one of them, which is the opposite
			// of what retiring it meant
			if( $valueless === true ) {
				\Nino\Text::setBlacklisted( $appData, $key, false );
				\Nino\Text::setBlacklisted( $appData, $newKey, true );
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

				// A locale missing this key entirely is null (see
				// \Nino\Text::entries()), not '' - both are excluded here, so
				// "the first non-empty value" doesn't pick a locale that never
				// had one
				$native = \Nino\Locales::getNativeLocale( $appData );
				$value 	= $entry['values'][$native] ?? ( array_values( array_filter( $entry['values'], fn( $v ) => $v !== null && $v !== '' ) )[0] ?? '' );

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
		 *	A handful of fills the kernel itself injects at request time
		 *	(Nino::request()'s Html::addFills() call) - never stored in any
		 *	/text/*.php file, so apiScan() below would otherwise flag them as
		 *	"missing" forever. Only the innermost, non-nested [[...]] a
		 *	static regex scan can even see (eg. the [[/nino/http/response/
		 *	uri]] inside html-header.tpl's [[/webpage[[/nino/http/response/
		 *	uri]]/title]]) - the outer, dynamically-constructed key itself
		 *	isn't something a scan of the raw template source can resolve at
		 *	all, since its final shape depends on which page is rendering
		 */
		private const array KERNEL_FILLS = [
			'/nino/http/request/uri', '/nino/http/response/uri', '/nino/http/response/locale',
			'/nino/auth/user', '/nino/dir', '/nino/public', '/date/year',
		];

		/**
		 *	Scan every public-site template (templates/*.tpl - not the workbench's
		 *	or _admin's own, those are separate text systems entirely) for
		 *	[[/key]] placeholders that aren't yet defined for any locale -
		 *	the exact gap this module exists to close: designing a template,
		 *	inventing a [[/key]] along the way, then forgetting to actually
		 *	add it anywhere. Doesn't write anything - apiCreate() (already
		 *	existing) is what turns an accepted result into a real key
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScan( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'missing' => self::_scanMissing( $appData ) ] );
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
				$missing[$entry['key']] = true;

			$created = 0;
			$ignored = 0;
			$skipped = 0;

			foreach( $rows as $row ) {

				$key = is_array( $row ) === true ? (string) ( $row['key'] ?? '' ) : '';

				if( isset( $missing[$key] ) === false )
					continue;

				if( ( $row['ignore'] ?? false ) === true ) {
					\Nino\Text::setBlacklisted( $appData, $key, true );
					$ignored++;
					continue;
				}

				// Trimmed, because a value of nothing but spaces is the same
				// "I have not decided yet" an empty field is
				$value = trim( (string) ( $row['value'] ?? '' ) );

				if( $value === '' ) {
					$skipped++;
					continue;
				}

				self::_writeKey( $appData, $key, false, $value );
				$created++;
			}

			\Nino\Http::ok( $request, [ 'created' => $created, 'ignored' => $ignored, 'skipped' => $skipped ] );
		}

		/**
		 *	How many [[/key]] placeholders apiScan() above would currently
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
		 *	Scan every public-site template for [[/key]] placeholders that
		 *	aren't yet defined for any locale - the actual work behind
		 *	apiScan()/missingCount() above
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'key', 'files' ], ... ]
		 */
		private static function _scanMissing( array &$appData ): array {

			$known = [];
			foreach( \Nino\Text::entries( $appData ) as $entry )
				$known[$entry['key']] = true;
			foreach( self::KERNEL_FILLS as $key )
				$known[$key] = true;
			// A retired key is usually in the blacklist and in no text file at
			// all (apiScanApply() writes no value for it), so without this it
			// would be reported again on the very next scan - which is the
			// thing "ignore" exists to stop. It also takes blacklisted keys
			// that do have a value out of the count: a key deliberately hidden
			// from the Text panel is not a gap anyone means to close
			foreach( array_keys( \Nino\Text::blacklist( $appData ) ) as $key )
				$known[$key] = true;

			$found = [];

			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [] as $file ) {

				$content = file_get_contents( $file );
				if( $content === false || preg_match_all( '/\[\[([^\[\]]+)\]\]/', $content, $matches ) === false )
					continue;

				foreach( array_unique( $matches[1] ) as $key ) {

					if( isset( $known[$key] ) === true || self::isValidKey( $key ) === false )
						continue;

					$found[$key] 			= $found[$key] ?? [];
					$found[$key][] 		= basename( $file );
				}
			}

			ksort( $found );

			return array_map( fn( $key, $files ) => [ 'key' => $key, 'files' => $files ], array_keys( $found ), array_values( $found ) );
		}
	}
}
