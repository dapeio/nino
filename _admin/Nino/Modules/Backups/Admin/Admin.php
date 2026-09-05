<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Backups\Admin	Backups panel: restoring a daily snapshot
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Backups {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Backups						Disaster recovery: restore a daily backup.
	 *
	 *											Deliberately independent of config.php's own backup keys:
	 *											- the archives are found under private/.backups, never
	 *											  addressed through a config value
	 *											- the decryption key has its own independent copy outside
	 *											  config.php (private/.auth/backup-key.php), rewritten by
	 *											  \Nino\Modules\Backups::_bootstrap() whenever it goes missing
	 *											So this still works even if config.php's *data* (not
	 *											syntax) is what's broken - eg. a wrecked admin user
	 *											record. A genuine config.php syntax error is out of scope:
	 *											the recovery page boots through the same kernel bootstrap as the workbench
	 *											and can't survive that either - that's a manual recovery.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/backups/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'backups/list' 		=> [ self::class, 'apiList' ],
				'backups/restore' 	=> [ self::class, 'apiRestore' ],
			];
		}

		/**
		 *	Nav entry for this module, rendered into the dashboard's tab bar
		 *
		 *	@return 	array										[ uri, label ]
		 */
		public static function nav(): array {
			return [ 'backups', '/_admin/nav/backups', 10, 'system' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hard-drive-icon lucide-hard-drive"><path d="M10 16h.01"/><path d="M2.212 11.577a2 2 0 0 0-.212.896V18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5.527a2 2 0 0 0-.212-.896L18.55 5.11A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><path d="M21.946 12.013H2.054"/><path d="M6 16h.01"/></svg>';
		}
		
		public static function panes(): array {
			return [ 'backups-list' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	Find the private backup directory.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _backupDirs( array &$appData ): array {

			// Its own copy of the path rather than \Nino\Modules\Backups'
			// constant, which that class keeps private: this class exists to
			// work when nothing else does - Restore is what a broken
			// installation reaches for - so it depends on no other class being
			// loadable (see this class's docblock, and the standalone
			// reasoning every other helper in this file follows)
			$dir = \Nino\Filesystem::getContentPath( $appData ). '/.backups';
			return is_dir( $dir ) === true ? [ $dir ] : [];
		}

		/**
		 *	The directory one dated archive actually sits in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$date					"Y-m-d", already validated
		 *
		 *	@return 	string|false
		 */
		private static function _backupDir( array &$appData, string $date = '' ): string|false {

			$dirs = self::_backupDirs( $appData );

			if( $date !== '' )
				foreach( $dirs as $dir )
					if( is_file( $dir. '/'. $date. '.php' ) === true )
						return $dir;

			return $dirs[0] ?? false;
		}

		/**
		 *	Read the encryption key's independent copy under private/.auth/
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false						Raw (not base64) key bytes
		 */
		private static function _key( array &$appData ): string|false {

			$path = \Nino\Filesystem::getContentPath( $appData ). '/.auth/backup-key.php';
			if( is_file( $path ) === false )
				return false;

			$raw = file_get_contents( $path );
			return base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ) );
		}

		/**
		 *	Decrypt one backup/snapshot file's payload
		 *
		 *	@param		string		$path					Absolute path to the .php file
		 *	@param		string		$key					Raw (not base64) 32-byte AES key
		 *
		 *	@return 	string|false						Decrypted tar.gz bytes, or false if decryption failed
		 */
		private static function _decrypt( string $path, string $key ): string|false {

			$raw 			= file_get_contents( $path );
			$payload 	= base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ) );
			$iv 			= substr( $payload, 0, 12 );
			$tag 			= substr( $payload, 12, 16 );
			$cipher 	= substr( $payload, 28 );

			return openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		}

		/**
		 *	Available backup dates, most recent first - shared by
		 *	apiList() and \Nino\Modules\Dashboard\Admin::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ "Y-m-d", ... ]
		 */
		public static function dates( array &$appData ): array {

			$dates = [];

			// Every location, not just the current one: an archive written
			// before the directory moved is still a restorable archive
			foreach( self::_backupDirs( $appData ) as $dir )
				foreach( glob( $dir. '/*.php' ) ?: [] as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$dates[] = basename( $file, '.php' );

			$dates = array_values( array_unique( $dates ) );

			rsort( $dates );

			return $dates;
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'backups/restore'	=> 'Restore Backup '. ( $data['date'] ?? '' ),
				default						=> '',
			};
		}

		/**
		 *	List available backup dates, most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'dates' => self::dates( $appData ) ] );
		}

		/**
		 *	Restore one chosen backup date: snapshot the *current* state
		 *	first (see _safetySnapshot()), so a wrong choice is itself
		 *	undoable, then overwrite every file the backup contains
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRestore( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$date = (string) ( \Nino\Admin\Admin::postData()['date'] ?? '' );

			if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid date' );
				return;
			}

			$result = self::restore( $appData, $date );

			if( $result !== true ) {
				\Nino\Http::fail( $request, $result[0], $result[1] );
				return;
			}

			\Nino\Http::ok( $request, [ 'ok' => true, 'restoredDate' => $date ] );
		}

		/**
		 *	Restore one dated archive over the live project - the whole
		 *	operation, shared with recovery.php, which is what runs it when
		 *	nobody can log in any more. The caller has validated the date
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$date					"Y-m-d"
		 *
		 *	@return 	true|array								True, or [ http status, message ]
		 */
		public static function restore( array &$appData, string $date ): true|array {

			$dir = self::_backupDir( $appData, $date );
			$key = self::_key( $appData );

			if( $dir === false || $key === false ) {
				return [ 404, 'no backup available' ];
			}

			$path = $dir. '/'. $date. '.php';

			if( is_file( $path ) === false ) {
				return [ 404, 'unknown backup date' ];
			}

			$gz = self::_decrypt( $path, $key );

			if( $gz === false ) {
				return [ 500, 'decryption failed' ];
			}

			self::_safetySnapshot( $appData, $dir, $key );

			$configPath	= \Nino\Filesystem::getConfigPath( $appData );
			$tmpGz 			= tempnam( sys_get_temp_dir(), 'ninorestore' ). '.tar.gz';
			$staging		= sys_get_temp_dir(). '/ninorestore-'. bin2hex( random_bytes( 8 ) );

			file_put_contents( $tmpGz, $gz );
			mkdir( $staging, 0755, true );
			( new \PharData( $tmpGz ) )->extractTo( $staging, null, true );
			unlink( $tmpGz );

			// config.php belongs under configPath, not root - see
			// \Nino\Filesystem::getConfigPath()'s docblock. Extracting it straight
			// to root like every other file would restore it to the wrong place,
			// and under a hardened NINO_CONFIG_DIR setup would additionally leak
			// a stray copy back into the webroot - so it's staged first and moved
			// separately from the rest of the archive.
			$stagedConfig = $staging. '/config.php';

			if( is_file( $stagedConfig ) === true ) {
				file_put_contents( $configPath. '/config.php', file_get_contents( $stagedConfig ) );
				unlink( $stagedConfig );
			}

			// A module that keeps state a restore must not simply overwrite
			// rewrites its staged files here, before the copy below - the
			// Newsletter module merges its removal record this way (see its
			// callbackRestore()). Restore itself knows no module: whatever
			// registered on '/nino/admin/restore' gets the live data
			// directory (read only) and the extracted backup
			$restoreArgs = [ 'dataDir' => \Nino\Filesystem::path( $appData, '/data' ), 'staging' => $staging ];
			\Nino\Callbacks::doCallbacks( $appData, '/nino/admin/restore', $restoreArgs );

			// The archive's own layout is flat - text/, elements/, images/,
			// data/ - but those no longer share one destination on disk:
			// everything but images is private and resolves against the
			// private root (see \Nino\Filesystem::PRIVATE_DIRS). Copied one
			// top-level entry at a time rather than as a single tree, so each
			// lands wherever this project actually keeps it
			foreach( glob( $staging. '/*' ) ?: [] as $entry ) {

				$target = \Nino\Filesystem::path( $appData, '/'. basename( $entry ) );

				if( is_dir( $entry ) === true )
					\Nino\Filesystem::copyDir( $entry, $target );
				else
					@copy( $entry, $target );
			}

			\Nino\Filesystem::removeDir( $staging );

			// extractTo()/copyDir() write straight to disk, bypassing Filesystem's own
			// cache tracking entirely - drop it so any getFileContent() call
			// later in this same request (or a request landing in the same
			// wall-clock second - see AppData::writeContentData()'s docblock
			// for why that specifically matters) re-reads the restored files
			// instead of whatever was cached from before the restore
			unset( $appData['./nino/filesystem/cache'] );

			if( function_exists( 'opcache_reset' ) === true )
				opcache_reset();

			return true;
		}

		/**
		 *	Archive the *current* on-disk state before overwriting it, same
		 *	tar+gzip+encrypt shape as \Nino\Modules\Backups::_create() (duplicated, not
		 *	called into - see this class' own docblock; the file manifest
		 *	itself is shared via \Nino\Backup::manifest(), which carries
		 *	no such coupling). Named distinctly from a dated backup so it's
		 *	never mistaken for one and never collides with/gets pruned by
		 *	Backup's own retention sweep
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$key					Raw (not base64) 32-byte AES key
		 *
		 *	@return 	void
		 */
		private static function _safetySnapshot( array &$appData, string $dir, string $key ): void {

			$tmpTar = tempnam( sys_get_temp_dir(), 'ninosnapshot' ). '.tar';
			$phar 	= new \PharData( $tmpTar );

			foreach( \Nino\Backup::manifest( $appData ) as $absolute => $archiveName )
				$phar->addFromString( $archiveName, file_get_contents( $absolute ) );

			$phar->compress( \Phar::GZ );
			unset( $phar );
			unlink( $tmpTar );

			$gz = file_get_contents( $tmpTar. '.gz' );
			unlink( $tmpTar. '.gz' );

			$iv 		= random_bytes( 12 );
			$tag 		= '';
			$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			file_put_contents( $dir. '/pre-restore-'. date( 'Y-m-d-His' ). '.php', self::STUB_PREFIX. base64_encode( $iv. $tag. $cipher ). self::STUB_SUFFIX );
		}
	}
}
