<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Newsletter\Admin		The /_admin panel of the Newsletter module - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Newsletter {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						Optional modules
	 *	Newsletter				View + delete of the newsletter signups -
	 *												\Nino\Modules\Newsletter (in _nino/Nino.php) writes
	 *												these, this reads/deletes its storage independently (same
	 *												shape as Admin\Submissions reading Modules\Form's
	 *												output: fixed path). Project-root /data, plain array file -
	 *												not a workbench concern, see Modules\Newsletter's own
	 *												docblock. Besides apiDelete, entries also go away via the
	 *												self-service unsubscribe link (Modules\Newsletter).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/newsletter/manage';

		public static function actions(): array {
			return [
				'newsletter/list' 	=> [ self::class, 'apiList' ],
				'newsletter/delete' => [ self::class, 'apiDelete' ],
			];
		}

		public static function nav(): array {
			return [ 'newsletter', '/_admin/nav/newsletter', 65, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mailbox-icon lucide-mailbox"><path d="M22 17a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.5C2 7 4 5 6.5 5H18c2.2 0 4 1.8 4 4v8Z"/><polyline points="15,9 18,9 18,11"/><path d="M6.5 5C9 5 11 7 11 9.5V17a2 2 0 0 1-2 2"/><line x1="6" x2="7" y1="10" y2="10"/></svg>';
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ), \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ) ];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function summary( array &$appData ): array {
			return [ 'value' => self::count( $appData ), 'label' => '/_admin/dashboard/label/newsletter' ];
		}

		public static function log( string $action, array $data ): string {
			return $action === 'newsletter/delete' ? 'Delete Newsletter Subscriber '. ( $data['email'] ?? '' ) : '';
		}

		private const string PATH = '/data/newsletter.php';

		// Same literal \Nino\Modules\Newsletter uses for its own removal
		// record (see that class' REMOVED_PATH docblock, including why it's
		// a sha256 hash and not the address) - duplicated rather than
		// referenced, same reasoning as PATH just above already being its
		// own copy rather than \Nino\Modules\Newsletter::PATH
		private const string REMOVED_PATH = '/data/newsletter-removed.php';

		/**
		 *	How many subscribers are currently on file - shared by
		 *	Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {
			return count( \Nino\Filesystem::getFileContent( $appData, self::PATH, [] ) );
		}

		/**
		 *	List every recorded newsletter signup, most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$entries = \Nino\Filesystem::getFileContent( $appData, self::PATH, [] );

			\Nino\Http::ok( $request, [ 'entries' => array_reverse( $entries ) ] );
		}

		/**
		 *	Delete one subscriber by email - the admin-side counterpart to
		 *	the visitor's own self-service unsubscribe link (see
		 *	Modules\Newsletter in Newsletter.php beside this)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$email = (string) ( \Nino\Admin\Admin::postData()['email'] ?? '' );

			if( $email === '' ) {
				\Nino\Http::fail( $request, 404, 'unknown email' );
				return;
			}

			$outcome = 'notfound';
			$readEntries = false;
			$removedHash = hash( 'sha256', mb_strtolower( trim( $email ) ) );

			$written = \Nino\Filesystem::mutate( $appData, self::PATH, function( array $entries ) use ( $email, $removedHash, &$appData, &$outcome, &$readEntries ): ?array {

				$readEntries = true;

				$filtered = array_values( array_filter( $entries, function( $entry ) use ( $email ) { return ( $entry['email'] ?? null ) !== $email; } ) );

				if( count( $filtered ) === count( $entries ) )
					return null;

				// Persist the durable removal before dropping the address. If that
				// write fails, leave the subscriber list untouched; if the following
				// list write fails, a retry is safe because the hash is idempotent.
				$removalWritten = \Nino\Filesystem::mutate( $appData, self::REMOVED_PATH, function( array $removed ) use ( $removedHash ): array {
					if( in_array( $removedHash, $removed, true ) === false )
						$removed[] = $removedHash;
					return $removed;
				} );

				if( $removalWritten === false ) {
					$outcome = 'removal-failed';
					return null;
				}

				$outcome = 'ready';
				return $filtered;
			} );

			if( $readEntries === false ) {
				\Nino\Http::fail( $request, 500, 'subscriber list could not be locked' );
				return;
			}

			if( $outcome === 'notfound' ) {
				\Nino\Http::fail( $request, 404, 'unknown email' );
				return;
			}

			if( $outcome !== 'ready' || $written === false ) {
				\Nino\Http::fail( $request, 500, 'subscriber could not be deleted' );
				return;
			}

			\Nino\Http::ok( $request );
		}
	}

}
