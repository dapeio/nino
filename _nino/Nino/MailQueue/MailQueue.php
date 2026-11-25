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
	 *	Five files below /data/ and a folder, kept out of every backup and of every restore (see \Nino\Backup::OUTBOX). The four that
	 *	hold mails, keys or states are php files that a webserver which serves them by mistake runs and gets nothing from - the one
	 *	statement of the file, `<?php return '...';`, returns the json it holds - and they are DATA, as every file of /data/
	 *	is: read from the disk with file_get_contents() and decoded here (decode()), and never loaded with include. A
	 *	second process - the cron command beside the webserver - writes them, and the compiled copy of a script that the
	 *	process of the webserver keeps cannot be invalidated by it: it would be the old outbox. Written under the lock of
	 *	the file, which every writer takes (lockFile(), read, putFileContent(), the sequence mutate() is made of).
	 *
	 *	WAITING, '/data/mail-queue.php': { format, waiting: [ mail, ... ] }. A mail is { id, key, content, kind, to,
	 *	subject, body, replyTo, queued, tries, next, error, claim, until, expires }, 'expires' - the second after which the
	 *	mail is never delivered - only where the caller gave one (see \Nino\Mail::queue()). The file is removed when the last mail leaves
	 *	it, so that the check of every request - whether the file is there - is a question of one stat. A mail to nobody
	 *	(\Nino\Mail::VOID) is a mail of the kind CLIENT here, whose 'to' is hyphens alone as long as the address was: the
	 *	outbox knows no kind of its own for it, and the deliverer lets it go without a transport (see \Nino\Mail::queue()).
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
	 *	STATES, '/data/mail-states.php': { format, states: [ [ id, state, at, content, owner ], ... ] }. What became of the mails that carry a key,
	 *	for KEEP_STATES seconds and for the last MAX_STATES_OWNER of the owner and MAX_STATES of the others: 'sent' or 'failed', by an id that is a keyed hash of the hash of
	 *	the key (the secret of the outbox is the key, so that nobody who reads the file can try a key against it), and the time. No
	 *	address, no subject, no text: \Nino\Mail::state() reads it, with the outbox, which says 'waiting'.
	 *
	 *	BODIES, '/data/.mail-bodies/<id>.php': the text of a mail of the kind OWNER, one file for each mail, written once before the
	 *	mail is in WAITING and removed with it. A wave of 500 mails of 20 KB would make WAITING 10 MB, which is written whole for every mail
	 *	that is queued, taken and settled (measured, see docs/development.md, Mail); with the text aside the file holds the 500 mails as it
	 *	holds 500 mails of no text. The mail in WAITING has the text '' and its id is the name of the file. A folder that starts with a
	 *	dot is none of a backup, and the text of a mail that is not in WAITING any more is swept after TEMP_KEEP seconds.
	 *
	 *	FAILED, '/data/mail-failed.php': { format, failed: [ record, ... ] }. What a mail came to that was given up on -
	 *	no address, no subject, no body, no key, only { id, kind, queued, failed, tries, error } - for KEEP_FAILED seconds.
	 *
	 *	A mail that carries an expiry has an end of its own: from the second it names, the next run takes it out of WAITING together
	 *	with its text (the file of the text of a mail of the owner, too) and notes its key as 'failed', and a deliverer that took it
	 *	a moment before is stopped just before the transport is asked. Two cases are told apart by what the transport said before.
	 *	A mail that a transport refused or threw on at least once ('error' is set) and then lapsed is a sign of a transport that is
	 *	down - a site whose outbox holds nothing but codes would otherwise never hear of it, since none of them is ever given up
	 *	on: it is kept in FAILED as a record of the word LAPSED (no text, no address, no key, as every record; the last MAX_LAPSED of them, in a
	 *	list of their own that pushes out no record of a mail given up on), counts in
	 *	counts() and for the failure of the dashboard as a mail given up on, and clears with the next delivery that went out - the
	 *	records of this word are none of what holds that back (see given()). A mail that lapsed before any transport was asked -
	 *	no run came in time - is no fault of the transport and leaves nothing but its state; the log says, once an hour, that mails
	 *	lapse unasked, which is what a cron job that does not run looks like. A mail whose lease is running - its transport was
	 *	asked before the second - stays in WAITING until the lease is over, so that the settle() of the transport finds it and notes
	 *	'sent', and no state of it says 'failed' and then 'sent'; a lease that ends with no settle() is followed by the removal, and
	 *	the mail is never asked again. The clock is the site's: one that is set back after the second makes the mail due again.
	 *
	 *	A mail is delivered at least once. A deliverer takes one mail at a time: the attempt is counted, the next one set and
	 *	the lease of the mail written - and read back - before the transport is asked (claim()), so that a process that dies in
	 *	the transport leaves a mail that is tried again after the wait it would have had after a failure, and a mail that
	 *	could not be written down is not sent at all. The windows in which a mail can go out twice are two: between the
	 *	transport's answer and settle(), and a transport that takes longer than LEASE seconds, which lets a second
	 *	deliverer take the mail while the first is still in it.
	 *
	 *	Every file is one a person can edit: nothing read from one is trusted (see _state()). Every file is written with the mode MODE, a
	 *	file that is there with a wider one gets it with its next write: the files hold the addresses and the texts of people, and
	 *	nobody on the machine but the user of the webserver reads them.
	 *
	 *	A file that cannot be read is not kept. What stands at DAMAGED in its place is a notice - the time, the size and the SHA-256 of
	 *	what was there, no word of it - and the outbox starts over; the notice goes after KEEP_FAILED seconds, and a file there that
	 *	is no notice goes with the next delivery.
	 *
	 *	A file that is there and cannot be read - its owner is another user, and the files are MODE - is neither empty nor damaged: it
	 *	is a failed read, which every caller answers as a failed write (queue() 'unwritable', a delivery takes nothing, counts() says
	 *	'unreadable'), and nothing is written over it. The log says why, at most once an hour (_say()).
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

		// How long the key of a delivered mail is remembered, in seconds - ten minutes - and how many keys at most: as many as the outbox
		// has places, so that a wave of the owner that is queued twice, a double click, is the same wave from its first mail to its last,
		// whichever of its mails went out in between
		public const int KEY_MEMORY = 600;
		public const int MAX_KEYS = self::MAX_WAITING + self::MAX_OWNER;

		// Where what became of a mail is kept for a while, by its key: a hash and a word and a time, no address and no text
		public const string STATES = '/data/mail-states.php';

		// How long the state of a mail is kept, in seconds - fourteen days, as the record of the mails given up on - and how many
		// states at most; the oldest go first. The states of the mails of the owner (OWNER) are a list of their own, so that the mails of
		// the visitors and the mails to nobody - a sign-in leaves one for every try - cannot push the states of a wave out of the file, and a
		// second click on it send it twice: five full waves in fourteen days
		public const int KEEP_STATES = 1209600;
		public const int MAX_STATES = 2000;
		public const int MAX_STATES_OWNER = 5 * self::MAX_OWNER;

		// Where the texts of the mails of the owner (OWNER) wait, one file for each mail, named by its id: the mails of a wave are
		// 20 KB each, and the one file of the outbox is written whole for every mail that is queued, taken and settled. A folder
		// that starts with a dot is none of a backup (see \Nino\Backup::_transient())
		public const string BODIES = '/data/.mail-bodies';

		// The longest the outbox waits from now to anything, in seconds: the longest wait of BACKOFF and a lease. A note that says more
		// is none, see \Nino\Http::MAIL_NOTE_SPAN
		public const int SPAN = 43500;

		// Where the second of the next due mail is kept, see \Nino\Http::MAIL_QUEUE_DUE
		public const string DUE = \Nino\Http::MAIL_QUEUE_DUE;

		// Where the secret of the outbox is kept: the key of the hash a mail to nobody has of its content
		public const string SECRET = '/data/mail-secret.php';

		// Where the mails are that were given up on
		public const string FAILED = '/data/mail-failed.php';

		// Where the notice stands of a file that could not be read: what the outbox started over from, and no word of what was in it
		public const string DAMAGED = '/data/mail-queue-damaged.php';
		public const string DAMAGED_FAILED = '/data/mail-failed-damaged.php';
		public const string DAMAGED_KEYS = '/data/mail-keys-damaged.php';
		public const string DAMAGED_STATES = '/data/mail-states-damaged.php';

		// How long a temporary file of a write that was cut off is left, in seconds: an hour
		public const int TEMP_KEEP = 3600;

		// How often the same trouble with a file is said in the log, in seconds: once an hour
		public const int SAY_WINDOW = 3600;

		// The mode of every file of the outbox: the user of the webserver reads and writes them, nobody else
		public const int MODE = 0600;

		// How many mails may wait at once
		public const int MAX_WAITING = 100;

		// How many of them mails of a visitor (CLIENT) may be: the rest of the places is the system's
		public const int MAX_CLIENT = 60;

		// How many mails of the owner (OWNER) may wait, whatever the others hold: a wave of 500 and a few more. Measured, the file of
		// the outbox holds them as it holds 100 mails with no text
		public const int MAX_OWNER = 600;

		// What delivering the mails of the owner costs, in seconds of credit: a mail takes OWNER_GAP of them, the credit grows by one
		// for each second and holds OWNER_BURST mails at most. A run delivers up to OWNER_BURST of them at once and then what the
		// time since the last has made - 20 mails a minute on average - and no run of any request or command delivers more
		public const int OWNER_GAP = 3;
		public const int OWNER_BURST = 20;

		// How large one mail may be: the bytes of its address, subject, reply address and body
		public const int MAX_BYTES = 65536;

		// How long a mail may wait before it is given up on, in seconds - two days
		public const int MAX_AGE = 172800;

		// The shortest an expiry that a caller names may be, in seconds from the moment of queue(): a minute, the first wait of a mail
		// that was not delivered at once; the longest is MAX_AGE, the age at which every mail is given up on whatever it says
		public const int MIN_EXPIRES = 60;

		// How long a mail that was given up on is kept as a record, and the notice of a file that could not be read, in seconds - fourteen days
		public const int KEEP_FAILED = 1209600;

		// How many records of mails given up on the file keeps; the oldest go first
		public const int MAX_FAILED = 500;

		// How many records of the word LAPSED it keeps besides, the oldest first: a record of a mail that lapsed after a refusal says that
		// mails lapse and the last few of them, and a flood of them in a bad hour must push out no record of a mail given up on
		public const int MAX_LAPSED = 50;

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
		public const string LAPSED = 'lapsed';
		public const string ABANDONED = 'abandoned';
		public const string UNREADABLE = 'unreadable';

		/**
		 *	The secret of the outbox: 32 random bytes as hex, made on the first call and kept in SECRET, which is no part of a backup. It
		 *	is the key of the HMAC that stands for the address in the hash of the content of a mail to nobody (\Nino\Mail::VOID), so that
		 *	nobody who reads the keys or the outbox can try an address against it. A file that is not what this class wrote is made anew:
		 *	the secret serves comparisons of ten minutes, nothing is lost with it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string										64 hex digits, '' where it can be neither read nor written
		 */
		public static function secret( array &$appData ): string {

			$valid = static fn( mixed $stored ): string => is_array( $stored ) === true && is_string( $stored['secret'] ?? null ) === true && preg_match( '/^[0-9a-f]{64}$/D', $stored['secret'] ) === 1 ? $stored['secret'] : '';
			$known = self::_load( $appData, self::SECRET );

			if( $known === false )
				return '';

			if( $valid( $known ) !== '' )
				return $valid( $known );

			if( \Nino\Filesystem::lockFile( $appData, self::SECRET ) === false )
				return '';

			// A second process may have made it between the look and the lock
			$known = self::_load( $appData, self::SECRET );

			if( $known !== false && $valid( $known ) !== '' ) {

				\Nino\Filesystem::unlockFile( $appData, self::SECRET );

				return $valid( $known );
			}

			$secret = bin2hex( random_bytes( 32 ) );

			return \Nino\Filesystem::putFileContent( $appData, self::SECRET, (string) json_encode( [ 'format' => 1, 'secret' => $secret ] ), true, self::MODE ) === true ? $secret : '';
		}

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
			$known = self::_withKey( $state['waiting'], $key, self::now( $appData ) ) ?? self::_remembered( $appData, $key );

			return [ 'id' => $known['id'] ?? '', 'content' => $known['content'] ?? '', 'full' => self::_full( $state['waiting'], $kind ) ];
		}

		/**
		 *	Put a mail into the outbox
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mail					{ to, subject, body, replyTo }, cleaned and checked
		 *	@param		string		$kind					One of \Nino\Mail::CLIENT, SYSTEM, TEST, OWNER
		 *	@param		string		$key					The hash of the key against a second queueing of the same mail, '' for none
		 *	@param		string		$content			The hash of what the mail is, which a second mail with the same key has to equal
		 *	@param		int				$expires			Seconds from now after which the mail is delivered by nobody, 0 for no expiry; no part of $content: the same mail with another expiry is the same mail
		 *
		 *	@return 	array										{ id, refused } - the id of the mail that waits, or '' and why it was not taken: 'full', 'conflict' (the key is a mail that is another) or 'unwritable'
		 */
		public static function add( array &$appData, array $mail, string $kind, string $key, string $content, int $expires = 0 ): array {

			$now		= self::now( $appData );
			$id			= bin2hex( random_bytes( 8 ) );
			$result	= [ 'id' => '', 'refused' => 'unwritable' ];

			// The callback answers null for a mail that is known and for a full outbox: those are answers of their own in $result,
			// and what stays 'unwritable' is a file that could not be locked or written
			$written = self::_mutate( $appData, self::WAITING, static function( array $stored ) use ( &$appData, $mail, $kind, $key, $content, $expires, $now, $id, &$result ): ?array {

				$state = self::_state( $stored );

				// The same mail twice is one: the one that waits is the answer, unless its expiry has passed, which makes it no mail that
				// waits - a code that lapsed and is still in the file is not the answer to the request for a new one. Another mail under
				// the key is refused, never answered with the id of a mail it is not
				$unreadable	= false;
				$known			= self::_withKey( $state['waiting'], $key, $now ) ?? self::_remembered( $appData, $key, $unreadable );

				// Where the keys cannot be read it is not known whether this mail went out a moment ago: it is not queued
				if( $known === null && $unreadable === true )
					return null;

				if( $known !== null ) {
					$result = $known['content'] === $content ? [ 'id' => $known['id'], 'refused' => '' ] : [ 'id' => '', 'refused' => 'conflict' ];
					return null;
				}

				// A wave takes longer than the keys are remembered: for the owner a mail that went out stays the same mail as long as its state is kept
				if( $kind === \Nino\Mail::OWNER && $key !== '' ) {

					$kept = self::_kept( $appData, $key, $content );

					if( $kept === null )
						return null;

					if( $kept['id'] !== '' || $kept['refused'] !== '' ) {
						$result = $kept;
						return null;
					}
				}

				if( self::_full( $state['waiting'], $kind ) === true ) {
					$result = [ 'id' => '', 'refused' => 'full' ];
					return null;
				}

				// The text of a mail of the owner waits in a file of its own, written before the mail is in the outbox: a mail that is
				// there has its text, and a text without a mail is swept (see _sweepBodies())
				if( $kind === \Nino\Mail::OWNER && self::_putBody( $appData, $id, (string) $mail['body'] ) === false )
					return null;

				$state['waiting'][$id] = [
					'id'			=> $id,
					'key'			=> $key,
					'content'	=> $content,
					'kind'		=> $kind,
					'to'			=> (string) $mail['to'],
					'subject'	=> (string) $mail['subject'],
					'body'		=> $kind === \Nino\Mail::OWNER ? '' : (string) $mail['body'],
					'replyTo'	=> (string) $mail['replyTo'],
					'queued'	=> $now,
					'tries'		=> 0,
					'next'		=> $now,
					'error'		=> '',
					'claim'		=> '',
					'until'		=> 0,
					'expires'	=> $expires > 0 ? $now + $expires : 0,
				];

				$result = [ 'id' => $id, 'refused' => '' ];

				return self::_file( $state );
			} );

			// The id of a mail that is new is answered when it is on the disk: the callback has set it before the write
			if( $written === false && $result['id'] === $id ) {

				$result = [ 'id' => '', 'refused' => 'unwritable' ];

				if( $kind === \Nino\Mail::OWNER )
					self::_dropBody( $appData, $id );
			}

			return $result;
		}

		/**
		 *	Take the mail that is due for the deliverer of $token, and give up on the ones that cannot be delivered any
		 *	more: older than MAX_AGE, out of attempts, or no mail at all. A mail whose expiry has passed is let go the same
		 *	way, with its text, unless its lease runs - it stays until the lease is over: a mail that a transport refused is a mail
		 *	given up on, with the word LAPSED, and one that no transport was asked for is no mail that was given up on: its key is
		 *	noted as failed and nothing else is written of it. Taking the mail is counting the attempt, setting
		 *	the wait after it and writing its lease - and nothing is answered that is not on the disk, read back from
		 *	there: where the outbox cannot be written the call says so and no mail is handed out, since a mail that is sent
		 *	without a record of it is sent again by every request that follows.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				The deliverer
		 *	@param		int				$now
		 *	@param		int				&$gaveUp			(reference) How many mails this call gave up on, for the report of the caller; a mail whose expiry passed is none of them
		 *	@param		bool			&$unwritable	(reference) Whether the outbox could not be written
		 *	@param		array 		&$lapses			(reference) { rejected, untried, orphaned }: how many mails whose expiry passed this call let go - rejected, a transport had refused them, which is in $gaveUp as a mail given up on; untried, no deliverer had taken them; orphaned, one had and did not finish
		 *
		 *	@return 	array|null								The mail taken { id, kind, to, subject, body, replyTo, expires }, null for none
		 */
		public static function claim( array &$appData, string $token, int $now, int &$gaveUp, bool &$unwritable, array &$lapses = [] ): ?array {

			$gaveUp			= 0;
			$unwritable	= false;
			$lapses			= [ 'rejected' => 0, 'untried' => 0, 'orphaned' => 0 ];
			$state			= self::_read( $appData );

			// A file that is there and cannot be read is no empty outbox: nothing is taken from it, and it is not written over
			if( $state['unreadable'] === true ) {

				$unwritable = true;

				return null;
			}

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
			$dropped = [];

			$written = self::_mutate( $appData, self::WAITING, static function( array $stored ) use ( &$appData, $token, $now, &$taken, &$given, &$dropped, &$lapses ): ?array {

				$state	= self::_state( $stored );
				$mine		= null;
				$records = [];
				$pending = [];
				$lapsed	= [];

				for( $i = 0; $i < $state['bad']; $i++ )
					$records[] = self::_record( bin2hex( random_bytes( 8 ) ), \Nino\Mail::CLIENT, $now, $now, 0, self::INVALID );

				// The id of a mail is its field, not the key it is stored under: php makes an array key of digits alone an int
				foreach( $state['waiting'] as $entry ) {

					// A mail whose expiry has passed leaves, once no transport holds it: one that a transport had refused is a sign of a transport that
					// is down and is recorded as a mail given up on, one that no transport was asked for leaves no record - its end is the one its
					// caller asked for. A mail that is held by a transport stays for the settle() of it, and is gone with its lease
					if( self::lapsed( $entry, $now ) === true && self::_leased( $entry, $now ) === false ) {

						if( $entry['error'] !== '' ) {

							$records[] = self::_record( $entry['id'], $entry['kind'], $entry['queued'], $now, $entry['tries'], self::LAPSED );
							$pending[ $entry['id'] ] = true;
						}
						else
							$lapsed[ $entry['id'] ] = $entry['tries'] === 0 ? 'untried' : 'orphaned';

						continue;
					}

					if( $now - $entry['queued'] >= self::MAX_AGE )
						$why = self::EXPIRED;
					else if( $entry['tries'] >= self::attempts() && $entry['next'] <= $now && self::_leased( $entry, $now ) === false )
						$why = self::ABANDONED;
					else
						continue;

					$records[] = self::_record( $entry['id'], $entry['kind'], $entry['queued'], $now, $entry['tries'], $why );
					$pending[ $entry['id'] ] = true;
				}

				// The mails of the owner are taken after the mails of the others and while the credit of their pace lasts, so that a wave neither
				// keeps a mail of a visitor waiting nor goes out in one run
				$credit	= self::_credit( $state['pace'], $now );
				$due		= array_filter( array_diff_key( $state['waiting'], $pending, $lapsed ), static fn( array $entry ): bool => $entry['next'] <= $now && $entry['tries'] < self::attempts() && self::_leased( $entry, $now ) === false && ( $entry['kind'] !== \Nino\Mail::OWNER || $credit >= self::OWNER_GAP ) );
				uasort( $due, static fn( array $a, array $b ): int => [ $a['kind'] === \Nino\Mail::OWNER, $a['queued'], $a['id'] ] <=> [ $b['kind'] === \Nino\Mail::OWNER, $b['queued'], $b['id'] ] );

				foreach( $due as $entry ) {

					$tries = $entry['tries'] + 1;
					$state['waiting'][ $entry['id'] ]['tries']	= $tries;
					$state['waiting'][ $entry['id'] ]['next']		= $now + self::BACKOFF[ min( $tries, count( self::BACKOFF ) ) - 1 ];
					$state['waiting'][ $entry['id'] ]['claim']	= $token;
					$state['waiting'][ $entry['id'] ]['until']	= $now + self::LEASE;
					$mine = [ 'id' => $entry['id'], 'kind' => $entry['kind'], 'to' => $entry['to'], 'subject' => $entry['subject'], 'body' => $entry['body'], 'replyTo' => $entry['replyTo'], 'expires' => $entry['expires'] ];

					if( $entry['kind'] === \Nino\Mail::OWNER )
						$state['pace'] = [ 'at' => $now, 'credit' => $credit - self::OWNER_GAP ];

					break;
				}

				// The record of what is given up on is written before the mail leaves the outbox: a process that dies in between
				// finds the mail again, and the record of an id is one
				if( $records !== [] && self::_fail( $appData, $records, $now ) === false ) {

					// An entry that is no mail cannot wait, and it is not dropped unrecorded: the run ends. The mails that are to be given up on
					// wait for the record, with no attempt counted, and the others are delivered: a record that cannot be written - a file
					// of another user - would hold the whole outbox otherwise
					if( $state['bad'] > 0 )
						return null;

					self::_say( $appData, 'mail-queue-record', 'Mail queue: the record of the mails given up on cannot be written, so the mails that are to be given up on wait and the others are delivered - check the owner and the mode of private/data/mail-failed.php.' );

					$records = [];
					$pending = [];
				}

				$lapses = [ 'rejected' => count( array_filter( $records, static fn( array $record ): bool => $record['error'] === self::LAPSED ) ), 'untried' => count( array_keys( $lapsed, 'untried', true ) ), 'orphaned' => count( array_keys( $lapsed, 'orphaned', true ) ) ];
				$gone = array_intersect_key( $state['waiting'], $pending + $lapsed );

				// What became of them is kept by their keys, once the record of them is written
				self::_note( $appData, array_map( static fn( array $entry ): array => [ $entry['key'], $entry['content'], $entry['kind'] === \Nino\Mail::OWNER ], $gone ), \Nino\Mail::STATE_FAILED, $now );

				$dropped = array_column( array_filter( $gone, static fn( array $entry ): bool => $entry['kind'] === \Nino\Mail::OWNER ), 'id' );
				$state['waiting'] = array_diff_key( $state['waiting'], $pending + $lapsed );
				$taken	= $mine;
				$given	= count( $records );

				return self::_file( $state );
			} );

			if( $written === false ) {

				$after			= self::_read( $appData );
				$unwritable = $taken !== null || $given > 0 || $after['waiting'] !== [] || $after['unreadable'] === true;

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

			// The texts of the mails that are gone leave with them, now that the outbox says so: a text left behind by an end in between is swept
			foreach( $dropped as $id )
				self::_dropBody( $appData, $id );

			// The text of a mail of the owner is read when the mail is handed out, from its file: 'aside' says why it is none
			if( $taken !== null && $taken['kind'] === \Nino\Mail::OWNER ) {

				$text = self::_body( $appData, $taken['id'] );

				$taken['body']	= $text['text'];
				$taken['aside']	= $text['failure'];
			}

			$gaveUp = $given;

			return $taken;
		}

		/**
		 *	What became of a mail the deliverer took. Where the file cannot be read or written when it is settled - the owner of the file is
	 *	another user, the disk is full - a mail that went out stays with its lease and goes out a second time after it: the window
	 *	between the answer of the transport and this call, which a wrong configuration of the cron job widens
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				The deliverer
		 *	@param		string		$id
		 *	@param		string		$outcome			'sent' - the mail leaves the outbox; 'again' - it stays for the time set when it was taken and says why in $error, unless it had its last attempt, which gives up on it; 'invalid' - it is no mail that can be sent, which gives up on it at once; 'held' - the mail was not tried, the attempt is not counted and it waits CAP_WAIT; 'lapsed' - its expiry passed after it was taken and before the transport was asked: it leaves the outbox with its text and its key is noted as failed, with the record of a mail given up on (LAPSED) where a transport had refused it before
		 *	@param		string		$error				One of the words of 'error', for 'again'
		 *	@param		int				$now
		 *
		 *	@return 	array										{ written, gaveUp } - whether the outbox says it now (a mail that is none of this deliverer's is left as it is, and that is written), and whether the mail was given up on by this call
		 */
		public static function settle( array &$appData, string $token, string $id, string $outcome, string $error, int $now ): array {

			$gaveUp	= false;
			$left		= false;
			$text		= false;

			$written = self::_mutate( $appData, self::WAITING, static function( array $stored ) use ( &$appData, $token, $id, $outcome, $error, $now, &$gaveUp, &$left, &$text ): ?array {

				$gaveUp	= false;
				$left		= false;
				$text		= false;
				$state	= self::_state( $stored );
				$entry	= $state['waiting'][$id] ?? null;

				if( $entry === null ) {
					$left = true;
					return null;
				}

				// A mail that was delivered leaves, whoever holds it by now: it has gone out
				if( $outcome === 'sent' ) {

					// The key is kept for a while, as a hash with the id: the same request again is the mail that went out. A key
					// that cannot be kept does not keep the mail: it has gone, and a mail that stays is sent again after its wait
					// and after the next one - what a key that is not kept costs is a second mail for a double click
					if( $entry['key'] !== '' && self::_remember( $appData, $entry, $now ) === false )
						self::_say( $appData, 'mail-queue-keys', 'Mail queue: the key of a mail that went out could not be kept, so a mail that is queued twice with the same key goes out twice - check private/data/mail-keys.php.' );

					// ...and what became of it for as long as a feature may ask
					self::_note( $appData, [ [ $entry['key'], $entry['content'], $entry['kind'] === \Nino\Mail::OWNER ] ], \Nino\Mail::STATE_SENT, $now );

					unset( $state['waiting'][$id] );
					$text = $entry['kind'] === \Nino\Mail::OWNER;
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
				else if( $outcome === 'lapsed' ) {

					// As claim() lets a lapsed mail go: one that a transport had refused is recorded, one that none was asked for is not
					if( $entry['error'] !== '' ) {

						if( self::_fail( $appData, [ self::_record( $id, $entry['kind'], $entry['queued'], $now, $entry['tries'], self::LAPSED ) ], $now ) === false )
							return null;

						$gaveUp = true;
					}

					self::_note( $appData, [ [ $entry['key'], $entry['content'], $entry['kind'] === \Nino\Mail::OWNER ] ], \Nino\Mail::STATE_FAILED, $now );

					unset( $state['waiting'][$id] );
					$text = $entry['kind'] === \Nino\Mail::OWNER;
				}
				else if( $outcome === 'invalid' || $entry['tries'] >= self::attempts() ) {

					if( self::_fail( $appData, [ self::_record( $id, $entry['kind'], $entry['queued'], $now, $entry['tries'], $outcome === 'invalid' ? self::INVALID : $error ) ], $now ) === false )
						return null;

					self::_note( $appData, [ [ $entry['key'], $entry['content'], $entry['kind'] === \Nino\Mail::OWNER ] ], \Nino\Mail::STATE_FAILED, $now );

					unset( $state['waiting'][$id] );
					$gaveUp = true;
					$text = $entry['kind'] === \Nino\Mail::OWNER;
				}
				else {
					$state['waiting'][$id]['error']	= $error;
					$state['waiting'][$id]['claim']	= '';
					$state['waiting'][$id]['until']	= 0;
				}

				return self::_file( $state );
			} );

			// The text of a mail of the owner that has left goes with it, once the outbox says so
			if( $written === true && $text === true )
				self::_dropBody( $appData, $id );

			return [ 'written' => $written === true || $left === true, 'gaveUp' => $gaveUp ];
		}

		/**
		 *	The deliverer is done: the file is removed where no mail waits any more, and the notices of files that could not be read go when they are old
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
		 *	@return 	array										{ waiting, failed, last, oldest, unreadable } - the counts, the minute of the last mail given up on and of the oldest that waits ('Y-m-d H:i'), null for none; whether a file of the outbox is there and cannot be read (the counts are those of what can be read, and the log says why)
		 */
		public static function counts( array &$appData ): array {

			$state		= self::_read( $appData );
			$stored		= self::_load( $appData, self::FAILED );
			$failed		= self::_failed( $stored, self::now( $appData ) );
			$last			= $failed === [] ? null : max( array_column( $failed, 'failed' ) );
			$oldest		= $state['waiting'] === [] ? null : min( array_column( $state['waiting'], 'queued' ) );

			return [
				'waiting'	=> count( $state['waiting'] ),
				'failed'	=> count( $failed ),
				'last'		=> $last === null ? null : date( 'Y-m-d H:i', $last ),
				'oldest'	=> $oldest === null ? null : date( 'Y-m-d H:i', $oldest ),
				'unreadable'	=> $state['unreadable'] === true || $stored === false,
			];
		}

		/**
		 *	How many mails given up on the file of them holds that stand for a failure of the delivery: all but the records of LAPSED, which
		 *	stand for the failure while the transport is down and are no reason to keep the failure of the dashboard once a mail went out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int												1 where the file is there and cannot be read: nothing is known then
		 */
		public static function given( array &$appData ): int {

			$stored = self::_load( $appData, self::FAILED );

			return $stored === false ? 1 : count( array_filter( self::_failed( $stored, self::now( $appData ) ), static fn( array $record ): bool => $record['error'] !== self::LAPSED ) );
		}

		/**
		 *	Whether the expiry of a mail has passed: from the second it names the mail is delivered by nobody. A mail with no expiry
		 *	never lapses by it
		 *
		 *	@param		array 		$entry				A mail of the outbox, as _state() answers it or claim() hands it out: { expires, ... }
		 *	@param		int				$now
		 *
		 *	@return 	bool
		 */
		public static function lapsed( array $entry, int $now ): bool {

			return $entry['expires'] > 0 && $now >= $entry['expires'];
		}

		/**
		 *	Whether a mail is over for good: its expiry has passed and no transport holds it. One that a transport holds is not over - its
		 *	outcome is open, and settle() decides it
		 *
		 *	@param		array 		$entry
		 *	@param		int				$now
		 *
		 *	@return 	bool
		 */
		private static function _over( array $entry, int $now ): bool {

			return self::lapsed( $entry, $now ) === true && self::_leased( $entry, $now ) === false;
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
		 *	The second at which the outbox has something to do: the first mail that is free and due, or too old, or whose expiry
		 *	is over, or - for a mail that belongs to a deliverer - when the lease is over. 'now' where the file holds what is no mail
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

			$wait = max( 0, self::OWNER_GAP - self::_credit( $state['pace'], $now ) );

			foreach( $state['waiting'] as $entry ) {

				$wake = max( $entry['next'], self::_holdEnd( $entry, $now ) );

				// A mail of the owner waits for its pace, unless it is out of attempts, which the next run gives up on whatever the pace
				if( $entry['kind'] === \Nino\Mail::OWNER && $entry['tries'] < self::attempts() )
					$wake = max( $wake, $now + $wait );

				$due = min( $due, $wake, $entry['queued'] + self::MAX_AGE, $entry['expires'] > 0 ? max( $entry['expires'], self::_holdEnd( $entry, $now ) ) : PHP_INT_MAX );
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
		 *	The waiting mail that carries a key: one whose expiry has passed and that no transport holds does not wait, though it is in the file until the next run
		 *
		 *	@param		array 		$waiting
		 *	@param		string		$key					'' is no key
		 *	@param		int				$now
		 *
		 *	@return 	array|null
		 */
		private static function _withKey( array $waiting, string $key, int $now ): ?array {

			if( $key === '' )
				return null;

			foreach( $waiting as $entry )
				if( $entry['key'] === $key && self::_over( $entry, $now ) === false )
					return $entry;

			return null;
		}

		/**
		 *	Whether the outbox has no place for a mail of a kind: for a mail of the owner the MAX_OWNER places of its own are taken, which
		 *	no other kind has a share in; for the others all of MAX_WAITING of the mails that are not the owner's are taken, or - for a
		 *	mail of a visitor - the MAX_CLIENT that a visitor may have
		 *
		 *	@param		array 		$waiting
		 *	@param		string		$kind
		 *
		 *	@return 	bool
		 */
		private static function _full( array $waiting, string $kind ): bool {

			$owner = count( array_filter( $waiting, static fn( array $entry ): bool => $entry['kind'] === \Nino\Mail::OWNER ) );

			if( $kind === \Nino\Mail::OWNER )
				return $owner >= self::MAX_OWNER;

			if( count( $waiting ) - $owner >= self::MAX_WAITING )
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

				// Two lists of their own: the records of the mails that lapsed are cut by themselves and push out no other
				$lapsed	= array_filter( $failed, static fn( array $record ): bool => $record['error'] === self::LAPSED );
				$kept		= array_slice( $lapsed, 0, self::MAX_LAPSED, true ) + array_slice( array_diff_key( $failed, $lapsed ), 0, self::MAX_FAILED, true );

				uasort( $kept, static fn( array $a, array $b ): int => $b['failed'] <=> $a['failed'] );

				return [ 'format' => 1, 'failed' => array_values( $kept ) ];
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

			return [ \Nino\Mail::CLIENT, \Nino\Mail::SYSTEM, \Nino\Mail::TEST, \Nino\Mail::OWNER ];
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
		 *	@return 	array|false								An empty array for a file that is not there or is not what this class wrote, false for one that is there and cannot be read (the owner is another user, the mode shuts it out)
		 */
		private static function _load( array &$appData, string $path ): array|false {

			$real = \Nino\Filesystem::path( $appData, $path );

			if( $real === '' )
				return [];

			clearstatcache( true, $real );
			$raw = @file_get_contents( $real );

			// What the file of the keys held is of no use after KEY_MEMORY seconds: one that cannot be read is no news, and is replaced by the next write
			if( $raw === false && $path === self::KEYS && self::_stale( $real ) === true )
				return [];

			if( $raw === false && is_file( $real ) === true ) {

				self::_unreadable( $appData, $path, $real );

				return false;
			}

			$stored = $raw === false ? [] : self::decode( $raw );

			return is_array( $stored ) === true ? $stored : [];
		}

		/**
		 *	Whether the file of the keys is older than the keys are kept: what it holds is of no use
		 *
		 *	@param		string		$real
		 *
		 *	@return 	bool
		 */
		private static function _stale( string $real ): bool {

			clearstatcache( true, $real );

			return is_file( $real ) === true && time() - (int) @filemtime( $real ) >= self::KEY_MEMORY;
		}

		/**
		 *	Who writes in the folder of the outbox, and who owns it: the files are written with a rename and are MODE, so a process that is
		 *	not the owner of the folder - a command run as root for a site whose webserver is another user - leaves files that the
		 *	webserver cannot read. A command that does not run as a webserver asks before it writes anything
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null								null where the process owns the folder, there is no folder yet or the system has no owners; else { owner, user } - their numbers
		 */
		public static function foreignOwner( array &$appData ): ?array {

			$folder = \Nino\Filesystem::path( $appData, '/data' );

			if( function_exists( 'posix_geteuid' ) === false || $folder === '' || is_dir( $folder ) === false )
				return null;

			clearstatcache( true, $folder );
			$owner	= @fileowner( $folder );
			$user		= posix_geteuid();

			return $owner === false || $owner === $user ? null : [ 'owner' => $owner, 'user' => $user ];
		}

		/**
		 *	What the deliverer does when it is done, with no mail to look at: the notices of damaged files, the keys and the temporary
		 *	files that were left go when their time is over
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function sweep( array &$appData ): void {

			self::_sweepAside( $appData );
		}

		/**
		 *	Say that a file of the outbox is there and cannot be read: at most once an hour, with the reason a person can act on - the
		 *	files are 0600, so another user than the one of the webserver, a cron job among them, reads none of them - and no address
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path
		 *	@param		string		$real
		 *
		 *	@return 	void
		 */
		private static function _unreadable( array &$appData, string $path, string $real ): void {

			$owner	= @fileowner( $real );
			$mode		= @fileperms( $real );

			self::_say( $appData, 'mail-queue-unreadable', 'Mail queue: '. basename( $path ). ' is there and cannot be read, so nothing is taken from the outbox and nothing is written to it (owner '. ( $owner === false ? '?' : (string) $owner ). ', mode '. ( $mode === false ? '?' : decoct( $mode & 0777 ) ). ', this process runs as user '. ( function_exists( 'posix_geteuid' ) === true ? (string) posix_geteuid() : '?' ). ') - the files of the outbox are 0600 and one that belongs to another user stays unreadable: only that user, or root, can give it to the user of the webserver (chown) or remove it, and a cron job that runs as the right user does not undo it.' );
		}

		/**
		 *	Say something about the outbox in the log, at most once in SAY_WINDOW seconds for each name
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					A slug, the counter
		 *	@param		string		$message
		 *
		 *	@return 	void
		 */
		private static function _say( array &$appData, string $name, string $message ): void {

			if( \Nino\Feature::count( $appData, $name, 'site', 1, self::SAY_WINDOW ) === true )
				trigger_error( $message, E_USER_WARNING );
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
		 *	@param		bool			&$unreadable	(reference) Whether the file of the keys is there and cannot be read: nothing is known then
		 *
		 *	@return 	array|null								{ key, id, content, at }
		 */
		private static function _remembered( array &$appData, string $key, bool &$unreadable = false ): ?array {

			if( $key === '' )
				return null;

			$now			= self::now( $appData );
			$stored		= self::_load( $appData, self::KEYS );
			$unreadable	= $stored === false;

			foreach( self::_keys( $stored, $now ) as $known )
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
		 *	The credit of the mails of the owner at a time, in seconds: what the last that was taken left, and one for each second since,
		 *	not above OWNER_BURST mails' worth. A mail takes OWNER_GAP of it. No pace is a full credit, and so is one that lies further
		 *	ahead than a credit can grow: a clock that was set back, or a file that somebody edited, holds nothing, and gives one burst of
		 *	OWNER_BURST mails each time it is set back
		 *
		 *	@param		array|null	$pace					{ at, credit } as _state() answers it
		 *	@param		int				$now
		 *
		 *	@return 	int
		 */
		private static function _credit( ?array $pace, int $now ): int {

			$cap = self::OWNER_BURST * self::OWNER_GAP;

			if( $pace === null || $pace['at'] > $now + $cap )
				return $cap;

			return min( $cap, $pace['credit'] + max( 0, $now - $pace['at'] ) );
		}

		/**
		 *	The id under which the state of a mail is kept: a keyed hash of the hash of its key, with the secret of the outbox, so that
		 *	nobody who reads the file can try a key against it, and half the length of a hash, which holds against a collision at the
		 *	number of states the file keeps
		 *
		 *	@param		string		$secret				As secret() answers
		 *	@param		string		$hash					The hash of the key
		 *
		 *	@return 	string										32 hex digits
		 */
		private static function _stateId( string $secret, string $hash ): string {

			return substr( hash_hmac( 'sha256', $hash, $secret ), 0, 32 );
		}

		/**
		 *	The states a file of them holds that are none too old and are what they should be
		 *
		 *	@param		mixed			$stored
		 *	@param		int				$now
		 *
		 *	@return 	array											{ id: [ id, state, at, content, owner ] }, the newest first; owner is 1 for a mail of the owner
		 */
		private static function _stateRecords( mixed $stored, int $now ): array {

			$records = [];

			foreach( is_array( $stored ) === true && is_array( $stored['states'] ?? null ) === true ? $stored['states'] : [] as $record ) {

				if( is_array( $record ) === false || count( $record ) !== 5 || is_string( $record[0] ?? null ) === false || preg_match( '/^[0-9a-f]{32}$/D', $record[0] ) !== 1
					|| is_string( $record[3] ?? null ) === false || preg_match( '/^[0-9a-f]{16}$/D', $record[3] ) !== 1 || in_array( $record[4] ?? null, [ 0, 1 ], true ) === false
					|| in_array( $record[1] ?? null, [ \Nino\Mail::STATE_SENT, \Nino\Mail::STATE_FAILED ], true ) === false
					|| is_int( $record[2] ?? null ) === false || $record[2] < 0 || $now - $record[2] >= self::KEEP_STATES )
					continue;

				$records[ $record[0] ] = [ $record[0], $record[1], $record[2], $record[3], $record[4] ];
			}

			uasort( $records, static fn( array $a, array $b ): int => $b[2] <=> $a[2] );

			return $records;
		}

		/**
		 *	Keep what became of mails by their keys: sent or given up on. A key that cannot be kept costs a feature the answer to
		 *	state() for that mail and nothing else, and is said in the log, as a key of the mails that went out is
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$items				[ [ the hash of the key, the hash of the content, whether it is a mail of the owner ], ... ]; '' for the key of a mail with none
		 *	@param		string		$state				\Nino\Mail::STATE_SENT or STATE_FAILED
		 *	@param		int				$now
		 *
		 *	@return 	void
		 */
		private static function _note( array &$appData, array $items, string $state, int $now ): void {

			$items = array_values( array_filter( $items, static fn( array $item ): bool => $item[0] !== '' ) );

			if( $items === [] )
				return;

			$secret = self::secret( $appData );

			$kept = $secret !== '' && self::_mutate( $appData, self::STATES, static function( array $stored ) use ( $items, $state, $now, $secret ): array {

				$fresh = [];

				foreach( $items as [ $hash, $content, $owner ] ) {

					$id = self::_stateId( $secret, $hash );
					$fresh[$id] = [ $id, $state, $now, substr( hash_hmac( 'sha256', $content, $secret ), 0, 16 ), $owner === true ? 1 : 0 ];
				}

				// The new ones stand before the old ones of the same second, which the cut keeps; each of the two lists is cut by itself
				$records = $fresh + array_diff_key( self::_stateRecords( $stored, $now ), $fresh );
				uasort( $records, static fn( array $a, array $b ): int => $b[2] <=> $a[2] );

				$room = [ 0 => self::MAX_STATES, 1 => self::MAX_STATES_OWNER ];
				$records = array_filter( $records, static function( array $record ) use ( &$room ): bool {

					return $room[ $record[4] ]-- > 0;
				} );

				return [ 'format' => 1, 'states' => array_values( $records ) ];
			} );

			if( $kept === false )
				self::_say( $appData, 'mail-queue-states', 'Mail queue: what became of a mail could not be kept, so \Nino\Mail::state() does not know it - check private/data/mail-states.php.' );
		}

		/**
		 *	What became of the mails that carry keys, read from the outbox and from the states, each file once
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$hashes				The hashes of the keys, none of them ''
		 *
		 *	@return 	array											{ hash: state } - \Nino\Mail::STATE_WAITING where the mail waits, STATE_SENT or STATE_FAILED where it was kept, STATE_UNKNOWN where nothing is; STATE_UNREADABLE for each where a file is there and cannot be read: what a mail came to is not known then
		 */
		public static function states( array &$appData, array $hashes ): array {

			$answer	= [];
			$state	= self::_read( $appData );
			$stored	= self::_load( $appData, self::STATES );
			$secret	= self::_load( $appData, self::SECRET );

			if( $state['unreadable'] === true || $stored === false || $secret === false ) {

				foreach( $hashes as $hash )
					$answer[$hash] = \Nino\Mail::STATE_UNREADABLE;

				return $answer;
			}

			// Without the secret nothing was ever kept, or what was kept cannot be found: a file of the states that this secret did not write
			// holds ids that no key has
			$key		= is_string( $secret['secret'] ?? null ) === true && preg_match( '/^[0-9a-f]{64}$/D', $secret['secret'] ) === 1 ? $secret['secret'] : '';
			$kept		= $key === '' ? [] : self::_stateRecords( $stored, self::now( $appData ) );
			$now		= self::now( $appData );
			$waiting = array_flip( array_column( array_filter( $state['waiting'], static fn( array $entry ): bool => self::_over( $entry, $now ) === false ), 'key' ) );
			$lapsed	= array_flip( array_column( array_filter( $state['waiting'], static fn( array $entry ): bool => self::_over( $entry, $now ) === true ), 'key' ) );

			foreach( $hashes as $hash ) {

				if( isset( $waiting[$hash] ) === true )
					$answer[$hash] = \Nino\Mail::STATE_WAITING;
				// A mail whose expiry has passed and that no transport holds is delivered by nobody, and is failed from that second, whether the next run took it out of the file or not
				else if( isset( $lapsed[$hash] ) === true )
					$answer[$hash] = \Nino\Mail::STATE_FAILED;
				else
					$answer[$hash] = $key !== '' ? ( $kept[ self::_stateId( $key, $hash ) ][1] ?? \Nino\Mail::STATE_UNKNOWN ) : \Nino\Mail::STATE_UNKNOWN;
			}

			return $answer;
		}

		/**
		 *	What the states say of a mail of the owner that carries a key, when it is neither in the outbox nor in the keys of the last
		 *	KEY_MEMORY seconds: a wave takes longer than the keys are remembered, and a second click that comes after the first mails
		 *	have gone out must not send them again. A mail that was delivered is the same mail for as long as the state is kept (and a
		 *	mail whose content is another is a conflict, as for a key that is remembered); a mail that was given up on is free to be
		 *	queued again, which is a second try of its owner
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The hash of the key
		 *	@param		string		$content			The hash of what the mail is
		 *
		 *	@return 	array|null								{ id, refused } for a mail that is known - its id, which is the same for every call, or 'conflict' - and { id: '', refused: '' } for one that is not; null where a file is there and cannot be read: nothing is known
		 */
		private static function _kept( array &$appData, string $key, string $content ): ?array {

			$stored	= self::_load( $appData, self::STATES );
			$secret	= self::_load( $appData, self::SECRET );

			if( $stored === false || $secret === false )
				return null;

			$salt = is_string( $secret['secret'] ?? null ) === true && preg_match( '/^[0-9a-f]{64}$/D', $secret['secret'] ) === 1 ? $secret['secret'] : '';
			$id		= $salt === '' ? '' : self::_stateId( $salt, $key );
			$kept	= $salt === '' ? null : ( self::_stateRecords( $stored, self::now( $appData ) )[$id] ?? null );

			if( $kept === null || $kept[1] !== \Nino\Mail::STATE_SENT )
				return [ 'id' => '', 'refused' => '' ];

			// The id of a mail that went out long ago is not kept: the answer is one that every call gets alike, and state() says what it is
			return $kept[3] === substr( hash_hmac( 'sha256', $content, $salt ), 0, 16 ) ? [ 'id' => substr( $id, 0, 16 ), 'refused' => '' ] : [ 'id' => '', 'refused' => 'conflict' ];
		}

		/**
		 *	The file of the text of a mail of the owner, by the id of the mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id						16 hex digits
		 *
		 *	@return 	string										The path, '' where the id is none
		 */
		private static function _bodyPath( array &$appData, string $id ): string {

			return preg_match( '/^[0-9a-f]{16}$/D', $id ) === 1 ? \Nino\Filesystem::path( $appData, self::BODIES. '/'. $id. '.php' ) : '';
		}

		/**
		 *	Write the text of a mail of the owner: in the form of the other files of the outbox, with the mode MODE, once and whole
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id
		 *	@param		string		$body
		 *
		 *	@return 	bool											Whether it is written
		 */
		private static function _putBody( array &$appData, string $id, string $body ): bool {

			$json = json_encode( [ 'format' => 1, 'body' => $body ], JSON_UNESCAPED_SLASHES );

			if( $json === false || self::_bodyPath( $appData, $id ) === '' )
				return false;

			$name		= self::BODIES. '/'. $id. '.php';
			$written = \Nino\Filesystem::putFileContent( $appData, $name, $json, true, self::MODE );

			// What was written is on the disk and nowhere else: the cache of the request keeps no copy of the text of a wave
			unset( $appData['./nino/filesystem/cache'][$name] );

			return $written;
		}

		/**
		 *	The text of a mail of the owner, from its file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id
		 *
		 *	@return 	array											{ text, failure } - the text and '', or '' and 'missing' (no file, or one that is not what this class wrote: the mail cannot be sent) or 'unreadable' (the file is there and cannot be read: the mail waits for its next attempt)
		 */
		private static function _body( array &$appData, string $id ): array {

			$real = self::_bodyPath( $appData, $id );

			if( $real === '' )
				return [ 'text' => '', 'failure' => 'missing' ];

			clearstatcache( true, $real );
			$raw = is_file( $real ) === true ? @file_get_contents( $real ) : false;

			if( $raw === false && is_file( $real ) === true ) {

				self::_say( $appData, 'mail-queue-body', 'Mail queue: the text of a mail of the owner is there and cannot be read, so the mail waits for its next attempt - the files of the outbox are 0600 and belong to the user of the webserver (private/data/.mail-bodies).' );

				return [ 'text' => '', 'failure' => 'unreadable' ];
			}

			$stored = $raw === false ? null : self::decode( $raw );

			return is_array( $stored ) === true && is_string( $stored['body'] ?? null ) === true ? [ 'text' => $stored['body'], 'failure' => '' ] : [ 'text' => '', 'failure' => 'missing' ];
		}

		/**
		 *	Remove the file of the text of a mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id
		 *
		 *	@return 	void
		 */
		private static function _dropBody( array &$appData, string $id ): void {

			$real = self::_bodyPath( $appData, $id );

			if( $real !== '' )
				@unlink( $real );
		}

		/**
		 *	Remove what is left of the texts of the mails of the owner: the file of a mail that is not in the outbox - an end between
		 *	the write of the text and the write of the mail, or between the write of the outbox and the removal of the text - once it is
		 *	older than TEMP_KEEP, which no mail that was just queued is, and the temporary files of a write that was cut off. The
		 *	outbox is read for it only where such a file is, so that a run that finds none pays for the listing of the folder
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _sweepBodies( array &$appData ): void {

			$folder = \Nino\Filesystem::path( $appData, self::BODIES );

			if( $folder === '' || is_dir( $folder ) === false || is_link( $folder ) === true )
				return;

			$old = [];

			foreach( glob( $folder. '/*.php' ) ?: [] as $file )
				if( time() - (int) @filemtime( $file ) >= self::TEMP_KEEP )
					$old[] = $file;

			$state = $old === [] ? null : self::_read( $appData );

			// An outbox that cannot be read says nothing about what waits in it: nothing is removed
			if( $state !== null && $state['unreadable'] === false )
				foreach( $old as $file )
					if( isset( $state['waiting'][ basename( $file, '.php' ) ] ) === false )
						@unlink( $file );

			foreach( glob( $folder. '/'. \Nino\Filesystem::PRIVATE_TEMP. '/*.tmp' ) ?: [] as $file )
				if( time() - (int) @filemtime( $file ) >= self::TEMP_KEEP )
					@unlink( $file );
		}

		/**
		 *	What a file of the outbox holds, made into what it should be: nothing that was read is trusted. A mail that is
		 *	not one - no id, a kind that is none, a text that is no text, a size over the limit - is not in 'waiting' and
		 *	is counted in 'bad', which the deliverer gives up on
		 *
		 *	@param		mixed			$stored
		 *
		 *	@return 	array										{ waiting: { id: mail }, bad: int, unreadable: bool, pace: { at, credit }|null } - a file that is there and could not be read (_load() answers false for it) is no empty file: nothing is taken from it and nothing is written over it
		 */
		private static function _state( mixed $stored ): array {

			$state = [ 'waiting' => [], 'bad' => 0, 'unreadable' => $stored === false, 'pace' => null ];

			if( is_array( $stored ) === false )
				return $state;

			// The credit of the mails of the owner, as the last that was taken left it: a number that is none says no pace, which is a full credit
			$pace = is_array( $stored['pace'] ?? null ) === true ? $stored['pace'] : [];

			if( is_int( $pace['at'] ?? null ) === true && is_int( $pace['credit'] ?? null ) === true && $pace['at'] >= 0 && $pace['credit'] >= 0 && $pace['credit'] <= self::OWNER_BURST * self::OWNER_GAP )
				$state['pace'] = [ 'at' => $pace['at'], 'credit' => $pace['credit'] ];

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

			// The expiry is there where the caller gave one: a mail with none has no field, and a field that is no number of seconds is no mail
			if( array_key_exists( 'expires', $entry ) === true && ( is_int( $entry['expires'] ) === false || $entry['expires'] < 0 ) )
				return null;

			if( preg_match( '/^[0-9a-f]{16}$/', $entry['id'] ) !== 1
				|| ( $entry['key'] !== '' && preg_match( '/^[0-9a-f]{64}$/', $entry['key'] ) !== 1 )
				|| preg_match( '/^[0-9a-f]{64}$/', $entry['content'] ) !== 1
				|| in_array( $entry['kind'], self::_kinds(), true ) === false
				|| ( $entry['kind'] === \Nino\Mail::OWNER && $entry['body'] !== '' )
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
				'expires'	=> $entry['expires'] ?? 0,
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

			// A mail with no expiry has no field for it: the bytes of such a mail are the bytes of a mail of an outbox that knows none
			$file = [ 'format' => 1, 'waiting' => array_values( array_map( static function( array $entry ): array {

				if( $entry['expires'] === 0 )
					unset( $entry['expires'] );

				return $entry;
			}, $state['waiting'] ) ) ];

			if( $state['pace'] !== null )
				$file['pace'] = $state['pace'];

			return $file;
		}

		/**
		 *	The read-modify-write of a file of the outbox, in the shape of \Nino\Filesystem::mutate() - the lock of the file,
		 *	the read, the callback, the write - with the read made from the disk by file_get_contents() and decode(): the file is
		 *	php that returns its json, and a read that includes it would be the compiled copy of another process's file. A file
		 *	that cannot be read - a person edited it and it does not decode - is replaced under that lock, so that no writer
		 *	can have put a good file there between the look and the removal: the outbox starts over, a notice of it is at
		 *	DAMAGED for KEEP_FAILED seconds - the time, the size, the SHA-256, nothing of what the file held - and a log line,
		 *	which names no address, says it happened. A file that is there and cannot be read is not that: the lock is
		 *	released and the answer is false, with the file as it was.
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

				// A file that is there and cannot be read is no empty file: it is not written over, not put aside and not started again
				// The file of the keys that cannot be read and is older than the keys are kept is replaced: nothing in it is of use, and the owner of the folder may
				if( $raw === false && $path === self::KEYS && self::_stale( $real ) === true && @unlink( $real ) === true )
					clearstatcache( true, $real );
				else if( $raw === false && is_file( $real ) === true ) {

					\Nino\Filesystem::unlockFile( $appData, $path );
					self::_unreadable( $appData, $path, $real );

					return false;
				}

				$stored		= $raw === false ? false : self::decode( $raw );

				if( is_array( $stored ) === false ) {

					if( $raw !== false && self::_replace( $appData, $path, $real, $raw ) === false ) {

						\Nino\Filesystem::unlockFile( $appData, $path );

						return false;
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
			if( \Nino\Filesystem::putFileContent( $appData, $path, $json, true, self::MODE ) === false )
				return false;

			if( $path === self::WAITING )
				self::_writeNote( $appData );

			return true;
		}

		/**
		 *	Replace a file that does not decode by the notice of it, under the lock of the file: the notice is written before the file
		 *	goes - where it cannot be written the file stays - and a notice that says the same, from an earlier try, is not written
		 *	again, so that its time stays the time the file was found
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path
		 *	@param		string		$real
		 *	@param		string		$raw					What the file holds
		 *
		 *	@return 	bool											Whether the file is gone and the outbox, or the record, or the keys, start empty
		 */
		private static function _replace( array &$appData, string $path, string $real, string $raw ): bool {

			$names	= [ self::FAILED => [ self::DAMAGED_FAILED, 'the record of the mails given up on starts empty' ], self::KEYS => [ self::DAMAGED_KEYS, 'the keys of the mails that went out start empty' ], self::STATES => [ self::DAMAGED_STATES, 'the states of the mails start empty' ] ];
			[ $to, $what ] = $names[$path] ?? [ self::DAMAGED, 'the outbox starts empty' ];
			$sha		= hash( 'sha256', $raw );
			$notice	= \Nino\Filesystem::path( $appData, $to );
			$there	= $notice !== '' && is_file( $notice ) === true ? self::decode( (string) @file_get_contents( $notice ) ) : null;

			if( ( is_array( $there ) === true ? ( $there['damaged']['sha256'] ?? '' ) : '' ) !== $sha
				&& \Nino\Filesystem::putFileContent( $appData, $to, (string) json_encode( [ 'format' => 1, 'damaged' => [ 'at' => time(), 'size' => strlen( $raw ), 'sha256' => $sha ] ] ), false, self::MODE ) === false )
				return false;

			if( @unlink( $real ) === false ) {

				self::_say( $appData, 'mail-queue-stuck', 'Mail queue: '. basename( $path ). ' does not decode and cannot be removed, so the outbox cannot be written - check the mode of private/data.' );

				return false;
			}

			trigger_error( 'Mail queue: a file of the outbox could not be read and was replaced by a notice, '. $what. '.', E_USER_WARNING );

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
				$written = \Nino\Filesystem::putFileContent( $appData, self::DUE, (string) self::_dueSecond( self::_read( $appData ), self::now( $appData ) ), false, self::MODE );

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

				if( $state['waiting'] === [] && $state['bad'] === 0 && $state['unreadable'] === false ) {
					@unlink( \Nino\Filesystem::path( $appData, self::DUE ) );
					@unlink( \Nino\Filesystem::path( $appData, self::WAITING ) );
				}
			}
			finally {
				\Nino\Filesystem::unlockFile( $appData, self::WAITING );
			}
		}

		/**
		 *	Whether a file is the notice of a file that could not be read, as _mutate() writes it: a few bytes, a time, a size and a hash
		 *
		 *	@param		string		$real					The path of the file
		 *
		 *	@return 	bool
		 */
		private static function _isNotice( string $real ): bool {

			$size = @filesize( $real );

			// A notice is far below this: a larger file is what a version kept of the damaged file
			if( $size === false || $size > 1024 )
				return false;

			$raw = @file_get_contents( $real );

			// A file that cannot be read is not judged: it goes with its age
			if( $raw === false )
				return true;

			$notice		= self::decode( $raw );
			$damaged	= is_array( $notice ) === true ? ( $notice['damaged'] ?? null ) : null;

			return is_array( $damaged ) === true && is_int( $damaged['at'] ?? null ) === true && is_int( $damaged['size'] ?? null ) === true
				&& is_string( $damaged['sha256'] ?? null ) === true && preg_match( '/^[0-9a-f]{64}$/', $damaged['sha256'] ) === 1;
		}

		/**
		 *	Remove the notices of a file that could not be read when they are KEEP_FAILED seconds old, and a file at their place that is none
		 *	- what a version kept of the damaged file itself holds what people wrote, and nobody keeps it - and the keys that are no use any more
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		private static function _sweepAside( array &$appData ): void {

			foreach( [ self::DAMAGED, self::DAMAGED_FAILED, self::DAMAGED_KEYS, self::DAMAGED_STATES ] as $path ) {

				$real = \Nino\Filesystem::path( $appData, $path );

				if( $real === '' || is_file( $real ) === false || \Nino\Filesystem::lockFile( $appData, $path, false ) === false )
					continue;

				try {
					clearstatcache( true, $real );

					if( self::_isNotice( $real ) === false || time() - (int) @filemtime( $real ) >= self::KEEP_FAILED )
						@unlink( $real );
				}
				finally {
					\Nino\Filesystem::unlockFile( $appData, $path );
				}
			}

			// The temporary files of a write that was cut off: they hold what a mail holds, in a folder nobody else can open
			$folder = \Nino\Filesystem::path( $appData, '/data' ). '/'. \Nino\Filesystem::PRIVATE_TEMP;

			foreach( glob( $folder. '/*.tmp' ) ?: [] as $file )
				if( time() - (int) @filemtime( $file ) >= self::TEMP_KEEP )
					@unlink( $file );

			self::_sweepBodies( $appData );

			// The keys of the mails that went out are of no use after their time, and the file is removed when nothing was added to it since
			$keys = \Nino\Filesystem::path( $appData, self::KEYS );

			if( $keys !== '' && is_file( $keys ) === true && time() - (int) @filemtime( $keys ) >= self::KEY_MEMORY && \Nino\Filesystem::lockFile( $appData, self::KEYS, false ) === true ) {

				@unlink( $keys );
				\Nino\Filesystem::unlockFile( $appData, self::KEYS );
			}
		}
	}
}
