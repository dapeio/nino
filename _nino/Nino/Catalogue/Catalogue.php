<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Catalogue						The catalogue: a signed list of packages - features, themes, packages of blocks -, fetched on request and installed from archives
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Catalogue					Where features, themes and packages of blocks come from when they are not copied by hand: a catalogue.json that
	 *										getnino.dev (or a catalogue of your own, see '/nino/catalogue/url') publishes beside the archives it lists, and a
	 *										detached signature catalogue.json.sig over its exact bytes. A panel loads it when asked - never on its own -,
	 *										offers what fits the running kernel, and installs an archive: downloaded, checked against the sha256 the signed
	 *										catalogue names, read in memory by \Nino\Archive, checked by \Nino\Packages and put in place by it.
	 *
	 *										Trust is the signature. The public half of the key ships with the kernel (PUBLIC_KEY) and '/nino/catalogue/key'
	 *										replaces it for a catalogue of your own; an empty one falls back to the kernel's. Every address the catalogue
	 *										names is https and the host of it a host of the public internet (\Nino\Fetch::isPublic()), and nothing else is
	 *										fetched.
	 *
	 *										What a catalogue says (format 2):
	 *
	 *										{ "format": 2, "generated": "...", "packages": [ {
	 *											"kind": "feature", "key": "newsletter", "version": "1.0.0", "name": ..., "description": ...,
	 *											"category": "communication", "maturity": "beta", "directory": "Newsletter",
	 *											"requires": { "nino": "^2.0", "php": [], "features": [] },
	 *											"archive": "https://.../feature-newsletter-1.0.0.tar.gz", "sha256": "...", "size": 12345, "released": "2026-09-07"
	 *										}, {
	 *											"kind": "theme", "key": "craft", "version": "1.1.0", "name": ..., "description": ...,
	 *											"requires": { "nino": "^2.0", "features": [ "themes" ], "fonts": [ "lora" ] },
	 *											"parts": { "section": { "feature": "basis", "contract": "^2.0" } }, "blocks": [ "hero-soft" ],
	 *											"preview": { "url": "https://.../craft.webp", "sha256": "...", "size": 41230 },
	 *											"archive": "...", "sha256": "...", "size": 48211, "released": "..."
	 *										}, {
	 *											"kind": "blocks", "key": "gastro", "version": "1.0.0", "name": ..., "description": ...,
	 *											"requires": { "nino": "^2.0", "features": [ "basis", "cards" ] },
	 *											"blocks": [ { "key": "menu", "category": "offer" } ], "preview": { ... }, "archive": "...", ...
	 *										} ] }
	 *
	 *										A kind this reader does not know is skipped, so a kernel reads a later catalogue that has a new one. An entry of
	 *										a kind it knows that is not what the kind says is left out and costs nothing but itself: the catalogue is
	 *										believed for its signature, not for the care of the one who wrote an entry, and parse() keeps what was left out
	 *										with the reason, for a panel to say. An archive is a .tar.gz holding one folder: for a feature the one that
	 *										lands below features/ - named by "directory" -, for a theme or a package of blocks the one named by its key.
	 *
	 *										The index may also name applications - a few named sets of features with an entry point, which the setup wizard
	 *										offers:
	 *
	 *										"applications": [ { "key": "website",
	 *											"name": { "en_US": ... }, "description": { "en_US": ... },
	 *											"features": [ "builder", "design" ],
	 *											"next": "/_admin/#builder", "start": "starter" } ]
	 *
	 *										"next" is a path of the project or null, "start" says who provides the home page: "starter" the pages the wizard
	 *										creates, "own" the application itself, null nothing. An application of the wrong shape is dropped and the rest is
	 *										kept. An application may also name "menu": "folded" - the workbench opens on its entry point with the menu
	 *										folded, once, when the wizard leads there (see \Nino\Applications::menu()); any other value is no statement.
	 *
	 *										A successful fetch() is kept under data/catalogue.php - see cached(), which reads it back without ever making a
	 *										request. A panel fills from that cache on every open; only its own refresh calls fetch() again. The picture of a
	 *										package is not fetched with the catalogue: preview() fetches one when it is asked for, and keeps it below
	 *										data/.catalogue/previews/.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Catalogue {

		// Where Nino's own catalogue is published. '' under '/nino/catalogue/url'
		// switches the catalogue off altogether
		public const string DEFAULT_URL = 'https://catalogue.getnino.dev/catalogue.json';

		// The public half of the key Nino's catalogue is signed with, PEM.
		// An empty '/nino/catalogue/key' falls back to this one, so an
		// installation always has a key to verify against; the setting names
		// another for a catalogue of your own
		public const string PUBLIC_KEY = <<<'PEM'
		-----BEGIN PUBLIC KEY-----
		MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE84ucNcgOiSyR6iujeBqNtxpoFEBg
		tRm6k5xjrlISP9l8GO5qX54XWFpzt18fsm8bDfH5+HEoJRgyI8ogDogNvQ==
		-----END PUBLIC KEY-----
		PEM;

		public const int FORMAT = 2;

		// How large the archive of a package may be, packed: what is read in memory is a good deal more, and \Nino\Packages::LIMITS says how much
		public const array ARCHIVE_BYTES = [
			\Nino\Packages::FEATURE	=> 8 * 1024 * 1024,
			\Nino\Packages::THEME		=> 2 * 1024 * 1024,
			\Nino\Packages::BLOCKS	=> 2 * 1024 * 1024,
		];

		// What the sentence of a refusal of an archive says, by the code of \Nino\Archive: the archive is refused, and here is what is wrong with it
		public const array ARCHIVE_WHY = [
			\Nino\Archive::UNSUPPORTED	=> 'this server cannot unpack an archive: the zlib functions inflate_init() and inflate_add() are missing',
			\Nino\Archive::GZIP					=> 'it is no gzip stream, or a damaged one',
			\Nino\Archive::BOMB					=> 'it unpacks to more than a package of its kind may be',
			\Nino\Archive::TRAILING			=> 'it holds bytes behind its end',
			\Nino\Archive::TRUNCATED		=> 'it ends inside an entry',
			\Nino\Archive::HEADER				=> 'a header of it does not hold',
			\Nino\Archive::TYPE					=> 'it holds an entry that is neither a file nor a directory',
			\Nino\Archive::PAX					=> 'it holds an extended header that names a path, a link or a size',
			\Nino\Archive::PATH					=> 'it holds a path that is no path of plain names',
			\Nino\Archive::DEPTH				=> 'it holds a path that is deeper than a package may be',
			\Nino\Archive::FOLDER				=> 'it holds something beside the one folder it is to hold',
			\Nino\Archive::DUPLICATE		=> 'it holds a name twice, or a name that is a file and a folder',
			\Nino\Archive::NAME					=> 'it holds a file that is no file of a package',
			\Nino\Archive::COUNT				=> 'it holds more entries than a package of its kind may',
			\Nino\Archive::FILE_SIZE		=> 'it holds a file that is larger than a package may have',
			\Nino\Archive::TOTAL_SIZE		=> 'its files are more than a package of its kind may be',
		];

		private const int MAX_CATALOGUE_BYTES	= 1024 * 1024;

		// What an entry may list: the blocks of a package, the groups of the parts of a theme, the features and the fonts it asks for
		private const int MAX_BLOCKS	= 100;
		private const int MAX_PARTS		= 40;
		private const int MAX_LISTED	= 40;

		// A contract of a part as the catalogue shows it: the constraint, and the feature where one is named
		private const string CONTRACT	= '/^[A-Za-z0-9@.^~<>=!*| ,-]{1,64}$/D';
		private const string FONT			= '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

		// Where a successfully fetched catalogue is kept between requests -
		// see fetch() and cached(). Overwritten whole on every fetch, never
		// merged; a project deletes it like any other cache without losing
		// anything, and the next fetch() (or open of the panel, once one runs)
		// writes it fresh
		private const string CACHE_FILE = '/data/catalogue.php';

		// The pictures of the packages that were asked for, each by the digest the signed catalogue names
		private const string PREVIEWS = '/data/.catalogue/previews';

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									The catalogue url, '' when the catalogue is switched off
		 */
		public static function url( array &$appData ): string {

			$url = $appData['/nino/catalogue/url'] ?? self::DEFAULT_URL;

			return is_string( $url ) === true && \Nino\Fetch::isHttpsUrl( $url ) === true ? $url : '';
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									The PEM public key the catalogue has to be signed with - the kernel's own unless '/nino/catalogue/key' names one
		 */
		public static function key( array &$appData ): string {

			$key = $appData['/nino/catalogue/key'] ?? '';

			return is_string( $key ) === true && trim( $key ) !== '' ? trim( $key ) : self::PUBLIC_KEY;
		}


		/**
		 *	Fetch, verify and parse the catalogue. Two requests - the json and
		 *	its detached signature - and nothing is believed before the
		 *	signature holds over the exact bytes. Read once per request.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|string						The parsed catalogue (see parse()), or why not
		 */
		public static function fetch( array &$appData ): array|string {

			if( is_array( $appData['./nino/catalogue'] ?? null ) === true )
				return $appData['./nino/catalogue'];

			$url = self::url( $appData );
			if( $url === '' )
				return 'the catalogue is switched off';

			$key = self::key( $appData );

			$json = self::_get( $appData, $url, [ 'maxBytes' => self::MAX_CATALOGUE_BYTES ] );
			if( $json['ok'] === false )
				return 'the catalogue could not be fetched: '. self::_transport( $json['error'] );

			$signature = self::_get( $appData, self::_signatureUrl( $url ), [ 'maxBytes' => 4096 ] );
			if( $signature['ok'] === false )
				return 'the catalogue signature could not be fetched: '. self::_transport( $signature['error'] );

			if( self::verify( $json['body'], $signature['body'], $key ) === false )
				return 'the catalogue signature does not verify';

			$catalogue = self::parse( $json['body'] );
			if( is_string( $catalogue ) === true )
				return $catalogue;

			$catalogue['url'] = $url;
			$appData['./nino/catalogue'] = $catalogue;

			// Kept under data/ so the panel can show it - and a list can fill
			// itself - without a request of its own; see cached(). Best
			// effort: a write that fails (a read-only data/) does not undo a
			// fetch that itself succeeded, it only leaves nothing to show on
			// the next request that does not fetch
			\Nino\Filesystem::putFileContent( $appData, self::CACHE_FILE, [
				'fetched'		=> time(),
				'url'				=> $url,
				'catalogue'	=> $catalogue,
			] );

			return $catalogue;
		}

		/**
		 *	The catalogue as it was last fetched, read from data/ - never
		 *	fetched itself, so this is safe to call on every page the panel
		 *	opens. null when there is nothing to show: no successful fetch
		 *	yet, a file that does not hold what fetch() writes (a catalogue of
		 *	another format among them), or one fetched under a url that is not
		 *	the configured one any more - which is also what switching the
		 *	catalogue off looks like, since '' never matches a url a fetch was
		 *	ever made under
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null							The same shape fetch() returns, plus 'fetched' (unix time); null for nothing cached
		 */
		public static function cached( array &$appData ): ?array {

			$stored = \Nino\Filesystem::getFileContent( $appData, self::CACHE_FILE, null );

			if( is_array( $stored ) === false )
				return null;

			if( is_int( $stored['fetched'] ?? null ) === false || is_string( $stored['url'] ?? null ) === false )
				return null;

			if( $stored['url'] !== self::url( $appData ) )
				return null;

			$catalogue = $stored['catalogue'] ?? null;

			if( is_array( $catalogue ) === false || ( $catalogue['format'] ?? null ) !== self::FORMAT || is_array( $catalogue['packages'] ?? null ) === false )
				return null;

			$catalogue['fetched'] = $stored['fetched'];

			return $catalogue;
		}

		/**
		 *	Whether a detached signature holds over the given bytes - an ECDSA
		 *	signature over SHA-256 in DER form, base64-encoded, as
		 *	`openssl dgst -sha256 -sign key.pem catalogue.json | base64` writes it
		 *
		 *	@param		string		$data					The exact bytes that were signed
		 *	@param		string		$signature		The base64 signature, whitespace tolerated
		 *	@param		string		$publicKeyPem	The public key
		 *
		 *	@return 	bool
		 */
		public static function verify( string $data, string $signature, string $publicKeyPem ): bool {

			$raw = base64_decode( preg_replace( '/\s+/', '', $signature ) ?? '', true );
			if( $raw === false || $raw === '' || $publicKeyPem === '' )
				return false;

			$key = openssl_pkey_get_public( $publicKeyPem );
			if( $key === false )
				return false;

			return openssl_verify( $data, $raw, $key, OPENSSL_ALGO_SHA256 ) === 1;
		}

		/**
		 *	The address of a catalogue's signature: '.sig' on the end of the
		 *	path, not on the end of the url.
		 *
		 *	Appended to the whole thing, a catalogue behind a token -
		 *	'https://host/catalogue.json?token=abc', which url() accepts,
		 *	since Fetch::isHttpsUrl() has no opinion on a query string -
		 *	was fetched as '...?token=abc.sig'. That is a 404, reported as
		 *	"the catalogue signature could not be fetched", with nothing
		 *	saying the shape of the url was the cause.
		 *
		 *	@param		string		$url					The catalogue's own url
		 *
		 *	@return 	string
		 */
		private static function _signatureUrl( string $url ): string {

			$pathLength = strcspn( $url, '?#' );

			return substr( $url, 0, $pathLength ). '.sig'. substr( $url, $pathLength );
		}


		/**
		 *	Validate and normalize a catalogue document. A document that is no
		 *	catalogue of this format is refused as a whole; an entry of a package
		 *	that is not what its kind says is left out and costs nothing but
		 *	itself - what was left out is kept, with the reason - and an entry of
		 *	a kind this reader does not know is skipped.
		 *
		 *	@param		string		$json
		 *
		 *	@return 	array|string						{ format, generated, packages: [ entry, ... ], unreadable: [ { index, kind, key, reason }, ... ], applications: [ application, ... ] } or why not.
		 *																		An entry of every kind has kind, key, name, description, version, nino, php (ext), requires (the keys of the features it
		 *																		asks for), archive, sha256, size, released and preview (null or url, sha256, size); a feature has category, maturity and
		 *																		directory beside, a theme fonts (the slugs it asks for), parts (group => feature, contract) and blocks (key, category),
		 *																		a package of blocks blocks
		 */
		public static function parse( string $json ): array|string {

			$document = json_decode( $json, true );
			if( is_array( $document ) === false )
				return 'the catalogue is not valid json';

			if( ( $document['format'] ?? null ) !== self::FORMAT )
				return 'the catalogue has format '. json_encode( $document['format'] ?? null ). ', this kernel reads format '. self::FORMAT;

			if( is_array( $document['packages'] ?? null ) === false )
				return 'the catalogue lists no packages';

			$packages		= [];
			$unreadable	= [];
			$seen				= [];

			foreach( $document['packages'] as $index => $entry ) {

				$clean = self::_entry( $entry );

				if( $clean === null )
					continue;

				if( is_string( $clean ) === true ) {

					$kind = is_array( $entry ) === true && in_array( $entry['kind'] ?? null, \Nino\Packages::KINDS, true ) === true ? $entry['kind'] : '';
					$key = is_array( $entry ) === true && is_string( $entry['key'] ?? null ) === true && preg_match( \Nino\Features::KEY_PATTERN, $entry['key'] ) === 1 ? $entry['key'] : '';

					$unreadable[] = [ 'index' => (int) $index, 'kind' => $kind, 'key' => $key, 'reason' => $clean ];
					continue;
				}

				$id = $clean['kind']. ':'. $clean['key']. '@'. $clean['version'];
				if( isset( $seen[$id] ) === true )
					continue;
				$seen[$id] = true;

				$packages[] = $clean;
			}

			// Dropped one by one, unlike a document: an application is a convenience
			// laid over features that are listed on their own, so one of the wrong
			// shape - or one this reader predates - costs that application and not
			// the catalogue. A document without the key has none
			$applications	= [];
			$known				= [];

			foreach( is_array( $document['applications'] ?? null ) === true ? $document['applications'] : [] as $entry ) {

				$clean = self::_application( $entry );
				if( $clean === null || isset( $known[ $clean['key'] ] ) === true )
					continue;
				$known[ $clean['key'] ] = true;

				$applications[] = $clean;
			}

			return [
				'format'				=> self::FORMAT,
				'generated'			=> is_string( $document['generated'] ?? null ) ? $document['generated'] : '',
				'packages'			=> $packages,
				'unreadable'		=> $unreadable,
				'applications'	=> $applications,
			];
		}

		/**
		 *	Validate and normalize one entry of the packages
		 *
		 *	@param		mixed			$entry
		 *
		 *	@return 	array|string|null				The clean entry, what is wrong with it, or null for a kind this reader does not know
		 */
		private static function _entry( mixed $entry ): array|string|null {

			if( is_array( $entry ) === false )
				return 'must be an object';

			$kind = $entry['kind'] ?? null;

			if( is_string( $kind ) === false )
				return '"kind" must be one of '. implode( ', ', \Nino\Packages::KINDS );

			if( in_array( $kind, \Nino\Packages::KINDS, true ) === false )
				return null;

			$feature = $kind === \Nino\Packages::FEATURE;

			$key = (string) ( $entry['key'] ?? '' );

			if( $feature === true ? preg_match( \Nino\Features::KEY_PATTERN, $key ) !== 1 : ( strlen( $key ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $key ) !== 1 ) )
				return '"key" must be a slug'. ( $feature === true ? '' : ' of at most '. \Nino\Components::NAME_LENGTH. ' characters' );

			if( \Nino\Features::localizedValid( $entry['name'] ?? '' ) === false )
				return '"name" must be a string or a locale => string map';

			if( isset( $entry['description'] ) === true && \Nino\Features::localizedValid( $entry['description'] ) === false )
				return '"description" must be a string or a locale => string map';

			$version = (string) ( $entry['version'] ?? '' );
			if( preg_match( \Nino\Features::VERSION_PATTERN, $version ) !== 1 )
				return '"version" must be major.minor.patch';

			$requires = $entry['requires'] ?? [];
			if( is_array( $requires ) === false || ( $requires !== [] && array_is_list( $requires ) === true ) )
				return '"requires" must be an object';

			$nino = $requires['nino'] ?? '*';
			$nino = is_string( $nino ) === true ? trim( $nino ) : '';
			if( $nino === '' || \Nino\Features::constraintValid( $nino ) === false )
				return '"requires" => "nino" must be a version constraint';

			$extensions = [];
			if( is_array( $requires['php'] ?? [] ) === false || array_is_list( $requires['php'] ?? [] ) === false )
				return '"requires" => "php" must list extension names';
			foreach( $requires['php'] ?? [] as $ext ) {
				if( is_string( $ext ) === false || preg_match( \Nino\Features::EXTENSION_PATTERN, $ext ) !== 1 )
					return '"requires" => "php" must list extension names';
				$extensions[] = strtolower( $ext );
			}

			// Self-excluded and de-duplicated, the same two steps
			// Features::manifest() takes over the identical field: a package
			// that lists itself, or lists the same key twice, is the panel
			// showing a requirement on itself and a requirement twice
			$features = [];
			if( is_array( $requires['features'] ?? [] ) === false || array_is_list( $requires['features'] ?? [] ) === false || count( $requires['features'] ?? [] ) > self::MAX_LISTED )
				return '"requires" => "features" must list feature keys';
			foreach( $requires['features'] ?? [] as $req ) {
				if( is_string( $req ) === false || preg_match( \Nino\Features::KEY_PATTERN, $req ) !== 1 )
					return '"requires" => "features" must list feature keys';
				if( ( $feature === false || $req !== $key ) && in_array( $req, $features, true ) === false )
					$features[] = $req;
			}

			$archive = (string) ( $entry['archive'] ?? '' );
			if( \Nino\Fetch::isHttpsUrl( $archive ) === false )
				return '"archive" must be an https url';

			$sha256 = strtolower( (string) ( $entry['sha256'] ?? '' ) );
			if( preg_match( '/^[a-f0-9]{64}$/', $sha256 ) !== 1 )
				return '"sha256" must be the hex digest of the archive';

			$size = $entry['size'] ?? null;
			if( is_int( $size ) === false || $size < 1 || $size > self::ARCHIVE_BYTES[$kind] )
				return '"size" must be the archive size in bytes, at most '. self::ARCHIVE_BYTES[$kind];

			$preview = null;

			if( isset( $entry['preview'] ) === true || $feature === false ) {

				$preview = self::_preview( $entry['preview'] ?? null );

				if( is_string( $preview ) === true )
					return $preview;
			}

			$clean = [
				'kind'				=> $kind,
				'key'					=> $key,
				'name'				=> $entry['name'],
				'description'	=> $entry['description'] ?? '',
				'version'			=> $version,
				'nino'				=> $nino,
				'php'					=> [ 'ext' => $extensions ],
				'requires'		=> $features,
				'archive'			=> $archive,
				'sha256'			=> $sha256,
				'size'				=> $size,
				'released'		=> is_string( $entry['released'] ?? null ) ? substr( $entry['released'], 0, 32 ) : '',
				'preview'			=> $preview,
			];

			if( $feature === true )
				return self::_feature( $entry, $clean );

			$blocks = self::_blocks( $entry['blocks'] ?? [], $kind === \Nino\Packages::BLOCKS );

			if( is_string( $blocks ) === true )
				return $blocks;

			$clean['blocks'] = $blocks;

			if( $kind === \Nino\Packages::BLOCKS )
				return $clean;

			$fonts = [];
			if( is_array( $requires['fonts'] ?? [] ) === false || array_is_list( $requires['fonts'] ?? [] ) === false || count( $requires['fonts'] ?? [] ) > self::MAX_LISTED )
				return '"requires" => "fonts" must list the slugs of fonts';
			foreach( $requires['fonts'] ?? [] as $font ) {
				if( is_string( $font ) === false || strlen( $font ) > 60 || preg_match( self::FONT, $font ) !== 1 )
					return '"requires" => "fonts" must list the slugs of fonts';
				if( in_array( $font, $fonts, true ) === false )
					$fonts[] = $font;
			}

			$parts = [];
			$listed = $entry['parts'] ?? [];
			if( is_array( $listed ) === false || ( $listed !== [] && array_is_list( $listed ) === true ) || count( $listed ) > self::MAX_PARTS )
				return '"parts" must be an object of at most '. self::MAX_PARTS. ' groups';
			foreach( $listed as $group => $part ) {
				if( is_string( $group ) === false || strlen( $group ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $group ) !== 1
					|| is_array( $part ) === false || is_string( $part['contract'] ?? null ) === false || preg_match( self::CONTRACT, $part['contract'] ) !== 1
					|| ( isset( $part['feature'] ) === true && ( is_string( $part['feature'] ) === false || preg_match( \Nino\Features::KEY_PATTERN, $part['feature'] ) !== 1 ) ) )
					return '"parts" names a group by its slug, and for each a "contract" and, where it has one, the "feature" of the group';
				$parts[$group] = [ 'feature' => $part['feature'] ?? '', 'contract' => $part['contract'] ];
			}

			$clean['fonts'] = $fonts;
			$clean['parts'] = $parts;

			return $clean;
		}

		/**
		 *	The fields of a feature that no other kind has: dropped rather than refused, which is the one place this reader deliberately differs from
		 *	Features::manifest(). A kernel that refused an entry over a category it did not understand would stop offering a feature the day a newer
		 *	catalogue publishes a category it predates - the field has to be the cheap kind to get wrong
		 *
		 *	@param		array 		$entry
		 *	@param		array 		$clean				The fields every kind has
		 *
		 *	@return 	array|string
		 */
		private static function _feature( array $entry, array $clean ): array|string {

			$category = $entry['category'] ?? '';
			if( is_string( $category ) === false || preg_match( \Nino\Features::CATEGORY_PATTERN, $category ) !== 1 )
				$category = '';

			// Dropped the same way, for the same reason: a badge is not worth the entry
			$maturity = $entry['maturity'] ?? '';
			if( \Nino\Features::maturityValid( $maturity ) === false )
				$maturity = '';

			// The rule Features::manifest() applies to the directory the archive lands in: an entry naming any other would install a feature the kernel
			// then refuses to read
			$directory = (string) ( $entry['directory'] ?? '' );
			if( preg_match( \Nino\Features::DIRECTORY_PATTERN, $directory ) !== 1 )
				return '"directory" must be a class name segment';

			return $clean + [ 'category' => $category, 'maturity' => $maturity, 'directory' => $directory ];
		}

		/**
		 *	The picture of a package: where it is, what it is and how large
		 *
		 *	@param		mixed			$preview
		 *
		 *	@return 	array|string						[ 'url', 'sha256', 'size' ] or what is wrong
		 */
		private static function _preview( mixed $preview ): array|string {

			$wrong = '"preview" is a webp picture: its https "url", its "sha256" and its "size" of at most '. \Nino\Packages::MAX_PREVIEW. ' bytes';

			if( is_array( $preview ) === false || array_diff( array_keys( $preview ), [ 'url', 'sha256', 'size' ] ) !== [] )
				return $wrong;

			$sha256 = strtolower( (string) ( $preview['sha256'] ?? '' ) );

			if( is_string( $preview['url'] ?? null ) === false || \Nino\Fetch::isHttpsUrl( $preview['url'] ) === false || preg_match( '/^[a-f0-9]{64}$/', $sha256 ) !== 1
				|| is_int( $preview['size'] ?? null ) === false || $preview['size'] < 1 || $preview['size'] > \Nino\Packages::MAX_PREVIEW )
				return $wrong;

			return [ 'url' => $preview['url'], 'sha256' => $sha256, 'size' => $preview['size'] ];
		}

		/**
		 *	The blocks a theme or a package of blocks lists: the keys, and for a package a category of each as well
		 *
		 *	@param		mixed			$blocks
		 *	@param		bool			$needed				Whether there must be one
		 *
		 *	@return 	array|string						[ [ 'key', 'category' ], ... ] or what is wrong
		 */
		private static function _blocks( mixed $blocks, bool $needed ): array|string {

			$wrong = '"blocks" lists the blocks of the package, one slug each or an object with the "key" and a "category", at most '. self::MAX_BLOCKS;

			if( is_array( $blocks ) === false || array_is_list( $blocks ) === false || count( $blocks ) > self::MAX_BLOCKS || ( $needed === true && $blocks === [] ) )
				return $wrong;

			$clean = [];
			$seen = [];

			foreach( $blocks as $block ) {

				$key = is_array( $block ) === true ? ( $block['key'] ?? null ) : $block;
				$category = is_array( $block ) === true ? ( $block['category'] ?? '' ) : '';

				if( is_string( $key ) === false || strlen( $key ) > \Nino\Components::NAME_LENGTH || preg_match( \Nino\Components::NAME, $key ) !== 1 || isset( $seen[$key] ) === true
					|| is_string( $category ) === false || ( $category !== '' && preg_match( \Nino\Features::CATEGORY_PATTERN, $category ) !== 1 ) )
					return $wrong;

				$seen[$key] = true;
				$clean[] = [ 'key' => $key, 'category' => $category ];
			}

			return $clean;
		}


		/**
		 *	The applications of a parsed catalogue this kernel can offer: those
		 *	whose features all have a version it can run - the rule offers()
		 *	applies to a feature, so an application is never half offered. In the
		 *	order the catalogue lists them.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue
		 *
		 *	@return 	array										The applications, as parse() returns them
		 */
		public static function applications( array &$appData, array $catalogue ): array {

			$offers = self::offers( $appData, $catalogue, \Nino\Packages::FEATURE );
			$fitting = [];

			foreach( (array) ( $catalogue['applications'] ?? [] ) as $application ) {

				foreach( $application['features'] as $feature )
					if( ( $offers[$feature]['fits'] ?? false ) !== true )
						continue 2;

				$fitting[] = $application;
			}

			return $fitting;
		}

		/**
		 *	What the catalogue offers this installation of one kind: per key the highest
		 *	version this kernel can run, beside what is on disk. Sorted by key.
		 *
		 *	- 'state'	'available' (not on disk), 'current' (on disk in that or a
		 *						newer version), 'upgrade' (on disk in an older version), or
		 *						'incompatible' (no version of it fits this kernel - the
		 *						newest is shown with what it asks for - or, for a theme or a
		 *						package of blocks, a feature it asks for is neither on disk nor
		 *						in the catalogue in a version that fits)
		 *	- 'local'	the version on disk, null when not there
		 *	- 'active'	whether the local one is active (a feature; a theme and a package of blocks are never)
		 *	- 'shipped'	whether the local one is delivered with the kernel: it is 'current' whatever
		 *						the catalogue lists, and install() refuses it
		 *	- 'needs'	what is not there that the package asks for, as a hint: 'features' (their keys: they are
		 *						installed with it where the catalogue lists them) and 'fonts' (their slugs: a font is
		 *						never installed with a package, and a package is installed without it)
		 *	- 'blocked'	the keys of the features it asks for that cannot be had: not on disk and not offered
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue
		 *	@param		string		$kind					One of \Nino\Packages::KINDS
		 *
		 *	@return 	array										key => offer
		 */
		public static function offers( array &$appData, array $catalogue, string $kind ): array {

			$byKey = [];
			foreach( $catalogue['packages'] as $entry )
				if( $entry['kind'] === $kind )
					$byKey[ $entry['key'] ][] = $entry;

			$feature		= $kind === \Nino\Packages::FEATURE;
			$installed	= $feature === true ? \Nino\Features::all( $appData ) : [];
			$packages		= $feature === true ? [] : \Nino\Packages::installed( $appData, $kind );
			$listed			= [];

			if( $feature === false )
				foreach( $catalogue['packages'] as $entry )
					if( $entry['kind'] === \Nino\Packages::FEATURE && self::_fits( $entry ) === true )
						$listed[ $entry['key'] ] = true;

			$offers = [];

			foreach( $byKey as $key => $entries ) {

				usort( $entries, static fn( array $a, array $b ): int => version_compare( self::_comparable( $b['version'] ), self::_comparable( $a['version'] ) ) );

				$best = null;
				foreach( $entries as $entry )
					if( self::_fits( $entry ) === true ) {
						$best = $entry;
						break;
					}

				$offer						= $best ?? $entries[0];
				$offer['fits']		= $best !== null;
				$offer['local']		= $feature === true ? ( $installed[$key]['version'] ?? null ) : ( $packages[$key] ?? null );
				$offer['active']	= $feature === true ? ( $installed[$key]['active'] ?? false ) : false;
				$offer['shipped']	= $feature === true && ( $installed[$key]['shipped'] ?? false ) === true;

				$needs = [ 'features' => [], 'fonts' => [] ];
				$blocked = [];

				foreach( $offer['requires'] as $required )
					if( \Nino\Features::get( $appData, $required ) === null ) {

						$needs['features'][] = $required;

						if( isset( $listed[$required] ) === false )
							$blocked[] = $required;
					}

				foreach( $offer['fonts'] ?? [] as $font )
					if( is_dir( \Nino\Filesystem::path( $appData, '/fonts/'. $font ) ) === false )
						$needs['fonts'][] = $font;

				$offer['needs']		= $needs;
				$offer['blocked']	= $feature === true ? [] : $blocked;

				// A feature that is delivered with the kernel is updated with it: the catalogue offers no version of it
				$offer['state']		= $offer['shipped'] === true
					? 'current'
					: ( $best === null || $offer['blocked'] !== []
						? 'incompatible'
						: ( $offer['local'] === null
							? 'available'
							: ( version_compare( self::_comparable( $offer['version'] ), self::_comparable( $offer['local'] ) ) > 0 ? 'upgrade' : 'current' ) ) );

				$offers[$key] = $offer;
			}

			ksort( $offers );

			return $offers;
		}

		/**
		 *	Whether a feature can be written into place at all
		 *
		 *	@return 	bool
		 */
		public static function writable(): bool {

			$dir = \Nino\Features::dir();

			return is_dir( $dir ) === true && is_writable( $dir ) === true;
		}


		/**
		 *	Install one catalogue entry, together with the features the project
		 *	does not carry yet that it asks for (see _plan()): fetch the catalogue
		 *	again (what the panel showed is not what is trusted - the signed
		 *	document is), and for each of them download the archive, check its
		 *	size and sha256 against the entry, read it in memory (\Nino\Archive),
		 *	have \Nino\Packages check it - a theme and a package of blocks for what
		 *	no code may be in, a feature by its manifest -, make sure what came
		 *	out is the package the catalogue promised, and put it in place:
		 *	replacing what was there, and putting it back if the exchange fails
		 *	half way. What is placed through \Nino\Packages::place() is the one
		 *	mechanism for every kind - the staging folder, the exchange, the
		 *	opcache of a feature, the memory of the blocks - and a feature is
		 *	asked its manifest by a closure of ours before the exchange, since
		 *	what a manifest says is php that has to be included to be read.
		 *	Nothing is activated here: the panel switches a feature it newly placed
		 *	on, and applies the update of an active one by activating it again in
		 *	a request of its own. A theme and a package of blocks change nothing
		 *	of a site by being there.
		 *
		 *	What was placed is under './nino/catalogue/placed' for the caller, a
		 *	list of [ 'kind', 'key', 'version' ] in the order it was placed; what
		 *	a refused package was refused for, as \Nino\Packages::check() answers
		 *	it, under './nino/catalogue/problem', with the kind, key and version of
		 *	the entry - for a panel that says it in its own words. Both are runtime
		 *	keys, never written anywhere.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					One of \Nino\Packages::KINDS
		 *	@param		string		$key					Package key
		 *	@param		string		$version			The version to install, as the catalogue names it
		 *
		 *	@return 	true|string							True, or why not - \Nino\Features::BUSY while another change of the features holds the lock, UNLOCKABLE where the lock cannot be had
		 */
		public static function install( array &$appData, string $kind, string $key, string $version ): true|string {

			if( in_array( $kind, \Nino\Packages::KINDS, true ) === false )
				return 'there is no such kind of package';

			// Under the lock of the features: what is placed below features/ or
			// private/packages/ is not taken away, or switched on over, by another
			// process while this one swaps the directory
			$locked = \Nino\Features::lock( $appData );
			if( $locked !== true )
				return $locked;

			try {
				return self::_install( $appData, $kind, $key, $version );
			}
			finally {
				\Nino\Features::unlock( $appData );
			}
		}

		/**
		 *	Take a theme or a package of blocks away. What uses it is no reason to keep it: a site never hangs on a package (see \Nino\Packages::remove()). A
		 *	feature is taken away by \Nino\Features::remove(), which asks that it is switched off first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					THEME or BLOCKS
		 *	@param		string		$key					The key of the package
		 *
		 *	@return 	true|string							True, or why not - \Nino\Features::BUSY while another change of the features holds the lock
		 */
		public static function remove( array &$appData, string $kind, string $key ): true|string {

			if( in_array( $kind, [ \Nino\Packages::THEME, \Nino\Packages::BLOCKS ], true ) === false )
				return 'only a theme and a package of blocks are taken away here - a feature is taken away by \\Nino\\Features::remove()';

			$result = \Nino\Packages::remove( $appData, $kind, $key );

			if( $result === true )
				return true;

			return in_array( $result['code'], [ 'busy', 'unlockable' ], true ) === true ? ( $result['code'] === 'busy' ? \Nino\Features::BUSY : \Nino\Features::UNLOCKABLE ) : \Nino\Packages::why( $result );
		}

		/**
		 *	The picture of a package, as the catalogue that was fetched last names it: from data/.catalogue/previews/ where it is, else fetched now - https, the host
		 *	one of the public internet, not more bytes than the entry says, the digest it names, and a webp by its head - and kept there. Never on the fetch of the
		 *	catalogue, so a catalogue opens without a request for each of its pictures. The answer to a browser is made by the panel that asks, from the path
		 *	(Answering a File in docs/development.md)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					One of \Nino\Packages::KINDS
		 *	@param		string		$key					The key of the package: the picture is the one of the version that is offered
		 *
		 *	@return 	array|string						[ 'bytes' => the picture, 'path' => the file it is kept in, 'sha256' => its digest ], or why not
		 */
		public static function preview( array &$appData, string $kind, string $key ): array|string {

			$catalogue = self::cached( $appData );

			if( $catalogue === null || in_array( $kind, \Nino\Packages::KINDS, true ) === false )
				return 'the catalogue has not been loaded';

			$entry = self::offers( $appData, $catalogue, $kind )[$key] ?? null;

			if( $entry === null || is_array( $entry['preview'] ?? null ) === false )
				return 'the catalogue has no picture of "'. self::_named( $key ). '"';

			$preview	= $entry['preview'];
			$dir			= \Nino\Filesystem::path( $appData, self::PREVIEWS );
			$path			= $dir. '/'. $preview['sha256']. '.webp';

			if( is_file( $path ) === true && is_link( $path ) === false ) {

				$bytes = @file_get_contents( $path, false, null, 0, $preview['size'] + 1 );

				if( is_string( $bytes ) === true && self::_picture( $bytes, $preview ) === true )
					return [ 'bytes' => $bytes, 'path' => $path, 'sha256' => $preview['sha256'] ];
			}

			$answer = self::_get( $appData, $preview['url'], [ 'maxBytes' => $preview['size'], 'timeout' => 30 ] );

			if( $answer['ok'] === false )
				return 'the picture could not be fetched: '. self::_transport( $answer['error'] );

			if( self::_picture( $answer['body'], $preview ) === false )
				return 'the picture does not match what the catalogue promised';

			\Nino\Filesystem::forceDir( $appData, self::PREVIEWS );

			$temporary = $dir. '/.'. bin2hex( random_bytes( 6 ) ). '.tmp';

			if( @file_put_contents( $temporary, $answer['body'] ) !== strlen( $answer['body'] ) || @rename( $temporary, $path ) === false ) {

				@unlink( $temporary );

				return 'the picture could not be kept';
			}

			// Kept: the pictures of the catalogue that is cached, and no other - a catalogue that is fetched again names other digests
			$named = [];

			foreach( $catalogue['packages'] as $package )
				if( is_array( $package['preview'] ?? null ) === true )
					$named[ $package['preview']['sha256']. '.webp' ] = true;

			// The file another request is writing - .<hex>.tmp, which is moved in by one rename - is not ours to delete, however long it lies: it is young, or it is
			// the leftover of a request that died, which the next fetch of a picture that is not kept takes away after WORK_AGE
			foreach( (array) scandir( $dir ) as $name )
				if( is_string( $name ) === true && $name !== '.' && $name !== '..' && isset( $named[$name] ) === false && is_file( $dir. '/'. $name ) === true
					&& ( preg_match( '/^\.[a-f0-9]{12}\.tmp$/D', $name ) !== 1 || abs( time() - (int) filemtime( $dir. '/'. $name ) ) > \Nino\Packages::WORK_AGE ) )
					@unlink( $dir. '/'. $name );

			return [ 'bytes' => $answer['body'], 'path' => $path, 'sha256' => $preview['sha256'] ];
		}

		/**
		 *	Whether bytes are the picture an entry promised: the size, the digest, and the head of a webp - RIFF, the length of the rest, WEBP
		 *
		 *	@param		string		$bytes
		 *	@param		array 		$preview			The 'preview' of an entry
		 *
		 *	@return 	bool
		 */
		private static function _picture( string $bytes, array $preview ): bool {

			return strlen( $bytes ) === $preview['size'] && hash( 'sha256', $bytes ) === $preview['sha256']
				&& strlen( $bytes ) >= 16 && str_starts_with( $bytes, 'RIFF' ) === true && substr( $bytes, 8, 4 ) === 'WEBP'
				&& (int) unpack( 'V', substr( $bytes, 4, 4 ) )[1] === strlen( $bytes ) - 8;
		}

		/**
		 *	The steps of install(), under the lock of the features
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					One of \Nino\Packages::KINDS
		 *	@param		string		$key					Package key
		 *	@param		string		$version			The version to install
		 *
		 *	@return 	true|string
		 */
		private static function _install( array &$appData, string $kind, string $key, string $version ): true|string {

			// Before a byte is downloaded: an archive cannot be read on a server without zlib
			if( \Nino\Archive::supported() === false )
				return self::ARCHIVE_WHY[ \Nino\Archive::UNSUPPORTED ];

			$catalogue = self::fetch( $appData );
			if( is_string( $catalogue ) === true )
				return $catalogue;

			// What this install will place, in the order it has to: the
			// features the project does not have yet, deepest first, then the
			// package that was asked for. Worked out whole before a byte
			// is downloaded, so a requirement the catalogue cannot serve is a
			// refusal with nothing on disk rather than half an installation
			$plan = self::_plan( $appData, $catalogue, $kind, $key, $version, [] );

			if( is_string( $plan ) === true )
				return $plan;

			if( self::writable() === false )
				foreach( $plan as $entry )
					if( $entry['kind'] === \Nino\Packages::FEATURE )
						return 'the features directory is not writable - download '. $entry['archive']. ' and unpack it there by hand';

			// What actually went in, for the panel to name, and what a package was refused for
			$appData['./nino/catalogue/placed'] = [];
			unset( $appData['./nino/catalogue/problem'] );

			foreach( $plan as $entry ) {

				$result = self::_place( $appData, $entry );

				// Which entry failed, not how many there are. _plan() appends the
				// package that was asked for last and every missing requirement
				// before it, so a plan of one is the requested package alone - but
				// as soon as one requirement had to come along, the size told
				// nothing about which entry the loop is on, and a refusal for the
				// package the project pressed Install on was announced as a
				// required feature of itself
				if( $result !== true )
					return $entry['kind'] === $kind && $entry['key'] === $key
						? $result
						: 'required feature "'. $entry['key']. '": '. $result;

				$appData['./nino/catalogue/placed'][] = [ 'kind' => $entry['kind'], 'key' => $entry['key'], 'version' => $entry['version'] ];
			}

			return true;
		}

		/**
		 *	Everything one install has to place, in order: the features a package
		 *	asks for before it, each one only where the project does not already
		 *	carry it, and every entry checked against this kernel before anything
		 *	is fetched.
		 *
		 *	A requirement already on disk is left exactly as it is, whatever
		 *	version it has - an install is not the moment to update something
		 *	a project chose to keep. What it cannot do it says: a requirement
		 *	the catalogue does not list is a refusal naming it, because the
		 *	alternative is placing a package that cannot be used.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue
		 *	@param		string		$kind					The kind of the package asked for
		 *	@param		string		$key					The package asked for
		 *	@param		string		$version			Its version, '' for the newest that fits
		 *	@param		array 		$chain				Keys of features already in the plan, against a cycle
		 *
		 *	@return 	array|string						The entries to place, or why not
		 */
		private static function _plan( array &$appData, array $catalogue, string $kind, string $key, string $version, array $chain ): array|string {

			$feature = $kind === \Nino\Packages::FEATURE;

			// A feature that is delivered with the kernel is updated with it, and nothing in the features directory takes its place
			if( $feature === true && ( \Nino\Features::get( $appData, $key )['shipped'] ?? false ) === true )
				return 'the feature "'. $key. '" is delivered with the kernel and is updated with it';

			$noun = match( $kind ) {
				\Nino\Packages::FEATURE	=> '',
				\Nino\Packages::THEME		=> 'theme ',
				default									=> 'package of blocks ',
			};

			$entry = null;

			foreach( $catalogue['packages'] as $candidate )
				if( $candidate['kind'] === $kind && $candidate['key'] === $key && ( $version === '' ? self::_fits( $candidate ) === true : $candidate['version'] === $version ) )
					if( $entry === null || version_compare( self::_comparable( $candidate['version'] ), self::_comparable( $entry['version'] ) ) > 0 )
						$entry = $candidate;

			if( $entry === null )
				return $version === ''
					? 'the catalogue lists no '. $noun. '"'. $key. '" this kernel can run'
					: 'the catalogue does not list '. $noun. '"'. $key. '" in version '. $version;

			if( self::_fits( $entry ) === false )
				return $noun. '"'. $key. '" '. $entry['version']. ' requires Nino '. $entry['nino']. ( $entry['php']['ext'] === [] ? '' : ' and the php extensions '. implode( ', ', $entry['php']['ext'] ) );

			$chain[]	= $kind. ':'. $key;
			$plan			= [];

			foreach( $entry['requires'] as $required ) {

				// Already there, or already in this plan: nothing to do either
				// way, and the second case is what stops two features that
				// require each other from planning forever
				if( in_array( \Nino\Packages::FEATURE. ':'. $required, $chain, true ) === true || \Nino\Features::get( $appData, $required ) !== null )
					continue;

				$sub = self::_plan( $appData, $catalogue, \Nino\Packages::FEATURE, $required, '', $chain );

				if( is_string( $sub ) === true )
					return 'required feature "'. $required. '": '. $sub;

				foreach( $sub as $subEntry ) {
					$chain[]	= \Nino\Packages::FEATURE. ':'. $subEntry['key'];
					$plan[]		= $subEntry;
				}
			}

			$plan[] = $entry;

			return $plan;
		}


		/**
		 *	Download one entry's archive, check it against what the signed
		 *	catalogue promised, read it in memory and put the package in place
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				One validated catalogue entry
		 *
		 *	@return 	true|string
		 */
		private static function _place( array &$appData, array $entry ): true|string {

			$kind		= $entry['kind'];
			$limits	= \Nino\Packages::LIMITS[$kind];

			$archive = self::_get( $appData, $entry['archive'], [ 'maxBytes' => $entry['size'], 'timeout' => 60 ] );
			if( $archive['ok'] === false )
				return 'the archive could not be fetched: '. self::_transport( $archive['error'] );

			if( strlen( $archive['body'] ) !== $entry['size'] || hash( 'sha256', $archive['body'] ) !== $entry['sha256'] )
				return 'the archive does not match what the catalogue promised';

			// The folder it holds is the one that lands below features/ for a feature, and the one of the key for the others. No file is larger than all
			// of a feature may be; of a theme or a package of blocks the largest file is a picture of a block
			$folder = $kind === \Nino\Packages::FEATURE ? $entry['directory'] : $entry['key'];
			$files = \Nino\Archive::read( $archive['body'], $folder, $limits['files'], $kind === \Nino\Packages::FEATURE ? $limits['bytes'] : \Nino\Blocks::MAX_PICTURE, $limits['bytes'] );

			if( is_string( $files ) === true )
				return 'the archive is refused: '. ( self::ARCHIVE_WHY[$files] ?? $files );

			if( $kind === \Nino\Packages::FEATURE ) {

				$manifest = null;
				$result = \Nino\Packages::place( $appData, $kind, $entry['directory'], $files, self::_verify( $entry, $manifest ) );

				if( $result === true && is_array( $manifest ) === true ) {

					/*	Whether the class this directory serves is already in memory - a
						running feature's is, loaded at boot - decides whether the update
						can be applied in this request at all: Features::activate() asks
						method_exists() for the upgrade hook, and that answers for the
						class in memory, the previous version's. Noted here, where the
						replacement happens, and read by activate(), which defers	*/
					if( class_exists( $manifest['module'], false ) === true )
						$appData['./nino/features/replaced'][ $manifest['key'] ] = true;
				}

				return self::_said( $appData, $entry, $result );
			}

			// What came out has to be the package the signed catalogue named - the same key, which place() holds to the folder, and the same version. Asked of
			// the staging folder like the manifest of a feature, after \Nino\Packages::place() has checked the files
			$promised = static function( string $folder ) use ( $kind, $entry, $files ): ?array {

				$says = \Nino\Packages::identity( $kind, $files );

				return $says !== null && $says['key'] === $entry['key'] && $says['version'] === $entry['version']
					? null : [ 'code' => 'promised', 'path' => '', 'params' => [ $says['key'] ?? '', $says['version'] ?? '', $entry['key'], $entry['version'] ] ];
			};

			return self::_said( $appData, $entry, \Nino\Packages::place( $appData, $kind, $entry['key'], $files, $promised ) );
		}

		/**
		 *	What a feature's manifest says, asked of the folder it was written to before it is exchanged for the one that is there: it has to be valid and to be
		 *	the feature the signed catalogue named - the same key, the same version - and to fit this kernel, or the directory that is there would be replaced
		 *	by one that cannot be switched on. A manifest is php the archive brought, and php that does not parse throws where an invalid one returns null
		 *
		 *	@param		array 		$entry				The entry of the feature
		 *	@param		array|null	&$manifest		(reference) The manifest, where it was good
		 *
		 *	@return 	\Closure									For \Nino\Packages::place()
		 */
		private static function _verify( array $entry, ?array &$manifest ): \Closure {

			return static function( string $folder ) use ( $entry, &$manifest ): ?array {

				set_error_handler( static fn(): bool => true );
				try {
					$manifest = \Nino\Features::manifest( $folder );
				}
				// Whatever it threw. Without this the throw left past restore_error_handler(), so the
				// swallow-everything closure above stayed on the stack for the rest of the process - and
				// install() raised instead of refusing, which is a 500 in the panel rather than a sentence
				catch( \Throwable ) {
					$manifest = null;
				}
				finally {
					restore_error_handler();
				}

				if( $manifest === null )
					return [ 'code' => 'manifest', 'path' => '', 'params' => [] ];

				if( $manifest['key'] !== $entry['key'] || $manifest['version'] !== $entry['version'] )
					return [ 'code' => 'promised', 'path' => '', 'params' => [ $manifest['key'], $manifest['version'], $entry['key'], $entry['version'] ] ];

				if( self::_fits( $manifest ) === false )
					return [ 'code' => 'unfit', 'path' => '', 'params' => [ $manifest['key'], $manifest['version'], $manifest['nino'], $manifest['php']['ext'] === [] ? '' : ' and the php extensions '. implode( ', ', $manifest['php']['ext'] ) ] ];

				return null;
			};
		}

		/**
		 *	The answer of \Nino\Packages as the sentence this class gives, and what it said kept for a panel
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry
		 *	@param		true|array	$result
		 *
		 *	@return 	true|string
		 */
		private static function _said( array &$appData, array $entry, true|array $result ): true|string {

			if( $result === true )
				return true;

			$appData['./nino/catalogue/problem'] = $result + [ 'kind' => $entry['kind'], 'key' => $entry['key'], 'version' => $entry['version'] ];

			return \Nino\Packages::why( $result );
		}

		/**
		 *	One request for the catalogue: the url is a host of the public internet, or it is not asked
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$url
		 *	@param		array 		$options			As \Nino\Fetch::get() takes them
		 *
		 *	@return 	array										As \Nino\Fetch::get() answers
		 */
		private static function _get( array &$appData, string $url, array $options ): array {

			$checked = \Nino\Fetch::check( $appData, $url );

			if( is_string( $checked ) === true )
				return [ 'ok' => false, 'status' => 0, 'body' => '', 'error' => $checked === 'unresolved' ? 'this server cannot resolve the name of the host' : 'the address is no host of the public internet' ];

			// To the addresses that were checked, and to no other
			return \Nino\Fetch::get( $appData, $url, $options + ( $checked === [] ? [] : [ 'addresses' => $checked ] ) );
		}

		/**
		 *	What a sentence may say of a name it was given: printable characters and not many
		 *
		 *	@param		string		$name
		 *
		 *	@return 	string
		 */
		private static function _named( string $name ): string {

			return (string) preg_replace( '/[^\x20-\x7E]/', '?', substr( $name, 0, 80 ) );
		}

		/**
		 *	What the transport said about a failed download, as it may go to the
		 *	browser: curl names what it could not open - a certificate file the
		 *	php.ini points at - by its path on this server. An answer that holds
		 *	a path of the filesystem is left to the log of the house and the
		 *	sentence says so; every other (a host that does not resolve, a
		 *	certificate that does not verify, an http status) is the operator's
		 *	to read as it is
		 *
		 *	@param		string		$error				Fetch's error
		 *
		 *	@return 	string
		 */
		private static function _transport( string $error ): string {

			if( preg_match( '#(?<![\w:/.-])/(?:[\w.-]+/)*[\w.-]+|\b[A-Za-z]:\\\\#', $error ) !== 1 )
				return $error;

			trigger_error( 'Catalogue: the download failed: '. $error, E_USER_WARNING );

			return 'the transfer failed - the reason is in the log';
		}

		/**
		 *	Validate and normalize one application of the index
		 *
		 *	@param		mixed			$entry
		 *
		 *	@return 	array|null							The clean application, null when it is not of the shape
		 */
		private static function _application( mixed $entry ): ?array {

			if( is_array( $entry ) === false )
				return null;

			$key = $entry['key'] ?? null;
			if( is_string( $key ) === false || preg_match( \Nino\Features::KEY_PATTERN, $key ) !== 1 )
				return null;

			if( \Nino\Features::localizedValid( $entry['name'] ?? '' ) === false )
				return null;

			if( isset( $entry['description'] ) === true && \Nino\Features::localizedValid( $entry['description'] ) === false )
				return null;

			$features = $entry['features'] ?? null;
			if( is_array( $features ) === false || $features === [] || array_is_list( $features ) === false )
				return null;

			foreach( $features as $feature )
				if( is_string( $feature ) === false || preg_match( \Nino\Features::KEY_PATTERN, $feature ) !== 1 )
					return null;

			if( count( array_unique( $features ) ) !== count( $features ) )
				return null;

			// Where the wizard leads once the application is in: a path of this
			// project, an anchor allowed. Anything else is no entry point - the
			// application is still worth offering, so it has none
			$next = $entry['next'] ?? null;
			if( is_string( $next ) === false || strlen( $next ) > 255 || \Nino\Http::isSitePath( $next ) === false )
				$next = null;

			$start = $entry['start'] ?? null;
			if( $start !== null && in_array( $start, [ 'starter', 'own' ], true ) === false )
				return null;

			$application = [
				'key'					=> $key,
				'name'				=> $entry['name'],
				'description'	=> $entry['description'] ?? '',
				'features'		=> $features,
				'next'				=> $next,
				'start'				=> $start,
			];

			if( ( $entry['menu'] ?? null ) === \Nino\Applications::MENU_FOLDED )
				$application['menu'] = \Nino\Applications::MENU_FOLDED;

			return $application;
		}

		/**
		 *	@param		array 		$entry
		 *
		 *	@return 	bool										Whether this kernel can run it: version constraint and extensions
		 */
		private static function _fits( array $entry ): bool {

			if( \Nino\Features::satisfies( $entry['nino'] ) === false )
				return false;

			foreach( $entry['php']['ext'] as $ext )
				if( extension_loaded( $ext ) === false )
					return false;

			return true;
		}

		/**
		 *	@param		string		$version			eg. '1.0.0-beta'
		 *
		 *	@return 	string									'1.0.0' - a pre-release counts as its release, as in Features::satisfies()
		 */
		private static function _comparable( string $version ): string {

			return preg_replace( '/-.*$/', '', $version ) ?? $version;
		}
	}
}
