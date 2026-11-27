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
	 *											the current day's file by \Nino\Activity, the writer a
	 *											feature outside the workbench reaches as well. Every line
	 *											of the workbench comes in through
	 *											\Nino\Admin\Admin::record(): one per successfully-dispatched
	 *											mutating action (its _logAction()), one per login (its
	 *											_logLoginOnce()), and those a panel records itself - a backup
	 *											made, an element type deleted, a feature installed but not
	 *											activated. Not encrypted like Backup - there's no
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

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'list', 'day' ];
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
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// Where the day's files are: the writer's directory, \Nino\Activity
		private const string LOGS_DIR = \Nino\Activity::LOGS_DIR;

		/**
		 *	Append one line to today's log file, then prune anything past
		 *	the retention. Failures are logged, never thrown - an audit
		 *	trail is a nice-to-have, it must never break the admin action
		 *	it's recording. The writer is \Nino\Activity::write(), which a
		 *	request outside the workbench reaches as well: it cleans both fields
		 *	(one line, the three fields kept, 300 characters for the message and
		 *	254 for the actor) and writes no line for a field that is not valid
		 *	UTF-8 or is empty
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$actor				The acting user's mail
		 *	@param		string		$message			Human-readable description of what happened
		 *
		 *	@return 	void
		 */
		public static function record( array &$appData, string $actor, string $message ): void {

			\Nino\Activity::write( $appData, $actor, $message );
		}

		/**
		 *	List every recorded line within the retention window, most
		 *	recent first, and beside it the key of each line (same length,
		 *	same order): <Y-m-d>.<n> - the day of the file the line stands in
		 *	and its 1-based position in that file as it is stored. A day's
		 *	file only ever grows at its end (see record()), so a key names the
		 *	same line for as long as the file lives, whatever is recorded
		 *	after it; the workbench writes it into the address of the line
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			$lines = [];
			$keys 	= [];

			foreach( self::_allDays( $appData ) as $day => $dayLines )
				foreach( $dayLines as $at => $line ) {
					$lines[] = $line;
					$keys[] 	= $day. '.'. ( $at + 1 );
				}

			\Nino\Http::ok( $request, [ 'lines' => array_reverse( $lines ), 'keys' => array_reverse( $keys ) ] );
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
		 *	How many lines were recorded after a point in time - what has
		 *	happened since the account's last visit, for a feature that says so. A line
		 *	carries its minute and nothing finer, so the minute of $since is
		 *	over: a line of that minute is not counted, the login that opened
		 *	the visit before among them. The sign-in the account made at $visit
		 *	is the visit itself and is not counted either. Only what the
		 *	retention window still holds can be counted
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int				$since				Timestamp of the earlier visit
		 *	@param		string		$actor				The account's mail, '' for none
		 *	@param		int				$visit				Timestamp of the sign-in now, 0 for none
		 *
		 *	@return 	int
		 */
		public static function countSince( array &$appData, int $since, string $actor = '', int $visit = 0 ): int {

			// A line starts with its minute, in a fixed format that sorts as time does
			$over 	= date( 'Y-m-d H:i', $since );
			$opened = date( 'Y-m-d H:i', $visit );
			$count 	= 0;

			foreach( self::_allLines( $appData ) as $line ) {

				$parts = explode( '  ', $line, 3 );

				if( count( $parts ) !== 3 || preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d$/', $parts[0] ) !== 1 || $parts[0] <= $over )
					continue;

				if( $actor !== '' && $visit > 0 && $parts[1] === $actor && $parts[2] === 'Login' && $parts[0] >= $opened )
					continue;

				$count++;
			}

			return $count;
		}

		/**
		 *	Read every recorded line within the retention window, oldest
		 *	first (as stored)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return		array
		 */
		private static function _allLines( array &$appData ): array {
			return array_merge( [], ...array_values( self::_allDays( $appData ) ) );
		}

		/**
		 *	Read the lines of every day's file within the retention window,
		 *	the oldest day first, each day's lines in the order they are
		 *	stored
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return		array									'Y-m-d' => string[]
		 */
		private static function _allDays( array &$appData ): array {

			$days = [];

			$files = glob( self::_logDir( $appData ). '/*.php' ) ?: [];

			sort( $files );

			foreach( $files as $file )
				if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', basename( $file, '.php' ) ) === 1 )
					$days[ basename( $file, '.php' ) ] = self::_readLines( $file );

			return $days;
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

			$decoded = base64_decode( substr( $raw, strlen( \Nino\Admin\Recovery::STUB_PREFIX ), -strlen( \Nino\Admin\Recovery::STUB_SUFFIX ) ) );

			return $decoded === '' ? [] : explode( "\n", $decoded );
		}
	}
}
