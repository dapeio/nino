<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Features\Admin	Features panel: the installed features, switched on and off, and their settings
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Features {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Every feature installed below features/ (see
	 *												\Nino\Features), one block each: what it is, whether
	 *												it is switched on, what stands in the way of switching
	 *												it on, and the settings its manifest declares, as a
	 *												form. The kernel class does the work - discovery,
	 *												validation, the install unit, the module list, the
	 *												record in config.php - and this panel only drives it
	 *												and phrases the answers for the browser: names,
	 *												descriptions, labels and option labels are localized
	 *												here, once, in the interface language, so the script
	 *												renders what it gets.
	 *
	 *												One pane, one script-built tab strip: Available (what
	 *												the catalogue offers that is not already current -
	 *												install or update), Inactive and Active. apiList()
	 *												answers the installed features and, alongside them,
	 *												the catalogue as \Nino\Catalogue::cached() last left
	 *												it on disk - so the Available tab fills without a
	 *												request the moment the panel opens. Only the panel's
	 *												own Refresh action (apiCatalogue()) ever fetches; the
	 *												offers themselves are always recomputed against the
	 *												features on disk now, so an install since the last
	 *												fetch is reflected without a new one.
	 *
	 *												A secret never travels to the browser. The list says
	 *												whether one is stored, the form shows an empty
	 *												password input, and posting it empty keeps the value
	 *												(the kernel's rule, see Features::validateSettings()).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/features/manage';

		// A feature key as the kernel spells it - checked here before the
		// kernel is asked, so a stray value never reaches an error message
		private const string KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';

		// A version as the catalogue names one - the same shape the kernel
		// accepts in a manifest (Features::VERSION_PATTERN)
		private const string VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/';

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
				'features/list' 			=> [ self::class, 'apiList' ],
				'features/activate' 	=> [ self::class, 'apiActivate' ],
				'features/deactivate'	=> [ self::class, 'apiDeactivate' ],
				'features/settings' 	=> [ self::class, 'apiSettings' ],
				'features/catalogue' 	=> [ self::class, 'apiCatalogue' ],
				'features/install' 		=> [ self::class, 'apiInstall' ],
			];
		}

		/**
		 *	Nav entry for this module - a system panel between Backups (10)
		 *	and Config (20)
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'features', '/_admin/nav/features', 15, 'system' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-blocks-icon lucide-blocks"><path d="M10 22V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v11a3 3 0 0 0 3 3h15a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1H10"/><rect x="14" y="2" width="8" height="8" rx="1"/></svg>';
		}

		// One pane: a tab strip and an action bar the script builds, then the
		// content of whichever tab is current
		public static function panes(): array {
			return [ 'features-list' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The module's own words, one <locale>.php per interface language
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	The dashboard tile: how many features are switched on
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										{ value, label }
		 */
		public static function summary( array &$appData ): array {

			$active = 0;

			foreach( \Nino\Features::all( $appData ) as $feature )
				if( $feature['active'] === true )
					$active++;

			return [ 'value' => $active, 'label' => '/_admin/features/label/active' ];
		}

		public static function log( string $action, array $data ): string {

			$key 		 = is_string( $data['key'] ?? null ) === true ? $data['key'] : '';
			$version = is_string( $data['version'] ?? null ) === true ? $data['version'] : '';

			return match( $action ) {
				'features/activate' 	=> 'Activate feature "'. $key. '"',
				'features/deactivate'	=> 'Deactivate feature "'. $key. '"',
				'features/settings' 	=> 'Edit settings of feature "'. $key. '"',
				'features/install' 		=> 'Install feature "'. $key. '" '. $version,
				default 							=> '',
			};
		}

		/**
		 *	Every installed feature with its state and its settings form, the
		 *	directory they are read from, the configured catalogue url ('' when
		 *	switched off - the Available tab's own empty state, and the reason
		 *	the panel offers no Refresh button) and whether the features
		 *	directory is writable, and the last cached catalogue, if there is
		 *	one - so the Available tab fills without a request of its own,
		 *	see \Nino\Catalogue::cached()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$locale 	= \Nino\Admin\Admin::sessionLocale( $appData );
			$features	= [];

			foreach( \Nino\Features::all( $appData ) as $feature )
				$features[] = self::_entry( $appData, $feature, $locale );

			\Nino\Http::ok( $request, [
				'dir'					=> self::_dir(),
				'catalogueUrl'	=> \Nino\Catalogue::url( $appData ),
				'writable'		=> \Nino\Catalogue::writable(),
				'catalogue'		=> self::_cachedCatalogue( $appData, $locale ),
				'features'		=> $features,
			] );
		}

		/**
		 *	Switch a feature on - or, for an active one whose manifest moved
		 *	ahead of the recorded version, apply the update (the kernel's one
		 *	step for both, see Features::activate())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiActivate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			self::_switch( $appData, $request, true );
		}

		/**
		 *	Switch a feature off - its class leaves the module list, everything
		 *	it keeps stays
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDeactivate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			self::_switch( $appData, $request, false );
		}

		/**
		 *	Save one feature's settings form in one write. The kernel checks
		 *	every field before it accepts any, so a single bad value leaves
		 *	config.php as it was - and every rejected field is named in the
		 *	answer, so the form can be fixed in one go
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSettings( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$key 		= self::_key( $data );
			$fields	= $data['fields'] ?? null;

			if( $key === null || \Nino\Features::get( $appData, $key ) === null ) {
				\Nino\Http::fail( $request, 400, 'unknown feature' );
				return;
			}

			if( is_array( $fields ) === false ) {
				\Nino\Http::fail( $request, 400, 'no fields posted' );
				return;
			}

			$errors = \Nino\Features::saveSettings( $appData, $key, $fields );

			if( $errors !== [] ) {

				// A whole-form error (the kernel's key '') is its message
				// alone; a field's is named, so the form can point at it
				$messages = [];
				foreach( $errors as $name => $message )
					$messages[] = (string) $name === '' ? (string) $message : $name. ': '. $message;

				\Nino\Http::fail( $request, 400, implode( '; ', $messages ) );
				return;
			}

			self::_answer( $appData, $request, $key );
		}

		/**
		 *	Refresh the catalogue - two requests to its url, believed only
		 *	with the signature (see Catalogue::fetch()) - and answer what it
		 *	offers this installation, phrased for the browser. Only ever on
		 *	request: the panel opens from \Nino\Catalogue::cached() alone (see
		 *	apiList()), and the script posts this when Refresh catalogue is
		 *	pressed. A successful fetch leaves the cache fresh on disk too, so
		 *	the next apiList() needs no request of its own.
		 *
		 *	The two ways the configuration rules it out are said in the
		 *	interface language; every other reason is the kernel's own English
		 *	sentence, behind a phrase of ours
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCatalogue( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			if( \Nino\Catalogue::url( $appData ) === '' ) {
				\Nino\Http::fail( $request, 400, self::_say( $appData, '/_admin/features/error/catalogue-off', 'the catalogue is switched off' ) );
				return;
			}

			if( \Nino\Catalogue::key( $appData ) === '' ) {
				\Nino\Http::fail( $request, 400, self::_say( $appData, '/_admin/features/error/catalogue-key', 'no catalogue key is configured, so no catalogue can be trusted' ) );
				return;
			}

			$catalogue = \Nino\Catalogue::fetch( $appData );

			if( is_string( $catalogue ) === true ) {
				\Nino\Http::fail( $request, 400, self::_say( $appData, '/_admin/features/error/catalogue-reason', $catalogue ) );
				return;
			}

			$locale	= \Nino\Admin\Admin::sessionLocale( $appData );
			$cached	= \Nino\Catalogue::cached( $appData );

			\Nino\Http::ok( $request, [
				'url'				=> $catalogue['url'],
				'generated'	=> $catalogue['generated'],
				'fetched'		=> self::_fetched( $cached['fetched'] ?? time() ),
				'writable'	=> \Nino\Catalogue::writable(),
				'offers'		=> self::_offers( $appData, $catalogue, $locale ),
			] );
		}

		/**
		 *	Install one catalogue entry: the kernel downloads, verifies and
		 *	places the directory (see Catalogue::install()) and activates
		 *	nothing. A feature that was active before is then activated again,
		 *	which is how its update is applied (Features::activate()); one
		 *	that was off, or new, is only put in place and waits in the list
		 *	for its Activate. Answers the feature's entry as the list would
		 *	show it now, and whether the update was applied
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiInstall( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$key 			= self::_key( $data );
			$version	= self::_version( $data );

			if( $key === null || $version === null ) {
				\Nino\Http::fail( $request, 400, 'no feature key and version posted' );
				return;
			}

			// Read before the directory changes: whether it is on now decides
			// what happens after the files are in place
			$before 		= \Nino\Features::get( $appData, $key );
			$wasActive	= $before !== null && $before['active'] === true;

			$result = \Nino\Catalogue::install( $appData, $key, $version );

			if( $result !== true ) {
				\Nino\Http::fail( $request, 400, $result );
				return;
			}

			if( $wasActive === true ) {

				$result = \Nino\Features::activate( $appData, $key );

				// The directory is already the new one - the answer has to say
				// so, since the list will show the update still waiting
				if( $result !== true ) {
					\Nino\Http::fail( $request, 400, self::_say( $appData, '/_admin/features/error/update-after-install', $result ) );
					return;
				}
			}

			$feature = \Nino\Features::get( $appData, $key );

			if( $feature === null ) {
				\Nino\Http::fail( $request, 500, 'feature "'. $key. '" disappeared' );
				return;
			}

			\Nino\Http::ok( $request, [
				'feature'	=> self::_entry( $appData, $feature, \Nino\Admin\Admin::sessionLocale( $appData ) ),
				'updated'	=> $wasActive,
			] );
		}

		/**
		 *	The shared half of activate/deactivate: a known key, the kernel's
		 *	verdict as a 400 when it refuses, else the refreshed entry
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		bool			$on						Activate (true) or deactivate
		 *
		 *	@return 	void
		 */
		private static function _switch( array &$appData, array &$request, bool $on ): void {

			$key = self::_key( \Nino\Admin\Admin::postData() );

			if( $key === null || \Nino\Features::get( $appData, $key ) === null ) {
				\Nino\Http::fail( $request, 400, 'unknown feature' );
				return;
			}

			$result = $on === true ? \Nino\Features::activate( $appData, $key ) : \Nino\Features::deactivate( $appData, $key );

			if( $result !== true ) {
				\Nino\Http::fail( $request, 400, $result );
				return;
			}

			self::_answer( $appData, $request, $key );
		}

		/**
		 *	Answer with one feature's entry as it reads now - after an
		 *	activation the registry was re-read, after a settings save the
		 *	values are read live
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$key					Feature key
		 *
		 *	@return 	void
		 */
		private static function _answer( array &$appData, array &$request, string $key ): void {

			$feature = \Nino\Features::get( $appData, $key );

			if( $feature === null ) {
				\Nino\Http::fail( $request, 500, 'feature "'. $key. '" disappeared' );
				return;
			}

			\Nino\Http::ok( $request, [ 'feature' => self::_entry( $appData, $feature, \Nino\Admin\Admin::sessionLocale( $appData ) ) ] );
		}

		/**
		 *	The catalogue as \Nino\Catalogue::cached() last left it on disk,
		 *	phrased the way apiCatalogue() phrases a fresh one - so the
		 *	Available tab renders identically whether it filled from the
		 *	cache on open or from a Refresh just now
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$locale				The interface language
		 *
		 *	@return 	array|null							{ url, fetched, offers } or null when nothing is cached
		 */
		private static function _cachedCatalogue( array &$appData, string $locale ): ?array {

			$cached = \Nino\Catalogue::cached( $appData );

			if( $cached === null )
				return null;

			return [
				'url'			=> $cached['url'],
				'fetched'	=> self::_fetched( $cached['fetched'] ),
				'offers'	=> self::_offers( $appData, $cached, $locale ),
			];
		}

		/**
		 *	The offers of a parsed catalogue (Catalogue::fetch()'s or
		 *	Catalogue::cached()'s - both carry 'features'), phrased for the
		 *	browser: names and descriptions localized, the extension list
		 *	flattened to 'ext'. Recomputed against Features::all() every time,
		 *	so an install or activation since the catalogue was last fetched
		 *	is reflected without a new request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue
		 *	@param		string		$locale				The interface language
		 *
		 *	@return 	array
		 */
		private static function _offers( array &$appData, array $catalogue, string $locale ): array {

			$offers = [];

			foreach( \Nino\Catalogue::offers( $appData, $catalogue ) as $offer )
				$offers[] = [
					'key'					=> $offer['key'],
					'name'				=> \Nino\Features::localized( $offer['name'], $locale ),
					'description'	=> \Nino\Features::localized( $offer['description'], $locale ),
					'version'			=> $offer['version'],
					'nino'				=> $offer['nino'],
					'ext'					=> $offer['php']['ext'],
					'requires'		=> $offer['requires'],
					'directory'		=> $offer['directory'],
					'archive'			=> $offer['archive'],
					'size'				=> $offer['size'],
					'released'		=> $offer['released'],
					'state'				=> $offer['state'],
					'fits'				=> $offer['fits'],
					'local'				=> $offer['local'],
					'active'			=> $offer['active'],
				];

			return $offers;
		}

		/**
		 *	A unix time as the panel shows it - the "Catalogue as of %s" line,
		 *	same rule as a backup date: formatted once here, in the interface
		 *	the browser gets a string to display, not a timestamp to format
		 *
		 *	@param		int				$timestamp
		 *
		 *	@return 	string
		 */
		private static function _fetched( int $timestamp ): string {

			return date( 'Y-m-d H:i', $timestamp );
		}

		/**
		 *	One feature the way the script renders it: the manifest's words in
		 *	the interface language, the state, and the settings schema as a
		 *	list of fields with their current values
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$feature			An entry of Features::all()
		 *	@param		string		$locale				The interface language
		 *
		 *	@return 	array
		 */
		private static function _entry( array &$appData, array $feature, string $locale ): array {

			$values 	= \Nino\Features::settings( $appData, $feature['key'] );
			$settings	= [];

			foreach( $feature['settings'] as $name => $schema ) {

				$field = [
					'name'			=> $name,
					'type'			=> $schema['type'],
					'label'			=> \Nino\Features::localized( $schema['label'], $locale ),
					'hint'			=> \Nino\Features::localized( $schema['hint'], $locale ),
					'required'	=> $schema['required'],
					'min'				=> $schema['min'] ?? null,
					'max'				=> $schema['max'] ?? null,
					'maxlength'	=> $schema['maxlength'] ?? null,
					'unit'			=> $schema['unit'] ?? '',
					'options'		=> [],
				];

				foreach( $schema['options'] ?? [] as $value => $label )
					$field['options'][] = [ 'value' => $value, 'label' => \Nino\Features::localized( $label, $locale ) ];

				// A secret stays on the server: the form learns whether one
				// is stored and nothing more
				if( $schema['type'] === 'secret' )
					$field['set'] = is_string( $values[$name] ?? null ) === true && $values[$name] !== '';
				else
					$field['value'] = $values[$name] ?? null;

				$settings[] = $field;
			}

			return [
				'key'					=> $feature['key'],
				'name'				=> \Nino\Features::localized( $feature['name'], $locale ),
				'description'	=> \Nino\Features::localized( $feature['description'], $locale ),
				'version'			=> $feature['version'],
				'installed'		=> $feature['installed'],
				'active'			=> $feature['active'],
				'update'			=> $feature['update'],
				'requires'		=> $feature['requires'],
				'problems'		=> $feature['problems'],
				'settings'		=> $settings,
			];
		}

		/**
		 *	The posted feature key, or null when it is not one
		 *
		 *	@param		array 		$data					The posted payload
		 *
		 *	@return 	string|null
		 */
		private static function _key( array $data ): ?string {

			$key = $data['key'] ?? null;

			return is_string( $key ) === true && preg_match( self::KEY_PATTERN, $key ) === 1 ? $key : null;
		}

		/**
		 *	The posted version, or null when it is not one
		 *
		 *	@param		array 		$data					The posted payload
		 *
		 *	@return 	string|null
		 */
		private static function _version( array $data ): ?string {

			$version = $data['version'] ?? null;

			return is_string( $version ) === true && preg_match( self::VERSION_PATTERN, $version ) === 1 ? $version : null;
		}

		/**
		 *	A message this panel phrases itself, in the interface language:
		 *	the fill's text, with the kernel's reason in its %s where it has
		 *	one. The panel's words are read the way the shell reads them for
		 *	the browser (Admin::textFills()); where they cannot be read, the
		 *	reason alone is the answer, so it is never empty
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$fill					A key of the panel's text files, eg. '/_admin/features/error/catalogue-off'
		 *	@param		string		$reason				The kernel's own sentence
		 *
		 *	@return 	string
		 */
		private static function _say( array &$appData, string $fill, string $reason ): string {

			$fills = \Nino\Admin\Admin::textFills( $appData, self::text(), \Nino\Admin\Admin::sessionLocale( $appData ) );
			$text	 = $fills['[['. $fill. ']]'] ?? '';

			if( is_string( $text ) === false || $text === '' )
				return $reason;

			return str_replace( '%s', $reason, $text );
		}

		/**
		 *	The features directory as the panel names it: project-relative
		 *	where it is inside the project, the absolute path where
		 *	NINO_FEATURES_DIR moved it out
		 *
		 *	@return 	string
		 */
		private static function _dir(): string {

			$dir = \Nino\Features::dir();
			$relative = \Nino\Admin\Panels::relative( $dir );

			return $relative !== '' ? $relative : $dir;
		}
	}
}
