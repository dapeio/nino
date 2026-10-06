<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Legal						see _nino/Nino/Modules/Modules.php for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Legal								The imprint and the privacy policy as elements - one
	 *											element per section, title and text per language, in the
	 *											types 'legal' and 'privacy' - drawn by [legal] and
	 *											[privacy] on two pages the module routes itself, in every
	 *											language, and linked from the menu 'legal' the setup wizard
	 *											creates.
	 *
	 *											What the module ships is a starting point and no legal
	 *											advice: the texts are not tailored to any website and not
	 *											legally reviewed, and the operator of a website is the one
	 *											who answers for them - see "Legal" in docs/development.md,
	 *											which says it in full.
	 *
	 *											An address, a phone number, the host is not written into
	 *											a text but named by a placeholder, #/project/company/contact/email#,
	 *											which placeholders() replaces with what the project's text keys
	 *											say - only keys below PREFIXES, only in the four-segment form of the
	 *											key grammar, and after the text has been made safe, so a value can
	 *											be nothing but text (see there).
	 *
	 *											A feature that processes personal data brings its own
	 *											section of the privacy policy: the 'elements' key of its
	 *											install unit, applied add-only by \Nino\Elements::seed().
	 *											Switching the feature off leaves the section where it is -
	 *											check() and contributions() say so - and deleting a section
	 *											for good is remembered (see callbackCommitted()), so an update
	 *											of the feature does not bring it back.
	 *
	 *											init() registers and reads, it writes nothing, says nothing and
	 *											triggers no warning: it runs on every request. Whatever is wrong
	 *											with the configuration is check()'s to report, for the workbench.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Legal {

		// The two pages: the page's key, the Element-URI its routes share (and
		// its /_nino/webpage<uri>/* details are named after), the element type
		// its shortcode draws and the template its routes render. The wizard
		// and the Routes panel keep both Element-URIs from every page of the
		// project's own
		public const array PAGES = [
			'imprint'	=> [ 'uri' => '/legal/imprint', 'type' => 'legal', 'template' => 'page-legal-imprint' ],
			'privacy'	=> [ 'uri' => '/legal/privacy', 'type' => 'privacy', 'template' => 'page-legal-privacy' ],
		];

		// The text keys a placeholder may name: the facts of the company and of
		// the website, which are public anyway. Not the mailbox the forms
		// deliver to, not a technical value, not a word of a template or a
		// feature - whoever may edit an element would otherwise bring any value
		// onto a public page. A constant of the kernel, deliberately no setting
		public const array PREFIXES = [ '/project/company/', '/project/website/general/' ];

		// Where the pages are reached, per page and language - config.php's key,
		// and what is used where it has none
		public const string PATHS = '/nino/legal/paths';

		private const array DEFAULT_PATHS = [
			'imprint'	=> [ 'de_DE' => '/impressum', 'en_US' => '/imprint' ],
			'privacy'	=> [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ],
		];

		// The callback a section's text goes through before it is drawn: it is
		// given [ 'type', 'id', 'html' ] and may add to 'html' - what Consent
		// does to put its settings button into its own section
		public const string SECTION = '/nino/legal/section';

		// What a path of a page has to look like: lower case words, joined by a
		// hyphen or a slash. No dot, no '..', no space, no '/_admin'
		private const string PATH_FORMAT = '#^/[a-z0-9]+(?:[-/][a-z0-9]+)*$#';

		// What a candidate for a placeholder looks like: '#', a key, '#' - not
		// one next to another '#'. Whether it is one is decided in
		// _replacement()
		private const string PLACEHOLDER = '/(?<!#)#(\/[^#\s<>]{1,200})#(?!#)/';

		// How many findings the dashboard shows before it says there are more
		private const int NOTICES = 8;

		// One section of a page - declared once, the way Navigation's markup is.
		// [[text]] is what the section's callback left of the text
		public static $html = [
			'section' => '<section class="nino-legal-section" id="[[anchor]]"[[lang]]><h3>[[title]]</h3><div class="nino-richtext">[[text]]</div></section>',
		];

		/**
		 *	Module initiating: the two shortcodes, the routes, and the two
		 *	listeners - one that tells the Seo feature about the pages, one that
		 *	remembers a section somebody deleted for good. Nothing is written
		 *	and nothing is said here - init() runs on every request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			\Nino\Html::addShortcode( $appData, 'legal', [ self::class, 'doLegalShortcode' ] );
			\Nino\Html::addShortcode( $appData, 'privacy', [ self::class, 'doPrivacyShortcode' ] );

			self::routes( $appData );

			// By name, not by constant: the Seo feature need not be there
			\Nino\Callbacks::registerCallback( $appData, '/seo/pages', [ self::class, 'callbackSeoPages' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/elements/committed', [ self::class, 'callbackCommitted' ] );
		}

		/**
		 *	[legal] - the sections of the imprint
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments (none)
		 *
		 *	@return 	string
		 */
		public static function doLegalShortcode( array &$appData, array $args ): string {
			return self::render( $appData, self::PAGES['imprint']['type'] );
		}

		/**
		 *	[privacy] - the sections of the privacy policy
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments (none)
		 *
		 *	@return 	string
		 */
		public static function doPrivacyShortcode( array &$appData, array $args ): string {
			return self::render( $appData, self::PAGES['privacy']['type'] );
		}

		/**
		 *	The sections of one type in the language of the visitor, in the order
		 *	of their 'order' field - the elements without one last - without the
		 *	ones switched off with 'hidden'. A section that has neither a title
		 *	nor a text in this language is drawn in the native language, or in
		 *	the first language that has one, and carries that language as
		 *	lang="". A type with nothing to show is ''.
		 *
		 *	Title and text are made safe the way [element] makes a field safe,
		 *	by the project's own model (\Nino\Html::fieldValue()), and only then
		 *	do the placeholders go in - see placeholders() - so that a value
		 *	can be no markup, and the Kernel's second pass over a shortcode's
		 *	output finds no '[' to read. What a listener of SECTION adds is code
		 *	of a feature and is not touched.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					'legal' or 'privacy'
		 *
		 *	@return 	string
		 */
		public static function render( array &$appData, string $type ): string {

			if( in_array( $type, array_column( self::PAGES, 'type' ), true ) === false )
				return '';

			// A type that was deleted is no section, and asking for it would say so
			// in the log on every page view
			if( \Nino\Filesystem::fileExists( $appData, '/elements/'. $type. '.php' ) === false )
				return '';

			$locale		= \Nino\Locales::getCurrentLocale( $appData );
			$model		= \Nino\Elements::getElementModel( $appData, '/'. $type );
			$elements	= \Nino\Elements::queryElements( $appData, '/'. $type, [], $locale, [], [ 'sort' => 'order' ] );
			$html			= '';

			foreach( is_array( $elements ) === true ? $elements : [] as $element ) {

				if( is_array( $element ) === false || ( $element['hidden'] ?? false ) === true || is_string( $element['.uri'] ?? null ) === false )
					continue;

				$id									= \Nino\Elements::getElementUriFromUri( $element['.uri'] );
				[ $title, $text, $from ] = self::_version( $appData, $element, $locale );

				if( $title === '' && $text === '' )
					continue;

				$text		= self::placeholders( $appData, \Nino\Html::fieldValue( $text, (array) ( $model['text'] ?? [] ) ) );
				$title	= self::placeholders( $appData, \Nino\Html::fieldValue( $title, (array) ( $model['title'] ?? [] ) ) );

				$section = [ 'type' => $type, 'id' => $id, 'html' => $text ];
				\Nino\Callbacks::doCallbacks( $appData, self::SECTION, $section );

				$html .= strtr( self::$html['section'], [
					'[[anchor]]'	=> self::_attribute( $type. '-'. $id ),
					'[[lang]]'		=> $from === $locale ? '' : ' lang="'. self::_attribute( str_replace( '_', '-', $from ) ). '"',
					'[[title]]'		=> $title,
					'[[text]]'		=> is_string( $section['html'] ?? null ) === true ? $section['html'] : $text,
				] );
			}

			return $html;
		}

		/**
		 *	Replace the placeholders in html that is safe already: #/project/company/contact/email#
		 *	becomes what that key says in the current language, as text.
		 *
		 *	Only a key the grammar accepts (\Nino\Text::isGrammarKey(), four
		 *	segments) below PREFIXES is replaced; anything else stays as it is
		 *	written, and so does a key with no value, so that it shows. A key
		 *	whose value is empty is replaced by nothing. The value is the one
		 *	[[key]] would give, nested fills resolved (\Nino\Html::resolveTextfill()), and
		 *	then made text: a line end of it stays one, tags and entities go, the
		 *	rest is escaped, and every bracket is an entity - a value can carry
		 *	no markup, no fill and no shortcode into the page.
		 *
		 *	Only in what stands between two tags, never inside one: the sanitizer
		 *	has checked a href as it stood, and a value put into it afterwards
		 *	would be no longer checked. The sanitizer builds its output itself, so
		 *	a '<' or a '>' that is not a tag's is an entity (see the test).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$html					Html as sanitizeHtml() or fieldValue() gives it
		 *
		 *	@return 	string
		 */
		public static function placeholders( array &$appData, string $html ): string {

			if( str_contains( $html, '#/' ) === false )
				return $html;

			$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

			if( $parts === false )
				return $html;

			// Tags stand at the odd places
			foreach( $parts as $index => $part )
				if( $index % 2 === 0 )
					$parts[$index] = (string) preg_replace_callback( self::PLACEHOLDER, function( array $match ) use ( &$appData ): string {
						return self::_replacement( $appData, $match[1] ) ?? $match[0];
					}, $part );

			return implode( '', $parts );
		}

		/**
		 *	The routes of the two pages, one per language - or one for several
		 *	languages that share a path - registered in the live route array
		 *	and nowhere else: a runtime route, so that the wizard and the Routes
		 *	panel, which know one path per page, are not broken by it. They
		 *	carry 'maintenance' => false and stay reachable while the site is down.
		 *
		 *	A route is only registered where the address is free. A path that is
		 *	invalid, taken by another route, or missing for a language is left
		 *	out, in silence - check() says so. A language that ends up without
		 *	a route of its own gets one under its own code, in front of the path
		 *	of the native language: /fr-fr/impressum, so that every language has a
		 *	page, a place in the menu and a language switch that leads somewhere.
		 *	The routes of one language come first, the ones several languages
		 *	share after them, because the first route that fits is the one
		 *	\Nino\Http::findRouteUri() answers.
		 *
		 *	Its own method, so a test can build the routes again
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function routes( array &$appData ): void {

			foreach( self::PAGES as $page => $definition )
				foreach( self::_plan( $appData, $page )['routes'] as $entry )
					$appData['/nino/http/routes'][ $entry['key'] ] = $entry['route'];
		}

		/**
		 *	The address of one page, in the directory the project is served
		 *	from - for a link, in the language wanted, else in the native one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$page					'imprint' or 'privacy'
		 *	@param		string		$locale				The language, '' for the current one
		 *
		 *	@return 	string									'' where the module is not active or the page has no route
		 */
		public static function url( array &$appData, string $page, string $locale = '' ): string {

			if( isset( self::PAGES[$page] ) === false || self::_active( $appData ) === false )
				return '';

			$wanted = $locale !== '' ? $locale : \Nino\Locales::getCurrentLocale( $appData );

			foreach( array_unique( [ $wanted, \Nino\Locales::getNativeLocale( $appData ) ] ) as $try ) {

				$routeKey = \Nino\Http::findRouteUri( $appData, self::PAGES[$page]['uri'], $try );

				if( $routeKey !== null && self::_isOurs( $appData['/nino/http/routes'][$routeKey] ?? [], $page ) === true )
					return rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). substr( $routeKey, strlen( 'GET:/' ) );
			}

			return '';
		}

		/**
		 *	Tell the Seo feature about the pages, which are in no config.php: one
		 *	entry per route - per language that shares one - with the details of
		 *	the page in that language and the day the type's file was changed.
		 *	Listener of '/seo/pages', see that feature's README
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$pages				(reference) The pages so far, entries are appended
		 *
		 *	@return 	void
		 */
		public static function callbackSeoPages( array &$appData, array &$pages ): void {

			foreach( self::PAGES as $page => $definition ) {

				$file		= \Nino\Filesystem::path( $appData, '/elements/'. $definition['type']. '.php' );
				$mtime	= is_file( $file ) === true ? filemtime( $file ) : false;

				foreach( self::_plan( $appData, $page )['routes'] as $entry )
					foreach( $entry['locales'] as $locale )
						$pages[] = [
							'externalPath'	=> substr( $entry['key'], strlen( 'GET:/' ) ),
							'uri'						=> $definition['uri'],
							'locale'				=> $locale,
							'lastmod'				=> $mtime === false ? null : date( 'Y-m-d', $mtime ),
							'title'					=> self::_pageText( $appData, $definition['uri'], 'title', $locale ),
							'description'		=> self::_pageText( $appData, $definition['uri'], 'description', $locale ),
						];
			}
		}

		/**
		 *	Listener of '/nino/elements/committed': a section of the two types that
		 *	is deleted for good - in every language - and that belongs to this
		 *	module or to a feature that is switched on, goes into
		 *	'/nino/elements/removed', so that \Nino\Elements::seed() does not put it
		 *	back with the next update of that feature. An element that is created
		 *	again by hand is taken out of the list. Everything else - another type,
		 *	one language only, a section of a feature that is off, one of the
		 *	project's own - is none of this listener's business, and cheap to say so
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$change			(reference) The change, see \Nino\Elements
		 *
		 *	@return 	void
		 */
		public static function callbackCommitted( array &$appData, array &$change ): void {

			$type = trim( (string) ( $change['type'] ?? '' ), '/' );
			$uri	= (string) ( $change['uri'] ?? '' );

			if( in_array( $type, array_column( self::PAGES, 'type' ), true ) === false || $uri === '' )
				return;

			$operation = (string) ( $change['operation'] ?? '' );

			if( $operation === 'delete' && ( $change['locale'] ?? '' ) !== '*' )
				return;

			if( $operation !== 'delete' && $operation !== 'insert' )
				return;

			$id = \Nino\Elements::getElementUriFromUri( $uri );

			if( $id === '' || ( $operation === 'delete' && self::_ownedSection( $appData, $type, $id ) === false ) )
				return;

			// A new element only matters where it was removed before
			if( $operation === 'insert' && in_array( $id, array_map( 'strval', (array) ( $appData[ \Nino\Elements::REMOVED ][$type] ?? [] ) ), true ) === false )
				return;

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
				return;

			try {

				$removed = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ] ?? [];
				$removed = is_array( $removed ) === true ? $removed : [];
				$list		 = array_values( array_map( 'strval', (array) ( $removed[$type] ?? [] ) ) );
				$listed	 = in_array( $id, $list, true );

				if( ( $operation === 'delete' ) === $listed )
					return;

				$list = $operation === 'delete' ? array_merge( $list, [ $id ] ) : array_values( array_diff( $list, [ $id ] ) );

				if( $list === [] )
					unset( $removed[$type] );
				else
					$removed[$type] = $list;

				if( $removed === [] )
					unset( $appData[ \Nino\Elements::REMOVED ] );
				else
					$appData[ \Nino\Elements::REMOVED ] = $removed;

				\Nino\AppData::writeContentData( $appData, [ \Nino\Elements::REMOVED ] );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	What a language added after the setup needs of this module: nothing
		 *	applies the unit again then (the wizard is closed, and a feature's
		 *	activation knows features only), so the language panel asks.
		 *
		 *	The new text file gets the names, titles and descriptions of the two
		 *	pages and the words of the Elements panel for the two types, from the
		 *	unit's text of that language where it has one - it is new, so what the
		 *	unit says wins over its empty values. Any other language has neither
		 *	unit text nor translation: it gets the two page names of the native
		 *	language, so that its entries appear in the menu, and the title and the
		 *	description stay empty, to be translated. And every section the
		 *	project has gets the version of this language that the unit - and the
		 *	units of the active features - bring for it: for an id the type has
		 *	and a language it lacks, nothing else, so a section somebody deleted
		 *	does not come back and no section is new.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$locale				The new language, its text file already there - it need not be switched on yet, which is the form's Save
		 *
		 *	@return 	true|string							true, or why a file could not be written
		 */
		public static function addLocale( array &$appData, string $locale ): true|string {

			if( self::_active( $appData ) === false || preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $locale ) !== 1 )
				return true;

			$textDir	= '/text';
			$path			= $textDir. '/'. $locale. '.php';
			$unit			= __DIR__. '/install/text/'. $locale. '.php';
			$words		= is_file( $unit ) === true ? (array) include $unit : [];

			if( $words === [] ) {

				$native		= \Nino\Filesystem::getFileContent( $appData, $textDir. '/'. \Nino\Locales::getNativeLocale( $appData ). '.php', [] );
				$native		= is_array( $native ) === true ? $native : [];

				foreach( self::PAGES as $definition )
					if( is_string( $native[ '[[/_nino/webpage'. $definition['uri']. '/name]]' ] ?? null ) === true && $native[ '[[/_nino/webpage'. $definition['uri']. '/name]]' ] !== '' )
						$words[ '[[/_nino/webpage'. $definition['uri']. '/name]]' ] = $native[ '[[/_nino/webpage'. $definition['uri']. '/name]]' ];
			}

			if( $words !== [] && \Nino\Features::mergeText( $appData, $path, $words, true ) === false )
				return 'could not write '. $path;

			foreach( self::_unitElements( $appData, true ) as $unitElements ) {

				// Only what the project has, and only the language that is new
				$stored		= \Nino\Filesystem::getFileContent( $appData, '/elements/'. $unitElements['type']. '.php', [] );
				$present	= self::_sectionIds( is_array( $stored ) === true ? $stored : [] );

				$data = [ '*' => array_intersect_key( (array) ( $unitElements['data']['*'] ?? [] ), $present ), $locale => array_intersect_key( (array) ( $unitElements['data'][$locale] ?? [] ), $present ) ];

				if( $data[$locale] === [] )
					continue;

				$seeded = \Nino\Elements::seed( $appData, $unitElements['type'], $data, [ $locale ] );

				if( $seeded !== true )
					return $seeded;
			}

			return true;
		}

		/**
		 *	The sections one feature brought to the privacy policy that are
		 *	still to be seen: in the project, not hidden - what the Features panel
		 *	names after switching the feature off or removing it, and the
		 *	dashboard names while it stays installed. Read from the 'elements'
		 *	key of the feature's own unit, so it works for a feature that is not
		 *	active, and as long as its directory is there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$featureKey		The feature's key
		 *	@param		string		$locale				The language of the titles, '' for the native one
		 *
		 *	@return 	array										[ [ 'type', 'id', 'title' ], ... ]
		 */
		public static function contributions( array &$appData, string $featureKey, string $locale = '' ): array {

			$feature = \Nino\Features::get( $appData, $featureKey );

			if( $feature === null || self::_active( $appData ) === false )
				return [];

			$locale = $locale !== '' && \Nino\Locales::verifyLocale( $appData, $locale ) === true ? $locale : \Nino\Locales::getNativeLocale( $appData );
			$found	= [];

			foreach( self::_featureElements( $feature ) as $unitElements )
				foreach( array_keys( self::_sectionIds( $unitElements['data'] ) ) as $id ) {

					$element = \Nino\Elements::getElement( $appData, '/'. $unitElements['type']. '/'. $id, $locale, false );

					if( is_array( $element ) === false || ( $element['hidden'] ?? false ) === true )
						continue;

					$found[] = [ 'type' => $unitElements['type'], 'id' => (string) $id, 'title' => (string) ( $element['title'] ?? '' ) !== '' ? (string) $element['title'] : (string) $id ];
				}

			return $found;
		}

		/**
		 *	What is wrong with the legal texts, for the workbench - read the way
		 *	the pages are drawn, in every language of the project:
		 *
		 *	- unknown: a placeholder for a key that has no value in a language
		 *	- empty: one whose value is empty
		 *	- ignored: something that looks like one and is never replaced - a key
		 *	  of another namespace or of another form
		 *	- translation: a section that has neither a title nor a text in a language
		 *	- nothing: a type that is missing - the unit's file to copy back is
		 *	  named - or has no section to be seen
		 *	- route: a path in /nino/legal/paths that is invalid or belongs to
		 *	  another route
		 *	- route-derived: a language without a path of its own, reached under
		 *	  its code in front of another path - /fr-fr/impressum
		 *	- route-none: a language that has no route at all - no path to derive
		 *	  one from, or the derived one is taken
		 *	- route-stored: a stored route that carries the Element-URI of one of
		 *	  the pages
		 *	- nav: a page that no menu a template of the project outputs has, in a
		 *	  language, with the real menu code asked - or the Navigation module is off
		 *	- feature: a section of a feature that is switched off, still to be seen
		 *	- undescribed: a feature that is on and has no section to be seen
		 *
		 *	Slow on purpose and for the dashboard alone - never run by a page
		 *	request. The language of the request is the one afterwards, as before
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'kind' => string, 'values' => [ string, ... ], 'link' => string ], ... ]
		 */
		public static function check( array &$appData ): array {

			if( self::_active( $appData ) === false )
				return [];

			$findings	= [];
			$locales	= \Nino\Locales::getAvailableLocales( $appData );
			$current	= \Nino\Locales::getCurrentLocale( $appData );
			$native		= \Nino\Locales::getNativeLocale( $appData );

			try {

				foreach( self::PAGES as $page => $definition ) {

					$type			= $definition['type'];
					$model		= \Nino\Filesystem::fileExists( $appData, '/elements/'. $type. '.php' ) === true ? \Nino\Elements::getElementModel( $appData, '/'. $type ) : [];

					if( $model === [] ) {
						$findings[] = [ 'kind' => 'nothing', 'values' => [ $type, self::_unitFile( $type ) ], 'link' => '#elements/'. $type ];
						continue;
					}

					$sections = [];

					foreach( $locales as $locale ) {

						\Nino\Locales::useLocale( $appData, $locale );

						$elements = \Nino\Elements::queryElements( $appData, '/'. $type, [], $locale, [], [ 'sort' => 'order' ] );

						foreach( is_array( $elements ) === true ? $elements : [] as $element ) {

							if( is_array( $element ) === false || ( $element['hidden'] ?? false ) === true || is_string( $element['.uri'] ?? null ) === false )
								continue;

							$id = \Nino\Elements::getElementUriFromUri( $element['.uri'] );
							$sections[$id] = true;
							$link = '#elements/'. $type. '/'. $id;

							if( (string) ( $element['title'] ?? '' ) === '' && (string) ( $element['text'] ?? '' ) === '' )
								$findings[] = [ 'kind' => 'translation', 'values' => [ self::_title( $element, $id, $native, $appData ), $locale ], 'link' => $link ];

							foreach( [ 'title', 'text' ] as $field )
								foreach( self::_candidates( \Nino\Html::fieldValue( (string) ( $element[$field] ?? '' ), (array) ( $model[$field] ?? [] ) ) ) as $key ) {

									$kind = self::_placeholderKind( $appData, $key );

									if( $kind !== null )
										$findings[] = [ 'kind' => $kind, 'values' => [ '#'. $key. '#', self::_title( $element, $id, $native, $appData ), $locale ], 'link' => $link ];
								}
						}
					}

					if( $sections === [] )
						$findings[] = [ 'kind' => 'nothing', 'values' => [ $type, self::_unitFile( $type ) ], 'link' => '#elements/'. $type ];
				}

				\Nino\Locales::useLocale( $appData, $current );

				$findings = array_merge( $findings, self::_checkRoutes( $appData ), self::_checkNav( $appData ), self::_checkFeatures( $appData ) );
			} finally {
				\Nino\Locales::useLocale( $appData, $current );
			}

			// A finding is said once, however many sections and languages find it
			$unique = [];
			foreach( $findings as $finding )
				$unique[ json_encode( $finding ) ] = $finding;

			return array_values( $unique );
		}

		/**
		 *	check() as the dashboard shows it: a notice - the fill of its text,
		 *	what goes into it, where it leads - for each finding, the first
		 *	eight, and one that says how many more there are
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'text', 'values', 'link' ], ... ], the shape Dashboard\Admin::apiSummary() uses
		 */
		public static function notices( array &$appData ): array {

			$findings = self::check( $appData );
			$notices	= [];

			foreach( array_slice( $findings, 0, self::NOTICES ) as $finding )
				$notices[] = [ 'text' => '/_admin/dashboard/notice/legal-'. $finding['kind'], 'values' => $finding['values'], 'link' => $finding['link'] ];

			if( count( $findings ) > self::NOTICES )
				$notices[] = [ 'text' => '/_admin/dashboard/notice/legal-more', 'values' => [ (string) ( count( $findings ) - self::NOTICES ) ], 'link' => '' ];

			return $notices;
		}

		/**
		 *	The routes of one page as routes() would register them, and what is
		 *	wrong with the paths on the way - the one place that decides, for
		 *	routes(), the Seo listener and check().
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$page					'imprint' or 'privacy'
		 *
		 *	@return 	array										[ 'routes' => [ [ 'key', 'route', 'locales' ], ... ], 'problems' => [ [ 'kind', 'locale', 'path' ], ... ] ] - kind is
		 *																	'invalid' or 'taken' for a configured path, 'derived' for a language whose route is the one under
		 *																	its own code, 'none' for one that has no route at all
		 */
		private static function _plan( array &$appData, string $page ): array {

			$definition	= self::PAGES[$page];
			$locales		= array_values( \Nino\Locales::getAvailableLocales( $appData ) );
			$native			= \Nino\Locales::getNativeLocale( $appData );
			$configured	= self::_paths( $appData )[$page];
			$problems		= [];
			$byPath			= [];

			foreach( $locales as $locale ) {

				$path = $configured[$locale] ?? null;

				if( is_string( $path ) === false )
					continue;

				if( preg_match( self::PATH_FORMAT, $path ) !== 1 ) {
					$problems[] = [ 'kind' => 'invalid', 'locale' => $locale, 'path' => $path ];
					continue;
				}

				if( self::_isFree( $appData, $path, $page ) === false ) {
					$problems[] = [ 'kind' => 'taken', 'locale' => $locale, 'path' => $path ];
					continue;
				}

				$byPath[$path][] = $locale;
			}

			$bound		= [];
			$shared		= [];
			$covered	= [];

			foreach( $byPath as $path => $sharing ) {

				$key = 'GET:/'. $path;

				if( count( $sharing ) === 1 )
					$bound[] = [ 'key' => $key, 'route' => [ 'uri' => $definition['uri'], 'locale' => $sharing[0], 'body' => self::_body( $definition ), 'maintenance' => false ], 'locales' => $sharing ];
				else
					$shared[] = [ 'key' => $key, 'route' => [ 'uri' => $definition['uri'], 'body' => self::_body( $definition ), 'maintenance' => false ], 'locales' => $sharing ];

				$covered = array_merge( $covered, $sharing );
			}

			// A route without a language fits every language, so none is left over
			if( $shared === [] ) {

				$base = null;

				// The path of the native language if it is a route, else one that is,
				// else any that is valid - a path that is taken makes a poor name
				$nativePath = $configured[$native] ?? null;

				foreach( array_merge( is_string( $nativePath ) === true && isset( $byPath[$nativePath] ) === true ? [ $nativePath ] : [], array_map( 'strval', array_keys( $byPath ) ), array_values( $configured ) ) as $path )
					if( is_string( $path ) === true && preg_match( self::PATH_FORMAT, $path ) === 1 ) {
						$base = $path;
						break;
					}

				foreach( array_diff( $locales, $covered ) as $locale ) {

					$path = $base === null ? null : '/'. strtolower( str_replace( '_', '-', $locale ) ). $base;

					if( $path === null ) {
						$problems[] = [ 'kind' => 'none', 'locale' => $locale, 'path' => '' ];
						continue;
					}

					if( self::_isFree( $appData, $path, $page ) === false ) {
						$problems[] = [ 'kind' => 'none', 'locale' => $locale, 'path' => $path ];
						continue;
					}

					$problems[] = [ 'kind' => 'derived', 'locale' => $locale, 'path' => $path ];
					$bound[] = [ 'key' => 'GET:/'. $path, 'route' => [ 'uri' => $definition['uri'], 'locale' => $locale, 'body' => self::_body( $definition ), 'maintenance' => false ], 'locales' => [ $locale ] ];
				}
			}

			return [ 'routes' => array_merge( $bound, $shared ), 'problems' => $problems ];
		}

		/**
		 *	@param		array 		$definition		One of PAGES
		 *
		 *	@return 	string									The body of the page's routes
		 */
		private static function _body( array $definition ): string {
			return '[template /templates/'. $definition['template']. ']';
		}

		/**
		 *	The paths of every page, per language - the configuration where it
		 *	has one for a page, the default of the module where not
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Page => locale => path, strings as they are written
		 */
		private static function _paths( array &$appData ): array {

			$configured	= is_array( $appData[ self::PATHS ] ?? null ) === true ? $appData[ self::PATHS ] : [];
			$paths			= [];

			foreach( self::DEFAULT_PATHS as $page => $defaults ) {

				$paths[$page] = is_array( $configured[$page] ?? null ) === true ? $configured[$page] : $defaults;

				foreach( $paths[$page] as $locale => $path )
					if( is_string( $locale ) === false || is_string( $path ) === false )
						unset( $paths[$page][$locale] );
			}

			return $paths;
		}

		/**
		 *	Whether an address is free for a page: no route holds it, or the one
		 *	that does is this module's own for that page - a second pass of
		 *	routes() meets its first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path
		 *	@param		string		$page
		 *
		 *	@return 	bool
		 */
		private static function _isFree( array &$appData, string $path, string $page ): bool {

			$route = $appData['/nino/http/routes']['GET:/'. $path] ?? null;

			return $route === null || self::_isOurs( $route, $page );
		}

		/**
		 *	@param		mixed			$route				A route
		 *	@param		string		$page
		 *
		 *	@return 	bool										Whether it is one this module registers for the page
		 */
		private static function _isOurs( mixed $route, string $page ): bool {

			return is_array( $route ) === true
				&& ( $route['uri'] ?? null ) === self::PAGES[$page]['uri']
				&& ( $route['body'] ?? null ) === self::_body( self::PAGES[$page] )
				&& ( $route['maintenance'] ?? null ) === false;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool										Whether the module is listed in '/nino/modules'
		 */
		private static function _active( array &$appData ): bool {
			return in_array( '\\Nino\\Modules\\Legal', array_map( static fn( mixed $class ): string => '\\'. ltrim( (string) $class, '\\' ), (array) ( $appData['/nino/modules'] ?? [] ) ), true );
		}

		/**
		 *	One detail of a page - 'title', 'description' - in a language, from
		 *	the text files, the way the page reads it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					The page's Element-URI
		 *	@param		string		$field
		 *	@param		string		$locale
		 *
		 *	@return 	string|null							Null where there is none
		 */
		private static function _pageText( array &$appData, string $uri, string $field, string $locale ): ?string {

			$textDir	= '/text';
			$key			= '[[/_nino/webpage'. $uri. '/'. $field. ']]';
			$value		= \Nino\Filesystem::getFileContent( $appData, $textDir. '/'. $locale. '.php', [] )[$key]
				?? \Nino\Filesystem::getFileContent( $appData, $textDir. '/global.php', [] )[$key] ?? null;

			return is_string( $value ) === true && $value !== '' ? $value : null;
		}

		/**
		 *	Title and text of an element in one language and, where the
		 *	language has neither, in the native one, else in the first that
		 *	has either
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$element			The element in $locale
		 *	@param		string		$locale
		 *
		 *	@return 	array										[ title, text, the language it is in ]
		 */
		private static function _version( array &$appData, array $element, string $locale ): array {

			foreach( array_unique( array_merge( [ $locale, \Nino\Locales::getNativeLocale( $appData ) ], \Nino\Locales::getAvailableLocales( $appData ) ) ) as $try ) {

				$version = $try === $locale ? $element : \Nino\Elements::getElement( $appData, (string) $element['.uri'], $try, [] );

				if( is_array( $version ) === false )
					continue;

				$title	= (string) ( $version['title'] ?? '' );
				$text		= (string) ( $version['text'] ?? '' );

				if( $title !== '' || $text !== '' )
					return [ $title, $text, $try ];
			}

			return [ '', '', $locale ];
		}

		/**
		 *	@param		string		$value
		 *
		 *	@return 	string									An attribute value that cannot start a fill or a shortcode
		 */
		private static function _attribute( string $value ): string {
			return str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
		}

		/**
		 *	What replaces one candidate: the key's value as safe text, or null
		 *	where the candidate is to stay as it is written
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					The candidate's key, with its leading slash
		 *
		 *	@return 	string|null
		 */
		private static function _replacement( array &$appData, string $key ): ?string {

			if( self::_allowed( $key ) === false )
				return null;

			$value = \Nino\Html::resolveTextfill( $appData, $key );

			if( $value === null )
				return null;

			$value = self::_plain( $value );
			$value = htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			$value = str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], $value );

			return str_replace( "\n", '<br>', $value );
		}

		/**
		 *	@param		string		$key
		 *
		 *	@return 	bool										Whether a placeholder may name it: the grammar's form, below one of PREFIXES
		 */
		private static function _allowed( string $key ): bool {

			if( \Nino\Text::isGrammarKey( $key ) === false )
				return false;

			foreach( self::PREFIXES as $prefix )
				if( str_starts_with( $key, $prefix ) === true )
					return true;

			return false;
		}

		/**
		 *	A value as the plain text it reads as: line breaks and the ends of
		 *	blocks stay lines, the tags go, the entities become the characters
		 *	they name. What is left is neither escaped nor safe
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string
		 */
		private static function _plain( string $value ): string {

			$value = \Nino\Html::breaksToNewlines( $value );
			$value = html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			return trim( str_replace( [ "\r\n", "\r" ], "\n", $value ) );
		}

		/**
		 *	The candidates for a placeholder in html that is safe: what stands
		 *	between two tags, the way placeholders() reads it
		 *
		 *	@param		string		$html
		 *
		 *	@return 	array										The keys with their leading slash, as written
		 */
		private static function _candidates( string $html ): array {

			$keys		= [];
			$parts	= preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [];

			foreach( $parts as $index => $part )
				if( $index % 2 === 0 && preg_match_all( self::PLACEHOLDER, $part, $found ) > 0 )
					$keys = array_merge( $keys, $found[1] );

			return array_values( array_unique( $keys ) );
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					A candidate's key
		 *
		 *	@return 	string|null							The finding it is - 'ignored', 'unknown', 'empty' - null for a placeholder that works
		 */
		private static function _placeholderKind( array &$appData, string $key ): ?string {

			if( self::_allowed( $key ) === false )
				return 'ignored';

			$value = \Nino\Html::resolveTextfill( $appData, $key );

			if( $value === null )
				return 'unknown';

			return self::_plain( $value ) === '' ? 'empty' : null;
		}

		/**
		 *	@param		array 		$element			An element
		 *	@param		string		$id
		 *	@param		string		$native				The native language
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Its title - in the native language where the one in hand has none - or its id
		 */
		private static function _title( array $element, string $id, string $native, array &$appData ): string {

			$title = (string) ( $element['title'] ?? '' );

			if( $title === '' )
				$title = (string) ( \Nino\Elements::getElement( $appData, (string) ( $element['.uri'] ?? '' ), $native, [] )['title'] ?? '' );

			return $title !== '' ? $title : $id;
		}

		/**
		 *	@param		string		$type					'legal' or 'privacy'
		 *
		 *	@return 	string									The file of the unit to copy back to /elements/ - what check() names
		 */
		private static function _unitFile( string $type ): string {
			return '_nino/Nino/Modules/Legal/install/elements/'. $type. '.php';
		}

		/**
		 *	The routes problems, as findings: each language that had to be given
		 *	another path or none, each invalid or taken path, and every route
		 *	of config.php that carries the Element-URI of a page
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Findings, see check()
		 */
		private static function _checkRoutes( array &$appData ): array {

			$findings	= [];

			foreach( self::PAGES as $page => $definition ) {

				$name = \Nino\Html::renderTextfill( $appData, '/_nino/webpage'. $definition['uri']. '/name' );
				$name = $name !== '' ? $name : $definition['uri'];

				foreach( self::_plan( $appData, $page )['problems'] as $problem ) {

					$kind = match( $problem['kind'] ) {
						'derived'	=> 'route-derived',
						'none'		=> 'route-none',
						default		=> 'route',
					};

					$findings[] = [ 'kind' => $kind, 'values' => [ $name, $problem['locale'], $problem['path'] !== '' ? $problem['path'] : '-' ], 'link' => '' ];
				}

				$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];

				foreach( is_array( $stored ) === true ? $stored : [] as $routeKey => $route )
					if( is_array( $route ) === true && ( $route['uri'] ?? null ) === $definition['uri'] )
						$findings[] = [ 'kind' => 'route-stored', 'values' => [ (string) $routeKey, $name ], 'link' => '' ];
			}

			return $findings;
		}

		/**
		 *	The nav findings: for each page, the languages in which no menu a
		 *	template of the project outputs has it - found by asking the menu
		 *	itself, in each language, for its lines
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Findings, see check()
		 */
		private static function _checkNav( array &$appData ): array {

			$findings	= [];
			$locales	= \Nino\Locales::getAvailableLocales( $appData );
			$native		= \Nino\Locales::getNativeLocale( $appData );
			$active		= in_array( '\\Nino\\Modules\\Navigation', array_map( static fn( mixed $class ): string => '\\'. ltrim( (string) $class, '\\' ), (array) ( $appData['/nino/modules'] ?? [] ) ), true );
			$navs			= $active === true ? self::_templateNavs( $appData ) : [];

			foreach( self::PAGES as $page => $definition ) {

				$missing = [];

				foreach( $locales as $locale ) {

					\Nino\Locales::useLocale( $appData, $locale );

					$routeKey	= \Nino\Http::findRouteUri( $appData, $definition['uri'], $locale );
					$path			= $routeKey === null ? '' : substr( $routeKey, strlen( 'GET:/' ) );
					$found		= false;

					foreach( $active === true && $path !== '' ? $navs : [] as $nav )
						foreach( \Nino\Modules\Navigation::routeLines( $appData, $nav ) as $line )
							if( explode( ':', $line, 2 )[0] === $path )
								$found = true;

					if( $found === false )
						$missing[] = $locale;
				}

				if( $missing !== [] ) {
					\Nino\Locales::useLocale( $appData, $native );
					$name = \Nino\Html::renderTextfill( $appData, '/_nino/webpage'. $definition['uri']. '/name' );
					$findings[] = [ 'kind' => 'nav', 'values' => [ $name !== '' ? $name : $definition['uri'], implode( ', ', $missing ) ], 'link' => '#navs' ];
				}
			}

			return $findings;
		}

		/**
		 *	The menus a template of the project outputs: every key a
		 *	[navigation nav="..."] names, anywhere below /templates. One that a
		 *	template builds from a fill is not seen, and a template nobody
		 *	includes is counted - a hint, no guarantee
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Menu keys
		 */
		private static function _templateNavs( array &$appData ): array {

			$dir	= \Nino\Filesystem::path( $appData, '/templates' );
			$navs	= [];

			if( is_dir( $dir ) === false )
				return [];

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
				if( $file->isFile() === true && $file->getExtension() === 'tpl' && preg_match_all( '/\[navigation\b[^\]]*\bnav="([^"\[]+)"/', (string) file_get_contents( $file->getPathname() ), $found ) > 0 )
					$navs = array_merge( $navs, $found[1] );

			return array_values( array_unique( $navs ) );
		}

		/**
		 *	The feature findings: a feature that is off and still has sections to
		 *	be seen, and one that is on and has none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Findings, see check()
		 */
		private static function _checkFeatures( array &$appData ): array {

			$findings	= [];
			$native		= \Nino\Locales::getNativeLocale( $appData );

			foreach( \Nino\Features::all( $appData ) as $key => $feature ) {

				if( self::_featureElements( $feature ) === [] )
					continue;

				$name				= \Nino\Features::localized( $feature['name'] ?? '', $native );
				$name				= $name !== '' ? $name : (string) $key;
				$sections		= self::contributions( $appData, (string) $key );

				if( $feature['active'] === false && $sections !== [] )
					$findings[] = [ 'kind' => 'feature', 'values' => [ $name, implode( ', ', array_column( $sections, 'title' ) ) ], 'link' => '#elements/privacy' ];

				if( $feature['active'] === true && $sections === [] )
					$findings[] = [ 'kind' => 'undescribed', 'values' => [ $name ], 'link' => '#elements/privacy' ];
			}

			return $findings;
		}

		/**
		 *	The elements the install unit of one feature brings: the 'elements'
		 *	key of its manifest, each file read, for the types of the two pages
		 *	only
		 *
		 *	@param		array 		$feature			One of \Nino\Features::all()
		 *
		 *	@return 	array										[ [ 'type', 'data' ], ... ]
		 */
		private static function _featureElements( array $feature ): array {

			return self::_unitElementsOf( (string) ( $feature['dir'] ?? '' ). '/install' );
		}

		/**
		 *	The elements of this module's unit, and - with $features - of the
		 *	units of every active feature
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$features			Whether the active features' units are included
		 *
		 *	@return 	array										[ [ 'type', 'data' ], ... ]
		 */
		private static function _unitElements( array &$appData, bool $features ): array {

			$found = self::_unitElementsOf( __DIR__. '/install' );

			if( $features === true )
				foreach( \Nino\Features::all( $appData ) as $feature )
					if( $feature['active'] === true )
						$found = array_merge( $found, self::_featureElements( $feature ) );

			return $found;
		}

		/**
		 *	@param		string		$unitDir			An install unit
		 *
		 *	@return 	array										[ [ 'type', 'data' ], ... ] for the types of the pages, files read like applyUnit() reads them
		 */
		private static function _unitElementsOf( string $unitDir ): array {

			$manifest	= \Nino\Features::readUnitManifest( $unitDir ) ?? [];
			$found		= [];

			foreach( (array) ( $manifest['elements'] ?? [] ) as $type => $file ) {

				if( in_array( $type, array_column( self::PAGES, 'type' ), true ) === false || is_string( $file ) === false || $file === ''
					|| str_contains( $file, '..' ) === true || str_starts_with( $file, '/' ) === true || is_file( $unitDir. '/'. $file ) === false )
					continue;

				$data = include $unitDir. '/'. $file;

				if( is_array( $data ) === true )
					$found[] = [ 'type' => (string) $type, 'data' => $data ];
			}

			return $found;
		}

		/**
		 *	@param		array 		$data					The content of an element file of a unit
		 *
		 *	@return 	array										The ids of its elements, as keys
		 */
		private static function _sectionIds( array $data ): array {

			$ids = [];

			foreach( $data as $bucket => $elements )
				if( $bucket !== 'model' && $bucket !== 'title' && is_array( $elements ) === true )
					foreach( array_keys( $elements ) as $id )
						if( $id !== '*' )
							$ids[(string) $id] = true;

			return $ids;
		}

		/**
		 *	Whether a section belongs to this module, or to a feature that is
		 *	on - the ones a tombstone is set for
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type
		 *	@param		string		$id
		 *
		 *	@return 	bool
		 */
		private static function _ownedSection( array &$appData, string $type, string $id ): bool {

			foreach( self::_unitElements( $appData, true ) as $unitElements )
				if( $unitElements['type'] === $type && isset( self::_sectionIds( $unitElements['data'] )[$id] ) === true )
					return true;

			return false;
		}
	}

}
