<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Language\Admin	Language panel: the site's languages, and Translations as its tab
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Language {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Language					Which languages the site serves and which one a visitor
	 *											gets before choosing, as a form - the two locale keys of
	 *											config.php, which constrain each other and are therefore
	 *											saved together. Adding a language writes its text file as
	 *											a translation skeleton (apiAddLocale()); translating it is
	 *											the Text panel's job, or the Translations tab's, which
	 *											sits on this same pane (see tabs()) because that is the
	 *											other half of the same workflow. Used to be a group of the
	 *											Config panel
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/language/manage';

		// The two settings, in render order - same shape as \Nino\Modules\Config\Admin::FIELDS.
		// Their values are not part of the schema: the language list comes
		// from the inventory (see _localeInventory()), the native language
		// with its own field. Label and hint are fill keys (_admin/text/),
		// resolved by the form in the interface language
		private const array FIELDS = [
			'/nino/locales/available' => [
				'type' 	=> 'locales',
				'label'	=> '/_admin/language/label/available',
				'hint' 	=> '/_admin/language/hint/available',
			],
			'/nino/locales/native' => [
				'type' 	=> 'native',
				'label'	=> '/_admin/language/label/native',
				'hint' 	=> '/_admin/language/hint/native',
			],
		];

		private const string INTRO = '/_admin/language/intro';

		// A locale id is exactly language_TERRITORY. Narrow on purpose: the
		// value becomes a filename (text/<locale>.php), so anything looser is a
		// path question, and every locale Nino itself ships uses this shape
		public const string LOCALE_PATTERN = '/^[a-z]{2}_[A-Z]{2}$/';

		// Display names for the locales that actually turn up in practice. A
		// code with no entry renders as itself - this is a convenience for
		// reading the list, never a whitelist of what may be added
		private const array LOCALE_NAMES = [
			'de_DE' => 'German (Germany)',			'de_AT' => 'German (Austria)',
			'de_CH' => 'German (Switzerland)',	'en_US' => 'English (US)',
			'en_GB' => 'English (UK)', 					'fr_FR' => 'French (France)',
			'it_IT' => 'Italian (Italy)', 			'es_ES' => 'Spanish (Spain)',
			'nl_NL' => 'Dutch (Netherlands)', 	'pl_PL' => 'Polish (Poland)',
			'pt_PT' => 'Portuguese (Portugal)',	'cs_CZ' => 'Czech (Czechia)',
			'da_DK' => 'Danish (Denmark)', 			'sv_SE' => 'Swedish (Sweden)',
		];

		public static function actions(): array {
			return [
				'language/list' 			=> [ self::class, 'apiList' ],
				'language/save' 			=> [ self::class, 'apiSave' ],
				'language/addlocale'	=> [ self::class, 'apiAddLocale' ],
			];
		}

		public static function nav(): array {
			return [ 'language', '/_admin/nav/language', 5, 'system' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-languages-icon lucide-languages"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>';
		}

		// What the strip calls this panel's own screen, beside Translations
		public static function tab(): string {
			return '/_admin/nav/languages';
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		// The project-wide translation hand-off is the other half of the
		// same workflow, so it is a tab of this pane - with its own
		// permission, see \Nino\Admin\Panels::collect()
		public static function tabs(): array {
			return [ Translations::class ];
		}

		public static function panes(): array {
			return [ 'language-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'language/save' 			=> 'Edit Languages',
				'language/addlocale'	=> 'Add Language '. ( $data['locale'] ?? '' ),
				default 							=> '',
			};
		}

		/**
		 *	The schema the form renders, the native language and the locale
		 *	inventory. Reads config.php fresh rather than $appData, for the
		 *	same reason \Nino\Modules\Config\Admin::apiList() does
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			$fields = [];
			foreach( self::FIELDS as $key => $field )
				$fields[] = $field + [
					'key' 	=> $key,
					'value'	=> $key === '/nino/locales/native' ? (string) ( $stored[$key] ?? '' ) : null,
				];

			\Nino\Http::ok( $request, [
				'intro' 	=> self::INTRO,
				'fields' 	=> $fields,
				'locales'	=> self::_localeInventory( $appData, $stored ),
			] );
		}

		/**
		 *	Every locale this project could sensibly list, and what actually
		 *	exists on disk for each one.
		 *
		 *	The union of two sources, not just the configured list: a
		 *	text/<locale>.php whose locale is not (or no longer) configured is
		 *	worth showing, because it is a language that has been translated and
		 *	is simply switched off. The other direction is the one that breaks a
		 *	site - a configured locale with no text file of its own renders every
		 *	per-locale fill as a raw [[key]], the exact case \Nino\Locales::init()
		 *	guards the native locale against - so 'hasText' is reported per entry
		 *	and the frontend says so before the language is ever saved.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$stored				config.php as read from disk
		 *
		 *	@return 	array										[ [ code, name, hasText, keys, active ], ... ]
		 */
		private static function _localeInventory( array &$appData, array $stored ): array {

			$configured = array_values( array_filter(
				(array) ( $stored['/nino/locales/available'] ?? [] ),
				fn( mixed $locale ): bool => is_string( $locale ) === true && preg_match( self::LOCALE_PATTERN, $locale ) === 1
			) );

			$textDir 	= (string) ( $stored['/nino/locales/textfiles'] ?? '/text' );
			$files 		= glob( \Nino\Filesystem::path( $appData, $textDir ). '/*.php' ) ?: [];

			$onDisk = [];
			foreach( $files as $file ) {
				$code = basename( $file, '.php' );
				if( preg_match( self::LOCALE_PATTERN, $code ) === 1 )
					$onDisk[$code] = $file;
			}

			$codes = array_values( array_unique( array_merge( $configured, array_keys( $onDisk ) ) ) );
			sort( $codes );

			$inventory = [];
			foreach( $codes as $code ) {

				$keys = 0;
				if( isset( $onDisk[$code] ) === true ) {
					$content 	= \Nino\Filesystem::getFileContent( $appData, $textDir. '/'. $code. '.php', [] );
					$keys 		= is_array( $content ) === true ? count( $content ) : 0;
				}

				$inventory[] = [
					'code' 		=> $code,
					'name' 		=> self::LOCALE_NAMES[$code] ?? $code,
					'hasText'	=> isset( $onDisk[$code] ),
					'keys' 		=> $keys,
					'active'	=> in_array( $code, $configured, true ),
				];
			}

			return $inventory;
		}

		/**
		 *	Create a new language's text file as a translation skeleton: every
		 *	key the native locale has, in its order, with empty values.
		 *
		 *	Without this, adding a language left the project in the one state
		 *	\Nino\Locales::init() warns about - a configured locale with no
		 *	text/<locale>.php renders every per-locale fill as a raw [[key]] -
		 *	and the only ways out were copying a file by hand or importing a
		 *	translation. \Nino\Text::saveBatch() cannot bootstrap one either:
		 *	it validates against the keys that already exist, so there is
		 *	nothing to save into an empty locale.
		 *
		 *	Empty values, not the native language's own: an untranslated key
		 *	has to be visibly untranslated. The Text panel lists a key with an
		 *	empty value as work to do, while a copied German sentence sitting
		 *	in the French file reads as finished and is what actually ships.
		 *
		 *	The language itself is not activated here. That is the form's
		 *	Save, together with the native language it has to agree with - so
		 *	a file created and then abandoned shows up next time as an
		 *	inactive language that already has its keys, which is a state the
		 *	inventory reports and one tick undoes.
		 *
		 *	An existing file is never overwritten. It is answered with its own
		 *	current key count instead: this is reachable from a button, and a
		 *	button must not be one click away from emptying a finished
		 *	translation.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiAddLocale( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$locale = (string) ( $data['locale'] ?? '' );

			if( preg_match( self::LOCALE_PATTERN, $locale ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'a language id looks like de_DE' );
				return;
			}

			$stored 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$textDir	= (string) ( $stored['/nino/locales/textfiles'] ?? '/text' );
			$native 	= (string) ( $stored['/nino/locales/native'] ?? '' );

			// Answered before the native locale is even looked at: this path
			// creates nothing, so a project whose native language is itself
			// unset must still be able to see what an existing file holds
			if( \Nino\Filesystem::fileExists( $appData, $textDir. '/'. $locale. '.php' ) === true ) {
				$existing = \Nino\Filesystem::getFileContent( $appData, $textDir. '/'. $locale. '.php', [] );
				\Nino\Http::ok( $request, [
					'locale' 	=> $locale,
					'created'	=> false,
					'keys' 		=> is_array( $existing ) === true ? count( $existing ) : 0,
				] );
				return;
			}

			if( preg_match( self::LOCALE_PATTERN, $native ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'no native language is configured to copy the keys from' );
				return;
			}

			if( $native === $locale ) {
				\Nino\Http::fail( $request, 400, 'the native language has no text file of its own to copy' );
				return;
			}

			$nativeContent = \Nino\Filesystem::getFileContent( $appData, $textDir. '/'. $native. '.php', [] );

			if( is_array( $nativeContent ) === false || $nativeContent === [] ) {
				\Nino\Http::fail( $request, 400, 'the native language '. $native. ' has no text file to copy the keys from' );
				return;
			}

			// array_fill_keys over array_keys, so the new file carries the
			// native one's key order - which is what makes the two readable
			// side by side, in the Text panel and in a diff alike
			$skeleton = array_fill_keys( array_keys( $nativeContent ), '' );

			if( \Nino\Filesystem::putFileContent( $appData, $textDir. '/'. $locale. '.php', $skeleton ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write '. $textDir. '/'. $locale. '.php' );
				return;
			}

			\Nino\Http::ok( $request, [
				'locale' 	=> $locale,
				'created'	=> true,
				'keys' 		=> count( $skeleton ),
				'from' 		=> $native,
			] );
		}

		/**
		 *	Save the form in one write: the native locale is only valid
		 *	against the language list being saved alongside it, so writing
		 *	them one at a time would mean a moment - or, if the second write
		 *	fails, permanently - where config.php names a native locale the
		 *	project does not have. Nothing is written until every field
		 *	validates, and a field the frontend did not send keeps what
		 *	config.php currently holds
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$posted = \Nino\Admin\Admin::postData()['fields'] ?? null;

			if( is_array( $posted ) === false ) {
				\Nino\Http::fail( $request, 400, 'no fields posted' );
				return;
			}

			$clean = [];
			foreach( self::FIELDS as $key => $field ) {

				if( array_key_exists( $key, $posted ) === false )
					continue;

				$value = $field['type'] === 'native'
					? ( ( is_string( $posted[$key] ) === true && preg_match( self::LOCALE_PATTERN, $posted[$key] ) === 1 ) ? $posted[$key] : null )
					: self::_cleanLocales( $posted[$key] );

				if( $value === null ) {
					\Nino\Http::fail( $request, 400, $key. ( $field['type'] === 'native' ? ': expected a locale id like de_DE' : ': expected a list of locale ids like de_DE' ) );
					return;
				}

				$clean[$key] = $value;
			}

			if( $clean === [] ) {
				\Nino\Http::fail( $request, 400, 'no known fields posted' );
				return;
			}

			// The two keys constrain each other, so they are checked against
			// the values being saved - not against what is on disk, which is
			// what they are about to stop being
			$locales = $clean['/nino/locales/available'] ?? ( $appData['/nino/locales/available'] ?? [] );
			$native 	= $clean['/nino/locales/native'] ?? ( $appData['/nino/locales/native'] ?? '' );

			if( isset( $clean['/nino/locales/available'] ) === true && $locales === [] ) {
				\Nino\Http::fail( $request, 400, 'at least one language is required' );
				return;
			}

			if( in_array( $native, $locales, true ) === false ) {
				\Nino\Http::fail( $request, 400, 'the native language must be one of the site\'s languages' );
				return;
			}

			foreach( $clean as $key => $value )
				$appData[$key] = $value;

			\Nino\AppData::writeContentData( $appData, array_keys( $clean ) );

			\Nino\Http::ok( $request, [ 'saved' => array_keys( $clean ) ] );
		}

		/**
		 *	@param		mixed			$value				As posted
		 *
		 *	@return 	array|null							Deduplicated locale list, or null if any entry is malformed
		 */
		private static function _cleanLocales( mixed $value ): ?array {

			if( is_array( $value ) === false )
				return null;

			$locales = [];
			foreach( $value as $locale ) {

				if( is_string( $locale ) === false || preg_match( self::LOCALE_PATTERN, $locale ) !== 1 )
					return null;

				if( in_array( $locale, $locales, true ) === false )
					$locales[] = $locale;
			}

			return $locales;
		}
	}
}
