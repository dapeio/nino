<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Features						Installed features: discovery, manifests, settings and activation
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Features					A feature is one directory below features/ (or
	 *										NINO_FEATURES_DIR), named after the module it brings:
	 *										features/Newsletter/Newsletter.php is the runtime class
	 *										\Nino\Modules\Newsletter, Admin/Admin.php its workbench
	 *										panel, install/ the unit that copies its templates and
	 *										texts, and feature.php the manifest this class reads -
	 *										name, version, the Nino version it was written for,
	 *										what it requires, and the settings the Features panel
	 *										offers for it. See docs/features.md.
	 *
	 *										A feature is not a wizard unit and needs no install step
	 *										of its own: it is dropped into the directory and switched
	 *										on in the Features panel, which is what activate() does -
	 *										apply its unit without overwriting anything the project
	 *										already has, list its class in '/nino/modules', record the
	 *										version. Everything a feature keeps is the project's:
	 *										settings under '/nino/features' in config.php, files
	 *										under data/, templates and texts copied once.
	 *
	 *										The unit application lives here rather than in the wizard
	 *										because both need it and _admin/install/ may be deleted
	 *										after setup - the wizard calls applyUnit() with overwrite
	 *										on, a feature activation with it off.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Features {

		// Where a feature's state lives in config.php: key => { version, settings }
		public const string STATE_KEY = '/nino/features';

		// The manifest beside a feature's class file
		public const string MANIFEST = 'feature.php';

		// What a settings schema may declare a value to be - each one is a
		// form control the Features panel knows how to render and this class
		// knows how to validate
		public const array SETTING_TYPES = [ 'bool', 'int', 'string', 'text', 'email', 'url', 'select', 'secret', 'lines' ];

		/*	The sections a manual is written in, in the order the panel draws
			them. A fixed vocabulary rather than free prose, because the thing a
			developer does with a feature's manual is *look something up* in it -
			which shortcode, which route, what the panel is called - and prose
			makes that a read rather than a glance. Every feature answering the
			same questions in the same order is worth more than any one of them
			answering them well.

			Two of the sections the panel draws are not in here, because a
			feature already declares them and writing them twice is writing them
			differently: the description comes from 'description', and the
			settings from 'settings' with their own labels and hints.

			An empty section is drawn all the same, saying so. "No callbacks" is
			an answer, and a reader who does not find the question has to go and
			check the source to learn that the answer was nothing.

			'markup' is the one that is not a thing Nino registers: it is what a
			feature asks a template to write - data-lightbox, a class a script
			looks for, a <script type="text/plain">. Several features add nothing
			but that, and without the section their manual would be empty while
			they are the ones with the most to say.

			What is deliberately not a section: the php a feature exposes to
			other code. This is the card an operator opens to find out what
			arrived on their site; Modules\Search::getElements() is a README
			question, and mixing the two makes both harder to scan.	*/
		public const array MANUAL_SECTIONS = [ 'shortcodes', 'markup', 'routes', 'panel', 'callbacks', 'install' ];

		// One manual entry is a handle and a line about it - not a paragraph,
		// and not a name so long it stops being a handle
		private const int MANUAL_ENTRIES = 40;
		private const int MANUAL_HANDLE_LENGTH = 120;

		// The coarse "what is this for" a feature is filed under, one per
		// feature: what the Features panel filters by and what a person
		// browsing a catalogue of forty features navigates by. A vocabulary
		// rather than free text, or the same idea arrives as four spellings
		// and the filter stops grouping anything.
		//
		// Advisory here on purpose: manifest() takes any slug, not only these
		// (see CATEGORY_PATTERN). A kernel that predates a category shows the
		// slug it does not know rather than refusing the feature - so a new
		// category costs a catalogue release, not a Nino release. What keeps
		// the vocabulary a vocabulary is the publishing side: the catalogue's
		// build step only ever publishes these.
		public const array CATEGORIES = [ 'content', 'ui', 'communication', 'marketing', 'security', 'system' ];

		private const string KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';
		private const string CATEGORY_PATTERN = '/^[a-z][a-z0-9-]{0,23}$/';
		private const string SETTING_PATTERN = '/^[a-z][a-zA-Z0-9]*$/';
		private const string VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/';

		private const int MAX_STRING_LENGTH = 1000;
		private const int MAX_TEXT_LENGTH = 10000;
		private const int MAX_LINES = 200;

		/**
		 *	The directory features live in - features/ below the project root,
		 *	or wherever NINO_FEATURES_DIR points (the autoloader reads the same
		 *	constant, so a relocated directory serves the classes too)
		 *
		 *	@return 	string
		 */
		public static function dir(): string {

			return defined( 'NINO_FEATURES_DIR' ) === true && is_string( NINO_FEATURES_DIR ) === true && NINO_FEATURES_DIR !== ''
				? rtrim( NINO_FEATURES_DIR, '/' )
				: dirname( __DIR__, 3 ). '/features';
		}

		/**
		 *	Every feature the directory holds, keyed by manifest key and sorted
		 *	by it - its manifest plus what this installation knows about it:
		 *
		 *	- 'dir'				absolute directory
		 *	- 'module'		the class it brings, \Nino\Modules\<Directory>
		 *	- 'active'		whether that class is listed in '/nino/modules'
		 *	- 'installed'	the version activate() last recorded, null before the first
		 *	- 'update'		active with a manifest version the record does not match
		 *	- 'problems'	why it cannot be activated here, [] when it can
		 *
		 *	A directory without a readable manifest is skipped with a warning
		 *	naming it, and so is a second directory claiming a key an earlier
		 *	one holds. Read once per request.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function all( array &$appData ): array {

			if( is_array( $appData['./nino/features/all'] ?? null ) === true )
				return $appData['./nino/features/all'];

			$features = [];
			$dir = self::dir();

			foreach( is_dir( $dir ) === true ? ( scandir( $dir ) ?: [] ) : [] as $entry ) {

				$path = $dir. '/'. $entry;

				if( $entry[0] === '.' || is_dir( $path ) === false || is_file( $path. '/'. self::MANIFEST ) === false )
					continue;

				$manifest = self::manifest( $path );
				if( $manifest === null )
					continue;

				if( isset( $features[ $manifest['key'] ] ) === true ) {
					trigger_error( 'Feature in '. $path. ' claims the key "'. $manifest['key']. '" that '. $features[ $manifest['key'] ]['dir']. ' already holds', E_USER_WARNING );
					continue;
				}

				$features[ $manifest['key'] ] = $manifest;
			}

			ksort( $features );

			// Written the way config.php writes it, with the leading separator -
			// a hand-edited list without one still names the same class
			$active	= array_map( static fn( mixed $class ): string => '\\'. ltrim( (string) $class, '\\' ), (array) ( $appData['/nino/modules'] ?? [] ) );
			$state	= is_array( $appData[ self::STATE_KEY ] ?? null ) ? $appData[ self::STATE_KEY ] : [];

			foreach( $features as $key => &$feature ) {
				$feature['active'] 		= in_array( $feature['module'], $active, true );
				$feature['installed']	= isset( $state[$key]['version'] ) === true && is_string( $state[$key]['version'] ) === true ? $state[$key]['version'] : null;
				$feature['update']		= $feature['active'] === true && $feature['installed'] !== $feature['version'];
				$feature['problems']	= self::_problems( $feature, $features );
			}
			unset( $feature );

			$appData['./nino/features/all'] = $features;

			return $features;
		}

		/**
		 *	One feature by key - see all()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *
		 *	@return 	array|null
		 */
		public static function get( array &$appData, string $key ): ?array {

			return self::all( $appData )[$key] ?? null;
		}

		/**
		 *	Read and validate one feature's manifest. Null with a warning when
		 *	the file is missing or does not describe a feature - a manifest
		 *	that is half right is not applied half way.
		 *
		 *	The class a feature brings is not declared but derived: the
		 *	autoloader serves \Nino\Modules\<Name> from features/<Name>/, so
		 *	that is the only class it can be. A 'module' entry is accepted
		 *	when it says the same and refused when it says anything else.
		 *
		 *	@param		string		$dir					The feature directory
		 *
		 *	@return 	array|null							The manifest with every key present and normalized, plus 'dir' and 'module'
		 */
		public static function manifest( string $dir ): ?array {

			$dir = rtrim( $dir, '/' );
			$path = $dir. '/'. self::MANIFEST;
			$name = basename( $dir );

			$fail = static function( string $why ) use ( $path ): null {
				trigger_error( 'Feature manifest '. $path. ': '. $why, E_USER_WARNING );
				return null;
			};

			if( preg_match( '/^[A-Z][A-Za-z0-9]*$/', $name ) !== 1 )
				return $fail( 'the directory name must be a class name segment (Newsletter, not newsletter)' );

			if( is_file( $path ) === false )
				return $fail( 'no such file' );

			$raw = include $path;
			if( is_array( $raw ) === false )
				return $fail( 'must return an array' );

			$key = (string) ( $raw['key'] ?? strtolower( $name ) );
			if( preg_match( self::KEY_PATTERN, $key ) !== 1 )
				return $fail( '"key" must be a slug' );

			if( self::_localizedValid( $raw['name'] ?? '' ) === false )
				return $fail( '"name" must be a string or a locale => string map' );

			if( isset( $raw['description'] ) === true && self::_localizedValid( $raw['description'] ) === false )
				return $fail( '"description" must be a string or a locale => string map' );

			// The prose a manifest may carry beyond its description: how the
			// feature is used, which the panel puts at the top of its screen.
			// Capped like a text setting - what does not fit a box in a panel
			// is a README, and a feature carries one of those already
			if( isset( $raw['manual'] ) === true && self::_manualSectioned( $raw['manual'] ) === true ) {

				/*	The sectioned form: section => handle => one line, localized
					the way a name or a description is. The handle - a shortcode,
					a route, the panel's name - is written once and is not
					translated, because it is what a developer types	*/
				foreach( $raw['manual'] as $section => $entries ) {

					if( in_array( $section, self::MANUAL_SECTIONS, true ) === false )
						return $fail( '"manual" knows the sections '. implode( ', ', self::MANUAL_SECTIONS ). ' - not "'. (string) $section. '"' );

					if( is_array( $entries ) === false )
						return $fail( '"manual" section "'. $section. '" must be a handle => text map' );

					if( count( $entries ) > self::MANUAL_ENTRIES )
						return $fail( '"manual" section "'. $section. '" is at most '. self::MANUAL_ENTRIES. ' entries - a longer one is a README' );

					foreach( $entries as $handle => $text ) {

						/*	A handle, or none: an entry written as a plain list item
							has an integer key and is a line with nothing to put in
							front of it - which is what a section like 'markup'
							sometimes needs, where the thing to say is a sentence and
							not a snippet.

							No is_string() beside the is_int(): an array key is one or
							the other, so the negative already says which	*/
						if( is_int( $handle ) === false && ( trim( $handle ) === '' || strlen( $handle ) > self::MANUAL_HANDLE_LENGTH ) )
							return $fail( '"manual" section "'. $section. '": a handle is a non-empty string of at most '. self::MANUAL_HANDLE_LENGTH. ' characters, or none at all' );

						if( self::_localizedValid( $text ) === false )
							return $fail( '"manual" section "'. $section. '", "'. $handle. '": the line must be a string or a locale => string map' );

						foreach( is_array( $text ) === true ? $text : [ $text ] as $line )
							if( strlen( (string) $line ) > self::MAX_STRING_LENGTH )
								return $fail( '"manual" section "'. $section. '", "'. $handle. '" is at most '. self::MAX_STRING_LENGTH. ' characters - one line, not a paragraph' );
					}
				}
			}
			else if( isset( $raw['manual'] ) === true ) {

				/*	...and the prose form, which is what a manual was before it
					had sections. Still read, so a catalogue written against the
					older shape keeps working - but the sectioned one is what to
					write: see docs/features.md	*/
				if( self::_localizedValid( $raw['manual'] ) === false )
					return $fail( '"manual" must be a section => handle => text map, or a string' );

				foreach( is_array( $raw['manual'] ) === true ? $raw['manual'] : [ $raw['manual'] ] as $text )
					if( strlen( (string) $text ) > self::MAX_TEXT_LENGTH )
						return $fail( '"manual" is at most '. self::MAX_TEXT_LENGTH. ' characters - a longer one is a README' );
			}

			// Any slug passes, CATEGORIES is what a feature should use - the
			// panel labels the ones it knows and shows the rest as they are.
			// Nothing depends on the value: it groups a list, so an unknown
			// one costs a heading, not a feature
			// Read, never cast: an array here would be a warning about string
			// conversion before it was ever a refusal naming the field
			$category = $raw['category'] ?? '';
			if( is_string( $category ) === false || ( $category !== '' && preg_match( self::CATEGORY_PATTERN, $category ) !== 1 ) )
				return $fail( '"category" must be a slug - one of '. implode( ', ', self::CATEGORIES ) );

			$version = (string) ( $raw['version'] ?? '' );
			if( preg_match( self::VERSION_PATTERN, $version ) !== 1 )
				return $fail( '"version" must be major.minor.patch' );

			$nino = (string) ( $raw['nino'] ?? '*' );
			if( trim( $nino ) === '' || self::constraintValid( $nino ) === false )
				return $fail( '"nino" must be a version constraint such as ^1.0' );

			$module = '\\Nino\\Modules\\'. $name;
			if( isset( $raw['module'] ) === true && '\\'. ltrim( (string) $raw['module'], '\\' ) !== $module )
				return $fail( '"module" can only be '. $module. ' - the class the autoloader serves from this directory' );

			if( is_file( $dir. '/'. $name. '.php' ) === false )
				return $fail( 'the class file '. $name. '.php is missing beside it' );

			$requires = [];
			foreach( (array) ( $raw['requires'] ?? [] ) as $req ) {
				if( is_string( $req ) === false || preg_match( self::KEY_PATTERN, $req ) !== 1 )
					return $fail( '"requires" must list feature keys' );
				if( $req !== $key && in_array( $req, $requires, true ) === false )
					$requires[] = $req;
			}

			$extensions = [];
			foreach( (array) ( $raw['php']['ext'] ?? [] ) as $ext ) {
				if( is_string( $ext ) === false || preg_match( '/^[a-z][a-z0-9_]*$/i', $ext ) !== 1 )
					return $fail( '"php" => "ext" must list extension names' );
				$extensions[] = strtolower( $ext );
			}

			$data = [];
			foreach( (array) ( $raw['data'] ?? [] ) as $file ) {
				if( is_string( $file ) === false || str_starts_with( $file, '/data/' ) === false || str_contains( $file, '..' ) === true )
					return $fail( '"data" must list paths below /data/' );
				$data[] = $file;
			}

			$settings = [];
			foreach( (array) ( $raw['settings'] ?? [] ) as $settingName => $schema ) {

				if( is_string( $settingName ) === false || preg_match( self::SETTING_PATTERN, $settingName ) !== 1 )
					return $fail( 'a setting name must be a lowerCamel identifier' );

				$clean = self::_schema( $settingName, $schema );
				if( is_string( $clean ) === true )
					return $fail( $clean );

				$settings[$settingName] = $clean;
			}

			return [
				'key'					=> $key,
				'dir'					=> $dir,
				'module'			=> $module,
				'name'				=> $raw['name'],
				'description'	=> $raw['description'] ?? '',
				'manual'			=> $raw['manual'] ?? '',
				'category'		=> $category,
				'version'			=> $version,
				'nino'				=> trim( $nino ),
				'php'					=> [ 'ext' => $extensions ],
				'requires'		=> $requires,
				'data'				=> $data,
				'settings'		=> $settings,
			];
		}

		/**
		 *	A value that is either one string or one string per locale, the
		 *	way a manifest names, describes and labels things: the panel shows
		 *	names of features that are not active, whose fills are therefore
		 *	not loaded, so the words travel in the manifest itself
		 *
		 *	@param		mixed			$value				String, or locale => string
		 *	@param		string		$locale				The locale wanted
		 *
		 *	@return 	string									That locale's string, else en_US, else the first, else ''
		 */
		public static function localized( mixed $value, string $locale ): string {

			if( is_string( $value ) === true )
				return $value;

			if( is_array( $value ) === false )
				return '';

			foreach( [ $locale, 'en_US' ] as $try )
				if( isset( $value[$try] ) === true && is_string( $value[$try] ) === true )
					return $value[$try];

			foreach( $value as $first )
				if( is_string( $first ) === true )
					return $first;

			return '';
		}

		/**
		 *	Whether a version satisfies a constraint. Enough of the composer
		 *	vocabulary to write a manifest with: '*', an exact version, a
		 *	comparison ('>=1.2'), a caret ('^1.0': the same major, at least
		 *	that), a tilde ('~1.2': the same major from 1.2 on, '~1.2.3': the
		 *	same minor from 1.2.3 on), parts joined by a comma or space (all
		 *	must hold) and alternatives joined by '||' (one must hold). A
		 *	pre-release suffix ('1.0.0-beta') counts as the release it
		 *	precedes: a feature written against 1.0 works on the 1.0 beta.
		 *
		 *	@param		string		$constraint
		 *	@param		string		$version			Default: the running kernel's
		 *
		 *	@return 	bool
		 */
		public static function satisfies( string $constraint, string $version = \Nino\VERSION ): bool {

			$version = self::_normalizeVersion( $version );
			if( $version === null )
				return false;

			foreach( explode( '||', $constraint ) as $alternative ) {

				$holds = true;
				foreach( preg_split( '/[\s,]+/', trim( $alternative ) ) ?: [] as $part ) {
					if( $part === '' )
						continue;
					if( self::_satisfiesOne( $part, $version ) === false ) {
						$holds = false;
						break;
					}
				}

				if( $holds === true )
					return true;
			}

			return false;
		}

		/**
		 *	A feature's settings as it should read them: what the Features
		 *	panel saved, the manifest's default for everything it has not,
		 *	the type's own zero value for a setting with no default. Only
		 *	settings the schema declares are answered - a stray key in
		 *	config.php is not a setting.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *
		 *	@return 	array										name => value
		 */
		public static function settings( array &$appData, string $key ): array {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return [];

			$stored = $appData[ self::STATE_KEY ][$key]['settings'] ?? [];
			$stored = is_array( $stored ) ? $stored : [];

			$values = [];
			foreach( $feature['settings'] as $name => $schema ) {

				// A stored value that no longer validates - a schema that
				// tightened, a hand edit - is not handed to the feature: the
				// default is, the same as if nothing had been saved
				[ $ok, $value ] = array_key_exists( $name, $stored ) === true ? self::_clean( $schema, $stored[$name], null ) : [ false, null ];

				$values[$name] = $ok === true
					? $value
					: ( array_key_exists( 'default', $schema ) === true ? $schema['default'] : self::_zero( $schema ) );
			}

			return $values;
		}

		/**
		 *	One setting - the way a feature's own code asks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		string		$name					Setting name
		 *	@param		mixed			$default			Answered for a setting the schema does not declare
		 *
		 *	@return 	mixed
		 */
		public static function setting( array &$appData, string $key, string $name, mixed $default = null ): mixed {

			$values = self::settings( $appData, $key );

			return array_key_exists( $name, $values ) === true ? $values[$name] : $default;
		}

		/**
		 *	Validate a posted settings form against a schema. Every setting is
		 *	checked before any is accepted; a setting the form did not send
		 *	keeps its current value; a 'secret' sent as '' keeps its current
		 *	value too (the form never shows one) and as null is cleared.
		 *
		 *	@param		array 		$schema				The manifest's 'settings'
		 *	@param		array 		$posted				name => value as posted
		 *	@param		array 		$current			name => value as stored
		 *
		 *	@return 	array										{ values: name => clean value, errors: name => message }
		 */
		public static function validateSettings( array $schema, array $posted, array $current = [] ): array {

			$values = [];
			$errors = [];

			foreach( $schema as $name => $field ) {

				if( array_key_exists( $name, $posted ) === false ) {
					if( array_key_exists( $name, $current ) === true )
						$values[$name] = $current[$name];
					continue;
				}

				[ $ok, $result ] = self::_clean( $field, $posted[$name], $current[$name] ?? null );

				if( $ok === true )
					$values[$name] = $result;
				else
					$errors[$name] = $result;
			}

			return [ 'values' => $values, 'errors' => $errors ];
		}

		/**
		 *	Save a feature's settings: validate, then one targeted write of
		 *	the state key. Nothing is written while anything is wrong.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		array 		$posted				name => value as posted
		 *
		 *	@return 	array										name => message for every rejected setting; [] when saved
		 */
		public static function saveSettings( array &$appData, string $key, array $posted ): array {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return [ '' => 'unknown feature "'. $key. '"' ];

			$state	= is_array( $appData[ self::STATE_KEY ] ?? null ) ? $appData[ self::STATE_KEY ] : [];
			$current = is_array( $state[$key]['settings'] ?? null ) ? $state[$key]['settings'] : [];

			$result = self::validateSettings( $feature['settings'], $posted, $current );
			if( $result['errors'] !== [] )
				return $result['errors'];

			$state[$key] = [
				'version'		=> $state[$key]['version'] ?? $feature['installed'],
				'settings'	=> $result['values'],
			];
			$appData[ self::STATE_KEY ] = $state;

			if( \Nino\AppData::writeContentData( $appData, [ self::STATE_KEY ] ) === false )
				return [ '' => 'could not write config.php' ];

			return [];
		}

		/**
		 *	Switch a feature on - the one step there is. Its requirements are
		 *	activated first, its install/ unit is applied without touching a
		 *	file or a text key the project already has, its class is listed
		 *	in '/nino/modules', its version recorded. Activating an active
		 *	feature is how an update is applied: the unit adds what is new,
		 *	and a module that implements upgrade( &$appData, $fromVersion )
		 *	gets to migrate its own data before the new version is recorded.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		array 		$chain				Keys already being activated, against a requirement cycle
		 *
		 *	@return 	true|string							True, or why not
		 */
		public static function activate( array &$appData, string $key, array $chain = [] ): true|string {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return 'unknown feature "'. $key. '"';

			if( $feature['problems'] !== [] )
				return 'feature "'. $key. '" cannot be activated: '. implode( '; ', $feature['problems'] );

			if( in_array( $key, $chain, true ) === true )
				return true;
			$chain[] = $key;

			foreach( $feature['requires'] as $required ) {
				$result = self::activate( $appData, $required, $chain );
				if( $result !== true )
					return 'required feature "'. $required. '": '. $result;
			}

			// The persisted routes, never the live ones - the live array
			// carries this request's own runtime routes (the workbench's, the
			// active modules' self-registered endpoints), which must not be
			// written into config.php. Same rule as the wizard's Setup step.
			$stored 		= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$stored 		= is_array( $stored ) ? $stored : [];
			$routes 		= is_array( $stored['/nino/http/routes'] ?? null ) ? $stored['/nino/http/routes'] : \Nino\AppData::DEFAULTS['/nino/http/routes'];
			$routesBefore	= $routes;
			$blacklist	= [];

			if( is_dir( $feature['dir']. '/install' ) === true )
				self::applyUnit( $appData, $feature['dir']. '/install', \Nino\Locales::getAvailableLocales( $appData ), $routes, $blacklist, false );

			$class 			= $feature['module'];
			$wasActive	= $feature['active'];

			if( $wasActive === true && $feature['installed'] !== $feature['version'] && method_exists( $class, 'upgrade' ) === true )
				if( $class::upgrade( $appData, (string) $feature['installed'] ) === false )
					return 'feature "'. $key. '" refused to upgrade from '. ( $feature['installed'] ?? 'an unrecorded version' );

			$modules = array_values( (array) ( $appData['/nino/modules'] ?? [] ) );
			if( $wasActive === false )
				$modules[] = $class;
			$appData['/nino/modules'] = array_values( array_unique( $modules ) );

			$state = is_array( $appData[ self::STATE_KEY ] ?? null ) ? $appData[ self::STATE_KEY ] : [];
			$state[$key] = [
				'version'		=> $feature['version'],
				'settings'	=> is_array( $state[$key]['settings'] ?? null ) ? $state[$key]['settings'] : [],
			];
			$appData[ self::STATE_KEY ] = $state;

			$keys = [ '/nino/modules', self::STATE_KEY ];

			$live = (array) ( $appData['/nino/http/routes'] ?? [] );
			if( $routes !== $routesBefore ) {
				$appData['/nino/http/routes'] = $routes;
				$keys[] = '/nino/http/routes';
			}

			$written = \Nino\AppData::writeContentData( $appData, $keys );

			// The live routes back, with the unit's new ones added: this
			// request goes on with everything it booted with
			$appData['/nino/http/routes'] = $live + $routes;

			if( $written === false )
				return 'could not write config.php';

			if( $blacklist !== [] )
				\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( mixed $list ) use ( $blacklist ): array {
					return array_values( array_unique( array_merge( is_array( $list ) ? $list : [], $blacklist ) ) );
				} );

			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	Switch a feature off: its class leaves '/nino/modules', and that
		 *	is all - settings, data, copied templates and texts stay, so
		 *	switching it back on finds everything as it was. Refused while
		 *	another active feature requires it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *
		 *	@return 	true|string							True, or why not
		 */
		public static function deactivate( array &$appData, string $key ): true|string {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return 'unknown feature "'. $key. '"';

			foreach( self::all( $appData ) as $other )
				if( $other['active'] === true && in_array( $key, $other['requires'], true ) === true )
					return 'feature "'. $key. '" is required by "'. $other['key']. '"';

			if( $feature['active'] === false )
				return true;

			$appData['/nino/modules'] = array_values( array_filter(
				(array) ( $appData['/nino/modules'] ?? [] ),
				static fn( mixed $class ): bool => '\\'. ltrim( (string) $class, '\\' ) !== $feature['module']
			) );

			if( \Nino\AppData::writeContentData( $appData, [ '/nino/modules' ] ) === false )
				return 'could not write config.php';

			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	Delete a feature's directory.
		 *
		 *	The one thing deactivation deliberately does not do, and the step
		 *	that was missing: a feature switched off is still a directory a
		 *	project carries, and until now the only way to be rid of it was a
		 *	file manager on the server. What it leaves behind is what
		 *	deactivation leaves behind - the settings recorded under
		 *	'/nino/features', the files under data/, the templates and texts
		 *	its unit copied once. That is deliberate on both counts: those are
		 *	the project's now, and putting the same feature back finds its
		 *	settings where it left them.
		 *
		 *	Refused for an active feature. Its class is listed in
		 *	'/nino/modules', so removing the directory would leave the
		 *	autoloader looking for a class that is not there - switch it off
		 *	first, which is one click and says what it is doing.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The feature's key
		 *
		 *	@return 	true|string							true, or why not
		 */
		public static function remove( array &$appData, string $key ): true|string {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return 'unknown feature "'. $key. '"';

			if( $feature['active'] === true )
				return 'feature "'. $key. '" is active - switch it off before removing it';

			// all() only ever reads directories below dir(), so this cannot
			// currently be anything else. Checked anyway: it is the guard
			// between a key from a request and a recursive delete
			$dir = $feature['dir'];
			if( str_starts_with( $dir, self::dir(). '/' ) === false || str_contains( $dir, '..' ) === true )
				return 'feature "'. $key. '" does not live below the features directory';

			\Nino\Filesystem::removeDir( $dir );

			if( is_dir( $dir ) === true )
				return 'could not remove '. $dir. ' - the web server may not write there';

			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	The unit manifest of an install/ directory - the wizard's units
		 *	and a feature's install/ share the shape (docs/setup.md, "Library
		 *	Format")
		 *
		 *	@param		string		$unitDir
		 *
		 *	@return 	array|null
		 */
		public static function readUnitManifest( string $unitDir ): ?array {

			$path = rtrim( $unitDir, '/' ). '/manifest.php';

			if( is_file( $path ) === false )
				return null;

			$manifest = include $path;

			return is_array( $manifest ) ? $manifest : null;
		}

		/**
		 *	Apply one unit's manifest.php: merge its routes into $routes
		 *	(skipping any locale-gated route whose locale is not available),
		 *	copy its files, templates and element types (same locale gating),
		 *	collect its blacklist entries, fill in its config defaults where
		 *	the project has nothing yet, and merge its text/global.php and
		 *	text/<locale>.php fragments into the real /text files.
		 *
		 *	With $overwrite the unit wins, which is what the wizard wants: a
		 *	re-applied unit replaces what it copied before. Without it the
		 *	project wins - a route, a file, a text key that exists stays as it
		 *	is and only what is missing is added. That is what a feature
		 *	activation and an update want.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitDir			Absolute path to the unit
		 *	@param		array 		$locales			The locales to apply for
		 *	@param		array 		&$routes			(reference) Routes accumulator - the persisted routes, never the live ones
		 *	@param		array 		&$blacklist		(reference) Collected blacklist keys, appended to
		 *	@param		bool			$overwrite		Whether the unit replaces what the project has
		 *
		 *	@return 	void
		 */
		public static function applyUnit( array &$appData, string $unitDir, array $locales, array &$routes, array &$blacklist, bool $overwrite = true ): void {

			$unitDir	= rtrim( $unitDir, '/' );
			$manifest	= self::readUnitManifest( $unitDir ) ?? [];

			foreach( ( $manifest['routes'] ?? [] ) as $routeKey => $route ) {
				if( isset( $route['locale'] ) === true && in_array( $route['locale'], $locales, true ) === false )
					continue;
				if( $overwrite === false && isset( $routes[$routeKey] ) === true )
					continue;
				$routes[$routeKey] = $route;
			}

			/*	Files before templates, and it is not cosmetic: base ships the
				deny rule for the private tree itself (private/.htaccess), and
				base is the first unit the wizard applies. Copying templates
				first would create private/ through forceDir() and only protect
				it a few statements later - a window, however short, in which a
				failed request could leave the directory readable with a
				project's templates already in it.	*/
			foreach( ( $manifest['files'] ?? [] ) as $file )
				self::copyTree( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ), $overwrite );

			if( count( $manifest['templates'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/templates' );
				foreach( $manifest['templates'] as $locale => $file ) {
					if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
						continue;
					self::copyFile( $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ), $overwrite );
				}
			}

			if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/elements' );
				foreach( $manifest['elementTypes'] as $file )
					self::copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/elements/'. $file ), $overwrite );
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

			// A unit's own config defaults - only filled in where the project
			// has nothing yet, never overwritten: re-applying a unit must not
			// reset a value the developer has edited since
			foreach( ( $manifest['config'] ?? [] ) as $configKey => $configValue )
				if( isset( $appData[$configKey] ) === false ) {
					$appData[$configKey] = $configValue;
					\Nino\AppData::writeContentData( $appData, [ $configKey ] );
				}

			$globalFragment = $unitDir. '/text/global.php';
			if( is_file( $globalFragment ) === true )
				self::mergeText( $appData, '/text/global.php', (array) include $globalFragment, $overwrite );

			foreach( $locales as $locale ) {
				$localeFragment = $unitDir. '/text/'. $locale. '.php';
				if( is_file( $localeFragment ) === true )
					self::mergeText( $appData, '/text/'. $locale. '.php', (array) include $localeFragment, $overwrite );
			}
		}

		/**
		 *	Merge a text fragment's keys into a /text/*.php file - the
		 *	fragment wins a key collision with $overwrite, the file without
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					Filesystem-relative path, eg. '/text/de_DE.php'
		 *	@param		array 		$fragment			Bracket-key => value pairs to merge in
		 *	@param		bool			$overwrite
		 *
		 *	@return 	void
		 */
		public static function mergeText( array &$appData, string $path, array $fragment, bool $overwrite = true ): void {

			\Nino\Filesystem::mutate( $appData, $path, function( mixed $content ) use ( $fragment, $overwrite ): array {
				$content = is_array( $content ) ? $content : [];
				return $overwrite === true ? array_merge( $content, $fragment ) : $content + $fragment;
			} );
		}

		/**
		 *	Copy one unit file into the project - plain file_get_contents/
		 *	put_contents rather than \Nino\Filesystem, which only knows how
		 *	to read and write .php files shaped as `<?php return [...];`:
		 *	these are .tpl markup, or an element type that has to survive
		 *	the copy byte for byte rather than round-trip through var_export()
		 *
		 *	@param		string		$from
		 *	@param		string		$to
		 *	@param		bool			$overwrite		Whether an existing $to is replaced
		 *
		 *	@return 	void
		 */
		public static function copyFile( string $from, string $to, bool $overwrite = true ): void {

			if( $overwrite === false && is_file( $to ) === true )
				return;

			$content = @file_get_contents( $from );
			if( $content === false )
				return;

			if( is_file( $to ) === true )
				@unlink( $to );

			if( is_dir( dirname( $to ) ) === false )
				@mkdir( dirname( $to ), 0755, true );

			file_put_contents( $to, $content );
		}

		/**
		 *	Copy a file or a whole directory of a unit into the project -
		 *	with $overwrite the way Filesystem::copyDir() does, without it
		 *	adding only what the project does not have
		 *
		 *	@param		string		$from
		 *	@param		string		$to
		 *	@param		bool			$overwrite
		 *
		 *	@return 	void
		 */
		public static function copyTree( string $from, string $to, bool $overwrite = true ): void {

			if( is_file( $from ) === true ) {
				self::copyFile( $from, $to, $overwrite );
				return;
			}

			if( is_dir( $from ) === false )
				return;

			if( $overwrite === true ) {
				\Nino\Filesystem::copyDir( $from, $to );
				return;
			}

			if( is_dir( $to ) === false )
				@mkdir( $to, 0755, true );

			foreach( scandir( $from ) ?: [] as $entry )
				if( $entry !== '.' && $entry !== '..' )
					self::copyTree( $from. '/'. $entry, $to. '/'. $entry, false );
		}

		/**
		 *	Why a feature cannot be activated on this installation - the
		 *	kernel version it was written for, an extension php lacks, a
		 *	feature it requires that is not installed. An installed but
		 *	inactive requirement is not a problem: activate() pulls it in.
		 *
		 *	@param		array 		$feature
		 *	@param		array 		$all					Every feature by key
		 *
		 *	@return 	array										Messages, [] when nothing stands in the way
		 */
		private static function _problems( array $feature, array $all ): array {

			$problems = [];

			if( self::satisfies( $feature['nino'] ) === false )
				$problems[] = 'requires Nino '. $feature['nino']. ', this is '. \Nino\VERSION;

			foreach( $feature['php']['ext'] as $ext )
				if( extension_loaded( $ext ) === false )
					$problems[] = 'requires the php extension "'. $ext. '"';

			foreach( $feature['requires'] as $required )
				if( isset( $all[$required] ) === false )
					$problems[] = 'requires the feature "'. $required. '", which is not installed';

			return $problems;
		}

		/**
		 *	Validate and normalize one setting's schema entry
		 *
		 *	@param		string		$name
		 *	@param		mixed			$schema
		 *
		 *	@return 	array|string						The clean schema, or what is wrong with it
		 */
		private static function _schema( string $name, mixed $schema ): array|string {

			if( is_array( $schema ) === false )
				return 'setting "'. $name. '" must be an array';

			$type = (string) ( $schema['type'] ?? '' );
			if( in_array( $type, self::SETTING_TYPES, true ) === false )
				return 'setting "'. $name. '" has an unknown type "'. $type. '"';

			if( isset( $schema['label'] ) === true && self::_localizedValid( $schema['label'] ) === false )
				return 'setting "'. $name. '": "label" must be a string or a locale => string map';

			if( isset( $schema['hint'] ) === true && self::_localizedValid( $schema['hint'] ) === false )
				return 'setting "'. $name. '": "hint" must be a string or a locale => string map';

			$clean = [
				'type'			=> $type,
				'label'			=> $schema['label'] ?? $name,
				'hint'			=> $schema['hint'] ?? '',
				'required'	=> ( $schema['required'] ?? false ) === true,
			];

			if( $type === 'int' ) {
				foreach( [ 'min', 'max' ] as $bound )
					if( isset( $schema[$bound] ) === true ) {
						if( is_int( $schema[$bound] ) === false )
							return 'setting "'. $name. '": "'. $bound. '" must be an int';
						$clean[$bound] = $schema[$bound];
					}
				if( isset( $clean['min'], $clean['max'] ) === true && $clean['min'] > $clean['max'] )
					return 'setting "'. $name. '": "min" is above "max"';
				if( isset( $schema['unit'] ) === true )
					$clean['unit'] = (string) $schema['unit'];
			}

			if( in_array( $type, [ 'string', 'text', 'secret', 'email', 'url' ], true ) === true ) {
				$ceiling = $type === 'text' ? self::MAX_TEXT_LENGTH : self::MAX_STRING_LENGTH;
				$maxlength = $schema['maxlength'] ?? $ceiling;
				if( is_int( $maxlength ) === false || $maxlength < 1 || $maxlength > $ceiling )
					return 'setting "'. $name. '": "maxlength" must be an int between 1 and '. $ceiling;
				$clean['maxlength'] = $maxlength;
			}

			if( $type === 'string' && isset( $schema['pattern'] ) === true ) {
				if( is_string( $schema['pattern'] ) === false || @preg_match( $schema['pattern'], '' ) === false )
					return 'setting "'. $name. '": "pattern" must be a valid regular expression';
				$clean['pattern'] = $schema['pattern'];
			}

			if( $type === 'select' ) {
				$options = $schema['options'] ?? null;
				if( is_array( $options ) === false || $options === [] )
					return 'setting "'. $name. '": a select needs "options"';
				$clean['options'] = [];
				foreach( $options as $value => $label ) {
					if( is_string( $value ) === false || $value === '' || self::_localizedValid( $label ) === false )
						return 'setting "'. $name. '": "options" must map a value to a label';
					$clean['options'][$value] = $label;
				}
			}

			if( array_key_exists( 'default', $schema ) === true ) {
				if( $type === 'secret' )
					return 'setting "'. $name. '": a secret cannot have a default';
				[ $ok, $result ] = self::_clean( $clean, $schema['default'], null );
				if( $ok === false )
					return 'setting "'. $name. '": the default does not validate - '. $result;
				$clean['default'] = $result;
			}

			return $clean;
		}

		/**
		 *	Coerce and validate one posted value against its schema. Coerce,
		 *	because a form posts strings: "5" for an int, "true" for a bool.
		 *	Anything that is not exactly one of the accepted forms is
		 *	rejected rather than cast, so a typo cannot become a 0.
		 *
		 *	@param		array 		$schema				The setting's clean schema
		 *	@param		mixed			$value				As posted
		 *	@param		mixed			$current			As stored - what a secret keeps when the form sends ''
		 *
		 *	@return 	array										[ true, clean value ] or [ false, message ]
		 */
		private static function _clean( array $schema, mixed $value, mixed $current ): array {

			$required = ( $schema['required'] ?? false ) === true;

			switch( $schema['type'] ) {

				case 'bool':
					if( is_bool( $value ) === true )
						return [ true, $value ];
					if( $value === 'true' || $value === 1 || $value === '1' )
						return [ true, true ];
					if( $value === 'false' || $value === 0 || $value === '0' )
						return [ true, false ];
					return [ false, 'must be true or false' ];

				case 'int':
					if( is_string( $value ) === true && preg_match( '/^-?\d+$/', trim( $value ) ) === 1 )
						$value = (int) trim( $value );
					if( is_int( $value ) === false )
						return [ false, 'must be a whole number' ];
					if( isset( $schema['min'] ) === true && $value < $schema['min'] )
						return [ false, 'must be at least '. $schema['min'] ];
					if( isset( $schema['max'] ) === true && $value > $schema['max'] )
						return [ false, 'must be at most '. $schema['max'] ];
					return [ true, $value ];

				case 'secret':
					if( $value === null )
						return [ true, '' ];
					if( is_string( $value ) === false )
						return [ false, 'must be a string' ];
					if( $value === '' )
						return [ true, is_string( $current ) ? $current : '' ];
					if( strlen( $value ) > $schema['maxlength'] )
						return [ false, 'must be at most '. $schema['maxlength']. ' characters' ];
					return [ true, $value ];

				case 'string':
				case 'text':
				case 'email':
				case 'url':
					if( is_string( $value ) === false )
						return [ false, 'must be a string' ];
					$value = trim( $value );
					if( strlen( $value ) > $schema['maxlength'] )
						return [ false, 'must be at most '. $schema['maxlength']. ' characters' ];
					if( $value === '' )
						return $required === true ? [ false, 'is required' ] : [ true, '' ];
					if( $schema['type'] === 'email' && filter_var( $value, FILTER_VALIDATE_EMAIL ) === false )
						return [ false, 'must be an email address' ];
					if( $schema['type'] === 'url' && ( filter_var( $value, FILTER_VALIDATE_URL ) === false || preg_match( '#^https?://#i', $value ) !== 1 ) )
						return [ false, 'must be an http(s) url' ];
					if( $schema['type'] === 'string' && isset( $schema['pattern'] ) === true && preg_match( $schema['pattern'], $value ) !== 1 )
						return [ false, 'does not match the required form' ];
					return [ true, $value ];

				case 'select':
					if( is_string( $value ) === false )
						return [ false, 'must be one of the options' ];
					if( $value === '' )
						return $required === true ? [ false, 'is required' ] : [ true, '' ];
					if( isset( $schema['options'][$value] ) === false )
						return [ false, 'must be one of the options' ];
					return [ true, $value ];

				case 'lines':
					if( is_string( $value ) === true )
						$value = preg_split( '/\r\n|\r|\n/', $value ) ?: [];
					if( is_array( $value ) === false )
						return [ false, 'must be a list' ];
					$lines = [];
					foreach( $value as $line ) {
						if( is_string( $line ) === false )
							return [ false, 'must be a list of strings' ];
						$line = trim( $line );
						if( strlen( $line ) > self::MAX_STRING_LENGTH )
							return [ false, 'a line must be at most '. self::MAX_STRING_LENGTH. ' characters' ];
						if( $line !== '' && in_array( $line, $lines, true ) === false )
							$lines[] = $line;
					}
					if( count( $lines ) > self::MAX_LINES )
						return [ false, 'must be at most '. self::MAX_LINES. ' lines' ];
					if( $lines === [] && $required === true )
						return [ false, 'is required' ];
					return [ true, $lines ];
			}

			return [ false, 'unknown type' ];
		}

		/**
		 *	A type's own empty value, for a setting with neither a stored
		 *	value nor a default
		 *
		 *	@param		array 		$schema
		 *
		 *	@return 	mixed
		 */
		private static function _zero( array $schema ): mixed {

			return match( $schema['type'] ) {
				'bool'	=> false,
				'int'		=> $schema['min'] ?? 0,
				'lines'	=> [],
				default	=> '',
			};
		}

		/**
		 *	@param		mixed			$value
		 *
		 *	@return 	bool										Whether it is a non-empty string or a non-empty locale => string map
		 */
		/**
		 *	A sectioned manual, resolved into one locale and ready to draw:
		 *	every section MANUAL_SECTIONS names, in that order, each a list of
		 *	{ handle, text }.
		 *
		 *	Every section is returned, including the empty ones, because "no
		 *	callbacks" is an answer and a reader who does not find the question
		 *	has to go and read the source to learn that the answer was nothing.
		 *
		 *	@param		mixed			$manual				A manifest's 'manual'
		 *	@param		string		$locale				The interface locale to resolve into
		 *
		 *	@return 	array|null							null for the older prose form
		 */
		public static function manualSections( mixed $manual, string $locale ): ?array {

			if( self::_manualSectioned( $manual ) === false )
				return null;

			$out = [];

			foreach( self::MANUAL_SECTIONS as $section ) {

				$out[$section] = [];

				foreach( (array) ( $manual[$section] ?? [] ) as $handle => $text )
					$out[$section][] = [
						/*	The handle is what a developer types, so it is not
							translated - only the line beside it is. An entry written
							as a plain list item has an integer key and no handle:
							a line that stands on its own	*/
						'handle'	=> is_int( $handle ) === true ? '' : (string) $handle,
						'text'		=> self::localized( $text, $locale ),
					];
			}

			return $out;
		}

		/**
		 *	Which of the two shapes a manual is written in.
		 *
		 *	A sectioned manual is section => handle => line, so its values are
		 *	arrays; the older prose form is a string or a locale => string map,
		 *	whose values are strings. That is the whole test, and it is enough:
		 *	no locale is called "shortcodes" and no section is called "en_US".
		 *
		 *	A section spelled wrong therefore still reads as sectioned, which is
		 *	what lets the validation name it - falling back to "must be a
		 *	string" for a typo'd section would be the least useful thing this
		 *	could say.
		 *
		 *	@param		mixed			$value				A manifest's 'manual'
		 *
		 *	@return 	bool
		 */
		private static function _manualSectioned( mixed $value ): bool {

			if( is_array( $value ) === false )
				return false;

			// An empty one is a feature saying it adds nothing, which is a
			// thing worth being able to say
			if( $value === [] )
				return true;

			foreach( $value as $entries )
				if( is_array( $entries ) === false )
					return false;

			return true;
		}

		private static function _localizedValid( mixed $value ): bool {

			if( is_string( $value ) === true )
				return trim( $value ) !== '';

			if( is_array( $value ) === false || $value === [] )
				return false;

			foreach( $value as $locale => $string )
				if( is_string( $locale ) === false || is_string( $string ) === false || trim( $string ) === '' )
					return false;

			return true;
		}

		/**
		 *	@param		string		$constraint
		 *
		 *	@return 	bool										Whether every part of it is something satisfies() understands
		 */
		public static function constraintValid( string $constraint ): bool {

			foreach( explode( '||', $constraint ) as $alternative )
				foreach( preg_split( '/[\s,]+/', trim( $alternative ) ) ?: [] as $part )
					if( $part !== '' && $part !== '*' && preg_match( '/^(?:\^|~|>=|<=|>|<|!=|=)?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.]+)?$/', $part ) !== 1 )
						return false;

			return true;
		}

		/**
		 *	@param		string		$part					One constraint part, eg. '^1.0'
		 *	@param		string		$version			Normalized
		 *
		 *	@return 	bool
		 */
		private static function _satisfiesOne( string $part, string $version ): bool {

			if( $part === '*' )
				return true;

			if( preg_match( '/^(\^|~|>=|<=|>|<|!=|=)?(\d+(?:\.\d+){0,2})(?:-[0-9A-Za-z.]+)?$/', $part, $m ) !== 1 )
				return false;

			$operator	= $m[1];
			$parts		= array_map( 'intval', explode( '.', $m[2] ) );
			$given		= count( $parts );
			$floor		= implode( '.', array_pad( $parts, 3, 0 ) );

			if( $operator === '^' ) {
				$ceiling = $parts[0] > 0 || $given === 1
					? ( $parts[0] + 1 ). '.0.0'
					: '0.'. ( ( $parts[1] ?? 0 ) + 1 ). '.0';
				return version_compare( $version, $floor, '>=' ) && version_compare( $version, $ceiling, '<' );
			}

			if( $operator === '~' ) {
				$ceiling = $given >= 3
					? $parts[0]. '.'. ( $parts[1] + 1 ). '.0'
					: ( $parts[0] + 1 ). '.0.0';
				return version_compare( $version, $floor, '>=' ) && version_compare( $version, $ceiling, '<' );
			}

			if( $operator === '' || $operator === '=' ) {
				// '1' means any 1.x.y, '1.2' any 1.2.y, '1.2.3' exactly that
				$upper = $given === 1 ? ( $parts[0] + 1 ). '.0.0' : ( $given === 2 ? $parts[0]. '.'. ( $parts[1] + 1 ). '.0' : null );
				return $upper === null
					? version_compare( $version, $floor, '==' )
					: ( version_compare( $version, $floor, '>=' ) && version_compare( $version, $upper, '<' ) );
			}

			return version_compare( $version, $floor, $operator );
		}

		/**
		 *	@param		string		$version			eg. '1.0.0-beta' or '1.2'
		 *
		 *	@return 	string|null							'1.0.0' / '1.2.0', null for something that is not a version
		 */
		private static function _normalizeVersion( string $version ): ?string {

			if( preg_match( '/^(\d+(?:\.\d+){0,2})(?:-[0-9A-Za-z.]+)?$/', trim( $version ), $m ) !== 1 )
				return null;

			return implode( '.', array_pad( array_map( 'intval', explode( '.', $m[1] ) ), 3, 0 ) );
		}
	}
}
