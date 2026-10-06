<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Maintenance	see _nino/Nino/Modules/Modules.php for the
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
	 *	Maintenance					One switch that answers every public page with a 503
	 *											instead of rendering it - a kernel module, not a feature,
	 *											because it changes what every request answers rather than
	 *											adding one. The one entry of
	 *											\Nino\Install\Setup::TOOL_MODULES - Design and Templates stood
	 *											beside it there until both became catalogue features: the
	 *											wizard adds its class whenever the class exists, there is no
	 *											picker checkbox for it and no install/ unit the wizard
	 *											applies - see install/ beside this file for why that
	 *											directory still ships content, and _body() below for the
	 *											fallback that makes the switch work without it.
	 *
	 *											init() registers callbackResponse() on /nino/http/response
	 *											at priority 1 - before Modules\Jstext (5) and Modules\Cache
	 *											(9), and before a route-specific handler such as
	 *											Modules\Form's POST /.form ever runs (see Http::response()):
	 *											the actual work is _prepare(), which shapes $request into
	 *											the 503 answer and is what a test calls directly; the
	 *											registered callback additionally ends the request right
	 *											there with \Nino\Http::output() when _prepare() applied,
	 *											same reasoning as Modules\Cache's own hit path - a route-
	 *											specific handler still to come must not process a write
	 *											(send mail, record a submission) while the site is down.
	 *
	 *											The page a visitor gets is the project's own
	 *											/templates/page-maintenance.tpl where there is one - in the
	 *											visitor's language, like every page of the site - and
	 *											otherwise the module's own, templates/page-maintenance.
	 *											<locale>.tpl, in the site's native language: the visitor has
	 *											chosen nothing yet and the site has not been designed for
	 *											them, so the language the operator writes in is the one
	 *											thing known about it. The constant below is only what is
	 *											left when neither template can be read.
	 *
	 *											A route that carries 'maintenance' => false is answered as
	 *											usual - the login, and the pages of Modules\Legal.
	 *
	 *											A signed-in account sees the site as it is, with a banner
	 *											saying it is down for everybody else: callbackOutput() on
	 *											/nino/http/output, the one hook that has the finished page.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Maintenance {

		// The workbench's own routes and everything below them - the one
		// carve-out. Everything else, including a module endpoint such as
		// /.form, answers the maintenance page too: the site is down for a
		// visitor, contact form included
		private const string ADMIN_PREFIX = '/_admin';

		// Where the module's own templates and words are, and what a locale
		// has to look like before it is made part of a path to one
		private const string DIR				= '/_nino/Nino/Modules/Maintenance';
		private const string LOCALE_FORMAT	= '/^[a-z]{2}_[A-Z]{2}$/';
		private const string LOCALE_FALLBACK	= 'en_US';

		private const int DEFAULT_RETRY = 3600;

		// Used only where the project defines neither
		// /module/maintenance/page/title nor /module/maintenance/page/text
		// anywhere (no text file, no runtime fill) and the module has no
		// default in the language of the page (install/text/<locale>.php) -
		// the page always has something to show. English only, same reasoning
		// as the recovery page and the setup wizard (see AGENTS.md,
		// "Designing an admin frontend")
		private const string DEFAULT_TITLE = 'Under maintenance';
		private const string DEFAULT_TEXT	= 'We will be back shortly.';

		// The link of the banner a signed-in account sees (see
		// callbackOutput()): one line with [[href]] and [[label]], filled
		// last into the banner template's [[link]]
		private static string $linkHtml = ' <a href="[[href]]" style="color:inherit;font-weight:600;text-decoration:underline">[[label]]</a>';

		// The links to the imprint and the privacy policy on the module's own
		// page (see _legal()): one line with [[href]] and [[label]] per page,
		// what stands between two of them, and the paragraph they stand in
		private static array $legalHtml = [
			'link'		=> '<a href="[[href]]">[[label]]</a>',
			'separator'	=> ' &middot; ',
			'list'		=> '<p>[[links]]</p>',
		];

		// What is left when neither of the module's own page templates can be
		// read: a minimal, self-contained page, so the one thing this module
		// must render - even on a project that has nothing else, or a
		// checkout that lost its templates/ - never depends on a file.
		// _body() below fills both placeholders itself and never hands this to
		// the shortcode/fill pipeline, so a value that happens to contain '['
		// cannot reopen a fill of its own
		private const string FALLBACK_BODY = <<<'HTML'
			<!doctype html>
			<html lang="en">
				<head>
					<meta charset="utf-8">
					<meta name="viewport" content="width=device-width, initial-scale=1">
					<title>%1$s</title>
				</head>
				<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:2rem;box-sizing:border-box;font-family:system-ui,sans-serif;text-align:center">
					<main>
						<h1>%1$s</h1>
						<p>%2$s</p>
						%3$s
					</main>
				</body>
			</html>
			HTML;

		/**
		 *	The /_admin screen this module brings along - collected by
		 *	Admin::panels() through Modules::collect(), so it appears in the
		 *	editor exactly while this module is active and vanishes with it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Panel class names
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Maintenance\Admin::class ];
		}

		/**
		 *	Register the response override, and - while the switch is on -
		 *	take the full-page cache out of the loop for the rest of this
		 *	request. Runtime only, never persisted: config.php's own
		 *	/nino/cache/status is untouched, so switching maintenance back
		 *	off leaves the cache exactly as configured
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			if( ( $appData['/nino/maintenance/status'] ?? false ) === true )
				$appData['/nino/cache/status'] = false;

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ], 1 );

			// Priority 9, beside Modules\Cache's own output callback (also 9,
			// and listed before this module, so it runs first). Either order is
			// safe: a signed-in account is never served from the cache or
			// written to it, and the switch turns the cache off - the banner is
			// for exactly them
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/output', [ self::class, 'callbackOutput' ], 9 );
		}

		/**
		 *	The registered callback. Delegates the actual decision to
		 *	_prepare(), then ends the request the moment it applied - a
		 *	route-specific handler for this exact route is still to come
		 *	(see Http::response()) and must not run while the site answers
		 *	maintenance, the same reasoning Modules\Cache's hit path answers
		 *	from inside this same global callback instead of letting the
		 *	request continue.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			if( self::_prepare( $appData, $request ) === false )
				return;

			\Nino\Http::output( $appData, $request );
		}

		/**
		 *	Decide whether this request is answered with the maintenance page
		 *	and, if so, shape $request into that answer - status, headers,
		 *	body. Split out from callbackResponse() so the decision itself is
		 *	testable without the exit that follows it in production.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current request
		 *
		 *	@return 	bool										Whether the maintenance answer was applied
		 */
		public static function _prepare( array &$appData, array &$request ): bool {

			if( ( $appData['/nino/maintenance/status'] ?? false ) !== true )
				return false;

			$uri = (string) ( $request['/nino/http/request']['uri'] ?? '' );

			// The workbench itself must keep working - an operator has to be
			// able to log in and switch this back off
			if( $uri === self::ADMIN_PREFIX || str_starts_with( $uri, self::ADMIN_PREFIX. '/' ) === true )
				return false;

			// A signed-in account sees the site as it is - how an operator
			// checks a change before switching maintenance back off
			if( \Nino\Auth::getCurrentUser( $appData ) !== false )
				return false;

			// A route that says so stays reachable: 'maintenance' => false in
			// its definition, which Http::response() has mixed into the answer
			// by now. The login has to - an operator who is not signed in must
			// be able to sign in, from the workbench's login form and from its
			// re-login dialog, to switch this back off or just to see the site
			// as it is - and so have the imprint and the privacy policy
			// (Modules\Legal), which a visitor is owed whatever the site is
			// doing. Set by code or by config.php, never by a request
			if( ( $request['/nino/http/response']['maintenance'] ?? true ) === false )
				return false;

			$own = is_file( \Nino\Filesystem::path( $appData, '/templates/page-maintenance.tpl' ) );

			if( $own === true )
				// Ending the request from inside this global callback (see
				// callbackResponse()) skips \Nino\Locales::response(), which
				// \Nino\request() would otherwise run right after
				// Http::response() - applied here instead, so a route with its
				// own 'locale' still renders the project's own maintenance page
				// in that language
				\Nino\Locales::response( $appData, $request );
			else {

				// The module's own page is the site's native language, whatever
				// the route says: useLocale(), not setCurrentLocale() - this is
				// nobody's choice, and the visitor's session is left alone
				$native = \Nino\Locales::getNativeLocale( $appData );

				if( preg_match( self::LOCALE_FORMAT, $native ) === 1 && \Nino\Locales::verifyLocale( $appData, $native ) === true ) {
					\Nino\Locales::useLocale( $appData, $native );
					$request['/nino/http/response']['locale'] = $native;
				}
			}

			// The same is true of the request fills \Nino\request() adds after
			// its response round - a project's own page-maintenance.tpl wears
			// the site's header, and that header names them. The same fills,
			// from the same place, with nobody signed in
			\Nino\Html::addFills( $appData, \Nino\Html::requestFills( $request, '' ), '*' );

			$request['/nino/http/response']['statusCode'] = 503;
			$request['/nino/http/response']['header']['Retry-After'] = (string) self::retry( $appData );
			$request['/nino/http/response']['header']['Cache-Control'] = 'no-store';
			$request['/nino/http/response']['body'] = self::_body( $appData, $own );

			return true;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int											Configured Retry-After in seconds, one hour if unset or nonsense
		 */
		public static function retry( array &$appData ): int {

			$retry = $appData['/nino/maintenance/retry'] ?? null;

			return ( is_int( $retry ) === true && $retry > 0 ) ? $retry : self::DEFAULT_RETRY;
		}

		/**
		 *	The maintenance page's body: the project's own
		 *	/templates/page-maintenance.tpl when one exists - installed by
		 *	hand, or by a project that copied this module's install/ content -
		 *	rendered through the normal pipeline so it wears the site's
		 *	design; otherwise the module's own template for the language of
		 *	the page (templates/page-maintenance.<locale>.tpl, en_US where
		 *	there is none for it) with the same two fills
		 *	(/module/maintenance/page/title, /module/maintenance/page/text) and
		 *	the links to the imprint and the privacy policy (see _legal())
		 *	resolved directly rather than through the shortcode pipeline, and
		 *	this module's default in that language (install/text/<locale>.php)
		 *	or a hardcoded one
		 *	wherever the project defines neither - so the switch always
		 *	answers something, on a project that never applied any install
		 *	content at all. The constant is what is left when no template can
		 *	be read.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$own					Whether the project has a page-maintenance.tpl
		 *
		 *	@return 	string
		 */
		private static function _body( array &$appData, bool $own ): string {

			if( $own === true )
				return \Nino\Html::renderHtml( $appData, '[template /templates/page-maintenance]' );

			$locale = self::_locale( $appData );
			$fills 	= \Nino\Html::getFills( $appData );
			$stock 	= \Nino\Filesystem::getFileContent( $appData, self::DIR. '/install/text/'. $locale. '.php', [] );
			$stock 	= is_array( $stock ) === true ? $stock : [];

			$title = $fills['[[/module/maintenance/page/title]]'] ?? null;
			$text	 = $fills['[[/module/maintenance/page/text]]'] ?? null;

			if( is_string( $title ) === false || $title === '' )
				$title = $stock['[[/module/maintenance/page/title]]'] ?? null;

			if( is_string( $text ) === false || $text === '' )
				$text = $stock['[[/module/maintenance/page/text]]'] ?? null;

			$title = is_string( $title ) === true && $title !== '' ? $title : self::DEFAULT_TITLE;
			$text	 = is_string( $text ) === true && $text !== '' ? $text : self::DEFAULT_TEXT;

			$title = htmlspecialchars( $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			$text	 = nl2br( htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
			$legal = self::_legal( $appData );

			foreach( array_unique( [ $locale, self::LOCALE_FALLBACK ] ) as $name ) {

				$template = \Nino\Filesystem::getFileContent( $appData, self::DIR. '/templates/page-maintenance.'. $name. '.tpl', '' );

				if( is_string( $template ) === true && $template !== '' )
					return strtr( $template, [ '[[title]]' => $title, '[[text]]' => $text, '[[legal]]' => $legal ] );
			}

			return sprintf( self::FALLBACK_BODY, $title, $text, $legal );
		}

		/**
		 *	The links to the imprint and the privacy policy for the module's own
		 *	page, in the language of the page: they stay reachable while the
		 *	site is down (see _prepare()), so a visitor can be sent to them.
		 *	Nothing where Modules\Legal is not active or a page has no route
		 *	or no name in this language - an empty string, not a dead link
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									A paragraph, or ''
		 */
		private static function _legal( array &$appData ): string {

			$links = [];

			foreach( \Nino\Modules\Legal::PAGES as $page => $definition ) {

				$url	= \Nino\Modules\Legal::url( $appData, $page );
				$name	= \Nino\Html::renderTextfill( $appData, '/_nino/webpage'. $definition['uri']. '/name' );

				if( $url === '' || $name === '' )
					continue;

				$links[] = strtr( self::$legalHtml['link'], [
					'[[href]]'	=> htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
					'[[label]]'	=> htmlspecialchars( $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
				] );
			}

			return $links === [] ? '' : str_replace( '[[links]]', implode( self::$legalHtml['separator'], $links ), self::$legalHtml['list'] );
		}

		/**
		 *	The language the module's own page is written in: the current one
		 *	(the native one, where _prepare() could set it), and only where it
		 *	has the shape of a locale id - it becomes part of a path
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		private static function _locale( array &$appData ): string {

			$locale = \Nino\Locales::getCurrentLocale( $appData );

			return preg_match( self::LOCALE_FORMAT, $locale ) === 1 ? $locale : self::LOCALE_FALLBACK;
		}

		/**
		 *	The banner a signed-in account sees while the site shows
		 *	everybody else the maintenance page: one line at the top of every
		 *	public page it opens, in the normal flow so it covers no sticky
		 *	header of the site, with a link to the switch for an account that
		 *	may use it.
		 *
		 *	On /nino/http/output because that is where the finished page is -
		 *	and a callback there runs on the bytes, after every fill has been
		 *	replaced, so the link is built from the project's directory
		 *	rather than written as a fill. Only a page is touched: a string
		 *	body of text/html that has a <body>. A json answer is an array
		 *	here, a feed or a plain text route says its type, and a fragment
		 *	has no body to put anything at the top of.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Finished request
		 *
		 *	@return 	void
		 */
		public static function callbackOutput( array &$appData, array &$request ): void {

			if( ( $appData['/nino/maintenance/status'] ?? false ) !== true || \Nino\Auth::getCurrentUser( $appData ) === false )
				return;

			$uri 		= (string) ( $request['/nino/http/request']['uri'] ?? '' );
			$body 	= $request['/nino/http/response']['body'] ?? null;
			$type 	= $request['/nino/http/response']['header']['Content-Type'] ?? 'text/html';

			if( $uri === self::ADMIN_PREFIX || str_starts_with( $uri, self::ADMIN_PREFIX. '/' ) === true )
				return;

			if( is_string( $body ) === false || is_string( $type ) === false || stripos( ltrim( $type ), 'text/html' ) !== 0 || stripos( $body, '</body>' ) === false )
				return;

			// A '>' inside a quoted attribute value is no end of the tag:
			// <body data-x="a>b"> opens where its last '>' is
			if( preg_match( '/<body\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i', $body, $open, PREG_OFFSET_CAPTURE ) !== 1 )
				return;

			$template = \Nino\Filesystem::getFileContent( $appData, self::DIR. '/templates/banner.tpl', '' );

			if( is_string( $template ) === false || $template === '' )
				return;

			// The module's own words for the page's language, English where it
			// has none - the files the panel reads, so both stay one text
			$words = [];

			foreach( array_unique( [ self::_locale( $appData ), self::LOCALE_FALLBACK ] ) as $name ) {

				$words = \Nino\Filesystem::getFileContent( $appData, self::DIR. '/text/'. $name. '.php', [] );
				$words = is_array( $words ) === true ? $words : [];

				if( isset( $words['[[/_admin/maintenance/banner/text]]'] ) === true )
					break;
			}

			$text = $words['[[/_admin/maintenance/banner/text]]'] ?? '';

			if( is_string( $text ) === false || $text === '' )
				return;

			$link = '';

			if( \Nino\Auth::checkPermission( $appData, \Nino\Modules\Maintenance\Admin::MANAGE_PERM ) === true )
				$link = strtr( self::$linkHtml, [
					'[[href]]'	=> htmlspecialchars( \Nino\Filesystem::getDir( $appData ). '/_admin#maintenance', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
					'[[label]]'	=> htmlspecialchars( (string) ( $words['[[/_admin/maintenance/banner/link]]'] ?? '' ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
				] );

			$banner = strtr( trim( $template ), [
				'[[text]]'	=> htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
				'[[link]]'	=> $link,
			] );

			$at = $open[0][1] + strlen( $open[0][0] );

			$request['/nino/http/response']['body'] = substr( $body, 0, $at ). $banner. substr( $body, $at );
		}
	}

}
