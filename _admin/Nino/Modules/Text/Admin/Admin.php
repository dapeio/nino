<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Text\Admin	Text panel: the values behind the text keys
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Text {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Text							Text editor: edits the site's [[key]] textfill values in
	 *											/text/global.php and /text/{locale}.php. The set of known keys
	 *											is developer-owned - this only ever edits existing key values,
	 *											never creates/removes keys. Keys listed in /text/blacklist.php
	 *											(technical values like uris, colors, typography) are hidden
	 *											entirely, since they aren't really "content" and editing them
	 *											could break routing/navigation/design rather than just copy.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/text/manage';

		/**
		 *	One permission per key, the key's own path appended:
		 *
		 *	  /_admin/text/update/page-home/atf/title
		 *
		 *	so '/_admin/text/update/page-home/*' is a whole group and
		 *	'/_admin/text/update/*' every key there is - the same
		 *	\Nino\Auth::checkPermission() wildcard as everywhere else, and
		 *	nothing here has to enumerate keys. Whether any of it applies is
		 *	\Nino\Admin\Admin::scoped()'s call: a role holding none of these
		 *	keeps what MANAGE_PERM has always meant, every key of every group.
		 */
		public const string SCOPE 		= '/_admin/text/';
		public const string UPDATE_PERM = '/_admin/text/update';

		public static function actions(): array {
			return [
				'text/keys' 			=> [ self::class, 'apiKeys' ],
				'text/savebatch' 	=> [ self::class, 'apiSaveBatch' ],
			];
		}

		public static function nav(): array {
			return [ 'text', '/_admin/nav/text', 30, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-text-initial-icon lucide-text-initial"><path d="M15 5h6"/><path d="M15 12h6"/><path d="M3 19h18"/><path d="m3 12 3.553-7.724a.5.5 0 0 1 .894 0L11 12"/><path d="M3.92 10h6.16"/></svg>';
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'text-list', 'text-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
				'/_admin/assets/html-editor.js',
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// The keys behind these values are edited on a tab of this same
		// pane (see \Nino\Admin\Panels::collect()), with its own permission
		public static function tabs(): array {
			return [ Keys::class ];
		}

		public static function log( string $action, array $data ): string {
			return $action === 'text/savebatch'
				? 'Edit Text /'. self::_category( is_array( $data['items'] ?? null ) ? $data['items'] : [] )
				: '';
		}

		/**
		 *	The category a batch of text/savebatch items belongs to - the
		 *	first non-empty path segment of a key (eg. "home" for
		 *	"/home/welcome/h2"), same grouping text.js itself uses to
		 *	present keys as one category's worth of fields per form.
		 *	A batch is always one category's fields saved together, so
		 *	the first item's key is representative of the whole request
		 *
		 *	@param		array 		$items				The request's "items" array
		 *
		 *	@return 	string
		 */
		private static function _category( array $items ): string {

			$parts = array_values( array_filter( explode( '/', (string) ( $items[0]['key'] ?? '' ) ) ) );

			return $parts[0] ?? '-';
		}

		/**
		 *	List every editable key with its current value(s), whether it's a
		 *	global (locale-independent) or per-locale key, whether it currently
		 *	holds markup (so the editor offers the html editor for it) and a
		 *	maxlength derived from the longest current value. Blacklisted keys
		 *	(technical values, not content) are hidden entirely - unlike _admin's
		 *	own text editor, this one only ever edits existing key values,
		 *	never sees the blacklist itself.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiKeys( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			// Each key says whether this account may write it, so the form can
			// show what it may not change as read-only instead of taking edits
			// the save would refuse (see mayUpdate())
			$keys = [];
			foreach( \Nino\Text::entries( $appData, false ) as $entry ) {
				$entry['writable'] = self::mayUpdate( $appData, $entry['key'] );
				$keys[] = $entry;
			}

			\Nino\Http::ok( $request, [
				'keys' 					=> $keys,
				'locales' 			=> \Nino\Locales::getAvailableLocales( $appData ),
				'selectedLocale' => \Nino\Admin\Admin::sessionLocale( $appData ),
			] );
		}

		/**
		 *	Save several keys' values in one request (a whole category's
		 *	worth of fields, all posted together) - see \Nino\Text::saveBatch()
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

			// A key this account may not write never reaches \Nino\Text, and
			// says so in its own result row rather than failing the whole
			// batch: the rest of the category is a legitimate save, and the
			// form already reports per key what became of it
			$allowed 	= [];
			$results 	= [];

			foreach( $items as $item ) {

				$key = (string) ( ( is_array( $item ) === true ? $item['key'] : null ) ?? '' );

				if( self::mayUpdate( $appData, $key ) === false ) {
					$results[$key] = [ 'ok' => false, 'error' => 'not allowed' ];
					continue;
				}

				$allowed[] = $item;
			}

			\Nino\Http::ok( $request, [ 'results' => $results + \Nino\Text::saveBatch( $appData, $allowed, false ) ] );
		}

		/**
		 *	Whether this account may change one key's value - see SCOPE
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					A text key, leading slash and all (eg. "/page-home/atf/title")
		 *
		 *	@return 	bool
		 */
		public static function mayUpdate( array &$appData, string $key ): bool {
			return \Nino\Admin\Admin::scoped( $appData, self::SCOPE, self::UPDATE_PERM. $key );
		}

	}
}
