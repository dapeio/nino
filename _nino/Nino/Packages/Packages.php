<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Packages						What comes from outside as a folder of files: a feature, a theme, a package of blocks - looked at, put in place, taken away
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Packages					The one mechanism for the three kinds of package: the files of a package are checked in memory, written to a staging
	 *										folder below data/, and exchanged for the folder the package lives in, under the lock of the features. Where the
	 *										files come from - an archive read by \Nino\Archive, a folder - is not its business.
	 *
	 *										kind		lives in															what it may hold
	 *										feature	features/<Directory>/									code: the rules of the archive are all
	 *										theme		private/packages/themes/<key>/				theme.json, parts/<group>/part.json and part.css, blocks/<block>/
	 *																																(block.json, block.tpl, text/<xx_XX>.json, images/<slot>.<ext>), preview.webp,
	 *																																LICENSE.txt, README.md
	 *										blocks	private/packages/blocks/<key>/				pack.json, <block>/ as above, preview.webp, LICENSE.txt
	 *
	 *										A theme and a package of blocks are data and have no code, and what they hold is a list of paths of forms: a file that
	 *										no form names is refused with its path, whatever it is called (a php file, an .htaccess, a script, a hidden file). Each
	 *										of the files a form names is read by the rules of what reads it later: a part by \Nino\Components::validateGroup() and
	 *										\Nino\Components::partProblems(), a block by \Nino\Blocks::validate(), a picture by \Nino\Images::size() and
	 *										\Nino\Images::cleanSvg(). A package with one file that is refused is refused as a whole; nothing of it is written. A svg is
	 *										written as the picture cleanSvg() rebuilds, never as it came. private/ is never served, and no reader includes a file
	 *										of a package. The packages are not in a backup: they come from the catalogue again, and nothing of a site hangs on them.
	 *
	 *										What is refused is answered as [ 'code' => a key of WHY, 'path' => the file or folder, '' for the package, 'params' => the
	 *										words that go into the sentence of the code ] - why() makes the English sentence for the log, and a tool says it in
	 *										its own words by the code.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Packages {

		// The kinds of package
		public const string FEATURE = 'feature';
		public const string THEME = 'theme';
		public const string BLOCKS = 'blocks';
		public const array KINDS = [ self::FEATURE, self::THEME, self::BLOCKS ];

		// Where the files of a package are written before they are exchanged for the folder, below the project's data; and where themes and packages of blocks live
		public const string STAGING = '/data/.packages';
		public const string ROOT = '/private/packages';
		public const array FOLDERS = [ self::THEME => 'themes', self::BLOCKS => 'blocks' ];

		// How old a staging folder or a folder that was put aside is when the next change removes it: the lock says that none is at work, but a
		// file system may know no lock, and a change that is younger than this may be at work
		public const int WORK_AGE = 600;

		// How much a package may be: files, and bytes of them together. A feature is the largest thing there is (1.7 MB unpacked of the largest one)
		public const array LIMITS = [
			self::FEATURE	=> [ 'files' => 2000, 'bytes' => 25165824 ],
			self::THEME		=> [ 'files' => 400, 'bytes' => 4194304 ],
			self::BLOCKS	=> [ 'files' => 400, 'bytes' => 4194304 ],
		];

		// How much a file may be: json and css of a part, a text of a licence or a manual, the picture of the tile of a package, and how deep a json is
		public const int MAX_JSON = 32768;
		public const int MAX_CSS = 65536;
		public const int MAX_TEXT = 65536;
		public const int MAX_PREVIEW = 262144;
		public const int DEPTH = 8;

		// How many parts a theme may have: the number the reader of the themes takes
		public const int MAX_PARTS = 40;

		// Why a package or a file of it is refused, or a change is not made: one sentence each, %s where a name or a number goes. The sentence is English, for
		// the log; a tool says it in its own words by the code. Where the answer has a path, the sentence is the rest of one that begins with it
		public const array WHY = [
			'kind'						=> 'there is no kind of package "%s"',
			'key'							=> '"%s" is no key of a package: a slug of lower case letters, digits and hyphens, at most %s characters',
			'empty'						=> 'the package holds no file',
			'parts-many'			=> 'the theme has more than %s parts',
			'files-many'			=> 'the package holds more than %s files',
			'bytes-many'			=> 'the files of the package are more than %s bytes together',
			'path'						=> 'the path is no path of plain segments',
			'twice'						=> 'the file is the file "%s" in another spelling',
			'not-allowed'			=> 'the file is no file of a %s',
			'missing'					=> 'the %s has no %s',
			'long'						=> 'the file is longer than %s bytes',
			'text'						=> 'the file is no text of utf-8 without a null byte',
			'json'						=> 'the file is no json map of at most %s levels',
			'file-key'				=> '"%s" is no key of a %s - there are %s',
			'file-slug'				=> '"%s" is a slug of lower case letters, digits and hyphens, at most %s characters',
			'file-version'		=> '"version" is major.minor.patch',
			'file-words'			=> '"%s" is a string or a locale => string map, with no empty text',
			'file-map'				=> '"%s" is a map',
			'identity'				=> '"key" is "%s" and the package is installed as "%s"',
			'part-key'				=> '"%s" is no key of a part - there are %s',
			'part-contract'		=> '"contract" names the feature and the versions of it the part is for, as basis@^1.0',
			'page-contract'		=> 'the contract of the page is kernel@<constraint>',
			'page-css'				=> 'the page has no stylesheet: its part is part.json alone',
			'page-keys'				=> 'the part of the page has a contract and knobs',
			'page-knob'				=> 'the knob "%s" of the page is a slug with a label, a ladder of %s sets of tokens of the page, and small as true or false',
			'group-feature'		=> 'the contract names "%s", the group is the feature "%s"\'s',
			'ladder-unknown'	=> 'ladder "%s" is no knob of the group',
			'extra-knob'			=> 'knob "%s": an additional knob is <component>.x-<name>, a label and a ladder, at most %s for a component',
			'group-knobs'			=> 'the knobs of the group are refused: %s',
			'variants'				=> 'variants of "%s": a component of the group, with a map of at most %s variants of a slug and a label',
			'standard'				=> 'standard of "%s": the component of the group and the name of a variant',
			'css'							=> 'the css of the part breaks the rules of a part: %s',
			'part-json'				=> 'the part has no part.json',
			'block'						=> 'the block is refused: %s',
			'preview'					=> 'the picture is no webp picture of at most %s bytes and %s pixels on a side',
			'busy'						=> 'another change of the features is running',
			'unlockable'			=> 'the packages cannot be locked for a change',
			'stage'						=> 'the staging folder cannot be written',
			'swap'						=> 'the folder of the package cannot be exchanged',
			'absent'					=> 'the package is not installed',
			'remove'					=> 'the folder of the package could not be removed',
		];

		// What a name of a folder, a part and a block, of a picture, of a contract and of a version is
		private const string SLUG = '[a-z][a-z0-9]*(?:-[a-z0-9]+)*';
		private const string WORD = '[a-z0-9]+(?:-[a-z0-9]+)*';
		private const string LOCALE = '[a-z]{2}_[A-Z]{2}';
		private const string CONTRACT = '/^([a-z][a-z0-9-]{0,39})@([0-9A-Za-z.^~<>=!*| ,-]{1,40})$/D';
		private const string VERSION = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,20})?$/D';

		// What a part.json may say; and the knobs of a component the part adds to the ones of the group, and the variants it names
		private const array PART_KEYS = [ 'contract', 'ladders', 'knobs', 'variants', 'standard' ];
		private const array THEME_KEYS = [ 'key', 'version', 'name', 'description', 'brand' ];
		private const array PACK_KEYS = [ 'key', 'version', 'name', 'description' ];
		private const int MAX_EXTRA = 2;
		private const int MAX_VARIANTS = 8;
		private const int MAX_PAGE_KNOBS = 16;

		/**
		 *	Why a package is refused or a change is not made, as the sentence of the log
		 *
		 *	@param		array			$problem			What check(), place() or remove() answered
		 *
		 *	@return 	string
		 */
		public static function why( array $problem ): string {

			$sentence = vsprintf( self::WHY[$problem['code']] ?? '%s', array_map( 'strval', (array) ( $problem['params'] ?? [] ) ) );

			return ( ( $problem['path'] ?? '' ) === '' ? '' : self::_shown( (string) $problem['path'] ). ': ' ). $sentence;
		}

		/**
		 *	The folder a package lives in, absolute
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					One of KINDS
		 *	@param		string		$key					The key of a theme or of a package of blocks, the name of the folder of a feature; '' for the folder all of the kind live in
		 *
		 *	@return 	string										'' for a kind that is none or a key that is no name of a folder
		 */
		public static function dir( array &$appData, string $kind, string $key ): string {

			if( in_array( $kind, self::KINDS, true ) === false )
				return '';

			if( $kind === self::FEATURE ) {

				if( $key === '' )
					return \Nino\Features::dir();

				return preg_match( \Nino\Features::DIRECTORY_PATTERN, $key ) === 1 ? \Nino\Features::dir(). '/'. $key : '';
			}

			if( $key !== '' && ( strlen( $key ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $key ) !== 1 ) )
				return '';

			return \Nino\Filesystem::path( $appData, self::ROOT. '/'. self::FOLDERS[$kind]. ( $key === '' ? '' : '/'. $key ) );
		}

		/**
		 *	Whether the files of a package are what a package of the kind may be. Nothing is read from a disk or written to one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data - the register of the components it holds is what a part and a block are read against
		 *	@param		string		$kind					One of KINDS
		 *	@param		array			$files				path below the folder of the package => bytes, as \Nino\Archive::read() answers them
		 *
		 *	@return 	array|null								null where the package is good; else [ 'code' => a key of WHY, 'path' => the file or folder it is about, '' for the package, 'params' => [ ... ] ],
		 *																	the first fault by the order of the paths, files before what is made of them
		 */
		public static function check( array &$appData, string $kind, array $files ): ?array {

			if( in_array( $kind, self::KINDS, true ) === false )
				return self::_problem( 'kind', '', [ self::_shown( $kind ) ] );

			if( $files === [] )
				return self::_problem( 'empty' );

			$limits = self::LIMITS[$kind];

			if( count( $files ) > $limits['files'] )
				return self::_problem( 'files-many', '', [ $limits['files'] ] );

			$paths = array_map( 'strval', array_keys( $files ) );
			sort( $paths, SORT_STRING );

			$sum = 0;

			foreach( $paths as $path )
				$sum += is_string( $files[$path] ) === true ? strlen( $files[$path] ) : 0;

			if( $sum > $limits['bytes'] )
				return self::_problem( 'bytes-many', '', [ $limits['bytes'] ] );

			$seen = [];
			$folds = [];

			foreach( $paths as $path )
				$folds[strtolower( $path )] = true;

			$found = [ 'single' => [], 'parts' => [], 'blocks' => [] ];

			foreach( $paths as $path ) {

				$bytes = $files[$path];

				if( self::_plain( $path ) === false )
					return self::_problem( 'path', $path );

				$folded = strtolower( $path );

				// A name that is a file and a folder as well: the file system holds one of them
				foreach( explode( '/', $folded ) as $at => $segment )
					if( $at > 0 && isset( $folds[implode( '/', array_slice( explode( '/', $folded ), 0, $at ) )] ) === true )
						return self::_problem( 'path', $path );

				if( isset( $seen[$folded] ) === true )
					return self::_problem( 'twice', $path, [ $seen[$folded] ] );

				$seen[$folded] = $path;

				if( is_string( $bytes ) === false )
					return self::_problem( 'text', $path );

				if( $kind === self::FEATURE )
					continue;

				$shape = self::_shape( $kind, $path );

				if( $shape === null )
					return self::_problem( 'not-allowed', $path, [ $kind === self::THEME ? 'theme' : 'package of blocks' ] );

				$problem = self::_content( $shape, $path, $bytes );

				if( $problem !== null )
					return $problem;

				if( isset( $shape['group'] ) === true )
					$found['parts'][$shape['group']][$shape['role']] = $bytes;
				else if( isset( $shape['block'] ) === true ) {

					if( $shape['role'] === 'block-text' )
						$found['blocks'][$shape['block']]['texts'][$shape['locale']] = $bytes;
					else if( $shape['role'] === 'block-image' )
						$found['blocks'][$shape['block']]['pictures'][$shape['name']. '.'. $shape['type']] = $bytes;
					else
						$found['blocks'][$shape['block']][$shape['role']] = $bytes;
				}
				else
					$found['single'][$shape['role']] = $bytes;
			}

			if( $kind === self::FEATURE )
				return null;

			$prefix = $kind === self::THEME ? 'blocks/' : '';
			$word = $kind === self::THEME ? 'theme' : 'package of blocks';
			$identity = $kind === self::THEME ? 'theme.json' : 'pack.json';

			if( isset( $found['single'][$identity] ) === false )
				return self::_problem( 'missing', '', [ $word, $identity ] );

			$problem = self::_identity( $kind, $identity, $found['single'][$identity] );

			if( $problem !== null )
				return $problem;

			if( $kind === self::THEME ) {

				if( count( $found['parts'] ) > self::MAX_PARTS )
					return self::_problem( 'parts-many', 'parts', [ self::MAX_PARTS ] );

				foreach( $found['parts'] as $group => $part ) {

					$problem = self::_part( $appData, $group, $part );

					if( $problem !== null )
						return $problem;
				}
			}

			foreach( $found['blocks'] as $block => $parts ) {

				$where = $prefix. $block;

				foreach( [ 'block-json' => 'block.json', 'block-tpl' => 'block.tpl' ] as $role => $name )
					if( isset( $parts[$role] ) === false )
						return self::_problem( 'missing', $where, [ 'block', $name ] );

				$reason = \Nino\Blocks::validate( $appData, (string) $block, [ 'json' => $parts['block-json'], 'tpl' => $parts['block-tpl'], 'texts' => $parts['texts'] ?? [], 'pictures' => $parts['pictures'] ?? [] ] );

				if( is_string( $reason ) === true )
					return self::_problem( 'block', $where, [ $reason ] );
			}

			return null;
		}

		/**
		 *	The key and the version a package says of itself in its theme.json or pack.json: what the catalogue's entry is compared with. For a package that check() takes
		 *
		 *	@param		string		$kind					THEME or BLOCKS
		 *	@param		array			$files				As check() takes them
		 *
		 *	@return 	array|null								[ 'key', 'version' ]; null for a kind that has none or a file that is no json map with them
		 */
		public static function identity( string $kind, array $files ): ?array {

			if( in_array( $kind, [ self::THEME, self::BLOCKS ], true ) === false )
				return null;

			$json = self::_decode( (string) ( $files[$kind === self::THEME ? 'theme.json' : 'pack.json'] ?? '' ), self::MAX_JSON );

			if( $json === null || is_string( $json['key'] ?? null ) === false || is_string( $json['version'] ?? null ) === false )
				return null;

			return [ 'key' => $json['key'], 'version' => $json['version'] ];
		}

		/**
		 *	Put the files of a package in the folder it lives in: checked, written to a staging folder, exchanged for the folder that is there - which is put
		 *	back where the exchange fails - and the staging folder removed however it ends. A svg is written as cleanSvg() rebuilds it. Under the lock
		 *	of the features: a change that finds it taken is not made, and anything a run that was cut off left behind is cleared first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					One of KINDS
		 *	@param		string		$key					The key of a theme or of a package of blocks - the key its files say - or the name of the folder of a feature
		 *	@param		array			$files				As check() takes them
		 *
		 *	@return 	true|array								true where the package is in place; else what is wrong, as check() answers: nothing is changed
		 */
		public static function place( array &$appData, string $kind, string $key, array $files ): true|array {

			$target = self::dir( $appData, $kind, $key );

			if( $target === '' )
				return self::_failed( $kind, $key, in_array( $kind, self::KINDS, true ) === false ? self::_problem( 'kind', '', [ self::_shown( $kind ) ] ) : self::_problem( 'key', '', [ self::_shown( $key ), \Nino\Components::NAME_LENGTH ] ) );

			$problem = self::check( $appData, $kind, $files );

			if( $problem === null && $kind !== self::FEATURE ) {

				$says = (string) ( self::identity( $kind, $files )['key'] ?? '' );

				if( $says !== $key )
					$problem = self::_problem( 'identity', $kind === self::THEME ? 'theme.json' : 'pack.json', [ $says, $key ] );
			}

			if( $problem !== null )
				return self::_failed( $kind, $key, $problem );

			$locked = \Nino\Features::lock( $appData );

			if( $locked !== true )
				return self::_problem( $locked === \Nino\Features::BUSY ? 'busy' : 'unlockable' );

			$stage = '';

			try {

				self::_sweep( $appData );

				\Nino\Filesystem::forceDir( $appData, self::STAGING );
				$stage = \Nino\Filesystem::path( $appData, self::STAGING ). '/'. $kind. '-'. $key. '-'. bin2hex( random_bytes( 6 ) );

				if( @mkdir( $stage. '/new', 0755, true ) === false )
					return self::_failed( $kind, $key, self::_problem( 'stage' ) );

				foreach( $files as $path => $bytes ) {

					$path = (string) $path;
					$content = $kind !== self::FEATURE && str_ends_with( $path, '.svg' ) === true ? \Nino\Images::cleanSvg( (string) $bytes ) : (string) $bytes;
					$file = $stage. '/new/'. $path;

					if( $content === null || ( is_dir( dirname( $file ) ) === false && @mkdir( dirname( $file ), 0755, true ) === false && is_dir( dirname( $file ) ) === false )
						|| @file_put_contents( $file, $content ) !== strlen( $content ) )
						return self::_failed( $kind, $key, self::_problem( 'stage', $path ) );
				}

				return self::_swap( $appData, $kind, $key, $target, $stage. '/new' );
			}
			finally {

				if( $stage !== '' )
					\Nino\Filesystem::removeDir( $stage );

				\Nino\Features::unlock( $appData );
			}
		}

		/**
		 *	Take a theme or a package of blocks away. What uses it is no reason to keep it: a site never hangs on a package, it draws from the copies it keeps. A feature
		 *	is taken away by \Nino\Features::remove(), which asks that it is switched off first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					THEME or BLOCKS
		 *	@param		string		$key					The key of the package
		 *
		 *	@return 	true|array								true where it is gone; else what is wrong, as check() answers
		 */
		public static function remove( array &$appData, string $kind, string $key ): true|array {

			$target = in_array( $kind, [ self::THEME, self::BLOCKS ], true ) === true ? self::dir( $appData, $kind, $key ) : '';

			if( $target === '' || $key === '' )
				return self::_failed( $kind, $key, in_array( $kind, [ self::THEME, self::BLOCKS ], true ) === false ? self::_problem( 'kind', '', [ self::_shown( $kind ) ] ) : self::_problem( 'key', '', [ self::_shown( $key ), \Nino\Components::NAME_LENGTH ] ) );

			$locked = \Nino\Features::lock( $appData );

			if( $locked !== true )
				return self::_problem( $locked === \Nino\Features::BUSY ? 'busy' : 'unlockable' );

			try {

				self::_sweep( $appData );

				// What an exchange that was cut off put aside is not to come back after the package is gone
				foreach( (array) scandir( dirname( $target ) ) as $name )
					if( is_string( $name ) === true && preg_match( '/^\.previous-'. preg_quote( $key, '/' ). '-[a-f0-9]{12}$/D', $name ) === 1 && is_link( dirname( $target ). '/'. $name ) === false )
						\Nino\Filesystem::removeDir( dirname( $target ). '/'. $name );

				// A link is dropped and what it points at is left, as Features::remove() does
				if( is_link( $target ) === true ) {

					$dropped = @unlink( $target ) === true;
					self::_forget( $appData, $kind );

					return $dropped === true ? true : self::_failed( $kind, $key, self::_problem( 'remove' ) );
				}

				if( is_dir( $target ) === false )
					return self::_problem( 'absent' );

				// Out of the way in one step, then deleted: a request that reads in between sees the package whole or not at all
				$aside = dirname( $target ). '/.removing-'. $key. '-'. bin2hex( random_bytes( 6 ) );

				if( @rename( $target, $aside ) === false )
					return self::_failed( $kind, $key, self::_problem( 'remove' ) );

				\Nino\Filesystem::removeDir( $aside );
				self::_forget( $appData, $kind );

				return is_dir( $aside ) === false ? true : self::_failed( $kind, $key, self::_problem( 'remove' ) );
			}
			finally {
				\Nino\Features::unlock( $appData );
			}
		}

		/**
		 *	Clear what a run that was cut off left: a staging folder and a folder that was put aside, which is put back where the exchange never came
		 *	to put the new one in its place. Under the lock, which place() and remove() take for themselves
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	true|array								true, or the answer of a lock that is taken
		 */
		public static function sweep( array &$appData ): true|array {

			$locked = \Nino\Features::lock( $appData );

			if( $locked !== true )
				return self::_problem( $locked === \Nino\Features::BUSY ? 'busy' : 'unlockable' );

			try {
				self::_sweep( $appData );
			}
			finally {
				\Nino\Features::unlock( $appData );
			}

			return true;
		}

		/**
		 *	The exchange of the folder of a package for a new one: the old one beside it, the new one in, the old one back where that fails
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind
		 *	@param		string		$key
		 *	@param		string		$target				The folder of the package
		 *	@param		string		$new					The folder with the files
		 *
		 *	@return 	true|array
		 */
		private static function _swap( array &$appData, string $kind, string $key, string $target, string $new ): true|array {

			$parent = dirname( $target );

			if( is_dir( $parent ) === false && @mkdir( $parent, 0755, true ) === false && is_dir( $parent ) === false )
				return self::_failed( $kind, $key, self::_problem( 'swap' ) );

			// opcache forgets what it compiled from these paths - before the exchange for the files that are there, after it for the ones that take their place
			if( $kind === self::FEATURE )
				\Nino\Features::invalidateOpcache( $target );

			$aside = '';

			if( is_dir( $target ) === true || is_link( $target ) === true || file_exists( $target ) === true ) {

				// Beside the folder, so that it is one file system and one rename; a link or a file in its place is not something to exchange
				$aside = $parent. '/.previous-'. $key. '-'. bin2hex( random_bytes( 6 ) );

				if( is_link( $target ) === true || is_dir( $target ) === false || @rename( $target, $aside ) === false )
					return self::_failed( $kind, $key, self::_problem( 'swap' ) );
			}

			if( self::_move( $appData, $new, $target ) === false ) {

				if( $aside !== '' ) {

					\Nino\Filesystem::removeDir( $target );
					@rename( $aside, $target );
				}

				return self::_failed( $kind, $key, self::_problem( 'swap' ) );
			}

			// Out of the way in one step, then deleted: .previous-* stands between the two moves and nowhere else
			if( $aside !== '' )
				self::_discard( $aside, $parent, $key );

			self::_forget( $appData, $kind );

			if( $kind === self::FEATURE ) {

				\Nino\Features::invalidateOpcache( $target );

				// The registry that was read before saw the folder as it was
				unset( $appData['./nino/features/all'] );
			}

			return true;
		}

		/**
		 *	Move a folder to a place that is not there: by rename, which is atomic, and where that fails - the two lie on file systems that are two - by copy and
		 *	delete, never into a folder that is there. './nino/packages/move', a closure ( string $from, string $to ): bool, stands in for the file system where a test
		 *	needs a move that fails, as './nino/fetch/stub' stands in for the network
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$from
		 *	@param		string		$to
		 *
		 *	@return 	bool
		 */
		private static function _move( array &$appData, string $from, string $to ): bool {

			$stub = $appData['./nino/packages/move'] ?? null;

			if( $stub instanceof \Closure )
				return $stub( $from, $to ) === true;

			if( @rename( $from, $to ) === true )
				return true;

			if( file_exists( $to ) === true || is_link( $to ) === true )
				return false;

			// Copied beside the place and moved in by one rename, so that a run that dies in the copy leaves no half of a package at the place
			$incoming = dirname( $to ). '/.incoming-'. basename( $to ). '-'. bin2hex( random_bytes( 6 ) );

			if( \Nino\Filesystem::copyDir( $from, $incoming ) === true && @rename( $incoming, $to ) === true )
				return true;

			\Nino\Filesystem::removeDir( $incoming );

			return false;
		}

		/**
		 *	The step of sweep(), under the lock
		 *
		 *	A staging folder is removed once it is older than WORK_AGE. A folder put aside by an exchange - .previous-<key>-<hex> - is put back
		 *	where the folder of its key is missing: the exchange was cut off between its two moves and the lock says no request is between them; where the
		 *	folder is there the exchange was done and what stands aside is the old one, which is removed once it is old enough. What a removal put aside -
		 *	.removing-<key>-<hex> - is never put back: it may be half deleted
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _sweep( array &$appData ): void {

			$staging = \Nino\Filesystem::path( $appData, self::STAGING );

			foreach( is_dir( $staging ) === true ? (array) scandir( $staging ) : [] as $name ) {

				if( is_string( $name ) === false || preg_match( '/^(?:feature|theme|blocks)-[A-Za-z0-9-]+-[a-f0-9]{12}$/D', $name ) !== 1 || is_link( $staging. '/'. $name ) === true || is_dir( $staging. '/'. $name ) === false )
					continue;

				if( self::_old( $staging. '/'. $name ) === true )
					\Nino\Filesystem::removeDir( $staging. '/'. $name );
			}

			foreach( self::KINDS as $kind ) {

				$parent = self::dir( $appData, $kind, '' );

				foreach( $parent !== '' && is_dir( $parent ) === true ? (array) scandir( $parent ) : [] as $name ) {

					if( is_string( $name ) === false || preg_match( '/^\.(previous|removing|incoming)-([A-Za-z0-9-]+)-[a-f0-9]{12}$/D', $name, $found ) !== 1 || is_link( $parent. '/'. $name ) === true || is_dir( $parent. '/'. $name ) === false )
						continue;

					$target = $parent. '/'. $found[2];

					if( $found[1] === 'previous' && file_exists( $target ) === false && is_link( $target ) === false ) {

						@rename( $parent. '/'. $name, $target );

						if( $kind === self::FEATURE ) {

							\Nino\Features::invalidateOpcache( $target );
							unset( $appData['./nino/features/all'] );
						}

						continue;
					}

					if( self::_old( $parent. '/'. $name ) === true )
						\Nino\Filesystem::removeDir( $parent. '/'. $name );
				}

				self::_forget( $appData, $kind );
			}
		}

		/**
		 *	Whether a folder of a run is older than WORK_AGE - or younger than now, which a clock that was set back makes it: it is not at work either
		 *
		 *	@param		string		$path
		 *
		 *	@return 	bool
		 */
		private static function _old( string $path ): bool {

			return abs( time() - (int) filemtime( $path ) ) > self::WORK_AGE;
		}

		/**
		 *	Delete a folder that was put aside: renamed to .removing-<key>-<hex> first, so that no half of it is ever taken for a package
		 *
		 *	@param		string		$aside
		 *	@param		string		$parent
		 *	@param		string		$key
		 *
		 *	@return 	void
		 */
		private static function _discard( string $aside, string $parent, string $key ): void {

			$going = $parent. '/.removing-'. $key. '-'. bin2hex( random_bytes( 6 ) );

			\Nino\Filesystem::removeDir( @rename( $aside, $going ) === true ? $going : $aside );
		}

		/**
		 *	What this request read of the blocks is not what the folders hold after a change of a theme or of a package of blocks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind
		 *
		 *	@return 	void
		 */
		private static function _forget( array &$appData, string $kind ): void {

			if( $kind !== self::FEATURE )
				\Nino\Blocks::forget( $appData );
		}

		/**
		 *	The form of a path a package has: segments of plain characters, none of them empty or a dot or two, not deeper than the archive reader allows
		 *
		 *	@param		string		$path
		 *
		 *	@return 	bool
		 */
		private static function _plain( string $path ): bool {

			if( $path === '' || strlen( $path ) > 255 || preg_match( '#^[A-Za-z0-9_.-]{1,100}(?:/[A-Za-z0-9_.-]{1,100})*$#D', $path ) !== 1 )
				return false;

			$segments = explode( '/', $path );

			return count( $segments ) <= \Nino\Archive::DEPTH_MAX && array_intersect( $segments, [ '.', '..' ] ) === [];
		}

		/**
		 *	The form a file of a theme or of a package of blocks has, by its path
		 *
		 *	@param		string		$kind
		 *	@param		string		$path
		 *
		 *	@return 	array|null								[ 'role', and 'group' | 'block', 'locale', 'name', 'type' ] where the path is one of the forms; null for any other
		 */
		private static function _shape( string $kind, string $path ): ?array {

			$slug = self::SLUG;
			$word = self::WORD;
			$locale = self::LOCALE;
			$theme = $kind === self::THEME;
			$base = $theme === true ? 'blocks/' : '';

			if( $path === ( $theme === true ? 'theme.json' : 'pack.json' ) )
				return [ 'role' => $path ];

			if( $theme === true && preg_match( '#^parts/('. $slug. ')/part\.(json|css)$#D', $path, $found ) === 1 )
				return strlen( $found[1] ) <= \Nino\Components::NAME_LENGTH ? [ 'role' => 'part-'. $found[2], 'group' => $found[1] ] : null;

			if( preg_match( '#^'. $base. '('. $slug. ')/block\.(json|tpl)$#D', $path, $found ) === 1 )
				return strlen( $found[1] ) <= \Nino\Components::NAME_LENGTH ? [ 'role' => 'block-'. $found[2], 'block' => $found[1] ] : null;

			if( preg_match( '#^'. $base. '('. $slug. ')/text/('. $locale. ')\.json$#D', $path, $found ) === 1 )
				return strlen( $found[1] ) <= \Nino\Components::NAME_LENGTH ? [ 'role' => 'block-text', 'block' => $found[1], 'locale' => $found[2] ] : null;

			if( preg_match( '#^'. $base. '('. $slug. ')/images/('. $word. ')\.(svg|webp|png|jpg)$#D', $path, $found ) === 1 )
				return strlen( $found[1] ) <= \Nino\Components::NAME_LENGTH && strlen( $found[2] ) <= 2 * \Nino\Components::NAME_LENGTH
					? [ 'role' => 'block-image', 'block' => $found[1], 'name' => $found[2], 'type' => $found[3] ] : null;

			if( in_array( $path, $theme === true ? [ 'preview.webp', 'LICENSE.txt', 'README.md' ] : [ 'preview.webp', 'LICENSE.txt' ], true ) === true )
				return [ 'role' => $path ];

			return null;
		}

		/**
		 *	What a file of a form is by itself: not longer than its form allows, a text that is utf-8 without a null byte, a json that is a map of a depth that
		 *	is limited, a picture of its type
		 *
		 *	@param		array			$shape				As _shape() answers it
		 *	@param		string		$path
		 *	@param		string		$bytes
		 *
		 *	@return 	array|null
		 */
		private static function _content( array $shape, string $path, string $bytes ): ?array {

			$role = $shape['role'];

			if( $role === 'block-image' || $role === 'preview.webp' ) {

				// A raster picture is read against its type by the block it belongs to; a preview is read here. A svg is rebuilt by cleanSvg() where the block is read
				if( $role === 'block-image' )
					return strlen( $bytes ) > \Nino\Blocks::MAX_PICTURE ? self::_problem( 'long', $path, [ \Nino\Blocks::MAX_PICTURE ] ) : null;

				$size = strlen( $bytes ) <= self::MAX_PREVIEW ? \Nino\Images::size( $bytes ) : false;

				return $size !== false && $size['type'] === IMAGETYPE_WEBP && $size['width'] <= \Nino\Blocks::MAX_SIDE && $size['height'] <= \Nino\Blocks::MAX_SIDE
					? null : self::_problem( 'preview', $path, [ self::MAX_PREVIEW, \Nino\Blocks::MAX_SIDE ] );
			}

			$limit = match( $role ) {
				'part-css'							=> self::MAX_CSS,
				'block-json', 'block-text'	=> \Nino\Blocks::MAX_JSON,
				'block-tpl'							=> \Nino\Blocks::MAX_TEMPLATE,
				'LICENSE.txt', 'README.md'	=> self::MAX_TEXT,
				default									=> self::MAX_JSON,
			};

			if( strlen( $bytes ) > $limit )
				return self::_problem( 'long', $path, [ $limit ] );

			if( str_contains( $bytes, "\0" ) === true || mb_check_encoding( $bytes, 'UTF-8' ) === false )
				return self::_problem( 'text', $path );

			if( str_ends_with( $path, '.json' ) === true && self::_decode( $bytes, $limit ) === null )
				return self::_problem( 'json', $path, [ self::DEPTH ] );

			return null;
		}

		/**
		 *	A json that is a map of names, of a depth that is limited
		 *
		 *	@param		string		$bytes
		 *	@param		int				$limit
		 *
		 *	@return 	array|null
		 */
		private static function _decode( string $bytes, int $limit ): ?array {

			if( strlen( $bytes ) > $limit )
				return null;

			try {
				$value = json_decode( $bytes, true, self::DEPTH, JSON_THROW_ON_ERROR );
			}
			catch( \JsonException ) {
				return null;
			}

			return is_array( $value ) === true && ( $value === [] || array_is_list( $value ) === false ) ? $value : null;
		}

		/**
		 *	The theme.json or the pack.json: the keys it has, the slug, the version, the words
		 *
		 *	@param		string		$kind
		 *	@param		string		$file
		 *	@param		string		$bytes
		 *
		 *	@return 	array|null
		 */
		private static function _identity( string $kind, string $file, string $bytes ): ?array {

			$json = (array) self::_decode( $bytes, self::MAX_JSON );
			$keys = $kind === self::THEME ? self::THEME_KEYS : self::PACK_KEYS;

			foreach( array_keys( $json ) as $key )
				if( in_array( $key, $keys, true ) === false )
					return self::_problem( 'file-key', $file, [ self::_shown( (string) $key ), $kind === self::THEME ? 'theme' : 'package of blocks', implode( ', ', $keys ) ] );

			$key = $json['key'] ?? null;

			if( is_string( $key ) === false || preg_match( \Nino\Components::NAME, $key ) !== 1 || strlen( $key ) > \Nino\Components::NAME_LENGTH )
				return self::_problem( 'file-slug', $file, [ 'key', \Nino\Components::NAME_LENGTH ] );

			if( is_string( $json['version'] ?? null ) === false || preg_match( self::VERSION, $json['version'] ) !== 1 )
				return self::_problem( 'file-version', $file );

			foreach( [ 'name', 'description' ] as $words )
				if( \Nino\Features::localizedValid( $json[$words] ?? null ) === false )
					return self::_problem( 'file-words', $file, [ $words ] );

			if( isset( $json['brand'] ) === true && is_array( $json['brand'] ) === false )
				return self::_problem( 'file-map', $file, [ 'brand' ] );

			return null;
		}

		/**
		 *	One part of a theme, a group of it: part.json against the group of the register where the project has the group, and part.css against the
		 *	rules of a part. Where the project has not the feature of the group, the components the part itself names stand for the ones of the group - the
		 *	feature may come later, and a part the reader finds wrong when it does is one that rests
		 *
		 *	The versions the contract names are not looked at: a part for a later version of a feature is installed, and the one who reads it says it is for a later one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$group
		 *	@param		array			$part					'part-json' => the text, 'part-css' => the text; either may be missing
		 *
		 *	@return 	array|null
		 */
		private static function _part( array &$appData, string $group, array $part ): ?array {

			$where = 'parts/'. $group;

			if( isset( $part['part-json'] ) === false )
				return self::_problem( 'part-json', $where );

			$path = $where. '/part.json';
			$json = (array) self::_decode( $part['part-json'], self::MAX_JSON );
			$css = (string) ( $part['part-css'] ?? '' );

			foreach( array_keys( $json ) as $key )
				if( in_array( $key, self::PART_KEYS, true ) === false )
					return self::_problem( 'part-key', $path, [ self::_shown( (string) $key ), implode( ', ', self::PART_KEYS ) ] );

			if( is_string( $json['contract'] ?? null ) === false || preg_match( self::CONTRACT, $json['contract'], $contract ) !== 1 || \Nino\Features::constraintValid( $contract[2] ) === false )
				return self::_problem( 'part-contract', $path );

			if( $group === 'page' )
				return self::_page( $json, $contract, $css, $where );

			foreach( [ 'ladders', 'knobs', 'variants', 'standard' ] as $key )
				if( is_array( $json[$key] ?? [] ) === false )
					return self::_problem( 'file-map', $path, [ $key ] );

			$declared = \Nino\Components::groups( $appData )[$group] ?? null;

			if( $declared !== null && $contract[1] !== $declared['feature'] )
				return self::_problem( 'group-feature', $path, [ $contract[1], $declared['feature'] ] );

			$claimed = [];

			foreach( [ 'ladders', 'knobs' ] as $key )
				foreach( array_keys( (array) ( $json[$key] ?? [] ) ) as $name )
					$claimed[] = explode( '.', (string) $name )[0];

			foreach( [ 'variants', 'standard' ] as $key )
				foreach( array_keys( (array) ( $json[$key] ?? [] ) ) as $name )
					$claimed[] = (string) $name;

			$claimed = array_values( array_unique( array_filter( $claimed, static fn( string $name ): bool => strlen( $name ) <= \Nino\Components::NAME_LENGTH && preg_match( \Nino\Components::NAME, $name ) === 1 ) ) );
			$components = $declared !== null ? $declared['components'] : $claimed;

			if( $declared !== null ) {

				$all = $declared['knobs'];

				foreach( (array) ( $json['ladders'] ?? [] ) as $key => $ladder ) {

					if( isset( $declared['knobs'][$key] ) === false )
						return self::_problem( 'ladder-unknown', $path, [ self::_shown( (string) $key ) ] );

					$all[$key]['ladder'] = $ladder;
				}

				$extra = [];

				foreach( (array) ( $json['knobs'] ?? [] ) as $key => $knob ) {

					if( is_string( $key ) === false || preg_match( '/^([a-z][a-z0-9-]*)\.x-([a-z0-9]+(?:-[a-z0-9]+)*)$/D', $key, $found ) !== 1 || is_array( $knob ) === false
						|| array_diff( array_keys( $knob ), [ 'label', 'ladder' ] ) !== [] || ( $extra[$found[1]] = ( $extra[$found[1]] ?? 0 ) + 1 ) > self::MAX_EXTRA )
						return self::_problem( 'extra-knob', $path, [ self::_shown( (string) $key ), self::MAX_EXTRA ] );

					$all[$key] = [ 'token' => '--'. $found[1]. '-x-'. $found[2], 'label' => $knob['label'] ?? '', 'ladder' => $knob['ladder'] ?? null ];
				}

				$checked = \Nino\Components::validateGroup( $group, [ 'label' => $declared['label'], 'knobs' => $all ], $components );

				if( is_string( $checked ) === true )
					return self::_problem( 'group-knobs', $path, [ $checked ] );
			}

			foreach( (array) ( $json['variants'] ?? [] ) as $component => $words ) {

				if( is_string( $component ) === false || ( $declared !== null && in_array( $component, $components, true ) === false ) || is_array( $words ) === false || count( $words ) > self::MAX_VARIANTS )
					return self::_problem( 'variants', $path, [ self::_shown( (string) $component ), self::MAX_VARIANTS ] );

				foreach( $words as $name => $label )
					if( is_string( $name ) === false || preg_match( \Nino\Components::NAME, $name ) !== 1 || strlen( $name ) > \Nino\Components::NAME_LENGTH || \Nino\Features::localizedValid( $label ) === false )
						return self::_problem( 'variants', $path, [ self::_shown( $component ), self::MAX_VARIANTS ] );
			}

			foreach( (array) ( $json['standard'] ?? [] ) as $component => $name )
				if( is_string( $component ) === false || is_string( $name ) === false || ( $declared !== null && in_array( $component, $components, true ) === false ) )
					return self::_problem( 'standard', $path, [ self::_shown( (string) $component ) ] );

			$problems = \Nino\Components::partProblems( $css, $components );

			return $problems === [] ? null : self::_problem( 'css', $where. '/part.css', [ implode( '; ', $problems ) ] );
		}

		/**
		 *	The part of the page: a contract with the kernel and knobs of the tokens of the page - no stylesheet. Which of the tokens of the page a knob may set is what
		 *	the one who reads the theme says; a token of another namespace is none here
		 *
		 *	@param		array			$json
		 *	@param		array			$contract			The parts of the match of the contract
		 *	@param		string		$css
		 *	@param		string		$where
		 *
		 *	@return 	array|null
		 */
		private static function _page( array $json, array $contract, string $css, string $where ): ?array {

			$path = $where. '/part.json';

			if( $contract[1] !== 'kernel' )
				return self::_problem( 'page-contract', $path );

			if( trim( $css ) !== '' )
				return self::_problem( 'page-css', $where. '/part.css' );

			if( array_diff( array_keys( $json ), [ 'contract', 'knobs' ] ) !== [] )
				return self::_problem( 'page-keys', $path );

			$knobs = $json['knobs'] ?? [];

			if( is_array( $knobs ) === false || count( $knobs ) > self::MAX_PAGE_KNOBS )
				return self::_problem( 'page-knob', $path, [ '', \Nino\Components::STEPS ] );

			foreach( $knobs as $name => $knob ) {

				$bad = is_string( $name ) === false || preg_match( \Nino\Components::NAME, $name ) !== 1 || strlen( $name ) > \Nino\Components::NAME_LENGTH
					|| is_array( $knob ) === false || array_diff( array_keys( $knob ), [ 'label', 'small', 'ladder' ] ) !== [] || \Nino\Features::localizedValid( $knob['label'] ?? null ) === false
					|| ( isset( $knob['small'] ) === true && is_bool( $knob['small'] ) === false )
					|| is_array( $knob['ladder'] ?? null ) === false || array_is_list( $knob['ladder'] ) === false || count( $knob['ladder'] ) !== \Nino\Components::STEPS;

				if( $bad === false )
					foreach( $knob['ladder'] as $set ) {

						if( is_array( $set ) === false || ( $set !== [] && array_is_list( $set ) === true ) ) {
							$bad = true;
							break;
						}

						foreach( $set as $token => $value )
							if( is_string( $token ) === false || preg_match( '/^--page-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $token ) !== 1 || \Nino\Components::validStep( $value ) === false )
								$bad = true;
					}

				if( $bad === true )
					return self::_problem( 'page-knob', $path, [ self::_shown( (string) $name ), \Nino\Components::STEPS ] );
			}

			return null;
		}

		/**
		 *	An answer: the code of WHY, the file it is about and the words of its sentence
		 *
		 *	@param		string		$code
		 *	@param		string		$path
		 *	@param		array			$params
		 *
		 *	@return 	array
		 */
		private static function _problem( string $code, string $path = '', array $params = [] ): array {

			return [ 'code' => $code, 'path' => $path, 'params' => $params ];
		}

		/**
		 *	An answer that is a failure, said once in the log in the one sentence the log gets
		 *
		 *	@param		string		$kind
		 *	@param		string		$key
		 *	@param		array			$problem
		 *
		 *	@return 	array											The problem
		 */
		private static function _failed( string $kind, string $key, array $problem ): array {

			trigger_error( 'Packages: the '. self::_shown( $kind ). ' "'. self::_shown( $key ). '" was not changed: '. self::why( $problem ), E_USER_WARNING );

			return $problem;
		}

		/**
		 *	What a message may say of a name it was found under: printable characters and not many
		 *
		 *	@param		string		$name
		 *
		 *	@return 	string
		 */
		private static function _shown( string $name ): string {

			return (string) preg_replace( '/[^\x20-\x7E]/', '?', substr( $name, 0, 80 ) ). ( strlen( $name ) > 80 ? '...' : '' );
		}
	}

}
