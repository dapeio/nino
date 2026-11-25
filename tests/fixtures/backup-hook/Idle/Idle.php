<?php
declare(strict_types=1);

namespace Nino\Modules {

	// A feature that is not switched on, with a hook that is none to call: it throws when it is
	class Idle {

		public static function init( array &$appData ): void {

			\Nino\Callbacks::registerCallback( $appData, '/nino/admin/backup', [ self::class, 'callbackBackup' ] );
		}

		public static function callbackBackup( array &$appData, array &$args ): void {

			throw new \RuntimeException( 'a feature that is not switched on was asked for its data' );
		}
	}
}
