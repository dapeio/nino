<?php
declare(strict_types=1);

namespace Nino\Modules {

	// A feature that owns data. The test registers its callback of '/nino/admin/backup' where it wants a feature whose callback throws, as the callback of a
	// feature does for a database that is locked or broken: the safety snapshot then packs the files of the feature as they lie
	class Ledger {

		public static function init( array &$appData ): void {
		}

		// Hands over a copy of one file that is larger than the file, and leaves the disk with the room that a copy of that size does not fit in
		public static function callbackCopy( array &$appData, array &$args ): void {

			$handle = fopen( $args['staging']. '/plain.sqlite', 'wb' );
			ftruncate( $handle, 6291456 );
			fclose( $handle );

			\Nino\Backup::$diskFree = 8388608;
		}

		public static function callbackBackup( array &$appData, array &$args ): void {

			$appData['./ledger/calls'] = (int) ( $appData['./ledger/calls'] ?? 0 ) + 1;

			throw new \RuntimeException( 'database is locked' );
		}
	}
}
