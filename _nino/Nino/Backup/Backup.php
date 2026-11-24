<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Backup					Shared backup/restore manifest - which files belong in an admin-panel archive
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Backup - shared backup/restore manifest (which files belong in an
	// admin-panel archive) - not a filesystem primitive, so not in Filesystem
	class Backup {

		/*	What a backup never carries, whatever else asks for it.

			auth-tries.php and ratelimit.php are named in the docblock below as
			transient throttling counters rather than data, and they were left
			out by simply not listing them - which held for as long as the list
			was literals. It stopped holding when a feature's manifest became a
			source of paths: one that names a directory gets its whole tree
			walked, and a manifest naming '/data/' itself - which the validator
			took, since it does start with '/data/' - put every one of these in
			every backup, and a restore then wrote them back. Restored
			auth-tries.php re-locks accounts somebody already waited out;
			a restored mail-status.php (the record of the last failed mail,
			\Nino\Mail::failure()) brings back a failure that was fixed long
			ago; a restored mail-queue.php (the outbox of \Nino\Mail::queue())
			sends mails again that were delivered since the archive was made, and
			a restored mail-failed.php reports mails that were given up on long
			ago; restored .locks plants lock files for requests that ended weeks ago.

			So the promise is kept where it is made rather than by what the list
			happens not to mention. A leading dot with it: '/data/.locks' is the
			only one today, and everything hidden under /data/ is this
			framework's own bookkeeping rather than a project's content	*/
		// The code of the RuntimeException that says a write failed - no room left in
		// the temporary directory or where the archives are kept, or a file that
		// cannot be written - which the panel words in the interface language
		public const int WRITE_FAILED = 1;

		// The code of the RuntimeException that says the disk has less room than a run needs, before it began: the sizes are
		// carried beside it by the run (see \Nino\Modules\Backups::now())
		public const int DISK_SHORT = 2;

		// What a run keeps free beyond what it counts: the tar's end, the stamp, the bookkeeping of the file system
		private const int SPACE_RESERVE = 4194304;

		/**
		 *	The free bytes of the disk a path lies on: what the system says, or what './nino/backups/disk' names as an int (the
		 *	test of the check cannot fill a disk)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A directory
		 *
		 *	@return 	int|null								null where the system does not say
		 */
		public static function freeSpace( array &$appData, string $path ): ?int {

			$injected = $appData['./nino/backups/disk'] ?? null;

			if( is_int( $injected ) === true )
				return $injected;

			$free = @disk_free_space( $path );

			return $free === false ? null : (int) $free;
		}

		/**
		 *	What packing files of this size needs of the temporary directory at the most. Measured with a temporary directory of a
		 *	tmpfs of a chosen size and files that do not compress: PharData holds the tar and what it is copying from, so the
		 *	tar takes twice the bytes of the files while it is made (82 MB for 41 MB of files), and then tar and the compressed archive
		 *	lie side by side, twice again. Where the archive is written to the same disk, the compressed archive (as large as the
		 *	files, where they do not compress) and the sealed one (a third more) lie together once the tar is gone, which is more
		 *	still: 7/3 of the bytes. A header of the tar takes 512 bytes for each file
		 *
		 *	@param		int				$bytes				The bytes of the files
		 *	@param		int				$files				How many there are
		 *	@param		bool			$together			Whether the archive is written to the disk of the temporary directory
		 *
		 *	@return 	int
		 */
		public static function spaceNeed( int $bytes, int $files, bool $together ): int {
			return ( $together === true ? (int) ceil( $bytes * 7 / 3 ) : 2 * $bytes ) + 512 * $files + self::SPACE_RESERVE;
		}

		/**
		 *	Whether the disk has the room that packing files of this size needs, before anything is packed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$bytes				The bytes of the files
		 *	@param		int				$files				How many there are
		 *	@param		string|null	$archiveDir		The directory the archive is written to, where there is one
		 *
		 *	@return 	array|null							null where it fits or the system does not say, else [ megabytes needed, megabytes free ]
		 */
		public static function spaceShort( array &$appData, int $bytes, int $files, ?string $archiveDir ): ?array {

			$temp 		= sys_get_temp_dir();
			$together = $archiveDir !== null && ( $a = @stat( $temp ) ) !== false && ( $b = @stat( $archiveDir ) ) !== false && $a['dev'] === $b['dev'];
			$need 		= self::spaceNeed( $bytes, $files, $together );
			$free 		= self::freeSpace( $appData, $temp );

			if( $free !== null && $free < $need )
				return [ (int) ceil( $need / 1048576 ), (int) floor( max( 0, $free ) / 1048576 ) ];

			// Archives that are kept on a disk of their own: the sealed archive, a third more than the compressed one, is written there
			return $archiveDir !== null && $together === false ? self::archiveShort( $appData, $bytes, $archiveDir ) : null;
		}

		/**
		 *	Whether the disk the archives are kept on has the room for one of files of this size: a third more than the bytes where they do not
		 *	compress, and the reserve
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$bytes				The bytes of the files
		 *	@param		string		$archiveDir		The directory the archive is written to
		 *
		 *	@return 	array|null							null where it fits or the system does not say, else [ megabytes needed, megabytes free ]
		 */
		public static function archiveShort( array &$appData, int $bytes, string $archiveDir ): ?array {

			$need = (int) ceil( $bytes * 4 / 3 ) + self::SPACE_RESERVE;
			$free = self::freeSpace( $appData, is_dir( $archiveDir ) === true ? $archiveDir : dirname( $archiveDir ) );

			return $free !== null && $free < $need ? [ (int) ceil( $need / 1048576 ), (int) floor( max( 0, $free ) / 1048576 ) ] : null;
		}

		/**
		 *	Whether the disks that the files of an unpacked archive are copied to have the room for them, before anything is copied: for each
		 *	disk, what its files grow by - the size of the staged files less the size of those they replace - and the reserve. The staged tree
		 *	is read, so the sizes are the sizes, not a guess
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *
		 *	@return 	array|null							null where it fits or the system does not say, else [ megabytes needed, megabytes free ]
		 */
		public static function restoreRoom( array &$appData, string $staging ): ?array {

			$growth = [];
			$free 	= [];

			foreach( glob( rtrim( $staging, DIRECTORY_SEPARATOR ). '/*' ) ?: [] as $entry ) {

				$name 		= basename( $entry );

				// The note of the state is read and taken out, no file of the project
				if( $name === 'nino.php' )
					continue;

				$target 	= $name === 'config.php' ? \Nino\Filesystem::getConfigPath( $appData ). '/config.php' : self::target( $appData, $name );
				$anchor 	= $target;

				while( $anchor !== dirname( $anchor ) && file_exists( $anchor ) === false )
					$anchor = dirname( $anchor );

				$stat 		= @stat( $anchor );
				$device 	= $stat === false ? $anchor : $stat['dev'];
				$free[$device] = $anchor;
				$grow 		= 0;

				$walk = is_dir( $entry ) === true
					? new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $entry, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY )
					: new \ArrayIterator( [ new \SplFileInfo( $entry ) ] );

				foreach( $walk as $file ) {

					$relative = $file->getPathname() === $entry ? '' : substr( $file->getPathname(), strlen( $entry ) );
					$grow 		+= (int) @filesize( $file->getPathname() ) - ( is_file( $target. $relative ) === true ? (int) @filesize( $target. $relative ) : 0 );
				}

				$growth[$device] = ( $growth[$device] ?? 0 ) + max( 0, $grow );
			}

			foreach( $growth as $device => $grow ) {

				$room = self::freeSpace( $appData, $free[$device] );
				$need = $grow + self::SPACE_RESERVE;

				if( $room !== null && $room < $need )
					return [ (int) ceil( $need / 1048576 ), (int) floor( max( 0, $room ) / 1048576 ) ];
			}

			return null;
		}

		// The files of the outbox of \Nino\Mail: not in a backup, and not brought back by a restore of any archive
		private const array OUTBOX = [ 'mail-queue.php', 'mail-queue-due.txt', 'mail-keys.php', 'mail-failed.php', 'mail-queue-damaged.php', 'mail-failed-damaged.php', 'mail-keys-damaged.php', 'mail-queue-unreadable.php', 'mail-queue-keys.php', 'mail-queue-stuck.php', 'mail-queue-record.php', 'mail-queue-unwritable.php' ];

		private const array NEVER = [ 'auth-tries.php', 'ratelimit.php', 'mail-status.php', 'catalogue.php', ...self::OUTBOX ];

		/**
		 *	Whether a path below /data/ is one a backup must not carry
		 *
		 *	@param		string		$relative			eg. 'stats/2026-09.php'
		 *
		 *	@return 	bool
		 */
		private static function _transient( string $relative ): bool {

			$segments = explode( '/', $relative );

			// What is kept to be made again - the pages and the trees of the templates - is no content either
			if( $segments[0] === 'cache' )
				return true;

			foreach( $segments as $segment )
				if( str_starts_with( $segment, '.' ) === true )
					return true;

			return in_array( $relative, self::NEVER, true );
		}

		// Absolute path -> archive name, for every file the admin panel
		// writes to at runtime: config.php, the text files, every element
		// type/image, the pages and the look - the templates the Builder
		// writes (page-*.tpl) and Design writes (frame-header.tpl and
		// frame-footer.tpl), the assets/ Design writes its theme.css into -
		// and the /data/ content a project actually accumulates (newsletter
		// subscribers, form submissions, the php error log logs.<month>.php,
		// and what an installed feature's manifest declares it owns below
		// /data/). A template is editorial content once the Builder exists, so
		// every one of them is in, in a folder of templates/ as well as
		// directly in it: a developer's own, which git versions as
		// well, is not hurt by being in an archive - a restore brings it back
		// as it was archived. Deliberately not developer code (_nino/,
		// _admin/ itself, ...) - that's already versioned in git and would
		// just bloat every backup. Also deliberately not
		// auth-tries.php, ratelimit.php or mail-status.php - transient
		// throttling counters and the last failed mail - nor the outbox of
		// \Nino\Mail (mail-queue.php and mail-failed.php), not data a restore
		// should bring back. The workbench's
		// activity log is not in an archive either: it is written to
		// private/.logs/<day>.php (see \Nino\Modules\Logs\Admin),
		// which is none of the directories this walks.
		//
		// Shared by \Nino\Modules\Backups::_create() and
		// \Nino\Modules\Backups\Admin::_safetySnapshot(), which both need the
		// exact same manifest for the exact same reason - kept here in the
		// kernel rather than in either of them, since the panel that restores
		// an archive has to work where no other class is loadable (see that
		// panel's own docblock and its _backupDir()).
		public static function manifest( array &$appData ): array {

			// Defensive: a caller right after writing config.php
			// (\Nino\Modules\Backups::_bootstrap()) needs the is_file() checks
			// below to see that write rather than a cached pre-write stat
			clearstatcache();

			// Every path here is resolved rather than concatenated onto one
			// root: config.php may sit outside the webroot on its own
			// (NINO_CONFIG_DIR), text/elements/templates/assets/data are
			// private and images are public, so the two halves need not live
			// under the same directory - see Filesystem::path() and PRIVATE_DIRS
			$text 			= \Nino\Filesystem::path( $appData, '/text' );
			$elements 	= \Nino\Filesystem::path( $appData, '/elements' );
			$templates 	= \Nino\Filesystem::path( $appData, '/templates' );
			$assets 		= \Nino\Filesystem::path( $appData, '/assets' );
			$images 		= \Nino\Filesystem::path( $appData, '/images' );
			$blocks 		= self::target( $appData, 'blocks' );
			$themes 		= self::target( $appData, 'themes' );
			$fonts 			= self::target( $appData, 'fonts' );
			$data 			= \Nino\Filesystem::path( $appData, '/data' );
			$configPath	= \Nino\Filesystem::getConfigPath( $appData );
			$files 			= [];
			if( is_file( $configPath. '/config.php' ) === true )
				$files[$configPath. '/config.php'] = 'config.php';

			// Every /text/*.php on disk, not just global.php plus the
			// currently available locales: that would silently drop
			// blacklist.php (written at runtime by Text::setBlacklisted(),
			// not reliably in git) and a removed locale's file (still on
			// disk, no longer in '/nino/locales/available') from every
			// backup from that point on
			foreach( glob( $text. '/*.php' ) ?: [] as $file )
				$files[$file] = 'text/'. basename( $file );

			foreach( glob( $elements. '/*.php' ) ?: [] as $file )
				$files[$file] = 'elements/'. basename( $file );

			// Element images are deliberately nested (images/elements/type/...)
			// and so are the stylesheet sources of assets/ and the templates of
			// a module or a feature that keeps its own in a folder of
			// templates/ (templates/<folder>/<name>.tpl). Walk the complete
			// tree of each and preserve each relative archive path; never
			// follow symlinks out of the public image directory or the
			// private asset and template ones. Of templates/ only the
			// templates are taken, at any depth (see template()): the pages of
			// the Builder and the frames of Design among them, and which of the
			// others a person wrote is not something a file says. The blocks the
			// project wrote itself (private/blocks/), its own themes
			// (private/themes/) and the fonts that are
			// installed (public/fonts/) are carried the same way, each file by the
			// rule of its directory (see block(), theme() and font()) - the work folder an
			// install of a font makes beside its target is none of them
			foreach( [ 'templates' => $templates, 'images' => $images, 'assets' => $assets, 'blocks' => $blocks, 'themes' => $themes, 'fonts' => $fonts ] as $archive => $root ) {

				if( is_dir( $root ) === false )
					continue;

				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::LEAVES_ONLY,
					\RecursiveIteratorIterator::CATCH_GET_CHILD
				);

				foreach( $iterator as $file ) {
					$path = $file->getPathname();
					if( $file->isFile() === false || is_link( $path ) === true )
						continue;

					$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( rtrim( $root, DIRECTORY_SEPARATOR ) ) + 1 ) );

					if( $archive === 'templates' && self::template( $relative ) === false )
						continue;

					if( $archive === 'blocks' && self::block( $relative ) === false )
						continue;

					if( $archive === 'themes' && self::theme( $relative ) === false )
						continue;

					if( $archive === 'fonts' && self::font( $relative ) === false )
						continue;

					$files[$path] = $archive. '/'. $relative;
				}
			}

			$files = self::_withinThemeLimits( $files );

			// The two files a Newsletter keeps: its subscribers, and the removal
			// record it writes on every unsubscribe (a sha256 per removed
			// address, not the address itself), which its callbackRestore()
			// falls back on when the live copy is itself what is being recovered
			// from. The catalogue's Newsletter declares both in its manifest, so
			// the loop below carries them as well; these literals are for the
			// copy a project that started on 1.0.0-beta may still run from
			// app/Nino/Modules/Newsletter/, which has no manifest for
			// Features::all() to read. Plain paths, no class constant: a backup
			// must not autoload a module the project may not have
			if( is_file( $data. '/newsletter.php' ) === true )
				$files[$data. '/newsletter.php'] = 'data/newsletter.php';

			if( is_file( $data. '/newsletter-removed.php' ) === true )
				$files[$data. '/newsletter-removed.php'] = 'data/newsletter-removed.php';

			foreach( glob( $data. '/forms.*.php' ) ?: [] as $file )
				$files[$file] = 'data/'. basename( $file );

			foreach( glob( $data. '/logs.*.php' ) ?: [] as $file )
				$files[$file] = 'data/'. basename( $file );

			// What an installed feature says it owns under /data/. The manifest
			// key is documented as what a backup carries (see docs/features.md,
			// "Data and restore"), and until this read it it was not:
			// the two entries above are hardcoded literals for the one feature
			// that predates the catalogue, and anything else - a directory a
			// feature owns, a file a feature nobody here knows about writes -
			// was simply never in a backup.
			//
			// \Nino\Features::all() reads the manifests, which are plain array
			// files; no feature class is autoloaded on a backup's behalf, which
			// is the same care the two literals above were written with. A
			// manifest's paths are validated when it is read (below /data/, no
			// '..'), so what arrives here needs no second check
			foreach( \Nino\Features::all( $appData ) as $feature ) {

				/*	Present, not active. Deactivating a feature is documented as
					removing its class from '/nino/modules' and nothing else -
					settings, data and copied files stay, and switching it back on
					finds everything as it was. A backup that ran while it was off
					did not have its data, so a restore from that backup broke the
					promise: config.php still recorded the installed version, the
					data behind it was gone. Ownership ends when the directory
					does, not when the switch goes off - a feature that was really
					removed drops out of all() and stops being carried here.	*/
				foreach( (array) ( $feature['data'] ?? [] ) as $owned ) {

					// Trimmed before it becomes an archive name: a manifest naming
					// '/data/stats/' produced 'data//stats/...' entries, one slash
					// per trailing one somebody wrote
					$owned	= '/'. trim( (string) $owned, '/' );
					$name		= ltrim( $owned, '/' );
					$path 	= \Nino\Filesystem::path( $appData, $owned );

					if( $owned === '/data' || self::_transient( substr( $name, 5 ) ) === true )
						continue;

					if( is_file( $path ) === true ) {
						$files[$path] = $name;
						continue;
					}

					if( is_dir( $path ) === false )
						continue;

					$iterator = new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
						\RecursiveIteratorIterator::LEAVES_ONLY,
						\RecursiveIteratorIterator::CATCH_GET_CHILD
					);

					foreach( $iterator as $file ) {

						$filePath = $file->getPathname();

						if( $file->isFile() === false || is_link( $filePath ) === true )
							continue;

						$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $filePath, strlen( rtrim( $path, DIRECTORY_SEPARATOR ) ) + 1 ) );

						if( self::_transient( substr( $name, 5 ). '/'. $relative ) === true )
							continue;

						$files[$filePath] = $name. '/'. $relative;
					}
				}
			}

			return $files;
		}

		/**
		 *	The files of the manifest without those of a theme that is more than a theme package may be: more files, or more
		 *	bytes together, than \Nino\Packages::LIMITS names for a theme. The theme is left out as a whole, with one line in
		 *	the log that names its key and why, and the backup goes on - a theme that is beyond the limit cannot be read
		 *	by the themes either. Bounded as it is (400 files, 4 MB a theme), the themes need no place of their own in the
		 *	check of the memory a backup needs before it packs
		 *
		 *	@param		array 		$files				absolute path => path in the archive
		 *
		 *	@return 	array
		 */
		private static function _withinThemeLimits( array $files ): array {

			$limits = \Nino\Packages::LIMITS[\Nino\Packages::THEME];
			$count	= [];
			$bytes	= [];

			foreach( $files as $path => $name )
				if( str_starts_with( $name, 'themes/' ) === true ) {
					$key = explode( '/', $name )[1];
					$count[$key] = ( $count[$key] ?? 0 ) + 1;
					$bytes[$key] = ( $bytes[$key] ?? 0 ) + (int) @filesize( $path );
				}

			foreach( $count as $key => $number ) {

				$why = $number > $limits['files'] ? 'more than '. $limits['files']. ' files' : ( $bytes[$key] > $limits['bytes'] ? 'more than '. $limits['bytes']. ' bytes' : '' );

				if( $why === '' )
					continue;

				trigger_error( 'Backup: the theme "'. $key. '" is left out of the archive, it holds '. $why, E_USER_WARNING );

				foreach( $files as $path => $name )
					if( str_starts_with( $name, 'themes/'. $key. '/' ) === true )
						unset( $files[$path] );
			}

			return $files;
		}

		// The top-level entries of an archive: manifest() makes no others, and
		// nino.php is stamp()'s. A restore copies each one into the project, so
		// an archive that names another would plant a file next to index.php
		private const array ARCHIVE_DIRS = [ 'text', 'elements', 'templates', 'assets', 'images', 'data', 'blocks', 'themes', 'fonts' ];
		private const array ARCHIVE_FILES = [ 'config.php', 'nino.php' ];

		/**
		 *	Where a top-level directory of an archive lives in the project. All of them are resolved by their
		 *	own name (see \Nino\Filesystem::path()) but two: the blocks of the project are below the private
		 *	root as private/blocks/ and its own themes as private/themes/, and an archive names them blocks/
		 *	and themes/. The one place of the mapping, for the collection and for the restore alike
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$entry				The directory as the archive names it, eg. 'templates'
		 *
		 *	@return 	string										Absolute path
		 */
		public static function target( array &$appData, string $entry ): string {
			return \Nino\Filesystem::path( $appData, in_array( $entry, [ 'blocks', 'themes' ], true ) === true ? '/private/'. $entry : '/'. $entry );
		}

		/**
		 *	Whether a path below blocks/ is a file of a block: a json, a template or the picture of a slot, in no hidden folder. A block
		 *	is data and has no code (see \Nino\Blocks), so what else lies there is none of the project's
		 *	blocks. The one rule of what a backup collects there and of what a restore takes
		 *
		 *	@param		string		$relative			eg. 'hero/block.json' or 'hero/text/en_US.json'
		 *
		 *	@return 	bool
		 */
		public static function block( string $relative ): bool {
			return self::_plain( $relative, self::BLOCK_NAME );
		}

		/**
		 *	Whether a path below themes/ is a file of a theme the project wrote itself: <key>/ and below it a path of one of
		 *	the forms of a theme package - theme.json, theme.css, parts/<group>/part.json and part.css, blocks/<block>/...,
		 *	preview.webp, LICENSE.txt, README.md (see \Nino\Packages::ofTheme(), the rule that package is checked by).
		 *	Nothing else is a part of the backup, whatever it is called: a file of another form is left out where the backup
		 *	is made and refused where it is restored. Only ever copied - nothing of the kernel includes or registers a file
		 *	of that directory. The one rule of what a backup collects there and of what a restore takes
		 *
		 *	@param		string		$relative			eg. 'mine/theme.json' or 'mine/parts/colors/part.css'
		 *
		 *	@return 	bool
		 */
		public static function theme( string $relative ): bool {
			return \Nino\Packages::ofTheme( $relative );
		}

		/**
		 *	Whether a path below fonts/ is a file of an installed font: a font file, the font.json that names the
		 *	family and its licence as text, in no hidden folder - the work folder of an install, .work-<random>,
		 *	is one. The directory is public, so a name a webserver hands to an interpreter is none. The one rule
		 *	of what a backup collects there and of what a restore takes
		 *
		 *	@param		string		$relative			eg. 'inter/400.woff2' or 'inter/font.json'
		 *
		 *	@return 	bool
		 */
		public static function font( string $relative ): bool {
			return self::_plain( $relative, self::FONT_NAME );
		}

		// A file of a directory that is carried by name: plain segments, none of them hidden, the name of the file by the pattern
		private static function _plain( string $relative, string $pattern ): bool {

			$segments = explode( '/', $relative );
			$name 		= (string) array_pop( $segments );

			foreach( array_merge( $segments, [ $name ] ) as $segment )
				if( preg_match( '/\.(?:php|phtml|phar|pht|phps)(?:\d|\.|$)/i', $segment ) === 1 )
					return false;

			foreach( $segments as $segment )
				if( preg_match( '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/D', $segment ) !== 1 )
					return false;

			return preg_match( $pattern, $name ) === 1;
		}

		/**
		 *	Whether a path below templates/ is a template: a file that is called
		 *	*.tpl, in whatever depth, and in no directory that is called like one.
		 *	A name that starts with a dot is none, as glob( '*.tpl' ) never
		 *	answered it. The one rule of what a backup collects and of what a
		 *	restore takes
		 *
		 *	@param		string		$relative			eg. 'page-home.tpl' or 'crm-portal/list.tpl'
		 *
		 *	@return 	bool
		 */
		public static function template( string $relative ): bool {

			$segments = explode( '/', $relative );
			$name 		= (string) array_pop( $segments );

			if( $name === '' || $name[0] === '.' || str_ends_with( $name, '.tpl' ) === false )
				return false;

			foreach( $segments as $segment )
				if( $segment === '' || str_ends_with( $segment, '.tpl' ) === true )
					return false;

			return true;
		}

		/**
		 *	Whether an archive holds an entry a restore must not take, read from
		 *	the headers of the tar itself and before anything is unpacked: the
		 *	unpacking normalizes a name ('templates/../x.tpl' becomes 'x.tpl' in
		 *	the staged copy, an absolute one is made relative), and what is
		 *	copied from there into the project is the normalized name. Refused
		 *	are a link or any kind of entry but a file and a directory (the long
		 *	names of the GNU tar among them, which PharData does not write: it
		 *	splits a name over the two fields of the header), a name with a NUL
		 *	byte in it, an absolute one, one with '..' in it, one that
		 *	starts anywhere but at a directory of manifest() or at config.php or
		 *	nino.php, and below templates/ a file that is no template or a
		 *	directory that is called like one (see template()). Every entry up to
		 *	where the tar ends or cannot be read is judged
		 *
		 *	@param		string		$tarGz				Absolute path of the .tar.gz
		 *
		 *	@return 	string|null							Why the archive is refused, or null where every entry may be restored
		 */
		public static function refusal( string $tarGz ): ?string {

			// What cannot be read as a tar at all - a file that is cut short or is
			// none - is not judged here: nothing in it is an entry, and the
			// unpacking says so itself
			$handle = @gzopen( $tarGz, 'rb' );
			if( $handle === false )
				return null;

			try {
				while( ( $block = gzread( $handle, 512 ) ) !== false && $block !== '' ) {

					if( strlen( $block ) !== 512 )
						return null;

					// The end of the archive: nothing but zeros follows. Whatever
					// follows it that is not a zero is read by no tar, but a reader
					// that went on would find it
					if( $block === str_repeat( "\0", 512 ) ) {

						while( ( $rest = gzread( $handle, 8192 ) ) !== false && $rest !== '' )
							if( trim( $rest, "\0" ) !== '' )
								return 'the backup holds data after its end';

						return null;
					}

					$sum = array_sum( array_map( 'ord', str_split( substr( $block, 0, 148 ). '        '. substr( $block, 156 ) ) ) );
					if( preg_match( '/^[0-7]+$/', trim( substr( $block, 148, 8 ), "\0 " ) ) !== 1 || octdec( trim( substr( $block, 148, 8 ), "\0 " ) ) !== $sum )
						return 'the backup is not a tar archive';

					$size = trim( substr( $block, 124, 12 ), "\0 " );
					if( preg_match( '/^[0-7]*$/', $size ) !== 1 )
						return 'the backup is not a tar archive';

					$size = (int) octdec( $size === '' ? '0' : $size );
					$type = $block[156];

					// A NUL byte is the end of a name in the header; one with bytes
					// after it is a name that reads differently to whoever stops at
					// it and whoever does not
					$name 	= self::_field( $block, 0, 100 );
					// The prefix is read whatever the magic of the header says: PharData
					// joins it to the name without looking at the magic
					$prefix = self::_field( $block, 345, 155 );

					if( $name === null || $prefix === null )
						return self::_refuse( rtrim( substr( $block, 0, 100 ), "\0" ), 'a name with a NUL byte' );

					if( $prefix !== '' )
						$name = $prefix. '/'. $name;

					$reason = self::_entry( $name, $type );
					if( $reason !== null )
						return self::_refuse( $name, $reason );

					// The blocks of the data are those of a file and of nothing else:
					// the size in the header of a directory is ignored by PharData
					// and by every tar, which reads the next block as the next
					// header. A reader that jumped over them would not see an
					// entry hidden there. Of the kinds accepted here, '0' and the
					// old "\0" carry data, '5' carries none
					$skip = $type === '5' ? 0 : (int) ( ceil( $size / 512 ) * 512 );
					if( $skip > 0 && gzseek( $handle, $skip, SEEK_CUR ) !== 0 )
						return null;
				}
			}
			finally {
				gzclose( $handle );
			}

			return null;
		}

		/**
		 *	Whether a file is one gzip stream and nothing else: the stream ends, and nothing follows its end - not a second member, which
		 *	zlib's readers join to the first as if it were one, and not bytes. PharData took neither, and the unpacking of this class
		 *	would take both, so a restore asks it first. Read in small pieces, so that the memory is that of one piece unpacked
		 *
		 *	@param		string		$gz						Absolute path of the .gz
		 *
		 *	@return 	bool
		 */
		public static function gzipOnly( string $gz ): bool {

			$handle = @fopen( $gz, 'rb' );
			$size 	= $handle === false ? 0 : (int) ( fstat( $handle )['size'] ?? 0 );
			$state 	= $handle === false ? false : @inflate_init( ZLIB_ENCODING_GZIP );

			if( $handle === false || $state === false ) {

				if( $handle !== false )
					fclose( $handle );

				return false;
			}

			try {
				while( feof( $handle ) === false ) {

					$piece = fread( $handle, 8192 );

					if( $piece === false || $piece === '' )
						break;

					if( @inflate_add( $state, $piece, ZLIB_NO_FLUSH ) === false )
						return false;

					if( inflate_get_status( $state ) === ZLIB_STREAM_END )
						return inflate_get_read_len( $state ) === $size;
				}
			}
			finally {
				fclose( $handle );
			}

			// It did not end
			return false;
		}

		/**
		 *	Unpack a .tar.gz into a directory, entry by entry and each file in pieces: what a restore stages an archive with. PharData
		 *	read the whole archive's index along and held about 4 % of the archive's size in memory for it - 19 MB for 500 MB of files - and
		 *	this holds a piece. It is the second reader of the headers after refusal(), which has judged them already: what is
		 *	still asked here is what writing needs - a file or a directory, a name of plain segments, a checksum that holds - and what
		 *	was written is read again by staged() before anything of it is used
		 *
		 *	@param		string		$tarGz				Absolute path of the .tar.gz
		 *	@param		string		$into					Absolute path of the directory the entries are made in, which exists
		 *	@param		bool			&$unwritten		(reference) Set where what failed is a write, a disk that is full
		 *
		 *	@return 	string|null							Why the archive could not be unpacked, null where it was
		 */
		public static function unpack( string $tarGz, string $into, bool &$unwritten = false ): ?string {

			$unwritten = false;

			$handle = @gzopen( $tarGz, 'rb' );

			if( $handle === false )
				return 'the backup could not be read';

			$root = rtrim( $into, DIRECTORY_SEPARATOR );

			try {
				while( ( $block = gzread( $handle, 512 ) ) !== false && $block !== '' ) {

					if( strlen( $block ) !== 512 )
						return 'the backup ends inside a header';

					if( $block === str_repeat( "\0", 512 ) )
						return null;

					$sum = array_sum( array_map( 'ord', str_split( substr( $block, 0, 148 ). '        '. substr( $block, 156 ) ) ) );
					if( preg_match( '/^[0-7]+$/', trim( substr( $block, 148, 8 ), "\0 " ) ) !== 1 || octdec( trim( substr( $block, 148, 8 ), "\0 " ) ) !== $sum )
						return 'the backup is not a tar archive';

					$size = trim( substr( $block, 124, 12 ), "\0 " );
					if( preg_match( '/^[0-7]*$/', $size ) !== 1 )
						return 'the backup is not a tar archive';

					$size 	= (int) octdec( $size === '' ? '0' : $size );
					$type 	= $block[156];
					$name 	= self::_field( $block, 0, 100 );
					$prefix = self::_field( $block, 345, 155 );

					if( $name === null || $prefix === null )
						return 'the backup holds a name with a NUL byte';

					if( $type !== '0' && $type !== "\0" && $type !== '5' )
						return 'the backup holds an entry that is no file and no directory';

					$segments = explode( '/', rtrim( $prefix !== '' ? $prefix. '/'. $name : $name, '/' ) );

					foreach( $segments as $segment )
						if( $segment === '' || $segment === '.' || $segment === '..' )
							return 'the backup holds a path that is none of plain segments';

					$target = $root. '/'. implode( '/', $segments );

					if( $type === '5' ) {

						if( is_dir( $target ) === false && @mkdir( $target, 0777, true ) === false && is_dir( $target ) === false )
							return 'a directory could not be made';

						continue;
					}

					if( is_dir( dirname( $target ) ) === false && @mkdir( dirname( $target ), 0777, true ) === false && is_dir( dirname( $target ) ) === false )
						return 'a directory could not be made';

					$out = @fopen( $target, 'wb' );

					if( $out === false )
						return 'a file could not be made';

					for( $left = $size; $left > 0; $left -= strlen( $piece ) ) {

						$piece = gzread( $handle, min( 1048576, $left ) );

						if( $piece === false || $piece === '' ) {
							fclose( $out );
							return 'the backup ends inside a file';
						}

						if( @fwrite( $out, $piece ) !== strlen( $piece ) ) {
							fclose( $out );
							$unwritten = true;
							return 'a file could not be written';
						}
					}

					if( @fclose( $out ) === false ) {
						$unwritten = true;
						return 'a file could not be written';
					}

					// The data comes in blocks of 512 bytes
					$padding = ( 512 - $size % 512 ) % 512;

					if( $padding > 0 && strlen( (string) gzread( $handle, $padding ) ) !== $padding )
						return 'the backup ends inside a file';
				}

				// The end of a tar is two blocks of zeros: a stream that stops before them was cut
				return 'the backup ends without its end';
			}
			finally {
				gzclose( $handle );
			}
		}

		/**
		 *	Take the files of the outbox of the mail out of the unpacked copy of an archive, whatever archive it is: one of an
		 *	older Nino, one somebody put together, one that carries what the backup of this kernel would not (see NEVER) - a
		 *	restore that brought the outbox back would send what went out long ago
		 *
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *
		 *	@return 	void
		 */
		public static function dropOutbox( string $staging ): void {

			foreach( self::OUTBOX as $name )
				@unlink( rtrim( $staging, DIRECTORY_SEPARATOR ). '/data/'. $name );

			// ...and the folder of the temporary files of its writes, which an archive that was made by hand may carry
			$folder = rtrim( $staging, DIRECTORY_SEPARATOR ). '/data/'. \Nino\Filesystem::PRIVATE_TEMP;

			if( is_dir( $folder ) === true && is_link( $folder ) === false )
				\Nino\Filesystem::removeDir( $folder );
		}

		/**
		 *	Whether the unpacked copy of an archive holds something a restore must
		 *	not copy into the project, read from the staging directory itself and
		 *	so from what was really unpacked, whatever the headers said (see
		 *	refusal()): a link, anything that is neither a file nor a directory, a
		 *	top-level entry outside the ones a backup makes, and below templates/
		 *	what template() does not take. The same rule as the headers' (_place())
		 *
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *
		 *	@return 	string|null							Why the archive is refused, or null where everything staged may be copied
		 */
		public static function staged( string $staging ): ?string {

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $staging, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach( $iterator as $item ) {

				$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $item->getPathname(), strlen( rtrim( $staging, DIRECTORY_SEPARATOR ) ) + 1 ) );

				if( $item->isLink() === true )
					return self::_refuse( $relative, 'a link' );

				if( $item->isFile() === false && $item->isDir() === false )
					return self::_refuse( $relative, 'an entry that is no file and no directory' );

				$reason = self::_place( explode( '/', $relative ), $item->isDir() );
				if( $reason !== null )
					return self::_refuse( $relative, $reason );
			}

			return null;
		}

		// The names a file below images/ may have: what Images writes (webp, png, jpg) and the other
		// formats a browser draws as a picture. Nothing a webserver hands to an interpreter, which is
		// what the directory is open to
		private const string IMAGE_NAME = '/^[A-Za-z0-9_][A-Za-z0-9_.-]*\.(?:jpe?g|png|webp|gif|avif|ico|svg)$/D';

		// The names a file below blocks/ may have, and one below fonts/: a block is a json, a template and the pictures of its slots, a font is
		// the font and the two files that say what it is. Nothing a webserver hands to an interpreter, and fonts/ is open to it
		private const string BLOCK_NAME = '/^[A-Za-z0-9_][A-Za-z0-9_.-]*\.(?:json|tpl|svg|webp|png|jpg)$/D';
		private const string FONT_NAME = '/^[A-Za-z0-9_][A-Za-z0-9_.-]*\.(?:woff2?|ttf|otf|json|txt)$/D';

		// One token of a data file's body: what var_export() writes for an array of strings, numbers,
		// booleans and null - and the same in the short form of an array, which a file written by hand has -
		// and nothing else. Anything that is no token of it is no data: a name, a variable, a call, a
		// '(' that does not belong to array(
		private const string DATA_TOKEN = '/\G(?:\s++|array\s*+\(|[\[\]),;]|=>|\.(?!\.)|-?(?:\d++(?:\.\d++)?(?:[eE][+-]?\d++)?|INF)|NAN|(?i:true|false|null)(?![A-Za-z0-9_])|\'(?:[^\'\\\\]++|\\\\.)*+\'|"\\\\0")/';

		// The one statement ahead of the return that is data: the declaration the install units' files start with
		private const string DECLARE = '/\Gdeclare\s*+\(\s*+strict_types\s*+=\s*+1\s*+\)\s*+;/';

		// The largest drawing (svg) a restore of an archive that was opened with a typed key judges: the check is a set of patterns over
		// one text, which cannot be run over a window of it without a drawing being cut where a script or a reference stands, so a drawing
		// above it is refused for its size and for nothing else - a drawing of a page is far smaller
		private const int SVG_MAX = 1048576;

		// The most a token of a data file may take where it has no end of its own that is in sight: a run of blanks or of digits. A file
		// with a longer one is no data file of Nino's, which writes none
		private const int TOKEN_MAX = 1048576;

		/**
		 *	Whether the text of a php file is data and nothing else: <?php, comments and one
		 *	declare(strict_types=1); where there are any, return, then what var_export() writes (see
		 *	DATA_TOKEN) with comments between its tokens, then the semicolon and nothing but blanks and
		 *	comments. It is read token by token with patterns of its own and not by the tokenizer, and
		 *	in pieces: a window of the file is held, a token is read where it stands in it, and a string
		 *	or a comment - which may be as long as the file - is walked over to its end without being
		 *	kept. Of a token that reaches the end of the window the next piece is read before it is
		 *	judged, so the answer is that of a reader of the whole text whatever the size of the window;
		 *	but for a run of blanks or digits beyond TOKEN_MAX, which is refused. Whatever the patterns
		 *	do not name - and a pattern that fails - is no data. A comment is what the element type files
		 *	of the install units and of the catalogue's features carry at their head and a person writes
		 *	between the lines of a file of their own: a line comment ends where php ends it, at any of the
		 *	three line breaks (a lone carriage return is one) and at a closing tag, which is left standing
		 *	to be refused; '#[' is an attribute and no comment
		 *
		 *	@param		resource	$handle				The file, at its start
		 *	@param		int				$window				How much is read at a time, and how much a token is looked ahead
		 *
		 *	@return 	bool
		 */
		private static function _isData( $handle, int $window = 65536 ): bool {

			$buffer = '';
			$at 		= 0;
			$eof 		= false;

			// At least $count bytes after $at, or what the file has left; what lies before $at goes
			$ensure = static function( int $count ) use ( $handle, $window, &$buffer, &$at, &$eof ): void {

				if( $eof === true || strlen( $buffer ) - $at >= $count )
					return;

				$buffer = (string) substr( $buffer, $at );
				$at 		= 0;

				while( $eof === false && strlen( $buffer ) < $count ) {

					$piece = fread( $handle, $window );

					if( $piece === false || $piece === '' )
						$eof = true;
					else
						$buffer .= $piece;
				}
			};

			// The token a pattern finds at $at, where it ends before the end of what is held or where there is no more of the file
			$match = static function( string $pattern ) use ( $ensure, &$buffer, &$at, &$eof ): ?string {

				while( true ) {

					$found = preg_match( $pattern, $buffer, $token, 0, $at ) === 1;

					if( $eof === true || ( $found === true && $at + strlen( $token[0] ) < strlen( $buffer ) ) )
						return $found === true ? $token[0] : null;

					if( strlen( $buffer ) - $at > self::TOKEN_MAX )
						return null;

					$ensure( strlen( $buffer ) - $at + 4096 );
				}
			};

			// Past a string that begins at $at: its end, and nothing in it but what the pattern of a string takes
			$string = static function() use ( $handle, $window, &$buffer, &$at, &$eof ): bool {

				$from = $at + 1;

				while( true ) {

					$from += strcspn( $buffer, "'\\", $from );

					if( $from >= strlen( $buffer ) || ( $buffer[$from] === '\\' && $from + 1 >= strlen( $buffer ) ) ) {

						// What was read is plain; a backslash waits for the character it escapes
						$buffer = $from < strlen( $buffer ) ? substr( $buffer, $from ) : '';
						$from 	= 0;
						$at 		= 0;
						$piece 	= $eof === true ? false : fread( $handle, $window );

						if( $piece === false || $piece === '' ) {
							$eof = true;
							return false;
						}

						$buffer .= $piece;
						continue;
					}

					if( $buffer[$from] === "'" ) {
						$at = $from + 1;
						return true;
					}

					// A backslash takes the character after it, and that is none of the line breaks the pattern's dot does not take
					if( $buffer[$from + 1] === "\n" )
						return false;

					$from += 2;
				}
			};

			// Past a comment that begins at $at, a block ($block) or one that ends at a line break; false where it has no end, or where a closing tag of php ends it
			$comment = static function( bool $block ) use ( $handle, $window, &$buffer, &$at, &$eof ): bool {

				$from = $at + ( $block === true ? 2 : 1 );

				if( $block === false && $buffer[$at] === '/' )
					$from++;

				while( true ) {

					$found = $block === true ? strpos( $buffer, '*/', $from ) : false;

					if( $block === false ) {

						$from += strcspn( $buffer, "\r\n?", $from );

						if( $from < strlen( $buffer ) && $buffer[$from] === '?' ) {

							if( $from + 1 < strlen( $buffer ) ) {

								if( $buffer[$from + 1] === '>' )
									return false;

								$from++;
								continue;
							}
						}
						else if( $from < strlen( $buffer ) ) {
							$at = $from;
							return true;
						}
					}
					else if( $found !== false ) {
						$at = $found + 2;
						return true;
					}

					// Everything but the last character, which may be the first of the end, is read and gone
					$keep 	= max( 0, strlen( $buffer ) - 1 );
					$piece 	= $eof === true ? false : fread( $handle, $window );

					if( $piece === false || $piece === '' ) {
						$eof = true;

						// A line comment that runs to the end of the file is closed by it
						if( $block === false ) {
							$buffer = '';
							$at 		= 0;
							return true;
						}

						return false;
					}

					$buffer = (string) substr( $buffer, $keep ). $piece;
					$from 	= $from > $keep ? $from - $keep : 0;
					$at 		= 0;
				}
			};

			$ensure( 6 );

			if( preg_match( '/^<\?php\s/', $buffer ) !== 1 )
				return false;

			$at 			= 5;
			$phase 		= 0;
			$declared = false;

			while( true ) {

				$ensure( $window );

				if( $at >= strlen( $buffer ) )
					break;

				$next = $buffer[$at + 1] ?? '';

				if( ( $buffer[$at] === '/' && $next === '*' ) || ( $buffer[$at] === '/' && $next === '/' ) || ( $buffer[$at] === '#' && $next !== '[' ) ) {

					if( $comment( $next === '*' && $buffer[$at] === '/' ) === false )
						return false;

					continue;
				}

				// Ahead of the return: blanks, one declare, then the return itself
				if( $phase === 0 ) {

					if( ( $token = $match( '/\G\s++/' ) ) !== null )
						$at += strlen( $token );
					else if( $declared === false && ( $token = $match( self::DECLARE ) ) !== null ) {
						$declared = true;
						$at += strlen( $token );
					}
					else if( $match( '/\Greturn(?=\s)/' ) !== null ) {
						$phase = 1;
						$at += 6;
					}
					else
						return false;

					continue;
				}

				// A string of the data is walked over; after the one semicolon nothing is but blanks and comments
				if( $buffer[$at] === "'" ) {

					if( $phase === 2 || $string() === false )
						return false;

					continue;
				}

				$token = $match( self::DATA_TOKEN );

				if( $token === null || ( $phase === 2 && trim( $token ) !== '' ) )
					return false;

				if( $token === ';' )
					$phase = 2;

				$at += strlen( $token );
			}

			return $phase === 2;
		}

		/**
		 *	Whether an svg is a drawing and nothing that runs: a file the browser opens as a document when
		 *	it is asked for by its address runs the script that is in it, and the pages the installer
		 *	brings have svg pictures, so the format cannot be refused outright. What is refused is what
		 *	makes an svg more than shapes: a script, an element that embeds or fetches another document
		 *	or animates an attribute, an event attribute, a reference that is not to the file itself, a
		 *	document type or an entity, and css that fetches or hides what it fetches. A check of the text and no parser: it is the one place of the
		 *	rule that is a judgement and not a grammar, and it errs on the side of refusing
		 *
		 *	@param		string		$source				The file's bytes
		 *
		 *	@return 	bool
		 */
		private static function _isPlainSvg( string $source ): bool {

			if( preg_match( '/^(?:\xEF\xBB\xBF)?\s*(?:<\?xml[^>]*\?>\s*)?(?:<!--.*?-->\s*)*<svg[\s>]/is', $source ) !== 1 )
				return false;

			if( preg_match( '/<\s*(?:script|foreignObject|iframe|embed|object|animate|set|handler|listener)\b|<!(?:DOCTYPE|ENTITY)|[\s"\'\/]on[a-z]+\s*=|(?:xlink:)?href\s*=\s*["\']?\s*(?!#)|javascript\s*:/i', $source ) === 1 )
				return false;

			// Style is css, and css fetches: an import, a font, an image set, a url that is no reference to
			// the file itself. What is written in an escape or an entity is not read as what it says, so a
			// style that has either is refused whole
			preg_match_all( '~<style\b[^>]*>(.*?)</style\s*>|\sstyle\s*=\s*"([^"]*)"|\sstyle\s*=\s*\'([^\']*)\'~is', $source, $styles, PREG_SET_ORDER );

			foreach( $styles as $style ) {

				$css = $style[1] !== '' ? $style[1] : ( ( $style[2] ?? '' ) !== '' ? $style[2] : ( $style[3] ?? '' ) );

				if( preg_match( '/[\\\\&]|@import|@font-face|image-set|-moz-binding|expression\s*\(|\bsrc\s*\(|url\s*\((?!\s*["\']?\s*#)/i', $css ) === 1 )
					return false;
			}

			return true;
		}

		/**
		 *	Whether the unpacked copy of an archive holds code. For an archive that was opened with a key
		 *	somebody TYPED: the project's own key proves that the archive was made by this project, a key
		 *	that is typed proves nothing about what is in it, and what a restore puts into the project is
		 *	read by php - config.php at every request, the texts, elements and data files, nino.php the
		 *	moment the restore reads it - or served by the webserver as it is, the pictures. So every
		 *	.php file must be data (_isData()), and what lies below images/ a picture with a name that
		 *	is none of a script's. Read from the staging directory, after staged() and before anything
		 *	of it is read or copied
		 *
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *
		 *	@return 	string|null							Why the archive is refused, or null where it holds data and pictures alone
		 */
		public static function codeRefusal( string $staging ): ?string {

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $staging, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach( $iterator as $item ) {

				$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $item->getPathname(), strlen( rtrim( $staging, DIRECTORY_SEPARATOR ) ) + 1 ) );
				$name 		= $item->getFilename();

				if( str_starts_with( $relative, 'images/' ) === true && ( preg_match( self::IMAGE_NAME, $name ) !== 1 || preg_match( '/\.(?:php|phtml|phar|pht|phps)(?:\d|\.|$)/i', $name ) === 1 ) )
					return self::_refuse( $relative, 'a file that is no picture' );

				// A svg of a block is read by the kernel as a drawing as well; what a restore puts there is held to the same rule
				if( ( str_starts_with( $relative, 'images/' ) === true || str_starts_with( $relative, 'blocks/' ) === true || str_starts_with( $relative, 'themes/' ) === true ) && preg_match( '/\.svg$/', $name ) === 1 ) {

					// Judged as one text, so only a drawing of the size a picture of shapes has: the limit is what the check takes of memory (measured
					// 14 MB for 1 MiB of the worst a drawing can hold), and a drawing of a page is far smaller
					$handle 	= @fopen( $item->getPathname(), 'rb' );
					$drawing 	= $handle === false ? false : stream_get_contents( $handle, self::SVG_MAX + 1 );

					if( $handle !== false )
						fclose( $handle );

					if( $drawing !== false && strlen( $drawing ) > self::SVG_MAX )
						return self::_refuse( $relative, 'a drawing of more than '. self::SVG_MAX. ' bytes, which is more than a restore judges' );

					if( $drawing === false || self::_isPlainSvg( $drawing ) === false )
						return self::_refuse( $relative, 'a drawing that is more than shapes' );
				}

				if( str_starts_with( $relative, 'blocks/' ) === true && self::block( substr( $relative, 7 ) ) === false )
					return self::_refuse( $relative, 'a file that is no part of a block' );

				if( str_starts_with( $relative, 'themes/' ) === true && self::theme( substr( $relative, 7 ) ) === false )
					return self::_refuse( $relative, 'a file that is no part of a theme' );

				if( str_starts_with( $relative, 'fonts/' ) === true && self::font( substr( $relative, 6 ) ) === false )
					return self::_refuse( $relative, 'a file that is no part of a font' );

				if( preg_match( '/\.(?:php|phtml|phar|pht|phps)\d?$/i', $name ) !== 1 )
					continue;

				// A php file by its name is data or it is refused; the extension is read as php reads it,
				// whatever the case
				$handle = @fopen( $item->getPathname(), 'rb' );
				$data 	= $handle !== false && self::_isData( $handle ) === true;

				if( $handle !== false )
					fclose( $handle );

				if( $data === false )
					return self::_refuse( $relative, 'code' );
			}

			return null;
		}

		// A header field up to its first NUL byte, or null where bytes follow that
		private static function _field( string $block, int $from, int $length ): ?string {

			$field = substr( $block, $from, $length );
			$end 	 = strpos( $field, "\0" );

			if( $end === false )
				return $field;

			return trim( substr( $field, $end ), "\0" ) === '' ? substr( $field, 0, $end ) : null;
		}

		/**
		 *	The reason one entry of an archive is not restored, or null
		 *
		 *	@param		string		$name					The entry's name as the tar holds it
		 *	@param		string		$type					The tar's type flag
		 *
		 *	@return 	string|null
		 */
		private static function _entry( string $name, string $type ): ?string {

			if( $type !== '0' && $type !== "\0" && $type !== '5' )
				return $type === '1' || $type === '2' ? 'a link' : 'an entry that is no file and no directory';

			// No rule of its own for an absolute path: its first segment is empty,
			// which no directory of the allowlist is
			$segments = explode( '/', rtrim( $name, '/' ) );

			if( in_array( '..', $segments, true ) === true )
				return 'a path that leaves the project';

			return self::_place( $segments, $type === '5' );
		}

		/**
		 *	Whether a file or a directory may lie where an archive puts it: the one
		 *	rule of the header reader (refusal()) and of the staged copy
		 *	(staged()), so that a difference between the two readings cannot
		 *	bring anything into the project that one of them would refuse
		 *
		 *	@param		array 		$segments			The path in the archive, split at '/'
		 *	@param		bool			$dir					Whether it is a directory
		 *
		 *	@return 	string|null
		 */
		private static function _place( array $segments, bool $dir ): ?string {

			// A directory is one of the top-level ones or below one; a file is
			// config.php or nino.php, or below a directory
			$known = count( $segments ) === 1 && $dir === false ? self::ARCHIVE_FILES : self::ARCHIVE_DIRS;

			if( in_array( $segments[0], $known, true ) === false )
				return 'a path that is none of the project\'s';

			if( $segments[0] === 'templates' && count( $segments ) > 1 ) {

				$below = array_slice( $segments, 1 );

				if( $dir === true ) {
					foreach( $below as $segment )
						if( str_ends_with( $segment, '.tpl' ) === true )
							return 'a directory that is called like a template';
				}
				else if( self::template( implode( '/', $below ) ) === false )
					return 'a file that is no template';
			}

			if( in_array( $segments[0], [ 'blocks', 'themes', 'fonts' ], true ) === true && count( $segments ) > 1 && $dir === false ) {

				$below = implode( '/', array_slice( $segments, 1 ) );
				$ok		 = match( $segments[0] ) { 'blocks' => self::block( $below ), 'themes' => self::theme( $below ), default => self::font( $below ) };

				if( $ok === false )
					return 'a file that is no part of a '. match( $segments[0] ) { 'blocks' => 'block', 'themes' => 'theme', default => 'font' };
			}

			return null;
		}

		// The reason, with a name that cannot break a line or a log: printable ascii, cut
		private static function _refuse( string $name, string $reason ): string {
			return 'the backup holds '. $reason. ': '. substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', $name ), 0, 80 );
		}

		/**
		 *	The state an archive comes from, as the file the archive carries it
		 *	in: nino.php, written the way Filesystem writes its data files. It
		 *	holds the version of Nino that made the archive and the version of
		 *	every feature the project had switched on then, by the feature's
		 *	key. Not a file of the project, so it is not in manifest():
		 *	\Nino\Modules\Backups::_create() and
		 *	\Nino\Modules\Backups\Admin::_safetySnapshot() add it after the
		 *	files the manifest names, and a restore reads it back from the
		 *	staged copy and says so where the version is not the one that runs
		 *	(see \Nino\Modules\Backups\Admin::_restoreFrom()). Kept here beside
		 *	the manifest for the same reason: the panel that restores an
		 *	archive has to work where no other class is loadable
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									The php source of the file: an array with 'nino' (the version) and 'features' (key => version)
		 */
		public static function stamp( array &$appData ): string {

			$features = [];

			foreach( \Nino\Features::all( $appData ) as $key => $feature )
				if( $feature['active'] === true )
					$features[(string) $key] = (string) $feature['version'];

			return '<?php return '. var_export( [ 'nino' => \Nino\VERSION, 'features' => $features ], true ). ';';
		}

		/**
		 *	Why a run that takes a safety copy of the project must not start: a feature is switched on
		 *	and its manifest is refused (see \Nino\Features::rejectedActive()), so the data it owns
		 *	cannot be named and an archive would lack it without a word. The one sentence of the backup
		 *	and of the restore of the Backups panel
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							The reason, null where nothing stands in the way
		 */
		public static function featureRefusal( array &$appData ): ?string {

			// A feature that is switched on and has no directory has no manifest to refuse and owns nothing a run could name: the project is backed up as it is
			$refused = array_diff_key( \Nino\Features::rejectedActive( $appData ), array_flip( \Nino\Features::missing( $appData ) ) );

			return $refused === [] ? null : 'backup refused: the feature in features/'. array_key_first( $refused ). ' is switched on and its manifest is refused ('. reset( $refused ). '), so its data cannot be named';
		}


		/**
		 *	The one sentence a backup or a restore adds to its answer where a feature is switched on and its directory is not there
		 *	(see \Nino\Features::missing()): what the archive holds of it is what the project has, and that is no folder
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							The sentence, null where every feature that is on is installed
		 */
		public static function missingNote( array &$appData ): ?string {

			$missing = \Nino\Features::missing( $appData );

			return $missing === [] ? null : 'the feature '. implode( ', ', array_map( static fn( string $name ): string => 'features/'. $name, $missing ) ). ' is switched on but not installed: its folder is not in the archive';
		}

		/**
		 *	Pack the files of a manifest into a tar and compress it: one pass over
		 *	all of them, which is what keeps the time in step with the number of
		 *	files - the entries added one by one made php write the archive again
		 *	for each of them - and what reads a file in pieces rather than whole.
		 *	The entries are the manifest's names with the file's bytes, and after
		 *	them nino.php (see stamp()); the mode of an entry is the file's. The
		 *	plain tar is removed as soon as the compressed one exists. The one
		 *	packing of \Nino\Modules\Backups::_create() and
		 *	\Nino\Modules\Backups\Admin::_safetySnapshot()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			Absolute path -> archive name, see manifest()
		 *	@param		string		$tar					Where the tar is made; the archive is $tar.'.gz'
		 *
		 *	@return 	void
		 *
		 *	@throws		\RuntimeException				If a file of the manifest cannot be read
		 */
		public static function pack( array &$appData, array $manifest, string $tar ): void {

			$entries = [];
			foreach( $manifest as $absolute => $name )
				$entries[$name] = $absolute;

			// The stamp is made before the step, which turns every notice into an exception: what it reads is no part of the writing
			$stamp = self::stamp( $appData );

			// Everything PharData says of a file that cannot be written - a disk that is full - is one sentence, and no path. It says it as a
			// notice first, and the handler of the kernel ends the request at a notice: so the step runs with a handler of its own
			// (see raising()), which makes it an exception that is caught here
			try {

				self::raising( static function() use ( $tar, $entries, $stamp ): void {

					$phar = new \PharData( $tar );

					$phar->buildFromIterator( new \ArrayIterator( $entries ) );
					$phar->addFromString( 'nino.php', $stamp );

					// Written out to the tar before it is touched below, and before the handler of the step is gone
					unset( $phar );
				} );
			}
			catch( \PharException | \UnexpectedValueException | \BadMethodCallException | \ErrorException ) {

				// The one that could not be read, by its name in the archive
				foreach( $entries as $name => $absolute )
					if( @is_readable( $absolute ) === false )
						throw new \RuntimeException( 'backup source could not be read: '. $name );

				// All of them can be read: what failed is the write
				throw new \RuntimeException( 'the backup could not be written: there was no room left, or a file could not be written', self::WRITE_FAILED );
			}

			self::_modes( $tar );
			self::_gzip( $tar, $tar. '.gz' );

			@unlink( $tar );
		}

		/**
		 *	Run a step in which every notice, warning and error that php raises is an \ErrorException, and the handler that was there is back
		 *	when it ends, whichever way it ends. What php functions say of a write that failed they say as a notice or a warning, and the
		 *	handler of the kernel ends the request at one: a step that wants to answer for it - a disk that is full - asks for the exception
		 *
		 *	@param		callable	$step					The work
		 *
		 *	@return 	mixed										What the step returns
		 *
		 *	@throws		\ErrorException						For what php raised in it
		 *	@throws		\PharException						Where the step does what PharData does and it says so by an exception of its own
		 *	@throws		\UnexpectedValueException
		 *	@throws		\BadMethodCallException
		 */
		public static function raising( callable $step ): mixed {

			set_error_handler( static function( int $level, string $message, string $file = '', int $line = 0 ): bool {

				// What the code silenced with @ is no failure of the step
				if( ( error_reporting() & $level ) === 0 )
					return false;

				throw new \ErrorException( $message, 0, $level, $file, $line );
			} );

			try {
				return $step();
			}
			finally {
				restore_error_handler();
			}
		}

		/**
		 *	Give every entry of a tar the mode an entry added from a string has,
		 *	which is the mode of a file this process creates: 0666 without the
		 *	umask. Packing a file takes the mode of the file along, and a source
		 *	that is read-only would then be read-only in the copy a restore stages
		 *	and hands to the features that merge into it. Only the mode field and
		 *	the checksum of the headers change; the data is jumped over
		 *
		 *	@param		string		$tar					Absolute path of the tar
		 *
		 *	@return 	void
		 */
		private static function _modes( string $tar ): void {

			$mode 	= sprintf( '%07o', 0666 & ~umask() );
			$handle = @fopen( $tar, 'r+b' );

			if( $handle === false )
				throw new \RuntimeException( 'the backup could not be read back', self::WRITE_FAILED );

			// A tar that php could not write to its end does not end with the zero
			// blocks of its end, or its last entry is longer than the file
			$stat 	= @fstat( $handle );
			$length = is_array( $stat ) === true ? $stat['size'] : null;
			$ended 	= false;

			while( ( $block = fread( $handle, 512 ) ) !== false && strlen( $block ) === 512 ) {

				if( trim( $block, "\0" ) === '' ) {
					$ended = true;
					break;
				}

				$size = (int) octdec( trim( substr( $block, 124, 12 ), "\0 " ) );

				if( $length !== null && ftell( $handle ) + (int) ( ceil( $size / 512 ) * 512 ) > $length )
					break;

				if( substr( $block, 100, 7 ) !== $mode ) {

					$block = substr_replace( $block, $mode, 100, 7 );
					$sum 	 = array_sum( array_map( 'ord', str_split( substr( $block, 0, 148 ). '        '. substr( $block, 156 ) ) ) );
					$block = substr_replace( $block, sprintf( '%06o', $sum ). "\0 ", 148, 8 );

					if( fseek( $handle, -512, SEEK_CUR ) !== 0 || @fwrite( $handle, $block ) !== 512 ) {
						fclose( $handle );
						throw new \RuntimeException( 'the backup could not be written', self::WRITE_FAILED );
					}
				}

				if( fseek( $handle, (int) ( ceil( $size / 512 ) * 512 ), SEEK_CUR ) !== 0 ) {
					fclose( $handle );
					throw new \RuntimeException( 'the backup could not be read back', self::WRITE_FAILED );
				}
			}

			if( $ended === false || @fflush( $handle ) === false || @fclose( $handle ) === false )
				throw new \RuntimeException( 'the backup could not be written', self::WRITE_FAILED );
		}

		/**
		 *	Compress a tar into a gzip file, in pieces, and read the result back
		 *	to its end: what comes out must be as long as what went in. Every
		 *	step is checked, and the last one is read back because the close of a
		 *	gzip stream says nothing of the last piece that php could not write
		 *	- a disk that is full leaves a file that ends short and reports no
		 *	error
		 *
		 *	@param		string		$tar					Absolute path of the tar
		 *	@param		string		$gz						Where the compressed copy is written
		 *
		 *	@return 	void
		 *
		 *	@throws		\RuntimeException				If a step fails or the file is not whole
		 */
		private static function _gzip( string $tar, string $gz ): void {

			$in 	= @fopen( $tar, 'rb' );
			$out 	= $in === false ? false : @gzopen( $gz, 'wb' );

			if( $in === false || $out === false ) {
				if( $in !== false )
					fclose( $in );
				throw new \RuntimeException( 'the backup could not be compressed', self::WRITE_FAILED );
			}

			$total = 0;

			try {
				while( feof( $in ) === false ) {

					$piece = fread( $in, 1048576 );

					if( $piece === false )
						throw new \RuntimeException( 'the backup could not be read back', self::WRITE_FAILED );

					if( $piece === '' )
						break;

					if( @gzwrite( $out, $piece ) !== strlen( $piece ) )
						throw new \RuntimeException( 'the backup could not be written', self::WRITE_FAILED );

					$total += strlen( $piece );
				}
			}
			finally {
				fclose( $in );
				$closed = @gzclose( $out );
			}

			if( $closed !== true )
				throw new \RuntimeException( 'the backup could not be written', self::WRITE_FAILED );

			// Read back to its end, without keeping it
			$back 	= @gzopen( $gz, 'rb' );
			$length = 0;

			if( $back === false )
				throw new \RuntimeException( 'the backup could not be read back', self::WRITE_FAILED );

			while( gzeof( $back ) === false ) {

				$piece = @gzread( $back, 1048576 );

				if( $piece === false || $piece === '' )
					break;

				$length += strlen( $piece );

				// A file that gives more than went in is not the one written
				if( $length > $total )
					break;
			}

			gzclose( $back );

			if( $length !== $total )
				throw new \RuntimeException( 'the backup is not whole: there was no room to write it, or it could not be written', self::WRITE_FAILED );
		}

		/**
		 *	What marks a file in the system's temp directory as this project's:
		 *	the temp directory is the server's, shared with every other site on
		 *	it, and what a run of this project leaves there is told from theirs
		 *	by the name of the private directory it came from
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Eight hex digits
		 */
		private static function _tempId( array &$appData ): string {

			$content = \Nino\Filesystem::getContentPath( $appData );

			return substr( sha1( (string) ( realpath( $content ) ?: $content ) ), 0, 8 );
		}

		/**
		 *	A file of a run's own in the system's temp directory, which stays
		 *	locked for as long as the run lives: the name is made of the kind of the
		 *	run and of the mark of this project, and what the run needs beside it
		 *	is that name plus a suffix ('.tar', '.tar.gz', '.staging'). The lock
		 *	is what tells a run that is going on from one that ended without a
		 *	word (see sweepTemp()) - the system takes it away with the process,
		 *	however that ends
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					'backup', 'snapshot' or 'restore'
		 *
		 *	@return 	array|false							[ path, lock handle ], or false where there is no file to be made
		 */
		public static function tempFile( array &$appData, string $kind ): array|false {

			$path = tempnam( sys_get_temp_dir(), 'nino'. $kind. '-'. self::_tempId( $appData ). '-' );

			if( $path === false )
				return false;

			$handle = @fopen( $path, 'r+b' );

			if( $handle === false || flock( $handle, LOCK_EX | LOCK_NB ) === false ) {
				@unlink( $path );
				return false;
			}

			return [ $path, $handle ];
		}

		/**
		 *	Remove what runs of this project left in the system's temp directory
		 *	when they ended without removing it - a request that php ended at
		 *	its time or memory limit, or that was killed, never reaches a
		 *	finally. Only the files and directories tempFile() names, with this
		 *	project's mark: whatever else lies there is none of ours. A run that
		 *	is going on holds the lock of its file, and what it has made stays
		 *	where it is; what is left of a run whose file is gone is removed
		 *	when it is a day old
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function sweepTemp( array &$appData ): void {

			$temp		= rtrim( sys_get_temp_dir(), '/' );
			$mark		= self::_tempId( $appData );
			$groups	= [];

			// The end of the name is what tempnam() made of it, and how long that is belongs to the php that runs: six
			// characters where the system names the file, around twenty where php does - so no length is asked of it
			// but one that a name can have
			foreach( @scandir( $temp ) ?: [] as $name )
				if( preg_match( '/^(nino(?:backup|snapshot|restore)-'. $mark. '-[A-Za-z0-9]{1,64})(?:\.tar|\.tar\.gz|\.staging)?$/', $name, $match ) === 1 )
					$groups[$match[1]][] = $name;

			$owner = function_exists( 'posix_geteuid' ) === true ? posix_geteuid() : null;

			foreach( $groups as $base => $names ) {

				// Only what this user made and that is no link: another user's file
				// of the same name, or a link put in its place, is none of ours
				foreach( $names as $index => $name ) {
					$stat = @lstat( $temp. '/'. $name );
					if( $stat === false || ( $stat['mode'] & 0170000 ) === 0120000 || ( $owner !== null && $stat['uid'] !== $owner ) )
						unset( $names[$index] );
				}

				$baseKept = in_array( $base, $names, true );

				// The main file is there and is somebody else's: the group is not ours
				if( count( $names ) === 0 || ( $baseKept === false && @lstat( $temp. '/'. $base ) !== false ) )
					continue;

				$lease = $baseKept === true ? @fopen( $temp. '/'. $base, 'r+b' ) : false;

				if( $lease !== false && flock( $lease, LOCK_EX | LOCK_NB ) === false ) {
					fclose( $lease );
					continue;
				}

				if( $lease === false )
					foreach( $names as $name )
						if( time() - (int) @filemtime( $temp. '/'. $name ) < 86400 )
							continue 2;

				foreach( $names as $name )
					\Nino\Filesystem::removeDir( $temp. '/'. $name );

				if( $lease !== false )
					fclose( $lease );
			}
		}
	}
}
