<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Http					Request/response composition, headers, routing, and locale redirects
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Http - request/response composition, headers, routing, and locale
	// redirects
	class Http {

		// The headers every response starts with (see request()) and ends
		// up with (see _finalizeResponse()) - a callback may change one or add
		// to them, and one it leaves out is put back
		private const array DEFAULT_HEADERS = [
			'Strict-Transport-Security' 	=> 'max-age=31536000; includeSubDomains',
			// frame-ancestors is what actually stops framing in a current
			// browser - X-Frame-Options below is the legacy fallback, and
			// only DENY/SAMEORIGIN are valid values there ('same-origin'
			// was silently ignored, ie. no clickjacking protection at all).
			// base-uri/form-action are not covered by default-src: without
			// them an injected <base>/<form action> escapes the policy -
			// and textfills (Html::_renderFills()) put admin-editable text
			// into the page unescaped, so the csp is load-bearing here.
			// img-src needs 'data:' spelled out: the '*' source covers
			// network schemes only, so without it the browser refused
			// every data: uri image - including the one Nino's own
			// Nino.css uses for .nino-hero-arrowdown, which therefore
			// rendered as an empty button on every page that has one.
			'Content-Security-Policy' 		=> 'default-src \'self\'; img-src * data:; style-src \'self\' \'unsafe-inline\'; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'',
			'X-Frame-Options' 						=> 'SAMEORIGIN',
			'X-Content-Type-Options'			=> 'nosniff',
			// What a visit to another site tells that site: the origin alone
			// where a link leaves for another origin, the whole address - query
			// string included - only within this one. A current browser does
			// that by itself; this says it for an older one, which sends the
			// whole address to every other https site, and an address can
			// carry a one-time token in its query
			'Referrer-Policy'							=> 'strict-origin-when-cross-origin',
			// Nothing a page of Nino asks a visitor's device for - the camera,
			// the microphone, the location - so no page may, nor a frame in it:
			// a feature switched off in the top document is off in every frame
			'Permissions-Policy'					=> 'camera=(), microphone=(), geolocation=()',
			// Nino's own answer to whether a response may be stored,
			// rather than php's: the session used to be started on
			// every request, and its cache limiter put four no-store
			// headers on every response as a side effect of that.
			// Now that a request without session state starts no
			// session (see Runtime::startSession()), the same answer
			// has to be given here or it would depend on whether a
			// page happened to render a form. A project that wants
			// its public pages cached by browsers and proxies
			// changes this one value - what a visitor sees is one
			// render behind an edit for as long as it says.
			'Cache-Control'								=> 'no-store',
		];

		/*	The directives a Content-Security-Policy falls back to where it names
			none of its own, nearest first: the table of CSP level 3, which
			allowSource() widens a directive by. A fetch directive has the list a
			browser would read its sources from; one with an empty list is none -
			it takes no fallback, and a policy that does not name it restricts
			nothing	*/
		private const array FALLBACKS = [
			'default-src'			=> [],
			'child-src'				=> [ 'default-src' ],
			'frame-src'				=> [ 'child-src', 'default-src' ],
			'worker-src'			=> [ 'child-src', 'script-src', 'default-src' ],
			'script-src'			=> [ 'default-src' ],
			'script-src-elem'	=> [ 'script-src', 'default-src' ],
			'script-src-attr'	=> [ 'script-src', 'default-src' ],
			'style-src'				=> [ 'default-src' ],
			'style-src-elem'	=> [ 'style-src', 'default-src' ],
			'style-src-attr'	=> [ 'style-src', 'default-src' ],
			'connect-src'			=> [ 'default-src' ],
			'font-src'				=> [ 'default-src' ],
			'img-src'					=> [ 'default-src' ],
			'manifest-src'		=> [ 'default-src' ],
			'media-src'				=> [ 'default-src' ],
			'object-src'			=> [ 'default-src' ],
			'prefetch-src'		=> [ 'default-src' ],
			'base-uri'				=> [],
			'form-action'			=> [],
			'frame-ancestors'	=> [],
			'sandbox'					=> [],
			'report-uri'			=> [],
			'report-to'				=> [],
			'upgrade-insecure-requests'	=> [],
			'require-trusted-types-for'	=> [],
			'trusted-types'		=> [],
		];

		/*	The addresses a page of the project cannot have: the paths router.php and
			the .htaccess files never hand to a page (the private and the source
			halves, the checkout's own tests, features, docs and history), and the
			kernel's and the workbench's own trees, which reach index.php on Apache
			and nginx but are not the project's to take (/_nino, /_admin) or are the
			asset tree (/public). A route at one of them, or below it, would be
			accepted and never reached. router.php and the .htaccess files are the
			source of the list: a directory they come to refuse is added here as
			well. /docs and /history are reserved for as long as their directory is
			there (see WHILE_PRESENT) - a project with a page of its own at one of them
			deletes the directory, as router.php says. The Routes panel and the setup
			wizard read the list through isReservedUri()	*/
		public const array RESERVED_URIS = [ '/_admin', '/_nino', '/private', '/public', '/app', '/tests', '/features', '/docs', '/history' ];

		// The ones of RESERVED_URIS that router.php refuses only while the
		// directory of the same name is in the checkout
		private const array WHILE_PRESENT = [ '/docs', '/history' ];

		// The most one write of sendFile() carries: a file is sent in pieces
		// of this size and never read as a whole
		private const int CHUNK_SIZE = 1048576;

		public static function request( array &$appData, array &$request ): void {

			$currentLocale = \Nino\Locales::getCurrentLocale( $appData );
			$header 			= self::_filterRequestHeaderFields( $request );
			$auth 				= self::_getBasicAuthCredentials( $request, $header );

			/*	Add request/response values.

				REQUEST_METHOD and REQUEST_URI are read with a default rather
				than assumed present: neither is guaranteed by the cgi
				environment, and php-cgi under IIS is the known sapi that
				composes no REQUEST_URI at all. Read unguarded that is an
				undefined-key warning, and an engine warning is fatal in Nino
				(see Runtime::NON_FATAL_LEVELS) - so the site did not answer
				such a request wrongly, it failed to answer it at all, with
				nothing in the response to say why. A method nothing named
				matches no route and a uri nothing named is '/', which is what
				cleanUri() already answers for a uri that cleans away to
				nothing	*/
			$request['/nino/http/request'] = [
				'method'				=> self::_cleanRawMethod( $request['REQUEST_METHOD'] ?? '' ),
				// The method as it came in, before HEAD is folded into GET for
				// routing - output() needs it to send a HEAD response without a
				// body, and it keeps that fold from being invisible to anything
				// else that cares
				'rawMethod'			=> self::_cleanRawMethod( $request['REQUEST_METHOD'] ?? '', false ),
				'uri'						=> self::_projectUri( $appData, self::cleanUri( $request['REQUEST_URI'] ?? '' ) ),
				'query'					=> self::_getRequestQueryVarsPart( $request['REQUEST_URI'] ?? '' ),
				'header'				=> $header,
				'body'					=> file_get_contents( 'php://input' ),
				'user'					=> $auth['user'],
				'pw'						=> $auth['pw'],
				'ip'						=> self::getClientIp( $appData ),
			];
			// Seed the response header with the default security headers, so a
			// callback that extends one of them (eg. Jstext appending its
			// nonce to the Content-Security-Policy) works on the real value
			// instead of an empty string that would later shadow the default
			$request['/nino/http/response'] = [
				'uri'					=> $request['/nino/http/request']['uri'],
				'locale'			=> $currentLocale,
				'header'			=> self::DEFAULT_HEADERS,
				'body'				=> '',
				'statusCode'	=> 200,
			];

			array_unshift( $appData['./nino/http/requests'], $request );

			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/request', $request );
		}

		public static function response( array &$appData, array &$request ): void {

			// Find current route
			// 'uri', not '.uri': the dot prefix is the convention for runtime
			// keys elsewhere (see Elements), but the response array's own key
			// is the plain one - written with the dot, this last-resort
			// fallback merged in a stray key and left the response uri
			// pointing at the unmatched request path, so every
			// [[/_nino/webpage[[/nino/http/response/uri]]/...]] fill on the 404
			// resolved against a page that does not exist
			$routeData = self::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] ) ??
				self::requestRoute( $appData, '/404', 'GET' ) ??
				[ 'uri' => '/404', 'statusCode' => 404 ];

			// A route's own header fields extend the seeded response header
			// (see request()) instead of replacing the whole array - eg.
			// robots.txt's Content-Type must not wipe the security defaults
			if( isset( $routeData['header'] ) === true )
				$routeData['header'] = array_merge( $request['/nino/http/response']['header'], $routeData['header'] );

			$request['/nino/http/response'] = array_merge( $request['/nino/http/response'], $routeData );

			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response', $request );
			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response/'. $request['/nino/http/request']['method']. ':/'. $request['/nino/http/response']['uri'], $request );
		}



		public static function output( array &$appData, array &$request ): never {

			self::_finalizeResponse( $request );

			// Output header
			if( headers_sent() === false )
				foreach( $request['/nino/http/response']['header'] AS $headerKey => $headerValue )
					header( $headerKey. ': '. $headerValue );

			// Send status code
			http_response_code( $request['/nino/http/response']['statusCode'] );

			// Send body - a HEAD response carries the headers of the GET it
			// stands for and nothing else. The request's method has already been
			// folded to GET for routing, so the unmapped value is what decides
			// here (see _cleanRawMethod()). Most sapis would drop the body
			// anyway; not generating it is both cheaper and unambiguous.
			if( ( $request['/nino/http/request']['rawMethod'] ?? '' ) !== 'HEAD' ) {

				// A response that names a file has no body to send but the file
				// itself (see _finalizeFile()), which goes out in pieces. Nothing
				// may stand between it and the client then: a buffer still open
				// would hold the bytes - the file in memory as a whole, which is
				// what sending it in pieces is for - and the compression php applies
				// to the output would send a length the headers did not promise.
				if( isset( $request['./nino/http/stream'] ) === true ) {

					// A buffer that cannot be ended ends the loop rather than spinning it
					while( ob_get_level() > 0 )
						if( @ob_end_clean() === false )
							break;

					@ini_set( 'zlib.output_compression', '0' );

					// A stream must not hold the visitor's session: php keeps it
					// locked for as long as it is open, and a stream is long - a media
					// element asks for one range after the other, and every other
					// request of that visitor (another tab, the workbench) would queue
					// behind each of them. Nothing writes to the session from here on,
					// the exit that follows is all that is left
					if( session_status() === PHP_SESSION_ACTIVE )
						session_write_close();

					self::sendFile( $request['./nino/http/stream']['path'], $request['./nino/http/stream']['start'], $request['./nino/http/stream']['length'] );
				}
				else
					echo $request['/nino/http/response']['body'];
			}

			exit;
		}

		// json-encode a non-string body, answer the file a response names, then
		// put back any default header the response left out - the part of
		// output() that decides what would actually be sent, split out since
		// output() itself exit()s and so can't be called from a test.
		//
		// Not run through filterHeaderFields(): that whitelist is built for
		// the request side (Accept, Cookie, ...) and silently drops
		// response-only headers a module has every right to set (Set-Cookie,
		// Content-Disposition, ETag, ...). Response header values are
		// framework/module-computed, not a direct echo of user input, and
		// header() itself already refuses to send a value containing a CR/LF.
		private static function _finalizeResponse( array &$request ): void {

			// What is to be streamed is decided below and nowhere else: a key
			// left standing by anything else names a file nobody has looked at
			unset( $request['./nino/http/stream'] );

			// A response with the status 200 that names a file answers that file
			// and its body is not read, whatever type it has - it is not the json
			// of an api answer, nor does it set the Content-Type of one
			$answersFile = ( $request['/nino/http/response']['statusCode'] ?? 200 ) === 200 && isset( $request['/nino/http/response']['file'] ) === true;

			// Catch json output
			if( $answersFile === false && is_string( $request['/nino/http/response']['body'] ) === false ) {

				/*	JSON_INVALID_UTF8_SUBSTITUTE, because json_encode() answers
					false for one malformed byte anywhere in the body and the
					whole response was lost for it. This is a framework whose
					content is files people edit with their own editors, and a
					value a panel posts is bytes like any other: one latin-1
					filename in a listing, one such byte in a logged element
					name, and the panel that shows it answered an empty 200
					from then on - until somebody went into the file by hand,
					which nothing told them to do. U+FFFD in place of the byte
					is what a browser would show for it anyway	*/
				$encoded = json_encode(
					$request['/nino/http/response']['body'],
					JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
				);

				/*	What is left cannot be substituted - Inf/NaN, a resource, a
					recursion - and false was written into the body and echoed
					as the empty string: an empty 200 carrying a json
					content-type, which every caller in _admin reads as a
					success with nothing in it, renders as a blank panel and
					says nothing about. A body that cannot be encoded is a
					failure to answer, so it is answered as one, with the
					reason in it. Not a status the response gave itself: a
					handler that failed with a 403 failed with a 403, whatever
					became of its body	*/
				if( $encoded === false ) {

					$reason = json_last_error_msg();

					trigger_error( 'Http: the response body could not be json-encoded ('. $reason. ') and was answered as a failure', E_USER_WARNING );

					if( ( $request['/nino/http/response']['statusCode'] ?? 200 ) < 400 )
						$request['/nino/http/response']['statusCode'] = 500;

					$encoded = (string) json_encode( [ 'error' => 'The response could not be encoded: '. $reason ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
				}

				$request['/nino/http/response']['body'] = $encoded;
				$request['/nino/http/response']['header']['Content-Type'] = 'application/json; charset=utf-8';
			}

			if( $answersFile === true )
				self::_finalizeFile( $request );

			$request['/nino/http/response']['header'] = array_merge( self::DEFAULT_HEADERS, $request['/nino/http/response']['header'] );
		}

		/*	The file branch of _finalizeResponse(): what a response with the status
			200 that names a file is answered with - the file whole, the one range
			of it the request asked for, the refusal of a range that no byte
			satisfies, or the 404 of a file that is not there any more. All of it
			ends up on $request: the status, the headers, an empty body and, for
			the bytes, the stream output() sends them by. Nothing is sent here and
			nothing is read of the file but its size and its time.

			@param		array 		&$request			(reference) Current server request

			@return 	void */
		private static function _finalizeFile( array &$request ): void {

			$path = is_string( $request['/nino/http/response']['file'] ) === true ? $request['/nino/http/response']['file'] : '';
			$size = false;

			// A null byte makes no path: the functions below answer it with an
			// error of their own, and a file with one in its name cannot be there
			if( $path !== '' && str_contains( $path, "\0" ) === false ) {

				// The file as it is now: the size of an earlier stat is a length the
				// client would wait for and never get
				clearstatcache( true, $path );

				if( is_file( $path ) === true && is_readable( $path ) === true )
					$size = @filesize( $path );
			}

			// Gone, never there, or no file: the words are the kernel's and a path
			// is never written into a response - nor into the warning about it
			if( $size === false ) {

				trigger_error( 'Http: the file a response names is no regular, readable file - it was answered as a 404', E_USER_WARNING );

				// What the module set for the file is not for this answer: a
				// disposition would save the two words as a download, and a lifetime
				// would have a cache keep the 404 for as long as it keeps the file
				foreach( array_keys( $request['/nino/http/response']['header'] ) as $name )
					if( is_string( $name ) === true && in_array( strtolower( $name ), [ 'content-type', 'content-disposition', 'cache-control' ], true ) === true )
						unset( $request['/nino/http/response']['header'][$name] );

				$request['/nino/http/response']['statusCode'] = 404;
				$request['/nino/http/response']['header']['Content-Type'] = 'text/plain; charset=utf-8';
				$request['/nino/http/response']['body'] = 'Not Found';

				return;
			}

			// A Range is read on a GET, and on the HEAD that stands for one: any
			// other method has the header ignored and the whole file answered, as
			// RFC 9110 has a server do
			$method	= $request['/nino/http/request']['rawMethod'] ?? '';
			$wanted	= $request['/nino/http/request']['header']['Range'] ?? '';
			$range	= ( $method === 'GET' || $method === 'HEAD' ) && is_string( $wanted ) === true ? self::byteRange( $wanted, $size ) : null;

			$request['/nino/http/response']['body'] = '';
			$request['/nino/http/response']['header']['Accept-Ranges'] = 'bytes';

			// A range no byte of the file satisfies: no bytes are sent, and the
			// size says where the file ends
			if( $range === false ) {

				$request['/nino/http/response']['statusCode'] = 416;
				$request['/nino/http/response']['header']['Content-Range'] = 'bytes */'. $size;

				return;
			}

			// The whole file, an empty one included, is a range of its own
			[ $start, $end ] = $range ?? [ 0, $size - 1 ];
			$length = $end - $start + 1;

			if( $range !== null ) {

				$request['/nino/http/response']['statusCode'] = 206;
				$request['/nino/http/response']['header']['Content-Range'] = 'bytes '. $start. '-'. $end. '/'. $size;
			}

			$request['/nino/http/response']['header']['Content-Length'] = (string) $length;

			// What the module set stands, in whatever case of letters it wrote the
			// name: a second Content-Type beside the module's would be the one sent
			if( self::_hasHeader( $request['/nino/http/response']['header'], 'Content-Type' ) === false )
				$request['/nino/http/response']['header']['Content-Type'] = 'application/octet-stream';

			$modified = @filemtime( $path );

			if( $modified !== false && self::_hasHeader( $request['/nino/http/response']['header'], 'Last-Modified' ) === false )
				$request['/nino/http/response']['header']['Last-Modified'] = gmdate( 'D, d M Y H:i:s', $modified ). ' GMT';

			$request['./nino/http/stream'] = [ 'path' => $path, 'start' => $start, 'length' => $length ];
		}

		/**
		 *	@param		array 		$header				A response's header map
		 *	@param		string		$name					A header name, eg. 'Content-Type'
		 *
		 *	@return 	bool										Whether the map names it, in any case of letters
		 */
		private static function _hasHeader( array $header, string $name ): bool {

			foreach( array_keys( $header ) as $key )
				if( is_string( $key ) === true && strcasecmp( $key, $name ) === 0 )
					return true;

			return false;
		}

		/**
		 *	The one range of a Range header that a file of $size bytes can answer.
		 *	Pure - it reads nothing but its two arguments - and the whole grammar
		 *	of the header the kernel reads: bytes=<first>-<last>, bytes=<first>-
		 *	and bytes=-<count>, the unit in any case of letters. A <last> beyond
		 *	the file is the last byte of it, and so is a <count> beyond the file
		 *	the whole of it.
		 *
		 *	A header that is no such range is nothing to answer, and the whole
		 *	file is sent: an empty one, another unit, a <last> before its <first>,
		 *	and a request for more than one range - an answer in several parts is
		 *	not made. A range of the right shape that the file cannot satisfy is
		 *	false, and is answered with a 416: a <first> at or past the end of the
		 *	file, a <count> of 0, and any range of an empty file.
		 *
		 *	@param		string		$header				The value of the Range header, eg. 'bytes=0-499'
		 *	@param		int			$size					The size of the file in bytes
		 *
		 *	@return 	array|null|false						[ first byte, last byte ], both included; null where there is no range to answer; false for one that no byte satisfies
		 */
		public static function byteRange( string $header, int $size ): array|null|false {

			if( preg_match( '/^bytes=(\d*)-(\d*)$/Di', trim( $header ), $found ) !== 1 || ( $found[1] === '' && $found[2] === '' ) )
				return null;

			// bytes=-<count>: the last <count> bytes
			if( $found[1] === '' ) {

				$count = (int) $found[2];

				return ( $count === 0 || $size === 0 ) ? false : [ max( 0, $size - $count ), $size - 1 ];
			}

			// A number past what an integer holds is as good as the largest one:
			// php casts it to that, and every file is smaller
			$first	= (int) $found[1];
			$last		= ( $found[2] === '' ) ? PHP_INT_MAX : (int) $found[2];

			if( $last < $first )
				return null;

			return ( $first >= $size ) ? false : [ $first, min( $last, $size - 1 ) ];
		}

		/**
		 *	Write a part of a file to the output, in pieces: the bytes of a response
		 *	that names a file, which output() sends after the headers.
		 *
		 *	It echoes, and is allowed to where a handler is not: a file is not a
		 *	string. A handler that read it into the body would hold all of it in
		 *	memory - the size of a video, of an archive - and one that echoed it
		 *	itself would write before the headers are sent and skip every rule
		 *	the response goes through. So a handler names the file under 'file'
		 *	and the kernel sends it, here, in pieces of at most CHUNK_SIZE bytes
		 *	with a flush after each, and stops once the client has gone.
		 *
		 *	It writes the bytes and nothing else: no header, and no output buffer
		 *	is touched - output() has ended them by the time it calls this - so
		 *	that a test captures what is written with ob_start().
		 *
		 *	The path is the caller's to have resolved: a file of the project
		 *	through \Nino\Filesystem::path(), or one in a directory the module owns,
		 *	and never one a request names.
		 *
		 *	@param		string		$path					The file, a regular one
		 *	@param		int			$start				The first byte to send, counted from 0
		 *	@param		int			$length				How many bytes to send, from there
		 *
		 *	@return 	bool										Whether all $length bytes were written - false for a file that cannot be opened, a start the file does not reach, a file that ended early or a client that went away
		 */
		public static function sendFile( string $path, int $start, int $length ): bool {

			// A regular file only: a directory opens, and fails on its first read
			$handle = ( is_file( $path ) === true ) ? @fopen( $path, 'rb' ) : false;

			if( $handle === false )
				return false;

			// A start past the end of the file is not a failed seek - the first
			// read finds nothing there, and that is what the answer says
			if( fseek( $handle, $start ) !== 0 ) {

				fclose( $handle );

				return false;
			}

			$sent = 0;

			while( $sent < $length && connection_aborted() === 0 ) {

				$piece = @fread( $handle, min( self::CHUNK_SIZE, $length - $sent ) );

				// The file is shorter than it was when its size was read
				if( $piece === false || $piece === '' )
					break;

				echo $piece;
				flush();

				$sent += strlen( $piece );
			}

			fclose( $handle );

			return $sent === $length;
		}

		/**
		 *	Set a response's status code and error body in one call - the
		 *	statusCode/body pair a handler otherwise sets by hand on every failure
		 *	branch, with 'error' as the body key enforced rather than just
		 *	remembered by convention.
		 *
		 *	'error' is the sentence for a developer or a log, in English. A client
		 *	that wants to say it in the reader's language needs something that
		 *	does not change with the wording, so a failure a person can cause also
		 *	carries a $code, the values that belong in the sentence as $params (in
		 *	the order the client's text uses them) and the $field the value was
		 *	refused for. All three are left out of the body when empty, so a
		 *	caller that gives none answers exactly what it always did.
		 *
		 *	A code is lower-case letters, digits and underscores -
		 *	/^[a-z][a-z0-9_]*$/, which is what Nino.adminUi.api.errorText() looks
		 *	up, so a code with a hyphen in it is never found. A panel's code is
		 *	its slug first, the hyphens of the slug written as underscores, then a
		 *	word: 'hello_long' for the panel 'hello', 'my_feature_empty' for
		 *	'my-feature'. Its sentence is the fill '/_admin/error/<code>' in both
		 *	interface languages, with %s where a param goes; the workbench keeps
		 *	its own codes there too ('csrf', 'session', the upload and image
		 *	ones).
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		int			$statusCode			The HTTP status, eg. 400
		 *	@param		string		$error				The English sentence for a developer or a log
		 *	@param		string		$code				The code of a failure a person can cause; '' for none
		 *	@param		array 		$params				What belongs in the sentence, in order
		 *	@param		string		$field				The posted key the value was refused for; '' for none
		 *
		 *	@return 	void
		 */
		public static function fail( array &$request, int $statusCode, string $error, string $code = '', array $params = [], string $field = '' ): void {
			$body = [ 'error' => $error ];
			if( $code !== '' )
				$body['code'] = $code;
			if( $params !== [] )
				$body['params'] = array_values( $params );
			if( $field !== '' )
				$body['field'] = $field;
			$request['/nino/http/response']['statusCode'] = $statusCode;
			$request['/nino/http/response']['body'] = $body;
		}

		/**
		 *	Set a response's success body - the counterpart of fail(), which sets
		 *	the status as well. The status stays what the request carries, 200
		 *	unless something set another; a body that is no string is answered as
		 *	json, a string as it is.
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		mixed		$body				What the response answers, an array for json
		 *
		 *	@return 	void
		 */
		public static function ok( array &$request, mixed $body = [ 'ok' => true ] ): void {
			$request['/nino/http/response']['body'] = $body;
		}

		/**
		 *	Make the response a redirect: the status, the Location header and an
		 *	empty body, in one call. A handler must not send a header itself (it
		 *	changes the response, see Http::output()), and a redirect set by hand
		 *	was one more way to get the directory, the status or the body wrong.
		 *
		 *	$to is a path of this site - it starts with a single '/', has no
		 *	whitespace or control character, no ':' before its first '/' (which a
		 *	browser reads as a scheme) and no backslash after the slash (which it
		 *	reads as a second one) - and Location is the project's directory and
		 *	that path, so that it works wherever the project is served from. Or $to
		 *	is an absolute http or https url, which is sent as it is: a site may
		 *	send a visitor elsewhere, that is what a redirect rule is for. Anything
		 *	else - '//host/path', 'javascript:...', a relative path - is no
		 *	redirect: a warning, and the response is left as it was. A target that
		 *	comes from the request - a posted return address, a parameter of the
		 *	query - has to be checked by the caller to be a path of this site first:
		 *	an absolute url is sent as it is, so a redirect to whatever a visitor
		 *	wrote there is an open one.
		 *
		 *	The status is 301, 302, 303, 307 or 308; any other is a warning and
		 *	303, the answer a form post is sent on with. The response is marked
		 *	no-store, so that a redirect that changes (a rule edited, a page moved)
		 *	takes effect for whoever was sent by the old one.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$to						A path of the site, eg. '/thanks', or an absolute url
		 *	@param		int				$status				301, 302, 303, 307 or 308
		 *
		 *	@return 	void
		 */
		public static function redirect( array &$appData, array &$request, string $to, int $status = 303 ): void {

			if( self::_isSitePath( $to ) === true )
				$location = rtrim( \Nino\Filesystem::getDir( $appData ), '/' ). $to;
			else if( \Nino\Fetch::isHttpsUrl( $to ) === true || ( preg_match( '#^http://#i', $to ) === 1 && \Nino\Fetch::isHttpsUrl( 'https'. substr( $to, 4 ) ) === true ) )
				$location = $to;
			else {
				trigger_error( 'Http::redirect(): "'. $to. '" is neither a path of this site nor an http(s) url - the response was left as it was', E_USER_WARNING );
				return;
			}

			if( in_array( $status, [ 301, 302, 303, 307, 308 ], true ) === false ) {
				trigger_error( 'Http::redirect(): '. $status. ' is no redirect status - 303 was sent', E_USER_WARNING );
				$status = 303;
			}

			$request['/nino/http/response']['statusCode']								= $status;
			$request['/nino/http/response']['header']['Location']				= $location;
			$request['/nino/http/response']['header']['Cache-Control']	= 'no-store';
			$request['/nino/http/response']['body']											= '';
		}

		/**
		 *	@param		string		$path
		 *
		 *	@return 	bool										Whether it is a path of this site that is safe to put in a Location header
		 */
		private static function _isSitePath( string $path ): bool {

			// A single slash first: '//host' is protocol-relative, and a browser
			// reads '/\host' as the same. Whitespace or a control character has no
			// place in a header, and a colon before the next slash reads as a
			// scheme ('/javascript:...') rather than as a segment
			return str_starts_with( $path, '/' ) === true && str_starts_with( $path, '//' ) === false && str_starts_with( $path, '/\\' ) === false
				&& preg_match( '/[\s\x00-\x1f\x7f]/', $path ) !== 1 && preg_match( '#^/[^/]*:#', $path ) !== 1;
		}

		/**
		 *	Widen one directive of the Content-Security-Policy of this response by
		 *	one source - what a feature needs when its page loads from a place the
		 *	policy does not name (a frame of another site, a script of a
		 *	provider). The policy is $request['/nino/http/response']['header']
		 *	['Content-Security-Policy']; an empty one, or one that is no string,
		 *	is left as it is.
		 *
		 *	A feature may widen a policy, never open it. A source is one token
		 *	without whitespace, ';' or ',' - the characters that would end it or
		 *	its directive - and never '*' alone or an unsafe keyword in quotes
		 *	('unsafe-inline', 'unsafe-eval', 'unsafe-hashes'): a warning and
		 *	false. On a directive that runs code (script-src, script-src-elem,
		 *	script-src-attr, object-src, worker-src) a scheme alone (https:) and
		 *	a wildcard host (https://*.example.com) are refused the same way,
		 *	since either admits every host. The directive is a name of lower
		 *	case letters and hyphens.
		 *
		 *	Where the directive exists, the source is appended to its first
		 *	occurrence (a browser ignores the second, so adding one would widen
		 *	nothing), unless it is listed already, in any case of letters. Where it
		 *	does not, it is built from the first of the directives a browser would
		 *	have read its sources from instead (FALLBACKS: frame-src from child-src,
		 *	then default-src), since naming 'self' by hand would narrow a policy
		 *	whose default-src lists more; and the copy is kept only if the source
		 *	is new to it, so that widening script-src-elem after script-src is a
		 *	step that changes nothing. A policy that has none of the fallbacks
		 *	restricts the directive in no way, and is left alone.
		 *
		 *	A directive that says 'none', or lists no source at all (which a
		 *	browser reads the same way), is the project having decided, and so is
		 *	a fallback that does. The directives that are no fetch directives
		 *	(base-uri, form-action, frame-ancestors, sandbox and the like) have no
		 *	fallback: present, the source is appended as to any directive;
		 *	absent, nothing happens - an absent one restricts nothing, and
		 *	creating it with one source would narrow the policy, which a widener
		 *	never does.
		 *
		 *	The policy is written back joined with '; '. \Nino\Modules\Jstext adds
		 *	its nonce by a merge of its own, which creates script-src even beside
		 *	a default-src that says 'none'; the two rules are different ones.
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$directive		The directive to widen, eg. 'frame-src'
		 *	@param		string		$source				One source, eg. 'https://player.example.com' or 'data:'
		 *
		 *	@return 	bool											Whether the policy changed
		 */
		public static function allowSource( array &$request, string $directive, string $source ): bool {

			if( preg_match( '/^[a-z][a-z-]*$/D', $directive ) !== 1 ) {
				trigger_error( 'Http::allowSource(): "'. $directive. '" is no directive of a policy', E_USER_WARNING );
				return false;
			}

			if( $source === '' || $source === '*' || preg_match( '/[\s\x00-\x1f\x7f;,]/', $source ) === 1
				|| preg_match( "/^'unsafe-/i", $source ) === 1 ) {
				trigger_error( 'Http::allowSource(): "'. $source. '" is no source a feature may add - a policy is widened, never opened', E_USER_WARNING );
				return false;
			}

			// On a directive that runs code a scheme alone (https:) or a wildcard
			// host (https://*.example.com) admits every host of the scheme or the
			// domain: that is opening, whatever the directive said before
			if( in_array( $directive, [ 'script-src', 'script-src-elem', 'script-src-attr', 'object-src', 'worker-src' ], true ) === true
				&& ( preg_match( '/^[a-z][a-z0-9+.-]*:$/Di', $source ) === 1 || str_contains( $source, '*' ) === true ) ) {
				trigger_error( 'Http::allowSource(): "'. $source. '" is no source a feature may add to '. $directive. ' - a scheme alone or a wildcard host opens the policy to every host', E_USER_WARNING );
				return false;
			}

			$policy = $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '';

			if( is_string( $policy ) === false || trim( $policy ) === '' )
				return false;

			$directives	= array_values( array_filter( array_map( 'trim', explode( ';', $policy ) ), static fn( string $part ): bool => $part !== '' ) );
			$index			= self::_directiveIndex( $directives, $directive );

			if( $index === null ) {

				foreach( self::FALLBACKS[$directive] ?? [] as $fallback ) {

					$from = self::_directiveIndex( $directives, $fallback );

					if( $from === null )
						continue;

					if( self::_closed( $directives[$from] ) === true )
						return false;

					// The fallback's own list, under this directive's name
					$directives[]	= $directive. substr( $directives[$from], strlen( $fallback ) );
					$index				= array_key_last( $directives );
					break;
				}

				if( $index === null )
					return false;
			}
			else if( self::_closed( $directives[$index] ) === true )
				return false;

			// A directive built from its fallback is new even when it adds nothing the
			// fallback did not name - and then it changes nothing, so it is not written
			if( in_array( strtolower( $source ), array_map( 'strtolower', preg_split( '/\s+/', $directives[$index] ) ?: [] ), true ) === true )
				return false;

			$directives[$index] .= ' '. $source;
			$request['/nino/http/response']['header']['Content-Security-Policy'] = implode( '; ', $directives );

			return true;
		}

		/**
		 *	@param		array 		$directives		The parts of a policy, trimmed
		 *	@param		string		$name					A directive
		 *
		 *	@return 	int|null									Where its first occurrence is
		 */
		private static function _directiveIndex( array $directives, string $name ): ?int {

			foreach( $directives as $index => $part )
				if( preg_match( '/^'. preg_quote( $name, '/' ). '(?:\s|$)/i', $part ) === 1 )
					return $index;

			return null;
		}

		/**
		 *	@param		string		$part					One directive of a policy
		 *
		 *	@return 	bool											Whether it is closed: 'none', or a name with no source at all - which blocks everything just the same
		 */
		private static function _closed( string $part ): bool {

			return preg_match( "/(?:^|\s)'none'(?:\s|\$)/i", $part ) === 1 || preg_match( '/^\S+$/D', $part ) === 1;
		}

		/**
		 *	The route that answers a request path: the one keyed for exactly that
		 *	path and method, else the nearest wildcard route ('GET://blog/*') on a
		 *	parent of the path, up to the root. Routes are keyed project-relative,
		 *	'GET://about' for '/about'.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri				The cleaned request path without the directory the project is served from, starting with '/'
		 *	@param		string		$method				GET, POST...
		 *
		 *	@return 	array|null						The route; null where none answers, and for a path that is empty or does not start with a slash
		 */
		public static function requestRoute( array &$appData, string $uri, string $method ): ?array {

			// Only normalized absolute request paths are routable. dirname('')
			// and dirname('relative') both stabilize at '.', which made the
			// parent walk below loop forever for a malformed direct call.
			if( $uri === '' || str_starts_with( $uri, '/' ) === false )
				return null;

			$result 		= $appData['/nino/http/routes'][$method. ':/'. $uri] ?? null;
			$parentUri	= $uri;

			while( $parentUri !== '/' && $result === null ) {
				$nextParent = dirname( $parentUri );
				if( $nextParent === '.' || $nextParent === $parentUri )
					break;
				$parentUri	= $nextParent;
				$result 		= $appData['/nino/http/routes'][$method. ':/'. $parentUri.'/*'] ?? null;
			}

			return $result;
		}

		/**
		 *	The route key (eg. "GET://about") that renders a response uri in a
		 *	language for one method - eg. to redirect to the language's own
		 *	address of the current page. A route fits when its 'uri' is the
		 *	response uri and it has no 'locale' or this one, and the first that
		 *	fits in the order of the table is the answer: the routes of one
		 *	language stand before the ones several languages share (see
		 *	\Nino\Modules\Legal and the Routes panel).
		 *
		 *	The method is part of the question. A page and the endpoint its form
		 *	posts to share an Element-URI (the Newsletter's page and
		 *	POST://.newsletter), and the endpoint may stand first in the table:
		 *	its key is no address a visitor can be sent to.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$responseUri	The Element-URI a route renders, eg. '/about'
		 *	@param		string		$locale				The language wanted
		 *	@param		string		$method				The method of the route: GET, POST...
		 *
		 *	@return 	string|null							The route key; null where no route of that method fits
		 */
		public static function findRouteUri( array &$appData, string $responseUri, string $locale, string $method = 'GET' ): ?string {

			// '??' like every other route read in this file: a route entry
			// without a 'uri' (hand-edited config.php, a module registering
			// only a body) raised an "Undefined array key" here, and
			// Runtime::handleError() treats an engine-raised level as fatal -
			// so one typo in the config turned every locale switch into a 500
			foreach( $appData['/nino/http/routes'] as $routeUri => $routeData )
				if( str_starts_with( (string) $routeUri, $method. ':/' ) === true && ( $routeData['uri'] ?? null ) === $responseUri && ( isset( $routeData['locale'] ) === false || $routeData['locale'] === $locale ) )
					return $routeUri;

			return null;
		}

		/**
		 *	The pages among a table of routes, for a feature that lists or matches
		 *	them - a sitemap, a menu of redirect targets, the pages behind a
		 *	password. One row for every GET route that is a public address:
		 *
		 *	- 'path'			the address, without the directory, '/' for the root; a
		 *								trailing '/*' is taken off
		 *	- 'wildcard'	whether it had one - the route answers the whole subtree
		 *	- 'uri'				the route's own 'uri' (the element it renders), else the path
		 *	- 'locale'		the route's own 'locale', else ''
		 *	- 'status'		its 'statusCode', 200 where it names none
		 *	- 'html'			whether it answers html, see answersHtml()
		 *
		 *	Left out are the routes that are no page of the site: a method but
		 *	GET, a path under '/_' or '/.' (the workbench, the module endpoints),
		 *	one with whitespace, a control character or a '*' that is not its
		 *	wildcard, and one that starts with '//' or '/\' (a browser reads that
		 *	as the address of another host). Nothing else is filtered: a caller
		 *	that does not want the 404 (a 'status' of 400 and above), a subtree or
		 *	a response that is no html drops those rows itself, and each of the
		 *	three that list pages wants another subset. The rows are in the order
		 *	of the table.
		 *
		 *	Not the editor's list of pages of \Nino\Modules\Routes\Admin::pages(),
		 *	which answers which routes are pages a person made.
		 *
		 *	@param		array 		$routes				A route table, eg. $appData['/nino/http/routes'], or the persisted one: AppData::stored( $appData, '/nino/http/routes', [] )
		 *
		 *	@return 	array										A list of rows
		 */
		public static function pages( array $routes ): array {

			$pages = [];

			foreach( $routes as $routeKey => $route ) {

				if( is_string( $routeKey ) === false || str_starts_with( $routeKey, 'GET:/' ) === false || is_array( $route ) === false )
					continue;

				$path			= substr( $routeKey, strlen( 'GET:/' ) );

				// '//x' and '/\x' are no path of this site: no request is routed to one, and
				// written into a link a browser reads it as the address of another host
				if( str_starts_with( $path, '//' ) === true || str_starts_with( $path, '/\\' ) === true )
					continue;

				$wildcard	= str_ends_with( $path, '/*' );

				if( $wildcard === true ) {

					$path = substr( $path, 0, -2 );

					if( $path === '' )
						$path = '/';
				}

				if( preg_match( '#^/[^\s\x00-\x1f\x7f*]*$#D', $path ) !== 1 || str_starts_with( $path, '/_' ) === true || str_starts_with( $path, '/.' ) === true )
					continue;

				$pages[] = [
					'path'			=> $path,
					'wildcard'	=> $wildcard,
					'uri'				=> is_string( $route['uri'] ?? null ) === true && $route['uri'] !== '' ? $route['uri'] : $path,
					'locale'		=> is_string( $route['locale'] ?? null ) === true ? $route['locale'] : '',
					'status'		=> (int) ( $route['statusCode'] ?? 200 ),
					'html'			=> self::answersHtml( $route ),
				];
			}

			return $pages;
		}

		/**
		 *	Whether a uri is one of the patterns of a list: the match a blacklist
		 *	of the cache, an exclusion of a sitemap or a statistic and the paths
		 *	of a protected area all make. A pattern is an exact uri ('/contact') or
		 *	a subtree ('/blog/*', which covers /blog itself and everything below
		 *	it, and not '/blogging'). Plain string work on purpose - a pattern is
		 *	editable text, and handing editable text to preg_match() makes the
		 *	expression itself an input. A pattern that is no string, or is empty,
		 *	matches nothing.
		 *
		 *	@param		string		$uri					A request path without the directory, eg. '/blog/post-1'
		 *	@param		array 		$patterns			Exact uris and subtrees
		 *
		 *	@return 	bool
		 */
		public static function matchesPath( string $uri, array $patterns ): bool {

			foreach( $patterns as $pattern ) {

				if( is_string( $pattern ) === false || $pattern === '' )
					continue;

				if( str_ends_with( $pattern, '/*' ) === false ) {
					if( $uri === $pattern )
						return true;
					continue;
				}

				$prefix = substr( $pattern, 0, -2 );

				if( $uri === $prefix || str_starts_with( $uri, $prefix. '/' ) === true )
					return true;
			}

			return false;
		}

		/**
		 *	Whether an address is one a page cannot have: one of RESERVED_URIS or
		 *	a path below one of them ('/features' and '/features/x', not
		 *	'/featured'). A run of slashes is one slash and the case of the letters
		 *	does not matter - the server and a case-insensitive filesystem judge an
		 *	address that way (see router.php), and an address is ASCII. '/docs' and
		 *	'/history' count while their directory is in the checkout.
		 *
		 *	@param		string		$uri					An address without the directory, eg. '/docs/guide'
		 *	@param		string|null	$root				The checkout's root; the one this file is in unless a test names another
		 *
		 *	@return 	bool
		 */
		public static function isReservedUri( string $uri, ?string $root = null ): bool {

			$uri = strtolower( (string) preg_replace( '#/{2,}#', '/', $uri ) );
			$root ??= dirname( __DIR__, 3 );
			$patterns = [];

			foreach( self::RESERVED_URIS as $reserved ) {

				if( in_array( $reserved, self::WHILE_PRESENT, true ) === true && is_dir( $root. $reserved ) === false )
					continue;

				$patterns[] = $reserved. '/*';
			}

			return self::matchesPath( $uri, $patterns );
		}

		/**
		 *	Whether a route answers html - the one thing a visitor can be asked
		 *	for a password in front of, or a sitemap lists. The first header the
		 *	route names Content-Type (in any case of letters) decides: html is a
		 *	value that starts with text/html, with a charset or not. A route that
		 *	names none is a page, since html is what the kernel answers by
		 *	default.
		 *
		 *	A response answers it the same way, its header being the same map:
		 *	Http::answersHtml( $request['/nino/http/response'] ).
		 *
		 *	@param		array 		$route				A route, or a response, with an optional 'header' map
		 *
		 *	@return 	bool
		 */
		public static function answersHtml( array $route ): bool {

			foreach( is_array( $route['header'] ?? null ) === true ? $route['header'] : [] as $name => $value )
				if( is_string( $name ) === true && strtolower( $name ) === 'content-type' )
					return is_string( $value ) === true && str_starts_with( strtolower( trim( $value ) ), 'text/html' ) === true;

			return true;
		}

		/**
		 *	A request this process has handled, by its place in the list
		 *	Http::request() keeps - the newest at 0. It is the copy stored when
		 *	the request began, with its '/nino/http/request' and the response as
		 *	it was seeded then: what code that has no request in hand, a shortcode
		 *	above all, can read of one. It does not see what a callback does to
		 *	the live response afterwards.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int			$offset				0 for the current request
		 *
		 *	@return 	array|false						The request, false where there is none
		 */
		public static function getRequest( array &$appData, int $offset = 0 ): array|false {
			return $appData['./nino/http/requests'][$offset] ?? false;
		}

		// Return clean uri
		/**
		 *	The request path as the routes know it: without the directory the
		 *	project is served from. Routes are keyed project-relative
		 *	('GET://about'), and every address the kernel writes - a form's
		 *	action, a menu entry, the workbench's own endpoint - puts
		 *	[[/nino/dir]] in front of such a key. What comes back from the
		 *	browser therefore carries the directory, and a site at
		 *	example.com/shop/ answered every one of its own addresses with the
		 *	404 page: '/shop/about' was looked up as it stood, and no route
		 *	is keyed that way. Only the leading segment that is the directory
		 *	comes off - '/shopping' on a site at '/shop' is left alone - and
		 *	the directory on its own is the site's root.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					A cleaned request path (see cleanUri())
		 *
		 *	@return 	string
		 */
		static private function _projectUri( array &$appData, string $uri ): string {

			$dir = rtrim( (string) ( $appData['/nino/dir'] ?? '' ), '/' );

			if( $dir === '' )
				return $uri;

			if( $uri === $dir )
				return '/';

			return str_starts_with( $uri, $dir. '/' ) === true
				? substr( $uri, strlen( $dir ) )
				: $uri;
		}

		static private function cleanUri( string $rawUri ): string {

			/*	The path is what comes before the first '?' or '#'. strtok()
				was the wrong primitive for that twice over: it skips leading
				delimiters, so '#fragment' came back as 'fragment' - the
				fragment standing in for the path - and it answers false for a
				string that holds nothing but delimiters, so '' and '#' reached
				the second call as false. That is a TypeError under
				strict_types, thrown inside request() before anything could
				answer at all, and an empty REQUEST_URI is not hypothetical
				(see the note in request()).

				strcspn() is the cut that was meant: the length of the leading
				run containing neither character, for every input including
				the empty one	*/
			$cleanUri = substr( $rawUri, 0, strcspn( $rawUri, '?#' ) );
			$cleanUri = (string) preg_replace( '/[^a-zA-Z0-9:\/\.~_\-%]/', '', $cleanUri );
			$cleanUri = rtrim( $cleanUri, '/' );

			if( $cleanUri === '' )
				$cleanUri = '/';

			return $cleanUri;
		}

		// The one header a forwarded client address is read from. Client-Ip
		// and the rest are not: every proxy that forwards an address writes
		// this one, and a second spelling would be a second thing the
		// allowlist below has to stay in sync with
		private const string FORWARDED_FOR = 'HTTP_X_FORWARDED_FOR';

		/**
		 *	Return current client ip address.
		 *
		 *	The TCP peer address (REMOTE_ADDR) is the answer, unless that peer
		 *	is itself a proxy this site was configured to trust:
		 *	X-Forwarded-For is an ordinary request header any client can set to
		 *	an arbitrary value, so a forwarded address is worth exactly as much
		 *	as the hop that wrote it. Read unconditionally it would let the
		 *	login cooldown's ip bucket (see Auth::loginUser()), the activity
		 *	log's ip field and the address a session is listed under all be
		 *	set by the request itself. A session is not pinned to an address -
		 *	its token is the key it lives under, and the ip beside it is the
		 *	display value the workbench's session list shows.
		 *
		 *	Behind a reverse proxy (Cloudflare, a load balancer, ...) the peer
		 *	address is the proxy's own for every single visitor, though, and
		 *	then every per-ip rule in the site collapses into one bucket:
		 *	Mail's send cap becomes a per-site cap, where five
		 *	contact-form submissions from anyone lock out every visitor's mail
		 *	(newsletter confirmations included) for the rest of the window -
		 *	silently, since a rate-limit refusal is not surfaced as an error.
		 *	Auth's ip bucket becomes one too, and it is the larger of the two:
		 *	maxtries * IP_TRIES_FACTOR wrong logins against account names that
		 *	need not exist put that one address in cooldown, and every admin's
		 *	correct password is refused for as long as it lasts.
		 *
		 *	So the peer has to be named: '/nino/http/proxies' lists the proxies
		 *	in front of this site, as exact addresses or cidr ranges. Where the
		 *	peer is one of them, the client is the rightmost X-Forwarded-For
		 *	hop that is not itself a listed proxy - everything left of it is
		 *	whatever the client sent (a forwarding proxy appends, it does not
		 *	verify) and is never read. An empty list, which is the default,
		 *	keeps the plain behaviour: the header is ignored and the peer is
		 *	the client. So does calling this without $appData, for the few
		 *	callers that have none.
		 *
		 *	@param		array 		$appData			Array with current app data; [] for the few callers that have none
		 *
		 *	@return 	string							The peer address (REMOTE_ADDR), or the client behind a proxy this site names under '/nino/http/proxies'
		 */
		public static function getClientIp( array $appData = [] ): string {

			$remote 	= $_SERVER['REMOTE_ADDR'] ?? '';
			$proxies	= $appData['/nino/http/proxies'] ?? [];

			if( is_array( $proxies ) === false || $proxies === []
				|| self::_isTrustedProxy( $remote, $proxies ) === false )
				return $remote;

			$hops = [];
			foreach( explode( ',', (string) ( $_SERVER[ self::FORWARDED_FOR ] ?? '' ) ) as $hop )
				if( ( $hop = self::_cleanIp( $hop ) ) !== '' )
					$hops[] = $hop;

			if( $hops === [] )
				return $remote;

			// Right to left: the rightmost hop is the one our own proxy
			// wrote, the one before it what the proxy before that wrote. The
			// walk stops at the first address no trusted hop vouches for
			for( $i = count( $hops ) - 1; $i >= 0; $i-- )
				if( self::_isTrustedProxy( $hops[$i], $proxies ) === false )
					return $hops[$i];

			// Every hop is a trusted proxy of ours, so the visitor is one of
			// them and the leftmost entry is as close to them as the chain
			// gets
			return $hops[0];
		}

		// One X-Forwarded-For entry down to a bare address: the spacing the
		// header is written with goes, a port some proxies append goes
		// ('198.51.100.7:53238', '[2001:db8::7]:53238'), and what is left has
		// to be an ip address - so a hop cannot smuggle in a hostname, a cidr
		// range or a rate-limit key of its own choosing
		private static function _cleanIp( string $raw ): string {

			$ip = trim( $raw );

			if( str_starts_with( $ip, '[' ) === true ) {

				// [v6]:port - the brackets are there to keep the port apart
				// from the address' own colons
				$end	= strpos( $ip, ']' );
				$ip 	= ( $end !== false ) ? substr( $ip, 1, $end - 1 ) : substr( $ip, 1 );
			}
			// A single colon is a v4 address with a port; several are a v6
			// address, which carries no port without the brackets above
			else if( substr_count( $ip, ':' ) === 1 )
				$ip = explode( ':', $ip )[0];

			return ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) ? $ip : '';
		}

		// Is this address one of the configured proxies? An entry is an exact
		// address or a cidr range ('10.0.0.0/8', '2001:db8::/32'); anything
		// else in the list matches nothing rather than everything, because
		// the failure mode of a typo here is trusting a header
		private static function _isTrustedProxy( string $ip, array $proxies ): bool {

			if( $ip === '' )
				return false;

			foreach( $proxies as $proxy )
				if( is_string( $proxy ) === true && self::_ipInRange( $ip, trim( $proxy ) ) === true )
					return true;

			return false;
		}

		// Compare packed addresses rather than text: inet_pton() turns both
		// sides into the same bytes whatever notation they were written in
		// ('::1' and '0:0:0:0:0:0:0:1' are one address, '10.0.0.1' has one
		// spelling but a v4-mapped v6 peer does not), and a cidr range is
		// then its first bits. v4 and v6 never match each other, since their
		// packed forms differ in length.
		private static function _ipInRange( string $ip, string $range ): bool {

			if( $range === '' )
				return false;

			$bits = null;

			if( str_contains( $range, '/' ) === true ) {

				[ $range, $prefix ] = explode( '/', $range, 2 );

				if( ctype_digit( $prefix ) === false )
					return false;

				$bits = (int) $prefix;
			}

			$packedIp 		= @inet_pton( $ip );
			$packedRange	= @inet_pton( $range );

			if( $packedIp === false || $packedRange === false
				|| strlen( $packedIp ) !== strlen( $packedRange ) )
				return false;

			if( $bits === null )
				return $packedIp === $packedRange;

			if( $bits > strlen( $packedRange ) * 8 )
				return false;

			$bytes 	= intdiv( $bits, 8 );
			$rest 	= $bits % 8;

			if( $bytes > 0 && strncmp( $packedIp, $packedRange, $bytes ) !== 0 )
				return false;

			if( $rest === 0 )
				return true;

			// The byte the prefix ends inside of: compare only its leading
			// $rest bits
			$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;

			return ( ord( $packedIp[$bytes] ) & $mask ) === ( ord( $packedRange[$bytes] ) & $mask );
		}

		// PHP_AUTH_USER/PHP_AUTH_PW are populated by some SAPIs, while CGI and
		// FastCGI commonly expose only HTTP_AUTHORIZATION after the web server
		// has been configured to pass it through. Prefer PHP's parsed values for
		// backwards compatibility, then decode one strict Basic credential pair
		// from the already normalized request header - or, where the header did
		// not survive the hop into the script at all, from the environment
		// variable the documented Apache fallback copies it into (see
		// _getRawAuthorization()). A password may contain a colon, so split only
		// at the first one.
		private static function _getBasicAuthCredentials( array $rawServer, array $header ): array {

			if( array_key_exists( 'PHP_AUTH_USER', $rawServer ) === true || array_key_exists( 'PHP_AUTH_PW', $rawServer ) === true )
				return [
					'user' 	=> is_string( $rawServer['PHP_AUTH_USER'] ?? null ) ? $rawServer['PHP_AUTH_USER'] : '',
					'pw' 		=> is_string( $rawServer['PHP_AUTH_PW'] ?? null ) ? $rawServer['PHP_AUTH_PW'] : '',
				];

			$authorization = $header['Authorization'] ?? '';

			if( is_string( $authorization ) === false || $authorization === '' )
				$authorization = self::_getRawAuthorization( $rawServer );

			if( preg_match( '/^Basic[ \t]+([A-Za-z0-9+\/]+={0,2})$/iD', trim( $authorization ), $matches ) !== 1 )
				return [ 'user' => '', 'pw' => '' ];

			$decoded = base64_decode( $matches[1], true );
			if( $decoded === false || str_contains( $decoded, ':' ) === false )
				return [ 'user' => '', 'pw' => '' ];

			[ $user, $pw ] = explode( ':', $decoded, 2 );

			return [ 'user' => $user, 'pw' => $pw ];
		}

		/*	The Authorization header as an environment variable, for the servers
			that hand a script no header at all.

			Apache strips Authorization from CGI and FastCGI requests unless
			CGIPassAuth is on, and that directive needs 2.4.13 - on an older one
			(and on a php-cgi wrapper it does not cover) the .htaccess falls back
			to copying the header into an environment variable:

			  RewriteCond %{HTTP:Authorization} .
			  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

			A variable set during an internal redirect reaches the script with a
			REDIRECT_ prefix, and each further redirect adds another one - so the
			name is not one value to look up but a small family, and which member
			arrives is the server's business rather than the project's. Nothing a
			client sends can land here: a request header becomes HTTP_<NAME>, so
			a header literally called "Redirect-Http-Authorization" arrives as
			HTTP_REDIRECT_HTTP_AUTHORIZATION and does not match.

			@param		array 		$rawServer		$_SERVER as request() received it

			@return 	string									'' when no variant carries anything */
		private static function _getRawAuthorization( array $rawServer ): string {

			foreach( $rawServer as $key => $value )
				if( is_string( $key ) === true && is_string( $value ) === true && $value !== ''
					&& preg_match( '/^(?:REDIRECT_)+HTTP_AUTHORIZATION$/', $key ) === 1 )
					return $value;

			return '';
		}

		// Build the request-side header array from $_SERVER (passed in as
		// $rawServer). PHP exposes request headers as HTTP_FOO_BAR keys
		// (Content-Type/-Length are the CGI-spec exception, without the
		// HTTP_ prefix) - filterHeaderFields() expects header names like
		// 'Foo-Bar' as keys, so this turns the underscores into hyphens
		// first. The case left ('ACCEPT-LANGUAGE') doesn't matter:
		// filterHeaderFields() matches case-insensitively and returns its
		// own allowlist's casing, since header names are case-insensitive
		// per HTTP anyway.
		static private function _filterRequestHeaderFields( array $rawServer ): array {

			$normalized = [];

			foreach( $rawServer as $key => $value ) {

				if( str_starts_with( $key, 'HTTP_' ) === true )
					$headerName = substr( $key, 5 );
				elseif( in_array( $key, [ 'CONTENT_TYPE', 'CONTENT_LENGTH' ], true ) === true )
					$headerName = $key;
				else
					continue;

				$normalized[str_replace( '_', '-', $headerName )] = $value;
			}

			return self::filterHeaderFields( $normalized );
		}

		// Keep the request headers Nino knows and drop every other key.
		// Matches case-insensitively and normalizes to the allowlist's own
		// casing - the request side arrives upper-cased from $_SERVER, and
		// an exact match would drop nearly every header ('Accept-Language'
		// arrives as 'ACCEPT-LANGUAGE'). Response-only names -
		// Location, the Content-Security-Policy and the other security
		// headers Nino sends - are not on it: a client that sends one is
		// sending nothing Nino reads.
		static public function filterHeaderFields( array $headerArray ): array {

			$allowed = [ 'Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language', 'Authorization', 'Cache-Control', 'Connection', 'Content-Length', 'Content-Type', 'Cookie', 'Date', 'Expect', 'From', 'Host', 'If-Modified-Since', 'If-None-Match', 'Max-Forwards', 'Origin', 'Pragma', 'Proxy-Authorization', 'Range', 'Referer', 'TE', 'User-Agent', 'Upgrade', 'Via', 'Warning', 'X-CSRF-Token' ];

			$filteredArray = [];

			foreach( $headerArray as $key => $value )
				foreach( $allowed as $canonical )
					if( strcasecmp( $key, $canonical ) === 0 ) {
						$filteredArray[$canonical] = $value;
						break;
					}

			return $filteredArray;
		}


		private static function _getRequestQueryVarsPart( string $rawRequest ): array {

			// Parse query vars
			$queryVars = [];
			$parsedUrl = @parse_url( $rawRequest, PHP_URL_QUERY );
			if( is_string( $parsedUrl ) === false || $parsedUrl === '' )
				return $queryVars;

			/*	parse_str() reads max_input_vars pairs and no key nested deeper than
				max_input_nesting_level, and raises an E_WARNING for what it drops -
				the second only where display_errors is off, which is where a site is
				live. An engine warning is fatal here (see Runtime::NON_FATAL_LEVELS),
				so one query of 1,001 variables, or of a key with 65 brackets, was a
				500 on every address. The same two cuts are made before php is asked:
				the first pairs are kept, the keys that are too deep are left out, and
				what it reads is what it would have kept, but for three edges that no
				query worth sending reaches: an empty pair counts here as one of the
				pairs and php skips it, a [ counts as a level wherever it stands in a
				key where php counts the [..] groups behind the name, and php drops
				the whole variable of a key that is too deep where this leaves out the
				key alone. A pair ends at any byte of arg_separator.input, as it does
				for php - the & of the default, and whatever a host has set there	*/
			$limit			= (int) ( ini_get( 'max_input_vars' ) ?: 1000 );
			$depth			= (int) ( ini_get( 'max_input_nesting_level' ) ?: 64 );
			$separators	= (string) ( ini_get( 'arg_separator.input' ) ?: '&' );
			$pairs			= [];
			foreach( array_slice( preg_split( '/['. preg_quote( $separators, '/' ). ']/', $parsedUrl, $limit + 1 ) ?: [], 0, $limit ) as $pair )
				if( substr_count( urldecode( explode( '=', $pair, 2 )[0] ), '[' ) <= $depth )
					$pairs[] = $pair;
			parse_str( implode( $separators[0], $pairs ), $queryVars );

			return $queryVars;
		}


		// Clean request methods
		static private function _cleanRawMethod( string $rawMethod, bool $mapHead = true ): string {

			$legalMethods = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH' ];

			$cleanMethod	= preg_replace( '/[^a-zA-Z]/', '', $rawMethod );
			$cleanMethod	= strtoupper( $cleanMethod );

			// Exact match rather than a substring test - 'XXGETXX' used to be
			// accepted as GET, since any legal method contained anywhere in the
			// value won
			if( in_array( $cleanMethod, $legalMethods, true ) === false )
				return '';

			// HEAD is a GET without a response body, but routes are only ever
			// registered for GET - so every HEAD request (uptime monitors, link
			// checkers, some crawlers) found no route and answered 404. The
			// unmapped value stays available as the request's 'rawMethod', which
			// is what output() suppresses the body by.
			return ( $mapHead === true && $cleanMethod === 'HEAD' ) ? 'GET' : $cleanMethod;
		}
	}
}
