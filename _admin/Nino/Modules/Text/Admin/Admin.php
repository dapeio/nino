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
		 *	  /_admin/text/update/template/page-home/hero/title
		 *
		 *	so '/_admin/text/update/template/page-home/*' is a whole template's worth and
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
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/textkeys.js' ),
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
				? 'Edit Text '. self::_groups( is_array( $data['items'] ?? null ) ? $data['items'] : [] )
				: '';
		}

		/**
		 *	The groups a batch of text/savebatch items belongs to, each once and
		 *	in the order the batch names them, joined by ", " - what the log
		 *	line says. A group is what the form that sent the batch is a row of:
		 *	/<namespace>/<category> for a key of the grammar (eg. "/template/page-home"),
		 *	/_nino/webpage<uri> for the details of a page, /_nino/locale for the
		 *	name of a language, the first segment for any other key. A page's
		 *	form saves its template's texts and its details together, so the
		 *	line names both: "/template/page-home, /_nino/webpage/home"
		 *
		 *	@param		array 		$items				The request's "items" array
		 *
		 *	@return 	string
		 */
		private static function _groups( array $items ): string {

			$groups = [];

			foreach( $items as $item ) {

				$key = is_array( $item ) === true ? (string) ( $item['key'] ?? '' ) : '';

				if( preg_match( '#^/_nino/webpage(/.+)/(?:name|title|description|uri)$#', $key, $page ) === 1 )
					$group = '/_nino/webpage'. $page[1];
				elseif( preg_match( '#^/(?:template|project|feature|module)/[^/]+#', $key, $grammar ) === 1 )
					$group = $grammar[0];
				elseif( preg_match( '#^/_nino/locale/#', $key ) === 1 )
					$group = '/_nino/locale';
				else
					$group = '/'. ( array_values( array_filter( explode( '/', $key ) ) )[0] ?? '-' );

				$groups[$group] = true;
			}

			return implode( ', ', array_keys( $groups ) ) ?: '/-';
		}

		/**
		 *	List every editable key with its current value(s), whether it's a
		 *	global (locale-independent) or per-locale key, whether it currently
		 *	holds markup (so the editor offers the html editor for it) and a
		 *	maxlength derived from the longest current value. Blacklisted keys
		 *	(technical values, not content) are hidden entirely - unlike _admin's
		 *	own text editor, this one only ever edits existing key values,
		 *	never sees the blacklist itself - and so are the words of the
		 *	workbench, /_admin/..., which are not the site's text.
		 *
		 *	Besides the keys, what the form needs to arrange them (see
		 *	assets/textkeys.js):
		 *
		 *	  - order		key => where a template first reads it, a number that only
		 *							says which of two keys of a row comes first (see _order())
		 *	  - pages		the stored routes that are pages, each with the template it
		 *							shows and that template's category and name; null where
		 *							the Routes panel is not there
		 *	  - templates	category => { file, name } of every template with a category,
		 *							the name from its <!-- nino:template-name --> line, else null
		 *	  - features	key => name, the name in the manifest in the language of the
		 *							workbench
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

				if( str_starts_with( $entry['key'], '/_admin/' ) === true )
					continue;

				$entry['writable'] = self::mayUpdate( $appData, $entry['key'] );
				$keys[] = $entry;
			}

			\Nino\Http::ok( $request, [
				'keys' 					=> $keys,
				'locales' 			=> \Nino\Locales::getAvailableLocales( $appData ),
				'selectedLocale' => \Nino\Admin\Admin::sessionLocale( $appData ),
				'order' 				=> self::_order( $appData, array_column( $keys, 'key' ) ),
				'pages' 				=> self::_pages( $appData ),
				'templates' 		=> self::_templates( $appData ),
				'features' 			=> self::_features( $appData ),
			] );
		}

		/**
		 *	Where the templates first read each key, as one number per key: the
		 *	form puts the sections and the fields of a row in the order of a
		 *	template, which is the order a page shows them in.
		 *
		 *	The template that reads a key is the one of its category for a
		 *	/template key (the project's, for /template/common); the templates of the feature or the module, then the
		 *	project's, for a /feature or /module key; the project's for any
		 *	other. A key none of them reads literally - one put together at
		 *	runtime, one PHP or a script asks for - has no number, and the form
		 *	puts it after the others. The number is the position in that
		 *	list of templates times ten million plus the offset in the file, so
		 *	it only compares keys that are looked up the same way - which is all
		 *	one row holds
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$keys					The keys to look for
		 *
		 *	@return 	array										key => number, only for the keys a template reads
		 */
		private static function _order( array &$appData, array $keys ): array {

			$root 		= \Nino\Filesystem::path( $appData, '/templates' );
			$project 	= glob( $root. '/*.tpl' ) ?: [];
			$features = \Nino\Features::all( $appData );
			$modules 	= [];
			$read 		= [];
			$order 		= [];

			sort( $project );

			foreach( glob( dirname( __DIR__, 5 ). '/_nino/Nino/Modules/*', GLOB_ONLYDIR ) ?: [] as $dir )
				$modules[strtolower( basename( $dir ) )] = $dir;

			foreach( $keys as $key ) {

				$files = $project;

				// /template/common belongs to no file of its own: every template reads it
				if( preg_match( '#^/template/([^/]+)/#', $key, $own ) === 1 && $own[1] !== 'common' )
					$files = is_file( $root. '/'. $own[1]. '.tpl' ) === true ? [ $root. '/'. $own[1]. '.tpl' ] : [];
				elseif( preg_match( '#^/feature/([^/]+)/#', $key, $feature ) === 1 && isset( $features[$feature[1]] ) === true )
					$files = array_merge( self::_templateFiles( $features[$feature[1]]['dir'] ), $project );
				elseif( preg_match( '#^/module/([^/]+)/#', $key, $module ) === 1 && isset( $modules[$module[1]] ) === true )
					$files = array_merge( self::_templateFiles( $modules[$module[1]] ), $project );

				foreach( $files as $index => $file ) {

					$read[$file] ??= self::_readKeys( $file );

					if( isset( $read[$file][$key] ) === true ) {
						$order[$key] = $index * 10000000 + $read[$file][$key];
						break;
					}
				}
			}

			return $order;
		}

		/**
		 *	The templates a module or a feature brings itself: its own
		 *	templates/ and the install/templates/ it copies into the project
		 *
		 *	@param		string		$dir					The module's or the feature's directory
		 *
		 *	@return 	array										File paths, each directory sorted
		 */
		private static function _templateFiles( string $dir ): array {

			$files = [];

			foreach( [ '/templates', '/install/templates' ] as $sub ) {
				$found = glob( $dir. $sub. '/*.tpl' ) ?: [];
				sort( $found );
				$files = array_merge( $files, $found );
			}

			return $files;
		}

		/**
		 *	The text keys a template reads literally as fills, and where each
		 *	is first read. Only the innermost fill of a nested one is seen, as in
		 *	the scan of the Keys tab
		 *
		 *	@param		string		$file
		 *
		 *	@return 	array										key => offset of its first occurrence
		 */
		private static function _readKeys( string $file ): array {

			$content = file_get_contents( $file );
			$keys 	 = [];

			if( $content === false || preg_match_all( '/\[\[(\/[^\[\]]+)\]\]/', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) === false )
				return [];

			foreach( $matches as $match )
				$keys[$match[1][0]] ??= $match[1][1];

			return $keys;
		}

		/**
		 *	The stored routes that are pages, with the template each shows, the
		 *	category of that template (null where its name is no word of a key,
		 *	as the legal page's, which picks its file by language) and the
		 *	name the template gives itself - the pages the Routes panel lists,
		 *	asked of it, and the ones it does not: a route to a template that has
		 *	no category (the demo catalogue's), with neither
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null								[ { uri, httpUri, template, category, templateName } ], null without the Routes panel
		 */
		private static function _pages( array &$appData ): ?array {

			if( class_exists( '\\Nino\\Modules\\Routes\\Admin' ) === false )
				return null;

			$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
			$pages 	= [];

			// No languages asked for: the names and titles are keys of the list
			foreach( \Nino\Modules\Routes\Admin::pages( $appData, $routes, [], [] ) as $page ) {

				$template = (string) $page['template'];

				$pages[] = [
					'uri' 					=> $page['uri'],
					'httpUri' 			=> $page['httpUri'],
					'template' 			=> $template,
					'category' 			=> $template !== '' ? \Nino\Modules\Template::category( $template ) : null,
					'templateName' 	=> $template !== '' ? self::_templateName( \Nino\Filesystem::path( $appData, '/templates' ). '/'. $template. '.tpl' ) : null,
				];
			}

			// A page the Routes panel does not list - no page-* template, and a
			// name that is no word of a key: the demo catalogue's - is still a
			// page of the site, and its details are its own
			foreach( $routes as $routeKey => $route ) {

				if( str_starts_with( (string) $routeKey, 'GET://' ) === false || \Nino\Modules\Routes\Admin::isPageRoute( (string) $routeKey, (array) $route ) === true )
					continue;

				if( preg_match( '~^\[template /templates/([A-Za-z0-9._-]+)\]$~', trim( (string) ( $route['body'] ?? '' ) ), $match ) !== 1 || \Nino\Modules\Template::category( $match[1] ) !== null )
					continue;

				$httpUri = substr( (string) $routeKey, strlen( 'GET:/' ) );

				$pages[] = [
					'uri' 					=> (string) ( $route['uri'] ?? $httpUri ),
					'httpUri' 			=> $httpUri,
					'template' 			=> $match[1],
					'category' 			=> null,
					'templateName' 	=> null,
				];
			}

			return $pages;
		}

		/**
		 *	Every template directly in templates/ whose name is a category, by
		 *	that category
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										category => { file, name }
		 */
		private static function _templates( array &$appData ): array {

			$templates = [];

			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [] as $file ) {

				$category = \Nino\Modules\Template::category( basename( $file ) );

				if( $category !== null )
					$templates[$category] = [ 'file' => basename( $file ), 'name' => self::_templateName( $file ) ];
			}

			return $templates;
		}

		/**
		 *	The name a template gives itself in its first line,
		 *	<!-- nino:template-name Home -->, as the Template Builder writes it
		 *
		 *	@param		string		$file
		 *
		 *	@return 	string|null
		 */
		private static function _templateName( string $file ): ?string {

			$head = is_file( $file ) === true ? file_get_contents( $file, false, null, 0, 300 ) : false;

			return ( $head !== false && preg_match( '~\A<!--[\t ]*nino:template-name[\t ]+([^\r\n<>]+?)[\t ]*-->~', $head, $match ) === 1 ) ? $match[1] : null;
		}

		/**
		 *	The name of every feature in the language of the workbench, by its key
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										key => name
		 */
		private static function _features( array &$appData ): array {

			$locale 	= \Nino\Admin\Admin::sessionLocale( $appData );
			$features = [];

			foreach( \Nino\Features::all( $appData ) as $key => $feature )
				$features[(string) $key] = \Nino\Features::localized( $feature['name'], $locale );

			return $features;
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
		 *	The scoped permissions this panel knows, as the tree the roles
		 *	form picks from (see \Nino\Modules\Users\Admin::scopeOptions()): per
		 *	group - the first segment of a key - one action, "change values",
		 *	whose own permission is the whole group and whose fields are the
		 *	group's keys, each by its key path. The values themselves are
		 *	never part of it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ { scope, door, label, areas } ] - see docs/recipes/admin-panel.md
		 */
		public static function scopes( array &$appData ): array {

			$groups = [];

			foreach( \Nino\Text::entries( $appData, false ) as $entry ) {

				$parts = array_values( array_filter( explode( '/', $entry['key'] ) ) );

				if( $parts === [] )
					continue;

				$groups[$parts[0]][] = [ 'id' => $entry['key'], 'label' => $entry['key'], 'perm' => self::UPDATE_PERM. $entry['key'] ];
			}

			ksort( $groups, SORT_STRING );

			$areas = [];

			foreach( $groups as $group => $fields )
				$areas[] = [
					'id' 			=> (string) $group,
					'label' 	=> '/'. $group,
					'actions' => [ [ 'id' => 'update', 'label' => '/_admin/text/scope/update', 'perm' => self::UPDATE_PERM. '/'. $group. '/*', 'fields' => $fields ] ],
				];

			return [ [ 'scope' => self::SCOPE, 'door' => self::MANAGE_PERM, 'label' => '/_admin/nav/text', 'areas' => $areas ] ];
		}

		/**
		 *	Whether this account may change one key's value - see SCOPE
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					A text key, leading slash and all (eg. "/template/page-home/hero/title")
		 *
		 *	@return 	bool
		 */
		public static function mayUpdate( array &$appData, string $key ): bool {
			return \Nino\Admin\Admin::scoped( $appData, self::SCOPE, self::UPDATE_PERM. $key );
		}

		/**
		 *	How many keys each language still has no text for: those with a
		 *	text in the native language and none, or an empty one, in the
		 *	other - the work the panel's own list shows as empty fields.
		 *	A language without a text file counts every such key, since its
		 *	pages render the raw keys. Hidden (blacklisted) keys and the
		 *	locale-independent ones are no translation work and stay out;
		 *	nothing is created and no language falls back to another here -
		 *	shared by \Nino\Modules\Dashboard\Admin::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										locale => count, only those with a count above 0
		 */
		public static function untranslatedCounts( array &$appData ): array {

			$native = \Nino\Locales::getNativeLocale( $appData );
			$counts = [];

			foreach( \Nino\Text::entries( $appData, false ) as $entry ) {

				if( $entry['global'] === true )
					continue;

				$source = $entry['values'][$native] ?? null;

				if( is_string( $source ) === false || $source === '' )
					continue;

				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {

					if( $locale === $native )
						continue;

					$value = $entry['values'][$locale] ?? null;

					if( $value === null || $value === '' )
						$counts[$locale] = ( $counts[$locale] ?? 0 ) + 1;
				}
			}

			return $counts;
		}

	}
}
