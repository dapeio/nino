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
	 *											job, so it needs no server-level scheduling at all. The
	 *											panel's "Back up now" adds an archive of its own on top
	 *											of that one (see now()). Lives
	 *											here rather than in the kernel (_nino/) since
	 *											it's a workbench operational concern, not something
	 *											every Nino deployment needs - a site with no workbench has
	 *											nothing to back up in the first place.
	 *
	 *											Three independent layers, the last two of which hold even
	 *											where the private half is served after all:
	 *											- kept below the private directory, at private/.backups
	 *											  (see dir()) - project data rather than a tool folder, so
	 *											  an update replaces _admin/ without taking the archives
	 *											  with it, and nothing under private/ is served to begin
	 *											  with. The one-time random directory name this class used
	 *											  to generate went with that move: it bought an unguessable
	 *											  name inside the webroot, and there is no longer a name
	 *											  inside the webroot to guess
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
	 *											Restore is the panel beside this file, Admin/Admin.php - this
	 *											class only ever creates, never reads/decrypts, backups.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Backups {

		// How many archives are kept when the project says nothing: the newest
		// daily ones and the newest of those made by hand (see now()). The
		// backup is an emergency tool, not a history. The project sets both
		// numbers in config.php: '/nino/admin/backups-daily' and
		// '/nino/admin/backups-manual', each 1 to KEEP_MAX
		private const int KEEP_DEFAULT = 3;
		private const int KEEP_MAX = 30;

		// Held by the daily run and by a restore (see \Nino\Modules\Backups\Admin::restore()):
		// the one never starts in the middle of the other
		public const string LOCK_PATH = '/_admin/backup-daily';

		// The note of the day's one attempt (see maybeRun()), in the archive
		// directory behind the same exit-stub as an archive. Its name is no
		// date, so no list offers it and no sweep touches it
		private const string ATTEMPT_FILE = 'attempt.php';

		// The record of the run that is going on (see _begin()), in the same
		// place and behind the same stub. The run holds its lock for as long as
		// it lives, so a record that is not locked is the one of a run that
		// ended without a word - what stopped() reads for the panel. Its name
		// is no date either
		private const string RUN_FILE = 'run.php';

		// What _create() holds at its peak is two copies of the compressed
		// archive (it and its ciphertext, while it is encrypted, PEAK_FACTOR):
		// the sources are packed in pieces and never in memory together, so what
		// compresses costs nothing. Opening an archive (Admin::restore() and
		// its snapshot) needs no more
		private const int PEAK_FACTOR = 2;

		// Formats that are compressed already: gzip takes the least of a file of
		// these, never less than PICTURE_FLOOR of its size (jpeg gains under one
		// percent, png and webp less), which makes the pictures a lower bound of the
		// archive that is safe to refuse on before anything is packed
		private const array PICTURE_FORMATS = [ 'jpg', 'jpeg', 'png', 'webp', 'avif', 'heic', 'heif' ];
		private const float PICTURE_FLOOR = 0.9;

		// Room for the code, the tar's own bookkeeping and the pieces the
		// stub is written in
		private const int PEAK_OVERHEAD = 16777216;

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

		/**
		 *	The directory that holds backup archives
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Absolute path, existing or not
		 */
		public static function dir( array &$appData ): string {

			return \Nino\Filesystem::getContentPath( $appData ). substr( self::BACKUPS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) );
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

			foreach( glob( self::dir( $appData ). '/*.php' ) ?: [] as $file )
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

				// A stable, virtual lock path rather than the archive directory:
				// it serializes the first-use key generation in _bootstrap() and
				// the once-a-day existence check alike.
				//
				// Not waited for: a restore holds it for as long as it runs, and a
				// request that waited here would then run as a second restore of its
				// own. The run that finds it taken is skipped, and the next request
				// makes it. A lock that cannot be had at all - the directory of the
				// locks is missing or not writable - is a failure and is logged
				$locked = \Nino\Filesystem::lockFile( $appData, self::LOCK_PATH, false );
				if( $locked === false ) {

					$locks = \Nino\Filesystem::path( $appData, '/data/.locks' );

					if( is_dir( $locks ) === false || is_writable( $locks ) === false )
						throw new \RuntimeException( 'daily backup could not be locked' );

					return;
				}

				self::_bootstrap( $appData );

				\Nino\Filesystem::forceDir( $appData, self::BACKUPS_DIR );

				// Taken once: a run that starts before midnight and ends after it
				// is an attempt of the day it started. The key is the test's
				// clock
				$today 	= (string) ( $appData['./nino/backups/today'] ?? date( 'Y-m-d' ) );
				$dir 		= self::dir( $appData );
				$path 	= $dir. '/'. $today. '.php';

				if( is_file( $path ) === true )
					return;

				// One attempt a day. An archive that cannot be made because the
				// request runs out of memory ends that request with a fatal
				// error no catch sees, and the next request would find today's
				// archive missing all the same and die the same way - the
				// workbench would be out of reach until somebody fixed it by
				// hand. The attempt is on disk before it starts, so the request
				// that dies in it is the only one it costs
				if( self::_attempted( $dir, $today ) === true )
					return;

				self::_noteAttempt( $dir, $today, '' );

				$run = self::_begin( $dir, 'daily' );

				try {
					self::_create( $appData, $dir, $path, $run );
				} catch( \Throwable $e ) {
					self::_noteAttempt( $dir, $today, self::_reason( $e ), self::_code( $e ) );
					throw $e;
				} finally {
					self::_end( $run );
				}

				@unlink( $dir. '/'. self::ATTEMPT_FILE );
				self::_prune( $dir, self::_keep( $appData, 'daily' ), self::_keep( $appData, 'manual' ), basename( $path ) );

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
		 *	Write one archive now, on request - the Backups panel's "Back up
		 *	now". An archive of its own, named by date and time, beside the
		 *	daily one and never in place of it: today's Y-m-d.php is the state
		 *	before the day's work (see maybeRun()), and a click at five in the
		 *	afternoon must not replace it. Kept like the daily ones - the same
		 *	way - and only the newest ones, as many as the project keeps.
		 *
		 *	Without the daily lock: the name is to the second, so two clicks in
		 *	the same second make the same archive - the second write replaces
		 *	the first, atomically, and both answer with that id. That lock is
		 *	taken by every request of the workbench
		 *	(see maybeRun()) - holding it for the seconds an archive takes
		 *	would stop all of them. _bootstrap() serialises itself under
		 *	config.php's lock. The activity log line is the panel's (see
		 *	Admin::log()), not written here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|array						The new archive's id ("Y-m-d-His"), or [ http status, message ] - and, where a write failed, the code the panel words it by as a third element
		 */
		public static function now( array &$appData ): string|array {

			if( ( $appData['/nino/admin/backups'] ?? true ) === false )
				return [ 409, 'backups are switched off' ];

			try {

				self::_bootstrap( $appData );

				\Nino\Filesystem::forceDir( $appData, self::BACKUPS_DIR );

				$dir 	= self::dir( $appData );
				$id 	= date( 'Y-m-d-His' );

				$run = self::_begin( $dir, 'manual' );

				try {
					self::_create( $appData, $dir, $dir. '/'. $id. '.php', $run );
				} finally {
					self::_end( $run );
				}

				// An archive that was made is the way out of a day that went
				// without one: the next request may try the daily one again
				@unlink( $dir. '/'. self::ATTEMPT_FILE );
				self::_prune( $dir, self::_keep( $appData, 'daily' ), self::_keep( $appData, 'manual' ), $id. '.php' );

				return $id;

			} catch( \RuntimeException | \PharException $e ) {
				return self::_code( $e ) === 'write' ? [ 500, $e->getMessage(), 'backups_write' ] : [ 500, $e->getMessage() ];
			}
		}

		/**
		 *	Why today's automatic backup is missing, while it is: the attempt
		 *	maybeRun() made today failed or was cut short, and no archive of
		 *	the day exists. Shared by the Backups panel, which says so
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							[ 'reason' => what the attempt reported, '' where it ended without a word ], or null
		 */
		public static function missed( array &$appData ): ?array {

			$dir = self::dir( $appData );

			if( is_file( $dir. '/'. date( 'Y-m-d' ). '.php' ) === true )
				return null;

			$note = self::_readAttempt( $dir );

			return $note === null || $note['date'] !== date( 'Y-m-d' ) ? null : [ 'reason' => $note['reason'] ] + ( isset( $note['code'] ) === true ? [ 'code' => $note['code'] ] : [] );
		}

		/**
		 *	The run that did not end, while there is one: the last run that began
		 *	- the daily one or one by hand - and that php or the server ended
		 *	before it was over, which no catch sees, and which nobody else
		 *	reports. It says when it began, how many files and how many bytes it
		 *	had to pack and, where php said so, what ended it: the time limit or
		 *	the memory limit, with the limit. A run that is going on is none of
		 *	this (it holds the lock of its record), and one that ended by itself
		 *	has removed its record. Shared by the Backups panel, which says so
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							[ 'kind' => 'daily'|'manual', 'started' => unix time, 'files' => int, 'bytes' => int, 'cause' => 'time'|'memory'|'error'|'' (what ended it, '' where nothing says), 'limit' => seconds or bytes of that limit, 0 where there is none ], or null
		 */
		public static function stopped( array &$appData ): ?array {

			$handle = @fopen( self::dir( $appData ). '/'. self::RUN_FILE, 'rb' );

			if( $handle === false )
				return null;

			// A shared lock that is refused is a run that lives
			if( flock( $handle, LOCK_SH | LOCK_NB ) === false ) {
				fclose( $handle );
				return null;
			}

			$note = self::_readNote( self::dir( $appData ). '/'. self::RUN_FILE );

			fclose( $handle );

			if( $note === null || in_array( $note['kind'] ?? null, [ 'daily', 'manual' ], true ) === false || is_int( $note['started'] ?? null ) === false )
				return null;

			return [
				'kind' 		=> $note['kind'],
				'started'	=> $note['started'],
				'files' 	=> is_int( $note['files'] ?? null ) === true ? $note['files'] : 0,
				'bytes' 	=> is_int( $note['bytes'] ?? null ) === true ? $note['bytes'] : 0,
				'cause' 	=> in_array( $note['cause'] ?? null, [ 'time', 'memory', 'error' ], true ) === true ? $note['cause'] : '',
				'limit' 	=> is_int( $note['limit'] ?? null ) === true ? $note['limit'] : 0,
			];
		}

		/**
		 *	Whether maybeRun() has made its attempt for today already
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$today				"Y-m-d"
		 *
		 *	@return 	bool
		 */
		private static function _attempted( string $dir, string $today ): bool {

			$note = self::_readAttempt( $dir );

			return $note !== null && $note['date'] === $today;
		}

		/**
		 *	The note of the last attempt, an earlier day's included
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *
		 *	@return 	array|null							[ 'date' => "Y-m-d", 'reason' => string ], or null where there is none or it is not one
		 */
		private static function _readAttempt( string $dir ): ?array {

			$note = self::_readNote( $dir. '/'. self::ATTEMPT_FILE );

			if( $note === null || is_string( $note['date'] ?? null ) === false || is_string( $note['reason'] ?? null ) === false )
				return null;

			return [ 'date' => $note['date'], 'reason' => $note['reason'] ] + ( ( $note['code'] ?? null ) === 'write' ? [ 'code' => 'write' ] : [] );
		}

		/**
		 *	What a note file holds: the json inside its stub
		 *
		 *	@param		string		$path					Absolute path of the note
		 *
		 *	@return 	array|null							The decoded note, or null where the file is not one
		 */
		private static function _readNote( string $path ): ?array {

			$raw = @file_get_contents( $path );

			if( is_string( $raw ) === false || str_starts_with( $raw, \Nino\Admin\Recovery::STUB_PREFIX ) === false || str_ends_with( $raw, \Nino\Admin\Recovery::STUB_SUFFIX ) === false )
				return null;

			$json = base64_decode( substr( $raw, strlen( \Nino\Admin\Recovery::STUB_PREFIX ), -strlen( \Nino\Admin\Recovery::STUB_SUFFIX ) ), true );
			$note = is_string( $json ) === true ? json_decode( $json, true ) : null;

			return is_array( $note ) === true ? $note : null;
		}

		/**
		 *	Write the note of today's attempt - before it is made with no
		 *	reason, after it failed with the one it reported. A note that
		 *	cannot be written is a failure of its own: an attempt nobody can
		 *	see would be one the next request makes again
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$date					"Y-m-d" of the day the attempt began
		 *	@param		string		$reason				What the attempt reported, '' while there is none
		 *	@param		string		$code					What kind of failure it was, where the panel words it: 'write', '' otherwise
		 *
		 *	@return 	void
		 */
		private static function _noteAttempt( string $dir, string $date, string $reason, string $code = '' ): void {

			self::_writeRawAtomic( $dir. '/'. self::ATTEMPT_FILE, \Nino\Admin\Recovery::STUB_PREFIX. base64_encode( (string) json_encode( [ 'date' => $date, 'reason' => $reason ] + ( $code === '' ? [] : [ 'code' => $code ] ) ) ). \Nino\Admin\Recovery::STUB_SUFFIX );
		}

		/**
		 *	The kind of failure the panel words in the interface language: a write
		 *	that failed, with no room or a file that cannot be written
		 *
		 *	@param		\Throwable	$e
		 *
		 *	@return 	string									'write', or '' for any other
		 */
		private static function _code( \Throwable $e ): string {

			return get_class( $e ) === \RuntimeException::class && $e->getCode() === \Nino\Backup::WRITE_FAILED ? 'write' : '';
		}

		/**
		 *	What the attempt reports of why it failed: the message of an
		 *	exception this class threw itself, and nothing of any other - the
		 *	message of an exception from php or from PharData carries a path
		 *
		 *	@param		\Throwable	$e
		 *
		 *	@return 	string
		 */
		private static function _reason( \Throwable $e ): string {

			return get_class( $e ) === \RuntimeException::class ? $e->getMessage() : '';
		}

		/**
		 *	The memory limit in bytes; 0 or less for none. php's own unless
		 *	'./nino/backups/memory' names one as an int: the test of the check
		 *	cannot lower the limit of the process that runs it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		private static function _limit( array &$appData ): int {

			$injected = $appData['./nino/backups/memory'] ?? null;

			return is_int( $injected ) === true ? $injected : ini_parse_quantity( (string) ini_get( 'memory_limit' ) );
		}

		/**
		 *	Refuse a step the memory limit would not carry, with the sizes in
		 *	the message: running out of it is a fatal error that no catch sees.
		 *	Asked where the size is known - of the largest file before the
		 *	archive is built, of the compressed archive before it is read - on
		 *	top of what this request uses already.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$bytes				What the step holds at once
		 *	@param		string		$of						What that is, for the message
		 *
		 *	@return 	void
		 *
		 *	@throws		\RuntimeException					If the step would not fit
		 */
		private static function _requireMemory( array &$appData, int $bytes, string $of ): void {

			$limit = self::_limit( $appData );

			if( $limit <= 0 )
				return;

			$used = memory_get_usage();
			$need = $used + $bytes + self::PEAK_OVERHEAD;

			if( $need > $limit )
				throw new \RuntimeException( sprintf( 'the backup needs about %d MB of memory (%d MB for %s, %d MB in use) and the limit is %d MB', ceil( $need / 1048576 ), ceil( $bytes / 1048576 ), $of, ceil( $used / 1048576 ), floor( $limit / 1048576 ) ) );
		}

		/**
		 *	Generate the encryption key on first use - a project that never
		 *	opens the workbench never gets that config.php key at all. The
		 *	random directory name this used to generate beside it is gone:
		 *	the archives sit at a fixed place below the private directory
		 *	now and need no unguessable name of their own (see dir()).
		 *
		 *	The key also gets an independent copy, written to
		 *	private/.auth/backup-key.php (behind the same .php exit-stub as
		 *	a backup itself, since it's just as sensitive) - the Backups
		 *	panel beside this file reads *that* copy, deliberately not this
		 *	one, so restoring never depends on config.php's data being
		 *	intact. The copy is reconciled on every call, independent of
		 *	whether the key already existed, since an install that had
		 *	backups running before the copy existed - or one whose copy was
		 *	deleted since - would otherwise never get one: the generation
		 *	above only ever runs once. It goes beside the recovery secret
		 *	rather than into a tool folder, so it survives an update that
		 *	replaces _admin/ and is there whenever a restore is.
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
			// directory now and need none (see dir())
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

			// Independent of the above: an install that already had a key
			// before this copy existed (or whose copy was lost/deleted since)
			// never gets one otherwise, since the block above only ran once,
			// on first-ever bootstrap
			\Nino\Filesystem::forceDir( $appData, dirname( self::KEY_PATH ) );

			$keyPath 		= self::keyPath( $appData );
			$keyContent = \Nino\Admin\Recovery::STUB_PREFIX. $appData['/nino/backup/key']. \Nino\Admin\Recovery::STUB_SUFFIX;

			if( @file_get_contents( $keyPath ) !== $keyContent )
				self::_writeRawAtomic( $keyPath, $keyContent );
		}

		/**
		 *	Begin a run: its record, which says that one is going on and what
		 *	it has to do, and the function that php calls when the request ends
		 *	without the run being over. That function is what php lets run even
		 *	after the time limit or the memory limit has ended the request: it
		 *	writes what ended it into the record and removes the temporary files
		 *	of the run, which a finally does not for such an end. A run that ends
		 *	by itself, with or without an archive, goes through _end() and leaves
		 *	no record. The record is locked for as long as the run lives, so a
		 *	panel that reads it can tell a run that is going on from one that is
		 *	gone; where it cannot be locked - a second run, a directory that
		 *	cannot be written - there is no record, and the run goes on without
		 *	one
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$kind					'daily' or 'manual'
		 *
		 *	@return 	\stdClass								The state of the run: what _create(), _end() and the shutdown function share
		 */
		private static function _begin( string $dir, string $kind ): \stdClass {

			$run 				= new \stdClass();
			$run->done 	= false;
			$run->path 	= $dir. '/'. self::RUN_FILE;
			$run->note 	= [ 'kind' => $kind, 'started' => time() ];
			$run->temp 	= '';
			$run->lease = null;
			$run->handle = @fopen( $run->path, 'c+b' );

			if( $run->handle !== false && flock( $run->handle, LOCK_EX | LOCK_NB ) === false ) {
				fclose( $run->handle );
				$run->handle = false;
			}

			self::_record( $run, [] );

			register_shutdown_function( static function() use ( $run ): void {

				if( $run->done === true )
					return;

				$run->done = true;

				$error 	= error_get_last();
				$cause 	= [];

				if( $error !== null && ( $error['type'] & ( E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR ) ) !== 0 ) {

					$cause = [ 'cause' => 'error' ];

					if( preg_match( '/^Maximum execution time of (\d+) seconds? exceeded/', $error['message'], $match ) === 1 )
						$cause = [ 'cause' => 'time', 'limit' => (int) $match[1] ];
					else if( preg_match( '/^Allowed memory size of (\d+) bytes exhausted/', $error['message'], $match ) === 1 )
						$cause = [ 'cause' => 'memory', 'limit' => (int) $match[1] ];
				}

				self::_record( $run, $cause );
				self::_dropTemp( $run );

				if( is_resource( $run->handle ) === true )
					fclose( $run->handle );
			} );

			return $run;
		}

		/**
		 *	Write what is known of a run into its record
		 *
		 *	@param		\stdClass	$run					See _begin()
		 *	@param		array 		$more					What to add to the record: 'files' and 'bytes' of the work, 'cause' and 'limit' of an end
		 *
		 *	@return 	void
		 */
		private static function _record( \stdClass $run, array $more ): void {

			if( is_resource( $run->handle ) === false )
				return;

			$run->note = $more + $run->note;

			ftruncate( $run->handle, 0 );
			rewind( $run->handle );
			fwrite( $run->handle, \Nino\Admin\Recovery::STUB_PREFIX. base64_encode( (string) json_encode( $run->note ) ). \Nino\Admin\Recovery::STUB_SUFFIX );
			fflush( $run->handle );
		}

		/**
		 *	End a run that ends by itself: its record goes, and its temporary files
		 *
		 *	@param		\stdClass	$run					See _begin()
		 *
		 *	@return 	void
		 */
		private static function _end( \stdClass $run ): void {

			$run->done = true;

			if( is_resource( $run->handle ) === true ) {
				@unlink( $run->path );
				fclose( $run->handle );
			}

			self::_dropTemp( $run );
		}

		/**
		 *	Remove the temporary files of a run, and let go of the lock of the
		 *	first of them
		 *
		 *	@param		\stdClass	$run					See _begin()
		 *
		 *	@return 	void
		 */
		private static function _dropTemp( \stdClass $run ): void {

			if( $run->temp === '' )
				return;

			@unlink( $run->temp. '.tar' );
			@unlink( $run->temp. '.tar.gz' );
			@unlink( $run->temp );

			if( is_resource( $run->lease ) === true )
				fclose( $run->lease );

			$run->temp 	= '';
			$run->lease = null;
		}

		/**
		 *	What the run has to pack, read from the files before anything is
		 *	made: the manifest, the number of files and of bytes, and of those
		 *	the bytes that do not compress - the pictures, which a compressed
		 *	archive holds as large as they are. A file the manifest names that
		 *	cannot be read ends the run here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ 'manifest' => path -> name, 'files' => int, 'bytes' => int, 'pictures' => int ]
		 *
		 *	@throws		\RuntimeException					If a file of the manifest cannot be read
		 */
		private static function _plan( array &$appData ): array {

			$manifest = \Nino\Backup::manifest( $appData );
			$bytes 		= 0;
			$pictures	= 0;

			foreach( $manifest as $absolute => $archiveName ) {

				$size = @filesize( $absolute );

				if( $size === false )
					throw new \RuntimeException( 'backup source could not be read: '. $archiveName );

				$bytes += $size;

				if( str_starts_with( $archiveName, 'images/' ) === true && in_array( strtolower( pathinfo( $archiveName, PATHINFO_EXTENSION ) ), self::PICTURE_FORMATS, true ) === true )
					$pictures += $size;
			}

			return [ 'manifest' => $manifest, 'files' => count( $manifest ), 'bytes' => $bytes, 'pictures' => $pictures ];
		}

		/**
		 *	Tar+gzip every file the admin panel can write to at runtime and,
		 *	after them, the note of the state they come from (see
		 *	\Nino\Backup::stamp()), encrypt it, and write it out behind the .php
		 *	exit-stub. Built via PharData rather than shelling out to
		 *	`tar`/`gzip` - pure PHP, and unlike the executable Phar class,
		 *	PharData isn't restricted by the phar.readonly ini setting many
		 *	hosts enable by default.
		 *
		 *	The files are packed in one pass (see \Nino\Backup::pack()), which
		 *	reads each in pieces. A project whose pictures could not be carried
		 *	by the memory limit is refused before anything is packed: the
		 *	pictures do not compress, so their size is the least the archive
		 *	will have, and it is held twice while it is encrypted. What the
		 *	size of the compressed archive tells beyond that is checked once
		 *	it exists
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		string		$path					Absolute path of the backup file to write: today's, or the timed archive's
		 *	@param		\stdClass	$run					The run this is part of, see _begin()
		 *
		 *	@return 	void
		 */
		private static function _create( array &$appData, string $dir, string $path, \stdClass $run ): void {

			// A feature that is switched on and whose manifest is refused owns data this run cannot name
			// (Backup::manifest() reads the manifests of the features it knows): the archive would lack it
			// without a word, so the run ends here, before it writes or removes anything
			$refusal = \Nino\Backup::featureRefusal( $appData );

			if( $refusal !== null )
				throw new \RuntimeException( $refusal );

			if( is_dir( $dir ) === false && @mkdir( $dir, 0755, true ) === false && is_dir( $dir ) === false )
				throw new \RuntimeException( 'backup directory could not be created' );

			// What an earlier run left that ended without a word
			\Nino\Backup::sweepTemp( $appData );

			$plan = self::_plan( $appData );

			self::_record( $run, [ 'files' => $plan['files'], 'bytes' => $plan['bytes'] ] );
			self::_requireMemory( $appData, (int) ( $plan['pictures'] * self::PICTURE_FLOOR ) * self::PEAK_FACTOR, 'the pictures' );

			$temp = \Nino\Backup::tempFile( $appData, 'backup' );
			if( $temp === false )
				throw new \RuntimeException( 'temporary backup file could not be created' );

			[ $run->temp, $run->lease ] = $temp;

			$tmpTar = $run->temp. '.tar';

			\Nino\Backup::pack( $appData, $plan['manifest'], $tmpTar );

			// What compresses costs nothing here; what does not is read, and
			// encrypted next to its plain bytes
			self::_requireMemory( $appData, (int) @filesize( $tmpTar. '.gz' ) * self::PEAK_FACTOR, 'the compressed archive' );

			$gz = @file_get_contents( $tmpTar. '.gz' );
			if( $gz === false )
				throw new \RuntimeException( 'compressed backup could not be read' );

			// Held in memory from here on
			@unlink( $tmpTar. '.gz' );

			$key = base64_decode( (string) $appData['/nino/backup/key'], true );
			if( is_string( $key ) === false || strlen( $key ) !== 32 )
				throw new \RuntimeException( 'backup encryption key is invalid' );

			$iv 		= random_bytes( 12 );
			$tag 		= '';
			$cipher = openssl_encrypt( $gz, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			// The plain archive is not needed past this line: the ciphertext
			// is written from where it is, in pieces
			unset( $gz );

			if( $cipher === false )
				throw new \RuntimeException( 'backup encryption failed' );

			self::_writeRawAtomic( $path, \Nino\Admin\Recovery::stubPieces( $iv, $tag, $cipher ) );
		}

		// Raw (non-serialized) counterpart to Filesystem's atomic writer. Backup
		// blobs and the restore-key stub are valid PHP source strings already;
		// passing either through putFileContent() would var_export them instead.
		// The content is a string or the pieces of one, in order, so an archive
		// need not exist as a single string next to its parts
		private static function _writeRawAtomic( string $path, string|iterable $content ): void {

			$temp = $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );

			if( $handle === false )
				throw new \RuntimeException( 'temporary file could not be opened: '. basename( $path ), \Nino\Backup::WRITE_FAILED );

			$written	= 0;
			$expected	= 0;

			foreach( is_string( $content ) === true ? [ $content ] : $content as $piece ) {
				$expected += strlen( $piece );
				$put = @fwrite( $handle, $piece );
				if( $put === false ) {
					$written = false;
					break;
				}
				$written += $put;
			}

			$flushed = @fflush( $handle );
			$closed	 = @fclose( $handle );

			// What is on the disk must be what was written: a write that was cut short
			// is not an archive, and nothing is renamed into its place
			clearstatcache( true, $temp );

			if( $written === false || $written !== $expected || $flushed === false || $closed === false || @filesize( $temp ) !== $expected ) {
				@unlink( $temp );
				throw new \RuntimeException( 'file could not be written: '. basename( $path ), \Nino\Backup::WRITE_FAILED );
			}

			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				throw new \RuntimeException( 'file could not be replaced: '. basename( $path ), \Nino\Backup::WRITE_FAILED );
			}
		}

		/**
		 *	How many archives of one kind the project keeps: the number config.php
		 *	names - a number, or a text that is one - up to KEEP_MAX, and the
		 *	default where it names none or less than one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					'daily' or 'manual'
		 *
		 *	@return 	int
		 */
		private static function _keep( array &$appData, string $kind ): int {

			$value = $appData['/nino/admin/backups-'. $kind] ?? null;

			if( is_numeric( $value ) === false || (int) $value < 1 )
				return self::KEEP_DEFAULT;

			return min( (int) $value, self::KEEP_MAX );
		}

		/**
		 *	Keep the archive that was just written, and the newest of the others
		 *	up to $daily dated backups and $manual archives of now() in all, and
		 *	delete the rest. Only ever called after a backup was made, and never
		 *	deletes that one - a name from a clock that was ahead and was set back
		 *	is later than the new one and would otherwise push it out: a name
		 *	later than the new archive's counts as older than every other, so it
		 *	is the first to go once there are more than the project keeps. Only
		 *	ever touches files whose name is exactly a plain "Y-m-d.php" date or a
		 *	"Y-m-d-His.php" one - the panel's own Admin::_safetySnapshot() also
		 *	writes into this same directory (differently named,
		 *	"pre-restore-<timestamp>.php", kept by its own bound) and must never
		 *	be swept up here
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *	@param		int				$daily				How many daily archives to keep
		 *	@param		int				$manual				How many archives made by hand to keep
		 *	@param		string		$written			File name of the archive just written
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir, int $daily, int $manual, string $written ): void {

			// Read, not globbed (see RotatingLog::prune()); the name carries
			// the date and the time, so sorting it sorts by age
			$days 	= [];
			$hands	= [];
			foreach( @scandir( $dir ) ?: [] as $name ) {
				if( preg_match( '/^\d{4}-\d{2}-\d{2}\.php$/', $name ) === 1 )
					$days[] = $name;
				else if( preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}\.php$/', $name ) === 1 )
					$hands[] = $name;
			}

			foreach( [ [ $days, $daily, date( 'Y-m-d' ). '.php' ], [ $hands, $manual, date( 'Y-m-d-His' ). '.php' ] ] as [ $names, $keep, $today ] ) {

				// Of the kind just written the new one stays whatever its name, and
				// with it the newest of the others; of the other kind, the newest
				$mine 	= in_array( $written, $names, true );
				$others = array_values( array_diff( $names, [ $written ] ) );
				rsort( $others );

				// Later than the new one, or than now: older than all the rest
				$later 	= array_filter( $others, static fn( string $name ): bool => $name > ( $mine === true ? $written : $today ) );
				$others = array_merge( array_diff( $others, $later ), $later );

				foreach( array_slice( $others, $mine === true ? $keep - 1 : $keep ) as $name )
					@unlink( $dir. '/'. $name );
			}
		}
	}
}
