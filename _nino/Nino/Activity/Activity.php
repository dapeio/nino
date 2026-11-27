<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Activity				The activity log's one writer: a line, cleaned, appended to the day's file
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Activity - the one writer of a line of the activity log, for the
	// workbench (\Nino\Admin\Admin::record(), \Nino\Modules\Logs\Admin::record())
	// and for a feature whose surface is outside it (record()). It needs
	// nothing of the workbench's shell, so a request that never loaded it
	// writes the same file the same way. The readers of the log stay in
	// \Nino\Modules\Logs\Admin. Not \Nino\RotatingLog, which only deletes
	// dated files that are old
	class Activity {

		// Under the private directory, not in this tool's own folder: a tool
		// folder holding runtime state cannot be replaced independently.
		public const string LOGS_DIR = \Nino\Filesystem::CONTENT_DIR. '/.logs';

		public const int RETENTION_DAYS = 14;

		// The most characters (code points) of a line's two fields
		public const int MESSAGE_LENGTH = 300;
		// 254 is the longest a valid mail is: an actor is cut by nothing a mail has, so two accounts never read as one
		public const int ACTOR_LENGTH = 254;

		// What is one blank in a line: every control character (\p{Cc}: C0, DEL, C1 - U+0085 among them), every format character (\p{Cf}: the
		// controls of the direction of a text, the zero-width characters, the soft hyphen, the byte order mark, the tag characters ...), the
		// separators of a line and a paragraph (U+2028, U+2029), the blank-looking fillers (the combining grapheme joiner, the Hangul and
		// Khmer fillers, the Braille blank, the Mongolian free variation selectors) and every space of Unicode (\p{Zs}). The other variation
		// selectors (U+FE00-FE0F, U+E0100-E01EF) are none of these: an emoji keeps its own
		private const string SEPARATORS = '/[\p{Cc}\p{Cf}\p{Zs}\x{2028}\x{2029}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{3164}\x{FFA0}\x{2800}\x{180B}-\x{180D}\x{180F}]+/u';

		/**
		 *	Hand one line to the activity log for a feature: a sentence of the
		 *	feature with the ids and the names of what it acted on. Text a visitor
		 *	typed does not belong into it - the kernel bounds what it can (one
		 *	line, 300 characters, no control character, the three fields of the
		 *	line kept) but cannot tell whose words they are.
		 *
		 *	The actor is never a parameter: the mail of the account that is signed
		 *	in, and for a request without one the fixed "feature:<key>". The key
		 *	is checked against the features that are on, so nothing of the
		 *	request becomes an actor
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$feature			The key of the calling feature, which must be on
		 *	@param		string		$message			The sentence, see above
		 *
		 *	@return 	bool										True when the line is in the day's file; false where the log is off, the Logs module is not delivered, the feature is not on, the sentence is empty or is not valid UTF-8, or the file could not be written
		 */
		public static function record( array &$appData, string $feature, string $message ): bool {

			// The key is compared as the manifests spell it, case and all: a key of another case is another key, and none is on
			if( preg_match( \Nino\Features::KEY_PATTERN, $feature ) !== 1 || ( \Nino\Features::get( $appData, $feature )['active'] ?? false ) !== true ) {
				trigger_error( 'Activity::record(): '. ( preg_match( \Nino\Features::KEY_PATTERN, $feature ) === 1 ? '"'. $feature. '"' : 'the given key' ). ' is not a feature that is switched on - no line was written', E_USER_WARNING );
				return false;
			}

			$user = \Nino\Auth::getCurrentUser( $appData );

			return self::write( $appData, $user !== false && is_string( $user['mail'] ?? null ) === true && $user['mail'] !== '' ? $user['mail'] : 'feature:'. $feature, $message );
		}

		/**
		 *	Append one line to today's log file, then prune anything past
		 *	RETENTION_DAYS. Failures are logged, never thrown - an audit
		 *	trail is a nice-to-have, it must never break the admin action
		 *	it's recording. Both fields are cleaned (see _oneLine()); a field
		 *	that is not valid UTF-8 or is empty after it writes no line.
		 *
		 *	The writer for the workbench's own lines, which name their actor
		 *	(\Nino\Admin\Admin::record(), \Nino\Modules\Logs\Admin::record()); a
		 *	feature calls record(), which chooses the actor. A workbench delivered
		 *	without the Logs module takes no log: where that module cannot be
		 *	loaded no line is written, whoever asks
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$actor				The acting user's mail
		 *	@param		string		$message			Human-readable description of what happened
		 *
		 *	@return 	bool										Whether the line is in the day's file; false too where the log is switched off
		 */
		public static function write( array &$appData, string $actor, string $message ): bool {

			if( ( $appData['/nino/admin/logs'] ?? true ) === false )
				return false;

			// class_exists() runs the autoloader, so a present module answers true without anything here naming a file
			if( class_exists( '\\Nino\\Modules\\Logs\\Admin' ) === false )
				return false;

			try {

				// One entry is one line, whatever was posted: a template name or
				// a type uri reaches the message verbatim, and a line break in it
				// would write a second line - dated, attributed to any account,
				// saying anything. An audit log that can be told what to say is
				// not one
				$actor 		= self::_oneLine( $actor, self::ACTOR_LENGTH );
				$message	= self::_oneLine( $message, self::MESSAGE_LENGTH );

				if( $actor === null || $message === null )
					throw new \RuntimeException( 'the '. ( $actor === null ? 'actor' : 'message' ). ' cannot be read as UTF-8 text' );

				if( $actor === '' || $message === '' )
					throw new \RuntimeException( 'the '. ( $actor === '' ? 'actor' : 'message' ). ' is empty' );

				$time		 = time();
				$relPath = self::LOGS_DIR. '/'. date( 'Y-m-d', $time ). '.php';

				\Nino\Filesystem::forceDir( $appData, self::LOGS_DIR );

				$dir 	= self::_logDir( $appData );
				$path = $dir. '/'. date( 'Y-m-d', $time ). '.php';

				// Locked directly, not via Filesystem::mutate(): this file is
				// base64+stub encoded, not the plain array format getFileContent()/
				// putFileContent() know how to read - but two concurrent admin
				// actions still need to not both read the same line list and each
				// overwrite the other's append
				if( \Nino\Filesystem::lockFile( $appData, $relPath ) === false )
					return false;

				/*	Released whichever way the write goes: a throw between the
					lock and the unlock left the handle held for the rest of the
					request. And written beside the file and renamed over it
					rather than in place with file_put_contents(): that left a
					reader a truncated file for the duration of the write, and a
					target that could not be written - a directory in its place,
					a permission, a full disk - raised php's own warning, which
					the framework's handler ends the request on. The action being
					logged died in its log line; the line is what is lost, and
					the catch below says so	*/
				try {
					$lines 	 = is_file( $path ) === true ? self::_readLines( $path ) : [];
					$lines[] = date( 'Y-m-d H:i', $time ). '  '. $actor. '  '. $message;

					if( \Nino\Filesystem::writeAtomic( $path, \Nino\Backup\Seal::STUB_PREFIX. base64_encode( implode( "\n", $lines ) ). \Nino\Backup\Seal::STUB_SUFFIX ) === false )
						throw new \RuntimeException( 'the day\'s file could not be written: '. basename( $path ) );
				}
				finally {
					\Nino\Filesystem::unlockFile( $appData, $relPath );
				}

				self::_prune( $dir );

				return true;

			} catch( \Throwable $e ) {
				trigger_error( 'Activity log write failed: '. $e->getMessage() );
				return false;
			}
		}

		/**
		 *	One field of a line: every control character, every format
		 *	character (direction controls, zero-width characters, the soft hyphen,
		 *	the byte order mark ...), the separators of a line and a paragraph, the
		 *	blank-looking fillers and every space of Unicode is one blank; a run
		 *	of blanks is one, which is what keeps the
		 *	three fields of a line apart (no value holds two blanks in a row);
		 *	trimmed, and cut at $most characters - code points, never inside one
		 *
		 *	@param		string		$text
		 *	@param		int				$most					The most characters the field keeps
		 *
		 *	@return 	string|null							Null for a text that is not valid UTF-8
		 */
		private static function _oneLine( string $text, int $most ): ?string {

			if( preg_match( '//u', $text ) !== 1 )
				return null;

			$clean = preg_replace( self::SEPARATORS, ' ', $text );

			if( is_string( $clean ) === false || preg_match( '/^.{0,'. $most. '}/su', $clean, $kept ) !== 1 )
				return null;

			return trim( $kept[0], ' ' );
		}

		/**
		 *	The directory that holds activity logs
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Absolute path, existing or not
		 */
		private static function _logDir( array &$appData ): string {

			return \Nino\Filesystem::getContentPath( $appData ). substr( self::LOGS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) );
		}

		/**
		 *	Read one day's file back into its individual lines
		 *
		 *	@param		string		$path					Absolute path to the .php file
		 *
		 *	@return 	string[]
		 */
		private static function _readLines( string $path ): array {

			// Silenced: a day's file that cannot be read - a directory standing
			// in its place - raised php's own warning here, and every listing
			// of the log died on it. What cannot be read holds no lines
			$raw = @file_get_contents( $path );

			if( is_string( $raw ) === false )
				return [];

			$decoded = base64_decode( substr( $raw, strlen( \Nino\Backup\Seal::STUB_PREFIX ), -strlen( \Nino\Backup\Seal::STUB_SUFFIX ) ) );

			return $decoded === '' ? [] : explode( "\n", $decoded );
		}

		/**
		 *	Delete dated log files older than RETENTION_DAYS - same
		 *	basename-must-match-a-plain-date guard as \Nino\Modules\Backups::_prune(), so
		 *	nothing other than a "Y-m-d.php" file is ever considered
		 *
		 *	@param		string		$dir					Absolute path to the log directory
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir ): void {

			$cutoff = ( new \DateTime( '-'. self::RETENTION_DAYS. ' days' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( $dir, '', 'Y-m-d', '.php', $cutoff );
		}
	}
}
