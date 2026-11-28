<?php
declare(strict_types=1);

namespace Nino\Modules {

	// A feature that owns data. The test registers its callback of '/nino/admin/backup' where it wants a feature whose callback throws, as the callback of a
	// feature does for a database that is locked or broken: the safety snapshot then packs the files of the feature as they lie
	class Ledger {

		// The connection that callbackKeep() leaves open, with a transaction, on the copy it handed over: a lock of this process on a file the run packs
		public static ?\PDO $kept = null;

		public static function init( array &$appData ): void {
		}

		// Hands over a copy of one file that is larger than the file, and leaves the disk with the room that a copy of that size does not fit in
		public static function callbackCopy( array &$appData, array &$args ): void {

			$handle = fopen( $args['staging']. '/plain.sqlite', 'wb' );
			ftruncate( $handle, 6291456 );
			fclose( $handle );

			\Nino\Backup::$diskFree = 8388608;
		}

		// Hands over a copy that is a database, and keeps a connection to it with a write transaction open, as a feature does that holds its copy (the log of the transaction
		// is in memory, so that no file of it is beside the copy)
		public static function callbackKeep( array &$appData, array &$args ): void {

			self::$kept = new \PDO( 'sqlite:'. $args['staging']. '/plain.sqlite', null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
			self::$kept->exec( 'PRAGMA journal_mode=MEMORY' );
			self::$kept->exec( 'CREATE TABLE t(x)' );
			self::$kept->exec( 'BEGIN IMMEDIATE' );
			self::$kept->exec( 'INSERT INTO t VALUES(1)' );
		}

		// A feature that has a hook and hands over nothing: the files it owns are packed as they lie, and its name is the one a stamp's list of copies is believed for
		public static function callbackNothing( array &$appData, array &$args ): void {
		}

		public static function callbackBackup( array &$appData, array &$args ): void {

			$appData['./ledger/calls'] = (int) ( $appData['./ledger/calls'] ?? 0 ) + 1;

			throw new \RuntimeException( 'database is locked' );
		}
	}
}
