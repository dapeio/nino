<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Users\Lockout	Login protection tab: the login throttle
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Users {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Lockout						The throttle in front of the login, as the two numbers it
	 *											is: how many failed attempts an account gets, and how long
	 *											it then stays locked. A tab of the Users pane, next to the
	 *											accounts it protects - it used to be a group of the Config
	 *											panel and validates the way that panel does - through the
	 *											shell (\Nino\Admin\Admin::cleanInt()), not the Config panel:
	 *											a module is deletable one directory at a time, and this
	 *											tab must not fatal when that one is gone.
	 *											'min'/'max' are not cosmetic: a maxtries of 0 locks every
	 *											account out permanently on the first failed attempt, and a
	 *											cooldown of 0 removes the throttle entirely - both are
	 *											reachable by typing a plausible-looking number.
	 *											Below the numbers sit the accounts locked right now,
	 *											each with a button that lifts its lock (see
	 *											\Nino\Auth::lockedAccounts() and unlock()): somebody
	 *											who mistyped five times need not wait out the cooldown.
	 *											A locked client address is no account and is not
	 *											listed - it still has to run out
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Lockout {

		public const string MANAGE_PERM = '/_admin/lockout/manage';

		// The two settings, in render order - same shape as \Nino\Modules\Config\Admin::FIELDS,
		// label and hint fill keys the form resolves (see _admin/text/)
		private const array FIELDS = [
			'/nino/auth/maxtries' => [
				'type' 	=> 'int',
				'min' 	=> 1,
				'max' 	=> 100,
				'label'	=> '/_admin/lockout/label/maxtries',
				'hint' 	=> '/_admin/lockout/hint/maxtries',
			],
			'/nino/auth/cooldown' => [
				'type' 	=> 'int',
				'min' 	=> 60,
				'max' 	=> 604800,
				'unit' 	=> 'seconds',
				'label'	=> '/_admin/lockout/label/cooldown',
				'hint' 	=> '/_admin/lockout/hint/cooldown',
			],
		];

		// The runtime's own fallback for a missing key (see \Nino\Auth), so
		// the form never shows a value the site is not actually running with
		private const array DEFAULTS = [
			'/nino/auth/maxtries' => 5,
			'/nino/auth/cooldown' => 3600,
		];

		public static function actions(): array {
			return [
				'lockout/list' => [ self::class, 'apiList' ],
				'lockout/save' => [ self::class, 'apiSave' ],
				'lockout/unlock' => [ self::class, 'apiUnlock' ],
			];
		}

		// A tab of the Users pane (see \Nino\Modules\Users\Admin::tabs())
		public static function nav(): array {
			return [ 'lockout', '/_admin/nav/lockout', 20, 'system' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'lockout-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/lockout.js' ) ];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'lockout/save' 		=> 'Edit Login Protection',
				'lockout/unlock' 	=> 'Lift Lock '. ( $data['username'] ?? '' ),
				default 					=> '',
			};
		}

		/**
		 *	Both settings with their current value and the schema the form
		 *	renders them with, and the accounts locked right now. Read from
		 *	config.php fresh rather than from $appData, for the same reason
		 *	\Nino\Modules\Config\Admin::apiList() does
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			$fields = [];
			foreach( self::FIELDS as $key => $field )
				$fields[] = $field + [
					'key' 	=> $key,
					'value'	=> array_key_exists( $key, $stored ) === true ? $stored[$key] : self::DEFAULTS[$key],
				];

			\Nino\Http::ok( $request, [ 'fields' => $fields, 'locked' => self::_locked( $appData ) ] );
		}

		/**
		 *	Lift one account's lock - the account's counter starts from zero
		 *	again. Answers with the accounts still locked, so the tab shows
		 *	what the server now holds without rebuilding the number form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUnlock( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$username = \Nino\Admin\Admin::postData()['username'] ?? null;

			if( is_string( $username ) === false || \Nino\Auth::getUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return;
			}

			if( \Nino\Auth::unlock( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lift the lock' );
				return;
			}

			\Nino\Http::ok( $request, [ 'locked' => self::_locked( $appData ) ] );
		}

		/**
		 *	Save the form in one write - nothing is written until every field
		 *	validates, and a field the frontend did not send keeps what
		 *	config.php currently holds
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$posted = \Nino\Admin\Admin::postData()['fields'] ?? null;

			if( is_array( $posted ) === false ) {
				\Nino\Http::fail( $request, 400, 'no fields posted' );
				return;
			}

			$clean = [];
			foreach( self::FIELDS as $key => $field ) {

				if( array_key_exists( $key, $posted ) === false )
					continue;

				$value = \Nino\Admin\Admin::cleanInt( $posted[$key], $field );

				if( $value === null ) {
					\Nino\Admin\Admin::failType( $request, $key, $field );
					return;
				}

				$clean[$key] = $value;
			}

			if( $clean === [] ) {
				\Nino\Http::fail( $request, 400, 'no known fields posted' );
				return;
			}

			foreach( $clean as $key => $value )
				$appData[$key] = $value;

			\Nino\AppData::writeContentData( $appData, array_keys( $clean ) );

			\Nino\Http::ok( $request, [ 'saved' => array_keys( $clean ) ] );
		}

		/**
		 *	The accounts locked out right now, by mail
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ { mail, until }, ... ] - until as 'Y-m-d H:i'
		 */
		private static function _locked( array &$appData ): array {

			$locked = \Nino\Auth::lockedAccounts( $appData );
			ksort( $locked, SORT_STRING );

			$rows = [];
			foreach( $locked as $mail => $until )
				$rows[] = [ 'mail' => (string) $mail, 'until' => date( 'Y-m-d H:i', $until ) ];

			return $rows;
		}
	}
}
