<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Form\Admin		The /_admin panel of the Form module - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Form {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						Optional modules
	 *	Submissions				Read-only view of the contact form's submissions -
	 *												\Nino\Modules\Form (Form.php beside this) writes these,
	 *												this reads its storage independently (same shape as
	 *												Dev\Restore reading Admin\Backup's output: fixed path,
	 *												never writes anything here). Project-root /data, plain
	 *												array files - not a workbench concern, see Modules\Form's
	 *												own docblock.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string VIEW_PERM = '/_admin/submissions/view';

		public static function actions(): array {
			return [ 'submissions/list' => [ self::class, 'apiList' ] ];
		}

		public static function nav(): array {
			return [ 'submissions', '/_admin/nav/submissions', 60, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-form-icon lucide-form"><path d="M4 14h6"/><path d="M4 2h10"/><rect x="4" y="18" width="16" height="4" rx="1"/><rect x="4" y="6" width="16" height="4" rx="1"/></svg>';
		}

		public static function perm(): string {
			return self::VIEW_PERM;
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ), \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ) ];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function summary( array &$appData ): array {
			return [ 'value' => self::count( $appData ), 'label' => '/_admin/dashboard/label/submissions' ];
		}

		/**
		 *	List every recorded submission within the retention window
		 *	(see Modules\Form::RETENTION_MONTHS), most recent first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'entries' => array_reverse( self::_entries( $appData ) ) ] );
		}

		/**
		 *	How many submissions are currently on file (retention window) -
		 *	shared by apiList above and Dashboard::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function count( array &$appData ): int {
			return count( self::_entries( $appData ) );
		}

		/**
		 *	Read every recorded submission within the retention window,
		 *	oldest first (as stored)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function _entries( array &$appData ): array {

			$entries 	= [];
			$dir 			= \Nino\Filesystem::path( $appData, '/data' );
			$files 		= glob( $dir. '/forms.*.php' ) ?: [];

			sort( $files );

			foreach( $files as $file )
				if( preg_match( '/^\d{4}-\d{2}$/', substr( basename( $file, '.php' ), 6 ) ) === 1 )
					foreach( \Nino\Filesystem::getFileContent( $appData, '/data/'. basename( $file ), [] ) as $entry )
						if( is_array( $entry ) === true )
							$entries[] = $entry;

			return $entries;
		}
	}

}
