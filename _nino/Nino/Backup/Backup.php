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

			auth-tries.php is named in the docblock below as
			a transient throttling counter rather than data, and it was left
			out by simply not listing it - which held for as long as the list
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
			happens not to mention. A leading dot with it: '/data/.locks' and
			'/data/.counters' (the folders of \Nino\Feature::count(), which keeps its
			counters there since a counter in a file of its own grew with its clients)
			are the ones today, and everything hidden under /data/ is this
			framework's own bookkeeping rather than a project's content	*/
		// The code of the RuntimeException that says a write failed - no room left in
		// the temporary directory or where the archives are kept, or a file that
		// cannot be written - which the panel words in the interface language
		public const int WRITE_FAILED = 1;

		// The code of the RuntimeException that says the disk has less room than a run needs, before it began: the sizes are
		// carried beside it by the run (see \Nino\Modules\Backups::now())
		public const int DISK_SHORT = 2;

		// The code of the RuntimeException that says a feature could not hand over the copy of its data a run asked for (see stage()): it threw,
		// or what it put into its staging is no file of its own. The run that threw it has named the feature (see \Nino\Modules\Backups::stopped())
		public const int HOOK_FAILED = 3;

		// The code of the RuntimeException that says a file the archive is to hold could not be read, or changed while it was packed: a
		// file of the project that is gone or unreadable at the time it is packed and that is no companion of a database and no temporary
		// file of a writer of the kernel (see leavable()). The panel words it with the name of the file (see sourceName())
		public const int SOURCE_FAILED = 4;

		// The code of the RuntimeException that says the name an archive is to have is taken by an archive that is there: one is never put in the place of another
		// (see \Nino\Modules\Backups::publish()), and the run answers as it does for a run that is going on, for it is a matter of the next second
		public const int NAME_TAKEN = 5;

		// The hook a feature answers before its files are packed (see stage()), the counterpart of '/nino/admin/restore'
		public const string HOOK = '/nino/admin/backup';

		// The hook a feature answers while a restore puts the files back, before they are copied over the live ones (see restoreCallbacks())
		public const string RESTORE_HOOK = '/nino/admin/restore';

		// Where the system lists the locks that processes hold on files (Linux), read by inUse()
		private const string LOCKS = '/proc/locks';

		// Where the system says which process this is (Linux): the pid the list of the locks names is the one this link points to, which is not what getmypid() answers in a pid namespace that sees the procfs of its host
		private const string SELF = '/proc/self';

		// The free bytes a test says the disk has, in place of what the system answers (see freeSpace()): a static of the class, which no file of a project - config.php and so an archive - can set
		public static ?int $diskFree = null;

		// Where a test points inUse() instead: a static of the class, which no file of a project - config.php and so an archive - can set
		public static ?string $locksFile = null;

		// Where a test points held() to find the link of the process: a static of the class, which no file of a project - config.php and so an archive - can set
		public static ?string $selfLink = null;

		// Where a test lets a file go or change in the moment a writer of the project does (see pack()): called with 'looked' and the name of a file that was found there
		// before anything is decided of it, with 'read' after the files were looked at and before they are read, with 'opening' and the name of a file between the look
		// of the open and the open itself (see _open()), with 'opened' and the name of a file after the kernel opened and checked it and before php reads it, and with 'failed' after the
		// writing failed. 'read' and 'failed' come with the number of the try, which is 1. A static of the class, which no file of a project - config.php and so an archive - can set
		public static ?\Closure $packing = null;

		// How long a restore waits in all, in seconds, for the locks of the writers of the files it puts back (see lockFiles()): a static of the class, which a test
		// lowers and which no file of a project - config.php and so an archive - can set
		public static float $lockWait = 10.0;

		// How many locks a restore holds at once, every one being a file that is open (see locksHeld()): a static of the class, which a test lowers. null for
		// what the limit of open files of the process says
		public static ?int $locksHeld = null;

		// The files an open database keeps beside itself while it is used: they belong to the live file and to nothing that is copied
		private const array COMPANIONS = [ '-wal', '-shm', '-journal' ];

		// The name of the file a writer of the kernel makes beside the file it writes and renames over it - \Nino\Filesystem::_writeFile() and writeAtomic(),
		// \Nino\Admin\Admin::writeFileAtomic() and the copy a restore makes beside a file (see \Nino\Modules\Backups\Admin::_beside()): the name of the file, a dot,
		// twelve hex digits, '.tmp'. The first group is the file the copy is of
		private const string TEMPORARY = '/^(.+)\.[0-9a-f]{12}\.tmp$/D';

		// What the sentence of a source that could not be read begins with, and the sentence of one that changed while it was packed (see sourceName())
		private const string SOURCE_GONE = 'backup source could not be read: ';
		private const string SOURCE_CHANGED = 'backup source changed while it was packed: ';

		// What a run keeps free beyond what it counts: the tar's end, the stamp, the bookkeeping of the file system
		private const int SPACE_RESERVE = 4194304;

		/**
		 *	The free bytes of the disk a path lies on: what the system says, or what \Nino\Backup::$diskFree names as an int (the
		 *	test of the check cannot fill a disk)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A directory
		 *
		 *	@return 	int|null								null where the system does not say
		 */
		public static function freeSpace( array &$appData, string $path ): ?int {

			if( self::$diskFree !== null )
				return self::$diskFree;

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
		 *	@param		int				$staged				The bytes of the copies the features hand over before the packing (see copyBytes()), which lie in the temporary directory as well
		 *
		 *	@return 	int
		 */
		public static function spaceNeed( int $bytes, int $files, bool $together, int $staged = 0 ): int {
			return ( $together === true ? (int) ceil( $bytes * 7 / 3 ) : 2 * $bytes ) + 512 * $files + self::SPACE_RESERVE + $staged;
		}

		/**
		 *	Whether the disk has the room that packing files of this size needs, before anything is packed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$bytes				The bytes of the files
		 *	@param		int				$files				How many there are
		 *	@param		string|null	$archiveDir		The directory the archive is written to, where there is one
		 *	@param		int				$staged				The bytes of the copies that are still to be handed over (see spaceNeed()); 0 once they lie there
		 *
		 *	@return 	array|null							null where it fits or the system does not say, else [ megabytes needed, megabytes free ]
		 */
		public static function spaceShort( array &$appData, int $bytes, int $files, ?string $archiveDir, int $staged = 0 ): ?array {

			$temp 		= sys_get_temp_dir();
			$together = $archiveDir !== null && ( $a = @stat( $temp ) ) !== false && ( $b = @stat( $archiveDir ) ) !== false && $a['dev'] === $b['dev'];
			$need 		= self::spaceNeed( $bytes, $files, $together, $staged );
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
		 *	disk, what its files grow by - the size of the staged files less the size of those they replace -, the size of the largest file that is put back beside
		 *	the file it replaces (it exists twice for a moment) and the reserve. The staged tree
		 *	is read, so the sizes are the sizes, not a guess
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *
		 *	@return 	array|null							null where it fits or the system does not say, else [ megabytes needed, megabytes free ]
		 */
		public static function restoreRoom( array &$appData, string $staging ): ?array {

			$growth	 = [];
			$largest = [];
			$free 	 = [];

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
					$size 		= (int) @filesize( $file->getPathname() );
					$grow 		+= $size - ( is_file( $target. $relative ) === true ? (int) @filesize( $target. $relative ) : 0 );

					// A file that is put back beside the file and renamed over it exists twice for a moment (see renamed()), one at a time
					if( self::renamed( $name === 'config.php' ? 'config.php' : $name. str_replace( DIRECTORY_SEPARATOR, '/', $relative ) ) === true )
						$largest[$device] = max( $largest[$device] ?? 0, $size );
				}

				$growth[$device] = ( $growth[$device] ?? 0 ) + max( 0, $grow );
			}

			foreach( $growth as $device => $grow ) {

				$room = self::freeSpace( $appData, $free[$device] );
				$need = $grow + ( $largest[$device] ?? 0 ) + self::SPACE_RESERVE;

				if( $room !== null && $room < $need )
					return [ (int) ceil( $need / 1048576 ), (int) floor( max( 0, $room ) / 1048576 ) ];
			}

			return null;
		}

		// The files of the outbox of \Nino\Mail: not in a backup, and not brought back by a restore of any archive
		private const array OUTBOX = [ 'mail-queue.php', 'mail-queue-due.txt', 'mail-keys.php', 'mail-failed.php', 'mail-queue-damaged.php', 'mail-failed-damaged.php', 'mail-keys-damaged.php', 'mail-queue-unreadable.php', 'mail-queue-keys.php', 'mail-queue-stuck.php', 'mail-queue-record.php', 'mail-queue-unwritable.php', 'mail-queue-body.php', 'mail-queue-states.php', 'mail-states.php', 'mail-states-damaged.php', 'mail-secret.php' ];

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
		// auth-tries.php or mail-status.php - the
		// failed-login counters and the last failed mail - nor the outbox of
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
			// "Data, backup and restore"), and until this read it it was not:
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
		 *	The callbacks of '/nino/admin/backup' by the feature that registered them: a callback belongs to the feature whose class - or a class
		 *	below its namespace - owns it, as a shortcode does (see \Nino\Features::shortcodes()). Only a feature that is switched on has registered
		 *	one. A callback that no feature owns - a function, a class of the kernel or of app/ - has no manifest that could name a file for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ feature key => its callbacks, the number of callbacks that no feature owns ]
		 */
		private static function _hooks( array &$appData ): array {

			$features = \Nino\Features::all( $appData );
			$hooks		= [];
			$strays 	= 0;

			foreach( \Nino\Callbacks::registered( $appData, self::HOOK ) as $callback ) {

				$found = self::_featureOf( $features, $callback );

				if( $found === null )
					$strays++;
				else
					$hooks[$found][] = $callback;
			}

			return [ $hooks, $strays ];
		}

		/**
		 *	The key of the feature that is switched on and owns a callback: the feature whose class - or a class below its namespace - owns it
		 *
		 *	@param		array 		$features			\Nino\Features::all()
		 *	@param		mixed			$callback			A callable as the callbacks of a hook are registered
		 *
		 *	@return 	string|null							The key, null for a callback that no feature owns
		 */
		private static function _featureOf( array $features, mixed $callback ): ?string {

			$owner = \Nino\Callbacks::owner( $callback );

			foreach( $features as $key => $feature ) {

				$module = ltrim( (string) $feature['module'], '\\' );

				if( $feature['active'] === true && $owner !== null && ( $owner === $module || str_starts_with( $owner, $module. '\\' ) === true ) )
					return (string) $key;
			}

			return null;
		}

		/**
		 *	The names of a manifest that a feature owns by what its manifest says it does: below one of the files or directories of its 'data',
		 *	and none that a backup leaves out of every archive (see _transient())
		 *
		 *	@param		array 		$feature			One feature as \Nino\Features::all() answers it
		 *	@param		array 		$names				Archive names, eg. 'data/crm.sqlite'
		 *
		 *	@return 	array										The names of $names it owns
		 */
		private static function _owned( array $feature, array $names ): array {

			$owned = [];

			foreach( (array) ( $feature['data'] ?? [] ) as $entry ) {

				$entry = '/'. trim( (string) $entry, '/' );

				if( $entry === '/data' )
					continue;

				$prefix = ltrim( $entry, '/' );

				foreach( $names as $name )
					if( str_starts_with( $name, 'data/' ) === true && ( $name === $prefix || str_starts_with( $name, $prefix. '/' ) === true ) && self::_transient( substr( $name, 5 ) ) === false )
						$owned[$name] = $name;
			}

			return array_values( $owned );
		}

		// The name of the file whose companion a name is - an open database keeps it beside itself (see COMPANIONS) - or null for a name that is none
		private static function _companion( string $name ): ?string {

			foreach( self::COMPANIONS as $suffix )
				if( str_ends_with( $name, $suffix ) === true )
					return substr( $name, 0, -strlen( $suffix ) );

			return null;
		}

		/**
		 *	Which files of a manifest a backup may leave out, told by proof and never by a guess. A file is left out where it is gone and is one of two kinds:
		 *
		 *	- the companion of a database, <name>-wal, <name>-shm or <name>-journal, where <name> is a file of the manifest or the companion is one that
		 *	  raw() added beside a database it packs as it lies ($companions). An open database makes them and removes them as its writers come and go:
		 *	  a log of writes that is gone was written into the main file, which a pack reads after it (see pack())
		 *	- a temporary file of a writer of the kernel, <name>.<12 lowercase hex digits>.tmp (see TEMPORARY): it lives for the time of one write and
		 *	  is renamed over <name> or removed. Where it is gone, the shape is the proof; where it is there it is packed like any file
		 *
		 *	A name that a feature declares whole in its manifest is its own file whatever it is called and is never left out: a declared `foo-wal` or
		 *	`x.tmp` is data, and where it is gone the backup says so (SOURCE_FAILED). So is every other file that is gone or cannot be read
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			Absolute path -> archive name, see manifest()
		 *	@param		array 		$companions		The archive names that raw() added beside a database it packs as it lies
		 *
		 *	@return 	array										Archive name -> 'companion' | 'temporary' for the files that may be left out where they are gone
		 */
		public static function leavable( array &$appData, array $manifest, array $companions = [] ): array {

			$listed 	= array_flip( array_values( $manifest ) );
			$named		= null;
			$vanish		= [];

			foreach( array_keys( $listed ) as $name ) {

				$name 		= (string) $name;
				$base 		= self::_companion( $name );
				$temporary = preg_match( self::TEMPORARY, basename( $name ) ) === 1;

				if( $base === null && $temporary === false )
					continue;

				// What a feature names whole is its own, and read at most once
				$named ??= self::_named( $appData );

				if( isset( $named[$name] ) === true )
					continue;

				if( $base !== null && ( isset( $listed[$base] ) === true || in_array( $name, $companions, true ) === true ) ) {
					$vanish[$name] = 'companion';
					continue;
				}

				if( $temporary === true )
					$vanish[$name] = 'temporary';
			}

			return $vanish;
		}

		/**
		 *	The exception for a file of the manifest that cannot be read or that changed while it was packed: SOURCE_FAILED, naming the file by its name in
		 *	the archive - printable ascii, cut - and no path
		 *
		 *	@param		string		$name					The archive name
		 *	@param		bool			$changed			Whether the file was there and was another by the time the pack failed, and not gone or unreadable
		 *
		 *	@return 	\RuntimeException
		 */
		public static function sourceFailure( string $name, bool $changed = false ): \RuntimeException {
			return new \RuntimeException( ( $changed === true ? self::SOURCE_CHANGED : self::SOURCE_GONE ). substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', $name ), 0, 80 ), self::SOURCE_FAILED );
		}

		/**
		 *	The name of the file a SOURCE_FAILED exception names (see sourceFailure()), for the sentence of the panel and the note of the daily backup
		 *
		 *	@param		\Throwable	$failure
		 *
		 *	@return 	string|null							The archive name, null for an exception that is none of this kind
		 */
		public static function sourceName( \Throwable $failure ): ?string {

			if( get_class( $failure ) !== \RuntimeException::class || $failure->getCode() !== self::SOURCE_FAILED )
				return null;

			return preg_match( '/^backup source (?:could not be read|changed while it was packed): (.*)$/Ds', $failure->getMessage(), $found ) === 1 ? $found[1] : null;
		}

		/**
		 *	The bytes the copies of the features will take beside the files they are made of, counted before the hooks are asked: for each file a
		 *	feature with a hook owns, its size and the size of the files an open database keeps beside it - what a copy of it can hold at the most,
		 *	since a copy has what the file holds and what its log of writes (-wal) has still to add
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			Absolute path -> archive name, see manifest()
		 *
		 *	@return 	int
		 */
		public static function copyBytes( array &$appData, array $manifest ): int {

			[ $hooks ] = self::_hooks( $appData );

			if( $hooks === [] )
				return 0;

			$features = \Nino\Features::all( $appData );
			$byName 	= array_flip( $manifest );
			$counted	= [];
			$bytes		= 0;

			foreach( array_keys( $hooks ) as $key )
				foreach( self::_owned( $features[$key], array_values( $manifest ) ) as $name ) {

					// A companion of a file that is in the manifest is counted with it
					if( isset( $counted[$name] ) === true || isset( $byName[ (string) self::_companion( $name ) ] ) === true )
						continue;

					$counted[$name] = true;
					$bytes += (int) @filesize( $byName[$name] );

					foreach( self::COMPANIONS as $suffix )
						$bytes += (int) @filesize( $byName[$name]. $suffix );
				}

			return $bytes;
		}

		/**
		 *	What a feature put into its staging, judged: every entry is a file of a plain kind - no link, no folder that is not on the way to one, no
		 *	device, pipe or socket, no file with a second name - and its name, relative to the staging, is one the manifest of the feature gives
		 *	and the live file of which is in the manifest. Read with lstat() and never opened, so a pipe cannot block it
		 *
		 *	@param		string		$key					The feature's key, for the sentence
		 *	@param		string		$dir					The staging of the feature
		 *	@param		array 		$allowed			The names the feature may hand over, relative to data/, as keys
		 *	@param		string		$below				The folder of the staging that is read, relative to it; '' for the staging itself
		 *	@param		array 		$declared			The entries of the manifest of the feature below data/ (without the slash at their ends): a name below one that is not allowed has no live file yet
		 *
		 *	@return 	array										Name relative to data/ -> absolute path of the copy
		 *
		 *	@throws		\RuntimeException					HOOK_FAILED, for the first entry that is none of these
		 */
		private static function _staged( string $key, string $dir, array $allowed, string $below = '', array $declared = [] ): array {

			$files = [];
			$here	 = $below === '' ? $dir : $dir. '/'. $below;

			// What php remembers of a path it was asked about is what the feature has changed since
			clearstatcache();

			foreach( @scandir( $here ) ?: [] as $entry ) {

				if( $entry === '.' || $entry === '..' )
					continue;

				$relative = $below === '' ? $entry : $below. '/'. $entry;
				$stat 		= @lstat( $dir. '/'. $relative );
				$kind 		= $stat === false ? 0 : $stat['mode'] & 0170000;
				$refuse		= static fn( string $what ): \RuntimeException => new \RuntimeException( 'the feature "'. $key. '" put '. $what. ' into its copy of the data, which is not taken: '. substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', $relative ), 0, 80 ), self::HOOK_FAILED );

				if( $kind === 0040000 ) {

					$inside = self::_staged( $key, $dir, $allowed, $relative, $declared );

					if( $inside === [] )
						throw $refuse( 'a folder with no file of its own' );

					$files += $inside;
					continue;
				}

				if( $kind === 0120000 )
					throw $refuse( 'a link' );

				if( $kind !== 0100000 )
					throw $refuse( 'an entry that is no file' );

				if( $stat['nlink'] !== 1 )
					throw $refuse( 'a file with a second name' );

				if( isset( $allowed[$relative] ) === false ) {

					// A name of its own that the project has no file of yet - a database that the first run of the feature has not made - is told as that
					foreach( $declared as $entry )
						if( $relative === $entry || str_starts_with( $relative, $entry. '/' ) === true )
							throw $refuse( 'a file that the project does not have yet, which is not asked for (a file is handed over where it exists)' );

					throw $refuse( 'a name the manifest of the feature does not give it' );
				}

				$files[$relative] = $dir. '/'. $relative;
			}

			return $files;
		}

		/**
		 *	That what lies in the staging is what was judged and nothing else, asked after each feature, so that the one that changed it is the one named:
		 *	a folder or a name more in the run's own folder, a name more or less in the folder of a feature that was asked before, and a file of one that is
		 *	another size, or was written, since. This is what the check is and what it is not: it finds a feature that is wrong - one that writes where it
		 *	was not asked to, or that leaves a link or a file of another kind - and it names it. It is no defence against a feature that means to deceive
		 *	the run: a hook is code of the feature and runs with the rights of the kernel, so it can read and write every file of the project itself, put
		 *	back a time it changed, and swap a file between the check and the packing. What it is not asked to do it cannot be stopped from doing
		 *
		 *	@param		string		$staging			The run's staging
		 *	@param		string|null	$key					The feature that was asked last: its folder is there and is not judged yet; null where there is none
		 *	@param		array 		$handed				What the features before it handed over: key => name relative to data/ => absolute path of the copy
		 *	@param		array 		$sealed				Of each copy, [ size, mtime, inode ] as it was when the feature was done
		 *
		 *	@return 	void
		 *
		 *	@throws		\RuntimeException					HOOK_FAILED, naming $key
		 */
		private static function _unchanged( string $staging, ?string $key, array $handed, array $sealed ): void {

			$found = array_values( array_diff( @scandir( $staging ) ?: [], [ '.', '..' ] ) );
			$known = array_map( 'strval', array_merge( array_keys( $handed ), $key === null ? [] : [ $key ] ) );
			$who	 = $key === null ? 'a feature' : 'the feature "'. $key. '"';

			sort( $found );
			sort( $known );

			if( $found !== $known )
				throw new \RuntimeException( $who. ' changed the folder for the copies of the data of the run, which is not its own', self::HOOK_FAILED );

			foreach( $handed as $before => $files ) {

				if( self::_staged( (string) $before, $staging. '/'. $before, array_flip( array_keys( $files ) ) ) !== $files )
					throw new \RuntimeException( $who. ' changed the copies of the data of the feature "'. $before. '"', self::HOOK_FAILED );

				foreach( $files as $copy ) {

					$stat = @lstat( $copy );

					if( $stat === false || [ $stat['size'], $stat['mtime'], $stat['ino'] ] !== $sealed[$copy] )
						throw new \RuntimeException( $who. ' changed the copies of the data of the feature "'. $before. '"', self::HOOK_FAILED );
				}
			}
		}

		// What php says of a write that failed because the disk is full: a hook that meets it ends the run as a disk that is full does, and not as a hook that is wrong
		private const string DISK_FULL = '/No space left on device|errno=28|out of free disk space|Disk quota exceeded|database or disk is full/i';

		/**
		 *	The names that the manifests of the features name themselves below data/, whole: a file a feature declares and not one that is found in a
		 *	folder it declares. A file of that kind is its own, whatever it is called
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Archive names as keys
		 */
		private static function _named( array &$appData ): array {

			$named = [];

			foreach( \Nino\Features::all( $appData ) as $feature )
				foreach( (array) ( $feature['data'] ?? [] ) as $entry )
					$named[ ltrim( '/'. trim( (string) $entry, '/' ), '/' ) ] = true;

			return $named;
		}

		/**
		 *	The files of a manifest with the files an open database keeps beside its file added, for the names the features in $keys declare: the state
		 *	of those features as it is, whole - what a safety snapshot packs for a feature that could not hand over a copy. A name that a feature
		 *	declares counts whether or not its main file is there: the files beside a database that is gone are part of the state as well. A main file
		 *	that is a link is left out, and named in $links: what lies behind a link is no file of the project
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			Absolute path -> archive name, see manifest()
		 *	@param		array 		$keys					Keys of features
		 *	@param		array 		&$links				(reference) The archive names of the main files that were left out as links
		 *	@param		array 		&$added				(reference) The archive names of the files beside a database that were added: the companions a pack may leave out where they are gone (see leavable())
		 *
		 *	@return 	array										The manifest to pack
		 */
		public static function raw( array &$appData, array $manifest, array $keys, array &$links = [], array &$added = [] ): array {

			$features = \Nino\Features::all( $appData );
			$byName 	= array_flip( $manifest );

			foreach( $keys as $key ) {

				$names = self::_owned( $features[$key] ?? [], array_values( $manifest ) );

				foreach( (array) ( $features[$key]['data'] ?? [] ) as $entry ) {

					$entry = ltrim( '/'. trim( (string) $entry, '/' ), '/' );

					if( $entry !== 'data' && str_starts_with( $entry, 'data/' ) === true && self::_transient( substr( $entry, 5 ) ) === false )
						$names[] = $entry;
				}

				foreach( array_unique( $names ) as $name ) {

					$live = \Nino\Filesystem::path( $appData, '/'. $name );

					if( is_dir( $live ) === true && is_link( $live ) === false )
						continue;

					if( is_link( $live ) === true ) {

						unset( $manifest[$live] );
						$links[] = $name;
					}

					foreach( self::COMPANIONS as $suffix )
						if( isset( $byName[$name. $suffix] ) === false && is_file( $live. $suffix ) === true && is_link( $live. $suffix ) === false ) {
							$manifest[$live. $suffix] = $name. $suffix;
							$byName[$name. $suffix] = $live. $suffix;
							$added[] = $name. $suffix;
						}
				}
			}

			return $manifest;
		}

		/**
		 *	Ask the features that keep a file the backup cannot copy as it lies - a database that is written to - for a copy that is whole, before
		 *	anything is packed. Each feature that is switched on and has a callback of '/nino/admin/backup' is called with { dataDir, staging }:
		 *	the live data directory and a folder of its own, empty, below $staging, made 0700 by this run. It writes the copy of each of its files there
		 *	under the name the file has below data/ (staging/crm.sqlite, made by VACUUM INTO), and the manifest that comes back names that copy where the
		 *	live file stood. The copy replaces a file the manifest already has and only that: what a feature puts there that its own manifest does not
		 *	give - another name, a name of another feature, a case twin, a link, a folder, a pipe, a file with a second name - ends the run, naming the
		 *	feature, and so does a hook that throws. Nothing is then packed and nothing falls back to the live file. A file a feature owns that is not
		 *	handed over is packed as it lies, as it is for a feature that has no hook. Of a database that was handed over, the files an open one keeps
		 *	beside itself (-wal, -shm, -journal) are left out - unless a feature names such a file itself - and the names that were handed over are
		 *	written to $notes, which the archive carries in its stamp: a restore takes the companions of those and of no other file out of the project.
		 *
		 *	With $tolerant - the safety snapshot before a restore, which is the net under the state as it IS - a feature whose hook fails does not end the
		 *	run: its copy is dropped, its files are packed as they lie with the files an open database keeps beside them, and $notes says so ('raw').
		 *	A restore is never kept from the project by a hook that cannot do its work, which is what a restore is there for.
		 *
		 *	What the check of the staging is and is not: see _unchanged()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			Absolute path -> archive name, see manifest()
		 *	@param		string		$staging			Where the run's staging is made, not existing yet: below the temporary directory, in the name of the run's own file
		 *	@param		callable|null	$announce		Called with the key of the feature before it is asked, and with '' after the last: what the run records of where it is
		 *	@param		bool			$tolerant			Whether a hook that fails is not the end of the run
		 *	@param		array 		&$notes				(reference) Set: 'asked' => keys of the features asked, 'copies' => archive names that were handed over, 'raw' => key => why, for the features whose files are packed as they lie, 'links' => the archive names left out as links, 'companions' => the archive names of the files beside a database that raw() added
		 *
		 *	@return 	array										The manifest to pack: the one that was given where no feature has a hook
		 *
		 *	@throws		\RuntimeException					HOOK_FAILED, or WRITE_FAILED where the staging cannot be made or the disk is full
		 */
		public static function stage( array &$appData, array $manifest, string $staging, ?callable $announce = null, bool $tolerant = false, array &$notes = [] ): array {

			$notes = [ 'asked' => [], 'copies' => [], 'raw' => [], 'links' => [], 'companions' => [] ];

			[ $hooks, $strays ] = self::_hooks( $appData );

			if( $strays > 0 )
				trigger_error( 'Backup: '. $strays. ' callback(s) of \''. self::HOOK. '\' belong to no feature that is switched on, and are not asked', E_USER_WARNING );

			if( $hooks === [] )
				return $manifest;

			// The lock of a run is held by the process, so a hook that asks for a backup of its own would be let in by it, and be asked itself
			if( ( $appData['./nino/backup/staging'] ?? false ) === true )
				throw new \RuntimeException( 'a feature asked for a backup while the features were handing over their data', self::HOOK_FAILED );

			if( @mkdir( $staging, 0700 ) === false )
				throw new \RuntimeException( 'the backup could not be written: the folder for the copies of the data could not be made', self::WRITE_FAILED );

			$features = \Nino\Features::all( $appData );
			$names		= array_values( $manifest );
			$dataDir	= \Nino\Filesystem::path( $appData, '/data' );
			$handed 	= [];
			$claimed	= [];
			$sealed 	= [];

			$appData['./nino/backup/staging'] = true;

			try {

				foreach( $hooks as $key => $callbacks ) {

					$dir = $staging. '/'. $key;

					if( @mkdir( $dir, 0700 ) === false )
						throw new \RuntimeException( 'the backup could not be written: the folder for the copies of the data could not be made', self::WRITE_FAILED );

					$notes['asked'][] = (string) $key;

					if( $announce !== null )
						$announce( (string) $key );

					try {

						self::_ask( $appData, (string) $key, $callbacks, $dataDir, $dir );

						// What the ones before it handed over is as they left it, and what this one hands over is its own
						self::_unchanged( $staging, (string) $key, $handed, $sealed );

						$allowed	= array_flip( array_map( static fn( string $name ): string => substr( $name, 5 ), self::_owned( $features[$key], $names ) ) );
						$declared	= array_map( static fn( string $entry ): string => substr( trim( (string) $entry, '/' ), 5 ), array_filter( (array) ( $features[$key]['data'] ?? [] ), static fn( mixed $entry ): bool => str_starts_with( trim( (string) $entry, '/' ), 'data/' ) ) );
						$own			= self::_staged( (string) $key, $dir, $allowed, '', $declared );

						foreach( $own as $relative => $copy ) {

							if( isset( $claimed[$relative] ) === true )
								throw new \RuntimeException( 'the feature "'. $key. '" handed over a file that the feature "'. $claimed[$relative]. '" handed over as well: '. substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', (string) $relative ), 0, 80 ), self::HOOK_FAILED );

							$base = self::_companion( (string) $relative );

							if( $base !== null && ( isset( $own[$base] ) === true || isset( $claimed[$base] ) === true ) )
								throw new \RuntimeException( 'the feature "'. $key. '" handed over a file that is the companion of another handed over: '. substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', (string) $relative ), 0, 80 ), self::HOOK_FAILED );

							foreach( self::COMPANIONS as $suffix )
								if( isset( $claimed[$relative. $suffix] ) === true )
									throw new \RuntimeException( 'the feature "'. $key. '" handed over a file of which another handed over is the companion: '. substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', (string) $relative ), 0, 80 ), self::HOOK_FAILED );
						}

						$handed[$key] = $own;

						foreach( $own as $relative => $copy ) {

							$claimed[$relative] = (string) $key;
							$stat = lstat( $copy );
							$sealed[$copy] = [ $stat['size'] ?? 0, $stat['mtime'] ?? 0, $stat['ino'] ?? 0 ];
						}
					}
					catch( \RuntimeException $failed ) {

						if( $tolerant === false || in_array( $failed->getCode(), [ self::HOOK_FAILED, self::WRITE_FAILED ], true ) === false )
							throw $failed;

						// The state as it is: this feature's files are packed as they lie, with what an open database keeps beside them, and the snapshot says so
						$notes['raw'][(string) $key] = $failed->getMessage();
						trigger_error( 'Backup: the safety snapshot holds the files of the feature "'. $key. '" as they lie: '. $failed->getMessage(), E_USER_WARNING );

						\Nino\Filesystem::removeDir( $dir );

						// What it did to the copies of the ones before it cannot be told apart from what they made: those are packed as they lie as well
						try {
							self::_unchanged( $staging, null, $handed, $sealed );
						}
						catch( \RuntimeException $changed ) {

							foreach( array_keys( $handed ) as $before ) {
								$notes['raw'][(string) $before] = $changed->getMessage();
								\Nino\Filesystem::removeDir( $staging. '/'. $before );
							}

							foreach( array_diff( @scandir( $staging ) ?: [], [ '.', '..' ] ) as $left )
								\Nino\Filesystem::removeDir( $staging. '/'. $left );

							$handed 	= [];
							$claimed	= [];
							$sealed 	= [];
						}
					}
				}
			}
			finally {
				unset( $appData['./nino/backup/staging'] );
			}

			if( $announce !== null )
				$announce( '' );

			$named		= self::_named( $appData );
			$replaced = [];
			$left 		= [];

			foreach( $claimed as $relative => $key ) {

				$replaced['data/'. $relative] = $staging. '/'. $key. '/'. $relative;

				// Beside a copy the files of the open database are left out: of its live file, which the copy replaces - unless a feature names one itself
				foreach( self::COMPANIONS as $suffix )
					if( isset( $named['data/'. $relative. $suffix] ) === false )
						$left['data/'. $relative. $suffix] = true;

				$notes['copies'][] = 'data/'. $relative;
			}

			$result = [];

			foreach( $manifest as $absolute => $name ) {

				if( isset( $left[$name] ) === true )
					continue;

				if( isset( $replaced[$name] ) === true )
					$result[$replaced[$name]] = $name;
				else
					$result[$absolute] = $name;
			}

			$notes['links'] = [];

			if( $notes['raw'] === [] )
				return $result;

			$result = self::raw( $appData, $result, array_keys( $notes['raw'] ), $notes['links'], $notes['companions'] );

			if( $notes['links'] !== [] )
				trigger_error( 'Backup: the safety snapshot leaves out '. count( $notes['links'] ). ' file(s) of a feature that are links, which are no files of the project', E_USER_WARNING );

			return $result;
		}

		/**
		 *	Call the callbacks of one feature with its folder. A hook that throws - an error as well - or leaves a notice of a write that the disk
		 *	refused ends here as a run that could not hand over the data, or as a run whose disk is full
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The feature
		 *	@param		array 		$callbacks		Its callbacks of the hook
		 *	@param		string		$dataDir			The live data directory
		 *	@param		string		$dir					Its folder of the run
		 *
		 *	@return 	void
		 *
		 *	@throws		\RuntimeException					HOOK_FAILED, or WRITE_FAILED for a disk that is full
		 */
		private static function _ask( array &$appData, string $key, array $callbacks, string $dataDir, string $dir ): void {

			// A write that the disk refused is a notice for php, which the handler of the kernel answers by ending the request: here it is the run's to
			// answer. Everything else goes to the handler that was there
			$previous = null;
			$previous = set_error_handler( static function( int $level, string $message, string $file = '', int $line = 0 ) use ( &$previous ): bool {

				if( ( error_reporting() & $level ) !== 0 && preg_match( self::DISK_FULL, $message ) === 1 )
					throw new \ErrorException( $message, 0, $level, $file, $line );

				return is_callable( $previous ) === true ? (bool) $previous( $level, $message, $file, $line ) : false;
			} );

			try {

				foreach( $callbacks as $callback ) {

					$args = [ 'dataDir' => $dataDir, 'staging' => $dir ];

					call_user_func_array( $callback, [ &$appData, &$args ] );
				}

				restore_error_handler();
			}
			catch( \Throwable $thrown ) {

				restore_error_handler();

				if( $thrown instanceof \Nino\Failure )
					throw $thrown;

				// What the feature said is for the log, which is private: the message of the run names the feature and no path
				trigger_error( 'Backup: the feature "'. $key. '" threw '. get_class( $thrown ). ' while it handed over its data: '. str_replace( [ "\r", "\n" ], ' ', $thrown->getMessage() ), E_USER_WARNING );

				if( preg_match( self::DISK_FULL, $thrown->getMessage() ) === 1 )
					throw new \RuntimeException( 'the backup could not be written: there was no room left, or a file could not be written', self::WRITE_FAILED, $thrown );

				throw new \RuntimeException( 'the feature "'. $key. '" could not hand over a copy of its data, so no backup was made', self::HOOK_FAILED, $thrown );
			}
		}

		/**
		 *	Call the callbacks of '/nino/admin/restore' the way \Nino\Callbacks::doCallbacks() does - in their order, a value a callback returns
		 *	replacing the arguments of the next - with one difference: a callback that throws, an error as well, does not end the restore. A restore
		 *	is never kept from happening by a callback, and config.php is replaced by the time they run, so a restore that ended here would leave the
		 *	project half in the archive's state. The callbacks that follow run, what the failing one said goes to the log, and the key of what it
		 *	belongs to is answered: the feature that owns the callback, else the class that does. What ends the request as \Nino\Runtime::fail()
		 *	does (\Nino\Failure) is thrown on at once, as everywhere (AGENTS.md, failure behavior), and so does an exit()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$args				(reference) What a callback is handed: [ 'dataDir' => ..., 'staging' => ... ]
		 *	@param		array 		$watch				Archive names, eg. 'data/crm.sqlite', of the staged files that a failing callback is asked about: which of them it took out of the staging
		 *	@param		array 		&$took				(reference) Set: the names of $watch that a callback that then threw took out of the staging
		 *
		 *	@return 	array										The keys of the features whose callbacks threw, once each, in the order they failed
		 */
		public static function restoreCallbacks( array &$appData, array &$args, array $watch = [], array &$took = [] ): array {

			$features = \Nino\Features::all( $appData );
			$failed 	= [];
			$took			= [];

			foreach( \Nino\Callbacks::registered( $appData, self::RESTORE_HOOK ) as $callback ) {

				$staged = array_filter( $watch, static fn( string $name ): bool => is_file( $args['staging']. '/'. $name ) === true );

				try {
					$args = call_user_func_array( $callback, [ &$appData, &$args ] ) ?? $args;
				}
				catch( \Nino\Failure $thrown ) {
					throw $thrown;
				}
				catch( \Throwable $thrown ) {

					$key = self::_featureOf( $features, $callback ) ?? \Nino\Callbacks::owner( $callback ) ?? 'unknown';

					// What the callback said is for the log, which is private: the answer of the restore names the feature and no path
					trigger_error( 'Backup: the feature "'. $key. '" threw '. get_class( $thrown ). ' while it restored its data: '. str_replace( [ "\r", "\n" ], ' ', $thrown->getMessage() ), E_USER_WARNING );

					$failed[$key] = $key;

					// A file it took out of the staging and then failed on: the live file is what the callback left of it, and the restore puts nothing back
					clearstatcache();

					foreach( $staged as $name )
						if( is_file( $args['staging']. '/'. $name ) === false )
							$took[$name] = $name;
				}
			}

			$took = array_values( $took );

			return array_values( $failed );
		}

		/**
		 *	The files of a stamp's 'copies' that a restore may rely on, as they are in the staging: [ archive name => whether a feature with a hook owns
		 *	it ] (see _handedOver()). Asked before the callbacks of the restore run, it is the list as the archive made it; what a callback takes out of
		 *	the staging after that stays on it, and inUse() and dropCompanions() leave it alone
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *	@param		mixed			$copies				The 'copies' of the stamp of the archive
		 *
		 *	@return 	array
		 */
		public static function handedOver( array &$appData, string $staging, mixed $copies ): array {
			return self::_handedOver( $appData, rtrim( $staging, DIRECTORY_SEPARATOR ), $copies );
		}

		/**
		 *	The files of a stamp's 'copies' that a restore may rely on: those of a feature that is switched on, has a callback of '/nino/admin/backup'
		 *	and owns the file by its manifest. The list is the archive's word and a stamp can be made by anybody who can make an archive, so it is
		 *	believed as far as the project can check it: a name is taken if the archive still carries it as a file (a feature that restored its data
		 *	itself has taken it out of the staging), it is no link and no companion, and the list has no more names than the archive has files below data/
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$root					Absolute path of the directory the archive was unpacked into, without a trailing slash
		 *	@param		mixed			$copies				The 'copies' of the stamp of the archive: archive names, as they were handed over
		 *
		 *	@return 	array										[ archive name => whether a feature with a hook owns it ] for the names that are taken
		 */
		private static function _handedOver( array &$appData, string $root, mixed $copies ): array {

			if( is_array( $copies ) === false || $copies === [] || is_dir( $root. '/data' ) === false )
				return [];

			clearstatcache();

			$count = 0;

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root. '/data', \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $carried )
				$count++;

			// More names than the archive has files: no stamp that was made by this code
			if( count( $copies ) > $count )
				return [];

			[ $hooks ] = self::_hooks( $appData );
			$features = \Nino\Features::all( $appData );
			$taken		= [];

			foreach( $copies as $name ) {

				// The stamp is the archive's: a name of it is judged as a name of an archive is, whatever the archive says
				if( is_string( $name ) === false || preg_match( '#^data/[^\0]+$#D', $name ) !== 1 || in_array( '..', explode( '/', $name ), true ) === true || in_array( '', explode( '/', $name ), true ) === true )
					continue;

				if( is_file( $root. '/'. $name ) === false || is_link( $root. '/'. $name ) === true || self::_companion( $name ) !== null )
					continue;

				$mine = false;

				foreach( array_keys( $hooks ) as $key )
					if( self::_owned( $features[$key], [ $name ] ) !== [] )
						$mine = true;

				$taken[$name] = $mine;
			}

			return $taken;
		}

		/**
		 *	The files a restore must not put back because another process uses them: of the files that a backup hook handed over whole and that
		 *	are still in the staging after the callbacks of the restore - the feature has not taken them over itself - those whose live file has a
		 *	POSIX lock on it, as a database has while a process reads it or writes to it (SQLite holds one for as long as a connection is in a
		 *	transaction, and in WAL mode for as long as it is open). Put back in place, the copy would write the archive's pages into the file under
		 *	the feet of that process, which goes on writing pages it computed from the old content: the database is corrupt then
		 *
		 *	How it is told without touching the file: stat() for the device and the inode, and the list of the locks the system keeps
		 *	('/proc/locks'). Opening the file to ask it would end the locks of this very process, since closing any descriptor of a file drops the
		 *	locks the process holds on it. Where the list cannot be read (a system that is not Linux, a restricted one) nothing is known and
		 *	nothing is left out: the files are put back as they are when nothing can be said about them. A lock that a filesystem keeps elsewhere
		 *	(NFS, some union and network filesystems) is not on that list either
		 *
		 *	The answer and the copy are not atomic: a process can take its lock a moment after the list was read, and one that held it can be gone
		 *	by the time the files are copied. What this takes away is the window of the whole restore - a process that uses the file at the
		 *	time of the check is seen, and one that comes later is the window that is left
		 *
		 *	An archive of another project ($foreign) is not believed about what it hands over: nothing is left out for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *	@param		mixed			$copies				The 'copies' of the stamp of the archive: archive names, as they were handed over
		 *	@param		bool			$foreign			Whether the archive is not this project's own
		 *	@param		array|null	$listed				handedOver() of the staging as the archive made it, where callbacks have run since: the names of it that are still staged are asked about
		 *
		 *	@return 	array										The archive names, eg. 'data/crm.sqlite', of the files that are in use
		 */
		public static function inUse( array &$appData, string $staging, mixed $copies, bool $foreign = false, ?array $listed = null ): array {

			if( $foreign === true )
				return [];

			$root		= rtrim( $staging, DIRECTORY_SEPARATOR );
			$taken	= array_keys( array_filter( $listed ?? self::_handedOver( $appData, $root, $copies ) ) );
			$taken	= array_values( array_filter( $taken, static fn( string $name ): bool => is_file( $root. '/'. $name ) === true ) );

			if( $taken === [] )
				return [];

			$list = @file_get_contents( self::$locksFile ?? self::LOCKS );

			if( $list === false )
				return [];

			$data 	= \Nino\Filesystem::path( $appData, '/data' );
			$locked = [];

			foreach( $list === '' ? [] : explode( "\n", $list ) as $line )
				if( preg_match( '/^\d+:\s+(?:->\s+)?POSIX\s.*?\s([0-9a-f]+):([0-9a-f]+):(\d+)\s/', $line, $match ) === 1 )
					$locked[ hexdec( $match[1] ). ':'. hexdec( $match[2] ). ':'. $match[3] ] = true;

			if( $locked === [] )
				return [];

			$busy = [];

			foreach( $taken as $name ) {

				clearstatcache( true, $data. '/'. substr( $name, 5 ) );
				$stat = @stat( $data. '/'. substr( $name, 5 ) );

				if( $stat === false )
					continue;

				if( isset( $locked[ self::_lockKey( $stat ) ] ) === true )
					$busy[] = $name;
			}

			return $busy;
		}

		// The device and the inode of a file as the list of the locks names them (see inUse() and held()): st_dev is major and minor, spread over the bits as the kernel writes them
		private static function _lockKey( array $stat ): string {

			$major = ( ( $stat['dev'] >> 8 ) & 0xfff ) | ( ( $stat['dev'] >> 32 ) & ~0xfff );
			$minor = ( $stat['dev'] & 0xff ) | ( ( $stat['dev'] >> 12 ) & ~0xff );

			return $major. ':'. $minor. ':'. $stat['ino'];
		}

		/**
		 *	Take the files an open database keeps beside itself out of the project, for the files of an archive that were handed over by a hook: the
		 *	archive lists their names in its stamp, and a file that is restored is a whole database, so a -wal, -shm or -journal of the live one beside it
		 *	would be applied to it. The list is the archive's word and a stamp can be made by anybody who can make an archive, so it is believed as far
		 *	as the project can check it (see _handedOver()), and a name is taken only where no manifest names the companion and the archive does not
		 *	carry it. A file that a feature took over in its callback, or that inUse() named and the restore took out of the staging, is not in
		 *	the staging, so nothing is taken away for it: the files beside it stay for the process that uses them. For an archive of another project ($foreign: it was opened
		 *	with a key that was typed) nothing is taken away at all; $left says beside how many files such companions were left standing, which the answer tells the operator
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *	@param		mixed			$copies				The 'copies' of the stamp of the archive: archive names, as they were handed over
		 *	@param		bool			$foreign			Whether the archive is not this project's own
		 *	@param		int				&$left				(reference) Set: how many of the listed files have companions in the project that were not taken away
		 *	@param		array|null	$listed				handedOver() of the staging as the archive made it, where callbacks have run since: the guard on the number of files is the archive's, and what a callback took out of the staging does not lower it
		 *
		 *	@return 	bool										false where a file that had to go could not be removed
		 */
		public static function dropCompanions( array &$appData, string $staging, mixed $copies, bool $foreign = false, int &$left = 0, ?array $listed = null ): bool {

			$left = 0;
			$root = rtrim( $staging, DIRECTORY_SEPARATOR );

			$named	= self::_named( $appData );
			$data 	= \Nino\Filesystem::path( $appData, '/data' );
			$whole	= true;

			foreach( $listed ?? self::_handedOver( $appData, $root, $copies ) as $name => $mine ) {

				if( is_file( $root. '/'. $name ) === false )
					continue;

				$standing = false;

				foreach( self::COMPANIONS as $suffix ) {

					$live = $data. '/'. substr( $name, 5 ). $suffix;

					if( isset( $named[$name. $suffix] ) === true || file_exists( $root. '/'. $name. $suffix ) === true || ( is_file( $live ) === false && is_link( $live ) === false ) )
						continue;

					if( $foreign === true || $mine === false ) {
						$standing = true;
						continue;
					}

					if( @unlink( $live ) === false )
						$whole = false;
				}

				if( $standing === true && $foreign === true )
					$left++;
			}

			return $whole;
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
		 *	The name a kernel writer locks a file of an archive under, for a restore that puts it back - for the files a writer reads, changes and writes back:
		 *	\Nino\Filesystem::mutate() and its like lock the virtual path of the file, which is the archive's name with a slash in front (config.php, the texts, the
		 *	elements, the templates and the data files that are php). The blocks and the themes are written as packages, a folder at a time, under the one lock of
		 *	the features (see \Nino\Packages::place()). The assets, the pictures and the fonts are only ever written whole, and a file of the data that is no php is
		 *	a database or the like that its feature opens itself: a lock protects none of them, and they have none here
		 *
		 *	@param		string		$name					The name in the archive, eg. 'text/de_DE.php'
		 *
		 *	@return 	string|null								null for a file that is put back without a lock
		 */
		public static function lockPath( string $name ): ?string {

			if( $name === 'config.php' )
				return '/config.php';

			$entry = explode( '/', $name )[0];

			if( in_array( $entry, [ 'blocks', 'themes' ], true ) === true )
				return \Nino\Features::LOCK_PATH;

			if( in_array( $entry, [ 'text', 'elements', 'templates' ], true ) === true )
				return str_contains( $name, '/' ) === true ? '/'. $name : null;

			return $entry === 'data' && str_contains( $name, '/' ) === true && str_ends_with( $name, '.php' ) === true ? '/'. $name : null;
		}

		/**
		 *	Whether a file below data/ is put back beside the file and by rename, as the files of the kernel's writers are: a php file. Every other file of the data
		 *	is a database or the like, which its feature opens itself: a process that has it open keeps the file it opened, so it is written over in place, as it
		 *	always was
		 *
		 *	@param		string		$name					The name in the archive, eg. 'data/forms.2026-11.php'
		 *
		 *	@return 	bool
		 */
		public static function renamed( string $name ): bool {
			return str_starts_with( $name, 'data/' ) === false || str_ends_with( $name, '.php' ) === true;
		}

		/**
		 *	The time the waiting for a lock is measured by, in seconds: the monotonic clock, which no change of the time of day moves
		 *
		 *	@return 	float
		 */
		public static function clock(): float {
			return hrtime( true ) / 1e9;
		}

		/**
		 *	How many locks a restore holds at once: every lock is an open file, and the limit of open files of the process is what ends them. A quarter of it
		 *	(the unpacking, the copies, the archive and what a callback opens need the rest), at most 4096 and at least 32 - the limit of 1024 that most
		 *	servers have gives 256, which is more than the locked files of a project are
		 *
		 *	@return 	int
		 */
		public static function locksHeld(): int {

			if( self::$locksHeld !== null )
				return self::$locksHeld;

			$limit = function_exists( 'posix_getrlimit' ) === true ? ( posix_getrlimit()['soft openfiles'] ?? 1024 ) : 1024;

			return max( 32, min( 4096, intdiv( is_int( $limit ) === true ? $limit : 1024, 4 ) ) );
		}

		/**
		 *	The locks a restore takes before it puts the files of an archive back (see lockPath()), in the one order it takes them in: the lock of the features,
		 *	where the archive holds blocks or themes, then config.php, then the texts, the elements, the templates and the data, the files of each in the order of
		 *	their names - which is the order Pages::put() takes its locks in. The order keeps the restore from waiting; it is not what keeps it from a deadlock
		 *	(see lockFiles())
		 *
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *
		 *	@return 	array										The virtual paths, each once
		 */
		public static function lockPaths( string $staging ): array {

			$root		= rtrim( $staging, DIRECTORY_SEPARATOR );
			$groups	= [ 'features' => [], 'config' => [], 'text' => [], 'elements' => [], 'templates' => [], 'data' => [] ];

			if( is_file( $root. '/config.php' ) === true )
				$groups['config'][] = '/config.php';

			foreach( array_keys( $groups ) as $entry ) {

				if( in_array( $entry, [ 'features', 'config' ], true ) === true || is_dir( $root. '/'. $entry ) === false )
					continue;

				foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root. '/'. $entry, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file ) {

					$name = $entry. str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $root. '/'. $entry ) ) );

					if( self::lockPath( $name ) !== null )
						$groups[$entry][] = '/'. $name;
				}

				sort( $groups[$entry] );
			}

			foreach( [ 'blocks', 'themes' ] as $entry )
				if( is_dir( $root. '/'. $entry ) === true )
					$groups['features'] = [ \Nino\Features::LOCK_PATH ];

			return array_values( array_unique( array_merge( ...array_values( $groups ) ) ) );
		}

		/**
		 *	Take the locks of the writers of some files, all of them, in the order of the list: each without waiting for it, polling in slices of 5 ms (flock()
		 *	has no time limit of its own) until every one is held or the time is up (\Nino\Backup::$lockWait in all, by the monotonic clock)
		 *
		 *	A process that holds a lock while it waits for another is what a lock held here must not be waited on by: where one lock has not come within a
		 *	fifth of the time and others are held, those are let go, a moment is left to whoever waits for them, and the round starts over. So this never waits
		 *	with a lock in its hand for longer than that, whatever order a writer takes its locks in, and nothing is held when it answers with a path
		 *
		 *	A lock that cannot be opened at all (see \Nino\Filesystem::lockUsable()) is none that anybody holds, and is left out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$paths				Virtual paths, see lockPath(), each once
		 *	@param		float|null	$until				The time (see clock()) the waiting ends at; null for \Nino\Backup::$lockWait from the call. One attempt is made whatever it is
		 *
		 *	@return 	true|string								true where every lock is held; else the virtual path of the one that another process held until the time was up
		 */
		public static function lockFiles( array &$appData, array $paths, ?float $until = null ): true|string {

			$until	??= self::clock() + self::$lockWait;
			$stall	= max( 0.01, self::$lockWait / 5 );

			while( true ) {

				$took = [];

				foreach( $paths as $path ) {

					$since = self::clock();

					while( \Nino\Filesystem::lockFile( $appData, (string) $path, false ) === false ) {

						// What cannot be opened is held by nobody
						if( \Nino\Filesystem::lockUsable( $appData, (string) $path ) === false )
							continue 2;

						$at = self::clock();

						if( $at >= $until ) {
							self::unlockFiles( $appData, $took );
							return (string) $path;
						}

						if( $took !== [] && $at - $since >= $stall ) {
							self::unlockFiles( $appData, $took );
							usleep( random_int( 1000, 20000 ) );
							continue 3;
						}

						usleep( 5000 );
					}

					$took[] = $path;
				}

				return true;
			}
		}

		/**
		 *	Let go of the locks lockFiles() took
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$paths				Virtual paths
		 *
		 *	@return 	void
		 */
		public static function unlockFiles( array &$appData, array $paths ): void {

			foreach( $paths as $path )
				\Nino\Filesystem::unlockFile( $appData, (string) $path );
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

			// ...and the folder of the texts of the mails of the owner, which a backup never carries and an archive somebody put together may
			$bodies = rtrim( $staging, DIRECTORY_SEPARATOR ). '/data/'. basename( \Nino\MailQueue::BODIES );

			if( is_dir( $bodies ) === true && is_link( $bodies ) === false )
				\Nino\Filesystem::removeDir( $bodies );
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
		public static function stamp( array &$appData, array $notes = [] ): string {

			$features = [];

			foreach( \Nino\Features::all( $appData ) as $key => $feature )
				if( $feature['active'] === true )
					$features[(string) $key] = (string) $feature['version'];

			// 'copies': the files a feature handed over (see stage()), the files whose companions a restore takes away; 'raw': the features whose files are in the archive as they lay;
			// 'links': the names left out as links; 'left', only where there are any: the names of the files that were gone or that are another request's temporary files, left out
			// (see pack()); 'held', only where there are any: the names of the files this request held a lock on, which the archive lacks (see held()). A restore reads 'nino' and
			// 'copies' and nothing else of it, so a note that an older kernel does not know is ignored by it, and the stamp of a project that left nothing out is the one it always was
			$left = array_values( (array) ( $notes['left'] ?? [] ) );
			$held = array_values( (array) ( $notes['held'] ?? [] ) );

			return '<?php return '. var_export( [ 'nino' => \Nino\VERSION, 'features' => $features, 'copies' => array_values( (array) ( $notes['copies'] ?? [] ) ), 'raw' => array_map( 'strval', array_keys( (array) ( $notes['raw'] ?? [] ) ) ), 'links' => array_values( (array) ( $notes['links'] ?? [] ) ) ] + ( $left === [] ? [] : [ 'left' => $left ] ) + ( $held === [] ? [] : [ 'held' => $held ] ), true ). ';';
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
		 *	Every file is read through one descriptor that the kernel opens and checks itself (see _open()), and php is handed that
		 *	descriptor and opens nothing by a path: what was looked at is what is read. A path that is a folder, a link or a named pipe by
		 *	the time of the open is never read - a folder would be an empty entry, a link would pack what lies behind it, a pipe would end
		 *	the request in a wait - and a file that a writer renamed a new file over is read whole, old or new. A file that is not
		 *	the regular file the manifest names ends the packing with SOURCE_FAILED and its name, as does one whose size or time of change
		 *	differs after the read from what it was at the open, since an entry of two moments is a torn one - except every name below data/,
		 *	which is packed as it lies (a database that is written to cannot be read in a moment), and the companions and temporary files
		 *	named below. The time of change is whole seconds: a write in place that keeps the size within the second of the open is not seen
		 *
		 *	Files come and go while a project is written to. What may vanish between the listing and the packing is left out and the stamp says
		 *	so ('left', and one notice that names the files): the companion of a database and the temporary file of a writer of the kernel
		 *	(see leavable()); a temporary file that is there is packed like any file. Any
		 *	other file that is gone, is no file any more or cannot be read ends the packing with SOURCE_FAILED and its name. A file whose
		 *	companion is listed is read after the companion: a log of writes (-wal) that is gone by then was checkpointed into the main file, and
		 *	the main file read after it holds what the log held. A raw copy of a database that is written to is no consistent snapshot all the same:
		 *	pages the file changes while it is read are of two moments, which is why a feature that writes one hands over a copy of it (see stage())
		 *
		 *	A file on which this request holds a lock of its own is not opened at all, and not packed (see held()): closing any descriptor of a
		 *	file ends every lock the process holds on it, so reading it would take the lock of a database in a transaction away from the
		 *	connection that holds it. It is left out with the files beside it, the stamp names them ('held') and so does one warning of the log,
		 *	and the names are handed back in $held for the caller to say so to the person who asked. The copies a feature handed over are packed
		 *	whatever they hold. Where the system does not list the locks (see held()) nothing is known of them and every file is opened
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$manifest			Absolute path -> archive name, see manifest()
		 *	@param		string		$tar					Where the tar is made; the archive is $tar.'.gz'
		 *	@param		array 		$notes				What stage() noted, which the stamp carries
		 *	@param		array 		&$held				(reference) Set: the archive names of the files that were left out because this request holds a lock on them
		 *
		 *	@return 	void
		 *
		 *	@throws		\RuntimeException				SOURCE_FAILED if a file of the manifest cannot be read or changed while it was packed, WRITE_FAILED if the archive cannot be written
		 */
		public static function pack( array &$appData, array $manifest, string $tar, array $notes = [], array &$held = [] ): void {

			$kinds		= self::leavable( $appData, $manifest, (array) ( $notes['companions'] ?? [] ) );
			$entries	= self::_ordered( $manifest );
			$left			= [];

			// What this request holds a lock on is never opened, with what lies beside it
			$held = self::held( $entries, (array) ( $notes['copies'] ?? [] ) );

			foreach( $held as $name )
				unset( $entries[$name] );

			// What is gone before the first byte is read: left out, or the end of the run for a file that is none of those. Each file is looked at once and the
			// answer decides; the open that follows is the second look, and what it finds is told apart from what this one found (see _open())
			foreach( $entries as $name => $absolute ) {

				if( self::_readable( $absolute ) === true ) {

					if( self::$packing !== null )
						( self::$packing )( 'looked', 0, (string) $name );

					continue;
				}

				if( isset( $kinds[$name] ) === false )
					throw self::sourceFailure( $name );

				$left[] = $name;
				unset( $entries[$name] );
			}

			$gone		= [];
			$phar		= null;

			// Everything PharData says of a file that cannot be written - a disk that is full - is one sentence, and no path. It says it as a
			// notice first, and the handler of the kernel ends the request at a notice: so the step runs with a handler of its own
			// (see raising()), which makes it an exception that is caught here
			try {

				if( self::$packing !== null )
					( self::$packing )( 'read', 1 );

				self::raising( static function() use ( $tar, $entries, $kinds, &$gone, &$phar ): void {

					$phar = new \PharData( $tar );

					$phar->buildFromIterator( self::_handles( $entries, $kinds, $gone ) );
				} );

				$left = array_merge( $left, $gone );

				// Made after the files, which says what the packing left out, and outside the step, which turns every notice into an exception: what it reads is no part of the writing
				$stamp = self::stamp( $appData, [ 'left' => $left, 'held' => $held ] + $notes );

				self::raising( static function() use ( &$phar, $stamp ): void {

					$phar->addFromString( 'nino.php', $stamp );

					// Written out to the tar before it is touched below, and before the handler of the step is gone
					$phar = null;
				} );
			}
			catch( \PharException | \UnexpectedValueException | \BadMethodCallException | \ErrorException $failed ) {

				// What php says here is a write that failed: no file is opened by php, and a file that cannot be read ends the run in _handles()
				$phar = null;
				@unlink( $tar );

				if( self::$packing !== null )
					( self::$packing )( 'failed', 1 );

				throw new \RuntimeException( 'the backup could not be written: there was no room left, or a file could not be written', self::WRITE_FAILED );
			}
			catch( \RuntimeException $source ) {

				// SOURCE_FAILED out of _handles(): what was written of the tar is of the entries that were
				$phar = null;
				@unlink( $tar );

				throw $source;
			}

			self::_modes( $tar );
			self::_gzip( $tar, $tar. '.gz' );

			@unlink( $tar );

			if( $left !== [] )
				trigger_error( 'Backup: '. count( $left ). ' file(s) were gone or another request\'s temporary files and are left out of the archive: '. self::nameList( $left ), E_USER_NOTICE );

			if( $held !== [] )
				trigger_error( 'Backup: '. count( $held ). ' file(s) are held open by this request - a database in a transaction - and are left out of the archive, which lacks that state: '. self::nameList( $held ), E_USER_WARNING );
		}

		/**
		 *	Names for a sentence of the log or of an answer: printable ascii, each cut, the first ten
		 *
		 *	@param		array 		$names				Archive names
		 *
		 *	@return 	string									The names, comma separated, '...' behind them where there are more
		 */
		public static function nameList( array $names ): string {
			return implode( ', ', array_map( static fn( mixed $name ): string => substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', (string) $name ), 0, 80 ), array_slice( array_values( $names ), 0, 10 ) ) ). ( count( $names ) > 10 ? ', ...' : '' );
		}

		/**
		 *	The archive names of the entries whose files this process holds a lock on, with the files beside them: a file that is opened and closed by
		 *	this very process loses the locks the process holds on it, whichever descriptor opened it (a transaction of a database that the request
		 *	holds is such a lock), so such a file is never opened to be packed. A database in write-ahead mode holds locks on its -shm as well, so the
		 *	companions (-wal, -shm, -journal) of a held file are held with it and the main file of a held companion with that: a log of writes is no
		 *	use without its main file, and is worse than none beside an older one
		 *
		 *	How it is told: the list of the locks the system keeps ('/proc/locks', or the file a test names in $locksFile), read once, and the
		 *	lines of kind POSIX that carry the pid of this process as the system names it (the process the link '/proc/self' points to, getmypid() where the system says none), by device and inode. The locks the kernel takes itself are flock() and listed as FLOCK,
		 *	and a lock of the kind OFD is not ended by another descriptor's close: neither is held. The copies a feature handed over ($copies, archive
		 *	names) are not held whatever they hold, they are packed. Where the list cannot be read (a system that is not Linux, a restricted one) or holds no lock of this
		 *	process, nothing is held and no file is looked at
		 *
		 *	@param		array 		$entries			Archive name -> absolute path, see _ordered()
		 *	@param		array 		$copies				The archive names of the copies the features handed over
		 *
		 *	@return 	array										The archive names that are held, in the order of the entries
		 */
		public static function held( array $entries, array $copies = [] ): array {

			$list = @file_get_contents( self::$locksFile ?? self::LOCKS );

			if( $list === false || $list === '' )
				return [];

			$pid		= (int) basename( (string) @readlink( self::$selfLink ?? self::SELF ) ) ?: getmypid();
			$locked = [];

			foreach( explode( "\n", $list ) as $line )
				if( preg_match( '/^\d+:\s+POSIX\s+\S+\s+\S+\s+(\d+)\s+([0-9a-f]+):([0-9a-f]+):(\d+)\s/', $line, $match ) === 1 && (int) $match[1] === $pid )
					$locked[ hexdec( $match[2] ). ':'. hexdec( $match[3] ). ':'. $match[4] ] = true;

			if( $locked === [] )
				return [];

			$held = [];

			foreach( $entries as $name => $absolute ) {

				clearstatcache( true, $absolute );
				$stat = @stat( $absolute );

				if( $stat !== false && isset( $locked[ self::_lockKey( $stat ) ] ) === true )
					$held[(string) $name] = true;
			}

			foreach( array_keys( $held ) as $name ) {

				$name = (string) $name;
				$base = self::_companion( $name ) ?? $name;

				foreach( [ '', ...self::COMPANIONS ] as $suffix )
					if( isset( $entries[$base. $suffix] ) === true )
						$held[$base. $suffix] = true;
			}

			foreach( $copies as $name )
				unset( $held[(string) $name] );

			return array_values( array_filter( array_map( 'strval', array_keys( $entries ) ), static fn( string $name ): bool => isset( $held[$name] ) === true ) );
		}

		/**
		 *	Open a file of the manifest for reading, the one descriptor the packing reads it through. The sequence is what makes it the file that
		 *	was meant and no other, and nothing in it waits:
		 *
		 *	1. lstat() must say a regular file: a folder, a link, a pipe, a device or a socket is never opened
		 *	2. fopen() with 'n', which opens without waiting: a named pipe that was put there since step 1 has no writer, and a plain open would
		 *	   wait for one for as long as the request lives
		 *	3. fstat() of the descriptor must say a regular file with the device and the inode of step 1. Where it says another inode, the path is
		 *	   looked at once more: a writer that renamed a new file over the path made the file the descriptor reads the one the path names at that moment,
		 *	   and that is a file like any other; where the path names another file than the descriptor reads, or none, the open was of something else
		 *	4. the stream waits as a stream does
		 *
		 *	The inode of the look that came before (see pack()) is not compared: a writer of the kernel renames a new file over the path, which is legitimate,
		 *	and file systems reuse inode numbers (1,000 rewrites of one file on ext4 gave 3 different numbers)
		 *
		 *	A parent folder that was swapped for a link is followed, as the open of any path follows it: both the lstat and the open see the file behind it and agree, and it needs
		 *	write access to the tree
		 *
		 *	@param		string		$name					The archive name, which the test hook is told
		 *	@param		string		$absolute			Absolute path of the file
		 *
		 *	@return 	resource|false|null					The descriptor; null where the file is gone or cannot be read; false where it is no regular file, is a link, or is another file than the path names
		 */
		private static function _open( string $name, string $absolute ): mixed {

			clearstatcache( true, $absolute );
			$look = @lstat( $absolute );

			if( $look === false )
				return null;

			if( ( $look['mode'] & 0170000 ) !== 0100000 )
				return false;

			if( self::$packing !== null )
				( self::$packing )( 'opening', 0, $name );

			$handle = @fopen( $absolute, 'rbn' );

			if( $handle === false )
				return null;

			$open = fstat( $handle );

			if( ( $open['mode'] & 0170000 ) !== 0100000 ) {
				fclose( $handle );
				return false;
			}

			if( $open['ino'] !== $look['ino'] || $open['dev'] !== $look['dev'] ) {

				clearstatcache( true, $absolute );
				$second = @lstat( $absolute );

				if( $second === false || ( $second['mode'] & 0170000 ) !== 0100000 || $second['ino'] !== $open['ino'] || $second['dev'] !== $open['dev'] ) {
					fclose( $handle );
					return false;
				}
			}

			stream_set_blocking( $handle, true );

			return $handle;
		}

		/**
		 *	The entries as php reads them: archive name => descriptor, each opened by _open() when php asks for it and closed when php has read it, so
		 *	that one descriptor of the packing is open at a time. A file that is gone or cannot be read at the open is left out where it may vanish
		 *	(leavable(), named in $gone) and ends the run where it is a real one; a file that is no regular file, or another one than the path names,
		 *	ends the run as one that changed. After php has read a file the bytes it took, the size and the time of change are compared with those at the open: a file that
		 *	was written to in place meanwhile is a torn entry, which ends the run as one that changed - except every name below data/, the kernel's own data/*.php included
		 *	(those are written by a rename and cannot be torn), which is packed as it lies, as the manual of the features promises (a raw safety snapshot of a database
		 *	that is written to would never be made, and a restore that has to be made would not begin), and a companion or a temporary file, which are left out where they
		 *	are gone and are packed as they are. The time of change is whole seconds, so a write in place that keeps the size within the second of the open is not seen
		 *
		 *	@param		array 		$entries			Archive name -> absolute path
		 *	@param		array 		$kinds				leavable() of the manifest
		 *	@param		array 		&$gone				(reference) The archive names that were left out at the open
		 *
		 *	@return 	\Generator
		 *
		 *	@throws		\RuntimeException				SOURCE_FAILED, which php lets out of the step as it is
		 */
		private static function _handles( array $entries, array $kinds, array &$gone ): \Generator {

			foreach( $entries as $name => $absolute ) {

				$name		= (string) $name;
				$handle = self::_open( $name, $absolute );

				if( is_resource( $handle ) === false ) {

					if( isset( $kinds[$name] ) === true ) {
						$gone[] = $name;
						continue;
					}

					throw self::sourceFailure( $name, $handle === false );
				}

				$opened = fstat( $handle );
				$size		= (int) $opened['size'];
				$time		= (int) $opened['mtime'];

				try {

					if( self::$packing !== null )
						( self::$packing )( 'opened', 0, $name );

					yield $name => $handle;

					$read		= ftell( $handle );
					$after	= fstat( $handle );
				}
				finally {

					if( is_resource( $handle ) === true )
						fclose( $handle );
				}

				// Every name below data/ is packed as it lies, whatever its size did meanwhile: what a feature keeps there is written in place, and the manual says it is packed as it
				// lies; the kernel's own data/*.php are written by a rename and cannot be torn, so the exemption costs nothing there. The time of change, not the time of the status:
				// the unlink of a rename writer changes the status of the old file, which is read whole
				if( ( $read !== $size || (int) $after['size'] !== $size || (int) $after['mtime'] !== $time ) && isset( $kinds[$name] ) === false && str_starts_with( $name, 'data/' ) === false )
					throw self::sourceFailure( $name, true );
			}
		}

		/**
		 *	The names of a manifest with their files, in the order they are packed: the companion of a database - its log of writes and the files beside it -
		 *	before the file it belongs to, where both are there, so that a log that goes while the packing is under way has been found gone before
		 *	the main file is read. Every other name keeps its place
		 *
		 *	@param		array 		$manifest			Absolute path -> archive name
		 *
		 *	@return 	array										Archive name -> absolute path
		 */
		private static function _ordered( array $manifest ): array {

			$byName = [];
			foreach( $manifest as $absolute => $name )
				$byName[$name] = $absolute;

			$entries = [];

			foreach( $byName as $name => $absolute ) {

				$name = (string) $name;

				// Put where its main file stands
				if( ( $base = self::_companion( $name ) ) !== null && isset( $byName[$base] ) === true )
					continue;

				foreach( self::COMPANIONS as $suffix )
					if( isset( $byName[$name. $suffix] ) === true )
						$entries[$name. $suffix] = $byName[$name. $suffix];

				$entries[$name] = $absolute;
			}

			return $entries;
		}

		/**
		 *	Whether a file of the manifest is one that can be read: a file, not a folder, and readable by this process
		 *
		 *	@param		string		$absolute
		 *
		 *	@return 	bool
		 */
		private static function _readable( string $absolute ): bool {

			clearstatcache( true, $absolute );

			return is_file( $absolute ) === true && @is_readable( $absolute ) === true;
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
