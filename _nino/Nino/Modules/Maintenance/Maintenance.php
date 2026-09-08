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
	 *											adding one. Listed in /nino/modules the same way Design and
	 *											Templates are (see \Nino\Install\Setup::TOOL_MODULES): the
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

		private const int DEFAULT_RETRY = 3600;

		// Used only where the project defines neither /maintenance/title nor
		// /maintenance/text anywhere (no text file, no runtime fill) - the
		// built-in fallback page always has something to show. English only,
		// same reasoning as the recovery page and the setup wizard (see
		// AGENTS.md, "Designing an admin frontend")
		private const string DEFAULT_TITLE = 'Under maintenance';
		private const string DEFAULT_TEXT	= 'We will be back shortly.';

		// A minimal, self-contained page: no [template ...] of its own, so it
		// never depends on the site's templates existing at all - the one
		// thing this module must render even on a project that has nothing
		// else. _body() below fills both placeholders itself and never hands
		// this to the shortcode/fill pipeline, so a value that happens to
		// contain '[[' cannot reopen a fill of its own
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

			// Ending the request from inside this global callback (see
			// callbackResponse()) skips \Nino\Locales::response(), which
			// \Nino\request() would otherwise run right after Http::response() -
			// applied here instead, so a route with its own 'locale' still
			// renders the maintenance page in that language
			\Nino\Locales::response( $appData, $request );

			// The same is true of the request fills \Nino\request() adds after
			// its response round - a project's own page-maintenance.tpl wears
			// the site's header, and that header names them
			\Nino\Html::addFills( $appData, [
				'[[/nino/http/request/uri]]'				=> (string) ( $request['/nino/http/request']['uri'] ?? '' ),
				'[[/nino/http/response/uri]]'				=> (string) ( $request['/nino/http/response']['uri'] ?? '' ),
				'[[/nino/http/response/uri/clean]]'	=> str_replace( '/', '_', (string) ( $request['/nino/http/response']['uri'] ?? '' ) ),
				'[[/nino/http/response/locale]]'		=> (string) ( $request['/nino/http/response']['locale'] ?? '' ),
				'[[/nino/auth/user]]'								=> '',
			], '*' );

			$request['/nino/http/response']['statusCode'] = 503;
			$request['/nino/http/response']['header']['Retry-After'] = (string) self::retry( $appData );
			$request['/nino/http/response']['header']['Cache-Control'] = 'no-store';
			$request['/nino/http/response']['body'] = self::_body( $appData );

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
		 *	design; otherwise the built-in fallback above, with the same two
		 *	fills (/maintenance/title, /maintenance/text) resolved directly
		 *	rather than through the shortcode pipeline, and a hardcoded
		 *	default wherever the project defines neither - so the switch
		 *	always answers something, on a project that never applied any
		 *	install content at all.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		private static function _body( array &$appData ): string {

			if( is_file( \Nino\Filesystem::path( $appData, '/templates/page-maintenance.tpl' ) ) === true )
				return \Nino\Html::renderHtml( $appData, '[template /templates/page-maintenance]' );

			$fills = \Nino\Html::getFills( $appData );

			$title = $fills['[[/maintenance/title]]'] ?? null;
			$text	 = $fills['[[/maintenance/text]]'] ?? null;

			$title = is_string( $title ) === true && $title !== '' ? $title : self::DEFAULT_TITLE;
			$text	 = is_string( $text ) === true && $text !== '' ? $text : self::DEFAULT_TEXT;

			return sprintf(
				self::FALLBACK_BODY,
				htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ),
				nl2br( htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) )
			);
		}
	}

}
