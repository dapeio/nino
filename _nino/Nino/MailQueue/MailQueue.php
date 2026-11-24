<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	MailQueue						The outbox of \Nino\Mail: what is kept, when it is tried again, what it gave up on
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	MailQueue is the storage and the clock of \Nino\Mail::queue(), and nothing a feature calls: a feature queues a
	 *	mail with \Nino\Mail::queue() and reads the outbox with \Nino\Mail::queued(). The methods are public only
	 *	because \Nino\Mail is the one class that uses them, and the class is loaded only where a mail is queued or
	 *	is due - a request of a site with an empty outbox, or with mails that wait for a time to come, never loads it
	 *	(see \Nino\Http::prepareAfter()).
	 *
	 *	Four files below /data/, kept out of every backup and of every restore (see \Nino\Backup::OUTBOX). The three that
	 *	hold mails or keys are php files that a webserver which serves them by mistake runs and gets nothing from - the one
	 *	statement of the file, `<?php return '...';`, returns the json it holds - and they are DATA, as every file of /data/
	 *	is: read from the disk with file_get_contents() and decoded here (decode()), and never loaded with include. A
	 *	second process - the cron command beside the webserver - writes them, and the compiled copy of a script that the
	 *	process of the webserver keeps cannot be invalidated by it: it would be the old outbox. Written under the lock of
	 *	the file, which every writer takes (lockFile(), read, putFileContent(), the sequence mutate() is made of).
	 *
	 *	WAITING, '/data/mail-queue.php': { format, waiting: [ mail, ... ] }. A mail is { id, key, content, kind, to,
	 *	subject, body, replyTo, queued, tries, next, error, claim, until }. The file is removed when the last mail leaves
	 *	it, so that the check of every request - whether the file is there - is a question of one stat.
	 *
	 *	DUE, '/data/mail-queue-due.txt': the second at which something is due, as the digits of a number, written after
	 *	every write of WAITING, from what the file holds, under its lock. What every request asks before its response,
	 *	instead of reading the outbox. It holds no mail, only a time, and is no php. A note that is missing, that is no
	 *	number, that lies further away than any wait can, or that is older than the file, says 'look' (see
	 *	\Nino\Http::_mailDue()): the outbox is read once and the note is made right.
	 *
	 *	KEYS, '/data/mail-keys.php': { format, keys: [ { key, id, content, at }, ... ] }. The keys of the mails that were
	 *	delivered in the last KEY_MEMORY seconds, as hashes, so that a request that comes twice - a double click - is the
	 *	mail that went out and not a second one. Nothing of the mail is in it.
	 *
	 *	FAILED, '/data/mail-failed.php': { format, failed: [ record, ... ] }. What a mail came to that was given up on -
	 *	no address, no subject, no body, no key, only { id, kind, queued, failed, tries, error } - for KEEP_FAILED seconds.
	 *
	 *	A mail is delivered at least once. A deliverer takes one mail at a time: the attempt is counted, the next one set and
	 *	the lease of the mail written - and read back - before the transport is asked (claim()), so that a process that dies in
	 *	the transport leaves a mail that is tried again after the wait it would have had after a failure, and a mail that
	 *	could not be written down is not sent at all. The windows in which a mail can go out twice are two: between the
	 *	transport's answer and settle(), and a transport that takes longer than LEASE seconds, which lets a second
	 *	deliverer take the mail while the first is still in it.
	 *
	 *	Every file is one a person can edit: nothing read from one is trusted (see _state()).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	final class MailQueue {

		// Where the mails wait - the path the kernel's check of every request names, see \Nino\Http::MAIL_QUEUE
		public const string WAITING = \Nino\Http::MAIL_QUEUE;

		// Where the keys of the mails that went out are kept for a while
		public const string KEYS = '/data/mail-keys.php';

		// How long the key of a delivered mail is remembered, in seconds - ten minutes - and how many keys at most
		public const int KEY_MEMORY = 600;
		public const int MAX_KEYS = 200;

		// The longest the outbox waits from now to anything, in seconds: the longest wait of BACKOFF and a lease. A note that says more
		// is none, see \Nino\Http::MAIL_NOTE_SPAN
		public const int SPAN = 43500;

		// Where the second of the next due mail is kept, see \Nino\Http::MAIL_QUEUE_DUE
		public const string DUE = \Nino\Http::MAIL_QUEUE_DUE;

		// Where the mails are that were given up on
		public const string FAILED = '/data/mail-failed.php';

		// Where a file that cannot be read is put aside, so that the outbox starts over and a person can read what was in it
		public const string DAMAGED = '/data/mail-queue-damaged.php';
		public const string DAMAGED_FAILED = '/data/mail-failed-damaged.php';

		// How many mails may wait at once
		public const int MAX_WAITING = 100;

		// How many of them mails of a visitor (CLIENT) may be: the rest of the places is the system's
		public const int MAX_CLIENT = 60;

		// How large one mail may be: the bytes of its address, subject, reply address and body
		public const int MAX_BYTES = 65536;

		// How long a mail may wait before it is given up on, in seconds - two days
		public const int MAX_AGE = 172800;

		// How long a mail that was given up on is kept as a record, and a file that was put aside is kept, in seconds - fourteen days
		public const int KEEP_FAILED = 1209600;

		// How many records of mails given up on the file keeps; the oldest go first
		public const int MAX_FAILED = 500;

		// The seconds a mail waits after the first, second ... attempt that did not deliver it: 1 min, 5 min, 30 min, 2 h, 12 h.
		// One more attempt than there are waits is the last
		public const array BACKOFF = [ 60, 300, 1800, 7200, 43200 ];

		// How long a mail belongs to the deliverer that took it, in seconds: five minutes. The longest a transport may take is
		// what the smtp transport of the catalogue waits for a server - its timeout, 15 seconds by default, for each of the
		// ten or so steps of a dialogue - and the lease is longer than that dialogue
		public const int LEASE = 300;

		// How many mails one request delivers after its response
		public const int BATCH = 5;

		// How long a mail waits that was held back by the hourly cap of the system, in seconds
		public const int CAP_WAIT = 300;

		// The words of 'error' - what is kept of why a mail was not delivered, and never what a transport said
		public const string REFUSED = 'refused';
		public const string INVALID = 'invalid';
		public const string FAILURE = 'exception';
		public const string EXPIRED = 'expired';
		public const string ABANDONED = 'abandoned';

		/**
		 *	The clock: now, or what a test has set in './nino/mail/now' - a key of the request, which no request can send
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function now( array &$appData ): int {

			return is_int( $appData['./nino/mail/now'] ?? null ) === true ? $appData['./nino/mail/now'] : time();
		}

		/**
		 *	How many attempts a mail has in all: the first, and one after each wait
		 *
		 *	@return 	int
		 */
		public static function attempts(): int {

			return count( self::BACKOFF ) + 1;
		}

		/**
		 *	What the outbox knows before a mail is queued, read without a lock: the id and the content of the mail that carries
		 *	the key - one that waits, or one that was delivered in the last KEY_MEMORY seconds - and whether the outbox has no
		 *	place for a mail of this kind
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind
		 *	@param		string		$key					The hash of the key, '' for none
		 *
		 *	@return 	array										{ id, content, full }
		 */
		public static function peek( array &$appData, string $kind, string $key ): array {

			$state = self::_read( $appData );
			$known = self::_withKey( $state['waiting'], $key ) ?? self::_remembered( $appData, $key );

			return [ 'id' => $known['id'] ?? '', 'content' => $known['content'] ?? '', 'full' => self::_full( $state['waiting'], $kind ) ];
		}

		/**
		 *	Put a mail into the outbox
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mail					{ to, subject, body, replyTo }, cleaned and checked
		 *	@param		string		$kind					One of \Nino\Mail::CLIENT, SYSTEM, TEST
		 *	@param		string		$key					The hash of the key against a second queueing of the same mail, '' for none
		 *	@param		string		$content			The hash of what the mail is, which a second mail with the same key has to equal
		 *
		 *	@return 	array										{ id, refused } - the id of the mail that waits, or '' and why it was not taken: 'full', 'conflict' (the key is a mail that is another) or 'unwritable'
		 */
		public static function add( array &$appData, array $mail, string $kind, string $key, string $content ): array {

			$now		= self::now( $appData );
			$id			= bin2hex( random_bytes( 8 ) );
			$result	= [ 'id' => '', 'refused' => 'unwritable' ];

			// The callback answers null for a mail that is known and for a full outbox: those are answers of their own in $result,
			// and what stays 'unwritable' is a file that could not be locked or written
			self::_mutate( $appData, self::WAITING, static function( array $stored ) use ( &$appData, $mail, $kind, $key, $content, $now, $id, &$result ): ?array {

				$state = self::_state( $stored );

				// The same mail twice is one: the one that waits is the answer. Another mail under the key is refused, never
				// answered with the id of a mail it is not
				$known = self::_withKey( $state['waiting'], $key ) ?? self::_remembered( $appData, $key );

				if( $known !== null ) {
					$result = $known['content'] === $content ? [ 'id' => $known['id'], 'refused' => '' ] : [ 'id' => '', 'refused' => 'conflict' ];
					return null;
				}

				if( self::_full( $state['waiting'], $kind ) === true ) {
					$result = [ 'id' => '', 'refused' => 'full' ];
					return null;
				}

				$state['waiting'][$id] = [
					'id'			=> $id,
					'key'			=> $key,
					'content'	=> $content,
					'kind'		=> $kind,
					'to'			=> (string) $mail['to'],
					'subject'	=> (string) $mail['subject'],
					'body'		=> (string) $mail['body'],
					'replyTo'	=> (string) $mail['replyTo'],
					'queued'	=> $now,
					'tries'		=> 0,
					'next'		=> $now,
					'error'		=> '',
					'claim'		=> '',
					'until'		=> 0,
				];

				$result = [ 'id' => $id, 'refused' => '' ];

				return self::_file( $state );
			} );

			return $result;
		}

		/**
		 *	Take the mail that is due for the deliverer of $token, and give up on the ones that cannot be delivered any
		 *	more: older than MAX_AGE, out of attempts, or no mail at all. Taking the mail is counting the attempt, setting
		 *	the wait after it and writing its lease - and nothing is answered that is not on the disk, read back from
		 *	there: where the outbox cannot be written the call says so and no mail is handed out, since a mail that is sent
		 *	without a record of it is sent again by every request that follows.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				The deliverer
		 *	@param		int				$now
		 *	@param		int				&$gaveUp			(reference) How many mails this call gave up on, for the report of the caller
		 *	@param		bool			&$unwritable	(reference) Whether the outbox could not be written
		 *
		 *	@return 	array|null								The mail taken { id, kind, to, subject, body, replyTo }, null for none
		 */
		public static function claim( array &$appData, string $token, int $now, int &$gaveUp, bool &$unwritable ): ?array {

			$gaveUp			= 0;
			$unwritable	= false;
			$state			= self::_read( $appData );

			// Nothing to do is a question the file answers without a lock: the common case of a request that finds a mail that
			// is due for somebody else
			if( self::_idle( $state, $now ) === true ) {

				// A note that says due where nothing is is put right, so that the requests that follow do not come for it
				if( self::_dueSecond( $state, $now ) > $now && self::_noteSecond( $appData ) <= $now )
					self::_mutate( $appData, self::WAITING, static fn( array $stored ): array => self::_file( self::_state( $stored ) ) );

				return null;
			}

			$taken	= null;
			$given	= 0;

			$written = self::_mutate( $appData, self::WAITING, static function( array $stored ) use ( &$appData, $token, $now, &$taken, &$given ): ?array {

				$state	= self::_state( $stored );
				$mine		= null;
				$records = [];

				for( $i = 0; $i < $state['bad']; $i++ )
					$records[] = self::_record( bin2hex( random_bytes( 8 ) ), \Nino\Mail::CLIENT, $now, $now, 0, self::INVALID );

				// The id of a mail is its field, not the key it is stored under: php makes an array key of digits alone an int
				foreach( $state['waiting'] as $entry ) {

					if( $now - $entry['queued'] >= self::MAX_AGE )
						$why = self::EXPIRED;
					else if( $entry['tries'] >= self::attempts() && $entry['next'] <= $now && self::_leased( $entry, $now ) === false )
						$why = self::ABANDONED;
					else
						continue;

					$records[] = self::_record( $entry['id'], $entry['kind'], $entry['queued'], $now, $entry['tries'], $why );
					unset( $state['waiting'][ $entry['id'] ] );
				}

				$due = array_filter( $state['waiting'], static fn( array $entry ): bool => $entry['next'] <= $now && $entry['tries'] < self::attempts() && self::_leased( $entry, $now ) === false );
				uasort( $due, static fn( array $a, array $b ): int => [ $a['queued'], $a['id'] ] <=> [ $b['queued'], $b['id'] ] );

				foreach( $due as $entry ) {

					$tries = $entry['tries'] + 1;
					$state['waiting'][ $entry['id'] ]['tries']	= $tries;
					$state['waiting'][ $entry['id'] ]['next']		= $now + self::BACKOFF[ min( $tries, count( self::BACKOFF ) ) - 1 ];
					$state['waiting'][ $entry['id'] ]['claim']	= $token;
					$state['waiting'][ $entry['id'] ]['until']	= $now + self::LEASE;
					$mine = [ 'id' => $entry['id'], 'kind' => $entry['kind'], 'to' => $entry['to'], 'subject' => $entry['subject'], 'body' => $entry['body'], 'replyTo' => $entry['replyTo'] ];

					break;
				}

				// The record of what is given up on is written before the mail leaves the outbox: a process that dies in between
				// finds the mail again, and the record of an id is one
				if( $records !== [] && self::_fail( $appData, $records, $now ) === false )
					return null;

				$taken	= $mine;
				$given	= count( $records );

				return self::_file( $state );
			} );

			if( $written === false ) {

				$unwritable = $taken !== null || $given > 0 || self::_read( $appData )['waiting'] !== [];

				return null;
			}

			// Read back from the disk, not from the cache of this request: the mail is handed out when it is down there
			if( $taken !== null ) {

				$disk = self::_disk( $appData, self::WAITING );

				if( ( $disk['waiting'][ $taken['id'] ]['claim'] ?? '' ) !== $token ) {

					$unwritable = true;

					return null;
				}
			}

			$gaveUp = $given;

			return $taken;
		}

		/**
		 *	What became of a mail the deliverer took
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				The deliverer
		 *	@param		string		$id
		 *	@param		string		$outcome			'sent' - the mail leaves the outbox; 'again' - it stays for the time set when it was taken and says why in $error, unless it had its last attempt, which gives up on it; 'invalid' - it is no mail that can be sent, which gives up on it at once; 'held' - the mail was not tried, the attempt is not counted and it waits CAP_WAIT
		 *	@param		string		$error				One of the words of 'error', for 'again'
		 *	@param		int				$now
		 *
		 *	@return 	array										{ written, gaveUp } - whether the outbox says it now (a mail that is none of this deliverer's is left as it is, and that is written), and whether the mail was given up on by this call
		 */
		public static function settle( array &$appData, string $token, string $id, string $outcome, string $error, int $now ): array {

			$gaveUp	= false;
			$left		= false;

			$written = self::_mutate( $appData, self::WAITING, static function( array $stored ) use ( &$appData, $token, $id, $outcome, $error, $now, &$gaveUp, &$left ): ?array {

				$gaveUp	= false;
				$left		= false;
				$state	= self::_state( $stored );
				$entry	= $state['waiting'][$id] ?? null;

				if( $entry === null ) {
					$left = true;
					return null;
				}

				// A mail that was delivered leaves, whoever holds it by now: it has gone out
				if( $outcome === 'sent' ) {

					// The key is kept for a while, as a hash with the id: the same request again is the mail that went out
					if( $entry['key'] !== '' && self::_remember( $appData, $entry, $now ) === false )
						return null;

					unset( $state['waiting'][$id] );
				}
				// Anything else is the business of the deliverer that took the mail last
				else if( $entry['claim'] !== $token ) {
					$left = true;
					return null;
				}
				else if( $outcome === 'held' ) {
					$state['waiting'][$id]['tries']	= max( 0, $entry['tries'] - 1 );
					$state['waiting'][$id]['next']	= $now + self::CAP_WAIT;
					$state['waiting'][$id]['claim']	= '';
					$state['waiting'][$id]['until']	= 0;
				}
				else if( $outcome === 'invalid' || $entry['tries'] >= self::attempts() ) {

					if( self::_fail( $appData, [ self::_record( $id, $entry['kind'], $entry['queued'], $now, $entry['tries'], $outcome === 'invalid' ? self::INVALID : $error ) ], $now ) === false )
						return null;

					unset( $state['waiting'][$id] );
					$gaveUp = true;
				}
				else {
					$state['waiting'][$id]['error']	= $error;
					$state['waiting'][$id]['claim']	= '';
					$state['waiting'][$id]['until']	= 0;
				}

				return self::_file( $state );
			} );

			return [ 'written' => $written === true || $left === true, 'gaveUp' => $gaveUp ];
		}

		/**
		 *	The deliverer is done: the file is removed where no mail waits any more, and what was put aside long ago goes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function release( array &$appData ): void {

			if( self::_read( $appData )['waiting'] === [] )
				self::_dropIfEmpty( $appData );

			self::_sweepAside( $appData );
		}

		/**
		 *	What the outbox holds, without a word of any mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										{ waiting, failed, last, oldest } - the counts, the minute of the last mail given up on and of the oldest that waits ('Y-m-d H:i'), null for none
		 */
		public static function counts( array &$appData ): array {

			$state		= self::_read( $appData );
			$failed		= self::_failed( self::_load( $appData, self::FAILED ), self::now( $appData ) );
			$last			= $failed === [] ? null : max( array_column( $failed, 'failed' ) );
			$oldest		= $state['waiting'] === [] ? null : min( array_column( $state['waiting'], 'queued' ) );

			return [
				'waiting'	=> count( $state['waiting'] ),
				'failed'	=> count( $failed ),
				'last'		=> $last === null ? null : date( 'Y-m-d H:i', $last ),
				'oldest'	=> $oldest === null ? null : date( 'Y-m-d H:i', $oldest ),
			];
		}

		/**
		 *	Whether there is nothing for a deliverer to do: no mail that is due and free, none to give up on, nothing that is
		 *	not a mail
		 *
		 *	@param		array 		$state				As _state() answers
		 *	@param		int				$now
		 *
		 *	@return 	bool
		 */
		private static function _idle( array $state, int $now ): bool {

			return $state['bad'] === 0 && self::_dueSecond( $state, $now ) > $now;
		}

		/**
		 *	The second at which the outbox has something to do: the first mail that is free and due, or too old, or - for a
		 *	mail that belongs to a deliverer - when the lease is over. 'now' where the file holds what is no mail
		 *
		 *	@param		array 		$state
		 *	@param		int				$now
		 *
		 *	@return 	int											PHP_INT_MAX for nothing
		 */
		private static function _dueSecond( array $state, int $now ): int {

			if( $state['bad'] > 0 )
				return $now;

			$due = PHP_INT_MAX;

			foreach( $state['waiting'] as $entry ) {

				$wake = max( $entry['next'], self::_holdEnd( $entry, $now ) );

				$due = min( $due, $wake, $entry['queued'] + self::MAX_AGE );
			}

			return $due;
		}

		/**
		 *	The second the note of the outbox says, 0 where there is none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		private static function _noteSecond( array &$appData ): int {

			$real = \Nino\Filesystem::path( $appData, self::DUE );
			clearstatcache( true, $real );
			$note = trim( (string) @file_get_contents( $real ) );

			return $note !== '' && ctype_digit( $note ) === true ? (int) $note : 0;
		}

		/**
		 *	Whether a mail belongs to a deliverer for now
		 *
		 *	@param		array 		$entry
		 *	@param		int				$now
		 *
		 *	@return 	bool
		 */
		private static function _leased( array $entry, int $now ): bool {

			return self::_holdEnd( $entry, $now ) > 0;
		}

		/**
		 *	The second a lease ends, 0 for a mail that belongs to no deliverer. A lease is never longer than LEASE: one that
		 *	ends further away than that is a clock that was set back, or a file somebody edited, and holds nothing
		 *
		 *	@param		array 		$entry
		 *	@param		int				$now
		 *
		 *	@return 	int
		 */
		private static function _holdEnd( array $entry, int $now ): int {

			return $entry['claim'] !== '' && $entry['until'] > $now && $entry['until'] - $now <= self::LEASE ? $entry['until'] : 0;
		}

		/**
		 *	The waiting mail that carries a key
		 *
		 *	@param		array 		$waiting
		 *	@param		string		$key					'' is no key
		 *
		 *	@return 	array|null
		 */
		private static function _withKey( array $waiting, string $key ): ?array {

			if( $key === '' )
				return null;

			foreach( $waiting as $entry )
				if( $entry['key'] === $key )
					return $entry;

			return null;
		}

		/**
		 *	Whether the outbox has no place for a mail of a kind: all of MAX_WAITING are taken, or - for a mail of a visitor - the
		 *	MAX_CLIENT that a visitor may have
		 *
		 *	@param		array 		$waiting
		 *	@param		string		$kind
		 *
		 *	@return 	bool
		 */
		private static function _full( array $waiting, string $kind ): bool {

			if( count( $waiting ) >= self::MAX_WAITING )
				return true;

			return $kind === \Nino\Mail::CLIENT && count( array_filter( $waiting, static fn( array $entry ): bool => $entry['kind'] === \Nino\Mail::CLIENT ) ) >= self::MAX_CLIENT;
		}

		/**
		 *	The record of a mail that was given up on
		 *
		 *	@return 	array
		 */
		private static function _record( string $id, string $kind, int $queued, int $failed, int $tries, string $error ): array {

			return [ 'id' => $id, 'kind' => $kind, 'queued' => $queued, 'failed' => $failed, 'tries' => $tries, 'error' => $error ];
		}

		/**
		 *	Add records to the file of the mails that were given up on, and let the old ones go
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$records
		 *	@param		int				$now
		 *
		 *	@return 	bool											Whether they are written
		 */
		private static function _fail( array &$appData, array $records, int $now ): bool {

			return self::_mutate( $appData, self::FAILED, static function( array $stored ) use ( $records, $now ): array {

				$failed = self::_failed( $stored, $now );

				foreach( $records as $record )
					$failed[ $record['id'] ] = $record;

				uasort( $failed, static fn( array $a, array $b ): int => $b['failed'] <=> $a['failed'] );

				return [ 'format' => 1, 'failed' => array_values( array_slice( $failed, 0, self::MAX_FAILED, true ) ) ];
			} );
		}

		/**
		 *	The records a file of the mails given up on holds that are none too old and are what they should be
		 *
		 *	@param		mixed			$stored
		 *	@param		int				$now
		 *
		 *	@return 	array											By id
		 */
		private static function _failed( mixed $stored, int $now ): array {

			$failed = [];

			foreach( is_array( $stored ) === true && is_array( $stored['failed'] ?? null ) === true ? $stored['failed'] : [] as $record ) {

				if( is_array( $record ) === false )
					continue;

				$id = $record['id'] ?? null;

				foreach( [ 'queued', 'failed', 'tries' ] as $number )
					if( is_int( $record[$number] ?? null ) === false || $record[$number] < 0 )
						continue 2;

				if( is_string( $id ) === false || preg_match( '/^[0-9a-f]{16}$/', $id ) !== 1 || $now - $record['failed'] >= self::KEEP_FAILED )
					continue;

				$failed[$id] = [
					'id'			=> $id,
					'kind'		=> in_array( $record['kind'] ?? null, self::_kinds(), true ) === true ? $record['kind'] : \Nino\Mail::CLIENT,
					'queued'	=> $record['queued'],
					'failed'	=> $record['failed'],
					'tries'		=> $record['tries'],
					'error'		=> is_string( $record['error'] ?? null ) === true && preg_match( '/^[a-z]{1,16}$/', $record['error'] ) === 1 ? $record['error'] : self::INVALID,
				];
			}

			return $failed;
		}

		/**
		 *	The kinds of mail an entry may have
		 *
		 *	@return 	array
		 */
		private static function _kinds(): array {

			return [ \Nino\Mail::CLIENT, \Nino\Mail::SYSTEM, \Nino\Mail::TEST ];
		}

		/**
		 *	What the waiting file holds: nothing is locked
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										As _state() answers
		 */
		private static function _read( array &$appData ): array {

			return self::_disk( $appData, self::WAITING );
		}

		/**
		 *	The json a file of the outbox holds, out of the one statement it is written in
		 *
		 *	@param		string		$raw					The bytes of the file
		 *
		 *	@return 	mixed											The decoded content, null where the file is not what this class wrote
		 */
		public static function decode( string $raw ): mixed {

			$head = "<?php return '";

			if( str_starts_with( $raw, $head ) === false || str_ends_with( $raw, "';" ) === false )
				return null;

			// php writes a string of this kind with two escapes: a backslash and a quote
			return json_decode( strtr( substr( $raw, strlen( $head ), -2 ), [ '\\\\' => '\\', "\\'" => "'" ] ), true );
		}

		/**
		 *	A content as the file of the outbox holds it - what putFileContent() writes of the json of it
		 *
		 *	@param		array 		$content
		 *
		 *	@return 	string
		 */
		public static function encode( array $content ): string {

			return '<?php return '. var_export( (string) json_encode( $content, JSON_UNESCAPED_SLASHES ), true ). ';';
		}

		/**
		 *	What a file of the outbox holds on the disk this moment, read from the file itself and from no cache - not the
		 *	cache of the request, which another process that writes the file does not tell, and not a compiled copy of it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path
		 *
		 *	@return 	mixed											[] for a file that is not there or cannot be read
		 */
		private static function _load( array &$appData, string $path ): mixed {

			$real = \Nino\Filesystem::path( $appData, $path );

			if( $real === '' )
				return [];

			clearstatcache( true, $real );
			$raw = @file_get_contents( $real );

			return $raw === false ? [] : ( self::decode( $raw ) ?? [] );
		}

		/**
		 *	The same, as a state of the waiting file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path
		 *
		 *	@return 	array										As _state() answers
		 */
		private static function _disk( array &$appData, string $path ): array {

			return self::_state( self::_load( $appData, $path ) );
		}

		/**
		 *	The mail a key was delivered for in the last KEY_MEMORY seconds, if it was
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The hash of the key, '' for none
		 *
		 *	@return 	array|null								{ key, id, content, at }
		 */
		private static function _remembered( array &$appData, string $key ): ?array {

			if( $key === '' )
				return null;

			$now = self::now( $appData );

			foreach( self::_keys( self::_load( $appData, self::KEYS ), $now ) as $known )
				if( $known['key'] === $key )
					return $known;

			return null;
		}

		/**
		 *	The keys a file of them holds that are none too old and are what they should be
		 *
		 *	@param		mixed			$stored
		 *	@param		int				$now
		 *
		 *	@return 	array
		 */
		private static function _keys( mixed $stored, int $now ): array {

			$keys = [];

			foreach( is_array( $stored ) === true && is_array( $stored['keys'] ?? null ) === true ? $stored['keys'] : [] as $known ) {

				if( is_array( $known ) === false || is_int( $known['at'] ?? null ) === false || $now - $known['at'] >= self::KEY_MEMORY )
					continue;

				foreach( [ 'key', 'content' ] as $hash )
					if( is_string( $known[$hash] ?? null ) === false || preg_match( '/^[0-9a-f]{64}$/', $known[$hash] ) !== 1 )
						continue 2;

				if( is_string( $known['id'] ?? null ) === false || preg_match( '/^[0-9a-f]{16}$/', $known['id'] ) !== 1 )
					continue;

				$keys[ $known['key'] ] = [ 'key' => $known['key'], 'id' => $known['id'], 'content' => $known['content'], 'at' => $known['at'] ];
			}

			return $keys;
		}

		/**
		 *	Keep the key of a mail that went out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry
		 *	@param		int				$now
		 *
		 *	@return 	bool											Whether it is written
		 */
		private static function _remember( array &$appData, array $entry, int $now ): bool {

			return self::_mutate( $appData, self::KEYS, static function( array $stored ) use ( $entry, $now ): array {

				$keys = self::_keys( $stored, $now );
				$keys[ $entry['key'] ] = [ 'key' => $entry['key'], 'id' => $entry['id'], 'content' => $entry['content'], 'at' => $now ];

				uasort( $keys, static fn( array $a, array $b ): int => $b['at'] <=> $a['at'] );

				return [ 'format' => 1, 'keys' => array_values( array_slice( $keys, 0, self::MAX_KEYS, true ) ) ];
			} );
		}

		/**
		 *	What a file of the outbox holds, made into what it should be: nothing that was read is trusted. A mail that is
		 *	not one - no id, a kind that is none, a text that is no text, a size over the limit - is not in 'waiting' and
		 *	is counted in 'bad', which the deliverer gives up on
		 *
		 *	@param		mixed			$stored
		 *
		 *	@return 	array										{ waiting: { id: mail }, bad: int }
		 */
		private static function _state( mixed $stored ): array {

			$state = [ 'waiting' => [], 'bad' => 0 ];

			if( is_array( $stored ) === false )
				return $state;

			foreach( is_array( $stored['waiting'] ?? null ) === true ? $stored['waiting'] : [] as $entry ) {

				$clean = self::_entry( $entry );

				if( $clean === null || isset( $state['waiting'][ $clean['id'] ] ) === true )
					$state['bad']++;
				else
					$state['waiting'][ $clean['id'] ] = $clean;
			}

			return $state;
		}

		/**
		 *	One mail of the waiting file, or null where it is none
		 *
		 *	@param		mixed			$entry
		 *
		 *	@return 	array|null
		 */
		private static function _entry( mixed $entry ): ?array {

			if( is_array( $entry ) === false )
				return null;

			foreach( [ 'id', 'key', 'content', 'kind', 'to', 'subject', 'body', 'replyTo', 'error', 'claim' ] as $text )
				if( is_string( $entry[$text] ?? null ) === false )
					return null;

			foreach( [ 'queued', 'tries', 'next', 'until' ] as $number )
				if( is_int( $entry[$number] ?? null ) === false || $entry[$number] < 0 )
					return null;

			if( preg_match( '/^[0-9a-f]{16}$/', $entry['id'] ) !== 1
				|| ( $entry['key'] !== '' && preg_match( '/^[0-9a-f]{64}$/', $entry['key'] ) !== 1 )
				|| preg_match( '/^[0-9a-f]{64}$/', $entry['content'] ) !== 1
				|| in_array( $entry['kind'], self::_kinds(), true ) === false
				|| preg_match( '/^[a-z]{0,16}$/', $entry['error'] ) !== 1
				|| strlen( $entry['to'] ) + strlen( $entry['subject'] ) + strlen( $entry['body'] ) + strlen( $entry['replyTo'] ) > self::MAX_BYTES )
				return null;

			return [
				'id'			=> $entry['id'],
				'key'			=> $entry['key'],
				'content'	=> $entry['content'],
				'kind'		=> $entry['kind'],
				'to'			=> $entry['to'],
				'subject'	=> $entry['subject'],
				'body'		=> $entry['body'],
				'replyTo'	=> $entry['replyTo'],
				'queued'	=> $entry['queued'],
				'tries'		=> $entry['tries'],
				'next'		=> $entry['next'],
				'error'		=> $entry['error'],
				'claim'		=> $entry['claim'],
				'until'		=> $entry['until'],
			];
		}

		/**
		 *	The content of the waiting file for a state
		 *
		 *	@param		array 		$state				As _state() answers
		 *
		 *	@return 	array
		 */
		private static function _file( array $state ): array {

			return [ 'format' => 1, 'waiting' => array_values( $state['waiting'] ) ];
		}

		/**
		 *	The read-modify-write of a file of the outbox, in the shape of \Nino\Filesystem::mutate() - the lock of the file,
		 *	the read, the callback, the write - with the read made from the disk by file_get_contents() and decode(): the file is
		 *	php that returns its json, and a read that includes it would be the compiled copy of another process's file. A file
		 *	that cannot be read - a person edited it and it does not decode - is put aside under that lock, so that no writer
		 *	can have put a good file there between the look and the move: the outbox starts over, what was in the file is at
		 *	DAMAGED for a person to read for KEEP_FAILED seconds, and a log line says it happened.
		 *
		 *	For the waiting file the note of what is due is written after the file, under the lock again, from what the file then
		 *	holds: an end between the two leaves a note that is older than the file, which says 'look' (see \Nino\Http::_mailDue()),
		 *	and a note that cannot be written is removed, which says it as well - the mails are in the file, and a mail that is in
		 *	the file is not lost for a note.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path
		 *	@param		callable	$fn						function( array $stored ): ?array - null writes nothing
		 *
		 *	@return 	bool											Whether the file was written
		 */
		private static function _mutate( array &$appData, string $path, callable $fn ): bool {

			if( \Nino\Filesystem::lockFile( $appData, $path ) === false )
				return false;

			$real = \Nino\Filesystem::path( $appData, $path );

			try {
				clearstatcache( true, $real );
				$raw			= is_file( $real ) === true ? @file_get_contents( $real ) : false;
				$stored		= $raw === false ? false : self::decode( $raw );

				if( is_array( $stored ) === false ) {

					if( $raw !== false ) {

						$to = \Nino\Filesystem::path( $appData, $path === self::FAILED ? self::DAMAGED_FAILED : self::DAMAGED );

						if( $to === '' || @rename( $real, $to ) === false ) {
							\Nino\Filesystem::unlockFile( $appData, $path );
							return false;
						}

						@touch( $to );
						trigger_error( 'Mail queue: a file of the outbox could not be read and was put aside, the outbox starts empty.', E_USER_WARNING );
					}

					$stored = [];
				}

				$new = $fn( $stored );
			}
			catch( \Throwable $error ) {
				\Nino\Filesystem::unlockFile( $appData, $path );
				throw $error;
			}

			$json = $new === null ? false : json_encode( $new, JSON_UNESCAPED_SLASHES );

			if( $json === false ) {
				\Nino\Filesystem::unlockFile( $appData, $path );
				return false;
			}

			// Releases the lock, as the end of a read-modify-write
			if( \Nino\Filesystem::putFileContent( $appData, $path, $json, true ) === false )
				return false;

			if( $path === self::WAITING )
				self::_writeNote( $appData );

			return true;
		}

		/**
		 *	Make the note beside the waiting file say what the file says: the second at which something is due
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _writeNote( array &$appData ): void {

			$note = \Nino\Filesystem::path( $appData, self::DUE );

			if( \Nino\Filesystem::lockFile( $appData, self::WAITING ) === false ) {
				@unlink( $note );
				return;
			}

			try {
				$written = \Nino\Filesystem::putFileContent( $appData, self::DUE, (string) self::_dueSecond( self::_read( $appData ), self::now( $appData ) ) );

				if( $written === false )
					@unlink( $note );
			}
			finally {
				\Nino\Filesystem::unlockFile( $appData, self::WAITING );
			}
		}

		/**
		 *	Remove the waiting file and its note where no mail waits in it, under the lock every write of it takes: a write
		 *	cannot come between the look and the removal
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _dropIfEmpty( array &$appData ): void {

			if( \Nino\Filesystem::lockFile( $appData, self::WAITING, false ) === false )
				return;

			try {
				$state = self::_read( $appData );

				if( $state['waiting'] === [] && $state['bad'] === 0 ) {
					@unlink( \Nino\Filesystem::path( $appData, self::DUE ) );
					@unlink( \Nino\Filesystem::path( $appData, self::WAITING ) );
				}
			}
			finally {
				\Nino\Filesystem::unlockFile( $appData, self::WAITING );
			}
		}

		/**
		 *	Remove what was put aside KEEP_FAILED seconds ago - it holds what people wrote, and nobody keeps it for ever - and the keys that are no use any more
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _sweepAside( array &$appData ): void {

			foreach( [ self::DAMAGED, self::DAMAGED_FAILED ] as $path ) {

				$real = \Nino\Filesystem::path( $appData, $path );

				if( $real !== '' && is_file( $real ) === true && time() - (int) @filemtime( $real ) >= self::KEEP_FAILED )
					@unlink( $real );
			}

			// The keys of the mails that went out are of no use after their time, and the file is removed when nothing was added to it since
			$keys = \Nino\Filesystem::path( $appData, self::KEYS );

			if( $keys !== '' && is_file( $keys ) === true && time() - (int) @filemtime( $keys ) >= self::KEY_MEMORY && \Nino\Filesystem::lockFile( $appData, self::KEYS, false ) === true ) {

				@unlink( $keys );
				\Nino\Filesystem::unlockFile( $appData, self::KEYS );
			}
		}
	}
}
