<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Mail					Thin wrapper around mail() with shared headers and a per-address send cap
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Mail - thin wrapper around mail(): centralizes Content-Type/Reply-To
	// headers (one place for a project-wide change) and a shared per-address
	// send cap - the calls of send() and sendAll() one client address may
	// make in an hour, the key '/nino/mail/cap' of config.php (default 5,
	// 0 for no cap; the Config panel edits it), counted by
	// \Nino\Feature::count() under the name 'ratelimit'. Over budget, send()
	// and sendAll() return false and rateLimited() answers true, which is
	// what tells a caller "we refused to send this" apart from a mail() that failed:
	// \Nino\Form::handle() asks it and answers 429 without recording the
	// submission, where a mail no transport took is recorded and answered 500.
	// The signal belongs to the call that was made, not to the request: both
	// methods reset it where they reset what each mail of the call came to,
	// './nino/mail/results', which is left in input order for a caller that
	// has to tell the first from the rest
	//
	// A call that sent something also keeps one record of how it went,
	// /data/mail-status.php: since when, when last and how many calls
	// failed, no address and nothing a transport said - the workbench's
	// dashboard shows it (see failure()). Written once per call rather than
	// per mail, since a contact form's owner mail and the confirmation that
	// follows it are one call: a confirmation that went out must not wipe
	// the failure of the mail the owner is waiting for
	//
	// mail() is the default transport, not the only one: a module or a
	// feature that delivers another way (smtp, an api) registers a
	// callback under '/nino/mail/send' (see TRANSPORT) and gets every
	// mail after the cap and the header cleaning, with everything it
	// needs to send it itself
	//
	// send(), sendAll() and sendSystem() deliver in the request, and the
	// answer is theirs. queue() is the other way: a mail a feature wants
	// out whatever happens to the request - the visitor leaves, the server
	// drops the work after the response, the transport does not answer - is
	// kept in an outbox below /data/ (see \Nino\MailQueue) and delivered
	// by the requests that follow, with a wait that grows between the
	// attempts, until it is delivered or given up on and reported. The
	// outbox is the class \Nino\MailQueue, which a request loads only where
	// a mail is queued or waits
	class Mail {

		// How long a window of the cap lasts, in seconds - no key of its own
		private const int WINDOW 		= 3600;

		// The calls an hour the system may make for the whole site, see sendSystem()
		public const int SYSTEM_CAP = 12;

		// The two kinds of mail the system sends, each with a counter of its own under the prefix COUNTER_PREFIX - a name
		// that no other counter of the toolset has - and the calls an hour it may make
		public const string SYSTEM = 'system';
		public const string TEST = 'test';
		public const int TEST_CAP = 6;
		private const string COUNTER_PREFIX = 'sysmail-';

		// The kind of a mail that queue() charges to the cap of the client who set it off, as send() does: one visitor's action
		public const string CLIENT = 'client';

		// The kind of a mail that goes to nobody, for queue() alone: it takes the whole way of a mail of the kind CLIENT - read, counted
		// against the client's cap and the places of the visitors, written, noted, work asked for after the response, key remembered -
		// and the deliverer lets it go without a transport. It is kept as a mail of the kind CLIENT whose address is a line of hyphens as
		// long as the address that was given (see queue()), so that no stored kind of the outbox is new
		public const string VOID = 'void';

		// The kind of a mail that an account of the workshop sets off - a round mail to the people of a workshop - for queue() alone: it is
		// taken only where the request carries the session of such an account (\Nino\Auth::getCurrentUser()), is charged to no cap of the
		// hour and to no place of the visitors or of the system, has places of its own (MailQueue::MAX_OWNER) and is delivered at a pace
		// of its own, after the mails of the others (see queue())
		public const string OWNER = 'owner';

		// What became of a mail by its key, see state(): it waits, it was delivered, it was given up on, the outbox knows no mail of
		// this key - never queued, or forgotten after MailQueue::KEEP_STATES seconds and MailQueue::MAX_STATES mails - or a file of
		// the outbox is there and cannot be read
		public const string STATE_WAITING = 'waiting';
		public const string STATE_SENT = 'sent';
		public const string STATE_FAILED = 'failed';
		public const string STATE_UNKNOWN = 'unknown';
		public const string STATE_UNREADABLE = 'unreadable';

		// The shape of the key of a mail, see queue(): the name of the feature, a slash, what it makes of it
		private const string KEY_PATTERN = '~^[a-z][a-z0-9-]{1,62}/[\x21-\x7e]{1,128}$~D';

		// The address a mail to nobody is checked with when it is delivered: it has none, and the check of the rest is the one of a mail
		private const string VOID_ADDRESS = 'void@example.invalid';

		// Where the last failed call is kept - see failure()
		private const string STATUS_PATH = '/data/mail-status.php';

		// The transport callback. Called with the mail as an array -
		//
		//   [ 'to' => address, 'subject' => raw utf-8 subject, 'body' => html,
		//     'replyTo' => address or '', 'sender' => From/envelope address or '',
		//     'headers' => the header block mail() would get, 'sent' => null ]
		//
		// - and a transport that took the mail sets 'sent' to true (delivered)
		// or false (refused). A transport that leaves 'sent' at null passes
		// the mail on: to the next callback, and finally to mail()
		public const string TRANSPORT = '/nino/mail/send';

		/**
		 *	Send an html mail with a Reply-To header, unless the current client
		 *	address has hit the send cap for this window - '/nino/mail/cap' calls
		 *	an hour, 0 for none, see _cap(). The cap is a counter of the
		 *	toolset, \Nino\Feature::count() under the name 'ratelimit', keyed by the
		 *	address of the client (hashed there, as every key is): a kernel class
		 *	calls it because the counter is the same one a feature keeps its own
		 *	by. Both signals of the call are reset on entry - what the mail came
		 *	to in './nino/mail/results' and whether the cap refused it, see
		 *	rateLimited() - so that nothing of an earlier call is read as this
		 *	one's.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$to
		 *	@param		string		$subject
		 *	@param		string		$body
		 *	@param		string		$replyTo
		 *
		 *	@return 	bool										Whether the mail was delivered
		 */
		public static function send( array &$appData, string $to, string $subject, string $body, string $replyTo ): bool {

			$appData['./nino/mail/results']			= [];
			$appData['./nino/mail/ratelimited']	= false;

			if( \Nino\Feature::count( $appData, 'ratelimit', \Nino\Http::getClientIp( $appData ), self::_cap( $appData ), self::WINDOW ) === false ) {

				// Flagged rather than just reported through the return value, so
				// a caller can tell "we refused to send this" apart from "mail()
				// failed" - Form uses it to not record a submission whose mail
				// was never attempted (see \Nino\Form::handle() and rateLimited())
				$appData['./nino/mail/ratelimited'] = true;

				return false;
			}

			$sent = self::_deliver( $appData, $to, $subject, $body, $replyTo );

			$appData['./nino/mail/results'] = [ $sent ];
			self::_recordOutcome( $appData, $sent );

			return $sent;
		}

		/**
		 *	Send several mails that are one action of one visitor - a contact
		 *	form's owner notification and the confirmation that answers it -
		 *	for one hit of the per-address cap ('/nino/mail/cap', see send()).
		 *
		 *	Charging each mail separately made the cap count actions that are
		 *	not the visitor's: with two mails per submission and a cap of five,
		 *	the third submission in an hour was answered "sent" while nothing
		 *	left the server. The cap exists to stop a form being used as a
		 *	relay, and a relay is measured in submissions, not in envelopes.
		 *
		 *	What each mail came to is left in './nino/mail/results', one bool
		 *	per mail in the order they were given - and whether the cap refused
		 *	the call in './nino/mail/ratelimited', see rateLimited(). Both are
		 *	reset on every call, a cap refusal and an empty batch included, so a
		 *	caller never reads the answer of an earlier one. The failure record
		 *	(see failure()) is written once for the whole call: a failure if any
		 *	mail failed, cleared only if every one was delivered.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mails				[ { to, subject, body, replyTo }, ... ]
		 *
		 *	@return 	bool										Whether every mail was delivered
		 */
		public static function sendAll( array &$appData, array $mails ): bool {

			$appData['./nino/mail/results']			= [];
			$appData['./nino/mail/ratelimited']	= false;

			if( $mails === [] )
				return true;

			if( \Nino\Feature::count( $appData, 'ratelimit', \Nino\Http::getClientIp( $appData ), self::_cap( $appData ), self::WINDOW ) === false ) {
				$appData['./nino/mail/ratelimited'] = true;
				return false;
			}

			return self::_deliverAll( $appData, $mails );
		}

		/**
		 *	Send mails that the system sends on its own account - a report to the owner of the site that a
		 *	page request happened to set off - as sendAll() sends them, but not on the account of the client that
		 *	made the request: they neither count against the per-address cap of that client nor are refused by it.
		 *	The system has a cap of its own instead, SYSTEM_CAP calls an hour for the whole site, kept in a counter
		 *	of its own, so that a loop in a feature cannot make a storm. All the mails of one call - one mail to
		 *	several addresses - are one unit of it, and the first that is not delivered ends the call: what comes after
		 *	it is not tried, and './nino/mail/results' holds a bool for each that was. Everything else is sendAll()'s:
		 *	the refusal in rateLimited(), the transport, the failure record
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mails				[ { to, subject, body, replyTo }, ... ]
		 *	@param		string		$kind					SYSTEM, the report the system makes on its own, or TEST, a mail somebody asked for to see that it comes (TEST_CAP calls an hour of its own); any other value is refused and nothing is sent
		 *
		 *	@return 	bool										Whether every mail was delivered
		 */
		public static function sendSystem( array &$appData, array $mails, string $kind = self::SYSTEM ): bool {

			$appData['./nino/mail/results']			= [];
			$appData['./nino/mail/ratelimited']	= false;

			if( $mails === [] )
				return true;

			if( in_array( $kind, [ self::SYSTEM, self::TEST ], true ) === false )
				return false;

			if( \Nino\Feature::count( $appData, self::COUNTER_PREFIX. $kind, 'site', $kind === self::SYSTEM ? self::SYSTEM_CAP : self::TEST_CAP, self::WINDOW ) === false ) {
				$appData['./nino/mail/ratelimited'] = true;
				return false;
			}

			return self::_deliverAll( $appData, $mails, true );
		}

		/**
		 *	Keep a mail in the outbox, to be delivered after the response of this request or of a later one - by every
		 *	request of the site, by the command of the outbox for a cron job (see docs/development.md, Mail) - whatever
		 *	becomes of this request. The promise is the id that is answered: a mail that has one is delivered, or - once
		 *	it has had its attempts, MailQueue::attempts() of them, after waits of 1 min, 5 min, 30 min, 2 h and 12 h - is
		 *	given up on, said in the log and in failure() with a count and a time and counted in queued(); and it
		 *	is delivered at least once: a process that dies between the transport's answer and the removal from the outbox
		 *	leaves a mail that is sent a second time, and so does a transport that takes longer than MailQueue::LEASE seconds,
		 *	after which another deliverer may take the mail. A mail is handed to the transport only after the outbox has
		 *	written, and read back, that it was taken: where the disk is full or the file cannot be written nothing is sent.
		 *
		 *	What the outbox does not take it refuses here, at the call, and says why in queueRefused(): a mail that is no
		 *	mail ('invalid': an address that is none, a subject or a reply address that is too long, a key over 200 bytes,
		 *	a kind that is none), one over MailQueue::MAX_BYTES ('size'), an outbox that holds MailQueue::MAX_WAITING mails
		 *	already, or MailQueue::MAX_CLIENT of a visitor's ('full'), a key that belongs to another mail ('conflict'), a client over its cap ('ratelimited', and rateLimited() says so), a file that cannot be written
		 *	('unwritable'). Nothing is queued and nothing is dropped silently. The mail is cleaned as send() cleans it - no
		 *	line break, no nul byte in a subject - and checked again when it is delivered, since the file is one a person can edit.
		 *	An address or a reply address with a control character in it, a reply address that is a list, and a text that is no
		 *	utf-8 are no mail ('invalid'): send() strips what it cannot send, a mail that waits is refused at the call.
		 *
		 *	The kind VOID is a mail to nobody, for the feature whose answer must not tell whether there is a mail: a sign-in that is
		 *	answered alike for an address with an account and one without calls queue() with CLIENT in the first case and VOID in the
		 *	second, with the same mail, the same key and the same code round the call. A mail of the kind VOID is checked and cleaned as
		 *	one of the kind CLIENT - its address too, which has to be one - and goes the way of one: it counts against the cap of the
		 *	client and the places of the visitors, is written to the outbox, noted, asked for after the response, and has its key
		 *	remembered once it is delivered. What queue() answers for it is what it answers for a mail of the kind CLIENT, the id or ''
		 *	and the same reasons in queueRefused(). The deliverer takes it, lets it go without asking a transport, and it is no
		 *	failure and no line of the log: queued() counts it as it counts a mail that was delivered, and deliverQueued() reports it as
		 *	'void', not as 'delivered' (a mail that goes to nobody clears no failure of the dashboard). Of the address nothing is kept:
		 *	it is stored as a line of hyphens as long as the address was, and the hash of the content holds it as an HMAC with the secret
		 *	of the outbox (MailQueue::secret()), so that no file of the project holds the address or lets it be guessed, and a second
		 *	call under the key with another address is a conflict, as it is for a mail. The write is as large as that of a mail of the
		 *	kind CLIENT whose address, subject and body are as long - the caller passes the same template with placeholders of the same
		 *	length in both cases, and a fresh code in each. VOID is no brake and no way round one: it takes the places and the cap of a visitor as a mail does, and a feature
		 *	needs a brake of its own for each sender before it calls queue(), the same in both cases.
		 *
		 *	The kind OWNER is a round mail that an account of the workshop sets off, one mail to each of the people of a workshop. It is
		 *	taken only where the request carries the session of an account (\Nino\Auth::getCurrentUser()): a visitor, a session that
		 *	ended, a command with no session get '' and queueRefused() says 'session'; and only in a POST request ('method' for any
		 *	other). Which account may send it, and to whom, and whether the request carries the token of the form (CSRF), the feature
		 *	asks itself - the kernel keeps that nobody without a session can set the kind off with a link, and nothing more. A mail
		 *	with a key that waits, or was delivered and is still known to state() (fourteen days), is the same mail whenever it is
		 *	queued again - its owner's second click, in the middle of a wave or an hour after it, sends nothing twice - and a mail that
		 *	was given up on is queued anew; a feature that wants the same text sent again on purpose gives it a new key. It counts against no cap of the hour and against no place of the visitors or of the system, and a full wave
		 *	keeps neither of them out, as they do not keep it: it has MailQueue::MAX_OWNER places of its own. Its text waits in a file
		 *	of its own for each mail, so that a wave of 500 mails of 20 KB is no larger to the outbox than 100 mails of a few lines (see
		 *	MailQueue). It is delivered after the mails of the others, at a pace - MailQueue::OWNER_BURST mails at once, and then one for
		 *	each MailQueue::OWNER_GAP seconds, which is 20 mails a minute for a cron job that runs every minute: 200 mails take ten
		 *	minutes, 500 mails 25 - with the waits and the end of the other kinds as they are. What became of one mail of a wave is asked
		 *	of state() by its key.
		 *
		 *	A mail that is worth nothing after a time - a code that is good for fifteen minutes - carries 'expires' => seconds. From the
		 *	second the expiry names, which is no part of what the mail is (the same mail with another expiry is the same mail under its key), the mail is
		 *	delivered by nobody: not by a request, not by the command, not by a deliverer that took it a moment before and has not yet asked the
		 *	transport. The next run of the outbox - a request that finds the note of what is due, or the command - takes it out of the file with its
		 *	text, the file of its text for a mail of the owner included, and notes its key as STATE_FAILED. One that a transport had refused or thrown
		 *	on before is a sign of a transport that is down - the only one a site whose mails all expire would ever get, since none of them is
		 *	given up on - and counts as a mail given up on: a record without text, address and key, queued(), the failure of the dashboard and a
		 *	line in the log at most once an hour, and the failure goes with the next delivery that went out. One that no transport was asked for
		 *	- no request and no cron job came in time - leaves nothing but its state, and the log says once an hour that mails lapse unasked.
		 *	Where nothing runs it lies in the file until something does, and its text with it. A mail whose transport was asked before the second
		 *	stays in the file until its lease is over, so that the answer of the transport is noted - state() says 'waiting' until it is
		 *	'sent' or 'failed', and a second queue() under its key is the same mail; it cannot be called back, and it is at most as long as the
		 *	lease (MailQueue::LEASE). A clock that is set back after the second makes the mail due again. A mail to nobody (VOID) takes the field as
		 *	a mail does and the way of it, to the same operations and the same bytes for the same value.
		 *
		 *	The caps of the hour hold as they do for send() and sendSystem(), at the place where the mail is not
		 *	delivered without them: a mail of the kind CLIENT counts one try of the client's cap when it is queued, the
		 *	only moment the client is known; a mail of the kind SYSTEM or TEST counts one unit of the site's cap of that
		 *	kind when it is about to go out, and one that is over the cap waits for the next hour and is no failure.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mail					{ to, subject, body, replyTo, expires? } - 'expires', where it is given, is a whole number of seconds from now, at least MailQueue::MIN_EXPIRES (60) and at most MailQueue::MAX_AGE (172800), after which the mail is delivered by nobody and its text is nowhere; any other value is 'invalid'
		 *	@param		string		$kind					CLIENT, SYSTEM, TEST, VOID or OWNER
		 *	@param		string		$key					Names the mail, '<name of the feature>/<anything>' - the name is a slug and no other feature's: a second call with the key of a mail that waits and the same mail is that mail, and its id is answered, one with another mail is refused as 'conflict'; '' for none. A key is remembered for ten minutes after its mail went out, so that a request that comes twice is the mail that went out
		 *
		 *	@return 	string									The id of the mail, 16 hex digits; '' where it was refused
		 */
		public static function queue( array &$appData, array $mail, string $kind = self::CLIENT, string $key = '' ): string {

			$appData['./nino/mail/ratelimited']			= false;
			$appData['./nino/mail/queue/refused']		= '';

			// A key is the caller's own: it starts with a name of the feature or the module that queues - 'invite/2026-0042' - so that
			// another one cannot take the id of its mail
			if( in_array( $kind, [ self::CLIENT, self::SYSTEM, self::TEST, self::VOID, self::OWNER ], true ) === false || ( $key !== '' && preg_match( self::KEY_PATTERN, $key ) !== 1 ) )
				return self::_refused( $appData, 'invalid' );

			// A mail of the owner is taken from an account that is signed in, and from nobody else: not a visitor, not a request with a session that
			// ended or was never opened, not a cron job. Whether this account may send it is the feature's to ask; the kernel keeps that no visitor can
			if( $kind === self::OWNER && \Nino\Auth::getCurrentUser( $appData ) === false )
				return self::_refused( $appData, 'session' );

			// ...and from a request that changes something: a wave is set off by a POST, never by a link another page sends the browser of a signed-in
			// account to. The right and the CSRF token of that POST are the feature's to check (Csrf::blocked() does for a route); the kernel holds the
			// session and the method
			if( $kind === self::OWNER && ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' )
				return self::_refused( $appData, 'method' );

			// The expiry is the one field of the mail that is no text of it: whole seconds from now, at least MailQueue::MIN_EXPIRES and at most
			// MailQueue::MAX_AGE. A field that is there and is none of that is no mail ('invalid'), whatever it is - a float, a string, null
			$expires = 0;

			if( array_key_exists( 'expires', $mail ) === true ) {

				$expires = $mail['expires'];

				if( is_int( $expires ) === false || $expires < \Nino\MailQueue::MIN_EXPIRES || $expires > \Nino\MailQueue::MAX_AGE )
					return self::_refused( $appData, 'invalid' );
			}

			$checked = self::_checked( $mail );

			if( $checked['mail'] === null )
				return self::_refused( $appData, $checked['refused'] );

			// The secret of the outbox is asked for by both: the hash of the content of a mail to nobody is keyed with it, and a mail
			// that asks the disk for it too does what a mail to nobody does
			$secret		= \Nino\MailQueue::secret( $appData );

			if( $secret === '' )
				return self::_refused( $appData, 'unwritable' );

			// A mail to nobody is a mail of the kind CLIENT to the outbox, whose address is no address: nothing of the one that was given is
			// kept. The hash of its content, which a second call with the key compares, holds the address as a keyed hash (HMAC with the
			// secret of the outbox), so that a second call with another address is a conflict, as it is for a mail, and the stored value
			// tells no address to whoever has the file but not the secret
			$void			= $kind === self::VOID;
			$kind			= $void === true ? self::CLIENT : $kind;
			$hash			= $key === '' ? '' : hash( 'sha256', $key );
			$content	= $void === true ? hash_hmac( 'sha256', (string) json_encode( [ self::VOID, $checked['mail'] ] ), $secret ) : hash( 'sha256', (string) json_encode( [ $kind, $checked['mail'] ] ) );
			$known		= \Nino\MailQueue::peek( $appData, $kind, $hash );

			if( $known['id'] !== '' )
				return $known['content'] === $content ? $known['id'] : self::_refused( $appData, 'conflict' );

			if( $known['full'] === true )
				return self::_refused( $appData, 'full' );

			if( $kind === self::CLIENT && \Nino\Feature::count( $appData, 'ratelimit', \Nino\Http::getClientIp( $appData ), self::_cap( $appData ), self::WINDOW ) === false ) {

				$appData['./nino/mail/ratelimited'] = true;

				return self::_refused( $appData, 'ratelimited' );
			}

			// The hyphens take as many bytes in the file as the address does there, where a quote or a backslash is written with an escape
			// of its own: the file is as large as the mail's. A mail at the very limit of MAX_BYTES whose address is made of such characters
			// is refused as 'size', which is the one place the two kinds differ in what they take
			if( $void === true ) {

				$checked['mail']['to'] = str_repeat( '-', strlen( \Nino\MailQueue::encode( [ 'to' => $checked['mail']['to'] ] ) ) - strlen( \Nino\MailQueue::encode( [ 'to' => '' ] ) ) );

				if( strlen( $checked['mail']['to'] ) + strlen( $checked['mail']['subject'] ) + strlen( $checked['mail']['replyTo'] ) + strlen( $checked['mail']['body'] ) > \Nino\MailQueue::MAX_BYTES )
					return self::_refused( $appData, 'size' );
			}

			$added = \Nino\MailQueue::add( $appData, $checked['mail'], $kind, $hash, $content, $expires );

			return $added['id'] !== '' ? $added['id'] : self::_refused( $appData, $added['refused'] );
		}

		/**
		 *	Why the last queue() took no mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									'' where it took the mail or none was queued; else 'invalid', 'size', 'full', 'conflict', 'ratelimited', 'session', 'method' or 'unwritable'
		 */
		public static function queueRefused( array &$appData ): string {

			return (string) ( $appData['./nino/mail/queue/refused'] ?? '' );
		}

		/**
		 *	What became of the mail that was queued under a key: it waits, it was delivered, it was given up on. A feature that
		 *	lists the people of a round mail reads it for the mark by each name - states() for all of them at once. What is kept is
		 *	the key as a keyed hash, the word and the time: no address, no subject, no text. A mail that was delivered or given up on
		 *	stays known for MailQueue::KEEP_STATES seconds (fourteen days), and for the last MailQueue::MAX_STATES mails that did; what is
		 *	older or further back is unknown, as is a key nothing was queued under. A mail to nobody (VOID) is delivered when it was let go.
		 *	The key is the one that was given to queue(), in its form - a name of the feature and a slash - and the form is all there is
		 *	to it: a feature that asks for a key of another one gets the answer, as it gets the id of its mail from queue() (see the
		 *	key there). There is no call back into the code of a feature when a mail is delivered or given up on: a hook in the middle of
		 *	the delivery would be foreign code in the work of a request that nobody waits for - the feature asks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *
		 *	@return 	string									STATE_WAITING, STATE_SENT, STATE_FAILED, STATE_UNKNOWN, or STATE_UNREADABLE where a file of the outbox is there and cannot be read (what a mail came to is not known then)
		 */
		public static function state( array &$appData, string $key ): string {

			return self::states( $appData, [ $key ] )[$key] ?? self::STATE_UNKNOWN;
		}

		/**
		 *	What became of the mails that were queued under keys, for many at once: the files are read once for all of them, where
		 *	one call of state() for each of 200 people would read them 200 times
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$keys					[ key, ... ]; what is no key in form is unknown, and what is no string is left out
		 *
		 *	@return 	array										{ key: state } as state() answers one, for each key that is a string
		 */
		public static function states( array &$appData, array $keys ): array {

			$hashes = [];

			foreach( $keys as $key )
				if( is_string( $key ) === true && preg_match( self::KEY_PATTERN, $key ) === 1 )
					$hashes[$key] = hash( 'sha256', $key );

			$known = $hashes === [] ? [] : \Nino\MailQueue::states( $appData, array_values( array_unique( $hashes ) ) );
			$answer = [];

			foreach( $keys as $key )
				if( is_string( $key ) === true )
					$answer[$key] = isset( $hashes[$key] ) === true ? $known[ $hashes[$key] ] : self::STATE_UNKNOWN;

			return $answer;
		}

		/**
		 *	What the outbox holds, for a screen that tells the operator - without a word of any mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										{ waiting, failed, last, oldest, unreadable }: the mails that wait; the mails given up on within the last fourteen days; the minute ('Y-m-d H:i') of the last of them, null for none; the minute the oldest mail that waits was queued, null for none. An oldest that is hours old says that nothing delivers; an unreadable that is true says that a file of the outbox is there and cannot be read (its owner is another user), and what is counted is what could be read
		 */
		public static function queued( array &$appData ): array {

			return \Nino\MailQueue::counts( $appData );
		}

		/**
		 *	Deliver what is due in the outbox: up to $limit mails, one at a time, the oldest first, each taken by its own
		 *	lease (see MailQueue::claim()) - two deliverers at once send different mails and never one twice. Every request
		 *	that finds a mail due calls it after its response (see \Nino\Http::prepareAfter()), and so does the command of
		 *	the outbox. A mail is delivered the way send() delivers one, past its caps, and a mail that is not delivered
		 *	waits for its next attempt, or is given up on after the last one: one line in the log with the count - no
		 *	address, no subject - and the record of failure(), which the dashboard shows. A delivery that follows without
		 *	a mail given up on in it clears that record, as a call of send() does, unless mails given up on are still kept in
		 *	queued(). Where the outbox cannot be written nothing is sent and the answer says so, 'unwritable'.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int|null	$limit				The most mails to deliver, MailQueue::BATCH for none; the mails of the owner (OWNER) are delivered after the others, within the limit and at their pace - MailQueue::OWNER_BURST at once at most, then one for each MailQueue::OWNER_GAP seconds
		 *
		 *	@return 	array										{ delivered, void, retry, held, failed, unwritable } - the mails delivered; the mails to nobody (VOID) that were let go, which clear no failure; the ones that wait for another attempt; the ones that wait for the cap of the hour; the ones given up on; whether the outbox could not be written
		 */
		public static function deliverQueued( array &$appData, ?int $limit = null ): array {

			$result = [ 'delivered' => 0, 'void' => 0, 'retry' => 0, 'held' => 0, 'failed' => 0, 'unwritable' => false ];

			// With no outbox there is no mail to look at, and what the outbox left - a notice, the keys, a temporary file - still goes when its time is over
			if( is_file( \Nino\Filesystem::path( $appData, \Nino\MailQueue::WAITING ) ) === false ) {

				\Nino\MailQueue::sweep( $appData );

				return $result;
			}

			$token	= bin2hex( random_bytes( 8 ) );
			$left		= max( 1, $limit ?? \Nino\MailQueue::BATCH );
			$lapsed	= 0;
			$untried = 0;
			$orphaned = 0;

			while( $left > 0 ) {

				$gaveUp			= 0;
				$unwritable	= false;
				$lapses			= [];
				$entry			= \Nino\MailQueue::claim( $appData, $token, \Nino\MailQueue::now( $appData ), $gaveUp, $unwritable, $lapses );

				$result['failed'] += $gaveUp;
				$lapsed		+= $lapses['rejected'] ?? 0;
				$untried	+= $lapses['untried'] ?? 0;
				$orphaned	+= $lapses['orphaned'] ?? 0;

				if( $unwritable === true ) {
					$result['unwritable'] = true;
					break;
				}

				if( $entry === null )
					break;

				[ $outcome, $error ] = self::_deliverQueued( $appData, $entry );

				$settled = \Nino\MailQueue::settle( $appData, $token, $entry['id'], $outcome, $error, \Nino\MailQueue::now( $appData ) );

				// A mail that went out and is not written down as gone would go out again with the next call of this loop
				if( $settled['written'] === false ) {
					$result['unwritable'] = true;
					break;
				}

				if( $settled['gaveUp'] === true ) {

					$result['failed']++;

					// A mail that lapsed after a transport refused it is given up on by its expiry
					$lapsed += $outcome === 'lapsed' ? 1 : 0;
				}
				else if( $outcome === 'sent' )
					$result[ self::_isVoid( $entry ) === true ? 'void' : 'delivered' ]++;
				else if( $outcome === 'held' )
					$result['held']++;
				// A mail that lapsed is counted by none of the words: it was neither delivered nor given up on, and no attempt is waiting
				else if( $outcome !== 'lapsed' )
					$result['retry']++;

				$left--;
			}

			\Nino\MailQueue::release( $appData );

			if( $result['unwritable'] === true ) {

				// Said once an hour: a disk that stays full would fill the log with it
				if( \Nino\Feature::count( $appData, 'mail-queue-unwritable', 'site', 1, self::WINDOW ) === true )
					trigger_error( 'Mail queue: the outbox cannot be written, so nothing is delivered from it - check the disk of private/data.', E_USER_WARNING );
			}
			else if( $result['failed'] > 0 ) {

				// Mails that lapsed after a transport refused them are the one sign a site of codes has of a transport that is down, and one such mail
				// follows another every few minutes: said once an hour, as the other troubles of the outbox are. Any other mail given up on is said at once
				if( $result['failed'] > $lapsed || \Nino\Feature::count( $appData, 'mail-queue-lapsed', 'site', 1, self::WINDOW ) === true )
					trigger_error( 'Mail queue: '. $result['failed']. ' mail(s) could not be delivered and were given up on - see \\Nino\\Mail::queued().', E_USER_WARNING );

				self::_recordOutcome( $appData, false );
			}
			// The look at the record is made for a mail to nobody as for a mail, so that the outbox is read the same; only a mail that went out clears the failure
			else if( $result['delivered'] + $result['void'] > 0 && \Nino\MailQueue::given( $appData ) === 0 && $result['delivered'] > 0 )
				self::_recordOutcome( $appData, true );

			// Mails that lapsed with no transport asked for them: no run came in time, which is a cron job that does not run, or a site nobody visits
			if( $untried > 0 && \Nino\Feature::count( $appData, 'mail-queue-untried', 'site', 1, self::WINDOW ) === true )
				trigger_error( 'Mail queue: '. $untried. ' mail(s) lapsed before any run took them - does the cron job of the outbox run?', E_USER_WARNING );

			// A deliverer took the mail and did not finish - a process that died, a transport that never answered - which is no question for the cron job
			if( $orphaned > 0 && \Nino\Feature::count( $appData, 'mail-queue-orphaned', 'site', 1, self::WINDOW ) === true )
				trigger_error( 'Mail queue: '. $orphaned. ' mail(s) lapsed after a deliverer took them and did not finish - a process that died, or a transport that did not answer.', E_USER_WARNING );

			return $result;
		}

		/**
		 *	One mail of the outbox, past the checks: whether it went, and why not
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				{ id, kind, to, subject, body, replyTo, expires }
		 *
		 *	@return 	array										[ outcome, error ] - see MailQueue::settle()
		 */
		private static function _deliverQueued( array &$appData, array $entry ): array {

			// The last moment before a transport is asked: a mail whose expiry passed since it was taken is let go, a mail to nobody as well
			if( \Nino\MailQueue::lapsed( $entry, \Nino\MailQueue::now( $appData ) ) === true )
				return [ 'lapsed', '' ];

			// The text of a mail of the owner is in a file of its own, which was not there or could not be read: a mail that has no text cannot be
			// sent, and a text that cannot be read now is tried again after the wait
			if( ( $entry['aside'] ?? '' ) !== '' )
				return $entry['aside'] === 'unreadable' ? [ 'again', \Nino\MailQueue::UNREADABLE ] : [ 'invalid', \Nino\MailQueue::INVALID ];

			// The file is one a person can edit: what was queued is checked once more, a mail to nobody with an address that stands for none
			$void			= self::_isVoid( $entry );
			$checked	= self::_checked( $void === true ? [ 'to' => self::VOID_ADDRESS ] + $entry : $entry );

			if( $checked['mail'] === null )
				return [ 'invalid', \Nino\MailQueue::INVALID ];

			// Past the checks and before the transport: the one place a mail to nobody differs from a mail, and it is the end of its way. The
			// sender is asked as _deliver() asks it, so that the texts it reads, and the line a sender that is none says in the log, are the same
			if( $void === true ) {

				self::_getSender( $appData );

				return [ 'sent', '' ];
			}

			if( in_array( $entry['kind'], [ self::SYSTEM, self::TEST ], true ) === true && \Nino\Feature::count( $appData, self::COUNTER_PREFIX. $entry['kind'], 'site', $entry['kind'] === self::SYSTEM ? self::SYSTEM_CAP : self::TEST_CAP, self::WINDOW ) === false )
				return [ 'held', '' ];

			try {
				$delivered = self::_deliver( $appData, $checked['mail']['to'], $checked['mail']['subject'], $checked['mail']['body'], $checked['mail']['replyTo'] );
			}
			// A transport is the code of a feature, and what ends a request ends this one as well
			catch( \Nino\Failure $failure ) {
				throw $failure;
			}
			catch( \Throwable ) {
				return [ 'again', \Nino\MailQueue::FAILURE ];
			}

			return $delivered === true ? [ 'sent', '' ] : [ 'again', \Nino\MailQueue::REFUSED ];
		}

		/**
		 *	Whether a mail of the outbox is a mail to nobody (VOID): one of the kind CLIENT whose address is hyphens and nothing else,
		 *	which no address is
		 *
		 *	@param		array 		$entry				{ kind, to }
		 *
		 *	@return 	bool
		 */
		private static function _isVoid( array $entry ): bool {

			return $entry['kind'] === self::CLIENT && preg_match( '/^-+$/D', (string) $entry['to'] ) === 1;
		}

		/**
		 *	A mail as the outbox takes it: cleaned the way _deliver() cleans it, and checked
		 *
		 *	@param		array 		$mail					{ to, subject, body, replyTo }
		 *
		 *	@return 	array										{ mail, refused } - the mail as { to, subject, body, replyTo } with the address alone in 'to' and a reply address that is one or '', or null and 'invalid' or 'size'
		 */
		private static function _checked( array $mail ): array {

			$to				= is_string( $mail['to'] ?? null ) === true ? $mail['to'] : '';
			$subject	= is_string( $mail['subject'] ?? null ) === true ? $mail['subject'] : '';
			$replyTo	= is_string( $mail['replyTo'] ?? null ) === true ? $mail['replyTo'] : '';
			$body			= is_string( $mail['body'] ?? null ) === true ? $mail['body'] : '';

			// What send() strips from an address - a line break, a nul byte - a mail that waits is refused for: it would be another
			// address than the one that was given. A reply address is one, and no list. And the file is json, which holds utf-8
			if( preg_match( '/[\x00-\x1f\x7f]/', $to. $replyTo ) === 1 || preg_match( '/[,;]/', $replyTo ) === 1
				|| mb_check_encoding( $to. $subject. $replyTo. $body, 'UTF-8' ) === false )
				return [ 'mail' => null, 'refused' => 'invalid' ];

			$to				= self::_headerValue( $to );
			$subject	= self::_headerValue( $subject );
			$replyTo	= self::_headerValue( $replyTo );
			$body			= str_replace( "\0", '', $body );

			if( preg_match( '/<([^<>]+)>$/', $to, $addressMatch ) === 1 )
				$to = trim( $addressMatch[1] );

			if( strlen( $to ) > 254 || strlen( $subject ) > 998 || strlen( $replyTo ) > 320 || filter_var( $to, FILTER_VALIDATE_EMAIL ) === false )
				return [ 'mail' => null, 'refused' => 'invalid' ];

			$replyTo = self::_replyToValue( $replyTo, false );

			if( strlen( $to ) + strlen( $subject ) + strlen( $replyTo ) + strlen( $body ) > \Nino\MailQueue::MAX_BYTES )
				return [ 'mail' => null, 'refused' => 'size' ];

			return [ 'mail' => [ 'to' => $to, 'subject' => $subject, 'body' => $body, 'replyTo' => $replyTo ], 'refused' => '' ];
		}

		/**
		 *	queue() says no: the reason is kept for queueRefused()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$reason
		 *
		 *	@return 	string									''
		 */
		private static function _refused( array &$appData, string $reason ): string {

			$appData['./nino/mail/queue/refused'] = $reason;

			return '';
		}

		/**
		 *	The deliveries of sendAll() and sendSystem(), past their caps: each mail, its result, and the
		 *	failure record once for the call
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mails
		 *	@param		bool			$stop					End the call at the first mail that is not delivered
		 *
		 *	@return 	bool										Whether every mail was delivered
		 */
		private static function _deliverAll( array &$appData, array $mails, bool $stop = false ): bool {

			$sent = true;

			foreach( $mails as $mail ) {

				$delivered = self::_deliver( $appData,
					(string) ( $mail['to'] ?? '' ),
					(string) ( $mail['subject'] ?? '' ),
					(string) ( $mail['body'] ?? '' ),
					(string) ( $mail['replyTo'] ?? '' ) );

				$appData['./nino/mail/results'][] = $delivered;
				$sent = $delivered && $sent;

				if( $delivered === false && $stop === true )
					break;
			}

			self::_recordOutcome( $appData, $sent );

			return $sent;
		}

		/**
		 *	Whether the last send() or sendAll() was refused by the per-address cap -
		 *	which is not a mail() that failed: nothing was attempted, and a
		 *	caller that records or reports the failure answers differently (a
		 *	form answers 429 and keeps no submission). Per call, not sticky: each
		 *	of the two sets it again, to false where the call was not refused, so
		 *	a caller that sends twice reads the answer of the second. Always false
		 *	where no cap applies: the key is 0.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function rateLimited( array &$appData ): bool {

			return ( $appData['./nino/mail/ratelimited'] ?? false ) === true;
		}

		/**
		 *	The calls an hour one client address may make: the key
		 *	'/nino/mail/cap' of config.php (its default in \Nino\AppData::DEFAULTS).
		 *	0 is no cap - \Nino\Feature::count() then counts nothing and touches
		 *	no file.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		private static function _cap( array &$appData ): int {

			return (int) ( $appData['/nino/mail/cap'] ?? \Nino\AppData::DEFAULTS['/nino/mail/cap'] );
		}

		/**
		 *	When mail last failed, as the dashboard shows it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							{ since, last, count } - 'Y-m-d H:i' twice and the number of
		 *																	failed calls since the first - or null where the last call
		 *																	went through, none was ever made, or the file is not one
		 *																	this class wrote
		 */
		public static function failure( array &$appData ): ?array {

			return self::_record( \Nino\Filesystem::getFileContent( $appData, self::STATUS_PATH, [] ) );
		}

		/**
		 *	What a status file holds, or null where it is not one this class
		 *	wrote - the file is plain php and editable, so nothing in it is
		 *	trusted: both dates have to be minutes, the count a whole number
		 *	of at least one
		 *
		 *	@param		mixed			$state
		 *
		 *	@return 	array|null
		 */
		private static function _record( mixed $state ): ?array {

			if( is_array( $state ) === false )
				return null;

			$since = $state['since'] ?? null;
			$last	 = $state['last'] ?? null;
			$count = $state['count'] ?? null;

			foreach( [ $since, $last ] as $date )
				if( is_string( $date ) === false || preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $date ) !== 1 )
					return null;

			if( is_int( $count ) === false || $count < 1 )
				return null;

			return [ 'since' => $since, 'last' => $last, 'count' => $count ];
		}

		/**
		 *	Keep what one send() or sendAll() call came to: a failure raises
		 *	the record - first date kept, last date and count moved on - and a
		 *	call that delivered everything clears it. Once per call, after its
		 *	deliveries, and never for a call that sent nothing (the cap, an
		 *	empty batch). A record that cannot be written changes nothing for
		 *	the caller: the mail went or did not go either way.
		 *
		 *	Holds no address, no subject and no word a transport said - it is
		 *	shown to everybody who opens the workbench's dashboard
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$allDelivered	Whether every mail of the call was delivered
		 *
		 *	@return 	void
		 */
		private static function _recordOutcome( array &$appData, bool $allDelivered ): void {

			// Nothing to clear, and no lock made for it
			if( $allDelivered === true && \Nino\Filesystem::getFileContent( $appData, self::STATUS_PATH, [] ) === [] )
				return;

			$now = date( 'Y-m-d H:i' );

			\Nino\Filesystem::mutate( $appData, self::STATUS_PATH, function( mixed $state ) use ( $allDelivered, $now ): ?array {

				// Cleared, and no file made where there was none
				if( $allDelivered === true )
					return ( $state === [] || $state === null ) ? null : [];

				$record = self::_record( $state );

				return [
					'since'	=> $record['since'] ?? $now,
					'last'	=> $now,
					'count'	=> ( $record['count'] ?? 0 ) + 1,
				];
			}, [] );
		}

		/**
		 *	One mail, past the cap: header cleaning, the From and Reply-To
		 *	lines, the transport callback, and mail() when no transport took
		 *	it. Never charges the cap itself - send() and sendAll() decide how
		 *	many hits a caller's action is worth
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$to
		 *	@param		string		$subject
		 *	@param		string		$body
		 *	@param		string		$replyTo
		 *
		 *	@return 	bool
		 */
		private static function _deliver( array &$appData, string $to, string $subject, string $body, string $replyTo ): bool {

			// Everything that ends up on a header line gets its CR/LF stripped.
			// $subject comes from an admin-editable textfill and send() is public
			// api, so without this a newline in any of them injects headers of
			// its own (a Bcc: to somewhere else being the obvious one).
			$to				= self::_headerValue( $to );
			$subject	= self::_headerValue( $subject );
			$replyTo	= self::_headerValue( $replyTo );

			// A nul byte goes too, from the body as well: mail() refuses one in
			// any argument with a ValueError rather than failing to send, and
			// a form field can carry one - that was a 500 for the visitor
			// instead of the false this class promises. Before the transport
			// callback, so no transport has to know either
			$body = str_replace( "\0", '', $body );

			// _headerValue() only strips CR/LF - mail()'s $to also accepts a
			// plain comma-separated list with no newline involved at all, so
			// an admin-editable field this comes from (Form's owner notify
			// uses '[[/project/mail/address/owner]]' verbatim) could silently
			// gain a second, invisible recipient. One address only: nothing
			// here currently needs send() to notify more than one.

			// FILTER_VALIDATE_EMAIL rejects the display-name form, and
			// "Max Mustermann <max@site.de>" is a plausible thing for a site owner
			// to have typed into '[[/project/mail/address/owner]]' back when
			// mail() accepted it. Take the address out of the angle brackets
			// rather than turn an existing, working install into one that
			// silently sends nothing.
			if( preg_match( '/<([^<>]+)>$/', $to, $addressMatch ) === 1 )
				$to = trim( $addressMatch[1] );

			if( filter_var( $to, FILTER_VALIDATE_EMAIL ) === false )
				return false;

			$replyTo = self::_replyToValue( $replyTo );

			// Without a From: the mail goes out as the webserver user
			// (www-data@some-host), which is the single most reliable way to end
			// up in a spam folder. The envelope sender (-f) matters just as
			// much: it is what SPF is checked against, and PHP defaults it to
			// the same webserver user.
			$sender = self::_getSender( $appData );

			$headers = 'MIME-Version: 1.0';
			$headers .= "\r\n". 'Content-Type: text/html; charset=UTF-8';

			if( $sender !== '' )
				$headers .= "\r\n". 'From: '. $sender;

			if( $replyTo !== '' )
				$headers .= "\r\n". 'Reply-To: '. $replyTo;

			// Another transport gets the mail first - cleaned and capped, with
			// the subject still raw, since how a subject is encoded is the
			// transport's business. Only when none took it does mail() run
			if( isset( $appData['./nino/callbacks'][ self::TRANSPORT ] ) === true ) {

				$mail = [ 'to' => $to, 'subject' => $subject, 'body' => $body, 'replyTo' => $replyTo, 'sender' => $sender, 'headers' => $headers, 'sent' => null ];

				\Nino\Callbacks::doCallbacks( $appData, self::TRANSPORT, $mail );

				if( is_bool( $mail['sent'] ?? null ) === true )
					return $mail['sent'];
			}

			// Encoded after _headerValue() above, not before: the CR/LF strip
			// has to run against the raw, untrusted subject - encoding first
			// would mean sanitizing mb_encode_mimeheader()'s output instead of
			// the actual input
			$subject = mb_encode_mimeheader( $subject, 'UTF-8', 'B' );

			// -f only for an address that validated - the parameter goes to the
			// sendmail command line, so it must never carry anything unchecked
			try {
				return ( $sender !== '' )
					? mail( $to, $subject, $body, $headers, '-f'. $sender )
					: mail( $to, $subject, $body, $headers );
			} catch( \ValueError ) {
				// An argument mail() refuses outright, which the strips above
				// did not know about: still a mail that did not go out, which
				// is what false has always meant here
				return false;
			}
		}

		// A single header value with anything that could start a new header
		// line removed - and the nul byte mail() refuses (see _deliver())
		private static function _headerValue( string $value ): string {

			return trim( str_replace( [ "\r", "\n", "\0" ], '', $value ) );
		}

		/**
		 *	A Reply-To header value, or '' where what was given is not one.
		 *
		 *	Every other address on a header line here is checked: $to with
		 *	FILTER_VALIDATE_EMAIL, and refusing the mail outright, and
		 *	_getSender() the same for From, which it drops rather than "passing
		 *	something unchecked to sendmail" - its own words. The reply address
		 *	had neither, and it comes from the same place those two do: an
		 *	admin-editable textfill, read through renderHtml(). A fill a project
		 *	never installed renders as its own literal, so
		 *	'[[/project/mail/address/owner]]' went out as the Reply-To header
		 *	verbatim - and so did whatever else somebody had typed into the Text
		 *	panel.
		 *	That fill is the Form module's install unit's, and the module is one
		 *	the wizard offers rather than one every project has, so a project
		 *	running the Newsletter feature without the contact form sent every
		 *	confirmation mail with a header naming a fill instead of a mailbox.
		 *
		 *	Dropped rather than refused: the recipient and the body are fine,
		 *	and a confirmation nobody receives because the owner mistyped a
		 *	textfill is worse than one nobody can reply to. Recorded once per
		 *	mail through the kernel's "record this and carry on" channel, since
		 *	nothing else would ever tell the operator why replies stopped
		 *	arriving.
		 *
		 *	The display-name form stays whole. 'Max Mustermann
		 *	<max@example.com>' is a valid value for this header, unlike mail()'s
		 *	own $to, and it is a plausible thing for a site owner to have
		 *	typed; only its address part has to hold up.
		 *
		 *	The outbox says it without the value (a mail that waits is personal data, and nothing of it is in a log):
		 *	$quote false.
		 *
		 *	@param		string		$replyTo			Already through _headerValue()
		 *	@param		bool			$quote				Whether the warning names the value
		 *
		 *	@return 	string
		 */
		private static function _replyToValue( string $replyTo, bool $quote = true ): string {

			if( $replyTo === '' )
				return '';

			$address = ( preg_match( '/<([^<>]+)>$/', $replyTo, $addressMatch ) === 1 ) ? trim( $addressMatch[1] ) : $replyTo;

			if( filter_var( $address, FILTER_VALIDATE_EMAIL ) !== false )
				return $replyTo;

			trigger_error( $quote === true
				? 'Mail: \''. $replyTo. '\' is no reply address - the mail went out without a Reply-To header.'
				: 'Mail: a reply address is none - the mail goes out without a Reply-To header.', E_USER_WARNING );

			return '';
		}

		// The address to send as: the '[[/project/mail/address/envelope]]'
		// textfill where it is set, otherwise the site owner's address from the
		// same Text values.
		// Two fills rather than a config.php key and a fill, because both are
		// the operator's to set and the Text panel is where they set the
		// other one. Separate because the envelope sender has to be an address
		// the sending host is allowed to send for (spf/dmarc), which is not
		// necessarily the mailbox replies should go to. A sender fill that is
		// no address - a typo in the Text panel, an unresolved fill rendering
		// as its own name - falls back to the owner address rather than
		// costing every mail its From, and says so in the log. Returns '' if
		// neither is a valid address, in which case send() simply omits
		// From:/-f rather than passing something unchecked to sendmail.
		private static function _getSender( array &$appData ): string {

			$sender = self::_headerValue( \Nino\Html::putFills( $appData, '[[/project/mail/address/envelope]]', [] ) );

			if( filter_var( $sender, FILTER_VALIDATE_EMAIL ) === false ) {

				if( $sender !== '' )
					trigger_error( 'Mail: \''. $sender. '\' is no sender address - the mail went out as the owner address.', E_USER_WARNING );

				$sender = self::_headerValue( \Nino\Html::putFills( $appData, '[[/project/mail/address/owner]]', [] ) );
			}

			return ( filter_var( $sender, FILTER_VALIDATE_EMAIL ) !== false ) ? $sender : '';
		}
	}
}
