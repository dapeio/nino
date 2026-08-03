<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Newsletter				see Nino.php's Modules section for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Newsletter				Double opt-in newsletter signup, everything under the
	 *										/.newsletter uri: POST /.newsletter validates the address
	 *										and mails a confirmation link - nothing is subscribed
	 *										until GET /.newsletter?confirm=<token> is visited. The
	 *										same per-subscriber token drives the self-service
	 *										unsubscribe (GET /.newsletter?unsubscribe=<token>, url
	 *										via getUnsubscribeLink() - append it to any outgoing
	 *										mail). Persisted as one growing, email-deduped
	 *										/data/newsletter.php - no per-month bucketing (unlike
	 *										Form::_record()'s forms.<Y-m>.php) since a subscriber
	 *										list isn't naturally date-bucketed the way individual
	 *										contact inquiries are. Read independently by
	 *										Admin\Newsletter in _admin/Admin.php.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Newsletter {

		private const int MAX_FIELD_LENGTH = 1000;

		private const string PATH = '/data/newsletter.php';

		/**
		 *	Register both /.newsletter routes and their handlers - the routes
		 *	are registered here rather than hand-declared in config.php
		 *	(unlike the page GET routes, which are genuinely per-project
		 *	content): this module owns the endpoints it needs, so a project
		 *	enabling Shortcodes\Newsletter (see config.php's /nino/modules)
		 *	gets a working signup + confirm/unsubscribe flow without also
		 *	having to remember to wire up the routes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			$appData['/nino/http/routes']['POST://.newsletter']	= [ 'uri' => '/.newsletter' ];
			$appData['/nino/http/routes']['GET://.newsletter']		= [ 'uri' => '/.newsletter', 'body' => '[template '. ( $appData['/nino/newsletter/page-template'] ?? '/templates/page-newsletter' ). ']' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.newsletter', [ self::class, 'callbackResponse' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://.newsletter', [ self::class, 'callbackAction' ] );
		}

		/**
		 *	Validate a signup request and mail the confirmation link - the
		 *	actual subscription only happens once that link is visited
		 *	(double opt-in, see callbackAction())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Respect a rejection from the earlier global Csrf callback - same guard
			// Form/Auth::callbackResponse use
			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			// Not escaped before validating (see Form::callbackResponse):
			// escaping an address with an apostrophe first would turn it into
			// "&#039;", either failing validation or getting stored in a form
			// getUnsubscribeLink() can never match again
			$email 		= mb_strtolower( substr( trim( (string) ( $_POST['email'] ?? '' ) ), 0, self::MAX_FIELD_LENGTH ) );
			$location = substr( trim( (string) ( $_POST['location'] ?? '' ) ), 0, self::MAX_FIELD_LENGTH );

			// Email missing/invalid
			if( $email === '' || filter_var( $email, FILTER_VALIDATE_EMAIL ) === false )
				$request['/nino/http/response']['statusCode'] = 400;

			// Honeypot is filled
			if( $location !== '' )
				$request['/nino/http/response']['statusCode'] = 418;

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return;

			self::_requestSignup( $appData, $email );

			// Always the same answer. Reporting 'new' vs 'existing' told anyone
			// who asked whether a given address is on the list - the signup form
			// is public, so that is a free subscriber-enumeration oracle. With
			// double opt-in there is nothing to tell the visitor either way
			// except "check your inbox".
			$request['/nino/http/response']['statusCode'] = 200;
			$request['/nino/http/response']['body'] 			= [ 'status' => 'ok' ];
		}

		/**
		 *	Handle a visited confirm/unsubscribe link (GET /.newsletter
		 *	?confirm=<token> / ?unsubscribe=<token>) and prepare the fills
		 *	the page template renders the outcome with. An unknown or
		 *	missing token answers 404 - the page stays a friendly html page
		 *	either way
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackAction( array &$appData, array &$request ): void {

			$query 	= $request['/nino/http/request']['query'] ?? [];
			$result = 'invalid';

			if( isset( $query['confirm'] ) === true )
				$result = ( self::_confirm( $appData, (string) $query['confirm'] ) === true ) ? 'confirmed' : 'invalid';
			else if( isset( $query['unsubscribe'] ) === true )
				$result = ( self::_unsubscribe( $appData, (string) $query['unsubscribe'] ) === true ) ? 'unsubscribed' : 'invalid';

			if( $result === 'invalid' )
				$request['/nino/http/response']['statusCode'] = 404;

			// Nested fills - the outer key is what the page template uses,
			// the inner one resolves per-locale from text/*.php
			\Nino\Html::addFills( $appData, [
				'[[/newsletter/page/title]]'	=> '[[/newsletter/page/'. $result. '/title]]',
				'[[/newsletter/page/text]]'		=> '[[/newsletter/page/'. $result. '/text]]',
			], '*' );
		}

		/**
		 *	Build the absolute self-service unsubscribe url for a subscribed
		 *	(or still pending) email - append it to any outgoing newsletter
		 *	mail. Same https://[[/website/url]] convention the sitemap
		 *	template uses for absolute urls
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$email				Subscriber to build the url for
		 *
		 *	@return 	string | false							Unsubscribe url, or false for an unknown email
		 */
		public static function getUnsubscribeLink( array &$appData, string $email ): string|false {

			$email = mb_strtolower( trim( $email ) );

			foreach( \Nino\Filesystem::getFileContent( $appData, self::PATH, [] ) as $entry )
				if( ( $entry['email'] ?? null ) === $email && empty( $entry['token'] ) === false )
					return self::_getActionUrl( $appData, 'unsubscribe', $entry['token'] );

			return false;
		}

		/**
		 *	Record a pending signup and mail its confirmation link - never
		 *	thrown, same reasoning as Form::_record: a storage failure must
		 *	not turn a visitor-facing signup into a 500. An email that is
		 *	already subscribed reports 'existing' (no mail); a still-pending
		 *	one gets its mail again with the same token (the first one may
		 *	simply never have arrived). Entries written before the double
		 *	opt-in flow (no status/token fields) count as subscribed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$email				Validated email address
		 *
		 *	@return 	string									'new' when a confirmation mail went out, 'existing' otherwise
		 */
		private static function _requestSignup( array &$appData, string $email ): string {

			try {

				$existing 	= false;
				$token 			= null;

				\Nino\Filesystem::mutate( $appData, self::PATH, function( array $entries ) use ( $email, &$existing, &$token ): ?array {

					foreach( $entries as $entryKey => $entry ) {

						if( ( $entry['email'] ?? null ) !== $email )
							continue;

						if( ( $entry['status'] ?? 'subscribed' ) === 'subscribed' ) {
							$existing = true;
							return null;
						}

						$token = $entry['token'];
						$entries[$entryKey]['date'] = date( 'Y-m-d H:i:s' );
					}

					if( $token === null ) {
						$token 		 = bin2hex( random_bytes( 16 ) );
						$entries[] = [
							'email'		=> $email,
							'token'		=> $token,
							'status'	=> 'pending',
							'date'		=> date( 'Y-m-d H:i:s' ),
							'ip'			=> \Nino\Http::getClientIp(),
						];
					}

					return $entries;
				} );

				if( $existing === true )
					return 'existing';

				// $token is only still null if the lock itself couldn't be taken
				// (mutate()'s callback never ran) - nothing was recorded, so
				// there is nothing to mail a confirm link for either
				if( $token === null )
					return 'existing';

				self::_sendConfirmMail( $appData, $email, $token );

				return 'new';

			} catch( \Throwable $e ) {
				trigger_error( 'Newsletter signup write failed: '. $e->getMessage() );
				return 'existing';
			}
		}

		/**
		 *	Send the confirmation mail carrying the signup link, in the
		 *	visitor's own current locale (no owner-locale juggling needed
		 *	here, unlike Form - there's no separate owner notification to
		 *	send). Template path is config-driven (/nino/newsletter/
		 *	confirm-template) so a project can swap it; recipient/subject/
		 *	body copy stay Text-driven same as Form's mail-user/mail-owner.
		 *	A mail failure is not surfaced to the visitor - the pending
		 *	entry is already recorded, a re-submit simply re-sends
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$email				Recipient
		 *	@param		string		$token				The pending entry's confirm token
		 *
		 *	@return 	void
		 */
		private static function _sendConfirmMail( array &$appData, string $email, string $token ): void {

			$template = $appData['/nino/newsletter/confirm-template'] ?? '/templates/mail-newsletter-confirm';

			\Nino\Html::addFills( $appData, [ '[[/newsletter/confirm/url]]' => self::_getActionUrl( $appData, 'confirm', $token ) ], '*' );

			$tpl 			= \Nino\Html::renderHtml( $appData, '[template '. $template. ']' );
			$subject 	= \Nino\Html::renderHtml( $appData, '[[/mail/newsletter/subject]]' );
			$replyTo 	= \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' );

			\Nino\Mail::send( $appData, $email, $subject, $tpl, $replyTo );
		}

		/**
		 *	Flip a pending entry to subscribed for a visited confirm link.
		 *	Idempotent - confirming an already-subscribed token stays true,
		 *	so a twice-clicked mail link doesn't scare the visitor with an
		 *	error
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				Token from the visited link
		 *
		 *	@return 	bool										False for an unknown/empty token or a failed write
		 */
		private static function _confirm( array &$appData, string $token ): bool {

			if( $token === '' )
				return false;

			try {

				$found = false;

				\Nino\Filesystem::mutate( $appData, self::PATH, function( array $entries ) use ( $token, &$found ): ?array {

					foreach( $entries as $entryKey => $entry ) {

						if( hash_equals( (string) ( $entry['token'] ?? '' ), $token ) === false )
							continue;

						$found = true;

						if( ( $entry['status'] ?? '' ) === 'subscribed' )
							return null;

						$entries[$entryKey]['status']	= 'subscribed';
						$entries[$entryKey]['date']		= date( 'Y-m-d H:i:s' );

						return $entries;
					}

					return null;
				} );

				return $found;

			} catch( \Throwable $e ) {
				trigger_error( 'Newsletter confirm failed: '. $e->getMessage() );
			}

			return false;
		}

		/**
		 *	Remove the entry a visited unsubscribe link's token belongs to -
		 *	works for subscribed and still-pending entries alike
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				Token from the visited link
		 *
		 *	@return 	bool										False for an unknown/empty token or a failed write
		 */
		private static function _unsubscribe( array &$appData, string $token ): bool {

			if( $token === '' )
				return false;

			try {

				$found = false;

				\Nino\Filesystem::mutate( $appData, self::PATH, function( array $entries ) use ( $token, &$found ): ?array {

					foreach( $entries as $entryKey => $entry )
						if( hash_equals( (string) ( $entry['token'] ?? '' ), $token ) === true ) {
							unset( $entries[$entryKey] );
							$found = true;
							return array_values( $entries );
						}

					return null;
				} );

				return $found;

			} catch( \Throwable $e ) {
				trigger_error( 'Newsletter unsubscribe failed: '. $e->getMessage() );
			}

			return false;
		}

		/**
		 *	Build an absolute /.newsletter action url (confirm/unsubscribe)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$action				Query key: 'confirm' or 'unsubscribe'
		 *	@param		string		$token				The entry's token
		 *
		 *	@return 	string									Absolute url
		 */
		private static function _getActionUrl( array &$appData, string $action, string $token ): string {

			return 'https://'. \Nino\Html::renderHtml( $appData, '[[/website/url]]' ). '/.newsletter?'. $action. '='. rawurlencode( $token );
		}
	}

}
