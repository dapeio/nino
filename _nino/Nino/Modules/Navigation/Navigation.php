<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Navigation				see _nino/Nino/Modules/Modules.php for the
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
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
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

			/*	Two sources, and they are not read the same way. A generated line
				carries a page's name, which is editor content: everything after
				the first ':' is that name, colons and all, and it is escaped on
				the way into the page. A hand-written line is the template
				author's own, so it keeps the third field it has always had -
				attributes for the tag - and keeps being written verbatim	*/
			$lines = [];

			foreach( ( $nav !== '' ) ? self::routeLines( $appData, (string) $nav ) : [] as $line )
				$lines[] = [ 'line' => $line, 'authored' => false ];

			if( $content !== '' )
				foreach( explode( PHP_EOL, $content ) as $line )
					$lines[] = [ 'line' => $line, 'authored' => true ];

			if( count( $lines ) === 0 )
				return '';

			foreach( $lines as $entry ) {

				$line 		= $entry['line'];
				$authored	= $entry['authored'];

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

				// A generated line is split once: a page called "Angebot: Sommer"
				// used to end at the second colon, and the rest of its own name
				// was written into the <a> tag as attributes - a name typed in
				// the Text panel deciding what the markup says
				$element		= explode( ':', $line, $authored === true ? 3 : 2 );
				$uri				= \Nino\Http::getRequest( $appData )['/nino/http/request']['uri'] ?? '/';
				$attributes = $element[2] ?? '';
				$element[0]	= trim( $element[0] );
				$title			= $element[1] ?? '';

				// And a page's name is text: a '<' an editor typed is a '<' on
				// the page, the same rule every other place their words reach one
				// follows. A hand-written line stays what its author wrote
				if( $authored === false ) {
					$title			= htmlspecialchars( $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
					$element[0]	= htmlspecialchars( $element[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
					// ...and what a shortcode returns is rendered again, fills and
					// shortcodes included, so a '[' is an entity on the way out - the
					// same swap every other place an editor's words reach a page makes
					$title			= str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], $title );
					$element[0]	= str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], $element[0] );
				}

				$attributes	.= ( $uri === $element[0] ) ? ' class="nino-is-active"' : '';
				$lis				.= str_replace( [ '[[uri]]', '[[attributes]]', '[[title]]' ], [ $element[0]	, $attributes, $title ], self::$html['li'] );
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
