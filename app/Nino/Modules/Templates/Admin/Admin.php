<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\Admin	The workbench's Templates panel - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							The Template Builder as a workbench panel: a workspace (see
	 *											layout()) with the template list, the section canvas and
	 *											the inspector side by side, rendered from its own template
	 *											and scripts. The actions are the three parts' - Documents
	 *											(the page files), Library (the section presets) and Content
	 *											(native text and Element Types for a section) - merged into
	 *											one map. A developer surface: every action writes project
	 *											files, so every one of them asks for MANAGE_PERM.
 *											The shell asks the class that ran an action - one of the
 *											three tabs - for its activity-log line, so each tab
 *											describes its own writes (log()); this class has none.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/templates/manage';

		private const array PARTS = [
			Documents::class,
			Library::class,
			Content::class,
		];

		public static function actions(): array {

			$actions = [];

			foreach( self::PARTS as $part )
				$actions += $part::actions();

			return $actions;
		}

		public static function nav(): array {
			return [ 'templates', '/_admin/nav/templates', 2, 'structure' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		// The panel renders its own regions (see templates/panel.tpl) rather
		// than mount points a script fills
		public static function template(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/templates/panel' );
		}

		// Three columns need the whole width: the rail folds to its icons and
		// the pane loses its reading-width ceiling (see style.css)
		public static function layout(): string {
			return 'workspace';
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-template-icon lucide-layout-template"><rect width="18" height="7" x="3" y="3" rx="1"/><rect width="9" height="7" x="3" y="14" rx="1"/><rect width="5" height="7" x="16" y="14" rx="1"/></svg>';
		}

		// script.js seeds the namespace the other three attach to, so it leads
		public static function assets(): array {
			return array_map(
				static fn( string $file ): string => \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/'. $file ),
				[ 'script.js', 'sections.js', 'composer.js', 'area-composer.js', 'style.css' ]
			);
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	The activity-log line for a mutating action, '' for a read - see
		 *	\Nino\Admin\Admin::_logAction()
		 *
		 *	@param		string		$action				The dispatched action name
		 *	@param		array			$data					The posted data
		 *
		 *	@return 	string
		 */
		/**
		 *	Same shape as every other panel's guard: each api method calls it
		 *	itself rather than trusting the dispatcher, so a direct call in a
		 *	test or a future dispatch change cannot walk past it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool
		 */
		public static function guard( array &$appData, array &$request ): bool {
			return \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM );
		}

		public static function postData(): array {
			return \Nino\Admin\Admin::postData();
		}
	}
}
