<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Navigation				see Nino.php's Modules section for the
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
	 *	Navigation					A quick & dirty nav renderer
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Navigation {

		public static
			$html = [
				'li'					=> '<li><a href="[[uri]]"[[attributes]]>[[title]]</a></li>',
				'nav-burger'	=> '<div class="nino-nav-wrap nino-nav-fullscreen nino-nav-burger [[class]]" id="[[id]]"><label><input type="checkbox"><div class="nino-nav-bg"></div><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><rect y="3" width="24" height="3"/><rect y="10" width="24" height="3"/><rect y="17" width="24" height="3"/></svg><div class="nino-nav-content">[[content]]</div></label></div>',
				'nav-regular'	=> '<div class="nino-nav-wrap nino-nav-fullscreen nino-nav-regular [[class]]" id="[[id]]"><div class="nino-nav-content">[[content]]</div></div>',
				'ul'					=> '<ul>[[content]]</ul>',
				'div'					=> '<div>[[content]]</div>',
			];

		/**
		 *	The /_admin screen this module brings along - collected by
		 *	Admin::panels() through Modules::collect(), so it appears in the
		 *	dev area exactly while this module is active and vanishes with it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Panel class names
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Navigation\Admin::class ];
		}

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'navigation', [ self::class, 'doShortcode' ] );
		}

		/**
		 *	Replace shortcode
		 *
		 *	Two sources, either or both: nav="&lt;key&gt;" collects every route
		 *	that lists itself under that key (see routeLines()), and whatever
		 *	stands between the tags is appended after it, unchanged - a
		 *	hand-written menu keeps working exactly as before, and a generated
		 *	one can still be extended by hand (an external link, a separator).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$content	= $args['content'] ?? '';
			$callback	= $args['callback'] ?? '';
			$id				= $args['id'] ?? '';
			$class		= $args['class'] ?? '';
			$nav			= $args['nav'] ?? '';
			$html 		= '';

			// Render list elements
			$lis	= '';

			$lines = ( $nav !== '' ) ? self::routeLines( $appData, (string) $nav ) : [];

			if( $content !== '' )
				$lines = array_merge( $lines, explode( PHP_EOL, $content ) );

			if( count( $lines ) === 0 )
				return '';

			foreach( $lines as $line ) {

				// Blank, not just empty: with the list coming from the routes,
				// what stands between the tags is usually nothing but the
				// template's own indentation - which used to end up as a stray
				// empty <div> in the rendered menu
				if( trim( $line ) === '' )
					continue;

				if( strpos( $line, ':' ) === false ) {
					$html .= str_replace( '[[content]]', $line, self::$html['div'] );
					continue;
				}

				$element		= explode( ':', $line );
				$uri				= \Nino\Http::getRequest( $appData )['/nino/http/request']['uri'] ?? '/';
				$attributes = $element[2] ?? '';
				$element[0]	= trim( $element[0] );

				$attributes	.= ( $uri === $element[0] ) ? ' class="nino-is-active"' : '';
				$lis				.= str_replace( [ '[[uri]]', '[[attributes]]', '[[title]]' ], [ $element[0]	, $attributes, $element[1] ], self::$html['li'] );
			}
			$html .= str_replace( '[[content]]', $lis, self::$html['ul'] );


			$template = ( in_array( 'burger', $args ) === true ) ? 'burger' : 'regular';
			$result = str_replace( [ '[[content]]', '[[id]]', '[[class]]' ], [ $html, $id, $class ], self::$html['nav-'. $template] );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			return $result;
		}

		/**
		 *	Every route that puts itself into one navigation, as the same
		 *	"&lt;uri&gt;:&lt;title&gt;" lines a hand-written menu uses.
		 *
		 *	Membership lives on the route itself - 'navs' =&gt; [ 'main' =&gt; 5,
		 *	'footer' =&gt; 3 ] - rather than in a generated textfill some tool
		 *	owns and overwrites. A route added by hand in config.php is
		 *	therefore a menu entry like any other, with no tool involved, and
		 *	nothing here ever writes: the menu is computed per request, so it
		 *	cannot go stale against the routes it describes.
		 *
		 *	The value is a priority, same rule Callbacks::registerCallback()
		 *	uses - lower runs first, 5 is the middle - except it is a plain
		 *	int rather than a fixed bucket. Equal priorities keep the order the
		 *	routes stand in, which is the page order the setup wizard and the Routes panel
		 *	write (see their apiApply()/apiMove()), so reordering pages there
		 *	reorders every menu they appear in without touching a priority.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$nav					Navigation key, eg. "main"
		 *
		 *	@return 	array										"&lt;uri&gt;:&lt;title&gt;" lines, in menu order
		 */
		public static function routeLines( array &$appData, string $nav ): array {

			$locale 	= \Nino\Locales::getCurrentLocale( $appData );
			$buckets 	= [];

			foreach( ( $appData['/nino/http/routes'] ?? [] ) as $routeKey => $route ) {

				// Only a page a visitor can actually open: a POST endpoint or a
				// module's own runtime route is not a menu entry
				if( str_starts_with( $routeKey, 'GET://' ) === false )
					continue;

				if( isset( $route['navs'][$nav] ) === false )
					continue;

				// Same rule Http::findRouteUri() applies: a locale-gated route
				// (eg. legal content whose slug differs by language) only exists
				// for its own locale, and only belongs in that locale's menu
				if( isset( $route['locale'] ) === true && $route['locale'] !== $locale )
					continue;

				// The page's own name in the current locale - the very key
				// the setup wizard and the Routes panel already write per webpage. A route
				// nobody named has nothing to show in a menu, so it stays out
				// rather than appearing as a raw uri or an empty link
				$title = \Nino\Html::renderTextfill( $appData, '/webpage'. ( $route['uri'] ?? '' ). '/name' );

				if( $title === '' )
					continue;

				// 'GET://' -> '/', 'GET://kontakt' -> '/kontakt' - the same
				// derivation Locales::callbackResponse() makes for its redirect,
				// and the same uri space the request carries, so the "active"
				// match in doShortcode() keeps comparing like for like
				$buckets[ (int) $route['navs'][$nav] ][] = substr( $routeKey, strlen( 'GET:/' ) ). ':'. $title;
			}

			ksort( $buckets );

			return count( $buckets ) === 0 ? [] : array_merge( ...array_values( $buckets ) );
		}
	}

}
