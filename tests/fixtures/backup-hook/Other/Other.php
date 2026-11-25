<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The second feature that answers '/nino/admin/backup': it hands over what $appData['./other/files'] names, other.db by default
	class Other {

		public static function init( array &$appData ): void {

			\Nino\Callbacks::registerCallback( $appData, '/nino/admin/backup', [ self::class, 'callbackBackup' ] );
		}

		public static function callbackBackup( array &$appData, array &$args ): void {

			$appData['./other/calls'] = ( $appData['./other/calls'] ?? 0 ) + 1;

			foreach( (array) ( $appData['./other/files'] ?? [ 'other.db' => 'copy of other' ] ) as $name => $bytes )
				file_put_contents( $args['staging']. '/'. $name, $bytes );
		}
	}
}
