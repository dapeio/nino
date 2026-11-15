<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Backups\Admin	Backups panel: restoring a snapshot, handing an archive over as a file, taking one back
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Backups {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Backups						Disaster recovery: restore a daily backup, back up now, download an
	 *											archive and upload one again.
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
	 *											The panel opens an archive with the key of this project only,
	 *											which is the one in config.php and, as a copy, under
	 *											private/.auth/ - the copy follows config.php at every request
	 *											of the workbench (see \Nino\Modules\Backups::_bootstrap()).
	 *											The workbench neither shows nor exports the key: the setup
	 *											wizard shows it once, as the recovery key, and an archive that
	 *											is downloaded is of use with that key kept by the person who
	 *											downloaded it. An archive of another project is restored on
	 *											recovery.php, where its key is typed (see restore() and
	 *											restoreFile()) - never here: an account with full access that
	 *											could type a key could restore an archive of its own making,
	 *											and a restore puts files into the project that php runs.
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

		// How many safety snapshots a project keeps - see _pruneSnapshots()
		private const int SNAPSHOT_KEEP = 3;

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'backups/list' 		=> [ self::class, 'apiList' ],
				'backups/restore' 	=> [ self::class, 'apiRestore' ],
				'backups/now' 		=> [ self::class, 'apiNow' ],
				'backups/download' 	=> [ self::class, 'apiDownload' ],
				'backups/upload' 	=> [ self::class, 'apiUpload' ],
			];
		}

		/**
		 *	Nav entry for this module - a system panel, at the weight the
		 *	rail orders it by within that group
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'backups', '/_admin/nav/backups', 10, 'system' ];
		}

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'backup', 'restore', 'external' ];
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
		 *	The private backup directory, while there is one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false						Absolute path, or false while none exists
		 */
		private static function _backupDir( array &$appData ): string|false {

			// Its own copy of the path rather than \Nino\Modules\Backups'
			// constant, which that class keeps private: this class exists to
			// work when nothing else does - Restore is what a broken
			// installation reaches for - so it depends on nothing but the
			// kernel and the workbench shell, where the stub it reads is
			// defined (\Nino\Admin\Recovery) - the two a bare boot loads (see
			// this class's docblock, and the standalone reasoning every other
			// helper in this file follows)
			$dir = \Nino\Filesystem::getContentPath( $appData ). '/.backups';
			return is_dir( $dir ) === true ? $dir : false;
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
			return base64_decode( substr( $raw, strlen( \Nino\Admin\Recovery::STUB_PREFIX ), -strlen( \Nino\Admin\Recovery::STUB_SUFFIX ) ) );
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

			// Read and decoded piece by piece: the file is the base64 of the
			// payload, and as one string next to its decoding and its parts it
			// took more memory to open an archive than it took to make it. A
			// piece is a multiple of four characters, so it decodes on its own
			$handle = @fopen( $path, 'rb' );
			if( $handle === false )
				return false;

			$remaining 	= (int) @filesize( $path ) - strlen( \Nino\Admin\Recovery::STUB_PREFIX ) - strlen( \Nino\Admin\Recovery::STUB_SUFFIX );
			$header 		= '';
			$cipher 		= '';

			fseek( $handle, strlen( \Nino\Admin\Recovery::STUB_PREFIX ) );

			while( $remaining > 0 ) {

				$piece = fread( $handle, min( 786432, $remaining ) );
				if( $piece === false || $piece === '' )
					break;

				$remaining -= strlen( $piece );
				$bytes 			= (string) base64_decode( $piece );

				// iv and tag come first, 28 bytes, and are not part of the ciphertext
				if( strlen( $header ) < 28 ) {
					$take 		= 28 - strlen( $header );
					$header .= substr( $bytes, 0, $take );
					$bytes 		= substr( $bytes, $take );
				}

				$cipher .= $bytes;
			}

			fclose( $handle );

			if( strlen( $header ) !== 28 )
				return false;

			return openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $header, 0, 12 ), substr( $header, 12, 16 ) );
		}

		// What an archive's id may look like: a dated backup, one made on
		// request (dated and timed, marked "-manual", see
		// \Nino\Modules\Backups::now(); the mark is optional because the ones
		// made before it existed have none and are read all the same), or the
		// safety snapshot a restore writes before it overwrites anything.
		// The snapshot used to match nothing here - not in this list, not in
		// apiRestore(), not in recovery.php - so "a wrong pick can itself be
		// undone", which the docblock, the panel's confirm text and both
		// manuals all promise, was true of a file only ssh could reach
		public const string ID_PATTERN = '/^(?:\d{4}-\d{2}-\d{2}(?:-\d{6}(?:-manual)?)?|pre-restore-\d{4}-\d{2}-\d{2}-\d{6})$/D';

		// The name an uploaded archive comes in: its id, behind what the download
		// put before it (apiDownload()) or nothing, and the extension it keeps. A
		// snapshot is not uploaded: it is the way out of a restore on the project
		// that made it, and no host in front of an id makes it one
		private const string UPLOAD_NAME_PATTERN = '/^(?:backup-(?!.*pre-restore-)[a-z0-9][a-z0-9.-]*-)?(\d{4}-\d{2}-\d{2}(?:-\d{6}(?:-manual)?)?)\.php$/D';

		/**
		 *	Available backup dates, most recent first - shared by
		 *	apiList() and the recovery page (\Nino\Admin\Recovery::handlePost())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ "Y-m-d" | "Y-m-d-His-manual" | "Y-m-d-His" | "pre-restore-Y-m-d-His", ... ]
		 */
		public static function dates( array &$appData ): array {

			$dates 			= [];
			$snapshots	= [];

			$dir = self::_backupDir( $appData );

			foreach( $dir === false ? [] : ( glob( $dir. '/*.php' ) ?: [] ) as $file ) {

				$id = basename( $file, '.php' );

				if( preg_match( '/^\d{4}-\d{2}-\d{2}(?:-\d{6}(?:-manual)?)?$/', $id ) === 1 )
					$dates[] = $id;
				else if( preg_match( self::ID_PATTERN, $id ) === 1 )
					$snapshots[] = $id;
			}

			// By the date and time the name starts with: the mark behind them
			// decides nothing about age
			$byAge = static fn( string $a, string $b ): int => strcmp( substr( $b, 0, 17 ), substr( $a, 0, 17 ) ) ?: strcmp( $b, $a );

			usort( $dates, $byAge );
			rsort( $snapshots );

			// The dated backups first, the snapshots after them: a snapshot is
			// the way back out of a restore, not one of the daily copies
			// somebody picks from
			return array_merge( $dates, $snapshots );
		}

		/**
		 *	How an archive came to be, from its id: a snapshot, one made by hand -
		 *	with or without the mark, see ID_PATTERN - or a daily one
		 *
		 *	@param		string		$id						An id that matches ID_PATTERN
		 *
		 *	@return 	string									'snapshot', 'manual' or 'daily'
		 */
		public static function kind( string $id ): string {

			if( str_starts_with( $id, 'pre-restore-' ) === true )
				return 'snapshot';

			return preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}/', $id ) === 1 ? 'manual' : 'daily';
		}

		/**
		 *	What the list shows of every archive: its id, how it came to be and how
		 *	large the file is, in the order of dates()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'id' => string, 'kind' => 'daily'|'manual'|'snapshot', 'size' => bytes ], ... ]
		 */
		public static function archives( array &$appData ): array {

			$dir 		= self::_backupDir( $appData );
			$result = [];

			foreach( self::dates( $appData ) as $id ) {
				// An archive that went while the list was made is not listed
				$size = $dir === false ? false : @filesize( $dir. '/'. $id. '.php' );
				if( $size !== false )
					$result[] = [ 'id' => $id, 'kind' => self::kind( $id ), 'size' => $size ];
			}

			return $result;
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'backups/restore'	=> 'Restore Backup '. \Nino\Admin\Admin::postString( $data, 'date' ),
				'backups/now'			=> 'Create Backup',
				'backups/download'	=> 'Download Backup '. \Nino\Admin\Admin::postString( $data, 'date' ),
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

			\Nino\Http::ok( $request, [
				'archives' => self::archives( $appData ),
				// The largest file the server takes in an upload, 0 where php names none
				'uploadLimit' => \Nino\Images::limits()['php'],
				// Whether the account may download and upload archives: full access only (see _fullAccess())
				'full' => self::_fullAccess( $appData ),
				'enabled' => ( $appData['/nino/admin/backups'] ?? true ) !== false,
				// Why today's automatic backup is missing, while it is (see
				// \Nino\Modules\Backups::missed())
				'missed' 	=> ( $appData['/nino/admin/backups'] ?? true ) !== false ? \Nino\Modules\Backups::missed( $appData ) : null,
				// A run, daily or by hand, that ended without an end of its own and what ended it (see
				// \Nino\Modules\Backups::stopped())
				'stopped' => ( $appData['/nino/admin/backups'] ?? true ) !== false ? \Nino\Modules\Backups::stopped( $appData ) : null,
			] );
		}

		/**
		 *	Back up now: one more archive, named by date and time (see
		 *	\Nino\Modules\Backups::now()). The activity log line is written
		 *	by the dispatcher through log(), not by that call
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiNow( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$result = \Nino\Modules\Backups::now( $appData );

			if( is_array( $result ) === true ) {
				if( ( $result[2] ?? '' ) === 'backups_write' )
					\Nino\Http::fail( $request, $result[0], $result[1], 'backups_write' );
				else
					\Nino\Http::fail( $request, $result[0], $result[1] );
				return;
			}

			\Nino\Http::ok( $request, [ 'id' => $result ] );
		}

		/**
		 *	Whether the account that asks holds full access, '/*' - the way the Users
		 *	panel tells it. Download and upload ask for it on top of the panel's own
		 *	permission: a download is every datum of the project in one file, and an
		 *	upload with a restore brings back any state the project had, with the
		 *	rights the accounts held then
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		private static function _fullAccess( array &$appData ): bool {

			$user = \Nino\Auth::getCurrentUser( $appData );

			return $user !== false && in_array( '/*', \Nino\Auth::permissions( $appData, $user ), true ) === true;
		}

		/**
		 *	Hand one archive over as it lies: encrypted, behind its stub, as a file
		 *	to save. The kernel sends it - in pieces, so that an archive of any size
		 *	costs one piece of memory (see "Answering a File" in docs/development.md)
		 *	- and the account that asked is the one the permission was asked of.
		 *	The path is the archive directory's and the id's, which ID_PATTERN
		 *	allows nothing else than a name of; a link is no archive.
		 *
		 *	No lock: an archive is written whole under another name and renamed into
		 *	its place, so a file that is there is complete, and one that goes while
		 *	it is sent (the retention of a backup that succeeded, a restore's
		 *	snapshots) is still read to its end by the open file, or is a 404 if it
		 *	went before - never half of a file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDownload( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			if( self::_fullAccess( $appData ) === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			$id = \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'date' );

			if( preg_match( self::ID_PATTERN, $id ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid date' );
				return;
			}

			$dir 	= self::_backupDir( $appData );
			$path = $dir === false ? '' : $dir. '/'. $id. '.php';

			if( $path === '' || is_file( $path ) === false || is_link( $path ) === true ) {
				\Nino\Http::fail( $request, 404, 'unknown backup date', 'backups_gone' );
				return;
			}

			// The name says where the archive comes from: the host of the request,
			// kept to the characters of a host name, and the id - which is what the
			// upload reads the date and the kind from
			$host = (string) preg_replace( '/:\d+$/', '', strtolower( (string) ( $request['/nino/http/request']['header']['Host'] ?? '' ) ) );

			if( preg_match( '/^[a-z0-9][a-z0-9.-]{0,60}$/', $host ) !== 1 )
				$host = 'nino';

			$request['/nino/http/response']['body'] 	= '';
			$request['/nino/http/response']['file'] 	= $path;
			$request['/nino/http/response']['header']['Content-Type'] 				= 'application/octet-stream';
			$request['/nino/http/response']['header']['Content-Disposition'] 	= 'attachment; filename="backup-'. $host. '-'. $id. '.php"';
			$request['/nino/http/response']['header']['Cache-Control'] 				= 'private, no-store';
		}

		/**
		 *	Put an archive that was downloaded earlier back in the list, where it
		 *	is restored like any other. Nothing is overwritten and nothing is
		 *	deleted - the retention runs after a backup that succeeded and counts
		 *	the archive in its kind, which the answer says (see
		 *	\Nino\Modules\Backups::retention()).
		 *
		 *	What is checked, in this order, before the file is in its place:
		 *	the permission, php's own verdict on the upload, the name (the date
		 *	and the kind come from it: the archive carries no other mark of
		 *	either), that nothing of that name exists, the shape of the stub
		 *	(prefix, base64 and nothing else, suffix), that this project's key
		 *	opens it - an archive of another project's key is refused - and that
		 *	what it opens to is gzip. What is stored is written by this method:
		 *	the stub's prefix and suffix are its own constants and what lies
		 *	between them has been read as base64, so the file cannot be anything
		 *	that php would run beyond the exit of the stub, whatever was
		 *	uploaded. It is moved into its place with link(), which refuses an
		 *	existing name atomically, under the lock a restore and the daily run
		 *	take
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUpload( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			if( self::_fullAccess( $appData ) === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			$failure = \Nino\Admin\Admin::uploadError( $_FILES['file'] ?? null );

			if( $failure !== null ) {
				// An archive over the limit has a way in that php does not stand in
				if( $failure['code'] === 'upload_too_large' )
					\Nino\Http::fail( $request, $failure['status'], $failure['error'], 'backups_too_large', $failure['params'] );
				else
					\Nino\Http::fail( $request, $failure['status'], $failure['error'], $failure['code'], $failure['params'] );
				return;
			}

			$tmp 		= $_FILES['file']['tmp_name'] ?? null;
			$given 	= $_FILES['file']['name'] ?? null;

			if( is_string( $tmp ) === false || is_string( $given ) === false ) {
				\Nino\Http::fail( $request, 400, 'no file uploaded', 'upload_missing' );
				return;
			}

			// A name of the right shape that is no date or no time of day is none of a
			// backup either: ids sort by age, and a month 13 would sort as one
			if( preg_match( self::UPLOAD_NAME_PATTERN, basename( str_replace( '\\', '/', $given ) ), $match ) !== 1
				|| checkdate( (int) substr( $match[1], 5, 2 ), (int) substr( $match[1], 8, 2 ), (int) substr( $match[1], 0, 4 ) ) === false
				|| ( strlen( $match[1] ) > 10 && ( (int) substr( $match[1], 11, 2 ) > 23 || (int) substr( $match[1], 13, 2 ) > 59 || (int) substr( $match[1], 15, 2 ) > 59 ) ) ) {
				\Nino\Http::fail( $request, 400, 'the file name is none of a backup', 'backups_upload_name' );
				return;
			}

			$id 	= $match[1];
			$key 	= self::_key( $appData );

			if( $key === false || strlen( $key ) !== 32 ) {
				\Nino\Http::fail( $request, 409, 'this project has no backup key yet', 'backups_upload_nokey' );
				return;
			}

			// Not waited for, as a restore does not wait: what holds it is a run that
			// reads and prunes this directory
			if( \Nino\Filesystem::lockFile( $appData, \Nino\Modules\Backups::LOCK_PATH, false ) === false ) {
				\Nino\Http::fail( $request, 409, 'a backup or a restore is running', 'backups_busy' );
				return;
			}

			$temp = '';

			try {

				$dir = \Nino\Filesystem::getContentPath( $appData ). '/.backups';

				if( is_dir( $dir ) === false && @mkdir( $dir, 0755, true ) === false && is_dir( $dir ) === false ) {
					\Nino\Http::fail( $request, 500, 'the backup directory could not be created', 'backups_write' );
					return;
				}

				$final = $dir. '/'. $id. '.php';

				if( file_exists( $final ) === true ) {
					\Nino\Http::fail( $request, 409, 'an archive of that name exists', 'backups_upload_exists', [ $id ] );
					return;
				}

				$temp 		= $dir. '/upload-'. bin2hex( random_bytes( 6 ) ). '.tmp';
				$staged 	= self::_stage( $tmp, $temp );

				if( $staged !== true ) {
					\Nino\Http::fail( $request, ...$staged );
					return;
				}

				// Whether this project's key opens it is the one proof that it is one
				// of its archives, and opening takes about what restoring it will: a
				// server that cannot is told, before php runs out of memory in the
				// middle of it
				$short = \Nino\Modules\Backups::openShort( $appData, (int) @filesize( $temp ) );

				if( $short !== null ) {
					\Nino\Http::fail( $request, 413, 'the memory limit does not carry opening the archive', 'backups_upload_memory', $short );
					return;
				}

				$gz = self::_decrypt( $temp, $key );

				if( $gz === false ) {
					\Nino\Http::fail( $request, 422, 'the archive cannot be opened with this project\'s key', 'backups_upload_key' );
					return;
				}

				$gzip = str_starts_with( $gz, "\x1f\x8b" );
				unset( $gz );

				if( $gzip === false ) {
					\Nino\Http::fail( $request, 422, 'the archive holds no gzip', 'backups_upload_format' );
					return;
				}

				// An existing name is refused by link() itself: a second upload of the
				// same name in the same moment does not replace the first
				if( @link( $temp, $final ) === false ) {

					if( file_exists( $final ) === true )
						\Nino\Http::fail( $request, 409, 'an archive of that name exists', 'backups_upload_exists', [ $id ] );
					else
						\Nino\Http::fail( $request, 500, 'the archive could not be put in its place', 'backups_write' );

					return;
				}

				$actor = \Nino\Auth::getCurrentUser( $appData );

				if( $actor !== false )
					\Nino\Admin\Admin::record( $appData, $actor['mail'], 'Upload Backup '. $id );

				\Nino\Http::ok( $request, [ 'id' => $id, 'kind' => self::kind( $id ), 'size' => (int) @filesize( $final ) ] + \Nino\Modules\Backups::retention( $appData, $id ) );
			}
			finally {
				if( $temp !== '' )
					@unlink( $temp );
				\Nino\Filesystem::unlockFile( $appData, \Nino\Modules\Backups::LOCK_PATH );
			}
		}

		/**
		 *	Read an uploaded file as the stub of an archive and write that stub
		 *	again, piece by piece, so that no more than a piece is in memory: its
		 *	prefix, then what lies between - base64, every piece of it decoding on
		 *	its own, nothing else - and its suffix. The prefix and the suffix are
		 *	the constants of the stub, checked against what the file starts and
		 *	ends with and written from the constants
		 *
		 *	@param		string		$from					The uploaded file
		 *	@param		string		$to						Where to write it, not existing yet
		 *
		 *	@return 	true|array								true, or [ http status, message, code ]
		 */
		private static function _stage( string $from, string $to ): true|array {

			$prefix = \Nino\Admin\Recovery::STUB_PREFIX;
			$suffix = \Nino\Admin\Recovery::STUB_SUFFIX;
			$size 	= (int) @filesize( $from );
			$inner 	= $size - strlen( $prefix ) - strlen( $suffix );

			// Room for the 28 bytes of iv and tag and a byte of ciphertext at least,
			// and base64 comes in fours
			if( $inner < 40 || $inner % 4 !== 0 )
				return [ 400, 'the file is no backup', 'backups_upload_format' ];

			$in = @fopen( $from, 'rb' );

			if( $in === false )
				return [ 500, 'could not read upload', 'upload_server' ];

			$out = @fopen( $to, 'xb' );

			if( $out === false ) {
				fclose( $in );
				return [ 500, 'the archive could not be written', 'backups_write' ];
			}

			$result = true;

			if( fread( $in, strlen( $prefix ) ) !== $prefix || fseek( $in, -strlen( $suffix ), SEEK_END ) !== 0 || fread( $in, strlen( $suffix ) ) !== $suffix )
				$result = [ 400, 'the file is no backup', 'backups_upload_format' ];

			if( $result === true && ( fseek( $in, strlen( $prefix ) ) !== 0 || fwrite( $out, $prefix ) !== strlen( $prefix ) ) )
				$result = [ 500, 'the archive could not be written', 'backups_write' ];

			// A multiple of four, so that each piece is base64 of its own
			$remaining = $inner;

			while( $result === true && $remaining > 0 ) {

				$piece = fread( $in, min( 786432, $remaining ) );

				if( $piece === false || $piece === '' || ( strlen( $piece ) % 4 !== 0 ) ) {
					$result = [ 500, 'could not read upload', 'upload_server' ];
					break;
				}

				// Base64 and nothing else - php's strict decoding skips white space -
				// with the padding where it belongs, at the very end
				$last = strlen( $piece ) === $remaining;

				if( preg_match( $last === true ? '/^[A-Za-z0-9+\/]*={0,2}$/D' : '/^[A-Za-z0-9+\/]+$/D', $piece ) !== 1 || base64_decode( $piece, true ) === false ) {
					$result = [ 400, 'the file is no backup', 'backups_upload_format' ];
					break;
				}

				if( fwrite( $out, $piece ) !== strlen( $piece ) ) {
					$result = [ 500, 'the archive could not be written', 'backups_write' ];
					break;
				}

				$remaining -= strlen( $piece );
			}

			if( $result === true && fwrite( $out, $suffix ) !== strlen( $suffix ) )
				$result = [ 500, 'the archive could not be written', 'backups_write' ];

			fclose( $in );

			if( fclose( $out ) === false && $result === true )
				$result = [ 500, 'the archive could not be written', 'backups_write' ];

			return $result;
		}

		/**
		 *	Restore one chosen backup date: snapshot the *current* state
		 *	first (see _safetySnapshot()), so a wrong choice is itself
		 *	undoable, then overwrite every file the backup contains.
		 *
		 *	A backup that comes from another version of Nino restores all the
		 *	same, and the answer says so: 'notice' - a code and its params, as
		 *	a failure names itself (see \Nino\Http::fail()) - which the panel
		 *	words in the interface language. The activity log says it in a line
		 *	of its own, written here: log() is handed the posted body alone and
		 *	could not know.
		 *
		 *	The line of the restore itself is written here too, for a failed one
		 *	as well (see recordRestore()): the shell writes a line for an answer
		 *	of 200 only, and asks the account that is signed in then - which
		 *	this restore has just ended. The activity log is none of what an
		 *	archive holds, so the line is there after the files were put back.
		 *
		 *	The session of the account that ran it ends here. The accounts and
		 *	their sessions are part of config.php, so an archive older than the
		 *	login would have ended it on the next request anyway and a younger
		 *	one would not - the panel says before it asks that the restore logs
		 *	out, and that is true either way. Not Auth::logoutUser(), which
		 *	writes this request's copy of the account record - the one from
		 *	before the restore - over the one the archive just put back
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRestore( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$date = \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'date' );

			if( preg_match( self::ID_PATTERN, $date ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid date' );
				return;
			}

			// Taken before the restore: the accounts and their sessions are part
			// of what it puts back, and the line is the actor's to be written
			$actor = \Nino\Auth::getCurrentUser( $appData );
			$notice = null;

			// The restore takes a safety copy first, which names every file a feature owns: the same refusal as
			// the backup's, before anything is touched. recovery.php, which is for when nothing else works, does not ask
			$refusal = \Nino\Backup::featureRefusal( $appData );
			$result = $refusal !== null ? [ 409, $refusal ] : self::restore( $appData, $date, $notice );

			if( $actor !== false )
				self::recordRestore( $appData, $actor['mail'], $date, $result );

			if( $result !== true ) {
				\Nino\Http::fail( $request, $result[0], $result[1], $result[2] ?? '' );
				return;
			}

			if( $notice !== null && $actor !== false )
				\Nino\Admin\Admin::record( $appData, $actor['mail'], 'Backup '. $date. ' comes from Nino '. $notice['params'][0]. ', this is '. $notice['params'][1]. ' ('. $notice['code']. ')' );

			// The session ends with the restore (see above): the values of the
			// session go, the csrf token is a new one as at any change of the
			// session's identity, and the request carries no account any more
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/token' );
			\Nino\Csrf::rotateToken( $appData );
			unset( $appData['./nino/auth/current'] );

			\Nino\Http::ok( $request, [ 'ok' => true, 'restoredDate' => $date ] + ( $notice !== null ? [ 'notice' => $notice ] : [] ) );
		}

		/**
		 *	Write the line of a restore into the activity log, for the one that
		 *	succeeded and for the one that did not - shared with recovery.php,
		 *	which has no account to name
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$actor				Who did it - an account's mail address, or the door recovery.php
		 *	@param		string		$date					The archive that was asked for
		 *	@param		true|array	$result				What restore() answered
		 *
		 *	@return 	void
		 */
		public static function recordRestore( array &$appData, string $actor, string $date, true|array $result ): void {
			\Nino\Admin\Admin::record( $appData, $actor, 'Restore Backup '. $date. ( $result === true ? '' : ' failed ('. $result[0]. ')' ) );
		}

		/**
		 *	Restore one dated archive over the live project - the whole
		 *	operation, shared with recovery.php, which is what runs it when
		 *	nobody can log in any more. The caller has validated the date
		 *
		 *	One at a time, and not while the daily backup is being made: both
		 *	take the same lock, and a restore that does not get it at once
		 *	answers 409 and touches nothing - two of them would interleave two
		 *	copies of the files and two safety snapshots
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$date					"Y-m-d", "Y-m-d-His" or "pre-restore-Y-m-d-His" (see ID_PATTERN)
		 *	@param		array|null	&$notice			(reference) Set where the archive comes from another version of Nino than the one that runs: [ 'code' => 'backups_restored_other_version', 'params' => [ from, to ] ]; left as it was otherwise
		 *	@param		string|null	$key					Raw (not base64) 32-byte key that somebody typed: the archive is opened with it and not with this project's own, and is then judged for what it holds (see _restoreArchive()). Only recovery.php passes one - behind its own secret, and on purpose not the panel
		 *
		 *	@return 	true|array								True, or [ http status, message ] - and, where a person can cause it, the code of the failure as a third element
		 */
		public static function restore( array &$appData, string $date, ?array &$notice = null, ?string $key = null ): true|array {

			if( \Nino\Filesystem::lockFile( $appData, \Nino\Modules\Backups::LOCK_PATH, false ) === false )
				return [ 409, 'a backup or a restore is running', 'backups_busy' ];

			try {
				$dir = self::_backupDir( $appData );

				return self::_restoreArchive( $appData, $dir, $dir === false ? '' : $dir. '/'. $date. '.php', $notice, $key );
			}
			finally {
				\Nino\Filesystem::unlockFile( $appData, \Nino\Modules\Backups::LOCK_PATH );
			}
		}

		/**
		 *	Restore the archive somebody uploaded to recovery.php, in one go with the key it comes with: the
		 *	file is kept for this restore alone, in the archive directory under a name no list offers, and is
		 *	gone when the restore ends, whichever way it ends. The same stub check as an upload to the panel
		 *	(_stage()), the same lock and the same restore as for an archive of the list, with the key that
		 *	was typed - see restore()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uploaded			The uploaded file (php's temporary file)
		 *	@param		string		$key					Raw (not base64) 32-byte AES key, the one that was typed
		 *	@param		array|null	&$notice			(reference) See restore()
		 *
		 *	@return 	true|array								See restore(); a refusal for the memory carries the values of its sentence as a fourth element
		 */
		public static function restoreFile( array &$appData, string $uploaded, string $key, ?array &$notice = null ): true|array {

			if( \Nino\Filesystem::lockFile( $appData, \Nino\Modules\Backups::LOCK_PATH, false ) === false )
				return [ 409, 'a backup or a restore is running', 'backups_busy' ];

			$temp = '';

			try {

				$dir = \Nino\Filesystem::getContentPath( $appData ). '/.backups';

				if( is_dir( $dir ) === false && @mkdir( $dir, 0755, true ) === false && is_dir( $dir ) === false )
					return [ 500, 'the backup directory could not be created', 'backups_write' ];

				$temp 		= $dir. '/upload-'. bin2hex( random_bytes( 6 ) ). '.tmp';
				$staged 	= self::_stage( $uploaded, $temp );

				if( $staged !== true )
					return $staged;

				$short = \Nino\Modules\Backups::openShort( $appData, (int) @filesize( $temp ) );

				if( $short !== null )
					return [ 413, 'the memory limit does not carry opening the archive', 'backups_upload_memory', $short ];

				return self::_restoreArchive( $appData, $dir, $temp, $notice, $key );
			}
			finally {
				if( $temp !== '' )
					@unlink( $temp );
				\Nino\Filesystem::unlockFile( $appData, \Nino\Modules\Backups::LOCK_PATH );
			}
		}

		/**
		 *	The restore itself, under the lock of restore()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string|false	$dir				The archive directory, false where there is none
		 *	@param		string		$path					The archive's file
		 *	@param		array|null	&$notice			(reference) See restore()
		 *	@param		string|null	$entered			The key somebody typed, or null for this project's own
		 *
		 *	@return 	true|array								See restore()
		 */
		private static function _restoreArchive( array &$appData, string|false $dir, string $path, ?array &$notice, ?string $entered ): true|array {

			$projectKey = self::_key( $appData );
			$key 				= $entered ?? $projectKey;

			if( $dir === false || $key === false ) {
				return [ 404, 'no backup available' ];
			}

			if( is_file( $path ) === false ) {
				return [ 404, 'unknown backup date' ];
			}

			$gz = self::_decrypt( $path, $key );

			if( $gz === false ) {
				// Not a server fault: the key does not open it. The sentence of the code says which key and what to do
				return $entered === null
					? [ 422, 'the archive cannot be opened with this project\'s key', 'backups_key' ]
					: [ 422, 'the key does not open the archive', 'backups_key_wrong' ];
			}

			/*	tempnam() creates the file it names, and the archive was written
				beside it under that name plus '.tar.gz' - so the file tempnam()
				made stayed behind, one per restore, for the life of the server
				(885 of them in this container's temp directory from the test
				runs alone, the snapshot's included). And nothing below was
				checked or caught: an archive PharData could not read threw out
				of here, past the staging directory and the archive it had
				written, both left standing as well, and the panel answered a
				500 with nothing said. Everything this makes is removed on every
				way out now, and what fails answers a sentence. What an earlier
				restore left where php ended it is removed first, and the file
				tempnam() made is the lock that tells such a restore from this one
				(see \Nino\Backup::sweepTemp())	*/
			\Nino\Backup::sweepTemp( $appData );

			$temp = \Nino\Backup::tempFile( $appData, 'restore' );
			if( $temp === false )
				return [ 500, 'the temporary restore file could not be created' ];

			[ $tmpBase, $lease ] = $temp;

			$tmpGz 		= $tmpBase. '.tar.gz';
			$staging	= $tmpBase. '.staging';

			try {
				// Not held in memory across the snapshot: that one needs as much
				// again while it is made, and the two together are more than
				// either needs. It waits in the file PharData is to read, which
				// only its owner may read: it is the plain archive, config.php
				// among it. The archive itself is not decrypted again: a snapshot
				// made in the second the archive was (a restore of a snapshot)
				// replaces it
				$handle = @fopen( $tmpGz, 'xb' );
				if( $handle === false )
					return [ 500, 'the backup could not be written for unpacking' ];

				@chmod( $tmpGz, 0600 );
				$put = @fwrite( $handle, $gz );
				@fclose( $handle );

				if( $put !== strlen( $gz ) )
					return [ 500, 'the backup could not be written for unpacking' ];

				unset( $gz );

				// Read before the snapshot and before anything is unpacked: the
				// names of its entries as the tar holds them, which the
				// unpacking would normalize into something harmless-looking (see
				// \Nino\Backup::refusal()). A refused archive costs nothing
				$refused = \Nino\Backup::refusal( $tmpGz );
				if( $refused !== null )
					return [ 422, $refused ];

				// Unpacked into the staging directory before the snapshot, which
				// is the first thing that touches the project: what the unpacking
				// really made is read there (\Nino\Backup::staged()), so an
				// archive that got past the headers is refused without costing a
				// snapshot either
				$unpacked = self::_unpack( $tmpGz, $staging );
				if( $unpacked !== true )
					return $unpacked;

				// A key that was typed proves nothing about what the archive holds, where the
				// project's own key proves that this project made it: so of an archive that was opened
				// with a typed key nothing is read or copied before it is known to be data and
				// pictures (see \Nino\Backup::codeRefusal()) - nino.php is read by php in _restoreFrom()
				if( $entered !== null ) {
					$refused = \Nino\Backup::codeRefusal( $staging );
					if( $refused !== null )
						return [ 422, $refused ];
				}

				// The way back out, before anything is touched - and a snapshot
				// that cannot be made is a restore that does not start. Made with the key the project
				// has now: after an archive of another project's key it is the one key that is not in
				// the project any more, and the snapshot is then opened with it typed in at recovery.php
				// (a project that has no key of its own makes it with the one that was typed)
				try {
					self::_safetySnapshot( $appData, $dir, $projectKey !== false && strlen( $projectKey ) === 32 ? $projectKey : $key );
				}
				catch( \RuntimeException $e ) {
					return [ 500, 'the safety snapshot could not be made: '. $e->getMessage() ];
				}

				return self::_restoreFrom( $appData, '', $tmpGz, $staging, $notice, true, true );
			}
			finally {
				@unlink( $tmpGz );
				if( is_dir( $staging ) === true )
					\Nino\Filesystem::removeDir( $staging );
				@unlink( $tmpBase );
				fclose( $lease );
			}
		}

		/**
		 *	Unpack the archive into the staging directory and read what came out
		 *
		 *	@param		string		$tmpGz				The .tar.gz
		 *	@param		string		$staging			Where to unpack it, not existing yet
		 *
		 *	@return 	true|array							true, or [ http status, message ]: 500 for an archive that cannot be unpacked, 422 for one that holds what a restore must not take
		 */
		private static function _unpack( string $tmpGz, string $staging ): true|array {

			if( @mkdir( $staging, 0700, true ) === false )
				return [ 500, 'the restore staging directory could not be created' ];

			// PharData throws for an archive it cannot read - a file somebody
			// truncated or replaced that still decrypts - and a throw here was
			// a 500 the panel could not explain
			try {
				( new \PharData( $tmpGz ) )->extractTo( $staging, null, true );
			}
			catch( \Throwable $e ) {
				return [ 500, 'the backup could not be unpacked: '. $e->getMessage() ];
			}

			$refused = \Nino\Backup::staged( $staging );

			return $refused === null ? true : [ 422, $refused ];
		}

		/**
		 *	Unpack the decrypted archive into the staging directory and copy
		 *	it over the project - restore()'s own second half, with the temp
		 *	files it works on removed by restore() whichever way this leaves
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$gz						The decrypted .tar.gz bytes; not read where $written says they are in $tmpGz
		 *	@param		string		$tmpGz				Where to write them for PharData
		 *	@param		string		$staging			Where to unpack them, not existing yet
		 *	@param		array|null	&$notice			(reference) See restore()
		 *	@param		bool			$written			Whether $tmpGz holds the archive already
		 *	@param		bool			$unpacked			Whether the archive is unpacked into $staging already (see _unpack())
		 *
		 *	@return 	true|array							true, or [ http status, message ]
		 */
		private static function _restoreFrom( array &$appData, string $gz, string $tmpGz, string $staging, ?array &$notice = null, bool $written = false, bool $unpacked = false ): true|array {

			if( $written === false && @file_put_contents( $tmpGz, $gz ) !== strlen( $gz ) )
				return [ 500, 'the backup could not be written for unpacking' ];

			if( $unpacked === false ) {
				$result = self::_unpack( $tmpGz, $staging );
				if( $result !== true )
					return $result;
			}

			// The note of where the archive comes from (see \Nino\Backup::stamp())
			// is no file of the project: it is read from the staged copy and taken
			// out of it, since the copy below puts every top-level entry somewhere
			// and this one would land beside index.php. An archive made before
			// there was a note has none, and there is nothing to say then
			$stagedStamp = $staging. '/nino.php';

			if( is_file( $stagedStamp ) === true ) {

				$stamp = include $stagedStamp;
				unlink( $stagedStamp );

				if( is_array( $stamp ) === true && is_string( $stamp['nino'] ?? null ) === true && $stamp['nino'] !== \Nino\VERSION )
					$notice = [ 'code' => 'backups_restored_other_version', 'params' => [ $stamp['nino'], \Nino\VERSION ] ];
			}

			$configPath = \Nino\Filesystem::getConfigPath( $appData );

			// config.php belongs under configPath, not root - see
			// \Nino\Filesystem::getConfigPath()'s docblock. Extracting it straight
			// to root like every other file would restore it to the wrong place,
			// and under a hardened NINO_CONFIG_DIR setup would additionally leak
			// a stray copy back into the webroot - so it's staged first and moved
			// separately from the rest of the archive.
			$stagedConfig = $staging. '/config.php';

			// config.php is the one file every request reads at boot. Written in
			// place, a request booting mid-write read a half-written file - an
			// include of a truncated var_export either fatals or returns
			// something that is not an array, and AppData::init() answers that
			// with "config.php exists but did not return an array" for everybody
			// until the write finished. Renamed over it instead, under the same
			// lock every other writer of that file takes, so a reader sees the
			// old file or the new one and nothing in between
			if( is_file( $stagedConfig ) === true ) {

				if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
					return [ 500, 'could not lock config.php for the restore' ];

				$target	= $configPath. '/config.php';
				$temp		= $target. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';

				if( @copy( $stagedConfig, $temp ) === false || @rename( $temp, $target ) === false ) {
					@unlink( $temp );
					\Nino\Filesystem::unlockFile( $appData, '/config.php' );
					return [ 500, 'could not write config.php' ];
				}

				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
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

			// The archive's own layout is flat - text/, elements/, templates/,
			// assets/, images/, data/ - but those no longer share one
			// destination on disk: everything but images is private and
			// resolves against the private root (see
			// \Nino\Filesystem::PRIVATE_DIRS). Copied one top-level entry at a
			// time rather than as a single tree, so each lands wherever this
			// project actually keeps it. What a directory of the archive holds
			// replaces the file of the same name on disk, and a file on disk
			// that the archive has not is left alone - for the templates, in a
			// folder of templates/ as well, and the assets as for the texts and
			// the elements; the folder is made where it is missing
			foreach( glob( $staging. '/*' ) ?: [] as $entry ) {

				$target = \Nino\Filesystem::path( $appData, '/'. basename( $entry ) );

				if( is_dir( $entry ) === true )
					\Nino\Filesystem::copyDir( $entry, $target );
				else
					@copy( $entry, $target );
			}

			// extractTo()/copyDir() write straight to disk, bypassing Filesystem's own
			// cache tracking entirely - drop it so any getFileContent() call
			// later in this same request (or a request landing in the same
			// wall-clock second - see AppData::writeContentData()'s docblock
			// for why that specifically matters) re-reads the restored files
			// instead of whatever was cached from before the restore. What
			// is made of the text files for the request - the fills of a
			// language and the list of the languages that have a file - goes
			// with it: a write through Filesystem drops those, and this one
			// is none
			unset( $appData['./nino/filesystem/cache'], $appData['./nino/html/filecache'], $appData['./nino/locales/text'] );

			if( function_exists( 'opcache_reset' ) === true )
				opcache_reset();

			return true;
		}

		/**
		 *	Archive the *current* on-disk state before overwriting it, same
		 *	tar+gzip+encrypt shape as \Nino\Modules\Backups::_create() (duplicated, not
		 *	called into - see this class' own docblock; the file manifest
		 *	and the note of the state it comes from are shared via
		 *	\Nino\Backup::manifest() and ::stamp(), which carry no such
		 *	coupling), so it holds what an archive holds: the templates and the
		 *	assets with the rest, and the stamp. Named distinctly from a dated
		 *	backup so it's never mistaken for one and never collides with/gets
		 *	pruned by Backup's own retention sweep
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$key					Raw (not base64) 32-byte AES key
		 *
		 *	@return 	void
		 */
		private static function _safetySnapshot( array &$appData, string $dir, string $key ): void {

			/*	The shape Backups::_create() has, which this had drifted from:
				the file tempnam() makes was never removed - one per restore,
				for the life of the server - and a file the manifest names but
				that cannot be read was false into addFromString(), a TypeError
				out of the panel with the temp files left behind. Everything
				this makes is removed on every way out, and what cannot be read
				or written throws, which restore() answers as a refusal before
				it touches anything	*/
			$temp = \Nino\Backup::tempFile( $appData, 'snapshot' );
			if( $temp === false )
				throw new \RuntimeException( 'the temporary snapshot file could not be created' );

			[ $tmpBase, $lease ] = $temp;

			$tmpTar = $tmpBase. '.tar';

			try {
				// One pass over the files, as a backup is made (see \Nino\Backup::pack())
				\Nino\Backup::pack( $appData, \Nino\Backup::manifest( $appData ), $tmpTar );

				$gz = @file_get_contents( $tmpTar. '.gz' );
				if( $gz === false )
					throw new \RuntimeException( 'the compressed snapshot could not be read' );

				@unlink( $tmpTar. '.gz' );

				$iv 		= random_bytes( 12 );
				$tag 		= '';
				$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
				unset( $gz );
				if( $cipher === false )
					throw new \RuntimeException( 'the snapshot could not be encrypted' );

				$snapshot	= $dir. '/pre-restore-'. date( 'Y-m-d-His' ). '.php';

				// Renamed into place like a backup itself: a snapshot the list
				// offers is a whole one or none
				if( \Nino\Admin\Admin::writeFileAtomic( $snapshot, \Nino\Admin\Recovery::stubPieces( $iv, $tag, $cipher ) ) === false )
					throw new \RuntimeException( 'the snapshot could not be written' );
			}
			finally {
				@unlink( $tmpTar );
				@unlink( $tmpTar. '.gz' );
				@unlink( $tmpBase );
				fclose( $lease );
			}

			self::_pruneSnapshots( $dir );
		}

		/**
		 *	Keep the newest SNAPSHOT_KEEP safety snapshots and drop the rest.
		 *
		 *	Backups::_prune() deliberately walks past these - they are not
		 *	dated backups and must not be swept up with them - which left
		 *	every restore a project ever did as a full encrypted copy on disk,
		 *	for good. Their own bound rather than the daily retention in days:
		 *	what makes a snapshot worth keeping is that it is recent in
		 *	restores, not in days, and a project that restores twice a year
		 *	would lose its way back out after two weeks of nothing happening.
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *
		 *	@return 	void
		 */
		private static function _pruneSnapshots( string $dir ): void {

			$snapshots = glob( $dir. '/pre-restore-*.php' ) ?: [];

			if( count( $snapshots ) <= self::SNAPSHOT_KEEP )
				return;

			// The name carries the timestamp, so sorting it sorts them by age
			rsort( $snapshots );

			foreach( array_slice( $snapshots, self::SNAPSHOT_KEEP ) as $file )
				@unlink( $file );
		}
	}
}
