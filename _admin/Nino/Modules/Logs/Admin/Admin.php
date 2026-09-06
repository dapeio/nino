<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Logs\Admin	Log panel: the activity log and the writing of it
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Logs {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Logs							Plaintext (behind the same self-terminating .php stub as
	 *											Backup - see that class' docblock for why) audit trail
	 *											of admin actions: who logged in, and every save/delete
	 *											that changed something. One line per event, appended to
	 *											the current day's file; \Nino\Admin\Admin::handlePost() calls
	 *											record() once per successfully-dispatched mutating
	 *											action, \Nino\Admin\Admin::init()'s login callback calls it once per
	 *											successful login. Not encrypted like Backup - there's no
	 *											key to manage and nothing here is as sensitive as a
	 *											password hash, the stub alone (no plaintext without
	 *											executing PHP, which exit()s first) is enough.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string VIEW_PERM = '/_admin/logs/view';

		public static function actions(): array {
			return [ 'logs/list' => [ self::class, 'apiList' ] ];
		}

		// Last in the content group, not in system: the log is what an editor
		// reads to see what happened to the content they work on, so the
		// Editor role the wizard writes from the content permissions carries
		// it (see \Nino\Modules\Users\Roles::defaults())
		public static function nav(): array {
			return [ 'logs', '/_admin/nav/logs', 90, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-logs-icon lucide-logs"><path d="M3 5h1"/><path d="M3 12h1"/><path d="M3 19h1"/><path d="M8 5h1"/><path d="M8 12h1"/><path d="M8 19h1"/><path d="M13 5h8"/><path d="M13 12h8"/><path d="M13 19h8"/></svg>';
		}

		public static function perm(): string {
			return self::VIEW_PERM;
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// Under the private directory, not in this tool's own folder: a tool
		// folder holding runtime state cannot be replaced independently.
		private const string LOGS_DIR = \Nino\Filesystem::CONTENT_DIR. '/.logs';

		private const int RETENTION_DAYS = 14;

		private const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		private const string STUB_SUFFIX = "';\n";

		/**
		 *	Append one line to today's log file, then prune anything past
		 *	RETENTION_DAYS. Failures are logged, never thrown - an audit
		 *	trail is a nice-to-have, it must never break the admin action
		 *	it's recording
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$actor				The acting user's mail
		 *	@param		string		$message			Human-readable description of what happened
		 *
		 *	@return 	void
		 */
		public static function record( array &$appData, string $actor, string $message ): void {

			if( ( $appData['/nino/admin/logs'] ?? true ) === false )
				return;

			try {

				$relPath = self::LOGS_DIR. '/'. date( 'Y-m-d' ). '.php';

				\Nino\Filesystem::forceDir( $appData, self::LOGS_DIR );

				$dir 	= \Nino\Filesystem::getContentPath( $appData ). substr( self::LOGS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) );
				$path = $dir. '/'. date( 'Y-m-d' ). '.php';

				// Locked directly, not via Filesystem::mutate(): this file is
				// base64+stub encoded, not the plain array format getFileContent()/
				// putFileContent() know how to read - but two concurrent admin
				// actions still need to not both read the same line list and each
				// overwrite the other's append
				if( \Nino\Filesystem::lockFile( $appData, $relPath ) === false )
					return;

				$lines 	 = is_file( $path ) === true ? self::_readLines( $path ) : [];
				// One entry is one line, whatever was posted: a template name or
				// a type uri reaches the message verbatim, and a line break in it
				// would write a second line - dated, attributed to any account,
				// saying anything. An audit log that can be told what to say is
				// not one
				$lines[] = date( 'Y-m-d H:i' ). '  '. self::_oneLine( $actor ). '  '. self::_oneLine( $message );

				file_put_contents( $path, self::STUB_PREFIX. base64_encode( implode( "\n", $lines ) ). self::STUB_SUFFIX );

				\Nino\Filesystem::unlockFile( $appData, $relPath );

				foreach( self::_logDirs( $appData ) as $logDir )
					self::_prune( $logDir );

			} catch( \Throwable $e ) {
				trigger_error( 'Activity log write failed: '. $e->getMessage() );
			}
		}

		/**
		 *	Collapse line breaks and control characters to one space
		 *
		 *	@param		string		$text
		 *
		 *	@return 	string
		 */
		private static function _oneLine( string $text ): string {
			return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $text ) );
		}

		/**
		 *	List every recorded line within the retention window, most
		 *	recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'lines' => array_reverse( self::_allLines( $appData ) ) ] );
		}

		/**
		 *	The most recent $limit lines, most recent first - shared by
		 *	\Nino\Modules\Dashboard\Admin::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$limit
		 *
		 *	@return 	array
		 */
		public static function recentLines( array &$appData, int $limit ): array {
			return array_slice( array_reverse( self::_allLines( $appData ) ), 0, $limit );
		}

		/**
		 *	Read every recorded line within the retention window, oldest
		 *	first (as stored)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _allLines( array &$appData ): array {

			$lines = [];

			foreach( self::_logDirs( $appData ) as $dir ) {

				$files = glob( $dir. '/*.php' ) ?: [];

				sort( $files );

				foreach( $files as $file )
					if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
						$lines = array_merge( $lines, self::_readLines( $file ) );
			}

			return $lines;
		}

		/**
		 *	The directory that holds activity logs.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Absolute paths, existing or not
		 */
		private static function _logDirs( array &$appData ): array {

			return [ \Nino\Filesystem::getContentPath( $appData ). substr( self::LOGS_DIR, strlen( \Nino\Filesystem::CONTENT_DIR ) ) ];
		}

		/**
		 *	Read one day's file back into its individual lines
		 *
		 *	@param		string		$path					Absolute path to the .php file
		 *
		 *	@return 	string[]
		 */
		private static function _readLines( string $path ): array {

			$raw 			= file_get_contents( $path );
			$decoded 	= base64_decode( substr( $raw, strlen( self::STUB_PREFIX ), -strlen( self::STUB_SUFFIX ) ) );

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
