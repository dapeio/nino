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
		 *	@param		array 		$options			'timeout' (seconds), 'maxBytes'
		 *
		 *	@return 	array										{ ok: bool, status: int, body: string, error: string }
		 */
		public static function get( array &$appData, string $url, array $options = [] ): array {

			$timeout	= max( 1, min( self::MAX_TIMEOUT, (int) ( $options['timeout'] ?? self::DEFAULT_TIMEOUT ) ) );
			$maxBytes	= max( 1, min( self::MAX_MAX_BYTES, (int) ( $options['maxBytes'] ?? self::DEFAULT_MAX_BYTES ) ) );
			$options	= [ 'timeout' => $timeout, 'maxBytes' => $maxBytes ];

			if( self::isHttpsUrl( $url ) === false )
				return self::_answer( false, 0, '', 'only an https url is fetched' );

			$stub = $appData['./nino/fetch/stub'] ?? null;
			if( is_callable( $stub ) === true ) {
				$stubbed = $stub( $url, $options );
				if( is_array( $stubbed ) === true )
					return self::_answer( ( $stubbed['ok'] ?? false ) === true, (int) ( $stubbed['status'] ?? 0 ), (string) ( $stubbed['body'] ?? '' ), (string) ( $stubbed['error'] ?? '' ) );
			}

			if( function_exists( 'curl_init' ) === true )
				return self::_curl( $url, $timeout, $maxBytes );

			if( filter_var( ini_get( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN ) === true )
				return self::_stream( $url, $timeout, $maxBytes );

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
		 *	@param		string		$url
		 *	@param		int				$timeout
		 *	@param		int				$maxBytes
		 *
		 *	@return 	array
		 */
		private static function _curl( string $url, int $timeout, int $maxBytes ): array {

			$handle = curl_init();
			if( $handle === false )
				return self::_answer( false, 0, '', 'curl could not be initialized' );

			$body		= '';
			$tooBig	= false;

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
		private static function _stream( string $url, int $timeout, int $maxBytes ): array {

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

			$stream = @fopen( $url, 'rb', false, $context );
			if( $stream === false )
				return self::_answer( false, 0, '', 'the request failed' );

			// Set by the http wrapper beside a successful fopen()
			$status = 0;
			foreach( $http_response_header as $line )
				if( preg_match( '#^HTTP/\S+\s+(\d{3})#', (string) $line, $m ) === 1 )
					$status = (int) $m[1];

			$body		= '';
			$tooBig	= false;
			while( feof( $stream ) === false ) {
				$chunk = fread( $stream, 65536 );
				if( $chunk === false )
					break;
				if( strlen( $body ) + strlen( $chunk ) > $maxBytes ) {
					$tooBig = true;
					break;
				}
				$body .= $chunk;
			}
			fclose( $stream );

			if( $tooBig === true )
				return self::_answer( false, $status, '', 'the answer exceeds '. $maxBytes. ' bytes' );

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
