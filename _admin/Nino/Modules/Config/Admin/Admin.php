<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Config\Admin	Config panel: the technical values of config.php
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Config {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								The project's settings, as a form rather than as raw json:
	 *												error handling, the workbench features that can be
	 *												switched off, and the page cache. Deliberately excludes
	 *												"hard" values a wrong edit could brick the whole site
	 *												over - modules, filesystem/dir, locales/textfiles - those
	 *												stay a by-hand, deliberate-only task. Two groups this
	 *												used to hold have moved next to what they are about: the
	 *												login throttle to the Users panel (see Lockout) and the
	 *												site's languages to the Language panel. The validation
	 *												they share with this panel lives in the shell
	 *												(\Nino\Admin\Admin::cleanInt(), typeError()), so no
	 *												panel depends on another one being delivered.
	 *
	 *												Three keys this used to edit are gone from here because
	 *												they now have real editors of their own, and a second,
	 *												unvalidated way to write the same data is a way to
	 *												corrupt it: '/nino/http/routes' belongs to Pages,
	 *												'/nino/html/navs' to the Navigation module's own
	 *												Navigations panel, and '/nino/html/assets'
	 *												is a build concern - its order is load-bearing for the css
	 *												cascade, which a json textarea shows nobody, so it stays a
	 *												deliberate config.php edit (or the Design panel's Theme
	 *												step) rather than a field that looks safe to change.
	 *
	 *												'/nino/html/images' and '/nino/auth/user' were never part of
	 *												this either, for the same reason: both get their own richer
	 *												editors (Images and Users below).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/config/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		// Every setting this panel offers, in render order: the type its value
		// has to have, the group it renders under, and the copy that explains
		// it - as fill keys into _admin/text/<locale>.php, so the form reads
		// in the interface language. The type is what apiSave() validates
		// against before anything is written - a value of the wrong shape
		// would otherwise silently corrupt that key for the rest of the site.
		//
		// 'min'/'max' bound an int field. They are not cosmetic: a cache ttl
		// of 0 would render every page twice, once to store and once to
		// serve - a value reachable by typing a plausible-looking number, so
		// the bound belongs next to the field rather than in a comment
		// somewhere (see Lockout for the two the login throttle keeps).
		private const array FIELDS = [
			'/nino/error/log' => [
				'type' 	=> 'bool',
				'group'	=> 'diagnostics',
				'label'	=> '/_admin/config/label/errorlog',
				'hint' 	=> '/_admin/config/hint/errorlog',
			],
			'/nino/error/display' => [
				'type' 	=> 'bool',
				'group'	=> 'diagnostics',
				'label'	=> '/_admin/config/label/errordisplay',
				'hint' 	=> '/_admin/config/hint/errordisplay',
			],
			'/nino/session/force-secure-cookie' => [
				'type' 	=> 'bool',
				'group'	=> 'diagnostics',
				'label'	=> '/_admin/config/label/securecookie',
				'hint' 	=> '/_admin/config/hint/securecookie',
			],
			// Both flags are read by the workbench (\Nino\Modules\Backups::maybeRun(), \Nino\Modules\Logs\Admin::record()),
			// which is also where the keys are named after.
			'/nino/admin/backups' => [
				'type' 	=> 'bool',
				'group'	=> 'editor',
				'label'	=> '/_admin/config/label/backups',
				'hint' 	=> '/_admin/config/hint/backups',
			],
			'/nino/admin/logs' => [
				'type' 	=> 'bool',
				'group'	=> 'editor',
				'label'	=> '/_admin/config/label/logs',
				'hint' 	=> '/_admin/config/hint/logs',
			],
			'/nino/cache/status' => [
				'type' 	=> 'bool',
				'group'	=> 'cache',
				'label'	=> '/_admin/config/label/cachestatus',
				'hint' 	=> '/_admin/config/hint/cachestatus',
			],
			'/nino/cache/ttl' => [
				'type' 	=> 'int',
				'group'	=> 'cache',
				'min' 	=> 10,
				'max' 	=> 2592000,
				'unit' 	=> 'seconds',
				'label'	=> '/_admin/config/label/cachettl',
				'hint' 	=> '/_admin/config/hint/cachettl',
			],
			'/nino/cache/blacklist' => [
				'type' 	=> 'lines',
				'group'	=> 'cache',
				'label'	=> '/_admin/config/label/cacheblacklist',
				'hint' 	=> '/_admin/config/hint/cacheblacklist',
			],
		];

		// Group key -> heading + intro (fill keys), in render order
		private const array GROUPS = [
			'diagnostics'	=> [ '/_admin/config/group/diagnostics', '/_admin/config/intro/diagnostics' ],
			'editor' 			=> [ '/_admin/config/group/editor', '/_admin/config/intro/editor' ],
			'cache' 			=> [ '/_admin/config/group/cache', '/_admin/config/intro/cache' ],
		];

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'config/list' 			=> [ self::class, 'apiList' ],
				'config/save' 			=> [ self::class, 'apiSave' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'config', '/_admin/nav/config', 20, 'system' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sliders-horizontal-icon lucide-sliders-horizontal"><path d="M10 5H3"/><path d="M12 19H3"/><path d="M14 3v4"/><path d="M16 17v4"/><path d="M21 12h-9"/><path d="M21 19h-5"/><path d="M21 5h-7"/><path d="M8 10v4"/><path d="M8 12H3"/></svg>';
		}

		public static function panes(): array {
			return [ 'config-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'config/save' => 'Edit Config '. implode( ', ', array_keys( is_array( $data['fields'] ?? null ) ? $data['fields'] : [] ) ),
				default 			=> '',
			};
		}

		/**
		 *	Every setting's current value, plus the schema the frontend renders
		 *	it with.
		 *
		 *	Reads config.php fresh rather than $appData directly: by the time
		 *	this runs, \Nino\Admin\Admin::init() has already added _admin's own GET/POST
		 *	/_admin route into $appData['/nino/http/routes'] at runtime (same
		 *	as \Nino\Admin\Admin::init() does, self-registered, never
		 *	persisted - see \Nino\Admin\Admin::init()'s docblock). That no longer matters
		 *	for routes, which this panel stopped editing, but the same applies
		 *	to anything a module writes at runtime, so the fresh read stays.
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
			foreach( self::FIELDS as $key => $field ) {

				$fields[] = $field + [
					'key' 		=> $key,
					'value'		=> self::_currentValue( $stored, $key, $field['type'] ),
				];
			}

			\Nino\Http::ok( $request, [
				'groups' 	=> self::GROUPS,
				'fields' 	=> $fields,
			] );
		}

		/**
		 *	One field's value as stored, falling back to the same default the
		 *	runtime itself applies when the key is missing.
		 *
		 *	@param		array 		$stored				config.php as read from disk
		 *	@param		string		$key
		 *	@param		string		$type					FIELDS type
		 *
		 *	@return 	mixed
		 */
		private static function _currentValue( array $stored, string $key, string $type ): mixed {

			if( array_key_exists( $key, $stored ) === true )
				return $stored[$key];

			// The runtime's own fallback for a missing key, so the form never
			// shows a value the site is not actually running with
			return match( $key ) {
				'/nino/error/log' 									=> true,
				'/nino/admin/backups', '/nino/admin/logs' => true,
				'/nino/cache/ttl' 									=> 3600,
				default 														=> match( $type ) {
					'bool' 								=> false,
					'int' 								=> 0,
					'lines' 							=> [],
					default 							=> '',
				},
			};
		}

		/**
		 *	Save the whole form in one write.
		 *
		 *	One request for every field rather than one per key, and one
		 *	writeContentData() call for all of them: a settings form is saved
		 *	as a unit. Nothing is written until every field validates, so a
		 *	single bad value leaves config.php exactly as it was.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$posted = $data['fields'] ?? null;

			if( is_array( $posted ) === false ) {
				\Nino\Http::fail( $request, 400, 'no fields posted' );
				return;
			}

			$clean = [];
			foreach( self::FIELDS as $key => $field ) {

				// A field the frontend did not send keeps whatever config.php
				// currently holds - this must never read as "set it to empty"
				if( array_key_exists( $key, $posted ) === false )
					continue;

				$value = self::_cleanValue( $posted[$key], $field );

				if( $value === null ) {
					\Nino\Http::fail( $request, 400, \Nino\Admin\Admin::typeError( $key, $field ) );
					return;
				}

				$clean[$key] = $value;
			}

			if( $clean === [] ) {
				\Nino\Http::fail( $request, 400, 'no known fields posted' );
				return;
			}

			foreach( $clean as $key => $value )
				$appData[$key] = $value;

			\Nino\AppData::writeContentData( $appData, array_keys( $clean ) );

			\Nino\Http::ok( $request, [ 'saved' => array_keys( $clean ) ] );
		}

		/**
		 *	Coerce and validate one posted value against its field definition.
		 *
		 *	Coerce, because a form posts strings: a number field's "5" is the
		 *	int 5 and a switch's "true" is the bool true, both of which
		 *	config.php has to receive as the real type - a "5" written into
		 *	'/nino/auth/maxtries' compares differently everywhere it is used.
		 *	Anything that is not exactly one of the accepted forms is rejected
		 *	rather than cast, so a typo cannot become a 0.
		 *
		 *	@param		mixed			$value				As posted
		 *	@param		array			$field				Its FIELDS entry
		 *
		 *	@return 	mixed										The clean value, or null if it does not validate
		 */
		private static function _cleanValue( mixed $value, array $field ): mixed {

			return match( $field['type'] ) {

				'bool' => match( true ) {
					is_bool( $value ) 												=> $value,
					$value === 'true', $value === 1, $value === '1' 	=> true,
					$value === 'false', $value === 0, $value === '0' => false,
					default 																	=> null,
				},

				'int' => \Nino\Admin\Admin::cleanInt( $value, $field ),

				'lines' => self::_cleanLines( $value ),

				default => null,
			};
		}

		/**
		 *	A list typed one entry per line. Blank lines and surrounding
		 *	whitespace go, because they are how a textarea ends up looking
		 *	after editing and none of them mean anything to a consumer.
		 *
		 *	@param		mixed			$value				As posted - a list, or the raw textarea string
		 *
		 *	@return 	array|null							The cleaned list, or null if it is not a list of strings
		 */
		private static function _cleanLines( mixed $value ): ?array {

			if( is_string( $value ) === true )
				$value = preg_split( '/\r\n|\r|\n/', $value );

			if( is_array( $value ) === false )
				return null;

			$lines = [];
			foreach( $value as $line ) {

				if( is_string( $line ) === false )
					return null;

				$line = trim( $line );

				if( $line !== '' && in_array( $line, $lines, true ) === false )
					$lines[] = $line;
			}

			return $lines;
		}
	}
}
