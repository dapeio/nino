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
			return [
				'submissions/list' 		=> [ self::class, 'apiList' ],
				'submissions/delete'	=> [ self::class, 'apiDelete' ],
			];
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

		// What the activity log writes for this panel's one destructive
		// action - the id alone, since the entry it named is gone by the time
		// anyone reads the line
		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'submissions/delete'	=> 'Delete submission '. (string) ( $data['id'] ?? '' ),
				default								=> '',
			};
		}

		public static function summary( array &$appData ): array {
			return [ 'value' => self::count( $appData ), 'label' => '/_admin/dashboard/label/submissions' ];
		}

		/**
		 *	List every recorded submission within the retention window
		 *	(see \Nino\Form::retention()), most recent first, and the forms
		 *	they belong to
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
				'forms'		=> self::_forms( $appData ),
			] );
		}

		/**
		 *	Delete one recorded submission.
		 *
		 *	Guarded by the panel's own permission rather than a second one:
		 *	whoever may read these inquiries is whoever answers them, and a
		 *	person asking for theirs to be removed asks the same someone. A
		 *	permission of its own would have to be granted to the Editor role
		 *	by hand on every project that already exists, which is a fair
		 *	price for a destructive action - but not for one that removes a
		 *	single row a reader could copy out beforehand
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::VIEW_PERM ) === false )
				return;

			$id = \Nino\Admin\Admin::postData()['id'] ?? null;

			if( is_string( $id ) === false || \Nino\Form::remove( $appData, $id ) === false ) {
				\Nino\Http::fail( $request, 404, 'no such submission' );
				return;
			}

			\Nino\Http::ok( $request, [ 'deleted' => true ] );
		}

		/**
		 *	The forms a project defines, as the panel needs them: the name a
		 *	person reads, and every field with the label it was rendered
		 *	under. A submission carries names, not labels - it is the form
		 *	definition that turns 'cat' back into "Subject", and the fills in
		 *	a label are resolved here, where the interface language is known
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										key => { name, fields: [ { name, label, type } ] }
		 */
		private static function _forms( array &$appData ): array {

			$forms = [];

			foreach( \Nino\Form::forms( $appData ) as $form ) {

				$fields = [];

				foreach( $form['fields'] as $field )
					$fields[] = [
						'name'	=> $field['name'],
						'label'	=> \Nino\Html::renderHtml( $appData, $field['label'] ),
						'type'	=> $field['type'],
					];

				$forms[ $form['key'] ] = [ 'name' => $form['name'], 'fields' => $fields ];
			}

			return $forms;
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
