<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Feature							The helpers a feature calls: templates, flags, a policy, a counter, a slug
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Feature						What the code of a feature does for itself, once. A feature
	 *										is a directory that uses nothing outside \Nino\*'s public
	 *										API and its own files, so a routine that several of them
	 *										need - the template of the markup, the class attribute of a
	 *										component, the Content-Security-Policy of a response, a
	 *										counter per client - was copied into each, and a fix reached
	 *										the copy it was made in. These are those routines, as the
	 *										kernel's.
	 *
	 *										Static methods only, and nothing here renders markup:
	 *										template() reads the file a feature keeps its markup in, the
	 *										feature fills it. The docblock of each method is its
	 *										reference.
	 *
	 *										Not to be confused with \Nino\Features, the other side of
	 *										the same word: discovery, manifests, settings and the
	 *										lifecycle of the directories below features/. This class
	 *										is what the code inside one of them calls.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	final class Feature {

		// What a template, a counter and a component are called: a slug. A
		// letter first, then letters, digits and hyphens, so that a name cannot
		// reach another directory or another file - the D, because without it a
		// trailing newline passes
		private const string NAME_PATTERN = '/^[a-z][a-z0-9-]*$/D';

		/*	What slug() makes of the letters ascii does not have. Lower case only,
			since the text is lower-cased first. The script twin, Nino.slug(),
			has to carry the same table, and the two must stay equal: a slug a
			page derives in the browser has to be the one the server derives from
			the same words	*/
		public const array TRANSLITERATIONS = [
			'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
			'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
			'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
			'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
			'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
			'ù' => 'u', 'ú' => 'u', 'û' => 'u',
			'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y', 'ð' => 'd', 'þ' => 'th',
			'ł' => 'l', 'š' => 's', 'ž' => 'z', 'č' => 'c', 'ř' => 'r', 'ě' => 'e',
		];

		/**
		 *	One template of the feature's own: <name>.tpl in its templates
		 *	directory, read through \Nino\Filesystem and ready to be filled with
		 *	str_replace() over its [[tokens]] - the markup of a feature lives in a
		 *	template, php decides what is shown (AGENTS.md, "Markup belongs in a
		 *	template"). The newlines at its end are not part of the markup (an
		 *	editor adds one), every other byte is - a space or a tab in front of
		 *	them too. Nothing is rendered here: no fill, no shortcode - whatever a
		 *	shortcode returns is rendered once more by \Nino\Html anyway.
		 *
		 *	The name is a slug and nothing else, so that it cannot name another
		 *	directory. The directory is the feature's own as a virtual path,
		 *	'/features/Catalog/templates' for features/Catalog/templates/, which
		 *	\Nino\Filesystem resolves against the features directory wherever
		 *	NINO_FEATURES_DIR put it; it is checked for nothing but what
		 *	getFileContent() refuses itself, a '..' and a NUL byte.
		 *
		 *	A template that is missing or empty is a bug of the feature and said
		 *	so, in a warning that names the path. The answer is '' then, and the
		 *	page renders without it instead of ending in a fatal.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$directory		The templates directory of the feature, a virtual path
		 *	@param		string		$name					The template without '.tpl': a slug
		 *
		 *	@return 	string										The markup without the newlines at its end; '' for one that is missing or empty, and for a name that is no slug - a warning each
		 */
		public static function template( array &$appData, string $directory, string $name ): string {

			if( preg_match( self::NAME_PATTERN, $name ) !== 1 ) {
				trigger_error( 'Feature::template(): "'. $name. '" is no template name - a slug of lower case letters, digits and hyphens', E_USER_WARNING );
				return '';
			}

			$path			= $directory. '/'. $name. '.tpl';
			$template	= \Nino\Filesystem::getFileContent( $appData, $path, '' );
			$template	= is_string( $template ) === true ? rtrim( $template, "\n" ) : '';

			if( $template === '' )
				trigger_error( 'Feature::template(): the template '. $path. ' is missing or empty', E_USER_WARNING );

			return $template;
		}

		/**
		 *	The value of the class attribute of a component's markup, from the
		 *	argument every shortcode may carry: '' where there is none, otherwise
		 *	a space and the classes, escaped as \Nino\Modules\Components::escape()
		 *	escapes - a '[' is written as &#91;, so that the next rendering pass
		 *	cannot read it as a fill or a shortcode. The space in front is the
		 *	point: a template spells the attribute with the classes it has itself
		 *	and this behind them,
		 *
		 *		<div class="nino-x[[classes]]">
		 *
		 *	and an argument that is not there leaves nothing behind.
		 *
		 *	The class and nothing else. A title, an alt text, an address or an id
		 *	is not a class and is escaped for what it is.
		 *
		 *	@param		array 		$args					The arguments of the shortcode, or what the renderer of a component was given
		 *
		 *	@return 	string										'' or ' ' and the escaped classes
		 */
		public static function classes( array $args ): string {

			$class = trim( (string) ( $args['class'] ?? '' ) );

			return $class === '' ? '' : ' '. \Nino\Modules\Components::escape( $class );
		}

		/**
		 *	Whether a flag of a shortcode is on. A shortcode without a schema takes
		 *	one two ways: a bare word - [copy block] - is on, and a named one -
		 *	[copy block="no"] - is read by the words a component's bool attribute
		 *	is read by, \Nino\Modules\Components::ON and OFF, case and a space
		 *	around the word ignored. Any other word, and a value that is no
		 *	string, leave it to $default.
		 *
		 *	A component declares a 'bool' attribute instead and is handed '1' or
		 *	'0' (Components::dispatch()); this is for the shortcode a feature
		 *	registers by itself.
		 *
		 *	@param		array 		$args					The arguments of the shortcode
		 *	@param		string		$name					The flag
		 *	@param		bool			$default			Where the argument is absent or says something else
		 *
		 *	@return 	bool
		 */
		public static function flag( array $args, string $name, bool $default = false ): bool {

			foreach( $args as $key => $value )
				if( is_int( $key ) === true && $value === $name )
					return true;

			$raw = $args[$name] ?? null;

			if( is_string( $raw ) === false )
				return $default;

			/*	Trimmed here and not in Components::_normalize(), which reads the
				same words. That one reads what the Builder and the schemas write,
				as it is written, and a space around a word has always sent it to the
				default there; widening it would turn ' yes ' from the default into
				on for every component that exists. This one is new, and reads what
				somebody typed into a template, where a space inside the quotes is a
				slip and not another word	*/
			$word = strtolower( trim( $raw ) );

			if( in_array( $word, \Nino\Modules\Components::ON, true ) === true )
				return true;

			return in_array( $word, \Nino\Modules\Components::OFF, true ) === true ? false : $default;
		}

		/**
		 *	The origin of an address as a Content-Security-Policy source, or ''
		 *	where it cannot safely be one: https only, no credentials, and a host
		 *	that is a plain ascii name - no ip literal (a name whose last label is
		 *	a number or a hex number is one, written in a form php does not call
		 *	one), no '*', no whitespace, no ';' or ',' that would end the
		 *	directive. A non-ascii host would have to be written as punycode; it is
		 *	refused rather than converted. The host is lower-cased, and the port
		 *	is kept, since a source without one means 443 only.
		 *
		 *	@param		string		$url
		 *
		 *	@return 	string										'https://host' or 'https://host:port', or ''
		 */
		public static function origin( string $url ): string {

			$parts = parse_url( $url );

			if( is_array( $parts ) === false || strtolower( (string) ( $parts['scheme'] ?? '' ) ) !== 'https' || isset( $parts['user'] ) === true || isset( $parts['pass'] ) === true )
				return '';

			$host = strtolower( (string) ( $parts['host'] ?? '' ) );

			// The last label of a name is never all digits or a hex number
			// (0x7f000001), and one that is is an ip address written in a form php
			// does not call one. The D: without it '$' also matches before a
			// trailing newline, which would end up in a header
			if( $host === '' || strlen( $host ) > 253 || preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/D', $host ) !== 1
				|| preg_match( '/(?:^|\.)(?:[0-9]+|0x[0-9a-f]*)$/iD', $host ) === 1 )
				return '';

			return 'https://'. $host. ( isset( $parts['port'] ) === true ? ':'. (int) $parts['port'] : '' );
		}

		/**
		 *	Let a page load from one more origin: the origin of $url, added to the
		 *	directive of the Content-Security-Policy of this response by
		 *	\Nino\Http::allowSource(), with everything that says about a directive
		 *	that is absent, closed or already lists it. An address that is no
		 *	origin (origin() answers '') changes nothing.
		 *
		 *	It works where the request is in hand: a callback of '/nino/http/response'
		 *	or of '/nino/http/output', at a priority below 9, where \Nino\Modules\Cache
		 *	stores the policy a hit is served under. A shortcode has no request -
		 *	\Nino\Http::getRequest() hands back the copy that was stored at the
		 *	start - so it collects the addresses it wrote into $appData, and a
		 *	callback of the feature applies them at output; the Embed feature of
		 *	the catalogue is the model. Whether to widen for a request at all is the
		 *	caller's to say: a feature that widens the policy of the pages of the
		 *	site leaves /_admin alone, and one that widens the policy of the
		 *	workbench (the Design feature does, for its fonts) does it on purpose.
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$directive		The directive to widen, eg. 'frame-src'
		 *	@param		string		$url					An address, eg. the src of an embedded frame
		 *
		 *	@return 	bool											Whether the policy changed
		 */
		public static function allowOrigin( array &$request, string $directive, string $url ): bool {

			$origin = self::origin( $url );

			return $origin === '' ? false : \Nino\Http::allowSource( $request, $directive, $origin );
		}

		/**
		 *	Register one try of a client and say whether it is still within budget:
		 *	a counter in a window, the rule \Nino\Mail has always sent by. The
		 *	counter is the file /data/<name>.php, shaped
		 *	[ hash => [ 'tries' => n, 'reset' => unix time ] ], and the first try
		 *	of a client opens its window of $window seconds; a window that has
		 *	run out starts a new one, and every entry of that kind is dropped when
		 *	the file is written next, so it cannot grow without bound.
		 *
		 *	The try is counted whatever the answer: a client past the limit keeps
		 *	counting, and is let in again when its window is over - not when it
		 *	stops. The answer is true for the tries up to $limit, false for those
		 *	after it, and false as well where the file could not be written -
		 *	no reliable count, no budget: an unreliable cap is worse than a closed
		 *	one.
		 *
		 *	The key is hashed, always. It is an address, a mail, a name - what a
		 *	person is - and the file lies where a backup, a log or a stranger with
		 *	a read bug can reach it; sha256 of it is the file's key, and the key
		 *	itself is never written. A backup carries a file below data/ only where
		 *	\Nino\Backup::manifest() names it - by a manifest's 'data' entry or by
		 *	the kernel's own list - so a counter under a name of its own stays out
		 *	of it.
		 *
		 *	A read-modify-write under \Nino\Filesystem::mutate(), as every counter
		 *	must be: two requests at once cannot read the same count. A file that
		 *	holds anything but an array is a file that holds no tries.
		 *
		 *	The name is the file, and /data/ is shared: the kernel keeps its own
		 *	files there (auth-tries, mail-status, catalogue) and so do the features
		 *	(what a manifest lists under 'data'). A counter of such a name would
		 *	find the other's entries and drop them on its next write, as it drops
		 *	whatever is no entry of a counter - the name is the counter's alone.
		 *
		 *	A $limit of 0 or less is no limit: true, and the file is not touched.
		 *
		 *	spent() asks without counting, forget() lets a client go.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a slug: the file /data/<name>.php
		 *	@param		string		$key					Whose try it is, eg. \Nino\Http::getClientIp( $appData )
		 *	@param		int				$limit				The tries a window allows
		 *	@param		int				$window				How long a window lasts, in seconds
		 *
		 *	@return 	bool											Whether this try is within the limit
		 */
		public static function count( array &$appData, string $name, string $key, int $limit, int $window ): bool {

			$path = self::_counterPath( 'count', $name );

			if( $path === null )
				return false;

			if( $limit <= 0 )
				return true;

			$hash		= hash( 'sha256', $key );
			$now		= time();
			$tries	= 0;

			$written = \Nino\Filesystem::mutate( $appData, $path, static function( mixed $state ) use ( $hash, $now, $window, &$tries ): array {

				$state = self::_live( is_array( $state ) === true ? $state : [], $now );

				$entry					= $state[$hash] ?? [ 'tries' => 0, 'reset' => $now + $window ];
				$entry['tries']	= (int) ( $entry['tries'] ?? 0 ) + 1;
				$state[$hash]		= $entry;
				$tries					= $entry['tries'];

				return $state;
			}, [] );

			return $written === true && $tries <= $limit;
		}

		/**
		 *	Whether a client has used up its tries, without counting one: the
		 *	counter of count(), read and not locked. For the guard in front of an
		 *	action that counts only when it succeeds - a form that counts the
		 *	submissions that went through and refuses the next one before it
		 *	does any work.
		 *
		 *	A client with no entry, or one whose window is over, has spent
		 *	nothing; a $limit of 0 or less is no limit, so nothing is ever spent.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a slug
		 *	@param		string		$key					Whose tries
		 *	@param		int				$limit				The tries a window allows
		 *
		 *	@return 	bool											True while the window is open and holds $limit tries or more
		 */
		public static function spent( array &$appData, string $name, string $key, int $limit ): bool {

			$path = self::_counterPath( 'spent', $name );

			if( $path === null || $limit <= 0 )
				return false;

			$state = \Nino\Filesystem::getFileContent( $appData, $path, [] );
			$entry = is_array( $state ) === true ? ( $state[ hash( 'sha256', $key ) ] ?? null ) : null;

			if( is_array( $entry ) === false || (int) ( $entry['reset'] ?? 0 ) <= time() )
				return false;

			return (int) ( $entry['tries'] ?? 0 ) >= $limit;
		}

		/**
		 *	Let a client go: its entry leaves the counter, so that the next try
		 *	opens a window of its own - what a correct password does to the tries
		 *	a wrong one counted. Nothing is written for a client that has no
		 *	entry; where something is, the entries whose window is over go with it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a slug
		 *	@param		string		$key					Whose tries
		 *
		 *	@return 	void
		 */
		public static function forget( array &$appData, string $name, string $key ): void {

			$path = self::_counterPath( 'forget', $name );

			if( $path === null )
				return;

			$hash	= hash( 'sha256', $key );
			$now	= time();

			\Nino\Filesystem::mutate( $appData, $path, static function( mixed $state ) use ( $hash, $now ): ?array {

				if( is_array( $state ) === false || array_key_exists( $hash, $state ) === false )
					return null;

				unset( $state[$hash] );

				return self::_live( $state, $now );
			}, [] );
		}

		/**
		 *	The file of a counter - /data/<name>.php - or null, with a warning,
		 *	for a name that is no slug
		 *
		 *	@param		string		$method				The method that was called, for the message
		 *	@param		string		$name
		 *
		 *	@return 	string|null
		 */
		private static function _counterPath( string $method, string $name ): ?string {

			if( preg_match( self::NAME_PATTERN, $name ) !== 1 ) {
				trigger_error( 'Feature::'. $method. '(): "'. $name. '" is no counter name - a slug of lower case letters, digits and hyphens', E_USER_WARNING );
				return null;
			}

			return '/data/'. $name. '.php';
		}

		/**
		 *	A counter without the entries whose window is over, and without
		 *	anything that is not an entry
		 *
		 *	@param		array 		$state				The counter as it was read
		 *	@param		int				$now
		 *
		 *	@return 	array
		 */
		private static function _live( array $state, int $now ): array {

			foreach( $state as $stateKey => $entry )
				if( is_array( $entry ) === false || (int) ( $entry['reset'] ?? 0 ) <= $now )
					unset( $state[$stateKey] );

			return $state;
		}

		/**
		 *	Register a shortcode unless the Components module owns the name:
		 *	\Nino\Modules\Components::components() or stacks() has an entry for it
		 *	when the manifest of a feature registered it as a component or as a
		 *	stack, and the two registries are the whole answer to "is the module
		 *	on and does it know it". What the module owns it renders itself -
		 *	registering a callback beside it would chain a second renderer onto
		 *	the first.
		 *
		 *	The answer says which it was, so a feature that has something to do
		 *	where the component owns the name asks for it - a feature that
		 *	rewrites the arguments of its component before the module sees them,
		 *	by a callback of '/nino/html/shortcode/<name>' at priority 1, does so
		 *	where this answers false:
		 *
		 *		if( \Nino\Feature::shortcode( $appData, 'copy', [ self::class, 'doShortcode' ] ) === false )
		 *			\Nino\Callbacks::registerCallback( $appData, '/nino/html/shortcode/copy', [ self::class, 'callbackShortcode' ], 1 );
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The shortcode
		 *	@param		callable	$callback			( array &$appData, array $args ): string
		 *
		 *	@return 	bool											True if it registered the shortcode, false if the Components module owns the name and nothing was registered
		 */
		public static function shortcode( array &$appData, string $name, callable $callback ): bool {

			if( isset( \Nino\Modules\Components::components( $appData )[$name] ) === true || isset( \Nino\Modules\Components::stacks( $appData )[$name] ) === true )
				return false;

			\Nino\Html::addShortcode( $appData, $name, $callback );

			return true;
		}

		/**
		 *	A name made into a slug: lower case, the letters ascii does not have
		 *	written the way TRANSLITERATIONS says (the umlauts as ae, oe and ue,
		 *	the sharp s as ss, an accented vowel as the plain one),
		 *	every run of anything but letters and digits as one hyphen, no hyphen
		 *	at either end, and at most $max characters of it - the cut never
		 *	leaves a hyphen at the end. '' for a text that holds no letter or digit.
		 *
		 *	The same rule for a page script is Nino.slug(); its table and
		 *	TRANSLITERATIONS must stay equal. The normalizer a search builds for
		 *	comparing words is another thing and stays its own.
		 *
		 *	@param		string		$text
		 *	@param		int				$max					The longest slug, in characters
		 *
		 *	@return 	string
		 */
		public static function slug( string $text, int $max = 60 ): string {

			$slug = strtr( mb_strtolower( $text, 'UTF-8' ), self::TRANSLITERATIONS );
			$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $slug ), '-' );

			return rtrim( substr( $slug, 0, max( 0, $max ) ), '-' );
		}
	}
}
