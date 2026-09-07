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

			$key = is_string( $data['key'] ?? null ) === true ? $data['key'] : '';

			return match( $action ) {
				'features/activate' 	=> 'Activate feature "'. $key. '"',
				'features/deactivate'	=> 'Deactivate feature "'. $key. '"',
				'features/settings' 	=> 'Edit settings of feature "'. $key. '"',
				default 							=> '',
			};
		}

		/**
		 *	Every installed feature with its state and its settings form,
		 *	plus the directory they are read from
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
				'dir' 			=> self::_dir(),
				'features'	=> $features,
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
