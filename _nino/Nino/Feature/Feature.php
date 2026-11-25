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

		// Where the counters are kept (see count()): a folder of the project's data that starts with a dot, which a backup reads as the framework's own bookkeeping
		private const string COUNTERS = '/data/.counters';

		// The key of the installation that chooses the file of a client (see _shardPath()), beside the folders of the counters
		private const string SEED_PATH = self::COUNTERS. '/seed.php';

		// The files of a counter: the first SHARD_LENGTH hex characters of the HMAC of a client's key, so 256 files (16 to the power of SHARD_LENGTH). Measured: a try of one file costs 1.9 ms
		// at 1,000 live entries, 15 ms at 10,000 and 224 ms at 100,000; at 256 files, 100,000 entries are 390 in a file and a try costs 1.2 ms (the target
		// was 5). More files would make the folders larger and the try no cheaper: what is left of the 1.2 ms is the lock and the write
		private const int SHARD_LENGTH = 2;

		// The most entries that are still open that a file holds, and with it what a counter keeps at most: 256 * SHARD_ROOM = 131,072 clients at
		// once, which holds the 100,000 that the counter was measured with (the files of 100,000 random keys hold 390 on average, 450 at most) and
		// bounds the disk at about 17 MB for a counter. What count() does at the number is explained there
		private const int SHARD_ROOM = 512;

		// The counter of what count() says about the counters themselves, once an hour for each thing (see _say()): its own entries are never said
		private const string NOTICE_COUNTER = 'counter-notices';

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
		 *	them too. Nothing is rendered here: no fill, no shortcode - and what a
		 *	shortcode returns is finished: html+ that is to be drawn is drawn by the
		 *	shortcode with \Nino\Html::renderSource().
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
		 *	a space and the classes, escaped as \Nino\Components::escape()
		 *	escapes and with a '[' written as &#91;, so that the next rendering pass
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

			return $class === '' ? '' : ' '. str_replace( '[', '&#91;', \Nino\Components::escape( $class ) );
		}

		/**
		 *	Whether a flag of a shortcode is on. A shortcode without a schema takes
		 *	one two ways: a bare word - [copy block] - is on, and a named one -
		 *	[copy block="no"] - is read by the words a component's bool attribute
		 *	is read by, \Nino\Components::ON and OFF, case and a space
		 *	around the word ignored. Any other word, and a value that is no
		 *	string, leave it to $default.
		 *
		 *	A component declares a 'bool' attribute instead and is handed '1' or
		 *	'0' (\Nino\Components::render()); this is for the shortcode a feature
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

			/*	Trimmed here and not in \Nino\Components::render(), which reads the
				same words. That one reads what the Builder and the schemas write,
				as it is written, and a space around a word has always sent it to the
				default there; widening it would turn ' yes ' from the default into
				on for every component that exists. This one is new, and reads what
				somebody typed into a template, where a space inside the quotes is a
				slip and not another word	*/
			$word = strtolower( trim( $raw ) );

			if( in_array( $word, \Nino\Components::ON, true ) === true )
				return true;

			return in_array( $word, \Nino\Components::OFF, true ) === true ? false : $default;
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
		 *	counter is a folder, /data/.counters/<name>/, of 256 files of
		 *	the shape [ hash => [ 'tries' => n, 'reset' => unix time ] ]: the file
		 *	of a client is the one its key falls into (see _shardPath()), and a try
		 *	reads and writes that file only, under the lock of that file, so that
		 *	its cost does not grow with the number of clients the counter has seen
		 *	(100,000 live entries cost a try 1.2 ms where one file with all of
		 *	them cost 224 ms), and two clients in two files do not wait for each
		 *	other. The first try of a client opens its window of $window seconds; a
		 *	window that has run out starts a new one, and every entry of that kind
		 *	is dropped when its file is written next. The files of a counter that
		 *	nobody tries any more are left as they are, each of them as small as
		 *	SHARD_ROOM entries.
		 *
		 *	The try is counted whatever the answer: a client past the limit keeps
		 *	counting, and is let in again when its window is over - not when it
		 *	stops. The answer is true for the tries up to $limit, false for those
		 *	after it, and false as well where the file could not be written -
		 *	no reliable count, no budget: an unreliable cap is worse than a closed
		 *	one.
		 *
		 *	A file holds SHARD_ROOM live entries at most, and what happens at that
		 *	number is the one decision of this method that is about an attacker.
		 *	Anybody who can make requests from many addresses - or, where a key is
		 *	what a visitor types, from one - can make entries: a try of a key that
		 *	has none opens one. Three answers are possible for a client that has no
		 *	entry and finds its file full of entries that are still open:
		 *	- Forget the oldest entry, and count the client. The attacker then
		 *		makes room for himself: SHARD_ROOM keys that fall into the file of his
		 *		own entry push it out, and his count starts from nothing - the limit
		 *		is no limit for whoever sends a few hundred requests more, which is
		 *		what the counter is there to stop. Refused.
		 *	- Let the client in without a count. The attacker fills the file, and
		 *		everything that falls into it is unlimited. Refused, for the same
		 *		reason and worse: it needs no cleverness.
		 *	- Refuse the client, and count nothing: false, as for a client past
		 *		its limit. A file that is full of open entries is a file that a
		 *		stream of keys has filled, and what comes through it from a key that
		 *		has no entry is not told from that stream. Entries that exist go on
		 *		counting - a client that is in is not locked out by the ones that
		 *		came after it - and every entry that runs out makes room again, so
		 *		that the refusal ends with the windows that caused it. This is what
		 *		count() does. The price is that a client that is not in a full file
		 *		is turned away for at most one window although it did nothing - the
		 *		longest the counter keeps any client out - and that a visitor who
		 *		finds the file of a guard in front of a form full cannot use the form
		 *		for as long. It is what stops the attacker as well, and it is
		 *		bounded by what he keeps up.
		 *	How far an attacker gets with it: he has to keep SHARD_ROOM open
		 *	entries in one file - a file of 256 - to turn away the clients of that
		 *	file, and the file of a client is chosen by a key of the installation
		 *	(see _shardPath()), so he cannot aim at the file of a client. Measured
		 *	with keys of different /64 networks and a window of an hour: 98,304 open
		 *	keys fill no file, 131,072 fill 132 of 256, and from about 150,000 all
		 *	are full (about 42 requests a second, each with a new key; the files
		 *	fill unevenly). A key that is turned away is lost to him. Where the key
		 *	is what a visitor types he needs less, unless the caller counts the
		 *	visitor first and the typed key after: do that. No entry is lost to him
		 *	on the way: a counter is never made smaller by what others do. The
		 *	refusal ends a window after his last new key, and it ends at once where
		 *	an operator deletes the files of the counter, data/.counters/<name>/,
		 *	which costs the clients of that counter the tries they hold and nothing
		 *	else; the log says so, once an hour for each counter (the counter
		 *	'counter-notices'), with the name of the folder.
		 *
		 *	The disk is bounded as well: 256 files of SHARD_ROOM entries,
		 *	about 17 MB for a counter, however many keys come. A counter is a name
		 *	that the code chooses - never a visitor, see the check of the name -
		 *	so the number of counters is that of the places in the code that ask.
		 *
		 *	The key is hashed, always. It is an address, a mail, a name - what a
		 *	person is - and the files lie where a backup, a log or a stranger with
		 *	a read bug can reach them; sha256 of it is the entry's key, and the key
		 *	itself is never written. The folder starts with a dot, which is how a
		 *	backup knows it is the framework's own bookkeeping and carries it
		 *	nowhere (see \Nino\Backup::manifest()), and a restore leaves it as it
		 *	is.
		 *
		 *	An address is counted as \Nino\Http::clientBucket() says - an IPv6
		 *	address as its /64 - so that a client who writes any address of its
		 *	network is one entry; a key that is no address is counted as it is.
		 *
		 *	A read-modify-write under \Nino\Filesystem::mutate(), as every counter
		 *	must be: two requests at once cannot read the same count. A file that
		 *	holds anything but an array is a file that holds no tries.
		 *
		 *	The name is the folder's, and nothing else is kept there; a file
		 *	/data/<name>.php of the same name is another thing and is not touched -
		 *	but for one that is a counter of an earlier form of this class (an array
		 *	of entries under 64 hex characters, none or many): that is removed when
		 *	the folder is made, which is when the counter is tried first, and its
		 *	tries run out within a window anyway; nothing reads it. A file of the
		 *	name that is anything else is named in a warning once an hour: a counter
		 *	and a file of one name do not disturb each other, but an operator who
		 *	finds both should not have to guess.
		 *
		 *	A $limit of 0 or less is no limit: true, and nothing is touched.
		 *
		 *	spent() asks without counting, forget() lets a client go.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a slug: the folder /data/.counters/<name>/
		 *	@param		string		$key					Whose try it is, eg. \Nino\Http::getClientIp( $appData )
		 *	@param		int				$limit				The tries a window allows
		 *	@param		int				$window				How long a window lasts, in seconds
		 *
		 *	@return 	bool											Whether this try is within the limit
		 */
		public static function count( array &$appData, string $name, string $key, int $limit, int $window ): bool {

			if( self::_counterPath( 'count', $name ) === null )
				return false;

			if( $limit <= 0 )
				return true;

			$hash		= hash( 'sha256', \Nino\Http::clientBucket( $key ) );
			$seed		= self::_seed( $appData, true );

			if( $seed === null ) {
				self::_say( $appData, $name, 'seed', 'Feature::count(): the key of the counters cannot be read or made, so no counter counts and every client is turned away - '. self::_owners( $appData ) );
				return false;
			}

			$path		= self::_shardPath( $name, $hash, $seed );
			$now		= time();
			$tries	= 0;
			$full		= false;

			self::_earlier( $appData, $name );

			$written = \Nino\Filesystem::mutate( $appData, $path, static function( mixed $state ) use ( $hash, $now, $window, &$tries, &$full ): ?array {

				$state = self::_live( is_array( $state ) === true ? $state : [], $now );

				if( isset( $state[$hash] ) === false && count( $state ) >= self::SHARD_ROOM ) {
					$full = true;
					return null;
				}

				$entry					= $state[$hash] ?? [ 'tries' => 0, 'reset' => $now + $window ];
				$entry['tries']	= (int) ( $entry['tries'] ?? 0 ) + 1;
				$state[$hash]		= $entry;
				$tries					= $entry['tries'];

				return $state;
			}, [] );

			if( $full === true )
				self::_say( $appData, $name, 'full', 'Feature::count(): a file of the counter "'. $name. '" holds '. self::SHARD_ROOM. ' open entries, and the clients that have none are turned away until one runs out - a stream of keys, or a site with more visitors than this counter is made for. The counter is the folder data/.counters/'. $name. '/: what is in it runs out within a window by itself, and deleting its files lets everybody in at once, at the price of the tries they hold' );
			elseif( $written === false )
				self::_say( $appData, $name, 'unusable', 'Feature::count(): the counter "'. $name. '" cannot be written, so no client of it is counted and every one is turned away - '. self::_owners( $appData ) );

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
		 *	nothing - unless its file is full of open entries, where count()
		 *	would turn it away, and a guard that asks first has to be told the
		 *	same; a $limit of 0 or less is no limit, so nothing is ever spent.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a slug
		 *	@param		string		$key					Whose tries
		 *	@param		int				$limit				The tries a window allows
		 *
		 *	@return 	bool											True while the window is open and holds $limit tries or more, or where a try would be turned away for want of room
		 */
		public static function spent( array &$appData, string $name, string $key, int $limit ): bool {

			if( self::_counterPath( 'spent', $name ) === null || $limit <= 0 )
				return false;

			$hash	= hash( 'sha256', \Nino\Http::clientBucket( $key ) );
			$seed	= self::_seed( $appData, false );

			// No key of the installation: nothing was ever counted
			if( $seed === null )
				return false;

			$state	= \Nino\Filesystem::getFileContent( $appData, self::_shardPath( $name, $hash, $seed ), [] );
			$now		= time();
			$entry	= is_array( $state ) === true ? ( $state[$hash] ?? null ) : null;

			if( is_array( $entry ) === false || (int) ( $entry['reset'] ?? 0 ) <= $now )
				return is_array( $state ) === true && count( self::_live( $state, $now ) ) >= self::SHARD_ROOM;

			return (int) ( $entry['tries'] ?? 0 ) >= $limit;
		}

		/**
		 *	Let a client go: its entry leaves the counter, so that the next try
		 *	opens a window of its own - what a correct password does to the tries
		 *	a wrong one counted. Nothing is written for a client that has no
		 *	entry; where something is, the entries of its file whose window is over
		 *	go with it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a slug
		 *	@param		string		$key					Whose tries
		 *
		 *	@return 	void
		 */
		public static function forget( array &$appData, string $name, string $key ): void {

			if( self::_counterPath( 'forget', $name ) === null )
				return;

			$hash		= hash( 'sha256', \Nino\Http::clientBucket( $key ) );
			$seed		= self::_seed( $appData, false );

			if( $seed === null )
				return;

			$now		= time();

			\Nino\Filesystem::mutate( $appData, self::_shardPath( $name, $hash, $seed ), static function( mixed $state ) use ( $hash, $now ): ?array {

				if( is_array( $state ) === false || array_key_exists( $hash, $state ) === false )
					return null;

				unset( $state[$hash] );

				return self::_live( $state, $now );
			}, [] );
		}

		/**
		 *	Say something about the counters in the log, once an hour for each thing and counter. The counter that keeps
		 *	the hour is a counter of this class, so it says nothing about itself
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter it is about
		 *	@param		string		$what					What it is, a word
		 *	@param		string		$message
		 *
		 *	@return 	void
		 */
		private static function _say( array &$appData, string $name, string $what, string $message ): void {

			// Asked first: where the folder of the counters cannot be written, the hour cannot be kept, and the warning that says so is said by every request
			// - one line in the log for a site that is broken, which is what an operator has to see
			if( $name === self::NOTICE_COUNTER || self::spent( $appData, self::NOTICE_COUNTER, $what. '|'. $name, 1 ) === true )
				return;

			self::count( $appData, self::NOTICE_COUNTER, $what. '|'. $name, 1, 3600 );
			trigger_error( $message, E_USER_WARNING );
		}

		/**
		 *	Who owns the folder of the counters and the key of the installation, and who runs this request: what an
		 *	operator needs to see that the web server may not write where it has to
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		private static function _owners( array &$appData ): string {

			$describe = static function( string $path ): string {

				$owner	= @fileowner( $path );
				$mode		= @fileperms( $path );

				return basename( $path ). ' owner '. ( $owner === false ? '?' : (string) $owner ). ', mode '. ( $mode === false ? '?' : decoct( $mode & 0777 ) );
			};

			return $describe( \Nino\Filesystem::path( $appData, self::COUNTERS ) ). '; '. $describe( \Nino\Filesystem::path( $appData, self::SEED_PATH ) ). '; this request runs as user '. ( function_exists( 'posix_geteuid' ) === true ? (string) posix_geteuid() : '?' ). ' - the folder data/.counters and the files in it have to belong to the user of the web server';
		}

		/**
		 *	A file /data/<name>.php of the name of a counter, where the counter is not yet a folder: it is removed if it
		 *	is a counter of an earlier form - an array of entries [ 64 hex characters => [ tries, reset ] ], an empty one included -
		 *	and left as it is if it is anything else, with a warning once an hour that names it. The counter works either way:
		 *	it is in its folder
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The counter, a checked slug
		 *
		 *	@return 	void
		 */
		private static function _earlier( array &$appData, string $name ): void {

			$file = \Nino\Filesystem::path( $appData, '/data/'. $name. '.php' );

			// Looked at once, at the first use: where the folder of the counter is there, a file of the name is none of the counter's business,
			// and it is not read - a file that comes back with a restore would cost every count its whole size
			if( is_dir( \Nino\Filesystem::path( $appData, self::COUNTERS. '/'. $name ) ) === true || is_file( $file ) === false )
				return;


			try {
				$state = \Nino\Filesystem::getFileContent( $appData, '/data/'. $name. '.php', [] );
			}
			catch( \Throwable ) {
				$state = null;
			}

			$counter = is_array( $state ) === true;

			foreach( $counter === true ? $state : [] as $key => $entry )
				if( preg_match( '/^[0-9a-f]{64}$/D', (string) $key ) !== 1 || is_array( $entry ) === false || array_keys( $entry ) !== [ 'tries', 'reset' ] || is_int( $entry['tries'] ) === false || is_int( $entry['reset'] ) === false )
					$counter = false;

			if( $counter === true ) {

				@unlink( $file );

				return;
			}

			self::_say( $appData, $name, 'earlier', 'Feature::count(): the file data/'. $name. '.php has the name of the counter "'. $name. '" and is no counter, so it is left as it is - the counter is the folder data/.counters/'. $name. '/ and the two do not disturb each other, but a counter and a file of one name are one name too many' );
		}

		/**
		 *	The folder of a counter - /data/.counters/<name> - or null, with a
		 *	warning, for a name that is no slug
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

			return self::COUNTERS. '/'. $name;
		}

		/**
		 *	The file of a counter that a client falls into: SHARD_LENGTH hex
		 *	characters of an HMAC of the entry's key under the key of the
		 *	installation. The key is what keeps an attacker from choosing the file
		 *	of a client he wants to crowd out (see count()): the sha256 of an
		 *	address is public, and a file chosen by its first characters could be
		 *	filled from outside with the keys that a few hundred requests can
		 *	make; chosen by a key that is not written anywhere he can read, he can
		 *	only fill them all.
		 *
		 *	@param		string		$name					The counter, a checked slug
		 *	@param		string		$hash					The sha256 of the client's key
		 *	@param		string		$seed					The key of the installation, see _seed()
		 *
		 *	@return 	string										A virtual path
		 */
		private static function _shardPath( string $name, string $hash, string $seed ): string {

			return self::COUNTERS. '/'. $name. '/'. substr( hash_hmac( 'sha256', $hash, $seed ), 0, self::SHARD_LENGTH ). '.php';
		}

		/**
		 *	The key of the installation that the files of the counters are chosen
		 *	by: 32 random bytes in hex, in /data/.counters/seed.php with the mode
		 *	0600 - it is what keeps a visitor from aiming at a file, see
		 *	_shardPath(). It is made by the first count() of the installation under
		 *	the lock of its file, so that two requests that meet it first make one.
		 *	A file that holds anything else - or does not parse - is made again:
		 *	the counters it chose files for lose their tries, which a window is
		 *	short for. A key that cannot be made is no counter: count() is false,
		 *	as where the file of a counter cannot be locked.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$make					Whether to make it where it is not there: count() does, the others only read
		 *
		 *	@return 	string|null								null where there is none and none is made, or it cannot be
		 */
		private static function _seed( array &$appData, bool $make ): ?string {

			$read = static function() use ( &$appData ): ?string {

				try {
					$state = \Nino\Filesystem::getFileContent( $appData, self::SEED_PATH, [] );
				}
				catch( \Throwable ) {
					return null;
				}

				return is_array( $state ) === true && is_string( $state['seed'] ?? null ) === true && preg_match( '/^[0-9a-f]{64}$/D', $state['seed'] ) === 1 ? $state['seed'] : null;
			};

			$seed = $read();

			if( $seed !== null || $make === false )
				return $seed;

			if( \Nino\Filesystem::lockFile( $appData, self::SEED_PATH ) === false )
				return null;

			// What another request made while this one waited for the lock: read again, from the disk
			$seed = $read();

			if( $seed !== null ) {
				\Nino\Filesystem::unlockFile( $appData, self::SEED_PATH );
				return $seed;
			}

			$seed = bin2hex( random_bytes( 32 ) );

			return \Nino\Filesystem::putFileContent( $appData, self::SEED_PATH, [ 'seed' => $seed ], true, 0600 ) === true ? $seed : null;
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
		 *	Register a shortcode unless the register of \Nino\Components owns the name:
		 *	it has an entry for every component, those of a feature among them
		 *	(read as they stand: components() asks the methods that an
		 *	attribute's options may name, and a registration is no time for that).
		 *	The register draws what it owns itself - registering a callback beside it
		 *	would be a renderer nobody asks.
		 *
		 *	The answer says which it was: a feature that has something else to do where the
		 *	register owns the name asks for it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The shortcode
		 *	@param		callable	$callback			( array &$appData, array $args ): string
		 *	@param		bool|null	$container			true a container - its end is needed -, false a leaf, null a container where its end stands in the document (see \Nino\Html::addShortcode())
		 *
		 *	@return 	bool											True if it registered the shortcode, false if the register owns the name and nothing was registered
		 */
		public static function shortcode( array &$appData, string $name, callable $callback, ?bool $container = null ): bool {

			if( isset( $appData[\Nino\Components::REGISTRY][$name] ) === true )
				return false;

			\Nino\Html::addShortcode( $appData, $name, $callback, $container );

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
		 *	comparing words is another function of its own, but it folds its
		 *	letters with this table too, so that a word typed without its accent
		 *	is the word the index holds with it.
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
