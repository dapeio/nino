<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Maintenance\Admin	The /_admin panel of the Maintenance module - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Maintenance {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						Optional modules
	 *	Admin							The Maintenance panel: one pane with the current state, the
	 *											switch and the Retry-After seconds. A developer surface -
	 *											switching the whole site off is not an editorial decision -
	 *											so it asks for its own MANAGE_PERM rather than sharing one
	 *											of the content permissions.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/maintenance/manage';

		// Same bounds the login throttle's cooldown uses (Users\Lockout) - a
		// retry below a minute is not a meaningful hint, a value above a
		// week is not a "retry" any more
		private const int RETRY_MIN = 60;
		private const int RETRY_MAX = 604800;

		public static function actions(): array {
			return [
				'maintenance/status'	=> [ self::class, 'apiStatus' ],
				'maintenance/set'		=> [ self::class, 'apiSet' ],
			];
		}

		/**
		 *	Nav entry for this module - a system panel next to Config (20)
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'maintenance', '/_admin/nav/maintenance', 18, 'system' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'maintenance-form' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cone-icon lucide-cone"><path d="M8.3 10 5 21h14l-3.3-11"/><path d="M12 3v11"/><circle cx="12" cy="3" r="1"/></svg>';
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The module's own words, one <locale>.php per interface language
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	The dashboard tile - shown only while the site is actually down,
		 *	same reasoning a healthy Features/Submissions count is still
		 *	shown at 0 does not apply here: an operator opens the dashboard
		 *	to see what needs attention, and "online" is not that
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null
		 */
		public static function summary( array &$appData ): ?array {

			if( ( $appData['/nino/maintenance/status'] ?? false ) !== true )
				return null;

			return [ 'value' => '', 'label' => '/_admin/dashboard/label/maintenance' ];
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'maintenance/set' => ( $data['status'] ?? false ) === true ? 'Switch maintenance on' : 'Switch maintenance off',
				default						 => '',
			};
		}

		/**
		 *	The current state - unlike Modules\Config\Admin::apiList(), read
		 *	straight from $appData rather than re-reading config.php: nothing
		 *	in this request writes either key at runtime without also
		 *	persisting it (see apiSet() below), so what config.php loaded at
		 *	the start of this request is already current
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiStatus( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, self::_state( $appData ) );
		}

		/**
		 *	Switch the site on or off, and save the Retry-After seconds in
		 *	the same write. Both keys or neither: a half-applied save would
		 *	leave the switch on with a Retry-After config.php never actually
		 *	held
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data		= \Nino\Admin\Admin::postData();
			$status	= $data['status'] ?? null;
			$retry	= \Nino\Admin\Admin::cleanInt( $data['retry'] ?? null, [ 'min' => self::RETRY_MIN, 'max' => self::RETRY_MAX ] );

			if( is_bool( $status ) === false ) {
				\Nino\Http::fail( $request, 400, 'status: expected true or false' );
				return;
			}

			if( $retry === null ) {
				\Nino\Http::fail( $request, 400, 'retry: expected a whole number between '. self::RETRY_MIN. ' and '. self::RETRY_MAX );
				return;
			}

			$appData['/nino/maintenance/status']	= $status;
			$appData['/nino/maintenance/retry']	= $retry;

			\Nino\AppData::writeContentData( $appData, [ '/nino/maintenance/status', '/nino/maintenance/retry' ] );

			\Nino\Http::ok( $request, self::_state( $appData ) );
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										{ status, retry }
		 */
		private static function _state( array &$appData ): array {
			return [
				'status'	=> ( $appData['/nino/maintenance/status'] ?? false ) === true,
				'retry'		=> \Nino\Modules\Maintenance::retry( $appData ),
			];
		}
	}

}
