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

		// Where the schemas of the components a feature brings are kept between requests: a php file for each
		// feature below the private tree (never served), not in a backup, emptied with the trees of the templates
		// (\Nino\Html::dropTrees()) and turned off by the same switch
		public const string REGISTER = '/data/cache/register';

		// What the folder of a feature that brings components is made of
		private const string COMPONENTS = 'components';
		private const string GROUPS = 'groups';

		// The longest standard part that is read: the parts of a feature of twenty components are 15 KB together
		private const int PART_BYTES = 262144;

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
			arrived on their site; Modules\Search::getElements() is a question
			for the class's own docblock, and mixing the two makes both harder
			to scan.	*/
		/**	Runtime key: route keys a unit applied without overwriting treats as taken, set by the setup wizard for the one request that places a feature	*/
		public const string RESERVED = './nino/features/reserved';

		public const array MANUAL_SECTIONS = [ 'shortcodes', 'markup', 'routes', 'panel', 'callbacks', 'install' ];

		// One manual entry is a handle and a line about it - not a paragraph,
		// and not a name so long it stops being a handle
		private const int MANUAL_ENTRIES = 40;
		private const int MANUAL_HANDLE_LENGTH = 120;

		// The longest a maturity word may be: it is drawn beside a name
		public const int MATURITY_LENGTH = 24;

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

		/*	What a feature key, a category and a version look like. Public
			because they are not this class's private business: \Nino\Catalogue
			reads the same three fields out of a published catalogue entry, and
			kept its own copies of all three. Two spellings of "what a feature
			key is" is one spelling too many - the catalogue is the publishing
			side of exactly these manifests. The key pattern has the D modifier:
			without it a key with a line break at its end is a slug	*/
		public const string KEY_PATTERN = '/^[a-z][a-z0-9-]*$/D';
		public const string CATEGORY_PATTERN = '/^[a-z][a-z0-9-]{0,23}$/D';
		public const string VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/D';

		/*	What a feature's directory and a PHP extension name look like, for
			the same reason: the directory is the class name segment
			(\Nino\Modules\<Directory>), so a catalogue entry that names one has
			to pass the rule manifest() applies to the directory it lands in,
			and "php" => "ext" is read by both	*/
		public const string DIRECTORY_PATTERN = '/^[A-Z][A-Za-z0-9]*$/D';
		public const string EXTENSION_PATTERN = '/^[a-z][a-z0-9_]*$/iD';

		// Where the image slots of a project are kept in config.php: uri => slot
		private const string IMAGES_KEY = '/nino/html/images';

		private const string SETTING_PATTERN = '/^[a-z][a-zA-Z0-9]*$/D';

		// A file below a feature's assets/ directory the bundles of the site may
		// carry: a style sheet or a script, a plain relative name with no hidden
		// first character
		private const string ASSET_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_.\/-]*\.(?:css|js)$/D';

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
		 *	The directory of the modules of the kernel: a module of it that has a feature.php is a feature that
		 *	is delivered with the kernel (see all())
		 *
		 *	@return 	string
		 */
		private static function _shipped(): string {

			return dirname( __DIR__ ). '/Modules';
		}

		/**
		 *	Every feature the directory holds, keyed by manifest key and sorted
		 *	by it - its manifest plus what this installation knows about it:
		 *
		 *	- 'dir'				absolute directory
		 *	- 'shipped'		whether it is delivered with the kernel: a module of the kernel with a
		 *								feature.php, which is switched on and off like any other, has no
		 *								directory to remove, and is updated with the kernel and not from the
		 *								catalogue
		 *	- 'module'		the class it brings, \Nino\Modules\<Directory>
		 *	- 'active'		whether that class is listed in '/nino/modules'
		 *	- 'installed'	the version activate() last recorded, null before the first
		 *	- 'update'		active with a manifest version the record does not match
		 *	- 'problems'	why it cannot be activated here, [] when it can
		 *
		 *	A directory without a readable manifest is skipped with a warning
		 *	naming it, and so is a second directory claiming a key an earlier
		 *	one holds; rejected() answers which, and why. Read once per request.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function all( array &$appData ): array {

			if( is_array( $appData['./nino/features/all'] ?? null ) === true )
				return $appData['./nino/features/all'];

			$features = [];
			$rejected = [];

			// The kernel's own come first: the key of a feature below features/ that one of them holds is refused
			$directories = [];

			foreach( [ self::_shipped() => true, self::dir() => false ] as $dir => $shipped )
				foreach( is_dir( $dir ) === true ? ( scandir( $dir ) ?: [] ) : [] as $entry )
					$directories[] = [ $dir. '/'. $entry, $entry, $shipped ];

			foreach( $directories as [ $path, $entry, $shipped ] ) {

				if( $entry[0] === '.' || is_dir( $path ) === false || is_file( $path. '/'. self::MANIFEST ) === false )
					continue;

				$manifest = self::manifest( $path, $reason, $shipped );
				if( $manifest === null ) {
					$rejected[$entry] = (string) $reason;
					continue;
				}

				if( isset( $features[ $manifest['key'] ] ) === true ) {
					trigger_error( 'Feature in '. $path. ' claims the key "'. $manifest['key']. '" that '. $features[ $manifest['key'] ]['dir']. ' already holds', E_USER_WARNING );
					$rejected[$entry] = 'claims the key "'. $manifest['key']. '" that the directory '. basename( $features[ $manifest['key'] ]['dir'] ). ' already holds';
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
			$appData['./nino/features/rejected'] = $rejected;

			return $features;
		}

		/**
		 *	The directories below features/ whose manifest all() refused, with
		 *	the reason - the sentence the warning in the log carries, in the order of the
		 *	directory, as all() reads it, for the
		 *	panel that lists the features. They are not loaded and not in all()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										directory name => reason
		 */
		public static function rejected( array &$appData ): array {

			self::all( $appData );

			return (array) ( $appData['./nino/features/rejected'] ?? [] );
		}

		/**
		 *	Whether \Nino\Modules\<Name> is a module of the kernel or of the workbench: the autoloader
		 *	serves those before a feature's, so a directory of that name is no feature's class
		 *
		 *	@param		string		$name					A directory name below features/
		 *
		 *	@return 	string|null							'the kernel' or 'the workbench', null where neither has a module of that name
		 */
		public static function moduleOwner( string $name ): ?string {

			foreach( [ 'the kernel' => dirname( __DIR__, 2 ), 'the workbench' => dirname( __DIR__, 3 ). '/_admin' ] as $owner => $root )
				if( is_dir( $root. '/Nino/Modules/'. $name ) === true )
					return $owner;

			return null;
		}

		/**
		 *	Of rejected(), the directories whose class is listed in '/nino/modules' all
		 *	the same - not the one that is named like a module of the kernel or of the workbench, whose
		 *	class listed there is that module and no feature's: a feature that was switched on and whose manifest is refused now
		 *	runs unfinished - its init() runs, its panel is in the menu - while all()
		 *	does not know it, so nothing that reads the features sees it: not the
		 *	panel's tabs, not a switch-off, not the data a backup carries
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										directory name => reason
		 */
		public static function rejectedActive( array &$appData ): array {

			$active = array_map( static fn( mixed $class ): string => '\\'. ltrim( (string) $class, '\\' ), (array) ( $appData['/nino/modules'] ?? [] ) );

			return array_filter( self::rejected( $appData ), static fn( string $directory ): bool => in_array( '\\Nino\\Modules\\'. $directory, $active, true ) === true && self::moduleOwner( $directory ) === null, ARRAY_FILTER_USE_KEY );
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
		 *	The shortcodes a feature registered, by name. Read from what is
		 *	registered now rather than from the manual's handles, which are a
		 *	convention: a shortcode belongs to the feature whose class - or a
		 *	class below its namespace, like Posts\Shortcodes - answers it.
		 *	Nothing is asked of Modules::callModules() or Html::addShortcode():
		 *	the callback is on record, and its owner can be read from it.
		 *	Only a feature that is active has registered anything
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *
		 *	@return 	array										The names, sorted; [] for an unknown key or a feature that registered none
		 */
		public static function shortcodes( array &$appData, string $key ): array {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return [];

			$module	= ltrim( $feature['module'], '\\' );
			$names	= [];

			foreach( \Nino\Html::shortcodes( $appData ) as $name )
				foreach( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/'. $name ) as $callback ) {

					$owner = \Nino\Callbacks::owner( $callback );

					if( $owner !== null && ( $owner === $module || str_starts_with( $owner, $module. '\\' ) === true ) ) {
						$names[] = $name;
						break;
					}
				}

			sort( $names );

			return $names;
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
		 *	@param		string|null	&$reason			(reference) Why the manifest was refused, null when it was not
		 *	@param		bool			$shipped			The directory is a module of the kernel that has a manifest: its name is a module's, as it is there
		 *
		 *	@return 	array|null							The manifest with every key present and normalized, plus 'dir', 'module' and 'shipped'
		 */
		public static function manifest( string $dir, ?string &$reason = null, bool $shipped = false ): ?array {

			$dir = rtrim( $dir, '/' );
			$path = $dir. '/'. self::MANIFEST;
			$name = basename( $dir );
			$reason = null;

			$fail = static function( string $why ) use ( $path, &$reason ): null {
				$reason = $why;
				trigger_error( 'Feature manifest '. $path. ': '. $why, E_USER_WARNING );
				return null;
			};

			if( preg_match( self::DIRECTORY_PATTERN, $name ) !== 1 )
				return $fail( 'the directory name must be a class name segment (Newsletter, not newsletter)' );

			// \Nino\Modules\<Name> is served by the first of the roots that holds it - _nino/,
			// _admin/, then features/ (the autoloader in Nino.php) - so the class of a feature
			// named like a module of the kernel or of the workbench, or a class below it (its
			// panel is \Nino\Modules\<Name>\Admin), is the kernel's and never the feature's:
			// a directory of that name in either root takes the name
			$owner = self::moduleOwner( $name );
			if( $owner !== null && $shipped === false )
				return $fail( 'the directory name is taken: \\Nino\\Modules\\'. $name. ' is a module of '. $owner. ', which the autoloader serves before a feature\'s' );

			if( is_file( $path ) === false )
				return $fail( 'no such file' );

			// A manifest is a php file somebody wrote: one that does not parse or throws when it is
			// included is a refusal with its reason, not a request that ends in a 500
			try {
				$raw = include $path;
			}
			catch( \Throwable $error ) {
				return $fail( 'cannot be included: '. $error::class. ': '. $error->getMessage(). ' (line '. $error->getLine(). ')' );
			}

			if( is_array( $raw ) === false )
				return $fail( 'must return an array' );

			// is_string() before the pattern, here and for the three fields
			// below: a manifest is a php file somebody put in features/, so
			// nothing says its fields are strings. A (string) cast of an
			// array raises "Array to string conversion", which the runtime
			// treats as fatal (see Runtime::NON_FATAL_LEVELS) - the reader
			// that exists to refuse a bad manifest with a sentence took the
			// site down with a 500 instead, one line before saying what was
			// wrong
			$key = $raw['key'] ?? strtolower( $name );
			if( is_string( $key ) === false || preg_match( self::KEY_PATTERN, $key ) !== 1 )
				return $fail( '"key" must be a slug' );

			if( self::localizedValid( $raw['name'] ?? '' ) === false )
				return $fail( '"name" must be a string or a locale => string map' );

			if( isset( $raw['description'] ) === true && self::localizedValid( $raw['description'] ) === false )
				return $fail( '"description" must be a string or a locale => string map' );

			// The prose a manifest may carry beyond its description: how the
			// feature is used, which the panel puts at the top of its screen.
			// Capped like a text setting - what does not fit a box in a panel
			// belongs in the docblock of the class, not in the manual
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

						if( self::localizedValid( $text ) === false )
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
				if( self::localizedValid( $raw['manual'] ) === false )
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

			// How far along it is, in the author's own words - "Beta", "Example":
			// a badge in the panel and nothing a check depends on, so no
			// vocabulary. Short, because it is drawn beside a name
			$maturity = $raw['maturity'] ?? '';
			if( $maturity !== '' && self::maturityValid( $maturity ) === false )
				return $fail( '"maturity" must be a short string or a locale => string map of them' );

			$version = $raw['version'] ?? '';
			if( is_string( $version ) === false || preg_match( self::VERSION_PATTERN, $version ) !== 1 )
				return $fail( '"version" must be major.minor.patch' );

			$nino = $raw['nino'] ?? '*';
			if( is_string( $nino ) === false || trim( $nino ) === '' || self::constraintValid( $nino ) === false )
				return $fail( '"nino" must be a version constraint such as ^1.0' );

			$module = '\\Nino\\Modules\\'. $name;
			if( isset( $raw['module'] ) === true && ( is_string( $raw['module'] ) === false || '\\'. ltrim( $raw['module'], '\\' ) !== $module ) )
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
				if( is_string( $ext ) === false || preg_match( self::EXTENSION_PATTERN, $ext ) !== 1 )
					return $fail( '"php" => "ext" must list extension names' );
				$extensions[] = strtolower( $ext );
			}

			$data = [];
			foreach( (array) ( $raw['data'] ?? [] ) as $file ) {
				/*	Something has to follow it: '/data/' does start with '/data/'
					and is not a path below it - it is the directory itself, and a
					manifest naming it claimed every file this framework keeps
					there, the transient ones included (see \Nino\Backup::NEVER,
					which no longer takes a manifest's word for that either)	*/
				if( is_string( $file ) === false || str_starts_with( $file, '/data/' ) === false
					|| trim( substr( $file, 6 ), '/' ) === '' || str_contains( $file, '..' ) === true )
					return $fail( '"data" must list paths below /data/' );
				$data[] = $file;
			}

			// The files of the feature's own assets/ directory that the site's two
			// bundles carry (see registerAssets()). The directory is the manifest's
			// own, so a manifest can name no file of another feature, and a file
			// is a style sheet or a script, never a '..' away from where it is
			$assets = [];
			$assetsDir = ( $shipped === true ? '/_nino/Nino/Modules/' : '/features/' ). $name. '/assets/';
			foreach( (array) ( $raw['assets'] ?? [] ) as $file ) {
				if( is_string( $file ) === false || str_starts_with( $file, $assetsDir ) === false
					|| preg_match( self::ASSET_PATTERN, substr( $file, strlen( $assetsDir ) ) ) !== 1 || str_contains( $file, '..' ) === true )
					return $fail( '"assets" must list css and js files below '. $assetsDir );
				$assets[] = $file;
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

			// The components and the stacks a feature declares: name => schema,
			// each read the way \Nino\Modules\Components registers a schema, so
			// that the manifest is refused here and not when a request is
			// rendered - and a component's schema as it is registered, which is
			// the feature's own: "by", the registry's word for who registered it,
			// is the key of the feature whatever the schema says, which is why the
			// reader is handed the key (and the directory, which a method named for the
			// options of an attribute is looked for in), and a tool offers it under the feature, so
			// that a schema naming a "group" is refused. The callbacks are not
			// declared but derived (see registerManifestComponents())
			$declared = [ 'components' => [], 'stacks' => [] ];
			foreach( $declared as $what => $none ) {

				if( is_array( $raw[$what] ?? [] ) === false )
					return $fail( '"'. $what. '" must be a name => schema map' );

				foreach( (array) ( $raw[$what] ?? [] ) as $name => $schema ) {

					$clean = \Nino\Modules\Components::validate( (string) $name, $schema, $what === 'stacks', $key, $dir );
					if( is_string( $clean ) === true )
						return $fail( $clean );

					$declared[$what][(string) $name] = $clean;
				}
			}

			return [
				'key'					=> $key,
				'dir'					=> $dir,
				'shipped'			=> $shipped,
				'module'			=> $module,
				'name'				=> $raw['name'],
				'description'	=> $raw['description'] ?? '',
				'manual'			=> $raw['manual'] ?? '',
				'category'		=> $category,
				'maturity'		=> $maturity,
				'version'			=> $version,
				'nino'				=> trim( $nino ),
				'php'					=> [ 'ext' => $extensions ],
				'requires'		=> $requires,
				'data'				=> $data,
				'assets'			=> $assets,
				'settings'		=> $settings,
				'components'	=> $declared['components'],
				'stacks'			=> $declared['stacks'],
			];
		}

		/**
		 *	Register what the active features declare as 'components' and
		 *	'stacks' in their manifest - the way into the registry of
		 *	\Nino\Modules\Components, beside a module calling addComponent()
		 *	itself. What a feature brings to the register of \Nino\Components,
		 *	from its folders, is registerComponents(). Each name is a shortcode whose renderer is by convention
		 *	[ '\Nino\Modules\<Directory>', 'component<Name>' ] - or 'stack<Name>' -
		 *	with the name written in studly caps: 'newsletter-signup' is
		 *	componentNewsletterSignup(). A component whose class has no such
		 *	method is rendered by the class's doShortcode() where it has one: the
		 *	shortcode a feature would write by hand and the component of its
		 *	manifest are then one method, which is handed the arguments the way
		 *	any renderer is, made ready. A stack keeps the strict rule. A renderer
		 *	the class does not have is said out loud and skipped, the way a
		 *	callback that is not callable is. A component is registered as the
		 *	feature's own - its schema says 'by' the key of the manifest (see
		 *	manifest()) - which is how a tool that lists the registry tells it
		 *	from the kernel's, and why it has no 'group': a tool offers it under
		 *	the feature.
		 *
		 *	Asked at every boot, by the Components module, and so only the
		 *	manifests of the features listed in '/nino/modules' are read: not
		 *	all() with its scan of the directory and its record of which are
		 *	active, which the workbench needs and a visitor does not
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function registerManifestComponents( array &$appData ): void {

			foreach( self::_listed( $appData ) as $manifest ) {

				if( $manifest['components'] === [] && $manifest['stacks'] === [] )
					continue;

				foreach( [ 'component' => 'components', 'stack' => 'stacks' ] as $prefix => $what )
					foreach( $manifest[$what] as $name => $schema ) {

						$callback = [ $manifest['module'], $prefix. str_replace( ' ', '', ucwords( str_replace( '-', ' ', (string) $name ) ) ) ];

						if( $what === 'components' && is_callable( $callback ) === false && is_callable( [ $manifest['module'], 'doShortcode' ] ) === true )
							$callback = [ $manifest['module'], 'doShortcode' ];

						if( is_callable( $callback ) === false ) {
							trigger_error( 'Feature manifest '. $manifest['dir']. '/'. self::MANIFEST. ': the '. $prefix. ' "'. $name. '" has no renderer '. $callback[0]. '::'. $callback[1]. '()', E_USER_WARNING );
							continue;
						}

						// A manifest is read on paper where it names a method for the options of an
						// attribute, and a class that does not load or lacks the method is found
						// here, with the check a module's registration gets: said and left out like a
						// renderer that is missing, and not the E_USER_ERROR of addComponent() on
						// every request
						$problem = null;

						if( array_filter( array_column( (array) $schema['attributes'], 'options' ), 'is_string' ) !== [] )
							try {
								$problem = \Nino\Modules\Components::validate( (string) $name, $schema, $what === 'stacks', $manifest['key'] );
							}
							catch( \Throwable $thrown ) {

								// Loading the class is what the check does: a file that does not parse, or
								// anything else that goes wrong as it loads, is a feature that is left out
								// of the registry and not a page that is a 500 for every visitor
								$problem = $prefix. ' "'. $name. '": "options": '. get_class( $thrown ). ': '. $thrown->getMessage();
							}

						if( is_string( $problem ) === true ) {
							trigger_error( 'Feature manifest '. $manifest['dir']. '/'. self::MANIFEST. ': '. $problem. ' - not registered', E_USER_WARNING );
							continue;
						}

						if( $what === 'stacks' )
							\Nino\Modules\Components::addStack( $appData, (string) $name, $callback, $schema );
						else
							\Nino\Modules\Components::addComponent( $appData, (string) $name, $callback, $schema );
					}
			}
		}

		/**
		 *	Register what the active features bring in their folders components/ and groups/ with the register of
		 *	\Nino\Components: for each feature that is listed in '/nino/modules' and has a folder components/, the
		 *	schemas as the cache of the register keeps them - where the files are as they were when it was written, and
		 *	not one of them is included - or as readComponents() reads and checks them, and then
		 *	\Nino\Components::restore(). The renderer of a component is [ the class of the feature,
		 *	'component'<Name> ], the name in studly caps, where the class has that method; a component with neither a
		 *	renderer nor a template is said and left out, as is one whose schema is refused: the feature goes on
		 *	without it, and what a request found wrong with a feature it says once.
		 *
		 *	The cache is written only where everything read was accepted, and only from what was checked: the file is
		 *	php that holds nothing but the data of the schemas. It is the cache of what a request would have to
		 *	do again and nothing a request needs: a folder that cannot be written is a feature that is read at every
		 *	request. It is never a reason for a warning, and not a thing a backup takes. '/nino/markup/cache' => false
		 *	turns it off, \Nino\Html::dropTrees() empties it.
		 *
		 *	Asked at every boot by \Nino\init(), ahead of the init() of every module
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function registerComponents( array &$appData ): void {

			// The features the kernel delivers first, then the others in the order of the modules: who has a name or a group
			// does not depend on the order a project switched them on in
			$manifests = array_merge(
				array_filter( self::_listed( $appData ), static fn( array $manifest ): bool => $manifest['shipped'] === true ),
				array_filter( self::_listed( $appData ), static fn( array $manifest ): bool => $manifest['shipped'] === false )
			);

			foreach( $manifests as $manifest ) {

				if( is_dir( $manifest['dir']. '/'. self::COMPONENTS ) === false )
					continue;

				$read = self::_register( $appData, $manifest );

				foreach( $read['rejected'] as $name => $why )
					self::_said( $appData, $manifest['key']. '/'. $name, 'Features::registerComponents(): '. $why. ' - not registered' );

				$refused = [];
				$renderers = self::_renderers( $manifest, $read['components'], $refused );

				foreach( $refused as $name => $why ) {

					self::_said( $appData, $manifest['key']. '/'. $name, 'Features::registerComponents(): '. $why. ' - not registered' );
					unset( $read['components'][$name] );
				}

				\Nino\Components::restore( $appData, $manifest['key'], $read, $renderers );
			}
		}

		/**
		 *	The renderers of the components a feature brings: [ the class of the feature, 'component'<Name> ] where the class
		 *	has that method. A component with neither that nor a template, and one that draws what it holds and has no
		 *	renderer, is refused
		 *
		 *	@param		array 		$manifest			The feature
		 *	@param		array 		$components		name => clean schema
		 *	@param		array			&$refused			(reference) name => why, of what has no renderer
		 *
		 *	@return 	array										name => callable
		 */
		private static function _renderers( array $manifest, array $components, array &$refused ): array {

			$renderers = [];

			foreach( $components as $name => $schema ) {

				$callback = [ $manifest['module'], 'component'. str_replace( ' ', '', ucwords( str_replace( '-', ' ', (string) $name ) ) ) ];

				if( is_callable( $callback ) === true )
					$renderers[$name] = $callback;
				else if( $schema['template'] === '' || isset( $schema['children'] ) === true )
					$refused[$name] = 'feature "'. $manifest['key']. '", component "'. $name. '" has no renderer '. $callback[0]. '::'. $callback[1]. '(), which it needs to '. ( isset( $schema['children'] ) === true ? 'draw what it holds' : 'draw anything: it has no template either' );
			}

			return $renderers;
		}

		/**
		 *	The schemas of the components of a feature for one request: from its file in the cache of the register
		 *	where that is the file's stamp - the kernel it was written for and the size and the time of every file
		 *	of components/ and groups/ - or read, and kept where all of it is accepted
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			The feature
		 *
		 *	@return 	array										See readComponents(); 'rejected' is [] for what comes from the cache, and the folder itself unreadable is every component refused
		 */
		private static function _register( array &$appData, array $manifest ): array {

			$on = ( $appData[\Nino\Html::TREES_SWITCH] ?? true ) !== false;
			$kept = self::REGISTER. '/'. $manifest['key']. '.php';
			$stamp = self::_stamp( $manifest['dir'] );
			$stored = $on === true ? self::_stored( $appData, $kept ) : null;

			if( $stored !== null && $stored['kernel'] === \Nino\VERSION && $stored['stamp'] === $stamp )
				return [ 'components' => $stored['components'], 'groups' => $stored['groups'], 'rejected' => [] ];

			$read = self::readComponents( $manifest['dir'], $manifest['key'] );

			if( is_string( $read ) === true )
				return [ 'components' => [], 'groups' => [], 'rejected' => [ '' => $read ] ];

			if( $on === true && $read['rejected'] === [] ) {

				// Written atomically and without a lock: two requests that write the file write the same schemas
				\Nino\Filesystem::putFileContent( $appData, $kept, [ 'kernel' => \Nino\VERSION, 'stamp' => $stamp, 'components' => $read['components'], 'groups' => $read['groups'] ], true );
				unset( $appData['./nino/filesystem/cache'][$kept] );
			}

			return $read;
		}

		/**
		 *	The size and the time of every file the schemas of a feature are read from: the component.php and the
		 *	template.tpl of each folder of components/, the group.php and the part.css of each folder of groups/ -
		 *	null for a file that is not there, so that one that comes is seen, as a folder that comes or goes is seen
		 *	by the names the lists hold. That is the limit of the comparison, and the one every cache of the kernel
		 *	has: a file of the same size written within the second of the one before it, by a tool that does not set the
		 *	time, is not seen until it changes again or the folder is emptied
		 *
		 *	@param		string		$dir					The directory of the feature
		 *
		 *	@return 	array										'<path>' => the time and the size as one number, 0 where there is no file
		 */
		private static function _stamp( string $dir ): array {

			$stamp = [];

			foreach( [ self::COMPONENTS => [ 'component.php', 'template.tpl' ], self::GROUPS => [ 'group.php', 'part/part.css' ] ] as $folder => $files ) {

				$path = $dir. '/'. $folder. '/';

				// A name that begins with a dot is none of the folders a feature has
				foreach( @scandir( $path ) ?: [] as $entry ) {

					if( $entry[0] === '.' )
						continue;

					foreach( $files as $file ) {

						// The time and the size of one file are one look at the disk - php keeps the answer of the first call for the second - and
						// one number: the size in front of the time, which is less than ten digits
						$full = $path. $entry. '/'. $file;
						$time = @filemtime( $full );
						$stamp[$full] = $time === false ? 0 : $time + filesize( $full ) * 10000000000;
					}
				}
			}

			return $stamp;
		}

		/**
		 *	What the cache of the register holds for a feature, if what is in the file is that. The file is php that the kernel
		 *	wrote from var_export() of an array; a file that does not parse, one that says something or holds another
		 *	shape is no cache and is written over. Whatever the file prints is dropped
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kept					The virtual path of the file
		 *
		 *	@return 	array|null								[ 'kernel' => ..., 'stamp' => ..., 'components' => ..., 'groups' => ... ]
		 */
		private static function _stored( array &$appData, string $kept ): ?array {

			$path = \Nino\Filesystem::path( $appData, $kept );

			if( $path === '' || is_file( $path ) === false )
				return null;

			ob_start();

			try {
				$value = ( static fn( string $path ): mixed => @include $path )( $path );
			}
			catch( \Throwable ) {
				$value = null;
			}
			finally {
				ob_end_clean();
			}

			if( is_array( $value ) === false || array_keys( $value ) !== [ 'kernel', 'stamp', 'components', 'groups' ]
				|| is_string( $value['kernel'] ) === false || is_array( $value['stamp'] ) === false
				|| is_array( $value['components'] ) === false || is_array( $value['groups'] ) === false )
				return null;

			foreach( [ 'components', 'groups' ] as $what )
				foreach( $value[$what] as $name => $entry )
					if( is_string( $name ) === false || is_array( $entry ) === false )
						return null;

			return $value;
		}

		/**
		 *	Say what a request found wrong with a feature, once for the request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$what					Which thing it is about
		 *	@param		string		$message
		 *
		 *	@return 	void
		 */
		private static function _said( array &$appData, string $what, string $message ): void {

			if( isset( $appData['./nino/features/said'][$what] ) === true )
				return;

			$appData['./nino/features/said'][$what] = true;

			trigger_error( $message, E_USER_WARNING );
		}

		/**
		 *	Read the folders components/ and groups/ of a feature: the schema of each components/<name>/component.php,
		 *	with the template.tpl beside it as its template where it names none, checked by
		 *	\Nino\Components::validate() with the key and the directory of the feature - and, for a feature's
		 *	component, with a 'group' that names a folder groups/<group>/ whose group.php is accepted by
		 *	\Nino\Components::validateGroup(). The name of the folder is the name of the component. A group that is
		 *	refused takes its components with it.
		 *
		 *	What is read is php of the feature, as its manifest is, and is included only for a feature that is on: the
		 *	request that registers it, and the Features panel for the one that is on - not for a feature that is off.
		 *	Nothing in a folder is a link: a folder, a component.php, a template.tpl, a group.php or a part.css whose
		 *	real path is not the one it is written under - a link to a file outside of the feature or inside it - is
		 *	refused, since what is read into the schemas and the style bundle of the site is not what the folder holds.
		 *	A standard part that breaks the rule of mixing (\Nino\Components::partProblems()) is refused alone: the group
		 *	and its components are the feature's, and the part is not put into the style bundle
		 *
		 *	@param		string		$dir					The directory of the feature
		 *	@param		string		$key					Its key, as the manifest says it
		 *
		 *	@return 	array|string							[ 'components' => name => clean schema, 'groups' => group => [ 'label', 'knobs', 'part' ],
		 *																	'rejected' => name, 'group <name>' or 'part <name>' => why ], or why the folders cannot be read at all
		 */
		public static function readComponents( string $dir, string $key ): array|string {

			$dir = rtrim( $dir, '/' );
			$base = realpath( $dir );

			if( $base === false )
				return 'feature "'. $key. '": the directory cannot be read';

			$components = [];
			$groups = [];
			$rejected = [];
			$of = 'feature "'. $key. '", ';

			foreach( [ self::COMPONENTS, self::GROUPS ] as $folder )
				if( file_exists( $dir. '/'. $folder ) === true && self::_inside( $base, $folder ) === false )
					return $of. 'the folder "'. $folder. '" is a link or not a folder: nothing is read from it';

			// What the folders hold: a directory is a component or a group, whatever its name is, and a file is none
			$names = [];

			foreach( @scandir( $base. '/'. self::COMPONENTS ) ?: [] as $entry )
				if( $entry[0] !== '.' && is_dir( $base. '/'. self::COMPONENTS. '/'. $entry ) === true )
					$names[] = $entry;

			foreach( $names as $name ) {

				// Nothing is included from a folder that is named no component
				if( strlen( $name ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $name ) !== 1 ) {
					$rejected[$name] = $of. 'component "'. self::_shown( $name ). '": a name is a slug of lower case letters, digits and hyphens, at most '. \Nino\Components::NAME_LENGTH. ' characters';
					continue;
				}

				$file = self::COMPONENTS. '/'. $name. '/component.php';

				if( self::_inside( $base, $file ) === false ) {
					$rejected[$name] = $of. 'component "'. self::_shown( $name ). '": the folder has no component.php of its own - a link, or none';
					continue;
				}

				$schema = self::_include( $base. '/'. $file, $failed );

				if( $failed !== null ) {
					$rejected[$name] = $of. 'component "'. self::_shown( $name ). '": component.php cannot be included: '. $failed;
					continue;
				}

				// The markup is the schema's own, or the template.tpl beside it: a file the schema names elsewhere is no file of the stamp
				if( is_array( $schema ) === true && is_string( $schema['template'] ?? null ) === true && str_ends_with( $schema['template'], '.tpl' ) === true
					&& str_contains( $schema['template'], '<' ) === false && str_contains( $schema['template'], '[[' ) === false ) {

					$rejected[$name] = $of. 'component "'. self::_shown( $name ). '": "template" is the markup, or no key at all with a template.tpl beside component.php - not the name of a file';
					continue;
				}

				// The template beside it, where the schema names none
				$template = self::COMPONENTS. '/'. $name. '/template.tpl';

				if( is_array( $schema ) === true && isset( $schema['template'] ) === false && file_exists( $base. '/'. $template ) === true ) {

					if( self::_inside( $base, $template ) === false ) {
						$rejected[$name] = $of. 'component "'. self::_shown( $name ). '": template.tpl is a link, or no file';
						continue;
					}

					$schema['template'] = $template;
				}

				$clean = \Nino\Components::validate( $name, $schema, $key, $dir );

				if( is_string( $clean ) === false && isset( $clean['group'] ) === false )
					$clean = $of. 'component "'. self::_shown( $name ). '": a component of a feature names its "group"';

				if( is_string( $clean ) === true )
					$rejected[$name] = $clean;
				else
					$components[$name] = $clean;
			}

			// The groups the components name, one folder each
			$members = [];

			foreach( $components as $name => $clean )
				$members[$clean['group']][] = $name;

			foreach( $members as $group => $own ) {

				$file = self::GROUPS. '/'. $group. '/group.php';
				$declared = null;
				$why = null;

				if( self::_inside( $base, $file ) === false )
					$why = $of. 'group "'. self::_shown( (string) $group ). '": there is no groups/'. self::_shown( (string) $group ). '/group.php of its own - a link, or none';
				else {

					$declared = self::_include( $base. '/'. $file, $failed );

					if( $failed !== null )
						$why = $of. 'group "'. self::_shown( (string) $group ). '": group.php cannot be included: '. $failed;
					else {

						$declared = \Nino\Components::validateGroup( (string) $group, $declared, $own );

						if( is_string( $declared ) === true )
							$why = $of. $declared;
					}
				}

				// The standard part is a file the style bundle of the site carries as it is
				$part = self::GROUPS. '/'. $group. '/part/part.css';

				if( $why === null && file_exists( $base. '/'. $part ) === true && self::_inside( $base, $part ) === false )
					$why = $of. 'group "'. self::_shown( (string) $group ). '": part/part.css is a link, or no file';

				if( $why !== null ) {

					$rejected['group '. $group] = $why;

					foreach( $own as $name ) {
						$rejected[$name] = $of. 'component "'. self::_shown( $name ). '": its group "'. self::_shown( (string) $group ). '" is refused';
						unset( $components[$name] );
					}

					continue;
				}

				// ...and keeps the rule of mixing, which costs 0.09 ms for the six parts of 15 KB that a feature of twenty components has (measured with
				// opcache, on a read that is a cold one: a request that has the file of the cache asks nobody). A part that breaks it does not go
				// into the bundle; the group and its components are the feature's all the same
				$standard = is_file( $base. '/'. $part ) === true;

				if( $standard === true ) {

					$css = filesize( $base. '/'. $part ) > self::PART_BYTES ? null : file_get_contents( $base. '/'. $part );
					$broken = is_string( $css ) === true ? \Nino\Components::partProblems( $css, $own ) : [ 'is longer than '. self::PART_BYTES. ' bytes' ];

					if( $broken !== [] ) {

						$rejected['part '. $group] = $of. 'group "'. self::_shown( (string) $group ). '": its standard part breaks the rule of mixing: '. implode( '; ', $broken );
						$standard = false;
					}
				}

				$groups[$group] = $declared + [ 'part' => $standard ];
			}

			return [ 'components' => $components, 'groups' => $groups, 'rejected' => $rejected ];
		}

		/**
		 *	Whether a path below the directory of a feature is a path of its own: there is a file or a folder at it
		 *	and its real path is the very path, so that nothing on the way is a link
		 *
		 *	@param		string		$base					The real path of the directory of the feature
		 *	@param		string		$relative			The path below it
		 *
		 *	@return 	bool
		 */
		private static function _inside( string $base, string $relative ): bool {

			return str_contains( $relative, "\0" ) === false && realpath( $base. '/'. $relative ) === $base. '/'. $relative;
		}

		/**
		 *	Include a php file of a feature that answers data, with no output of its own and whatever it throws
		 *	said as a sentence
		 *
		 *	@param		string		$file					An absolute path
		 *	@param		string|null	&$failed			(reference) What was thrown, null where nothing was
		 *
		 *	@return 	mixed
		 */
		private static function _include( string $file, ?string &$failed ): mixed {

			$failed = null;

			// A file that was changed is the file that is read: opcache would hand back what it compiled before, within the
			// seconds it does not look at the time of a file, and the schemas of the old text would be kept under the stamp of the new
			if( function_exists( 'opcache_invalidate' ) === true )
				@opcache_invalidate( $file, true );

			ob_start();

			try {
				return ( static fn( string $file ): mixed => include $file )( $file );
			}
			catch( \Throwable $thrown ) {

				$failed = self::_shown( $thrown::class. ': '. $thrown->getMessage(). ' (line '. $thrown->getLine(). ')', 200 );

				return null;
			}
			finally {
				ob_end_clean();
			}
		}

		/**
		 *	What a message may say of a name it was found under or of what a file threw: printable characters and not many
		 *
		 *	@param		string		$name
		 *	@param		int				$length				How many characters at most
		 *
		 *	@return 	string
		 */
		private static function _shown( string $name, int $length = 60 ): string {

			return (string) preg_replace( '/[^\x20-\x7E]/', '?', substr( $name, 0, $length ) ). ( strlen( $name ) > $length ? '...' : '' );
		}

		/**
		 *	Why a component or a group of an active feature is not registered, for the Features panel: what
		 *	readComponents() refused, and what another feature has claimed before it (the register of the
		 *	request holds who owns a name and a group). [] for a feature that is off, that has no folder components/
		 *	or whose components are all there. Reads the folders, so it is for a panel and not for a request of a visitor
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The key of a feature
		 *
		 *	@return 	array										Sentences
		 */
		public static function componentProblems( array &$appData, string $key ): array {

			$feature = self::get( $appData, $key );

			if( $feature === null || $feature['active'] === false || is_dir( $feature['dir']. '/'. self::COMPONENTS ) === false )
				return [];

			$read = self::readComponents( $feature['dir'], $key );

			if( is_string( $read ) === true )
				return [ $read ];

			$refused = [];
			self::_renderers( $feature, $read['components'], $refused );

			$problems = array_merge( array_values( $read['rejected'] ), array_values( $refused ) );

			foreach( array_keys( $read['groups'] ) as $group )
				if( ( $appData[\Nino\Components::OWNERS]['groups'][$group] ?? $key ) !== $key )
					$problems[] = 'group "'. $group. '" is the feature "'. $appData[\Nino\Components::OWNERS]['groups'][$group]. '"\'s';

			foreach( array_keys( $read['components'] ) as $name )
				if( ( $appData[\Nino\Components::OWNERS]['names'][$name] ?? $key ) !== $key )
					$problems[] = 'component "'. $name. '" is the feature "'. $appData[\Nino\Components::OWNERS]['names'][$name]. '"\'s';

			return $problems;
		}

		/**
		 *	Put the standard part of each group the features bring into the style bundle of the site: the file
		 *	groups/<group>/part/part.css of the feature, a file of the bundle like the stylesheets a manifest names - so
		 *	it stands in the layer of the components. Before that the hook '/nino/components/parts' is asked, with
		 *	[ 'group' => 'section', 'feature' => 'basis', 'css' => '/features/Basis/groups/section/part/part.css' ]: what
		 *	a callback answers as 'css' - a css file of the site, written as a path of the bundle - is the part that
		 *	goes in, and '' leaves the group without one. An answer that is no such array is said once and the standard
		 *	part stands.
		 *
		 *	Asked at every boot by \Nino\init(), behind the init() of every module, since that is where a module registers
		 *	a callback, and behind registerComponents(), which says which groups there are
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function registerParts( array &$appData ): void {

			$listed = [];

			foreach( self::_listed( $appData ) as $manifest )
				$listed[$manifest['key']] = $manifest;

			foreach( \Nino\Components::groups( $appData ) as $group => $declared ) {

				$manifest = $listed[$declared['feature']] ?? null;

				if( $declared['part'] !== true || $manifest === null )
					continue;

				$standard = ( $manifest['shipped'] === true ? '/_nino/Nino/Modules/' : '/features/' ). basename( $manifest['dir'] ). '/'. self::GROUPS. '/'. $group. '/part/part.css';
				$asked = [ 'group' => (string) $group, 'feature' => $manifest['key'], 'css' => $standard ];
				$answer = \Nino\Callbacks::doCallbacks( $appData, \Nino\Components::PARTS, $asked );

				// A stylesheet that is there, below a feature - a folder of features/ or a module of the kernel - or nothing: the bundle is served
				$named = is_array( $answer ) === true && is_string( $answer['css'] ?? null ) === true
					&& ( $answer['css'] === '' || ( preg_match( '#^/(?:features|_nino/Nino/Modules)/[A-Z][A-Za-z0-9]*/[A-Za-z0-9_][A-Za-z0-9_./-]*\.css$#D', $answer['css'] ) === 1
						&& str_contains( $answer['css'], '..' ) === false && is_file( \Nino\Filesystem::path( $appData, $answer['css'] ) ) === true ) );

				if( $named === false ) {

					self::_said( $appData, 'part '. $group, 'Features: an answer to the hook "'. \Nino\Components::PARTS. '" for the group "'. $group. '" names no stylesheet of a feature that is there and no "" - the standard part stands' );
					$answer = [ 'css' => $standard ];
				}

				if( $answer['css'] !== '' )
					\Nino\Html::addAsset( $appData, '/.cache/style.css', $answer['css'] );
			}
		}

		/**
		 *	Put the files the active features name under 'assets' into the two
		 *	bundles of the site: a style sheet into '/.cache/style.css', a script
		 *	into '/.cache/script.js', each once and in the order the manifest
		 *	lists it. The paths are virtual, '/features/<Directory>/assets/<file>',
		 *	which \Nino\Filesystem resolves against the features directory wherever
		 *	NINO_FEATURES_DIR put it. A feature that wants its files in another
		 *	bundle calls \Nino\Html::addAsset() itself, in its init().
		 *
		 *	Asked at every boot by \Nino\init(), ahead of the init() of every module,
		 *	and so what a feature adds to a bundle itself comes after the files
		 *	of its manifest. All of them stand behind the kernel's files and in front
		 *	of the project's own files below '/assets/' in the list (see
		 *	\Nino\Html::addAsset()).
		 *
		 *	Only the manifests of the features listed in '/nino/modules' are read,
		 *	as registerManifestComponents() reads them.
		 *
		 *	Not the 'assets' of a stack's schema (\Nino\Modules\Components::validate()),
		 *	which is a note for a tool about the files a stack needs and adds nothing
		 *	to a page.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function registerAssets( array &$appData ): void {

			foreach( self::_listed( $appData ) as $manifest )
				foreach( $manifest['assets'] as $path )
					\Nino\Html::addAsset( $appData, str_ends_with( $path, '.css' ) === true ? '/.cache/style.css' : '/.cache/script.js', $path );
		}

		/**
		 *	The manifests of the features listed in '/nino/modules', in that
		 *	order: the walk the readers of a boot share, registerAssets(),
		 *	registerComponents() and registerParts(). A class that is no feature's, a directory
		 *	without a manifest and a manifest that does not validate - said in a
		 *	warning, as manifest() does - are left out.
		 *
		 *	The walk is kept for the request in './nino/features/listed', beside
		 *	the list it was made for: a manifest is read, and a bad one said, once
		 *	per boot however many readers ask, and a list that has changed since is
		 *	walked again.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										The manifests, as manifest() answers them
		 */
		private static function _listed( array &$appData ): array {

			$modules = (array) ( $appData['/nino/modules'] ?? [] );

			if( ( $appData['./nino/features/listed']['modules'] ?? null ) === $modules )
				return $appData['./nino/features/listed']['manifests'];

			$listed = [];

			foreach( $modules as $class ) {

				if( preg_match( '/^\\\\?Nino\\\\Modules\\\\([A-Z][A-Za-z0-9]*)$/D', (string) $class, $match ) !== 1 )
					continue;

				// A module of the kernel with a manifest first, as all() and the autoloader have it: a directory below features/ that is
				// named like one is refused there and is no feature of its own
				$shipped = true;
				$dir = self::_shipped(). '/'. $match[1];

				if( is_file( $dir. '/'. self::MANIFEST ) === false ) {

					$shipped = false;
					$dir = self::dir(). '/'. $match[1];

					if( is_file( $dir. '/'. self::MANIFEST ) === false )
						continue;
				}

				$manifest = self::manifest( $dir, $reason, $shipped );

				if( $manifest !== null )
					$listed[] = $manifest;
			}

			$appData['./nino/features/listed'] = [ 'modules' => $modules, 'manifests' => $listed ];

			return $listed;
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

				// $holds starts false and is earned by a part that held. It
				// used to start true, so an alternative with no parts at all -
				// what '^9.0 ||' splits into - was satisfied by every version
				// there is: a feature written for a Nino nobody has yet
				// installed on 1.x without a word. constraintValid() refuses
				// that shape now too, so this is the second of two doors
				$holds = false;
				foreach( preg_split( '/[\s,]+/', trim( $alternative ) ) ?: [] as $part ) {
					if( $part === '' )
						continue;
					if( self::_satisfiesOne( $part, $version ) === false ) {
						$holds = false;
						break;
					}
					$holds = true;
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
		 *	Per type - the form posts strings, the clean value has the type:
		 *	bool takes 'true'/'false', 1/0 and '1'/'0' and nothing else; int a
		 *	string of digits within min and max ('5.5' is none); string one
		 *	trimmed line of at most maxlength (1 to 1000, default 1000) that
		 *	matches its pattern where it has one, an empty optional value
		 *	passing the pattern by; text the same over several lines, maxlength
		 *	up to 10000; email FILTER_VALIDATE_EMAIL; url FILTER_VALIDATE_URL
		 *	with an http or https scheme; select exactly one of its options, or
		 *	'' unless required; secret '' keeps, null clears, anything else
		 *	replaces; lines a textarea split at line breaks or a list as it is,
		 *	every line trimmed, empty lines and duplicates dropped, at most
		 *	1000 characters per line and 200 lines. 'required' refuses an empty
		 *	value of any type.
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

			$locked = self::lock( $appData );
			if( $locked !== true )
				return [ '' => $locked ];

			try {
				return self::_saveSettings( $appData, $key, $posted );
			}
			finally {
				self::unlock( $appData );
			}
		}

		/**
		 *	The step of saveSettings(), under the lock of the features. The
		 *	record is read, changed for this one feature and written on the file
		 *	itself: the versions another process recorded meanwhile are not
		 *	written back as this request booted with them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		array 		$posted				name => posted value
		 *
		 *	@return 	array										name => message for every rejected setting; [] when saved
		 */
		private static function _saveSettings( array &$appData, string $key, array $posted ): array {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return [ '' => 'unknown feature "'. $key. '"' ];

			$errors = [];

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', function( array $content ) use ( $feature, $key, $posted, &$errors ): ?array {

				$state	= is_array( $content[ self::STATE_KEY ] ?? null ) ? $content[ self::STATE_KEY ] : [];
				$current = is_array( $state[$key]['settings'] ?? null ) ? $state[$key]['settings'] : [];

				$result = self::validateSettings( $feature['settings'], $posted, $current );
				if( $result['errors'] !== [] ) {
					$errors = $result['errors'];
					return null;
				}

				$state[$key] = [
					'version'		=> $state[$key]['version'] ?? $feature['installed'],
					'settings'	=> $result['values'],
				];
				$content[ self::STATE_KEY ] = $state;

				return $content;
			} );

			if( $errors !== [] )
				return $errors;

			if( $written === false )
				return [ '' => 'could not write config.php' ];

			$persisted = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$appData[ self::STATE_KEY ] = is_array( $persisted ) === true && is_array( $persisted[ self::STATE_KEY ] ?? null ) === true ? $persisted[ self::STATE_KEY ] : [];

			return [];
		}

		/*	Where the lock every change of a feature's state is made under lives:
			a virtual path in the shape \Nino\Modules\Backups::LOCK_PATH has, whose
			side-car file in data/.locks is what is locked (see
			\Nino\Filesystem::lockFile())	*/
		public const string LOCK_PATH = '/nino/features/lock';

		// What a change answers where another one holds the lock
		public const string BUSY = 'another change of the features is running';

		// What a change answers where the lock cannot be had at all
		public const string UNLOCKABLE = 'the features cannot be locked for a change';

		/**
		 *	Take the lock every change of a feature's state is made under -
		 *	placing a directory, switching on, switching off, removing, applying
		 *	an update, saving settings - so that between "the directory is there"
		 *	and "config.php lists its class" no other process removes it, and the
		 *	other way round. One lock for all of them, and not waited for: a
		 *	change that finds it taken answers busy at once (the caller's 409)
		 *	instead of queueing behind a download. It is released when the process
		 *	ends, however it ends.
		 *
		 *	Taken again by the same request it counts up and is given back when
		 *	every taker has called unlock(): activate() takes it for the feature
		 *	and for each requirement, the panel for an install that activates.
		 *
		 *	What this request knew about the features when it booted may be
		 *	older than the lock: another process can have switched a feature on,
		 *	updated it or taken a directory away in between. So when the lock is
		 *	taken the module list and the record of the versions are read from
		 *	config.php again and the registry is forgotten. That is the state a
		 *	change reads, changes and writes (Filesystem::mutate() on the file):
		 *	nothing this request booted with that the file does not carry is
		 *	written back. A module in the list of this request that config.php
		 *	does not list is not kept - it is either one that was taken out
		 *	meanwhile or one that only this request registered, and neither is a
		 *	thing a change of the features may write.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	true|string							True, BUSY while another change holds the lock, UNLOCKABLE when the lock file cannot be opened at all (the reason is in the log)
		 */
		public static function lock( array &$appData ): true|string {

			$depth = (int) ( $appData['./nino/features/lock'] ?? 0 );

			if( $depth > 0 ) {
				$appData['./nino/features/lock'] = $depth + 1;
				return true;
			}

			if( \Nino\Filesystem::lockFile( $appData, self::LOCK_PATH, false ) === false ) {

				// Busy is the lock being taken. A lock file that cannot be opened -
				// the directory of the locks missing or not writable, a file of
				// another owner, a directory in its place - is not busy, and asking
				// again does not help: its own answer, and the log has where
				if( \Nino\Filesystem::lockUsable( $appData, self::LOCK_PATH ) === false ) {
					trigger_error( 'Features::lock(): the lock file of the features cannot be opened - check the owner and the permissions of '. \Nino\Filesystem::path( $appData, '/data' ). '/.locks and of the file in it', E_USER_WARNING );
					return self::UNLOCKABLE;
				}

				return self::BUSY;
			}

			$appData['./nino/features/lock'] = 1;

			$appData['./nino/filesystem/cache']['/config.php']['fstat'] = [];
			$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			if( is_array( $stored ) === true ) {

				if( is_array( $stored['/nino/modules'] ?? null ) === true )
					$appData['/nino/modules'] = array_values( $stored['/nino/modules'] );

				$appData[ self::STATE_KEY ] = is_array( $stored[ self::STATE_KEY ] ?? null ) === true ? $stored[ self::STATE_KEY ] : [];
			}

			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	Give the lock of lock() back; the last taker of a request releases it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function unlock( array &$appData ): void {

			$depth = (int) ( $appData['./nino/features/lock'] ?? 0 );

			if( $depth > 1 ) {
				$appData['./nino/features/lock'] = $depth - 1;
				return;
			}

			if( $depth === 1 ) {
				unset( $appData['./nino/features/lock'] );
				\Nino\Filesystem::unlockFile( $appData, self::LOCK_PATH );
			}
		}

		/**
		 *	Switch a feature on - the one step there is. Its requirements are
		 *	activated first, its install/ unit is applied without touching a
		 *	file or a text key the project already has, its class is listed
		 *	in '/nino/modules', its version recorded. Activating an active
		 *	feature is how an update is applied: the unit adds what is new,
		 *	and a module that implements upgrade( &$appData, $fromVersion )
		 *	gets to migrate its own data before the new version is recorded.
		 *	Made under the lock of the features (see lock()).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		array 		$chain				Keys already being activated, against a requirement cycle
		 *	@param		array|null	&$report		(reference) What the unit of this feature added and what the project had already, see applyUnit(); the requirements' units are not in it
		 *	@param		bool			$reapply			The unit is applied once more to a feature that is on (the panel's Apply content): the elements of a type the project has are left alone, see applyUnit()
		 *
		 *	@return 	true|string							True, or why not - BUSY while another change of the features holds the lock
		 */
		public static function activate( array &$appData, string $key, array $chain = [], ?array &$report = null, bool $reapply = false ): true|string {

			$locked = self::lock( $appData );
			if( $locked !== true )
				return $locked;

			try {
				return self::_activate( $appData, $key, $chain, $report, $reapply );
			}
			finally {
				\Nino\Html::dropTrees( $appData );
				self::unlock( $appData );
			}
		}

		/**
		 *	The step of activate(), under the lock of the features
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		array 		$chain				Keys already being activated
		 *	@param		array|null	&$report		(reference) See activate()
		 *	@param		bool			$reapply			See activate()
		 *
		 *	@return 	true|string
		 */
		private static function _activate( array &$appData, string $key, array $chain, ?array &$report = null, bool $reapply = false ): true|string {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return 'unknown feature "'. $key. '"';

			if( $feature['problems'] !== [] )
				return 'feature "'. $key. '" cannot be activated: '. implode( '; ', $feature['problems'] );

			if( in_array( $key, $chain, true ) === true )
				return true;
			$chain[] = $key;

			foreach( $feature['requires'] as $required ) {

				/*	A requirement that is already on, at the version its own
					directory carries and with nothing wrong with it, has nothing
					left for an activation to do: the unit only ever adds what the
					project does not have, the module is already listed, and the
					recorded version is already this one. What it did cost was a
					full unit pass - every file copied or skipped, every text key
					walked, config.php written - once per requirement, on every
					activation of anything that requires it.

					'installed' rather than 'active' alone, because a newer
					directory placed while the feature was off is an update and
					that has to run; and 'problems' because a requirement that
					has become unrunnable must still be refused here, loudly,
					rather than quietly skipped	*/
				$have = self::get( $appData, $required );

				if( $have !== null && $have['active'] === true && $have['problems'] === [] && $have['installed'] === $have['version'] )
					continue;

				$result = self::activate( $appData, $required, $chain );
				if( $result !== true )
					return 'required feature "'. $required. '": '. $result;
			}

			/*	Replaced in this request while its class was already loaded -
				Catalogue::install() notes that - so the class in memory is the
				previous version's: method_exists() below answers for it, and
				the hook the new version brought cannot run here. Once the new
				version is recorded nothing runs it later either. Measured on
				the panel's update of a running feature: the new version was
				recorded with its upgrade() never called, and never to be
				called. Refused as the deferral it is, before anything is
				touched; the panel activates again in a request of its own	*/
			if( isset( $appData['./nino/features/replaced'][$key] ) === true && $feature['installed'] !== null && $feature['installed'] !== $feature['version'] )
				return 'feature "'. $key. '" was replaced in this request while its previous version is loaded - the update is applied by activating it in a new request';

			// The persisted routes, never the live ones - the live array
			// carries this request's own runtime routes (the workbench's, the
			// active modules' self-registered endpoints), which must not be
			// written into config.php. Same rule as the wizard's Setup step.
			$routes 		= \Nino\AppData::stored( $appData, '/nino/http/routes', \Nino\AppData::DEFAULTS['/nino/http/routes'] );
			$routes 		= is_array( $routes ) ? $routes : \Nino\AppData::DEFAULTS['/nino/http/routes'];
			$routesBefore	= $routes;
			$blacklist	= [];
			$config			= [];

			// A unit file that could not be copied is a refusal, before
			// anything is listed or recorded - see applyUnit()
			if( is_dir( $feature['dir']. '/install' ) === true ) {
				$applied = self::applyUnit( $appData, $feature['dir']. '/install', \Nino\Locales::getAvailableLocales( $appData ), $routes, $blacklist, $config, false, $report, $reapply );
				if( $applied !== true )
					return 'feature "'. $key. '" could not be activated: '. $applied;
			}

			$class 			= $feature['module'];

			// The record, not the active flag, says whether this is an update:
			// deactivate() keeps the recorded version on purpose, and a newer
			// directory placed while the feature was off (the panel's install
			// leaves a switched-off feature off) reaches the hook on the
			// activation that follows. Running it only for a feature already
			// on skipped the migration on exactly that path - and recorded the
			// new version, so no later activation could run it either
			if( $feature['installed'] !== null && $feature['installed'] !== $feature['version'] && method_exists( $class, 'upgrade' ) === true )
				if( $class::upgrade( $appData, (string) $feature['installed'] ) === false )
					return 'feature "'. $key. '" refused to upgrade from '. $feature['installed'];

			/*	What this activation adds, rather than what config.php held when
				the snapshot above was taken. Everything between that read and
				this point can have written the file: applyUnit() merges text
				files, and the upgrade hook is a feature's own code, which
				docs/features.md invites to migrate config.php with the kernel's
				own mutate(). Persisting the snapshot reverted whatever they did -
				a hook's migration silently undone by the activation that called
				it, in one process, no second request needed.

				So the three keys are written as the differences they are, against
				the file as it stands at lock time. isset() rather than
				array_key_exists() on the routes, because that is the test
				applyUnit() itself applied against the snapshot	*/
			$added = [];
			foreach( $routes as $routeKey => $route )
				if( ( $routesBefore[$routeKey] ?? null ) !== $route )
					$added[$routeKey] = $route;

			$live = (array) ( $appData['/nino/http/routes'] ?? [] );
			$version = $feature['version'];

			/*	E_USER_ERROR, the level writeContentData() raises for the same
				thing: a config.php write that is dropped rather than made has
				to be loud, because what is lost is a route, a module list or a
				version record. The refusal below it is for a handler that
				decides to carry on - the same shape AppData and Elements use,
				and the same phpstan-baseline entry	*/
			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				trigger_error( 'Features::activate(): could not lock config.php for writing.', E_USER_ERROR );
				return 'could not write config.php';
			}

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ) use ( $class, $key, $version, $added, $config ): array {

				// Whether the class is in the file, not whether this request
				// thought the feature was on: the two can differ, and what an
				// activation means is that the class is listed afterwards
				$modules = array_values( (array) ( $content['/nino/modules'] ?? [] ) );
				if( in_array( $class, $modules, true ) === false )
					$modules[] = $class;
				$content['/nino/modules'] = array_values( array_unique( $modules ) );

				$state = is_array( $content[ self::STATE_KEY ] ?? null ) ? $content[ self::STATE_KEY ] : [];
				$state[$key] = [
					'version'		=> $version,
					'settings'	=> is_array( $state[$key]['settings'] ?? null ) ? $state[$key]['settings'] : [],
				];
				$content[ self::STATE_KEY ] = $state;

				if( $added !== [] ) {

					$stored = is_array( $content['/nino/http/routes'] ?? null ) ? $content['/nino/http/routes'] : \Nino\AppData::DEFAULTS['/nino/http/routes'];

					foreach( $added as $routeKey => $route )
						if( isset( $stored[$routeKey] ) === false )
							$stored[$routeKey] = $route;

					$content['/nino/http/routes'] = $stored;
				}

				// The unit's own defaults, still only where the file has nothing:
				// applyUnit() decided that against $appData, and the file is what
				// it has to hold against
				foreach( $config as $configKey => $configValue )
					if( $configKey === self::IMAGES_KEY )
						$content[$configKey] = ( is_array( $content[$configKey] ?? null ) === true ? $content[$configKey] : [] ) + $configValue;
					elseif( isset( $content[$configKey] ) === false )
						$content[$configKey] = $configValue;

				return $content;
			} );

			// The live routes back, with the unit's new ones added: this
			// request goes on with everything it booted with
			$appData['/nino/http/routes'] = $live + $routes;

			if( $written === false ) {
				trigger_error( 'Features::activate(): failed to write config.php.', E_USER_ERROR );
				return 'could not write config.php';
			}

			// What was written, read back into this request: the file is the
			// decision now, not the copy this request booted with
			$persisted = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$persisted = is_array( $persisted ) ? $persisted : [];
			$appData['/nino/modules'] = array_values( (array) ( $persisted['/nino/modules'] ?? [] ) );
			$appData[ self::STATE_KEY ] = is_array( $persisted[ self::STATE_KEY ] ?? null ) ? $persisted[ self::STATE_KEY ] : [];

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
		 *	another active feature requires it. Made under the lock of the features
		 *	(see lock()).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *
		 *	@return 	true|string							True, or why not - BUSY while another change of the features holds the lock
		 */
		public static function deactivate( array &$appData, string $key ): true|string {

			$locked = self::lock( $appData );
			if( $locked !== true )
				return $locked;

			try {
				return self::_deactivate( $appData, $key );
			}
			finally {
				\Nino\Html::dropTrees( $appData );
				self::unlock( $appData );
			}
		}

		/**
		 *	The step of deactivate(), under the lock of the features
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *
		 *	@return 	true|string
		 */
		private static function _deactivate( array &$appData, string $key ): true|string {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return 'unknown feature "'. $key. '"';

			foreach( self::all( $appData ) as $other )
				if( $other['active'] === true && in_array( $key, $other['requires'], true ) === true )
					return 'feature "'. $key. '" is required by "'. $other['key']. '"';

			if( $feature['active'] === false )
				return true;

			$class = $feature['module'];

			$unlisted = self::_unlist( $appData, $class );

			if( $unlisted !== true )
				return $unlisted;

			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	Take a class out of '/nino/modules', read, changed and written on the
		 *	file under the lock: the list of this request is the one it booted with
		 *	or read when it took the lock (see lock()), and a list written from
		 *	memory is a list that can bring back a class somebody took out. The
		 *	list of this request follows the file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$class				The class with its leading separator
		 *
		 *	@return 	true|string							True, or why not
		 */
		private static function _unlist( array &$appData, string $class ): true|string {

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content, array &$appData ) use ( $class ): array {

				$list = is_array( $content['/nino/modules'] ?? null ) === true ? $content['/nino/modules'] : (array) ( $appData['/nino/modules'] ?? [] );

				$content['/nino/modules'] = array_values( array_filter(
					$list,
					static fn( mixed $listed ): bool => '\\'. ltrim( (string) $listed, '\\' ) !== $class
				) );

				return $content;
			} );

			if( $written === false ) {
				trigger_error( 'Features: failed to write config.php.', E_USER_WARNING );
				return 'could not write config.php';
			}

			$persisted = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$appData['/nino/modules'] = array_values( (array) ( is_array( $persisted ) === true ? ( $persisted['/nino/modules'] ?? [] ) : [] ) );

			return true;
		}

		/**
		 *	Switch off a feature that runs although its manifest is refused (see
		 *	rejectedActive()): its class leaves '/nino/modules', and that is all.
		 *	deactivate() does not know the key of such a directory, so it cannot be
		 *	asked; what a switch-off otherwise reads from the manifest - whether another feature requires
		 *	it - is not known here either, and nothing runs that belongs to a feature
		 *	that is known. The directory stays, and with it what the feature kept.
		 *	Made under the lock of the features (see lock()).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$directory		The directory below features/, as rejectedActive() names it
		 *
		 *	@return 	true|string							True, or why not - BUSY while another change of the features holds the lock
		 */
		public static function deactivateRefused( array &$appData, string $directory ): true|string {

			$locked = self::lock( $appData );
			if( $locked !== true )
				return $locked;

			try {

				if( isset( self::rejectedActive( $appData )[$directory] ) === false )
					return 'unknown directory "'. $directory. '"';

				$unlisted = self::_unlist( $appData, '\\Nino\\Modules\\'. $directory );

				if( $unlisted !== true )
					return $unlisted;

				unset( $appData['./nino/features/all'] );

				return true;
			}
			finally {
				\Nino\Html::dropTrees( $appData );
				self::unlock( $appData );
			}
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
		 *	Refused for a feature that is delivered with the kernel (a module of it with a
		 *	manifest, 'shipped'), and for an active feature. Its class is listed in
		 *	'/nino/modules', so removing the directory would leave the
		 *	autoloader looking for a class that is not there - switch it off
		 *	first, which is one click and says what it is doing. Made under the
		 *	lock of the features (see lock()), so that no switching on slips in
		 *	between the look at the directory and its deletion.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The feature's key
		 *
		 *	@return 	true|string							true, or why not - BUSY while another change of the features holds the lock
		 */
		public static function remove( array &$appData, string $key ): true|string {

			$locked = self::lock( $appData );
			if( $locked !== true )
				return $locked;

			try {
				return self::_remove( $appData, $key );
			}
			finally {
				\Nino\Html::dropTrees( $appData );
				self::unlock( $appData );
			}
		}

		/**
		 *	The step of remove(), under the lock of the features
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The feature's key
		 *
		 *	@return 	true|string
		 */
		private static function _remove( array &$appData, string $key ): true|string {

			$feature = self::get( $appData, $key );
			if( $feature === null )
				return 'unknown feature "'. $key. '"';

			if( $feature['shipped'] === true )
				return 'feature "'. $key. '" is delivered with the kernel and has no directory of its own to remove';

			if( $feature['active'] === true )
				return 'feature "'. $key. '" is active - switch it off before removing it';

			// all() only ever reads directories below dir(), so this cannot
			// currently be anything else. Checked anyway: it is the guard
			// between a key from a request and a recursive delete
			$dir = $feature['dir'];
			if( str_starts_with( $dir, self::dir(). '/' ) === false || str_contains( $dir, '..' ) === true )
				return 'feature "'. $key. '" does not live below the features directory';

			self::invalidateOpcache( $dir );

			// A feature reached through a symlink - a checkout linked into
			// features/ during development, or a deployment that assembles the
			// directory out of links - goes by dropping the link, never by
			// deleting what it points at. removeDir() refuses to follow a link
			// at all, which is right, but it declined to remove the link either:
			// the feature stayed where it was, is_dir() followed the link and
			// still said yes, and the operator was told the web server may not
			// write there - which was not the reason, and not a thing they could
			// do anything about. Dropping the link leaves the checkout on the
			// other side of it alone
			if( is_link( $dir ) === true )
				@unlink( $dir );
			else
				\Nino\Filesystem::removeDir( $dir );

			// The path is for the log of the house: the sentence goes to the browser
			if( is_dir( $dir ) === true ) {
				trigger_error( 'Features::remove(): could not remove '. $dir, E_USER_WARNING );
				return 'could not remove the directory of feature "'. $key. '" - the web server may not write there';
			}

			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	Drop every php file below a directory from opcache - around a
		 *	directory being swapped for another at the same paths (a catalogue
		 *	install or update), and before one is deleted. opcache looks at a
		 *	file's timestamp every couple of seconds at most, and not at all
		 *	where validate_timestamps is off; \Nino\Filesystem does the same
		 *	for the one file it writes
		 *
		 *	@param		string		$dir					Absolute directory
		 *
		 *	@return 	void
		 */
		public static function invalidateOpcache( string $dir ): void {

			if( function_exists( 'opcache_invalidate' ) === false || is_dir( $dir ) === false )
				return;

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
				if( $file->isFile() === true && strtolower( $file->getExtension() ) === 'php' )
					opcache_invalidate( $file->getPathname(), true );
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
		 *	add its 'elements' to the types they name, collect its blacklist
		 *	entries and its config defaults for keys the project has nothing
		 *	for yet, and merge its text/global.php and
		 *	text/<locale>.php fragments into the real /text files (see
		 *	applyUnitTexts(): a language that has no fragment gets the keys of
		 *	another's, empty).
		 *
		 *	The config defaults are collected, not written. This method used to
		 *	write them itself, and config.php cannot be both written here and
		 *	decided by the caller under one lock - which is what activate() needs
		 *	in order not to commit a snapshot it read before this ran.
		 *
		 *	With $overwrite the unit wins, which is what the wizard wants: a
		 *	re-applied unit replaces what it copied before. Without it the
		 *	project wins - a route, a file, a text key that exists stays as it
		 *	is and only what is missing is added. That is what a feature
		 *	activation and an update want. A route key under self::RESERVED counts as one the project has: the setup wizard sets it for the request that places a feature of an application whose start page is the wizard's own. 'elements' are the exception to
		 *	"the unit wins": they are content, so they are added in both cases
		 *	and never replaced - a re-run of the wizard keeps what an editor
		 *	wrote (see \Nino\Elements::seed()).
		 *
		 *	'imageSlots' gives the unit's templates their picture places (see
		 *	imageSlots()). A place is added to the project's where it has none of that uri, and handed back in $config
		 *	under '/nino/html/images' as the places it adds, which a caller adds to
		 *	the file the way it adds the rest of $config.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitDir			Absolute path to the unit
		 *	@param		array 		$locales			The locales to apply for
		 *	@param		array 		&$routes			(reference) Routes accumulator - the persisted routes, never the live ones
		 *	@param		array 		&$blacklist		(reference) Collected blacklist keys, appended to
		 *	@param		array 		&$config			(reference) Collected config defaults, key => value, for the caller to write
		 *	@param		bool			$overwrite		Whether the unit replaces what the project has
		 *	@param		array|null	&$report		(reference) What the unit added and what the project had already, kind by kind: 'routes', 'files', 'templates', 'types', 'slots', 'elements' and 'texts', each { added, present } - read before each is placed, so that a second run says it added nothing. What $overwrite replaces counts as added
		 *	@param		bool			$reapply			The unit is applied to a feature that is on already: elements are content, and one the project deleted is not brought back, so the elements of a type the project had before this run are not seeded - a type it did not have comes with its elements
		 *
		 *	@return 	true|string							true, or the first file the unit could not
		 *																	copy or write - named the way the project
		 *																	sees it, eg. '/templates/page-x.tpl'
		 */
		public static function applyUnit( array &$appData, string $unitDir, array $locales, array &$routes, array &$blacklist, array &$config, bool $overwrite = true, ?array &$report = null, bool $reapply = false ): true|string {

			$unitDir	= rtrim( $unitDir, '/' );
			$manifest	= self::readUnitManifest( $unitDir ) ?? [];

			// Which types of the unit's elements the project has before anything is placed: a type file the unit copies is no reason to seed it
			$hadTypes = [];
			foreach( array_keys( (array) ( $manifest['elements'] ?? [] ) ) as $typeName )
				$hadTypes[ trim( (string) $typeName, '/' ) ] = \Nino\Filesystem::fileExists( $appData, '/elements/'. trim( (string) $typeName, '/' ). '.php' );

			$report	= array_fill_keys( [ 'routes', 'files', 'templates', 'types', 'slots', 'elements', 'texts' ], [ 'added' => 0, 'present' => 0 ] );

			foreach( ( $manifest['routes'] ?? [] ) as $routeKey => $route ) {
				if( isset( $route['locale'] ) === true && in_array( $route['locale'], $locales, true ) === false )
					continue;
				if( $overwrite === false && ( isset( $routes[$routeKey] ) === true || in_array( $routeKey, (array) ( $appData[self::RESERVED] ?? [] ), true ) === true ) ) {
					$report['routes']['present']++;
					continue;
				}
				$report['routes']['added']++;
				$routes[$routeKey] = $route;
			}

			/*	Files before templates, and it is not cosmetic: base ships the
				deny rule for the private tree itself (private/.htaccess), and
				base is the first unit the wizard applies. Copying templates
				first would create private/ through forceDir() and only protect
				it a few statements later - a window, however short, in which a
				failed request could leave the directory readable with a
				project's templates already in it.	*/
			/*	Every copy below is checked, and the first one that failed is the
				answer. Unchecked, a target that could not be written was either
				php's own warning - which the framework's handler ends the
				request on, a 500 with half the unit copied - or, under a handler
				that carries on, nothing at all: the caller listed the class and
				recorded the version over a template that was never written	*/
			foreach( ( $manifest['files'] ?? [] ) as $file ) {
				self::_tally( $report['files'], $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ), $overwrite );
				if( self::copyTree( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/'. $file ), $overwrite ) === false )
					return 'could not copy /'. $file;
			}

			if( count( $manifest['templates'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/templates' );
				foreach( $manifest['templates'] as $locale => $file ) {
					if( is_string( $locale ) === true && in_array( $locale, $locales, true ) === false )
						continue;
					self::_tally( $report['templates'], $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ), $overwrite );
					if( self::copyFile( $unitDir. '/templates/'. $file, \Nino\Filesystem::path( $appData, '/templates/'. $file ), $overwrite ) === false )
						return 'could not copy /templates/'. $file;
				}
			}

			/*	The picture places the unit's templates show, checked once its files
				are in place: only the ones the project has no place of that uri for
				are added, and the places that are new travel in $config - the caller
				adds them to the file as it adds the rest, place by place	*/
			$slots = [];
			$seeded = self::imageSlots( $appData, $manifest, $slots );

			if( $seeded !== true )
				return $seeded;

			$have = is_array( $appData[self::IMAGES_KEY] ?? null ) === true ? $appData[self::IMAGES_KEY] : [];
			$new = array_diff_key( $slots, $have );

			$report['slots'] = [ 'added' => count( $new ), 'present' => count( $slots ) - count( $new ) ];

			if( $new !== [] ) {
				$appData[self::IMAGES_KEY] = $have + $new;
				$config[self::IMAGES_KEY] = ( is_array( $config[self::IMAGES_KEY] ?? null ) === true ? $config[self::IMAGES_KEY] : [] ) + $new;
			}

			if( count( $manifest['elementTypes'] ?? [] ) > 0 ) {
				\Nino\Filesystem::forceDir( $appData, '/elements' );
				foreach( $manifest['elementTypes'] as $file ) {
					self::_tally( $report['types'], $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/elements/'. $file ), $overwrite );
					if( self::copyFile( $unitDir. '/'. $file, \Nino\Filesystem::path( $appData, '/elements/'. $file ), $overwrite ) === false )
						return 'could not copy /elements/'. $file;
				}
			}

			/*	Elements, which are content: added to a type, whatever $overwrite
				says, and never replacing a value or an element the project has
				(\Nino\Elements::seed()). The file is one of the unit's own, named
				without a way out of its directory - a manifest is trusted like the
				code beside it, but a path is still no place to be careless	*/
			foreach( ( $manifest['elements'] ?? [] ) as $typeUri => $file ) {

				$source = is_string( $file ) === true ? $unitDir. '/'. $file : '';

				if( is_string( $typeUri ) === false || is_string( $file ) === false || $file === '' || str_contains( $file, '..' ) === true || str_contains( $file, "\0" ) === true
					|| str_starts_with( $file, '/' ) === true || is_file( $source ) === false )
					return 'could not read the elements of "'. ( is_string( $typeUri ) === true ? $typeUri : '' ). '"';

				$content = include $source;

				if( is_array( $content ) === false )
					return 'could not read the elements of "'. $typeUri. '"';

				if( $reapply === true && ( $hadTypes[ trim( $typeUri, '/' ) ] ?? false ) === true )
					continue;

				$counted = [ 0, 0 ];
				$seeded = \Nino\Elements::seed( $appData, $typeUri, $content, $locales, $counted );

				if( $seeded !== true )
					return $seeded;

				$report['elements']['added'] += $counted[0];
				$report['elements']['present'] += $counted[1];
			}

			foreach( ( $manifest['blacklist'] ?? [] ) as $key )
				$blacklist[] = $key;

			/*	A unit's own config defaults - only filled in where the project
				has nothing yet, never overwritten: re-applying a unit must not
				reset a value the developer has edited since.

				Handed to the caller rather than written here. Writing them was
				one full rewrite of config.php per unit at best, and it made the
				file impossible to decide under a lock: putFileContent() releases
				the lock at the end of every write, so a caller holding one lost
				it here. activate() now writes every key it decides, these
				included, in one locked read-modify-write at the end. $appData
				still gets the value, because the rest of this request reads it	*/
			foreach( ( $manifest['config'] ?? [] ) as $configKey => $configValue )
				if( isset( $appData[$configKey] ) === false ) {
					$appData[$configKey] = $configValue;
					$config[$configKey] = $configValue;
				}

			$present = 0;
			$texts = self::applyUnitTexts( $appData, $unitDir, $locales, $overwrite, $present );

			if( is_string( $texts ) === true )
				return 'could not write '. $texts;

			$report['texts'] = [ 'added' => $texts, 'present' => $present ];

			return true;
		}

		/**
		 *	The image slots a unit declares, 'imageSlots' in its manifest -
		 *	uri => [ 'label' => string or locale map, 'width', 'height' and
		 *	optionally 'filename' ] - checked and collected into $into for the
		 *	caller to add to the project's slots. 'imageSlots' and not 'images':
		 *	that is the directory 'files' copies from. A slot with a 'filename'
		 *	is seeded with that file, which has to be there once the unit's files
		 *	are copied; one without is an empty slot, which renders nothing until
		 *	somebody uploads a picture. The label is one string for the project,
		 *	in its native language: a slot has a single label, not one per locale.
		 *	The first unit to name a uri is the one that counts. The wizard asks
		 *	for the slots of its page units, applyUnit() for those of every other
		 *	unit - a feature's among them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			The unit's manifest
		 *	@param		array 		&$into				(reference) uri => slot, collected across units
		 *
		 *	@return 	true|string							true, or 'could not seed image slot <uri>'
		 */
		public static function imageSlots( array &$appData, array $manifest, array &$into ): true|string {

			foreach( ( $manifest['imageSlots'] ?? [] ) as $slotUri => $slot ) {

				$label 		= self::localized( is_array( $slot ) === true ? ( $slot['label'] ?? '' ) : '', \Nino\Locales::getNativeLocale( $appData ) );
				$width 		= is_array( $slot ) === true ? (int) ( $slot['width'] ?? 0 ) : 0;
				$height 	= is_array( $slot ) === true ? (int) ( $slot['height'] ?? 0 ) : 0;
				$filename = is_array( $slot ) === true ? ( $slot['filename'] ?? null ) : null;

				if( is_string( $slotUri ) === false || preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)*$#', $slotUri ) !== 1
					|| $label === '' || $width < 1 || $height < 1 || $width * $height > \Nino\Images::MAX_SOURCE_PIXELS
					|| ( $filename !== null && ( is_string( $filename ) === false || $filename === '' || str_contains( $filename, '..' ) === true || str_starts_with( $filename, '/' ) === true
						|| is_file( \Nino\Filesystem::path( $appData, '/images/'. $filename ) ) === false ) ) )
					return 'could not seed image slot '. ( is_string( $slotUri ) === true ? $slotUri : '' );

				$into[$slotUri] ??= [ 'label' => $label, 'width' => $width, 'height' => $height, 'filename' => $filename ];
			}

			return true;
		}

		/**
		 *	Merge the words of a unit into the project's text files, and nothing
		 *	else of it: text/global.php into /text/global.php and, for each of
		 *	$locales, its fragment text/<locale>.php into /text/<locale>.php - the
		 *	unit wins a key collision with $overwrite, the project without it.
		 *
		 *	A language the unit has no fragment of gets the keys of one that it has -
		 *	the native language's fragment, else the one of en_US, else the first
		 *	there is - with an empty text each, and add-only whatever $overwrite
		 *	says: it never replaces a text. Every key of the unit then exists in
		 *	every language the project serves, so that a page shows the text of
		 *	the native language where the language has none of its own (see
		 *	\Nino\Html::getFills()), and the Dashboard counts what is left to
		 *	translate (see \Nino\Modules\Text\Admin::untranslatedCounts()).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitDir			Absolute path to the unit
		 *	@param		array 		$locales			The languages to merge for
		 *	@param		bool			$overwrite			Whether the unit replaces what the project has
		 *	@param		int				&$present			(reference) How many of the keys the project had already, which a merge without $overwrite leaves as they are; keys a language is given empty count as well
		 *
		 *	@return 	int|string							How many keys were new to the files, or the first file that could not be written - '/text/global.php'
		 */
		public static function applyUnitTexts( array &$appData, string $unitDir, array $locales, bool $overwrite = true, int &$present = 0 ): int|string {

			$unitDir	= rtrim( $unitDir, '/' );
			$added		= 0;
			$empty		= null;

			$present = 0;

			$merge = function( string $path, array $fragment, bool $replace ) use ( &$appData, &$added, &$present ): bool {

				$before = \Nino\Filesystem::getFileContent( $appData, $path, [] );
				$new = count( array_diff_key( $fragment, is_array( $before ) === true ? $before : [] ) );
				$added += $new;
				$present += count( $fragment ) - $new;

				return self::mergeText( $appData, $path, $fragment, $replace );
			};

			$globalFragment = $unitDir. '/text/global.php';
			if( is_file( $globalFragment ) === true && $merge( '/text/global.php', (array) include $globalFragment, $overwrite ) === false )
				return '/text/global.php';

			foreach( $locales as $locale ) {

				$localeFragment = $unitDir. '/text/'. $locale. '.php';

				if( is_file( $localeFragment ) === true ) {

					if( $merge( '/text/'. $locale. '.php', (array) include $localeFragment, $overwrite ) === false )
						return '/text/'. $locale. '.php';

					continue;
				}

				$empty ??= self::_emptyFragment( $appData, $unitDir );

				if( $empty !== [] && $merge( '/text/'. $locale. '.php', $empty, false ) === false )
					return '/text/'. $locale. '.php';
			}

			return $added;
		}

		/**
		 *	The keys of a unit's words in one language, each with an empty text -
		 *	what a language the unit has no words of is given (see applyUnitTexts()).
		 *	The language is the native one where the unit has a fragment of it,
		 *	else en_US, else the first fragment there is
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$unitDir			Absolute path to the unit
		 *
		 *	@return 	array								'[[key]]' => '', [] where the unit has no words of any language
		 */
		private static function _emptyFragment( array &$appData, string $unitDir ): array {

			$fragments = array_filter( glob( $unitDir. '/text/*.php' ) ?: [], static fn( string $file ): bool => preg_match( \Nino\Locales::PATTERN, basename( $file, '.php' ) ) === 1 && is_file( $file ) === true );

			foreach( [ \Nino\Locales::getNativeLocale( $appData ), 'en_US' ] as $locale )
				if( in_array( $unitDir. '/text/'. $locale. '.php', $fragments, true ) === true )
					return array_fill_keys( array_keys( (array) include $unitDir. '/text/'. $locale. '.php' ), '' );

			sort( $fragments );

			return $fragments === [] ? [] : array_fill_keys( array_keys( (array) include $fragments[0] ), '' );
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
		 *	@return 	bool										Whether the file was written
		 */
		public static function mergeText( array &$appData, string $path, array $fragment, bool $overwrite = true ): bool {

			return \Nino\Filesystem::mutate( $appData, $path, function( mixed $content ) use ( $fragment, $overwrite ): array {
				$content = is_array( $content ) ? $content : [];
				return $overwrite === true ? array_merge( $content, $fragment ) : $content + $fragment;
			} );
		}

		/**
		 *	Count the files of a unit's file or directory into $tally: those the
		 *	project has not got yet and those it has, as copyTree() is about to meet
		 *	them. Under $overwrite every file is the unit's. A source that is not
		 *	there counts nothing - copyTree() is the one that answers for it
		 *
		 *	@param		array 		&$tally			(reference) { added, present }
		 *	@param		string		$from
		 *	@param		string		$to
		 *	@param		bool			$overwrite
		 *
		 *	@return 	void
		 */
		private static function _tally( array &$tally, string $from, string $to, bool $overwrite ): void {

			if( is_file( $from ) === true ) {
				$tally[ $overwrite === false && is_file( $to ) === true ? 'present' : 'added' ]++;
				return;
			}

			if( is_dir( $from ) === false )
				return;

			foreach( scandir( $from ) ?: [] as $entry )
				if( $entry !== '.' && $entry !== '..' )
					self::_tally( $tally, $from. '/'. $entry, $to. '/'. $entry, $overwrite );
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
		 *	@return 	bool										Whether $to holds the unit's file now - a
		 *																	source that cannot be read or a target that
		 *																	cannot be written is false, and the caller
		 *																	says which file it was
		 */
		public static function copyFile( string $from, string $to, bool $overwrite = true ): bool {

			if( $overwrite === false && is_file( $to ) === true )
				return true;

			$content = @file_get_contents( $from );
			if( $content === false )
				return false;

			if( is_file( $to ) === true )
				@unlink( $to );

			if( is_dir( dirname( $to ) ) === false )
				@mkdir( dirname( $to ), 0755, true );

			/*	Checked, and silenced: a target that cannot be written - a
				directory in its place, a permission, a full disk - raised php's
				own warning here, which the framework's handler ends the request
				on, and answered nothing to the caller either way. So an
				activation was a 500 with half a unit copied or, under a handler
				that carries on, a success with a template missing. The false is
				the answer now, and applyUnit() names the file	*/
			return @file_put_contents( $to, $content ) === strlen( $content );
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
		 *	@return 	bool										Whether everything below $from is in place -
		 *																	a source the unit names but does not carry,
		 *																	or one file that could not be copied, is false
		 */
		public static function copyTree( string $from, string $to, bool $overwrite = true ): bool {

			if( is_file( $from ) === true )
				return self::copyFile( $from, $to, $overwrite );

			if( is_dir( $from ) === false )
				return false;

			if( $overwrite === true )
				return \Nino\Filesystem::copyDir( $from, $to );

			if( is_dir( $to ) === false && @mkdir( $to, 0755, true ) === false && is_dir( $to ) === false )
				return false;

			$copied = true;

			foreach( scandir( $from ) ?: [] as $entry )
				if( $entry !== '.' && $entry !== '..' && self::copyTree( $from. '/'. $entry, $to. '/'. $entry, false ) === false )
					$copied = false;

			return $copied;
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

			if( isset( $schema['label'] ) === true && self::localizedValid( $schema['label'] ) === false )
				return 'setting "'. $name. '": "label" must be a string or a locale => string map';

			if( isset( $schema['hint'] ) === true && self::localizedValid( $schema['hint'] ) === false )
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

					// (string), not is_string(): php stores a numeric string key
					// as an int, so a select whose values are numbers - a page
					// size, a column count - had its whole manifest refused with
					// a message that contradicted what its author had written
					$value = (string) $value;

					if( $value === '' || self::localizedValid( $label ) === false )
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
					if( is_string( $value ) === true && preg_match( '/^-?\d+$/', trim( $value ) ) === 1 ) {

						/*	(int) saturates instead of failing, so a digit string wider
							than an int - a pasted id, a run of nines - arrived here as
							PHP_INT_MAX, or PHP_INT_MIN with a sign, and was stored as
							if that was what had been asked for. A setting without a
							'max' kept it; one with a 'max' did refuse it, but named a
							bound the value had never been near. The digits are held
							against what the cast made of them, sign and leading zeros
							off both sides first, so '007' and '-0' still pass	*/
						$digits	= trim( $value );
						$value	= (int) $digits;
						$size		= ltrim( ltrim( $digits, '-' ), '0' );

						if( $size !== '' && $size !== ltrim( (string) $value, '-' ) )
							return [ false, 'must be a whole number' ];
					}
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
					/*	Seen as keys rather than in_array() over the list built so
						far, which walked it once per line - and the length was
						checked only after the whole post had been walked and
						de-duplicated, so an oversized one was paid for in full
						before being refused. The cap is checked as each line is
						kept now, which is the same threshold reached earlier	*/
					$lines = [];
					$seen		= [];

					foreach( $value as $line ) {

						if( is_string( $line ) === false )
							return [ false, 'must be a list of strings' ];

						$line = trim( $line );

						if( strlen( $line ) > self::MAX_STRING_LENGTH )
							return [ false, 'a line must be at most '. self::MAX_STRING_LENGTH. ' characters' ];

						if( $line === '' || isset( $seen[$line] ) === true )
							continue;

						$seen[$line]	= true;
						$lines[]			= $line;

						if( count( $lines ) > self::MAX_LINES )
							return [ false, 'must be at most '. self::MAX_LINES. ' lines' ];
					}
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
		 *	Whether a manifest's manual says anything about using the feature: a
		 *	text with a word in it, in any language, or a sectioned manual with an
		 *	entry in one of its sections. A sectioned one whose sections are all
		 *	empty is a feature saying it adds nothing, which its own screen in the
		 *	Features panel shows - every section, "none" under each - and which a
		 *	panel's "?" has nothing to open for.
		 *
		 *	@param		mixed			$manual				A manifest's 'manual'
		 *
		 *	@return 	bool
		 */
		public static function hasManual( mixed $manual ): bool {

			if( is_string( $manual ) === true )
				return trim( $manual ) !== '';

			if( is_array( $manual ) === false )
				return false;

			// A sectioned manual holds lists, the older form holds texts
			foreach( $manual as $value )
				if( ( is_array( $value ) === true && $value !== [] ) || ( is_string( $value ) === true && trim( $value ) !== '' ) )
					return true;

			return false;
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

		/**
		 *	Whether a name or description is usable: a non-empty string, or a
		 *	non-empty locale => string map with nothing empty in it.
		 *
		 *	Public for the same reason the patterns above are - \Nino\Catalogue
		 *	validates the same two fields of the same manifests once they are
		 *	published, and carried a line-for-line copy of this
		 *
		 *	@param		mixed			$value
		 *
		 *	@return 	bool
		 */
		public static function localizedValid( mixed $value ): bool {

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
		 *	Whether a maturity is usable: one short string, or a locale =>
		 *	string map of them, each at most MATURITY_LENGTH characters once
		 *	trimmed. Public because \Nino\Catalogue validates the same field
		 *	of the same manifests once they are published - and treats a bad
		 *	one as no maturity rather than as a bad catalogue
		 *
		 *	@param		mixed			$value
		 *
		 *	@return 	bool
		 */
		public static function maturityValid( mixed $value ): bool {

			if( self::localizedValid( $value ) === false )
				return false;

			foreach( is_array( $value ) === true ? $value : [ $value ] as $word )
				if( mb_strlen( trim( (string) $word ) ) > self::MATURITY_LENGTH )
					return false;

			return true;
		}

		/**
		 *	@param		string		$constraint
		 *
		 *	@return 	bool										Whether every part of it is something satisfies() understands
		 */
		public static function constraintValid( string $constraint ): bool {

			foreach( explode( '||', $constraint ) as $alternative ) {

				// An alternative that says nothing is not an alternative. A
				// trailing, doubled or lone '||' produced one, and satisfies()
				// read it as "holds for everything" - the constraint that is
				// meant to keep a feature off a kernel it was not written for
				// waved every kernel through
				if( trim( $alternative ) === '' )
					return false;

				foreach( preg_split( '/[\s,]+/', trim( $alternative ) ) ?: [] as $part )
					if( $part !== '' && $part !== '*' && preg_match( '/^(?:\^|~|>=|<=|>|<|!=|=)?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.]+)?$/', $part ) !== 1 )
						return false;
			}

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
