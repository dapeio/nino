<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Catalogue						The feature catalogue: a signed list of archives, fetched on request, installed below features/
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Catalogue					Where features come from when they are not copied by
	 *										hand: a catalogue.json that getnino.dev (or a catalogue of
	 *										your own, see '/nino/catalogue/url') publishes beside the
	 *										feature archives it lists, and a detached signature
	 *										catalogue.json.sig over its exact bytes. The Features panel
	 *										loads it when asked - never on its own - offers what fits
	 *										the running kernel, and installs an archive: downloaded,
	 *										checked against the sha256 the signed catalogue names,
	 *										unpacked into a staging directory below data/, validated
	 *										as a feature, and only then moved into features/.
	 *
	 *										Trust is the signature. The public half of the key ships
	 *										with the kernel (PUBLIC_KEY) and '/nino/catalogue/key'
	 *										replaces it for a catalogue of your own; an empty key
	 *										accepts no catalogue at all. Every archive url the
	 *										catalogue names is fetched over https and nothing else.
	 *
	 *										What a catalogue says (format 1):
	 *
	 *										{ "format": 1, "generated": "...", "features": [ {
	 *											"key": "newsletter", "name": ..., "description": ...,
	 *											"version": "1.0.0", "nino": "^1.0", "php": { "ext": [] },
	 *											"requires": [], "directory": "Newsletter",
	 *											"archive": "https://.../newsletter-1.0.0.tar.gz",
	 *											"sha256": "...", "size": 12345, "released": "2026-09-07"
	 *										} ] }
	 *
	 *										An archive is a .tar.gz holding one directory named after
	 *										the feature's class, exactly what lands below features/.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Catalogue {

		// Where Nino's own catalogue is published. '' under '/nino/catalogue/url'
		// switches the catalogue off altogether
		public const string DEFAULT_URL = 'https://getnino.dev/features/catalogue.json';

		// The public half of the key Nino's catalogue is signed with, PEM.
		// Empty until the first key exists - and an empty key verifies nothing,
		// so until then no catalogue is accepted. '/nino/catalogue/key' names
		// another key for a catalogue of your own
		public const string PUBLIC_KEY = '';

		public const int FORMAT = 1;

		private const int MAX_CATALOGUE_BYTES	= 1024 * 1024;
		private const int MAX_ARCHIVE_BYTES		= 20 * 1024 * 1024;
		private const int MAX_UNPACKED_BYTES	= 50 * 1024 * 1024;
		private const int MAX_ENTRIES					= 5000;

		// Downloads and unpacked archives wait here, below the private data
		// directory, until they are verified - never in features/ itself
		private const string STAGING = '/data/.features';

		private const string KEY_PATTERN				= '/^[a-z][a-z0-9-]*$/';
		private const string DIRECTORY_PATTERN	= '/^[A-Z][A-Za-z0-9]*$/';
		private const string VERSION_PATTERN		= '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/';

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
		 *	@return 	string									The PEM public key the catalogue has to be signed with, '' for none
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
			if( $key === '' )
				return 'no catalogue key is configured, so no catalogue can be trusted';

			$json = \Nino\Fetch::get( $appData, $url, [ 'maxBytes' => self::MAX_CATALOGUE_BYTES ] );
			if( $json['ok'] === false )
				return 'the catalogue could not be fetched: '. $json['error'];

			$signature = \Nino\Fetch::get( $appData, $url. '.sig', [ 'maxBytes' => 4096 ] );
			if( $signature['ok'] === false )
				return 'the catalogue signature could not be fetched: '. $signature['error'];

			if( self::verify( $json['body'], $signature['body'], $key ) === false )
				return 'the catalogue signature does not verify';

			$catalogue = self::parse( $json['body'] );
			if( is_string( $catalogue ) === true )
				return $catalogue;

			$catalogue['url'] = $url;
			$appData['./nino/catalogue'] = $catalogue;

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
		 *	Validate and normalize a catalogue document. Anything that is not
		 *	what format 1 says is refused as a whole: a catalogue that is half
		 *	right is not offered half way.
		 *
		 *	@param		string		$json
		 *
		 *	@return 	array|string						{ format, generated, features: [ entry, ... ] } or why not
		 */
		public static function parse( string $json ): array|string {

			$document = json_decode( $json, true );
			if( is_array( $document ) === false )
				return 'the catalogue is not valid json';

			if( ( $document['format'] ?? null ) !== self::FORMAT )
				return 'the catalogue has format '. json_encode( $document['format'] ?? null ). ', this kernel reads format '. self::FORMAT;

			if( is_array( $document['features'] ?? null ) === false )
				return 'the catalogue lists no features';

			$features	= [];
			$seen			= [];

			foreach( $document['features'] as $index => $entry ) {

				$clean = self::_entry( $entry );
				if( is_string( $clean ) === true )
					return 'catalogue entry '. $index. ': '. $clean;

				$id = $clean['key']. '@'. $clean['version'];
				if( isset( $seen[$id] ) === true )
					continue;
				$seen[$id] = true;

				$features[] = $clean;
			}

			return [
				'format'		=> self::FORMAT,
				'generated'	=> is_string( $document['generated'] ?? null ) ? $document['generated'] : '',
				'features'	=> $features,
			];
		}

		/**
		 *	What the catalogue offers this installation: per key the highest
		 *	version this kernel can run, beside what is on disk. Sorted by key.
		 *
		 *	- 'state'	'available' (not on disk), 'current' (on disk in that or a
		 *						newer version), 'upgrade' (on disk in an older version), or
		 *						'incompatible' (no version of it fits this kernel - the
		 *						newest is shown with what it asks for)
		 *	- 'local'	the version on disk, null when not there
		 *	- 'active'	whether the local one is active
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue
		 *
		 *	@return 	array										key => offer
		 */
		public static function offers( array &$appData, array $catalogue ): array {

			$byKey = [];
			foreach( $catalogue['features'] as $entry )
				$byKey[ $entry['key'] ][] = $entry;

			$installed	= \Nino\Features::all( $appData );
			$offers			= [];

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
				$offer['local']		= $installed[$key]['version'] ?? null;
				$offer['active']	= $installed[$key]['active'] ?? false;
				$offer['state']		= $best === null
					? 'incompatible'
					: ( $offer['local'] === null
						? 'available'
						: ( version_compare( self::_comparable( $offer['version'] ), self::_comparable( $offer['local'] ) ) > 0 ? 'upgrade' : 'current' ) );

				$offers[$key] = $offer;
			}

			ksort( $offers );

			return $offers;
		}

		/**
		 *	Whether the panel can write a feature into place at all
		 *
		 *	@return 	bool
		 */
		public static function writable(): bool {

			$dir = \Nino\Features::dir();

			return is_dir( $dir ) === true && is_writable( $dir ) === true;
		}

		/**
		 *	Install one catalogue entry: fetch the catalogue again (what the
		 *	panel showed is not what is trusted - the signed document is),
		 *	download the archive, check its size and sha256 against the
		 *	entry, unpack it below data/ with every entry validated, make
		 *	sure what came out is the feature the catalogue promised, and
		 *	move it into features/ - replacing what was there, and putting it
		 *	back if the move fails half way. Nothing is activated here; the
		 *	panel does that where an update replaced an active feature.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Feature key
		 *	@param		string		$version			The version to install, as the catalogue names it
		 *
		 *	@return 	true|string							True, or why not
		 */
		public static function install( array &$appData, string $key, string $version ): true|string {

			$catalogue = self::fetch( $appData );
			if( is_string( $catalogue ) === true )
				return $catalogue;

			$entry = null;
			foreach( $catalogue['features'] as $candidate )
				if( $candidate['key'] === $key && $candidate['version'] === $version )
					$entry = $candidate;

			if( $entry === null )
				return 'the catalogue does not list "'. $key. '" in version '. $version;

			if( self::_fits( $entry ) === false )
				return '"'. $key. '" '. $version. ' requires Nino '. $entry['nino']. ( $entry['php']['ext'] === [] ? '' : ' and the php extensions '. implode( ', ', $entry['php']['ext'] ) );

			if( self::writable() === false )
				return 'the features directory is not writable - download '. $entry['archive']. ' and unpack it there by hand';

			$archive = \Nino\Fetch::get( $appData, $entry['archive'], [ 'maxBytes' => $entry['size'], 'timeout' => 60 ] );
			if( $archive['ok'] === false )
				return 'the archive could not be fetched: '. $archive['error'];

			if( strlen( $archive['body'] ) !== $entry['size'] || hash( 'sha256', $archive['body'] ) !== $entry['sha256'] )
				return 'the archive does not match what the catalogue promised';

			\Nino\Filesystem::forceDir( $appData, self::STAGING );
			$staging = \Nino\Filesystem::path( $appData, self::STAGING ). '/'. $key. '-'. bin2hex( random_bytes( 6 ) );
			if( @mkdir( $staging, 0755, true ) === false )
				return 'could not create the staging directory';

			$result = self::_unpackAndPlace( $appData, $archive['body'], $entry, $staging );

			\Nino\Filesystem::removeDir( $staging );

			return $result;
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$bytes				The verified archive
		 *	@param		array 		$entry				Its catalogue entry
		 *	@param		string		$staging			An empty directory of its own
		 *
		 *	@return 	true|string
		 */
		private static function _unpackAndPlace( array &$appData, string $bytes, array $entry, string $staging ): true|string {

			$archivePath = $staging. '/archive.tar.gz';
			if( file_put_contents( $archivePath, $bytes ) !== strlen( $bytes ) )
				return 'could not write the archive to the staging directory';

			$unpacked = self::_unpack( $archivePath, $entry['directory'], $staging. '/unpacked' );
			if( $unpacked !== true )
				return $unpacked;

			$source = $staging. '/unpacked/'. $entry['directory'];

			// What came out has to be the feature the signed catalogue named -
			// the same key, the same version - and a valid one. manifest()
			// warns on its own for an invalid one; here that is a refusal
			set_error_handler( static fn(): bool => true );
			$manifest = \Nino\Features::manifest( $source );
			restore_error_handler();

			if( $manifest === null )
				return 'the archive does not hold a valid feature';

			if( $manifest['key'] !== $entry['key'] || $manifest['version'] !== $entry['version'] )
				return 'the archive holds "'. $manifest['key']. '" '. $manifest['version']. ', the catalogue promised "'. $entry['key']. '" '. $entry['version'];

			// The entry fit this kernel, or install() would not be here - the
			// manifest inside has to fit too, or the directory that is there
			// would be replaced by one that cannot be switched on
			if( self::_fits( $manifest ) === false )
				return 'the archive holds "'. $manifest['key']. '" '. $manifest['version']. ', which requires Nino '. $manifest['nino']. ( $manifest['php']['ext'] === [] ? '' : ' and the php extensions '. implode( ', ', $manifest['php']['ext'] ) );

			$target		= \Nino\Features::dir(). '/'. $entry['directory'];
			$previous	= $staging. '/previous';

			if( is_dir( $target ) === true && @rename( $target, $previous ) === false )
				return 'could not move the installed feature aside';

			if( @rename( $source, $target ) === false ) {
				if( is_dir( $previous ) === true )
					@rename( $previous, $target );
				return 'could not move the feature into place';
			}

			// The registry read before this request saw the directory as it
			// was - the next reader sees the new one
			unset( $appData['./nino/features/all'] );

			return true;
		}

		/**
		 *	Unpack a .tar.gz after looking at every entry: one directory named
		 *	after the feature, nothing outside it, nothing that is not a plain
		 *	file or directory, and bounded in count and size. Only then is the
		 *	archive extracted, into the given directory.
		 *
		 *	@param		string		$archivePath
		 *	@param		string		$directory		The one top-level directory the archive may hold
		 *	@param		string		$into
		 *
		 *	@return 	true|string
		 */
		private static function _unpack( string $archivePath, string $directory, string $into ): true|string {

			try {
				$phar = new \PharData( $archivePath );

				$entries	= 0;
				$bytes		= 0;

				foreach( new \RecursiveIteratorIterator( $phar, \RecursiveIteratorIterator::SELF_FIRST ) as $file ) {

					$path = substr( (string) $file->getPathname(), strlen( 'phar://'. $archivePath. '/' ) );

					if( $path !== $directory && str_starts_with( $path, $directory. '/' ) === false )
						return 'the archive holds "'. $path. '" outside "'. $directory. '/"';

					foreach( explode( '/', $path ) as $segment )
						if( $segment === '' || $segment === '.' || $segment === '..' || str_contains( $segment, '\\' ) === true )
							return 'the archive holds an unsafe path "'. $path. '"';

					if( $file->isLink() === true || ( $file->isFile() === false && $file->isDir() === false ) )
						return 'the archive holds "'. $path. '", which is neither a file nor a directory';

					$entries++;
					$bytes += $file->isFile() === true ? (int) $file->getSize() : 0;

					if( $entries > self::MAX_ENTRIES )
						return 'the archive holds more than '. self::MAX_ENTRIES. ' entries';

					if( $bytes > self::MAX_UNPACKED_BYTES )
						return 'the archive unpacks to more than '. self::MAX_UNPACKED_BYTES. ' bytes';
				}

				if( $entries === 0 )
					return 'the archive is empty';

				if( @mkdir( $into, 0755, true ) === false )
					return 'could not create the unpacking directory';

				$phar->extractTo( $into, null, true );
			}
			catch( \Throwable $e ) {
				return 'the archive could not be read: '. $e->getMessage();
			}

			// PharData drops what it will not name - a "../" it silently
			// strips, a symlink it writes as an empty file - so what came out
			// is looked at once more: exactly the one directory, no links
			if( ( scandir( $into ) ?: [] ) !== [ '.', '..', $directory ] )
				return 'the archive unpacked to more than the directory "'. $directory. '"';

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $into. '/'. $directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST ) as $file )
				if( $file->isLink() === true || ( $file->isFile() === false && $file->isDir() === false ) )
					return 'the archive unpacked to "'. substr( (string) $file->getPathname(), strlen( $into ) + 1 ). '", which is neither a file nor a directory';

			return true;
		}

		/**
		 *	Validate and normalize one catalogue entry
		 *
		 *	@param		mixed			$entry
		 *
		 *	@return 	array|string						The clean entry, or what is wrong with it
		 */
		private static function _entry( mixed $entry ): array|string {

			if( is_array( $entry ) === false )
				return 'must be an object';

			$key = (string) ( $entry['key'] ?? '' );
			if( preg_match( self::KEY_PATTERN, $key ) !== 1 )
				return '"key" must be a slug';

			if( self::_localizedValid( $entry['name'] ?? '' ) === false )
				return '"name" must be a string or a locale => string map';

			if( isset( $entry['description'] ) === true && self::_localizedValid( $entry['description'] ) === false )
				return '"description" must be a string or a locale => string map';

			$version = (string) ( $entry['version'] ?? '' );
			if( preg_match( self::VERSION_PATTERN, $version ) !== 1 )
				return '"version" must be major.minor.patch';

			$nino = trim( (string) ( $entry['nino'] ?? '*' ) );
			if( $nino === '' || \Nino\Features::constraintValid( $nino ) === false )
				return '"nino" must be a version constraint';

			$extensions = [];
			foreach( (array) ( $entry['php']['ext'] ?? [] ) as $ext ) {
				if( is_string( $ext ) === false || preg_match( '/^[a-z][a-z0-9_]*$/i', $ext ) !== 1 )
					return '"php" => "ext" must list extension names';
				$extensions[] = strtolower( $ext );
			}

			$requires = [];
			foreach( (array) ( $entry['requires'] ?? [] ) as $req ) {
				if( is_string( $req ) === false || preg_match( self::KEY_PATTERN, $req ) !== 1 )
					return '"requires" must list feature keys';
				$requires[] = $req;
			}

			$directory = (string) ( $entry['directory'] ?? '' );
			if( preg_match( self::DIRECTORY_PATTERN, $directory ) !== 1 )
				return '"directory" must be a class name segment';

			$archive = (string) ( $entry['archive'] ?? '' );
			if( \Nino\Fetch::isHttpsUrl( $archive ) === false )
				return '"archive" must be an https url';

			$sha256 = strtolower( (string) ( $entry['sha256'] ?? '' ) );
			if( preg_match( '/^[a-f0-9]{64}$/', $sha256 ) !== 1 )
				return '"sha256" must be the hex digest of the archive';

			$size = $entry['size'] ?? null;
			if( is_int( $size ) === false || $size < 1 || $size > self::MAX_ARCHIVE_BYTES )
				return '"size" must be the archive size in bytes, at most '. self::MAX_ARCHIVE_BYTES;

			return [
				'key'					=> $key,
				'name'				=> $entry['name'],
				'description'	=> $entry['description'] ?? '',
				'version'			=> $version,
				'nino'				=> $nino,
				'php'					=> [ 'ext' => $extensions ],
				'requires'		=> $requires,
				'directory'		=> $directory,
				'archive'			=> $archive,
				'sha256'			=> $sha256,
				'size'				=> $size,
				'released'		=> is_string( $entry['released'] ?? null ) ? substr( $entry['released'], 0, 32 ) : '',
			];
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

		/**
		 *	@param		mixed			$value
		 *
		 *	@return 	bool
		 */
		private static function _localizedValid( mixed $value ): bool {

			if( is_string( $value ) === true )
				return trim( $value ) !== '';

			if( is_array( $value ) === false || $value === [] )
				return false;

			foreach( $value as $locale => $string )
				if( is_string( $locale ) === false || is_string( $string ) === false || trim( $string ) === '' )
					return false;

			return true;
		}
	}
}
