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

		// How many of the files that could not be put back a restore that is done in part names: the rest is the entry of the archive they are in
		private const int NAMED_FILES = 10;

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
		 *	The files and directories a request makes for its work - the plain archive of a restore, the copy of an upload - and removes
		 *	when it ends in order, which a finally does not for the end that php makes of a request at its time or memory limit: a function
		 *	that php runs at that end removes what is still listed. What a step removes by itself it takes off the list
		 *
		 *	@return 	array										[ the files still to be removed, the directories still to be removed ], each an \ArrayObject of paths
		 */
		private static function _guard(): array {

			$files 	= new \ArrayObject();
			$dirs 	= new \ArrayObject();

			register_shutdown_function( static function() use ( $files, $dirs ): void {

				foreach( $files as $file )
					@unlink( (string) $file );

				foreach( $dirs as $dir )
					if( is_dir( (string) $dir ) === true )
						\Nino\Filesystem::removeDir( (string) $dir );
			} );

			return [ $files, $dirs ];
		}

		/**
		 *	Whether the disk where an uploaded file is kept for its check has the room for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					The archive directory
		 *	@param		string		$uploaded			The uploaded file
		 *
		 *	@return 	array|null							null where it fits, else [ http status, message, code, params ]
		 */
		private static function _roomForUpload( array &$appData, string $dir, string $uploaded ): ?array {

			$need = (int) @filesize( $uploaded ) + 4194304;
			$free = \Nino\Backup::freeSpace( $appData, $dir );

			return $free !== null && $free < $need ? [ 507, 'the disk has no room for the archive', 'backups_disk', [ (int) ceil( $need / 1048576 ), (int) floor( max( 0, $free ) / 1048576 ) ] ] : null;
		}

		/**
		 *	What a restore answers where an archive could not be opened (see \Nino\Backup\Seal::open()): a tag that does not hold is
		 *	the key or a file that was changed, which cannot be told apart, and anything else is a file that is none of ours
		 *
		 *	@param		string		$code					What open() answered
		 *	@param		bool			$typed				Whether the key is one somebody typed at recovery.php
		 *
		 *	@return 	array										[ http status, message, code ]
		 */
		private static function _failure( string $code, bool $typed ): array {

			if( $code === \Nino\Backup\Seal::AUTH )
				return $typed === true ? [ 422, 'the key does not open the archive', 'backups_key_wrong' ] : [ 422, 'the archive cannot be opened with this project\'s key', 'backups_key' ];

			return [ 422, 'the file is no backup', 'backups_upload_format' ];
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
				\Nino\Http::fail( $request, $result[0], $result[1], (string) ( $result[2] ?? '' ), $result[3] ?? [] );
				return;
			}

			$note = \Nino\Backup::missingNote( $appData );

			\Nino\Http::ok( $request, [ 'id' => $result ] + ( $note !== null ? [ 'note' => $note ] : [] ) );
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

			$temp 	= '';
			[ $guardFiles ] = self::_guard();

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

				$short = self::_roomForUpload( $appData, $dir, $tmp );

				if( $short !== null ) {
					\Nino\Http::fail( $request, ...$short );
					return;
				}

				$temp 		= $dir. '/upload-'. bin2hex( random_bytes( 6 ) ). '.tmp';
				$guardFiles->append( $temp );
				$staged 	= self::_stage( $tmp, $temp );

				if( $staged !== true ) {
					\Nino\Http::fail( $request, ...$staged );
					return;
				}

				// Whether this project's key opens it is the one proof that it is one
				// of its archives: every chunk of it is read and its tag checked, none of
				// it kept. An archive of format 1 is read whole, and a server that cannot
				// is told, before php runs out of memory in the middle of it
				if( \Nino\Backup\Seal::format( $temp ) === 1 ) {

					$short = \Nino\Modules\Backups::openShort( $appData, (int) @filesize( $temp ) );

					if( $short !== null ) {
						\Nino\Http::fail( $request, 413, 'the memory limit does not carry opening the archive', 'backups_upload_memory', $short );
						return;
					}
				}

				try {
					$opened = \Nino\Backup\Seal::open( $temp, $key, null, $head );
				}
				catch( \RuntimeException ) {
					\Nino\Http::fail( $request, 500, 'could not read upload', 'upload_server' );
					return;
				}

				if( $opened !== null ) {
					\Nino\Http::fail( $request, 422, $opened === \Nino\Backup\Seal::AUTH ? 'the archive cannot be opened with this project\'s key' : 'the file is no backup', $opened === \Nino\Backup\Seal::AUTH ? 'backups_upload_key' : 'backups_upload_format' );
					return;
				}

				if( $head !== "\x1f\x8b" ) {
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
		 *	A restore that went through and left something out (see wentThrough()) ends as one that succeeded does and answers its 500 with the
		 *	notices; one that stopped halfway or was refused answers and ends where it is.
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

			// A restore that was refused, or stopped halfway, ends here: nothing of what follows is done for it. One that went through and left
			// something out (see wentThrough()) ends as a restore that succeeded does, and answers its failure with the notices
			if( $result !== true && self::wentThrough( $result ) === false ) {
				self::fail( $request, $result );
				return;
			}

			if( $notice !== null && $actor !== false )
				\Nino\Admin\Admin::record( $appData, $actor['mail'], match( $notice['code'] ) {
					'backups_snapshot_raw' 			=> 'The safety snapshot of '. $notice['params'][0]. ' is the state as it was, with all its files ('. $notice['code']. ')',
					'backups_companions_left' 	=> 'The files an open database keeps beside itself stand beside '. $notice['params'][0]. ' files of the project, as they were ('. $notice['code']. ')',
					default 										=> 'Backup '. $date. ' comes from Nino '. $notice['params'][0]. ', this is '. $notice['params'][1]. ' ('. $notice['code']. ')' } );

			// The session ends with the restore (see above): the values of the
			// session go, the csrf token is a new one as at any change of the
			// session's identity, and the request carries no account any more
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/token' );
			\Nino\Csrf::rotateToken( $appData );
			unset( $appData['./nino/auth/current'] );

			$note = \Nino\Backup::missingNote( $appData );
			$more = ( $notice !== null ? [ 'notice' => $notice ] : [] ) + ( $note !== null ? [ 'note' => $note ] : [] );

			if( $result !== true ) {
				self::fail( $request, $result );
				$request['/nino/http/response']['body'] += $more;
				return;
			}

			\Nino\Http::ok( $request, [ 'ok' => true, 'restoredDate' => $date ] + $more );
		}

		/**
		 *	The answer of a restore that did not succeed: the failure as \Nino\Http::fail() has it, and the fields of its own that a restore that is
		 *	done in part names what it left (the fifth element of what restore() answers) - shared with recovery.php
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$result				What restore() answered: [ http status, message, code, params, fields ]
		 *
		 *	@return 	void
		 */
		public static function fail( array &$request, array $result ): void {

			\Nino\Http::fail( $request, $result[0], $result[1], $result[2] ?? '', $result[3] ?? [] );

			if( is_array( $result[4] ?? null ) === true )
				$request['/nino/http/response']['body'] += $result[4];
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
			// What a restore that is done in part says besides: the archive of the list that holds the state before (the last of its params), and
			// every part of what it names - the features whose callback failed, the files left as they are, the files a failed callback took out
			// of the staging, the files written while they were in use
			$what = '';

			if( $result !== true && in_array( $result[2] ?? '', [ 'backups_restore_partial', 'backups_restore_waiting', 'backups_restore_callback', 'backups_restore_inuse', 'backups_restore_both', 'backups_restore_written' ], true ) === true ) {

				$what = ' in part, the state before is the archive '. (string) end( $result[3] );

				foreach( [ 'features' => ', the restore callback of %s failed', 'files' => ', %s in use and left as it is', 'taken' => ', %s not put back by the restore: the callback of the feature took it out and failed', 'written' => ', %s written while in use and possibly damaged', 'cut' => ', the copy of %s was not whole', 'waiting' => ', %s being written by another request and left as it was' ] as $field => $words )
					if( is_array( $result[4][$field] ?? null ) === true && $result[4][$field] !== [] )
						$what .= sprintf( $words, implode( ', ', $result[4][$field] ) );
			}

			\Nino\Admin\Admin::record( $appData, $actor, 'Restore Backup '. $date. ( $result === true ? '' : ' failed ('. $result[0]. ')'. $what ) );
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
		 *	@return 	true|array								True, or [ http status, message ] - and, where a person can cause it, the code of the failure as a third element, its params as a fourth and, for a restore that is done in part, the fields of the answer that name what it left as a fifth
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

			$temp 	= '';
			[ $guardFiles ] = self::_guard();

			try {

				$dir = \Nino\Filesystem::getContentPath( $appData ). '/.backups';

				if( is_dir( $dir ) === false && @mkdir( $dir, 0755, true ) === false && is_dir( $dir ) === false )
					return [ 500, 'the backup directory could not be created', 'backups_write' ];

				$short = self::_roomForUpload( $appData, $dir, $uploaded );

				if( $short !== null )
					return $short;

				$temp 		= $dir. '/upload-'. bin2hex( random_bytes( 6 ) ). '.tmp';
				$guardFiles->append( $temp );
				$staged 	= self::_stage( $uploaded, $temp );

				if( $staged !== true )
					return $staged;

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

			$began			= time();
			$projectKey = self::_key( $appData );
			$key 				= $entered ?? $projectKey;

			if( $dir === false || $key === false ) {
				return [ 404, 'no backup available' ];
			}

			if( is_file( $path ) === false ) {
				return [ 404, 'unknown backup date' ];
			}

			// An archive of format 1 is read whole: a server that cannot carry it says so before php runs out of memory
			if( \Nino\Backup\Seal::format( $path ) === 1 ) {

				$short = \Nino\Modules\Backups::openShort( $appData, (int) @filesize( $path ) );

				if( $short !== null )
					return [ 413, 'the memory limit does not carry opening the archive', 'backups_upload_memory', $short ];
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

			// The plain archive, what it is unpacked to and the snapshot of the project need room next to each other: said before any of
			// them is made, with the sizes
			$short = \Nino\Modules\Backups::restoreShort( $appData, (int) @filesize( $path ) );

			if( $short !== null )
				return [ 507, 'the disk has no room for a restore of the archive', 'backups_disk', $short ];

			$temp = \Nino\Backup::tempFile( $appData, 'restore' );
			if( $temp === false )
				return [ 500, 'the temporary restore file could not be created', 'backups_write' ];

			[ $tmpBase, $lease ] = $temp;

			$tmpGz 		= $tmpBase. '.tar.gz';
			$staging	= $tmpBase. '.staging';

			// What a request that php ends takes with it: the plain archive, config.php among it, is no file to leave in the temporary directory
			[ $guardFiles, $guardDirs ] = self::_guard();
			$guardFiles->exchangeArray( [ $tmpGz, $tmpBase ] );
			$guardDirs->exchangeArray( [ $staging ] );

			$locks = [];

			try {
				// The plain archive waits in the file PharData is to read, which only
				// its owner may read: config.php is among it. It is written chunk by
				// chunk as the archive is opened and used once every chunk of the file
				// has held - a file that was cut, changed or put together from two
				// ends in a tag that does not, and nothing of what was read is looked
				// at then. Not held in memory across the snapshot, which needs as much
				// again while it is made. The archive itself is not opened again: a
				// snapshot made in the second the archive was (a restore of a
				// snapshot) replaces it
				$handle = @fopen( $tmpGz, 'xb' );
				if( $handle === false )
					return [ 500, 'the backup could not be written for unpacking', 'backups_write' ];

				@chmod( $tmpGz, 0600 );
				fclose( $handle );

				try {
					$opened = \Nino\Backup\Seal::open( $path, $key, $tmpGz, $head );
				}
				catch( \RuntimeException ) {
					return [ 500, 'the backup could not be written for unpacking', 'backups_write' ];
				}

				if( $opened !== null )
					return self::_failure( $opened, $entered !== null );

				// One gzip stream: a tar that is cut in two members, or bytes behind the end, are what PharData refused and no reader of ours may join
				if( $head !== "\x1f\x8b" || \Nino\Backup::gzipOnly( $tmpGz ) === false )
					return [ 422, 'the archive holds no gzip', 'backups_upload_format' ];

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
				// The disks the files are copied back to have the room for them: said with the numbers before anything is touched
				$short = \Nino\Backup::restoreRoom( $appData, $staging );

				if( $short !== null )
					return [ 507, 'the disk of the project has no room for the files of the archive', 'backups_disk', $short ];

				// What an earlier restore that php ended left beside the files (see _sweepBeside()) is taken away before anything is made or packed
				self::_sweepBeside( $appData, $staging, $began );

				// The id of the safety snapshot is taken here, with its wait for the next second where a snapshot of this one is there - a restore a moment ago -,
				// before a lock of a writer is held: the writers are not kept waiting for it. The restore's own lock is held, which no other producer of an archive
				// takes a name under, and the link that puts the snapshot in its place refuses a name that has been taken meanwhile. The state before that restore is
				// kept by an archive of its own
				$name = \Nino\Modules\Backups::freeId( $dir, 'pre-restore-', '' );

				if( $name === null )
					return [ 409, 'the archives carry a later time than the clock of the server', 'backups_clock' ];

				// The files that a writer reads, changes and writes back go back under the locks their writers take (see \Nino\Backup::lockPath() and
				// lockFiles()), all of them held before the first byte is put back and before the snapshot - as many as the limit of open files lets the
				// restore hold (see \Nino\Backup::locksHeld()), the rest one at a time as each file is put back. A writer that waits for one of them reads
				// the file as it is put back, and a project that is being written to is refused here, with nothing made and nothing changed. A lock is
				// let go as its file is in place, the rest at the end (the finally below)
				$locks	= \Nino\Backup::lockPaths( $staging );
				$busy		= \Nino\Backup::lockFiles( $appData, array_slice( $locks, 0, \Nino\Backup::locksHeld() ) );

				if( $busy !== true )
					return [ 409, 'another request is writing a file the restore has to put back', 'backups_restore_busy', [ $busy ] ];

				$snapshot = '';
				$hook 		= '';
				$short 		= [];
				$raw			= [];

				try {
					$snapshot = self::_safetySnapshot( $appData, $dir, $projectKey !== false && strlen( $projectKey ) === 32 ? $projectKey : $key, $name, $hook, $short, $raw );
				}
				catch( \RuntimeException $e ) {

					// The disk that the copies the features handed over filled, with the sizes; and the feature that could not hand its data over, by its key
					if( $e->getCode() === \Nino\Backup::DISK_SHORT && $short !== [] )
						return [ 507, 'the disk has no room for the safety snapshot', 'backups_disk', $short ];

					if( $e->getCode() === \Nino\Backup::HOOK_FAILED && $hook !== '' )
						return [ 500, 'the safety snapshot could not be made: '. $e->getMessage(), 'backups_hook', [ $hook ] ];

					// A snapshot of the name was made meanwhile: the restore has not begun, and answers as it does for a run that goes on
					if( $e->getCode() === \Nino\Backup::NAME_TAKEN )
						return [ 409, 'a backup or a restore is running', 'backups_busy' ];

					// A file of the project that could not be read, or that changed while the snapshot was packed: named in the answer and in the log, and the restore has not begun
					if( \Nino\Backup::sourceName( $e ) !== null ) {

						trigger_error( 'Restore: the safety snapshot could not be made: '. $e->getMessage(), E_USER_WARNING );

						return [ 500, 'the safety snapshot could not be made: '. $e->getMessage(), 'backups_source', [ (string) \Nino\Backup::sourceName( $e ) ] ];
					}

					return [ 500, 'the safety snapshot could not be made: '. $e->getMessage() ] + ( $e->getCode() === \Nino\Backup::WRITE_FAILED ? [ 2 => 'backups_write' ] : [] );
				}

				return self::_restoreFrom( $appData, '', $tmpGz, $staging, $notice, true, true, $snapshot, $raw, $entered !== null );
			}
			finally {
				\Nino\Backup::unlockFiles( $appData, $locks );
				@unlink( $tmpGz );
				if( is_dir( $staging ) === true )
					\Nino\Filesystem::removeDir( $staging );
				@unlink( $tmpBase );
				fclose( $lease );
				$guardFiles->exchangeArray( [] );
				$guardDirs->exchangeArray( [] );
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
				return [ 500, 'the restore staging directory could not be created', 'backups_write' ];

			// An archive that cannot be read - a file somebody truncated or replaced that still decrypts - is
			// answered with what is wrong with it, and no path
			$unwritten = false;
			$failure 	 = \Nino\Backup::unpack( $tmpGz, $staging, $unwritten );

			if( $failure !== null )
				return [ 500, 'the backup could not be unpacked: '. $failure ] + ( $unwritten === true ? [ 2 => 'backups_write' ] : [] );

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
		 *	@param		string		$snapshot			The id of the safety snapshot
		 *	@param		array 		$raw					The keys of the features whose files the snapshot holds as they lie
		 *	@param		bool			$foreign			Whether the archive is not this project's own
		 *
		 *	@return 	true|array							true, or [ http status, message ]
		 */
		private static function _restoreFrom( array &$appData, string $gz, string $tmpGz, string $staging, ?array &$notice = null, bool $written = false, bool $unpacked = false, string $snapshot = '', array $raw = [], bool $foreign = false ): true|array {

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
			$copies			 = [];

			if( is_file( $stagedStamp ) === true ) {

				$stamp = include $stagedStamp;
				unlink( $stagedStamp );

				if( is_array( $stamp ) === true && is_string( $stamp['nino'] ?? null ) === true && $stamp['nino'] !== \Nino\VERSION )
					$notice = [ 'code' => 'backups_restored_other_version', 'params' => [ $stamp['nino'], \Nino\VERSION ] ];

				// The files a feature handed over whole, as the archive lists them: of those, and of no other file, the companions go (see \Nino\Backup::dropCompanions())
				$copies = is_array( $stamp ) === true ? ( $stamp['copies'] ?? [] ) : [];
			}

			// The safety snapshot holds the state of a feature whose hook could not do its work as it was, with all its files: said, and no failure
			if( $raw !== [] )
				$notice = $notice ?? [ 'code' => 'backups_snapshot_raw', 'params' => [ implode( ', ', $raw ) ] ];

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

				// Held since the restore began; where a snapshot that ran since let it go, it is asked for once more, for the whole time
				$busy = \Nino\Backup::lockFiles( $appData, [ '/config.php' ] );

				if( $busy !== true )
					return [ 409, 'another request is writing a file the restore has to put back', 'backups_restore_busy', [ $busy ] ];

				if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
					return [ 500, 'could not lock config.php for the restore', 'backups_write' ];

				$target	= $configPath. '/config.php';
				$temp		= $target. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';

				if( @copy( $stagedConfig, $temp ) === false || @rename( $temp, $target ) === false ) {
					@unlink( $temp );
					\Nino\Filesystem::unlockFile( $appData, '/config.php' );
					return [ 500, 'could not write config.php', 'backups_write' ];
				}

				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
				unlink( $stagedConfig );
			}

			// The outbox of the mail is no part of any restore, whatever the archive carries
			\Nino\Backup::dropOutbox( $staging );

			// A module that keeps state a restore must not simply overwrite
			// rewrites its staged files here, before the copy below - the
			// Newsletter module merges its removal record this way (see its
			// callbackRestore()). Restore itself knows no module: whatever
			// registered on '/nino/admin/restore' gets the live data
			// directory (read only) and the extracted backup. A callback that
			// throws does not end the restore - config.php is replaced when they run -:
			// what is left in the staging of its files is copied as if it had
			// none, and the answer names it (see \Nino\Backup::restoreCallbacks())
			$restoreArgs = [ 'dataDir' => \Nino\Filesystem::path( $appData, '/data' ), 'staging' => $staging ];
			$listed			 = \Nino\Backup::handedOver( $appData, $staging, $copies );
			$took				 = [];
			$thrown 		 = \Nino\Backup::restoreCallbacks( $appData, $restoreArgs, array_keys( array_filter( $listed ) ), $took );

			// The archive's own layout is flat - text/, elements/, templates/,
			// assets/, images/, data/, blocks/, themes/, fonts/ - and they do not share
			// one destination on disk: everything but images and fonts is
			// private and resolves against the private root (see
			// \Nino\Filesystem::PRIVATE_DIRS), and blocks/ and themes/ are private/blocks/
			// and private/themes/ (see \Nino\Backup::target()). Copied one top-level entry at a
			// time rather than as a single tree, so each lands wherever this
			// project actually keeps it. What a directory of the archive holds
			// replaces the file of the same name on disk, and a file on disk
			// that the archive has not is left alone - for the templates, in a
			// folder of templates/ as well, and the assets as for the texts and
			// the elements; the folder is made where it is missing. An archive
			// that has no blocks/, themes/ or fonts/ puts nothing there and takes
			// nothing away: the blocks, the themes and the fonts on disk stay
			//
			// A file is written beside the file it replaces and renamed over it, under the lock its writers take (see _putBack()), so that a writer that
			// read the file before does not write on top of what is put back and a reader never sees half a file. A database that was handed over whole
			// is the exception: it is written over in place, and its size is what says that it is whole
			//
			// Every copy is asked how it went and every file is looked at after it: a disk that fills up leaves files that are cut off, and
			// a restore that says it was done over such a site is the worst answer there is
			$unfinished = false;
			$cut				= [];
			$held				= [];

			// What an archive carries of a database that a feature hands over whole (see \Nino\Backup::stage()) is that one file: the files an open
			// database keeps beside itself in the project would be applied to the copy that is put there, so they go - and where one cannot be
			// removed, the answer is the one for a restore that is done in part
			$left = 0;

			// A database that was handed over whole and that a feature has not taken over in its callback is not written over while a process holds
			// a lock on the live file: the copy goes into the file the process works on, which then goes on writing pages of the old state into
			// the new content. Its staged file is taken out, so that the copy below does not touch it and the files beside it are not taken away (what
			// is not staged is not handed over), and they stay for the process that uses them. The look and the copy are not one step: it is made
			// here, and a second time right before the files of data/ are copied - a file that is held by then is left as well, though the files beside it
			// were taken away by the step between - and once more right after, for a file that was held while it was written: it cannot be
			// undone, and the answer says that it may be damaged. A process that takes its lock between two of the looks is not seen
			$inUse		= [];
			$written	= [];
			$leave		= static function( array $names ) use ( $staging, &$inUse ): void {

				foreach( $names as $name ) {
					@unlink( $staging. '/'. $name );
					trigger_error( 'Restore: '. $name. ' is in use by another process and was left as it is, with the files beside it', E_USER_WARNING );
					$inUse[] = $name;
				}
			};

			$leave( \Nino\Backup::inUse( $appData, $staging, $copies, $foreign, $listed ) );

			if( \Nino\Backup::dropCompanions( $appData, $staging, $copies, $foreign, $left, $listed ) === false ) {
				$unfinished = true;
				$cut[]			= 'the files beside a database';
			}

			// An archive of another project is not believed about what to take away: the operator is told beside how many files the companions stand
			if( $left > 0 )
				$notice = $notice ?? [ 'code' => 'backups_companions_left', 'params' => [ (string) $left ] ];

			// The files that are read-modify-written by their writers first, so that their locks are let go early: the large trees come last
			$first		= array_flip( [ 'text', 'elements', 'templates', 'data', 'assets', 'blocks', 'themes', 'fonts', 'images' ] );
			$entries	= glob( $staging. '/*' ) ?: [];

			usort( $entries, static fn( string $one, string $other ): int => ( $first[basename( $one )] ?? 99 ) <=> ( $first[basename( $other )] ?? 99 ) );

			foreach( $entries as $entry ) {

				$name		= basename( $entry );
				$target = \Nino\Backup::target( $appData, $name );

				if( $name === 'data' )
					$leave( \Nino\Backup::inUse( $appData, $staging, $copies, $foreign, $listed ) );

				$whole	 = true;
				$inPlace = [];

				// What a feature keeps below data/ that is no php - a database - and what a feature handed over whole: written over the live file, as it
				// always was. A process that has such a file open keeps the file it opened, which a rename would leave to it
				if( $name === 'data' )
					foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $entry, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file ) {

						$one = 'data'. str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $entry ) ) );

						if( \Nino\Backup::renamed( $one ) === true && isset( $listed[$one] ) === false )
							continue;

						$inPlace[] = $one;
						$to 			 = $target. substr( $one, 4 );

						if( \Nino\Filesystem::copyDir( $file->getPathname(), $to ) === false || self::_whole( $file->getPathname(), $to ) === false )
							$whole = false;
					}

				[ $lost, $waiting ] = is_dir( $entry ) === true ? self::_putBack( $appData, $entry, $target, $name, $inPlace ) : [ @copy( $entry, $target ) === false || self::_whole( $entry, $target ) === false ? [ $name ] : [], [] ];

				if( $whole === false || $lost !== [] ) {
					$unfinished = true;

					// The entry is named where a copy in place fell short, the files where they could not be put back - the first few, then the entry
					if( $whole === false || count( $lost ) > self::NAMED_FILES )
						$cut[] = $name;

					foreach( array_slice( $lost, 0, self::NAMED_FILES ) as $one )
						if( $one !== $name )
							$cut[] = $one;
				}

				// A file that another request was writing for as long as the restore waits: it is as it was, and no disk is full for it
				foreach( $waiting as $one )
					$held[] = $one;

				if( $name === 'data' ) {

					$written = \Nino\Backup::inUse( $appData, $staging, $copies, $foreign, $listed );

					foreach( $written as $one )
						trigger_error( 'Restore: '. $one. ' was in use by another process while it was put back and may be damaged', E_USER_WARNING );
				}
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

			// Partly done: the answer says so, and which archive of the list holds the state before - no path. What the restore did not do or did
			// to a file that is in use is in fields of the answer of their own, whichever code the answer has, and the sentence of each code says
			// only what happened with it. The sentence of the answer carries one word or number for each of its places, which is what the
			// recovery page takes of an answer (see Nino.pageText.failure()): the first key, the numbers of files. Of what happened together, the
			// worst is the code, and a copy that fell short is the worst: that restore stopped halfway and ends where it is, whatever else it
			// says. Then a file that was written while it was in use, a callback and files that are left as they are, a callback, files that
			// are left as they are. The log has a line for every one of them
			foreach( $cut as $name )
				trigger_error( 'Restore: the copy of '. $name. ' is not whole: a file could not be written, the disk may be full', E_USER_WARNING );

			foreach( $held as $name )
				trigger_error( 'Restore: '. $name. ' was being written by another request for as long as the restore waits, and is as it was', E_USER_WARNING );

			foreach( $took as $name )
				trigger_error( 'Restore: '. $name. ' was taken out of the staging by a callback that then failed, and was not put back', E_USER_WARNING );

			$fields = array_filter( [ 'features' => $thrown, 'files' => $inUse, 'taken' => $took, 'written' => $written, 'cut' => $cut, 'waiting' => $held ] );

			if( $unfinished === true )
				return [ 500, 'the restore is done in part: a file could not be written, the disk may be full', 'backups_restore_partial', [ $snapshot ], $fields ];

			if( $held !== [] )
				return [ 500, 'the restore is done in part: files that another request was writing were left as they are', 'backups_restore_waiting', [ count( $held ), $snapshot ], $fields ];

			if( $written !== [] )
				return [ 500, 'the restore is done in part: files that were in use were written and may be damaged', 'backups_restore_written', [ count( $written ), $snapshot ], $fields ];

			if( $thrown !== [] && $inUse !== [] )
				return [ 500, 'the restore is done in part: a feature could not restore its data and files that are in use were left as they are', 'backups_restore_both', [ $thrown[0], count( $inUse ), $snapshot ], $fields ];

			if( $thrown !== [] )
				return [ 500, 'the restore is done in part: a feature could not restore its data', 'backups_restore_callback', [ $thrown[0], $snapshot ], $fields ];

			if( $inUse !== [] )
				return [ 500, 'the restore is done in part: files that are in use were left as they are', 'backups_restore_inuse', [ count( $inUse ), $snapshot ], $fields ];

			return true;
		}

		/**
		 *	Whether a restore that did not succeed went through all the same: the files of the archive are put back, and what is left out is named in
		 *	the answer. Such a restore ends as one that succeeded does - the session of the account that ran it, the notices - and a restore that
		 *	stopped halfway (backups_restore_partial) does not
		 *
		 *	@param		true|array	$result				What restore() answered
		 *
		 *	@return 	bool
		 */
		public static function wentThrough( true|array $result ): bool {
			return $result !== true && in_array( $result[2] ?? '', [ 'backups_restore_callback', 'backups_restore_waiting', 'backups_restore_inuse', 'backups_restore_both', 'backups_restore_written' ], true ) === true;
		}

		/**
		 *	Put the files of one directory of the archive back, each beside the file it replaces and renamed over it (see _beside()). A file that a writer reads,
		 *	changes and writes back (see \Nino\Backup::lockPath()) is put back under the lock its writers take: the lock is taken here where the restore does not
		 *	hold it - one of the files beyond what it holds locks for (see \Nino\Backup::locksHeld()), or a lock that a callback let go of - with the whole time
		 *	\Nino\Backup::$lockWait of its own, and is let go as the file is in place, with what was put back compared to the archive's file, size and hash, while
		 *	it is held. A writer that waited for the lock reads the file as it is put back and writes on top of it. A file whose lock another request held that
		 *	long is not put back, whatever lies there: it is as it was, and is named apart from the files that could not be written. A file that is a directory
		 *	where it goes, or a disk that is full, leaves that file as it was, and the others are put back all the same. The assets, the pictures and the fonts have
		 *	no lock and wait for none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source				A directory of the staging, eg. '/tmp/x.staging/text'
		 *	@param		string		$target				Where its files go
		 *	@param		string		$entry				Its name in the archive, eg. 'text'
		 *	@param		array 		$inPlace			The names in the archive of the files that are put back in place and not here
		 *
		 *	@return 	array										[ the names of the files that could not be written, the names of the files whose lock was not had ]
		 */
		private static function _putBack( array &$appData, string $source, string $target, string $entry, array $inPlace ): array {

			$lost		= [];
			$waiting	= [];

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file ) {

				$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $source ) + 1 ) );
				$name 		= $entry. '/'. $relative;

				if( in_array( $name, $inPlace, true ) === true )
					continue;

				$there	= $target. '/'. $relative;
				$lock		= \Nino\Backup::lockPath( $name );

				// A file is never put back without the lock that is its writers': where it is not had it stays as it is
				if( $lock !== null && \Nino\Backup::lockFiles( $appData, [ $lock ] ) !== true ) {
					$waiting[] = $name;
					continue;
				}

				try {
					$done = ( is_dir( dirname( $there ) ) === true || @mkdir( dirname( $there ), 0755, true ) === true || is_dir( dirname( $there ) ) === true )
						&& self::_beside( $file->getPathname(), $there ) === true
						&& self::_whole( $file->getPathname(), $there, true ) === true;
				}
				finally {

					// The lock of the features is one for all the files of the blocks and the themes: it is let go with the others at the end
					if( $lock !== null && $lock !== \Nino\Features::LOCK_PATH )
						\Nino\Filesystem::unlockFile( $appData, $lock );
				}

				if( $done === false )
					$lost[] = $name;
			}

			return [ $lost, $waiting ];
		}

		/**
		 *	Take away what a restore that php ended left beside the files it puts back: <file>.<12 hex digits>.tmp, the name of the copy that _beside() makes,
		 *	where <file> is a file of this archive that is put back beside the file and renamed over it - at any depth, not in a hidden folder, not through a
		 *	link. Nothing else goes: not a file a person or a feature named like that, not the copy of a writer of the kernel that is at work, and none that is
		 *	not a minute older than the restore began. Under the lock of the restore, so that no restore of the same project is writing such a file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$staging			Absolute path of the directory the archive was unpacked into
		 *	@param		int				$began				The time the restore began (unix)
		 *
		 *	@return 	void
		 */
		private static function _sweepBeside( array &$appData, string $staging, int $began ): void {

			$staging = rtrim( $staging, DIRECTORY_SEPARATOR );

			$walk = static function( string $dir, string $relative ) use ( &$walk, $began, $staging ): void {

				foreach( @scandir( $dir ) ?: [] as $object ) {

					if( $object[0] === '.' )
						continue;

					$path = $dir. '/'. $object;

					if( is_link( $path ) === true )
						continue;

					if( is_dir( $path ) === true ) {
						$walk( $path, $relative. '/'. $object );
						continue;
					}

					if( preg_match( '/^(.+)\.[0-9a-f]{12}\.tmp$/D', $object, $found ) !== 1 || (int) @filemtime( $path ) >= $began - 60 )
						continue;

					$name = substr( $relative. '/'. $found[1], 1 );

					if( \Nino\Backup::renamed( $name ) === true && is_file( $staging. '/'. $name ) === true )
						@unlink( $path );
				}
			};

			foreach( glob( $staging. '/*' ) ?: [] as $entry )
				if( is_dir( $entry ) === true )
					$walk( \Nino\Backup::target( $appData, basename( $entry ) ), '/'. basename( $entry ) );

			// config.php is written beside itself, in its own directory
			if( is_file( $staging. '/config.php' ) === true )
				foreach( glob( \Nino\Filesystem::getConfigPath( $appData ). '/config.php.*.tmp' ) ?: [] as $leftover )
					if( preg_match( '/\.[0-9a-f]{12}\.tmp$/D', $leftover ) === 1 && (int) @filemtime( $leftover ) < $began - 60 )
						@unlink( $leftover );
		}

		/**
		 *	Replace a file by a copy of another that is made beside it and renamed over it, so that a reader sees the file as it was or as it is and nothing
		 *	between, and a file that cannot be written is left as it was. The copy is made by the system, in no more memory than a buffer, whatever the size
		 *	of the file. The mode of the file that is replaced is kept; the compiled copy of a php file goes
		 *
		 *	@param		string		$from					The staged file
		 *	@param		string		$to						The file it replaces, or where it goes
		 *
		 *	@return 	bool											Whether it is in place
		 */
		private static function _beside( string $from, string $to ): bool {

			$temp = $to. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';

			if( @copy( $from, $temp ) === false ) {
				@unlink( $temp );
				return false;
			}

			$mode = @fileperms( $to );

			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $to ) === false ) {
				@unlink( $temp );
				return false;
			}

			if( function_exists( 'opcache_invalidate' ) === true )
				opcache_invalidate( $to, true );

			return true;
		}

		/**
		 *	Whether what was copied is what there was to copy: every file of the source is at the target and is not shorter than it. A copy answers
		 *	true only where it wrote every byte, so a file that is cut is shorter than its source; one that is longer was written to by another process
		 *	after the copy - a database that is in use grows, and the restore of it is not done in part for that
		 *
		 *	A file that was put back under the lock of its writers (see _putBack()) is no database that grows: $exact asks for the size of the source and for
		 *	its hash, the bytes of the archive and nothing else
		 *
		 *	@param		string		$source				A staged file or directory
		 *	@param		string		$target				Where it was copied to
		 *	@param		bool			$exact				Whether a file must be what the source is, byte for byte
		 *
		 *	@return 	bool
		 */
		private static function _whole( string $source, string $target, bool $exact = false ): bool {

			clearstatcache();

			if( is_file( $source ) === true ) {

				if( is_file( $target ) === false )
					return false;

				if( $exact === false )
					return filesize( $target ) >= filesize( $source );

				$hash = @hash_file( 'sha256', $source );

				return filesize( $target ) === filesize( $source ) && $hash !== false && @hash_file( 'sha256', $target ) === $hash;
			}

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file ) {

				$there = $target. substr( $file->getPathname(), strlen( $source ) );

				if( is_file( $there ) === false || filesize( $there ) < $file->getSize() )
					return false;
			}

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
		 *	@param		string		$name					The id of the snapshot, 'pre-restore-Y-m-d-His', taken before the locks of the writers (see \Nino\Modules\Backups::freeId())
		 *	@param		string		&$hook				(reference) The key of the feature that was asked for a copy of its data last, '' once none is being asked (see \Nino\Backup::stage())
		 *	@param		array 		&$short				(reference) Set where the copies the features handed over left the disk without the room for the packing: [ megabytes needed, megabytes free ]
		 *	@param		array 		&$raw					(reference) The keys of the features whose files the snapshot holds as they lie, because their hook could not hand over a copy
		 *
		 *	@return 	string									The id of the snapshot, as the list names it
		 */
		private static function _safetySnapshot( array &$appData, string $dir, string $key, string $name, string &$hook = '', array &$short = [], array &$raw = [] ): string {

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
				throw new \RuntimeException( 'the temporary snapshot file could not be created', \Nino\Backup::WRITE_FAILED );

			[ $tmpBase, $lease ] = $temp;

			$tmpTar = $tmpBase. '.tar';

			// What a request that php ends takes with it: the plain tar of the project is no file to leave in the temporary directory
			[ $guardFiles, $guardDirs ] = self::_guard();
			$guardFiles->exchangeArray( [ $tmpTar, $tmpTar. '.gz', $tmpBase ] );
			$guardDirs->exchangeArray( [ $tmpBase. '.staging' ] );

			try {
				// The features that keep a database hand over a copy of it that is whole, as they do for a backup, and what is packed is that copy;
				// the disk is asked again with what they wrote
				// A net under the state as it IS: a hook that fails is no reason to keep a restore from happening, which is what a database that
				// is broken needs. That feature's files are packed as they lie, with what an open database keeps beside them, and the stamp says so
				$notes		= [];
				$whole		= \Nino\Backup::manifest( $appData );
				$manifest = \Nino\Backup::stage( $appData, $whole, $tmpBase. '.staging', static function( string $feature ) use ( &$hook ): void {
					$hook = $feature;
				}, true, $notes );

				$bytes = 0;

				foreach( array_keys( $manifest ) as $absolute )
					$bytes += (int) @filesize( $absolute );

				$room = \Nino\Backup::spaceShort( $appData, $bytes, count( $manifest ), $dir );

				// The copies filled the disk: the files as they lie are what there is room for, or there is none
				if( $room !== null && $notes['copies'] !== [] ) {

					\Nino\Filesystem::removeDir( $tmpBase. '.staging' );

					$notes['raw']		= array_fill_keys( $notes['asked'], 'the disk had no room for the copies' );
					$notes['copies']	= [];
					$notes['links']		= [];
					$notes['companions'] = [];
					$manifest 				= \Nino\Backup::raw( $appData, $whole, $notes['asked'], $notes['links'], $notes['companions'] );
					$bytes 						= 0;

					foreach( array_keys( $manifest ) as $absolute )
						$bytes += (int) @filesize( $absolute );

					$room = \Nino\Backup::spaceShort( $appData, $bytes, count( $manifest ), $dir );
				}

				if( $room !== null ) {
					$short = $room;
					throw new \RuntimeException( 'the disk has no room', \Nino\Backup::DISK_SHORT );
				}

				// One pass over the files, as a backup is made (see \Nino\Backup::pack())
				$raw = array_map( 'strval', array_keys( (array) ( $notes['raw'] ?? [] ) ) );

				\Nino\Backup::pack( $appData, $manifest, $tmpTar, $notes );

				$snapshot	= $dir. '/'. $name. '.php';

				// Sealed in chunks as it is written, and put into place like a backup
				// itself, which never replaces an archive of the name: a snapshot the list
				// offers is a whole one or none
				\Nino\Modules\Backups::publish( $snapshot, \Nino\Backup\Seal::pieces( $tmpTar. '.gz', $key ) );
			}
			finally {
				@unlink( $tmpTar );
				@unlink( $tmpTar. '.gz' );
				@unlink( $tmpBase );
				\Nino\Filesystem::removeDir( $tmpBase. '.staging' );
				fclose( $lease );
				$guardFiles->exchangeArray( [] );
				$guardDirs->exchangeArray( [] );
			}

			self::_pruneSnapshots( $dir );

			return basename( $snapshot, '.php' );
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
