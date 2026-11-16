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
	 *	Navigation					A quick & dirty nav renderer - routeLines() fires
	 *											'/nino/navigation/lines' with the menu's key and
	 *											its lines, so that a module can change which
	 *											entries a menu shows
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Navigation {

		public static
			$html = [
				'li'					=> '<li><a href="[[uri]]"[[attributes]]>[[title]]</a></li>',
				'ul'					=> '<ul[[id]][[class]]>[[content]]</ul>',
				'div'					=> '<div>[[content]]</div>',
			];

		// What marks the entry of the page that is shown
		private const string CURRENT = ' aria-current="page"';

		/**
		 *	The /_admin screen this module brings along - collected by
		 *	Admin::panels() through Modules::collect(), so it appears in the
		 *	workbench's rail exactly while this module is active and vanishes with it
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
			\Nino\Html::addShortcode( $appData, 'navigation', [ self::class, 'doShortcode' ], null );
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
		 *	What it writes is a bare list - <ul><li><a> - with no class of its own: the
		 *	look of a menu is the part of a feature (the header and the footer of Basis
		 *	draw theirs from entries()). The id and the class the call names are the
		 *	list's, and the entry of the page that is shown is marked
		 *	aria-current="page".
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

			/*	Two sources, and they are not read the same way. A generated entry
				carries a page's name, which is editor content: it is the text of
				entries(), and it is escaped on the way into the page. A hand-written
				line is the template author's own, so it keeps the third field it
				has always had - attributes for the tag - and keeps being written
				verbatim	*/
			$items = [];

			foreach( ( $nav !== '' ) ? self::entries( $appData, (string) $nav ) : [] as $entry )
				$items[] = [ 'entry' => $entry ];

			if( $content !== '' )
				foreach( explode( PHP_EOL, $content ) as $line )
					$items[] = [ 'line' => $line ];

			if( count( $items ) === 0 )
				return '';

			// An entry names its page the way the routes do, from the project's root; the page is reached
			// under the directory the project is served from. A hand-written line that already names
			// somewhere else (a full url, a fragment) is written as it stands
			$dir = rtrim( \Nino\Filesystem::getDir( $appData ), '/' );

			// A line the template's author wrote is html+ of the template, drawn where the menu stands
			$context = \Nino\Html::context( $appData );
			$draw = static function( string $source ) use ( &$appData, $context ): string {
				return \Nino\Html::renderSource( $appData, $source, $context );
			};

			// What an editor's words are written as: text, with every bracket an entity, the same swap every other
			// place their words reach a page makes
			$text = static function( string $value ): string {
				return str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
			};

			foreach( $items as $item ) {

				// A generated entry: its address and its title are the text they are
				if( isset( $item['entry'] ) === true ) {

					$entry = $item['entry'];

					// An entry without an address is a line a callback left behind: text, escaped the way a title is
					if( $entry['href'] === '' )
						$html .= str_replace( '[[content]]', $text( $entry['title'] ), self::$html['div'] );
					else
						$lis .= str_replace( [ '[[uri]]', '[[attributes]]', '[[title]]' ], [ $text( $entry['href'] ), $entry['current'] === true ? self::CURRENT : '', $text( $entry['title'] ) ], self::$html['li'] );

					continue;
				}

				$line = $item['line'];

				// Blank, not just empty: with the list coming from the routes,
				// what stands between the tags is usually nothing but the
				// template's own indentation - which used to end up as a stray
				// empty <div> in the rendered menu
				if( trim( $line ) === '' )
					continue;

				if( strpos( $line, ':' ) === false ) {
					$html .= str_replace( '[[content]]', $draw( $line ), self::$html['div'] );
					continue;
				}

				// A hand-written line stays what its author wrote - drawn, each of its three parts as the source it is
				$element		= explode( ':', $line, 3 );
				$uri				= \Nino\Http::getRequest( $appData )['/nino/http/request']['uri'] ?? '/';
				$element[0]	= $draw( trim( $element[0] ) );
				$title			= $draw( $element[1] ?? '' );
				$attributes	= $draw( $element[2] ?? '' );

				$attributes	.= ( $uri === $element[0] ) ? self::CURRENT : '';
				$href				= self::_address( $dir, $element[0] );
				$lis				.= str_replace( [ '[[uri]]', '[[attributes]]', '[[title]]' ], [ $href, $attributes, $title ], self::$html['li'] );
			}

			// The id and the class are the call's own words, written as an attribute
			$attribute = static fn( string $name, string $value ): string => $value === '' ? '' : ' '. $name. '="'. htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ). '"';
			$result = str_replace( [ '[[id]]', '[[class]]', '[[content]]' ], [ $attribute( 'id', (string) $id ), $attribute( 'class', (string) $class ), $lis ], self::$html['ul'] );
			// A list with no entry is no list: lines with no address are drawn and nothing is drawn around them
			$result = $html. ( $lis === '' ? '' : $result );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			return $result;
		}

		/**
		 *	The entries of one navigation as data, for what draws a menu in a markup of its own: the routes that
		 *	put themselves into it (routeLines(), so '/nino/navigation/lines' has had its say), in menu order. The
		 *	hand-written lines between the tags of [navigation] are not part of it.
		 *
		 *	The values are raw - text, not markup: the caller escapes what it writes, for the place it writes it
		 *	to, and a title is never read as a call or a fill. The title is the page's name as an editor typed it
		 *	(the entities it is stored with are decoded), whatever it holds. [navigation] draws its generated
		 *	entries from this function and escapes them, with every bracket an entity.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$nav					Navigation key, eg. "main"
		 *
		 *	@return 	array										A list of [ 'href' => the address as a link takes it: the path from the project's
		 *																	root with the directory the project is served from in front of it, '' for a line a
		 *																	callback left behind that has none, 'title' => the text, 'current' => whether it is the
		 *																	page that is shown ]
		 */
		public static function entries( array &$appData, string $nav ): array {

			$dir		= rtrim( \Nino\Filesystem::getDir( $appData ), '/' );
			$shown	= \Nino\Http::getRequest( $appData )['/nino/http/request']['uri'] ?? '/';
			$entries	= [];

			foreach( self::routeLines( $appData, $nav ) as $line ) {

				// Blank, as a hand-written line is
				if( trim( $line ) === '' )
					continue;

				// A line is split once: a page called "Angebot: Sommer" ends at the first colon, and the rest of
				// its own name is its name
				if( strpos( $line, ':' ) === false ) {
					$entries[] = [ 'href' => '', 'title' => $line, 'current' => false ];
					continue;
				}

				[ $address, $title ] = explode( ':', $line, 2 );

				$address = trim( $address );

				$entries[] = [ 'href' => self::_address( $dir, $address ), 'title' => $title, 'current' => $shown === $address ];
			}

			return $entries;
		}

		/**
		 *	The address of a link: a path from the project's root is reached under the directory the project is
		 *	served from, anything else - a full url, a fragment, '//host' - is as it is written
		 *
		 *	@param		string		$dir					The directory, without a slash at the end
		 *	@param		string		$address
		 *
		 *	@return 	string
		 */
		private static function _address( string $dir, string $address ): string {
			return ( str_starts_with( $address, '/' ) === true && str_starts_with( $address, '//' ) === false ) ? $dir. $address : $address;
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
		 *	A route that exists only at runtime - a feature's /blog, registered
		 *	in its init() - has no entry in config.php to carry a 'navs' of its
		 *	own. Its memberships are the key '/nino/html/navroutes', the same
		 *	shape one level up, by the page's Element-URI - the route's own
		 *	'uri' - rather than by route: [ '/blog' =&gt; [ 'main' =&gt; 3 ] ].
		 *	A page that has a route per language (Modules\Legal's imprint) is
		 *	one page, and stays in the menu when a path changes or a language
		 *	joins. Every GET route with that 'uri' is a member for it, a route
		 *	on a wildcard ('/blog/*') never is. It is read only for a route
		 *	that is live right now, so a feature switched off takes its menu
		 *	entry with it, and a membership the route's own 'navs' states for
		 *	the same menu wins. A page is in a menu once: where a route of the
		 *	current language and one without a language share an Element-URI,
		 *	the one of the current language is the entry. Malformed entries are
		 *	skipped.
		 *
		 *	The value is a priority, same rule Callbacks::registerCallback()
		 *	uses - lower runs first, 5 is the middle - except it is a plain
		 *	int rather than a fixed bucket. Equal priorities keep the order the
		 *	routes stand in, which is the page order the setup wizard and the Routes panel
		 *	write (see their apiApply()/apiMove()), so reordering pages there
		 *	reorders every menu they appear in without touching a priority.
		 *
		 *	Once the lines are collected and ordered, '/nino/navigation/lines' is
		 *	fired - once per call, for a menu without lines too - with
		 *	[ 'nav' =&gt; the menu's key, 'lines' =&gt; the lines above ]. It is how a
		 *	module takes a page out of a menu for the visitor at hand, or adds
		 *	one, without a shortcode of its own: a callback changes 'lines' in
		 *	place, or returns an array that replaces the argument. What comes back
		 *	is the strings of 'lines' in their order; a callback that leaves
		 *	anything else behind - or removes 'lines' - ends in no lines at all.
		 *	The lines a template's author wrote between the tags are not part of
		 *	it: doShortcode() appends them afterwards.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$nav					Navigation key, eg. "main"
		 *
		 *	@return 	array										"&lt;uri&gt;:&lt;title&gt;" lines, in menu order
		 */
		public static function routeLines( array &$appData, string $nav ): array {

			$locale 	= \Nino\Locales::getCurrentLocale( $appData );
			$buckets 	= [];
			$entries 	= [];
			$ofLocale = [];

			foreach( ( $appData['/nino/http/routes'] ?? [] ) as $routeKey => $route ) {

				// Only a page a visitor can actually open: a POST endpoint is not a
				// menu entry
				if( str_starts_with( $routeKey, 'GET://' ) === false )
					continue;

				// The route's own membership first, then the one its page keeps
				// beside the routes (see above)
				$prio = $route['navs'][$nav] ?? self::_runtimePriority( $appData, (string) $routeKey, (string) ( $route['uri'] ?? '' ), $nav );

				if( $prio === null )
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
				// ...as text: it is stored with its quotes and brackets as entities
				// (\Nino\Text::sanitizeValue()), so it is decoded here and escaped
				// once, where it is drawn, as Components::value() does for a plain text
				$title = html_entity_decode( \Nino\Html::renderTextfill( $appData, '/_nino/webpage'. ( $route['uri'] ?? '' ). '/name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				if( $title === '' )
					continue;

				// 'GET://' -> '/', 'GET://kontakt' -> '/kontakt' - the same
				// derivation Locales::switchFromQuery() makes for its redirect,
				// and the same uri space the request carries, so the "active"
				// match in doShortcode() keeps comparing like for like
				$entries[] = [
					'prio' 	=> (int) $prio,
					'uri' 	=> (string) ( $route['uri'] ?? '' ),
					'bound' => isset( $route['locale'] ),
					'line' 	=> substr( $routeKey, strlen( 'GET:/' ) ). ':'. $title,
				];

				if( isset( $route['locale'] ) === true )
					$ofLocale[ (string) ( $route['uri'] ?? '' ) ] = true;
			}

			foreach( $entries as $entry )
				if( $entry['bound'] === true || $entry['uri'] === '' || isset( $ofLocale[ $entry['uri'] ] ) === false )
					$buckets[ $entry['prio'] ][] = $entry['line'];

			ksort( $buckets );

			$args = [ 'nav' => $nav, 'lines' => count( $buckets ) === 0 ? [] : array_merge( ...array_values( $buckets ) ) ];

			\Nino\Callbacks::doCallbacks( $appData, '/nino/navigation/lines', $args );

			// What a callback leaves behind is read as untrusted: the lines go
			// on to doShortcode(), which splits and prints strings only
			$lines = is_array( $args ) === true && is_array( $args['lines'] ?? null ) === true ? $args['lines'] : [];

			return array_values( array_filter( $lines, 'is_string' ) );
		}

		/**
		 *	The priority a page holds in one navigation as a runtime-only
		 *	membership, from '/nino/html/navroutes' - null when there is none,
		 *	when the value is not a whole number, or for a route on a
		 *	wildcard, which no link can point at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$routeKey			Eg. 'GET://blog'
		 *	@param		string		$uri					The route's Element-URI, eg. '/blog'
		 *	@param		string		$nav					Navigation key, eg. "main"
		 *
		 *	@return 	int|null
		 */
		private static function _runtimePriority( array &$appData, string $routeKey, string $uri, string $nav ): ?int {

			if( $uri === '' || str_ends_with( $routeKey, '/*' ) === true )
				return null;

			$prio = $appData['/nino/html/navroutes'][$uri][$nav] ?? null;

			return is_int( $prio ) === true ? $prio : null;
		}
	}

}
