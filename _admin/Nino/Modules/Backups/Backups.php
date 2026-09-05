<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Backups	The daily encrypted snapshot - the module's runtime half,
 *											taken on the first authenticated request of the day
 *											(see \Nino\Admin\Admin::guard()). Restoring one is the
 *											panel's half, beside this file in Admin/Admin.php.
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Backups						Encrypted daily snapshot of everything the admin panel can
	 *											write to at runtime - triggered once per authenticated
	 *											admin request per day (see \Nino\Admin\Admin::guard()), not a cron
	 *											job, so it needs no server-level scheduling at all. Lives
	 *											here rather than in the kernel (_nino/Nino.php) since
	 *											it's a workbench operational concern, not something
	 *											every Nino deployment needs - a site with no workbench has
	 *											nothing to back up in the first place.
	 *
	 *											Three independent layers, none of which need any
	 *											webserver configuration:
	 *											- stored under a one-time random directory name, never
	 *											  linked anywhere, so it can't be crawled/guessed
	 *											- .php extension with a self-terminating stub - a direct
	 *											  request hits exit() before the real (encrypted) data
	 *											  ever gets output
	 *											- AES-256-GCM encrypted payload (12-byte iv + 16-byte tag
	 *											  + ciphertext), so raw filesystem access to the file
	 *											  alone still isn't enough without the key
	 *
	 *											The encrypted payload is base64 and stays inside a
	 *											single quoted string literal in the same PHP block as
	 *											the exit() - never appended as raw bytes after a closing
	 *											"?>". First attempt did that and broke in practice: PHP
	 *											tokenizes/compiles the *entire* file before running any
	 *											of it, and "?>" turns the tokenizer back into tag-
	 *											scanning mode for the rest of the file. A large enough
	 *											blob of encrypted (ie. uniformly random) bytes will
	 *											eventually contain a literal "<?=" by chance - confirmed
	 *											this empirically, it broke on the very first real backup
	 *											- which reopens a PHP block partway through random noise
	 *											and fails to compile, so exit() never even runs and the
	 *											request 500s instead of 403ing. Keeping everything as one
	 *											valid, never-closed PHP block/string sidesteps this
	 *											entirely - there's no point where the tokenizer re-scans
	 *											for tags inside a string literal.
	 *
	 *											Restore lives in _admin, not here - see _admin/Admin.php. This
	 *											class only ever creates, never reads/decrypts, backups.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Backups {

		private const int RETENTION_DAYS = 14;
		private const string LOCK_PATH = '/_admin/backup-daily';

		// Under the private directory, not in this tool's own folder: archives
		// are project data, and a tool folder holding them cannot be replaced
		// independently on an update.
		private const string BACKUPS_DIR = \Nino\Filesystem::CONTENT_DIR. '/.backups';

		// The archive key's own copy, kept outside config.php on purpose: a
		// restore rewrites config.php, so the key that decrypts the archive
		// being restored cannot only live in it. Next to the /_admin
		// credential rather than next to the archives - it is a credential,
		// they are data
		public const string KEY_PATH = \Nino\Filesystem::CONTENT_DIR. '/.auth/backup-key.php';

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	The directory that holds backup archives.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Absolute paths, existing or not
		 */
		public static function dirs( array &$appData ): array {

			return [ \Nino\Filesystem::getContentPath( $appData ). substr( self::BACKUPS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) ) ];
		}

		/**
		 *	Where the archive key's own copy lives.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@return 	string									Absolute path
		 */
		public static function keyPath( array &$appData ): string {

			return \Nino\Filesystem::getContentPath( $appData ). substr( self::KEY_PATH, strlen( \Nino\Filesystem::CONTENT_DIR ) );
		}

		/**
		 *	Most recent backup date on file, if any - shared by
		 *	\Nino\Modules\Dashboard\Admin::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|null							"Y-m-d", or null if none exist yet
		 */
		public static function lastDate( array &$appData ): ?string {

			$dates = [];

			foreach( self::dirs( $appData ) as $dir )
				foreach( glob( $dir. '/*.php' ) ?: [] as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$dates[] = basename( $file, '.php' );

			if( count( $dates ) === 0 )
				return null;

			rsort( $dates );

			return $dates[0];
		}

		/**
		 *	Bootstrap the backup dir/key on first call, then create today's
		 *	backup if it doesn't exist yet and prune anything past retention.
		 *	Called from \Nino\Admin\Admin::guard() - so once per authenticated request,
		 *	not a cron job. Failures never interrupt the admin action that
		 *	triggered this (backup is a safety net, not something that should
		 *	itself become a new way to break the admin panel) - logged, not
		 *	thrown.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function maybeRun( array &$appData ): void {

			$locked = false;

			try {

				if( ( $appData['/nino/admin/backups'] ?? true ) === false )
					return;

				// The directory name does not exist until _bootstrap(), so it
				// cannot itself be the lock key. A stable, virtual path serializes
				// first-use key generation and the once-a-day existence check alike.
				$locked = \Nino\Filesystem::lockFile( $appData, self::LOCK_PATH );
				if( $locked === false )
					throw new \RuntimeException( 'daily backup could not be locked' );

				self::_bootstrap( $appData );

				\Nino\Filesystem::forceDir( $appData, self::BACKUPS_DIR );

				$dir 		= self::dirs( $appData )[0];
				$path 	= $dir. '/'. date( 'Y-m-d' ). '.php';

				if( is_file( $path ) === true )
					return;

				self::_create( $appData, $dir, $path );
				foreach( self::dirs( $appData ) as $backupDir )
					self::_prune( $backupDir );

				// Through the shell rather than at the Logs module directly: the
				// log is a module a delivery may drop, and this line sits inside
				// the catch below - reaching for a class that is not there would
				// turn a backup that just succeeded into "Backup failed", once
				// per day, forever (see \Nino\Admin\Admin::record())
				$user = \Nino\Auth::getCurrentUser( $appData );
				if( $user !== false )
					\Nino\Admin\Admin::record( $appData, $user['mail'], 'Backup created' );

			} catch( \Throwable $e ) {
				trigger_error( 'Backup failed: '. $e->getMessage() );
			} finally {
				if( $locked === true )
					\Nino\Filesystem::unlockFile( $appData, self::LOCK_PATH );
			}
		}

		/**
		 *	Generate the random backup directory name and encryption key on
		 *	first use - a project that never opens the workbench never gets these
		 *	config.php keys at all.
		 *
		 *	The key also gets an independent copy written into _admin/ (behind
		 *	the same .php exit-stub as a backup itself, since it's just as
		 *	sensitive) - _admin/Admin.php's Restore module reads *that* copy,
		 *	deliberately not this one, so restoring never depends on
		 *	config.php's data being intact. This copy is reconciled on every
		 *	call independent of whether dir/key already existed, since an
		 *	install that had Backup running before Restore/_admin's key file
		 *	existed would otherwise never get one (the dir/key generation
		 *	itself only ever runs once). Skipped if _admin/ isn't present
		 *	(already removed after initial setup, or never deployed) - fine,
		 *	restoring isn't possible without it either way.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _bootstrap( array &$appData ): void {

			$readConfig = false;
			$changed 	= false;
			// Generated before mutate() takes config.php's lock: random_bytes()
			// can throw, and Filesystem::mutate() deliberately expects a callback
			// that returns normally in order to release its lock.
			//
			// Only the key. The directory used to be generated alongside it,
			// as an unguessable name; the archives live under the content
			// directory now and need none (see dirs())
			$candidateKey = base64_encode( random_bytes( 32 ) );

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', function( mixed $content ) use ( &$appData, &$readConfig, &$changed, $candidateKey ): mixed {

				$readConfig = true;
				if( is_array( $content ) === false )
					return null;

				// Re-read under config.php's own lock. A second request may have
				// booted before the first generated this value; trusting its stale
				// in-memory appData here would generate a different key and overwrite
				// the first backup's only decryption key.
				if( is_string( $content['/nino/backup/key'] ?? null ) === false ) {
					$content['/nino/backup/key'] = $candidateKey;
					$changed = true;
				}

				$appData['/nino/backup/key'] = $content['/nino/backup/key'];

				return $changed === true ? $content : null;
			}, false );

			if( $readConfig === false )
				throw new \RuntimeException( 'config.php could not be locked' );
			if( isset( $appData['/nino/backup/key'] ) === false )
				throw new \RuntimeException( 'config.php could not be read' );
			if( $changed === true && $written === false )
				throw new \RuntimeException( 'backup configuration could not be written' );

			// Independent of the above: an install that already had a dir/key
			// before this copy existed (or whose copy was lost/deleted since)
			// never gets one otherwise, since the block above only ran once,
			// on first-ever bootstrap
			\Nino\Filesystem::forceDir( $appData, dirname( self::KEY_PATH ) );

			$keyPath 		= self::keyPath( $appData );
			$keyContent = self::STUB_PREFIX. $appData['/nino/backup/key']. self::STUB_SUFFIX;

			if( @file_get_contents( $keyPath ) !== $keyContent )
				self::_writeRawAtomic( $keyPath, $keyContent );
		}

		/**
		 *	Tar+gzip every file the admin panel can write to at runtime,
		 *	encrypt it, and write it out behind the .php exit-stub. Built via
		 *	PharData rather than shelling out to `tar`/`gzip` - pure PHP, and
		 *	unlike the executable Phar class, PharData isn't restricted by
		 *	the phar.readonly ini setting many hosts enable by default.
		 *
		 *	Reads each file's bytes itself and hands them to addFromString()
		 *	rather than pointing addFile() at the path - addFile() alone
		 *	archived a stale, already-out-of-date copy of a just-written file
		 *	(config.php, right after _bootstrap() writes it) even though a
		 *	plain file_get_contents() at that same point already saw the
		 *	current content. Whatever addFile()'s own internal caching is
		 *	keyed on, supplying the bytes directly sidesteps it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$path					Absolute path of today's backup file
		 *
		 *	@return 	void
		 */
		private static function _create( array &$appData, string $dir, string $path ): void {

			if( is_dir( $dir ) === false && @mkdir( $dir, 0755, true ) === false && is_dir( $dir ) === false )
				throw new \RuntimeException( 'backup directory could not be created' );

			$tmpBase = tempnam( sys_get_temp_dir(), 'ninobackup' );
			if( $tmpBase === false )
				throw new \RuntimeException( 'temporary backup file could not be created' );

			$tmpTar = $tmpBase. '.tar';
			@unlink( $tmpBase );

			try {
				$phar = new \PharData( $tmpTar );
				foreach( \Nino\Backup::manifest( $appData ) as $absolute => $archiveName ) {
					$bytes = @file_get_contents( $absolute );
					if( $bytes === false )
						throw new \RuntimeException( 'backup source could not be read: '. $archiveName );
					$phar->addFromString( $archiveName, $bytes );
				}
				$phar->compress( \Phar::GZ );
				unset( $phar );

				$gz = @file_get_contents( $tmpTar. '.gz' );
				if( $gz === false )
					throw new \RuntimeException( 'compressed backup could not be read' );

				$key = base64_decode( (string) $appData['/nino/backup/key'], true );
				if( is_string( $key ) === false || strlen( $key ) !== 32 )
					throw new \RuntimeException( 'backup encryption key is invalid' );

				$iv 		= random_bytes( 12 );
				$tag 		= '';
				$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
				if( $cipher === false )
					throw new \RuntimeException( 'backup encryption failed' );

				self::_writeRawAtomic( $path, self::STUB_PREFIX. base64_encode( $iv. $tag. $cipher ). self::STUB_SUFFIX );
			} finally {
				@unlink( $tmpBase );
				@unlink( $tmpTar );
				@unlink( $tmpTar. '.gz' );
			}
		}

		// Raw (non-serialized) counterpart to Filesystem's atomic writer. Backup
		// blobs and the restore-key stub are valid PHP source strings already;
		// passing either through putFileContent() would var_export them instead.
		private static function _writeRawAtomic( string $path, string $content ): void {

			$temp = $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );

			if( $handle === false )
				throw new \RuntimeException( 'temporary file could not be opened: '. basename( $path ) );

			$written = @fwrite( $handle, $content );
			$flushed = @fflush( $handle );
			@fclose( $handle );

			if( $written === false || $written !== strlen( $content ) || $flushed === false ) {
				@unlink( $temp );
				throw new \RuntimeException( 'file could not be written: '. basename( $path ) );
			}

			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				throw new \RuntimeException( 'file could not be replaced: '. basename( $path ) );
			}
		}

		/**
		 *	Delete dated backups older than RETENTION_DAYS. Only ever touches
		 *	files whose name is exactly a plain "Y-m-d.php" date - _admin's
		 *	Restore::_safetySnapshot() also writes into this same directory
		 *	(differently named, "pre-restore-<timestamp>.php") and must never
		 *	be swept up here
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir ): void {

			$cutoff = ( new \DateTime( '-'. self::RETENTION_DAYS. ' days' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( $dir, '', 'Y-m-d', '.php', $cutoff );
		}
	}
}
