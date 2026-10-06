<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Users\RecoveryPassword	Recovery password tab: changing the secret recovery.php asks for
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Users {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	RecoveryPassword	The recovery password, the secret the wizard's last step set
	 *											and _admin/recovery.php asks for, changed from the workbench:
	 *											the old one, the new one twice. A tab of the Users pane, with
	 *											a permission of its own - the password is what restores a
	 *											backup over the project and makes an account with full
	 *											access from recovery.php, so changing it is not a part of
	 *											managing users. The old password is verified by
	 *											\Nino\Admin\Recovery::change() under the counter recovery.php
	 *											shares: five wrong ones lock recovery.php for an hour, from
	 *											either door. Open recovery sessions stay open; there is no
	 *											first password to set here, a project without one is told
	 *											to write it by hand (see docs/_admin.md)
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class RecoveryPassword {

		public const string MANAGE_PERM = '/_admin/recoverypw/manage';

		public static function actions(): array {
			return [
				'recoverypw/save' => [ self::class, 'apiSave' ],
			];
		}

		// A tab of the Users pane (see \Nino\Modules\Users\Admin::tabs())
		public static function nav(): array {
			return [ 'recoverypw', '/_admin/nav/recoverypw', 30, 'system' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'recoverypw-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/recoverypw.js' ) ];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// A fixed line, never the data: the passwords are in it
		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'recoverypw/save' => 'Change Recovery Password',
				default 					=> '',
			};
		}

		/**
		 *	Change the recovery password. Nothing posted is echoed back: the
		 *	answer is ok or the reason it is not
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$current 	= is_string( $data['current'] ?? null ) === true ? $data['current'] : '';
			$new 			= is_string( $data['pw'] ?? null ) === true ? $data['pw'] : '';

			$status = \Nino\Admin\Recovery::change( $appData, $current, $new );

			if( $status === 200 ) {
				\Nino\Http::ok( $request );
				return;
			}

			// A refusal a person can cause names itself (the code carries the words in
			// the workbench's language), and the field it is about
			match( $status ) {
				400 		=> \Nino\Http::fail( $request, 400, 'password must be at least '. \Nino\Admin\Recovery::MIN_PW_LENGTH. ' characters', 'recoverypw_password_short', [ \Nino\Admin\Recovery::MIN_PW_LENGTH ], 'pw' ),
				401 		=> \Nino\Http::fail( $request, 401, 'wrong current password', 'recoverypw_wrong', [], 'current' ),
				429 		=> \Nino\Http::fail( $request, 429, 'too many attempts', 'recoverypw_locked' ),
				409 		=> \Nino\Http::fail( $request, 409, 'no recovery password is set - see the manual', 'recoverypw_unset' ),
				default => \Nino\Http::fail( $request, $status, 'could not store the recovery password' ),
			};
		}
	}
}
