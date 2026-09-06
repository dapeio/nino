<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Mail					Thin wrapper around mail() with shared headers and a per-ip send cap
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Mail - thin wrapper around mail(): centralizes Content-Type/Reply-To
	// headers (one place for a project-wide change) and a shared per-ip
	// send cap (5/hour, /data/ratelimit.php) - over budget, send() just
	// returns false, the same outcome as any other mail() failure, which
	// Form/Newsletter already treat as non-fatal, so a rate-limited burst
	// just becomes silently-missing mail
	class Mail {

		private const int MAX_TRIES 	= 5;
		private const int WINDOW 		= 3600;

		// Send an html mail with a Reply-To header, unless the current
		// client ip has hit the send cap for this window
		public static function send( array &$appData, string $to, string $subject, string $body, string $replyTo ): bool {

			if( self::_hit( $appData, \Nino\Http::getClientIp() ) === false ) {

				// Flagged rather than just reported through the return value, so
				// a caller can tell "we refused to send this" apart from "mail()
				// failed" - Form uses it to not record a submission whose mail
				// was never attempted (see its callbackResponse())
				$appData['./nino/mail/ratelimited'] = true;

				return false;
			}

			// Everything that ends up on a header line gets its CR/LF stripped.
			// $subject comes from an admin-editable textfill and send() is public
			// api, so without this a newline in any of them injects headers of
			// its own (a Bcc: to somewhere else being the obvious one).
			$to				= self::_headerValue( $to );
			$subject	= self::_headerValue( $subject );
			$replyTo	= self::_headerValue( $replyTo );

			// _headerValue() only strips CR/LF - mail()'s $to also accepts a
			// plain comma-separated list with no newline involved at all, so
			// an admin-editable field this comes from (Form's owner notify
			// uses '[[/form/email/owner]]' verbatim) could silently gain a
			// second, invisible recipient. One address only: nothing here
			// currently needs send() to notify more than one.

			// FILTER_VALIDATE_EMAIL rejects the display-name form, and
			// "Max Mustermann <max@site.de>" is a plausible thing for a site owner
			// to have typed into '[[/form/email/owner]]' back when mail() accepted
			// it. Take the address out of the angle brackets rather than turn an
			// existing, working install into one that silently sends nothing.
			if( preg_match( '/<([^<>]+)>$/', $to, $addressMatch ) === 1 )
				$to = trim( $addressMatch[1] );

			if( filter_var( $to, FILTER_VALIDATE_EMAIL ) === false )
				return false;

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

			// Encoded after _headerValue() above, not before: the CR/LF strip
			// has to run against the raw, untrusted subject - encoding first
			// would mean sanitizing mb_encode_mimeheader()'s output instead of
			// the actual input
			$subject = mb_encode_mimeheader( $subject, 'UTF-8', 'B' );

			// -f only for an address that validated - the parameter goes to the
			// sendmail command line, so it must never carry anything unchecked
			return ( $sender !== '' )
				? mail( $to, $subject, $body, $headers, '-f'. $sender )
				: mail( $to, $subject, $body, $headers );
		}

		// A single header value with anything that could start a new header
		// line removed
		private static function _headerValue( string $value ): string {

			return trim( str_replace( [ "\r", "\n" ], '', $value ) );
		}

		// The address to send as: '/nino/mail/sender' from config.php if set,
		// otherwise the site owner's address from the Text values. Configurable
		// because the envelope sender has to be an address the sending host is
		// allowed to send for (spf/dmarc), which is not necessarily the mailbox
		// replies should go to. Returns '' if neither is a valid address, in
		// which case send() simply omits From:/-f rather than passing something
		// unchecked to sendmail.
		private static function _getSender( array &$appData ): string {

			$sender = self::_headerValue( (string) ( $appData['/nino/mail/sender'] ?? '' ) );

			if( $sender === '' )
				$sender = self::_headerValue( \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' ) );

			return ( filter_var( $sender, FILTER_VALIDATE_EMAIL ) !== false ) ? $sender : '';
		}

		// Register one send attempt for $key and report whether it's
		// still within budget for the current window. Any key (not just
		// the one being checked) whose window has already elapsed is
		// dropped on write, so the state file can't grow without bound.
		private static function _hit( array &$appData, string $key ): bool {

			$path 	= '/data/ratelimit.php';
			$now 		= time();
			$tries	= null;

			// An unlocked read-modify-write lets two parallel sends read the
			// same counter and write back the same +1, bypassing the cap by
			// simply firing requests concurrently - which is what a sending
			// burst looks like anyway. No reliable counter, no send: an
			// unreliable cap is worse than a closed one here.
			$written = \Nino\Filesystem::mutate( $appData, $path, function( array $state ) use ( $key, $now, &$tries ): array {

				foreach( $state as $stateKey => $entry )
					if( ( $entry['reset'] ?? 0 ) <= $now )
						unset( $state[$stateKey] );

				$entry 					= $state[$key] ?? [ 'tries' => 0, 'reset' => $now + self::WINDOW ];
				$entry['tries']	= (int) $entry['tries'] + 1;
				$state[$key] 		= $entry;
				$tries 					= $entry['tries'];

				return $state;
			} );

			return $written === true && $tries <= self::MAX_TRIES;
		}
	}
}
