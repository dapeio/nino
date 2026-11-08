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
	 *											turn, under a card that greets the account (see
	 *											apiSummary()). Doesn't add any storage of its own: the
	 *											sentences of the greeting are the elements of a type of
	 *											the project's, put in place once
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		// The element type the greeting's sentences are, and the words an
		// element may carry in its three fields that say when it fits
		public const string GREETINGS = '/greetings';

		private const array PHASES 		= [ 'any', 'early', 'morning', 'noon', 'afternoon', 'evening', 'night' ];
		private const array DAYS 			= [ 'any', 'monday', 'friday', 'weekend' ];
		private const array OCCASIONS = [ 'none', 'christmas', 'newyear' ];

		public static function actions(): array {
			return [ 'dashboard/summary' => [ self::class, 'apiSummary' ] ];
		}

		public static function nav(): array {
			return [ 'dashboard', '/_admin/nav/dashboard', 0, 'content' ];
		}

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'greeting', 'notice', 'tile', 'element', 'activity' ];
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
		 *	'greeting' is what the card above the notices says, and all of it
		 *	facts: the account's name, the time of the login before this one (see
		 *	\Nino\Auth, null where the session holds none) and, only
		 *	where the module is there and the account may see it, the number of
		 *	log lines since that visit and the days since the last backup. The
		 *	words, the hour and the dice are the script's (see assets/admin.js)
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

			// Before the types are counted: it puts the type of its sentences in
			// place where there is none, and that type is one of them. The card
			// is drawn only while one of its three lines is on (the switches are
			// in the Config panel); what a line that is off would have needed is
			// not fetched at all
			$greeting = self::_greeting( $appData, $body['lastBackup'] ?? null );

			if( in_array( true, $greeting['show'], true ) === true )
				$body['greeting'] = $greeting;

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
		 *	What the greeting is made of - see apiSummary(): the three switches of
		 *	the Config panel, and for each line that is on the facts it needs. The
		 *	sentences are the visible elements of the type GREETINGS, in the
		 *	interface language (a language an element has no text in takes the
		 *	native one, as everywhere), each with when it fits; the type is put in
		 *	place first if the project has none (see seedGreetings())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string|null	$lastBackup	The date of the last backup, "Y-m-d", null for none
		 *
		 *	@return 	array										{ show, name, previousLogin, activitySince?, backupDays?, sentences? }
		 */
		private static function _greeting( array &$appData, ?string $lastBackup ): array {

			$show = [
				'hello' 		=> ( $appData['/nino/admin/greeting/hello'] ?? true ) !== false,
				'sentence' 	=> ( $appData['/nino/admin/greeting/sentence'] ?? true ) !== false,
				'facts' 		=> ( $appData['/nino/admin/greeting/facts'] ?? true ) !== false,
			];

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$name 		= $current !== false && is_string( $current['name'] ?? null ) === true ? $current['name'] : '';
			$previous = \Nino\Runtime::getSessionValue( $appData, './nino/auth/previous', null );

			$greeting = [ 'show' => $show, 'name' => $name, 'previousLogin' => is_int( $previous ) === true ? $previous : null ];

			if( $show['sentence'] === true )
				$greeting['sentences'] = self::_sentences( $appData );

			if( $show['facts'] === false )
				return $greeting;

			if( $current !== false && is_int( $previous ) === true && $previous > 0
				&& class_exists( '\\Nino\\Modules\\Logs\\Admin' ) === true
				&& \Nino\Auth::checkPermission( $appData, \Nino\Modules\Logs\Admin::VIEW_PERM ) === true )
				$greeting['activitySince'] = \Nino\Modules\Logs\Admin::countSince( $appData, $previous, (string) $current['mail'], \Nino\Auth::lastLogin( $current ) );

			$backup = $lastBackup === null ? false : \DateTimeImmutable::createFromFormat( 'Y-m-d|', $lastBackup );

			if( $backup !== false )
				$greeting['backupDays'] = max( 0, (int) $backup->diff( new \DateTimeImmutable('today') )->format('%r%a') );

			return $greeting;
		}

		/**
		 *	The sentences of the greeting: every element of the type GREETINGS that
		 *	is not hidden and has a text, in the interface language. The three
		 *	fields that say when it fits are cut to the words they may hold - a
		 *	value a hand-edited file holds otherwise is 'any' or 'none'
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ { text, phase, day, occasion }, ... ]
		 */
		private static function _sentences( array &$appData ): array {

			// null is the answer for a type that is not there (an empty type is a list)
			$elements = \Nino\Elements::queryElements( $appData, self::GREETINGS, [], '*', null );

			if( $elements === null ) {

				if( isset( $appData[ \Nino\Elements::REMOVED ][ trim( self::GREETINGS, '/' ) ] ) === true
					|| self::seedGreetings( $appData, \Nino\Locales::getAvailableLocales( $appData ) ) !== true )
					return [];

				$elements = \Nino\Elements::queryElements( $appData, self::GREETINGS, [], '*', [] );
			}

			$locale = \Nino\Admin\Admin::sessionLocale( $appData );
			$list 	= [];

			foreach( $elements as $element ) {

				$full = is_array( $element ) === true && is_string( $element['.uri'] ?? null ) === true ? \Nino\Elements::getElementWithFallback( $appData, $element['.uri'], $locale ) : false;

				if( $full === false || ( $full['hidden'] ?? false ) === true || is_string( $full['text'] ?? null ) === false || trim( $full['text'] ) === '' )
					continue;

				$list[] = [
					'text' 			=> $full['text'],
					'phase' 		=> in_array( $full['phase'] ?? null, self::PHASES, true ) === true ? $full['phase'] : 'any',
					'day' 			=> in_array( $full['day'] ?? null, self::DAYS, true ) === true ? $full['day'] : 'any',
					'occasion' 	=> in_array( $full['occasion'] ?? null, self::OCCASIONS, true ) === true ? $full['occasion'] : 'none',
				];
			}

			return $list;
		}

		/**
		 *	Put the type GREETINGS and its sentences in place: what the unit file
		 *	beside this module brings, added to the project and never replacing
		 *	anything of it (see \Nino\Elements::seed()). Done by the setup wizard
		 *	for a new project and by the dashboard for one that has none yet; a type
		 *	the editors deleted is on the list of what was removed and does not come
		 *	back (see \Nino\Modules\Elements\Types::apiDelete())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$locales			The languages to bring the sentences in
		 *
		 *	@return 	true|string							true, or why it failed
		 */
		public static function seedGreetings( array &$appData, array $locales ): true|string {

			$content = include dirname( __DIR__ ). '/install/elements/greetings.php';

			return is_array( $content ) === true ? \Nino\Elements::seed( $appData, self::GREETINGS, $content, $locales ) : 'could not read the greetings';
		}
	}
}
