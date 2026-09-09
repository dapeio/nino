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
	 *	Submissions				Read-only view of the submissions every form leaves -
	 *												\Nino\Form records them and \Nino\Form::entries() reads
	 *												them back, so this panel and the endpoint never disagree
	 *												about what is on disk or where. Project-root /data,
	 *												plain array files - not a workbench concern, see
	 *												\Nino\Form's own docblock.
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

			\Nino\Http::ok( $request, [
				'entries'	=> array_reverse( \Nino\Form::entries( $appData ) ),
				// The forms a project defines, so the panel can say which one a
				// submission belongs to rather than only that one arrived
				'forms'		=> array_column( \Nino\Form::forms( $appData ), 'name', 'key' ),
			] );
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
			return count( \Nino\Form::entries( $appData ) );
		}

	}

}
