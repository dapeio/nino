<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Fetch								One outbound https request, on request and nowhere else
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Fetch							The kernel's one http client: a GET over https, with a
	 *										timeout and a byte cap, through curl where the extension
	 *										is there and through php's own stream wrapper where it
	 *										is not. Nothing in Nino calls it on its own - the
	 *										Features panel does, when someone presses "load the
	 *										catalogue" or installs a feature (see \Nino\Catalogue),
	 *										and that is deliberately the only outbound request the
	 *										framework makes.
	 *
	 *										Plain https only: no redirects are followed, no other
	 *										scheme is fetched, the certificate is verified, and the
	 *										answer is cut off at the cap rather than read to the end.
	 *										The user agent says "Nino" and nothing more - not the
	 *										version, not the site.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Fetch {

		public const int DEFAULT_TIMEOUT		= 15;
		public const int DEFAULT_MAX_BYTES	= 2 * 1024 * 1024;
		public const int MAX_TIMEOUT				= 120;
		public const int MAX_MAX_BYTES			= 100 * 1024 * 1024;
		public const string USER_AGENT			= 'Nino';

		// Endings of a name that is a name of a local network and no host of the public internet
		private const array LOCAL_ENDINGS = [ 'localhost', 'local', 'localdomain', 'internal', 'intranet', 'lan', 'home', 'corp', 'arpa' ];

		/**
		 *	GET one https url.
		 *
		 *	A test replaces the network by putting a callable under
		 *	'./nino/fetch/stub' - it receives the url and the options and
		 *	answers with the same shape this returns, or null to fall through
		 *	to the real request. Runtime-only ('./'), never configuration.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$url					The https url
		 *	@param		array 		$options			'timeout' (seconds), 'maxBytes', 'addresses' (the addresses of the host the request is to go to, as isPublic() found them)
		 *
		 *	@return 	array										{ ok: bool, status: int, body: string, error: string }
		 */
		public static function get( array &$appData, string $url, array $options = [] ): array {

			$timeout	= max( 1, min( self::MAX_TIMEOUT, (int) ( $options['timeout'] ?? self::DEFAULT_TIMEOUT ) ) );
			$maxBytes	= max( 1, min( self::MAX_MAX_BYTES, (int) ( $options['maxBytes'] ?? self::DEFAULT_MAX_BYTES ) ) );

			// The addresses isPublic() found for the name: the request goes to these and to no other the name may have by now
			$addresses = array_values( array_filter( (array) ( $options['addresses'] ?? [] ), 'is_string' ) );
			$options	= [ 'timeout' => $timeout, 'maxBytes' => $maxBytes ];

			if( $addresses !== [] )
				$options['addresses'] = $addresses;

			if( self::isHttpsUrl( $url ) === false )
				return self::_answer( false, 0, '', 'only an https url is fetched' );

			$stub = $appData['./nino/fetch/stub'] ?? null;
			if( $stub instanceof \Closure ) {
				$stubbed = $stub( $url, $options );
				if( is_array( $stubbed ) === true )
					return self::_answer( ( $stubbed['ok'] ?? false ) === true, (int) ( $stubbed['status'] ?? 0 ), (string) ( $stubbed['body'] ?? '' ), (string) ( $stubbed['error'] ?? '' ) );
			}

			if( function_exists( 'curl_init' ) === true )
				return self::_curl( $url, $timeout, $maxBytes, $addresses );

			if( filter_var( ini_get( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN ) === true )
				return self::_stream( $url, $timeout, $maxBytes, $addresses );

			return self::_answer( false, 0, '', 'no http client available: neither the curl extension nor allow_url_fopen' );
		}

		/**
		 *	@param		string		$url
		 *
		 *	@return 	bool										Whether it is an absolute https url with a host and without credentials
		 */
		public static function isHttpsUrl( string $url ): bool {

			if( strlen( $url ) > 2048 || preg_match( '/[\s\x00-\x1f]/', $url ) === 1 )
				return false;

			$parts = parse_url( $url );

			return is_array( $parts ) === true
				&& strtolower( (string) ( $parts['scheme'] ?? '' ) ) === 'https'
				&& (string) ( $parts['host'] ?? '' ) !== ''
				&& isset( $parts['user'] ) === false
				&& isset( $parts['pass'] ) === false;
		}

		/**
		 *	Whether an address names a host that may be asked on behalf of a person who typed no address: the one of a catalogue, an archive, a picture. What is
		 *	checked: the address is https; its host is a name - an address in place of a name, dotted, whole, hexadecimal or an IPv6 one, is none - of two labels
		 *	or more, with a top level of letters that is no name of a local network; and, with the lookup, every address the system gives the name is one of the
		 *	public internet. A name the system gives no address is not asked.
		 *
		 *	What is not checked, and what stands against it:
		 *	- the request that follows goes to the addresses that were found (check() answers them, get() takes them as 'addresses'): a name that answers another
		 *	  address the second time is not asked for it. Where php has no curl, the stream wrapper is given the address and the name for the certificate;
		 *	- the IPv6 addresses of a name are read with dns_get_record(), and where php has not got it they are not read: a name with a public IPv4 address and
		 *	  an internal IPv6 one passes there;
		 *	- a server that sends its requests through a proxy of its environment (HTTPS_PROXY) lets the proxy do the lookup, and this one says nothing about
		 *	  where the proxy goes.
		 *	Against all three: an address is asked only where an account that may change the setting named it or where a catalogue named it that is signed, a
		 *	certificate has to hold for the name, and an archive is believed for the digest the signed catalogue names.
		 *
		 *	A test replaces the lookup by putting a closure under './nino/fetch/resolve' - it receives the host and answers the addresses, or false; a string or an array that
		 *	config.php names is no closure and no replacement. Where the
		 *	network is replaced ('./nino/fetch/stub' is a closure) and no such lookup is named, the name is judged by its spelling alone: nothing is asked of a
		 *	name server by a run that asks nothing of the network.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$url
		 *	@param		bool			$lookup				Whether the name is looked up as well
		 *
		 *	@return 	bool
		 */
		public static function isPublic( array &$appData, string $url, bool $lookup = true ): bool {

			return is_array( self::check( $appData, $url, $lookup ) );
		}

		/**
		 *	What isPublic() asks, and what it found: the addresses of the host the request is to be made to, which get() takes as 'addresses' so that the request goes to
		 *	the addresses that were checked and not to what the name answers a moment later
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$url
		 *	@param		bool			$lookup				Whether the name is looked up as well
		 *
		 *	@return 	array|string							The addresses ([] where there was no lookup), or why not: 'spelling' (no name of the public internet), 'unresolved' (the system gives the name no address) or 'private' (one of its addresses is not public)
		 */
		public static function check( array &$appData, string $url, bool $lookup = true ): array|string {

			if( self::isHttpsUrl( $url ) === false )
				return 'spelling';

			$host = rtrim( strtolower( (string) parse_url( $url, PHP_URL_HOST ) ), '.' );
			$labels = explode( '.', $host );

			if( $host === '' || strlen( $host ) > 253 || count( $labels ) < 2 || preg_match( '/^[a-z][a-z0-9-]*$/D', end( $labels ) ) !== 1 || in_array( end( $labels ), self::LOCAL_ENDINGS, true ) === true )
				return 'spelling';

			foreach( $labels as $label )
				if( preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label ) !== 1 )
					return 'spelling';

			if( $lookup === false )
				return [];

			$resolve = $appData['./nino/fetch/resolve'] ?? null;

			if( $resolve instanceof \Closure === false ) {

				if( ( $appData['./nino/fetch/stub'] ?? null ) instanceof \Closure )
					return [];

				$resolve = self::_resolve( ... );
			}

			$addresses = $resolve( $host );

			// No answer is no leave: a name nobody knows now may be an internal one a moment later
			if( is_array( $addresses ) === false || $addresses === [] )
				return 'unresolved';

			foreach( $addresses as $address )
				if( is_string( $address ) === false || self::_publicAddress( $address ) === false )
					return 'private';

			return array_values( $addresses );
		}

		/**
		 *	@param		string		$host
		 *
		 *	@return 	array|false								The addresses the system gives the name, false for none
		 */
		private static function _resolve( string $host ): array|false {

			$found = @gethostbynamel( $host );
			$found = is_array( $found ) === true ? $found : [];

			if( function_exists( 'dns_get_record' ) === true ) {

				$records = @dns_get_record( $host, DNS_AAAA );

				foreach( is_array( $records ) === true ? $records : [] as $record )
					if( is_string( $record['ipv6'] ?? null ) === true )
						$found[] = $record['ipv6'];
			}

			return $found === [] ? false : $found;
		}

		/**
		 *	@param		string		$address
		 *
		 *	@return 	bool										Whether it is an address of the public internet. The ranges that are not global are listed here and not asked of filter_var(), whose lists differ between the versions of php
		 */
		private static function _publicAddress( string $address ): bool {

			if( filter_var( $address, FILTER_VALIDATE_IP ) === false )
				return false;

			$packed = (string) @inet_pton( $address );
			$bytes = array_values( unpack( 'C*', $packed ) ?: [] );

			if( count( $bytes ) === 4 ) {

				[ $a, $b, $c ] = $bytes;

				return ( $a === 0 || $a === 10 || $a === 127 || $a >= 224 ) === false
					&& ( $a === 100 && ( $b & 0xC0 ) === 64 ) === false
					&& ( $a === 169 && $b === 254 ) === false
					&& ( $a === 172 && ( $b & 0xF0 ) === 16 ) === false
					&& ( $a === 192 && $b === 0 && ( $c === 0 || $c === 2 ) ) === false
					&& ( $a === 192 && $b === 88 && $c === 99 ) === false
					&& ( $a === 192 && $b === 168 ) === false
					&& ( $a === 198 && ( $b & 0xFE ) === 18 ) === false
					&& ( $a === 198 && $b === 51 && $c === 100 ) === false
					&& ( $a === 203 && $b === 0 && $c === 113 ) === false;
			}

			if( count( $bytes ) !== 16 )
				return false;

			// 64:ff9b::/96 carries an IPv4 address, which is the one that counts
			if( str_starts_with( $packed, "\0\x64\xff\x9b". str_repeat( "\0", 8 ) ) === true )
				return self::_publicAddress( (string) inet_ntop( substr( $packed, 12 ) ) );

			// Global unicast is 2000::/3, and in it not 2001::/23 (the protocols, 2001:10:: among them), 2001:db8::/32 (the documentation), 2002::/16 (6to4, which
			// carries an IPv4 address of anybody's) - everything else is multicast, site local, unique local, link local, mapped, embedded, loopback or nothing
			if( ( $bytes[0] & 0xE0 ) !== 0x20 )
				return false;

			if( $bytes[0] === 0x20 && ( ( $bytes[1] === 0x01 && $bytes[2] < 0x02 ) || ( $bytes[1] === 0x01 && $bytes[2] === 0x0d && $bytes[3] === 0xb8 ) || $bytes[1] === 0x02 ) )
				return false;

			return true;
		}

		/**
		 *	@param		string		$url
		 *	@param		int				$timeout
		 *	@param		int				$maxBytes
		 *
		 *	@return 	array
		 */
		private static function _curl( string $url, int $timeout, int $maxBytes, array $addresses = [] ): array {

			$handle = curl_init();
			if( $handle === false )
				return self::_answer( false, 0, '', 'curl could not be initialized' );

			$body		= '';
			$tooBig	= false;

			// The name is connected to the addresses that were checked, whatever the system answers for it now: the certificate still has to hold for the name
			$resolve = [];

			if( $addresses !== [] ) {

				$parts = parse_url( $url );
				$port = (int) ( $parts['port'] ?? 443 );
				$resolve = [ strtolower( (string) $parts['host'] ). ':'. $port. ':'. implode( ',', array_map( static fn( string $address ): string => str_contains( $address, ':' ) ? '['. $address. ']' : $address, $addresses ) ) ];
			}

			curl_setopt_array( $handle, [
				CURLOPT_URL								=> $url,
				CURLOPT_RETURNTRANSFER		=> false,
				CURLOPT_FOLLOWLOCATION		=> false,
				CURLOPT_PROTOCOLS					=> CURLPROTO_HTTPS,
				CURLOPT_REDIR_PROTOCOLS		=> CURLPROTO_HTTPS,
				CURLOPT_CONNECTTIMEOUT		=> $timeout,
				CURLOPT_TIMEOUT						=> $timeout,
				CURLOPT_SSL_VERIFYPEER		=> true,
				CURLOPT_SSL_VERIFYHOST		=> 2,
				CURLOPT_USERAGENT					=> self::USER_AGENT,
				CURLOPT_RESOLVE						=> $resolve,
				CURLOPT_HTTPHEADER				=> [ 'Accept: */*' ],
				CURLOPT_WRITEFUNCTION			=> static function( mixed $curl, string $chunk ) use ( &$body, &$tooBig, $maxBytes ): int {
					if( strlen( $body ) + strlen( $chunk ) > $maxBytes ) {
						$tooBig = true;
						return -1;
					}
					$body .= $chunk;
					return strlen( $chunk );
				},
			] );

			$done		= curl_exec( $handle );
			$status	= (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
			$error	= curl_error( $handle );
			curl_close( $handle );

			if( $tooBig === true )
				return self::_answer( false, $status, '', 'the answer exceeds '. $maxBytes. ' bytes' );

			if( $done === false )
				return self::_answer( false, $status, '', $error !== '' ? $error : 'the request failed' );

			if( $status !== 200 )
				return self::_answer( false, $status, '', 'http '. $status );

			return self::_answer( true, $status, $body, '' );
		}

		/**
		 *	@param		string		$url
		 *	@param		int				$timeout
		 *	@param		int				$maxBytes
		 *
		 *	@return 	array
		 */
		private static function _stream( string $url, int $timeout, int $maxBytes, array $addresses = [] ): array {

			$context = stream_context_create( [
				'http' => [
					'method'					=> 'GET',
					'timeout'					=> $timeout,
					'follow_location'	=> 0,
					'max_redirects'		=> 0,
					'ignore_errors'		=> true,
					'user_agent'			=> self::USER_AGENT,
					'header'					=> "Accept: */*\r\n",
				],
				'ssl' => [
					'verify_peer'				=> true,
					'verify_peer_name'	=> true,
				],
			] );

			// Connected to the first address that was checked, with the name for the certificate and the Host header
			if( $addresses !== [] ) {

				$parts = parse_url( $url );
				$address = $addresses[0];
				$port = isset( $parts['port'] ) === true ? ':'. $parts['port'] : '';
				$host = (string) $parts['host'];
				$url = 'https://'. ( str_contains( $address, ':' ) ? '['. $address. ']' : $address ). $port. ( $parts['path'] ?? '/' ). ( isset( $parts['query'] ) === true ? '?'. $parts['query'] : '' );

				stream_context_set_option( $context, 'ssl', 'peer_name', $host );
				stream_context_set_option( $context, 'ssl', 'SNI_server_name', $host );
				stream_context_set_option( $context, 'http', 'header', "Accept: */*\r\nHost: ". $host. $port. "\r\n" );
			}

			$stream = @fopen( $url, 'rb', false, $context );
			if( $stream === false )
				return self::_answer( false, 0, '', 'the request failed' );

			// Set by the http wrapper beside a successful fopen()
			$status = 0;
			foreach( $http_response_header as $line )
				if( preg_match( '#^HTTP/\S+\s+(\d{3})#', (string) $line, $m ) === 1 )
					$status = (int) $m[1];

			$body			= '';
			$tooBig		= false;
			$unread		= false;
			while( feof( $stream ) === false ) {
				$chunk = fread( $stream, 65536 );
				/*	Not the end of the body: fread() answers false for a read
					that failed, and a used-up read timeout is how it fails
					here. Taken for an end, a server that sent its headers,
					part of a body and then went quiet came back as ok with
					status 200 and half an answer - a catalogue that is half a
					json document, an archive that is half an archive, both
					described as complete. The curl half reports the same stall
					as a failed transfer, and so does this one now.	*/
				if( $chunk === false ) {
					$unread = true;
					break;
				}
				if( strlen( $body ) + strlen( $chunk ) > $maxBytes ) {
					$tooBig = true;
					break;
				}
				$body .= $chunk;
			}

			// Read before fclose(): 'timed_out' is the stream's own record of
			// why the read above gave up, and it is gone with the handle
			$timedOut = stream_get_meta_data( $stream )['timed_out'] === true;

			fclose( $stream );

			if( $tooBig === true )
				return self::_answer( false, $status, '', 'the answer exceeds '. $maxBytes. ' bytes' );

			// No body on a failure, same as the curl half: what arrived is a
			// fragment, and a caller that is handed a fragment has no way of
			// knowing it is one
			if( $unread === true )
				return self::_answer( false, $status, '', $timedOut === true
					? 'the answer timed out after '. $timeout. ' seconds'
					: 'the answer could not be read to the end' );

			if( $status !== 200 )
				return self::_answer( false, $status, '', 'http '. $status );

			return self::_answer( true, $status, $body, '' );
		}

		/**
		 *	@param		bool			$ok
		 *	@param		int				$status
		 *	@param		string		$body
		 *	@param		string		$error
		 *
		 *	@return 	array
		 */
		private static function _answer( bool $ok, int $status, string $body, string $error ): array {

			return [ 'ok' => $ok, 'status' => $status, 'body' => $body, 'error' => $error ];
		}
	}
}
