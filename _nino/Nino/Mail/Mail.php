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
	// \Nino\Feature::count() in /data/ratelimit.php. Over budget, send()
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
		 *	The caps of the hour hold as they do for send() and sendSystem(), at the place where the mail is not
		 *	delivered without them: a mail of the kind CLIENT counts one try of the client's cap when it is queued, the
		 *	only moment the client is known; a mail of the kind SYSTEM or TEST counts one unit of the site's cap of that
		 *	kind when it is about to go out, and one that is over the cap waits for the next hour and is no failure.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$mail					{ to, subject, body, replyTo }
		 *	@param		string		$kind					CLIENT, SYSTEM or TEST
		 *	@param		string		$key					Names the mail, '<name of the feature>/<anything>' - the name is a slug and no other feature's: a second call with the key of a mail that waits and the same mail is that mail, and its id is answered, one with another mail is refused as 'conflict'; '' for none. A key is remembered for ten minutes after its mail went out, so that a request that comes twice is the mail that went out
		 *
		 *	@return 	string									The id of the mail, 16 hex digits; '' where it was refused
		 */
		public static function queue( array &$appData, array $mail, string $kind = self::CLIENT, string $key = '' ): string {

			$appData['./nino/mail/ratelimited']			= false;
			$appData['./nino/mail/queue/refused']		= '';

			// A key is the caller's own: it starts with a name of the feature or the module that queues - 'invite/2026-0042' - so that
			// another one cannot take the id of its mail
			if( in_array( $kind, [ self::CLIENT, self::SYSTEM, self::TEST ], true ) === false || ( $key !== '' && preg_match( '~^[a-z][a-z0-9-]{1,62}/[\x21-\x7e]{1,128}$~D', $key ) !== 1 ) )
				return self::_refused( $appData, 'invalid' );

			$checked = self::_checked( $mail );

			if( $checked['mail'] === null )
				return self::_refused( $appData, $checked['refused'] );

			$hash			= $key === '' ? '' : hash( 'sha256', $key );
			$content	= hash( 'sha256', (string) json_encode( [ $kind, $checked['mail'] ] ) );
			$known		= \Nino\MailQueue::peek( $appData, $kind, $hash );

			if( $known['id'] !== '' )
				return $known['content'] === $content ? $known['id'] : self::_refused( $appData, 'conflict' );

			if( $known['full'] === true )
				return self::_refused( $appData, 'full' );

			if( $kind === self::CLIENT && \Nino\Feature::count( $appData, 'ratelimit', \Nino\Http::getClientIp( $appData ), self::_cap( $appData ), self::WINDOW ) === false ) {

				$appData['./nino/mail/ratelimited'] = true;

				return self::_refused( $appData, 'ratelimited' );
			}

			$added = \Nino\MailQueue::add( $appData, $checked['mail'], $kind, $hash, $content );

			return $added['id'] !== '' ? $added['id'] : self::_refused( $appData, $added['refused'] );
		}

		/**
		 *	Why the last queue() took no mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									'' where it took the mail or none was queued; else 'invalid', 'size', 'full', 'conflict', 'ratelimited' or 'unwritable'
		 */
		public static function queueRefused( array &$appData ): string {

			return (string) ( $appData['./nino/mail/queue/refused'] ?? '' );
		}

		/**
		 *	What the outbox holds, for a screen that tells the operator - without a word of any mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										{ waiting, failed, last, oldest }: the mails that wait; the mails given up on within the last fourteen days; the minute ('Y-m-d H:i') of the last of them, null for none; the minute the oldest mail that waits was queued, null for none. An oldest that is hours old says that nothing delivers
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
		 *	@param		int|null	$limit				The most mails to deliver, MailQueue::BATCH for none
		 *
		 *	@return 	array										{ delivered, retry, held, failed, unwritable } - the mails delivered; the ones that wait for another attempt; the ones that wait for the cap of the hour; the ones given up on; whether the outbox could not be written
		 */
		public static function deliverQueued( array &$appData, ?int $limit = null ): array {

			$result = [ 'delivered' => 0, 'retry' => 0, 'held' => 0, 'failed' => 0, 'unwritable' => false ];

			if( is_file( \Nino\Filesystem::path( $appData, \Nino\MailQueue::WAITING ) ) === false )
				return $result;

			$token	= bin2hex( random_bytes( 8 ) );
			$left		= max( 1, $limit ?? \Nino\MailQueue::BATCH );

			while( $left > 0 ) {

				$gaveUp			= 0;
				$unwritable	= false;
				$entry			= \Nino\MailQueue::claim( $appData, $token, \Nino\MailQueue::now( $appData ), $gaveUp, $unwritable );

				$result['failed'] += $gaveUp;

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

				if( $settled['gaveUp'] === true )
					$result['failed']++;
				else if( $outcome === 'sent' )
					$result['delivered']++;
				else if( $outcome === 'held' )
					$result['held']++;
				else
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

				trigger_error( 'Mail queue: '. $result['failed']. ' mail(s) could not be delivered and were given up on - see \\Nino\\Mail::queued().', E_USER_WARNING );
				self::_recordOutcome( $appData, false );
			}
			else if( $result['delivered'] > 0 && \Nino\MailQueue::counts( $appData )['failed'] === 0 )
				self::_recordOutcome( $appData, true );

			return $result;
		}

		/**
		 *	One mail of the outbox, past the checks: whether it went, and why not
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				{ id, kind, to, subject, body, replyTo }
		 *
		 *	@return 	array										[ outcome, error ] - see MailQueue::settle()
		 */
		private static function _deliverQueued( array &$appData, array $entry ): array {

			// The file is one a person can edit: what was queued is checked once more
			$checked = self::_checked( $entry );

			if( $checked['mail'] === null )
				return [ 'invalid', \Nino\MailQueue::INVALID ];

			if( $entry['kind'] !== self::CLIENT && \Nino\Feature::count( $appData, self::COUNTER_PREFIX. $entry['kind'], 'site', $entry['kind'] === self::SYSTEM ? self::SYSTEM_CAP : self::TEST_CAP, self::WINDOW ) === false )
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
