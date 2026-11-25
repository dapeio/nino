<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The feature of tests/backup-hook-smoke.php that answers '/nino/admin/backup'. What it does in the hook is set by the test in
	// $appData['./vault/mode']: 'files' writes what $appData['./vault/files'] names (name below the staging => bytes), the other modes
	// are the attempts a feature can make that a run must refuse
	class Vault {

		public static function init( array &$appData ): void {

			\Nino\Callbacks::registerCallback( $appData, '/nino/admin/backup', [ self::class, 'callbackBackup' ] );
		}

		public static function callbackBackup( array &$appData, array &$args ): void {

			$appData['./vault/calls'] = ( $appData['./vault/calls'] ?? 0 ) + 1;
			$staging = $args['staging'];

			// What the run handed over: the two folders, and whether the staging was empty and closed to everybody else
			$appData['./vault/seen'][] = $args + [ 'empty' => ( scandir( $staging ) ?: [] ) === [ '.', '..' ], 'perms' => fileperms( $staging ) & 0777 ];
			$mode		 = $appData['./vault/mode'] ?? 'files';

			switch( $mode ) {

				case 'files':
					foreach( (array) ( $appData['./vault/files'] ?? [] ) as $name => $bytes ) {

						@mkdir( dirname( $staging. '/'. $name ), 0777, true );
						file_put_contents( $staging. '/'. $name, $bytes );
					}
					break;

				case 'link':
					symlink( (string) $appData['./vault/target'], $staging. '/vault.db' );
					break;

				case 'hardlink':
					$appData['./vault/linked'] = @link( (string) $appData['./vault/target'], $staging. '/vault.db' );
					break;

				case 'folder':
					mkdir( $staging. '/vault.db' );
					break;

				case 'fifo':
					posix_mkfifo( $staging. '/vault.db', 0600 );
					break;

				// A hook that writes into the folder of the feature that was asked before it, and then fails
				case 'tamper':
					file_put_contents( $staging. '/../other/other.db', 'forged' );
					throw new \RuntimeException( 'vault broke after it wrote into the folder of another' );

				case 'throw':
					throw new \RuntimeException( 'vault broke while it was copying /secret/place/vault.db' );

				case 'error':
					intdiv( 1, 0 );
					break;

				case 'exit':
					exit( 3 );

				// A hook that does not end: the time limit of the request ends it
				case 'spin':
					while( true )
						$appData['./vault/spun'] = ( $appData['./vault/spun'] ?? 0 ) + 1;

				// What the test does while the run is in the hook: it asks from a second process, which the lock of the run is for
				case 'during':
					$appData['./vault/during']();
					file_put_contents( $staging. '/vault.db', 'copy of vault' );
					break;

				// A hook that asks for a backup of its own, in the process that holds the lock of the run
				case 'again':
					$appData['./vault/again'] = \Nino\Modules\Backups::now( $appData );
					file_put_contents( $staging. '/vault.db', 'copy of vault' );
					break;

				// A disk that is full under a write of the hook: what php says of it, as a notice
				case 'diskfull':
					trigger_error( 'fwrite(): write of 4096 bytes failed with errno=28 No space left on device', E_USER_WARNING );
					break;

				// The hook that a test hands over: the example of the manual, run as it is written there
				case 'example':
					call_user_func_array( $appData['./vault/example'], [ &$appData, &$args ] );
					break;

				case 'sqlite':
					$live = new \PDO( 'sqlite:'. $args['dataDir']. '/vault.db' );
					$live->exec( 'VACUUM INTO '. $live->quote( $staging. '/vault.db' ) );
					$live = null;

					// A writer that goes on while the run does: what it commits now is not in the copy
					if( is_callable( $appData['./vault/after'] ?? null ) === true )
						$appData['./vault/after']();
					break;
			}
		}
	}
}
