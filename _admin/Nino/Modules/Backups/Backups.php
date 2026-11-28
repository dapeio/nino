<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Backups	The daily encrypted snapshot - the module's runtime half,
 *											taken by the first call of the day: the workbench's guard
 *											makes it on every guarded request (see \Nino\Admin\Admin::guard()),
 *											and a feature's surface outside the workbench may call
 *											maybeRun() on purpose. Restoring one is the
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
	 *											write to at runtime - made by the first call of the day, which
	 *											the workbench's guard makes on every guarded request (see
	 *											\Nino\Admin\Admin::guard()) and a feature's surface outside the
	 *											workbench makes on purpose, not a cron
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
	 *											- AES-256-GCM encrypted payload, in chunks of 256 KiB that
	 *											  each carry their own tag (see \Nino\Backup\Seal), so
	 *											  raw filesystem access to the file alone still isn't
	 *											  enough without the key, and an archive of any size is
	 *											  written and opened with the memory of a chunk
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

		// What an archive made by hand is called after its date and time, so that
		// the name says how it came to be. The archives that were made before
		// there was a suffix - "Y-m-d-His" alone - are read as made by hand all
		// the same, and nothing renames them: that reading is the optional group
		// of the patterns that name an archive (here, in _prune() and retention(),
		// and in the panel's ID_PATTERN, dates() and UPLOAD_NAME_PATTERN). It is
		// to be removed when no archive of the older name is left anywhere an
		// upload could bring it from
		public const string MANUAL_SUFFIX = '-manual';

		// What a note, the archive key's copy and an archive begin and end with: the stub that ends a request for the
		// file, defined once by the kernel (\Nino\Backup\Seal), which a page request has as well as a request of the
		// workbench: missed() and stopped() are for a feature that reports from a page request too
		public const string STUB_PREFIX = \Nino\Backup\Seal::STUB_PREFIX;
		public const string STUB_SUFFIX = \Nino\Backup\Seal::STUB_SUFFIX;

		// The note of the day's one attempt (see maybeRun()), in the archive
		// directory behind the same exit-stub as an archive. Its name is no
		// date, so no list offers it and no sweep touches it
		public const string ATTEMPT_FILE = 'attempt.php';

		// The record of the run that is going on (see _begin()), in the same
		// place and behind the same stub. The run holds its lock for as long as
		// it lives, so a record that is not locked is the one of a run that
		// ended without a word - what stopped() reads for the panel. Its name
		// is no date either
		private const string RUN_FILE = 'run.php';

		// What _create() holds at its peak is a few chunks of the archive (see \Nino\Backup\Seal::pieces()): the sources are packed
		// and compressed in pieces, and the file is written in the chunks it is sealed in, so no size of the sources is a size of
		// the memory. What does lie whole in memory is an archive of format 1, which is read and no longer written (openShort())
		//
		// Room for the code and the pieces an archive of format 1 is read in, on top of the archive itself
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
		 *
		 *	Called by the workbench's guard (\Nino\Admin\Admin::guard()) on every
		 *	guarded request, and on purpose by a feature whose surface lies outside
		 *	the workbench, after \Nino\Auth::guard() let the request in: the public
		 *	guard starts no backup of its own, and a site whose owner never opens the
		 *	workbench would have none. The first call of a day makes the archive, so
		 *	no cron job is needed. The call needs the kernel and this module, not the
		 *	workbench's shell (the line of the log is written by
		 *	\Nino\Activity::write()): a request that never loaded the shell makes the
		 *	same archive and the same line. A feature asks class_exists() first: a
		 *	delivery may drop this module.
		 *
		 *	What a call costs where today's archive is there: the two lock files
		 *	(this class's and the one of config.php's key check), a read of
		 *	config.php and of the key's copy, and no write. The call never waits for
		 *	a restore, which holds the lock for as long as it runs: it returns at
		 *	once and the next request makes the backup. Failures never interrupt the
		 *	request that triggered this (backup is a safety net, not something that
		 *	should itself become a new way to break a page) - raised as a notice,
		 *	never thrown.
		 *
		 *	The line "Backup created" names the signed-in account; a request with no
		 *	account makes the archive and writes no line.
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
				// that dies in it is the only one it costs.
				//
				// One more attempt after a file that could not be read or that changed
				// while it was packed (see \Nino\Backup::SOURCE_FAILED): what ended that
				// run was a moment of the project and not the request, and the note of the
				// retry is on disk before it starts, as the first one's was, so a retry
				// that dies or fails is the last of the day
				$retry = false;

				if( self::_attempted( $dir, $today ) === true ) {

					$note = self::_readAttempt( $dir );

					if( $note === null || ( $note['code'] ?? '' ) !== 'source' || ( $note['retried'] ?? false ) === true )
						return;

					$retry = true;
				}

				self::_noteAttempt( $dir, $today, '', '', '', $retry );

				$run = self::_begin( $dir, 'daily' );

				$cause = null;

				try {
					self::_create( $appData, $dir, $path, $run );
				} catch( \Throwable $e ) {
					$cause = self::_cause( $e );
					self::_noteAttempt( $dir, $today, self::_reason( $e ), self::_code( $e ), (string) ( \Nino\Backup::sourceName( $e ) ?? '' ), $retry );
					throw $e;
				} finally {
					self::_end( $run, $cause );
				}

				@unlink( $dir. '/'. self::ATTEMPT_FILE );
				self::_prune( $dir, self::_keep( $appData, 'daily' ), self::_keep( $appData, 'manual' ), basename( $path ) );

				// Through the kernel's writer, which needs nothing of the workbench's
				// shell: this call is made from a request outside the workbench as
				// well, where \Nino\Admin\Admin is not loaded, and a class that is
				// not there would turn a backup that just succeeded into "Backup
				// failed" inside the catch below. The writer takes no line where the
				// Logs module is not delivered and never throws
				$user = \Nino\Auth::getCurrentUser( $appData );
				if( $user !== false )
					\Nino\Activity::write( $appData, $user['mail'], 'Backup created' );

			} catch( \Throwable $e ) {
				trigger_error( 'Backup failed: '. $e->getMessage() );
			} finally {
				if( $locked === true )
					\Nino\Filesystem::unlockFile( $appData, self::LOCK_PATH );
			}
		}

		/**
		 *	Write one archive now, on request - the Backups panel's "Back up
		 *	now". An archive of its own, named by date and time and marked as
		 *	made by hand, beside the daily one and never in place of it: today's
		 *	Y-m-d.php is the state before the day's work (see maybeRun()), and a
		 *	click at five in the afternoon must not replace it. Kept like the daily ones - the same
		 *	way - and only the newest ones, as many as the project keeps.
		 *
		 *	Under the lock of the daily run and of a restore (not waited for): two
		 *	clicks at once make one archive and answer the other with a 409, and a
		 *	restore is never in the middle of a backup. _bootstrap() serialises itself
		 *	under config.php's lock. The activity log line is the panel's (see
		 *	Admin::log()), not written here
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|array						The new archive's id ("Y-m-d-His-manual"), or [ http status, message ] - and, where a write failed, the code the panel words it by as a third element
		 */
		public static function now( array &$appData ): string|array {

			if( ( $appData['/nino/admin/backups'] ?? true ) === false )
				return [ 409, 'backups are switched off' ];

			// The lock of the daily run and of a restore, not waited for: a second "Back up now", a restore and a run of the day
			// never go on at the same time, and the one that finds it taken is told so. The requests of the workbench that find it
			// taken skip the daily run (see maybeRun()), which is what they do while a restore runs
			if( \Nino\Filesystem::lockFile( $appData, self::LOCK_PATH, false ) === false )
				return [ 409, 'a backup or a restore is running', 'backups_busy' ];

			$run = null;

			try {

				self::_bootstrap( $appData );

				\Nino\Filesystem::forceDir( $appData, self::BACKUPS_DIR );

				$dir 		= self::dir( $appData );
				$id 		= date( 'Y-m-d-His' ). self::MANUAL_SUFFIX;
				$run 		= self::_begin( $dir, 'manual', ( $appData['./nino/backups/answer'] ?? PHP_SAPI !== 'cli' ) === true );
				$cause 	= null;

				try {
					self::_create( $appData, $dir, $dir. '/'. $id. '.php', $run );
				} catch( \Throwable $e ) {
					$cause = self::_cause( $e );
					throw $e;
				} finally {
					self::_end( $run, $cause );
				}

				// An archive that was made is the way out of a day that went
				// without one: the next request may try the daily one again
				@unlink( $dir. '/'. self::ATTEMPT_FILE );
				self::_prune( $dir, self::_keep( $appData, 'daily' ), self::_keep( $appData, 'manual' ), $id. '.php' );

				return $id;

			} catch( \RuntimeException | \PharException $e ) {

				if( $e->getCode() === \Nino\Backup::DISK_SHORT && $run instanceof \stdClass )
					return [ 507, $e->getMessage(), 'backups_disk', $run->short ];

				// A feature that could not hand over the copy of its data: named by the run, in the sentence of the panel as well
				if( $e->getCode() === \Nino\Backup::HOOK_FAILED && $run instanceof \stdClass && $run->hook !== '' )
					return [ 500, $e->getMessage(), 'backups_hook', [ $run->hook ] ];

				// A file of the project that could not be read, or that changed while it was packed: named in the sentence of the panel
				if( self::_code( $e ) === 'source' )
					return [ 500, $e->getMessage(), 'backups_source', [ (string) \Nino\Backup::sourceName( $e ) ] ];

				return self::_code( $e ) === 'write' ? [ 500, $e->getMessage(), 'backups_write' ] : [ 500, $e->getMessage() ];
			}
			finally {
				\Nino\Filesystem::unlockFile( $appData, self::LOCK_PATH );
			}
		}

		/**
		 *	Why today's automatic backup is missing, while it is: the attempt
		 *	maybeRun() made today failed or was cut short, and no archive of
		 *	the day exists. Shared by the Backups panel, which says so
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							[ 'reason' => what the attempt reported, '' where it ended without a word, 'code' => 'write' or 'source' where the panel words the reason, 'file' => the name of the file of a 'source', 'retry' => whether the next request makes one more attempt ], or null
		 */
		public static function missed( array &$appData ): ?array {

			$dir = self::dir( $appData );

			if( is_file( $dir. '/'. date( 'Y-m-d' ). '.php' ) === true )
				return null;

			$note = self::_readAttempt( $dir );

			// A request that ended in a hook left no word of its own: the record of the run knows which feature it was asking
			if( $note !== null && $note['reason'] === '' ) {

				$run = self::stopped( $appData );

				if( $run !== null && $run['kind'] === 'daily' && $run['feature'] !== '' )
					$note['reason'] = 'the request ended while the feature "'. $run['feature']. '" handed over its data';
			}

			return $note === null || $note['date'] !== date( 'Y-m-d' ) ? null : [ 'reason' => $note['reason'] ]
				+ ( isset( $note['code'] ) === true ? [ 'code' => $note['code'] ] : [] )
				+ ( ( $note['code'] ?? '' ) === 'source' ? [ 'file' => $note['file'] ?? '', 'retry' => ( $note['retried'] ?? false ) !== true ] : [] );
		}

		/**
		 *	The run that did not end, while there is one: the last run that began
		 *	- the daily one or one by hand - and that php or the server ended
		 *	before it was over, which no catch sees, and which nobody else
		 *	reports. It says when it began, how many files and how many bytes it
		 *	had to pack and, where php said so, what ended it: the time limit or
		 *	the memory limit, with the limit - and the feature it was asking for
		 *	a copy of its data, where that was what it was doing. A run that is going on is none of
		 *	this (it holds the lock of its record), and one that ended by itself
		 *	has removed its record. Shared by the Backups panel, which says so
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							[ 'kind' => 'daily'|'manual', 'started' => unix time, 'files' => int, 'bytes' => int, 'cause' => 'time'|'memory'|'error'|'disk'|'hook'|'' (what ended it, '' where nothing says), 'limit' => seconds or bytes of that limit, 0 where there is none, 'feature' => the key of the feature that was asked for its data when it ended, '' where none was ], or null
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
				'cause' 	=> in_array( $note['cause'] ?? null, [ 'time', 'memory', 'error', 'disk', 'hook' ], true ) === true ? $note['cause'] : '',
				'limit' 	=> is_int( $note['limit'] ?? null ) === true ? $note['limit'] : 0,
				'feature'	=> is_string( $note['feature'] ?? null ) === true && preg_match( '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $note['feature'] ) === 1 ? $note['feature'] : '',
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
		 *	@return 	array|null							[ 'date' => "Y-m-d", 'reason' => string ] and, where the attempt reported one, 'code' ('write' or 'source'), the 'file' of a 'source' and whether it was a 'retried' attempt; or null where there is none or it is not one
		 */
		private static function _readAttempt( string $dir ): ?array {

			$note = self::_readNote( $dir. '/'. self::ATTEMPT_FILE );

			if( $note === null || is_string( $note['date'] ?? null ) === false || is_string( $note['reason'] ?? null ) === false )
				return null;

			$code = in_array( $note['code'] ?? null, [ 'write', 'source' ], true ) === true ? $note['code'] : '';

			return [ 'date' => $note['date'], 'reason' => $note['reason'] ]
				+ ( $code !== '' ? [ 'code' => $code ] : [] )
				+ ( $code === 'source' ? [ 'file' => is_string( $note['file'] ?? null ) === true ? $note['file'] : '' ] : [] )
				+ ( ( $note['retried'] ?? null ) === true ? [ 'retried' => true ] : [] );
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

			if( is_string( $raw ) === false || str_starts_with( $raw, self::STUB_PREFIX ) === false || str_ends_with( $raw, self::STUB_SUFFIX ) === false )
				return null;

			$json = base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ), true );
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
		 *	@param		string		$code					What kind of failure it was, where the panel words it: 'write', 'source', '' otherwise
		 *	@param		string		$file					The name in the archive of the file that could not be read, for the code 'source'
		 *	@param		bool			$retried			Whether this is the one more attempt that a failure of the code 'source' allows, the last of the day
		 *
		 *	@return 	void
		 */
		private static function _noteAttempt( string $dir, string $date, string $reason, string $code = '', string $file = '', bool $retried = false ): void {

			self::_writeRawAtomic( $dir. '/'. self::ATTEMPT_FILE, self::STUB_PREFIX. base64_encode( (string) json_encode( [ 'date' => $date, 'reason' => $reason ] + ( $code === '' ? [] : [ 'code' => $code ] ) + ( $code === 'source' ? [ 'file' => $file ] : [] ) + ( $retried === true ? [ 'retried' => true ] : [] ) ) ). self::STUB_SUFFIX );
		}

		/**
		 *	The kind of failure the panel words in the interface language: a write
		 *	that failed, with no room or a file that cannot be written, or a file of
		 *	the project that could not be read or that changed while it was packed
		 *
		 *	@param		\Throwable	$e
		 *
		 *	@return 	string									'write', 'source', or '' for any other
		 */
		private static function _code( \Throwable $e ): string {

			if( get_class( $e ) !== \RuntimeException::class )
				return '';

			return match( true ) {
				in_array( $e->getCode(), [ \Nino\Backup::WRITE_FAILED, \Nino\Backup::DISK_SHORT ], true ) => 'write',
				$e->getCode() === \Nino\Backup::SOURCE_FAILED => 'source',
				default => '',
			};
		}

		/**
		 *	What ended a run that failed on its own, as the record of it says: 'disk' where a write failed, nothing else
		 *
		 *	@param		\Throwable	$e
		 *
		 *	@return 	string|null
		 */
		private static function _cause( \Throwable $e ): ?string {
			return get_class( $e ) === \RuntimeException::class && $e->getCode() === \Nino\Backup::WRITE_FAILED ? 'disk' : null;
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
		 *	cannot lower the limit of the process that runs it. Asked by openShort() alone
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
		 *	The archive key as the person who installs the project is to keep it, made now where the project has
		 *	none yet: the setup wizard's last step shows it once (see \Nino\Install\Finish::apiComplete()). It is
		 *	the key _bootstrap() makes on the first request of the workbench, and that one leaves a key
		 *	that is there alone, so what is shown is what the archives are made with
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string|false							The key, base64 of 32 bytes, or false where it could not be made or written
		 */
		public static function recoveryKey( array &$appData ): string|false {

			if( \Nino\Filesystem::lockFile( $appData, self::LOCK_PATH, false ) === false )
				return false;

			try {
				self::_bootstrap( $appData );
			}
			catch( \RuntimeException ) {
				return false;
			}
			finally {
				\Nino\Filesystem::unlockFile( $appData, self::LOCK_PATH );
			}

			$key = $appData['/nino/backup/key'] ?? null;

			return is_string( $key ) === true && strlen( (string) base64_decode( $key, true ) ) === 32 ? $key : false;
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
			$keyContent = self::STUB_PREFIX. $appData['/nino/backup/key']. self::STUB_SUFFIX;

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
		private static function _begin( string $dir, string $kind, bool $answer = false ): \stdClass {

			$run 				= new \stdClass();
			$run->done 	= false;
			$run->path 	= $dir. '/'. self::RUN_FILE;
			$run->note 	= [ 'kind' => $kind, 'started' => time() ];
			$run->temp 	= '';
			$run->lease = null;
			$run->partial = '';
			$run->short = [];
			$run->hook 	= '';
			$run->handle = @fopen( $run->path, 'c+b' );

			if( $run->handle !== false && flock( $run->handle, LOCK_EX | LOCK_NB ) === false ) {
				fclose( $run->handle );
				$run->handle = false;
			}

			self::_record( $run, [] );

			register_shutdown_function( static function() use ( $run, $answer ): void {

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
					else if( preg_match( '/could not be written|No space left on device|errno=28|Disk quota exceeded/i', $error['message'] ) === 1 )
						$cause = [ 'cause' => 'disk' ];
				}

				// A request that ended in a hook: the record says which feature it was asking, and what ended it was the hook
				if( $run->hook !== '' && ( $cause === [] || $cause['cause'] === 'error' ) )
					$cause = [ 'cause' => 'hook' ];

				self::_record( $run, $cause );
				self::_dropTemp( $run );

				// Where nothing is sent yet, whoever asked for the backup by hand is told in the words of the panel and not by an empty answer: this is
				// the end of a request that has no response left to set
				if( $run->hook !== '' && $answer === true && headers_sent() === false ) {

					while( ob_get_level() > 0 )
						@ob_end_clean();

					http_response_code( 500 );
					header( 'Content-Type: application/json; charset=utf-8' );
					echo json_encode( [ 'error' => 'the backup ended while a feature handed over its data', 'code' => 'backups_hook', 'params' => [ $run->hook ] ] );
				}

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
			fwrite( $run->handle, self::STUB_PREFIX. base64_encode( (string) json_encode( $run->note ) ). self::STUB_SUFFIX );
			fflush( $run->handle );
		}

		/**
		 *	End a run that ends by itself: its record goes, and its temporary files. Where the run ended because a write failed - a disk
		 *	that is full - the record stays and says so ('disk'), as it does for a run that php ended: the person who looks at the
		 *	panel later is told what ended it, and the next run that succeeds takes the record away
		 *
		 *	@param		\stdClass	$run					See _begin()
		 *	@param		string|null	$cause				'disk', or null for a run that ended by itself in order
		 *
		 *	@return 	void
		 */
		private static function _end( \stdClass $run, ?string $cause = null ): void {

			$run->done = true;

			if( is_resource( $run->handle ) === true ) {

				if( $cause !== null )
					self::_record( $run, [ 'cause' => $cause ] );
				else
					@unlink( $run->path );

				fclose( $run->handle );
			}

			self::_dropTemp( $run );
		}

		/**
		 *	Remove the temporary files of a run - the file an archive is being written to among them - and let go of the lock of the
		 *	first of them
		 *
		 *	@param		\stdClass	$run					See _begin()
		 *
		 *	@return 	void
		 */
		private static function _dropTemp( \stdClass $run ): void {

			// The archive that was being written, which only a rename makes one
			if( $run->partial !== '' ) {
				@unlink( $run->partial );
				$run->partial = '';
			}

			if( $run->temp === '' )
				return;

			// The copies the features handed over, in the folder the run made for them beside its file
			\Nino\Filesystem::removeDir( $run->temp. '.staging' );

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
		 *	made: the manifest - every file of the project the archive carries,
		 *	the data a feature owns among them - the number of files and of
		 *	bytes. A file the manifest names that cannot be read ends the run here, as a file of the project that has gone (see \Nino\Backup::SOURCE_FAILED), unless it is one that a
		 *	backup leaves out where it is gone (see \Nino\Backup::leavable()): those are not counted
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array|null	$given				The manifest after the features handed over their copies (see \Nino\Backup::stage()), which is measured as it is; null for the files as they lie
		 *
		 *	@return 	array										[ 'manifest' => path -> name, 'files' => int, 'bytes' => int, 'copies' => the bytes the copies of the features are still to take, 0 for a manifest that was given ]
		 *
		 *	@throws		\RuntimeException					SOURCE_FAILED, if a file of the manifest cannot be read
		 */
		private static function _plan( array &$appData, ?array $given = null ): array {

			$manifest = $given ?? \Nino\Backup::manifest( $appData );
			$kinds		= \Nino\Backup::leavable( $appData, $manifest );
			$bytes 		= 0;
			$files		= 0;

			foreach( $manifest as $absolute => $archiveName ) {

				$size = @filesize( $absolute );

				if( $size === false ) {

					if( isset( $kinds[$archiveName] ) === true )
						continue;

					throw \Nino\Backup::sourceFailure( $archiveName );
				}

				$bytes += $size;
				$files++;
			}

			return [ 'manifest' => $manifest, 'files' => $files, 'bytes' => $bytes, 'copies' => $given === null ? \Nino\Backup::copyBytes( $appData, $manifest ) : 0 ];
		}

		/**
		 *	Tar+gzip every file the admin panel can write to at runtime and,
		 *	after them, the note of the state they come from (see
		 *	\Nino\Backup::stamp()), seal it in chunks (see \Nino\Backup\Seal),
		 *	and write it out behind the .php exit-stub. Built via PharData rather
		 *	than shelling out to `tar`/`gzip` - pure PHP, and unlike the
		 *	executable Phar class, PharData isn't restricted by the phar.readonly
		 *	ini setting many hosts enable by default.
		 *
		 *	The files are packed in one pass (see \Nino\Backup::pack()), which
		 *	reads each in pieces - PharData copies a file of any size from stream
		 *	to stream - and the compressed archive is read in chunks to be sealed,
		 *	so what a run holds in memory does not grow with the sources. What it
		 *	needs is room on the disk, about twice the bytes of the sources, so a
		 *	disk with less free is refused before anything is packed, with the
		 *	sizes in the message
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
				throw new \RuntimeException( 'backup directory could not be created', \Nino\Backup::WRITE_FAILED );

			// What an earlier run left that ended without a word
			\Nino\Backup::sweepTemp( $appData );
			self::_sweepPartial( $dir );

			$key = base64_decode( (string) $appData['/nino/backup/key'], true );
			if( is_string( $key ) === false || strlen( $key ) !== 32 )
				throw new \RuntimeException( 'backup encryption key is invalid' );

			$plan = self::_plan( $appData );

			self::_record( $run, [ 'files' => $plan['files'], 'bytes' => $plan['bytes'] ] );

			// What packing needs of the disk at the most (see \Nino\Backup::spaceNeed(), measured): twice the bytes of the files in the
			// temporary directory, and where the archive is written to the same disk, more. A disk that has less is refused with the
			// sizes before anything is made; a disk that fills up all the same - another request writes to it - ends the run with the
			// notice of a write that failed, and the record of the run says 'disk'
			// The copies the features with a hook hand over lie beside the files for as long as the run does, which the first count adds
			$short = \Nino\Backup::spaceShort( $appData, $plan['bytes'], $plan['files'], $dir, $plan['copies'] );

			if( $short !== null ) {
				$run->short = $short;
				throw new \RuntimeException( sprintf( 'the backup needs about %d MB of free space for its temporary files and %d MB are free', $short[0], $short[1] ), \Nino\Backup::DISK_SHORT );
			}

			$temp = \Nino\Backup::tempFile( $appData, 'backup' );
			if( $temp === false )
				throw new \RuntimeException( 'temporary backup file could not be created', \Nino\Backup::WRITE_FAILED );

			[ $run->temp, $run->lease ] = $temp;

			// Before anything is packed, under the lock of the run: a feature that keeps a database hands over a copy of it that is whole, and
			// the run packs the copy where the live file stood. What the features wrote is counted again by what it is, and the disk is asked again
			// with those sizes - a run that ends in a hook or in a disk the copies filled leaves no archive, and the folder of the copies goes with
			// the run's other files (see _dropTemp()). The record of the run names the feature it is asking, for a request that is ended meanwhile
			$notes	= [];
			$staged = \Nino\Backup::stage( $appData, $plan['manifest'], $run->temp. '.staging', static function( string $feature ) use ( $run ): void {
				$run->hook = $feature;
				self::_record( $run, [ 'feature' => $feature ] );
			}, false, $notes );

			if( $staged !== $plan['manifest'] ) {

				$plan = self::_plan( $appData, $staged );

				self::_record( $run, [ 'files' => $plan['files'], 'bytes' => $plan['bytes'] ] );

				$short = \Nino\Backup::spaceShort( $appData, $plan['bytes'], $plan['files'], $dir );

				if( $short !== null ) {
					$run->short = $short;
					throw new \RuntimeException( sprintf( 'the backup needs about %d MB of free space for its temporary files and %d MB are free', $short[0], $short[1] ), \Nino\Backup::DISK_SHORT );
				}
			}

			$tmpTar = $run->temp. '.tar';

			\Nino\Backup::pack( $appData, $plan['manifest'], $tmpTar, $notes );

			// Sealed chunk by chunk while it is written, to a file beside the archive that a rename makes one
			self::_writeRawAtomic( $path, \Nino\Backup\Seal::pieces( $tmpTar. '.gz', $key ), $run );

			@unlink( $tmpTar. '.gz' );
		}

		/**
		 *	Remove the files a run that was ended left in the archive directory while it wrote an archive (see _writeRawAtomic()): a
		 *	temporary file that is a day old belongs to no run that goes on
		 *
		 *	@param		string		$dir					Absolute path to the backup directory
		 *
		 *	@return 	void
		 */
		private static function _sweepPartial( string $dir ): void {

			foreach( @scandir( $dir ) ?: [] as $name )
				if( preg_match( '/^(?:.+\.php\.[0-9a-f]{12}|upload-[0-9a-f]{12})\.tmp$/D', $name ) === 1 && time() - (int) @filemtime( $dir. '/'. $name ) > 86400 )
					@unlink( $dir. '/'. $name );
		}

		// Raw (non-serialized) counterpart to Filesystem's atomic writer. Backup
		// blobs and the restore-key stub are valid PHP source strings already;
		// passing either through putFileContent() would var_export them instead.
		// The content is a string or the pieces of one, in order, so an archive
		// need not exist as a single string next to its parts. The file is written
		// under a name of its own and renamed: a run that ends in the middle of it
		// leaves no archive, and the file it leaves is the one a run's shutdown
		// removes ($run) or the next run of the day sweeps
		private static function _writeRawAtomic( string $path, string|iterable $content, ?\stdClass $run = null ): void {

			$temp = $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );

			if( $handle === false )
				throw new \RuntimeException( 'temporary file could not be opened: '. basename( $path ), \Nino\Backup::WRITE_FAILED );

			if( $run !== null )
				$run->partial = $temp;

			try {

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
				$handle	 = null;

				// What is on the disk must be what was written: a write that was cut short
				// is not an archive, and nothing is renamed into its place
				clearstatcache( true, $temp );

				if( $written === false || $written !== $expected || $flushed === false || $closed === false || @filesize( $temp ) !== $expected )
					throw new \RuntimeException( 'file could not be written: '. basename( $path ), \Nino\Backup::WRITE_FAILED );

				$mode = @fileperms( $path );
				if( $mode !== false )
					@chmod( $temp, $mode & 0777 );

				if( @rename( $temp, $path ) === false )
					throw new \RuntimeException( 'file could not be replaced: '. basename( $path ), \Nino\Backup::WRITE_FAILED );

				if( $run !== null )
					$run->partial = '';
			}
			catch( \Throwable $e ) {

				if( is_resource( $handle ) === true )
					fclose( $handle );

				@unlink( $temp );

				if( $run !== null )
					$run->partial = '';

				throw $e;
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
		 *	"Y-m-d-His-manual.php" one (or "Y-m-d-His.php", which an archive made by
		 *	hand was called before the suffix) - the panel's own Admin::_safetySnapshot() also
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
				else if( preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}(?:'. self::MANUAL_SUFFIX. ')?\.php$/', $name ) === 1 )
					$hands[] = $name;
			}

			foreach( [ [ $days, $daily, date( 'Y-m-d' ). '.php', 10 ], [ $hands, $manual, date( 'Y-m-d-His' ). '.php', 17 ] ] as [ $names, $keep, $today, $length ] )
				foreach( self::_doomed( $names, $keep, $written, $today, $length ) as $name )
					@unlink( $dir. '/'. $name );
		}

		/**
		 *	Which archives of one kind a run that wrote $written deletes: of the
		 *	kind just written the new one stays whatever its name, and with it the
		 *	newest of the others; of the other kind, the newest. A name is compared
		 *	by its date and time, the first $length characters, so that a suffix
		 *	decides nothing about its age
		 *
		 *	@param		string[]	$names				File names of the archives of the kind
		 *	@param		int				$keep					How many of the kind are kept
		 *	@param		string		$written			File name of the archive just written, or one of the other kind
		 *	@param		string		$today				What "now" is called as a file name of the kind
		 *	@param		int				$length				How many characters of a name are its date and time
		 *
		 *	@return 	string[]								The file names to delete
		 */
		private static function _doomed( array $names, int $keep, string $written, string $today, int $length ): array {

			$mine 	= in_array( $written, $names, true );
			$others = array_values( array_diff( $names, [ $written ] ) );
			rsort( $others );

			// Later than the new one, or than now: older than all the rest
			$limit	= substr( $mine === true ? $written : $today, 0, $length );
			$later 	= array_filter( $others, static fn( string $name ): bool => substr( $name, 0, $length ) > $limit );
			$others = array_merge( array_diff( $others, $later ), $later );

			return array_slice( $others, $mine === true ? $keep - 1 : $keep );
		}

		/**
		 *	Whether the next backup that succeeds deletes this archive: it is not
		 *	among the newest of its kind the project keeps. The panel says so after
		 *	an upload, since an old archive that is put back counts in its kind like
		 *	any other and is the first to go. A clock that was ahead counts as in
		 *	_prune(): such a name is older than the rest
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id						An id of a daily archive or of one made by hand
		 *
		 *	@return 	array										[ 'drops' => bool, 'keep' => how many of its kind the project keeps ]
		 */
		public static function retention( array &$appData, string $id ): array {

			$manual	= preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}/', $id ) === 1;
			$names 	= [];

			foreach( @scandir( self::dir( $appData ) ) ?: [] as $name )
				if( preg_match( $manual === true ? '/^\d{4}-\d{2}-\d{2}-\d{6}(?:'. self::MANUAL_SUFFIX. ')?\.php$/' : '/^\d{4}-\d{2}-\d{2}\.php$/', $name ) === 1 )
					$names[] = $name;

			$keep = self::_keep( $appData, $manual === true ? 'manual' : 'daily' );

			// Nothing was written: the first $keep of the kind stay
			return [ 'drops' => in_array( $id. '.php', self::_doomed( $names, $keep, '', date( $manual === true ? 'Y-m-d-His' : 'Y-m-d' ). '.php', $manual === true ? 17 : 10 ), true ), 'keep' => $keep ];
		}

		/**
		 *	What opening an archive of format 1 of this size costs in memory beyond what is asked for already, and whether the
		 *	limit carries it. Such an archive is read whole and decrypted whole (see \Nino\Backup\Seal::open()): its ciphertext
		 *	and its plain bytes are in memory together, about one and a half times the file, which is base64. An archive of
		 *	format 2 is opened chunk by chunk and costs no more than a chunk, so the callers ask for format 1 only
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$bytes				Size of the archive file
		 *
		 *	@return 	array|null							null where it fits, else [ megabytes needed, megabytes the limit is ]
		 */
		public static function openShort( array &$appData, int $bytes ): ?array {

			$limit = self::_limit( $appData );

			if( $limit <= 0 )
				return null;

			$need = memory_get_usage() + (int) ( $bytes * 1.5 ) + self::PEAK_OVERHEAD;

			return $need > $limit ? [ (int) ceil( $need / 1048576 ), (int) floor( $limit / 1048576 ) ] : null;
		}

		/**
		 *	Whether the disk has the room a restore of this archive needs, before anything is opened: the plain archive and the files
		 *	it is unpacked to lie in the temporary directory next to each other - at least twice the plain archive, which is three
		 *	quarters of the file where it does not compress - and the safety snapshot of what the project holds needs what a backup
		 *	needs (see \Nino\Backup::spaceNeed()) on top, since the two others are still there while it is made
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$bytes				Size of the archive file
		 *
		 *	@return 	array|null							null where it fits or the system does not say, else [ megabytes needed, megabytes free ]
		 */
		public static function restoreShort( array &$appData, int $bytes ): ?array {

			$manifest = \Nino\Backup::manifest( $appData );
			$have 		= 0;

			foreach( array_keys( $manifest ) as $absolute )
				$have += (int) @filesize( $absolute );

			$temp 		= sys_get_temp_dir();
			$dir 			= self::dir( $appData );
			$together = ( $a = @stat( $temp ) ) !== false && ( $b = @stat( is_dir( $dir ) === true ? $dir : dirname( $dir ) ) ) !== false && $a['dev'] === $b['dev'];
			$need 		= 2 * (int) ceil( $bytes * 3 / 4 ) + \Nino\Backup::spaceNeed( $have, count( $manifest ), $together, \Nino\Backup::copyBytes( $appData, $manifest ) );
			$free 		= \Nino\Backup::freeSpace( $appData, $temp );

			if( $free !== null && $free < $need )
				return [ (int) ceil( $need / 1048576 ), (int) floor( max( 0, $free ) / 1048576 ) ];

			// The snapshot is written to the directory of the archives, which is a disk of its own where it is not the temporary one
			return $together === false ? \Nino\Backup::archiveShort( $appData, $have, $dir ) : null;
		}
	}
}
