<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Form				see Nino.php's Modules section for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules 						Optional Modules
	 *	Form								Handles the contact form's POST /.form - sanitizes/validates
	 *												the posted fields, sends the owner/user mail pair
	 *												(recipient/subject/message stay Text/template driven -
	 *												see /form/email/owner, /form/subject/owner, /form/subject/
	 *												user, /templates/mail-owner.tpl, /templates/mail-user.tpl -
	 *												the user mail renders in the visitor's current locale, the
	 *												owner mail always in the site's native locale),
	 *												and records every successful submission so it's visible in
	 *												_editor (in addition to the mail itself) - see
	 *												Admin\Submissions in _editor/Editor.php, which reads this
	 *												module's storage independently (same shape as Dev\Restore
	 *												reading Admin\Backup's output).
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Form {

		private const int MAX_FIELD_LENGTH 	= 1000;
		private const int RETENTION_MONTHS 	= 3;

		/**
		 *	Register the POST / handler
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
    	$appData['/nino/http/routes']['POST://.form'] = [ 'uri' => '/.form' ];
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	Validate and send the contact form, then record it for _editor -
		 *	same field set/rules the form has always used
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Respect a rejection from the earlier global Csrf callback - same guard
			// Auth::callbackResponse uses, see its comment for why this is a
			// dedicated flag rather than checking statusCode
			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			// Trimmed and length-capped, but not yet escaped - escaping is an
			// output concern, and doing it here broke validation: a perfectly
			// legitimate "o'brien@example.com" becomes "o&#039;brien@..." and
			// FILTER_VALIDATE_EMAIL then rejects it, ie. a 400 for a valid
			// address the visitor has no way of getting past
			$clean = fn( string $key ): string => substr( trim( (string) ( $_POST[$key] ?? '' ) ), 0, self::MAX_FIELD_LENGTH );

			$name			= $clean( 'name' );
			$email		= $clean( 'email' );
			$message	= $clean( 'message' );
			$location	= $clean( 'location' );
			$cat			= $clean( 'cat' );

			// Fields empty
			if( $name === '' || $email === '' || $message === '' || filter_var( $email, FILTER_VALIDATE_EMAIL ) === false )
				$request['/nino/http/response']['statusCode'] = 400;

			// Honeypot is filled
			if( $location !== '' )
				$request['/nino/http/response']['statusCode'] = 418;

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return;

			// Send emails
			// [[/nino/dir]] is resolved manually here (not via the normal
			// Text-fill mechanism), since this callback runs inside
			// Http::response() - before \Nino\request() adds it as a fill
			// Escaped here, on the way into the html mail templates - the one
			// place these values actually become markup
			$safe = fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );

			$fields = [
				'[[name]]'					=> $safe( $name ),
				'[[email]]'					=> $safe( $email ),
				'[[message]]'				=> nl2br( $safe( $message ) ),
				'[[subject]]'				=> $safe( $cat ),
				'[[date]]'					=> date("Y-m-d H:i:s"),
				'[[/nino/dir]]'		=> \Nino\Filesystem::getDir( $appData ),
			];

			// Recipient/subject are Text values ([[/form/...]], editable via _editor) -
			// only the message structure itself (the .tpl files) is developer territory
			$emailOwner = \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' );

			// The visitor's own confirmation mail stays in whichever locale
			// they filled out the form in (already the current locale)
			$tpl_user 		= \Nino\Html::renderHtml( $appData, '[template /templates/mail-user]' );
			$tpl_user 		= str_replace( array_keys( $fields ), array_values( $fields ), $tpl_user );
			$subjectUser 	= \Nino\Html::renderHtml( $appData, '[[/form/subject/user]]' );

			// ...but the owner notification always goes out in the site's
			// native locale, regardless of which locale the visitor used
			$visitorLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Locales::setCurrentLocale( $appData, \Nino\Locales::getNativeLocale( $appData ) );

			$tpl_owner 		= \Nino\Html::renderHtml( $appData, '[template /templates/mail-owner]' );
			$tpl_owner 		= str_replace( array_keys( $fields ), array_values( $fields ), $tpl_owner );
			$subjectOwner = \Nino\Html::renderHtml( $appData, '[[/form/subject/owner]]' );

			\Nino\Locales::setCurrentLocale( $appData, $visitorLocale );

			\Nino\Mail::send( $appData, $emailOwner, $subjectOwner, $tpl_owner, $email );

			\Nino\Mail::send( $appData, $email, $subjectUser, $tpl_user, $emailOwner );

			$request['/nino/http/response']['statusCode'] = 200;

			// A submission whose mail was refused by the rate limit is not
			// recorded: writing one entry per request regardless turns a
			// throttled flood into unthrottled disk growth from an
			// unauthenticated endpoint. A genuine mail() failure still records -
			// there the inquiry did happen and losing it would be worse.
			if( ( $appData['./nino/mail/ratelimited'] ?? false ) === true )
				return;

			// Stored escaped, same as before: _editor's submissions panel decodes
			// these entities again on render (see _editor/assets/submissions.js,
			// decodeEntities() into textContent)
			self::_record( $appData, [
				'date'		=> date( 'Y-m-d H:i:s' ),
				'name'		=> $safe( $name ),
				'email'		=> $safe( $email ),
				'message'	=> $safe( $message ),
				'cat'			=> $safe( $cat ),
				'ip'			=> \Nino\Http::getClientIp(),
			] );
		}

		/**
		 *	Append one submission to this month's /data/forms.<Y-m>.php,
		 *	then prune months past RETENTION_MONTHS. Never thrown - a
		 *	missing record must not turn a successfully-sent contact mail
		 *	into a 500 for the visitor. Read independently by
		 *	Admin\Submissions in _editor/Editor.php (same shape as
		 *	Dev\Restore reading Admin\Backup's output). Plain
		 *	"<?php return [...];" array files (Filesystem::getFileContent()/
		 *	putFileContent()'s native .php handling), same as config.php/
		 *	text/elements - readable directly (eg. in an emergency without
		 *	_editor installed at all) rather than needing a decoder. Lives
		 *	in /data, not under _editor/, since this is public-site kernel
		 *	data _editor merely happens to read - same fixed-path reasoning
		 *	as Runtime::_recordError()'s own docblock.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				One submission ( date, name, email, message, cat, ip )
		 *
		 *	@return 	void
		 */
		private static function _record( array &$appData, array $entry ): void {

			try {

				$path = '/data/forms.'. date( 'Y-m' ). '.php';

				\Nino\Filesystem::mutate( $appData, $path, function( array $entries ) use ( $entry ): array {
					$entries[] = $entry;
					return $entries;
				} );

				self::_prune( \Nino\Filesystem::getPath( $appData ). '/data' );

			} catch( \Throwable $e ) {
				trigger_error( 'Submission log write failed: '. $e->getMessage() );
			}
		}

		/**
		 *	Delete monthly files older than RETENTION_MONTHS - a longer
		 *	window than the admin activity log (see Admin\Logs): these are
		 *	real business inquiries, not just an operational safety net
		 *
		 *	@param		string		$dir					Absolute path to the /data directory
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir ): void {

			$cutoff = ( new \DateTime( 'first day of -'. self::RETENTION_MONTHS. ' months' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( $dir, 'forms.', 'Y-m', '.php', $cutoff );
		}
	}

}
