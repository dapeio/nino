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
			ago; restored .locks plants lock files for requests that ended weeks ago.

			So the promise is kept where it is made rather than by what the list
			happens not to mention. A leading dot with it: '/data/.locks' is the
			only one today, and everything hidden under /data/ is this
			framework's own bookkeeping rather than a project's content	*/
		private const array NEVER = [ 'auth-tries.php', 'ratelimit.php', 'mail-status.php', 'catalogue.php' ];

		/**
		 *	Whether a path below /data/ is one a backup must not carry
		 *
		 *	@param		string		$relative			eg. 'stats/2026-09.php'
		 *
		 *	@return 	bool
		 */
		private static function _transient( string $relative ): bool {

			foreach( explode( '/', $relative ) as $segment )
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
		// throttling counters and the last failed mail, not data a restore
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
			// others a person wrote is not something a file says
			foreach( [ 'templates' => $templates, 'images' => $images, 'assets' => $assets ] as $archive => $root ) {

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

					$files[$path] = $archive. '/'. $relative;
				}
			}

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

		// The top-level entries of an archive: manifest() makes no others, and
		// nino.php is stamp()'s. A restore copies each one into the project, so
		// an archive that names another would plant a file next to index.php
		private const array ARCHIVE_DIRS = [ 'text', 'elements', 'templates', 'assets', 'images', 'data' ];
		private const array ARCHIVE_FILES = [ 'config.php', 'nino.php' ];

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

	}
}
