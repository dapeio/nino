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
		// type/image, and the /data/ content a project actually accumulates
		// (newsletter subscribers, form submissions, the php error log
		// logs.<month>.php, and what an installed feature's manifest declares
		// it owns below /data/). Deliberately not developer code (_nino/,
		// templates, _admin/ itself, ...) - that's already versioned in git
		// and would just bloat every backup. Also deliberately not
		// auth-tries.php, ratelimit.php or mail-status.php - transient
		// throttling counters and the last failed mail, not data a restore
		// should bring back. The workbench's
		// activity log is not in an archive either: it is written to
		// private/.logs/<day>.php (see \Nino\Modules\Logs\Admin),
		// which is none of the four directories this walks.
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
			// (NINO_CONFIG_DIR), text/elements/data are private and images
			// are public, so the two halves need not live under the same
			// directory - see Filesystem::path() and PRIVATE_DIRS
			$text 			= \Nino\Filesystem::path( $appData, '/text' );
			$elements 	= \Nino\Filesystem::path( $appData, '/elements' );
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

			// Element images are deliberately nested (images/elements/type/...).
			// Walk the complete tree and preserve each relative archive path;
			// never follow symlinks out of the public image directory.
			if( is_dir( $images ) === true ) {
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $images, \FilesystemIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::LEAVES_ONLY,
					\RecursiveIteratorIterator::CATCH_GET_CHILD
				);

				foreach( $iterator as $file ) {
					$path = $file->getPathname();
					if( $file->isFile() === false || is_link( $path ) === true )
						continue;

					$relative = substr( $path, strlen( rtrim( $images, DIRECTORY_SEPARATOR ) ) + 1 );
					$files[$path] = 'images/'. str_replace( DIRECTORY_SEPARATOR, '/', $relative );
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
			// key is documented as "what a backup carries" (see docs/features.md),
			// and until this read it it was not: the two entries above are
			// hardcoded literals for the one feature that predates the catalogue,
			// and anything else - a directory a feature owns, a file a feature
			// nobody here knows about writes - was simply never in a backup.
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

	}
}
