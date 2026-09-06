<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Dashboard\Admin	Dashboard panel: one overview of every module
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Dashboard {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Dashboard					Landing panel: a handful of read-only numbers
	 *											already available from the other panels (element
	 *											counts, the last backup, recent activity, and one
	 *											tile per panel that reports a number through its
	 *											summary() - see \Nino\Panels), pulled together into
	 *											one overview instead of having to open each tab in
	 *											turn. Doesn't add any storage of its own
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public static function actions(): array {
			return [ 'dashboard/summary' => [ self::class, 'apiSummary' ] ];
		}

		public static function nav(): array {
			return [ 'dashboard', '/_admin/nav/dashboard', 0, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-column-icon lucide-chart-column"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>';
		}

		// Renders straight into its pane, no mount points of its own
		public static function panes(): array {
			return [];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	Gather the overview numbers. Being reachable by any authenticated
		 *	admin (\Nino\Admin\Admin::guard(), not guardPerm()) only covers the panel
		 *	itself - elements/lastBackup are basic operational info nobody's
		 *	gated behind a specific permission, but every tile a panel
		 *	contributes (see summary() in the panel contract) surfaces that
		 *	panel's own data and is included only for an admin who actually
		 *	holds its permission, same as opening the panel directly would
		 *	require
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSummary( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			// The two numbers this panel fetches itself rather than through
			// summary() come from two other modules, and a module is a
			// directory a delivery may not have brought: asked for, never
			// assumed. What is missing is left out of the answer, and the
			// script draws the card it has data for (see assets/admin.js)
			$body = [ 'tiles' => [] ];

			if( class_exists( '\\Nino\\Modules\\Elements\\Admin' ) === true )
				$body['elements'] = \Nino\Modules\Elements\Admin::typeCounts( $appData );

			if( class_exists( '\\Nino\\Modules\\Backups' ) === true )
				$body['lastBackup'] = \Nino\Modules\Backups::lastDate( $appData );

			foreach( \Nino\Admin\Admin::allPanels( $appData ) as $panel ) {

				if( method_exists( $panel['class'], 'summary' ) === false )
					continue;

				if( $panel['perm'] !== '' && \Nino\Auth::checkPermission( $appData, $panel['perm'] ) === false )
					continue;

				$tile = $panel['class']::summary( $appData );

				if( is_array( $tile ) === true )
					$body['tiles'][] = [ 'panel' => $panel['uri'], 'value' => (string) ( $tile['value'] ?? '' ), 'label' => (string) ( $tile['label'] ?? $panel['label'] ) ];
			}

			if( class_exists( '\\Nino\\Modules\\Logs\\Admin' ) === true
				&& \Nino\Auth::checkPermission( $appData, \Nino\Modules\Logs\Admin::VIEW_PERM ) === true )
				$body['recentActivity'] = \Nino\Modules\Logs\Admin::recentLines( $appData, 8 );

			$request['/nino/http/response']['body'] = $body;
		}
	}
}
