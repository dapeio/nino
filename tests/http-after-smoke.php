<?php
declare(strict_types=1);

/**
 *	Nino
 *	http-after-smoke.php	Work asked for with \Nino\Http::afterResponse(): it runs once the response is out, and the
 *												response tells the length of the bytes that go out - counted after every callback of
 *												'/nino/http/output' and after the body was encoded - where no server ends the connection
 *												itself. A response without asked-for work is the response it always was. What cannot be
 *												let go does not run the work. The requests are child processes, since output() exit()s.
 *
 *	Usage: php tests/http-after-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'http-after' );
$sandbox = ninoSandboxDir( $appData );

$child = $sandbox. '/child.php';
file_put_contents( $child, '<?php
declare(strict_types=1);
$root = $argv[1]; $sandbox = $argv[2]; $case = $argv[3]; $ask = $argv[4] === "1";
require $root . "/tests/harness.php";
$appData = [ "./nino/uid" => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"] = $sandbox; $appData["./nino/filesystem/configpath"] = $sandbox . "/private"; $appData["./nino/filesystem/contentpath"] = $sandbox . "/private"; $appData["./nino/filesystem/publicpath"] = $sandbox . "/public";
$appData["/nino/dir"] = ""; $appData["/nino/install/texts"] = \Nino\VERSION; $appData["./nino/locales/current"] = "de_DE"; $appData["/nino/locales/native"] = "de_DE"; $appData["/nino/locales/available"] = [ "de_DE" ];
register_shutdown_function( static function(): void { foreach( ninoWarnings() as $warning ) echo "\nWARN: ", $warning; } );
$response = [ "statusCode" => 200, "header" => [ "Content-Type" => "text/html; charset=utf-8" ], "body" => "<p>Grüße</p>" ];
$method = "GET";
if( $case === "session" ) { ini_set( "session.save_path", $sandbox ); session_start(); }
if( $case === "handler" ) ob_start( static fn( string $buffer ): string => $buffer );
if( $case === "buffer" ) ob_start();
if( $case === "late" ) echo " ";
if( $case === "json" ) $response["body"] = [ "a" => "ü" ];
if( $case === "head" ) $method = "HEAD";
if( $case === "banner" ) \Nino\Callbacks::registerCallback( $appData, "/nino/http/output", static function( array &$appData, array &$request ): void { $request["/nino/http/response"]["body"] .= "<div class=banner>Wartung</div>"; } );
if( $case === "length" ) $response["header"]["Content-Length"] = "5";
if( $case === "encoded" ) $response["header"]["Content-Encoding"] = "gzip";
if( $case === "empty" ) { $response["statusCode"] = 304; $response["body"] = ""; }
$request = [ "/nino/http/request" => [ "method" => "GET", "rawMethod" => $method ], "/nino/http/response" => $response ];
if( $case === "throw" )
	\Nino\Http::afterResponse( $appData, static function(): void { throw new \RuntimeException( "first work" ); } );
if( $case === "fail" )
	\Nino\Http::afterResponse( $appData, static function(): void { \Nino\Runtime::fail( "first work" ); } );
if( $ask )
	\Nino\Http::afterResponse( $appData, static function() use ( $sandbox, &$request ): void { file_put_contents( $sandbox . "/work.txt", json_encode( [ "Content-Length: " . ( $request["/nino/http/response"]["header"]["Content-Length"] ?? "none" ), "session: " . session_status() ] ) ); } );
\Nino\Callbacks::doCallbacks( $appData, "/nino/http/output", $request );
\Nino\Http::output( $appData, $request );
' );

/**
 *	One request in a child process, with the asked-for work or without
 *
 *	@return 	array										{ out, work (the headers the work saw, null where it did not run) }
 */
$run = static function( string $case, bool $ask, array $ini = [], bool $keep = false ) use ( $child, $sandbox ): array {

	@unlink( $sandbox. '/work.txt' );

	// The warning of a dropped work is said once an hour: each request of the tests is the first, but where a test asks for the hour
	if( $keep === false )
		@unlink( $sandbox. '/private/data/after-response-dropped.php' );
	$command = array_merge( [ PHP_BINARY ], $ini, [ $child, dirname( __DIR__ ), $sandbox, $case, $ask === true ? '1' : '0' ] );
	$process = proc_open( $command, [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	$out = stream_get_contents( $pipes[1] ). stream_get_contents( $pipes[2] );
	proc_close( $process );

	return [ 'out' => $out, 'work' => is_file( $sandbox. '/work.txt' ) === true ? json_decode( (string) file_get_contents( $sandbox. '/work.txt' ), true ) : null ];
};

$length = static fn( ?array $headers ): ?string => $headers === null ? null : ( array_values( array_filter( array_map( static fn( string $line ): ?string => stripos( $line, 'Content-Length: ' ) === 0 ? substr( $line, 16 ) : null, $headers ) ) )[0] ?? null );

echo "Work after the response\n";

$html = $run( 'html', true );
check( 'the work runs after the last byte is out, and the response tells the length of its body in bytes', $html['out'] === '<p>Grüße</p>' && $length( $html['work'] ) === '14' );

$banner = $run( 'banner', true );
$bannerBody = '<p>Grüße</p><div class=banner>Wartung</div>';
check( 'the length is that of the page after every callback of the output has written into it - a banner among them', $banner['out'] === $bannerBody && $length( $banner['work'] ) === '45' && strlen( $bannerBody ) === 45 );

$json = $run( 'json', true );
check( 'the length of a response whose body is encoded at the output is that of the encoded bytes', $length( $json['work'] ) === (string) strlen( $json['out'] ) && str_contains( $json['out'], '"a"' ) === true );

$head = $run( 'head', true );
check( 'a HEAD request sends no body, tells the length of the page a GET gets, and the work runs', $head['out'] === '' && $length( $head['work'] ) === (string) strlen( '<p>Grüße</p>' ) );

$session = $run( 'session', true );
check( 'the session of the visitor is closed before the work, so that the next request of the same browser does not queue behind it', ( $session['work'][1] ?? '' ) === 'session: '. PHP_SESSION_NONE );

foreach( [ 'length' => 'a length somebody else set', 'encoded' => 'an encoding somebody else set', 'empty' => 'a status without a body' ] as $case => $what )
	check( 'with '. $what. ' the response goes out as it is and the work does not run in this request', $run( $case, true )['work'] === null && str_contains( $run( $case, true )['out'], 'Fatal' ) === false );

$compressed = $run( 'html', true, [ '-d', 'zlib.output_compression=1' ] );
check( 'a page php compresses itself is not compressed for the response that carries work: the compression is off for it, and the length is its body\'s', $length( $compressed['work'] ) === '14' && $compressed['out'] === '<p>Grüße</p>' );
$late = $run( 'late', true, [ '-d', 'zlib.output_compression=1', '-d', 'display_errors=1' ] );
check( '...where it cannot be turned off - the output has begun - the work is dropped for this request and a warning says so, once', $late['work'] === null && substr_count( $late['out'], 'Work after the response is dropped' ) === 1 );

$handler = $run( 'handler', true );
check( 'an output buffer with a handler of its own gets no length and the work does not run: a handler may change what goes out', $handler['work'] === null && str_starts_with( $handler['out'], '<p>Grüße</p>' ) && substr_count( $handler['out'], 'Work after the response is dropped' ) === 1 );
$again = $run( 'handler', true, [], true );
check( '...and that is said once an hour: the next request that drops its work says nothing, and does not fill the log', $again['work'] === null && str_contains( $again['out'], 'Work after the response is dropped' ) === false );
$buffer = $run( 'buffer', true );
check( '...a buffer without one is flushed, and the length is the body\'s', $length( $buffer['work'] ) === '14' && $buffer['out'] === '<p>Grüße</p>' );
$throw = $run( 'throw', true );
check( 'work that throws does not end the work that comes after it, and says so in the log', $length( $throw['work'] ) === '14' && str_contains( $throw['out'], 'first work' ) === true );

$fail = $run( 'fail', true );
check( 'work that ends the request with Runtime::fail() ends it: the work after it does not run', $fail['work'] === null && str_contains( $fail['out'], 'first work' ) === true );

// A real server that compresses every page, and a client that takes what is compressed
$router = $sandbox. '/router.php';
file_put_contents( $router, '<?php
$root = getenv( "NINO_ROOT" ); $sb = getenv( "NINO_SANDBOX" );
require $root . "/_nino/Nino.php";
$appData = [ "./nino/uid" => $sb ];
\Nino\AppData::prepare( $appData );
foreach( [ "path" => $sb, "configpath" => $sb . "/private", "contentpath" => $sb . "/private", "publicpath" => $sb . "/public" ] as $n => $v ) $appData["./nino/filesystem/" . $n] = $v;
$appData["/nino/dir"] = "";
if( isset( $_GET["work"] ) ) \Nino\Http::afterResponse( $appData, static function() use ( $sb ): void { usleep( 300000 ); file_put_contents( $sb . "/served.txt", "x", FILE_APPEND ); } );
$request = [ "/nino/http/request" => [ "method" => "GET", "rawMethod" => "GET" ], "/nino/http/response" => [ "statusCode" => 200, "header" => [ "Content-Type" => "text/html" ], "body" => str_repeat( "<p>Grüße aus dem Test</p>", 200 ) ] ];
\Nino\Http::output( $appData, $request );
' );
$probe = stream_socket_server( 'tcp://127.0.0.1:0' );
$port = (int) substr( (string) stream_socket_get_name( $probe, false ), (int) strrpos( (string) stream_socket_get_name( $probe, false ), ':' ) + 1 );
fclose( $probe );
$server = proc_open( [ PHP_BINARY, '-d', 'zlib.output_compression=1', '-S', '127.0.0.1:'. $port, $router ], [ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $serverPipes, $sandbox, [ 'NINO_ROOT' => dirname( __DIR__ ), 'NINO_SANDBOX' => $sandbox ] + $_ENV + [ 'PATH' => (string) getenv( 'PATH' ) ] );
usleep( 700000 );
$fetch = static function( string $path ) use ( $port ): array {
	$socket = stream_socket_client( 'tcp://127.0.0.1:'. $port, $errno, $errstr, 5 );
	$start = microtime( true );
	fwrite( $socket, "GET ". $path. " HTTP/1.1\r\nHost: x\r\nAccept-Encoding: gzip\r\nConnection: close\r\n\r\n" );
	$headers = '';
	while( ( $line = fgets( $socket ) ) !== false && $line !== "\r\n" )
		$headers .= $line;
	// A client that does what a browser does: stops at the length it was told
	preg_match( '/^Content-Length: (\d+)/mi', $headers, $length );
	$body = isset( $length[1] ) === true ? (string) stream_get_contents( $socket, (int) $length[1] ) : (string) stream_get_contents( $socket );
	$took = microtime( true ) - $start;
	fclose( $socket );
	return [ 'headers' => $headers, 'body' => $body, 'length' => isset( $length[1] ) === true ? (int) $length[1] : null, 'took' => $took ];
};
@unlink( $sandbox. '/served.txt' );
$full = str_repeat( '<p>Grüße aus dem Test</p>', 200 );
$withWork = $fetch( '/?work=1' );
usleep( 600000 );
$plain = $fetch( '/' );
fclose( $serverPipes[1] ?? STDIN );
proc_terminate( $server );
check( 'on a server that compresses every page, the response that carries work is whole and not compressed, as long as its body, and the client has it before the work is done', $withWork['body'] === $full && $withWork['length'] === strlen( $full ) && stripos( $withWork['headers'], 'Content-Encoding' ) === false && $withWork['took'] < 0.25 );
check( '...the work ran, once, and the next response is compressed again', @file_get_contents( $sandbox. '/served.txt' ) === 'x' && stripos( $plain['headers'], 'Content-Encoding: gzip' ) !== false );

foreach( [ 'html', 'json', 'head', 'banner', 'length', 'encoded', 'empty' ] as $case ) {
	$plain = $run( $case, false );
	check( 'without asked-for work, "'. $case. '" is sent as it always was: no length of the kernel, no work', $plain['work'] === null && ( $case === 'length' || $case === 'encoded' || $case === 'empty' || str_contains( $plain['out'], 'Content-Length' ) === false ) );
}

// The decision alone, for what a child cannot make: a stream, and a server that ends the connection itself
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => 'x', 'header' => [] ], './nino/http/stream' => [ 'path' => '/x' ] ];
\Nino\Http::afterResponse( $appData, static function(): void {} );
check( 'a response that streams a file is sent as it is and the work is dropped', \Nino\Http::prepareAfter( $appData, $request ) === [] && isset( $request['/nino/http/response']['header']['Content-Length'] ) === false && isset( $appData['./nino/http/after'] ) === false );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'body' => 'x', 'header' => [] ] ];
check( 'nothing asked for, nothing decided: the response is not touched', \Nino\Http::prepareAfter( $appData, $request ) === [] && $request['/nino/http/response']['header'] === [] );

echo "\n";

ninoDone( $appData );
