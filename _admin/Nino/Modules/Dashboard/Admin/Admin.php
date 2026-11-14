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
	 *											summary() - see \Nino\Admin\Panels), pulled together into
	 *											one overview instead of having to open each tab in
	 *											turn, under whatever areas a feature puts at its head
	 *											(see HEAD). Doesn't add any storage of its own
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		// The callback a feature answers to put an area at the head of the
		// dashboard, above the notices (see apiSummary())
		public const string HEAD = '/nino/admin/dashboard/head';

		public static function actions(): array {
			return [ 'dashboard/summary' => [ self::class, 'apiSummary' ] ];
		}

		public static function nav(): array {
			return [ 'dashboard', '/_admin/nav/dashboard', 0, 'content' ];
		}

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'notice', 'tile', 'element', 'activity' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-column-icon lucide-chart-column"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>';
		}

		// The one pane without the head that names it (see
		// \Nino\Admin\Panels::panesHtml()): the overview is the tiles, and
		// a row saying "Dashboard" over them would be a title over a screen
		// that has no single subject. It has the "?" of its manual in a row
		// above the screen - which is why the screen is drawn into a mount
		// of its own and not into the pane, whose first child that row is
		public static function head(): bool {
			return false;
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
		 *	require.
		 *
		 *	'notices' are what needs somebody's attention, shown above the
		 *	tiles: each is { text, values, link } - a fill key, what fills its
		 *	%s in order, and the #panel it leads to ('' for none). Mail that
		 *	fails is one for everybody who opens the dashboard, like
		 *	lastBackup: the record holds no address and no word a transport
		 *	said. Texts still to translate are the Text panel's business and
		 *	only an account allowed to open it is told. What is wrong with the
		 *	legal texts is told to one that may edit elements.
		 *
		 *	'head' is what the areas above the notices are drawn from: name =>
		 *	data, each name registered by a script of the same feature (see
		 *	assets/admin.js, head). The kernel knows none of them: a feature
		 *	adds its entry in a callback of HEAD, which is handed the entries
		 *	so far and answers them with its own; an entry whose name is no
		 *	lower-case slug or whose data is no array is named in a warning and left out, and there is no
		 *	'head' in the answer where nothing is left
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
			$body = [ 'tiles' => [], 'notices' => [] ];

			$failure = \Nino\Mail::failure( $appData );

			if( $failure !== null )
				$body['notices'][] = [ 'text' => '/_admin/dashboard/notice/mail', 'values' => [ $failure['since'], (string) $failure['count'] ], 'link' => '' ];

			if( class_exists( '\\Nino\\Modules\\Text\\Admin' ) === true
				&& \Nino\Auth::checkPermission( $appData, \Nino\Modules\Text\Admin::MANAGE_PERM ) === true )
				foreach( \Nino\Modules\Text\Admin::untranslatedCounts( $appData ) as $locale => $count )
					$body['notices'][] = [ 'text' => '/_admin/dashboard/notice/untranslated', 'values' => [ (string) $count, (string) $locale ], 'link' => '#text' ];

			// What is wrong with the imprint and the privacy policy - a placeholder
			// with no value, a language without a path, a page in no menu - is the
			// business of whoever may edit elements, and only the module knows. At
			// most eight lines (see \Nino\Modules\Legal::notices(), which has
			// none where the module is not active). Read here, on the dashboard,
			// and never by a page request
			if( class_exists( '\\Nino\\Modules\\Legal' ) === true && class_exists( '\\Nino\\Modules\\Elements\\Admin' ) === true
				&& \Nino\Auth::checkPermission( $appData, \Nino\Modules\Elements\Admin::MANAGE_PERM ) === true )
				foreach( \Nino\Modules\Legal::notices( $appData ) as $notice )
					$body['notices'][] = $notice;

			if( class_exists( '\\Nino\\Modules\\Backups' ) === true )
				$body['lastBackup'] = \Nino\Modules\Backups::lastDate( $appData );

			// Before the types are counted: what a callback puts in place - a
			// type of elements, say - is one of them
			$head = self::_head( $appData );

			if( $head !== [] )
				$body['head'] = $head;

			if( class_exists( '\\Nino\\Modules\\Elements\\Admin' ) === true )
				$body['elements'] = \Nino\Modules\Elements\Admin::typeCounts( $appData );

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

		/**
		 *	The areas of the head: every callback of HEAD is handed the areas so far and
		 *	asked on its own, and what it answers is taken area by area. A callback is a
		 *	feature's code and must not take the Dashboard down: one that throws, or
		 *	answers no list, is named and its answer dropped, the areas of the others stay;
		 *	an entry whose name is no lower-case slug or whose data is no array is named
		 *	and left out; a name that is taken stays with the callback that had it first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => data
		 */
		private static function _head( array &$appData ): array {

			$head = [];

			foreach( \Nino\Callbacks::registered( $appData, self::HEAD ) as $callback ) {

				$handed = $head;

				try {
					$answer = call_user_func_array( $callback, [ &$appData, &$handed ] ) ?? $handed;
				} catch( \Throwable $error ) {
					trigger_error( 'Dashboard head: a callback threw '. get_class( $error ). ' and its areas are dropped', E_USER_WARNING );
					continue;
				}

				if( is_array( $answer ) === false ) {
					trigger_error( 'Dashboard head: a callback answered '. gettype( $answer ). ', not a list of areas, and its answer is dropped', E_USER_WARNING );
					continue;
				}

				foreach( $answer as $name => $data ) {

					if( array_key_exists( $name, $head ) === true ) {

						if( $head[$name] !== $data )
							trigger_error( 'Dashboard head: the area \''. $name. '\' is taken, the first one stays', E_USER_WARNING );

						continue;
					}

					if( is_string( $name ) === false || preg_match( '/^[a-z][a-z0-9-]*$/D', $name ) !== 1 ) {
						trigger_error( 'Dashboard head: an area needs a lower-case slug for a name, \''. ( is_string( $name ) === true ? $name : gettype( $name ) ). '\' is left out', E_USER_WARNING );
						continue;
					}

					if( is_array( $data ) === false ) {
						trigger_error( 'Dashboard head: the area \''. $name. '\' needs an array for data and is left out', E_USER_WARNING );
						continue;
					}

					$head[$name] = $data;
				}
			}

			return $head;
		}
	}
}
